<?php

namespace App\Modulos\EducaSync\Services;

use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Models\Demanda;
use App\Modulos\EducaSync\Models\Disponibilidade;
use App\Modulos\EducaSync\Models\Slot;
use App\Modulos\EducaSync\Models\Versao;
use App\Core\Database;
use App\Dominio\Escola\Models\MatrizCurricular;

/**
 * EducaSync — regras de cadastro do cenário, dos slots, das demandas e da disponibilidade.
 */
class EducaSyncCenarioService
{
    private Cenario $cenarios;
    private Slot $slots;
    private Demanda $demandas;
    private Versao $versoes;
    private Disponibilidade $disponibilidades;

    public function __construct(
        ?Cenario $cenarios = null,
        ?Slot $slots = null,
        ?Demanda $demandas = null,
        ?Versao $versoes = null,
        ?Disponibilidade $disponibilidades = null
    ) {
        $this->cenarios = $cenarios ?? new Cenario();
        $this->slots = $slots ?? new Slot();
        $this->demandas = $demandas ?? new Demanda();
        $this->versoes = $versoes ?? new Versao();
        $this->disponibilidades = $disponibilidades ?? new Disponibilidade();
    }

    public function disponibilidades(): Disponibilidade
    {
        return $this->disponibilidades;
    }

    public function cenarios(): Cenario
    {
        return $this->cenarios;
    }

    public function slots(): Slot
    {
        return $this->slots;
    }

    public function demandas(): Demanda
    {
        return $this->demandas;
    }

