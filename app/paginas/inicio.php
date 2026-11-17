<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';

exigirLogin();

if (!isset($_SESSION['perfil_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$repositorio = new JogadorRepositorio();
$perfil = $repositorio->buscarPorId((int) $_SESSION['perfil_id']);

if (!$perfil || $perfil->getUsuarioId() !== (int) $_SESSION['usuario_id']) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$opcoes = [
    ['pagina' => 'equipe.php', 'icone' => '◉', 'titulo' => 'Minha equipe', 'descricao' => 'Veja seus três Pokémon, atributos e ataques.'],
    ['pagina' => 'batalhas.php', 'icone' => '⚔', 'titulo' => 'Batalhas', 'descricao' => 'Desafie os treinadores e ganhe recompensas.'],
    ['pagina' => 'loja.php', 'icone' => '▣', 'titulo' => 'Loja', 'descricao' => 'Compre itens para suas próximas batalhas.'],
    ['pagina' => 'inventario.php', 'icone' => '▤', 'titulo' => 'Inventário', 'descricao' => 'Confira os itens disponíveis no seu perfil.'],
    ['pagina' => 'evolucao.php', 'icone' => '✦', 'titulo' => 'Evolução', 'descricao' => 'Evolua seus Pokémon com o item especial.'],
    ['pagina' => 'pokedex.php', 'icone' => '▧', 'titulo' => 'Pokédex', 'descricao' => 'Conheça os Pokémon, elementos e movimentos.'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu principal — MonstroBolso</title>
    <link rel="stylesheet" href="../assets/css/monstrobolso.css?v=1">
</head>
<body class="mb-body">
<div class="mb-shell">
    <header class="mb-header">
        <a class="mb-brand" href="inicio.php" aria-label="Voltar ao início do MonstroBolso">
            <span class="mb-brand-mark" aria-hidden="true">●</span>
            <span class="mb-brand-text">MonstroBolso</span>
        </a>
        <div class="mb-top-actions">
            <span class="mb-pill">Treinador: <?= escapar($perfil->getNome()) ?></span>
            <span class="mb-pill">✦ <?= (int) $perfil->getSaldo() ?> Pokédólares</span>
        </div>
    </header>

    <main>
        <section class="mb-menu-hero">
            <h1 class="mb-heading">Bem-vindo à sua jornada!</h1>
            <p class="mb-description">Escolha para onde deseja ir e prepare sua equipe para a aventura.</p>
        </section>

        <nav class="mb-menu-grid" aria-label="Menu principal">
            <?php foreach ($opcoes as $opcao): ?>
                <a class="mb-paper mb-menu-link" href="<?= escapar($opcao['pagina']) ?>">
                    <span class="mb-menu-icon" aria-hidden="true"><?= escapar($opcao['icone']) ?></span>
                    <strong><?= escapar($opcao['titulo']) ?></strong>
                    <small><?= escapar($opcao['descricao']) ?></small>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="mb-menu-bottom">
            <a class="mb-button mb-button-light" href="escolher_perfil.php">Trocar perfil</a>
            <a class="mb-button" href="logout.php">Sair da conta</a>
        </div>
    </main>
</div>
</body>
</html>
