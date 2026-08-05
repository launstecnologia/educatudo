<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Services/JornadasAlunoConsultaService.php';

/**
 * Endpoints JSON (somente leitura) para MCP de jornadas dos alunos.
 */
class JornadasAlunoMcpController extends BaseController
{
    private AuthManager $auth;
    private JornadasAlunoConsultaService $consulta;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$this->usuarioPodeConsultar($user)) {
            $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
        }
        $this->consulta = new JornadasAlunoConsultaService();
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

            case 'listar_materias_jornadas':
            case 'listar_materias':
                $this->json([
                    'success' => true,
                    'data' => $this->consulta->listarMaterias(),
                ]);
                break;

            case 'listar_jornadas_aluno':
                $resultado = $this->consulta->listarJornadasAluno($this->lerFiltros());
                $this->json([
                    'success' => !empty($resultado['ok']) && empty($resultado['error']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], (!empty($resultado['ok']) && empty($resultado['error'])) ? 200 : 400);
                break;

            case 'detalhar_jornada_aluno':
                $resultado = $this->consulta->detalharJornadaAluno(
                    (int) ($_POST['aluno_id'] ?? 0),
                    (int) ($_POST['jornada_id'] ?? 0),
                    ['somente_erros' => $_POST['somente_erros'] ?? false]
                );
                $this->json([
                    'success' => !empty($resultado['ok']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], !empty($resultado['ok']) ? 200 : 400);
                break;

            case 'resumo_jornadas_aluno':
                $resultado = $this->consulta->resumoJornadasAluno($this->lerFiltros());
                $this->json([
                    'success' => !empty($resultado['ok']) && empty($resultado['error']),
                    'error' => $resultado['error'] ?? null,
                    'data' => $resultado,
                ], (!empty($resultado['ok']) && empty($resultado['error'])) ? 200 : 400);
                break;

            default:
                $this->json(['success' => false, 'error' => 'Tool desconhecida.'], 400);
        }
    }

    /** @return array<string,mixed> */
    private function lerFiltros(): array
    {
        return [
            'aluno_id' => (int) ($_POST['aluno_id'] ?? 0),
            'aluno_nome' => trim((string) ($_POST['aluno_nome'] ?? $_POST['nome'] ?? '')),
            'turma_nome' => trim((string) ($_POST['turma_nome'] ?? $_POST['turma'] ?? '')),
            'materia_id' => (int) ($_POST['materia_id'] ?? 0),
            'materia_nome' => trim((string) ($_POST['materia_nome'] ?? '')),
            'bimestre' => (int) ($_POST['bimestre'] ?? 0),
            'data_inicio' => trim((string) ($_POST['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_POST['data_fim'] ?? '')),
            'status_aluno' => trim((string) ($_POST['status_aluno'] ?? '')),
            'limite' => (int) ($_POST['limite'] ?? 80),
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
