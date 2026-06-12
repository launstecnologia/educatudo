# Como cadastrar aluno

Guia para **secretaria e coordenação** incluir um novo aluno no EducaTudo.

Para o fluxo completo (matrícula, lista de chamada, faltas, movimentações), veja também [secretaria-guia-alunos.md](./secretaria-guia-alunos.md).

---

## Onde cadastrar

| Menu | Caminho |
|------|---------|
| **Alunos → Novo aluno** | `/admin/students/create` |

Perfis com acesso: **secretaria**, **coordenador**, **diretor** (conforme permissões da escola).

---

## Passo a passo

1. Acesse o painel **Admin**
2. Menu **Alunos** → botão **Novo aluno** (ou **Cadastrar**)
3. Preencha os campos obrigatórios:
   - **Nome completo**
   - **Turma** — turma principal do aluno no ano letivo
4. Recomendado para a secretaria:
   - **Código do aluno / RA** — identificador único na escola
   - **Nickname** — login do aluno no portal (se usar nickname)
   - **Sexo** — necessário para lista de chamada em ordem *meninas/meninos primeiro*
   - **Email**, **CPF**, **data de nascimento** — conforme política da escola
5. **Senha** — se deixar em branco, o sistema pode usar fluxo de primeiro acesso / senha padrão conforme configuração
6. Marque **Aluno ativo** se o cadastro já entra em uso
7. Salve

---

## O que o sistema faz automaticamente ao salvar

- Cria o registro em **alunos** com a turma principal (`turma_id`)
- Grava **histórico de turma** (`alunos_turmas_historico`)
- Cria **matrícula ativa** na turma (tabela `matricula`), se a estrutura estiver habilitada
- Atribui **número na lista de chamada** da turma (após migration `059_lista_chamada.sql`)
  - Respeita **data limite** da turma: entrada tardia vai ao final da lista

---

## Editar aluno depois do cadastro

| Ação | Onde |
|------|------|
| Editar dados | Ficha do aluno → **Editar** (`/admin/students/{id}/edit`) |
| Ver matrículas | Ficha → aba / seção **Matrículas** |
| Responsáveis | Ficha → **Cadastrar responsável** |

Alterar **turma** na edição sincroniza matrícula, histórico e lista de chamada (remanejamento interno). Para troca em lote, use [remanejamento](./remanejamento-vs-transferencia.md).

---

## Matrícula x turma do cadastro

- **Turma no cadastro** = turma principal visível em provas, jornadas e faltas
- **Matrículas** permitem vínculo formal aluno ↔ turma ↔ ano letivo (útil se o aluno tiver mais de um curso/turma no mesmo ano)
- Se matrícula e cadastro divergirem, na ficha use **Sincronizar matrícula com turma do cadastro**

---

## Erros comuns

| Problema | Solução |
|----------|---------|
| "Código do aluno já cadastrado" | RA/código duplicado — conferir se o aluno já existe |
| "Turma é obrigatória" | Selecione a turma antes de salvar |
| Aluno sem nº na lista | Rodar migration 059; conferir lista em **Turmas → Lista de chamada** |
| Aluno não aparece nas faltas | Só entram alunos **ativos**; evento de faltas deve incluir a turma do aluno |

---

## Próximos passos após cadastrar

1. Conferir **lista de chamada** da turma e imprimir PDF se necessário → [lista-de-chamada.md](./lista-de-chamada.md)
2. Vincular **responsável** (pai/mãe) na ficha do aluno
3. No bimestre, lançar **faltas** no evento da turma → [faltas-e-lista-de-chamada.md](./faltas-e-lista-de-chamada.md)
