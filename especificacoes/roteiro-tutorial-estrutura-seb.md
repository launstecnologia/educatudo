# Roteiro — Tutorial: Acadêmico → Estrutura (Grupo SEB)

**Público:** secretaria / coordenação da SEB  
**Duração sugerida:** 12–15 min  
**Objetivo:** mostrar, na ordem certa, como montar a base da escola antes de turmas, notas e boletim.

**Ordem obrigatória:**  
Ano Letivo → Cursos e Séries → Componentes Curriculares → Matriz Curricular → Calendário Letivo

---

## O que é cada item

| Item | O que é |
|---|---|
| Ano Letivo | Define o ano escolar, as datas e se a escola trabalha com bimestre, trimestre ou semestre |
| Cursos e Séries | Cadastro dos cursos (Fundamental, Médio…) e das séries de cada um |
| Componentes Curriculares | Lista das disciplinas (Matemática, Português, etc.) |
| Matriz Curricular | Diz quais disciplinas cada série tem e a carga horária de cada uma |
| Calendário Letivo | Marca dias letivos, feriados e recessos do ano |

**Observação SEB:** no cadastro de Componentes Curriculares **não** usamos vínculo mãe/filho (unidade curricular / desdobramentos). Cada disciplina é cadastrada sozinha.

---

## Checklist de gravação

- [ ] Conta admin com permissão Acadêmico
- [ ] Ambiente de teste (não produção SEB)
- [ ] Resolução 1920×1080, zoom 100–110%
- [ ] Cursor destacado
- [ ] Dados fictícios (sem nome real de aluno)
- [ ] Mostrar sucesso após cada Salvar
- [ ] Não abrir Z-Config / Sistema neste vídeo
- [ ] Não mostrar campo Unidade Curricular / desdobramentos

## Capítulos sugeridos (YouTube / Drive)

1. 0:00 Introdução  
2. 0:45 Ano Letivo  
3. 3:30 Cursos e Séries  
4. 6:00 Componentes Curriculares  
5. 9:00 Matriz Curricular  
6. 12:00 Calendário Letivo  
7. 14:30 Recado final  

---

## BLOCO 0 — Abertura (0:00–0:45)

**Tela:** menu lateral, seção Acadêmico aberta, subseção Estrutura.

**Fala:**

> “Neste vídeo vamos configurar a Estrutura acadêmica. É a base de tudo: ano, cursos, disciplinas, matriz e calendário. Sem isso, a escola não consegue montar turma, lançar nota nem gerar boletim. Vamos na ordem do menu.”

**Ação:** clicar em **Acadêmico** → expandir **Estrutura** → apontar os 5 itens.

---

## BLOCO 1 — Ano Letivo (0:45–3:30)

### 1.1 Entrar na tela

**Ação:** clicar em **Ano Letivo**.

**Fala:**

> “Ano Letivo define o ano escolar, as datas de início e fim, e se a escola trabalha com bimestre, trimestre, semestre ou etapa única. Essa organização vale para prova, boletim e conselho.”

### 1.2 Criar um ano

**Ação:** clicar em **Novo Ano Letivo** (painel lateral abre).

| Campo | Exemplo | O que dizer |
|---|---|---|
| Ano Letivo | 2026 | “Ano civil da escola.” |
| Ano letivo ativo | marcado | “Só o ano ativo aparece nas telas do dia a dia.” |
| Início | 01/02/2026 | “Primeiro dia letivo.” |
| Término | 15/12/2026 | “Último dia letivo.” |
| Organização | Trimestral *(ou Bimestral, conforme a SEB)* | “Aqui nasce o 1º, 2º e 3º período. Não existe outra tela para cadastrar trimestre.” |

**Fala extra:**

> “Embaixo aparecem os Períodos Letivos. Em cada um, informe início, término e dias letivos. As datas precisam caber no ano e não podem se sobrepor.”

**Ação:** preencher 1º / 2º / 3º (ou bimestres) → **Salvar**.

**Fechar bloco:**

> “Pronto. Com o ano ativo e a organização definida, o resto do sistema já sabe como o ano se divide.”

---

## BLOCO 2 — Cursos e Séries (3:30–6:00)

### 2.1 Entrar

**Ação:** **Cursos e Séries**.

**Fala:**

> “Aqui cadastramos o caminho do aluno: o curso e as séries ou anos escolares de cada curso. Exemplo: Ensino Fundamental II com 6º, 7º, 8º e 9º.”

### 2.2 Criar curso

**Ação:** **Novo Curso**.

| Campo | Exemplo | O que dizer |
|---|---|---|
| Curso | Ensino Fundamental — Anos Finais | “Nome que a escola usa.” |
| Tipo | Regular | “Regular entra no histórico oficial. Extra é complemento, como curso paralelo.” |
| Cor | (opcional) | “Só para identificar visualmente.” |
| Ativo | marcado | — |

**Salvar.**

### 2.3 Criar séries

**Ação:** no card do curso → **Nova série / ano escolar**.

**Exemplos a cadastrar:** 6º Ano, 7º Ano, 8º Ano, 9º Ano.

**Fala:**

> “Cada série fica ligada ao curso. Você pode arrastar pela alça para mudar a ordem. A ordem importa: é a progressão do aluno.”

**Dica rápida (sem aprofundar):**

> “No menu de ações do curso também existe importar alunos. Isso fica para outro vídeo; agora o foco é só a estrutura.”

---

## BLOCO 3 — Componentes Curriculares (6:00–9:00)

