# PRD — EducaTudo

> Documento de requisitos de produto do sistema completo. Fonte da verdade sobre O QUE a plataforma faz e para quem.
> Como o sistema faz: @design.md e `.claude/docs/architecture.md`. Backlog: @tasks.md.
> Para features novas, preencher a seção "Feature em andamento" no final antes de implementar.

---

## 1. Visão

**EducaTudo** é um ecossistema educacional SaaS para escolas, colégios e instituições de ensino brasileiras que integra gestão escolar, conteúdo pedagógico e inteligência artificial no dia a dia da sala de aula.

**Proposta de valor por público:**
- **Escola**: plataforma completa white-label (domínio, logos, cores e módulos próprios) sem custo de infraestrutura por instituição.
- **Aluno**: tutora de IA disponível 24h (Tudinha), trilhas de aprendizagem, provas online e correção de redação nos moldes do ENEM.
- **Professor**: criação de conteúdo assistida por IA (provas, jornadas, slides, apostilas, agentes customizados) e correção automatizada.
- **Pais**: acompanhamento em tempo real da vida escolar do filho, incluindo app mobile.

**Modelo de negócio**: assinatura por escola (planos com limites de alunos/professores/tokens de IA) + sistema de créditos para funcionalidades de IA, com recarga via PIX (Asaas) e planos recorrentes.

---

## 2. Princípios de produto

1. **Cada escola é um universo isolado** — banco próprio, identidade visual própria, módulos ativados conforme o plano (`FeatureGate`).
2. **IA como camada transversal, não como módulo** — presente em chat, redação, provas, exercícios, slides, apostilas, ocorrências, grade horária e monitoramento de conteúdo sensível.
3. **Todo consumo de IA é medido e monetizável** — créditos por operação, limites diários configuráveis, consolidação de uso por escola.
4. **Cada perfil vê apenas o seu mundo** — permissões por perfil e, dentro do admin, por papel (diretor, coordenador, secretaria, financeiro, desenvolvedor).
5. **O responsável nunca edita, só acompanha** — perfis de pais e monitor são somente leitura.

---

## 3. Perfis (personas)

| Perfil | Acesso | O que busca |
|---|---|---|
| **Aluno** | Web `/` (+ PWA) | Estudar, fazer provas/redações, tirar dúvidas com IA, acompanhar o próprio desempenho |
| **Professor** | Web `/professor` | Criar conteúdo e avaliações, corrigir, acompanhar turmas |
| **Admin escolar** | Web `/admin` | Operar a escola: pessoas, estrutura acadêmica, notas, comunicação, financeiro |
| **Secretaria** | Web `/admin` (papel restrito) | Cadastros, matrículas, movimentação, frequência |
| **Monitor de sala** | Web `/monitor` | Supervisionar alunos em tempo real durante a aula (somente leitura) |
| **Pais/Responsável** | Web `/pais` + app mobile (API JWT) | Acompanhar desempenho, comunicados, entrada/saída do filho |
| **Master (SaaS)** | Web `/master` (banco master) | Gerir escolas, planos, faturamento, migrations e suporte |

---

## 4. Requisitos funcionais por domínio

### 4.1 Aprendizagem (aluno + professor)

**Jornada do Aluno** (flag `jornadas`)
- Professor monta trilhas com módulos: vídeos (YouTube), documentos, textos, exercícios, resumos e redações — do zero ou com auxílio de IA.
- Aluno avança no próprio ritmo, com progresso por etapa; gabarito e conteúdo permanecem acessíveis após o prazo.
- Professor responde dúvidas, corrige resumos com nota e acompanha progresso individual; relatórios consolidados por turma.
- Status de jornadas vencidas atualizado por cron diário.

