<?php
require_once __DIR__ . '/../../../Helpers/EssayTextStructureHelper.php';
require_once __DIR__ . '/../../../Helpers/EssayCriteriaDisplayHelper.php';
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Corrigir redação — <?= htmlspecialchars($submission['student_name']) ?></h2>
            <p class="text-gray-600"><?= htmlspecialchars($proposal['title']) ?> (<?= htmlspecialchars($proposal['board_name']) ?>)</p>
        </div>
        <a href="<?= URL ?>/professor/redacao-configuravel/<?= (int)$proposal['id'] ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Voltar</a>
    </div>
</div>

<?php
$contentText = !empty($submission['content_text']) ? $submission['content_text'] : ($submission['ocr_text'] ?? '');
$structuredText = EssayTextStructureHelper::decode((string) ($submission['ocr_text_structure_json'] ?? ''));
$ocrLayoutMeta = EssayTextStructureHelper::decode((string) ($submission['ocr_layout_json'] ?? ''));
$transcriptionIdentifier = '';
if (!empty($ocrLayoutMeta['record_code'])) {
    $transcriptionIdentifier = (string) $ocrLayoutMeta['record_code'];
} elseif (!empty($ocrLayoutMeta['record_id'])) {
    $transcriptionIdentifier = '#' . (string) $ocrLayoutMeta['record_id'];
} elseif (!empty($ocrLayoutMeta['transcription_id'])) {
    $transcriptionIdentifier = (string) $ocrLayoutMeta['transcription_id'];
} elseif (!empty($ocrLayoutMeta['id'])) {
    $transcriptionIdentifier = (string) $ocrLayoutMeta['id'];
}
$flattenedStructuredLines = !empty($structuredText) ? EssayTextStructureHelper::flatten($structuredText) : [];
if (empty($flattenedStructuredLines) && trim((string) $contentText) !== '') {
    $structuredFromPlain = EssayTextStructureHelper::buildFromPlainText((string) $contentText);
    $flattenedStructuredLines = !empty($structuredFromPlain) ? EssayTextStructureHelper::flatten($structuredFromPlain) : [];
}
if (!function_exists('normalizeEssayTextForDisplay')) {
    /**
     * Normaliza texto OCR para leitura:
     * - padroniza quebras de linha
     * - preserva parágrafos (linha em branco)
     * - junta quebras de linha artificiais dentro do mesmo parágrafo
     */
    function normalizeEssayTextForDisplay($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = preg_split("/\n\s*\n/u", $text, -1, PREG_SPLIT_NO_EMPTY);
        $normalized = [];
        foreach ($paragraphs as $p) {
            $p = trim((string) $p);
            if ($p === '') continue;
            // Dentro do mesmo parágrafo, quebra de linha vira espaço.
            $p = preg_replace("/\n+/u", " ", $p);
            // Compacta múltiplos espaços.
            $p = preg_replace("/[ \t]{2,}/u", " ", $p);
            $normalized[] = trim($p);
        }
        return implode("\n\n", $normalized);
    }
}
if (!function_exists('extractEssayIssueTermsFromFeedback')) {
    /**
     * Extrai termos problemáticos citados explicitamente entre aspas no feedback.
     *
     * Ex.: "incluirem" ou 'incluirem'
     *
     * @return array<int, string>
     */
    function extractEssayIssueTermsFromFeedback($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }

        $matches = [];
        preg_match_all('/["“”\'‘’]([^"“”\'‘’]{2,80})["“”\'‘’]/u', $text, $matches);
        if (empty($matches[1])) {
            return [];
        }

        $terms = [];
        foreach ($matches[1] as $term) {
            $term = trim((string) $term);
            if ($term === '' || mb_strlen($term, 'UTF-8') < 2) {
                continue;
            }
            // Evita capturar frases longas demais; foco em palavras/trechos objetivos.
            if (substr_count($term, ' ') > 5) {
                continue;
            }
            $terms[] = $term;
        }

        $terms = array_values(array_unique($terms));
        usort($terms, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
        });

        return $terms;
    }
}
if (!function_exists('renderEssayParagraphWithHighlights')) {
    /**
     * Renderiza um parágrafo destacando termos apontados no feedback.
     */
    function renderEssayParagraphWithHighlights($paragraph, array $highlightTerms)
    {
        $paragraph = (string) $paragraph;
        if ($paragraph === '') {
            return '';
        }
        if (empty($highlightTerms)) {
            return nl2br(htmlspecialchars($paragraph));
        }

        $escapedTerms = [];
        foreach ($highlightTerms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }
            $escapedTerms[] = preg_quote($term, '/');
        }
        if (empty($escapedTerms)) {
            return nl2br(htmlspecialchars($paragraph));
        }

        $pattern = '/(' . implode('|', $escapedTerms) . ')/iu';
        $parts = preg_split($pattern, $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return nl2br(htmlspecialchars($paragraph));
        }

        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $matched = false;
            foreach ($highlightTerms as $term) {
                if (mb_strtolower($part, 'UTF-8') === mb_strtolower((string) $term, 'UTF-8')) {
                    $matched = true;
                    break;
                }
            }

            $safe = nl2br(htmlspecialchars($part));
            if ($matched) {
                $html .= '<span class="essay-issue-highlight" title="Trecho citado no feedback de correção">' . $safe . '</span>';
            } else {
                $html .= $safe;
            }
        }

        return $html;
    }
}
if (!function_exists('buildEssayParagraphsFromStructuredLines')) {
    function buildEssayParagraphsFromStructuredLines(array $lines): array
    {
        $paragraphs = [];
        $current = '';
        $previousParagraphId = null;

        foreach ($lines as $line) {
            $paragraphId = trim((string) ($line['paragraph_id'] ?? ''));
            $lineText = trim((string) ($line['text'] ?? ''));

            if ($lineText === '') {
                continue;
            }

            if ($current === '') {
                $current = $lineText;
            } elseif ($paragraphId !== '' && $previousParagraphId !== null && $paragraphId !== $previousParagraphId) {
                $paragraphs[] = trim($current);
                $current = $lineText;
            } elseif (isLikelyEssayParagraphStart($lineText)) {
                $paragraphs[] = trim($current);
                $current = $lineText;
            } else {
                $current .= ' ' . $lineText;
            }

            if ($paragraphId !== '') {
                $previousParagraphId = $paragraphId;
            }
        }

        if (trim($current) !== '') {
            $paragraphs[] = trim($current);
        }

        return $paragraphs;
    }
}
if (!function_exists('decodeEssayRawResponsePayload')) {
    function decodeEssayRawResponsePayload($rawResponseJson)
    {
        if (!is_string($rawResponseJson) || trim($rawResponseJson) === '') {
            return [];
        }

        $decoded = json_decode($rawResponseJson, true);
        return is_array($decoded) ? $decoded : [];
    }
}
if (!function_exists('prepareEssayAnnotations')) {
    function prepareEssayAnnotations(array $annotations)
    {
        $prepared = [];
        $displayIndex = 1;

        foreach ($annotations as $annotation) {
            if (!is_array($annotation)) {
                continue;
            }

            $annotation['display_index'] = $displayIndex++;
            $annotation['line_id'] = normalizeEssayLineId($annotation['line_id'] ?? '');
            $annotation['start'] = isset($annotation['start']) ? (int) $annotation['start'] : null;
            $annotation['end'] = isset($annotation['end']) ? (int) $annotation['end'] : null;
            $annotation['source'] = (string) ($annotation['source'] ?? 'ai');
            $annotation['color'] = (string) ($annotation['color'] ?? 'yellow');
            $prepared[] = $annotation;
        }

        return $prepared;
    }
}
if (!function_exists('groupEssayAnnotationsByLine')) {
    function groupEssayAnnotationsByLine(array $annotations)
    {
        $grouped = [];
        foreach ($annotations as $annotation) {
            $lineId = normalizeEssayLineId($annotation['line_id'] ?? '');
            if ($lineId === '') {
                continue;
            }
            $grouped[$lineId][] = $annotation;
        }

        foreach ($grouped as $lineId => $items) {
            usort($items, function ($a, $b) {
                return ((int) ($a['start'] ?? 0)) <=> ((int) ($b['start'] ?? 0));
            });
            $grouped[$lineId] = $items;
        }

        return $grouped;
    }
}
if (!function_exists('normalizeEssayLineId')) {
    function normalizeEssayLineId($lineId)
    {
        $lineId = trim((string) $lineId);
        if ($lineId === '') {
            return '';
        }
        if (preg_match('/^l\d+$/i', $lineId)) {
            return strtolower($lineId);
        }
        if (ctype_digit($lineId)) {
            return 'l' . $lineId;
        }
        if (preg_match('/(\d+)/', $lineId, $matches)) {
            return 'l' . $matches[1];
        }
        return strtolower($lineId);
    }
}
if (!function_exists('extractEssayLineNumber')) {
    function extractEssayLineNumber($lineId)
    {
        $normalized = normalizeEssayLineId($lineId);
        if (preg_match('/^l(\d+)$/', $normalized, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }
}
if (!function_exists('isLikelyEssayParagraphStart')) {
    function isLikelyEssayParagraphStart($lineText)
    {
        $lineText = ltrim((string) $lineText);
        if ($lineText === '') {
            return false;
        }
        return (bool) preg_match('/^(em primeiro lugar|primordialmente|ademais|nesse vi[eé]s|portanto|diante disso|consequentemente|assim|entretanto|no entanto|outrossim|por fim|em suma|em síntese)\b/iu', $lineText);
    }
}
if (!function_exists('buildEssayAnnotationTitle')) {
    function buildEssayAnnotationTitle(array $annotation)
    {
        $parts = [];
        if (($annotation['source'] ?? 'ai') === 'teacher') {
            $parts[] = 'Comentário do professor';
        }
        if (!empty($annotation['type'])) {
            $parts[] = 'Tipo: ' . $annotation['type'];
        }
        if (!empty($annotation['comment'])) {
            $parts[] = 'Comentário: ' . $annotation['comment'];
        }
        if (!empty($annotation['replacement'])) {
            $parts[] = 'Sugestão: ' . $annotation['replacement'];
        }

        return implode(' | ', $parts);
    }
}
if (!function_exists('buildEssayAnnotationClasses')) {
    function buildEssayAnnotationClasses(array $annotation)
    {
        $source = (string) ($annotation['source'] ?? 'ai');
        if ($source !== 'teacher') {
            return ['essay-ai-annotation', 'essay-ai-annotation-badge'];
        }

        $color = (string) ($annotation['color'] ?? 'blue');
        $allowedColors = ['yellow', 'blue', 'pink', 'green'];
        if (!in_array($color, $allowedColors, true)) {
            $color = 'blue';
        }

        return [
            'essay-teacher-annotation essay-teacher-annotation-' . $color,
            'essay-teacher-annotation-badge essay-teacher-annotation-badge-' . $color,
        ];
    }
}
if (!function_exists('renderEssayAnnotatedLine')) {
    function renderEssayAnnotatedLine($lineText, $lineId, array $annotationsByLine)
    {
        $lineText = (string) $lineText;
        $lineId = (string) $lineId;
        $annotations = $annotationsByLine[$lineId] ?? [];

        if (empty($annotations)) {
            return $lineText !== '' ? htmlspecialchars($lineText) : '&nbsp;';
        }

        $lineLength = mb_strlen($lineText, 'UTF-8');
        $cursor = 0;
        $html = '';

        foreach ($annotations as $annotation) {
            $start = isset($annotation['start']) ? (int) $annotation['start'] : null;
            $end = isset($annotation['end']) ? (int) $annotation['end'] : null;

            if ($start === null || $end === null || $start < 0 || $end <= $start || $start >= $lineLength) {
                continue;
            }

            $start = max($cursor, min($start, $lineLength));
            $end = min($end, $lineLength);
            if ($end <= $start) {
                continue;
            }

            $before = mb_substr($lineText, $cursor, $start - $cursor, 'UTF-8');
            if ($before !== '') {
                $html .= htmlspecialchars($before);
            }

            $selected = mb_substr($lineText, $start, $end - $start, 'UTF-8');
            if ($selected === '') {
                continue;
            }

            $annotationIdRaw = (string) ($annotation['id'] ?? ('line-' . $lineId . '-' . $start));
            $annotationId = 'annotation-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $annotationIdRaw);
            $title = buildEssayAnnotationTitle($annotation);
            [$annotationClass, $badgeClass] = buildEssayAnnotationClasses($annotation);
            $html .= '<button type="button"'
                . ' class="essay-inline-annotation ' . htmlspecialchars($annotationClass) . '"'
                . ' title="' . htmlspecialchars($title) . '"'
                . ' data-annotation-id="' . htmlspecialchars($annotationId) . '"'
                . ' data-annotation-type="' . htmlspecialchars((string) ($annotation['type'] ?? 'ajuste')) . '"'
                . ' data-annotation-source="' . htmlspecialchars((string) ($annotation['source'] ?? 'ai')) . '"'
                . ' data-annotation-selected="' . htmlspecialchars((string) ($annotation['selected_text'] ?? '')) . '"'
                . ' data-annotation-replacement="' . htmlspecialchars((string) ($annotation['replacement'] ?? '')) . '"'
                . ' data-annotation-comment="' . htmlspecialchars((string) ($annotation['comment'] ?? '')) . '">';
            $html .= htmlspecialchars($selected);
            $html .= '<sup class="' . htmlspecialchars($badgeClass) . '">' . (int) ($annotation['display_index'] ?? 0) . '</sup>';
            $html .= '</button>';

            $cursor = $end;
        }

        if ($cursor < $lineLength) {
            $html .= htmlspecialchars(mb_substr($lineText, $cursor, null, 'UTF-8'));
        }

        return $html !== '' ? $html : htmlspecialchars($lineText);
    }
}
$contentTextNormalized = normalizeEssayTextForDisplay($contentText);
$gradesJson = [];
$teacherGradesJson = [];
if ($correction && !empty($correction['grades_json'])) {
    $decoded = json_decode($correction['grades_json'], true);
    $gradesJson = is_array($decoded) ? $decoded : [];
}
if ($correction && !empty($correction['teacher_grades_json'])) {
    $decoded = json_decode($correction['teacher_grades_json'], true);
    $teacherGradesJson = is_array($decoded) ? $decoded : [];
}
$isEnemBoard = EssayCriteriaDisplayHelper::isEnemBoard(
    $proposal['board_name'] ?? '',
    $proposal['board_slug'] ?? ''
);
$criteriaDisplay = EssayCriteriaDisplayHelper::buildCriteriaDisplay(
    isset($criteria) && is_array($criteria) ? $criteria : [],
    $gradesJson,
    $isEnemBoard
);
$criteriaSectionTitle = EssayCriteriaDisplayHelper::getSectionTitle($isEnemBoard);
$criteriaSectionTitleSingular = EssayCriteriaDisplayHelper::getSectionTitle($isEnemBoard, false);
$maxTotalScore = EssayCriteriaDisplayHelper::calculateMaxTotal($criteriaDisplay, $isEnemBoard);
$highlightFeedbackTexts = [];
if ($correction) {
    if (!empty($correction['feedback_text'])) {
        $highlightFeedbackTexts[] = (string) $correction['feedback_text'];
    }
    if (!empty($correction['suggestions_text'])) {
        $highlightFeedbackTexts[] = (string) $correction['suggestions_text'];
    }
}
foreach ([$gradesJson, $teacherGradesJson] as $gradeSource) {
    foreach ($gradeSource as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!empty($item['feedback'])) {
            $highlightFeedbackTexts[] = (string) $item['feedback'];
        }
        if (!empty($item['description'])) {
            $highlightFeedbackTexts[] = (string) $item['description'];
        }
    }
}
$essayHighlightTerms = [];
foreach ($highlightFeedbackTexts as $feedbackText) {
    $essayHighlightTerms = array_merge($essayHighlightTerms, extractEssayIssueTermsFromFeedback($feedbackText));
}
$essayHighlightTerms = array_values(array_unique($essayHighlightTerms));
usort($essayHighlightTerms, function ($a, $b) {
    return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
});
$rawResponsePayload = $correction ? decodeEssayRawResponsePayload((string) ($correction['raw_response_json'] ?? '')) : [];
$aiAnnotationsRaw = $correction ? json_decode((string) ($correction['ai_annotations_json'] ?? ''), true) : [];
$teacherAnnotationsRaw = $correction ? json_decode((string) ($correction['teacher_annotations_json'] ?? ''), true) : [];
$aiAnnotations = prepareEssayAnnotations(is_array($aiAnnotationsRaw) ? $aiAnnotationsRaw : []);
$teacherAnnotations = prepareEssayAnnotations(is_array($teacherAnnotationsRaw) ? $teacherAnnotationsRaw : []);
$allAnnotations = array_merge($aiAnnotations, $teacherAnnotations);
$annotationsByLine = groupEssayAnnotationsByLine($allAnnotations);
$displayStructuredLines = $flattenedStructuredLines;
// Modo visual "bonito" (parágrafo contínuo) quando não há anotações a preservar por linha.
$useParagraphVisualMode = true;
if (empty($allAnnotations) && empty($flattenedStructuredLines) && trim((string) $contentTextNormalized) !== '') {
    $displayStructuredLines = EssayTextStructureHelper::flatten(
        EssayTextStructureHelper::buildFromPlainText((string) $contentTextNormalized)
    );
}
$aiC5Elements = !empty($rawResponsePayload['c5_elements']) && is_array($rawResponsePayload['c5_elements'])
    ? $rawResponsePayload['c5_elements']
    : [];
$aiGeneralComment = (string) ($rawResponsePayload['general_comment'] ?? '');
$rawResponsePrettyJson = !empty($rawResponsePayload)
    ? json_encode($rawResponsePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : '';

$issueTextsForReview = [];
foreach ($essayHighlightTerms as $term) {
    $term = trim((string) $term);
    if ($term !== '') {
        $issueTextsForReview[] = $term;
    }
}
foreach ($teacherAnnotations as $annotation) {
    $selectedText = trim((string) ($annotation['selected_text'] ?? ''));
    if ($selectedText !== '') {
        $issueTextsForReview[] = $selectedText;
    }
}
$issueTextsForReview = array_values(array_unique($issueTextsForReview));
usort($issueTextsForReview, function ($a, $b) {
    return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
});
?>
<style>
    /* Pauta: linha a cada 28px alinhada ao texto (padding 16px = 1rem) */
    #textoRedacaoLeitura.redacao-pautada,
    #textareaTextoRedacao.redacao-pautada {
        background-color: #fefefe !important;
        background-image: repeating-linear-gradient( transparent 0px, transparent 27px, rgba(203, 213, 225, 0.7) 27px, rgba(203, 213, 225, 0.7) 28px ) !important;
        background-position: 0 16px !important;
        line-height: 28px !important;
    }
    #textoRedacaoLeitura .paragrafo-redacao {
        text-indent: 2em;
        margin: 0;
        padding: 0 12px;
        line-height: 28px;
        font-size: 1rem;
        white-space: normal;
        text-align: justify;
        text-align-last: left;
        text-justify: inter-word;
        hyphens: none;
    }
    #textoRedacaoLeitura .paragrafo-redacao + .paragrafo-redacao {
        padding-top: 28px;
    }
    #textareaTextoRedacao {
        text-indent: 2em;
    }
    #textoRedacaoLeitura .essay-structured-row {
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr);
        gap: 0;
        align-items: start;
        min-height: 28px;
    }
    #textoRedacaoLeitura .essay-structured-number {
        color: #9ca3af;
        font-size: 12px;
        text-align: center;
        border-right: 1px solid #d1d5db;
        user-select: none;
        line-height: 28px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    #textoRedacaoLeitura .essay-structured-text {
        white-space: pre-wrap;
        word-break: break-word;
        text-align: left;
        text-align-last: auto;
        hyphens: none;
        font-size: 1rem;
        line-height: 28px;
        padding: 0 12px;
        font-family: 'Times New Roman', Georgia, serif;
    }
    #textoRedacaoLeitura .essay-structured-text--paragraph-start {
        text-indent: 2em;
        margin-top: 16px;
    }
    #textoRedacaoLeitura .essay-ai-annotation {
        background: rgba(253, 230, 138, 0.6);
        border-bottom: 2px solid #f59e0b;
        border-radius: 2px;
        padding: 0 1px;
        text-decoration: none;
        color: inherit;
    }
    #textoRedacaoLeitura .essay-ai-annotation:hover {
        background: rgba(252, 211, 77, 0.75);
    }
    #textoRedacaoLeitura .essay-inline-annotation {
        appearance: none;
        border: 0;
        font: inherit;
        cursor: pointer;
        display: inline;
    }
    #textoRedacaoLeitura .essay-ai-annotation-badge {
        display: inline-block;
        min-width: 16px;
        margin-left: 2px;
        padding: 0 4px;
        border-radius: 9999px;
        background: #7c3aed;
        color: #fff;
        font-size: 10px;
        line-height: 16px;
        vertical-align: top;
        user-select: none;
        pointer-events: none;
    }
    #textoRedacaoLeitura .essay-teacher-annotation {
        background: rgba(191, 219, 254, 0.75);
        border-bottom: 2px solid #2563eb;
        border-radius: 2px;
        padding: 0 1px;
        text-decoration: none;
        color: inherit;
    }
    #textoRedacaoLeitura .essay-teacher-annotation:hover {
        background: rgba(147, 197, 253, 0.92);
    }
    #textoRedacaoLeitura .essay-teacher-annotation-badge {
        display: inline-block;
        min-width: 16px;
        margin-left: 2px;
        padding: 0 4px;
        border-radius: 9999px;
        background: #2563eb;
        color: #fff;
        font-size: 10px;
        line-height: 16px;
        vertical-align: top;
        user-select: none;
        pointer-events: none;
    }
    #textoRedacaoLeitura .essay-teacher-annotation-yellow {
        background: rgba(254, 240, 138, 0.82);
        border-bottom-color: #ca8a04;
    }
    #textoRedacaoLeitura .essay-teacher-annotation-blue {
        background: rgba(191, 219, 254, 0.75);
        border-bottom-color: #2563eb;
    }
    #textoRedacaoLeitura .essay-teacher-annotation-pink {
        background: rgba(251, 207, 232, 0.8);
        border-bottom-color: #db2777;
    }
    #textoRedacaoLeitura .essay-teacher-annotation-green {
        background: rgba(187, 247, 208, 0.8);
        border-bottom-color: #16a34a;
    }
    #textoRedacaoLeitura .essay-teacher-annotation-badge-yellow {
        background: #ca8a04;
    }
    #textoRedacaoLeitura .essay-teacher-annotation-badge-blue {
        background: #2563eb;
    }
    #textoRedacaoLeitura .essay-teacher-annotation-badge-pink {
        background: #db2777;
    }
    #textoRedacaoLeitura .essay-teacher-annotation-badge-green {
        background: #16a34a;
    }
    .teacher-annotation-help {
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 14px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1e3a8a;
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 14px;
        line-height: 1.45;
    }
    .teacher-annotation-help i {
        margin-top: 2px;
    }
    .teacher-annotation-modal {
        position: fixed;
        inset: 0;
        z-index: 120;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(17, 24, 39, 0.55);
    }
    .teacher-annotation-modal.hidden {
        display: none;
    }
    .teacher-annotation-dialog {
        width: 100%;
        max-width: 680px;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 25px 60px rgba(15, 23, 42, 0.28);
        border: 1px solid #dbeafe;
        padding: 24px;
    }
    .teacher-annotation-selection {
        min-height: 68px;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px 14px;
        color: #334155;
        font-size: 14px;
    }
    .teacher-selection-action {
        position: fixed;
        z-index: 130;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid #bfdbfe;
        background: rgba(255, 255, 255, 0.98);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
        backdrop-filter: blur(12px);
    }
    .teacher-selection-action.hidden {
        display: none;
    }
    .essay-annotation-popover {
        position: fixed;
        z-index: 140;
        width: min(420px, calc(100vw - 24px));
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
        padding: 16px;
    }
    .essay-annotation-popover.hidden {
        display: none;
    }
