<?php

/**
 * Leitura dos arquivos em backend/storage/logs (compartilhados entre escolas).
 */
class LogsAplicacaoService
{
    public const LIMITE_PADRAO = 100;
    public const LIMITE_MAXIMO = 200;
    public const ARQUIVO_TODOS = 'todos';

    private string $diretorio;

    /** @var array<string, array{bytes:int,mtime:int,path:string}>|null */
    private ?array $arquivosCache = null;

    public function __construct(?string $diretorio = null)
    {
        if (!class_exists('Logger', false)) {
            require_once dirname(__DIR__) . '/Core/Logger.php';
        }
        $this->diretorio = $diretorio ?? (string) Logger::getLogDir();
    }

    /**
     * @return array{
     *   diretorio:string,
     *   gravavel:bool,
     *   php_error_log:string,
     *   arquivos:list<array{nome:string,bytes:int,mtime:int}>
     * }
     */
    public function diagnostico(): array
    {
        $dirReal = realpath($this->diretorio) ?: $this->diretorio;
        $arquivos = [];
        foreach ($this->listarArquivosDisco() as $nome => $meta) {
            $arquivos[] = [
                'nome' => $nome,
                'bytes' => $meta['bytes'],
                'mtime' => $meta['mtime'],
            ];
        }
        return [
            'diretorio' => $dirReal,
            'gravavel' => is_dir($this->diretorio) && is_writable($this->diretorio),
            'php_error_log' => (string) ini_get('error_log'),
            'arquivos' => $arquivos,
        ];
    }

    /**
     * @return list<array{chave:string,rotulo:string,existe:bool}>
     */
    public function arquivosDisponiveis(): array
    {
        $out = [
            [
                'chave' => self::ARQUIVO_TODOS,
                'rotulo' => 'Todos os arquivos',
                'existe' => true,
            ],
        ];
        foreach ($this->listarArquivosDisco() as $nome => $meta) {
            $kb = max(1, (int) ceil($meta['bytes'] / 1024));
            $out[] = [
                'chave' => $nome,
                'rotulo' => $nome . ' (' . $kb . ' KB)',
                'existe' => true,
            ];
        }
        return $out;
    }

    /**
     * @return array{arquivo:string,linhas:list<array<string,string>>}
     */
    public function ultimasEntradas(string $arquivo, int $limite = self::LIMITE_PADRAO, string $busca = ''): array
    {
        $arquivo = $this->normalizarArquivo($arquivo);
        $limite = max(1, min(self::LIMITE_MAXIMO, $limite));
        $buscaNorm = mb_strtolower(trim($busca));

        $fontes = $arquivo === self::ARQUIVO_TODOS
            ? $this->fontesPadrao()
            : [$arquivo];

        $entradas = [];
        foreach ($fontes as $nome) {
            $path = $this->caminhoSeguro($nome);
            if ($path === '' || !is_readable($path)) {
                continue;
            }
            foreach ($this->lerCauda($path, $limite * 2) as $texto) {
                $texto = trim((string) $texto);
                if ($texto === '') {
                    continue;
                }
                if ($buscaNorm !== '' && mb_strpos(mb_strtolower($texto), $buscaNorm) === false) {
                    continue;
                }
                $parsed = $this->parseLinha($texto);
                $parsed['arquivo'] = $nome;
                $entradas[] = $parsed;
            }
        }

        usort($entradas, static function (array $a, array $b): int {
            return ($b['ordem'] <=> $a['ordem']);
        });

        if (count($entradas) > $limite) {
            $entradas = array_slice($entradas, 0, $limite);
        }

        foreach ($entradas as &$item) {
            unset($item['ordem']);
        }
        unset($item);

        return [
            'arquivo' => $arquivo,
            'linhas' => $entradas,
        ];
    }

    public function normalizarArquivo(string $arquivo): string
    {
        $arquivo = basename(trim($arquivo));
        if ($arquivo === '' || $arquivo === self::ARQUIVO_TODOS) {
            return self::ARQUIVO_TODOS;
        }
        $disco = $this->listarArquivosDisco();
        return isset($disco[$arquivo]) ? $arquivo : self::ARQUIVO_TODOS;
    }

    /**
     * @return list<string>
     */
    private function fontesPadrao(): array
    {
        $nomes = [];
        foreach (array_keys($this->listarArquivosDisco()) as $nome) {
            if ($nome === 'rotas_nao_encontrada.log') {
                continue;
            }
            $nomes[] = $nome;
        }
        return $nomes;
    }

    /**
     * @return array<string, array{bytes:int,mtime:int,path:string}>
     */
    private function listarArquivosDisco(): array
    {
        if ($this->arquivosCache !== null) {
            return $this->arquivosCache;
        }

        $dirs = [];
        $loggerDir = realpath($this->diretorio);
        if ($loggerDir !== false && is_dir($loggerDir)) {
            $dirs[$loggerDir] = true;
        }
        if (defined('BASE_PATH')) {
            $baseLogs = realpath(BASE_PATH . '/storage/logs');
            if ($baseLogs !== false && is_dir($baseLogs)) {
                $dirs[$baseLogs] = true;
            }
        }

        $out = [];
        foreach (array_keys($dirs) as $dir) {
            foreach (glob($dir . '/*.log') ?: [] as $path) {
                $this->registrarArquivo($out, $path);
            }
        }

        $phpLog = trim((string) ini_get('error_log'));
        if ($phpLog !== '' && is_file($phpLog)) {
            $this->registrarArquivo($out, $phpLog);
        }

        uasort($out, static function (array $a, array $b): int {
            return $b['mtime'] <=> $a['mtime'];
        });
        $this->arquivosCache = $out;
        return $out;
    }

