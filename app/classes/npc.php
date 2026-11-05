<?php

class NPC
{
    private int $id;
    private string $nome;
    private string $dificuldade;
    private int $recompensa;

    public function __construct(
        int $id,
        string $nome,
        string $dificuldade,
        int $recompensa
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->dificuldade = $dificuldade;
        $this->recompensa = max(0, $recompensa);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getDificuldade(): string
    {
        return $this->dificuldade;
    }

    public function getRecompensa(): int
    {
        return $this->recompensa;
    }
}
