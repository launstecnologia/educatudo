<?php

namespace App\Modulos\DashboardGestao\Services;

use Database;

/**
 * Recorte Escola → Curso → Série → Turma (+ ano, bimestre, turno).
 * Resolve IDs de turma uma vez; os widgets só consomem o recorte.
 */
class DashboardFiltro
{
    public int $anoLetivoId = 0;
    public int $anoCivil = 0;
    public int $bimestre = 0;
    public int $cursoId = 0;
    public int $serieId = 0;
    public int $turmaId = 0;
    public string $turno = '';
    /** @var list<int> */
    public array $turmaIds = [];
    public bool $semTurmas = false;
    public string $inicio = '';
    public string $fim = '';
    public string $hoje = '';

    /**
     * @param array<string,mixed> $input
     */
    public static function deInput(array $input, Database $db): self
    {
        $f = new self();
        $f->hoje = date('Y-m-d');
        $f->bimestre = max(0, min(4, (int) ($input['bimestre'] ?? 0)));
        $f->cursoId = (int) ($input['curso_id'] ?? 0);
        $f->serieId = (int) ($input['serie_id'] ?? 0);
        $f->turmaId = (int) ($input['turma_id'] ?? 0);
        $turno = strtolower(trim((string) ($input['turno'] ?? '')));
        $f->turno = in_array($turno, ['manha', 'tarde', 'noite', 'integral'], true) ? $turno : '';

        $anos = self::listarAnosLetivos($db);
        $f->anoLetivoId = (int) ($input['ano_letivo_id'] ?? 0);
        if ($f->anoLetivoId <= 0) {
            $f->anoLetivoId = self::anoLetivoPadraoId($anos);
        }
        $f->anoCivil = self::anoCivilDe($anos, $f->anoLetivoId);
        if ($f->anoCivil <= 0) {
            $f->anoCivil = (int) date('Y');
        }
        if ($f->bimestre <= 0) {
            $f->bimestre = (int) ceil(((int) date('n')) / 3);
        }

        $periodo = self::periodoDoBimestre($f->anoCivil, $f->bimestre);
        $f->inicio = $periodo['inicio'];
        $f->fim = $periodo['fim'];

        $f->turmaIds = self::resolverTurmaIds($db, $f);
        $f->semTurmas = $f->turmaIds === [];

        return $f;
    }

    /**
     * @return array{inicio:string,fim:string}
     */
    public static function periodoDoBimestre(int $anoLetivo, int $bimestre): array
    {
        require_once dirname(__DIR__, 2) . '/diario/Models/ClassDiary.php';
        $diary = new \App\Modulos\Diario\Models\ClassDiary();
        return $diary->periodoDoBimestre($anoLetivo, $bimestre);
    }

    /**
     * @return list<array{id:int,ano:int,ativo:int}>
     */
    public static function listarAnosLetivos(Database $db): array
    {
        try {
            if ($db->fetch("SHOW TABLES LIKE 'ano_letivo'") === false) {
                return [];
            }
            $rows = $db->fetchAll(
                'SELECT id, ano, ativo FROM ano_letivo ORDER BY ano DESC'
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'ano' => (int) ($row['ano'] ?? 0),
                'ativo' => (int) ($row['ativo'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @param list<array{id:int,ano:int,ativo:int}> $anos
     */
    private static function anoLetivoPadraoId(array $anos): int
    {
        $anoAtual = (int) date('Y');
        foreach ($anos as $a) {
            if ((int) $a['ano'] === $anoAtual && (int) $a['ativo'] === 1) {
                return (int) $a['id'];
            }
        }
        foreach ($anos as $a) {
            if ((int) $a['ativo'] === 1) {
                return (int) $a['id'];
            }
        }
        return isset($anos[0]) ? (int) $anos[0]['id'] : 0;
    }

    /**
     * @param list<array{id:int,ano:int,ativo:int}> $anos
     */
    private static function anoCivilDe(array $anos, int $id): int
    {
        foreach ($anos as $a) {
            if ((int) $a['id'] === $id) {
                return (int) $a['ano'];
            }
        }
        return 0;
    }

    /**
     * @return list<int>
     */
    private static function resolverTurmaIds(Database $db, self $f): array
    {
        $where = ['t.ativo = 1'];
        $params = [];

        if ($f->turmaId > 0) {
            $where[] = 't.id = :turma_id';
            $params['turma_id'] = $f->turmaId;
        }
        if ($f->anoLetivoId > 0 && self::colunaTurma($db, 'ano_letivo_id')) {
            $where[] = '(t.ano_letivo_id = :ano_letivo_id OR (t.ano_letivo_id IS NULL AND t.ano_letivo = :ano_civil))';
            $params['ano_letivo_id'] = $f->anoLetivoId;
            $params['ano_civil'] = $f->anoCivil;
        } elseif ($f->anoCivil > 0) {
            $where[] = 't.ano_letivo = :ano_civil';
            $params['ano_civil'] = $f->anoCivil;
        }
        if ($f->cursoId > 0) {
            if (self::colunaTurma($db, 'curso_novo_id')) {
                $where[] = '(t.curso_novo_id = :curso_id OR t.curso_id = :curso_id2)';
                $params['curso_id'] = $f->cursoId;
                $params['curso_id2'] = $f->cursoId;
            } else {
                $where[] = 't.curso_id = :curso_id';
                $params['curso_id'] = $f->cursoId;
            }
        }
        if ($f->serieId > 0 && self::colunaTurma($db, 'serie_id')) {
            $where[] = 't.serie_id = :serie_id';
            $params['serie_id'] = $f->serieId;
        }
        if ($f->turno !== '' && self::colunaTurma($db, 'turno')) {
            $where[] = 't.turno = :turno';
            $params['turno'] = $f->turno;
        }

        $sql = 'SELECT t.id FROM turmas t WHERE ' . implode(' AND ', $where);
        try {
            $rows = $db->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    public static function colunaTurma(Database $db, string $coluna): bool
    {
        static $cache = [];
        if (isset($cache[$coluna])) {
            return $cache[$coluna];
        }
        if (!preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            $cache[$coluna] = false;
            return false;
        }
        try {
            $row = $db->fetch("SHOW COLUMNS FROM turmas LIKE '{$coluna}'");
            $cache[$coluna] = $row !== false && $row !== null;
        } catch (\Throwable $e) {
            $cache[$coluna] = false;
        }
        return $cache[$coluna];
    }

    /**
     * Query string para links dos módulos de origem.
     *
     * @return array<string,scalar>
     */
    public function queryModulo(): array
    {
        $q = [
            'ano_letivo' => $this->anoCivil,
            'ano_letivo_id' => $this->anoLetivoId,
            'bimestre' => $this->bimestre,
        ];
        if ($this->cursoId > 0) {
            $q['curso_id'] = $this->cursoId;
        }
        if ($this->serieId > 0) {
            $q['serie_id'] = $this->serieId;
        }
        if ($this->turmaId > 0) {
            $q['turma_id'] = $this->turmaId;
        }
        if ($this->turno !== '') {
            $q['turno'] = $this->turno;
        }
        return $q;
    }
}
