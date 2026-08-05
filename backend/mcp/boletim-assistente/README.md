# MCP — Assistente de Boletim (EducaTudo)

Servidor MCP (stdio) que expõe as mesmas tools do chat em **Configuração de Boletim**.

## O que faz

| Tool | Uso |
|------|-----|
| `listar_tipos_avaliacao` | Semanal, Bimestral, ENAC… (Evento de Prova) |
| `listar_turmas` / `listar_materias` | Escopo da regra |
| `listar_regras` / `obter_regra` | Criar ou editar existente |
| `resolver_blocos_por_tipo` | “prova semanal” → IDs de eventos no período |
| `propor_regra_nl` | Linguagem natural → rascunho (não salva sozinho) |
| `contexto_catalogo` | Snapshot para grounding |

O rascunho deve ser revisado no admin (`/admin/boletim-configuracao`) antes de **Salvar regra**.

## Setup

```bash
cd mcp/boletim-assistente   # dentro do repositório src/
npm install
```

1. Faça login no admin da escola no browser.
2. Copie o cookie de sessão (`PHPSESSID=...`).
3. Abra a configuração de boletim e copie um `_token` CSRF do formulário (ou do HTML).

### Cursor (`~/.cursor/mcp.json` ou settings MCP)

```json
{
  "mcpServers": {
    "educatudo-boletim": {
      "command": "node",
      "args": ["/ABS/PATH/src/mcp/boletim-assistente/src/index.js"],
      "env": {
        "EDUCATUDO_BASE_URL": "http://localhost:8000",
        "EDUCATUDO_COOKIE": "PHPSESSID=seu_cookie",
        "EDUCATUDO_CSRF": "seu_token_csrf"
      }
    }
  }
}
```

## Pré-requisitos na escola

1. TudiCoins ligado (`creditos_habilitado`) na escola.
2. Item `boletim_assistente_mensagem` na tabela de custos (pode ficar com **Cobra** desmarcado = uso gratuito).
3. Usuário admin/coordenação com permissão de configuração de boletim.
4. `OPENAI_API_KEY` configurada no tenant/app.

## Chat in-app

Na tela **Configuração de Boletim**, botão flutuante **Assistente IA** — mesmo backend, sem precisar de MCP.