</style>
<?php
$originalSubmissionUrl = URL . '/professor/redacao-configuravel/envios/' . (int) ($submission['id'] ?? 0) . '/original';
$submissionImagePath = strtolower((string) ($submission['content_image_path'] ?? ''));
$isOriginalPdf = (bool) preg_match('/\.pdf(\?.*)?$/', $submissionImagePath);
$submissionMode = $proposal['submission_mode'] ?? 'texto';
$imageAnnotations = null;
if ($correction && !empty($correction['image_annotations_json'])) {
    $imageAnnotations = json_decode((string) $correction['image_annotations_json'], true);
}
$isPhotoCentric = in_array($submissionMode, ['foto', 'texto_ou_foto'], true) && !empty($submission['content_image_path']) && !$isOriginalPdf;
?>
<?php if (!empty($submission['content_image_path']) && !$isOriginalPdf): ?>
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-indigo-200 p-6 mb-6" id="blocoImagemRedacao">
    <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Correção na foto da redação</h3>
            <p class="text-sm text-gray-600">Use caneta ou dedo no tablet para rabiscar, destacar e escrever comentários sobre a imagem.</p>
        </div>
        <a href="<?= htmlspecialchars($originalSubmissionUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm font-medium">
            <i class="fas fa-external-link-alt"></i> Abrir original
        </a>
    </div>
    <?php
    $annotator_id = 'essayAnnotatorTeacher';
    $image_url = $originalSubmissionUrl;
    $initial_annotations = is_array($imageAnnotations) ? $imageAnnotations : null;
    $readonly = false;
    $submission_id = (int) ($submission['id'] ?? 0);
    $save_url = URL . '/professor/redacao-configuravel/envios/' . $submission_id . '/anotacoes-imagem';
    include __DIR__ . '/../../components/essay_image_annotator.php';
    ?>
