# Censo Escolar

A escola declara ao INEP quem estuda e quem trabalha nela. O EducaTudo monta o arquivo. A secretaria baixa e envia no Educacenso.

Novembro de 2026 · Admin → Censo Escolar

Este texto é o que vai para a escola. O PDF correspondente é `censo-escolar-educacenso.pdf`.

---

## O que a secretaria faz

1. Abre a edição do ano, na **Matrícula Inicial**.
2. Sincroniza. O sistema traz as turmas daquele ano letivo, os alunos ativos dessas turmas e os professores da grade.
3. Confere a data de referência e completa o que o INEP pede e ainda está vazio.
4. Corrige os erros. Com erro impeditivo, o botão de gerar arquivo fica desligado.
5. Gera o TXT, baixa em Exportações e importa no Educacenso.

---

## Quem entra na declaração

A edição é de um **ano letivo**. Entram as turmas desse ano. Entram os alunos ativos vinculados a essas turmas, cada um com a matrícula da turma em que está. Entram os professores que aparecem na grade dessas turmas. Se a edição for de uma unidade, só entra essa unidade.

A **data de referência** é o dia da fotografia do Censo: quem a escola declara naquele dia. No painel ela aparece como Referência (em 2026, 28/05/2026). O sistema sugere 28 de maio do ano da edição. Dá para mudar em Configuração.

A lista não se corta sozinha por essa data. Quem saiu antes, ou não deve ser declarado, a secretaria tira na matrícula ou no vínculo, em **Incluir na exportação → Não**. A pessoa continua no cadastro da escola.

---

## Os dois TXT

**Importação.** Entra no Educacenso. Nome `mi2026_v1.txt`. Cada nova geração vira v2, v3, sem apagar a anterior. Campos separados por `|`. A última linha é `99|`.

**Identificação.** Só é criado se algum aluno do arquivo ainda não tem código INEP de 12 dígitos. Nome `id2026_v1.txt`. Leva nome, nascimento, filiação e município.

| Linha | O que é |
|---|---|
| 00 e 10 | A escola: identificação e infraestrutura |
| 20 | As turmas |
| 30 | Alunos, professores e gestores |
| 40 | Quem é o gestor |
| 50 | Qual professor está em qual turma |
| 60 | Qual aluno está matriculado em qual turma |
| 99 | Fim do arquivo |

---

## Enviar ao Educacenso

1. Em Exportações, baixe o TXT de importação da versão que acabou de gerar.
2. No Educacenso, abra a migração da **Matrícula Inicial** daquele ano e importe o arquivo.
3. Se o site pedir a identificação dos alunos novos, envie também o TXT de identificação.

## Se o arquivo for recusado

O Educacenso devolve a crítica (qual linha e qual campo). A secretaria corrige no EducaTudo, em Pendências ou na aba da pessoa, gera de novo e importa a versão nova. A versão anterior continua na lista, com data e quantidade de linhas.

Erro impeditivo o EducaTudo já segura antes do download. Alerta de pessoa sem código INEP não segura: esse código o Educacenso cria na identificação.

## Como conferir que deu certo

1. Na visão geral, os blocos usados estão completos e os erros impeditivos estão em zero.
2. O Educacenso aceita o arquivo, sem crítica pendente.
3. Quando o Educacenso devolver os códigos INEP, a secretaria sobe esse retorno em **Retornos**, confere e só então grava o código no aluno. O sistema não aplica o código sozinho.

O botão **CSV auxiliar** baixa uma planilha para olhar os alunos no Excel. Esse CSV não entra no Educacenso.

## No fim do ano

A mesma tela tem a **Situação do Aluno**: aprovado, reprovado, transferido, deixou de frequentar. O resultado acadêmico já lançado na escola é reaproveitado. É a segunda coleta do Censo, separada do TXT da Matrícula Inicial.

---

## Nota interna

Não entra no PDF da escola.

O TXT da Situação do Aluno ainda não é emitido: o leiaute oficial dessa etapa não está carregado. A tela guarda o resultado. A data de referência é gravada na edição (padrão `ano-05-28`) e aparece no painel; a sincronização escolhe as pessoas pelo ano letivo da turma, pelo aluno ativo e pela unidade, não pela data.
