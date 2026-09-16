<?php
/**
 * Clona uma escola (tenant) no Master: cadastro, layout, limites, créditos e banco MySQL.
 * Executado pela fila (MasterFilaService) — nunca na request HTTP.
 */

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/MasterSecretVault.php';
require_once __DIR__ . '/../Core/MasterTenantConnection.php';
require_once __DIR__ . '/../Core/MysqlProvisioningService.php';
require_once __DIR__ . '/DominioEscolaService.php';
require_once __DIR__ . '/MasterFilaService.php';

class ClonarEscolaService
{
    /** @var list<string> */
    private const LAYOUT_SKIP = [
        'educaprof_api_key',
        'educaprof_api_base_url',
        'educaprof_school_slug',
        'maintenance_mode',
    ];

    /**
     * Valida o POST, reserva slug/domínio e enfileira o job.
     *
     * @param array<string,mixed> $post
     * @return array{success:bool,error?:string,job_id?:int,escola_destino_id?:int}
     */
    public static function enfileirar(array $post, int $masterUserId): array
    {
        $origemId = (int) ($post['escola_origem_id'] ?? $post['id'] ?? 0);
        $nome = trim((string) ($post['nome'] ?? ''));
        $slug = self::normalizarSlug((string) ($post['slug'] ?? ''), $nome);
        $dominioInput = trim((string) ($post['dominio'] ?? ''));
        $ativo = isset($post['ativo']) && (string) $post['ativo'] === '1' ? 1 : 0;

        $host = trim((string) ($post['db_host'] ?? 'localhost'));
        $porta = (int) ($post['db_porta'] ?? 3306);
        $nomeBanco = trim((string) ($post['db_nome_banco'] ?? ''));
        $usuario = trim((string) ($post['db_usuario'] ?? ''));
        $senha = (string) ($post['db_senha'] ?? '');
        $criarBanco = !empty($post['criar_banco_automaticamente']);

        if ($origemId < 1) {
            return ['success' => false, 'error' => 'Escola de origem inválida.'];
        }
        if ($nome === '' || $slug === '') {
            return ['success' => false, 'error' => 'Informe o nome da nova escola.'];
        }
        if ($host === '' || $nomeBanco === '' || $usuario === '') {
            return ['success' => false, 'error' => 'Informe host, nome do banco e usuário do destino.'];
        }
        if ($senha === '') {
            return ['success' => false, 'error' => 'Informe a senha do banco de destino.'];
        }
        if ($porta < 1 || $porta > 65535) {
            return ['success' => false, 'error' => 'Porta do banco inválida.'];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $nomeBanco) || strlen($nomeBanco) > 64) {
            return ['success' => false, 'error' => 'Nome do banco destino inválido (apenas letras, números e underscore).'];
        }
        $reservados = ['mysql', 'sys', 'information_schema', 'performance_schema'];
        if (in_array(strtolower($nomeBanco), $reservados, true)) {
            return ['success' => false, 'error' => 'Não é permitido clonar para um banco de sistema do MySQL.'];
        }

        $pdo = MasterFilaService::masterPdo();
        if (!$pdo instanceof PDO) {
            return ['success' => false, 'error' => 'Não foi possível conectar ao banco master.'];
        }

        $origem = self::buscarEscolaComBanco($pdo, $origemId);
        if (!$origem) {
            return ['success' => false, 'error' => 'Escola de origem não encontrada ou sem banco configurado.'];
        }

        $mesmoBanco = strtolower((string) $origem['host']) === strtolower($host)
            && (int) $origem['porta'] === $porta
            && strtolower((string) $origem['nome_banco']) === strtolower($nomeBanco);
        if ($mesmoBanco) {
            return ['success' => false, 'error' => 'O banco de destino não pode ser o mesmo da escola de origem.'];
        }

        $masterCfg = Database::getConfigFromEnv();
        $masterNome = strtolower((string) ($masterCfg['name'] ?? ''));
        if ($masterNome !== '' && strtolower($nomeBanco) === $masterNome) {
            return ['success' => false, 'error' => 'O banco de destino não pode ser o banco master.'];
        }

