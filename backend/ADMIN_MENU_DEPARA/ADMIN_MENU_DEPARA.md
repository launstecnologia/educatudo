# Depara do menu lateral Admin

Comparação entre o menu **antes** (grupos planos: Acadêmico, Avaliações, Gestão Escolar…) e o menu **depois** (subgrupos **Estrutura**, **Pessoas e turmas**, **Como a escola avalia**, mais Rotina, Fechamento, Secretaria e Painéis).

Fontes:

- Antes: `backend/ADMIN_NAVBAR_FUNCIONALIDADES.md`
- Depois: `admin_sidebar_menu_principal.php` e `admin_sidebar_menu_secretaria.php`

Nem todo usuário vê todos os itens: o menu continua filtrado por perfil, permissão e módulo ligado.

---

## O que mudou na estrutura

| Antes (grupo do menu) | Depois |
|---|---|
| Dashboard (item solto no topo) | **Painéis → Dashboard** |
| Assistente (item solto) | Continua solto no topo |
| **Acadêmico** (lista única) | **Acadêmico**, com 3 subcabeçalhos: Estrutura · Pessoas e turmas · Como a escola avalia |
| **Avaliações** (grupo próprio) | Grupo some do lateral. Itens de nota/boletim entram em Acadêmico; jornadas e inclusão vão para Pedagógico; Relatórios vão para Painéis |
| **Gestão Escolar** | Renomeado para **Secretaria**. Parte do conteúdo sai para Rotina, Fechamento e Painéis |
| — | **Rotina** (novo): o dia a dia |
| — | **Fechamento** (novo): fim de período |
| — | **Painéis** (novo): indicadores e relatórios |
| Comunicação, Conteúdo, Financeiro, Monitoramento, Sistema, Usuários, Z-Configuração | Sem mudança de lugar |

---

## Depara item a item

### Acadêmico — Estrutura

| Funcionalidade | Rota | Antes | Depois | Observação |
|---|---|---|---|---|
| Implantar acadêmico | `/admin/implantar-academico` | não existia no menu | Acadêmico (acima dos subgrupos) | Trilha nova de implantação |
| Ano Letivo | `/admin/ano-letivo` | Acadêmico | Acadêmico → **Estrutura** | |
| Curso | `/admin/curso` | Acadêmico | Acadêmico → **Estrutura → Cursos e Séries** | Unificado com Série |
| Série | `/admin/serie` | Acadêmico | Acadêmico → **Estrutura → Cursos e Séries** | Tela única: `/admin/cursos-series` |
| Componentes Curriculares | `/admin/componentes-curriculares` | Acadêmico | Acadêmico → **Estrutura** | |
| Matriz Curricular | `/admin/matrizes-curriculares` | Acadêmico | Acadêmico → **Estrutura** | |
| Calendário Letivo | `/admin/calendario-letivo` | Acadêmico | Acadêmico → **Estrutura** | Secretaria: não lista no lateral (só no hub, se houver) |

### Acadêmico — Pessoas e turmas

| Funcionalidade | Rota | Antes | Depois | Observação |
|---|---|---|---|---|
| Professores | `/admin/teachers` | Acadêmico | Acadêmico → **Pessoas e turmas** | |
| Alunos | `/admin/students` | Acadêmico | Acadêmico → **Pessoas e turmas** | |
| Turmas | `/admin/turmas` | Acadêmico | Acadêmico → **Pessoas e turmas** | |
| Grade Horária | `/admin/grade-horaria` | Acadêmico | Acadêmico → **Pessoas e turmas** | |
| Salas / Ambientes | `/admin/salas` | Acadêmico | Acadêmico → **Pessoas e turmas** | |

### Acadêmico — Como a escola avalia

