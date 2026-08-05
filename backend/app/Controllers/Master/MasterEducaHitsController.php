<?php
/**
 * EducaTudo - Master - EducaHits
 * Lista pedidos gravados nos tenants (educa_hits_requests), entrega via API externa e link opcional ao portal.
 */

if (!class_exists('MasterEducaHitsController')) {

require_once __DIR__ . '/../../Core/EducaHitsConfig.php';

class MasterEducaHitsController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    /** Itens por página na listagem de pedidos (master). */
    private const EDUCAHITS_PEDIDOS_PER_PAGE_OPTIONS = [25, 50, 100];

    public function __construct()
    {
        parent::__construct();
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            if (!headers_sent()) {
                header('Location: ' . URL . '/master');
            } else {
                echo '<script>window.location.href=' . json_encode(URL . '/master') . ';</script>';
            }
            exit;
        }
    }

    /**
     * Flash + redirect com metadados para o console do browser (F12).
     *
     * @param array<string, mixed> $meta
     */
    private function flashDeliverError(string $message, array $meta = []): void
    {
        $this->setFlashMessage($message, 'error', array_merge([
            'source' => 'educahits_deliver',
            'ts' => gmdate('c'),
        ], $meta));
        header('Location: ' . rtrim(URL, '/') . '/master/educa-hits/cadastro');
        exit;
    }

    private static function clipDebugText(string $s, int $max = 6000): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($s) > $max ? mb_substr($s, 0, $max, 'UTF-8') . '…' : $s;
        }

        return strlen($s) > $max ? substr($s, 0, $max) . '…' : $s;
    }

    private static function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $last = strtolower(substr($value, -1));
        $num = (float) $value;

        return match ($last) {
            'g' => (int) ($num * 1024 * 1024 * 1024),
            'm' => (int) ($num * 1024 * 1024),
            'k' => (int) ($num * 1024),
            default => (int) $num,
        };
    }

    /**
     * Detecta quando o PHP descarta $_POST por limite de upload (post_max_size / upload_max_filesize).
     */
    private function isPostLikelyDiscardedByPhpLimit(): bool
    {
        $isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
        if (!$isPost) {
            return false;
        }
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0) {
            return false;
        }
        $postMax = self::iniToBytes((string) ini_get('post_max_size'));
        if ($postMax > 0 && $contentLength > $postMax) {
            return true;
        }

        return empty($_POST) && empty($_FILES);
    }

    private function isCurlErrorRetryable(string $curlError): bool
    {
        $e = strtolower($curlError);
        if ($e === '') {
            return true;
        }
        foreach (['timeout', 'timed out', 'connection reset', 'connection refused', 'could not resolve', 'recv failure', 'empty reply', 'ssl'] as $needle) {
            if (strpos($e, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $file */
    private function detectFileExt(array $file, string $fallback): string
    {
        $name = trim((string) ($file['name'] ?? ''));
        if ($name === '') {
            return $fallback;
        }
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : $fallback;
    }

    /** @return array<int,string> */
    private function buildReceiveHeaders(string $url, string $bearer, bool $isJson): array
    {
        $headers = [
            'Authorization: Bearer ' . $bearer,
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
        ];
        if ($isJson) {
            $headers[] = 'Content-Type: application/json';
        }
        if (stripos($url, 'supabase.co') !== false) {
            $headers[] = 'apikey: ' . EducaHitsConfig::receiveSupabaseGatewayApiKey();
        }

        return $headers;
    }

    /**
     * @param array<string,mixed> $json
     * @return array{audio:string,cover:string}
     */
    private function extractUploadUrls(array $json): array
    {
        $containers = [];
        if (isset($json['upload_urls']) && is_array($json['upload_urls'])) {
            $containers[] = $json['upload_urls'];
        }
        if (isset($json['uploadUrls']) && is_array($json['uploadUrls'])) {
            $containers[] = $json['uploadUrls'];
        }
        if (isset($json['data']) && is_array($json['data'])) {
            if (isset($json['data']['upload_urls']) && is_array($json['data']['upload_urls'])) {
                $containers[] = $json['data']['upload_urls'];
            }
            if (isset($json['data']['uploadUrls']) && is_array($json['data']['uploadUrls'])) {
                $containers[] = $json['data']['uploadUrls'];
            }
        }

        foreach ($containers as $c) {
            $audio = $this->extractSingleUploadUrl($c, ['audio', 'audio_url', 'audioUrl']);
            $cover = $this->extractSingleUploadUrl($c, ['cover', 'cover_url', 'coverUrl']);
            if ($audio !== '') {
                return ['audio' => $audio, 'cover' => $cover];
            }
        }

        return ['audio' => '', 'cover' => ''];
    }

    /**
     * @param array<string,mixed> $container
     * @param array<int,string> $keys
     */
    private function extractSingleUploadUrl(array $container, array $keys): string
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $container)) {
                continue;
            }
            $v = $container[$k];
            if (is_string($v)) {
                $url = trim($v);
                if ($url !== '') {
                    return $url;
                }
            }
            if (is_array($v)) {
                foreach (['url', 'signed_url', 'signedUrl', 'href', 'upload_url', 'uploadUrl'] as $nestedKey) {
                    if (isset($v[$nestedKey]) && is_string($v[$nestedKey])) {
                        $url = trim($v[$nestedKey]);
                        if ($url !== '') {
                            return $url;
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * Upload direto no Storage via URL assinada (PUT).
     *
     * @return array<string,mixed>
     */
    private function putFileToSignedUrl(string $signedUrl, string $tmpPath, string $mime, int $timeout): array
    {
        $fp = @fopen($tmpPath, 'rb');
        if ($fp === false) {
            return [
                'ok' => false,
                'http_status' => 0,
                'curl_error' => 'Falha ao abrir arquivo temporário.',
                'body_preview' => '',
            ];
        }
        $size = @filesize($tmpPath);
        if (!is_int($size) || $size < 0) {
            $size = 0;
        }

        $ch = curl_init($signedUrl);
        if ($ch === false) {
            fclose($fp);

            return [
                'ok' => false,
                'http_status' => 0,
                'curl_error' => 'Falha em curl_init para signed URL.',
                'body_preview' => '',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => $size,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 45,
            CURLOPT_TIMEOUT => max(60, $timeout),
            CURLOPT_HTTPHEADER => ['Content-Type: ' . $mime],
        ]);
        $raw = curl_exec($ch);
        $curlErr = (string) curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        $rawStr = is_string($raw) ? $raw : '';
        $ok = $httpCode >= 200 && $httpCode < 300;

        return [
            'ok' => $ok,
            'http_status' => $httpCode,
            'curl_error' => $curlErr,
            'body_preview' => self::clipDebugText(trim($rawStr), 1200),
        ];
    }

    private function getActiveSchoolsForSelect(): array
    {
        $db = Database::getInstance();
        try {
            $stmt = $db->query('SELECT id, nome, slug FROM escolas WHERE ativo = 1 ORDER BY nome');

            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Pedidos armazenados no banco de cada escola (fluxo antigo / app EducaTudo).
     *
     * @return list<array<string,mixed>>
     */
    private function fetchLocalRequestsFromDatabases(): array
    {
        $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($masterPdo instanceof PDO && defined('MULTI_TENANT_ACTIVE') && MULTI_TENANT_ACTIVE === true) {
            if (!class_exists('DatabaseManager', false)) {
                require_once __DIR__ . '/../../Core/DatabaseManager.php';
            }
            $db = Database::getInstance();
            $all = [];
            try {
                // Todas as escolas ativas; tenta abrir o banco de cada uma (só entra quem tem config_escolas_banco).
                $schools = $db->query(
                    'SELECT e.id, e.nome, e.slug FROM escolas e WHERE e.ativo = 1 ORDER BY e.nome'
                )->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                return [];
            }
            $manager = new DatabaseManager($masterPdo);
            foreach ($schools as $s) {
                $eid = (int) ($s['id'] ?? 0);
                if ($eid <= 0) {
                    continue;
                }
                try {
                    $tenantDb = $manager->getConnectionForTenant($eid);
                    $rows = $tenantDb->fetchAll(
                        'SELECT r.id, r.user_id, r.school_id, r.class_id, r.grade, r.subject, r.topic,
                                r.music_style, r.description, r.status, r.created_at, r.updated_at,
                                a.nome AS aluno_nome
                         FROM educa_hits_requests r
                         LEFT JOIN alunos a ON a.id = r.user_id
                         ORDER BY r.created_at DESC
                         LIMIT 200'
                    );
                    foreach ($rows as $r) {
                        $r['escola_id'] = $eid;
                        $r['escola_nome'] = (string) ($s['nome'] ?? '');
                        $r['escola_slug'] = (string) ($s['slug'] ?? '');
                        $all[] = $r;
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }
            usort($all, static function ($a, $b) {
                return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
            });

            return array_slice($all, 0, 500);
        }

        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll(
                'SELECT r.id, r.user_id, r.school_id, r.class_id, r.grade, r.subject, r.topic,
                        r.music_style, r.description, r.status, r.created_at, r.updated_at,
                        a.nome AS aluno_nome
                 FROM educa_hits_requests r
                 LEFT JOIN alunos a ON a.id = r.user_id
                 ORDER BY r.created_at DESC
                 LIMIT 500'
            );
            foreach ($rows as &$r) {
                $r['escola_nome'] = '';
                $r['escola_slug'] = '';
            }
            unset($r);

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Opcional: GET JSON (Bearer igual à entrega) — formatos aceites: array, { "requests": [...] }, { "data": [...] }.
     *
     * @return list<array<string,mixed>>
     */
    private function fetchOptionalRequestsJson(): array
    {
        $url = EducaHitsConfig::masterRequestsApiUrl();
        $bearer = EducaHitsConfig::receiveBearerToken();
        if ($url === '' || $bearer === '' || !function_exists('curl_init')) {
            return [];
        }
        $reqHeaders = [
            'Authorization: Bearer ' . $bearer,
            'Accept: application/json',
        ];
        if (stripos($url, 'supabase.co') !== false) {
            $reqHeaders[] = 'apikey: ' . EducaHitsConfig::receiveSupabaseGatewayApiKey();
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return [];
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $reqHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        if (isset($decoded['requests']) && is_array($decoded['requests'])) {
            return $decoded['requests'];
        }
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }
        $isList = array_keys($decoded) === range(0, count($decoded) - 1);

        return $isList ? $decoded : [];
    }

    /**
     * Opcional: GET JSON de últimas músicas.
     * Formatos aceites: array, { "songs": [...] }, { "data": [...] }, { "musics": [...] }.
     *
     * @return list<array<string,mixed>>
     */
    private function fetchOptionalLatestSongsJson(): array
    {
        $url = EducaHitsConfig::masterSongsApiUrl();
        $bearer = EducaHitsConfig::receiveBearerToken();
        if ($url === '' || $bearer === '' || !function_exists('curl_init')) {
            return [];
        }
        $reqHeaders = [
            'Authorization: Bearer ' . $bearer,
            'Accept: application/json',
        ];
        if (stripos($url, 'supabase.co') !== false) {
            $reqHeaders[] = 'apikey: ' . EducaHitsConfig::receiveSupabaseGatewayApiKey();
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return [];
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $reqHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        if (isset($decoded['songs']) && is_array($decoded['songs'])) {
            return $decoded['songs'];
        }
        if (isset($decoded['musics']) && is_array($decoded['musics'])) {
            return $decoded['musics'];
        }
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }
        $isList = array_keys($decoded) === range(0, count($decoded) - 1);

        return $isList ? $decoded : [];
    }

    private function baseViewData(array $extra = []): array
    {
        return array_merge([
            'title' => 'EducaHits',
            'current_page' => 'educa_hits',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'flash' => $this->getFlashMessage(),
            'csrf_token' => $this->generateCsrfToken(),
            'educahits_master_dashboard' => EducaHitsConfig::masterDashboardUrl(),
            'educahits_receive_url' => EducaHitsConfig::receiveSongUrl(),
            'educahits_delete_song_url' => EducaHitsConfig::deleteSongApiUrl(),
        ], $extra);
    }

    public function index(): void
    {
        $this->requireMaster();
        header('Location: ' . rtrim(URL, '/') . '/master/educa-hits/pedidos');
        exit;
    }

    public function pedidos(): void
    {
        $this->requireMaster();
        $requestsPreview = $this->fetchOptionalRequestsJson();
        $localRequests = $this->fetchLocalRequestsFromDatabases();
        $allSchools = [];
        $allSubjects = [];
        $allTopics = [];
        $allStyles = [];
        foreach ($localRequests as $row) {
            $school = trim((string) ($row['escola_nome'] ?? ''));
            $subject = trim((string) ($row['subject'] ?? ''));
            $topic = trim((string) ($row['topic'] ?? ''));
            $style = trim((string) ($row['music_style'] ?? ''));
            if ($school !== '') {
                $allSchools[$school] = $school;
            }
            if ($subject !== '') {
                $allSubjects[$subject] = $subject;
            }
            if ($topic !== '') {
                $allTopics[$topic] = $topic;
            }
            if ($style !== '') {
                $allStyles[$style] = $style;
            }
        }

        $statusFilterRaw = strtolower(trim((string) ($_GET['status'] ?? '')));
        $allowedStatusFilters = ['pending', 'processing', 'approved', 'rejected', 'excluded', 'archived'];
        $statusFilter = in_array($statusFilterRaw, $allowedStatusFilters, true) ? $statusFilterRaw : 'pending';

        $filters = [
            'escola' => trim((string) ($_GET['escola'] ?? '')),
            'materia' => trim((string) ($_GET['materia'] ?? '')),
            'tema' => trim((string) ($_GET['tema'] ?? '')),
            'estilo' => trim((string) ($_GET['estilo'] ?? '')),
            'status' => $statusFilter,
        ];

        $localRequests = array_values(array_filter($localRequests, static function (array $row) use ($filters): bool {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $bucket = $filters['status'] ?? 'pending';
            $matchesBucket = match ($bucket) {
                'pending' => $status === 'pending' || $status === '',
                'processing' => in_array($status, ['processing', 'in_progress'], true),
                'approved' => in_array($status, ['approved', 'completed'], true),
                'rejected' => $status === 'rejected',
                'excluded' => in_array($status, ['excluded', 'deleted'], true),
                'archived' => in_array($status, ['archived', 'arquivado'], true),
                default => $status === 'pending',
            };
            if (!$matchesBucket) {
                return false;
            }
            if ($filters['escola'] !== '' && trim((string) ($row['escola_nome'] ?? '')) !== $filters['escola']) {
                return false;
            }
            if ($filters['materia'] !== '' && trim((string) ($row['subject'] ?? '')) !== $filters['materia']) {
                return false;
            }
            if ($filters['tema'] !== '' && trim((string) ($row['topic'] ?? '')) !== $filters['tema']) {
                return false;
            }
            if ($filters['estilo'] !== '' && trim((string) ($row['music_style'] ?? '')) !== $filters['estilo']) {
                return false;
            }
            return true;
        }));

        $perPageRaw = (int) ($_GET['per_page'] ?? 25);
        $perPage = in_array($perPageRaw, self::EDUCAHITS_PEDIDOS_PER_PAGE_OPTIONS, true)
            ? $perPageRaw
            : 25;
        $totalPedidos = count($localRequests);
        $totalPages = $totalPedidos > 0 ? (int) ceil($totalPedidos / $perPage) : 0;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $localRequestsPaginated = $totalPedidos > 0
            ? array_slice($localRequests, $offset, $perPage)
            : [];
        $showEscolaCol = false;
        foreach ($localRequests as $_lr) {
            if (!empty($_lr['escola_nome'])) {
                $showEscolaCol = true;
                break;
            }
        }

        sort($allSchools);
        sort($allSubjects);
        sort($allTopics);
        sort($allStyles);
        $this->viewWithLayout('master', 'master/educa-hits/pedidos', $this->baseViewData([
            'page_title' => 'EducaHits — pedidos',
            'educahits_section' => 'pedidos',
            'requests_preview' => $requestsPreview,
            'local_requests' => $localRequestsPaginated,
            'filters' => $filters,
            'filter_escolas' => $allSchools,
            'filter_materias' => $allSubjects,
            'filter_temas' => $allTopics,
            'filter_estilos' => $allStyles,
            'educahits_pedidos_pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalPedidos,
                'total_pages' => $totalPages,
                'from' => $totalPedidos > 0 ? $offset + 1 : 0,
                'to' => $totalPedidos > 0 ? min($offset + $perPage, $totalPedidos) : 0,
            ],
            'educahits_pedidos_per_page_options' => self::EDUCAHITS_PEDIDOS_PER_PAGE_OPTIONS,
            'show_escola_col' => $showEscolaCol,
        ]));
    }

    public function musicas(): void
    {
        $this->requireMaster();
        $latestSongs = $this->fetchOptionalLatestSongsJson();
        $this->viewWithLayout('master', 'master/educa-hits/musicas', $this->baseViewData([
            'page_title' => 'EducaHits — músicas',
            'educahits_section' => 'musicas',
            'latest_songs' => $latestSongs,
        ]));
    }

    public function cadastro(): void
    {
        $this->requireMaster();
        $this->viewWithLayout('master', 'master/educa-hits/cadastro', $this->baseViewData([
            'page_title' => 'EducaHits — cadastro de músicas',
            'educahits_section' => 'cadastro',
            'escolas' => $this->getActiveSchoolsForSelect(),
        ]));
    }

    public function configuracao(): void
    {
        $this->requireMaster();
        $this->viewWithLayout('master', 'master/educa-hits/configuracao', $this->baseViewData([
            'page_title' => 'EducaHits — configuração',
            'educahits_section' => 'configuracao',
            'educahits_portal_login' => EducaHitsConfig::portalLoginUrl(),
            'educahits_portal_request' => EducaHitsConfig::portalRequestUrl(),
            'educahits_requests_api' => EducaHitsConfig::masterRequestsApiUrl(),
            'educahits_songs_api' => EducaHitsConfig::masterSongsApiUrl(),
        ]));
    }

    private function updateRequestStatusInStorage(int $requestId, string $newStatus, int $escolaId): bool
    {
        if ($requestId <= 0 || $newStatus === '') {
            return false;
        }

        $removeRequest = in_array($newStatus, ['excluded', 'deleted', 'archived', 'arquivado'], true);

        $statusCandidates = match ($newStatus) {
            'processing' => ['processing', 'in_progress'],
            'approved' => ['approved', 'completed'],
            'rejected' => ['rejected'],
            'excluded' => ['excluded', 'deleted'],
            'archived' => ['archived', 'arquivado'],
            'in_progress' => ['in_progress', 'processing'],
            'completed' => ['completed', 'approved'],
            'deleted' => ['deleted', 'excluded'],
            'arquivado' => ['arquivado', 'archived'],
            default => [$newStatus],
        };

        $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($masterPdo instanceof PDO && defined('MULTI_TENANT_ACTIVE') && MULTI_TENANT_ACTIVE === true) {
            if ($escolaId <= 0) {
                return false;
            }
            if (!class_exists('DatabaseManager', false)) {
                require_once __DIR__ . '/../../Core/DatabaseManager.php';
            }
            try {
                $manager = new DatabaseManager($masterPdo);
                $tenantDb = $manager->getConnectionForTenant($escolaId);

                if ($removeRequest) {
                    try {
                        $stmt = $tenantDb->query(
                            'DELETE FROM educa_hits_requests WHERE id = :id',
                            ['id' => $requestId]
                        );
                        if ($stmt instanceof PDOStatement && $stmt->rowCount() > 0) {
                            return true;
                        }
                        $exists = $tenantDb->fetch('SELECT id FROM educa_hits_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
                        return !(is_array($exists) && !empty($exists['id']));
                    } catch (Throwable $e) {
                        return false;
                    }
                }

                foreach ($statusCandidates as $candidate) {
                    try {
                        $stmt = $tenantDb->query(
                            'UPDATE educa_hits_requests SET status = :status WHERE id = :id',
                            ['status' => $candidate, 'id' => $requestId]
                        );
                        if ($stmt instanceof PDOStatement && $stmt->rowCount() > 0) {
                            return true;
                        }
                        $exists = $tenantDb->fetch('SELECT id FROM educa_hits_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
                        if (is_array($exists) && !empty($exists['id'])) {
                            return true;
                        }
                    } catch (Throwable $e) {
                        continue;
                    }
                }

                return false;
            } catch (Throwable $e) {
                return false;
            }
        }

        try {
            $db = Database::getInstance();
            if ($removeRequest) {
                try {
                    $stmt = $db->query(
                        'DELETE FROM educa_hits_requests WHERE id = :id',
                        ['id' => $requestId]
                    );
                    if ($stmt instanceof PDOStatement && $stmt->rowCount() > 0) {
                        return true;
                    }
                    $exists = $db->fetch('SELECT id FROM educa_hits_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
                    return !(is_array($exists) && !empty($exists['id']));
                } catch (Throwable $e) {
                    return false;
                }
            }

            foreach ($statusCandidates as $candidate) {
                try {
                    $stmt = $db->query(
                        'UPDATE educa_hits_requests SET status = :status WHERE id = :id',
                        ['status' => $candidate, 'id' => $requestId]
                    );
                    if ($stmt instanceof PDOStatement && $stmt->rowCount() > 0) {
                        return true;
                    }
                    $exists = $db->fetch('SELECT id FROM educa_hits_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
                    if (is_array($exists) && !empty($exists['id'])) {
                        return true;
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }

            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function updateRequestStatus(): void
    {
        $this->requireMaster();
        $redirect = rtrim(URL, '/') . '/master/educa-hits/pedidos';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $redirect);
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido para responder pedido.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $requestId = (int) ($_POST['request_id'] ?? 0);
        $escolaId = (int) ($_POST['escola_id'] ?? $_POST['school_id'] ?? 0);
        $rawStatus = strtolower(trim((string) ($_POST['status'] ?? '')));
        $allowed = [
            'approved' => 'approved',
            'rejected' => 'rejected',
            'processing' => 'processing',
            'excluded' => 'excluded',
            'archived' => 'archived',
        ];
        $newStatus = $allowed[$rawStatus] ?? '';

        if ($requestId <= 0 || $newStatus === '') {
            $this->setFlashMessage('Dados inválidos para atualizar o pedido.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $ok = $this->updateRequestStatusInStorage($requestId, $newStatus, $escolaId);
        if (!$ok) {
            $this->setFlashMessage('Não foi possível atualizar o status do pedido.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $labels = [
            'approved' => 'Aprovado',
            'rejected' => 'Recusado',
            'processing' => 'Em processamento',
            'excluded' => 'Excluído',
            'archived' => 'Arquivado',
        ];
        $this->setFlashMessage('Pedido #' . $requestId . ' atualizado para "' . ($labels[$newStatus] ?? $newStatus) . '".', 'success');
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * Atualiza status de vários pedidos (mesma regra de updateRequestStatus).
     */
    public function bulkRequestStatus(): void
    {
        $this->requireMaster();
        $redirect = rtrim(URL, '/') . '/master/educa-hits/pedidos';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $redirect);
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido para ação em massa.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $selected = $_POST['selected'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_filter(array_map('strval', $selected), static function ($v) {
            return $v !== '';
        }));

        $bulkAction = strtolower(trim((string) ($_POST['bulk_action'] ?? 'apply')));
        $allowed = [
            'approved' => 'approved',
            'rejected' => 'rejected',
            'processing' => 'processing',
            'excluded' => 'excluded',
            'archived' => 'archived',
        ];

        if ($bulkAction === 'excluded') {
            $newStatus = 'excluded';
        } else {
            $rawStatus = strtolower(trim((string) ($_POST['status'] ?? '')));
            $newStatus = $allowed[$rawStatus] ?? '';
        }

        if ($newStatus === '' || $selected === []) {
            $this->setFlashMessage('Selecione ao menos um pedido e uma ação válida.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $labels = [
            'approved' => 'Aprovado',
            'rejected' => 'Recusado',
            'processing' => 'Em processamento',
            'excluded' => 'Excluído',
            'archived' => 'Arquivado',
        ];

        $ok = 0;
        $fail = 0;
        foreach ($selected as $pair) {
            $parts = explode(':', (string) $pair, 2);
            $requestId = (int) ($parts[0] ?? 0);
            $escolaId = (int) ($parts[1] ?? 0);
            if ($requestId <= 0) {
                $fail++;
                continue;
            }
            if ($this->updateRequestStatusInStorage($requestId, $newStatus, $escolaId)) {
                $ok++;
            } else {
                $fail++;
            }
        }

        if ($ok === 0 && $fail > 0) {
            $this->setFlashMessage('Não foi possível aplicar o status aos pedidos selecionados.', 'error');
        } elseif ($fail > 0) {
            $this->setFlashMessage(
                'Processados ' . ($ok + $fail) . ' pedido(s): ' . $ok . ' com status “' . ($labels[$newStatus] ?? $newStatus) . '”, ' . $fail . ' falha(s).',
                'error'
            );
        } else {
            $this->setFlashMessage(
                $ok . ' pedido(s) atualizado(s) para “' . ($labels[$newStatus] ?? $newStatus) . '”.',
                'success'
            );
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function deleteSong(): void
    {
        $this->requireMaster();
        $redirect = rtrim(URL, '/') . '/master/educa-hits/musicas';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $redirect);
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido para apagar música.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $apiUrl = EducaHitsConfig::deleteSongApiUrl();
        if (strpos($apiUrl, 'EDUCAHITS_DELETE_SONG_API=') === 0) {
            $apiUrl = substr($apiUrl, strlen('EDUCAHITS_DELETE_SONG_API='));
        }
        $bearer = EducaHitsConfig::receiveBearerToken();
        if ($apiUrl === '' || $bearer === '') {
            $this->setFlashMessage('Configure EDUCAHITS_DELETE_SONG_API e o token Bearer no .env.', 'error');
            header('Location: ' . $redirect);
            exit;
        }
        if (!function_exists('curl_init')) {
            $this->setFlashMessage('cURL não está habilitado no PHP.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $songId = trim((string) ($_POST['song_id'] ?? ''));
        if ($songId === '') {
            $this->setFlashMessage('Música inválida para apagar (id ausente).', 'error');
            header('Location: ' . $redirect);
            exit;
        }
        // Resolve placeholders comuns no .env e garante id na query para APIs que esperam parâmetro na URL.
        $deleteUrl = str_replace(['{id}', ':id', 'uuid-da-musica'], rawurlencode($songId), $apiUrl);
        $parts = parse_url($deleteUrl);
        $query = [];
        if (is_array($parts) && !empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }
        if (empty($query['id']) && empty($query['song_id'])) {
            $sep = (strpos($deleteUrl, '?') === false) ? '?' : '&';
            $deleteUrl .= $sep . 'id=' . rawurlencode($songId);
        }
        $validUrl = filter_var($deleteUrl, FILTER_VALIDATE_URL);
        if ($validUrl === false) {
            $this->setFlashMessage('URL inválida em EDUCAHITS_DELETE_SONG_API. Verifique o .env.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $payload = [
            'song_id' => $songId,
            'id' => $songId,
            'school_slug' => trim((string) ($_POST['school_slug'] ?? '')),
            'audio_url' => trim((string) ($_POST['audio_url'] ?? '')),
            'cover_url' => trim((string) ($_POST['cover_url'] ?? '')),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'artist' => trim((string) ($_POST['artist'] ?? '')),
            'album' => trim((string) ($_POST['album'] ?? '')),
            // Campos de compatibilidade para APIs que reutilizam validação do endpoint de criação.
            'action' => 'delete',
            'delete' => true,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') {
            $this->setFlashMessage('Falha ao montar payload para apagar música.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $headers = $this->buildReceiveHeaders($deleteUrl, $bearer, true);
        $headers[] = 'X-HTTP-Method-Override: DELETE';
        $ch = curl_init($deleteUrl);
        if ($ch === false) {
            $this->setFlashMessage('Falha ao inicializar cURL para apagar música.', 'error');
            header('Location: ' . $redirect);
            exit;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => min(120, EducaHitsConfig::receiveCurlTimeoutSeconds()),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ]);
        $raw = curl_exec($ch);
        $curlErr = (string) curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            $this->setFlashMessage('Erro de rede ao apagar música: ' . $curlErr, 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $rawStr = trim((string) $raw);
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $rawStr !== '' ? self::clipDebugText($rawStr, 300) : ('HTTP ' . $httpCode);
            $this->setFlashMessage('Falha ao apagar música: ' . $msg, 'error');
            header('Location: ' . $redirect);
            exit;
        }
        if ($rawStr === '') {
            $this->setFlashMessage('Música apagada com sucesso.', 'success');
            header('Location: ' . $redirect);
            exit;
        }
        $json = json_decode($rawStr, true);
        if (is_array($json)) {
            if (!empty($json['error'])) {
                $this->setFlashMessage('Falha ao apagar música: ' . (string) $json['error'], 'error');
                header('Location: ' . $redirect);
                exit;
            }
            $ok = !empty($json['success']) || (!empty($json['status']) && strtolower((string) $json['status']) === 'ok');
            if (!$ok && isset($json['message']) && (string) $json['message'] !== '') {
                $this->setFlashMessage('Falha ao apagar música: ' . (string) $json['message'], 'error');
                header('Location: ' . $redirect);
                exit;
            }
        }

        $this->setFlashMessage('Música apagada com sucesso.', 'success');
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     * @return array{ok:bool,message?:string,meta?:array<string,mixed>}
     */
    private function deliverToSchoolSlug(string $slug, array $post, array $files, string $apiUrl, string $bearer): array
    {
        $fail = static function (string $message, array $meta = []): array {
            return ['ok' => false, 'message' => $message, 'meta' => $meta];
        };

        $audio = $files['audio'] ?? null;
        if (empty($audio['tmp_name']) || !is_uploaded_file($audio['tmp_name'])) {
            return $fail('Selecione o arquivo de áudio.', ['step' => 'validation_audio']);
        }
        $mimeAudio = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($audio['tmp_name']);
            if (is_string($m) && $m !== '') {
                $mimeAudio = $m;
            }
        }
        $cover = $files['cover'] ?? null;
        $hasCover = !empty($cover['tmp_name']) && is_uploaded_file($cover['tmp_name']);
        $mimeCover = 'application/octet-stream';
        if ($hasCover && function_exists('mime_content_type')) {
            $mc = @mime_content_type($cover['tmp_name']);
            if (is_string($mc) && $mc !== '') {
                $mimeCover = $mc;
            }
        }

        $curlTimeout = EducaHitsConfig::receiveCurlTimeoutSeconds();
        $maxAttempts = EducaHitsConfig::receiveMaxAttempts();
        if (function_exists('set_time_limit')) {
            @set_time_limit(min(7200, $curlTimeout * $maxAttempts + 180));
        }

        if (EducaHitsConfig::preferDirectUpload()) {
            $initPayload = [
                'title' => trim((string) ($post['title'] ?? '')),
                'artist' => trim((string) ($post['artist'] ?? '')),
                'album' => trim((string) ($post['album'] ?? '')),
                'subject' => trim((string) ($post['subject'] ?? '')),
                'topic' => trim((string) ($post['topic'] ?? '')),
                'lyrics' => trim((string) ($post['lyrics'] ?? '')),
                'notes' => trim((string) ($post['notes'] ?? '')),
                'duration' => (string) (int) ($post['duration'] ?? 0),
                'audio_ext' => $this->detectFileExt((array) $audio, 'mp3'),
            ];
            if ($slug !== '') {
                $initPayload['school_slug'] = $slug;
            }
            if ($hasCover) {
                $initPayload['cover_ext'] = $this->detectFileExt((array) $cover, 'jpg');
            }
            $initBody = json_encode($initPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($initBody) || $initBody === '') {
                return $fail('Falha ao gerar JSON de metadados para a API EducaHits.', ['step' => 'json_encode_init', 'school_slug' => $slug]);
            }

            $initHeaders = $this->buildReceiveHeaders($apiUrl, $bearer, true);
            $initRaw = '';
            $initErr = '';
            $initHttp = 0;
            $initEffective = '';
            $initRedirect = '';

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                if ($attempt > 1) {
                    $wait = min(60, 2 ** ($attempt - 1));
                    sleep($wait);
                }
                $ch = curl_init($apiUrl);
                if ($ch === false) {
                    if ($attempt < $maxAttempts) {
                        continue;
                    }
                    return $fail('Falha ao contatar a API de entrega (etapa JSON).', ['step' => 'init_curl_init', 'endpoint' => $apiUrl, 'attempts' => $attempt, 'school_slug' => $slug]);
                }
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $initBody,
                    CURLOPT_HTTPHEADER => $initHeaders,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 45,
                    CURLOPT_TIMEOUT => $curlTimeout,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_MAXREDIRS => 0,
                ]);
                $initRaw = curl_exec($ch);
                $initErr = (string) curl_error($ch);
                $initHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $initEffective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $initRedirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
                curl_close($ch);

                if ($initRaw === false) {
                    if ($attempt < $maxAttempts && $this->isCurlErrorRetryable($initErr)) {
                        continue;
                    }
                    return $fail('Erro de rede na etapa JSON de entrega.', [
                        'step' => 'init_curl_exec',
                        'curl_error' => $initErr,
                        'http_status' => $initHttp,
                        'endpoint' => $apiUrl,
                        'effective_url' => is_string($initEffective) ? $initEffective : '',
                        'school_slug' => $slug,
                        'attempts' => $attempt,
                        'max_attempts' => $maxAttempts,
                    ]);
                }
                if (in_array($initHttp, [502, 504, 524], true) && $attempt < $maxAttempts) {
                    continue;
                }
                break;
            }

            $initStr = trim((string) $initRaw);
            $initLooksHtml = (stripos($initStr, '<!doctype') !== false || stripos($initStr, '<html') !== false);
            if ($initLooksHtml) {
                return $fail('API EducaHits (etapa JSON): resposta HTML em vez de JSON. Verifique URL da function e Bearer (ADMIN_API_KEY).', [
                    'step' => 'init_response_html',
                    'http_status' => $initHttp,
                    'body_preview' => self::clipDebugText($initStr, 1200),
                    'endpoint_requested' => $apiUrl,
                    'effective_url' => is_string($initEffective) ? $initEffective : '',
                    'redirect_url' => is_string($initRedirect) ? $initRedirect : '',
                    'school_slug' => $slug,
                ]);
            }
            if ($initHttp >= 300 && $initHttp < 400) {
                return $fail('API EducaHits (etapa JSON): redirecionamento HTTP ' . $initHttp . '. Ajuste URL/token.', [
                    'step' => 'init_http_redirect',
                    'http_status' => $initHttp,
                    'redirect_url' => is_string($initRedirect) ? $initRedirect : '',
                    'body_preview' => self::clipDebugText($initStr, 800),
                    'endpoint_requested' => $apiUrl,
                    'effective_url' => is_string($initEffective) ? $initEffective : '',
                    'school_slug' => $slug,
                ]);
            }
            if (in_array($initHttp, [502, 504, 524], true)) {
                return $fail('API EducaHits (etapa JSON): HTTP ' . $initHttp . ' após ' . $maxAttempts . ' tentativa(s). A function não respondeu dentro do limite.', [
                    'step' => 'init_gateway_timeout',
                    'http_status' => $initHttp,
                    'curl_timeout_used' => $curlTimeout,
                    'attempts_used' => $maxAttempts,
                    'body_preview' => self::clipDebugText($initStr, 800),
                    'endpoint' => $apiUrl,
                    'effective_url' => is_string($initEffective) ? $initEffective : '',
                    'school_slug' => $slug,
                ]);
            }
            if (in_array($initHttp, [401, 403], true)) {
                return $fail('API EducaHits (etapa JSON): HTTP ' . $initHttp . ' — acesso negado. Confirme o Bearer = ADMIN_API_KEY.', [
                    'step' => 'init_auth',
                    'http_status' => $initHttp,
                    'body_preview' => self::clipDebugText($initStr, 800),
                    'endpoint' => $apiUrl,
                    'effective_url' => is_string($initEffective) ? $initEffective : '',
                    'school_slug' => $slug,
                ]);
            }

            $allowLegacyFallback = false;
            $initJson = [];
            if ($initStr === '') {
                $allowLegacyFallback = true;
            } else {
                $decoded = json_decode($initStr, true);
                if (!is_array($decoded)) {
                    $allowLegacyFallback = true;
                } else {
                    $initJson = $decoded;
                }
            }

            if (!$allowLegacyFallback && isset($initJson['error']) && $initJson['error'] !== '' && $initJson['error'] !== null) {
                return $fail('API EducaHits (etapa JSON): ' . (string) $initJson['error'], [
                    'step' => 'init_api_error_field',
                    'http_status' => $initHttp,
                    'response_json' => $initJson,
                    'endpoint' => $apiUrl,
                    'school_slug' => $slug,
                ]);
            }

            if (!$allowLegacyFallback) {
                $uploadUrls = $this->extractUploadUrls($initJson);
                if ($uploadUrls['audio'] !== '') {
                    $audioUp = $this->putFileToSignedUrl($uploadUrls['audio'], (string) $audio['tmp_name'], $mimeAudio, $curlTimeout);
                    if (empty($audioUp['ok'])) {
                        return $fail('Falha no upload direto do áudio (signed URL).', [
                            'step' => 'upload_audio_signed_url',
                            'http_status' => (int) ($audioUp['http_status'] ?? 0),
                            'curl_error' => (string) ($audioUp['curl_error'] ?? ''),
                            'body_preview' => (string) ($audioUp['body_preview'] ?? ''),
                            'school_slug' => $slug,
                        ]);
                    }
                    if ($hasCover && $uploadUrls['cover'] !== '') {
                        $coverUp = $this->putFileToSignedUrl($uploadUrls['cover'], (string) $cover['tmp_name'], $mimeCover, $curlTimeout);
                        if (empty($coverUp['ok'])) {
                            return $fail('Falha no upload direto da capa (signed URL).', [
                                'step' => 'upload_cover_signed_url',
                                'http_status' => (int) ($coverUp['http_status'] ?? 0),
                                'curl_error' => (string) ($coverUp['curl_error'] ?? ''),
                                'body_preview' => (string) ($coverUp['body_preview'] ?? ''),
                                'school_slug' => $slug,
                            ]);
                        }
                    }

                    return ['ok' => true];
                }

                $initOk = $initHttp >= 200 && $initHttp < 300;
                $bodyOk = !empty($initJson['success']) || (!empty($initJson['status']) && strtolower((string) $initJson['status']) === 'ok');
                if ($initOk && $bodyOk) {
                    return ['ok' => true];
                }
            }
        }

        if (!function_exists('curl_file_create')) {
            return $fail('Ambiente PHP incompatível (curl_file_create).', ['step' => 'curl_file_create', 'school_slug' => $slug]);
        }
        $cfAudio = curl_file_create($audio['tmp_name'], $mimeAudio, (string) ($audio['name'] ?? 'audio.mp3'));
        $payload = [
            'title' => trim((string) ($post['title'] ?? '')),
            'artist' => trim((string) ($post['artist'] ?? '')),
            'album' => trim((string) ($post['album'] ?? '')),
            'subject' => trim((string) ($post['subject'] ?? '')),
            'topic' => trim((string) ($post['topic'] ?? '')),
            'lyrics' => trim((string) ($post['lyrics'] ?? '')),
            'notes' => trim((string) ($post['notes'] ?? '')),
            'duration' => (string) (int) ($post['duration'] ?? 0),
            'audio' => $cfAudio,
        ];
        if ($slug !== '') {
            $payload['school_slug'] = $slug;
        }
        if ($hasCover) {
            $payload['cover'] = curl_file_create($cover['tmp_name'], $mimeCover, (string) ($cover['name'] ?? 'cover.jpg'));
        }
        $headers = $this->buildReceiveHeaders($apiUrl, $bearer, false);

        $raw = '';
        $curlErr = '';
        $httpCode = 0;
        $effectiveUrl = '';
        $redirectUrl = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                $wait = min(60, 2 ** ($attempt - 1));
                sleep($wait);
            }
            $ch = curl_init($apiUrl);
            if ($ch === false) {
                if ($attempt < $maxAttempts) {
                    continue;
                }
                return $fail('Falha ao contatar a API de entrega.', ['step' => 'curl_init', 'endpoint' => $apiUrl, 'attempts' => $attempt, 'school_slug' => $slug]);
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 45,
                CURLOPT_TIMEOUT => $curlTimeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
            ]);
            $raw = curl_exec($ch);
            $curlErr = (string) curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            if ($raw === false) {
                if ($attempt < $maxAttempts && $this->isCurlErrorRetryable($curlErr)) {
                    continue;
                }
                return $fail('Erro de rede ao enviar a música.', [
                    'step' => 'curl_exec',
                    'curl_error' => $curlErr,
                    'http_status' => $httpCode,
                    'endpoint' => $apiUrl,
                    'effective_url' => is_string($effectiveUrl) ? $effectiveUrl : '',
                    'school_slug' => $slug,
                    'attempts' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);
            }
            if (in_array($httpCode, [502, 504, 524], true) && $attempt < $maxAttempts) {
                continue;
            }
            break;
        }

        $rawStr = (string) $raw;
        $looksHtml = (stripos($rawStr, '<!doctype') !== false || stripos($rawStr, '<html') !== false);
        if ($looksHtml) {
            return $fail('API EducaHits: resposta HTML em vez de JSON da API.', [
                'step' => 'response_html',
                'http_status' => $httpCode,
                'body_preview' => self::clipDebugText($rawStr, 1200),
                'body_length' => strlen($rawStr),
                'endpoint_requested' => $apiUrl,
                'effective_url' => is_string($effectiveUrl) ? $effectiveUrl : '',
                'redirect_url' => is_string($redirectUrl) ? $redirectUrl : '',
                'school_slug' => $slug,
            ]);
        }
        if ($httpCode >= 300 && $httpCode < 400) {
            return $fail('API EducaHits: redirecionamento HTTP ' . $httpCode . ' em vez de processar upload.', [
                'step' => 'http_redirect',
                'http_status' => $httpCode,
                'redirect_url' => is_string($redirectUrl) ? $redirectUrl : '',
                'body_preview' => self::clipDebugText($rawStr, 800),
                'endpoint_requested' => $apiUrl,
                'effective_url' => is_string($effectiveUrl) ? $effectiveUrl : '',
                'school_slug' => $slug,
            ]);
        }
        if (in_array($httpCode, [502, 504, 524], true)) {
            return $fail('API EducaHits: HTTP ' . $httpCode . ' após ' . $maxAttempts . ' tentativa(s).', [
                'step' => 'gateway_timeout',
                'http_status' => $httpCode,
                'curl_timeout_used' => $curlTimeout,
                'attempts_used' => $maxAttempts,
                'body_preview' => self::clipDebugText(trim($rawStr), 800),
                'endpoint' => $apiUrl,
                'effective_url' => is_string($effectiveUrl) ? $effectiveUrl : '',
                'school_slug' => $slug,
            ]);
        }

        $trimmed = trim($rawStr);
        if ($trimmed === '') {
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['ok' => true];
            }
            if (in_array($httpCode, [401, 403], true)) {
                return $fail('API EducaHits: HTTP ' . $httpCode . ' — acesso negado.', [
                    'step' => 'empty_body_auth',
                    'http_status' => $httpCode,
                    'endpoint' => $apiUrl,
                    'effective_url' => is_string($effectiveUrl) ? $effectiveUrl : '',
                    'school_slug' => $slug,
                ]);
            }

            return $fail('API EducaHits: resposta sem corpo (HTTP ' . $httpCode . ').', [
                'step' => 'empty_body',
                'http_status' => $httpCode,
                'endpoint' => $apiUrl,
                'effective_url' => is_string($effectiveUrl) ? $effectiveUrl : '',
                'school_slug' => $slug,
            ]);
        }

        $json = json_decode($trimmed, true);
        if (!is_array($json)) {
            $jerr = function_exists('json_last_error_msg') ? json_last_error_msg() : '';
            $snippet = self::clipDebugText($trimmed, 400);
            $detail = $snippet === '' ? '(vazio)' : $snippet;

            return $fail('API EducaHits: resposta não é JSON válido. ' . ($jerr !== '' ? 'PHP: ' . $jerr . '. ' : '') . 'Prévia: ' . $detail, [
                'step' => 'json_decode',
                'http_status' => $httpCode,
                'json_last_error' => $jerr,
                'body_preview' => self::clipDebugText($trimmed),
                'body_length' => strlen($trimmed),
                'endpoint' => $apiUrl,
                'effective_url' => is_string($effectiveUrl) ? $effectiveUrl : '',
                'school_slug' => $slug,
            ]);
        }

        $httpOk = $httpCode >= 200 && $httpCode < 300;
        if (array_key_exists('error', $json) && $json['error'] !== '' && $json['error'] !== null) {
            return $fail('API EducaHits: ' . (string) $json['error'], [
                'step' => 'api_error_field',
                'http_status' => $httpCode,
                'response_json' => $json,
                'endpoint' => $apiUrl,
                'school_slug' => $slug,
            ]);
        }
        $bodyOk = !empty($json['success'])
            || (!empty($json['status']) && strtolower((string) $json['status']) === 'ok');
        if (!$httpOk || !$bodyOk) {
            $msg = isset($json['message']) ? (string) $json['message'] : ('HTTP ' . $httpCode);

            return $fail('API EducaHits: ' . $msg, [
                'step' => 'response_validation',
                'http_status' => $httpCode,
                'http_ok' => $httpOk,
                'body_ok' => $bodyOk,
                'response_json' => $json,
                'endpoint' => $apiUrl,
                'school_slug' => $slug,
            ]);
        }

        return ['ok' => true];
    }

    public function deliver(): void
    {
        $this->requireMaster();
        $redirect = rtrim(URL, '/') . '/master/educa-hits/cadastro';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $redirect);
            exit;
        }
        if ($this->isPostLikelyDiscardedByPhpLimit()) {
            $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            $postMaxRaw = (string) ini_get('post_max_size');
            $uploadMaxRaw = (string) ini_get('upload_max_filesize');
            $this->flashDeliverError(
                'Upload excedeu o limite do servidor. Aumente upload_max_filesize/post_max_size ou envie um arquivo menor.',
                [
                    'step' => 'post_too_large',
                    'content_length' => $contentLength,
                    'post_max_size' => $postMaxRaw,
                    'upload_max_filesize' => $uploadMaxRaw,
                ]
            );
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->flashDeliverError('Token inválido.', ['step' => 'csrf']);
        }

        $apiUrl = EducaHitsConfig::receiveSongUrl();
        $bearer = EducaHitsConfig::receiveBearerToken();
        if ($apiUrl === '' || $bearer === '') {
            $this->flashDeliverError(
                'Configure EDUCAHITS_RECEIVE_SONG_URL e o Bearer: EDUCAHITS_ADMIN_API_KEY (secret ADMIN_API_KEY no backend) ou EDUCAHITS_RECEIVE_API_TOKEN no .env.',
                [
                    'step' => 'config',
                    'receive_url_set' => $apiUrl !== '',
                    'token_set' => $bearer !== '',
                ]
            );
        }
        if (!function_exists('curl_init')) {
            $this->flashDeliverError('cURL não está habilitado no PHP.', ['step' => 'curl_extension']);
        }

        $post = $_POST;
        $files = $_FILES;
        $audio = $files['audio'] ?? null;
        if (empty($audio['tmp_name']) || !is_uploaded_file($audio['tmp_name'])) {
            $this->flashDeliverError('Selecione o arquivo de áudio.', ['step' => 'validation_audio']);
        }

        $title = trim((string) ($post['title'] ?? ''));
        $artist = trim((string) ($post['artist'] ?? ''));
        if ($title === '' || $artist === '') {
            $this->flashDeliverError('Título e artista são obrigatórios.', ['step' => 'validation_meta']);
        }

        $targetSlugs = [];
        $db = Database::getInstance();
        $escolaRaw = trim((string) ($post['escola_id'] ?? ''));
        $allMode = false;
        if ($escolaRaw === 'all') {
            $allMode = true;
            // Envio único no modo "todas as escolas" para evitar duplicar a mesma música.
            // Se a API exigir school_slug, fazemos fallback para o modo legado por escola.
            $targetSlugs = [''];
        } else {
            $escolaId = (int) $escolaRaw;
            $slug = '';
            try {
                $row = $db->fetch('SELECT slug FROM escolas WHERE id = :id AND ativo = 1', ['id' => $escolaId]);
                $slug = trim((string) ($row['slug'] ?? ''));
            } catch (Throwable $e) {
                $slug = '';
            }
            if ($slug === '') {
                $this->flashDeliverError('Selecione uma escola válida (slug necessário para a API).', [
                    'step' => 'validation_school',
                    'escola_id' => $escolaId,
                ]);
            }
            $targetSlugs[] = $slug;
        }

        $okCount = 0;
        $fails = [];
        foreach ($targetSlugs as $slug) {
            $result = $this->deliverToSchoolSlug($slug, $post, $files, $apiUrl, $bearer);
            if (!empty($result['ok'])) {
                $okCount++;
                continue;
            }
            $result['meta'] = is_array($result['meta'] ?? null) ? $result['meta'] : [];
            $result['meta']['school_slug'] = $slug;
            $fails[] = $result;
        }

        if ($allMode && $okCount === 0 && !empty($fails)) {
            $firstFail = $fails[0];
            $firstMsg = strtolower((string) ($firstFail['message'] ?? ''));
            $slugSeemsRequired = strpos($firstMsg, 'school_slug') !== false || strpos($firstMsg, 'slug') !== false;
            if ($slugSeemsRequired) {
                $targetSlugs = [];
                $schools = $this->getActiveSchoolsForSelect();
                foreach ($schools as $s) {
                    $slug = trim((string) ($s['slug'] ?? ''));
                    if ($slug !== '') {
                        $targetSlugs[] = $slug;
                    }
                }
                $targetSlugs = array_values(array_unique($targetSlugs));
                if (empty($targetSlugs)) {
                    $this->flashDeliverError('Nenhuma escola ativa com slug foi encontrada para envio em lote.', [
                        'step' => 'validation_school_all',
                    ]);
                }

                $okCount = 0;
                $fails = [];
                foreach ($targetSlugs as $slug) {
                    $result = $this->deliverToSchoolSlug($slug, $post, $files, $apiUrl, $bearer);
                    if (!empty($result['ok'])) {
                        $okCount++;
                        continue;
                    }
                    $result['meta'] = is_array($result['meta'] ?? null) ? $result['meta'] : [];
                    $result['meta']['school_slug'] = $slug;
                    $fails[] = $result;
                }
            }
        }

        if (!empty($fails)) {
            $first = $fails[0];
            $meta = is_array($first['meta'] ?? null) ? $first['meta'] : [];
            $meta['step'] = 'batch_deliver';
            $meta['success_count'] = $okCount;
            $meta['fail_count'] = count($fails);
            $meta['failed_schools'] = array_values(array_map(static function ($f) {
                return (string) (($f['meta']['school_slug'] ?? '') ?: '');
            }, $fails));

            $prefix = $okCount > 0 ? 'Envio parcial: ' . $okCount . ' escola(s) com sucesso. ' : '';
            $this->flashDeliverError($prefix . (string) ($first['message'] ?? 'Falha ao enviar música.'), $meta);
        }

        if (count($targetSlugs) > 1) {
            $this->setFlashMessage('Música enviada ao EducaHits para ' . $okCount . ' escola(s) com sucesso.', 'success');
        } else {
            $this->setFlashMessage('Música enviada ao EducaHits com sucesso.', 'success');
        }
        header('Location: ' . $redirect);
        exit;
    }
}
}
