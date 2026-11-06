
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
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MonstroBolso - Inicio</title>
</head>
<body>

    <h1>MonstroBolso</h1>

    <h2>
        Bem-vindo, <?= htmlspecialchars($perfil->getNome()) ?>!
    </h2>

    <p>
        Saldo:
        <?= $perfil->getSaldo() ?> Pokédólares
    </p>

    <h3>Menu principal</h3>

    <nav>
        <ul>
            <li><a href="equipe.php">Minha equipe</a></li>
            <li><a href="pokedex.php">Pokédex</a></li>
            <li><a href="batalhas.php">Batalhas</a></li>
            <li><a href="loja.php">Loja</a></li>
            <li><a href="inventario.php">Inventário</a></li>
            <li><a href="evolucao.php">Evolução</a></li>
        </ul>
    </nav>

    <p>
        <a href="escolher_perfil.php">Trocar perfil</a>
    </p>

    <p>
        <a href="logout.php">Sair da conta</a>
    </p>

</body>
</html>
