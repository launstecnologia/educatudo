# Navbar Admin — Mapa de páginas e funcionalidades

Este documento descreve as páginas exibidas no menu lateral do Admin. A estrutura foi levantada a partir dos arquivos:

- `backend/app/Views/layouts/components/admin_sidebar.php`
- `backend/app/Views/layouts/components/admin_sidebar_menu_principal.php`
- `backend/app/Views/layouts/components/admin_sidebar_menu_secretaria.php`
- `backend/config/routes/admin.php`
- `backend/app/Modulos/*/manifest.php`

Observação: o menu é condicionado por perfil, permissões (`AdminPermissionMatrix`) e módulos ligados/desligados por escola (`LayoutHelper` / `FeatureGate`). Portanto, nem todo usuário vê todos os itens ao mesmo tempo.

## Estrutura geral

O Admin usa um menu lateral com:

- identificação do sistema/escola;
- dados do usuário logado;
- grupos de navegação expansíveis;
- itens diretos e subitens aninhados;
- botão de saída.

Há três variações principais:

- **Menu principal**: usado pelos perfis administrativos gerais, como `dev`, `diretor` e `coordenador`.
- **Menu Secretaria**: versão reduzida com foco em cadastros acadêmicos, avaliação, gestão escolar e secretaria.
- **Menu Financeiro**: versão reduzida com acesso somente às telas financeiras.

## Itens diretos

### Dashboard

Rota: `/admin/dashboard`

Página inicial do painel administrativo. Reúne indicadores gerais da escola e serve como ponto de partida para a gestão.

### Assistente

Rota: `/admin/assistente`

Chat de apoio à coordenação/direção. Consulta dados acadêmicos e operacionais por ferramentas internas, como provas, jornadas, boletins, faltas e contexto do aluno.

Visibilidade: módulo `assistente` ligado e perfil `dev`, `diretor` ou `coordenador`.

## Acadêmico

Rota do hub: `/admin/academico`

Central para cadastros e estruturas acadêmicas da escola.

### Alunos

Rota: `/admin/students`

Gestão completa dos alunos: cadastro, edição, importação, responsáveis, matrícula vinculada, status ativo/inativo, acesso como aluno/pai, foto, boletim, documentos, auditoria e relatórios ligados ao aluno.

### Ano Letivo

Rota: `/admin/ano-letivo`

Cadastro e manutenção dos anos letivos usados nas turmas, matrículas, calendário e demais rotinas acadêmicas.

### Calendário Letivo

Rota: `/admin/calendario-letivo`

Configura dias letivos, eventos do calendário pedagógico, tipos de evento e carga anual associada ao ano letivo.

### Componentes Curriculares

Rota: `/admin/componentes-curriculares`

Cadastro das disciplinas/componentes curriculares usados em turmas, matrizes, boletins, avaliações e demais rotinas pedagógicas.

### Curso

Rota: `/admin/curso`

Cadastro de cursos/ofertas acadêmicas. Também possui fluxo de importação de alunos por curso.

### Grade Horária

Rota: `/admin/grade-horaria`

Monta e administra a grade de aulas. Inclui geração de PDF e importação/estruturação por IA a partir de imagem.

### Matriz Curricular

Rota: `/admin/matrizes-curriculares`

Relaciona série, componente curricular e carga horária. Base para organização curricular e documentos acadêmicos.

### Professores

Rota: `/admin/teachers`

Cadastro e manutenção de professores: dados, status, importação/exportação e controle de vínculo com a plataforma.

### Agrupamentos

Rota: `/admin/agrupamentos-componentes`

Cadastro de agrupamentos de componentes curriculares para uso em boletins e relatórios, especialmente quando a escola precisa consolidar disciplinas em linhas agrupadas.

### Grupos de regras de notas

Rota: `/admin/grupos-regras-notas`

Cadastro de grupos dinâmicos para organizar regras de nota, tipos e marcas usados nos fluxos de boletim.

