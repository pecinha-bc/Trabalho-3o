<?php

require_once __DIR__ . '/conexao.php';

try {
    Conexao::conectar();
    echo 'Conexao com o banco de dados realizada com sucesso!';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Falha ao conectar ao banco de dados.';
}
