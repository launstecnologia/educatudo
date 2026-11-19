# Plano de rollback

Cada onda da Fase B entra em commit separado. Reverter é voltar esse commit. Não há migration neste plano, então não há rollback de banco. Não há mudança de Nginx.

Depois de qualquer volta que tenha mexido em arquivo com `Cache-Control: public`, a cópia em cache (navegador e, se a operação autorizar, Cloudflare) pode continuar servindo o arquivo antigo até expirar. A volta do código não apaga essa cópia. A limpeza de cache é passo operacional, feito à mão, só nas URLs da onda, e só com autorização. Esta fase não limpa cache.

## Onda 1 — entrega de arquivo

Reverter:

- `backend/app/Infra/Services/AcessoArquivoPrivado.php`
- `backend/app/Dominio/Escola/Controllers/ServeImagemExercicioController.php`
- `backend/app/Modulos/provas/Controllers/ServeExamQuestionImageController.php`
- a view que tiver passado a apontar para `/media/serve` em vez da URL direta de documento, se essa troca tiver entrado na mesma onda

Efeito da volta: `/uploads/jornadas/exercicios/` volta a responder 200 sem sessão, e pasta desconhecida volta a ser pública. É a exposição atual. Só reverter se a onda 1 impedir aluno logado de ver o enunciado e a correção imediata não for possível.

Conferência depois da volta: `GET` anônimo da imagem de exercício de fixture volta a 200 em local; logo em `/uploads/layout/` continua 200.

## Onda 2 — vínculo

Reverter só `AcessoArquivoPrivado::podeLer` (e as consultas novas no mesmo arquivo). A onda 1 permanece: anônimo continua sem arquivo, sessão da escola volta a ver qualquer chave privada genérica.

Usar esta volta se professor ou aluno da turma deixar de abrir documento ou exercício que a tela mostra.

## Onda 3 — recuperação

Reverter `AuthController` e as três views de recuperação se elas tiverem mudado, mais `primeiro-acesso.php` se o formulário de homônimo tiver mudado.

Efeito: some o limite e volta a frase diferente quando o e-mail existe. E-mails podem voltar a ser disparados sem teto. Não reverter só o controller e deixar a view à espera de um campo novo.

O contador em arquivo (`storage/logs/`, no mesmo padrão de `login_rate_limit.json`) pode ser apagado no ambiente de teste. Não é dado de aluno. Não apagar log de aplicação para "limpar" o rollback.

## Onda 4 — middleware

Reverter `AuthMiddleware.php` e a tabela de exceções. É a volta mais importante se algum perfil legítimo passar a ser redirecionado. As ondas 1 a 3 não dependem desta.

## Onda 5 — cabeçalhos

Remover os `header()` adicionados em `backend/bootstrap/app.php`. Report-Only não bloqueia página; a volta existe se algum proxy rejeitar o tamanho do cabeçalho ou se o relatório indicar falha de aplicação. Não há rollback de Nginx porque o Nginx não muda.

## Onda 6 — texto da OpenAPI e fotos

Texto: reverter `EspecificacaoOpenApiService.php`. As rotas continuam públicas nos dois estados.

Fotos: se `PUBLIC_TYPES` tiver perdido `avatars` ou `professores`, devolver os dois valores. A URL `/media/serve` não muda.

## Onda 7 — Redis e S3

Redis: reverter o bloco de `entrarComo`. O token volta a ser aceito quando o Redis não responde, até expirar.

S3: reverter o laço de `tenant_prefix` falso em `MediaServeController`. Faz falta se logo antigo passar a 404.

## Ordem se várias ondas subirem e precisarem voltar

Voltar da última para a primeira. A onda 1 é a que restaura a exposição anônima; deixá-la por último numa emergência em que as ondas seguintes tenham quebrado o login ou o middleware.

## O que não reverter pelo git

- Arquivo de fixture criado só no ambiente de teste: apagar do disco de teste.
- Canário já commitado no repositório (`auditoria-canario-e7c4a91f.png` e `auditoria-canario-m3b8d26e.png`): não fazem parte da correção. Não apagá-los neste rollback.
- Cache da borda: operação à parte, se tiver sido limpo na subida.