### Boletins

Rota: `/admin/boletins`

Cadastro/organização dos boletins oficiais ou extras disponíveis para a escola.

### Regras Acadêmicas

Rota: `/admin/regras-academicas`

Configura regras de aprovação, recuperação, média, resultado acadêmico e critérios usados no fechamento escolar.

### Salas / Ambientes

Rota: `/admin/salas`

Cadastro de salas, ambientes e locais físicos usados pela escola, inclusive como base para patrimônio.

### Série

Rota: `/admin/serie`

Cadastro de séries/anos escolares associados aos cursos.

### Turmas

Rota: `/admin/turmas`

Cadastro e gestão de turmas. Inclui vinculação de alunos, lista de chamada, exportações, status e visualização detalhada.

## Avaliações

Rota do hub: `/admin/avaliacoes`

Central para provas, notas, redações, jornadas e relatórios avaliativos.

### Avaliação Adaptativa

Rota: `/admin/inclusao/versoes`

Fila e aprovação de versões adaptadas de provas para alunos com máscara de inclusão. Faz parte do EducaInclui e trabalha com laudos, versões adaptadas, diff e PDFs.

### Avaliações/Notas

Rota: `/admin/provas`

Gestão de provas online, blocos de prova, avaliações, notas, correções, tentativas, impressão/PDF e aprovação/reprovação de provas enviadas.

### Tipos de Nota

Rota: `/admin/provas/tipos-avaliacao`

Cadastro dos tipos usados nos eventos de prova (prova bimestral, trabalho, recuperação etc.). Cada tipo pode apontar para uma coluna do quadro de notas.

### Jornada da Redação

Rota: `/admin/redacao-professor`

Administra propostas de redação, envios dos alunos, relatórios e analytics por aluno/proposta.

### Jornada do Aluno

Rota: `/admin/jornadas`

Gestão de jornadas de aprendizagem: criação, módulos, vídeos, documentos, exercícios, redação, relatórios e acompanhamento do desempenho dos alunos.

### Eventos de Notas

Rota: `/admin/boletim`

Configuração e acompanhamento dos eventos/regras usados para gerar boletins, incluindo geração, simulação, travas e boletins gerados.

### Guia do Boletim

Rota: `/admin/boletim-guia`

Página de orientação/guia para uso e configuração do módulo de boletim.

### Relatórios

Rota: `/admin/relatorios`

Hub de relatórios administrativos. Relaciona acessos, jornadas, exercícios, redações, censo e boletins da coordenação.

## Escola

Rota do hub: `/admin/expo-colag`

Grupo opcional com nome customizável pela escola.

### Expo Colag

Rota: `/admin/expo-colag`

Módulo de feira/projetos. É controlado por feature flag e aparece somente quando o módulo `expo_colag` está habilitado.

## Comunicação

Rota do hub: `/admin/comunicacao`

Central de comunicação entre escola, alunos, responsáveis e comunidade.

### Comunicação Escolar

Rota: `/admin/comunicacao-escolar`

Mensagens/comunicados escolares. Permite criar, visualizar e responder comunicações.

### Calendário Escolar

Rota: `/admin/calendario-escolar`

Agenda de eventos escolares voltados à comunicação com a comunidade. Permite criar, editar, consultar dados e cancelar eventos.

### Fórum

Rota: `/forum`

Área de discussão/comunidade. O Admin acessa a moderação e acompanhamento do fórum quando o módulo está ativo.

### Denúncias Fórum

Rota: `/forum/moderation/reports`

Fila de denúncias do fórum para análise/moderação.

### Mural de Recados

Rota: `/admin/mural-recados`

Cadastro e manutenção de recados exibidos aos usuários da plataforma.

### Notificações

Rota: `/admin/notifications`

Gestão de notificações internas. Permite criar, editar, visualizar e acompanhar comunicados/notificações.

