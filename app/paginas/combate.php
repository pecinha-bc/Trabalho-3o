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
                            'Sua equipe nao possui Pokemon com HP. Verifique os dados do perfil.'
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
<?php require_once __DIR__ . '/../includes/layout.php'; ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Combate — MonstroBolso</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Alegreya:wght@400;500;600;700&family=Cinzel:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/combate-if.css?v=2">
</head>
<body class="if-page">
<main class="if-shell">
  <header class="if-header">
    <a class="if-brand" href="inicio.php">MonstroBolso</a>
    <div class="if-score">
      <span><?= escapar($perfil->getNome()) ?> <b>×</b> <?= escapar($npc['nome']) ?></span>
      <?php if ($dados): ?>
        <span>Turno <?= (int) $estado['turno'] ?></span>
      <?php endif; ?>
    </div>
    <nav class="if-links" aria-label="Navegação">
      <a href="batalhas.php">Treinadores</a>
      <a href="inicio.php">Menu</a>
    </nav>
  </header>

  <?php if ($erro !== ''): ?><p class="if-notice if-error" role="alert"><?= escapar($erro) ?></p><?php endif; ?>
  <?php if ($aviso !== ''): ?><p class="if-notice if-success" role="status"><?= escapar($aviso) ?></p><?php endif; ?>

  <?php if (!$dados): ?>
    <section class="if-prestart if-paper">
      <h1>Preparar combate</h1>
      <p>Treinador adversário: <strong><?= escapar($npc['nome']) ?></strong></p>
      <div class="if-prestart-teams">
        <div><h2>Seus Pokémon</h2>
          <?php foreach ($equipeJogador as $p): ?>
            <p><?= escapar($p['nome']) ?> — HP <?= (int) $p['hp_atual'] ?>/<?= (int) $p['hp_maximo'] ?></p>
          <?php endforeach; ?>
        </div>
        <div><h2>Pokémon adversários</h2>
          <?php foreach ($equipeNpc as $p): ?>
            <p><?= escapar($p['nome']) ?> — HP <?= (int) $p['hp_maximo'] ?></p>
          <?php endforeach; ?>
        </div>
      </div>
      <form method="post">
        <input type="hidden" name="token" value="<?= escapar($_SESSION['token_combate']) ?>">
        <input type="hidden" name="acao" value="iniciar">
        <button class="if-button if-primary" type="submit">Iniciar combate</button>
      </form>
    </section>
  <?php else: ?>
    <section class="if-view" aria-label="Ginásio do Instituto Federal">
      <div class="if-scene">
        <img class="if-gym" src="../assets/imagens/ginasio-if.webp" alt="Ginásio com o símbolo do Instituto Federal no telão e campo de batalha amarelo">

        <?php if ($ativoNpc): ?>
          <img class="if-pokemon if-pokemon-enemy" src="<?= mbSeguro(mbImagemPokemon((string) $ativoNpc['nome'])) ?>" alt="<?= escapar($ativoNpc['nome']) ?> no chão do lado adversário" onerror="this.onerror=null;this.src='../assets/imagens/pokemon-indisponivel.svg'">
          <div class="if-hp if-hp-enemy">
            <div class="if-hp-title"><strong><?= escapar($ativoNpc['nome']) ?></strong><span><?= escapar(ucfirst($ativoNpc['tipo'] ?? '')) ?></span></div>
            <div class="if-hp-track" role="progressbar" aria-label="HP do adversário" aria-valuemin="0" aria-valuemax="<?= (int) $ativoNpc['hp_maximo'] ?>" aria-valuenow="<?= (int) $ativoNpc['hp_atual'] ?>">
              <span style="width:<?= (int) $ativoNpc['hp_maximo'] > 0 ? max(0, min(100, round(100 * $ativoNpc['hp_atual'] / $ativoNpc['hp_maximo']))) : 0 ?>%"></span>
            </div>
            <small>HP <?= (int) $ativoNpc['hp_atual'] ?>/<?= (int) $ativoNpc['hp_maximo'] ?> · <?= escapar($ativoNpc['status']) ?></small>
          </div>
        <?php endif; ?>

        <?php if ($ativoJogador): ?>
          <img class="if-pokemon if-pokemon-player" src="<?= mbSeguro(mbImagemPokemon((string) $ativoJogador['nome'])) ?>" alt="<?= escapar($ativoJogador['nome']) ?> no chão do lado do jogador" onerror="this.onerror=null;this.src='../assets/imagens/pokemon-indisponivel.svg'">
          <div class="if-hp if-hp-player">
            <div class="if-hp-title"><strong><?= escapar($ativoJogador['nome']) ?></strong><span><?= escapar(ucfirst($ativoJogador['tipo'] ?? '')) ?></span></div>
            <div class="if-hp-track" role="progressbar" aria-label="HP do seu Pokémon" aria-valuemin="0" aria-valuemax="<?= (int) $ativoJogador['hp_maximo'] ?>" aria-valuenow="<?= (int) $ativoJogador['hp_atual'] ?>">
              <span style="width:<?= (int) $ativoJogador['hp_maximo'] > 0 ? max(0, min(100, round(100 * $ativoJogador['hp_atual'] / $ativoJogador['hp_maximo']))) : 0 ?>%"></span>
            </div>
            <small>HP <?= (int) $ativoJogador['hp_atual'] ?>/<?= (int) $ativoJogador['hp_maximo'] ?> · <?= escapar($ativoJogador['status']) ?></small>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="if-controls" aria-label="Comandos do combate">
      <div class="if-paper if-attacks">
        <h2>Ataques</h2>
        <div class="if-moves">
          <?php foreach ($ataques as $ataque): ?>
            <form method="post">
              <input type="hidden" name="token" value="<?= escapar($_SESSION['token_combate']) ?>">
              <input type="hidden" name="acao" value="atacar">
              <input type="hidden" name="referencia" value="<?= (int) $ataque['id'] ?>">
              <button type="submit" class="if-button if-move">
                <strong><?= escapar($ataque['nome']) ?></strong>
                <small><?= escapar(ucfirst($ataque['tipo'])) ?> · Poder <?= (int) $ataque['poder_base'] ?></small>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="if-paper if-items">
        <h2>Itens <small><?= (int) $estado['usos_jogador'] ?>/<?= (int) $limite ?></small></h2>
        <?php if ((int) $estado['usos_jogador'] >= $limite): ?>
          <p>Limite de itens atingido.</p>
        <?php elseif ((int) ($estado['item_turno_jogador'] ?? 0) === (int) $estado['turno']): ?>
          <p>Item já utilizado neste turno.</p>
        <?php elseif (!$inventario): ?>
          <p>Não há itens de batalha disponíveis.</p>
        <?php else: ?>
          <form method="post" class="if-item-form">
            <input type="hidden" name="token" value="<?= escapar($_SESSION['token_combate']) ?>">
            <input type="hidden" name="acao" value="item">
            <label for="item_batalha">Item</label>
            <select id="item_batalha" name="referencia" required>
              <?php foreach ($inventario as $item): ?>
                <option value="<?= (int) $item['id'] ?>"><?= escapar(mbNomeItem($item)) ?> (<?= (int) $item['quantidade'] ?>)</option>
              <?php endforeach; ?>
            </select>
            <label for="alvo_batalha">Pokémon</label>
            <select id="alvo_batalha" name="alvo_id" required>
              <?php foreach ($pokemons as $p): if ($p['lado'] !== 'jogador') continue; ?>
                <option value="<?= (int) $p['id'] ?>"><?= escapar($p['nome']) ?> — <?= (int) $p['hp_atual'] ?>/<?= (int) $p['hp_maximo'] ?> HP</option>
              <?php endforeach; ?>
            </select>
            <button class="if-button if-primary" type="submit">Usar item</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="if-paper if-switch">
        <h2>Trocar Pokémon</h2>
        <div class="if-switch-list">
          <?php foreach ($pokemons as $p): if ($p['lado'] !== 'jogador') continue; ?>
            <div class="if-switch-entry">
              <span><strong><?= escapar($p['nome']) ?></strong><small>HP <?= (int) $p['hp_atual'] ?>/<?= (int) $p['hp_maximo'] ?></small></span>
              <?php if ((int) $p['id'] !== (int) $estado['jogador_ativo'] && (int) $p['hp_atual'] > 0): ?>
                <form method="post">
                  <input type="hidden" name="token" value="<?= escapar($_SESSION['token_combate']) ?>">
                  <input type="hidden" name="acao" value="trocar">
                  <input type="hidden" name="referencia" value="<?= (int) $p['id'] ?>">
                  <button class="if-button if-switch-button" type="submit">Trocar</button>
                </form>
              <?php else: ?>
                <em><?= (int) $p['id'] === (int) $estado['jogador_ativo'] ? 'Ativo' : 'Sem HP' ?></em>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <details class="if-history if-paper">
      <summary>Histórico <span><?= !empty($dados['eventos']) ? escapar($dados['eventos'][0]['descricao']) : 'A batalha começou.' ?></span></summary>
      <div class="if-history-list">
        <?php foreach ($dados['eventos'] as $evento): ?>
          <p><?= escapar($evento['descricao']) ?></p>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</main>
</body>
</html>
