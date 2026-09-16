<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../Models/VidaEscolar.php';
require_once __DIR__ . '/../../../Services/ResultadoAcademicoService.php';
require_once __DIR__ . '/../../../Models/Education/ComponenteCurricular.php';
require_once __DIR__ . '/../../boletins/Services/BoletimCadastroService.php';

use App\Modulos\Boletins\Services\BoletimCadastroService;
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
    /** @var array<string, bool> chave ano:bimestre — evento de faltas cadastrado */
    private array $faltasEventoExisteCache = [];
    /** @var array<string, array<string, int>|false> false = sem tabela diário; chave ano:bimestre */
    private array $faltasDiarioCache = [];
    /** @var array<string, list<array{label:string,materias_ids:list<int>}>> */
    private array $gruposLinhaCache = [];
    /** @var array<int, int>|null */
    private ?array $paiPorFilhoCache = null;
    private ?BoletimCadastroService $boletimCadastro = null;
    /** @var array<string, array<string,mixed>|null> */
    private array $modeloBoletimCache = [];

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
            $this->alinharFichaAoModelo($existente);
            return ['success' => true, 'id' => (int) $existente['id'], 'criada' => false];
        }

        $turma = $this->model->turmaPorId($turmaId);
        $matricula = $this->model->findMatriculaDaTurma($alunoId, $turmaId);
        $fichaId = $this->model->criarFicha([
            'aluno_id' => $alunoId,
            'turma_id' => $turmaId,
            'matricula_id' => $matricula['id'] ?? null,
            'ano_letivo' => $anoLetivo,
            'serie_nome' => $turma['serie'] ?? null,
            'status' => 'em_curso',
        ]);

        $componentes = $this->componentesParaNovaFicha($turmaId, $anoLetivo, $alunoId);
        $jaTemMateria = [];
        foreach ($this->model->listarLinhas($fichaId) as $linhaExistente) {
            $midExistente = (int) ($linhaExistente['materia_id'] ?? 0);
            if ($midExistente > 0) {
                $jaTemMateria[$midExistente] = true;
            }
        }
        $ordem = 0;
        foreach ($componentes as $comp) {
            $materiaId = (int) ($comp['materia_id'] ?? 0);
            if ($materiaId > 0 && isset($jaTemMateria[$materiaId])) {
                continue;
            }
            $ordem++;
            $linhaId = $this->model->criarLinha([
                'ficha_id' => $fichaId,
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'componente_nome' => (string) ($comp['componente_nome'] ?? 'Componente'),
                'carga_horaria' => null,
                'ordem' => $ordem,
            ]);
            if ($materiaId > 0) {
                $jaTemMateria[$materiaId] = true;
            }
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
     * Se o aluno já tem boletim gerado ou resultado homologado e ainda não
     * tem ficha na Vida escolar, cria, preenche e homologa a ficha do ano.
     *
     * @param array<string,mixed>|null $usuario
     * @return array{success:bool,id?:int,criada?:bool,error?:string}
     */
    public function materializarFichaOficialSeFaltar(int $alunoId, ?array $usuario = null): array
    {
        if (!$this->model->schemaPronto() || $alunoId <= 0) {
            return ['success' => false, 'error' => 'Migration da vida escolar ainda não foi aplicada.'];
        }
        $aluno = $this->model->alunoPorId($alunoId);
        if (!$aluno) {
            return ['success' => false, 'error' => 'Aluno não encontrado.'];
        }
        $ctx = $this->resolverContextoFicha($alunoId, $aluno);
        if ($ctx === null) {
            return ['success' => false, 'error' => 'Aluno sem turma ou ano letivo.'];
        }

        $usuario = is_array($usuario) ? $usuario : [];
        $existente = $this->model->findFichaAlunoAno($alunoId, $ctx['ano_letivo'], $ctx['turma_id']);
        if ($existente) {
            $fichaId = (int) $existente['id'];
            $temHomolog = $this->alunoTemResultadoHomologado($alunoId, $ctx['turma_id'], $ctx['ano_letivo']);
            if ($this->fichaSemNotas($fichaId) || $this->fichaSemFaltasNasNotas($fichaId)) {
                $this->sincronizarDeEventosGerados($alunoId, $usuario, null, null, $fichaId, false, false);
                if ($temHomolog) {
                    $this->homologarFicha($fichaId, $usuario);
                }
            }
            return ['success' => true, 'id' => $fichaId, 'criada' => false];
        }

        $temGerados = $this->model->listarResultadosGeradosOficiais($alunoId) !== [];
        $temHomolog = $this->alunoTemResultadoHomologado($alunoId, $ctx['turma_id'], $ctx['ano_letivo']);
        if (!$temGerados && !$temHomolog) {
            return ['success' => true, 'id' => 0, 'criada' => false];
        }

        $ok = $this->garantirFicha(
            $alunoId,
            $ctx['turma_id'],
            $ctx['ano_letivo'],
            isset($usuario['id']) ? (int) $usuario['id'] : null
        );
        if (empty($ok['success'])) {
            return $ok;
        }
        $fichaId = (int) ($ok['id'] ?? 0);
        $this->sincronizarDeEventosGerados($alunoId, $usuario, null, null, $fichaId, false, false);
        if ($temHomolog) {
            $this->homologarFicha($fichaId, $usuario);
        }

        return ['success' => true, 'id' => $fichaId, 'criada' => !empty($ok['criada'])];
    }

    /**
     * Regrava notas e faltas da ficha a partir dos eventos oficiais (seed/demo).
     *
     * @param array<string,mixed> $usuario
     * @return array{success:bool,id?:int,error?:string}
     */
    public function reescreverFichaDeEventos(int $fichaId, array $usuario = []): array
    {
        $ficha = $this->model->findFicha($fichaId);
        if (!$ficha) {
            return ['success' => false, 'error' => 'Ficha não encontrada.'];
        }
        $alunoId = (int) ($ficha['aluno_id'] ?? 0);
        $this->model->atualizarFicha($fichaId, ['status' => 'em_curso']);
        foreach ($this->model->listarCelulas($fichaId) as $c) {
            if (($c['origem'] ?? '') === 'externa') {
                continue;
            }
            $this->model->atualizarCelula((int) $c['id'], ['status' => 'aberta']);
        }
        $ok = $this->sincronizarDeEventosGerados($alunoId, $usuario, null, null, $fichaId, false, true, true);
        if (empty($ok['success'])) {
            return $ok;
        }
        $this->homologarFicha($fichaId, $usuario);
        return ['success' => true, 'id' => $fichaId];
    }

    /**
     * @param array<string,mixed> $aluno
     * @return array{turma_id:int,ano_letivo:int}|null
     */
    private function resolverContextoFicha(int $alunoId, array $aluno): ?array
    {
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        $ano = (int) ($aluno['turma_ano_letivo'] ?? 0);
        if ($turmaId > 0 && $ano > 0) {
            return ['turma_id' => $turmaId, 'ano_letivo' => $ano];
        }
        try {
            $mat = $this->db->fetch(
                "SELECT m.turma_id, al.ano AS ano_letivo
                 FROM matricula m
                 INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                 WHERE m.aluno_id = :aid
                 ORDER BY (m.status = 'ativa') DESC, al.ano DESC, m.id DESC
                 LIMIT 1",
                ['aid' => $alunoId]
            );
            $tid = (int) ($mat['turma_id'] ?? 0);
            $anoM = (int) ($mat['ano_letivo'] ?? 0);
            if ($tid > 0 && $anoM > 0) {
                return ['turma_id' => $tid, 'ano_letivo' => $anoM];
            }
        } catch (\Throwable $e) {
            // matrícula ainda não migrada
        }
        return null;
    }

    private function alunoTemResultadoHomologado(int $alunoId, int $turmaId, int $anoLetivo): bool
    {
        try {
            $tem = $this->db->fetch("SHOW TABLES LIKE 'resultado_academico'");
            if (!$tem) {
                return false;
            }
            $row = $this->db->fetch(
                "SELECT id FROM resultado_academico
                  WHERE aluno_id = :aid AND turma_id = :tid AND ano_letivo = :ano
                    AND periodo_tipo = 'ano' AND status = 'homologado'
                  LIMIT 1",
                ['aid' => $alunoId, 'tid' => $turmaId, 'ano' => $anoLetivo]
            );
            return is_array($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function fichaSemNotas(int $fichaId): bool
    {
        foreach ($this->model->listarCelulas($fichaId) as $c) {
            if (isset($c['nota']) && $c['nota'] !== null && $c['nota'] !== '') {
                return false;
            }
        }
        return true;
    }

    /** Célula com nota e coluna de falta ainda vazia (ex.: JSON gerado com faltas:null). */
    private function fichaSemFaltasNasNotas(int $fichaId): bool
    {
        foreach ($this->model->listarCelulas($fichaId) as $c) {
            $periodo = (int) ($c['periodo_numero'] ?? -1);
            if ($periodo < 1 || $periodo > 4) {
                continue;
            }
            $temNota = isset($c['nota']) && $c['nota'] !== null && $c['nota'] !== '';
            if (!$temNota) {
                continue;
            }
            if (!isset($c['faltas']) || $c['faltas'] === null || $c['faltas'] === '') {
                return true;
            }
        }
        return false;
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
        $this->alinharFichaAoModelo($ficha);
        $linhas = $this->model->listarLinhas($fichaId);
        $celulas = $this->model->listarCelulas($fichaId);
        $porLinha = [];
        foreach ($celulas as $c) {
            $lid = (int) $c['linha_id'];
            $porLinha[$lid][(int) $c['periodo_numero']] = $c;
        }
        $idsOcultos = $this->materiaIdsOcultosPorAgrupamento(
            (int) ($ficha['aluno_id'] ?? 0),
            (int) ($ficha['ano_letivo'] ?? 0),
            $linhas,
            $porLinha
        );
        $linhas = $this->linhasUnicasDoQuadro($ficha, $linhas, $idsOcultos);
        $modelo = $this->modeloOficialDaFicha($ficha);
        $bimsGerados = $this->bimestresGeradosPorMateria(
            (int) ($ficha['aluno_id'] ?? 0),
            (int) ($ficha['ano_letivo'] ?? 0),
            (int) ($modelo['id'] ?? 0)
        );
        $fichaCriadaTs = strtotime((string) ($ficha['created_at'] ?? '')) ?: 0;
        $grid = [];
        foreach ($linhas as $l) {
            $lid = (int) $l['id'];
            $mid = (int) ($l['materia_id'] ?? 0);
            $orfao = $fichaCriadaTs > 0 && strtotime((string) ($l['created_at'] ?? '')) > 0
                && strtotime((string) $l['created_at']) < ($fichaCriadaTs - 5);
            $grid[] = [
                'linha' => $l,
                'celulas' => $this->celulasQuadroVisiveis(
                    $porLinha[$lid] ?? [],
                    $mid,
                    $bimsGerados,
                    $orfao
                ),
            ];
        }

        return [
            'ficha' => $ficha,
            'grid' => $grid,
            'periodos' => self::PERIODOS,
            'auditoria' => $this->model->listarAuditoria($fichaId, 40),
            'modelo_nome' => is_array($modelo) ? (string) ($modelo['nome'] ?? '') : '',
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

    public function sincronizarEscolarizacaoDaFicha(int $fichaId): void
    {
        $this->sincronizarEscolarizacaoInterna($fichaId);
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
        bool $incluirReabertas = false,
        bool $forcarEscrita = false
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

        if (!is_array($ficha)) {
            return ['success' => false, 'error' => 'Ficha não encontrada para este aluno.'];
        }
        if (($ficha['status'] ?? '') === 'homologada' && !$forcarEscrita) {
            return ['success' => true, 'atualizadas' => 0];
        }

        $this->alinharFichaAoModelo($ficha);
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
        $modeloBoletimId = (int) (($this->modeloOficialDaFicha($ficha) ?? [])['id'] ?? 0);
        foreach ($this->model->listarResultadosGeradosOficiais($alunoId) as $row) {
            if (!$this->eventoPertenceAoAno($row, $ano)) {
                continue;
            }
            if (!$this->resultadoPertenceAoModelo($row, $modeloBoletimId)) {
                continue;
            }
            $mid = (int) ($row['materia_id'] ?? 0);
            $nomeExibir = trim((string) ($row['materia_nome'] ?? ''));
            $linha = $this->resolverLinhaFichaParaResultado(
                (int) $fichaId,
                $mid,
                $nomeExibir,
                $porId,
                $porNome,
                $linhas,
                $alunoId,
                $ano,
                (int) ($row['ordem_linha'] ?? 0)
            );
            if (!$linha) {
                continue;
            }
            if ($this->resultadoEhDesdobramentoDaLinha($mid, $nomeExibir, $linha)) {
                continue;
            }
            if ((int) ($linha['id'] ?? 0) > 0 && !$this->linhaEstaNaLista($linhas, (int) $linha['id'])) {
                $linhas[] = $linha;
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
                if ($faltas === null && $mid > 0) {
                    $faltas = $this->faltasLancadasAlunoMateria($alunoId, $mid, $periodo, $ano);
                }
                if ($this->aplicarCelulaCalculada(
                    $linhaId,
                    $periodo,
                    $vals['nota'] ?? null,
                    $faltas,
                    $incluirReabertas,
                    $forcarEscrita
                )) {
                    $n++;
                    $linhasTocadas[$linhaId] = true;
                }
            }
        }

        $linhas = $this->model->listarLinhas((int) $fichaId);
        $n += $this->limparBimsSemEventoNaFicha(
            $linhas,
            $this->bimestresGeradosPorMateria($alunoId, $ano, $modeloBoletimId),
            $linhasTocadas,
            $incluirReabertas,
            $forcarEscrita
        );

        foreach (array_keys($linhasTocadas) as $linhaId) {
            $this->recalcularFinal((int) $linhaId, is_array($ficha) ? $ficha : null, $forcarEscrita);
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
                $modeloBoletimId = (int) (($this->modeloOficialDaFicha($ficha) ?? [])['id'] ?? 0);
                foreach ($resultadosPorAluno[$aid] ?? [] as $row) {
                    if (!$this->eventoPertenceAoAno($row, (int) $ano)) {
                        continue;
                    }
                    if (!$this->resultadoPertenceAoModelo($row, $modeloBoletimId)) {
                        continue;
                    }
                    $mid = (int) ($row['materia_id'] ?? 0);
                    $nomeExibir = trim((string) ($row['materia_nome'] ?? ''));
                    $linha = $this->resolverLinhaFichaParaResultado(
                        $fid,
                        $mid,
                        $nomeExibir,
                        $porId,
                        $porNome,
                        $linhas,
                        $aid,
                        (int) $ano,
                        (int) ($row['ordem_linha'] ?? 0)
                    );
                    if (!$linha) {
                        continue;
                    }
                    if ($this->resultadoEhDesdobramentoDaLinha($mid, $nomeExibir, $linha)) {
                        continue;
                    }
                    $linhaId = (int) ($linha['id'] ?? 0);
                    if ($linhaId <= 0) {
                        continue;
                    }
                    if (!$this->linhaEstaNaLista($linhas, $linhaId)) {
                        $linhas[] = $linha;
                        $linhasPorFicha[$fid][] = $linha;
                        foreach ($this->model->listarCelulasPorLinhas([$linhaId]) as $chaveCelNova => $celNova) {
                            $celulas[$chaveCelNova] = $celNova;
                        }
                    }
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
                        if ($faltas === null && $mid > 0) {
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
        $observacao = $this->observacaoConselhoDaFicha($ficha);
        if ($existente) {
            $patch = [
                'resultado' => $resultado,
                'ficha_id' => $fichaId,
            ];
            if ($observacao !== null) {
                $patch['observacao'] = $observacao;
            }
            $this->model->atualizarAnoEscolarizacao((int) $existente['id'], $patch);
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
            'observacao' => $observacao,
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

    private function recalcularFinal(int $linhaId, ?array $ficha = null, bool $forcarEscrita = false): void
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
        if (in_array($final['status'] ?? '', ['homologada'], true) && !$forcarEscrita) {
            $faltaVazia = ($final['faltas'] ?? null) === null || $final['faltas'] === '';
            if ($temFaltas && $faltaVazia) {
                $this->model->atualizarCelula((int) $final['id'], ['faltas' => $faltasTotal]);
            }
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
        $alunoId = (int) ($ficha['aluno_id'] ?? 0);
        $turmaId = (int) ($ficha['turma_id'] ?? 0);
        $ano = (int) ($ficha['ano_letivo'] ?? 0);
        if ($alunoId > 0 && $turmaId > 0 && $ano > 0) {
            try {
                $homolog = $this->db->fetch(
                    "SELECT situacao FROM resultado_academico
                      WHERE aluno_id = :aid AND turma_id = :tid AND ano_letivo = :ano
                        AND periodo_tipo = 'ano' AND status = 'homologado'
                      LIMIT 1",
                    ['aid' => $alunoId, 'tid' => $turmaId, 'ano' => $ano]
                );
                if (is_array($homolog)) {
                    $rotuloHomolog = $this->rotuloResultadoVida((string) ($homolog['situacao'] ?? ''));
                    if ($rotuloHomolog !== '') {
                        return $rotuloHomolog;
                    }
                }
            } catch (\Throwable $e) {
                // schema ainda não aplicado
            }
        }

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
                'aluno_id' => $alunoId,
                'turma_id' => $turmaId,
            ], $regra ?? []);
        } catch (\Throwable $e) {
            return $media === null ? 'Em andamento' : 'Aprovado';
        }
        $sit = (string) ($avaliado['situacao'] ?? '');
        $rotuloMap = $this->rotuloResultadoVida($sit);
        if ($rotuloMap !== '') {
            return $rotuloMap;
        }
        $rotulo = trim((string) ($avaliado['rotulo'] ?? ''));
        return $rotulo !== '' ? $rotulo : 'Em andamento';
    }

    /**
     * @param array<string,mixed> $ficha
     */
    private function observacaoConselhoDaFicha(array $ficha): ?string
    {
        $alunoId = (int) ($ficha['aluno_id'] ?? 0);
        $turmaId = (int) ($ficha['turma_id'] ?? 0);
        $ano = (int) ($ficha['ano_letivo'] ?? 0);
        if ($alunoId <= 0 || $turmaId <= 0 || $ano <= 0) {
            return null;
        }
        try {
            $del = $this->db->fetch(
                "SELECT d.justificativa, d.resultado_decisao
                 FROM conselho_deliberacoes d
                 INNER JOIN conselho_sessoes s ON s.id = d.sessao_id
                 WHERE s.turma_id = :turma AND s.ano_letivo = :ano AND d.aluno_id = :aluno
                 ORDER BY s.bimestre DESC, d.id DESC
                 LIMIT 1",
                ['turma' => $turmaId, 'ano' => $ano, 'aluno' => $alunoId]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($del)) {
            return null;
        }
        $just = trim((string) ($del['justificativa'] ?? ''));
        if ($just === '') {
            return null;
        }
        return 'Conselho de Classe: ' . $just;
    }

    private function rotuloResultadoVida(string $sit): string
    {
        $map = [
            'aprovado' => 'Aprovado',
            'aprovado_recuperacao' => 'Aprovado',
            'aprovado_conselho' => 'Aprovado pelo Conselho',
            'aproveitamento' => 'Aprovado',
            'reprovado_rendimento' => 'Retido',
            'reprovado_frequencia' => 'Retido',
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
        return $map[$sit] ?? '';
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

        $diario = $this->faltasDoDiarioAlunoMateria($alunoId, $materiaId, $bimestre, $anoLetivo);
        if ($diario !== null) {
            return $diario;
        }

        if (!empty($this->faltasEventoExisteCache[$cacheKey])) {
            return 0;
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function carregarFaltasLancadas(int $anoLetivo, int $bimestre): array
    {
        $cacheKey = $anoLetivo . ':' . $bimestre;
        $this->faltasEventoExisteCache[$cacheKey] = false;
        $path = dirname(__DIR__, 3) . '/Models/Education/SchoolAbsence.php';
        if (!class_exists('SchoolAbsence', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('SchoolAbsence', false)) {
            return [];
        }
        $absence = new \SchoolAbsence();
        $eventoId = $absence->idEventoPorAnoBimestre($anoLetivo, $bimestre);
        $this->faltasEventoExisteCache[$cacheKey] = $eventoId > 0;
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

    private function faltasDoDiarioAlunoMateria(int $alunoId, int $materiaId, int $bimestre, int $anoLetivo): ?int
    {
        if ($materiaId <= 0) {
            return null;
        }
        $cacheKey = $anoLetivo . ':' . $bimestre;
        if (!array_key_exists($cacheKey, $this->faltasDiarioCache)) {
            $this->faltasDiarioCache[$cacheKey] = $this->carregarFaltasDoDiario($anoLetivo, $bimestre);
        }
        $map = $this->faltasDiarioCache[$cacheKey];
        if ($map === false) {
            return null;
        }
        $chave = $alunoId . '_' . $materiaId;
        if (array_key_exists($chave, $map)) {
            return $map[$chave];
        }

        return 0;
    }

    /**
     * @return array<string, int>|false false quando o diário não existe neste tenant
     */
    private function carregarFaltasDoDiario(int $anoLetivo, int $bimestre)
    {
        try {
            $tem = $this->db->fetch("SHOW TABLES LIKE 'diario_frequencias'");
            if (!$tem) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }
        $bimestre = max(1, min(4, $bimestre));
        $mesInicio = ($bimestre - 1) * 3 + 1;
        $mesFim = $mesInicio + 2;
        $inicio = sprintf('%04d-%02d-01', $anoLetivo, $mesInicio);
        $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anoLetivo, $mesFim)));
        $rows = $this->db->fetchAll(
            "SELECT df.aluno_id, da.materia_id, COUNT(*) AS n
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE df.situacao = 'falta'
               AND da.status <> 'cancelada'
               AND da.data_aula BETWEEN :inicio AND :fim
             GROUP BY df.aluno_id, da.materia_id",
            ['inicio' => $inicio, 'fim' => $fim]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $aid = (int) ($r['aluno_id'] ?? 0);
            $mid = (int) ($r['materia_id'] ?? 0);
            if ($aid <= 0 || $mid <= 0) {
                continue;
            }
            $out[$aid . '_' . $mid] = (int) ($r['n'] ?? 0);
        }

        return $out;
    }

    private function aplicarCelulaCalculada(
        int $linhaId,
        int $periodo,
        ?float $nota,
        ?int $faltas,
        bool $incluirReabertas = false,
        bool $forcarEscrita = false
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
        if (($cel['origem'] ?? '') === 'externa') {
            return false;
        }
        if (!$forcarEscrita && !in_array($status, $permitidos, true)) {
            $faltaVazia = ($cel['faltas'] ?? null) === null || $cel['faltas'] === '';
            if ($faltas === null || !$faltaVazia) {
                return false;
            }
            $this->model->atualizarCelula((int) $cel['id'], ['faltas' => $faltas]);
            return true;
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
     * Apaga nota calculada aberta em bimestre que não tem evento gerado.
     *
     * @param list<array<string,mixed>> $linhas
     * @param array<int, array<int, true>> $bimsGerados
     * @param array<int, true> $linhasTocadas
     */
    private function limparBimsSemEventoNaFicha(
        array $linhas,
        array $bimsGerados,
        array &$linhasTocadas,
        bool $incluirReabertas,
        bool $forcarEscrita
    ): int {
        $n = 0;
        $permitidos = $incluirReabertas ? ['aberta', 'reaberta'] : ['aberta'];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $linhaId = (int) ($linha['id'] ?? 0);
            $mid = (int) ($linha['materia_id'] ?? 0);
            if ($linhaId <= 0) {
                continue;
            }
            foreach ([1, 2, 3, 4] as $periodo) {
                if ($mid > 0 && isset($bimsGerados[$mid][$periodo])) {
                    continue;
                }
                $cel = $this->model->findCelulaLinhaPeriodo($linhaId, $periodo);
                if (!$cel || (string) ($cel['origem'] ?? '') === 'externa') {
                    continue;
                }
                if ((string) ($cel['origem'] ?? '') !== 'calculada') {
                    continue;
                }
                $status = (string) ($cel['status'] ?? '');
                if (!$forcarEscrita && !in_array($status, $permitidos, true)) {
                    continue;
                }
                $this->model->atualizarCelula((int) $cel['id'], [
                    'nota' => null,
                    'conceito' => null,
                    'faltas' => null,
                    'origem' => 'vazia',
                ]);
                $n++;
                $linhasTocadas[$linhaId] = true;
            }
        }

        return $n;
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

    /**
     * Evento do filho (Literatura) não cria linha nova nem grava em cima da
     * linha oficial do pai (Língua Portuguesa). A nota oficial vem da linha
     * agrupada do boletim.
     */
    private function resultadoEhDesdobramentoDaLinha(int $materiaIdResultado, string $nomeResultado, array $linha): bool
    {
        $linhaMid = (int) ($linha['materia_id'] ?? 0);
        if ($linhaMid <= 0) {
            return false;
        }
        if ($materiaIdResultado > 0 && $materiaIdResultado === $linhaMid) {
            return false;
        }
        $nomeLinha = mb_strtolower(trim((string) ($linha['componente_nome'] ?? '')));
        $nomeRes = mb_strtolower(trim($nomeResultado));
        if ($nomeRes !== '' && $nomeRes === $nomeLinha) {
            return false;
        }
        if ($materiaIdResultado > 0) {
            $pai = $this->paiIdDaMateria($materiaIdResultado);
            if ($pai === $linhaMid) {
                return true;
            }
        }
        foreach ($this->filhosDaMateria($linhaMid) as $fid) {
            if ($materiaIdResultado === $fid) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int, array<string,mixed>> $porId
     */
    private function linhaOficialDoPaiNaFicha(
        int $materiaId,
        string $nomeKey,
        array $porId,
        int $alunoId,
        int $anoLetivo
    ): ?array {
        if ($materiaId > 0) {
            $pai = $this->paiIdDaMateria($materiaId);
            if ($pai > 0 && isset($porId[$pai])) {
                return $porId[$pai];
            }
        }
        if ($alunoId <= 0 || $anoLetivo <= 0) {
            return null;
        }
        foreach ($this->gruposLinhaDoAluno($alunoId, $anoLetivo) as $g) {
            $label = mb_strtolower(trim((string) ($g['label'] ?? '')));
            $idsGrupo = array_map('intval', (array) ($g['materias_ids'] ?? []));
            $bateNome = $nomeKey !== '' && $label === $nomeKey;
            $bateId = $materiaId > 0 && in_array($materiaId, $idsGrupo, true);
            if (!$bateNome && !$bateId) {
                continue;
            }
            foreach ($idsGrupo as $fid) {
                $pai = $this->paiIdDaMateria($fid);
                if ($pai > 0 && isset($porId[$pai])) {
                    return $porId[$pai];
                }
            }
        }
        return null;
    }

    private function paiIdDaMateria(int $materiaId): int
    {
        if ($materiaId <= 0) {
            return 0;
        }
        return (int) ($this->mapaPaiPorFilho()[$materiaId] ?? 0);
    }

    /**
     * @return list<int>
     */
    private function filhosDaMateria(int $paiId): array
    {
        if ($paiId <= 0) {
            return [];
        }
        $ids = [];
        foreach ($this->mapaPaiPorFilho() as $filhoId => $pai) {
            if ($pai === $paiId) {
                $ids[] = (int) $filhoId;
            }
        }
        return $ids;
    }

    /**
     * @return array<int, int>
     */
    private function mapaPaiPorFilho(): array
    {
        if ($this->paiPorFilhoCache !== null) {
            return $this->paiPorFilhoCache;
        }
        try {
            $this->paiPorFilhoCache = (new \ComponenteCurricular())->mapaPaiPorFilho();
        } catch (\Throwable $e) {
            $this->paiPorFilhoCache = [];
        }
        return $this->paiPorFilhoCache;
    }

    /**
     * Encaixa a linha do boletim gerado na ficha. Linha agrupada (group_line,
     * materia_id nulo) é criada com o nome do grupo (ex.: Língua Portuguesa).
     *
     * @param array<int, array<string,mixed>> $porId
     * @param array<string, array<string,mixed>> $porNome
     * @param list<array<string,mixed>> $linhasExistentes
     * @return array<string,mixed>|null
     */
    private function resolverLinhaFichaParaResultado(
        int $fichaId,
        int $materiaId,
        string $materiaNome,
        array &$porId,
        array &$porNome,
        array $linhasExistentes,
        int $alunoId = 0,
        int $anoLetivo = 0,
        int $ordemGerada = 0
    ): ?array {
        $nomeKey = mb_strtolower(trim($materiaNome));
        $linha = $this->encontrarLinhaFicha($materiaId, $nomeKey, $porId, $porNome, $linhasExistentes);
        if (!$linha) {
            $linha = $this->linhaOficialDoPaiNaFicha($materiaId, $nomeKey, $porId, $alunoId, $anoLetivo);
        }
        if ($linha) {
            return $linha;
        }
        $nomeExibir = trim($materiaNome);
        if ($fichaId <= 0 || $nomeExibir === '') {
            return null;
        }
        if (!$this->podeCriarLinhaComponente($fichaId, $materiaId, $linhasExistentes)) {
            return null;
        }
        $ordemMax = 0;
        $ordemGrupo = null;
        $filhosGrupo = [];
        if ($materiaId <= 0 && $alunoId > 0 && $anoLetivo > 0 && $nomeKey !== '') {
            foreach ($this->gruposLinhaDoAluno($alunoId, $anoLetivo) as $g) {
                if (mb_strtolower(trim((string) ($g['label'] ?? ''))) === $nomeKey) {
                    $filhosGrupo = array_fill_keys(array_map('intval', (array) ($g['materias_ids'] ?? [])), true);
                    break;
                }
            }
        }
        foreach ($linhasExistentes as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $ordemLn = (int) ($ln['ordem'] ?? 0);
            $ordemMax = max($ordemMax, $ordemLn);
            $midLn = (int) ($ln['materia_id'] ?? 0);
            if ($midLn > 0 && isset($filhosGrupo[$midLn])) {
                $ordemGrupo = $ordemGrupo === null ? $ordemLn : min($ordemGrupo, $ordemLn);
            }
        }
        foreach ($porNome as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $ordemMax = max($ordemMax, (int) ($ln['ordem'] ?? 0));
        }
        if ($ordemGrupo !== null) {
            $ordem = $ordemGrupo;
        } elseif ($ordemGerada > 0) {
            $ordem = $ordemGerada;
        } else {
            $ordem = $ordemMax + 1;
        }
        $nova = $this->criarLinhaFichaCompleta(
            $fichaId,
            $materiaId > 0 ? $materiaId : null,
            $nomeExibir,
            $ordem
        );
        if (!$nova) {
            return null;
        }
        if ($materiaId > 0) {
            $porId[$materiaId] = $nova;
        }
        if ($nomeKey !== '') {
            $porNome[$nomeKey] = $nova;
        }

        return $nova;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function criarLinhaFichaCompleta(int $fichaId, ?int $materiaId, string $nome, int $ordem): ?array
    {
        $nome = trim($nome);
        if ($fichaId <= 0 || $nome === '') {
            return null;
        }
        $linhaId = $this->model->criarLinha([
            'ficha_id' => $fichaId,
            'materia_id' => ($materiaId !== null && $materiaId > 0) ? $materiaId : null,
            'componente_nome' => $nome,
            'carga_horaria' => null,
            'ordem' => $ordem,
        ]);
        if ($linhaId <= 0) {
            return null;
        }
        foreach ([1, 2, 3, 4, 0] as $periodo) {
            $this->model->criarCelula([
                'linha_id' => $linhaId,
                'periodo_numero' => $periodo,
                'origem' => 'vazia',
                'status' => 'aberta',
            ]);
        }

        return $this->model->findLinha($linhaId);
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    private function linhaEstaNaLista(array $linhas, int $linhaId): bool
    {
        if ($linhaId <= 0) {
            return false;
        }
        foreach ($linhas as $ln) {
            if (is_array($ln) && (int) ($ln['id'] ?? 0) === $linhaId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Uma linha por matéria do modelo de boletim (ou da matriz, se não houver modelo).
     *
     * @param array<string,mixed> $ficha
     * @param list<array<string,mixed>> $linhas
     * @param array<int, true> $idsOcultos
     * @return list<array<string,mixed>>
     */
    private function linhasUnicasDoQuadro(array $ficha, array $linhas, array $idsOcultos): array
    {
        $idsPermitidos = $this->materiaIdsDoModeloDaFicha($ficha);
        $ordemModelo = $this->ordemMateriasDoModeloDaFicha($ficha);
        $porMateria = [];
        $semMateria = [];
        foreach ($linhas as $l) {
            if (!is_array($l)) {
                continue;
            }
            $mid = (int) ($l['materia_id'] ?? 0);
            if ($mid > 0 && isset($idsOcultos[$mid])) {
                continue;
            }
            if ($mid > 0 && $idsPermitidos !== [] && !isset($idsPermitidos[$mid])) {
                continue;
            }
            if ($mid > 0) {
                $prev = $porMateria[$mid] ?? null;
                if ($prev === null || (int) ($l['id'] ?? 0) > (int) ($prev['id'] ?? 0)) {
                    $porMateria[$mid] = $l;
                }
                continue;
            }
            if ($idsPermitidos !== []) {
                continue;
            }
            $semMateria[] = $l;
        }
        $nomesUsados = [];
        foreach ($porMateria as $l) {
            $n = mb_strtolower(trim((string) ($l['componente_nome'] ?? '')));
            if ($n !== '') {
                $nomesUsados[$n] = true;
            }
        }
        $out = array_values($porMateria);
        foreach ($semMateria as $l) {
            $n = mb_strtolower(trim((string) ($l['componente_nome'] ?? '')));
            if ($n !== '' && isset($nomesUsados[$n])) {
                continue;
            }
            $out[] = $l;
            if ($n !== '') {
                $nomesUsados[$n] = true;
            }
        }
        usort($out, static function ($a, $b) use ($ordemModelo) {
            $ma = (int) ($a['materia_id'] ?? 0);
            $mb = (int) ($b['materia_id'] ?? 0);
            $oa = $ordemModelo[$ma] ?? 1000 + (int) ($a['ordem'] ?? 0);
            $ob = $ordemModelo[$mb] ?? 1000 + (int) ($b['ordem'] ?? 0);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $out;
    }

    /**
     * @param array<string,mixed> $ficha
     * @return array<int, int> materia_id => ordem
     */
    private function ordemMateriasDoModeloDaFicha(array $ficha): array
    {
        $modelo = $this->modeloOficialDaFicha($ficha);
        if (!is_array($modelo)) {
            return [];
        }
        $ordem = [];
        foreach ($this->boletimCadastro()->componentesParaFicha($modelo) as $c) {
            $id = (int) ($c['materia_id'] ?? 0);
            if ($id > 0 && !isset($ordem[$id])) {
                $ordem[$id] = (int) ($c['ordem'] ?? (count($ordem) + 1));
            }
        }

        return $ordem;
    }

    /**
     * @param array<string,mixed> $ficha
     * @return array<int, true>
     */
    private function materiaIdsDoModeloDaFicha(array $ficha): array
    {
        $modelo = $this->modeloOficialDaFicha($ficha);
        if (is_array($modelo)) {
            $ids = [];
            foreach ($this->boletimCadastro()->componentesParaFicha($modelo) as $c) {
                $id = (int) ($c['materia_id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
            if ($ids !== []) {
                return $ids;
            }
        }

        return $this->materiaIdsMatrizDaTurma((int) ($ficha['turma_id'] ?? 0));
    }

    /**
     * @return array<int, true>
     */
    private function materiaIdsMatrizDaTurma(int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }
        $ids = [];
        foreach ($this->model->componentesDaTurma($turmaId) as $c) {
            $id = (int) ($c['materia_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    private function boletimCadastro(): BoletimCadastroService
    {
        if ($this->boletimCadastro === null) {
            $this->boletimCadastro = new BoletimCadastroService();
        }

        return $this->boletimCadastro;
    }

    /**
     * @param array<string,mixed> $ficha
     * @return array<string,mixed>|null
     */
    private function modeloOficialDaFicha(array $ficha): ?array
    {
        $turmaId = (int) ($ficha['turma_id'] ?? 0);
        $ano = (int) ($ficha['ano_letivo'] ?? 0);
        $alunoId = (int) ($ficha['aluno_id'] ?? 0);
        $chave = $turmaId . ':' . $ano . ':' . $alunoId;
        if (array_key_exists($chave, $this->modeloBoletimCache)) {
            return $this->modeloBoletimCache[$chave];
        }
        $turma = $turmaId > 0 ? $this->model->turmaPorId($turmaId) : null;
        $serieId = (int) ($turma['serie_id'] ?? 0);
        $preferido = $alunoId > 0 ? $this->boletimIdDicaDoAluno($alunoId, $ano) : 0;
        $this->modeloBoletimCache[$chave] = $this->boletimCadastro()->encontrarOficialParaTurma(
            $turmaId,
            $serieId,
            $ano,
            $preferido > 0 ? $preferido : null
        );

        return $this->modeloBoletimCache[$chave];
    }

    /**
     * @return list<array{materia_id:int,componente_nome:string,ordem?:int}>
     */
    private function componentesParaNovaFicha(int $turmaId, int $anoLetivo, int $alunoId = 0): array
    {
        $turma = $turmaId > 0 ? $this->model->turmaPorId($turmaId) : null;
        $serieId = (int) ($turma['serie_id'] ?? 0);
        $preferido = $alunoId > 0 ? $this->boletimIdDicaDoAluno($alunoId, $anoLetivo) : 0;
        $modelo = $this->boletimCadastro()->encontrarOficialParaTurma(
            $turmaId,
            $serieId,
            $anoLetivo,
            $preferido > 0 ? $preferido : null
        );
        if (is_array($modelo)) {
            $comps = $this->boletimCadastro()->componentesParaFicha($modelo);
            if ($comps !== []) {
                return $comps;
            }
        }

        return $this->model->componentesDaTurma($turmaId);
    }

    /**
     * Garante na ficha as matérias do modelo oficial (não apaga linhas extras).
     *
     * @param array<string,mixed> $ficha
     */
    private function alinharFichaAoModelo(array $ficha): void
    {
        $fichaId = (int) ($ficha['id'] ?? 0);
        if ($fichaId <= 0 || ($ficha['status'] ?? '') === 'homologada') {
            return;
        }
        $modelo = $this->modeloOficialDaFicha($ficha);
        if (!is_array($modelo)) {
            return;
        }
        $comps = $this->boletimCadastro()->componentesParaFicha($modelo);
        if ($comps === []) {
            return;
        }
        $linhas = $this->model->listarLinhas($fichaId);
        $jaTem = [];
        foreach ($linhas as $ln) {
            $mid = (int) ($ln['materia_id'] ?? 0);
            if ($mid > 0) {
                $jaTem[$mid] = true;
            }
        }
        foreach ($comps as $comp) {
            $mid = (int) ($comp['materia_id'] ?? 0);
            if ($mid <= 0 || isset($jaTem[$mid])) {
                continue;
            }
            $nome = (string) ($comp['componente_nome'] ?? 'Componente');
            $nova = $this->criarLinhaFichaCompleta(
                $fichaId,
                $mid,
                $nome,
                (int) ($comp['ordem'] ?? 0)
            );
            if ($nova) {
                $jaTem[$mid] = true;
            }
        }
    }

    private function boletimIdDicaDoAluno(int $alunoId, int $anoLetivo): int
    {
        if ($alunoId <= 0) {
            return 0;
        }
        $notas = 0;
        $qualquer = 0;
        foreach ($this->model->listarResultadosGeradosOficiais($alunoId) as $row) {
            $bid = (int) ($row['boletim_id'] ?? 0);
            if ($bid <= 0 || !$this->eventoPertenceAoAno($row, $anoLetivo)) {
                continue;
            }
            if ($qualquer <= 0) {
                $qualquer = $bid;
            }
            if ((string) ($row['exibir_em'] ?? '') === 'notas') {
                $notas = $bid;
                break;
            }
        }

        return $notas > 0 ? $notas : $qualquer;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resultadoPertenceAoModelo(array $row, int $boletimId): bool
    {
        if ($boletimId <= 0) {
            return true;
        }
        $rowBoletim = (int) ($row['boletim_id'] ?? 0);
        if ($rowBoletim <= 0) {
            return true;
        }

        return $rowBoletim === $boletimId;
    }

    /**
     * @return array<int, array<int, true>> materia_id => bimestre => true
     */
    private function bimestresGeradosPorMateria(int $alunoId, int $anoLetivo, int $boletimId = 0): array
    {
        if ($alunoId <= 0 || $anoLetivo <= 0) {
            return [];
        }
        $out = [];
        foreach ($this->model->listarResultadosGeradosOficiais($alunoId) as $row) {
            if (!$this->eventoPertenceAoAno($row, $anoLetivo)) {
                continue;
            }
            if (!$this->resultadoPertenceAoModelo($row, $boletimId)) {
                continue;
            }
            $mid = (int) ($row['materia_id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            foreach (array_keys($this->periodosDaLinhaGerada($row)) as $bim) {
                $bim = (int) $bim;
                if ($bim >= 1 && $bim <= 4) {
                    $out[$mid][$bim] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Esconde nota de demonstração (linha antiga / bimestre sem evento gerado).
     *
     * @param array<int, array<string,mixed>> $celulas
     * @param array<int, array<int, true>> $bimsGerados
     * @return array<int, array<string,mixed>>
     */
    private function celulasQuadroVisiveis(array $celulas, int $materiaId, array $bimsGerados, bool $linhaOrfa): array
    {
        $temEventoMateria = $materiaId > 0 && isset($bimsGerados[$materiaId]);
        $alterou = false;
        foreach ($celulas as $periodo => $c) {
            if (!is_array($c)) {
                continue;
            }
            $p = (int) $periodo;
            if ($p === 0) {
                continue;
            }
            $origem = (string) ($c['origem'] ?? '');
            if ($origem === 'externa') {
                continue;
            }
            $temEventoBim = $temEventoMateria && isset($bimsGerados[$materiaId][$p]);
            if ($temEventoBim) {
                continue;
            }
            $status = (string) ($c['status'] ?? '');
            $aberta = in_array($status, ['aberta', 'reaberta'], true);
            $apagarDemo = $origem === 'calculada' && ($linhaOrfa || !$aberta);
            if (!$apagarDemo) {
                continue;
            }
            $alterou = true;
            $celulas[$periodo]['nota'] = null;
            $celulas[$periodo]['conceito'] = null;
            if ($linhaOrfa || $origem === 'calculada') {
                $celulas[$periodo]['faltas'] = null;
            }
            if ($celulas[$periodo]['nota'] === null && ($celulas[$periodo]['faltas'] ?? null) === null) {
                $celulas[$periodo]['origem'] = 'vazia';
            }
        }

        if ($alterou) {
            return $this->finalQuadroAPartirDosBims($celulas);
        }

        return $celulas;
    }

    /**
     * @param array<int, array<string,mixed>> $celulas
     * @return array<int, array<string,mixed>>
     */
    private function finalQuadroAPartirDosBims(array $celulas): array
    {
        if (!isset($celulas[0]) || !is_array($celulas[0])) {
            return $celulas;
        }
        $notas = [];
        $faltas = 0;
        $temFaltas = false;
        foreach ([1, 2, 3, 4] as $p) {
            $c = $celulas[$p] ?? null;
            if (!is_array($c)) {
                continue;
            }
            if (is_numeric($c['nota'] ?? null)) {
                $notas[] = (float) $c['nota'];
            }
            if (($c['faltas'] ?? null) !== null && $c['faltas'] !== '') {
                $faltas += (int) $c['faltas'];
                $temFaltas = true;
            }
        }
        if ($notas === []) {
            $celulas[0]['nota'] = null;
            $celulas[0]['origem'] = 'vazia';
        } else {
            $celulas[0]['nota'] = array_sum($notas) / count($notas);
            $celulas[0]['origem'] = 'calculada';
        }
        $celulas[0]['faltas'] = $temFaltas ? $faltas : null;

        return $celulas;
    }

    /**
     * @param array<int, array<string,mixed>> $porId
     * @param array<string, array<string,mixed>> $porNome
     * @param list<array<string,mixed>> $linhasExistentes
     * @return array<string,mixed>|null
     */
    private function encontrarLinhaFicha(
        int $materiaId,
        string $nomeKey,
        array $porId,
        array $porNome,
        array $linhasExistentes
    ): ?array {
        $escolhida = null;
        if ($materiaId > 0) {
            foreach ($linhasExistentes as $ln) {
                if (!is_array($ln) || (int) ($ln['materia_id'] ?? 0) !== $materiaId) {
                    continue;
                }
                if ($escolhida === null || (int) ($ln['id'] ?? 0) > (int) ($escolhida['id'] ?? 0)) {
                    $escolhida = $ln;
                }
            }
            if ($escolhida) {
                return $escolhida;
            }
            if (isset($porId[$materiaId])) {
                return $porId[$materiaId];
            }

            return null;
        }
        if ($nomeKey !== '' && isset($porNome[$nomeKey])) {
            return $porNome[$nomeKey];
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $linhasExistentes
     */
    private function podeCriarLinhaComponente(int $fichaId, int $materiaId, array $linhasExistentes): bool
    {
        foreach ($linhasExistentes as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            if ($materiaId > 0 && (int) ($ln['materia_id'] ?? 0) === $materiaId) {
                return false;
            }
        }
        $ficha = $this->model->findFicha($fichaId);
        if (!is_array($ficha)) {
            return $materiaId > 0;
        }
        $idsPermitidos = $this->materiaIdsDoModeloDaFicha($ficha);
        if ($idsPermitidos === []) {
            return $materiaId > 0;
        }
        if ($materiaId <= 0) {
            return false;
        }

        return isset($idsPermitidos[$materiaId]);
    }

    /**
     * Filhos do group_line (ex.: Literatura) somem do quadro quando a linha
     * agrupada já existe na ficha e o filho não tem lançamento próprio.
     *
     * @param list<array<string,mixed>> $linhas
     * @param array<int, array<int, array<string,mixed>>> $porLinha
     * @return array<int, true>
     */
    private function materiaIdsOcultosPorAgrupamento(
        int $alunoId,
        int $anoLetivo,
        array $linhas,
        array $porLinha
    ): array {
        if ($alunoId <= 0 || $anoLetivo <= 0) {
            return [];
        }
        $grupos = $this->gruposLinhaDoAluno($alunoId, $anoLetivo);
        if ($grupos === []) {
            return [];
        }
        $nomesNaFicha = [];
        foreach ($linhas as $l) {
            if (!is_array($l)) {
                continue;
            }
            $n = mb_strtolower(trim((string) ($l['componente_nome'] ?? '')));
            if ($n !== '') {
                $nomesNaFicha[$n] = true;
            }
        }
        $ocultar = [];
        foreach ($grupos as $g) {
            $label = mb_strtolower(trim((string) ($g['label'] ?? '')));
            if ($label === '' || !isset($nomesNaFicha[$label])) {
                continue;
            }
            foreach ((array) ($g['materias_ids'] ?? []) as $mid) {
                $mid = (int) $mid;
                if ($mid > 0) {
                    $ocultar[$mid] = true;
                }
            }
        }
        foreach ($linhas as $l) {
            if (!is_array($l)) {
                continue;
            }
            $paiFicha = (int) ($l['materia_id'] ?? 0);
            if ($paiFicha <= 0) {
                continue;
            }
            foreach ($this->filhosDaMateria($paiFicha) as $fid) {
                $ocultar[$fid] = true;
            }
        }
        if ($ocultar === []) {
            return [];
        }
        $out = [];
        foreach ($linhas as $l) {
            if (!is_array($l)) {
                continue;
            }
            $mid = (int) ($l['materia_id'] ?? 0);
            if ($mid <= 0 || !isset($ocultar[$mid])) {
                continue;
            }
            $lid = (int) ($l['id'] ?? 0);
            if ($this->celulasLinhaTemValor($porLinha[$lid] ?? [])) {
                continue;
            }
            $out[$mid] = true;
        }

        return $out;
    }

    /**
     * @return list<array{label:string,materias_ids:list<int>}>
     */
    private function gruposLinhaDoAluno(int $alunoId, int $anoLetivo): array
    {
        $cacheKey = $alunoId . ':' . $anoLetivo;
        if (isset($this->gruposLinhaCache[$cacheKey])) {
            return $this->gruposLinhaCache[$cacheKey];
        }
        $out = [];
        $vistos = [];
        foreach ($this->model->listarConfigJsonGruposLinhaDoAluno($alunoId, $anoLetivo) as $raw) {
            $grp = $this->parseGroupLineConfigJson($raw);
            if ($grp === null) {
                continue;
            }
            $chave = mb_strtolower($grp['label']) . ':' . implode(',', $grp['materias_ids']);
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $out[] = $grp;
        }
        $this->gruposLinhaCache[$cacheKey] = $out;

        return $out;
    }

    /**
     * @return array{label:string,materias_ids:list<int>}|null
     */
    private function parseGroupLineConfigJson(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $grp = $decoded['group_line'] ?? null;
        if (!is_array($grp) || empty($grp['enabled'])) {
            return null;
        }
        $label = trim((string) ($grp['label'] ?? $grp['nome'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($grp['key'] ?? ''));
        }
        $ids = [];
        foreach ((array) ($grp['materias_ids'] ?? []) as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($label === '' || $ids === []) {
            return null;
        }

        return ['label' => $label, 'materias_ids' => $ids];
    }

    /**
     * @param array<int, array<string,mixed>> $celulas
     */
    private function celulasLinhaTemValor(array $celulas): bool
    {
        foreach ($celulas as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (($c['nota'] ?? null) !== null && $c['nota'] !== '') {
                return true;
            }
            if (trim((string) ($c['conceito'] ?? '')) !== '') {
                return true;
            }
            if (($c['faltas'] ?? null) !== null && $c['faltas'] !== '') {
                return true;
            }
        }

        return false;
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