</div>
<?php endif; ?>
<div id="redacao"></div>
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6 mb-6" id="blocoTextoRedacao" <?= ($submissionMode === 'foto' && empty(trim($contentText))) ? 'style="display:none"' : '' ?>>
    <div class="flex justify-between items-center mb-2">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Texto da redação</h3>
            <?php if ($transcriptionIdentifier !== ''): ?>
                <p class="text-xs text-gray-500 mt-1">Id Transcrição: <span class="font-mono"><?= htmlspecialchars($transcriptionIdentifier) ?></span></p>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= URL ?>/professor/redacao-configuravel/<?= (int)$proposal['id'] ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm font-medium">
                <i class="fas fa-book-open"></i> Ver tema e repertório
            </a>
            <button type="button" id="btnEditarRedacao" class="inline-flex items-center gap-2 px-3 py-1.5 bg-amber-100 text-amber-800 rounded-lg hover:bg-amber-200 text-sm font-medium">
                <i class="fas fa-edit"></i> Editar
            </button>
            <button type="button" id="btnSalvarTextoRedacao" class="hidden inline-flex items-center gap-2 px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                <i class="fas fa-save"></i> Salvar
            </button>
            <button type="button" id="btnCancelarEditarRedacao" class="hidden inline-flex items-center gap-2 px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
                Cancelar
            </button>
            <?php if (!empty($submission['content_image_path'])): ?>
            <a href="<?= URL ?>/professor/redacao-configuravel/envios/<?= (int)$submission['id'] ?>/original" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 text-sm font-medium">
                <i class="fas fa-external-link-alt"></i> Visualizar original
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="teacher-annotation-help">
        <i class="fas fa-highlighter"></i>
        <div>
            <div class="font-semibold">Marcações do professor no texto</div>
            <div>Selecione um trecho da redação em modo leitura para destacar e adicionar um comentário. Essas observações também aparecerão para o aluno.</div>
        </div>
    </div>
    <?php if (!empty($issueTextsForReview)): ?>
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3">
        <h4 class="text-sm font-semibold text-rose-900 mb-2">Trechos a revisar (impactam <?= htmlspecialchars(mb_strtolower($criteriaSectionTitle)) ?>)</h4>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($issueTextsForReview as $issueText): ?>
                <span class="inline-flex items-center rounded-full bg-white border border-rose-200 px-3 py-1 text-xs text-rose-800">"<?= htmlspecialchars($issueText) ?>"</span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <div id="textoRedacaoLeitura" class="redacao-pautada text-gray-800 border border-gray-200 rounded-lg px-4 py-4 min-h-[400px] max-h-[600px] overflow-y-auto"><?php
        if (!empty($displayStructuredLines) && !$useParagraphVisualMode) {
            $previousParagraphId = null;
            foreach ($displayStructuredLines as $line) {
                $lineId = normalizeEssayLineId($line['line_id'] ?? '');
                $lineNumber = extractEssayLineNumber($line['line_id'] ?? '');
                $lineText = (string) ($line['text'] ?? '');
                $paragraphId = trim((string) ($line['paragraph_id'] ?? ''));
                $isParagraphStart = !empty($line['is_paragraph_start']) || ($paragraphId !== '' && $paragraphId !== $previousParagraphId) || ($lineNumber > 1 && isLikelyEssayParagraphStart($lineText));
                $textClasses = 'essay-structured-text' . ($isParagraphStart ? ' essay-structured-text--paragraph-start' : '');
                $previousParagraphId = $paragraphId !== '' ? $paragraphId : $previousParagraphId;
                echo '<div class="essay-structured-row" data-line-id="' . htmlspecialchars($lineId) . '">';
                echo '<div class="essay-structured-number">' . ($lineNumber > 0 ? sprintf('%02d', $lineNumber) : '&nbsp;') . '</div>';
                echo '<div class="' . htmlspecialchars($textClasses) . '" data-line-id="' . htmlspecialchars($lineId) . '" data-line-raw="' . htmlspecialchars($lineText, ENT_QUOTES, 'UTF-8') . '">' . ($lineText !== '' ? renderEssayAnnotatedLine($lineText, $lineId, $annotationsByLine) : '&nbsp;') . '</div>';
                echo '</div>';
            }
        } elseif (!empty($displayStructuredLines)) {
            $paragraphsFromStructured = buildEssayParagraphsFromStructuredLines($displayStructuredLines);
            $paragraphLineIndex = 1;
            foreach ($paragraphsFromStructured as $paragraph) {
                if (trim($paragraph) !== '') {
                    $lineId = 'p' . $paragraphLineIndex++;
                    echo '<p class="paragrafo-redacao essay-structured-text" data-line-id="' . htmlspecialchars($lineId) . '" data-line-raw="' . htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') . '">' . nl2br(htmlspecialchars($paragraph)) . '</p>';
                }
            }
        } else {
        $textoParaExibir = trim($contentTextNormalized ?? '');
        if ($textoParaExibir === '') {
            echo '<span class="text-gray-500">(Sem texto)</span>';
        } else {
            $paragrafos = preg_split('/\n\s*\n/', $textoParaExibir, -1, PREG_SPLIT_NO_EMPTY);
            $paragraphLineIndex = 1;
            foreach ($paragrafos as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $lineId = 'p' . $paragraphLineIndex++;
                    echo '<p class="paragrafo-redacao essay-structured-text" data-line-id="' . htmlspecialchars($lineId) . '" data-line-raw="' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '">' . nl2br(htmlspecialchars($p)) . '</p>';
                }
            }
        }
        }
    ?></div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">Dica: selecione um trecho e depois clique no botão ao lado para comentar.</p>
        <button type="button" id="btnAbrirAnotacaoProfessor" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="fas fa-comment-medical mr-2"></i>Adicionar comentário ao trecho
        </button>
    </div>
    <div id="textoRedacaoEdicao" class="hidden">
        <textarea id="textareaTextoRedacao" class="redacao-pautada w-full min-h-[400px] p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-y font-sans text-gray-800" placeholder="Digite ou edite o texto da redação..."><?= htmlspecialchars($contentTextNormalized) ?></textarea>
    </div>
</div>
<div id="teacherSelectionAction" class="teacher-selection-action hidden" aria-hidden="true">
    <span class="text-xs font-medium text-slate-600">Trecho selecionado</span>
    <button type="button" id="btnComentarSelecaoProfessor" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
        <i class="fas fa-comment-dots mr-1"></i> Comentar
    </button>
</div>
<div id="essayAnnotationPopover" class="essay-annotation-popover hidden" aria-hidden="true">
    <div class="flex items-start justify-between gap-3 mb-3">
        <div>
            <div id="essayAnnotationPopoverTitle" class="text-sm font-semibold text-slate-900">Comentário</div>
            <div id="essayAnnotationPopoverType" class="text-xs text-slate-500 mt-1"></div>
        </div>
        <button type="button" id="btnFecharPopoverAnotacao" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
    </div>
    <div class="space-y-3 text-sm">
        <div>
            <div class="font-medium text-slate-700 mb-1">Trecho marcado</div>
            <div id="essayAnnotationPopoverSelected" class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-slate-700"></div>
        </div>
        <div id="essayAnnotationPopoverReplacementWrap" class="hidden">
            <div class="font-medium text-emerald-700 mb-1">Sugestão</div>
            <div id="essayAnnotationPopoverReplacement" class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-emerald-800"></div>
        </div>
        <div>
            <div class="font-medium text-slate-700 mb-1">Comentário</div>
            <div id="essayAnnotationPopoverComment" class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-slate-700 whitespace-pre-wrap"></div>
        </div>
    </div>
