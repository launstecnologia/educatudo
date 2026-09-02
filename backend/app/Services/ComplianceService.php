<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/FrequencyService.php';
require_once __DIR__ . '/ClassDiaryService.php';

/**
 * EducaTudo - ComplianceService
 *
 * Painel de Conformidade Pedagógica: consolida, em tempo real, indicadores de
 * todos os módulos relevantes (cadastro/documentação, Censo/INEP, diário de
 * classe, frequência, BNCC e calendário) sem exigir lançamentos duplicados.
 *
 * Cada indicador retorna um percentual (0–100) e uma cor (verde/amarelo/vermelho)
 * conforme as faixas: verde > 95%, amarelo 80–95%, vermelho < 80%.
 *
 * Toda consulta é defensiva: tabelas/colunas inexistentes (tenant que não rodou
 * a migration) não quebram o painel — o indicador correspondente vira "indisponível".
 */
class ComplianceService
{
    /** @var Database */
    private $db;

    /** Documentos considerados essenciais para o indicador de documentação. */
    private const DOCS_ESSENCIAIS = ['cpf', 'certidao_nascimento', 'comprovante_residencia'];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function cor(?float $pct): string
    {
        if ($pct === null) {
            return 'indisponivel';
        }
        if ($pct > 95) {
            return 'verde';
        }
        if ($pct >= 80) {
            return 'amarelo';
        }
        return 'vermelho';
    }

    /**
     * Dashboard consolidado: cabeçalho da escola, indicadores e conformidade geral.
     *
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $indicadores = [
            'documentacao' => $this->indicadorDocumentacao(),
            'censo' => $this->indicadorCenso(),
            'diario' => $this->indicadorDiario(),
            'frequencia' => $this->indicadorFrequencia(),
            'bncc' => $this->indicadorBncc(),
            'calendario' => $this->indicadorCalendario(),
        ];

        $percentuais = [];
        foreach ($indicadores as $ind) {
            if ($ind['percentual'] !== null) {
                $percentuais[] = (float) $ind['percentual'];
            }
        }
        $geral = $percentuais ? round(array_sum($percentuais) / count($percentuais), 1) : null;

        return [
            'escola' => $this->resumoEscola(),
            'indicadores' => $indicadores,
            'conformidade_geral' => $geral,
            'conformidade_cor' => self::cor($geral),
        ];
    }

    /**
     * Resumo numérico da escola para o cabeçalho do painel.
     *
     * @return array<string,mixed>
     */
    public function resumoEscola(): array
    {
        return [
            'ano_letivo' => (int) date('Y'),
            'turmas' => $this->contar('turmas'),
            'alunos' => $this->contar('alunos', 'ativo = 1'),
            'professores' => $this->contar('professores', 'ativo = 1'),
        ];
    }

