
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

$npcId = (int) ($_SESSION['npc_selecionado_id'] ?? 0);
$perfilSelecionado = (int) (
    $_SESSION['npc_selecionado_perfil_id'] ?? 0
);

if ($npcId <= 0 || $perfilSelecionado !== $perfilId) {
    header('Location: batalhas.php');
    exit;
}

$pdo = Conexao::conectar();
$erro = '';

function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

if (!isset($_SESSION['token_combate'])) {
    $_SESSION['token_combate'] = bin2hex(random_bytes(32));
}

// consulta o treinador selecionado
$consulta = $pdo->prepare(
    "SELECT id, nome, dificuldade, recompensa
     FROM npc WHERE id = :id"
);

$consulta->execute([':id' => $npcId]);
$npc = $consulta->fetch();

if (!$npc) {
    header('Location: batalhas.php');
    exit;
}

// consulta a equipe do jogador
$consulta = $pdo->prepare(
    "SELECT pp.id, pp.hp_atual, pp.hp_maximo,
            pp.ataque, pp.defesa, pp.velocidade,
            p.nome, p.tipo
     FROM pokemon_perfil pp
     INNER JOIN pokemon p ON p.id = pp.pokemon_id
     WHERE pp.perfil_id = :perfil_id
     ORDER BY pp.id"
);

$consulta->execute([':perfil_id' => $perfilId]);
$equipeJogador = $consulta->fetchAll();

// consulta a equipe do npc
$consulta = $pdo->prepare(
    "SELECT np.id, p.nome, p.tipo,
            p.hp_base, p.ataque, p.defesa,
            p.velocidade
     FROM npc_pokemon np
     INNER JOIN pokemon p ON p.id = np.pokemon_id
     WHERE np.npc_id = :npc_id
     ORDER BY np.posicao"
);

$consulta->execute([':npc_id' => $npcId]);
$equipeNpc = $consulta->fetchAll();

// inicia uma batalha apenas mediante confirmacao
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['token_combate'], $token)
    ) {
        $erro = 'Formulario invalido.';
    } else {

        try {
            $pdo->beginTransaction();

            // bloqueia o perfil para evitar batalhas simultaneas
            $consulta = $pdo->prepare(
                "SELECT id FROM perfil
                 WHERE id = :id AND usuario_id = :usuario_id
                 FOR UPDATE"
            );

            $consulta->execute([
                ':id' => $perfilId,
                ':usuario_id' => $usuarioId
            ]);

            if (!$consulta->fetch()) {
                throw new RuntimeException('Perfil invalido.');
            }

            // verifica se existe uma batalha em andamento
            $consulta = $pdo->prepare(
                "SELECT id FROM batalha
                 WHERE perfil_id = :perfil_id
                   AND resultado = 'em_andamento'
                 LIMIT 1"
            );

            $consulta->execute([':perfil_id' => $perfilId]);

            if ($consulta->fetch()) {
                throw new RuntimeException(
                    'Voce ja possui uma batalha em andamento.'
                );
            }

            // consulta novamente a equipe do jogador
            $consulta = $pdo->prepare(
                "SELECT id, hp_atual
                 FROM pokemon_perfil
                 WHERE perfil_id = :perfil_id
                 ORDER BY id FOR UPDATE"
            );

            $consulta->execute([':perfil_id' => $perfilId]);
            $pokemonsJogador = $consulta->fetchAll();

            if (count($pokemonsJogador) !== 3) {
                throw new RuntimeException(
                    'Sua equipe deve possuir tres Pokemon.'
                );
            }

            $possuiPokemonVivo = false;

            foreach ($pokemonsJogador as $pokemon) {
                if ((int) $pokemon['hp_atual'] > 0) {
                    $possuiPokemonVivo = true;
                }
            }

            if (!$possuiPokemonVivo) {
                throw new RuntimeException(
                    'Sua equipe nao possui Pokemon em condicoes de lutar.'
                );
            }

            // consulta os tres pokemons do npc
            $consulta = $pdo->prepare(
                "SELECT id FROM npc_pokemon
                 WHERE npc_id = :npc_id
                 ORDER BY posicao"
            );

            $consulta->execute([':npc_id' => $npcId]);
            $pokemonsNpc = $consulta->fetchAll();

            if (count($pokemonsNpc) !== 3) {
                throw new RuntimeException(
                    'O treinador adversario nao possui equipe completa.'
                );
            }

            // cria o registro principal da batalha
            $consulta = $pdo->prepare(
                "INSERT INTO batalha
                    (perfil_id, npc_id, resultado, recompensa)
                 VALUES
                    (:perfil_id, :npc_id, 'em_andamento', 0)"
            );

            $consulta->execute([
                ':perfil_id' => $perfilId,
                ':npc_id' => $npcId
            ]);

            $batalhaId = (int) $pdo->lastInsertId();

            // adiciona os pokemons do jogador
            $consulta = $pdo->prepare(
                "INSERT INTO batalha_pokemon
                    (batalha_id, pokemon_perfil_id)
                 VALUES
                    (:batalha_id, :pokemon_id)"
            );

            foreach ($pokemonsJogador as $pokemon) {
                $consulta->execute([
                    ':batalha_id' => $batalhaId,
                    ':pokemon_id' => $pokemon['id']
                ]);
            }

            // adiciona os pokemons do npc
            $consulta = $pdo->prepare(
                "INSERT INTO batalha_pokemon
                    (batalha_id, npc_pokemon_id)
                 VALUES
                    (:batalha_id, :pokemon_id)"
            );

            foreach ($pokemonsNpc as $pokemon) {
                $consulta->execute([
                    ':batalha_id' => $batalhaId,
                    ':pokemon_id' => $pokemon['id']
                ]);
            }

            // cada npc comeca o combate com hp cheio
            $consulta = $pdo->prepare(
                "UPDATE batalha_pokemon bp
                 INNER JOIN npc_pokemon np
                    ON np.id = bp.npc_pokemon_id
                 INNER JOIN pokemon p
                    ON p.id = np.pokemon_id
                 SET bp.hp_atual = p.hp_base,
                     bp.hp_maximo = p.hp_base,
                     bp.status = 'nenhum'
                 WHERE bp.batalha_id = :batalha_id
                   AND bp.npc_pokemon_id IS NOT NULL"
            );

            $consulta->execute([':batalha_id' => $batalhaId]);

            $pdo->commit();

            $_SESSION['token_combate'] = bin2hex(random_bytes(32));

            header('Location: combate.php?iniciada=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException) {
                $erro = 'Erro ao registrar a batalha no banco.';
            } else {
                $erro = $e->getMessage();
            }
        }
    }
}