        $dupBanco = $pdo->prepare(
            'SELECT escola_id FROM config_escolas_banco WHERE host = :host AND porta = :porta AND nome_banco = :nome LIMIT 1'
        );
        $dupBanco->execute(['host' => $host, 'porta' => $porta, 'nome' => $nomeBanco]);
        if ($dupBanco->fetch()) {
            return ['success' => false, 'error' => 'Já existe uma escola usando este banco de dados.'];
        }

        if (MasterFilaService::tabelaExiste($pdo)) {
            $stFila = $pdo->query(
                "SELECT payload FROM fila_jobs_master
                  WHERE tipo = 'clonar_escola' AND status IN ('pending','processing')"
            );
            while ($stFila && ($rowFila = $stFila->fetch(PDO::FETCH_ASSOC))) {
                $p = json_decode((string) ($rowFila['payload'] ?? ''), true);
                if (!is_array($p)) {
                    continue;
                }
                $mesmoDestinoFila = strtolower((string) ($p['db_host'] ?? '')) === strtolower($host)
                    && (int) ($p['db_porta'] ?? 0) === $porta
                    && strtolower((string) ($p['db_nome_banco'] ?? '')) === strtolower($nomeBanco);
                if ($mesmoDestinoFila) {
                    return ['success' => false, 'error' => 'Já existe uma clonagem em andamento para este banco de destino.'];
                }
            }
        }

        $stSlug = $pdo->prepare('SELECT id FROM escolas WHERE slug = :slug LIMIT 1');
        $stSlug->execute(['slug' => $slug]);
        if ($stSlug->fetch()) {
            return ['success' => false, 'error' => 'Já existe uma escola com este slug. Altere o slug da cópia.'];
        }

        $dominioSvc = new DominioEscolaService();
        $domRes = $dominioSvc->normalizarDominioParaSalvar($slug, $dominioInput);
        if (!empty($domRes['erro'])) {
            return ['success' => false, 'error' => (string) $domRes['erro']];
        }
        $dominio = (string) $domRes['dominio'];
        $stDom = $pdo->prepare('SELECT id FROM escolas WHERE dominio = :dominio LIMIT 1');
        $stDom->execute(['dominio' => $dominio]);
        if ($stDom->fetch()) {
            return ['success' => false, 'error' => 'Já existe uma escola com este domínio.'];
        }

        $ins = $pdo->prepare(
            'INSERT INTO escolas (nome, slug, dominio, ativo, dns_status, ssl_status)
             VALUES (:nome, :slug, :dominio, 0, :dns, :ssl)'
        );
        $ins->execute([
            'nome' => $nome,
            'slug' => $slug,
            'dominio' => $dominio,
            'dns' => (string) ($domRes['dns_status'] ?? DominioEscolaService::DNS_NAO_CONFIGURADO),
            'ssl' => DominioEscolaService::SSL_NAO_VERIFICADO,
        ]);
        $destinoId = (int) $pdo->lastInsertId();

        self::gravarConfigBanco($pdo, $destinoId, $host, $porta, $nomeBanco, $usuario, $senha);

        $payload = [
            'escola_origem_id' => $origemId,
            'escola_destino_id' => $destinoId,
            'nome' => $nome,
            'slug' => $slug,
            'dominio' => $dominio,
            'ativo' => $ativo,
            'criar_banco' => $criarBanco ? 1 : 0,
            'db_host' => $host,
            'db_porta' => $porta,
            'db_nome_banco' => $nomeBanco,
            'db_usuario' => $usuario,
            'db_senha_criptografada' => MasterSecretVault::encryptDbPassword($senha),
        ];

