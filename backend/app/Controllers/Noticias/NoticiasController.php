<?php
/**
 * API REST - Notícias (feed RSS).
 * GET /api/noticias?categoria=educacao&limit=20
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Models/Noticia.php';

class NoticiasController extends BaseController
{
    private $noticiaModel;

    public function __construct()
    {
        parent::__construct();
        $this->noticiaModel = new Noticia();
    }

    /**
     * GET /api/noticias
     * Query: categoria (opcional), limit (opcional, default 20)
     */
    public function index()
    {
        $categoria = isset($_GET['categoria']) ? trim((string) $_GET['categoria']) : null;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

        $lista = $this->noticiaModel->getUltimas($categoria, $limit);

        $result = [];
        foreach ($lista as $row) {
            $result[] = [
                'titulo' => $row['titulo'],
                'descricao' => $row['descricao'],
                'imagem' => $row['imagem'],
                'categoria' => $row['categoria'],
                'link' => $row['link'],
                'data_publicacao' => $row['data_publicacao'],
            ];
        }

        $this->json($result);
    }
}
