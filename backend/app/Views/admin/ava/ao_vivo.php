<?php
$disciplina = $disciplina ?? [];
$aulas = $aulas ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$base = rtrim((string) ($base_url ?? '/professor/ava'), '/');
$disciplinaId = (int) ($disciplina['id'] ?? 0);

$estadoBadge = static function (string $s): array {
    return [
        'ao_vivo' => ['Ao vivo', 'bg-red-100 text-red-700'],
        'agendada' => ['Agendada', 'bg-blue-100 text-blue-700'],
        'encerrada' => ['Encerrada', 'bg-gray-100 text-gray-600'],
        'cancelada' => ['Cancelada', 'bg-amber-100 text-amber-700'],
    ][$s] ?? [ucfirst($s), 'bg-gray-100 text-gray-600'];
};
?>

<div class="mb-6">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4">
            <a href="<?= URL . $base ?>/disciplinas/<?= $disciplinaId ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-1">Aulas ao vivo</h2>
                <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($disciplina['nome'] ?? '')) ?></p>
            </div>
        </div>
        <a href="<?= URL . $base ?>/disciplinas/<?= $disciplinaId ?>/ao-vivo/nova" class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm"><i class="fa-solid fa-plus mr-2"></i> Nova aula ao vivo</a>
    </div>
</div>

<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<?php if (empty($aulas)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500"><i class="fa-solid fa-video text-3xl mb-3 text-gray-300"></i><p>Nenhuma aula ao vivo agendada.</p></div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($aulas as $a): [$txt, $cls] = $estadoBadge((string) ($a['estado'] ?? 'agendada')); $aid = (int) $a['id']; ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?= htmlspecialchars($txt) ?></span>
                        <h3 class="text-base font-semibold text-gray-900"><?= htmlspecialchars((string) $a['titulo']) ?></h3>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 flex flex-wrap gap-3">
                        <span><i class="fa-solid fa-shapes mr-1"></i><?= htmlspecialchars((string) ($a['plataforma'] ?? '')) ?></span>
                        <?php if (!empty($a['inicio_em'])): ?><span><i class="fa-regular fa-clock mr-1"></i><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $a['inicio_em']))) ?></span><?php endif; ?>
                        <?php if (!empty($a['tem_gravacao'])): ?><span class="text-green-700"><i class="fa-solid fa-film mr-1"></i>Gravação disponível</span><?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="<?= URL . $base ?>/ao-vivo/<?= $aid ?>/entrar" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700"><i class="fa-solid fa-video mr-1"></i> Entrar</a>
                    <?php ob_start(); ?>
                    <a href="<?= URL . $base ?>/ao-vivo/<?= $aid ?>/editar" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                    </a>
                    <?php if (($a['estado'] ?? '') !== 'encerrada'): ?>
                    <form method="post" action="<?= URL . $base ?>/ao-vivo/<?= $aid ?>/status">
                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                        <input type="hidden" name="status" value="encerrada">
                        <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-stop text-gray-400 w-4 text-center"></i> Encerrar
                        </button>
                    </form>
                    <?php endif; ?>
                    <div class="border-t border-gray-100 my-1"></div>
                    <form method="post" action="<?= URL . $base ?>/ao-vivo/<?= $aid ?>/excluir" onsubmit="return confirm('Excluir esta aula ao vivo?');">
                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                        <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                    </form>
                    <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                    <?php $row_actions_dropdown_id = 'row-actions-live-' . $aid; ?>
                    <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
                </div>
            </div>
            <form method="post" action="<?= URL . $base ?>/ao-vivo/<?= $aid ?>/gravacao" class="mt-3 flex gap-2">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <input type="url" name="gravacao_url" value="<?= htmlspecialchars((string) ($a['gravacao_url'] ?? '')) ?>" placeholder="Link da gravação (YouTube, Drive, etc.)" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700" title="Salvar gravação"><i class="fa-solid fa-film"></i></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
