<?php
/**
 * Fila de jobs longos no banco master (clonar escola, etc.).
 * Não usa ai_jobs dos tenants — o trabalho acontece antes do tenant existir.
 *
 * Enfileira na request HTTP e processa via cron/process_master_jobs.php
 * (ou worker CLI disparado em background).
 */

require_once __DIR__ . '/../Core/Database.php';

class MasterFilaService
{
    public const TIPO_CLONAR_ESCOLA = 'clonar_escola';
    public const MAX_TENTATIVAS = 2;
    public const STALE_MINUTOS = 120;

    public static function tabelaExiste(?PDO $pdo = null): bool
    {
        $pdo = $pdo ?? self::masterPdo();
        if (!$pdo instanceof PDO) {
            return false;
        }
        try {
            $st = $pdo->query("SHOW TABLES LIKE 'fila_jobs_master'");
            return $st !== false && $st->fetchColumn() !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function masterPdo(): ?PDO
    {
        $global = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($global instanceof PDO) {
            return $global;
        }
        try {
            $pdo = Database::createMasterPdo();
            $GLOBALS['_educatudo_master_pdo'] = $pdo;
            return $pdo;
        } catch (Throwable $e) {
            error_log('[MasterFilaService] falha ao conectar master: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function enfileirar(
        string $tipo,
        array $payload,
        ?int $escolaOrigemId = null,
        ?int $escolaDestinoId = null,
        ?int $criadoPor = null
    ): int {
        $pdo = self::masterPdo();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Não foi possível conectar ao banco master.');
        }
        if (!self::tabelaExiste($pdo)) {
            throw new RuntimeException('Fila do Master não configurada. Rode a migration 2026_09_16_fila_jobs_master.sql no banco master.');
        }

        $st = $pdo->prepare(
            "INSERT INTO fila_jobs_master
                (tipo, status, payload, escola_origem_id, escola_destino_id, criado_por)
             VALUES
                (:tipo, 'pending', :payload, :escola_origem_id, :escola_destino_id, :criado_por)"
        );
        $st->execute([
            'tipo' => $tipo,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'escola_origem_id' => $escolaOrigemId,
            'escola_destino_id' => $escolaDestinoId,
            'criado_por' => $criadoPor,
        ]);

        $jobId = (int) $pdo->lastInsertId();
        self::tryProcessImmediately();
        return $jobId;
    }

    /**
     * @param array<string,mixed> $resultado
     */
    public static function atualizarProgresso(int $jobId, string $mensagem, int $percentual = 0, array $extra = []): void
    {
        $pdo = self::masterPdo();
        if (!$pdo instanceof PDO || $jobId < 1) {
            return;
        }
        $body = array_merge($extra, [
            'mensagem' => $mensagem,
            'percentual' => max(0, min(100, $percentual)),
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);
        try {
            $st = $pdo->prepare(
                "UPDATE fila_jobs_master
                    SET resultado = :resultado, started_at = NOW()
                  WHERE id = :id AND status = 'processing'"
            );
            $st->execute([
                'resultado' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $jobId,
            ]);
        } catch (Throwable $e) {
            error_log('[MasterFilaService] progresso job=' . $jobId . ' ' . $e->getMessage());
        }
    }

    public static function marcarDestino(int $jobId, int $escolaDestinoId): void
    {
        $pdo = self::masterPdo();
        if (!$pdo instanceof PDO || $jobId < 1 || $escolaDestinoId < 1) {
            return;
        }
        $st = $pdo->prepare('UPDATE fila_jobs_master SET escola_destino_id = :dest WHERE id = :id');
        $st->execute(['dest' => $escolaDestinoId, 'id' => $jobId]);
    }

    /**
     * Processa o próximo job pending. Retorna true se processou algum.
     */
    public static function processNext(): bool
    {
        $pdo = self::masterPdo();
        if (!$pdo instanceof PDO || !self::tabelaExiste($pdo)) {
            return false;
        }

        self::liberarJobsTravados($pdo);

        $pdo->beginTransaction();
        try {
            $st = $pdo->query(
                "SELECT * FROM fila_jobs_master
                  WHERE status = 'pending'
                  ORDER BY id ASC
                  LIMIT 1
                  FOR UPDATE"
            );
            $job = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
            if (!$job) {
                $pdo->commit();
                return false;
            }

            $up = $pdo->prepare(
                "UPDATE fila_jobs_master
                    SET status = 'processing', started_at = NOW(), tentativas = tentativas + 1
                  WHERE id = :id"
            );
            $up->execute(['id' => (int) $job['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[MasterFilaService] processNext lock: ' . $e->getMessage());
            return false;
        }

        $jobId = (int) $job['id'];
        $tipo = (string) ($job['tipo'] ?? '');
        $payload = json_decode((string) ($job['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $payload['_job_id'] = $jobId;

        try {
            $resultado = self::dispatch($tipo, $payload);
            $done = $pdo->prepare(
                "UPDATE fila_jobs_master
                    SET status = 'done',
                        resultado = :resultado,
                        mensagem_erro = NULL,
                        completed_at = NOW()
                  WHERE id = :id"
            );
            $done->execute([
                'resultado' => json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $jobId,
            ]);
            self::redigirPayload($pdo, $jobId);
        } catch (Throwable $e) {
            $tentativas = (int) ($job['tentativas'] ?? 0) + 1;
            $final = $tentativas >= self::MAX_TENTATIVAS;
            $fail = $pdo->prepare(
                "UPDATE fila_jobs_master
                    SET status = :status,
                        mensagem_erro = :erro,
                        completed_at = IF(:final = 1, NOW(), completed_at)
                  WHERE id = :id"
            );
            $fail->execute([
                'status' => $final ? 'failed' : 'pending',
                'erro' => substr($e->getMessage(), 0, 4000),
                'final' => $final ? 1 : 0,
                'id' => $jobId,
            ]);
            if ($final) {
                self::redigirPayload($pdo, $jobId);
            }
            error_log('[MasterFilaService] job=' . $jobId . ' tipo=' . $tipo . ' ' . $e->getMessage());
        }

        return true;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function listarRecentes(string $tipo = '', int $limite = 20): array
    {
        $pdo = self::masterPdo();
        if (!$pdo instanceof PDO || !self::tabelaExiste($pdo)) {
            return [];
        }
        $limite = max(1, min(50, $limite));
        $sql = "SELECT j.id, j.tipo, j.status, j.resultado, j.mensagem_erro, j.tentativas,
                       j.escola_origem_id, j.escola_destino_id, j.criado_por,
                       j.created_at, j.started_at, j.completed_at,
                       o.nome AS origem_nome, d.nome AS destino_nome
                  FROM fila_jobs_master j
                  LEFT JOIN escolas o ON o.id = j.escola_origem_id
                  LEFT JOIN escolas d ON d.id = j.escola_destino_id";
        if ($tipo !== '') {
            $st = $pdo->prepare($sql . " WHERE j.tipo = :tipo ORDER BY j.id DESC LIMIT {$limite}");
            $st->execute(['tipo' => $tipo]);
        } else {
            $st = $pdo->query($sql . " ORDER BY j.id DESC LIMIT {$limite}");
        }
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as &$row) {
            $row['resultado_decoded'] = json_decode((string) ($row['resultado'] ?? ''), true) ?: [];
        }
        unset($row);
        return $rows;
    }

    public static function temPendentes(string $tipo = ''): bool
    {
        $pdo = self::masterPdo();
        if (!$pdo instanceof PDO || !self::tabelaExiste($pdo)) {
            return false;
        }
        if ($tipo !== '') {
            $st = $pdo->prepare(
                "SELECT 1 FROM fila_jobs_master
                  WHERE tipo = :tipo AND status IN ('pending','processing')
                  LIMIT 1"
            );
            $st->execute(['tipo' => $tipo]);
        } else {
            $st = $pdo->query(
                "SELECT 1 FROM fila_jobs_master
                  WHERE status IN ('pending','processing')
                  LIMIT 1"
            );
        }
        return $st && $st->fetchColumn() !== false;
    }

    public static function tryProcessImmediately(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (self::spawnBackgroundWorker()) {
            return;
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
    }

    private static function spawnBackgroundWorker(): bool
    {
        try {
            if (!function_exists('exec')) {
                return false;
            }
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (in_array('exec', $disabled, true)) {
                return false;
            }
            if (stripos(PHP_OS, 'WIN') === 0) {
                return false;
            }
            $script = realpath(__DIR__ . '/../../cron/process_master_jobs.php');
            if ($script === false) {
                return false;
            }
            $php = self::findPhpCliBinary();
            if ($php === null) {
                return false;
            }
            $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' > /dev/null 2>&1 &';
            exec($cmd);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function findPhpCliBinary(): ?string
    {
        $candidates = [];
        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            if (stripos(PHP_BINARY, 'fpm') === false) {
                $candidates[] = PHP_BINARY;
            } else {
                $guess = str_replace(
                    ['/sbin/php-fpm', '/bin/php-fpm', 'php-fpm'],
                    ['/bin/php', '/bin/php', 'php'],
                    PHP_BINARY
                );
                if ($guess !== PHP_BINARY) {
                    $candidates[] = $guess;
                }
            }
        }
        if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
            $candidates[] = PHP_BINDIR . '/php';
        }
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';
        foreach ($candidates as $candidate) {
            if ($candidate && @is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private static function liberarJobsTravados(PDO $pdo): void
    {
        $min = (int) self::STALE_MINUTOS;
        $max = (int) self::MAX_TENTATIVAS;
        $pdo->exec(
            "UPDATE fila_jobs_master
                SET status = IF(tentativas >= {$max}, 'failed', 'pending'),
                    mensagem_erro = IF(tentativas >= {$max}, 'Job travado (timeout).', mensagem_erro)
              WHERE status = 'processing'
                AND started_at < NOW() - INTERVAL {$min} MINUTE"
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function dispatch(string $tipo, array $payload): array
    {
        if ($tipo === self::TIPO_CLONAR_ESCOLA) {
            require_once __DIR__ . '/ClonarEscolaService.php';
            return ClonarEscolaService::executar($payload);
        }
        throw new RuntimeException('Tipo de job desconhecido: ' . $tipo);
    }

    private static function redigirPayload(PDO $pdo, int $jobId): void
    {
        try {
            $st = $pdo->prepare('SELECT payload FROM fila_jobs_master WHERE id = :id LIMIT 1');
            $st->execute(['id' => $jobId]);
            $raw = $st->fetchColumn();
            $payload = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($payload)) {
                return;
            }
            unset($payload['db_senha_criptografada'], $payload['db_senha']);
            $up = $pdo->prepare('UPDATE fila_jobs_master SET payload = :payload WHERE id = :id');
            $up->execute([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $jobId,
            ]);
        } catch (Throwable $e) {
            error_log('[MasterFilaService] redigirPayload job=' . $jobId . ' ' . $e->getMessage());
        }
    }
}
