<?php

require_once __DIR__ . '/../../Services/AvaCourseService.php';
require_once __DIR__ . '/../../Services/AvaActivityService.php';
require_once __DIR__ . '/../../Models/Ava/DisciplineEnrollment.php';

/**
 * EducaTudo - AVA: atividades/tarefas e rubricas na área do professor.
 * Reaproveita views admin/ava/* com base_url '/professor/ava'.
 */
if (!class_exists('AvaActivityTeacherController')) {
class AvaActivityTeacherController extends BaseController
{
    private const EXTENSOES = ['pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'mp3', 'mp4'];
    private const BASE = '/professor/ava';

    private $authManager;
    private AvaCourseService $service;
    private AvaActivityService $activities;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirect('/professor');
        }
        $this->service = new AvaCourseService();
        $this->activities = new AvaActivityService();
    }

    private function professorId(): int
    {
        $user = $this->authManager->getUser();
        return (int) ($user['id'] ?? 0);
    }

    private function ownsDiscipline(int $disciplinaId): bool
    {
        return $this->service->disciplinesModel()->isOwnedByTeacher($disciplinaId, $this->professorId());
    }

    private function csrfOk(): bool
    {
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            return false;
        }
        return true;
    }

    // ---- Listagem de atividades ------------------------------------------

    public function list(string $disciplinaId): void
    {
        $did = (int) $disciplinaId;
        if (!$this->ownsDiscipline($did)) {
            $this->redirect(self::BASE);
            return;
        }
        $disc = $this->service->disciplinesModel()->find($did);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'admin/ava/atividades', [
            'title' => 'Atividades - ' . htmlspecialchars((string) $disc['nome']),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'atividades' => $this->activities->listForTeacher($did),
            'rubricas' => $this->activities->rubricsModel()->byDiscipline($did),
            'base_url' => self::BASE,
            'is_admin' => false,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function create(string $disciplinaId): void
    {
        $did = (int) $disciplinaId;
        if (!$this->ownsDiscipline($did)) {
            $this->redirect(self::BASE);
            return;
        }
        $this->renderForm($did, null);
    }

    public function edit(string $id): void
    {
        $atv = $this->activities->activitiesModel()->find((int) $id);
        if (!$atv || !$this->ownsDiscipline((int) $atv['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $this->renderForm((int) $atv['disciplina_id'], $atv);
    }

    private function renderForm(int $disciplinaId, ?array $atv): void
    {
        $disc = $this->service->disciplinesModel()->find($disciplinaId);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'admin/ava/atividade_form', [
            'title' => ($atv ? 'Editar Atividade' : 'Nova Atividade') . ' - AVA',
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'atividade' => $atv,
            'modulos' => $this->service->modulesModel()->byDiscipline($disciplinaId),
            'rubricas' => $this->activities->rubricsModel()->byDiscipline($disciplinaId),
            'tipos' => Activity::TIPOS,
            'status_opcoes' => Activity::STATUS,
            'base_url' => self::BASE,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function store(string $disciplinaId): void
    {
        $did = (int) $disciplinaId;
        if (!$this->csrfOk() || !$this->ownsDiscipline($did)) {
            $this->redirect(self::BASE . '/disciplinas/' . $did . '/atividades');
            return;
        }
        $data = $_POST;
        $data['disciplina_id'] = $did;
        $data['professor_id'] = $this->professorId();
        if (trim((string) ($data['titulo'] ?? '')) === '') {
            $this->setFlashMessage('Informe o título da atividade.', 'error');
            $this->redirect(self::BASE . '/disciplinas/' . $did . '/atividades/nova');
            return;
        }
        $this->activities->activitiesModel()->save($data);
        $this->setFlashMessage('Atividade criada.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $did . '/atividades');
    }

    public function update(string $id): void
    {
        $atv = $this->activities->activitiesModel()->find((int) $id);
        if (!$this->csrfOk() || !$atv || !$this->ownsDiscipline((int) $atv['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $data = $_POST;
        $data['disciplina_id'] = (int) $atv['disciplina_id'];
        $this->activities->activitiesModel()->save($data, (int) $id);
        $this->setFlashMessage('Atividade atualizada.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . (int) $atv['disciplina_id'] . '/atividades');
    }

    public function delete(string $id): void
    {
        $atv = $this->activities->activitiesModel()->find((int) $id);
        if (!$this->csrfOk() || !$atv || !$this->ownsDiscipline((int) $atv['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $did = (int) $atv['disciplina_id'];
        $this->activities->activitiesModel()->delete((int) $id);
        $this->setFlashMessage('Atividade removida.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $did . '/atividades');
    }

    // ---- Entregas / correção ---------------------------------------------

    public function submissions(string $id): void
    {
        $atv = $this->activities->activitiesModel()->find((int) $id);
        if (!$atv || !$this->ownsDiscipline((int) $atv['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'admin/ava/atividade_entregas', [
            'title' => 'Entregas - ' . htmlspecialchars((string) $atv['titulo']),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'atividade' => $atv,
            'entregas' => $this->activities->submissionsModel()->byActivity((int) $id),
            'total_alunos' => (new DisciplineEnrollment())->countByDiscipline((int) $atv['disciplina_id']),
            'base_url' => self::BASE,
        ]);
    }

    public function gradeForm(string $id): void
    {
        $entrega = $this->activities->submissionsModel()->findById((int) $id);
        if (!$entrega || !$this->ownsDiscipline((int) $entrega['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $rubrica = !empty($entrega['rubrica_id'])
            ? $this->activities->rubricsModel()->findWithCriteria((int) $entrega['rubrica_id'])
            : null;
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'admin/ava/atividade_corrigir', [
            'title' => 'Corrigir entrega',
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'entrega' => $entrega,
            'arquivos' => $this->activities->submissionsModel()->files((int) $id),
            'rubrica' => $rubrica,
            'resultado_anterior' => $this->decodeRubrica($entrega['rubrica_resultado_json'] ?? null),
            'base_url' => self::BASE,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function grade(string $id): void
    {
        $entrega = $this->activities->submissionsModel()->findById((int) $id);
        if (!$this->csrfOk() || !$entrega || !$this->ownsDiscipline((int) $entrega['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $notaMaxima = (float) ($entrega['nota_maxima'] ?? 10);
        $reenviar = !empty($_POST['reenviar']);
        $resultado = null;
        if (!empty($entrega['rubrica_id']) && isset($_POST['criterio']) && is_array($_POST['criterio'])) {
            $pontuacoes = [];
            foreach ($_POST['criterio'] as $cid => $val) {
                $pontuacoes[(int) $cid] = (float) $val;
            }
            $calc = $this->activities->gradeFromRubric((int) $entrega['rubrica_id'], $pontuacoes, $notaMaxima);
            $nota = $calc['nota'];
            $resultado = $calc['resultado'];
        } else {
            $nota = max(0.0, min($notaMaxima, (float) ($_POST['nota'] ?? 0)));
        }
        $this->activities->submissionsModel()->grade(
            (int) $id,
            $nota,
            trim((string) ($_POST['feedback'] ?? '')) ?: null,
            $resultado,
            $this->professorId(),
            $reenviar
        );
        $this->setFlashMessage($reenviar ? 'Devolvido para reenvio.' : 'Entrega avaliada.', 'success');
        $this->redirect(self::BASE . '/atividades/' . (int) $entrega['atividade_id'] . '/entregas');
    }

    public function downloadFile(string $id): void
    {
        $arquivo = $this->activities->submissionsModel()->findFile((int) $id);
        if (!$arquivo || !$this->ownsDiscipline((int) $arquivo['disciplina_id'])) {
            http_response_code(403);
            echo 'Acesso negado';
            return;
        }
        $this->streamFile($arquivo);
    }

    // ---- Rubricas ---------------------------------------------------------

    public function storeRubrica(string $disciplinaId): void
    {
        $did = (int) $disciplinaId;
        if (!$this->csrfOk() || !$this->ownsDiscipline($did)) {
            $this->redirect(self::BASE);
            return;
        }
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        if ($titulo === '') {
            $this->setFlashMessage('Informe o título da rubrica.', 'error');
            $this->redirect(self::BASE . '/disciplinas/' . $did . '/atividades');
            return;
        }
        $id = (int) ($_POST['rubrica_id'] ?? 0);
        $rubricaId = $this->activities->rubricsModel()->save($did, $titulo, trim((string) ($_POST['descricao'] ?? '')) ?: null, $id ?: null);
        $criterios = [];
        $titulos = $_POST['criterio_titulo'] ?? [];
        if (is_array($titulos)) {
            foreach ($titulos as $i => $t) {
                $criterios[] = [
                    'titulo' => (string) $t,
                    'descricao' => (string) ($_POST['criterio_descricao'][$i] ?? ''),
                    'peso' => (float) ($_POST['criterio_peso'][$i] ?? 1),
                    'pontuacao_max' => (float) ($_POST['criterio_pmax'][$i] ?? 10),
                ];
            }
        }
        $this->activities->rubricsModel()->replaceCriteria($rubricaId, $criterios);
        $this->setFlashMessage('Rubrica salva.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $did . '/atividades');
    }

    public function deleteRubrica(string $id): void
    {
        $rubrica = $this->activities->rubricsModel()->find((int) $id);
        $did = (int) ($rubrica['disciplina_id'] ?? 0);
        if (!$this->csrfOk() || !$rubrica || !$this->ownsDiscipline($did)) {
            $this->redirect(self::BASE);
            return;
        }
        $this->activities->rubricsModel()->delete((int) $id);
        $this->setFlashMessage('Rubrica removida.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $did . '/atividades');
    }

    // ---- Helpers ----------------------------------------------------------

    private function decodeRubrica($json): array
    {
        if (empty($json)) {
            return [];
        }
        $arr = is_array($json) ? $json : json_decode((string) $json, true);
        return is_array($arr) ? $arr : [];
    }

    private function streamFile(array $arquivo): void
    {
        if (empty($arquivo['arquivo_key'])) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $conteudo = (new MediaStorageService($this->config))->getContents('arquivos', (string) $arquivo['arquivo_key']);
        if ($conteudo === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: ' . (string) ($arquivo['mime'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) ($arquivo['nome'] ?? 'entrega')) . '"');
            header('Content-Length: ' . strlen($conteudo));
        }
        echo $conteudo;
    }
}
}
