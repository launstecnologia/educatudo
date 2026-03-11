# EducaTudo – WebSocket presença online

Servidor WebSocket para presença online por escola (tenant). Os clientes conectam, enviam `login` com escola (slug), usuario_id, nome e tipo (aluno/professor). O servidor faz broadcast do resumo (alunos e professores online por escola) para todos os clientes.

## Variáveis de ambiente

- **PORT** – Porta do servidor (padrão: 3001).

## Uso

```bash
npm install
npm start
```

Em produção, exponha o WS atrás de proxy (nginx/caddy) para `wss://ws.educatudo.com`.

## Protocolo

- Cliente envia no `onopen`: `{ type: "login", escola, usuario_id, nome, tipo }` (tipo: "aluno" ou "professor").
- Servidor envia a todos: `{ type: "master_update", data: { [escola]: { alunos: number, professores: number } } }` após cada login/close.
