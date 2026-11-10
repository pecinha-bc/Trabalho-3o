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

    // consulta somente os itens deste perfil, sempre com quantidade positiva
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

        // itens de evolucao nao podem ser usados durante uma batalha
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
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - MonstroBolso</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-family: Arial, sans-serif; color: #1d2736; background: #f2f4f8; }
        main { max-width: 1050px; margin: 0 auto; }
        h1 { margin: 0 0 8px; }
        h2 { margin: 30px 0 12px; }
        .descricao { color: #536274; line-height: 1.5; }
        .resumo { display: flex; flex-wrap: wrap; gap: 12px; margin: 22px 0; }
        .resumo div { background: #fff; padding: 14px 18px; border: 1px solid #ddd; border-radius: 10px; }
        .grade { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; }
        .item { padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 12px; }
        .item h3 { margin: 0 0 12px; font-size: 19px; }
        .efeito { color: #536274; line-height: 1.5; min-height: 44px; }
        .quantidade { display: inline-block; padding: 7px 12px; border-radius: 20px; background: #e9f1ff; color: #174f9b; font-weight: bold; }
        .especial { border-color: #bd94dc; }
        .aviso { background: #fff4d6; padding: 14px; border-radius: 9px; margin: 16px 0; }
        .erro { background: #f9dadd; }
        .acoes { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
        .botao { display: inline-block; padding: 12px 18px; background: #246bd6; color: #fff; border-radius: 7px; text-decoration: none; }
        .botao.secundario { background: #fff; color: #246bd6; border: 1px solid #246bd6; }
    </style>
</head>
<body>
<main>
    <h1>Inventario - MonstroBolso</h1>
    <p class="descricao">Consulte os itens comprados na loja e suas quantidades.</p>

    <div class="resumo">
        <div><strong>Treinador:</strong> <?= escaparInventario($perfil->getNome()) ?></div>
        <div><strong>Saldo:</strong> <?= (int) $perfil->getSaldo() ?> Pokedolares</div>
        <div><strong>Tipos de itens:</strong> <?= $tiposDiferentes ?></div>
        <div><strong>Total de unidades:</strong> <?= $quantidadeTotal ?></div>
    </div>

    <?php if ($mensagemErro !== ''): ?>
        <div class="aviso erro"><?= escaparInventario($mensagemErro) ?></div>
    <?php elseif ($quantidadeTotal === 0): ?>
        <div class="aviso">
            Seu inventario esta vazio. Visite a loja para comprar itens.
        </div>
    <?php else: ?>
        <h2>Itens para batalha</h2>
        <p class="descricao">Estes itens podem ser usados durante o combate, respeitando o limite da dificuldade escolhida.</p>

        <?php if (count($itensBatalha) === 0): ?>
            <p class="descricao">Voce ainda nao possui itens de batalha.</p>
        <?php else: ?>
            <div class="grade">
                <?php foreach ($itensBatalha as $item): ?>
                    <section class="item">
                        <h3><?= escaparInventario($item['nome']) ?></h3>
                        <p class="efeito"><?= escaparInventario(descreverEfeitoInventario($item)) ?></p>
                        <span class="quantidade">Quantidade: <?= $item['quantidade'] ?></span>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($itensEspeciais) > 0): ?>
            <h2>Itens especiais</h2>
            <p class="descricao">Esses itens ficam guardados no perfil, mas nao podem ser usados durante a batalha.</p>
            <div class="grade">
                <?php foreach ($itensEspeciais as $item): ?>
                    <section class="item especial">
                        <h3><?= escaparInventario($item['nome']) ?></h3>
                        <p class="efeito"><?= escaparInventario(descreverEfeitoInventario($item)) ?></p>
                        <span class="quantidade">Quantidade: <?= $item['quantidade'] ?></span>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="acoes">
        <a class="botao" href="loja.php">Ir para a loja</a>
        <a class="botao secundario" href="inicio.php">Voltar ao menu principal</a>
    </div>
</main>
</body>
</html>
