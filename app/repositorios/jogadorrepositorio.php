
<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/jogador.php';

class JogadorRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    // cria o perfil com kit inicial em uma transacao unica
    public function criarPerfil(int $usuarioId, string $nome): int
    {
        $nome = trim($nome);
        if ($usuarioId <= 0 || $nome === '') {
            throw new InvalidArgumentException('Usuario e nome do perfil sao obrigatorios.');
        }

        $this->pdo->beginTransaction();
        try {
            $consulta = $this->pdo->prepare(
                'INSERT INTO perfil (usuario_id, nome, saldo) VALUES (:usuario, :nome, 100)'
            );
            $consulta->execute([':usuario' => $usuarioId, ':nome' => $nome]);
            $perfilId = (int) $this->pdo->lastInsertId();

            $consulta = $this->pdo->prepare(
                "SELECT id, nome FROM item WHERE nome IN ('Pocao', 'Antidoto')"
            );
            $consulta->execute();
            $itens = $consulta->fetchAll();
            if (count($itens) !== 2) {
                throw new RuntimeException('Importe os dados iniciais antes de criar perfis.');
            }

            $inserir = $this->pdo->prepare(
                'INSERT INTO inventario (perfil_id, item_id, quantidade) VALUES (:perfil, :item, :qtd)'
            );
            foreach ($itens as $item) {
                $inserir->execute([
                    ':perfil' => $perfilId,
                    ':item' => (int) $item['id'],
                    ':qtd' => $item['nome'] === 'Pocao' ? 2 : 1
                ]);
            }

            // impede que a migracao opcional entregue o kit uma segunda vez
            $consulta = $this->pdo->prepare(
                'INSERT INTO kit_inicial_entregue (perfil_id) VALUES (:id)'
            );
            $consulta->execute([':id' => $perfilId]);
            $this->pdo->commit();
            return $perfilId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function buscarPorId(int $id): ?Jogador
    {
        $sql = "SELECT * FROM perfil WHERE id = :id";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([':id' => $id]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return new Jogador(
            (int) $dados['id'],
            (int) $dados['usuario_id'],
            $dados['nome'],
            (int) $dados['saldo']
        );
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        $sql = "
            SELECT *
            FROM perfil
            WHERE usuario_id = :usuario_id
            ORDER BY nome
        ";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([':usuario_id' => $usuarioId]);

        $jogadores = [];

        foreach ($consulta->fetchAll() as $dados) {
            $jogadores[] = new Jogador(
                (int) $dados['id'],
                (int) $dados['usuario_id'],
                $dados['nome'],
                (int) $dados['saldo']
            );
        }

        return $jogadores;
    }

    public function atualizarSaldo(int $perfilId, int $saldo): bool
    {
        if ($perfilId <= 0 || $saldo < 0) {
            return false;
        }

        $sql = "
            UPDATE perfil
            SET saldo = :saldo
            WHERE id = :id
        ";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([
            ':saldo' => $saldo,
            ':id' => $perfilId
        ]);

        return $consulta->rowCount() > 0;
    }

    public function adicionarSaldo(int $perfilId, int $valor): bool
    {
        if ($perfilId <= 0 || $valor <= 0) {
            return false;
        }

        $sql = "
            UPDATE perfil
            SET saldo = saldo + :valor
            WHERE id = :id
        ";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([
            ':valor' => $valor,
            ':id' => $perfilId
        ]);

        return $consulta->rowCount() > 0;
    }

    public function gastarSaldo(int $perfilId, int $valor): bool
    {
        if ($perfilId <= 0 || $valor <= 0) {
            return false;
        }

        $sql = "
            UPDATE perfil
            SET saldo = saldo - :valor
            WHERE id = :id
            AND saldo >= :valor
        ";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([
            ':valor' => $valor,
            ':id' => $perfilId
        ]);

        return $consulta->rowCount() > 0;
    }
}
