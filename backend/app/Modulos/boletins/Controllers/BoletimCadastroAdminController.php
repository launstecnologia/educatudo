<?php
/**
 * Admin — cadastro de boletins (oficial / extra).
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/BoletimCadastroService.php';
require_once __DIR__ . '/../../../Models/System/BoletimConfig.php';
require_once __DIR__ . '/../../../Models/Education/ComponenteCurricular.php';
require_once __DIR__ . '/../../../Helpers/AdminPasswordHelper.php';

use App\Modulos\Boletins\Services\BoletimCadastroService;

if (!class_exists('BoletimCadastroAdminController')) {
class BoletimCadastroAdminController extends AdminBaseController
{
    /** @var BoletimCadastroService */
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new BoletimCadastroService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'visualizar', false)) {
            return;
        }
        $flash = $this->getFlashMessage();
        $cfg = new BoletimConfig();
        $cfg->ensureSchema();
        $itens = $this->service->model()->listar();
        $qtdNotasPorBoletim = $cfg->contarEventosNotasPorBoletim();
        $eventosPorBoletim = [];
        foreach ($qtdNotasPorBoletim as $boletimId => $qtd) {
            if ((int) $qtd <= 0) {
                continue;
            }
            $lista = $cfg->listarEventosNotasDoBoletim((int) $boletimId);
            $eventosPorBoletim[(int) $boletimId] = is_array($lista) ? $lista : [];
        }
        $nomesRegra = [];
        foreach ($this->service->listarRegrasAcademicas() as $ra) {
            $nomesRegra[(int) ($ra['id'] ?? 0)] = (string) ($ra['nome'] ?? '');
        }
        foreach ($itens as &$item) {
            $eventosDoItem = $eventosPorBoletim[(int) $item['id']] ?? [];
            $item['eventos_notas'] = $eventosDoItem;
            $item['eventos_notas_qtd'] = count($eventosDoItem);
            $item['evento_notas_id'] = (int) ($eventosDoItem[0]['id'] ?? 0);
            $rid = (int) ($item['regra_academica_id'] ?? 0);
            $item['regra_academica_nome'] = $rid > 0 ? ($nomesRegra[$rid] ?? '') : '';
            $item['criterios'] = $this->service->criteriosDoBoletim($item);
        }
        unset($item);

        $usuario = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/boletins/index', [
            'title' => 'Modelo de Boletim — EducaTudo',
            'page_title' => 'Painel Administrativo',
            'user' => $usuario,
            'pode_dev' => is_array($usuario) && (string) ($usuario['perfil_admin'] ?? '') === 'dev',
            'current_page' => 'boletins',
            'itens' => $itens,
            'schema_pronto' => $this->service->model()->tabelasProntas(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function novo(): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'cadastrar', false)) {
            return;
        }
        $this->renderFormulario(null);
    }

    public function editar($id): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'alterar', false)) {
            return;
        }
        $item = $this->service->model()->findById((int) $id);
        if ($item === null) {
            $this->setFlashMessage('Boletim não encontrado.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        $this->renderFormulario($item);
    }

    public function salvar(): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'cadastrar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/boletins/novo');
            return;
        }
        $result = $this->service->salvar($_POST, null);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar.', 'error');
            $this->redirect('/admin/boletins/novo');
            return;
        }
        $this->setFlashMessage('Boletim cadastrado. No Evento de Notas, escolha este boletim como destino.', 'success');
        $this->redirect('/admin/boletins');
    }

    public function atualizar($id): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'alterar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/boletins/' . (int) $id . '/editar');
            return;
        }
        $result = $this->service->salvar($_POST, (int) $id);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível atualizar.', 'error');
            $this->redirect('/admin/boletins/' . (int) $id . '/editar');
            return;
        }
        $this->setFlashMessage('Boletim atualizado.', 'success');
        $this->redirect('/admin/boletins');
    }

    public function excluir($id): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'excluir', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        $senha = (string) ($_POST['senha'] ?? '');
        $user = $this->auth->getUser();
        if (trim($senha) === '') {
            $this->setFlashMessage('Digite sua senha para confirmar a exclusão.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        if (!is_array($user) || !AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->setFlashMessage('Senha incorreta.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        $result = $this->service->excluir((int) $id);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível excluir.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        $this->setFlashMessage('Modelo ocultado da lista. O cadastro, as avaliações e as notas já geradas foram mantidos.', 'success');
        $this->redirect('/admin/boletins');
    }

    public function gerarAvaliacoes($id): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'cadastrar', false)) {
            return;
        }
        require_once __DIR__ . '/../Services/GeradorAvaliacoesAnualService.php';
        $id = (int) $id;
        $gerador = new \App\Modulos\Boletins\Services\GeradorAvaliacoesAnualService();
        $prev = $gerador->previsualizar($id, (int) ($_GET['ano_letivo'] ?? 0));
        if (empty($prev['ok'])) {
            $this->setFlashMessage($prev['error'] ?? 'Modelo não encontrado.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        $eventos = $gerador->listarEventosModelo($id, (int) ($prev['ano'] ?? 0));
        $modelosPorBimestre = [];
        foreach (($prev['periodos'] ?? []) as $periodoPrev) {
            if (!is_array($periodoPrev)) {
                continue;
            }
            $bimPrev = (int) ($periodoPrev['bimestre'] ?? 0);
            if ($bimPrev <= 0) {
                continue;
            }
            $modelosPorBimestre[$bimPrev] = $gerador->modeloPadraoParaBimestre($eventos, $bimPrev);
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/boletins/gerar-avaliacoes', [
            'title' => 'Gerar avaliações do ano — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'boletins',
            'boletim' => $prev['boletim'],
            'periodos' => $prev['periodos'] ?? [],
            'eventos_modelo' => $eventos,
            'modelos_por_bimestre' => $modelosPorBimestre,
            'ano_letivo' => (int) ($prev['ano'] ?? date('Y')),
            'usou_calendario' => !empty($prev['usou_calendario']),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function gerarAvaliacoesExecutar($id): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'cadastrar', false)) {
            return;
        }
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/boletins/' . $id . '/gerar-avaliacoes');
            return;
        }
        require_once __DIR__ . '/../Services/GeradorAvaliacoesAnualService.php';
        $gerador = new \App\Modulos\Boletins\Services\GeradorAvaliacoesAnualService();
        $modelosPorBimestre = [];
        $rawModelos = $_POST['modelo_por_bimestre'] ?? [];
        if (is_array($rawModelos)) {
            foreach ($rawModelos as $bimRaw => $regraRaw) {
                $bim = (int) $bimRaw;
                $regraId = (int) $regraRaw;
                if ($bim > 0 && $regraId > 0) {
                    $modelosPorBimestre[$bim] = $regraId;
                }
            }
        }
        $bimestresSelecionados = [];
        $rawBims = $_POST['bimestres'] ?? [];
        if (is_array($rawBims)) {
            foreach ($rawBims as $bimRaw) {
                $bim = (int) $bimRaw;
                if ($bim > 0) {
                    $bimestresSelecionados[$bim] = $bim;
                }
            }
        }
        $bimestresSelecionados = array_values($bimestresSelecionados);
        $ano = (int) ($_POST['ano_letivo'] ?? 0);
        $result = $gerador->gerar($id, $modelosPorBimestre, $ano, $bimestresSelecionados);
        if (empty($result['success'])) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível gerar os eventos.', 'error');
            $this->redirect('/admin/boletins/' . $id . '/gerar-avaliacoes');
            return;
        }
        $nCriados = count($result['criados'] ?? []);
        $nSubst = count($result['substituidos'] ?? []);
        $nIgn = count($result['ignorados'] ?? []);
        $nPulados = count($result['pulados'] ?? []);
        $partes = [];
        if ($nCriados > 0) {
            $partes[] = $nCriados . ' avaliação(ões) criada(s)';
        }
        if ($nSubst > 0) {
            $partes[] = $nSubst . ' substituída(s)';
        }
        $msg = $partes !== []
            ? (implode(', ', $partes) . '.')
            : 'Nenhum evento novo foi criado.';
        if ($nIgn > 0 && ($nCriados > 0 || $nSubst > 0)) {
            $msg .= ' ' . $nIgn . ' bimestre(s) já cadastrado(s) foram mantidos.';
        }
        if ($nPulados > 0 && ($nCriados > 0 || $nSubst > 0)) {
            $msg .= ' ' . $nPulados . ' bimestre(s) ficaram de fora desta geração.';
        }
        $this->setFlashMessage($msg, 'success');
        $this->redirect('/admin/boletins');
    }

    public function simularBoletim($id): void
    {
        $this->renderGeracaoBoletins((int) $id, true);
    }

    public function gerarBoletinsPagina($id): void
    {
        $abrirSimular = isset($_GET['simular']) && in_array(strtolower(trim((string) $_GET['simular'])), ['1', 'true', 'sim', 'yes'], true);
        $this->renderGeracaoBoletins((int) $id, $abrirSimular);
    }

    public function gerarDocumento($id): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'alterar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        $sync = $this->service->sincronizarDocumento((int) $id);
        if (empty($sync['success'])) {
            $this->setFlashMessage($sync['error'] ?? 'Não foi possível montar o documento.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        $regraId = (int) ($sync['regra_id'] ?? 0);
        if ($regraId <= 0) {
            $this->setFlashMessage('Cadastre um evento de notas apontando para este boletim e gere as notas primeiro.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }
        $_SESSION['boletim_flash'] = 'Documento atualizado. Use Gerar no Evento de Notas para preencher as médias; o boletim acompanha automaticamente.';
        $_SESSION['boletim_flash_type'] = 'success';
        $this->redirect('/admin/boletim');
    }

    private function exigirDevModeloBoletim(): bool
    {
        $user = $this->auth->getUser();
        if (is_array($user) && (string) ($user['perfil_admin'] ?? '') === 'dev') {
            return true;
        }
        $this->setFlashMessage('Você não tem permissão. Contate o Administrador.', 'error');
        $this->redirect('/admin/boletins');

        return false;
    }

    private function renderGeracaoBoletins(int $id, bool $abrirSimular): void
    {
        if (!$this->exigirDevModeloBoletim()) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('configuracao_boletim', 'visualizar', false)) {
            return;
        }
        $item = $this->service->model()->findById($id);
        if ($item === null) {
            $this->setFlashMessage('Boletim não encontrado.', 'error');
            $this->redirect('/admin/boletins');
            return;
        }

        $cfg = new BoletimConfig();
        $cfg->ensureSchema();
        $eventos = $cfg->listarEventosNotasDoBoletim($id);
        $regraId = isset($_GET['regra_id']) ? (int) $_GET['regra_id'] : 0;
        if ($regraId <= 0 && $eventos !== []) {
            $regraId = $cfg->primeiroEventoNotasSemGeracao($eventos);
        }

        $regra = $regraId > 0 ? $cfg->getRuleById($regraId) : null;
        if ($regra && (int) ($regra['boletim_id'] ?? 0) !== $id) {
            $regra = null;
            $regraId = 0;
        }

        $ano = (int) ($regra['ano_letivo'] ?? ($item['ano_letivo'] ?? date('Y')));
        if ($ano < 2000) {
            $ano = (int) date('Y');
        }
        $dataInicio = $this->dataYmd((string) ($_GET['data_inicio'] ?? ($regra['default_data_inicio'] ?? '')));
        $dataFim = $this->dataYmd((string) ($_GET['data_fim'] ?? ($regra['default_data_fim'] ?? '')));
        if ($dataInicio === null) {
            $dataInicio = sprintf('%04d-01-01', $ano);
        }
        if ($dataFim === null) {
            $dataFim = sprintf('%04d-12-31', $ano);
        }
        if ($dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }
        $periodoRef = 'RANGE:' . $dataInicio . ':' . $dataFim;

        $alunos = [];
        $turmas = [];
        $simulacao = null;
        $selectedAlunoId = isset($_GET['aluno_id']) ? (int) $_GET['aluno_id'] : 0;
        $geracaoEmAndamento = false;
        if (is_array($regra)) {
            $alunos = $this->alunosVinculadosRegra($cfg, $regra);
            $turmas = $cfg->getAvailableClasses(1000);
            $geracaoEmAndamento = $cfg->temGeracaoEmAndamento($regraId);
            if ($selectedAlunoId > 0) {
                $permitido = false;
                foreach ($alunos as $alunoRow) {
                    if ((int) ($alunoRow['id'] ?? 0) === $selectedAlunoId) {
                        $permitido = true;
                        break;
                    }
                }
                if ($permitido) {
                    require_once __DIR__ . '/../../../Controllers/Admin/BoletimConfigController.php';
                    $motor = new BoletimConfigController(true);
                    // Igual ao Evento de Notas: Demonstrativo com mãe + filhas (ex.: Língua Portuguesa).
                    $simulacao = $motor->simularRegraAluno(
                        $regra,
                        $selectedAlunoId,
                        $periodoRef,
                        $dataInicio,
                        $dataFim,
                        [],
                        false,
                        true
                    );
                    try {
                        $simBoletim = $motor->simularRegraAluno(
                            $regra,
                            $selectedAlunoId,
                            $periodoRef,
                            $dataInicio,
                            $dataFim,
                            [],
                            true
                        );
                        $matrizBoletim = is_array($simBoletim) ? ($simBoletim['matriz_materias'] ?? null) : null;
                        if (is_array($simulacao) && is_array($matrizBoletim)) {
                            $simulacao['matriz_materias_boletim'] = $matrizBoletim;
                        }
                    } catch (Throwable $e) {
                        error_log('Gerar boletins simulação agrupada aluno #' . $selectedAlunoId . ': ' . $e->getMessage());
                    }
                    if (is_array($simulacao)) {
                        $simulacao = $motor->montarMatrizDemonstrativoComGrupoHierarquico($simulacao, $regra);
                    }
                } else {
                    $selectedAlunoId = 0;
                }
            }
        }

        $flash = $this->getFlashMessage();
        if (!empty($_SESSION['boletim_flash'])) {
            $tipoSessao = strtolower(trim((string) ($_SESSION['boletim_flash_type'] ?? 'success')));
            $flash = [
                'message' => (string) $_SESSION['boletim_flash'],
                'type' => $tipoSessao === 'error' ? 'error' : 'success',
            ];
            unset($_SESSION['boletim_flash'], $_SESSION['boletim_flash_type']);
        }

        $this->viewWithLayout('admin', 'admin/boletins/gerar-boletins', [
            'title' => 'Notas / Boletins — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'boletins',
            'boletim' => $item,
            'eventos' => $eventos,
            'regra' => $regra,
            'selected_regra_id' => $regraId,
            'alunos' => array_slice($alunos, 0, 400),
            'turmas' => $turmas,
            'periodo_ref' => $periodoRef,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'simulacao' => $simulacao,
            'selected_aluno_id' => $selectedAlunoId,
            'geracao_em_andamento' => $geracaoEmAndamento,
            'abrir_simular' => $abrirSimular && $selectedAlunoId <= 0,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => ($flash['type'] ?? '') === 'success' ? 'success' : (($flash['message'] ?? '') ? 'error' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
        ]);
    }

    /**
     * @param array<string,mixed> $regra
     * @return list<array<string,mixed>>
     */
    private function alunosVinculadosRegra(BoletimConfig $cfg, array $regra): array
    {
        $turmasIds = $this->idsJsonCampo($regra['turmas_ids'] ?? null);
        if ($turmasIds !== []) {
            return $cfg->getStudentsListByClasses($turmasIds, 5000);
        }
        $seriesIds = $this->idsJsonCampo($regra['series_ids'] ?? null);
        if ($seriesIds === []) {
            return $cfg->getStudentsList(5000);
        }
        return $cfg->getStudentsListBySeries($seriesIds, 5000);
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function idsJsonCampo($raw): array
    {
        $decoded = [];
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parsed = json_decode(trim($raw), true);
            if (is_array($parsed)) {
                $decoded = $parsed;
            }
        }
        $ids = [];
        foreach ($decoded as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function dataYmd(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return null;
            }
            return $s;
        }
        return null;
    }

    /**
     * @param array<string,mixed>|null $item
     */
    private function renderFormulario(?array $item): void
    {
        $cfg = new BoletimConfig();
        $cfg->ensureSchema();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/boletins/form', [
            'title' => ($item ? 'Editar' : 'Novo') . ' boletim — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'boletins',
            'item' => $item,
            'materias' => $this->materiasOficiaisDoBoletim(),
            'series' => $cfg->getAvailableSeries(300),
            'turmas' => $cfg->getAvailableClasses(800),
            'anos_letivos' => $this->anosLetivos(),
            'regras_academicas' => $this->service->listarRegrasAcademicas(
                null,
                (int) ($item['regra_academica_id'] ?? 0)
            ),
            'criterios' => $item ? $this->service->criteriosDoBoletim($item) : $this->service->criteriosDoBoletim([]),
            'schema_pronto' => $this->service->model()->tabelasProntas(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    /**
     * Áreas oficiais (pai + componentes sem desdobramento). Filhos não entram.
     *
     * @return list<array{id:int,nome:string,eh_rotulo:bool}>
     */
    private function materiasOficiaisDoBoletim(): array
    {
        $out = [];
        try {
            foreach ((new ComponenteCurricular())->getOficiaisParaMatriz(true) as $c) {
                $id = (int) ($c['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $out[] = [
                    'id' => $id,
                    'nome' => (string) ($c['nome'] ?? ''),
                    'eh_rotulo' => !empty($c['eh_rotulo']),
                ];
            }
        } catch (Throwable $e) {
            error_log('materiasOficiaisDoBoletim: ' . $e->getMessage());
        }
        usort($out, static fn ($a, $b) => strcmp((string) $a['nome'], (string) $b['nome']));
        return $out;
    }

    /**
     * @return list<int>
     */
    private function anosLetivos(): array
    {
        $ano = (int) date('Y');
        $out = [];
        for ($a = $ano + 1; $a >= $ano - 4; $a--) {
            $out[] = $a;
        }
        return $out;
    }
}
}
