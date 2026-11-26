<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';

exigirLogin();

if (!isset($_SESSION['usuario_id'], $_SESSION['perfil_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$perfilId = (int) $_SESSION['perfil_id'];

$jogadorRepositorio = new JogadorRepositorio();
$perfil = $jogadorRepositorio->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

function escaparInventario($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function descreverEfeitoInventario(array $item): string
{
    $tipo = $item['tipo_efeito'];
    $valor = (int) $item['valor_efeito'];
    $status = $item['status_removido'];

    if ($tipo === 'cura_hp') {
        return 'Recupera ' . $valor . '% do HP maximo.';
    }

    if ($tipo === 'cura_total') {
        return 'Recupera todo o HP.';
    }

    if ($tipo === 'restauracao_total') {
        return 'Recupera ' . $valor . '% do HP e remove as condicoes especiais.';
    }

    if ($tipo === 'reviver') {
        return 'Revive um Pokemon derrotado com ' . $valor . '% do HP maximo.';
    }

    if ($tipo === 'cura_status') {
        if ($status === 'todos') {
            return 'Remove todas as condicoes especiais.';
        }
        return 'Remove a condicao: ' . $status . '.';
    }

    if ($tipo === 'evolucao') {
        return 'Usado fora da batalha para evoluir um Pokemon que ainda nao evoluiu.';
    }

    return 'Efeito do item cadastrado no banco de dados.';
}

$itensBatalha = [];
$itensEspeciais = [];
$quantidadeTotal = 0;
$mensagemErro = '';

try {
    $pdo = Conexao::conectar();

    
    $consulta = $pdo->prepare(
        'SELECT i.id, i.nome, i.tipo_efeito, i.valor_efeito,
                i.status_removido, i.aparece_inventario,
                inv.quantidade
         FROM inventario inv
         INNER JOIN item i ON i.id = inv.item_id
         WHERE inv.perfil_id = :perfil_id
           AND inv.quantidade > 0
         ORDER BY i.nome'
    );
    $consulta->execute([':perfil_id' => $perfilId]);

    foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $item['quantidade'] = (int) $item['quantidade'];
        $quantidadeTotal += $item['quantidade'];

        
        if ($item['tipo_efeito'] === 'evolucao' || !(bool) $item['aparece_inventario']) {
            $itensEspeciais[] = $item;
        } else {
            $itensBatalha[] = $item;
        }
    }
} catch (PDOException $e) {
    $mensagemErro = 'Nao foi possivel consultar o inventario. Confira a conexao com o banco.';
}

$tiposDiferentes = count($itensBatalha) + count($itensEspeciais);
?>
<?php require_once __DIR__ . '/../includes/layout.php'; mbAbrirPagina('Inventário', 'Os itens que você conquistou ou comprou, sempre à mão.', $perfil, 'inventario.php'); ?>

<div class="mb-ui-stats" style="justify-content:center;margin-bottom:23px">
 <span class="mb-counter">✦ <?= (int) $perfil->getSaldo() ?> Pokédólares</span><span class="mb-counter">▣ <?= $tiposDiferentes ?> tipos de itens</span><span class="mb-counter">🎒 <?= $quantidadeTotal ?> unidades</span>
</div>
<?php if ($mensagemErro !== ''): ?><div class="mb-ui-alert is-error"><?= escaparInventario($mensagemErro) ?></div>
<?php elseif ($quantidadeTotal === 0): ?><div class="mb-ui-alert">Sua bolsa está vazia. Visite a loja para comprar itens.</div>
<?php else: ?>
 <h2 class="mb-ui-section-title">Itens de batalha</h2>
 <?php if (!$itensBatalha): ?><div class="mb-paper mb-ui-card">Nenhum item de batalha por enquanto.</div><?php else: ?>
  <div class="mb-ui-grid mb-ui-grid-4">
  <?php foreach ($itensBatalha as $item): ?>
   <article class="mb-paper mb-ui-card">
    <div class="mb-ui-item-hero"><span class="mb-ui-kicker">CONSUMÍVEL</span><span class="mb-ui-item-symbol" aria-hidden="true"><img class="mb-ui-item-icon" src="<?= mbSeguro(mbIconeItem($item)) ?>" alt=""></span></div>
    <h2><?= escaparInventario(mbNomeItem($item)) ?></h2>
    <p class="mb-ui-muted mb-ui-item-desc"><?= escaparInventario(descreverEfeitoInventario($item)) ?></p>
    <span class="mb-ui-badge">Quantidade: <?= (int) $item['quantidade'] ?></span>
   </article>
  <?php endforeach; ?>
  </div>
 <?php endif; ?>
 <?php if ($itensEspeciais): ?>
   <h2 class="mb-ui-section-title">Itens especiais</h2>
   <div class="mb-ui-grid mb-ui-grid-4">
   <?php foreach ($itensEspeciais as $item): ?>
    <article class="mb-paper mb-ui-card">
     <div class="mb-ui-item-hero"><span class="mb-ui-kicker">RARIDADE ESPECIAL</span><span class="mb-ui-item-symbol" aria-hidden="true"><img class="mb-ui-item-icon" src="<?= mbSeguro(mbIconeItem($item)) ?>" alt=""></span></div>
     <h2><?= escaparInventario(mbNomeItem($item)) ?></h2>
     <p class="mb-ui-muted mb-ui-item-desc"><?= escaparInventario(descreverEfeitoInventario($item)) ?></p>
     <span class="mb-ui-badge mb-ui-badge-gold">Quantidade: <?= (int) $item['quantidade'] ?></span>
    </article>
   <?php endforeach; ?>
   </div>
 <?php endif; ?>
<?php endif; ?>
<div class="mb-ui-actions"><a class="mb-button mb-button-green" href="loja.php">Visitar a loja</a><a class="mb-button mb-button-light" href="evolucao.php">Evoluir Pokémon</a></div>
<?php mbEncerrarPagina(); ?>
