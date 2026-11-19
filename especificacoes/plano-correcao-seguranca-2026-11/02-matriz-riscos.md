# Matriz de riscos

Base: `01-diagnostico.md`. Probabilidade alta significa que o código já toma a decisão insegura sem condição extra de ambiente. Probabilidade média significa que falta um arquivo no disco, um bucket compartilhado ou uma sessão de teste.

Impacto considera dado de aluno, enunciado de prova, senha e disponibilidade do e-mail da escola. Não considera um atacante já dentro da rede da escola como premissa.

| ID | Impacto | Probabilidade | Prioridade | Evidência | Confiança |
|---|---|---|---|---|---|
| SEC-001 | Alto. Enunciado de exercício com o nome do arquivo. | Alta. A rota não consulta sessão. A auditoria já recebeu o PNG. | Alta | HTTP 200 informado; controller linhas 19–51; arquivo local de 67 bytes | Comprovado |
| SEC-005 | Alto. Documento, vídeo, apostila, imagem de questão e anexo de notificação da mesma escola para qualquer sessão. | Alta no código. Média na exploração: é preciso ter a chave e uma conta da escola. | Alta | `podeLer` linhas 24–26 | Comprovado em código. Falta o teste com duas contas fictícias |
| SEC-006 | Alto se a pasta tiver PDF ou vídeo de aluno. | Alta no código. Média no efeito: o arquivo precisa existir em `public/uploads/` daquele host. | Alta | `pastaDireta` linha 82; bootstrap linhas 761–779 | Comprovado em código. Pedido dinâmico a pasta desconhecida não foi feito |
| SEC-002 | Médio. Disparo de e-mail, descoberta de nome no primeiro acesso, tentativa da pergunta de segurança sem trava. | Alta. Os métodos não chamam limite. | Média | Métodos citados no diagnóstico. POST não executado | Comprovado em código |
| COMP-01 | Médio. Token de redefinição no log se o envio falhar. | Média. Só no caminho de exceção, e só quem lê o log. | Média | Linhas 1504–1512 e 1937–1948 | Comprovado em código |
| SEC-008 | Alto se um controller do grupo `Auth` servir dado sem repetir o perfil. | Média. Os painéis principais rechecam o perfil. A falha aparece no controller que esquecer. | Alta potencial | `AuthMiddleware::handle` linhas 30–61 | Lacuna comprovada. Exploração de um método nomeado não comprovada |
| SEC-007 | Médio. Foto de pessoa para quem souber a chave. | Média. O tipo é público; a chave não é adivinhável por este diagnóstico. | Média potencial | `PUBLIC_TYPES` linhas 41–47 | Comportamento comprovado. Dano depende da política e da chave |
| SEC-003 | Baixo a médio. Clique em quadro e recursos do navegador. `X-Frame-Options` já foi colocado no proxy em 18/11. | Alta para a ausência de CSP. | Média/baixa | Busca no PHP e no Nginx do repositório | Ausência comprovada |
| COMP-02 | Alto se o bucket for único e ainda houver objeto sem pasta da escola. | Baixa até o bucket ser inspecionado. | Suspeita | Linhas 145–158 | Não comprovado como exposição |
| COMP-03 | Médio. Reuso de um token de suporte ainda dentro do prazo, só com Redis fora. | Baixa. Exige token válido e Redis indisponível. | Baixa | Linhas 1221–1238; `RedisCache::get` devolve null | Comprovado em código |
| SEC-004 | Baixo. Mapa da API e orientação de usar credencial real. As rotas em si continuam com JWT. | Alta a exposição. Baixo o abuso direto do JSON. | Revisão | 26 caminhos no serviço | Exposição intencional |
| COMP-04 | Baixo. Nome da escola e se a facial está configurada. | Alta. A rota é pública. | Informativo | `ApiStatusController` linhas 26–44 | Comprovado. Não é vulnerabilidade de dado de aluno |
| COMP-05 | Baixo enquanto a pasta só tiver imagem gerada. | Desconhecida. A pasta de produção não foi listada. | Informativo | Alias Nginx linhas 63–66 | Hipótese |

## Ordem pelo dano já decidido pelo código

1. SEC-001 — já houve resposta 200 sem sessão.
2. SEC-006 — a mesma máquina entrega qualquer outra pasta de `public/uploads/` que não esteja na lista.
3. SEC-005 — a porta autenticada não separa o dono.
4. SEC-002 e COMP-01 — e-mail e token no log, sem pedido destrutivo de senha nesta fase.
5. SEC-008 — corrigir com teste, porque o middleware único pode derrubar rota legítima.
6. SEC-007 — decisão de produto antes do código.
7. SEC-003 — Report-Only, depois de a entrega de arquivo estar fechada.
8. SEC-004 — revisão de texto.
9. COMP-02 e COMP-03 — quando o ambiente (bucket e Redis) puder ser confirmado em homologação.
10. COMP-04 e COMP-05 — registrar, sem mudança obrigatória.

## O que não entra como vulnerabilidade

- Ausência de `escola_id` nas queries de tenant. O isolamento previsto é a conexão.
- `GET /api/v1/status` com slug e nome da escola.
- Logo em `/media/serve?type=layout` com cache público.
- OpenAPI acessível, até a revisão de conteúdo.
- `TeacherController::viewStudent` retornar antes da consulta (já registrado na auditoria de autorização; não reaberto aqui).
- XSS em `/primeiro-acesso`: os campos voltam escapados. O ZAP não enviou script.
