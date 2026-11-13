<?php

require_once __DIR__ . '/../conexao.php';

// controla as regras e persiste o estado de cada batalha no banco
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
            if (empty($batalha['log']) && $batalha['resultado'] === 'em_andamento') {
                $this->salvarEstado($batalhaId, $estado);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $consulta = $this->pdo->prepare(
            'SELECT tipo_evento, descricao FROM batalha_evento
             WHERE batalha_id = :id ORDER BY id DESC LIMIT 35'
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
            // bloqueio evita turnos duplicados em requisicoes simultaneas
            $batalha = $this->buscarBatalha($batalhaId, $perfilId);
            if ($batalha['resultado'] !== 'em_andamento') {
                throw new RuntimeException('Esta batalha ja terminou.');
            }
            $pokemons = $this->buscarPokemons($batalhaId);
            $estado = $this->carregarEstado($batalha, $pokemons);
            $this->efeitosPendentes = [];
            $limites = ['facil' => 3, 'medio' => 5, 'dificil' => 7];
            $limite = $limites[$batalha['dificuldade']];

            $jogadorId = (int) $estado['jogador_ativo'];
            $npcId = (int) $estado['npc_ativo'];
            if (!$jogadorId || !$npcId) {
                throw new RuntimeException('Nao ha Pokemon disponiveis para lutar.');
            }

            if ($acao === 'item') {
                // o item nao consome ataque, mas e limitado a um por rodada
                if ($estado['usos_jogador'] >= $limite) {
                    throw new RuntimeException('Seu limite total de itens foi atingido.');
                }
                if ((int) ($estado['item_turno_jogador'] ?? 0) === (int) $estado['turno']) {
                    throw new RuntimeException('Voce ja usou um item nesta rodada.');
                }
                if (!isset($pokemons[$alvoId]) || $pokemons[$alvoId]['lado'] !== 'jogador') {
                    throw new InvalidArgumentException('Alvo de item invalido.');
                }
                $item = $this->buscarItem($referencia);
                if (!$item || $item['tipo_efeito'] === 'evolucao' ||
                    !(int) $item['aparece_inventario']) {
                    throw new InvalidArgumentException('Item de batalha invalido.');
                }
                if (!$this->aplicarItem($pokemons[$alvoId], $item)) {
                    throw new RuntimeException('O item nao tem efeito nesse Pokemon.');
                }
                $consulta = $this->pdo->prepare(
                    'UPDATE inventario SET quantidade = quantidade - 1
                     WHERE perfil_id = :perfil AND item_id = :item AND quantidade > 0'
                );
                $consulta->execute([':perfil' => $perfilId, ':item' => $referencia]);
                if ($consulta->rowCount() !== 1) {
                    throw new RuntimeException('Voce nao possui esse item.');
                }
                $this->removerEfeitoSeCurado($estado, $pokemons, $alvoId);
                $estado['usos_jogador']++;
                $estado['item_turno_jogador'] = (int) $estado['turno'];
                $this->evento(
                    $batalhaId, 'item',
                    'Jogador usou ' . $item['nome'] . ' em ' . $pokemons[$alvoId]['nome'] . '.',
                    $alvoId, null, $referencia
                );
                // nenhum ataque ocorre ate o jogador escolher atacar ou trocar
            } elseif ($acao === 'atacar' || $acao === 'trocar') {
                if ($acao === 'atacar') {
                    $ataqueJogador = $this->buscarAtaque(
                        (int) $pokemons[$jogadorId]['pokemon_id'], $referencia
                    );
                    if (!$ataqueJogador) {
                        throw new InvalidArgumentException('Ataque nao permitido.');
                    }
                } else {
                    if (!isset($pokemons[$referencia]) ||
                        $pokemons[$referencia]['lado'] !== 'jogador' ||
                        $pokemons[$referencia]['hp_atual'] <= 0 ||
                        $referencia === $jogadorId) {
                        throw new InvalidArgumentException('Troca de Pokemon invalida.');
                    }
                }

                // o NPC tem a mesma inteligencia em todas as dificuldades
                // e sempre realiza a acao principal antes do jogador
                $itemNpc = $this->escolherItemNpc($pokemons, $estado, $limite);
                if ($itemNpc !== null) {
                    $this->usarItemNpc(
                        $batalhaId, $pokemons, $estado,
                        $itemNpc['item'], $itemNpc['alvo']
                    );
                }
                $this->acaoNpc($batalhaId, $pokemons, $estado);

                if ($pokemons[$jogadorId]['hp_atual'] > 0) {
                    if ($acao === 'trocar') {
                        // a troca gasta a acao principal e nao provoca outro ataque
                        $estado['jogador_ativo'] = $referencia;
                        $this->evento(
                            $batalhaId, 'informacao',
                            'Jogador trocou para ' . $pokemons[$referencia]['nome'] . '.',
                            $referencia
                        );
                    } else {
                        $this->acaoJogador(
                            $batalhaId, $pokemons, $estado, $ataqueJogador
                        );
                    }
                } else {
                    $this->evento(
                        $batalhaId, 'informacao',
                        'O Pokemon ativo do jogador caiu antes de realizar a acao.'
                    );
                }

                // o status novo so se aplica a partir da proxima rodada
                foreach ($this->efeitosPendentes as $efeito) {
                    $id = $efeito['pokemon_id'];
                    if ($pokemons[$id]['hp_atual'] > 0 &&
                        $pokemons[$id]['status'] === 'nenhum') {
                        $pokemons[$id]['status'] = $efeito['tipo'];
                        $estado['efeitos'][(string) $id] = [
                            'tipo' => $efeito['tipo'],
                            'dano_base' => $efeito['dano_base'],
                            'ativa_em' => (int) $estado['turno'] + 1
                        ];
                        $this->evento(
                            $batalhaId, 'status',
                            $pokemons[$id]['nome'] . ' recebeu ' . $efeito['tipo'] . '.',
                            $id
                        );
                    }
                }
                $estado['turno']++;
            } else {
                throw new InvalidArgumentException('Acao invalida.');
            }

            // salva os seis participantes antes do gatilho de finalizacao
            $this->salvarPokemons($batalhaId, $pokemons);
            $resultado = $this->verificarResultado($pokemons);
            if ($resultado === null) {
                $this->ajustarAtivos($batalhaId, $pokemons, $estado);
            }
            $this->salvarEstado($batalhaId, $estado);

            if ($resultado !== null) {
                // o trigger do banco cura equipes e credita o premio uma so vez
                $consulta = $this->pdo->prepare(
                    "UPDATE batalha SET resultado = :resultado
                     WHERE id = :id AND resultado = 'em_andamento'"
                );
                $consulta->execute([':resultado' => $resultado, ':id' => $batalhaId]);
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
            'SELECT b.*, n.nome AS nome_npc, n.dificuldade
             FROM batalha b INNER JOIN npc n ON n.id = b.npc_id
             WHERE b.id = :id AND b.perfil_id = :perfil FOR UPDATE'
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
                    CASE WHEN bp.pokemon_perfil_id IS NULL THEN 'npc' ELSE 'jogador' END AS lado,
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
            foreach (['id', 'hp_atual', 'hp_maximo', 'pokemon_id', 'ataque', 'defesa', 'velocidade'] as $campo) {
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
            if (!is_array($estado)) {
                throw new RuntimeException('Estado da batalha invalido.');
            }
            if (($estado['versao'] ?? null) === 1) {
                // migra o estado antigo sem perder estoque e contadores
                $estado['versao'] = 2;
                $estado['efeitos'] = [];
                $estado['item_turno_jogador'] = 0;
            }
            if (($estado['versao'] ?? null) !== 2) {
                throw new RuntimeException('Versao do estado da batalha desconhecida.');
            }
            return $estado;
        }

        $consulta = $this->pdo->prepare(
            'SELECT item_id, quantidade FROM inventario_npc WHERE npc_id = :id'
        );
        $consulta->execute([':id' => $batalha['npc_id']]);
        $estoque = [];
        foreach ($consulta->fetchAll() as $item) {
            $estoque[(int) $item['item_id']] = (int) $item['quantidade'];
        }

        $estado = [
            'versao' => 2,
            'turno' => 1,
            'jogador_ativo' => 0,
            'npc_ativo' => 0,
            'usos_jogador' => 0,
            'usos_npc' => 0,
            'item_turno_jogador' => 0,
            'efeitos' => [],
            'estoque_npc' => $estoque
        ];
        $this->ajustarAtivos((int) $batalha['id'], $pokemons, $estado);
        return $estado;
    }

    private function salvarEstado(int $batalhaId, array $estado): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE batalha SET `log` = :estado WHERE id = :id'
        );
        $consulta->execute([
            ':estado' => json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':id' => $batalhaId
        ]);
    }

    private function salvarPokemons(int $batalhaId, array $pokemons): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE batalha_pokemon SET hp_atual = :hp, status = :status
             WHERE id = :id AND batalha_id = :batalha'
        );
        foreach ($pokemons as $pokemon) {
            $consulta->execute([
                ':hp' => $pokemon['hp_atual'],
                ':status' => $pokemon['status'],
                ':id' => $pokemon['id'],
                ':batalha' => $batalhaId
            ]);
        }
    }

    private function ajustarAtivos(int $batalhaId, array $pokemons, array &$estado): void
    {
        foreach (['jogador' => 'jogador_ativo', 'npc' => 'npc_ativo'] as $lado => $campo) {
            $atual = (int) ($estado[$campo] ?? 0);
            if ($atual && isset($pokemons[$atual]) && $pokemons[$atual]['hp_atual'] > 0) {
                continue;
            }
            $estado[$campo] = 0;
            foreach ($pokemons as $pokemon) {
                if ($pokemon['lado'] === $lado && $pokemon['hp_atual'] > 0) {
                    $estado[$campo] = $pokemon['id'];
                    $this->evento(
                        $batalhaId, 'informacao',
                        ($lado === 'jogador' ? 'Jogador' : 'NPC') .
                        ' enviou ' . $pokemon['nome'] . '.', $pokemon['id']
                    );
                    break;
                }
            }
        }
    }

    private function buscarAtaque(int $pokemonId, int $ataqueId): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT a.* FROM ataque a
             INNER JOIN pokemon_ataque pa ON pa.ataque_id = a.id
             WHERE pa.pokemon_id = :pokemon AND a.id = :ataque'
        );
        $consulta->execute([':pokemon' => $pokemonId, ':ataque' => $ataqueId]);
        return $consulta->fetch() ?: null;
    }

    private function ataqueNpc(int $batalhaId, array &$pokemons, int $npcId, int $jogadorId, array &$estado): void
    {
        if ($pokemons[$npcId]['hp_atual'] <= 0 || $pokemons[$jogadorId]['hp_atual'] <= 0) {
            return;
        }
        $consulta = $this->pdo->prepare(
            'SELECT a.* FROM ataque a INNER JOIN pokemon_ataque pa ON pa.ataque_id = a.id
             WHERE pa.pokemon_id = :pokemon ORDER BY pa.posicao'
        );
        $consulta->execute([':pokemon' => $pokemons[$npcId]['pokemon_id']]);
        $ataques = $consulta->fetchAll();
        if (!$ataques) {
            throw new RuntimeException('NPC sem ataques cadastrados.');
        }

        // todos os NPCs escolhem o melhor ataque estimado
        $escolhido = null;
        $melhor = -1;
        foreach ($ataques as $ataque) {
            $dano = $this->calcularDano($pokemons[$npcId], $pokemons[$jogadorId], $ataque);
            $bonusStatus = $pokemons[$jogadorId]['status'] === 'nenhum'
                ? (int) $ataque['chance_status'] * 0.09 : 0;
            $pontuacao = $dano + $bonusStatus;
            if ($pontuacao > $melhor) {
                $melhor = $pontuacao;
                $escolhido = $ataque;
            }
        }
        $this->atacar($batalhaId, $pokemons, $npcId, $jogadorId, $escolhido, $estado);
    }

    private function acaoNpc(int $batalhaId, array &$pokemons, array &$estado): void
    {
        $npcId = (int) $estado['npc_ativo'];
        $jogadorId = (int) $estado['jogador_ativo'];
        if (!$npcId || !$jogadorId || $pokemons[$npcId]['hp_atual'] <= 0 ||
            $pokemons[$jogadorId]['hp_atual'] <= 0) {
            return;
        }
        if (!$this->resolverStatus($batalhaId, $pokemons, $estado, $npcId)) {
            return;
        }
        if ($pokemons[$npcId]['hp_atual'] > 0) {
            $this->ataqueNpc($batalhaId, $pokemons, $npcId, $jogadorId, $estado);
        }
    }

    private function acaoJogador(int $batalhaId, array &$pokemons, array &$estado, array $ataque): void
    {
        $jogadorId = (int) $estado['jogador_ativo'];
        $npcId = (int) $estado['npc_ativo'];
        if (!$this->resolverStatus($batalhaId, $pokemons, $estado, $jogadorId)) {
            return;
        }
        if ($pokemons[$jogadorId]['hp_atual'] > 0 && $pokemons[$npcId]['hp_atual'] > 0) {
            $this->atacar($batalhaId, $pokemons, $jogadorId, $npcId, $ataque, $estado);
        }
    }

    // efeitos de status sao aplicados somente na proxima acao do afetado
    private function resolverStatus(
        int $batalhaId, array &$pokemons, array &$estado, int $pokemonId
    ): bool {
        $efeito = $estado['efeitos'][(string) $pokemonId] ?? null;
        if (!$efeito || (int) $efeito['ativa_em'] > (int) $estado['turno']) {
            return true;
        }
        unset($estado['efeitos'][(string) $pokemonId]);
        $status = $pokemons[$pokemonId]['status'];
        $pokemons[$pokemonId]['status'] = 'nenhum';
        if ($status === 'nenhum') {
            return true;
        }

        if (in_array($status, ['queimadura', 'veneno', 'paralisia'], true)) {
            // queimadura e veneno: 50% do dano inicial
            // paralisia: 25% de dano adicional e nao bloqueia a acao
            $multiplicador = $status === 'paralisia' ? 0.25 : 0.50;
            $dano = max(1, (int) floor((int) $efeito['dano_base'] * $multiplicador));
            $pokemons[$pokemonId]['hp_atual'] = max(
                0, $pokemons[$pokemonId]['hp_atual'] - $dano
            );
            $this->evento(
                $batalhaId, 'dano',
                $pokemons[$pokemonId]['nome'] . ' perdeu ' . $dano .
                ' HP adicional por ' . $status . '.', $pokemonId
            );
            if ($pokemons[$pokemonId]['hp_atual'] === 0) {
                $this->evento(
                    $batalhaId, 'derrota',
                    $pokemons[$pokemonId]['nome'] . ' caiu devido a ' . $status . '.',
                    $pokemonId
                );
                return false;
            }
            return true;
        }
        if (in_array($status, ['sono', 'congelamento'], true)) {
            $this->evento(
                $batalhaId, 'status',
                $pokemons[$pokemonId]['nome'] . ' nao agiu devido a ' . $status . '.',
                $pokemonId
            );
            return false;
        }
        return true;
    }

    private function calcularDano(array $origem, array $destino, array $ataque): int
    {
        $dano = max(1, (int) floor(
            (int) $ataque['poder_base'] +
            ($origem['ataque'] - $destino['defesa']) / 10
        ));
        // vantagem simples entre elementos, sem golpe critico
        $vantagem = [
            'fogo' => 'planta',
            'agua' => 'fogo',
            'planta' => 'agua',
            'eletrico' => 'agua'
        ];
        if (($vantagem[$ataque['tipo']] ?? null) === $destino['tipo']) {
            $dano = max(1, (int) floor($dano * 1.25));
        }
        return $dano;
    }

    private function atacar(
        int $batalhaId, array &$pokemons, int $origemId, int $destinoId,
        array $ataque, array &$estado
    ): void {
        if ($pokemons[$origemId]['hp_atual'] <= 0 ||
            $pokemons[$destinoId]['hp_atual'] <= 0) {
            return;
        }
        $origem = $pokemons[$origemId];
        $destino = $pokemons[$destinoId];
        $this->evento(
            $batalhaId, 'ataque',
            $origem['nome'] . ' usou ' . $ataque['nome'] . '.',
            $origemId, (int) $ataque['id']
        );

        // velocidade influencia esquiva, nao a ordem dos turnos
        $esquiva = min(15, max(0, (int) floor(
            ($destino['velocidade'] - $origem['velocidade']) / 4
        )));
        if ($esquiva > 0 && random_int(1, 100) <= $esquiva) {
            $this->evento(
                $batalhaId, 'informacao',
                $destino['nome'] . ' desviou do ataque.', $destinoId
            );
            return;
        }
        $dano = $this->calcularDano($origem, $destino, $ataque);
        $pokemons[$destinoId]['hp_atual'] = max(
            0, $destino['hp_atual'] - $dano
        );
        $this->evento(
            $batalhaId, 'dano',
            $destino['nome'] . ' recebeu ' . $dano . ' de dano.', $destinoId
        );
        if ($pokemons[$destinoId]['hp_atual'] === 0) {
            $this->evento(
                $batalhaId, 'derrota', $destino['nome'] . ' foi derrotado.', $destinoId
            );
            return;
        }
        if ($destino['status'] === 'nenhum' &&
            $ataque['status_causado'] !== 'nenhum' &&
            random_int(1, 100) <= (int) $ataque['chance_status']) {
            $this->efeitosPendentes[] = [
                'pokemon_id' => $destinoId,
                'tipo' => $ataque['status_causado'],
                'dano_base' => $dano
            ];
        }
    }

    private function buscarItem(int $itemId): ?array
    {
        $consulta = $this->pdo->prepare('SELECT * FROM item WHERE id = :id');
        $consulta->execute([':id' => $itemId]);
        return $consulta->fetch() ?: null;
    }

    private function aplicarItem(array &$pokemon, array $item): bool
    {
        $tipo = $item['tipo_efeito'];
        $hp = (int) $pokemon['hp_atual'];
        $maximo = (int) $pokemon['hp_maximo'];
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
            $pokemon['hp_atual'] = min(
                $maximo, $hp + max(1, (int) ceil($maximo * $percentual / 100))
            );
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

    private function removerEfeitoSeCurado(array &$estado, array $pokemons, int $id): void
    {
        if ($pokemons[$id]['status'] === 'nenhum') {
            unset($estado['efeitos'][(string) $id]);
        }
    }

    private function escolherItemNpc(array $pokemons, array $estado, int $limite): ?array
    {
        if ($estado['usos_npc'] >= $limite) {
            return null;
        }
        $consulta = $this->pdo->query(
            "SELECT * FROM item WHERE aparece_inventario = TRUE
             AND tipo_efeito <> 'evolucao'"
        );
        $itens = $consulta->fetchAll();
        $disponiveis = [];
        foreach ($itens as $item) {
            if (($estado['estoque_npc'][(int) $item['id']] ?? 0) > 0) {
                $disponiveis[] = $item;
            }
        }
        if (!$disponiveis) {
            return null;
        }
        $ativoId = (int) $estado['npc_ativo'];
        $ativo = $pokemons[$ativoId] ?? null;
        if (!$ativo || $ativo['hp_atual'] <= 0) {
            return null;
        }
        // primeiro remove status que impede a acao
        if (in_array($ativo['status'], ['sono', 'congelamento', 'paralisia'], true)) {
            foreach ($disponiveis as $item) {
                if ($item['tipo_efeito'] === 'cura_status' &&
                    in_array($item['status_removido'], [$ativo['status'], 'todos'], true)) {
                    return ['item' => $item, 'alvo' => $ativoId];
                }
            }
        }
        // quando o HP esta baixo, seleciona a cura mais proxima do HP faltante
        if ($ativo['hp_atual'] * 100 <= $ativo['hp_maximo'] * 55) {
            $faltante = $ativo['hp_maximo'] - $ativo['hp_atual'];
            $melhor = null;
            $menorDesperdicio = PHP_INT_MAX;
            foreach ($disponiveis as $item) {
                if (!in_array($item['tipo_efeito'], ['cura_hp', 'cura_total', 'restauracao_total'], true)) {
                    continue;
                }
                $cura = $item['tipo_efeito'] === 'cura_hp'
                    ? (int) ceil($ativo['hp_maximo'] * (int) $item['valor_efeito'] / 100)
                    : $ativo['hp_maximo'];
                $desperdicio = abs($faltante - $cura);
                if ($desperdicio < $menorDesperdicio) {
                    $menorDesperdicio = $desperdicio;
                    $melhor = $item;
                }
            }
            if ($melhor !== null) {
                return ['item' => $melhor, 'alvo' => $ativoId];
            }
        }
        // revive aliados para nao ficar com apenas um Pokemon
        foreach ($pokemons as $pokemon) {
            if ($pokemon['lado'] === 'npc' && $pokemon['hp_atual'] === 0) {
                foreach ($disponiveis as $item) {
                    if ($item['tipo_efeito'] === 'reviver') {
                        return ['item' => $item, 'alvo' => $pokemon['id']];
                    }
                }
            }
        }
        // remove outros status quando houver um item valido
        if ($ativo['status'] !== 'nenhum') {
            foreach ($disponiveis as $item) {
                if ($item['tipo_efeito'] === 'cura_status' &&
                    in_array($item['status_removido'], [$ativo['status'], 'todos'], true)) {
                    return ['item' => $item, 'alvo' => $ativoId];
                }
            }
        }
        return null;
    }

    private function usarItemNpc(
        int $batalhaId, array &$pokemons, array &$estado, array $item, int $alvoId
    ): void {
        $id = (int) $item['id'];
        if (($estado['estoque_npc'][$id] ?? 0) <= 0 ||
            !$this->aplicarItem($pokemons[$alvoId], $item)) {
            return;
        }
        $estado['estoque_npc'][$id]--;
        $estado['usos_npc']++;
        $this->removerEfeitoSeCurado($estado, $pokemons, $alvoId);
        $this->evento(
            $batalhaId, 'item',
            'NPC usou ' . $item['nome'] . ' em ' . $pokemons[$alvoId]['nome'] . '.',
            $alvoId, null, $id
        );
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
        int $batalhaId, string $tipo, string $descricao,
        ?int $pokemonId = null, ?int $ataqueId = null, ?int $itemId = null
    ): void {
        $consulta = $this->pdo->prepare(
            'INSERT INTO batalha_evento
             (batalha_id, batalha_pokemon_id, ataque_id, item_id, tipo_evento, descricao)
             VALUES (:b, :p, :a, :i, :tipo, :descricao)'
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
