<?php

namespace App\Painel\User\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Services\SchoolCalendarService;

/**
 * Calendário letivo do aluno — extraído do StudentController (Etapa 8).
 * URL preservada: GET /calendario-letivo
 */
if (!class_exists(__NAMESPACE__ . '\\AlunoCalendarioController', false)) {
class AlunoCalendarioController extends BaseController
{
    private $authManager;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'aluno') {
            $this->redirect('/dashboard');
        }
    }

    public function index(): void
    {
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        if ($ano < 2000 || $ano > 2100) {
            $ano = (int) date('Y');
        }
        $service = new SchoolCalendarService($this->db);
        $user = $this->authManager->getUser();
        $escopo = $service->escopoDaTurma((int) ($user['turma_id'] ?? 0));
        $cfg = $service->resolver($ano, $escopo['serie_id'], $escopo['curso_id']);
        $todos = $cfg ? $service->eventos((int) $cfg['id']) : [];
        $eventos = array_values(array_filter($todos, static fn ($e) => (int) ($e['visivel_aluno'] ?? 0) === 1));
        $visuais = $service->visuaisTipos();
        $this->viewWithLayout('student', 'student/calendario-letivo', [
            'title' => 'Calendário Letivo - EducaTudo',
            'user' => $user,
            'ano' => $ano,
            'eventos' => $eventos,
            'tipoLabels' => $visuais['labels'],
            'tipoBg' => $visuais['bg'],
            'tipoText' => $visuais['text'],
            'calendario_nome' => (string) ($cfg['nome'] ?? ''),
            'calendario_rotulo' => (string) ($cfg['rotulo'] ?? ''),
            'calendario_cor' => (string) ($cfg['cor'] ?? ''),
            'calendario_cor_texto' => (string) ($cfg['cor_texto'] ?? ''),
        ]);
    }
}
}

if (!class_exists('AlunoCalendarioController', false) && class_exists(__NAMESPACE__ . '\\AlunoCalendarioController', false)) {
    class_alias(__NAMESPACE__ . '\\AlunoCalendarioController', 'AlunoCalendarioController');
}
