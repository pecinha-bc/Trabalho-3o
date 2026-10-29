CREATE DATABASE IF NOT EXISTS trabalho3oano
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE trabalho3oano;

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS vw_historico_batalhas;
DROP VIEW IF EXISTS vw_saldo_perfis;
DROP VIEW IF EXISTS vw_loja_itens;

DROP TRIGGER IF EXISTS trg_recompensa_vitoria;
DROP TRIGGER IF EXISTS trg_define_recompensa;
DROP TRIGGER IF EXISTS trg_inicializa_pokemon_perfil;
DROP TRIGGER IF EXISTS trg_valida_hp_pokemon_perfil;
DROP TRIGGER IF EXISTS trg_inicializa_npc_pokemon;
DROP TRIGGER IF EXISTS trg_inicializa_batalha_pokemon;

DROP TABLE IF EXISTS batalha_evento;
DROP TABLE IF EXISTS batalha_pokemon;
DROP TABLE IF EXISTS batalha;
DROP TABLE IF EXISTS pokemon_ataque;
DROP TABLE IF EXISTS ataque;
DROP TABLE IF EXISTS inventario_npc;
DROP TABLE IF EXISTS inventario;
DROP TABLE IF EXISTS item;
DROP TABLE IF EXISTS npc_pokemon;
DROP TABLE IF EXISTS pokemon_perfil;
DROP TABLE IF EXISTS npc;
DROP TABLE IF EXISTS perfil;
DROP TABLE IF EXISTS pokemon;
DROP TABLE IF EXISTS usuario;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_usuario VARCHAR(50) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE perfil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(50) NOT NULL,
    saldo INT NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuario(id),
    UNIQUE (usuario_id, nome),
    CHECK (saldo >= 0)
);

CREATE TABLE pokemon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    tipo ENUM('fogo', 'agua', 'planta', 'eletrico') NOT NULL,
    hp_base INT NOT NULL,
    ataque INT NOT NULL,
    defesa INT NOT NULL,
    velocidade INT NOT NULL,
    evolui_para_id INT NULL,
    FOREIGN KEY (evolui_para_id) REFERENCES pokemon(id),
    CHECK (hp_base > 0),
    CHECK (ataque > 0),
    CHECK (defesa > 0),
    CHECK (velocidade > 0),
    CHECK (evolui_para_id IS NULL OR evolui_para_id <> id)
);

CREATE TABLE pokemon_perfil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    perfil_id INT NOT NULL,
    pokemon_id INT NOT NULL,
    hp_atual INT NULL,
    hp_maximo INT NULL,
    ataque INT NULL,
    defesa INT NULL,
    velocidade INT NULL,
    nivel INT NOT NULL DEFAULT 1,
    experiencia INT NOT NULL DEFAULT 0,
    evoluiu BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM(
        'nenhum',
        'queimadura',
        'veneno',
        'paralisia',
        'congelamento',
        'sono'
    ) NOT NULL DEFAULT 'nenhum',
    FOREIGN KEY (perfil_id) REFERENCES perfil(id),
    FOREIGN KEY (pokemon_id) REFERENCES pokemon(id),
    CHECK (nivel > 0),
    CHECK (experiencia >= 0),
    CHECK (hp_atual IS NULL OR hp_atual >= 0),
    CHECK (hp_maximo IS NULL OR hp_maximo > 0),
    CHECK (ataque IS NULL OR ataque > 0),
    CHECK (defesa IS NULL OR defesa > 0),
    CHECK (velocidade IS NULL OR velocidade > 0),
    CHECK (hp_atual IS NULL OR hp_maximo IS NULL OR hp_atual <= hp_maximo)
);

CREATE TABLE npc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    dificuldade ENUM('facil', 'medio', 'dificil') NOT NULL UNIQUE,
    recompensa INT NOT NULL,
    CHECK (recompensa >= 0)
);

