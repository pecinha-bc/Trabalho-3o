
<?php

class Batalha
{
    private int $id;
    private int $perfilId;
    private int $npcId;
    private string $resultado;
    private int $recompensa;
    private array $eventos = [];

    private int $limiteItens;
    private int $itensUsadosJogador = 0;
    private int $itensUsadosNpc = 0;

    public function __construct(
        int $id,
        int $perfilId,
        int $npcId,
        string $resultado = 'em_andamento',
        int $recompensa = 0,
        string $dificuldade = 'facil'
    ) {
        $this->id = $id;
        $this->perfilId = $perfilId;
        $this->npcId = $npcId;
        $this->resultado = $resultado;
        $this->recompensa = max(0, $recompensa);

        $limites = [
            'facil' => 3,
            'medio' => 5,
            'dificil' => 7
        ];

        if (!isset($limites[$dificuldade])) {
            throw new InvalidArgumentException(
                'Dificuldade de batalha invalida.'
            );
        }

        $this->limiteItens = $limites[$dificuldade];
    }

    // registra os acontecimentos da batalha
    public function registrarEvento(string $descricao): void
    {
        if ($this->estaEmAndamento()) {
            $this->eventos[] = $descricao;
        }
    }

    // verifica se o participante ainda pode usar itens
    public function podeUsarItem(string $participante): bool
    {
        if (!$this->estaEmAndamento()) {
            return false;
        }

        if ($participante === 'jogador') {
            return $this->itensUsadosJogador < $this->limiteItens;
        }

        if ($participante === 'npc') {
            return $this->itensUsadosNpc < $this->limiteItens;
        }

        return false;
    }

    // registra um item utilizado pelo jogador ou npc
    public function registrarUsoItem(string $participante): bool
    {
        if (!$this->podeUsarItem($participante)) {
            return false;
        }

        if ($participante === 'jogador') {
            $this->itensUsadosJogador++;
        } elseif ($participante === 'npc') {
            $this->itensUsadosNpc++;
        }

        return true;
    }

    // informa quantos itens ainda podem ser utilizados
    public function getItensRestantes(string $participante): int
    {
        if ($participante === 'jogador') {
            return max(
                0,
                $this->limiteItens - $this->itensUsadosJogador
            );
        }

        if ($participante === 'npc') {
            return max(
                0,
                $this->limiteItens - $this->itensUsadosNpc
            );
        }

        return 0;
    }

    // finaliza a batalha
    public function finalizar(string $resultado): bool
    {
        $resultadosPermitidos = [
            'vitoria',
            'derrota',
            'empate'
        ];

        if (
            !$this->estaEmAndamento() ||
            !in_array($resultado, $resultadosPermitidos, true)
        ) {
            return false;
        }

        $this->resultado = $resultado;

        return true;
    }

    // verifica se a batalha ainda esta acontecendo
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

    public function getLimiteItens(): int
    {
        return $this->limiteItens;
    }

    public function getItensUsadosJogador(): int
    {
        return $this->itensUsadosJogador;
    }

    public function getItensUsadosNpc(): int
    {
        return $this->itensUsadosNpc;
    }
}
