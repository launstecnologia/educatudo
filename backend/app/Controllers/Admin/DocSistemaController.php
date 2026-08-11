<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Services/DocSistemaWikiService.php';

/**
 * Wiki visual dos Markdowns em doc_sistema/ (admin escola).
 */
class DocSistemaController extends BaseController
{
    private AuthManager $auth;
    private DocSistemaWikiService $wiki;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$this->usuarioPodeVer($user)) {
            $this->redirect(URL . '/admin/dashboard');
            return;
        }
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

        $this->viewWithLayout('admin', 'admin/doc_sistema/index', [
            'title' => ($doc['titulo'] ?? 'Docs') . ' — Doc do sistema',
            'page_title' => 'Doc do sistema',
            'current_page' => 'doc_sistema',
            'user' => $this->auth->getUser(),
            'paginas' => $paginas,
            'pagina_atual' => $doc,
            'wiki_url_base' => URL . '/admin/doc-sistema',
            'wiki_voltar_href' => URL . '/admin/assistente',
            'wiki_voltar_label' => 'Assistente',
            'wiki_titulo' => 'Doc do sistema',
        ]);
    }

    private function usuarioPodeVer(?array $user): bool
    {
        if (!$user || ($user['tipo'] ?? '') !== 'admin') {
            return false;
        }
        return in_array((string) ($user['perfil_admin'] ?? ''), ['dev', 'diretor', 'coordenador'], true);
    }
}
