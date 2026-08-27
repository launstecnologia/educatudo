<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../../../Services/DocumentProcessorService.php';
require_once __DIR__ . '/../../../Services/OpenAIService.php';

use App\Services\OpenAIService;
use DocumentProcessorService;

/**
 * Lê o PDF/imagem do histórico e monta um rascunho de importação.
 * A coordenação confere e só então valida.
 */
class HistoricoOcrService
{
    private VidaEscolarService $vida;

    public function __construct(?VidaEscolarService $vida = null)
    {
        $this->vida = $vida ?? new VidaEscolarService();
    }

    /**
     * @param array{id?:int,nome?:string,tipo?:string} $usuario
     * @return array{success:bool,importacao_id?:int,error?:string}
     */
    public function processarDocumento(int $alunoId, int $documentoId, array $usuario): array
    {
        $doc = $this->vida->model()->findDocumento($documentoId);
        if (!$doc || (int) ($doc['aluno_id'] ?? 0) !== $alunoId) {
            return ['success' => false, 'error' => 'Documento não encontrado para este aluno.'];
        }
        $path = $this->caminhoFisico((string) ($doc['arquivo_key'] ?? ''));
        if ($path === '' || !is_file($path)) {
            return ['success' => false, 'error' => 'Arquivo do histórico não encontrado no servidor.'];
        }

        try {
            $processor = new DocumentProcessorService();
            $texto = trim((string) $processor->extrairTexto($path, (string) ($doc['arquivo_mime'] ?? '')));
        } catch (\Throwable $e) {
            $texto = '';
        }

        $openai = new OpenAIService();
        $dados = [];
        try {
            if ($this->textoServeParaEstruturar($texto)) {
                $dados = $openai->estruturarHistoricoEscolar($texto);
            }
        } catch (\Throwable $e) {
            error_log('HistoricoOcr texto: ' . $e->getMessage());
            $dados = [];
        }

        $vazio = !$this->historicoTemComponentes(is_array($dados) ? $dados : []);
        if ($vazio) {
            $imagens = $this->imagensParaLeitura($path, (string) ($doc['arquivo_mime'] ?? ''), (string) ($doc['arquivo_nome'] ?? ''));
            if ($imagens === []) {
                return ['success' => false, 'error' => 'Não foi possível ler o arquivo (PDF escaneado sem texto?). Envie uma foto nítida das páginas ou lance o rascunho na Trajetória.'];
            }
            foreach ($imagens as $img) {
                try {
                    $parte = $openai->estruturarHistoricoEscolarImagem($img['b64'], $img['mime']);
                    $dados = $this->mesclarHistorico(is_array($dados) ? $dados : [], $parte);
                } catch (\Throwable $e) {
                    error_log('HistoricoOcr visao: ' . $e->getMessage());
                }
            }
        }

        $dados = $this->enriquecerComMatriz($alunoId, is_array($dados) ? $dados : []);
        $dados['documento_id'] = $documentoId;
        if (trim((string) ($dados['escola_origem'] ?? '')) === '') {
            $dados['escola_origem'] = trim((string) ($doc['escola_emissora'] ?? '')) ?: 'Escola de origem';
        }
        if (!$this->historicoTemComponentes($dados)) {
            return ['success' => false, 'error' => 'A IA não encontrou anos ou notas no documento. Envie um arquivo nítido ou lance o rascunho na Trajetória.'];
        }

        $res = $this->vida->salvarImportacao($alunoId, $dados, $usuario);
        if (empty($res['success'])) {
            return ['success' => false, 'error' => $res['error'] ?? 'Falha ao gravar o rascunho.'];
        }

        return ['success' => true, 'importacao_id' => (int) ($res['id'] ?? 0)];
    }

