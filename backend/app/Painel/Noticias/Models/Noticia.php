<?php

namespace App\Painel\Noticias\Models;
/**
 * Model para a tabela noticias (ingestão RSS).
 */

use App\Core\Database;

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

if (!class_exists('Noticia', false) && class_exists(__NAMESPACE__ . '\\Noticia', false)) {
    class_alias(__NAMESPACE__ . '\\Noticia', 'Noticia');
}
