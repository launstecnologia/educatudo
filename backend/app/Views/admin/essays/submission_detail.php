<?php
require_once __DIR__ . '/../../../Helpers/EssayCriteriaDisplayHelper.php';

$studentName = (string) ($student['nome'] ?? $submission['student_name'] ?? 'Aluno');
$essayText = trim((string) ($submission['content_text'] ?? $submission['ocr_text'] ?? ''));
$gradesJson = [];
$teacherGradesJson = [];
if (!empty($correction['grades_json'])) {
    $decoded = json_decode((string) $correction['grades_json'], true);
    $gradesJson = is_array($decoded) ? $decoded : [];
}
if (!empty($correction['teacher_grades_json'])) {
    $decoded = json_decode((string) $correction['teacher_grades_json'], true);
    $teacherGradesJson = is_array($decoded) ? $decoded : [];
}
$aiTotalScore = isset($correction['ai_total_score']) && $correction['ai_total_score'] !== null
    ? (float) $correction['ai_total_score']
    : null;
$teacherTotalScore = isset($correction['teacher_total_score']) && $correction['teacher_total_score'] !== null
    ? (float) $correction['teacher_total_score']
    : null;
$useAverage = !empty($correction['use_average']);
$totalScore = isset($correction['total_score']) ? (float) $correction['total_score'] : null;

$isEnemBoard = EssayCriteriaDisplayHelper::isEnemBoard(
    $proposal['board_name'] ?? '',
    $proposal['board_slug'] ?? ''
);
$criteriaDisplay = EssayCriteriaDisplayHelper::buildCriteriaDisplay(
    isset($criteria) && is_array($criteria) ? $criteria : [],
    $gradesJson,
    $isEnemBoard
);
$criteriaSectionTitleSingular = EssayCriteriaDisplayHelper::getSectionTitle($isEnemBoard, false);
$maxTotalScore = EssayCriteriaDisplayHelper::calculateMaxTotal($criteriaDisplay, $isEnemBoard);
?>

<div class="mb-8">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Detalhes da Redação</h2>
            <p class="text-gray-600"><?= htmlspecialchars((string) ($proposal['title'] ?? '')) ?> — <?= htmlspecialchars($studentName) ?></p>
        </div>
        <a href="<?= URL ?>/admin/redacao-professor/relatorio" class="inline-flex items-center justify-center bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
            Voltar para relatório
        </a>
    </div>
</div>

<div id="redacao" class="bg-white/90 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Redação</h3>
    <div class="text-sm text-gray-600 mb-4">
        Enviada em: <?= !empty($submission['submitted_at']) ? date('d/m/Y H:i', strtotime((string) $submission['submitted_at'])) : '—' ?>
    </div>
    <div class="border border-gray-200 rounded-lg p-4 min-h-[220px] whitespace-pre-wrap text-gray-800 leading-relaxed bg-white">
        <?= $essayText !== '' ? nl2br(htmlspecialchars($essayText)) : '<span class="text-gray-500">Sem texto disponível.</span>' ?>
    </div>
</div>