**Provas online** (flag `provas`)
- Tipos de questão: múltipla escolha, V/F, dissertativa; pesos e níveis de dificuldade por questão.
- Criação assistida por IA a partir de tema; importação de questões por imagem; banco de questões reutilizável por escola.
- Aplicação com cronômetro e modo seguro anti-cola (bloqueia navegação fora da página); respostas salvas em tempo real.
- Resultado imediato ou liberado após todos finalizarem; correção manual de dissertativas pelo professor.
- **Blocos de prova**: agrupamento de provas de várias matérias num período ("Prova Bimestral"); resultados consolidados, relatório aluno×questão, exportação Excel, impressão, gabarito liberável, blocos-modelo reutilizáveis.
- **EducaInclui** (flag `inclusao`): versões adaptadas/acessíveis das provas para alunos com necessidades específicas.

**Redação** (flag `redacoes`)
- **Jornada da Redação**: professor cria propostas com banca, tipo textual, textos motivadores e critérios; envio por turma ou aluno; repertório argumentativo gerado por IA.
- **Redação livre**: aluno escreve a partir de tema livre ou gerado por IA.
- Correção por IA nos moldes do ENEM (nota por competência + sugestões), pelo professor, ou híbrida; feedback em áudio (ElevenLabs).
- Redação manuscrita: foto → OCR (Google Vision/Supabase) → texto estruturado → correção.
- Relatórios de envios e correções para professor e coordenação; limites de correção configuráveis por escola.

**Exercícios** (flag `exercicios`)
- Listas do banco de questões da escola, organizadas por matéria e assunto.
- Geração sob demanda por IA: aluno escolhe tema, quantidade e dificuldade; feedback imediato com gabarito e explicação.

**Simulados** (flag `simulados`)
- Provas preparatórias formato ENEM a partir de base central de bancas/questões mantida pelo Master; acompanhamento de desempenho.

**Complementares**
- **Flashcards** (flag `aluno_flashcards`): decks gerados por IA por tema; explicação detalhada sob demanda.
- **Minicursos / EAD** (flag `ead`): cursos complementares com aulas em vídeo; AVA com cursos, matrículas, avaliações, certificados e aulas ao vivo (JaaS/Jitsi) com gravação.
- **Livros** (flag `educa_livros`): acervo digital via Google Books.
- **Jogos educativos** (flag `jogos`): xadrez, quiz com IA e outros, com middleware de segurança próprio.
- **EducaLabs** (flag `educalabs`): criação de projetos pelo aluno com auxílio de IA; API de validação de token para apps externos.
- **Inglês** (flag `ingles`): conversação com IA, transcrição de áudio (Whisper).
- **Caderno** (flag `aluno_caderno_novo`): anotações pessoais com editor e quadro branco.
- **Drive** (flag `drive`): arquivos pessoais do aluno (storage privado por tenant).
- **Plano de aula** (flag `planos_aula`): professor publica objetivos/conteúdos/recursos com exportação PDF; aluno, pais e coordenação consultam.

### 4.2 Inteligência artificial

- **Chat Tudinha**: tutora de IA em linguagem natural (OpenAI GPT-4o/4o-mini); mantém contexto; aceita imagens de caderno/livro (OCR) e responde por voz (TTS).
- **TudinhaProf / Agentes de IA** (flag `ai_agents`): professor cria agentes customizados com upload dos próprios materiais.
- **Gerador de slides** (flag `gerar_slides`): apresentações via integração Gamma.
- **Apostilas com IA**: geração de material didático personalizado (professor e aluno).
- **Monitoramento de conteúdo sensível**: IA detecta situações de risco nas interações; alertas para a coordenação.
- **Processamento assíncrono obrigatório**: toda operação de IA > 2s roda via fila (`AIJobService`, cron a cada 1 min). Uso de tokens consolidado diariamente por escola.

### 4.3 Gestão escolar (admin/secretaria)

