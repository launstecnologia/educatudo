# Perfis e URLs

> Atualizado: **2026-07-21**

---

## Tabela de perfis

| Perfil | URL base | Banco | Papel |
|---|---|---|---|
| Aluno | `/` | Tenant | Consome conteúdo, provas, redações, jornadas |
| Professor | `/professor` | Tenant | Cria jornadas/provas, corrige |
| Admin (escola) | `/admin` | Tenant | Gestão da escola |
| Monitor | `/monitor` | Tenant | Supervisão, leitura |
| Pais | `/pais` ou API JWT | Tenant | Acompanha filho (leitura) |
| Master | `/master` | **Master** | Escolas, migrations, financeiro SaaS |

Rotas: `src/config/routes/<perfil>.php`.

---

## Admin escola — perfis internos

Além do tipo `admin`, há `perfil_admin` (ex.: `dev`, `diretor`, `coordenador`, …).

Permissões de menu/módulo: `AdminPermissionMatrix`.

**Assistente** e **Doc do sistema** (wiki no admin): hoje liberados para `dev` / `diretor` / `coordenador`.

---

## API Pais

JWT em `/api/*` — ver `src/docs/API_PAIS_ROTAS_E_CAMPOS.md`.

CSRF **não** se aplica às rotas API com JWT; as demais POSTs web usam CSRF.

---

## Onde cada um “mora” no código

| Perfil | Controllers | Views |
|---|---|---|
| Admin | `Controllers/Admin/` | `Views/admin/` |
| Master | `Controllers/Master/` | `Views/master/` |
| Professor | `Controllers/Teacher/` (+ outros) | `Views/teacher/` / `professor/` |
| Aluno | vários | `Views/student/` |
| Pais | `Controllers/Api/` / parents | `Views/parents/` |
