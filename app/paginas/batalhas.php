
<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';

exigirLogin();

if (!isset($_SESSION['perfil_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$perfilId = (int) $_SESSION['perfil_id'];
$usuarioId = (int) $_SESSION['usuario_id'];

$jogadorRepositorio = new JogadorRepositorio();
$perfil = $jogadorRepositorio->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

$pdo = Conexao::conectar();

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

if (!isset($_SESSION['token_batalhas'])) {
    $_SESSION['token_batalhas'] = bin2hex(random_bytes(32));
}

$erro = '';

$limites = [
    'facil' => 3,
    'medio' => 5,
    'dificil' => 7
];


$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM pokemon_perfil
     WHERE perfil_id = :perfil_id"
);

$consulta->execute([':perfil_id' => $perfilId]);
$quantidadeEquipe = (int) $consulta->fetchColumn();


$consulta = $pdo->query(
    "SELECT id, nome, dificuldade, recompensa
     FROM npc
     ORDER BY FIELD(dificuldade, 'facil', 'medio', 'dificil')"
);

$treinadores = $consulta->fetchAll();


$consulta = $pdo->query(
    "SELECT np.npc_id, p.nome, p.tipo
     FROM npc_pokemon np
     INNER JOIN pokemon p ON p.id = np.pokemon_id
     ORDER BY np.npc_id, np.posicao"
);

$equipesNpc = [];

foreach ($consulta->fetchAll() as $pokemon) {
    $equipesNpc[$pokemon['npc_id']][] = $pokemon;
}


$consulta = $pdo->prepare(
    "SELECT b.id, b.npc_id, n.nome AS nome_npc
     FROM batalha b
     INNER JOIN npc n ON n.id = b.npc_id
     WHERE b.perfil_id = :perfil_id
       AND b.resultado = 'em_andamento'
     ORDER BY b.id DESC
     LIMIT 1"
);

$consulta->execute([':perfil_id' => $perfilId]);
$batalhaAtiva = $consulta->fetch();


if ($batalhaAtiva) {
    $_SESSION['npc_selecionado_id'] =
        (int) $batalhaAtiva['npc_id'];

    $_SESSION['npc_selecionado_perfil_id'] = $perfilId;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['token_batalhas'], $token)
    ) {
        $erro = 'Formulario invalido.';
    } elseif ($batalhaAtiva) {
        $erro = 'Voce ja possui uma batalha em andamento.';
    } elseif ($quantidadeEquipe !== 3) {
        $erro = 'Voce precisa ter tres Pokemon na equipe.';
    } else {

        $npcId = filter_var(
            $_POST['npc_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $npcEscolhido = null;

        foreach ($treinadores as $treinador) {
            if ((int) $treinador['id'] === $npcId) {
                $npcEscolhido = $treinador;
                break;
            }
        }

        if (!$npcEscolhido) {
            $erro = 'Treinador invalido.';
        } else {

            $_SESSION['npc_selecionado_id'] = $npcId;
            $_SESSION['npc_selecionado_perfil_id'] = $perfilId;

            $_SESSION['token_batalhas'] = bin2hex(
                random_bytes(32)
            );

            
            header('Location: combate.php');
            exit;
        }
    }
}

?>

<?php require_once __DIR__ . '/../includes/layout.php'; mbAbrirPagina('Batalhas', 'Escolha um treinador, enfrente seus três Pokémon e conquiste recompensas.', $perfil, 'batalhas.php'); ?>

<?php if ($erro !== ''): ?><div class="mb-ui-alert is-error" role="alert"><?= escapar($erro) ?></div><?php endif; ?>
<?php if ($batalhaAtiva): ?>
<section class="mb-paper mb-ui-card mb-npc-notice">
  <h2>Uma batalha aguarda você!</h2>
  <p>Seu confronto com <strong><?= escapar($batalhaAtiva['nome_npc']) ?></strong> ainda não terminou.</p>
  <a class="mb-button mb-button-green" href="combate.php">Retomar batalha</a>
</section>
<?php elseif ($quantidadeEquipe !== 3): ?>
<section class="mb-paper mb-ui-card mb-npc-notice">
  <h2>Prepare sua equipe</h2>
  <p>Para desafiar os treinadores, você precisa selecionar três Pokémon.</p>
  <a class="mb-button" href="equipe.php">Montar minha equipe</a>
</section>
<?php else: ?>
<h2 class="mb-trainers-heading">Escolha um Treinador</h2>
<div class="mb-npc-list">
<?php foreach ($treinadores as $npc): ?>
  <?php
  $dificuldade = (string) $npc['dificuldade'];
  $limite = $limites[$dificuldade] ?? 3;
  $pokemons = $equipesNpc[$npc['id']] ?? [];
  $nomeDificuldade = ['facil'=>'Fácil','medio'=>'Médio','dificil'=>'Difícil'][$dificuldade] ?? ucfirst($dificuldade);
  $imagemTreinador = in_array($dificuldade, ['facil','medio','dificil'], true) ? $dificuldade : 'facil';
  ?>
  <article class="mb-paper mb-npc-row is-<?= escapar($dificuldade) ?>">
    <div class="mb-npc-avatar">
      <img src="../assets/imagens/treinador-<?= escapar($imagemTreinador) ?>.webp" alt="Retrato ilustrado do <?= escapar($npc['nome']) ?>" loading="eager">
    </div>
    <div class="mb-npc-info">
      <div class="mb-npc-naming">
        <span class="mb-npc-difficulty"><?= escapar($nomeDificuldade) ?></span>
        <h3><?= escapar($npc['nome']) ?></h3>
      </div>
      <div class="mb-npc-roster" aria-label="Equipe adversária">
        <?php foreach ($pokemons as $pokemon): ?>
        <div class="mb-npc-pokemon" title="<?= escapar($pokemon['nome']) ?>">
          <span class="mb-npc-pokeball" aria-hidden="true"></span>
          <span class="mb-npc-species"><?= escapar($pokemon['nome']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mb-npc-aside">
      <span class="mb-ui-badge mb-ui-badge-gold">✦ <?= (int) $npc['recompensa'] ?> Pokédólares</span>
      <span class="mb-ui-badge mb-npc-limit">Até <?= (int) $limite ?> itens</span>
      <form method="post" class="mb-ui-form">
        <input type="hidden" name="token" value="<?= escapar($_SESSION['token_batalhas']) ?>">
        <input type="hidden" name="npc_id" value="<?= (int) $npc['id'] ?>">
        <button class="mb-button mb-button-green" type="submit">⚔ Iniciar batalha</button>
      </form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php mbEncerrarPagina(); ?>
