<?php
/**
 * EducaTudo - Log de Provas no painel Master (agregação cross-tenant, somente leitura).
 *
 * Lista anomalias durante a realização de provas online pelo aluno (erro de
 * sessão/salvar resposta/finalizar, tentativa de burlar o modo seguro/tela
 * cheia) — registradas em provas_log_eventos (tenant) por ExamController.
 * Mesmo padrão de agregação de MasterTicketsController::coletarTickets():
 * conecta em cada escola sob demanda, sem duplicar dado em massa no master.
 */

if (!class_exists('MasterLogProvasController')) {

class MasterLogProvasController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';
    private const SESSION_MASTER_USER_NOME = 'master_user_nome';
    private const PER_PAGE = 30;
    private const PER_SCHOOL_LIMIT = 200;

    public function __construct()
    {
        parent::__construct();
        if (!class_exists('DatabaseManager', false)) {
            require_once __DIR__ . '/../../Core/DatabaseManager.php';
        }
        if (!class_exists('ProvaLogEvento', false)) {
            require_once __DIR__ . '/../../Models/Exams/ProvaLogEvento.php';
        }
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    public function index()
    {
        $this->requireMaster();

        $filtroEscola = (int) ($_GET['escola_id'] ?? 0);
        $filtroTipo = trim((string) ($_GET['tipo_evento'] ?? ''));
        $filtroBusca = trim((string) ($_GET['busca'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        if ($filtroTipo !== '' && !in_array($filtroTipo, ProvaLogEvento::TIPOS_VALIDOS, true)) {
            $filtroTipo = '';
        }

        $db = Database::getInstance();
        $escolas = $db->query(
            "SELECT e.id, e.nome, e.slug, e.dominio, e.ativo
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome"
        )->fetchAll(PDO::FETCH_ASSOC);

        $todos = $this->coletarEventos($escolas, $filtroEscola, $filtroTipo, $filtroBusca);

        usort($todos, static function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        $total = count($todos);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;
        $eventos = array_slice($todos, $offset, self::PER_PAGE);

        $resumo = ['total' => $total];
        foreach (ProvaLogEvento::TIPOS_VALIDOS as $tipo) {
            $resumo[$tipo] = 0;
        }
        foreach ($todos as $ev) {
            $tp = $ev['tipo_evento'] ?? '';
            if (isset($resumo[$tp])) {
                $resumo[$tp]++;
            }
        }

        $this->viewWithLayout('master', 'master/log-provas/index', [
            'title' => 'Log de Provas - Painel Master',
            'page_title' => 'Log de Provas',
            'current_page' => 'log_provas',
            'master_nome' => $_SESSION[self::SESSION_MASTER_USER_NOME] ?? 'Admin',
            'eventos' => $eventos,
            'escolas' => $escolas,
            'tipos_evento' => ProvaLogEvento::TIPOS_VALIDOS,
            'resumo' => $resumo,
            'filtros' => [
                'escola_id' => $filtroEscola,
                'tipo_evento' => $filtroTipo,
                'busca' => $filtroBusca,
            ],
            'paginacao' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => self::PER_PAGE,
            ],
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $escolas
     * @return array<int, array<string, mixed>>
     */
    private function coletarEventos(array $escolas, int $filtroEscola, string $filtroTipo, string $filtroBusca): array
    {
        $manager = new DatabaseManager(null);
        $todos = [];
        $buscaLike = $filtroBusca !== '' ? '%' . $filtroBusca . '%' : '';

        foreach ($escolas as $e) {
            $escolaId = (int) ($e['id'] ?? 0);
            if ($escolaId < 1) {
                continue;
            }
            if ($filtroEscola > 0 && $escolaId !== $filtroEscola) {
                continue;
            }

            try {
                $tenantDb = $manager->getConnectionForTenant($escolaId);
                $sql = "SELECT ev.id, ev.tipo_evento, ev.aluno_id, a.nome AS aluno_nome,
                               ev.prova_id, p.titulo AS prova_titulo, ev.bloco_id, pb.titulo AS bloco_titulo,
                               ev.detalhe, ev.ip, ev.user_agent, ev.created_at
                        FROM provas_log_eventos ev
                        LEFT JOIN alunos a ON a.id = ev.aluno_id
                        LEFT JOIN provas p ON p.id = ev.prova_id
                        LEFT JOIN provas_blocos pb ON pb.id = ev.bloco_id
                        WHERE 1=1";
                $params = [];
                if ($filtroTipo !== '') {
                    $sql .= " AND ev.tipo_evento = :tipo_evento";
                    $params['tipo_evento'] = $filtroTipo;
                }
                if ($buscaLike !== '') {
                    $sql .= " AND (a.nome LIKE :busca OR p.titulo LIKE :busca2 OR ev.detalhe LIKE :busca3)";
                    $params['busca'] = $buscaLike;
                    $params['busca2'] = $buscaLike;
                    $params['busca3'] = $buscaLike;
                }
                $sql .= " ORDER BY ev.created_at DESC LIMIT " . self::PER_SCHOOL_LIMIT;

                $rows = $tenantDb->fetchAll($sql, $params);
                foreach ($rows as $row) {
                    $row['escola_id'] = $escolaId;
                    $row['escola_nome'] = $e['nome'] ?? '';
                    $row['escola_slug'] = $e['slug'] ?? '';
                    $todos[] = $row;
                }
            } catch (Throwable $ex) {
                // tenant sem a tabela ainda (migration pendente) ou conexão falhou — não derruba a listagem
            }
        }

        return $todos;
    }
}

}
