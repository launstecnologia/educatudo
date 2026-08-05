<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Progresso do aluno em aulas e vídeos.
 */
class LessonProgress
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<string,mixed>|null */
    public function find(int $alunoId, int $aulaId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM ava_progresso_aula WHERE aluno_id = :a AND aula_id = :l",
            ['a' => $alunoId, 'l' => $aulaId]
        );
        return $row ?: null;
    }

    /** Mapa aula_id => progresso para uma lista de aulas. @return array<int,array<string,mixed>> */
    public function mapByLessons(int $alunoId, array $aulaIds): array
    {
        $aulaIds = array_values(array_filter(array_map('intval', $aulaIds)));
        if ($alunoId <= 0 || empty($aulaIds)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($aulaIds), '?'));
        $params = array_merge([$alunoId], $aulaIds);
        $rows = $this->db->fetchAll(
            "SELECT * FROM ava_progresso_aula WHERE aluno_id = ? AND aula_id IN ($in)",
            $params
        ) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['aula_id']] = $r;
        }
        return $map;
    }

    public function setStatus(int $alunoId, int $aulaId, string $status, float $percentual = 0.0): void
    {
        if (!in_array($status, ['nao_iniciada', 'em_andamento', 'concluida'], true)) {
            return;
        }
        $percentual = max(0, min(100, $percentual));
        $existing = $this->find($alunoId, $aulaId);
        $concluida = $status === 'concluida' ? 'NOW()' : 'NULL';
        if ($existing) {
            $this->db->update(
                "UPDATE ava_progresso_aula SET status = :s, percentual = :p, concluida_em = " . $concluida . ", updated_at = NOW()
                 WHERE id = :id",
                ['s' => $status, 'p' => $percentual, 'id' => $existing['id']]
            );
            return;
        }
        $this->db->insert(
            "INSERT INTO ava_progresso_aula (aluno_id, aula_id, status, percentual, concluida_em)
             VALUES (:a, :l, :s, :p, " . $concluida . ")",
            ['a' => $alunoId, 'l' => $aulaId, 's' => $status, 'p' => $percentual]
        );
    }

    /** Total de aulas e concluídas de uma disciplina para um aluno. @return array{total:int,concluidas:int} */
    public function disciplineStats(int $alunoId, int $disciplinaId): array
    {
        $total = $this->db->fetch(
            "SELECT COUNT(*) AS t FROM ava_aulas a
             INNER JOIN ava_modulos m ON m.id = a.modulo_id
             WHERE m.disciplina_id = :d",
            ['d' => $disciplinaId]
        );
        $concl = $this->db->fetch(
            "SELECT COUNT(*) AS t FROM ava_progresso_aula pa
             INNER JOIN ava_aulas a ON a.id = pa.aula_id
             INNER JOIN ava_modulos m ON m.id = a.modulo_id
             WHERE m.disciplina_id = :d AND pa.aluno_id = :a AND pa.status = 'concluida'",
            ['d' => $disciplinaId, 'a' => $alunoId]
        );
        return ['total' => (int) ($total['t'] ?? 0), 'concluidas' => (int) ($concl['t'] ?? 0)];
    }

    // ---- Progresso de vídeo ----------------------------------------------

    /** @return array<string,mixed>|null */
    public function findVideo(int $alunoId, int $aulaId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM ava_progresso_video WHERE aluno_id = :a AND aula_id = :l",
            ['a' => $alunoId, 'l' => $aulaId]
        );
        return $row ?: null;
    }

    public function saveVideo(int $alunoId, int $aulaId, int $segundoAtual, int $tempoAssistido, int $duracao): array
    {
        $duracao = max(0, $duracao);
        $pct = $duracao > 0 ? min(100, round(($segundoAtual / $duracao) * 100, 2)) : 0;
        $concluido = $pct >= 90 ? 1 : 0;
        $existing = $this->findVideo($alunoId, $aulaId);
        if ($existing) {
            $tempoAssistido = max((int) $existing['tempo_assistido_seg'], $tempoAssistido);
            $this->db->update(
                "UPDATE ava_progresso_video SET segundo_atual = :sa, tempo_assistido_seg = :ta, duracao_seg = :dur,
                    percentual = :p, concluido = :c, ultimo_acesso = NOW() WHERE id = :id",
                ['sa' => $segundoAtual, 'ta' => $tempoAssistido, 'dur' => $duracao, 'p' => $pct, 'c' => $concluido, 'id' => $existing['id']]
            );
        } else {
            $this->db->insert(
                "INSERT INTO ava_progresso_video (aluno_id, aula_id, segundo_atual, tempo_assistido_seg, duracao_seg, percentual, concluido)
                 VALUES (:a, :l, :sa, :ta, :dur, :p, :c)",
                ['a' => $alunoId, 'l' => $aulaId, 'sa' => $segundoAtual, 'ta' => $tempoAssistido, 'dur' => $duracao, 'p' => $pct, 'c' => $concluido]
            );
        }
        return ['percentual' => $pct, 'concluido' => $concluido];
    }
}
