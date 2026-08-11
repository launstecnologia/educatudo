<?php
/**
 * Agrega KPIs do dashboard Master a partir dos bancos das escolas.
 * Uso: CRON à meia-noite (master_dashboard_kpis.php).
 */

if (!class_exists('MasterDashboardKpisService')) {

class MasterDashboardKpisService
{
    /** Intervalo mínimo (minutos) entre sessões do mesmo aluno para contar como novo acesso. */
    private const GAP_ACESSOS_MINUTOS = 10;

    /**
     * Conta métricas de um tenant. Tabelas ausentes são ignoradas (0).
     *
     * @return array{
     *   total_logins_sucesso: int,
     *   total_jornadas: int,
     *   total_provas: int,
     *   modulos: array<int, array{slug: string, label: string, acessos: int}>
     * }
     */
    public function agregarTenant(Database $db): array
    {
        // Sessões do mesmo aluno com menos de 10 min entre login_at não contam
        // (saiu/entrou de novo por erro, refresh, etc.).
        $logins = $this->contarAcessosComGap($db);
        $jornadas = $this->countTabela($db, 'jornadas', "SELECT COUNT(*) AS c FROM jornadas");
        $provas = $this->countTabela($db, 'provas', "SELECT COUNT(*) AS c FROM provas");

        $modulos = [];
        // Alunos distintos que usaram o módulo (não COUNT de linhas de progresso).
        $defs = [
            ['slug' => 'jornadas', 'label' => 'Jornadas', 'tabela' => 'jornadas_progresso_alunos', 'sql' => "SELECT COUNT(DISTINCT aluno_id) AS c FROM jornadas_progresso_alunos"],
            ['slug' => 'provas', 'label' => 'Provas', 'tabela' => 'provas_realizacoes', 'sql' => "SELECT COUNT(DISTINCT aluno_id) AS c FROM provas_realizacoes"],
            ['slug' => 'redacoes', 'label' => 'Redações', 'tabela' => 'jornadas_redacoes_alunos', 'sql' => "SELECT COUNT(DISTINCT aluno_id) AS c FROM jornadas_redacoes_alunos"],
            ['slug' => 'ava', 'label' => 'AVA', 'tabela' => 'ava_progresso_aula', 'sql' => "SELECT COUNT(DISTINCT aluno_id) AS c FROM ava_progresso_aula"],
            ['slug' => 'chat', 'label' => 'Chat / Tudinha', 'tabela' => 'tudinha_mensagens', 'sql' => "SELECT COUNT(DISTINCT aluno_id) AS c FROM tudinha_mensagens"],
        ];

        foreach ($defs as $def) {
            $n = $this->countTabela($db, $def['tabela'], $def['sql']);
            if ($n > 0) {
                $modulos[] = [
                    'slug' => $def['slug'],
                    'label' => $def['label'],
                    'acessos' => $n,
                ];
            }
        }

        return [
            'total_logins_sucesso' => $logins,
            'total_jornadas' => $jornadas,
            'total_provas' => $provas,
            'modulos' => $modulos,
        ];
    }

    /**
     * Soma snapshots por escola e grava no banco master.
     *
     * @param array<int, array{escola_id: int, total_logins_sucesso: int, total_jornadas: int, total_provas: int, modulos: array}> $porEscola
     */
    public function salvarNoMaster(PDO $masterPdo, array $porEscola): void
    {
        $totalLogins = 0;
        $totalJornadas = 0;
        $totalProvas = 0;
        $modulosAgg = [];

        $geradoEm = date('Y-m-d H:i:s');

        foreach ($porEscola as $row) {
            $escolaId = (int) ($row['escola_id'] ?? 0);
            if ($escolaId < 1) {
                continue;
            }
            $logins = (int) ($row['total_logins_sucesso'] ?? 0);
            $jornadas = (int) ($row['total_jornadas'] ?? 0);
            $provas = (int) ($row['total_provas'] ?? 0);
            $modulos = is_array($row['modulos'] ?? null) ? $row['modulos'] : [];

            $totalLogins += $logins;
            $totalJornadas += $jornadas;
            $totalProvas += $provas;

            foreach ($modulos as $m) {
                $slug = (string) ($m['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                if (!isset($modulosAgg[$slug])) {
                    $modulosAgg[$slug] = [
                        'slug' => $slug,
                        'label' => (string) ($m['label'] ?? $slug),
                        'acessos' => 0,
                    ];
                }
                $modulosAgg[$slug]['acessos'] += (int) ($m['acessos'] ?? 0);
            }

            $modulosJson = json_encode(array_values($modulos), JSON_UNESCAPED_UNICODE);
            $stmt = $masterPdo->prepare(
                "INSERT INTO master_dashboard_kpis_escolas
                    (escola_id, total_logins_sucesso, total_jornadas, total_provas, modulos_json, gerado_em)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    total_logins_sucesso = VALUES(total_logins_sucesso),
                    total_jornadas = VALUES(total_jornadas),
                    total_provas = VALUES(total_provas),
                    modulos_json = VALUES(modulos_json),
                    gerado_em = VALUES(gerado_em)"
            );
            $stmt->execute([$escolaId, $logins, $jornadas, $provas, $modulosJson, $geradoEm]);
        }

        $modulosLista = array_values($modulosAgg);
        usort($modulosLista, static function ($a, $b) {
            return ($b['acessos'] ?? 0) <=> ($a['acessos'] ?? 0);
        });
        $modulosJsonGlobal = json_encode($modulosLista, JSON_UNESCAPED_UNICODE);

        $stmt = $masterPdo->prepare(
            "INSERT INTO master_dashboard_kpis
                (id, total_logins_sucesso, total_jornadas, total_provas, modulos_json, gerado_em)
             VALUES (1, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                total_logins_sucesso = VALUES(total_logins_sucesso),
                total_jornadas = VALUES(total_jornadas),
                total_provas = VALUES(total_provas),
                modulos_json = VALUES(modulos_json),
                gerado_em = VALUES(gerado_em)"
        );
        $stmt->execute([$totalLogins, $totalJornadas, $totalProvas, $modulosJsonGlobal, $geradoEm]);
    }

    /**
     * Conta sessões de alunos com gap mínimo entre logins consecutivos do mesmo aluno.
     */
    private function contarAcessosComGap(Database $db): int
    {
        if (!$db->tableExists('alunos_sessoes_acesso')) {
            return 0;
        }
        $gap = self::GAP_ACESSOS_MINUTOS;
        $sql = "SELECT COUNT(*) AS c
                FROM (
                    SELECT aluno_id,
                           login_at,
                           TIMESTAMPDIFF(
                               MINUTE,
                               LAG(login_at) OVER (PARTITION BY aluno_id ORDER BY login_at ASC, id ASC),
                               login_at
                           ) AS gap_min
                    FROM alunos_sessoes_acesso
                ) s
                WHERE gap_min IS NULL OR gap_min >= :gap";
        try {
            $row = $db->fetch($sql, ['gap' => $gap]);
            return (int) ($row['c'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function countTabela(Database $db, string $tabela, string $sql): int
    {
        if (!$db->tableExists($tabela)) {
            return 0;
        }
        try {
            $row = $db->fetch($sql);
            return (int) ($row['c'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

}
