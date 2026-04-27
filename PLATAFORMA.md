# EducaTudo — Ecossistema Educacional

O EducaTudo é uma plataforma educacional completa desenvolvida para escolas, colégios e instituições de ensino que desejam integrar tecnologia e inteligência artificial ao dia a dia da sala de aula. A plataforma funciona como um ecossistema onde alunos, professores, coordenadores, pais e administradores encontram ferramentas pensadas para cada um dos seus papéis.

A arquitetura é multi-tenant, o que significa que cada escola opera de forma independente, com seu próprio banco de dados, domínio personalizado, identidade visual e configurações. Uma escola pode ativar ou desativar módulos conforme sua necessidade, e toda a aparência — logos, cores, menus — pode ser customizada sem interferir nas demais.

---

## Para o Aluno

O aluno é o centro da plataforma. Ao acessar o EducaTudo, ele encontra um dashboard que resume seu progresso: exercícios realizados, redações escritas, interações com a IA, média de acertos, desempenho em jornadas e provas.

### Chat Tudinha

A Tudinha é a assistente de inteligência artificial da plataforma. O aluno pode conversar com ela em linguagem natural, tirar dúvidas sobre qualquer matéria, enviar imagens de cadernos ou livros (que são transcritas automaticamente via OCR) e até ouvir as respostas por voz. A Tudinha utiliza modelos da OpenAI e mantém o contexto da conversa, funcionando como uma tutora particular disponível a qualquer momento.

### Exercícios

A plataforma oferece dois tipos de exercícios. O primeiro é baseado em um banco de questões curado pela escola, com listas organizadas por matéria e assunto. O segundo é gerado sob demanda pela inteligência artificial: o aluno escolhe um tema, a quantidade desejada e o nível de dificuldade, e a IA cria exercícios personalizados na hora. Em ambos os casos, o aluno recebe feedback imediato com gabarito e explicações.

### Redação

O módulo de redação permite que o aluno escreva textos a partir de temas livres ou gerados por IA, e receba uma correção automática nos moldes do ENEM, com notas por competência e sugestões de melhoria. O aluno também pode fotografar uma redação escrita à mão e enviar a imagem, que é convertida em texto digital via OCR antes de ser corrigida. Além da redação livre, existe a Jornada da Redação, onde o professor cria propostas estruturadas com bancas, textos motivadores e critérios de correção específicos.

### Jornadas

As jornadas são trilhas de aprendizagem criadas pelos professores. Cada jornada é composta por módulos que podem conter vídeos, documentos, textos explicativos, exercícios, resumos e até redações. O aluno avança pela jornada no seu ritmo, concluindo cada etapa e acompanhando seu progresso. Ao final, pode consultar o gabarito dos exercícios e revisitar todo o conteúdo, mesmo após o prazo estipulado pelo professor.

### Provas Online

O aluno realiza provas criadas por seus professores diretamente na plataforma. As provas podem ser cronometradas, com modo seguro anti-cola que impede a navegação fora da página durante a avaliação. As respostas são salvas em tempo real para evitar perdas, e o resultado é apresentado conforme a configuração da prova — imediatamente ou após todos os alunos finalizarem.

### Flashcards

A IA gera decks de flashcards a partir de qualquer tema. O aluno estuda no formato pergunta e resposta, e quando não entende uma questão, pode pedir uma explicação detalhada gerada automaticamente.

### Outros Recursos

O aluno ainda conta com simulados no formato ENEM, um sistema de minicursos com aulas em vídeo, acesso a livros digitais via integração com Google Books, um caderno pessoal com editor e quadro branco, um drive para guardar arquivos, fórum de discussão com colegas, jogos educativos (incluindo xadrez e quiz com IA), o EducaLabs para criar projetos com auxílio de inteligência artificial, e prática de conversação em inglês com transcrição de áudio e chat com IA.

O aluno pode acessar o mural de recados da escola, conversar diretamente com professores pelo chat, visualizar planos de aula, apostilas e arquivos compartilhados, e abrir tickets de suporte quando precisar de ajuda.

### Carteira de Créditos

Algumas funcionalidades que utilizam inteligência artificial consomem créditos. O aluno pode acompanhar seu saldo, ver o histórico de movimentações e, quando habilitado pela escola, comprar créditos adicionais via PIX ou assinar planos recorrentes.

### Desempenho

O aluno tem acesso a painéis completos de desempenho com indicadores por matéria, taxa de acertos, comparativos entre jornadas e provas, e evolução ao longo do tempo.

---

## Para o Professor

O professor é o criador de conteúdo e o responsável por acompanhar o aprendizado dos seus alunos.

### Jornadas

O professor monta jornadas de aprendizagem do zero ou com auxílio de IA. Pode adicionar módulos com vídeos do YouTube, documentos, textos explicativos, exercícios (criados manualmente ou gerados por IA), redações e espaços para o aluno escrever resumos. Acompanha o progresso individual de cada aluno, responde dúvidas, corrige resumos com notas e revisa redações com correção automática por IA ou manual.

### Provas

O professor cria provas com questões de múltipla escolha, verdadeiro ou falso e dissertativas. Pode gerar questões automaticamente por IA a partir de um tema, importar questões por imagem e organizar a prova com diferentes pesos e níveis de dificuldade. Após a aplicação, acompanha os resultados detalhados e corrige questões dissertativas.

### Propostas de Redação

O professor configura propostas de redação com bancas específicas, textos motivadores, tipos textuais e critérios de correção. Pode gerar repertório argumentativo por IA e enviar as propostas para turmas ou alunos individualmente. A correção pode ser feita pela IA, pelo próprio professor ou por uma combinação dos dois.

### Exercícios

