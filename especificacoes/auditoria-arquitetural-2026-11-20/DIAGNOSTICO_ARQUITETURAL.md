# Diagnóstico Arquitetural — EducaTudo

**Data:** 2026-11-20  
**Método:** auditoria somente leitura do repositório  
**Não faz:** pentest ativo, alteração de código, execução de migrations, leitura de secrets  

> Cada achado traz **severidade**, **impacto**, **evidência** e diferencia **mecanismo implementado** vs **segurança validada por teste**.

---

## 1. Veredito em uma página

O EducaTudo possui uma **base arquitetural coerente para SaaS educacional multi-instituição**: PHP puro com kernel próprio, monólito modular, um MySQL por escola, feature flags, painéis por perfil e bordas (API JWT, webhooks, crons, serviços satélite).

Os principais riscos não estão “na ideia” do multi-tenant, e sim na **qualidade de engenharia desigual**:

1. Controllers e serviços com milhares de linhas (manutenção e regressão).
2. SQL e regra de negócio ainda misturados em Controllers.
3. Migração incompleta Painel → Módulos.
4. CSRF e ownership **não** centralizados (disciplina por endpoint).
5. CI só faz deploy — testes não gateiam merge.
6. Fila de IA em MySQL + cron (simples, teto de escala).

Isso **não** invalida a arquitetura atual para o estágio do produto; exige transparência e plano de evolução priorizado.

---

## 2. Pontos fortes (comprovados)

| # | Ponto forte | Evidência | Confiança |
|---|---|---|---|
| 1 | Isolamento multi-tenant por PDO (não por filtro SQL genérico) | `bootstrap_multi_tenant.php`, `Database::setCurrentInstance()`, `DatabaseManager` | **[FATO]** |
| 2 | Fast-path Redis de tenant + fallback | `TenantResolver`, `RedisCache`, bootstrap | **[FATO]** |
| 3 | Modularização física avançada (49 manifests) | `app/Modulos/*/manifest.php`, `ModuloRegistry` | **[FATO]** |
| 4 | Feature gate por escola no Router | `FeatureGate` + `Router::dispatch` | **[FATO]** |
| 5 | Separação documentada Painel / Domínio / Módulos | `doc_sistema/mapa.md`, `estrutura.md` | **[FATO]** |
| 6 | Schema versionado com rollback | `database/migrations/` (~538 arquivos) + painel Master | **[FATO]** |
| 7 | Credenciais de DB tenant criptografadas | `MasterSecretVault` (openssl) | **[FATO]** |
| 8 | Sessão com cookie seguro + preferência Redis | `bootstrap/app.php` | **[FATO]** |
| 9 | IA longa via fila (`AIJobService`) | enqueue + cron `process_ai_jobs.php` | **[FATO]** |
| 10 | Docker Compose local reproduzível | `docker-compose.yml` (nginx, php, mysql, redis) | **[FATO]** |
| 11 | ACL admin matricial | `AdminPermissionMatrix` + AuthMiddleware | **[FATO]** |
| 12 | Documentação operacional rica para o time | `CLAUDE.md`, `.claude/docs/`, wiki `doc_sistema/` | **[FATO]** |
| 13 | App pais desacoplado (Flutter) com contrato API | `familia/` + `API/Controllers/V1` | **[FATO]** |
| 14 | Replay de sessão entre escolas invalidado | `AuthManager::sessaoConfereComEscolaAtual` + teste local em auditoria SEC-10 | **[FATO]** código + **[FATO]** teste local documentado |

---

## 3. Qualidade da engenharia — checklist com evidências

| Critério | Situação | Evidência | Nota |
|---|---|---|---|
| Separação de responsabilidades | **Parcial** | Services existem; Controllers gigantes com SQL | God-controllers |
| Reutilização | **Parcial** | Core, Infra, Compat aliases | Alto reuso do Core; pouco contrato formal |
| Nomenclatura | **Em transição** | Regra PT desde 2026-07; legado EN + Compat | Drift controlado |
| Injeção de dependências | **Ausente / mínima** | `new Service()` / singletons (`Database::getInstance`) | Sem container DI |
| Interfaces / contratos | **Raros** | Poucas Policies; sem ports hexagonais | — |
| Tratamento de erros | **Parcial** | `Logger`; handlers no bootstrap | Cobertura uniforme **[NÃO VERIFICADO]** |
| Validação de entradas | **Por endpoint** | Controllers/Services | Sem camada única |
| Testes automatizados | **Existem, cobertura limitada** | PHPUnit `backend/tests/`; Playwright `tests/` + `qa/` | Sem CI de testes |
| Análise estática | **Não evidenciada no CI** | Sem phpstan/psalm no workflow | Hooks locais `php -l` (Cursor) |
| Padronização | **Documentada** | coding-standards, admin UI specs | Adesão desigual |
| Controle de versão de banco | **Sim** | migrations + rollback + Master | — |
| Documentação técnica | **Forte** | wiki + CLAUDE + specs | — |
| CI/CD | **Só deploy** | `.github/workflows/deploy-main.yml` | Sem gate de qualidade |

