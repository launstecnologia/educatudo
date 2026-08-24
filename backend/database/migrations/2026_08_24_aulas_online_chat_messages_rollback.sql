-- Rollback: remove tabela de chat das aulas online (só se esta migration tiver sido a que a criou).
DROP TABLE IF EXISTS `aulas_online_chat_messages`;
