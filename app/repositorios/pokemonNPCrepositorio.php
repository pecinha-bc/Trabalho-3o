<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/pokemonnpc.php';

class PokemonNPCRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function listarPorNPC(int $npcId): array
    {
        $sql = "
            SELECT *
            FROM npc_pokemon
            WHERE npc_id = :npc_id
            ORDER BY posicao
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':npc_id' => $npcId
        ]);

        $resultados = $consulta->fetchAll();

        $pokemons = [];

        foreach ($resultados as $dados) {
            $pokemons[] = new PokemonNPC(
                (int) $dados['id'],
                (int) $dados['npc_id'],
                (int) $dados['pokemon_id'],
                (int) $dados['posicao'],
                (int) $dados['hp_atual'],
                (int) $dados['hp_maximo'],
                $dados['status']
            );
        }

        return $pokemons;
    }

    public function buscarPorId(int $id): ?PokemonNPC
    {
        $sql = "
            SELECT *
            FROM npc_pokemon
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

        return new PokemonNPC(
            (int) $dados['id'],
            (int) $dados['npc_id'],
            (int) $dados['pokemon_id'],
            (int) $dados['posicao'],
            (int) $dados['hp_atual'],
            (int) $dados['hp_maximo'],
            $dados['status']
        );
    }
}
