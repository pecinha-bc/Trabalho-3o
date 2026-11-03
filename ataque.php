<?php

class Ataque
{
    private int $id;
    private string $nome;
    private string $tipo;
    private int $poder;

    public function __construct(
        int $id,
        string $nome,
        string $tipo,
        int $poder
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->tipo = $tipo;
        $this->poder = $poder;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function getPoder(): int
    {
        return $this->poder;
    }
}