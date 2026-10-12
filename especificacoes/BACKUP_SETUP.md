# Guia Rápido de Configuração de Backup

## Passo a Passo

### 1. Instalar Dependências

```bash
composer install
```

### 2. Configurar Google Drive API

1. Acesse: https://console.cloud.google.com/
2. Crie/selecione um projeto
3. Ative a **Google Drive API**
4. Crie uma **Service Account**:
   - Vá em "APIs & Services" > "Credentials"
   - "Create Credentials" > "Service Account"
   - Crie a conta e baixe a chave JSON

5. Salve o arquivo JSON em:
   ```
   storage/backup-credentials.json
   ```

6. **IMPORTANTE - Configurar Acesso ao Google Drive (OBRIGATÓRIO)**:
   
   ⚠️ **Service Accounts não têm quota de armazenamento própria!** Você DEVE usar uma das opções:
   
   ## Opção 1 — Usar Shared Drive (Recomendado) ⭐⭐⭐⭐⭐
   
   **É a solução mais usada em sistemas, bots e automações.**
   
   **Passo 1 - Criar Shared Drive:**
   - No Google Drive (conta humana), crie um **Drive Compartilhado**
   - Nome sugerido: "EducaTudo Backups"
   
   **Passo 2 - Adicionar Service Account:**
   - No Shared Drive criado, clique em "Gerenciar membros"
   - Adicione o email da Service Account (encontrado no JSON como `client_email`)
   - Permissão: **Editor** ou **Gerente**
   
   **Passo 3 - Obter ID do Shared Drive:**
   - Abra o Shared Drive no navegador
   - O ID está na URL: `https://drive.google.com/drive/folders/0AXXXXXXXXXXXXXXXXX`
   - Copie o ID (a parte após `/folders/`)
   
   **Passo 4 - Adicionar shared_drive_id no JSON:**
   - Abra o arquivo `storage/backup-credentials.json`
   - Adicione a propriedade `shared_drive_id`:
     ```json
     {
       "type": "service_account",
       "project_id": "...",
       "private_key_id": "...",
       "private_key": "...",
       "client_email": "...",
       "client_id": "...",
       "auth_uri": "...",
       "token_uri": "...",
       "auth_provider_x509_cert_url": "...",
       "client_x509_cert_url": "...",
       "universe_domain": "googleapis.com",
       "shared_drive_id": "0AXXXXXXXXXXXXXXXXX"
     }
     ```
   
   **Pronto!** O sistema criará automaticamente a pasta `EducaTudo_Backups` dentro do Shared Drive.
   
   ---
   
   ## Opção 2 — Usar OAuth Delegation (Domain-Wide Delegation)
   
   **Use apenas se não tiver acesso a Shared Drives.**
   
   **Passo 1 - Adicionar delegated_user no JSON:**
   - Abra o arquivo `storage/backup-credentials.json`
   - Adicione a propriedade `delegated_user` com o email de um usuário real:
     ```json
     {
       "type": "service_account",
       ...
       "delegated_user": "admin@seudominio.com"
     }
     ```
   - Use um email de um usuário real do Google Workspace que tem quota de armazenamento
   
   **Passo 2 - Habilitar Domain-Wide Delegation:**
   - No Google Cloud Console, vá em "APIs & Services" > "Credentials"
   - Clique na Service Account criada
   - Na seção "Domain-wide delegation", clique em "Enable Google Workspace Domain-wide Delegation"
   - Anote o "Client ID" da Service Account
   
   **Passo 3 - Configurar no Admin Console:**
   - Acesse: https://admin.google.com/
   - Vá em "Segurança" > "Controles de API" > "Domain-wide delegation"
   - Clique em "Adicionar novo" e insira:
     - **Client ID**: (o ID da Service Account)
     - **Escopos OAuth**: `https://www.googleapis.com/auth/drive.file`
   - Salve
   
   **Passo 4 - Criar pasta no Google Drive:**
   - Faça login no Google Drive com o email do `delegated_user`
   - Crie a pasta `EducaTudo_Backups`
   - A Service Account acessará esta pasta como o usuário delegado

### 3. Testar Backup

```bash
php cron/backup.php
```

### 4. Configurar Automação

#### Linux (Cron)

```bash
crontab -e
```

Adicione:
```cron
0 2 * * * cd /caminho/para/educatudo && /usr/bin/php cron/backup.php >> storage/logs/backup.log 2>&1
```

#### Windows (Task Scheduler)

1. Abra "Agendador de Tarefas"
2. Crie nova tarefa:
   - Nome: EducaTudo Backup
   - Gatilho: Diário às 02:00
   - Ação: Executar `php.exe cron/backup.php`

### 5. Verificar

- Backup criado em `storage/backups/`
- Arquivo no Google Drive na pasta `EducaTudo_Backups`
- Logs sem erros

## Próximos Passos

- Leia a documentação completa: `docs/BACKUP_POLICY.md`
- Configure teste de restauração mensal
- Configure monitoramento e alertas

