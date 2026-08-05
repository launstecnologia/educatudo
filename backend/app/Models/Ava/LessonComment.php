<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Comentários / dúvidas por aula (threads de 1 nível).
 */
class LessonComment
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Comentários de uma aula em formato de árvore (raiz + respostas).
     * @return list<array<string,mixed>>
     */
    public function byLesson(int $aulaId): array
    {
        if ($aulaId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT * FROM ava_comentarios WHERE aula_id = :a AND removido = 0
             ORDER BY fixado DESC, created_at ASC",
            ['a' => $aulaId]
        ) ?: [];

        $raiz = [];
        $porPai = [];
        foreach ($rows as $r) {
            if (empty($r['parent_id'])) {
                $r['respostas'] = [];
                $raiz[(int) $r['id']] = $r;
            } else {
                $porPai[(int) $r['parent_id']][] = $r;
            }
        }
        foreach ($porPai as $paiId => $respostas) {
            if (isset($raiz[$paiId])) {
                $raiz[$paiId]['respostas'] = $respostas;
            }
        }
        return array_values($raiz);
    }

    public function countByLesson(int $aulaId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS t FROM ava_comentarios WHERE aula_id = :a AND removido = 0",
            ['a' => $aulaId]
        );
        return (int) ($row['t'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM ava_comentarios WHERE id = :id", ['id' => $id]);
        return $row ?: null;
    }

    public function create(int $aulaId, string $autorTipo, int $autorId, string $autorNome, string $conteudo, ?int $parentId = null): int
    {
        $autorTipo = in_array($autorTipo, ['aluno', 'professor'], true) ? $autorTipo : 'aluno';
        return (int) $this->db->insert(
            "INSERT INTO ava_comentarios (aula_id, parent_id, autor_tipo, autor_id, autor_nome, conteudo)
             VALUES (:aula, :parent, :tipo, :aid, :nome, :conteudo)",
            [
                'aula' => $aulaId,
                'parent' => $parentId ?: null,
                'tipo' => $autorTipo,
                'aid' => $autorId,
                'nome' => substr($autorNome, 0, 255),
                'conteudo' => $conteudo,
            ]
        );
    }

    public function softDelete(int $id): void
    {
        if ($id > 0) {
            $this->db->update("UPDATE ava_comentarios SET removido = 1, updated_at = NOW() WHERE id = :id", ['id' => $id]);
        }
    }

    public function togglePin(int $id, bool $fixar): void
    {
        if ($id > 0) {
            $this->db->update("UPDATE ava_comentarios SET fixado = :f, updated_at = NOW() WHERE id = :id", ['f' => $fixar ? 1 : 0, 'id' => $id]);
        }
    }
}
