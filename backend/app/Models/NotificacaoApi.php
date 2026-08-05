<?php
/**
 * Model para a tabela notificacoes_api (notificações manuais/API).
 * Não confundir com a tabela notificacoes do sistema in-app.
 */
require_once __DIR__ . '/../Core/Database.php';

class NotificacaoApi
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cria notificação manual (origem = 'manual').
     */
    public function criar($titulo, $mensagem, $imagem = null, $categoria = null)
    {
        $sql = "INSERT INTO notificacoes_api (titulo, mensagem, imagem, categoria, origem)
                VALUES (:titulo, :mensagem, :imagem, :categoria, 'manual')";
        return (int) $this->db->insert($sql, [
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'imagem' => $imagem !== '' && $imagem !== null ? $imagem : null,
            'categoria' => $categoria !== '' && $categoria !== null ? $categoria : null,
        ]);
    }

    /**
     * Lista notificações da API (para GET /api/notificacoes).
     */
    public function listar($limit = 50)
    {
        $limit = max(1, min(100, (int) $limit));
        $sql = "SELECT id, titulo, mensagem, imagem, categoria, origem, created_at
                FROM notificacoes_api
                ORDER BY created_at DESC
                LIMIT " . $limit;
        return $this->db->fetchAll($sql);
    }
}
