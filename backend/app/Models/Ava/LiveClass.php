<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Aula ao vivo vinculada a uma disciplina.
 */
class LiveClass
{
    private $db;

    public const PLATAFORMAS = [
        'jitsi' => 'Sala EducaTudo (Jitsi)',
        'panda' => 'Panda Video Live',
        'externo' => 'Link externo (Meet/Zoom/YouTube)',
    ];

    public const STATUS = [
        'agendada' => 'Agendada',
        'ao_vivo' => 'Ao vivo',
        'encerrada' => 'Encerrada',
        'cancelada' => 'Cancelada',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function byDiscipline(int $disciplinaId): array
    {
        if ($disciplinaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT l.*, m.titulo AS modulo_titulo
             FROM ava_aulas_ao_vivo l
             LEFT JOIN ava_modulos m ON m.id = l.modulo_id
             WHERE l.disciplina_id = :d
             ORDER BY (l.inicio_em IS NULL), l.inicio_em DESC, l.id DESC",
            ['d' => $disciplinaId]
        ) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT l.*, d.nome AS disciplina_nome, d.curso_id, d.professor_id AS disc_professor_id, d.tutor_id AS disc_tutor_id
             FROM ava_aulas_ao_vivo l
             INNER JOIN ava_disciplinas d ON d.id = l.disciplina_id
             WHERE l.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $id = null): int
    {
        $plataforma = isset(self::PLATAFORMAS[$data['plataforma'] ?? '']) ? $data['plataforma'] : 'jitsi';
        $status = isset(self::STATUS[$data['status'] ?? '']) ? $data['status'] : 'agendada';
        $params = [
            'disciplina_id' => (int) ($data['disciplina_id'] ?? 0),
            'modulo_id' => (int) ($data['modulo_id'] ?? 0) ?: null,
            'professor_id' => (int) ($data['professor_id'] ?? 0) ?: null,
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
            'plataforma' => $plataforma,
            'link_externo' => trim((string) ($data['link_externo'] ?? '')) ?: null,
            'inicio_em' => $this->normalizeDate($data['inicio_em'] ?? null),
            'fim_em' => $this->normalizeDate($data['fim_em'] ?? null),
            'status' => $status,
        ];
        if ($id !== null && $id > 0) {
            $params['id'] = $id;
            $this->db->update(
                "UPDATE ava_aulas_ao_vivo SET modulo_id=:modulo_id, titulo=:titulo, descricao=:descricao,
                    plataforma=:plataforma, link_externo=:link_externo, inicio_em=:inicio_em, fim_em=:fim_em,
                    status=:status, updated_at=NOW()
                 WHERE id=:id",
                $params
            );
            return $id;
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_aulas_ao_vivo (disciplina_id, modulo_id, professor_id, titulo, descricao, plataforma,
                link_externo, inicio_em, fim_em, status)
             VALUES (:disciplina_id, :modulo_id, :professor_id, :titulo, :descricao, :plataforma, :link_externo,
                :inicio_em, :fim_em, :status)",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->query("DELETE FROM ava_aulas_ao_vivo WHERE id = :id", ['id' => $id]);
        }
    }

    public function setStatus(int $id, string $status): void
    {
        if ($id > 0 && isset(self::STATUS[$status])) {
            $this->db->update("UPDATE ava_aulas_ao_vivo SET status = :s, updated_at = NOW() WHERE id = :id", ['s' => $status, 'id' => $id]);
        }
    }

    /** Persiste dados da integração Panda (criação da live). */
    public function setPandaLive(int $id, string $liveId, string $player): void
    {
        $this->db->update(
            "UPDATE ava_aulas_ao_vivo SET panda_live_id = :lid, panda_live_player = :p, updated_at = NOW() WHERE id = :id",
            ['lid' => $liveId ?: null, 'p' => $player ?: null, 'id' => $id]
        );
    }

    /** Persiste a gravação Panda obtida após o encerramento. */
    public function setPandaRecording(int $id, string $player, string $hls): void
    {
        $this->db->update(
            "UPDATE ava_aulas_ao_vivo SET panda_recording_player = :p, panda_recording_hls = :h,
                panda_recording_synced_at = NOW(), updated_at = NOW() WHERE id = :id",
            ['p' => $player ?: null, 'h' => $hls ?: null, 'id' => $id]
        );
    }

    public function setRecordingUrl(int $id, ?string $url): void
    {
        $this->db->update(
            "UPDATE ava_aulas_ao_vivo SET gravacao_url = :u, updated_at = NOW() WHERE id = :id",
            ['u' => $url ?: null, 'id' => $id]
        );
    }

    private function normalizeDate($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
