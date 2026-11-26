<?php
require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';
exigirLogin();

$repositorio = new JogadorRepositorio();
$usuarioId = (int) $_SESSION['usuario_id'];
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['criar_perfil'])) {
        $nome = trim((string) ($_POST['nome_perfil'] ?? ''));
        if ($nome === '') {
            $erro = 'Informe um nome para o perfil.';
        } else {
            try {
                $repositorio->criarPerfil($usuarioId, $nome);
                header('Location: escolher_perfil.php');
                exit;
            } catch (PDOException $e) {
                $erro = $e->getCode() === '23000'
                    ? 'Voce ja possui um perfil com esse nome.'
                    : 'Nao foi possivel criar o perfil.';
            } catch (RuntimeException | InvalidArgumentException $e) {
                $erro = $e->getMessage();
            }
        }
    }
    if (isset($_POST['selecionar_perfil'])) {
        $perfilId = (int) ($_POST['perfil_id'] ?? 0);
        $perfisDisponiveis = $repositorio->listarPorUsuario($usuarioId);
        foreach ($perfisDisponiveis as $perfil) {
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
function mbEscape(string $texto): string {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar perfil - MonstroBolso</title>
    <link rel="stylesheet" href="../assets/css/monstrobolso.css">
    <link rel="stylesheet" href="../assets/css/autenticacao.css">
    <link rel="stylesheet" href="../assets/css/tema-final.css?v=6">
</head>
<body class="mb-body mb-scene-auth">
<div class="mb-auth-shell">
    <header class="mb-auth-header">
        <a class="mb-brand" href="escolher_perfil.php"><span class="mb-brand-mark" aria-hidden="true">●</span><span class="mb-brand-text">MonstroBolso</span></a>
        <a class="mb-pill" href="logout.php">Sair da conta</a>
    </header>
    <div class="mb-auth-profile-intro">
        <h1>Escolha seu treinador</h1>
        <p>Bem-vindo, <?= mbEscape((string) ($_SESSION['nome_usuario'] ?? 'treinador')) ?>! Selecione um perfil ou comece uma nova jornada.</p>
    </div>
    <?php if ($erro !== ''): ?><p class="mb-auth-alert" role="alert"><?= mbEscape($erro) ?></p><?php endif; ?>
    <main class="mb-auth-profile-layout">
        <section class="mb-paper mb-auth-profile-card">
            <span class="mb-auth-eyebrow">Sua jornada</span>
            <h2>Seus perfis</h2>
            <?php if (count($perfis) > 0): ?>
                <div class="mb-auth-profile-list">
                    <?php foreach ($perfis as $perfil): ?>
                        <form method="post" class="mb-auth-profile-row">
                            <div><strong><?= mbEscape($perfil->getNome()) ?></strong><small>Saldo: <?= (int) $perfil->getSaldo() ?> Pokédólares</small></div>
                            <input type="hidden" name="perfil_id" value="<?= (int) $perfil->getId() ?>">
                            <button class="mb-button" type="submit" name="selecionar_perfil" value="1">Jogar</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mb-auth-profile-empty">Você ainda não possui um perfil. Crie seu primeiro treinador ao lado.</p>
            <?php endif; ?>
        </section>
        <section class="mb-paper mb-auth-profile-card">
            <span class="mb-auth-eyebrow">Nova aventura</span>
            <h2>Criar perfil</h2>
            <p>Ao criar um perfil, você recebe um kit inicial para suas primeiras batalhas.</p>
            <form method="post" class="mb-auth-form">
                <label for="nome_perfil">Nome do treinador</label>
                <input type="text" name="nome_perfil" id="nome_perfil" maxlength="50" required placeholder="Nome do seu personagem">
                <button class="mb-button mb-button-green" type="submit" name="criar_perfil" value="1">Criar perfil</button>
            </form>
        </section>
    </main>
</div>
</body>
</html>