| Funcionalidade | Rota | Antes | Depois | Observação |
|---|---|---|---|---|
| Regras Acadêmicas | `/admin/regras-academicas` | Acadêmico | Acadêmico → **Como a escola avalia** | Label novo: **Regras de Aprovação** |
| Tipos de Nota | `/admin/provas/tipos-avaliacao` | Avaliações | Acadêmico → **Como a escola avalia** | Saiu do grupo Avaliações |
| Grupos de regras de notas | `/admin/grupos-regras-notas` | Acadêmico | Acadêmico → **Como a escola avalia → Quadro de Notas** | Rota nova: `/admin/quadros-notas` |
| Avaliações/Notas | `/admin/provas` | Avaliações | Acadêmico → **Como a escola avalia** | Label novo: **Lançamento de Notas** |
| Boletins | `/admin/boletins` | Acadêmico | Acadêmico → **Como a escola avalia** | Label novo: **Modelo de Boletim** |

### Rotina — O dia a dia

| Funcionalidade | Rota | Antes | Depois | Observação |
|---|---|---|---|---|
| Diário de Classe | `/admin/diario` | Gestão Escolar | **Rotina → O dia a dia** | |
| Faltas | `/admin/faltas` | Gestão Escolar | **Rotina → Frequência** | Unificado com Presença em `/admin/frequencia` |
| Presença | `/admin/presenca` | Gestão Escolar | **Rotina → Frequência** | Mesma tela de Frequência |
| Ocorrências | `/admin/ocorrencias` | Gestão Escolar | **Rotina → O dia a dia** | |

### Fechamento — Fim de período

| Funcionalidade | Rota | Antes | Depois | Observação |
|---|---|---|---|---|
| Painel de Fechamento | `/admin/fechamento` | não existia no menu | **Fechamento → Fim de período** | Módulo novo |
| Conselho de Classe | `/admin/conselhos` | Gestão Escolar | **Fechamento → Fim de período** | |
| Resultados Finais | `/admin/resultados-finais` | Gestão Escolar | **Fechamento → Fim de período** | |

### Secretaria (antes: Gestão Escolar)

| Funcionalidade | Rota | Antes | Depois | Observação |
|---|---|---|---|---|
| Censo Escolar | `/admin/censo` | Gestão Escolar | **Secretaria** | |
| Documentos Institucionais | `/admin/documentos-institucionais` | Gestão Escolar | **Secretaria** | |
| Assinatura Digital | `/admin/configuracao/assinatura-digital` | Gestão Escolar | Secretaria → Documentos Institucionais (submenu) | |
| Layout de documentos | `/admin/modelos-documentos` | Gestão Escolar | Secretaria → Documentos Institucionais (submenu) | Secretaria reduzida: item direto |
| Matrículas | `/admin/enrollment` | Gestão Escolar | **Secretaria** | |
| Configuração de Matrícula | `/admin/enrollment/config` | Gestão Escolar | Secretaria → Matrículas (submenu) | |
| Movimentação de alunos | `/admin/students/remanejamento` | Gestão Escolar | Secretaria → Matrículas (submenu) | Sem processo de matrícula: item direto |
| Vida Escolar | `/admin/vida-escolar` | Gestão Escolar | **Secretaria** | |
| Ofícios | `/admin/vida-escolar/oficios` | Gestão Escolar (sub de Vida Escolar) | Sem item próprio no lateral | Continua acessível pela Vida Escolar |
| Recursos Físicos | — | Gestão Escolar | Secretaria → **Outros da escola** | |
| Almoxarifado | `/admin/almoxarifado` | Gestão Escolar | Secretaria → Outros da escola → Recursos Físicos | |
| Patrimônio | `/admin/patrimonio` | Gestão Escolar | Secretaria → Outros da escola → Recursos Físicos | |
| TudiCoins da Escola | `/admin/tudicoins` | Gestão Escolar | Secretaria → **Outros da escola** | Só no menu principal (não no de Secretaria) |
| Pacotes de TudiCoins | `/admin/creditos/pacotes` | Gestão Escolar | Secretaria → TudiCoins (submenu) | Só no menu principal |

### Painéis

| Funcionalidade | Rota | Antes | Depois | Observação |
|---|---|---|---|---|
| Dashboard | `/admin/dashboard` | Item solto no topo | **Painéis** | |
| Saúde Acadêmica | `/admin/saude-academica` | Gestão Escolar | **Painéis** | |
| Conformidade | `/admin/conformidade` | Gestão Escolar | **Painéis** | |
| Relatórios | `/admin/relatorios` | Avaliações | **Painéis** | |

