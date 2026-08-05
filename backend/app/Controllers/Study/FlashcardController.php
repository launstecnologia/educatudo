<?php
/**
 * SmartFlashcards: create and generate flashcards (student).
 * Prompt is loaded from dev_settings, not hardcoded.
 */

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Services/FlashcardService.php';

class FlashcardController extends BaseController
{
    private $authManager;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
    }

    /**
     * List student's flashcard decks (history).
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            header('Location: ' . URL . '/login');
            exit;
        }

        require_once __DIR__ . '/../../Models/Study/FlashcardDeck.php';
        require_once __DIR__ . '/../../Models/Study/Flashcard.php';
        $deckModel = new FlashcardDeck();
        $cardModel = new Flashcard();

        $decks = $deckModel->listByAluno($user['id'], 50);
        $deckIds = array_column($decks, 'id');
        $counts = $cardModel->countByDeckIds($deckIds);
        foreach ($decks as &$d) {
            $d['cards_count'] = $counts[(int) $d['id']] ?? 0;
        }
        unset($d);

        $this->viewWithLayout('student', 'student/flashcards/index', [
            'title' => 'Meus Flashcards - EducaTudo',
            'user' => $user,
            'decks' => $decks,
        ]);
    }

    /**
     * Show form: topic or images (OCR), grade (pre-filled from aluno turma), quantity.
     */
    public function create()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            header('Location: ' . URL . '/login');
            exit;
        }

        $gradeSuggested = '';
        try {
            $db = \Database::getInstance();
            $aluno = $db->fetch(
                'SELECT a.id, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :id',
                ['id' => $user['id']]
            );
            if (!empty($aluno['turma_nome'])) {
                $gradeSuggested = $aluno['turma_nome'];
            }
        } catch (\Exception $e) {
            // ignore
        }

        $this->viewWithLayout('student', 'student/flashcards/create', [
            'title' => 'Criar Flashcards - EducaTudo',
            'user' => $user,
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'error' => null,
            'success_message' => null,
            'grade' => $gradeSuggested,
            'grade_suggested' => $gradeSuggested,
        ]);
    }

    /**
     * POST: generate flashcards via FlashcardService, then redirect or show message.
     */
    public function generate()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            header('Location: ' . URL . '/login');
            exit;
        }

        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $this->viewWithLayout('student', 'student/flashcards/create', [
                'title' => 'Criar Flashcards - EducaTudo',
                'user' => $user,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
                'error' => 'Requisição inválida. Tente novamente.',
                'success_message' => null,
            ]);
            return;
        }

        $topic = trim($_POST['topic'] ?? '');
        $grade = trim($_POST['grade'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 5);

        // Série: se vazio, tentar preencher da turma do aluno
        if ($grade === '') {
            try {
                $db = \Database::getInstance();
                $aluno = $db->fetch(
                    'SELECT t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :id',
                    ['id' => $user['id']]
                );
                if (!empty($aluno['turma_nome'])) {
                    $grade = $aluno['turma_nome'];
                }
            } catch (\Exception $e) {
                // keep grade empty
            }
        }

        $hasImages = !empty($_FILES['images']['name'][0]) || !empty($_POST['images_base64']);

        if ($hasImages) {
            // Fluxo OCR: múltiplas imagens → Google Vision → gerar flashcards a partir do conteúdo
            $imagesBase64 = [];
            if (!empty($_POST['images_base64']) && is_array($_POST['images_base64'])) {
                foreach ($_POST['images_base64'] as $b64) {
                    $b64 = trim((string) $b64);
                    if (strlen($b64) > 100) {
                        $imagesBase64[] = $b64;
                    }
                }
            }
            if (!empty($_FILES['images']['tmp_name'])) {
                foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                    if (is_uploaded_file($tmp) && $_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $data = file_get_contents($tmp);
                        if ($data !== false) {
                            $imagesBase64[] = base64_encode($data);
                        }
                    }
                }
            }
            if (empty($imagesBase64)) {
                $this->viewWithLayout('student', 'student/flashcards/create', [
                    'title' => 'Criar Flashcards - EducaTudo',
                    'user' => $user,
                    'csrf_token' => $_SESSION['csrf_token'] ?? '',
                    'error' => 'Nenhuma imagem válida. Envie arquivos ou cole imagens.',
                    'success_message' => null,
                    'topic' => $topic,
                    'grade' => $grade,
                    'quantity' => $quantity,
                    'grade_suggested' => $grade,
                ]);
                return;
            }
            require_once __DIR__ . '/../../Services/OpenAIService.php';
            $openAI = new \App\Services\OpenAIService();
            $allText = [];
            foreach ($imagesBase64 as $idx => $b64) {
                try {
                    $text = $openAI->transcreverComGoogleVision($b64, true); // true = retorna '' se imagem sem texto (não lança exceção)
                    if (trim($text) !== '') {
                        $allText[] = $text;
                    }
                } catch (\Exception $e) {
                    if (defined('DEBUG') && DEBUG) {
                        if (!class_exists('Logger')) {
                            require_once __DIR__ . '/../../Core/Logger.php';
                        }
                        \Logger::debug('FlashcardController: OCR failed for image ' . ($idx + 1), ['exception' => $e], 'flashcard');
                    }
                }
            }
            $content = implode("\n\n", $allText);
            if (trim($content) === '') {
                $this->viewWithLayout('student', 'student/flashcards/create', [
                    'title' => 'Criar Flashcards - EducaTudo',
                    'user' => $user,
                    'csrf_token' => $_SESSION['csrf_token'] ?? '',
                    'error' => 'Não foi possível extrair texto das imagens. Tente imagens mais nítidas ou digite o tema.',
                    'success_message' => null,
                    'topic' => $topic,
                    'grade' => $grade,
                    'quantity' => $quantity,
                    'grade_suggested' => $grade,
                ]);
                return;
            }
            // Enriquecer: se tiver tema, envia tema + conteúdo do OCR junto ao prompt
            if ($topic !== '') {
                $content = "Tema solicitado: " . $topic . "\n\nConteúdo do material (extraído por OCR das imagens):\n" . $content;
            }
            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosSvc = new \App\Services\CreditosService();
            $refCreditoFlashcard = 'flashcard_gen_' . (int) $user['id'] . '_' . str_replace('.', '', uniqid('', true));
            try {
                $creditosSvc->consumir('aluno', (int) $user['id'], 'flashcard', $refCreditoFlashcard);
            } catch (\Exception $e) {
                if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                    $this->viewWithLayout('student', 'student/flashcards/create', [
                        'title' => 'Criar Flashcards - EducaTudo',
                        'user' => $user,
                        'csrf_token' => $_SESSION['csrf_token'] ?? '',
                        'error' => $e->getMessage(),
                        'success_message' => null,
                        'topic' => $topic,
                        'grade' => $grade,
                        'quantity' => $quantity,
                        'grade_suggested' => $grade,
                    ]);
                    return;
                }
                throw $e;
            }
            // OCR concluído: enfileira geração assíncrona (mesma ref do débito para estorno)
            require_once __DIR__ . '/../../Services/AIJobService.php';
            $jobId = \App\Services\AIJobService::enqueue(
                'criar_flashcards',
                [
                    'user_id'        => (int) $user['id'],
                    'mode'           => 'content',
                    'content'        => $content,
                    'grade'          => $grade,
                    'quantity'       => $quantity,
                    'credits_ref'    => $refCreditoFlashcard,
                    'credits_modulo' => 'flashcard',
                ],
                (int) $user['id'],
                'aluno'
            );
            header('Location: ' . URL . '/flashcards/gerando/' . $jobId);
            exit;
        } else {
            if ($topic === '') {
                $this->viewWithLayout('student', 'student/flashcards/create', [
                    'title' => 'Criar Flashcards - EducaTudo',
                    'user' => $user,
                    'csrf_token' => $_SESSION['csrf_token'] ?? '',
                    'error' => 'Informe o tema ou adicione imagens para extrair o conteúdo (OCR).',
                    'success_message' => null,
                    'topic' => $topic,
                    'grade' => $grade,
                    'quantity' => $quantity,
                    'grade_suggested' => $grade,
                ]);
                return;
            }

            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosSvc = new \App\Services\CreditosService();
            $refCredito  = 'flashcard_gen_' . (int) $user['id'] . '_' . str_replace('.', '', uniqid('', true));
            try {
                $creditosSvc->consumir('aluno', (int) $user['id'], 'flashcard', $refCredito);
            } catch (\Exception $e) {
                if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                    $this->viewWithLayout('student', 'student/flashcards/create', [
                        'title' => 'Criar Flashcards - EducaTudo',
                        'user' => $user,
                        'csrf_token' => $_SESSION['csrf_token'] ?? '',
                        'error' => $e->getMessage(),
                        'success_message' => null,
                        'topic' => $topic,
                        'grade' => $grade,
                        'quantity' => $quantity,
                        'grade_suggested' => $grade,
                    ]);
                    return;
                }
                throw $e;
            }

            require_once __DIR__ . '/../../Services/AIJobService.php';
            $jobId = \App\Services\AIJobService::enqueue(
                'criar_flashcards',
                [
                    'user_id'        => (int) $user['id'],
                    'mode'           => 'topic',
                    'topic'          => $topic,
                    'grade'          => $grade,
                    'quantity'       => $quantity,
                    'credits_ref'    => $refCredito,
                    'credits_modulo' => 'flashcard',
                ],
                (int) $user['id'],
                'aluno'
            );
            header('Location: ' . URL . '/flashcards/gerando/' . $jobId);
            exit;
        }
    }

    /** GET: página de aguardo enquanto o job de flashcards é processado */
    public function gerando($jobId)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            header('Location: ' . URL . '/login');
            exit;
        }
        // Renderiza o conteúdo direto com o layout. Antes apontava para
        // 'student/flashcards/gerando', um wrapper que chamava viewWithLayout
        // novamente — incluindo o layout (student.php) 2x e causando fatal
        // "Cannot redeclare ..." nas funções de topo do layout.
        $this->viewWithLayout('student', 'student/flashcards/gerando-content', [
            'title'    => 'Gerando Flashcards...',
            'user'     => $user,
            'job_id'   => (int) $jobId,
        ]);
    }

    /**
     * Show deck and list of cards.
     */
    public function deck($id)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            header('Location: ' . URL . '/login');
            exit;
        }

        require_once __DIR__ . '/../../Models/Study/FlashcardDeck.php';
        require_once __DIR__ . '/../../Models/Study/Flashcard.php';
        $deckModel = new FlashcardDeck();
        $cardModel = new Flashcard();

        $deck = $deckModel->getByIdAndAluno((int) $id, $user['id']);
        if (!$deck) {
            header('Location: ' . URL . '/flashcards');
            exit;
        }

        $cards = $cardModel->listByDeck((int) $deck['id']);

        $this->viewWithLayout('student', 'student/flashcards/deck', [
            'title' => 'Flashcards: ' . htmlspecialchars($deck['topic']) . ' - EducaTudo',
            'user' => $user,
            'deck' => $deck,
            'cards' => $cards,
        ]);
    }

    /**
     * Tela de explicação da Tudinha: cartões que o aluno marcou como "não entendi".
     * GET ?deck_id=1&card_ids=2,5,7
     */
    public function explicar()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            header('Location: ' . URL . '/login');
            exit;
        }

        $deckId = (int) ($_GET['deck_id'] ?? 0);
        $cardIdsRaw = $_GET['card_ids'] ?? '';
        $cardIds = array_filter(array_map('intval', explode(',', $cardIdsRaw)));

        if ($deckId <= 0 || empty($cardIds)) {
            header('Location: ' . URL . '/flashcards');
            exit;
        }

        require_once __DIR__ . '/../../Models/Study/FlashcardDeck.php';
        require_once __DIR__ . '/../../Models/Study/Flashcard.php';
        $deckModel = new FlashcardDeck();
        $cardModel = new Flashcard();

        $deck = $deckModel->getByIdAndAluno($deckId, $user['id']);
        if (!$deck) {
            header('Location: ' . URL . '/flashcards');
            exit;
        }

        $allCards = $cardModel->listByDeck($deckId);
        $cards = [];
        foreach ($allCards as $c) {
            if (in_array((int) $c['id'], $cardIds, true)) {
                $cards[] = $c;
            }
        }
        if (empty($cards)) {
            header('Location: ' . URL . '/flashcards/deck/' . $deckId);
            exit;
        }

        require_once __DIR__ . '/../../Models/Study/FlashcardExplicacao.php';
        $explicacaoModel = new FlashcardExplicacao();
        $explicacoesSalvas = $explicacaoModel->listarPorAlunoDeckCards($user['id'], $deckId, $cardIds);

        $this->viewWithLayout('student', 'student/flashcards/explicar', [
            'title' => 'Explicação - Tudinha - EducaTudo',
            'user' => $user,
            'deck' => $deck,
            'cards' => $cards,
            'explicacoes_salvas' => $explicacoesSalvas,
        ]);
    }

    /**
     * POST: gera (ou simplifica) explicação de um cartão via OpenAI.
     * JSON: card_id, deck_id, explicacoes_anteriores[].
     */
    public function explicarGerar()
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            echo json_encode(['success' => false, 'error' => 'Não autorizado']);
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            return;
        }

        $deckId = (int) ($_POST['deck_id'] ?? 0);
        $cardId = (int) ($_POST['card_id'] ?? 0);
        $explicacoesAnteriores = isset($_POST['explicacoes_anteriores']) && is_array($_POST['explicacoes_anteriores'])
            ? array_values(array_filter(array_map('trim', $_POST['explicacoes_anteriores'])))
            : [];

        if ($deckId <= 0 || $cardId <= 0) {
            echo json_encode(['success' => false, 'error' => 'deck_id e card_id são obrigatórios']);
            return;
        }

        require_once __DIR__ . '/../../Models/Study/FlashcardDeck.php';
        require_once __DIR__ . '/../../Models/Study/Flashcard.php';
        $deckModel = new FlashcardDeck();
        $cardModel = new Flashcard();

        $deck = $deckModel->getByIdAndAluno($deckId, $user['id']);
        if (!$deck) {
            echo json_encode(['success' => false, 'error' => 'Baralho não encontrado']);
            return;
        }

        $allCards = $cardModel->listByDeck($deckId);
        $card = null;
        foreach ($allCards as $c) {
            if ((int) $c['id'] === $cardId) {
                $card = $c;
                break;
            }
        }
        if (!$card) {
            echo json_encode(['success' => false, 'error' => 'Cartão não encontrado']);
            return;
        }

        require_once __DIR__ . '/../../Services/CreditosService.php';
        $creditosSvc = new \App\Services\CreditosService();
        $refNaoEntendi = 'flashcard_ne_' . $deckId . '_' . $cardId . '_' . str_replace('.', '', uniqid('', true));
        $moduloDebitado = null;
        try {
            // Explicação "não entendi": cobra flashcard_nao_entendi se marcado no Master; senão usa o mesmo flag de geração (flashcard).
            $moduloDebitado = $creditosSvc->consumirPrimeiroModuloDisponivel(
                'aluno',
                (int) $user['id'],
                ['flashcard_nao_entendi', 'flashcard'],
                $refNaoEntendi
            );
        } catch (\Exception $e) {
            if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                return;
            }
            echo json_encode(['success' => false, 'error' => 'Erro ao verificar créditos.']);
            return;
        }

        try {
            require_once __DIR__ . '/../../Services/OpenAIService.php';
            $openAI = new \App\Services\OpenAIService();
            $explicacao = $openAI->explicarFlashcard(
                $card['question'],
                $card['answer'],
                $explicacoesAnteriores
            );

            require_once __DIR__ . '/../../Models/Study/FlashcardExplicacao.php';
            $explicacaoModel = new FlashcardExplicacao();
            $explicacaoModel->salvar($user['id'], $deckId, $cardId, $explicacao);

            echo json_encode(['success' => true, 'explicacao' => $explicacao]);
        } catch (\Exception $e) {
            try {
                if ($moduloDebitado !== null) {
                    $creditosSvc->estornarPorReferencia($moduloDebitado, $refNaoEntendi);
                }
            } catch (\Exception $e2) {
                if (class_exists('Logger')) {
                    \Logger::warning('FlashcardController::explicarGerar estorno', ['exception' => $e2], 'flashcard');
                }
            }
            if (class_exists('Logger')) {
                \Logger::error('FlashcardController::explicarGerar', ['exception' => $e], 'flashcard');
            }
            echo json_encode(['success' => false, 'error' => 'Não foi possível gerar a explicação. Tente novamente.']);
        }
    }
}
