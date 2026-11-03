
<?php

class Item
{
    private int $id;
    private string $nome;
    private int $preco;
    private string $tipoEfeito;
    private int $valorEfeito;
    private string $statusRemovido;
    private bool $apareceLoja;
    private bool $apareceInventario;

    public function __construct(
        int $id,
        string $nome,
        int $preco,
        string $tipoEfeito,
        int $valorEfeito,
        string $statusRemovido = 'nenhum',
        bool $apareceLoja = true,
        bool $apareceInventario = true
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->preco = max(0, $preco);
        $this->tipoEfeito = $tipoEfeito;
        $this->valorEfeito = max(0, $valorEfeito);
        $this->statusRemovido = $statusRemovido;
        $this->apareceLoja = $apareceLoja;
        $this->apareceInventario = $apareceInventario;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getPreco(): int
    {
        return $this->preco;
    }

    public function getTipoEfeito(): string
    {
        return $this->tipoEfeito;
    }

    public function getValorEfeito(): int
    {
        return $this->valorEfeito;
    }

    public function getStatusRemovido(): string
    {
        return $this->statusRemovido;
    }

    public function apareceNaLoja(): bool
    {
        return $this->apareceLoja;
    }

    public function apareceNoInventario(): bool
    {
        return $this->apareceInventario;
    }
}
