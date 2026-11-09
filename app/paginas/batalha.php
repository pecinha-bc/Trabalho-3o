
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

$pdo = Conexao::conectar();

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

if (!isset($_SESSION['token_batalhas'])) {
    $_SESSION['token_batalhas'] = bin2hex(random_bytes(32));
}

$erro = '';

$limites = [
    'facil' => 3,
    'medio' => 5,
    'dificil' => 7
];

// verifica a quantidade de pokemons do jogador
$consulta = $pdo->prepare(
    "SELECT COUNT(*) FROM pokemon_perfil
     WHERE perfil_id = :perfil_id"
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

// consulta as equipes dos treinadores
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

// procura uma batalha que ainda nao terminou
$consulta = $pdo->prepare(
    "SELECT b.id, b.npc_id, n.nome AS nome_npc
     FROM batalha b
     INNER JOIN npc n ON n.id = b.npc_id
     WHERE b.perfil_id = :perfil_id
       AND b.resultado = 'em_andamento'
     ORDER BY b.id DESC
     LIMIT 1"
);

$consulta->execute([':perfil_id' => $perfilId]);
$batalhaAtiva = $consulta->fetch();

// prepara a sessao para retomar o combate
if ($batalhaAtiva) {
    $_SESSION['npc_selecionado_id'] =
        (int) $batalhaAtiva['npc_id'];

    $_SESSION['npc_selecionado_perfil_id'] = $perfilId;
}

// recebe a escolha do treinador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['token_batalhas'], $token)
    ) {
        $erro = 'Formulario invalido.';
    } elseif ($batalhaAtiva) {
        $erro = 'Voce ja possui uma batalha em andamento.';
    } elseif ($quantidadeEquipe !== 3) {
        $erro = 'Voce precisa ter tres Pokemon na equipe.';
    } else {

        $npcId = filter_var(
            $_POST['npc_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
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

            $_SESSION['token_batalhas'] = bin2hex(
                random_bytes(32)
            );

            // abre automaticamente a preparacao do combate
            header('Location: combate.php');
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Batalhas - MonstroBolso</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f8;
            margin: 0;
            padding: 20px;
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

        button, .botao {
            display: inline-block;
            box-sizing: border-box;
            width: 100%;
            padding: 12px 18px;
            margin-top: 14px;
            background: #246bd6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 15px;
        }

        button:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .mensagem {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .erro {
            background: #f8d7da;
        }

        .voltar {
            text-align: center;
            margin-top: 30px;
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

    <?php if ($erro !== ''): ?>
        <div class="mensagem erro">
            <?= escapar($erro) ?>
        </div>
    <?php endif; ?>

    <?php if ($batalhaAtiva): ?>

        <div class="mensagem">
            <strong>Voce possui uma batalha em andamento.</strong>

            <p>
                Adversario:
                <?= escapar($batalhaAtiva['nome_npc']) ?>
            </p>

            <a class="botao" href="combate.php">
                Retomar batalha
            </a>
        </div>

    <?php elseif ($quantidadeEquipe !== 3): ?>

        <div class="mensagem">
            Voce precisa escolher tres Pokemon antes de batalhar.

            <a class="botao" href="equipe.php">
                Montar equipe
            </a>
        </div>

    <?php else: ?>

        <p class="descricao">
            Escolha um treinador para enfrentar.
            Confira sua equipe e a recompensa antes de comecar.
        </p>

        <div class="grade">

            <?php foreach ($treinadores as $npc): ?>

                <?php
                    $dificuldade = $npc['dificuldade'];
                    $limite = $limites[$dificuldade] ?? 3;
                    $pokemons = $equipesNpc[$npc['id']] ?? [];
                ?>

                <div class="treinador <?= escapar($dificuldade) ?>">

                    <h3><?= escapar($npc['nome']) ?></h3>

                    <p>
                        <strong>Dificuldade:</strong>
                        <?= escapar(ucfirst($dificuldade)) ?>
                    </p>

                    <p>
                        <strong>Recompensa:</strong>
                        <?= (int) $npc['recompensa'] ?>
                        Pokedolares
                    </p>

                    <p>
                        <strong>Limite de itens:</strong>
                        <?= $limite ?> para cada participante
                    </p>

                    <h4>Equipe adversaria</h4>

                    <ul>
                        <?php foreach ($pokemons as $pokemon): ?>
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

                        <button type="submit">
                            Enfrentar treinador
                        </button>

                    </form>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <div class="voltar">
        <a href="inicio.php">Voltar ao menu principal</a>
    </div>

</main>
</body>
</html>
