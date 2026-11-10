<?php

namespace App\Modulos\VidaEscolar\Services;


use App\Services\DeclarationService;
use App\Services\HistoricoEscolarService;
use App\Core\Database;
use App\Models\Education\HistoricoDocumento;
use App\Models\Education\ResultadoAcademico;
use App\Models\User\StudentDocument;
/**
 * Monta o prontuário do aluno: identidade, documentos, conferência SED/INEP
 * e pacote de transferência. Não recalcula nota.
 */
class ProntuarioVidaEscolarService
{
    public const ABAS = ['identidade', 'trajetoria', 'boletim', 'documentos', 'conferencia'];

    private DeclarationService $declaracoes;
    private HistoricoEscolarService $historico;
    private $db;

    public function __construct()
    {
        $this->declaracoes = new DeclarationService();
        $this->historico = new HistoricoEscolarService();
        $this->db = Database::getInstance();
    }

    public static function abaValida(?string $aba): string
    {
        $aba = strtolower(trim((string) $aba));
        return in_array($aba, self::ABAS, true) ? $aba : 'identidade';
    }

    /**
     * @return array<string,mixed>
     */
    public function montar(int $alunoId, VidaEscolarService $vida, int $fichaId = 0, bool $comQuadroImpressao = true): array
    {
        $aluno = $vida->model()->alunoPorId($alunoId);
        if (!$aluno) {
            return [];
        }
        $matricula = $this->declaracoes->getMatriculaAtiva($alunoId);
        $unidade = $this->declaracoes->getUnidadeForAluno($aluno);
        $checklistHistorico = $this->historico->schemaPronto()
            ? $this->historico->checklist($alunoId)
            : ['ok' => false, 'itens' => []];
        $sed = $this->checklistSed($aluno, $matricula, $unidade, $checklistHistorico);
        $inep = $this->snapshotInep($alunoId, $aluno, $unidade);
        $this->reconhecerEntregasExternas($alunoId, $vida);
        $docsChecklist = $this->checklistEntrega($alunoId);
        $docsRecebidos = $vida->model()->schemaPronto() ? $vida->model()->listarDocumentos($alunoId) : [];
        $historicos = $this->historicosOficiais($alunoId);
        $resultados = $this->resultadosHomologados($alunoId);
        $emissoes = $this->emissoesDeclaracao($alunoId);
        $trajetoria = $vida->trajetoria($alunoId);
        if ($vida->model()->schemaPronto()) {
            try {
                $vida->materializarFichaOficialSeFaltar($alunoId);
            } catch (\Throwable $e) {
                error_log('Vida escolar materializar ficha aluno #' . $alunoId . ': ' . $e->getMessage());
            }
            try {
                $this->garantirFichasMatriculasAtivas($alunoId, $vida);
            } catch (\Throwable $e) {
                error_log('Vida escolar fichas matrículas ativas aluno #' . $alunoId . ': ' . $e->getMessage());
            }
        }
        $fichas = $vida->model()->schemaPronto() ? $vida->model()->listarFichasAluno($alunoId) : [];
        $fichas = $this->enriquecerFichasComModelo($fichas, $alunoId);

        if ($fichaId > 0) {
            $pertence = false;
            foreach ($fichas as $f) {
                if ((int) ($f['id'] ?? 0) === $fichaId) {
                    $pertence = true;
                    break;
                }
            }
            if (!$pertence) {
                $fichaId = 0;
            }
        }
        if ($fichaId <= 0 && $fichas !== []) {
            $fichaId = (int) $fichas[0]['id'];
        }
        if ($fichaId > 0) {
            $vida->recolherDesdobramentosSePrecisar($fichaId);
        }
        $quadro = $fichaId > 0 ? $vida->quadro($fichaId) : null;
        $fichaAtual = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : [];
        $quadroImpressaoHtml = $comQuadroImpressao
            ? $this->htmlQuadroImpressaoDoAluno($alunoId, $fichaAtual, is_array($quadro) ? $quadro : null)
            : '';

        $capa = $this->capa($aluno, $matricula, $quadro, $docsChecklist, $sed, $inep, $historicos, $trajetoria);

        return [
            'aluno' => $aluno,
            'matricula' => $matricula,
            'unidade' => $unidade,
            'capa' => $capa,
            'sed' => $sed,
            'inep' => $inep,
            'docs_checklist' => $docsChecklist,
            'docs_recebidos' => $docsRecebidos,
            'historicos' => $historicos,
            'resultados' => $resultados,
            'emissoes' => $emissoes,
            'trajetoria' => $trajetoria,
            'escola_anterior' => $this->escolaAnteriorDoAluno($alunoId),
            'fichas' => $fichas,
            'ficha_id' => $fichaId,
            'quadro' => $quadro,
            'quadro_impressao_html' => $quadroImpressaoHtml,
            'links' => $this->links($alunoId, $aluno, $fichaId),
        ];
    }

    /**
     * Linhas prontas para digitação assistida no portal da SED.
     *
     * @param array<string,mixed> $prontuario
     * @return list<array{campo:string,valor:string}>
     */
    public function planilhaSed(array $prontuario): array
    {
        $aluno = is_array($prontuario['aluno'] ?? null) ? $prontuario['aluno'] : [];
        $mat = is_array($prontuario['matricula'] ?? null) ? $prontuario['matricula'] : [];
        $un = is_array($prontuario['unidade'] ?? null) ? $prontuario['unidade'] : [];
        $ficha = is_array($prontuario['quadro']['ficha'] ?? null) ? $prontuario['quadro']['ficha'] : [];
        $historico = is_array($prontuario['historicos'][0] ?? null) ? $prontuario['historicos'][0] : [];

        return [
            ['campo' => 'Nome do aluno', 'valor' => (string) (\App\Helpers\StudentFormHelper::nomeExibicao($aluno))],
            ['campo' => 'CPF / CIN', 'valor' => (string) ($aluno['cpf'] ?? '')],
            ['campo' => 'RG', 'valor' => (string) ($aluno['rg'] ?? '')],
            ['campo' => 'Data de nascimento', 'valor' => $this->dataBr($aluno['data_nasc'] ?? null)],
            ['campo' => 'Nome da mãe', 'valor' => (string) ($aluno['nome_mae'] ?? '')],
            ['campo' => 'Nome do pai', 'valor' => (string) ($aluno['nome_pai'] ?? '')],
            ['campo' => 'Sexo', 'valor' => (string) ($aluno['sexo'] ?? '')],
            ['campo' => 'Cor/raça', 'valor' => (string) ($aluno['cor_raca'] ?? '')],
            ['campo' => 'Nacionalidade', 'valor' => (string) ($aluno['nacionalidade'] ?? '')],
            ['campo' => 'Código INEP do aluno', 'valor' => (string) ($aluno['codigo_inep'] ?? '')],
            ['campo' => 'Escola', 'valor' => (string) ($un['nome'] ?? '')],
            ['campo' => 'INEP da escola', 'valor' => (string) ($un['inep'] ?? '')],
            ['campo' => 'CNPJ', 'valor' => (string) ($un['cnpj'] ?? '')],
            ['campo' => 'Turma', 'valor' => (string) ($aluno['turma_nome'] ?? $mat['turma_nome'] ?? '')],
            ['campo' => 'Série/ano', 'valor' => (string) ($aluno['turma_serie'] ?? $mat['turma_serie'] ?? $ficha['serie_nome'] ?? '')],
            ['campo' => 'Ano letivo', 'valor' => (string) ((int) ($mat['ano_letivo'] ?? $aluno['turma_ano_letivo'] ?? $ficha['ano_letivo'] ?? 0) ?: '')],
            ['campo' => 'Data de matrícula', 'valor' => $this->dataBr($mat['data_entrada'] ?? null)],
            ['campo' => 'Data de saída', 'valor' => $this->dataBr($mat['data_saida'] ?? null)],
            ['campo' => 'Situação da matrícula', 'valor' => (string) ($mat['status'] ?? $this->situacaoAluno($aluno))],
            ['campo' => 'Nº registro SED / GDAE', 'valor' => (string) ($historico['numero_registro_sed'] ?? '')],
            ['campo' => 'Status da ficha do boletim', 'valor' => (string) ($ficha['status'] ?? 'sem ficha')],
        ];
    }