- **Pessoas**: cadastro completo de alunos (importação Excel/CSV, responsáveis, status ativo/pagante), professores, admins e monitores; papéis de admin com permissões diferenciadas (`AdminPermissionMatrix`).
- **Estrutura acadêmica**: ano letivo, cursos, séries, turmas, matérias, grade horária (importável por imagem com leitura por IA); movimentação/transferência de alunos entre turmas.
- **Avaliação e notas**: regras de médias e composição de boletim configuráveis (`BoletimConfig`); notas consolidadas por turma/aluno/matéria; faltas e frequência.
- **Ocorrências disciplinares**: registro com transcrição de áudio por IA.
- **Saúde acadêmica**: indicadores consolidados de risco/desempenho por aluno.
- **Controle de acesso físico**: reconhecimento facial de entrada/saída, pareamento de dispositivos de portaria, histórico visível para os pais.
- **Monitoramento**: alunos online em tempo real (WebSocket/SSE), tentativas de login, alertas sensíveis, dashboards de infraestrutura (CPU, memória, banco, consumo OpenAI).
- **Financeiro da escola**: dashboard de pagantes e mensalidades (quando habilitado).
- **Configurações (papel desenvolvedor)**: módulos ativos, prompts de IA, limites diários, chaves de API, SMTP, layout completo, PWA, webhooks, modo manutenção.

### 4.4 Comunicação

- **Mural de recados**: escola/professor → alunos e turmas.
- **Comunicação escolar**: comunicados individuais ou coletivos com anexos, respostas e confirmação de leitura.
- **Calendário escolar**: provas, reuniões, feriados e eventos, visível para alunos e pais.
- **Chat** (flag `chat`): aluno ↔ professor dentro da plataforma.
- **Fórum** (flag `forum`): discussões entre alunos com moderação e denúncias.
- **Notificações** (flag `notifications`): push (OneSignal), WhatsApp (Evolution API), e-mail (SMTP) e in-app; feed de notícias via RSS (cron).
- **Tickets de suporte**: aluno abre ticket → centralizado no painel Master, com resposta direta de lá.

### 4.5 Pais e app mobile

- Painel web `/pais` e app Flutter (repo `app/`) consumindo API REST `/api/*` autenticada por JWT (`JWTService`, contrato em `src/docs/API_PAIS_ROTAS_E_CAMPOS.md`).
- Seleção entre múltiplos filhos vinculados ao responsável.
- Somente leitura: desempenho em provas/exercícios, jornadas, redações corrigidas, boletim, planos de aula, mural, comunicados (com confirmação de leitura), calendário, histórico de entrada/saída facial.
- Push de comunicados, eventos e entrada/saída.

### 4.6 Monetização e créditos

- **Carteira de créditos** (`CreditosService`): saldo por usuário; operações de IA debitam créditos conforme precificação global definida pelo Master (por módulo de IA).
- **Recarga**: PIX via Asaas (webhook HMAC) e planos recorrentes com recarga mensal automática (cron).
- **EducaShop**: compra de recursos pelo professor com créditos.
- **Checkout de créditos por tenant** (`TenantCreditsCheckoutService`): escola compra créditos para distribuir.
- **Limites por plano**: alunos, professores e tokens de IA/mês, configurados por escola no Master.

### 4.7 Painel Master (operação SaaS)

- **Escolas/tenants**: criação com slug, domínio e banco próprios; configuração individual de identidade visual, módulos, chaves de API, e-mail, limites, créditos, links úteis, apps externos e tutoriais.
- **Exportar/importar configuração** de escola em JSON (templates entre escolas).
- **Entrar como**: acesso a qualquer usuário de qualquer escola via token HMAC de 5 min (`ENTRA_COMO_SECRET`), em nova aba, sem senha.
- **Migrations**: execução por escola, em lote ou seleção, com acompanhamento (`MigrationRunner`).
- **Financeiro do SaaS**: planos, precificação, faturamento, integração Asaas.
- **Base central**: bancas e questões de simulados compartilhadas entre escolas.
- **Suporte**: tickets de todas as escolas centralizados.
- **Infra**: monitoramento de conexões de banco, usuários master, configurações globais.

---

## 5. Matriz módulo × perfil (resumo)

| Domínio | Aluno | Professor | Admin | Pais | Monitor | Master |
|---|---|---|---|---|---|---|
| Jornadas | usa | cria/corrige | gere/relatórios | vê | vê | — |
| Provas/blocos | faz | cria/corrige | gere/consolida | vê notas | vê andamento | base central |
| Redação | escreve | propõe/corrige | gere/relatórios | vê | — | — |
| IA (chat, flashcards, exercícios) | usa | cria conteúdo | configura limites | — | — | precifica |
| Gestão escolar | — | vê suas turmas | opera | — | — | cria a escola |
| Comunicação | recebe/responde | envia | publica/modera | recebe/confirma | — | tickets |
| Créditos | consome/compra | consome/compra | habilita | — | — | precifica/fatura |

