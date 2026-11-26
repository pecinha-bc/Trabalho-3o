<?php

require_once __DIR__ . '/../includes/sessao.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../repositorios/jogadorrepositorio.php';

exigirLogin();

if (!isset($_SESSION['perfil_id'])) {
    header('Location: escolher_perfil.php');
    exit;
}

$perfilId = (int) $_SESSION['perfil_id'];
$usuarioId = (int) $_SESSION['usuario_id'];

$jogadorRepositorio = new JogadorRepositorio();
$perfil = $jogadorRepositorio->buscarPorId($perfilId);

if (!$perfil || $perfil->getUsuarioId() !== $usuarioId) {
    unset($_SESSION['perfil_id'], $_SESSION['nome_perfil']);
    header('Location: escolher_perfil.php');
    exit;
}

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$rotulosTipo = [
    'fogo' => 'Fogo',
    'agua' => 'Agua',
    'planta' => 'Planta',
    'eletrico' => 'Eletrico',
    'normal' => 'Normal'
];

try {
    $pdo = Conexao::conectar();

    
    $sql = "
        SELECT
            p.id, p.nome, p.tipo, p.hp_base,
            p.ataque, p.defesa, p.velocidade,
            e.nome AS evolucao_nome,
            origem.nome AS origem_nome
        FROM pokemon p
        LEFT JOIN pokemon e
            ON e.id = p.evolui_para_id
        LEFT JOIN pokemon origem
            ON origem.evolui_para_id = p.id
        ORDER BY
            FIELD(p.tipo, 'fogo', 'agua', 'planta', 'eletrico'),
            CASE WHEN p.evolui_para_id IS NULL THEN 1 ELSE 0 END,
            p.nome
    ";

    $pokemons = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    
    $sqlAtaques = "
        SELECT
            pa.pokemon_id,
            a.nome, a.tipo, a.poder_base,
            a.status_causado, a.chance_status
        FROM pokemon_ataque pa
        INNER JOIN ataque a ON a.id = pa.ataque_id
        ORDER BY pa.pokemon_id, pa.posicao
    ";

    $ataquesPorPokemon = [];

    foreach ($pdo->query($sqlAtaques)->fetchAll(PDO::FETCH_ASSOC) as $ataque) {
        $ataquesPorPokemon[(int) $ataque['pokemon_id']][] = $ataque;
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Nao foi possivel carregar a Pokedex. Verifique a conexao com o banco.');
}

?>
<?php require_once __DIR__ . '/../includes/layout.php'; mbAbrirPagina('Pokedex', 'Consulte as espécies, seus atributos, movimentos e evoluções.', $perfil, 'pokedex.php'); ?>

<div class="mb-pokedex-emblem"><img src="../assets/imagens/pokedex-icone.png" alt="Pokedex vermelha" width="136" height="105"></div>
<section class="mb-paper mb-ui-card mb-ui-filter" aria-label="Filtros da Pokedex">
 <div><label for="busca">Pesquisar espécie</label><input id="busca" type="search" autocomplete="off" placeholder="Ex.: Pikachu"></div>
 <div><label for="filtroTipo">Elemento</label><select id="filtroTipo"><option value="todos">Todos os elementos</option><option value="fogo">Fogo</option><option value="agua">Água</option><option value="planta">Planta</option><option value="eletrico">Elétrico</option></select></div>
</section>
<p class="mb-ui-note" id="contador" aria-live="polite" style="display:table;margin:0 0 20px"><?= count($pokemons) ?> Pokémon encontrados</p>
<div class="mb-ui-grid mb-ui-grid-4" id="grade">
<?php foreach ($pokemons as $pokemon): ?>
 <?php $tipo = $pokemon['tipo']; $ataques = $ataquesPorPokemon[(int) $pokemon['id']] ?? []; ?>
 <article class="mb-paper mb-ui-card mb-ui-poke-search-result" data-nome="<?= escapar(strtolower($pokemon['nome'])) ?>" data-tipo="<?= escapar($tipo) ?>">
  <div class="mb-ui-art mb-ui-art-small"><img src="<?= mbSeguro(mbImagemPokemon((string) $pokemon['nome'])) ?>" alt="<?= escapar($pokemon['nome']) ?>" loading="lazy" onerror="this.src='../assets/imagens/pokemon-indisponivel.svg';this.onerror=null"></div>
  <div class="mb-ui-pokemon-name"><h2 style="font-size:23px"><?= escapar($pokemon['nome']) ?></h2><small>#<?= (int) $pokemon['id'] ?></small></div>
  <span class="mb-ui-type mb-ui-type-<?= escapar($tipo) ?>"><?= escapar($rotulosTipo[$tipo] ?? ucfirst($tipo)) ?></span>
  <div class="mb-ui-stat-grid" style="margin-top:15px"><div class="mb-ui-stat-box"><span>HP base</span><strong><?= (int) $pokemon['hp_base'] ?></strong></div><div class="mb-ui-stat-box"><span>Ataque</span><strong><?= (int) $pokemon['ataque'] ?></strong></div><div class="mb-ui-stat-box"><span>Defesa</span><strong><?= (int) $pokemon['defesa'] ?></strong></div><div class="mb-ui-stat-box"><span>Velocidade</span><strong><?= (int) $pokemon['velocidade'] ?></strong></div></div>
  <p class="mb-ui-note" style="margin-top:14px"><?php if ($pokemon['evolucao_nome'] !== null): ?>Evolui para: <strong><?= escapar($pokemon['evolucao_nome']) ?></strong><?php elseif ($pokemon['origem_nome'] !== null): ?>Evolução de: <strong><?= escapar($pokemon['origem_nome']) ?></strong><?php else: ?>Sem evolução cadastrada.<?php endif; ?></p>
  <h3 style="font-size:16px;margin:18px 0 8px">Movimentos</h3>
  <?php if (!$ataques): ?><p class="mb-ui-muted">Sem ataques cadastrados.</p><?php endif; ?>
  <?php foreach ($ataques as $ataque): ?>
   <div class="mb-ui-attack"><strong><?= escapar($ataque['nome']) ?></strong><small><?= escapar($rotulosTipo[$ataque['tipo']] ?? $ataque['tipo']) ?> · Poder <?= (int) $ataque['poder_base'] ?></small>
   <?php if ($ataque['status_causado'] !== 'nenhum'): ?><small>Efeito: <?= escapar($ataque['status_causado']) ?> (<?= (int) $ataque['chance_status'] ?>%)</small><?php endif; ?></div>
  <?php endforeach; ?>
 </article>
<?php endforeach; ?>
</div>
<div class="mb-paper mb-ui-card mb-ui-empty" id="vazio" hidden>Nenhum Pokémon encontrado com esses filtros.</div>
<script>
(function () {
  'use strict';
  const busca = document.getElementById('busca');
  const filtro = document.getElementById('filtroTipo');
  const cards = Array.from(document.querySelectorAll('.mb-ui-poke-search-result'));
  const contador = document.getElementById('contador');
  const vazio = document.getElementById('vazio');
  function atualizar() {
    const nome = busca.value.trim().toLocaleLowerCase('pt-BR');
    const tipo = filtro.value;
    let total = 0;
    cards.forEach(card => {
      const visivel = card.dataset.nome.includes(nome) && (tipo === 'todos' || card.dataset.tipo === tipo);
      card.hidden = !visivel;
      if (visivel) total++;
    });
    contador.textContent = total + ' Pokémon encontrados';
    vazio.hidden = total !== 0;
  }
  busca.addEventListener('input', atualizar);
  filtro.addEventListener('change', atualizar);
  atualizar();
})();
</script>
<?php mbEncerrarPagina(); ?>
