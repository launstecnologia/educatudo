<?php
require_once __DIR__ . '/../../../Helpers/EssayTextStructureHelper.php';
require_once __DIR__ . '/../../../Helpers/EssayCriteriaDisplayHelper.php';
?>
<?php
$primaryColor = LayoutHelper::get('primary_color', '#6366f1');
$boardName = $submission['board_name'] ?? $proposal['board_name'] ?? 'Banca';
$boardSlug = $proposal['board_slug'] ?? '';
$isEnemBoard = EssayCriteriaDisplayHelper::isEnemBoard($boardName, $boardSlug);
$contentText = !empty($submission['content_text']) ? $submission['content_text'] : ($submission['ocr_text'] ?? '');
$structuredText = EssayTextStructureHelper::decode((string) ($submission['ocr_text_structure_json'] ?? ''));
$flattenedStructuredLines = !empty($structuredText) ? EssayTextStructureHelper::flatten($structuredText) : [];
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
$criteriaDisplay = EssayCriteriaDisplayHelper::buildCriteriaDisplay(
    isset($criteria) && is_array($criteria) ? $criteria : [],
    $gradesJson,
    $isEnemBoard
);
$criteriaSectionTitle = EssayCriteriaDisplayHelper::getSectionTitle($isEnemBoard);
$maxTotal = EssayCriteriaDisplayHelper::calculateMaxTotal($criteriaDisplay, $isEnemBoard);
$lines = $contentText ? explode("\n", $contentText) : [];
$lineCount = !empty($flattenedStructuredLines) ? count($flattenedStructuredLines) : count($lines);
if (!function_exists('normalizeEssayTextForDisplay')) {
    function normalizeEssayTextForDisplay($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = preg_split("/\n\s*\n/u", $text, -1, PREG_SPLIT_NO_EMPTY);
        $normalized = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }
            $paragraph = preg_replace("/\n+/u", " ", $paragraph);
            $paragraph = preg_replace("/[ \t]{2,}/u", " ", $paragraph);
            $normalized[] = trim($paragraph);
        }
        return implode("\n\n", $normalized);
    }
}
if (!function_exists('renderEssayParagraphsHtml')) {
    function renderEssayParagraphsHtml($text)
    {
        $text = normalizeEssayTextForDisplay((string) $text);
        if ($text === '') {
            return '<span class="text-gray-500">(Sem texto)</span>';
        }
        $paragraphs = preg_split("/\n\s*\n/u", $text, -1, PREG_SPLIT_NO_EMPTY);
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }
            $html .= '<p class="redacao-paragrafo">' . htmlspecialchars($paragraph) . '</p>';
        }
        return $html !== '' ? $html : '<span class="text-gray-500">(Sem texto)</span>';
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
$contentTextNormalized = normalizeEssayTextForDisplay($contentText);
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
            return ['redacao-ai-annotation', 'redacao-ai-annotation-badge'];
        }

        $color = (string) ($annotation['color'] ?? 'blue');
        $allowedColors = ['yellow', 'blue', 'pink', 'green'];
        if (!in_array($color, $allowedColors, true)) {
            $color = 'blue';
        }

        return [
            'redacao-teacher-annotation redacao-teacher-annotation-' . $color,
            'redacao-teacher-annotation-badge redacao-teacher-annotation-badge-' . $color,
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
            $annotationId = 'student-annotation-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $annotationIdRaw);
            $title = buildEssayAnnotationTitle($annotation);
            [$annotationClass, $badgeClass] = buildEssayAnnotationClasses($annotation);
            $html .= '<button type="button"'
                . ' class="redacao-inline-annotation ' . htmlspecialchars($annotationClass) . '"'
                . ' title="' . htmlspecialchars($title) . '"'
                . ' data-annotation-id="' . htmlspecialchars($annotationId) . '"'
                . ' data-annotation-type="' . htmlspecialchars((string) ($annotation['type'] ?? 'ajuste')) . '"'
                . ' data-annotation-source="' . htmlspecialchars((string) ($annotation['source'] ?? 'ai')) . '"'
                . ' data-annotation-selected="' . htmlspecialchars((string) ($annotation['selected_text'] ?? '')) . '"'
                . ' data-annotation-replacement="' . htmlspecialchars((string) ($annotation['replacement'] ?? '')) . '"'
                . ' data-annotation-comment="' . htmlspecialchars((string) ($annotation['comment'] ?? '')) . '">';
            $html .= htmlspecialchars($selected);
            $html .= '<span class="' . htmlspecialchars($badgeClass) . '">' . (int) ($annotation['display_index'] ?? 0) . '</span>';
            $html .= '</button>';
            $cursor = $end;
        }
        if ($cursor < $lineLength) {
            $html .= htmlspecialchars(mb_substr($lineText, $cursor, null, 'UTF-8'));
        }
        return $html !== '' ? $html : htmlspecialchars($lineText);
    }
}
$rawResponsePayload = $correction ? decodeEssayRawResponsePayload((string) ($correction['raw_response_json'] ?? '')) : [];
$aiAnnotations = [];
$teacherAnnotations = [];
$allAnnotations = array_merge($aiAnnotations, $teacherAnnotations);
$aiAnnotationsByLine = groupEssayAnnotationsByLine($allAnnotations);
$displayStructuredLines = $flattenedStructuredLines;
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
?>
<style>
    #studentTextoRedacaoLeitura.redacao-pautada {
        background-color: #fefefe !important;
        background-image: repeating-linear-gradient(
            transparent 0px,
            transparent 27px,
            rgba(203, 213, 225, 0.7) 27px,
            rgba(203, 213, 225, 0.7) 28px
        ) !important;
        background-position: 0 16px !important;
        line-height: 28px !important;
    }
    #studentTextoRedacaoLeitura {
        position: relative;
        z-index: 1;
        font-family: 'Times New Roman', Georgia, serif;
        font-size: 15px;
    }
    @media (min-width: 640px) {
        #studentTextoRedacaoLeitura {
            font-size: 16px;
        }
    }
    .card-border {
        border-color: <?= htmlspecialchars($primaryColor) ?>30;
    }
    .text-primary {
        color: <?= htmlspecialchars($primaryColor) ?>;
    }
    .bg-primary-light {
        background-color: <?= htmlspecialchars($primaryColor) ?>10;
    }
    .border-primary {
        border-color: <?= htmlspecialchars($primaryColor) ?>;
    }
    .redacao-structured-row {
        display: block;
        align-items: start;
        min-height: 28px;
    }
    @media (min-width: 640px) {
        .redacao-structured-row {
            min-height: 28px;
        }
    }
    .redacao-structured-number {
        display: none;
    }
    .redacao-structured-text {
        white-space: pre-wrap;
        word-break: break-word;
        text-align: left;
        text-align-last: auto;
        word-spacing: normal;
        letter-spacing: normal;
        hyphens: none;
        font-size: 1rem;
        line-height: 28px;
        padding: 0 12px;
    }
    .redacao-structured-text--paragraph-start {
        text-indent: 2em;
    }
    .redacao-paragrafo {
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
    .redacao-ai-annotation {
        background: rgba(253, 230, 138, 0.6);
        border-bottom: 2px solid #f59e0b;
        border-radius: 2px;
        padding: 0 1px;
        text-decoration: none;
        color: inherit;
    }
    .redacao-inline-annotation {
        appearance: none;
        border: 0;
        font: inherit;
        cursor: pointer;
        display: inline;
        position: relative;
    }
    .redacao-ai-annotation-badge {
        position: absolute;
        top: -10px;
        right: -10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 9999px;
        background: #7c3aed;
        color: #fff;
        font-size: 10px;
        line-height: 1;
        pointer-events: none;
        z-index: 2;
    }
    .redacao-teacher-annotation {
        background: rgba(191, 219, 254, 0.75);
        border-bottom: 2px solid #2563eb;
        border-radius: 2px;
        padding: 0 1px;
        text-decoration: none;
        color: inherit;
    }
    .redacao-teacher-annotation-badge {
        position: absolute;
        top: -10px;
        right: -10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 9999px;
        color: #fff;
        font-size: 10px;
        line-height: 1;
        pointer-events: none;
        z-index: 2;
    }
    .redacao-teacher-annotation-yellow {
        background: rgba(254, 240, 138, 0.82);
        border-bottom-color: #ca8a04;
    }
    .redacao-teacher-annotation-blue {
        background: rgba(191, 219, 254, 0.75);
        border-bottom-color: #2563eb;
    }
    .redacao-teacher-annotation-pink {
        background: rgba(251, 207, 232, 0.8);
        border-bottom-color: #db2777;
    }
    .redacao-teacher-annotation-green {
        background: rgba(187, 247, 208, 0.8);
        border-bottom-color: #16a34a;
    }
    .redacao-teacher-annotation-badge-yellow {
        background: #ca8a04;
    }
    .redacao-teacher-annotation-badge-blue {
        background: #2563eb;
    }
    .redacao-teacher-annotation-badge-pink {
        background: #db2777;
    }
    .redacao-teacher-annotation-badge-green {
        background: #16a34a;
    }
    .redacao-annotation-popover {
        position: fixed;
        z-index: 90;
        width: min(420px, calc(100vw - 24px));
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
        padding: 16px;
    }
    .redacao-annotation-popover.hidden {
        display: none;
    }
</style>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($submission['proposal_title']) ?></h2>
        <p class="text-gray-500"><?= htmlspecialchars($boardName) ?> — <?= htmlspecialchars($submission['text_type_name'] ?? '') ?></p>
    </div>
    <a href="<?= URL ?>/jornada-redacao/<?= (int) ($submission['proposal_id'] ?? 0) ?>" target="_blank" rel="noopener noreferrer"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-white"
       style="background-color: <?= htmlspecialchars($primaryColor) ?>;">
        <i class="fas fa-external-link-alt"></i> Abrir tema e repertório
    </a>
</div>
<div id="studentEssayAnnotationPopover" class="redacao-annotation-popover hidden" aria-hidden="true">
    <div class="flex items-start justify-between gap-3 mb-3">
        <div>
            <div id="studentEssayAnnotationPopoverTitle" class="text-sm font-semibold text-slate-900">Comentário</div>
            <div id="studentEssayAnnotationPopoverType" class="text-xs text-slate-500 mt-1"></div>
        </div>
        <button type="button" id="btnFecharPopoverAnotacaoAluno" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
    </div>
    <div class="space-y-3 text-sm">
        <div>
            <div class="font-medium text-slate-700 mb-1">Trecho marcado</div>
            <div id="studentEssayAnnotationPopoverSelected" class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-slate-700"></div>
        </div>
        <div id="studentEssayAnnotationPopoverReplacementWrap" class="hidden">
            <div class="font-medium text-emerald-700 mb-1">Sugestão</div>
            <div id="studentEssayAnnotationPopoverReplacement" class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-emerald-800"></div>
        </div>
        <div>
            <div class="font-medium text-slate-700 mb-1">Comentário</div>
            <div id="studentEssayAnnotationPopoverComment" class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-slate-700 whitespace-pre-wrap"></div>
        </div>
    </div>
</div>

<?php
$submissionModeStudent = $proposal['submission_mode'] ?? 'texto';
$hasSubmissionImage = !empty($submission['content_image_path']);
$isSubmissionPdf = (bool) preg_match('/\.pdf(\?.*)?$/i', (string) ($submission['content_image_path'] ?? ''));
$annotatedImageUrl = URL . '/jornada-redacao/correcao/' . (int) ($submission['id'] ?? 0) . '/imagem-corrigida';
$originalImageUrl = URL . '/jornada-redacao/correcao/' . (int) ($submission['id'] ?? 0) . '/original';
$hasAnnotatedFlattened = $correction && !empty(trim((string) ($correction['annotated_image_key'] ?? '')));
$imageAnnotationsStudent = null;
if ($correction && !empty($correction['image_annotations_json'])) {
    $imageAnnotationsStudent = json_decode((string) $correction['image_annotations_json'], true);
}
$showTextBlock = trim((string) $contentText) !== '' || !in_array($submissionModeStudent, ['foto'], true);
?>

<?php if ($hasSubmissionImage && !$isSubmissionPdf): ?>
<div class="bg-white rounded-xl shadow-sm border card-border p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
        <i class="fas fa-image text-purple-600"></i> Sua redação (foto)
    </h3>
    <?php
    $hasImageAnnotations = is_array($imageAnnotationsStudent)
        && (!empty($imageAnnotationsStudent['strokes']) || !empty($imageAnnotationsStudent['texts']));
    ?>
    <?php if ($hasImageAnnotations): ?>
        <p class="text-sm text-gray-600 mb-3">Marcações do professor sobre sua redação.</p>
        <?php
        $annotator_id = 'essayAnnotatorStudent';
        $image_url = $originalImageUrl;
        $initial_annotations = $imageAnnotationsStudent;
        $readonly = true;
        include __DIR__ . '/../../components/essay_image_annotator.php';
        ?>
        <?php if ($hasAnnotatedFlattened): ?>
        <p class="text-xs text-gray-500 mt-3">
            <a href="<?= htmlspecialchars($annotatedImageUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-purple-600 hover:underline">Abrir versão em imagem única</a>
        </p>
        <?php endif; ?>
    <?php elseif ($hasAnnotatedFlattened): ?>
        <p class="text-sm text-gray-600 mb-3">Devolutiva do professor com marcações na folha.</p>
        <img src="<?= htmlspecialchars($annotatedImageUrl) ?>" alt="Redação corrigida" class="w-full rounded-lg border border-gray-200 shadow-sm">
    <?php else: ?>
        <p class="text-sm text-gray-600 mb-3">Foto enviada. Aguardando correção do professor.</p>
        <img src="<?= htmlspecialchars($originalImageUrl) ?>" alt="Redação enviada" class="w-full rounded-lg border border-gray-200 shadow-sm">
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Sua redação com pauta -->
<?php if ($showTextBlock): ?>
<div class="bg-white rounded-xl shadow-sm border card-border p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
        <span>📝</span> Sua redação
    </h3>
    <div id="studentTextoRedacaoLeitura" class="redacao-pautada text-gray-800 border border-gray-200 rounded-lg px-4 py-4 min-h-[400px] max-h-[600px] overflow-y-auto"><?php if (!empty($displayStructuredLines) && !$useParagraphVisualMode): ?>
            <?php $previousParagraphId = null; ?>
            <?php foreach ($displayStructuredLines as $line): ?>
            <?php
                $lineId = normalizeEssayLineId($line['line_id'] ?? '');
                $lineNumber = extractEssayLineNumber($line['line_id'] ?? '');
                $lineText = (string) ($line['text'] ?? '');
                $paragraphId = trim((string) ($line['paragraph_id'] ?? ''));
                $isParagraphStart = !empty($line['is_paragraph_start']) || ($paragraphId !== '' && $paragraphId !== $previousParagraphId) || ($lineNumber > 1 && isLikelyEssayParagraphStart($lineText));
                $textClasses = 'redacao-structured-text' . ($isParagraphStart ? ' redacao-structured-text--paragraph-start' : '');
                $previousParagraphId = $paragraphId !== '' ? $paragraphId : $previousParagraphId;
            ?>
            <div class="redacao-structured-row">
                <div class="redacao-structured-number"><?= $lineNumber > 0 ? $lineNumber : '&nbsp;' ?></div>
                <div class="<?= htmlspecialchars($textClasses) ?>"><?= $lineText !== '' ? renderEssayAnnotatedLine($lineText, $lineId, $aiAnnotationsByLine) : '&nbsp;' ?></div>
            </div>
            <?php endforeach; ?>
        <?php elseif (!empty($displayStructuredLines)): ?>
            <?php foreach (buildEssayParagraphsFromStructuredLines($displayStructuredLines) as $paragraph): ?>
                <?php if (trim($paragraph) !== ''): ?>
                    <p class="redacao-paragrafo"><?= htmlspecialchars($paragraph) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <?= renderEssayParagraphsHtml($contentTextNormalized) ?>
        <?php endif; ?></div>
</div>
<?php endif; ?>

<?php if ($correction): ?>
<?php
$totalScore = null;
if ($correction) {
    if (isset($correction['teacher_total_score']) && $correction['teacher_total_score'] !== null && $correction['teacher_total_score'] !== '') {
        $totalScore = (float) $correction['teacher_total_score'];
    } elseif ($correction['total_score'] !== null && $correction['total_score'] !== '') {
        $totalScore = (float) $correction['total_score'];
    }
}
$percentage = ($totalScore !== null && $maxTotal > 0) ? ($totalScore / $maxTotal) * 100 : 0;
?>
<!-- Nota final -->
<div class="bg-primary-light rounded-xl p-5 mb-5 border border-primary/20">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-medium text-gray-600 mb-1">Nota Final</h3>
            <p class="text-4xl font-bold" style="color: <?= htmlspecialchars($primaryColor) ?>;"><?= $totalScore !== null ? number_format($totalScore, 0, ',', '.') : '—' ?><span class="text-lg font-normal text-gray-500">/<?= number_format($maxTotal, 0, ',', '.') ?></span></p>
        </div>
        <?php if ($totalScore !== null): ?>
        <div class="w-20 h-20 relative">
            <svg class="w-20 h-20 transform -rotate-90">
                <circle cx="40" cy="40" r="35" stroke="#e5e7eb" stroke-width="6" fill="none"/>
                <circle cx="40" cy="40" r="35" stroke="<?= htmlspecialchars($primaryColor) ?>" stroke-width="6" fill="none" 
                        stroke-dasharray="<?= 2 * 3.14159 * 35 ?>" 
                        stroke-dashoffset="<?= 2 * 3.14159 * 35 * (1 - $percentage / 100) ?>"
                        stroke-linecap="round"/>
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-gray-700"><?= round($percentage) ?>%</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$audioProfessorUrl = URL . '/jornada-redacao/correcao/' . (int) ($submission['id'] ?? 0) . '/audio-professor';
$hasAudioProfessor = $correction && !empty(trim($correction['teacher_feedback_audio_key'] ?? ''));
?>
<?php if ($hasAudioProfessor): ?>
<div class="bg-white rounded-xl shadow-sm border card-border p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
        <span>🎧</span> Áudio do professor
    </h3>
    <p class="text-sm text-gray-600 mb-3">Seu professor enviou uma mensagem em áudio sobre sua redação.</p>
    <audio controls preload="metadata" class="w-full max-w-xl" src="<?= htmlspecialchars($audioProfessorUrl) ?>">
        Seu navegador não suporta reprodução de áudio.
    </audio>
</div>
<?php endif; ?>

<?php if (!empty($criteriaDisplay)): ?>
<!-- Critérios / correções -->
<div class="bg-white rounded-xl shadow-sm border card-border p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 <?= htmlspecialchars($criteriaSectionTitle) ?></h3>
    <div class="space-y-4">
        <?php $pos = 0; foreach ($criteriaDisplay as $c): $pos++; $slug = $c['slug']; $name = $c['name']; $max = (float)($c['max_score'] ?? 200);
            $val = $teacherGradesJson[$slug] ?? $gradesJson[$slug] ?? null;
            $score = null;
            $feedback = '';
            if (is_array($val)) {
                $score = isset($val['score']) ? (float)$val['score'] : (isset($val['nota']) ? (float)$val['nota'] : null);
                $feedback = $val['feedback'] ?? $val['explicacao'] ?? '';
            } elseif (is_numeric($val)) {
                $score = (float)$val;
            }
            $pct = $score !== null ? ($score / $max) * 100 : 0;
        ?>
        <div class="p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center justify-between mb-2">
                <p class="font-medium text-gray-900"><?= htmlspecialchars(EssayCriteriaDisplayHelper::formatCriterionLabel($pos, $name, $isEnemBoard)) ?></p>
                <span class="font-bold text-primary"><?= $score !== null ? number_format($score, 0, ',', '.') : '—' ?>/<?= number_format($max, 0, ',', '.') ?></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                <div class="h-2 rounded-full" style="width: <?= $pct ?>%; background-color: <?= htmlspecialchars($primaryColor) ?>;"></div>
            </div>
            <?php if ($feedback !== ''): ?>
            <p class="text-gray-600 text-sm mt-2"><?= htmlspecialchars($feedback) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($aiAnnotations)): ?>
