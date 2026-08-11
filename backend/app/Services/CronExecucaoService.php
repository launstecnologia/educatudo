<?php
/**
 * Persiste histórico de execuções de cron no banco master.
 */
namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';

class CronExecucaoService
{
    public const SCRIPT_PROCESS_AI_JOBS = 'process_ai_jobs';

    private const RETENCAO_DIAS = 30;

    /** @var \PDO|null */
    private static $masterPdo = null;

    /** @var bool|null */
    private static $tabelaDisponivel = null;

    /**
     * Abre (ou reutiliza) PDO do banco master a partir do .env.
     */
    public static function masterPdo(): ?\PDO
    {
        if (self::$masterPdo instanceof \PDO) {
            return self::$masterPdo;
        }

        $global = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($global instanceof \PDO) {
            self::$masterPdo = $global;
            return self::$masterPdo;
        }

        try {
            $cfg = \Database::getConfigFromEnv();
            $host = (string) ($cfg['host'] ?? '127.0.0.1');
            $port = (int) ($cfg['port'] ?? 3306);
            $name = trim((string) ($cfg['name'] ?? ''));
            $user = (string) ($cfg['user'] ?? '');
            $pass = (string) ($cfg['pass'] ?? '');
            if ($name === '' || $user === '') {
                return null;
            }
            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
            $pdo->exec("SET time_zone = '-03:00'");
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            self::$masterPdo = $pdo;
            return self::$masterPdo;
        } catch (\Throwable $e) {
            error_log('[CronExecucaoService] falha ao conectar master: ' . $e->getMessage());
            return null;
        }
    }

    public static function tabelasDisponiveis(?\PDO $pdo = null): bool
    {
        if (self::$tabelaDisponivel !== null) {
            return self::$tabelaDisponivel;
        }
        $pdo = $pdo ?? self::masterPdo();
        if (!$pdo) {
            self::$tabelaDisponivel = false;
            return false;
        }
        try {
            $pdo->query('SELECT 1 FROM cron_execucoes LIMIT 1');
            self::$tabelaDisponivel = true;
        } catch (\Throwable $e) {
            self::$tabelaDisponivel = false;
        }
        return self::$tabelaDisponivel;
    }

