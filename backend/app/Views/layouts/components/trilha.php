<?php
/**
 * Trilha de navegação: faixa com ícone de início e o caminho da página.
 *
 * @var list<array{rotulo: string, url: ?string}> $trilhaItens
 */
\App\Helpers\TrilhaAdmin::marcarRenderizada();
if (count($trilhaItens) < 2) {
    return;
}
$trilhaUltimo = count($trilhaItens) - 1;
$trilhaInicioUrl = trim((string) ($trilhaItens[0]['url'] ?? ''));
?>
<nav aria-label="Trilha de navegação" class="inline-flex items-center gap-3 max-w-full rounded-xl border border-gray-200 bg-white py-2.5 px-5 shadow-sm text-sm">
    <?php if ($trilhaInicioUrl !== ''): ?>
    <a href="<?= htmlspecialchars($trilhaInicioUrl, ENT_QUOTES, 'UTF-8') ?>" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Início">
        <i class="fa-solid fa-house text-xs"></i>
    </a>
    <?php endif; ?>
    <span class="flex items-center gap-1.5 min-w-0">
        <?php foreach ($trilhaItens as $trilhaIndice => $trilhaItem): ?>
            <?php if ($trilhaIndice > 0): ?>
            <span class="text-gray-300 shrink-0" aria-hidden="true">›</span>
            <?php endif; ?>
            <?php if ($trilhaIndice === $trilhaUltimo || empty($trilhaItem['url'])): ?>
            <span class="<?= $trilhaIndice === $trilhaUltimo ? 'truncate font-semibold text-gray-900' : 'truncate text-gray-500' ?>"<?= $trilhaIndice === $trilhaUltimo ? ' aria-current="page"' : '' ?>><?= htmlspecialchars((string) $trilhaItem['rotulo'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
            <a href="<?= htmlspecialchars((string) $trilhaItem['url'], ENT_QUOTES, 'UTF-8') ?>" class="truncate text-gray-500 hover:text-gray-800"><?= htmlspecialchars((string) $trilhaItem['rotulo'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </span>
</nav>
