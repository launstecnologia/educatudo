-- Adiciona tipo de usuário e motivo da falha em tentativas de login (para bloqueio por nickname e relatório no admin)
-- tipo: aluno, admin_escola, professor, pai
-- motivo_falha: nickname_invalido (login/nickname não existe), senha_invalida (senha errada)

ALTER TABLE `tentativas_login`
  ADD COLUMN `tipo` VARCHAR(30) NULL DEFAULT NULL COMMENT 'aluno, admin_escola, professor, pai' AFTER `success`,
  ADD COLUMN `motivo_falha` VARCHAR(50) NULL DEFAULT NULL COMMENT 'nickname_invalido, senha_invalida' AFTER `tipo`;

CREATE INDEX idx_tentativas_login_tipo_created ON tentativas_login (tipo, created_at);
