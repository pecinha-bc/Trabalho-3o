<?php

require_once __DIR__ . '/../conexao.php';

class MotorBatalha
{
    private PDO $pdo;
    private array $efeitosPendentes = [];

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function consultar(int $batalhaId, int $perfilId): array
    {
        $this->pdo->beginTransaction();

        try {
            $batalha = $this->buscarBatalha($batalhaId, $perfilId);
            $pokemons = $this->buscarPokemons($batalhaId);
            $estado = $this->carregarEstado($batalha, $pokemons);
            $this->salvarEstado($batalhaId, $estado);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $consulta = $this->pdo->prepare(
            "SELECT tipo_evento, descricao FROM batalha_evento
             WHERE batalha_id = :id ORDER BY id DESC LIMIT 25"
        );
        $consulta->execute([':id' => $batalhaId]);

        return [
            'batalha' => $batalha,
            'pokemons' => $pokemons,
            'estado' => $estado,
            'eventos' => $consulta->fetchAll()
        ];
    }

    public function executar(
        int $batalhaId,
        int $perfilId,
        string $acao,
        int $referencia = 0,
        int $alvoId = 0
    ): array {
        $this->pdo->beginTransaction();

        try {
            $batalha = $this->buscarBatalha($batalhaId, $perfilId);
            if ($batalha['resultado'] !== 'em_andamento') {
                throw new RuntimeException('Esta batalha ja terminou.');
            }

            $pokemons = $this->buscarPokemons($batalhaId);
            $estado = $this->carregarEstado($batalha, $pokemons);
            $this->efeitosPendentes = [];
            $limite = [
                'facil' => 3,
                'medio' => 5,
                'dificil' => 7
            ][$batalha['dificuldade']];

            $jogadorId = (int) $estado['jogador_ativo'];
            $npcId = (int) $estado['npc_ativo'];

            if (!$jogadorId || !$npcId) {
                throw new RuntimeException('Nao ha Pokemon disponiveis para lutar.');
            }

            if ($acao === 'atacar') {
                $ataque = $this->buscarAtaque(
                    (int) $pokemons[$jogadorId]['pokemon_id'],
                    $referencia
                );
                if (!$ataque) {
                    throw new InvalidArgumentException('Ataque nao permitido.');
                }

                $itemNpc = $this->escolherItemNpc($pokemons, $estado, $limite);
                if ($itemNpc !== null) {
                    $this->usarItemNpc(
                        $batalhaId, $pokemons, $estado,
                        $itemNpc['item'], $itemNpc['alvo']
                    );
                    $this->atacar(
                        $batalhaId, $pokemons, $jogadorId, $npcId, $ataque
                    );
                } elseif (
                    $pokemons[$npcId]['velocidade'] >
                    $pokemons[$jogadorId]['velocidade']
                ) {
                    $this->ataqueNpc($batalhaId, $pokemons, $npcId, $jogadorId);
                    if ($pokemons[$jogadorId]['hp_atual'] > 0) {
                        $this->atacar(
                            $batalhaId, $pokemons, $jogadorId, $npcId, $ataque
                        );
                    }
                } else {
                    $this->atacar(
                        $batalhaId, $pokemons, $jogadorId, $npcId, $ataque
                    );
                    if ($pokemons[$npcId]['hp_atual'] > 0) {
                        $this->ataqueNpc(
                            $batalhaId, $pokemons, $npcId, $jogadorId
                        );
                    }
                }
            } elseif ($acao === 'item') {
                if ($estado['usos_jogador'] >= $limite) {
                    throw new RuntimeException('Seu limite de itens foi atingido.');
                }
                if (!isset($pokemons[$alvoId]) ||
                    $pokemons[$alvoId]['lado'] !== 'jogador') {
                    throw new InvalidArgumentException('Alvo de item invalido.');
                }

                $item = $this->buscarItem($referencia);
                if (!$item || $item['tipo_efeito'] === 'evolucao' ||
                    !(int) $item['aparece_inventario']) {
                    throw new InvalidArgumentException('Item nao permitido.');
                }
                if (!$this->aplicarItem($pokemons[$alvoId], $item)) {
                    throw new RuntimeException('Este item nao pode ser usado nesse Pokemon.');
                }

                $consulta = $this->pdo->prepare(
                    "UPDATE inventario SET quantidade = quantidade - 1
                     WHERE perfil_id = :perfil AND item_id = :item
                     AND quantidade > 0"
                );
                $consulta->execute([
                    ':perfil' => $perfilId,
                    ':item' => $referencia
                ]);
                if ($consulta->rowCount() !== 1) {
                    throw new RuntimeException('Voce nao possui este item.');
                }

                $estado['usos_jogador']++;
                $this->evento(
                    $batalhaId, 'item',
                    'Jogador usou ' . $item['nome'] . ' em ' .
                    $pokemons[$alvoId]['nome'] . '.',
                    $alvoId, null, $referencia
                );
                $this->respostaNpc($batalhaId, $pokemons, $estado, $limite);
            } elseif ($acao === 'trocar') {
                if (!isset($pokemons[$referencia]) ||
                    $pokemons[$referencia]['lado'] !== 'jogador' ||
                    $pokemons[$referencia]['hp_atual'] <= 0 ||
                    $referencia === $jogadorId) {
                    throw new InvalidArgumentException('Troca de Pokemon invalida.');
                }
                $estado['jogador_ativo'] = $referencia;
                $this->evento(
                    $batalhaId, 'informacao',
                    'Jogador enviou ' . $pokemons[$referencia]['nome'] . '.',
                    $referencia
                );
                $this->respostaNpc($batalhaId, $pokemons, $estado, $limite);
            } else {
                throw new InvalidArgumentException('Acao invalida.');
            }

            // aplica queimadura e veneno ao final do turno
            foreach (['jogador_ativo', 'npc_ativo'] as $campo) {
                $id = (int) $estado[$campo];
                if ($id && $pokemons[$id]['hp_atual'] > 0 &&
                    in_array($pokemons[$id]['status'], ['queimadura', 'veneno'], true)) {
                    $dano = max(1, (int) floor($pokemons[$id]['hp_maximo'] * 0.10));
                    $pokemons[$id]['hp_atual'] = max(
                        0, $pokemons[$id]['hp_atual'] - $dano
                    );
                    $this->evento(
                        $batalhaId, 'dano',
                        $pokemons[$id]['nome'] . ' sofreu ' . $dano .
                        ' de dano por ' . $pokemons[$id]['status'] . '.',
                        $id
                    );
                }
            }

            // os novos status entram em vigor apenas no fim do turno
            foreach ($this->efeitosPendentes as $efeito) {
                $id = $efeito['pokemon_id'];
                if ($pokemons[$id]['hp_atual'] > 0 &&
                    $pokemons[$id]['status'] === 'nenhum') {
                    $pokemons[$id]['status'] = $efeito['status'];
                    $this->evento(
                        $batalhaId, 'status',
                        $pokemons[$id]['nome'] . ' recebeu ' . $efeito['status'] . '.',
                        $id
                    );
                }
            }

            $resultado = $this->verificarResultado($pokemons);

            if ($resultado === null) {
                $this->ajustarAtivos($batalhaId, $pokemons, $estado);
                $estado['turno']++;
            }

            // grava o estado dos seis pokemons antes de finalizar
            $consulta = $this->pdo->prepare(
                "UPDATE batalha_pokemon
                 SET hp_atual = :hp, status = :status
                 WHERE id = :id AND batalha_id = :batalha"
            );
            foreach ($pokemons as $pokemon) {
                $consulta->execute([
                    ':hp' => $pokemon['hp_atual'],
                    ':status' => $pokemon['status'],
                    ':id' => $pokemon['id'],
                    ':batalha' => $batalhaId
                ]);
            }

            $this->salvarEstado($batalhaId, $estado);

            if ($resultado !== null) {
                $consulta = $this->pdo->prepare(
                    "UPDATE batalha SET resultado = :resultado
                     WHERE id = :id AND resultado = 'em_andamento'"
                );
                $consulta->execute([
                    ':resultado' => $resultado,
                    ':id' => $batalhaId
                ]);
                if ($consulta->rowCount() !== 1) {
                    throw new RuntimeException('Nao foi possivel finalizar a batalha.');
                }
                $this->evento(
                    $batalhaId, 'informacao',
                    'Batalha finalizada: ' . $resultado . '.'
                );
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->consultar($batalhaId, $perfilId);
    }

    private function buscarBatalha(int $batalhaId, int $perfilId): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT b.*, n.nome AS nome_npc, n.dificuldade
             FROM batalha b INNER JOIN npc n ON n.id = b.npc_id
             WHERE b.id = :id AND b.perfil_id = :perfil FOR UPDATE"
        );
        $consulta->execute([':id' => $batalhaId, ':perfil' => $perfilId]);
        $dados = $consulta->fetch();
        if (!$dados) {
            throw new RuntimeException('Batalha nao encontrada para este perfil.');
        }
        return $dados;
    }

    private function buscarPokemons(int $batalhaId): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT bp.id, bp.hp_atual, bp.hp_maximo, bp.status,
                    CASE WHEN bp.pokemon_perfil_id IS NULL
                        THEN 'npc' ELSE 'jogador' END AS lado,
                    COALESCE(pj.id, pn.id) AS pokemon_id,
                    COALESCE(pj.nome, pn.nome) AS nome,
                    COALESCE(pj.tipo, pn.tipo) AS tipo,
                    COALESCE(pp.ataque, pn.ataque) AS ataque,
                    COALESCE(pp.defesa, pn.defesa) AS defesa,
                    COALESCE(pp.velocidade, pn.velocidade) AS velocidade
             FROM batalha_pokemon bp
             LEFT JOIN pokemon_perfil pp ON pp.id = bp.pokemon_perfil_id
             LEFT JOIN pokemon pj ON pj.id = pp.pokemon_id
             LEFT JOIN npc_pokemon np ON np.id = bp.npc_pokemon_id
             LEFT JOIN pokemon pn ON pn.id = np.pokemon_id
             WHERE bp.batalha_id = :id ORDER BY bp.id"
        );
        $consulta->execute([':id' => $batalhaId]);
        $pokemons = [];
        foreach ($consulta->fetchAll() as $p) {
            foreach (['id', 'hp_atual', 'hp_maximo', 'pokemon_id',
                      'ataque', 'defesa', 'velocidade'] as $campo) {
                $p[$campo] = (int) $p[$campo];
            }
            $pokemons[$p['id']] = $p;
        }
        return $pokemons;
    }

    private function carregarEstado(array $batalha, array $pokemons): array
    {
        if (!empty($batalha['log'])) {
            $estado = json_decode($batalha['log'], true);
            if (!is_array($estado) || ($estado['versao'] ?? null) !== 1) {
                throw new RuntimeException('Formato do estado da batalha invalido.');
            }
            return $estado;
        }

        $estoque = [];
        $consulta = $this->pdo->prepare(
            "SELECT item_id, quantidade FROM inventario_npc WHERE npc_id = :id"
        );
        $consulta->execute([':id' => $batalha['npc_id']]);
        foreach ($consulta->fetchAll() as $item) {
            $estoque[(int) $item['item_id']] = (int) $item['quantidade'];
        }

        $estado = [
            'versao' => 1,
            'turno' => 1,
            'jogador_ativo' => 0,
            'npc_ativo' => 0,
            'usos_jogador' => 0,
            'usos_npc' => 0,
            'estoque_npc' => $estoque
        ];
        $this->ajustarAtivos((int) $batalha['id'], $pokemons, $estado);
        return $estado;
    }

    private function salvarEstado(int $batalhaId, array $estado): void
    {
        $consulta = $this->pdo->prepare(
            "UPDATE batalha SET `log` = :estado WHERE id = :id"
        );
        $consulta->execute([
            ':estado' => json_encode(
                $estado, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
            ':id' => $batalhaId
        ]);
    }

    private function ajustarAtivos(
        int $batalhaId, array $pokemons, array &$estado
    ): void {
        foreach (['jogador' => 'jogador_ativo', 'npc' => 'npc_ativo']
                 as $lado => $campo) {
            $atual = (int) ($estado[$campo] ?? 0);
            if ($atual && isset($pokemons[$atual]) &&
                $pokemons[$atual]['hp_atual'] > 0) {
                continue;
            }
            $estado[$campo] = 0;
            foreach ($pokemons as $pokemon) {
                if ($pokemon['lado'] === $lado && $pokemon['hp_atual'] > 0) {
                    $estado[$campo] = $pokemon['id'];
                    $this->evento(
                        $batalhaId, 'informacao',
                        ($lado === 'jogador' ? 'Jogador' : 'NPC') .
                        ' enviou ' . $pokemon['nome'] . '.',
                        $pokemon['id']
                    );
                    break;
                }
            }
        }
    }

    private function buscarAtaque(int $pokemonId, int $ataqueId): ?array
    {
        $consulta = $this->pdo->prepare(
            "SELECT a.* FROM ataque a
             INNER JOIN pokemon_ataque pa ON pa.ataque_id = a.id
             WHERE pa.pokemon_id = :pokemon AND a.id = :ataque"
        );
        $consulta->execute([
            ':pokemon' => $pokemonId,
            ':ataque' => $ataqueId
        ]);
        return $consulta->fetch() ?: null;
    }

    private function ataqueNpc(
        int $batalhaId, array &$pokemons, int $npcId, int $jogadorId
    ): void {
        if ($pokemons[$npcId]['hp_atual'] <= 0 ||
            $pokemons[$jogadorId]['hp_atual'] <= 0) {
            return;
        }
        $consulta = $this->pdo->prepare(
            "SELECT a.* FROM ataque a
             INNER JOIN pokemon_ataque pa ON pa.ataque_id = a.id
             WHERE pa.pokemon_id = :pokemon ORDER BY pa.posicao"
        );
        $consulta->execute([':pokemon' => $pokemons[$npcId]['pokemon_id']]);
        $ataques = $consulta->fetchAll();
        if (!$ataques) {
            throw new RuntimeException('NPC sem ataques cadastrados.');
        }
        $indice = random_int(0, count($ataques) - 1);
        $this->atacar(
            $batalhaId, $pokemons, $npcId, $jogadorId, $ataques[$indice]
        );
    }

    private function atacar(
        int $batalhaId,
        array &$pokemons,
        int $origemId,
        int $destinoId,
        array $ataque
    ): void {
        if ($pokemons[$origemId]['hp_atual'] <= 0 ||
            $pokemons[$destinoId]['hp_atual'] <= 0) {
            return;
        }
        $origem = &$pokemons[$origemId];
        $destino = &$pokemons[$destinoId];

        if ($origem['status'] === 'sono' ||
            $origem['status'] === 'congelamento') {
            $chance = $origem['status'] === 'sono' ? 30 : 20;
            if (random_int(1, 100) > $chance) {
                $this->evento(
                    $batalhaId, 'status',
                    $origem['nome'] . ' nao conseguiu atacar.', $origemId
                );
                return;
            }
            $origem['status'] = 'nenhum';
            $this->evento(
                $batalhaId, 'status',
                $origem['nome'] . ' se recuperou.', $origemId
            );
        }

        if ($origem['status'] === 'paralisia' && random_int(1, 100) <= 25) {
            $this->evento(
                $batalhaId, 'status',
                $origem['nome'] . ' ficou paralisado e perdeu a vez.', $origemId
            );
            return;
        }

        $dano = max(1, (int) floor(
            (int) $ataque['poder_base'] +
            ($origem['ataque'] - $destino['defesa']) / 10
        ));
        $destino['hp_atual'] = max(0, $destino['hp_atual'] - $dano);

        $this->evento(
            $batalhaId, 'ataque',
            $origem['nome'] . ' usou ' . $ataque['nome'] . '.',
            $origemId, (int) $ataque['id']
        );
        $this->evento(
            $batalhaId, 'dano',
            $destino['nome'] . ' recebeu ' . $dano . ' de dano.',
            $destinoId
        );

        if ($destino['hp_atual'] <= 0) {
            $this->evento(
                $batalhaId, 'derrota',
                $destino['nome'] . ' foi derrotado.', $destinoId
            );
            return;
        }

        if ($destino['status'] === 'nenhum' &&
            $ataque['status_causado'] !== 'nenhum' &&
            random_int(1, 100) <= (int) $ataque['chance_status']) {
            $this->efeitosPendentes[] = [
                'pokemon_id' => $destinoId,
                'status' => $ataque['status_causado']
            ];
        }
    }

    private function buscarItem(int $itemId): ?array
    {
        $consulta = $this->pdo->prepare(
            "SELECT * FROM item WHERE id = :id"
        );
        $consulta->execute([':id' => $itemId]);
        return $consulta->fetch() ?: null;
    }

    private function aplicarItem(array &$pokemon, array $item): bool
    {
        $tipo = $item['tipo_efeito'];
        $hp = $pokemon['hp_atual'];
        $maximo = $pokemon['hp_maximo'];
        $percentual = (int) $item['valor_efeito'];

        if ($tipo === 'reviver') {
            if ($hp !== 0) {
                return false;
            }
            $pokemon['hp_atual'] = min(
                $maximo, max(1, (int) ceil($maximo * $percentual / 100))
            );
            $pokemon['status'] = 'nenhum';
            return true;
        }

        if ($hp <= 0) {
            return false;
        }

        if ($tipo === 'cura_hp') {
            if ($hp >= $maximo) {
                return false;
            }
            $cura = max(1, (int) ceil($maximo * $percentual / 100));
            $pokemon['hp_atual'] = min($maximo, $hp + $cura);
            return true;
        }

        if ($tipo === 'cura_total') {
            if ($hp >= $maximo) {
                return false;
            }
            $pokemon['hp_atual'] = $maximo;
            return true;
        }

        if ($tipo === 'restauracao_total') {
            if ($hp >= $maximo && $pokemon['status'] === 'nenhum') {
                return false;
            }
            $pokemon['hp_atual'] = $maximo;
            $pokemon['status'] = 'nenhum';
            return true;
        }

        if ($tipo === 'cura_status') {
            if ($pokemon['status'] === 'nenhum' ||
                ($item['status_removido'] !== 'todos' &&
                 $item['status_removido'] !== $pokemon['status'])) {
                return false;
            }
            $pokemon['status'] = 'nenhum';
            return true;
        }

        return false;
    }

    private function escolherItemNpc(
        array $pokemons, array $estado, int $limite
    ): ?array {
        if ($estado['usos_npc'] >= $limite) {
            return null;
        }
        $ativoId = (int) $estado['npc_ativo'];
        if (!$ativoId || $pokemons[$ativoId]['hp_atual'] <= 0) {
            return null;
        }
        $ativo = $pokemons[$ativoId];

        $consulta = $this->pdo->query(
            "SELECT * FROM item WHERE aparece_inventario = TRUE
             AND tipo_efeito <> 'evolucao' ORDER BY preco ASC"
        );
        $itens = $consulta->fetchAll();

        // cura status quando possivel
        if ($ativo['status'] !== 'nenhum') {
            foreach ($itens as $item) {
                if (($estado['estoque_npc'][$item['id']] ?? 0) > 0 &&
                    $item['tipo_efeito'] === 'cura_status' &&
                    ($item['status_removido'] === 'todos' ||
                     $item['status_removido'] === $ativo['status'])) {
                    return ['item' => $item, 'alvo' => $ativoId];
                }
            }
        }

        // usa uma pocao quando o hp esta baixo
        if ($ativo['hp_atual'] * 100 <= $ativo['hp_maximo'] * 45) {
            foreach ($itens as $item) {
                if (($estado['estoque_npc'][$item['id']] ?? 0) > 0 &&
                    in_array($item['tipo_efeito'],
                        ['cura_hp', 'cura_total', 'restauracao_total'], true)) {
                    return ['item' => $item, 'alvo' => $ativoId];
                }
            }
        }

        // tenta reviver um aliado derrotado
        foreach ($pokemons as $pokemon) {
            if ($pokemon['lado'] !== 'npc' || $pokemon['hp_atual'] > 0) {
                continue;
            }
            foreach ($itens as $item) {
                if (($estado['estoque_npc'][$item['id']] ?? 0) > 0 &&
                    $item['tipo_efeito'] === 'reviver') {
                    return ['item' => $item, 'alvo' => $pokemon['id']];
                }
            }
        }

        return null;
    }

    private function usarItemNpc(
        int $batalhaId,
        array &$pokemons,
        array &$estado,
        array $item,
        int $alvoId
    ): void {
        if (($estado['estoque_npc'][$item['id']] ?? 0) <= 0 ||
            !$this->aplicarItem($pokemons[$alvoId], $item)) {
            return;
        }
        $estado['estoque_npc'][$item['id']]--;
        $estado['usos_npc']++;
        $this->evento(
            $batalhaId, 'item',
            'NPC usou ' . $item['nome'] . ' em ' .
            $pokemons[$alvoId]['nome'] . '.',
            $alvoId, null, (int) $item['id']
        );
    }

    private function respostaNpc(
        int $batalhaId, array &$pokemons, array &$estado, int $limite
    ): void {
        $npcId = (int) $estado['npc_ativo'];
        $jogadorId = (int) $estado['jogador_ativo'];
        if ($pokemons[$npcId]['hp_atual'] <= 0 ||
            $pokemons[$jogadorId]['hp_atual'] <= 0) {
            return;
        }
        $itemNpc = $this->escolherItemNpc($pokemons, $estado, $limite);
        if ($itemNpc) {
            $this->usarItemNpc(
                $batalhaId, $pokemons, $estado,
                $itemNpc['item'], $itemNpc['alvo']
            );
        } else {
            $this->ataqueNpc($batalhaId, $pokemons, $npcId, $jogadorId);
        }
    }

    private function verificarResultado(array $pokemons): ?string
    {
        $jogadorVivo = false;
        $npcVivo = false;
        foreach ($pokemons as $pokemon) {
            if ($pokemon['hp_atual'] <= 0) {
                continue;
            }
            if ($pokemon['lado'] === 'jogador') {
                $jogadorVivo = true;
            } else {
                $npcVivo = true;
            }
        }
        if ($jogadorVivo && $npcVivo) {
            return null;
        }
        if (!$jogadorVivo && !$npcVivo) {
            return 'empate';
        }
        return $jogadorVivo ? 'vitoria' : 'derrota';
    }

    private function evento(
        int $batalhaId,
        string $tipo,
        string $descricao,
        ?int $pokemonId = null,
        ?int $ataqueId = null,
        ?int $itemId = null
    ): void {
        $consulta = $this->pdo->prepare(
            "INSERT INTO batalha_evento
                (batalha_id, batalha_pokemon_id, ataque_id,
                 item_id, tipo_evento, descricao)
             VALUES (:b, :p, :a, :i, :tipo, :descricao)"
        );
        $consulta->execute([
            ':b' => $batalhaId,
            ':p' => $pokemonId,
            ':a' => $ataqueId,
            ':i' => $itemId,
            ':tipo' => $tipo,
            ':descricao' => $descricao
        ]);
    }
}

