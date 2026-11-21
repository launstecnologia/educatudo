# Etapa 6 — Modularização das redações com IA

**Data:** 2026-11-21  
**Pré-requisito verificado:** Etapa 5 presente no repositório (hub/salvarRascunho no módulo; SC ~6836 → base desta etapa).  
**Deploy / migrations / schema / CSRF global / preços / prompts / modelos / notas-boletim / async novo:** não alterados.

Baseline SC Etapa 5: **6836** · Após Etapa 6: **5852** (−984).

---

## 1. Funcionalidades migradas

| URL | Método | Destino |
|---|---|---|
| `/redacoes/criar` | POST | `AlunoRedacaoLegadoController@criar` → `RedacaoAlunoIaService::criarRedacao` |
| `/redacoes/gerar-tema` | POST | `@gerarTema` → `gerarTema` |
| `/redacoes/transcrever-imagem` | POST | `@transcreverImagem` → `transcreverImagem` |
| `/redacoes/corrigir` | POST | `@corrigir` → `corrigir` |
| `/redacoes/salvar-rascunho` | POST | já no módulo (Etapa 5) — sem mudança de regra |

URLs, CSRF localizado, prompts (`config_layout`), parâmetros OpenAI e chaves TudiCoins preservados.

---

## 2. Arquivos criados e alterados

### Criados
- `backend/app/Modulos/redacoes/Services/RedacaoAlunoIaService.php`
- `backend/tests/Modulos/RedacaoAlunoIaCaracterizacaoTest.php`
- Este relatório

### Alterados
- `AlunoRedacaoLegadoController.php` — criar / gerarTema / transcreverImagem / corrigir
- `Modulos/redacoes/routes.php`
- `StudentController.php` — métodos IA legado removidos
- `RedacaoAlunoLegadoCaracterizacaoTest.php` — asserts de rotas
- `.github/workflows/pr-validacao.yml` — filter `RedacaoAlunoIa`
- `estrutura.md`, `PLANO_EXTRACAO_REDACOES_IA.md`, `PLANO_EXTRACAO_TUDINHA.md`

---

## 3. Redução do StudentController

| Momento | Linhas |
|---|---|
| Baseline histórico | ~8183 |
| Pós Etapa 5 | 6836 |
| **Pós Etapa 6** | **5852** |

Delta Etapa 6: **−984**. Removidos: `createEssay`, `gerarTemaIA`, `transcreverImagem`, `corrigirRedacao`, `processEssayCorrection`, redirects HTML auxiliares. Mantidos no SC: limites diários genéricos (`podeExecutarAcao` etc.) para Tudinha/chat.

---

## 4. Testes executados (resultados reais)

| Suite | Resultado |
|---|---|
| PHPUnit módulos (filter Etapa 2–6) | **87 OK**, 263 asserts |
| PHPUnit Seguranca | **40 OK**, 83 asserts |
| **Total gate** | **127 testes**, **346 assertions** |
| Baseline Etapa 5 | 112 testes · 291 asserts |
| PHP lint (SC, service, controller) | OK |
| Smoke autenticado / HTTP com IA real | **Não executado** |

Cenários cobertos nos testes de caracterização (mocks): sucesso tema/correção/transcrição; falha IA; timeout; JSON inválido (tema = fallback sem estorno; correção = erro); saldo insuficiente; limite diário; requisição repetida (sem idempotência); ownership outro aluno; ausência de `escola_id`.

---

## 5. Comportamento de créditos preservado

| Fluxo | TudiCoins | Ordem | Estorno | Limite diário |
|---|---|---|---|---|
| `gerarTema` | `redacao_gerar_tema_aluno` | limite → **consumir** → OpenAI → registrar ação | Sim, só em exception após débito | `gerar_tema_redacao` + insert em `alunos_acoes_diarias` |
| `gerarTema` JSON inválido | debitado | fallback estruturado | **Não** (legado) | registra ação |
| `corrigir` | **Não consome** (apesar de existir chave `redacao_correcao_ia` no registry) | limite → persistir texto → OpenAI → UPDATE notas | N/A | checa limite mas **não registra** ação (legado) |
| `transcreverImagem` | **Não consome** | upload → OpenAI/Vision | N/A | não |
| `criar` | Não | insert → correção automática best-effort | N/A | não |

Nenhuma nova política de cobrança/reserva/idempotência foi introduzida.

---

## 6. Correções de segurança

1. **IDOR em correção:** fetch e UPDATE final de feedback passam a exigir `aluno_id` (antes o UPDATE de competências era só por `id`).
2. CSRF efetivo no controller nas quatro rotas de escrita migradas.
3. Isolamento tenant continua via PDO (sem `WHERE escola_id`).

---

## 7. Riscos residuais

1. `corrigir` não debita `redacao_correcao_ia` nem registra `alunos_acoes_diarias` — limite diário de correção efetivamente frágil; **não “corrigido”** para não mudar regra comercial sem aprovação.
2. `gerarTema` / `corrigir` / `transcrever` permanecem **síncronos** (podem >2s); async via `AIJobService` **não** introduzido (exige aprovação — 6.5).
3. `transcreverImagem`: validação MIME ainda pelo `type` do upload (legado); `finfo_file` não adicionado nesta fatia.
4. Referência de crédito com `uniqid` — retry do cliente gera novo débito (caracterizado).
5. Helpers de limite ainda duplicados no SC para chat (Etapa 7 pode unificar).
6. Homologação autenticada pendente.

---

## 8. Pendências de homologação

- [ ] Aluno: gerar tema (sucesso, saldo insuficiente, limite).
- [ ] Aluno: corrigir redação própria; tentar ID de outro aluno.
- [ ] Aluno: transcrever imagem JPG/PNG.
- [ ] Confirmar que prompts customizados em `config_layout` continuam aplicados.
- [ ] Não validar com chave OpenAI de produção nos testes automatizados.

---

## 9. Rollback

1. Reapontar as 4 rotas em `Modulos/redacoes/routes.php` para `User/StudentController@…` **somente** após restaurar os métodos do histórico git.
2. Manter UPDATE com `aluno_id` mesmo em rollback parcial (segurança).
3. Sem migration.

---

## 10. Preparação Etapa 7 — Tudinha e Chat

Ver `PLANO_EXTRACAO_TUDINHA.md` atualizado:

1. Inventário completo de rotas `/chat*` no `student.php`.
2. Caracterizar créditos (`tudinha_mensagem`, voz, stream) + estorno em falha.
3. Extrair primeiro **somente leitura** (`getMessages`, info).
4. Uploads com MIME/`TENANT_SLUG`.
5. `sendMessage` não-stream; stream por último.
6. Unificar limites diários compartilhados (hoje ainda no SC) sem mudar regras.
7. Não alterar prompts/agentes/preços sem suite dedicada.

---

## 6.5 Performance / resiliência (análise, sem mudança)

| Ponto | Estado atual | Ação Etapa 6 |
|---|---|---|
| Timeout OpenAI | tratado em transcrição/correção via mensagem | preservado |
| Chamadas bloqueantes | síncronas no request | **sem fila** (aprovação necessária) |
| Upload grande | teto 10MB | preservado |
| Logs | Logger::openaiError + error_log | preservado |
| Memória (base64 imagem) | risco residual | documentado |
