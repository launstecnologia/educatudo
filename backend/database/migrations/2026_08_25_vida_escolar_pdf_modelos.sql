-- Modelos de PDF da Vida Escolar (boletim, dossiê, pacote, SED, histórico).
-- Tenant. Idempotente. Herdam o papel timbrado (usar_layout_padrao = 1).
-- O HTML também é criado em runtime por VidaEscolarPdfService::garantirModelos().
-- Rollback: 2026_08_25_vida_escolar_pdf_modelos_rollback.sql

SET @rodape_sec_dir := '<div class="fecho">{{cidade_data}}.</div><div class="assinaturas"><div class="sig"><div class="line"></div><div class="nome">{{secretario_nome}}</div><div class="cargo">Secretaria</div></div><div class="sig"><div class="line"></div><div class="nome">{{diretor_nome}}</div><div class="cargo">Direção</div></div></div>';

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'vida_escolar_boletim',
  'Boletim (Vida Escolar)',
  'Ficha oficial do ano. Placeholders: {{quadro_notas_html}}, {{aluno_nome}}, {{turma_nome}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}}</div><h1 class="doc-title">{{titulo}}</h1><p style="text-align:center;font-size:10pt;color:#4b5563;">{{turma_nome}} · {{serie}} · {{ano_letivo}}</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class="label">Turma / série</td><td>{{turma_nome}} · {{serie}}</td></tr><tr><td class="label">Situação</td><td>{{situacao_matricula}}</td></tr></table>{{quadro_notas_html}}<p>{{observacoes}}</p>',
  @rodape_sec_dir,
  'paisagem', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'vida_escolar_boletim');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'vida_escolar_pacote',
  'Pacote de transferência',
  'Identidade + trajetória + boletim. {{identidade_html}}, {{trajetoria_html}}, {{quadro_notas_html}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}}</div><h1 class="doc-title">{{titulo}}</h1><p style="text-align:center;font-size:10pt;color:#4b5563;">{{aluno_nome}} · {{ano_letivo}}</p><h3>1. Identificação</h3>{{identidade_html}}<h3>2. Trajetória</h3>{{trajetoria_html}}<h3>3. Boletim do ano</h3>{{quadro_notas_html}}<p>Emita também o Histórico Escolar oficial. Débito financeiro não impede a expedição destes documentos acadêmicos.</p>',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'vida_escolar_pacote');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'vida_escolar_dossie',
  'Dossiê do aluno',
  'Pacote completo. {{identidade_html}}, {{trajetoria_html}}, {{quadro_notas_html}}, {{documentos_html}}, {{sed_html}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}}</div><h1 class="doc-title">{{titulo}}</h1><p style="text-align:center;font-size:10pt;color:#4b5563;">{{aluno_nome}} · {{data_hoje}}</p><h3>Identidade</h3>{{identidade_html}}<h3>Trajetória</h3>{{trajetoria_html}}<h3>Boletim</h3>{{quadro_notas_html}}<h3>Documentos de matrícula</h3>{{documentos_html}}<h3>SED / Educacenso</h3>{{sed_html}}',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'vida_escolar_dossie');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'vida_escolar_sed',
  'Planilha SED',
  'Campos para digitação na SED. {{identidade_html}} e {{sed_html}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}}</div><h1 class="doc-title">{{titulo}}</h1><p>Planilha de apoio à digitação no portal da SED. Não há API pública.</p>{{identidade_html}}<h3>Conferência</h3>{{sed_html}}',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'vida_escolar_sed');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'vida_escolar_historico',
  'Histórico escolar oficial',
  'Documento emitido/assinado. Placeholders: {{historico_html}}, {{aluno_nome}}, {{observacoes}}.',
  '',
  '<div class="doc-num">Histórico nº {{numero}}/{{ano}}</div><h1 class="doc-title">Histórico Escolar</h1><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class="label">Turma atual</td><td>{{turma_nome}} · {{serie}}</td></tr></table>{{historico_html}}<p>{{observacoes}}</p>',
  @rodape_sec_dir,
  'paisagem', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'vida_escolar_historico');
