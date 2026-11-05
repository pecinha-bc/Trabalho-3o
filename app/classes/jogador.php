<?php

class Jogador
{
    private int $id;
    private int $usuarioId;
    private string $nome;
    private int $saldo;

    public function __construct(
        int $id,
        int $usuarioId,
        string $nome,
        int $saldo = 0
    ) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->nome = $nome;
        $this->saldo = max(0, $saldo);
    }

    public function adicionarSaldo(int $valor): void
    {
        if ($valor > 0) {
            $this->saldo += $valor;
        }
    }

    public function gastarSaldo(int $valor): bool
    {
        if ($valor <= 0 || $valor > $this->saldo) {
            return false;
        }

        $this->saldo -= $valor;

        return true;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getSaldo(): int
    {
        return $this->saldo;
    }
}
