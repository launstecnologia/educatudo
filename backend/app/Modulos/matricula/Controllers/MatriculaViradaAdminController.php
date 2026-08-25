<?php

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../Services/MatriculaViradaService.php';
require_once __DIR__ . '/../Services/MatriculaProcessoService.php';

use App\Modulos\Matricula\Services\MatriculaViradaService;
use App\Modulos\Matricula\Services\MatriculaProcessoService;

if (!class_exists('MatriculaViradaAdminController')) {
class MatriculaViradaAdminController extends BaseController
{
    private $auth;
    private MatriculaViradaService $service;
    private MatriculaProcessoService $processoService;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->service = new MatriculaViradaService();
        $this->processoService = new MatriculaProcessoService();
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin');
        }
    }

    public function form(): void
    {
        $anoOrigem = (int) ($_GET['ano_origem_id'] ?? 0);
        $anoDestino = (int) ($_GET['ano_destino_id'] ?? 0);
        $mapa = [];
        if ($anoOrigem > 0 && $anoDestino > 0) {
            $mapa = $this->service->mapaSucessao($anoOrigem, $anoDestino);
        }
        $this->viewWithLayout('admin', 'admin/matricula/virada', [
            'title' => 'Virada de ano — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'anos_letivos' => $this->processoService->getAnosLetivos(),
            'ano_origem_id' => $anoOrigem,
            'ano_destino_id' => $anoDestino,
            'mapa' => $mapa,
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function clonar(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/virada', 'Token inválido.', 'error');
            return;
        }
        $origem = (int) ($_POST['ano_origem_id'] ?? 0);
        $destino = (int) ($_POST['ano_destino_id'] ?? 0);
        try {
            $r = $this->service->clonarTurmas($origem, $destino);
            $msg = "Turmas clonadas: {$r['clonadas']}";
            if ($r['ligadas'] > 0) {
                $msg .= ", {$r['ligadas']} já ligadas";
            }
            if ($r['erros'] !== []) {
                $msg .= '. Falhas: ' . implode('; ', array_slice($r['erros'], 0, 3));
            }
            $this->redirectWithMsg(
                '/admin/enrollment/virada?ano_origem_id=' . $origem . '&ano_destino_id=' . $destino,
                $msg . '.',
                $r['erros'] === [] ? 'success' : 'error'
            );
        } catch (\Throwable $e) {
            $this->redirectWithMsg('/admin/enrollment/virada', $this->msgErro($e), 'error');
        }
    }

    private function redirectWithMsg(string $url, string $msg, string $type = 'success'): void
    {
        $sep = str_contains($url, '?') ? '&' : '?';
        $this->redirect($url . $sep . 'msg=' . rawurlencode($msg) . '&status_type=' . $type);
    }

    private function msgErro(\Throwable $e): string
    {
        if ($e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }
        error_log('MatriculaViradaAdminController: ' . $e->getMessage());
        return 'Não foi possível concluir. Tente de novo.';
    }
}
}
