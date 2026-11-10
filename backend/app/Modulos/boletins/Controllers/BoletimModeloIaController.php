<?php

namespace App\Modulos\Boletins\Controllers;

use App\Core\CreditosModuleRegistry;
use App\Infra\Services\AIJobService;
use App\Modulos\Boletins\Services\BoletimModeloLeituraService;
use App\Painel\Admin\Controllers\AdminBaseController;

class BoletimModeloIaController extends AdminBaseController
{
    private const PERMISSION_KEY = 'configuracao_boletim';
    private const CREDITO_MODULO = 'boletim_ler_modelo';
    private const SESSAO_RASCUNHO_MODELO = 'boletim_modelo_ia_rascunho';

    private BoletimModeloLeituraService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new BoletimModeloLeituraService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'visualizar', false)) {
            return;
        }

        $jobId = max(0, (int) ($_GET['job_id'] ?? 0));
        $jobResumo = null;
        if ($jobId > 0) {
            $jobResumo = $this->resolverJobDoUsuario($jobId);
            if ($jobResumo !== null && $jobResumo['status'] === 'done' && !empty($jobResumo['result']['rascunho'])) {
                $_SESSION[self::SESSAO_RASCUNHO_MODELO] = $jobResumo['result']['rascunho'];
                $this->setFlashMessage('Leitura concluída. Revise o mapeamento antes de seguir para o assistente.', 'success');
                $this->redirect('/admin/boletins/importar-modelo');
                return;
            }
            if ($jobResumo !== null && $jobResumo['status'] === 'failed') {
                $this->setFlashMessage((string) ($jobResumo['erro'] ?? 'Falha ao processar o modelo.'), 'error');
                $this->redirect('/admin/boletins/importar-modelo');
                return;
            }
        }

        $rascunhoSessao = $_SESSION[self::SESSAO_RASCUNHO_MODELO] ?? null;
        if (!is_array($rascunhoSessao)) {
            $rascunhoSessao = null;
        }

        $catalogoOpcoes = ['pecas' => [], 'tipos' => [], 'materias' => [], 'eventos' => []];
        try {
            $catalogoOpcoes = $this->service->catalogoLocal();
            if ($rascunhoSessao !== null) {
                $rascunhoSessao = $this->service->aplicarAtribuicoes($rascunhoSessao, [], $catalogoOpcoes);
                $_SESSION[self::SESSAO_RASCUNHO_MODELO] = $rascunhoSessao;
            }
        } catch (\Throwable $e) {
            error_log('BoletimModeloIaController catalogo: ' . $e->getMessage());
        }

        $this->viewWithLayout('admin', 'admin/boletins/importar-modelo', [
            'title' => 'Importar Modelo de Boletim (IA)',
            'page_title' => 'Importar Modelo de Boletim (IA)',
            'user' => $this->auth->getUser(),
            'current_page' => 'boletim-layout',
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
            'job_id' => $jobId > 0 ? $jobId : null,
            'job' => $jobResumo,
            'rascunho_modelo_ia' => $rascunhoSessao,
            'catalogo_opcoes' => $catalogoOpcoes,
            'tudicoins_disponivel' => CreditosModuleRegistry::acaoIaDisponivel(self::CREDITO_MODULO),
        ]);
    }

    public function analisar(): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'alterar', false)) {
            return;
        }

        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/boletins/importar-modelo');
            return;
        }

        if (!CreditosModuleRegistry::acaoIaDisponivel(self::CREDITO_MODULO)) {
            $this->setFlashMessage('Leitura por IA indisponível. Ative TudiCoins para esta escola no Master.', 'error');
            $this->redirect('/admin/boletins/importar-modelo');
            return;
        }

        try {
            $upload = $this->service->salvarUploadTemporario((array) ($_FILES['arquivo'] ?? []));
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/boletins/importar-modelo');
            return;
        }

        $usuario = $this->auth->getUser() ?? [];
        $userId = (int) ($usuario['id'] ?? 0);
        $creditosRef = 'boletim_modelo_ia:' . bin2hex(random_bytes(10));
        $debitou = false;
        try {
            $creditos = new \App\Services\CreditosService();
            if (!$creditos->podeConsumir('escola', CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, self::CREDITO_MODULO)) {
                throw new \RuntimeException('TudiCoins insuficientes na carteira da escola.');
            }
            $creditos->consumirEscola(self::CREDITO_MODULO, $creditosRef);
            $debitou = true;

            $jobId = AIJobService::enqueue('boletim_ler_modelo', [
                'arquivo' => [
                    'path' => $upload['path'],
                    'mime' => $upload['mime'],
                    'nome_original' => $upload['nome_original'],
                    'slug' => $upload['slug'],
                ],
                'credits_ref' => $creditosRef,
                'credits_modulo' => self::CREDITO_MODULO,
            ], $userId, 'admin');
        } catch (\Throwable $e) {
            if ($debitou) {
                try {
                    (new \App\Services\CreditosService())->estornarPorReferencia(self::CREDITO_MODULO, $creditosRef);
                } catch (\Throwable $estornoErro) {
                    error_log('BoletimModeloIaController estorno: ' . $estornoErro->getMessage());
                }
            }
            if (isset($upload['path']) && is_string($upload['path']) && is_file($upload['path'])) {
                @unlink($upload['path']);
            }
            $this->setFlashMessage('Não foi possível iniciar a leitura por IA: ' . $e->getMessage(), 'error');
            $this->redirect('/admin/boletins/importar-modelo');
            return;
        }

        $this->setFlashMessage('Arquivo enviado. A IA está lendo o modelo em segundo plano.', 'info');
        $this->redirect('/admin/boletins/importar-modelo?job_id=' . (int) $jobId);
    }

    public function preparar(): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSION_KEY, 'alterar', false)) {
            return;
        }

        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/boletins/importar-modelo');
            return;
        }

        $rascunho = $_SESSION[self::SESSAO_RASCUNHO_MODELO] ?? null;
        if (!is_array($rascunho)) {
            $this->setFlashMessage('Não há leitura de modelo pronta para preparar.', 'error');
            $this->redirect('/admin/boletins/importar-modelo');
            return;
        }

        $atribuicoesRaw = (string) ($_POST['atribuicoes'] ?? '{}');
        $atribuicoes = json_decode($atribuicoesRaw, true);
        if (!is_array($atribuicoes)) {
            $atribuicoes = [];
        }

        try {
            $catalogo = $this->service->catalogoLocal();
            $rascunhoAplicado = $this->service->aplicarAtribuicoes($rascunho, $atribuicoes, $catalogo);
        } catch (\Throwable $e) {
            $this->setFlashMessage('Falha ao aplicar atribuições: ' . $e->getMessage(), 'error');
            $this->redirect('/admin/boletins/importar-modelo');
            return;
        }

        $_SESSION[self::SESSAO_RASCUNHO_MODELO] = $rascunhoAplicado;
        if ($this->service->temPendencias($rascunhoAplicado)) {
            $this->setFlashMessage('Ainda há pendências ou ambiguidades. Revise as atribuições antes de seguir.', 'error');
            $this->redirect('/admin/boletins/importar-modelo');
            return;
        }

        $rascunhoAssistente = $this->service->montarRascunhoAssistente($rascunhoAplicado);
        $_SESSION['boletim_assistente_rascunho'] = $rascunhoAssistente;
        $this->setFlashMessage('Rascunho preparado. Revise no assistente de boletim e finalize o evento.', 'success');
        $this->redirect('/admin/boletim-configuracao/assistente?continuar=1&voltar=boletim-layout');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolverJobDoUsuario(int $jobId): ?array
    {
        $job = AIJobService::getJob($jobId);
        if (!is_array($job)) {
            return null;
        }
        if ((string) ($job['job_type'] ?? '') !== 'boletim_ler_modelo') {
            return null;
        }

        $usuario = $this->auth->getUser() ?? [];
        $userId = (int) ($usuario['id'] ?? 0);
        if ($userId <= 0 || (int) ($job['user_id'] ?? 0) !== $userId) {
            return null;
        }

        $resultado = json_decode((string) ($job['result'] ?? ''), true);
        if (!is_array($resultado)) {
            $resultado = [];
        }

        return [
            'id' => (int) ($job['id'] ?? 0),
            'status' => (string) ($job['status'] ?? 'pending'),
            'erro' => (string) ($job['error_message'] ?? ''),
            'result' => $resultado,
            'created_at' => (string) ($job['created_at'] ?? ''),
            'completed_at' => (string) ($job['completed_at'] ?? ''),
        ];
    }
}

if (!class_exists('BoletimModeloIaController', false) && class_exists(__NAMESPACE__ . '\\BoletimModeloIaController', false)) {
    class_alias(__NAMESPACE__ . '\\BoletimModeloIaController', 'BoletimModeloIaController');
}
