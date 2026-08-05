<?php
require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../../../Middleware/MobileApiAuthMiddleware.php';
require_once __DIR__ . '/../../../Policies/ParentStudentAccessPolicy.php';
require_once __DIR__ . '/../../../Helpers/AvatarUrlHelper.php';

class MobileParentController extends BaseController
{
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    public function me(): void
    {
        $parent = MobileApiAuthMiddleware::$parent;
        if (!is_array($parent)) {
            $this->json(['error' => ['code' => 'unauthorized', 'message' => 'Não autorizado.']], 401);
        }

        $this->json(['data' => $this->serializeParent($parent)]);
    }

    public function students(): void
    {
        $parentId = (int) MobileApiAuthMiddleware::$parentId;
        $students = (new ParentStudentAccessPolicy($this->db))->studentsFor($parentId);

        $data = array_map(static function (array $student): array {
            $photoUrl = !empty($student['foto_url'])
                ? AvatarUrlHelper::normalizeAdminAvatarUrl((string) $student['foto_url'])
                : null;

            return [
                'id' => (int) $student['id'],
                'name' => (string) $student['nome'],
                'photo_url' => $photoUrl,
                'class' => $student['turma_id'] !== null ? [
                    'id' => (int) $student['turma_id'],
                    'name' => $student['class_name'] !== null ? (string) $student['class_name'] : null,
                    'grade' => $student['class_grade'] !== null ? (string) $student['class_grade'] : null,
                ] : null,
            ];
        }, $students);

        $this->json(['data' => $data, 'meta' => ['count' => count($data)]]);
    }

    private function serializeParent(array $parent): array
    {
        return [
            'id' => (int) $parent['id'],
            'name' => (string) $parent['nome'],
            'email' => $parent['email'] !== null ? (string) $parent['email'] : null,
            'cpf' => preg_replace('/\D+/', '', (string) ($parent['cpf'] ?? '')),
            'phone' => $parent['telefone'] !== null ? (string) $parent['telefone'] : null,
            'must_change_password' => !empty($parent['force_password_change']),
        ];
    }
}
