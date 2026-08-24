<?php

/**
 * Leitura dos arquivos em backend/storage/logs (compartilhados entre escolas).
 */
class LogsAplicacaoService
{
    public const LIMITE_PADRAO = 500;
    public const LIMITE_MAXIMO = 2000;
    public const ARQUIVO_TODOS = 'todos';
    private const BYTES_CAUDA = 32 * 1024 * 1024;

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

    public function excluirArquivo(string $nome): bool
    {
        $this->arquivosCache = null;
        $nome = basename(trim($nome));
        if ($nome === '' || $nome === self::ARQUIVO_TODOS) {
            return false;
        }
        $path = $this->caminhoSeguro($nome);
        if ($path === '' || !is_file($path)) {
            return false;
        }
        return @unlink($path);
    }

    /**
     * @return array{ok:int,falha:int}
     */
    public function excluirTodos(): array
    {
        $this->arquivosCache = null;
        $ok = 0;
        $falha = 0;
        foreach ($this->listarArquivosDisco() as $meta) {
            $path = (string) ($meta['path'] ?? '');
            if ($path === '' || !is_file($path) || !$this->caminhoPermitido($path)) {
                $falha++;
                continue;
            }
            if (@unlink($path)) {
                $ok++;
            } else {
                $falha++;
            }
        }
        $this->arquivosCache = null;
        return ['ok' => $ok, 'falha' => $falha];
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
        $cauda = max($limite * 5, 2000);
        foreach ($fontes as $nome) {
            $path = $this->caminhoSeguro($nome);
            if ($path === '' || !is_readable($path)) {
                continue;
            }
            foreach ($this->lerCauda($path, $cauda, self::BYTES_CAUDA) as $texto) {
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
            $cmp = ((int) ($b['ordem'] ?? 0)) <=> ((int) ($a['ordem'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) ($a['arquivo'] ?? ''), (string) ($b['arquivo'] ?? ''));
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

    /**
     * Erros únicos (URL + mensagem) em todos os arquivos de log, ordenados por horário.
     *
     * @return list<array<string,string>>
     */
    public function errosDistintos(string $busca = '', int $max = 50): array
    {
        $buscaNorm = mb_strtolower(trim($busca));
        $fontes = $this->fontesPadrao();

        $vistos = [];
        $erros = [];
        foreach ($fontes as $nome) {
            $path = $this->caminhoSeguro($nome);
            if ($path === '' || !is_readable($path)) {
                continue;
            }
            $conteudo = $this->lerBytesFinais($path, self::BYTES_CAUDA);
            if ($conteudo === '') {
                continue;
            }
            $agulha = $buscaNorm !== '' ? $buscaNorm : '/admin';
            foreach ($this->partirConteudoLog($path, $conteudo, 8000, $agulha) as $texto) {
                $this->acumularErroDistinto($erros, $vistos, $texto, $nome, '');
            }
            if ($buscaNorm === '') {
                foreach ($this->partirConteudoLog($path, $conteudo, 800, null) as $texto) {
                    $this->acumularErroDistinto($erros, $vistos, $texto, $nome, '');
                }
            }
        }

        usort($erros, static function (array $a, array $b): int {
            $adminA = (int) (stripos($a['url'] ?? '', '/admin') !== false);
            $adminB = (int) (stripos($b['url'] ?? '', '/admin') !== false);
            if ($adminA !== $adminB) {
                return $adminB <=> $adminA;
            }
            return ($b['ordem'] <=> $a['ordem']);
        });

        $erros = array_slice($erros, 0, max(1, $max));
        foreach ($erros as &$item) {
            unset($item['ordem']);
        }
        unset($item);

        return $erros;
    }

    /**
     * @param list<array<string,mixed>> $erros
     * @param array<string,true> $vistos
     */
    private function acumularErroDistinto(array &$erros, array &$vistos, string $texto, string $nome, string $buscaNorm): void
    {
        $texto = trim($texto);
        if ($texto === '') {
            return;
        }
        if ($buscaNorm !== '' && mb_strpos(mb_strtolower($texto), $buscaNorm) === false) {
            return;
        }
        $parsed = $this->parseLinha($texto);
        if (empty($parsed['eh_erro']) && ($parsed['mensagem'] ?? '') === '') {
            return;
        }
        $parsed['arquivo'] = $nome;
        $chave = mb_strtolower(
            ($parsed['url'] !== '' ? $parsed['url'] : '')
            . '|'
            . mb_substr($parsed['mensagem'] !== '' ? $parsed['mensagem'] : $parsed['texto'], 0, 160)
        );
        if (isset($vistos[$chave])) {
            return;
        }
        $vistos[$chave] = true;
        $erros[] = $parsed;
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

    private function lerCauda(string $path, int $maxLinhas, int $maxBytes = self::BYTES_CAUDA): array
    {
        $conteudo = $this->lerBytesFinais($path, $maxBytes);
        if ($conteudo === '') {
            return [];
        }
        return $this->partirConteudoLog($path, $conteudo, $maxLinhas, null);
    }

    private function lerBytesFinais(string $path, int $maxBytes): string
    {
        $tamanho = @filesize($path);
        if ($tamanho === false || $tamanho <= 0) {
            return '';
        }
        $maxBytes = min((int) $tamanho, max(1024, $maxBytes));
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return '';
        }
        if ($maxBytes < $tamanho) {
            fseek($fh, -$maxBytes, SEEK_END);
        }
        $conteudo = (string) stream_get_contents($fh);
        fclose($fh);
        return $conteudo;
    }

    /**
     * @return list<string>
     */
    private function partirConteudoLog(string $path, string $conteudo, int $maxLinhas, ?string $filtro): array
    {
        $nome = basename($path);
        $ehBlocoLogger = (bool) preg_match('/^(app(\.data)?_|app\.data\.log$|database_|openai_|auth_|api_|email_|general_)/', $nome)
            || $nome === 'app.data.log';

        if ($ehBlocoLogger) {
            $itens = preg_split('/\n-{20,}\n?/', $conteudo) ?: [];
        } else {
            $itens = preg_split("/\r\n|\n|\r/", $conteudo) ?: [];
        }

        $filtroNorm = $filtro !== null && $filtro !== '' ? mb_strtolower($filtro) : '';
        $itens = array_values(array_filter($itens, static function ($item) use ($filtroNorm) {
            $item = trim((string) $item);
            if ($item === '') {
                return false;
            }
            if ($filtroNorm === '') {
                return true;
            }
            return mb_strpos(mb_strtolower($item), $filtroNorm) !== false;
        }));

        if (count($itens) > $maxLinhas) {
            $itens = array_slice($itens, -$maxLinhas);
        }
        return $itens;
    }

    /**
     * @return array{texto:string,escola:string,tipo:string,url:string,mensagem:string,eh_erro:bool,ordem:int,horario:string}
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
        } elseif (preg_match('/Request URI:\s*(\S+)/u', $texto, $m)) {
            $url = trim($m[1]);
        } elseif (preg_match('/\|\s*URI:\s*([^|]+)/u', $texto, $m)) {
            $url = trim($m[1]);
        }
        if ($escola === '' && $url !== '' && preg_match('#https?://([a-z0-9-]+)\.#i', $url, $m)) {
            $escola = strtolower($m[1]);
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
        } elseif (preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $texto, $m)) {
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
            'horario' => $ordem > 0 ? date('d/m/Y H:i:s', $ordem) : '',
        ];
    }
}
