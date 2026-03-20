# Limite de upload (CSV / importações) — erro 413

Se o admin mostrar **HTTP 413 Request Entity Too Large** com **nginx** na mensagem, o proxy está bloqueando o tamanho do POST **antes** do PHP.

## Nginx

No `server { ... }` que atende o domínio da escola (ou num `location` que faz o proxy para PHP):

```nginx
client_max_body_size 50m;
```

Valores comuns: `20m`, `50m`, `100m` conforme o maior CSV esperado.

Depois:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

## PHP-FPM

Garanta que o PHP aceite o mesmo tamanho (no `php.ini` do `.user.ini` do docroot, ou pool FPM):

```ini
upload_max_filesize = 50M
post_max_size = 55M
```

`post_max_size` deve ser um pouco maior que `upload_max_filesize` (headers multipart).

## Referência no código

Os controllers limitam o tamanho por aplicação (ex.: importação de responsáveis até ~100MB), mas **nginx/php** precisam permitir pelo menos esse valor, senão a resposta será 413 ou upload falho.
