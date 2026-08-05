<?php
require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../../../Core/Logger.php';
require_once __DIR__ . '/../../../Services/FacialRecognitionProviderService.php';
require_once __DIR__ . '/../../../Services/FacialAttendanceNotificationService.php';

class FacialDeviceApiController extends BaseController
{
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    public function pair(): void
    {
        if (!$this->schemaReady()) $this->error('device_api_not_configured', 'Pareamento ainda não configurado nesta escola.', 503);
        $input = $this->jsonInput();
        $code = preg_replace('/\D+/', '', (string) ($input['pairing_code'] ?? ''));
        $deviceUid = trim((string) ($input['device_id'] ?? ''));
        $name = trim((string) ($input['device_name'] ?? ''));
        if (strlen($code) !== 6 || !preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $deviceUid) || $name === '' || mb_strlen($name) > 120) {
            $this->error('validation_error', 'Código, identificador ou nome do dispositivo inválido.', 422);
        }
        $this->rateLimit($deviceUid);
        $this->db->beginTransaction();
        try {
            $pairing = $this->db->fetch(
                "SELECT id, created_by_user_id FROM facial_device_pairing_codes
                 WHERE code_hash = :hash AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE",
                ['hash' => hash('sha256', $code)]
            );
            if (!$pairing) {
                $this->db->rollback();
                $this->error('invalid_pairing_code', 'Código inválido, expirado ou já utilizado.', 401);
            }
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $this->db->query(
                "INSERT INTO facial_devices (device_uid, name, token_hash, enabled, paired_by_user_id, paired_at, last_seen_at)
                 VALUES (:uid, :name, :token, 1, :user_id, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), token_hash = VALUES(token_hash), enabled = 1,
                    paired_by_user_id = VALUES(paired_by_user_id), paired_at = NOW(), last_seen_at = NOW()",
                ['uid' => $deviceUid, 'name' => $name, 'token' => $tokenHash, 'user_id' => $pairing['created_by_user_id']]
            );
            $this->db->update('UPDATE facial_device_pairing_codes SET used_at = NOW() WHERE id = :id', ['id' => $pairing['id']]);
            $this->db->commit();
            $payload = [
                'device_token' => $token,
                'device' => ['id' => $deviceUid, 'name' => $name],
                'school' => $this->school(),
                'mode' => 'portaria',
            ];
            // Compatibilidade com clientes que leem o token no nível principal
            // e com os que seguem o envelope padrão `data` da API.
            $this->json(['success' => true, 'device_token' => $token, 'data' => $payload]);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            $this->error('pairing_failed', 'Não foi possível parear o dispositivo.', 500);
        }
    }

    public function me(): void
    {
        $device = $this->authenticatedDevice();
        $this->json(['data' => [
            'device' => ['id' => $device['device_uid'], 'name' => $device['name']],
            'school' => $this->school(),
            'mode' => 'portaria',
        ]]);
    }

    public function classes(): void
    {
        $this->authenticatedDevice();
        $rows = $this->db->fetchAll('SELECT id, nome, serie FROM turmas WHERE ativo = 1 ORDER BY serie, nome');
        $this->json(['data' => array_map(static fn ($row) => [
            'id' => (int) $row['id'], 'name' => (string) $row['nome'], 'grade' => $row['serie'] ?? null,
        ], $rows)]);
    }

    public function students(): void
    {
        $this->authenticatedDevice();
        $query = trim((string) ($_GET['q'] ?? ''));
        $classId = max(0, (int) ($_GET['class_id'] ?? 0));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $where = ['a.ativo = 1'];
        $params = [];
        if ($query !== '') {
            $where[] = '(a.nome LIKE :name OR a.ra LIKE :ra OR a.codigo_aluno LIKE :code)';
            $params += ['name' => '%' . $query . '%', 'ra' => '%' . $query . '%', 'code' => '%' . $query . '%'];
        }
        if ($classId > 0) {
            $where[] = 'a.turma_id = :class_id';
            $params['class_id'] = $classId;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) ($this->db->fetch("SELECT COUNT(*) total FROM alunos a WHERE {$whereSql}", $params)['total'] ?? 0);
        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, a.codigo_aluno, a.turma_id, t.nome class_name, t.serie class_grade,
                    fp.active face_active,
                    (SELECT COUNT(*) FROM student_face_samples fs WHERE fs.face_profile_id = fp.id) sample_count
             FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id
             LEFT JOIN student_face_profiles fp ON fp.student_id = a.id
             WHERE {$whereSql} ORDER BY a.nome LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $this->json(['data' => array_map([$this, 'serializeStudent'], $rows), 'meta' => [
            'page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage)),
        ]]);
    }

    public function student($id): void
    {
        $this->authenticatedDevice();
        $row = $this->studentRow((int) $id);
        if (!$row) $this->error('student_not_found', 'Aluno não encontrado.', 404);
        $events = $this->db->fetchAll(
            'SELECT id, kind, event_at, confidence, notified_at FROM student_access_events WHERE student_id = :id ORDER BY event_at DESC LIMIT 30',
            ['id' => (int) $id]
        );
        $data = $this->serializeStudent($row);
        $data['events'] = array_map([$this, 'serializeEvent'], $events);
        $this->json(['data' => $data]);
    }

    public function registerSample($id): void
    {
        $device = $this->authenticatedDevice();
        $input = $this->jsonInput();
        $consent = filter_var($input['consent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$consent) $this->error('consent_required', 'Confirme a autorização do responsável.', 422);
        $student = $this->studentRow((int) $id);
        if (!$student) $this->error('student_not_found', 'Aluno não encontrado.', 404);
        try {
            $externalKey = $this->externalKey((int) $id);
            $profile = $this->db->fetch('SELECT * FROM student_face_profiles WHERE student_id = :id LIMIT 1', ['id' => (int) $id]);
            if (!$profile) {
                $profileId = (int) $this->db->insert(
                    'INSERT INTO student_face_profiles (student_id, external_key, consent_at, consent_by_user_id, active) VALUES (:student, :external, NOW(), :user, 1)',
                    ['student' => (int) $id, 'external' => $externalKey, 'user' => $device['paired_by_user_id'] ?? null]
                );
            } else {
                $profileId = (int) $profile['id'];
                $externalKey = (string) $profile['external_key'];
                $this->db->update('UPDATE student_face_profiles SET active = 1, consent_at = NOW(), consent_by_user_id = :user WHERE id = :id', ['user' => $device['paired_by_user_id'] ?? null, 'id' => $profileId]);
            }
            $providerResult = (new FacialRecognitionProviderService())->registerFace($externalKey, $this->descriptorFromInput($input));
            $providerFaceId = (string) ($providerResult['face']['id'] ?? '');
            if ($providerFaceId === '') throw new RuntimeException('A API facial não retornou a amostra.');
            $this->db->query('INSERT IGNORE INTO student_face_samples (face_profile_id, provider_face_id) VALUES (:profile, :provider)', ['profile' => $profileId, 'provider' => $providerFaceId]);
            $count = (int) ($this->db->fetch('SELECT COUNT(*) total FROM student_face_samples WHERE face_profile_id = :id', ['id' => $profileId])['total'] ?? 0);
            $this->json([
                'success' => true,
                'student_id' => (int) $id,
                'sample_count' => $count,
                'data' => ['student_id' => (int) $id, 'sample_count' => $count],
                'message' => 'Amostra facial cadastrada.',
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error('invalid_descriptor', $e->getMessage(), 422);
        } catch (Throwable $e) {
            Logger::error('Cadastro facial pelo dispositivo falhou', ['student_id' => (int) $id, 'exception' => $e], 'facial');
            $this->error('face_registration_failed', 'Não foi possível cadastrar a amostra facial.', 502);
        }
    }

    public function deleteProfile($id): void
    {
        $this->authenticatedDevice();
        $profile = $this->db->fetch('SELECT * FROM student_face_profiles WHERE student_id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$profile) $this->error('profile_not_found', 'Cadastro facial não encontrado.', 404);
        try {
            (new FacialRecognitionProviderService())->deleteFaces((string) $profile['external_key']);
            $this->db->query('DELETE FROM student_face_profiles WHERE id = :id', ['id' => $profile['id']]);
            http_response_code(204); exit;
        } catch (Throwable $e) {
            $this->error('profile_delete_failed', 'Não foi possível excluir o cadastro facial.', 502);
        }
    }

    /** Sincroniza apenas o estado do cadastro; a biometria permanece no Lovable. */
    public function syncFaceStatus($id): void
    {
        $device = $this->authenticatedDevice();
        $student = $this->studentRow((int) $id);
        if (!$student) $this->error('student_not_found', 'Aluno não encontrado.', 404);
        $input = $this->jsonInput();
        $registered = filter_var($input['registered'] ?? $input['approved'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$registered) {
            $profile = $this->db->fetch('SELECT id FROM student_face_profiles WHERE student_id = :id LIMIT 1', ['id' => (int) $id]);
            if ($profile) $this->db->query('DELETE FROM student_face_profiles WHERE id = :id', ['id' => $profile['id']]);
            $this->json(['success' => true, 'data' => ['student_id' => (int) $id, 'registered' => false, 'sample_count' => 0]]);
        }
        $externalReference = trim((string) ($input['external_face_id'] ?? $input['face_id'] ?? ''));
        if ($externalReference === '' || strlen($externalReference) > 100 || !preg_match('/^[A-Za-z0-9._:-]+$/', $externalReference)) {
            $this->error('validation_error', 'Identificador facial externo inválido.', 422);
        }
        $sampleCount = min(10, max(1, (int) ($input['sample_count'] ?? 1)));
        $externalKey = 'lovable_t' . (defined('TENANT_ID') ? (int) TENANT_ID : 0) . '_a' . (int) $id;
        $profile = $this->db->fetch('SELECT id FROM student_face_profiles WHERE student_id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$profile) {
            $profileId = (int) $this->db->insert(
                'INSERT INTO student_face_profiles (student_id, external_key, consent_at, consent_by_user_id, active) VALUES (:student, :external, NOW(), :user, 1)',
                ['student' => (int) $id, 'external' => $externalKey, 'user' => $device['paired_by_user_id'] ?? null]
            );
        } else {
            $profileId = (int) $profile['id'];
            $this->db->update('UPDATE student_face_profiles SET external_key = :external, consent_at = NOW(), consent_by_user_id = :user, active = 1 WHERE id = :id', ['external' => $externalKey, 'user' => $device['paired_by_user_id'] ?? null, 'id' => $profileId]);
            $this->db->query('DELETE FROM student_face_samples WHERE face_profile_id = :id', ['id' => $profileId]);
        }
        for ($index = 1; $index <= $sampleCount; $index++) {
            $sampleId = 'lovable:' . substr(hash('sha256', $externalReference), 0, 48) . ':' . $index;
            $this->db->query('INSERT INTO student_face_samples (face_profile_id, provider_face_id) VALUES (:profile, :provider)', ['profile' => $profileId, 'provider' => $sampleId]);
        }
        $this->json(['success' => true, 'data' => ['student_id' => (int) $id, 'registered' => true, 'sample_count' => $sampleCount]]);
    }

    /** Recebe do Lovable somente um evento já reconhecido e dispara a notificação. */
    public function attendance(): void
    {
        $device = $this->authenticatedDevice();
        $input = $this->jsonInput();
        if (!filter_var($input['approved'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $this->json(['success' => true, 'data' => ['registered' => false, 'reason' => 'not_approved']]);
        }
        $studentId = (int) ($input['student_id'] ?? 0);
        $student = $this->studentRow($studentId);
        if (!$student) $this->error('student_not_found', 'Aluno não encontrado.', 404);
        $kind = strtolower(trim((string) ($input['kind'] ?? '')));
        if (!in_array($kind, ['entrada', 'saida'], true)) $this->error('validation_error', 'Informe entrada ou saída.', 422);
        $externalEventId = trim((string) ($input['event_id'] ?? ''));
        if ($externalEventId === '' || strlen($externalEventId) > 160) $this->error('validation_error', 'Identificador do evento inválido.', 422);
        $tenant = defined('TENANT_ID') ? (int) TENANT_ID : 0;
        $providerId = 'lovable:' . $tenant . ':' . substr(hash('sha256', $externalEventId), 0, 64);
        $existing = $this->db->fetch('SELECT id, event_at FROM student_access_events WHERE provider_presence_id = :id LIMIT 1', ['id' => $providerId]);
        if ($existing) {
            $this->json(['success' => true, 'data' => ['registered' => false, 'duplicate' => true, 'event_id' => (int) $existing['id']]]);
        }
        try {
            $eventAt = !empty($input['event_at']) ? new DateTimeImmutable((string) $input['event_at']) : new DateTimeImmutable('now');
        } catch (Throwable $e) {
            $this->error('validation_error', 'Data e horário inválidos.', 422);
        }
        $confidence = isset($input['confidence']) && is_numeric($input['confidence']) ? (float) $input['confidence'] : null;
        if ($confidence !== null && ($confidence < 0 || $confidence > 1)) $this->error('validation_error', 'Confiança inválida.', 422);
        $eventId = (int) $this->db->insert(
            'INSERT INTO student_access_events (student_id, kind, event_at, confidence, provider_presence_id, registered_by_user_id) VALUES (:student, :kind, :at, :confidence, :provider, :user)',
            ['student' => $studentId, 'kind' => $kind, 'at' => $eventAt->format('Y-m-d H:i:s'), 'confidence' => $confidence, 'provider' => $providerId, 'user' => $device['paired_by_user_id'] ?? null]
        );
        $notification = ['parents' => 0, 'sent' => 0, 'failed' => 0];
        try {
            $notification = (new FacialAttendanceNotificationService($this->db))->notify(['id' => $studentId, 'nome' => (string) $student['nome']], $eventId, $kind, $eventAt, (int) ($device['paired_by_user_id'] ?? 0));
            $this->db->update('UPDATE student_access_events SET notified_at = NOW() WHERE id = :id', ['id' => $eventId]);
        } catch (Throwable $e) {
            Logger::error('Push do evento Lovable falhou', ['event_id' => $eventId, 'exception' => $e], 'facial');
        }
        $this->json(['success' => true, 'data' => [
            'registered' => true, 'event_id' => $eventId, 'student_id' => $studentId,
            'kind' => $kind, 'date' => $eventAt->format('Y-m-d'), 'time' => $eventAt->format('H:i'),
            'notification' => $notification,
        ]]);
    }

    public function recognize(): void
    {
        $device = $this->authenticatedDevice();
        try {
            $result = (new FacialRecognitionProviderService())->recognize($this->descriptorFromInput($this->jsonInput()));
            if (empty($result['match'])) $this->json(['data' => ['match' => false, 'registered' => false]]);
            $profile = $this->db->fetch(
                'SELECT fp.student_id, a.nome, a.turma_id, t.nome class_name FROM student_face_profiles fp INNER JOIN alunos a ON a.id = fp.student_id AND a.ativo = 1 LEFT JOIN turmas t ON t.id = a.turma_id WHERE fp.external_key = :key AND fp.active = 1 LIMIT 1',
                ['key' => (string) ($result['name'] ?? '')]
            );
            if (!$profile) $this->json(['data' => ['match' => false, 'registered' => false]]);
            if (empty($result['registered'])) $this->json(['data' => ['match' => true, 'registered' => false, 'student' => ['id' => (int) $profile['student_id'], 'name' => $profile['nome'], 'class_name' => $profile['class_name']]]]);
            $presence = (array) ($result['presence'] ?? []);
            $providerId = (string) ($presence['id'] ?? '');
            $kind = (string) ($presence['kind'] ?? '');
            if ($providerId === '' || !in_array($kind, ['entrada', 'saida'], true)) throw new RuntimeException('Presença inválida.');
            $existing = $this->db->fetch('SELECT id, event_at, kind FROM student_access_events WHERE provider_presence_id = :id LIMIT 1', ['id' => $providerId]);
            if ($existing) $this->json(['data' => ['match' => true, 'registered' => false, 'student' => ['id' => (int) $profile['student_id'], 'name' => $profile['nome'], 'class_name' => $profile['class_name']]]]);
            $now = new DateTimeImmutable('now');
            $eventId = (int) $this->db->insert(
                'INSERT INTO student_access_events (student_id, kind, event_at, confidence, provider_presence_id, registered_by_user_id) VALUES (:student, :kind, :event_at, :confidence, :provider, :user)',
                ['student' => $profile['student_id'], 'kind' => $kind, 'event_at' => $now->format('Y-m-d H:i:s'), 'confidence' => $result['confidence'] ?? null, 'provider' => $providerId, 'user' => $device['paired_by_user_id'] ?? null]
            );
            $notification = ['parents' => 0, 'sent' => 0, 'failed' => 0];
            try {
                $notification = (new FacialAttendanceNotificationService($this->db))->notify(['id' => (int) $profile['student_id'], 'nome' => (string) $profile['nome']], $eventId, $kind, $now, (int) ($device['paired_by_user_id'] ?? 0));
                $this->db->update('UPDATE student_access_events SET notified_at = NOW() WHERE id = :id', ['id' => $eventId]);
            } catch (Throwable $notifyError) {
                Logger::error('Push de presença do dispositivo falhou', ['event_id' => $eventId, 'exception' => $notifyError], 'facial');
            }
            $this->json(['data' => ['match' => true, 'registered' => true, 'student' => ['id' => (int) $profile['student_id'], 'name' => $profile['nome'], 'class_name' => $profile['class_name']], 'event' => ['id' => $eventId, 'kind' => $kind, 'date' => $now->format('Y-m-d'), 'time' => $now->format('H:i'), 'notified_parents' => ($notification['parents'] ?? 0) > 0]]]);
        } catch (InvalidArgumentException $e) {
            $this->error('invalid_descriptor', $e->getMessage(), 422);
        } catch (Throwable $e) {
            Logger::error('Reconhecimento pelo dispositivo falhou', ['exception' => $e], 'facial');
            $this->error('recognition_failed', 'Não foi possível concluir o reconhecimento.', 502);
        }
    }

    public function events(): void
    {
        $this->authenticatedDevice();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $where = ['1=1']; $params = [];
        foreach (['student_id' => 'e.student_id', 'class_id' => 'a.turma_id'] as $key => $column) {
            if ((int) ($_GET[$key] ?? 0) > 0) { $where[] = $column . ' = :' . $key; $params[$key] = (int) $_GET[$key]; }
        }
        if (in_array($_GET['kind'] ?? '', ['entrada', 'saida'], true)) { $where[] = 'e.kind = :kind'; $params['kind'] = $_GET['kind']; }
        if (!empty($_GET['date_from'])) { $where[] = 'DATE(e.event_at) >= :date_from'; $params['date_from'] = $_GET['date_from']; }
        if (!empty($_GET['date_to'])) { $where[] = 'DATE(e.event_at) <= :date_to'; $params['date_to'] = $_GET['date_to']; }
        $whereSql = implode(' AND ', $where);
        $total = (int) ($this->db->fetch("SELECT COUNT(*) total FROM student_access_events e INNER JOIN alunos a ON a.id = e.student_id WHERE {$whereSql}", $params)['total'] ?? 0);
        $rows = $this->db->fetchAll("SELECT e.id, e.kind, e.event_at, e.confidence, e.notified_at, a.id student_id, a.nome student_name, t.nome class_name FROM student_access_events e INNER JOIN alunos a ON a.id = e.student_id LEFT JOIN turmas t ON t.id = a.turma_id WHERE {$whereSql} ORDER BY e.event_at DESC LIMIT {$perPage} OFFSET {$offset}", $params);
        $this->json(['data' => array_map([$this, 'serializeEvent'], $rows), 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))]]);
    }

    public function logout(): void
    {
        $device = $this->authenticatedDevice();
        $this->db->update(
            "UPDATE facial_devices SET enabled = 0, token_hash = SHA2(CONCAT(token_hash, UUID()), 256) WHERE id = :id",
            ['id' => $device['id']]
        );
        http_response_code(204);
        exit;
    }

    private function authenticatedDevice(): array
    {
        if (!$this->schemaReady()) $this->error('device_api_not_configured', 'API de dispositivos indisponível.', 503);
        $header = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        $token = trim((string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ''));
        if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match)) {
            $token = $match[1];
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            $this->error('missing_token', 'Token do dispositivo não informado.', 401);
        }
        $device = $this->db->fetch(
            'SELECT id, device_uid, name, paired_by_user_id FROM facial_devices WHERE token_hash = :hash AND enabled = 1 LIMIT 1',
            ['hash' => hash('sha256', strtolower($token))]
        );
        if (!$device) $this->error('invalid_token', 'Dispositivo não autorizado.', 401);
        $this->db->update('UPDATE facial_devices SET last_seen_at = NOW() WHERE id = :id', ['id' => $device['id']]);
        return $device;
    }

    private function school(): array
    {
        $app = require __DIR__ . '/../../../../config/app.php';
        $school = $app['school'] ?? [];
        return [
            'id' => defined('TENANT_ID') ? (int) TENANT_ID : null,
            'name' => (string) ($school['name'] ?? 'EducaTudo'),
            'slug' => defined('TENANT_SLUG') ? (string) TENANT_SLUG : (string) ($school['code'] ?? ''),
            'api_base_url' => defined('URL') ? rtrim((string) URL, '/') : '',
        ];
    }

    private function schemaReady(): bool
    {
        return $this->db->tableExists('facial_device_pairing_codes')
            && $this->db->tableExists('facial_devices')
            && $this->db->tableExists('student_face_profiles')
            && $this->db->tableExists('student_face_samples')
            && $this->db->tableExists('student_access_events');
    }

    private function studentRow(int $id): ?array
    {
        if ($id <= 0) return null;
        $row = $this->db->fetch(
            "SELECT a.id, a.nome, a.ra, a.codigo_aluno, a.turma_id, t.nome class_name, t.serie class_grade,
                    fp.active face_active,
                    (SELECT COUNT(*) FROM student_face_samples fs WHERE fs.face_profile_id = fp.id) sample_count
             FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id
             LEFT JOIN student_face_profiles fp ON fp.student_id = a.id
             WHERE a.id = :id AND a.ativo = 1 LIMIT 1",
            ['id' => $id]
        );
        return $row ?: null;
    }

    private function serializeStudent(array $row): array
    {
        $classId = !empty($row['turma_id']) ? (int) $row['turma_id'] : null;
        $className = $row['class_name'] ?? null;
        $classGrade = $row['class_grade'] ?? null;
        $sampleCount = (int) ($row['sample_count'] ?? 0);
        $faceActive = !empty($row['face_active']);
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['nome'],
            'ra' => (string) ($row['ra'] ?: ($row['codigo_aluno'] ?? '')),
            'registration' => (string) ($row['codigo_aluno'] ?? ''),
            // Campos simples mantêm compatibilidade com o frontend do leitor.
            'class_id' => $classId,
            'class_name' => $className,
            'class_grade' => $classGrade,
            'sample_count' => $sampleCount,
            'has_face' => $faceActive && $sampleCount > 0,
            'class' => [
                'id' => $classId,
                'name' => $className,
                'grade' => $classGrade,
            ],
            'face_profile' => [
                'active' => $faceActive,
                'sample_count' => $sampleCount,
            ],
        ];
    }

    private function serializeEvent(array $row): array
    {
        $timestamp = strtotime((string) $row['event_at']);
        return [
            'id' => (int) $row['id'],
            'kind' => (string) $row['kind'],
            'date' => $timestamp ? date('Y-m-d', $timestamp) : null,
            'time' => $timestamp ? date('H:i', $timestamp) : null,
            'event_at' => $timestamp ? date(DATE_ATOM, $timestamp) : null,
            'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : null,
            'notified_parents' => !empty($row['notified_at']),
            'student' => isset($row['student_id']) ? ['id' => (int) $row['student_id'], 'name' => $row['student_name'] ?? null, 'class_name' => $row['class_name'] ?? null] : null,
        ];
    }

    private function externalKey(int $studentId): string
    {
        return 'et_t' . (defined('TENANT_ID') ? (int) TENANT_ID : 0) . '_a' . $studentId;
    }

    private function descriptorFromInput(array $input): array
    {
        $descriptor = $input['descriptor']
            ?? $input['face_descriptor']
            ?? $input['faceDescriptor']
            ?? $input['embedding']
            ?? [];
        if (is_string($descriptor)) {
            $decoded = json_decode($descriptor, true);
            $descriptor = is_array($decoded) ? $decoded : array_map('trim', explode(',', $descriptor));
        }
        return is_array($descriptor) ? array_values($descriptor) : [];
    }

    private function jsonInput(): array
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : (is_array($_POST) ? $_POST : []);
    }

    private function rateLimit(string $deviceUid): void
    {
        $key = hash('sha256', $deviceUid . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $file = sys_get_temp_dir() . '/educatudo_facial_pair_' . $key . '.json';
        $now = time();
        $attempts = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
        $attempts = is_array($attempts) ? array_values(array_filter($attempts, static fn ($time) => (int) $time > time() - 300)) : [];
        if (count($attempts) >= 10) $this->error('too_many_attempts', 'Muitas tentativas. Aguarde cinco minutos.', 429);
        $attempts[] = $now;
        @file_put_contents($file, json_encode($attempts), LOCK_EX);
    }

    private function error(string $code, string $message, int $status): void
    {
        $this->json(['success' => false, 'message' => $message, 'error' => ['code' => $code, 'message' => $message]], $status);
    }
}
