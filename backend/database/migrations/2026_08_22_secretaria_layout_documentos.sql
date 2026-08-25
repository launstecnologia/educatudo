-- Papel timbrado compartilhado (logo/cabeçalho/rodapé/assinatura) + seed das declarações
-- editáveis em /admin/modelos-documentos. Idempotente.
-- Rollback: 2026_08_22_secretaria_layout_documentos_rollback.sql

SET @db := DATABASE();

-- ── 1. Papel timbrado (1 linha por escola) ──
CREATE TABLE IF NOT EXISTS `secretaria_declaracoes_layouts` (
  `id`                    TINYINT UNSIGNED NOT NULL,
  `cabecalho_html`        MEDIUMTEXT NULL,
  `rodape_html`           MEDIUMTEXT NULL,
  `imagem_cabecalho`      VARCHAR(500) NULL,
  `imagem_rodape`         VARCHAR(500) NULL,
  `razao_social`          VARCHAR(180) NULL,
  `cnpj`                  VARCHAR(20) NULL,
  `unidade_assinatura_id` INT NULL,
  `cargo_assinante`       VARCHAR(40) NOT NULL DEFAULT 'direcao',
  `assinante_nome`        VARCHAR(180) NULL,
  `atualizado_por`        INT NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `secretaria_declaracoes_layouts`
  (`id`, `cabecalho_html`, `rodape_html`, `cargo_assinante`)
SELECT
  1,
  '<div class="header"><div class="logo-cell">{{logo_html}}</div><div class="title-cell"><p class="escola">{{escola_nome}}</p><p class="meta">{{escola_endereco}}</p><p class="meta">{{escola_docs}}</p></div></div>',
  '',
  'direcao'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `secretaria_declaracoes_layouts` WHERE `id` = 1);

-- ── 2. Seed das declarações / autorizações (só se a tabela de modelos existir) ──
SET @has_mod := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');

