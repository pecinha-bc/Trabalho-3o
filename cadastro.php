
<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/UsuarioRepositorio.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUsuario = trim($_POST['nome_usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if ($nomeUsuario === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas nao coincidem.';
    } else {
        try {
            $repositorio = new UsuarioRepositorio();
            $repositorio->cadastrar($nomeUsuario, $senha);

            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $erro = 'Esse nome de usuario ja esta cadastrado.';
            } else {
                $erro = 'Nao foi possivel realizar o cadastro.';
            }
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - MonstroBolso</title>
</head>
<body>

    <h1>MonstroBolso</h1>
    <h2>Criar conta</h2>

    <?php if ($erro !== ''): ?>
        <p><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="nome_usuario">Nome de usuario:</label>
        <input
            type="text"
            id="nome_usuario"
            name="nome_usuario"
            maxlength="50"
            required
        >

        <label for="senha">Senha:</label>
        <input
            type="password"
            id="senha"
            name="senha"
            required
        >

        <label for="confirmar_senha">Confirmar senha:</label>
        <input
            type="password"
            id="confirmar_senha"
            name="confirmar_senha"
            required
        >

        <button type="submit">Cadastrar</button>
    </form>

    <p>
        Ja possui uma conta?
        <a href="login.php">Fazer login</a>
    </p>

</body>
</html>
