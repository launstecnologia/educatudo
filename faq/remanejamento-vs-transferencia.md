# Remanejamento vs transferência escolar (TR)

## Remanejamento (troca interna de turma)

- **Menu:** Admin → **Acadêmico → Movimentação de alunos** → aba **Remanejamento** (`/admin/students/remanejamento`)
- O aluno **permanece ativo** na escola
- Troca a turma principal, sincroniza matrícula e histórico
- Gera **novo número de chamada** na turma de destino
- Notas lançadas migram com a turma (mesmo comportamento da edição de turma)

## Transferência escolar (saída / TR)

- **Menu:** Admin → **Acadêmico → Movimentação de alunos** → aba **Saída da escola (TR)** (`/admin/students/transferencia-escolar`)
- **Inativa** o aluno com motivo TRANSFERENCIA (governança LGPD: senha + CONFIRMAR)
- Encerra matrículas ativas
- Marca **TR** na lista de chamada da turma de origem (histórico preservado)
- Opcional: remove a turma do cadastro após o TR

## Rota legada `/admin/students/transfer`

- Redireciona automaticamente para **Remanejamento**
- Use **Saída da escola (TR)** para aluno que deixa a escola — não remanejamento

## Inativar um aluno na ficha

Na ficha do aluno, use **Inativar / TR** (modal com motivo, observação e senha). Não use exclusão física — reservada ao perfil dev.

## Exclusão física

Coordenadora e secretaria **não** devem excluir alunos do banco. Use inativação/TR para manter histórico pedagógico e LGPD.

---

## Ver também

- [como-cadastrar-aluno.md](./como-cadastrar-aluno.md) — cadastro inicial
- [secretaria-guia-alunos.md](./secretaria-guia-alunos.md) — índice completo da secretaria
- [lista-de-chamada.md](./lista-de-chamada.md) — numeração por turma
- [faltas-e-lista-de-chamada.md](./faltas-e-lista-de-chamada.md) — faltas na mesma ordem da lista