</div>
<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
    var submissionId = <?= (int)$submission['id'] ?>;
    var token = <?= json_encode($csrf_token) ?>;
    var highlightTerms = <?= json_encode(array_values($essayHighlightTerms), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var bloco = document.getElementById('blocoTextoRedacao');
    var btnEditar = document.getElementById('btnEditarRedacao');
    var btnSalvar = document.getElementById('btnSalvarTextoRedacao');
    var btnCancelar = document.getElementById('btnCancelarEditarRedacao');
    var divLeitura = document.getElementById('textoRedacaoLeitura');
    var divEdicao = document.getElementById('textoRedacaoEdicao');
    var textarea = document.getElementById('textareaTextoRedacao');
    var textoOriginal = textarea.value;
    var btnAbrirAnotacaoProfessor = document.getElementById('btnAbrirAnotacaoProfessor');
    var modalAnotacaoProfessor = document.getElementById('modalAnotacaoProfessor');
    var btnFecharModalAnotacao = document.getElementById('btnFecharModalAnotacao');
    var btnCancelarAnotacaoProfessor = document.getElementById('btnCancelarAnotacaoProfessor');
    var btnSalvarAnotacaoProfessor = document.getElementById('btnSalvarAnotacaoProfessor');
    var textoTrechoSelecionado = document.getElementById('textoTrechoSelecionado');
    var anotacaoReplacement = document.getElementById('anotacaoReplacement');
    var anotacaoComment = document.getElementById('anotacaoComment');
    var anotacaoColor = document.getElementById('anotacaoColor');
    var anotacaoProfessorErro = document.getElementById('anotacaoProfessorErro');
    var teacherSelectionAction = document.getElementById('teacherSelectionAction');
    var btnComentarSelecaoProfessor = document.getElementById('btnComentarSelecaoProfessor');
    var essayAnnotationPopover = document.getElementById('essayAnnotationPopover');
    var btnFecharPopoverAnotacao = document.getElementById('btnFecharPopoverAnotacao');
    var essayAnnotationPopoverTitle = document.getElementById('essayAnnotationPopoverTitle');
    var essayAnnotationPopoverType = document.getElementById('essayAnnotationPopoverType');
    var essayAnnotationPopoverSelected = document.getElementById('essayAnnotationPopoverSelected');
    var essayAnnotationPopoverReplacementWrap = document.getElementById('essayAnnotationPopoverReplacementWrap');
    var essayAnnotationPopoverReplacement = document.getElementById('essayAnnotationPopoverReplacement');
    var essayAnnotationPopoverComment = document.getElementById('essayAnnotationPopoverComment');
    var currentTeacherSelection = null;
    var lastTeacherSelection = null;
    var suppressTeacherSelectionRefreshUntil = 0;

    function rawTextFromLeitura() {
        var span = divLeitura.querySelector('.text-gray-500');
        if (span) return '';
        var structuredRows = divLeitura.querySelectorAll('.essay-structured-row');
        if (structuredRows.length) {
            var structuredParts = [];
            for (var i = 0; i < structuredRows.length; i++) {
                var structuredText = structuredRows[i].querySelector('.essay-structured-text');
                structuredParts.push(structuredText ? structuredText.textContent.trim() : '');
            }
            return structuredParts.join('\n');
        }
        var paras = divLeitura.querySelectorAll('.paragrafo-redacao');
        if (!paras.length) return '';
        var parts = [];
        for (var i = 0; i < paras.length; i++) parts.push(paras[i].textContent.trim());
        return parts.join('\n\n');
    }
    function normalizeEssayText(t) {
        if (!t || !t.trim()) return '';
        // Normaliza EOL
        t = t.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        // Quebra em parágrafos reais (linha em branco)
        var paras = t.trim().split(/\n\s*\n/);
        var normalized = [];
        for (var i = 0; i < paras.length; i++) {
            var p = (paras[i] || '').trim();
            if (!p) continue;
            // Junta linhas artificiais do OCR
            p = p.replace(/\n+/g, ' ').replace(/[ \t]{2,}/g, ' ').trim();
            if (p) normalized.push(p);
        }
        return normalized.join('\n\n');
    }
    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    function escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    function highlightParagraph(text) {
        var normalizedTerms = (highlightTerms || []).filter(function(term) {
            return !!(term && term.trim());
        }).sort(function(a, b) {
            return b.length - a.length;
        });
        if (!normalizedTerms.length) {
            return escapeHtml(text).replace(/\n/g, '<br>');
        }

        var pattern = new RegExp('(' + normalizedTerms.map(escapeRegex).join('|') + ')', 'gi');
        var parts = text.split(pattern);
        var html = '';
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i];
            if (!part) continue;
            var matched = normalizedTerms.some(function(term) {
                return part.toLowerCase() === term.toLowerCase();
            });
            var safe = escapeHtml(part).replace(/\n/g, '<br>');
            if (matched) {
                html += '<span class="essay-issue-highlight" title="Trecho citado no feedback de correção">' + safe + '</span>';
            } else {
                html += safe;
            }
        }
        return html;
    }
    function renderParagrafos(t) {
        if (!t || !t.trim()) return '<span class="text-gray-500">(Sem texto)</span>';
        var txt = normalizeEssayText(t);
        if (!txt) return '<span class="text-gray-500">(Sem texto)</span>';
        var paras = txt.split(/\n\s*\n/);
        var html = '';
        for (var i = 0; i < paras.length; i++) {
            var p = paras[i].trim();
            if (p) html += '<p class="paragrafo-redacao essay-structured-text" data-line-id="p' + (i + 1) + '" data-line-raw="' + escapeHtml(p).replace(/"/g, '&quot;') + '">' + highlightParagraph(p) + '</p>';
        }
        return html || '<span class="text-gray-500">(Sem texto)</span>';
    }
    function clearTeacherSelection() {
        currentTeacherSelection = null;
        if (btnAbrirAnotacaoProfessor) btnAbrirAnotacaoProfessor.disabled = true;
        if (teacherSelectionAction) teacherSelectionAction.classList.add('hidden');
        if (textoTrechoSelecionado) textoTrechoSelecionado.textContent = 'Nenhum trecho selecionado.';
        if (anotacaoProfessorErro) {
            anotacaoProfessorErro.textContent = '';
            anotacaoProfessorErro.classList.add('hidden');
        }
    }
    function closeEssayAnnotationPopover() {
        if (essayAnnotationPopover) essayAnnotationPopover.classList.add('hidden');
    }
    function openEssayAnnotationPopover(trigger) {
        if (!essayAnnotationPopover || !trigger) return;
        var source = trigger.getAttribute('data-annotation-source') === 'teacher' ? 'Comentário do professor' : 'Anotação da IA';
        var type = trigger.getAttribute('data-annotation-type') || 'ajuste';
        var selected = trigger.getAttribute('data-annotation-selected') || '';
        var replacement = trigger.getAttribute('data-annotation-replacement') || '';
        var comment = trigger.getAttribute('data-annotation-comment') || '';
        essayAnnotationPopoverTitle.textContent = source;
        essayAnnotationPopoverType.textContent = type;
        essayAnnotationPopoverSelected.textContent = selected || 'Trecho não informado.';
        essayAnnotationPopoverComment.textContent = comment || 'Sem comentário adicional.';
        if (replacement) {
            essayAnnotationPopoverReplacement.textContent = replacement;
            essayAnnotationPopoverReplacementWrap.classList.remove('hidden');
        } else {
            essayAnnotationPopoverReplacementWrap.classList.add('hidden');
            essayAnnotationPopoverReplacement.textContent = '';
        }
        var rect = trigger.getBoundingClientRect();
        essayAnnotationPopover.style.visibility = 'hidden';
        essayAnnotationPopover.classList.remove('hidden');
        var popoverWidth = essayAnnotationPopover.offsetWidth;
        var popoverHeight = essayAnnotationPopover.offsetHeight;
        var top = rect.bottom + 10;
        var left = rect.left;
        var maxLeft = window.innerWidth - popoverWidth - 12;
        left = Math.max(12, Math.min(left, maxLeft));
        if (top + popoverHeight > window.innerHeight - 12) {
            top = rect.top - popoverHeight - 10;
        }
        top = Math.max(12, top);
        essayAnnotationPopover.style.top = top + 'px';
        essayAnnotationPopover.style.left = left + 'px';
        essayAnnotationPopover.style.visibility = '';
    }
    function positionTeacherSelectionAction(range) {
        if (!teacherSelectionAction || !range) return;
        var rect = range.getBoundingClientRect();
        if (!rect || (!rect.width && !rect.height)) {
            teacherSelectionAction.classList.add('hidden');
            return;
        }
        teacherSelectionAction.style.visibility = 'hidden';
        teacherSelectionAction.classList.remove('hidden');
        var actionWidth = teacherSelectionAction.offsetWidth;
        var actionHeight = teacherSelectionAction.offsetHeight;
        var top = rect.top - actionHeight - 14;
        var left = rect.left;
        if (top < 10) {
            top = rect.bottom + 10;
        }
        var maxLeft = window.innerWidth - actionWidth - 10;
        left = Math.max(10, Math.min(left, maxLeft));
        teacherSelectionAction.style.top = top + 'px';
        teacherSelectionAction.style.left = left + 'px';
        teacherSelectionAction.style.visibility = '';
    }
    function openTeacherAnnotationModal() {
        var selectionData = currentTeacherSelection || lastTeacherSelection;
        if (!modalAnotacaoProfessor || !selectionData) return;
        currentTeacherSelection = selectionData;
        lastTeacherSelection = selectionData;
        if (textoTrechoSelecionado) textoTrechoSelecionado.textContent = selectionData.selectedText;
        if (anotacaoReplacement) anotacaoReplacement.value = '';
        if (anotacaoComment) anotacaoComment.value = '';
        if (anotacaoColor) anotacaoColor.value = 'blue';
        if (anotacaoProfessorErro) {
            anotacaoProfessorErro.textContent = '';
            anotacaoProfessorErro.classList.add('hidden');
        }
        if (teacherSelectionAction) {
            teacherSelectionAction.classList.add('hidden');
        }
        modalAnotacaoProfessor.classList.remove('hidden');
        modalAnotacaoProfessor.setAttribute('aria-hidden', 'false');
        if (anotacaoComment) anotacaoComment.focus();
    }
    function closeTeacherAnnotationModal() {
        if (!modalAnotacaoProfessor) return;
        modalAnotacaoProfessor.classList.add('hidden');
        modalAnotacaoProfessor.setAttribute('aria-hidden', 'true');
    }
    function isBadgeNode(node) {
        if (!node) return false;
        var element = node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
        return !!(element && element.closest && element.closest('.essay-ai-annotation-badge, .essay-teacher-annotation-badge'));
    }
    function getSelectionOffsets(root, range) {
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function(node) {
                return isBadgeNode(node) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
            }
        });
        var start = 0;
        var end = 0;
        var foundStart = false;
        var foundEnd = false;
        var current;

        while ((current = walker.nextNode())) {
            var length = current.textContent.length;
            if (!foundStart) {
                if (current === range.startContainer) {
                    start += range.startOffset;
                    foundStart = true;
                } else {
                    start += length;
                }
            }

            if (!foundEnd) {
                if (current === range.endContainer) {
                    end += range.endOffset;
                    foundEnd = true;
                } else {
                    end += length;
                }
            }

            if (foundStart && foundEnd) {
                break;
            }
        }

        return {
            start: foundStart ? start : -1,
            end: foundEnd ? end : -1
        };
    }
    function refreshTeacherSelection() {
        if (modalAnotacaoProfessor && !modalAnotacaoProfessor.classList.contains('hidden')) {
            return;
        }
        if (Date.now() < suppressTeacherSelectionRefreshUntil) {
            return;
        }
        if (!divLeitura || divLeitura.classList.contains('hidden')) {
            clearTeacherSelection();
            return;
        }
        var selection = window.getSelection ? window.getSelection() : null;
        if (!selection || selection.rangeCount === 0) {
            clearTeacherSelection();
            return;
        }
        var range = selection.getRangeAt(0);
        if (!range || range.collapsed) {
            clearTeacherSelection();
            return;
        }
        var commonParent = range.commonAncestorContainer.nodeType === Node.ELEMENT_NODE
            ? range.commonAncestorContainer
            : range.commonAncestorContainer.parentElement;
        var lineElement = commonParent && commonParent.closest ? commonParent.closest('.essay-structured-text') : null;
        if (!lineElement || !divLeitura.contains(lineElement)) {
            clearTeacherSelection();
            return;
        }
        var startParent = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement;
        var endParent = range.endContainer.nodeType === Node.ELEMENT_NODE ? range.endContainer : range.endContainer.parentElement;
        var startLine = startParent && startParent.closest ? startParent.closest('.essay-structured-text') : null;
        var endLine = endParent && endParent.closest ? endParent.closest('.essay-structured-text') : null;
        if (!startLine || !endLine || startLine !== endLine) {
            clearTeacherSelection();
            return;
        }

        var offsets = getSelectionOffsets(lineElement, range);
        var selectedText = selection.toString().replace(/\s+/g, ' ').trim();
        if (offsets.start < 0 || offsets.end <= offsets.start || !selectedText) {
            clearTeacherSelection();
            return;
        }

        currentTeacherSelection = {
            lineId: lineElement.getAttribute('data-line-id') || '',
            start: offsets.start,
            end: offsets.end,
            selectedText: selectedText
        };
        lastTeacherSelection = {
            lineId: currentTeacherSelection.lineId,
            start: currentTeacherSelection.start,
            end: currentTeacherSelection.end,
            selectedText: currentTeacherSelection.selectedText
        };
        if (!currentTeacherSelection.lineId) {
            clearTeacherSelection();
            return;
        }
        if (btnAbrirAnotacaoProfessor) btnAbrirAnotacaoProfessor.disabled = false;
        positionTeacherSelectionAction(range);
        if (textoTrechoSelecionado) textoTrechoSelecionado.textContent = currentTeacherSelection.selectedText;
        if (anotacaoProfessorErro) {
            anotacaoProfessorErro.textContent = '';
            anotacaoProfessorErro.classList.add('hidden');
        }
    }
    function entrarEdicao() {
        clearTeacherSelection();
        divLeitura.classList.add('hidden');
        divEdicao.classList.remove('hidden');
        btnEditar.classList.add('hidden');
        btnSalvar.classList.remove('hidden');
        btnCancelar.classList.remove('hidden');
        textarea.value = normalizeEssayText(rawTextFromLeitura());
        textarea.focus();
    }
    function sairEdicao(atualizarLeitura) {
        divLeitura.classList.remove('hidden');
        divEdicao.classList.add('hidden');
        btnEditar.classList.remove('hidden');
        btnSalvar.classList.add('hidden');
        btnCancelar.classList.add('hidden');
        if (atualizarLeitura) {
            divLeitura.innerHTML = renderParagrafos(textarea.value);
        }
    }
    btnEditar.addEventListener('click', function() { entrarEdicao(); });
    btnCancelar.addEventListener('click', function() {
        textarea.value = textoOriginal;
        sairEdicao(false);
    });
    btnSalvar.addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        var fd = new FormData();
        fd.append('_token', token);
        fd.append('content_text', textarea.value);
        fetch('<?= URL ?>/professor/redacao-configuravel/envios/' + submissionId + '/atualizar-texto', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Salvar';
                if (d.success) {
                    textoOriginal = textarea.value;
                    sairEdicao(true);
                    clearTeacherSelection();
                } else {
                    alert(d.error || 'Erro ao salvar');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Salvar';
                alert('Erro de conexão');
            });
    });
    if (divLeitura) {
        ['mouseup', 'keyup'].forEach(function(eventName) {
            divLeitura.addEventListener(eventName, function() {
                setTimeout(refreshTeacherSelection, 0);
            });
        });
        divLeitura.addEventListener('click', function(e) {
            var annotationButton = e.target.closest('.essay-inline-annotation');
            if (!annotationButton) return;
            e.preventDefault();
            e.stopPropagation();
            openEssayAnnotationPopover(annotationButton);
        });
    }
    if (btnFecharPopoverAnotacao) {
        btnFecharPopoverAnotacao.addEventListener('click', closeEssayAnnotationPopover);
    }
    document.addEventListener('click', function(e) {
        if (essayAnnotationPopover && !essayAnnotationPopover.classList.contains('hidden')) {
            if (!essayAnnotationPopover.contains(e.target) && !e.target.closest('.essay-inline-annotation')) {
                closeEssayAnnotationPopover();
            }
        }
    });
    document.addEventListener('selectionchange', function() {
        if (!divLeitura || divLeitura.classList.contains('hidden')) return;
        setTimeout(refreshTeacherSelection, 0);
    });
    if (btnAbrirAnotacaoProfessor) {
        ['pointerdown', 'mousedown', 'touchstart'].forEach(function(eventName) {
            btnAbrirAnotacaoProfessor.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                suppressTeacherSelectionRefreshUntil = Date.now() + 500;
                openTeacherAnnotationModal();
            }, { passive: false });
        });
        btnAbrirAnotacaoProfessor.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    }
    if (btnComentarSelecaoProfessor) {
        ['pointerdown', 'mousedown', 'touchstart'].forEach(function(eventName) {
            btnComentarSelecaoProfessor.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                suppressTeacherSelectionRefreshUntil = Date.now() + 500;
                openTeacherAnnotationModal();
            }, { passive: false });
        });
        btnComentarSelecaoProfessor.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    }
    if (btnFecharModalAnotacao) {
        btnFecharModalAnotacao.addEventListener('click', closeTeacherAnnotationModal);
    }
    if (btnCancelarAnotacaoProfessor) {
        btnCancelarAnotacaoProfessor.addEventListener('click', closeTeacherAnnotationModal);
    }
    if (modalAnotacaoProfessor) {
        modalAnotacaoProfessor.addEventListener('click', function(e) {
            if (e.target === modalAnotacaoProfessor) {
                closeTeacherAnnotationModal();
            }
        });
    }
    window.addEventListener('scroll', function() {
        if (teacherSelectionAction && !teacherSelectionAction.classList.contains('hidden')) {
            teacherSelectionAction.classList.add('hidden');
        }
        closeEssayAnnotationPopover();
    }, true);
    if (btnSalvarAnotacaoProfessor) {
        btnSalvarAnotacaoProfessor.addEventListener('click', function() {
            if (!currentTeacherSelection) {
                if (anotacaoProfessorErro) {
                    anotacaoProfessorErro.textContent = 'Selecione um trecho antes de salvar o comentário.';
                    anotacaoProfessorErro.classList.remove('hidden');
                }
                return;
            }
            var commentValue = anotacaoComment ? anotacaoComment.value.trim() : '';
            if (!commentValue) {
                if (anotacaoProfessorErro) {
                    anotacaoProfessorErro.textContent = 'Escreva um comentário para salvar a anotação.';
                    anotacaoProfessorErro.classList.remove('hidden');
                }
                return;
            }

            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Salvando...';
            var fd = new FormData();
            fd.append('_token', token);
            fd.append('line_id', currentTeacherSelection.lineId);
            fd.append('start', String(currentTeacherSelection.start));
            fd.append('end', String(currentTeacherSelection.end));
            fd.append('selected_text', currentTeacherSelection.selectedText);
            fd.append('replacement', anotacaoReplacement ? anotacaoReplacement.value.trim() : '');
            fd.append('comment', commentValue);
            fd.append('color', anotacaoColor ? anotacaoColor.value : 'blue');
            fetch('<?= URL ?>/professor/redacao-configuravel/envios/' + submissionId + '/anotacoes', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    btn.disabled = false;
                    btn.textContent = 'Salvar comentário';
                    if (d.success) {
                        closeTeacherAnnotationModal();
                        window.location.reload();
                    } else if (anotacaoProfessorErro) {
                        anotacaoProfessorErro.textContent = d.error || 'Erro ao salvar anotação.';
                        anotacaoProfessorErro.classList.remove('hidden');
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.textContent = 'Salvar comentário';
                    if (anotacaoProfessorErro) {
                        anotacaoProfessorErro.textContent = 'Erro de conexão ao salvar a anotação.';
                        anotacaoProfessorErro.classList.remove('hidden');
                    }
                });
        });
    }
    document.querySelectorAll('.btn-remover-anotacao').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var annotationId = this.getAttribute('data-annotation-id') || '';
            if (!annotationId) return;
            if (!confirm('Remover este comentário do professor?')) return;
            var fd = new FormData();
            fd.append('_token', token);
            fd.append('annotation_id', annotationId);
            fetch('<?= URL ?>/professor/redacao-configuravel/envios/' + submissionId + '/anotacoes/remover', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        window.location.reload();
                    } else {
                        alert(d.error || 'Erro ao remover anotação.');
                    }
                })
                .catch(function() {
                    alert('Erro de conexão ao remover a anotação.');
                });
        });
    });
    });
})();
</script>

