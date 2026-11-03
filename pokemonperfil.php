<?php

class PokemonPerfil
{
    private int $id;
    private int $perfilId;
    private int $pokemonId;
    private int $hpAtual;
    private int $hpMaximo;
    private int $ataque;
    private int $defesa;
    private int $velocidade;
    private int $nivel;
    private int $experiencia;
    private bool $evoluiu;
    private string $status;

    public function __construct(
        int $id,
        int $perfilId,
        int $pokemonId,
        int $hpAtual,
        int $hpMaximo,
        int $ataque,
        int $defesa,
        int $velocidade,
        int $nivel = 1,
        int $experiencia = 0,
        bool $evoluiu = false,
        string $status = 'nenhum'
    ) {
        $this->id = $id;
        $this->perfilId = $perfilId;
        $this->pokemonId = $pokemonId;
        $this->hpMaximo = max(1, $hpMaximo);
        $this->hpAtual = max(0, min($hpAtual, $this->hpMaximo));
        $this->ataque = $ataque;
        $this->defesa = $defesa;
        $this->velocidade = $velocidade;
        $this->nivel = max(1, $nivel);
        $this->experiencia = max(0, $experiencia);
        $this->evoluiu = $evoluiu;
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

    public function adicionarExperiencia(int $quantidade): void
    {
        if ($quantidade > 0) {
            $this->experiencia += $quantidade;
        }
    }

    public function alterarStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getId(): int { return $this->id; }
    public function getPerfilId(): int { return $this->perfilId; }
    public function getPokemonId(): int { return $this->pokemonId; }
    public function getHpAtual(): int { return $this->hpAtual; }
    public function getHpMaximo(): int { return $this->hpMaximo; }
    public function getAtaque(): int { return $this->ataque; }
    public function getDefesa(): int { return $this->defesa; }
    public function getVelocidade(): int { return $this->velocidade; }
    public function getNivel(): int { return $this->nivel; }
    public function getExperiencia(): int { return $this->experiencia; }
    public function getStatus(): string { return $this->status; }
    public function jaEvoluiu(): bool { return $this->evoluiu; }
}