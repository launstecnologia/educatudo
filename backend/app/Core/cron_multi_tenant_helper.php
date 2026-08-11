<?php
/**
 * EducaTudo - Helper para crons em modo multi-tenant
 * Quando MULTI_TENANT=true: executa o callback para cada escola ativa (conexão do tenant já registrada).
 * Quando MULTI_TENANT=false: executa o callback uma vez (Database::getInstance() usa .env como hoje).
 *
 * Uso no cron:
 *   require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';
 *   CronMultiTenantHelper::run(function (?int $escolaId) {
 *       $db = Database::getInstance();
 *       // ... lógica do cron ...
 *   });
 */

// Garante mb_* mesmo quando o PHP CLI do servidor não tem a extensão mbstring
// (comum em workers/crons cujo binário difere do PHP-FPM). Evita fatais
// "Call to undefined function mb_*()" ao processar jobs de IA.
require_once __DIR__ . '/../Helpers/mbstring_polyfill.php';

class CronMultiTenantHelper
{
    /**
     * Executa $callback uma vez (single-tenant) ou uma vez por escola ativa (multi-tenant).
     * Em multi-tenant, $escolaId é passado para o callback; em single-tenant é null.
     *
     * @param callable $callback function(?int $escolaId): void|int|array
     * @param bool $forceIterateTenants Se true, sempre itera os bancos das escolas (útil para crons no servidor master com MULTI_TENANT=false)
     */
    public static function run(callable $callback, bool $forceIterateTenants = false): void
    {
        self::runWithReport($callback, $forceIterateTenants);
    }

