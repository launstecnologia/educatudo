<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../Models/VidaEscolar.php';
require_once __DIR__ . '/../../../Services/ResultadoAcademicoService.php';

use App\Modulos\VidaEscolar\Models\VidaEscolar;
use Database;
use ResultadoAcademicoService;

/**
 * Fichas oficiais de boletim e histórico. A composição (provas/jornadas)
 * continua no motor legado; este serviço grava a célula do documento.
 */
class VidaEscolarService
{
    public const PERIODOS = [1 => '1º Bimestre', 2 => '2º Bimestre', 3 => '3º Bimestre', 4 => '4º Bimestre', 0 => 'FINAL'];

    private VidaEscolar $model;
    private $db;
    private ?ResultadoAcademicoService $motor = null;
    /** @var array<string, ?array<string,mixed>> */
    private array $regraAcadCache = [];
    /** @var array<string, array<string, int>> alunoId_materiaId => faltas, chave ano:bimestre */
    private array $faltasLancadasCache = [];

    public function __construct(?VidaEscolar $model = null)
    {
        $this->model = $model ?? new VidaEscolar();
        $this->db = Database::getInstance();
    }

    public function model(): VidaEscolar
    {
        return $this->model;
    }

    private function motor(): ResultadoAcademicoService
    {
        if ($this->motor === null) {
            $this->motor = new ResultadoAcademicoService();
        }
        return $this->motor;
    }

    public function quadroDoAluno(int $alunoId): ?array
    {
        if (!$this->model->schemaPronto() || $alunoId <= 0) {
            return null;
        }
        $fichas = $this->model->listarFichasAluno($alunoId);
        if ($fichas === []) {
            return null;
        }
        return $this->quadro((int) $fichas[0]['id']);
    }

    public static function aoVincularTurma(int $alunoId, int $turmaId, int $anoLetivo): void
    {
        try {
            $svc = new self();
            $ok = $svc->garantirFicha($alunoId, $turmaId, $anoLetivo, null);
            if (!empty($ok['success'])) {
                $svc->sincronizarDeEventosGerados(
                    $alunoId,
                    [],
                    null,
                    null,
                    (int) ($ok['id'] ?? 0) ?: null,
                    false,
                    false
                );
            }
        } catch (\Throwable $e) {
            error_log('VidaEscolar aoVincularTurma: ' . $e->getMessage());
        }
    }

    /**
     * Garante a ficha vazia do ano (matrícula / vínculo turma).
     *
     * @return array{success: bool, id?: int, criada?: bool, error?: string}
     */
    public function garantirFicha(int $alunoId, int $turmaId, int $anoLetivo, ?int $usuarioId = null): array
    {
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Migration da vida escolar ainda não foi aplicada.'];
        }
        if ($alunoId <= 0 || $turmaId <= 0 || $anoLetivo <= 0) {
            return ['success' => false, 'error' => 'Aluno, turma e ano letivo são obrigatórios.'];
        }

        $existente = $this->model->findFichaAlunoAno($alunoId, $anoLetivo, $turmaId);
        if ($existente) {
            return ['success' => true, 'id' => (int) $existente['id'], 'criada' => false];
        }

        $turma = $this->model->turmaPorId($turmaId);
        $matricula = $this->model->findMatriculaAtiva($alunoId, $turmaId);
        $fichaId = $this->model->criarFicha([
            'aluno_id' => $alunoId,
            'turma_id' => $turmaId,
            'matricula_id' => $matricula['id'] ?? null,
            'ano_letivo' => $anoLetivo,
            'serie_nome' => $turma['serie'] ?? null,
            'status' => 'em_curso',
        ]);

        $componentes = $this->model->componentesDaTurma($turmaId);
        $ordem = 0;
        foreach ($componentes as $comp) {
            $ordem++;
            $linhaId = $this->model->criarLinha([
                'ficha_id' => $fichaId,
                'materia_id' => (int) ($comp['materia_id'] ?? 0) ?: null,
                'componente_nome' => (string) ($comp['componente_nome'] ?? 'Componente'),
                'carga_horaria' => null,
                'ordem' => $ordem,
            ]);
            foreach ([1, 2, 3, 4, 0] as $periodo) {
                $this->model->criarCelula([
                    'linha_id' => $linhaId,
                    'periodo_numero' => $periodo,
                    'origem' => 'vazia',
                    'status' => 'aberta',
                ]);
            }
        }

        $this->model->registrarAuditoria([
            'ficha_id' => $fichaId,
            'acao' => 'criar_ficha',
            'valor_novo' => json_encode(['ano' => $anoLetivo, 'turma_id' => $turmaId], JSON_UNESCAPED_UNICODE),
            'usuario_id' => $usuarioId,
        ]);

