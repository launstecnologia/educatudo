<?php

namespace App\Dominio\Escola\Controllers;



use App\Painel\Admin\Controllers\AdminBaseController;
use App\Core\AdminPermissionMatrix;
use App\Core\LayoutHelper;
use App\Services\SchoolCalendarService;

/**
 * EducaTudo - SchoolCalendarController (Calendário Letivo)
 */
if (!class_exists(__NAMESPACE__ . '\\SchoolCalendarController', false)) {
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
        $calendarios = $service->listarDoAno($ano);
        $pedidoId = (int) ($_GET['id'] ?? 0);
        $cfg = null;
        foreach ($calendarios as $cal) {
            if ((int) ($cal['id'] ?? 0) === $pedidoId) {
                $cfg = $cal;
                break;
            }
        }
        if (!$cfg) {
            $cfg = $service->getAno($ano) ?: ($calendarios[0] ?? null);
            if ($cfg && !isset($cfg['rotulo'])) {
                $cfg = $service->getPorId((int) ($cfg['id'] ?? 0)) ?? $cfg;
            }
        }
        $eventos = $cfg ? $service->eventos((int) $cfg['id']) : [];
        $status = $cfg
            ? $service->status((int) $cfg['id'], $ano, (int) $cfg['dias_meta'], (int) $cfg['carga_horaria_meta'])
            : null;
        $flash = $this->getFlashMessage();
        $visuais = $service->visuaisTipos();
        $this->viewWithLayout('admin', 'admin/calendario-letivo/index', [
            'title' => 'Calendário Letivo - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'calendario_letivo',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'ano' => $ano,
            'config' => $cfg,
            'calendarios' => $calendarios,
            'varios_pronto' => $service->variosDisponivel(),
            'cursos_series' => $service->variosDisponivel() ? $service->cursosComSeries() : [],
            'eventos' => $eventos,
            'status' => $status,
            'schema_pronto' => $service->tableExists(),
            'pode_publicar_escolar' => $this->podePublicarCalendarioEscolar($service),
            'pode_alterar_evento' => $this->podeAcaoCalendario('alterar'),
            'pode_cadastrar_tipo' => $service->tabelaTiposExiste() && $this->podeAcaoCalendario('cadastrar'),
            'pode_excluir_tipo' => $service->tabelaTiposExiste() && $this->podeAcaoCalendario('excluir'),
            'tipos' => $visuais['tipos'],
            'tipoLabels' => $visuais['labels'],
            'tipoBg' => $visuais['bg'],
            'tipoText' => $visuais['text'],
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
        $dias = max(0, (int) ($_POST['dias_meta'] ?? 200));
        $carga = max(0, (int) ($_POST['carga_horaria_meta'] ?? 800));
        $obs = trim((string) ($_POST['observacao'] ?? ''));
        if ($service->variosDisponivel()) {
            $resultado = $service->salvarCalendario(
                (int) ($_POST['calendario_id'] ?? 0),
                $ano,
                trim((string) ($_POST['nome'] ?? 'Geral')),
                $dias,
                $carga,
                $obs,
                (array) ($_POST['curso_ids'] ?? []),
                (array) ($_POST['serie_ids'] ?? [])
            );
            if (empty($resultado['ok'])) {
                $this->setFlashMessage((string) ($resultado['erro'] ?? 'Não foi possível salvar o calendário.'), 'error');
                $this->redirectCalendario($ano, (int) ($_POST['calendario_id'] ?? 0));
                return;
            }
            $this->setFlashMessage('Calendário salvo.', 'success');
            $this->redirectCalendario($ano, (int) ($resultado['id'] ?? 0));
            return;
        }
        $service->salvarAno($ano, $dias, $carga, $obs);
        $this->setFlashMessage('Calendário do ano salvo.', 'success');
        $this->redirectCalendario($ano);
    }

    public function excluirCalendario(): void
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
        (new SchoolCalendarService($this->db))->excluirCalendario((int) ($_POST['calendario_id'] ?? 0));
        $this->setFlashMessage('Calendário removido.', 'success');
        $this->redirectCalendario($ano);
    }

    public function salvarEvento(): void
    {
        $eventoId = (int) ($_POST['id'] ?? 0);
        $acao = $eventoId > 0 ? 'alterar' : 'cadastrar';
        if (!$this->enforceAdminPermissionKey('calendario_letivo', $acao, false)) {
            return;
        }
        $ano = (int) ($_POST['ano'] ?? date('Y'));
        $calendarioIdPedido = (int) ($_POST['calendario_id'] ?? 0);
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->responderEvento(
                $ano,
                $calendarioIdPedido,
                false,
                'Sessão expirada. Recarregue a página e tente novamente.',
                ['formulario' => 'Sessão expirada. Recarregue a página e tente novamente.'],
                403
            );
            return;
        }

        $datas = SchoolCalendarService::validarDatasEvento(
            (string) ($_POST['data_inicio'] ?? ''),
            (string) ($_POST['data_fim'] ?? '')
        );
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $erros = $datas['erros'];
        if ($descricao === '') {
            $erros['descricao'] = 'Informe a descrição do evento.';
        }
        if ($erros !== []) {
            $this->responderEvento($ano, $calendarioIdPedido, false, (string) reset($erros), $erros, 422);
            return;
        }

        $inicio = $datas['inicio'];
        $fim = $datas['fim'];
        try {
            $service = new SchoolCalendarService($this->db);
            $cfg = $calendarioIdPedido > 0 ? $service->getPorId($calendarioIdPedido) : null;
            if (!$cfg || (int) ($cfg['ano'] ?? 0) !== $ano) {
                $cfg = $service->getAno($ano);
            }
            if (!$cfg && $eventoId <= 0) {
                $service->salvarAno($ano, 200, 800, '');
                $cfg = $service->getAno($ano);
            }
        } catch (\Throwable $e) {
            error_log('Calendário letivo preparar ano: ' . $e->getMessage());
            $this->responderEvento(
                $ano,
                $calendarioIdPedido,
                false,
                'Não foi possível salvar agora. Os dados continuam aqui. Tente novamente.',
                ['formulario' => 'Não foi possível salvar agora. Os dados continuam aqui. Tente novamente.'],
                500
            );
            return;
        }
        if (!$cfg) {
            $this->responderEvento(
                $ano,
                0,
                false,
                'Não foi possível preparar o calendário do ano.',
                ['formulario' => 'Não foi possível preparar o calendário do ano.'],
                422
            );
            return;
        }
        $calendarioId = (int) $cfg['id'];
        $token = $eventoId > 0 ? '' : $this->tokenEnvioEvento();
        if ($token !== '' && $this->eventoJaEnviado($token)) {
            $this->responderEvento($ano, $calendarioId, true, 'Evento adicionado ao calendário letivo.', [], 200, 'success');
            return;
        }

        $tipo = (string) ($_POST['tipo'] ?? 'feriado');
        if (!$service->tipoValido($tipo)) {
            $tipo = 'feriado';
        }
        $local = trim((string) ($_POST['local_evento'] ?? ''));
        $link = trim((string) ($_POST['link_reuniao'] ?? ''));
        $publicarEscolar = !empty($_POST['publicar_calendario_escolar']) && $this->podePublicarCalendarioEscolar($service);
        $visivelPais = (isset($_POST['visivel_pais']) || $publicarEscolar) ? 1 : 0;
        $visivelAluno = isset($_POST['visivel_aluno']) ? 1 : 0;
        $visivelProfessor = isset($_POST['visivel_professor']) ? 1 : 0;
        $atualizando = $eventoId > 0;
        try {
            if ($atualizando) {
                $ok = $service->atualizarEvento(
                    $eventoId,
                    $calendarioId,
                    $inicio,
                    $fim,
                    $tipo,
                    $descricao,
                    $link,
                    $local,
                    $visivelAluno,
                    $visivelProfessor,
                    $visivelPais,
                );
                if (!$ok) {
                    $this->responderEvento(
                        $ano,
                        $calendarioId,
                        false,
                        'Não foi possível atualizar o evento.',
                        ['formulario' => 'Não foi possível atualizar o evento.'],
                        422
                    );
                    return;
                }
                $msg = 'Evento atualizado no calendário letivo.';
            } else {
                $gravou = $service->salvarEvento(
                    $calendarioId,
                    $inicio,
                    $fim,
                    $tipo,
                    $descricao,
                    $link,
                    $local,
                    $visivelAluno,
                    $visivelProfessor,
                    $visivelPais,
                );
                if (!$gravou) {
                    $this->responderEvento(
                        $ano,
                        $calendarioId,
                        false,
                        'Não foi possível salvar o evento.',
                        ['formulario' => 'Não foi possível salvar o evento.'],
                        422
                    );
                    return;
                }
                $this->lembrarEnvioEvento($token);
                $msg = 'Evento adicionado ao calendário letivo.';
            }
            $escolarId = 0;
            $tipoFlash = 'success';
            if ($publicarEscolar) {
                $user = $this->auth->getUser();
                try {
                    $escolarId = $service->publicarNoCalendarioEscolar(
                        $descricao,
                        $inicio,
                        $fim,
                        $tipo,
                        $local,
                        (int) ($user['id'] ?? 0)
                    );
                } catch (\Throwable $e) {
                    error_log('Calendário letivo publicar evento escolar: ' . $e->getMessage());
                    $escolarId = 0;
                }
                $verbo = $atualizando ? 'atualizado' : 'adicionado';
                if ($escolarId > 0) {
                    $msg = 'Evento ' . $verbo . ' no calendário letivo e publicado no calendário escolar. Os responsáveis foram notificados.';
                } else {
                    $msg = 'Evento ' . $verbo . ' no calendário letivo. Não foi possível publicar no calendário escolar.';
                    $tipoFlash = 'error';
                }
            }
            $this->responderEvento($ano, $calendarioId, true, $msg, [], 200, $tipoFlash);
        } catch (\Throwable $e) {
            error_log('Calendário letivo salvar evento: ' . $e->getMessage());
            $this->responderEvento(
                $ano,
                $calendarioId,
                false,
                'Não foi possível salvar agora. Os dados continuam aqui. Tente novamente.',
                ['formulario' => 'Não foi possível salvar agora. Os dados continuam aqui. Tente novamente.'],
                500
            );
        }
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
        $this->redirectCalendario($ano, (int) ($_POST['calendario_id'] ?? 0));
    }

    public function salvarTipo(): void
    {
        if (!$this->enforceAdminPermissionKey('calendario_letivo', 'cadastrar')) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->json(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.'], 403);
            return;
        }
        $efeito = (string) ($_POST['efeito'] ?? '');
        if (!in_array($efeito, ['neutro', 'nao_letivo', 'reposicao'], true)) {
            $efeito = !empty($_POST['nao_letivo']) ? 'nao_letivo' : 'neutro';
        }
        $result = (new SchoolCalendarService($this->db))->salvarTipo(
            (string) ($_POST['nome'] ?? ''),
            (string) ($_POST['cor'] ?? ''),
            $efeito
        );
        $this->json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function excluirTipo(): void
    {
        if (!$this->enforceAdminPermissionKey('calendario_letivo', 'excluir')) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->json(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.'], 403);
            return;
        }
        $result = (new SchoolCalendarService($this->db))->excluirTipo((string) ($_POST['slug'] ?? ''));
        $this->json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function atualizarEfeitoTipo(): void
    {
        if (!$this->enforceAdminPermissionKey('calendario_letivo', 'alterar')) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->json(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.'], 403);
            return;
        }
        $result = (new SchoolCalendarService($this->db))->atualizarEfeitoTipo(
            (string) ($_POST['slug'] ?? ''),
            (string) ($_POST['efeito'] ?? '')
        );
        $this->json($result, !empty($result['ok']) ? 200 : 422);
    }

    private function pedidoEventoJson(): bool
    {
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($xhr === 'xmlhttprequest') {
            return true;
        }

        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /**
     * @param array<string,string> $erros
     */
    private function responderEvento(
        int $ano,
        int $calendarioId,
        bool $ok,
        string $mensagem,
        array $erros = [],
        int $status = 200,
        string $tipoFlash = 'error'
    ): void {
        if ($this->pedidoEventoJson()) {
            if ($ok) {
                $this->setFlashMessage($mensagem, $tipoFlash === 'success' ? 'success' : 'error');
                $this->json([
                    'ok' => true,
                    'mensagem' => $mensagem,
                    'redirect' => $this->urlCalendario($ano, $calendarioId),
                ]);
            }
            $this->json([
                'ok' => false,
                'erros' => $erros !== [] ? $erros : ['formulario' => $mensagem],
            ], $status > 0 ? $status : 422);
        }
        $this->setFlashMessage($mensagem, $ok ? ($tipoFlash === 'success' ? 'success' : 'error') : 'error');
        $this->redirectCalendario($ano, $calendarioId);
    }

    private function tokenEnvioEvento(): string
    {
        $token = trim((string) ($_POST['envio_token'] ?? ''));
        if ($token === '' || strlen($token) > 80 || preg_match('/^[A-Za-z0-9\-]+$/', $token) !== 1) {
            return '';
        }

        return $token;
    }

    private function eventoJaEnviado(string $token): bool
    {
        $mapa = $_SESSION['calendario_evento_envio'] ?? [];

        return $token !== '' && is_array($mapa) && !empty($mapa[$token]);
    }

    private function lembrarEnvioEvento(string $token): void
    {
        if ($token === '') {
            return;
        }
        if (!isset($_SESSION['calendario_evento_envio']) || !is_array($_SESSION['calendario_evento_envio'])) {
            $_SESSION['calendario_evento_envio'] = [];
        }
        $_SESSION['calendario_evento_envio'][$token] = 1;
        if (count($_SESSION['calendario_evento_envio']) > 30) {
            $_SESSION['calendario_evento_envio'] = array_slice($_SESSION['calendario_evento_envio'], -30, null, true);
        }
    }

    private function urlCalendario(int $ano, int $id = 0): string
    {
        $url = '/admin/calendario-letivo?ano=' . $ano;
        if ($id > 0) {
            $url .= '&id=' . $id;
        }

        return $url;
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
        }
        $permissions = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $this->auth->getUser() ?? []);
        return AdminPermissionMatrix::can($permissions, 'calendario_escolar', 'cadastrar');
    }

    private function podeAcaoCalendario(string $acao): bool
    {
        if (!class_exists('AdminPermissionMatrix')) {
        }
        $permissions = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $this->auth->getUser() ?? []);
        return AdminPermissionMatrix::can($permissions, 'calendario_letivo', $acao);
    }

    private function redirectCalendario(int $ano, int $id = 0): void
    {
        $this->redirect($this->urlCalendario($ano, $id));
    }
}
}

if (!class_exists('SchoolCalendarController', false) && class_exists(__NAMESPACE__ . '\\SchoolCalendarController', false)) {
    class_alias(__NAMESPACE__ . '\\SchoolCalendarController', 'SchoolCalendarController');
}
