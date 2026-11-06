
<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/JogadorRepositorio.php';

exigirLogin();

$repositorio = new JogadorRepositorio();
$usuarioId = (int) $_SESSION['usuario_id'];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['criar_perfil'])) {
        $nome = trim($_POST['nome_perfil'] ?? '');

        if ($nome === '') {
            $erro = 'Informe um nome para o perfil.';
        } else {
            try {
                $repositorio->criarPerfil($usuarioId, $nome);
                header('Location: escolher_perfil.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $erro = 'Voce ja possui um perfil com esse nome.';
                } else {
                    $erro = 'Nao foi possivel criar o perfil.';
                }
            }
        }
    }

    if (isset($_POST['selecionar_perfil'])) {
        $perfilId = (int) ($_POST['perfil_id'] ?? 0);
        $perfis = $repositorio->listarPorUsuario($usuarioId);

        foreach ($perfis as $perfil) {
            if ($perfil->getId() === $perfilId) {
                $_SESSION['perfil_id'] = $perfil->getId();
                $_SESSION['nome_perfil'] = $perfil->getNome();

                header('Location: inicio.php');
                exit;
            }
        }

        $erro = 'Perfil invalido.';
    }
}

$perfis = $repositorio->listarPorUsuario($usuarioId);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escolher Perfil - MonstroBolso</title>
</head>
<body>

    <h1>MonstroBolso</h1>

    <h2>Bem-vindo, <?= htmlspecialchars($_SESSION['nome_usuario']) ?>!</h2>

    <h3>Seus perfis</h3>

    <?php if ($erro !== ''): ?>
        <p><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <?php if (count($perfis) > 0): ?>

        <?php foreach ($perfis as $perfil): ?>
            <form method="POST">
                <input
                    type="hidden"
                    name="perfil_id"
                    value="<?= $perfil->getId() ?>"
                >

                <p>
                    <?= htmlspecialchars($perfil->getNome()) ?>
                    - Saldo: <?= $perfil->getSaldo() ?> Pokédólares
                </p>

                <button type="submit" name="selecionar_perfil">
                    Escolher perfil
                </button>
            </form>
        <?php endforeach; ?>

    <?php else: ?>
        <p>Voce ainda nao possui perfis cadastrados.</p>
    <?php endif; ?>

    <h3>Criar novo perfil</h3>

    <form method="POST">
        <label for="nome_perfil">Nome do personagem:</label>

        <input
            type="text"
            id="nome_perfil"
            name="nome_perfil"
            maxlength="50"
            required
        >

        <button type="submit" name="criar_perfil">
            Criar perfil
        </button>
    </form>

    <p>
        <a href="logout.php">Sair da conta</a>
    </p>

</body>
</html>