### Antipadrões observados

| Antipadrão | Evidência | Severidade |
|---|---|---|
| God Controller | `BoletimConfigController` ~16k linhas; `TeacherJourneyController` ~9k; `ExamController` ~8k; `StudentController` ~8k | Alta |
| SQL em Controller | ~140 controllers com SQL/`prepare`; top: TeacherJourney, Student, Exam | Alta |
| God Service | `OpenAIService` ~4k; `AIJobService` ~2.7k | Média–Alta |
| Legado paralelo | `Painel/User/*` + shells novos `Painel/Aluno|Professor|Pais` | Média |
| Compat de aliases | Centenas de `class_alias` em maps | Média (manutenção) |
| Descrição Composer desatualizada | “Single-Tenant” no `composer.json` | Baixa (sinalização) |
| Drift PHP 8.2 docs vs 8.4 Docker | CLAUDE vs Dockerfile | Baixa–Média |

Dependências circulares formais entre namespaces **não** foram mapeadas via análise estática completa. Há **grafo direcionado denso** no cluster acadêmico (não necessariamente ciclo). **[INFERÊNCIA]**

---

## 4. Infraestrutura e escalabilidade (somente o que está no repo)

### 4.1 Stack versionada

| Componente | Evidência |
|---|---|
| Nginx 1.27 | `docker-compose.yml` |
| PHP-FPM 8.4 | `docker/php/Dockerfile` |
| MySQL 8.0 | compose local; VPS usa MySQL remoto (compose VPS sem mysql) |
| Redis 7 | compose; cache tenant + sessão preferencial |
| Storage | local `storage/` / `uploads/` ou S3 via env |
| Serviços satélite | `services/apostila-ai`, `services/educasync-solver`, `ws-server` |

### 4.2 O que a arquitetura permite escalar

| Capacidade | Avaliação | Base |
|---|---|---|
| Mais escolas (tenants) | **Boa** em princípio — DB isolado por escola | Modelo PDO |
| Mais requests web | **Horizontal possível** em PHP-FPM *se* sessão/Redis compartilhado e arquivos em S3 | Compose não prova HA |
| Cache de resolução de tenant | **Sim** | Redis TTL |
| Jobs de IA | **Limitada** — fila MySQL + cron/CLI | Sem broker dedicado |
| AVA / provas concorrentes | **Depende** de MySQL por tenant + app | **[NÃO VERIFICADO]** em carga |

### 4.3 Limitadores potenciais

| Limitador | Por quê | Severidade |
|---|---|---|
| Singleton `Database::setCurrentInstance` | Documentado como inseguro fora de PHP-FPM / async paralelo | Alta (em cenários workers long-lived) |
| Sessão em arquivo se Redis cair | Sticky session / perda de escala | Média |
| Fila IA em MySQL | Contenção, polling, sem backpressure avançado | Média |
| Controllers monólitos | Hotspots de CPU/memória em requests pesados | Média |
| Deploy único do monólito | Blast radius de release | Média |
| CI sem testes | Regressões chegam a produção | Alta (processo) |

**Não afirmar HA / balanceamento / multi-AZ em produção:** não há evidência suficiente no repositório. Compose VPS e workflow self-hosted sugerem deploy tradicional; topologia real **[NÃO VERIFICADO]**.

---

## 5. Segurança arquitetural

> Distinção obrigatória: **mecanismo no código** ≠ **controle validado por teste**.

### 5.1 Mecanismos identificados

