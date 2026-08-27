<?php

namespace App\Modulos\ConselhoClasse\Services;

require_once __DIR__ . '/../Models/ConselhoSessao.php';
require_once __DIR__ . '/../../../Models/Education/ClassDiary.php';
require_once __DIR__ . '/../../../Services/FrequencyService.php';

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
use ClassDiary;
use Database;
use FrequencyService;
use App\Modulos\Ocorrencias\Models\Ocorrencia;
use App\Modulos\Ocorrencias\Services\OcorrenciaService;
use LayoutHelper;

/**
 * EducaTudo - Conselho de Classe
 * Consolida fontes existentes (boletim, diário, frequência, ocorrências).
 * Não duplica nota nem falta. Não aplica regra acadêmica própria além
 * do recorte já gravado em boletim_resultados_gerados.
 */
class ConselhoService
{
    private ConselhoSessao $model;
    private FrequencyService $frequencia;
    private ClassDiary $diario;
    private $db;

    public function __construct(?ConselhoSessao $model = null, ?FrequencyService $frequencia = null, ?ClassDiary $diario = null)
    {
        $this->model = $model ?? new ConselhoSessao();
        $this->frequencia = $frequencia ?? new FrequencyService();
        $this->diario = $diario ?? new ClassDiary();
        $this->db = Database::getInstance();
    }

    public function model(): ConselhoSessao
    {
        return $this->model;
    }

    /**
     * @return array{success: bool, id?: int, error?: string}
     */
    public function criar(array $input, int $usuarioId): array
    {
        $turmaId = (int) ($input['turma_id'] ?? 0);
        $anoLetivo = (int) ($input['ano_letivo'] ?? 0);
        $bimestre = (int) ($input['bimestre'] ?? 0);
        if ($turmaId <= 0 || $anoLetivo <= 0 || $bimestre < 1 || $bimestre > 4) {
            return ['success' => false, 'error' => 'Informe turma, ano letivo e bimestre'];
        }
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Execute a migration do Conselho de Classe antes de usar o módulo.'];
        }

        $turma = $this->model->turmaPorId($turmaId);
        if (!$turma || (int) ($turma['ativo'] ?? 0) !== 1) {
            return ['success' => false, 'error' => 'Turma não encontrada ou inativa'];
        }
        if ((int) ($turma['ano_letivo'] ?? 0) !== $anoLetivo) {
            return ['success' => false, 'error' => 'O ano letivo precisa ser o da turma selecionada'];
        }

        $existente = $this->model->findByTurmaPeriodo($turmaId, $anoLetivo, $bimestre);
        if ($existente) {
            return ['success' => true, 'id' => (int) $existente['id']];
        }

