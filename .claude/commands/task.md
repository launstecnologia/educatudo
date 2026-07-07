---
description: Executa qualquer tarefa de criação (feature, tela, ajuste, relatório) seguindo o fluxo spec-driven do EducaTudo
---

Tarefa solicitada: **$ARGUMENTS**

Siga este fluxo do início ao fim. Não pule etapas.

## 1. Estruturar a tarefa

Reformule o pedido neste formato e mostre ao usuário antes de codar:

```
**Objetivo:** o que vai existir quando terminar (1 frase)
**Perfis afetados:** aluno / professor / admin / pais / monitor / master
**Onde vive:** módulo existente ou módulo novo · rotas em config/routes/<perfil>.php
**Dados:** tabelas existentes ou migration nova (tenant ou master?)
**Feature flag:** módulo opcional por escola? (FeatureGate)
**Créditos/IA:** consome IA? (se >2s → job assíncrono via AIJobService + debitar créditos)
**Fora de escopo:** o que NÃO entra nesta tarefa
**Critérios de aceite:** lista verificável de "pronto"
```

Se algum campo ficar ambíguo e mudar a implementação, pergunte antes de continuar.

## 2. Registrar

- Adicione a tarefa em `specs/tasks.md` (seção "Em andamento").
- Se for feature de produto (não ajuste técnico), preencha a seção "Feature em andamento" do `specs/PRD.md`.

## 3. Planejar

- Tarefa que toca 3+ arquivos: apresente o plano (arquivos e o que muda em cada um) e aguarde aprovação.
- Tarefa pequena (1-2 arquivos): siga direto.
- Módulo novo → use o fluxo do `/new-module`. Migration → fluxo do `/migration`.

## 4. Implementar

- Seguir `.claude/docs/coding-standards.md`; copiar a estrutura de `.claude/examples/` (Controller magro, Service com a lógica, Model com prepared statements nomeados).
- Nomes em inglês; views em `app/Views/<perfil>/<modulo>/`; ownership validado; CSRF em POST.

## 5. Verificar

- Migration criada/alterada → subagent **migration-checker**.
- Qualquer PHP alterado → subagent **code-reviewer** no diff. Corrigir os itens críticos antes de seguir.
- Se houver teste Playwright cobrindo o fluxo, rode-o (skill `testing`).

## 6. Encerrar

- Mover a tarefa em `specs/tasks.md` para "Feito" com a data.
- Resumo final: o que foi criado, arquivos tocados, como verificar manualmente (URL + perfil), e pendências se houver.