A disponibilidade real por escola depende das flags do `FeatureGate` (`jornadas`, `provas`, `redacoes`, `simulados`, `exercicios`, `ead`, `chat`, `forum`, `drive`, `jogos`, `ingles`, `inclusao`, `educalabs`, `educa_livros`, `ai_agents`, `gerar_slides`, `aluno_flashcards`, `aluno_caderno_novo`, `planos_aula`, `notifications`) e das permissões por papel de admin.

---

## 6. Requisitos não funcionais

| Categoria | Requisito |
|---|---|
| **Isolamento** | Banco de dados por escola; conexão PDO separada; vazamento entre tenants é falha crítica |
| **Segurança** | Prepared statements; CSRF em POST; ownership validado; MIME real em uploads; `TENANT_SLUG` no path físico; HMAC em webhooks; segredos só via env |
| **Desempenho** | IA > 2s sempre assíncrona; cache Redis para tenant (60s) e sessões; respostas de prova salvas em tempo real |
| **Disponibilidade** | Modo manutenção por escola; backup diário dos bancos (cron); rollback obrigatório em migrations |
| **Escalabilidade** | Multi-instância atrás de load balancer exige Redis (sessões compartilhadas); PHP-FPM síncrono (ver ESCALABILIDADE.md) |
| **Observabilidade** | Logs JSON estruturados com TENANT_ID; auditoria (`AuditMiddleware`); dashboards de infra no admin/master |
| **Privacidade** | Dados de menores: acesso dos pais restrito aos próprios filhos; monitor sem escrita; alertas sensíveis restritos à coordenação |
| **Plataforma** | Web responsiva + PWA; app mobile dos pais (Flutter, Android/iOS) |

---

## 7. Integrações externas

| Serviço | Função no produto |
|---|---|
| OpenAI | Chat, geração de questões/exercícios/flashcards, correção de redação, OCR, Whisper (áudio), TTS (voz), DALL-E, conteúdo sensível |
| Google Vision / Supabase | OCR de redação manuscrita |
| ElevenLabs | Feedback de redação em áudio |
| Gamma | Geração de slides |
| Asaas | PIX, assinaturas e cobranças (créditos/planos) |
| JaaS (Jitsi) | Aulas ao vivo com gravação |
| OneSignal | Push web e mobile |
| Evolution API | Notificações WhatsApp |
| Google Books | Acervo de livros |
| AWS S3 | Storage de mídia (opcional; padrão local) |
| WebSocket / SSE | Presença online e notificações em tempo real |

---

## 8. Fora de escopo (por ora) / roadmap

- **EducaTudo B2C** — aluno se cadastra direto, sem escola, pagando por plano/créditos (proposta em `docs/educatudo-b2c.md`). Não implementar sem decisão explícita.
- Migração para runtime assíncrono (Swoole/RoadRunner) — bloqueada pelo singleton de conexão (ver design.md §2).
- Renomeação em massa PT→EN de código legado — fazer gradualmente ao tocar nos arquivos (ver tasks.md).

---

## 9. Feature em andamento

### Diário de Classe — Reestruturação (Fase 1)

**Objetivo**: reorganizar o Diário de Classe — módulo já existente (`diario_aulas`/`diario_frequencias`, `ClassDiaryController`/`ClassDiaryAdminController`/`FrequencyService`) — para que ele seja o ponto central de acompanhamento da execução pedagógica de cada vínculo **Turma + Componente Curricular + Professor + Ano Letivo** ("diário lógico", sem cadastro manual — nasce do vínculo Grade Horária). Reorganiza a UI e remove uma divergência de dados (nota lançada na chamada); não recria nenhum dos módulos que já são fonte oficial de outro dado (Plano de Aula, Frequência, Notas).

