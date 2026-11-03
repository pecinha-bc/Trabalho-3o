<?php

class PokemonNPC
{
    private int $id;
    private int $npcId;
    private int $pokemonId;
    private int $posicao;
    private int $hpAtual;
    private int $hpMaximo;
    private string $status;

    public function __construct(
        int $id,
        int $npcId,
        int $pokemonId,
        int $posicao,
        int $hpAtual,
        int $hpMaximo,
        string $status = 'nenhum'
    ) {
        $this->id = $id;
        $this->npcId = $npcId;
        $this->pokemonId = $pokemonId;
        $this->posicao = $posicao;
        $this->hpMaximo = max(1, $hpMaximo);
        $this->hpAtual = max(0, min($hpAtual, $this->hpMaximo));
        $this->status = $status;
    }

    public function receberDano(int $dano): void
    {
        $this->hpAtual = max(0, $this->hpAtual - max(0, $dano));
    }

    public function curar(int $quantidade): void
    {
        $this->hpAtual = min(
            $this->hpMaximo,
            $this->hpAtual + max(0, $quantidade)
        );
    }

    public function estaVivo(): bool
    {
        return $this->hpAtual > 0;
    }

    public function alterarStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getId(): int { return $this->id; }
    public function getNpcId(): int { return $this->npcId; }
    public function getPokemonId(): int { return $this->pokemonId; }
    public function getPosicao(): int { return $this->posicao; }
    public function getHpAtual(): int { return $this->hpAtual; }
    public function getHpMaximo(): int { return $this->hpMaximo; }
    public function getStatus(): string { return $this->status; }
}