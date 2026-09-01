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
            'pode_publicar_escolar' => $this->podePublicarCalendarioEscolar($service),
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
        if (!$cfg) {
            $this->setFlashMessage('Não foi possível preparar o calendário do ano.', 'error');
            $this->redirect('/admin/calendario-letivo?ano=' . $ano);
            return;
        }
        $inicio = $this->sanitizeDate((string) ($_POST['data_inicio'] ?? ''));
        $fim = $this->sanitizeDate((string) ($_POST['data_fim'] ?? '')) ?: $inicio;
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $tipo = (string) ($_POST['tipo'] ?? 'feriado');
        $tiposValidos = ['feriado', 'recesso', 'reposicao', 'evento', 'suspensao', 'avaliacao'];
        if (!in_array($tipo, $tiposValidos, true)) {
            $tipo = 'feriado';
        }
        $local = trim((string) ($_POST['local_evento'] ?? ''));
        $publicarEscolar = !empty($_POST['publicar_calendario_escolar']) && $this->podePublicarCalendarioEscolar($service);
        $visivelPais = (isset($_POST['visivel_pais']) || $publicarEscolar) ? 1 : 0;
        if ($inicio === '' || $descricao === '') {
            $this->setFlashMessage('Informe data e descrição do evento.', 'error');
            $this->redirect('/admin/calendario-letivo?ano=' . $ano);
            return;
        }
        $service->salvarEvento(
            (int) $cfg['id'],
            $inicio,
            $fim,
            $tipo,
            $descricao,
            trim((string) ($_POST['link_reuniao'] ?? '')),
            $local,
            isset($_POST['visivel_aluno']) ? 1 : 0,
            isset($_POST['visivel_professor']) ? 1 : 0,
            $visivelPais,
        );
        $msg = 'Evento adicionado ao calendário letivo.';
        $escolarId = 0;
        if ($publicarEscolar) {
            $user = $this->auth->getUser();
            $escolarId = $service->publicarNoCalendarioEscolar(
                $descricao,
                $inicio,
                $fim,
                $tipo,
                $local,
                (int) ($user['id'] ?? 0)
            );
            $msg = $escolarId > 0
                ? 'Evento adicionado ao calendário letivo e publicado no calendário escolar. Os responsáveis foram notificados.'
                : 'Evento adicionado ao calendário letivo. Não foi possível publicar no calendário escolar.';
        }
        $this->setFlashMessage($msg, $publicarEscolar && $escolarId <= 0 ? 'error' : 'success');
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

    private function podePublicarCalendarioEscolar(SchoolCalendarService $service): bool
    {
        if (!$service->tabelaEscolarExiste()) {
            return false;
        }
        if (class_exists('LayoutHelper') && !LayoutHelper::isModuleEnabled('calendario_escolar')) {
            return false;
        }
        if (!class_exists('AdminPermissionMatrix')) {
            require_once __DIR__ . '/../../Core/AdminPermissionMatrix.php';
        }
        $permissions = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $this->auth->getUser() ?? []);
        return AdminPermissionMatrix::can($permissions, 'calendario_escolar', 'cadastrar');
    }
}
}
