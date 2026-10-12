# Mapa Geral dos Módulos — EducaTudo

> Este documento explica, em linguagem simples, tudo que existe na plataforma EducaTudo: o que cada módulo faz, quem usa, e como as coisas se conectam. Serve como ponto de partida para qualquer pessoa (técnica ou não) entender o sistema como um todo.

---

## Como a plataforma é organizada

O EducaTudo tem **6 perfis de acesso**, cada um com seu próprio painel:

| Perfil | Onde acessa | O que faz, resumindo |
|---|---|---|
| **Aluno** | `/` | Consome conteúdo, faz provas, escreve redações, brinca, tira dúvidas com IA |
| **Professor** | `/professor` | Cria jornadas, provas, corrige redações, acompanha turmas |
| **Admin (escola)** | `/admin` | Gestão completa da escola: alunos, professores, financeiro, configurações |
| **Monitor** | `/monitor` | Supervisão de sala em tempo real, só leitura (não vê notas nem financeiro) |
| **Pais** | `/pais` ou app celular | Acompanha o desempenho do(s) filho(s), só leitura |
| **Master** (equipe EducaTudo) | `/master` | Painel para gerenciar todas as escolas clientes, financeiro da plataforma, etc. |

Cada escola é **isolada das outras** — os dados de uma escola nunca se misturam com os de outra, mesmo estando no mesmo sistema.

---

## 1. Módulos do Aluno

### 1.1 Jornada do Aluno
A "jornada" é uma trilha de estudo organizada pelo professor, dividida em **módulos** sequenciais. Cada módulo pode ser:
- Um **vídeo ou conteúdo** (texto, documento, vídeo)
- Um **exercício** (questões de múltipla escolha, corrigidas automaticamente)
- Um **resumo do aluno** (o aluno escreve o que aprendeu, professor ou IA dá feedback)
- Uma **dica do professor**

O aluno avança módulo por módulo, e só vê o gabarito depois de terminar tudo. O sistema acompanha o progresso (% concluído, taxa de acerto, tempo gasto). Tem também o recurso **"Tudinha explica"**, onde o aluno pede pra IA explicar uma questão específica.

### 1.2 Redação (três formatos)
Existem três jeitos de trabalhar redação na plataforma:

| Formato | Como funciona |
|---|---|
| **Redação Configurável** | Professor cria um tema com critérios de correção (um "quadro" de redação). Aluno escreve (digitado ou foto de redação manuscrita, que o sistema lê via OCR). IA corrige automaticamente, dando nota por critério e feedback detalhado. |
| **Redação Livre** | Professor sobe a redação de um aluno (já escrita no papel) direto, sem precisar de um tema pré-cadastrado, e pede a correção por IA. |
| **Redação na Jornada** | A redação é só mais um módulo dentro de uma jornada. |

Depois da correção, o aluno pode receber até **feedback em áudio** (voz sintetizada) do professor, e pode reescrever e reenviar pra nova análise.

### 1.3 Provas
Uma "prova" é um teste digital de uma matéria; várias provas (de matérias diferentes) podem ser agrupadas num **"bloco de prova"** (ex.: simulado com Matemática + Português + História, todos no mesmo período).

As questões podem ser criadas manualmente pelo professor, **geradas por IA**, ou importadas de um **banco de questões** pronto. Tipos de questão: múltipla escolha, verdadeiro/falso, resposta curta.

Tem um recurso de **acessibilidade chamado EducaInclui** (ver seção 6) que adapta provas pra alunos com laudo médico/pedagógico (TDAH, dislexia, etc.) — fonte maior, mais tempo, menos alternativas, leitura em voz alta, e até reescrita da questão em linguagem mais simples.

### 1.4 Apostilas (duas versões)
- **Apostilas (simples)**: o professor/admin sobe um PDF ou imagem, o aluno só visualiza. Sem IA.
- **IA da Apostila**: módulo novo, com IA que lê a apostila inteira e permite chat, geração de provas e slides — [já documentado em detalhe aqui](IA_DA_APOSTILA_GUIA_DO_USUARIO.md).

### 1.5 Minicursos
Cursos curtos com módulos e aulas (vídeo, slide, PDF, link ou texto). O aluno marca aulas como vistas e acompanha o progresso.

### 1.6 Jogos
- **Jogos (geral)**: portal de jogos educativos, hospedado fora da plataforma principal, acessado com login único.
- **Tudinha do Milhão**: jogo de perguntas e respostas estilo "Show do Milhão", com 15 níveis de prêmio. *Atualmente desativado para alunos* (decisão da escola, configurável).

### 1.7 Exercícios (avulsos)
Listas de exercícios fora de uma jornada, com **24 horas de espera** antes de poder refazer a mesma lista. Podem ser gerados por IA sob demanda.

