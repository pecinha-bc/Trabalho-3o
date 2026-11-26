<?php
require_once __DIR__ . '/itens.php';

function mbSeguro($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
function mbImagemPokemon(string $nome): string {
    $mapa = [
        'Charmander'=>4, 'Charmeleon'=>5, 'Growlithe'=>58, 'Arcanine'=>59,
        'Vulpix'=>37, 'Ninetales'=>38, 'Squirtle'=>7, 'Wartortle'=>8,
        'Psyduck'=>54, 'Golduck'=>55, 'Poliwag'=>60, 'Poliwhirl'=>61,
        'Bulbasaur'=>1, 'Ivysaur'=>2, 'Oddish'=>43, 'Gloom'=>44,
        'Bellsprout'=>69, 'Weepinbell'=>70, 'Pikachu'=>25, 'Raichu'=>26,
        'Magnemite'=>81, 'Magneton'=>82, 'Electabuzz'=>125, 'Electivire'=>466,
    ];
    $n = $mapa[$nome] ?? 0;
    if ($n === 0) return '../assets/imagens/pokemon-indisponivel.svg';
    
    return 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/' . $n . '.png';
}
function mbAbrirPagina(string $titulo, string $descricao, $perfil = null, string $pagina = ''): void {
    $tituloHtml = mbSeguro($titulo);
    $descricaoHtml = mbSeguro($descricao);
    $nome = $perfil ? mbSeguro($perfil->getNome()) : mbSeguro($_SESSION['nome_usuario'] ?? 'Treinador');
    $saldo = $perfil ? (int) $perfil->getSaldo() : null;
    $navegacao = [
        'inicio.php'=>'Início', 'equipe.php'=>'Equipe', 'batalhas.php'=>'Batalhas',
        'loja.php'=>'Loja', 'inventario.php'=>'Inventário',
        'evolucao.php'=>'Evolução', 'pokedex.php'=>'Pokedex'
    ];
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="theme-color" content="#304d52">';
    echo '<title>'. $tituloHtml .' — MonstroBolso</title>';
    echo '<link rel="stylesheet" href="../assets/css/monstrobolso.css?v=2">';
    echo '<link rel="stylesheet" href="../assets/css/jornada.css?v=2">';
    echo '<link rel="stylesheet" href="../assets/css/tema-final.css?v=18">';
    if ($pagina === 'batalhas.php') echo '<link rel="stylesheet" href="../assets/css/batalhas-final.css?v=1">';
    $cena = ['loja.php'=>'mercado','inventario.php'=>'inventario','evolucao.php'=>'santuario','batalhas.php'=>'arena','combate.php'=>'arena','pokedex.php'=>'pokedex','equipe.php'=>'equipe'][$pagina] ?? 'equipa';
    echo '</head><body class="mb-body mb-world-page mb-scene-'. $cena .'"><div class="mb-shell mb-world-shell">';
    echo '<header class="mb-header mb-world-header">';
    echo '<a href="inicio.php" class="mb-brand"><span class="mb-brand-mark" aria-hidden="true">●</span><span class="mb-brand-text">MonstroBolso</span></a>';
    echo '<div class="mb-top-actions"><span class="mb-pill">Treinador: '. $nome .'</span>';
    if ($saldo !== null) echo '<span class="mb-pill">✦ '. $saldo .' Pokédólares</span>';
    echo '<a class="mb-pill" href="escolher_perfil.php">Trocar perfil</a></div></header>';
    if ($perfil) {
        echo '<nav class="mb-world-nav" aria-label="Navegação principal">';
        foreach ($navegacao as $url=>$rotulo) {
            $ativo = ($pagina === $url) ? ' aria-current="page" class="is-active"' : '';
            echo '<a href="'. $url .'"'. $ativo .'>'. $rotulo .'</a>';
        }
        echo '</nav>';
    }
    echo '<main class="mb-world-main"><div class="mb-world-intro">';
    echo '<span class="mb-world-eyebrow">DIÁRIO DE AVENTURA · MONSTROBOLSO</span>';
    echo '<h1 class="mb-heading">'. $tituloHtml .'</h1>';
    echo '<p class="mb-description">'. $descricaoHtml .'</p></div>';
}
function mbEncerrarPagina(): void {
    echo '</main><footer class="mb-world-footer">';

    echo '<a href="inicio.php">Voltar ao refúgio</a></footer></div></body></html>';
}