| Controle | Implementado? | Evidência | Validado por teste nesta análise? |
|---|---|---|---|
| Autenticação web (sessão) | Sim | AuthMiddleware, AuthManager | Parcial (SEC-10 local: isolamento sessão) |
| Autenticação API JWT | Sim | ApiAuth / MobileApiAuth | HTTP JWT local inconclusivo em SEC-10 (schema) |
| Autorização por perfil | Sim | middleware + rotas | Parcial |
| ACL admin | Sim | AdminPermissionMatrix | **Não** nesta passagem |
| Feature flags | Sim | FeatureGate | **Não** (segurança) |
| Isolamento entre escolas | Sim (PDO + checagem sessão) | bootstrap + AuthManager | **Sim** (replay cookie — SEC-10) |
| Ownership aluno | Parcial / por endpoint | filters `aluno_id`, Policies | Parcial (mídia 403; chat Tudinha) |
| Ownership professor↔aluno | Policy + checks | `PoliticaAcessoProfessorAluno` | **Falha IDOR documentada** em SEC-10 (chat professor) |
| SQL Injection (prepared) | Padrão de projeto + uso amplo | Models/Controllers com `:param` | Não é prova de 100% cobertura |
| CSRF | Sim, **por controller** | `BaseController::validateCsrf` | Sem middleware global — cobertura total **[NÃO VERIFICADO]** |
| XSS | Mitigações (HtmlPurifier, escape em views — adesão variável) | Composer + práticas | **Não** auditado sistematicamente aqui |
| Segredos | Env + vault master | `.env.example`, MasterSecretVault | Conteúdo de `.env` não lido |
| Upload MIME | Regra `finfo_file` + `TENANT_SLUG` | docs/regras | Cobertura total **[NÃO VERIFICADO]** |
| Webhooks HMAC | Regra de projeto | Webhooks controllers | Por integração |
| Audit log | Sim | `Logger::logAudit`, AuditMiddleware | — |

### 5.2 Riscos de segurança (arquiteturais)

| ID | Risco | Severidade | Impacto | Evidência | Recomendação |
|---|---|---|---|---|---|
| S1 | Autorização de ownership inconsistente entre endpoints | Alta | IDOR / vazamento entre usuários da mesma escola | SEC-10: IDOR chat professor; Policies só 2 arquivos | Centralizar checagens; testes de autorização por recurso |
| S2 | CSRF não global | Média–Alta | POST sem token em endpoints esquecidos | Sem CsrfMiddleware no Router | Middleware CSRF para todas rotas web POST |
| S3 | Controllers gigantes dificultam review de segurança | Média | Bugs de auth passam despercebidos | Tamanhos de Student/Exam/Boletim | Fatiar + code owners |
| S4 | SQL em Controllers | Média | Maior chance de concatenação/erro de ownership | Grep em Controllers | Empurrar SQL para Models/Services |
| S5 | Secrets de integrações em DB + paths de JSON | Média | Superfície de vazamento operacional | OpenAI key no DB; Firebase path | Inventário + rotação + least privilege |
| S6 | CI sem security/tests gate | Média | Regressão de correções (ex. SEC-10) | Só deploy-main.yml | Pipeline com PHPUnit + smoke auth |

Referência de achados recentes: `especificacoes/auditoria-sec-10-blackphoenix-2026-11-20.md` e `especificacoes/correcao-sec-10-idor-chat-professor-2026-11-20.md`.

---

## 6. Dívida técnica e riscos (catálogo)

### 6.1 Fragilidades comprovadas

| ID | Problema | Severidade | Impacto | Evidência | Recomendação |
|---|---|---|---|---|---|
| D1 | God Controllers | Alta | Manutenção, onboarding, bugs | Arquivos 5k–16k linhas | Extrair Services; fatiar por caso de uso |
| D2 | SQL espalhado em Controllers | Alta | Regressão, segurança, testes | ~140 controllers com SQL | Camada Model/Repository obrigatória em código novo |
| D3 | Painel/User como super-módulo | Alta | Mapa mental falso; duplicação | Student/Teacher/Parent Controllers | Migrar fluxos restantes para Modulos |
| D4 | Cluster acadêmico acoplado | Alta (evolução) | Extrair módulo ≠ extrair serviço | Grafo use cruzado | Tratar como um BC; APIs internas antes de split |
| D5 | Sem DI / contratos | Média | Testabilidade baixa | Instantiação direta | Introduzir interfaces nos hotspots |
| D6 | Compat aliases | Média | Medo de delete; nomes fantasma | Compat maps | Remoção incremental com grep + testes |
| D7 | CI só deploy | Alta (processo) | Qualidade não gateia release | deploy-main.yml | PHPUnit + Playwright smoke no PR |
| D8 | Cobertura E2E pequena | Média | Regressão UX crítica | 5 specs em `tests/` | Priorizar login, boletim, prova, matrícula |
| D9 | Fila IA em MySQL | Média | Escala de jobs | AIJobService | Monitorar; avaliar broker se volume crescer |
| D10 | Drift docs PHP/versão | Baixa | Confusão operacional | CLAUDE 8.2 vs Docker 8.4 | Unificar documentação |
| D11 | composer description Single-Tenant | Baixa | Comunicação externa errada | composer.json | Corrigir texto |

