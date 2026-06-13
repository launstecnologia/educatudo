# Guia da secretaria — alunos, matrícula e movimentações

Índice dos fluxos de **gestão de alunos** no EducaTudo para secretaria e coordenação.

---

## Mapa rápido

| Preciso… | Onde ir | FAQ |
|----------|---------|-----|
| Cadastrar aluno novo | Admin → Alunos → Novo | [como-cadastrar-aluno.md](./como-cadastrar-aluno.md) |
| Lista numerada da turma (PDF) | Turmas → turma → **Lista de chamada** | [lista-de-chamada.md](./lista-de-chamada.md) |
| Lançar faltas do bimestre | Admin → **Faltas** | [faltas-e-lista-de-chamada.md](./faltas-e-lista-de-chamada.md) |
| Trocar de turma (continua na escola) | **Acadêmico → Movimentação de alunos** (Remanejamento) | [remanejamento-vs-transferencia.md](./remanejamento-vs-transferencia.md) |
| Saída da escola (TR) | **Acadêmico → Movimentação de alunos** (aba TR) | [remanejamento-vs-transferencia.md](./remanejamento-vs-transferencia.md) |
| Inativar um aluno | Ficha do aluno → **Inativar / TR** | [remanejamento-vs-transferencia.md](./remanejamento-vs-transferencia.md) |
| Matrículas / cursos paralelos | Ficha do aluno → Matrículas ou Turma → Vincular aluno | Este guia (seções abaixo) |
| Conferir vínculos e divergências | **Saúde Acadêmica** | [rematricula-manual.md](./rematricula-manual.md) (checklist) |
| Rematrícula (manual até wizard) | **Acadêmico → Movimentação de alunos** + matrículas na ficha | [rematricula-manual.md](./rematricula-manual.md) |

---

## Perfis e permissões

A **secretaria** (`admin_escola`, perfil `secretaria`) costuma ter acesso a:

- Alunos (cadastro, edição, ficha)
- Turmas, ano letivo, séries
- Faltas, ocorrências, grade horária
- Transferência, remanejamento, transferência escolar
- Lista de chamada
- Ativar / inativar aluno (com senha e confirmação)
- Matrículas do aluno

**Não** deve usar **exclusão física** do aluno (reservada ao perfil técnico/dev). Para saída da escola, use **inativação com motivo TRANSFERENCIA**.

---

## Cadastro e dados do aluno

### Campos importantes

- **Turma** — define turma principal e lista de chamada
- **RA / código** — identificador único
- **Sexo** — ordenação da lista (meninas/meninos primeiro)
- **Ativo** — alunos inativos não entram em faltas nem contagens de ativos

### Após salvar

O sistema mantém três vínculos alinhados:

1. `alunos.turma_id` — turma principal
2. `alunos_turmas_historico` — linha do tempo de turmas
3. `matricula` — vínculo formal por ano letivo (status ativa / transferido / concluído)

Detalhes do cadastro: [como-cadastrar-aluno.md](./como-cadastrar-aluno.md)

---

## Matrículas

Na **ficha do aluno** → seção **Matrículas**:

| Ação | Efeito |
|------|--------|
| **Adicionar matrícula** | Novo vínculo aluno + turma + ano |
| **Encerrar matrícula** | Status `transferido` ou `concluido`, com data de saída |
| **Sincronizar com cadastro** | Alinha matrícula ativa à turma principal do cadastro |

**Quando usar:** aluno em mais de uma turma/curso no mesmo ano, ou quando a coordenação exige registro formal separado do campo turma do cadastro.

Na ficha do aluno, use **Matrícula** (Ações Rápidas) e desmarque **Definir como turma principal** ao vincular curso extra em paralelo.

---

## Curso extra (Música, Robótica, etc.)

### Cadastro da estrutura

