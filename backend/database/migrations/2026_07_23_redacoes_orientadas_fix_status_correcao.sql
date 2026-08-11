-- Corrige dados dessincronizados no módulo Redação Orientada: entregas que já
-- têm uma correção salva (redacoes_orientadas_correcoes.submission_id) mas
-- ficaram com status = 'submitted' em vez de 'corrected'.
--
-- Causa raiz (já corrigida em código): TeacherEssayController::submitForStudent()
-- e submitBatch() sobrescreviam a entrega ao reenviar sem checar se ela já
-- tinha sido corrigida, resetando o status sem apagar a correção antiga.
--
-- Idempotente: a cláusula WHERE só afeta linhas ainda desatualizadas, rodar
-- de novo não tem efeito.
UPDATE redacoes_orientadas_entregas e
INNER JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
SET e.status = 'corrected'
WHERE e.status <> 'corrected';
