<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Services/AssistenteConsultaAmpliadaService.php';

/**
 * Endpoints JSON (somente leitura) para MCP: turma, bloco, jornadas do professor,
 * boletim e faltas.
 */
class AssistenteConsultaMcpController extends BaseController
{
    private AuthManager $auth;
    private AssistenteConsultaAmpliadaService $consulta;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$this->usuarioPodeConsultar($user)) {
            $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
        }
        $this->consulta = new AssistenteConsultaAmpliadaService();
    }

    public function ferramenta(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }

        $nome = trim((string) ($_POST['tool'] ?? ''));
        $filtros = $this->lerFiltros();

        switch ($nome) {
            case 'saude_turma':
                $resultado = $this->consulta->saudeTurma($filtros);
                break;
            case 'resumo_provas_turma':
                $resultado = $this->consulta->resumoProvasTurma($filtros);
                break;
            case 'buscar_blocos':
                $resultado = $this->consulta->buscarBlocos($filtros);
                break;
            case 'resultados_bloco':
                $resultado = $this->consulta->resultadosBloco($filtros);
                break;
            case 'resumo_jornadas_professor':
                $resultado = $this->consulta->resumoJornadasProfessor($filtros);
                break;
            case 'boletim_aluno':
                $resultado = $this->consulta->boletimAluno($filtros);
                break;
            case 'faltas_aluno':
                $resultado = $this->consulta->faltasAluno($filtros);
                break;
            default:
                $this->json(['success' => false, 'error' => 'Tool desconhecida.'], 400);
                return;
        }

        $ok = !empty($resultado['ok']);
        $temCandidatos = !empty($resultado['candidatos']) && is_array($resultado['candidatos']);
        // Ambíguo (vários candidatos): HTTP 200 para o MCP/cliente não perder a lista.
        $httpOk = $ok || $temCandidatos;
        $this->json([
            'success' => $httpOk,
            'error' => $ok
                ? null
                : ($resultado['error'] ?? $resultado['aviso'] ?? 'Falha na consulta.'),
            'data' => $resultado,
        ], $httpOk ? 200 : 400);
    }

    /** @return array<string,mixed> */
    private function lerFiltros(): array
    {
        return [
            'turma_id' => (int) ($_POST['turma_id'] ?? 0),
            'turma_nome' => trim((string) ($_POST['turma_nome'] ?? $_POST['turma'] ?? '')),
            'ano_letivo_id' => (int) ($_POST['ano_letivo_id'] ?? 0),
            'ano_letivo' => (int) ($_POST['ano_letivo'] ?? $_POST['jr_ano_letivo'] ?? 0),
            'nivel' => trim((string) ($_POST['nivel'] ?? '')),
            'data_inicio' => trim((string) ($_POST['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_POST['data_fim'] ?? '')),
            'titulo' => trim((string) ($_POST['titulo'] ?? $_POST['termo'] ?? $_POST['bloco_titulo'] ?? '')),
            'bloco_id' => (int) ($_POST['bloco_id'] ?? 0),
            'limite' => (int) ($_POST['limite'] ?? 15),
            'professor_id' => (int) ($_POST['professor_id'] ?? 0),
            'professor_nome' => trim((string) ($_POST['professor_nome'] ?? $_POST['nome'] ?? '')),
            'somente_atencao' => !empty($_POST['somente_atencao']),
            'aluno_id' => (int) ($_POST['aluno_id'] ?? 0),
            'aluno_nome' => trim((string) ($_POST['aluno_nome'] ?? '')),
            'exibir_em' => trim((string) ($_POST['exibir_em'] ?? '')),
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
