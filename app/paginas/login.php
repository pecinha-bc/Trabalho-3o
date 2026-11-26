<?php
require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/usuariorepositorio.php';

$erro = '';
$nomeUsuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUsuario = trim((string) ($_POST['nome_usuario'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

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
        $erro = 'Nao foi possivel realizar o login. Verifique o banco de dados.';
    }
}
function mbEscape(string $texto): string {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — MonstroBolso</title>
<link rel="stylesheet" href="../assets/css/monstrobolso.css?v=6">
<link rel="stylesheet" href="../assets/css/autenticacao.css?v=6">
<link rel="stylesheet" href="../assets/css/tema-final.css?v=6">
</head>
<body class="mb-body mb-scene-auth">
<main class="mb-auth-center">
<a class="mb-auth-logo" href="login.php"><span class="mb-logo-ball" aria-hidden="true"></span>MonstroBolso</a>
<section class="mb-paper mb-auth-parchment">
<h1>LOGIN</h1>
<p class="mb-auth-subtitle">Acesse sua conta e continue sua jornada!</p>
<?php if ($erro !== ''): ?><p class="mb-auth-alert" role="alert"><?= mbEscape($erro) ?></p><?php endif; ?>
<form method="post" class="mb-auth-form">
<label for="nome_usuario">Nome de usuário</label>
<input id="nome_usuario" name="nome_usuario" type="text" maxlength="50" autocomplete="username" required placeholder="Ex.: seu nome de treinador" value="<?= mbEscape($nomeUsuario) ?>">
<label for="senha">Senha</label>
<div class="mb-auth-password"><input id="senha" name="senha" type="password" autocomplete="current-password" required placeholder="Digite sua senha"><button type="button" class="mb-visibility" aria-label="Mostrar senha" data-password="senha">◉</button></div>
<button type="submit" class="mb-auth-submit">ENTRAR</button>
</form>
<div class="mb-auth-bottom">Ainda não tem uma conta? <a href="cadastro.php">CADASTRE-SE</a></div>
</section></main>
<script>
document.querySelectorAll('[data-password]').forEach(button => button.addEventListener('click', () => {
 const input = document.getElementById(button.dataset.password);
 input.type = input.type === 'password' ? 'text' : 'password';
 button.setAttribute('aria-label',input.type === 'password' ? 'Mostrar senha' : 'Ocultar senha');
}));
</script></body></html>
