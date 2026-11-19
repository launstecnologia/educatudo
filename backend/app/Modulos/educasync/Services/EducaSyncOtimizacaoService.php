<?php

namespace App\Modulos\EducaSync\Services;

use App\Core\CreditosModuleRegistry;
use App\Core\Database;
use App\Infra\Services\AIJobService;
use App\Modulos\EducaSync\Models\Alocacao;
use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Models\Execucao;
use App\Modulos\EducaSync\Models\Versao;

/**
 * EducaSync — enfileira a otimização (AIJobService) e persiste o resultado do solver.
 *
 * Fluxo: enfileirar() debita TudiCoins da escola, cria a execução e o job;
 * processarJob() roda no worker, chama o solver e grava versão + alocações.
 * O estorno em falha definitiva é do AIJobService (credits_ref/credits_modulo).
 */
class EducaSyncOtimizacaoService
{
    public const JOB_TIPO = 'educasync_otimizar';
    public const CREDITO_MODULO = 'educasync_otimizar';
    /**
     * Liberação temporária para testes locais: gerar/reotimizar sem TudiCoins.
     * Voltar para false antes de produção.
     */
    public const LIBERAR_SEM_TUDICOINS = true;

    private Cenario $cenarios;
    private Execucao $execucoes;
    private Versao $versoes;
    private Alocacao $alocacoes;
    private EducaSyncContextoService $contexto;
    private EducaSyncSolverCliente $solver;

    public function __construct(
        ?Cenario $cenarios = null,
        ?Execucao $execucoes = null,
        ?Versao $versoes = null,
        ?Alocacao $alocacoes = null,
        ?EducaSyncContextoService $contexto = null,
        ?EducaSyncSolverCliente $solver = null
    ) {
        $this->cenarios = $cenarios ?? new Cenario();
        $this->execucoes = $execucoes ?? new Execucao();
        $this->versoes = $versoes ?? new Versao();
        $this->alocacoes = $alocacoes ?? new Alocacao();
        $this->contexto = $contexto ?? new EducaSyncContextoService();
        $this->solver = $solver ?? new EducaSyncSolverCliente();
    }

    public static function disponivel(): bool
    {
        return self::LIBERAR_SEM_TUDICOINS
            || CreditosModuleRegistry::acaoIaDisponivel(self::CREDITO_MODULO);
    }