    /**
     * @param array<string,mixed> $aluno
     * @param array<string,mixed>|null $matricula
     * @param array<string,mixed>|null $quadro
     * @param array<string,mixed> $docsChecklist
     * @param array<string,mixed> $sed
     * @param array<string,mixed> $inep
     * @param list<array<string,mixed>> $historicos
     * @param array<string,mixed> $trajetoria
     * @return array<string,mixed>
     */
    private function escolaAnteriorDoAluno(int $alunoId): string
    {
        if ($alunoId <= 0) {
            return '';
        }
        try {
            $row = $this->db->fetch(
                "SELECT aluno_escola_anterior
                 FROM matricula_processos
                 WHERE aluno_id = :id
                   AND aluno_escola_anterior IS NOT NULL
                   AND TRIM(aluno_escola_anterior) <> ''
                 ORDER BY id DESC
                 LIMIT 1",
                ['id' => $alunoId]
            );
        } catch (\Throwable $e) {
            return '';
        }
        return is_array($row) ? trim((string) ($row['aluno_escola_anterior'] ?? '')) : '';
    }

    private function capa(
        array $aluno,
        ?array $matricula,
        ?array $quadro,
        array $docsChecklist,
        array $sed,
        array $inep,
        array $historicos,
        array $trajetoria
    ): array {
        $ficha = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : [];
        $statusFicha = (string) ($ficha['status'] ?? '');
        $entregues = (int) ($docsChecklist['entregues'] ?? 0);
        $totalDocs = (int) ($docsChecklist['total'] ?? 0);
        $sedOk = (int) ($sed['ok_qtd'] ?? 0);
        $sedTotal = (int) ($sed['total'] ?? 0);
        $historicoEmitido = false;
        foreach ($historicos as $h) {
            if (in_array((string) ($h['status'] ?? ''), ['Emitido', 'Assinado'], true)) {
                $historicoEmitido = true;
                break;
            }
        }

        return [
            'situacao' => $this->situacaoAluno($aluno, $matricula),
            'turma' => (string) ($aluno['turma_nome'] ?? $matricula['turma_nome'] ?? ''),
            'serie' => (string) ($aluno['turma_serie'] ?? $matricula['turma_serie'] ?? $ficha['serie_nome'] ?? ''),
            'ano_letivo' => (int) ($matricula['ano_letivo'] ?? $aluno['turma_ano_letivo'] ?? $ficha['ano_letivo'] ?? 0),
            'status_ficha' => $statusFicha !== '' ? $statusFicha : 'sem_ficha',
            'status_ficha_label' => $this->labelFicha($statusFicha),
            'docs_txt' => $totalDocs > 0 ? ($entregues . '/' . $totalDocs . ' entregues') : 'Sem checklist',
            'docs_ok' => $totalDocs > 0 && $entregues >= $totalDocs,
            'sed_txt' => $sedTotal > 0 ? ($sedOk . '/' . $sedTotal . ' campos') : '—',
            'sed_ok' => !empty($sed['ok']),
            'inep_txt' => (string) ($inep['resumo'] ?? 'Censo não iniciado'),
            'inep_ok' => !empty($inep['ok']),
            'historico_emitido' => $historicoEmitido,
            'anos_trajetoria' => count($trajetoria['anos'] ?? []),
        ];
    }

    /**
     * @param array<string,mixed> $aluno
     * @param array<string,mixed>|null $matricula
     */
    private function situacaoAluno(array $aluno, ?array $matricula = null): string
    {
        $st = strtolower(trim((string) ($matricula['status'] ?? '')));
        if ($st === 'transferido') {
            return 'Transferido';
        }
        if ($st === 'concluido' || $st === 'concluinte') {
            return 'Concluinte';
        }
        if ($st === 'cancelado' || $st === 'inativo') {
            return 'Inativo';
        }
        if ((int) ($aluno['ativo'] ?? 1) !== 1) {
            return 'Inativo';
        }
        if ($st === 'ativa' || $st === 'ativo' || $st === '') {
            return 'Cursando';
        }
        return ucfirst($st);
    }

    private function labelFicha(string $status): string
    {
        return match ($status) {
            'homologada' => 'Homologada',
            'fechada' => 'Fechada',
            'em_curso' => 'Em curso',
            default => 'Sem ficha',
        };
    }

