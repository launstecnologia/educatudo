# Padrões de código — referência rápida

> Versão condensada para consulta via `@`. A versão completa com contexto está no CLAUDE.md.

## Camadas

- **Controller**: recebe request, chama Service/Model, renderiza View. Máx ~50 linhas de lógica; acima disso, extrair Service.
- **Service**: lógica de negócio. Todo módulo novo tem um, mesmo pequeno.
- **Model**: acesso a dados. `$this->db = Database::getInstance()` no construtor; métodos `findById`, `findByX`, `create`, `update`, `delete`.
- **View**: PHP + Tailwind em `app/Views/<perfil>/<modulo>/`.

## SQL

- Prepared statements sempre — parâmetros nomeados (`:id`) são o padrão do projeto.
- Cast explícito de ids: `['id' => (int) $id]`.
- Nunca `WHERE escola_id` em query de tenant.

## Nomenclatura

- Classes/métodos/arquivos em inglês. Tabelas do banco ficam em PT (legado — não renomear).
- Exceções aceitas: `Simulados`, `BoletimConfig`, `GradeHoraria`.
- Controllers prefixados por perfil: `Admin*`, `Student*`, `Teacher*`.

Conflitos conhecidos a corrigir (não replicar o padrão antigo):

| Errado (PT) | Correto (EN) |
|---|---|
| `AlunoMovimentacaoService` | `StudentMovementService` |
| `JornadasRelatorioService` | `JourneyReportService` |
| `RedacaoLivreEnvio` | `EssayFreeSubmission` |
| `NotificacaoApi` | `ApiNotification` |

## Segurança (resumo)

Ownership validado em todo recurso de aluno · CSRF em POST não-API · `finfo_file()` em upload · `TENANT_SLUG` no path físico · HMAC em webhook · segredos só via env.
