<?php
$certificados = $certificados ?? [];
$flash_status = (string) ($flash_type ?? '');
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/cursos" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Meus certificados</h2>
            <p class="text-sm text-gray-600">Certificados de conclusão das suas disciplinas.</p>
        </div>
    </div>
</div>

<?php if ($flash_status !== '' && !empty($flash_message)):
    $cls = $flash_status === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($flash_status === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200'); ?>
    <div class="mb-6 p-4 rounded-lg border <?= $cls ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<?php if (empty($certificados)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-gray-500"><i class="fa-solid fa-award text-3xl mb-3 text-gray-300"></i><p>Você ainda não possui certificados. Conclua uma disciplina com certificação habilitada para emitir.</p></div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($certificados as $c): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-gray-900"><?= htmlspecialchars((string) ($c['disciplina_nome'] ?? $c['titulo'] ?? 'Disciplina')) ?></h3>
                <div class="mt-1 text-xs text-gray-500 flex flex-wrap gap-3">
                    <?php if ((int) ($c['carga_horaria'] ?? 0) > 0): ?><span><i class="fa-regular fa-clock mr-1"></i><?= (int) $c['carga_horaria'] ?>h</span><?php endif; ?>
                    <?php if (!empty($c['emitido_em'])): ?><span><i class="fa-regular fa-calendar mr-1"></i><?= htmlspecialchars(date('d/m/Y', strtotime((string) $c['emitido_em']))) ?></span><?php endif; ?>
                    <span class="font-mono text-green-700"><?= htmlspecialchars((string) ($c['codigo'] ?? '')) ?></span>
                </div>
            </div>
            <?php if (!empty($c['disciplina_id'])): ?>
            <a href="<?= URL ?>/cursos/disciplina/<?= (int) $c['disciplina_id'] ?>/certificado" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 whitespace-nowrap"><i class="fa-solid fa-download mr-2"></i> Baixar PDF</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
