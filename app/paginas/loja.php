<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';

exigirLogin();

if (!isset($_SESSION['perfil_id'], $_SESSION['usuario_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$perfilId = (int) $_SESSION['perfil_id'];
$usuarioId = (int) $_SESSION['usuario_id'];
$repositorio = new JogadorRepositorio();
$perfil = $repositorio->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

$pdo = Conexao::conectar();
$erro = '';
$sucesso = $_SESSION['sucesso_loja'] ?? '';
unset($_SESSION['sucesso_loja']);

if (!isset($_SESSION['token_loja'])) {
    $_SESSION['token_loja'] = bin2hex(random_bytes(32));
}

function escaparLoja($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $itemInformado = $_POST['item_id'] ?? null;
    $quantidadeInformada = $_POST['quantidade'] ?? null;

    $itemId = is_string($itemInformado)
        ? filter_var($itemInformado, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $quantidade = is_string($quantidadeInformada)
        ? filter_var($quantidadeInformada, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]])
        : false;

    if (!is_string($token) || !hash_equals($_SESSION['token_loja'], $token)) {
        $erro = 'Formulario invalido. Atualize a pagina.';
    } elseif ($itemId === false || $quantidade === false) {
        $erro = 'Escolha um item e uma quantidade de 1 a 5.';
    } else {
        try {
            $pdo->beginTransaction();

            // bloqueia o saldo do perfil durante a compra
            $consulta = $pdo->prepare(
                'SELECT saldo FROM perfil WHERE id = :perfil AND usuario_id = :usuario FOR UPDATE'
            );
            $consulta->execute([':perfil' => $perfilId, ':usuario' => $usuarioId]);
            $saldo = $consulta->fetchColumn();

            if ($saldo === false) {
                throw new RuntimeException('Perfil nao encontrado.');
            }

            // usa o preco oficial cadastrado no banco
            $consulta = $pdo->prepare(
                'SELECT id, nome, preco FROM item WHERE id = :id AND aparece_loja = TRUE'
            );
            $consulta->execute([':id' => $itemId]);
            $item = $consulta->fetch();

            if (!$item) {
                throw new RuntimeException('Item indisponivel na loja.');
            }

            $custo = (int) $item['preco'] * $quantidade;

            if ($custo > (int) $saldo) {
                throw new RuntimeException('Saldo insuficiente para esta compra.');
            }

            $consulta = $pdo->prepare(
                'UPDATE perfil SET saldo = saldo - :custo WHERE id = :perfil AND saldo >= :custo_minimo'
            );
            $consulta->execute([
                ':custo' => $custo,
                ':perfil' => $perfilId,
                ':custo_minimo' => $custo
            ]);

            if ($consulta->rowCount() !== 1 && $custo > 0) {
                throw new RuntimeException('Nao foi possivel descontar o saldo.');
            }

            // soma a compra ao inventario ja existente
            $consulta = $pdo->prepare(
                'INSERT INTO inventario (perfil_id, item_id, quantidade)
                 VALUES (:perfil, :item, :quantidade)
                 ON DUPLICATE KEY UPDATE quantidade = quantidade + VALUES(quantidade)'
            );
            $consulta->execute([
                ':perfil' => $perfilId,
                ':item' => $itemId,
                ':quantidade' => $quantidade
            ]);

            $pdo->commit();
            $_SESSION['token_loja'] = bin2hex(random_bytes(32));
            $_SESSION['sucesso_loja'] = 'Compra realizada: ' . $quantidade . ' x ' . $item['nome'] . '.';
            header('Location: loja.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = $e instanceof PDOException
                ? 'Erro ao registrar a compra no banco de dados.'
                : $e->getMessage();
        }
    }
}

// atualiza os dados apresentados depois de qualquer compra
$perfil = $repositorio->buscarPorId($perfilId);

$consulta = $pdo->prepare(
    'SELECT i.id, i.nome, i.preco, i.tipo_efeito, i.valor_efeito,
            i.status_removido, COALESCE(inv.quantidade, 0) AS quantidade_possuida
     FROM item i
     LEFT JOIN inventario inv ON inv.item_id = i.id AND inv.perfil_id = :perfil
     WHERE i.aparece_loja = TRUE
     ORDER BY i.preco, i.nome'
);
$consulta->execute([':perfil' => $perfilId]);
$itens = $consulta->fetchAll();

$nomesEfeitos = [
    'cura_hp' => 'Recupera HP',
    'cura_total' => 'Recupera todo o HP',
    'cura_status' => 'Remove condicao especial',
    'restauracao_total' => 'Recupera HP e remove condicoes',
    'reviver' => 'Revive um Pokemon derrotado',
    'evolucao' => 'Permite uma evolucao fora da batalha'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja - MonstroBolso</title>
    <style>
        body { margin: 0; padding: 24px; font-family: Arial, sans-serif; background: #f2f4f8; color: #1d2736; }
        main { max-width: 1050px; margin: auto; }
        h1 { margin-bottom: 8px; }
        .resumo { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin: 20px 0; }
        .saldo { background: #fff; padding: 14px 18px; border-radius: 10px; border: 1px solid #ddd; }
        .grade { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
        .item { background: white; border: 1px solid #ddd; border-radius: 12px; padding: 20px; }
        .item h2 { margin: 0 0 10px; font-size: 20px; }
        .preco { color: #17633a; font-weight: bold; font-size: 18px; }
        .detalhe { color: #516072; min-height: 38px; }
        .mensagem { padding: 12px; border-radius: 8px; margin: 16px 0; background: #d8f3e3; }
        .erro { background: #f9dadd; }
        label { display: block; margin: 12px 0 7px; }
        select { padding: 9px; width: 100%; box-sizing: border-box; }
        button { width: 100%; background: #226ad5; color: white; border: 0; border-radius: 7px; padding: 12px; margin-top: 12px; cursor: pointer; }
        button:hover { background: #184e9d; }
        a { color: #226ad5; }
        .voltar { margin-top: 25px; }
    </style>
</head>
<body>
<main>
    <h1>Loja MonstroBolso</h1>
    <p>Compre itens para curar sua equipe e preparar suas batalhas.</p>

    <div class="resumo">
        <div><strong>Treinador:</strong> <?= escaparLoja($perfil->getNome()) ?></div>
        <div class="saldo"><strong>Saldo:</strong> <?= (int) $perfil->getSaldo() ?> Pokedolares</div>
    </div>

    <?php if ($erro !== ''): ?>
        <div class="mensagem erro"><?= escaparLoja($erro) ?></div>
    <?php endif; ?>
    <?php if ($sucesso !== ''): ?>
        <div class="mensagem"><?= escaparLoja($sucesso) ?></div>
    <?php endif; ?>

    <div class="grade">
        <?php foreach ($itens as $item): ?>
            <section class="item">
                <h2><?= escaparLoja($item['nome']) ?></h2>
                <p class="preco"><?= (int) $item['preco'] ?> Pokedolares</p>
                <p class="detalhe">
                    <?= escaparLoja($nomesEfeitos[$item['tipo_efeito']] ?? $item['tipo_efeito']) ?>
                    <?php if ((int) $item['valor_efeito'] > 0 &&
                              in_array($item['tipo_efeito'], ['cura_hp', 'reviver', 'restauracao_total'], true)): ?>
                        (<?= (int) $item['valor_efeito'] ?>% do HP)
                    <?php endif; ?>
                </p>
                <p><strong>No inventario:</strong> <?= (int) $item['quantidade_possuida'] ?></p>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= escaparLoja($_SESSION['token_loja']) ?>">
                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                    <label for="quantidade-<?= (int) $item['id'] ?>">Quantidade</label>
                    <select id="quantidade-<?= (int) $item['id'] ?>" name="quantidade">
                        <?php for ($q = 1; $q <= 5; $q++): ?>
                            <option value="<?= $q ?>"><?= $q ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit">Comprar</button>
                </form>
            </section>
        <?php endforeach; ?>
    </div>

    <p class="voltar"><a href="inicio.php">Voltar ao menu principal</a></p>
</main>
</body>
</html>
