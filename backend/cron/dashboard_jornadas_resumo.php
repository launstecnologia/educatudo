<?php
/**
 * Cron: Pré-cálculo do resumo de jornadas para o dashboard admin.
 * Recomendado: 00:00 e 12:00
 * 0 0,12 * * * /usr/bin/php /caminho/projeto/src/cron/dashboard_jornadas_resumo.php >> /caminho/projeto/src/storage/logs/cron_dashboard_jornadas_resumo.log 2>&1
 */

$isCli = php_sapi_name() === 'cli';
$basePath = dirname(__DIR__);

date_default_timezone_set('America/Sao_Paulo');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';

$cronMultiTenantPath = $basePath . '/app/Core/cron_multi_tenant_helper.php';
if (file_exists($cronMultiTenantPath)) {
    require_once $cronMultiTenantPath;
}

function logResumo(string $msg, string $basePath): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($basePath . '/storage/logs/cron_dashboard_jornadas_resumo.log', $line, FILE_APPEND);
    echo $line;
}

function calcularResumoSegmento(Database $db, string $segmento): array
{
    if ($segmento === 'medio') {
        $whereTipoJornada = "LOWER(COALESCE(t.tipo_ensino, '')) LIKE '%medio%'";
        $whereTipoAluno = "LOWER(COALESCE(t_aluno.tipo_ensino, '')) LIKE '%medio%'";
    } elseif ($segmento === 'fundamental_ii') {
        $whereTipoJornada = "(LOWER(COALESCE(t.tipo_ensino, '')) LIKE '%fundamental ii%' OR LOWER(COALESCE(t.tipo_ensino, '')) LIKE '%fundamental 2%')";
        $whereTipoAluno = "(LOWER(COALESCE(t_aluno.tipo_ensino, '')) LIKE '%fundamental ii%' OR LOWER(COALESCE(t_aluno.tipo_ensino, '')) LIKE '%fundamental 2%')";
    } else {
        return [
            'jornadas_escopo' => 0,
            'pares_atribuidos' => 0,
            'concluidos' => 0,
            'pendentes' => 0,
            'taxa_conclusao' => 0.0,
        ];
    }

    $totalJornadas = (int)($db->fetch(
        "SELECT COUNT(*) AS count
         FROM jornadas j
         INNER JOIN turmas t ON t.id = j.turma_id
         WHERE {$whereTipoJornada}"
    )['count'] ?? 0);

    $resumo = $db->fetch(
        "SELECT
            COALESCE(SUM(base.jornadas_atribuidas), 0) AS total_previstas,
            COALESCE(SUM(base.jornadas_feitas), 0) AS total_realizadas
         FROM (
             SELECT
                a.id AS aluno_id,
                COUNT(DISTINCT j.id) AS jornadas_atribuidas,
                LEAST(
                    COUNT(DISTINCT CASE WHEN jpa.jornada_id IS NOT NULL THEN j.id END),
                    COUNT(DISTINCT j.id)
                ) AS jornadas_feitas
             FROM alunos a
             INNER JOIN turmas t_aluno ON t_aluno.id = a.turma_id
             LEFT JOIN jornadas j ON j.turma_id = a.turma_id
             LEFT JOIN jornadas_progresso_alunos jpa
                ON jpa.aluno_id = a.id
               AND jpa.jornada_id = j.id
               AND jpa.status = 'concluido'
               AND (jpa.atividade_tipo IS NULL OR jpa.atividade_tipo = 'jornada_concluida')
             WHERE a.ativo = 1
               AND {$whereTipoAluno}
             GROUP BY a.id
         ) base"
    ) ?: [];

    $paresAtribuidos = (int)($resumo['total_previstas'] ?? 0);
    $concluidos = (int)($resumo['total_realizadas'] ?? 0);
    if ($concluidos > $paresAtribuidos) {
        $concluidos = $paresAtribuidos;
    }
    $pendentes = max(0, $paresAtribuidos - $concluidos);
    $taxa = $paresAtribuidos > 0 ? round(($concluidos / $paresAtribuidos) * 100, 2) : 0.0;

    return [
        'jornadas_escopo' => $totalJornadas,
        'pares_atribuidos' => $paresAtribuidos,
        'concluidos' => $concluidos,
        'pendentes' => $pendentes,
        'taxa_conclusao' => $taxa,
    ];
}

$runner = function (?int $escolaId) use ($basePath) {
    $db = Database::getInstance();

    try {
        $db->query("CREATE TABLE IF NOT EXISTS dashboard_jornadas_resumo (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            segmento VARCHAR(32) NOT NULL,
            jornadas_escopo INT NOT NULL DEFAULT 0,
            pares_atribuidos INT NOT NULL DEFAULT 0,
            concluidos INT NOT NULL DEFAULT 0,
            pendentes INT NOT NULL DEFAULT 0,
            taxa_conclusao DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            atualizado_em DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dashboard_jornadas_resumo_segmento (segmento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach (['medio', 'fundamental_ii'] as $segmento) {
            $r = calcularResumoSegmento($db, $segmento);
            $db->query(
                "INSERT INTO dashboard_jornadas_resumo
                    (segmento, jornadas_escopo, pares_atribuidos, concluidos, pendentes, taxa_conclusao, atualizado_em)
                 VALUES
                    (:segmento, :jornadas_escopo, :pares_atribuidos, :concluidos, :pendentes, :taxa_conclusao, NOW())
                 ON DUPLICATE KEY UPDATE
                    jornadas_escopo = VALUES(jornadas_escopo),
                    pares_atribuidos = VALUES(pares_atribuidos),
                    concluidos = VALUES(concluidos),
                    pendentes = VALUES(pendentes),
                    taxa_conclusao = VALUES(taxa_conclusao),
                    atualizado_em = VALUES(atualizado_em)",
                [
                    'segmento' => $segmento,
                    'jornadas_escopo' => $r['jornadas_escopo'],
                    'pares_atribuidos' => $r['pares_atribuidos'],
                    'concluidos' => $r['concluidos'],
                    'pendentes' => $r['pendentes'],
                    'taxa_conclusao' => $r['taxa_conclusao'],
                ]
            );
        }

        logResumo('Resumo dashboard jornadas atualizado com sucesso' . ($escolaId ? " (escola_id={$escolaId})" : ''), $basePath);
    } catch (Throwable $e) {
        logResumo('Erro ao atualizar resumo dashboard jornadas: ' . $e->getMessage(), $basePath);
    }
};

if (class_exists('CronMultiTenantHelper')) {
    CronMultiTenantHelper::run($runner, true);
} else {
    $runner(null);
}
