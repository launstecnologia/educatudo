# Status da refatoração de nomes de tabelas

Refatoração conforme `PROMPT_CURSOR_REFATORACAO.md` (mapeamento antigo → novo).

## Concluído no código PHP

- **Usuários e autenticação:** `pais`→`responsaveis`, `login_attempts`→`tentativas_login`, `password_resets`→`redefinicoes_senha`, `user_consents`→`usuarios_consentimentos`, `audit_logs`→`logs_auditoria`, `senha_logs`→`logs_senhas`
- **Alunos:** `aluno_acoes_diarias`→`alunos_acoes_diarias`, `onboarding_alunos`→`alunos_onboarding`, `student_status_history`→`alunos_historico_status`, `sessoes_acesso_alunos`→`alunos_sessoes_acesso`
- **Chat Tudinha:** `conversas_chat`→`tudinha_conversas`, `mensagens_chat`→`tudinha_mensagens`, `analises_tudinha`→`tudinha_analises`
- **Caderno:** `caderno_aluno`→`cadernos_aluno`, `caderno_aluno_pastas`→`cadernos_aluno_pastas`, `caderno_aluno_anexos`→`cadernos_aluno_anexos`
- **Chat professor-aluno:** `chat_professor_aluno`→`chat_professores_alunos`, `chat_professor_aluno_mensagens`→`chat_professores_alunos_mensagens`, `chat_professor_aluno_anexos`→`chat_professores_alunos_anexos`
- **Jogos:** `games_tokens`→`jogos_tokens_externos`, `game_sessions`→`jogos_sessoes`, `game_actions`→`jogos_acoes`, `partidas_jogo_milhao`→`jogos_milhao_partidas`, `perguntas_jogo_milhao`→`jogos_milhao_perguntas`, `respostas_jogo_milhao`→`jogos_milhao_respostas`
- **Redações:** `temas_redacao`→`redacoes_temas`
- **Exercícios/listas:** `lista_exercicios`→`listas_exercicios`, `sessoes_exercicios`→`exercicios_sessoes`, `respostas_exercicios`→`exercicios_respostas`, `listas_personalizadas`→`listas_exercicios_personalizadas`, `listas_exercicios_personalizados`→`listas_personalizadas_exercicios`, `sessoes_exercicios_personalizados`→`listas_personalizadas_sessoes`, `respostas_exercicios_personalizados`→`listas_personalizadas_respostas`
- **Config:** `layout_config`→`config_layout`, `dev_settings`→`config_dev` (model DevSetting e LayoutHelper)

## Pendente no código PHP (aplicar manualmente ou em próxima etapa)

Substituir nos arquivos indicados pelo grep:

- **Provas/simulados:** `provas_blocos_provas`→`provas_blocos_vinculo`, `simulado_questoes`→`simulados_questoes`, `simulado_estatisticas`→`simulados_estatisticas`, `blocos_modelo`→`provas_blocos_modelos`, `blocos_modelo_professores`→`provas_blocos_modelos_professores` (ExamController, MockExamController, ExamBlockModel, ExamBlock, TeacherExamController)
- **ENEM:** `enem_exams`→`enem_provas`, `enem_disciplines`→`enem_disciplinas`, `enem_questions`→`enem_questoes`, `enem_alternatives`→`enem_alternativas`, `enem_question_files`→`enem_questoes_arquivos`, `questoes_enem`→`enem_questoes_vinculo`
- **Fórum:** `forum_topics`→`forum_topicos`, `forum_topic_turmas`→`forum_topicos_turmas`, `forum_replies`→`forum_respostas`, `forum_attachments`→`forum_anexos`, `forum_reports`→`forum_denuncias`, `forum_moderation_alerts`→`forum_moderacao_alertas`, `forum_votes`→`forum_votos`, `forum_user_reputation`→`forum_usuarios_reputacao`
- **Flashcards:** `flashcard_decks`→`flashcards_baralhos`, `flashcards`→`flashcards_cartas`, `flashcard_templates`→`flashcards_modelos`, `flashcard_template_cards`→`flashcards_modelos_cartas`
- **Drive/arquivos:** `drive_items`→`drive_itens`, `drive_shares`→`drive_compartilhamentos`, `modulo_arquivos`→`modulos_arquivos`, `modulo_arquivos_anexos`→`modulos_arquivos_anexos`, `modulo_arquivos_turmas`→`modulos_arquivos_turmas`
- **Notificações:** `push_notificacoes`→`notificacoes_push`, `push_notificacao_envios`→`notificacoes_push_envios` (PushNotification model e controllers)
- **Sistema:** `escolas_database_config`→`config_escolas_database`, `valores_cobrados_mensais`→`financeiro_valores_mensais`, `tickets`→`suporte_tickets`, `ticket_mensagens`→`suporte_tickets_mensagens`, `ocorrencias_aluno`→`alunos_ocorrencias`, `ocorrencias_aluno_itens`→`alunos_ocorrencias_itens`, `professor_slides`→`professores_slides`, `professor_ai_*`→`professores_ia_*`, `llm_usage_log`→`logs_uso_llm`
- **Grade:** `grade_horaria_aulas`→`grade_horaria`

## Script SQL

O arquivo **`docs/migrations/RENAME_TABELAS_REFATORACAO.sql`** contém todos os `RENAME TABLE` do mapeamento.

- Só execute o script **depois** de aplicar no código PHP todas as alterações correspondentes a essas tabelas.
- Faça backup do banco antes.
- Se uma parte do código ainda não foi refatorada, não renomeie essas tabelas no banco até concluir a refatoração no PHP.

## Observações

- **Rotas e URLs** como `/pais` (portal dos pais) **não** foram alteradas; apenas o nome da tabela no banco e nas queries.
- **Chaves de configuração** como `max_login_attempts` em `config/app.php` **não** foram alteradas (não são nomes de tabela).
- **Session keys** como `dev_settings_flash` em DevSettingsController foram mantidas (não são tabelas).
- Ordem usada para listas: primeiro `listas_exercicios_personalizados`→`listas_personalizadas_exercicios`, depois `listas_personalizadas`→`listas_exercicios_personalizadas`, para evitar que o segundo replace altere o primeiro nome.