-- Declaração de matrícula
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_matricula',
     'Declaração de Matrícula',
     'Emitida na ficha do aluno. Placeholders: {{aluno_nome}}, {{turma_nome}}, {{ano_letivo}}.',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><div class=\"corpo\"><p>Declaramos, para os devidos fins, que <span class=\"destaque\">{{aluno_nome}}</span>{{aluno_cpf_frase}}{{aluno_nasc_frase}}, encontra-se regularmente <span class=\"destaque\">matriculado(a)</span> nesta instituição de ensino{{turma_frase}}, referente ao ano letivo de <span class=\"destaque\">{{ano_letivo}}</span>.</p><table class=\"dados\"><tr><td class=\"label\">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class=\"label\">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class=\"label\">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class=\"label\">Data de nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class=\"label\">Turma</td><td>{{turma_nome}}</td></tr><tr><td class=\"label\">Série</td><td>{{serie}}</td></tr><tr><td class=\"label\">Ano letivo</td><td>{{ano_letivo}}</td></tr><tr><td class=\"label\">Situação</td><td>{{situacao_matricula}}</td></tr></table><p>Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{secretario_nome}}</div><div class=\"cargo\">Secretaria</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_matricula')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Declaração de frequência
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_frequencia',
     'Declaração de Frequência',
     'Usa {{frequencia_html}} (tabela gerada na emissão).',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><div class=\"corpo\"><p>Declaramos, para os devidos fins, que <span class=\"destaque\">{{aluno_nome}}</span>{{turma_frase}} apresentou a frequência abaixo no período de <span class=\"destaque\">{{periodo_inicio}}</span> a <span class=\"destaque\">{{periodo_fim}}</span>, conforme os registros de diário de classe desta instituição.</p>{{frequencia_html}}<p>Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{secretario_nome}}</div><div class=\"cargo\">Secretaria</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_frequencia')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Declaração de comparecimento
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_comparecimento',
     'Declaração de Comparecimento',
     'Placeholders: {{data_comparecimento}}, {{periodo_texto}}.',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><div class=\"corpo\"><p>Declaramos, para os devidos fins, que <span class=\"destaque\">{{aluno_nome}}</span>{{turma_frase}} compareceu a esta instituição de ensino no dia <span class=\"destaque\">{{data_comparecimento}}</span>{{periodo_texto_frase}}.</p><table class=\"dados\"><tr><td class=\"label\">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class=\"label\">Turma</td><td>{{turma_nome}}</td></tr><tr><td class=\"label\">Data do comparecimento</td><td>{{data_comparecimento}}</td></tr><tr><td class=\"label\">Período / Horário</td><td>{{periodo_texto}}</td></tr></table><p>Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{secretario_nome}}</div><div class=\"cargo\">Secretaria</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_comparecimento')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Declaração de transferência
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_transferencia',
     'Declaração de Transferência',
     'Placeholders: {{data_entrada}}, {{data_saida}}, {{situacao_matricula}}.',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><div class=\"corpo\"><p>Declaramos, para os devidos fins, que <span class=\"destaque\">{{aluno_nome}}</span>{{aluno_cpf_frase}} esteve matriculado(a) nesta instituição de ensino{{turma_frase}}, referente ao ano letivo de <span class=\"destaque\">{{ano_letivo}}</span>, encontrando-se a situação de seu vínculo registrada como <span class=\"destaque\">{{situacao_matricula}}</span>.</p><table class=\"dados\"><tr><td class=\"label\">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class=\"label\">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class=\"label\">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class=\"label\">Turma</td><td>{{turma_nome}}</td></tr><tr><td class=\"label\">Série</td><td>{{serie}}</td></tr><tr><td class=\"label\">Ano letivo</td><td>{{ano_letivo}}</td></tr><tr><td class=\"label\">Data de entrada</td><td>{{data_entrada}}</td></tr><tr><td class=\"label\">Data de saída</td><td>{{data_saida}}</td></tr><tr><td class=\"label\">Situação</td><td>{{situacao_matricula}}</td></tr></table><p>Declaramos ainda que o(a) referido(a) aluno(a) está apto(a) a prosseguir seus estudos em outra instituição. Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{secretario_nome}}</div><div class=\"cargo\">Secretaria</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_transferencia')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ficha de matrícula
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_ficha_matricula',
     'Ficha de Matrícula',
     'Documento cadastral. {{responsaveis_html}} lista os responsáveis.',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><p style=\"text-align:center;font-size:10pt;color:#4b5563;margin-top:-8px;\">Ano letivo <span class=\"destaque\">{{ano_letivo}}</span></p><h3>1. Dados do(a) Aluno(a)</h3><table class=\"dados\"><tr><td class=\"label\">Nome completo</td><td>{{aluno_nome}}</td></tr><tr><td class=\"label\">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class=\"label\">Data de nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class=\"label\">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class=\"label\">RG</td><td>{{aluno_rg}}</td></tr><tr><td class=\"label\">Turma</td><td>{{turma_nome}}</td></tr><tr><td class=\"label\">Série</td><td>{{serie}}</td></tr><tr><td class=\"label\">Endereço</td><td>{{aluno_endereco}}</td></tr><tr><td class=\"label\">E-mail</td><td>{{aluno_email}}</td></tr><tr><td class=\"label\">Telefone</td><td>{{aluno_telefone}}</td></tr></table><h3>2. Responsáveis</h3>{{responsaveis_html}}<p>Declaro que as informações acima são verdadeiras e estou ciente das normas da instituição de ensino.</p>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{resp_nome}}</div><div class=\"cargo\">Responsável legal</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_ficha_matricula')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Autorização de saída
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_aut_saida',
     'Autorização de Saída',
     'Placeholders: {{data_evento}}, {{aut_horario}}, {{aut_motivo}}.',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><div class=\"corpo\"><p>Eu, <span class=\"destaque\">{{resp_nome}}</span>, responsável legal pelo(a) aluno(a) <span class=\"destaque\">{{aluno_nome}}</span>{{turma_frase}}, <span class=\"destaque\">autorizo</span> a sua saída desta instituição de ensino no dia <span class=\"destaque\">{{data_evento}}</span>, às <span class=\"destaque\">{{aut_horario}}</span>.</p><p>Motivo: <span class=\"destaque\">{{aut_motivo}}</span>.</p><p>Declaro estar ciente de que, a partir do horário autorizado, a responsabilidade sobre o(a) aluno(a) passa a ser do responsável legal.</p></div>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{resp_nome}}</div><div class=\"cargo\">Responsável legal</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_aut_saida')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Autorização de retirada
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_aut_retirada',
     'Autorização de Retirada por Terceiros',
     'Placeholders: {{aut_nome_autorizado}}, {{aut_documento}}, {{aut_parentesco}}.',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><div class=\"corpo\"><p>Eu, <span class=\"destaque\">{{resp_nome}}</span>, responsável legal pelo(a) aluno(a) <span class=\"destaque\">{{aluno_nome}}</span>{{turma_frase}}, <span class=\"destaque\">autorizo</span> a pessoa abaixo identificada a retirar o(a) referido(a) aluno(a) desta instituição de ensino.</p><table class=\"dados\"><tr><td class=\"label\">Pessoa autorizada</td><td>{{aut_nome_autorizado}}</td></tr><tr><td class=\"label\">Documento (RG/CPF)</td><td>{{aut_documento}}</td></tr><tr><td class=\"label\">Grau de parentesco / vínculo</td><td>{{aut_parentesco}}</td></tr></table><p>Declaro estar ciente de que a instituição somente liberará o(a) aluno(a) mediante a apresentação de documento de identificação da pessoa autorizada, e que assumo total responsabilidade por esta autorização.</p></div>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{resp_nome}}</div><div class=\"cargo\">Responsável legal</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_aut_retirada')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Autorização de uso de imagem
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_aut_imagem',
     'Autorização de Uso de Imagem',
     'Placeholder {{aut_finalidade}} descreve o uso autorizado.',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><div class=\"corpo\"><p>Eu, <span class=\"destaque\">{{resp_nome}}</span>, responsável legal pelo(a) aluno(a) <span class=\"destaque\">{{aluno_nome}}</span>{{turma_frase}}, <span class=\"destaque\">autorizo</span> o uso da imagem, voz e produções escolares do(a) referido(a) aluno(a) pela instituição de ensino.</p><p>A presente autorização abrange o uso da imagem em <span class=\"destaque\">{{aut_finalidade}}</span>, sem qualquer ônus para a instituição, sendo vedada a utilização para fins comerciais ou que exponham negativamente o(a) aluno(a).</p><p>Esta autorização é concedida de forma gratuita e por prazo indeterminado, podendo ser revogada por escrito a qualquer momento pelo responsável legal.</p></div>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{resp_nome}}</div><div class=\"cargo\">Responsável legal</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_aut_imagem')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Autorização de passeio
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "INSERT INTO `secretaria_modelos_documentos`
    (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
   SELECT
     'declaracao_aut_passeio',
     'Autorização de Passeio/Excursão',
     'Placeholders: {{aut_local}}, {{data_evento}}, {{aut_hora_saida}}, {{aut_hora_retorno}}.',
     '',
     '<div class=\"doc-num\">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class=\"doc-title\">{{titulo}}</h1><div class=\"corpo\"><p>Eu, <span class=\"destaque\">{{resp_nome}}</span>, responsável legal pelo(a) aluno(a) <span class=\"destaque\">{{aluno_nome}}</span>{{turma_frase}}, <span class=\"destaque\">autorizo</span> a sua participação no passeio/excursão organizado(a) por esta instituição de ensino.</p><table class=\"dados\"><tr><td class=\"label\">Destino / Local</td><td>{{aut_local}}</td></tr><tr><td class=\"label\">Data</td><td>{{data_evento}}</td></tr><tr><td class=\"label\">Horário de saída</td><td>{{aut_hora_saida}}</td></tr><tr><td class=\"label\">Horário previsto de retorno</td><td>{{aut_hora_retorno}}</td></tr></table><p>Declaro estar ciente da programação, do meio de transporte utilizado e das orientações da instituição, autorizando a participação do(a) aluno(a) na atividade acima descrita.</p></div>',
     '<div class=\"fecho\">{{cidade_data}}.</div><div class=\"assinaturas\"><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{resp_nome}}</div><div class=\"cargo\">Responsável legal</div></div><div class=\"sig\"><div class=\"line\"></div><div class=\"nome\">{{diretor_nome}}</div><div class=\"cargo\">Direção</div></div></div>',
     'retrato', 1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_aut_passeio')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