    /**
     * @param array<string,mixed> $aluno
     * @param array<string,mixed>|null $matricula
     * @param array<string,mixed>|null $unidade
     * @param array{ok:bool,itens:list} $checklistHistorico
     * @return array{ok:bool,ok_qtd:int,total:int,itens:list<array{chave:string,ok:bool,mensagem:string,obrigatorio:bool}>}
     */
    private function checklistSed(array $aluno, ?array $matricula, ?array $unidade, array $checklistHistorico): array
    {
        $itens = [];
        $add = static function (string $chave, bool $ok, string $msg, bool $obrigatorio = true) use (&$itens): void {
            $itens[] = ['chave' => $chave, 'ok' => $ok, 'mensagem' => $msg, 'obrigatorio' => $obrigatorio];
        };

        $add('nome', trim((string) ($aluno['nome'] ?? '')) !== '', 'Nome completo');
        $add('nascimento', $this->dataValida($aluno['data_nasc'] ?? null), 'Data de nascimento');
        $add(
            'documento',
            trim((string) ($aluno['cpf'] ?? '')) !== '' || trim((string) ($aluno['rg'] ?? '')) !== '',
            'CPF ou RG'
        );
        $add(
            'filiacao',
            trim((string) ($aluno['nome_mae'] ?? '')) !== '' || trim((string) ($aluno['nome_pai'] ?? '')) !== '',
            'Filiação (mãe e/ou pai)'
        );
        $add('sexo', trim((string) ($aluno['sexo'] ?? '')) !== '', 'Sexo');
        $add('cor_raca', trim((string) ($aluno['cor_raca'] ?? '')) !== '', 'Cor/raça', false);
        $add('nacionalidade', trim((string) ($aluno['nacionalidade'] ?? '')) !== '', 'Nacionalidade');
        $add('turma', trim((string) ($aluno['turma_nome'] ?? $matricula['turma_nome'] ?? '')) !== '', 'Turma atual');
        $add('data_matricula', $this->dataValida($matricula['data_entrada'] ?? null), 'Data de matrícula');
        $add('inep_escola', $unidade && trim((string) ($unidade['inep'] ?? '')) !== '', 'Código INEP da escola');
        $add('cnpj', $unidade && trim((string) ($unidade['cnpj'] ?? '')) !== '', 'CNPJ da unidade');
        $add('codigo_inep_aluno', trim((string) ($aluno['codigo_inep'] ?? '')) !== '', 'Código INEP do aluno', false);

        foreach ($checklistHistorico['itens'] ?? [] as $item) {
            $chave = (string) ($item['chave'] ?? '');
            if (in_array($chave, ['diretor', 'secretario'], true)) {
                $add($chave, !empty($item['ok']), (string) ($item['mensagem'] ?? $chave), false);
            }
        }

        $okQtd = 0;
        $obrigatoriosOk = true;
        foreach ($itens as $i) {
            if ($i['ok']) {
                $okQtd++;
            } elseif ($i['obrigatorio']) {
                $obrigatoriosOk = false;
            }
        }

        return [
            'ok' => $obrigatoriosOk,
            'ok_qtd' => $okQtd,
            'total' => count($itens),
            'itens' => $itens,
        ];
    }

