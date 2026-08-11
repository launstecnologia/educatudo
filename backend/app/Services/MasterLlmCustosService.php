<?php
/**
 * Agrega uso de LLM (tokens + custo USD) de todos os tenants para o painel Master.
 */
namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/MasterTenantConnection.php';

class MasterLlmCustosService
{
    /**
     * Preços de referência (USD por 1M tokens). Usados para estimativa atualizada na UI.
     * Fonte: documentação OpenAI / feeds públicos — revisar periodicamente.
     *
     * @return array<string, array{input: float, output: float}>
     */
    public static function precosReferenciaPor1M(): array
    {
        $path = dirname(__DIR__, 2) . '/config/llm_precos.php';
        if (is_file($path)) {
            $cfg = require $path;
            if (is_array($cfg) && !empty($cfg['modelos']) && is_array($cfg['modelos'])) {
                return $cfg['modelos'];
            }
        }
        return [
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
            'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
            'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
            'o3' => ['input' => 2.00, 'output' => 8.00],
            'o4-mini' => ['input' => 1.10, 'output' => 4.40],
        ];
    }

    public static function estimarCustoUsd(string $model, int $promptTokens, int $completionTokens): float
    {
        $precos = self::precosReferenciaPor1M();
        $key = self::normalizarModelo($model, array_keys($precos));
        $p = $precos[$key] ?? $precos['gpt-4o-mini'] ?? ['input' => 0.15, 'output' => 0.60];
        $cost = ($promptTokens / 1_000_000) * (float) $p['input']
            + ($completionTokens / 1_000_000) * (float) $p['output'];
        return round($cost, 6);
    }

