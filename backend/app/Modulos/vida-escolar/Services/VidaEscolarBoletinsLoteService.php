<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/VidaEscolarService.php';
require_once __DIR__ . '/ProntuarioVidaEscolarService.php';
require_once __DIR__ . '/VidaEscolarPdfService.php';
require_once __DIR__ . '/../../../Core/LayoutHelper.php';
require_once __DIR__ . '/../../../Services/AIJobService.php';

/**
 * Gera um PDF por aluno (boletim oficial da Vida Escolar) e empacota em ZIP.
 * Pensado para rodar em job de fundo: um aluno por vez, com heartbeat.
 */
class VidaEscolarBoletinsLoteService
{
    public const TIPO_JOB = 'vida_escolar_boletins_zip';
    public const MAX_ALUNOS = 800;

    /**
     * @param array<string,mixed> $payload
     * @return array{arquivo:string,nome_download:string,total:int,emitidos:int,falhas:int,iniciado_em:string,finalizado_em:string}
     */
    public function executarJob(array $payload): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
            throw new \RuntimeException('O módulo Vida Escolar está desativado nesta escola.');
        }

        $jobId = (int) ($payload['_job_id'] ?? 0);
        $anoLetivo = (int) ($payload['ano_letivo'] ?? 0);
        $slugTenant = $this->slugDePayload($payload);
        $alunoIds = $this->normalizarIds($payload['aluno_ids'] ?? []);
        $iniciadoEm = date('Y-m-d H:i:s');
        if ($jobId > 0) {
            $this->gravarProgresso($jobId, [
                'iniciado_em' => $iniciadoEm,
                'finalizado_em' => null,
            ]);
        }
        if ($jobId <= 0 || $anoLetivo <= 0 || $alunoIds === []) {
            throw new \RuntimeException('Lote de boletins incompleto (ano ou alunos ausentes).');
        }
        if (count($alunoIds) > self::MAX_ALUNOS) {
            throw new \RuntimeException(
                'O lote tem mais de ' . self::MAX_ALUNOS . ' alunos. Filtre por turma ou ano e tente de novo.'
            );
        }

        $vida = new VidaEscolarService();
        if (!$vida->model()->schemaPronto()) {
            throw new \RuntimeException('Execute a migration da Vida Escolar (painel Master) antes de emitir os boletins.');
        }

        $fichas = $vida->model()->listarFichasAlunosAno($alunoIds, $anoLetivo);
        $porAluno = [];
        foreach ($fichas as $ficha) {
            $alunoId = (int) ($ficha['aluno_id'] ?? 0);
            $fichaId = (int) ($ficha['id'] ?? 0);
            if ($alunoId > 0 && $fichaId > 0) {
                $porAluno[$alunoId] = $fichaId;
            }
        }

        $ordem = [];
        foreach ($alunoIds as $alunoId) {
            if (isset($porAluno[$alunoId])) {
                $ordem[] = $alunoId;
            }
        }
        if ($ordem === []) {
            throw new \RuntimeException('Nenhum aluno deste filtro tem ficha na Vida Escolar para o ano ' . $anoLetivo . '.');
        }

        $dirZip = self::diretorioZip($slugTenant);
        if (!is_dir($dirZip) && !mkdir($dirZip, 0770, true) && !is_dir($dirZip)) {
            throw new \RuntimeException('Não foi possível criar a pasta dos boletins.');
        }

        $dirTmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 've_boletins_'
            . $slugTenant
            . '_'
            . $jobId;
        $this->limparDiretorio($dirTmp);
        if (!mkdir($dirTmp, 0700, true) && !is_dir($dirTmp)) {
            throw new \RuntimeException('Não foi possível criar a pasta temporária dos PDFs.');
        }

        $prontuarioSvc = new ProntuarioVidaEscolarService();
        $pdfSvc = new VidaEscolarPdfService();
        $periodos = VidaEscolarService::PERIODOS;
        $arquivos = [];
        $falhas = [];
        $indice = 0;
        $digitos = max(3, strlen((string) count($ordem)));

        try {
            foreach ($ordem as $alunoId) {
                $indice++;
                \App\Services\AIJobService::renovarHeartbeat($jobId);

                $fichaId = (int) ($porAluno[$alunoId] ?? 0);
                try {
                    $dados = $prontuarioSvc->montar($alunoId, $vida, $fichaId);
                    if ($dados === []) {
                        throw new \RuntimeException('Prontuário vazio.');
                    }
                    $dados['planilha_sed'] = $prontuarioSvc->planilhaSed($dados);
                    $out = $pdfSvc->htmlProntuario(
                        VidaEscolarPdfService::CODIGO_BOLETIM,
                        'Boletim Escolar',
                        $dados,
                        $periodos,
                        null
                    );
                    $binario = $pdfSvc->gerarPdfBinario($out['html'], $out['modelo']);
                    if ($binario === '') {
                        throw new \RuntimeException('PDF vazio.');
                    }

                    $nomeAluno = (string) ($dados['aluno']['nome'] ?? $dados['nome'] ?? 'aluno');
                    $ra = (string) ($dados['aluno']['ra'] ?? $dados['ra'] ?? '');
                    $entrada = str_pad((string) $indice, $digitos, '0', STR_PAD_LEFT)
                        . '_'
                        . $this->slugArquivo($nomeAluno)
                        . ($ra !== '' ? '_' . $this->slugArquivo($ra) : '_' . $alunoId)
                        . '.pdf';
                    $pathPdf = $dirTmp . DIRECTORY_SEPARATOR . $entrada;
                    if (file_put_contents($pathPdf, $binario) === false) {
                        throw new \RuntimeException('Falha ao gravar o PDF temporário.');
                    }
                    $arquivos[] = ['nome' => $entrada, 'path' => $pathPdf];
                    unset($dados, $out, $binario);
                } catch (\Throwable $e) {
                    $falhas[] = 'Aluno ' . $alunoId . ': ' . $e->getMessage();
                    error_log('VidaEscolarBoletinsLoteService aluno=' . $alunoId . ': ' . $e->getMessage());
                }

                if ($indice % 10 === 0 && function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            if ($arquivos === []) {
                $detalhe = $falhas !== [] ? ' ' . $falhas[0] : '';
                throw new \RuntimeException(
                    'Não foi possível gerar nenhum boletim.' . $detalhe
                    . ' Confira o Layout de documentos (papel timbrado e modelo do boletim).'
                );
            }

            $emitidos = count($arquivos);
            $arquivos[] = [
                'nome' => 'LEIA-ME.txt',
                'path' => $this->gravarManifesto(
                    $dirTmp,
                    $anoLetivo,
                    count($ordem),
                    $emitidos,
                    $falhas,
                    $iniciadoEm,
                    date('Y-m-d H:i:s')
                ),
            ];

            $nomeZip = 'boletins_' . $jobId . '.zip';
            $pathZip = $dirZip . DIRECTORY_SEPARATOR . $nomeZip;
            if (is_file($pathZip)) {
                @unlink($pathZip);
            }
            $this->empacotarZip($pathZip, $arquivos);
            $finalizadoEm = date('Y-m-d H:i:s');

            $nomeDownload = trim((string) ($payload['nome_download'] ?? ''));
            $nomeDownload = preg_replace('/[\r\n\t"\\\\]/', '', $nomeDownload) ?: '';
            if ($nomeDownload === '' || !str_ends_with(strtolower($nomeDownload), '.zip')) {
                $nomeDownload = 'boletins_vida_escolar_' . $anoLetivo . '_' . date('Ymd_His') . '.zip';
            }

            return [
                'arquivo' => $nomeZip,
                'nome_download' => $nomeDownload,
                'total' => count($ordem),
                'emitidos' => $emitidos,
                'falhas' => count($falhas),
                'iniciado_em' => $iniciadoEm,
                'finalizado_em' => $finalizadoEm,
            ];
        } finally {
            $this->limparDiretorio($dirTmp);
        }
    }

    public static function diretorioZip(?string $slug = null): string
    {
        $normalizado = self::normalizarSlug($slug);
        if ($normalizado === '') {
            throw new \RuntimeException('Slug da escola ausente; não é possível gravar o ZIP dos boletins.');
        }
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 4);
        return rtrim($base, '/\\') . '/storage/exports/' . $normalizado . '/boletins';
    }

    public static function caminhoZip(int $jobId, ?string $slug = null): ?string
    {
        if ($jobId <= 0) {
            return null;
        }
        $dir = self::diretorioZip($slug);
        $path = $dir . '/boletins_' . $jobId . '.zip';
        $realDir = realpath($dir);
        $realFile = realpath($path);
        if ($realDir === false || $realFile === false) {
            return null;
        }
        if (!str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return is_file($realFile) ? $realFile : null;
    }

    /**
     * @param mixed $ids
     * @return list<int>
     */
    private function normalizarIds($ids): array
    {
        if (!is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        return array_values($out);
    }

    /**
     * @param list<array{nome:string,path:string}> $arquivos
     */
    private function empacotarZip(string $destino, array $arquivos): void
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            $flags = \ZipArchive::CREATE;
            if (defined('ZipArchive::OVERWRITE')) {
                $flags |= \ZipArchive::OVERWRITE;
            }
            if ($zip->open($destino, $flags) !== true) {
                throw new \RuntimeException('Não foi possível criar o arquivo ZIP.');
            }
            foreach ($arquivos as $arquivo) {
                $nome = $this->nomeEntradaZip((string) ($arquivo['nome'] ?? ''));
                $path = (string) ($arquivo['path'] ?? '');
                if ($nome === '' || $path === '' || !is_file($path)) {
                    continue;
                }
                $zip->addFile($path, $nome);
            }
            $zip->close();
            if (!is_file($destino) || filesize($destino) < 22) {
                throw new \RuntimeException('O ZIP dos boletins ficou vazio.');
            }
            return;
        }

        $this->gravarZipArmazenado($destino, $arquivos);
        if (!is_file($destino) || filesize($destino) < 22) {
            throw new \RuntimeException('O ZIP dos boletins ficou vazio.');
        }
    }

    /**
     * ZIP método store (sem compressão), um arquivo por vez — não depende de ZipArchive.
     *
     * @param list<array{nome:string,path:string}> $arquivos
     */
    private function gravarZipArmazenado(string $destino, array $arquivos): void
    {
        $fh = fopen($destino, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Não foi possível gravar o ZIP.');
        }

        $central = '';
        $offset = 0;
        $count = 0;
        try {
            foreach ($arquivos as $arquivo) {
                $nome = $this->nomeEntradaZip((string) ($arquivo['nome'] ?? ''));
                $path = (string) ($arquivo['path'] ?? '');
                if ($nome === '' || $path === '' || !is_file($path)) {
                    continue;
                }
                $data = file_get_contents($path);
                if ($data === false) {
                    continue;
                }
                $crc = crc32($data);
                $size = strlen($data);
                $nameLength = strlen($nome);
                $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0)
                    . $nome
                    . $data;
                fwrite($fh, $local);
                $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset)
                    . $nome;
                $offset += strlen($local);
                $count++;
                unset($data, $local);
            }
            $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $offset, 0);
            fwrite($fh, $central . $eocd);
        } finally {
            fclose($fh);
        }
    }

    /**
     * @param array<string,mixed> $parcial
     */
    private function gravarProgresso(int $jobId, array $parcial): void
    {
        if ($jobId <= 0) {
            return;
        }
        $db = \Database::getInstance();
        if (!$db->tableExists('ai_jobs')) {
            return;
        }
        try {
            $db->query(
                'UPDATE ai_jobs SET result = :result WHERE id = :id AND status = :status',
                [
                    'result' => json_encode($parcial, JSON_UNESCAPED_UNICODE),
                    'id' => $jobId,
                    'status' => 'processing',
                ]
            );
        } catch (\Throwable $e) {
            error_log('VidaEscolarBoletinsLoteService progresso job=' . $jobId . ': ' . $e->getMessage());
        }
    }

    /**
     * @param list<string> $falhas
     */
    private function gravarManifesto(
        string $dirTmp,
        int $anoLetivo,
        int $total,
        int $emitidos,
        array $falhas,
        string $iniciadoEm,
        string $finalizadoEm
    ): string {
        $linhas = [
            'Boletins da Vida Escolar',
            'Ano letivo: ' . $anoLetivo,
            'Início da geração: ' . $this->formatarHorario($iniciadoEm),
            'Término da geração: ' . $this->formatarHorario($finalizadoEm),
            'Alunos no lote: ' . $total,
            'PDFs gerados: ' . $emitidos,
            'Falhas: ' . count($falhas),
            '',
        ];
        if ($falhas !== []) {
            $linhas[] = 'Alunos que não entraram no ZIP:';
            foreach ($falhas as $falha) {
                $linhas[] = '- ' . $falha;
            }
        }
        $path = $dirTmp . DIRECTORY_SEPARATOR . 'LEIA-ME.txt';
        file_put_contents($path, implode("\n", $linhas) . "\n");
        return $path;
    }

    private function formatarHorario(string $dt): string
    {
        $ts = strtotime($dt);
        return $ts === false ? $dt : date('d/m/Y H:i:s', $ts);
    }

    private function nomeEntradaZip(string $nome): string
    {
        $nome = str_replace(['\\', "\0"], '/', $nome);
        $nome = basename($nome);
        return $nome !== '' && $nome !== '.' && $nome !== '..' ? $nome : '';
    }

    private function slugArquivo(string $texto): string
    {
        $s = $texto;
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
            if (is_string($conv) && $conv !== '') {
                $s = $conv;
            }
        }
        $s = preg_replace('/[^A-Za-z0-9_-]+/', '_', $s) ?? '';
        $s = trim($s, '_-');
        return $s !== '' ? substr($s, 0, 60) : 'aluno';
    }

    private function slugDePayload(array $payload): string
    {
        return self::normalizarSlug((string) ($payload['tenant_slug'] ?? ''));
    }

    private static function normalizarSlug(?string $slug): string
    {
        $limpo = preg_replace('/[^a-z0-9_-]/i', '', (string) $slug);
        if (is_string($limpo) && $limpo !== '') {
            return $limpo;
        }
        if (defined('TENANT_SLUG')) {
            $constante = preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG);
            if (is_string($constante) && $constante !== '') {
                return $constante;
            }
        }
        return '';
    }

    private function limparDiretorio(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $itens = scandir($dir);
        if (!is_array($itens)) {
            return;
        }
        foreach ($itens as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
