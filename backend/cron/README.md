# 📅 Scripts de Cron Job - EducaTudo

Este diretório contém scripts que devem ser executados periodicamente via cron job.

## 🔧 Scripts Disponíveis

### 1. `atualizar_status_jornadas.php`

Atualiza automaticamente o status das jornadas baseado nas datas de início e fim.

**O que faz:**
- Calcula o status de cada jornada baseado na data atual:
  - **Aguardando**: quando a data atual < data_inicio
  - **Em Andamento**: quando a data atual está entre data_inicio e data_fim
  - **Concluído**: quando a data atual > data_fim
- Atualiza o campo `estrutura` (JSON) com o status calculado

**Frequência recomendada:** Diariamente às 00:00 ou a cada hora

### 2. `llm_usage_daily.php`

Registro diário de custos de IA: lê do banco as conversas e mensagens de IA do dia anterior, estima tokens e custo, e registra/atualiza em `llm_usage_log`.

**O que faz:**
- Processa a data do **dia anterior** (quando roda à meia-noite, processa o dia que acabou)
- Lê `professor_ai_mensagens` (chat professor) e `mensagens_chat` (is_ia=1, chat aluno/Tudinha) daquela data
- Estima tokens e custo por mensagem e insere em `llm_usage_log` com `source = 'computed'`
- Remove registros `computed`/`backfill` daquela data antes de reinserir (evita duplicar)

**Frequência recomendada:** Diariamente às 00:00

**Exemplo de cron (meia-noite):**
```
0 0 * * * /usr/bin/php /caminho/para/projeto/cron/llm_usage_daily.php
```

**Testar com data específica (CLI):**
```bash
php cron/llm_usage_daily.php 2026-02-05
```

**Logs:** `storage/logs/llm_usage_daily_YYYY-MM-DD.log`

### 2b. `master_dashboard_kpis.php`

Agrega KPIs do painel Master (logins bem-sucedidos, jornadas/provas criadas, uso por módulo) de todos os tenants e grava snapshot em `master_dashboard_kpis` / `master_dashboard_kpis_escolas` no banco master.

**O que faz:**
- Itera escolas ativas via `CronMultiTenantHelper`
- Conta `alunos_sessoes_acesso` com gap de 10 min entre logins do mesmo aluno (reentradas próximas não contam), `jornadas`, `provas`
- Proxy de uso: alunos distintos em jornadas, provas, redações, AVA e Tudinha
- Upsert no master (dashboard não consulta N bancos em tempo real)

**Frequência recomendada:** Diariamente às 00:00

```
0 0 * * * /usr/bin/php /caminho/projeto/src/cron/master_dashboard_kpis.php >> /caminho/projeto/src/storage/logs/cron_master_dashboard_kpis.log 2>&1
```

**Rodar manualmente:** `php src/cron/master_dashboard_kpis.php`

**Pré-requisito:** migration master `2026_07_09_master_dashboard_kpis_master.sql`

**Logs:** `storage/logs/cron_master_dashboard_kpis.log`

### 2c. `asaas_cancelar_pendentes.php`

Cancela no Asaas (`DELETE /v3/payments/{id}`) as compras de TudiCoins com status `pending` há mais de **1 hora** e marca `compras_creditos.status = cancelled`. O aluno precisa gerar um novo QR para comprar de novo.

**O que faz:**
- Lê API key do Master (`asaas_master_config`)
- Itera escolas ativas (tenant via `MasterTenantConnection`)
- Busca `pending` com `created_at` ≥ 1h
- Se já estiver pago no Asaas, **não cancela** (deixa o reconcile creditar)
- Sem `asaas_payment_id`: só marca `cancelled` no banco

**Frequência recomendada:** a cada 15 minutos

```
*/15 * * * * /usr/bin/php /caminho/projeto/src/cron/asaas_cancelar_pendentes.php >> /caminho/projeto/src/storage/logs/cron_asaas_cancelar_pendentes.log 2>&1
```

**Rodar manualmente:**
```bash
php src/cron/asaas_cancelar_pendentes.php
php src/cron/asaas_cancelar_pendentes.php 60   # minutos (opcional)
```

**HTTP (mesma key do reconcile):**
```
GET /master/asaas/cancelar-pendentes-cron?key=ASAAS_RECONCILE_KEY&minutos=60
```

**Logs:** `storage/logs/cron_asaas_cancelar_pendentes.log`

### 2d. `process_ai_jobs.php`

Processa a fila `ai_jobs` de cada escola (até 5 jobs por tenant por execução) e grava histórico no banco master (`cron_execucoes` / `cron_execucoes_escolas`).

**O que faz:**
- Itera escolas ativas via `CronMultiTenantHelper::runWithReport`
- Chama `AIJobService::processNext()` e `cleanup()`
- Registra início/fim, escolas OK/erro/puladas e jobs processados