        try {
            $jobId = MasterFilaService::enfileirar(
                MasterFilaService::TIPO_CLONAR_ESCOLA,
                $payload,
                $origemId,
                $destinoId,
                $masterUserId > 0 ? $masterUserId : null
            );
        } catch (Throwable $e) {
            try {
                $pdo->prepare('DELETE FROM escolas WHERE id = :id LIMIT 1')->execute(['id' => $destinoId]);
            } catch (Throwable $ignored) {
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return [
            'success' => true,
            'job_id' => $jobId,
            'escola_destino_id' => $destinoId,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function executar(array $payload): array
    {
        $jobId = (int) ($payload['_job_id'] ?? 0);
        $origemId = (int) ($payload['escola_origem_id'] ?? 0);
        $destinoId = (int) ($payload['escola_destino_id'] ?? 0);
        $nome = trim((string) ($payload['nome'] ?? ''));
        $slug = self::normalizarSlug((string) ($payload['slug'] ?? ''), $nome);
        $ativo = !empty($payload['ativo']) ? 1 : 0;
        $criarBanco = !empty($payload['criar_banco']);

        $host = trim((string) ($payload['db_host'] ?? ''));
        $porta = (int) ($payload['db_porta'] ?? 3306);
        $nomeBanco = trim((string) ($payload['db_nome_banco'] ?? ''));
        $usuario = trim((string) ($payload['db_usuario'] ?? ''));
        $senha = MasterSecretVault::decryptDbPassword((string) ($payload['db_senha_criptografada'] ?? ''));

        if ($origemId < 1 || $destinoId < 1 || $nome === '' || $slug === '') {
            throw new RuntimeException('Payload de clonagem incompleto.');
        }
        if ($host === '' || $nomeBanco === '' || $usuario === '' || $senha === '') {
            throw new RuntimeException('Credenciais do banco destino incompletas.');
        }

        $masterPdo = MasterFilaService::masterPdo();
        if (!$masterPdo instanceof PDO) {
            throw new RuntimeException('Não foi possível conectar ao banco master.');
        }

        $origem = self::buscarEscolaComBanco($masterPdo, $origemId);
        if (!$origem) {
            throw new RuntimeException('Escola de origem indisponível no momento da clonagem.');
        }

        $progresso = static function (string $msg, int $pct) use ($jobId, $destinoId): void {
            MasterFilaService::atualizarProgresso($jobId, $msg, $pct, ['escola_destino_id' => $destinoId]);
        };

        $progresso('Preparando banco de destino…', 5);

        $reservados = ['mysql', 'sys', 'information_schema', 'performance_schema'];
        if (in_array(strtolower($nomeBanco), $reservados, true)) {
            throw new RuntimeException('Não é permitido clonar para um banco de sistema do MySQL.');
        }

        $tentativas = 1;
        if ($jobId > 0) {
            $stTent = $masterPdo->prepare('SELECT tentativas FROM fila_jobs_master WHERE id = :id LIMIT 1');
            $stTent->execute(['id' => $jobId]);
            $tentativas = max(1, (int) $stTent->fetchColumn());
        }

        if ($criarBanco) {
            self::criarBancoDestino($host, $porta, $nomeBanco, $usuario, $senha);
        }

        self::garantirBancoDestinoPronto($host, $porta, $nomeBanco, $usuario, $senha, $destinoId, $masterPdo, $tentativas > 1);

        $tmpDir = self::tmpDir($jobId);
        $dumpFile = $tmpDir . '/dump.sql';
        $cnfFile = $tmpDir . '/origem.cnf';
        $cnfDest = $tmpDir . '/destino.cnf';

        try {
            $progresso('Exportando banco da escola de origem…', 15);
            $senhaOrigem = MasterSecretVault::decryptDbPassword((string) ($origem['senha_criptografada'] ?? ''));
            self::escreverCnf($cnfFile, (string) $origem['host'], (int) $origem['porta'], (string) $origem['usuario'], $senhaOrigem);
            self::escreverCnf($cnfDest, $host, $porta, $usuario, $senha);
            self::dumpBanco($cnfFile, (string) $origem['nome_banco'], $dumpFile);

            $progresso('Importando dump no banco de destino…', 50);
            self::importarDump($cnfDest, $nomeBanco, $dumpFile);

            $progresso('Gravando conexão e copiando configurações do Master…', 75);
            self::gravarConfigBanco($masterPdo, $destinoId, $host, $porta, $nomeBanco, $usuario, $senha);
            self::copiarMetadadosMaster($masterPdo, $origemId, $destinoId, (string) $origem['slug'], $slug, $nome);

            $progresso('Alinhando o schema com as migrations atuais do projeto…', 82);
            $tenantPdo = self::conectarMysql($host, $porta, $usuario, $senha, $nomeBanco, true);
            MysqlProvisioningService::runTenantMigrations($masterPdo, $tenantPdo, $destinoId, true);

            $progresso('Ajustando a cópia no banco da nova escola…', 88);
            self::posProcessarTenant($host, $porta, $nomeBanco, $usuario, $senha, (string) $origem['nome'], $nome);

            $progresso('Copiando arquivos locais da escola…', 92);
            $arquivos = self::copiarArquivosLocais((string) $origem['slug'], $slug);

            $upAtivo = $masterPdo->prepare('UPDATE escolas SET ativo = :ativo WHERE id = :id');
            $upAtivo->execute(['ativo' => $ativo, 'id' => $destinoId]);

            self::invalidarCacheDestino($masterPdo, $destinoId);
        } finally {
            self::limparDir($tmpDir);
        }

        return [
            'ok' => true,
            'escola_destino_id' => $destinoId,
            'slug' => $slug,
            'arquivos_copiados' => $arquivos,
            'mensagem' => 'Escola clonada com sucesso.',
            'percentual' => 100,
        ];
    }

    public static function normalizarSlug(string $slug, string $nomeFallback = ''): string
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' && $nomeFallback !== '') {
            $slug = strtolower($nomeFallback);
        }
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
            if (is_string($converted) && $converted !== '') {
                $slug = $converted;
            }
        }
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function buscarEscolaComBanco(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare(
            'SELECT e.id, e.nome, e.slug, e.dominio, e.ativo,
                    b.host, b.porta, b.nome_banco, b.usuario, b.senha_criptografada
               FROM escolas e
               INNER JOIN config_escolas_banco b ON b.escola_id = e.id
              WHERE e.id = :id
              LIMIT 1'
        );
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function criarBancoDestino(string $host, int $porta, string $nomeBanco, string $usuario, string $senha): void
    {
        if (!MysqlProvisioningService::isAvailable()) {
            throw new RuntimeException('Criação automática de banco indisponível (defina DB_ADMIN_USER e DB_ADMIN_PASS).');
        }
        $admin = MysqlProvisioningService::getAdminConnectionParams();
        $adminUser = (string) (function_exists('env') ? env('DB_ADMIN_USER', '') : '');
        $adminPass = (string) (function_exists('env') ? env('DB_ADMIN_PASS', '') : '');
        $adminHost = (string) ($admin['host'] ?? $host);
        $adminPorta = (int) ($admin['port'] ?? $porta);
        try {
            MysqlProvisioningService::createDatabaseAndUser(
                $adminHost,
                $adminPorta,
                $adminUser,
                $adminPass,
                $nomeBanco,
                $usuario,
                $senha
            );
        } catch (Throwable $e) {
            throw new RuntimeException(
                MysqlProvisioningService::formatarErroAdminMysql($e, $adminUser, $adminHost)
            );
        }
    }

    private static function garantirBancoDestinoPronto(
        string $host,
        int $porta,
        string $nomeBanco,
        string $usuario,
        string $senha,
        int $destinoId,
        PDO $masterPdo,
        bool $permitirEsvaziar = false
    ): void {
        $pdo = self::conectarMysql($host, $porta, $usuario, $senha, $nomeBanco, false);
        $dbQ = self::quoteIdent($nomeBanco);
        try {
            $pdo->exec('USE ' . $dbQ);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Não foi possível abrir o banco destino "' . $nomeBanco . '". Crie o banco (ou marque criar automaticamente) e tente de novo. Detalhe: ' . $e->getMessage()
            );
        }
        $n = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        if ($n === 0) {
            return;
        }

        $st = $masterPdo->prepare(
            'SELECT host, porta, nome_banco FROM config_escolas_banco WHERE escola_id = :id LIMIT 1'
        );
        $st->execute(['id' => $destinoId]);
        $cfg = $st->fetch(PDO::FETCH_ASSOC);
        $mesmoDestino = is_array($cfg) && (
            strtolower((string) ($cfg['host'] ?? '')) === strtolower($host)
            && (int) ($cfg['porta'] ?? 0) === $porta
            && strtolower((string) ($cfg['nome_banco'] ?? '')) === strtolower($nomeBanco)
        );
        if (!$permitirEsvaziar || !$mesmoDestino) {
            throw new RuntimeException(
                'O banco destino "' . $nomeBanco . '" não está vazio (' . $n . ' tabela(s)). Use um banco novo para a clonagem.'
            );
        }

        self::esvaziarBanco($pdo);
    }

    private static function esvaziarBanco(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);
        foreach ($views as $row) {
            $name = (string) ($row[0] ?? '');
            if ($name !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $pdo->exec('DROP VIEW IF EXISTS `' . $name . '`');
            }
        }
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $row) {
            $name = (string) ($row[0] ?? '');
            if ($name !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                $pdo->exec('DROP TABLE IF EXISTS `' . $name . '`');
            }
        }
        try {
            $procs = $pdo->query(
                "SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.routines WHERE ROUTINE_SCHEMA = DATABASE()"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($procs as $p) {
                $name = (string) ($p['ROUTINE_NAME'] ?? '');
                $type = strtoupper((string) ($p['ROUTINE_TYPE'] ?? ''));
                if ($name === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                    continue;
                }
                if ($type === 'PROCEDURE') {
                    $pdo->exec('DROP PROCEDURE IF EXISTS `' . $name . '`');
                } elseif ($type === 'FUNCTION') {
                    $pdo->exec('DROP FUNCTION IF EXISTS `' . $name . '`');
                }
            }
        } catch (Throwable $e) {
            // sem permissão em routines
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private static function conectarMysql(
        string $host,
        int $porta,
        string $usuario,
        string $senha,
        string $nomeBanco = '',
        bool $requireDb = true
    ): PDO {
        $dsn = 'mysql:host=' . str_replace([':', ';', ' '], '', $host) . ';port=' . $porta . ';charset=utf8mb4';
        if ($requireDb && $nomeBanco !== '') {
            $dsn .= ';dbname=' . $nomeBanco;
        }
        $pdo = new PDO($dsn, $usuario, $senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec("SET time_zone = '-03:00'");
        return $pdo;
    }

    private static function escreverCnf(string $path, string $host, int $porta, string $usuario, string $senha): void
    {
        $ini = "[client]\n"
            . 'host=' . $host . "\n"
            . 'port=' . $porta . "\n"
            . 'user=' . $usuario . "\n"
            . 'password=' . str_replace(["\n", "\r"], '', $senha) . "\n"
            . "default-character-set=utf8mb4\n";
        if (file_put_contents($path, $ini) === false) {
            throw new RuntimeException('Não foi possível gravar arquivo temporário de conexão MySQL.');
        }
        @chmod($path, 0600);
    }

    private static function dumpBanco(string $cnf, string $nomeBanco, string $dumpFile): void
    {
        $bin = self::findBin('mysqldump');
        if ($bin === null) {
            throw new RuntimeException('mysqldump não encontrado no servidor. Instale o cliente MySQL para clonar o banco.');
        }
        self::validarIdentificador($nomeBanco, 'banco de origem');

        $attempts = [
            ['--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob', '--default-character-set=utf8mb4', '--set-gtid-purged=OFF', '--column-statistics=0', '--skip-definer'],
            ['--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob', '--default-character-set=utf8mb4', '--set-gtid-purged=OFF', '--column-statistics=0'],
            ['--single-transaction', '--quick', '--routines', '--triggers', '--hex-blob', '--default-character-set=utf8mb4'],
        ];

        $lastError = '';
        foreach ($attempts as $flags) {
            $cmd = escapeshellarg($bin)
                . ' --defaults-extra-file=' . escapeshellarg($cnf)
                . ' ' . implode(' ', $flags)
                . ' ' . escapeshellarg($nomeBanco);
            $result = self::runToFile($cmd, $dumpFile);
            if ($result['ok'] && is_file($dumpFile) && filesize($dumpFile) > 50) {
                self::stripDefiners($dumpFile);
                return;
            }
            $lastError = $result['error'] !== '' ? $result['error'] : 'dump vazio';
        }

        throw new RuntimeException('Falha no mysqldump: ' . $lastError);
    }

    private static function importarDump(string $cnf, string $nomeBanco, string $dumpFile): void
    {
        $bin = self::findBin('mysql');
        if ($bin === null) {
            throw new RuntimeException('Cliente mysql não encontrado no servidor. Instale o cliente MySQL para importar o dump.');
        }
        if (!is_file($dumpFile) || filesize($dumpFile) < 50) {
            throw new RuntimeException('Arquivo de dump inválido ou vazio.');
        }
        self::validarIdentificador($nomeBanco, 'banco destino');

        $cmd = escapeshellarg($bin)
            . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' --default-character-set=utf8mb4 '
            . escapeshellarg($nomeBanco);
        $result = self::runFromFile($cmd, $dumpFile);
        if (!$result['ok']) {
            throw new RuntimeException('Falha ao importar dump: ' . $result['error']);
        }
    }

    /**
     * @return array{ok:bool,error:string}
     */
    private static function runToFile(string $cmd, string $outFile): array
    {
        $stderrFile = $outFile . '.err';
        $full = $cmd . ' > ' . escapeshellarg($outFile) . ' 2>' . escapeshellarg($stderrFile);
        $code = 0;
        exec($full, $unused, $code);
        $err = is_file($stderrFile) ? trim((string) file_get_contents($stderrFile)) : '';
        @unlink($stderrFile);
        if ($code !== 0) {
            @unlink($outFile);
            return ['ok' => false, 'error' => $err !== '' ? $err : ('código ' . $code)];
        }
        return ['ok' => true, 'error' => $err];
    }

    /**
     * @return array{ok:bool,error:string}
     */
    private static function runFromFile(string $cmd, string $inFile): array
    {
        $stderrFile = $inFile . '.import.err';
        $full = $cmd . ' < ' . escapeshellarg($inFile) . ' 2>' . escapeshellarg($stderrFile);
        $code = 0;
        exec($full, $unused, $code);
        $err = is_file($stderrFile) ? trim((string) file_get_contents($stderrFile)) : '';
        @unlink($stderrFile);
        if ($code !== 0) {
            return ['ok' => false, 'error' => $err !== '' ? $err : ('código ' . $code)];
        }
        return ['ok' => true, 'error' => $err];
    }

    private static function stripDefiners(string $dumpFile): void
    {
        $in = fopen($dumpFile, 'rb');
        if ($in === false) {
            return;
        }
        $tmp = $dumpFile . '.tmp';
        $out = fopen($tmp, 'wb');
        if ($out === false) {
            fclose($in);
            return;
        }
        while (($line = fgets($in)) !== false) {
            $line = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', '', $line) ?? $line;
            fwrite($out, $line);
        }
        fclose($in);
        fclose($out);
        @rename($tmp, $dumpFile);
    }

    private static function gravarConfigBanco(
        PDO $pdo,
        int $escolaId,
        string $host,
        int $porta,
        string $nomeBanco,
        string $usuario,
        string $senha
    ): void {
        $st = $pdo->prepare(
            'INSERT INTO config_escolas_banco (escola_id, host, porta, nome_banco, usuario, senha_criptografada)
             VALUES (:id, :host, :porta, :nome, :usuario, :senha)
             ON DUPLICATE KEY UPDATE
                host = VALUES(host),
                porta = VALUES(porta),
                nome_banco = VALUES(nome_banco),
                usuario = VALUES(usuario),
                senha_criptografada = VALUES(senha_criptografada)'
        );
        $st->execute([
            'id' => $escolaId,
            'host' => $host,
            'porta' => $porta,
            'nome' => $nomeBanco,
            'usuario' => $usuario,
            'senha' => MasterSecretVault::encryptDbPassword($senha),
        ]);
    }

    private static function copiarMetadadosMaster(
        PDO $pdo,
        int $origemId,
        int $destinoId,
        string $slugOrigem,
        string $slugDestino,
        string $nomeNovo
    ): void {
        if (self::tabelaExiste($pdo, 'config_escolas_layout')) {
            $rows = $pdo->prepare('SELECT config_key, config_value FROM config_escolas_layout WHERE escola_id = :id');
            $rows->execute(['id' => $origemId]);
            $ins = $pdo->prepare(
                'INSERT INTO config_escolas_layout (escola_id, config_key, config_value)
                 VALUES (:id, :k, :v)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)'
            );
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $key = (string) ($row['config_key'] ?? '');
                if ($key === '' || in_array($key, self::LAYOUT_SKIP, true)) {
                    continue;
                }
                $val = (string) ($row['config_value'] ?? '');
                if ($slugOrigem !== '' && $slugDestino !== '' && $slugOrigem !== $slugDestino) {
                    $val = str_replace('/' . $slugOrigem . '/', '/' . $slugDestino . '/', $val);
                }
                if (in_array($key, ['system_title', 'nome_escola', 'school_name'], true)) {
                    $val = $nomeNovo;
                }
                $ins->execute(['id' => $destinoId, 'k' => $key, 'v' => $val]);
            }
            $ins->execute(['id' => $destinoId, 'k' => 'maintenance_mode', 'v' => '0']);
        }

        if (self::tabelaExiste($pdo, 'limites_escolas')) {
            $lim = $pdo->prepare('SELECT * FROM limites_escolas WHERE escola_id = :id LIMIT 1');
            $lim->execute(['id' => $origemId]);
            $row = $lim->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                unset($row['id'], $row['escola_id'], $row['created_at'], $row['updated_at']);
                $cols = array_keys($row);
                if ($cols) {
                    $fields = implode(', ', array_merge(['escola_id'], $cols));
                    $place = implode(', ', array_merge([':escola_id'], array_map(static fn ($c) => ':' . $c, $cols)));
                    $sql = "INSERT INTO limites_escolas ({$fields}) VALUES ({$place})
                            ON DUPLICATE KEY UPDATE " . implode(', ', array_map(static fn ($c) => "{$c} = VALUES({$c})", $cols));
                    $params = ['escola_id' => $destinoId];
                    foreach ($row as $k => $v) {
                        $params[$k] = $v;
                    }
                    $pdo->prepare($sql)->execute($params);
                }
            }
        }

        self::copiarPares($pdo, 'escolas_creditos_vinculo', 'escola_id', $origemId, $destinoId);
        self::copiarPares($pdo, 'escolas_creditos_pacotes', 'escola_id', $origemId, $destinoId);
        self::copiarPares($pdo, 'escolas_creditos_planos', 'escola_id', $origemId, $destinoId);
        self::copiarPares($pdo, 'videos_tutoriais_escolas', 'escola_id', $origemId, $destinoId);

        if (self::tabelaExiste($pdo, 'migrations_escolas')) {
            $pdo->prepare('DELETE FROM migrations_escolas WHERE escola_id = :id')->execute(['id' => $destinoId]);
            $pdo->prepare(
                'INSERT INTO migrations_escolas (escola_id, migration_name, executed_at)
                 SELECT :dest, migration_name, executed_at FROM migrations_escolas WHERE escola_id = :orig'
            )->execute(['dest' => $destinoId, 'orig' => $origemId]);
        }
    }

