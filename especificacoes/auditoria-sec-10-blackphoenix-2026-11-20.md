# EducaTudo — Auditoria SEC-10 (BlackPhoenix) · 20/11/2026

Documento READ-ONLY + gray-box **local** (Docker). Sem HTTP em produção. Não substitui o relatório BlackPhoenix.

**Complementa:** `status-pentest-blackphoenix-2026-11-20.pdf`, `consolidado-seguranca-2026-11-19.md`, canvas `sec-10-auditoria-blackphoenix`.

## 1. Escopo e método

| Item | Detalhe |
|---|---|
| Ambiente | `colag.localhost` (escola 1) · `escola2.localhost` (escola 2) · Docker local |
| Contas | Aluno `aluno.teste` (id 618) · Aluno `e2e.a01` (id 619) · Prof `mat.e2e@colag.local` (id 56) · Pai CPF `108.600.471-05` (id 3) · Admin `admin@colag.local` · Aluno escola2 `teste2` (id 634) |
| O que é prova | Código (`arquivo:linha`) e/ou HTTP/SQL local com resultado |
| O que não é prova | 401 anônimo sozinho (não fecha IDOR autenticado) |

## 2. Tabela da lacuna SEC-10 — status após esta passagem

> Princípio: **receber HTTP 401 sem autenticação não comprova** que um usuário autenticado esteja impedido de acessar dados de terceiros.

| Verificação | Situação (20/11) | Tipo de prova |
|---|---|---|
| Aluno A acessando dados do aluno B | **Parcialmente testado — bloqueado onde exercitado** | HTTP mídia + SQL ownership conversa |
| Professor acessando aluno/turma sem vínculo | **Testado — falha (IDOR)** | HTTP + INSERT em `chat_professores_alunos` |
| Responsável acessando aluno sem vínculo | **Código comprovado; HTTP local inconclusivo** | SQL vínculo + `getFilhoById`; painel pais 500 por schema local |
| Administrador acessando outra escola | **Testado — isolado** | Replay cookie Colag → Escola2 |
| API de notificações com id de outro usuário | **Código comprovado; HTTP JWT local quebrado** | `MobileNotificationController`; login mobile falha por coluna ausente |
| Arquivo privado por outro usuário autenticado | **Testado — 403** | `GET /media/serve?type=chat&key=aluno/619/...` com sessão do 618 |
| Retenção e criptografia dos alertas sensíveis | **Parcialmente demonstrado** | Schema + `StudentStatusService`; sem job de purge; texto em claro |

## 3. Evidências dos testes (local)

### 3.1 Isolamento multi-tenant (aluno e admin)

**Ação:** login em `colag.localhost`, reutilizar `EDUCATUDO_SESSION` em `escola2.localhost`.

| Ator | Rota no host escola2 | Resultado |
|---|---|---|
| Aluno 618 | `GET /dashboard` | Sessão invalidada (`Set-Cookie: EDUCATUDO_SESSION=deleted`) |
| Admin Colag | `GET /admin/dashboard` | Sessão invalidada (mesmo padrão) |

**Código:** `AuthManager::sessaoConfereComEscolaAtual()` — `backend/app/Core/AuthManager.php` ~489–520 (`tenant_id` da sessão ≠ `TENANT_ID` → logout).

**Avaliação:** proteção **comprovada** para replay de sessão entre escolas.

### 3.2 Aluno A × dados do aluno B

**Setup SQL:** conversas Tudinha `id=671` (aluno 618) e `id=672` (aluno 619).

```text
A_owns_B (conv 672 + aluno_id=618) → 0
B_owns_B (conv 672 + aluno_id=619) → 1
```

**Código de ownership no envio/leitura:** `StudentController` filtra `tudinha_conversas` com `id = :conversa_id AND aluno_id = :aluno_id` (ex.: ~2714–2715).

**HTTP mídia (formato de key reconhecido por `donoAluno`):**

