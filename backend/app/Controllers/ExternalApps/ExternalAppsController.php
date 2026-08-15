<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/DatabaseManager.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/../../Core/GamesAccessSchedule.php';
require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
require_once __DIR__ . '/../../Core/Logger.php';

if (!class_exists('ExternalAppsController')) {
class ExternalAppsController extends BaseController
{
    private const LOG_CAT_EXTERNAL_CREDITS = 'external_apps_credits';
    /** Módulo único Tudinha 2.0: custo vem do payload (custo) com teto; descrição em descricao → extrato. */
    private const TUDINHA2_MODULE_KEY = 'app_externo_tudinha2';
    private const EDUCAPROF_PROVA_BIMESTRAL_MODULE_KEY = 'app_externo_educaprof_prova_bimestral';
    private const EDUCAPROF_PROVA_BIMESTRAL_DEFAULT_COST = 20.0;
    private const DECLARATIVE_CUSTO_MIN = 0.0001;
    private const DECLARATIVE_CUSTO_MAX = 100.0;
    private const TOKENS_TABLE = 'validacao_tokens_apps';
    private const TOKENS_TABLE_LEGACY = 'notes_tokens';
    private const EDUCAPROF_DEFAULT_URL = 'https://educaprof.educatudo.com';
    private const CREDIT_ACTION_MAP = [
        'tudinha2:chat' => 'app_externo_tudinha2',
        'tudinha2:mensagem' => 'app_externo_tudinha2',
        'tudinha2:message' => 'app_externo_tudinha2',
        'tudinha2:detailed_explanation' => 'app_externo_tudinha2',
        'tudinha2:flashcards' => 'app_externo_tudinha2',
        'tudinha2:mind_map' => 'app_externo_tudinha2',
        'tudinha2:quiz' => 'app_externo_tudinha2',
        'tudinha2:full_question' => 'app_externo_tudinha2',
        'tudinha2:image_generate' => 'app_externo_tudinha2',
        'tudinha2:image_search' => 'app_externo_tudinha2',
        'tudinha2:presentation' => 'app_externo_tudinha2',
        'tudinha2:imagem' => 'app_externo_tudinha2',
        'tudinha2:image' => 'app_externo_tudinha2',
        'educaprof:chat' => 'app_externo_tudinha2',
        'educaprof:mensagem' => 'app_externo_tudinha2',
        'educaprof:message' => 'app_externo_tudinha2',
        'educaprof:detailed_explanation' => 'app_externo_tudinha2',
        'educaprof:flashcards' => 'app_externo_tudinha2',
        'educaprof:mind_map' => 'app_externo_tudinha2',
        'educaprof:quiz' => 'app_externo_tudinha2',
        'educaprof:full_question' => 'app_externo_tudinha2',
        'educaprof:image_generate' => 'app_externo_tudinha2',
        'educaprof:image_search' => 'app_externo_tudinha2',
        'educaprof:presentation' => 'app_externo_tudinha2',
        'educaprof:imagem' => 'app_externo_tudinha2',
        'educaprof:image' => 'app_externo_tudinha2',
        'educaprof:prova_bimestral' => 'app_externo_educaprof_prova_bimestral',
        'notes:ia' => 'app_externo_notes_ia',
        'notes:resumo_ia' => 'app_externo_notes_ia',
        'games:dica_ia' => 'app_externo_games_dica_ia',
        'games:hint_ai' => 'app_externo_games_dica_ia',
        'educahits:gerar_musica' => 'app_externo_educahits_gerar_musica',
        'educahits:generate_music' => 'app_externo_educahits_gerar_musica',
    ];

    private $authManager;
    private $db;
    private $requestPayload = null;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
    }

    public function index()
    {
        $user = $this->requireAllowedUser();
        $links = $this->getExternalAppsForUserType((string) ($user['tipo'] ?? ''));
        $baseRoute = (($user['tipo'] ?? '') === 'professor') ? '/professor/materiais' : '/aluno/materiais';

        $this->viewWithLayout('student', 'student/materiais/index', [
            'page_title' => 'Materiais',
            'title' => 'Materiais - EducaTudo',
            'current_page' => 'materiais',
            'user' => $user,
            'links' => $links,
            'base_route' => $baseRoute,
        ]);
    }

    public function abrir($id)
    {
        $user = $this->requireAllowedUser();
        $backRoute = (($user['tipo'] ?? '') === 'professor') ? '/professor/dashboard' : '/dashboard';
        $links = $this->getExternalAppsForUserType((string) ($user['tipo'] ?? ''));
        $link = $this->findLinkById((string) $id, $links);
        if (!$link) {
            $this->setFlashMessage('Link externo não encontrado.', 'error');
            $this->redirect($backRoute);
            return;
        }

        $appKey = $this->resolveAppKey($link);
        if ($appKey === 'games' && ($user['tipo'] ?? '') === 'aluno' && !GamesAccessSchedule::isAccessAllowedNow()) {
            $this->setFlashMessage(GamesAccessSchedule::studentBlockedMessage(), 'error');
            $this->redirect($backRoute);
            return;
        }

        $token = $this->generateAccessToken();
        $slug = $this->getSlugForExternalLink();
        $tokenTable = $this->resolveTokensTable($this->db);
        $nickname = $this->fetchStudentNickname($this->db, (int) ($user['id'] ?? 0));

        if ($tokenTable === self::TOKENS_TABLE) {
            $this->db->insert(
                "INSERT INTO " . self::TOKENS_TABLE . " (token, app, user_id, user_name, user_nickname, tenant_slug, created_at, expires_at, used)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)",
                [$token, $appKey, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''), $nickname, $slug]
            );
        } else {
            $this->db->insert(
                "INSERT INTO " . self::TOKENS_TABLE_LEGACY . " (token, user_id, user_name, created_at, expires_at, used)
                 VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)",
                [$token, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? '')]
            );
        }

        $tokenMeta = $this->db->fetch(
            "SELECT created_at, expires_at, NOW() AS db_now
             FROM {$tokenTable}
             WHERE token = ?
             ORDER BY id DESC
             LIMIT 1",
            [$token]
        );
        $this->logTokenDebug('emit', [
            'token' => $this->maskToken($token),
            'user_id' => (string) ($user['id'] ?? ''),
            'user_type' => (string) ($user['tipo'] ?? ''),
            'slug_detected' => $this->detectTenantSlug(),
            'db' => $this->getDatabaseName($this->db),
            'token_table' => $tokenTable,
            'created_at' => (string) ($tokenMeta['created_at'] ?? ''),
            'expires_at' => (string) ($tokenMeta['expires_at'] ?? ''),
            'db_now' => (string) ($tokenMeta['db_now'] ?? ''),
            'app' => $appKey,
            'app_name' => (string) ($link['nome'] ?? ''),
            'nickname' => $nickname,
        ]);

        $instId = trim((string) LayoutHelper::get('external_institution_id', ''));
        $redirectUrl = $this->buildExternalAccessUrl((string) ($link['url'] ?? ''), $token, $slug, $instId, $appKey);
        header('Location: ' . $redirectUrl);
        exit;
    }

    public function validateToken()
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = trim((string) $this->getRequestValue('token'));
        if ($token === '') {
            $this->json(['error' => 'Token não fornecido'], 400);
            return;
        }

        $tenantSlug = $this->getRequestedTenantSlug();
        if ($tenantSlug === '' && trim((string) $this->getRequestValue('inst')) !== '') {
            $dbForValidation = $this->resolveTenantDatabaseByInst(trim((string) $this->getRequestValue('inst')));
        } else {
            $dbForValidation = $this->resolveTenantDatabaseForValidation($tenantSlug);
        }
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        $dbName = $this->getDatabaseName($dbForValidation);
        $tokenTable = $this->resolveTokensTable($dbForValidation);
        $appKey = $this->resolveRequestAppKey();
        if ($appKey === '') {
            $appKey = $this->inferAppFromRequestContext();
        }

        $this->logTokenDebug('validate.request', [
            'token' => $this->maskToken($token),
            'slug' => $tenantSlug,
            'inst' => (string) $this->getRequestValue('inst'),
            'app' => $appKey,
            'method' => $requestMethod,
            'content_type' => $contentType,
            'db' => $dbName,
            'token_table' => $tokenTable,
            'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
            'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        ]);

        if ($tokenTable === self::TOKENS_TABLE) {
            $row = $dbForValidation->fetch(
                "SELECT t.user_id, t.user_name, t.user_nickname, t.tenant_slug, t.app
                 FROM " . self::TOKENS_TABLE . " t
                 WHERE t.token = ? AND t.expires_at > NOW()",
                [$token]
            );
        } else {
            $row = $dbForValidation->fetch(
                "SELECT t.user_id, t.user_name, '' AS user_nickname, '' AS tenant_slug, '' AS app
                 FROM " . self::TOKENS_TABLE_LEGACY . " t
                 WHERE t.token = ? AND t.expires_at > NOW()",
                [$token]
            );
        }

        if (!$row) {
            $diag = null;
            try {
                $diag = $dbForValidation->fetch(
                    "SELECT t.created_at, t.expires_at, NOW() AS db_now,
                            TIMESTAMPDIFF(SECOND, NOW(), t.expires_at) AS seconds_to_expire
                     FROM {$tokenTable} t
                     WHERE t.token = ?
                     ORDER BY t.id DESC
                     LIMIT 1",
                    [$token]
                );
            } catch (Throwable $e) {
                $diag = ['diag_error' => $e->getMessage()];
            }

            $this->logTokenDebug('validate.fail', [
                'token' => $this->maskToken($token),
                'slug' => $tenantSlug,
                'db' => $dbName,
                'token_table' => $tokenTable,
                'app' => $appKey,
                'diagnostic' => $diag,
            ]);
            $detalhes = [
                'slug' => $tenantSlug,
                'db' => $dbName,
                'token_table' => $tokenTable,
                'diagnostic' => $diag,
                'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
                'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            ];
            if ($tenantSlug === '' && trim((string) $this->getRequestValue('inst')) !== '') {
                $detalhes['inst'] = trim((string) $this->getRequestValue('inst'));
            }
            if ($dbForValidation === $this->db) {
                $detalhes['hint'] = 'Tenant não resolvido: a validação consultou o banco master. O token está no tenant (ex.: educa_004_educa). Verifique no servidor master: MULTI_TENANT_ACTIVE=true e, no banco master, tabela escolas com slug do request (ex.: slug=educa, ativo=1) e config_escolas_banco para essa escola.';
            }
            $this->writeValidationFailLog($dbForValidation, $appKey ?: 'unknown', 'validate.fail', $detalhes);
            $this->json(['error' => 'Token inválido ou expirado'], 401);
            return;
        }

        $inst = $this->readExternalInstitutionId($dbForValidation);
        if ($inst === '') {
            $inst = trim((string) $this->getRequestValue('inst'));
        }
        $nickname = trim((string) ($row['user_nickname'] ?? ''));
        if ($nickname === '') {
            $nickname = $this->fetchStudentNickname($dbForValidation, (int) ($row['user_id'] ?? 0));
        }
        $responseSlug = trim((string) ($row['tenant_slug'] ?? ''));
        if ($responseSlug === '') {
            $responseSlug = $tenantSlug;
        }
        $responseApp = trim((string) ($row['app'] ?? ''));
        if ($responseApp === '') {
            $responseApp = $appKey;
        }
        $configuredLink = $this->findConfiguredExternalLinkByApp($dbForValidation, $responseApp);
        $sendStudentContext = !empty($configuredLink['enviar_contexto_aluno']);
        $studentContext = null;
        if ($sendStudentContext) {
            $studentContext = $this->buildStudentContextPayload($dbForValidation, (int) ($row['user_id'] ?? 0));
        }

        $response = [
            'userId' => (string) $row['user_id'],
            'userName' => (string) ($row['user_name'] ?? ''),
            'nickname' => $nickname,
            'userType' => $this->inferUserTypeFromApp($responseApp),
            'slug' => $responseSlug,
            'inst' => $inst,
            'app' => $responseApp,
            // Campos legados/alternativos para facilitar integração dos apps externos.
            'alunoId' => (string) $row['user_id'],
            'alunoNome' => (string) ($row['user_name'] ?? ''),
            // Indica se este app recebe payload rico no validate-token.
            'contextoAlunoHabilitado' => $sendStudentContext,
        ];
        if ($sendStudentContext) {
            $response['contexto_aluno'] = $studentContext;
            // alias em camelCase para integrações novas.
            $response['contextoAluno'] = $studentContext;
        }
        if ($this->inferUserTypeFromApp($responseApp) === 'professor') {
            $requestedAction = $this->normalizeActionKey((string) ($this->getRequestValue('acao') ?? $this->getRequestValue('action') ?? ''));
            $response = array_merge($response, $this->buildProfessorCreditsValidationPayload($dbForValidation, $responseApp, (int) ($row['user_id'] ?? 0), $requestedAction));
        }
        $this->json($response);

        $this->logTokenDebug('validate.ok', [
            'token' => $this->maskToken($token),
            'slug' => $responseSlug,
            'db' => $dbName,
            'token_table' => $tokenTable,
            'app' => $responseApp,
            'user_id' => (string) ($row['user_id'] ?? ''),
            'nickname' => $nickname,
        ]);
    }

    public function creditContext()
    {
        header('Content-Type: application/json; charset=utf-8');

        $snap = $this->snapshotExternalCreditsRequest();
        try {
            $context = $this->resolveExternalCreditContext();
            $db = $context['db'];
            $moduleKey = $context['module_key'];
            $creditEnabled = $this->isCreditosHabilitadoForDb($db);
            $cobra = $creditEnabled && $this->moduloCobraCreditoForContext($db, $moduleKey);
            $custo = $cobra ? $this->getEffectiveCustoForContext($db, $context) : 0.0;
            $custoFonte = ($cobra && ($context['declared_custo'] ?? null) !== null) ? 'declarado' : ($cobra ? 'layout' : null);
            $saldo = $this->getSaldoForDb($db, $context['user_type'], $context['user_id']);
            $motivoCobraInativo = $this->motivoCobraInativo($creditEnabled, $cobra);

            $this->json([
                'ok' => true,
                'app' => $context['app'],
                'action' => $context['action'],
                'moduleKey' => $moduleKey,
                'description' => $this->resolveConsumeDescription($context, null),
                'creditosHabilitado' => $creditEnabled,
                'cobraCredito' => $cobra,
                'custo' => $custo,
                'custoFonte' => $custoFonte,
                'saldo' => $saldo,
                'podeConsumir' => !$cobra || round($saldo, CreditosDecimalHelper::SCALE) + 1e-9 >= round($custo, CreditosDecimalHelper::SCALE),
                'userType' => $context['user_type'],
                'userId' => $context['user_id'],
                'slug' => $context['slug'],
                'motivoCobraInativo' => $motivoCobraInativo,
            ]);
        } catch (Throwable $e) {
            Logger::error('external-apps/credit-context erro', array_merge($snap, [
                'error' => $e->getMessage(),
            ]), self::LOG_CAT_EXTERNAL_CREDITS);
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function consumeCredit()
    {
        header('Content-Type: application/json; charset=utf-8');

        $snap = $this->snapshotExternalCreditsRequest();
        try {
            $context = $this->resolveExternalCreditContext(true);
            $db = $context['db'];
            $moduleKey = $context['module_key'];
            $referenceId = $context['reference_id'];

            $existing = $this->findMovimentacaoByReference($db, $context['user_type'], $context['user_id'], $moduleKey, $referenceId, 'consumo');
            if ($existing) {
                $saldoAtual = $this->getSaldoForDb($db, $context['user_type'], $context['user_id']);
                $this->json([
                    'ok' => true,
                    'alreadyConsumed' => true,
                    'charged' => true,
                    'movementId' => (int) ($existing['id'] ?? 0),
                    'moduleKey' => $moduleKey,
                    'description' => $this->resolveConsumeDescription($context, $existing),
                    'referenceId' => $referenceId,
                    'saldoAtual' => $saldoAtual,
                    'custo' => abs((float) ($existing['valor'] ?? 0)),
                ]);
                return;
            }

            $creditEnabled = $this->isCreditosHabilitadoForDb($db);
            $cobra = $creditEnabled && $this->moduloCobraCreditoForContext($db, $moduleKey);
            $saldoAntes = $this->getSaldoForDb($db, $context['user_type'], $context['user_id']);
            $motivoCobraInativo = $this->motivoCobraInativo($creditEnabled, $cobra);

            if (!$cobra) {
                $this->json([
                    'ok' => true,
                    'charged' => false,
                    'alreadyConsumed' => false,
                    'moduleKey' => $moduleKey,
                    'description' => $this->resolveConsumeDescription($context, null),
                    'referenceId' => $referenceId,
                    'saldoAntes' => $saldoAntes,
                    'saldoDepois' => $saldoAntes,
                    'custo' => 0.0,
                    'creditosHabilitado' => $creditEnabled,
                    'cobraCredito' => false,
                    'motivoCobraInativo' => $motivoCobraInativo,
                ]);
                return;
            }

            $custo = $this->getEffectiveCustoForContext($db, $context);
            if (round($saldoAntes, CreditosDecimalHelper::SCALE) + 1e-9 < round($custo, CreditosDecimalHelper::SCALE)) {
                $this->json([
                    'error' => 'Créditos insuficientes. Compre mais créditos ou aguarde a recarga mensal.',
                    'moduleKey' => $moduleKey,
                    'description' => $this->resolveConsumeDescription($context, null),
                    'saldoAtual' => $saldoAntes,
                    'custo' => $custo,
                    'motivoCobraInativo' => null,
                ], 402);
                return;
            }

            $observacao = $this->buildMovimentacaoObservacao($context);
            $movementId = $this->consumirCreditosForDb(
                $db,
                $context['user_type'],
                $context['user_id'],
                $moduleKey,
                $referenceId,
                $custo,
                $observacao
            );
            $saldoDepois = $this->getSaldoForDb($db, $context['user_type'], $context['user_id']);

            $this->json([
                'ok' => true,
                'charged' => true,
                'alreadyConsumed' => false,
                'movementId' => $movementId,
                'moduleKey' => $moduleKey,
                'description' => $this->resolveConsumeDescription($context, null),
                'referenceId' => $referenceId,
                'saldoAntes' => $saldoAntes,
                'saldoDepois' => $saldoDepois,
                'custo' => $custo,
                'custoFonte' => ($context['declared_custo'] ?? null) !== null ? 'declarado' : 'layout',
            ]);
        } catch (Throwable $e) {
            Logger::error('external-apps/consume erro', array_merge($snap, [
                'error' => $e->getMessage(),
            ]), self::LOG_CAT_EXTERNAL_CREDITS);
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function refundCredit()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $context = $this->resolveExternalCreditContext(true);
            $db = $context['db'];
            $moduleKey = $context['module_key'];
            $referenceId = $context['reference_id'];

            $existingRefund = $this->findMovimentacaoByReference($db, $context['user_type'], $context['user_id'], $moduleKey, $referenceId, 'estorno');
            if ($existingRefund) {
                $this->json([
                    'ok' => true,
                    'alreadyRefunded' => true,
                    'referenceId' => $referenceId,
                    'moduleKey' => $moduleKey,
                    'movementId' => (int) ($existingRefund['id'] ?? 0),
                    'saldoAtual' => $this->getSaldoForDb($db, $context['user_type'], $context['user_id']),
                ]);
                return;
            }

            $movementId = $this->estornarCreditosPorReferenciaForDb($db, $context['user_type'], $context['user_id'], $moduleKey, $referenceId);
            if ($movementId === null) {
                $this->json([
                    'ok' => true,
                    'alreadyRefunded' => false,
                    'refunded' => false,
                    'referenceId' => $referenceId,
                    'moduleKey' => $moduleKey,
                    'message' => 'Nenhum consumo encontrado para esta referência.',
                ]);
                return;
            }

            $this->json([
                'ok' => true,
                'alreadyRefunded' => false,
                'refunded' => true,
                'referenceId' => $referenceId,
                'moduleKey' => $moduleKey,
                'movementId' => $movementId,
                'saldoAtual' => $this->getSaldoForDb($db, $context['user_type'], $context['user_id']),
            ]);
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Dados seguros do request para logs (sem token completo).
     */
    private function snapshotExternalCreditsRequest(): array
    {
        $token = trim((string) ($this->getRequestValue('token') ?? ''));
        $ref = trim((string) ($this->getRequestValue('referencia_id') ?? $this->getRequestValue('reference_id') ?? ''));

        $custoRaw = $this->getRequestValue('custo');

        return [
            'slug' => $this->getRequestedTenantSlug(),
            'inst' => trim((string) ($this->getRequestValue('inst') ?? '')),
            'app' => trim((string) ($this->getRequestValue('app') ?? '')),
            'action' => trim((string) ($this->getRequestValue('acao') ?? $this->getRequestValue('action') ?? '')),
            'token_masked' => $this->maskToken($token),
            'referencia_id_len' => strlen($ref),
            'custo_payload' => $custoRaw !== null && $custoRaw !== '' ? $custoRaw : null,
        ];
    }

    private function truncateForLog(string $value, int $max = 96): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }
        return substr($value, 0, $max) . '...';
    }

    /**
     * Quando cobraCredito é false: indica se o bloqueio é escola (créditos off) ou só o módulo.
     */
    private function motivoCobraInativo(bool $creditEnabled, bool $cobra): ?string
    {
        if ($cobra) {
            return null;
        }
        return $creditEnabled ? 'credito_modulo_desligado' : 'creditos_sistema_desligado';
    }

    private function getRequestedTenantSlug(): string
    {
        $slug = trim((string) $this->getRequestValue('slug'));
        return strtolower($slug);
    }

    private function getRequestValue(string $key): ?string
    {
        if (isset($_GET[$key])) {
            return (string) $_GET[$key];
        }
        if (isset($_POST[$key])) {
            return (string) $_POST[$key];
        }

        $payload = $this->getRequestPayload();
        if (isset($payload[$key])) {
            return is_scalar($payload[$key]) ? (string) $payload[$key] : null;
        }

        return null;
    }

    private function getRequestPayload(): array
    {
        if (is_array($this->requestPayload)) {
            return $this->requestPayload;
        }

        $this->requestPayload = [];
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return $this->requestPayload;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $this->requestPayload = $decoded;
        }

        return $this->requestPayload;
    }

    private function resolveExternalCreditContext(bool $requireReferenceId = false): array
    {
        $token = trim((string) $this->getRequestValue('token'));
        if ($token === '') {
            throw new InvalidArgumentException('Token não fornecido.');
        }

        $action = $this->normalizeActionKey((string) ($this->getRequestValue('acao') ?? $this->getRequestValue('action') ?? ''));
        if ($action === '') {
            throw new InvalidArgumentException('Ação não fornecida.');
        }

        $requestedApp = $this->resolveRequestAppKey();
        $tenantSlug = $this->getRequestedTenantSlug();
        if ($tenantSlug === '' && trim((string) $this->getRequestValue('inst')) !== '') {
            $db = $this->resolveTenantDatabaseByInst(trim((string) $this->getRequestValue('inst')));
        } else {
            $db = $this->resolveTenantDatabaseForValidation($tenantSlug);
        }

        $tokenTable = $this->resolveTokensTable($db);
        if ($tokenTable === self::TOKENS_TABLE) {
            $row = $db->fetch(
                "SELECT t.user_id, t.user_name, t.user_nickname, t.tenant_slug, t.app
                 FROM " . self::TOKENS_TABLE . " t
                 WHERE t.token = ? AND t.expires_at > NOW()",
                [$token]
            );
        } else {
            $row = $db->fetch(
                "SELECT t.user_id, t.user_name, '' AS user_nickname, '' AS tenant_slug, '' AS app
                 FROM " . self::TOKENS_TABLE_LEGACY . " t
                 WHERE t.token = ? AND t.expires_at > NOW()",
                [$token]
            );
        }

        if (!$row) {
            throw new RuntimeException('Token inválido ou expirado.');
        }

        $resolvedApp = trim((string) ($row['app'] ?? ''));
        if ($resolvedApp === '') {
            $resolvedApp = $requestedApp;
        }
        $resolvedApp = $this->normalizeAppKey($resolvedApp);
        if ($resolvedApp === '') {
            throw new InvalidArgumentException('App não identificado para este token.');
        }
        if ($requestedApp !== '' && $requestedApp !== $resolvedApp) {
            throw new InvalidArgumentException('App do request não confere com o token validado.');
        }

        $moduleKey = $this->resolveCreditModuleKey($resolvedApp, $action);
        if (!CreditosModuleRegistry::isValid($moduleKey)) {
            throw new InvalidArgumentException('Ação de crédito ainda não cadastrada no catálogo.');
        }

        $referenceId = trim((string) ($this->getRequestValue('referencia_id') ?? $this->getRequestValue('reference_id') ?? ''));
        if ($requireReferenceId && $referenceId === '') {
            throw new InvalidArgumentException('referencia_id é obrigatório.');
        }

        $declaredCusto = null;
        $declaredDescricao = '';
        if ($resolvedApp === 'tudinha2') {
            $declaredCusto = $this->parseTudinha2DeclaredCusto();
            $declaredDescricao = mb_substr(trim((string) ($this->getRequestValue('descricao') ?? '')), 0, 512);
        }

        $userType = $this->inferUserTypeFromApp($resolvedApp);
        return [
            'db' => $db,
            'app' => $resolvedApp,
            'action' => $action,
            'module_key' => $moduleKey,
            'user_id' => (int) ($row['user_id'] ?? 0),
            'user_type' => $userType,
            'slug' => trim((string) ($row['tenant_slug'] ?? $tenantSlug)),
            'reference_id' => $referenceId,
            'declared_custo' => $declaredCusto,
            'declared_descricao' => $declaredDescricao,
        ];
    }

    private function normalizeActionKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?? '';
        return trim($value, '_-');
    }

    private function resolveCreditModuleKey(string $app, string $action): string
    {
        $key = $this->normalizeAppKey($app) . ':' . $this->normalizeActionKey($action);
        return self::CREDIT_ACTION_MAP[$key] ?? '';
    }

    private function buildOfficialCreditDescription(string $app, string $action, ?string $moduleKey = null): string
    {
        $mk = $moduleKey ?? $this->resolveCreditModuleKey($app, $action);
        if ($mk !== '' && CreditosModuleRegistry::isValid($mk)) {
            return CreditosModuleRegistry::getLabel($mk);
        }
        return ucfirst($app) . ' - ' . $action;
    }

    private function resolveConsumeDescription(array $context, ?array $existingMov = null): string
    {
        $fromReq = trim((string) ($context['declared_descricao'] ?? ''));
        if ($fromReq !== '') {
            return mb_substr($fromReq, 0, 255);
        }
        if ($existingMov !== null && !empty($existingMov['observacao'])) {
            return mb_substr(trim((string) $existingMov['observacao']), 0, 255);
        }
        return $this->buildOfficialCreditDescription(
            (string) ($context['app'] ?? ''),
            (string) ($context['action'] ?? ''),
            (string) ($context['module_key'] ?? '') ?: null
        );
    }

    private function buildMovimentacaoObservacao(array $context): ?string
    {
        $s = trim((string) ($context['declared_descricao'] ?? ''));
        if ($s === '') {
            return null;
        }
        return mb_substr($s, 0, 512);
    }

    private function moduloCobraCreditoTudinha2(Database $db): bool
    {
        return $this->moduloCobraCreditoForDb($db, self::TUDINHA2_MODULE_KEY)
            || $this->moduloCobraCreditoForDb($db, 'app_externo_tudinha2_mensagem')
            || $this->moduloCobraCreditoForDb($db, 'app_externo_tudinha2_imagem');
    }

    private function buildProfessorCreditsValidationPayload(Database $db, string $app, int $userId, string $action = ''): array
    {
        $creditEnabled = $this->isCreditosHabilitadoForDb($db);
        $moduleKey = $this->resolveProfessorCreditsModuleKey($app, $action);
        $cobra = $creditEnabled && $this->moduloCobraCreditoForContext($db, $moduleKey);
        $custo = $cobra ? $this->getEffectiveCustoForContext($db, ['module_key' => $moduleKey]) : 0.0;
        $saldo = $this->getSaldoForDb($db, 'professor', $userId);
        $possui = !$cobra || round($saldo, CreditosDecimalHelper::SCALE) + 1e-9 >= round($custo, CreditosDecimalHelper::SCALE);

        return [
            'creditosHabilitado' => $creditEnabled,
            'cobraCredito' => $cobra,
            'moduloCredito' => $moduleKey,
            'custoCredito' => $custo,
            'acaoCredito' => $action,
            'saldoCreditos' => $saldo,
            'possuiCreditos' => $possui,
            'motivoCobraInativo' => $this->motivoCobraInativo($creditEnabled, $cobra),
        ];
    }

    private function resolveProfessorCreditsModuleKey(string $app, string $action = ''): string
    {
        $normalizedApp = $this->normalizeAppKey($app);
        $normalizedAction = $this->normalizeActionKey($action);
        if ($normalizedApp === 'educaprof' && $normalizedAction === 'prova_bimestral') {
            return self::EDUCAPROF_PROVA_BIMESTRAL_MODULE_KEY;
        }
        if ($normalizedApp === 'educaprof') {
            return self::TUDINHA2_MODULE_KEY;
        }
        return self::TUDINHA2_MODULE_KEY;
    }

    private function moduloCobraCreditoForContext(Database $db, string $moduleKey): bool
    {
        if ($moduleKey === self::TUDINHA2_MODULE_KEY) {
            return $this->moduloCobraCreditoTudinha2($db);
        }
        return $this->moduloCobraCreditoForDb($db, $moduleKey);
    }

    private function parseTudinha2DeclaredCusto(): ?float
    {
        $raw = $this->getRequestValue('custo');
        if ($raw === null || $raw === '') {
            return null;
        }
        $p = CreditosDecimalHelper::parsePost($raw);
        if ($p < self::DECLARATIVE_CUSTO_MIN) {
            return null;
        }
        return $this->clampDeclarativeCusto($p);
    }

    private function clampDeclarativeCusto(float $c): float
    {
        $c = round($c, CreditosDecimalHelper::SCALE);
        if ($c < self::DECLARATIVE_CUSTO_MIN) {
            return self::DECLARATIVE_CUSTO_MIN;
        }
        if ($c > self::DECLARATIVE_CUSTO_MAX) {
            return self::DECLARATIVE_CUSTO_MAX;
        }
        return $c;
    }

    private function getCustoModuloTudinha2Fallback(Database $db): float
    {
        foreach ([self::TUDINHA2_MODULE_KEY, 'app_externo_tudinha2_mensagem', 'app_externo_tudinha2_imagem'] as $k) {
            $raw = $this->getConfigLayoutValue($db, 'credito_custo_' . $k, '');
            if ($raw !== null && $raw !== '') {
                return CreditosDecimalHelper::fromScalar($raw, 1.0);
            }
        }
        return 1.0;
    }

    private function getEffectiveCustoForContext(Database $db, array $context): float
    {
        $mk = (string) ($context['module_key'] ?? '');
        $decl = $context['declared_custo'] ?? null;
        if ($mk === self::TUDINHA2_MODULE_KEY && $decl !== null) {
            return $this->clampDeclarativeCusto((float) $decl);
        }
        if ($mk === self::TUDINHA2_MODULE_KEY) {
            return $this->getCustoModuloTudinha2Fallback($db);
        }
        return $this->getCustoModuloForDb($db, $mk);
    }

    private function inferUserTypeFromApp(string $app): string
    {
        if ($this->normalizeAppKey($app) === 'educaprof') {
            return 'professor';
        }
        return 'aluno';
    }

    private function isCreditosHabilitadoForDb(Database $db): bool
    {
        $v = $this->getConfigLayoutValue($db, 'creditos_habilitado', '0');
        return $v === '1' || $v === 1 || $v === true;
    }

    private function moduloCobraCreditoForDb(Database $db, string $moduloKey): bool
    {
        if (!CreditosModuleRegistry::isValid($moduloKey)) {
            return false;
        }
        $default = $moduloKey === self::EDUCAPROF_PROVA_BIMESTRAL_MODULE_KEY ? '__missing__' : '0';
        $v = $this->getConfigLayoutValue($db, 'credito_modulo_' . $moduloKey, $default);
        if ($moduloKey === self::EDUCAPROF_PROVA_BIMESTRAL_MODULE_KEY && $v === '__missing__') {
            return true;
        }
        return $v === '1' || $v === 1 || $v === true;
    }

    private function getCustoModuloForDb(Database $db, string $moduloKey): float
    {
        $default = $moduloKey === self::EDUCAPROF_PROVA_BIMESTRAL_MODULE_KEY
            ? (string) self::EDUCAPROF_PROVA_BIMESTRAL_DEFAULT_COST
            : '';
        $raw = $this->getConfigLayoutValue($db, 'credito_custo_' . $moduloKey, $default);
        return CreditosDecimalHelper::fromScalar($raw, 1.0);
    }

    private function getConfigLayoutValue(Database $db, string $key, $default = '')
    {
        try {
            $row = $db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ? LIMIT 1",
                [$key]
            );
            if ($row === null || !array_key_exists('config_value', $row)) {
                return $default;
            }
            return $row['config_value'];
        } catch (Throwable $e) {
            return $default;
        }
    }

    private function ensureCarteiraRow(Database $db, string $userType, int $userId): void
    {
        $this->ensureWalletSchemaForDb($db);
        $row = $db->fetch(
            "SELECT id FROM carteira_usuarios WHERE user_type = ? AND user_id = ? LIMIT 1",
            [$userType, $userId]
        );
        if ($row !== null) {
            return;
        }
        $db->insert(
            "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado) VALUES (?, ?, 0, 0, 0)",
            [$userType, $userId]
        );
    }

    private function getSaldoForDb(Database $db, string $userType, int $userId): float
    {
        $this->ensureCarteiraRow($db, $userType, $userId);
        $row = $db->fetch(
            "SELECT saldo, saldo_escola, saldo_comprado FROM carteira_usuarios WHERE user_type = ? AND user_id = ? LIMIT 1",
            [$userType, $userId]
        );
        $saldoEscola = CreditosDecimalHelper::fromScalar($row['saldo_escola'] ?? 0, 0.0);
        $saldoComprado = CreditosDecimalHelper::fromScalar($row['saldo_comprado'] ?? 0, 0.0);
        return round($saldoEscola + $saldoComprado, CreditosDecimalHelper::SCALE);
    }

    private function consumirCreditosForDb(
        Database $db,
        string $userType,
        int $userId,
        string $moduloKey,
        string $referenceId,
        float $custo,
        ?string $observacao = null
    ): int {
        $this->ensureCarteiraRow($db, $userType, $userId);
        $saldo = $this->getSaldoForDb($db, $userType, $userId);
        $custo = round($custo, CreditosDecimalHelper::SCALE);
        if (round($saldo, CreditosDecimalHelper::SCALE) + 1e-9 < round($custo, CreditosDecimalHelper::SCALE)) {
            throw new RuntimeException('Créditos insuficientes. Compre mais créditos ou aguarde a recarga mensal.');
        }

        $wallet = $db->fetch(
            "SELECT saldo_escola, saldo_comprado FROM carteira_usuarios WHERE user_type = ? AND user_id = ? LIMIT 1",
            [$userType, $userId]
        ) ?: ['saldo_escola' => 0, 'saldo_comprado' => 0];
        $saldoEscola = CreditosDecimalHelper::fromScalar($wallet['saldo_escola'] ?? 0, 0.0);
        $saldoComprado = CreditosDecimalHelper::fromScalar($wallet['saldo_comprado'] ?? 0, 0.0);
        $consumirEscola = min($saldoEscola, $custo);
        $restante = round($custo - $consumirEscola, CreditosDecimalHelper::SCALE);
        $consumirComprado = $restante > 0 ? min($saldoComprado, $restante) : 0.0;
        $novoSaldoEscola = round($saldoEscola - $consumirEscola, CreditosDecimalHelper::SCALE);
        $novoSaldoComprado = round($saldoComprado - $consumirComprado, CreditosDecimalHelper::SCALE);
        $novoSaldo = round($novoSaldoEscola + $novoSaldoComprado, CreditosDecimalHelper::SCALE);
        $origem = 'misto';
        if ($consumirComprado <= 0.00009) {
            $origem = 'escola';
        } elseif ($consumirEscola <= 0.00009) {
            $origem = 'comprado';
        }

        $db->query(
            "UPDATE carteira_usuarios SET saldo = ?, saldo_escola = ?, saldo_comprado = ?, updated_at = NOW() WHERE user_type = ? AND user_id = ?",
            [$novoSaldo, $novoSaldoEscola, $novoSaldoComprado, $userType, $userId]
        );
        $obs = ($observacao !== null && $observacao !== '') ? $observacao : null;
        $db->insert(
            "INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, modulo_key, referencia_id, observacao) VALUES (?, ?, 'consumo', ?, ?, ?, ?, ?)",
            [$userType, $userId, $origem, -$custo, $moduloKey, $referenceId, $obs]
        );
        $row = $db->fetch("SELECT LAST_INSERT_ID() AS id");
        return (int) ($row['id'] ?? 0);
    }

    private function estornarCreditosPorReferenciaForDb(Database $db, string $userType, int $userId, string $moduloKey, string $referenceId): ?int
    {
        $consumo = $this->findMovimentacaoByReference($db, $userType, $userId, $moduloKey, $referenceId, 'consumo');
        if (!$consumo) {
            return null;
        }
        $valorEstorno = round(abs((float) ($consumo['valor'] ?? 0)), CreditosDecimalHelper::SCALE);
        $wallet = $db->fetch(
            "SELECT saldo_escola, saldo_comprado FROM carteira_usuarios WHERE user_type = ? AND user_id = ? LIMIT 1",
            [$userType, $userId]
        ) ?: ['saldo_escola' => 0, 'saldo_comprado' => 0];
        $saldoEscola = CreditosDecimalHelper::fromScalar($wallet['saldo_escola'] ?? 0, 0.0);
        $saldoComprado = CreditosDecimalHelper::fromScalar($wallet['saldo_comprado'] ?? 0, 0.0);
        $origem = (string) ($consumo['saldo_origem'] ?? 'misto');
        if ($origem === 'comprado') {
            $saldoComprado = round($saldoComprado + $valorEstorno, CreditosDecimalHelper::SCALE);
        } else {
            $saldoEscola = round($saldoEscola + $valorEstorno, CreditosDecimalHelper::SCALE);
        }
        $novoSaldo = round($saldoEscola + $saldoComprado, CreditosDecimalHelper::SCALE);
        $db->query(
            "UPDATE carteira_usuarios SET saldo = ?, saldo_escola = ?, saldo_comprado = ?, updated_at = NOW() WHERE user_type = ? AND user_id = ?",
            [$novoSaldo, $saldoEscola, $saldoComprado, $userType, $userId]
        );
        $db->insert(
            "INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, modulo_key, referencia_id) VALUES (?, ?, 'estorno', ?, ?, ?, ?)",
            [$userType, $userId, $origem === '' ? 'escola' : $origem, $valorEstorno, $moduloKey, $referenceId]
        );
        $row = $db->fetch("SELECT LAST_INSERT_ID() AS id");
        return (int) ($row['id'] ?? 0);
    }

    private function findMovimentacaoByReference(Database $db, string $userType, int $userId, string $moduloKey, string $referenceId, string $tipo): ?array
    {
        $row = $db->fetch(
            "SELECT id, valor, saldo_origem, created_at, observacao
             FROM carteira_movimentacoes
             WHERE user_type = ? AND user_id = ? AND tipo = ? AND modulo_key = ? AND referencia_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$userType, $userId, $tipo, $moduloKey, $referenceId]
        );
        return is_array($row) ? $row : null;
    }

    private function ensureWalletSchemaForDb(Database $db): void
    {
        try {
            $saldoEscola = $db->fetch("SHOW COLUMNS FROM carteira_usuarios LIKE 'saldo_escola'");
            if (!$saldoEscola) {
                $db->query("ALTER TABLE carteira_usuarios ADD COLUMN saldo_escola DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo");
                $db->query("UPDATE carteira_usuarios SET saldo_escola = COALESCE(saldo, 0)");
            }
        } catch (Throwable $e) {
        }

        try {
            $saldoComprado = $db->fetch("SHOW COLUMNS FROM carteira_usuarios LIKE 'saldo_comprado'");
            if (!$saldoComprado) {
                $db->query("ALTER TABLE carteira_usuarios ADD COLUMN saldo_comprado DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo_escola");
            }
        } catch (Throwable $e) {
        }

        try {
            $saldoOrigem = $db->fetch("SHOW COLUMNS FROM carteira_movimentacoes LIKE 'saldo_origem'");
            if (!$saldoOrigem) {
                $db->query("ALTER TABLE carteira_movimentacoes ADD COLUMN saldo_origem ENUM('escola','comprado','misto') NULL DEFAULT NULL AFTER tipo");
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * Resolve o banco do tenant pelo inst (external_institution_id) quando o slug não foi enviado.
     * Usado quando o link foi montado sem slug (ex.: slug numérico igual ao inst foi suprimido).
     */
    private function resolveTenantDatabaseByInst(string $inst): Database
    {
        if (
            $inst === '' ||
            !defined('MULTI_TENANT_ACTIVE') ||
            MULTI_TENANT_ACTIVE !== true
        ) {
            return $this->db;
        }
        try {
            $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
            if ($masterPdo === null || !($masterPdo instanceof PDO)) {
                $masterPdo = Database::createMasterPdo();
                $GLOBALS['_educatudo_master_pdo'] = $masterPdo;
            }
            $stmt = $masterPdo->prepare(
                "SELECT escola_id FROM config_escolas_layout WHERE config_key = 'external_institution_id' AND config_value = :inst LIMIT 1"
            );
            $stmt->execute(['inst' => $inst]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['escola_id'])) {
                return $this->db;
            }
            $manager = $GLOBALS['_educatudo_db_manager'] ?? null;
            if ($manager === null || !($manager instanceof DatabaseManager)) {
                $manager = new DatabaseManager($masterPdo);
            }
            return $manager->getConnectionForTenant((int) $row['escola_id']);
        } catch (Throwable $e) {
            $this->logTokenDebug('tenant.resolve.by_inst.error', ['inst' => $inst, 'error' => $e->getMessage()]);
            return $this->db;
        }
    }

    private function resolveTenantDatabaseForValidation(string $tenantSlug): Database
    {
        if (
            $tenantSlug === '' ||
            !defined('MULTI_TENANT_ACTIVE') ||
            MULTI_TENANT_ACTIVE !== true
        ) {
            return $this->db;
        }

        try {
            $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
            if ($masterPdo === null || !($masterPdo instanceof PDO)) {
                $masterPdo = Database::createMasterPdo();
                $GLOBALS['_educatudo_master_pdo'] = $masterPdo;
            }

            $stmt = $masterPdo->prepare("SELECT id FROM escolas WHERE slug = :slug AND ativo = 1 LIMIT 1");
            $stmt->execute(['slug' => $tenantSlug]);
            $escola = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$escola || empty($escola['id'])) {
                $this->logTokenDebug('tenant.resolve.miss', [
                    'slug' => $tenantSlug,
                    'message' => 'slug da escola não encontrado; o link deve enviar o slug da escola, não o ID (inst). Fallback para DB atual.',
                ]);
                return $this->db;
            }

            $manager = $GLOBALS['_educatudo_db_manager'] ?? null;
            if ($manager === null || !($manager instanceof DatabaseManager)) {
                $manager = new DatabaseManager($masterPdo);
            }
            return $manager->getConnectionForTenant((int) $escola['id']);
        } catch (Throwable $e) {
            $this->logTokenDebug('tenant.resolve.error', [
                'slug' => $tenantSlug,
                'error' => $e->getMessage(),
            ]);
            return $this->db;
        }
    }

    private function readExternalInstitutionId(Database $db): string
    {
        try {
            $row = $db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ? LIMIT 1",
                ['external_institution_id']
            );
            return trim((string) ($row['config_value'] ?? ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    private function requireAllowedUser(): array
    {
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }

        $user = $this->authManager->getUser();
        $tipo = (string) ($user['tipo'] ?? '');
        if (!in_array($tipo, ['aluno', 'professor'], true)) {
            $this->redirectToCorrectDashboard($tipo);
        }

        return $user;
    }

    private function getExternalAppsForUserType(string $userType): array
    {
        $raw = LayoutHelper::get('external_apps_links', '[]');
        $links = json_decode((string) $raw, true);
        if (!is_array($links)) {
            $links = [];
        }

        if (empty($links)) {
            $links = $this->buildLegacyLinks();
        } else {
            // A config pode ter sido herdada de outra escola (ex: clonagem de tenant) sem
            // incluir todos os apps padrão. Completa com os defaults legados cujo app key
            // ainda não esteja presente, em vez de descartá-los só porque a lista não está
            // totalmente vazia.
            $existingAppKeys = [];
            foreach ($links as $existingLink) {
                if (is_array($existingLink)) {
                    $existingAppKeys[$this->resolveAppKey($existingLink)] = true;
                }
            }
            foreach ($this->buildLegacyLinks() as $legacyLink) {
                $legacyAppKey = $this->resolveAppKey($legacyLink);
                if (!isset($existingAppKeys[$legacyAppKey])) {
                    $links[] = $legacyLink;
                }
            }
        }

        $filtered = [];
        foreach ($links as $idx => $link) {
            if (!is_array($link)) {
                continue;
            }
            $name = trim((string) ($link['nome'] ?? $link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }
            $allowAluno = !empty($link['aluno']);
            $allowProfessor = !empty($link['professor']);
            if (($userType === 'aluno' && !$allowAluno) || ($userType === 'professor' && !$allowProfessor)) {
                continue;
            }
            $filtered[] = [
                'id' => (string) ($link['id'] ?? $idx),
                'nome' => $name,
                'url' => $url,
                'app' => $this->resolveAppKey($link),
                'nova_guia' => !empty($link['nova_guia']),
                'enviar_contexto_aluno' => !empty($link['enviar_contexto_aluno']),
            ];
        }

        return $filtered;
    }

    private function buildLegacyLinks(): array
    {
        $defaults = [
            ['id' => 'educalabs', 'app' => 'educalabs', 'nome' => 'EducaLabs', 'url' => trim((string) LayoutHelper::get('educalabs_external_url', 'https://educalabs.educatudo.com/login')), 'aluno' => true, 'professor' => false, 'nova_guia' => true],
            ['id' => 'games', 'app' => 'games', 'nome' => 'Games', 'url' => trim((string) LayoutHelper::get('games_external_url', 'https://games.educatudo.com')), 'aluno' => true, 'professor' => false, 'nova_guia' => true],
            ['id' => 'notes', 'app' => 'notes', 'nome' => 'Notes', 'url' => trim((string) LayoutHelper::get('notes_external_url', 'https://notes.educatudo.com')), 'aluno' => true, 'professor' => false, 'nova_guia' => true],
            ['id' => 'educaprof', 'app' => 'educaprof', 'nome' => 'Educa Prof', 'url' => trim((string) LayoutHelper::get('educaprof_external_url', self::EDUCAPROF_DEFAULT_URL)), 'aluno' => false, 'professor' => true, 'nova_guia' => true],
        ];
        return array_values(array_filter($defaults, function ($item) {
            return trim((string) ($item['url'] ?? '')) !== '';
        }));
    }

    private function findLinkById(string $id, array $links): ?array
    {
        foreach ($links as $link) {
            if ((string) ($link['id'] ?? '') === $id) {
                return $link;
            }
        }
        return null;
    }

    /**
     * Monta a URL do app externo com apenas token e slug.
     * O app já sabe que deve validar em master.educatudo.com/external-apps/validate-token.
     */
    private function buildExternalAccessUrl(string $baseUrl, string $token, string $slug, string $instId, string $appKey = ''): string
    {
        if (strpos($baseUrl, '{token}') !== false || strpos($baseUrl, '{slug}') !== false) {
            $url = str_replace('{token}', urlencode($token), $baseUrl);
            $url = str_replace('{slug}', urlencode($slug), $url);
            return $url;
        }

        $parts = parse_url($baseUrl);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['token'] = $token;
        if ($slug !== '') {
            $query['slug'] = $slug;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $base = $host !== '' ? ($scheme . '://' . $host . $port . $path) : $baseUrl;
        $url = $base . '?' . http_build_query($query);
        if (!empty($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        return $url;
    }

    /**
     * Slug a enviar no link: prioriza o campo "Slug da escola" (config); senão detectTenantSlug().
     */
    private function getSlugForExternalLink(): string
    {
        $slugFromConfig = trim((string) LayoutHelper::get('external_institution_id', ''));
        $slug = ($slugFromConfig !== '' && !ctype_digit($slugFromConfig))
            ? strtolower($slugFromConfig)
            : $this->detectTenantSlug();
        return $this->slugForExternalLink($slug, $slugFromConfig);
    }

    private function detectTenantSlug(): string
    {
        $hostSlug = $this->getSlugFromHost();
        $fromSession = trim((string) ($_SESSION['tenant_slug'] ?? $_SESSION['school_slug'] ?? $_SESSION['escola_slug'] ?? ''));
        $fromConfig = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? ''));
        $fromLayout = trim((string) LayoutHelper::get('tenant_slug', ''));

        if ($hostSlug !== '' && !ctype_digit($hostSlug)) {
            return strtolower($hostSlug);
        }
        if ($fromSession !== '' && !ctype_digit($fromSession)) {
            return strtolower($fromSession);
        }
        if ($fromConfig !== '' && !ctype_digit($fromConfig)) {
            return strtolower($fromConfig);
        }
        if ($fromLayout !== '' && !ctype_digit($fromLayout)) {
            return strtolower($fromLayout);
        }
        if ($hostSlug !== '') {
            return strtolower($hostSlug);
        }
        if ($fromSession !== '') {
            return strtolower($fromSession);
        }
        if ($fromConfig !== '') {
            return strtolower($fromConfig);
        }
        if ($fromLayout !== '') {
            return strtolower($fromLayout);
        }
        return '';
    }

    private function getSlugFromHost(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        if ($host === '') {
            return '';
        }
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            return strtolower(trim((string) $parts[0]));
        }
        return '';
    }

    /**
     * Slug a ser enviado no link externo. Nunca envia slug puramente numérico (evita ID/inst como slug).
     * Se o único valor disponível for numérico, retorna vazio; o app pode enviar inst na validação.
     */
    private function slugForExternalLink(string $detectedSlug, string $instId): string
    {
        $s = strtolower(trim($detectedSlug));
        if ($s !== '' && ctype_digit($s)) {
            return '';
        }
        return $s;
    }

    private function generateAccessToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function maskToken(string $token): string
    {
        $len = strlen($token);
        if ($len <= 12) {
            return str_repeat('*', $len);
        }
        return substr($token, 0, 6) . '...' . substr($token, -4);
    }

    private function getDatabaseName(Database $db): string
    {
        try {
            $row = $db->fetch('SELECT DATABASE() AS db_name');
            return (string) ($row['db_name'] ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function resolveAppKey(array $link): string
    {
        $candidates = [
            (string) ($link['app'] ?? ''),
            (string) ($link['code'] ?? ''),
            (string) ($link['id'] ?? ''),
            (string) ($link['nome'] ?? $link['label'] ?? ''),
        ];
        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeAppKey($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $url = trim((string) ($link['url'] ?? ''));
        if ($url !== '') {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $normalizedHost = $this->normalizeAppKey($host);
            if ($normalizedHost !== '') {
                return $normalizedHost;
            }
        }

        return 'external';
    }

    private function normalizeAppKey(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        if (strpos($value, 'tudinha 2') !== false || strpos($value, 'tudinha2') !== false || strpos($value, 'tudinha-2') !== false) {
            return 'tudinha2';
        }
        if (strpos($value, 'educalabs') !== false) {
            return 'educalabs';
        }
        if (strpos($value, 'games') !== false) {
            return 'games';
        }
        if (strpos($value, 'notes') !== false) {
            return 'notes';
        }
        if (strpos($value, 'educa hits') !== false || strpos($value, 'educahits') !== false || strpos($value, 'educa-hits') !== false) {
            return 'educahits';
        }
        if (strpos($value, 'educa prof') !== false || strpos($value, 'educaprof') !== false || strpos($value, 'educa-prof') !== false) {
            return 'educaprof';
        }
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
        $value = trim($value, '-_');
        return $value;
    }

    private function inferAppFromRequestContext(): string
    {
        $sources = [
            (string) ($_SERVER['HTTP_REFERER'] ?? ''),
            (string) ($_SERVER['HTTP_ORIGIN'] ?? ''),
        ];
        foreach ($sources as $source) {
            if ($source === '') {
                continue;
            }
            $host = strtolower((string) parse_url($source, PHP_URL_HOST));
            $normalized = $this->normalizeAppKey($host !== '' ? $host : $source);
            if ($normalized !== '') {
                return $normalized;
            }
        }
        return '';
    }

    private function resolveRequestAppKey(): string
    {
        $requestedApp = $this->normalizeAppKey((string) ($this->getRequestValue('app') ?? ''));
        if ($requestedApp === '') {
            return '';
        }

        if (!in_array($requestedApp, ['chat', 'message', 'mensagem', 'image', 'imagem', 'presentation'], true)) {
            return $requestedApp;
        }

        $contextApp = $this->inferAppFromRequestContext();
        if ($contextApp !== '') {
            return $contextApp;
        }

        return $requestedApp;
    }

    private function resolveTokensTable(Database $db): string
    {
        try {
            if ($db->tableExists(self::TOKENS_TABLE)) {
                return self::TOKENS_TABLE;
            }
            if ($db->tableExists(self::TOKENS_TABLE_LEGACY)) {
                $this->logTokenDebug('token_table.legacy_fallback', [
                    'db' => $this->getDatabaseName($db),
                    'table' => self::TOKENS_TABLE_LEGACY,
                    'target_table' => self::TOKENS_TABLE,
                ]);
                return self::TOKENS_TABLE_LEGACY;
            }

            $db->query(
                "CREATE TABLE IF NOT EXISTS " . self::TOKENS_TABLE . " (
                    id INT NOT NULL AUTO_INCREMENT,
                    token VARCHAR(128) NOT NULL,
                    app VARCHAR(100) DEFAULT NULL,
                    user_id INT NOT NULL,
                    user_name VARCHAR(100) NOT NULL,
                    user_nickname VARCHAR(100) DEFAULT NULL,
                    tenant_slug VARCHAR(120) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_token (token),
                    KEY idx_token (token),
                    KEY idx_user_id (user_id),
                    KEY idx_expires_at (expires_at),
                    KEY idx_tenant_slug (tenant_slug),
                    KEY idx_app (app)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            return self::TOKENS_TABLE;
        } catch (Throwable $e) {
            $this->logTokenDebug('token_table.resolve_error', [
                'db' => $this->getDatabaseName($db),
                'error' => $e->getMessage(),
            ]);
            return self::TOKENS_TABLE_LEGACY;
        }
    }

    private function fetchStudentNickname(Database $db, int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        try {
            $row = $db->fetch(
                "SELECT nickname FROM alunos WHERE id = ? LIMIT 1",
                [$userId]
            );
            return trim((string) ($row['nickname'] ?? ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    private function findConfiguredExternalLinkByApp(Database $db, string $appKey): array
    {
        $appKey = $this->normalizeAppKey($appKey);
        if ($appKey === '') {
            return [];
        }
        $links = $this->readExternalAppsLinksFromDb($db);
        foreach ($links as $idx => $link) {
            if (!is_array($link)) {
                continue;
            }
            $candidate = $this->resolveAppKey($link);
            if ($candidate === $appKey) {
                $link['id'] = (string) ($link['id'] ?? $idx);
                return $link;
            }
        }
        return [];
    }

    private function readExternalAppsLinksFromDb(Database $db): array
    {
        $raw = '';
        try {
            $row = $db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ? LIMIT 1",
                ['external_apps_links']
            );
            $raw = (string) ($row['config_value'] ?? '');
        } catch (Throwable $e) {
            $raw = '';
        }
        $links = json_decode($raw, true);
        if (!is_array($links) || empty($links)) {
            return $this->buildLegacyLinks();
        }
        return $links;
    }

    private function buildStudentContextPayload(Database $db, int $alunoId): array
    {
        $base = [
            'aluno' => [],
            'provas' => [],
            'jornadas' => [],
            'planos_aula' => [],
            'gerado_em' => date('c'),
        ];
        if ($alunoId <= 0) {
            return $base;
        }

        $base['aluno'] = $this->fetchAlunoBaseContext($db, $alunoId);
        $base['provas'] = $this->fetchAlunoProvasContext($db, $alunoId);
        $base['jornadas'] = $this->fetchAlunoJornadasContext($db, $alunoId, (int) ($base['aluno']['turma_id'] ?? 0));
        $base['planos_aula'] = $this->fetchAlunoPlanosAulaContext($db, (int) ($base['aluno']['turma_id'] ?? 0));

        return $base;
    }

    private function fetchAlunoBaseContext(Database $db, int $alunoId): array
    {
        try {
            $row = $db->fetch(
                "SELECT a.id, a.nome, a.nickname, a.email, a.turma_id, t.nome AS turma_nome, t.serie AS turma_serie
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.id = ?
                 LIMIT 1",
                [$alunoId]
            );
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        try {
            $row = $db->fetch(
                "SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1",
                [$table, $column]
            );
            return !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function fetchAlunoProvasContext(Database $db, int $alunoId): array
    {
        try {
            if (!$db->tableExists('provas_realizacoes') || !$db->tableExists('provas')) {
                return [];
            }
            $hasProvasMateriaId = $this->hasColumn($db, 'provas', 'materia_id');
            $hasRealizacoesMateria = $this->hasColumn($db, 'provas_realizacoes', 'materia');
            $hasRealizacoesDisciplina = $this->hasColumn($db, 'provas_realizacoes', 'disciplina');
            $hasRealizacoesAreaConhecimento = $this->hasColumn($db, 'provas_realizacoes', 'area_conhecimento');
            $hasQuestoesTotal = $this->hasColumn($db, 'provas_realizacoes', 'questoes_total');
            $hasTotalQuestoes = $this->hasColumn($db, 'provas_realizacoes', 'total_questoes');
            $hasQtdQuestoes = $this->hasColumn($db, 'provas_realizacoes', 'qtd_questoes');
            $hasQuestoesCorretas = $this->hasColumn($db, 'provas_realizacoes', 'questoes_corretas');
            $hasAcertos = $this->hasColumn($db, 'provas_realizacoes', 'acertos');
            $hasQtdAcertos = $this->hasColumn($db, 'provas_realizacoes', 'qtd_acertos');
            $hasStatus = $this->hasColumn($db, 'provas_realizacoes', 'status');
            $hasNota = $this->hasColumn($db, 'provas_realizacoes', 'nota');
            $hasIniciadoEm = $this->hasColumn($db, 'provas_realizacoes', 'iniciado_em');
            $hasFinalizadoEm = $this->hasColumn($db, 'provas_realizacoes', 'finalizado_em');
            $hasRespostasCorreta = $db->tableExists('provas_respostas') && $this->hasColumn($db, 'provas_respostas', 'correta');
            $hasProvasProfessorId = $this->hasColumn($db, 'provas', 'professor_id');

            $materiaParts = [];
            if ($hasProvasMateriaId) {
                $materiaParts[] = "NULLIF(TRIM(COALESCE(m.nome, '')), '')";
            }
            if ($hasRealizacoesMateria) {
                $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.materia, '')), '')";
            }
            if ($hasRealizacoesDisciplina) {
                $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.disciplina, '')), '')";
            }
            if ($hasRealizacoesAreaConhecimento) {
                $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.area_conhecimento, '')), '')";
            }
            $materiaExpr = !empty($materiaParts) ? "COALESCE(" . implode(', ', $materiaParts) . ")" : "NULL";

            $qtdParts = [];
            if ($hasQuestoesTotal) {
                $qtdParts[] = "pr.questoes_total";
            }
            if ($hasTotalQuestoes) {
                $qtdParts[] = "pr.total_questoes";
            }
            if ($hasQtdQuestoes) {
                $qtdParts[] = "pr.qtd_questoes";
            }
            $qtdParts[] = "(SELECT COUNT(*) FROM provas_questoes pq WHERE pq.prova_id = pr.prova_id)";
            $qtdExpr = "COALESCE(" . implode(', ', $qtdParts) . ", 0)";

            $acertosParts = [];
            if ($hasQuestoesCorretas) {
                $acertosParts[] = "pr.questoes_corretas";
            }
            if ($hasAcertos) {
                $acertosParts[] = "pr.acertos";
            }
            if ($hasQtdAcertos) {
                $acertosParts[] = "pr.qtd_acertos";
            }
            if ($hasRespostasCorreta) {
                $acertosParts[] = "(SELECT COUNT(*) FROM provas_respostas prs WHERE prs.prova_id = pr.prova_id AND prs.aluno_id = pr.aluno_id AND prs.correta = 1)";
            }
            $acertosExpr = !empty($acertosParts) ? "COALESCE(" . implode(', ', $acertosParts) . ", 0)" : "0";
            $joinMateria = $hasProvasMateriaId ? "LEFT JOIN materias m ON m.id = p.materia_id" : "";
            $joinProfessor = $hasProvasProfessorId ? "LEFT JOIN professores prof ON prof.id = p.professor_id" : "";
            $profNomeExpr = $hasProvasProfessorId ? "prof.nome" : "NULL";
            $notaExpr = $hasNota ? "pr.nota" : "NULL";
            $statusExpr = $hasStatus ? "pr.status" : "NULL";
            $iniciadoExpr = $hasIniciadoEm ? "pr.iniciado_em" : "pr.id";
            $finalizadoExpr = $hasFinalizadoEm ? "pr.finalizado_em" : "NULL";

            $orderExpr = $hasIniciadoEm ? "pr.iniciado_em" : "pr.id";
            $rows = $db->fetchAll(
                "SELECT pr.id AS realizacao_id, pr.prova_id, p.titulo AS prova_titulo,
                        {$materiaExpr} AS materia_nome,
                        {$qtdExpr} AS total_questoes,
                        {$acertosExpr} AS acertos,
                        GREATEST({$qtdExpr} - {$acertosExpr}, 0) AS erros,
                        {$notaExpr} AS nota, {$statusExpr} AS status, {$iniciadoExpr} AS iniciado_em, {$finalizadoExpr} AS finalizado_em,
                        {$profNomeExpr} AS professor_nome
                 FROM provas_realizacoes pr
                 INNER JOIN provas p ON p.id = pr.prova_id
                 {$joinMateria}
                 {$joinProfessor}
                 WHERE pr.aluno_id = ?
                 ORDER BY {$orderExpr} DESC
                 LIMIT 80",
                [$alunoId]
            );
            if (!is_array($rows) || empty($rows)) {
                return [];
            }
            $this->attachProvasQuestoesContext($db, $rows, $alunoId);
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function attachProvasQuestoesContext(Database $db, array &$provas, int $alunoId): void
    {
        if (!$db->tableExists('provas_questoes') || !$db->tableExists('provas_respostas')) {
            return;
        }
        foreach ($provas as &$prova) {
            $provaId = (int) ($prova['prova_id'] ?? 0);
            if ($provaId <= 0) {
                $prova['questoes'] = [];
                continue;
            }
            try {
                $questoes = $db->fetchAll(
                    "SELECT id, enunciado, tipo, ordem
                     FROM provas_questoes
                     WHERE prova_id = ?
                     ORDER BY ordem ASC, id ASC",
                    [$provaId]
                );
                if (!is_array($questoes)) {
                    $questoes = [];
                }
                $respRows = $db->fetchAll(
                    "SELECT questao_id, alternativa_id, resposta_texto, correta
                     FROM provas_respostas
                     WHERE prova_id = ? AND aluno_id = ?",
                    [$provaId, $alunoId]
                );
                $respostasByQuestao = [];
                if (is_array($respRows)) {
                    foreach ($respRows as $r) {
                        $qid = (int) ($r['questao_id'] ?? 0);
                        if ($qid <= 0) {
                            continue;
                        }
                        $respostasByQuestao[$qid] = $r;
                    }
                }
                $qids = [];
                foreach ($questoes as $q) {
                    $qid = (int) ($q['id'] ?? 0);
                    if ($qid > 0) {
                        $qids[] = $qid;
                    }
                }
                $alternativasByQuestao = [];
                if (!empty($qids) && $db->tableExists('provas_alternativas')) {
                    $place = implode(',', array_fill(0, count($qids), '?'));
                    $alts = $db->fetchAll(
                        "SELECT id, questao_id, texto, correta, ordem
                         FROM provas_alternativas
                         WHERE questao_id IN ({$place})
                         ORDER BY questao_id ASC, ordem ASC, id ASC",
                        $qids
                    );
                    if (is_array($alts)) {
                        foreach ($alts as $a) {
                            $qid = (int) ($a['questao_id'] ?? 0);
                            if (!isset($alternativasByQuestao[$qid])) {
                                $alternativasByQuestao[$qid] = [];
                            }
                            $alternativasByQuestao[$qid][] = $a;
                        }
                    }
                }
                foreach ($questoes as &$q) {
                    $qid = (int) ($q['id'] ?? 0);
                    $q['alternativas'] = $alternativasByQuestao[$qid] ?? [];
                    $q['resposta_aluno'] = $respostasByQuestao[$qid] ?? null;
                    $q['acertou'] = isset($respostasByQuestao[$qid]['correta']) ? ((int) $respostasByQuestao[$qid]['correta'] === 1) : null;
                }
                unset($q);
                $prova['questoes'] = $questoes;
            } catch (Throwable $e) {
                $prova['questoes'] = [];
            }
        }
        unset($prova);
    }

    private function fetchAlunoJornadasContext(Database $db, int $alunoId, int $turmaId): array
    {
        if ($alunoId <= 0 || $turmaId <= 0 || !$db->tableExists('jornadas')) {
            return [];
        }
        try {
            $hasJornadasMateriaId = $this->hasColumn($db, 'jornadas', 'materia_id');
            $hasJornadasProgresso = $db->tableExists('jornadas_progresso_alunos');
            $joinMateria = $hasJornadasMateriaId ? "LEFT JOIN materias m ON m.id = j.materia_id" : "";
            $materiaExpr = $hasJornadasMateriaId ? "m.nome" : "NULL";
            $joinProgresso = $hasJornadasProgresso ? "LEFT JOIN jornadas_progresso_alunos jpa ON jpa.jornada_id = j.id AND jpa.aluno_id = :aluno_id" : "";
            $statusExpr = $hasJornadasProgresso ? "jpa.status" : "NULL";
            $conclusaoExpr = $hasJornadasProgresso ? "jpa.data_conclusao" : "NULL";

            $params = ['turma_id' => $turmaId];
            if ($hasJornadasProgresso) {
                $params['aluno_id'] = $alunoId;
            }
            $sql =
                "SELECT j.id AS jornada_id, j.titulo, {$materiaExpr} AS materia_nome,
                        p.nome AS professor_nome, {$statusExpr} AS status_aluno, {$conclusaoExpr} AS data_conclusao,
                        j.created_at
                 FROM jornadas j
                 LEFT JOIN professores p ON p.id = j.professor_id
                 {$joinMateria}
                 {$joinProgresso}
                 WHERE j.turma_id = :turma_id
                 ORDER BY j.created_at DESC
                 LIMIT 100";
            $rows = $db->fetchAll($sql, $params);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function fetchAlunoPlanosAulaContext(Database $db, int $turmaId): array
    {
        if ($turmaId <= 0 || !$db->tableExists('planos_aula')) {
            return [];
        }
        try {
            $hasDeletedAt = $this->hasColumn($db, 'planos_aula', 'deleted_at');
            $hasDataAula = $this->hasColumn($db, 'planos_aula', 'data_aula');
            $hasDataInicio = $this->hasColumn($db, 'planos_aula', 'data_inicio');
            $hasDataFim = $this->hasColumn($db, 'planos_aula', 'data_fim');
            $whereDeleted = $hasDeletedAt ? "AND pa.deleted_at IS NULL" : "";
            $dataAulaExpr = $hasDataAula ? "pa.data_aula" : "NULL";
            $dataInicioExpr = $hasDataInicio ? "pa.data_inicio" : "NULL";
            $dataFimExpr = $hasDataFim ? "pa.data_fim" : "NULL";
            $rows = $db->fetchAll(
                "SELECT pa.id, pa.titulo, {$dataAulaExpr} AS data_aula, {$dataInicioExpr} AS data_inicio, {$dataFimExpr} AS data_fim, pa.created_at,
                        p.nome AS professor_nome, m.nome AS materia_nome
                 FROM planos_aula pa
                 LEFT JOIN professores p ON pa.professor_id = p.id
                 LEFT JOIN materias m ON pa.materia_id = m.id
                 WHERE pa.turma_id = ?
                 {$whereDeleted}
                 ORDER BY pa.created_at DESC
                 LIMIT 120",
                [$turmaId]
            );
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function logTokenDebug(string $event, array $data = []): void
    {
        try {
            $logDir = __DIR__ . '/../../../storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/external_apps_token_' . date('Y-m-d') . '.log';
            $line = '[' . date('Y-m-d H:i:s') . '] [' . $event . '] '
                . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . PHP_EOL;
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            error_log('[ExternalAppsToken][log.error] ' . $e->getMessage());
        }
    }

    /**
     * Grava falha de validação na tabela do tenant (ou master) para o Master ver em Apps Externos.
     * Se o endpoint for master.educatudo.com e o slug não for enviado, $db é o banco master — execute 033 no master.
     * Em caso de falha no insert (ex.: tabela não existe no tenant), tenta gravar no $this->db (master) como fallback.
     */
    private function writeValidationFailLog($db, string $app, string $evento, array $detalhes = []): void
    {
        $json = json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $targets = [$db];
        if (isset($this->db) && $this->db !== $db) {
            $targets[] = $this->db;
        }
        foreach ($targets as $target) {
            try {
                if (!is_object($target) || !method_exists($target, 'insert')) {
                    continue;
                }
                $target->insert(
                    "INSERT INTO log_validacao_apps_externos (app, evento, detalhes) VALUES (?, ?, ?)",
                    [$app, $evento, $json]
                );
                return;
            } catch (Throwable $e) {
                error_log('[ExternalAppsToken][writeValidationFailLog] ' . $e->getMessage());
            }
        }
    }
}
}
