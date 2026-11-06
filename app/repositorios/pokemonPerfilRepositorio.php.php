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

        $resultados = $consulta->fetchAll();

        $pokemons = [];

        foreach ($resultados as $dados) {
            $pokemons[] = new PokemonPerfil(
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

        return $pokemons;
    }

    public function buscarPorId(int $id): ?PokemonPerfil
    {
        $sql = "
            SELECT *
            FROM pokemon_perfil
            WHERE id = :id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':id' => $id
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

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
