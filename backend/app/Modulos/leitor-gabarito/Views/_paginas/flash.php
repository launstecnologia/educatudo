<?php
$flash = $flash ?? null;
if (empty($flash['message'])) {
    return;
}
$tipo = (string) ($flash['type'] ?? 'info');
$classes = [
    'success' => 'bg-green-50 text-green-800 border-green-200',
    'error' => 'bg-red-50 text-red-800 border-red-200',
    'warning' => 'bg-amber-50 text-amber-900 border-amber-200',
    'info' => 'bg-slate-50 text-slate-800 border-slate-200',
];
$classe = $classes[$tipo] ?? $classes['info'];
?>
<div class="rounded-lg border px-4 py-3 text-sm <?= $classe ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
