<?php
$turmas = $turmas ?? [];
$csrf_token = $csrf_token ?? '';

$statsTurmas = $stats_turmas ?? [];
$totalTurmas = (int)($statsTurmas['total_turmas'] ?? count($turmas));
$turmasAtivas = (int)($statsTurmas['turmas_ativas'] ?? 0);
$totalAlunos = (int)($statsTurmas['total_alunos'] ?? 0);
$mediaAlunos = $statsTurmas['media_alunos'] ?? 0;

$series = $series ?? [];
$cursos = $cursos ?? [];
$cursosNovo = $cursosNovo ?? [];
$seriesPorCurso = $seriesPorCurso ?? [];
$matrizesPorSerie = $matrizesPorSerie ?? [];
$anosLetivo = $anosLetivo ?? [];
$salas = $salas ?? [];
$ano_letivo_id = $ano_letivo_id ?? null;
$usaEstruturaNova = !empty($cursosNovo);

$turnos = [
    'manha' => 'Manhã',
    'tarde' => 'Tarde',
    'noite' => 'Noite',
    'integral' => 'Integral',
];

$page_header_title = 'Turmas';
$page_header_subtitle = 'Gerencie as turmas da escola';
ob_start();
?>
<button type="button" onclick="openTurmaDrawer()"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova Turma
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<?php if ($totalTurmas > 0): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm text-gray-600">Total de turmas</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $totalTurmas ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm text-gray-600">Turmas ativas</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $turmasAtivas ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm text-gray-600">Total de alunos</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $totalAlunos ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm text-gray-600">Média por turma</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $mediaAlunos ?></p>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <?php if (!empty($turmas)): ?>
    <?php
    $bulk_delete_csrf_token = $csrf_token;
    $bulk_delete_bulk_url = URL . '/admin/turmas/bulk-delete';
    $bulk_delete_ids_param = 'turma_ids';
    $bulk_delete_entity_singular = 'turma';
    $bulk_delete_entity_plural = 'turmas';
    $bulk_delete_single_url_template = URL . '/admin/turmas/{id}';
    $bulk_delete_single_method = 'DELETE';
    include __DIR__ . '/../_partials/bulk_delete_modal.php';
    ?>
    <?php endif; ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php if (!empty($turmas)): ?>
                    <th class="px-4 py-3 text-left w-10">
                        <input type="checkbox" id="bulk-select-all" class="rounded border-gray-300 text-red-600" title="Selecionar todos">
                    </th>
                    <?php endif; ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vagas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($turmas)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-users-rectangle text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma turma cadastrada</p>
                        <button type="button" onclick="openTurmaDrawer()"
                           class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Nova Turma
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($turmas as $turma): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap">
                        <input type="checkbox" class="bulk-row-checkbox rounded border-gray-300 text-red-600" value="<?= (int) $turma['id'] ?>">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($turma['nome']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?= htmlspecialchars($turma['serie']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?= (int) $turma['total_alunos'] ?> alunos
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php
                        $vr = $turma['vagas_resumo'] ?? null;
                        if (!is_array($vr) || !empty($vr['ilimitado'])) {
                            echo 'Ilimitado';
                        } else {
                            $ocup = (int) ($vr['ocupadas'] ?? 0);
                            $tot = (int) ($vr['vagas'] ?? 0);
                            $fila = (int) ($vr['fila'] ?? 0);
                            $cls = !empty($vr['lotada']) ? 'text-red-700 font-semibold' : 'text-gray-700';
                            echo '<span class="' . $cls . '">' . $ocup . '/' . $tot . '</span>';
                            if ($fila > 0) {
                                echo ' <span class="text-xs text-purple-700">+' . $fila . ' fila</span>';
                            }
                        }
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $turma['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $turma['ativo'] ? 'Ativa' : 'Inativa' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/turmas/<?= (int) $turma['id'] ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-circle-info text-gray-400 w-4 text-center"></i> Detalhes
                        </a>
                        <button type="button" onclick="openTurmaDrawer(<?= (int) $turma['id'] ?>)"
                           class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <button type="button"
                                onclick="toggleStatus(<?= (int) $turma['id'] ?>, <?= $turma['ativo'] ? 'false' : 'true' ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-power-off text-gray-400 w-4 text-center"></i> <?= $turma['ativo'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="openBulkDeleteSingle(<?= (int) $turma['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-turma-' . (int) $turma['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
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
    $pagTotal = (int)($pag['total'] ?? 0);
    $pagPerPage = (int)($pag['per_page'] ?? 10);
    $pagPage = (int)($pag['page'] ?? 1);
    $pagTotalPages = (int)($pag['total_pages'] ?? 1);
    $pagQueryParams = $_GET ?? [];
    unset($pagQueryParams['page']);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> turma(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/turmas<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/turmas<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/turmas<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Offcanvas: Cadastrar/Editar Turma -->
<div id="turmaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeTurmaDrawer()"></div>
<aside id="turmaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="turmaDrawerTitle" class="text-xl font-bold text-gray-900">Nova Turma</h2>
        <button type="button" onclick="closeTurmaDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="turma-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="tu_id" value="">
        <div id="tu-legacy-hidden-fields"></div>

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados Acadêmicos</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <?php if ($usaEstruturaNova): ?>
                    <div>
                        <label for="tu_ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-1">Ano Letivo <span class="text-red-500">*</span></label>
                        <select id="tu_ano_letivo_id" name="ano_letivo_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <?php if (empty($anosLetivo)): ?>
                            <option value="<?= (int) $ano_letivo_id ?>"><?= (int) date('Y') ?></option>
                            <?php endif; ?>
                            <?php foreach ($anosLetivo as $al): ?>
                            <option value="<?= (int) $al['id'] ?>" <?= (int) $ano_letivo_id === (int) $al['id'] ? 'selected' : '' ?>>
                                <?= (int) $al['ano'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="tu_curso_novo_id" class="block text-sm font-medium text-gray-700 mb-1">Curso <span class="text-red-500">*</span></label>
                        <select id="tu_curso_novo_id" name="curso_novo_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione o curso</option>
                            <?php foreach ($cursosNovo as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-possui-serie="<?= (int) ($c['possui_serie'] ?? 1) ?>">
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Inclui cursos regulares e extras (ex.: Música, Robótica).</p>
                    </div>
                    <div id="tu-wrap-serie-novo" class="hidden">
                        <label for="tu_serie_id" class="block text-sm font-medium text-gray-700 mb-1">Série</label>
                        <select id="tu_serie_id" name="serie_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione a série</option>
                        </select>
                    </div>
                    <div id="tu-wrap-matriz" class="hidden md:col-span-2">
                        <label for="tu_matriz_curricular_id" class="block text-sm font-medium text-gray-700 mb-1">Matriz Curricular</label>
                        <select id="tu_matriz_curricular_id" name="matriz_curricular_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Nenhuma / definir depois</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Define os Componentes Curriculares e a carga horária desta turma. Só lista matrizes cadastradas para a série selecionada.</p>
                    </div>

                    <?php elseif (!empty($cursos)): ?>
                    <div class="md:col-span-2">
                        <label for="tu_curso_id" class="block text-sm font-medium text-gray-700 mb-1">Curso / Série <span class="text-red-500">*</span></label>
                        <select id="tu_curso_id" name="curso_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione o curso/série</option>
                            <?php foreach ($cursos as $curso): ?>
                            <?php
                            $tipoNome = trim((string) ($curso['tipo_nome'] ?? ''));
                            $cursoNome = (string) ($curso['nome'] ?? '');
                            $optionLabel = $tipoNome !== '' ? ($tipoNome . ' - ' . $cursoNome) : $cursoNome;
                            ?>
                            <option value="<?= (int) $curso['id'] ?>" data-serie="<?= htmlspecialchars($cursoNome) ?>">
                                <?= htmlspecialchars($optionLabel) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php else: ?>
                    <div class="md:col-span-2">
                        <label for="tu_serie" class="block text-sm font-medium text-gray-700 mb-1">Série <span class="text-red-500">*</span></label>
                        <select id="tu_serie" name="serie" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione a série</option>
                            <?php foreach ($series as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Identificação</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="md:col-span-2">
                        <label for="tu_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome da turma <span class="text-red-500">*</span></label>
                        <input type="text" id="tu_nome" name="nome" required
                               placeholder="Ex.: 1ºA, 2ºB, 3ºA…"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="mt-1 text-xs text-gray-500">Identificador exibido nas listagens e na ficha da turma.</p>
                    </div>
                    <div>
                        <label for="tu_turno" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                        <select id="tu_turno" name="turno"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Não informado</option>
                            <?php foreach ($turnos as $valor => $rotulo): ?>
                            <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="tu_sala_padrao_id" class="block text-sm font-medium text-gray-700 mb-1">Sala Padrão</label>
                        <select id="tu_sala_padrao_id" name="sala_padrao_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Nenhuma</option>
                            <?php foreach ($salas as $sala): ?>
                            <option value="<?= (int) $sala['id'] ?>"><?= htmlspecialchars($sala['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            <a href="<?= URL ?>/admin/salas" class="text-green-700 hover:underline">Cadastrar nova sala/ambiente</a>
                        </p>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Capacidade</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="tu_vagas" class="block text-sm font-medium text-gray-700 mb-1">Capacidade (vagas)</label>
                        <input type="number" id="tu_vagas" name="vagas" min="0"
                               placeholder="Vazio = sem limite"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="mt-1 text-xs text-gray-500">Deixe em branco ou 0 para turma sem limite de vagas.</p>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Controle</h3>
                <div class="grid grid-cols-1 gap-y-5">
                    <label class="flex items-center">
                        <input type="checkbox" id="tu_ativo" name="ativo" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Turma ativa</span>
                    </label>
                    <div>
                        <label for="tu_observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea id="tu_observacoes" name="observacoes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeTurmaDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="tu-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<script>
var TURMA_USA_ESTRUTURA_NOVA = <?= $usaEstruturaNova ? 'true' : 'false' ?>;
var turmaSeriesPorCurso = <?= json_encode($seriesPorCurso, JSON_UNESCAPED_UNICODE) ?>;
var turmaMatrizesPorSerie = <?= json_encode($matrizesPorSerie, JSON_UNESCAPED_UNICODE) ?>;
var turmaAnoLetivoIdPadrao = <?= (int) $ano_letivo_id ?>;
var turmaSerieIdAlvo = 0;
var turmaMatrizIdAlvo = 0;

function turmaAtualizarMatriz() {
    var wrapMatriz = document.getElementById('tu-wrap-matriz');
    var selMatriz = document.getElementById('tu_matriz_curricular_id');
    var selSerie = document.getElementById('tu_serie_id');
    if (!wrapMatriz || !selMatriz || !selSerie) return;
    var serieId = parseInt(selSerie.value, 10);
    selMatriz.innerHTML = '<option value="">Nenhuma / definir depois</option>';
    var opcoes = serieId ? (turmaMatrizesPorSerie[serieId] || []) : [];
    if (opcoes.length === 0) {
        wrapMatriz.classList.add('hidden');
        return;
    }
    wrapMatriz.classList.remove('hidden');
    opcoes.forEach(function (m) {
        var o = document.createElement('option');
        o.value = m.id;
        o.textContent = m.nome + ' (' + m.codigo + ')' + (m.inativa ? ' — inativa' : '');
        if (turmaMatrizIdAlvo && parseInt(m.id, 10) === turmaMatrizIdAlvo) o.selected = true;
        selMatriz.appendChild(o);
    });
}

function turmaAtualizarSerie() {
    var selCurso = document.getElementById('tu_curso_novo_id');
    var wrapSerie = document.getElementById('tu-wrap-serie-novo');
    var selSerie = document.getElementById('tu_serie_id');
    if (!selCurso || !wrapSerie || !selSerie) return;
    var id = parseInt(selCurso.value, 10);
    var opt = selCurso.options[selCurso.selectedIndex];
    var possuiSerie = opt ? parseInt(opt.getAttribute('data-possui-serie') || '1', 10) : 0;
    selSerie.innerHTML = '<option value="">Selecione a série</option>';
    selSerie.removeAttribute('required');
    if (id && possuiSerie === 1 && turmaSeriesPorCurso[id]) {
        wrapSerie.classList.remove('hidden');
        turmaSeriesPorCurso[id].forEach(function (s) {
            var o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.nome;
            if (turmaSerieIdAlvo && parseInt(s.id, 10) === turmaSerieIdAlvo) o.selected = true;
            selSerie.appendChild(o);
        });
    } else {
        wrapSerie.classList.add('hidden');
    }
    turmaAtualizarMatriz();
}

(function () {
    var selCurso = document.getElementById('tu_curso_novo_id');
    var selSerie = document.getElementById('tu_serie_id');
    if (!selCurso || !selSerie) return;
    selCurso.addEventListener('change', function () { turmaSerieIdAlvo = 0; turmaMatrizIdAlvo = 0; turmaAtualizarSerie(); });
    selSerie.addEventListener('change', function () { turmaMatrizIdAlvo = 0; turmaAtualizarMatriz(); });
})();

function turmaSetLegacyHiddenFields(cursoId, serie) {
    var wrap = document.getElementById('tu-legacy-hidden-fields');
    wrap.innerHTML = '';
    if (!cursoId) return;
    var inputCurso = document.createElement('input');
    inputCurso.type = 'hidden';
    inputCurso.name = 'curso_id';
    inputCurso.value = cursoId;
    var inputSerie = document.createElement('input');
    inputSerie.type = 'hidden';
    inputSerie.name = 'serie';
    inputSerie.value = serie || '';
    wrap.appendChild(inputCurso);
    wrap.appendChild(inputSerie);
}

function openTurmaDrawer(id) {
    var form = document.getElementById('turma-form');
    form.reset();
    document.getElementById('tu_id').value = '';
    document.getElementById('tu_ativo').checked = true;
    turmaSerieIdAlvo = 0;
    turmaMatrizIdAlvo = 0;
    turmaSetLegacyHiddenFields(null, null);

    var cursoNovoSel = document.getElementById('tu_curso_novo_id');
    if (cursoNovoSel) {
        var anoLetivoInput = document.getElementById('tu_ano_letivo_id');
        if (anoLetivoInput) anoLetivoInput.value = turmaAnoLetivoIdPadrao;
        turmaAtualizarSerie();
    }

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('turmaDrawerTitle').textContent = 'Nova Turma';
        document.getElementById('tu-form-submit-label').textContent = 'Salvar';
        showTurmaDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('turmaDrawerTitle').textContent = 'Editar Turma';
    document.getElementById('tu-form-submit-label').textContent = 'Salvar Alterações';
    showTurmaDrawer();

    fetch('<?= URL ?>/admin/turmas/' + id + '/dados')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert('Erro: ' + (data.error || '')); closeTurmaDrawer(); return; }
            var item = data.item;
            document.getElementById('tu_id').value = item.id;
            document.getElementById('tu_nome').value = item.nome;
            document.getElementById('tu_vagas').value = (item.vagas !== null && item.vagas !== undefined) ? item.vagas : '';
            document.getElementById('tu_ativo').checked = !!parseInt(item.ativo, 10);
            document.getElementById('tu_turno').value = item.turno || '';
            document.getElementById('tu_sala_padrao_id').value = item.sala_padrao_id || '';
            document.getElementById('tu_observacoes').value = item.observacoes || '';

            if (cursoNovoSel) {
                var anoLetivoInput = document.getElementById('tu_ano_letivo_id');
                if (anoLetivoInput) anoLetivoInput.value = item.ano_letivo_id || turmaAnoLetivoIdPadrao;
                turmaSerieIdAlvo = parseInt(item.serie_id, 10) || 0;
                turmaMatrizIdAlvo = parseInt(item.matriz_curricular_id, 10) || 0;
                if (item.curso_novo_id) {
                    cursoNovoSel.value = item.curso_novo_id;
                } else if (item.curso_id) {
                    // Turma antiga (fluxo legado) editada dentro da estrutura nova:
                    // preserva curso_id/serie originais em campos ocultos.
                    turmaSetLegacyHiddenFields(item.curso_id, item.serie);
                }
                turmaAtualizarSerie();
            } else {
                var cursoIdSel = document.getElementById('tu_curso_id');
                if (cursoIdSel && item.curso_id) { cursoIdSel.value = item.curso_id; }
                var serieSel = document.getElementById('tu_serie');
                if (serieSel && item.serie) { serieSel.value = item.serie; }
            }
        })
        .catch(function () { alert('Erro de conexão.'); closeTurmaDrawer(); });
}

function showTurmaDrawer() {
    document.getElementById('turmaDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('turmaDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
}

function closeTurmaDrawer() {
    var drawer = document.getElementById('turmaDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    setTimeout(function () { document.getElementById('turmaDrawerBackdrop').classList.add('hidden'); }, 300);
}

document.getElementById('turma-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var mode = this.dataset.mode;
    var id = document.getElementById('tu_id').value;
    var url = mode === 'create' ? '<?= URL ?>/admin/turmas' : '<?= URL ?>/admin/turmas/' + id;
    fetch(url, { method: 'POST', body: new FormData(this) })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) { window.location.reload(); }
            else { alert('Erro: ' + result.error); }
        })
        .catch(function () { alert('Erro de conexão. Tente novamente.'); });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeTurmaDrawer(); }
});

// Vindo da página de detalhe da turma (?edit=ID) — abre o offcanvas já em modo edição.
(function () {
    var params = new URLSearchParams(window.location.search);
    var editId = parseInt(params.get('edit'), 10);
    if (editId) { openTurmaDrawer(editId); }
})();
</script>

<script>
function toggleStatus(id, newStatus) {
    if (!confirm('Tem certeza que deseja ' + (newStatus ? 'ativar' : 'desativar') + ' esta turma?')) {
        return;
    }
    fetch('<?= URL ?>/admin/turmas/' + id + '/toggle-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(<?= json_encode($csrf_token) ?>)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Falha ao alterar status'));
        }
    })
    .catch(function () { alert('Erro de conexão. Tente novamente.'); });
}
</script>
