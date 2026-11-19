<?php

use App\Modulos\EducaSync\Models\Cenario;

$cenarios = is_array($cenarios ?? null) ? $cenarios : [];
$schemaPronto = !empty($schema_pronto);

$page_header_title = 'EducaSync';
$page_header_subtitle = 'Monte cenários de grade horária, gere versões otimizadas e publique na Grade Horária.';
$page_header_icon = 'fa-solid fa-wand-magic-sparkles';
ob_start();
if ($schemaPronto):
?>
<a href="<?= URL ?>/admin/educasync/novo"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo cenário
</a>
<?php
endif;
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 w-full">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cenário</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano letivo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horários</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Demandas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versões</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($cenarios === []): ?>
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <p>Nenhum cenário criado.</p>
                        <?php if ($schemaPronto): ?>
                        <a href="<?= URL ?>/admin/educasync/novo" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i>Criar o primeiro cenário
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($cenarios as $c):
                    $statusCenario = (string) ($c['status'] ?? '');
                    $statusVariant = $statusCenario === 'ativo' ? 'ativo' : 'rascunho';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $c['nome']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $c['ano_letivo'] ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars(Cenario::PERIODOS[$c['periodo']] ?? (string) $c['periodo']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php $ui_badge_variant = $statusVariant; $ui_badge_label = Cenario::STATUS[$statusCenario] ?? $statusCenario; include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php'; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $c['total_slots'] ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $c['total_demandas'] ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $c['total_versoes'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <a href="<?= URL ?>/admin/educasync/<?= (int) $c['id'] ?>" class="text-sm font-medium text-purple-700 hover:text-purple-900">
                            Abrir <i class="fa-solid fa-arrow-right ml-1"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
