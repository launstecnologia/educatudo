-- Catálogo padrão de Layout de documentos (declarações, autorizações, contratos e oficiais).
-- Tenant. Idempotente: cada código só entra se ainda não existir.
-- Roda no bootstrap de escola nova (nome com "catalogo", sem _seed_ isolado).
-- Também preenche escolas que ficaram sem o seed de 2026_08_22 (PREPARE/HTML quebrava).
-- Depende de 2026_08_06_secretaria_modelos_documentos.sql (tabela).
-- Rollback: 2026_08_25_secretaria_modelos_documentos_catalogo_rollback.sql

SET @rodape_sec_dir := '<div class="fecho">{{cidade_data}}.</div><div class="assinaturas"><div class="sig"><div class="line"></div><div class="nome">{{secretario_nome}}</div><div class="cargo">Secretaria</div></div><div class="sig"><div class="line"></div><div class="nome">{{diretor_nome}}</div><div class="cargo">Direção</div></div></div>';
SET @rodape_resp_dir := '<div class="fecho">{{cidade_data}}.</div><div class="assinaturas"><div class="sig"><div class="line"></div><div class="nome">{{resp_nome}}</div><div class="cargo">Responsável legal</div></div><div class="sig"><div class="line"></div><div class="nome">{{diretor_nome}}</div><div class="cargo">Direção</div></div></div>';
SET @cab_contrato := '<div style="text-align:center;margin-bottom:16px;"><h1 style="margin:0;font-size:16pt;">{{escola_nome}}</h1><p style="margin:4px 0;font-size:9pt;color:#555;">{{escola_endereco}}{{escola_cnpj}}</p><h2 style="margin:12px 0 0;font-size:13pt;">Contrato de {{tipo_matricula}}</h2><p style="margin:4px 0;font-size:9pt;">Ano letivo {{ano_letivo}} · {{data_hoje}}</p></div>';

