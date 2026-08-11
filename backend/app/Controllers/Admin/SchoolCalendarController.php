<?php

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Services/SchoolCalendarService.php';

/**
 * EducaTudo - SchoolCalendarController (Calendário Letivo)
 */
if (!class_exists('SchoolCalendarController')) {
class SchoolCalendarController extends AdminBaseController
{
    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('calendario_letivo', 'visualizar', false)) {
            return;
        }
        $service = new SchoolCalendarService($this->db);
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        if ($ano < 2000 || $ano > 2100) {
            $ano = (int) date('Y');
        }
        $cfg = $service->getAno($ano);
        $eventos = $cfg ? $service->eventos((int) $cfg['id']) : [];
        $status = $cfg
            ? $service->status((int) $cfg['id'], $ano, (int) $cfg['dias_meta'], (int) $cfg['carga_horaria_meta'])
            : null;
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/calendario-letivo/index', [
            'title' => 'Calendário Letivo - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'calendario_letivo',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'ano' => $ano,
            'config' => $cfg,
            'eventos' => $eventos,
            'status' => $status,
            'schema_pronto' => $service->tableExists(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvarAno(): void
    {
        if (!$this->enforceAdminPermissionKey('calendario_letivo', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/calendario-letivo');
            return;
        }
        $ano = (int) ($_POST['ano'] ?? date('Y'));
        $service = new SchoolCalendarService($this->db);
        $service->salvarAno(
            $ano,
            max(0, (int) ($_POST['dias_meta'] ?? 200)),
            max(0, (int) ($_POST['carga_horaria_meta'] ?? 800)),
            trim((string) ($_POST['observacao'] ?? ''))
        );
        $this->setFlashMessage('Calendário do ano salvo.', 'success');
        $this->redirect('/admin/calendario-letivo?ano=' . $ano);
    }

    public function salvarEvento(): void
    {
        if (!$this->enforceAdminPermissionKey('calendario_letivo', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/calendario-letivo');
            return;
        }
        $ano = (int) ($_POST['ano'] ?? date('Y'));
        $service = new SchoolCalendarService($this->db);
        $cfg = $service->getAno($ano);
        if (!$cfg) {
            $service->salvarAno($ano, 200, 800, '');
            $cfg = $service->getAno($ano);
        }
        $inicio = $this->sanitizeDate((string) ($_POST['data_inicio'] ?? ''));
        $fim = $this->sanitizeDate((string) ($_POST['data_fim'] ?? '')) ?: $inicio;
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        if ($inicio === '' || $descricao === '') {
            $this->setFlashMessage('Informe data e descrição do evento.', 'error');
            $this->redirect('/admin/calendario-letivo?ano=' . $ano);
            return;
        }
        $service->salvarEvento(
            (int) $cfg['id'],
            $inicio,
            $fim,
            (string) ($_POST['tipo'] ?? 'feriado'),
            $descricao,
            trim((string) ($_POST['link_reuniao'] ?? '')),
            trim((string) ($_POST['local_evento'] ?? '')),
            isset($_POST['visivel_aluno']) ? 1 : 0,
            isset($_POST['visivel_professor']) ? 1 : 0,
            isset($_POST['visivel_pais']) ? 1 : 0,
        );
        $this->setFlashMessage('Evento adicionado ao calendário.', 'success');
        $this->redirect('/admin/calendario-letivo?ano=' . $ano);
    }

    public function excluirEvento(): void
    {
        if (!$this->enforceAdminPermissionKey('calendario_letivo', 'excluir', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/calendario-letivo');
            return;
        }
        $ano = (int) ($_POST['ano'] ?? date('Y'));
        (new SchoolCalendarService($this->db))->excluirEvento((int) ($_POST['id'] ?? 0));
        $this->setFlashMessage('Evento removido.', 'success');
        $this->redirect('/admin/calendario-letivo?ano=' . $ano);
    }

    private function sanitizeDate(string $raw): string
    {
        $raw = trim($raw);
        $dt = DateTime::createFromFormat('Y-m-d', $raw);
        return ($dt && $dt->format('Y-m-d') === $raw) ? $raw : '';
    }
}
}
