
<?php

class Conexao
{
    private static ?PDO $pdo = null;

    public static function conectar(): PDO
    {
        if (self::$pdo === null) {
            $host = 'localhost';
            $banco = 'trabalho3oano';
            $usuario = 'root';
            $senha = '';

            try {
                self::$pdo = new PDO(
                    "mysql:host=$host;dbname=$banco;charset=utf8mb4",
                    $usuario,
                    $senha
                );

                self::$pdo->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

                self::$pdo->setAttribute(
                    PDO::ATTR_DEFAULT_FETCH_MODE,
                    PDO::FETCH_ASSOC
                );
            } catch (PDOException $e) {
                throw new RuntimeException(
                    'Nao foi possivel conectar ao banco de dados.',
                    0,
                    $e
                );
            }
        }

        return self::$pdo;
    }
}
