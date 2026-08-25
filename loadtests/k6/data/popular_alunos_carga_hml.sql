-- EducaTudo — seed de alunos de carga (HML)
-- Rode SOMENTE no banco do tenant de homologação. Não use no master nem em produção.
--
-- Login:  carga00001 … carga05000   (5 dígitos: carga00001, não carga0001)
-- Senha:  Carga@2026
--
-- Sem CTE recursivo (phpMyAdmin costuma falhar com cte_max_recursion_depth).
-- Idempotente: nickname/RA já existente é pulado.

SET @total := 5000;

INSERT INTO alunos (
    nome,
    nickname,
    email,
    senha_hash,
    ra,
    codigo_aluno,
    ativo,
    status,
    primeiro_acesso,
    pagante,
    serie,
    password
)
SELECT
    CONCAT('Aluno Carga carga', LPAD(nums.i, 5, '0')),
    CONCAT('carga', LPAD(nums.i, 5, '0')),
    CONCAT('carga', LPAD(nums.i, 5, '0'), '@carga.local'),
    '$2y$12$YKTNycibV8gU5iSX858Hdui91kcjT32VbOBlimekBGlKjgcObW.5C',
    CONCAT('carga', LPAD(nums.i, 5, '0')),
    CONCAT('carga', LPAD(nums.i, 5, '0')),
    1,
    'ACTIVE',
    0,
    0,
    'Não informada',
    ''
FROM (
    SELECT a.n + b.n * 10 + c.n * 100 + d.n * 1000 AS i
    FROM
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
         UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
        CROSS JOIN
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
         UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
        CROSS JOIN
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
         UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c
        CROSS JOIN
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
         UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d
) nums
WHERE nums.i BETWEEN 1 AND @total
  AND NOT EXISTS (
      SELECT 1
      FROM alunos a
      WHERE a.nickname = CONCAT('carga', LPAD(nums.i, 5, '0'))
         OR a.ra = CONCAT('carga', LPAD(nums.i, 5, '0'))
  );

SELECT COUNT(*) AS alunos_carga
FROM alunos
WHERE nickname REGEXP '^carga[0-9]{5}$';

-- Conferência rápida
-- SELECT nickname FROM alunos WHERE nickname LIKE 'carga%' ORDER BY nickname LIMIT 5;
--
-- Login no portal do aluno:
--   usuário: carga00001
--   senha:   Carga@2026
