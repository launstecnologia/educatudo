<?php
/**
 * Boletim já emitido pela coordenação, só para visualização.
 *
 * @var list<array<string,mixed>>|null $boletins_gerados_boletim
 */
$boletins_gerados = is_array($boletins_gerados_boletim ?? null) ? $boletins_gerados_boletim : [];
$boletim_pode_excluir = false;
?>
<?php if ($boletins_gerados === []): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhum boletim disponível.</p>
    </div>
<?php else: ?>
    <?php require __DIR__ . '/boletins_gerados.php'; ?>
<?php endif; ?>
