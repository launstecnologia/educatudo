<?php

namespace App\Services;

use Database;

/**
 * Calcula o score de propensão à rematrícula por aluno.
 * Fórmula determinística e explicável (spec §9.2).
 */
class EnrollmentScoreService
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function calcular(int $alunoId, int $ciclo, ?int $anoLetivoId = null): array
    {
        $sinais  = $this->coletarSinais($alunoId, $anoLetivoId);
        $score   = $this->computarScore($sinais);
        $faixa   = $this->faixa($score);
        $motivos = $this->motivos($sinais);

        $this->persistir($alunoId, $ciclo, $score, $faixa, $sinais, $motivos);

        return compact('score', 'faixa', 'motivos', 'sinais');
    }

    public function calcularLote(array $alunoIds, int $ciclo, ?int $anoLetivoId = null): array
    {
        $result = [];
        foreach ($alunoIds as $id) {
            $result[$id] = $this->calcular((int) $id, $ciclo, $anoLetivoId);
        }
        return $result;
    }

    public function buscarScore(int $alunoId, int $ciclo): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM enrollment_score WHERE aluno_id = ? AND ciclo = ?",
            [$alunoId, $ciclo]
        ) ?: null;
    }

    public function listarPorFaixa(int $ciclo, string $faixa = ''): array
    {
        $where = 's.ciclo = ?';
        $p     = [$ciclo];
        if ($faixa) { $where .= ' AND s.faixa = ?'; $p[] = $faixa; }

        return $this->db->fetchAll(
            "SELECT s.*, a.nome AS aluno_nome, t.nome AS turma_nome, t.serie AS turma_serie
             FROM enrollment_score s
             INNER JOIN alunos a ON a.id = s.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE {$where}
             ORDER BY s.score ASC",
            $p
        ) ?: [];
    }

    // ── Coleta sinais já existentes na plataforma ─────────────────────────────

    private function coletarSinais(int $alunoId, ?int $anoLetivoId): array
    {
        // 1. Frequência — diário de classe
        $freq = $this->calcularFrequencia($alunoId, $anoLetivoId);

        // 2. Desempenho — média de notas/provas
        $desemp = $this->calcularDesempenho($alunoId, $anoLetivoId);

        // 3. Inadimplência — não há KSI; aproximamos com campo `status` do aluno ou ausência
        $atrasos = $this->calcularAtrasos($alunoId);

        // 4. Engajamento — uso da plataforma (logins / atividades)
        $engaj = $this->calcularEngajamento($alunoId, $anoLetivoId);

        // 5. Tempo na escola — matrículas anteriores
        $anosNaEscola = (int) ($this->db->fetch(
            "SELECT COUNT(DISTINCT ano_letivo_id) AS total FROM matricula WHERE aluno_id = ?",
            [$alunoId]
        )['total'] ?? 1);

        return compact('freq', 'desemp', 'atrasos', 'engaj', 'anosNaEscola');
    }

    private function calcularFrequencia(int $alunoId, ?int $anoLetivoId): float
    {
        // Busca frequência do diário de classe
        $row = $this->db->fetch(
            "SELECT
               SUM(CASE WHEN presenca = 'presente' THEN 1 ELSE 0 END) AS presentes,
               COUNT(*) AS total
             FROM lista_chamada
             WHERE aluno_id = ?" . ($anoLetivoId ? " AND ano_letivo_id = ?" : ""),
            $anoLetivoId ? [$alunoId, $anoLetivoId] : [$alunoId]
        );
        if (!$row || (int)($row['total'] ?? 0) === 0) return 85.0; // default otimista sem dados
        return round((int)$row['presentes'] / (int)$row['total'] * 100, 1);
    }

    private function calcularDesempenho(int $alunoId, ?int $anoLetivoId): float
    {
        // Tenta buscar média de notas em provas online
        $row = $this->db->fetch(
            "SELECT AVG(nota) AS media
             FROM exam_results
             WHERE aluno_id = ?" . ($anoLetivoId ? " AND ano_letivo_id = ?" : ""),
            $anoLetivoId ? [$alunoId, $anoLetivoId] : [$alunoId]
        );
        $media = $row ? (float)($row['media'] ?? 0) : 0;
        if ($media > 0) {
            // Normaliza para 0-100 (se notas em escala 0-10)
            return $media > 10 ? $media : $media * 10;
        }
        return 75.0; // default sem dados
    }

    private function calcularAtrasos(int $alunoId): int
    {
        // Sem KSI, usa ocorrências financeiras ou campo inadimplente se existir
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM ocorrencias
             WHERE aluno_id = ? AND tipo LIKE '%inadim%'",
            [$alunoId]
        );
        return (int)($row['total'] ?? 0);
    }

    private function calcularEngajamento(int $alunoId, ?int $anoLetivoId): float
    {
        // Tenta usar atividades no AVA ou Quest como proxy de engajamento
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM ava_activity_submissions WHERE aluno_id = ?",
            [$alunoId]
        );
        $atividades = (int)($row['total'] ?? 0);
        // Normaliza: 20+ atividades = 100, 0 = 10
        return min(100, max(10, $atividades * 5));
    }

    // ── Fórmula do spec §9.2 ─────────────────────────────────────────────────

    private function computarScore(array $s): int
    {
        $freq_n   = (float) $s['freq'];
        $desemp_n = (float) $s['desemp'];
        $inad_n   = max(0, 100 - $s['atrasos'] * 22);
        $engaj_n  = (float) $s['engaj'];
        $tempo_n  = min(100, 55 + $s['anosNaEscola'] * 9);

        return (int) round(
            $freq_n   * 0.28 +
            $desemp_n * 0.22 +
            $inad_n   * 0.24 +
            $engaj_n  * 0.18 +
            $tempo_n  * 0.08
        );
    }

    private function faixa(int $score): string
    {
        if ($score >= 78) return 'verde';
        if ($score >= 58) return 'amarelo';
        return 'vermelho';
    }

    private function motivos(array $s): array
    {
        $m = [];
        if ($s['freq'] < 75)    $m[] = 'queda de frequência';
        if ($s['desemp'] < 65)  $m[] = 'queda de desempenho';
        if ($s['atrasos'] >= 2) $m[] = 'inadimplência recorrente';
        if ($s['engaj'] < 45)   $m[] = 'baixo engajamento';
        return $m ?: ['sem sinais de risco'];
    }

    // ── Persistência ─────────────────────────────────────────────────────────

    private function persistir(int $alunoId, int $ciclo, int $score, string $faixa, array $sinais, array $motivos): void
    {
        $motivosJson = implode(', ', $motivos);
        $existing = $this->db->fetch(
            "SELECT id FROM enrollment_score WHERE aluno_id = ? AND ciclo = ?",
            [$alunoId, $ciclo]
        );
        if ($existing) {
            $this->db->update(
                "UPDATE enrollment_score SET score=?, faixa=?, freq_n=?, desemp_n=?, inad_n=?, engaj_n=?, tempo_n=?, motivos=?, calculado_em=NOW()
                 WHERE aluno_id = ? AND ciclo = ?",
                [
                    $score, $faixa,
                    (int)$sinais['freq'], (int)$sinais['desemp'],
                    (int)max(0, 100 - $sinais['atrasos'] * 22),
                    (int)$sinais['engaj'],
                    (int)min(100, 55 + $sinais['anosNaEscola'] * 9),
                    $motivosJson, $alunoId, $ciclo,
                ]
            );
        } else {
            $this->db->insert(
                "INSERT INTO enrollment_score (aluno_id, ciclo, score, faixa, freq_n, desemp_n, inad_n, engaj_n, tempo_n, motivos)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $alunoId, $ciclo, $score, $faixa,
                    (int)$sinais['freq'], (int)$sinais['desemp'],
                    (int)max(0, 100 - $sinais['atrasos'] * 22),
                    (int)$sinais['engaj'],
                    (int)min(100, 55 + $sinais['anosNaEscola'] * 9),
                    $motivosJson,
                ]
            );
        }
    }
}