**Frequência recomendada:** a cada minuto

```
* * * * * /usr/bin/php /caminho/projeto/backend/cron/process_ai_jobs.php >> /caminho/projeto/backend/storage/logs/ai_jobs_cron.log 2>&1
```

### 2e. `presenca_corte.php`

Gestão de Presença: após o horário de corte da 1ª aula da turma, marca falta nos alunos sem entrada no dia e recalcula `faltas_lancamentos` dos eventos de origem Diário (se a escola ligou a consolidação).

**Frequência recomendada:** a cada 15 minutos no horário escolar

```
*/15 6-20 * * 1-6 /usr/bin/php /caminho/projeto/backend/cron/presenca_corte.php >> /caminho/projeto/backend/storage/logs/presenca_corte.log 2>&1
```

**Pré-requisito:** migration master `2026_08_10_cron_execucoes_master.sql`

**Painel:** Master → **Fila IA** (`/master/fila-ia`) — fila multi-escola + detalhe do job (aluno/professor) + histórico do cron.

**Logs:** `storage/logs/ai_jobs_cron.log` + tabelas `cron_execucoes*`

## ⚙️ Como Configurar o Cron Job

### Opção 1: Via cPanel

1. Acesse o cPanel do seu servidor
2. Vá em **Cron Jobs** ou **Agendador de Tarefas**
3. Adicione um novo cron job com as seguintes configurações:

```
Frequência: Diariamente
Hora: 00:00
Comando: /usr/bin/php /caminho/completo/para/projeto/cron/atualizar_status_jornadas.php
```

**Exemplo de caminho completo:**
```
/usr/bin/php /home/usuario/public_html/educatudo/cron/atualizar_status_jornadas.php
```

### Opção 2: Via SSH (Linha de Comando)

1. Acesse o servidor via SSH
2. Execute o comando:
```bash
crontab -e
```

3. Adicione uma das seguintes linhas:

**Para executar diariamente às 00:00:**
```
0 0 * * * /usr/bin/php /caminho/completo/para/projeto/cron/atualizar_status_jornadas.php >> /caminho/completo/para/projeto/storage/logs/cron_output.log 2>&1
```

**Para executar a cada hora:**
```
0 * * * * /usr/bin/php /caminho/completo/para/projeto/cron/atualizar_status_jornadas.php >> /caminho/completo/para/projeto/storage/logs/cron_output.log 2>&1
```

**Para executar a cada 6 horas:**
```
0 */6 * * * /usr/bin/php /caminho/completo/para/projeto/cron/atualizar_status_jornadas.php >> /caminho/completo/para/projeto/storage/logs/cron_output.log 2>&1
```

### Opção 3: Via URL (Web Cron)

Se o servidor não permitir cron jobs, você pode usar um serviço de web cron:

1. Acesse serviços como:
   - https://cron-job.org
   - https://www.easycron.com
   - https://www.setcronjob.com

2. Configure uma URL para chamar:
```
https://seudominio.com/educatudo/cron/atualizar_status_jornadas.php
```

3. Configure a frequência desejada

## 📊 Logs

Os logs são salvos em:
```
storage/logs/cron_jornadas.log
```

Cada execução registra:
- Data e hora da execução
- Total de jornadas processadas
- Status de cada jornada atualizada
- Resumo final com contadores

## 🔍 Verificar se o Cron está Funcionando

1. Execute manualmente o script:
```bash
php cron/atualizar_status_jornadas.php
```

2. Verifique os logs:
```bash
tail -f storage/logs/cron_jornadas.log
```

3. Verifique no banco de dados se o campo `estrutura` foi atualizado com `status_jornada`

## ⚠️ Notas Importantes

- O script atualiza apenas jornadas com status 'ativa' ou 'pausada'
- Jornadas sem datas (data_inicio ou data_fim) são ignoradas
- O status é calculado baseado na data atual do servidor (timezone: America/Sao_Paulo)
- O script é seguro para executar múltiplas vezes (idempotente)

## 🛠️ Solução de Problemas

### Erro: "Database connection failed"
- Verifique as credenciais em `config/app.php`
- Verifique se o banco de dados está acessível

### Erro: "Permission denied"
- Verifique as permissões do arquivo:
```bash
chmod +x cron/atualizar_status_jornadas.php
```

### Cron não está executando
- Verifique se o caminho do PHP está correto:
```bash
which php
```
- Verifique os logs do cron:
```bash
grep CRON /var/log/syslog
```
### Resumo do Dashboard (Jornadas)

Para evitar consultas pesadas a cada acesso do dashboard admin, o resumo de jornadas pode ser pré-calculado 2x ao dia:

```bash
0 0,12 * * * /usr/bin/php /caminho/para/projeto/src/cron/dashboard_jornadas_resumo.php >> /caminho/para/projeto/src/storage/logs/cron_dashboard_jornadas_resumo.log 2>&1
```

