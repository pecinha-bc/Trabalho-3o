<?php

class InventarioNPC
{
    private int $id;
    private int $npcId;
    private int $itemId;
    private int $quantidade;

    public function __construct(
        int $id,
        int $npcId,
        int $itemId,
        int $quantidade
    ) {
        $this->id = $id;
        $this->npcId = $npcId;
        $this->itemId = $itemId;
        $this->quantidade = $quantidade;
    }

    public function adicionar(int $quantidade): void
    {
        if ($quantidade > 0) {
            $this->quantidade += $quantidade;
        }
    }

    public function remover(int $quantidade): void
    {
        if ($quantidade > 0 && $quantidade <= $this->quantidade) {
            $this->quantidade -= $quantidade;
        }
    }

    public function possuiItem(): bool
    {
        return $this->quantidade > 0;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNpcId(): int
    {
        return $this->npcId;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getQuantidade(): int
    {
        return $this->quantidade;
    }
}