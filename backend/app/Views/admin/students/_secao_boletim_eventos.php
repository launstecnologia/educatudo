<?php
$boletins_gerados = is_array($boletins_gerados ?? null) ? $boletins_gerados : [];
?>
<?php if (!empty($boletins_gerados)): ?>
<div class="flex justify-end mb-4">
    <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/boletim/pdf"
       class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-sm shadow-sm">
        <i class="fa-solid fa-download"></i>
        Baixar PDF
    </a>
</div>
<?php endif; ?>

<?php if (empty($boletins_gerados)): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhum evento gerado para este aluno ainda.</p>
    </div>
<?php else: ?>
    <?php
    $boletim_pode_excluir = (bool) ($boletim_pode_excluir ?? false);
    $boletim_aluno_id = (int) ($student['id'] ?? 0);
    $boletim_csrf_token = (string) ($csrf_token ?? '');
    require __DIR__ . '/../../partials/boletins_gerados.php';
    ?>
<?php endif; ?>
