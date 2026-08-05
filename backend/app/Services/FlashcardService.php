<?php
/**
 * EducaTudo - Flashcard generation via OpenAI using prompt from dev_settings.
 * Builds prompt, calls OpenAI, validates JSON, saves deck and cards.
 */

// Garante mb_* mesmo em PHP sem a extensão mbstring (workers CLI podem usar
// um binário diferente do PHP-FPM). normalizeTopic() depende de mb_strtolower.
require_once __DIR__ . '/../Helpers/mbstring_polyfill.php';
require_once __DIR__ . '/../Models/System/DevSetting.php';
require_once __DIR__ . '/../Models/Study/FlashcardDeck.php';
require_once __DIR__ . '/../Models/Study/Flashcard.php';
require_once __DIR__ . '/../Models/Study/FlashcardTemplate.php';
require_once __DIR__ . '/OpenAIService.php';
if (!class_exists('Logger')) {
    require_once __DIR__ . '/../Core/Logger.php';
}

class FlashcardService
{
    private const PROMPT_KEY = 'flashcard_prompt';
    private const PROMPT_FROM_CONTENT_KEY = 'flashcard_prompt_from_content';
    private const DEFAULT_PROMPT = "You are Tudinha, an educational AI tutor of EducaTudo.\n\nCreate {QUANTITY} flashcards about:\n\nTopic: {TOPIC}\nGrade Level: {GRADE}\n\nRules:\n1. Use clear and objective questions.\n2. Answers must be short and didactic.\n3. Adapt language to grade level.\n4. Focus on essential exam concepts.\n5. Return ONLY valid JSON in format:\n\n[\n  {\n    \"question\": \"...\",\n    \"answer\": \"...\"\n  }\n]\n\nDo not add explanations outside JSON.";
    private const DEFAULT_PROMPT_FROM_CONTENT = "You are Tudinha, an educational AI tutor of EducaTudo.\n\nBased on the following content (extracted from study material), create {QUANTITY} flashcards.\n\nGrade Level: {GRADE}\n\nContent:\n{CONTENT}\n\nRules:\n1. Use clear and objective questions.\n2. Answers must be short and didactic.\n3. Adapt language to grade level.\n4. Base questions and answers only on the content above.\n5. Return ONLY valid JSON in format:\n\n[\n  {\n    \"question\": \"...\",\n    \"answer\": \"...\"\n  }\n]\n\nDo not add explanations outside JSON.";

    /** @var DevSetting */
    private $devSetting;
    /** @var \App\Services\OpenAIService */
    private $openAI;
    /** @var FlashcardDeck */
    private $deckModel;
    /** @var Flashcard */
    private $cardModel;
    /** @var FlashcardTemplate */
    private $templateModel;

    public function __construct()
    {
        $this->devSetting = new DevSetting();
        if (!class_exists('App\Services\OpenAIService')) {
            require_once __DIR__ . '/OpenAIService.php';
        }
        $this->openAI = new \App\Services\OpenAIService();
        $this->deckModel = new FlashcardDeck();
        $this->cardModel = new Flashcard();
        $this->templateModel = new FlashcardTemplate();
    }

    private static function normalizeTopic($topic)
    {
        $t = trim((string) $topic);
        $t = mb_strtolower($t, 'UTF-8');
        $t = preg_replace('/\s+/', ' ', $t);
        return $t;
    }