    /**
     * @param list<string> $known
     */
    private static function normalizarModelo(string $model, array $known): string
    {
        $m = strtolower(trim($model));
        if ($m === '' || $m === 'unknown') {
            return 'gpt-4o-mini';
        }
        if (in_array($m, $known, true)) {
            return $m;
        }
        // Match prefixo (ex.: gpt-4o-mini-2024-07-18)
        usort($known, static fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($known as $k) {
            if (str_starts_with($m, $k)) {
                return $k;
            }
        }
        return 'gpt-4o-mini';
    }

    /**
     * @return array{
     *   date_start: string,
     *   date_end: string,
     *   escola_id: int,
     *   totais: array{requests:int,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost_usd:float,cost_estimado:float},
     *   por_escola: list<array>,
     *   por_modelo: list<array>,
     *   por_tipo: list<array>,
     *   por_dia: list<array>,
     *   precos: array<string, array{input:float,output:float}>,
     *   erros: list<string>
     * }
     */
    public static function agregar(string $dateStart, string $dateEnd, int $escolaIdFiltro = 0): array
    {
        $emptyTotais = [
            'requests' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost_usd' => 0.0,
            'cost_estimado' => 0.0,
        ];
        $out = [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'escola_id' => $escolaIdFiltro,
            'totais' => $emptyTotais,
            'por_escola' => [],
            'por_modelo' => [],
            'por_tipo' => [],
            'por_dia' => [],
            'precos' => self::precosReferenciaPor1M(),
            'erros' => [],
        ];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
            $out['erros'][] = 'Datas inválidas.';
            return $out;
        }

        $db = \Database::getInstance();
        $escolas = $db->query(
            "SELECT e.id, e.nome, e.slug
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $start = $dateStart . ' 00:00:00';
        $end = $dateEnd . ' 23:59:59';

        $aggModelo = [];
        $aggTipo = [];
        $aggDia = [];
        $porEscola = [];

        foreach ($escolas as $escola) {
            $eid = (int) ($escola['id'] ?? 0);
            if ($escolaIdFiltro > 0 && $eid !== $escolaIdFiltro) {
                continue;
            }
            $conn = \MasterTenantConnection::getPdoAndEscola($eid);
            if ($conn === null || empty($conn['pdo'])) {
                $out['erros'][] = 'Sem conexão: ' . ($escola['nome'] ?? $eid);
                continue;
            }
            /** @var \PDO $pdo */
            $pdo = $conn['pdo'];
            try {
                $pdo->query('SELECT 1 FROM logs_uso_llm LIMIT 1');
            } catch (\Throwable $e) {
                continue;
            }

            try {
                $st = $pdo->prepare(
                    "SELECT
                        COUNT(*) AS requests,
                        COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
                        COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
                        COALESCE(SUM(total_tokens), 0) AS total_tokens,
                        COALESCE(SUM(cost_usd), 0) AS cost_usd
                     FROM logs_uso_llm
                     WHERE created_at >= ? AND created_at <= ?"
                );
                $st->execute([$start, $end]);
                $tot = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

                $stM = $pdo->prepare(
                    "SELECT model,
                            COUNT(*) AS requests,
                            COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
                            COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
                            COALESCE(SUM(total_tokens), 0) AS total_tokens,
                            COALESCE(SUM(cost_usd), 0) AS cost_usd
                     FROM logs_uso_llm
                     WHERE created_at >= ? AND created_at <= ?
                     GROUP BY model"
                );
                $stM->execute([$start, $end]);
                $models = $stM->fetchAll(\PDO::FETCH_ASSOC);

                $stT = $pdo->prepare(
                    "SELECT usage_type,
                            COUNT(*) AS requests,
                            COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
                            COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
                            COALESCE(SUM(total_tokens), 0) AS total_tokens,
                            COALESCE(SUM(cost_usd), 0) AS cost_usd
                     FROM logs_uso_llm
                     WHERE created_at >= ? AND created_at <= ?
                     GROUP BY usage_type"
                );
                $stT->execute([$start, $end]);
                $types = $stT->fetchAll(\PDO::FETCH_ASSOC);

                $stD = $pdo->prepare(
                    "SELECT DATE(created_at) AS dt,
                            COUNT(*) AS requests,
                            COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
                            COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
                            COALESCE(SUM(total_tokens), 0) AS total_tokens,
                            COALESCE(SUM(cost_usd), 0) AS cost_usd
                     FROM logs_uso_llm
                     WHERE created_at >= ? AND created_at <= ?
                     GROUP BY DATE(created_at)"
                );
                $stD->execute([$start, $end]);
                $days = $stD->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('[MasterLlmCustos] escola=' . $eid . ' ' . $e->getMessage());
                $out['erros'][] = 'Falha ao ler uso LLM: ' . ($escola['nome'] ?? (string) $eid);
                continue;
            }

            $req = (int) ($tot['requests'] ?? 0);
            $pt = (int) ($tot['prompt_tokens'] ?? 0);
            $ct = (int) ($tot['completion_tokens'] ?? 0);
            $tt = (int) ($tot['total_tokens'] ?? 0);
            $cost = (float) ($tot['cost_usd'] ?? 0);
            $est = 0.0;
            foreach ($models as $m) {
                $est += self::estimarCustoUsd(
                    (string) ($m['model'] ?? ''),
                    (int) ($m['prompt_tokens'] ?? 0),
                    (int) ($m['completion_tokens'] ?? 0)
                );
            }

            if ($req > 0 || $tt > 0) {
                $porEscola[] = [
                    'escola_id' => $eid,
                    'nome' => (string) ($escola['nome'] ?? ''),
                    'slug' => (string) ($escola['slug'] ?? ''),
                    'requests' => $req,
                    'prompt_tokens' => $pt,
                    'completion_tokens' => $ct,
                    'total_tokens' => $tt,
                    'cost_usd' => round($cost, 6),
                    'cost_estimado' => round($est, 6),
                ];
            }

            $out['totais']['requests'] += $req;
            $out['totais']['prompt_tokens'] += $pt;
            $out['totais']['completion_tokens'] += $ct;
            $out['totais']['total_tokens'] += $tt;
            $out['totais']['cost_usd'] += $cost;
            $out['totais']['cost_estimado'] += $est;

            foreach ($models as $m) {
                $mk = (string) ($m['model'] ?? 'unknown');
                if (!isset($aggModelo[$mk])) {
                    $aggModelo[$mk] = [
                        'model' => $mk,
                        'requests' => 0,
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0,
                        'cost_usd' => 0.0,
                        'cost_estimado' => 0.0,
                    ];
                }
                $aggModelo[$mk]['requests'] += (int) ($m['requests'] ?? 0);
                $aggModelo[$mk]['prompt_tokens'] += (int) ($m['prompt_tokens'] ?? 0);
                $aggModelo[$mk]['completion_tokens'] += (int) ($m['completion_tokens'] ?? 0);
                $aggModelo[$mk]['total_tokens'] += (int) ($m['total_tokens'] ?? 0);
                $aggModelo[$mk]['cost_usd'] += (float) ($m['cost_usd'] ?? 0);
                $aggModelo[$mk]['cost_estimado'] += self::estimarCustoUsd(
                    $mk,
                    (int) ($m['prompt_tokens'] ?? 0),
                    (int) ($m['completion_tokens'] ?? 0)
                );
            }

            foreach ($types as $t) {
                $tk = (string) ($t['usage_type'] ?? 'general');
                if (!isset($aggTipo[$tk])) {
                    $aggTipo[$tk] = [
                        'usage_type' => $tk,
                        'requests' => 0,
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0,
                        'cost_usd' => 0.0,
                    ];
                }
                $aggTipo[$tk]['requests'] += (int) ($t['requests'] ?? 0);
                $aggTipo[$tk]['prompt_tokens'] += (int) ($t['prompt_tokens'] ?? 0);
                $aggTipo[$tk]['completion_tokens'] += (int) ($t['completion_tokens'] ?? 0);
                $aggTipo[$tk]['total_tokens'] += (int) ($t['total_tokens'] ?? 0);
                $aggTipo[$tk]['cost_usd'] += (float) ($t['cost_usd'] ?? 0);
            }

            foreach ($days as $d) {
                $dk = (string) ($d['dt'] ?? '');
                if ($dk === '') {
                    continue;
                }
                if (!isset($aggDia[$dk])) {
                    $aggDia[$dk] = [
                        'date' => $dk,
                        'requests' => 0,
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0,
                        'cost_usd' => 0.0,
                    ];
                }
                $aggDia[$dk]['requests'] += (int) ($d['requests'] ?? 0);
                $aggDia[$dk]['prompt_tokens'] += (int) ($d['prompt_tokens'] ?? 0);
                $aggDia[$dk]['completion_tokens'] += (int) ($d['completion_tokens'] ?? 0);
                $aggDia[$dk]['total_tokens'] += (int) ($d['total_tokens'] ?? 0);
                $aggDia[$dk]['cost_usd'] += (float) ($d['cost_usd'] ?? 0);
            }
        }

        usort($porEscola, static fn ($a, $b) => $b['cost_usd'] <=> $a['cost_usd']);
        $porModelo = array_values($aggModelo);
        usort($porModelo, static fn ($a, $b) => $b['cost_usd'] <=> $a['cost_usd']);
        foreach ($porModelo as &$row) {
            $row['cost_usd'] = round((float) $row['cost_usd'], 6);
            $row['cost_estimado'] = round((float) $row['cost_estimado'], 6);
        }
        unset($row);

        $porTipo = array_values($aggTipo);
        usort($porTipo, static fn ($a, $b) => $b['cost_usd'] <=> $a['cost_usd']);
        foreach ($porTipo as &$row) {
            $row['cost_usd'] = round((float) $row['cost_usd'], 6);
        }
        unset($row);

        ksort($aggDia);
        $porDia = array_values($aggDia);
        foreach ($porDia as &$row) {
            $row['cost_usd'] = round((float) $row['cost_usd'], 6);
        }
        unset($row);

        $out['totais']['cost_usd'] = round($out['totais']['cost_usd'], 6);
        $out['totais']['cost_estimado'] = round($out['totais']['cost_estimado'], 6);
        $out['por_escola'] = $porEscola;
        $out['por_modelo'] = $porModelo;
        $out['por_tipo'] = $porTipo;
        $out['por_dia'] = $porDia;

        return $out;
    }
}
