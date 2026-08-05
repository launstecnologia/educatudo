# Tasks — backlog atual

> Uma tarefa por linha. Claude trabalha de cima para baixo; mova para "Feito" ao concluir.
> Tarefas grandes: quebre em subtarefas antes de começar (Plan Mode).

## Em andamento

- [ ] Rodar a migration `2026_07_08_educainclui_pt.sql` (via painel Master ou `run_migrations.php`) em todas as escolas antes/junto do deploy do código atualizado — sem isso o módulo EducaInclui quebra (código já aponta pros nomes novos de tabela/coluna)
- [ ] Rodar migrations TudiCoins: `2026_07_09_tudicoins_carteira_escola.sql` (tenant) + `2026_07_09_tudicoins_educainclui_itens_master.sql` (master) + `2026_07_09_tudicoins_compras_escola.sql` (tenant)
- [ ] Agendar cron `backend/cron/tudicoins_recarga_mensal.php` (dia 1 do mês) para recarga B2B automática

## Próximas

- [ ] Expo Colag S5: mapa/escala de stand + área do responsável + galeria com moderação
- [ ] Mover `Noticia.php`, `RedacaoLivreEnvio.php`, `RedacaoLivreCorrecao.php` para subpastas de Models
- [ ] Extrair lógica de Controller para Service nos módulos sem Service (Games, Exercises, Apostilas, Minicursos, EducaHits) — fazer ao tocar no módulo
- [ ] Renomear módulos em inglês para português ao tocar em cada um (Finance, Enrollment, Inventory, Patrimony, School Calendar/Communication, Mobile*, Facial*, `Education/CourseCatalogController` etc.) — inventário completo e conflitos conhecidos em `.claude/docs/nomenclatura.md`. Não fazer como frente dedicada nem em lote.
- [ ] Decidir com o time se `matricula`/`enrollment` e `financeiro_valores_mensais`/`finance_*` devem ser mesclados ou mantidos como sistemas separados com nomes PT claros — pré-requisito antes de renomear qualquer uma dessas tabelas (ver `.claude/docs/nomenclatura.md`)

## Feito

<!-- mover itens concluídos para cá com a data -->
- [x] Expo Colag S1: módulo `app/Modulos/expo-colag/` (manifest, rotas, Models/Service/Controllers/Views), migration `2026_08_05_expo_colag_s1`, FeatureGate + Master `geral_expo_colag` (default off), menus Colag/admin, config da edição + rascunho de projeto — 2026-08-05
- [x] Expo Colag S2: wizard 6 blocos (criar/editar/publicar/preview), migration `2026_08_05_expo_colag_s2` (relações + autorização imagem), painel admin de autorizações — 2026-08-05
- [x] Expo Colag S3: mural com filtros/seções, inscrição com lock FOR UPDATE + lista de espera, conflitos de agenda, aprovar/recusar professor, CTA aluno — 2026-08-05
- [x] Expo Colag S4: painel professor (tarefas/materiais/stand), painel aluno, QR público `/expo-colag/s/{token}`, programação admin/aluno, migration `2026_08_05_expo_colag_s4` — 2026-08-05

- [x] TudiCoins base: docs (`.claude/docs/tudicoins.md`, regra Cursor, `/new-module`), registry com escopo/pagador, gate FeatureGate/Master, rename UI, pool + visibilidade aluno, carteira escola + EducaInclui (laudo/prova), migrations 2026_07_09 — 2026-07-09
- [x] TudiCoins fase 2: compra Asaas da escola (`/admin/tudicoins`), lock FOR UPDATE no débito, cron recarga mensal B2B, gates IA em provas/redação/essays/exercicio-form — 2026-07-09
- [x] Aplicar design system (padrão real do projeto, offcanvas para criar/editar) na tela Admin > Usuários (`app/Views/admin/usuarios/*`, `UsuarioController`) — 2026-07-07
- [x] Aplicar padrão offcanvas em Admin > Professores, Monitores, Perfis de Permissão e Instituição/Unidades — 2026-07-07
- [x] Transformar cadastro de Máscara de Acessibilidade (EducaInclui) em wizard de 5 etapas (`app/Views/admin/accommodations/manage.php`, `AccommodationController`) — code-reviewer aprovado; falta verificação manual em navegador — 2026-07-08
- [x] Sidebar admin: renomear "Versões adaptadas" para "Avaliação Adaptativa" (herda ícone do EducaInclui) e remover item de menu "EducaInclui" (`admin_sidebar.php`) — 2026-07-08
- [x] Detalhe do aluno: botão "EducaInclui / Laudo" abre offcanvas de resumo em vez de navegar (`students/show.php`, `AccommodationController::resumoJson`) — code-reviewer aprovado — 2026-07-08
- [x] Wizard EducaInclui: corrigir redirects de upload/análise/ativação/aprovação que voltavam pra Etapa 1; adicionar loading + sucesso/erro real (polling `AIJobPoller`) na análise de laudo por IA — code-reviewer aprovado — 2026-07-08
- [x] Rename piloto da padronização de nomenclatura (`.claude/docs/nomenclatura.md`): módulo EducaInclui de inglês para português — `AccommodationController`→`EducaIncluiController`, `StudentAccommodation`→`MascaraAluno`, `AccommodationRule`→`RegraMascara`, `AccommodationDocument`→`LaudoAluno`, `AssessmentVersion(Log)`→`VersaoAdaptada(Log)`, `AccommodationService`→`EducaIncluiService`, `AccommodationMaskResolver`→`MascaraResolver`, `AccommodationRuleCatalog`→`CatalogoRegraMascara`, pasta de view `accommodations`→`inclusao`; rotas atualizadas — code-reviewer aprovado — 2026-07-08
- [x] Segunda etapa do rename EducaInclui: tabelas e colunas do banco também para português (`student_accommodations`→`mascaras_alunos`, `accommodation_documents`→`laudos_alunos`, `accommodation_rules`→`regras_mascara`, `assessment_versions`→`versoes_adaptadas`, `assessment_version_logs`→`versoes_adaptadas_logs`, e ~25 colunas — ver `database/migrations/2026_07_08_educainclui_pt.sql`); código (Models/Controller/Services/Views + trechos de `ExamController`/`TeacherExamController`/`AIJobService`) atualizado para os nomes novos; migration validada por `migration-checker` (achou e corrigiu bug real no rollback) e code-reviewer aprovado — falta rodar a migration em cada escola (ver "Em andamento") — 2026-07-08
