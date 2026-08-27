<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$alunoId = (int) ($aluno_id ?? 0);
$base = URL . '/admin/students/' . $alunoId . '/vida-escolar';
$token = (string) ($csrf_token ?? '');
$aba = (string) ($aba ?? 'identidade');
$fichaId = (int) ($ficha_id ?? 0);
$capa = is_array($capa ?? null) ? $capa : [];
$links = is_array($links ?? null) ? $links : [];
$quadro = is_array($quadro ?? null) ? $quadro : null;
$qs = $fichaId > 0 ? ['ficha_id' => $fichaId] : [];
$hrefAba = static function (string $nome) use ($base, $qs): string {
    return $base . '?' . http_build_query(array_merge($qs, ['aba' => $nome]));
};
$abas = [
    'identidade' => ['label' => 'Identidade', 'icon' => 'fa-id-card'],
    'trajetoria' => ['label' => 'Trajetória', 'icon' => 'fa-timeline'],
    'boletim' => ['label' => 'Boletim', 'icon' => 'fa-table'],
    'documentos' => ['label' => 'Documentos', 'icon' => 'fa-folder-open'],
    'conferencia' => ['label' => 'SED / INEP', 'icon' => 'fa-clipboard-check'],
    'dossie' => ['label' => 'Dossiê', 'icon' => 'fa-box-archive'],
];
?>
<div class="mb-6">
    <div class="flex justify-between items-start gap-4 flex-wrap">
        <div>
            <a href="<?= URL ?>/admin/students/<?= $alunoId ?>" class="text-sm text-slate-500 hover:text-slate-700">← Cadastro do aluno</a>
            <h2 class="text-2xl font-bold text-gray-900 mt-1">Prontuário · <?= $esc($aluno['nome'] ?? '') ?></h2>
            <p class="text-gray-600 text-sm mt-1">Vida escolar completa: boletim, trajetória, documentos e conferência para SED e Educacenso.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= URL . ($links['pacote'] ?? ($base . '/pacote-transferencia')) ?>" download class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Baixar pacote de transferência</a>
            <a href="<?= URL . ($links['dossie'] ?? ($base . '/dossie')) ?>" download class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Baixar dossiê (PDF)</a>
        </div>
    </div>
</div>

<?php if (!empty($flash_message)): ?>
<div class="mb-4 rounded-lg px-4 py-3 text-sm <?= ($flash_status ?? '') === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
    <?= $esc($flash_message) ?>
</div>
<?php endif; ?>

<?php if (empty($schema_pronto)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-900 text-sm mb-6">
    Aplique a migration <code>2026_08_25_vida_escolar</code> no Master para gravar a ficha oficial. O prontuário já mostra cadastro, SED/INEP e documentos emitidos.
</div>
<?php endif; ?>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
    <?php
    $resumoCards = [
        ['label' => 'Situação', 'value' => (string) ($capa['situacao'] ?? '—'), 'icon' => 'fa-user-graduate', 'valueClass' => 'text-gray-900', 'iconClass' => 'bg-slate-100 text-slate-600'],
        ['label' => 'Ficha do ano', 'value' => (string) ($capa['status_ficha_label'] ?? 'Sem ficha'), 'icon' => 'fa-table', 'valueClass' => 'text-gray-900', 'iconClass' => 'bg-blue-50 text-blue-600'],
        ['label' => 'Documentos', 'value' => (string) ($capa['docs_txt'] ?? '—'), 'icon' => 'fa-folder-open', 'valueClass' => !empty($capa['docs_ok']) ? 'text-green-700' : 'text-amber-700', 'iconClass' => 'bg-amber-50 text-amber-600'],
        ['label' => 'SED / INEP', 'value' => (string) ($capa['sed_txt'] ?? '—'), 'icon' => 'fa-clipboard-check', 'valueClass' => !empty($capa['sed_ok']) ? 'text-green-700' : 'text-amber-700', 'iconClass' => 'bg-green-50 text-green-600'],
    ];
    foreach ($resumoCards as $card):
    ?>
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500"><?= $esc($card['label']) ?></p>
                <p class="mt-1 text-lg font-bold leading-tight <?= $esc($card['valueClass']) ?>"><?= $esc($card['value']) ?></p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= $esc($card['iconClass']) ?>">
                <i class="fa-solid <?= $esc($card['icon']) ?> text-sm"></i>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<p class="text-sm text-gray-600 mb-4">
    <?= $esc($capa['turma'] ?? '') ?><?= !empty($capa['serie']) ? ' · ' . $esc($capa['serie']) : '' ?>
    <?= !empty($capa['ano_letivo']) ? ' · ' . (int) $capa['ano_letivo'] : '' ?>
    · <?= (int) ($capa['anos_trajetoria'] ?? 0) ?> ano(s) na trajetória
    <?php if (!empty($capa['historico_emitido'])): ?> · Histórico oficial emitido<?php endif; ?>
    · Educacenso: <?= $esc($capa['inep_txt'] ?? '') ?>
</p>

<div class="flex flex-wrap gap-2 text-sm mb-6">
    <?php foreach ($abas as $chave => $meta): ?>
        <a href="<?= $esc($hrefAba($chave)) ?>"
           class="px-3 py-1.5 rounded-full <?= $aba === $chave ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            <i class="fa-solid <?= $esc($meta['icon']) ?> mr-1 text-xs"></i><?= $esc($meta['label']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php
$partial = __DIR__ . '/_aba_' . $aba . '.php';
if (is_file($partial)) {
    include $partial;
}
?>
