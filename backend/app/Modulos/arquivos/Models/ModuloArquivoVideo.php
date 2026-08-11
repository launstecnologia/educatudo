<?php
/**
 * Model — vídeos embutidos do módulo Arquivos (tabela modulos_arquivos_videos).
 */

if (!class_exists('ModuloArquivoVideo')) {
class ModuloArquivoVideo
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listByModuloArquivo(int $moduloArquivoId): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT * FROM modulos_arquivos_videos WHERE modulo_arquivo_id = :id ORDER BY ordem',
                ['id' => $moduloArquivoId]
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function create(int $moduloArquivoId, string $url, int $ordem = 0): int
    {
        return (int) $this->db->insert(
            'INSERT INTO modulos_arquivos_videos (modulo_arquivo_id, url, titulo, ordem)
             VALUES (:ma_id, :url, NULL, :ordem)',
            [
                'ma_id' => $moduloArquivoId,
                'url' => $url,
                'ordem' => $ordem,
            ]
        );
    }

    public function deleteByModuloArquivo(int $moduloArquivoId): void
    {
        try {
            $this->db->query(
                'DELETE FROM modulos_arquivos_videos WHERE modulo_arquivo_id = :id',
                ['id' => $moduloArquivoId]
            );
        } catch (Throwable $e) {
            // tabela pode não existir em tenants antigos
        }
    }
}
}