    /**
     * Generate flashcards for a student.
     *
     * @param int $alunoId
     * @param string $topic
     * @param string $grade
     * @param int $quantity
     * @return array { success: bool, deck_id: int|null, message: string, cards_count: int }
     */
    public function generate($alunoId, $topic, $grade, $quantity)
    {
        $topic = trim((string) $topic);
        $grade = trim((string) $grade);
        $quantity = (int) $quantity;
        if ($quantity < 1) {
            $quantity = 5;
        }
        if ($quantity > 30) {
            $quantity = 30;
        }

        $topicNormalized = self::normalizeTopic($topic);
        $existing = $this->templateModel->findByTopicGradeQuantity($topicNormalized, $grade, $quantity);
        if ($existing) {
            $templateCards = $this->templateModel->getCards($existing['id']);
            if (!empty($templateCards)) {
                try {
                    $deckId = $this->deckModel->create($alunoId, $topic, $grade, $quantity);
                    $position = 0;
                    foreach ($templateCards as $c) {
                        $this->cardModel->add($deckId, $c['question'], $c['answer'], $position);
                        $position++;
                    }
                    return [
                        'success' => true,
                        'deck_id' => $deckId,
                        'message' => $position . ' flashcard(s) criado(s) (reutilizados).',
                        'cards_count' => $position,
                    ];
                } catch (\Exception $e) {
                    Logger::error('FlashcardService: copy from template failed', ['exception' => $e], 'flashcard');
                }
            }
        }

        $promptTemplate = $this->devSetting->get(self::PROMPT_KEY);
        if ($promptTemplate === null || $promptTemplate === '') {
            $promptTemplate = self::DEFAULT_PROMPT;
        }

        $prompt = str_replace(
            ['{TOPIC}', '{GRADE}', '{QUANTITY}'],
            [$topic, $grade, (string) $quantity],
            $promptTemplate
        );

        try {
            $content = $this->openAI->generateText($prompt, [
                'model' => 'gpt-4o-mini',
                'max_tokens' => 1500,
                'temperature' => 0.7,
            ]);
        } catch (\Exception $e) {
            Logger::error('FlashcardService: OpenAI call failed', [
                'exception' => $e,
                'aluno_id' => $alunoId,
                'topic' => $topic,
            ], 'flashcard');
            return [
                'success' => false,
                'deck_id' => null,
                'message' => 'Não foi possível gerar os flashcards no momento. Tente novamente mais tarde.',
                'cards_count' => 0,
            ];
        }

        $content = $this->extractJson($content);
        $items = $this->parseAndValidateCards($content);
        if ($items === null) {
            Logger::warning('FlashcardService: Invalid or empty JSON from OpenAI', [
                'aluno_id' => $alunoId,
                'topic' => $topic,
                'raw_length' => strlen($content),
            ], 'flashcard');
            return [
                'success' => false,
                'deck_id' => null,
                'message' => 'A resposta da IA não está no formato esperado. Tente outro tema ou quantidade.',
                'cards_count' => 0,
            ];
        }

        try {
            $deckId = $this->deckModel->create($alunoId, $topic, $grade, $quantity);
            $position = 0;
            foreach ($items as $item) {
                $question = isset($item['question']) ? trim((string) $item['question']) : '';
                $answer = isset($item['answer']) ? trim((string) $item['answer']) : '';
                if ($question !== '' || $answer !== '') {
                    $this->cardModel->add($deckId, $question, $answer, $position);
                    $position++;
                }
            }
            $this->templateModel->createWithCards($topicNormalized, $grade, $quantity, $items);
        } catch (\Exception $e) {
            Logger::error('FlashcardService: Failed to save deck/cards', [
                'exception' => $e,
                'aluno_id' => $alunoId,
            ], 'flashcard');
            return [
                'success' => false,
                'deck_id' => null,
                'message' => 'Erro ao salvar os flashcards. Tente novamente.',
                'cards_count' => 0,
            ];
        }

        return [
            'success' => true,
            'deck_id' => $deckId,
            'message' => count($items) . ' flashcard(s) criado(s) com sucesso.',
            'cards_count' => $position,
        ];
    }

