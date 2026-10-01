<?php

namespace App\Modulos\ModelosDocumentos\Services;

/**
 * Imprime a ficha oficial com o Chrome, o mesmo motor da tela.
 * Sem o binário, o chamador segue no Dompdf.
 */
class GeradorPdfFolhaService
{
    public function gerarSeFolhaOficial(string $html): ?string
    {
        if (!str_contains($html, 'seed-folha') && !str_contains($html, 'doc-linha')) {
            return null;
        }
        if (!function_exists('proc_open')) {
            return null;
        }
        $binario = $this->binario();
        if ($binario === null) {
            return null;
        }

        $id = bin2hex(random_bytes(8));
        $dir = sys_get_temp_dir();
        $htmlPath = $dir . DIRECTORY_SEPARATOR . 'folha_' . $id . '.html';
        $pdfPath = $dir . DIRECTORY_SEPARATOR . 'folha_' . $id . '.pdf';
        if (file_put_contents($htmlPath, $html) === false) {
            return null;
        }
        @chmod($htmlPath, 0600);

        $cmd = [
            $binario,
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--no-first-run',
            '--disable-extensions',
            '--no-pdf-header-footer',
            '--proxy-server=127.0.0.1:9',
            '--proxy-bypass-list=<-loopback>',
            '--print-to-pdf=' . $pdfPath,
            $htmlPath,
        ];
        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $ambiente = [
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
            'HOME' => $dir,
            'TMPDIR' => $dir,
            'LANG' => 'C.UTF-8',
            'XDG_CONFIG_HOME' => $dir,
            'XDG_CACHE_HOME' => $dir,
            'XDG_RUNTIME_DIR' => $dir,
        ];
        $proc = proc_open($cmd, $desc, $pipes, null, $ambiente);
        if (!is_resource($proc)) {
            @unlink($htmlPath);
            return null;
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $inicio = time();
        do {
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            usleep(100000);
        } while ((time() - $inicio) < 25);
        if (!empty($status['running'])) {
            proc_terminate($proc);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        @unlink($htmlPath);

        if (!is_file($pdfPath) || filesize($pdfPath) < 100) {
            @unlink($pdfPath);
            return null;
        }
        $bin = file_get_contents($pdfPath);
        @unlink($pdfPath);

        return is_string($bin) && $bin !== '' ? $bin : null;
    }

    private function binario(): ?string
    {
        $env = getenv('PDF_FOLHA_CHROME');
        if (is_string($env) && $env !== '' && is_file($env) && is_executable($env)) {
            return $env;
        }
        $candidatos = [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/lib/chromium/chromium',
        ];
        foreach ($candidatos as $caminho) {
            if (is_file($caminho) && is_executable($caminho)) {
                return $caminho;
            }
        }

        return null;
    }
}
