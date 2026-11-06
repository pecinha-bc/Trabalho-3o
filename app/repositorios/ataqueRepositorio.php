<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/ataque.php';

class AtaqueRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function listarTodos(): array
    {
        $sql = "SELECT * FROM ataque ORDER BY nome";

        $consulta = $this->pdo->query($sql);

        $resultados = $consulta->fetchAll();

        $ataques = [];

        foreach ($resultados as $dados) {
            $ataques[] = new Ataque(
                (int) $dados['id'],
                $dados['nome'],
                $dados['tipo'],
                (int) $dados['poder_base']
            );
        }

        return $ataques;
    }

    public function listarPorPokemon(int $pokemonId): array
    {
        $sql = "
            SELECT a.*
            FROM ataque a
            INNER JOIN pokemon_ataque pa
                ON a.id = pa.ataque_id
            WHERE pa.pokemon_id = :pokemon_id
            ORDER BY pa.posicao
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':pokemon_id' => $pokemonId
        ]);

        $resultados = $consulta->fetchAll();

        $ataques = [];

        foreach ($resultados as $dados) {
            $ataques[] = new Ataque(
                (int) $dados['id'],
                $dados['nome'],
                $dados['tipo'],
                (int) $dados['poder_base']
            );
        }

        return $ataques;
    }
}
