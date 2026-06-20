# Plano de implementação sem regressões

## Situação encontrada

- Backend PHP 8/MySQL em `src/`.
- API JWT de responsáveis já implementada para login por e-mail.
- Endpoints existentes para filhos, dashboard, provas, exercícios, jornadas,
  planos de aula e redações.
- Validação de vínculo responsável–aluno já existe nos controllers da API.
- Push web/PWA usa OneSignal; ainda não há integração FCM mobile.
- `src/` é um repositório Git aninhado/gitlink com alterações existentes.

## Fases

### 0. Linha de base

- registrar os contratos atuais e executar smoke tests web/API;
- preservar todas as alterações existentes em `src/`;
- definir URL/tenant de dev, staging e produção;
- instalar Flutter estável e validar `flutter doctor`.

### 1. Segurança e fundação da API

- adicionar `/api/mobile/v1` sem alterar `/api/auth` e `/api/parents`;
- login por CPF e senha;
- associar JWT ao tenant, adicionar refresh/logout e revogação;
- centralizar autorização de acesso aos filhos;
- adicionar testes de isolamento entre responsáveis e tenants;
- proteger a versão nova com feature flag.

### 2. Shell do aplicativo

- gerar Android com `scripts/bootstrap_android.sh`;
- configurar ambientes, tema, navegação, HTTP e armazenamento seguro;
- implementar login, sessão e seleção de aluno;
- adicionar testes unitários e de widgets desde a primeira feature.

### 3. Fatias acadêmicas

Entregar e validar uma feature por vez: Home, provas, jornadas, redações, plano
de aula, notas/boletim, mensagens e faltas. Cada fatia inclui endpoint, modelo,
repositório, estado, tela, testes e telemetria.

### 4. Push FCM

- migration aditiva de dispositivos e feed;
- registro, renovação e revogação do token FCM;
- envio FCM em paralelo ao OneSignal web;
- foreground, background, app encerrado e deep link;
- métricas de envio sem registrar dados sensíveis.

### 5. Liberação

- testes PHP, Flutter, integração e regressão web;
- piloto por escola/feature flag;
- monitorar `401`, `403`, falhas de envio e crashes;
- ampliar gradualmente e manter rollback apenas por configuração.

## Divisão de trabalho por agentes

1. Backend/API: autenticação, autorização, contratos e migrations.
2. Flutter Core: shell, ambientes, sessão, navegação e design system.
3. Features: módulos acadêmicos em fatias verticais.
4. Qualidade/Push: FCM, testes integrados, CI e documentação de release.

Os agentes trabalham em branches separadas e não editam o mesmo arquivo ao
mesmo tempo. Mudanças no backend entram em commits pequenos; o app só consome
endpoints publicados em staging e cobertos por contrato.
