# MCP — Provas / saúde do Professor (EducaTudo)

Consulta somente leitura: provas aplicadas pelo professor, acertos/erros dos alunos, ranking de erros e saúde das turmas.

Também integrado ao chat **Assistente** em `/admin/assistente`.

## Tools

| Tool | Uso |
|------|-----|
| `buscar_professores` | Nome / e-mail / código |
| `listar_turmas_professor` | Turmas do professor |
| `listar_provas_professor` | Provas + acertos/erros |
| `resumo_provas_professor` | Consolidado |
| `detalhar_prova_professor` | Acertos/erros por aluno |
| `ranking_erros_prova_professor` | Questões mais erradas |
| `saude_turmas_professor` | KPIs crítico/atenção |

## Setup

```bash
cd mcp/provas-professor
npm install
```

Endpoint: `POST /admin/consulta-provas-professor/mcp/ferramenta`
