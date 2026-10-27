            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Evolucao nao permitida para este Pokemon';
        END IF;

        SET NEW.evoluiu = TRUE;
    END IF;
END//

CREATE TRIGGER trg_inicializa_npc_pokemon
BEFORE INSERT ON npc_pokemon
FOR EACH ROW
BEGIN
    DECLARE v_hp INT;

    SELECT hp_base
    INTO v_hp
    FROM pokemon
    WHERE id = NEW.pokemon_id;

    IF NEW.hp_maximo IS NULL THEN
        SET NEW.hp_maximo = v_hp;
    END IF;

    IF NEW.hp_atual IS NULL THEN
        SET NEW.hp_atual = NEW.hp_maximo;
    END IF;

    IF NEW.hp_atual < 0 OR NEW.hp_atual > NEW.hp_maximo THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'HP do NPC fora dos limites permitidos';
    END IF;
END//

CREATE TRIGGER trg_inicializa_batalha_pokemon
BEFORE INSERT ON batalha_pokemon
FOR EACH ROW
BEGIN
    DECLARE v_hp_atual INT;
    DECLARE v_hp_maximo INT;
    DECLARE v_status VARCHAR(20);

    IF NEW.pokemon_perfil_id IS NOT NULL THEN
        SELECT hp_atual, hp_maximo, status
        INTO v_hp_atual, v_hp_maximo, v_status
        FROM pokemon_perfil
        WHERE id = NEW.pokemon_perfil_id;
    ELSE
        SELECT hp_atual, hp_maximo, status
        INTO v_hp_atual, v_hp_maximo, v_status
        FROM npc_pokemon
        WHERE id = NEW.npc_pokemon_id;
    END IF;

    SET NEW.hp_atual = v_hp_atual;
    SET NEW.hp_maximo = v_hp_maximo;
    SET NEW.status = v_status;
END//

CREATE TRIGGER trg_define_recompensa
BEFORE UPDATE ON batalha
FOR EACH ROW
BEGIN
    IF OLD.resultado <> 'em_andamento' THEN
        IF NEW.resultado <> OLD.resultado THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Uma batalha finalizada nao pode ser alterada';
        END IF;

        SET NEW.recompensa = OLD.recompensa;
    ELSEIF NEW.resultado = 'vitoria' THEN
        SET NEW.recompensa = (
            SELECT recompensa
            FROM npc
            WHERE id = NEW.npc_id
        );
    ELSEIF NEW.resultado IN ('derrota', 'empate') THEN
        SET NEW.recompensa = 0;
    ELSE
        SET NEW.recompensa = 0;
    END IF;
END//

CREATE TRIGGER trg_recompensa_vitoria
AFTER UPDATE ON batalha
FOR EACH ROW
BEGIN
    IF OLD.resultado = 'em_andamento'
       AND NEW.resultado IN ('vitoria', 'derrota', 'empate') THEN

        UPDATE pokemon_perfil pp
        INNER JOIN batalha_pokemon bp
            ON bp.pokemon_perfil_id = pp.id
        SET
            pp.hp_atual = bp.hp_atual,
            pp.hp_maximo = bp.hp_maximo,
            pp.status = bp.status
        WHERE bp.batalha_id = NEW.id
          AND bp.pokemon_perfil_id IS NOT NULL;

        UPDATE npc_pokemon np
        INNER JOIN batalha_pokemon bp
            ON bp.npc_pokemon_id = np.id
        SET
            np.hp_atual = bp.hp_atual,
            np.hp_maximo = bp.hp_maximo,
            np.status = bp.status
        WHERE bp.batalha_id = NEW.id
          AND bp.npc_pokemon_id IS NOT NULL;

        IF NEW.resultado = 'vitoria' AND NEW.recompensa > 0 THEN
            UPDATE perfil
            SET saldo = saldo + NEW.recompensa
            WHERE id = NEW.perfil_id;
        END IF;
    END IF;
END//

DELIMITER ;