-- ── Declarações ──

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_matricula',
  'Declaração de Matrícula',
  'Emitida na ficha do aluno. Placeholders: {{aluno_nome}}, {{turma_nome}}, {{ano_letivo}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Declaramos, para os devidos fins, que <span class="destaque">{{aluno_nome}}</span>{{aluno_cpf_frase}}{{aluno_nasc_frase}}, encontra-se regularmente <span class="destaque">matriculado(a)</span> nesta instituição de ensino{{turma_frase}}, referente ao ano letivo de <span class="destaque">{{ano_letivo}}</span>.</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class="label">Data de nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr><tr><td class="label">Série</td><td>{{serie}}</td></tr><tr><td class="label">Ano letivo</td><td>{{ano_letivo}}</td></tr><tr><td class="label">Situação</td><td>{{situacao_matricula}}</td></tr></table><p>Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_matricula');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_frequencia',
  'Declaração de Frequência',
  'Usa {{frequencia_html}} (tabela gerada na emissão).',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Declaramos, para os devidos fins, que <span class="destaque">{{aluno_nome}}</span>{{turma_frase}} apresentou a frequência abaixo no período de <span class="destaque">{{periodo_inicio}}</span> a <span class="destaque">{{periodo_fim}}</span>, conforme os registros de diário de classe desta instituição.</p>{{frequencia_html}}<p>Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_frequencia');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_comparecimento',
  'Declaração de Comparecimento',
  'Placeholders: {{data_comparecimento}}, {{periodo_texto}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Declaramos, para os devidos fins, que <span class="destaque">{{aluno_nome}}</span>{{turma_frase}} compareceu a esta instituição de ensino no dia <span class="destaque">{{data_comparecimento}}</span>{{periodo_texto_frase}}.</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr><tr><td class="label">Data do comparecimento</td><td>{{data_comparecimento}}</td></tr><tr><td class="label">Período / Horário</td><td>{{periodo_texto}}</td></tr></table><p>Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_comparecimento');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_transferencia',
  'Declaração de Transferência',
  'Placeholders: {{data_entrada}}, {{data_saida}}, {{situacao_matricula}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Declaramos, para os devidos fins, que <span class="destaque">{{aluno_nome}}</span>{{aluno_cpf_frase}} esteve matriculado(a) nesta instituição de ensino{{turma_frase}}, referente ao ano letivo de <span class="destaque">{{ano_letivo}}</span>, encontrando-se a situação de seu vínculo registrada como <span class="destaque">{{situacao_matricula}}</span>.</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr><tr><td class="label">Série</td><td>{{serie}}</td></tr><tr><td class="label">Ano letivo</td><td>{{ano_letivo}}</td></tr><tr><td class="label">Data de entrada</td><td>{{data_entrada}}</td></tr><tr><td class="label">Data de saída</td><td>{{data_saida}}</td></tr><tr><td class="label">Situação</td><td>{{situacao_matricula}}</td></tr></table><p>Declaramos ainda que o(a) referido(a) aluno(a) está apto(a) a prosseguir seus estudos em outra instituição. Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_transferencia');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_ficha_matricula',
  'Ficha de Matrícula',
  'Documento cadastral. {{responsaveis_html}} lista os responsáveis.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><p style="text-align:center;font-size:10pt;color:#4b5563;">Ano letivo <span class="destaque">{{ano_letivo}}</span></p><h3>1. Dados do(a) Aluno(a)</h3><table class="dados"><tr><td class="label">Nome completo</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">Data de nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class="label">RG</td><td>{{aluno_rg}}</td></tr><tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr><tr><td class="label">Série</td><td>{{serie}}</td></tr><tr><td class="label">Endereço</td><td>{{aluno_endereco}}</td></tr><tr><td class="label">E-mail</td><td>{{aluno_email}}</td></tr><tr><td class="label">Telefone</td><td>{{aluno_telefone}}</td></tr></table><h3>2. Responsáveis</h3>{{responsaveis_html}}<p>Declaro que as informações acima são verdadeiras e estou ciente das normas da instituição de ensino.</p>',
  @rodape_resp_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_ficha_matricula');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_historico',
  'Histórico Escolar',
  'Emitido na ficha do aluno. Usa {{historico_html}} / {{quadro_notas_html}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">Histórico Escolar</h1><div class="corpo"><p>Histórico escolar de <span class="destaque">{{aluno_nome}}</span>{{aluno_cpf_frase}}{{aluno_nasc_frase}}, referente aos estudos cursados nesta instituição.</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class="label">Turma atual</td><td>{{turma_nome}}</td></tr><tr><td class="label">Série</td><td>{{serie}}</td></tr><tr><td class="label">Curso</td><td>{{curso_nome}}</td></tr></table>{{historico_html}}{{quadro_notas_html}}<p>Por ser expressão da verdade, firmamos o presente histórico.</p></div>',
  @rodape_sec_dir,
  'paisagem', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_historico');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_bolsista_integral',
  'Declaração de Bolsista Integral',
  'Declara vínculo de bolsa integral. Placeholders: {{aluno_nome}}, {{ano_letivo}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Declaramos, para os devidos fins, que <span class="destaque">{{aluno_nome}}</span>{{aluno_cpf_frase}}{{turma_frase}} é <span class="destaque">bolsista integral</span> nesta instituição de ensino no ano letivo de <span class="destaque">{{ano_letivo}}</span>.</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr><tr><td class="label">Série</td><td>{{serie}}</td></tr><tr><td class="label">Ano letivo</td><td>{{ano_letivo}}</td></tr><tr><td class="label">Situação</td><td>{{situacao_matricula}}</td></tr></table><p>A presente declaração não implica isenção automática de taxas eventuais não cobertas pela bolsa. Por ser expressão da verdade, firmamos a presente.</p></div>',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_bolsista_integral');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_conclusao',
  'Declaração de Conclusão',
  'Conclusão de série/curso. Placeholders: {{serie}}, {{ano_letivo}}, {{situacao_matricula}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Declaramos, para os devidos fins, que <span class="destaque">{{aluno_nome}}</span>{{aluno_cpf_frase}}{{aluno_nasc_frase}} concluiu a <span class="destaque">{{serie}}</span>{{turma_frase}}, referente ao ano letivo de <span class="destaque">{{ano_letivo}}</span>, com situação <span class="destaque">{{situacao_matricula}}</span>.</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / Código</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">Curso</td><td>{{curso_nome}}</td></tr><tr><td class="label">Série concluída</td><td>{{serie}}</td></tr><tr><td class="label">Ano letivo</td><td>{{ano_letivo}}</td></tr><tr><td class="label">Situação</td><td>{{situacao_matricula}}</td></tr></table><p>Por ser expressão da verdade, firmamos a presente declaração.</p></div>',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_conclusao');

