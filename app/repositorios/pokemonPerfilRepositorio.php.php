
<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/pokemonperfil.php';

class PokemonPerfilRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    // lista os pokemons pertencentes ao perfil
    public function listarPorPerfil(int $perfilId): array
    {
        $sql = "
            SELECT *
            FROM pokemon_perfil
            WHERE perfil_id = :perfil_id
            ORDER BY id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $perfilId
        ]);

        $pokemons = [];

        foreach ($consulta->fetchAll() as $dados) {
            $pokemons[] = $this->criarObjeto($dados);
        }

        return $pokemons;
    }

    // busca um pokemon do perfil pelo id
    public function buscarPorId(int $id): ?PokemonPerfil
    {
        $sql = "
            SELECT *
            FROM pokemon_perfil
            WHERE id = :id
        ";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([':id' => $id]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return $this->criarObjeto($dados);
    }

    // verifica se o perfil ja possui pokemons
    public function possuiEquipe(int $perfilId): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM pokemon_perfil
            WHERE perfil_id = :perfil_id
        ";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([
            ':perfil_id' => $perfilId
        ]);

        return (int) $consulta->fetchColumn() > 0;
    }

    // registra os tres pokemons iniciais
    public function criarEquipeInicial(
        int $perfilId,
        array $pokemonIds
    ): bool {
        if ($perfilId <= 0) {
            throw new InvalidArgumentException(
                'Perfil invalido.'
            );
        }

        $pokemonIds = array_map('intval', $pokemonIds);

        if (
            count($pokemonIds) !== 3 ||
            count(array_unique($pokemonIds)) !== 3
        ) {
            throw new InvalidArgumentException(
                'Escolha exatamente tres pokemons diferentes.'
            );
        }

        $nomesPermitidos = [
            'Charmander', 'Growlithe', 'Vulpix',
            'Squirtle', 'Psyduck', 'Poliwag',
            'Bulbasaur', 'Oddish', 'Bellsprout',
            'Pikachu', 'Magnemite', 'Electabuzz'
        ];

        try {
            $this->pdo->beginTransaction();

            // bloqueia o perfil durante a escolha
            $consultaPerfil = $this->pdo->prepare(
                "SELECT id FROM perfil WHERE id = :id FOR UPDATE"
            );

            $consultaPerfil->execute([
                ':id' => $perfilId
            ]);

            if (!$consultaPerfil->fetch()) {
                throw new InvalidArgumentException(
                    'Perfil nao encontrado.'
                );
            }

            if ($this->possuiEquipe($perfilId)) {
                throw new RuntimeException(
                    'Este perfil ja possui uma equipe.'
                );
            }

            // confirma que os tres pokemons sao permitidos
            $marcadores = implode(
                ',',
                array_fill(0, count($nomesPermitidos), '?')
            );

            $sql = "
                SELECT id
                FROM pokemon
                WHERE nome IN ($marcadores)
            ";

            $consulta = $this->pdo->prepare($sql);
            $consulta->execute($nomesPermitidos);

            $idsPermitidos = array_map(
                'intval',
                $consulta->fetchAll(PDO::FETCH_COLUMN)
            );

            foreach ($pokemonIds as $pokemonId) {
                if (!in_array($pokemonId, $idsPermitidos, true)) {
                    throw new InvalidArgumentException(
                        'Pokemon nao permitido na escolha inicial.'
                    );
                }
            }

            // o trigger do banco inicializa os atributos
            $sql = "
                INSERT INTO pokemon_perfil
                    (perfil_id, pokemon_id)
                VALUES
                    (:perfil_id, :pokemon_id)
            ";

            $consulta = $this->pdo->prepare($sql);

            foreach ($pokemonIds as $pokemonId) {
                $consulta->execute([
                    ':perfil_id' => $perfilId,
                    ':pokemon_id' => $pokemonId
                ]);
            }

            $this->pdo->commit();

            return true;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    // transforma os dados do banco em objeto
    private function criarObjeto(array $dados): PokemonPerfil
    {
        return new PokemonPerfil(
            (int) $dados['id'],
            (int) $dados['perfil_id'],
            (int) $dados['pokemon_id'],
            (int) $dados['hp_atual'],
            (int) $dados['hp_maximo'],
            (int) $dados['ataque'],
            (int) $dados['defesa'],
            (int) $dados['velocidade'],
            (int) $dados['nivel'],
            (int) $dados['experiencia'],
            (bool) $dados['evoluiu'],
            $dados['status']
        );
    }
}
