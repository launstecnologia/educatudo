# Módulo: Jornada do Aluno

> Explicação em linguagem simples do módulo de Jornadas da plataforma EducaTudo.

---

## O que é

Uma **"jornada"** é uma trilha de estudo organizada pelo professor — um caminho com etapas em sequência que o aluno percorre, em vez de receber tudo solto. É a principal forma de o professor entregar conteúdo de forma estruturada.

---

## Quem usa e onde

| Perfil | Onde acessa | O que faz |
|---|---|---|
| **Professor** | `/professor/jornadas` | Cria e organiza as jornadas e seus módulos |
| **Admin** | `/admin/jornadas` | Acompanha e gera relatórios de todas as jornadas da escola |
| **Aluno** | `/jornadas` | Percorre a jornada, módulo por módulo |

---

## A estrutura: jornada → módulos

Uma jornada é dividida em **módulos**, organizados em ordem. Cada módulo pode ser de um destes tipos:

| Tipo de módulo | O que contém |
|---|---|
| **Conteúdo/Vídeo** | Vídeo, documento (PDF, slide) ou texto explicativo do professor |
| **Exercício** | Questões de múltipla escolha, corrigidas automaticamente |
| **Resumo do aluno** | O aluno escreve, com suas palavras, o que aprendeu — o professor (ou a IA) pode comentar e avaliar |
| **Dica do professor** | Um recado/orientação rápida, geralmente em texto |
| **Redação** | (ver documento específico de [Jornada da Redação](MODULO_JORNADA_DA_REDACAO.md)) |

O professor define a ordem dos módulos, e pode escolher para quais turmas (uma ou várias) e em qual período (data de início e de término) a jornada fica disponível.

---

## Como o aluno percorre uma jornada

1. Na tela "Minhas Jornadas", o aluno vê todas as jornadas atribuídas a ele, com status: **Aguardando** (ainda não começou), **Em andamento**, **Concluída** ou **Expirada**.
2. Ao abrir uma jornada, vê os módulos em ordem.
3. Para cada módulo:
   - Se for conteúdo: assiste o vídeo ou lê o material.
   - Se for exercício: responde as questões — a correção é automática.
   - Se for resumo: escreve o texto e envia.
4. **O gabarito dos exercícios só é liberado depois que o aluno termina todos os módulos da jornada** — isso evita que ele veja a resposta antes de tentar responder de verdade.
5. O aluno pode acompanhar o **progresso** (% de módulos concluídos, taxa de acerto, tempo gasto em cada parte).

---

## Recursos de apoio

- **"Tudinha explica"**: em qualquer questão de exercício, o aluno pode pedir para a IA (Tudinha) explicar o porquê da resposta certa.
- **Dúvidas**: o aluno pode fazer uma pergunta vinculada a um módulo específico, e o professor (ou a IA) responde.

---

## O que o professor vê

- Lista de alunos que já completaram cada módulo.
- Resultado detalhado dos exercícios por aluno (quem acertou o quê).
- Tempo médio gasto em cada módulo — útil para identificar se um conteúdo está confuso ou muito longo.

---

## Perguntas frequentes

**Uma jornada pode ter conteúdos de tipos diferentes misturados?**
Sim — é comum ter vídeo, depois exercício, depois um resumo, todos na mesma jornada.

**O aluno pode pular um módulo e voltar depois?**
Os módulos seguem a ordem definida pelo professor; normalmente o aluno avança em sequência.

**Dá para reaproveitar uma jornada em outra turma?**
Sim, o professor pode vincular a mesma jornada a mais de uma turma.
