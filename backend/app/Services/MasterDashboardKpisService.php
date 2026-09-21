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
     *   total_exercicios_ia: int,
     *   total_exercicios: int,
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
        $exerciciosIa = $this->countTabela(
            $db,
            'listas_personalizadas_respostas',
            "SELECT COUNT(*) AS c FROM listas_personalizadas_respostas"
        );
        $exerciciosUnidade = $this->countTabela(
            $db,
            'exercicios_respostas',
            "SELECT COUNT(*) AS c FROM exercicios_respostas"
        );
        $exerciciosJornada = $this->countTabela(
            $db,
            'jornadas_progresso_alunos',
            "SELECT COUNT(*) AS c FROM jornadas_progresso_alunos
              WHERE atividade_tipo IN ('exercicio', 'exercicio_modulo')
                AND resposta IS NOT NULL
                AND resposta != ''"
        );
        $exerciciosProva = $this->countTabela(
            $db,
            'provas_respostas',
            "SELECT COUNT(*) AS c FROM provas_respostas"
        );
        $exerciciosTotal = $exerciciosIa + $exerciciosUnidade + $exerciciosJornada + $exerciciosProva;

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
            'total_exercicios_ia' => $exerciciosIa,
            'total_exercicios' => $exerciciosTotal,
            'modulos' => $modulos,
        ];
    }

    /**
     * Soma snapshots por escola e grava no banco master.
     *
     * @param array<int, array{escola_id: int, total_logins_sucesso: int, total_jornadas: int, total_provas: int, total_exercicios_ia?: int, total_exercicios?: int, modulos: array}> $porEscola
     */
    public function salvarNoMaster(PDO $masterPdo, array $porEscola): void
    {
        $totalLogins = 0;
        $totalJornadas = 0;
        $totalProvas = 0;
        $totalExerciciosIa = 0;
        $totalExercicios = 0;
        $modulosAgg = [];
        $temExercicios = $this->masterTemColuna($masterPdo, 'master_dashboard_kpis', 'total_exercicios')
            && $this->masterTemColuna($masterPdo, 'master_dashboard_kpis', 'total_exercicios_ia')
            && $this->masterTemColuna($masterPdo, 'master_dashboard_kpis_escolas', 'total_exercicios')
            && $this->masterTemColuna($masterPdo, 'master_dashboard_kpis_escolas', 'total_exercicios_ia');

        $geradoEm = date('Y-m-d H:i:s');

        foreach ($porEscola as $row) {
            $escolaId = (int) ($row['escola_id'] ?? 0);
            if ($escolaId < 1) {
                continue;
            }
            $logins = (int) ($row['total_logins_sucesso'] ?? 0);
            $jornadas = (int) ($row['total_jornadas'] ?? 0);
            $provas = (int) ($row['total_provas'] ?? 0);
            $exerciciosIa = (int) ($row['total_exercicios_ia'] ?? 0);
            $exercicios = (int) ($row['total_exercicios'] ?? 0);
            $modulos = is_array($row['modulos'] ?? null) ? $row['modulos'] : [];

            $totalLogins += $logins;
            $totalJornadas += $jornadas;
            $totalProvas += $provas;
            $totalExerciciosIa += $exerciciosIa;
            $totalExercicios += $exercicios;

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
            if ($temExercicios) {
                $stmt = $masterPdo->prepare(
                    "INSERT INTO master_dashboard_kpis_escolas
                        (escola_id, total_logins_sucesso, total_jornadas, total_provas,
                         total_exercicios_ia, total_exercicios, modulos_json, gerado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        total_logins_sucesso = VALUES(total_logins_sucesso),
                        total_jornadas = VALUES(total_jornadas),
                        total_provas = VALUES(total_provas),
                        total_exercicios_ia = VALUES(total_exercicios_ia),
                        total_exercicios = VALUES(total_exercicios),
                        modulos_json = VALUES(modulos_json),
                        gerado_em = VALUES(gerado_em)"
                );
                $stmt->execute([$escolaId, $logins, $jornadas, $provas, $exerciciosIa, $exercicios, $modulosJson, $geradoEm]);
            } else {
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
        }

        $modulosLista = array_values($modulosAgg);
        usort($modulosLista, static function ($a, $b) {
            return ($b['acessos'] ?? 0) <=> ($a['acessos'] ?? 0);
        });
        $modulosJsonGlobal = json_encode($modulosLista, JSON_UNESCAPED_UNICODE);

        if ($temExercicios) {
            $stmt = $masterPdo->prepare(
                "INSERT INTO master_dashboard_kpis
                    (id, total_logins_sucesso, total_jornadas, total_provas,
                     total_exercicios_ia, total_exercicios, modulos_json, gerado_em)
                 VALUES (1, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    total_logins_sucesso = VALUES(total_logins_sucesso),
                    total_jornadas = VALUES(total_jornadas),
                    total_provas = VALUES(total_provas),
                    total_exercicios_ia = VALUES(total_exercicios_ia),
                    total_exercicios = VALUES(total_exercicios),
                    modulos_json = VALUES(modulos_json),
                    gerado_em = VALUES(gerado_em)"
            );
            $stmt->execute([$totalLogins, $totalJornadas, $totalProvas, $totalExerciciosIa, $totalExercicios, $modulosJsonGlobal, $geradoEm]);
        } else {
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
    }

    private function masterTemColuna(PDO $pdo, string $tabela, string $coluna): bool
    {
        $permitidas = ['master_dashboard_kpis', 'master_dashboard_kpis_escolas'];
        if (!in_array($tabela, $permitidas, true) || !preg_match('/^[a-z0-9_]+$/', $coluna)) {
            return false;
        }
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :tabela
                    AND COLUMN_NAME = :coluna"
            );
            $st->execute(['tabela' => $tabela, 'coluna' => $coluna]);
            return (int) $st->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
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
