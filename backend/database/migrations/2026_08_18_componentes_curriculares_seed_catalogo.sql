-- Seed do catálogo padrão de Componentes Curriculares (BNCC/Novo Ensino Médio).
-- Tenant. Idempotente (cada linha só entra se o nome ainda não existir —
-- padrão `INSERT ... SELECT ... FROM DUAL WHERE NOT EXISTS` já usado em
-- 2026_08_06_secretaria_modelos_documentos.sql).
-- Rollback: 2026_08_18_componentes_curriculares_seed_catalogo_rollback.sql
--
-- IDs: sem faixa reservada. Cada linha usa o AUTO_INCREMENT normal da tabela,
-- no mesmo espaço de id que a escola usa pros próprios cadastros — não há
-- separação "catálogo do sistema" vs "cadastro da escola" por id.
--
-- Duas convenções de ID foram tentadas e descartadas antes desta versão
-- (decisão registrada aqui pra não repetir a investigação):
-- 1) IDs negativos fixos (-1 a -32): preservam a numeração baixa da escola
--    (confirmado em teste real), mas colidem com a coluna
--    `expo_colag_projeto_materias.materia_id` (era INT UNSIGNED — erro SQL
--    com valor negativo) e com dezenas de pontos do código que tratam
--    `materia_id > 0` / `<= 0` como sentinela de "sem matéria/legado"
--    (BoletimConfig, ExamBlock, TeacherExamController, TeacherJourneyController,
--    MatrizCurricularService e outros) — corrigir tudo isso é um projeto à
--    parte, não cabia nesta tarefa.
-- 2) Faixa positiva alta fixa (ex.: 900001+): parecia mais simples, mas
--    testado em MySQL 8 real e DESCARTADO — inserir um valor positivo
--    explícito numa coluna AUTO_INCREMENT move o contador pra frente
--    permanentemente (`ALTER TABLE ... AUTO_INCREMENT` não consegue baixá-lo
--    de volta no InnoDB), então o próximo componente criado pela escola
--    passaria a sair com id 900033+ em vez de 1, 2, 3...
--
-- Guard por nome (case-insensitive, trim) evita tanto reexecução da migration
-- quanto duplicar um componente que a escola já tinha cadastrado manualmente
-- antes desta migration existir (não há UNIQUE em nome/codigo).
--
-- Depende de 2026_08_18_componentes_curriculares_campos.sql (colunas base) E
-- 2026_08_18_componentes_curriculares_etapas.sql (etapa_infantil/fund_i/fund_ii/medio
-- + ENUMs ampliados de area_conhecimento/tipo, usados por várias linhas abaixo)
-- já terem rodado antes nesta escola — a ordem alfabética garante isso
-- ("...campos" < "...etapas" < "...seed_catalogo").
--
-- Observação sobre a coluna `area_conhecimento` do componente Xadrez:
-- a área de origem informada era "Complementar", que não é uma área de
-- conhecimento de fato — foi mapeada para 'outra'.

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Língua Portuguesa', 'LPO', 'LPO', 'linguagens', 'formacao_geral', 'nao_aplica', 'obrigatoria', 'obrigatoria', 'obrigatoria', 'Componente curricular de Língua Portuguesa.', '#3B82F6', 1, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Língua Portuguesa'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Matemática', 'MAT', 'MAT', 'matematica', 'formacao_geral', 'nao_aplica', 'obrigatoria', 'obrigatoria', 'obrigatoria', 'Componente curricular de Matemática.', '#F59E0B', 2, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Matemática'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Ciências', 'CIE', 'CIE', 'ciencias_natureza', 'formacao_geral', 'nao_aplica', 'obrigatoria', 'obrigatoria', 'nao_aplica', 'Componente curricular de Ciências.', '#10B981', 3, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Ciências'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'História', 'HIS', 'HIS', 'ciencias_humanas', 'formacao_geral', 'nao_aplica', 'obrigatoria', 'obrigatoria', 'obrigatoria', 'Componente curricular de História.', '#EF4444', 4, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('História'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Geografia', 'GEO', 'GEO', 'ciencias_humanas', 'formacao_geral', 'nao_aplica', 'obrigatoria', 'obrigatoria', 'obrigatoria', 'Componente curricular de Geografia.', '#EF4444', 5, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Geografia'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Arte', 'ART', 'ART', 'linguagens', 'formacao_geral', 'nao_aplica', 'obrigatoria', 'obrigatoria', 'obrigatoria', 'Componente curricular de Arte.', '#3B82F6', 6, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Arte'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Educação Física', 'EDF', 'EDF', 'linguagens', 'formacao_geral', 'nao_aplica', 'obrigatoria', 'obrigatoria', 'obrigatoria', 'Componente curricular de Educação Física.', '#3B82F6', 7, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Educação Física'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Língua Inglesa', 'ING', 'ING', 'linguagens', 'formacao_geral', 'nao_aplica', 'oferta', 'obrigatoria', 'obrigatoria', 'Componente curricular de Língua Inglesa.', '#3B82F6', 8, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Língua Inglesa'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Ensino Religioso', 'ER', 'ER', 'ensino_religioso', 'formacao_geral', 'nao_aplica', 'oferta', 'oferta', 'nao_aplica', 'Componente curricular de Ensino Religioso.', '#8B5CF6', 9, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Ensino Religioso'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Biologia', 'BIO', 'BIO', 'ciencias_natureza', 'formacao_geral', 'nao_aplica', 'nao_aplica', 'nao_aplica', 'obrigatoria', 'Componente curricular de Biologia.', '#10B981', 10, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Biologia'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Física', 'FIS', 'FIS', 'ciencias_natureza', 'formacao_geral', 'nao_aplica', 'nao_aplica', 'nao_aplica', 'obrigatoria', 'Componente curricular de Física.', '#10B981', 11, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Física'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Química', 'QUI', 'QUI', 'ciencias_natureza', 'formacao_geral', 'nao_aplica', 'nao_aplica', 'nao_aplica', 'obrigatoria', 'Componente curricular de Química.', '#10B981', 12, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Química'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Filosofia', 'FIL', 'FIL', 'ciencias_humanas', 'formacao_geral', 'nao_aplica', 'nao_aplica', 'nao_aplica', 'obrigatoria', 'Componente curricular de Filosofia.', '#EF4444', 13, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Filosofia'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Sociologia', 'SOC', 'SOC', 'ciencias_humanas', 'formacao_geral', 'nao_aplica', 'nao_aplica', 'nao_aplica', 'obrigatoria', 'Componente curricular de Sociologia.', '#EF4444', 14, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Sociologia'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Língua Espanhola', 'ESP', 'ESP', 'linguagens', 'lingua_adicional', 'nao_aplica', 'nao_aplica', 'oferta', 'oferta', 'Componente curricular de Língua Espanhola.', '#3B82F6', 15, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Língua Espanhola'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Redação', 'RED', 'RED', 'linguagens', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Redação.', '#3B82F6', 16, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Redação'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Literatura', 'LIT', 'LIT', 'linguagens', 'complementar', 'nao_aplica', 'nao_aplica', 'oferta', 'oferta', 'Componente curricular de Literatura.', '#3B82F6', 17, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Literatura'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Libras', 'LIB', 'LIB', 'linguagens', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Libras.', '#3B82F6', 18, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Libras'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Projeto de Vida', 'PVI', 'PVI', 'interdisciplinar', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Projeto de Vida.', '#EC4899', 19, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Projeto de Vida'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Educação Financeira', 'EDFN', 'EDFN', 'interdisciplinar', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Educação Financeira.', '#EC4899', 20, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Educação Financeira'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Tecnologia e Inovação', 'TEC', 'TEC', 'tecnologia', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Tecnologia e Inovação.', '#06B6D4', 21, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Tecnologia e Inovação'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Computação', 'CMP', 'CMP', 'computacao', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Computação.', '#0EA5E9', 22, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Computação'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Informática', 'INF', 'INF', 'tecnologia', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Informática.', '#06B6D4', 23, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Informática'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Robótica', 'ROB', 'ROB', 'tecnologia', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Robótica.', '#06B6D4', 24, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Robótica'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Programação', 'PROG', 'PROG', 'tecnologia', 'complementar', 'nao_aplica', 'nao_aplica', 'oferta', 'oferta', 'Componente curricular de Programação.', '#06B6D4', 25, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Programação'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Empreendedorismo', 'EMP', 'EMP', 'interdisciplinar', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Empreendedorismo.', '#EC4899', 26, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Empreendedorismo'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Música', 'MUS', 'MUS', 'arte', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Música.', '#F97316', 27, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Música'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Teatro', 'TEA', 'TEA', 'arte', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Teatro.', '#F97316', 28, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Teatro'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Dança', 'DAN', 'DAN', 'arte', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Dança.', '#F97316', 29, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Dança'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Xadrez', 'XAD', 'XAD', 'outra', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'nao_aplica', 'Componente curricular de Xadrez.', '#6B7280', 30, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Xadrez'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Educação Ambiental', 'ECO', 'ECO', 'interdisciplinar', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Educação Ambiental.', '#EC4899', 31, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Educação Ambiental'));

INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
SELECT 'Educação para Cidadania', 'CID', 'CID', 'interdisciplinar', 'complementar', 'nao_aplica', 'oferta', 'oferta', 'oferta', 'Componente curricular de Educação para Cidadania.', '#EC4899', 32, 1, 1, 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Educação para Cidadania'));
