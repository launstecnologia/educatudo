<?php
$itens = $itens ?? [];
$cursos = $cursos ?? [];
$series = $series ?? [];
$filtros = $filtros ?? ['curso_id' => '', 'serie_id' => ''];
$status = (string) ($status ?? '');
$message = (string) ($message ?? '');
$csrf_token = $csrf_token ?? '';
$series_por_curso = $series_por_curso ?? [];
$componentes_disponiveis = $componentes_disponiveis ?? [];

$page_header_title = 'Matriz Curricular';
$page_header_subtitle = 'Defina o componente oficial de cada série. Desdobramentos somam a carga da área.';
ob_start();
?>
<button type="button" onclick="openMatrizDrawer()"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova Matriz
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';

$flash_status = $status;
$flash_message = $message;
include __DIR__ . '/../_partials/flash_message.php';
?>

<form method="GET" action="<?= URL ?>/admin/matrizes-curriculares" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap items-end gap-4">
    <div>
        <label for="curso_id" class="block text-xs font-medium text-gray-500 mb-1">Curso</label>
        <select id="curso_id" name="curso_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            <option value="">Todos</option>
            <?php foreach ($cursos as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (string) $filtros['curso_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="serie_id" class="block text-xs font-medium text-gray-500 mb-1">Série</label>
        <select id="serie_id" name="serie_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            <option value="">Todas</option>
            <?php foreach ($series as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (string) $filtros['serie_id'] === (string) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Filtrar</button>
    <?php if (!empty($filtros['curso_id']) || !empty($filtros['serie_id'])): ?>
    <a href="<?= URL ?>/admin/matrizes-curriculares" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">Limpar</a>
    <?php endif; ?>
</form>

<?php if (empty($itens)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-500">
    <i class="fa-solid fa-sitemap text-4xl text-gray-300 mb-4"></i>
    <p>Nenhuma matriz curricular cadastrada</p>
    <button type="button" onclick="openMatrizDrawer()"
       class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Cadastrar a primeira matriz
    </button>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Curso</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Componentes</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($itens as $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['nome']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($row['codigo']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['curso_nome']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['serie_nome']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($row['turno'] ?? '—') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= (int) $row['total_componentes'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $row['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $row['ativo'] ? 'Ativa' : 'Inativa' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openMatrizDrawer(<?= (int) $row['id'] ?>)"
                           class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="excluirMatriz(<?= (int) $row['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-matriz-' . (int) $row['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Offcanvas: Cadastrar/Editar Matriz Curricular -->
<div id="matrizDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeMatrizDrawer()"></div>
<aside id="matrizDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="matrizDrawerTitle" class="text-xl font-bold text-gray-900">Nova Matriz</h2>
        <button type="button" onclick="closeMatrizDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="matriz-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="mz_id" value="">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados gerais</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="mz_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome da matriz <span class="text-red-500">*</span></label>
                        <input type="text" id="mz_nome" name="nome" required placeholder="Ex.: Ensino Fundamental - 6º Ano - 2026"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="mz_codigo" class="block text-sm font-medium text-gray-700 mb-1">Código <span class="text-red-500">*</span></label>
                        <input type="text" id="mz_codigo" name="codigo" required placeholder="Ex.: EF-6-2026"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="mz_curso_id" class="block text-sm font-medium text-gray-700 mb-1">Curso <span class="text-red-500">*</span></label>
                        <select id="mz_curso_id" name="curso_id" required onchange="matrizAtualizarSeries()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione o curso</option>
                            <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="mz_serie_id" class="block text-sm font-medium text-gray-700 mb-1">Série <span class="text-red-500">*</span></label>
                        <select id="mz_serie_id" name="serie_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione o curso primeiro</option>
                        </select>
                    </div>
                    <div>
                        <label for="mz_modalidade" class="block text-sm font-medium text-gray-700 mb-1">Modalidade</label>
                        <input type="text" id="mz_modalidade" name="modalidade" placeholder="Ex.: Regular"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="mz_turno" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                        <input type="text" id="mz_turno" name="turno" placeholder="Ex.: Manhã, Todos"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="mz_duracao_padrao_aula_minutos" class="block text-sm font-medium text-gray-700 mb-1">Duração padrão da aula (min) <span class="text-red-500">*</span></label>
                        <input type="number" id="mz_duracao_padrao_aula_minutos" name="duracao_padrao_aula_minutos" min="1" required value="50"
                               oninput="matrizRecalcularCargaHoraria()"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="mz_dias_letivos_previstos" class="block text-sm font-medium text-gray-700 mb-1">Dias letivos previstos</label>
                        <input type="number" id="mz_dias_letivos_previstos" name="dias_letivos_previstos" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="mz_carga_horaria_anual_prevista" class="block text-sm font-medium text-gray-700 mb-1">Carga horária anual prevista (h)</label>
                        <input type="number" id="mz_carga_horaria_anual_prevista" name="carga_horaria_anual_prevista" min="0" step="0.5"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="mz_base_legal" class="block text-sm font-medium text-gray-700 mb-1">Base legal / referência</label>
                        <input type="text" id="mz_base_legal" name="base_legal" placeholder="Ex.: Resolução CNE/CEB nº..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center">
                            <input type="checkbox" id="mz_ativo" name="ativo" value="1" checked
                                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Matriz ativa</span>
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="mz_observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea id="mz_observacoes" name="observacoes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                </div>
            </section>

            <section>
                <div class="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Componentes Curriculares</h3>
                    <button type="button" onclick="matrizAdicionarComponente()"
                            class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fa-solid fa-plus mr-1.5"></i> Adicionar componente
                    </button>
                </div>
                <p class="text-sm text-gray-500 mb-4">Inclua o componente oficial (ex.: Língua Portuguesa). Se ele tiver desdobramentos, as aulas são lançadas em cada filho e a carga da área é a soma.</p>

                <div class="overflow-x-auto mb-2">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Componente</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Aulas/sem.</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">CH sem.</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Obrig.</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Ordem</th>
                                <th class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="matriz-componentes-body"></tbody>
                        <tfoot>
                            <tr>
                                <td class="px-3 py-2 text-sm font-semibold text-gray-700 text-right" colspan="2">Total semanal:</td>
                                <td class="px-3 py-2 text-sm font-semibold text-gray-900" id="matriz-total-semanal">0h00</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p id="matriz-componentes-vazio" class="text-sm text-gray-400 italic">Nenhum componente adicionado ainda.</p>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeMatrizDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="mz-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<template id="matriz-componente-row-template">
    <tr class="matriz-componente-row">
        <td class="px-3 py-2 align-top">
            <select class="matriz-materia-select w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm" onchange="matrizAoTrocarComponente(this)" required>
                <option value="">Selecione…</option>
                <?php foreach ($componentes_disponiveis as $comp): ?>
                <option value="<?= (int) $comp['id'] ?>" data-rotulo="<?= !empty($comp['eh_rotulo']) ? '1' : '0' ?>">
                    <?= htmlspecialchars($comp['nome']) ?><?= !empty($comp['codigo']) ? ' (' . htmlspecialchars($comp['codigo']) . ')' : '' ?><?= !empty($comp['eh_rotulo']) ? ' — área oficial' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="px-3 py-2 align-top">
            <input type="number" class="matriz-aulas-semana w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm" min="1" value="1" oninput="matrizRecalcularCargaHoraria()" required>
        </td>
        <td class="px-3 py-2 align-top text-sm text-gray-600 matriz-ch-semanal">—</td>
        <td class="px-3 py-2 align-top">
            <input type="checkbox" class="matriz-obrigatorio rounded border-gray-300 text-green-600" checked>
        </td>
        <td class="px-3 py-2 align-top">
            <input type="number" class="matriz-ordem w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm" min="0" value="0">
        </td>
        <td class="px-3 py-2 align-top">
            <button type="button" onclick="matrizRemoverComponente(this)" class="text-red-500 hover:text-red-700" aria-label="Remover">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    </tr>
</template>

<template id="matriz-grupo-header-template">
    <tr class="matriz-grupo-header bg-slate-50">
        <td class="px-3 py-2 align-top">
            <div class="text-sm font-semibold text-gray-900 matriz-grupo-nome"></div>
            <p class="text-xs text-gray-500 mt-0.5">Carga oficial = soma dos desdobramentos</p>
        </td>
        <td class="px-3 py-2 align-top text-sm text-gray-700 matriz-grupo-aulas">0</td>
        <td class="px-3 py-2 align-top text-sm text-gray-600 matriz-grupo-ch">—</td>
        <td class="px-3 py-2 align-top">
            <input type="checkbox" class="matriz-grupo-obrigatorio rounded border-gray-300 text-green-600" checked>
        </td>
        <td class="px-3 py-2 align-top">
            <input type="number" class="matriz-grupo-ordem w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm" min="0" value="0">
        </td>
        <td class="px-3 py-2 align-top">
            <button type="button" onclick="matrizRemoverGrupo(this)" class="text-red-500 hover:text-red-700" aria-label="Remover área">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    </tr>
</template>

<template id="matriz-filho-row-template">
    <tr class="matriz-componente-row matriz-filho-row">
        <td class="px-3 py-2 align-top pl-8">
            <input type="hidden" class="matriz-materia-select" value="">
            <span class="text-sm text-gray-800 matriz-filho-nome"></span>
        </td>
        <td class="px-3 py-2 align-top">
            <input type="number" class="matriz-aulas-semana w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm" min="1" value="1" oninput="matrizRecalcularCargaHoraria()" required>
        </td>
        <td class="px-3 py-2 align-top text-sm text-gray-600 matriz-ch-semanal">—</td>
        <td class="px-3 py-2 align-top">
            <input type="hidden" class="matriz-obrigatorio" value="1">
        </td>
        <td class="px-3 py-2 align-top">
            <input type="hidden" class="matriz-ordem" value="0">
        </td>
        <td class="px-3 py-2 align-top"></td>
    </tr>
</template>

<script>
var MATRIZ_SERIES_POR_CURSO = <?= json_encode($series_por_curso, JSON_UNESCAPED_UNICODE) ?>;
var MATRIZ_FILHOS_POR_PAI = <?= json_encode($filhos_por_pai ?? [], JSON_UNESCAPED_UNICODE) ?>;
var MATRIZ_NOMES_OFICIAIS = <?= json_encode(
    array_column($componentes_disponiveis ?? [], 'nome', 'id'),
    JSON_UNESCAPED_UNICODE
) ?>;
var matrizSerieAlvo = 0;

function matrizAtualizarSeries() {
    var cursoId = document.getElementById('mz_curso_id').value;
    var serieSelect = document.getElementById('mz_serie_id');
    var seriesDoCurso = MATRIZ_SERIES_POR_CURSO[cursoId] || [];

    serieSelect.innerHTML = '<option value="">Selecione a série</option>';
    seriesDoCurso.forEach(function (s) {
        var opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.nome;
        if (matrizSerieAlvo && parseInt(s.id, 10) === matrizSerieAlvo) opt.selected = true;
        serieSelect.appendChild(opt);
    });
}

function matrizFilhosDoPai(paiId) {
    return MATRIZ_FILHOS_POR_PAI[paiId] || MATRIZ_FILHOS_POR_PAI[String(paiId)] || [];
}

function matrizAdicionarComponente(dados) {
    if (dados && (dados.eh_rotulo || (dados.filhos && dados.filhos.length))) {
        matrizAdicionarGrupo(dados);
        return;
    }

    var template = document.getElementById('matriz-componente-row-template');
    var clone = template.content.cloneNode(true);
    var row = clone.querySelector('.matriz-componente-row');

    if (dados) {
        row.querySelector('.matriz-materia-select').value = dados.materia_id;
        row.querySelector('.matriz-aulas-semana').value = dados.aulas_semana;
        row.querySelector('.matriz-obrigatorio').checked = !!dados.obrigatorio;
        row.querySelector('.matriz-ordem').value = dados.ordem_boletim ?? 0;
        document.getElementById('matriz-componentes-body').appendChild(row);
        if (matrizFilhosDoPai(dados.materia_id).length) {
            matrizAoTrocarComponente(row.querySelector('.matriz-materia-select'), dados);
            return;
        }
        matrizRecalcularCargaHoraria();
        return;
    }

    document.getElementById('matriz-componentes-body').appendChild(row);
    matrizRecalcularCargaHoraria();
}

function matrizAdicionarGrupo(dados) {
    var paiId = parseInt(dados.materia_id, 10) || 0;
    var catalogo = matrizFilhosDoPai(paiId);
    var filhosSalvos = Array.isArray(dados.filhos) ? dados.filhos : [];
    var filhos = catalogo.length ? catalogo : filhosSalvos;
    if (!paiId || !filhos.length) {
        alert('Essa área ainda não tem desdobramentos cadastrados.');
        return;
    }

    var headerTpl = document.getElementById('matriz-grupo-header-template');
    var header = headerTpl.content.cloneNode(true).querySelector('.matriz-grupo-header');
    header.setAttribute('data-pai-id', String(paiId));
    header.querySelector('.matriz-grupo-nome').textContent = MATRIZ_NOMES_OFICIAIS[paiId] || MATRIZ_NOMES_OFICIAIS[String(paiId)] || dados.nome || 'Área';
    header.querySelector('.matriz-grupo-obrigatorio').checked = dados.obrigatorio === undefined ? true : !!dados.obrigatorio && dados.obrigatorio !== '0';
    header.querySelector('.matriz-grupo-ordem').value = dados.ordem_boletim ?? 0;

    var body = document.getElementById('matriz-componentes-body');
    body.appendChild(header);

    var aulasPorFilho = {};
    (dados.filhos || []).forEach(function (f) {
        aulasPorFilho[String(f.materia_id)] = f.aulas_semana;
    });

    filhos.forEach(function (filho) {
        var fid = parseInt(filho.materia_id || filho.id, 10) || 0;
        if (!fid) return;
        var filhoTpl = document.getElementById('matriz-filho-row-template');
        var row = filhoTpl.content.cloneNode(true).querySelector('.matriz-filho-row');
        row.setAttribute('data-pai-id', String(paiId));
        row.querySelector('.matriz-materia-select').value = String(fid);
        row.querySelector('.matriz-filho-nome').textContent = filho.nome || filho.materia_nome || ('#' + fid);
        row.querySelector('.matriz-aulas-semana').value = aulasPorFilho[String(fid)] || filho.aulas_semana || 1;
        body.appendChild(row);
    });

    matrizRecalcularCargaHoraria();
}

function matrizAoTrocarComponente(select, dadosOriginais) {
    var id = parseInt(select.value, 10) || 0;
    var filhos = matrizFilhosDoPai(id);
    if (!filhos.length) {
        matrizRecalcularCargaHoraria();
        return;
    }
    var row = select.closest('tr');
    var dados = dadosOriginais || {
        materia_id: id,
        obrigatorio: row.querySelector('.matriz-obrigatorio') ? row.querySelector('.matriz-obrigatorio').checked : true,
        ordem_boletim: row.querySelector('.matriz-ordem') ? row.querySelector('.matriz-ordem').value : 0,
        filhos: []
    };
    dados.materia_id = id;
    dados.eh_rotulo = true;
    if (!dados.filhos || !dados.filhos.length) {
        dados.filhos = filhos;
    }
    row.remove();
    matrizAdicionarGrupo(dados);
}

function matrizRemoverComponente(botao) {
    botao.closest('.matriz-componente-row').remove();
    matrizRecalcularCargaHoraria();
}

function matrizRemoverGrupo(botao) {
    var header = botao.closest('.matriz-grupo-header');
    var paiId = header.getAttribute('data-pai-id');
    header.remove();
    document.querySelectorAll('.matriz-filho-row[data-pai-id="' + paiId + '"]').forEach(function (row) {
        row.remove();
    });
    matrizRecalcularCargaHoraria();
}

function matrizFormatarHoras(minutos) {
    var horas = Math.floor(minutos / 60);
    var mins = minutos % 60;
    return horas + 'h' + String(mins).padStart(2, '0');
}

function matrizRecalcularCargaHoraria() {
    var duracaoAula = parseInt(document.getElementById('mz_duracao_padrao_aula_minutos').value, 10) || 0;
    var totalMinutos = 0;

    document.querySelectorAll('.matriz-componente-row').forEach(function (row) {
        var aulas = parseInt(row.querySelector('.matriz-aulas-semana').value, 10) || 0;
        var minutos = aulas * duracaoAula;
        totalMinutos += minutos;
        var cel = row.querySelector('.matriz-ch-semanal');
        if (cel) cel.textContent = matrizFormatarHoras(minutos);
    });

    document.querySelectorAll('.matriz-grupo-header').forEach(function (header) {
        var paiId = header.getAttribute('data-pai-id');
        var aulas = 0;
        document.querySelectorAll('.matriz-filho-row[data-pai-id="' + paiId + '"]').forEach(function (row) {
            aulas += parseInt(row.querySelector('.matriz-aulas-semana').value, 10) || 0;
        });
        header.querySelector('.matriz-grupo-aulas').textContent = String(aulas);
        header.querySelector('.matriz-grupo-ch').textContent = matrizFormatarHoras(aulas * duracaoAula);
    });

    document.getElementById('matriz-total-semanal').textContent = matrizFormatarHoras(totalMinutos);

    var vazio = document.getElementById('matriz-componentes-vazio');
    var qtd = document.querySelectorAll('.matriz-componente-row, .matriz-grupo-header').length;
    vazio.style.display = qtd === 0 ? '' : 'none';
}

function matrizLimparComponentes() {
    document.getElementById('matriz-componentes-body').innerHTML = '';
    matrizRecalcularCargaHoraria();
}

function openMatrizDrawer(id) {
    var form = document.getElementById('matriz-form');
    form.reset();
    document.getElementById('mz_id').value = '';
    document.getElementById('mz_ativo').checked = true;
    document.getElementById('mz_duracao_padrao_aula_minutos').value = 50;
    matrizSerieAlvo = 0;
    matrizLimparComponentes();
    matrizAtualizarSeries();

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('matrizDrawerTitle').textContent = 'Nova Matriz';
        document.getElementById('mz-form-submit-label').textContent = 'Salvar';
        showMatrizDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('matrizDrawerTitle').textContent = 'Editar Matriz';
    document.getElementById('mz-form-submit-label').textContent = 'Salvar Alterações';
    showMatrizDrawer();

    fetch('<?= URL ?>/admin/matrizes-curriculares/' + id + '/dados')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert('Erro: ' + (data.error || '')); closeMatrizDrawer(); return; }
            var item = data.item;
            document.getElementById('mz_id').value = item.id;
            document.getElementById('mz_nome').value = item.nome;
            document.getElementById('mz_codigo').value = item.codigo;
            document.getElementById('mz_curso_id').value = item.curso_id;
            document.getElementById('mz_modalidade').value = item.modalidade || '';
            document.getElementById('mz_turno').value = item.turno || '';
            document.getElementById('mz_duracao_padrao_aula_minutos').value = item.duracao_padrao_aula_minutos || 50;
            document.getElementById('mz_dias_letivos_previstos').value = item.dias_letivos_previstos ?? '';
            document.getElementById('mz_carga_horaria_anual_prevista').value = item.carga_horaria_anual_prevista ?? '';
            document.getElementById('mz_base_legal').value = item.base_legal || '';
            document.getElementById('mz_observacoes').value = item.observacoes || '';
            document.getElementById('mz_ativo').checked = !!parseInt(item.ativo, 10);

            matrizSerieAlvo = parseInt(item.serie_id, 10) || 0;
            matrizAtualizarSeries();

            matrizLimparComponentes();
            (data.componentes || []).forEach(function (c) { matrizAdicionarComponente(c); });
        })
        .catch(function () { alert('Erro de conexão.'); closeMatrizDrawer(); });
}

function showMatrizDrawer() {
    document.getElementById('matrizDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('matrizDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
}

function closeMatrizDrawer() {
    var drawer = document.getElementById('matrizDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    setTimeout(function () { document.getElementById('matrizDrawerBackdrop').classList.add('hidden'); }, 300);
}

document.getElementById('matriz-form').addEventListener('submit', function (e) {
    e.preventDefault();

    document.querySelectorAll('.matriz-grupo-header').forEach(function (header) {
        var paiId = header.getAttribute('data-pai-id');
        var obrig = header.querySelector('.matriz-grupo-obrigatorio').checked ? '1' : '';
        var ordem = header.querySelector('.matriz-grupo-ordem').value || '0';
        document.querySelectorAll('.matriz-filho-row[data-pai-id="' + paiId + '"]').forEach(function (row) {
            var obr = row.querySelector('.matriz-obrigatorio');
            var ord = row.querySelector('.matriz-ordem');
            if (obr) obr.value = obrig;
            if (ord) ord.value = ordem;
        });
    });

    // Atribui os names dinâmicos das linhas de componente só na hora de enviar.
    document.querySelectorAll('.matriz-componente-row').forEach(function (row, index) {
        row.querySelectorAll('select, input').forEach(function (field) {
            field.removeAttribute('name');
            if (field.classList.contains('matriz-materia-select')) field.name = 'componentes[' + index + '][materia_id]';
            else if (field.classList.contains('matriz-aulas-semana')) field.name = 'componentes[' + index + '][aulas_semana]';
            else if (field.classList.contains('matriz-obrigatorio')) field.name = 'componentes[' + index + '][obrigatorio]';
            else if (field.classList.contains('matriz-ordem')) field.name = 'componentes[' + index + '][ordem_boletim]';
        });
        var hiddenAnteriores = row.querySelectorAll('input[type="hidden"][data-ordem-historico]');
        hiddenAnteriores.forEach(function (h) { h.remove(); });
        var ordemInput = row.querySelector('.matriz-ordem');
        if (ordemInput) {
            // Espelha a mesma ordem no histórico — MVP não expõe os dois campos separadamente.
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.setAttribute('data-ordem-historico', '1');
            hidden.name = 'componentes[' + index + '][ordem_historico]';
            hidden.value = ordemInput.value;
            row.appendChild(hidden);
        }
    });

    var mode = this.dataset.mode;
    var id = document.getElementById('mz_id').value;
    var url = mode === 'create' ? '<?= URL ?>/admin/matrizes-curriculares' : '<?= URL ?>/admin/matrizes-curriculares/' + id + '/update';
    fetch(url, { method: 'POST', body: new FormData(this) })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) { window.location.reload(); }
            else { alert('Erro: ' + result.error); }
        })
        .catch(function () { alert('Erro de conexão. Tente novamente.'); });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeMatrizDrawer(); }
});
</script>

<script>
function excluirMatriz(id) {
    if (!confirm('Tem certeza que deseja excluir esta Matriz Curricular? Esta ação não pode ser desfeita.')) return;
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    fetch(URL_BASE + '/admin/matrizes-curriculares/' + id + '/delete', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((r) => r.json())
        .then((data) => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir: ' + (data.error || 'tente novamente'));
            }
        })
        .catch(() => alert('Erro de conexão'));
}
</script>
