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

- Classes/métodos/arquivos em **português** (decisão de 2026-07-08 — detalhe e racional em `.claude/docs/nomenclatura.md`). Tabelas do banco ficam em PT; legado em inglês só é renomeado caso a caso, nunca em lote.
- Exceções que não se traduzem: siglas oficiais (`BNCC`, `ENEM`, `INEP`), nomes de marca/produto (`Tudinha`, `EducaLabs`, `EducaHits`, `EducaInclui`, `EducaShop`), sufixo arquitetural (`Controller`/`Service`/`Model`/`Middleware`).
- Coluna do banco = chave do array de dados no Model/Controller = atributo `name=` do input no formulário — sempre o mesmo nome.
- Controllers prefixados por perfil: `Admin*`, `Student*`, `Teacher*` (prefixo de perfil continua em inglês, é convenção estrutural, não vocabulário de negócio).

Conflitos conhecidos a corrigir ao tocar no módulo (não replicar o padrão em inglês em código novo — lista completa em `.claude/docs/nomenclatura.md`):

| Hoje (EN) | Alvo (PT) |
|---|---|
| ~~`AccommodationController`~~ | ~~`EducaIncluiController`~~ — feito em 2026-07-08 (piloto do processo, ver `specs/tasks.md`) |
| `FinanceController` | `FinanceiroController` |
| `EnrollmentAdminController` | `MatriculaOnlineAdminController` (após decidir o par `matricula`/`enrollment` — ver doc) |
| `CourseCatalogController` | `CatalogoCursoController` |
| `PatrimonyAdminController` | `PatrimonioAdminController` |

## Segurança (resumo)

Ownership validado em todo recurso de aluno · CSRF em POST não-API · `finfo_file()` em upload · `TENANT_SLUG` no path físico · HMAC em webhook · segredos só via env.
