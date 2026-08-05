<?php
$filtrosAtivosCount = 0;
foreach ([$filtro_professor ?? '', $filtro_turma ?? '', $filtro_materia ?? '', $filtro_tipo_ensino ?? '', $filtro_bimestre ?? '', $filtro_avaliativo ?? '', $filtro_busca ?? ''] as $fv) {
    if ($fv !== '' && $fv !== null) {
        $filtrosAtivosCount++;
    }
}
?>
<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Gestão de Jornadas</h2>
            <p class="text-gray-600">Crie e gerencie as jornadas de aprendizado</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
            <a href="<?= URL ?>/admin/jornadas/relatorio"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-chart-simple mr-2 text-gray-500"></i>
                Relatório
            </a>
            <a href="<?= URL ?>/admin/jornadas/criar"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Nova Jornada
            </a>
        </div>
    </div>
</div>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar jornadas</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/jornadas" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="busca" class="block text-sm font-medium text-gray-700 mb-1.5">Buscar</label>
                <input id="busca" name="busca"
                       value="<?= htmlspecialchars((string) ($filtro_busca ?? '')) ?>"
                       placeholder="Título, professor, turma..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="professor_id" class="block text-sm font-medium text-gray-700 mb-1.5">Professor</label>
                <select id="professor_id" name="professor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($professores as $professor): ?>
                        <option value="<?= $professor['id'] ?>" <?= ($filtro_professor ?? '') == $professor['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($professor['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= $turma['id'] ?>" <?= ($filtro_turma ?? '') == $turma['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turma['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-1.5">Matéria</label>
                <select id="materia_id" name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= $materia['id'] ?>" <?= ($filtro_materia ?? '') == $materia['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($materia['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tipo_ensino" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Ensino</label>
                <select id="tipo_ensino" name="tipo_ensino" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($tipos_ensino as $tipo): ?>
                        <?php $tipoValue = $tipo['tipo_ensino'] ?? ''; ?>
                        <?php if ($tipoValue === '') continue; ?>
                        <option value="<?= htmlspecialchars($tipoValue) ?>" <?= ($filtro_tipo_ensino ?? '') == $tipoValue ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tipoValue) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="bimestre" class="block text-sm font-medium text-gray-700 mb-1.5">Bimestre</label>
                <select id="bimestre" name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="1" <?= ($filtro_bimestre ?? '') == '1' ? 'selected' : '' ?>>1º Bimestre</option>
                    <option value="2" <?= ($filtro_bimestre ?? '') == '2' ? 'selected' : '' ?>>2º Bimestre</option>
                    <option value="3" <?= ($filtro_bimestre ?? '') == '3' ? 'selected' : '' ?>>3º Bimestre</option>
                    <option value="4" <?= ($filtro_bimestre ?? '') == '4' ? 'selected' : '' ?>>4º Bimestre</option>
                </select>
            </div>
            <div>
                <label for="avaliativo" class="block text-sm font-medium text-gray-700 mb-1.5">Avaliativo</label>
                <select id="avaliativo" name="avaliativo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="1" <?= ($filtro_avaliativo ?? '') == '1' ? 'selected' : '' ?>>Sim</option>
                    <option value="0" <?= ($filtro_avaliativo ?? '') == '0' ? 'selected' : '' ?>>Não</option>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <button type="button" onclick="clearFilters()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<!-- Journeys Table (admin - Lista de Jornadas) -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Jornadas</h3>
        <?php if (!empty($jornadas)): ?>
        <div class="flex items-center gap-2">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" id="selecionar-todas-jornadas" class="rounded border-gray-300">
                Selecionar todas da página
            </label>
            <button type="button"
                    id="btn-inativar-lote"
                    class="px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                Inativar selecionadas
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">seleção</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">titulo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">termas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">jornada</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">inicio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">final</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">acoes</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($jornadas)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <p>Nenhuma jornada cadastrada</p>
                            <a href="<?= URL ?>/admin/jornadas/criar" class="btn-primary-custom mt-4 inline-block px-4 py-2 rounded-lg text-sm transition-colors hover:opacity-90">
                                Criar Primeira Jornada
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($jornadas as $journey): ?>
                    <?php
                        $dataInicio = $journey['data_inicio'] ?? null;
                        $horaInicio = $journey['hora_inicio'] ?? null;
                        $dataFim = $journey['data_fim'] ?? null;
                        $horaFim = $journey['hora_fim'] ?? null;
                        $inicioFormatado = $dataInicio ? date('d/m/Y', strtotime($dataInicio)) . ($horaInicio ? ' ' . $horaInicio : '') : '—';
                        $fimFormatado = $dataFim ? date('d/m/Y', strtotime($dataFim)) . ($horaFim ? ' ' . $horaFim : '') : '—';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <input type="checkbox"
                                   class="jornada-select rounded border-gray-300"
                                   value="<?= (int) $journey['id'] ?>">
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-gray-900"><?= htmlspecialchars($journey['titulo']) ?></div>
                            <div class="text-xs text-gray-500 mt-0.5">
                                <?php
                                $prof = trim((string)($journey['professor_nome'] ?? ''));
                                $materia = trim((string)($journey['materia_nome'] ?? ''));
                                $prof = $prof !== '' ? htmlspecialchars($prof) : 'N/A';
                                $materia = $materia !== '' ? htmlspecialchars($materia) : '—';
                                ?>
                                <?= $prof ?> — <?= $materia ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php 
                            $turmasNomes = $journey['turmas_selecionadas_nomes'] ?? [$journey['turma_nome'] ?? ''];
                            $turmasNomes = array_filter($turmasNomes);
                            echo !empty($turmasNomes) ? htmlspecialchars(implode(', ', $turmasNomes)) : htmlspecialchars($journey['turma_nome'] ?? '—');
                            ?>
                        </td>
                        <td class="px-6 py-4 text-sm" title="<?= (isset($journey['realizou_count']) && $journey['realizou_count'] !== null) ? '' : 'Clique em Visualizar para ver realizou/não realizou' ?>">
                            <div class="flex items-center gap-3 flex-wrap">
                                <?php if (isset($journey['realizou_count']) && $journey['realizou_count'] !== null): ?>
                                <span class="inline-flex items-center gap-1.5 text-green-600" title="Realizou a jornada">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span class="font-medium"><?= (int)$journey['realizou_count'] ?></span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-red-600" title="Não realizou a jornada">
                                    <span class="relative inline-flex items-center justify-center">
                                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        <span class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                            <span class="w-6 h-0.5 bg-red-600 transform rotate-45 block rounded"></span>
                                        </span>
                                    </span>
                                    <span class="font-medium"><?= (int)$journey['nao_realizou_count'] ?></span>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-400">—</span>
                                <span class="text-xs text-gray-500">(na jornada)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $inicioFormatado ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $fimFormatado ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?php ob_start(); ?>
                            <a href="<?= URL ?>/admin/jornadas/<?= $journey['id'] ?>"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Visualizar
                            </a>
                            <a href="<?= URL ?>/admin/jornadas/<?= $journey['id'] ?>/editar"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <button type="button"
                                    onclick="abrirModalInativar(<?= (int)$journey['id'] ?>, this.getAttribute('data-titulo'))"
                                    data-titulo="<?= htmlspecialchars($journey['titulo'] ?? 'Jornada', ENT_QUOTES, 'UTF-8') ?>"
                                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-ban text-red-400 w-4 text-center"></i> Inativar
                            </button>
                            <?php
                            $row_actions_dropdown_items = ob_get_clean();
                            $row_actions_dropdown_id = 'row-actions-jornada-' . (int)$journey['id'];
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
    $pagTotal = (int)($pag['total_jornadas'] ?? 0);
    $pagPerPage = (int)($pag['per_page'] ?? 10);
    $pagPage = (int)($pag['page'] ?? 1);
    $pagTotalPages = (int)($pag['total_pages'] ?? 1);
    $pagQueryParams = array_filter([
        'professor_id' => $filtro_professor ?? '',
        'turma_id' => $filtro_turma ?? '',
        'materia_id' => $filtro_materia ?? '',
        'tipo_ensino' => $filtro_tipo_ensino ?? '',
        'bimestre' => $filtro_bimestre ?? '',
        'avaliativo' => $filtro_avaliativo ?? '',
        'busca' => $filtro_busca ?? '',
    ], static function ($value) {
        return $value !== '' && $value !== null;
    });
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> jornada(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/jornadas<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/jornadas<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/jornadas<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function abrirModalInativar(jornadaId, titulo) {
        var tituloEsc = esc(titulo);
        var existente = document.getElementById('modalInativarJornada');
        if (existente) existente.remove();
        var modal = document.createElement('div');
        modal.id = 'modalInativarJornada';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = '<div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">' +
            '<div class="p-6">' +
            '<div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">' +
            '<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>' +
            '</div>' +
            '<h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Confirmar inativação</h3>' +
            '<p class="text-sm text-gray-600 text-center mb-4">A jornada <strong>"' + tituloEsc + '"</strong> será inativada e não aparecerá mais para o professor nem para o aluno. Ela permanece no sistema (apenas oculta). Digite sua senha de admin para confirmar.</p>' +
            '<div class="mb-4">' +
            '<label class="block text-sm font-medium text-gray-700 mb-2">Digite sua senha para confirmar:</label>' +
            '<input type="password" id="senhaInativarAdmin" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Sua senha" autocomplete="current-password">' +
            '<p id="erroSenhaInativar" class="text-red-600 text-xs mt-1 hidden"></p>' +
            '</div>' +
            '<div class="flex space-x-3">' +
            '<button type="button" onclick="fecharModalInativar()" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium">Cancelar</button>' +
            '<button type="button" id="btnConfirmarInativarAdmin" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium flex items-center justify-center gap-2">Inativar</button>' +
            '</div></div></div>';
        document.body.appendChild(modal);
        document.getElementById('btnConfirmarInativarAdmin').addEventListener('click', function() { confirmarInativar(jornadaId); });
        var inputSenha = document.getElementById('senhaInativarAdmin');
        if (inputSenha) {
            setTimeout(function() { inputSenha.focus(); }, 100);
            inputSenha.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') fecharModalInativar();
                else if (e.key === 'Enter') { e.preventDefault(); confirmarInativar(jornadaId); }
            });
        }
    }
    function fecharModalInativar() {
        var modal = document.getElementById('modalInativarJornada');
        if (modal) modal.remove();
    }
    function confirmarInativar(jornadaId) {
        var senha = document.getElementById('senhaInativarAdmin').value;
        var erroEl = document.getElementById('erroSenhaInativar');
        if (!senha) {
            erroEl.textContent = 'Por favor, digite sua senha';
            erroEl.classList.remove('hidden');
            return;
        }
        erroEl.classList.add('hidden');
        var btn = document.getElementById('btnConfirmarInativarAdmin');
        if (btn) { btn.disabled = true; btn.textContent = 'Inativando...'; }
        var formData = new FormData();
        formData.append('_token', '<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>');
        formData.append('jornada_id', jornadaId);
        formData.append('senha', senha);
        fetch('<?= URL ?>/admin/jornadas/inativar', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    fecharModalInativar();
                    location.reload();
                } else {
                    erroEl.textContent = data.error || 'Erro ao inativar';
                    erroEl.classList.remove('hidden');
                    if (btn) { btn.disabled = false; btn.textContent = 'Inativar'; }
                }
            })
            .catch(function() {
                erroEl.textContent = 'Erro de conexão. Tente novamente.';
                erroEl.classList.remove('hidden');
                if (btn) { btn.disabled = false; btn.textContent = 'Inativar'; }
            });
    }

    (function () {
        var checkAll = document.getElementById('selecionar-todas-jornadas');
        var btnLote = document.getElementById('btn-inativar-lote');
        var csrfToken = '<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>';

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.jornada-select:checked'))
                .map(function (el) { return parseInt(el.value || '0', 10); })
                .filter(function (id) { return id > 0; });
        }

        function updateBtnState() {
            if (!btnLote) return;
            btnLote.disabled = getSelectedIds().length === 0;
        }

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                var checked = !!checkAll.checked;
                document.querySelectorAll('.jornada-select').forEach(function (cb) {
                    cb.checked = checked;
                });
                updateBtnState();
            });
        }

        document.querySelectorAll('.jornada-select').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var all = document.querySelectorAll('.jornada-select');
                var checked = document.querySelectorAll('.jornada-select:checked');
                if (checkAll) {
                    checkAll.checked = all.length > 0 && all.length === checked.length;
                }
                updateBtnState();
            });
        });

        function abrirModalInativarLote(ids) {
            var existente = document.getElementById('modalInativarLoteJornada');
            if (existente) existente.remove();
            var modal = document.createElement('div');
            modal.id = 'modalInativarLoteJornada';
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = '<div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">' +
                '<div class="p-6">' +
                '<div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">' +
                '<svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>' +
                '</div>' +
                '<h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Confirmar inativação em lote</h3>' +
                '<p class="text-sm text-gray-600 text-center mb-4">Você selecionou <strong>' + ids.length + ' jornada(s)</strong>. Digite sua senha para confirmar a inativação.</p>' +
                '<div class="mb-4">' +
                '<label class="block text-sm font-medium text-gray-700 mb-2">Digite sua senha para confirmar:</label>' +
                '<input type="password" id="senhaInativarLoteAdmin" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Sua senha" autocomplete="current-password">' +
                '<p id="erroSenhaInativarLote" class="text-red-600 text-xs mt-1 hidden"></p>' +
                '</div>' +
                '<div class="flex space-x-3">' +
                '<button type="button" id="btnCancelarInativarLote" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium">Cancelar</button>' +
                '<button type="button" id="btnConfirmarInativarLoteAdmin" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium">Inativar selecionadas</button>' +
                '</div></div></div>';
            document.body.appendChild(modal);

            document.getElementById('btnCancelarInativarLote').addEventListener('click', function () {
                modal.remove();
            });
            document.getElementById('btnConfirmarInativarLoteAdmin').addEventListener('click', function () {
                confirmarInativarLote(ids);
            });
            var inputSenha = document.getElementById('senhaInativarLoteAdmin');
            if (inputSenha) {
                setTimeout(function () { inputSenha.focus(); }, 60);
            }
        }

        function confirmarInativarLote(ids) {
            var senhaEl = document.getElementById('senhaInativarLoteAdmin');
            var erroEl = document.getElementById('erroSenhaInativarLote');
            var btn = document.getElementById('btnConfirmarInativarLoteAdmin');
            var senha = senhaEl ? senhaEl.value : '';
            if (!senha) {
                erroEl.textContent = 'Por favor, digite sua senha';
                erroEl.classList.remove('hidden');
                return;
            }
            erroEl.classList.add('hidden');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Inativando...';
            }

            var formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('senha', senha);
            ids.forEach(function (id) {
                formData.append('jornada_ids[]', String(id));
            });
            fetch('<?= URL ?>/admin/jornadas/inativar-lote', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                        return;
                    }
                    erroEl.textContent = data.error || 'Erro ao inativar jornadas.';
                    erroEl.classList.remove('hidden');
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Inativar selecionadas';
                    }
                })
                .catch(function () {
                    erroEl.textContent = 'Erro de conexão. Tente novamente.';
                    erroEl.classList.remove('hidden');
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Inativar selecionadas';
                    }
                });
        }

        if (btnLote) {
            btnLote.addEventListener('click', function () {
                var ids = getSelectedIds();
                if (ids.length === 0) {
                    return;
                }
                abrirModalInativarLote(ids);
            });
            updateBtnState();
        }
    })();

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

    function clearFilters() {
        window.location.href = <?= json_encode(URL . '/admin/jornadas') ?>;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilterDrawer();
        }
    });
</script>
