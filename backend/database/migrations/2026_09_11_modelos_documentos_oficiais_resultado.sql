-- Modelos oficiais do fechamento no Layout de documentos
-- (boletim, ficha, ata, declaração, histórico, certificado e diploma).
-- Idempotente. O HTML/JSON visual é aplicado em PHP (EstruturaDocumentosOficiais).
-- Rollback: 2026_09_11_modelos_documentos_oficiais_resultado_rollback.sql

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos`
      SET `nome` = 'Boletim Escolar Final',
          `descricao` = 'Resultados Finais. Layout visual A4. Placeholders: {{quadro_notas_html}}, {{situacao_final}}, {{frequencia_percentual}}.',
          `orientacao` = 'retrato'
    WHERE `codigo` = 'resultado_boletim_padrao'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos`
      SET `nome` = 'Ficha Individual / Vida Escolar',
          `descricao` = 'Ficha acadêmica do aluno. Placeholders: {{quadro_notas_html}}, {{aluno_filiacao}}, {{conselho_label}}.',
          `orientacao` = 'retrato'
    WHERE `codigo` = 'resultado_ficha_individual'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos`
      SET `nome` = 'Ata de Resultados Finais',
          `descricao` = 'Ata coletiva da turma. Placeholders: {{tabela_html}}, {{ata_totais}}.',
          `orientacao` = 'retrato'
    WHERE `codigo` = 'resultado_ata_finais'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos`
      SET `nome` = 'Histórico Escolar',
          `descricao` = 'Histórico no fechamento. Placeholders: {{historico_html}}, {{quadro_notas_html}}.',
          `orientacao` = 'retrato'
    WHERE `codigo` = 'resultado_historico'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos`
      SET `nome` = 'Declaração de Conclusão de Ano/Série',
          `descricao` = 'Comprovação de conclusão. Placeholders: {{serie}}, {{situacao_final}}, {{codigo_validacao}}.',
          `orientacao` = 'retrato'
    WHERE `codigo` = 'declaracao_conclusao'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'resultado_certificado_conclusao',
     'Certificado de Conclusão',
     'Certificado da etapa. Placeholders: {{etapa}}, {{codigo_validacao}}.',
     '',
     '<p>O(A) {{escola_nome}} certifica que {{aluno_nome}} concluiu a etapa {{etapa}} no ano letivo de {{ano_letivo}}.</p>',
     '',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_certificado_conclusao')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'resultado_diploma',
     'Diploma',
     'Diploma ilustrativo para modalidades que o exijam. Placeholders: {{etapa}}, {{carga_horaria_total}}.',
     '',
     '<p>O(A) {{escola_nome}} confere a {{aluno_nome}} o diploma de {{etapa}} no ano de {{ano_letivo}}.</p>',
     '',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_diploma')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
