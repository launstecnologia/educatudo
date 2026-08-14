-- Regras de contrato da matrícula: vincula tipo de produto a um modelo de documento.
-- Permite vários contratos por processo (matrícula, material, uniforme…).
-- Dependência: requer matricula_processos (2026_08_06_matricula_processos_upgrade).

CREATE TABLE IF NOT EXISTS matricula_contrato_regras (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(160) NOT NULL,
    tipo ENUM(
        'matricula',
        'mensalidade',
        'material_didatico',
        'uniforme',
        'taxa',
        'outros'
    ) NOT NULL DEFAULT 'matricula',
    modelo_documento_codigo VARCHAR(80) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    enviar_zapsign TINYINT(1) NOT NULL DEFAULT 1,
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_matricula_contrato_regras_tipo (tipo),
    KEY idx_matricula_contrato_regras_ativo (ativo, ordem),
    KEY idx_matricula_contrato_regras_modelo (modelo_documento_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Instâncias geradas por processo (PDF / ZapSign por regra).
CREATE TABLE IF NOT EXISTS matricula_processos_contratos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enrollment_id INT NOT NULL,
    regra_id INT UNSIGNED NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'matricula',
    nome VARCHAR(160) NOT NULL,
    modelo_documento_codigo VARCHAR(80) NOT NULL,
    pdf_path VARCHAR(500) NULL,
    contrato_token VARCHAR(64) NULL,
    contrato_hash VARCHAR(64) NULL,
    zapsign_doc_token VARCHAR(120) NULL,
    zapsign_signer_token VARCHAR(120) NULL,
    zapsign_sign_url VARCHAR(500) NULL,
    zapsign_status VARCHAR(40) NULL,
    zapsign_enviado_em DATETIME NULL,
    assinado_em DATETIME NULL,
    assinante_nome VARCHAR(255) NULL,
    status ENUM('pendente','gerado','enviado','assinado','cancelado') NOT NULL DEFAULT 'pendente',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mpc_enrollment_tipo (enrollment_id, tipo),
    KEY idx_mpc_enrollment (enrollment_id),
    KEY idx_mpc_regra (regra_id),
    KEY idx_mpc_zapsign (zapsign_doc_token),
    CONSTRAINT fk_mpc_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES matricula_processos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_mpc_regra
        FOREIGN KEY (regra_id) REFERENCES matricula_contrato_regras (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds padrão (só se ainda não houver regras).
INSERT INTO matricula_contrato_regras (nome, tipo, modelo_documento_codigo, ativo, enviar_zapsign, ordem)
SELECT 'Contrato de Matrícula', 'matricula', 'contrato_matricula', 1, 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_contrato_regras WHERE tipo = 'matricula');

INSERT INTO matricula_contrato_regras (nome, tipo, modelo_documento_codigo, ativo, enviar_zapsign, ordem)
SELECT 'Contrato de Material Didático', 'material_didatico', 'contrato_material_didatico', 1, 1, 2
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_contrato_regras WHERE tipo = 'material_didatico');
