<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../../../Services/DeclarationService.php';
require_once __DIR__ . '/../../../Services/HistoricoEscolarService.php';
require_once __DIR__ . '/../../../Models/User/StudentDocument.php';
require_once __DIR__ . '/../../../Models/Education/HistoricoDocumento.php';
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';

use App\Services\DeclarationService;
use App\Services\HistoricoEscolarService;
use Database;
use HistoricoDocumento;
use ResultadoAcademico;
use StudentDocument;

/**
 * Monta o prontuário do aluno: identidade, documentos, conferência SED/INEP
 * e pacote de transferência. Não recalcula nota.
 */
class ProntuarioVidaEscolarService
{
    public const ABAS = ['identidade', 'trajetoria', 'boletim', 'documentos', 'conferencia', 'dossie'];

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
    public function montar(int $alunoId, VidaEscolarService $vida, int $fichaId = 0): array
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
        $fichas = $vida->model()->schemaPronto() ? $vida->model()->listarFichasAluno($alunoId) : [];

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
        $quadro = $fichaId > 0 ? $vida->quadro($fichaId) : null;

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
            'fichas' => $fichas,
            'ficha_id' => $fichaId,
            'quadro' => $quadro,
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
            ['campo' => 'Nome do aluno', 'valor' => (string) ($aluno['nome'] ?? '')],
            ['campo' => 'CPF', 'valor' => (string) ($aluno['cpf'] ?? '')],
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