### Notificações Push

Rota: `/admin/notificacoes-push`

Envio e acompanhamento de notificações push.

### Reuniões

Rota: `/admin/reunioes/geral`

Gestão de reuniões gerais e atas em PDF. Há também fluxo de reuniões por aluno em `/admin/reunioes/aluno`.

## Conteúdo

Rota do hub: `/admin/conteudo`

Central de materiais e conteúdos disponibilizados para alunos/professores.

### Arquivos

Rota: `/admin/arquivos`

Administração de materiais/arquivos das turmas. O módulo também conversa com os portais de aluno e professor.

### EducaHits (portal)

Rota: portal externo retornado por `EducaHitsConfig::portalLoginUrl()`

Atalho para o portal EducaHits quando o módulo `educa_hits` está habilitado.

### Meu Material

Rota: `/admin/apostilas-ia`

Módulo de apostilas/material com IA. Permite criar, enviar PDF/capa, editar, reprocessar e acompanhar status.

## Financeiro

Rota do hub: `/admin/financeiro-escolar`

Central do financeiro escolar. O perfil `financeiro` vê basicamente este grupo e seus subitens.

### Dashboard

Rota: `/admin/finance`

Visão geral financeira, indicadores e ações como disparo de régua de cobrança.

### Contratos

Rota: `/admin/finance/contracts`

Gestão de contratos financeiros de alunos/responsáveis: criação, ativação, cancelamento, descontos, renegociação e parcelas.

### Planos e Preços

Rota: `/admin/finance/plans`

Cadastro de planos financeiros e seus itens.

### Cobranças Avulsas

Rota: `/admin/finance/charges`

Lista e gestão de cobranças avulsas. Permite registrar pagamento e criar cobrança individual pelo aluno.

### Nova em Lote

Rota: `/admin/finance/charges/batch`

Criação de cobranças avulsas em lote.

### Contas a Pagar

Rota: `/admin/finance/bills`

Cadastro, pagamento e exclusão de contas/despesas da escola.

### Fluxo de Caixa

Rota: `/admin/finance/cashflow`

Relatório de entradas e saídas financeiras por período.

### Inadimplência

Rota: `/admin/finance/report/inadimplencia`

Relatório de inadimplência e acompanhamento de parcelas vencidas.

### Descontos

Rota: `/admin/finance/discount-rules`

Cadastro e ativação/desativação de regras de desconto.

### Configurações

Rota: `/admin/finance/settings`

Parâmetros de funcionamento do módulo financeiro.

### Balanço Patrimonial

Rota: `/admin/finance/reports/balanco`

Relatório contábil de balanço patrimonial.

### DRE

Rota: `/admin/finance/reports/dre`

Demonstração do Resultado do Exercício.

### DFC

Rota: `/admin/finance/reports/dfc`

Demonstração do Fluxo de Caixa.

### DMPL

Rota: `/admin/finance/reports/dmpl`

Demonstração das Mutações do Patrimônio Líquido.

### DLPA

Rota: `/admin/finance/reports/dlpa`

Demonstração de Lucros ou Prejuízos Acumulados.

## Gestão Escolar

Rota do hub: `/admin/gestao-escolar`

Central de secretaria, documentação, acompanhamento escolar e operação institucional.

### Censo Escolar

Rota: `/admin/censo`

Módulo do Censo Escolar/Educacenso. Ajuda a organizar e exportar informações necessárias ao censo.

### Conformidade

Rota: `/admin/conformidade`

Painel de conformidade pedagógica. Possui pendências, auditoria e consulta por IA.

### Conselho de Classe

Rota: `/admin/conselhos`

Registro e acompanhamento de conselho de classe, usado junto ao fechamento acadêmico.

### Diário de Classe

Rota: `/admin/diario`

Acompanhamento administrativo dos diários de classe, com integração ao professor quando o módulo está ativo.

### Faltas