1. **Acadêmico → Curso** → tipo **Extra** (sem série)
2. **Acadêmico → Turmas** → turma vinculada ao curso extra
3. Vincular alunos na turma ou na ficha do aluno

### Vincular aluno já matriculado no regular

| Caminho | Observação |
|---------|------------|
| Ficha do aluno → **Matrícula** | Desmarque **Definir como turma principal** |
| Turma extra → **Vincular aluno** | Não altera a turma principal |
| Curso → **Importar / vincular (CSV)** | Alunos existentes recebem só matrícula adicional |

### Aluno só do curso extra

- Cadastre o aluno (sem turma no form) → **Matrícula** na turma extra **com** turma principal marcada, **ou**
- Importe via CSV na turma extra (aluno novo recebe esta turma como principal)

### Limitações conhecidas

- **Faltas, provas, jornadas e lista de chamada oficial** usam a **turma principal** (`alunos.turma_id`)
- Matrícula extra aparece na ficha da turma com badge **Matriculado**
- Para trocar a turma principal, use matrícula marcando **Definir como turma principal** ou remanejamento

---

## Lista de chamada

- Configuração por turma: critério (alfabética, meninas, meninos), **data limite**, recálculo e PDF
- Número automático ao cadastrar ou remanejar
- Aluno com **TR** permanece na lista impressa com marca TR

Guia completo: [lista-de-chamada.md](./lista-de-chamada.md)

---

## Faltas (bimestre)

Módulo separado em **Admin → Faltas**:

1. Criar **evento** (nome, bimestre, ano, turmas, matérias opcionais)
2. **Lançar faltas** por aluno e matéria
3. Valores podem ir para o **boletim** (conforme regras da escola)

**Vínculo com lista de chamada:** na tela de lançamento, os alunos aparecem na **mesma ordem** da lista de chamada (coluna **Nº**), desde que a migration 059 esteja aplicada.

Guia: [faltas-e-lista-de-chamada.md](./faltas-e-lista-de-chamada.md)

---

## Movimentações de turma

**Menu:** Admin → **Acadêmico → Movimentação de alunos**

### Remanejamento (troca interna)

- Aba **Remanejamento** — URL: `/admin/students/remanejamento`
- Aluno **permanece ativo**
- Nova turma + novo nº de chamada + matrícula sincronizada

### Transferência escolar (TR)

- Aba **Saída da escola (TR)** — URL: `/admin/students/transferencia-escolar`
- Inativa o aluno, encerra matrículas, marca TR na lista
- Exige observação, senha do operador e digitar **CONFIRMAR**

### Inativar na ficha (um aluno)

- Botão **Inativar / TR** na ficha
- Motivos: TRANSFERENCIA, EVASAO, CONCLUSAO, ADMINISTRATIVO

### Reativar

- Botão **Ativar** na ficha de aluno inativo
- Confirmação com **CONFIRMAR**

Detalhes: [remanejamento-vs-transferencia.md](./remanejamento-vs-transferencia.md)

---

## Checklist do início do ano letivo

- [ ] Turmas e ano letivo cadastrados
- [ ] Migration `059_lista_chamada.sql` aplicada (se usar lista numerada)
- [ ] Alunos importados ou cadastrados com turma e sexo
- [ ] Lista de chamada conferida e PDF impresso por turma
- [ ] Eventos de **faltas** criados por bimestre
- [ ] Responsáveis vinculados onde necessário
- [ ] **Saúde Acadêmica** conferida (`/admin/saude-academica`) — sem alertas críticos
- [ ] Rematrículas do ano validadas — [rematricula-manual.md](./rematricula-manual.md)

---

## Deploy / pré-requisito técnico

No servidor da escola (`src` atualizado + migration):

1. `058_monitores_perfil.sql` — se usar monitor de sala
2. `059_lista_chamada.sql` — lista de chamada e integração com faltas

Dúvidas de monitor: [como-cadastrar-monitor.md](./como-cadastrar-monitor.md)
