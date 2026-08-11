<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Aula (conteúdo) + anexos.
 */
class Lesson
{
    private $db;

    public const TIPOS = [
        'video' => 'Vídeo',
        'texto' => 'Texto / Artigo',
        'pdf' => 'PDF / Material',
        'apresentacao' => 'Apresentação',
        'audio' => 'Áudio',
        'link' => 'Link externo',
        'html' => 'Conteúdo HTML',
        'quiz' => 'Quiz / Atividade',
    ];

    public const PROVIDERS = ['none', 'mp4', 'youtube', 'vimeo', 'bunny', 'cloudflare', 'panda'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function byModule(int $moduloId): array
    {
        if ($moduloId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM ava_aulas WHERE modulo_id = :m ORDER BY ordem ASC, id ASC",
            ['m' => $moduloId]
        ) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT a.*, m.disciplina_id, m.titulo AS modulo_titulo, d.curso_id, d.nome AS disciplina_nome
             FROM ava_aulas a
             INNER JOIN ava_modulos m ON m.id = a.modulo_id
             INNER JOIN ava_disciplinas d ON d.id = m.disciplina_id
             WHERE a.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $id = null): int
    {
        $tipo = isset(self::TIPOS[$data['tipo'] ?? '']) ? $data['tipo'] : 'video';
        $provider = in_array($data['video_provider'] ?? '', self::PROVIDERS, true) ? $data['video_provider'] : 'none';
        $params = [
            'modulo_id' => (int) ($data['modulo_id'] ?? 0),
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
            'professor_id' => (int) ($data['professor_id'] ?? 0) ?: null,
            'tipo' => $tipo,
            'conteudo_html' => $data['conteudo_html'] ?? null,
            'video_provider' => $provider,
            'video_ref' => trim((string) ($data['video_ref'] ?? '')) ?: null,
            'duracao_seg' => (int) ($data['duracao_seg'] ?? 0),
            'imagem_key' => $data['imagem_key'] ?? null,
            'tempo_estimado_min' => (int) ($data['tempo_estimado_min'] ?? 0),
            'data_liberacao' => $data['data_liberacao'] ?? null,
            'data_encerramento' => $data['data_encerramento'] ?? null,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'permite_download' => !empty($data['permite_download']) ? 1 : 0,
            'permite_comentarios' => !empty($data['permite_comentarios']) ? 1 : 0,
            'ordem' => (int) ($data['ordem'] ?? 0),
        ];
        if ($id !== null && $id > 0) {
            $params['id'] = $id;
            $this->db->update(
                "UPDATE ava_aulas SET titulo=:titulo, descricao=:descricao, professor_id=:professor_id, tipo=:tipo,
                    conteudo_html=:conteudo_html, video_provider=:video_provider, video_ref=:video_ref, duracao_seg=:duracao_seg,
                    imagem_key=:imagem_key, tempo_estimado_min=:tempo_estimado_min, data_liberacao=:data_liberacao,
                    data_encerramento=:data_encerramento, obrigatoria=:obrigatoria, permite_download=:permite_download,
                    permite_comentarios=:permite_comentarios, ordem=:ordem, updated_at=NOW()
                 WHERE id=:id",
                $params
            );
            return $id;
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_aulas (modulo_id, titulo, descricao, professor_id, tipo, conteudo_html, video_provider,
                video_ref, duracao_seg, imagem_key, tempo_estimado_min, data_liberacao, data_encerramento, obrigatoria,
                permite_download, permite_comentarios, ordem)
             VALUES (:modulo_id, :titulo, :descricao, :professor_id, :tipo, :conteudo_html, :video_provider, :video_ref,
                :duracao_seg, :imagem_key, :tempo_estimado_min, :data_liberacao, :data_encerramento, :obrigatoria,
                :permite_download, :permite_comentarios, :ordem)",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->query("DELETE FROM ava_aulas WHERE id = :id", ['id' => $id]);
        }
    }

    // ---- Anexos -----------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public function attachments(int $aulaId): array
    {
        if ($aulaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM ava_aula_anexos WHERE aula_id = :a ORDER BY id ASC",
            ['a' => $aulaId]
        ) ?: [];
    }

    /** @param array<string,mixed> $data */
    public function addAttachment(int $aulaId, array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO ava_aula_anexos (aula_id, tipo, arquivo_key, nome, mime, tamanho, url)
             VALUES (:a, :tipo, :key, :nome, :mime, :tam, :url)",
            [
                'a' => $aulaId,
                'tipo' => $data['tipo'] ?? 'arquivo',
                'key' => $data['arquivo_key'] ?? null,
                'nome' => $data['nome'] ?? null,
                'mime' => $data['mime'] ?? null,
                'tam' => isset($data['tamanho']) ? (int) $data['tamanho'] : null,
                'url' => $data['url'] ?? null,
            ]
        );
    }

    public function deleteAttachment(int $anexoId): void
    {
        if ($anexoId > 0) {
            $this->db->query("DELETE FROM ava_aula_anexos WHERE id = :id", ['id' => $anexoId]);
        }
    }

    /** @return array<string,mixed>|null */
    public function findAttachment(int $anexoId): ?array
    {
        if ($anexoId <= 0) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM ava_aula_anexos WHERE id = :id", ['id' => $anexoId]);
        return $row ?: null;
    }
}
