
USE trabalho3oano;

-- pokemons iniciais e suas evolucoes
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

-- relaciona cada pokemon com sua evolucao
UPDATE pokemon p JOIN pokemon e ON e.nome = 'Charmeleon'
SET p.evolui_para_id = e.id WHERE p.nome = 'Charmander';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Arcanine'
SET p.evolui_para_id = e.id WHERE p.nome = 'Growlithe';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Ninetales'
SET p.evolui_para_id = e.id WHERE p.nome = 'Vulpix';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Wartortle'
SET p.evolui_para_id = e.id WHERE p.nome = 'Squirtle';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Golduck'
SET p.evolui_para_id = e.id WHERE p.nome = 'Psyduck';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Poliwhirl'
SET p.evolui_para_id = e.id WHERE p.nome = 'Poliwag';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Ivysaur'
SET p.evolui_para_id = e.id WHERE p.nome = 'Bulbasaur';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Gloom'
SET p.evolui_para_id = e.id WHERE p.nome = 'Oddish';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Weepinbell'
SET p.evolui_para_id = e.id WHERE p.nome = 'Bellsprout';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Raichu'
SET p.evolui_para_id = e.id WHERE p.nome = 'Pikachu';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Magneton'
SET p.evolui_para_id = e.id WHERE p.nome = 'Magnemite';

UPDATE pokemon p JOIN pokemon e ON e.nome = 'Electivire'
SET p.evolui_para_id = e.id WHERE p.nome = 'Electabuzz';

-- ataques e associacoes: um neutro e dois elementais por especie
INSERT INTO ataque (nome, tipo, poder_base, status_causado, chance_status) VALUES
('Investida', 'normal', 8, 'nenhum', 0),
('Brasa', 'fogo', 13, 'queimadura', 30),
('Lanca-Chamas', 'fogo', 21, 'queimadura', 15),
('Presas de Fogo', 'fogo', 17, 'queimadura', 25),
('Giro de Fogo', 'fogo', 16, 'queimadura', 20),
('Jato d''Agua', 'agua', 20, 'nenhum', 0),
('Jato Gelado', 'agua', 13, 'congelamento', 15),
('Onda d''Agua', 'agua', 17, 'nenhum', 0),
('Rajada de Bolhas', 'agua', 18, 'nenhum', 0),
('Chicote de Vinha', 'planta', 19, 'nenhum', 0),
('Po Sonifero', 'planta', 9, 'sono', 20),
('Absorver', 'planta', 17, 'nenhum', 0),
('Esporos Toxicos', 'planta', 10, 'veneno', 30),
('Folha Navalha', 'planta', 21, 'nenhum', 0),
('Choque do Trovao', 'eletrico', 15, 'paralisia', 25),
('Raio', 'eletrico', 22, 'paralisia', 10),
('Onda Eletrica', 'eletrico', 10, 'paralisia', 40),
('Soco Trovao', 'eletrico', 19, 'paralisia', 20);

-- cada especie inicial conhece tres ataques
INSERT INTO pokemon_ataque (pokemon_id, ataque_id, posicao)
SELECT p.id, a.id, x.posicao
FROM (
    SELECT 'Charmander' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Charmander' AS pokemon, 'Brasa' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Charmander' AS pokemon, 'Lanca-Chamas' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Growlithe' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Growlithe' AS pokemon, 'Presas de Fogo' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Growlithe' AS pokemon, 'Lanca-Chamas' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Vulpix' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Vulpix' AS pokemon, 'Brasa' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Vulpix' AS pokemon, 'Giro de Fogo' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Squirtle' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Squirtle' AS pokemon, 'Jato d''Agua' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Squirtle' AS pokemon, 'Jato Gelado' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Psyduck' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Psyduck' AS pokemon, 'Onda d''Agua' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Psyduck' AS pokemon, 'Jato Gelado' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Poliwag' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Poliwag' AS pokemon, 'Rajada de Bolhas' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Poliwag' AS pokemon, 'Jato Gelado' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Bulbasaur' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Bulbasaur' AS pokemon, 'Chicote de Vinha' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Bulbasaur' AS pokemon, 'Po Sonifero' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Oddish' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Oddish' AS pokemon, 'Absorver' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Oddish' AS pokemon, 'Esporos Toxicos' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Bellsprout' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Bellsprout' AS pokemon, 'Folha Navalha' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Bellsprout' AS pokemon, 'Po Sonifero' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Pikachu' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Pikachu' AS pokemon, 'Choque do Trovao' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Pikachu' AS pokemon, 'Raio' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Magnemite' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Magnemite' AS pokemon, 'Choque do Trovao' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Magnemite' AS pokemon, 'Onda Eletrica' AS ataque, 3 AS posicao
UNION ALL
    SELECT 'Electabuzz' AS pokemon, 'Investida' AS ataque, 1 AS posicao
UNION ALL
    SELECT 'Electabuzz' AS pokemon, 'Soco Trovao' AS ataque, 2 AS posicao
UNION ALL
    SELECT 'Electabuzz' AS pokemon, 'Raio' AS ataque, 3 AS posicao
) x
JOIN pokemon p ON p.nome = x.pokemon
JOIN ataque a ON a.nome = x.ataque;