### Pedagógico (recebeu o que saiu de Avaliações)

| Funcionalidade | Rota | Antes | Depois | Observação |
|---|---|---|---|---|
| Aulas Online | `/admin/aulas-online` | Pedagógico | Pedagógico | Sem mudança |
| AVA / EAD | `/admin/ava` | Pedagógico | Pedagógico | Sem mudança |
| BNCC / Plano de Curso | `/admin/bncc` | Pedagógico | Pedagógico | Sem mudança |
| EducaCursos | `/admin/minicursos` | Pedagógico | Pedagógico | Sem mudança |
| Plano de Aula | `/admin/planos-aula` | Pedagógico | Pedagógico | Sem mudança |
| Avaliação Adaptativa | `/admin/inclusao/versoes` | Avaliações | **Pedagógico** | |
| Jornada da Redação | `/admin/redacao-professor` | Avaliações | **Pedagógico** | Também no menu Secretaria, se houver permissão |
| Jornada do Aluno | `/admin/jornadas` | Avaliações | **Pedagógico** | Também no menu Secretaria, se houver permissão |

---

## Saiu do menu lateral (rota ainda existe)

Esses itens estavam no lateral antigo e **não têm mais link próprio** no sidebar. A página continua acessível pela URL (e, em alguns casos, por outra tela).

| Funcionalidade | Rota antiga | Onde estava | Onde procurar agora |
|---|---|---|---|
| Agrupamentos | `/admin/agrupamentos-componentes` | Acadêmico | Sem item no menu. A ideia de “blocos de disciplinas” passou a aparecer no **Quadro de Notas** |
| Eventos de Notas | `/admin/boletim` | Avaliações | Sem item no menu. Hub `/admin/avaliacoes` ainda aponta para essa tela. Fluxo oficial de boletim: **Modelo de Boletim** + **Lançamento de Notas** |
| Guia do Boletim | `/admin/boletim-guia` | Avaliações | Sem item no menu |
| Grupo **Avaliações** | `/admin/avaliacoes` | Grupo do lateral | Hub ainda existe, mas o grupo não aparece mais no sidebar |
| Ofícios | `/admin/vida-escolar/oficios` | Gestão Escolar | Entrar em **Vida Escolar** |

---

## Grupos que não mudaram de lugar

Comunicação, Conteúdo, Financeiro, Monitoramento, Sistema, Usuários e Z-Configuração mantêm os mesmos itens e rotas.

Expo Colag continua no grupo da escola (nome customizável).

Assistente continua no topo, fora de grupo.

Sair continua em `/logout?portal=admin`.

---

## Mapa rápido do Acadêmico novo

Ordem de execução no lateral (não alfabética):

```
Acadêmico
├── Implantar acadêmico
├── Estrutura
│   ├── Ano Letivo
│   ├── Cursos e Séries          ← Curso + Série
│   ├── Componentes Curriculares
│   ├── Matriz Curricular
│   └── Calendário Letivo
├── Pessoas e turmas
│   ├── Professores
│   ├── Alunos
│   ├── Turmas
│   ├── Grade Horária
│   └── Salas / Ambientes
└── Como a escola avalia
    ├── Regras de Aprovação      ← Regras Acadêmicas
    ├── Tipos de Nota            ← vinha de Avaliações
    ├── Quadro de Notas          ← Grupos de regras de notas
    ├── Lançamento de Notas      ← Avaliações/Notas
    └── Modelo de Boletim        ← Boletins
```

---

## Menu Secretaria (perfil)

O perfil Secretaria segue o mesmo recorte: **Acadêmico** (com os 3 subgrupos), **Rotina**, **Pedagógico** (só jornadas, se permitido), **Fechamento** e **Secretaria** + **Painéis**.

Não vê Comunicação, Conteúdo, Financeiro, Monitoramento, Sistema, Usuários, Z-Configuração, Calendário Letivo no lateral, TudiCoins nem Conformidade.