CREATE TABLE npc_pokemon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npc_id INT NOT NULL,
    pokemon_id INT NOT NULL,
    posicao TINYINT NOT NULL,
    hp_atual INT NULL,
    hp_maximo INT NULL,
    status ENUM(
        'nenhum',
        'queimadura',
        'veneno',
        'paralisia',
        'congelamento',
        'sono'
    ) NOT NULL DEFAULT 'nenhum',
    FOREIGN KEY (npc_id) REFERENCES npc(id),
    FOREIGN KEY (pokemon_id) REFERENCES pokemon(id),
    UNIQUE (npc_id, posicao),
    CHECK (posicao BETWEEN 1 AND 3),
    CHECK (hp_atual IS NULL OR hp_atual >= 0),
    CHECK (hp_maximo IS NULL OR hp_maximo > 0),
    CHECK (hp_atual IS NULL OR hp_maximo IS NULL OR hp_atual <= hp_maximo)
);

CREATE TABLE item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    preco INT NOT NULL,
    tipo_efeito ENUM(
        'cura_hp',
        'cura_total',
        'cura_status',
        'restauracao_total',
        'reviver',
        'evolucao'
    ) NOT NULL,
    valor_efeito INT NOT NULL DEFAULT 0,
    status_removido ENUM(
        'nenhum',
        'queimadura',
        'veneno',
        'paralisia',
        'congelamento',
        'sono',
        'todos'
    ) NOT NULL DEFAULT 'nenhum',
    aparece_loja BOOLEAN NOT NULL DEFAULT TRUE,
    aparece_inventario BOOLEAN NOT NULL DEFAULT TRUE,
    CHECK (preco >= 0),
    CHECK (valor_efeito >= 0)
);

CREATE TABLE inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    perfil_id INT NOT NULL,
    item_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    FOREIGN KEY (perfil_id) REFERENCES perfil(id),
    FOREIGN KEY (item_id) REFERENCES item(id),
    UNIQUE (perfil_id, item_id),
    CHECK (quantidade >= 0)
);

CREATE TABLE inventario_npc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npc_id INT NOT NULL,
    item_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    FOREIGN KEY (npc_id) REFERENCES npc(id),
    FOREIGN KEY (item_id) REFERENCES item(id),
    UNIQUE (npc_id, item_id),
    CHECK (quantidade >= 0)
);

CREATE TABLE ataque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(60) NOT NULL UNIQUE,
    tipo ENUM('normal', 'fogo', 'agua', 'planta', 'eletrico') NOT NULL,
    poder_base INT NOT NULL,
    status_causado ENUM(
        'nenhum',
        'queimadura',
        'veneno',
        'paralisia',
        'congelamento',
        'sono'
    ) NOT NULL DEFAULT 'nenhum',
    chance_status TINYINT NOT NULL DEFAULT 0,
    CHECK (poder_base > 0),
    CHECK (chance_status BETWEEN 0 AND 100)
);

CREATE TABLE pokemon_ataque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pokemon_id INT NOT NULL,
    ataque_id INT NOT NULL,
    posicao TINYINT NOT NULL,
    FOREIGN KEY (pokemon_id) REFERENCES pokemon(id),
    FOREIGN KEY (ataque_id) REFERENCES ataque(id),
    UNIQUE (pokemon_id, posicao),
    UNIQUE (pokemon_id, ataque_id),
    CHECK (posicao BETWEEN 1 AND 3)
);

CREATE TABLE batalha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    perfil_id INT NOT NULL,
    npc_id INT NOT NULL,
    resultado ENUM('em_andamento', 'vitoria', 'derrota', 'empate')
        NOT NULL DEFAULT 'em_andamento',
    recompensa INT NOT NULL DEFAULT 0,
    log LONGTEXT NULL,
    data_batalha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (perfil_id) REFERENCES perfil(id),
    FOREIGN KEY (npc_id) REFERENCES npc(id),
    CHECK (recompensa >= 0)
);

CREATE TABLE batalha_pokemon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batalha_id INT NOT NULL,
    pokemon_perfil_id INT NULL,
    npc_pokemon_id INT NULL,
    hp_atual INT NULL,
    hp_maximo INT NULL,
    status ENUM(
        'nenhum',
        'queimadura',
        'veneno',
        'paralisia',
        'congelamento',
        'sono'
    ) NOT NULL DEFAULT 'nenhum',
    FOREIGN KEY (batalha_id) REFERENCES batalha(id),
    FOREIGN KEY (pokemon_perfil_id) REFERENCES pokemon_perfil(id),
    FOREIGN KEY (npc_pokemon_id) REFERENCES npc_pokemon(id),
    CHECK (
        (pokemon_perfil_id IS NOT NULL AND npc_pokemon_id IS NULL)
        OR
        (pokemon_perfil_id IS NULL AND npc_pokemon_id IS NOT NULL)
    ),
    CHECK (hp_atual IS NULL OR hp_atual >= 0),
    CHECK (hp_maximo IS NULL OR hp_maximo > 0),
    CHECK (hp_atual IS NULL OR hp_maximo IS NULL OR hp_atual <= hp_maximo),
    UNIQUE (batalha_id, pokemon_perfil_id),
    UNIQUE (batalha_id, npc_pokemon_id)
);