        $dataReuniao = trim((string) ($input['data_reuniao'] ?? ''));
        if ($dataReuniao !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataReuniao)) {
            return ['success' => false, 'error' => 'Data da reunião inválida'];
        }

        $id = $this->model->criar([
            'turma_id' => $turmaId,
            'ano_letivo' => $anoLetivo,
            'bimestre' => $bimestre,
            'data_reuniao' => $dataReuniao !== '' ? $dataReuniao : null,
            'pauta' => trim((string) ($input['pauta'] ?? '')) ?: null,
            'criado_por' => $usuarioId,
        ]);

        $this->sugerirParticipantes($id, $turmaId);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function abrir(int $sessaoId, int $usuarioId): array
    {
        $sessao = $this->model->findById($sessaoId);
        if (!$sessao) {
            return ['success' => false, 'error' => 'Conselho não encontrado'];
        }
        $status = (string) $sessao['status'];
        if (!in_array($status, ['em_preparacao', 'reaberto'], true)) {
            return ['success' => false, 'error' => 'Este Conselho não pode ser aberto neste status'];
        }
        $this->model->marcarAberto($sessaoId, $usuarioId);
        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function finalizar(int $sessaoId, int $usuarioId): array
    {
        $sessao = $this->model->findById($sessaoId);
        if (!$sessao) {
            return ['success' => false, 'error' => 'Conselho não encontrado'];
        }
        $status = (string) $sessao['status'];
        if (!in_array($status, ['em_andamento', 'reaberto'], true)) {
            return ['success' => false, 'error' => 'Finalize apenas um Conselho em andamento ou reaberto'];
        }
        $this->model->marcarFinalizado($sessaoId, $usuarioId);
        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function reabrir(int $sessaoId, int $usuarioId): array
    {
        $sessao = $this->model->findById($sessaoId);
        if (!$sessao) {
            return ['success' => false, 'error' => 'Conselho não encontrado'];
        }
        if ((string) $sessao['status'] !== 'finalizado') {
            return ['success' => false, 'error' => 'Só é possível reabrir um Conselho finalizado'];
        }
        $this->model->marcarReaberto($sessaoId, $usuarioId);
        return ['success' => true];
    }

    public function podeRegistrar(array $sessao): bool
    {
        return in_array((string) ($sessao['status'] ?? ''), ['em_andamento', 'reaberto'], true);
    }

    /**
     * Painel inicial: turmas do ano/bimestre com status, alunos e pendências.
     *
     * @return list<array<string,mixed>>
     */
    public function painel(int $anoLetivo, int $bimestre, int $turmaId = 0): array
    {
        $linhas = $this->model->listarPainel($anoLetivo, $bimestre, $turmaId);
        foreach ($linhas as &$linha) {
            $tid = (int) $linha['turma_id'];
            $pendencias = $this->contarPendencias($tid, $anoLetivo, $bimestre);
            $linha['pendencias'] = $pendencias;
            $linha['status_exibicao'] = $linha['sessao_id']
                ? (string) $linha['status']
                : 'nao_iniciado';
        }
        unset($linha);
        return $linhas;
    }

    /**
     * Matriz do Conselho: alunos × componentes, frequência e situação.
     *
     * @return array<string,mixed>
     */
    public function matriz(array $sessao): array
    {
        $turmaId = (int) $sessao['turma_id'];
        $anoLetivo = (int) $sessao['ano_letivo'];
        $bimestre = (int) $sessao['bimestre'];
        $alunos = $this->model->alunosDaTurma($turmaId);
        $notas = $this->notasDoBoletim($turmaId, $anoLetivo, $bimestre);
        $frequencias = $this->frequenciasPorAluno($turmaId, $anoLetivo, $bimestre);
        $deliberacoes = $this->model->deliberacoesVigentes((int) $sessao['id']);
        $componentes = $notas['componentes'];

        $linhas = [];
        foreach ($alunos as $aluno) {
            $alunoId = (int) $aluno['id'];
            $notasAluno = $notas['por_aluno'][$alunoId] ?? [];
            $freq = $frequencias[$alunoId] ?? null;
            $preliminar = $this->resultadoPreliminar($notasAluno, $freq, $notas['nota_minima'], !empty($aluno['transferido']));
            $deliberacao = $deliberacoes[$alunoId] ?? null;
            $linhas[] = [
                'aluno' => $aluno,
                'componentes' => $notasAluno,
                'frequencia' => $freq,
                'resultado_preliminar' => $preliminar,
                'deliberacao' => $deliberacao,
                'resultado_homologado' => $deliberacao
                    ? (string) $deliberacao['resultado_decisao']
                    : null,
            ];
        }

        return [
            'componentes' => $componentes,
            'nota_minima' => $notas['nota_minima'],
            'linhas' => $linhas,
            'pendencias' => $this->contarPendencias($turmaId, $anoLetivo, $bimestre),
            'participantes' => $this->model->listarParticipantes((int) $sessao['id']),
            'professores_turma' => $this->model->professoresDaTurma($turmaId),
        ];
    }

    /**
     * Ficha do aluno no Conselho — só leitura das fontes + histórico do próprio Conselho.
     *
     * @return array<string,mixed>|null
     */
    public function fichaAluno(array $sessao, int $alunoId): ?array
    {
        $aluno = $this->model->alunoDaTurma($alunoId, (int) $sessao['turma_id']);
        if (!$aluno) {
            return null;
        }

        $matriz = $this->matriz($sessao);
        $linha = null;
        foreach ($matriz['linhas'] as $item) {
            if ((int) $item['aluno']['id'] === $alunoId) {
                $linha = $item;
                break;
            }
        }

        $ocorrencias = [];
        if ($this->moduloOcorrenciasHabilitado() && $this->model->tabelaExiste('alunos_ocorrencias')) {
            require_once __DIR__ . '/../../ocorrencias/Models/Ocorrencia.php';
            $ocorrencias = (new Ocorrencia())->listarPorAluno($alunoId, 50);
        }

        return [
            'aluno' => $aluno,
            'linha' => $linha,
            'componentes' => $matriz['componentes'],
            'nota_minima' => $matriz['nota_minima'],
            'deliberacoes' => $this->model->listarDeliberacoes((int) $sessao['id'], $alunoId),
            'encaminhamentos' => $this->model->listarEncaminhamentos((int) $sessao['id'], $alunoId),
            'observacoes' => $this->model->listarObservacoes((int) $sessao['id'], $alunoId),
            'ocorrencias' => $ocorrencias,
        ];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function deliberar(int $sessaoId, array $input, int $usuarioId): array
    {
        $sessao = $this->model->findById($sessaoId);
        if (!$sessao) {
            return ['success' => false, 'error' => 'Conselho não encontrado'];
        }
        if (!$this->podeRegistrar($sessao)) {
            return ['success' => false, 'error' => 'Deliberação só é permitida com o Conselho em andamento ou reaberto'];
        }

        $alunoId = (int) ($input['aluno_id'] ?? 0);
        $aluno = $this->model->alunoDaTurma($alunoId, (int) $sessao['turma_id']);
        if (!$aluno) {
            return ['success' => false, 'error' => 'Aluno não pertence a esta turma'];
        }

        $decisao = trim((string) ($input['resultado_decisao'] ?? ''));
        if (!isset(ConselhoSessao::RESULTADOS[$decisao])) {
            return ['success' => false, 'error' => 'Decisão inválida'];
        }
        $justificativa = trim((string) ($input['justificativa'] ?? ''));
        if ($justificativa === '') {
            return ['success' => false, 'error' => 'Informe a justificativa da deliberação'];
        }

        $ficha = $this->fichaAluno($sessao, $alunoId);
        $anterior = (string) (($ficha['linha']['resultado_preliminar']['codigo'] ?? '') ?: 'sem_dados');

        $this->model->inserirDeliberacao([
            'sessao_id' => $sessaoId,
            'aluno_id' => $alunoId,
            'materia_id' => (int) ($input['materia_id'] ?? 0),
            'resultado_anterior' => $anterior,
            'resultado_decisao' => $decisao,
            'justificativa' => $justificativa,
            'registrado_por' => $usuarioId,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function encaminhar(int $sessaoId, array $input, int $usuarioId): array
    {
        $sessao = $this->model->findById($sessaoId);
        if (!$sessao) {
            return ['success' => false, 'error' => 'Conselho não encontrado'];
        }
        if (!$this->podeRegistrar($sessao)) {
            return ['success' => false, 'error' => 'Encaminhamento só é permitido com o Conselho em andamento ou reaberto'];
        }

        $alunoId = (int) ($input['aluno_id'] ?? 0);
        if (!$this->model->alunoDaTurma($alunoId, (int) $sessao['turma_id'])) {
            return ['success' => false, 'error' => 'Aluno não pertence a esta turma'];
        }

        $tipo = trim((string) ($input['tipo'] ?? ''));
        if (!isset(ConselhoSessao::ENCAMINHAMENTOS[$tipo])) {
            return ['success' => false, 'error' => 'Tipo de encaminhamento inválido'];
        }
        $detalhe = trim((string) ($input['detalhe'] ?? ''));
        if ($detalhe === '') {
            return ['success' => false, 'error' => 'Descreva o encaminhamento'];
        }

        $ocorrenciaId = null;
        if (!empty($input['gerar_ocorrencia']) && $this->moduloOcorrenciasHabilitado() && $this->model->tabelaExiste('alunos_ocorrencias')) {
            require_once __DIR__ . '/../../ocorrencias/Services/OcorrenciaService.php';
            $result = (new OcorrenciaService())->criar([
                'aluno_id' => $alunoId,
                'titulo' => 'Encaminhamento do Conselho de Classe (' . $this->periodoLabel((int) $sessao['bimestre']) . ') — ' . (ConselhoSessao::ENCAMINHAMENTOS[$tipo] ?? $tipo),
                'detalhe' => $detalhe,
                'nivel_gravidade' => 'moderado',
                'data_ocorrencia' => date('Y-m-d H:i:s'),
                'categoria_id' => $this->categoriaPedagogicaId(),
            ], $usuarioId, 'admin');
            if (!empty($result['success'])) {
                $ocorrenciaId = (int) ($result['id'] ?? 0) ?: null;
            }
        }

        $this->model->inserirEncaminhamento([
            'sessao_id' => $sessaoId,
            'aluno_id' => $alunoId,
            'tipo' => $tipo,
            'detalhe' => $detalhe,
            'ocorrencia_id' => $ocorrenciaId,
            'criado_por' => $usuarioId,
        ]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function salvarParticipantes(int $sessaoId, array $input): array
    {
        $sessao = $this->model->findById($sessaoId);
        if (!$sessao) {
            return ['success' => false, 'error' => 'Conselho não encontrado'];
        }
        if ((string) $sessao['status'] === 'finalizado') {
            return ['success' => false, 'error' => 'Reabra o Conselho para alterar participantes'];
        }

        $nomes = $input['nome'] ?? [];
        $cargos = $input['cargo'] ?? [];
        $presentes = $input['presente'] ?? [];
        $professores = $input['professor_id'] ?? [];
        if (!is_array($nomes)) {
            return ['success' => false, 'error' => 'Lista de participantes inválida'];
        }

        $linhas = [];
        foreach ($nomes as $idx => $nome) {
            $cargo = (string) ($cargos[$idx] ?? 'professor');
            $linhas[] = [
                'nome' => $nome,
                'cargo' => isset(ConselhoSessao::CARGOS[$cargo]) ? $cargo : 'outro',
                'presente' => !empty($presentes[$idx]) ? 1 : 0,
                'professor_id' => $professores[$idx] ?? null,
            ];
        }
        $this->model->substituirParticipantes($sessaoId, $linhas);
        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function gerarAta(int $sessaoId, array $input, int $usuarioId): array
    {
        $sessao = $this->model->findById($sessaoId);
        if (!$sessao) {
            return ['success' => false, 'error' => 'Conselho não encontrado'];
        }
        if ((string) $sessao['status'] === 'em_preparacao') {
            return ['success' => false, 'error' => 'Coloque o Conselho em andamento antes de gerar a ata'];
        }
        if ((string) $sessao['status'] === 'finalizado' && $this->model->getAta($sessaoId)) {
            return ['success' => false, 'error' => 'Reabra o Conselho para alterar a ata já gerada'];
        }

        $matriz = $this->matriz($sessao);
        $snapshot = [
            'sessao' => [
                'id' => (int) $sessao['id'],
                'turma' => (string) $sessao['turma_nome'],
                'ano_letivo' => (int) $sessao['ano_letivo'],
                'bimestre' => (int) $sessao['bimestre'],
                'status' => (string) $sessao['status'],
                'data_reuniao' => $sessao['data_reuniao'] ?? null,
            ],
            'participantes' => $matriz['participantes'],
            'linhas' => array_map(static function (array $linha): array {
                return [
                    'aluno_id' => (int) $linha['aluno']['id'],
                    'aluno_nome' => (string) $linha['aluno']['nome'],
                    'resultado_preliminar' => $linha['resultado_preliminar']['codigo'] ?? null,
                    'resultado_homologado' => $linha['resultado_homologado'],
                    'frequencia' => $linha['frequencia']['percentual'] ?? null,
                ];
            }, $matriz['linhas']),
            'deliberacoes' => $this->model->listarDeliberacoes($sessaoId),
            'encaminhamentos' => $this->model->listarEncaminhamentos($sessaoId),
        ];

        $this->model->salvarAta($sessaoId, [
            'pauta' => trim((string) ($input['pauta'] ?? $sessao['pauta'] ?? '')) ?: null,
            'sintese' => trim((string) ($input['sintese'] ?? '')) ?: null,
            'decisoes' => trim((string) ($input['decisoes'] ?? '')) ?: null,
            'conteudo_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'gerada_por' => $usuarioId,
        ]);
        if (trim((string) ($input['pauta'] ?? '')) !== '') {
            $this->model->atualizarDados(
                $sessaoId,
                $sessao['data_reuniao'] ?? null,
                trim((string) $input['pauta'])
            );
        }
        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function registrarObservacao(int $sessaoId, int $alunoId, int $professorId, string $texto): array
    {
        $sessao = $this->model->findById($sessaoId);
        if (!$sessao) {
            return ['success' => false, 'error' => 'Conselho não encontrado'];
        }
        if (!$this->model->professorTemTurma($professorId, (int) $sessao['turma_id'])) {
            return ['success' => false, 'error' => 'Conselho fora das suas turmas'];
        }
        if (!$this->podeRegistrar($sessao)) {
            return ['success' => false, 'error' => 'Observação só pode ser registrada com o Conselho em andamento ou reaberto'];
        }
        if (!$this->model->alunoDaTurma($alunoId, (int) $sessao['turma_id'])) {
            return ['success' => false, 'error' => 'Aluno inválido nesta turma'];
        }
        $texto = trim($texto);
        if ($texto === '') {
            return ['success' => false, 'error' => 'Escreva a observação'];
        }
        $this->model->salvarObservacao($sessaoId, $alunoId, $professorId, $texto);
        return ['success' => true];
    }

    public function periodoLabel(int $bimestre): string
    {
        $bimestre = max(1, min(4, $bimestre));
        return $bimestre . 'º Bimestre';
    }

    public static function statusLabel(string $status): string
    {
        if ($status === 'nao_iniciado') {
            return 'Não iniciado';
        }
        return ConselhoSessao::STATUS[$status] ?? $status;
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'finalizado' => 'ativo',
            'em_andamento' => 'info',
            'reaberto' => 'pendente',
            'em_preparacao' => 'rascunho',
            default => 'neutro',
        };
    }

    /**
     * Hook para o boletim: publicação só deve esperar Conselho se a escola
     * passar a exigir. Sem essa config, sempre libera.
     */
    public function boletimPodePublicar(int $turmaId, int $anoLetivo, int $bimestre, bool $exigirConselho = false): bool
    {
        if (!$exigirConselho) {
            return true;
        }
        $sessao = $this->model->findByTurmaPeriodo($turmaId, $anoLetivo, $bimestre);
        return $sessao !== null && (string) $sessao['status'] === 'finalizado';
    }

    private function sugerirParticipantes(int $sessaoId, int $turmaId): void
    {
        $linhas = [];
        foreach ($this->model->professoresDaTurma($turmaId) as $prof) {
            $linhas[] = [
                'professor_id' => (int) $prof['id'],
                'nome' => (string) $prof['nome'],
                'cargo' => 'professor',
                'presente' => 1,
            ];
        }
        if ($linhas !== []) {
            $this->model->substituirParticipantes($sessaoId, $linhas);
        }
    }

    /**
     * @return array{total:int, diarios:int, notas:int, frequencia:int}
     */
    private function contarPendencias(int $turmaId, int $anoLetivo, int $bimestre): array
    {
        $esperados = $this->model->contarDiariosEsperados($turmaId);
        $fechados = $this->model->contarDiariosFechados($turmaId, $anoLetivo, $bimestre);
        $diarios = max(0, $esperados - $fechados);

        $alunos = $this->model->alunosDaTurma($turmaId);
        $notas = $this->notasDoBoletim($turmaId, $anoLetivo, $bimestre);
        $freqs = $this->frequenciasPorAluno($turmaId, $anoLetivo, $bimestre);
        $semNota = 0;
        $baixaFreq = 0;
        foreach ($alunos as $aluno) {
            if (!empty($aluno['transferido'])) {
                continue;
            }
            $id = (int) $aluno['id'];
            if (empty($notas['por_aluno'][$id])) {
                $semNota++;
            }
            $pct = $freqs[$id]['percentual'] ?? null;
            if ($pct !== null && $pct < FrequencyService::MINIMO_LEGAL) {
                $baixaFreq++;
            }
        }

        return [
            'total' => $diarios + $semNota + $baixaFreq,
            'diarios' => $diarios,
            'notas' => $semNota,
            'frequencia' => $baixaFreq,
        ];
    }

    /**
     * Consome o snapshot já gerado do boletim. Não recalcula fórmula.
     *
     * @return array{componentes:list<array{id:?int,nome:string}>, por_aluno:array<int,array<string,array<string,mixed>>>, nota_minima:float}
     */
    private function notasDoBoletim(int $turmaId, int $anoLetivo, int $bimestre): array
    {
        $vazio = ['componentes' => [], 'por_aluno' => [], 'nota_minima' => 6.0];
        if (!$this->model->tabelaExiste('boletim_resultados_gerados') || !$this->model->tabelaExiste('boletim_regras')) {
            return $vazio;
        }

        $params = ['turma_id' => $turmaId, 'ano_letivo' => $anoLetivo, 'bimestre' => $bimestre];
        $rows = $this->db->fetchAll(
            "SELECT g.aluno_id, g.materia_id, g.materia_nome, g.media_final, g.notas_json,
                    r.nota_minima_aprovacao
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             INNER JOIN alunos a ON a.id = g.aluno_id
             WHERE g.preview = 0 AND g.vigente = 1 AND a.turma_id = :turma_id
               AND r.ano_letivo = :ano_letivo AND r.bimestre = :bimestre
             ORDER BY g.ordem_linha ASC, g.id ASC",
            $params
        ) ?: [];

        $componentes = [];
        $vistosComp = [];
        $porAluno = [];
        $notaMinima = 6.0;
        foreach ($rows as $row) {
            $nome = trim((string) ($row['materia_nome'] ?? ''));
            if ($nome === '') {
                $nome = 'Sem componente';
            }
            $chave = mb_strtolower($nome);
            if (!isset($vistosComp[$chave])) {
                $vistosComp[$chave] = true;
                $componentes[] = [
                    'id' => isset($row['materia_id']) && $row['materia_id'] !== null ? (int) $row['materia_id'] : null,
                    'nome' => $nome,
                ];
            }
            if (isset($row['nota_minima_aprovacao']) && is_numeric($row['nota_minima_aprovacao'])) {
                $notaMinima = (float) $row['nota_minima_aprovacao'];
            }
            $media = $row['media_final'];
            if ($media === null || $media === '') {
                $notasJson = json_decode((string) ($row['notas_json'] ?? ''), true);
                if (is_array($notasJson)) {
                    foreach (['media_final', 'media_bim', 'media'] as $codigo) {
                        if (isset($notasJson[$codigo]) && is_numeric($notasJson[$codigo])) {
                            $media = $notasJson[$codigo];
                            break;
                        }
                    }
                }
            }
            $alunoId = (int) $row['aluno_id'];
            $porAluno[$alunoId][$chave] = [
                'nome' => $nome,
                'materia_id' => isset($row['materia_id']) && $row['materia_id'] !== null ? (int) $row['materia_id'] : null,
                'media' => is_numeric($media) ? (float) $media : null,
            ];
        }

        $vazio['componentes'] = $componentes;
        $vazio['por_aluno'] = $porAluno;
        $vazio['nota_minima'] = $notaMinima;
        return $vazio;
    }

    /**
     * @return array<int,array{percentual:?float,faltas:int,total_aulas:int}>
     */
    private function frequenciasPorAluno(int $turmaId, int $anoLetivo, int $bimestre): array
    {
        $periodo = $this->diario->periodoDoBimestre($anoLetivo, $bimestre);
        $lista = $this->frequencia->alunosPercentual($turmaId, $periodo['inicio'], $periodo['fim']);
        $out = [];
        foreach ($lista as $item) {
            $out[(int) $item['aluno_id']] = [
                'percentual' => $item['percentual'],
                'faltas' => (int) ($item['faltas'] ?? 0),
                'total_aulas' => (int) ($item['total_aulas'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,array<string,mixed>> $notasAluno
     * @param array<string,mixed>|null $freq
     * @return array{codigo:string,label:string,detalhe:string,abaixo:list<string>}
     */
    private function resultadoPreliminar(array $notasAluno, ?array $freq, float $notaMinima, bool $transferido): array
    {
        if ($transferido) {
            return [
                'codigo' => 'transferido',
                'label' => 'Transferido',
                'detalhe' => 'Aluno saiu da turma no período.',
                'abaixo' => [],
            ];
        }

        $abaixo = [];
        $temNota = false;
        foreach ($notasAluno as $comp) {
            if (!isset($comp['media']) || $comp['media'] === null) {
                continue;
            }
            $temNota = true;
            if ((float) $comp['media'] < $notaMinima) {
                $abaixo[] = (string) $comp['nome'];
            }
        }

        $pct = $freq['percentual'] ?? null;
        $baixaFreq = $pct !== null && $pct < FrequencyService::MINIMO_LEGAL;

        if (!$temNota) {
            return [
                'codigo' => 'sem_notas',
                'label' => 'Sem notas',
                'detalhe' => 'Boletim ainda não gerado para este aluno neste período.',
                'abaixo' => [],
            ];
        }

        if ($abaixo !== []) {
            $nomes = implode(', ', $abaixo);
            return [
                'codigo' => 'recuperacao',
                'label' => 'Recuperação',
                'detalhe' => 'Abaixo do critério: ' . $nomes,
                'abaixo' => $abaixo,
            ];
        }

        if ($baixaFreq) {
            return [
                'codigo' => 'baixa_frequencia',
                'label' => 'Baixa frequência',
                'detalhe' => 'Frequência ' . number_format((float) $pct, 1, ',', '.') . '% (mínimo legal 75%).',
                'abaixo' => [],
            ];
        }

        return [
            'codigo' => 'aprovado',
            'label' => 'Aprovado',
            'detalhe' => 'Médias e frequência dentro do critério do boletim gerado.',
            'abaixo' => [],
        ];
    }

    private function moduloOcorrenciasHabilitado(): bool
    {
        if (!class_exists('LayoutHelper', false)) {
            require_once __DIR__ . '/../../../Core/LayoutHelper.php';
        }
        if (!class_exists('LayoutHelper')) {
            return true;
        }
        return LayoutHelper::isModuleEnabled('ocorrencias');
    }

    private function categoriaPedagogicaId(): int
    {
        if (!$this->model->tabelaExiste('ocorrencias_categorias')) {
            return 0;
        }
        $row = $this->db->fetch(
            "SELECT id FROM ocorrencias_categorias WHERE slug = 'pedagogica' AND ativo = 1 LIMIT 1"
        );
        return (int) ($row['id'] ?? 0);
    }
}