        return ['success' => true, 'id' => $fichaId, 'criada' => true];
    }

    /**
     * Quadro componente × bimestres para tela/PDF.
     *
     * @return array<string,mixed>|null
     */
    public function quadro(int $fichaId): ?array
    {
        $ficha = $this->model->findFicha($fichaId);
        if (!$ficha) {
            return null;
        }
        $linhas = $this->model->listarLinhas($fichaId);
        $celulas = $this->model->listarCelulas($fichaId);
        $porLinha = [];
        foreach ($celulas as $c) {
            $lid = (int) $c['linha_id'];
            $porLinha[$lid][(int) $c['periodo_numero']] = $c;
        }
        $grid = [];
        foreach ($linhas as $l) {
            $lid = (int) $l['id'];
            $grid[] = [
                'linha' => $l,
                'celulas' => $porLinha[$lid] ?? [],
            ];
        }

        return [
            'ficha' => $ficha,
            'grid' => $grid,
            'periodos' => self::PERIODOS,
            'auditoria' => $this->model->listarAuditoria($fichaId, 40),
        ];
    }

    /**
     * Secretaria lança/corrige célula aberta ou reaberta. Não altera externa homologada.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed> $usuario
     * @return array{success: bool, error?: string}
     */
    public function salvarCelula(int $celulaId, array $input, array $usuario, ?int $alunoId = null): array
    {
        $cel = $this->model->findCelula($celulaId);
        if (!$cel) {
            return ['success' => false, 'error' => 'Célula não encontrada.'];
        }
        $ficha = $this->model->findFicha((int) $cel['ficha_id']);
        if (!$ficha) {
            return ['success' => false, 'error' => 'Ficha não encontrada.'];
        }
        if ($alunoId !== null && $alunoId > 0 && (int) ($ficha['aluno_id'] ?? 0) !== $alunoId) {
            return ['success' => false, 'error' => 'Célula não pertence a este aluno.'];
        }
        if (($ficha['status'] ?? '') === 'homologada' && ($cel['status'] ?? '') !== 'reaberta') {
            return ['success' => false, 'error' => 'Ficha homologada. Reabra o período com motivo para corrigir.'];
        }
        if (in_array($cel['status'] ?? '', ['fechada', 'homologada'], true)) {
            return ['success' => false, 'error' => 'Célula fechada. Reabra com motivo para alterar.'];
        }
        if (($cel['origem'] ?? '') === 'externa' && ($cel['status'] ?? '') !== 'reaberta' && ($cel['status'] ?? '') !== 'aberta') {
            return ['success' => false, 'error' => 'Resultado de outra escola não pode ser alterado pelo lançamento comum.'];
        }

        $antes = [
            'nota' => $cel['nota'],
            'conceito' => $cel['conceito'],
            'faltas' => $cel['faltas'],
            'origem' => $cel['origem'],
        ];

        $nota = $this->notaOuNull($input['nota'] ?? null);
        $conceito = trim((string) ($input['conceito'] ?? '')) ?: null;
        $faltas = isset($input['faltas']) && $input['faltas'] !== '' ? (int) $input['faltas'] : null;
        $origem = (string) ($input['origem'] ?? $cel['origem'] ?? 'calculada');
        if (!in_array($origem, ['vazia', 'calculada', 'externa', 'mista'], true)) {
            $origem = 'calculada';
        }
        if ($nota === null && $conceito === null) {
            $origem = 'vazia';
        } elseif ($origem === 'vazia') {
            $origem = 'calculada';
        }

        $this->model->atualizarCelula($celulaId, [
            'nota' => $nota,
            'conceito' => $conceito,
            'faltas' => $faltas,
            'origem' => $origem,
            'escola_origem' => trim((string) ($input['escola_origem'] ?? $cel['escola_origem'] ?? '')) ?: null,
            'nota_original' => trim((string) ($input['nota_original'] ?? $cel['nota_original'] ?? '')) ?: null,
            'escala_original' => trim((string) ($input['escala_original'] ?? $cel['escala_original'] ?? '')) ?: null,
            'observacao' => trim((string) ($input['observacao'] ?? $cel['observacao'] ?? '')) ?: null,
            'versao' => (int) ($cel['versao'] ?? 1) + ((($cel['status'] ?? '') === 'reaberta') ? 1 : 0),
        ]);

        $this->recalcularFinal((int) $cel['linha_id']);

        $this->model->registrarAuditoria([
            'ficha_id' => (int) $cel['ficha_id'],
            'celula_id' => $celulaId,
            'acao' => 'salvar_celula',
            'campo' => 'nota',
            'valor_anterior' => json_encode($antes, JSON_UNESCAPED_UNICODE),
            'valor_novo' => json_encode(['nota' => $nota, 'conceito' => $conceito, 'faltas' => $faltas, 'origem' => $origem], JSON_UNESCAPED_UNICODE),
            'motivo' => trim((string) ($input['motivo'] ?? '')) ?: null,
            'usuario_id' => (int) ($usuario['id'] ?? 0) ?: null,
            'usuario_nome' => $usuario['nome'] ?? null,
            'usuario_perfil' => $usuario['tipo'] ?? null,
        ]);

        return ['success' => true];
    }

    /**
     * Fecha as células preenchidas de um bimestre (1-4).
     *
     * @return array{success: bool, error?: string, fechadas?: int}
     */
    public function fecharBimestre(int $fichaId, int $bimestre, array $usuario): array
    {
        if ($bimestre < 1 || $bimestre > 4) {
            return ['success' => false, 'error' => 'Bimestre inválido.'];
        }
        $ficha = $this->model->findFicha($fichaId);
        if (!$ficha || ($ficha['status'] ?? '') === 'homologada') {
            return ['success' => false, 'error' => 'Ficha indisponível para fechamento.'];
        }
        $n = 0;
        foreach ($this->model->listarCelulas($fichaId) as $c) {
            if ((int) $c['periodo_numero'] !== $bimestre) {
                continue;
            }
            if (($c['status'] ?? '') !== 'aberta' && ($c['status'] ?? '') !== 'reaberta') {
                continue;
            }
            if (($c['origem'] ?? '') === 'vazia') {
                continue;
            }
            $this->model->atualizarCelula((int) $c['id'], ['status' => 'fechada']);
            $n++;
        }
        $this->model->registrarAuditoria([
            'ficha_id' => $fichaId,
            'acao' => 'fechar_bimestre',
            'valor_novo' => (string) $bimestre,
            'usuario_id' => (int) ($usuario['id'] ?? 0) ?: null,
            'usuario_nome' => $usuario['nome'] ?? null,
            'usuario_perfil' => $usuario['tipo'] ?? null,
        ]);
        return ['success' => true, 'fechadas' => $n];
    }

    /**
     * Homologa a ficha do ano (trava células não vazias e gera linha de escolarização interna).
     *
     * @return array{success: bool, error?: string}
     */
    public function homologarFicha(int $fichaId, array $usuario): array
    {
        $ficha = $this->model->findFicha($fichaId);
        if (!$ficha) {
            return ['success' => false, 'error' => 'Ficha não encontrada.'];
        }
        if (($ficha['status'] ?? '') === 'homologada') {
            return ['success' => true];
        }
        foreach ($this->model->listarLinhas($fichaId) as $linha) {
            $this->recalcularFinal((int) $linha['id']);
        }
        foreach ($this->model->listarCelulas($fichaId) as $c) {
            if (($c['origem'] ?? '') === 'vazia') {
                continue;
            }
            if (($c['status'] ?? '') !== 'homologada') {
                $this->model->atualizarCelula((int) $c['id'], ['status' => 'homologada']);
            }
        }
        $this->model->atualizarFicha($fichaId, [
            'status' => 'homologada',
            'homologada_em' => date('Y-m-d H:i:s'),
            'homologada_por' => (int) ($usuario['id'] ?? 0) ?: null,
        ]);
        $this->sincronizarEscolarizacaoInterna($fichaId);
        $this->model->registrarAuditoria([
            'ficha_id' => $fichaId,
            'acao' => 'homologar',
            'usuario_id' => (int) ($usuario['id'] ?? 0) ?: null,
            'usuario_nome' => $usuario['nome'] ?? null,
            'usuario_perfil' => $usuario['tipo'] ?? null,
        ]);
        return ['success' => true];
    }

    /**
     * Reabre célula ou bimestre inteiro. Motivo obrigatório.
     *
     * @return array{success: bool, error?: string}
     */
    public function reabrir(int $fichaId, int $bimestre, string $motivo, array $usuario, ?int $celulaId = null): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            return ['success' => false, 'error' => 'Informe o motivo da reabertura.'];
        }
        $ficha = $this->model->findFicha($fichaId);
        if (!$ficha) {
            return ['success' => false, 'error' => 'Ficha não encontrada.'];
        }
        $alvos = [];
        foreach ($this->model->listarCelulas($fichaId) as $c) {
            if ($celulaId !== null && $celulaId > 0 && (int) $c['id'] !== $celulaId) {
                continue;
            }
            if ($celulaId === null && $bimestre >= 1 && $bimestre <= 4 && (int) $c['periodo_numero'] !== $bimestre) {
                continue;
            }
            if (!in_array($c['status'] ?? '', ['fechada', 'homologada'], true)) {
                continue;
            }
            $alvos[] = $c;
        }
        if ($alvos === []) {
            return ['success' => false, 'error' => 'Nenhuma célula homologada/fechada para reabrir.'];
        }
        if (($ficha['status'] ?? '') === 'homologada') {
            $this->model->atualizarFicha($fichaId, [
                'status' => 'em_curso',
                'versao' => (int) ($ficha['versao'] ?? 1) + 1,
            ]);
        }
        foreach ($alvos as $c) {
            $this->model->atualizarCelula((int) $c['id'], [
                'status' => 'reaberta',
                'versao' => (int) ($c['versao'] ?? 1) + 1,
            ]);
            $this->model->registrarAuditoria([
                'ficha_id' => $fichaId,
                'celula_id' => (int) $c['id'],
                'acao' => 'reabrir',
                'valor_anterior' => json_encode(['status' => $c['status'], 'nota' => $c['nota']], JSON_UNESCAPED_UNICODE),
                'motivo' => $motivo,
                'usuario_id' => (int) ($usuario['id'] ?? 0) ?: null,
                'usuario_nome' => $usuario['nome'] ?? null,
                'usuario_perfil' => $usuario['tipo'] ?? null,
            ]);
        }
        return ['success' => true];
    }

    /**
     * Copia médias e faltas dos eventos oficiais (Notas e Boletim) para a ficha.
     *
     * @return array{success: bool, atualizadas?: int, error?: string}
     */
    public function alimentarDoCalculo(int $alunoId, ?string $periodoRef = null, ?array $usuario = null, ?int $fichaId = null): array
    {
        return $this->sincronizarDeEventosGerados($alunoId, $usuario ?? [], null, $periodoRef, $fichaId, true, true);
    }

    /**
     * Grava na ficha todas as linhas oficiais de boletim_resultados_gerados (preview=0).
     * Evento de Notas preenche a coluna do bimestre; evento de Boletim (legado) preenche B1–B4.
     * A coluna FINAL sai da formula_media da regra acadêmica.
     *
     * @return array{success: bool, atualizadas?: int, error?: string}
     */
    public function sincronizarDeEventosGerados(
        int $alunoId,
        array $usuario = [],
        ?int $regraId = null,
        ?string $periodoRef = null,
        ?int $fichaId = null,
        bool $registrarAuditoria = true,
        bool $incluirReabertas = false
    ): array {
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Migration da vida escolar ainda não foi aplicada.'];
        }
        $aluno = $this->model->alunoPorId($alunoId);
        if (!$aluno) {
            return ['success' => false, 'error' => 'Aluno não encontrado.'];
        }
        if ($fichaId !== null && $fichaId > 0) {
            $ficha = $this->model->findFicha($fichaId);
            if (!$ficha || (int) ($ficha['aluno_id'] ?? 0) !== $alunoId) {
                return ['success' => false, 'error' => 'Ficha não encontrada para este aluno.'];
            }
            $ano = (int) ($ficha['ano_letivo'] ?? 0);
            $turmaId = (int) ($ficha['turma_id'] ?? 0);
            if ($ano <= 0 || $turmaId <= 0) {
                return ['success' => false, 'error' => 'Ficha sem turma ou ano letivo.'];
            }
        } else {
            $ano = (int) ($aluno['turma_ano_letivo'] ?? date('Y'));
            $turmaId = (int) ($aluno['turma_id'] ?? 0);
            if ($turmaId <= 0) {
                return ['success' => false, 'error' => 'Aluno sem turma.'];
            }
            $ok = $this->garantirFicha($alunoId, $turmaId, $ano, $usuario['id'] ?? null);
            if (empty($ok['success'])) {
                return $ok;
            }
            $fichaId = (int) $ok['id'];
            $ficha = $this->model->findFicha($fichaId);
        }

        if (!is_array($ficha) || ($ficha['status'] ?? '') === 'homologada') {
            return ['success' => true, 'atualizadas' => 0];
        }

        $linhas = $this->model->listarLinhas($fichaId);
        $porId = [];
        $porNome = [];
        foreach ($linhas as $linha) {
            $mid = (int) ($linha['materia_id'] ?? 0);
            if ($mid > 0) {
                $porId[$mid] = $linha;
            }
            $nome = mb_strtolower(trim((string) ($linha['componente_nome'] ?? '')));
            if ($nome !== '') {
                $porNome[$nome] = $linha;
            }
        }

        $n = 0;
        $linhasTocadas = [];
        foreach ($this->model->listarResultadosGeradosOficiais($alunoId) as $row) {
            if (!$this->eventoPertenceAoAno($row, $ano)) {
                continue;
            }
            $mid = (int) ($row['materia_id'] ?? 0);
            $nome = mb_strtolower(trim((string) ($row['materia_nome'] ?? '')));
            $linha = ($mid > 0 ? ($porId[$mid] ?? null) : null) ?? ($porNome[$nome] ?? null);
            if (!$linha) {
                continue;
            }
            $linhaId = (int) $linha['id'];
            $periodosGerados = $this->periodosDaLinhaGerada($row);
            $bimRow = (int) ($row['bimestre'] ?? 0);
            if ($bimRow < 1 || $bimRow > 4) {
                $bimRow = $this->bimestreDePeriodoRef((string) ($row['periodo_ref'] ?? '')) ?? 0;
            }
            if ($bimRow >= 1 && $bimRow <= 4 && !isset($periodosGerados[$bimRow])) {
                $periodosGerados[$bimRow] = ['nota' => null, 'faltas' => null];
            }
            foreach ($periodosGerados as $periodo => $vals) {
                if ($periodo < 1 || $periodo > 4) {
                    continue;
                }
                $faltas = $vals['faltas'] ?? null;
                if ($faltas === null) {
                    $faltas = $this->faltasLancadasAlunoMateria($alunoId, $mid, $periodo, $ano);
                }
                if ($this->aplicarCelulaCalculada(
                    $linhaId,
                    $periodo,
                    $vals['nota'] ?? null,
                    $faltas,
                    $incluirReabertas
                )) {
                    $n++;
                    $linhasTocadas[$linhaId] = true;
                }
            }
        }

        foreach (array_keys($linhasTocadas) as $linhaId) {
            $this->recalcularFinal((int) $linhaId, is_array($ficha) ? $ficha : null);
        }

        if ($n > 0 && $registrarAuditoria) {
            $this->model->registrarAuditoria([
                'ficha_id' => $fichaId,
                'acao' => 'alimentar_calculo',
                'valor_novo' => json_encode([
                    'atualizadas' => $n,
                    'periodo_ref' => $periodoRef,
                    'regra_id' => $regraId,
                    'origem' => 'eventos_gerados',
                ], JSON_UNESCAPED_UNICODE),
                'usuario_id' => (int) ($usuario['id'] ?? 0) ?: null,
                'usuario_nome' => $usuario['nome'] ?? null,
            ]);
        }
        return ['success' => true, 'atualizadas' => $n];
    }

    /**
     * Sincroniza fichas de vários alunos após gerar boletim em lote.
     *
     * @param list<int> $alunoIds
     * @return array{success: bool, atualizadas?: int, error?: string}
     */
    public function sincronizarDeEventosGeradosEmLote(
        array $alunoIds,
        array $usuario = [],
        ?int $regraId = null,
        ?string $periodoRef = null
    ): array {
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Migration da vida escolar ainda não foi aplicada.'];
        }
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static function ($id) {
            return $id > 0;
        })));
        if ($alunoIds === []) {
            return ['success' => true, 'atualizadas' => 0];
        }

        $alunos = $this->model->alunosPorIds($alunoIds);
        $porAno = [];
        foreach ($alunos as $aid => $aluno) {
            $ano = (int) ($aluno['turma_ano_letivo'] ?? date('Y'));
            $turmaId = (int) ($aluno['turma_id'] ?? 0);
            if ($ano <= 0 || $turmaId <= 0) {
                continue;
            }
            $porAno[$ano][$aid] = $aluno;
        }

        $n = 0;
        foreach ($porAno as $ano => $alunosAno) {
            $idsAno = array_map('intval', array_keys($alunosAno));
            $fichas = $this->model->listarFichasPorAlunosAno($idsAno, (int) $ano);
            foreach ($alunosAno as $aid => $aluno) {
                $turmaId = (int) ($aluno['turma_id'] ?? 0);
                $chaveFicha = $aid . ':' . $turmaId;
                if (isset($fichas[$chaveFicha])) {
                    continue;
                }
                $ok = $this->garantirFicha($aid, $turmaId, (int) $ano, $usuario['id'] ?? null);
                if (!empty($ok['success']) && (int) ($ok['id'] ?? 0) > 0) {
                    $fichaNova = $this->model->findFicha((int) $ok['id']);
                    if (is_array($fichaNova)) {
                        $fichas[$chaveFicha] = $fichaNova;
                    }
                }
            }

            $fichaIds = [];
            $fichaPorAluno = [];
            foreach ($alunosAno as $aid => $aluno) {
                $turmaId = (int) ($aluno['turma_id'] ?? 0);
                $ficha = $fichas[$aid . ':' . $turmaId] ?? null;
                if (!is_array($ficha) || ($ficha['status'] ?? '') === 'homologada') {
                    continue;
                }
                $fid = (int) ($ficha['id'] ?? 0);
                if ($fid <= 0) {
                    continue;
                }
                $fichaIds[] = $fid;
                $fichaPorAluno[$aid] = $ficha;
            }
            if ($fichaIds === []) {
                continue;
            }

            $linhasPorFicha = $this->model->listarLinhasPorFichas($fichaIds);
            $linhaIds = [];
            foreach ($linhasPorFicha as $linhasF) {
                foreach ($linhasF as $linha) {
                    $linhaIds[] = (int) ($linha['id'] ?? 0);
                }
            }
            $celulas = $this->model->listarCelulasPorLinhas($linhaIds);
            $resultadosPorAluno = $this->model->listarResultadosGeradosOficiaisPorAlunos(
                array_keys($fichaPorAluno),
                $regraId
            );
            $updates = [];
            $linhasTocadas = [];

            foreach ($fichaPorAluno as $aid => $ficha) {
                $fid = (int) ($ficha['id'] ?? 0);
                $linhas = $linhasPorFicha[$fid] ?? [];
                $porId = [];
                $porNome = [];
                foreach ($linhas as $linha) {
                    $mid = (int) ($linha['materia_id'] ?? 0);
                    if ($mid > 0) {
                        $porId[$mid] = $linha;
                    }
                    $nome = mb_strtolower(trim((string) ($linha['componente_nome'] ?? '')));
                    if ($nome !== '') {
                        $porNome[$nome] = $linha;
                    }
                }
                foreach ($resultadosPorAluno[$aid] ?? [] as $row) {
                    if (!$this->eventoPertenceAoAno($row, (int) $ano)) {
                        continue;
                    }
                    $mid = (int) ($row['materia_id'] ?? 0);
                    $nome = mb_strtolower(trim((string) ($row['materia_nome'] ?? '')));
                    $linha = ($mid > 0 ? ($porId[$mid] ?? null) : null) ?? ($porNome[$nome] ?? null);
                    if (!$linha) {
                        continue;
                    }
                    $linhaId = (int) $linha['id'];
                    $periodosGerados = $this->periodosDaLinhaGerada($row);
                    $bimRow = (int) ($row['bimestre'] ?? 0);
                    if ($bimRow < 1 || $bimRow > 4) {
                        $bimRow = $this->bimestreDePeriodoRef((string) ($row['periodo_ref'] ?? '')) ?? 0;
                    }
                    if ($bimRow >= 1 && $bimRow <= 4 && !isset($periodosGerados[$bimRow])) {
                        $periodosGerados[$bimRow] = ['nota' => null, 'faltas' => null];
                    }
                    foreach ($periodosGerados as $periodo => $vals) {
                        $periodo = (int) $periodo;
                        if ($periodo < 1 || $periodo > 4) {
                            continue;
                        }
                        $faltas = $vals['faltas'] ?? null;
                        if ($faltas === null) {
                            $faltas = $this->faltasLancadasAlunoMateria($aid, $mid, $periodo, (int) $ano);
                        }
                        $chaveCel = $linhaId . ':' . $periodo;
                        $cel = $celulas[$chaveCel] ?? null;
                        if (!$cel) {
                            continue;
                        }
                        $status = (string) ($cel['status'] ?? '');
                        if ($status !== 'aberta' || ($cel['origem'] ?? '') === 'externa') {
                            continue;
                        }
                        $nota = $vals['nota'] ?? null;
                        if ($nota === null && $faltas === null) {
                            continue;
                        }
                        $campos = ['id' => (int) $cel['id'], 'origem' => 'calculada'];
                        if ($nota !== null) {
                            $campos['nota'] = round((float) $nota, 2);
                            $celulas[$chaveCel]['nota'] = $campos['nota'];
                        } else {
                            $campos['nota'] = $cel['nota'] ?? null;
                        }
                        if ($faltas !== null) {
                            $campos['faltas'] = (int) $faltas;
                            $celulas[$chaveCel]['faltas'] = $campos['faltas'];
                        } else {
                            $campos['faltas'] = $cel['faltas'] ?? null;
                        }
                        $celulas[$chaveCel]['origem'] = 'calculada';
                        $updates[] = $campos;
                        $n++;
                        $linhasTocadas[$linhaId] = $ficha;
                    }
                }
            }

            foreach ($linhasTocadas as $linhaId => $ficha) {
                $porBim = [];
                $faltasTotal = 0;
                $temFaltas = false;
                foreach ([1, 2, 3, 4] as $p) {
                    $c = $celulas[$linhaId . ':' . $p] ?? null;
                    if ($c && is_numeric($c['nota'] ?? null)) {
                        $porBim[$p] = (float) $c['nota'];
                    }
                    if ($c && $c['faltas'] !== null && $c['faltas'] !== '') {
                        $faltasTotal += (int) $c['faltas'];
                        $temFaltas = true;
                    }
                }
                $final = $celulas[$linhaId . ':0'] ?? null;
                if (!$final) {
                    continue;
                }
                $stFinal = (string) ($final['status'] ?? '');
                if ($stFinal === 'homologada') {
                    continue;
                }
                if ($stFinal === 'fechada' && ($final['origem'] ?? '') === 'externa') {
                    continue;
                }
                $materiaId = 0;
                foreach ($linhasPorFicha[(int) ($ficha['id'] ?? 0)] ?? [] as $ln) {
                    if ((int) ($ln['id'] ?? 0) === (int) $linhaId) {
                        $materiaId = (int) ($ln['materia_id'] ?? 0);
                        break;
                    }
                }
                $media = $this->mediaFinalDaLinha($porBim, is_array($ficha) ? $ficha : [], $materiaId);
                $updates[] = [
                    'id' => (int) $final['id'],
                    'nota' => $media,
                    'faltas' => $temFaltas ? $faltasTotal : null,
                    'origem' => $media === null ? 'vazia' : 'calculada',
                ];
            }

            $this->model->atualizarCelulasEmLote($updates);
        }

        return ['success' => true, 'atualizadas' => $n];
    }

    /**
     * Trajetória para a tela de histórico vivo.
     *
     * @return array{anos: list<array<string,mixed>>}
     */
    public function trajetoria(int $alunoId): array
    {
        $anos = [];
        foreach ($this->model->listarAnosEscolarizacao($alunoId) as $ano) {
            $ano['componentes'] = $this->model->listarComponentesAno((int) $ano['id']);
            $anos[] = $ano;
        }
        return ['anos' => $anos];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success: bool, id?: int, error?: string}
     */
    public function adicionarAnoExterno(int $alunoId, array $input, array $usuario): array
    {
        $anoLetivo = trim((string) ($input['ano_letivo'] ?? ''));
        $serie = trim((string) ($input['serie_ano'] ?? ''));
        $escola = trim((string) ($input['escola_nome'] ?? ''));
        if ($anoLetivo === '' || $serie === '' || $escola === '') {
            return ['success' => false, 'error' => 'Informe ano letivo, série e escola.'];
        }
        try {
            $anoId = $this->model->criarAnoEscolarizacao([
                'aluno_id' => $alunoId,
                'ano_letivo' => $anoLetivo,
                'serie_ano' => $serie,
                'origem' => 'externo',
                'escola_nome' => $escola,
                'escola_inep' => trim((string) ($input['escola_inep'] ?? '')) ?: null,
                'municipio' => trim((string) ($input['municipio'] ?? '')) ?: null,
                'uf' => strtoupper(substr(trim((string) ($input['uf'] ?? '')), 0, 2)) ?: null,
                'resultado' => trim((string) ($input['resultado'] ?? '')) ?: null,
                'carga_horaria_total' => ($input['carga_horaria_total'] ?? '') !== '' ? (int) $input['carga_horaria_total'] : null,
                'documento_id' => !empty($input['documento_id']) ? (int) $input['documento_id'] : null,
                'observacao' => trim((string) ($input['observacao'] ?? '')) ?: null,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Já existe um ano de escolarização com essa série e origem.'];
        }

        $componentes = $input['componentes'] ?? [];
        if (is_string($componentes)) {
            $componentes = json_decode($componentes, true) ?: [];
        }
        $ordem = 0;
        foreach (is_array($componentes) ? $componentes : [] as $comp) {
            $nome = trim((string) ($comp['componente_original'] ?? $comp['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $ordem++;
            $this->model->criarComponenteEscolarizacao([
                'ano_id' => $anoId,
                'componente_original' => $nome,
                'materia_id' => !empty($comp['materia_id']) ? (int) $comp['materia_id'] : null,
                'nota_original' => trim((string) ($comp['nota_original'] ?? $comp['nota'] ?? '')) ?: null,
                'escala_original' => trim((string) ($comp['escala_original'] ?? '')) ?: null,
                'nota_convertida' => $this->notaOuNull($comp['nota_convertida'] ?? $comp['nota'] ?? null),
                'carga_horaria' => ($comp['carga_horaria'] ?? '') !== '' ? (int) $comp['carga_horaria'] : null,
                'ordem' => $ordem,
            ]);
        }

        return ['success' => true, 'id' => $anoId];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success: bool, id?: int, error?: string}
     */
    public function salvarImportacao(int $alunoId, array $input, array $usuario): array
    {
        $id = (int) ($input['importacao_id'] ?? 0);
        $payload = [
            'anos_anteriores' => $input['anos_anteriores'] ?? [],
            'bimestres_atuais' => $input['bimestres_atuais'] ?? [],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $campos = [
            'escola_origem' => trim((string) ($input['escola_origem'] ?? '')) ?: null,
            'escola_inep' => trim((string) ($input['escola_inep'] ?? '')) ?: null,
            'municipio' => trim((string) ($input['municipio'] ?? '')) ?: null,
            'uf' => strtoupper(substr(trim((string) ($input['uf'] ?? '')), 0, 2)) ?: null,
            'data_transferencia' => $this->dataOuNull($input['data_transferencia'] ?? null),
            'data_entrada' => $this->dataOuNull($input['data_entrada'] ?? null),
            'documento_id' => !empty($input['documento_id']) ? (int) $input['documento_id'] : null,
            'payload_json' => $json,
            'status' => 'em_conferencia',
        ];
        if ($id > 0) {
            $imp = $this->model->findImportacao($id);
            if (!$imp || (int) $imp['aluno_id'] !== $alunoId) {
                return ['success' => false, 'error' => 'Importação não encontrada.'];
            }
            if (($imp['status'] ?? '') === 'validada') {
                return ['success' => false, 'error' => 'Importação já validada.'];
            }
            $this->model->atualizarImportacao($id, $campos);
            return ['success' => true, 'id' => $id];
        }
        $id = $this->model->criarImportacao(array_merge($campos, [
            'aluno_id' => $alunoId,
            'status' => 'rascunho',
            'criado_por' => (int) ($usuario['id'] ?? 0) ?: null,
        ]));
        $this->model->atualizarImportacao($id, ['status' => 'em_conferencia']);
        return ['success' => true, 'id' => $id];
    }

    /**
     * Aplica anos anteriores na escolarização e bimestres do ano na ficha.
     *
     * @return array{success: bool, error?: string, resumo?: array<string,int>}
     */
    public function validarImportacao(int $importacaoId, array $usuario): array
    {
        $imp = $this->model->findImportacao($importacaoId);
        if (!$imp) {
            return ['success' => false, 'error' => 'Importação não encontrada.'];
        }
        if (($imp['status'] ?? '') === 'validada') {
            return ['success' => true];
        }
        $payload = json_decode((string) ($imp['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            return ['success' => false, 'error' => 'Não há dados conferidos para validar.'];
        }
        $alunoId = (int) $imp['aluno_id'];
        $escola = (string) ($imp['escola_origem'] ?? 'Escola de origem');
        $anos = is_array($payload['anos_anteriores'] ?? null) ? $payload['anos_anteriores'] : [];
        $bims = is_array($payload['bimestres_atuais'] ?? null) ? $payload['bimestres_atuais'] : [];
        if ($anos === [] && $bims === []) {
            return ['success' => false, 'error' => 'Inclua ao menos um ano anterior ou um bimestre do ano atual.'];
        }

        $nAnos = 0;
        foreach ($anos as $ano) {
            $res = $this->adicionarAnoExterno($alunoId, array_merge($ano, [
                'escola_nome' => $ano['escola_nome'] ?? $escola,
                'escola_inep' => $imp['escola_inep'],
                'municipio' => $imp['municipio'],
                'uf' => $imp['uf'],
                'documento_id' => $imp['documento_id'],
            ]), $usuario);
            if (!empty($res['success'])) {
                $nAnos++;
            }
        }

        $nCel = 0;
        $aluno = $this->model->alunoPorId($alunoId);
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        $anoAtual = (int) ($aluno['turma_ano_letivo'] ?? date('Y'));
        if ($bims !== [] && $turmaId > 0) {
            $g = $this->garantirFicha($alunoId, $turmaId, $anoAtual, $usuario['id'] ?? null);
            if (!empty($g['success'])) {
                $nCel = $this->aplicarBimestresExternos((int) $g['id'], $bims, $escola, (int) ($imp['documento_id'] ?? 0), $usuario);
            }
        }

        $resumo = ['anos_anteriores' => $nAnos, 'celulas_externas' => $nCel];
        $this->model->atualizarImportacao($importacaoId, [
            'status' => 'validada',
            'validada_por' => (int) ($usuario['id'] ?? 0) ?: null,
            'validada_em' => date('Y-m-d H:i:s'),
            'resumo_json' => json_encode($resumo, JSON_UNESCAPED_UNICODE),
        ]);
        return ['success' => true, 'resumo' => $resumo];
    }

    /**
     * Itens no formato do histórico oficial (emissão).
     *
     * @return list<array<string,mixed>>
     */
    public function itensParaHistoricoOficial(int $alunoId): array
    {
        $out = [];
        foreach ($this->model->listarAnosEscolarizacao($alunoId) as $ano) {
            $comps = $this->model->listarComponentesAno((int) $ano['id']);
            foreach ($comps as $c) {
                $nota = $c['nota_convertida'] ?? $c['nota_original'] ?? null;
                $out[] = [
                    'ano_letivo' => (string) $ano['ano_letivo'],
                    'serie_ano' => (string) $ano['serie_ano'],
                    'componente' => (string) $c['componente_original'],
                    'materia_id' => $c['materia_id'] ?? null,
                    'resultado_valor' => $nota !== null && $nota !== '' ? (string) $nota : null,
                    'carga_horaria' => $c['carga_horaria'] ?? null,
                    'frequencia_percentual' => $c['frequencia_percentual'] ?? null,
                    'origem' => (($ano['origem'] ?? '') === 'interno') ? 'Interno' : 'Externo',
                    'escola_origem' => $ano['escola_nome'] ?? null,
                    '_resultado_ano' => $this->mapearResultadoHistorico((string) ($ano['resultado'] ?? '')),
                ];
            }
        }
        return $out;
    }

    private function aplicarBimestresExternos(int $fichaId, array $bims, string $escola, int $documentoId, array $usuario): int
    {
        $linhas = $this->model->listarLinhas($fichaId);
        $porNome = [];
        $porId = [];
        foreach ($linhas as $l) {
            $porNome[mb_strtolower(trim((string) $l['componente_nome']))] = $l;
            $mid = (int) ($l['materia_id'] ?? 0);
            if ($mid > 0) {
                $porId[$mid] = $l;
            }
        }
        $n = 0;
        foreach ($bims as $item) {
            $nome = mb_strtolower(trim((string) ($item['componente'] ?? $item['componente_original'] ?? '')));
            $mid = (int) ($item['materia_id'] ?? 0);
            $linha = ($mid > 0 ? ($porId[$mid] ?? null) : null) ?? ($porNome[$nome] ?? null);
            if (!$linha) {
                continue;
            }
            $periodo = (int) ($item['periodo_numero'] ?? $item['bimestre'] ?? 0);
            if ($periodo < 1 || $periodo > 4) {
                continue;
            }
            $cel = $this->model->findCelulaLinhaPeriodo((int) $linha['id'], $periodo);
            if (!$cel) {
                continue;
            }
            if (in_array($cel['status'] ?? '', ['fechada', 'homologada'], true) && ($cel['origem'] ?? '') !== 'vazia') {
                continue;
            }
            $nota = $this->notaOuNull($item['nota'] ?? $item['nota_convertida'] ?? null);
            $this->model->atualizarCelula((int) $cel['id'], [
                'nota' => $nota,
                'conceito' => trim((string) ($item['conceito'] ?? '')) ?: null,
                'faltas' => ($item['faltas'] ?? '') !== '' ? (int) $item['faltas'] : null,
                'origem' => 'externa',
                'status' => 'fechada',
                'escola_origem' => $escola,
                'documento_id' => $documentoId > 0 ? $documentoId : null,
                'nota_original' => trim((string) ($item['nota_original'] ?? $item['nota'] ?? '')) ?: null,
                'escala_original' => trim((string) ($item['escala_original'] ?? '')) ?: null,
            ]);
            $this->recalcularFinal((int) $linha['id']);
            $this->model->registrarAuditoria([
                'ficha_id' => $fichaId,
                'celula_id' => (int) $cel['id'],
                'acao' => 'importar_externa',
                'valor_novo' => json_encode(['nota' => $nota, 'escola' => $escola], JSON_UNESCAPED_UNICODE),
                'usuario_id' => (int) ($usuario['id'] ?? 0) ?: null,
                'usuario_nome' => $usuario['nome'] ?? null,
            ]);
            $n++;
        }
        return $n;
    }

    private function sincronizarEscolarizacaoInterna(int $fichaId): void
    {
        $ficha = $this->model->findFicha($fichaId);
        if (!$ficha) {
            return;
        }
        $alunoId = (int) $ficha['aluno_id'];
        $anoLetivo = (string) (int) $ficha['ano_letivo'];
        $serie = (string) ($ficha['serie_nome'] ?: $ficha['turma_serie'] ?: 'Série');
        $existente = null;
        foreach ($this->model->listarAnosEscolarizacao($alunoId) as $a) {
            if ((string) $a['ano_letivo'] === $anoLetivo && ($a['origem'] ?? '') === 'interno') {
                $existente = $a;
                break;
            }
        }
        $escola = '';
        try {
            $cfg = $this->db->fetch("SELECT valor FROM configuracoes WHERE chave IN ('school_name','nome_escola') LIMIT 1");
            $escola = is_array($cfg) ? (string) ($cfg['valor'] ?? '') : '';
        } catch (\Throwable $e) {
            $escola = '';
        }
        $resultado = $this->resultadoDaFicha($ficha);
        if ($existente) {
            $this->model->atualizarAnoEscolarizacao((int) $existente['id'], [
                'resultado' => $resultado,
                'ficha_id' => $fichaId,
            ]);
            return;
        }
        $anoId = $this->model->criarAnoEscolarizacao([
            'aluno_id' => $alunoId,
            'ano_letivo' => $anoLetivo,
            'serie_ano' => $serie,
            'origem' => 'interno',
            'escola_nome' => $escola !== '' ? $escola : 'Esta instituição',
            'ficha_id' => $fichaId,
            'resultado' => $resultado,
        ]);
        $ordem = 0;
        foreach ($this->quadro($fichaId)['grid'] ?? [] as $row) {
            $final = $row['celulas'][0] ?? null;
            $nota = is_array($final) ? ($final['nota'] ?? null) : null;
            $ordem++;
            $this->model->criarComponenteEscolarizacao([
                'ano_id' => $anoId,
                'componente_original' => (string) ($row['linha']['componente_nome'] ?? ''),
                'materia_id' => $row['linha']['materia_id'] ?? null,
                'nota_convertida' => $nota,
                'nota_original' => $nota !== null ? (string) $nota : null,
                'carga_horaria' => $row['linha']['carga_horaria'] ?? null,
                'ordem' => $ordem,
            ]);
        }
    }

    private function recalcularFinal(int $linhaId, ?array $ficha = null): void
    {
        $porBim = [];
        $faltasTotal = 0;
        $temFaltas = false;
        foreach ([1, 2, 3, 4] as $p) {
            $c = $this->model->findCelulaLinhaPeriodo($linhaId, $p);
            if ($c && is_numeric($c['nota'] ?? null)) {
                $porBim[$p] = (float) $c['nota'];
            }
            if ($c && $c['faltas'] !== null && $c['faltas'] !== '') {
                $faltasTotal += (int) $c['faltas'];
                $temFaltas = true;
            }
        }
        $final = $this->model->findCelulaLinhaPeriodo($linhaId, 0);
        if (!$final) {
            return;
        }
        if (in_array($final['status'] ?? '', ['homologada'], true)) {
            return;
        }
        if (in_array($final['status'] ?? '', ['fechada'], true) && ($final['origem'] ?? '') === 'externa') {
            return;
        }
        if ($ficha === null) {
            $linha = $this->model->findLinha($linhaId);
            $fichaId = (int) ($linha['ficha_id'] ?? 0);
            $ficha = $fichaId > 0 ? $this->model->findFicha($fichaId) : null;
        }
        $materiaId = 0;
        if ($ficha) {
            $linha = $this->model->findLinha($linhaId);
            $materiaId = (int) ($linha['materia_id'] ?? 0);
        }
        $media = $this->mediaFinalDaLinha($porBim, is_array($ficha) ? $ficha : [], $materiaId);
        $this->model->atualizarCelula((int) $final['id'], [
            'nota' => $media,
            'faltas' => $temFaltas ? $faltasTotal : null,
            'origem' => $media === null ? 'vazia' : 'calculada',
        ]);
    }

    /**
     * @param array<int, float> $porBim
     * @param array<string,mixed> $ficha
     */
    private function mediaFinalDaLinha(array $porBim, array $ficha, int $materiaId): ?float
    {
        if ($porBim === []) {
            return null;
        }
        $mediaSimples = round(array_sum($porBim) / count($porBim), 2);
        $regra = $this->regraAcademicaDaFicha($ficha, $materiaId);
        $formula = trim((string) ($regra['formula_media'] ?? ''));
        if ($formula === '' || preg_match('/^\s*\(\s*B1\s*\+\s*B2\s*\+\s*B3\s*\+\s*B4\s*\)\s*\/\s*4\s*$/i', $formula)) {
            return $mediaSimples;
        }
        $valores = [];
        foreach ([1, 2, 3, 4] as $p) {
            if (isset($porBim[$p])) {
                $valores['B' . $p] = $porBim[$p];
                $valores['b' . $p] = $porBim[$p];
            }
        }
        $tokens = [];
        if (preg_match_all('/\bB([1-4])\b/i', $formula, $m)) {
            foreach ($m[1] as $n) {
                $tokens[(int) $n] = true;
            }
        }
        foreach (array_keys($tokens) as $p) {
            if (!isset($porBim[$p])) {
                return $mediaSimples;
            }
        }
        try {
            $avaliado = $this->motor()->avaliarFormula($formula, $valores);
            if (!empty($avaliado['ok']) && isset($avaliado['valor']) && is_numeric($avaliado['valor'])) {
                return round((float) $avaliado['valor'], 2);
            }
        } catch (\Throwable $e) {
            error_log('VidaEscolar formula_media: ' . $e->getMessage());
        }
        return $mediaSimples;
    }

    /**
     * @param array<string,mixed> $ficha
     * @return array<string,mixed>|null
     */
    private function regraAcademicaDaFicha(array $ficha, int $materiaId = 0): ?array
    {
        if ($ficha === []) {
            return null;
        }
        $chave = (int) ($ficha['id'] ?? 0) . ':' . $materiaId;
        if (array_key_exists($chave, $this->regraAcadCache)) {
            return $this->regraAcadCache[$chave];
        }
        $turmaId = (int) ($ficha['turma_id'] ?? 0);
        $turma = $turmaId > 0 ? $this->model->turmaPorId($turmaId) : null;
        $contexto = [
            'ano_letivo' => (int) ($ficha['ano_letivo'] ?? 0),
            'curso_id' => (int) ($turma['curso_novo_id'] ?? $turma['curso_id'] ?? 0) ?: null,
            'serie_id' => (int) ($turma['serie_id'] ?? 0) ?: null,
            'matriz_curricular_id' => (int) ($turma['matriz_curricular_id'] ?? $ficha['matriz_curricular_id'] ?? 0) ?: null,
            'materia_id' => $materiaId > 0 ? $materiaId : null,
            'periodo_tipo' => 'bimestre',
        ];
        try {
            $this->regraAcadCache[$chave] = $this->motor()->resolverRegra($contexto);
        } catch (\Throwable $e) {
            $this->regraAcadCache[$chave] = null;
        }
        return $this->regraAcadCache[$chave];
    }

    /**
     * @param array<string,mixed> $ficha
     */
    private function resultadoDaFicha(array $ficha): string
    {
        $notas = [];
        $quadro = $this->quadro((int) $ficha['id']);
        foreach (is_array($quadro['grid'] ?? null) ? $quadro['grid'] : [] as $row) {
            $final = $row['celulas'][0] ?? null;
            if (is_array($final) && is_numeric($final['nota'] ?? null)) {
                $notas[] = (float) $final['nota'];
            }
        }
        $media = $notas === [] ? null : round(array_sum($notas) / count($notas), 2);
        $regra = $this->regraAcademicaDaFicha($ficha);
        try {
            $avaliado = $this->motor()->avaliar([
                'media' => $media,
                'tem_nota' => $media !== null,
                'aluno_id' => (int) ($ficha['aluno_id'] ?? 0),
                'turma_id' => (int) ($ficha['turma_id'] ?? 0),
            ], $regra ?? []);
        } catch (\Throwable $e) {
            return $media === null ? 'Em andamento' : 'Aprovado';
        }
        $sit = (string) ($avaliado['situacao'] ?? '');
        $map = [
            'aprovado' => 'Aprovado',
            'aprovado_recuperacao' => 'Aprovado',
            'aprovado_conselho' => 'Aprovado',
            'reprovado_rendimento' => 'Reprovado',
            'reprovado_frequencia' => 'Reprovado',
            'recuperacao' => 'Recuperação',
            'exame_final' => 'Exame final',
            'progressao_parcial' => 'Progressão parcial',
            'dependencia' => 'Dependência',
            'transferido' => 'Transferido',
            'desistente' => 'Desistente',
            'nao_avaliado' => 'Não avaliado',
            'resultado_pendente' => 'Resultado pendente',
            'em_andamento' => 'Em andamento',
        ];
        if (isset($map[$sit])) {
            return $map[$sit];
        }
        $rotulo = trim((string) ($avaliado['rotulo'] ?? ''));
        return $rotulo !== '' ? $rotulo : 'Em andamento';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int, array{nota:?float, faltas:?int}>
     */
    private function periodosDaLinhaGerada(array $row): array
    {
        $notas = json_decode((string) ($row['notas_json'] ?? ''), true);
        $notas = is_array($notas) ? $notas : [];
        $notasLower = [];
        foreach ($notas as $nk => $nv) {
            $notasLower[strtolower((string) $nk)] = $nv;
        }
        $colunas = json_decode((string) ($row['colunas_json'] ?? ''), true);
        $colunas = is_array($colunas) ? $colunas : [];
        $out = [];
        $bimEvento = (int) ($row['bimestre'] ?? 0);
        if ($bimEvento < 1 || $bimEvento > 4) {
            $bimEvento = $this->bimestreDePeriodoRef((string) ($row['periodo_ref'] ?? '')) ?? 0;
        }
        $aplicar = static function (int $periodo, ?float $nota, ?int $faltas) use (&$out): void {
            if ($periodo < 1 || $periodo > 4) {
                return;
            }
            if (!isset($out[$periodo])) {
                $out[$periodo] = ['nota' => null, 'faltas' => null];
            }
            if ($nota !== null) {
                $out[$periodo]['nota'] = $nota;
            }
            if ($faltas !== null) {
                $out[$periodo]['faltas'] = $faltas;
            }
        };
        $periodoDe = static function (string $grupo, string $codigo): ?int {
            $grupo = strtolower(trim($grupo));
            $codigo = strtolower(trim($codigo));
            if (preg_match('/^b([1-4])$/', $grupo, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/^b([1-4])([_\s]|$)/', $codigo, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/(?:^|_)b([1-4])(?:_|$)/', $codigo, $m)) {
                return (int) $m[1];
            }
            return null;
        };

        foreach ($colunas as $col) {
            if (!is_array($col)) {
                continue;
            }
            $codOrig = trim((string) ($col['codigo'] ?? ''));
            if ($codOrig === '') {
                continue;
            }
            $cod = strtolower($codOrig);
            $tipo = strtolower(trim((string) ($col['layout_type'] ?? '')));
            $grupo = strtolower(trim((string) ($col['layout_group'] ?? '')));
            if (in_array($tipo, ['rec', 'resultado', 'semana_nq', 'media_sem', 'n', 'q'], true)) {
                continue;
            }
            $ehFalta = $tipo === 'faltas' || str_contains($cod, 'falt');
            $periodo = $periodoDe($grupo, $cod);
            if ($periodo === null && $ehFalta && $bimEvento >= 1 && $bimEvento <= 4) {
                $periodo = $bimEvento;
            }
            if ($periodo === null) {
                continue;
            }
            $val = $notas[$codOrig] ?? $notasLower[$cod] ?? null;
            if (!is_numeric($val)) {
                continue;
            }
            $ehMedia = $tipo === 'media' || str_contains($cod, 'media');
            if ($ehFalta) {
                $aplicar($periodo, null, (int) round((float) $val));
            } elseif ($ehMedia || ($tipo === '' && !str_contains($cod, 'rec') && !str_contains($cod, 'result'))) {
                $aplicar($periodo, (float) $val, null);
            }
        }

        foreach ([1, 2, 3, 4] as $b) {
            foreach (['b' . $b . '_media', 'media_b' . $b, 'b' . $b] as $k) {
                if (isset($notasLower[$k]) && is_numeric($notasLower[$k])) {
                    $aplicar($b, (float) $notasLower[$k], null);
                }
            }
            foreach (['b' . $b . '_faltas', 'faltas_b' . $b] as $k) {
                if (isset($notasLower[$k]) && is_numeric($notasLower[$k])) {
                    $aplicar($b, null, (int) round((float) $notasLower[$k]));
                }
            }
        }

        if ($bimEvento >= 1 && $bimEvento <= 4 && ($out[$bimEvento]['faltas'] ?? null) === null) {
            foreach (['faltas', 'faltas_bim'] as $k) {
                if (isset($notasLower[$k]) && is_numeric($notasLower[$k])) {
                    $aplicar($bimEvento, null, (int) round((float) $notasLower[$k]));
                    break;
                }
            }
        }

        $temNotaBim = false;
        foreach ($out as $vals) {
            if (($vals['nota'] ?? null) !== null) {
                $temNotaBim = true;
                break;
            }
        }
        if (!$temNotaBim) {
            $media = null;
            if (is_numeric($row['media_final'] ?? null)) {
                $media = (float) $row['media_final'];
            } else {
                foreach (['media_final', 'media_bim', 'media'] as $k) {
                    if (isset($notasLower[$k]) && is_numeric($notasLower[$k])) {
                        $media = (float) $notasLower[$k];
                        break;
                    }
                }
            }
            if ($bimEvento >= 1 && $bimEvento <= 4 && $media !== null) {
                $aplicar($bimEvento, $media, null);
            }
        }

        return $out;
    }

    private function faltasLancadasAlunoMateria(int $alunoId, int $materiaId, int $bimestre, int $anoLetivo): ?int
    {
        if ($alunoId <= 0 || $bimestre < 1 || $bimestre > 4 || $anoLetivo < 2000) {
            return null;
        }
        $cacheKey = $anoLetivo . ':' . $bimestre;
        if (!isset($this->faltasLancadasCache[$cacheKey])) {
            $this->faltasLancadasCache[$cacheKey] = $this->carregarFaltasLancadas($anoLetivo, $bimestre);
        }
        $map = $this->faltasLancadasCache[$cacheKey];
        $porMateria = $alunoId . '_' . $materiaId;
        if ($materiaId > 0 && array_key_exists($porMateria, $map)) {
            return $map[$porMateria];
        }
        $legado = $alunoId . '_0';
        if (array_key_exists($legado, $map)) {
            return $map[$legado];
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function carregarFaltasLancadas(int $anoLetivo, int $bimestre): array
    {
        $path = dirname(__DIR__, 3) . '/Models/Education/SchoolAbsence.php';
        if (!class_exists('SchoolAbsence', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('SchoolAbsence', false)) {
            return [];
        }
        $absence = new \SchoolAbsence();
        $eventoId = $absence->idEventoPorAnoBimestre($anoLetivo, $bimestre);
        if ($eventoId <= 0) {
            return [];
        }
        $out = [];
        foreach ($absence->getLancamentosMapByEvento($eventoId) as $chave => $item) {
            if (!is_array($item) || !is_numeric($item['faltas'] ?? null)) {
                continue;
            }
            $out[(string) $chave] = (int) round((float) $item['faltas']);
        }

        return $out;
    }

    private function aplicarCelulaCalculada(
        int $linhaId,
        int $periodo,
        ?float $nota,
        ?int $faltas,
        bool $incluirReabertas = false
    ): bool {
        if ($nota === null && $faltas === null) {
            return false;
        }
        $cel = $this->model->findCelulaLinhaPeriodo($linhaId, $periodo);
        if (!$cel) {
            $this->model->criarCelula([
                'linha_id' => $linhaId,
                'periodo_numero' => $periodo,
                'origem' => 'vazia',
                'status' => 'aberta',
            ]);
            $cel = $this->model->findCelulaLinhaPeriodo($linhaId, $periodo);
        }
        if (!$cel) {
            return false;
        }
        $status = (string) ($cel['status'] ?? '');
        $permitidos = $incluirReabertas ? ['aberta', 'reaberta'] : ['aberta'];
        if (!in_array($status, $permitidos, true)) {
            return false;
        }
        if (($cel['origem'] ?? '') === 'externa') {
            return false;
        }
        $campos = ['origem' => 'calculada'];
        if ($nota !== null) {
            $campos['nota'] = round($nota, 2);
        }
        if ($faltas !== null) {
            $campos['faltas'] = $faltas;
        }
        $this->model->atualizarCelula((int) $cel['id'], $campos);
        return true;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function eventoPertenceAoAno(array $row, int $anoFicha): bool
    {
        $anoRow = (int) ($row['ano_letivo'] ?? 0);
        if ($anoRow > 0) {
            return $anoRow === $anoFicha;
        }
        $ref = (string) ($row['periodo_ref'] ?? '');
        if (preg_match('/(20\d{2})/', $ref, $m)) {
            return (int) $m[1] === $anoFicha;
        }
        return true;
    }

    public function bimestreDePeriodoRef(?string $ref): ?int
    {
        $ref = strtoupper(trim((string) $ref));
        if ($ref === '') {
            return null;
        }
        if (preg_match('/B\s*([1-4])/', $ref, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(^|[^0-9])([1-4])([^0-9]|$)/', $ref, $m)) {
            return (int) $m[2];
        }
        return null;
    }

    public function bimestreAtual(): int
    {
        $mes = (int) date('n');
        if ($mes <= 3) {
            return 1;
        }
        if ($mes <= 6) {
            return 2;
        }
        if ($mes <= 9) {
            return 3;
        }
        return 4;
    }

    private function mapearResultadoHistorico(string $resultado): string
    {
        $r = mb_strtolower(trim($resultado));
        if (str_contains($r, 'reten') || str_contains($r, 'reprov')) {
            return 'Retido';
        }
        if (str_contains($r, 'transf')) {
            return 'Transferido';
        }
        if (str_contains($r, 'conselho')) {
            return 'Aprovado_Conselho';
        }
        if ($r === '' || str_contains($r, 'curs')) {
            return 'Cursando';
        }
        return 'Aprovado';
    }

    private function notaOuNull($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v)) {
            $v = str_replace(',', '.', trim($v));
        }
        return is_numeric($v) ? round((float) $v, 2) : null;
    }

    private function dataOuNull($v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
