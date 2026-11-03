<?php
$page_header_title = 'Componentes Curriculares';
$page_header_subtitle = 'Cadastre o componente curricular, a área do conhecimento e o tipo em cada ciclo ou etapa de ensino.';
$trilhaItens = \App\Helpers\TrilhaAdmin::montar('/admin/componentes-curriculares', 'Componentes Curriculares');
$filtros = is_array($filtros ?? null) ? $filtros : ['nome' => '', 'sigla' => '', 'area' => '', 'etapa' => ''];
$filtrosAtivos = 0;
foreach (['nome', 'sigla', 'area', 'etapa'] as $chaveFiltro) {
    if (trim((string) ($filtros[$chaveFiltro] ?? '')) !== '') {
        $filtrosAtivos++;
    }
}
$ciclosFiltro = [
    'etapa_infantil' => 'Educação Infantil',
    'etapa_fund_i' => 'Ensino Fundamental I',
    'etapa_fund_ii' => 'Ensino Fundamental II',
    'etapa_medio' => 'Ensino Médio',
];
?>
<div class="mb-6">
    <?php include __DIR__ . '/../../../../../Views/layouts/components/trilha.php'; ?>
    <div class="mt-4 flex justify-between items-center gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                <i class="fa-solid fa-book text-lg" aria-hidden="true"></i>
            </span>
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($page_header_title) ?></h2>
                <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($page_header_subtitle) ?></p>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 flex-wrap flex-shrink-0 ml-auto">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivos > 0): ?>
                    <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
                <?php endif; ?>
            </button>
            <?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
            <?php
            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Log';
            $ui_btn_icon = 'fa-solid fa-clock-rotate-left';
            $ui_btn_onclick = 'abrirTrilhaComponentes()';
            include \App\Core\ViewPaths::adminPartial('ui/btn.php');
            ?>
            <?php endif; ?>
            <button type="button" onclick="openComponenteDrawer()"
                    class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Componente Curricular
            </button>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div>
        <table class="w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Componente curricular</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Área do conhecimento</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Etapas de ensino</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">Situação</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-400">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-book text-4xl text-gray-300 mb-4"></i>
                        <?php if ($filtrosAtivos > 0): ?>
                            <p>Nenhum componente curricular encontrado com esses filtros</p>
                            <a href="<?= URL ?>/admin/componentes-curriculares" class="inline-block mt-4 text-sm font-medium text-blue-600 hover:text-blue-800">Limpar filtros</a>
                        <?php else: ?>
                            <p>Nenhum componente curricular cadastrado</p>
                            <button type="button" onclick="openComponenteDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                                <i class="fa-solid fa-plus mr-2"></i> Novo Componente Curricular
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php
                $idsComFilhos = is_array($ids_com_filhos ?? null) ? $ids_com_filhos : [];
                $nomePorId = is_array($nome_por_id ?? null) ? $nome_por_id : [];
                if ($nomePorId === []) {
                    foreach ($items as $itMap) {
                        $nomePorId[(int) ($itMap['id'] ?? 0)] = (string) ($itMap['nome'] ?? '');
                    }
                }
                $idsNaLista = [];
                foreach ($items as $itemLista) {
                    $idsNaLista[(int) ($itemLista['id'] ?? 0)] = true;
                }
                $filhosPorPai = [];
                $raizesLista = [];
                foreach ($items as $itemLista) {
                    $paiLista = (int) ($itemLista['pai_id'] ?? 0);
                    if ($paiLista > 0 && isset($idsNaLista[$paiLista])) {
                        $filhosPorPai[$paiLista][] = $itemLista;
                        continue;
                    }
                    $raizesLista[] = $itemLista;
                }
                $linhasLista = [];
                foreach ($raizesLista as $raizLista) {
                    $raizId = (int) ($raizLista['id'] ?? 0);
                    $filhosLista = $filhosPorPai[$raizId] ?? [];
                    $linhasLista[] = [
                        'item' => $raizLista,
                        'filho' => false,
                        'tem_filhos' => $filhosLista !== [],
                        'ultimo' => false,
                        'pai' => 0,
                    ];
                    $totalFilhos = count($filhosLista);
                    foreach ($filhosLista as $indiceFilho => $filhoLista) {
                        $linhasLista[] = [
                            'item' => $filhoLista,
                            'filho' => true,
                            'tem_filhos' => false,
                            'ultimo' => $indiceFilho === $totalFilhos - 1,
                            'pai' => $raizId,
                        ];
                    }
                }
                $etapasCurtas = [
                    'etapa_infantil' => 'Infantil',
                    'etapa_fund_i' => 'Fund. I',
                    'etapa_fund_ii' => 'Fund. II',
                    'etapa_medio' => 'Ensino Médio',
                ];
                ?>
                <?php foreach ($linhasLista as $linha): ?>
                <?php
                $item = $linha['item'];
                $itemId = (int) ($item['id'] ?? 0);
                $paiIdItem = (int) ($item['pai_id'] ?? 0);
                $codigoItem = trim((string) ($item['codigo'] ?? ''));
                $siglaItem = trim((string) ($item['sigla'] ?? ''));
                $areaItem = (string) ($areas_conhecimento[$item['area_conhecimento'] ?? ''] ?? ($item['area_conhecimento'] ?? ''));
                $etapasItem = [
                    'etapa_infantil' => $item['etapa_infantil'] ?? 'nao_aplica',
                    'etapa_fund_i' => $item['etapa_fund_i'] ?? 'nao_aplica',
                    'etapa_fund_ii' => $item['etapa_fund_ii'] ?? 'nao_aplica',
                    'etapa_medio' => $item['etapa_medio'] ?? 'nao_aplica',
                ];
                $ehFilho = !empty($linha['filho']);
                $temFilhos = !empty($linha['tem_filhos']);
                $paiVisivel = (int) ($linha['pai'] ?? 0);
                ?>
                <tr class="<?= $ehFilho ? 'bg-gray-50 hover:bg-gray-100' : 'hover:bg-gray-50' ?>" <?= $ehFilho ? 'data-cc-filho="' . $paiVisivel . '"' : '' ?>>
                    <td class="align-middle px-4 py-3">
                        <div class="flex items-start gap-2 min-w-0 <?= $ehFilho ? 'pl-10' : '' ?>">
                            <?php if (!$ehFilho): ?>
                                <div class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center">
                                    <?php if ($temFilhos): ?>
                                        <button type="button" class="cc-seta flex h-5 w-5 items-center justify-center text-gray-400 hover:text-gray-600" aria-expanded="true" aria-label="Recolher desdobramentos de <?= htmlspecialchars($item['nome'] ?? '') ?>" onclick="alternarFilhosComponente(<?= $itemId ?>, this)">
                                            <svg class="cc-seta-marca" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                                <path d="M2.25 4.25 L6 8 L9.75 4.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($item['nome'] ?? '') ?></span>
                                    <?php if ($temFilhos || isset($idsComFilhos[$itemId])): ?>
                                        <span class="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">Unidade</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-0.5 whitespace-nowrap text-xs text-gray-500">
                                    Código: <?= $codigoItem !== '' ? htmlspecialchars($codigoItem) : '—' ?>
                                    <span class="mx-1 text-gray-300" aria-hidden="true">·</span>
                                    Sigla: <?= $siglaItem !== '' ? htmlspecialchars($siglaItem) : '—' ?>
                                </p>
                                <?php if (!$ehFilho && $paiIdItem > 0): ?>
                                    <p class="mt-1 text-xs font-medium text-violet-700">Abaixo de <?= htmlspecialchars($nomePorId[$paiIdItem] ?? '—') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 align-middle text-sm text-gray-600"><?= $areaItem !== '' ? htmlspecialchars($areaItem) : '—' ?></td>
                    <td class="px-4 py-3 align-middle">
                        <div class="flex flex-wrap gap-1">
                            <?php $temEtapa = false; ?>
                            <?php foreach ($etapasItem as $campoEtapa => $valorEtapa): ?>
                                <?php if ($valorEtapa === 'nao_aplica' || $valorEtapa === '') continue; ?>
                                <?php $temEtapa = true; ?>
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium <?= $valorEtapa === 'obrigatoria' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600' ?>" title="<?= htmlspecialchars($etapas[$valorEtapa] ?? $valorEtapa) ?>">
                                    <?= htmlspecialchars($etapasCurtas[$campoEtapa] ?? $campoEtapa) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (!$temEtapa): ?>
                                <span class="text-xs text-gray-400">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 align-middle whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($item['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= !empty($item['ativo']) ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 align-middle whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openComponenteDrawer(<?= (int) $item['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="deleteComponente(<?= (int) $item['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-cc-' . (int) $item['id'];
                        include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php');
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($items)): ?>
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 px-6 py-4">
        <p class="text-sm text-gray-600">Exibindo 1–<?= count($items) ?> de <?= count($items) ?> componentes</p>
    </div>
    <?php endif; ?>
</div>

<style>
.cc-seta-marca { display: block; transition: transform .15s ease; }
.cc-seta.cc-fechado .cc-seta-marca { transform: rotate(-90deg); }
</style>

<!-- Criar/Editar componente curricular em drawer lateral -->
<div id="componenteDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeComponenteDrawer()"></div>
<aside id="componenteDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="componenteDrawerTitle" class="text-xl font-bold text-gray-900">Novo Componente Curricular</h2>
        <button type="button" onclick="closeComponenteDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="componente-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" id="cc_id" value="">
        <input type="hidden" name="_method" id="cc_method" value="" disabled>

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Componente Curricular</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="cc_nome" class="block text-sm font-medium text-gray-700 mb-1">Componente Curricular *</label>
                        <input type="text" id="cc_nome" name="nome" required placeholder="Ex: Matemática"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="cc_codigo" class="block text-sm font-medium text-gray-700 mb-1">Código *</label>
                        <input type="text" id="cc_codigo" name="codigo" required placeholder="Ex: MAT"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 uppercase">
                    </div>
                    <div>
                        <label for="cc_sigla" class="block text-sm font-medium text-gray-700 mb-1">Sigla</label>
                        <input type="text" id="cc_sigla" name="sigla" placeholder="Ex: MAT"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 uppercase">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cc_pai_id" class="block text-sm font-medium text-gray-700 mb-1">Unidade Curricular</label>
                        <div class="relative">
                            <select id="cc_pai_id" name="pai_id"
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="">Nenhuma — este componente é a unidade curricular e tem nota própria</option>
                                <?php foreach (($pais_candidatos ?? []) as $paiOpt): ?>
                                    <?php $pidOpt = (int) ($paiOpt['id'] ?? 0); ?>
                                    <?php if ($pidOpt <= 0) { continue; } ?>
                                    <option value="<?= $pidOpt ?>"><?= htmlspecialchars((string) ($paiOpt['nome'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">A unidade curricular é o componente oficial da matriz e do histórico (ex.: Língua Portuguesa). Os desdobramentos (Gramática, Redação) recebem aula, nota e diário; a carga da unidade é a soma deles.</p>
                    </div>
                    <div>
                        <label for="cc_area_conhecimento" class="block text-sm font-medium text-gray-700 mb-1">Área do Conhecimento *</label>
                        <div class="relative">
                            <select id="cc_area_conhecimento" name="area_conhecimento" required
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <?php foreach ($areas_conhecimento as $valor => $rotulo): ?>
                                <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label for="cc_tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo/Categoria *</label>
                        <div class="relative">
                            <select id="cc_tipo" name="tipo" required
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <?php foreach ($tipos as $valor => $rotulo): ?>
                                <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label for="cc_cor" class="block text-sm font-medium text-gray-700 mb-1">Cor</label>
                        <input type="color" id="cc_cor" name="cor" value="#3B82F6"
                               class="h-10 w-full px-1 py-1 border border-gray-300 rounded-lg cursor-pointer">
                    </div>
                    <div>
                        <label for="cc_ordem" class="block text-sm font-medium text-gray-700 mb-1">Ordem de exibição</label>
                        <input type="number" id="cc_ordem" name="ordem" value="0" min="0" step="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cc_descricao" class="block text-sm font-medium text-gray-700 mb-1">Ementa</label>
                        <textarea id="cc_descricao" name="descricao" rows="3" placeholder="Descrição do conteúdo do componente curricular"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Ciclo / Etapa de Ensino</h3>
                <p class="text-xs text-gray-500 mb-4 -mt-2">Para cada ciclo ou etapa, defina o tipo de componente: obrigatório, eletivo ou se não se aplica.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <?php
                    $etapasCampos = [
                        'etapa_infantil' => 'Educação Infantil',
                        'etapa_fund_i' => 'Ensino Fundamental I',
                        'etapa_fund_ii' => 'Ensino Fundamental II',
                        'etapa_medio' => 'Ensino Médio',
                    ];
                    ?>
                    <?php foreach ($etapasCampos as $campo => $rotulo): ?>
                    <div>
                        <label for="cc_<?= $campo ?>" class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($rotulo) ?></label>
                        <div class="relative">
                            <select id="cc_<?= $campo ?>" name="<?= $campo ?>"
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <?php foreach ($etapas as $valor => $rotuloEtapa): ?>
                                <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotuloEtapa) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Permissões e Situação</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" id="cc_permite_avaliacao" name="permite_avaliacao" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="text-sm font-medium text-gray-700">Permite avaliação</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" id="cc_permite_frequencia" name="permite_frequencia" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="text-sm font-medium text-gray-700">Permite frequência</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" id="cc_permite_plano_aula" name="permite_plano_aula" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="text-sm font-medium text-gray-700">Permite plano de aula</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" id="cc_permite_diario" name="permite_diario" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="text-sm font-medium text-gray-700">Permite diário</span>
                    </label>
                    <label class="flex items-center gap-3 sm:col-span-2 pt-2 border-t border-gray-100">
                        <input type="checkbox" id="cc_ativo" name="ativo" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">Ativo</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Componentes inativos ficam ocultos nas telas de cadastro que os utilizam.</span>
                        </span>
                    </label>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeComponenteDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="componente-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Filtrar componentes</h3>
            <p class="mt-0.5 text-xs text-gray-500">Nome, sigla, área e ciclo ou etapa de ensino.</p>
        </div>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar filtros">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/admin/componentes-curriculares" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro-nome" class="block text-sm font-medium text-gray-700 mb-1.5">Nome do componente</label>
                <input type="text" id="filtro-nome" name="nome" value="<?= htmlspecialchars((string) ($filtros['nome'] ?? '')) ?>"
                       placeholder="Ex: Matemática"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro-sigla" class="block text-sm font-medium text-gray-700 mb-1.5">Sigla</label>
                <input type="text" id="filtro-sigla" name="sigla" value="<?= htmlspecialchars((string) ($filtros['sigla'] ?? '')) ?>"
                       placeholder="Ex: MAT"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase">
            </div>
            <div>
                <label for="filtro-area" class="block text-sm font-medium text-gray-700 mb-1.5">Área do conhecimento</label>
                <select id="filtro-area" name="area"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach (($areas_conhecimento ?? []) as $valorArea => $rotuloArea): ?>
                        <option value="<?= htmlspecialchars((string) $valorArea) ?>" <?= (string) ($filtros['area'] ?? '') === (string) $valorArea ? 'selected' : '' ?>><?= htmlspecialchars((string) $rotuloArea) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-etapa" class="block text-sm font-medium text-gray-700 mb-1.5">Ciclo / etapa de ensino</label>
                <select id="filtro-etapa" name="etapa"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($ciclosFiltro as $campoEtapa => $rotuloEtapa): ?>
                        <option value="<?= htmlspecialchars($campoEtapa) ?>" <?= (string) ($filtros['etapa'] ?? '') === $campoEtapa ? 'selected' : '' ?>><?= htmlspecialchars($rotuloEtapa) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500">Lista quem é obrigatório ou eletivo nesse ciclo. O que não se aplica fica de fora.</p>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= URL ?>/admin/componentes-curriculares" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">Limpar</a>
            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">Aplicar filtros</button>
        </div>
    </form>
</aside>

<script>
const URL_BASE = <?= json_encode(URL) ?>;
const componenteCheckboxFields = ['permite_avaliacao', 'permite_frequencia', 'permite_plano_aula', 'permite_diario', 'ativo'];
const componenteTextFields = ['nome', 'codigo', 'sigla', 'area_conhecimento', 'tipo', 'etapa_infantil', 'etapa_fund_i', 'etapa_fund_ii', 'etapa_medio', 'cor', 'ordem', 'descricao', 'pai_id'];

function showComponenteDrawer() {
    document.getElementById('componenteDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('componenteDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeComponenteDrawer() {
    document.getElementById('componenteDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('componenteDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openComponenteDrawer(id) {
    const form = document.getElementById('componente-form');
    form.reset();
    document.getElementById('cc_id').value = '';
    document.getElementById('cc_method').value = '';
    document.getElementById('cc_method').disabled = true;
    document.getElementById('cc_cor').value = '#3B82F6';

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('componenteDrawerTitle').textContent = 'Novo Componente Curricular';
        document.getElementById('componente-form-submit-label').textContent = 'Salvar';
        componenteCheckboxFields.forEach((field) => {
            document.getElementById('cc_' + field).checked = true;
        });
        var paiElCreate = document.getElementById('cc_pai_id');
        if (paiElCreate) paiElCreate.value = '';
        showComponenteDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('componenteDrawerTitle').textContent = 'Editar Componente Curricular';
    document.getElementById('componente-form-submit-label').textContent = 'Salvar Alterações';
    document.getElementById('cc_method').value = 'PUT';
    document.getElementById('cc_method').disabled = false;

    showComponenteDrawer();

    fetch(URL_BASE + '/admin/componentes-curriculares/' + id + '/dados')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar o componente curricular'));
                closeComponenteDrawer();
                return;
            }
            const item = data.item;
            document.getElementById('cc_id').value = item.id;
            componenteTextFields.forEach((field) => {
                const el = document.getElementById('cc_' + field);
                if (el && item[field] !== null && item[field] !== undefined && item[field] !== '') {
                    el.value = item[field];
                }
            });
            componenteCheckboxFields.forEach((field) => {
                document.getElementById('cc_' + field).checked = !!parseInt(item[field], 10);
            });
            var paiEl = document.getElementById('cc_pai_id');
            if (paiEl) {
                paiEl.value = item.pai_id ? String(item.pai_id) : '';
            }
        })
        .catch(() => {
            alert('Erro de conexão ao carregar componente curricular.');
            closeComponenteDrawer();
        });
}

document.getElementById('componente-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const mode = this.dataset.mode;
    const id = document.getElementById('cc_id').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const submitLabel = document.getElementById('componente-form-submit-label');
    const originalText = submitLabel.textContent;

    submitBtn.disabled = true;
    submitLabel.textContent = 'Salvando...';

    const url = mode === 'create' ? (URL_BASE + '/admin/componentes-curriculares') : (URL_BASE + '/admin/componentes-curriculares/' + id);

    fetch(url, { method: 'POST', body: new FormData(this), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Erro ao salvar'));
            }
        })
        .catch(() => alert('Erro de conexão. Tente novamente.'))
        .finally(() => {
            submitBtn.disabled = false;
            submitLabel.textContent = originalText;
        });
});

function deleteComponente(id) {
    if (!confirm('Tem certeza que deseja excluir este componente curricular? Esta ação não pode ser desfeita.')) return;
    const formData = new FormData();
    formData.append('_method', 'DELETE');
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    fetch(URL_BASE + '/admin/componentes-curriculares/' + id, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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

function alternarFilhosComponente(id, botao) {
    var aberto = botao.getAttribute('aria-expanded') === 'true';
    var rotulo = botao.getAttribute('aria-label') || '';
    botao.setAttribute('aria-expanded', aberto ? 'false' : 'true');
    botao.setAttribute('aria-label', aberto ? rotulo.replace('Recolher', 'Expandir') : rotulo.replace('Expandir', 'Recolher'));
    botao.classList.toggle('cc-fechado', aberto);
    document.querySelectorAll('[data-cc-filho="' + id + '"]').forEach(function (tr) {
        tr.classList.toggle('hidden', aberto);
    });
}

function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') {
        return;
    }
    const filtro = document.getElementById('filterDrawer');
    if (filtro && !filtro.classList.contains('translate-x-full')) {
        closeFilterDrawer();
        return;
    }
    closeComponenteDrawer();
});
</script>
<?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
<?php
$trilha_log_id = 'trilhaComponentes';
$trilha_log_titulo = 'Log de componentes curriculares';
$trilha_log_url = URL . '/admin/componentes-curriculares/logs';
$trilha_log_vazio = 'Nenhum cadastro, alteração ou exclusão de componente curricular registrado.';
include \App\Core\ViewPaths::adminPartial('ui/trilha_log.php');
?>
<?php endif; ?>
