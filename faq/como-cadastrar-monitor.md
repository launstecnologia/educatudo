# Como cadastrar o Monitor de Sala

Guia para **coordenação e equipe administrativa** configurar quem acompanha os alunos online durante provas e jornadas.

Para saber como usar o painel depois do cadastro, veja [como-usar-painel-monitor.md](./como-usar-painel-monitor.md).

---

## Para que serve

O **Monitor de Sala** é um perfil separado do aluno e do professor. Ele permite:

- Ver **quem está online** nas turmas vinculadas
- Acompanhar **provas em andamento** e alertas de **prova cancelada** (modo seguro)
- Abrir o **detalhe do aluno**: jornadas, respostas e provas
- **Resetar a senha** do aluno para o padrão `123456` (com confirmação da senha do monitor)

O monitor **só enxerga alunos das turmas** que a coordenação selecionar no cadastro.

---

## Onde cadastrar no painel Admin

| Menu | Caminho |
|------|---------|
| **Usuários → Monitores** | `/admin/monitors` |

> Requer acesso de administrador/coordenação com permissão ao menu de usuários.

---

## Passo a passo — novo monitor

1. Acesse o painel **Admin**
2. No menu lateral, abra **Usuários** e clique em **Monitores**
3. Clique em **Novo Monitor**
4. Preencha:
   - **Nome** — ex.: *Maria Silva — Monitor 3º ano*
   - **Email** — será o login no portal do monitor
   - **Turmas** — marque **ao menos uma** turma que essa pessoa pode acompanhar
   - **Ativo** — deixe marcado para permitir login
5. Clique em **Salvar**

### Senha inicial

- Todo monitor novo recebe a senha padrão **`123456`**
- No **primeiro login**, o sistema obriga a troca dessa senha
- A nova senha deve seguir as regras de senha forte da plataforma (não pode ser `123456`)

---

## Editar ou desativar um monitor

Na listagem **Monitores de Sala** (`/admin/monitors`):

| Ação | Quando usar |
|------|-------------|
| **Editar** | Alterar nome, email, turmas ou senha |
| **Inativo** | Bloquear login sem excluir o histórico |
| **Excluir** | Remover o cadastro (ação irreversível) |

Ao editar, o campo **senha** é opcional: deixe em branco para manter a senha atual.

---

## Informar o acesso ao monitor

Depois de cadastrar, passe ao monitor:

| Item | Valor |
|------|-------|
| **URL de login** | `https://[sua-escola].educatudo.com/monitor` |
| **Usuário** | Email cadastrado |
| **Senha inicial** | `123456` (trocar no primeiro acesso) |

A URL exata também aparece no rodapé da tela de listagem de monitores no Admin.

---

## Checklist rápido

- [ ] Turmas corretas selecionadas (só essas aparecerão no painel)
- [ ] Email único e válido
- [ ] Monitor marcado como **Ativo**
- [ ] Pessoa orientada a trocar a senha no primeiro login
- [ ] Link `/monitor` compartilhado com a equipe da sala

---

## Perguntas frequentes

**O monitor vê alunos de todas as turmas da escola?**  
Não. Apenas das turmas marcadas no cadastro.

**Posso cadastrar o mesmo email em dois monitores?**  
Não. O email precisa ser único.

**A coordenação precisa de um monitor separado?**  
Sim. O painel Admin serve para **cadastrar**; o acompanhamento em tempo real é feito em `/monitor`, com login de monitor.

**O monitor pode alterar notas ou corrigir provas?**  
Não. Ele acompanha presença, contexto (prova/jornada) e respostas já salvas. Correção e liberação de prova cancelada ficam com coordenação/professor no Admin.
