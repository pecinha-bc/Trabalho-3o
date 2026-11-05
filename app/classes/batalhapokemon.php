<?php

class BatalhaPokemon
{
    private int $id;
    private int $batalhaId;
    private ?int $pokemonPerfilId;
    private ?int $npcPokemonId;
    private int $hpAtual;
    private int $hpMaximo;
    private string $status;

    public function __construct(
        int $id,
        int $batalhaId,
        ?int $pokemonPerfilId,
        ?int $npcPokemonId,
        int $hpAtual,
        int $hpMaximo,
        string $status = 'nenhum'
    ) {
        $this->id = $id;
        $this->batalhaId = $batalhaId;
        $this->pokemonPerfilId = $pokemonPerfilId;
        $this->npcPokemonId = $npcPokemonId;
        $this->hpAtual = $hpAtual;
        $this->hpMaximo = $hpMaximo;
        $this->status = $status;
    }

    public function receberDano(int $dano): void
    {
        $this->hpAtual = max(0, $this->hpAtual - $dano);
    }

    public function curar(int $quantidade): void
    {
        $this->hpAtual = min(
            $this->hpMaximo,
            $this->hpAtual + $quantidade
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

    public function getId(): int
    {
        return $this->id;
    }

    public function getBatalhaId(): int
    {
        return $this->batalhaId;
    }

    public function getPokemonPerfilId(): ?int
    {
        return $this->pokemonPerfilId;
    }

    public function getNpcPokemonId(): ?int
    {
        return $this->npcPokemonId;
    }

    public function getHpAtual(): int
    {
        return $this->hpAtual;
    }

    public function getHpMaximo(): int
    {
        return $this->hpMaximo;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
