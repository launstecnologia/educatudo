---
description: Investiga e corrige um bug relatado, com verificação
---

Corrija o problema: **$ARGUMENTS**

1. Reproduza/localize primeiro: procure o fluxo em `src/config/routes/*.php` → Controller → Service/Model. Cheque `src/storage/logs/` (logs JSON com TENANT_ID) se for erro de produção.
2. Diagnostique a causa raiz antes de editar — não trate sintoma.
3. Aplique a correção mínima que resolve a causa, seguindo as convenções (prepared statements, ownership, sem `WHERE escola_id`).
4. Verifique: `php -l` no arquivo (o hook já roda), e se houver teste Playwright relacionado na raiz, rode-o.
5. Invoque o subagent **code-reviewer** no diff antes de apresentar o resultado.
6. Resuma: causa raiz, o que mudou, como verificou.
