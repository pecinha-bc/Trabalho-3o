<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../includes/itens.php';
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

            
            $consulta = $pdo->prepare(
                'SELECT saldo FROM perfil WHERE id = :perfil AND usuario_id = :usuario FOR UPDATE'
            );
            $consulta->execute([':perfil' => $perfilId, ':usuario' => $usuarioId]);
            $saldo = $consulta->fetchColumn();

            if ($saldo === false) {
                throw new RuntimeException('Perfil nao encontrado.');
            }

            
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
            $_SESSION['sucesso_loja'] = 'Compra realizada: ' . $quantidade . ' x ' . mbNomeItem($item) . '.';
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
<?php require_once __DIR__ . '/../includes/layout.php'; mbAbrirPagina('Loja', 'Prepare sua bolsa: poções, antídotos e itens especiais para cada etapa da aventura.', $perfil, 'loja.php'); ?>

<div class="mb-ui-stats" style="justify-content:center;margin-bottom:22px"><span class="mb-counter">✦ Saldo disponível: <?= (int) $perfil->getSaldo() ?> Pokédólares</span><a href="inventario.php" class="mb-button mb-button-light">Ver minha bolsa</a></div>
<?php if ($erro !== ''): ?><div class="mb-ui-alert is-error" role="alert"><?= escaparLoja($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== ''): ?><div class="mb-ui-alert is-success" role="status"><?= escaparLoja($sucesso) ?></div><?php endif; ?>
<?php if (!$itens): ?><div class="mb-ui-alert">Nenhum item disponível para compra. Confira os dados iniciais no banco.</div><?php endif; ?>
<div class="mb-ui-grid mb-ui-grid-4">
<?php foreach ($itens as $item): ?>
 <article class="mb-paper mb-ui-card">
  <div class="mb-ui-item-hero"><span class="mb-ui-kicker">ARTIGO DA LOJA</span><span class="mb-ui-item-symbol" aria-hidden="true"><img class="mb-ui-item-icon" src="<?= mbSeguro(mbIconeItem($item)) ?>" alt=""></span></div>
  <h2><?= escaparLoja(mbNomeItem($item)) ?></h2>
  <p class="mb-ui-price">✦ <?= (int) $item['preco'] ?></p>
  <p class="mb-ui-muted mb-ui-item-desc"><?= escaparLoja($nomesEfeitos[$item['tipo_efeito']] ?? $item['tipo_efeito']) ?>
    <?php if ((int) $item['valor_efeito'] > 0 && in_array($item['tipo_efeito'], ['cura_hp','reviver','restauracao_total'], true)): ?> (<?= (int) $item['valor_efeito'] ?>% do HP)<?php endif; ?>
  </p>
  <p><span class="mb-ui-stock">Na bolsa: <?= (int) $item['quantidade_possuida'] ?></span></p>
  <form method="post" class="mb-ui-form">
    <input type="hidden" name="token" value="<?= escaparLoja($_SESSION['token_loja']) ?>">
    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
    <label for="quantidade-<?= (int) $item['id'] ?>">Quantidade</label>
    <select id="quantidade-<?= (int) $item['id'] ?>" name="quantidade">
      <?php for ($q = 1; $q <= 5; $q++): ?><option value="<?= $q ?>"><?= $q ?></option><?php endfor; ?>
    </select>
    <button type="submit" class="mb-button mb-button-green">Comprar item</button>
  </form>
 </article>
<?php endforeach; ?>
</div>
<?php mbEncerrarPagina(); ?>
