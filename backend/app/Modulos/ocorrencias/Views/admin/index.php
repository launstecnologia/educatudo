<?php
require_once __DIR__ . '/../../Models/Ocorrencia.php';

use App\Modulos\Ocorrencias\Models\Ocorrencia;

$ocorrencias = is_array($ocorrencias ?? null) ? $ocorrencias : [];
$categorias = is_array($categorias ?? null) ? $categorias : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];
$schemaEstendido = !empty($schema_estendido);
$schemaAnexos = !empty($schema_anexos);
$csrf_token = $csrf_token ?? '';
$categoriasAtivas = is_array($categorias_ativas ?? null) ? $categorias_ativas : $categorias;
$aluno = is_array($aluno_preenchido ?? null) ? $aluno_preenchido : null;
$aula = is_array($aula ?? null) ? $aula : null;
$dataPadrao = date('Y-m-d\TH:i');
if ($aula && !empty($aula['data_aula'])) {
    $hora = substr((string) ($aula['horario_de'] ?? '08:00:00'), 0, 5);
    $dataPadrao = $aula['data_aula'] . 'T' . $hora;
}

$filtrosAtivosCount = 0;
foreach ([$filtros['data_inicio'] ?? '', $filtros['data_fim'] ?? '', $filtros['status'] ?? '', $filtros['categoria_id'] ?? 0, $filtros['turma_id'] ?? 0] as $fv) {
    if (!empty($fv)) {
        $filtrosAtivosCount++;
    }
}

$page_header_title = 'Ocorrências';
$page_header_subtitle = 'Registro central da vida escolar do aluno. Não altera nota nem frequência.';
ob_start();
?>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <?php if ($filtrosAtivosCount > 0): ?>
    <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
    <?php endif; ?>
</button>
<button type="button" onclick="openOcorrenciaDrawer()"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova ocorrência
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

$statusLabel = Ocorrencia::STATUS;
$gravidadeLabel = Ocorrencia::GRAVIDADES;
?>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar ocorrências</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/ocorrencias" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_data_inicio" class="block text-sm font-medium text-gray-700 mb-1.5">De</label>
                <input type="date" id="filtro_data_inicio" name="data_inicio" value="<?= htmlspecialchars((string) ($filtros['data_inicio'] ?? '')) ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_data_fim" class="block text-sm font-medium text-gray-700 mb-1.5">Até</label>
                <input type="date" id="filtro_data_fim" name="data_fim" value="<?= htmlspecialchars((string) ($filtros['data_fim'] ?? '')) ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <?php if ($schemaEstendido): ?>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($statusLabel as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= ($filtros['status'] ?? '') === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_categoria" class="block text-sm font-medium text-gray-700 mb-1.5">Categoria</label>
                <select id="filtro_categoria" name="categoria_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (int) ($filtros['categoria_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label for="filtro_turma" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="filtro_turma" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todas</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>" <?= (int) ($filtros['turma_id'] ?? 0) === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= URL ?>/admin/ocorrencias" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 text-center">Limpar</a>
            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">Aplicar filtros</button>
        </div>
    </form>
</aside>


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
                        <p>Nenhuma ocorrência encontrada.</p>
                        <button type="button" onclick="openOcorrenciaDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i>Nova ocorrência
                        </button>
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
    unset($queryParams['page'], $queryParams['novo'], $queryParams['aluno_id'], $queryParams['aula_id']);
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

