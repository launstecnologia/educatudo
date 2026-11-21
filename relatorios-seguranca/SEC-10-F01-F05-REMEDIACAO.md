# SEC-10 · Remediação F01–F05

**Data:** 2026-11-21  
**Commit-alvo da auditoria:** `8464de73`  
**Regra:** correções no monólito; **sem deploy**, **sem migrations**, **sem reinício de produção**.

## Resumo

| ID | Gravidade | Ação |
|---|---|---|
| F01 | Média | Isolamento Master fail-closed por `MASTER_DOMAIN` |
| F02 | Alta | Remoção da credencial persistente `master_auth` (Opção A) |
| F03 | Média | Cobertura documentada; `CSRF_MODE=enforce` preparado (não ligado às cegas) |
| F04 | Baixa | Deadline de 600s no CSRF de login (sessão + cookie assinado) |
| F05 | Média | GETs do chat só leem; leitura via POST + CSRF |

## F01 — Isolamento Master

Arquivo: `backend/app/Core/bootstrap_multi_tenant.php`

- Produção: path `/master` só no host `MASTER_DOMAIN`. Ausência de `MASTER_DOMAIN` → acesso administrativo negado.
- Dev/local: se `MASTER_DOMAIN` estiver definido, também exige o host; senão mantém convenção `master.*` / path local.
- Host canônico = `HTTP_HOST` (sem porta). Não usa `X-Forwarded-Host`.

**Produção:** definir `MASTER_DOMAIN=master.…` (valor externo ao código).

## F02 — Credencial persistente Master

Arquivo: `backend/app/Painel/Master/Controllers/MasterAuthController.php`

- Removidos emissão, restauração e segredo fixo `master-default-secret-change-me`.
- Logout continua expirando o cookie legado `master_auth`.
- Autenticação Master = sessão PHP + 2FA (código), sem “lembrar login” assinado.

## F03 — CSRF enforce

- Middleware permanece com default `audit` (rollout seguro).
- Em produção + audit, alerta único por worker ao detectar falha CSRF.
- `.env.example` documenta `CSRF_MODE=enforce` e rollback.
- Exceções: `backend/config/csrf_excecoes.php` (lista vazia).

**Homologação:** validar formulários/AJAX → `CSRF_MODE=enforce` → se regressão, voltar para `audit`.

## F04 — CSRF de login

Arquivo: `backend/app/Painel/Auth/Controllers/AuthController.php`

- Emissão grava `login_csrf_token` + `login_csrf_expires_at` (600s) na sessão.
- Cookie assinado com HMAC (`SCHOOL_SECRET_KEY`) quando o segredo existe.
- Token expirado / ausente / inválido → rejeitado; login legítimo preservado.

## F05 — Chat GET idempotente

Arquivos: `ChatController`, rotas teacher/student, views do chat.

- GET abertura/polling: não cria conversa e não marca lidas.
- Criação de conversa: no POST de envio (já existente).
- POST `/professor/chat/marcar-lidas` e `/chat-professor/marcar-lidas` com CSRF + `PoliticaAcessoProfessorAluno`.
- Front chama marcar-lidas após carregar mensagens e após polling com novidades.

## Ativação / rollback

| Item | Ativar | Rollback |
|---|---|---|
| F01 | `MASTER_DOMAIN` no `.env` de produção | Remover só em emergência (reabre host escolar) |
| F02 | deploy do código | N/A (cookie legado só limpo) |
| F03 | `CSRF_MODE=enforce` após homolog | `CSRF_MODE=audit` |
| F04/F05 | deploy do código | revert do commit |

## Testes sugeridos

```bash
cd backend && ./vendor/bin/phpunit --do-not-cache-result --filter \
  'MasterAcessoCodigoTest|CsrfMiddlewareTest|Sec10ChatIdorComplementoTest|Sec10F01F05Test'
```
