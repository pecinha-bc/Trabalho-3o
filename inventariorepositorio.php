<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/inventario.php';

class InventarioRepositorio
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
            FROM inventario
            WHERE perfil_id = :perfil_id
            ORDER BY id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $perfilId
        ]);

        $resultados = $consulta->fetchAll();

        $itens = [];

        foreach ($resultados as $dados) {
            $itens[] = new Inventario(
                (int) $dados['id'],
                (int) $dados['perfil_id'],
                (int) $dados['item_id'],
                (int) $dados['quantidade']
            );
        }

        return $itens;
    }

    public function buscarItem(int $perfilId, int $itemId): ?Inventario
    {
        $sql = "
            SELECT *
            FROM inventario
            WHERE perfil_id = :perfil_id
            AND item_id = :item_id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $perfilId,
            ':item_id' => $itemId
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return new Inventario(
            (int) $dados['id'],
            (int) $dados['perfil_id'],
            (int) $dados['item_id'],
            (int) $dados['quantidade']
        );
    }

    public function salvar(Inventario $inventario): void
    {
        $sql = "
            INSERT INTO inventario
                (perfil_id, item_id, quantidade)
            VALUES
                (:perfil_id, :item_id, :quantidade)
            ON DUPLICATE KEY UPDATE
                quantidade = VALUES(quantidade)
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $inventario->getPerfilId(),
            ':item_id' => $inventario->getItemId(),
            ':quantidade' => $inventario->getQuantidade()
        ]);
    }

    public function removerItem(int $perfilId, int $itemId): void
    {
        $sql = "
            DELETE FROM inventario
            WHERE perfil_id = :perfil_id
            AND item_id = :item_id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $perfilId,
            ':item_id' => $itemId
        ]);
    }
}