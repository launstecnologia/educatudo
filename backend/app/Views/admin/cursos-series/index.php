<?php
$list = $list ?? [];
$schema_ready = $schema_ready ?? false;
$seriesPorCurso = is_array($series_por_curso ?? null) ? $series_por_curso : [];
$cursos = $cursos ?? [];
$csrf_token = $csrf_token ?? '';
$has_tipo_possui_serie = (bool) ($has_tipo_possui_serie ?? false);
$status = (string) ($status ?? '');
$message = (string) ($message ?? '');

$page_header_title = 'Cursos e Séries';
$page_header_subtitle = 'Cada curso mostra as séries embaixo. Cadastre o curso uma vez por ano; as séries ficam dentro dele.';
if ($schema_ready) {
    ob_start();
    ?>
    <button type="button" onclick="openSerieDrawer()"
       class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
        <i class="fa-solid fa-plus mr-2"></i>
        Nova Série
    </button>
    <button type="button" onclick="openCursoDrawer()"
       class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Novo Curso
    </button>
    <?php
    $page_header_actions = ob_get_clean();
} else {
    $page_header_actions = '';
}
include __DIR__ . '/../_partials/page_header_list.php';

$flash_status = $status;
$flash_message = $message;
include __DIR__ . '/../_partials/flash_message.php';
?>

<?php if (!$schema_ready): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
    Estrutura ainda não disponível. Execute as migrations 022 a 026 no banco do tenant.
</div>
<?php elseif (empty($list)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-500">
    <i class="fa-solid fa-graduation-cap text-4xl text-gray-300 mb-4"></i>
    <p>Nenhum curso cadastrado</p>
    <button type="button" onclick="openCursoDrawer()"
       class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Cadastrar o primeiro curso
    </button>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($list as $row):
        $cid = (int) ($row['id'] ?? 0);
        $tipo = $row['tipo'] ?? 'regular';
        $tipoLabel = $tipo === 'extra' ? 'Extra' : 'Regular';
        $tipoBadge = $tipo === 'extra' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800';
        $seriesDoCurso = $seriesPorCurso[$cid] ?? [];
        $importLabel = $tipo === 'extra' ? 'Importar / vincular' : 'Importar alunos';
        ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-semibold text-gray-900"><?= htmlspecialchars((string) ($row['nome'] ?? '')) ?></h3>
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $tipoBadge ?>"><?= $tipoLabel ?></span>
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= !empty($row['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                        <?= !empty($row['ativo']) ? 'Ativo' : 'Inativo' ?>
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1"><?= (int) ($row['total_series'] ?? count($seriesDoCurso)) ?> série(s)</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($tipo !== 'extra'): ?>
                <button type="button" onclick="openSerieDrawer(null, <?= $cid ?>)"
                        class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-plus mr-2"></i> Nova série
                </button>
                <?php endif; ?>
                <?php ob_start(); ?>
                <button type="button" onclick="openCursoDrawer(<?= $cid ?>)"
                   class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar curso
                </button>
                <a href="<?= URL ?>/admin/curso/<?= $cid ?>/importar-alunos"
                   class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-file-import text-gray-400 w-4 text-center"></i> <?= htmlspecialchars($importLabel) ?>
                </a>
                <?php
                $row_actions_dropdown_items = ob_get_clean();
                $row_actions_dropdown_id = 'row-actions-curso-' . $cid;
                include __DIR__ . '/../_partials/row_actions_dropdown.php';
                ?>
            </div>
        </div>
        <?php if ($seriesDoCurso === []): ?>
        <p class="px-6 py-4 text-sm text-gray-500">Nenhuma série neste curso.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ordem</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($seriesDoCurso as $serie): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($serie['nome'] ?? '')) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-600"><?= (int) ($serie['ordem'] ?? 0) ?></td>
                        <td class="px-6 py-3">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($serie['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= !empty($serie['ativo']) ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <?php ob_start(); ?>
                            <button type="button" onclick="openSerieDrawer(<?= (int) $serie['id'] ?>)"
                               class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </button>
                            <?php
                            $row_actions_dropdown_items = ob_get_clean();
                            $row_actions_dropdown_id = 'row-actions-serie-' . (int) $serie['id'];
                            include __DIR__ . '/../_partials/row_actions_dropdown.php';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$somente_drawers = true;
include __DIR__ . '/../curso/index.php';
include __DIR__ . '/../serie/index.php';
?>