    public function caminhoFisico(string $arquivoKey): string
    {
        $key = str_replace('\\', '/', $arquivoKey);
        $key = ltrim($key, '/');
        if ($key === '' || str_contains($key, '..') || !str_contains($key, '/vida-escolar/')) {
            return '';
        }
        $base = dirname(__DIR__, 4) . '/storage/uploads/';
        $realUploads = realpath($base);
        $realPath = realpath($base . $key);
        if ($realUploads === false || $realPath === false || !str_starts_with($realPath, $realUploads . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
            return '';
        }
        if (defined('TENANT_SLUG') && trim((string) TENANT_SLUG) !== '') {
            $slug = preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG);
            if ($slug !== '' && !str_starts_with($key, $slug . '/vida-escolar/')) {
                return '';
            }
        }
        return $realPath;
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private function enriquecerComMatriz(int $alunoId, array $dados): array
    {
        $materias = $this->vida->model()->materiasAtivas();
        $porNome = [];
        foreach ($materias as $m) {
            $porNome[$this->normalizarNome((string) ($m['nome'] ?? ''))] = (int) ($m['id'] ?? 0);
        }
        $bims = is_array($dados['bimestres_atuais'] ?? null) ? $dados['bimestres_atuais'] : [];
        foreach ($bims as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $mid = (int) ($item['materia_id'] ?? 0);
            if ($mid <= 0) {
                $nome = $this->normalizarNome((string) ($item['componente'] ?? $item['componente_original'] ?? ''));
                $mid = $porNome[$nome] ?? $this->melhorMateria($nome, $porNome);
            }
            $bims[$i]['materia_id'] = $mid;
            $bims[$i]['periodo_numero'] = max(1, min(4, (int) ($item['periodo_numero'] ?? $item['bimestre'] ?? 1)));
        }
        $dados['bimestres_atuais'] = $bims;
        return $dados;
    }

    /**
     * @param array<string,int> $porNome
     */
    private function melhorMateria(string $nome, array $porNome): int
    {
        if ($nome === '') {
            return 0;
        }
        $melhor = 0;
        $score = 0;
        foreach ($porNome as $cand => $id) {
            similar_text($nome, $cand, $pct);
            if ($pct > $score && $pct >= 72) {
                $score = $pct;
                $melhor = $id;
            }
        }
        return $melhor;
    }

    private function normalizarNome(string $nome): string
    {
        $nome = mb_strtolower(trim($nome));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        if (is_string($ascii) && $ascii !== '') {
            $nome = $ascii;
        }
        $nome = preg_replace('/[^a-z0-9]+/', ' ', $nome) ?? $nome;
        return trim($nome);
    }

    private function textoServeParaEstruturar(string $texto): bool
    {
        $t = trim($texto);
        if (mb_strlen($t) < 80) {
            return false;
        }
        return preg_match_all('/[A-Za-zÀ-ú]{4,}/u', $t) >= 8;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function historicoTemComponentes(array $dados): bool
    {
        foreach (is_array($dados['anos_anteriores'] ?? null) ? $dados['anos_anteriores'] : [] as $ano) {
            if (!is_array($ano)) {
                continue;
            }
            foreach (is_array($ano['componentes'] ?? null) ? $ano['componentes'] : [] as $c) {
                if (!is_array($c)) {
                    continue;
                }
                if (trim((string) ($c['componente_original'] ?? $c['componente'] ?? '')) !== '') {
                    return true;
                }
            }
        }
        foreach (is_array($dados['bimestres_atuais'] ?? null) ? $dados['bimestres_atuais'] : [] as $b) {
            if (!is_array($b)) {
                continue;
            }
            if (trim((string) ($b['componente'] ?? $b['componente_original'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $parte
     * @return array<string,mixed>
     */
    private function mesclarHistorico(array $base, array $parte): array
    {
        if ($base === []) {
            return $parte;
        }
        foreach (['escola_origem', 'municipio', 'uf', 'data_transferencia'] as $k) {
            if (trim((string) ($base[$k] ?? '')) === '' && trim((string) ($parte[$k] ?? '')) !== '') {
                $base[$k] = $parte[$k];
            }
        }
        $base['anos_anteriores'] = $this->mesclarAnos(
            is_array($base['anos_anteriores'] ?? null) ? $base['anos_anteriores'] : [],
            is_array($parte['anos_anteriores'] ?? null) ? $parte['anos_anteriores'] : []
        );
        $base['bimestres_atuais'] = $this->mesclarBimestres(
            is_array($base['bimestres_atuais'] ?? null) ? $base['bimestres_atuais'] : [],
            is_array($parte['bimestres_atuais'] ?? null) ? $parte['bimestres_atuais'] : []
        );
        return $base;
    }

    /**
     * @param list<mixed> $a
     * @param list<mixed> $b
     * @return list<array<string,mixed>>
     */
    private function mesclarAnos(array $a, array $b): array
    {
        $porChave = [];
        foreach (array_merge($a, $b) as $ano) {
            if (!is_array($ano)) {
                continue;
            }
            $chave = trim((string) ($ano['ano_letivo'] ?? '')) . '|' . trim((string) ($ano['serie_ano'] ?? $ano['serie'] ?? ''));
            if ($chave === '|') {
                continue;
            }
            if (!isset($porChave[$chave])) {
                $ano['componentes'] = [];
                $porChave[$chave] = $ano;
                $porChave[$chave]['_comp'] = [];
            }
            if (trim((string) ($porChave[$chave]['resultado'] ?? '')) === '' && trim((string) ($ano['resultado'] ?? '')) !== '') {
                $porChave[$chave]['resultado'] = $ano['resultado'];
            }
            foreach (is_array($ano['componentes'] ?? null) ? $ano['componentes'] : [] as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $ck = mb_strtolower(trim((string) ($c['componente_original'] ?? $c['componente'] ?? '')));
                if ($ck === '' || isset($porChave[$chave]['_comp'][$ck])) {
                    continue;
                }
                $porChave[$chave]['_comp'][$ck] = true;
                $porChave[$chave]['componentes'][] = $c;
            }
        }
        $out = [];
        foreach ($porChave as $ano) {
            unset($ano['_comp']);
            $out[] = $ano;
        }
        return $out;
    }

    /**
     * @param list<mixed> $a
     * @param list<mixed> $b
     * @return list<array<string,mixed>>
     */
    private function mesclarBimestres(array $a, array $b): array
    {
        $out = [];
        $visto = [];
        foreach (array_merge($a, $b) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $k = mb_strtolower(trim((string) ($item['componente'] ?? $item['componente_original'] ?? '')))
                . '|' . (int) ($item['periodo_numero'] ?? $item['bimestre'] ?? 0);
            if ($k === '|0' || isset($visto[$k])) {
                continue;
            }
            $visto[$k] = true;
            $out[] = $item;
        }
        return $out;
    }

    /**
     * @return list<array{b64:string,mime:string}>
     */
    private function imagensParaLeitura(string $path, string $mime, string $nome): array
    {
        $mime = strtolower(trim($mime));
        $ext = strtolower(pathinfo($nome !== '' ? $nome : $path, PATHINFO_EXTENSION));
        $isImg = str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
        $isPdf = $mime === 'application/pdf' || $ext === 'pdf';
        if ($isImg) {
            $bytes = @file_get_contents($path);
            if (!is_string($bytes) || $bytes === '') {
                return [];
            }
            $mimeOut = 'image/jpeg';
            if ($mime === 'image/png' || $ext === 'png') {
                $mimeOut = 'image/png';
            } elseif ($mime === 'image/webp' || $ext === 'webp') {
                $mimeOut = 'image/webp';
            }
            return [$this->compactarImagem($bytes, $mimeOut)];
        }
        if ($isPdf) {
            return $this->rasterizarPdf($path);
        }
        return [];
    }

    /**
     * @return list<array{b64:string,mime:string}>
     */
    private function rasterizarPdf(string $path): array
    {
        $out = [];
        if (class_exists('Imagick')) {
            try {
                $im = new \Imagick();
                $im->setResolution(150, 150);
                $im->readImage($path . '[0-4]');
                foreach ($im as $page) {
                    $page->setImageBackgroundColor('white');
                    try {
                        $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                    } catch (\Throwable $e) {
                    }
                    try {
                        $flat = $page->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                        if ($flat instanceof \Imagick) {
                            $page = $flat;
                        }
                    } catch (\Throwable $e) {
                    }
                    $page->setImageFormat('jpeg');
                    $page->setImageCompressionQuality(75);
                    if ($page->getImageWidth() > 1800) {
                        $h = (int) max(1, round($page->getImageHeight() * 1800 / $page->getImageWidth()));
                        $page->resizeImage(1800, $h, \Imagick::FILTER_LANCZOS, 1);
                    }
                    $blob = $page->getImageBlob();
                    if (is_string($blob) && $blob !== '') {
                        $out[] = ['b64' => base64_encode($blob), 'mime' => 'image/jpeg'];
                    }
                }
                $im->clear();
                $im->destroy();
            } catch (\Throwable $e) {
                error_log('HistoricoOcr pdf Imagick: ' . $e->getMessage());
                $out = [];
            }
        }
        if ($out !== []) {
            return $out;
        }
        if (!function_exists('shell_exec') || !$this->comandoExiste('pdftoppm')) {
            return [];
        }
        $dir = sys_get_temp_dir() . '/ve_ocr_' . uniqid('', true);
        if (!@mkdir($dir, 0700) && !is_dir($dir)) {
            return [];
        }
        try {
            $prefix = $dir . '/p';
            @shell_exec('pdftoppm -jpeg -r 150 -f 1 -l 5 ' . escapeshellarg($path) . ' ' . escapeshellarg($prefix) . ' 2>/dev/null');
            $files = glob($prefix . '*.jpg') ?: [];
            sort($files);
            foreach ($files as $f) {
                $bytes = @file_get_contents($f);
                if (is_string($bytes) && $bytes !== '') {
                    $out[] = $this->compactarImagem($bytes, 'image/jpeg');
                }
            }
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        return $out;
    }

    /**
     * @return array{b64:string,mime:string}
     */
    private function compactarImagem(string $bytes, string $mime): array
    {
        $mime = $mime === 'image/jpg' ? 'image/jpeg' : $mime;
        if (strlen($bytes) <= 1_200_000 && str_starts_with($mime, 'image/')) {
            return ['b64' => base64_encode($bytes), 'mime' => $mime];
        }
        if (class_exists('Imagick')) {
            try {
                $im = new \Imagick();
                $im->readImageBlob($bytes);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(75);
                if ($im->getImageWidth() > 1800) {
                    $h = (int) max(1, round($im->getImageHeight() * 1800 / $im->getImageWidth()));
                    $im->resizeImage(1800, $h, \Imagick::FILTER_LANCZOS, 1);
                }
                $out = $im->getImageBlob();
                $im->clear();
                $im->destroy();
                if (is_string($out) && $out !== '') {
                    return ['b64' => base64_encode($out), 'mime' => 'image/jpeg'];
                }
            } catch (\Throwable $e) {
                error_log('HistoricoOcr compactar: ' . $e->getMessage());
            }
        }
        return ['b64' => base64_encode($bytes), 'mime' => $mime === 'image/png' ? 'image/png' : 'image/jpeg'];
    }

    private function comandoExiste(string $cmd): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }
        $out = @shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null');
        return is_string($out) && trim($out) !== '';
    }
}
