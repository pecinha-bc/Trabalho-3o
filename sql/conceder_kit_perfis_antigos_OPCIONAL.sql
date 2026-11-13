USE trabalho3oano;
-- opcional: executar apenas apos atualizar_regras_2026.sql
-- concede o kit a perfis antigos uma unica vez
START TRANSACTION;
CREATE TEMPORARY TABLE perfis_sem_kit AS
SELECT p.id AS perfil_id
FROM perfil p LEFT JOIN kit_inicial_entregue k ON k.perfil_id = p.id
WHERE k.perfil_id IS NULL;
UPDATE perfil p INNER JOIN perfis_sem_kit t ON t.perfil_id = p.id
SET p.saldo = p.saldo + 100;
INSERT INTO inventario (perfil_id, item_id, quantidade)
SELECT t.perfil_id, i.id, IF(i.nome = 'Pocao', 2, 1)
FROM perfis_sem_kit t
JOIN item i ON i.nome IN ('Pocao', 'Antidoto')
ON DUPLICATE KEY UPDATE quantidade = quantidade + VALUES(quantidade);
INSERT INTO kit_inicial_entregue (perfil_id)
SELECT perfil_id FROM perfis_sem_kit;
DROP TEMPORARY TABLE perfis_sem_kit;
COMMIT;
