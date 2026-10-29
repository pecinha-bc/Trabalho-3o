
<?php
require_once __DIR__ . '/app/conexao.php';

try {
    echo "Arquivo de conexao carregado com sucesso!";
} catch (Throwable $e) {
    echo "Erro na conexao: " . $e->getMessage();
}
?>
