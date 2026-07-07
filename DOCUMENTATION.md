# EducaTudo - Documentação Técnica

## 1. Visão Geral do Projeto

### Objetivo da Aplicação

EducaTudo é uma plataforma educacional SaaS (Software as a Service) desenvolvida em PHP, projetada para gerenciar o ambiente educacional completo de uma ou múltiplas instituições de ensino. A plataforma oferece funcionalidades para alunos, professores, coordenadores e pais/responsáveis.

### Tipo de Aplicação

- **Plataforma Educacional**: Suporta **single-tenant** (uma instância por instituição) ou **multi-tenant** (várias escolas na mesma instalação, quando `MULTI_TENANT=true` no `.env`).
- **Sistema Web**: Aplicação PHP server-side com interface responsiva.
- **Arquitetura MVC**: Model-View-Controller para separação de responsabilidades.
- **Painel Master** (multi-tenant): Gestão de escolas, migrações, detalhes por escola (usuários, professores, módulos, layout, API keys, limites, vídeos, banco).

### Stack Tecnológico

- **Backend**: PHP (nativo, sem framework)
- **Arquitetura**: MVC customizado
- **Banco de Dados**: MySQL/MariaDB (por tenant em multi-tenant; banco master para escolas)
- **Frontend**: HTML, CSS (Tailwind CSS), JavaScript
- **Autenticação**: Sessões PHP (web); JWT para API REST (pais)
- **Integrações**: Google Books API, OpenAI API, Gamma Slides API, ElevenLabs, OneSignal (push), Gamma Slides
- **Mídia**: Armazenamento local ou AWS S3 (`MEDIA_STORAGE`, `DRIVE_STORAGE`)
- **Métricas e monitoramento**: `MetricsService`, logs estruturados por escola/usuário
- **Gerenciamento de Dependências**: Composer

### Funcionalidades Principais

- Gestão de alunos, professores e turmas
- Sistema de provas online e blocos de provas
- Jornadas de aprendizado (módulos educacionais) — admin e professor
- Exercícios personalizados e simulados
- Chat educacional com IA (Tudinha)
- Sistema de redações (essays) e correção por IA
- Notificações, push (OneSignal) e comunicação
- Relatórios e análises de desempenho
- Jogos educacionais (Jogo do Milhão)
- Fórum, Drive, Caderno, Apostilas, Minicursos
- EducaLabs, Apps externos, Notes (acesso por token)
- API REST para pais (JWT)
- PWA (manifests por perfil)
- Recuperação de senha (aluno, professor), primeiro acesso, termos e políticas

---

## 2. Arquitetura MVC

### Conceito MVC

O projeto segue o padrão arquitetural Model-View-Controller (MVC), que separa a aplicação em três camadas principais:

#### Model (Modelo)

- **Responsabilidade**: Representa as entidades do domínio e lógica de acesso a dados
- **Localização**: `app/Models/`
- **Função**: 
  - Interage com o banco de dados
  - Define estrutura de dados das entidades
  - Contém métodos para CRUD (Create, Read, Update, Delete)
  - Gerencia relacionamentos entre entidades
- **Exemplo**: `Models/Exams/Exam.php` representa a entidade "Prova" no sistema

#### View (Visão)

- **Responsabilidade**: Apresentação dos dados ao usuário
- **Localização**: `app/Views/`
- **Função**:
  - Renderiza HTML, CSS e JavaScript
  - Exibe formulários e dados
  - Recebe dados do Controller para exibição
  - Não contém lógica de negócio
- **Exemplo**: `Views/student/exams/index.php` exibe a lista de provas para o aluno

#### Controller (Controlador)

- **Responsabilidade**: Orquestra a comunicação entre Model e View
- **Localização**: `app/Controllers/`
- **Função**:
  - Recebe requisições HTTP (GET, POST, PUT, DELETE)
  - Valida dados de entrada
  - Chama métodos do Model para processar dados
  - Decide qual View renderizar ou retorna JSON
  - Gerencia fluxo da aplicação
- **Exemplo**: `Controllers/Exams/ExamController.php` gerencia todas as ações relacionadas a provas

### Fluxo de Comunicação MVC

```
1. Requisição HTTP → Router
2. Router → Controller (método específico)
3. Controller → Model (busca/processa dados)
4. Model → Database (consulta/atualiza)
5. Database → Model (retorna dados)
6. Model → Controller (retorna resultado)
7. Controller → View (passa dados)
8. View → HTML renderizado → Cliente
```

### Exemplo Prático

**Cenário**: Aluno acessa `/aluno/provas`

1. **Router** (`config/routes.php`): Mapeia `/aluno/provas` para `Exams/ExamController@indexAluno`
2. **Controller** (`ExamController@indexAluno`): 
   - Instancia `Exam` model
   - Chama `$examModel->getProvasAluno($alunoId)`
3. **Model** (`Exam.php`): Executa query SQL e retorna array de provas
4. **Controller**: Recebe dados e chama `$this->viewWithLayout('student', 'student/exams/index', $data)`
5. **View** (`student/exams/index.php`): Renderiza HTML com lista de provas
6. **Response**: HTML enviado ao navegador

---

## 3. Estrutura de Pastas do Projeto

```
src/
├── app/
│   ├── Controllers/              # Por domínio: Admin, Api, Apostilas, Auth, Drive, Education, Essays,
│   │                             # Exams, Exercises, ExternalApps, EducaLabs, Files, Forum, Games,
│   │                             # Integrations, Master, Media, Minicursos, Notes, Noticias,
│   │                             # Notifications, Pwa, Study, Support, Teacher, User; MonitoramentoController (raiz)
│   ├── Core/                     # App, Router, Database, DatabaseManager, Auth, AuthManager, BaseController,
│   │                             # Logger, FeatureGate, JornadaStatusHelper, LayoutHelper, MigrationRunner,
│   │                             # MysqlProvisioningService, TenantResolver, WebhookManager,
│   │                             # bootstrap_multi_tenant.php, cron_multi_tenant_helper.php
│   ├── Middleware/               # AuthMiddleware, ApiAuthMiddleware, AuditMiddleware, GameSecurityMiddleware, PasswordCheckMiddleware
│   ├── Models/                  # User, Education, Exams, Notifications, Drive, Essays, Forum, Study,
│   │                             # PushNotifications, System; Noticia, NotificacaoApi (raiz)
│   ├── Services/                 # IA (OpenAI, Tudinha, RAG, Embedding, EssayAI), OneSignal, JWT, Email,
│   │                             # MediaStorage, DriveStorage, Metrics, Backup, DocumentProcessor, etc.
│   ├── Utils/                    # ChatFormatter, HtmlSanitizer
│   ├── Helpers/                  # MarkdownHelper
│   ├── Views/                    # admin, teacher, student, parents, auth, layouts, master, legal, components, notifications
│   ├── public/                  # uploads (mural, provas, jornadas, etc.)
│   └── storage/                  # chat, drive, logs
├── config/                       # app.php, routes.php, monitoring.php (opcional)
├── database/                     # migrations/ (educatudo.sql, multi_tenant_master.sql, 003–006_*.sql), analise/
├── public/                       # service-worker.js, robots.txt, static/, uploads
├── storage/                      # logs, uploads, chat, drive
├── vendor/
├── index.php                     # Ponto de entrada (bootstrap multi-tenant, App)
├── health.php                    # Health check
└── composer.json
```

### Descrição das Pastas Principais

#### app/Controllers/

