<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Services/ProvasAlunoConsultaService.php';

/**
 * Endpoints JSON (somente leitura) para MCP / consultas de provas dos alunos.
 */
class ProvasAlunoMcpController extends BaseController
{
    private AuthManager $auth;
    private ProvasAlunoConsultaService $consulta;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$this->usuarioPodeConsultar($user)) {
            $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
        }
        $this->consulta = new ProvasAlunoConsultaService();
    }

    public function ferramenta(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }

        $nome = trim((string) ($_POST['tool'] ?? ''));

        switch ($nome) {
            case 'buscar_alunos':
                $termo = trim((string) ($_POST['termo'] ?? $_POST['nome'] ?? ''));
                $turma = trim((string) ($_POST['turma'] ?? $_POST['turma_nome'] ?? ''));
                $this->json([
                    'success' => true,
                    'data' => $this->consulta->buscarAlunos($termo, 20, $turma !== '' ? $turma : null),
                ]);
                break;

            case 'listar_materias':
                $this->json([
                    'success' => true,
                    'data' => $this->consulta->listarMaterias(),
                ]);
                break;

            case 'listar_tipos_avaliacao':
                $this->json([
                    'success' => true,
                    'data' => $this->consulta->listarTiposAvaliacao(),
                ]);
                break;

            case 'listar_provas_aluno':
                $filtros = $this->lerFiltrosProvas();
                $resultado = $this->consulta->listarProvasAluno($filtros);
                $this->json([
                    'success' => !empty($resultado['ok']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], !empty($resultado['ok']) ? 200 : 400);
                break;

            case 'detalhar_prova_aluno':
                $alunoId = (int) ($_POST['aluno_id'] ?? 0);
                $provaId = (int) ($_POST['prova_id'] ?? 0);
                $resultado = $this->consulta->detalharProvaAluno($alunoId, $provaId, [
                    'somente_erros' => $_POST['somente_erros'] ?? false,
                    'materia_nome' => trim((string) ($_POST['materia_nome'] ?? $_POST['materia'] ?? '')),
                    'titulo' => trim((string) ($_POST['titulo'] ?? $_POST['titulo_prova'] ?? '')),
                ]);
                $this->json([
                    'success' => !empty($resultado['ok']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], !empty($resultado['ok']) ? 200 : 400);
                break;

            case 'resumo_provas_aluno':
                $filtros = $this->lerFiltrosProvas();
                $resultado = $this->consulta->resumoProvasAluno($filtros);
                $this->json([
                    'success' => !empty($resultado['ok']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], !empty($resultado['ok']) ? 200 : 400);
                break;

            default:
                $this->json(['success' => false, 'error' => 'Tool desconhecida.'], 400);
        }
    }

    /** @return array<string,mixed> */
    private function lerFiltrosProvas(): array
    {
        return [
            'aluno_id' => (int) ($_POST['aluno_id'] ?? 0),
            'aluno_nome' => trim((string) ($_POST['aluno_nome'] ?? $_POST['nome'] ?? '')),
            'materia_id' => (int) ($_POST['materia_id'] ?? 0),
            'materia_nome' => trim((string) ($_POST['materia_nome'] ?? '')),
            'tipo_avaliacao_id' => (int) ($_POST['tipo_avaliacao_id'] ?? 0),
            'tipo_avaliacao_nome' => trim((string) ($_POST['tipo_avaliacao_nome'] ?? $_POST['tipo'] ?? '')),
            'turma_nome' => trim((string) ($_POST['turma_nome'] ?? $_POST['turma'] ?? '')),
            'bimestre' => (int) ($_POST['bimestre'] ?? 0),
            'data_inicio' => trim((string) ($_POST['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_POST['data_fim'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'finalizado')),
            'limite' => (int) ($_POST['limite'] ?? 50),
        ];
    }

    private function usuarioPodeConsultar(?array $user): bool
    {
        // Sessão de admin_escola grava tipo=admin; o perfil real está em perfil_admin.
        if (!$user || ($user['tipo'] ?? '') !== 'admin') {
            return false;
        }
        return in_array((string) ($user['perfil_admin'] ?? ''), ['dev', 'diretor', 'coordenador'], true);
    }
}
