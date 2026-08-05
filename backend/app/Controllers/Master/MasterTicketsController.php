<?php
/**
 * EducaTudo - Tickets de suporte no painel Master (agregação cross-tenant).
 */

if (!class_exists('MasterTicketsController')) {

class MasterTicketsController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';
    private const SESSION_MASTER_USER_NOME = 'master_user_nome';
    private const PER_PAGE = 20;
    private const PER_SCHOOL_LIMIT = 100;

    public function __construct()
    {
        parent::__construct();
        if (!class_exists('MasterSecretVault', false)) {
            require_once __DIR__ . '/../../Core/MasterSecretVault.php';
        }
        if (!class_exists('DatabaseManager', false)) {
            require_once __DIR__ . '/../../Core/DatabaseManager.php';
        }
        if (!function_exists('rich_text_render')) {
            require_once __DIR__ . '/../../Helpers/RichTextHelper.php';
        }
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return $xhr === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }

    /**
     * Listagem agregada de tickets de todas as escolas.
     */
    public function index()
    {
        $this->requireMaster();

        $filtroEscola = (int) ($_GET['escola_id'] ?? 0);
        $filtroStatus = trim((string) ($_GET['status'] ?? ''));
        $filtroBusca = trim((string) ($_GET['busca'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $statusValidos = ['aberto', 'em_andamento', 'respondido', 'fechado'];
        if ($filtroStatus !== '' && !in_array($filtroStatus, $statusValidos, true)) {
            $filtroStatus = '';
        }

        $db = Database::getInstance();
        $escolas = $db->query(
            "SELECT e.id, e.nome, e.slug, e.dominio, e.ativo,
             (SELECT config_value FROM config_escolas_layout c WHERE c.escola_id = e.id AND c.config_key = 'logo_1x1_url' LIMIT 1) AS logo_1x1_url
             FROM escolas e
             INNER JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.ativo = 1
             ORDER BY e.nome"
        )->fetchAll(PDO::FETCH_ASSOC);

        $todos = $this->coletarTickets($escolas, $filtroEscola, $filtroStatus, $filtroBusca);

        usort($todos, static function ($a, $b) {
            return strcmp($b['criado_em'] ?? '', $a['criado_em'] ?? '');
        });

        $total = count($todos);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;
        $tickets = array_slice($todos, $offset, self::PER_PAGE);

        $resumo = [
            'total' => $total,
            'aberto' => 0,
            'em_andamento' => 0,
            'respondido' => 0,
            'fechado' => 0,
        ];
        foreach ($todos as $t) {
            $st = $t['status'] ?? '';
            if (isset($resumo[$st])) {
                $resumo[$st]++;
            }
        }

        $this->viewWithLayout('master', 'master/tickets/index', [
            'title' => 'Tickets de Suporte - Painel Master',
            'page_title' => 'Tickets de Suporte',
            'current_page' => 'tickets',
            'master_nome' => $_SESSION[self::SESSION_MASTER_USER_NOME] ?? 'Admin',
            'tickets' => $tickets,
            'escolas' => $escolas,
            'resumo' => $resumo,
            'filtros' => [
                'escola_id' => $filtroEscola,
                'status' => $filtroStatus,
                'busca' => $filtroBusca,
            ],
            'paginacao' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => self::PER_PAGE,
            ],
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * JSON para popular o offcanvas de detalhe.
     */
    public function dados()
    {
        $this->requireMaster();
        $escolaId = (int) ($_GET['escola_id'] ?? 0);
        $ticketId = (int) ($_GET['ticket_id'] ?? 0);

        if ($escolaId < 1 || $ticketId < 1) {
            $this->json(['ok' => false, 'error' => 'Parâmetros inválidos.'], 400);
        }

        $ctx = $this->carregarTicketContexto($escolaId, $ticketId);
        if ($ctx === null) {
            $this->json(['ok' => false, 'error' => 'Ticket não encontrado.'], 404);
        }

        $ticket = $ctx['ticket'];
        $escolaSlug = (string) ($ctx['escola']['slug'] ?? '');
        $mensagens = [];
        foreach ($ctx['mensagens'] as $msg) {
            if (!function_exists('ticket_message_html')) {
                require_once __DIR__ . '/../../Helpers/RichTextHelper.php';
            }
            $mensagemHtml = ticket_message_html($msg['mensagem'] ?? '');
            // /media/serve é relativo ao domínio da própria escola (funciona sozinho
            // quando visto no tenant do aluno) — no painel Master a requisição sai do
            // domínio master.educatudo.com, sem contexto de tenant, então a imagem
            // quebra (ícone de "imagem não encontrada"). Injeta &tenant={slug} nas
            // URLs de /media/serve que ainda não tiverem esse parâmetro.
            if ($escolaSlug !== '' && strpos($mensagemHtml, '/media/serve') !== false) {
                $mensagemHtml = preg_replace_callback(
                    '/(<img\b[^>]*\bsrc=")(\/media\/serve\?[^"]*)(")/i',
                    function (array $m) use ($escolaSlug): string {
                        if (stripos($m[2], 'tenant=') !== false) {
                            return $m[0];
                        }
                        $sep = strpos($m[2], '?') !== false ? '&' : '?';
                        return $m[1] . $m[2] . $sep . 'tenant=' . rawurlencode($escolaSlug) . $m[3];
                    },
                    $mensagemHtml
                ) ?? $mensagemHtml;
            }
            $mensagens[] = [
                'id' => (int) ($msg['id'] ?? 0),
                'remetente_tipo' => $msg['remetente_tipo'] ?? '',
                'mensagem_html' => $mensagemHtml,
                'criado_em' => $msg['criado_em'] ?? null,
                'criado_em_fmt' => !empty($msg['criado_em']) ? date('d/m/Y H:i', strtotime($msg['criado_em'])) : '',
            ];
        }

        $this->json([
            'ok' => true,
            'escola' => [
                'id' => (int) $ctx['escola']['id'],
                'nome' => $ctx['escola']['nome'] ?? '',
                'slug' => $ctx['escola']['slug'] ?? '',
            ],
            'ticket' => [
                'id' => (int) ($ticket['id'] ?? 0),
                'assunto' => $ticket['assunto'] ?? '',
                'categoria' => $ticket['categoria'] ?? '',
                'modulo' => $ticket['modulo'] ?? '',
                'status' => $ticket['status'] ?? 'aberto',
                'aluno_nome' => $ticket['aluno_nome'] ?? '',
                'aluno_email' => $ticket['aluno_email'] ?? '',
                'aluno_ra' => $ticket['aluno_ra'] ?? '',
                'criado_em' => $ticket['criado_em'] ?? null,
                'criado_em_fmt' => !empty($ticket['criado_em']) ? date('d/m/Y H:i', strtotime($ticket['criado_em'])) : '',
                'fechado_em' => $ticket['fechado_em'] ?? null,
                'fechado_em_fmt' => !empty($ticket['fechado_em']) ? date('d/m/Y H:i', strtotime($ticket['fechado_em'])) : '',
            ],
            'mensagens' => $mensagens,
        ]);
    }

    /**
     * Compat: redireciona a URL antiga /ver para a listagem com offcanvas.
     */
    public function exibir()
    {
        $this->requireMaster();
        $escolaId = (int) ($_GET['escola_id'] ?? 0);
        $ticketId = (int) ($_GET['ticket_id'] ?? 0);
        $qs = http_build_query(array_filter([
            'abrir_escola' => $escolaId > 0 ? $escolaId : null,
            'abrir_ticket' => $ticketId > 0 ? $ticketId : null,
        ]));
        header('Location: ' . URL . '/master/tickets' . ($qs !== '' ? '?' . $qs : ''));
        exit;
    }

    public function responder()
    {
        $this->requireMaster();
        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        $isJson = $this->wantsJson();

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.'], 403);
            }
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/tickets');
            exit;
        }

        if ($escolaId < 1 || $ticketId < 1 || $mensagem === '') {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Preencha a mensagem.'], 422);
            }
            $this->setFlashMessage('Preencha a mensagem.', 'error');
            header('Location: ' . URL . '/master/tickets');
            exit;
        }

        $escola = $this->buscarEscolaComBanco($escolaId);
        $pdo = $escola ? $this->connectMasterTenant($escola) : null;
        if (!$pdo) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Erro ao conectar ao banco da escola.'], 500);
            }
            $this->setFlashMessage('Erro ao conectar ao banco da escola.', 'error');
            header('Location: ' . URL . '/master/tickets');
            exit;
        }

        try {
            $masterId = (int) ($_SESSION[self::SESSION_MASTER_USER_ID] ?? 0);
            $pdo->prepare(
                "INSERT INTO suporte_tickets_mensagens (ticket_id, remetente_tipo, remetente_id, mensagem, criado_em) VALUES (?, 'admin', ?, ?, NOW())"
            )->execute([$ticketId, $masterId, $mensagem]);

            $pdo->prepare(
                "UPDATE suporte_tickets SET status = 'respondido', atualizado_em = NOW() WHERE id = ?"
            )->execute([$ticketId]);

            if ($isJson) {
                $this->json(['ok' => true, 'message' => 'Resposta enviada com sucesso.', 'status' => 'respondido']);
            }
            $this->setFlashMessage('Resposta enviada com sucesso.', 'success');
        } catch (PDOException $e) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Erro ao enviar resposta.'], 500);
            }
            $this->setFlashMessage('Erro ao enviar resposta.', 'error');
        }

        header('Location: ' . URL . '/master/tickets?abrir_escola=' . $escolaId . '&abrir_ticket=' . $ticketId);
        exit;
    }

    public function fechar()
    {
        $this->requireMaster();
        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $isJson = $this->wantsJson();

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.'], 403);
            }
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/tickets');
            exit;
        }

        $escola = $this->buscarEscolaComBanco($escolaId);
        $pdo = $escola ? $this->connectMasterTenant($escola) : null;
        if (!$pdo) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Erro ao conectar.'], 500);
            }
            $this->setFlashMessage('Erro ao conectar.', 'error');
            header('Location: ' . URL . '/master/tickets');
            exit;
        }

        try {
            $pdo->prepare(
                "UPDATE suporte_tickets SET status = 'fechado', fechado_em = NOW(), atualizado_em = NOW() WHERE id = ?"
            )->execute([$ticketId]);
            if ($isJson) {
                $this->json(['ok' => true, 'message' => 'Ticket fechado.', 'status' => 'fechado']);
            }
            $this->setFlashMessage('Ticket fechado.', 'success');
        } catch (PDOException $e) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Erro ao fechar ticket.'], 500);
            }
            $this->setFlashMessage('Erro ao fechar ticket.', 'error');
        }

        header('Location: ' . URL . '/master/tickets');
        exit;
    }

    public function marcarAndamento()
    {
        $this->requireMaster();
        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $isJson = $this->wantsJson();

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.'], 403);
            }
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/tickets');
            exit;
        }

        $escola = $this->buscarEscolaComBanco($escolaId);
        $pdo = $escola ? $this->connectMasterTenant($escola) : null;
        if (!$pdo) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Erro ao conectar.'], 500);
            }
            $this->setFlashMessage('Erro ao conectar.', 'error');
            header('Location: ' . URL . '/master/tickets');
            exit;
        }

        try {
            $pdo->prepare(
                "UPDATE suporte_tickets SET status = 'em_andamento', atualizado_em = NOW() WHERE id = ?"
            )->execute([$ticketId]);
            if ($isJson) {
                $this->json(['ok' => true, 'message' => 'Ticket marcado como em andamento.', 'status' => 'em_andamento']);
            }
            $this->setFlashMessage('Ticket marcado como em andamento.', 'success');
        } catch (PDOException $e) {
            if ($isJson) {
                $this->json(['ok' => false, 'error' => 'Erro ao atualizar ticket.'], 500);
            }
            $this->setFlashMessage('Erro ao atualizar ticket.', 'error');
        }

        header('Location: ' . URL . '/master/tickets');
        exit;
    }

    /**
     * @param array<int, array<string, mixed>> $escolas
     * @return array<int, array<string, mixed>>
     */
    private function coletarTickets(array $escolas, int $filtroEscola, string $filtroStatus, string $filtroBusca): array
    {
        $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if (!$masterPdo) {
            return [];
        }

        $manager = new DatabaseManager($masterPdo);
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
                $sql = "SELECT t.id, t.assunto, t.categoria, t.modulo, t.status, t.criado_em, a.nome AS aluno_nome
                        FROM suporte_tickets t
                        INNER JOIN alunos a ON a.id = t.aluno_id
                        WHERE 1=1";
                $params = [];
                if ($filtroStatus !== '') {
                    $sql .= " AND t.status = :status";
                    $params['status'] = $filtroStatus;
                }
                if ($buscaLike !== '') {
                    $sql .= " AND (t.assunto LIKE :busca OR a.nome LIKE :busca2)";
                    $params['busca'] = $buscaLike;
                    $params['busca2'] = $buscaLike;
                }
                $sql .= " ORDER BY t.criado_em DESC LIMIT " . self::PER_SCHOOL_LIMIT;

                $rows = $tenantDb->fetchAll($sql, $params);
                foreach ($rows as $row) {
                    $row['escola_id'] = $escolaId;
                    $row['escola_nome'] = $e['nome'] ?? '';
                    $row['escola_slug'] = $e['slug'] ?? '';
                    $row['escola_dominio'] = $e['dominio'] ?? '';
                    $row['escola_logo_1x1_url'] = $e['logo_1x1_url'] ?? '';
                    $todos[] = $row;
                }
            } catch (Throwable $ex) {
                // tenant sem tabela ou conexão falhou
            }
        }

        return $todos;
    }

    /**
     * @return array{escola: array, ticket: array, mensagens: array}|null
     */
    private function carregarTicketContexto(int $escolaId, int $ticketId): ?array
    {
        $escola = $this->buscarEscolaComBanco($escolaId);
        if (!$escola) {
            return null;
        }
        $pdo = $this->connectMasterTenant($escola);
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT t.*, a.nome as aluno_nome, a.email as aluno_email, a.ra as aluno_ra
                 FROM suporte_tickets t
                 INNER JOIN alunos a ON a.id = t.aluno_id
                 WHERE t.id = ?"
            );
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }

        if (!$ticket) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM suporte_tickets_mensagens WHERE ticket_id = ? ORDER BY criado_em ASC"
            );
            $stmt->execute([$ticketId]);
            $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $mensagens = [];
        }

        try {
            $pdo->prepare(
                "UPDATE suporte_tickets_mensagens SET lida = 1 WHERE ticket_id = ? AND remetente_tipo = 'aluno' AND lida = 0"
            )->execute([$ticketId]);
        } catch (PDOException $e) {
        }

        return [
            'escola' => $escola,
            'ticket' => $ticket,
            'mensagens' => $mensagens,
        ];
    }

    private function buscarEscolaComBanco(int $escolaId): ?array
    {
        $db = Database::getInstance();
        $escola = $db->query(
            "SELECT e.*, b.host AS db_host, b.porta AS db_porta, b.nome_banco AS db_nome_banco,
                    b.usuario AS db_usuario, b.senha_criptografada AS db_senha
             FROM escolas e
             LEFT JOIN config_escolas_banco b ON b.escola_id = e.id
             WHERE e.id = ?",
            [$escolaId]
        )->fetch(PDO::FETCH_ASSOC);

        return $escola ?: null;
    }

    private function connectMasterTenant(array $escola): ?PDO
    {
        $host = $escola['db_host'] ?? null;
        $port = (int) ($escola['db_porta'] ?? 3306);
        $dbName = $escola['db_nome_banco'] ?? null;
        $user = $escola['db_usuario'] ?? null;
        $pass = MasterSecretVault::decryptDbPassword($escola['db_senha'] ?? '');
        if (!$host || !$dbName || !$user) {
            return null;
        }
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET time_zone = '-03:00'");
            return $pdo;
        } catch (PDOException $e) {
            return null;
        }
    }
}

}
