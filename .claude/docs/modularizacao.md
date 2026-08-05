# Modularização — monólito modular

## Objetivo

Organizar features opcionais como **módulos físicos** em `backend/app/Modulos/<chave>/`, com manifest, rotas, Controllers, Models e Services co-localizados — sem microserviços.

## Estrutura de um módulo

```
app/Modulos/<chave>/
  manifest.php          # chave, rotas→feature keys, menus, labels
  routes.php            # rotas admin/professor/aluno do domínio
  Controllers/          # controllers do módulo
  Models/               # acesso a dados
  Services/             # lógica de negócio
  Views/
    student|teacher|admin/
```

## Como o Core descobre o módulo

| Peça | Papel |
|---|---|
| `ModuloRegistry` | Escaneia `app/Modulos/*/manifest.php` |
| `FeatureGate` | Mescla `rotas` do manifest no gate de URI |
| `config/routes.php` | `glob` em `app/Modulos/*/routes.php` (dentro do middleware Auth) |
| `Router` | Resolve handler `Modulos/<chave>/<Controller>@method` |
| `BaseController` | View `perfil/modulo/arquivo` também em `Modulos/<modulo>/Views/<perfil>/` |

## Piloto: Arquivos

Módulo em `app/Modulos/arquivos/`.

- Feature keys: `aluno_arquivos`, `professor_arquivos` (Master: `geral_arquivos`)
- Sub-feature: `aluno_recuperacao` (menu/rota Recuperação) — default **off** via `feature_defaults`; ligar só no COLAG no Master
- Rotas: `/aluno/arquivos*`, `/aluno/recuperacao`, `/professor/arquivos*`, `/admin/arquivos*`
- Controllers magros; SQL/upload em `ArquivosService` + Models
- Views em PT: `Views/aluno`, `Views/professor`, `Views/admin` (paths `aluno/arquivos/...`, `professor/arquivos/...`)
- Layouts globais continuam `Views/layouts/student.php` e `professor.php` (fora do módulo)
- Controllers em PT: `ArquivosAlunoController`, `ArquivosProfessorController`, `ArquivosAdminController`

## Piloto: Drive

Módulo em `app/Modulos/drive/`.

- Feature key: `drive` (Master bloco Aluno via `master_aluno`)
- Rotas: `/drive*` e `/professor/drive*`
- Models `DriveItem`/`DriveShare` + `DriveStorageService` no módulo
- Views PT: `Views/aluno`, `Views/professor`

## Como migrar o próximo módulo

1. Criar pasta `app/Modulos/<chave>/` com `manifest.php` + `routes.php`
2. Mover Controllers/Views; criar Models + Service
3. Apontar rotas para `Modulos/<chave>/<Controller>@method`
4. Remover rotas duplicadas de `config/routes/{perfil}.php`
5. Deixar shim em `Controllers/<Old>/` se houver referências legadas
6. Não duplicar lista no FeatureGate — use o manifest

## O que não fazer

- Microserviços / deploy por módulo
- `WHERE escola_id` em query de tenant
- Migrar todos os módulos de uma vez
