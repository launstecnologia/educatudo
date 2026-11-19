# EducaTudo — relatório do ZAP de 18/11/2026

**Alvo:** `https://educa.educatudo.com`  
**Ferramenta:** OWASP ZAP 2.17.0  
**Relatório original:** gerado em 18/11/2026 às 20:47 (horário de Brasília)  
**Escopo do scan:** 15 endpoints públicos, sem autenticação  
**Onde a correção foi feita:** Nginx do proxy em `/opt/proxy/nginx/conf.d/educatudo.conf` e limpeza de cache na Cloudflare  
**Código PHP:** não foi alterado

O scan apontou 2 alertas médios, 7 baixos e 6 informativos. A borda que o navegador e o ZAP enxergam é a Cloudflare, na frente do proxy. O proxy encaminha para o Nginx da aplicação em HTTP. O PHP só entra nas páginas e em arquivos que o bootstrap entrega, como o `robots.txt`. CSS estático não passa pelo PHP.

## O que foi ajustado

As três linhas abaixo foram colocadas no `server` que escuta a porta 443. O `server_name` desse bloco é `educatudo.com`, `www.educatudo.com` e `*.educatudo.com`, então vale para `educa.educatudo.com`.

```nginx
add_header X-Content-Type-Options nosniff always;
add_header X-Frame-Options SAMEORIGIN always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

Depois do reload do container `nginx-proxy`, a Cloudflare ainda servia cópia antiga de dois arquivos. O cache foi limpo por URL:

- `https://educa.educatudo.com/robots.txt`
- `https://educa.educatudo.com/static/css/tailwind.css?v=1791844919`

Conferência feita em seguida, na resposta HTTP real:

| URL | `nosniff` | `X-Frame-Options` | HSTS | Cache da Cloudflare |
| --- | --- | --- | --- | --- |
| `/` | sim | `SAMEORIGIN` | sim | dinâmico, sem cópia antiga |
| `/robots.txt` | sim | `SAMEORIGIN` | sim | limpo (`BYPASS`) |
| `tailwind.css` | sim | `SAMEORIGIN` | sim | buscou de novo no servidor (`MISS`) |

O HSTS das páginas HTML já existia no PHP (`backend/bootstrap/app.php`). O proxy passou a enviar o mesmo header para o arquivo que não entra no PHP. Por isso a home pode mostrar o HSTS duas vezes. Os dois valores são iguais. Não é falha.

### Alertas que devem sumir no próximo ZAP

| Alerta no ZAP | Risco | O que o scan viu | O que mudou |
| --- | --- | --- | --- |
| Missing Anti-clickjacking Header | Médio | 5 páginas sem `X-Frame-Options` e sem `frame-ancestors` | O proxy envia `X-Frame-Options: SAMEORIGIN` |
| X-Content-Type-Options Header Missing | Baixo | `robots.txt` e `tailwind.css` | O proxy envia `nosniff`. A cópia em cache foi apagada |
| Strict-Transport-Security Header Not Set | Baixo | só `tailwind.css` | O proxy envia HSTS também nesse arquivo |
| Retrieved from Cache | Informativo | `Age` de cerca de 6 horas no `robots.txt` e de 23 dias no CSS | Essas URLs foram buscadas de novo na origem |

O header `X-Frame-Options` também está nos arquivos `docker/nginx/*.conf` do repositório. Na resposta pública ele não saía, porque o proxy da frente não o repetia e o CSS é entregue por um `location` do Nginx da aplicação que declara outro `add_header`. Nesse caso o Nginx não herda os headers do `server`. A correção efetiva foi no proxy.

## O que pode continuar

Estes itens podem permanecer no próximo relatório. Não são correção pendente desta rodada.

### Content-Security-Policy ausente — médio

O ZAP listou `/`, `/politica-privacidade`, `/primeiro-acesso` e `/sitemap.xml`. Não existe `Content-Security-Policy` no PHP, no proxy nem no Nginx do repositório.