<div class="bg-amber-50 rounded-xl border border-amber-200 p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">🖍️ Marcações no texto</h3>
    <div class="space-y-3">
        <?php foreach ($aiAnnotations as $annotation): ?>
        <?php
        $annotationId = 'student-annotation-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($annotation['id'] ?? ''));
        $lineIdLabel = (string) ($annotation['line_id'] ?? '—');
        $annotationType = (string) ($annotation['type'] ?? 'ajuste');
        $selectedText = (string) ($annotation['selected_text'] ?? '');
        $replacementText = (string) ($annotation['replacement'] ?? '');
        $commentText = (string) ($annotation['comment'] ?? '');
        ?>
        <div id="<?= htmlspecialchars($annotationId) ?>" class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm scroll-mt-24">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-purple-600 px-2 text-xs font-bold text-white"><?= (int) ($annotation['display_index'] ?? 0) ?></span>
                <span class="text-xs font-semibold uppercase tracking-wide text-amber-700"><?= htmlspecialchars($annotationType) ?></span>
                <span class="text-xs text-gray-500">Linha <?= htmlspecialchars($lineIdLabel) ?></span>
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
<div class="bg-blue-50 rounded-xl border border-blue-200 p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">🧑‍🏫 Marcações do professor</h3>
    <div class="space-y-3">
        <?php foreach ($teacherAnnotations as $annotation): ?>
        <?php
        $annotationId = 'student-annotation-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($annotation['id'] ?? ''));
        $lineIdLabel = (string) ($annotation['line_id'] ?? '—');
        $selectedText = (string) ($annotation['selected_text'] ?? '');
        $replacementText = (string) ($annotation['replacement'] ?? '');
        $commentText = (string) ($annotation['comment'] ?? '');
        ?>
        <div id="<?= htmlspecialchars($annotationId) ?>" class="rounded-lg border border-blue-200 bg-white p-4 shadow-sm scroll-mt-24">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-blue-600 px-2 text-xs font-bold text-white"><?= (int) ($annotation['display_index'] ?? 0) ?></span>
                <span class="text-xs font-semibold uppercase tracking-wide text-blue-700">Comentário do professor</span>
                <span class="text-xs text-gray-500">Linha <?= htmlspecialchars($lineIdLabel) ?></span>
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

