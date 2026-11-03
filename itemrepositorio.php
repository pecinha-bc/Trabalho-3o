<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/item.php';

class ItemRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function listarTodos(): array
    {
        $sql = "SELECT * FROM item ORDER BY nome";

        $consulta = $this->pdo->query($sql);

        $resultados = $consulta->fetchAll();

        $itens = [];

        foreach ($resultados as $dados) {
            $itens[] = new Item(
                (int) $dados['id'],
                $dados['nome'],
                (int) $dados['preco'],
                $dados['tipo_efeito'],
                (int) $dados['valor_efeito'],
                $dados['status_removido'],
                (bool) $dados['aparece_loja'],
                (bool) $dados['aparece_inventario']
            );
        }

        return $itens;
    }

    public function listarLoja(): array
    {
        $sql = "
            SELECT *
            FROM item
            WHERE aparece_loja = TRUE
            ORDER BY nome
        ";

        $consulta = $this->pdo->query($sql);

        $resultados = $consulta->fetchAll();

        $itens = [];

        foreach ($resultados as $dados) {
            $itens[] = new Item(
                (int) $dados['id'],
                $dados['nome'],
                (int) $dados['preco'],
                $dados['tipo_efeito'],
                (int) $dados['valor_efeito'],
                $dados['status_removido'],
                (bool) $dados['aparece_loja'],
                (bool) $dados['aparece_inventario']
            );
        }

        return $itens;
    }

    public function buscarPorId(int $id): ?Item
    {
        $sql = "SELECT * FROM item WHERE id = :id";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':id' => $id
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return new Item(
            (int) $dados['id'],
            $dados['nome'],
            (int) $dados['preco'],
            $dados['tipo_efeito'],
            (int) $dados['valor_efeito'],
            $dados['status_removido'],
            (bool) $dados['aparece_loja'],
            (bool) $dados['aparece_inventario']
        );
    }
}