**Perfis**: Professor (`/professor`) opera; Admin/Coordenação (`/admin`) acompanha e pode reabrir diário fechado. Pais/Monitor não são afetados nesta fase.

**Telas**:
- **Diários de Classe** (nova, lista): filtros (Ano Letivo, Período/Bimestre, Turma, Professor, Componente Curricular, Situação); colunas Turma/Componente/Professor/Aulas/Chamadas/Pendências/Situação; ação "Abrir Diário".
- **Diário — tela interna** (nova estrutura de abas sobre a rota existente de chamada):
  - **Resumo**: indicadores (aulas previstas/realizadas, chamadas pendentes, eventos de nota concluídos) + lista de pendências.
  - **Aulas**: lista vinda de Grade Horária, situações Futura/Pendente/Rascunho/Finalizada/Não realizada; abre o registro individual reaproveitando a tela atual de chamada.
  - **Registro da aula** (tela já existente, ajustada): cabeçalho (componente/turma/nº aula/data/horário/professor/sala), Plano de Aula vinculado só para consulta (heurística atual de match por data mantida nesta fase), chamada **sem campo de nota**, execução da aula (conforme planejado/parcial/alterado/não realizada), observações, finalizar/reabrir.
  - **Frequência**: consolidação automática via `FrequencyService` (mesma fonte, sem tela redundante); "Lançar Faltas" avaliado à parte (módulo legado `faltas_*` fica fora de escopo desta fase — ver Decisões).
  - **Avaliações e Notas**: somente consulta aos Eventos de Nota (`provas_blocos`) do período; "Lançar notas" leva à tela real existente — nenhum lançamento novo dentro do Diário.
  - **Fechamento**: por bimestre, valida pendências (aulas/chamadas/notas) antes de permitir fechar; após fechado, edição exige reabertura pela coordenação.
- **Acompanhamento do Diário** (já existe, `admin/diario`): preservada como ferramenta gerencial da coordenação, passando a refletir a visão por diário.

**Dados**: reaproveita `diario_aulas`/`diario_frequencias` (tenant). Migration nova (tenant) para tabela de fechamento por período (turma+matéria+professor+ano_letivo+bimestre+status+quem fechou/reabriu) e ajuste do ENUM de situação de frequência (+ saída antecipada). Sem nova migration em `grade_horaria`, `faltas_*` ou `planos_aula` nesta fase.

**Decisões de escopo desta entrega (registradas em 2026-08-19, ver design.md)**:
- Módulo legado "Só Faltas" (`faltas_eventos`/`faltas_lancamentos`, `/admin/faltas`) **não** é unificado nem migrado nesta fase — continua coexistindo com `diario_frequencias` como fonte separada, decisão adiada como os outros pares ambíguos já registrados em `.claude/docs/nomenclatura.md`.
- Grade Horária **não** ganha `ano_letivo_id` nem cruzamento com `calendario_letivo_eventos` nesta fase — "aulas previstas" continua com o cálculo atual (conta dia-da-semana no período, sem descontar feriado/recesso), mesma limitação que já existe hoje.
- Nota é removida da **tela** de chamada, mas a coluna `diario_frequencias.nota` e os dados já lançados **não** são migrados/apagados nesta fase.
- Vínculo Plano de Aula ↔ Diário continua pela heurística textual atual (parse de datas do plano) — trocar por FK direta fica para depois, se necessário.
- Conselho de Classe (citado no menu da spec original) é módulo à parte, fora desta entrega.

### Matriz Curricular

**Objetivo**: formalizar o que cada série de um curso deve cursar — quais Componentes Curriculares, quantas aulas/semana, carga horária, obrigatoriedade — como configuração acadêmica própria. Hoje isso não existe: `materias` (Componentes Curriculares) é um catálogo plano por escola, sem relação com série nenhuma, e a associação turma↔matéria só existe implicitamente via `grade_horaria` (linha de horário).

**Perfis**: Admin/Secretaria cadastram a Matriz e os Componentes vinculados; ao criar/editar Turma, selecionam uma Matriz (campo novo, opcional). Professor/Coordenação só passam a se beneficiar indiretamente (turma já "sabe" o que deve ser lecionado) — sem tela própria nesta fase.

