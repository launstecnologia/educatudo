<?php

if (!class_exists('LaudoAluno')) {
class LaudoAluno
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM laudos_alunos WHERE id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorMascara(int $mascaraId): array
    {
        if ($mascaraId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT id, mascara_id, caminho_arquivo, nome_original, tipo_mime, hash_arquivo, algoritmo_cripto, created_at, enviado_por
             FROM laudos_alunos
             WHERE mascara_id = :id
             ORDER BY id DESC",
            ['id' => $mascaraId]
        );
    }

    public function create(array $data): int
    {
        $id = $this->db->insert(
            "INSERT INTO laudos_alunos
             (mascara_id, caminho_arquivo, hash_arquivo, tipo_mime, nome_original, algoritmo_cripto, enviado_por, created_at)
             VALUES (:mascara_id, :caminho_arquivo, :hash_arquivo, :tipo_mime, :nome_original, :algoritmo_cripto, :enviado_por, NOW())",
            [
                'mascara_id' => (int) $data['mascara_id'],
                'caminho_arquivo' => (string) $data['caminho_arquivo'],
                'hash_arquivo' => (string) $data['hash_arquivo'],
                'tipo_mime' => (string) $data['tipo_mime'],
                'nome_original' => $data['nome_original'] ?? null,
                'algoritmo_cripto' => $data['algoritmo_cripto'] ?? 'AES-256-GCM',
                'enviado_por' => $data['enviado_por'] ?? null,
            ]
        );
        return (int) $id;
    }
}
}
