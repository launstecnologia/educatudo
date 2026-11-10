# Manual de fórmulas do boletim

Versão para leigo em PDF (completa, com exemplos numéricos): [`manual-formulas-boletim.pdf`](./manual-formulas-boletim.pdf) · HTML fonte: [`manual-formulas-boletim-leigo.html`](./manual-formulas-boletim-leigo.html).

Guia do editor de cálculo (coluna amarela: MU, Média Bim, Resultado Final, etc.) em **Boletim → Montar quadro** / assistente de evento.

A fórmula define **como a coluna é calculada**. As colunas usadas na conta (PP, PU, PE…) podem ficar ocultas na grade — elas continuam entrando no cálculo.

---

## 1. O que o motor aceita

| Recurso | Como usar | Exemplo |
|---|---|---|
| Soma / subtração | `+` `−` | `PP + PU` |
| Multiplicação (peso) | `×` | `PP × 2` |
| Divisão | `÷` | `(PP + PU) ÷ 2` |
| Parênteses | `( )` | `((PP × 2) + PU) ÷ 3` |
| Vírgula | Separar argumentos de função | `maior (PP, PU)` |
| Número | Campo **Número** → **Inserir** | `2`, `0.5`, `3` |
| Maior valor | `maior (` | `maior (PP, PU)` |
| Menor valor | `menor (` | `menor (PP, PU)` |
| Nota substituta | `nota substituta (` | `nota substituta (PP, PU, CH)` |
| Média com recuperação | `média c/ rec (` | `média c/ rec ((MS1 + MS2) ÷ 2, REC)` |

Regras gerais:

1. **Média simples** = soma das notas **entre parênteses**, depois **÷ quantidade**.  
   Ex.: `(Prova + Trabalho + Participação) ÷ 3`
2. A plataforma **corrige sozinha** o caso `a + b + c ÷ 3` (vira `(a + b + c) ÷ 3`). Mesmo assim, prefira escrever com parênteses.
3. Nota **ausente** (traço) **não vira 0** nas funções `nota substituta` e `média c/ rec`. Na conta aritmética pura (`+ − × ÷`), ausente entra como 0.
4. **Valor máximo** da coluna corta o resultado (ex.: escala 10). Em “Resultado Final × 2”, coloque valor máximo **20**.
5. Siglas na tela (PP, PU…) são rótulos; no cálculo usam o código do período (`b1_pp`, `b1_pu`…).

---

## 2. Fórmulas prontas (botões)

### 2.1 Média das peças

**O que faz:** monta a média aritmética de todas as peças marcadas no evento.

**Formato:** `(peça1 + peça2 + … + peçaN) ÷ N`

**Quando usar:** média igual para todas as notas (sem peso diferente).

**Exemplo:** 3 peças → `(Semanal + Bimestral + Trabalho) ÷ 3`

---

### 2.2 Maior entre as duas primeiras

**O que faz:** usa a função `maior (` com as **duas primeiras** peças do evento.

**Formato:** `maior (peça1, peça2)`

**Quando usar:** vale a maior nota entre duas provas (ex.: melhor entre 1ª e 2ª chamada já lançadas como peças).

**Se houver só 1 peça:** cai na “Média das peças”.

---

### 2.3 Média com nota substituta

**O que faz:** monta `nota substituta (bases…, substituta)`.

No print Santa Maria (PP / PU / CH):

- Bases: **PP** e **PU**
- Substituta: **CH** (2ª chamada)
- **Não inclui PE** automaticamente

**Formato gerado (SM):** `nota substituta (PP, PU, CH)`

**Semântica da função:**

1. Calcula a **média das bases** (PP e PU → ÷ 2).
2. Se **uma** base estiver ausente, a **substituta (CH)** entra **só no lugar da primeira ausente**.
3. Se as duas bases existem, **CH é ignorada**.
4. **Não coloque `÷ 2` de novo** depois da função — a média já está dentro.

**MU do Santa Maria (média + pontos extras):**

A MU oficial do manual SM é:

`((PP + PU) ÷ 2) + PE`

Com substituta (CH cobre falta de PP ou PU):

`nota substituta (PP, PU, CH) + PE`

Monte a pronta “Média com nota substituta” e **acrescente** `+` e **PE**.

| Situação | Conta efetiva |
|---|---|
| PP=8, PU=6, CH=—, PE=1 | `((8+6)÷2)+1` = **8** |
| PP=—, PU=6, CH=7, PE=1 | `((7+6)÷2)+1` = **7,5** |
| PP=8, PU=—, CH=5, PE=0 | `((8+5)÷2)+0` = **6,5** |
| PP=—, PU=—, CH=9, PE=1 | CH cobre só a 1ª; a 2ª segue ausente → conta com o que a função montar; preferível lançar ao menos uma base |

---

### 2.4 Média com recuperação

**O que faz:** monta `média c/ rec (média, REC)`.

**Formato típico:** `média c/ rec ((MS1 + MS2) ÷ 2, REC)`  
(ou as médias/blocos que o assistente encontrar)

