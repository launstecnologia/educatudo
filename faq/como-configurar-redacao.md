# Como configurar uma redação no EducaTudo

Guia para coordenação e equipe administrativa da escola.

---

## O que é a Redação Configurável?

É o módulo que permite criar atividades de redação personalizadas: definir o padrão de correção (como ENEM), montar o tema, escolher turmas, acompanhar envios e correções.

O trabalho se divide em **duas etapas**:

1. **Preparar a estrutura** — feita uma vez (ou quando mudar o padrão de correção)
2. **Criar cada proposta de redação** — feita a cada simulado, avaliação ou atividade

---

## Onde encontrar no painel Admin

| Menu | Para que serve |
|------|----------------|
| **Sistema → Configuração de Prompt** (Redação Configurável) | Cadastrar bancas, tipos de texto, critérios de nota e instruções de correção automática |
| **Redação do Professor → Listagem** | Criar e acompanhar propostas de redação |
| **Redação do Professor → Relatório** | Ver quem enviou, quem foi corrigido e as notas |

---

## Etapa 1 — Preparar a estrutura (fazer antes da primeira redação)

Acesse **Sistema → Configuração de Prompt**.

### 1.1 Cadastrar uma Banca

A banca representa o **modelo de avaliação** (ex.: ENEM, vestibular da escola).

1. Clique em **Nova Banca**
2. Preencha o **nome** (ex.: *ENEM*)
3. Marque **Banca ativa** para professores e alunos poderem usar
4. Salve

### 1.2 Cadastrar Tipos Textuais

Dentro da banca, clique em **Tipos** e depois em **Novo Tipo Textual**.

Exemplo: *Dissertativo-argumentativo*.

Cada tipo pode ter critérios e instruções de correção próprios.

### 1.3 Cadastrar Critérios de correção

No tipo textual, clique em **Critérios → Novo Critério**.

Para cada critério informe:

- **Nome** — ex.: *Competência I – Domínio da norma*
- **Pontuação máxima** — ex.: 200 (padrão ENEM)
- **Ordem** — define a sequência na tela de notas

Repita para todas as competências (no ENEM são 5, totalizando 1000 pontos).

### 1.4 Configurar instruções de correção automática (opcional)

Ainda no tipo textual, clique em **Prompts → Novo Prompt**.

Essas instruções orientam a **correção automática por inteligência artificial**. Só é necessário se o professor for usar essa opção na correção.

- Pode existir mais de uma versão; apenas **uma fica ativa** por vez
- Para trocar, use **Definir como ativo** na versão desejada

> **Importante:** Se a redação for enviada **somente em foto**, a correção automática não funciona — o professor corrige manualmente na imagem.

---

## Etapa 2 — Criar uma proposta de redação

Acesse **Redação do Professor → Listagem** e clique em **Nova proposta**.

### 2.1 Dados básicos

| Campo | O que preencher |
|-------|-----------------|
| **Banca** | Modelo de correção (ex.: ENEM) |
| **Tipo Textual** | Formato da redação |
| **Título do evento** | Nome visível para alunos — ex.: *Simulado de março* |
| **Professor responsável** | Quem criou/gerencia a atividade |
| **Professores com acesso para corrigir** | Outros professores que também podem corrigir (opcional) |

### 2.2 Definir o tema

Escolha uma das opções:

**Opção A — Configurar tema (mais comum)**

- **Tema da redação** — frase do tema
- **Contexto / Descrição** — textos de apoio, instruções, imagens
- **Repertório** — textos motivadores (pode adicionar vários)
- Use **Gerar com IA** para criar o contexto a partir do tema (opcional)

**Opção B — Tema pronto (PDF ou imagem)**

- Envie o arquivo com a proposta completa
- O aluno verá esse documento como referência

### 2.3 Como o aluno deve enviar a redação

| Opção | Quando usar |
|-------|-------------|
| **Digitar no editor** | Aluno escreve na plataforma; pode enviar foto e o sistema tenta ler o texto automaticamente |
| **Somente foto da redação manuscrita** | Aluno fotografa a folha; professor corrige rabiscando na imagem (ideal para tablet) |
| **Aluno escolhe** | O aluno decide entre digitar ou enviar foto |

### 2.4 Outras configurações

