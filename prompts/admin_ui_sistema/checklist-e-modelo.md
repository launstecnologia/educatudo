# Checklist, Modelo de Prompt e Exemplo

> Parte de `prompts/admin_ui_sistema.md`. Ler o índice lá antes se ainda não leu.

## 12. Checklist antes de entregar

- [ ] Título `h2` + subtítulo `text-sm text-gray-600`
- [ ] Card branco com `rounded-xl` (lista) ou `rounded-lg` (form)
- [ ] CTA principal `bg-primary text-primary` (cor do sistema); filtros/ações secundárias outline cinza
- [ ] Uma tela, um cadastro — cadastros irmãos em páginas separadas
- [ ] Tabela com thead `bg-gray-50`, uppercase nos headers
- [ ] Badges `rounded-full text-xs`
- [ ] Inputs com label `font-medium text-gray-700` e focus ring consistente
- [ ] Estado vazio com ícone FA grande `text-gray-300`
- [ ] Mobile: `overflow-x-auto` na tabela; grid 1 coluna
- [ ] PHP escapado; CSRF em POST
- [ ] Ícones Font Awesome (não emojis no UI, exceto títulos legacy)

---

## Modelo de prompt (copie e preencha)

```
Implemente/refatore a tela admin seguindo o Design System EducaTudo
(documento: prompts/admin_ui_sistema.md — referência Lista de Alunos).

## Contexto
- **TIPO DE TELA:** [listagem | formulário create | formulário edit | ficha detalhe | hub com abas]
- **ROTA:** [ex: /admin/turmas]
- **TÍTULO (h2):** [ex: Lista de Turmas]
- **SUBTÍTULO:** [ex: Gerencie as turmas da escola]
- **LAYOUT:** admin.php (sidebar + main)

## Listagem (se aplicável)
- **COLUNAS:** [Nome | Col2 | Status | Ações]
- **CTA PRIMÁRIO:** [ex: + Registrar nova Turma → rota create]
- **FILTROS (drawer):** [campo1, campo2, ...] ou "sem filtros"
- **AÇÃO POR LINHA:** [ex: Detalhes → rota show]
- **BADGE STATUS:** [mapear valores → cores]
- **VAZIO:** [mensagem + CTA]

## Formulário (se aplicável)
- **SEÇÕES:**
  - [Seção 1]: campos...
  - [Seção 2]: campos...
- **CAMPOS:** (por linha: name | tipo | label | obrigatório | placeholder | máscara)
- **BOTÕES RODAPÉ:** Cancelar (volta listagem) + Salvar (verde)
- **MENSAGENS:** erro/sucesso inline ou flash

## Backend
- Controller: [NomeController@metodo]
- Variáveis para view: [lista, filtros, pagination, csrf_token, ...]

## Restrições
- Tailwind utility classes only (sem CSS custom exceto ficha complexa)
- Font Awesome 6.5 para ícones
- Não inventar componentes fora do design system
- Manter consistência com src/app/Views/admin/students/index.php e create.php
```

---

## Exemplo preenchido (nova listagem)

```
Implemente a listagem admin de Monitores seguindo prompts/admin_ui_sistema.md.

- TIPO: listagem
- ROTA: /admin/monitors
- TÍTULO: Lista de Monitores
- SUBTÍTULO: Gerencie os monitores de sala vinculados às turmas
- COLUNAS: Monitor (avatar+ nome) | E-mail | Turmas | Status | Ações
- CTA: + Registrar Monitor → /admin/monitors/create
- FILTROS: nome, turma_id (select)
- AÇÃO LINHA: Detalhes (botão azul suave)
- STATUS: ativo=green, inativo=red
- Paginação igual à lista de alunos
```

> Nota: este exemplo é ilustrativo do **preenchimento do modelo de prompt**
> (seção "listagem página própria"). A tela real de Monitores
> (`/admin/monitors`) foi implementada depois seguindo a exceção de offcanvas
> de `estrutura-e-cadastro.md` §1d — sem filtro/paginação (não eram
> necessários) e com "Editar" via dropdown de ações abrindo o offcanvas, não
> um botão "Detalhes" separado. Use este bloco só como referência de como
> preencher o modelo, não como descrição do estado atual de `/admin/monitors`.