Uma política restrita (`default-src 'self'`) quebra o que a plataforma já carrega de fora e o script escrito na própria página:

- Font Awesome em `cdnjs.cloudflare.com`
- HTMX em `unpkg.com`
- MathJax e fonte OpenDyslexic em `cdn.jsdelivr.net`
- Google Fonts em `fonts.googleapis.com` e `fonts.gstatic.com`
- script inline do login, como `togglePassword`

Por isso a CSP não foi ligada. Ela só deve entrar depois de uma lista explícita dessas origens e do script inline, com teste de login, painel do aluno, professor e admin.

### Headers de origem cruzada e Permissions-Policy — baixo

O ZAP pediu `Cross-Origin-Embedder-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy` e `Permissions-Policy`. Nenhum deles está configurado.

`Cross-Origin-Embedder-Policy` e `Cross-Origin-Resource-Policy` restritivos impedem a página de usar script, fonte e mídia de outro domínio. Isso atinge os mesmos CDNs acima e qualquer incorporação (videoaula, Jitsi, documento). `Permissions-Policy` mal fechada pode bloquear câmera e microfone usados em aula. Ficam de fora até essa lista ser revisada.

### Possível XSS em `/primeiro-acesso` — informativo

O ZAP marcou três campos reaparecendo no HTML depois do POST. Não enviou aspas nem script. Confiança baixa.

| Campo | Onde volta | Como a view grava |
| --- | --- | --- |
| `_token` | `input` oculto | `htmlspecialchars` |
| `aluno_nome` | `value` do campo de nome | `htmlspecialchars` |
| `turma_id` | `value` do `option` | convertido com `(int)` |

Arquivo: `backend/app/Views/auth/primeiro-acesso.php`. Do jeito que está, esses três pontos não executam HTML enviado pelo usuário. O alerta descreve um lugar para revisar, não um XSS confirmado.

### Timestamp Unix — baixo

A evidência `1791844919` é o `filemtime` de `public/static/css/tailwind.css`, colocado na URL como `?v=` por `backend/app/Helpers/EstilosPlataforma.php`. Serve para o navegador buscar o CSS novo quando o arquivo muda. Não é data de sessão, token nem dado de aluno.

### Avisos de cache — informativo

| Alerta | Evidência | Por que pode continuar |
| --- | --- | --- |
| Non-Storable Content | `private` e `no-store` em `/`, login e páginas públicas | Página com sessão e formulário não deve ser guardada em cache compartilhado |
| Re-examine Cache-control Directives | `no-cache, private` no HTML e `public, max-age=86400` no `robots.txt` | HTML de sessão fica privado. `robots.txt` é público e pode ser cacheado por um dia |
| Storable and Cacheable Content | logo em `/media/serve?type=layout`, `robots.txt` e o CSS | Os três são públicos. O CSS usa `max-age=2592000` (30 dias) de propósito |

A URL de mídia do scan é o logo da escola (`type=layout`, arquivo `escola_4_logo_1x1_1781706551.png`). Em `MediaServeController`, `layout` está na lista de tipos públicos e responde com `Cache-Control: public, max-age=86400`. Tipo privado (`redacoes`, `chat`, `tickets` e outros) continua exigindo sessão e a checagem de `AcessoArquivoPrivado`. Este scan não mostrou arquivo privado entregue sem login.

### Cookie de sessão identificado — informativo

O ZAP só registrou que a resposta cria sessão (`EDUCATUDO_SESSION` e `login_csrf`). Na resposta conferida depois do ajuste, `EDUCATUDO_SESSION` veio com `Secure`, `HttpOnly` e `SameSite=Lax`. O alerta não diz que o cookie está inseguro.

## Fora deste scan

O ZAP não retestou os achados altos do relatório de 17/11/2026 (API legada, autorização de mídia privada e arquivo estático). Eles não entram como resolvidos nem como descartados por este relatório.
