<?php

namespace App\Modulos\Matricula\Services;

/**
 * Integração ZapSign (assinatura eletrônica de contratos de matrícula).
 * Credenciais por tenant em config_layout; token de API criptografado.
 */
class ZapSignService
{
    public const API_BASE_PRODUCAO = 'https://api.zapsign.com.br/api/v1';
    public const API_BASE_SANDBOX = 'https://sandbox.api.zapsign.com.br/api/v1';
    public const APP_BASE_PRODUCAO = 'https://app.zapsign.com.br';
    public const APP_BASE_SANDBOX = 'https://sandbox.app.zapsign.com.br';

    /** @deprecated Use apiBaseUrl() */
    public const API_BASE = self::API_BASE_PRODUCAO;

    private const KEY_ATIVO = 'zapsign_ativo';
    private const KEY_TOKEN = 'zapsign_api_token_encrypted';
    private const KEY_WEBHOOK = 'zapsign_webhook_token';
    private const KEY_EMAIL = 'zapsign_enviar_email';
    private const KEY_AMBIENTE = 'zapsign_ambiente';
    private const KEY_WEBHOOK_BASE = 'zapsign_webhook_base_url';

    private $db;

    public function __construct(?\Database $db = null)
    {
        $this->db = $db ?? \Database::getInstance();
    }

    public function estaAtivo(): bool
    {
        if ($this->lerConfig(self::KEY_ATIVO, '0') !== '1') {
            return false;
        }
        return $this->obterApiToken() !== '';
    }

    public function ambiente(): string
    {
        $amb = strtolower(trim($this->lerConfig(self::KEY_AMBIENTE, 'sandbox')));
        return $amb === 'production' ? 'production' : 'sandbox';
    }

    public function apiBaseUrl(): string
    {
        return $this->ambiente() === 'production'
            ? self::API_BASE_PRODUCAO
            : self::API_BASE_SANDBOX;
    }

    public function appBaseUrl(): string
    {
        return $this->ambiente() === 'production'
            ? self::APP_BASE_PRODUCAO
            : self::APP_BASE_SANDBOX;
    }

    /**
     * Base pública para o webhook (ngrok / domínio). Sem path.
     */
    public function webhookBaseUrl(): string
    {
        $custom = trim($this->lerConfig(self::KEY_WEBHOOK_BASE, ''));
        if ($custom !== '') {
            return rtrim($custom, '/');
        }
        return rtrim((string) URL, '/');
    }

    public function webhookUrl(): string
    {
        $slug = defined('TENANT_SLUG') ? trim((string) TENANT_SLUG) : '';
        $path = ($slug !== '')
            ? '/webhooks/zapsign/' . rawurlencode($slug)
            : '/webhooks/zapsign';
        return $this->webhookBaseUrl() . $path;
    }

    /**
     * @return array{
     *   ativo:bool,
     *   ambiente:string,
     *   api_token_mascarado:string,
     *   tem_api_token:bool,
     *   webhook_token:string,
     *   webhook_base_url:string,
     *   enviar_email:bool,
     *   webhook_url:string
     * }
     */
    public function obterConfigPublica(): array
    {
        $token = $this->obterApiToken();
        $webhook = $this->lerConfig(self::KEY_WEBHOOK, '');
        $baseCustom = trim($this->lerConfig(self::KEY_WEBHOOK_BASE, ''));
        return [
            'ativo' => $this->lerConfig(self::KEY_ATIVO, '0') === '1',
            'ambiente' => $this->ambiente(),
            'tem_api_token' => $token !== '',
            'api_token_mascarado' => $this->mascarar($token),
            'webhook_token' => $webhook,
            'webhook_base_url' => $baseCustom,
            'enviar_email' => $this->lerConfig(self::KEY_EMAIL, '1') === '1',
            'webhook_url' => $this->webhookUrl(),
        ];
    }

