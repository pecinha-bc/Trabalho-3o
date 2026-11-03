<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../classes/usuario.php';

class UsuarioRepositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexao::conectar();
    }

    public function cadastrar(string $nomeUsuario, string $senha): int
    {
        $nomeUsuario = trim($nomeUsuario);

        if ($nomeUsuario === '' || $senha === '') {
            throw new InvalidArgumentException(
                'Preencha o nome de usuario e a senha.'
            );
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "
            INSERT INTO usuario (nome_usuario, senha_hash)
            VALUES (:nome_usuario, :senha_hash)
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':nome_usuario' => $nomeUsuario,
            ':senha_hash' => $senhaHash
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function buscarPorNome(string $nomeUsuario): ?Usuario
    {
        $sql = "
            SELECT *
            FROM usuario
            WHERE nome_usuario = :nome_usuario
        ";

        $consulta = $this->pdo->prepare($sql);

        $consulta->execute([
            ':nome_usuario' => trim($nomeUsuario)
        ]);

        $dados = $consulta->fetch();

        if (!$dados) {
            return null;
        }

        return new Usuario(
            (int) $dados['id'],
            $dados['nome_usuario'],
            $dados['senha_hash']
        );
    }

    public function autenticar(
        string $nomeUsuario,
        string $senha
    ): ?Usuario {
        $usuario = $this->buscarPorNome($nomeUsuario);

        if (!$usuario) {
            return null;
        }

        if (!password_verify($senha, $usuario->getSenhaHash())) {
            return null;
        }

        return $usuario;
    }
}