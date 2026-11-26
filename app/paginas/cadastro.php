<?php
require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/usuariorepositorio.php';

$erro = '';
$nomeUsuario = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUsuario = trim((string) ($_POST['nome_usuario'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');
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
            $erro = $e->getCode() === '23000'
                ? 'Esse nome de usuario ja esta cadastrado.'
                : 'Nao foi possivel realizar o cadastro.';
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
        }
    }
}
function mbEscape(string $texto): string {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastro — MonstroBolso</title>
<link rel="stylesheet" href="../assets/css/monstrobolso.css?v=6">
<link rel="stylesheet" href="../assets/css/autenticacao.css?v=6">
<link rel="stylesheet" href="../assets/css/tema-final.css?v=6">
</head>
<body class="mb-body mb-scene-auth">
<main class="mb-auth-center">
<a class="mb-auth-logo" href="login.php"><span class="mb-logo-ball" aria-hidden="true"></span>MonstroBolso</a>
<section class="mb-paper mb-auth-parchment">
<h1>CADASTRO</h1>
<p class="mb-auth-subtitle">Crie sua conta e comece sua jornada como Treinador!</p>
<?php if ($erro !== ''): ?><p class="mb-auth-alert" role="alert"><?= mbEscape($erro) ?></p><?php endif; ?>
<form method="post" class="mb-auth-form">
<label for="nome_usuario">Nome de usuário</label>
<input id="nome_usuario" name="nome_usuario" type="text" maxlength="50" autocomplete="username" required placeholder="Ex.: seu nome de treinador" value="<?= mbEscape($nomeUsuario) ?>">
<label for="senha">Senha</label>
<div class="mb-auth-password"><input id="senha" name="senha" type="password" autocomplete="new-password" required placeholder="Crie uma senha"><button type="button" class="mb-visibility" aria-label="Mostrar senha" data-password="senha">◉</button></div>
<label for="confirmar_senha">Confirmar senha</label>
<div class="mb-auth-password"><input id="confirmar_senha" name="confirmar_senha" type="password" autocomplete="new-password" required placeholder="Repita sua senha"><button type="button" class="mb-visibility" aria-label="Mostrar confirmação" data-password="confirmar_senha">◉</button></div>
<button type="submit" class="mb-auth-submit">CADASTRAR</button>
</form>
<div class="mb-auth-bottom">Já possui uma conta? <a href="login.php">Fazer login</a></div>
</section></main>
<script>
document.querySelectorAll('[data-password]').forEach(button => button.addEventListener('click', () => {
 const input = document.getElementById(button.dataset.password);
 input.type = input.type === 'password' ? 'text' : 'password';
 button.setAttribute('aria-label',input.type === 'password' ? 'Mostrar senha' : 'Ocultar senha');
}));
</script></body></html>
