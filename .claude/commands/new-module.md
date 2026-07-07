---
description: Cria a estrutura de um módulo novo seguindo o padrão EducaTudo
---

Crie o módulo **$ARGUMENTS** seguindo o checklist do CLAUDE.md:

1. Antes de codar, pergunte (se não estiver claro): quais perfis acessam (aluno/professor/admin), se é opcional por escola, e se precisa de migration.
2. Crie a estrutura em `src/`:
   - `app/Controllers/<Modulo>/` — um controller por perfil (`AdminXController`, `StudentXController`, `TeacherXController`), só coordenação, sem lógica de negócio.
   - `app/Models/<Modulo>/` — entidade principal + relacionadas, no padrão de `.claude/examples/ExampleModel.php`.
   - `app/Services/<Modulo>Service.php` — toda a lógica de negócio, mesmo que pequena (não replicar o antipadrão dos módulos antigos sem Service).
   - `app/Views/<perfil>/<modulo>/` — views por perfil.
3. Rotas em `src/config/routes/<perfil>.php` (nunca direto em `routes.php`).
4. Se opcional por escola: registrar em `src/app/Core/FeatureGate.php`.
5. Se precisar de tabela: migration `NNN_descricao.sql` + `NNN_descricao_rollback.sql` em `src/database/migrations/`, depois invocar o subagent **migration-checker**.
6. Nomes em inglês. Ao final, invocar o subagent **code-reviewer**.
