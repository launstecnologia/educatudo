---
name: educatudo-admin-ui
description: >
  Padrao de OFFCANVAS para telas de cadastro simples do painel administrativo
  do Educatudo (educatudo_oficial). Use quando for criar/ajustar uma tela de
  CRUD simples (listagem + criar + editar de uma entidade unica, sem
  sub-recursos nem multiplas etapas) e precisar decidir ou implementar o
  padrao de offcanvas/drawer em vez de pagina propria. Tambem use ao
  mencionar offcanvas, drawer de cadastro, dropdown de acoes, endpoint
  .../dados (JSON para popular offcanvas de edicao), ou telas como Usuarios,
  Professores, Monitores, Perfis de Permissao, Unidades. NAO use para fluxos
  complexos (Provas, Jornadas, Redacoes, Blocos, AVA/Cursos) - essas seguem
  pagina propria, documentada em prompts/admin_ui_sistema.md.
---

# Offcanvas de Cadastro Simples — Educatudo Admin

**Leia primeiro `prompts/admin_ui_sistema.md`** (raiz do repo) — é a fonte
única de verdade para tudo que não é específico de offcanvas: cor whitelabel
(`btn-primary-custom`/`bg-primary`), tabela, badges, dropdown de ações
(`_partials/row_actions_dropdown.php`), paginação, cabeçalho de listagem
(`_partials/page_header_list.php`). Esta skill **não duplica** esse conteúdo —
ela documenta só a parte que é exceção: o offcanvas único de criar/editar
para cadastro simples (seção 1d daquele documento).

## Quando usar esta skill (offcanvas) vs página própria

Critério completo está em `prompts/admin_ui_sistema/estrutura-e-cadastro.md`
§1d. Resumo:

**Offcanvas** (esta skill) — entidade única e achatada, formulário cabe num
painel lateral, edição é ação rápida. Exemplos reais já implementados:
Usuários, Professores, Monitores, Perfis de Permissão, Instituição/Unidades
(paths na tabela de §1d).

**Página própria** (fora do escopo desta skill, ver
`prompts/admin_ui_sistema/formularios-e-filtros.md` §6) — qualquer tela com
múltiplas etapas, sub-recursos com listagem própria ou conteúdo rico: Provas,
Blocos de Prova, Jornadas, Redações/Propostas, Planos de Aula, AVA/Cursos.
**Não** aplique offcanvas nessas telas.

Se não tiver certeza de qual categoria a tela pedida se encaixa, pergunte
antes de implementar — a diferença de esforço e arquitetura é grande.

## Antes de gerar qualquer offcanvas

| Preciso de... | Ler |
|---|---|
| A cor/botão/badge/tabela/dropdown/paginação (tudo que não é offcanvas) | `prompts/admin_ui_sistema.md` (documento principal) |
| A estrutura do offcanvas de criar/editar (painel único, `data-mode`, endpoint `.../dados`) | `references/forms.md` |
| Qualquer tela (sempre, no final) | `references/responsive.md` — checklist de responsividade e compatibilidade entre navegadores |

## Regras específicas do offcanvas (não repetidas do doc principal)

1. **Um único `index.php` por módulo.** Sem `create.php`/`edit.php` separados — o formulário de criar e editar é o mesmo `<form data-mode="create|edit">`, dentro do próprio `index.php` da listagem.
2. **Modo editar popula via `fetch`**, nunca via atributos `data-*` no botão nem página server-rendered. O controller ganha um método novo (`GET .../{id}/dados`) que devolve JSON com os campos da entidade — mesmo formato de `UsuarioController::dados()` (`src/app/Controllers/User/UserController.php`).
3. **Criar e editar reaproveitam o mesmo form**: seções/campos exclusivos de um dos modos (ex.: senha no criar, status/info do sistema no editar) ficam com `id` próprio e são mostrados/escondidos via JS ao abrir o drawer, não duplicados em dois forms.
4. **Método HTTP de update**: siga o que o módulo já usa. Se o módulo já tinha rotas `PUT`/`DELETE` via `_method` override (a maioria dos CRUDs antigos do admin), mantenha — não migre para POST puro só por causa do offcanvas. Ver "Armadilha do `_method` vazio" abaixo.
5. **CSRF**: token no form (POST) sempre; o endpoint `GET .../dados` é somente leitura e não exige `_token`, mas deve repetir a mesma checagem de permissão/ownership que a página de edição antiga fazia.

## Armadilha do `_method` vazio (já causou bug real)

Se o módulo usa `_method` override (PUT/DELETE via POST), o form único
criar+editar tem um `<input type="hidden" name="_method">` que só deve ir
preenchido em modo editar. **Nunca deixe esse campo com `value=""` sem
`disabled`** — `FormData` inclui campos com valor vazio, e o router do
projeto (`src/app/Core/Router.php`) faz `isset($_POST['_method'])`, que é
`true` mesmo para string vazia. Isso derruba o roteamento (o método vira
`''`, nenhuma rota bate, 404) e a criação de novos registros simplesmente
para de funcionar. Solução:

```html
<input type="hidden" name="_method" id="x_method" value="" disabled>
```

```js
// ao abrir em modo criar
document.getElementById('x_method').value = '';
document.getElementById('x_method').disabled = true;

// ao abrir em modo editar
document.getElementById('x_method').value = 'PUT';
document.getElementById('x_method').disabled = false;
```

Campo `disabled` é excluído do `FormData` automaticamente — é a forma mais
simples de garantir que `_method` só vai quando deve ir.

## Fluxo de trabalho

1. Confirmar que a tela se encaixa no critério de cadastro simples (seção acima). Se não, parar e usar o padrão de página própria do documento principal.
2. Ler `prompts/admin_ui_sistema.md` para cor, tabela, dropdown, paginação, cabeçalho.
3. Ler `references/forms.md` para a estrutura do offcanvas em si.
4. No controller: criar o endpoint `.../{id}/dados` (JSON), remover as rotas/métodos de página de criar/editar antigos.
5. Testar especificamente o fluxo de **criar** (não só editar) — é onde a armadilha do `_method` vazio se manifesta.
