<?php
require_once __DIR__ . '/../../Models/ConselhoSessao.php';
require_once __DIR__ . '/../../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
use App\Modulos\ConselhoClasse\Services\ConselhoService;

$linhas = is_array($linhas ?? null) ? $linhas : [];
$anos = is_array($anos ?? null) ? $anos : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$bimestre = (int) ($bimestre ?? 1);
$turmaId = (int) ($turma_id ?? 0);
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Conselho de Classe';
$page_header_subtitle = 'Etapa colegiada de análise pedagógica. Consome boletim, diário e ocorrências — não duplica nota nem frequência.';
ob_start();
?>
<a href="<?= URL ?>/admin/conselhos/novo?ano_letivo=<?= $anoLetivo ?>&bimestre=<?= $bimestre ?>"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Iniciar Conselho
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<form method="GET" action="<?= URL ?>/admin/conselhos" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</label>
        <select name="ano_letivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <?php foreach ($anos as $ano): ?>
                <option value="<?= (int) $ano ?>" <?= $anoLetivo === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
        <select name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <?php for ($b = 1; $b <= 4; $b++): ?>
                <option value="<?= $b ?>" <?= $bimestre === $b ? 'selected' : '' ?>><?= $b ?>º Bimestre</option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Turma</label>
        <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="0">Todas</option>
            <?php foreach ($turmas as $turma): ?>
                <option value="<?= (int) $turma['id'] ?>" <?= $turmaId === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Filtrar</button>
        <a href="<?= URL ?>/admin/conselhos" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Limpar</a>
    </div>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendências</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($linhas === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-chalkboard-user text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma turma encontrada para este filtro.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($linhas as $linha):
                    $status = (string) ($linha['status_exibicao'] ?? 'nao_iniciado');
                    $pend = (int) (($linha['pendencias']['total'] ?? 0));
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div class="font-medium"><?= htmlspecialchars((string) $linha['turma_nome']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($linha['turma_serie'] ?? '')) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= (int) $bimestre ?>º Bimestre / <?= (int) $anoLetivo ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= (int) ($linha['total_alunos'] ?? 0) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm <?= $pend > 0 ? 'text-amber-700 font-medium' : 'text-gray-700' ?>"><?= $pend ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $ui_badge_variant = ConselhoService::statusBadge($status);
                        $ui_badge_label = ConselhoService::statusLabel($status);
                        include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php';
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <?php if (!empty($linha['sessao_id'])): ?>
                        <a href="<?= URL ?>/admin/conselhos/<?= (int) $linha['sessao_id'] ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-table text-gray-400 w-4 text-center"></i> Abrir matriz
                        </a>
                        <?php else: ?>
                        <a href="<?= URL ?>/admin/conselhos/novo?turma_id=<?= (int) $linha['turma_id'] ?>&ano_letivo=<?= $anoLetivo ?>&bimestre=<?= $bimestre ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-play text-gray-400 w-4 text-center"></i> Iniciar
                        </a>
                        <?php endif; ?>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-conselho-' . (int) $linha['turma_id'];
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
