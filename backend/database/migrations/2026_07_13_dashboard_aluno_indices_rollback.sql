ALTER TABLE provas_realizacoes DROP INDEX IF EXISTS idx_provas_realizacoes_aluno_status;
ALTER TABLE provas_turmas DROP INDEX IF EXISTS idx_provas_turmas_turma_prova;
ALTER TABLE jornadas_progresso_alunos DROP INDEX IF EXISTS idx_jornadas_progresso_aluno_jornada;
ALTER TABLE mural_recados_vistos DROP INDEX IF EXISTS idx_mural_recados_vistos_aluno_recado;