    /**
     * Inicia registro de uma execução. Retorna 0 se tabelas/migration ainda não existirem.
     */
    public static function iniciar(string $script): int
    {
        $pdo = self::masterPdo();
        if (!$pdo || !self::tabelasDisponiveis($pdo)) {
            return 0;
        }
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO cron_execucoes (script, status, iniciado_em, hostname)
                 VALUES (:script, 'rodando', NOW(), :hostname)"
            );
            $stmt->execute([
                'script' => substr($script, 0, 64),
                'hostname' => substr((string) gethostname(), 0, 128) ?: null,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('[CronExecucaoService] iniciar: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @param array{
     *   escola_id?: int|null,
     *   escola_nome?: string|null,
     *   status: 'ok'|'erro'|'pulada',
     *   jobs_processados?: int,
     *   mensagem_erro?: string|null,
     *   iniciado_em?: string|null,
     *   finalizado_em?: string|null
     * } $detalhe
     */
    public static function registrarEscola(int $execucaoId, array $detalhe): void
    {
        if ($execucaoId < 1) {
            return;
        }
        $pdo = self::masterPdo();
        if (!$pdo || !self::tabelasDisponiveis($pdo)) {
            return;
        }
        $status = (string) ($detalhe['status'] ?? 'ok');
        if (!in_array($status, ['ok', 'erro', 'pulada'], true)) {
            $status = 'erro';
        }
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO cron_execucoes_escolas
                    (cron_execucao_id, escola_id, escola_nome, status, jobs_processados, mensagem_erro, iniciado_em, finalizado_em)
                 VALUES
                    (:cron_execucao_id, :escola_id, :escola_nome, :status, :jobs_processados, :mensagem_erro, :iniciado_em, :finalizado_em)"
            );
            $escolaId = isset($detalhe['escola_id']) ? (int) $detalhe['escola_id'] : null;
            if ($escolaId !== null && $escolaId < 1) {
                $escolaId = null;
            }
            $stmt->execute([
                'cron_execucao_id' => $execucaoId,
                'escola_id' => $escolaId,
                'escola_nome' => self::truncar((string) ($detalhe['escola_nome'] ?? ''), 255) ?: null,
                'status' => $status,
                'jobs_processados' => max(0, (int) ($detalhe['jobs_processados'] ?? 0)),
                'mensagem_erro' => self::truncar((string) ($detalhe['mensagem_erro'] ?? ''), 4000) ?: null,
                'iniciado_em' => $detalhe['iniciado_em'] ?? null,
                'finalizado_em' => $detalhe['finalizado_em'] ?? date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[CronExecucaoService] registrarEscola: ' . $e->getMessage());
        }
    }

    /**
     * @param array{
     *   escolas_total?: int,
     *   escolas_ok?: int,
     *   escolas_erro?: int,
     *   escolas_puladas?: int,
     *   jobs_processados?: int,
     *   mensagem_erro?: string|null
     * } $totais
     */
    public static function finalizar(int $execucaoId, array $totais = []): void
    {
        if ($execucaoId < 1) {
            return;
        }
        $pdo = self::masterPdo();
        if (!$pdo || !self::tabelasDisponiveis($pdo)) {
            return;
        }

        $escolasOk = max(0, (int) ($totais['escolas_ok'] ?? 0));
        $escolasErro = max(0, (int) ($totais['escolas_erro'] ?? 0));
        $escolasPuladas = max(0, (int) ($totais['escolas_puladas'] ?? 0));
        $escolasTotal = max(0, (int) ($totais['escolas_total'] ?? ($escolasOk + $escolasErro + $escolasPuladas)));
        $jobs = max(0, (int) ($totais['jobs_processados'] ?? 0));

        if ($escolasErro > 0 && $escolasOk > 0) {
            $status = 'parcial';
        } elseif ($escolasErro > 0 && $escolasOk === 0) {
            $status = 'erro';
        } else {
            $status = 'ok';
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE cron_execucoes
                 SET status = :status,
                     finalizado_em = NOW(),
                     duracao_ms = GREATEST(0, TIMESTAMPDIFF(MICROSECOND, iniciado_em, NOW()) DIV 1000),
                     escolas_total = :escolas_total,
                     escolas_ok = :escolas_ok,
                     escolas_erro = :escolas_erro,
                     escolas_puladas = :escolas_puladas,
                     jobs_processados = :jobs_processados,
                     mensagem_erro = :mensagem_erro
                 WHERE id = :id"
            );
            $stmt->execute([
                'status' => $status,
                'escolas_total' => $escolasTotal,
                'escolas_ok' => $escolasOk,
                'escolas_erro' => $escolasErro,
                'escolas_puladas' => $escolasPuladas,
                'jobs_processados' => $jobs,
                'mensagem_erro' => self::truncar((string) ($totais['mensagem_erro'] ?? ''), 4000) ?: null,
                'id' => $execucaoId,
            ]);
        } catch (\Throwable $e) {
            error_log('[CronExecucaoService] finalizar: ' . $e->getMessage());
        }

        self::limparAntigas($pdo);
    }

    /**
     * Marca execução como erro fatal (ex.: falha antes de iterar escolas).
     */
    public static function falhar(int $execucaoId, string $mensagem): void
    {
        if ($execucaoId < 1) {
            return;
        }
        $pdo = self::masterPdo();
        if (!$pdo || !self::tabelasDisponiveis($pdo)) {
            return;
        }
        try {
            $stmt = $pdo->prepare(
                "UPDATE cron_execucoes
                 SET status = 'erro',
                     finalizado_em = NOW(),
                     duracao_ms = GREATEST(0, TIMESTAMPDIFF(MICROSECOND, iniciado_em, NOW()) DIV 1000),
                     mensagem_erro = :mensagem_erro
                 WHERE id = :id AND status = 'rodando'"
            );
            $stmt->execute([
                'mensagem_erro' => self::truncar($mensagem, 4000),
                'id' => $execucaoId,
            ]);
        } catch (\Throwable $e) {
            error_log('[CronExecucaoService] falhar: ' . $e->getMessage());
        }
    }

    private static function limparAntigas(\PDO $pdo): void
    {
        try {
            // Literal int: MySQL não aceita placeholder em INTERVAL de forma portátil.
            $dias = (int) self::RETENCAO_DIAS;
            $pdo->exec(
                "DELETE FROM cron_execucoes WHERE iniciado_em < (NOW() - INTERVAL {$dias} DAY)"
            );
        } catch (\Throwable $e) {
            // cleanup não deve derrubar o cron
        }
    }

    private static function truncar(string $valor, int $max): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $max);
        }
        return substr($valor, 0, $max);
    }
}