    /**
     * @param array<string,mixed> $aluno
     * @param array<string,mixed>|null $unidade
     * @return array<string,mixed>
     */
    private function snapshotInep(int $alunoId, array $aluno, ?array $unidade): array
    {
        $path = dirname(__DIR__, 2) . '/censo-escolar/Models/CensoEdicao.php';
        $edicoes = [];
        if (is_file($path)) {
            require_once $path;
            try {
                $censo = new \App\Modulos\CensoEscolar\Models\CensoEdicao();
                if ($censo->schemaPronto()) {
                    foreach ($censo->listar(8) as $ed) {
                        $edicaoId = (int) ($ed['id'] ?? 0);
                        if ($edicaoId <= 0) {
                            continue;
                        }
                        $mat = $censo->buscarPorChaves('censo_matriculas', [
                            'edicao_id' => $edicaoId,
                            'aluno_id' => $alunoId,
                        ]);
                        $sit = $censo->buscarPorChaves('censo_situacoes_aluno', [
                            'edicao_id' => $edicaoId,
                            'aluno_id' => $alunoId,
                        ]);
                        $edicoes[] = [
                            'id' => $edicaoId,
                            'ano' => (int) ($ed['ano'] ?? 0),
                            'etapa' => (string) ($ed['etapa_coleta'] ?? ''),
                            'status' => (string) ($ed['status'] ?? ''),
                            'matricula' => is_array($mat),
                            'situacao' => is_array($sit) ? (string) ($sit['situacao'] ?? $sit['codigo_situacao'] ?? 'registrada') : '',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log('Prontuario INEP: ' . $e->getMessage());
            }
        }

        $temMatricula = false;
        foreach ($edicoes as $ed) {
            if (!empty($ed['matricula'])) {
                $temMatricula = true;
                break;
            }
        }
        $inepAluno = trim((string) ($aluno['codigo_inep'] ?? ''));
        $inepEscola = $unidade ? trim((string) ($unidade['inep'] ?? '')) : '';

        $resumo = 'Censo não iniciado nesta escola';
        if ($edicoes !== []) {
            $resumo = $temMatricula ? 'Matrícula no Censo' : 'Edição aberta — aluno ainda não sincronizado';
        } elseif ($inepEscola !== '') {
            $resumo = 'INEP da escola ok · Censo sem edição';
        }

        return [
            'ok' => $inepEscola !== '' && ($temMatricula || $edicoes === []),
            'resumo' => $resumo,
            'codigo_aluno' => $inepAluno,
            'codigo_escola' => $inepEscola,
            'edicoes' => $edicoes,
        ];
    }

    /**
     * Anexos da vida escolar ou da matrícula passam a contar no checklist da ficha.
     */
    public function reconhecerEntregasExternas(int $alunoId, ?VidaEscolarService $vida = null): void
    {
        if ($alunoId <= 0) {
            return;
        }
        $model = new StudentDocument();
        if (!$model->tableExists()) {
            return;
        }
        $ja = [];
        foreach ($model->getByAluno($alunoId) as $row) {
            $tipo = (string) ($row['tipo'] ?? '');
            $st = (string) ($row['status'] ?? '');
            if ($tipo !== '' && ($st === 'entregue' || $st === 'dispensado')) {
                $ja[$tipo] = true;
            }
        }

        $vida = $vida ?? new VidaEscolarService();
        $encontrados = $this->tiposRecebidosForaDaFicha($alunoId, $vida);
        foreach ($encontrados as $tipo => $meta) {
            if (!empty($ja[$tipo])) {
                continue;
            }
            $model->save($alunoId, $tipo, [
                'status' => 'entregue',
                'titulo' => $meta['titulo'] ?? null,
                'observacao' => 'Reconhecido a partir do prontuário ou da matrícula.',
                'arquivo_key' => $meta['arquivo_key'] ?? null,
                'arquivo_nome' => $meta['arquivo_nome'] ?? null,
                'arquivo_mime' => $meta['arquivo_mime'] ?? null,
                'arquivo_tamanho' => $meta['arquivo_tamanho'] ?? null,
            ]);
        }
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function tiposRecebidosForaDaFicha(int $alunoId, VidaEscolarService $vida): array
    {
        $mapVida = [
            'historico' => 'historico_escolar',
            'declaracao_transferencia' => 'declaracao_transferencia',
        ];
        $out = [];
        if ($vida->model()->schemaPronto()) {
            foreach ($vida->model()->listarDocumentos($alunoId) as $d) {
                $tipo = $mapVida[(string) ($d['tipo'] ?? '')] ?? '';
                if ($tipo === '' || empty($d['arquivo_key'])) {
                    continue;
                }
                $out[$tipo] = [
                    'titulo' => (string) ($d['arquivo_nome'] ?? ''),
                    'arquivo_key' => $d['arquivo_key'],
                    'arquivo_nome' => $d['arquivo_nome'] ?? null,
                    'arquivo_mime' => $d['arquivo_mime'] ?? null,
                    'arquivo_tamanho' => $d['arquivo_tamanho'] ?? null,
                ];
            }
        }

        $mapMat = [
            'historico' => 'historico_escolar',
            'declaracao_transferencia' => 'declaracao_transferencia',
            'rg' => 'rg',
            'cpf' => 'cpf',
            'certidao' => 'certidao_nascimento',
            'comprovante_residencia' => 'comprovante_residencia',
        ];
        try {
            $temProc = $this->db->fetch("SHOW TABLES LIKE 'matricula_processos'");
            $temDocs = $this->db->fetch("SHOW TABLES LIKE 'matricula_processos_documentos'");
            if ($temProc && $temDocs) {
                $rows = $this->db->fetchAll(
                    'SELECT d.tipo, d.nome_original, d.path, d.mime, d.tamanho
                     FROM matricula_processos_documentos d
                     INNER JOIN matricula_processos p ON p.id = d.enrollment_id
                     WHERE p.aluno_id = :id',
                    ['id' => $alunoId]
                );
                foreach (is_array($rows) ? $rows : [] as $d) {
                    $tipo = $mapMat[(string) ($d['tipo'] ?? '')] ?? '';
                    if ($tipo === '' || isset($out[$tipo])) {
                        continue;
                    }
                    $out[$tipo] = [
                        'titulo' => (string) ($d['nome_original'] ?? ''),
                        'arquivo_key' => $d['path'] ?? null,
                        'arquivo_nome' => $d['nome_original'] ?? null,
                        'arquivo_mime' => $d['mime'] ?? null,
                        'arquivo_tamanho' => $d['tamanho'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // checklist da ficha continua mesmo se o módulo de matrícula não existir
        }

        return $out;
    }

    /**
     * @return array{itens:list<array<string,mixed>>,entregues:int,total:int}
     */
    private function checklistEntrega(int $alunoId): array
    {
        $model = new StudentDocument();
        $checklist = StudentDocument::checklist();
        $rows = $model->getByAluno($alunoId);
        $porTipo = [];
        $outros = [];
        foreach ($rows as $row) {
            if (($row['tipo'] ?? '') === 'outros') {
                $outros[] = $row;
            } else {
                $porTipo[$row['tipo'] ?? ''] = $row;
            }
        }
        $itens = [];
        $entregues = 0;
        $total = 0;
        foreach ($checklist as $tipo => $label) {
            if ($tipo === 'outros') {
                continue;
            }
            $total++;
            $row = $porTipo[$tipo] ?? null;
            $status = (string) ($row['status'] ?? 'pendente');
            if ($status === 'entregue' || $status === 'dispensado') {
                $entregues++;
            }
            $itens[] = [
                'tipo' => $tipo,
                'label' => $label,
                'status' => $status,
                'arquivo' => !empty($row['arquivo_key']),
            ];
        }
        foreach ($outros as $row) {
            $itens[] = [
                'tipo' => 'outros',
                'label' => StudentDocument::tipoLabel('outros', $row['titulo'] ?? null),
                'status' => (string) ($row['status'] ?? 'pendente'),
                'arquivo' => !empty($row['arquivo_key']),
            ];
        }

        return ['itens' => $itens, 'entregues' => $entregues, 'total' => $total];
    }

    /** @return list<array<string,mixed>> */
    private function historicosOficiais(int $alunoId): array
    {
        try {
            $model = new HistoricoDocumento();
            return $model->listarPorAluno($alunoId);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    private function resultadosHomologados(int $alunoId): array
    {
        try {
            $model = new ResultadoAcademico();
            if (!$model->schemaPronto()) {
                return [];
            }
            return $model->listarPorAluno($alunoId);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    private function emissoesDeclaracao(int $alunoId): array
    {
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'declaracoes_emitidas'");
            if (!$row) {
                return [];
            }
            $rows = $this->db->fetchAll(
                'SELECT * FROM declaracoes_emitidas WHERE aluno_id = :id ORDER BY id DESC LIMIT 40',
                ['id' => $alunoId]
            );
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $aluno
     * @return array<string,string>
     */
    private function links(int $alunoId, array $aluno, int $fichaId): array
    {
        $base = '/admin/students/' . $alunoId;
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        $qsTurma = $turmaId > 0 ? ('?turma_id=' . $turmaId) : '';
        $qsFicha = $fichaId > 0 ? ('?ficha_id=' . $fichaId) : '';

        return [
            'cadastro' => $base,
            'historico' => $base . '/historico-escolar',
            'declaracao_matricula' => $base . '/declaracoes/matricula/pdf',
            'declaracao_frequencia' => $base . '/declaracoes/frequencia/pdf',
            'declaracao_transferencia' => $base . '/declaracoes/transferencia/pdf',
            'ficha_matricula' => $base . '/declaracoes/ficha_matricula/pdf',
            'ficha_individual' => '/admin/resultados-finais/aluno/' . $alunoId . '/ficha' . $qsTurma,
            'boletim_oficial' => '/admin/resultados-finais/aluno/' . $alunoId . '/boletim/pdf' . $qsTurma,
            'boletim_ficha' => $base . '/vida-escolar/pdf' . $qsFicha,
            'pacote' => $base . '/vida-escolar/pacote-transferencia' . $qsFicha,
            'dossie' => $base . '/vida-escolar/dossie' . $qsFicha,
            'sed' => $base . '/vida-escolar/sed' . $qsFicha,
            'censo' => '/admin/censo',
            'resultados_finais' => '/admin/resultados-finais',
        ];
    }

    /**
     * HTML do quadro de impressão (PP/PU/CH…), para AJAX na aba Boletim do Detalhe.
     *
     * @param array<string,mixed> $ficha
     */
    public function htmlQuadroImpressaoParaAluno(int $alunoId, array $ficha): string
    {
        return $this->htmlQuadroImpressaoDoAluno($alunoId, $ficha, null);
    }

    /**
     * Mesmo layout do boletim criado no assistente (PP/PU/CH/PE/FAL…), para a Vida do Aluno e PDF.
     * Usa a regra do modelo da turma — não o Quadro SM genérico (MU/Situação).
     *
     * @param array<string,mixed> $ficha
     * @param array<string,mixed>|null $quadro
     */
    private function htmlQuadroImpressaoDoAluno(int $alunoId, array $ficha, ?array $quadro = null): string
    {
        unset($quadro);
        if ($alunoId <= 0) {
            return '';
        }
        // Quadro do assistente (PP/PU/CH…) só com Eventos Avaliativos / Demonstrativo ligado.
        if (!class_exists(\App\Core\LayoutHelper::class)
            || !\App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos')
        ) {
            return '';
        }
        $ano = (int) ($ficha['ano_letivo'] ?? 0);
        $turmaId = (int) ($ficha['turma_id'] ?? 0);
        $cursoTipo = 'regular';
        $serieId = 0;
        try {
            if ($turmaId > 0) {
                $rowTurma = $this->db->fetch(
                    "SELECT t.serie_id, COALESCE(c.tipo, 'regular') AS curso_tipo
                     FROM turmas t
                     LEFT JOIN curso c ON c.id = t.curso_novo_id
                     WHERE t.id = :id LIMIT 1",
                    ['id' => $turmaId]
                );
                if (is_array($rowTurma)) {
                    $serieId = (int) ($rowTurma['serie_id'] ?? 0);
                    $cursoTipo = strtolower(trim((string) ($rowTurma['curso_tipo'] ?? 'regular'))) === 'extra'
                        ? 'extra'
                        : 'regular';
                }
            }
        } catch (\Throwable $e) {
            $cursoTipo = 'regular';
        }
        $finalidadePref = $cursoTipo === 'extra' ? 'complementar' : 'oficial';

        $regraId = $this->regraIdDoModeloTurma($turmaId, $serieId, $ano, $finalidadePref);
        if ($regraId > 0) {
            $htmlRegra = $this->htmlDeRegraBoletimAluno($alunoId, $regraId);
            if ($htmlRegra !== '') {
                return $htmlRegra;
            }
        }

        $htmlGerado = $this->htmlDeBoletimGerado($alunoId, $ano, $finalidadePref, $turmaId);
        if ($htmlGerado !== '') {
            return $htmlGerado;
        }

        return '';
    }

    /**
     * Regra do quadro que o admin construiu (impressão), não o esqueleto Média/Faltas do cadastro.
     *
     * @param 'oficial'|'complementar' $finalidadePref
     */
    private function regraIdDoModeloTurma(int $turmaId, int $serieId, int $ano, string $finalidadePref): int
    {
        if ($turmaId <= 0 && $serieId <= 0) {
            return 0;
        }
        try {
            if (!class_exists(\App\Modulos\Boletins\Services\BoletimCadastroService::class)) {
                return 0;
            }
            $cadastro = new \App\Modulos\Boletins\Services\BoletimCadastroService();
            $modelo = $cadastro->encontrarModeloParaTurma($turmaId, $serieId, $ano, $finalidadePref);
            if ($modelo === null && $finalidadePref === 'complementar') {
                $modelo = $cadastro->encontrarModeloParaTurma($turmaId, $serieId, $ano, 'oficial');
            }
            if (!is_array($modelo)) {
                return 0;
            }
            $boletimId = (int) ($modelo['id'] ?? 0);
            $regraCadastro = (int) ($modelo['regra_id'] ?? 0);
            $regraImpressao = $this->regraIdImpressaoDoBoletim($boletimId, $regraCadastro);
            return $regraImpressao > 0 ? $regraImpressao : $regraCadastro;
        } catch (\Throwable $e) {
            error_log('Modelo boletim turma #' . $turmaId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Preferência: quadro-impressao / santa-maria / quadro livre do modelo (o que foi montado no assistente).
     * O boletins.regra_id costuma ser só o documento Média+Faltas.
     */
    private function regraIdImpressaoDoBoletim(int $boletimId, int $regraCadastro = 0): int
    {
        if ($boletimId <= 0) {
            return 0;
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT id, extras_json, updated_at
                 FROM boletim_regras
                 WHERE ativo = 1
                   AND exibir_em = 'boletim'
                   AND boletim_id = :bid
                 ORDER BY updated_at DESC, id DESC",
                ['bid' => $boletimId]
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('Regra impressão boletim #' . $boletimId . ': ' . $e->getMessage());
            return 0;
        }

        $melhor = 0;
        $melhorPts = -1;
        $melhorUpdated = -1;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $extras = json_decode((string) ($row['extras_json'] ?? ''), true);
            if (!is_array($extras)) {
                $extras = [];
            }
            $origem = strtolower(trim((string) ($extras['origem'] ?? '')));
            $pts = 0;
            if (in_array($origem, ['quadro-impressao', 'santa-maria', 'composto'], true)) {
                $pts += 100;
            }
            if (!empty($extras['quadro_livre']) || !empty($extras['modelo_santa_maria'])) {
                $pts += 40;
            }
            if (!empty($extras['linhas_layout']) && is_array($extras['linhas_layout'])) {
                $pts += 20;
            }
            if ((int) ($extras['layout_boletim_id'] ?? 0) > 0) {
                $pts += 10;
            }
            // Esqueleto do cadastro (Média/Faltas) só se não houver quadro montado.
            if ($regraCadastro > 0 && $id === $regraCadastro) {
                $pts += 1;
            }
            $updated = strtotime((string) ($row['updated_at'] ?? '')) ?: 0;
            if ($pts > $melhorPts || ($pts === $melhorPts && $updated > $melhorUpdated)) {
                $melhorPts = $pts;
                $melhorUpdated = $updated;
                $melhor = $id;
            }
        }

        return $melhorPts >= 10 ? $melhor : 0;
    }

    /**
     * Injeta group_line a partir dos agrupamentos do cadastro / pai_id das matérias
     * quando a regra de impressão não gravou o "Agrupar linha".
     *
     * @param array<string,mixed> $regra
     * @param list<array<string,mixed>> $linhasPresentes
     * @return array<string,mixed>
     */
    private function enriquecerRegraComAgrupamentosCadastro(array $regra, array $linhasPresentes): array
    {
        $componentes = is_array($regra['componentes'] ?? null) ? $regra['componentes'] : [];
        if ($componentes === []) {
            return $regra;
        }

        $jaTemGrupo = false;
        foreach ($componentes as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $cfg = is_array($comp['config'] ?? null) ? $comp['config'] : [];
            if ($cfg === [] && is_string($comp['config_json'] ?? null) && trim((string) $comp['config_json']) !== '') {
                $decoded = json_decode((string) $comp['config_json'], true);
                $cfg = is_array($decoded) ? $decoded : [];
            }
            $gl = is_array($cfg['group_line'] ?? null) ? $cfg['group_line'] : null;
            if (is_array($gl) && !empty($gl['enabled'])) {
                $jaTemGrupo = true;
                break;
            }
        }
        if ($jaTemGrupo) {
            return $regra;
        }

        $midsPresentes = [];
        foreach ($linhasPresentes as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $mid = (int) ($lin['materia_id'] ?? 0);
            if ($mid > 0) {
                $midsPresentes[$mid] = true;
            }
        }

        $grupos = [];
        $cobertos = [];

        try {
            if (class_exists(\App\Modulos\AgrupamentosComponentes\Services\AgrupamentoComponenteService::class)) {
                $svc = new \App\Modulos\AgrupamentosComponentes\Services\AgrupamentoComponenteService();
                foreach ($svc->listarParaBoletim() as $ag) {
                    if (!is_array($ag)) {
                        continue;
                    }
                    $filhos = array_values(array_filter(array_map('intval', (array) ($ag['materias_ids'] ?? [])), static fn ($id) => $id > 0));
                    if (count($filhos) < 2) {
                        continue;
                    }
                    if ($midsPresentes !== []) {
                        $presentes = array_values(array_filter($filhos, static fn ($id) => isset($midsPresentes[$id])));
                        if (count($presentes) < 2) {
                            continue;
                        }
                        $filhos = $presentes;
                    }
                    $agId = (int) ($ag['id'] ?? 0);
                    $rotuloId = (int) ($ag['materia_rotulo_id'] ?? 0);
                    $label = trim((string) ($ag['nome'] ?? ''));
                    if ($rotuloId > 0) {
                        $nomeRotulo = $this->nomeMateriaPorId($rotuloId);
                        if ($nomeRotulo !== '') {
                            $label = $nomeRotulo;
                        }
                    }
                    if ($label === '') {
                        $label = 'Grupo';
                    }
                    $gk = 'ag_' . ($agId > 0 ? $agId : md5($label . implode(',', $filhos)));
                    $grupos[$gk] = [
                        'key' => $gk,
                        'label' => $label,
                        'mode' => ((string) ($ag['modo'] ?? 'media')) === 'soma' ? 'soma' : 'media',
                        'materias_ids' => $filhos,
                        'agrupamento_id' => $agId,
                        'aplicar_em' => 'ambos',
                    ];
                    foreach ($filhos as $fid) {
                        $cobertos[$fid] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Agrupamentos cadastro vida aluno: ' . $e->getMessage());
        }

        // Pai/filho em matérias (ex.: Língua Portuguesa → Literatura/Redação).
        try {
            $filhosPorPai = $this->db->fetchAll(
                "SELECT id, nome, pai_id
                 FROM materias
                 WHERE ativo = 1 AND pai_id IS NOT NULL AND pai_id > 0
                 ORDER BY nome ASC"
            ) ?: [];
            $porPai = [];
            foreach ($filhosPorPai as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pai = (int) ($row['pai_id'] ?? 0);
                $fid = (int) ($row['id'] ?? 0);
                if ($pai <= 0 || $fid <= 0 || isset($cobertos[$fid])) {
                    continue;
                }
                if ($midsPresentes !== [] && !isset($midsPresentes[$fid])) {
                    continue;
                }
                $porPai[$pai][] = $fid;
            }
            foreach ($porPai as $paiId => $filhos) {
                $filhos = array_values(array_unique(array_map('intval', $filhos)));
                if (count($filhos) < 2) {
                    continue;
                }
                $label = $this->nomeMateriaPorId((int) $paiId);
                if ($label === '') {
                    continue;
                }
                $gk = 'pai_' . (int) $paiId;
                $grupos[$gk] = [
                    'key' => $gk,
                    'label' => $label,
                    'mode' => 'media',
                    'materias_ids' => $filhos,
                    'agrupamento_id' => 0,
                    'aplicar_em' => 'ambos',
                ];
                foreach ($filhos as $fid) {
                    $cobertos[$fid] = true;
                }
            }
        } catch (\Throwable $e) {
            error_log('Hierarquia pai_id vida aluno: ' . $e->getMessage());
        }

        if ($grupos === []) {
            return $regra;
        }

        foreach ($grupos as $g) {
            $componentes[] = [
                'codigo' => 'grp_' . $g['key'],
                'nome' => $g['label'],
                'source_type' => 'nenhuma',
                'config' => [
                    'papel_wizard' => 'exibe',
                    'titulo_boletim' => 1,
                    'group_line' => [
                        'enabled' => true,
                        'key' => $g['key'],
                        'label' => $g['label'],
                        'mode' => $g['mode'],
                        'modo_padrao' => $g['mode'],
                        'materias_ids' => $g['materias_ids'],
                        'agrupamento_id' => $g['agrupamento_id'],
                        'aplicar_em' => $g['aplicar_em'],
                        'arredondamento' => 'todos',
                    ],
                ],
            ];
        }
        $regra['componentes'] = $componentes;

        return $regra;
    }

    private function nomeMateriaPorId(int $materiaId): string
    {
        if ($materiaId <= 0) {
            return '';
        }
        try {
            $row = $this->db->fetch(
                'SELECT nome FROM materias WHERE id = :id LIMIT 1',
                ['id' => $materiaId]
            );
            return is_array($row) ? trim((string) ($row['nome'] ?? '')) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Renderiza o quadro exatamente como no assistente (boletim_quadro_tabela).
     * Prefere resultado gerado da regra; senão simula ao vivo.
     */
    private function htmlDeRegraBoletimAluno(int $alunoId, int $regraId): string
    {
        if ($alunoId <= 0 || $regraId <= 0) {
            return '';
        }
        $nivelBuffer = ob_get_level();
        try {
            $cfg = new \App\Models\System\BoletimConfig();
            $regra = $cfg->getRuleById($regraId);
            if (!is_array($regra) || empty($regra['componentes'])) {
                return '';
            }
            $motor = new \App\Modulos\Boletins\Controllers\BoletimConfigController(true);

            $cols = [];
            $linhas = [];
            $gerado = $cfg->getGeneratedBoletimByAlunoAndRegra($alunoId, $regraId);
            if (is_array($gerado) && !empty($gerado['colunas']) && !empty($gerado['linhas'])) {
                $linhasBase = is_array($gerado['linhas']) ? $gerado['linhas'] : [];
                $regraHier = $this->enriquecerRegraComAgrupamentosCadastro($regra, $linhasBase);
                $sim = [
                    'matriz_materias' => [
                        'colunas' => $gerado['colunas'],
                        'linhas' => $linhasBase,
                    ],
                ];
                $sim = $motor->montarMatrizDemonstrativoComGrupoHierarquico($sim, $regraHier);
                $matriz = is_array($sim['matriz_materias'] ?? null) ? $sim['matriz_materias'] : [];
                $cols = is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                $linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
            }

            if ($cols === [] || $linhas === []) {
                $ini = $this->normalizarDataYmd((string) ($regra['default_data_inicio'] ?? ''));
                $fim = $this->normalizarDataYmd((string) ($regra['default_data_fim'] ?? ''));
                if ($ini === null || $fim === null) {
                    $anoRegra = (int) ($regra['ano_letivo'] ?? date('Y'));
                    if ($anoRegra >= 2000) {
                        $ini = $ini ?? sprintf('%04d-01-01', $anoRegra);
                        $fim = $fim ?? sprintf('%04d-12-31', $anoRegra);
                    }
                }
                if ($ini === null || $fim === null) {
                    return '';
                }
                if ($ini > $fim) {
                    [$ini, $fim] = [$fim, $ini];
                }
                $periodoRef = 'RANGE:' . $ini . ':' . $fim;
                $regraHier = $this->enriquecerRegraComAgrupamentosCadastro($regra, []);
                $simulacao = $motor->simularRegraAluno(
                    $regraHier,
                    $alunoId,
                    $periodoRef,
                    $ini,
                    $fim,
                    [],
                    true,
                    false
                );
                if (!is_array($simulacao)) {
                    return '';
                }
                $simulacao = $motor->montarMatrizDemonstrativoComGrupoHierarquico($simulacao, $regraHier);
                $matriz = is_array($simulacao['matriz_materias'] ?? null) ? $simulacao['matriz_materias'] : [];
                $cols = is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                $linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
            }

            if ($cols === [] || $linhas === []) {
                return '';
            }

            return $this->renderizarQuadroBoletimTabela(
                $cols,
                $linhas,
                (int) ($regra['decimal_places'] ?? 2),
                (string) ($regra['exibir_em'] ?? 'boletim')
            );
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivelBuffer) {
                @ob_end_clean();
            }
            error_log('Quadro regra vida aluno #' . $alunoId . ' regra #' . $regraId . ': ' . $e->getMessage());
            return '';
        }
    }

    /**
     * @param list<array<string,mixed>> $cols
     * @param list<array<string,mixed>> $linhas
     */
    private function renderizarQuadroBoletimTabela(array $cols, array $linhas, int $decimalPlaces, string $exibirEm): string
    {
        $nivelBuffer = ob_get_level();
        try {
            $decimalPlaces = ($decimalPlaces === 1) ? 1 : 2;
            $edicaoSimulacao = null;
            $ev = [
                'exibir_em' => $exibirEm !== '' ? $exibirEm : 'boletim',
                'colunas' => $cols,
                'linhas' => $linhas,
                'decimal_places' => $decimalPlaces,
                'grupo_regras_notas_id' => 0,
            ];
            $html = '';
            if (\App\Helpers\BoletimQuadroLayoutHelper::partirTabelas($cols) !== []
                || \App\Helpers\BoletimQuadroLayoutHelper::ehLayoutQuadro($cols)
            ) {
                ob_start();
                include \App\Core\ViewPaths::partial('boletim_quadro_tabela.php');
                $html = trim((string) ob_get_clean());
            }
            if (stripos($html, '<table') === false) {
                $tituloTabelaSimples = 'Matérias';
                $ocultar_grupo_hierarquia = false;
                ob_start();
                include \App\Core\ViewPaths::partial('boletim_quadro_tabela_simples.php');
                $html = trim((string) ob_get_clean());
            }
            if (stripos($html, '<table') === false) {
                $ev = [
                    'exibir_em' => $exibirEm !== '' ? $exibirEm : 'boletim',
                    'colunas' => $cols,
                    'linhas' => $linhas,
                    'decimal_places' => $decimalPlaces,
                ];
                ob_start();
                $boletins_gerados = [$ev];
                $boletim_pode_excluir = false;
                $boletim_aluno_id = 0;
                $partial = \App\Core\ViewPaths::modulo('boletins/Views/partials/boletins_gerados.php');
                if (!is_file($partial)) {
                    ob_end_clean();
                    return '';
                }
                require $partial;
                $html = trim((string) ob_get_clean());
            }

            return stripos($html, '<table') === false ? '' : $html;
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivelBuffer) {
                @ob_end_clean();
            }
            error_log('Render quadro boletim vida aluno: ' . $e->getMessage());
            return '';
        }
    }

    private function normalizarDataYmd(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        $ts = strtotime($valor);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    /**
     * @param 'oficial'|'complementar' $finalidadePref
     */
    private function htmlDeBoletimGerado(int $alunoId, int $ano, string $finalidadePref, int $turmaId): string
    {
        try {
            $cfg = new \App\Models\System\BoletimConfig();
            $eventos = $cfg->getGeneratedBoletinsByAluno($alunoId, 'coordenacao', 'boletim');
        } catch (\Throwable $e) {
            return '';
        }
        if ($eventos === []) {
            return '';
        }

        $escolhido = null;
        $scoreMelhor = -1;
        $updatedMelhor = -1;
        foreach ($eventos as $ev) {
            if (!is_array($ev) || empty($ev['linhas']) || empty($ev['colunas'])) {
                continue;
            }
            $anoEv = (int) ($ev['ano_letivo'] ?? 0);
            if ($ano > 0 && $anoEv !== $ano) {
                continue;
            }
            $fin = strtolower(trim((string) ($ev['finalidade'] ?? 'oficial'))) === 'complementar'
                ? 'complementar'
                : 'oficial';
            $score = ($fin === $finalidadePref) ? 100 : 0;
            if ($ano > 0 && $anoEv === $ano) {
                $score += 20;
            }
            $updated = strtotime((string) ($ev['updated_at'] ?? '')) ?: 0;
            if ($score > $scoreMelhor || ($score === $scoreMelhor && $updated > $updatedMelhor)) {
                $scoreMelhor = $score;
                $updatedMelhor = $updated;
                $escolhido = $ev;
            }
        }
        if (!is_array($escolhido) || $scoreMelhor < 100) {
            return '';
        }

        $regraId = (int) ($escolhido['regra_id'] ?? 0);
        if ($regraId > 0) {
            try {
                $regra = $cfg->getRuleById($regraId);
                if (is_array($regra) && !empty($regra['componentes'])) {
                    $motor = new \App\Modulos\Boletins\Controllers\BoletimConfigController(true);
                    $linhasBase = is_array($escolhido['linhas']) ? $escolhido['linhas'] : [];
                    $regraHier = $this->enriquecerRegraComAgrupamentosCadastro($regra, $linhasBase);
                    $sim = [
                        'matriz_materias' => [
                            'colunas' => $escolhido['colunas'],
                            'linhas' => $linhasBase,
                        ],
                    ];
                    $sim = $motor->montarMatrizDemonstrativoComGrupoHierarquico($sim, $regraHier);
                    $matriz = is_array($sim['matriz_materias'] ?? null) ? $sim['matriz_materias'] : [];
                    if (!empty($matriz['linhas']) && is_array($matriz['linhas'])) {
                        $escolhido['linhas'] = $matriz['linhas'];
                    }
                    if (!empty($matriz['colunas']) && is_array($matriz['colunas'])) {
                        $escolhido['colunas'] = $matriz['colunas'];
                    }
                }
            } catch (\Throwable $e) {
                error_log('Hierarquia boletim vida aluno #' . $alunoId . ' regra #' . $regraId . ': ' . $e->getMessage());
            }
        }

        $cols = is_array($escolhido['colunas'] ?? null) ? $escolhido['colunas'] : [];
        $linhas = is_array($escolhido['linhas'] ?? null) ? $escolhido['linhas'] : [];
        if ($cols === [] || $linhas === []) {
            return '';
        }

        return $this->renderizarQuadroBoletimTabela(
            $cols,
            $linhas,
            (int) ($escolhido['decimal_places'] ?? 2),
            (string) ($escolhido['exibir_em'] ?? 'boletim')
        );
    }

    /**
     * Garante ficha por matrícula ativa (regular + curso extra), para abas no detalhe.
     */
    private function garantirFichasMatriculasAtivas(int $alunoId, VidaEscolarService $vida): void
    {
        if ($alunoId <= 0 || !class_exists(\App\Dominio\Pessoas\Services\AlunoTurmaResolver::class)) {
            return;
        }
        $resolver = new \App\Dominio\Pessoas\Services\AlunoTurmaResolver();
        foreach ($resolver->listarMatriculasAtivas($alunoId) as $mat) {
            if (!is_array($mat)) {
                continue;
            }
            $turmaId = (int) ($mat['turma_id'] ?? 0);
            $ano = (int) ($mat['ano_letivo_ano'] ?? 0);
            if ($turmaId <= 0 || $ano <= 0) {
                continue;
            }
            $vida->garantirFicha($alunoId, $turmaId, $ano);
        }
    }

    /**
     * Rotula cada ficha com o modelo de boletim (oficial ou extra) da turma.
     *
     * @param list<array<string,mixed>> $fichas
     * @return list<array<string,mixed>>
     */
    private function enriquecerFichasComModelo(array $fichas, int $alunoId): array
    {
        if ($fichas === []) {
            return [];
        }
        $cursoPorTurma = [];
        try {
            if (class_exists(\App\Dominio\Pessoas\Services\AlunoTurmaResolver::class)) {
                $resolver = new \App\Dominio\Pessoas\Services\AlunoTurmaResolver();
                foreach ($resolver->listarMatriculasAtivas($alunoId) as $mat) {
                    if (!is_array($mat)) {
                        continue;
                    }
                    $tid = (int) ($mat['turma_id'] ?? 0);
                    if ($tid > 0) {
                        $cursoPorTurma[$tid] = strtolower(trim((string) ($mat['curso_tipo'] ?? 'regular')));
                    }
                }
            }
        } catch (\Throwable $e) {
            $cursoPorTurma = [];
        }

        $cadastro = null;
        try {
            if (class_exists(\App\Modulos\Boletins\Services\BoletimCadastroService::class)) {
                $cadastro = new \App\Modulos\Boletins\Services\BoletimCadastroService();
            }
        } catch (\Throwable $e) {
            $cadastro = null;
        }

        $out = [];
        foreach ($fichas as $f) {
            if (!is_array($f)) {
                continue;
            }
            $turmaId = (int) ($f['turma_id'] ?? 0);
            $ano = (int) ($f['ano_letivo'] ?? 0);
            $cursoTipo = $cursoPorTurma[$turmaId] ?? 'regular';
            $finalidadePref = $cursoTipo === 'extra' ? 'complementar' : 'oficial';
            $modelo = null;
            if ($cadastro !== null) {
                $serieId = 0;
                try {
                    $turma = $this->db->fetch(
                        'SELECT serie_id FROM turmas WHERE id = :id LIMIT 1',
                        ['id' => $turmaId]
                    );
                    $serieId = (int) (is_array($turma) ? ($turma['serie_id'] ?? 0) : 0);
                } catch (\Throwable $e) {
                    $serieId = 0;
                }
                $modelo = $cadastro->encontrarModeloParaTurma($turmaId, $serieId, $ano, $finalidadePref);
                if ($modelo === null && $finalidadePref === 'complementar') {
                    $modelo = $cadastro->encontrarModeloParaTurma($turmaId, $serieId, $ano, 'oficial');
                }
            }
            $boletimNome = is_array($modelo) ? trim((string) ($modelo['nome'] ?? '')) : '';
            $boletimFin = is_array($modelo)
                ? ((($modelo['finalidade'] ?? '') === 'complementar') ? 'complementar' : 'oficial')
                : ($finalidadePref === 'complementar' ? 'complementar' : 'oficial');
            $turmaNome = trim((string) ($f['turma_nome'] ?? 'turma'));
            $f['curso_tipo'] = $cursoTipo;
            $f['boletim_nome'] = $boletimNome;
            $f['boletim_finalidade'] = $boletimFin;
            $f['aba_rotulo'] = $boletimNome !== '' ? $boletimNome : $turmaNome;
            $out[] = $f;
        }

        return $out;
    }

    private function dataValida($valor): bool
    {
        $s = trim((string) $valor);
        if ($s === '' || $s === '0000-00-00') {
            return false;
        }
        $ts = strtotime($s);
        return $ts !== false && $ts > 0;
    }

    private function dataBr($valor): string
    {
        if (!$this->dataValida($valor)) {
            return '';
        }
        return date('d/m/Y', strtotime((string) $valor));
    }
}

if (!class_exists('ProntuarioVidaEscolarService', false) && class_exists(__NAMESPACE__ . '\\ProntuarioVidaEscolarService', false)) {
    class_alias(__NAMESPACE__ . '\\ProntuarioVidaEscolarService', 'ProntuarioVidaEscolarService');
}