    private static function copiarPares(PDO $pdo, string $tabela, string $colEscola, int $origemId, int $destinoId): void
    {
        if (!self::tabelaExiste($pdo, $tabela)) {
            return;
        }
        self::validarIdentificador($tabela, 'tabela');
        self::validarIdentificador($colEscola, 'coluna');
        $colsStmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $tabela) . '`');
        $cols = [];
        while ($c = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            $field = (string) ($c['Field'] ?? '');
            if ($field !== '' && $field !== $colEscola) {
                $cols[] = $field;
            }
        }
        if (!$cols) {
            return;
        }
        $colList = '`' . implode('`, `', $cols) . '`';
        $sql = "INSERT IGNORE INTO `{$tabela}` (`{$colEscola}`, {$colList})
                SELECT :dest, {$colList} FROM `{$tabela}` WHERE `{$colEscola}` = :orig";
        $pdo->prepare($sql)->execute(['dest' => $destinoId, 'orig' => $origemId]);
    }

    private static function posProcessarTenant(
        string $host,
        int $porta,
        string $nomeBanco,
        string $usuario,
        string $senha,
        string $nomeAntigo,
        string $nomeNovo
    ): void {
        $pdo = self::conectarMysql($host, $porta, $usuario, $senha, $nomeBanco, true);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach (['sessions', 'ci_sessions', 'login_attempts'] as $tabela) {
            if (self::tabelaExiste($pdo, $tabela)) {
                $pdo->exec('TRUNCATE TABLE `' . str_replace('`', '', $tabela) . '`');
            }
        }

        if (self::tabelaExiste($pdo, 'config_layout')) {
            $keysNome = ['system_title', 'nome_escola', 'school_name'];
            $st = $pdo->prepare(
                "UPDATE config_layout SET config_value = :novo, updated_at = NOW()
                  WHERE config_key = :k AND (config_value = :antigo OR config_value = '' OR config_value IS NULL)"
            );
            foreach ($keysNome as $k) {
                try {
                    $st->execute(['novo' => $nomeNovo, 'k' => $k, 'antigo' => $nomeAntigo]);
                } catch (Throwable $e) {
                    $pdo->prepare(
                        'UPDATE config_layout SET config_value = :novo WHERE config_key = :k'
                    )->execute(['novo' => $nomeNovo, 'k' => $k]);
                }
            }
            try {
                $pdo->prepare(
                    "INSERT INTO config_layout (config_key, config_value)
                     VALUES ('maintenance_mode', '0')
                     ON DUPLICATE KEY UPDATE config_value = '0'"
                )->execute();
            } catch (Throwable $e) {
                // schema legado sem unique em config_key
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @return array{ok:bool,detalhe:string}
     */
    private static function copiarArquivosLocais(string $slugOrigem, string $slugDestino): array
    {
        $slugOrigem = self::normalizarSlug($slugOrigem);
        $slugDestino = self::normalizarSlug($slugDestino);
        if ($slugOrigem === '' || $slugDestino === '' || $slugOrigem === $slugDestino) {
            return ['ok' => true, 'detalhe' => 'sem cópia de arquivos'];
        }
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $pares = [
            $base . '/storage/files/' . $slugOrigem => $base . '/storage/files/' . $slugDestino,
            $base . '/storage/uploads/' . $slugOrigem => $base . '/storage/uploads/' . $slugDestino,
            $base . '/public/uploads/' . $slugOrigem => $base . '/public/uploads/' . $slugDestino,
        ];
        $copiados = 0;
        foreach ($pares as $from => $to) {
            if (!is_dir($from)) {
                continue;
            }
            $copiados += self::copiarDir($from, $to);
        }
        return ['ok' => true, 'detalhe' => $copiados . ' arquivo(s) locais copiados'];
    }

    private static function copiarDir(string $from, string $to): int
    {
        $count = 0;
        if (!is_dir($to) && !@mkdir($to, 0755, true) && !is_dir($to)) {
            return 0;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $dest = $to . DIRECTORY_SEPARATOR . $it->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    @mkdir($dest, 0755, true);
                }
                continue;
            }
            $dir = dirname($dest);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (@copy($item->getPathname(), $dest)) {
                $count++;
            }
        }
        return $count;
    }

    private static function invalidarCacheDestino(PDO $pdo, int $escolaId): void
    {
        $st = $pdo->prepare('SELECT id, slug, dominio FROM escolas WHERE id = :id LIMIT 1');
        $st->execute(['id' => $escolaId]);
        $escola = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        require_once __DIR__ . '/../Core/RedisCache.php';
        if ($escolaId > 0) {
            RedisCache::delete('tenant_config_' . $escolaId);
        }
        $slug = strtolower(trim((string) ($escola['slug'] ?? '')));
        $dominio = strtolower(trim((string) ($escola['dominio'] ?? '')));
        if ($dominio !== '' && strpos($dominio, ':') !== false) {
            $dominio = explode(':', $dominio, 2)[0];
        }
        if ($dominio !== '') {
            RedisCache::delete('tenant_' . $dominio);
            if ($slug !== '') {
                RedisCache::delete('tenant_' . $dominio . '_' . $slug);
            }
        }
        require_once __DIR__ . '/../Core/LayoutHelper.php';
        LayoutHelper::invalidateCacheDaEscola($escolaId);
    }

    private static function tabelaExiste(PDO $pdo, string $tabela): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1'
            );
            $st->execute(['t' => $tabela]);
            return $st->fetchColumn() !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function quoteIdent(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private static function validarIdentificador(string $value, string $label): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            throw new RuntimeException('Identificador inválido (' . $label . ').');
        }
    }

    private static function tmpDir(int $jobId): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dir = $base . '/storage/tmp/clonar-escola-' . max(0, $jobId) . '-' . bin2hex(random_bytes(4));
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar pasta temporária para o dump.');
        }
        return $dir;
    }

    private static function limparDir(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $files = glob($dir . '/*') ?: [];
        foreach ($files as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        @rmdir($dir);
    }

    private static function findBin(string $name): ?string
    {
        $paths = [
            $name,
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/opt/mysql/bin/' . $name,
        ];
        foreach ($paths as $path) {
            if (@is_executable($path)) {
                return $path;
            }
        }
        $cmd = (stripos(PHP_OS, 'WIN') === 0) ? 'where ' . $name : 'which ' . $name;
        exec($cmd, $out, $code);
        if ($code === 0 && !empty($out[0]) && @is_executable(trim($out[0]))) {
            return trim($out[0]);
        }
        return null;
    }
}
