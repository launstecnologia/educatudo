# Rematrícula manual (até existir wizard no sistema)

O EducaTudo **ainda não possui** um assistente de rematrícula em lote. Até a Fase 2 do roadmap, a secretaria deve seguir o fluxo manual abaixo — **sem recriar alunos** e **sem alterar IDs**.

Para o índice completo da secretaria, veja [secretaria-guia-alunos.md](./secretaria-guia-alunos.md).

---

## Quando usar

- Início de ano letivo: alunos que **permanecem na escola** e vão para nova turma/série
- Aluno ativo que precisa de **nova matrícula formal** no ano corrente

**Não use rematrícula manual para:**

- Saída da escola → [Transferência escolar (TR)](./remanejamento-vs-transferencia.md)
- Troca de turma no **mesmo ano** → [Remanejamento](./remanejamento-vs-transferencia.md)

---

## Passo a passo recomendado

### 1. Preparar estrutura do ano novo

1. **Acadêmico → Ano letivo** — criar/ativar o ano destino
2. **Acadêmico → Turmas** — cadastrar turmas do ano novo (ex.: 7º A 2026)
3. Conferir cursos/séries (estrutura regular) e turmas de **curso extra**, se houver

### 2. Rematricular aluno a aluno (ou em lote por remanejamento)

**Opção A — Remanejamento em lote (mesma escola, troca de turma principal)**

1. Admin → **Remanejamento** (`/admin/students/remanejamento`)
2. Selecione turma origem → turma destino → alunos
3. O sistema atualiza turma principal, matrícula, histórico e lista de chamada

**Opção B — Ficha do aluno (controle fino)**

1. Abra a ficha do aluno
2. **Editar** → altere a **turma principal** para a turma do ano novo (se for o caso)
3. Seção **Matrículas** → **Adicionar matrícula** na turma/ano novo
4. Encerre matrícula(s) do ano anterior com status **concluído** (não exclua o aluno)

**Curso extra em paralelo:** ao adicionar matrícula na turma extra, **desmarque** “Definir como turma principal”.

### 3. Conferir lista de chamada

1. **Turmas → turma → Lista de chamada**
2. Recalcular se necessário; imprimir PDF para arquivo da turma

Detalhes: [lista-de-chamada.md](./lista-de-chamada.md)

### 4. Validar com Saúde Acadêmica

1. Admin → **Saúde Acadêmica** (`/admin/saude-academica`)
2. Selecione o **ano letivo** → **Analisar**
3. Corrija alertas (sem matrícula, divergência, falta de nº na chamada)

---

## O que NÃO fazer

| Evitar | Motivo |
|--------|--------|
| Reimportar planilha de alunos “do zero” | Gera duplicidade ou novo ID; perde histórico pedagógico |
| Excluir aluno físico do banco | Use inativação/TR; exclusão é perfil dev |
| Forçar “Sincronizar matrícula” sem conferir | Pode encerrar matrícula de curso extra válida |
| Trocar RA/código pensando em “novo aluno” | RA deve permanecer o identificador da escola |

---

## Checklist pós-rematrícula

- [ ] Turma principal correta na ficha
- [ ] Matrícula **ativa** no ano novo
- [ ] Matrícula do ano anterior **encerrada** (`concluido` ou `transferido`)
- [ ] Número na lista de chamada
- [ ] Saúde Acadêmica sem alertas críticos
- [ ] Curso extra: matrícula paralela sem alterar turma principal

---

## Próxima evolução (roadmap)

Está previsto um **wizard de rematrícula** com:

- Mapeamento turma origem → turma destino
- Pré-visualização (dry-run)
- Execução em lote **sem** criar novos `aluno_id`

Até lá, este guia é o procedimento oficial.

---

## Ver também

- [secretaria-guia-alunos.md](./secretaria-guia-alunos.md) — matrículas e curso extra
- [como-cadastrar-aluno.md](./como-cadastrar-aluno.md) — aluno novo
- [remanejamento-vs-transferencia.md](./remanejamento-vs-transferencia.md) — movimentações
