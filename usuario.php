<?php

class Usuario
{
    private int $id;
    private string $nomeUsuario;
    private string $senhaHash;

    public function __construct(
        int $id,
        string $nomeUsuario,
        string $senhaHash
    ) {
        $this->id = $id;
        $this->nomeUsuario = $nomeUsuario;
        $this->senhaHash = $senhaHash;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNomeUsuario(): string
    {
        return $this->nomeUsuario;
    }

    public function getSenhaHash(): string
    {
        return $this->senhaHash;
    }
}