### 1.8 Meu Caderno
Caderno digital pessoal do aluno, num app separado, acessado com login único a partir da plataforma principal.

### 1.9 Fórum
Espaço de perguntas e respostas entre alunos (e professores). Tem moderação automática por IA (bloqueia conteúdo inadequado), sistema de votos e "melhor resposta".

### 1.10 Drive
Armazenamento pessoal de arquivos (como um Google Drive simplificado), com pastas, upload e compartilhamento entre alunos/professores.

### 1.11 Flashcards
Cartões de estudo (pergunta/resposta) gerados por IA — a partir de um tema digitado, ou de fotos de páginas (a IA lê a imagem e cria os cartões).

### 1.12 EducaLabs, Educa Livros e Inglês
- **EducaLabs**: laboratório de experimentos/programação interativa, app externo com login único.
- **Educa Livros**: busca de livros (catálogo do Google Books) para pesquisa e leitura.
- **Inglês**: conversação em inglês com IA (fala ou texto), com histórico de conversas.

### 1.13 EducaHits
Pedido de músicas educativas personalizadas (a IA compõe uma música sobre o tema que o aluno escolher), ou acesso a um portal de conteúdo musical externo.

### 1.14 Chat com a Tudinha
**Tudinha** é a tutora de IA da plataforma. Aluno (ou professor) conversa com ela pra tirar dúvidas sobre qualquer conteúdo, pedir explicação de exercícios, entender uma redação corrigida, etc. As conversas ficam salvas e podem ser retomadas.

> Mensagens com sinais de risco (menção a automutilação, abuso, etc.) são detectadas automaticamente e geram um alerta para a coordenação da escola — é um recurso de segurança, não de vigilância geral.

### 1.15 Suporte (Tickets)
O aluno pode abrir um chamado de ajuda (dúvida, problema técnico, etc.), anexar arquivos, e conversar com o administrador da escola até o chamado ser resolvido.

---

## 2. Módulos do Professor

Além de criar/gerenciar jornadas, provas e redações (já descritos acima do ponto de vista do aluno), o professor tem:

- **Planos de aula**: criação de planos com objetivo, conteúdo, atividades e materiais, vinculados a jornadas.
- **Mural de Recados**: avisos para as turmas que leciona.
- **Drive próprio**: pasta de materiais, pode compartilhar com alunos.
- **Notificações para a turma**: avisos pontuais.
- **Gerar Slides com IA**: monta apresentações de aula automaticamente (via Gamma), inclusive a partir do conteúdo de uma apostila processada por IA.
- **Colar JSON** (recurso novo): permite copiar exercícios prontos (gerados pela IA da Apostila, por exemplo) e colar direto na criação de uma prova ou de um exercício de jornada, sem digitar tudo de novo.

---

## 3. Módulos do Admin (gestão da escola)

O painel de Admin foi dividido em áreas especializadas:

| Área | O que cobre |
|---|---|
| **Alunos** | Cadastro, dashboard com estatísticas, análise de uso da Tudinha, alertas de comportamento sensível |
| **Professores** | Cadastro, ativação/inativação, atribuição de turmas e matérias, controle de quem é "pagante" |
| **Financeiro da escola** | Valores a receber (por aluno/professor pagante), faturas, integração com o gateway de pagamento Asaas |
| **Catálogo de exercícios** | Banco de questões reutilizável, importação em lote |
| **Relatórios** | Uso da Tudinha, histórico de conversas, consolidação de uso de IA |
| **Ocorrências / Alertas sensíveis** | Painel dos alertas de risco detectados automaticamente nas conversas dos alunos com a IA |
| **Monitores** | Cadastro de quem supervisiona salas (perfil Monitor) |
| **Configurações (Dev)** | E-mail (SMTP), prompts personalizados da IA, variáveis avançadas do sistema |
| **Configurações gerais** | Turmas, matérias, usuários admin, aparência/tema da escola |

### Estrutura escolar
- **Ano letivo**: períodos, feriados, recesso.
- **Curso → Série → Turma**: a hierarquia que organiza o ano escolar (ex.: Ensino Médio → 1º Ano → Turma A).
- **Grade horária**: horários de aula por turma/professor/matéria/sala — pode até ser criada a partir de uma foto da grade (a IA lê e organiza).

### Mural, Webhooks e Monitoramento
- **Mural de Recados**: o admin pode ver e publicar avisos para qualquer turma da escola.
- **Webhooks**: conexões automáticas com serviços externos — por exemplo, quando uma aula online grava o vídeo, ou quando um pagamento é confirmado no Asaas, o sistema é avisado automaticamente.
- **Painel de Monitoramento**: tela em tempo real só para o admin, mostrando saúde do sistema (uso de IA, banco de dados, usuários online) — é uma ferramenta técnica/operacional.

---

## 4. Painel Master (equipe EducaTudo)

