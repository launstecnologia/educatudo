<?php
$disciplina = $disciplina ?? [];
$aulas = $aulas ?? [];
$flash_status = (string) ($flash_type ?? '');
$disciplinaId = (int) ($disciplina['id'] ?? 0);

$estadoBadge = static function (string $s): array {
    return [
        'ao_vivo' => ['Ao vivo agora', 'bg-red-100 text-red-700'],
        'agendada' => ['Agendada', 'bg-blue-100 text-blue-700'],
        'encerrada' => ['Encerrada', 'bg-gray-100 text-gray-600'],
        'cancelada' => ['Cancelada', 'bg-amber-100 text-amber-700'],
    ][$s] ?? [ucfirst($s), 'bg-gray-100 text-gray-600'];
};
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/cursos/disciplina/<?= $disciplinaId ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Aulas ao vivo</h2>
            <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($disciplina['nome'] ?? '')) ?></p>
        </div>
    </div>
</div>

<?php if ($flash_status !== '' && !empty($flash_message)):
    $cls = $flash_status === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($flash_status === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200'); ?>
    <div class="mb-6 p-4 rounded-lg border <?= $cls ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<?php if (empty($aulas)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500"><i class="fa-solid fa-video text-3xl mb-3 text-gray-300"></i><p>Nenhuma aula ao vivo nesta disciplina.</p></div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($aulas as $a): [$txt, $cls] = $estadoBadge((string) ($a['estado'] ?? 'agendada')); ?>
        <a href="<?= URL ?>/cursos/ao-vivo/<?= (int) $a['id'] ?>" class="block bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-green-300 hover:shadow transition">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?php if (($a['estado'] ?? '') === 'ao_vivo'): ?><span class="w-1.5 h-1.5 bg-red-600 rounded-full mr-1 animate-pulse"></span><?php endif; ?><?= htmlspecialchars($txt) ?></span>
                        <h3 class="text-base font-semibold text-gray-900"><?= htmlspecialchars((string) $a['titulo']) ?></h3>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 flex flex-wrap gap-3">
                        <?php if (!empty($a['inicio_em'])): ?><span><i class="fa-regular fa-clock mr-1"></i><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $a['inicio_em']))) ?></span><?php endif; ?>
                        <?php if (!empty($a['tem_gravacao'])): ?><span class="text-green-700"><i class="fa-solid fa-film mr-1"></i>Gravação disponível</span><?php endif; ?>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-300 mt-1"></i>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
