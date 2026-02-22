-- =============================================================================
-- REFATORAÇÃO DE NOMES DE TABELAS - EducaTudo
-- Execute este script APÓS aplicar as alterações no código PHP.
-- Faça backup do banco antes de executar.
-- =============================================================================

-- USUÁRIOS E AUTENTICAÇÃO
RENAME TABLE pais TO responsaveis;
RENAME TABLE login_attempts TO tentativas_login;
RENAME TABLE password_resets TO redefinicoes_senha;
RENAME TABLE user_consents TO usuarios_consentimentos;
RENAME TABLE audit_logs TO logs_auditoria;
RENAME TABLE senha_logs TO logs_senhas;

-- ALUNOS E SEGURANÇA
RENAME TABLE aluno_acoes_diarias TO alunos_acoes_diarias;
RENAME TABLE onboarding_alunos TO alunos_onboarding;
RENAME TABLE student_status_history TO alunos_historico_status;
RENAME TABLE sessoes_acesso_alunos TO alunos_sessoes_acesso;

-- CHAT TUDINHA
RENAME TABLE conversas_chat TO tudinha_conversas;
RENAME TABLE mensagens_chat TO tudinha_mensagens;
RENAME TABLE analises_tudinha TO tudinha_analises;

-- CADERNO DO ALUNO
RENAME TABLE caderno_aluno TO cadernos_aluno;
RENAME TABLE caderno_aluno_pastas TO cadernos_aluno_pastas;
RENAME TABLE caderno_aluno_anexos TO cadernos_aluno_anexos;

-- CHAT PROFESSOR-ALUNO
RENAME TABLE chat_professor_aluno TO chat_professores_alunos;
RENAME TABLE chat_professor_aluno_mensagens TO chat_professores_alunos_mensagens;
RENAME TABLE chat_professor_aluno_anexos TO chat_professores_alunos_anexos;

-- JOGOS
RENAME TABLE games_tokens TO jogos_tokens_externos;
RENAME TABLE game_sessions TO jogos_sessoes;
RENAME TABLE game_actions TO jogos_acoes;
RENAME TABLE partidas_jogo_milhao TO jogos_milhao_partidas;
RENAME TABLE perguntas_jogo_milhao TO jogos_milhao_perguntas;
RENAME TABLE respostas_jogo_milhao TO jogos_milhao_respostas;
RENAME TABLE partidas_dama TO jogos_dama_partidas;
RENAME TABLE estatisticas_dama TO jogos_dama_estatisticas;

-- REDAÇÕES
RENAME TABLE temas_redacao TO redacoes_temas;

-- EXERCÍCIOS E LISTAS
RENAME TABLE lista_exercicios TO listas_exercicios;
RENAME TABLE sessoes_exercicios TO exercicios_sessoes;
RENAME TABLE respostas_exercicios TO exercicios_respostas;
RENAME TABLE historico_exercicios TO exercicios_historico;
RENAME TABLE execucao_exercicios TO exercicios_execucoes;
RENAME TABLE listas_personalizadas TO listas_exercicios_personalizadas;
RENAME TABLE listas_exercicios_personalizados TO listas_personalizadas_exercicios;
RENAME TABLE sessoes_exercicios_personalizados TO listas_personalizadas_sessoes;
RENAME TABLE respostas_exercicios_personalizados TO listas_personalizadas_respostas;
RENAME TABLE estatisticas_exercicios_aluno TO exercicios_estatisticas_alunos;
RENAME TABLE estatisticas_exercicios_turma TO exercicios_estatisticas_turmas;

-- PROVAS E SIMULADOS
RENAME TABLE provas_blocos_provas TO provas_blocos_vinculo;
RENAME TABLE simulado_questoes TO simulados_questoes;
RENAME TABLE simulado_estatisticas TO simulados_estatisticas;
RENAME TABLE blocos_modelo TO provas_blocos_modelos;
RENAME TABLE blocos_modelo_professores TO provas_blocos_modelos_professores;

-- ENEM
RENAME TABLE enem_exams TO enem_provas;
RENAME TABLE enem_disciplines TO enem_disciplinas;
RENAME TABLE enem_questions TO enem_questoes;
RENAME TABLE enem_alternatives TO enem_alternativas;
RENAME TABLE enem_question_files TO enem_questoes_arquivos;
RENAME TABLE questoes_enem TO enem_questoes_vinculo;

-- PLANOS DE AULA
RENAME TABLE grade_horaria_aulas TO grade_horaria;

-- FÓRUM
RENAME TABLE forum_topics TO forum_topicos;
RENAME TABLE forum_topic_turmas TO forum_topicos_turmas;
RENAME TABLE forum_replies TO forum_respostas;
RENAME TABLE forum_attachments TO forum_anexos;
RENAME TABLE forum_reports TO forum_denuncias;
RENAME TABLE forum_moderation_alerts TO forum_moderacao_alertas;
RENAME TABLE forum_votes TO forum_votos;
RENAME TABLE forum_user_reputation TO forum_usuarios_reputacao;

-- FLASHCARDS
RENAME TABLE flashcard_decks TO flashcards_baralhos;
RENAME TABLE flashcards TO flashcards_cartas;
RENAME TABLE flashcard_templates TO flashcards_modelos;
RENAME TABLE flashcard_template_cards TO flashcards_modelos_cartas;

-- DRIVE E ARQUIVOS
RENAME TABLE drive_items TO drive_itens;
RENAME TABLE drive_shares TO drive_compartilhamentos;
RENAME TABLE modulo_arquivos TO modulos_arquivos;
RENAME TABLE modulo_arquivos_anexos TO modulos_arquivos_anexos;
RENAME TABLE modulo_arquivos_turmas TO modulos_arquivos_turmas;

-- NOTIFICAÇÕES
RENAME TABLE push_notificacoes TO notificacoes_push;
RENAME TABLE push_notificacao_envios TO notificacoes_push_envios;

-- SISTEMA E CONFIGURAÇÕES
RENAME TABLE layout_config TO config_layout;
RENAME TABLE dev_settings TO config_dev;
RENAME TABLE escolas_database_config TO config_escolas_database;
RENAME TABLE valores_cobrados_mensais TO financeiro_valores_mensais;
RENAME TABLE tickets TO suporte_tickets;
RENAME TABLE ticket_mensagens TO suporte_tickets_mensagens;
RENAME TABLE ocorrencias_aluno TO alunos_ocorrencias;
RENAME TABLE ocorrencias_aluno_itens TO alunos_ocorrencias_itens;
RENAME TABLE professor_slides TO professores_slides;
RENAME TABLE professor_ai_agents TO professores_ia_agentes;
RENAME TABLE professor_ai_conversas TO professores_ia_conversas;
RENAME TABLE professor_ai_documentos TO professores_ia_documentos;
RENAME TABLE professor_ai_documento_chunks TO professores_ia_documentos_chunks;
RENAME TABLE professor_ai_mensagens TO professores_ia_mensagens;
RENAME TABLE llm_usage_log TO logs_uso_llm;
