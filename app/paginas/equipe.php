
<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';
require_once __DIR__ . '/../repositorios/pokemonrepositorio.php';
require_once __DIR__ . '/../repositorios/pokemonPerfilRepositorio.php';

exigirLogin();

if (!isset($_SESSION['perfil_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$jogadorRepositorio = new JogadorRepositorio();
$pokemonRepositorio = new PokemonRepositorio();
$equipeRepositorio = new PokemonPerfilRepositorio();

$perfilId = (int) $_SESSION['perfil_id'];
$usuarioId = (int) $_SESSION['usuario_id'];

$perfil = $jogadorRepositorio->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

if (!isset($_SESSION['token_equipe'])) {
    $_SESSION['token_equipe'] = bin2hex(random_bytes(32));
}

$erro = '';
$sucesso = '';

try {
    $equipe = $equipeRepositorio->listarPorPerfil($perfilId);
    $iniciais = $pokemonRepositorio->listarIniciais();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $token = $_POST['token'] ?? '';

        if (
            !is_string($token) ||
            !hash_equals($_SESSION['token_equipe'], $token)
        ) {
            $erro = 'Formulario invalido. Atualize a pagina.';
        } elseif (count($equipe) > 0) {
            $erro = 'Voce ja possui uma equipe inicial.';
        } else {
            $selecionados = $_POST['pokemons'] ?? [];

            if (!is_array($selecionados)) {
                $erro = 'Selecao invalida.';
            } elseif (
                count($selecionados) !== 3 ||
                count(array_unique($selecionados)) !== 3
            ) {
                $erro = 'Escolha exatamente tres pokemons diferentes.';
            } else {
                $ids = [];

                foreach ($selecionados as $id) {
                    if (
                        !is_string($id) ||
                        !ctype_digit($id) ||
                        (int) $id <= 0
                    ) {
                        $erro = 'Selecao invalida.';
                        break;
                    }

                    $ids[] = (int) $id;
                }

                if ($erro === '') {
                    try {
                        $equipeRepositorio->criarEquipeInicial(
                            $perfilId,
                            $ids
                        );

                        $_SESSION['token_equipe'] = bin2hex(
                            random_bytes(32)
                        );

                        header('Location: equipe.php?criada=1');
                        exit;

                    } catch (InvalidArgumentException $e) {
                        $erro = $e->getMessage();
                    } catch (RuntimeException $e) {
                        $erro = $e->getMessage();
                    } catch (PDOException $e) {
                        $erro = 'Nao foi possivel salvar a equipe.';
                    }
                }
            }
        }
    }

    if (isset($_GET['criada']) && $_GET['criada'] === '1') {
        $sucesso = 'Sua equipe foi criada com sucesso!';
    }

    $equipe = $equipeRepositorio->listarPorPerfil($perfilId);

    // associa os dados das especies aos pokemons do perfil
    $especies = [];

    foreach ($pokemonRepositorio->listarTodos() as $pokemon) {
        $especies[$pokemon->getId()] = $pokemon;
    }

} catch (PDOException $e) {
    http_response_code(500);
    exit('Erro ao consultar os dados da equipe.');
}

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Equipe - MonstroBolso</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        main {
            max-width: 1100px;
            margin: auto;
        }

        h1, h2 {
            text-align: center;
        }

        .grade {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px;
            margin-top: 25px;
        }

        .pokemon {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 18px;
        }

        .pokemon h3 {
            margin-top: 0;
        }

        .atributo {
            margin: 8px 0;
        }

        .selecionar {
            margin-top: 15px;
            font-weight: bold;
        }

        button {
            background: #2878d0;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #195a9d;
        }

        .mensagem {
            padding: 12px;
            background: #fff3cd;
            margin: 15px 0;
            border-radius: 6px;
        }

        .sucesso {
            background: #d4edda;
        }

        .acoes {
            margin-top: 25px;
            text-align: center;
        }

        a {
            color: #195a9d;
        }
    </style>
</head>

<body>
<main>

    <h1>MonstroBolso</h1>
    <h2>Minha Equipe</h2>

    <p>
        Treinador:
        <strong><?= escapar($perfil->getNome()) ?></strong>
    </p>

    <?php if ($erro !== ''): ?>
        <div class="mensagem">
            <?= escapar($erro) ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso !== ''): ?>
        <div class="mensagem sucesso">
            <?= escapar($sucesso) ?>
        </div>
    <?php endif; ?>

    <?php if (count($equipe) === 0): ?>

        <h2>Escolha seus tres Pokemon iniciais</h2>

        <p>
            Escolha exatamente tres Pokemon diferentes.
            Confira os atributos antes de confirmar.
            Essa escolha inicial nao podera ser repetida.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="token"
                value="<?= escapar($_SESSION['token_equipe']) ?>"
            >

            <div class="grade">

                <?php foreach ($iniciais as $pokemon): ?>

                    <div class="pokemon">

                        <h3>
                            <?= escapar($pokemon->getNome()) ?>
                        </h3>

                        <p>
                            Tipo:
                            <?= escapar($pokemon->getTipo()) ?>
                        </p>

                        <div class="atributo">
                            HP: <?= $pokemon->getHpMax() ?>
                        </div>

                        <div class="atributo">
                            Ataque: <?= $pokemon->getAtaque() ?>
                        </div>

                        <div class="atributo">
                            Defesa: <?= $pokemon->getDefesa() ?>
                        </div>

                        <div class="atributo">
                            Velocidade:
                            <?= $pokemon->getVelocidade() ?>
                        </div>

                        <div class="selecionar">
                            <label>
                                <input
                                    type="checkbox"
                                    name="pokemons[]"
                                    value="<?= $pokemon->getId() ?>"
                                    class="escolha"
                                >
                                Selecionar
                            </label>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="acoes">
                <p>
                    Selecionados:
                    <strong id="contador">0</strong> / 3
                </p>

                <button
                    type="submit"
                    id="confirmar"
                    disabled
                >
                    Confirmar equipe
                </button>
            </div>

        </form>

    <?php else: ?>

        <h2>Seus Pokemon</h2>

        <div class="grade">

            <?php foreach ($equipe as $pokemonPerfil): ?>

                <?php
                    $especie = $especies[
                        $pokemonPerfil->getPokemonId()
                    ] ?? null;
                ?>

                <div class="pokemon">

                    <h3>
                        <?= $especie
                            ? escapar($especie->getNome())
                            : 'Pokemon desconhecido'
                        ?>
                    </h3>

                    <p>
                        Tipo:
                        <?= $especie
                            ? escapar($especie->getTipo())
                            : 'Desconhecido'
                        ?>
                    </p>

                    <p>
                        Nivel:
                        <?= $pokemonPerfil->getNivel() ?>
                    </p>

                    <p>
                        HP:
                        <?= $pokemonPerfil->getHpAtual() ?>
                        /
                        <?= $pokemonPerfil->getHpMaximo() ?>
                    </p>

                    <p>
                        Ataque:
                        <?= $pokemonPerfil->getAtaque() ?>
                    </p>

                    <p>
                        Defesa:
                        <?= $pokemonPerfil->getDefesa() ?>
                    </p>

                    <p>
                        Velocidade:
                        <?= $pokemonPerfil->getVelocidade() ?>
                    </p>

                    <p>
                        Experiencia:
                        <?= $pokemonPerfil->getExperiencia() ?>
                    </p>

                    <p>
                        Status:
                        <?= escapar($pokemonPerfil->getStatus()) ?>
                    </p>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <div class="acoes">
        <a href="inicio.php">Voltar ao menu principal</a>
    </div>

</main>

<script>
const escolhas = document.querySelectorAll('.escolha');
const contador = document.getElementById('contador');
const confirmar = document.getElementById('confirmar');

function atualizarSelecao() {
    const selecionados = document.querySelectorAll(
        '.escolha:checked'
    );

    if (!contador || !confirmar) {
        return;
    }

    contador.textContent = selecionados.length;

    confirmar.disabled = selecionados.length !== 3;

    escolhas.forEach(function(campo) {
        campo.disabled = (
            !campo.checked &&
            selecionados.length >= 3
        );
    });
}

escolhas.forEach(function(campo) {
    campo.addEventListener('change', atualizarSelecao);
});

atualizarSelecao();
</script>

</body>
</html>
