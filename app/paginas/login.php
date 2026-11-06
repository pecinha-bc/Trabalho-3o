
<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/usuariorepositorio.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUsuario = $_POST['nome_usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';

    try {
        $repositorio = new UsuarioRepositorio();
        $usuario = $repositorio->autenticar($nomeUsuario, $senha);

        if ($usuario) {
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario->getId();
            $_SESSION['nome_usuario'] = $usuario->getNomeUsuario();

            header('Location: escolher_perfil.php');
            exit;
        }

        $erro = 'Nome de usuario ou senha incorretos.';
    } catch (PDOException $e) {
        $erro = 'Nao foi possivel realizar o login.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MonstroBolso</title>
</head>
<body>

    <h1>MonstroBolso</h1>
    <h2>Entrar</h2>

    <?php if ($erro !== ''): ?>
        <p><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="nome_usuario">Nome de usuario:</label>
        <input
            type="text"
            id="nome_usuario"
            name="nome_usuario"
            required
        >

        <label for="senha">Senha:</label>
        <input
            type="password"
            id="senha"
            name="senha"
            required
        >

        <button type="submit">Entrar</button>
    </form>

    <p>
        Ainda nao tem uma conta?
        <a href="cadastro.php">Cadastre-se</a>
    </p>

</body>
</html>