    /**
     * @param array{
     *   ativo?:bool|int|string,
     *   ambiente?:string,
     *   api_token?:string,
     *   webhook_base_url?:string,
     *   enviar_email?:bool|int|string,
     *   regenerar_webhook?:bool|int|string
     * } $input
     */
    public function salvarConfig(array $input): array
    {
        $ativo = !empty($input['ativo']) ? '1' : '0';
        $enviarEmail = !empty($input['enviar_email']) ? '1' : '0';
        $ambiente = strtolower(trim((string) ($input['ambiente'] ?? 'sandbox')));
        if ($ambiente !== 'production') {
            $ambiente = 'sandbox';
        }

        $this->gravarConfig(self::KEY_ATIVO, $ativo);
        $this->gravarConfig(self::KEY_EMAIL, $enviarEmail);
        $this->gravarConfig(self::KEY_AMBIENTE, $ambiente);

        $baseUrl = $this->normalizarWebhookBaseUrl((string) ($input['webhook_base_url'] ?? ''));
        $this->gravarConfig(self::KEY_WEBHOOK_BASE, $baseUrl);

        $apiToken = trim((string) ($input['api_token'] ?? ''));
        if ($apiToken !== '') {
            require_once BASE_PATH . '/app/Core/MasterSecretVault.php';
            $enc = \MasterSecretVault::encrypt($apiToken);
            if ($enc === null || $enc === '') {
                throw new \RuntimeException('Falha ao criptografar o token da API.');
            }
            $this->gravarConfig(self::KEY_TOKEN, $enc);
        }

        // Token do webhook: só gera/regenera no servidor (não aceita valor arbitrário do POST).
        $atualWebhook = $this->lerConfig(self::KEY_WEBHOOK, '');
        if (!empty($input['regenerar_webhook']) || $atualWebhook === '') {
            $this->gravarConfig(self::KEY_WEBHOOK, bin2hex(random_bytes(24)));
        }

        return $this->obterConfigPublica();
    }

    public function validarWebhookToken(?string $sent): bool
    {
        $esperado = $this->lerConfig(self::KEY_WEBHOOK, '');
        if ($esperado === '' || $sent === null || $sent === '') {
            return false;
        }
        return hash_equals($esperado, $sent);
    }

