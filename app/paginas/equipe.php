<?php

// pagina visual da equipe: mantem a validacao e a persistencia originais
require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';
require_once __DIR__ . '/../repositorios/pokemonrepositorio.php';
require_once __DIR__ . '/../repositorios/pokemonPerfilRepositorio.php';

exigirLogin();

if (empty($_SESSION['perfil_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$perfilId = (int) $_SESSION['perfil_id'];
$usuarioId = (int) $_SESSION['usuario_id'];

$jogadorRepositorio = new JogadorRepositorio();
$pokemonRepositorio = new PokemonRepositorio();
$equipeRepositorio = new PokemonPerfilRepositorio();
$perfil = $jogadorRepositorio->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

if (empty($_SESSION['token_equipe'])) {
    $_SESSION['token_equipe'] = bin2hex(random_bytes(32));
}

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// numero nacional da especie para exibir a ilustracao
// o pokemon continua sendo identificado pelo id real do banco
$numeroPokedex = [
    'Charmander' => 4, 'Charmeleon' => 5,
    'Growlithe' => 58, 'Arcanine' => 59,
    'Vulpix' => 37, 'Ninetales' => 38,
    'Squirtle' => 7, 'Wartortle' => 8,
    'Psyduck' => 54, 'Golduck' => 55,
    'Poliwag' => 60, 'Poliwhirl' => 61,
    'Bulbasaur' => 1, 'Ivysaur' => 2,
    'Oddish' => 43, 'Gloom' => 44,
    'Bellsprout' => 69, 'Weepinbell' => 70,
    'Pikachu' => 25, 'Raichu' => 26,
    'Magnemite' => 81, 'Magneton' => 82,
    'Electabuzz' => 125, 'Electivire' => 466,
];

function obterImagemPokemon(string $nome, array $mapa): string
{
    $numero = $mapa[$nome] ?? null;
    if (!$numero) {
        return '../assets/imagens/pokemon-indisponivel.svg';
    }
    return 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/' . $numero . '.png';
}

$erro = '';
$sucesso = '';
$selecionadosAntes = [];
$cartoes = [];

try {
    $equipe = $equipeRepositorio->listarPorPerfil($perfilId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['token'] ?? '';
        $enviados = $_POST['pokemons'] ?? [];

        if (!is_string($token) || !hash_equals($_SESSION['token_equipe'], $token)) {
            $erro = 'Formulario invalido. Atualize a pagina e tente novamente.';
        } elseif (count($equipe) > 0) {
            $erro = 'Este perfil ja possui uma equipe.';
        } elseif (!is_array($enviados) || count($enviados) !== 3) {
            $erro = 'Escolha exatamente tres Pokemon diferentes.';
        } else {
            $ids = [];
            foreach ($enviados as $valor) {
                if (!is_string($valor) || !ctype_digit($valor) || (int) $valor <= 0) {
                    $erro = 'Selecao invalida.';
                    break;
                }
                $ids[] = (int) $valor;
            }

            if ($erro === '' && count(array_unique($ids)) !== 3) {
                $erro = 'Escolha tres Pokemon diferentes.';
            }

            if ($erro === '') {
                try {
                    // a transacao, os IDs permitidos e o INSERT ficam no repositorio existente
                    $equipeRepositorio->criarEquipeInicial($perfilId, $ids);
                    $_SESSION['token_equipe'] = bin2hex(random_bytes(32));
                    header('Location: equipe.php?criada=1');
                    exit;
                } catch (PDOException $e) {
                    $erro = 'Nao foi possivel salvar a equipe. Tente novamente.';
                } catch (InvalidArgumentException | RuntimeException $e) {
                    $erro = $e->getMessage();
                }
            }
            $selecionadosAntes = $ids;
        }
    }

    if (isset($_GET['criada']) && $_GET['criada'] === '1') {
        $sucesso = 'Equipe registrada com sucesso!';
    }

    $equipe = $equipeRepositorio->listarPorPerfil($perfilId);
    $visualizacao = count($equipe) > 0;

    // consulta os tres ataques de cada especie para exibir no painel
    $pdo = Conexao::conectar();
    $consultaAtaques = $pdo->query(
        'SELECT pa.pokemon_id, a.nome, a.tipo, a.poder_base '
        . 'FROM pokemon_ataque pa '
        . 'INNER JOIN ataque a ON a.id = pa.ataque_id '
        . 'ORDER BY pa.pokemon_id, pa.posicao'
    );
    $ataques = [];
    foreach ($consultaAtaques->fetchAll() as $dados) {
        $ataques[(int) $dados['pokemon_id']][] = [
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'],
            'poder' => (int) $dados['poder_base'],
        ];
    }

    if ($visualizacao) {
        $especies = [];
        foreach ($pokemonRepositorio->listarTodos() as $especie) {
            $especies[$especie->getId()] = $especie;
        }
        foreach ($equipe as $pokemonPerfil) {
            $especie = $especies[$pokemonPerfil->getPokemonId()] ?? null;
            if (!$especie) {
                continue;
            }
            $cartoes[] = [
                'id' => $especie->getId(),
                'nome' => $especie->getNome(),
                'tipo' => $especie->getTipo(),
                'imagem' => obterImagemPokemon($especie->getNome(), $numeroPokedex),
                'hpAtual' => $pokemonPerfil->getHpAtual(),
                'hp' => $pokemonPerfil->getHpMaximo(),
                'ataque' => $pokemonPerfil->getAtaque(),
                'defesa' => $pokemonPerfil->getDefesa(),
                'velocidade' => $pokemonPerfil->getVelocidade(),
                'nivel' => $pokemonPerfil->getNivel(),
                'status' => $pokemonPerfil->getStatus(),
                'ataques' => $ataques[$especie->getId()] ?? [],
            ];
        }
    } else {
        foreach ($pokemonRepositorio->listarIniciais() as $especie) {
            $cartoes[] = [
                'id' => $especie->getId(),
                'nome' => $especie->getNome(),
                'tipo' => $especie->getTipo(),
                'imagem' => obterImagemPokemon($especie->getNome(), $numeroPokedex),
                'hpAtual' => $especie->getHpMax(),
                'hp' => $especie->getHpMax(),
                'ataque' => $especie->getAtaque(),
                'defesa' => $especie->getDefesa(),
                'velocidade' => $especie->getVelocidade(),
                'nivel' => 1,
                'status' => 'nenhum',
                'ataques' => $ataques[$especie->getId()] ?? [],
            ];
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    exit('Erro ao consultar os dados da equipe.');
}

$dadosJson = json_encode(
    $cartoes,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
$selecionadosJson = json_encode($selecionadosAntes);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $visualizacao ? 'Minha Equipe' : 'Escolha de Pokémon' ?> — MonstroBolso</title>
    <link rel="stylesheet" href="../assets/css/monstrobolso.css?v=1">
    <script defer src="../assets/js/equipe.js?v=1"></script>
</head>
<body class="mb-body">
<div class="mb-shell">
    <header class="mb-header">
        <a class="mb-brand" href="inicio.php" aria-label="MonstroBolso - menu principal">
            <span class="mb-brand-mark" aria-hidden="true">●</span>
            <span class="mb-brand-text">MonstroBolso</span>
        </a>
        <div class="mb-top-actions">
            <span class="mb-pill">Treinador: <?= escapar($perfil->getNome()) ?></span>
            <span class="mb-pill">✦ <?= (int) $perfil->getSaldo() ?> Pokédólares</span>
        </div>
    </header>

    <div class="mb-title-wrap">
        <h1 class="mb-heading"><?= $visualizacao ? 'Minha Equipe' : 'Montagem de Time' ?></h1>
        <p class="mb-description"><?= $visualizacao
            ? 'Passe para o lado e conheça cada Pokémon da sua equipe.'
            : 'Escolha três Pokémon para enfrentar os treinadores adversários.' ?></p>
    </div>

    <?php if ($erro !== ''): ?>
        <p class="mb-message mb-message-error" role="alert"><?= escapar($erro) ?></p>
    <?php endif; ?>
    <?php if ($sucesso !== ''): ?>
        <p class="mb-message mb-message-success" role="status"><?= escapar($sucesso) ?></p>
    <?php endif; ?>

    <noscript><p class="mb-message mb-message-error">Ative o JavaScript para navegar e escolher seus Pokémon.</p></noscript>
    <main id="mb-equipe" data-mode="<?= $visualizacao ? 'visualizacao' : 'selecao' ?>">
        <div class="mb-content">
            <aside class="mb-paper mb-team-panel" aria-label="Pokémon da equipe">
                <div class="mb-brush-heading">◉ Seu Time <span id="mb-team-count">(<?= $visualizacao ? count($cartoes) : 0 ?>/3)</span></div>
                <div id="mb-team-list" class="mb-team-list" aria-live="polite"></div>
                <?php if (!$visualizacao): ?>
                    <p class="mb-side-hint">Clique em “Selecionar” para adicionar o Pokémon destacado.</p>
                <?php endif; ?>
            </aside>

            <section class="mb-stage" aria-label="Pokémon em destaque">
                <button class="mb-stage-nav mb-stage-prev" id="mb-prev" type="button" aria-label="Pokémon anterior">❮</button>
                <div class="mb-creature" id="mb-creature">
                    <img id="mb-creature-image" src="../assets/imagens/pokemon-indisponivel.svg" alt="Pokémon destacado" fetchpriority="high">
                    <div class="mb-creature-name" id="mb-creature-name">Carregando...</div>
                </div>
                <button class="mb-stage-nav mb-stage-next" id="mb-next" type="button" aria-label="Próximo Pokémon">❯</button>
            </section>

            <aside class="mb-paper mb-details" aria-label="Atributos do Pokémon">
                <div class="mb-brush-heading">Informações</div>
                <div class="mb-detail-card" id="mb-details-content" aria-live="polite"></div>
            </aside>
        </div>

        <div class="mb-carousel-area" aria-label="Navegação entre Pokémon">
            <button class="mb-mini-nav" id="mb-thumbs-prev" type="button" aria-label="Rolar para a esquerda">❮</button>
            <div class="mb-thumbs" id="mb-thumbs"></div>
            <button class="mb-mini-nav" id="mb-thumbs-next" type="button" aria-label="Rolar para a direita">❯</button>
        </div>

        <?php if (!$visualizacao): ?>
            <form method="POST" id="mb-team-form" autocomplete="off">
                <input type="hidden" name="token" value="<?= escapar($_SESSION['token_equipe']) ?>">
                <div id="mb-hidden-ids"></div>
                <div class="mb-footer-actions">
                    <a href="inicio.php" class="mb-button mb-button-light">← Voltar</a>
                    <span class="mb-counter" id="mb-counter">0 de 3 escolhidos</span>
                    <button id="mb-choose" class="mb-button" type="button">Selecionar Pokémon</button>
                    <button id="mb-confirm" class="mb-button mb-button-green" type="submit" disabled>Confirmar Time ✓</button>
                </div>
            </form>
        <?php else: ?>
            <div class="mb-footer-actions">
                <a href="inicio.php" class="mb-button mb-button-light">← Menu principal</a>
                <a href="batalhas.php" class="mb-button mb-button-green">Ir para Batalhas ⚔</a>
            </div>
        <?php endif; ?>
        <p class="mb-hint">Use as setas para navegar. Os atributos vêm do banco de dados.</p>
    </main>
</div>

<script id="mb-pokemon-data" type="application/json"><?= $dadosJson ?: '[]' ?></script>
<script id="mb-initial-selected" type="application/json"><?= $selecionadosJson ?: '[]' ?></script>
</body>
</html>
