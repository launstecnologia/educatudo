-- Rollback de 2026_10_07_conselho_et25_finalizado.sql
-- Remove só o Conselho ET25. Não apaga aluno, turma nem resultado acadêmico.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DELETE o FROM conselho_observacoes o
INNER JOIN conselho_sessoes cs ON cs.id = o.sessao_id
INNER JOIN turmas t ON t.id = cs.turma_id
WHERE t.observacoes LIKE 'ET25 %';

DELETE e FROM conselho_encaminhamentos e
INNER JOIN conselho_sessoes cs ON cs.id = e.sessao_id
INNER JOIN turmas t ON t.id = cs.turma_id
WHERE t.observacoes LIKE 'ET25 %';

DELETE d FROM conselho_deliberacoes d
INNER JOIN conselho_sessoes cs ON cs.id = d.sessao_id
INNER JOIN turmas t ON t.id = cs.turma_id
WHERE t.observacoes LIKE 'ET25 %';

DELETE a FROM conselho_atas a
INNER JOIN conselho_sessoes cs ON cs.id = a.sessao_id
INNER JOIN turmas t ON t.id = cs.turma_id
WHERE t.observacoes LIKE 'ET25 %';

DELETE p FROM conselho_participantes p
INNER JOIN conselho_sessoes cs ON cs.id = p.sessao_id
INNER JOIN turmas t ON t.id = cs.turma_id
WHERE t.observacoes LIKE 'ET25 %';

DELETE cs FROM conselho_sessoes cs
INNER JOIN turmas t ON t.id = cs.turma_id
WHERE t.observacoes LIKE 'ET25 %';