### 6.2 Dependências críticas

| Dependência | Por quê crítica |
|---|---|
| MySQL (master + N tenants) | Fonte da verdade + fila IA |
| Redis | Performance de tenant + sessão preferencial |
| OpenAI | Features de IA / créditos |
| Asaas | Financeiro SaaS |
| AWS S3 (se ligado) | Arquivos em multi-instância |
| Firebase | Push do app pais |

### 6.3 Riscos de manutenção / escala / segurança (resumo)

| Tipo | Risco principal |
|---|---|
| Manutenção | Custo marginal sobe nos Controllers acadêmicos e no StudentController |
| Escala | App PHP escala; fila IA e storage local não acompanham sem Redis/S3/worker |
| Segurança | IDOR intra-tenant é o vetor mais relevante após o isolamento entre escolas |

---

## 7. Comparação: monolito modular vs microsserviços

### 7.1 Adequação atual ao domínio

Para gestão educacional multi-instituição com forte consistência acadêmica (turma ↔ prova ↔ nota ↔ boletim ↔ fechamento), o **monolito modular com DB por escola é adequado**. O modelo evita joins cross-tenant e simplifica compliance de isolamento.

### 7.2 Quando NÃO migrar para microsserviços

- Só porque “é tendência”.
- Antes de ter contratos internos, testes e ownership estáveis.
- Enquanto o cluster acadêmico compartilha regras e tabelas no mesmo schema tenant.

### 7.3 Critérios objetivos que justificariam extrair um módulo

Extrair para serviço independente **só** se a maioria for verdadeira:

1. **Ciclo de release** diferente do monólito (time/produto separado).
2. **Escala** ou **SLA** distintos (ex.: OCR gabarito, solver de grade, transcoding).
3. **Fronteira de dados** clara (poucas tabelas, API estável).
4. **Acoplamento baixo** no código (já visto em financeiro, drive, matrícula, solver).
5. **Operação** preparada (auth service-to-service, observabilidade, deploy).

**Já parcialmente extraídos (padrão saudável):** `services/educasync-solver`, `services/apostila-ai`, `ws-server`.  
**Maus candidatos hoje:** provas, boletins, fechamento, regras-academicas.

### 7.4 Alternativa recomendável

Evoluir o monólito modular: boundaries claros, anti-corruption entre módulos, fila/worker dedicados, CI forte — e extrair **apenas** cargas com necessidade operacional real.

---

## 8. Matriz executiva (para apresentação externa)

| Dimensão | Nota qualitativa | Comentário curto |
|---|---|---|
| Adequação ao negócio multi-escola | Alta | DB-per-tenant + Master |
| Clareza estrutural | Alta | Mapa Painel/Domínio/Módulos |
| Modularidade efetiva | Média | Pastas sim; BC acadêmico acoplado |
| Qualidade de código | Média–Baixa nos hotspots | God controllers |
| Operabilidade / Docker | Alta (dev) | Compose completo |
| Escalabilidade horizontal | Média (potencial) | Condicionada a Redis/S3/sessão |
| Segurança estrutural | Média–Alta | Isolamento forte; IDOR intra-tenant a vigiar |
| Prontidão para microsserviços | Baixa no núcleo | Alta só em satélites |
| Transparência técnica | Alta | Docs + esta auditoria |

---

*Complementa: `ARQUITETURA_TECNICA_EDUCATUDO.md` · `MAPA_MODULOS_EDUCATUDO.md` · `PLANO_EVOLUCAO_ARQUITETURAL.md`*
