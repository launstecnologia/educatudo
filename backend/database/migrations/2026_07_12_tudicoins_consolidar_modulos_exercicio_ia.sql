-- Tenant: propaga o toggle liga/desliga (credito_modulo_*) já configurado
-- nas chaves antigas pras duas novas consolidadas — assim a escola não
-- precisa reconfigurar do zero. Preço (credito_custo_*) NÃO é migrado
-- automaticamente (fica no default de 1.0 TudiCoin até o Master revisar
-- manualmente na tela consolidada — agregar preço de módulos diferentes
-- automaticamente é mais arriscado que só herdar o liga/desliga).
--
-- MAX(config_value) sobre as duas chaves antigas é um OR lógico
-- proposital: se qualquer uma das duas já estava ligada ('1'), a chave
-- nova nasce ligada. Se nenhuma das duas existir na escola, HAVING
-- COUNT(*) > 0 descarta a linha e nada é inserido (sem GROUP BY, a query
-- inteira vira 1 grupo implícito — comportamento válido em MySQL 8).

INSERT INTO `config_layout` (`config_key`, `config_value`)
SELECT 'credito_modulo_gerar_exercicio_ia_professor', MAX(`config_value`)
FROM `config_layout`
WHERE `config_key` IN ('credito_modulo_gerar_questoes_prova', 'credito_modulo_gerar_exercicios_jornada')
HAVING COUNT(*) > 0
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`);

INSERT INTO `config_layout` (`config_key`, `config_value`)
SELECT 'credito_modulo_gerar_exercicio_ia_aluno', MAX(`config_value`)
FROM `config_layout`
WHERE `config_key` IN ('credito_modulo_exercicios_ia_personalizado', 'credito_modulo_exercicio_ia_aluno')
HAVING COUNT(*) > 0
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`);