Rota: `/admin/faltas`

Lançamento, consulta e exportação de faltas.

### Presença

Rota: `/admin/presenca`

Controle/consolidado de presença, incluindo cenários de entrada/saída quando integrados.

### Documentos Institucionais

Rota: `/admin/documentos-institucionais`

Gestão de documentos institucionais como PPP, regimento e documentos relacionados à escola.

### Assinatura Digital

Rota: `/admin/configuracao/assinatura-digital`

Configuração de assinatura digital para documentos ligados ao processo de matrícula.

### Layout de documentos

Rota: `/admin/modelos-documentos`

Cadastro de modelos e layouts de documentos/contratos/textos editáveis usados pela secretaria.

### Matrículas

Rota: `/admin/enrollment`

Processo de matrícula/rematrícula. Inclui criação, edição, contrato, status, cancelamento e painel de score.

### Configuração de Matrícula

Rota: `/admin/enrollment/config`

Configurações do processo de matrícula/rematrícula.

### Movimentação de alunos

Rota: `/admin/students/remanejamento`

Fluxos de remanejamento e transferência escolar de alunos.

### Ocorrências

Rota: `/admin/ocorrencias`

Registro central de ocorrências do aluno. Permite cadastrar, acompanhar status, anexos, comunicação com pais e encaminhamentos.

### Recursos Físicos

Grupo que reúne almoxarifado e patrimônio quando o módulo `recursos_fisicos` está habilitado.

### Almoxarifado

Rota: `/admin/almoxarifado`

Gestão de itens, depósitos, fornecedores, movimentações, requisições e atendimento/aprovação de requisições.

### Patrimônio

Rota: `/admin/patrimonio`

Cadastro e controle de bens patrimoniais, ambientes, movimentações e conferências.

### Resultados Finais

Rota: `/admin/resultados-finais`

Fechamento de resultados por turma/aluno. Inclui homologação, casos especiais, atas, fichas, boletins e relatórios.

### Vida Escolar

Rota: `/admin/vida-escolar`

Prontuário/vida escolar do aluno, com boletim, documentos, importações, histórico, dossiê e pacote de transferência.

### Ofícios

Rota: `/admin/vida-escolar/oficios`

Emissão e gestão de ofícios ligados à vida escolar.

### Saúde Acadêmica

Rota: `/admin/saude-academica`

Painel de acompanhamento acadêmico, com visão de aprendizagem e sinais de atenção por aluno/turma.

### TudiCoins da Escola

Rota: `/admin/tudicoins`

Carteira de créditos/TudiCoins da escola. Permite comprar, pagar, verificar e acompanhar status.

### Pacotes de TudiCoins

Rota: `/admin/creditos/pacotes`

Configuração dos pacotes de TudiCoins disponíveis para compra/uso.

## Monitoramento

Rota do hub: `/admin/monitoramento-escolar`

Central de acompanhamento de segurança, presença online e eventos sensíveis.

### Alertas Sensíveis

Rota: `/admin/monitoramento/alertas`

Fila de alertas sensíveis gerados por uso da plataforma. Permite analisar, atualizar status e ver conteúdo relacionado.

Visibilidade: perfis `dev`, `diretor` e `coordenador`; não aparece para `financeiro` e `secretaria`.

### Alunos Online

Ação: abre modal via `abrirModalAlunosOnline()`

Mostra alunos online em tempo real, usando endpoints como `/admin/api/alunos-online` e stream.

### Tentativas de login

Rota: `/admin/tentativas-login`

Lista tentativas de login para auditoria/segurança.

## Pedagógico

Rota do hub: `/admin/pedagogico`

Central de recursos pedagógicos e produção/gestão de conteúdo didático.

### Aulas Online

Rota: `/admin/aulas-online`

Gestão de aulas online: criação, edição, sala/chat, anexos, integração, gravações e exclusão.

### AVA / EAD

Rota: `/admin/ava`

