# Lista de chamada por turma

## Onde acessar

1. **Admin** → **Turmas** → abra a turma → **Lista de chamada**
2. Ou diretamente: `/admin/turmas/{id}/lista-chamada`

Perfis com acesso: **coordenador** e **secretaria** (permissão `lista_chamada`).

## Pré-requisito

Rodar a migration `059_lista_chamada.sql` no banco da escola.

## Funcionalidades

- **Número de chamada automático** ao cadastrar aluno na turma
- **Critérios de ordem**: alfabética, meninas primeiro, meninos primeiro
- **Data limite**: alunos que entram após essa data vão para o **final** da lista (entrada tardia)
- **TR**: alunos com transferência escolar permanecem na lista impressa com marca **TR**
- **Recalcular lista**: reaplica o critério e renumera
- **PDF**: botão **Imprimir PDF** na tela da lista

## Campo sexo

No cadastro/edição do aluno, preencha **Sexo** para ordenação por meninas/meninos primeiro.

## Relação com o módulo de Faltas

O lançamento de **faltas** (`/admin/faltas/lancar`) usa a **mesma ordem** e o **número de chamada** desta lista (ano do evento de faltas). Não é necessário configurar duas vezes — mantenha a lista de chamada atualizada e as faltas seguirão a mesma ordem.

Detalhes: [faltas-e-lista-de-chamada.md](./faltas-e-lista-de-chamada.md)

## Cadastro de alunos

Para incluir aluno novo (que já entra numerado na lista): [como-cadastrar-aluno.md](./como-cadastrar-aluno.md)

Índice completo da secretaria: [secretaria-guia-alunos.md](./secretaria-guia-alunos.md)

## Checklist de aceite (coordenação)

- [ ] Aluno novo entra com nº automático
- [ ] Entrada após data corte vai ao final
- [ ] TR aparece na lista sem apagar histórico
- [ ] Remanejamento gera novo nº na turma destino
- [ ] Impressão PDF bate com a tela
