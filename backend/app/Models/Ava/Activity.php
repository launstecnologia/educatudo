<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Atividade / Tarefa com entrega.
 */
class Activity
{
    private $db;

    public const TIPOS = [
        'arquivo' => 'Envio de arquivo',
        'texto' => 'Texto / Redação online',
        'link' => 'Link / URL',
        'multiplo' => 'Arquivo + texto',
    ];

    public const STATUS = [
        'rascunho' => 'Rascunho',
        'publicada' => 'Publicada',
        'encerrada' => 'Encerrada',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function byDiscipline(int $disciplinaId, bool $somentePublicadas = false): array
    {
        if ($disciplinaId <= 0) {
            return [];
        }
        $where = $somentePublicadas ? " AND a.status = 'publicada'" : '';
        return $this->db->fetchAll(
            "SELECT a.*, m.titulo AS modulo_titulo,
                    (SELECT COUNT(*) FROM ava_atividade_entregas e WHERE e.atividade_id = a.id AND e.status IN ('enviada','avaliada','reenviar')) AS total_entregas,
                    (SELECT COUNT(*) FROM ava_atividade_entregas e WHERE e.atividade_id = a.id AND e.status = 'avaliada') AS total_avaliadas
             FROM ava_atividades a
             LEFT JOIN ava_modulos m ON m.id = a.modulo_id
             WHERE a.disciplina_id = :d" . $where . "
             ORDER BY (a.data_entrega IS NULL), a.data_entrega ASC, a.id DESC",
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
            "SELECT a.*, d.nome AS disciplina_nome, d.curso_id, m.titulo AS modulo_titulo,
                    r.titulo AS rubrica_titulo
             FROM ava_atividades a
             INNER JOIN ava_disciplinas d ON d.id = a.disciplina_id
             LEFT JOIN ava_modulos m ON m.id = a.modulo_id
             LEFT JOIN ava_rubricas r ON r.id = a.rubrica_id
             WHERE a.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $id = null): int
    {
        $tipo = isset(self::TIPOS[$data['tipo_entrega'] ?? '']) ? $data['tipo_entrega'] : 'arquivo';
        $status = isset(self::STATUS[$data['status'] ?? '']) ? $data['status'] : 'publicada';
        $params = [
            'disciplina_id' => (int) ($data['disciplina_id'] ?? 0),
            'modulo_id' => (int) ($data['modulo_id'] ?? 0) ?: null,
            'aula_id' => (int) ($data['aula_id'] ?? 0) ?: null,
            'professor_id' => (int) ($data['professor_id'] ?? 0) ?: null,
            'rubrica_id' => (int) ($data['rubrica_id'] ?? 0) ?: null,
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
            'instrucoes' => $data['instrucoes'] ?? null,
            'tipo_entrega' => $tipo,
            'peso' => (float) ($data['peso'] ?? 1),
            'nota_maxima' => (float) ($data['nota_maxima'] ?? 10),
            'data_abertura' => $this->normalizeDate($data['data_abertura'] ?? null),
            'data_entrega' => $this->normalizeDate($data['data_entrega'] ?? null),
            'aceita_atraso' => !empty($data['aceita_atraso']) ? 1 : 0,
            'permite_reenvio' => !empty($data['permite_reenvio']) ? 1 : 0,
            'max_arquivos' => max(1, (int) ($data['max_arquivos'] ?? 5)),
            'tamanho_max_mb' => max(1, (int) ($data['tamanho_max_mb'] ?? 20)),
            'status' => $status,
        ];
        if ($id !== null && $id > 0) {
            $params['id'] = $id;
            $this->db->update(
                "UPDATE ava_atividades SET modulo_id=:modulo_id, aula_id=:aula_id, rubrica_id=:rubrica_id,
                    titulo=:titulo, descricao=:descricao, instrucoes=:instrucoes, tipo_entrega=:tipo_entrega,
                    peso=:peso, nota_maxima=:nota_maxima, data_abertura=:data_abertura, data_entrega=:data_entrega,
                    aceita_atraso=:aceita_atraso, permite_reenvio=:permite_reenvio, max_arquivos=:max_arquivos,
                    tamanho_max_mb=:tamanho_max_mb, status=:status, updated_at=NOW()
                 WHERE id=:id",
                $params
            );
            return $id;
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_atividades (disciplina_id, modulo_id, aula_id, professor_id, rubrica_id, titulo, descricao,
                instrucoes, tipo_entrega, peso, nota_maxima, data_abertura, data_entrega, aceita_atraso, permite_reenvio,
                max_arquivos, tamanho_max_mb, status)
             VALUES (:disciplina_id, :modulo_id, :aula_id, :professor_id, :rubrica_id, :titulo, :descricao, :instrucoes,
                :tipo_entrega, :peso, :nota_maxima, :data_abertura, :data_entrega, :aceita_atraso, :permite_reenvio,
                :max_arquivos, :tamanho_max_mb, :status)",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->query("DELETE FROM ava_atividades WHERE id = :id", ['id' => $id]);
        }
    }

    /** Atividades publicadas de uma aula (para exibir no player). @return list<array<string,mixed>> */
    public function byLesson(int $aulaId): array
    {
        if ($aulaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM ava_atividades WHERE aula_id = :a AND status = 'publicada' ORDER BY id DESC",
            ['a' => $aulaId]
        ) ?: [];
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
