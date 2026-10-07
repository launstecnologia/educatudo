<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../../../Services/HistoricoEscolarService.php';
require_once __DIR__ . '/../../../Services/DocumentoOficialService.php';
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/../../../Models/Education/HistoricoDocumento.php';
require_once __DIR__ . '/../../../Controllers/Admin/ReportAdminController.php';
require_once __DIR__ . '/VidaEscolarPdfService.php';

use App\Services\HistoricoEscolarService;
use Database;
use DocumentoOficialService;
use HistoricoDocumento;
use ResultadoAcademico;

/**
 * Emite histórico, ficha, boletim e demonstrativo a partir da aba do aluno
 * e registra cada geração com data e hora.
 */
class EmissaoDocumentosAlunoService
{
    public const TIPOS = ['historico', 'historico_transferencia', 'ficha', 'boletim', 'demonstrativo'];

    private const ROTULOS = [
        'ficha_individual' => 'Ficha do aluno',
        'boletim' => 'Boletim',
        'historico' => 'Histórico escolar',
        'historico_transferencia' => 'Histórico para transferência',
        'demonstrativo_notas' => 'Demonstrativo de notas',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return list<array{tipo:string,nome:string,descricao:string,icone:string}>
     */
    public function catalogo(int $alunoId): array
    {
        $transferencia = $this->paraTransferencia($alunoId);
        $saida = $this->transferenciaDoAluno($alunoId);
        $catalogo = [
            [
                'tipo' => 'historico',
                'nome' => $transferencia ? 'Histórico para transferência' : 'Histórico escolar',
                'descricao' => $transferencia
                    ? 'O ano ainda não foi homologado, então o PDF sai para transferência.'
                    : 'O boletim deste ano já foi homologado.',
                'icone' => 'fa-file-lines',
            ],
        ];
        if ($saida !== null) {
            $quando = $this->dataBr((string) ($saida['data_saida'] ?? ''));
            $catalogo[] = [
                'tipo' => 'historico_transferencia',
                'nome' => 'Histórico de transferência',
                'descricao' => $quando !== ''
                    ? 'Notas do período cursado até a saída em ' . $quando . ', com turma e situação de transferido.'
                    : 'Notas do período cursado até a saída, com turma e situação de transferido.',
                'icone' => 'fa-right-from-bracket',
            ];
        }

        return array_merge($catalogo, [
            [
                'tipo' => 'ficha',
                'nome' => 'Ficha do aluno',
                'descricao' => 'Ficha individual do ano, com componentes, frequência e situação.',
                'icone' => 'fa-id-card',
            ],
            [
                'tipo' => 'boletim',
                'nome' => 'Boletim',
                'descricao' => 'Boletim do ano letivo deste aluno.',
                'icone' => 'fa-table',
            ],
            [
                'tipo' => 'demonstrativo',
                'nome' => 'Demonstrativo de notas',
                'descricao' => 'Quadro vigente de notas do aluno.',
                'icone' => 'fa-list-ol',
            ],
        ]);
    }

    /**
     * @return list<array{titulo:string,quando:string,quem:string}>
     */
    public function listar(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $itens = [];
        $model = new ResultadoAcademico();
        if ($model->tabelaExiste('resultado_documento_emissoes')) {
            try {
                $rows = $this->db->fetchAll(
                    'SELECT tipo, snapshot_json, emitido_em
                     FROM resultado_documento_emissoes
                     WHERE aluno_id = :id
                       AND tipo IN (\'ficha_individual\', \'boletim\', \'historico\', \'historico_transferencia\', \'demonstrativo_notas\')
                     ORDER BY emitido_em DESC, id DESC
                     LIMIT 80',
                    ['id' => $alunoId]
                ) ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $itens[] = $this->linhaDoRegistro($row);
            }
        }

        $historicos = (new HistoricoDocumento())->listarPorAluno($alunoId);
        foreach ($historicos as $doc) {
            if (!is_array($doc) || !in_array((string) ($doc['status'] ?? ''), ['Emitido', 'Assinado', 'Entregue'], true)) {
                continue;
            }
            $quando = trim((string) ($doc['emitido_em'] ?? $doc['created_at'] ?? ''));
            if ($quando === '') {
                continue;
            }
            $transferencia = (string) ($doc['finalidade'] ?? '') === 'Transferencia';
            $itens[] = [
                'titulo' => $transferencia ? 'Histórico para transferência' : 'Histórico escolar',
                'quando' => $quando,
                'quem' => '',
            ];
        }

        usort($itens, static function (array $a, array $b): int {
            return strcmp((string) $b['quando'], (string) $a['quando']);
        });

        return array_slice($itens, 0, 40);
    }

    /**
     * @param array<string,mixed> $usuario
     * @param array<string,mixed>|null $config
     */
    public function emitir(int $alunoId, string $tipo, array $usuario, ?array $config): void
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \RuntimeException('Documento desconhecido.');
        }
        $aluno = $this->aluno($alunoId);
        if ($aluno === null) {
            throw new \RuntimeException('Aluno não encontrado.');
        }
        $userId = (int) ($usuario['id'] ?? 0) ?: null;
        $userNome = trim((string) ($usuario['nome'] ?? ''));
        $slug = preg_replace('/[^a-z0-9]+/i', '_', (string) ($aluno['nome'] ?? 'aluno')) ?: 'aluno';

        if ($tipo === 'historico' || $tipo === 'historico_transferencia') {
            $this->emitirHistorico($aluno, $userId, $userNome, $config, $slug, $tipo === 'historico_transferencia');
            return;
        }
        if ($tipo === 'demonstrativo') {
            $this->emitirDemonstrativo($aluno, $userId, $userNome, $slug, $config);
            return;
        }

        $docs = new DocumentoOficialService();
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        $ano = (int) ($aluno['ano_letivo'] ?? 0) ?: (int) date('Y');
        $emitido = $tipo === 'ficha'
            ? $docs->emitirFicha($alunoId, $turmaId, $ano, 'ano', 0, $userId, $config)
            : $docs->emitirBoletim($alunoId, $turmaId, $ano, 'ano', 0, $userId, $config);
        $homologado = !empty($emitido['payload']['_homologado']);
        if (!$homologado) {
            $tipoLog = $tipo === 'ficha' ? 'ficha_individual' : 'boletim';
            $this->registrar($alunoId, $turmaId, $ano, $tipoLog, self::ROTULOS[$tipoLog], $userId, $userNome);
        }
        $orientacao = (string) ($emitido['orientacao'] ?? 'portrait') === 'landscape' ? 'paisagem' : 'retrato';
        $arquivo = ($tipo === 'ficha' ? 'ficha_aluno_' : 'boletim_') . $slug . '.pdf';
        $titulo = $tipo === 'ficha' ? 'Ficha do aluno' : 'Boletim';
        $this->enviar(
            (string) ($emitido['html'] ?? ''),
            $arquivo,
            ['orientacao' => $orientacao, 'formato_papel' => (string) ($emitido['papel'] ?? 'A4')],
            $alunoId,
            $titulo,
            $tipo,
            $userId,
            $userNome,
            $config
        );
    }

