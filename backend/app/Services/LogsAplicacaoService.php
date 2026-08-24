<?php

/**
 * Leitura dos arquivos em backend/storage/logs (compartilhados entre escolas).
 */
class LogsAplicacaoService
{
    public const LIMITE_PADRAO = 100;
    public const LIMITE_MAXIMO = 200;

    private string $diretorio;

    public function __construct(?string $diretorio = null)
    {
        if (!class_exists('Logger', false)) {
            require_once dirname(__DIR__) . '/Core/Logger.php';
        }
        $this->diretorio = $diretorio ?? (string) Logger::getLogDir();
    }

    /**
     * @return list<array{chave:string,rotulo:string,existe:bool}>
     */
    public function arquivosDisponiveis(): array
    {
        $hoje = date('Y-m-d');
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $candidatos = [
            'error.log' => 'Erros PHP (error.log)',
            'app_' . $hoje . '.log' => 'App de hoje',
            'app_' . $ontem . '.log' => 'App de ontem',
            'database_' . $hoje . '.log' => 'Banco de hoje',
            'app.data.log' => 'App contínuo (app.data.log)',
        ];

        $out = [];
        foreach ($candidatos as $chave => $rotulo) {
            $out[] = [
                'chave' => $chave,
                'rotulo' => $rotulo,
                'existe' => is_file($this->caminhoSeguro($chave)),
            ];
        }
        return $out;
    }

    /**
     * @return array{arquivo:string,linhas:list<array{texto:string,escola:string,tipo:string,url:string}>}
     */
    public function ultimasEntradas(string $arquivo, int $limite = self::LIMITE_PADRAO, string $busca = ''): array
    {
        $arquivo = $this->normalizarArquivo($arquivo);
        $limite = max(1, min(self::LIMITE_MAXIMO, $limite));
        $path = $this->caminhoSeguro($arquivo);
        $linhasBrutas = $path !== '' && is_readable($path)
            ? $this->lerCauda($path, $limite * 3)
            : [];

        $buscaNorm = mb_strtolower(trim($busca));
        $entradas = [];
        foreach (array_reverse($linhasBrutas) as $texto) {
            $texto = trim((string) $texto);
            if ($texto === '') {
                continue;
            }
            if ($buscaNorm !== '' && mb_strpos(mb_strtolower($texto), $buscaNorm) === false) {
                continue;
            }
            $entradas[] = $this->parseLinha($texto);
            if (count($entradas) >= $limite) {
                break;
            }
        }

        return [
            'arquivo' => $arquivo,
            'linhas' => $entradas,
        ];
    }

    public function normalizarArquivo(string $arquivo): string
    {
        $arquivo = basename(trim($arquivo));
        foreach ($this->arquivosDisponiveis() as $item) {
            if ($item['chave'] === $arquivo) {
                return $arquivo;
            }
        }
        return 'error.log';
    }

    private function caminhoSeguro(string $arquivo): string
    {
        $arquivo = basename($arquivo);
        if (!preg_match('/^[a-zA-Z0-9_.-]+\.log$/', $arquivo)) {
            return '';
        }
        $dir = realpath($this->diretorio);
        if ($dir === false) {
            return '';
        }
        $path = $dir . DIRECTORY_SEPARATOR . $arquivo;
        $real = realpath($path);
        if ($real === false) {
            return $path;
        }
        if (!str_starts_with($real, $dir . DIRECTORY_SEPARATOR) && $real !== $dir) {
            return '';
        }
        return $real;
    }

    /**
     * @return list<string>
     */
    private function lerCauda(string $path, int $maxLinhas): array
    {
        $tamanho = @filesize($path);
        if ($tamanho === false || $tamanho <= 0) {
            return [];
        }

        $maxBytes = min((int) $tamanho, 512 * 1024);
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        if ($maxBytes < $tamanho) {
            fseek($fh, -$maxBytes, SEEK_END);
        }
        $conteudo = (string) stream_get_contents($fh);
        fclose($fh);

        $linhas = preg_split("/\r\n|\n|\r/", $conteudo) ?: [];
        $linhas = array_values(array_filter($linhas, static fn ($l) => trim((string) $l) !== ''));
        if (count($linhas) > $maxLinhas) {
            $linhas = array_slice($linhas, -$maxLinhas);
        }
        return $linhas;
    }

    /**
     * @return array{texto:string,escola:string,tipo:string,url:string}
     */
    private function parseLinha(string $texto): array
    {
        $escola = '';
        $tipo = '';
        $url = '';
        if (preg_match('/Escola:\s*([^|]+)/u', $texto, $m)) {
            $escola = trim($m[1]);
        }
        if (preg_match('/Tipo:\s*([^|]+)/u', $texto, $m)) {
            $tipo = trim($m[1]);
        }
        if (preg_match('/URL:\s*([^|]+)/u', $texto, $m)) {
            $url = trim($m[1]);
        }
        return [
            'texto' => $texto,
            'escola' => $escola,
            'tipo' => $tipo,
            'url' => $url,
        ];
    }
}
