<?php
require_once __DIR__ . '/../../Models/Ocorrencia.php';

use App\Modulos\Ocorrencias\Models\Ocorrencia;

$ocorrencias = is_array($ocorrencias ?? null) ? $ocorrencias : [];
$categorias = is_array($categorias ?? null) ? $categorias : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];
$schemaEstendido = !empty($schema_estendido);
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Ocorrências';
$page_header_subtitle = 'Registro central da vida escolar do aluno. Não altera nota nem frequência.';
ob_start();
?>
<a href="<?= URL ?>/admin/ocorrencias/nova"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova ocorrência
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

$statusLabel = Ocorrencia::STATUS;
$gravidadeLabel = Ocorrencia::GRAVIDADES;
?>

<form method="GET" action="<?= URL ?>/admin/ocorrencias" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">De</label>
        <input type="date" name="data_inicio" value="<?= htmlspecialchars((string) ($filtros['data_inicio'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Até</label>
        <input type="date" name="data_fim" value="<?= htmlspecialchars((string) ($filtros['data_fim'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <?php if ($schemaEstendido): ?>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">Todos</option>
            <?php foreach ($statusLabel as $valor => $rotulo): ?>
                <option value="<?= htmlspecialchars($valor) ?>" <?= ($filtros['status'] ?? '') === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Categoria</label>
        <select name="categoria_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="0">Todas</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>" <?= (int) ($filtros['categoria_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $cat['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Turma</label>
        <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="0">Todas</option>
            <?php foreach ($turmas as $turma): ?>
                <option value="<?= (int) $turma['id'] ?>" <?= (int) ($filtros['turma_id'] ?? 0) === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-5 flex gap-2">
        <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Filtrar</button>
        <a href="<?= URL ?>/admin/ocorrencias" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Limpar</a>
    </div>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <?php if ($schemaEstendido): ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <?php endif; ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gravidade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pais</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($ocorrencias)): ?>
                <tr>
                    <td colspan="<?= $schemaEstendido ? 9 : 7 ?>" class="px-6 py-12 text-center text-gray-500">
                        Nenhuma ocorrência encontrada.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($ocorrencias as $oc):
                    $status = (string) ($oc['status'] ?? '');
                    $statusVariant = $status === 'encerrada' ? 'ativo' : ($status === 'em_acompanhamento' ? 'info' : 'pendente');
                    $grav = (string) ($oc['nivel_gravidade'] ?? '');
                    $gravVariant = $grav === 'grave' ? 'erro' : ($grav === 'moderado' ? 'pendente' : 'neutro');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= date('d/m/Y H:i', strtotime((string) $oc['data_ocorrencia'])) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div class="font-medium"><?= htmlspecialchars((string) ($oc['titulo'] ?? '')) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars(mb_strimwidth((string) ($oc['detalhe'] ?? ''), 0, 80, '…')) ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($oc['alunos_nomes'] ?? '—')) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($oc['turma_nome'] ?? '—')) ?></td>
                    <?php if ($schemaEstendido): ?>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($oc['categoria_nome'] ?? '—')) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php $ui_badge_variant = $statusVariant; $ui_badge_label = $statusLabel[$status] ?? ($status !== '' ? $status : '—'); include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php'; ?>
                    </td>
                    <?php endif; ?>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php $ui_badge_variant = $gravVariant; $ui_badge_label = $gravidadeLabel[$grav] ?? $grav; include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php'; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= !empty($oc['enviar_pais']) ? 'Sim' : 'Não' ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/ocorrencias/<?= (int) $oc['id'] ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Detalhes
                        </a>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-oc-' . (int) $oc['id'];
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pag = $pagination ?? [];
    $total = (int) ($pag['total'] ?? 0);
    $perPage = (int) ($pag['per_page'] ?? 10);
    $page = (int) ($pag['page'] ?? 1);
    $totalPages = (int) ($pag['total_pages'] ?? 1);
    $queryParams = array_merge($_GET ?? [], []);
    unset($queryParams['page']);
    $baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
    $sep = $baseQuery === '' ? '?' : '&';
    ?>
    <?php if ($total > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> registro(s)
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="<?= URL ?>/admin/ocorrencias<?= $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= URL ?>/admin/ocorrencias<?= $baseQuery . $sep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= URL ?>/admin/ocorrencias<?= $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($schemaEstendido): ?>
<div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Categorias da escola</h3>
    <p class="text-xs text-gray-500 mb-4">O tipo da ocorrência é cadastro da escola, não um valor fixo no código.</p>
    <ul class="text-sm text-gray-700 mb-4 flex flex-wrap gap-2">
        <?php foreach ($categorias as $cat): ?>
            <li class="px-2 py-1 rounded-full <?= !empty($cat['ativo']) ? 'bg-slate-100 text-slate-700' : 'bg-gray-50 text-gray-400' ?>">
                <?= htmlspecialchars((string) $cat['nome']) ?><?= empty($cat['ativo']) ? ' (inativa)' : '' ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="POST" action="<?= URL ?>/admin/ocorrencias/categorias" class="flex flex-col sm:flex-row gap-3 max-w-xl">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="text" name="nome" required maxlength="80" placeholder="Nova categoria"
               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm">
        <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Adicionar</button>
    </form>
</div>
<?php endif; ?>