**Semântica:**

| REC | Resultado |
|---|---|
| Ausente (traço) | Fica **só a média** |
| Lançada | `(média + REC) ÷ 2` |

**Quando usar:** fechamento de semestre/ano com recuperação opcional.

---

### 2.5 Limpar

Apaga todos os chips da fórmula desta coluna (não apaga o título nem o valor máximo).

---

## 3. Funções avulsas (sinais amarelos)

### 3.1 `maior (` / `menor (`

- `maior (A, B)` → o maior entre A e B (aceita mais de 2 argumentos).
- `menor (A, B)` → o menor.

Útil para “fica a melhor nota” ou “fica a pior” entre colunas.

### 3.2 `nota substituta (`

Igual à pronta da seção 2.3, montada à mão:

`nota substituta (base1, base2, …, substituta)`

- Último argumento = **substituta**.
- Demais = **bases** da média.
- Substituta preenche **somente a primeira base ausente**.

### 3.3 `média c/ rec (`

Igual à pronta da seção 2.4:

`média c/ rec (expressão_da_média, coluna_REC)`

---

## 4. Média com peso (ponderada)

Não existe botão “peso”. Use **×** (multiplicar) e **÷** (dividir pela soma dos pesos).

### Regra

`(nota1 × peso1) + (nota2 × peso2) + …  ÷  (peso1 + peso2 + …)`

Sempre **some os termos ponderados entre parênteses** (ou deixe a correção automática) e **divida pela soma dos pesos**.

### Exemplos

| Objetivo | Fórmula no editor |
|---|---|
| PP peso 2, PU peso 1 | `((PP × 2) + PU) ÷ 3` |
| Prova 60%, trabalho 40% | `((Prova × 6) + (Trabalho × 4)) ÷ 10` |
| Semanal 1 + Bimestral 2 | `((Semanal × 1) + (Bimestral × 2)) ÷ 3` |
| MU SM sem CH | `((PP + PU) ÷ 2) + PE` |
| MU SM com CH | `nota substituta (PP, PU, CH) + PE` |
| Média bim × 2 no final | `(media_bim × 2)` e valor máximo **20** |

### Passo a passo no editor

1. Abra o bloco de cálculo (ex.: MU).
2. Clique nas siglas à esquerda (PP, PU…).
3. Use `×` e **Número → Inserir** para o peso.
4. Feche com `)` se precisar e `÷` + soma dos pesos.
5. **Salvar bloco**.
6. Na grade, pode ocultar PP/PU/CH/PE e deixar **só MU** — o cálculo continua usando as ocultas.

---

## 5. Casos Santa Maria (resumo)

| Coluna | Fórmula sugerida | Observação |
|---|---|---|
| **MU** | `nota substituta (PP, PU, CH) + PE` ou `((PP + PU) ÷ 2) + PE` | PE é **soma**, não entra na média |
| **MS** (semestre) | `(MU1 + MU2) ÷ 2` | Sem as duas MUs → traço |
| **MA** | `(MS1 + MS2) ÷ 2` | |
| **MF** com REC | `média c/ rec (MA, PF)` (ou a REC do layout) | Sem REC = MA |

Colunas **PP, PU, CH, PE, FAL** no demonstrativo são **lançamento** (sem fórmula). A fórmula fica na coluna **calculada** (MU, MS, MA, MF…).

---

## 6. Erros comuns

| Erro | O que acontece | Como evitar |
|---|---|---|
| `nota substituta (PP, PU, CH) ÷ 2` | Nota pela metade | A função já faz a média |
| Esquecer `+ PE` na MU SM | MU = só a média PP/PU | Some PE depois da função |
| Peso sem dividir pela soma | Infla a nota | Sempre `÷ (soma dos pesos)` |
| Valor máximo 10 com fórmula × 2 | Resultado cortado em 10 | Suba o valor máximo (ex.: 20) |
| Só MU na grade e achar que “não calcula” | Antes podia zerar a mãe | Ocultar coluna ≠ tirar do cálculo; recarregue a prévia após salvar |
| Usar REC dentro de `+` simples | Ausente vira 0 e puxa a média | Prefira `média c/ rec (` |

---

## 7. Exceção por matéria

Em **Exceção só nesta matéria**: uma disciplina pode ter fórmula diferente (ex.: sem semanal → só bimestral + ENAC). As demais matérias seguem a fórmula geral da coluna.

---

## 8. Checklist rápido

1. Definir **título** e **valor máximo** da coluna.
2. Escolher uma **fórmula pronta** ou montar com chips + sinais.
3. Para peso: `×` peso em cada nota e `÷` soma dos pesos.
4. **Salvar bloco**.
5. Ocultar colunas intermediárias se quiser (só MU no print).
6. Conferir a **prévia** com aluno real.

---

*Referência de implementação: editor em `backend/app/Modulos/boletins/Views/admin/boletim/_assistente_wizard.php`; motor em `ResultadoAcademicoService::avaliarFormula` (`chamada` / `composicao` / `max` / `min`).*
