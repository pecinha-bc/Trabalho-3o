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

function escaparEvolucao($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// aplica o aumento de 30% com arredondamento para inteiro
function aumentarEvolucao(int $valor): int
{
    return (int) round($valor * 1.30, 0, PHP_ROUND_HALF_UP);
}

$pdo = Conexao::conectar();
$erro = '';
$sucesso = $_SESSION['sucesso_evolucao'] ?? '';
unset($_SESSION['sucesso_evolucao']);

if (!isset($_SESSION['token_evolucao'])) {
    $_SESSION['token_evolucao'] = bin2hex(random_bytes(32));
}

// a evolucao e feita em uma unica transacao, incluindo o consumo do item
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['token'] ?? '';
    $informado = $_POST['pokemon_perfil_id'] ?? null;
    $pokemonPerfilId = is_string($informado)
        ? filter_var($informado, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if (!is_string($token) || !hash_equals($_SESSION['token_evolucao'], $token)) {
        $erro = 'Formulario invalido. Atualize a pagina.';
    } elseif ($pokemonPerfilId === false) {
        $erro = 'Escolha um Pokemon valido.';
    } else {
        try {
            $pdo->beginTransaction();

            // trava o perfil e confirma que ele pertence ao usuario conectado
            $consulta = $pdo->prepare(
                'SELECT id FROM perfil WHERE id = :perfil AND usuario_id = :usuario FOR UPDATE'
            );
            $consulta->execute([':perfil' => $perfilId, ':usuario' => $usuarioId]);
            if (!$consulta->fetch()) {
                throw new RuntimeException('Perfil nao encontrado.');
            }

            // a evolucao e proibida durante uma batalha
            $consulta = $pdo->prepare(
                "SELECT id FROM batalha WHERE perfil_id = :perfil AND resultado = 'em_andamento' LIMIT 1"
            );
            $consulta->execute([':perfil' => $perfilId]);
            if ($consulta->fetch()) {
                throw new RuntimeException('Finalize sua batalha antes de evoluir um Pokemon.');
            }

            // consulta e trava o Pokemon pertencente ao perfil
            $consulta = $pdo->prepare(
                'SELECT pp.id, pp.pokemon_id, pp.hp_atual, pp.hp_maximo,
                        pp.ataque, pp.defesa, pp.velocidade, pp.evoluiu,
                        p.nome AS nome_atual, p.evolui_para_id
                 FROM pokemon_perfil pp
                 INNER JOIN pokemon p ON p.id = pp.pokemon_id
                 WHERE pp.id = :pokemon AND pp.perfil_id = :perfil
                 FOR UPDATE'
            );
            $consulta->execute([':pokemon' => $pokemonPerfilId, ':perfil' => $perfilId]);
            $pokemon = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$pokemon) {
                throw new RuntimeException('Este Pokemon nao pertence ao seu perfil.');
            }
            if ((int) $pokemon['evoluiu'] === 1) {
                throw new RuntimeException('Este Pokemon ja evoluiu uma vez.');
            }
            if ($pokemon['evolui_para_id'] === null) {
                throw new RuntimeException('Este Pokemon nao possui evolucao disponivel.');
            }

            $consulta = $pdo->prepare('SELECT id, nome FROM pokemon WHERE id = :id');
            $consulta->execute([':id' => (int) $pokemon['evolui_para_id']]);
            $evolucao = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$evolucao) {
                throw new RuntimeException('Especie evoluida nao encontrada.');
            }

            // confirma e trava a unidade do item de evolucao
            $consulta = $pdo->prepare(
                "SELECT inv.id, inv.quantidade
                 FROM inventario inv
                 INNER JOIN item i ON i.id = inv.item_id
                 WHERE inv.perfil_id = :perfil
                   AND i.nome = 'Item de Evolucao'
                   AND i.tipo_efeito = 'evolucao'
                 FOR UPDATE"
            );
            $consulta->execute([':perfil' => $perfilId]);
            $item = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$item || (int) $item['quantidade'] < 1) {
                throw new RuntimeException('Voce precisa comprar um Item de Evolucao na loja.');
            }

            $novoHpMaximo = aumentarEvolucao((int) $pokemon['hp_maximo']);
            $novoHpAtual = min($novoHpMaximo, aumentarEvolucao((int) $pokemon['hp_atual']));
            $novoAtaque = aumentarEvolucao((int) $pokemon['ataque']);
            $novaDefesa = aumentarEvolucao((int) $pokemon['defesa']);
            $novaVelocidade = aumentarEvolucao((int) $pokemon['velocidade']);

            // o gatilho trg_valida_hp_pokemon_perfil valida e marca evoluiu = 1
            $consulta = $pdo->prepare(
                'UPDATE pokemon_perfil
                 SET pokemon_id = :evolucao,
                     hp_atual = :hp_atual,
                     hp_maximo = :hp_maximo,
                     ataque = :ataque,
                     defesa = :defesa,
                     velocidade = :velocidade
                 WHERE id = :id AND perfil_id = :perfil
                   AND pokemon_id = :antigo AND evoluiu = 0'
            );
            $consulta->execute([
                ':evolucao' => (int) $evolucao['id'],
                ':hp_atual' => $novoHpAtual,
                ':hp_maximo' => $novoHpMaximo,
                ':ataque' => $novoAtaque,
                ':defesa' => $novaDefesa,
                ':velocidade' => $novaVelocidade,
                ':id' => $pokemonPerfilId,
                ':perfil' => $perfilId,
                ':antigo' => (int) $pokemon['pokemon_id']
            ]);
            if ($consulta->rowCount() !== 1) {
                throw new RuntimeException('Nao foi possivel atualizar o Pokemon.');
            }

            $consulta = $pdo->prepare(
                'UPDATE inventario SET quantidade = quantidade - 1
                 WHERE id = :id AND quantidade > 0'
            );
            $consulta->execute([':id' => (int) $item['id']]);
            if ($consulta->rowCount() !== 1) {
                throw new RuntimeException('Nao foi possivel consumir o item de evolucao.');
            }

            $pdo->commit();

            $_SESSION['sucesso_evolucao'] = $pokemon['nome_atual'] .
                ' evoluiu para ' . $evolucao['nome'] . '!';
            $_SESSION['token_evolucao'] = bin2hex(random_bytes(32));
            header('Location: evolucao.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = $e instanceof PDOException
                ? 'Nao foi possivel concluir a evolucao no banco de dados.'
                : $e->getMessage();
        }
    }
}

