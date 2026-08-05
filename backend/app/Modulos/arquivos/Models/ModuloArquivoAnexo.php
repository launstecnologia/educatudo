<?php
/**
 * Model — anexos do módulo Arquivos (tabela modulos_arquivos_anexos).
 */

if (!class_exists('ModuloArquivoAnexo')) {
class ModuloArquivoAnexo
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT aa.*, aa.modulo_arquivo_id FROM modulos_arquivos_anexos aa WHERE aa.id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function listByModuloArquivo(int $moduloArquivoId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id ORDER BY ordem',
            ['id' => $moduloArquivoId]
        ) ?: [];
    }

    public function listCaminhosByModuloArquivo(int $moduloArquivoId): array
    {
        return $this->db->fetchAll(
            'SELECT caminho FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id',
            ['id' => $moduloArquivoId]
        ) ?: [];
    }

    public function listIdsCaminhosByModuloArquivo(int $moduloArquivoId): array
    {
        return $this->db->fetchAll(
            'SELECT id, caminho FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id',
            ['id' => $moduloArquivoId]
        ) ?: [];
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO modulos_arquivos_anexos
                (modulo_arquivo_id, caminho, nome_original, extensao, tamanho, ordem)
             VALUES
                (:ma_id, :caminho, :nome_original, :extensao, :tamanho, :ordem)',
            [
                'ma_id' => (int) $data['modulo_arquivo_id'],
                'caminho' => (string) $data['caminho'],
                'nome_original' => (string) $data['nome_original'],
                'extensao' => (string) $data['extensao'],
                'tamanho' => (int) ($data['tamanho'] ?? 0),
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->query('DELETE FROM modulos_arquivos_anexos WHERE id = :aid', ['aid' => $id]);
    }
}
}
