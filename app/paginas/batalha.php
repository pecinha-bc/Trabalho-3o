
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

$repositorio = new JogadorRepositorio();
$perfil = $repositorio->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

$pdo = Conexao::conectar();

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

// verifica quantos pokemons o jogador possui
$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM pokemon_perfil WHERE perfil_id = :perfil_id"
);

$consulta->execute([':perfil_id' => $perfilId]);

$quantidadeEquipe = (int) $consulta->fetchColumn();

// consulta os treinadores
$consulta = $pdo->query(
    "SELECT id, nome, dificuldade, recompensa
     FROM npc
     ORDER BY FIELD(dificuldade, 'facil', 'medio', 'dificil')"
);

$treinadores = $consulta->fetchAll();

// consulta os pokemons dos treinadores
$consulta = $pdo->query(
    "SELECT np.npc_id, p.nome, p.tipo
     FROM npc_pokemon np
     INNER JOIN pokemon p ON p.id = np.pokemon_id
     ORDER BY np.npc_id, np.posicao"
);

$equipesNpc = [];

foreach ($consulta->fetchAll() as $pokemon) {
    $equipesNpc[$pokemon['npc_id']][] = $pokemon;
}

// prepara a protecao do formulario
if (!isset($_SESSION['token_batalhas'])) {
    $_SESSION['token_batalhas'] = bin2hex(random_bytes(32));
}

$mensagem = '';
$erro = '';

// registra o treinador escolhido para a proxima etapa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['token_batalhas'], $token)
    ) {
        $erro = 'Formulario invalido.';
    } elseif ($quantidadeEquipe !== 3) {
        $erro = 'Voce precisa ter tres pokemons na equipe.';
    } else {

        $npcId = filter_var(
            $_POST['npc_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $npcEscolhido = null;

        foreach ($treinadores as $treinador) {
            if ((int) $treinador['id'] === $npcId) {
                $npcEscolhido = $treinador;
                break;
            }
        }

        if (!$npcEscolhido) {
            $erro = 'Treinador invalido.';
        } else {
            $_SESSION['npc_selecionado_id'] = $npcId;
            $_SESSION['npc_selecionado_perfil_id'] = $perfilId;

            $mensagem = 'Treinador selecionado: '
                . $npcEscolhido['nome'];
        }
    }
}

$limites = [
    'facil' => 3,
    'medio' => 5,
    'dificil' => 7
];

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Batalhas - MonstroBolso</title>

    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f2f4f8;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 1050px;
            margin: auto;
        }

        h1, h2 {
            text-align: center;
        }

        .descricao {
            text-align: center;
            color: #555;
        }

        .grade {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .treinador {
            background: white;
            border: 2px solid #ddd;
            border-radius: 12px;
            padding: 22px;
        }

        .treinador h3 {
            margin-top: 0;
        }

        .facil {
            border-color: #34a853;
        }

        .medio {
            border-color: #f4b400;
        }

        .dificil {
            border-color: #ea4335;
        }

        .atributo {
            margin: 12px 0;
        }

        ul {
            padding-left: 20px;
        }

        li {
            margin-bottom: 8px;
        }

        button {
            background: #246bd6;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 6px;
            width: 100%;
            margin-top: 14px;
            cursor: pointer;
        }

        button:hover {
            background: #1751a5;
        }

        button:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .mensagem {
            background: #d4edda;
            padding: 15px;
            border-radius: 7px;
            margin-top: 20px;
        }

        .erro {
            background: #f8d7da;
        }

        .voltar {
            text-align: center;
            margin-top: 30px;
        }

        a {
            color: #246bd6;
        }
    </style>
</head>

<body>

<main>

    <h1>MonstroBolso</h1>
    <h2>Escolha seu adversario</h2>

    <p class="descricao">
        Treinador:
        <strong><?= escapar($perfil->getNome()) ?></strong>
    </p>

    <p class="descricao">
        Escolha um dos tres treinadores para enfrentar.
        Cada treinador possui uma dificuldade e uma recompensa.
    </p>

    <?php if ($erro !== ''): ?>
        <div class="mensagem erro">
            <?= escapar($erro) ?>
        </div>
    <?php endif; ?>

    <?php if ($mensagem !== ''): ?>
        <div class="mensagem">
            <?= escapar($mensagem) ?>
            <p>
                Seu adversario foi registrado.
                A proxima etapa sera iniciar o combate.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($quantidadeEquipe !== 3): ?>

        <div class="mensagem erro">
            Voce precisa escolher tres Pokemon antes de batalhar.
            <a href="equipe.php">Montar equipe</a>
        </div>

    <?php endif; ?>

    <div class="grade">

        <?php foreach ($treinadores as $npc): ?>

            <?php
                $dificuldade = $npc['dificuldade'];
                $limite = $limites[$dificuldade];
                $pokemonsNpc = $equipesNpc[$npc['id']] ?? [];
            ?>

            <div class="treinador <?= escapar($dificuldade) ?>">

                <h3><?= escapar($npc['nome']) ?></h3>

                <div class="atributo">
                    <strong>Dificuldade:</strong>
                    <?= escapar(ucfirst($dificuldade)) ?>
                </div>

                <div class="atributo">
                    <strong>Recompensa:</strong>
                    <?= (int) $npc['recompensa'] ?>
                    Pokedolares
                </div>

                <div class="atributo">
                    <strong>Limite de itens:</strong>
                    <?= $limite ?> para cada participante
                </div>

                <h4>Equipe adversaria</h4>

                <ul>
                    <?php foreach ($pokemonsNpc as $pokemon): ?>
                        <li>
                            <?= escapar($pokemon['nome']) ?>
                            (<?= escapar($pokemon['tipo']) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>

                <form method="POST">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= escapar($_SESSION['token_batalhas']) ?>"
                    >

                    <input
                        type="hidden"
                        name="npc_id"
                        value="<?= (int) $npc['id'] ?>"
                    >

                    <button
                        type="submit"
                        <?= $quantidadeEquipe !== 3 ? 'disabled' : '' ?>
                    >
                        Selecionar treinador
                    </button>

                </form>

            </div>

        <?php endforeach; ?>

    </div>

    <div class="voltar">
        <a href="inicio.php">Voltar ao menu principal</a>
    </div>

</main>

</body>
</html>
