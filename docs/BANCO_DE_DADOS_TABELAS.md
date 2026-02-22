# Documentação das Tabelas do Banco de Dados – EducaTudo

Este documento descreve **todas as tabelas** do banco `educatudo_bd_educatudo`, a finalidade de cada uma e onde estão vinculadas no código (módulos/controllers/models).

**Data da análise:** fevereiro/2026.  
**Fonte:** `src/educatudo_bd_educatudo.sql` + varredura em `src/app/**/*.php`.

---

## Índice por módulo

1. [Usuários e autenticação](#1-usuários-e-autenticação)
2. [Alunos e segurança](#2-alunos-e-segurança)
3. [Chat Tudinha e conversas](#3-chat-tudinha-e-conversas)
4. [Alertas sensíveis e LGPD](#4-alertas-sensiveis-e-lgpd)
5. [Análises Tudinha](#5-análises-tudinha)
6. [Caderno do aluno](#6-caderno-do-aluno)
7. [Chat professor–aluno](#7-chat-professoraluno)
8. [Jogos](#8-jogos)
9. [Redações e temas](#9-redações-e-temas)
10. [Exercícios e listas](#10-exercícios-e-listas)
11. [Provas e simulados](#11-provas-e-simulados)
12. [Questões e ENEM](#12-questões-e-enem)
13. [Jornadas pedagógicas](#13-jornadas-pedagógicas)
14. [Planos de aula e grade](#14-planos-de-aula-e-grade)
15. [Fórum](#15-fórum)
16. [Flashcards](#16-flashcards)
17. [Minicursos](#17-minicursos)
18. [Arquivos e drive](#18-arquivos-e-drive)
19. [Notificações e push](#19-notificações-e-push)
20. [Integrações (EducaLabs, Notes, Inglês)](#20-integrações)
21. [Redação ENEM / Essay](#21-redação-enem--essay)
22. [Sistema e configurações](#22-sistema-e-configurações)
23. [Tabelas não utilizadas ou legado](#23-tabelas-não-utilizadas-ou-legado)

---

## 1. Usuários e autenticação

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **usuarios** | Usuários do sistema (admin, coordenador etc.): login, tipo, perfil. | Auth (AuthController, Auth, AuthManager), AdminController, UserController, layouts, vários controllers. |
| **alunos** | Cadastro de alunos: nome, email, RA, turma, série, responsável, status. | StudentController, AuthController, AdminController, CadernoController, AvatarController, Drive, Forum, Exercises, Journeys, ExamController, ClassController, Api, etc. |
| **professores** | Cadastro de professores. | TeacherController, AuthController, AdminController, ChatController, GradeHorariaController, ExamController, Files, Layouts. |
| **pais** | Responsáveis pelos alunos. | ParentController, AuthController, AdminController, Api. |
| **turmas** | Turmas/classes. | AdminController, ClassController, Forum, JourneyController, ExamController, TeacherController, StudentController, vários views. |
| **materias** | Matérias/disciplinas. | JourneyController, ExamController, GradeHorariaController, Files, CadernoController, AdminController, Subject model. |
| **login_attempts** | Tentativas de login (controle de bloqueio/segurança). | AuthController, Auth. |
| **password_resets** | Tokens para redefinição de senha. | AuthController. |
| **sessoes** | Sessões de usuário (PHP/sessão ou persistidas). | Auth, controle de sessão. |
| **user_consents** | Consentimento do usuário (LGPD/termos). | Layouts (student, professor, admin), verificação de aceite. |
| **audit_logs** | Log de ações (quem acessou o quê, payload). | AuditMiddleware, Logger, AdminController (relatórios/logins). |

---

## 2. Alunos e segurança

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **alunos_seguranca** | Pergunta/resposta de segurança para recuperação de acesso do aluno. | AuthController (cadastro da pergunta, validação no reset). |
| **alunos_turmas_historico** | Histórico de vínculo aluno–turma (datas início/fim, ano letivo). | AdminController (transferência de turma, relatórios, listagem de alunos). |
| **aluno_acoes_diarias** | Contagem de ações diárias por tipo (ex.: chat_interacoes, gerar_tema_redacao, exercicios) para limites. | StudentController, ExerciseController (INSERT e consultas de limite). |
| **onboarding_alunos** | Progresso do onboarding do aluno (etapas concluídas). | OnboardingController, StudentController, AvatarController. |
| **student_status_history** | Histórico de mudança de status do aluno (ex.: ACTIVE, INACTIVE). | StudentStatusService. |

---

## 3. Chat Tudinha e conversas

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **conversas_chat** | Conversas do aluno com a Tudinha: título, matéria, total de interações, excluída. | StudentController, AdminController, StudentStatusService (listagem, criação, exclusão lógica, relatórios). |
| **mensagens_chat** | Mensagens de cada conversa (aluno/IA, tipo, image_url). | StudentController (envio, listagem, contagem para análise), AdminController, StudentStatusService. |

---

## 4. Alertas sensíveis e LGPD

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **alertas_sensiveis** | Alertas de conteúdo sensível (saúde emocional, violência, linguagem) vindos do chat; vínculo com mensagem e retenção LGPD. | ChatSafetyMonitorService (INSERT), AdminController (listagem, atualização de status), StudentStatusService (anonimização), admin_sidebar (contador). |
| **alertas_sensiveis_acoes** | Ações tomadas sobre cada alerta (quem agiu, observações). | AdminController (INSERT ao tratar alerta), StudentStatusService (UPDATE/anonimização). |

---

## 5. Análises Tudinha

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **analises_tudinha** | Análises geradas por IA sobre o aluno (dificuldades, facilidades, recomendações) até uma data. | AdminController (geração/inserção), StudentStatusService (UPDATE para anonimização/retenção). |

---

## 6. Caderno do aluno

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **caderno_aluno** | Anotações do aluno: título, matéria, pasta, observação. | CadernoController (CRUD completo). |
| **caderno_aluno_pastas** | Pastas do caderno (organização). | CadernoController (CRUD de pastas, listagem). |
| **caderno_aluno_anexos** | Anexos e anotação em canvas por item do caderno. | CadernoController (upload, listagem, edição de anotação, exclusão). |

---

## 7. Chat professor–aluno

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **chat_professor_aluno** | Sala de chat entre um professor e um aluno. | ChatController (abertura de sala, listagem, atualização). |
| **chat_professor_aluno_mensagens** | Mensagens da sala professor–aluno. | ChatController (envio, listagem, marcação lida). |
| **chat_professor_aluno_anexos** | Anexos das mensagens do chat professor–aluno. | ChatController (upload, listagem). |

---

## 8. Jogos

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **games_tokens** | Tokens de acesso ao ambiente externo de jogos (SSO, expiração, usado). | GameController (geração e validação), EducaLabsController (validação de token). |
| **game_sessions** | Sessão ativa do aluno no Jogo do Milhão (partida, token, expires_at). | GameSecurityMiddleware (criação e validação). |
| **game_actions** | Ações registradas durante o jogo (partida, aluno, action, timestamp). | GameSecurityMiddleware (INSERT e leitura para rate limit). |
| **partidas_jogo_milhao** | Partida do Jogo do Milhão: pontuação, pergunta atual, ajudas, status, datas. | MillionGameController, GameSecurityMiddleware (todo o fluxo do jogo). |
| **perguntas_jogo_milhao** | Banco de perguntas do Jogo do Milhão (nível, alternativas, ativa). | MillionGameController (sorteio e exibição). |
| **respostas_jogo_milhao** | Resposta do aluno por pergunta (partida, pergunta, acertou, ajuda usada, tempo). | MillionGameController, GameSecurityMiddleware. |
| **partidas_dama** | Partidas do jogo da dama (tabuleiro, vez, resultado, nível). | **Nenhum arquivo PHP** – rota `/jogos/damas` existe mas método não implementado; **legado**. |
| **estatisticas_dama** | Estatísticas do jogo da dama por aluno. | **Nenhum arquivo PHP** – **legado**. |

---

## 9. Redações e temas

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **redacoes** | Redações do aluno (tema, título, conteúdo, tipo, rascunho, tema_gerado etc.). | StudentController (criação, listagem, edição, exclusão, integração com tema/IA). |
| **temas_redacao** | Temas de redação disponíveis (ativo, texto do tema). | StudentController, AdminController (listagem de redações com tema). |

---

## 10. Exercícios e listas

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **lista_exercicios** | Lista de exercícios (nome, turma/matéria, etc.). | StudentController, ExerciseController, CustomExerciseController, Journeys. |
| **exercicios** | Itens de exercício vinculados a uma lista (ordem, enunciado, alternativas). | StudentController, ExerciseController, JourneyController, AdminController. |
| **sessoes_exercicios** | Sessão de realização de lista de exercícios (aluno, lista, started_at). | StudentController, ExerciseController. |
| **respostas_exercicios** | Resposta do aluno por exercício (sessão, exercicio_id, resposta, is_correct). | StudentController, ExerciseController. |
| **historico_exercicios** | Histórico/agregado de realização de exercícios. | Consultas de relatório/estatísticas (AdminController, JourneyController). |
| **listas_personalizadas** | Listas de exercícios personalizadas (por professor/IA). | CustomExerciseController, ExerciseController. |
| **listas_exercicios_personalizados** | Vínculo lista personalizada ↔ exercícios. | CustomExerciseController. |
| **sessoes_exercicios_personalizados** | Sessão de realização de lista personalizada. | CustomExerciseController. |
| **respostas_exercicios_personalizados** | Respostas em listas personalizadas. | CustomExerciseController. |
| **execucao_exercicios** | Registro de execução (aluno, lista). | Uso em fluxos de exercícios/listas. |
| **estatisticas_exercicios_aluno** | Estatísticas por aluno (matéria, série, totais, percentual). | Relatórios e dashboards. |
| **estatisticas_exercicios_turma** | Estatísticas por turma. | Relatórios. |
| **questoes** | Banco de questões (provas/simulados). | ExamController, MockExamController, blocos de prova. |
| **questoes_personalizadas** | Questões geradas/customizadas (ex. IA). | CustomExerciseController, JourneyController. |

---

## 11. Provas e simulados

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **provas** | Prova aplicada (nome, turma, data, etc.). | ExamController, TeacherExamController, AdminController, Models Exam. |
| **provas_blocos** | Blocos de prova (agrupamento de questões). | ExamController, ExamBlockController, ExamBlockModelController, Models. |
| **provas_blocos_professores** | Vínculo bloco–professor (matéria, número de questões). | ExamBlockModel, ExamBlock. |
| **provas_blocos_professores_turmas** | Turmas permitidas por bloco–professor. | Exam. |
| **provas_blocos_provas** | Associação bloco–prova. | Exam. |
| **provas_blocos_turmas** | Turmas do bloco. | Exam. |
| **provas_questoes** | Questões de cada prova (ordem, questão_id). | ExamController, TeacherExam. |
| **provas_alternativas** | Alternativas das questões na prova. | Exam. |
| **provas_realizacoes** | Realização da prova por aluno (início, fim, nota). | ExamController, StudentController (exams). |
| **provas_respostas** | Respostas do aluno por questão na realização. | ExamController. |
| **provas_turmas** | Turmas que podem fazer a prova. | Exam. |
| **provas_professores** | Professores responsáveis pela prova. | Exam. |
| **provas_final** | Dados finais/consolidados da prova. | Exam. |
| **simulados** | Simulados (tipo ENEM, etc.). | MockExamController, views de simulados. |
| **simulado_questoes** | Questões do simulado. | MockExamController. |
| **simulado_estatisticas** | Estatísticas do simulado. | MockExamController. |
| **config_simulados** | Configuração de simulados (nome, ativo). | MockExamController. |
| **blocos_modelo** | Modelos de bloco de prova (nome, descrição, criado_por). | ExamBlockModel, AdminController (blocos-modelo). |
| **blocos_modelo_professores** | Professores e matérias do modelo de bloco. | ExamBlockModel. |

---

## 12. Questões e ENEM

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **enem_exams** | Provas ENEM (ano, edição). | MockExamController, fluxos que usam questões ENEM. |
| **enem_disciplines** | Disciplinas do ENEM. | Estrutura ENEM. |
| **enem_questions** | Questões ENEM (exam_id, disciplina, enunciado, alternativa correta). | MockExamController, ExamController, JourneyController, AdminController. |
| **enem_alternatives** | Alternativas das questões ENEM. | Mesmos módulos que enem_questions. |
| **enem_question_files** | Arquivos (imagens) das questões ENEM. | Exibição de questões. |
| **questoes_enem** | Vínculo/espelho de questões ENEM para uso em provas/simulados. | ExamController, MockExamController. |
| **backup_enem_questions_20251028** | Backup de questões ENEM (data no nome). | **Nenhum arquivo PHP** – apenas backup; **não utilizado pela aplicação**. |

---

## 13. Jornadas pedagógicas

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **jornadas** | Jornada de aprendizagem (nome, descrição, turmas, datas). | JourneyController, TeacherJourneyController, AdminJourneyController, ContentBlockController. |
| **jornadas_aulas** | Aulas vinculadas à jornada. | TeacherJourneyController, AdminJourneyController. |
| **jornadas_blocos_conteudo** | Blocos de conteúdo da jornada. | JourneyController, TeacherJourneyController, AdminJourneyController. |
| **jornadas_duvidas** | Dúvidas dos alunos na jornada. | JourneyController. |
| **jornadas_exercicios** | Exercícios da jornada. | JourneyController, TeacherJourneyController. |
| **jornadas_materias** | Matérias da jornada. | TeacherJourneyController, AdminJourneyController. |
| **jornadas_mensagens** | Mensagens/avisos na jornada. | TeacherJourneyController, AdminJourneyController. |
| **jornadas_mensagens_anexos** | Anexos das mensagens. | TeacherJourneyController. |
| **jornadas_modulos** | Módulos da jornada (aula, exercício, vídeo, etc.). | JourneyController, TeacherJourneyController, AdminJourneyController. |
| **jornadas_modulos_documentos** | Documentos do módulo. | TeacherJourneyController, AdminJourneyController. |
| **jornadas_modulos_exercicios** | Exercícios do módulo. | JourneyController, TeacherJourneyController, AdminJourneyController. |
| **jornadas_modulos_textos** | Textos do módulo. | TeacherJourneyController, AdminJourneyController. |
| **jornadas_modulos_videos** | Vídeos do módulo. | TeacherJourneyController, AdminJourneyController. |
| **jornadas_progresso_alunos** | Progresso do aluno na jornada. | JourneyController, TeacherJourneyController, AdminJourneyController. |
| **jornadas_progresso_blocos** | Progresso por bloco. | JourneyController. |
| **jornadas_redacoes** | Redações vinculadas à jornada. | TeacherJourneyController, JourneyController. |
| **jornadas_redacoes_alunos** | Redações dos alunos na jornada. | JourneyController, TeacherJourneyController. |
| **jornadas_relatorios** | Relatórios da jornada. | AdminJourneyController, TeacherController. |
| **jornadas_resumos_alunos** | Resumos feitos pelo aluno. | TeacherJourneyController, JourneyController. |
| **jornadas_tempo_alunos** | Tempo gasto por aluno na jornada. | JourneyController. |
| **jornadas_tipos_blocos** | Tipos de bloco (aula, exercício, vídeo, etc.). | Estrutura de jornadas. |

---

## 14. Planos de aula e grade

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **planos_aula** | Planos de aula (objetivos, professor, matéria, datas). | LessonPlanController, TeacherJourneyController, TeacherController, ParentController, StudentController, ExamController, Api, FeatureGate, layouts. |
| **grade_horaria_aulas** | Grade horária (dia, horário, turma, professor, matéria, período). | GradeHorariaController. |

---

## 15. Fórum

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **forum_topics** | Tópicos do fórum (título, conteúdo, autor, matéria, turma). | ForumController, ForumTopic, ForumModerationController. |
| **forum_topic_turmas** | Turmas que podem ver o tópico. | ForumTopic. |
| **forum_replies** | Respostas aos tópicos. | ForumController. |
| **forum_attachments** | Anexos de tópicos/respostas. | ForumController, ForumAttachment. |
| **forum_reports** | Denúncias de conteúdo. | ForumController, ForumModerationController. |
| **forum_moderation_alerts** | Alertas de moderação (IA). | ForumModerationAlert, ForumModerationController. |
| **forum_votes** | Votos em tópicos/respostas. | Forum. |
| **forum_user_reputation** | Reputação do usuário no fórum. | Forum. |

---

## 16. Flashcards

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **flashcard_decks** | Baralhos de flashcards. | FlashcardController, FlashcardService, FlashcardDeck. |
| **flashcards** | Cartas (pergunta/resposta). | FlashcardController, FlashcardService, Flashcard. |
| **flashcard_templates** | Modelos de baralho. | FlashcardTemplate. |
| **flashcard_template_cards** | Cartas do modelo. | Flashcard. |
| **flashcard_explicacoes** | Explicações pedidas pelo aluno (“não entendi”). | FlashcardController, FlashcardExplicacao, ChatSafetyMonitorService (alertas). |

---

## 17. Minicursos

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **minicursos** | Cadastro de minicursos. | AdminMinicursoController, StudentMinicursoController. |
| **minicursos_arquivos** | Arquivos/links do minicurso. | AdminMinicursoController, StudentMinicursoController. |
| **minicursos_aulas** | Aulas do minicurso. | Minicursos. |
| **minicursos_modulos** | Módulos do minicurso. | Minicursos. |
| **minicursos_progresso** | Progresso do aluno no minicurso. | StudentMinicursoController. |

---

## 18. Arquivos e drive

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **drive_items** | Itens do drive (pastas e arquivos); owner aluno ou professor. | DriveController, DriveItem, DriveShare. |
| **drive_shares** | Compartilhamento de item (com quem, permissão). | DriveController, DriveShare. |
| **modulo_arquivos** | Módulos de arquivos do professor (turma, matéria, título). | TeacherFileController, StudentFileController. |
| **modulo_arquivos_anexos** | Anexos do módulo de arquivos. | TeacherFileController, StudentFileController. |
| **modulo_arquivos_turmas** | Turmas que podem ver o módulo. | TeacherFileController, StudentFileController. |

---

## 19. Notificações e push

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **notificacoes** | Notificações in-app (título, mensagem, tipo, destinatários). | NotificationController, TeacherNotificationController, UserNotificationController, Notification model. |
| **notificacoes_api** | Notificações criadas via API/manual. | NotificacaoApi, NotificacoesApiController. |
| **notificacoes_configuracoes** | Configurações de envio. | Notification. |
| **notificacoes_destinatarios** | Destinatários das notificações. | NotificationRecipient, Notification. |
| **notificacoes_historico** | Histórico de envio. | Notification. |
| **push_notificacoes** | Notificações push (OneSignal etc.). | PushNotificationController, PushNotification model, OneSignalService. |
| **push_notificacao_envios** | Registro de envios push. | PushNotification. |

---

## 20. Integrações

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **educalabs_tokens** | Tokens de acesso ao EducaLabs (SSO). | EducaLabsController. |
| **educalabs_projects** | Projetos do EducaLabs (código HTML/CSS/JS). | EducaLabsController. |
| **educalabs_messages** | Mensagens do chat no projeto EducaLabs. | EducaLabsController. |
| **notes_tokens** | Tokens para o módulo de anotações externo. | NotesTokenController, NotesController, EducaLabsController (validação). |
| **ingles_conversas** | Conversas do módulo de inglês (aluno). | EnglishController. |
| **ingles_mensagens** | Mensagens da conversa de inglês. | EnglishController. |
| **webhooks** | Webhooks configurados (admin). | WebhookController. |

---

## 21. Redação ENEM / Essay

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **essay_boards** | “Boards” de redação (nome, slug, ativo). | EssayBoard, EssayProposal, EssaySubmission, EssayPrompt, EssayTextType. |
| **essay_text_types** | Tipos de texto (dissertativo, etc.) por board. | EssayTextType, EssayProposal, EssaySubmission. |
| **essay_prompts** | Prompts de correção/geração por board e tipo. | EssayPrompt, EssayAIService, EssayCorrection. |
| **essay_criteria** | Critérios de correção (competências, peso). | EssayCriterion. |
| **essay_proposals** | Propostas de redação (tema, board, tipo). | EssayProposal, TeacherEssayController, StudentEssayController. |
| **essay_proposal_turmas** | Turmas da proposta. | EssayProposal. |
| **essay_proposal_students** | Alunos específicos da proposta. | EssayProposal. |
| **essay_submissions** | Redações entregues (proposta, aluno, conteúdo, status). | EssaySubmission, TeacherEssayController, StudentEssayController. |
| **essay_corrections** | Correção (notas, feedback, IA). | EssayCorrection, TeacherEssayController, EssayAIService. |
| **essay_correction_logs** | Log de alterações na correção. | EssayCorrection. |

---

## 22. Sistema e configurações

| Tabela | Finalidade | Onde está vinculada |
|--------|------------|----------------------|
| **layout_config** | Configurações gerais (chave–valor): URL dos jogos, EducaLabs, módulos ativos, etc. | LayoutHelper, OpenAIService, StudentController, EducaLabsController, GameController, admin dev. |
| **dev_settings** | Configurações de desenvolvimento (ex.: prompt de flashcards). | DevSetting, DevSettingsController, FlashcardService, FlashcardController. |
| **migrations_executadas** | Controle de migrações já rodadas. | MigrationRunner, AdminController (dev/migrations). |
| **escolas_database_config** | Configuração por escola (multi-tenant). | Uso em contexto de multi-escola. |
| **relatorios** | Definição de relatórios disponíveis (pais, admin). | ParentController, TeacherController, AdminController. |
| **tutoriais** | Tutoriais em vídeo (YouTube) para o professor. | AdminController (listar/salvar/deletar), TeacherController (tutoriais). |
| **professor_slides** | Slides gerados para o professor. | TeacherController, SlidesController. |
| **valores_cobrados_mensais** | Valores financeiros mensais (cobrança). | AdminController (financeiro). |
| **ocorrencias_aluno** | Ocorrências disciplinares/pedagógicas do aluno. | AdminController, ParentController. |
| **ocorrencias_aluno_itens** | Itens/alunos vinculados à ocorrência. | AdminController. |
| **tickets** | Tickets de suporte. | SupportTicketController. |
| **ticket_mensagens** | Mensagens do ticket. | SupportTicketController. |
| **noticias** | Notícias/avisos do sistema. | NoticiasController, RssService. |
| **sessoes_acesso_alunos** | Registro de acessos do aluno (sessão, IP, etc.). | Controle de acesso/auditoria. |
| **pontuacao_alunos** | Pontuação/gamificação do aluno. | MillionGameController, relatórios. |
| **avatares_alunos** | Avatar e objetivos do aluno. | AvatarController, layouts (student), EnglishController, student_sidebar. |
| **professor_ai_agents** | Agentes de IA do professor. | TeacherAIController. |
| **professor_ai_conversas** | Conversas com o agente. | TeacherAIController. |
| **professor_ai_documentos** | Documentos enviados ao agente. | TeacherAIController. |
| **professor_ai_documento_chunks** | Chunks dos documentos (RAG). | TeacherAIController. |
| **professor_ai_mensagens** | Mensagens da conversa com o agente. | TeacherAIController. |
| **llm_usage_log** | Uso de modelos (tokens, custo, tipo). | OpenAIService, EssayAIService, admin dev (custos-llm). |
| **senha_logs** | Log de alterações de senha. | Auditoria. |

---

## 23. Tabelas não utilizadas ou legado

| Tabela | Finalidade | Situação |
|--------|------------|----------|
| **partidas_dama** | Partidas do jogo da dama. | **Não referenciada em nenhum PHP.** Rota `/jogos/damas` existe mas o método `GameController@damas` não existe. **Legado.** |
| **estatisticas_dama** | Estatísticas do jogo da dama. | **Não referenciada em nenhum PHP.** **Legado.** |
| **backup_enem_questions_20251028** | Backup de questões ENEM. | **Não referenciada.** Apenas backup histórico; aplicação usa `enem_questions` / `questoes_enem`. |

---

## Resumo de vínculos por módulo (arquivos principais)

- **Auth:** AuthController, Auth, AuthManager, PasswordCheckMiddleware  
- **Alunos:** StudentController, AdminController, CadernoController, AvatarController, OnboardingController  
- **Chat Tudinha:** StudentController, ChatSafetyMonitorService, StudentStatusService  
- **Admin:** AdminController, layouts admin_sidebar, dev (tutoriais, layout, webhooks, migrations, etc.)  
- **Professores:** TeacherController, TeacherJourneyController, TeacherExamController, GradeHorariaController, ChatController, Files  
- **Pais:** ParentController, Api ParentController  
- **Jornadas:** JourneyController, TeacherJourneyController, AdminJourneyController, ContentBlockController  
- **Provas/Simulados:** ExamController, MockExamController, ExamBlockController, ExamBlockModelController, Models Exam*  
- **Exercícios:** ExerciseController, CustomExerciseController  
- **Jogos:** GameController, MillionGameController, GameSecurityMiddleware  
- **Redação ENEM:** TeacherEssayController, StudentEssayController, EssayAIService, Models Essay*  
- **Notificações:** NotificationController, PushNotificationController, NotificacaoApi, Models Notification*  
- **Drive:** DriveController, DriveItem, DriveShare  
- **Fórum:** ForumController, ForumModerationController, Models Forum*  
- **Flashcards:** FlashcardController, FlashcardService, Models Study Flashcard*  

Se alguma tabela aparecer em relatórios ou em código que não foi varrido (ex.: jobs, outros repositórios), vale incluir aqui depois.

---

*Documento gerado com base na análise estática do código e do dump do banco. Para alterações no schema, conferir migrações e `educatudo_bd_educatudo.sql`.*
