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

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menu Inicial — MonstroBolso</title>
<link rel="stylesheet" href="../assets/css/monstrobolso.css?v=6">
<link rel="stylesheet" href="../assets/css/tema-final.css?v=11">
</head>
<body class="mb-body mb-scene-menu">
<main class="mb-approved-menu" aria-label="Menu Inicial">
<div class="mb-menu-status"><span>Treinador: <?= escapar($perfil->getNome()) ?></span><span>✦ <?= (int) $perfil->getSaldo() ?> Pokédólares</span><a href="escolher_perfil.php">Trocar perfil</a><a href="logout.php">Sair</a></div>
<div class="mb-menu-visual">
<img src="../assets/imagens/menu-aprovado.webp" alt="MonstroBolso — Menu Inicial: Minha Equipe, Batalhas, Loja, Inventário, Evolução e Pokedex" width="1671" height="941">
<nav class="mb-menu-hotspots" aria-label="Escolha uma área do jogo">
<a class="mb-hotspot mb-hotspot-1" href="equipe.php" aria-label="Minha Equipe"><span>Minha Equipe</span></a>
<a class="mb-hotspot mb-hotspot-2" href="batalhas.php" aria-label="Batalhas"><span>Batalhas</span></a>
<a class="mb-hotspot mb-hotspot-3" href="loja.php" aria-label="Loja"><span>Loja</span></a>
<a class="mb-hotspot mb-hotspot-4" href="inventario.php" aria-label="Inventário"><span>Inventário</span></a>
<a class="mb-hotspot mb-hotspot-5" href="evolucao.php" aria-label="Evolução"><span>Evolução</span></a>
<a class="mb-hotspot mb-hotspot-6" href="pokedex.php" aria-label="Pokedex"><span>Pokedex</span></a>
</nav>
</div>
</main>
</body>
</html>
