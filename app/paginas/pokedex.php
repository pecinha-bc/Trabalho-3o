<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';

exigirLogin();

if (!isset($_SESSION['perfil_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$perfilId = (int) $_SESSION['perfil_id'];
$usuarioId = (int) $_SESSION['usuario_id'];

$jogadorRepositorio = new JogadorRepositorio();
$perfil = $jogadorRepositorio->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$rotulosTipo = [
    'fogo' => 'Fogo',
    'agua' => 'Agua',
    'planta' => 'Planta',
    'eletrico' => 'Eletrico',
    'normal' => 'Normal'
];

try {
    $pdo = Conexao::conectar();

    // carrega todas as especies e suas relacoes de evolucao
    $sql = "
        SELECT
            p.id, p.nome, p.tipo, p.hp_base,
            p.ataque, p.defesa, p.velocidade,
            e.nome AS evolucao_nome,
            origem.nome AS origem_nome
        FROM pokemon p
        LEFT JOIN pokemon e
            ON e.id = p.evolui_para_id
        LEFT JOIN pokemon origem
            ON origem.evolui_para_id = p.id
        ORDER BY
            FIELD(p.tipo, 'fogo', 'agua', 'planta', 'eletrico'),
            CASE WHEN p.evolui_para_id IS NULL THEN 1 ELSE 0 END,
            p.nome
    ";

    $pokemons = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // carrega os ataques de todas as especies em uma unica consulta
    $sqlAtaques = "
        SELECT
            pa.pokemon_id,
            a.nome, a.tipo, a.poder_base,
            a.status_causado, a.chance_status
        FROM pokemon_ataque pa
        INNER JOIN ataque a ON a.id = pa.ataque_id
        ORDER BY pa.pokemon_id, pa.posicao
    ";

    $ataquesPorPokemon = [];

    foreach ($pdo->query($sqlAtaques)->fetchAll(PDO::FETCH_ASSOC) as $ataque) {
        $ataquesPorPokemon[(int) $ataque['pokemon_id']][] = $ataque;
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Nao foi possivel carregar a Pokedex. Verifique a conexao com o banco.');
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokedex - MonstroBolso</title>
    <style>
        :root {
            color-scheme: light;
            --fundo: #eef2f7;
            --texto: #192334;
            --borda: #d9e2ed;
            --azul: #246bd6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: var(--fundo);
            color: var(--texto);
            font-family: Arial, sans-serif;
        }

        main { max-width: 1180px; margin: auto; }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        h1 { margin: 0 0 6px; font-size: 30px; }
        h2 { margin: 0 0 14px; font-size: 20px; }
        p { line-height: 1.5; }
        .descricao { color: #536174; margin: 0; }

        a.voltar {
            display: inline-block;
            padding: 11px 16px;
            background: #fff;
            border: 1px solid var(--borda);
            border-radius: 8px;
            color: var(--azul);
            text-decoration: none;
            font-weight: bold;
        }

        .filtros {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--borda);
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 14px;
        }

        label {
            display: block;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 7px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #aab8ca;
            border-radius: 7px;
            background: #fff;
            font-size: 15px;
        }

        .contador { color: #536174; margin: 0 0 18px; }

        .grade {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(265px, 1fr));
            gap: 18px;
        }

        .card-pokemon {
            background: #fff;
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 19px;
            box-shadow: 0 3px 12px rgba(16, 35, 55, .04);
        }

        .cabecalho-card {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
        }

        .nome { margin: 0; font-size: 21px; }

        .numero { font-size: 13px; color: #6e7a89; }

        .tipo {
            display: inline-block;
            font-size: 12px;
            font-weight: bold;
            border-radius: 20px;
            padding: 5px 10px;
            margin: 10px 0 15px;
            background: #e9eef6;
        }

        .tipo-fogo { background: #ffe1d7; color: #a13318; }
        .tipo-agua { background: #dceeff; color: #125aa1; }
        .tipo-planta { background: #dcf5df; color: #246b36; }
        .tipo-eletrico { background: #fff0b4; color: #775300; }

        .atributos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 16px;
        }

        .atributo {
            background: #f3f6fa;
            padding: 10px;
            border-radius: 7px;
        }

        .atributo span { color: #536174; font-size: 12px; }
        .atributo strong { display: block; font-size: 19px; margin-top: 4px; }

        .evolucao {
            background: #f6f8fa;
            border-radius: 7px;
            padding: 10px;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .ataques-titulo { font-size: 15px; margin-bottom: 9px; }
        .ataque { border-top: 1px solid #edf0f4; padding: 9px 0; }
        .ataque:last-child { padding-bottom: 0; }
        .ataque strong { font-size: 14px; }
        .ataque small { display: block; color: #596779; margin-top: 4px; line-height: 1.4; }
        .vazio { display: none; padding: 20px; text-align: center; }

        @media (max-width: 620px) {
            body { padding: 14px; }
            .filtros { grid-template-columns: 1fr; }
            h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <div>
            <h1>Pokedex MonstroBolso</h1>
            <p class="descricao">
                Conheca os Pokemon, seus atributos, ataques e evolucoes.
            </p>
            <p class="descricao">
                Treinador: <strong><?= escapar($perfil->getNome()) ?></strong>
            </p>
        </div>
        <a class="voltar" href="inicio.php">Voltar ao menu</a>
    </header>

    <section class="filtros" aria-label="Filtros da Pokedex">
        <div>
            <label for="busca">Buscar Pokemon pelo nome</label>
            <input id="busca" type="search" placeholder="Ex.: Pikachu" autocomplete="off">
        </div>
        <div>
            <label for="filtroTipo">Filtrar por tipo</label>
            <select id="filtroTipo">
                <option value="todos">Todos os tipos</option>
                <option value="fogo">Fogo</option>
                <option value="agua">Agua</option>
                <option value="planta">Planta</option>
                <option value="eletrico">Eletrico</option>
            </select>
        </div>
    </section>

    <p class="contador" id="contador" aria-live="polite">
        <?= count($pokemons) ?> Pokemon encontrados
    </p>

    <div class="grade" id="grade">
        <?php foreach ($pokemons as $pokemon): ?>
            <?php
                $tipo = $pokemon['tipo'];
                $ataques = $ataquesPorPokemon[(int) $pokemon['id']] ?? [];
            ?>
            <article
                class="card-pokemon"
                data-nome="<?= escapar(strtolower($pokemon['nome'])) ?>"
                data-tipo="<?= escapar($tipo) ?>"
            >
                <div class="cabecalho-card">
                    <h2 class="nome"><?= escapar($pokemon['nome']) ?></h2>
                    <span class="numero">#<?= (int) $pokemon['id'] ?></span>
                </div>
                <span class="tipo tipo-<?= escapar($tipo) ?>">
                    <?= escapar($rotulosTipo[$tipo] ?? ucfirst($tipo)) ?>
                </span>

                <div class="atributos">
                    <div class="atributo">
                        <span>HP base</span>
                        <strong><?= (int) $pokemon['hp_base'] ?></strong>
                    </div>
                    <div class="atributo">
                        <span>Ataque</span>
                        <strong><?= (int) $pokemon['ataque'] ?></strong>
                    </div>
                    <div class="atributo">
                        <span>Defesa</span>
                        <strong><?= (int) $pokemon['defesa'] ?></strong>
                    </div>
                    <div class="atributo">
                        <span>Velocidade</span>
                        <strong><?= (int) $pokemon['velocidade'] ?></strong>
                    </div>
                </div>

                <div class="evolucao">
                    <?php if ($pokemon['evolucao_nome'] !== null): ?>
                        <strong>Evolui para:</strong>
                        <?= escapar($pokemon['evolucao_nome']) ?>
                    <?php elseif ($pokemon['origem_nome'] !== null): ?>
                        <strong>Evolucao de:</strong>
                        <?= escapar($pokemon['origem_nome']) ?>
                    <?php else: ?>
                        Sem evolucao cadastrada.
                    <?php endif; ?>
                </div>

                <h3 class="ataques-titulo">Ataques</h3>
                <?php if (!$ataques): ?>
                    <p class="descricao">Nenhum ataque cadastrado.</p>
                <?php endif; ?>

                <?php foreach ($ataques as $ataque): ?>
                    <div class="ataque">
                        <strong><?= escapar($ataque['nome']) ?></strong>
                        <small>
                            Tipo: <?= escapar($rotulosTipo[$ataque['tipo']] ?? $ataque['tipo']) ?>
                            | Poder: <?= (int) $ataque['poder_base'] ?>
                        </small>
                        <?php if ($ataque['status_causado'] !== 'nenhum'): ?>
                            <small>
                                Efeito: <?= escapar($ataque['status_causado']) ?>
                                (<?= (int) $ataque['chance_status'] ?>% de chance)
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="vazio" id="vazio">Nenhum Pokemon encontrado com esses filtros.</p>
</main>

<script>
const busca = document.getElementById('busca');
const filtroTipo = document.getElementById('filtroTipo');
const cards = document.querySelectorAll('.card-pokemon');
const contador = document.getElementById('contador');
const vazio = document.getElementById('vazio');

function atualizarPokedex() {
    const nome = busca.value.trim().toLocaleLowerCase('pt-BR');
    const tipo = filtroTipo.value;
    let quantidade = 0;

    cards.forEach(function(card) {
        const correspondeNome = card.dataset.nome.includes(nome);
        const correspondeTipo = tipo === 'todos' || card.dataset.tipo === tipo;
        const mostrar = correspondeNome && correspondeTipo;

        card.hidden = !mostrar;
        // garante que o CSS do cartao respeite a filtragem
        card.style.display = mostrar ? '' : 'none';

        if (mostrar) quantidade++;
    });

    contador.textContent = quantidade + ' Pokemon encontrados';
    vazio.style.display = quantidade === 0 ? 'block' : 'none';
}

busca.addEventListener('input', atualizarPokedex);
filtroTipo.addEventListener('change', atualizarPokedex);
</script>
</body>
</html>