<div id="correcao" class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Correção (<?= htmlspecialchars($proposal['board_name'] ?? 'Banca') ?>)</h3>
    <?php
    $hasActivePrompt = !empty($has_active_prompt);
    $hasAiCorrection = $correction && !empty(trim($correction['grades_json'] ?? '')) && trim($correction['grades_json']) !== '[]';
    $canUseAiCorrection = $hasActivePrompt || $hasAiCorrection;
    $hasTeacherAudio = $correction && !empty(trim($correction['teacher_feedback_audio_key'] ?? ''));
    $audioFeedbackListenUrl = URL . '/professor/redacao-configuravel/envios/' . (int) $submission['id'] . '/audio-feedback';
    ?>
    <div class="flex flex-wrap gap-3 mb-4 items-center">
        <?php if ($hasActivePrompt): ?>
        <button type="button" id="btnRunAI" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700">Gerar correção por IA</button>
        <?php endif; ?>
        <button type="button" id="btnCorrecaoProfessor" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Correção professor</button>
        <?php if ($hasAiCorrection): ?>
        <button type="button" id="btnRemoverCorrecaoIA" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Remover correção por IA</button>
        <?php endif; ?>
        <button type="button" id="btnAbrirModalAudio" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 inline-flex items-center gap-2">
            <i class="fas fa-microphone"></i> Enviar áudio para aluno
        </button>
        <?php if ($hasTeacherAudio): ?>
        <span class="text-sm text-teal-700 font-medium inline-flex items-center gap-2">
            <i class="fas fa-check-circle"></i> Áudio ao aluno enviado
            <a href="<?= htmlspecialchars($audioFeedbackListenUrl) ?>" target="_blank" rel="noopener" class="text-teal-800 underline hover:no-underline">Ouvir</a>
        </span>
        <?php endif; ?>
    </div>

    <div id="correctionResult" class="hidden mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800"></div>
    <?php
    $hasTeacherData = $correction && (isset($correction['teacher_grades_json']) && $correction['teacher_grades_json'] !== '' && $correction['teacher_grades_json'] !== '[]');
    $showCorrectionBlock = $correction && ($hasTeacherData || $hasAiCorrection);
    ?>
    <?php if (!empty($aiAnnotations)): ?>
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <h4 class="text-md font-semibold text-amber-900 mb-3">Marcações da IA no texto</h4>
        <div class="space-y-3">
            <?php foreach ($aiAnnotations as $annotation): ?>
            <?php
            $annotationId = 'annotation-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($annotation['id'] ?? ''));
            $lineIdLabel = (string) ($annotation['line_id'] ?? '—');
            $annotationType = (string) ($annotation['type'] ?? 'ajuste');
            $selectedText = (string) ($annotation['selected_text'] ?? '');
            $replacementText = (string) ($annotation['replacement'] ?? '');
            $commentText = (string) ($annotation['comment'] ?? '');
            ?>
            <div id="<?= htmlspecialchars($annotationId) ?>" class="rounded-lg border border-amber-200 bg-white p-3 shadow-sm scroll-mt-24">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-purple-600 px-2 text-xs font-bold text-white"><?= (int) ($annotation['display_index'] ?? 0) ?></span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-amber-700"><?= htmlspecialchars($annotationType) ?></span>
                        <span class="text-xs text-gray-500">Linha <?= htmlspecialchars($lineIdLabel) ?></span>
                    </div>
                    <button type="button" class="btn-remover-anotacao text-xs font-semibold text-red-600 hover:text-red-700" data-annotation-id="<?= htmlspecialchars((string) ($annotation['id'] ?? '')) ?>">
                        Remover
                    </button>
                </div>
                <?php if ($selectedText !== ''): ?>
                <p class="text-sm text-gray-700"><span class="font-semibold">Trecho:</span> “<?= htmlspecialchars($selectedText) ?>”</p>
                <?php endif; ?>
                <?php if ($replacementText !== ''): ?>
                <p class="text-sm text-green-700 mt-1"><span class="font-semibold">Sugestão:</span> <?= htmlspecialchars($replacementText) ?></p>
                <?php endif; ?>
                <?php if ($commentText !== ''): ?>
                <p class="text-sm text-gray-600 mt-2 whitespace-pre-wrap"><?= htmlspecialchars($commentText) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($teacherAnnotations)): ?>
    <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
        <h4 class="text-md font-semibold text-blue-900 mb-3">Marcações do professor</h4>
        <div class="space-y-3">
            <?php foreach ($teacherAnnotations as $annotation): ?>
            <?php
            $annotationId = 'annotation-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($annotation['id'] ?? ''));
            $lineIdLabel = (string) ($annotation['line_id'] ?? '—');
            $selectedText = (string) ($annotation['selected_text'] ?? '');
            $replacementText = (string) ($annotation['replacement'] ?? '');
            $commentText = (string) ($annotation['comment'] ?? '');
            ?>
            <div id="<?= htmlspecialchars($annotationId) ?>" class="rounded-lg border border-blue-200 bg-white p-3 shadow-sm scroll-mt-24">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-blue-600 px-2 text-xs font-bold text-white"><?= (int) ($annotation['display_index'] ?? 0) ?></span>
                        <span class="text-xs font-semibold uppercase tracking-wide text-blue-700">Comentário do professor</span>
                        <span class="text-xs text-gray-500">Linha <?= htmlspecialchars($lineIdLabel) ?></span>
                    </div>
                    <button type="button" class="btn-remover-anotacao text-xs font-semibold text-red-600 hover:text-red-700" data-annotation-id="<?= htmlspecialchars((string) ($annotation['id'] ?? '')) ?>">
                        Remover
                    </button>
                </div>
                <?php if ($selectedText !== ''): ?>
                <p class="text-sm text-gray-700"><span class="font-semibold">Trecho:</span> “<?= htmlspecialchars($selectedText) ?>”</p>
                <?php endif; ?>
                <?php if ($replacementText !== ''): ?>
                <p class="text-sm text-green-700 mt-1"><span class="font-semibold">Sugestão:</span> <?= htmlspecialchars($replacementText) ?></p>
                <?php endif; ?>
                <?php if ($commentText !== ''): ?>
                <p class="text-sm text-gray-600 mt-2 whitespace-pre-wrap"><?= htmlspecialchars($commentText) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($aiC5Elements)): ?>
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
        <h4 class="text-md font-semibold text-emerald-900 mb-3">Elementos da proposta de intervenção (C5)</h4>
        <div class="flex flex-wrap gap-2">
            <?php
            $c5Labels = [
                'agent' => 'Agente',
                'action' => 'Ação',
                'means' => 'Meio/Modo',
                'effect' => 'Efeito',
                'detail' => 'Detalhamento',
            ];
            foreach ($c5Labels as $key => $label):
                $isPresent = !empty($aiC5Elements[$key]);
            ?>
            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium <?= $isPresent ? 'bg-emerald-600 text-white' : 'bg-white text-emerald-800 border border-emerald-200' ?>">
                <span><?= $isPresent ? '✓' : '○' ?></span>
                <span><?= htmlspecialchars($label) ?></span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($aiGeneralComment !== ''): ?>
    <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <h4 class="text-md font-semibold text-slate-900 mb-2">Comentário geral retornado pela IA</h4>
        <p class="text-sm text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars($aiGeneralComment) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($rawResponsePrettyJson !== ''): ?>
    <details class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Ver retorno completo da IA</summary>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-100"><?= htmlspecialchars($rawResponsePrettyJson) ?></pre>
    </details>
    <?php endif; ?>

    <div id="blocoCorrecaoProfessor" class="<?= $showCorrectionBlock ? '' : 'hidden' ?>">
    <form id="correctionForm" class="space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <?php if ($canUseAiCorrection): ?>
        <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-100">
            <div class="flex items-center gap-2">
                <input type="checkbox" id="use_average" name="use_average" value="1" <?= ($correction && !empty($correction['use_average'])) ? 'checked' : '' ?> class="h-4 w-4 text-purple-600 rounded">
                <label for="use_average" class="text-sm font-medium text-gray-700">Usar média entre nota da IA e minha nota</label>
            </div>
            <p class="text-xs text-gray-600 mt-2 ml-6">Sua nota de professor é o que você preenche em cada <?= htmlspecialchars(mb_strtolower($criteriaSectionTitleSingular)) ?> abaixo. Ao salvar, a nota final do aluno será a média entre a nota da IA e a soma das suas notas (máx. <?= number_format($maxTotalScore, 0, ',', '.') ?>).</p>
        </div>
        <?php endif; ?>

        <?php
        $aiTotal = isset($correction['ai_total_score']) ? (float)$correction['ai_total_score'] : null;
        if ($aiTotal === null && !empty($gradesJson)) {
            $aiTotal = 0;
            foreach ($gradesJson as $item) {
                if (is_array($item) && isset($item['score'])) $aiTotal += (float)$item['score'];
                elseif (is_numeric($item)) $aiTotal += (float)$item;
            }
        }
        $teacherTotal = isset($correction['teacher_total_score']) ? (float)$correction['teacher_total_score'] : null;
        $displayTotal = isset($correction['total_score']) ? (float)$correction['total_score'] : null;
        ?>
        <div id="resumoNotas" class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 grid grid-cols-1 sm:grid-cols-<?= $canUseAiCorrection ? '3' : '2' ?> gap-4">
            <?php if ($canUseAiCorrection): ?>
            <div class="text-center p-3 bg-amber-50 rounded-lg border border-amber-200">
                <p class="text-xs font-medium text-amber-800 uppercase tracking-wide">Nota da IA</p>
                <p id="displayNotaIa" class="text-2xl font-bold text-amber-700"><?= $aiTotal !== null ? number_format($aiTotal, 0, ',', '.') : '—' ?></p>
            </div>
            <?php endif; ?>
            <div class="text-center p-3 bg-blue-50 rounded-lg border border-blue-200">
                <p class="text-xs font-medium text-blue-800 uppercase tracking-wide">Minha nota</p>
                <p id="displayMinhaNota" class="text-2xl font-bold text-blue-700"><?= $teacherTotal !== null ? number_format($teacherTotal, 0, ',', '.') : '—' ?></p>
            </div>
            <div class="text-center p-3 bg-green-50 rounded-lg border border-green-200">
                <p class="text-xs font-medium text-green-800 uppercase tracking-wide"><?= $canUseAiCorrection ? 'Nota final (média)' : 'Nota final' ?></p>
                <p id="displayMedia" class="text-2xl font-bold text-green-700"><?= $displayTotal !== null ? number_format($displayTotal, 0, ',', '.') : '—' ?></p>
            </div>
        </div>
        <?php if ($canUseAiCorrection): ?>
        <p class="text-xs text-gray-500 mb-4">A "Nota final" é a média entre a nota da IA e a sua quando a opção acima estiver marcada; caso contrário, é a sua nota.</p>
        <?php endif; ?>

        <h4 class="text-md font-semibold text-gray-800 border-b pb-2"><?= htmlspecialchars($criteriaSectionTitle) ?> — preencha sua nota em cada campo</h4>
        <?php
        $pos = 0;
        foreach ($criteriaDisplay as $c):
            $slug = $c['slug'];
            $name = $c['name'];
            $max = (float)$c['max_score'];
            $pos++;
            $val = $teacherGradesJson[$slug] ?? $gradesJson[$slug] ?? null;
            $scoreVal = '';
            $feedbackVal = '';
            if (is_array($val)) {
                $scoreVal = isset($val['score']) ? $val['score'] : (isset($val['nota']) ? $val['nota'] : '');
                $feedbackVal = $val['feedback'] ?? $val['explicacao'] ?? '';
            } elseif ($val !== null && $val !== '') {
                $scoreVal = $val;
            }
            $notaIa = null;
            if (!empty($gradesJson[$slug])) {
                $g = $gradesJson[$slug];
                if (is_array($g) && isset($g['score'])) $notaIa = (float)$g['score'];
                elseif (is_numeric($g)) $notaIa = (float)$g;
            }
        ?>
        <div class="border-l-4 border-blue-200 pl-4 py-2">
            <label for="grade_<?= htmlspecialchars($slug) ?>" class="block text-sm font-medium text-gray-900"><?= htmlspecialchars(EssayCriteriaDisplayHelper::formatCriterionLabel($pos, $name, $isEnemBoard)) ?> (máx. <?= number_format($max, 0, ',', '.') ?>)</label>
            <div class="mt-1 flex flex-wrap items-center gap-4">
                <?php if ($notaIa !== null): ?>
                <div class="flex items-center gap-1">
                    <span class="text-xs text-amber-700 font-medium">Nota da IA:</span>
                    <span class="text-sm font-semibold text-amber-700"><?= number_format($notaIa, 0, ',', '.') ?></span>
                </div>
                <?php endif; ?>
                <div class="flex items-center gap-2">
                    <label for="grade_<?= htmlspecialchars($slug) ?>" class="text-xs text-gray-500 whitespace-nowrap">Sua nota:</label>
                    <input type="number" step="0.01" id="grade_<?= htmlspecialchars($slug) ?>" name="grade_<?= htmlspecialchars($slug) ?>" value="<?= htmlspecialchars($scoreVal) ?>"
                           class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" min="0" max="<?= $max ?>" placeholder="0–<?= (int)$max ?>">
                </div>
            </div>
            <label for="feedback_<?= htmlspecialchars($slug) ?>" class="block text-sm font-medium text-gray-700 mt-2">Descrição / feedback (pode editar)</label>
            <textarea id="feedback_<?= htmlspecialchars($slug) ?>" name="feedback_<?= htmlspecialchars($slug) ?>" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" placeholder="Explicação para esta <?= htmlspecialchars(mb_strtolower($criteriaSectionTitleSingular)) ?>..."><?= htmlspecialchars($feedbackVal) ?></textarea>
        </div>
        <?php endforeach; ?>

        <div>
            <label for="feedback_text" class="block text-sm font-medium text-gray-700">Comentários gerais</label>
            <textarea id="feedback_text" name="feedback_text" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" placeholder="Comentários gerais ao aluno..."><?= $correction ? htmlspecialchars($correction['feedback_text'] ?? '') : '' ?></textarea>
        </div>
        <div>
            <label for="suggestions_text" class="block text-sm font-medium text-gray-700">Sugestões de melhoria</label>
            <textarea id="suggestions_text" name="suggestions_text" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500 bg-green-50/50" placeholder="Sugestões de melhoria..."><?= $correction ? htmlspecialchars($correction['suggestions_text'] ?? '') : '' ?></textarea>
        </div>
        <div id="formError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
        <div id="formSuccess" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"></div>
        <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Salvar correção</button>
    </form>
    </div>