Esse painel não é usado pelas escolas — é o painel da equipe que opera a plataforma como um todo (modelo "SaaS": Software as a Service).

- **Gestão de Escolas**: cadastra novas escolas (cada uma com seu próprio banco de dados), liga/desliga módulos por escola, configura cores e identidade visual, gerencia chaves de integrações (e-mail, pagamento, IA) por escola.
- **Financeiro consolidado**: visão de receita de todas as escolas, status de pagamento.
- **Migrações**: aplica atualizações de banco de dados em todas as escolas de uma vez (ou uma por uma).
- **Precificação**: define quanto cada funcionalidade de IA custa em "créditos" (ver seção 6).

---

## 5. Pais

Pais/responsáveis têm acesso só de leitura ao desempenho do(s) filho(s):

- Notas e boletim
- Jornadas em andamento e progresso
- Provas feitas e notas
- Redações entregues e correções
- Planos de aula da turma do filho
- Comunicação com o professor

Existe também um **aplicativo de celular** para os pais, usando o mesmo sistema por trás (uma "porta" diferente de entrada para os mesmos dados).

---

## 6. Como as coisas funcionam por trás (conceitos transversais)

Estas três coisas não são "módulos" que você abre numa tela — são mecanismos que sustentam várias funcionalidades ao mesmo tempo.

### 6.1 Fila de Inteligência Artificial (processamento em segundo plano)
Sempre que uma ação pede pra IA fazer algo que demora (corrigir uma redação, gerar exercícios, processar uma apostila inteira), o sistema **não deixa a tela travada esperando**. Em vez disso:

1. A tarefa entra numa fila.
2. Você vê um indicador de "processando..."
3. Quando terminar, o resultado aparece — sem precisar recarregar a página.

Isso é o que permite, por exemplo, processar uma apostila de 600 páginas sem travar o sistema.

### 6.2 Créditos
Algumas funcionalidades de IA (gerar slides, corrigir redação, conversar com a Tudinha em alguns contextos, gerar exercícios, etc.) **consomem créditos**. Cada escola tem um saldo, que pode vir de:
- Um valor gratuito mensal (recarrega todo mês)
- Créditos comprados (não expiram)

Se a escola ficar sem créditos, aquela funcionalidade específica fica bloqueada até a próxima recarga ou compra — o resto da plataforma continua funcionando normalmente. Se uma tarefa de IA falhar por erro do sistema, o crédito é devolvido automaticamente.

### 6.3 EducaInclui (acessibilidade e inclusão)
Sistema de apoio a alunos com laudo médico/pedagógico (TDAH, dislexia, autismo, baixa visão, etc.). A coordenação cadastra as adaptações necessárias para cada aluno, que podem ser:

- **Simples** (não muda o conteúdo da prova): tempo extra, fonte maior, leitura em voz alta, esconder cronômetro, tema de alto contraste.
- **Significativas** (muda o conteúdo): menos alternativas por questão, menos questões no total (com nota recalculada proporcionalmente), ou até a IA reescrevendo o enunciado em linguagem mais simples e literal.

Adaptações "significativas" exigem **aprovação humana** antes de valerem — a IA pode sugerir, mas uma pessoa da coordenação sempre revisa e aprova antes do aluno ver a prova adaptada. Os laudos médicos ficam **criptografados** e só pessoas autorizadas (coordenação/AEE) têm acesso.

---

## Resumo visual

```
                         ┌─────────────┐
                         │   MASTER    │  (equipe EducaTudo — gerencia todas as escolas)
                         └──────┬──────┘
                                │
                  ┌─────────────┴─────────────┐
                  │      Escola (isolada)     │
                  └─────────────┬─────────────┘
                                │
        ┌───────────┬──────────┼──────────┬───────────┐
        ▼           ▼          ▼          ▼           ▼
     ALUNO     PROFESSOR     ADMIN      MONITOR       PAIS
        │           │          │           │           │
   Jornadas    Cria jornadas  Gestão    Supervisão   Acompanha
   Provas      Provas         completa  em tempo     desempenho
   Redação     Redação        da        real         do(s)
   Jogos       Planos de aula escola    (só leitura) filho(s)
   Tudinha     Mural                                 (só leitura)
   Drive       Drive
   Fórum       Notificações
   Flashcards  Slides (IA)
   ...         Colar JSON

        Por trás de tudo: fila de IA assíncrona + sistema de créditos + EducaInclui
```

---

## Documentos relacionados

- [Guia do Usuário — IA da Apostila](IA_DA_APOSTILA_GUIA_DO_USUARIO.md)
- `EDUCAINCLUI_PROVAS_ADAPTATIVAS.md` e `EDUCAINCLUI_MASCARA_GUIA.md` — detalhes do módulo de inclusão
- `API_PAIS_ROTAS_E_CAMPOS.md` — detalhes técnicos da API do app de pais