- **Exibir campo de título** — se o aluno deve dar um título à redação
- **Turmas** — quais turmas participam (obrigatório)
- **Alunos** — deixe em branco para todos da turma, ou selecione alunos específicos
- **Período** — data/hora de início e fim (em branco = sem limite de prazo)
- **Status:**
  - **Rascunho** — alunos não veem
  - **Publicada** — alunos já podem acessar

Clique em **Salvar**.

---

## O que acontece depois de publicar

### Para o aluno

1. Acessa **Jornada da Redação** no menu do aluno
2. Vê as propostas disponíveis
3. Lê o tema e materiais de apoio
4. Escreve ou envia a foto da redação
5. Após a correção, vê notas, comentários e imagem marcada (se aplicável)

### Para o professor

1. Acessa **Jornada da Redação** no painel do professor
2. Abre a proposta e vê a lista de alunos com status:
   - Não enviado
   - Visualizado
   - Enviado
   - Corrigido
3. Clica no aluno para **corrigir**
4. Pode:
   - Usar **correção automática** (quando há texto digitado)
   - **Rabiscar na foto** da redação (caneta ou dedo no tablet)
   - Destacar trechos e escrever comentários no texto
   - Gravar **áudio de feedback**
   - Ajustar notas por competência
5. Salva a correção — o aluno recebe a devolutiva

---

## Acompanhar resultados (coordenação)

### Na listagem da proposta

- Quantidade de alunos, envios e correções
- Filtro por nome e status

### No relatório

**Redação do Professor → Relatório**

- Visão geral de todas as propostas
- Quem enviou, quem foi corrigido, notas
- Acesso ao detalhe de cada envio

### Exportar dados

O professor pode **exportar para Excel** na tela da proposta.

---

## Permissões entre professores

Na tela da proposta (admin ou professor responsável):

- **Conceder acesso** a outros professores para corrigir
- **Remover acesso** quando não precisarem mais

Útil quando mais de um professor corrige a mesma turma.

---

## Perguntas frequentes

**Preciso configurar a banca toda vez?**  
Não. Configure uma vez. Depois só crie novas propostas escolhendo a banca já cadastrada.

**O aluno não vê a proposta. O que verificar?**  
- Status está como **Publicada**?  
- A turma do aluno foi selecionada?  
- O período já começou e ainda não terminou?  
- O aluno está na lista (se você restringiu alunos específicos)?

**Posso editar uma proposta depois de publicada?**  
Sim. Abra a proposta e clique em **Editar**. Alunos que já enviaram não perdem o envio.

**Qual modo de envio escolher?**  
- Prova digital na plataforma → **Digitar no editor**  
- Redação feita à mão no caderno → **Somente foto**  
- Flexibilidade para o aluno → **Aluno escolhe**

**A correção automática funciona com foto?**  
Não. Com foto, o professor corrige manualmente na imagem e define as notas.

**Como o professor corrige redação manuscrita?**  
Abre o envio do aluno, usa as ferramentas de desenho na foto (caneta, borracha, texto), salva os rabiscos e preenche as notas das competências.

**O que é a Coletânea?**  
Botão na tela da proposta que reúne tema, contexto, repertório e arquivos — material de consulta durante a correção.

**Posso enviar redações em nome dos alunos?**  
Sim. O professor pode usar **Enviar redações em bloco** ou **Transcrever** para um aluno específico.

---

## Checklist rápido

**Antes da primeira redação da escola:**
- [ ] Banca cadastrada e ativa
- [ ] Tipo textual criado
- [ ] Critérios de nota cadastrados
- [ ] Instruções de correção automática (se for usar)

**Para cada nova atividade:**
- [ ] Nova proposta criada
- [ ] Tema e materiais definidos
- [ ] Modo de envio escolhido
- [ ] Turmas selecionadas
- [ ] Período definido (se houver prazo)
- [ ] Status **Publicada**
- [ ] Professor(s) com acesso à correção definidos

---

## Resumo do fluxo

```
Coordenação prepara banca e critérios (uma vez)
        ↓
Coordenação ou professor cria a proposta
        ↓
Publica para as turmas escolhidas
        ↓
Aluno envia redação (texto ou foto)
        ↓
Professor corrige (manual, na foto ou com ajuda automática)
        ↓
Aluno vê nota e feedback na Jornada da Redação
        ↓
Coordenação acompanha no relatório
```