CREATE TABLE batalha_evento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batalha_id INT NOT NULL,
    batalha_pokemon_id INT NULL,
    ataque_id INT NULL,
    item_id INT NULL,
    tipo_evento ENUM(
        'ataque',
        'item',
        'dano',
        'status',
        'derrota',
        'cura',
        'informacao'
    ) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    data_evento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batalha_id) REFERENCES batalha(id),
    FOREIGN KEY (batalha_pokemon_id) REFERENCES batalha_pokemon(id),
    FOREIGN KEY (ataque_id) REFERENCES ataque(id),
    FOREIGN KEY (item_id) REFERENCES item(id)
);

CREATE OR REPLACE VIEW vw_historico_batalhas AS
SELECT
    b.id AS batalha_id,
    p.nome AS treinador,
    n.nome AS npc,
    n.dificuldade,
    b.resultado,
    b.recompensa,
    b.data_batalha
FROM batalha b
INNER JOIN perfil p ON b.perfil_id = p.id
INNER JOIN npc n ON b.npc_id = n.id;

CREATE OR REPLACE VIEW vw_saldo_perfis AS
SELECT
    p.id AS perfil_id,
    p.nome AS treinador,
    u.nome_usuario,
    p.saldo
FROM perfil p
INNER JOIN usuario u ON p.usuario_id = u.id;

CREATE OR REPLACE VIEW vw_loja_itens AS
SELECT
    id,
    nome,
    preco,
    tipo_efeito,
    valor_efeito,
    status_removido
FROM item
WHERE aparece_loja = TRUE;

DELIMITER //

CREATE TRIGGER trg_inicializa_pokemon_perfil
BEFORE INSERT ON pokemon_perfil
FOR EACH ROW
BEGIN
    DECLARE v_hp INT;
    DECLARE v_ataque INT;
    DECLARE v_defesa INT;
    DECLARE v_velocidade INT;

    SELECT hp_base, ataque, defesa, velocidade
    INTO v_hp, v_ataque, v_defesa, v_velocidade
    FROM pokemon
    WHERE id = NEW.pokemon_id;

    IF NEW.hp_maximo IS NULL THEN
        SET NEW.hp_maximo = v_hp;
    END IF;

    IF NEW.hp_atual IS NULL THEN
        SET NEW.hp_atual = NEW.hp_maximo;
    END IF;

    IF NEW.ataque IS NULL THEN
        SET NEW.ataque = v_ataque;
    END IF;

    IF NEW.defesa IS NULL THEN
        SET NEW.defesa = v_defesa;
    END IF;

    IF NEW.velocidade IS NULL THEN
        SET NEW.velocidade = v_velocidade;
    END IF;

    IF NEW.hp_atual < 0 OR NEW.hp_atual > NEW.hp_maximo THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'HP do Pokemon fora dos limites permitidos';
    END IF;
END//

CREATE TRIGGER trg_valida_hp_pokemon_perfil
BEFORE UPDATE ON pokemon_perfil
FOR EACH ROW
BEGIN
    DECLARE v_evolucao_permitida INT;

    IF NEW.hp_atual IS NULL
       OR NEW.hp_maximo IS NULL
       OR NEW.hp_atual < 0
       OR NEW.hp_maximo <= 0
       OR NEW.hp_atual > NEW.hp_maximo THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'HP do Pokemon fora dos limites permitidos';
    END IF;

    IF NEW.pokemon_id <> OLD.pokemon_id THEN
        IF OLD.evoluiu = TRUE THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Este Pokemon ja evoluiu uma vez';
        END IF;

        SELECT evolui_para_id
        INTO v_evolucao_permitida
        FROM pokemon
        WHERE id = OLD.pokemon_id;

        IF v_evolucao_permitida IS NULL
           OR NEW.pokemon_id <> v_evolucao_permitida THEN
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
