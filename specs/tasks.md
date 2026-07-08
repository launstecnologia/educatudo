# Tasks — backlog atual

> Uma tarefa por linha. Claude trabalha de cima para baixo; mova para "Feito" ao concluir.
> Tarefas grandes: quebre em subtarefas antes de começar (Plan Mode).

## Em andamento

- [ ]

## Próximas

- [ ] Mover `Noticia.php`, `RedacaoLivreEnvio.php`, `RedacaoLivreCorrecao.php` para subpastas de Models
- [ ] Extrair lógica de Controller para Service nos módulos sem Service (Games, Exercises, Apostilas, Minicursos, EducaHits) — fazer ao tocar no módulo
- [ ] Renomear services em PT para EN (`AlunoMovimentacaoService` → `StudentMovementService`, etc.)

## Feito

<!-- mover itens concluídos para cá com a data -->
- [x] Aplicar design system (padrão real do projeto, offcanvas para criar/editar) na tela Admin > Usuários (`app/Views/admin/usuarios/*`, `UsuarioController`) — 2026-07-07
- [x] Aplicar padrão offcanvas em Admin > Professores, Monitores, Perfis de Permissão e Instituição/Unidades — 2026-07-07
- [x] Transformar cadastro de Máscara de Acessibilidade (EducaInclui) em wizard de 5 etapas (`app/Views/admin/accommodations/manage.php`, `AccommodationController`) — code-reviewer aprovado; falta verificação manual em navegador — 2026-07-08
- [x] Sidebar admin: renomear "Versões adaptadas" para "Avaliação Adaptativa" (herda ícone do EducaInclui) e remover item de menu "EducaInclui" (`admin_sidebar.php`) — 2026-07-08
- [x] Detalhe do aluno: botão "EducaInclui / Laudo" abre offcanvas de resumo em vez de navegar (`students/show.php`, `AccommodationController::resumoJson`) — code-reviewer aprovado — 2026-07-08