</div>

<div id="modalCorrecaoIa" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/50" aria-modal="true" role="dialog">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-purple-200 p-6 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-purple-100 text-purple-700">
            <i class="fas fa-robot text-2xl"></i>
        </div>
        <div class="mx-auto mb-4 h-12 w-12 rounded-full border-4 border-purple-200 border-t-purple-600 animate-spin"></div>
        <h4 class="text-xl font-semibold text-gray-900 mb-2">Tudinha está corrigindo a redação</h4>
        <p class="text-sm text-gray-600 leading-6">
            Aguarde um instante. Estamos enviando o texto para análise e preparando a correção com as <?= htmlspecialchars(mb_strtolower($criteriaSectionTitle)) ?>, observações e sugestões.
        </p>
        <p class="mt-4 text-xs text-gray-500">
            Esse processo pode levar alguns segundos.
        </p>
    </div>
</div>

<div id="modalAnotacaoProfessor" class="teacher-annotation-modal hidden" aria-modal="true" role="dialog">
    <div class="teacher-annotation-dialog">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h4 class="text-xl font-semibold text-gray-900">Comentário do professor</h4>
                <p class="text-sm text-gray-500 mt-1">Destaque o trecho, registre a observação e, se quiser, deixe uma sugestão de ajuste.</p>
            </div>
            <button type="button" id="btnFecharModalAnotacao" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Trecho selecionado</label>
            <div id="textoTrechoSelecionado" class="teacher-annotation-selection">Nenhum trecho selecionado.</div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="anotacaoColor" class="block text-sm font-medium text-gray-700 mb-2">Cor do destaque</label>
                <select id="anotacaoColor" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="yellow">Amarelo</option>
                    <option value="blue" selected>Azul</option>
                    <option value="pink">Rosa</option>
                    <option value="green">Verde</option>
                </select>
            </div>
            <div>
                <label for="anotacaoReplacement" class="block text-sm font-medium text-gray-700 mb-2">Sugestão de ajuste (opcional)</label>
                <input type="text" id="anotacaoReplacement" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Ex.: reescrever para maior clareza">
            </div>
        </div>

        <div class="mb-4">
            <label for="anotacaoComment" class="block text-sm font-medium text-gray-700 mb-2">Comentário do professor</label>
            <textarea id="anotacaoComment" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Explique ao aluno o que precisa ser ajustado nesse trecho..."></textarea>
        </div>

        <div id="anotacaoProfessorErro" class="hidden mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <button type="button" id="btnCancelarAnotacaoProfessor" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" id="btnSalvarAnotacaoProfessor" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Salvar comentário</button>
        </div>
    </div>
