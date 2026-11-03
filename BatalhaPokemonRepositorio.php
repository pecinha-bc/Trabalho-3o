<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/batalhapokemon.php';

class BatalhaPokemonRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function adicionarPokemonPerfil(
        int $batalhaId,
        int $pokemonPerfilId
    ): int {
        $sql = "
            INSERT INTO batalha_pokemon (
                batalha_id,
                pokemon_perfil_id,
                npc_pokemon_id
            )
            VALUES (
                :batalha_id,
                :pokemon_perfil_id,
                NULL
            )
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':batalha_id' => $batalhaId,
            ':pokemon_perfil_id' => $pokemonPerfilId
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function adicionarPokemonNPC(
        int $batalhaId,
        int $npcPokemonId
    ): int {
        $sql = "
            INSERT INTO batalha_pokemon (
                batalha_id,
                pokemon_perfil_id,
                npc_pokemon_id
            )
            VALUES (
                :batalha_id,
                NULL,
                :npc_pokemon_id
            )
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':batalha_id' => $batalhaId,
            ':npc_pokemon_id' => $npcPokemonId
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listarPorBatalha(int $batalhaId): array
    {
        $sql = "
            SELECT *
            FROM batalha_pokemon
            WHERE batalha_id = :batalha_id
            ORDER BY id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':batalha_id' => $batalhaId
        ]);

        $resultados = $consulta->fetchAll();

        $pokemons = [];

        foreach ($resultados as $dados) {
            $pokemons[] = new BatalhaPokemon(
                (int) $dados['id'],
                (int) $dados['batalha_id'],
                $dados['pokemon_perfil_id'] !== null
                    ? (int) $dados['pokemon_perfil_id']
                    : null,
                $dados['npc_pokemon_id'] !== null
                    ? (int) $dados['npc_pokemon_id']
                    : null,
                (int) $dados['hp_atual'],
                (int) $dados['hp_maximo'],
                $dados['status']
            );
        }

        return $pokemons;
    }

    public function atualizarEstado(
        int $id,
        int $hpAtual,
        string $status
    ): void {
        $sql = "
            UPDATE batalha_pokemon
            SET hp_atual = :hp_atual,
                status = :status
            WHERE id = :id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':hp_atual' => $hpAtual,
            ':status' => $status,
            ':id' => $id
        ]);
    }
}