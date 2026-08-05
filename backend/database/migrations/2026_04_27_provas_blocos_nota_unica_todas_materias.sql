-- Adiciona configuração para replicar a mesma nota do aluno em todas as matérias do evento
-- Uso típico: ENAC
--
-- Compatível com MySQL 5.7+: não usa "ADD COLUMN IF NOT EXISTS" (só a partir do MySQL 8.0.12;
-- em 5.7/alguns hosts gera erro 1064). Se a coluna já existir, o ALTER falha com "Duplicate column";
-- nesse caso a migração já está aplicada e pode ser ignorada/marcada como ok.

ALTER TABLE provas_blocos
    ADD COLUMN nota_unica_todas_materias TINYINT(1) NOT NULL DEFAULT 0
    AFTER configuracao_nota;