</div>

<!-- Modal fora do card com backdrop-blur: evita que fixed fique preso ao ancestor e quebre cliques nas abas -->
<div id="modalAudioAluno" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50" aria-modal="true" role="dialog">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 border border-gray-200 relative z-[101]">
        <div class="flex justify-between items-start mb-4">
            <h4 class="text-lg font-semibold text-gray-900">Enviar áudio para o aluno</h4>
            <button type="button" id="btnFecharModalAudio" class="text-gray-500 hover:text-gray-800 text-xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-600 mb-4">Envie um arquivo de áudio ou grave direto no navegador. Ouça a prévia e confirme antes de enviar.</p>

        <?php if ($hasTeacherAudio): ?>
        <div class="mb-4 p-3 bg-teal-50 border border-teal-200 rounded-lg">
            <p class="text-xs font-medium text-teal-800 mb-2">Áudio atual (substituir ao enviar um novo)</p>
            <audio controls class="w-full" src="<?= htmlspecialchars($audioFeedbackListenUrl) ?>"></audio>
            <button type="button" id="btnRemoverAudioExistente" class="mt-2 text-sm text-red-600 hover:underline">Remover áudio atual</button>
        </div>
        <?php endif; ?>

        <div class="flex gap-2 mb-4 border-b border-gray-200 pb-2" role="tablist">
            <button type="button" id="tabAudioUpload" role="tab" aria-selected="true" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-purple-100 text-purple-800">Enviar arquivo</button>
            <button type="button" id="tabAudioGravar" role="tab" aria-selected="false" class="px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Gravar no navegador</button>
        </div>

        <div id="panelUpload" class="space-y-3 mb-4" role="tabpanel">
            <label class="block text-sm font-medium text-gray-700">Arquivo (mp3, webm, m4a, wav, ogg — máx. 20 MB)</label>
            <input type="file" id="inputArquivoAudio" accept="audio/*,.webm,.mp3,.m4a,.wav,.ogg" class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-purple-50 file:text-purple-700">
        </div>

        <div id="panelGravar" class="hidden space-y-3 mb-4" role="tabpanel">
            <div class="flex flex-wrap gap-2 items-center">
                <button type="button" id="btnIniciarGravacao" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">Iniciar gravação</button>
                <button type="button" id="btnPararGravacao" class="hidden px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">Parar</button>
                <span id="gravacaoStatus" class="text-sm text-gray-500"></span>
            </div>
            <p class="text-xs text-gray-500">Permita o uso do microfone quando o navegador solicitar.</p>
        </div>

        <div class="mb-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Prévia (confira antes de enviar)</p>
            <audio id="audioPreview" controls class="w-full hidden"></audio>
            <p id="semPreview" class="text-sm text-gray-400">Nenhuma prévia ainda — escolha um arquivo ou grave um áudio.</p>
        </div>

        <div id="modalAudioErro" class="hidden mb-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>

        <div class="flex flex-wrap gap-2 justify-end">
            <button type="button" id="btnCancelarModalAudio" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" id="btnConfirmarEnvioAudio" class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 disabled:opacity-50" disabled>Confirmar e enviar ao aluno</button>
        </div>
    </div>
</div>