### 3.1 Entrar

**Ação:** **Componentes Curriculares**.

**Fala:**

> “Aqui fica a lista de disciplinas da escola: Matemática, Português, História… É o catálogo. Depois a Matriz diz quais entram em cada série.”

### 3.2 Criar disciplina

**Ação:** **Novo Componente Curricular**.

| Campo | Exemplo | O que dizer |
|---|---|---|
| Componente Curricular | Matemática | “Nome da disciplina.” |
| Código | MAT | “Obrigatório. Usado em listas e relatórios.” |
| Sigla | MAT | “Opcional, para telas compactas.” |
| Área do Conhecimento | Matemática | “Área da disciplina.” |
| Tipo/Categoria | conforme a lista da escola | “Como a escola classifica.” |
| Ciclos / Etapas | Fund. II = Obrigatória *(ajustar à etapa)* | “Em quais etapas essa disciplina vale.” |
| Permite avaliação | marcado | “Se recebe nota.” |
| Permite frequência | marcado | “Se entra na chamada.” |
| Permite diário | marcado | “Se aparece no diário de classe.” |
| Ativo | marcado | — |

**Salvar.**

**Fala:**

> “Repita para as disciplinas do curso. Cada componente entra sozinho na lista — sem vínculo de disciplina mãe e filha.”

**Demo sugerida:** cadastrar 2 ou 3 (Matemática, Língua Portuguesa, História) e mostrar na tabela.

### Não mostrar neste vídeo

- campo **Unidade Curricular**
- desdobramentos (ex.: Gramática abaixo de Português)
- badge “Unidade” / setinha de filhos na lista

---

## BLOCO 4 — Matriz Curricular (9:00–12:00)

### 4.1 Entrar

**Ação:** **Matriz Curricular**.

**Fala:**

> “A Matriz liga série e disciplinas. Ela diz: no 6º Ano, quais componentes entram, com quantas aulas por semana e qual carga. Depois, ao criar a turma, a gente escolhe essa matriz.”

### 4.2 Nova matriz

**Ação:** **Nova Matriz**.

| Campo | Exemplo | O que dizer |
|---|---|---|
| Nome | EF Anos Finais — 6º Ano — 2026 | “Nome claro para a coordenação.” |
| Código | EF-6-2026 | “Código único.” |
| Curso | Ensino Fundamental — Anos Finais | “Primeiro o curso.” |
| Série | 6º Ano | “Só lista séries daquele curso.” |
| Duração padrão da aula | 50 | “Em minutos. Serve para calcular carga.” |
| Dias letivos previstos | 200 | “Opcional, referência anual.” |
| Matriz ativa | marcado | — |

### 4.3 Adicionar componentes

**Ação:** **Adicionar componente** → escolher Matemática → aulas/semana (ex.: 5) → obrigatório.

Repetir com Português, História…

**Fala:**

> “Aqui você só escolhe o componente, informa as aulas por semana e se é obrigatório. A carga semanal calcula sozinha e o total aparece embaixo.”

**Salvar.**

**Fechar bloco:**

> “Sem matriz, a turma fica sem referência de disciplinas e carga. Por isso ela vem depois de cursos e componentes.”

---

## BLOCO 5 — Calendário Letivo (12:00–14:30)

### 5.1 Entrar

**Ação:** **Calendário Letivo**.

**Fala:**

> “O Calendário marca os dias do ano: feriados, recessos, reposições e eventos. É o mapa visual do ano letivo.”

### 5.2 Configurar o ano (se ainda não houver)

**Ação:** **Configurar ano** → confirmar vínculo com o ano / cursos-séries conforme a tela → salvar.

### 5.3 Adicionar evento

**Ação:** **Adicionar evento**.

| Campo | Exemplo | O que dizer |
|---|---|---|
| Tipo | Feriado / Recesso / Avaliação | “Cada tipo tem cor diferente no calendário.” |
| Descrição | Tiradentes | “Nome do evento.” |
| Data início / fim | 21/04/2026 | “Pode ser um dia ou um intervalo.” |

Cadastrar 2 exemplos: 1 feriado + 1 recesso.

**Fala:**

> “Você pode ver o ano inteiro ou só o mês. Em Eventos, a lista completa aparece. Filtros ajudam quando o calendário já está cheio.”

**Nota SEB:**

> “Neste pacote o foco é o calendário acadêmico (feriados e recessos). Avisos à família ficam de fora deste vídeo.”

---

## BLOCO 6 — Fechamento (14:30–15:00)

**Tela:** voltar ao menu Estrutura, passar o mouse pelos 5 itens.

**Fala:**

> “Recapitulando a ordem:  
> 1) Ano Letivo — datas e bimestre/trimestre  
> 2) Cursos e Séries — caminho do aluno  
> 3) Componentes — disciplinas  
> 4) Matriz — o que cada série cursa  
> 5) Calendário — feriados e eventos  
>  
> Com a Estrutura pronta, o próximo passo é Pessoas e turmas: professores, salas, turmas, alunos e grade horária. Até o próximo vídeo.”

**Tela final:** lista dos 5 itens com as descrições curtas da tabela do início.

---

## Rotas (referência interna)

| Item | Rota |
|---|---|
| Ano Letivo | `/admin/ano-letivo` |
| Cursos e Séries | `/admin/cursos-series` |
| Componentes Curriculares | `/admin/componentes-curriculares` |
| Matriz Curricular | `/admin/matrizes-curriculares` |
| Calendário Letivo | `/admin/calendario-letivo` |