| Pedido | HTTP |
|---|---|
| Sessão 618 → `type=chat&key=aluno/619/sec10.txt` | **403** |
| Anônimo → mesma key de 618 | **401** |

**Código:** `AcessoArquivoPrivado::donoAluno` exige `aluno/{id}/` ou `chat_{id}_` — `AcessoArquivoPrivado.php` ~990–998; `MediaServeController.php` ~82–89.

**Avaliação:** IDOR de arquivo chat **bloqueado** no cenário testado. Leitura HTTP de `/chat/mensagens` ficou instável por sessão nesta rodada; ownership SQL/código cobre o recurso.

### 3.3 Professor sem vínculo × aluno

**Fato:** professor id 56 tem **0** linhas em `jornadas`.

**Ação:** `GET /professor/chat/619` com sessão do professor 56.

**Prova SQL (após o pedido):**

```text
chat_professores_alunos id=2 | aluno_id=619 | professor_id=56 | created_at=2026-11-20 16:04:13
```

**Código:** `ChatController::chatProfessor` carrega aluno só por `a.id = :aluno_id` e faz `INSERT` na conversa **sem** checar turma/jornada — `backend/app/Painel/Integrations/Controllers/ChatController.php` ~130–157.

**Avaliação:** **IDOR intra-tenant comprovado** (BOLA: qualquer aluno do tenant). Correção: exigir vínculo professor↔turma/aluno antes de criar/ler.

### 3.4 Responsável sem vínculo

**SQL (mesma regra de `getFilhoById`):**

```text
pai3_filho645 → 1   (vínculo existe)
pai3_filho646 → 0   (filho do responsável 4)
```

**Código:** `ParentController::getFilhoById` — `backend/app/Painel/User/Controllers/ParentController.php` ~63–90 (`alunos_responsaveis` / `responsavel_id`).

**HTTP local:** `GET /pais/filhos` e `/pais/filhos/{id}/notas` responderam **500** por schema desatualizado (`Unknown column 'a.nome_social'`). Não invalida o ownership no código; **não** substitui reteste HTTP após migration.

**Avaliação:** proteção **comprovada no código**; HTTP autenticado **não verificado** neste ambiente.

### 3.5 API notificações (`/visualizado` / `/clicado`)

Rotas pedidas **não existem**. Equivalente:

| Método | Rota | Auth |
|---|---|---|
| GET | `/api/notificacoes` | `MobileApiAuth` |
| POST | `/api/notificacoes/{id}/lida` | `MobileApiAuth` |

**Código:** `MobileNotificationController::read` só atualiza envio com `notificacao_id` + `user_id` do JWT + `role='pai'`; senão 404 genérico — `backend/app/API/Controllers/V1/MobileNotificationController.php` ~56–92. Tenant no JWT: `MobileApiAuthMiddleware.php` ~47–50.

**HTTP local:** `POST /api/v1/auth/login` falhou (`Unknown column 'force_password_change'`). Reteste JWT pendente após alinhar schema.

### 3.6 Retenção / criptografia alertas

**Política pública:** `/politica-retencao` — chats 5 anos; acadêmico 5 anos; análises Tudinha e alertas sensíveis até **20 anos** (`backend/app/Views/legal/retention.php` ~44–46).

**Código ao inativar/formar:** `StudentStatusService::applyLgpdRules` define `expires_at` (+5 anos mensagens / +20 anos alertas e análises) e `retention_reason` — `StudentStatusService.php` ~453–510.

**Schema `alertas_sensiveis` (Colag):** colunas `mensagem_resumo`, `mensagem_aluno` (texto), `expires_at`, `anonymized_at`, `retention_reason`. **Sem** colunas de cifra/envelope.

**Criptografia encontrada:** `MasterSecretVault` (AES) para senhas de banco de tenant — não para alertas/chats.

**Job de anonimização/purge pós-`expires_at`:** não localizado em `cron/` / scripts nesta passagem.

