
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

    // cria uma batalha para o perfil e o npc
    public function criar(int $perfilId, int $npcId): int
    {
        if ($perfilId <= 0 || $npcId <= 0) {
            throw new InvalidArgumentException(
                'Perfil ou treinador invalido.'
            );
        }

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

    // busca uma batalha e a dificuldade do treinador
    public function buscarPorId(int $id): ?Batalha
    {
        $sql = "
            SELECT
                b.*,
                n.dificuldade
            FROM batalha b
            INNER JOIN npc n
                ON n.id = b.npc_id
            WHERE b.id = :id
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':id' => $id
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return $this->criarObjeto($dados);
    }

    // lista o historico de batalhas do perfil
    public function listarPorPerfil(int $perfilId): array
    {
        $sql = "
            SELECT
                b.*,
                n.dificuldade
            FROM batalha b
            INNER JOIN npc n
                ON n.id = b.npc_id
            WHERE b.perfil_id = :perfil_id
            ORDER BY b.data_batalha DESC, b.id DESC
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $perfilId
        ]);

        $batalhas = [];

        foreach ($consulta->fetchAll() as $dados) {
            $batalhas[] = $this->criarObjeto($dados);
        }

        return $batalhas;
    }

    // busca a batalha em andamento de um perfil
    public function buscarEmAndamento(
        int $perfilId
    ): ?Batalha {
        $sql = "
            SELECT
                b.*,
                n.dificuldade
            FROM batalha b
            INNER JOIN npc n
                ON n.id = b.npc_id
            WHERE b.perfil_id = :perfil_id
              AND b.resultado = 'em_andamento'
            ORDER BY b.id DESC
            LIMIT 1
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':perfil_id' => $perfilId
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return $this->criarObjeto($dados);
    }

    // finaliza a batalha e aciona os gatilhos do banco
    public function finalizar(
        int $batalhaId,
        string $resultado,
        string $log = ''
    ): bool {
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

        return $consulta->rowCount() === 1;
    }

    // transforma os dados do banco em objeto
    private function criarObjeto(array $dados): Batalha
    {
        return new Batalha(
            (int) $dados['id'],
            (int) $dados['perfil_id'],
            (int) $dados['npc_id'],
            $dados['resultado'],
            (int) $dados['recompensa'],
            $dados['dificuldade']
        );
    }
}
