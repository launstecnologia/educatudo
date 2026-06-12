# Faltas e lista de chamada

Como o módulo de **faltas** se relaciona com a **lista de chamada** da secretaria.

---

## São a mesma coisa?

**Não.** São módulos complementares:

| Módulo | Função | Caminho |
|--------|--------|---------|
| **Lista de chamada** | Número e ordem dos alunos na turma (secretaria, diário, PDF) | `/admin/turmas/{id}/lista-chamada` |
| **Faltas** | Lançamento de faltas por **bimestre**, turma e **matéria** (vai para boletim) | `/admin/faltas` |

A lista de chamada **não registra presença diária** — só a numeração/ordem oficial da turma.

O módulo de faltas **não redefine** o número de chamada — ele **reutiliza** a ordem já definida na lista.

---

## Como ficou integrado

Depois da migration `059_lista_chamada.sql`:

1. Ao **lançar faltas** (`/admin/faltas/lancar`), os alunos ativos da turma são listados na **mesma ordem** da lista de chamada do ano do evento
2. Aparece a coluna **Nº** (quando houver numeração cadastrada)
3. Um aviso na tela orienta a alterar ordem em **Turmas → Lista de chamada**

Se a migration não foi aplicada, as faltas continuam em ordem **alfabética** (comportamento anterior).

---

## Fluxo recomendado para a secretaria

```text
1. Cadastrar alunos na turma
2. Configurar lista de chamada (critério, data limite, recalcular se preciso)
3. Imprimir PDF da lista para arquivo da turma
4. Criar evento de faltas do bimestre incluindo a(s) turma(s)
5. Lançar faltas — mesma ordem da lista facilita conferência com o diário
```

---

## Lançar faltas — resumo

1. **Admin → Faltas**
2. **Novo evento**: nome (ex. "1º bimestre 2026"), bimestre, **ano letivo**, turmas participantes
3. Opcional: fixar matérias no evento (lançamento em matriz)
4. Abrir **Lançar** no evento
5. Filtrar turmas/matérias se necessário
6. Preencher quantidade de faltas por aluno (e por matéria)
7. **Salvar**

Somente alunos com `ativo = 1` e turma principal no evento aparecem na grade.

---

## Boletim

As faltas lançadas por matéria entram na soma usada pelo **boletim** (conforme configuração da escola). Eventos antigos sem matéria podem ter formato legado — a tela de lançamento avisa quando isso ocorre.

---

## Quando a ordem não bate

| Situação | O que fazer |
|----------|-------------|
| Aluno novo sem número | Cadastro recente: conferir lista de chamada; usar **Recalcular** se necessário |
| Remanejou de turma | Nº muda na turma destino — relançar filtro de turma no evento de faltas |
| TR / inativo ainda na lista impressa | TR permanece na lista histórica; não aparece em **faltas** (só ativos) |
| Ordem alfabética nas faltas | Migration 059 não aplicada ou aluno sem registro em `alunos_turma_chamada` |

Mais sobre lista: [lista-de-chamada.md](./lista-de-chamada.md)

Mais sobre alunos: [secretaria-guia-alunos.md](./secretaria-guia-alunos.md)
