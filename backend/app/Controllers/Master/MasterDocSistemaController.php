<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Services/DocSistemaWikiService.php';

/**
 * Wiki doc_sistema/ no painel Master.
 */
class MasterDocSistemaController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    private DocSistemaWikiService $wiki;

    public function __construct()
    {
        parent::__construct();
        $this->requireMaster();
        $this->wiki = new DocSistemaWikiService();
    }

    public function index($pagina = null): void
    {
        $paginas = $this->wiki->listarPaginas();
        $slug = trim((string) ($pagina ?? $_GET['pagina'] ?? ''));
        $doc = $this->wiki->resolverPagina($slug, $paginas);
        if ($slug !== '' && $this->wiki->carregarPagina($slug) === null) {
            http_response_code(404);
        }

        $this->viewWithLayout('master', 'shared/doc_sistema/wiki', [
            'title' => ($doc['titulo'] ?? 'Docs') . ' — Documentação',
            'page_title' => 'Documentação',
            'current_page' => 'documentacao',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'paginas' => $paginas,
            'pagina_atual' => $doc,
            'wiki_url_base' => URL . '/master/documentacao',
            'wiki_voltar_href' => URL . '/master/dashboard',
            'wiki_voltar_label' => 'Dashboard',
            'wiki_titulo' => 'Documentação',
            'wiki_subtitulo' => 'Documentação viva do EducaTudo (Master). Arquivos em doc_sistema/.',
        ]);
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }
}
