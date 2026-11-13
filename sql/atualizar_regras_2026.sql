USE trabalho3oano;

-- execute somente com backup e sem batalhas em andamento

-- novo valor das recompensas
UPDATE npc SET recompensa = CASE dificuldade WHEN 'facil' THEN 125 WHEN 'medio' THEN 250 WHEN 'dificil' THEN 500 END;

-- atualizar equipes de npc respeitando as posicoes existentes
UPDATE npc_pokemon np
JOIN npc n ON n.id = np.npc_id
JOIN (
    SELECT 'Treinador iniciante' AS treinador, 1 AS posicao, 'Vulpix' AS pokemon
    UNION ALL SELECT 'Treinador iniciante' AS treinador, 2 AS posicao, 'Oddish' AS pokemon
    UNION ALL SELECT 'Treinador iniciante' AS treinador, 3 AS posicao, 'Poliwag' AS pokemon
    UNION ALL SELECT 'Treinador experiente' AS treinador, 1 AS posicao, 'Growlithe' AS pokemon
    UNION ALL SELECT 'Treinador experiente' AS treinador, 2 AS posicao, 'Squirtle' AS pokemon
    UNION ALL SELECT 'Treinador experiente' AS treinador, 3 AS posicao, 'Raichu' AS pokemon
    UNION ALL SELECT 'Treinador especialista' AS treinador, 1 AS posicao, 'Arcanine' AS pokemon
    UNION ALL SELECT 'Treinador especialista' AS treinador, 2 AS posicao, 'Wartortle' AS pokemon
    UNION ALL SELECT 'Treinador especialista' AS treinador, 3 AS posicao, 'Electivire' AS pokemon
) x ON x.treinador = n.nome AND x.posicao = np.posicao
JOIN pokemon p ON p.nome = x.pokemon
SET np.pokemon_id = p.id, np.hp_maximo = p.hp_base,
    np.hp_atual = p.hp_base, np.status = 'nenhum';

-- inserir ou atualizar os ataques por nome
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
('Soco Trovao', 'eletrico', 19, 'paralisia', 20)
ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), poder_base = VALUES(poder_base),
    status_causado = VALUES(status_causado), chance_status = VALUES(chance_status);

-- substituir somente as associacoes das 24 especies do jogo
DELETE FROM pokemon_ataque WHERE pokemon_id IN (
    SELECT id FROM pokemon WHERE nome IN (
        'Charmander', 'Growlithe', 'Vulpix', 'Squirtle', 'Psyduck', 'Poliwag', 'Bulbasaur', 'Oddish', 'Bellsprout', 'Pikachu', 'Magnemite', 'Electabuzz', 'Charmeleon', 'Arcanine', 'Ninetales', 'Wartortle', 'Golduck', 'Poliwhirl', 'Ivysaur', 'Gloom', 'Weepinbell', 'Raichu', 'Magneton', 'Electivire'
    )
);

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

-- renovar o estoque padrao dos npc, que o motor copia por batalha
DELETE FROM inventario_npc WHERE npc_id IN (SELECT id FROM npc WHERE dificuldade IN ('facil','medio','dificil'));
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

-- tabela para controlar o kit inicial e impedir entregas duplicadas
CREATE TABLE IF NOT EXISTS kit_inicial_entregue (
    perfil_id INT PRIMARY KEY,
    data_entrega TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (perfil_id) REFERENCES perfil(id)
);

-- atualizar o gatilho de finalizacao para curar os dois lados
DROP TRIGGER IF EXISTS trg_recompensa_vitoria;
DELIMITER //
CREATE TRIGGER trg_recompensa_vitoria
AFTER UPDATE ON batalha
FOR EACH ROW
BEGIN
    IF OLD.resultado = 'em_andamento' AND NEW.resultado IN ('vitoria', 'derrota', 'empate') THEN
        UPDATE pokemon_perfil pp
        INNER JOIN batalha_pokemon bp ON bp.pokemon_perfil_id = pp.id
        SET pp.hp_atual = pp.hp_maximo, pp.status = 'nenhum'
        WHERE bp.batalha_id = NEW.id AND bp.pokemon_perfil_id IS NOT NULL;

        UPDATE npc_pokemon np
        INNER JOIN batalha_pokemon bp ON bp.npc_pokemon_id = np.id
        SET np.hp_atual = np.hp_maximo, np.status = 'nenhum'
        WHERE bp.batalha_id = NEW.id AND bp.npc_pokemon_id IS NOT NULL;

        IF NEW.resultado = 'vitoria' AND NEW.recompensa > 0 THEN
            UPDATE perfil SET saldo = saldo + NEW.recompensa
            WHERE id = NEW.perfil_id;
        END IF;
    END IF;
END//
DELIMITER ;
