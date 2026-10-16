# QA EducaTudo

Testes de ponta a ponta com Playwright, na pasta `qa/`, separados da aplicação PHP. A suíte cobre o caminho acadêmico real do admin: aluno, matrícula, chegada no meio do ano, ano letivo, grade, calendário, regras de aprovação, tipos de nota, quadro, modelo de boletim, configurar notas, lançamento, fechamento, conselho, diário, ocorrência e frequência.

Ela também mede cliques, etapas, tempo de carregamento, obstáculos de tela, teclado, quatro tamanhos de viewport e acessibilidade com axe-core.

Nenhuma chamada de IA é executada. Rotas de imagem, transcrição e assistente são abortadas. No modo padrão, `POST`, `PUT` e `DELETE` também são abortados, então a suíte não grava aluno, nota, boletim nem envia e-mail de recuperação de senha.

## Ambiente

A aplicação local sobe com `docker compose up -d`. A escola de desenvolvimento usada aqui é `http://colag.localhost`. A suíte recusa host `*.educatudo.com`.

```bash
cp qa/.env.example qa/.env
```

Preencha `QA_ADMIN_EMAIL` e `QA_ADMIN_SENHA` com uma conta de admin da escola local ou de homologação. Os scripts em `tmp/smoke_*.php` já usam a conta local da Colag. Não use senha de produção e não commite `qa/.env`.

Fora de `localhost` e `*.localhost`, defina também `QA_CONFIRMAR_AMBIENTE=homologacao`.

## Instalar

```bash
cd qa
npm install
npx playwright install chromium
```

## Executar

Na pasta `qa/`:

```bash
npm test
```

Com o navegador visível:

```bash
npm run test:headed
```

Um arquivo só:

```bash
npx playwright test testes/secretaria.spec.ts
npx playwright test testes/secretaria.spec.ts --headed
```

## Relatórios

O HTML do Playwright, com screenshot, vídeo e trace das falhas:

```bash
npm run report
```

O relatório único de funcional, UX, acessibilidade e priorização:

`qa/relatorios/educatudo-qa.html`

Abra esse arquivo no navegador. Ele é gerado ao final de cada execução.

## O que a suíte cobre

- Login válido, login inválido, campo obrigatório, logout e sessão apagada.
- Recuperação de senha só até o formulário. O envio é bloqueado.
- Acesso administrativo sem sessão volta ao login.
- Cada rota acadêmica listada em `src/catalogo.ts`: título, erro 5xx, tempo, campos, axe nas páginas principais e obstáculos heurísticos.
- Links do menu que apontam para essas rotas não podem responder 5xx.
- Cadastro de aluno: seis etapas, mensagens da etapa 1, cancelar sem gravação.
- Matrícula, ano letivo, grade (sem o botão de IA), calendário, regra, tipo de nota, quadro, modelo, assistente de notas, lançamento, boletins gerados e, se já existir modelo, gerar, simular e períodos.
- Chegada no meio do ano, se a vida escolar listar um aluno.
- Fechamento, conselho, diário, ocorrência e frequência.
- Tab no login, Escape no ano letivo, foco no cadastro de aluno.
- Desktop 1920×1080, notebook 1366×768, tablet 768×1024 e mobile 390×844.

## O que ainda não cobre

- Gravar um aluno fictício, matricular, lançar nota e gerar boletim de ponta a ponta. Isso exige `QA_PERMITIR_GRAVACAO=1` e uma limpeza combinada. O padrão não grava.
- Perfil professor, monitor, pais e aluno. Sem `QA_PROFESSOR_EMAIL`, a comparação de permissão fica ignorada.
- Exclusão, transferência, remanejamento, homologar fechamento, finalizar conselho e publicar boletim.
- E-mail real de recuperação de senha.
- Leitor de tela e revisão humana de contraste.

## Limites de UX

Em `src/catalogo.ts` e nos fluxos, cada caminho tem limite de cliques e de tempo de carregamento. Passar do limite gera alerta **Baixo** no relatório. Isso não reprova o teste e não é uma nota de facilidade.

O tempo da aplicação vem do Navigation Timing, até o `DOMContentLoaded`. O tempo de parede inclui a automação e fica numa coluna separada.

## Limitações da auditoria

- axe-core não substitui avaliação manual.
- Nome acessível, rolagem e elemento coberto são heurísticas. O relatório marca quando o achado é confirmado por uma regra ou por uma requisição observada.
- A contagem de cliques é a do caminho do script, não de todos os atalhos do menu.
- Interceptar gravação pode fazer a tela mostrar “erro de conexão”. Esse texto, nesse caso, é efeito do teste, não da aplicação. O relatório diz isso quando o fluxo de ano letivo dispara o POST.
- A suíte principal bloqueia service workers (`serviceWorkers: 'block'`). O arquivo `testes/service-worker.spec.ts` deixa o worker ativo de propósito: o `install` faz `cache.addAll`, que inclui `GET /admin`, e `loginAdmin()` desloga quem já está autenticado.

## Segurança

- Não apontar para produção.
- Não preencher senha, token ou e-mail real nos relatórios. O gerador oculta esses padrões.
- Não acionar importação de grade por imagem, transcrição de ocorrência nem o assistente de boletim.
- Não excluir registros.
