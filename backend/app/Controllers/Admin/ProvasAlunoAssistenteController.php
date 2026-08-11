<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../Services/ProvasAlunoAssistenteService.php';
require_once __DIR__ . '/../../Services/AssistenteHistoricoService.php';

/**
 * Assistente admin (chat + histórico de conversas).
 * Hoje focado em provas do aluno; histórico já preparado para ampliar o MCP.
 */
class ProvasAlunoAssistenteController extends BaseController
{
    private AuthManager $auth;
    private ProvasAlunoAssistenteService $assistente;
    private AssistenteHistoricoService $historico;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$this->usuarioPodeUsar($user)) {
            if ($this->isAjaxRequest()) {
                $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
            }
            $this->redirect(URL . '/admin/dashboard');
            return;
        }
        $this->assistente = new ProvasAlunoAssistenteService();
        $this->historico = new AssistenteHistoricoService();
    }

    public function index(): void
    {
        $user = $this->auth->getUser();
        $userId = (int) ($user['id'] ?? 0);
        $disponivel = CreditosModuleRegistry::acaoIaDisponivel(ProvasAlunoAssistenteService::MODULO_CREDITOS);
        $historicoDisponivel = $this->historico->tabelasProntas();
        $conversas = $historicoDisponivel ? $this->historico->listarConversas($userId) : [];

        $this->viewWithLayout('admin', 'admin/assistente/index', [
            'title' => 'Assistente - EducaTudo',
            'page_title' => 'Assistente',
            'current_page' => 'assistente',
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'assistente_disponivel' => $disponivel,
            'historico_disponivel' => $historicoDisponivel,
            'conversas_iniciais' => $conversas,
        ]);
    }

    public function listarConversas(): void
    {
        $user = $this->auth->getUser();
        $this->json([
            'success' => true,
            'historico_disponivel' => $this->historico->tabelasProntas(),
            'data' => $this->historico->listarConversas((int) ($user['id'] ?? 0)),
        ]);
    }

    public function criarConversa(): void
    {
        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }
        if (!$this->historico->tabelasProntas()) {
            $this->json([
                'success' => false,
                'error' => 'Rode a migration 2026_07_20_assistente_conversas para habilitar o histórico.',
            ], 503);
        }
        $user = $this->auth->getUser();
        $titulo = trim((string) ($_POST['titulo'] ?? 'Nova conversa'));
        $conv = $this->historico->criarConversa((int) ($user['id'] ?? 0), $titulo);
        if ($conv === null) {
            $this->json(['success' => false, 'error' => 'Não foi possível criar a conversa.'], 500);
        }
        $this->json(['success' => true, 'data' => $conv]);
    }

    public function obterConversa(): void
    {
        $user = $this->auth->getUser();
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $conv = $this->historico->obterConversa((int) ($user['id'] ?? 0), $id);
        if ($conv === null) {
            $this->json(['success' => false, 'error' => 'Conversa não encontrada.'], 404);
        }
        $this->json(['success' => true, 'data' => $conv]);
    }

    public function renomearConversa(): void
    {
        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }
        $user = $this->auth->getUser();
        $id = (int) ($_POST['id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $ok = $this->historico->renomear((int) ($user['id'] ?? 0), $id, $titulo);
        $this->json(['success' => $ok, 'error' => $ok ? null : 'Não foi possível renomear.'], $ok ? 200 : 400);
    }

    public function excluirConversa(): void
    {
        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }
        $user = $this->auth->getUser();
        $id = (int) ($_POST['id'] ?? 0);
        $ok = $this->historico->excluir((int) ($user['id'] ?? 0), $id);
        $this->json(['success' => $ok, 'error' => $ok ? null : 'Não foi possível excluir.'], $ok ? 200 : 400);
    }

    public function mensagem(): void
    {
        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->json(['success' => false, 'error' => 'CSRF inválido. Recarregue a página.'], 419);
        }
        $this->assertIaDisponivel();

        $payload = $this->lerPayloadMensagem();
        $resultado = $this->assistente->processarMensagem(
            $payload['mensagem'],
            $payload['historico']
        );
        if (!empty($resultado['success'])) {
            $this->persistirTurno($payload, $resultado);
        }
        $this->json($resultado, !empty($resultado['success']) ? 200 : 400);
    }

    public function mensagemStream(): void
    {
        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->json(['success' => false, 'error' => 'CSRF inválido. Recarregue a página.'], 419);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        $emit = static function (string $event, array $data): void {
            echo 'event: ' . $event . "\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            @flush();
        };

        if (!CreditosModuleRegistry::acaoIaDisponivel(ProvasAlunoAssistenteService::MODULO_CREDITOS)) {
            $emit('error', [
                'error' => 'Assistente indisponível. Ative TudiCoins para esta escola no Master e inclua o item provas_aluno_assistente_mensagem no catálogo de custos.',
            ]);
            exit;
        }

        $payload = $this->lerPayloadMensagem();
        $resultado = $this->assistente->processarMensagem(
            $payload['mensagem'],
            $payload['historico'],
            static function (string $status) use ($emit): void {
                $emit('status', ['text' => $status]);
            },
            static function (string $chunk) use ($emit): void {
                if ($chunk !== '') {
                    $emit('chunk', ['text' => $chunk]);
                }
            }
        );

        if (empty($resultado['success'])) {
            $emit('error', ['error' => $resultado['error'] ?? 'Falha no assistente.']);
            exit;
        }

        $meta = $this->persistirTurno($payload, $resultado);

        $emit('done', [
            'success' => true,
            'mensagem' => $resultado['mensagem'] ?? '',
            'painel' => $resultado['painel'] ?? null,
            'consultas' => $resultado['consultas'] ?? [],
            'conversa_id' => $meta['conversa_id'] ?? null,
            'conversa_titulo' => $meta['titulo'] ?? null,
        ]);
        exit;
    }

    public function ferramenta(): void
    {
        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }
        $this->assertIaDisponivel();

        $tool = trim((string) ($_POST['tool'] ?? ''));
        $args = [];
        foreach ($_POST as $k => $v) {
            if ($k === '_token' || $k === 'tool') {
                continue;
            }
            $args[$k] = $v;
        }
        $resultado = $this->assistente->executarTool($tool, $args);
        $ok = !isset($resultado['ok']) || !empty($resultado['ok']);
        $this->json([
            'success' => $ok,
            'error' => $resultado['error'] ?? null,
            'data' => $resultado,
        ], $ok ? 200 : 400);
    }

    /**
     * @param array{mensagem:string,historico:list,conversa_id:int} $payload
     * @param array<string,mixed> $resultado
     * @return array{conversa_id:?int,titulo:?string}
     */
    private function persistirTurno(array $payload, array $resultado): array
    {
        $user = $this->auth->getUser();
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0 || !$this->historico->tabelasProntas()) {
            return ['conversa_id' => null, 'titulo' => null];
        }

        $conversaId = (int) ($payload['conversa_id'] ?? 0);
        if ($conversaId > 0) {
            if (!$this->historico->pertenceAoUsuario($userId, $conversaId)) {
                $conversaId = 0;
            }
        }
        if ($conversaId <= 0) {
            $criada = $this->historico->criarConversa($userId, 'Nova conversa');
            $conversaId = $criada ? (int) $criada['id'] : 0;
        }
        if ($conversaId <= 0) {
            return ['conversa_id' => null, 'titulo' => null];
        }

        $this->historico->adicionarMensagem($userId, $conversaId, 'user', $payload['mensagem']);
        $this->historico->adicionarMensagem(
            $userId,
            $conversaId,
            'assistant',
            (string) ($resultado['mensagem'] ?? ''),
            is_array($resultado['painel'] ?? null) ? $resultado['painel'] : null
        );
        $this->historico->atualizarMetaAposResposta(
            $userId,
            $conversaId,
            $payload['mensagem'],
            is_array($resultado['painel'] ?? null) ? $resultado['painel'] : null
        );

        $conv = $this->historico->obterConversa($userId, $conversaId);
        return [
            'conversa_id' => $conversaId,
            'titulo' => $conv['titulo'] ?? null,
        ];
    }

    /** @return array{mensagem:string,historico:list,conversa_id:int} */
    private function lerPayloadMensagem(): array
    {
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($mensagem === '') {
            $this->json(['success' => false, 'error' => 'Mensagem vazia.'], 400);
        }

        $conversaId = (int) ($_POST['conversa_id'] ?? 0);
        $user = $this->auth->getUser();
        $userId = (int) ($user['id'] ?? 0);

        $historico = [];
        if ($this->historico->tabelasProntas()) {
            if ($conversaId > 0) {
                $historico = $this->historico->historicoParaLlm($userId, $conversaId, 12);
            }
            // Com histórico no banco, não aceita historico JSON do cliente.
        } else {
            $raw = $_POST['historico'] ?? '';
            if (is_string($raw) && $raw !== '') {
                if (strlen($raw) > 80000) {
                    $this->json(['success' => false, 'error' => 'Histórico muito grande.'], 400);
                }
                $dec = json_decode($raw, true);
                if (is_array($dec)) {
                    $historico = $dec;
                }
            }
        }

        return [
            'mensagem' => $mensagem,
            'historico' => $historico,
            'conversa_id' => $conversaId,
        ];
    }

    private function assertIaDisponivel(): void
    {
        if (!CreditosModuleRegistry::acaoIaDisponivel(ProvasAlunoAssistenteService::MODULO_CREDITOS)) {
            $this->json([
                'success' => false,
                'error' => 'Assistente indisponível. Ative TudiCoins para esta escola no Master.',
            ], 403);
        }
    }

    private function usuarioPodeUsar(?array $user): bool
    {
        if (!$user || ($user['tipo'] ?? '') !== 'admin') {
            return false;
        }
        return in_array((string) ($user['perfil_admin'] ?? ''), ['dev', 'diretor', 'coordenador'], true);
    }

    private function isAjaxRequest(): bool
    {
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($xhr === 'xmlhttprequest') {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json') || str_contains($accept, 'text/event-stream');
    }
}
