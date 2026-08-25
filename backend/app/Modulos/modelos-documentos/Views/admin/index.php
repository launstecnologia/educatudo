<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lista = $lista ?? [];
$schema_pronto = $schema_pronto ?? false;
$layout_pronto = $layout_pronto ?? false;
$categoria = (string) ($categoria ?? 'declaracao');
$categorias = is_array($categorias ?? null) ? $categorias : [];
require_once __DIR__ . '/../../Services/ModeloDocumentoService.php';

use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;

$page_header_title = 'Layout de documentos';
$page_header_subtitle = 'Monte o papel timbrado e o conteúdo de declarações, contratos e documentos oficiais.';
ob_start(); ?>
<a href="<?= URL ?>/admin/modelos-documentos/layout" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-stamp mr-1.5"></i> Papel timbrado
</a>
<a href="<?= URL ?>/admin/modelos-documentos/editor?categoria=<?= $esc($categoria === 'todos' ? 'outro' : $categoria) ?>" class="btn-primary text-sm">
    <i class="fa-solid fa-plus mr-1.5"></i> Novo modelo
</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (!$schema_pronto): ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
    Execute a migration <code>2026_08_06_secretaria_modelos_documentos.sql</code> no painel Master para liberar este recurso.
</div>
<?php endif; ?>

<?php if (!$layout_pronto): ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
    Execute a migration <code>2026_08_22_secretaria_layout_documentos.sql</code> para habilitar o papel timbrado (logo, cabeçalho e assinatura compartilhados) e os modelos de declaração.
    Para A4/A5, margem e espaçamento de linha, rode também <code>2026_08_22_modelos_documentos_papel.sql</code>.
</div>
<?php endif; ?>

<div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900">Papel timbrado da escola</h3>
            <p class="text-sm text-gray-600 mt-1">Logo, faixa de cabeçalho, rodapé e assinatura usados por declarações e documentos oficiais que herdarem o layout.</p>
        </div>
        <a href="<?= URL ?>/admin/modelos-documentos/layout"
           class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium bg-primary text-primary hover:opacity-90">
            Configurar identidade visual
        </a>
    </div>
</div>

<div class="flex flex-wrap gap-2 text-sm mb-6">
    <?php foreach ($categorias as $key => $label):
        $ativa = $categoria === $key;
    ?>
    <a href="<?= URL ?>/admin/modelos-documentos?categoria=<?= $esc($key) ?>"
       class="px-3 py-1.5 rounded-full <?= $ativa ? 'bg-primary text-primary' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
        <?= $esc($label) ?>
    </a>
    <?php endforeach; ?>
    <a href="<?= URL ?>/admin/modelos-documentos?categoria=todos"
       class="px-3 py-1.5 rounded-full <?= $categoria === 'todos' ? 'bg-primary text-primary' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
        Todos
    </a>
</div>

<div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
    Clique em <strong>Editar</strong> para abrir o construtor visual (folha A4, colunas e blocos).
    O PDF usa a mesma estrutura na emissão.
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Nome', 'Código', 'Tipo', 'Papel timbrado', 'Status', 'Ações'] as $h): ?>
                    <th class="px-6 py-3 <?= $h === 'Ações' ? 'text-right' : 'text-left' ?> text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $esc($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($lista)): ?>
                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhum modelo nesta categoria.</td></tr>
                <?php else: foreach ($lista as $row):
                    $rowId = (int) ($row['id'] ?? 0);
                    $codigo = (string) ($row['codigo'] ?? '');
                    $codigoSistema = ModeloDocumentoService::isCodigoSistema($codigo);
                    $catRow = ModeloDocumentoService::categoriaDoCodigo($codigo);
                    $catLabel = $categorias[$catRow] ?? 'Outros';
                    $usaLayout = (int) ($row['usar_layout_padrao'] ?? 0) === 1;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">
                        <?= $esc($row['nome'] ?? '') ?>
                        <?php if (!empty($row['descricao'])): ?>
                        <div class="text-xs text-gray-500 font-normal mt-0.5"><?= $esc($row['descricao']) ?></div>
                        <?php endif; ?>
                        <?php
                        $papelRow = strtoupper((string) ($row['formato_papel'] ?? 'a4'));
                        if ($papelRow !== 'A5') { $papelRow = 'A4'; }
                        $oriRow = (($row['orientacao'] ?? 'retrato') === 'paisagem') ? 'horizontal' : 'vertical';
                        ?>
                        <div class="text-xs text-gray-400 font-normal mt-0.5"><?= $esc($papelRow) ?> · <?= $esc($oriRow) ?></div>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600"><code><?= $esc($codigo) ?></code></td>
                    <td class="px-6 py-3 text-sm text-gray-600"><?= $esc($catLabel) ?></td>
                    <td class="px-6 py-3 text-sm">
                        <?php if ($usaLayout): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Herdado</span>
                        <?php else: ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Próprio</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-sm">
                        <?php if (!empty($row['ativo'])): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Ativo</span>
                        <?php else: ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-sm text-right whitespace-nowrap">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/modelos-documentos/<?= $rowId ?>/preview"
                           target="_blank" rel="noopener"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Ver PDF
                        </a>
                        <a href="<?= URL ?>/admin/modelos-documentos/<?= $rowId ?>/editor"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </a>
                        <?php if (!$codigoSistema): ?>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="post" action="<?= URL ?>/admin/modelos-documentos/excluir"
                              onsubmit="return confirm('Excluir este modelo?')">
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
                            <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
                            <input type="hidden" name="id" value="<?= $rowId ?>">
                            <input type="hidden" name="categoria" value="<?= $esc($categoria) ?>">
                            <button type="submit"
                                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-modelo-' . $rowId;
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