**Dados**:
- **Matriz Curricular**: nome, código, `curso_id` (FK `curso`), `serie_id` (FK `serie`), modalidade, turno, carga horária anual prevista, dias letivos previstos, duração padrão da aula (min), base legal/referência, observações, ativo.
- **Componentes da Matriz**: `matriz_id`, `materia_id` (FK `materias`/Componente Curricular), aulas por semana, obrigatório, ordem no boletim, ordem no histórico. Carga horária semanal/anual **calculada automaticamente** (aulas × duração da aula), não digitada.
- **Turma**: novo campo opcional `matriz_curricular_id` — só faz sentido/fica disponível quando a turma usa a "nova estrutura" já existente (`curso_novo_id` + `serie_id` preenchidos — ver `ClassController@store/update`); turmas no fluxo legado (texto livre) não vinculam matriz por enquanto.

**Decisões de escopo desta entrega (registradas em 2026-08-18, ver design.md)**:
- Referencia `curso`/`serie` **reais** — essas tabelas e a FK em `turmas` já existem (migrations 023/024/028/031), não é texto livre nem migration nova nessas duas tabelas.
- CRUD simples, editável livremente. **Sem** versionamento/trava (nova matriz imutável por ano) nesta fase — fica para uma entrega futura, quando o histórico escolar/boletim já estiver consumindo a matriz de verdade.
- **Sem** "Regras Acadêmicas" nesta entrega da Matriz (2026-08-18) — motor criado depois, ver seção abaixo.
- **Não** mexe no `serie` texto livre legado usado em Jornadas/Exercícios/Blocos de Prova/Histórico Escolar (~30 arquivos, com comparação por igualdade de string) nem na coluna `serie` duplicada em `alunos` — dívida técnica maior, registrada à parte em tasks.md.

### Regras Acadêmicas (aprovação, recuperação e boletim)

**Objetivo**: um motor configurável de regras acadêmicas (`ResultadoAcademicoService`) que transforma notas, frequência e o regimento da escola em situação acadêmica calculada. O boletim continua sendo visão/snapshot (`boletim_resultados_gerados`); não duplica nota. Sem regra cadastrada, o comportamento atual do evento de boletim (`nota_minima_aprovacao` → Aprovado/Reprovado) permanece.

**Perfis**: Admin/coordenação/secretaria cadastram em `/admin/regras-academicas`. Aluno, pais e coordenação só vêem o rótulo de situação no boletim/histórico já gerado.

**Dados** (tenant, migration `2026_08_22_regras_academicas.sql`):
- `regras_academicas`: vigência por ano letivo, curso/série/matriz/componente (exceção), tipo de período, média mínima, frequência mínima, arredondamento, fórmula de média/final, tipo e composição da recuperação, flags (só frequência, componente sem nota).
- `regras_academicas_historico`: snapshot imutável a cada gravação (versão, parâmetros JSON, responsável, data).

**Fora desta entrega**: Conselho de Classe, simulação de impacto (quantos aprovados/reprovados) sem gravar, mudança do calendário da escola de bimestre para trimestre/semestre no restante do sistema.

### Ocorrências do aluno

**Objetivo**: registro único da vida escolar do aluno, separado de nota, frequência e prontuário. Reusa `alunos_ocorrencias` (não cria tabela paralela).

**Perfis**: Admin/coordenação/secretaria cadastram e acompanham em `/admin/ocorrencias`. Professor inicia a partir da chamada do Diário (`/professor/ocorrencias/nova`). Pais só vêem o que foi marcado com `enviar_pais`.

**Dados** (tenant, migration `2026_08_22_ocorrencias_ciclo.sql`): categorias da escola (`ocorrencias_categorias`), status (`aberta` / `em_acompanhamento` / `encerrada`), snapshot de `turma_id`/`ano_letivo_id`, vínculo opcional `diario_aula_id`/`materia_id`, local, encaminhamento, data de comunicação com o responsável, histórico de ações. Gravidade e `enviar_pais` já existiam.

