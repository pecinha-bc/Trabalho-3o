<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';
require_once __DIR__ . '/../classes/motorbatalha.php';

exigirLogin();

if (!isset($_SESSION['perfil_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$perfilId = (int) $_SESSION['perfil_id'];
$usuarioId = (int) $_SESSION['usuario_id'];
$perfil = (new JogadorRepositorio())->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

$pdo = Conexao::conectar();
$motor = new MotorBatalha();
$erro = '';
$aviso = $_SESSION['aviso_combate'] ?? '';
unset($_SESSION['aviso_combate']);

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['token_combate'])) {
    $_SESSION['token_combate'] = bin2hex(random_bytes(32));
}

function buscarAtiva(PDO $pdo, int $perfilId): ?array
{
    $consulta = $pdo->prepare(
        "SELECT b.id, b.npc_id, n.nome AS nome_npc, n.dificuldade
         FROM batalha b INNER JOIN npc n ON n.id = b.npc_id
         WHERE b.perfil_id = :perfil AND b.resultado = 'em_andamento'
         ORDER BY b.id DESC LIMIT 1"
    );
    $consulta->execute([':perfil' => $perfilId]);
    return $consulta->fetch() ?: null;
}

$batalhaAtiva = buscarAtiva($pdo, $perfilId);
$npcId = $batalhaAtiva
    ? (int) $batalhaAtiva['npc_id']
    : (int) ($_SESSION['npc_selecionado_id'] ?? 0);

if (!$batalhaAtiva &&
    (int) ($_SESSION['npc_selecionado_perfil_id'] ?? 0) !== $perfilId) {
    $npcId = 0;
}

if ($npcId <= 0) {
    header('Location: batalhas.php');
    exit;
}

$consulta = $pdo->prepare(
    "SELECT id, nome, dificuldade, recompensa FROM npc WHERE id = :id"
);
$consulta->execute([':id' => $npcId]);
$npc = $consulta->fetch();

if (!$npc) {
    header('Location: batalhas.php');
    exit;
}

$limites = ['facil' => 3, 'medio' => 5, 'dificil' => 7];
$limite = $limites[$npc['dificuldade']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $acao = $_POST['acao'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['token_combate'], $token)) {
        $erro = 'Formulario invalido. Atualize a pagina.';
    } else {
        try {
            if ($acao === 'iniciar') {
                if ($batalhaAtiva) {
                    throw new RuntimeException('Ja existe uma batalha em andamento.');
                }

                $pdo->beginTransaction();
                try {
                    // bloqueia o perfil contra duas iniciacoes simultaneas
                    $consulta = $pdo->prepare(
                        "SELECT id FROM perfil
                         WHERE id = :id AND usuario_id = :usuario FOR UPDATE"
                    );
                    $consulta->execute([
                        ':id' => $perfilId, ':usuario' => $usuarioId
                    ]);
                    if (!$consulta->fetch()) {
                        throw new RuntimeException('Perfil invalido.');
                    }

                    if (buscarAtiva($pdo, $perfilId)) {
                        throw new RuntimeException('Ja existe uma batalha em andamento.');
                    }

                    $consulta = $pdo->prepare(
                        "SELECT id, hp_atual FROM pokemon_perfil
                         WHERE perfil_id = :perfil ORDER BY id FOR UPDATE"
                    );
                    $consulta->execute([':perfil' => $perfilId]);
                    $equipeJogador = $consulta->fetchAll();

                    if (count($equipeJogador) !== 3) {
                        throw new RuntimeException('Sua equipe precisa ter tres Pokemon.');
                    }
                    $vivos = 0;
                    foreach ($equipeJogador as $pokemon) {
                        if ((int) $pokemon['hp_atual'] > 0) {
                            $vivos++;
                        }
                    }
                    if ($vivos === 0) {
                        throw new RuntimeException(
                            'Todos os seus Pokemon estao derrotados. Cure sua equipe.'
                        );
                    }

                    $consulta = $pdo->prepare(
                        "SELECT id FROM npc_pokemon
                         WHERE npc_id = :npc ORDER BY posicao"
                    );
                    $consulta->execute([':npc' => $npcId]);
                    $equipeNpc = $consulta->fetchAll();
                    if (count($equipeNpc) !== 3) {
                        throw new RuntimeException('NPC sem equipe completa.');
                    }

                    $consulta = $pdo->prepare(
                        "INSERT INTO batalha (perfil_id, npc_id)
                         VALUES (:perfil, :npc)"
                    );
                    $consulta->execute([
                        ':perfil' => $perfilId, ':npc' => $npcId
                    ]);
                    $batalhaId = (int) $pdo->lastInsertId();

                    $consulta = $pdo->prepare(
                        "INSERT INTO batalha_pokemon
                         (batalha_id, pokemon_perfil_id)
                         VALUES (:b, :p)"
                    );
                    foreach ($equipeJogador as $pokemon) {
                        $consulta->execute([
                            ':b' => $batalhaId, ':p' => $pokemon['id']
                        ]);
                    }

                    $consulta = $pdo->prepare(
                        "INSERT INTO batalha_pokemon
                         (batalha_id, npc_pokemon_id)
                         VALUES (:b, :p)"
                    );
                    foreach ($equipeNpc as $pokemon) {
                        $consulta->execute([
                            ':b' => $batalhaId, ':p' => $pokemon['id']
                        ]);
                    }

                    // o npc inicia cada batalha com hp cheio
                    $consulta = $pdo->prepare(
                        "UPDATE batalha_pokemon bp
                         INNER JOIN npc_pokemon np ON np.id = bp.npc_pokemon_id
                         INNER JOIN pokemon p ON p.id = np.pokemon_id
                         SET bp.hp_atual = p.hp_base,
                             bp.hp_maximo = p.hp_base,
                             bp.status = 'nenhum'
                         WHERE bp.batalha_id = :b
                           AND bp.npc_pokemon_id IS NOT NULL"
                    );
                    $consulta->execute([':b' => $batalhaId]);
                    $pdo->commit();

                    $_SESSION['aviso_combate'] = 'Batalha iniciada!';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            } else {
                if (!$batalhaAtiva) {
                    throw new RuntimeException('Nenhuma batalha em andamento.');
                }
                $referencia = filter_var(
                    $_POST['referencia'] ?? null, FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                $alvo = filter_var(
                    $_POST['alvo_id'] ?? 0, FILTER_VALIDATE_INT
                );

                if ($referencia === false || $referencia === null) {
                    throw new InvalidArgumentException('Opcao invalida.');
                }
                $dadosApos = $motor->executar(
                    (int) $batalhaAtiva['id'], $perfilId,
                    $acao, (int) $referencia, (int) $alvo
                );
                if ($dadosApos['batalha']['resultado'] !== 'em_andamento') {
                    $_SESSION['aviso_combate'] = 'Batalha encerrada: ' .
                        $dadosApos['batalha']['resultado'] .
                        '. Recompensa: ' .
                        $dadosApos['batalha']['recompensa'] .
                        ' Pokedolares.';
                }
            }

            $_SESSION['token_combate'] = bin2hex(random_bytes(32));
            header('Location: combate.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Ocorreu um erro no banco de dados.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = $e->getMessage();
        }
    }
}

$batalhaAtiva = buscarAtiva($pdo, $perfilId);
$dados = $batalhaAtiva
    ? $motor->consultar((int) $batalhaAtiva['id'], $perfilId)
    : null;

$ataques = [];
$inventario = [];
$equipeJogador = [];
$equipeNpc = [];

if ($dados) {
    $estado = $dados['estado'];
    $pokemons = $dados['pokemons'];
    $ativoJogador = $pokemons[$estado['jogador_ativo']] ?? null;
    $ativoNpc = $pokemons[$estado['npc_ativo']] ?? null;

    if ($ativoJogador) {
        $consulta = $pdo->prepare(
            "SELECT a.id, a.nome, a.tipo, a.poder_base,
                    a.status_causado, a.chance_status
             FROM pokemon_ataque pa
             INNER JOIN ataque a ON a.id = pa.ataque_id
             WHERE pa.pokemon_id = :id ORDER BY pa.posicao"
        );
        $consulta->execute([':id' => $ativoJogador['pokemon_id']]);
        $ataques = $consulta->fetchAll();
    }

    $consulta = $pdo->prepare(
        "SELECT i.id, i.nome, inv.quantidade
         FROM inventario inv INNER JOIN item i ON i.id = inv.item_id
         WHERE inv.perfil_id = :perfil AND inv.quantidade > 0
           AND i.aparece_inventario = TRUE AND i.tipo_efeito <> 'evolucao'
         ORDER BY i.nome"
    );
    $consulta->execute([':perfil' => $perfilId]);
    $inventario = $consulta->fetchAll();
} else {
    $consulta = $pdo->prepare(
        "SELECT p.nome, pp.hp_atual, pp.hp_maximo
         FROM pokemon_perfil pp
         INNER JOIN pokemon p ON p.id = pp.pokemon_id
         WHERE pp.perfil_id = :perfil ORDER BY pp.id"
    );
    $consulta->execute([':perfil' => $perfilId]);
    $equipeJogador = $consulta->fetchAll();

    $consulta = $pdo->prepare(
        "SELECT p.nome, p.hp_base AS hp_maximo
         FROM npc_pokemon np
         INNER JOIN pokemon p ON p.id = np.pokemon_id
         WHERE np.npc_id = :npc ORDER BY np.posicao"
    );
    $consulta->execute([':npc' => $npcId]);
    $equipeNpc = $consulta->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Combate - MonstroBolso</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px;
               background: #f1f3f7; color: #20242b; }
        main { max-width: 1050px; margin: auto; }
        h1, h2 { text-align: center; }
        .grade { display: grid; grid-template-columns: repeat(auto-fit,
                 minmax(280px, 1fr)); gap: 18px; margin: 20px 0; }
        .painel { background: white; padding: 20px; border-radius: 10px;
                  border: 1px solid #ddd; }
        .pokemon { background: #f5f7fb; padding: 12px; margin: 9px 0;
                   border-radius: 8px; }
        .ativo { border: 2px solid #246bd6; }
        .barra { height: 12px; background: #ddd; border-radius: 8px;
                 overflow: hidden; margin-top: 7px; }
        .vida { height: 100%; background: #30a46c; }
        .mensagem { padding: 14px; margin: 15px 0; border-radius: 7px;
                    background: #d8ecde; }
        .erro { background: #f8d7da; }
        form { margin: 10px 0; }
        button { padding: 10px 15px; background: #246bd6;
                 color: white; border: 0; border-radius: 6px; cursor: pointer; }
        button:disabled { background: #aaa; cursor: not-allowed; }
        select { padding: 9px; max-width: 100%; margin: 5px 0; }
        .historico { max-height: 300px; overflow-y: auto; }
        .historico p { border-bottom: 1px solid #eee; padding-bottom: 8px; }
        a { color: #246bd6; }
    </style>
</head>
<body>
<main>
    <h1>MonstroBolso</h1>
    <h2>Combate</h2>
    <p><strong>Jogador:</strong> <?= escapar($perfil->getNome()) ?></p>
    <p><strong>Adversario:</strong> <?= escapar($npc['nome']) ?></p>

    <?php if ($erro !== ''): ?>
        <div class="mensagem erro"><?= escapar($erro) ?></div>
    <?php endif; ?>
    <?php if ($aviso !== ''): ?>
        <div class="mensagem"><?= escapar($aviso) ?></div>
    <?php endif; ?>

    <?php if (!$dados): ?>
        <p>Confira os Pokemon antes de iniciar a batalha.</p>
        <p>Limite de itens: <strong><?= $limite ?> para cada lado</strong>.</p>
        <div class="grade">
            <section class="painel">
                <h3>Sua equipe</h3>
                <?php foreach ($equipeJogador as $p): ?>
                    <div class="pokemon">
                        <strong><?= escapar($p['nome']) ?></strong>
                        HP: <?= (int) $p['hp_atual'] ?> /
                        <?= (int) $p['hp_maximo'] ?>
                    </div>
                <?php endforeach; ?>
            </section>
            <section class="painel">
                <h3>Equipe do NPC</h3>
                <?php foreach ($equipeNpc as $p): ?>
                    <div class="pokemon">
                        <strong><?= escapar($p['nome']) ?></strong>
                        HP: <?= (int) $p['hp_maximo'] ?>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
        <form method="post">
            <input type="hidden" name="token"
                   value="<?= escapar($_SESSION['token_combate']) ?>">
            <input type="hidden" name="acao" value="iniciar">
            <button type="submit">Iniciar batalha</button>
        </form>
    <?php else: ?>
        <p><strong>Turno:</strong> <?= (int) $estado['turno'] ?> |
           <strong>Itens usados:</strong>
           jogador <?= (int) $estado['usos_jogador'] ?> / <?= $limite ?>,
           NPC <?= (int) $estado['usos_npc'] ?> / <?= $limite ?></p>

        <div class="grade">
            <section class="painel">
                <h3>Seus Pokemon</h3>
                <?php foreach ($pokemons as $p): ?>
                    <?php if ($p['lado'] !== 'jogador') continue; ?>
                    <div class="pokemon <?= $p['id'] === (int) $estado['jogador_ativo']
                        ? 'ativo' : '' ?>">
                        <strong><?= escapar($p['nome']) ?></strong>
                        HP: <?= $p['hp_atual'] ?> / <?= $p['hp_maximo'] ?>
                        <div class="barra"><div class="vida" style="width:<?=
                            $p['hp_maximo'] > 0
                            ? (int) round(100 * $p['hp_atual'] / $p['hp_maximo'])
                            : 0 ?>%"></div></div>
                        <p>Status: <?= escapar($p['status']) ?></p>
                        <?php if ($p['id'] !== (int) $estado['jogador_ativo'] &&
                            $p['hp_atual'] > 0): ?>
                            <form method="post">
                                <input type="hidden" name="token"
                                       value="<?= escapar($_SESSION['token_combate']) ?>">
                                <input type="hidden" name="acao" value="trocar">
                                <input type="hidden" name="referencia"
                                       value="<?= $p['id'] ?>">
                                <button type="submit">Trocar para este Pokemon</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
            <section class="painel">
                <h3>Pokemon do adversario</h3>
                <?php foreach ($pokemons as $p): ?>
                    <?php if ($p['lado'] !== 'npc') continue; ?>
                    <div class="pokemon <?= $p['id'] === (int) $estado['npc_ativo']
                        ? 'ativo' : '' ?>">
                        <strong><?= escapar($p['nome']) ?></strong>
                        HP: <?= $p['hp_atual'] ?> / <?= $p['hp_maximo'] ?>
                        <div class="barra"><div class="vida" style="width:<?=
                            $p['hp_maximo'] > 0
                            ? (int) round(100 * $p['hp_atual'] / $p['hp_maximo'])
                            : 0 ?>%"></div></div>
                        <p>Status: <?= escapar($p['status']) ?></p>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>

        <div class="grade">
            <section class="painel">
                <h3>Ataques de <?= escapar($ativoJogador['nome'] ?? '') ?></h3>
                <?php foreach ($ataques as $a): ?>
                    <form method="post">
                        <input type="hidden" name="token"
                               value="<?= escapar($_SESSION['token_combate']) ?>">
                        <input type="hidden" name="acao" value="atacar">
                        <input type="hidden" name="referencia"
                               value="<?= (int) $a['id'] ?>">
                        <button type="submit">
                            <?= escapar($a['nome']) ?>
                            (poder <?= (int) $a['poder_base'] ?>)
                        </button>
                    </form>
                <?php endforeach; ?>
            </section>
            <section class="painel">
                <h3>Usar item</h3>
                <?php if ((int) $estado['usos_jogador'] >= $limite): ?>
                    <p>Voce atingiu o limite de itens nesta batalha.</p>
                <?php elseif (!$inventario): ?>
                    <p>Voce nao possui itens de batalha. Compre na loja.</p>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="token"
                               value="<?= escapar($_SESSION['token_combate']) ?>">
                        <input type="hidden" name="acao" value="item">
                        <label>Item:</label>
                        <select name="referencia" required>
                            <?php foreach ($inventario as $item): ?>
                                <option value="<?= (int) $item['id'] ?>">
                                    <?= escapar($item['nome']) ?>
                                    (<?= (int) $item['quantidade'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p><label>Pokemon:</label></p>
                        <select name="alvo_id" required>
                            <?php foreach ($pokemons as $p): ?>
                                <?php if ($p['lado'] !== 'jogador') continue; ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= escapar($p['nome']) ?>
                                    (HP <?= $p['hp_atual'] ?>/<?= $p['hp_maximo'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p><button type="submit">Utilizar item</button></p>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <section class="painel historico">
            <h3>Ultimos acontecimentos</h3>
            <?php foreach ($dados['eventos'] as $evento): ?>
                <p><?= escapar($evento['descricao']) ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <p><a href="batalhas.php">Voltar para os treinadores</a></p>
    <p><a href="inicio.php">Menu principal</a></p>
</main>
</body>
</html>
