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
        $perfil = $dir . DIRECTORY_SEPARATOR . 'chrome_' . $id;
        if (!@mkdir($perfil, 0700, true) && !is_dir($perfil)) {
            return null;
        }
        @chmod($perfil, 0700);
        $htmlPath = $perfil . DIRECTORY_SEPARATOR . 'folha.html';
        $pdfPath = $perfil . DIRECTORY_SEPARATOR . 'folha.pdf';
        if (file_put_contents($htmlPath, $html) === false) {
            $this->limparPerfil($perfil, $htmlPath, $pdfPath);
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
            '--user-data-dir=' . $perfil . DIRECTORY_SEPARATOR . 'profile',
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
            'HOME' => $perfil,
            'TMPDIR' => $perfil,
            'LANG' => 'C.UTF-8',
            'XDG_CONFIG_HOME' => $perfil,
            'XDG_CACHE_HOME' => $perfil,
            'XDG_RUNTIME_DIR' => $perfil,
            'FONTCONFIG_PATH' => '/etc/fonts',
            'DBUS_SESSION_BUS_ADDRESS' => 'disabled:',
        ];
        $proc = proc_open($cmd, $desc, $pipes, null, $ambiente);
        if (!is_resource($proc)) {
            $this->limparPerfil($perfil, $htmlPath, $pdfPath);
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

        if (!is_file($pdfPath) || filesize($pdfPath) < 100) {
            $this->limparPerfil($perfil, $htmlPath, $pdfPath);
            return null;
        }
        $bin = file_get_contents($pdfPath);
        $this->limparPerfil($perfil, $htmlPath, $pdfPath);

        return is_string($bin) && $bin !== '' ? $bin : null;
    }

    private function limparPerfil(string $perfil, string $htmlPath, string $pdfPath): void
    {
        @unlink($htmlPath);
        @unlink($pdfPath);
        $this->apagarDiretorio($perfil);
    }

    private function apagarDiretorio(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $caminho = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($caminho)) {
                $this->apagarDiretorio($caminho);
            } else {
                @unlink($caminho);
            }
        }
        @rmdir($dir);
    }

    private function binario(): ?string
    {
        $env = getenv('PDF_FOLHA_CHROME');
        if (is_string($env) && $env !== '' && is_file($env) && is_executable($env)) {
            return $env;
        }
        $candidatos = [
            '/usr/lib/chromium/chromium',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ];
        foreach ($candidatos as $caminho) {
            if (is_file($caminho) && is_executable($caminho)) {
                return $caminho;
            }
        }

        return null;
    }
}

if (!class_exists('GeradorPdfFolhaService', false) && class_exists(__NAMESPACE__ . '\\GeradorPdfFolhaService', false)) {
    class_alias(__NAMESPACE__ . '\\GeradorPdfFolhaService', 'GeradorPdfFolhaService');
}
