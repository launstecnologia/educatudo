<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lista = $lista ?? [];
$schema_pronto = $schema_pronto ?? false;
require_once __DIR__ . '/../../Services/ModeloDocumentoService.php';

use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;

$page_header_title = 'Contratos e outros modelos';
$page_header_subtitle = 'Contrato de matrícula e textos editáveis com placeholders. Declarações/autorizações terão módulo próprio.';
ob_start(); ?>
<a href="<?= URL ?>/admin/modelos-documentos/create" class="btn-primary text-sm">
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

<div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
    Use placeholders como <code>{{aluno_nome}}</code> e <code>{{resp_nome}}</code> — preenchidos na emissão do PDF.
    <ul class="mt-2 list-disc list-inside text-slate-600 space-y-0.5">
        <li><code>contrato_matricula</code> — documento assinável da trilha (escolha em Matrículas → Configuração)</li>
        <li><code>contrato_material_didatico</code> — Material didático / Papelaria</li>
        <li>Placeholders de pagantes: <code>{{pagante1_nome}}</code>, <code>{{pagante2_nome}}</code>, <code>{{pagante1_percentual}}</code></li>
    </ul>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Nome', 'Código', 'Orientação', 'Status', 'Atualizado', 'Ações'] as $h): ?>
                    <th class="px-6 py-3 <?= $h === 'Ações' ? 'text-right' : 'text-left' ?> text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $esc($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($lista)): ?>
                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhum modelo cadastrado.</td></tr>
                <?php else: foreach ($lista as $row):
                    $rowId = (int) ($row['id'] ?? 0);
                    $codigoSistema = ModeloDocumentoService::isCodigoSistema((string) ($row['codigo'] ?? ''));
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">
                        <?= $esc($row['nome'] ?? '') ?>
                        <?php if (!empty($row['descricao'])): ?>
                        <div class="text-xs text-gray-500 font-normal mt-0.5"><?= $esc($row['descricao']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600"><code><?= $esc($row['codigo'] ?? '') ?></code></td>
                    <td class="px-6 py-3 text-sm text-gray-600">
                        <?= (($row['orientacao'] ?? 'retrato') === 'paisagem') ? 'Horizontal' : 'Vertical' ?>
                    </td>
                    <td class="px-6 py-3 text-sm">
                        <?php if (!empty($row['ativo'])): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Ativo</span>
                        <?php else: ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500 whitespace-nowrap">
                        <?= !empty($row['updated_at']) ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '—' ?>
                    </td>
                    <td class="px-6 py-3 text-sm text-right whitespace-nowrap">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/modelos-documentos/<?= $rowId ?>/preview"
                           target="_blank" rel="noopener"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Ver modelo
                        </a>
                        <a href="<?= URL ?>/admin/modelos-documentos/<?= $rowId ?>/edit"
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
