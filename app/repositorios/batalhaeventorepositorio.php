<?php

require_once __DIR__ . '/../conexao.php';

class BatalhaEventoRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function registrarEvento(
        int $batalhaId,
        string $tipoEvento,
        string $descricao,
        ?int $batalhaPokemonId = null,
        ?int $ataqueId = null,
        ?int $itemId = null
    ): void {
        $sql = "
            INSERT INTO batalha_evento (
                batalha_id,
                batalha_pokemon_id,
                ataque_id,
                item_id,
                tipo_evento,
                descricao
            )
            VALUES (
                :batalha_id,
                :batalha_pokemon_id,
                :ataque_id,
                :item_id,
                :tipo_evento,
                :descricao
            )
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':batalha_id' => $batalhaId,
            ':batalha_pokemon_id' => $batalhaPokemonId,
            ':ataque_id' => $ataqueId,
            ':item_id' => $itemId,
            ':tipo_evento' => $tipoEvento,
            ':descricao' => $descricao
        ]);
    }

    public function listarPorBatalha(int $batalhaId): array
    {
        $sql = "
            SELECT *
            FROM batalha_evento
            WHERE batalha_id = :batalha_id
            ORDER BY data_evento, id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':batalha_id' => $batalhaId
        ]);

        return $consulta->fetchAll();
    }
}