**Avaliação:** retenção **parcial**; criptografia de alertas **lacuna**; anonimização automática **não demonstrada**.

## 4. Login (controles)

| Controle | Status | Evidência |
|---|---|---|
| Honeypot `site_url` | Parcial | `AuthController.php` ~828–837 |
| `login_csrf` | Comprovada | `AuthController.php` ~32, 839–850 |
| Rate limit IP | Comprovada | `AuthController.php` ~852–863, 975+ |
| Rate limit por identificador | Comprovada | `AuthManager.php` ~25–40, 797+ |
| Turnstile/captcha | Lacuna | Sem implementação no backend |

## 5. Inventário resumido de rotas (middleware)

Grupo `Auth`: `backend/config/routes.php` ~18–29.

| Área | Onde |
|---|---|
| Aluno boletim/notas/carteira/chat | `config/routes/student.php` |
| Professor carteira/chat | `config/routes/teacher.php` |
| Drive aluno/professor | `app/Modulos/drive/routes.php` |
| Admin boletim | `app/Modulos/boletins/routes.php` + `admin.php` |
| Pais web | `config/routes/parents.php` |
| `/media/serve` | `config/routes/public.php` ~63 (auth no controller) |
| API pais JWT v1 | `config/routes/api_v1.php` + `MobileApiAuth` |

## 6. Achados priorizados

| Crit. | Achado | Evidência | Correção |
|---|---|---|---|
| Alto | IDOR chat professor↔qualquer aluno | `ChatController.php` ~130–157 + row id=2 | Exigir vínculo turma/jornada |
| Médio | Sem captcha no login | Ausência Turnstile | Adicionar Turnstile |
| Médio | Alertas 20 anos em claro + sem purge | Schema + `StudentStatusService` | Job anonimização + envelope se mantido |
| Baixo | Warning `session_destroy` no logout cross-tenant | Observado no HTTP escola2 | Endurecer logout |
| Info | Schema local incompleto (`nome_social`, `force_password_change`) | 500 pais / JWT | Rodar migrations tenant antes do gray-box formal |

## 7. Matriz gray-box restante (não executada por completo)

Contas fictícias sugeridas: A1/A2, P1, R1/R2, Adm1 × escolas 1 e 2.

| ID | Caso | Estado |
|---|---|---|
| TC-01 | A1 boletim próprio | Parcial (dashboard ok em rodada anterior) |
| TC-02 | A1 compra/carteira de A2 | Não executado |
| TC-03 | A1 conversa HTTP de A2 | SQL ok; HTTP sessão instável nesta rodada |
| TC-04 | A1 drive de A2 | Sem dados drive no Colag |
| TC-05 | Sessão A1 em escola2 | **OK — isolado** |
| TC-06 | P1 chat aluno sem vínculo | **FALHA — IDOR** |
| TC-07 | R1 filho de R2 (HTTP) | Pendente (500 schema) |
| TC-08 | JWT notif id de outro | Pendente (login mobile quebrado) |
| TC-09 | Adm1 em escola2 | **OK — isolado** |
| TC-10 | Media A1 lê key de A2 | **OK — 403** |

## 8. Parecer

1. **SEC-10 não fecha** só com scan anônimo: o ponto crítico autenticado foi exercitado em parte.
2. **Furo confirmado:** professor inicia chat com aluno sem vínculo de turma.
3. **Isolamento entre escolas por sessão** e **mídia chat cross-aluno (path `aluno/{id}/`)** passaram no local.
4. **Retenção 20 anos** está na política e no `expires_at`, mas **sem prova de anonimização automática nem cifra** do conteúdo sensível.
5. Antes do gray-box formal em homolog: alinhar migrations (`nome_social`, `force_password_change`) e repetir pais + JWT.

**Veredito:** REPROVADO para encerrar SEC-10 até corrigir IDOR do chat professor e fechar retenção/cripto (ou aceitar risco jurídico documentado) + reteste HTTP dos itens ainda pendentes.
