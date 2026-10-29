USE trabalho3oano;

-- Pokemons iniciais e suas evolucoes
INSERT INTO pokemon (nome, tipo, hp_base, ataque, defesa, velocidade, evolui_para_id) VALUES
('Charmander', 'fogo', 100, 52, 43, 65, NULL),
('Growlithe', 'fogo', 120, 70, 45, 60, NULL),
('Vulpix', 'fogo', 100, 41, 40, 65, NULL),
('Squirtle', 'agua', 120, 48, 65, 43, NULL),
('Psyduck', 'agua', 110, 52, 48, 55, NULL),
('Poliwag', 'agua', 100, 50, 40, 90, NULL),
('Bulbasaur', 'planta', 120, 49, 49, 45, NULL),
('Oddish', 'planta', 100, 50, 55, 30, NULL),
('Bellsprout', 'planta', 110, 75, 35, 40, NULL),
('Pikachu', 'eletrico', 100, 55, 40, 90, NULL),
('Magnemite', 'eletrico', 100, 35, 70, 45, NULL),
('Electabuzz', 'eletrico', 130, 83, 57, 105, NULL),
('Charmeleon', 'fogo', 130, 68, 58, 80, NULL),
('Arcanine', 'fogo', 156, 91, 59, 78, NULL),
('Ninetales', 'fogo', 130, 53, 52, 85, NULL),
('Wartortle', 'agua', 156, 62, 85, 56, NULL),
('Golduck', 'agua', 143, 68, 62, 72, NULL),
('Poliwhirl', 'agua', 130, 65, 52, 117, NULL),
('Ivysaur', 'planta', 156, 64, 64, 59, NULL),
('Gloom', 'planta', 130, 65, 72, 39, NULL),
('Weepinbell', 'planta', 143, 98, 46, 52, NULL),
('Raichu', 'eletrico', 130, 72, 52, 117, NULL),
('Magneton', 'eletrico', 130, 46, 91, 59, NULL),
('Electivire', 'eletrico', 169, 108, 74, 137, NULL);

-- Relaciona cada especie inicial com sua evolucao permitida
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Charmeleon' SET p.evolui_para_id = e.id WHERE p.nome = 'Charmander';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Arcanine' SET p.evolui_para_id = e.id WHERE p.nome = 'Growlithe';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Ninetales' SET p.evolui_para_id = e.id WHERE p.nome = 'Vulpix';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Wartortle' SET p.evolui_para_id = e.id WHERE p.nome = 'Squirtle';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Golduck' SET p.evolui_para_id = e.id WHERE p.nome = 'Psyduck';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Poliwhirl' SET p.evolui_para_id = e.id WHERE p.nome = 'Poliwag';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Ivysaur' SET p.evolui_para_id = e.id WHERE p.nome = 'Bulbasaur';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Gloom' SET p.evolui_para_id = e.id WHERE p.nome = 'Oddish';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Weepinbell' SET p.evolui_para_id = e.id WHERE p.nome = 'Bellsprout';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Raichu' SET p.evolui_para_id = e.id WHERE p.nome = 'Pikachu';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Magneton' SET p.evolui_para_id = e.id WHERE p.nome = 'Magnemite';
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Electivire' SET p.evolui_para_id = e.id WHERE p.nome = 'Electabuzz';

-- Ataques comuns e especiais
INSERT INTO ataque (nome, tipo, poder_base, status_causado, chance_status) VALUES
('Arranhao', 'normal', 8, 'nenhum', 0),
('Mordida', 'normal', 10, 'nenhum', 0),
('Brasa', 'fogo', 15, 'queimadura', 20),
('Investida', 'normal', 8, 'nenhum', 0),
('Lanca-Chamas', 'fogo', 15, 'queimadura', 30),
('Jato d''Agua', 'agua', 15, 'nenhum', 0),
('Cabecada', 'normal', 10, 'nenhum', 0),
('Pancada', 'normal', 10, 'nenhum', 0),
('Folha Navalha', 'planta', 15, 'nenhum', 0),
('Chicote', 'normal', 8, 'nenhum', 0),
('Choque do Trovao', 'eletrico', 15, 'paralisia', 20);

-- Vincula tres ataques a cada especie; evolucoes mantem o conjunto da especie inicial
INSERT INTO pokemon_ataque (pokemon_id, ataque_id, posicao)
SELECT p.id, a.id, x.posicao
FROM (
    SELECT 'Charmander' pokemon, 'Arranhao' ataque, 1 posicao UNION ALL
    SELECT 'Charmander', 'Mordida', 2 UNION ALL SELECT 'Charmander', 'Brasa', 3 UNION ALL
    SELECT 'Growlithe', 'Investida', 1 UNION ALL SELECT 'Growlithe', 'Mordida', 2 UNION ALL SELECT 'Growlithe', 'Lanca-Chamas', 3 UNION ALL
    SELECT 'Vulpix', 'Investida', 1 UNION ALL SELECT 'Vulpix', 'Arranhao', 2 UNION ALL SELECT 'Vulpix', 'Brasa', 3 UNION ALL
    SELECT 'Squirtle', 'Investida', 1 UNION ALL SELECT 'Squirtle', 'Mordida', 2 UNION ALL SELECT 'Squirtle', 'Jato d''Agua', 3 UNION ALL
    SELECT 'Psyduck', 'Arranhao', 1 UNION ALL SELECT 'Psyduck', 'Cabecada', 2 UNION ALL SELECT 'Psyduck', 'Jato d''Agua', 3 UNION ALL
    SELECT 'Poliwag', 'Investida', 1 UNION ALL SELECT 'Poliwag', 'Pancada', 2 UNION ALL SELECT 'Poliwag', 'Jato d''Agua', 3 UNION ALL
    SELECT 'Bulbasaur', 'Investida', 1 UNION ALL SELECT 'Bulbasaur', 'Pancada', 2 UNION ALL SELECT 'Bulbasaur', 'Folha Navalha', 3 UNION ALL
    SELECT 'Oddish', 'Arranhao', 1 UNION ALL SELECT 'Oddish', 'Pancada', 2 UNION ALL SELECT 'Oddish', 'Folha Navalha', 3 UNION ALL
    SELECT 'Bellsprout', 'Chicote', 1 UNION ALL SELECT 'Bellsprout', 'Pancada', 2 UNION ALL SELECT 'Bellsprout', 'Folha Navalha', 3 UNION ALL
    SELECT 'Pikachu', 'Investida', 1 UNION ALL SELECT 'Pikachu', 'Cabecada', 2 UNION ALL SELECT 'Pikachu', 'Choque do Trovao', 3 UNION ALL
    SELECT 'Magnemite', 'Investida', 1 UNION ALL SELECT 'Magnemite', 'Pancada', 2 UNION ALL SELECT 'Magnemite', 'Choque do Trovao', 3 UNION ALL
    SELECT 'Electabuzz', 'Pancada', 1 UNION ALL SELECT 'Electabuzz', 'Investida', 2 UNION ALL SELECT 'Electabuzz', 'Choque do Trovao', 3
) x
JOIN pokemon p ON p.nome = x.pokemon
JOIN ataque a ON a.nome = x.ataque;

