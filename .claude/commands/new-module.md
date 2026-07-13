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
4. Se opcional por escola: registrar em `src/app/Core/FeatureGate.php` **e** nas listas `MODULOS_*` do Master (`MasterEscolaDetailController`).
5. Se o módulo (ou qualquer ação dele) usar IA/OCR: seguir `.claude/docs/tudicoins.md` e `.cursor/rules/tudicoins.mdc` —
   - chave em `CreditosModuleRegistry` (`escopo_ui`, `feature_modules`, `pagador`);
   - item nas tabelas de custo TudiCoins do Master;
   - débito via `CreditosService`;
   - se for 100% IA (`modulo_inteiro`), o Master só habilita com TudiCoins ligado.
6. Se precisar de tabela: migration `NNN_descricao.sql` + `NNN_descricao_rollback.sql` em `src/database/migrations/`, depois invocar o subagent **migration-checker**.
7. Nomenclatura em português (ver `.claude/docs/nomenclatura.md`). Ao final, invocar o subagent **code-reviewer**.
