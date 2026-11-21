# Etapa 7 — Descontinuação do Chat Tudinha

**Data:** 2026-11-21  
**Decisão de produto:** descontinuar o **chat** da Tudinha (não a personagem, nem TudiCoins, nem outras IAs).  
**Deploy / migrations / DROP de tabelas / apagar storage / CSRF global / preços:** não feitos.

Baseline SC Etapa 6: **5852** · Após Etapa 7: **4020** (−1832).

---

## 1. Inventário (classificação)

### Exclusivo do chat (desabilitado / removido)
| Item | Ação |
|---|---|
| Rotas `/chat`, `/chat/*` (aluno) | → `ChatTudinhaDescontinuadoController@indisponivel` (410) |
| Métodos SC: chat, createConversation, sendMessage(+stream), uploads chat, gerarImagem, flashcardConcluido, textoParaVoz, voz realtime, getMessages, verImagemChat, getConversationInfo, deleteConversation | Removidos do SC |
| Helpers `getStudentConversations`, `monitorarChatMensagem` | Removidos |
| View `Painel/Aluno/Views/chat.php` | Removida |
| Links sidebar / bottom nav / defaults Master·Admin | Removidos ou filtrados |
| Slider dashboard `chat_tudinha` / `chat_tudinha_2` | Sem destino `/chat` |

### Compartilhado (preservado)
| Item | Motivo |
|---|---|
| `TudinhaService` + agentes / pipeline | Usado por **jornadas** (`gerarExplicacaoSemHistorico`) |
| Flag módulo `chat` no FeatureGate / LayoutHelper | Jornadas ainda consultam `isModuleEnabled('chat')` para modal “explica exercício” |
| `ChatFormatter` | Teacher AI agents |
| `vozParaTexto` no SC + rota `/aluno/provas/voz-para-texto` | Provas |
| `/chat-professor` e módulo comunicação / aulas-online chat | Outros produtos |
| Apps externos “Tudinha” no menu | External apps, não `/chat` |
| Chaves `tudinha_*` no `CreditosModuleRegistry` | Ledger histórico / catálogo; endpoints não debitam mais |
| Tabelas `tudinha_conversas`, `tudinha_mensagens`, storage | Histórico preservado (sem DROP) |
| Limites `chat_interacoes` no SC | Código de limite genérico ainda presente; sem rota ativa de chat |

### Incerto / não tocado
| Item | Nota |
|---|---|
| `ChatSafetyMonitorService` | Sem consumidores após remoção; arquivo **mantido** |
| Agentes em `app/Agentes/Tudinha*` | Dependência de `TudinhaService`/jornadas — mantidos |
| `service-worker.js` lista `/chat` | Cache SW legado; rota já 410 |
| Flutter `familia/` | Sem rotas de chat Tudinha encontradas |

---

## 2. Rotas desabilitadas (aluno)

Todas → `User/ChatTudinhaDescontinuadoController@indisponivel` (auth aluno; JSON/HTML **410**):

`/chat`, `/chat/conversa`, `/chat/mensagem`, `/chat/mensagem-stream` (GET/POST), uploads, `/chat/gerar-imagem`, `/chat/flashcard-concluido`, TTS/STT/voz-realtime do chat, `/chat/mensagens`, `/chat/ver-imagem`, `/chat/conversa-info`, `/chat/conversa/excluir`.

**Preservadas:** `/chat-professor*`, `/aluno/provas/voz-para-texto`.

---

## 3. Métodos removidos do StudentController

Bloco do chat (~1900 linhas de implementação) + helpers de conversa/monitoramento. Mantido: `vozParaTexto`.

---

## 4. Arquivos

### Criados
- `app/Painel/User/Controllers/ChatTudinhaDescontinuadoController.php`
- `tests/Modulos/ChatTudinhaDescontinuacaoTest.php`
- Este relatório

### Removidos
- `app/Painel/Aluno/Views/chat.php`

### Alterados
- `config/routes/student.php`
- `StudentController.php`
- `student_sidebar.php`, `layouts/student.php`, `dashboard.php` (aluno)
- Defaults mobile Master/Admin
- `pr-validacao.yml`, `estrutura.md`, `PLANO_EXTRACAO_TUDINHA.md`

---

## 5. Linhas SC

| Momento | Linhas |
|---|---|
| Pós Etapa 6 | 5852 |
| **Pós Etapa 7** | **4020** |

Delta: **−1832**.

---

## 6. Testes executados

| Suite | Resultado |
|---|---|
| PHPUnit ChatTudinhaDescontinuacao | **10 OK**, 51 asserts |
| PHPUnit módulos (filter gate) | **97 OK**, 314 asserts |
| PHPUnit Seguranca | **40 OK**, 83 asserts |
| **Total gate** | **137 testes**, **397 assertions** |
| Baseline Etapa 6 | 127 · 346 |
| Smoke autenticado | **Não executado** |

Cobertura dos testes: rotas 410; sem StudentController@chat; chat-professor e provas STT ok; sidebar sem link; redações IA intactas; TudinhaService em jornadas; registry créditos; sem DROP de tabelas no código.

---

## 7. Segurança / histórico

- Novas conversas / streaming / OpenAI / TudiCoins via `/chat*` **não executam** (handler sem Creditos/OpenAI/SQL).
- Auth exigida no constructor do handler.
- Dados `tudinha_*` e arquivos em storage **não apagados**.
- Isolamento tenant inalterado (PDO; handler sem `escola_id`).

---

## 8. Riscos residuais

1. Configs antigas de mobile nav no banco ainda podem listar `/chat` até edição — **filtradas em runtime** no layout aluno.
2. Slides com URL absoluta `/chat` ficam sem link (imagem pode continuar visível).
3. Módulo flag `chat` permanece ligado para jornadas — não confundir com chat HTTP.
4. `ChatSafetyMonitorService` e agentes de chat sem consumidor HTTP — limpeza futura opcional.
5. Homologação autenticada pendente (410 real no browser).

---

## 9. Rollback

1. Restaurar métodos do SC e view `chat.php` do git.
2. Reapontar rotas `/chat*` para `User/StudentController@…`.
3. Restaurar links de sidebar/nav defaults.
4. Sem migration — dados históricos intactos.

---

## 10. Preparação Etapa 8 (sugestão)

Prioridades possíveis pós-descontinuação:

1. Extrair/unificar limites diários restantes no SC.
2. Avaliar se flag `chat` deve virar chave própria de “explica exercício” (decisão de produto).
3. Remover dead code (`ChatSafetyMonitorService` se confirmado sem uso).
4. Continuar redução do SC (carteira, notas, etc.) conforme plano de evolução.
5. Homologar 410 + jornadas “Tudinha explica” + redações IA em ambiente com conta de teste.
