# Como usar o Painel do Monitor de Sala

Guia para quem **acessa o portal do monitor** durante provas, simulados ou jornadas — em geral o monitor de sala ou a coordenação presente no evento.

Para cadastrar o acesso, veja [como-cadastrar-monitor.md](./como-cadastrar-monitor.md).

---

## Acesso ao painel

| Item | Valor |
|------|-------|
| **URL** | `https://[sua-escola].educatudo.com/monitor` |
| **Login** | Email cadastrado pela coordenação |
| **Senha** | Definida no primeiro acesso (senha inicial: `123456`) |

### Primeiro login

1. Entre com email e senha `123456`
2. O sistema pede **nova senha** (obrigatório)
3. Depois você é direcionado ao **painel principal**

---

## Visão geral — Dashboard

Após o login, você vê **Alunos online agora** (`/monitor/dashboard`).

### Contadores no topo

| Indicador | Significado |
|-----------|-------------|
| **Online agora** | Alunos conectados nas suas turmas |
| **Provas canceladas** | Alunos que saíram do modo seguro (prova interrompida) |
| **Em prova agora** | Alunos realizando prova neste momento |

### Filtro por evento / bloco

Se houver provas agendadas em blocos, use o seletor **Filtrar por evento / bloco de prova** para focar só nos alunos daquele simulado.

### Cards dos alunos

Cada card mostra:

- Nome e **RA**
- **Turma**
- O que o aluno está fazendo (prova, jornada, navegação)
- **Tempo online**
- Destaque **vermelho** se a prova foi **cancelada** (modo seguro)

Clique no card para abrir o **detalhe do aluno**.

> A lista atualiza **em tempo real** (não é necessário atualizar a página).

---

## Detalhe do aluno

Na ficha do aluno você encontra:

### Status agora

- **Online** com descrição do contexto (ex.: nome da prova ou jornada)
- **Offline** se não houver sessão ativa

### Provas / simulados

Lista provas **em andamento** ou **canceladas**:

| Status | O que significa |
|--------|-----------------|
| **Em andamento** | Aluno está fazendo a prova |
| **Cancelada (modo seguro)** | Aluno saiu do modo seguro; a prova foi interrompida — coordenação precisa liberar nova tentativa |

Clique em **Ver respostas** para ver as questões e o que o aluno já respondeu (durante a prova, só respostas já salvas).

### Jornadas

Cada jornada exibe um selo de engajamento:

| Selo | Significado |
|------|-------------|
| **Realizou exercícios** | Aluno enviou ao menos uma resposta |
| **Abriu a jornada, mas ainda não respondeu** | Entrou na atividade sem enviar respostas |
| **Ainda não abriu** | Sem registro de acesso |

Clique em **Ver detalhes** para abrir exercício por exercício: enunciado, imagem, alternativas, acerto/erro e resposta do aluno.

---

## Resetar senha do aluno

Use quando o aluno esqueceu a senha ou não consegue entrar.

1. Abra o **detalhe do aluno**
2. Em **Ações rápidas**, clique em **Resetar senha para 123456**
3. Confirme no modal digitando **sua senha de monitor**
4. Clique em **Confirmar reset**

A senha do aluno passa a ser **`123456`**. Oriente o aluno a trocar depois, se a escola exigir.

> Por segurança, o reset **não funciona** sem a senha correta do monitor logado.

---

## Alertas importantes

### Prova cancelada (modo seguro)

Quando o aluno força saída do modo seguro (troca de app, botão voltar, etc.):

- O card fica **vermelho** no dashboard
- No detalhe do aluno aparece aviso de **prova cancelada**
- O monitor **não libera** nova tentativa — acione a **coordenação** no Admin

### Aluno “online” mas sem respostas na jornada

Pode ser normal: o aluno abriu a jornada mas ainda não enviou exercícios. Confira o selo **Abriu a jornada, mas ainda não respondeu** no detalhe da jornada.

---

## O que o monitor **não** faz

- Não cadastra outros monitores (isso é no Admin)
- Não libera prova cancelada
- Não altera notas ou gabaritos
- Não vê alunos de turmas fora do seu vínculo

---

## Checklist no dia da prova

- [ ] Login em `/monitor` com senha pessoal (não a padrão)
- [ ] Filtro de **bloco/evento** selecionado, se aplicável
- [ ] Dashboard aberto para acompanhar cards em tempo real
- [ ] Saber identificar card **vermelho** (prova cancelada)
- [ ] Saber resetar senha do aluno com **confirmação da sua senha**

---

## Resumo do fluxo

```
Coordenação cadastra monitor e turmas (Admin)
        ↓
Monitor faz login em /monitor
        ↓
Dashboard: alunos online, alertas, filtro por bloco
        ↓
Clica no aluno → provas, jornadas, reset de senha
        ↓
Se necessário: coordenação libera nova tentativa de prova no Admin
```
