<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Services/SaudeAcademicaService.php';
require_once __DIR__ . '/../../Services/SaudeAprendizagemService.php';
require_once __DIR__ . '/../../Services/AlunoTurmaResolver.php';

use App\Services\SaudeAcademicaService;
use App\Services\SaudeAprendizagemService;

class SaudeAcademicaController extends BaseController
{
    private $auth;
    private $db;
    private $service;
    private $aprendizagemService;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->service = new SaudeAcademicaService();
        $this->aprendizagemService = new SaudeAprendizagemService();

        $user = $this->auth->getUser();
        if (!$this->canAccess($user)) {
            $this->redirect(URL . '/admin');
            exit;
        }
    }

    private function canAccess(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['tipo'] ?? '') === 'admin') {
            return true;
        }
        if (!class_exists('AdminSecretariaAccess')) {
            require_once __DIR__ . '/../../Core/AdminSecretariaAccess.php';
        }
        if (($user['tipo'] ?? '') === 'admin_escola'
            && in_array((string) ($user['perfil_admin'] ?? ''), AdminSecretariaAccess::perfisAdminEscolaGestaoPedagogica(), true)) {
            return true;
        }

        return false;
    }

    public function index(): void
    {
        $user = $this->auth->getUser();
        $anoLetivoId = (int) ($_GET['ano_letivo_id'] ?? 0);
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $tipo = trim((string) ($_GET['tipo'] ?? ''));
        $aba = trim((string) ($_GET['aba'] ?? 'cadastro'));
        $aba = $aba === 'aprendizagem' ? 'aprendizagem' : 'cadastro';
        $nivel = trim((string) ($_GET['nivel'] ?? ''));
        $executar = !empty($_GET['executar']);

        $anosLetivo = $this->service->listarAnosLetivo();
        if ($anoLetivoId <= 0) {
            $anoLetivoId = $this->service->resolverAnoLetivoPadrao();
        }

        $turmas = [];
        try {
            $turmas = $this->db->fetchAll(
                'SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC'
            ) ?: [];
        } catch (\Exception $e) {
            $turmas = [];
        }

        if ($aba === 'aprendizagem') {
            $nivelFiltro = in_array($nivel, SaudeAprendizagemService::NIVEIS, true) ? $nivel : null;
            $analise = [
                'linhas' => [],
                'kpis' => [],
                'fontes' => [],
                'regras' => $this->aprendizagemService->regras(),
            ];
            if ($executar && $anoLetivoId > 0) {
                $analise = $this->aprendizagemService->analisar($anoLetivoId, $turmaId, $nivelFiltro);
            }
            $rotulosNiveis = [];
            foreach (SaudeAprendizagemService::NIVEIS as $nivelKey) {
                $rotulosNiveis[$nivelKey] = $this->aprendizagemService->rotuloNivel($nivelKey);
            }

            $this->viewWithLayout('admin', 'admin/saude-academica/aprendizagem', [
                'title' => 'Saúde da Aprendizagem - EducaTudo',
                'page_title' => 'Saúde da Aprendizagem',
                'current_page' => 'saude_academica',
                'user' => $user,
                'anos_letivo' => $anosLetivo,
                'turmas' => $turmas,
                'filtros' => [
                    'aba' => 'aprendizagem',
                    'ano_letivo_id' => $anoLetivoId,
                    'turma_id' => $turmaId,
                    'nivel' => $nivelFiltro ?? '',
                    'executar' => $executar,
                ],
                'linhas' => $analise['linhas'],
                'kpis' => $analise['kpis'],
                'fontes' => $analise['fontes'],
                'regras' => $analise['regras'],
                'niveis' => $rotulosNiveis,
            ]);
            return;
        }

        $kpis = [];
        $linhas = [];
        if ($executar && $anoLetivoId > 0) {
            $tipoFiltro = ($tipo !== '' && in_array($tipo, SaudeAcademicaService::TIPOS_ALERTA, true)) ? $tipo : null;
            $linhas = $this->service->buscarAlertas($anoLetivoId, $tipoFiltro, $turmaId);
            $kpis = $this->service->calcularKpis($anoLetivoId);
        }

        $tiposAlerta = [];
        foreach (SaudeAcademicaService::TIPOS_ALERTA as $t) {
            $tiposAlerta[$t] = $this->service->rotuloTipoAlerta($t);
        }

        $resolver = new \App\Services\AlunoTurmaResolver();

        $this->viewWithLayout('admin', 'admin/saude-academica/index', [
            'title' => 'Saúde Acadêmica - EducaTudo',
            'page_title' => 'Saúde Acadêmica',
            'current_page' => 'saude_academica',
            'user' => $user,
            'anos_letivo' => $anosLetivo,
            'turmas' => $turmas,
            'filtros' => [
                'ano_letivo_id' => $anoLetivoId,
                'turma_id' => $turmaId,
                'tipo' => $tipo,
                'executar' => $executar,
            ],
            'kpis' => $kpis,
            'linhas' => $linhas,
            'tipos_alerta' => $tiposAlerta,
            'schema_matricula' => $resolver->supportsMatricula(),
            'schema_chamada' => $resolver->supportsListaChamada(),
        ]);
    }
}