-- As evolucoes usam os mesmos tres ataques da especie anterior
INSERT INTO pokemon_ataque (pokemon_id, ataque_id, posicao)
SELECT evolucao.id, pa.ataque_id, pa.posicao
FROM (
    SELECT 'Charmander' base, 'Charmeleon' evolucao UNION ALL
    SELECT 'Growlithe', 'Arcanine' UNION ALL
    SELECT 'Vulpix', 'Ninetales' UNION ALL
    SELECT 'Squirtle', 'Wartortle' UNION ALL
    SELECT 'Psyduck', 'Golduck' UNION ALL
    SELECT 'Poliwag', 'Poliwhirl' UNION ALL
    SELECT 'Bulbasaur', 'Ivysaur' UNION ALL
    SELECT 'Oddish', 'Gloom' UNION ALL
    SELECT 'Bellsprout', 'Weepinbell' UNION ALL
    SELECT 'Pikachu', 'Raichu' UNION ALL
    SELECT 'Magnemite', 'Magneton' UNION ALL
    SELECT 'Electabuzz', 'Electivire'
) mapa
JOIN pokemon base ON base.nome = mapa.base
JOIN pokemon evolucao ON evolucao.nome = mapa.evolucao
JOIN pokemon_ataque pa ON pa.pokemon_id = base.id;

-- Treinadores NPC e recompensas
INSERT INTO npc (nome, dificuldade, recompensa) VALUES
('Treinador iniciante', 'facil', 50),
('Treinador experiente', 'medio', 100),
('Treinador especialista', 'dificil', 200);

INSERT INTO npc_pokemon (npc_id, pokemon_id, posicao)
SELECT n.id, p.id, x.posicao
FROM (
    SELECT 'Treinador iniciante' treinador, 'Charmander' pokemon, 1 posicao UNION ALL
    SELECT 'Treinador iniciante', 'Oddish', 2 UNION ALL
    SELECT 'Treinador iniciante', 'Poliwag', 3 UNION ALL
    SELECT 'Treinador experiente', 'Growlithe', 1 UNION ALL
    SELECT 'Treinador experiente', 'Squirtle', 2 UNION ALL
    SELECT 'Treinador experiente', 'Pikachu', 3 UNION ALL
    SELECT 'Treinador especialista', 'Electabuzz', 1 UNION ALL
    SELECT 'Treinador especialista', 'Bellsprout', 2 UNION ALL
    SELECT 'Treinador especialista', 'Magnemite', 3
) x
JOIN npc n ON n.nome = x.treinador
JOIN pokemon p ON p.nome = x.pokemon;

-- Itens da loja: valores de efeito em percentual quando aplicavel
INSERT INTO item (nome, preco, tipo_efeito, valor_efeito, status_removido, aparece_loja, aparece_inventario) VALUES
('Pocao', 30, 'cura_hp', 20, 'nenhum', TRUE, TRUE),
('Super Pocao', 60, 'cura_hp', 40, 'nenhum', TRUE, TRUE),
('Hiper Pocao', 100, 'cura_hp', 60, 'nenhum', TRUE, TRUE),
('Pocao Maxima', 150, 'cura_hp', 100, 'nenhum', TRUE, TRUE),
('Restauracao Total', 250, 'restauracao_total', 100, 'todos', TRUE, TRUE),
('Reviver', 120, 'reviver', 50, 'nenhum', TRUE, TRUE),
('Antidoto', 25, 'cura_status', 0, 'veneno', TRUE, TRUE),
('Burn Heal', 25, 'cura_status', 0, 'queimadura', TRUE, TRUE),
('Ice Heal', 25, 'cura_status', 0, 'congelamento', TRUE, TRUE),
('Awakening', 25, 'cura_status', 0, 'sono', TRUE, TRUE),
('Paralyze Heal', 25, 'cura_status', 0, 'paralisia', TRUE, TRUE),
('Full Heal', 80, 'cura_status', 0, 'todos', TRUE, TRUE),
('Item de Evolucao', 200, 'evolucao', 1, 'nenhum', TRUE, FALSE);

-- Inventario de NPC inicia vazio; o jogador recebe itens ao compra-los na loja.
