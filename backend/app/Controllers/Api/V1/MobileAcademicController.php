<?php
require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Middleware/MobileApiAuthMiddleware.php';
require_once __DIR__ . '/../../../Policies/ParentStudentAccessPolicy.php';
require_once __DIR__ . '/../../../Services/ParentAcademicReadService.php';

class MobileAcademicController extends BaseController
{
    private $policy;
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->policy = new ParentStudentAccessPolicy();
        $this->service = new ParentAcademicReadService();
    }

    public function dashboard($id): void { $this->respond($id, 'dashboard'); }
    public function exams($id): void { $this->respond($id, 'exams', true); }
    public function journeys($id): void { $this->respond($id, 'journeys', true); }
    public function lessonPlans($id): void { $this->respond($id, 'lessonPlans', true); }
    public function writingJourneys($id): void { $this->respond($id, 'writingJourneys', true); }
    public function essays($id): void { $this->respond($id, 'essays', true); }
    public function notices($id): void { $this->respond($id, 'notices', true); }
    public function reportCard($id): void { $this->respond($id, 'reportCard', true); }

    public function accessEvents($id): void
    {
        $studentId = $this->authorizedStudentId($id);
        $data = $this->service->accessEvents($studentId, $_GET['from'] ?? null, $_GET['to'] ?? null);
        $this->json(['data'=>$data,'meta'=>['count'=>count($data['events'] ?? [])]]);
    }

    public function lessonPlan($id, $planId): void
    {
        $studentId = $this->authorizedStudentId($id);
        $validatedPlanId = filter_var($planId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validatedPlanId === false) {
            $this->notFound('lesson_plan_not_found', 'Plano de aula não encontrado.');
        }

        $data = $this->service->lessonPlan($studentId, (int) $validatedPlanId);
        if ($data === null) {
            // Não revela planos de outra turma.
            $this->notFound('lesson_plan_not_found', 'Plano de aula não encontrado.');
        }
        $this->json(['data' => $data]);
    }

    private function respond($rawId, string $method, bool $collection = false): void
    {
        $studentId = $this->authorizedStudentId($rawId);

        $data = $this->service->{$method}($studentId);
        $response = ['data' => $data];
        if ($collection) {
            $response['meta'] = ['count' => count($data)];
        }
        $this->json($response);
    }

    private function authorizedStudentId($rawId): int
    {
        $studentId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $parentId = (int) MobileApiAuthMiddleware::$parentId;
        if ($studentId === false || !$this->policy->canAccess($parentId, (int) $studentId)) {
            // Não revela se o aluno existe para responsáveis sem vínculo.
            $this->notFound('student_not_found', 'Aluno não encontrado.');
        }
        return (int) $studentId;
    }

    private function notFound(string $code, string $message): void
    {
        $this->json(['error' => ['code' => $code, 'message' => $message]], 404);
    }
}
