# MCP — Jornadas dos Alunos (EducaTudo)

Consulta somente leitura do desempenho do aluno nas jornadas (progresso %, status, acertos).

Também integrado ao chat **Assistente** em `/admin/assistente`.

## Tools

| Tool | Uso |
|------|-----|
| `buscar_alunos` | Nome + turma opcional |
| `listar_materias_jornadas` | Catálogo |
| `listar_jornadas_aluno` | Lista com % e status |
| `detalhar_jornada_aluno` | Exercícios da jornada |
| `resumo_jornadas_aluno` | Consolidado |

## Setup

```bash
cd mcp/jornadas-aluno
npm install
```

```json
{
  "mcpServers": {
    "educatudo-jornadas-aluno": {
      "command": "node",
      "args": ["/ABS/PATH/src/mcp/jornadas-aluno/src/index.js"],
      "env": {
        "EDUCATUDO_BASE_URL": "http://localhost:8000",
        "EDUCATUDO_COOKIE": "PHPSESSID=...",
        "EDUCATUDO_CSRF": "..."
      }
    }
  }
}
```

Endpoint: `POST /admin/consulta-jornadas-aluno/mcp/ferramenta`
