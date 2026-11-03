<?php

class Inventario
{
    private int $id;
    private int $perfilId;
    private int $itemId;
    private int $quantidade;

    public function __construct(
        int $id,
        int $perfilId,
        int $itemId,
        int $quantidade = 0
    ) {
        $this->id = $id;
        $this->perfilId = $perfilId;
        $this->itemId = $itemId;
        $this->quantidade = max(0, $quantidade);
    }

    public function adicionar(int $quantidade): void
    {
        if ($quantidade > 0) {
            $this->quantidade += $quantidade;
        }
    }

    public function remover(int $quantidade): bool
    {
        if ($quantidade <= 0 || $quantidade > $this->quantidade) {
            return false;
        }

        $this->quantidade -= $quantidade;

        return true;
    }

    public function possuiItem(): bool
    {
        return $this->quantidade > 0;
    }

    public function getId(): int { return $this->id; }
    public function getPerfilId(): int { return $this->perfilId; }
    public function getItemId(): int { return $this->itemId; }
    public function getQuantidade(): int { return $this->quantidade; }
}