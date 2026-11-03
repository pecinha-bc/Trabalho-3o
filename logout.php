
<?php

require_once __DIR__ . '/../includes/sessao.php';

encerrarSessao();

header('Location: login.php');
exit;
