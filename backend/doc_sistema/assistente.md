# Assistente (coordenação)

Chat de IA no admin da escola para consultar desempenho **sem a LLM montar SQL**.

> Atualizado: **2026-07-21** · Catálogo de tools: [tool.md](tool.md)

---

## Onde acessar

| Superfície | URL |
|---|---|
| Chat | `/admin/assistente` |
| Wiki tools (admin) | `/admin/doc-sistema/tool` |
| Wiki (master) | `/master/documentacao/tool` |

Quem pode: `dev`, `diretor`, `coordenador` (+ TudiCoins módulo `provas_aluno_assistente_mensagem`).

---

## Arquitetura

```
Usuário (NL)
    → OpenAI (gpt-4o-mini)
    → emite <<<CONSULTA>>>{"tool":"…","args":{…}}<<<FIM>>>
    → ProvasAlunoAssistenteService::executarTool()
    → Services de leitura (SQL prepared)
    → JSON + painel na UI
```

A LLM **nunca** escreve SQL. Tools só leem dados.

---

## Domínios cobertos

1. **Aluno** — provas e jornadas (detalhe questão a questão)  
2. **Professor** — provas aplicadas, ranking de erros, saúde das turmas  
3. **Turma** — saúde e consolidado de provas (sem professor)  
4. **Bloco de prova** — dashboard de resultados  
5. **Boletim / faltas** — eventos gerados + frequência do diário  

Orquestrador: `ProvasAlunoAssistenteService`.  
Extras: `AssistenteConsultaAmpliadaService`.

---

## MCP (opcional)

Servers Node em `src/mcp/*` chamam endpoints POST JSON com CSRF + cookie de sessão admin:

- `consulta-provas-aluno`
- `consulta-jornadas-aluno`
- `consulta-provas-professor`
- `consulta-assistente` (turma/bloco/boletim/faltas)

Úteis no Cursor; o chat web **não** depende deles (usa Services diretos).

---

## Histórico de chats

Tabelas via migration `assistente_conversas` (master/tenant conforme migration).  
Service: `AssistenteHistoricoService`.

---

## Assistente de Boletim (outro produto)

Configuração de **regras** de boletim: `/admin/boletim-configuracao` + MCP `boletim-assistente`.  
Não é o mesmo chat de `/admin/assistente` — tools listadas em [tool.md](tool.md) §5.