<div id="correcao" class="bg-white/90 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Correção</h3>

    <?php if (!$correction): ?>
        <p class="text-gray-500">Esta redação ainda não possui correção registrada.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
            <div class="p-3 rounded-lg border border-gray-200 bg-gray-50">
                <p class="text-xs uppercase text-gray-500">Data correção</p>
                <p class="text-base font-semibold text-gray-800"><?= !empty($correction['teacher_adjusted_at']) ? date('d/m/Y H:i', strtotime((string) $correction['teacher_adjusted_at'])) : (!empty($correction['updated_at']) ? date('d/m/Y H:i', strtotime((string) $correction['updated_at'])) : '—') ?></p>
            </div>
            <div class="p-3 rounded-lg border border-gray-200 bg-gray-50">
                <p class="text-xs uppercase text-gray-500">Nota final</p>
                <p class="text-base font-semibold text-gray-800"><?= $totalScore !== null ? number_format($totalScore, 1, ',', '.') . ' / ' . number_format($maxTotalScore, 0, ',', '.') : '—' ?></p>
            </div>
            <div class="p-3 rounded-lg border border-gray-200 bg-gray-50">
                <p class="text-xs uppercase text-gray-500">Status</p>
                <p class="text-base font-semibold text-gray-800">Corrigido</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
            <div class="p-3 rounded-lg border border-amber-200 bg-amber-50">
                <p class="text-xs uppercase text-amber-700">Nota Tudinha (IA)</p>
                <p class="text-base font-semibold text-amber-900"><?= $aiTotalScore !== null ? number_format($aiTotalScore, 1, ',', '.') : '—' ?></p>
            </div>
            <div class="p-3 rounded-lg border border-blue-200 bg-blue-50">
                <p class="text-xs uppercase text-blue-700">Nota Professor</p>
                <p class="text-base font-semibold text-blue-900"><?= $teacherTotalScore !== null ? number_format($teacherTotalScore, 1, ',', '.') : '—' ?></p>
            </div>
            <div class="p-3 rounded-lg border border-green-200 bg-green-50">
                <p class="text-xs uppercase text-green-700">Cálculo da nota final</p>
                <p class="text-base font-semibold text-green-900">
                    <?php if ($useAverage): ?>
                        Média entre IA e Professor
                    <?php else: ?>
                        Correção do Professor
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="mb-2">
            <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Correção da Tudinha e do Professor</h4>
        </div>

        <div class="space-y-4 mb-5">
            <?php
            $pos = 0;
            foreach ($criteriaDisplay as $criterion):
                $slug = (string) ($criterion['slug'] ?? '');
                $name = (string) ($criterion['name'] ?? ('Competência ' . ($pos + 1)));
                $max = (float) ($criterion['max_score'] ?? 200);
                $pos++;

                $ai = $gradesJson[$slug] ?? null;
                $teacher = $teacherGradesJson[$slug] ?? null;

                $aiScore = is_array($ai) ? ($ai['score'] ?? $ai['nota'] ?? null) : $ai;
                $teacherScore = is_array($teacher) ? ($teacher['score'] ?? $teacher['nota'] ?? null) : $teacher;

                $aiFeedback = is_array($ai) ? ((string) ($ai['feedback'] ?? $ai['explicacao'] ?? '')) : '';
                $teacherFeedback = is_array($teacher) ? ((string) ($teacher['feedback'] ?? $teacher['explicacao'] ?? '')) : '';
                $displayFeedback = trim($teacherFeedback) !== '' ? $teacherFeedback : $aiFeedback;
            ?>
            <div class="border-l-4 border-blue-200 pl-4 py-2">
                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars(EssayCriteriaDisplayHelper::formatCriterionLabel($pos, $name, $isEnemBoard)) ?> (máx. <?= number_format($max, 0, ',', '.') ?>)</p>
                <div class="mt-1 flex flex-wrap items-center gap-6">
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-amber-700 font-medium">Nota da IA:</span>
                        <span class="text-sm font-semibold text-amber-700"><?= $aiScore !== null && $aiScore !== '' ? htmlspecialchars((string) $aiScore) : '—' ?></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-blue-700 font-medium">Nota do Professor:</span>
                        <span class="text-sm font-semibold text-blue-700"><?= $teacherScore !== null && $teacherScore !== '' ? htmlspecialchars((string) $teacherScore) : '—' ?></span>
                    </div>
                </div>
                <label class="block text-sm font-medium text-gray-700 mt-2">Descrição / feedback</label>
                <div class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-800 whitespace-pre-wrap">
                    <?= $displayFeedback !== '' ? nl2br(htmlspecialchars($displayFeedback)) : '<span class="text-gray-500">Sem feedback para esta ' . htmlspecialchars(mb_strtolower($criteriaSectionTitleSingular)) . '.</span>' ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($correction['feedback_text'])): ?>
            <div class="mb-4">
                <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Comentários gerais (versão final salva pelo professor)</h4>
                <div class="text-gray-800 whitespace-pre-wrap border border-gray-200 rounded-lg p-3 bg-gray-50"><?= nl2br(htmlspecialchars((string) $correction['feedback_text'])) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($correction['suggestions_text'])): ?>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Sugestões</h4>
                <div class="text-gray-800 whitespace-pre-wrap border border-gray-200 rounded-lg p-3 bg-gray-50"><?= nl2br(htmlspecialchars((string) $correction['suggestions_text'])) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