    private function historicoProntoParaPdf(int $alunoId): int
    {
        foreach ((new HistoricoDocumento())->listarPorAluno($alunoId) as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            if (!in_array((string) ($doc['status'] ?? ''), ['Conferido', 'Emitido', 'Assinado', 'Entregue'], true)) {
                continue;
            }
            $id = (int) ($doc['id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    public function paraTransferencia(int $alunoId): bool
    {
        if ($alunoId <= 0) {
            return true;
        }
        try {
            $row = $this->db->fetch(
                'SELECT status FROM boletim_fichas WHERE aluno_id = :id ORDER BY ano_letivo DESC, id DESC LIMIT 1',
                ['id' => $alunoId]
            );
        } catch (\Throwable $e) {
            return true;
        }
        if (!is_array($row)) {
            return true;
        }

        return (string) ($row['status'] ?? '') !== 'homologada';
    }

    /**
     * Última matrícula encerrada por transferência, com turma e data de saída.
     *
     * @return array<string,mixed>|null
     */
    private function transferenciaDoAluno(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        try {
            $existe = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if (!$existe) {
                return null;
            }
            $row = $this->db->fetch(
                "SELECT m.data_saida, m.data_entrada, m.status,
                        t.nome AS turma_nome, t.serie AS turma_serie,
                        al.ano AS ano_letivo
                 FROM matricula m
                 LEFT JOIN turmas t ON t.id = m.turma_id
                 LEFT JOIN ano_letivo al ON al.id = m.ano_letivo_id
                 WHERE m.aluno_id = :id AND m.status = 'transferido'
                 ORDER BY m.data_saida DESC, m.id DESC
                 LIMIT 1",
                ['id' => $alunoId]
            );
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $dados
     * @param array<string,mixed> $saida
     * @return array<string,mixed>
     */
    private function aplicarDadosTransferencia(array $dados, array $saida): array
    {
        $data = substr(trim((string) ($saida['data_saida'] ?? '')), 0, 10);
        $dataBr = $this->dataBr($data);
        $turma = trim((string) ($saida['turma_nome'] ?? ''));
        $serie = trim((string) ($saida['turma_serie'] ?? ''));
        $ano = trim((string) ($saida['ano_letivo'] ?? ''));
        $dados['transferencia'] = [
            'data_saida' => $data,
            'data_saida_br' => $dataBr,
            'turma' => $turma,
            'serie' => $serie,
            'ano_letivo' => $ano,
        ];
        if (!is_array($dados['documento'] ?? null)) {
            $dados['documento'] = [];
        }
        $dados['documento']['finalidade'] = 'Transferencia';

        $onde = [];
        if ($turma !== '') {
            $onde[] = 'da turma ' . $turma;
        }
        if ($serie !== '') {
            $onde[] = 'série ' . $serie;
        }
        if ($ano !== '') {
            $onde[] = 'ano letivo de ' . $ano;
        }
        $frase = 'Aluno transferido'
            . ($dataBr !== '' ? ' em ' . $dataBr : '')
            . ($onde !== [] ? ', ' . implode(', ', $onde) : '')
            . '. As notas deste ano correspondem ao período cursado até a saída.';
        $obs = trim((string) ($dados['observacoes_gerais'] ?? ''));
        if (!str_contains($obs, 'Aluno transferido')) {
            $dados['observacoes_gerais'] = trim($obs . ($obs !== '' ? ' ' : '') . $frase);
        }

        $resultados = is_array($dados['resultados'] ?? null) ? $dados['resultados'] : [];
        $marcou = false;
        $ultimoIndice = null;
        foreach ($resultados as $i => $resultado) {
            if (!is_array($resultado)) {
                continue;
            }
            $ultimoIndice = $i;
            if ($ano !== '' && trim((string) ($resultado['ano_letivo'] ?? '')) === $ano) {
                $resultados[$i]['resultado'] = 'Transferido';
                $marcou = true;
            }
        }
        if (!$marcou && $ultimoIndice !== null && $ano === '') {
            $resultados[$ultimoIndice]['resultado'] = 'Transferido';
            $marcou = true;
        }
        if (!$marcou) {
            $resultados[] = [
                'ano_letivo' => $ano,
                'serie_ano' => $serie,
                'resultado' => 'Transferido',
            ];
        }
        $dados['resultados'] = $resultados;

        return $dados;
    }

    private function dataBr(string $data): string
    {
        $data = substr(trim($data), 0, 10);
        if ($data === '' || $data === '0000-00-00') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $data);

        return $dt instanceof \DateTime ? $dt->format('d/m/Y') : '';
    }

    /**
     * @param array<string,mixed> $aluno
     * @param array<string,mixed>|null $config
     */
    private function emitirHistorico(array $aluno, ?int $userId, string $userNome, ?array $config, string $slug, bool $somenteTransferencia = false): void
    {
        $alunoId = (int) ($aluno['id'] ?? 0);
        $saida = $somenteTransferencia ? $this->transferenciaDoAluno($alunoId) : null;
        if ($somenteTransferencia && $saida === null) {
            throw new \RuntimeException('Este aluno não tem transferência registrada.');
        }
        $transferencia = $somenteTransferencia || $this->paraTransferencia($alunoId);
        $finalidade = $transferencia ? 'Transferencia' : 'Conclusao';
        $svc = new HistoricoEscolarService($this->db);
        $id = $this->historicoProntoParaPdf($alunoId);
        if ($id <= 0) {
            $res = $svc->gerarRascunho($alunoId, $finalidade, $userId, null);
            if (empty($res['success'])) {
                throw new \RuntimeException((string) ($res['error'] ?? 'Não foi possível montar o histórico.'));
            }
            $id = (int) ($res['id'] ?? 0);
        }
        $dados = $svc->dadosParaPdf($id);
        if (!is_array($dados)) {
            throw new \RuntimeException('Não foi possível montar o histórico.');
        }
        if (is_array($dados['documento'] ?? null)) {
            $dados['documento']['finalidade'] = $finalidade;
        }
        if ($saida !== null) {
            $dados = $this->aplicarDadosTransferencia($dados, $saida);
        }
        $html = (new VidaEscolarPdfService($this->db))->htmlHistorico($dados, $config);
        $tipoLog = $somenteTransferencia || $transferencia ? 'historico_transferencia' : 'historico';
        $titulo = $somenteTransferencia ? 'Histórico de transferência' : self::ROTULOS[$tipoLog];
        $this->registrar(
            $alunoId,
            (int) ($aluno['turma_id'] ?? 0),
            (int) ($aluno['ano_letivo'] ?? 0) ?: (int) date('Y'),
            $tipoLog,
            $titulo,
            $userId,
            $userNome
        );
        $arquivo = ($somenteTransferencia ? 'historico_de_transferencia_' : ($transferencia ? 'historico_transferencia_' : 'historico_escolar_')) . $slug . '.pdf';
        $this->enviar($html, $arquivo, ['orientacao' => 'paisagem'], $alunoId, $titulo, $tipoLog, $userId, $userNome, $config);
    }

    /**
     * @param array<string,mixed> $aluno
     * @param array<string,mixed>|null $config
     */
    private function emitirDemonstrativo(array $aluno, ?int $userId, string $userNome, string $slug, ?array $config): void
    {
        $alunoId = (int) ($aluno['id'] ?? 0);
        $quadro = (new \ReportAdminController())->htmlDemonstrativoNotasDoAluno($alunoId);
        if (trim($quadro) === '') {
            throw new \RuntimeException('Não há demonstrativo de notas vigente para este aluno.');
        }
        $nome = htmlspecialchars((string) ($aluno['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#111;}'
            . 'h1{font-size:16px;margin:0 0 4px;} p{margin:0 0 12px;color:#444;}'
            . 'table{width:100%;border-collapse:collapse;} th,td{border:1px solid #d1d5db;padding:3px 4px;}'
            . '</style></head><body><h1>Demonstrativo de Notas</h1><p>' . $nome . '</p>'
            . $quadro . '</body></html>';
        $this->registrar(
            $alunoId,
            (int) ($aluno['turma_id'] ?? 0),
            (int) ($aluno['ano_letivo'] ?? 0) ?: (int) date('Y'),
            'demonstrativo_notas',
            self::ROTULOS['demonstrativo_notas'],
            $userId,
            $userNome
        );
        $this->enviar(
            $html,
            'demonstrativo_notas_' . $slug . '.pdf',
            ['orientacao' => 'paisagem'],
            $alunoId,
            self::ROTULOS['demonstrativo_notas'],
            'demonstrativo_notas',
            $userId,
            $userNome,
            $config
        );
    }

    /**
     * @param array{orientacao?:string,formato_papel?:string} $modelo
     * @param array<string,mixed>|null $config
     */
    private function enviar(
        string $html,
        string $arquivo,
        array $modelo,
        int $alunoId,
        string $titulo,
        string $tipo,
        ?int $userId,
        string $userNome,
        ?array $config
    ): void {
        if (trim($html) === '') {
            throw new \RuntimeException('Não foi possível gerar o PDF.');
        }
        $bin = (new VidaEscolarPdfService($this->db))->gerarPdfBinario($html, $modelo);
        $arquivo = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $arquivo) ?: 'documento.pdf';
        if (!str_ends_with(strtolower($arquivo), '.pdf')) {
            $arquivo .= '.pdf';
        }
        require_once __DIR__ . '/ArquivoPdfAlunoService.php';
        (new ArquivoPdfAlunoService($this->db))->guardar($alunoId, $tipo, $titulo, $bin, $arquivo, $userId, $userNome, $config);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $arquivo . '"');
        header('Content-Length: ' . strlen($bin));
        header('X-Content-Type-Options: nosniff');
        echo $bin;
        exit;
    }

    private function registrar(int $alunoId, int $turmaId, int $ano, string $tipo, string $titulo, ?int $userId, string $userNome): void
    {
        $model = new ResultadoAcademico();
        if (!$model->tabelaExiste('resultado_documento_emissoes')) {
            return;
        }
        $snapshot = json_encode([
            'titulo' => $titulo,
            'emitido_nome' => $userNome,
        ], JSON_UNESCAPED_UNICODE);
        $model->registrarEmissao([
            'tipo' => $tipo,
            'aluno_id' => $alunoId,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'ano_letivo' => $ano > 0 ? $ano : (int) date('Y'),
            'snapshot_json' => is_string($snapshot) ? $snapshot : null,
            'emitido_por' => $userId,
        ]);
    }

    /**
     * @param array<string,mixed> $row
     * @return array{titulo:string,quando:string,quem:string}
     */
    private function linhaDoRegistro(array $row): array
    {
        $tipo = (string) ($row['tipo'] ?? '');
        $titulo = self::ROTULOS[$tipo] ?? $tipo;
        $quem = '';
        $snap = json_decode((string) ($row['snapshot_json'] ?? ''), true);
        if (is_array($snap)) {
            if (trim((string) ($snap['titulo'] ?? '')) !== '') {
                $titulo = trim((string) $snap['titulo']);
            }
            $quem = trim((string) ($snap['emitido_nome'] ?? ''));
        }

        return [
            'titulo' => $titulo,
            'quando' => trim((string) ($row['emitido_em'] ?? '')),
            'quem' => $quem,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function aluno(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT a.id, a.nome, a.turma_id, t.ano_letivo
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id
             LIMIT 1',
            ['id' => $alunoId]
        );

        return is_array($row) ? $row : null;
    }
}
