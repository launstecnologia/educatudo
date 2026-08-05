# MCP — Provas dos Alunos (EducaTudo)

Servidor MCP (stdio) para a LLM consultar **desempenho em provas** de um aluno: semanais, bimestrais e outros tipos de evento, com acertos/erros, nota, data e matéria.

## O que faz

| Tool | Uso |
|------|-----|
| `buscar_alunos` | Nome parcial / RA → lista de candidatos com `id` |
| `listar_materias` | Catálogo para filtrar |
| `listar_tipos_avaliacao` | Semanal, Bimestral, ENAC… |
| `listar_provas_aluno` | Histórico detalhado (filtros: matéria, tipo, período) |
| `detalhar_prova_aluno` | Uma prova específica |
| `resumo_provas_aluno` | Médias/contagens por tipo e por matéria |

### Exemplo de pergunta

> “Como o Lucas foi nas provas semanais de Matemática?”

Fluxo sugerido para o LLM:

1. `buscar_alunos` com `termo=Lucas` → pegar `aluno_id`
2. `listar_provas_aluno` com `aluno_id`, `materia_nome=Matemática`, `tipo_avaliacao_nome=semanal`

Cada item traz: título, matéria, tipo, evento (data/bimestre), realização (dia, nota, acertos, erros, %).

## Setup

```bash
cd mcp/provas-aluno   # dentro do repositório src/
npm install
```

1. Login no **admin** da escola no browser.
2. Copie o cookie de sessão (`PHPSESSID=...`).
3. Copie um `_token` CSRF de qualquer formulário do admin.

### Cursor (`~/.cursor/mcp.json` ou settings MCP)

```json
{
  "mcpServers": {
    "educatudo-provas-aluno": {
      "command": "node",
      "args": ["/ABS/PATH/src/mcp/provas-aluno/src/index.js"],
      "env": {
        "EDUCATUDO_BASE_URL": "http://localhost:8000",
        "EDUCATUDO_COOKIE": "PHPSESSID=seu_cookie",
        "EDUCATUDO_CSRF": "seu_token_csrf"
      }
    }
  }
}
```

## Quem pode usar

Sessão de **admin** ou **admin_escola** com perfil `dev`, `diretor` ou `coordenador`.

## Chat no admin

Além do MCP, há a página **Assistente de Provas** em `/admin/assistente-provas`
(menu Avaliações → Assistente de Provas), para coordenação/direção/dev.

Chave TudiCoins: `provas_aluno_assistente_mensagem` (pagador = escola).
Inclua no catálogo Master de custos; o checkbox **Cobra** pode ficar off = grátis.
