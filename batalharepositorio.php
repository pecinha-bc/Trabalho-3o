<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/batalha.php';

class BatalhaRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function criar(int $perfilId, int $npcId): int
    {
        $sql = "
            INSERT INTO batalha
                (perfil_id, npc_id, resultado, recompensa)
            VALUES
                (:perfil_id, :npc_id, 'em_andamento', 0)
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $perfilId,
            ':npc_id' => $npcId
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarPorId(int $id): ?Batalha
    {
        $sql = "
            SELECT *
            FROM batalha
            WHERE id = :id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':id' => $id
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return new Batalha(
            (int) $dados['id'],
            (int) $dados['perfil_id'],
            (int) $dados['npc_id'],
            $dados['resultado'],
            (int) $dados['recompensa']
        );
    }

    public function listarPorPerfil(int $perfilId): array
    {
        $sql = "
            SELECT *
            FROM batalha
            WHERE perfil_id = :perfil_id
            ORDER BY data_batalha DESC
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $perfilId
        ]);

        $resultados = $consulta->fetchAll();

        $batalhas = [];

        foreach ($resultados as $dados) {
            $batalhas[] = new Batalha(
                (int) $dados['id'],
                (int) $dados['perfil_id'],
                (int) $dados['npc_id'],
                $dados['resultado'],
                (int) $dados['recompensa']
            );
        }

        return $batalhas;
    }

    public function finalizar(
        int $batalhaId,
        string $resultado,
        string $log = ''
    ): void {
        $resultadosPermitidos = [
            'vitoria',
            'derrota',
            'empate'
        ];

        if (!in_array($resultado, $resultadosPermitidos, true)) {
            throw new InvalidArgumentException(
                'Resultado de batalha invalido.'
            );
        }

        $sql = "
            UPDATE batalha
            SET resultado = :resultado,
                log = :log
            WHERE id = :id
            AND resultado = 'em_andamento'
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':resultado' => $resultado,
            ':log' => $log,
            ':id' => $batalhaId
        ]);
    }
}