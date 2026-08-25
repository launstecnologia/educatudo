<?php

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../Services/MatriculaCampanhaService.php';
require_once __DIR__ . '/../Services/MatriculaProcessoService.php';
require_once __DIR__ . '/../Services/MatriculaVagaService.php';
require_once __DIR__ . '/../Models/MatriculaProcesso.php';

use App\Modulos\Matricula\Models\MatriculaProcesso;
use App\Modulos\Matricula\Services\MatriculaCampanhaService;
use App\Modulos\Matricula\Services\MatriculaProcessoService;
use App\Modulos\Matricula\Services\MatriculaVagaService;

if (!class_exists('MatriculaPaisController')) {
class MatriculaPaisController extends BaseController
{
    private $auth;
    private $db;
    private MatriculaCampanhaService $campanhaService;
    private MatriculaProcessoService $processoService;
    private MatriculaProcesso $model;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->campanhaService = new MatriculaCampanhaService($this->db);
        $this->processoService = new MatriculaProcessoService($this->db);
        $this->model = $this->processoService->getModel();
        $user = $this->auth->getUser();
        if ($user && ($user['tipo'] ?? '') !== 'pai') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    public function rematricula(int $filhoId): void
    {
        $filho = $this->getFilhoById($filhoId);
        if (!$filho) {
            $this->redirect('/pais/filhos');
            return;
        }
        $campanha = $this->campanhaService->campanhaAbertaParaAluno($filhoId);
        $processo = is_array($campanha['processo'] ?? null) ? $campanha['processo'] : null;
        $produtos = $processo ? $this->model->listarProdutos((int) $processo['id']) : [];
        $noPrazo = $campanha ? $this->campanhaService->campanhaNoPrazo($campanha) : false;

        $this->viewWithLayout('parent', 'parents/matricula/rematricula', [
            'title' => 'Rematrícula — EducaTudo',
            'current_page' => 'rematricula',
            'user' => $this->auth->getUser(),
            'filhos' => $this->getFilhos(),
            'filho' => $filho,
            'campanha' => $campanha,
            'processo' => $processo,
            'produtos' => $produtos,
            'no_prazo' => $noPrazo,
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function confirmar(int $filhoId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/pais/filhos/' . $filhoId . '/rematricula?msg=' . rawurlencode('Token inválido.') . '&status_type=error');
            return;
        }
        $filho = $this->getFilhoById($filhoId);
        if (!$filho) {
            $this->redirect('/pais/filhos');
            return;
        }
        $campanha = $this->campanhaService->campanhaAbertaParaAluno($filhoId);
        $processo = is_array($campanha['processo'] ?? null) ? $campanha['processo'] : null;
        if (!$campanha || !$processo) {
            $this->redirect('/pais/filhos/' . $filhoId . '/rematricula?msg=' . rawurlencode('Não há rematrícula aberta.') . '&status_type=error');
            return;
        }
        if (!$this->campanhaService->campanhaNoPrazo($campanha)) {
            $this->redirect('/pais/filhos/' . $filhoId . '/rematricula?msg=' . rawurlencode('Fora do prazo. Procure a secretaria.') . '&status_type=error');
            return;
        }

        $upd = [
            'aluno_nome_mae' => trim((string) ($_POST['aluno_nome_mae'] ?? '')) ?: null,
            'aluno_nome_pai' => trim((string) ($_POST['aluno_nome_pai'] ?? '')) ?: null,
            'aluno_cor_raca' => trim((string) ($_POST['aluno_cor_raca'] ?? '')) ?: null,
            'aluno_nacionalidade' => trim((string) ($_POST['aluno_nacionalidade'] ?? '')) ?: null,
            'aluno_codigo_inep' => trim((string) ($_POST['aluno_codigo_inep'] ?? '')) ?: null,
        ];
        $this->model->update((int) $processo['id'], $upd);
        $processo = $this->model->findById((int) $processo['id']);
        if (!empty($campanha['exige_censo']) && trim((string) ($processo['aluno_nome_mae'] ?? '')) === '') {
            $this->redirect('/pais/filhos/' . $filhoId . '/rematricula?msg=' . rawurlencode('Informe o nome da mãe (Censo).') . '&status_type=error');
            return;
        }

        if (empty($processo['contrato_token'])) {
            $this->processoService->generateContratoToken((int) $processo['id']);
            $processo = $this->model->findById((int) $processo['id']);
        }

        $status = (string) ($processo['status'] ?? '');
        if ($status === 'lista_espera') {
            $this->redirect('/pais/filhos/' . $filhoId . '/rematricula?msg=' . rawurlencode('Seu filho está na lista de espera. A secretaria avisará quando houver vaga.') . '&status_type=error');
            return;
        }
        if ($status === 'rascunho') {
            $turmaId = (int) ($processo['turma_id'] ?? 0);
            if ($turmaId > 0) {
                $vaga = new MatriculaVagaService($this->db);
                $ownTx = !$this->db->inTransaction();
                if ($ownTx) {
                    $this->db->beginTransaction();
                }
                try {
                    $destino = $vaga->decidirDestino($turmaId);
                    if (($destino['destino'] ?? '') === 'lista_espera') {
                        $vaga->colocarNaFila((int) $processo['id'], $turmaId, $this->auth->getUser());
                        if ($ownTx) {
                            $this->db->commit();
                        }
                        $this->redirect('/pais/filhos/' . $filhoId . '/rematricula?msg=' . rawurlencode('A turma está lotada. Você entrou na lista de espera.') . '&status_type=error');
                        return;
                    }
                    $this->model->transition((int) $processo['id'], 'aguardando_assinatura', $this->auth->getUser(), 'familia_confirmou');
                    if ($ownTx) {
                        $this->db->commit();
                    }
                } catch (\Throwable $e) {
                    if ($ownTx && $this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    error_log('MatriculaPaisController confirmar: ' . $e->getMessage());
                    $this->redirect('/pais/filhos/' . $filhoId . '/rematricula?msg=' . rawurlencode('Não foi possível confirmar agora. Tente de novo.') . '&status_type=error');
                    return;
                }
            } else {
                $this->model->transition((int) $processo['id'], 'aguardando_assinatura', $this->auth->getUser(), 'familia_confirmou');
            }
            $processo = $this->model->findById((int) $processo['id']);
        }

        $token = (string) ($processo['contrato_token'] ?? '');
        $this->redirect(URL . '/matricula/contrato/' . $token);
    }

    private function getPaiId(): int
    {
        return (int) ($this->auth->getUser()['id'] ?? 0);
    }

    private function getFilhoById(int $filhoId): ?array
    {
        $paiId = $this->getPaiId();
        if ($paiId <= 0 || $filhoId <= 0) {
            return null;
        }
        return $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome, t.serie
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.id = :filho_id
               AND a.ativo = 1
               AND (
                    a.responsavel_id = :pai_id_legacy
                    OR EXISTS (
                        SELECT 1 FROM alunos_responsaveis ar
                        WHERE ar.aluno_id = a.id AND ar.responsavel_id = :pai_id_rel AND ar.ativo = 1
                    )
               )",
            [
                'filho_id' => $filhoId,
                'pai_id_legacy' => $paiId,
                'pai_id_rel' => $paiId,
            ]
        ) ?: null;
    }

    private function getFilhos(): array
    {
        $paiId = $this->getPaiId();
        if ($paiId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT a.*, t.nome as turma_nome, t.serie
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1
               AND (
                    a.responsavel_id = :pai_id_legacy
                    OR EXISTS (
                        SELECT 1 FROM alunos_responsaveis ar
                        WHERE ar.aluno_id = a.id AND ar.responsavel_id = :pai_id_rel AND ar.ativo = 1
                    )
               )
             ORDER BY a.nome ASC",
            ['pai_id_legacy' => $paiId, 'pai_id_rel' => $paiId]
        ) ?: [];
    }
}
}
