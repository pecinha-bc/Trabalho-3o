<?php

class Pokemon
{
    private int $id;
    private string $nome;
    private string $tipo;
    private int $hpMax;
    private int $hpAtual;
    private int $ataque;
    private int $defesa;
    private int $velocidade;

    public function __construct(
        int $id,
        string $nome,
        string $tipo,
        int $hpMax,
        int $ataque,
        int $defesa,
        int $velocidade
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->tipo = $tipo;
        $this->hpMax = max(1, $hpMax);
        $this->hpAtual = $this->hpMax;
        $this->ataque = $ataque;
        $this->defesa = $defesa;
        $this->velocidade = $velocidade;
    }

    public function receberDano(int $dano): void
    {
        $dano = max(0, $dano);

        $this->hpAtual -= $dano;

        if ($this->hpAtual < 0) {
            $this->hpAtual = 0;
        }
    }

    public function estaVivo(): bool
    {
        return $this->hpAtual > 0;
    }

    public function curar(int $quantidade): void
    {
        $quantidade = max(0, $quantidade);

        $this->hpAtual += $quantidade;

        if ($this->hpAtual > $this->hpMax) {
            $this->hpAtual = $this->hpMax;
        }
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

    public function getHpMax(): int
    {
        return $this->hpMax;
    }

    public function getHpAtual(): int
    {
        return $this->hpAtual;
    }

    public function getAtaque(): int
    {
        return $this->ataque;
    }

    public function getDefesa(): int
    {
        return $this->defesa;
    }

    public function getVelocidade(): int
    {
        return $this->velocidade;
    }
}