<script>
(function() {
    const submissionId = <?= (int)$submission['id'] ?>;
    const token = document.querySelector('input[name="_token"]').value;
    const aiTotal = <?= $aiTotal !== null ? json_encode((float)$aiTotal) : 'null' ?>;
    const modalCorrecaoIa = document.getElementById('modalCorrecaoIa');

    function openCorrecaoIaModal() {
        if (modalCorrecaoIa) {
            modalCorrecaoIa.classList.remove('hidden');
        }
    }

    function closeCorrecaoIaModal() {
        if (modalCorrecaoIa) {
            modalCorrecaoIa.classList.add('hidden');
        }
    }

    function updateResumo() {
        const displayMinha = document.getElementById('displayMinhaNota');
        const displayMedia = document.getElementById('displayMedia');
        if (!displayMinha || !displayMedia) return;
        let sum = 0;
        document.querySelectorAll('input[name^="grade_"]').forEach(function(inp) {
            const v = parseFloat(inp.value);
            if (!isNaN(v)) sum += v;
        });
        displayMinha.textContent = sum > 0 ? sum.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) : '—';
        const useAverage = document.getElementById('use_average') && document.getElementById('use_average').checked;
        if (useAverage && aiTotal != null) {
            const media = (aiTotal + sum) / 2;
            displayMedia.textContent = media.toLocaleString('pt-BR', { maximumFractionDigits: 1 });
        } else if (sum > 0) {
            displayMedia.textContent = sum.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
        } else {
            displayMedia.textContent = aiTotal != null ? aiTotal.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) : '—';
        }
    }

    document.querySelectorAll('input[name^="grade_"]').forEach(function(inp) {
        inp.addEventListener('input', updateResumo);
        inp.addEventListener('change', updateResumo);
    });
    var useAvg = document.getElementById('use_average');
    if (useAvg) useAvg.addEventListener('change', updateResumo);
    updateResumo();

    document.getElementById('btnCorrecaoProfessor').addEventListener('click', function() {
        var bloco = document.getElementById('blocoCorrecaoProfessor');
        if (bloco) bloco.classList.toggle('hidden');
    });

    var btnRemoverIA = document.getElementById('btnRemoverCorrecaoIA');
    if (btnRemoverIA) {
        btnRemoverIA.addEventListener('click', function() {
            if (!confirm('Remover a correção gerada por IA? A nota final passará a ser apenas a sua (professor).')) return;
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Removendo...';
            var fd = new FormData();
            fd.append('_token', token);
            fetch('<?= URL ?>/professor/redacao-configuravel/envios/' + submissionId + '/remover-correcao-ia', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        window.location.reload();
                    } else {
                        alert(d.error || 'Erro ao remover');
                        btn.disabled = false;
                        btn.textContent = 'Remover correção por IA';
                    }
                })
                .catch(function() {
                    alert('Erro de conexão');
                    btn.disabled = false;
                    btn.textContent = 'Remover correção por IA';
                });
        });
    }

    function parseJsonResponse(response) {
        return response.text().then(function(text) {
            var payload = null;
            try {
                payload = text ? JSON.parse(text) : null;
            } catch (error) {
                payload = null;
            }
            return {
                ok: response.ok,
                status: response.status,
                text: text,
                data: payload
            };
        });
    }

    var btnRunAI = document.getElementById('btnRunAI');
    if (btnRunAI) {
        btnRunAI.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Gerando...';
            openCorrecaoIaModal();
            const fd = new FormData();
            fd.append('_token', token);
            fetch('<?= URL ?>/professor/redacao-configuravel/envios/' + submissionId + '/corrigir-ia', { method: 'POST', body: fd })
                .then(parseJsonResponse)
                .then(result => {
                    if (!result.ok || !result.data || !result.data.job_id) {
                        closeCorrecaoIaModal();
                        btn.disabled = false;
                        btn.textContent = 'Gerar correção por IA';
                        var msg = (result.data && result.data.error) ? result.data.error : ('Erro HTTP ' + result.status);
                        alert(msg);
                        return;
                    }
                    btn.textContent = 'Processando...';
                    new AIJobPoller(result.data.job_id, {
                        statusUrl: '<?= URL ?>/professor/ai-job/{id}/status',
                        onDone: function() {
                            closeCorrecaoIaModal();
                            window.location.reload();
                        },
                        onFailed: function(err) {
                            closeCorrecaoIaModal();
                            btn.disabled = false;
                            btn.textContent = 'Gerar correção por IA';
                            alert('Falha na correção por IA: ' + err);
                        }
                    });
                })
                .catch(function(error) {
                    closeCorrecaoIaModal();
                    btn.disabled = false;
                    btn.textContent = 'Gerar correção por IA';
                    alert('Erro de conexão: ' + (error && error.message ? error.message : 'falha desconhecida'));
                });
        });
    }

    document.getElementById('correctionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        document.getElementById('formError').classList.add('hidden');
        document.getElementById('formSuccess').classList.add('hidden');
        const fd = new FormData(this);
        fetch('<?= URL ?>/professor/redacao-configuravel/envios/' + submissionId + '/salvar-correcao', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('formSuccess').textContent = d.message;
                    document.getElementById('formSuccess').classList.remove('hidden');
                } else {
                    document.getElementById('formError').textContent = d.error || 'Erro';
                    document.getElementById('formError').classList.remove('hidden');
                }
            })
            .catch(function() {
                document.getElementById('formError').textContent = 'Erro de conexão';
                document.getElementById('formError').classList.remove('hidden');
            });
    });
})();
</script>

<?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>
<script>
(function() {
    var submissionId = <?= (int) $submission['id'] ?>;
    var token = <?= json_encode($csrf_token) ?>;
    var modal = document.getElementById('modalAudioAluno');
    var btnOpen = document.getElementById('btnAbrirModalAudio');
    var btnClose = document.getElementById('btnFecharModalAudio');
    var btnCancel = document.getElementById('btnCancelarModalAudio');
    var tabUpload = document.getElementById('tabAudioUpload');
    var tabGravar = document.getElementById('tabAudioGravar');
    var panelUpload = document.getElementById('panelUpload');
    var panelGravar = document.getElementById('panelGravar');
    var inputFile = document.getElementById('inputArquivoAudio');
    var audioPreview = document.getElementById('audioPreview');
    var semPreview = document.getElementById('semPreview');
    var btnConfirm = document.getElementById('btnConfirmarEnvioAudio');
    var errBox = document.getElementById('modalAudioErro');
    var btnIniciar = document.getElementById('btnIniciarGravacao');
    var btnParar = document.getElementById('btnPararGravacao');
    var gravacaoStatus = document.getElementById('gravacaoStatus');
    var btnRemoverExistente = document.getElementById('btnRemoverAudioExistente');

    var pendingFile = null;
    var pendingBlob = null;
    var mediaRecorder = null;
    var mediaStream = null;
    var recordedChunks = [];

    function showErr(msg) {
        if (!errBox) return;
        errBox.textContent = msg || '';
        errBox.classList.toggle('hidden', !msg);
    }
    function clearPending() {
        pendingFile = null;
        pendingBlob = null;
        if (inputFile) inputFile.value = '';
    }
    function setPreviewFromUrl(url) {
        if (!audioPreview || !semPreview) return;
        audioPreview.src = url;
        audioPreview.classList.remove('hidden');
        semPreview.classList.add('hidden');
        if (btnConfirm) btnConfirm.disabled = false;
    }
    function resetPreview() {
        if (audioPreview) {
            audioPreview.removeAttribute('src');
            audioPreview.classList.add('hidden');
        }
        if (semPreview) semPreview.classList.remove('hidden');
        if (btnConfirm) btnConfirm.disabled = true;
        clearPending();
        showErr('');
    }

    function openModal() {
        if (modal) modal.classList.remove('hidden');
        resetPreview();
    }
    function closeModal() {
        if (modal) modal.classList.add('hidden');
        stopRecordingCleanup();
        resetPreview();
    }

    if (btnOpen) btnOpen.addEventListener('click', openModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    }

    function activateTabUpload() {
        if (!tabUpload || !tabGravar || !panelUpload || !panelGravar) return;
        tabUpload.className = 'px-3 py-1.5 rounded-lg text-sm font-medium bg-purple-100 text-purple-800';
        tabGravar.className = 'px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100';
        tabUpload.setAttribute('aria-selected', 'true');
        tabGravar.setAttribute('aria-selected', 'false');
        panelUpload.classList.remove('hidden');
        panelGravar.classList.add('hidden');
        stopRecordingCleanup();
    }
    function activateTabGravar() {
        if (!tabUpload || !tabGravar || !panelUpload || !panelGravar) return;
        tabGravar.className = 'px-3 py-1.5 rounded-lg text-sm font-medium bg-purple-100 text-purple-800';
        tabUpload.className = 'px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100';
        tabGravar.setAttribute('aria-selected', 'true');
        tabUpload.setAttribute('aria-selected', 'false');
        panelGravar.classList.remove('hidden');
        panelUpload.classList.add('hidden');
    }
    if (tabUpload) {
        tabUpload.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            activateTabUpload();
        });
    }
    if (tabGravar) {
        tabGravar.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            activateTabGravar();
        });
    }

    if (inputFile) {
        inputFile.addEventListener('change', function() {
            var f = this.files && this.files[0];
            pendingBlob = null;
            if (!f) {
                resetPreview();
                return;
            }
            pendingFile = f;
            setPreviewFromUrl(URL.createObjectURL(f));
        });
    }

    function stopRecordingCleanup() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            try { mediaRecorder.stop(); } catch (e) {}
        }
        mediaRecorder = null;
        if (mediaStream) {
            mediaStream.getTracks().forEach(function(t) { try { t.stop(); } catch (e) {} });
            mediaStream = null;
        }
        recordedChunks = [];
        if (btnIniciar) btnIniciar.classList.remove('hidden');
        if (btnParar) btnParar.classList.add('hidden');
        if (gravacaoStatus) gravacaoStatus.textContent = '';
    }

    if (btnIniciar) {
        btnIniciar.addEventListener('click', function() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showErr('Seu navegador não suporta gravação de áudio.');
                return;
            }
            showErr('');
            recordedChunks = [];
            var mime = 'audio/webm';
            if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mime = 'audio/webm;codecs=opus';
            } else if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/webm')) {
                mime = 'audio/webm';
            }
            navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream) {
                mediaStream = stream;
                try {
                    mediaRecorder = new MediaRecorder(stream, { mimeType: mime });
                } catch (e) {
                    mediaRecorder = new MediaRecorder(stream);
                }
                mediaRecorder.ondataavailable = function(e) {
                    if (e.data && e.data.size > 0) recordedChunks.push(e.data);
                };
                mediaRecorder.onstop = function() {
                    var blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
                    pendingFile = null;
                    pendingBlob = blob;
                    if (inputFile) inputFile.value = '';
                    setPreviewFromUrl(URL.createObjectURL(blob));
                    stopRecordingCleanup();
                };
                mediaRecorder.start();
                if (btnIniciar) btnIniciar.classList.add('hidden');
                if (btnParar) btnParar.classList.remove('hidden');
                if (gravacaoStatus) gravacaoStatus.textContent = 'Gravando…';
            }).catch(function() {
                showErr('Não foi possível acessar o microfone.');
            });
        });
    }
    if (btnParar) {
        btnParar.addEventListener('click', function() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            if (gravacaoStatus) gravacaoStatus.textContent = '';
        });
    }

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            showErr('');
            var fileToSend = null;
            if (pendingFile) {
                fileToSend = pendingFile;
            } else if (pendingBlob) {
                fileToSend = new File([pendingBlob], 'gravacao.webm', { type: pendingBlob.type || 'audio/webm' });
            }
            if (!fileToSend) {
                showErr('Selecione um arquivo ou grave um áudio e confira a prévia.');
                return;
            }
            var fd = new FormData();
            fd.append('_token', token);
            fd.append('audio_file', fileToSend);
            btnConfirm.disabled = true;
            btnConfirm.textContent = 'Enviando…';
            fetch('<?= URL ?>/professor/redacao-configuravel/envios/' + submissionId + '/audio-feedback', {
                method: 'POST',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btnConfirm.disabled = false;
                btnConfirm.textContent = 'Confirmar e enviar ao aluno';
                if (d.success) {
                    window.location.reload();
                } else {
                    showErr(d.error || 'Erro ao enviar');
                }
            })
            .catch(function() {
                btnConfirm.disabled = false;
                btnConfirm.textContent = 'Confirmar e enviar ao aluno';
                showErr('Erro de conexão');
            });
        });
    }

    if (btnRemoverExistente) {
        btnRemoverExistente.addEventListener('click', function() {
            if (!confirm('Remover o áudio enviado ao aluno?')) return;
            var fd = new FormData();
            fd.append('_token', token);
            fetch('<?= URL ?>/professor/redacao-configuravel/envios/' + submissionId + '/audio-feedback/remover', {
                method: 'POST',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) window.location.reload();
                else alert(d.error || 'Erro');
            })
            .catch(function() { alert('Erro de conexão'); });
        });
    }
})();
</script>