<?php if (!empty($aiC5Elements) && $isEnemBoard): ?>
<div class="bg-emerald-50 rounded-xl border border-emerald-200 p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">✅ Proposta de intervenção (Competência 5)</h3>
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

<?php if (false && $aiGeneralComment !== ''): ?>
<div class="bg-slate-50 rounded-xl border border-slate-200 p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">💬 Comentário geral</h3>
    <div class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($aiGeneralComment) ?></div>
</div>
<?php endif; ?>

<?php if (false && !empty($correction['feedback_text'])): ?>
<!-- Comentários -->
<div class="bg-white rounded-xl shadow-sm border card-border p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">💬 Comentários do Professor</h3>
    <div class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($correction['feedback_text']) ?></div>
</div>
<?php endif; ?>

<?php if (false && !empty($correction['suggestions_text'])): ?>
<!-- Sugestões -->
<div class="bg-green-50 rounded-xl border border-green-200 p-5 mb-5">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">💡 Sugestões de Melhoria</h3>
    <div class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($correction['suggestions_text']) ?></div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="bg-amber-50 rounded-xl border border-amber-200 p-6 mb-5 text-center">
    <div class="text-3xl mb-2">⏳</div>
    <p class="text-amber-800 font-medium">Correção em andamento</p>
    <p class="text-amber-600 text-sm mt-1">O professor ainda está corrigindo sua redação.</p>