    /**
     * Igual a run(), mas devolve relatório por escola (para gravar em cron_execucoes).
     *
     * Callback pode retornar:
     * - int: quantidade de jobs/itens processados
     * - array{jobs_processados?:int, mensagem?:string}
     * - void
     *
     * @param callable $callback function(?int $escolaId): mixed
     * @return array{
     *   escolas_total:int,
     *   escolas_ok:int,
     *   escolas_erro:int,
     *   escolas_puladas:int,
     *   jobs_processados:int,
     *   detalhes: list<array{
     *     escola_id: int|null,
     *     escola_nome: string|null,
     *     status: 'ok'|'erro'|'pulada',
     *     jobs_processados: int,
     *     mensagem_erro: string|null,
     *     iniciado_em: string,
     *     finalizado_em: string
     *   }>
     * }
     */
    public static function runWithReport(callable $callback, bool $forceIterateTenants = false): array
    {
        require_once __DIR__ . '/Database.php';

        $report = [
            'escolas_total' => 0,
            'escolas_ok' => 0,
            'escolas_erro' => 0,
            'escolas_puladas' => 0,
            'jobs_processados' => 0,
            'detalhes' => [],
        ];

        $multiTenant = self::isMultiTenantEnabled();
        if (!$multiTenant && !$forceIterateTenants) {
            // Comportamento legado: reset da conexão + exceção sobe (não engolir).
            Database::setCurrentInstance(null);
            $detalhe = self::executarCallbackLegado($callback, null, null);
            $report['escolas_total'] = 1;
            self::acumularDetalhe($report, $detalhe);
            return $report;
        }

        require_once __DIR__ . '/TenantResolver.php';
        require_once __DIR__ . '/DatabaseManager.php';

        $masterConfig = Database::getConfigFromEnv();
        $masterPdo = self::createMasterPdo($masterConfig);
        $resolver = new TenantResolver($masterPdo);
        $manager = new DatabaseManager($masterPdo);
        $escolasIds = $resolver->listarEscolasAtivas();
        $nomesEscolas = self::mapearNomesEscolas($masterPdo, $escolasIds);

        if (empty($escolasIds)) {
            if (!$forceIterateTenants) {
                return $report;
            }
            // forceIterateTenants mas nenhuma escola ativa: roda uma vez com conexão padrão (comportamento legado)
            Database::setCurrentInstance(null);
            $detalhe = self::executarCallbackLegado($callback, null, null);
            $report['escolas_total'] = 1;
            self::acumularDetalhe($report, $detalhe);
            return $report;
        }

        $masterDbName = trim($masterConfig['name'] ?? '');
        foreach ($escolasIds as $escolaId) {
            $report['escolas_total']++;
            $escolaNome = $nomesEscolas[(int) $escolaId] ?? null;
            try {
                // Evitar rodar lógica de tenant contra o banco master (escola com nome_banco = master)
                if ($masterDbName !== '') {
                    $stmt = $masterPdo->prepare(
                        "SELECT nome_banco FROM config_escolas_banco WHERE escola_id = :escola_id LIMIT 1"
                    );
                    $stmt->execute(['escola_id' => $escolaId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && trim($row['nome_banco'] ?? '') === $masterDbName) {
                        error_log("Cron multi-tenant: pulando escola_id={$escolaId} (banco aponta para o master; configure um banco próprio em config_escolas_banco).");
                        $detalhe = [
                            'escola_id' => (int) $escolaId,
                            'escola_nome' => $escolaNome,
                            'status' => 'pulada',
                            'jobs_processados' => 0,
                            'mensagem_erro' => 'Banco da escola aponta para o master; configure um banco próprio.',
                            'iniciado_em' => date('Y-m-d H:i:s'),
                            'finalizado_em' => date('Y-m-d H:i:s'),
                        ];
                        self::acumularDetalhe($report, $detalhe);
                        continue;
                    }
                }
                $tenantDb = $manager->getConnectionForTenant($escolaId);
                Database::setCurrentInstance($tenantDb);
                $detalhe = self::executarCallbackCapturando($callback, (int) $escolaId, $escolaNome);
                self::acumularDetalhe($report, $detalhe);
            } catch (Throwable $e) {
                error_log("Cron multi-tenant: erro para escola_id={$escolaId}: " . $e->getMessage());
                $detalhe = [
                    'escola_id' => (int) $escolaId,
                    'escola_nome' => $escolaNome,
                    'status' => 'erro',
                    'jobs_processados' => 0,
                    'mensagem_erro' => $e->getMessage(),
                    'iniciado_em' => date('Y-m-d H:i:s'),
                    'finalizado_em' => date('Y-m-d H:i:s'),
                ];
                self::acumularDetalhe($report, $detalhe);
            } finally {
                Database::setCurrentInstance(null);
            }
        }

        return $report;
    }

    /**
     * @param array<string,mixed> $report
     * @param array<string,mixed> $detalhe
     */
    private static function acumularDetalhe(array &$report, array $detalhe): void
    {
        $report['detalhes'][] = $detalhe;
        $status = (string) ($detalhe['status'] ?? 'erro');
        if ($status === 'ok') {
            $report['escolas_ok']++;
        } elseif ($status === 'pulada') {
            $report['escolas_puladas']++;
        } else {
            $report['escolas_erro']++;
        }
        $report['jobs_processados'] += (int) ($detalhe['jobs_processados'] ?? 0);
    }

    /**
     * @param list<int> $escolasIds
     * @return array<int, string>
     */
    private static function mapearNomesEscolas(PDO $masterPdo, array $escolasIds): array
    {
        if ($escolasIds === []) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $escolasIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $masterPdo->prepare("SELECT id, nome FROM escolas WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['id']] = (string) ($row['nome'] ?? '');
            }
            return $map;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Caminho single-tenant / fallback: exceção sobe (compatível com crons legados).
     *
     * @return array{
     *   escola_id: int|null,
     *   escola_nome: string|null,
     *   status: 'ok'|'erro'|'pulada',
     *   jobs_processados: int,
     *   mensagem_erro: string|null,
     *   iniciado_em: string,
     *   finalizado_em: string
     * }
     */
    private static function executarCallbackLegado(callable $callback, ?int $escolaId, ?string $escolaNome): array
    {
        $iniciado = date('Y-m-d H:i:s');
        $ret = $callback($escolaId);
        return self::montarDetalheOk($escolaId, $escolaNome, $ret, $iniciado);
    }

    /**
     * Caminho multi-tenant: captura erro da escola e segue para a próxima.
     *
     * @return array{
     *   escola_id: int|null,
     *   escola_nome: string|null,
     *   status: 'ok'|'erro'|'pulada',
     *   jobs_processados: int,
     *   mensagem_erro: string|null,
     *   iniciado_em: string,
     *   finalizado_em: string
     * }
     */
    private static function executarCallbackCapturando(callable $callback, ?int $escolaId, ?string $escolaNome): array
    {
        $iniciado = date('Y-m-d H:i:s');
        try {
            $ret = $callback($escolaId);
            return self::montarDetalheOk($escolaId, $escolaNome, $ret, $iniciado);
        } catch (Throwable $e) {
            error_log('Cron multi-tenant: erro no callback escola_id=' . ($escolaId ?? 'null') . ': ' . $e->getMessage());
            return [
                'escola_id' => $escolaId,
                'escola_nome' => $escolaNome,
                'status' => 'erro',
                'jobs_processados' => 0,
                'mensagem_erro' => $e->getMessage(),
                'iniciado_em' => $iniciado,
                'finalizado_em' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * @return array{
     *   escola_id: int|null,
     *   escola_nome: string|null,
     *   status: 'ok'|'erro'|'pulada',
     *   jobs_processados: int,
     *   mensagem_erro: string|null,
     *   iniciado_em: string,
     *   finalizado_em: string
     * }
     */
    private static function montarDetalheOk(?int $escolaId, ?string $escolaNome, mixed $ret, string $iniciado): array
    {
        $jobs = 0;
        $mensagem = null;
        if (is_int($ret) || is_float($ret)) {
            $jobs = max(0, (int) $ret);
        } elseif (is_array($ret)) {
            $jobs = max(0, (int) ($ret['jobs_processados'] ?? 0));
            $mensagem = isset($ret['mensagem']) ? (string) $ret['mensagem'] : null;
        }
        return [
            'escola_id' => $escolaId,
            'escola_nome' => $escolaNome,
            'status' => 'ok',
            'jobs_processados' => $jobs,
            'mensagem_erro' => $mensagem,
            'iniciado_em' => $iniciado,
            'finalizado_em' => date('Y-m-d H:i:s'),
        ];
    }

    private static function isMultiTenantEnabled(): bool
    {
        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../.env');
        $paths = [$envPath, __DIR__ . '/../../.env', __DIR__ . '/../../../.env'];
        foreach ($paths as $p) {
            $resolved = is_file($p) ? $p : (realpath($p) ?: $p);
            if (!is_file($resolved)) {
                continue;
            }
            $lines = @file($resolved, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, 'MULTI_TENANT=') === 0) {
                    $v = trim(substr($line, 12));
                    if (strlen($v) >= 2 && $v[0] === '"' && substr($v, -1) === '"') {
                        $v = substr($v, 1, -1);
                    }
                    return strtolower($v) === 'true';
                }
            }
            break;
        }
        return false;
    }

    private static function createMasterPdo(array $config): PDO
    {
        $host = $config['host'] ?? 'localhost';
        $port = (int) ($config['port'] ?? 3306);
        $name = $config['name'] ?? '';
        $user = $config['user'] ?? '';
        $pass = $config['pass'] ?? '';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET time_zone = '-03:00'");
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    }
}