// consulta a quantidade do item disponivel para este perfil
$consulta = $pdo->prepare(
    "SELECT COALESCE(inv.quantidade, 0)
     FROM item i
     LEFT JOIN inventario inv ON inv.item_id = i.id AND inv.perfil_id = :perfil
     WHERE i.nome = 'Item de Evolucao' AND i.tipo_efeito = 'evolucao'
     LIMIT 1"
);
$consulta->execute([':perfil' => $perfilId]);
$quantidadeItem = (int) ($consulta->fetchColumn() ?: 0);

// nao permitir evoluir enquanto existir uma batalha em andamento
$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM batalha WHERE perfil_id = :perfil AND resultado = 'em_andamento'"
);
$consulta->execute([':perfil' => $perfilId]);
$emBatalha = (int) $consulta->fetchColumn() > 0;

// lista os Pokemon do perfil com a proxima evolucao disponivel
$consulta = $pdo->prepare(
    'SELECT pp.id, pp.hp_atual, pp.hp_maximo, pp.ataque,
            pp.defesa, pp.velocidade, pp.nivel, pp.evoluiu,
            p.nome, p.tipo, p.evolui_para_id,
            e.nome AS nome_evolucao
     FROM pokemon_perfil pp
     INNER JOIN pokemon p ON p.id = pp.pokemon_id
     LEFT JOIN pokemon e ON e.id = p.evolui_para_id
     WHERE pp.perfil_id = :perfil
     ORDER BY pp.id'
);
$consulta->execute([':perfil' => $perfilId]);
$pokemons = $consulta->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evolucao - MonstroBolso</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-family: Arial, sans-serif; color: #243044; background: #f2f4f8; }
        main { max-width: 1060px; margin: 0 auto; }
        h1 { margin-bottom: 8px; }
        .descricao { color: #5a687a; line-height: 1.5; }
        .resumo { background: white; border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin: 18px 0; }
        .grade { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-top: 20px; }
        .pokemon { background: white; border: 1px solid #ddd; border-radius: 12px; padding: 20px; }
        .pokemon h2 { font-size: 21px; margin: 0 0 8px; }
        .dados { line-height: 1.8; }
        .futuro { background: #f0f5ff; padding: 12px; border-radius: 8px; margin: 14px 0; }
        .aviso { padding: 12px 15px; border-radius: 8px; margin: 15px 0; background: #fff0cd; }
        .erro { background: #f9dadd; }
        .sucesso { background: #d8f3e3; }
        button { padding: 12px 16px; width: 100%; background: #246bd6; color: #fff; border: none; border-radius: 7px; cursor: pointer; }
        button:disabled { background: #a5b0be; cursor: not-allowed; }
        a { color: #246bd6; }
        .links { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 30px; }
    </style>
</head>
<body>
<main>
    <h1>Evolucao de Pokemon</h1>
    <p class="descricao">Cada Pokemon pode evoluir uma unica vez, usando um Item de Evolucao comprado na loja. A evolucao aumenta o HP, ataque, defesa e velocidade em 30%.</p>

    <div class="resumo">
        <strong>Treinador:</strong> <?= escaparEvolucao($perfil->getNome()) ?>
        &nbsp; | &nbsp;
        <strong>Itens de Evolucao:</strong> <?= $quantidadeItem ?>
    </div>

    <?php if ($erro !== ''): ?>
        <div class="aviso erro"><?= escaparEvolucao($erro) ?></div>
    <?php endif; ?>
    <?php if ($sucesso !== ''): ?>
        <div class="aviso sucesso"><?= escaparEvolucao($sucesso) ?></div>
    <?php endif; ?>
    <?php if ($emBatalha): ?>
        <div class="aviso">Voce possui uma batalha em andamento. Finalize-a antes de evoluir um Pokemon.</div>
    <?php elseif ($quantidadeItem < 1): ?>
        <div class="aviso">Voce nao possui um Item de Evolucao. Compre um na loja para evoluir seus Pokemon.</div>
    <?php endif; ?>

    <div class="grade">
        <?php foreach ($pokemons as $pokemon): ?>
            <?php
                $jaEvoluiu = (int) $pokemon['evoluiu'] === 1;
                $podeEvoluir = !$jaEvoluiu && $pokemon['evolui_para_id'] !== null;
                $podeConfirmar = $podeEvoluir && !$emBatalha && $quantidadeItem > 0;
            ?>
            <section class="pokemon">
                <h2><?= escaparEvolucao($pokemon['nome']) ?></h2>
                <p><strong>Tipo:</strong> <?= escaparEvolucao($pokemon['tipo']) ?></p>
                <p><strong>Nivel:</strong> <?= (int) $pokemon['nivel'] ?></p>
                <div class="dados">
                    HP: <?= (int) $pokemon['hp_atual'] ?> / <?= (int) $pokemon['hp_maximo'] ?><br>
                    Ataque: <?= (int) $pokemon['ataque'] ?><br>
                    Defesa: <?= (int) $pokemon['defesa'] ?><br>
                    Velocidade: <?= (int) $pokemon['velocidade'] ?>
                </div>

                <?php if ($podeEvoluir): ?>
                    <div class="futuro">
                        <strong>Evolucao: <?= escaparEvolucao($pokemon['nome_evolucao']) ?></strong>
                        <div class="dados">
                            HP maximo: <?= (int) $pokemon['hp_maximo'] ?> → <?= aumentarEvolucao((int) $pokemon['hp_maximo']) ?><br>
                            Ataque: <?= (int) $pokemon['ataque'] ?> → <?= aumentarEvolucao((int) $pokemon['ataque']) ?><br>
                            Defesa: <?= (int) $pokemon['defesa'] ?> → <?= aumentarEvolucao((int) $pokemon['defesa']) ?><br>
                            Velocidade: <?= (int) $pokemon['velocidade'] ?> → <?= aumentarEvolucao((int) $pokemon['velocidade']) ?>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="token" value="<?= escaparEvolucao($_SESSION['token_evolucao']) ?>">
                        <input type="hidden" name="pokemon_perfil_id" value="<?= (int) $pokemon['id'] ?>">
                        <button type="submit" <?= !$podeConfirmar ? 'disabled' : '' ?>
                                onclick="return confirm('Evoluir este Pokemon e consumir um Item de Evolucao?');">
                            Evoluir Pokemon
                        </button>
                    </form>
                <?php else: ?>
                    <div class="aviso"><?= $jaEvoluiu ? 'Este Pokemon ja evoluiu e nao pode evoluir novamente.' : 'Este Pokemon nao possui evolucao disponivel.' ?></div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <?php if (count($pokemons) === 0): ?>
        <div class="aviso">Sua equipe ainda esta vazia. Escolha seus Pokemon primeiro.</div>
    <?php endif; ?>

    <nav class="links">
        <a href="loja.php">Comprar Item de Evolucao</a>
        <a href="inventario.php">Ver inventario</a>
        <a href="equipe.php">Minha equipe</a>
        <a href="inicio.php">Voltar ao menu</a>
    </nav>
</main>
</body>
</html>