<div id="ocorrenciaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeOcorrenciaDrawer()"></div>
<aside id="ocorrenciaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">Nova ocorrência</h2>
        <button type="button" onclick="closeOcorrenciaDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="ocorrencia-form" method="post" action="<?= URL ?>/admin/ocorrencias" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <?php if ($aula): ?>
        <input type="hidden" name="diario_aula_id" value="<?= (int) $aula['id'] ?>">
        <?php endif; ?>
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <?php if ($aula): ?>
            <p class="text-sm text-gray-600 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3">
                Vinculada à aula de <?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?>
                · <?= htmlspecialchars((string) ($aula['turma_nome'] ?? '')) ?>
                · <?= date('d/m/Y', strtotime((string) $aula['data_aula'])) ?>
            </p>
            <?php endif; ?>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Fato</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="oc_data" class="block text-sm font-medium text-gray-700 mb-1">Data e hora <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="oc_data" name="data_ocorrencia" required value="<?= htmlspecialchars($dataPadrao) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <?php if ($schemaEstendido): ?>
                    <div>
                        <label for="oc_categoria" class="block text-sm font-medium text-gray-700 mb-1">Categoria <span class="text-red-500">*</span></label>
                        <select id="oc_categoria" name="categoria_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione</option>
                            <?php foreach ($categoriasAtivas as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars((string) $cat['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="sm:col-span-2">
                        <label for="oc_titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                        <input type="text" id="oc_titulo" name="titulo" required maxlength="120" placeholder="Ex.: Conflito no intervalo"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="oc_detalhe" class="block text-sm font-medium text-gray-700 mb-1">Descrição <span class="text-red-500">*</span></label>
                        <textarea id="oc_detalhe" name="detalhe" rows="4" required placeholder="Fato observado, sem julgamento automático."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                    <div>
                        <label for="oc_gravidade" class="block text-sm font-medium text-gray-700 mb-1">Gravidade <span class="text-red-500">*</span></label>
                        <select id="oc_gravidade" name="nivel_gravidade" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione</option>
                            <?php foreach (Ocorrencia::GRAVIDADES as $valor => $rotulo): ?>
                            <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="oc_local" class="block text-sm font-medium text-gray-700 mb-1">Local</label>
                        <input type="text" id="oc_local" name="local" maxlength="120" placeholder="Pátio, sala, corredor…"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Alunos envolvidos <span class="text-red-500">*</span></h3>
                <?php if ($aluno): ?>
                    <input type="hidden" name="alunos[]" value="<?= (int) $aluno['id'] ?>">
                    <p class="text-sm text-gray-800"><?= htmlspecialchars((string) $aluno['nome']) ?>
                        <span class="text-gray-500">· <?= htmlspecialchars((string) ($aluno['turma_nome'] ?? '')) ?></span>
                    </p>
                <?php else: ?>
                    <div class="flex flex-col sm:flex-row gap-3 mb-3">
                        <input type="text" id="alunoBusca" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Buscar aluno pelo nome">
                        <button type="button" id="btnBuscarAluno" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Buscar</button>
                    </div>
                    <div id="alunoResultados" class="space-y-1 mb-3 text-sm"></div>
                    <div id="alunoSelecionados" class="space-y-2"></div>
                <?php endif; ?>
                <div class="mt-4">
                    <label for="oc_testemunhas" class="block text-sm font-medium text-gray-700 mb-1">Testemunhas</label>
                    <textarea id="oc_testemunhas" name="testemunhas" rows="2" placeholder="Quem viu o fato (nomes, um por linha)"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Encaminhamento</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="oc_encaminhamento" class="block text-sm font-medium text-gray-700 mb-1">O que a escola vai fazer</label>
                        <textarea id="oc_encaminhamento" name="encaminhamento" rows="3" placeholder="Não é punição automática."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                    <div>
                        <label for="oc_retorno" class="block text-sm font-medium text-gray-700 mb-1">Retorno para conversar</label>
                        <input type="date" id="oc_retorno" name="retorno_em"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="flex items-center pt-6">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="enviar_pais" name="enviar_pais" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                            <span class="text-sm text-gray-700">Disponibilizar no portal do responsável</span>
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="oc_anexos" class="block text-sm font-medium text-gray-700 mb-1">Fotos ou documentos</label>
                        <?php if ($schemaAnexos): ?>
                        <input type="file" id="oc_anexos" name="anexos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,.doc,.docx"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <p class="text-xs text-gray-500 mt-1">JPG, PNG, WebP, GIF, PDF ou Word. Até 8 arquivos, 10 MB cada. Dá para incluir mais depois, no detalhe.</p>
                        <?php else: ?>
                        <p class="text-xs text-gray-500">Anexos ficam disponíveis após a atualização do banco desta escola.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeOcorrenciaDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">Salvar ocorrência</button>
        </div>
    </form>
</aside>

<script>
function showDrawer(backdropId, drawerId) {
    document.getElementById(backdropId).classList.remove('hidden');
    var drawer = document.getElementById(drawerId);
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
    document.body.style.overflow = 'hidden';
}
function hideDrawer(backdropId, drawerId) {
    document.getElementById(backdropId).classList.add('hidden');
    var drawer = document.getElementById(drawerId);
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function openFilterDrawer() { closeOcorrenciaDrawer(); showDrawer('filterDrawerBackdrop', 'filterDrawer'); }
function closeFilterDrawer() { hideDrawer('filterDrawerBackdrop', 'filterDrawer'); }
function openOcorrenciaDrawer() { closeFilterDrawer(); showDrawer('ocorrenciaDrawerBackdrop', 'ocorrenciaDrawer'); }
function closeOcorrenciaDrawer() { hideDrawer('ocorrenciaDrawerBackdrop', 'ocorrenciaDrawer'); }
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
        closeOcorrenciaDrawer();
    }
});
<?php if (!$aluno): ?>
(function () {
    var resultados = document.getElementById('alunoResultados');
    var selecionados = document.getElementById('alunoSelecionados');
    var escolhidos = {};
    function renderSelecionados() {
        selecionados.innerHTML = '';
        Object.values(escolhidos).forEach(function (a) {
            var wrap = document.createElement('div');
            wrap.className = 'flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2 text-sm';
            var nome = document.createElement('span');
            nome.textContent = a.nome + (a.turma_nome ? ' · ' + a.turma_nome : '');
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'alunos[]';
            hidden.value = a.id;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'text-xs text-red-600';
            btn.textContent = 'Remover';
            btn.addEventListener('click', function () { delete escolhidos[a.id]; renderSelecionados(); });
            wrap.appendChild(nome);
            wrap.appendChild(hidden);
            wrap.appendChild(btn);
            selecionados.appendChild(wrap);
        });
    }
    var btnBuscar = document.getElementById('btnBuscarAluno');
    var inputBusca = document.getElementById('alunoBusca');
    if (inputBusca) {
        inputBusca.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (btnBuscar) btnBuscar.click();
            }
        });
    }
    if (btnBuscar) {
        btnBuscar.addEventListener('click', async function () {
            var term = document.getElementById('alunoBusca').value.trim();
            if (!term) return;
            var resp = await fetch(<?= json_encode(URL . '/admin/ocorrencias/buscar-alunos', JSON_UNESCAPED_SLASHES) ?> + '?term=' + encodeURIComponent(term));
            var data = await resp.json();
            resultados.innerHTML = '';
            (data.alunos || []).forEach(function (a) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'block w-full text-left px-3 py-2 rounded-lg hover:bg-gray-50';
                b.textContent = a.nome + (a.turma_nome ? ' · ' + a.turma_nome : '');
                b.addEventListener('click', function () {
                    escolhidos[a.id] = a;
                    renderSelecionados();
                    resultados.innerHTML = '';
                });
                resultados.appendChild(b);
            });
            if (!(data.alunos || []).length) {
                resultados.innerHTML = '<p class="text-gray-500">Nenhum aluno encontrado.</p>';
            }
        });
    }
})();
<?php endif; ?>
if (new URLSearchParams(window.location.search).get('novo') === '1') {
    openOcorrenciaDrawer();
    if (window.history && window.history.replaceState) {
        var limpa = new URL(window.location.href);
        limpa.searchParams.delete('novo');
        window.history.replaceState({}, '', limpa.pathname + limpa.search + limpa.hash);
    }
}
</script>

