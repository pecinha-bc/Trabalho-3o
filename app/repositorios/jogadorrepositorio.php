
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

    public function criarPerfil(int $usuarioId, string $nome): int
    {
        $nome = trim($nome);

        if ($usuarioId <= 0 || $nome === '') {
            throw new InvalidArgumentException(
                'Usuario e nome do perfil sao obrigatorios.'
            );
        }

        $sql = "
            INSERT INTO perfil (usuario_id, nome, saldo)
            VALUES (:usuario_id, :nome, 0)
        ";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([
            ':usuario_id' => $usuarioId,
            ':nome' => $nome
        ]);

        return (int) $this->pdo->lastInsertId();
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