**Fora desta entrega**: anexos, relatórios, reincidência automática, Conselho de Classe, app/API JWT dos pais, IA/áudio no formulário novo (endpoints antigos preservados), mistura com alertas sensíveis ou ATA de reunião.

### Resultados finais (homologação e documentos oficiais)

**Objetivo**: um fechamento acadêmico homologado (snapshot imutável) alimenta boletim oficial, ficha individual, ata de resultados finais, histórico e relatórios. Não recalcula nota em cada PDF. A composição de notas continua em `boletim_resultados_gerados`; a homologação congela o resultado calculado pelo motor de regras + frequência do diário + deliberação vigente do conselho.

**Perfis**: Admin/coordenação/secretaria operam em `/admin/resultados-finais` (permissão `resultados_finais`). Direção reabre com motivo. Aluno/pais não emitem nesta entrega.

**Telas**: painel por turma (pendências críticas configuráveis), fechamento com homologação em lote, cadastro de situações especiais (dispensa, aproveitamento, progressão parcial, dependência), prévia e PDF da ficha/ata/boletim, relatórios (aprovados, reprovados, recuperação, frequência, desempenho, classificação, pendências, fechamento) em HTML/PDF/CSV, escolha de layout HTML por tipo de documento.

**Dados** (tenant, migration `2026_08_22_resultados_finais.sql`): `resultado_academico` (+ itens + histórico de versões), `resultado_situacoes_especiais`, `resultado_documento_layouts`, `resultado_fechamento_config`, `resultado_documento_emissoes`. Layouts seedados em `secretaria_modelos_documentos` (`resultado_*`); fallback PHP se o modelo não existir.

**Fora desta entrega**: substituir o workflow jurídico do Histórico Escolar (`historico_documentos` / assinatura / QR), emissão pelo portal dos pais, unificar o módulo legado de faltas, simulação de impacto sem gravar.

### Vida escolar (boletim e histórico oficiais)

**Objetivo**: boletim e histórico passam a ser fichas padrão que existem desde a matrícula (começam vazias) e são preenchidas ao longo do ano. A composição da média do bimestre (provas, trabalhos, exercícios, jornadas) continua no motor atual; a ficha é o documento. Transferência de entrada importa anos concluídos para escolarização e bimestres do ano atual para células externas, sem virar avaliação desta escola.

**Perfis**: Admin/secretaria/coordenação em `/admin/vida-escolar` e no prontuário do aluno. Professor não altera célula `externa` nem homologada. Aluno e pais vêem a ficha oficial. Direção reabre com motivo.

**Dados** (tenant, migration `2026_08_25_vida_escolar.sql`): `boletim_fichas` + linhas + células + auditoria; `escolarizacao_anos` + componentes; `vida_escolar_documentos` e `vida_escolar_importacoes`.

**Fora desta entrega**: composição ponderada 40/60 do bimestre partido, soma automática de frequência quando o documento só tem percentual.

### Painel de notas (quadro)

**Objetivo**: o Quadro de Notas é o molde do **painel** (grade de avaliações), não do boletim oficial. Aluno e pais veem tabelas por bloco (A/B), linhas nas matérias-filhas, células preenchidas automaticamente quando a prova ou o trabalho é lançado com o tipo/coluna certos. Coluna **calculada** fecha a nota da matéria (média ou pesos); `vai_para_boletim` marca o que o boletim oficial pode ler depois.

**Perfis**: Admin monta o quadro. Aluno em `/notas` e `/notas-boletins?secao=notas`. Pais em `/pais/filhos/{id}/notas`. Coordenação vê o mesmo painel na ficha do aluno.

**Dados** (tenant, migration `2026_09_12_quadros_colunas_papel.sql`): em `quadros_notas_colunas` (ou `grupos_regras_notas_marcas`): `papel` (`lancamento`|`calculada`), `tipo_nota_id`, `formula_json`, `vai_para_boletim`.

**Fora desta entrega**: boletim oficial já lendo `vai_para_boletim`; tela do professor com o mesmo painel; pesos na UI do wizard (a fórmula já aceita `pesos` no JSON).

