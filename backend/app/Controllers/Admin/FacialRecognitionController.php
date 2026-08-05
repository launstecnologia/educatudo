<?php

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Services/FacialRecognitionProviderService.php';
require_once __DIR__ . '/../../Services/FacialAttendanceNotificationService.php';
require_once __DIR__ . '/../../Core/Logger.php';

class FacialRecognitionController extends AdminBaseController
{
    private FacialRecognitionProviderService $provider;

    public function __construct()
    {
        parent::__construct();
        $this->provider = new FacialRecognitionProviderService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'visualizar', false)) return;
        $schemaReady = $this->schemaReady();
        $students = [];
        $events = [];
        if ($schemaReady) {
            $query = trim((string) ($_GET['q'] ?? ''));
            $params = [];
            $where = 'a.ativo = 1';
            if ($query !== '') {
                $where .= ' AND (a.nome LIKE :query OR a.cpf LIKE :query_cpf)';
                $params['query'] = '%' . $query . '%';
                $params['query_cpf'] = '%' . preg_replace('/\D+/', '', $query) . '%';
            }
            $students = $this->db->fetchAll(
                "SELECT a.id, a.nome, t.nome class_name, t.serie class_grade,
                        fp.id profile_id, fp.active face_active, fp.consent_at,
                        (SELECT COUNT(*) FROM student_face_samples fs WHERE fs.face_profile_id = fp.id) sample_count,
                        (SELECT CONCAT(e.kind, '|', DATE_FORMAT(e.event_at, '%d/%m/%Y %H:%i'))
                           FROM student_access_events e WHERE e.student_id = a.id ORDER BY e.event_at DESC LIMIT 1) last_event
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 LEFT JOIN student_face_profiles fp ON fp.student_id = a.id
                 WHERE {$where}
                 ORDER BY a.nome LIMIT 300",
                $params
            );
            $events = $this->recentEvents(100);
        }
        $this->viewWithLayout('admin', 'admin/facial-recognition/index', [
            'title' => 'Entrada e saída por reconhecimento facial',
            'page_title' => 'Reconhecimento Facial',
            'current_page' => 'reconhecimento-facial',
            'user' => $this->auth->getUser(),
            'students' => $students,
            'events' => $events,
            'schema_ready' => $schemaReady,
            'api_configured' => $this->provider->isConfigured(),
            'query' => $_GET['q'] ?? '',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function studentEvents($id): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'visualizar', false)) return;
        $student = $this->student((int) $id);
        if (!$student) {
            $this->setFlashMessage('Aluno não encontrado.', 'error');
            $this->redirect('/admin/students');
            return;
        }
        $events = [];
        if ($this->schemaReady()) {
            $events = $this->db->fetchAll(
                "SELECT e.id, e.kind, e.event_at, e.confidence
                 FROM student_access_events e
                 WHERE e.student_id = ?
                 ORDER BY e.event_at DESC LIMIT 200",
                [(int) $id]
            );
        }
        $this->viewWithLayout('admin', 'admin/facial-recognition/student-events', [
            'title'        => 'Registros de Entrada/Saída — ' . $student['nome'],
            'current_page' => 'reconhecimento-facial',
            'user'         => $this->auth->getUser(),
            'student'      => $student,
            'events'       => $events,
            'schema_ready' => $this->schemaReady(),
        ]);
    }

    public function enroll($id): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'cadastrar', false)) return;
        if (!$this->schemaReady()) {
            $this->setFlashMessage('Execute a migration 066_facial_attendance.sql.', 'error');
            $this->redirect('/admin/reconhecimento-facial');
        }
        $student = $this->student((int) $id);
        if (!$student) {
            $this->setFlashMessage('Aluno não encontrado.', 'error');
            $this->redirect('/admin/reconhecimento-facial');
        }
        $profile = $this->db->fetch(
            "SELECT fp.*, (SELECT COUNT(*) FROM student_face_samples fs WHERE fs.face_profile_id = fp.id) sample_count
             FROM student_face_profiles fp WHERE fp.student_id = :student_id LIMIT 1",
            ['student_id' => (int) $student['id']]
        );
        $events = $this->db->fetchAll(
            "SELECT id, kind, event_at, confidence, notified_at
             FROM student_access_events
             WHERE student_id = :student_id
             ORDER BY event_at DESC
             LIMIT 100",
            ['student_id' => (int) $student['id']]
        );
        $this->viewWithLayout('admin', 'admin/facial-recognition/enroll', [
            'title' => 'Cadastrar face - ' . $student['nome'],
            'page_title' => 'Cadastro Facial',
            'current_page' => 'reconhecimento-facial',
            'user' => $this->auth->getUser(),
            'student' => $student,
            'profile' => $profile,
            'events' => $events,
            'api_configured' => $this->provider->isConfigured(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function registerSample($id): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'cadastrar', true)) return;
        $payload = $this->jsonInput();
        if (!$this->verifyCsrfToken($payload['_token'] ?? '')) {
            $this->json(['error' => 'Sessão expirada. Atualize a página.'], 419);
        }
        if (empty($payload['consent'])) {
            $this->json(['error' => 'Confirme o consentimento do responsável.'], 422);
        }
        $student = $this->student((int) $id);
        if (!$student) $this->json(['error' => 'Aluno não encontrado.'], 404);
        try {
            $descriptor = $payload['descriptor'] ?? [];
            if (is_string($descriptor)) {
                $descriptor = array_map('trim', explode(',', $descriptor));
            }
            $externalKey = $this->externalKey((int) $student['id']);
            $user = $this->auth->getUser();
            $profile = $this->db->fetch('SELECT * FROM student_face_profiles WHERE student_id = :id LIMIT 1', ['id' => $student['id']]);
            if (!$profile) {
                $profileId = (int) $this->db->insert(
                    "INSERT INTO student_face_profiles (student_id, external_key, consent_at, consent_by_user_id, active)
                     VALUES (:student_id, :external_key, NOW(), :user_id, 1)",
                    ['student_id' => $student['id'], 'external_key' => $externalKey, 'user_id' => $user['id'] ?? null]
                );
            } else {
                $profileId = (int) $profile['id'];
                $externalKey = (string) $profile['external_key'];
                $this->db->update(
                    'UPDATE student_face_profiles SET active = 1, consent_at = NOW(), consent_by_user_id = :user_id WHERE id = :id',
                    ['user_id' => $user['id'] ?? null, 'id' => $profileId]
                );
            }
            $result = $this->provider->registerFace($externalKey, (array) $descriptor);
            $providerFaceId = (string) ($result['face']['id'] ?? '');
            if ($providerFaceId === '') throw new RuntimeException('O serviço não retornou o identificador da amostra.');
            $this->db->query(
                'INSERT IGNORE INTO student_face_samples (face_profile_id, provider_face_id) VALUES (:profile_id, :provider_face_id)',
                ['profile_id' => $profileId, 'provider_face_id' => $providerFaceId]
            );
            $count = (int) ($this->db->fetch('SELECT COUNT(*) total FROM student_face_samples WHERE face_profile_id = :id', ['id' => $profileId])['total'] ?? 0);
            $this->json(['success' => true, 'sample_count' => $count, 'message' => 'Amostra facial cadastrada com sucesso.']);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Logger::error('Cadastro facial falhou', ['student_id' => (int) $id, 'exception' => $e], 'facial');
            $this->json(['error' => 'Não foi possível cadastrar a face. Tente novamente.'], 502);
        }
    }

    public function deleteProfile($id): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'excluir', true)) return;
        $payload = $this->jsonInput();
        if (!$this->verifyCsrfToken($payload['_token'] ?? ($_POST['_token'] ?? ''))) {
            $this->json(['error' => 'Sessão expirada.'], 419);
        }
        $profile = $this->db->fetch('SELECT * FROM student_face_profiles WHERE student_id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$profile) $this->json(['error' => 'Cadastro facial não encontrado.'], 404);
        try {
            $this->provider->deleteFaces((string) $profile['external_key']);
            $this->db->query('DELETE FROM student_face_profiles WHERE id = :id', ['id' => (int) $profile['id']]);
            $this->json(['success' => true]);
        } catch (Throwable $e) {
            Logger::error('Exclusão facial falhou', ['student_id' => (int) $id, 'exception' => $e], 'facial');
            $this->json(['error' => 'Não foi possível excluir o cadastro no serviço facial.'], 502);
        }
    }

    public function kiosk(): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'alterar', false)) return;
        $this->viewWithLayout('admin', 'admin/facial-recognition/kiosk', [
            'title' => 'Totem de entrada e saída',
            'page_title' => 'Totem Facial',
            'current_page' => 'reconhecimento-facial',
            'user' => $this->auth->getUser(),
            'api_configured' => $this->provider->isConfigured(),
            'schema_ready' => $this->schemaReady(),
            'events' => $this->schemaReady() ? $this->recentEvents(20) : [],
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function recognize(): void
    {
        if (!$this->enforceAdminPermissionKey('reconhecimento_facial', 'alterar', true)) return;
        $payload = $this->jsonInput();
        if (!$this->verifyCsrfToken($payload['_token'] ?? '')) {
            $this->json(['error' => 'Sessão expirada. Atualize a página.'], 419);
        }
        try {
            $result = $this->provider->recognize((array) ($payload['descriptor'] ?? []));
            if (empty($result['match'])) {
                $this->json(['success' => true, 'match' => false, 'message' => 'Rosto não reconhecido.']);
            }
            $externalKey = (string) ($result['name'] ?? '');
            $profile = $this->db->fetch(
                "SELECT fp.id, fp.student_id, a.nome, a.turma_id
                 FROM student_face_profiles fp INNER JOIN alunos a ON a.id = fp.student_id AND a.ativo = 1
                 WHERE fp.external_key = :external_key AND fp.active = 1 LIMIT 1",
                ['external_key' => $externalKey]
            );
            if (!$profile) {
                Logger::warning('Reconhecimento facial sem vínculo local', ['external_key_hash' => hash('sha256', $externalKey)], 'facial');
                $this->json(['success' => true, 'match' => false, 'message' => 'Cadastro reconhecido sem vínculo ativo no EducaTudo.']);
            }
            if (empty($result['registered'])) {
                $this->json([
                    'success' => true,
                    'match' => true,
                    'registered' => false,
                    'student_name' => $profile['nome'],
                    'message' => 'Leitura ignorada pelo intervalo de segurança.',
                ]);
            }
            $presence = is_array($result['presence'] ?? null) ? $result['presence'] : [];
            $providerPresenceId = (string) ($presence['id'] ?? '');
            $kind = (string) ($presence['kind'] ?? '');
            if ($providerPresenceId === '' || !in_array($kind, ['entrada', 'saida'], true)) {
                throw new RuntimeException('Resposta de presença inválida.');
            }
            $existing = $this->db->fetch('SELECT id, event_at FROM student_access_events WHERE provider_presence_id = :id LIMIT 1', ['id' => $providerPresenceId]);
            if ($existing) {
                $this->json([
                    'success' => true, 'match' => true, 'registered' => false,
                    'student_name' => $profile['nome'], 'kind' => $kind,
                    'message' => 'Evento já processado.',
                ]);
            }
            $eventAt = new DateTimeImmutable('now');
            $confidence = isset($result['confidence']) ? (float) $result['confidence'] : null;
            $user = $this->auth->getUser();
            $eventId = (int) $this->db->insert(
                "INSERT INTO student_access_events
                    (student_id, kind, event_at, confidence, provider_presence_id, registered_by_user_id)
                 VALUES (:student_id, :kind, :event_at, :confidence, :provider_id, :user_id)",
                [
                    'student_id' => $profile['student_id'], 'kind' => $kind,
                    'event_at' => $eventAt->format('Y-m-d H:i:s'), 'confidence' => $confidence,
                    'provider_id' => $providerPresenceId, 'user_id' => $user['id'] ?? null,
                ]
            );
            $notification = ['parents' => 0, 'sent' => 0, 'failed' => 0];
            try {
                $notification = (new FacialAttendanceNotificationService($this->db))->notify(
                    ['id' => (int) $profile['student_id'], 'nome' => (string) $profile['nome']],
                    $eventId,
                    $kind,
                    $eventAt,
                    (int) ($user['id'] ?? 0)
                );
                $this->db->update('UPDATE student_access_events SET notified_at = NOW() WHERE id = :id', ['id' => $eventId]);
            } catch (Throwable $notifyError) {
                Logger::error('Push de presença falhou', ['event_id' => $eventId, 'exception' => $notifyError], 'facial');
            }
            $this->json([
                'success' => true, 'match' => true, 'registered' => true,
                'event_id' => $eventId, 'student_name' => $profile['nome'],
                'kind' => $kind, 'time' => $eventAt->format('H:i'),
                'confidence' => $confidence, 'notification' => $notification,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Logger::error('Reconhecimento facial falhou', ['exception' => $e], 'facial');
            $this->json(['error' => 'Não foi possível concluir o reconhecimento.'], 502);
        }
    }

    private function student(int $id): ?array
    {
        if ($id <= 0) return null;
        $row = $this->db->fetch(
            'SELECT a.id, a.nome, a.turma_id, t.nome class_name, t.serie class_grade FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id WHERE a.id = :id AND a.ativo = 1 LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    private function externalKey(int $studentId): string
    {
        $tenant = defined('TENANT_ID') ? (int) TENANT_ID : 0;
        return 'et_t' . $tenant . '_a' . $studentId;
    }

    private function schemaReady(): bool
    {
        return $this->db->tableExists('student_face_profiles')
            && $this->db->tableExists('student_face_samples')
            && $this->db->tableExists('student_access_events');
    }

    private function recentEvents(int $limit): array
    {
        return $this->db->fetchAll(
            "SELECT e.id, e.kind, e.event_at, e.confidence, e.notified_at,
                    a.id student_id, a.nome student_name, t.nome class_name
             FROM student_access_events e
             INNER JOIN alunos a ON a.id = e.student_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             ORDER BY e.event_at DESC LIMIT " . max(1, min(500, $limit))
        );
    }

    private function jsonInput(): array
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : $_POST;
    }
}
