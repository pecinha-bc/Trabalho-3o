<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/pokemon.php';

class PokemonRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function listarTodos(): array
    {
        $sql = "SELECT * FROM pokemon ORDER BY nome";

        $consulta = $this->pdo->query($sql);

        $resultados = $consulta->fetchAll();

        $pokemons = [];

        foreach ($resultados as $dados) {
            $pokemons[] = new Pokemon(
                (int) $dados['id'],
                $dados['nome'],
                $dados['tipo'],
                (int) $dados['hp_base'],
                (int) $dados['ataque'],
                (int) $dados['defesa'],
                (int) $dados['velocidade']
            );
        }

        return $pokemons;
    }

    public function buscarPorId(int $id): ?Pokemon
    {
        $sql = "SELECT * FROM pokemon WHERE id = :id";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':id' => $id
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return new Pokemon(
            (int) $dados['id'],
            $dados['nome'],
            $dados['tipo'],
            (int) $dados['hp_base'],
            (int) $dados['ataque'],
            (int) $dados['defesa'],
            (int) $dados['velocidade']
        );
    }
}