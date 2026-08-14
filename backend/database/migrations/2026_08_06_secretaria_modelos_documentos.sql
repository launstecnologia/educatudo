-- Modelos editáveis de documentos (contratos e textos customizados).
-- Cabeçalho / corpo / rodapé em HTML + placeholders {{campo}}.
-- Tabela criada já com nome PT (secretaria_modelos_documentos). Idempotente.
-- Rollback: 2026_08_06_secretaria_modelos_documentos_rollback.sql

CREATE TABLE IF NOT EXISTS `secretaria_modelos_documentos` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo`             VARCHAR(80) NOT NULL COMMENT 'Ex.: contrato_matricula',
  `nome`               VARCHAR(180) NOT NULL,
  `descricao`          VARCHAR(500) NULL,
  `cabecalho_html`     MEDIUMTEXT NULL,
  `corpo_html`         MEDIUMTEXT NOT NULL,
  `rodape_html`        MEDIUMTEXT NULL,
  `imagem_cabecalho`   VARCHAR(500) NULL,
  `imagem_rodape`      VARCHAR(500) NULL,
  `orientacao`         ENUM('retrato','paisagem') NOT NULL DEFAULT 'retrato',
  `ativo`              TINYINT(1) NOT NULL DEFAULT 1,
  `usar_layout_padrao` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_por`         INT NULL,
  `atualizado_por`     INT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_secretaria_modelos_documentos_codigo` (`codigo`),
  KEY `idx_secretaria_modelos_documentos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: contrato de matrícula genérico
INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'contrato_matricula',
  'Contrato de Matrícula',
  'Usado em Processos de matrícula → Gerar Contrato PDF. Placeholders: {{aluno_nome}}, {{resp_nome}}, {{turma_nome}}, etc.',
  '<div style="text-align:center;margin-bottom:16px;">
  <h1 style="margin:0;font-size:16pt;">{{escola_nome}}</h1>
  <p style="margin:4px 0;font-size:9pt;color:#555;">{{escola_endereco}}{{escola_cnpj}}</p>
  <h2 style="margin:12px 0 0;font-size:13pt;">Contrato de {{tipo_matricula}}</h2>
  <p style="margin:4px 0;font-size:9pt;">Ano letivo {{ano_letivo}} · {{data_hoje}}</p>
</div>',
  '<h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;">1. Dados do Aluno</h3>
<p><strong>Nome:</strong> {{aluno_nome}}<br>
<strong>CPF:</strong> {{aluno_cpf}}<br>
<strong>Nascimento:</strong> {{aluno_data_nasc}}<br>
<strong>E-mail:</strong> {{aluno_email}}<br>
<strong>Telefone:</strong> {{aluno_telefone}}<br>
<strong>Turma:</strong> {{turma_nome}}</p>

<h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:16px;">2. Responsável Legal</h3>
<p><strong>Nome:</strong> {{resp_nome}}<br>
<strong>CPF:</strong> {{resp_cpf}}<br>
<strong>Parentesco:</strong> {{resp_parentesco}}<br>
<strong>E-mail:</strong> {{resp_email}}<br>
<strong>Telefone/WhatsApp:</strong> {{resp_telefone}}<br>
<strong>Endereço:</strong> {{resp_endereco}}</p>

<h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:16px;">3. Cláusulas</h3>
<p>O responsável declara estar ciente das normas da escola e das condições desta matrícula para o ano letivo informado.</p>
<p>{{observacoes}}</p>

<p style="margin-top:28px;">Local e data: ______________________, {{data_hoje}}.</p>
<p style="margin-top:36px;">_________________________________<br>Assinatura do Responsável<br>{{resp_nome}}</p>',
  '<div style="margin-top:24px;font-size:8pt;color:#666;text-align:center;border-top:1px solid #ddd;padding-top:8px;">
  Documento gerado por {{escola_nome}} · {{data_hoje}}
</div>',
  'retrato',
  1,
  0
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'contrato_matricula'
);

-- Seed: material didático (template curto genérico — sem base64/COLAG)
INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'contrato_material_didatico',
  'Contrato de Material Didático',
  'Modelo genérico para material didático / papelaria. Edite no admin conforme a escola.',
  '<div style="text-align:center;margin-bottom:16px;">
  <h1 style="margin:0;font-size:16pt;">{{escola_nome}}</h1>
  <p style="margin:4px 0;font-size:9pt;color:#555;">{{escola_endereco}}{{escola_cnpj}}</p>
  <h2 style="margin:12px 0 0;font-size:13pt;">Contrato de Material Didático</h2>
  <p style="margin:4px 0;font-size:9pt;">Ano letivo {{ano_letivo}} · {{data_hoje}}</p>
</div>',
  '<h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;">1. Aluno</h3>
<p><strong>Nome:</strong> {{aluno_nome}}<br>
<strong>Turma:</strong> {{turma_nome}} · {{serie}}</p>

<h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:16px;">2. Responsável</h3>
<p><strong>Nome:</strong> {{resp_nome}}<br>
<strong>CPF:</strong> {{resp_cpf}}<br>
<strong>Telefone:</strong> {{resp_telefone}}</p>

<h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:16px;">3. Valores</h3>
<p><strong>Valor total:</strong> {{valor_anuidade}}<br>
<strong>Parcelas:</strong> {{num_parcelas}} × {{valor_parcela}}</p>

<p style="margin-top:20px;">O responsável declara estar ciente das condições de aquisição/uso do material didático para o ano letivo informado.</p>
<p style="margin-top:28px;">Local e data: ______________________, {{data_hoje}}.</p>
<p style="margin-top:36px;">_________________________________<br>Assinatura do Responsável<br>{{resp_nome}}</p>',
  '<div style="margin-top:24px;font-size:8pt;color:#666;text-align:center;border-top:1px solid #ddd;padding-top:8px;">
  {{escola_nome}} · Material didático · {{data_hoje}}
</div>',
  'retrato',
  1,
  0
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'contrato_material_didatico'
);