    /**
     * Valida o cenário, debita TudiCoins e coloca a otimização na fila.
     *
     * Opções (reotimização parcial, todas opcionais):
     * - versao_base_id (int): versão do cenário cujas aulas viram locks;
     * - fixar_dias (list<int> 1–7): trava as aulas da versão base que caem nesses dias;
     * - fixar_todas (bool): trava todas as aulas da versão base (precedência sobre fixar_dias).
     * Com versao_base_id é obrigatório informar fixar_dias ou fixar_todas. A origem dos locks
     * fica em parametros_json.locks_origem da execução; o job reconstrói os locks a partir dela.
     *
     * @param array<string,mixed> $opcoes
     * @return array{success:bool, error?:string, execucao_id?:int, job_id?:int, aulas_fixadas?:int}
     */
    public function enfileirar(int $cenarioId, int $usuarioId, array $opcoes = []): array
    {
        $cenario = $this->cenarios->findById($cenarioId);
        if (!$cenario) {
            return ['success' => false, 'error' => 'Cenário não encontrado.'];
        }
        if ((string) $cenario['status'] === 'arquivado') {
            return ['success' => false, 'error' => 'Cenário arquivado não pode ser otimizado.'];
        }
        if (!self::disponivel()) {
            return ['success' => false, 'error' => 'Otimização indisponível. Ative TudiCoins para esta escola no Master.'];
        }
        $ativa = $this->execucoes->ativaDoCenario($cenarioId);
        if ($ativa) {
            $ativa = $this->sincronizarComJob($ativa);
        }
        if ($ativa && in_array((string) $ativa['status'], Execucao::STATUS_ATIVOS, true)) {
            return ['success' => false, 'error' => 'Já existe uma otimização em andamento neste cenário.'];
        }

        $erros = $this->contexto->validar($cenarioId);
        if ($erros !== []) {
            return ['success' => false, 'error' => implode(' ', $erros)];
        }

        $locksOrigem = null;
        $totalLocks = 0;
        if ($opcoes !== []) {
            $preparo = $this->prepararLocks($cenarioId, $opcoes);
            if (isset($preparo['error'])) {
                return ['success' => false, 'error' => $preparo['error']];
            }
            $locksOrigem = $preparo['origem'];
            $totalLocks = $preparo['total'];
        }

        $maxTimeS = $this->contexto->tempoMaximo($cenario);
        $creditosRef = 'educasync_otimizar:' . $cenarioId . ':' . bin2hex(random_bytes(8));
        $debitou = false;
        $execucaoId = 0;

        try {
            if (!self::LIBERAR_SEM_TUDICOINS) {
                $creditos = new \App\Services\CreditosService();
                if (!$creditos->podeConsumir('escola', CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, self::CREDITO_MODULO)) {
                    return ['success' => false, 'error' => 'TudiCoins insuficientes na carteira da escola.'];
                }
                $creditos->consumirEscola(self::CREDITO_MODULO, $creditosRef);
                $debitou = true;
            }

            $parametros = [
                'max_time_s' => $maxTimeS,
                'pesos' => $this->contexto->pesosDoCenario($cenario),
            ];
            if ($locksOrigem !== null) {
                $parametros['locks_origem'] = $locksOrigem;
            }
            $execucaoId = $this->execucoes->criar($cenarioId, $usuarioId, $parametros);

            $payloadJob = [
                'cenario_id' => $cenarioId,
                'execucao_id' => $execucaoId,
            ];
            if ($debitou) {
                $payloadJob['credits_ref'] = $creditosRef;
                $payloadJob['credits_modulo'] = self::CREDITO_MODULO;
            }
            $jobId = AIJobService::enqueue(self::JOB_TIPO, $payloadJob, $usuarioId, 'admin', false);
            $this->execucoes->atualizar($execucaoId, ['ai_job_id' => $jobId]);
            AIJobService::tentarDispararWorker();
        } catch (\Throwable $e) {
            error_log('EducaSyncOtimizacaoService::enfileirar: ' . $e->getMessage());
            if ($execucaoId > 0) {
                $this->execucoes->atualizar($execucaoId, ['status' => 'falhou', 'log_resumo' => 'Não foi possível enfileirar a otimização.']);
            }
            if ($debitou) {
                $this->estornar($creditosRef);
            }
            return ['success' => false, 'error' => 'Não foi possível iniciar a otimização. Tente novamente.'];
        }

        $retorno = ['success' => true, 'execucao_id' => $execucaoId, 'job_id' => $jobId];
        if ($locksOrigem !== null) {
            $retorno['aulas_fixadas'] = $totalLocks;
        }
        return $retorno;
    }

    /**
     * Valida as opções de reotimização e conta os locks que valeriam hoje.
     *
     * @param array<string,mixed> $opcoes
     * @return array{error?:string, origem?:array<string,mixed>, total?:int}
     */
    private function prepararLocks(int $cenarioId, array $opcoes): array
    {
        $versaoBaseId = (int) ($opcoes['versao_base_id'] ?? 0);
        $fixarTodas = !empty($opcoes['fixar_todas']);
        $fixarDias = $this->normalizarDias($opcoes['fixar_dias'] ?? []);

        if ($versaoBaseId <= 0) {
            return ['error' => 'Informe a versão base para reotimizar.'];
        }
        $versao = $this->versoes->findById($versaoBaseId);
        if (!$versao || (int) $versao['cenario_id'] !== $cenarioId) {
            return ['error' => 'Versão base não encontrada neste cenário.'];
        }
        if (!$fixarTodas && $fixarDias === []) {
            return ['error' => 'Escolha ao menos um dia para manter fixo (ou fixar todas as aulas).'];
        }

        $locks = $this->contexto->locksDaVersao($cenarioId, $versaoBaseId, $fixarDias, $fixarTodas);
        if ($locks === []) {
            return ['error' => 'A versão base não tem aulas aplicáveis nos dias escolhidos.'];
        }

        return [
            'origem' => [
                'versao_base_id' => $versaoBaseId,
                'fixar_dias' => $fixarTodas ? [] : $fixarDias,
                'fixar_todas' => $fixarTodas,
                'total' => count($locks),
            ],
            'total' => count($locks),
        ];
    }

    /**
     * @param mixed $dias
     * @return list<int> dias 1–7 únicos, ordenados
     */
    private function normalizarDias($dias): array
    {
        if (!is_array($dias)) {
            return [];
        }
        $out = [];
        foreach ($dias as $dia) {
            if (is_int($dia) || (is_string($dia) && ctype_digit($dia))) {
                $d = (int) $dia;
                if ($d >= 1 && $d <= 7) {
                    $out[$d] = $d;
                }
            }
        }
        ksort($out);
        return array_values($out);
    }

