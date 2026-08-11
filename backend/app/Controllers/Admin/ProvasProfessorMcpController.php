<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Services/ProvasProfessorConsultaService.php';

/**
 * Endpoints JSON (somente leitura) para MCP / consultas de provas do professor.
 */
class ProvasProfessorMcpController extends BaseController
{
    private AuthManager $auth;
    private ProvasProfessorConsultaService $consulta;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$this->usuarioPodeConsultar($user)) {
            $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
        }
        $this->consulta = new ProvasProfessorConsultaService();
    }

    public function ferramenta(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }

        $nome = trim((string) ($_POST['tool'] ?? ''));

        switch ($nome) {
            case 'buscar_professores':
                $termo = trim((string) ($_POST['termo'] ?? $_POST['nome'] ?? ''));
                $this->json([
                    'success' => true,
                    'data' => $this->consulta->buscarProfessores($termo, 20),
                ]);
                break;

            case 'listar_turmas_professor':
                $resultado = $this->consulta->resolverProfessor($this->lerFiltrosProfessor());
                if (empty($resultado['ok'])) {
                    $this->json([
                        'success' => false,
                        'error' => $resultado['error'] ?? $resultado['aviso'] ?? 'Professor inválido.',
                        'data' => $resultado,
                    ], 400);
                }
                $this->json([
                    'success' => true,
                    'data' => [
                        'professor' => $resultado['professor'],
                        'turmas' => $this->consulta->listarTurmasProfessor((int) $resultado['professor']['id']),
                    ],
                ]);
                break;

            case 'listar_provas_professor':
                $resultado = $this->consulta->listarProvasProfessor($this->lerFiltrosProfessor());
                $this->json([
                    'success' => !empty($resultado['ok']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], !empty($resultado['ok']) ? 200 : 400);
                break;

            case 'resumo_provas_professor':
                $resultado = $this->consulta->resumoProvasProfessor($this->lerFiltrosProfessor());
                $this->json([
                    'success' => !empty($resultado['ok']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], !empty($resultado['ok']) ? 200 : 400);
                break;

            case 'detalhar_prova_professor':
                $resultado = $this->consulta->detalharProvaProfessor(
                    (int) ($_POST['professor_id'] ?? 0),
                    (int) ($_POST['prova_id'] ?? 0)
                );
                $this->json([
                    'success' => !empty($resultado['ok']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], !empty($resultado['ok']) ? 200 : 400);
                break;

            case 'ranking_erros_prova_professor':
                $resultado = $this->consulta->rankingErrosProva(
                    (int) ($_POST['professor_id'] ?? 0),
                    (int) ($_POST['prova_id'] ?? 0),
                    (int) ($_POST['limite'] ?? 15)
                );
                $this->json([
                    'success' => !empty($resultado['ok']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], !empty($resultado['ok']) ? 200 : 400);
                break;

            case 'saude_turmas_professor':
                $resultado = $this->consulta->saudeTurmasProfessor($this->lerFiltrosProfessor());
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
    private function lerFiltrosProfessor(): array
    {
        return [
            'professor_id' => (int) ($_POST['professor_id'] ?? 0),
            'professor_nome' => trim((string) ($_POST['professor_nome'] ?? $_POST['nome'] ?? '')),
            'turma_id' => (int) ($_POST['turma_id'] ?? 0),
            'turma_nome' => trim((string) ($_POST['turma_nome'] ?? $_POST['turma'] ?? '')),
            'materia_nome' => trim((string) ($_POST['materia_nome'] ?? $_POST['materia'] ?? '')),
            'data_inicio' => trim((string) ($_POST['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_POST['data_fim'] ?? '')),
            'ano_letivo_id' => (int) ($_POST['ano_letivo_id'] ?? 0),
            'nivel' => trim((string) ($_POST['nivel'] ?? '')),
            'limite' => (int) ($_POST['limite'] ?? 40),
        ];
    }

    private function usuarioPodeConsultar(?array $user): bool
    {
        if (!$user || ($user['tipo'] ?? '') !== 'admin') {
            return false;
        }
        return in_array((string) ($user['perfil_admin'] ?? ''), ['dev', 'diretor', 'coordenador'], true);
    }
}