-- ── Autorizações ──

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_aut_saida',
  'Autorização de Saída',
  'Placeholders: {{data_evento}}, {{aut_horario}}, {{aut_motivo}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Eu, <span class="destaque">{{resp_nome}}</span>, responsável legal pelo(a) aluno(a) <span class="destaque">{{aluno_nome}}</span>{{turma_frase}}, <span class="destaque">autorizo</span> a sua saída desta instituição de ensino no dia <span class="destaque">{{data_evento}}</span>, às <span class="destaque">{{aut_horario}}</span>.</p><p>Motivo: <span class="destaque">{{aut_motivo}}</span>.</p><p>Declaro estar ciente de que, a partir do horário autorizado, a responsabilidade sobre o(a) aluno(a) passa a ser do responsável legal.</p></div>',
  @rodape_resp_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_aut_saida');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_aut_retirada',
  'Autorização de Retirada por Terceiros',
  'Placeholders: {{aut_nome_autorizado}}, {{aut_documento}}, {{aut_parentesco}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Eu, <span class="destaque">{{resp_nome}}</span>, responsável legal pelo(a) aluno(a) <span class="destaque">{{aluno_nome}}</span>{{turma_frase}}, <span class="destaque">autorizo</span> a pessoa abaixo identificada a retirar o(a) referido(a) aluno(a) desta instituição de ensino.</p><table class="dados"><tr><td class="label">Pessoa autorizada</td><td>{{aut_nome_autorizado}}</td></tr><tr><td class="label">Documento (RG/CPF)</td><td>{{aut_documento}}</td></tr><tr><td class="label">Grau de parentesco / vínculo</td><td>{{aut_parentesco}}</td></tr></table><p>Declaro estar ciente de que a instituição somente liberará o(a) aluno(a) mediante a apresentação de documento de identificação da pessoa autorizada, e que assumo total responsabilidade por esta autorização.</p></div>',
  @rodape_resp_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_aut_retirada');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_aut_imagem',
  'Autorização de Uso de Imagem',
  'Placeholder {{aut_finalidade}} descreve o uso autorizado.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Eu, <span class="destaque">{{resp_nome}}</span>, responsável legal pelo(a) aluno(a) <span class="destaque">{{aluno_nome}}</span>{{turma_frase}}, <span class="destaque">autorizo</span> o uso da imagem, voz e produções escolares do(a) referido(a) aluno(a) pela instituição de ensino.</p><p>A presente autorização abrange o uso da imagem em <span class="destaque">{{aut_finalidade}}</span>, sem qualquer ônus para a instituição, sendo vedada a utilização para fins comerciais ou que exponham negativamente o(a) aluno(a).</p><p>Esta autorização é concedida de forma gratuita e por prazo indeterminado, podendo ser revogada por escrito a qualquer momento pelo responsável legal.</p></div>',
  @rodape_resp_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_aut_imagem');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'declaracao_aut_passeio',
  'Autorização de Passeio/Excursão',
  'Placeholders: {{aut_local}}, {{data_evento}}, {{aut_hora_saida}}, {{aut_hora_retorno}}.',
  '',
  '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo}}</h1><div class="corpo"><p>Eu, <span class="destaque">{{resp_nome}}</span>, responsável legal pelo(a) aluno(a) <span class="destaque">{{aluno_nome}}</span>{{turma_frase}}, <span class="destaque">autorizo</span> a sua participação no passeio/excursão organizado(a) por esta instituição de ensino.</p><table class="dados"><tr><td class="label">Destino / Local</td><td>{{aut_local}}</td></tr><tr><td class="label">Data</td><td>{{data_evento}}</td></tr><tr><td class="label">Horário de saída</td><td>{{aut_hora_saida}}</td></tr><tr><td class="label">Horário previsto de retorno</td><td>{{aut_hora_retorno}}</td></tr></table><p>Declaro estar ciente da programação, do meio de transporte utilizado e das orientações da instituição, autorizando a participação do(a) aluno(a) na atividade acima descrita.</p></div>',
  @rodape_resp_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'declaracao_aut_passeio');

