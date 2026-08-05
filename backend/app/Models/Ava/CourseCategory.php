<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Categoria de curso.
 */
class CourseCategory
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM ava_categorias ORDER BY nome ASC") ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM ava_categorias WHERE id = :id", ['id' => $id]);
        return $row ?: null;
    }

    public function save(string $nome, ?string $descricao = null, ?int $id = null): int
    {
        $slug = $this->slugify($nome);
        if ($id !== null && $id > 0) {
            $this->db->update(
                "UPDATE ava_categorias SET nome = :nome, slug = :slug, descricao = :descricao, updated_at = NOW() WHERE id = :id",
                ['nome' => $nome, 'slug' => $slug, 'descricao' => $descricao, 'id' => $id]
            );
            return $id;
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_categorias (nome, slug, descricao) VALUES (:nome, :slug, :descricao)",
            ['nome' => $nome, 'slug' => $slug, 'descricao' => $descricao]
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->query("DELETE FROM ava_categorias WHERE id = :id", ['id' => $id]);
        }
    }

    private function slugify(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = preg_replace('/[^a-z0-9]+/u', '-', $texto) ?? '';
        return trim($texto, '-');
    }
}