    /**
     * Reconstrói os locks a partir da origem gravada na execução (versão base é imutável).
     *
     * @param array<string,mixed> $execucao
     * @return list<array{demanda_id:int, slot_id:int}>
     */
    private function locksDaExecucao(array $execucao, int $cenarioId): array
    {
        $parametros = json_decode((string) ($execucao['parametros_json'] ?? ''), true);
        $origem = is_array($parametros) ? ($parametros['locks_origem'] ?? null) : null;
        if (!is_array($origem)) {
            return [];
        }
        $versaoBaseId = (int) ($origem['versao_base_id'] ?? 0);
        $versao = $versaoBaseId > 0 ? $this->versoes->findById($versaoBaseId) : null;
        if (!$versao || (int) $versao['cenario_id'] !== $cenarioId) {
            throw new \RuntimeException('Versão base da reotimização não existe mais.');
        }
        return $this->contexto->locksDaVersao(
            $cenarioId,
            $versaoBaseId,
            $this->normalizarDias($origem['fixar_dias'] ?? []),
            !empty($origem['fixar_todas'])
        );
    }

    /**
     * Executado pelo AIJobService (case educasync_otimizar).
     *
     * Falha de conexão/resposta do solver lança exceção: o job é tentado de novo
     * e, esgotadas as tentativas, o AIJobService estorna as TudiCoins.
     * Cenário sem solução (INFEASIBLE/UNKNOWN) não é falha técnica: encerra a
     * execução como "inviável" e estorna aqui mesmo.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function processarJob(array $payload): array
    {
        $cenarioId = (int) ($payload['cenario_id'] ?? 0);
        $execucaoId = (int) ($payload['execucao_id'] ?? 0);

        $execucao = $execucaoId > 0 ? $this->execucoes->findById($execucaoId) : null;
        if (!$execucao || (int) $execucao['cenario_id'] !== $cenarioId || $cenarioId <= 0) {
            throw new \RuntimeException('Execução do EducaSync não encontrada para este job.');
        }

        // Idempotência: job reprocessado após conclusão/inviável não gera segunda versão.
        if (!in_array((string) $execucao['status'], Execucao::STATUS_ATIVOS, true)) {
            return [
                'execucao_id' => $execucaoId,
                'versao_id' => !empty($execucao['versao_id']) ? (int) $execucao['versao_id'] : null,
                'solver_status' => $execucao['solver_status'] ?? null,
                'ignorado' => true,
            ];
        }
        if (!$this->execucoes->marcarExecutando($execucaoId)) {
            $atual = $this->execucoes->findById($execucaoId);
            return [
                'execucao_id' => $execucaoId,
                'versao_id' => !empty($atual['versao_id']) ? (int) $atual['versao_id'] : null,
                'solver_status' => $atual['solver_status'] ?? null,
                'ignorado' => true,
            ];
        }

        try {
            $cenario = $this->cenarios->findById($cenarioId);
            if (!$cenario) {
                throw new \RuntimeException('Cenário do EducaSync não encontrado.');
            }
            $maxTimeS = $this->contexto->tempoMaximo($cenario);
            $locks = $this->locksDaExecucao($execucao, $cenarioId);
            $resultado = $this->solver->otimizar($this->contexto->montarPayload($cenarioId, $locks), $maxTimeS);
        } catch (\Throwable $e) {
            error_log('EducaSyncOtimizacaoService::processarJob: ' . $e->getMessage());
            $this->execucoes->atualizar($execucaoId, [
                'log_resumo' => 'Falha ao falar com o otimizador. Tente novamente em instantes.',
            ]);
            throw $e;
        }

        return $this->persistirResultado($execucao, $resultado, $payload);
    }

    /**
     * Estado da execução para o polling da tela (sem expor o job de outro cenário).
     *
     * @return array<string,mixed>|null
     */
    public function status(int $cenarioId, int $execucaoId): ?array
    {
        $execucao = $this->execucoes->findById($execucaoId);
        if (!$execucao || (int) $execucao['cenario_id'] !== $cenarioId) {
            return null;
        }

        $execucao = $this->sincronizarComJob($execucao);

        return [
            'id' => (int) $execucao['id'],
            'status' => (string) $execucao['status'],
            'status_label' => Execucao::STATUS[$execucao['status']] ?? (string) $execucao['status'],
            'terminou' => !in_array($execucao['status'], Execucao::STATUS_ATIVOS, true),
            'solver_status' => $execucao['solver_status'],
            'versao_id' => !empty($execucao['versao_id']) ? (int) $execucao['versao_id'] : null,
            'tempo_ms' => $execucao['tempo_ms'] !== null ? (int) $execucao['tempo_ms'] : null,
            'mensagem' => (string) ($execucao['log_resumo'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $execucao
     * @param array{status:string, alocacoes:list<array<string,mixed>>, metricas:array<string,mixed>, objetivo:?float, best_bound:?float, tempo_ms:int} $resultado
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function persistirResultado(array $execucao, array $resultado, array $payload): array
    {
        $execucaoId = (int) $execucao['id'];
        $cenarioId = (int) $execucao['cenario_id'];
        $temSolucao = in_array($resultado['status'], ['OPTIMAL', 'FEASIBLE'], true) && $resultado['alocacoes'] !== [];

        if (!$temSolucao) {
            $this->execucoes->atualizar($execucaoId, [
                'status' => 'inviavel',
                'solver_status' => $resultado['status'],
                'objetivo' => $resultado['objetivo'],
                'best_bound' => $resultado['best_bound'],
                'tempo_ms' => $resultado['tempo_ms'],
                'log_resumo' => $this->mensagemSemSolucao($resultado['status'], $cenarioId),
            ]);
            if (!empty($payload['credits_ref'])) {
                $this->estornar((string) $payload['credits_ref']);
            }
            return ['execucao_id' => $execucaoId, 'versao_id' => null, 'solver_status' => $resultado['status']];
        }

        $alocacoesValidas = $this->validarAlocacoesDoCenario($cenarioId, $resultado['alocacoes']);
        if ($alocacoesValidas === null) {
            $this->execucoes->atualizar($execucaoId, [
                'status' => 'falhou',
                'solver_status' => $resultado['status'],
                'tempo_ms' => $resultado['tempo_ms'],
                'log_resumo' => 'O otimizador devolveu alocações incompatíveis com o cenário. Nada foi publicado.',
            ]);
            throw new \RuntimeException('Alocações do EducaSync inválidas para o cenário.');
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $metricas = $resultado['metricas'];
            $metricas['tempo_ms'] = $metricas['tempo_ms'] ?? $resultado['tempo_ms'];
            $parametrosExecucao = json_decode((string) ($execucao['parametros_json'] ?? ''), true);
            if (is_array($parametrosExecucao) && is_array($parametrosExecucao['locks_origem'] ?? null)) {
                $metricas['reotimizacao'] = $parametrosExecucao['locks_origem'];
            }
            $versaoId = $this->versoes->criar($cenarioId, $resultado['status'], $metricas);
            $this->alocacoes->inserirLote($versaoId, $alocacoesValidas);
            $this->execucoes->atualizar($execucaoId, [
                'versao_id' => $versaoId,
                'status' => 'concluida',
                'solver_status' => $resultado['status'],
                'objetivo' => $resultado['objetivo'],
                'best_bound' => $resultado['best_bound'],
                'tempo_ms' => $resultado['tempo_ms'],
                'log_resumo' => count($alocacoesValidas) . ' aulas alocadas.',
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('EducaSyncOtimizacaoService::persistirResultado: ' . $e->getMessage());
            $this->execucoes->atualizar($execucaoId, [
                'log_resumo' => 'Falha ao gravar o resultado da otimização.',
            ]);
            throw $e;
        }

        return [
            'execucao_id' => $execucaoId,
            'versao_id' => $versaoId,
            'solver_status' => $resultado['status'],
            'metricas' => $metricas,
        ];
    }

    /**
     * Se o job esgotou as tentativas, a execução ainda aparece como ativa: fecha como falha.
     *
     * @param array<string,mixed> $execucao
     * @return array<string,mixed>
     */
    private function sincronizarComJob(array $execucao): array
    {
        if (!in_array($execucao['status'], Execucao::STATUS_ATIVOS, true) || empty($execucao['ai_job_id'])) {
            return $execucao;
        }

        $job = AIJobService::getJob((int) $execucao['ai_job_id']);
        if (!is_array($job) || (string) ($job['status'] ?? '') !== 'failed') {
            return $execucao;
        }

        $mensagem = trim((string) ($execucao['log_resumo'] ?? ''));
        if ($mensagem === '') {
            $mensagem = mb_substr((string) ($job['error_message'] ?? 'A otimização falhou.'), 0, 1000);
        }
        $this->execucoes->atualizar((int) $execucao['id'], ['status' => 'falhou', 'log_resumo' => $mensagem]);
        $execucao['status'] = 'falhou';
        $execucao['log_resumo'] = $mensagem;

        return $execucao;
    }

    /**
     * Garante demanda/slot do cenário, professor preenchido e contagem = aulas_semana.
     *
     * @param list<array<string,mixed>> $alocacoes
     * @return list<array{demanda_id:int, slot_id:int, professor_id:int, sala_id:?int}>|null
     */
    private function validarAlocacoesDoCenario(int $cenarioId, array $alocacoes): ?array
    {
        $demandas = [];
        foreach ((new \App\Modulos\EducaSync\Models\Demanda())->listarPorCenario($cenarioId) as $d) {
            $demandas[(int) $d['id']] = $d;
        }
        $slots = [];
        foreach ((new \App\Modulos\EducaSync\Models\Slot())->listarPorCenario($cenarioId) as $s) {
            $slots[(int) $s['id']] = true;
        }

        $contagem = [];
        $normalizadas = [];
        foreach ($alocacoes as $a) {
            $demandaId = (int) ($a['demanda_id'] ?? 0);
            $slotId = (int) ($a['slot_id'] ?? 0);
            $professorId = (int) ($a['professor_id'] ?? 0);
            if ($demandaId <= 0 || !isset($demandas[$demandaId]) || $slotId <= 0 || !isset($slots[$slotId])) {
                return null;
            }
            if ($professorId <= 0) {
                $professorId = (int) ($demandas[$demandaId]['professor_id'] ?? 0);
            }
            if ($professorId <= 0) {
                return null;
            }
            $contagem[$demandaId] = ($contagem[$demandaId] ?? 0) + 1;
            $normalizadas[] = [
                'demanda_id' => $demandaId,
                'slot_id' => $slotId,
                'professor_id' => $professorId,
                'sala_id' => !empty($a['sala_id']) ? (int) $a['sala_id'] : (!empty($demandas[$demandaId]['sala_id']) ? (int) $demandas[$demandaId]['sala_id'] : null),
            ];
        }

        foreach ($demandas as $demandaId => $d) {
            if (($contagem[$demandaId] ?? 0) !== (int) $d['aulas_semana']) {
                return null;
            }
        }

        return $normalizadas;
    }

    private function mensagemSemSolucao(string $solverStatus, int $cenarioId = 0): string
    {
        if ($solverStatus === 'INFEASIBLE') {
            $base = 'O solver provou que não existe grade que atenda a todas as restrições.';
            $detalhe = $this->resumoSobrecargaProfessores($cenarioId);
            return $detalhe !== ''
                ? $base . ' ' . $detalhe
                : $base . ' Revise disponibilidades, professores e horários.';
        }
        if ($solverStatus === 'MODEL_INVALID') {
            return 'O solver recusou o cenário como inválido. Revise horários, demandas e professores.';
        }
        return 'O solver não encontrou uma grade no tempo limite. Aumente o tempo máximo ou simplifique o cenário.';
    }

    /**
     * Ex.: "Rafael Itinerário tem 36 aulas e só 27 horários livres."
     */
    private function resumoSobrecargaProfessores(int $cenarioId): string
    {
        if ($cenarioId <= 0) {
            return '';
        }
        try {
            $consulta = new EducaSyncAssistenteConsultaService();
            $analise = $consulta->analisarConflitos($cenarioId, []);
        } catch (\Throwable $e) {
            return '';
        }
        $trechos = [];
        foreach ((array) ($analise['conflitos_obvios'] ?? []) as $c) {
            if (($c['tipo'] ?? '') !== 'professor_sobrecarregado') {
                continue;
            }
            $trechos[] = ($c['professor'] ?? 'Professor')
                . ' tem ' . (int) ($c['aulas_semana'] ?? 0)
                . ' aulas e só ' . (int) ($c['horarios_livres'] ?? 0)
                . ' horários livres';
            if (count($trechos) >= 3) {
                break;
            }
        }
        if ($trechos === []) {
            return '';
        }
        return implode('; ', $trechos) . '.';
    }

    private function estornar(string $creditosRef): void
    {
        try {
            (new \App\Services\CreditosService())->estornarPorReferencia(self::CREDITO_MODULO, $creditosRef);
        } catch (\Throwable $e) {
            error_log('EducaSyncOtimizacaoService estorno: ' . $e->getMessage());
        }
    }
}
