# TudiCoins — créditos de IA

> Nome de produto na UI: **TudiCoins**. No código/banco legado as chaves ainda usam `creditos_*` / `carteira_*` (rename só de UI nesta fase).

## Quando TudiCoins está desligado

- Módulos **100% IA** somem do menu e das rotas (`FeatureGate` + Master força off).
- Em módulos **mistos** (ex.: Jornada), só somem botões/ações de IA; o fluxo manual continua.
- Com TudiCoins off, ações de IA **não rodam** (não é “libera de graça”).

## Tipos de ação cobrável

Cada chave em `CreditosModuleRegistry` tem:

| Campo | Valores | Exemplo |
|-------|---------|---------|
| `escopo_ui` | `modulo_inteiro` \| `acao` | Flashcard = módulo; gerar exercício na jornada = ação |
| `feature_modules` | chaves `module_*` | `['aluno_flashcards']`, `['chat']` |
| `pagador` | `usuario` \| `escola` | EducaInclui debita a escola |

## Modelos comerciais (flags em `config_layout`)

| Flag | Efeito |
|------|--------|
| `creditos_habilitado` | Liga o sistema TudiCoins |
| `creditos_modo_pool_escola` | Consumo do aluno/professor debita a **carteira da escola** |
| `creditos_exibir_menu_carteira` | Aluno vê Minha Carteira (mesmo com pool) |
| `creditos_exibir_menu_comprar` | Atalho EducaShop no menu do aluno (forçado on se `creditos_aluno_pode_comprar`) |
| `creditos_liberar_escola_comprar` | Escola (admin) pode comprar pacotes para a carteira institucional |
| `creditos_aluno_pode_comprar` | Aluno compra pacote avulso (Asaas/EducaShop); libera o menu EducaShop automaticamente |
| `creditos_mensal_aluno` / `_professor` / `_escola` | Cota B2B mensal (recarga cron/manual) |
| `creditos_liberar_b2c` | Legado: planos B2C (assinatura); UI Master→Escola não edita mais esta flag |

Cortesia manual no Master→Escola foi removida da UI (tipo `cortesia` no extrato permanece no serviço).

## Catálogo Master (obrigatório em feature nova com IA)

1. Adicionar chave em `CreditosModuleRegistry` (label, grupo, escopo_ui, feature_modules, pagador).
2. Incluir o item nas **tabelas de custo** (`/master/creditos-catalogo/tabelas`).
3. Se for módulo opcional por escola: registrar em `FeatureGate` + listas do Master (`MODULOS_*`).
4. Se `escopo_ui = modulo_inteiro`: o Master só permite habilitar o módulo com TudiCoins on.
5. Debitar via `CreditosService::consumir(...)` (ou `consumirEscola` quando `pagador = escola`).

## EducaInclui (carteira da escola)

| Chave | Quando debita |
|-------|----------------|
| `educainclui_analisar_laudo` | Cadastro/análise de laudo (OCR + IA) |
| `educainclui_gerar_prova` | Geração de versão adaptada de prova |

## Checklist `/new-module` e código novo com IA

Ver também `.cursor/rules/tudicoins.mdc` e o comando `/new-module`.