    /**
     * Generate flashcards from OCR/content (e.g. pasted images). Topic stored as "Conteúdo de estudo".
     *
     * @param int $alunoId
     * @param string $content Text from OCR (multiple images concatenated)
     * @param string $grade
     * @param int $quantity
     * @return array same as generate()
     */
    public function generateFromContent($alunoId, $content, $grade, $quantity)
    {
        $content = trim((string) $content);
        $grade = trim((string) $grade);
        $quantity = (int) $quantity;
        if ($quantity < 1) $quantity = 5;
        if ($quantity > 30) $quantity = 30;
        if (strlen($content) < 50) {
            return [
                'success' => false,
                'deck_id' => null,
                'message' => 'O conteúdo extraído das imagens está muito curto. Adicione mais imagens ou use texto.',
                'cards_count' => 0,
            ];
        }

        $promptTemplate = $this->devSetting->get(self::PROMPT_FROM_CONTENT_KEY);
        if ($promptTemplate === null || $promptTemplate === '') {
            $promptTemplate = self::DEFAULT_PROMPT_FROM_CONTENT;
        }
        $prompt = str_replace(
            ['{CONTENT}', '{GRADE}', '{QUANTITY}'],
            [$content, $grade, (string) $quantity],
            $promptTemplate
        );

        try {
            $responseContent = $this->openAI->generateText($prompt, [
                'model' => 'gpt-4o-mini',
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ]);
        } catch (\Exception $e) {
            Logger::error('FlashcardService: generateFromContent OpenAI failed', ['exception' => $e, 'aluno_id' => $alunoId], 'flashcard');
            return [
                'success' => false,
                'deck_id' => null,
                'message' => 'Não foi possível gerar os flashcards. Tente novamente.',
                'cards_count' => 0,
            ];
        }

        $responseContent = $this->extractJson($responseContent);
        $items = $this->parseAndValidateCards($responseContent);
        if ($items === null) {
            Logger::warning('FlashcardService: generateFromContent invalid JSON', ['aluno_id' => $alunoId], 'flashcard');
            return [
                'success' => false,
                'deck_id' => null,
                'message' => 'A resposta da IA não está no formato esperado. Tente com menos imagens ou outra quantidade.',
                'cards_count' => 0,
            ];
        }

        $topicForDeck = 'Conteúdo de estudo';
        try {
            $deckId = $this->deckModel->create($alunoId, $topicForDeck, $grade, $quantity);
            $position = 0;
            foreach ($items as $item) {
                $question = isset($item['question']) ? trim((string) $item['question']) : '';
                $answer = isset($item['answer']) ? trim((string) $item['answer']) : '';
                if ($question !== '' || $answer !== '') {
                    $this->cardModel->add($deckId, $question, $answer, $position);
                    $position++;
                }
            }
        } catch (\Exception $e) {
            Logger::error('FlashcardService: generateFromContent save failed', ['exception' => $e, 'aluno_id' => $alunoId], 'flashcard');
            return [
                'success' => false,
                'deck_id' => null,
                'message' => 'Erro ao salvar os flashcards. Tente novamente.',
                'cards_count' => 0,
            ];
        }

        return [
            'success' => true,
            'deck_id' => $deckId,
            'message' => count($items) . ' flashcard(s) criado(s) com sucesso.',
            'cards_count' => $position,
        ];
    }

    private function extractJson($content)
    {
        $content = trim($content);
        if (strpos($content, '```json') !== false) {
            $start = strpos($content, '```json') + 7;
            $end = strpos($content, '```', $start);
            if ($end !== false) {
                return trim(substr($content, $start, $end - $start));
            }
        }
        if (strpos($content, '```') !== false) {
            $start = strpos($content, '```') + 3;
            $end = strpos($content, '```', $start);
            if ($end !== false) {
                return trim(substr($content, $start, $end - $start));
            }
        }
        return $content;
    }

    /**
     * @return array|null Array of { question, answer } or null if invalid
     */
    private function parseAndValidateCards($content)
    {
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }
        $out = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = [
                'question' => isset($item['question']) ? (string) $item['question'] : '',
                'answer' => isset($item['answer']) ? (string) $item['answer'] : '',
            ];
        }
        return $out;
    }
}
