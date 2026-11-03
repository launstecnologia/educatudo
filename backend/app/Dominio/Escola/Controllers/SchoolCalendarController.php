<?php

namespace App\Dominio\Escola\Controllers;



use App\Painel\Admin\Controllers\AdminBaseController;
use App\Core\AdminPermissionMatrix;
use App\Core\LayoutHelper;
use App\Painel\Admin\Services\TrilhaCadastroService;
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
        $gradesProvas = [];
        if ($eventos !== [] && class_exists(\App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService::class)) {
            $gradesProvas = (new \App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService())->gradesDosCompromissos(
                $eventos,
                array_map('intval', (array) ($cfg['curso_ids'] ?? [])),
                array_map('intval', (array) ($cfg['serie_ids'] ?? []))
            );
        }
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
            'grades_provas' => $gradesProvas,
            'status' => $status,
            'schema_pronto' => $service->tableExists(),
            'aviso_vinculo' => $service->vinculoAvisoDisponivel(),
            'turmas_aviso' => $this->podePublicarCalendarioEscolar($service) ? $service->turmasParaAviso() : [],
            'alunos_aviso' => $this->podePublicarCalendarioEscolar($service) ? $service->alunosParaAviso() : [],
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

    public function logs(): void
    {
        $user = $this->auth->getUser();
        if (!$user || ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->json(['error' => 'Sem permissão.'], 403);
            return;
        }

        try {
            $logs = (new TrilhaCadastroService($this->db))->listar('calendario');
        } catch (\Throwable $e) {
            $this->json(['error' => 'Não foi possível ler os logs do calendário letivo.'], 500);
            return;
        }

        $this->json(['success' => true, 'logs' => $logs]);
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
        $user = $this->auth->getUser();
        (new SchoolCalendarService($this->db))->excluirCalendario(
            (int) ($_POST['calendario_id'] ?? 0),
            (int) ($user['id'] ?? 0)
        );
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
        $podePublicar = $this->podePublicarCalendarioEscolar($service);
        $avisoPedido = $service->interpretarAvisoFamilia($_POST, $podePublicar);
        if (!$avisoPedido['ok']) {
            $this->responderEvento($ano, $calendarioId, false, (string) reset($avisoPedido['erros']), $avisoPedido['erros'], 422);
            return;
        }
        $avisoChecado = $avisoPedido['aviso'];
        if (
            !empty($avisoChecado['publicar'])
            && empty($avisoChecado['dia_inteiro'])
            && (string) ($avisoChecado['hora_fim'] ?? '') !== ''
            && $inicio === $fim
            && (string) $avisoChecado['hora_fim'] < (string) $avisoChecado['hora_inicio']
        ) {
            $this->responderEvento(
                $ano,
                $calendarioId,
                false,
                'O horário de fim deve ser igual ou posterior ao de início.',
                ['hora_inicio' => 'O horário de fim deve ser igual ou posterior ao de início.'],
                422
            );
            return;
        }
        $publicarEscolar = !empty($avisoPedido['aviso']['publicar']);
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
                $eventoId = $service->salvarEvento(
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
                if ($eventoId <= 0) {
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
            $tipoFlash = 'success';
            $user = $this->auth->getUser();
            $aviso = $avisoPedido['aviso'];
            $aviso['titulo'] = $descricao;
            $aviso['inicio'] = $inicio;
            $aviso['fim'] = $fim;
            $aviso['tipo'] = $tipo;
            $aviso['local'] = $local;
            $aviso['link'] = $link;
            $aviso['autor_id'] = (int) ($user['id'] ?? 0);
            $aviso['atualizando'] = $atualizando;
            if ($publicarEscolar || ($atualizando && $podePublicar)) {
                try {
                    $resultado = $service->sincronizarAvisoFamilia($eventoId, $aviso);
                } catch (\Throwable $e) {
                    error_log('Calendário letivo sincronizar aviso da família: ' . $e->getMessage());
                    $resultado = ['ok' => false, 'id' => 0, 'acao' => 'erro'];
                }
                $msg = $this->mensagemAvisoFamilia($msg, $resultado, $publicarEscolar);
                if (($resultado['acao'] ?? '') === 'erro' || (isset($resultado['notificou']) && $resultado['notificou'] === false)) {
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
        $user = $this->auth->getUser();
        (new SchoolCalendarService($this->db))->excluirEvento(
            (int) ($_POST['id'] ?? 0),
            (int) ($user['id'] ?? 0)
        );
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

    /**
     * @param array{ok?:bool,id?:int,acao?:string} $resultado
     */
    private function mensagemAvisoFamilia(string $base, array $resultado, bool $publicar): string
    {
        $acao = (string) ($resultado['acao'] ?? '');
        $avisou = !array_key_exists('notificou', $resultado) || !empty($resultado['notificou']);
        if ($acao === 'criado') {
            return $avisou
                ? 'Evento salvo no calendário letivo e aviso enviado à família.'
                : 'Evento salvo e publicado para a família. Não foi possível enviar a notificação.';
        }
        if ($acao === 'atualizado') {
            return $avisou
                ? 'Evento salvo. O aviso da família foi atualizado e os responsáveis notificados.'
                : 'Evento salvo e o aviso da família foi atualizado. Não foi possível enviar a notificação.';
        }
        if ($acao === 'inalterado') {
            return 'Evento salvo. O aviso da família permanece publicado.';
        }
        if ($acao === 'cancelado') {
            return $avisou
                ? 'Evento salvo. O aviso da família foi cancelado e os responsáveis notificados.'
                : 'Evento salvo e o aviso da família foi cancelado. Não foi possível enviar a notificação.';
        }
        if ($acao === 'sem_vinculo') {
            return 'Evento salvo. Para sincronizar o aviso da família, rode a migration 2026_10_31_calendario_aviso_familia.sql no painel Master.';
        }
        if ($acao === 'erro') {
            return $publicar
                ? 'Evento salvo no calendário letivo. Não foi possível avisar a família.'
                : 'Evento salvo no calendário letivo. Não foi possível cancelar o aviso da família.';
        }
        return $base;
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
