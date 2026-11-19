<?php

namespace App\Modulos\EducaSync\Controllers;

use App\Modulos\EducaSync\Models\Alocacao;
use App\Modulos\EducaSync\Models\Execucao;
use App\Modulos\EducaSync\Services\EducaSyncAssistenteService;
use App\Modulos\EducaSync\Services\EducaSyncCenarioService;
use App\Modulos\EducaSync\Services\EducaSyncOtimizacaoService;
use App\Modulos\EducaSync\Services\EducaSyncPublicacaoService;
use App\Painel\Admin\Controllers\AdminBaseController;

/**
 * EducaSync — cenários de grade horária (admin).
 * Gera rascunhos pelo solver e publica em grade_horaria.
 */
class EducaSyncAdminController extends AdminBaseController
{
    private const PERMISSAO = 'educasync';
    private const BASE = '/admin/educasync';

    private EducaSyncCenarioService $cenarioService;

    public function __construct()
    {
        parent::__construct();
        $this->cenarioService = new EducaSyncCenarioService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'visualizar', false)) {
            return;
        }

        $cenarios = $this->cenarioService->cenarios();
        $schemaPronto = $cenarios->schemaPronto();
        $flash = $this->flashParaView();
        if (!$schemaPronto && $flash['flash_message'] === '') {
            $flash = ['flash_status' => 'error', 'flash_message' => 'Execute a migration do EducaSync (painel Master) antes de usar o módulo.'];
        }

        $this->viewWithLayout('admin', 'admin/educasync/index', array_merge([
            'title' => 'EducaSync - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'educasync',
            'cenarios' => $schemaPronto ? $cenarios->listar() : [],
            'schema_pronto' => $schemaPronto,
            'csrf_token' => $this->generateCsrfToken(),
        ], $flash));
    }

    public function novo(): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'cadastrar', false)) {
            return;
        }

        $this->viewWithLayout('admin', 'admin/educasync/form', array_merge([
            'title' => 'Novo cenário - EducaSync',
            'user' => $this->auth->getUser(),
            'current_page' => 'educasync',
            'ano_letivo' => (int) date('Y'),
            'csrf_token' => $this->generateCsrfToken(),
        ], $this->flashParaView()));
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'cadastrar', false)) {
            return;
        }
        if (!$this->csrfOuRedirect(self::BASE . '/novo')) {
            return;
        }

        $resultado = $this->cenarioService->criar($_POST, $this->usuarioId());
        if (empty($resultado['success'])) {
            $this->setFlashMessage($resultado['error'] ?? 'Não foi possível criar o cenário.', 'error');
            $this->redirect(self::BASE . '/novo');
            return;
        }

        $this->setFlashMessage('Cenário criado. Cadastre os horários e as demandas.', 'success');
        $this->redirect(self::BASE . '/' . (int) $resultado['id']);
    }

    public function show($id): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'visualizar', false)) {
            return;
        }
        $cenario = $this->cenarioOuRedirect((int) $id);
        if (!$cenario) {
            return;
        }

        $cenarioId = (int) $cenario['id'];
        $cenarios = $this->cenarioService->cenarios();
        $execucoes = (new Execucao())->listarPorCenario($cenarioId, 5);
        $bloqueios = $this->bloqueiosProfessores($cenarioId);
        $sugestaoSlots = $this->cenarioService->sugerirParametrosSlots($cenarioId);
        $anosLetivos = $this->listarAnosLetivosParaSelect((int) $cenario['ano_letivo']);
        $turmas = $cenarios->turmasAtivas((int) $cenario['ano_letivo']);
        $demandas = $this->cenarioService->demandas()->listarPorCenario($cenarioId);
        $turmaIds = array_map(static fn ($t) => (int) ($t['id'] ?? 0), $turmas);
        $professoresDemanda = [];
        foreach ($demandas as $d) {
            $pid = (int) ($d['professor_id'] ?? 0);
            if ($pid > 0) {
                $professoresDemanda[] = $pid;
            }
        }
        // Só quem está nas turmas do ano do cenário (ou já aparece nas demandas).
        $professores = $cenarios->professoresDasTurmas($turmaIds, $professoresDemanda);
        $slots = $this->cenarioService->slots()->listarPorCenario($cenarioId);

        $this->viewWithLayout('admin', 'admin/educasync/show', array_merge([
            'title' => 'EducaSync - ' . (string) $cenario['nome'],
            'user' => $this->auth->getUser(),
            'current_page' => 'educasync',
            'cenario' => $cenario,
            'slots' => $slots,
            'demandas' => $demandas,
            'versoes' => $this->cenarioService->versoes()->listarPorCenario($cenarioId),
            'execucoes' => $execucoes,
            'execucao_ativa' => (new Execucao())->ativaDoCenario($cenarioId),
            'turmas' => $turmas,
            'materias' => $cenarios->materiasAtivas(),
            'professores' => $professores,
            'salas' => $cenarios->salasAtivas(),
            'anos_letivos' => $anosLetivos,
            'sugestao_slots' => $sugestaoSlots,
            'tudicoins_disponivel' => EducaSyncOtimizacaoService::disponivel(),
            'bloqueios_professores' => $bloqueios['por_professor'],
            'resumo_bloqueios' => $bloqueios['resumo'],
            'professores_sobrecarregados' => $this->professoresSobrecarregados(
                $demandas,
                $bloqueios['por_professor'],
                count($slots)
            ),
            'assistente_disponivel' => EducaSyncAssistenteService::disponivel(),
            'csrf_token' => $this->generateCsrfToken(),
        ], $this->flashParaView()));
    }

    /**
     * Anos cadastrados + o ano atual do cenário (mesmo se ainda não estiver em ano_letivo).
     *
     * @return list<int>
     */
    private function listarAnosLetivosParaSelect(int $anoAtual): array
    {
        $anos = [];
        try {
            $rows = $this->db->fetchAll('SELECT ano FROM ano_letivo ORDER BY ano DESC') ?: [];
            foreach ($rows as $row) {
                $ano = (int) ($row['ano'] ?? 0);
                if ($ano >= 2000 && $ano <= 2100) {
                    $anos[$ano] = $ano;
                }
            }
        } catch (\Throwable $e) {
            // schema antigo / tabela ausente
        }
        if ($anoAtual >= 2000 && $anoAtual <= 2100) {
            $anos[$anoAtual] = $anoAtual;
        }
        if ($anos === []) {
            $anos[(int) date('Y')] = (int) date('Y');
        }
        rsort($anos, SORT_NUMERIC);
        return array_values($anos);
    }

    public function gerarSlots($id): void
    {
        $this->acaoCenario((int) $id, 'cadastrar', function (int $cenarioId): array {
            $resultado = $this->cenarioService->gerarSlots($cenarioId, $_POST);
            $resultado['mensagem'] = ($resultado['total'] ?? 0) . ' horários salvos.';
            return $resultado;
        });
    }

    public function excluirSlot($id, $slotId): void
    {
        $this->acaoCenario((int) $id, 'excluir', function (int $cenarioId) use ($slotId): array {
            $resultado = $this->cenarioService->excluirSlot($cenarioId, (int) $slotId);
            $resultado['mensagem'] = 'Horário removido.';
            return $resultado;
        });
    }

    public function salvarDemanda($id): void
    {
        $this->acaoCenario((int) $id, 'cadastrar', function (int $cenarioId): array {
            $resultado = $this->cenarioService->salvarDemanda($cenarioId, $_POST);
            $resultado['mensagem'] = 'Demanda salva.';
            return $resultado;
        });
    }

    public function excluirDemanda($id, $demandaId): void
    {
        $this->acaoCenario((int) $id, 'excluir', function (int $cenarioId) use ($demandaId): array {
            $resultado = $this->cenarioService->excluirDemanda($cenarioId, (int) $demandaId);
            $resultado['mensagem'] = 'Demanda removida.';
            return $resultado;
        });
    }

    public function excluirTodasDemandas($id): void
    {
        $this->acaoCenario((int) $id, 'excluir', function (int $cenarioId): array {
            $resultado = $this->cenarioService->excluirTodasDemandas($cenarioId);
            if (!empty($resultado['success'])) {
                $resultado['mensagem'] = (int) ($resultado['removidas'] ?? 0) . ' demanda(s) removida(s).';
            }
            return $resultado;
        });
    }

    public function atualizarAnoLetivo($id): void
    {
        $this->acaoCenario((int) $id, 'cadastrar', function (int $cenarioId): array {
            $ano = (int) ($_POST['ano_letivo'] ?? 0);
            $resultado = $this->cenarioService->atualizarAnoLetivo($cenarioId, $ano);
            if (!empty($resultado['success'])) {
                $mensagem = 'Ano letivo atualizado para ' . (int) $resultado['ano_letivo'] . '.';
                if ((int) ($resultado['demandas_removidas'] ?? 0) > 0) {
                    $mensagem .= ' ' . (int) $resultado['demandas_removidas'] . ' demanda(s) do ano anterior foram removidas.';
                }
                $resultado['mensagem'] = $mensagem;
            }
            return $resultado;
        });
    }

    public function importarMatriz($id): void
    {
        $this->acaoCenario((int) $id, 'cadastrar', function (int $cenarioId): array {
            $resultado = $this->cenarioService->importarDemandasMatriz($cenarioId);
            if (!empty($resultado['success'])) {
                $mensagem = 'Matriz importada: ' . (int) $resultado['criadas'] . ' demanda(s) criada(s), '
                    . (int) $resultado['atualizadas'] . ' atualizada(s).';
                if ((int) $resultado['sem_professor'] > 0) {
                    $mensagem .= ' ' . (int) $resultado['sem_professor'] . ' sem professor (defina antes de gerar).';
                }
                if ((int) $resultado['sem_matriz'] > 0) {
                    $mensagem .= ' ' . (int) $resultado['sem_matriz'] . ' turma(s) sem matriz curricular foram ignoradas.';
                }
                $resultado['mensagem'] = $mensagem;
            }
            return $resultado;
        });
    }

    public function salvarDisponibilidadeProfessor($id): void
    {
        $this->acaoCenario((int) $id, 'cadastrar', function (int $cenarioId): array {
            $resultado = $this->cenarioService->salvarDisponibilidadeProfessor($cenarioId, $_POST);
            $resultado['mensagem'] = ($resultado['total'] ?? 0) . ' horário(s) bloqueado(s) para o professor.';
            return $resultado;
        });
    }

    public function limparDisponibilidadeProfessor($id): void
    {
        $this->acaoCenario((int) $id, 'excluir', function (int $cenarioId): array {
            $resultado = $this->cenarioService->limparDisponibilidadeProfessor($cenarioId, $_POST);
            $resultado['mensagem'] = 'Bloqueios do professor removidos.';
            return $resultado;
        });
    }

    public function gerar($id): void
    {
        $this->acaoCenario((int) $id, 'cadastrar', function (int $cenarioId): array {
            $resultado = (new EducaSyncOtimizacaoService())->enfileirar($cenarioId, $this->usuarioId());
            $resultado['mensagem'] = 'Otimização iniciada. Acompanhe o andamento abaixo.';
            return $resultado;
        });
    }

    /**
     * Reotimização parcial: nova versão mantendo fixas as aulas dos dias marcados da versão base.
     * Consome TudiCoins como "Gerar grade".
     */
    public function reotimizar($id, $versaoId): void
    {
        $cenarioId = (int) $id;
        $versaoId = (int) $versaoId;
        $voltarVersao = self::BASE . '/' . $cenarioId . '/versoes/' . $versaoId;
        $voltarCenario = self::BASE . '/' . $cenarioId;

        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'cadastrar', false)) {
            return;
        }
        if (!$this->csrfOuRedirect($voltarVersao)) {
            return;
        }
        if (!$this->cenarioOuRedirect($cenarioId)) {
            return;
        }
        if (($_POST['confirmar'] ?? '') !== '1') {
            $this->setFlashMessage('Confirme a reotimização (consome TudiCoins da escola).', 'error');
            $this->redirect($voltarVersao);
            return;
        }

        $resultado = (new EducaSyncOtimizacaoService())->enfileirar($cenarioId, $this->usuarioId(), [
            'versao_base_id' => $versaoId,
            'fixar_dias' => $_POST['fixar_dias'] ?? [],
            'fixar_todas' => ($_POST['fixar_todas'] ?? '') === '1',
        ]);
        if (empty($resultado['success'])) {
            $this->setFlashMessage($resultado['error'] ?? 'Não foi possível reotimizar.', 'error');
            $this->redirect($voltarVersao);
            return;
        }

        $this->setFlashMessage(
            'Reotimização iniciada mantendo ' . (int) ($resultado['aulas_fixadas'] ?? 0)
            . ' aula(s) fixa(s). Acompanhe o andamento no cenário.',
            'success'
        );
        $this->redirect($voltarCenario);
    }

    /**
     * Polling da execução (JSON). Só responde execução do cenário informado.
     */
    public function status($id, $execucaoId): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'visualizar', true)) {
            return;
        }

        $status = (new EducaSyncOtimizacaoService())->status((int) $id, (int) $execucaoId);
        if ($status === null) {
            $this->json(['error' => 'Execução não encontrada.'], 404);
            return;
        }
        $this->json($status);
    }

    public function versao($id, $versaoId): void
    {
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'visualizar', false)) {
            return;
        }
        $cenario = $this->cenarioOuRedirect((int) $id);
        if (!$cenario) {
            return;
        }

        $versao = $this->cenarioService->versoes()->findById((int) $versaoId);
        if (!$versao || (int) $versao['cenario_id'] !== (int) $cenario['id']) {
            $this->setFlashMessage('Versão não encontrada.', 'error');
            $this->redirect(self::BASE . '/' . (int) $cenario['id']);
            return;
        }

        $simulacao = (new EducaSyncPublicacaoService())->simular((int) $cenario['id'], (int) $versao['id']);
        $metricas = json_decode((string) ($versao['metricas_json'] ?? ''), true);

        $this->viewWithLayout('admin', 'admin/educasync/versao', array_merge([
            'title' => 'Versão #' . (int) $versao['id'] . ' - EducaSync',
            'user' => $this->auth->getUser(),
            'current_page' => 'educasync',
            'cenario' => $cenario,
            'versao' => $versao,
            'metricas' => is_array($metricas) ? $metricas : [],
            'alocacoes' => (new Alocacao())->listarPorVersao((int) $versao['id']),
            'simulacao' => $simulacao,
            'tudicoins_disponivel' => EducaSyncOtimizacaoService::disponivel(),
            'execucao_ativa' => (new Execucao())->ativaDoCenario((int) $cenario['id']),
            'csrf_token' => $this->generateCsrfToken(),
        ], $this->flashParaView()));
    }

    public function publicar($id, $versaoId): void
    {
        $voltar = self::BASE . '/' . (int) $id . '/versoes/' . (int) $versaoId;
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, 'alterar', false)) {
            return;
        }
        if (!$this->enforceAdminPermissionKey('grade_horaria', 'alterar', false)) {
            return;
        }
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        if (($_POST['confirmar'] ?? '') !== '1') {
            $this->setFlashMessage('Confirme a substituição da grade horária das turmas para publicar.', 'error');
            $this->redirect($voltar);
            return;
        }

        $resultado = (new EducaSyncPublicacaoService())->publicar((int) $id, (int) $versaoId, $this->usuarioId());
        if (empty($resultado['success'])) {
            $this->setFlashMessage($resultado['error'] ?? 'Não foi possível publicar.', 'error');
            $this->redirect($voltar);
            return;
        }

        $diff = (array) ($resultado['diff'] ?? []);
        $this->setFlashMessage(
            'Grade publicada em ' . (int) ($diff['turmas'] ?? 0) . ' turma(s): '
            . (int) ($diff['inseridas'] ?? 0) . ' aulas novas, '
            . (int) ($diff['removidas'] ?? 0) . ' removidas, '
            . (int) ($diff['mantidas'] ?? 0) . ' mantidas.',
            'success'
        );
        $this->redirect($voltar);
    }

    /**
     * POST padrão sobre um cenário: permissão, CSRF, cenário existente, ação e redirect.
     *
     * @param callable(int):array{success?:bool, error?:string, mensagem?:string} $acao
     */
    private function acaoCenario(int $cenarioId, string $permissao, callable $acao): void
    {
        $voltar = self::BASE . '/' . $cenarioId;
        if (!$this->enforceAdminPermissionKey(self::PERMISSAO, $permissao, false)) {
            return;
        }
        if (!$this->csrfOuRedirect($voltar)) {
            return;
        }
        if (!$this->cenarioOuRedirect($cenarioId)) {
            return;
        }

        $resultado = $acao($cenarioId);
        $ok = !empty($resultado['success']);
        $this->setFlashMessage(
            $ok ? (string) ($resultado['mensagem'] ?? 'Feito.') : (string) ($resultado['error'] ?? 'Não foi possível concluir.'),
            $ok ? 'success' : 'error'
        );
        $this->redirect($voltar);
    }

    private function cenarioOuRedirect(int $id): ?array
    {
        $cenario = $this->cenarioService->cenarios()->findById($id);
        if ($cenario) {
            return $cenario;
        }
        $this->setFlashMessage('Cenário não encontrado.', 'error');
        $this->redirect(self::BASE);
        return null;
    }

    private function csrfOuRedirect(string $voltar): bool
    {
        if ($this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            return true;
        }
        $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
        $this->redirect($voltar);
        return false;
    }

    /**
     * Bloqueios de professores do cenário para a view.
     *
     * @return array{por_professor:array<int,list<int>>, resumo:list<array<string,mixed>>} slots bloqueados por professor_id
     */
    private function bloqueiosProfessores(int $cenarioId): array
    {
        $disponibilidades = $this->cenarioService->disponibilidades();
        $porProfessor = [];
        foreach ($disponibilidades->listarPorCenario($cenarioId) as $d) {
            if ((string) $d['tipo'] === 'professor' && (int) $d['livre'] === 0) {
                $porProfessor[(int) $d['ref_id']][] = (int) $d['slot_id'];
            }
        }
        return ['por_professor' => $porProfessor, 'resumo' => $disponibilidades->resumoBloqueiosProfessores($cenarioId)];
    }

    /**
     * Professores com mais aulas na demanda do que horários livres (após bloqueios).
     * A grade fica inviável até redistribuir carga ou aliviar disponibilidade.
     *
     * @param list<array<string,mixed>> $demandas
     * @param array<int,list<int>> $bloqueiosPorProfessor
     * @return list<array{professor_id:int,nome:string,aulas:int,bloqueados:int,livres:int,faltam:int}>
     */
    private function professoresSobrecarregados(array $demandas, array $bloqueiosPorProfessor, int $totalSlots): array
    {
        if ($totalSlots <= 0) {
            return [];
        }

        $aulas = [];
        $nomes = [];
        foreach ($demandas as $d) {
            $pid = (int) ($d['professor_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $aulas[$pid] = ($aulas[$pid] ?? 0) + (int) ($d['aulas_semana'] ?? 0);
            $nomes[$pid] = (string) ($d['professor_nome'] ?? ('Professor ' . $pid));
        }

        $out = [];
        foreach ($aulas as $pid => $qtd) {
            $bloqueados = count($bloqueiosPorProfessor[$pid] ?? []);
            $livres = max(0, $totalSlots - $bloqueados);
            if ($qtd <= $livres) {
                continue;
            }
            $out[] = [
                'professor_id' => $pid,
                'nome' => $nomes[$pid],
                'aulas' => $qtd,
                'bloqueados' => $bloqueados,
                'livres' => $livres,
                'faltam' => $qtd - $livres,
            ];
        }
        usort($out, static fn(array $a, array $b): int => $b['faltam'] <=> $a['faltam']);
        return $out;
    }

    private function usuarioId(): int
    {
        return (int) (($this->auth->getUser() ?? [])['id'] ?? 0);
    }

    /**
     * @return array{flash_status:string, flash_message:string}
     */
    private function flashParaView(): array
    {
        $flash = $this->getFlashMessage();
        $mensagem = (string) ($flash['message'] ?? '');
        return [
            'flash_status' => $mensagem === '' ? '' : (($flash['type'] ?? '') === 'success' ? 'success' : 'error'),
            'flash_message' => $mensagem,
        ];
    }
}
