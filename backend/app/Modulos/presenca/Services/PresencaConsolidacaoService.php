<?php

require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../../../Models/Education/ClassDiary.php';
require_once __DIR__ . '/../../../Models/Education/SchoolAbsence.php';
require_once __DIR__ . '/../Models/PresencaConfig.php';

/**
 * Recalcula o total em faltas_lancamentos (não soma +1) para eventos de origem=diario.
 */
class PresencaConsolidacaoService
{
    private $db;
    private $configModel;
    private $diary;
    private $absence;

    public function __construct(
        ?Database $db = null,
        ?PresencaConfig $config = null,
        ?ClassDiary $diary = null,
        ?SchoolAbsence $absence = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->configModel = $config ?? new PresencaConfig();
        $this->diary = $diary ?? new ClassDiary();
        $this->absence = $absence ?? new SchoolAbsence();
    }

    public function consolidarAlunoNaData(int $alunoId, string $data): void
    {
        $cfg = $this->configModel->obter();
        if (empty($cfg['consolidar_boletim'])) {
            return;
        }
        $corte = $cfg['data_corte'] ?? null;
        if ($corte && $data < $corte) {
            return;
        }
        if (!$this->colunaOrigemEvento()) {
            return;
        }
        $ano = (int) date('Y', strtotime($data));
        $bimestre = $this->diary->bimestreDaData($data);
        $eventos = $this->eventosDiarioDoPeriodo($ano, $bimestre);
        foreach ($eventos as $evento) {
            $this->recalcularAlunoNoEvento($alunoId, (int) $evento['id'], $ano, $bimestre, $corte);
        }
    }

    /**
     * Recalcula todos os alunos dos eventos origem=diario (cron).
     */
    public function consolidarPendentes(?string $dataRef = null): int
    {
        $cfg = $this->configModel->obter();
        if (empty($cfg['consolidar_boletim']) || !$this->colunaOrigemEvento()) {
            return 0;
        }
        $dataRef = $dataRef ?: date('Y-m-d');
        $ano = (int) date('Y', strtotime($dataRef));
        $bimestre = $this->diary->bimestreDaData($dataRef);
        $corte = $cfg['data_corte'] ?? null;
        $n = 0;
        foreach ($this->eventosDiarioDoPeriodo($ano, $bimestre) as $evento) {
            $alunos = $this->alunosDoEvento((int) $evento['id']);
            foreach ($alunos as $alunoId) {
                $this->recalcularAlunoNoEvento($alunoId, (int) $evento['id'], $ano, $bimestre, $corte);
                $n++;
            }
        }
        return $n;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function eventosDiarioDoPeriodo(int $ano, int $bimestre): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, bimestre, ano_letivo, turmas_json, materias_json
             FROM faltas_eventos
             WHERE ativo = 1 AND origem = 'diario' AND ano_letivo = :ano",
            ['ano' => $ano]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            if ($this->bimestreNumero((string) $row['bimestre']) === $bimestre) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private function recalcularAlunoNoEvento(int $alunoId, int $eventoId, int $ano, int $bimestre, ?string $corte): void
    {
        $evento = $this->absence->getEventoById($eventoId);
        if (!$evento) {
            return;
        }
        $periodo = $this->diary->periodoDoBimestre($ano, $bimestre);
        $inicio = $corte && $corte > $periodo['inicio'] ? $corte : $periodo['inicio'];
        $fim = $periodo['fim'];
        $turmas = array_map('intval', (array) ($evento['turmas_ids'] ?? []));
        $materias = array_map('intval', (array) ($evento['materias_ids'] ?? []));

        $params = ['aluno_id' => $alunoId, 'inicio' => $inicio, 'fim' => $fim];
        $sql = "SELECT da.materia_id, COUNT(*) AS faltas
                FROM diario_frequencias df
                INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
                WHERE df.aluno_id = :aluno_id
                  AND df.situacao = 'falta'
                  AND da.status <> 'cancelada'
                  AND da.data_aula BETWEEN :inicio AND :fim";
        if ($turmas !== []) {
            $ph = [];
            foreach ($turmas as $i => $tid) {
                $k = 't' . $i;
                $ph[] = ':' . $k;
                $params[$k] = $tid;
            }
            $sql .= ' AND da.turma_id IN (' . implode(',', $ph) . ')';
        }
        if ($materias !== []) {
            $ph = [];
            foreach ($materias as $i => $mid) {
                $k = 'm' . $i;
                $ph[] = ':' . $k;
                $params[$k] = $mid;
            }
            $sql .= ' AND da.materia_id IN (' . implode(',', $ph) . ')';
        }
        $sql .= ' GROUP BY da.materia_id';
        $rows = $this->db->fetchAll($sql, $params) ?: [];

        $porMateria = [];
        foreach ($rows as $r) {
            $mid = (int) ($r['materia_id'] ?? 0);
            if ($mid > 0) {
                $porMateria[$mid] = (string) ((int) ($r['faltas'] ?? 0));
            }
        }
        $existentes = $this->db->fetchAll(
            'SELECT materia_id FROM faltas_lancamentos WHERE evento_id = :evento AND aluno_id = :aluno',
            ['evento' => $eventoId, 'aluno' => $alunoId]
        ) ?: [];
        foreach ($existentes as $ex) {
            $mid = (int) ($ex['materia_id'] ?? 0);
            if ($mid > 0 && !isset($porMateria[$mid])) {
                $porMateria[$mid] = '0';
            }
        }
        if ($porMateria === []) {
            return;
        }
        $this->absence->upsertLancamentos($eventoId, [$alunoId => $porMateria], [], null);
    }

    /**
     * @return list<int>
     */
    private function alunosDoEvento(int $eventoId): array
    {
        $evento = $this->absence->getEventoById($eventoId);
        if (!$evento) {
            return [];
        }
        $alunos = $this->absence->listAlunosByTurmas((array) ($evento['turmas_ids'] ?? []), (int) ($evento['ano_letivo'] ?? 0));
        $ids = [];
        foreach ($alunos as $a) {
            $id = (int) ($a['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public function bimestreNumero(string $bimestre): int
    {
        if (preg_match('/(\d)/', $bimestre, $m)) {
            $n = (int) $m[1];
            return max(1, min(4, $n));
        }
        return 0;
    }

    private function colunaOrigemEvento(): bool
    {
        if (!$this->db->tableExists('faltas_eventos')) {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT 1 AS ok FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faltas_eventos' AND COLUMN_NAME = 'origem' LIMIT 1"
        );
        return (bool) $row;
    }
}
