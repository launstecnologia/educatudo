# Ambiente e integrações

> Referência completa de env vars e serviços externos. O resumo está no CLAUDE.md.

## Variáveis de ambiente críticas

| Variável | Obrigatória | Descrição |
|---|---|---|
| `MULTI_TENANT` | Sim | `true` = multi-escola, `false` = instância única |
| `MASTER_DOMAIN` | Se multi-tenant | Domínio do painel master (ex: `master.educatudo.com`) |
| `DB_HOST/NAME/USER/PASS` | Sim | Banco master (ou único banco se single-tenant) |
| `MASTER_ENCRYPTION_KEY` | Sim | Chave AES-256 para encriptar senhas de banco dos tenants — só via env, nunca no código ou banco |
| `REDIS_URL` | Produção | Cache de tenant e sessões. Obrigatório em multi-instância |
| `SESSION_DOMAIN` | Produção | `.educatudo.com` para cookies cross-subdomínio |
| `SESSION_SECURE` | Produção | `true` em HTTPS |
| `ENTRA_COMO_SECRET` | Sim | Secret para o Master entrar como admin de uma escola |
| `OPENAI_API_KEY` | Funcionalidades IA | Chat Tudinha, correção de redações, flashcards, slides |

## Integrações externas

| Serviço | Variável env | Usado em |
|---|---|---|
| OpenAI | `OPENAI_API_KEY` | Chat Tudinha, correção redação, flashcards, slides, exercícios IA |
| ElevenLabs | `ELEVENLABS_API_KEY` | Áudio de feedback de redação |
| Google Vision / Supabase | `SUPABASE_TRANSCRIBE_URL` | OCR de redação manuscrita |
| OneSignal | `ONESIGNAL_APP_ID` | Push notifications |
| Asaas | `ASAAS_API_KEY` | Pagamentos (créditos/planos) — webhook com HMAC |
| JaaS (Jitsi) | `JAAS_API_KEY` | Aulas online com gravação — webhook com HMAC |
| AWS S3 | `AWS_*` | Storage de mídia (opcional; padrão = local) |
| Google Books | sem autenticação | Busca de livros |
| Evolution API | `EVOLUTION_API_*` | WhatsApp (notificações) |

Chamadas externas de IA que demoram mais de 2s devem ser assíncronas (job em fila via `AIJobService`). Não bloquear a request HTTP.

## Requisitos locais

PHP 8.0+ (produção usa 8.2), MySQL 8, Redis, extensões `pdo_mysql`, `gd`, `mbstring`, `fileinfo`. Local roda via Docker (`docker-compose up -d`), então o PHP pode não existir no host.
