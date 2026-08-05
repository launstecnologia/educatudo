ALTER TABLE `provas_blocos`
ADD COLUMN `gabarito_liberado` TINYINT(1) DEFAULT 0
COMMENT '0 = gabarito bloqueado para alunos, 1 = gabarito liberado pela coordenação'
AFTER `liberado`;