// procura a batalha em andamento do jogador
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

$pokemonsBatalha = [];

if ($batalhaAtiva) {

    // consulta os seis pokemons registrados no combate
    $consulta = $pdo->prepare(
        "SELECT bp.hp_atual, bp.hp_maximo, bp.status,
                CASE
                    WHEN bp.pokemon_perfil_id IS NOT NULL
                    THEN 'jogador'
                    ELSE 'npc'
                END AS participante,
                COALESCE(pj.nome, pn.nome) AS nome
         FROM batalha_pokemon bp

         LEFT JOIN pokemon_perfil pp
            ON pp.id = bp.pokemon_perfil_id

         LEFT JOIN pokemon pj
            ON pj.id = pp.pokemon_id

         LEFT JOIN npc_pokemon np
            ON np.id = bp.npc_pokemon_id

         LEFT JOIN pokemon pn
            ON pn.id = np.pokemon_id

         WHERE bp.batalha_id = :batalha_id

         ORDER BY
            CASE
                WHEN bp.pokemon_perfil_id IS NOT NULL
                THEN 0 ELSE 1
            END,
            bp.id"
    );

    $consulta->execute([
        ':batalha_id' => $batalhaAtiva['id']
    ]);

    $pokemonsBatalha = $consulta->fetchAll();
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

    <title>Combate - MonstroBolso</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f3f7;
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

        .equipes {
            display: grid;
            grid-template-columns: repeat(
                auto-fit, minmax(280px, 1fr)
            );
            gap: 20px;
            margin-top: 25px;
        }

        .painel {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        .pokemon {
            padding: 12px;
            margin: 10px 0;
            background: #f6f7fb;
            border-radius: 7px;
        }

        .pokemon strong {
            display: block;
            margin-bottom: 7px;
        }

        .mensagem {
            padding: 15px;
            background: #d4edda;
            border-radius: 8px;
            margin-top: 20px;
        }

        .erro {
            background: #f8d7da;
        }

        .acoes {
            margin-top: 25px;
            text-align: center;
        }

        button {
            padding: 14px 30px;
            background: #246bd6;
            border: none;
            color: white;
            border-radius: 7px;
            cursor: pointer;
            font-size: 16px;
        }

        a {
            color: #246bd6;
        }
    </style>
</head>

<body>

<main>

    <h1>MonstroBolso</h1>
    <h2>Preparacao do combate</h2>

    <p>
        Jogador:
        <strong><?= escapar($perfil->getNome()) ?></strong>
    </p>

    <p>
        Adversario selecionado:
        <strong><?= escapar($npc['nome']) ?></strong>
    </p>

    <?php if ($erro !== ''): ?>
        <div class="mensagem erro">
            <?= escapar($erro) ?>
        </div>
    <?php endif; ?>

    <?php if ($batalhaAtiva): ?>

        <div class="mensagem">
            <strong>
                Batalha #<?= (int) $batalhaAtiva['id'] ?>
                registrada no banco de dados!
            </strong>

            <p>
                Adversario:
                <?= escapar($batalhaAtiva['nome_npc']) ?>
            </p>

            <p>
                Os seis Pokemon foram adicionados ao combate.
                O sistema de turnos sera implementado
                na proxima etapa.
            </p>
        </div>

        <div class="equipes">

            <div class="painel">
                <h3>Equipe do jogador</h3>

                <?php foreach ($pokemonsBatalha as $pokemon): ?>
                    <?php if ($pokemon['participante'] === 'jogador'): ?>

                        <div class="pokemon">
                            <strong>
                                <?= escapar($pokemon['nome']) ?>
                            </strong>

                            HP:
                            <?= (int) $pokemon['hp_atual'] ?>
                            /
                            <?= (int) $pokemon['hp_maximo'] ?>

                            <p>
                                Status:
                                <?= escapar($pokemon['status']) ?>
                            </p>
                        </div>

                    <?php endif; ?>
                <?php endforeach; ?>

            </div>

            <div class="painel">
                <h3>Equipe do adversario</h3>

                <?php foreach ($pokemonsBatalha as $pokemon): ?>
                    <?php if ($pokemon['participante'] === 'npc'): ?>

                        <div class="pokemon">
                            <strong>
                                <?= escapar($pokemon['nome']) ?>
                            </strong>

                            HP:
                            <?= (int) $pokemon['hp_atual'] ?>
                            /
                            <?= (int) $pokemon['hp_maximo'] ?>

                            <p>
                                Status:
                                <?= escapar($pokemon['status']) ?>
                            </p>
                        </div>

                    <?php endif; ?>
                <?php endforeach; ?>

            </div>

        </div>

    <?php else: ?>

        <p>
            Confira as duas equipes antes de iniciar.
        </p>

        <p>
            Limite de itens para cada participante:
            <strong>
                <?= $limites[$npc['dificuldade']] ?>
            </strong>
        </p>

        <div class="equipes">

            <div class="painel">
                <h3>Sua equipe</h3>

                <?php foreach ($equipeJogador as $pokemon): ?>

                    <div class="pokemon">
                        <strong>
                            <?= escapar($pokemon['nome']) ?>
                        </strong>

                        Tipo: <?= escapar($pokemon['tipo']) ?>

                        <p>
                            HP:
                            <?= (int) $pokemon['hp_atual'] ?>
                            /
                            <?= (int) $pokemon['hp_maximo'] ?>
                        </p>

                        <p>
                            Ataque: <?= (int) $pokemon['ataque'] ?>
                            |
                            Defesa: <?= (int) $pokemon['defesa'] ?>
                        </p>

                        <p>
                            Velocidade:
                            <?= (int) $pokemon['velocidade'] ?>
                        </p>
                    </div>

                <?php endforeach; ?>

            </div>

            <div class="painel">
                <h3>Equipe adversaria</h3>

                <?php foreach ($equipeNpc as $pokemon): ?>

                    <div class="pokemon">
                        <strong>
                            <?= escapar($pokemon['nome']) ?>
                        </strong>

                        Tipo: <?= escapar($pokemon['tipo']) ?>

                        <p>
                            HP: <?= (int) $pokemon['hp_base'] ?>
                        </p>

                        <p>
                            Ataque: <?= (int) $pokemon['ataque'] ?>
                            |
                            Defesa: <?= (int) $pokemon['defesa'] ?>
                        </p>

                        <p>
                            Velocidade:
                            <?= (int) $pokemon['velocidade'] ?>
                        </p>
                    </div>

                <?php endforeach; ?>

            </div>

        </div>

        <div class="acoes">

            <form method="POST">
                <input
                    type="hidden"
                    name="token"
                    value="<?= escapar($_SESSION['token_combate']) ?>"
                >

                <button type="submit">
                    Iniciar batalha
                </button>
            </form>

        </div>

    <?php endif; ?>

    <div class="acoes">
        <a href="batalhas.php">Voltar aos treinadores</a>
    </div>

</main>

</body>
</html>
