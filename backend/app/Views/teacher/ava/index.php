<?php
$disciplinas = $disciplinas ?? [];
$base = rtrim((string) ($base_url ?? '/professor/ava'), '/');
$flash_status = (string) ($flash_type ?? '');
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-1">Minhas Disciplinas (AVA)</h2>
    <p class="text-sm text-gray-600">Gerencie o conteúdo das disciplinas em que você é professor ou tutor.</p>
</div>

<?php if ($flash_status !== '' && !empty($flash_message)):
    $cls = $flash_status === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($flash_status === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200'); ?>
    <div class="mb-6 p-4 rounded-lg border <?= $cls ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<?php if (empty($disciplinas)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500">
        Você ainda não tem disciplinas atribuídas no AVA.
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($disciplinas as $d): ?>
            <a href="<?= URL . $base ?>/disciplinas/<?= (int) $d['id'] ?>" class="block bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-book-open"></i></div>
                    <span class="text-xs text-gray-400"><?= (int) ($d['total_alunos'] ?? 0) ?> aluno(s)</span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars((string) $d['nome']) ?></h3>
                <p class="text-sm text-gray-500"><?= htmlspecialchars((string) ($d['curso_nome'] ?? '')) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
