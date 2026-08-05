<?php
/**
 * Model para a tabela noticias (ingestão RSS).
 */
require_once __DIR__ . '/../Core/Database.php';

class Noticia
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lista últimas notícias com filtro opcional por categoria e limit.
     */
    public function getUltimas($categoria = null, $limit = 20)
    {
        $limit = max(1, min(100, (int) $limit));
        $sql = "SELECT id, titulo, link, descricao, imagem, categoria, fonte, data_publicacao, criado_em
                FROM noticias
                WHERE 1=1";
        $params = [];

        if ($categoria !== null && $categoria !== '') {
            $sql .= " AND categoria = :categoria";
            $params['categoria'] = $categoria;
        }

        $sql .= " ORDER BY data_publicacao DESC, criado_em DESC LIMIT " . $limit;

        return $this->db->fetchAll($sql, $params);
    }
}
