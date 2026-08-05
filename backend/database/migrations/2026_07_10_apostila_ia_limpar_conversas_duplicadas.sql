-- Limpeza one-time: remove conversas duplicadas geradas pelo bug PHP+Python
-- gravando a mesma pergunta/resposta em sequência (Fase A corrigiu a causa).
-- Mantém o registro de menor id quando pergunta+resposta+usuário coincidem
-- e created_at está a até 10 segundos de distância.
-- Executar em cada banco de tenant.

DELETE c1
FROM apostila_ia_conversas c1
INNER JOIN apostila_ia_conversas c2
    ON c1.apostila_id = c2.apostila_id
    AND (c1.professor_id <=> c2.professor_id)
    AND c1.pergunta = c2.pergunta
    AND c1.resposta = c2.resposta
    AND c1.id > c2.id
    AND ABS(TIMESTAMPDIFF(SECOND, c1.created_at, c2.created_at)) <= 10;
