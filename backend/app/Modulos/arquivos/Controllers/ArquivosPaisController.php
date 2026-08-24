<?php
/**
 * EducaTudo - Módulo Arquivos (Pais)
 * Somente leitura: materiais que o professor disponibilizou ao aluno.
 */

require_once __DIR__ . '/../Services/ArquivosService.php';

if (!class_exists('ArquivosPaisController')) {
class ArquivosPaisController extends BaseController
{
    private $auth;
    private $db;
    private ArquivosService $arquivosService;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->arquivosService = new ArquivosService();
        $user = $this->auth->getUser();
        if ($user && ($user['tipo'] ?? '') !== 'pai') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    public function index($id): void
    {
        $ctx = $this->contextoFilho((int) $id);
        if ($ctx === null) {
            return;
        }
        [$filhos, $filho] = $ctx;

        $turmaId = (int) ($filho['turma_id'] ?? 0);
        $alunoId = (int) $filho['id'];
        $basePath = '/pais/filhos/' . $alunoId . '/arquivos';

        $resultado = $turmaId > 0
            ? $this->arquivosService->listarParaAluno(
                $turmaId,
                $alunoId,
                [
                    'materia_id' => $_GET['materia_id'] ?? null,
                    'professor_id' => $_GET['professor_id'] ?? null,
                    'titulo' => $_GET['titulo'] ?? '',
                    'pasta_id' => array_key_exists('pasta_id', $_GET) ? $_GET['pasta_id'] : null,
                    'page' => $_GET['page'] ?? 1,
                ],
                false
            )
            : $this->resultadoVazio();

        $this->viewWithLayout('parent', 'aluno/arquivos/index', [
            'title' => 'Arquivos - EducaTudo',
            'page_title' => 'Arquivos',
            'current_page' => 'arquivos',
            'user' => $this->auth->getUser(),
            'filhos' => $filhos,
            'filho' => $filho,
            'lista' => $resultado['lista'],
            'pastas' => $resultado['pastas'],
            'pasta_atual' => $resultado['pasta_atual'],
            'filtro_pasta_id' => $resultado['filtro_pasta_id'],
            'modo_recuperacao' => false,
            'base_path' => $basePath,
            'url_ver_base' => URL . $basePath . '/ver',
            'subtituloLista' => 'Materiais disponibilizados pelo professor para '
                . (string) ($filho['nome'] ?? 'o aluno') . '.',
            'filtro_offcanvas' => true,
            'filtro_materia_id' => $resultado['filtro_materia_id'],
            'filtro_professor_id' => $resultado['filtro_professor_id'],
            'filtro_titulo' => $resultado['filtro_titulo'],
            'materias' => $resultado['materias'],
            'professores' => $resultado['professores'],
            'total' => $resultado['total'],
            'page' => $resultado['page'],
            'per_page' => $resultado['per_page'],
            'total_pages' => $resultado['total_pages'],
        ]);
    }

    public function ver($id, $pubId): void
    {
        $ctx = $this->contextoFilho((int) $id);
        if ($ctx === null) {
            return;
        }
        [$filhos, $filho] = $ctx;

        $alunoId = (int) $filho['id'];
        $turmaId = (int) ($filho['turma_id'] ?? 0);
        $pubId = (int) $pubId;
        $basePath = '/pais/filhos/' . $alunoId . '/arquivos';

        if ($pubId <= 0 || $turmaId <= 0) {
            $this->setFlashMessage('Publicação não encontrada.', 'error');
            $this->redirect($basePath);
            return;
        }

        $pub = $this->arquivosService->arquivos()->findVisivelParaAluno($pubId, $turmaId, $alunoId);
        if (!$pub) {
            $this->setFlashMessage('Publicação não encontrada.', 'error');
            $this->redirect($basePath);
            return;
        }

        $this->viewWithLayout('parent', 'aluno/arquivos/ver', [
            'title' => $pub['titulo'] . ' - Arquivos',
            'page_title' => 'Arquivos',
            'current_page' => 'arquivos',
            'user' => $this->auth->getUser(),
            'filhos' => $filhos,
            'filho' => $filho,
            'pub' => $pub,
            'anexos' => $this->arquivosService->anexos()->listByModuloArquivo($pubId),
            'videos' => $this->arquivosService->videosComEmbed($pubId),
            'modo_recuperacao' => false,
            'voltar_url' => URL . $basePath,
            'voltar_label' => '← Voltar aos arquivos',
            'url_visualizar_base' => URL . $basePath . '/visualizar',
        ]);
    }

    public function visualizarAnexo($id, $anexoId): void
    {
        $filho = $this->getFilhoById((int) $id);
        if (!$filho || !$this->moduloHabilitado()) {
            http_response_code(403);
            exit;
        }

        $anexoId = (int) $anexoId;
        $anexo = $anexoId > 0 ? $this->arquivosService->anexos()->findById($anexoId) : null;
        $turmaId = (int) ($filho['turma_id'] ?? 0);
        if (
            !$anexo
            || $turmaId <= 0
            || !$this->arquivosService->arquivos()->alunoPodeVer(
                (int) $anexo['modulo_arquivo_id'],
                $turmaId,
                (int) $filho['id']
            )
        ) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }

        $download = (!empty($_GET['download']) && $_GET['download'] === '1');
        $this->arquivosService->enviarAnexoAoNavegador($anexo, $this->config, $download);
        exit;
    }

    /**
     * @return array{0: array, 1: array}|null
     */
    private function contextoFilho(int $filhoId): ?array
    {
        $filhos = $this->getFilhos();
        $filho = $this->getFilhoById($filhoId);
        if (!$filho) {
            $this->redirect('/pais/filhos');
            return null;
        }
        if (!$this->moduloHabilitado()) {
            $this->setFlashMessage('O módulo Arquivos está desabilitado.', 'error');
            $this->redirect('/pais/dashboard');
            return null;
        }
        return [$filhos, $filho];
    }

    private function moduloHabilitado(): bool
    {
        require_once __DIR__ . '/../../../Core/LayoutHelper.php';
        return LayoutHelper::isModuleEnabled('aluno_arquivos');
    }

    private function resultadoVazio(): array
    {
        return [
            'lista' => [],
            'pastas' => [],
            'pasta_atual' => null,
            'total' => 0,
            'materias' => [],
            'professores' => [],
            'page' => 1,
            'per_page' => 15,
            'total_pages' => 1,
            'filtro_pasta_id' => null,
            'filtro_materia_id' => null,
            'filtro_professor_id' => null,
            'filtro_titulo' => '',
        ];
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
                        WHERE ar.aluno_id = a.id
                          AND ar.responsavel_id = :pai_id_rel
                          AND ar.ativo = 1
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
                        WHERE ar.aluno_id = a.id
                          AND ar.responsavel_id = :pai_id_rel
                          AND ar.ativo = 1
                    )
               )
             ORDER BY a.nome ASC",
            ['pai_id_legacy' => $paiId, 'pai_id_rel' => $paiId]
        ) ?: [];
    }

    private function redirectToCorrectDashboard($tipo): void
    {
        switch ($tipo) {
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            default:
                $this->redirect('/pais/dashboard');
        }
    }
}
}