    /**
     * @param array<string, array{bytes:int,mtime:int,path?:string}> $out
     */
    private function registrarArquivo(array &$out, string $path): void
    {
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            return;
        }
        $nome = basename($real);
        if (!preg_match('/^[a-zA-Z0-9_.-]+\.log$/', $nome)) {
            return;
        }
        if (!$this->caminhoPermitido($real)) {
            return;
        }
        $out[$nome] = [
            'bytes' => (int) (@filesize($real) ?: 0),
            'mtime' => (int) (@filemtime($real) ?: 0),
            'path' => $real,
        ];
    }

    private function caminhoPermitido(string $real): bool
    {
        $prefixos = [];
        $loggerDir = realpath($this->diretorio);
        if ($loggerDir !== false) {
            $prefixos[] = $loggerDir;
        }
        if (defined('BASE_PATH')) {
            $baseLogs = realpath(BASE_PATH . '/storage/logs');
            if ($baseLogs !== false) {
                $prefixos[] = $baseLogs;
            }
        }
        foreach ($prefixos as $dir) {
            if ($real === $dir || str_starts_with($real, $dir . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }

    private function caminhoSeguro(string $arquivo): string
    {
        $arquivo = basename($arquivo);
        $disco = $this->listarArquivosDisco();
        if (!isset($disco[$arquivo]['path'])) {
            return '';
        }
        $real = (string) $disco[$arquivo]['path'];
        return $this->caminhoPermitido($real) ? $real : '';
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

        $maxBytes = min((int) $tamanho, 1024 * 1024);
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        if ($maxBytes < $tamanho) {
            fseek($fh, -$maxBytes, SEEK_END);
        }
        $conteudo = (string) stream_get_contents($fh);
        fclose($fh);

        $nome = basename($path);
        $ehBlocoLogger = (bool) preg_match('/^(app(\.data)?_|app\.data\.log$|database_|openai_|auth_|api_|email_|general_)/', $nome)
            || $nome === 'app.data.log';

        if ($ehBlocoLogger) {
            $blocos = preg_split('/\n-{20,}\n?/', $conteudo) ?: [];
            $blocos = array_values(array_filter($blocos, static fn ($b) => trim((string) $b) !== ''));
            if (count($blocos) > $maxLinhas) {
                $blocos = array_slice($blocos, -$maxLinhas);
            }
            return $blocos;
        }

        $linhas = preg_split("/\r\n|\n|\r/", $conteudo) ?: [];
        $linhas = array_values(array_filter($linhas, static fn ($l) => trim((string) $l) !== ''));
        if (count($linhas) > $maxLinhas) {
            $linhas = array_slice($linhas, -$maxLinhas);
        }
        return $linhas;
    }

    /**
     * @return array{texto:string,escola:string,tipo:string,url:string,mensagem:string,eh_erro:bool,ordem:int}
     */
    private function parseLinha(string $texto): array
    {
        $escola = '';
        $tipo = '';
        $url = '';
        $mensagem = '';
        if (preg_match('/Escola:\s*([^|]+)/u', $texto, $m)) {
            $escola = trim($m[1]);
        }
        if (preg_match('/Tipo:\s*([^|]+)/u', $texto, $m)) {
            $tipo = trim($m[1]);
        }
        if (preg_match('/URL:\s*([^|]+)/u', $texto, $m)) {
            $url = trim($m[1]);
        }
        if (preg_match('/EXCEÇÃO NÃO CAPTURADA:\s*(.+)$/um', $texto, $m)) {
            $mensagem = trim($m[1]);
        } elseif (preg_match('/ERRO FATAL PHP[^:]*:\s*(.+)$/um', $texto, $m)) {
            $mensagem = trim($m[1]);
        } elseif (preg_match('/^Message:\s*(.+)$/um', $texto, $m)) {
            $mensagem = trim($m[1]);
        } elseif (preg_match('/\|\s*Error:\s*(.+)$/um', $texto, $m)) {
            $mensagem = trim($m[1]);
        }

        $ehErro = $mensagem !== ''
            || (bool) preg_match('/^\s*ERROR\b/m', $texto)
            || stripos($texto, 'EXCEÇÃO') !== false
            || stripos($texto, 'FATAL') !== false
            || stripos($texto, 'Erro na execução') !== false;

        $ordem = 0;
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $texto, $m)) {
            $ordem = (int) strtotime($m[1]);
        } elseif (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})/', $texto, $m)) {
            $ordem = (int) strtotime($m[1]);
        }

        return [
            'texto' => $texto,
            'escola' => $escola,
            'tipo' => $tipo,
            'url' => $url,
            'mensagem' => $mensagem,
            'eh_erro' => $ehErro,
            'ordem' => $ordem,
        ];
    }
}