Controllers organizados por domínio. Inclui **Master/** (painel multi-tenant: escolas, detalhes, migrações), **Api/** (REST pais, JWT, Swagger, OneSignal, push tracking), **Media/**, **Pwa/**, **EducaLabs/**, **ExternalApps/**, **Notes/**, **Noticias/**, **Apostilas/**, **Minicursos/**, **Forum/**, **Files/**, **Study/** (Flashcards), além de Auth, User, Teacher, Education, Exams, Exercises, Games, Integrations, Notifications, Support.

#### app/Models/

Organizados por domínio: User, Education, Exams, Notifications, **Drive**, **Essays**, **Forum**, **Study**, **PushNotifications**, **System**; na raiz: **Noticia**, **NotificacaoApi**.

#### app/Views/

Por papel (admin, teacher, student, parents), **master/** (painel master), **legal/**, **layouts/** e **components/**.

#### app/Core/

Classes fundamentais e **multi-tenant**: **TenantResolver**, **LayoutHelper**, **MigrationRunner**, **MysqlProvisioningService**, **FeatureGate**, **DatabaseManager**, **JornadaStatusHelper**, **WebhookManager**, **bootstrap_multi_tenant.php**, **cron_multi_tenant_helper.php**, além de App, Router, Database, Auth, BaseController, Logger.

#### config/

**app.php**: carrega `.env`, configurações de tenant, school, media, aws, drive, ai, email, session, security. **routes.php**: rotas master, públicas, API, auth, protegidas por perfil.

#### database/migrations/

Scripts SQL: dump principal, **multi_tenant_master.sql**, migrações numeradas (ex.: limites/vídeos master, tokens apps, apostilas, tipos de curso).

---

## 4. Controllers

### 4.1 Organização dos Controllers

Os controllers estão organizados em subpastas por **domínio de responsabilidade**, não por papel do usuário. Isso permite melhor escalabilidade e manutenção.

**Estrutura de Organização:**

```
Controllers/
├── Admin/                   # Mural, DevSettings
├── Api/                     # Auth (JWT), Parent (REST), Swagger, OneSignalTags, PushTracking
├── Apostilas/               # Admin, Teacher, Student
├── Auth/                    # Autenticação (login, logout, recuperação, primeiro acesso, termos)
├── Drive/                   # Drive (pastas, upload, compartilhamento)
├── Education/               # Jornadas, turmas, planos de aula, grade, catálogo, servir imagens
├── Essays/                  # Redações (Admin, Teacher, Student)
├── Exams/                   # Provas, blocos, modelo, simulados, servir imagens
├── Exercises/               # Exercícios padrão e personalizados
├── ExternalApps/            # Apps externos (abrir, validar token)
├── EducaLabs/               # Projetos EducaLabs, preview, token
├── Files/                   # Arquivos (Student, Teacher)
├── Forum/                   # Fórum e ForumModeration
├── Games/                   # GameController, MillionGameController
├── Integrations/            # Chat, English, GoogleBooks, Slides, Webhook
├── Master/                  # Painel master: Auth, Escolas, EscolaDetail, Migrations
├── Media/                   # MediaServeController (servir mídia)
├── Minicursos/              # Admin, Student
├── Notes/                   # NotesController, NotesTokenController
├── Noticias/                # NoticiasController, NotificacoesApiController
├── Notifications/           # Notification, UserNotification, PushNotification
├── Pwa/                     # Manifests por perfil
├── Study/                   # FlashcardController
├── Support/                 # SupportTicketController
├── Teacher/                 # TeacherAI, TeacherJourney, TeacherExam, TeacherMural, TeacherNotification
├── User/                    # Student, Teacher, Admin, Parent, User, Avatar, Caderno, Onboarding
└── MonitoramentoController.php  # Raiz
```

**Padrões de Nomenclatura:**

- Todos os nomes em **inglês**
- **PascalCase** com sufixo `Controller.php`
- Nomes no **singular** (ex: `ExamController`, não `ExamsController`)
- Evitar nomes longos desnecessários
- Separar por responsabilidade usando subpastas

**Exemplo de Nomenclatura:**

- ✅ `ExamController.php` (singular, claro)
- ✅ `TeacherExamController.php` (específico para professores)
- ❌ `ExamsController.php` (plural)
- ❌ `ProvaController.php` (português)

### 4.2 Lista Completa de Controllers

#### Auth/

**AuthController.php**
- **Responsabilidade**: Gerencia autenticação, login, logout e recuperação de senha
- **Funcionalidades**:
  - Login para diferentes perfis (aluno, professor, admin, pais)
  - Autenticação de usuários
  - Logout
  - Recuperação de senha
  - Redirecionamento baseado em perfil
- **Métodos principais**: `loginAluno()`, `loginAdmin()`, `loginProfessor()`, `loginPais()`, `autenticar()`, `logout()`, `recuperarSenha()`

#### User/

**StudentController.php**
- **Responsabilidade**: Gerencia todas as ações e funcionalidades do aluno
- **Funcionalidades**:
  - Dashboard do aluno
  - Chat com IA (Tudinha)
  - Gerenciamento de redações
  - Visualização de jornadas
  - Alteração de senha obrigatória
  - Upload de imagens para transcrição
- **Métodos principais**: `dashboard()`, `chat()`, `createConversation()`, `sendMessage()`, `essays()`, `escreverRedacao()`, `corrigirRedacao()`

**TeacherController.php**
- **Responsabilidade**: Gerencia o painel e ações gerais do professor
- **Funcionalidades**:
  - Dashboard do professor
  - Visualização de alunos
  - Gerenciamento de provas do aluno
  - Relatórios de alunos
  - Upload de foto de perfil
  - Gerenciamento de turmas
- **Métodos principais**: `dashboard()`, `student()`, `viewStudent()`, `studentProvas()`, `studentRelatorio()`, `gerarSlides()`

**AdminController.php**
- **Responsabilidade**: Gerencia todas as funcionalidades administrativas
- **Funcionalidades**:
  - Dashboard administrativo
  - CRUD de alunos, professores, turmas, usuários
  - Gerenciamento de exercícios
  - Importação/exportação de dados
  - Relatórios e análises
  - Configurações do sistema
- **Métodos principais**: `dashboard()`, `alunos()`, `criarAluno()`, `professores()`, `exercicios()`, `relatorios()`

**ParentController.php**
- **Responsabilidade**: Gerencia o painel dos pais/responsáveis
- **Funcionalidades**:
  - Dashboard dos pais
  - Visualização de filhos
  - Acompanhamento de desempenho
  - Visualização de jornadas dos filhos
  - Visualização de redações
  - Relatórios dos filhos
- **Métodos principais**: `dashboard()`, `filhos()`, `filhoDetalhes()`, `desempenhoFilho()`, `jornadasFilho()`, `redacoesFilho()`

**UserController.php**
- **Responsabilidade**: Gerencia operações genéricas de usuários
- **Funcionalidades**:
  - CRUD de usuários
  - Upload de avatar
  - Alteração de senha
- **Métodos principais**: `index()`, `create()`, `store()`, `edit()`, `update()`, `uploadAvatar()`, `changePassword()`

**AvatarController.php**
- **Responsabilidade**: Gerencia criação e gerenciamento de avatares
- **Funcionalidades**:
  - Geração de avatares
  - Upload de avatares personalizados
  - Geração de SVG
- **Métodos principais**: `index()`, `salvar()`, `gerarAvatar()`, `gerarSvg()`

#### Teacher/

**TeacherAIController.php**
- **Responsabilidade**: Gerencia agentes de IA para professores
- **Funcionalidades**:
  - Criação e edição de agentes de IA
  - Upload de documentos para RAG (Retrieval Augmented Generation)
  - Chat com agentes de IA
  - Histórico de conversas
- **Métodos principais**: `index()`, `criar()`, `editar()`, `salvar()`, `uploadDocumento()`, `enviarMensagem()`, `historicoConversa()`

**TeacherJourneyController.php**
- **Responsabilidade**: Gerencia jornadas de aprendizado criadas por professores
- **Funcionalidades**:
  - CRUD de jornadas
  - Gerenciamento de módulos
  - Criação de exercícios (manual e por IA)
  - Gerenciamento de redações
  - Análise de resumos dos alunos
  - Mensagens e comunicação com alunos
- **Métodos principais**: `index()`, `criar()`, `editar()`, `exercicios()`, `criarExercicio()`, `gerarExercicioIA()`, `redacoes()`, `corrigirRedacao()`, `analiseResumos()`

**TeacherNotificationController.php**
- **Responsabilidade**: Gerencia notificações enviadas por professores para suas turmas
- **Funcionalidades**:
  - Criação de notificações
  - Listagem de notificações
  - Visualização de notificações
  - Exclusão de notificações
- **Métodos principais**: `index()`, `create()`, `store()`, `show()`, `delete()`

**TeacherExamController.php**
- **Responsabilidade**: Gerencia provas criadas por professores dentro de blocos
- **Funcionalidades**:
  - Visualização de provas do professor
  - Envio de provas para aprovação
  - Gerenciamento de provas em blocos
  - Visualização completa de blocos
  - Aprovação final de blocos
- **Métodos principais**: `index()`, `enviar()`, `aprovar()`, `reprovar()`, `gerenciar()`, `visualizarCompleto()`, `aprovarFinal()`

#### Education/

**JourneyController.php**
- **Responsabilidade**: Gerencia jornadas de aprendizado do ponto de vista do aluno
- **Funcionalidades**:
  - Listagem de jornadas disponíveis
  - Visualização de jornadas
  - Execução de exercícios de módulos
  - Envio de resumos
  - Escrita de redações da jornada
  - Visualização de correções
- **Métodos principais**: `index()`, `show()`, `executarExerciciosModulo()`, `responderExercicioModulo()`, `enviarResumo()`, `escreverRedacaoJornada()`, `verCorrecaoRedacaoJornada()`

**AdminJourneyController.php**
- **Responsabilidade**: Gerencia jornadas do ponto de vista administrativo
- **Funcionalidades**:
  - CRUD de jornadas (admin)
  - Gerenciamento de módulos
  - Adição de exercícios, vídeos e documentos
  - Gerenciamento de redações de módulos
  - Relatórios de jornadas
- **Métodos principais**: `index()`, `criar()`, `editar()`, `gerenciarModulos()`, `adicionarModulo()`, `gerenciarExerciciosModulo()`, `gerenciarVideosModulo()`, `gerenciarRedacaoModulo()`

**LessonPlanController.php**
- **Responsabilidade**: Gerencia planos de aula
- **Funcionalidades**:
  - CRUD de planos de aula (professor)
  - Visualização e aprovação (admin)
  - Exportação para PDF
  - Busca por objetivos
- **Métodos principais**: `index()`, `criar()`, `editar()`, `salvar()`, `visualizar()`, `exportarPdf()`, `aprovarRejeitar()`

**ClassController.php**
- **Responsabilidade**: Gerencia turmas (classes)
- **Funcionalidades**:
  - CRUD de turmas
  - Busca por ano letivo
  - Busca por série
  - Toggle de status
- **Métodos principais**: `index()`, `create()`, `store()`, `show()`, `edit()`, `update()`, `destroy()`, `toggleStatus()`, `getByAnoLetivo()`, `getBySerie()`

**ContentBlockController.php**
- **Responsabilidade**: Gerencia blocos de conteúdo dentro de jornadas
- **Funcionalidades**:
  - Adição de blocos de conteúdo
  - Edição de blocos
  - Remoção de blocos
  - Atualização de ordem
- **Métodos principais**: `index()`, `adicionarBloco()`, `editarBloco()`, `removerBloco()`, `atualizarOrdem()`

#### Exams/

**ExamController.php**
- **Responsabilidade**: Gerencia provas individuais
- **Funcionalidades**:
  - CRUD de provas (professor e admin)
  - Criação de questões (manual e por IA)
  - Upload de imagens para questões
  - Correção de questões dissertativas
  - Visualização de resultados de alunos
  - Liberação e aprovação de provas
  - Gerenciamento de provas em blocos
- **Métodos principais**: `index()`, `criar()`, `editar()`, `salvar()`, `adicionarQuestao()`, `gerarQuestoesIA()`, `corrigirQuestao()`, `liberarProva()`, `retornar()`, `realizar()`, `finalizar()`

**ExamBlockController.php**
- **Responsabilidade**: Gerencia blocos de provas (múltiplas provas agrupadas)
- **Funcionalidades**:
  - CRUD de blocos de provas
  - Associação de provas ao bloco
  - Associação de professores e turmas
  - Toggle de liberação
  - Criação a partir de blocos modelo
- **Métodos principais**: `index()`, `criar()`, `editar()`, `salvar()`, `atualizar()`, `visualizar()`, `toggleLiberado()`, `excluir()`

**ExamBlockModelController.php**
- **Responsabilidade**: Gerencia blocos modelo (templates de blocos de provas)
- **Funcionalidades**:
  - CRUD de blocos modelo
  - Reutilização de estruturas de provas
- **Métodos principais**: `index()`, `criar()`, `editar()`, `salvar()`, `atualizar()`, `excluir()`, `dados()`

**MockExamController.php**
- **Responsabilidade**: Gerencia simulados ENEM
- **Funcionalidades**:
  - Criação de simulados
  - Execução de simulados
  - Correção automática
  - Visualização de resultados
  - Ocultação de simulados
- **Métodos principais**: `index()`, `criar()`, `criarSimulado()`, `iniciar()`, `responder()`, `finalizar()`, `resultado()`, `ocultarSimulado()`

#### Exercises/

**ExerciseController.php**
- **Responsabilidade**: Gerencia exercícios padrão do sistema
- **Funcionalidades**:
  - Listagem de exercícios disponíveis
  - Início de execução
  - Resposta a questões
  - Finalização e resultado
  - Histórico de exercícios
- **Métodos principais**: `index()`, `iniciar()`, `responder()`, `finalizar()`, `resultado()`, `historico()`

**CustomExerciseController.php**
- **Responsabilidade**: Gerencia exercícios personalizados criados por professores
- **Funcionalidades**:
  - Criação de listas de exercícios personalizados
  - Geração de exercícios por IA
  - Execução de exercícios personalizados
  - Histórico e resultados
- **Métodos principais**: `index()`, `criar()`, `gerarExercicios()`, `iniciarExecucao()`, `executar()`, `responderQuestao()`, `finalizar()`, `resultados()`, `historico()`

#### Games/

**GameController.php**
- **Responsabilidade**: Gerencia jogos educacionais genéricos
- **Funcionalidades**:
  - Listagem de jogos disponíveis
  - Acesso a jogos específicos (xadrez, damas, milhão)
- **Métodos principais**: `index()`, `xadrez()`, `damas()`, `milhao()`

**MillionGameController.php**
- **Responsabilidade**: Gerencia o Jogo do Milhão
- **Funcionalidades**:
  - Início de partidas
  - Continuação de partidas
  - Resposta a perguntas
  - Uso de ajudas
  - Abandono de partidas
  - Verificação de partidas ativas
  - Limpeza de partidas órfãs
- **Métodos principais**: `index()`, `jogar()`, `iniciarPartida()`, `continuarPartida()`, `responderPergunta()`, `usarAjuda()`, `abandonar()`, `heartbeat()`, `verificarPartida()`, `limparOrfas()`

#### Integrations/

**ChatController.php**
- **Responsabilidade**: Gerencia chat entre alunos e professores
- **Funcionalidades**:
  - Listagem de conversas
  - Chat individual aluno-professor
  - Envio de mensagens
  - Busca de mensagens
- **Métodos principais**: `indexAluno()`, `chatAluno()`, `enviarMensagemAluno()`, `buscarMensagensAluno()`, `indexProfessor()`, `chatProfessor()`, `enviarMensagemProfessor()`, `buscarMensagensProfessor()`

**EnglishController.php**
- **Responsabilidade**: Gerencia funcionalidades de inglês (speaking)
- **Funcionalidades**:
  - Transcrição de áudio para texto
  - Conversação em inglês com IA
  - Histórico de conversas
- **Métodos principais**: `index()`, `transcreverAudio()`, `conversar()`, `historico()`

**GoogleBooksController.php**
- **Responsabilidade**: Integração com Google Books API
- **Funcionalidades**:
  - Busca de livros
  - Visualização de detalhes
  - Integração com biblioteca educacional
- **Métodos principais**: `index()`, `buscar()`, `detalhes()`

**SlidesController.php**
- **Responsabilidade**: Integração com Gamma Slides API para geração de slides
- **Funcionalidades**:
  - Geração de slides educacionais via IA
- **Métodos principais**: `gerar()`

**WebhookController.php**
- **Responsabilidade**: Gerencia webhooks do sistema
- **Funcionalidades**:
  - CRUD de webhooks
  - Teste de webhooks
- **Métodos principais**: `index()`, `create()`, `update()`, `delete()`, `test()`

#### Notifications/

**NotificationController.php**
- **Responsabilidade**: Gerencia notificações do sistema (admin)
- **Funcionalidades**:
  - CRUD de notificações
  - API para notificações não lidas
  - Marcação de notificações como lidas
- **Métodos principais**: `index()`, `create()`, `store()`, `show()`, `edit()`, `update()`, `delete()`, `apiNaoLidas()`, `apiMarcarLida()`

**UserNotificationController.php**
- **Responsabilidade**: Gerencia visualização de notificações pelos usuários
- **Funcionalidades**:
  - Listagem de notificações do usuário
  - Visualização individual
  - Marcação como lida
  - API para notificações recentes
- **Métodos principais**: `index()`, `atualizar()`, `show()`, `marcarLida()`, `apiNaoLidas()`, `apiMarcarLida()`, `apiRecentes()`, `apiMarcarTodasLidas()`

#### Support/

**SupportTicketController.php**
- **Responsabilidade**: Gerencia tickets de suporte
- **Funcionalidades**:
  - Criação de tickets
  - Visualização de tickets
  - Envio de mensagens em tickets
- **Métodos principais**: `index()`, `criar()`, `processarCriar()`, `visualizar()`, `enviarMensagem()`

#### Master/ (multi-tenant)

**MasterAuthController.php** — Login e dashboard no banco master; **MasterEscolasController.php** — CRUD de escolas; **MasterEscolaDetailController.php** — Detalhes da escola: visão geral, usuários, professores, módulos, layout, links úteis, apps externos, prompts, API keys, e-mail, logins, acessos, limites, vídeos, banco, entrar como; **MasterMigrationsController.php** — Executar migrações (todas ou por escola), marcar executadas.

#### Api/

**AuthController.php** — Login API (JWT para pais); **ParentController.php** — REST: filhos, dashboard, provas, exercícios, jornadas, planos de aula, redações (middleware ApiAuth); **SwaggerController.php** — Documentação OpenAPI; **OneSignalTagsController.php** — Tags OneSignal; **PushTrackingController.php** — Tracking de push (visualizado/clicado).

#### Media/, Pwa/, EducaLabs/, ExternalApps/, Notes/

**MediaServeController.php** — Servir mídia (layout, redações etc.) via URL estável (S3 ou local). **PwaController.php** — Manifests PWA por perfil (aluno, professor, admin, pais). **EducaLabsController.php** — Acesso, CRUD de projetos, preview, token, view pública. **ExternalAppsController.php** — Abrir app externo, validar token. **NotesController.php** — Acesso às notas; **NotesTokenController.php** — Validar token e logout.

#### Noticias/, Apostilas/, Minicursos/, Forum/, Files/, Study/

**NoticiasController.php** — API notícias (RSS); **NotificacoesApiController.php** — API notificações manuais. **AdminApostilaController**, **TeacherApostilaController**, **StudentApostilaController** — Apostilas. **AdminMinicursoController**, **StudentMinicursoController** — Minicursos. **ForumController.php** — Fórum (index, create, store, show, reply, vote, markBestAnswer); **ForumModerationController.php** — Denúncias, alertas, resolver, excluir tópico/resposta. **StudentFileController**, **TeacherFileController** — Arquivos. **FlashcardController.php** — Flashcards (SmartFlashcards com IA).

#### Admin/, Notifications (extras)

**AdminMuralController.php** — Mural de recados (admin); **DevSettingsController.php** — Configurações de desenvolvimento. **PushNotificationController.php** — CRUD de push notifications (admin).

---

## 5. Models

### 5.1 Organização dos Models

Os models estão organizados em subpastas por **domínio de entidade**, representando as entidades principais do sistema.

**Estrutura de Organização:**

```
Models/
├── User/                    # Student, Teacher, Parent
├── Education/               # ClassRoom, LessonPlan, Subject
├── Exams/                   # Exam, ExamBlock, ExamBlockModel, TeacherExam
├── Notifications/           # Notification, NotificationRecipient
├── Drive/                   # DriveItem, DriveShare
├── Essays/                  # EssayBoard, EssayCorrection, EssayCriterion, EssayPrompt, EssayProposal, EssaySubmission, EssayTextType
├── Forum/                   # ForumAttachment, ForumModerationAlert, ForumReply, ForumReport, ForumTopic, ForumVote
├── Study/                   # Flashcard, FlashcardDeck, FlashcardExplicacao, FlashcardTemplate
├── PushNotifications/      # PushNotification
├── System/                  # DevSetting
├── Noticia.php              # Raiz
└── NotificacaoApi.php       # Raiz
```

**Padrões de Nomenclatura:**

- Todos os nomes em **inglês**
- **PascalCase**
- Nomes no **singular**
- Representam **entidades do domínio**, não ações
- Evitar palavras reservadas do PHP (ex: `Class` → `ClassRoom`)

### 5.2 Tradução dos Models (Português → Inglês)

| Nome Antigo | Nome Atual | Localização | Motivo |
|------------|-----------|-------------|--------|
| `Prova.php` | `Exam.php` | `Exams/Exam.php` | Padronização para inglês |
| `ProvaBloco.php` | `ExamBlock.php` | `Exams/ExamBlock.php` | Padronização para inglês |
| `ProvaProfessor.php` | `TeacherExam.php` | `Exams/TeacherExam.php` | Padronização e clareza |
| `BlocoModelo.php` | `ExamBlockModel.php` | `Exams/ExamBlockModel.php` | Padronização para inglês |
| `PlanoAula.php` | `LessonPlan.php` | `Education/LessonPlan.php` | Padronização para inglês |
| `Turma.php` | `ClassRoom.php` | `Education/ClassRoom.php` | Evitar conflito com palavra reservada `Class` |
| `Notificacao.php` | `Notification.php` | `Notifications/Notification.php` | Padronização para inglês |
| `NotificacaoDestinatario.php` | `NotificationRecipient.php` | `Notifications/NotificationRecipient.php` | Padronização para inglês |
| `Student.php` | `Student.php` | `User/Student.php` | Já estava correto |
| `Teacher.php` | `Teacher.php` | `User/Teacher.php` | Já estava correto |
| `Parent.php` | `Parent.php` | `User/Parent.php` | Já estava correto |
| `Subject.php` | `Subject.php` | `Education/Subject.php` | Já estava correto |

### 5.3 Descrição de Todos os Models

#### User/

**Student.php**
- **Representa**: Entidade Aluno
- **Responsabilidades**:
  - CRUD de alunos
  - Busca e listagem
  - Relacionamentos com turmas
  - Validações de dados do aluno
- **Relações principais**: 
  - Pertence a uma `ClassRoom` (turma)
  - Tem múltiplas `Exam` (provas realizadas)
  - Tem múltiplas redações
  - Participa de múltiplas jornadas

**Teacher.php**
- **Representa**: Entidade Professor
- **Responsabilidades**:
  - CRUD de professores
  - Gerenciamento de turmas associadas
  - Validações de dados do professor
- **Relações principais**:
  - Tem múltiplas `ClassRoom` (turmas)
  - Cria múltiplas `Journey` (jornadas)
  - Cria múltiplas `Exam` (provas)
  - Tem múltiplas `LessonPlan` (planos de aula)

**Parent.php**
- **Representa**: Entidade Pai/Responsável
- **Responsabilidades**:
  - CRUD de pais
  - Relacionamento com alunos (filhos)
  - Validações de dados
- **Relações principais**:
  - Tem múltiplos `Student` (filhos)

#### Education/

**ClassRoom.php**
- **Representa**: Entidade Turma
- **Responsabilidades**:
  - CRUD de turmas
  - Busca por série e ano letivo
  - Validações de dados
- **Relações principais**:
  - Tem múltiplos `Student` (alunos)
  - Tem múltiplos `Teacher` (professores)
  - Tem múltiplas `Journey` (jornadas)
  - Tem múltiplas `ExamBlock` (blocos de provas)

**LessonPlan.php**
- **Representa**: Entidade Plano de Aula
- **Responsabilidades**:
  - CRUD de planos de aula
  - Aprovação/rejeição (admin)
  - Exportação para PDF
  - Validações
- **Relações principais**:
  - Pertence a um `Teacher` (professor criador)
  - Pode estar associado a uma `Subject` (matéria)

**Subject.php**
- **Representa**: Entidade Matéria/Disciplina
- **Responsabilidades**:
  - CRUD de matérias
  - Validações
- **Relações principais**:
  - Tem múltiplas `Journey` (jornadas)
  - Tem múltiplas `Exam` (provas)
  - Tem múltiplos `LessonPlan` (planos de aula)

#### Exams/

**Exam.php**
- **Representa**: Entidade Prova Individual
- **Responsabilidades**:
  - CRUD de provas
  - Gerenciamento de questões
  - Gerenciamento de respostas dos alunos
  - Cálculo de notas
  - Finalização de provas
  - Validações
- **Relações principais**:
  - Pertence a um `Teacher` (professor criador)
  - Pode pertencer a um `ExamBlock` (bloco)
  - Tem múltiplas questões
  - Tem múltiplas respostas de alunos
  - Está associada a múltiplas `ClassRoom` (turmas)

**ExamBlock.php**
- **Representa**: Entidade Bloco de Provas (múltiplas provas agrupadas)
- **Responsabilidades**:
  - CRUD de blocos de provas
  - Associação de provas ao bloco
  - Associação de professores e turmas
  - Gerenciamento de status e liberação
  - Validações
- **Relações principais**:
  - Tem múltiplas `Exam` (provas do bloco)
  - Tem múltiplos `Teacher` (professores)
  - Está associado a múltiplas `ClassRoom` (turmas)
  - Pode ser baseado em um `ExamBlockModel` (bloco modelo)

**TeacherExam.php**
- **Representa**: Entidade Prova de Professor (dentro de blocos)
- **Responsabilidades**:
  - Gerenciamento de provas criadas por professores em blocos
  - Status de aprovação
  - Validações
- **Relações principais**:
  - Pertence a um `Teacher` (professor)
  - Pertence a um `ExamBlock` (bloco)
  - Relacionada a uma `Exam` (prova individual)

**ExamBlockModel.php**
- **Representa**: Entidade Bloco Modelo (template de blocos de provas)
- **Responsabilidades**:
  - CRUD de blocos modelo
  - Reutilização de estruturas
  - Validações
- **Relações principais**:
  - Pode gerar múltiplos `ExamBlock` (blocos reais)

#### Notifications/

**Notification.php**
- **Representa**: Entidade Notificação
- **Responsabilidades**:
  - CRUD de notificações
  - Gerenciamento de destinatários
  - Marcação como lida
  - Validações
- **Relações principais**:
  - Tem múltiplos `NotificationRecipient` (destinatários)
  - Criada por `Admin` ou `Teacher`

**NotificationRecipient.php**
- **Representa**: Entidade Destinatário de Notificação
- **Responsabilidades**:
  - Gerenciamento de destinatários
  - Marcação de leitura
  - Validações
- **Relações principais**:
  - Pertence a uma `Notification` (notificação)
  - Relacionado a um usuário (aluno, professor, pai)

#### Drive/, Essays/, Forum/, Study/, PushNotifications/, System/

**DriveItem**, **DriveShare** — Itens e compartilhamentos do Drive. **EssayBoard**, **EssayCorrection**, **EssayCriterion**, **EssayPrompt**, **EssayProposal**, **EssaySubmission**, **EssayTextType** — Redações (boards, correções, critérios, propostas, envios). **ForumTopic**, **ForumReply**, **ForumVote**, **ForumReport**, **ForumAttachment**, **ForumModerationAlert** — Fórum e moderação. **Flashcard**, **FlashcardDeck**, **FlashcardExplicacao**, **FlashcardTemplate** — Flashcards. **PushNotification** — Notificações push. **DevSetting** — Configurações de desenvolvimento por escola. **Noticia** — Notícias (RSS); **NotificacaoApi** — Notificações manuais via API.

---

## 6. Views

### 6.1 Organização das Views

As views estão organizadas principalmente por **papel do usuário** (student, teacher, admin, parents), com layouts compartilhados e componentes reutilizáveis.

**Estrutura de Organização:**

```
Views/
├── admin/              # Dashboard, alunos, professores, turmas, provas, jornadas, notificações, push-notifications,
│                       # exercícios, matérias, blocos-modelo, mural-recados, essays, minicursos, settings, reports,
│                       # grade-horaria, monitoramento, cursos, classes, forum, usuarios, financeiro, apostilas,
│                       # planos-aula, dev, maintenance, webhooks, dev-settings, ocorrencias, notifications
├── teacher/            # Dashboard, jornadas, provas, ai-agent, chat, notifications, mural, alunos
├── student/            # Dashboard, exams, exercises, exercises-personalizados, journeys, simulations, chat, tickets,
│                       # jogo-milhao, livros, ingles, forum, notes, materiais, educalabs, drive, caderno, apostilas,
│                       # planos-aula, arquivos, mural-recados, essays, minicursos, flashcards
├── parents/            # Dashboard, filhos, desempenho, jornadas, redações, relatórios
├── auth/               # Login por perfil (aluno, admin, professor, pais)
├── layouts/            # Layouts base (admin, teacher, student, parent) e components (sidebars, notification-banner)
├── master/             # Painel master: escolas (listagem, detalhes: acessos, professores, usuarios, etc.), migrations
├── legal/              # Termos, políticas
├── components/         # Componentes reutilizáveis
└── notifications/      # Views de notificações
```

**Padrões de Organização:**

- Views separadas por papel do usuário
- Uso de layouts base (`layouts/student.php`, `layouts/teacher.php`, etc.)
- Componentes reutilizáveis em `layouts/components/`
- Views específicas por funcionalidade dentro de cada pasta

### 6.2 Descrição das Views

#### admin/

Views para o painel administrativo. Inclui:

- **dashboard.php**: Dashboard principal do admin
- **students/**: CRUD de alunos
- **teachers/**: CRUD de professores
- **turmas/**: CRUD de turmas
- **provas/**: Gerenciamento de provas e blocos
- **journeys/**: Gerenciamento de jornadas
- **planos-aula/**: Aprovação de planos de aula
- **reports/**: Relatórios e análises
- **notifications/**: Gerenciamento de notificações
- **settings/**: Configurações do sistema

#### teacher/

Views para o painel do professor. Inclui:

- **dashboard.php**: Dashboard do professor
- **provas/**: Criação e edição de provas
- **journeys/**: Gerenciamento de jornadas
- **planos-aula/**: Criação de planos de aula
- **ai-agent/**: Gerenciamento de agentes de IA
- **chat/**: Chat com alunos
- **notifications/**: Criação de notificações
- **alunos.php**: Visualização de alunos
- **student-*.php**: Visualizações específicas de alunos

#### student/

Views para o painel do aluno. Inclui:

- **dashboard.php**: Dashboard do aluno
- **exams/**: Provas online (listagem, realização, resultados)
- **exercises/**: Exercícios padrão
- **exercises-personalizados/**: Exercícios personalizados
- **journeys/**: Jornadas de aprendizado
- **simulations/**: Simulados ENEM
- **chat/**: Chat com IA (Tudinha) e professores
- **tickets/**: Tickets de suporte
- **jogo-milhao/**: Jogo do Milhão
- **livros/**: Biblioteca (Google Books)
- **ingles/**: Módulo de inglês (speaking)

#### parents/

Views para o painel dos pais. Inclui:

- **dashboard.php**: Dashboard dos pais
- **filhos.php**: Lista de filhos
- **filho-detalhes.php**: Detalhes de um filho
- **desempenho-filho.php**: Desempenho acadêmico
- **jornadas-filho.php**: Jornadas do filho
- **redacoes-filho.php**: Redações do filho
- **relatorios-filho.php**: Relatórios do filho

#### auth/

Views de autenticação:

- **login.php**: Login genérico
- **login-admin.php**: Login de administrador
- **login-aluno.php**: Login de aluno
- **login-professor.php**: Login de professor
- **login-pais.php**: Login de pais

#### layouts/

Layouts base e componentes:

- **admin.php**: Layout base para admin
- **teacher.php**: Layout base para professor
- **student.php**: Layout base para aluno
- **parent.php**: Layout base para pais
- **professor.php**: Layout alternativo (compatibilidade)
- **components/**: Componentes reutilizáveis
  - **admin_sidebar.php**: Menu lateral do admin
  - **professor_sidebar.php**: Menu lateral do professor
  - **student_sidebar.php**: Menu lateral do aluno
  - **parent_sidebar.php**: Menu lateral dos pais
  - **notification-banner.php**: Banner de notificações

### 6.3 Relação View ↔ Controller

**Padrão de Nomenclatura:**

- Controller: `Exams/ExamController.php`
- View correspondente: `student/exams/index.php` ou `teacher/provas/index.php`

**Método de Renderização:**

Os controllers usam o método `viewWithLayout()` do `BaseController`:

```php
$this->viewWithLayout('student', 'student/exams/index', $data);
```

Onde:
- `'student'`: Layout a ser usado (`layouts/student.php`)
- `'student/exams/index'`: Caminho da view dentro de `Views/`
- `$data`: Array de dados passados para a view

---

## 7. Rotas (Router)

### 7.1 Sistema de Roteamento

O sistema de roteamento está implementado em `app/Core/Router.php` e as rotas são definidas em `config/routes.php`.

### 7.2 Como Funciona o Roteamento

1. **Ponto de Entrada**: `index.php` inicializa o `App` que carrega o `Router`
2. **Definição de Rotas**: `config/routes.php` define todas as rotas usando métodos do router
3. **Dispatch**: O router compara a URL da requisição com as rotas definidas
4. **Execução**: Se encontrar correspondência, executa o controller e método especificados

### 7.3 Formato de Rotas

**Sintaxe:**
```php
$router->get('/caminho', 'Controller/MetodoController@metodo');
$router->post('/caminho', 'Controller/MetodoController@metodo');
```

**Exemplos:**
```php
// Rota GET simples
$router->get('/dashboard', 'User/StudentController@dashboard');

// Rota com parâmetro
$router->get('/aluno/provas/realizar/{id}', 'Exams/ExamController@realizar');

// Rota POST
$router->post('/login', 'Auth/AuthController@autenticar');
```

### 7.4 Suporte a Subpastas de Controllers

O router foi atualizado para suportar controllers em subpastas:

```php
// Antes (não funcionava):
'ExamController@index'

// Agora (funciona):
'Exams/ExamController@index'
```

O router busca automaticamente em:
- `app/Controllers/Exams/ExamController.php`
- Tenta diferentes namespaces se necessário

### 7.5 Middleware

O sistema suporta middleware para autenticação e API:

```php
// Sessão (web)
$router->middleware('Auth', function($router) {
    $router->get('/dashboard', 'User/StudentController@dashboard');
});

// JWT (API pais)
$router->middleware('ApiAuth', function($router) {
    $router->get('/api/parents/children', 'Api/ParentController@children');
});
```

Nomes usados: **Auth** (AuthMiddleware), **ApiAuth** (ApiAuthMiddleware), **PasswordCheck**, **GameSecurity**, **Audit**.

### 7.6 Padrão de URLs

**Master (multi-tenant, domínio master):**
- `/master` — Login master
- `/master/dashboard` — Dashboard
- `/master/escolas` — Listagem e CRUD de escolas
- `/master/escolas/{id}/detalhes` — Detalhes (usuários, professores, módulos, layout, api-keys, limites, vídeos, banco, etc.)
- `/master/migrations` — Executar migrações

**Públicas (sem autenticação):**
- `/` — Login de aluno
- `/admin`, `/professor`, `/pais` — Telas de login por perfil
- `/login`, `/logout` — Autenticação
- `/termos-de-uso`, `/politica-privacidade`, `/politica-retencao`
- `/recuperar-senha`, `/primeiro-acesso`, `/aluno/recuperar-senha`, `/professor/recuperar-senha`
- `/media/serve` — Servir mídia; `/api/noticias`, `/api/notificacoes` — APIs públicas
- `/api/auth/login` — Login API (JWT); `/api/docs`, `/api/openapi.json` — Swagger
- EducaLabs/Notes/Games/ExternalApps: rotas públicas de validação de token e view pública

**Protegidas (middleware Auth):**
- `/dashboard` — Dashboard do usuário
- `/aluno/provas`, `/professor/provas`, `/admin/*` — Por perfil
- `/forum`, `/drive`, `/chat`, `/exercicios`, `/jornadas`, `/jogo-milhao`, etc.

**API Pais (middleware ApiAuth):**
- `/api/parents/children`, `/api/parents/child/{id}/dashboard`, `/api/parents/child/{id}/exams`, etc.

---

## 8. Arquivos Principais do Projeto

### 8.1 index.php

**Localização**: `src/index.php`

**Responsabilidade**: Ponto de entrada principal da aplicação

**Funcionalidades**:
- Define `ENV_FILE_PATH`, ajusta `memory_limit`
- Carrega autoload do Composer
- Configura sessão (cookie params: lifetime, domain, secure, httponly, samesite)
- Define `FOLDER` e `URL` a partir de `SCRIPT_NAME` e `HTTP_HOST`
- Configura ambiente (`ENVIRONMENT`, `DEBUG`), timezone (America/Sao_Paulo)
- Registra shutdown para erros fatais (métricas, `buildStructuredErrorLog`, Logger)
- Autoloader customizado (suporte a `DatabaseWrapper` via `config/monitoring.php`)
- Funções auxiliares: `inferSchoolForErrorLog`, `inferUserTypeForErrorLog`, `buildStructuredErrorLog`
- Handler de exceções não capturadas (log, métricas 500, resposta JSON ou HTML)
- Servir estáticos: `service-worker.js`, `favicon.ico`, `robots.txt`, `.well-known/*`, `static/*`, `public/uploads/*`, `storage/chat/*`
- **Bootstrap multi-tenant**: `require_once app/Core/bootstrap_multi_tenant.php` (se `MULTI_TENANT=true`: resolve tenant, registra conexão do tenant)
- Carrega `App` e executa `$app->run()`

**Fluxo de Execução**:
1. Configuração de sessão e constantes
2. Autoload e handlers de erro
3. Servir arquivos estáticos (se aplicável) e sair
4. Bootstrap multi-tenant (se ativo)
5. `new App()` → carrega config e rotas
6. `$app->run()` → Router dispatch → Controller → View ou JSON

### 8.2 app/Core/App.php

**Responsabilidade**: Classe principal da aplicação

**Funcionalidades**:
- Inicializa o sistema de roteamento
- Carrega configurações
- Carrega rotas de `config/routes.php`
- Executa o router
- Trata erros e exceções
- Renderiza páginas de erro

### 8.3 app/Core/Router.php

**Responsabilidade**: Sistema de roteamento HTTP

**Funcionalidades**:
- Registra rotas (GET, POST, PUT, DELETE)
- Processa requisições HTTP
- Resolve controllers e métodos
- Suporta parâmetros dinâmicos (`{id}`)
- Suporta middleware
- Suporta controllers em subpastas
- Trata erros de roteamento

### 8.4 app/Core/Database.php

**Responsabilidade**: Gerenciador de banco de dados

**Funcionalidades**:
- Conexão com MySQL/MariaDB
- Execução de queries
- Prepared statements
- Transações
- Singleton pattern

### 8.5 app/Core/BaseController.php

**Responsabilidade**: Controller base para todos os controllers

**Funcionalidades**:
- Métodos comuns (`viewWithLayout()`, `redirect()`, `json()`)
- Gerenciamento de mensagens flash
- Geração de tokens CSRF
- Validações básicas

### 8.6 app/Core/AuthManager.php

**Responsabilidade**: Gerenciamento de autenticação

**Funcionalidades**:
- Login de usuários
- Verificação de autenticação
- Gerenciamento de sessão
- Redirecionamento baseado em perfil

### 8.7 config/app.php

**Responsabilidade**: Configurações gerais da aplicação (carrega `.env`)

**Conteúdo**:
- `loadEnv()` e `env()`: variáveis do `.env`; em multi-tenant, `tenant.id`, `tenant.slug`, `tenant.domain` (runtime)
- `app`: name, version, environment, debug, url, folder
- `database`: host, port, name, user, pass, charset, options PDO
- `session`: name, lifetime, domain, secure, httponly, samesite
- `security`: csrf_token_name, password_min_length, max_login_attempts, lockout_duration, entra_como_secret
- `upload`, `ai`, `email`, `logs`, `school` (code, name, logo, colors), `aws`, `drive`, `media` (storage, local_paths, tenant_prefix), `tenant`, `perfiles`, `routes`

### 8.8 config/routes.php

**Responsabilidade**: Definição de todas as rotas da aplicação

**Organização**:
- Rotas públicas no início
- Rotas protegidas dentro de middleware
- Agrupadas por funcionalidade
- Comentários explicativos

---

### 8.9 app/Core/bootstrap_multi_tenant.php e TenantResolver

**bootstrap_multi_tenant.php**: Lido por `index.php` quando `MULTI_TENANT=true` no `.env`. Conecta ao banco master, verifica se a requisição é para `/master` ou domínio master; caso contrário, usa **TenantResolver** para obter `escola_id` por header `X-Tenant` (slug) ou por `HTTP_HOST` (domínio). Registra a conexão do tenant como a instância padrão de `Database`.

**TenantResolver**: Recebe PDO do banco master. `resolve()` retorna `escola_id`; `resolveTenant()` retorna array `id`, `slug`, `dominio`. Consulta tabela `escolas` (slug ou dominio, ativo=1) e opcionalmente `config_escolas_banco` para dados de conexão do tenant.

---

## 9. Padrões e Convenções do Projeto

### 9.1 Idioma

**Regra**: Todo o código deve estar em **inglês**

- Nomes de classes: `ExamController`, `StudentModel`
- Nomes de métodos: `getProvas()`, `createExam()`
- Nomes de variáveis: `$examData`, `$studentList`
- Comentários: Podem estar em português para facilitar comunicação
- Mensagens ao usuário: Português (interface)

### 9.2 Nomenclatura

**Controllers:**
- PascalCase
- Sufixo `Controller`
- Singular: `ExamController` (não `ExamsController`)
- Exemplo: `app/Controllers/Exams/ExamController.php`

**Models:**
- PascalCase
- Singular
- Representa entidade: `Exam` (não `ExamManager`)
- Exemplo: `app/Models/Exams/Exam.php`

**Views:**
- snake_case ou kebab-case
- Descritivo: `student/exams/index.php`
- Exemplo: `app/Views/student/exams/index.php`

**Métodos:**
- camelCase
- Verbos de ação: `get()`, `create()`, `update()`, `delete()`
- Descritivos: `getProvasAluno()` (não `getPA()`)

**Variáveis:**
- camelCase
- Descritivas: `$examData` (não `$ed`)

### 9.3 Organização

**Controllers:**
- Organizados por **domínio de responsabilidade**
- Subpastas: `Auth/`, `User/`, `Exams/`, `Education/`, etc.
- Não organizar por papel do usuário

**Models:**
- Organizados por **domínio de entidade**
- Subpastas: `User/`, `Education/`, `Exams/`, `Notifications/`

**Views:**
- Organizadas por **papel do usuário**
- Subpastas: `admin/`, `teacher/`, `student/`, `parents/`
- Funcionalidades dentro de cada pasta

### 9.4 Estrutura de Arquivos

**Controller:**
```php
<?php
class ExamController extends BaseController
{
    private $examModel;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        require_once __DIR__ . '/../../Models/Exams/Exam.php';
        $this->examModel = new Exam();
        $this->db = Database::getInstance();
    }
    
    public function index()
    {
        // Lógica do método
    }
}
```

**Model:**
```php
<?php
class Exam
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function findById($id)
    {
        // Lógica de busca
    }
}
```

### 9.5 Onde Criar Novos Arquivos

**Novo Controller:**
1. Identificar o domínio (Exams, Education, User, etc.)
2. Criar em `app/Controllers/{Dominio}/NomeController.php`
3. Seguir padrão de nomenclatura
4. Adicionar rota em `config/routes.php`

**Novo Model:**
1. Identificar o domínio da entidade
2. Criar em `app/Models/{Dominio}/NomeEntidade.php`
3. Seguir padrão de nomenclatura
4. Atualizar controllers que usam o model

**Nova View:**
1. Identificar o papel do usuário (student, teacher, admin, parents)
2. Criar em `app/Views/{papel}/funcionalidade/arquivo.php`
3. Usar layout apropriado via `viewWithLayout()`

### 9.6 Boas Práticas Adotadas

1. **Separação de Responsabilidades**: Cada classe tem uma responsabilidade clara
2. **DRY (Don't Repeat Yourself)**: Reutilização de código via BaseController e layouts
3. **Single Responsibility**: Controllers focados, Models representam entidades
4. **Organização por Domínio**: Facilita escalabilidade
5. **Nomenclatura Consistente**: Padrões claros e seguidos
6. **Validação de Entrada**: Controllers validam dados antes de processar
7. **Tratamento de Erros**: Try-catch e logging adequados
8. **Segurança**: CSRF tokens, validação de permissões, prepared statements

---

## 10. Como Contribuir / Evoluir o Projeto

### 10.1 Onde Criar Novos Controllers

**Processo:**

1. **Identificar o Domínio:**
   - Autenticação? → `Auth/`
   - Gestão de usuário? → `User/`
   - Professor? → `Teacher/`
   - Conteúdo educacional? → `Education/`
   - Provas/exames? → `Exams/`
   - Exercícios? → `Exercises/`
   - Jogos? → `Games/`
   - Integrações externas? → `Integrations/`
   - Notificações? → `Notifications/`
   - Suporte? → `Support/`
   - Painel master (multi-tenant)? → `Master/`
   - API REST? → `Api/`
   - Servir mídia? → `Media/`
   - PWA? → `Pwa/`
   - EducaLabs, apps externos, notes? → `EducaLabs/`, `ExternalApps/`, `Notes/`
   - Apostilas, minicursos, fórum, arquivos, flashcards? → `Apostilas/`, `Minicursos/`, `Forum/`, `Files/`, `Study/`
   - Notícias/API notificações? → `Noticias/`
   - Admin (mural, dev)? → `Admin/`

2. **Criar o Arquivo:**
   ```
   app/Controllers/{Dominio}/NomeController.php
   ```

3. **Seguir Estrutura Base:**
   ```php
   <?php
   class NomeController extends BaseController
   {
       private $model;
       private $db;
       
       public function __construct()
       {
           parent::__construct();
           // Require do model se necessário
           $this->db = Database::getInstance();
       }
       
       public function index()
       {
           // Implementação
       }
   }
   ```

4. **Adicionar Rotas:**
   Em `config/routes.php`:
   ```php
   $router->get('/caminho', '{Dominio}/NomeController@metodo');
   ```

### 10.2 Onde Criar Novos Models

**Processo:**

1. **Identificar o Domínio da Entidade:**
   - Usuário? → `User/`
   - Educacional? → `Education/`
   - Provas? → `Exams/`
   - Notificações? → `Notifications/`
   - Drive? → `Drive/`
   - Redações (essays)? → `Essays/`
   - Fórum? → `Forum/`
   - Estudo (flashcards)? → `Study/`
   - Push? → `PushNotifications/`
   - Sistema (dev settings)? → `System/`
   - Notícias/API? → raiz (`Noticia.php`, `NotificacaoApi.php`)

2. **Criar o Arquivo:**
   ```
   app/Models/{Dominio}/NomeEntidade.php
   ```

3. **Seguir Estrutura Base:**
   ```php
   <?php
   class NomeEntidade
   {
       private $db;
       
       public function __construct()
       {
           $this->db = Database::getInstance();
       }
       
       public function findById($id)
       {
           // Implementação
       }
   }
   ```

4. **Atualizar Controllers:**
   Adicionar require e instanciação nos controllers que usam o model.

### 10.3 Como Manter o Padrão

**Checklist ao Criar Novo Código:**

- [ ] Nome em inglês?
- [ ] PascalCase para classes?
- [ ] camelCase para métodos e variáveis?
- [ ] Singular para controllers e models?
- [ ] Organizado na pasta correta?
- [ ] Rota adicionada em `config/routes.php`?
- [ ] Require do model atualizado se necessário?
- [ ] Comentários explicativos?
- [ ] Validação de entrada?
- [ ] Tratamento de erros?

### 10.4 O que NÃO Fazer

**Evitar:**

1. **Criar controllers fora das subpastas:**
   ❌ `app/Controllers/NovoController.php`
   ✅ `app/Controllers/{Dominio}/NovoController.php`

2. **Usar português em código:**
   ❌ `class ProvaController`
   ✅ `class ExamController`

3. **Usar plural em controllers/models:**
   ❌ `ExamsController`, `StudentsModel`
   ✅ `ExamController`, `Student`

4. **Misturar responsabilidades:**
   ❌ Controller fazendo queries SQL diretas
   ✅ Controller usando Model para acessar dados

5. **Criar models genéricos:**
   ❌ `DataModel`, `ManagerModel`
   ✅ `Exam`, `Student`, `ClassRoom`

6. **Ignorar organização por domínio:**
   ❌ Todos controllers na raiz de `Controllers/`
   ✅ Controllers organizados em subpastas por domínio

7. **Usar palavras reservadas:**
   ❌ `Class` (reservada do PHP)
   ✅ `ClassRoom`

### 10.5 Processo de Refatoração Futura

Se precisar refatorar código existente:

1. **Identificar padrões antigos:**
   - Nomes em português
   - Organização incorreta
   - Violação de padrões

2. **Planejar mudanças:**
   - Mapear arquivos afetados
   - Identificar dependências
   - Criar plano de migração

3. **Executar refatoração:**
   - Mover arquivos
   - Renomear classes
   - Atualizar referências
   - Testar funcionalidades

4. **Documentar:**
   - Atualizar esta documentação
   - Comunicar mudanças à equipe

---

## 11. Estrutura de Banco de Dados

### 11.1 Principais Tabelas (tenant)

**Usuários:** `usuarios`, `alunos`, `professores`, `pais`

**Educacional:** `turmas`, `materias`, `jornadas`, `modulos`, `planos_aula`, e tabelas relacionadas a cursos/tipos de curso (migração 006)

**Provas:** `provas`, `provas_blocos`, `provas_blocos_provas`, `provas_questoes`, `provas_questoes_alternativas`, `provas_respostas`, `provas_realizacoes`, `blocos_modelo`

**Notificações:** `notificacoes`, `notificacoes_destinatarios`; push: tabelas de push notifications

**Outros:** Drive (itens, compartilhamentos), redações (essays), fórum (tópicos, respostas, votos, denúncias, anexos, moderação), flashcards, apostilas, minicursos, notícias/API notificações, dev_settings, etc.

### 11.2 Banco Master (multi-tenant)

Quando `MULTI_TENANT=true`, existe um banco **master** com: `escolas` (id, slug, dominio, ativo, ...), `config_escolas_banco` (configuração de conexão por escola). Script: `database/migrations/multi_tenant_master.sql`.

### 11.3 Migrações

As migrações estão em `database/migrations/`: dump principal (`educatudo.sql`), **multi_tenant_master.sql**, e arquivos numerados (ex.: `003_master_limites_videos.sql`, `004_validacao_tokens_apps.sql`, `005_modulo_apostilas.sql`, `006_tipos_curso_e_cursos.sql`). Devem ser executadas na ordem. O painel Master oferece “Executar todas” ou “Executar por escola”.

---

## 12. Serviços e Integrações

### 12.1 Serviços (app/Services/)

- **OpenAIService.php** — Integração OpenAI API
- **TudinhaService.php** — Chat educacional com IA
- **DocumentProcessorService.php** — Processamento de documentos
- **EmbeddingService.php** — Embeddings para RAG
- **RAGService.php** — RAG (Retrieval Augmented Generation)
- **EssayAIService.php**, **EssayOCRService.php** — Redações e OCR
- **ElevenLabsService.php** — Voz (texto-para-voz, etc.)
- **JWTService.php** — Tokens JWT (API pais)
- **OneSignalService.php** — Push notifications
- **EmailService.php** — Envio de e-mails
- **MediaStorageService.php**, **DriveStorageService.php** — Armazenamento (local/S3)
- **MetricsService.php** — Métricas e monitoramento
- **BackupService.php** — Backup
- **DatabaseWrapper.php** — Wrapper de Database (monitoramento, opcional)
- **ChatSafetyMonitorService.php** — Segurança do chat
- **ForumSecurityService.php** — Segurança do fórum
- **FlashcardService.php** — Geração de flashcards com IA
- **StudentStatusService.php** — Status do aluno
- **FileProcessorService.php** — Processamento de arquivos
- **EvolutionApiService.php**, **NanoBananaService.php** — Integrações externas
- **RssService.php** — RSS (notícias)

### 12.2 Integrações Externas

- **Google Books API** — Biblioteca de livros
- **Gamma Slides API** — Geração de slides
- **OpenAI API** — IA (chat, exercícios, correção, etc.)
- **ElevenLabs** — Voz
- **OneSignal** — Push notifications

---

## 13. Middleware

**app/Middleware/**

- **AuthMiddleware.php** — Autenticação (sessão); redireciona para login se não autenticado
- **ApiAuthMiddleware.php** — Autenticação API por JWT (usado nas rotas `/api/parents/*`)
- **PasswordCheckMiddleware.php** — Verificação de senha obrigatória (primeiro acesso / alteração forçada)
- **GameSecurityMiddleware.php** — Segurança para jogos (token, validação)
- **AuditMiddleware.php** — Auditoria de ações (opcional)

---

## 14. Conclusão

Esta documentação serve como guia oficial para desenvolvedores trabalhando no projeto EducaTudo. A estrutura refatorada segue padrões modernos de desenvolvimento, facilitando manutenção, escalabilidade e colaboração em equipe.

**Princípios Fundamentais:**
- Organização por domínio
- Nomenclatura em inglês
- Separação clara de responsabilidades
- Padrões consistentes
- Documentação atualizada

**Próximos Passos:**
- Manter esta documentação atualizada
- Seguir os padrões estabelecidos
- Contribuir com melhorias na estrutura
- Documentar novas funcionalidades

---

**Última Atualização**: Março 2025
**Versão da Documentação**: 1.1
**Status do Projeto**: Em desenvolvimento ativo (single-tenant e multi-tenant)