    public function versoes(): Versao
    {
        return $this->versoes;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool, error?:string, id?:int}
     */
    public function criar(array $post, int $usuarioId): array
    {
        $nome = trim((string) ($post['nome'] ?? ''));
        $anoLetivo = (int) ($post['ano_letivo'] ?? 0);
        $periodo = (string) ($post['periodo'] ?? 'manha');
        $maxTimeS = (int) ($post['max_time_s'] ?? 60);
        $pesoJanelas = (int) ($post['peso_janelas'] ?? 300);

        if ($nome === '' || mb_strlen($nome) > 150) {
            return ['success' => false, 'error' => 'Informe o nome do cenário (até 150 caracteres).'];
        }
        if ($anoLetivo < 2000 || $anoLetivo > 2100) {
            return ['success' => false, 'error' => 'Informe um ano letivo válido.'];
        }
        if (!isset(Cenario::PERIODOS[$periodo])) {
            return ['success' => false, 'error' => 'Período inválido.'];
        }

        $id = $this->cenarios->criar([
            'nome' => $nome,
            'ano_letivo' => $anoLetivo,
            'periodo' => $periodo,
            'max_time_s' => max(5, min(600, $maxTimeS)),
            'pesos_json' => json_encode(['janelas' => max(0, min(1000, $pesoJanelas))]),
            'created_by' => $usuarioId,
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Sugere duração e aulas/dia a partir das matrizes das turmas ativas do ano do cenário.
     * Horário de início e intervalo continuam manuais (a matriz não guarda isso).
     *
     * @return array{
     *   duracao_min:int,
     *   aulas_por_dia:int,
     *   hora_inicio:string,
     *   veio_matriz:bool,
     *   matrizes:int,
     *   aviso:?string
     * }
     */
    public function sugerirParametrosSlots(int $cenarioId): array
    {
        $cenario = $this->cenarios->findById($cenarioId);
        $periodo = (string) ($cenario['periodo'] ?? 'manha');
        $horaInicio = $periodo === 'tarde' ? '13:00' : '07:00';
        $padrao = [
            'duracao_min' => 50,
            'aulas_por_dia' => 5,
            'hora_inicio' => $horaInicio,
            'veio_matriz' => false,
            'matrizes' => 0,
            'aviso' => null,
        ];
        if (!$cenario) {
            return $padrao;
        }

        $turmas = $this->cenarios->turmasAtivasComMatriz((int) $cenario['ano_letivo']);
        if ($turmas === []) {
            $padrao['aviso'] = 'Nenhuma turma ativa neste ano — usando valores padrão.';
            return $padrao;
        }

        $matrizes = new MatrizCurricular();
        $duracoes = [];
        $aulasPorDiaMax = 0;
        $matrizesUsadas = [];
        $cache = [];

        foreach ($turmas as $turma) {
            $matrizId = (int) ($turma['matriz_curricular_id'] ?? 0);
            if ($matrizId <= 0) {
                continue;
            }
            $matrizesUsadas[$matrizId] = true;
            if (!isset($cache[$matrizId])) {
                $matriz = $matrizes->findById($matrizId);
                $duracao = (int) ($matriz['duracao_padrao_aula_minutos'] ?? 50);
                $totalAulas = 0;
                foreach ($matrizes->getComponentesOficiais($matrizId) as $componente) {
                    $totalAulas += max(0, (int) ($componente['aulas_semana'] ?? 0));
                }
                $cache[$matrizId] = [
                    'duracao' => max(20, min(120, $duracao > 0 ? $duracao : 50)),
                    'aulas_dia' => max(1, min(12, (int) ceil($totalAulas / 5))),
                ];
            }
            $duracoes[] = $cache[$matrizId]['duracao'];
            $aulasPorDiaMax = max($aulasPorDiaMax, $cache[$matrizId]['aulas_dia']);
        }

        if ($matrizesUsadas === []) {
            $padrao['aviso'] = 'Turmas sem matriz curricular vinculada — usando valores padrão.';
            return $padrao;
        }

        $contagem = array_count_values($duracoes);
        arsort($contagem);
        $duracaoMaisComum = (int) array_key_first($contagem);

        return [
            'duracao_min' => $duracaoMaisComum,
            'aulas_por_dia' => max(1, $aulasPorDiaMax),
            'hora_inicio' => $horaInicio,
            'veio_matriz' => true,
            'matrizes' => count($matrizesUsadas),
            'aviso' => null,
        ];
    }

    /**
     * Cria (ou atualiza o horário de) slots em lote: N aulas por dia a partir de um horário inicial.
     *
     * @param array<string,mixed> $post dias[], hora_inicio, duracao_min, aulas_por_dia, intervalo_hora, intervalo_min
     * @return array{success:bool, error?:string, total?:int}
     */
    public function gerarSlots(int $cenarioId, array $post): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }

        $dias = [];
        foreach ((array) ($post['dias'] ?? []) as $dia) {
            $dia = (int) $dia;
            if (isset(Slot::DIAS[$dia])) {
                $dias[$dia] = $dia;
            }
        }
        $inicio = $this->minutos((string) ($post['hora_inicio'] ?? ''));
        $duracao = (int) ($post['duracao_min'] ?? 0);
        $aulasPorDia = (int) ($post['aulas_por_dia'] ?? 0);
        $intervaloHoraRaw = trim((string) ($post['intervalo_hora'] ?? ''));
        $intervaloInicio = $intervaloHoraRaw !== '' ? $this->minutos($intervaloHoraRaw) : null;
        $intervaloMin = (int) ($post['intervalo_min'] ?? 0);

        if ($dias === []) {
            return ['success' => false, 'error' => 'Selecione ao menos um dia da semana.'];
        }
        if ($inicio === null) {
            return ['success' => false, 'error' => 'Informe o horário da primeira aula.'];
        }
        if ($duracao < 20 || $duracao > 120) {
            return ['success' => false, 'error' => 'A duração da aula deve ficar entre 20 e 120 minutos.'];
        }
        if ($aulasPorDia < 1 || $aulasPorDia > 12) {
            return ['success' => false, 'error' => 'Informe de 1 a 12 aulas por dia.'];
        }
        if ($intervaloHoraRaw !== '' && $intervaloInicio === null) {
            return ['success' => false, 'error' => 'Informe um horário válido para o início do intervalo.'];
        }
        $intervaloMin = max(0, min(90, $intervaloMin));
        if ($intervaloInicio !== null && $intervaloMin < 1) {
            return ['success' => false, 'error' => 'Informe a duração do intervalo em minutos.'];
        }
        if ($intervaloInicio !== null && $intervaloInicio <= $inicio) {
            return ['success' => false, 'error' => 'O intervalo deve começar depois da primeira aula.'];
        }

        $total = 0;
        foreach ($dias as $dia) {
            $cursor = $inicio;
            $intervaloAplicado = false;
            for ($ordem = 1; $ordem <= $aulasPorDia; $ordem++) {
                if (
                    $intervaloInicio !== null
                    && !$intervaloAplicado
                    && $intervaloMin > 0
                    && ($cursor >= $intervaloInicio || ($cursor + $duracao) > $intervaloInicio)
                ) {
                    $cursor = $intervaloInicio + $intervaloMin;
                    $intervaloAplicado = true;
                }
                $fim = $cursor + $duracao;
                if ($fim > 24 * 60 - 1) {
                    return ['success' => false, 'error' => 'Os horários passam da meia-noite. Reduza as aulas por dia.'];
                }
                $this->slots->salvar($cenarioId, $dia, $ordem, $this->hhmm($cursor), $this->hhmm($fim));
                $total++;
                $cursor = $fim;
            }
        }

        return ['success' => true, 'total' => $total];
    }

    /**
     * @return array{success:bool, error?:string}
     */
    public function excluirSlot(int $cenarioId, int $slotId): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }
        return $this->slots->excluir($cenarioId, $slotId) > 0
            ? ['success' => true]
            : ['success' => false, 'error' => 'Horário não encontrado.'];
    }

    /**
     * @param array<string,mixed> $post turma_id, materia_id, professor_id, aulas_semana, sala_id
     * @return array{success:bool, error?:string}
     */
    public function salvarDemanda(int $cenarioId, array $post): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }

        $turmaId = (int) ($post['turma_id'] ?? 0);
        $materiaId = (int) ($post['materia_id'] ?? 0);
        $professorId = (int) ($post['professor_id'] ?? 0);
        $aulasSemana = (int) ($post['aulas_semana'] ?? 0);
        $salaId = (int) ($post['sala_id'] ?? 0);

        if ($turmaId <= 0 || $materiaId <= 0) {
            return ['success' => false, 'error' => 'Escolha a turma e a matéria.'];
        }
        if ($professorId <= 0) {
            return ['success' => false, 'error' => 'Escolha o professor da demanda.'];
        }
        if ($aulasSemana < 1 || $aulasSemana > 40) {
            return ['success' => false, 'error' => 'Informe de 1 a 40 aulas por semana.'];
        }

        $this->demandas->salvar($cenarioId, $turmaId, $materiaId, $professorId, $aulasSemana, $salaId > 0 ? $salaId : null, 'manual');
        return ['success' => true];
    }

    /**
     * @return array{success:bool, error?:string}
     */
    public function excluirDemanda(int $cenarioId, int $demandaId): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }
        return $this->demandas->excluir($cenarioId, $demandaId) > 0
            ? ['success' => true]
            : ['success' => false, 'error' => 'Demanda não encontrada.'];
    }

    /**
     * Remove todas as demandas do cenário (antes de reimportar outra matriz/ano).
     *
     * @return array{success:bool, removidas?:int, error?:string}
     */
    public function excluirTodasDemandas(int $cenarioId): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }
        if (!$this->cenarios->findById($cenarioId)) {
            return ['success' => false, 'error' => 'Cenário não encontrado.'];
        }
        $removidas = $this->demandas->excluirTodas($cenarioId);
        return ['success' => true, 'removidas' => $removidas];
    }

    /**
     * Troca o ano letivo do cenário. Demandas antigas são limpas — as turmas mudam com o ano.
     *
     * @return array{success:bool, ano_letivo?:int, demandas_removidas?:int, error?:string}
     */
    public function atualizarAnoLetivo(int $cenarioId, int $anoLetivo): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }
        $cenario = $this->cenarios->findById($cenarioId);
        if (!$cenario) {
            return ['success' => false, 'error' => 'Cenário não encontrado.'];
        }
        if ($anoLetivo < 2000 || $anoLetivo > 2100) {
            return ['success' => false, 'error' => 'Ano letivo inválido.'];
        }
        $atual = (int) ($cenario['ano_letivo'] ?? 0);
        if ($atual === $anoLetivo) {
            return ['success' => true, 'ano_letivo' => $anoLetivo, 'demandas_removidas' => 0];
        }

        $db = \App\Core\Database::getInstance();
        $db->beginTransaction();
        try {
            $removidas = $this->demandas->excluirTodas($cenarioId);
            $this->cenarios->atualizarAnoLetivo($cenarioId, $anoLetivo);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('EducaSyncCenarioService::atualizarAnoLetivo: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível atualizar o ano letivo.'];
        }

        return [
            'success' => true,
            'ano_letivo' => $anoLetivo,
            'demandas_removidas' => $removidas,
        ];
    }

    /**
     * Importa as demandas das turmas ativas do ano letivo a partir da Matriz Curricular.
     *
     * - Usa as linhas oficiais da matriz (desdobramentos somam no pai), só com aulas_semana > 0.
     * - Professor: vínculo do cadastro (professores.materias); se vários, o mais frequente na grade
     *   da turma entre os vinculados; se ninguém no cadastro, cai no histórico da grade.
     * - Demanda que já existe mantém professor e sala atuais; só preenche professor se estava vazio.
     *
     * @return array{success:bool, criadas?:int, atualizadas?:int, sem_professor?:int, sem_matriz?:int, error?:string}
     */
    public function importarDemandasMatriz(int $cenarioId): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }
        $cenario = $this->cenarios->findById($cenarioId);
        if (!$cenario) {
            return ['success' => false, 'error' => 'Cenário não encontrado.'];
        }

        $turmas = $this->cenarios->turmasAtivasComMatriz((int) $cenario['ano_letivo']);
        if ($turmas === []) {
            return ['success' => false, 'error' => 'Nenhuma turma ativa em ' . (int) $cenario['ano_letivo'] . '.'];
        }

        $existentes = [];
        foreach ($this->demandas->listarPorCenario($cenarioId) as $d) {
            $existentes[(int) $d['turma_id'] . ':' . (int) $d['materia_id']] = $d;
        }

        $matrizes = new MatrizCurricular();
        $cacheMatriz = [];
        $criadas = 0;
        $atualizadas = 0;
        $semProfessor = 0;
        $semMatriz = 0;
        $processouAlguma = false;

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            foreach ($turmas as $turma) {
                $matrizId = (int) ($turma['matriz_curricular_id'] ?? 0);
                if ($matrizId <= 0) {
                    $semMatriz++;
                    continue;
                }
                $turmaId = (int) $turma['id'];
                $cacheMatriz[$matrizId] ??= $matrizes->getComponentesOficiais($matrizId);

                foreach ($cacheMatriz[$matrizId] as $componente) {
                    $materiaId = (int) ($componente['materia_id'] ?? 0);
                    $aulas = (int) ($componente['aulas_semana'] ?? 0);
                    if ($materiaId <= 0 || $aulas <= 0) {
                        continue;
                    }
                    $aulas = min($aulas, 40);
                    $processouAlguma = true;

                    $chave = $turmaId . ':' . $materiaId;
                    $atual = $existentes[$chave] ?? null;
                    $professorId = $atual && !empty($atual['professor_id']) ? (int) $atual['professor_id'] : null;
                    $salaId = $atual && !empty($atual['sala_id']) ? (int) $atual['sala_id'] : null;

                    if ($professorId === null) {
                        $materiaIds = [$materiaId];
                        foreach ((array) ($componente['filhos'] ?? []) as $filho) {
                            $materiaIds[] = (int) ($filho['materia_id'] ?? 0);
                        }
                        $professorId = $this->demandas->professorSugerido($turmaId, $materiaIds);
                    }

                    $mudou = !$atual
                        || (int) $atual['aulas_semana'] !== $aulas
                        || (int) ($atual['professor_id'] ?? 0) !== (int) ($professorId ?? 0)
                        || (string) $atual['origem'] !== 'matriz';
                    if ($mudou) {
                        $this->demandas->salvar($cenarioId, $turmaId, $materiaId, $professorId, $aulas, $salaId, 'matriz');
                        $atual ? $atualizadas++ : $criadas++;
                    }
                    if ($professorId === null) {
                        $semProfessor++;
                    }
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('EducaSyncCenarioService::importarDemandasMatriz: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível importar as demandas da matriz.'];
        }

        if (!$processouAlguma) {
            return [
                'success' => false,
                'error' => $semMatriz > 0
                    ? 'Nenhuma turma ativa tem matriz curricular vinculada (' . $semMatriz . ' sem matriz).'
                    : 'As matrizes das turmas não têm componentes com aulas por semana.',
            ];
        }

        return [
            'success' => true,
            'criadas' => $criadas,
            'atualizadas' => $atualizadas,
            'sem_professor' => $semProfessor,
            'sem_matriz' => $semMatriz,
        ];
    }

    /**
     * Bloqueios de horário do professor: slots marcados ficam indisponíveis, o resto fica livre.
     *
     * @param array<string,mixed> $post professor_id, slots[] (ids dos slots BLOQUEADOS)
     * @return array{success:bool, error?:string, total?:int}
     */
    public function salvarDisponibilidadeProfessor(int $cenarioId, array $post): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }
        $professorId = (int) ($post['professor_id'] ?? 0);
        if ($professorId <= 0 || !$this->cenarios->professorAtivo($professorId)) {
            return ['success' => false, 'error' => 'Escolha um professor válido.'];
        }

        $slotIds = is_array($post['slots'] ?? null) ? $post['slots'] : [];
        try {
            $total = $this->disponibilidades->salvarBloqueios($cenarioId, 'professor', $professorId, $slotIds);
        } catch (\Throwable $e) {
            error_log('EducaSyncCenarioService::salvarDisponibilidadeProfessor: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível salvar a disponibilidade.'];
        }
        return ['success' => true, 'total' => $total];
    }

    /**
     * @param array<string,mixed> $post professor_id
     * @return array{success:bool, error?:string}
     */
    public function limparDisponibilidadeProfessor(int $cenarioId, array $post): array
    {
        if ($this->bloqueadoPorVersoes($cenarioId)) {
            return ['success' => false, 'error' => $this->mensagemBloqueio()];
        }
        $professorId = (int) ($post['professor_id'] ?? 0);
        if ($professorId <= 0) {
            return ['success' => false, 'error' => 'Escolha um professor.'];
        }
        $this->disponibilidades->limpar($cenarioId, 'professor', $professorId);
        return ['success' => true];
    }

    /** Slots e demandas já usados por versões não podem sumir (as alocações cairiam junto). */
    private function bloqueadoPorVersoes(int $cenarioId): bool
    {
        return $this->versoes->listarPorCenario($cenarioId) !== [];
    }

    private function mensagemBloqueio(): string
    {
        return 'Este cenário já tem versões geradas. Crie um novo cenário para alterar horários ou remover demandas.';
    }

    private function minutos(string $hora): ?int
    {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', trim($hora), $m)) {
            return null;
        }
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    private function hhmm(int $minutos): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60);
    }
}
