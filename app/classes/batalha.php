<?php

class Batalha
{
    private int $id;
    private int $perfilId;
    private int $npcId;
    private string $resultado;
    private int $recompensa;
    private array $eventos = [];

    public function __construct(
        int $id,
        int $perfilId,
        int $npcId,
        string $resultado = 'em_andamento',
        int $recompensa = 0
    ) {
        $this->id = $id;
        $this->perfilId = $perfilId;
        $this->npcId = $npcId;
        $this->resultado = $resultado;
        $this->recompensa = max(0, $recompensa);
    }

    public function registrarEvento(string $descricao): void
    {
        if ($this->resultado === 'em_andamento') {
            $this->eventos[] = $descricao;
        }
    }

    public function finalizar(string $resultado): bool
    {
        $resultadosPermitidos = [
            'vitoria',
            'derrota',
            'empate'
        ];

        if (
            $this->resultado !== 'em_andamento' ||
            !in_array($resultado, $resultadosPermitidos, true)
        ) {
            return false;
        }

        $this->resultado = $resultado;

        return true;
    }

    public function estaEmAndamento(): bool
    {
        return $this->resultado === 'em_andamento';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPerfilId(): int
    {
        return $this->perfilId;
    }

    public function getNpcId(): int
    {
        return $this->npcId;
    }

    public function getResultado(): string
    {
        return $this->resultado;
    }

    public function getRecompensa(): int
    {
        return $this->recompensa;
    }

    public function getEventos(): array
    {
        return $this->eventos;
    }
}