    /**
     * Envia o PDF do contrato de matrícula para assinatura na ZapSign.
     *
     * @param array<string,mixed> $enrollment
     * @return array{ok:bool,message:string,doc_token?:string,sign_url?:string,signer_token?:string}
     */
    public function enviarContratoMatricula(array $enrollment, string $pdfRelativePath): array
    {
        if (!$this->estaAtivo()) {
            return ['ok' => false, 'message' => 'ZapSign não está ativo ou sem token.'];
        }

        $abs = $this->resolverPdfAbsoluto($pdfRelativePath);
        if ($abs === null || !is_readable($abs)) {
            return ['ok' => false, 'message' => 'PDF do contrato não encontrado.'];
        }

        $bin = file_get_contents($abs);
        if ($bin === false || $bin === '') {
            return ['ok' => false, 'message' => 'PDF do contrato vazio ou ilegível.'];
        }

        $id = (int) ($enrollment['id'] ?? 0);
        $aluno = trim((string) ($enrollment['aluno_nome'] ?? 'Aluno'));
        $respNome = trim((string) ($enrollment['resp_nome'] ?? ''));
        if ($respNome === '') {
            return ['ok' => false, 'message' => 'Responsável sem nome — necessário para a ZapSign.'];
        }

        $signer = [
            'name' => $respNome,
            'auth_mode' => 'assinaturaTela',
        ];

        $email = trim((string) ($enrollment['resp_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signer['email'] = $email;
            $signer['lock_email'] = true;
        }

        $fone = $this->normalizarTelefone((string) ($enrollment['resp_telefone'] ?? ''));
        if ($fone !== null) {
            $signer['phone_country'] = $fone['country'];
            $signer['phone_number'] = $fone['number'];
            $signer['lock_phone'] = true;
        }

        $enviarEmail = $this->lerConfig(self::KEY_EMAIL, '1') === '1'
            && !empty($signer['email']);

        $payload = [
            'name' => 'Contrato matrícula #' . $id . ' — ' . mb_substr($aluno, 0, 80),
            'base64_pdf' => base64_encode($bin),
            'lang' => 'pt-br',
            'external_id' => 'enrollment:' . $id,
            'folder_path' => '/EducaTudo/Matriculas/',
            'disable_signer_emails' => !$enviarEmail,
            'send_automatic_email' => $enviarEmail,
            'signers' => [$signer],
        ];

        try {
            $resp = $this->request('POST', '/docs/', $payload);
        } catch (\Throwable $e) {
            error_log('[ZapSignService::enviarContratoMatricula] ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $docToken = (string) ($resp['token'] ?? '');
        $signers = (!empty($resp['signers']) && is_array($resp['signers'])) ? $resp['signers'] : [];
        $first = $signers[0] ?? [];
        $signerToken = (string) ($first['token'] ?? '');
        $signUrl = trim((string) ($first['sign_url'] ?? ''));
        if ($signUrl === '' && $signerToken !== '') {
            $signUrl = $this->appBaseUrl() . '/verificar/' . $signerToken;
        }

        if ($docToken === '') {
            return ['ok' => false, 'message' => 'ZapSign não retornou token do documento.'];
        }

        require_once __DIR__ . '/../Models/MatriculaProcesso.php';
        $model = new \App\Modulos\Matricula\Models\MatriculaProcesso($this->db);
        $model->update($id, [
            'zapsign_doc_token' => $docToken,
            'zapsign_signer_token' => $signerToken !== '' ? $signerToken : null,
            'zapsign_sign_url' => $signUrl !== '' ? mb_substr($signUrl, 0, 500) : null,
            'zapsign_status' => (string) ($resp['status'] ?? 'pending'),
            'zapsign_enviado_em' => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok' => true,
            'message' => 'Documento enviado à ZapSign.',
            'doc_token' => $docToken,
            'sign_url' => $signUrl,
            'signer_token' => $signerToken,
        ];
    }

    /**
     * Envia PDF de uma instância de contrato (regra) à ZapSign.
     *
     * @param array<string,mixed> $enrollment
     * @return array{ok:bool,message:string,doc_token?:string,sign_url?:string}
     */
    public function enviarContratoProcesso(
        array $enrollment,
        string $pdfRelativePath,
        int $processoContratoId,
        string $nomeDocumento = 'Contrato'
    ): array {
        if (!$this->estaAtivo()) {
            return ['ok' => false, 'message' => 'ZapSign não está ativo ou sem token.'];
        }
        if ($processoContratoId <= 0) {
            // Fallback legado: atualiza só o processo
            return $this->enviarContratoMatricula($enrollment, $pdfRelativePath);
        }

        $abs = $this->resolverPdfAbsoluto($pdfRelativePath);
        if ($abs === null || !is_readable($abs)) {
            return ['ok' => false, 'message' => 'PDF do contrato não encontrado.'];
        }
        $bin = file_get_contents($abs);
        if ($bin === false || $bin === '') {
            return ['ok' => false, 'message' => 'PDF do contrato vazio ou ilegível.'];
        }

        $id = (int) ($enrollment['id'] ?? 0);
        $aluno = trim((string) ($enrollment['aluno_nome'] ?? 'Aluno'));
        $respNome = trim((string) ($enrollment['resp_nome'] ?? ''));
        if ($respNome === '') {
            return ['ok' => false, 'message' => 'Responsável sem nome — necessário para a ZapSign.'];
        }

        $signer = [
            'name' => $respNome,
            'auth_mode' => 'assinaturaTela',
        ];
        $email = trim((string) ($enrollment['resp_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signer['email'] = $email;
            $signer['lock_email'] = true;
        }
        $fone = $this->normalizarTelefone((string) ($enrollment['resp_telefone'] ?? ''));
        if ($fone !== null) {
            $signer['phone_country'] = $fone['country'];
            $signer['phone_number'] = $fone['number'];
            $signer['lock_phone'] = true;
        }

        $enviarEmail = $this->lerConfig(self::KEY_EMAIL, '1') === '1' && !empty($signer['email']);
        $payload = [
            'name' => mb_substr($nomeDocumento . ' #' . $id . ' — ' . $aluno, 0, 120),
            'base64_pdf' => base64_encode($bin),
            'lang' => 'pt-br',
            'external_id' => 'enrollment:' . $id . ':contrato:' . $processoContratoId,
            'folder_path' => '/EducaTudo/Matriculas/',
            'disable_signer_emails' => !$enviarEmail,
            'send_automatic_email' => $enviarEmail,
            'signers' => [$signer],
        ];

        try {
            $resp = $this->request('POST', '/docs/', $payload);
        } catch (\Throwable $e) {
            error_log('[ZapSignService::enviarContratoProcesso] ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $docToken = (string) ($resp['token'] ?? '');
        $signers = (!empty($resp['signers']) && is_array($resp['signers'])) ? $resp['signers'] : [];
        $first = $signers[0] ?? [];
        $signerToken = (string) ($first['token'] ?? '');
        $signUrl = trim((string) ($first['sign_url'] ?? ''));
        if ($signUrl === '' && $signerToken !== '') {
            $signUrl = $this->appBaseUrl() . '/verificar/' . $signerToken;
        }
        if ($docToken === '') {
            return ['ok' => false, 'message' => 'ZapSign não retornou token do documento.'];
        }

        $status = (string) ($resp['status'] ?? 'pending');
        $enviadoEm = date('Y-m-d H:i:s');
        try {
            $this->db->query(
                'UPDATE matricula_processos_contratos SET
                    zapsign_doc_token = ?, zapsign_signer_token = ?, zapsign_sign_url = ?,
                    zapsign_status = ?, zapsign_enviado_em = ?, status = ?
                 WHERE id = ? AND enrollment_id = ?',
                [
                    $docToken,
                    $signerToken !== '' ? $signerToken : null,
                    $signUrl !== '' ? mb_substr($signUrl, 0, 500) : null,
                    $status,
                    $enviadoEm,
                    'enviado',
                    $processoContratoId,
                    $id,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[ZapSignService::enviarContratoProcesso] update instancia: ' . $e->getMessage());
        }

        // Se for o contrato principal (tipo matricula), espelha no processo
        try {
            $inst = $this->db->fetch(
                'SELECT tipo FROM matricula_processos_contratos WHERE id = ?',
                [$processoContratoId]
            );
            if (($inst['tipo'] ?? '') === 'matricula') {
                require_once __DIR__ . '/../Models/MatriculaProcesso.php';
                $model = new \App\Modulos\Matricula\Models\MatriculaProcesso($this->db);
                $model->update($id, [
                    'zapsign_doc_token' => $docToken,
                    'zapsign_signer_token' => $signerToken !== '' ? $signerToken : null,
                    'zapsign_sign_url' => $signUrl !== '' ? mb_substr($signUrl, 0, 500) : null,
                    'zapsign_status' => $status,
                    'zapsign_enviado_em' => $enviadoEm,
                ]);
            }
        } catch (\Throwable $e) {
            // ok
        }

        return [
            'ok' => true,
            'message' => 'Documento enviado à ZapSign.',
            'doc_token' => $docToken,
            'sign_url' => $signUrl,
            'signer_token' => $signerToken,
        ];
    }

    /**
     * Processa webhook doc_signed (documento totalmente assinado).
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool,ignored?:bool,message?:string,enrollment_id?:int}
     */
    public function processarWebhookAssinatura(array $payload): array
    {
        $event = (string) ($payload['event_type'] ?? $payload['event'] ?? '');
        $status = strtolower(trim((string) ($payload['status'] ?? '')));

        // Fail-closed: só processa doc_signed com status signed (todos assinaram).
        if ($event !== 'doc_signed') {
            return ['ok' => true, 'ignored' => true, 'message' => 'event_' . ($event !== '' ? $event : 'empty')];
        }
        if ($status !== 'signed') {
            return ['ok' => true, 'ignored' => true, 'message' => 'status_' . ($status !== '' ? $status : 'empty')];
        }

        $docToken = trim((string) ($payload['token'] ?? ''));
        if ($docToken === '') {
            return ['ok' => false, 'message' => 'missing_doc_token'];
        }

        require_once __DIR__ . '/../Models/MatriculaProcesso.php';
        require_once __DIR__ . '/MatriculaProcessoService.php';
        $model = new \App\Modulos\Matricula\Models\MatriculaProcesso($this->db);

        // Preferência: instância multi-contrato
        $instancia = null;
        try {
            $instancia = $this->db->fetch(
                'SELECT * FROM matricula_processos_contratos WHERE zapsign_doc_token = ? LIMIT 1',
                [$docToken]
            ) ?: null;
        } catch (\Throwable $e) {
            $instancia = null;
        }

        $enrollment = null;
        if ($instancia) {
            $enrollment = $model->findById((int) $instancia['enrollment_id']);
        }
        if (!$enrollment) {
            $enrollment = $model->findByZapSignDocToken($docToken);
        }
        if (!$enrollment) {
            return ['ok' => false, 'message' => 'enrollment_not_found'];
        }

        // Confirma vínculo: external_id (se vier) deve bater com o id do processo.
        $externalId = trim((string) ($payload['external_id'] ?? ''));
        if ($externalId !== '') {
            $eid = (int) $enrollment['id'];
            $okExt = hash_equals('enrollment:' . $eid, $externalId)
                || preg_match('/^enrollment:' . $eid . '(:contrato:\\d+)?$/', $externalId) === 1;
            if (!$okExt) {
                return ['ok' => false, 'message' => 'external_id_mismatch'];
            }
        }

        $who = (!empty($payload['signer_who_signed']) && is_array($payload['signer_who_signed']))
            ? $payload['signer_who_signed']
            : [];
        $nome = trim((string) ($who['name'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($enrollment['resp_nome'] ?? 'Responsável'));
        }

        if ($instancia) {
            try {
                $this->db->query(
                    'UPDATE matricula_processos_contratos SET
                        status = ?, assinado_em = NOW(), assinante_nome = ?, zapsign_status = ?
                     WHERE id = ?',
                    ['assinado', mb_substr($nome, 0, 255), 'signed', (int) $instancia['id']]
                );
            } catch (\Throwable $e) {
                error_log('[ZapSignService::processarWebhookAssinatura] instancia: ' . $e->getMessage());
            }
            // Só efetiva a matrícula se for o contrato principal
            if (($instancia['tipo'] ?? '') !== 'matricula') {
                return [
                    'ok' => true,
                    'message' => 'assinatura_contrato_secundario',
                    'enrollment_id' => (int) $enrollment['id'],
                ];
            }
        }

        if (!empty($enrollment['assinado_em'])) {
            $model->update((int) $enrollment['id'], [
                'zapsign_status' => 'signed',
            ]);
            return [
                'ok' => true,
                'ignored' => true,
                'message' => 'already_signed',
                'enrollment_id' => (int) $enrollment['id'],
            ];
        }

        $ip = 'zapsign:webhook';
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = 'zapsign:' . substr((string) $_SERVER['REMOTE_ADDR'], 0, 40);
        }

        $svc = new MatriculaProcessoService($this->db);
        $svc->registrarAssinatura($enrollment, $ip, $nome);

        $model->update((int) $enrollment['id'], [
            'zapsign_status' => 'signed',
        ]);

        return [
            'ok' => true,
            'message' => 'assinatura_registrada',
            'enrollment_id' => (int) $enrollment['id'],
        ];
    }

    /**
     * Consulta o documento na ZapSign e, se já estiver signed, registra a assinatura.
     *
     * @param array<string,mixed> $enrollment
     * @return array{ok:bool,message:string,enrollment_id?:int}
     */
    public function sincronizarDocumentoMatricula(array $enrollment): array
    {
        $docToken = trim((string) ($enrollment['zapsign_doc_token'] ?? ''));
        if ($docToken === '') {
            return ['ok' => false, 'message' => 'Processo sem documento ZapSign.'];
        }
        if (!$this->estaAtivo()) {
            return ['ok' => false, 'message' => 'ZapSign inativo ou sem token.'];
        }
        if (!empty($enrollment['assinado_em'])) {
            return [
                'ok' => true,
                'message' => 'Já estava assinado no EducaTudo.',
                'enrollment_id' => (int) $enrollment['id'],
            ];
        }

        try {
            $doc = $this->request('GET', '/docs/' . rawurlencode($docToken) . '/');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $status = strtolower(trim((string) ($doc['status'] ?? '')));
        if ($status !== 'signed') {
            require_once __DIR__ . '/../Models/MatriculaProcesso.php';
            $model = new \App\Modulos\Matricula\Models\MatriculaProcesso($this->db);
            $model->update((int) $enrollment['id'], [
                'zapsign_status' => $status !== '' ? $status : 'pending',
            ]);
            return [
                'ok' => false,
                'message' => 'Na ZapSign o status ainda é "' . ($status !== '' ? $status : 'pendente') . '".',
            ];
        }

        $doc['event_type'] = 'doc_signed';
        if (empty($doc['external_id'])) {
            $doc['external_id'] = 'enrollment:' . (int) $enrollment['id'];
        }
        return $this->processarWebhookAssinatura($doc);
    }

    /**
     * Registra/atualiza webhook na ZapSign apontando para a URL pública (ngrok).
     *
     * @return array{ok:bool,message:string}
     */
    public function registrarWebhookNaZapSign(): array
    {
        if ($this->obterApiToken() === '') {
            return ['ok' => false, 'message' => 'Salve o token da API antes.'];
        }
        $webhookToken = $this->lerConfig(self::KEY_WEBHOOK, '');
        if ($webhookToken === '') {
            $webhookToken = bin2hex(random_bytes(24));
            $this->gravarConfig(self::KEY_WEBHOOK, $webhookToken);
        }
        $url = $this->webhookUrl();
        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            return [
                'ok' => false,
                'message' => 'Informe a URL pública do ngrok antes de registrar o webhook.',
            ];
        }

        $headers = [
            ['name' => 'Authorization', 'value' => 'Bearer ' . $webhookToken],
            ['name' => 'ngrok-skip-browser-warning', 'value' => 'true'],
            ['name' => 'Content-Type', 'value' => 'application/json'],
        ];
        // Bootstrap multi-tenant resolve por X-Tenant quando o Host é ngrok/público.
        $slug = defined('TENANT_SLUG') ? trim((string) TENANT_SLUG) : '';
        if ($slug !== '') {
            $headers[] = ['name' => 'X-Tenant', 'value' => $slug];
        }

        $payload = [
            'url' => $url,
            'type' => 'doc_signed',
            'headers' => $headers,
        ];

        try {
            $this->request('POST', '/user/company/webhook/', $payload);
            return [
                'ok' => true,
                'message' => 'Webhook registrado na ZapSign: ' . $url,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $token = $this->obterApiToken();
        if ($token === '') {
            throw new \RuntimeException('Token da API ZapSign não configurado.');
        }

        $url = $this->apiBaseUrl() . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Falha ao iniciar cURL.');
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ];
        if ($method !== 'GET' && $method !== 'HEAD') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Erro de rede ZapSign: ' . $err);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Resposta inválida da ZapSign (HTTP ' . $http . ').');
        }

        if ($http < 200 || $http >= 300) {
            $msg = (string) ($data['detail'] ?? $data['message'] ?? $data['error'] ?? 'HTTP ' . $http);
            if (is_array($data['detail'] ?? null)) {
                $msg = json_encode($data['detail'], JSON_UNESCAPED_UNICODE);
            }
            throw new \RuntimeException('ZapSign: ' . mb_substr((string) $msg, 0, 400));
        }

        return $data;
    }

    private function obterApiToken(): string
    {
        $enc = $this->lerConfig(self::KEY_TOKEN, '');
        if ($enc === '') {
            return '';
        }
        require_once BASE_PATH . '/app/Core/MasterSecretVault.php';
        $plain = \MasterSecretVault::decrypt($enc);
        return is_string($plain) ? trim($plain) : '';
    }

    private function resolverPdfAbsoluto(string $relative): ?string
    {
        $relative = ltrim(str_replace(['..', '\\'], '', $relative), '/');
        if ($relative === '') {
            return null;
        }
        $base = dirname(__DIR__, 4);
        $abs = $base . '/' . $relative;
        return is_file($abs) ? $abs : null;
    }

    /**
     * @return array{country:string,number:string}|null
     */
    private function normalizarTelefone(string $raw): ?array
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return ['country' => '55', 'number' => substr($digits, 2)];
        }
        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            return ['country' => '55', 'number' => $digits];
        }
        return null;
    }

    private function mascarar(string $token): string
    {
        if ($token === '') {
            return '';
        }
        $len = strlen($token);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', max(0, $len - 4)) . substr($token, -4);
    }

    /**
     * Aceita URL do ngrok/domínio público (sem path) ou vazio (= usa URL local).
     */
    private function normalizarWebhookBaseUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . $raw;
        }
        $parts = parse_url($raw);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('URL base do webhook inválida. Use https://xxxx.ngrok-free.app');
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('URL base do webhook deve ser http ou https.');
        }
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (':' . (int) $parts['port']) : '';
        return $scheme . '://' . $host . $port;
    }

    private function lerConfig(string $key, string $default = ''): string
    {
        try {
            $row = $this->db->fetch(
                'SELECT config_value FROM config_layout WHERE config_key = ? LIMIT 1',
                [$key]
            );
            if ($row && isset($row['config_value']) && $row['config_value'] !== null && $row['config_value'] !== '') {
                return (string) $row['config_value'];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $default;
    }

    private function gravarConfig(string $key, string $value): void
    {
        $this->db->query(
            'INSERT INTO config_layout (config_key, config_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP',
            [$key, $value]
        );
        try {
            if (class_exists('RedisCache', false)) {
                if (defined('TENANT_ID')) {
                    \RedisCache::delete('config_layout_' . TENANT_ID);
                }
                \RedisCache::delete('config_layout_single');
                \RedisCache::delete('config_layout');
            }
        } catch (\Throwable $e) {
            // ok
        }
    }
}
