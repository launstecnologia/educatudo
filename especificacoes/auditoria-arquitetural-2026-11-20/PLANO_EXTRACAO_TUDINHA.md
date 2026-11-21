# Plano Tudinha / Chat — atualizado pós Etapa 7

**Data:** 2026-11-21  
**Status:** **Chat HTTP descontinuado** (não modularizado). Ver `ETAPA7_DESCONTINUACAO_CHAT_TUDINHA.md`.

---

## O que foi descontinuado

- Portal aluno: todas as rotas `/chat` e `/chat/*` (exceto `/chat-professor`).
- Código exclusivo no `StudentController` + view `chat.php`.
- Entradas de UI (sidebar, bottom nav, sliders `chat_tudinha*`).

## O que permanece (não é o chat)

- `TudinhaService` / agentes — **jornadas** (“explica exercício”).
- Flag de módulo `chat` — ainda usada por jornadas.
- Apps externos nomeados Tudinha.
- TudiCoins / chaves `tudinha_*` no registry (sem novos débitos via `/chat`).
- Tabelas e arquivos históricos.
- `/chat-professor`, comunicação, aulas-online, apostilas-ia chat.

## Próximos passos opcionais (Etapa 8+)

1. Decidir se a flag `chat` vira chave própria para “explica exercício”.
2. Remover dead code (`ChatSafetyMonitorService`) se confirmado sem uso.
3. Homologar 410 + jornadas + redações em ambiente autenticado.