-- os evoluidos herdam os ataques da especie inicial
INSERT INTO pokemon_ataque (pokemon_id, ataque_id, posicao)
SELECT evolucao.id, pa.ataque_id, pa.posicao
FROM (
    SELECT 'Charmander' AS base, 'Charmeleon' AS evolucao
UNION ALL
    SELECT 'Growlithe' AS base, 'Arcanine' AS evolucao
UNION ALL
    SELECT 'Vulpix' AS base, 'Ninetales' AS evolucao
UNION ALL
    SELECT 'Squirtle' AS base, 'Wartortle' AS evolucao
UNION ALL
    SELECT 'Psyduck' AS base, 'Golduck' AS evolucao
UNION ALL
    SELECT 'Poliwag' AS base, 'Poliwhirl' AS evolucao
UNION ALL
    SELECT 'Bulbasaur' AS base, 'Ivysaur' AS evolucao
UNION ALL
    SELECT 'Oddish' AS base, 'Gloom' AS evolucao
UNION ALL
    SELECT 'Bellsprout' AS base, 'Weepinbell' AS evolucao
UNION ALL
    SELECT 'Pikachu' AS base, 'Raichu' AS evolucao
UNION ALL
    SELECT 'Magnemite' AS base, 'Magneton' AS evolucao
UNION ALL
    SELECT 'Electabuzz' AS base, 'Electivire' AS evolucao
) mapa
JOIN pokemon base ON base.nome = mapa.base
JOIN pokemon evolucao ON evolucao.nome = mapa.evolucao
JOIN pokemon_ataque pa ON pa.pokemon_id = base.id;

-- treinadores npc e recompensas (+150% sobre os valores iniciais)
INSERT INTO npc (nome, dificuldade, recompensa) VALUES
('Treinador iniciante', 'facil', 125),
('Treinador experiente', 'medio', 250),
('Treinador especialista', 'dificil', 500);

-- equipes: facil sem evolucao, medio com raichu e dificil evoluido
INSERT INTO npc_pokemon (npc_id, pokemon_id, posicao)
SELECT n.id, p.id, x.posicao
FROM (
    SELECT 'Treinador iniciante' AS treinador, 'Vulpix' AS pokemon, 1 AS posicao
UNION ALL
    SELECT 'Treinador iniciante' AS treinador, 'Oddish' AS pokemon, 2 AS posicao
UNION ALL
    SELECT 'Treinador iniciante' AS treinador, 'Poliwag' AS pokemon, 3 AS posicao
UNION ALL
    SELECT 'Treinador experiente' AS treinador, 'Growlithe' AS pokemon, 1 AS posicao
UNION ALL
    SELECT 'Treinador experiente' AS treinador, 'Squirtle' AS pokemon, 2 AS posicao
UNION ALL
    SELECT 'Treinador experiente' AS treinador, 'Raichu' AS pokemon, 3 AS posicao
UNION ALL
    SELECT 'Treinador especialista' AS treinador, 'Arcanine' AS pokemon, 1 AS posicao
UNION ALL
    SELECT 'Treinador especialista' AS treinador, 'Wartortle' AS pokemon, 2 AS posicao
UNION ALL
    SELECT 'Treinador especialista' AS treinador, 'Electivire' AS pokemon, 3 AS posicao
) x
JOIN npc n ON n.nome = x.treinador
JOIN pokemon p ON p.nome = x.pokemon;

-- itens da loja
-- valores de cura em percentual do hp maximo
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

-- os tres npc possuem acesso aos 12 itens de batalha, por combate
INSERT INTO inventario_npc (npc_id, item_id, quantidade)
SELECT n.id, i.id, CASE
    WHEN i.nome = 'Pocao' THEN 2
    WHEN i.nome = 'Reviver' AND n.dificuldade <> 'facil' THEN 2
    ELSE 1
END AS quantidade
FROM npc n
CROSS JOIN item i
WHERE i.tipo_efeito <> 'evolucao' AND i.aparece_inventario = TRUE;