    /** Indicador: % de alunos ativos com todos os documentos essenciais entregues. */
    private function indicadorDocumentacao(): array
    {
        $base = $this->indicadorVazio('Documentação', 'fa-folder-open');
        if (!$this->tableExists('alunos_documentos') || !$this->tableExists('alunos')) {
            return $base;
        }
        $total = $this->contar('alunos', 'ativo = 1');
        if ($total === 0) {
            return $base + ['percentual' => null];
        }
        $placeholders = implode(',', array_fill(0, count(self::DOCS_ESSENCIAIS), '?'));
        $necessarios = count(self::DOCS_ESSENCIAIS);
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS completos FROM (
                SELECT a.id,
                       COUNT(DISTINCT CASE WHEN d.status = 'entregue' AND d.tipo IN ($placeholders) THEN d.tipo END) AS entregues
                FROM alunos a
                LEFT JOIN alunos_documentos d ON d.aluno_id = a.id
                WHERE a.ativo = 1
                GROUP BY a.id
                HAVING entregues >= ?
            ) t",
            array_merge(self::DOCS_ESSENCIAIS, [$necessarios])
        );
        $completos = (int) ($row['completos'] ?? 0);
        $pct = round(($completos / $total) * 100, 1);
        return array_merge($base, [
            'percentual' => $pct,
            'cor' => self::cor($pct),
            'detalhe' => $completos . ' de ' . $total . ' alunos com documentação essencial completa',
            'link' => '/admin/conformidade/pendencias?area=documentacao',
        ]);
    }

    /** Indicador: % de alunos ativos com campos do Censo/INEP preenchidos. */
    private function indicadorCenso(): array
    {
        $base = $this->indicadorVazio('Censo / INEP', 'fa-school-flag');
        if (!$this->tableExists('alunos')) {
            return $base;
        }
        $cols = $this->colunas('alunos');
        $checks = [];
        foreach (['codigo_inep', 'cpf', 'nome_mae', 'cor_raca', 'data_nasc'] as $c) {
            if (isset($cols[$c])) {
                $checks[] = "(a.$c IS NOT NULL AND TRIM(a.$c) <> '')";
            }
        }
        if (!$checks) {
            return $base;
        }
        $total = $this->contar('alunos', 'ativo = 1');
        if ($total === 0) {
            return $base + ['percentual' => null];
        }
        $cond = implode(' AND ', $checks);
        $row = $this->db->fetch("SELECT COUNT(*) AS ok FROM alunos a WHERE a.ativo = 1 AND $cond");
        $ok = (int) ($row['ok'] ?? 0);
        $pct = round(($ok / $total) * 100, 1);
        return array_merge($base, [
            'percentual' => $pct,
            'cor' => self::cor($pct),
            'detalhe' => $ok . ' de ' . $total . ' alunos com dados do Censo completos',
            'link' => '/admin/reports/censo',
        ]);
    }

    /** Indicador: % de aulas "em dia" no diário (mês corrente). */
    private function indicadorDiario(): array
    {
        $base = $this->indicadorVazio('Diário de Classe', 'fa-book');
        if (!$this->tableExists('diario_aulas') || !$this->tableExists('grade_horaria')) {
            return $base;
        }
        try {
            $service = new ClassDiaryService();
            $inicio = date('Y-m-01');
            $fim = date('Y-m-d');
            $indicadores = $service->indicadores($inicio, $fim);
            $resumo = $service->resumoIndicadores($indicadores);
            $total = (int) $resumo['total'];
            if ($total === 0) {
                return $base + ['percentual' => null];
            }
            $pct = round(((int) $resumo['em_dia'] / $total) * 100, 1);
            return array_merge($base, [
                'percentual' => $pct,
                'cor' => self::cor($pct),
                'detalhe' => $resumo['em_dia'] . ' em dia · ' . $resumo['atencao'] . ' atenção · ' . $resumo['atraso'] . ' em atraso',
                'link' => '/admin/diario/indicadores',
            ]);
        } catch (Throwable $e) {
            return $base;
        }
    }

    /** Indicador: taxa global de presença no diário (aulas finalizadas). */
    private function indicadorFrequencia(): array
    {
        $base = $this->indicadorVazio('Frequência', 'fa-user-check');
        if (!$this->tableExists('diario_frequencias') || !$this->tableExists('diario_aulas')) {
            return $base;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE da.status = 'finalizada' AND da.data_aula >= ?",
            [date('Y-01-01')]
        );
        $total = (int) ($row['total'] ?? 0);
        if ($total === 0) {
            return $base + ['percentual' => null];
        }
        $pct = round(((int) ($row['presencas'] ?? 0) / $total) * 100, 1);
        return array_merge($base, [
            'percentual' => $pct,
            'cor' => self::cor($pct),
            'detalhe' => 'Presença média em ' . $total . ' registros de chamada no ano',
            'link' => '/admin/conformidade/pendencias?area=frequencia',
        ]);
    }

    /** Indicador: cobertura de habilidades BNCC (Fase 4 — opcional). */
    private function indicadorBncc(): array
    {
        $base = $this->indicadorVazio('BNCC', 'fa-list-check');
        $file = __DIR__ . '/BnccService.php';
        if (!is_file($file) || !$this->tableExists('bncc_habilidades')) {
            return $base;
        }
        require_once $file;
        try {
            $service = new BnccService($this->db);
            $cobertura = $service->coberturaGeral();
            if ($cobertura['percentual'] === null) {
                return $base + ['percentual' => null];
            }
            $pct = (float) $cobertura['percentual'];
            return array_merge($base, [
                'percentual' => $pct,
                'cor' => self::cor($pct),
                'detalhe' => $cobertura['trabalhadas'] . ' de ' . $cobertura['previstas'] . ' habilidades trabalhadas',
                'link' => '/admin/bncc',
            ]);
        } catch (Throwable $e) {
            return $base;
        }
    }

    /** Indicador: cumprimento de dias letivos (Fase 5 — opcional). */
    private function indicadorCalendario(): array
    {
        $base = $this->indicadorVazio('Calendário Letivo', 'fa-calendar-check');
        $file = __DIR__ . '/SchoolCalendarService.php';
        if (!is_file($file) || !$this->tableExists('calendario_letivo')) {
            return $base;
        }
        require_once $file;
        try {
            $service = new SchoolCalendarService($this->db);
            $status = $service->statusAnoVigente();
            if ($status === null || $status['percentual'] === null) {
                return $base + ['percentual' => null];
            }
            $pct = (float) $status['percentual'];
            return array_merge($base, [
                'percentual' => $pct,
                'cor' => self::cor($pct),
                'detalhe' => $status['dias_letivos'] . ' de ' . $status['dias_meta'] . ' dias letivos previstos',
                'link' => '/admin/calendario-letivo',
            ]);
        } catch (Throwable $e) {
            return $base;
        }
    }

    /**
     * @return array{label:string,icone:string,percentual:null,cor:string,detalhe:string,link:?string}
     */
    private function indicadorVazio(string $label, string $icone): array
    {
        return [
            'label' => $label,
            'icone' => $icone,
            'percentual' => null,
            'cor' => 'indisponivel',
            'detalhe' => 'Dados insuficientes ou módulo não configurado.',
            'link' => null,
        ];
    }

    private function contar(string $tabela, string $where = ''): int
    {
        if (!$this->tableExists($tabela)) {
            return 0;
        }
        $sql = "SELECT COUNT(*) AS n FROM `$tabela`";
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        try {
            $row = $this->db->fetch($sql);
            return (int) ($row['n'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function tableExists(string $tabela): bool
    {
        static $cache = [];
        if (array_key_exists($tabela, $cache)) {
            return $cache[$tabela];
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
                [$tabela]
            );
            $cache[$tabela] = !empty($row['ok']);
        } catch (Throwable $e) {
            $cache[$tabela] = false;
        }
        return $cache[$tabela];
    }

    /** @return array<string,bool> */
    private function colunas(string $tabela): array
    {
        static $cache = [];
        if (isset($cache[$tabela])) {
            return $cache[$tabela];
        }
        $cols = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ?",
                [$tabela]
            ) ?: [];
            foreach ($rows as $r) {
                $cols[(string) $r['COLUMN_NAME']] = true;
            }
        } catch (Throwable $e) {
            $cols = [];
        }
        $cache[$tabela] = $cols;
        return $cols;
    }
}