</div>
<?php endif; ?>

<!-- Links -->
<div class="flex flex-wrap gap-4 mt-6">
    <a href="<?= URL ?>/jornada-redacao" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
        ← Voltar às redações
    </a>
    <a href="<?= URL ?>/jornada-redacao/historico" class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline">
        📋 Meu histórico
    </a>
</div>
<script>
(function() {
    var popover = document.getElementById('studentEssayAnnotationPopover');
    var btnClose = document.getElementById('btnFecharPopoverAnotacaoAluno');
    var title = document.getElementById('studentEssayAnnotationPopoverTitle');
    var type = document.getElementById('studentEssayAnnotationPopoverType');
    var selected = document.getElementById('studentEssayAnnotationPopoverSelected');
    var replacementWrap = document.getElementById('studentEssayAnnotationPopoverReplacementWrap');
    var replacement = document.getElementById('studentEssayAnnotationPopoverReplacement');
    var comment = document.getElementById('studentEssayAnnotationPopoverComment');
    var root = document.querySelector('.redacao-pautada');

    function closePopover() {
        if (popover) popover.classList.add('hidden');
    }

    function openPopover(trigger) {
        if (!popover || !trigger) return;
        title.textContent = trigger.getAttribute('data-annotation-source') === 'teacher' ? 'Comentário do professor' : 'Observação da correção';
        type.textContent = trigger.getAttribute('data-annotation-type') || 'ajuste';
        selected.textContent = trigger.getAttribute('data-annotation-selected') || 'Trecho não informado.';
        comment.textContent = trigger.getAttribute('data-annotation-comment') || 'Sem comentário adicional.';
        var replacementValue = trigger.getAttribute('data-annotation-replacement') || '';
        if (replacementValue) {
            replacement.textContent = replacementValue;
            replacementWrap.classList.remove('hidden');
        } else {
            replacement.textContent = '';
            replacementWrap.classList.add('hidden');
        }

        var rect = trigger.getBoundingClientRect();
        var top = rect.bottom + window.scrollY + 10;
        var left = rect.left + window.scrollX;
        var maxLeft = window.scrollX + window.innerWidth - popover.offsetWidth - 12;
        left = Math.max(window.scrollX + 12, Math.min(left, maxLeft));
        if (top + popover.offsetHeight > window.scrollY + window.innerHeight - 12) {
            top = rect.top + window.scrollY - popover.offsetHeight - 10;
        }
        popover.style.top = top + 'px';
        popover.style.left = left + 'px';
        popover.classList.remove('hidden');
    }

    if (root) {
        root.addEventListener('click', function(e) {
            var trigger = e.target.closest('.redacao-inline-annotation');
            if (!trigger) return;
            e.preventDefault();
            e.stopPropagation();
            openPopover(trigger);
        });
    }
    if (btnClose) {
        btnClose.addEventListener('click', closePopover);
    }
    document.addEventListener('click', function(e) {
        if (popover && !popover.classList.contains('hidden')) {
            if (!popover.contains(e.target) && !e.target.closest('.redacao-inline-annotation')) {
                closePopover();
            }
        }
    });
    window.addEventListener('scroll', closePopover, true);
})();
</script>