-- ── Contratos ──

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'contrato_matricula',
  'Contrato de Matrícula',
  'Usado em Processos de matrícula. Placeholders: {{aluno_nome}}, {{resp_nome}}, {{turma_nome}}.',
  @cab_contrato,
  '<h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;">1. Dados do Aluno</h3><p><strong>Nome:</strong> {{aluno_nome}}<br><strong>CPF:</strong> {{aluno_cpf}}<br><strong>Nascimento:</strong> {{aluno_data_nasc}}<br><strong>E-mail:</strong> {{aluno_email}}<br><strong>Telefone:</strong> {{aluno_telefone}}<br><strong>Turma:</strong> {{turma_nome}}</p><h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:16px;">2. Responsável Legal</h3><p><strong>Nome:</strong> {{resp_nome}}<br><strong>CPF:</strong> {{resp_cpf}}<br><strong>Parentesco:</strong> {{resp_parentesco}}<br><strong>E-mail:</strong> {{resp_email}}<br><strong>Telefone/WhatsApp:</strong> {{resp_telefone}}<br><strong>Endereço:</strong> {{resp_endereco}}</p><h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:16px;">3. Cláusulas</h3><p>O responsável declara estar ciente das normas da escola e das condições desta matrícula para o ano letivo informado.</p><p>{{observacoes}}</p><p style="margin-top:28px;">Local e data: ______________________, {{data_hoje}}.</p><p style="margin-top:36px;">_________________________________<br>Assinatura do Responsável<br>{{resp_nome}}</p>',
  '<div style="margin-top:24px;font-size:8pt;color:#666;text-align:center;border-top:1px solid #ddd;padding-top:8px;">Documento gerado por {{escola_nome}} · {{data_hoje}}</div>',
  'retrato', 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'contrato_matricula');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'contrato_material_didatico',
  'Contrato de Material Didático',
  'Modelo genérico para material didático / papelaria.',
  '<div style="text-align:center;margin-bottom:16px;"><h1 style="margin:0;font-size:16pt;">{{escola_nome}}</h1><p style="margin:4px 0;font-size:9pt;color:#555;">{{escola_endereco}}{{escola_cnpj}}</p><h2 style="margin:12px 0 0;font-size:13pt;">Contrato de Material Didático</h2><p style="margin:4px 0;font-size:9pt;">Ano letivo {{ano_letivo}} · {{data_hoje}}</p></div>',
  '<h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;">1. Aluno</h3><p><strong>Nome:</strong> {{aluno_nome}}<br><strong>Turma:</strong> {{turma_nome}} · {{serie}}</p><h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:16px;">2. Responsável</h3><p><strong>Nome:</strong> {{resp_nome}}<br><strong>CPF:</strong> {{resp_cpf}}<br><strong>Telefone:</strong> {{resp_telefone}}</p><h3 style="font-size:11pt;border-bottom:1px solid #ccc;padding-bottom:4px;margin-top:16px;">3. Valores</h3><p><strong>Valor total:</strong> {{valor_anuidade}}<br><strong>Parcelas:</strong> {{num_parcelas}} × {{valor_parcela}}</p><p style="margin-top:20px;">O responsável declara estar ciente das condições de aquisição/uso do material didático para o ano letivo informado.</p><p style="margin-top:28px;">Local e data: ______________________, {{data_hoje}}.</p><p style="margin-top:36px;">_________________________________<br>Assinatura do Responsável<br>{{resp_nome}}</p>',
  '<div style="margin-top:24px;font-size:8pt;color:#666;text-align:center;border-top:1px solid #ddd;padding-top:8px;">{{escola_nome}} · Material didático · {{data_hoje}}</div>',
  'retrato', 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'contrato_material_didatico');

