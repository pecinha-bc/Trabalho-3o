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

            
            $consulta = $pdo->prepare(
                'SELECT id FROM perfil WHERE id = :perfil AND usuario_id = :usuario FOR UPDATE'
            );
            $consulta->execute([':perfil' => $perfilId, ':usuario' => $usuarioId]);
            if (!$consulta->fetch()) {
                throw new RuntimeException('Perfil nao encontrado.');
            }

            
            $consulta = $pdo->prepare(
                "SELECT id FROM batalha WHERE perfil_id = :perfil AND resultado = 'em_andamento' LIMIT 1"
            );
            $consulta->execute([':perfil' => $perfilId]);
            if ($consulta->fetch()) {
                throw new RuntimeException('Finalize sua batalha antes de evoluir um Pokemon.');
            }

            
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


$consulta = $pdo->prepare(
    "SELECT COALESCE(inv.quantidade, 0)
     FROM item i
     LEFT JOIN inventario inv ON inv.item_id = i.id AND inv.perfil_id = :perfil
     WHERE i.nome = 'Item de Evolucao' AND i.tipo_efeito = 'evolucao'
     LIMIT 1"
);
$consulta->execute([':perfil' => $perfilId]);
$quantidadeItem = (int) ($consulta->fetchColumn() ?: 0);


$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM batalha WHERE perfil_id = :perfil AND resultado = 'em_andamento'"
);
$consulta->execute([':perfil' => $perfilId]);
$emBatalha = (int) $consulta->fetchColumn() > 0;


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
<?php require_once __DIR__ . '/../includes/layout.php'; mbAbrirPagina('Evolução', 'Uma transformação por Pokémon. Use o Item de Evolução fora das batalhas e fortaleça sua equipe em 30%.', $perfil, 'evolucao.php'); ?>

<div class="mb-ui-stats" style="justify-content:center;margin-bottom:22px"><span class="mb-counter">✦ Itens de Evolução: <?= (int) $quantidadeItem ?></span><a class="mb-button mb-button-light" href="loja.php">Comprar item</a></div>
<?php if ($erro !== ''): ?><div class="mb-ui-alert is-error" role="alert"><?= escaparEvolucao($erro) ?></div><?php endif; ?>
<?php if ($sucesso !== ''): ?><div class="mb-ui-alert is-success" role="status"><?= escaparEvolucao($sucesso) ?></div><?php endif; ?>
<?php if ($emBatalha): ?><div class="mb-ui-alert">Você possui uma batalha em andamento. Termine o combate antes de evoluir.</div>
<?php elseif ($quantidadeItem < 1): ?><div class="mb-ui-alert">Você precisa de um Item de Evolução. Ele está disponível na loja.</div><?php endif; ?>
<div class="mb-ui-grid">
<?php foreach ($pokemons as $pokemon): ?>
 <?php $jaEvoluiu = (int) $pokemon['evoluiu'] === 1; $podeEvoluir = !$jaEvoluiu && $pokemon['evolui_para_id'] !== null; $podeConfirmar = $podeEvoluir && !$emBatalha && $quantidadeItem > 0; ?>
 <article class="mb-paper mb-ui-card">
  <div class="mb-ui-art"><img src="<?= mbSeguro(mbImagemPokemon((string) $pokemon['nome'])) ?>" alt="Ilustração de <?= escaparEvolucao($pokemon['nome']) ?>" onerror="this.src='../assets/imagens/pokemon-indisponivel.svg';this.onerror=null"></div>
  <div class="mb-ui-pokemon-name"><h2><?= escaparEvolucao($pokemon['nome']) ?></h2><span class="mb-ui-type mb-ui-type-<?= escaparEvolucao($pokemon['tipo']) ?>"><?= escaparEvolucao($pokemon['tipo']) ?></span></div>
  <p class="mb-ui-muted">Nível <?= (int) $pokemon['nivel'] ?> · <?= $jaEvoluiu ? 'Já evoluiu' : 'Forma inicial' ?></p>
  <div class="mb-ui-stat-grid"><div class="mb-ui-stat-box"><span>HP</span><strong><?= (int) $pokemon['hp_atual'] ?> / <?= (int) $pokemon['hp_maximo'] ?></strong></div><div class="mb-ui-stat-box"><span>Ataque</span><strong><?= (int) $pokemon['ataque'] ?></strong></div><div class="mb-ui-stat-box"><span>Defesa</span><strong><?= (int) $pokemon['defesa'] ?></strong></div><div class="mb-ui-stat-box"><span>Velocidade</span><strong><?= (int) $pokemon['velocidade'] ?></strong></div></div>
  <?php if ($podeEvoluir): ?>
   <div class="mb-ui-evolve-future">
    <strong>✦ Próxima forma: <?= escaparEvolucao($pokemon['nome_evolucao']) ?></strong>
    <p class="mb-ui-muted">HP <?= (int) $pokemon['hp_maximo'] ?> → <?= aumentarEvolucao((int) $pokemon['hp_maximo']) ?> · Ataque <?= (int) $pokemon['ataque'] ?> → <?= aumentarEvolucao((int) $pokemon['ataque']) ?></p>
    <p class="mb-ui-muted">Defesa <?= (int) $pokemon['defesa'] ?> → <?= aumentarEvolucao((int) $pokemon['defesa']) ?> · Velocidade <?= (int) $pokemon['velocidade'] ?> → <?= aumentarEvolucao((int) $pokemon['velocidade']) ?></p>
   </div>
   <form method="post" class="mb-ui-form">
    <input type="hidden" name="token" value="<?= escaparEvolucao($_SESSION['token_evolucao']) ?>">
    <input type="hidden" name="pokemon_perfil_id" value="<?= (int) $pokemon['id'] ?>">
    <button class="mb-button mb-button-green" type="submit" <?= !$podeConfirmar ? 'disabled' : '' ?> onclick="return confirm('Evoluir este Pokémon consumindo um Item de Evolução?');">Evoluir Pokémon ✦</button>
   </form>
  <?php else: ?><p class="mb-ui-note"><?= $jaEvoluiu ? 'Este Pokémon já evoluiu e não pode evoluir novamente.' : 'Não há uma evolução disponível para esta espécie.' ?></p><?php endif; ?>
 </article>
<?php endforeach; ?>
</div>
<?php if (!$pokemons): ?><div class="mb-ui-alert">Sua equipe ainda está vazia. <a href="equipe.php">Selecione três Pokémon</a> primeiro.</div><?php endif; ?>
<?php mbEncerrarPagina(); ?>
