<?php
/**
 * Agrega uso de LLM (tokens + custo USD) de todos os tenants para o painel Master.
 */
namespace App\Painel\Master\Services;


class MasterLlmCustosService
{
    /** Cotação padrão se config/env ausentes. */
    private const USD_BRL_PADRAO = 5.78;

    /**
     * @return array{atualizado_em?:string,usd_brl?:float,modelos?:array<string,array{input:float,output:float}>}
     */
    private static function carregarConfigPrecos(): array
    {
        // Services → Master → Painel → app → backend/config
        $path = dirname(__DIR__, 4) . '/config/llm_precos.php';
        if (!is_file($path)) {
            return [];
        }
        $cfg = require $path;
        return is_array($cfg) ? $cfg : [];
    }

    /**
     * Preços de referência (USD por 1M tokens). Usados para estimativa atualizada na UI.
     * Fonte: documentação OpenAI / feeds públicos — revisar periodicamente.
     *
     * @return array<string, array{input: float, output: float}>
     */
    public static function precosReferenciaPor1M(): array
    {
        $cfg = self::carregarConfigPrecos();
        if (!empty($cfg['modelos']) && is_array($cfg['modelos'])) {
            return $cfg['modelos'];
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

    /**
     * Cotação USD→BRL (config llm_precos.php, env LLM_USD_BRL ou padrão).
     */
    public static function cotacaoUsdBrl(?float $override = null): float
    {
        if ($override !== null && $override > 0) {
            return round($override, 4);
        }
        $env = getenv('LLM_USD_BRL');
        if ($env !== false && is_numeric($env) && (float) $env > 0) {
            return round((float) $env, 4);
        }
        $cfg = self::carregarConfigPrecos();
        $cfgRate = (float) ($cfg['usd_brl'] ?? 0);
        if ($cfgRate > 0) {
            return round($cfgRate, 4);
        }
        return self::USD_BRL_PADRAO;
    }

    /** Meta padrão: gastar no máx. R$ 0,10 de LLM por crédito cobrado a R$ 0,20. */
    private const CUSTO_ALVO_BRL_POR_CREDITO_PADRAO = 0.10;

    public static function custoAlvoBrlPorCredito(): float
    {
        $cfg = self::carregarConfigPrecos();
        $v = (float) ($cfg['custo_alvo_brl_por_credito'] ?? 0);
        if ($v > 0) {
            return round($v, 4);
        }
        return self::CUSTO_ALVO_BRL_POR_CREDITO_PADRAO;
    }

    /**
     * Receita/custo/lucro em BRL com base em TudiCoins consumidos e custo LLM.
     * 1 TudiCoin = R$ 0,20 (CreditosDecimalHelper::BRL_POR_UNIDADE).
     * Meta: custo LLM ≤ R$ 0,10/crédito → lucro alvo R$ 0,10/crédito.
     *
     * @return array<string,mixed>
     */
    public static function montarFinanceiro(
        float $creditosConsumidos,
        float $custoUsd,
        ?float $cotacao = null,
        int $totalTokens = 0
    ): array {
        $cotacao = self::cotacaoUsdBrl($cotacao);
        $brlPorCredito = (float) \App\Core\CreditosDecimalHelper::BRL_POR_UNIDADE;
        $custoAlvoBrl = self::custoAlvoBrlPorCredito();
        $lucroAlvoBrl = round(max(0.0, $brlPorCredito - $custoAlvoBrl), 4);
        $creditos = round(max(0.0, $creditosConsumidos), 4);
        $custoUsd = round(max(0.0, $custoUsd), 6);
        $totalTokens = max(0, $totalTokens);
        $receitaBrl = round($creditos * $brlPorCredito, 2);
        $custoBrl = round($custoUsd * $cotacao, 2);
        $lucroBrl = round($receitaBrl - $custoBrl, 2);
        $margem = $receitaBrl > 0 ? round(($lucroBrl / $receitaBrl) * 100, 1) : null;
        $lucroMedio = $creditos > 0 ? round($lucroBrl / $creditos, 4) : null;
        $custoRealPorCredito = $creditos > 0 ? round($custoBrl / $creditos, 4) : null;
        $tokensPorCreditoReal = ($creditos > 0 && $totalTokens > 0)
            ? (int) round($totalTokens / $creditos)
            : null;

        // Mix do período: R$/token → quantos tokens cabem no custo alvo de 1 crédito.
        $brlPorToken = ($totalTokens > 0 && $custoBrl > 0)
            ? ($custoBrl / $totalTokens)
            : null;
        $tokensPorCreditoAlvo = ($brlPorToken !== null && $brlPorToken > 0)
            ? (int) max(1, round($custoAlvoBrl / $brlPorToken))
            : null;
        $usdMaxPorCredito = $cotacao > 0 ? round($custoAlvoBrl / $cotacao, 6) : null;
        $dentroMeta = $custoRealPorCredito !== null
            ? ($custoRealPorCredito <= $custoAlvoBrl + 0.00005)
            : null;

        // Quanto deveria ser cobrado se debitasse 1 crédito a cada R$ custo_alvo de LLM.
        $creditosEquivalentesUso = \App\Core\CreditosDecimalHelper::creditosPorCustoLlmBrl($custoBrl, $custoAlvoBrl);
        $receitaIdealBrl = round($creditosEquivalentesUso * $brlPorCredito, 2);
        $lucroIdealBrl = round($receitaIdealBrl - $custoBrl, 2);

        return [
            'cotacao_usd_brl' => $cotacao,
            'brl_por_credito' => $brlPorCredito,
            'custo_alvo_brl_por_credito' => $custoAlvoBrl,
            'lucro_alvo_brl_por_credito' => $lucroAlvoBrl,
            'usd_max_por_credito' => $usdMaxPorCredito,
            'creditos_consumidos' => $creditos,
            'creditos_equivalentes_uso' => $creditosEquivalentesUso,
            'receita_ideal_brl' => $receitaIdealBrl,
            'lucro_ideal_brl' => $lucroIdealBrl,
            'receita_brl' => $receitaBrl,
            'custo_usd' => $custoUsd,
            'custo_brl' => $custoBrl,
            'lucro_brl' => $lucroBrl,
            'margem_pct' => $margem,
            'lucro_medio_por_credito_brl' => $lucroMedio,
            'custo_real_por_credito_brl' => $custoRealPorCredito,
            'tokens_por_credito_real' => $tokensPorCreditoReal,
            'tokens_por_credito_alvo' => $tokensPorCreditoAlvo,
            'brl_por_token' => $brlPorToken !== null ? round($brlPorToken, 8) : null,
            'dentro_meta_custo' => $dentroMeta,
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
     *   totais: array{requests:int,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost_usd:float,cost_estimado:float,cost_brl:float,creditos_consumidos:float},
     *   financeiro: array,
     *   por_escola: list<array>,
     *   por_modelo: list<array>,
     *   por_tipo: list<array>,
     *   por_dia: list<array>,
     *   precos: array<string, array{input:float,output:float}>,
     *   erros: list<string>
     * }
     */
    public static function agregar(string $dateStart, string $dateEnd, int $escolaIdFiltro = 0, ?float $cotacaoUsdBrl = null): array
    {
        $cotacao = self::cotacaoUsdBrl($cotacaoUsdBrl);
        $emptyTotais = [
            'requests' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost_usd' => 0.0,
            'cost_estimado' => 0.0,
            'cost_brl' => 0.0,
            'creditos_consumidos' => 0.0,
        ];
        $out = [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'escola_id' => $escolaIdFiltro,
            'totais' => $emptyTotais,
            'financeiro' => self::montarFinanceiro(0, 0, $cotacao),
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

        $db = \App\Core\Database::getInstance();
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
            $conn = \App\Core\MasterTenantConnection::getPdoAndEscola($eid);
            if ($conn === null || empty($conn['pdo'])) {
                $out['erros'][] = 'Sem conexão: ' . ($escola['nome'] ?? $eid);
                continue;
            }
            /** @var \PDO $pdo */
            $pdo = $conn['pdo'];
            $tot = [];
            $models = [];
            $types = [];
            $days = [];
            $temLogsLlm = true;
            try {
                $pdo->query('SELECT 1 FROM logs_uso_llm LIMIT 1');
            } catch (\Throwable $e) {
                $temLogsLlm = false;
            }

            if ($temLogsLlm) {
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
                }
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

            $creditosEscola = 0.0;
            try {
                $stC = $pdo->prepare(
                    "SELECT COALESCE(SUM(ABS(valor)), 0) AS creditos
                     FROM carteira_movimentacoes
                     WHERE tipo = 'consumo'
                       AND created_at >= ? AND created_at <= ?"
                );
                $stC->execute([$start, $end]);
                $creditosEscola = round((float) (($stC->fetch(\PDO::FETCH_ASSOC) ?: [])['creditos'] ?? 0), 4);
            } catch (\Throwable $e) {
                // carteira ausente — ignora
            }

            $costBrl = round($cost * $cotacao, 2);
            $finEscola = self::montarFinanceiro($creditosEscola, $cost, $cotacao, $tt);

            if ($req > 0 || $tt > 0 || $creditosEscola > 0) {
                $porEscola[] = [
                    'escola_id' => $eid,
                    'nome' => (string) ($escola['nome'] ?? ''),
                    'slug' => (string) ($escola['slug'] ?? ''),
                    'requests' => $req,
                    'prompt_tokens' => $pt,
                    'completion_tokens' => $ct,
                    'total_tokens' => $tt,
                    'cost_usd' => round($cost, 6),
                    'cost_brl' => $costBrl,
                    'cost_estimado' => round($est, 6),
                    'creditos_consumidos' => $creditosEscola,
                    'creditos_equivalentes_uso' => (float) ($finEscola['creditos_equivalentes_uso'] ?? 0),
                    // Custo = gasto LLM; venda = cobrança pelo uso (meta R$ 0,20 / crédito cheio).
                    'custo_brl' => $costBrl,
                    'venda_brl' => (float) ($finEscola['receita_ideal_brl'] ?? 0),
                    'receita_brl' => $finEscola['receita_brl'],
                    'lucro_brl' => (float) ($finEscola['lucro_ideal_brl'] ?? $finEscola['lucro_brl']),
                    'lucro_cobrado_brl' => $finEscola['lucro_brl'],
                ];
            }

            $out['totais']['requests'] += $req;
            $out['totais']['prompt_tokens'] += $pt;
            $out['totais']['completion_tokens'] += $ct;
            $out['totais']['total_tokens'] += $tt;
            $out['totais']['cost_usd'] += $cost;
            $out['totais']['cost_estimado'] += $est;
            $out['totais']['creditos_consumidos'] += $creditosEscola;

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
            $row['cost_brl'] = round((float) $row['cost_usd'] * $cotacao, 2);
            $row['cost_estimado'] = round((float) $row['cost_estimado'], 6);
        }
        unset($row);

        $porTipo = array_values($aggTipo);
        usort($porTipo, static fn ($a, $b) => $b['cost_usd'] <=> $a['cost_usd']);
        foreach ($porTipo as &$row) {
            $row['cost_usd'] = round((float) $row['cost_usd'], 6);
            $finTipo = self::montarFinanceiro(0.0, (float) $row['cost_usd'], $cotacao, (int) ($row['total_tokens'] ?? 0));
            $row['cost_brl'] = (float) ($finTipo['custo_brl'] ?? 0);
            $row['custo_brl'] = $row['cost_brl'];
            $row['venda_brl'] = (float) ($finTipo['receita_ideal_brl'] ?? 0);
            $row['lucro_brl'] = (float) ($finTipo['lucro_ideal_brl'] ?? 0);
            $row['creditos_equivalentes_uso'] = (float) ($finTipo['creditos_equivalentes_uso'] ?? 0);
        }
        unset($row);

        ksort($aggDia);
        $porDia = array_values($aggDia);
        foreach ($porDia as &$row) {
            $row['cost_usd'] = round((float) $row['cost_usd'], 6);
            $finDia = self::montarFinanceiro(0.0, (float) $row['cost_usd'], $cotacao, (int) ($row['total_tokens'] ?? 0));
            $row['cost_brl'] = (float) ($finDia['custo_brl'] ?? 0);
            $row['custo_brl'] = $row['cost_brl'];
            $row['venda_brl'] = (float) ($finDia['receita_ideal_brl'] ?? 0);
            $row['lucro_brl'] = (float) ($finDia['lucro_ideal_brl'] ?? 0);
            $row['creditos_equivalentes_uso'] = (float) ($finDia['creditos_equivalentes_uso'] ?? 0);
        }
        unset($row);

        $out['totais']['cost_usd'] = round($out['totais']['cost_usd'], 6);
        $out['totais']['cost_estimado'] = round($out['totais']['cost_estimado'], 6);
        $out['totais']['creditos_consumidos'] = round((float) $out['totais']['creditos_consumidos'], 4);
        $out['financeiro'] = self::montarFinanceiro(
            (float) $out['totais']['creditos_consumidos'],
            (float) $out['totais']['cost_usd'],
            $cotacao,
            (int) ($out['totais']['total_tokens'] ?? 0)
        );
        $out['totais']['cost_brl'] = $out['financeiro']['custo_brl'];
        $out['por_escola'] = $porEscola;
        $out['por_modelo'] = $porModelo;
        $out['por_tipo'] = $porTipo;
        $out['por_dia'] = $porDia;

        return $out;
    }

    /**
     * Detalhe de um dia: chats Tudinha (mensagens com tokens) + chamadas brutas de logs_uso_llm.
     *
     * @return array{
     *   date: string,
     *   escola_id: int,
     *   conversas: list<array>,
     *   chamadas: list<array>,
     *   totais: array{conversas:int,mensagens:int,chamadas:int,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost_usd:float},
     *   erros: list<string>
     * }
     */
    public static function detalheDia(
        string $date,
        int $escolaIdFiltro = 0,
        int $limiteChamadas = 200,
        int $limiteMensagens = 100,
        ?float $cotacaoUsdBrl = null
    ): array {
        $cotacao = self::cotacaoUsdBrl($cotacaoUsdBrl);
        $brlPorCredito = (float) \App\Core\CreditosDecimalHelper::BRL_POR_UNIDADE;
        $custoAlvoBrl = self::custoAlvoBrlPorCredito();
        $out = [
            'date' => $date,
            'escola_id' => $escolaIdFiltro,
            'conversas' => [],
            'chamadas' => [],
            'totais' => [
                'conversas' => 0,
                'mensagens' => 0,
                'chamadas' => 0,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'cost_usd' => 0.0,
                'custo_brl' => 0.0,
                'venda_brl' => 0.0,
                'lucro_brl' => 0.0,
                'creditos_equivalentes_uso' => 0.0,
            ],
            'meta' => [
                'cotacao_usd_brl' => $cotacao,
                'brl_por_credito' => $brlPorCredito,
                'custo_alvo_brl_por_credito' => $custoAlvoBrl,
            ],
            'erros' => [],
        ];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $out['erros'][] = 'Data inválida.';
            return $out;
        }

        $limiteChamadas = max(1, min(500, $limiteChamadas));
        $limiteMensagens = max(1, min(300, $limiteMensagens));
        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';

        $db = \App\Core\Database::getInstance();
        $escolas = $db->query(
            "SELECT e.id, e.nome, e.slug
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $conversasAgg = [];
        $chamadas = [];
        $restanteChamadas = $limiteChamadas;
        $restanteMensagens = $limiteMensagens;

        foreach ($escolas as $escola) {
            if ($restanteChamadas <= 0 && $restanteMensagens <= 0) {
                break;
            }
            $eid = (int) ($escola['id'] ?? 0);
            if ($escolaIdFiltro > 0 && $eid !== $escolaIdFiltro) {
                continue;
            }
            $conn = \App\Core\MasterTenantConnection::getPdoAndEscola($eid);
            if ($conn === null || empty($conn['pdo'])) {
                $out['erros'][] = 'Sem conexão: ' . ($escola['nome'] ?? $eid);
                continue;
            }
            /** @var \PDO $pdo */
            $pdo = $conn['pdo'];
            $nomeEscola = (string) ($escola['nome'] ?? '');
            $slugEscola = (string) ($escola['slug'] ?? '');

            if ($restanteChamadas > 0) {
                try {
                    $pdo->query('SELECT 1 FROM logs_uso_llm LIMIT 1');
                    $limLocal = $restanteChamadas;
                    $stL = $pdo->prepare(
                        "SELECT id, model, prompt_tokens, completion_tokens, total_tokens, cost_usd, usage_type, created_at
                         FROM logs_uso_llm
                         WHERE created_at >= ? AND created_at <= ?
                         ORDER BY created_at DESC, id DESC
                         LIMIT {$limLocal}"
                    );
                    $stL->execute([$start, $end]);
                    foreach ($stL->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $pt = (int) ($row['prompt_tokens'] ?? 0);
                        $ct = (int) ($row['completion_tokens'] ?? 0);
                        $tt = (int) ($row['total_tokens'] ?? ($pt + $ct));
                        $cost = (float) ($row['cost_usd'] ?? 0);
                        $custoBrl = round($cost * $cotacao, 4);
                        $creditosUso = \App\Core\CreditosDecimalHelper::creditosPorCustoLlmBrl($custoBrl, $custoAlvoBrl);
                        $vendaBrl = round($creditosUso * $brlPorCredito, 4);
                        $chamadas[] = [
                            'escola_id' => $eid,
                            'escola_nome' => $nomeEscola,
                            'escola_slug' => $slugEscola,
                            'id' => (int) ($row['id'] ?? 0),
                            'model' => (string) ($row['model'] ?? ''),
                            'prompt_tokens' => $pt,
                            'completion_tokens' => $ct,
                            'total_tokens' => $tt,
                            'cost_usd' => round($cost, 6),
                            'custo_brl' => $custoBrl,
                            'venda_brl' => $vendaBrl,
                            'lucro_brl' => round($vendaBrl - $custoBrl, 4),
                            'creditos_equivalentes_uso' => $creditosUso,
                            'usage_type' => (string) ($row['usage_type'] ?? ''),
                            'created_at' => (string) ($row['created_at'] ?? ''),
                        ];
                        $out['totais']['chamadas']++;
                        $out['totais']['prompt_tokens'] += $pt;
                        $out['totais']['completion_tokens'] += $ct;
                        $out['totais']['total_tokens'] += $tt;
                        $out['totais']['cost_usd'] += $cost;
                        $restanteChamadas--;
                        if ($restanteChamadas <= 0) {
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // tabela ausente nesta escola — ignora
                }
            }

            if ($restanteMensagens <= 0) {
                continue;
            }

            try {
                $temTokens = false;
                try {
                    $chk = $pdo->query(
                        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'assistente_mensagens'
                           AND COLUMN_NAME = 'prompt_tokens'
                         LIMIT 1"
                    );
                    $temTokens = (bool) $chk->fetchColumn();
                } catch (\Throwable $e) {
                    $temTokens = false;
                }

                // Sem colunas de token: não lista preview de chat (evita PII sem valor de custo).
                if (!$temTokens) {
                    continue;
                }

                $limLocalMsg = $restanteMensagens;
                $sqlMsg = "SELECT m.id AS mensagem_id, m.conversa_id, m.conteudo, m.created_at,
                                  m.prompt_tokens, m.completion_tokens, m.total_tokens, m.modelo,
                                  c.titulo, c.usuario_id, c.aluno_id, c.aluno_nome,
                                  u.nome AS usuario_nome
                           FROM assistente_mensagens m
                           INNER JOIN assistente_conversas c ON c.id = m.conversa_id
                           LEFT JOIN usuarios u ON u.id = c.usuario_id
                           WHERE m.role = 'assistant'
                             AND m.created_at >= ? AND m.created_at <= ?
                             AND (m.total_tokens > 0 OR m.prompt_tokens > 0 OR m.completion_tokens > 0)
                           ORDER BY m.created_at DESC, m.id DESC
                           LIMIT {$limLocalMsg}";

                $stM = $pdo->prepare($sqlMsg);
                $stM->execute([$start, $end]);
                foreach ($stM->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $cid = (int) ($row['conversa_id'] ?? 0);
                    $key = $eid . ':' . $cid;
                    if (!isset($conversasAgg[$key])) {
                        $conversasAgg[$key] = [
                            'escola_id' => $eid,
                            'escola_nome' => $nomeEscola,
                            'escola_slug' => $slugEscola,
                            'conversa_id' => $cid,
                            'titulo' => (string) ($row['titulo'] ?? 'Conversa'),
                            'usuario_id' => (int) ($row['usuario_id'] ?? 0),
                            'usuario_nome' => (string) ($row['usuario_nome'] ?? ''),
                            'aluno_id' => isset($row['aluno_id']) && $row['aluno_id'] !== null ? (int) $row['aluno_id'] : null,
                            'aluno_nome' => isset($row['aluno_nome']) ? (string) $row['aluno_nome'] : null,
                            'prompt_tokens' => 0,
                            'completion_tokens' => 0,
                            'total_tokens' => 0,
                            'mensagens' => [],
                        ];
                        $out['totais']['conversas']++;
                    }
                    $pt = (int) ($row['prompt_tokens'] ?? 0);
                    $ct = (int) ($row['completion_tokens'] ?? 0);
                    $tt = (int) ($row['total_tokens'] ?? ($pt + $ct));
                    $preview = trim((string) ($row['conteudo'] ?? ''));
                    if (mb_strlen($preview) > 140) {
                        $preview = mb_substr($preview, 0, 137) . '…';
                    }
                    $conversasAgg[$key]['prompt_tokens'] += $pt;
                    $conversasAgg[$key]['completion_tokens'] += $ct;
                    $conversasAgg[$key]['total_tokens'] += $tt;
                    $conversasAgg[$key]['mensagens'][] = [
                        'mensagem_id' => (int) ($row['mensagem_id'] ?? 0),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                        'preview' => $preview,
                        'prompt_tokens' => $pt,
                        'completion_tokens' => $ct,
                        'total_tokens' => $tt,
                        'modelo' => isset($row['modelo']) ? (string) $row['modelo'] : null,
                    ];
                    $out['totais']['mensagens']++;
                    $restanteMensagens--;
                    if ($restanteMensagens <= 0) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // tabelas do assistente ausentes — ignora
            }
        }

        $conversas = array_values($conversasAgg);
        usort($conversas, static fn ($a, $b) => ($b['total_tokens'] ?? 0) <=> ($a['total_tokens'] ?? 0));
        usort($chamadas, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        $out['conversas'] = $conversas;
        $out['chamadas'] = $chamadas;
        $out['totais']['cost_usd'] = round($out['totais']['cost_usd'], 6);

        $fin = self::montarFinanceiro(
            0.0,
            (float) $out['totais']['cost_usd'],
            $cotacao,
            (int) ($out['totais']['total_tokens'] ?? 0)
        );
        $out['totais']['custo_brl'] = (float) ($fin['custo_brl'] ?? 0);
        $out['totais']['venda_brl'] = (float) ($fin['receita_ideal_brl'] ?? 0);
        $out['totais']['lucro_brl'] = (float) ($fin['lucro_ideal_brl'] ?? 0);
        $out['totais']['creditos_equivalentes_uso'] = (float) ($fin['creditos_equivalentes_uso'] ?? 0);
        $out['financeiro'] = $fin;

        return $out;
    }
}

if (!class_exists('MasterLlmCustosService', false) && class_exists(__NAMESPACE__ . '\\MasterLlmCustosService', false)) {
    class_alias(__NAMESPACE__ . '\\MasterLlmCustosService', 'MasterLlmCustosService');
}
