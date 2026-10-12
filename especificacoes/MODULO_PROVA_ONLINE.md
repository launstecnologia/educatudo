# Módulo: Prova Online

> Explicação em linguagem simples do módulo de Provas Online da plataforma EducaTudo.

---

## O que é

É o sistema de **avaliações digitais** da escola. Em vez de aplicar prova no papel, o professor cria a prova dentro da plataforma, os alunos respondem no computador/celular, e (na maioria dos casos) a correção é automática.

---

## Conceitos principais

| Termo | O que significa |
|---|---|
| **Bloco de Prova** | Um "evento" de avaliação que agrupa várias provas de matérias diferentes no mesmo período — ex.: "Simulado de Janeiro" reunindo Matemática, Português e História, todas no mesmo intervalo de datas. |
| **Prova** | Uma avaliação de uma matéria específica, criada por um professor, dentro de um bloco. |
| **Questão** | Cada pergunta da prova. Pode ser de múltipla escolha, verdadeiro/falso, ou resposta curta/discursiva. |

---

## Quem usa e onde

| Perfil | Onde acessa | O que faz |
|---|---|---|
| **Admin** | `/admin/provas` | Cria e organiza os blocos de prova, acompanha relatórios gerais |
| **Professor** | `/professor/provas` | Cria a prova da sua matéria, cadastra as questões, corrige o que precisa de correção manual |
| **Aluno** | `/provas` | Faz a prova dentro do prazo, vê a nota depois da liberação |

---

## Como o professor cria uma prova

1. Escolhe (ou recebe já pronto, se o admin organizou) o bloco de prova e a data/horário.
2. Cria a prova vinculada à sua matéria e turma.
3. Adiciona as questões, que podem vir de três formas:
   - **Digitadas manualmente** — o professor escreve a questão e as alternativas.
   - **Geradas por Inteligência Artificial** — o professor descreve o assunto e a dificuldade, e a IA sugere as questões (o professor revisa antes de usar).
   - **Importadas de um banco de questões** — uma biblioteca de questões já prontas, organizadas por assunto e dificuldade, prontas para reaproveitar.
   - **Coladas em JSON** — se as questões já vieram de outro lugar da plataforma (por exemplo, da "IA da Apostila"), o professor pode colar tudo de uma vez em vez de digitar uma por uma.
4. Define o valor de cada questão e a ordem.
5. Libera a prova para os alunos verem (antes disso, ela fica invisível para eles).

---

## Como o aluno faz a prova

1. A prova aparece na tela do aluno quando o bloco está ativo e liberado.
2. O aluno inicia a prova — o cronômetro começa a contar (se houver tempo definido).
3. Responde questão por questão.
4. Ao terminar (ou quando o tempo acabar), a prova é entregue automaticamente.
5. Questões de múltipla escolha e verdadeiro/falso são **corrigidas na hora**. Questões discursivas/abertas ficam esperando a correção do professor.

---

## Correção

- **Automática**: múltipla escolha e verdadeiro/falso — o sistema já sabe a resposta certa e calcula a nota no mesmo instante.
- **Manual**: questões discursivas — o professor lê a resposta do aluno e atribui a nota.
- O professor pode ver estatísticas por questão: quantos alunos acertaram, qual foi a questão mais difícil, etc. — útil para saber em que ponto a turma teve mais dificuldade.

---

## Provas adaptadas (acessibilidade)

Alunos com laudo médico ou pedagógico (TDAH, dislexia, baixa visão, etc.) podem ter uma **versão adaptada** da prova, configurada pela coordenação da escola através do módulo **EducaInclui**. As adaptações vão desde coisas simples (fonte maior, mais tempo, leitura em voz alta) até mudanças no conteúdo (menos alternativas, menos questões, linguagem mais simples) — sempre revisadas por uma pessoa antes de valerem. *(Veja documentação específica do EducaInclui para detalhes.)*

---

## Perguntas frequentes

**Os alunos de uma mesma turma podem receber a prova em ordem diferente?**
Sim, é possível embaralhar a ordem das questões e das alternativas para dificultar a "cola" entre vizinhos.

**O aluno pode ver a nota imediatamente?**
Depende da configuração: o professor pode optar por liberar a nota só depois de uma certa data (por exemplo, depois que todos os alunos terminarem).

**É possível reaplicar a mesma prova para outra turma?**
Sim, o professor pode duplicar uma prova já criada.
