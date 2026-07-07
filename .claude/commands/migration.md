---
description: Cria migration de tenant ou master com rollback obrigatório
---

Crie uma migration para: **$ARGUMENTS**

1. Descubra o próximo número: liste `src/database/migrations/` e pegue o maior `NNN` (migrations recentes podem usar `YYYY_MM_DD_`; siga o padrão mais recente do diretório).
2. Decida o alvo: tabela de escola → migration de tenant (`NNN_descricao.sql`); tabela do SaaS (escolas, planos, billing) → `NNN_descricao_master.sql`.
3. Escreva a migration idempotente (`IF NOT EXISTS`, guards em `ADD COLUMN`), `utf8mb4`/InnoDB.
4. **Sempre** crie o `_rollback.sql` correspondente.
5. Invoque o subagent **migration-checker** para validar.
6. NÃO execute no banco — informe ao usuário que a execução é via `/master/migrations` ou `php src/scripts/run_migrations.php`.