-- ── Documentos oficiais (Resultados Finais — códigos resultado_*) ──

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'resultado_boletim_padrao',
  'Boletim Escolar',
  'Usado em Resultados Finais. Placeholders: {{quadro_notas_html}}, {{situacao_final}}, {{frequencia_percentual}}.',
  '',
  '<div class="doc-num">Boletim nº {{numero}}/{{ano}}</div><h1 class="doc-title">Boletim Escolar</h1><p style="text-align:center;font-size:10pt;color:#4b5563;">{{periodo_label}} · {{ano_letivo}}</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / RA</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr><tr><td class="label">Série / Curso</td><td>{{serie}} · {{curso_nome}}</td></tr><tr><td class="label">Situação</td><td>{{situacao_final}}</td></tr><tr><td class="label">Frequência</td><td>{{frequencia_percentual}}</td></tr></table>{{quadro_notas_html}}<p>{{observacoes}}</p>',
  @rodape_sec_dir,
  'paisagem', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_boletim_padrao');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'resultado_ficha_individual',
  'Ficha Individual',
  'Ficha acadêmica completa. Placeholders: {{quadro_notas_html}}, {{observacoes}}.',
  '',
  '<div class="doc-num">Ficha nº {{numero}}/{{ano}}</div><h1 class="doc-title">Ficha Individual do Aluno</h1><p style="text-align:center;font-size:10pt;color:#4b5563;">{{periodo_label}} · {{ano_letivo}}</p><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / RA</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr><tr><td class="label">Série / Curso</td><td>{{serie}} · {{curso_nome}}</td></tr><tr><td class="label">Situação final</td><td>{{situacao_final}}</td></tr><tr><td class="label">Frequência</td><td>{{frequencia_percentual}}</td></tr></table>{{quadro_notas_html}}<p><strong>Observações</strong></p><p>{{observacoes}}</p>',
  @rodape_sec_dir,
  'retrato', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_ficha_individual');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'resultado_ata_finais',
  'Ata de Resultados Finais',
  'Ata da turma. Placeholders: {{tabela_html}}, {{total_alunos}}, {{total_homologados}}.',
  '',
  '<div class="doc-num">Ata nº {{numero}}/{{ano}}</div><h1 class="doc-title">Ata de Resultados Finais</h1><p style="text-align:center;font-size:10pt;color:#4b5563;">{{turma_nome}} · {{serie}} · {{periodo_label}} · {{ano_letivo}}</p><p>Total de alunos: <span class="destaque">{{total_alunos}}</span> · Homologados: <span class="destaque">{{total_homologados}}</span> · Pendências: <span class="destaque">{{total_pendencias}}</span>.</p>{{tabela_html}}<p>A presente ata registra o resultado acadêmico dos alunos da turma no período informado.</p>',
  @rodape_sec_dir,
  'paisagem', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_ata_finais');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'resultado_relatorio_padrao',
  'Relatório de Resultados',
  'Relatórios de fechamento (aprovados, reprovados, pendências etc.). Usa {{titulo_relatorio}} e {{tabela_html}}.',
  '',
  '<div class="doc-num">Relatório nº {{numero}}/{{ano}}</div><h1 class="doc-title">{{titulo_relatorio}}</h1><p style="text-align:center;font-size:10pt;color:#4b5563;">{{turma_nome}} · {{serie}} · {{periodo_label}} · {{ano_letivo}}</p>{{tabela_html}}<p>Total de alunos no recorte: <span class="destaque">{{total_alunos}}</span>.</p>',
  @rodape_sec_dir,
  'paisagem', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_relatorio_padrao');

INSERT INTO `secretaria_modelos_documentos`
  (`codigo`, `nome`, `descricao`, `cabecalho_html`, `corpo_html`, `rodape_html`, `orientacao`, `ativo`, `usar_layout_padrao`)
SELECT
  'resultado_historico',
  'Histórico Escolar (Resultados)',
  'Histórico emitido pelo módulo de Resultados Finais. Placeholders: {{historico_html}}, {{quadro_notas_html}}.',
  '',
  '<div class="doc-num">Histórico nº {{numero}}/{{ano}}</div><h1 class="doc-title">Histórico Escolar</h1><table class="dados"><tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr><tr><td class="label">Matrícula / RA</td><td>{{aluno_codigo}}</td></tr><tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr><tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr><tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr><tr><td class="label">Série / Curso</td><td>{{serie}} · {{curso_nome}}</td></tr><tr><td class="label">Situação final</td><td>{{situacao_final}}</td></tr><tr><td class="label">Frequência</td><td>{{frequencia_percentual}}</td></tr></table>{{historico_html}}{{quadro_notas_html}}<p>{{observacoes}}</p>',
  @rodape_sec_dir,
  'paisagem', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_historico');
