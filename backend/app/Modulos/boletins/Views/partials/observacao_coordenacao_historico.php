<?php
/**
 * Versões escritas e log de alteração da observação da coordenação.
 *
 * @var list<array<string,mixed>>|null $obs_versoes
 * @var list<array<string,mixed>>|null $obs_log
 */
$obsVersoes = is_array($obs_versoes ?? null) ? $obs_versoes : [];
$obsLog = is_array($obs_log ?? null) ? $obs_log : [];
$obsEsc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="obs-historico mt-3 space-y-3">
    <div class="obs-versoes">
        <?php if ($obsVersoes !== []): ?>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Versões escritas</p>
            <ul class="space-y-2">
                <?php foreach ($obsVersoes as $versao): ?>
                    <?php if (!is_array($versao)) { continue; } ?>
                    <li class="rounded-lg border border-gray-200 bg-white px-3 py-2">
                        <p class="text-sm text-gray-800 whitespace-pre-wrap break-words"><?= $obsEsc($versao['conteudo'] ?? '') ?></p>
                        <p class="mt-1 text-xs text-gray-500"><?= $obsEsc(trim(((string) ($versao['usuario_nome'] ?? '')) . ' · ' . ((string) ($versao['criado_em'] ?? '')), ' ·')) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="obs-log">
        <?php if ($obsLog !== []): ?>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Registro de alterações</p>
            <ul class="space-y-1">
                <?php foreach ($obsLog as $item): ?>
                    <?php if (!is_array($item)) { continue; } ?>
                    <li class="text-xs text-gray-600">
                        <?= $obsEsc($item['criado_em'] ?? '') ?>
                        — <?= $obsEsc(($item['usuario_nome'] ?? '') !== '' ? $item['usuario_nome'] : 'Coordenação') ?>
                        — <?= $obsEsc($item['rotulo'] ?? '') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
