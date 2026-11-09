<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/inventarionpc.php';

class InventarioNPCRepositorio
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
            FROM inventario_npc
            WHERE npc_id = :npc_id
            ORDER BY id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':npc_id' => $npcId
        ]);

        $resultados = $consulta->fetchAll();

        $itens = [];

        foreach ($resultados as $dados) {
            $itens[] = new InventarioNPC(
                (int) $dados['id'],
                (int) $dados['npc_id'],
                (int) $dados['item_id'],
                (int) $dados['quantidade']
            );
        }

        return $itens;
    }

    public function buscarItem(int $npcId, int $itemId): ?InventarioNPC
    {
        $sql = "
            SELECT *
            FROM inventario_npc
            WHERE npc_id = :npc_id
            AND item_id = :item_id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':npc_id' => $npcId,
            ':item_id' => $itemId
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return new InventarioNPC(
            (int) $dados['id'],
            (int) $dados['npc_id'],
            (int) $dados['item_id'],
            (int) $dados['quantidade']
        );
    }

    public function salvar(InventarioNPC $inventario): void
    {
        $sql = "
            INSERT INTO inventario_npc
                (npc_id, item_id, quantidade)
            VALUES
                (:npc_id, :item_id, :quantidade)
            ON DUPLICATE KEY UPDATE
                quantidade = VALUES(quantidade)
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':npc_id' => $inventario->getNpcId(),
            ':item_id' => $inventario->getItemId(),
            ':quantidade' => $inventario->getQuantidade()
        ]);
    }

    public function removerItem(int $npcId, int $itemId): void
    {
        $sql = "
            DELETE FROM inventario_npc
            WHERE npc_id = :npc_id
            AND item_id = :item_id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':npc_id' => $npcId,
            ':item_id' => $itemId
        ]);
    }
}
