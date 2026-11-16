-- Modelo da ata completa da turma no Layout de documentos.
-- A grade (alunos x componentes) entra no bloco tabela coletiva.
-- Idempotente. Rollback: 2026_11_16_resultado_ata_completa_layout_rollback.sql

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'secretaria_modelos_documentos');

SET @sql := IF(@has = 0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'resultado_ata_completa',
     'Ata completa da turma',
     'Ata de resultados finais em tabela única. A grade de alunos fica no bloco Tabela coletiva. Placeholders: {{tabela_html}}, {{ano_letivo}}, {{turma_nome}}.',
     '',
     '<h1>Ata de resultados finais — {{ano_letivo}}</h1><p>{{etapa}} · {{serie}} · {{turma_nome}} · {{turno}}</p>{{tabela_html}}',
     '',
     'paisagem', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (
     SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_ata_completa'
   )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