Ambiente virtual de aprendizagem: cursos, categorias, períodos/semestres, disciplinas, avaliações, módulos, aulas e anexos.

### BNCC / Plano de Curso

Rota: `/admin/bncc`

Consulta/importação de habilidades BNCC e apoio ao planejamento curricular. O plano de curso possui rotas próprias em `/admin/plano-curso`.

### EducaCursos

Rota: `/admin/minicursos`

Gestão de minicursos, módulos e aulas.

### Plano de Aula

Rota: `/admin/planos-aula`

Acompanhamento administrativo dos planos de aula dos professores, com visualização, edição, aprovação/rejeição e PDF.

## Sistema

Rota do hub: `/admin/sistema`

Central de configurações funcionais relacionadas à experiência do sistema.

### Avatares dos Alunos

Rota: `/admin/avatares-alunos`

Upload, listagem e exclusão de avatares disponíveis para alunos.

### Configuração de Prompt

Rota: `/admin/redacao-configuravel`

Configuração avançada da redação: bancas, tipos textuais, critérios, prompts e permissões de propostas.

### Tickets

Rota: `/admin/dev/tickets`

Fila de tickets de suporte. Permite ver, responder e fechar tickets.

Visibilidade: somente perfil `dev`.

## Usuários

Rota do hub: `/admin/gestao-usuarios`

Central de usuários administrativos e permissões.

### Administradores

Rota: `/admin/usuarios`

Cadastro e manutenção de usuários administradores da escola, incluindo avatar e senha.

### Monitores

Rota: `/admin/monitors`

Cadastro e manutenção de usuários monitores.

### Perfis de Permissão

Rota: `/admin/permissoes-perfis`

Cadastro e edição de perfis de permissão usados para controlar o que cada usuário administrativo pode visualizar/alterar.

## Z-Configuração

Rota do hub: `/admin/z-configuracao`

Área de configurações avançadas, geralmente para direção/dev.

### Dev Settings

Rota: `/admin/dev`

Painel técnico de configurações avançadas: integrações, módulos, aparência, IA, conteúdo, operação, PWA, layout mobile, custos LLM, logs, logins, migrations, SSH, métricas e webhooks.

Visibilidade: somente perfil `dev`.

### Instituição

Rota: `/admin/unidades`

Cadastro de unidades da escola, matriz/filial e dados institucionais.

### Modo Manutenção

Rota: `/admin/maintenance/painel`

Ativa/desativa modo manutenção para a escola.

### Slider Dashboard

Rota: `/admin/settings#slider-dashboard`

Configuração de banners/slides exibidos no dashboard.

### UI Modelos

Rota: `/admin/configuracao/ui-modelos`

Área demonstrativa do design system do Admin: botões, tabelas, formulários, offcanvas, badges e wizard.

Visibilidade: somente perfil `dev`.

## Menu Secretaria

O perfil `secretaria` usa um menu reduzido. Ele mantém os grupos:

- Dashboard;
- Acadêmico;
- Avaliações;
- Gestão Escolar;
- Sair.

Principais diferenças:

- não exibe Comunicação, Conteúdo, Financeiro, Monitoramento, Pedagógico, Sistema, Usuários e Z-Configuração;
- remove itens que não estejam permitidos em `AdminSecretariaAccess`;
- foca em alunos, ano letivo, curso, série, turmas, professores, matriz, regras acadêmicas, boletins, avaliações/notas, redação, jornadas, relatórios, censo, conselho, diário/faltas/presença, ocorrências, vida escolar, saúde acadêmica, recursos físicos e movimentação de alunos.

## Menu Financeiro

O perfil `financeiro` usa um menu reduzido com:

- Financeiro;
- subitens financeiros;
- Sair.

Esse perfil não navega pelos demais grupos administrativos.

## Sair

Rota: `/logout?portal=admin`

Encerra a sessão do usuário no portal Admin.

