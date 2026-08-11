<?php
$disciplina = $disciplina ?? [];
$atividades = $atividades ?? [];
$flash_status = (string) ($flash_type ?? '');
$disciplinaId = (int) ($disciplina['id'] ?? 0);

$situacao = static function (string $s): array {
    return [
        'avaliada' => ['Avaliada', 'bg-green-100 text-green-700'],
        'enviada' => ['Enviada', 'bg-blue-100 text-blue-700'],
        'reenviar' => ['Reenviar', 'bg-amber-100 text-amber-700'],
        'pendente' => ['Pendente', 'bg-gray-100 text-gray-600'],
        'encerrada' => ['Encerrada', 'bg-red-100 text-red-700'],
    ][$s] ?? [ucfirst($s), 'bg-gray-100 text-gray-600'];
};
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/cursos/disciplina/<?= $disciplinaId ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Atividades</h2>
            <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($disciplina['nome'] ?? '')) ?></p>
        </div>
    </div>
</div>

<?php if ($flash_status !== '' && !empty($flash_message)):
    $cls = $flash_status === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($flash_status === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200'); ?>
    <div class="mb-6 p-4 rounded-lg border <?= $cls ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<?php if (empty($atividades)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500"><i class="fa-solid fa-clipboard-list text-3xl mb-3 text-gray-300"></i><p>Nenhuma atividade disponível nesta disciplina.</p></div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($atividades as $a): [$txt, $cls] = $situacao((string) ($a['situacao'] ?? 'pendente')); $entrega = $a['entrega'] ?? null; ?>
        <a href="<?= URL ?>/cursos/atividade/<?= (int) $a['id'] ?>" class="block bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-green-300 hover:shadow transition">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900"><?= htmlspecialchars((string) $a['titulo']) ?></h3>
                    <?php if (!empty($a['descricao'])): ?><p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars((string) $a['descricao']) ?></p><?php endif; ?>
                    <div class="mt-2 text-xs text-gray-500 flex flex-wrap gap-3">
                        <?php if (!empty($a['data_entrega'])): ?><span><i class="fa-regular fa-clock mr-1"></i>Prazo: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $a['data_entrega']))) ?></span><?php endif; ?>
                        <?php if ($entrega && $entrega['nota'] !== null): ?><span class="text-green-700 font-semibold"><i class="fa-solid fa-star mr-1"></i>Nota: <?= number_format((float) $entrega['nota'], 2, ',', '') ?>/<?= number_format((float) $a['nota_maxima'], 2, ',', '') ?></span><?php endif; ?>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?= $cls ?>"><?= htmlspecialchars($txt) ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
