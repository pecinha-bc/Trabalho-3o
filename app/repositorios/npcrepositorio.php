<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/npc.php';

class NPCRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function listarTodos(): array
    {
        $sql = "
            SELECT *
            FROM npc
            ORDER BY id
        ";

        $consulta = $this->pdo->query($sql);
        $resultados = $consulta->fetchAll();

        $npcs = [];

        foreach ($resultados as $dados) {
            $npcs[] = new NPC(
                (int) $dados['id'],
                $dados['nome'],
                $dados['dificuldade'],
                (int) $dados['recompensa']
            );
        }

        return $npcs;
    }

    public function buscarPorId(int $id): ?NPC
    {
        $sql = "
            SELECT *
            FROM npc
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

        return new NPC(
            (int) $dados['id'],
            $dados['nome'],
            $dados['dificuldade'],
            (int) $dados['recompensa']
        );
    }
}