O professor pode criar e organizar listas de exercícios no banco de questões da escola, que ficam disponíveis para os alunos resolverem a qualquer momento.

### Outros Recursos

O professor conta com planos de aula (com exportação em PDF), gestão de arquivos e apostilas para compartilhar com turmas, mural de recados, chat direto com alunos, relatórios de desempenho por turma e por aluno, drive pessoal, geração de slides por IA via integração com Gamma, e o TudinhaProf — um criador de agentes de IA customizados onde o professor pode fazer upload de seus próprios materiais e criar um assistente especializado no conteúdo da disciplina.

---

## Para a Coordenação e Administração da Escola

O painel administrativo oferece controle total sobre a instituição dentro da plataforma.

### Gestão de Pessoas

Cadastro completo de alunos com importação por Excel ou CSV, transferência entre turmas, controle de status ativo e pagante, cadastro de pais e responsáveis, e registro de ocorrências disciplinares com transcrição de áudio por IA. Professores e administradores também são gerenciados com perfis de acesso diferenciados (diretor, coordenador, financeiro, desenvolvedor).

### Estrutura Acadêmica

A escola configura anos letivos, cursos, séries, turmas, matérias e grade horária. A importação da grade pode ser feita por imagem, com leitura automática por IA.

### Provas em Blocos

As provas podem ser organizadas em blocos de avaliação — agrupamentos que reúnem provas de diferentes matérias em um mesmo período, como "Avaliação Semanal" ou "Prova Bimestral". O coordenador acompanha resultados consolidados por bloco, com relatório de acertos detalhado (aluno × questão), exportação para Excel e impressão em paisagem. Pode também liberar gabarito para alunos e criar blocos modelo reutilizáveis.

### Monitoramento

O admin monitora alunos conectados em tempo real, tentativas de login, conteúdo sensível detectado pela IA, e tem acesso a dashboards de infraestrutura com métricas de uso de CPU, memória, banco de dados e consumo da API da OpenAI.

### Financeiro

Dashboard financeiro com relatório de alunos pagantes e gestão de mensalidades.

### Configurações

Através do perfil de desenvolvedor, o admin pode gerenciar módulos ativos, personalizar prompts de IA, configurar limites diários de uso, gerenciar chaves de API, configurar envio de e-mail SMTP, customizar o layout completo da plataforma, ativar o PWA para uso como aplicativo e configurar webhooks.

---

## Para os Pais e Responsáveis

Os pais acessam um painel dedicado onde podem acompanhar o desempenho acadêmico de seus filhos. Visualizam resultados de exercícios e provas, progresso nas jornadas, planos de aula, redações escritas e corrigidas, e relatórios de desempenho. Podem enviar e receber mensagens da escola e receber notificações. O acesso dos pais também está disponível via aplicativo mobile, com uma API REST completa autenticada por JWT.

---

## Painel Master (Super-Administrador)

O painel Master é a central de controle de toda a operação EducaTudo. É através dele que se gerencia o ecossistema multi-tenant.

### Gestão de Escolas

Cada escola é criada com seu próprio slug, domínio e banco de dados. O master pode configurar individualmente cada aspecto de uma escola: identidade visual (logos, cores, capa de login), módulos habilitados, chaves de API, configurações de e-mail, limites de uso (alunos, professores, tokens de IA por mês), sistema de créditos e planos, links úteis, aplicativos externos integrados e vídeos tutoriais.

### Acesso às Escolas

O master pode entrar como qualquer usuário de qualquer escola — seja admin, professor ou aluno — diretamente pelo painel, sem precisar de senha. O sistema gera um token HMAC assinado com validade de cinco minutos, que autentica a sessão na escola de destino. Esse acesso abre em uma nova aba, mantendo o painel master disponível.

### Tickets de Suporte

Todos os tickets de suporte abertos pelos alunos de todas as escolas são centralizados no dashboard do master. O administrador pode visualizar o histórico completo de mensagens de cada ticket e responder diretamente pelo painel master, sem precisar acessar o painel da escola.

### Infraestrutura

O master gerencia migrations de banco de dados (executar individualmente por escola, em lote ou apenas para escolas selecionadas), monitora conexões de banco de dados, configura a precificação global de créditos por módulo de IA, e administra os usuários do próprio painel master.

### Exportação e Importação

Toda a configuração de uma escola pode ser exportada e importada em formato JSON, facilitando a replicação de configurações entre escolas ou a criação de templates.

---

## Integrações

A plataforma se integra com diversos serviços externos:

- **OpenAI** — Chat (GPT-4o, GPT-4o-mini), geração de exercícios, correção de redações, OCR, transcrição de áudio (Whisper), texto para voz (TTS), geração de imagens (DALL-E) e monitoramento de conteúdo sensível.
- **Google Vision** — OCR de imagens como etapa inicial de transcrição.
- **Google Books** — Busca e visualização de livros digitais.
- **Gamma** — Geração de apresentações de slides por IA.
- **OneSignal** — Notificações push para navegadores e dispositivos móveis.
- **PIX** — Pagamento de créditos e assinaturas.
- **WebSocket** — Presença online em tempo real no painel master.
- **SSE (Server-Sent Events)** — Notificações em tempo real e monitoramento de alunos online.

---

## Tecnologias

A plataforma é construída com PHP no backend, seguindo o padrão MVC, com MySQL como banco de dados. O frontend utiliza Tailwind CSS para estilização e JavaScript vanilla para interatividade. A arquitetura multi-tenant é implementada com resolução de tenant por domínio e bancos de dados isolados por escola. O sistema suporta cache Redis, armazenamento de mídia local ou em S3, PWA para uso como aplicativo e API REST com autenticação JWT para o aplicativo dos pais.
