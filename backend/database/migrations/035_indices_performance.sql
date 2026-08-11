-- Índices para performance (banco do TENANT).
-- Execute no banco de cada tenant. Reduz tempo de consultas em config_layout,
-- provas_turmas e jornadas_redacoes_alunos.

-- config_layout: consultas por config_key (LayoutHelper, Router, AdminController)
CREATE INDEX idx_config_layout_config_key ON config_layout(config_key);

-- provas_turmas: filtro por prova_id (ExamController, listagem de provas por turma)
CREATE INDEX idx_provas_turmas_prova_id ON provas_turmas(prova_id);

-- jornadas_redacoes_alunos: filtro por jornada_redacao_id (TeacherJourneyController)
CREATE INDEX idx_jra_jornada_redacao_id ON jornadas_redacoes_alunos(jornada_redacao_id);
