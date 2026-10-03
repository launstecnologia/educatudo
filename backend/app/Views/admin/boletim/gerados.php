<?php
$user = $user ?? [];
$regrasCatalogo = is_array($regras_catalogo ?? null) ? $regras_catalogo : [];
$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$perPage = (int) ($per_page ?? 50);
$totalPages = (int) ($total_pages ?? 1);

$flashMessage = trim((string) ($flash_message ?? ''));
$flashType = (string) ($flash_type ?? 'success');
if (!class_exists('PeriodoLetivo')) {
    require_once __DIR__ . '/../../../Core/PeriodoLetivo.php';
}

$selectedRegraId = (int) ($filters['regra_id'] ?? 0);
$alunoQ = (string) ($filters['aluno_q'] ?? '');
$exibirEmFilter = (string) ($filters['exibir_em'] ?? '');
$previewFilter = (string) ($filters['preview'] ?? 'all');
$atualizadoDe = (string) ($filters['atualizado_de'] ?? '');
$atualizadoAte = (string) ($filters['atualizado_ate'] ?? '');

$toDatetimeLocal = static function (string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    // já vem como "YYYY-MM-DDTHH:MM" — devolve direto.
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $v)) return substr($v, 0, 16);
    if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})/', $v, $m)) return $m[1] . 'T' . $m[2];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v . 'T00:00';
    return '';
};
$atualizadoDeInput = $toDatetimeLocal($atualizadoDe);
$atualizadoAteInput = $toDatetimeLocal($atualizadoAte);
$filtrosAtivosCount = 0;
if ($selectedRegraId > 0) {
    $filtrosAtivosCount++;
}
if ($alunoQ !== '') {
    $filtrosAtivosCount++;
}
if ($exibirEmFilter !== '') {
    $filtrosAtivosCount++;
}
if ($previewFilter !== 'all') {
    $filtrosAtivosCount++;
}
if ($atualizadoDe !== '') {
    $filtrosAtivosCount++;
}
if ($atualizadoAte !== '') {
    $filtrosAtivosCount++;
}

$queryBaseParams = array_filter([
    'regra_id' => $selectedRegraId > 0 ? $selectedRegraId : null,
    'aluno_q' => $alunoQ !== '' ? $alunoQ : null,
    'exibir_em' => $exibirEmFilter !== '' ? $exibirEmFilter : null,
    'preview' => $previewFilter !== 'all' ? $previewFilter : null,
    'atualizado_de' => $atualizadoDe !== '' ? $atualizadoDe : null,
    'atualizado_ate' => $atualizadoAte !== '' ? $atualizadoAte : null,
    'per_page' => $perPage !== 50 ? $perPage : null,
], static function ($v) { return $v !== null && $v !== ''; });

$buildPageUrl = static function (int $p) use ($queryBaseParams): string {
    $params = $queryBaseParams;
    if ($p > 1) {
        $params['page'] = $p;
    }
    $qs = http_build_query($params);
    return URL . '/admin/boletim-configuracao/gerados' . ($qs !== '' ? ('?' . $qs) : '');
};

?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Versão de Notas</h1>
        <div class="flex items-center justify-between flex-wrap gap-3">
            <a href="<?= URL ?>/admin/boletim"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-arrow-left mr-2 text-gray-500"></i>
                Voltar
            </a>
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
    <aside id="filterDrawer"
           class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
           aria-hidden="true">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Filtrar versões</h3>
            <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <form method="GET" id="form-filtros-gerados" action="<?= URL ?>/admin/boletim-configuracao/gerados" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Regra</label>
            <select name="regra_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="0">Todas as regras</option>
                <?php foreach ($regrasCatalogo as $regCat): ?>
                    <?php $rid = (int) ($regCat['id'] ?? 0); ?>
                    <?php if ($rid <= 0) { continue; } ?>
                    <?php
                    $nomeCat = trim((string) ($regCat['nome'] ?? 'Regra'));
                    $codigoCat = trim((string) ($regCat['codigo'] ?? ''));
                    $label = '#' . $rid . ' - ' . $nomeCat . ($codigoCat !== '' ? (' (' . $codigoCat . ')') : '');
                    ?>
                    <option value="<?= $rid ?>" <?= $selectedRegraId === $rid ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Aluno (nome ou RA)</label>
            <input type="text" name="aluno_q" value="<?= htmlspecialchars($alunoQ, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex.: João, 2026..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Exibido em</label>
            <select name="exibir_em" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todos</option>
                <option value="boletim" <?= $exibirEmFilter === 'boletim' ? 'selected' : '' ?>>Boletim</option>
                <option value="notas" <?= $exibirEmFilter === 'notas' ? 'selected' : '' ?>>Notas</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
            <select name="preview" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="all" <?= $previewFilter === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="0" <?= $previewFilter === '0' ? 'selected' : '' ?>>Oficial</option>
                <option value="1" <?= $previewFilter === '1' ? 'selected' : '' ?>>Preview (simulação)</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Atualizado de</label>
            <input type="datetime-local" name="atualizado_de"
                   value="<?= htmlspecialchars($atualizadoDeInput, ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Atualizado até</label>
            <input type="datetime-local" name="atualizado_ate"
                   value="<?= htmlspecialchars($atualizadoAteInput, ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= URL ?>/admin/boletim-configuracao/gerados"
               class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors text-center">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
        </form>
    </aside>

    <?php if ($flashMessage !== ''): ?>
        <?php $bgClass = $flashType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>
        <div class="p-4 rounded-lg border <?= $bgClass ?>"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3 flex-wrap">
            <div class="text-sm text-gray-700">
                <strong><?= number_format($total, 0, ',', '.') ?></strong> boletim(ns) gerado(s)
                <?php if ($totalPages > 1): ?>
                    &middot; página <strong><?= (int) $page ?></strong> de <strong><?= (int) $totalPages ?></strong>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($total > 0): ?>
                    <span class="text-xs text-gray-500 mr-1">Exportar notas</span>
                    <button type="button" class="btn-exportar-notas inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50" data-formato="json">JSON</button>
                    <button type="button" class="btn-exportar-notas inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-emerald-300 text-emerald-800 bg-white hover:bg-emerald-50" data-formato="excel">Excel</button>
                    <button type="button" class="btn-exportar-notas inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-indigo-300 text-indigo-800 bg-white hover:bg-indigo-50" data-formato="pdf">PDF</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div class="p-8 text-center text-gray-500 text-sm">Nenhum boletim gerado encontrado com esses filtros.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm" id="tabela-boletins-gerados">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Ref</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Aluno</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Turma</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Bimestre</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Ano</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Vigente</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Histórico</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($rows as $idx => $row): ?>
                            <?php
                            $alunoId = (int) ($row['aluno_id'] ?? 0);
                            $regraId = (int) ($row['regra_id'] ?? 0);
                            $periodoRef = (string) ($row['periodo_ref'] ?? '');
                            $vigenteFlag = (int) ($row['vigente'] ?? 0) === 1;
                            $anoLinha = (int) ($row['regra_ano_letivo'] ?? 0);
                            $bimestreLinha = (int) ($row['regra_bimestre'] ?? 0);
                            $rotuloBimestre = $bimestreLinha > 0
                                ? PeriodoLetivo::rotulo($anoLinha > 0 ? $anoLinha : (int) date('Y'), $bimestreLinha)
                                : '';
                            $rowKey = $alunoId . '-' . $regraId . '-' . md5($periodoRef);
                            ?>
                            <tr class="boletim-gerado-row <?= $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>"
                                data-aluno-id="<?= $alunoId ?>"
                                data-regra-id="<?= $regraId ?>"
                                data-periodo-ref="<?= htmlspecialchars($periodoRef, ENT_QUOTES, 'UTF-8') ?>"
                                data-aluno-nome="<?= htmlspecialchars((string) ($row['aluno_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-regra-nome="<?= htmlspecialchars((string) ($row['regra_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-key="<?= htmlspecialchars($rowKey, ENT_QUOTES, 'UTF-8') ?>">
                                <td class="px-3 py-2 text-gray-900 align-top whitespace-nowrap"><?= $regraId > 0 ? $regraId : '—' ?></td>
                                <td class="px-3 py-2 font-medium text-gray-900">
                                    <?= htmlspecialchars((string) ($row['aluno_nome'] ?? 'Aluno #' . $alunoId), ENT_QUOTES, 'UTF-8') ?>
                                    <span class="block text-xs font-normal text-gray-500"><?= htmlspecialchars((string) ($row['regra_nome'] ?? 'Regra #' . $regraId), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($row['aluno_ra'])): ?>
                                        <span class="block text-[11px] font-normal text-gray-400">RA <?= htmlspecialchars((string) $row['aluno_ra'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-gray-700 align-top"><?= htmlspecialchars((string) ($row['turma_nome'] ?? '—'), ENT_QUOTES, 'UTF-8') ?: '—' ?></td>
                                <td class="px-3 py-2 text-gray-700 align-top whitespace-nowrap"><?= htmlspecialchars($rotuloBimestre !== '' ? $rotuloBimestre : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-gray-700 align-top whitespace-nowrap"><?= $anoLinha > 0 ? $anoLinha : '—' ?></td>
                                <td class="px-3 py-2 align-top">
                                    <?php if ($vigenteFlag): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">Sim</span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-gray-600 align-top">
                                    <?php
                                    $boletim_versoes = is_array($row['versoes'] ?? null) ? $row['versoes'] : [];
                                    $boletim_versoes_fallback = (string) ($row['updated_at'] ?? '');
                                    $boletim_versoes_compact = true;
                                    require __DIR__ . '/../../partials/boletim_versoes_historico.php';
                                    ?>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap align-top">
                                    <?php ob_start(); ?>
                                    <button type="button"
                                            class="btn-preview-boletim flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap">
                                        <i class="fa-solid fa-eye text-gray-400 w-4 text-center shrink-0"></i> Preview
                                    </button>
                                    <?php
                                    $row_actions_dropdown_items = ob_get_clean();
                                    $row_actions_dropdown_id = 'boletim-gerado-' . $rowKey;
                                    include __DIR__ . '/../_partials/row_actions_dropdown.php';
                                    ?>
                                </td>
                            </tr>
                            <tr class="preview-row hidden">
                                <td colspan="8" class="px-3 py-3 bg-indigo-50/40">
                                    <div class="preview-content rounded-lg bg-white border border-indigo-100 p-3 text-sm text-gray-700">
                                        <div class="preview-placeholder text-gray-500">Carregando preview…</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-between gap-2 flex-wrap text-sm">
                    <div class="text-gray-600">Página <?= (int) $page ?> de <?= (int) $totalPages ?></div>
                    <div class="flex items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= htmlspecialchars($buildPageUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">‹ Anterior</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= htmlspecialchars($buildPageUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">Próxima ›</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
    }
});
(function () {
    var exportUrl = <?= json_encode(URL . '/admin/boletim-configuracao/gerados/exportar', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    document.querySelectorAll('.btn-exportar-notas').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = document.getElementById('form-filtros-gerados');
            var params = new URLSearchParams(form ? new FormData(form) : undefined);
            params.set('formato', btn.getAttribute('data-formato') || 'json');
            window.location = exportUrl + '?' + params.toString();
        });
    });
})();
(function () {
    var baseUrl = <?= json_encode(rtrim((string) (defined('URL') ? URL : ''), '/'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var tabela = document.getElementById('tabela-boletins-gerados');
    if (!tabela) return;

    function getRowData(btn) {
        var row = btn.closest('tr.boletim-gerado-row');
        if (!row) {
            var preview = btn.closest('tr.preview-row');
            row = preview ? preview.previousElementSibling : null;
        }
        if (!row || !row.classList.contains('boletim-gerado-row')) return null;
        return {
            row: row,
            previewRow: row.nextElementSibling && row.nextElementSibling.classList.contains('preview-row') ? row.nextElementSibling : null,
            alunoId: parseInt(row.getAttribute('data-aluno-id') || '0', 10),
            regraId: parseInt(row.getAttribute('data-regra-id') || '0', 10),
            periodoRef: row.getAttribute('data-periodo-ref') || '',
        };
    }

    function carregarPreview(d, versao) {
        if (!d || !d.previewRow) return;
        var content = d.previewRow.querySelector('.preview-content');
        if (!content) return;
        d.previewRow.classList.remove('hidden');
        d.previewRow.dataset.loaded = '0';
        d.previewRow.dataset.versao = versao > 0 ? String(versao) : '';
        content.innerHTML = '<div class="preview-placeholder text-gray-500">Carregando preview…</div>';
        var params = new URLSearchParams();
        params.set('aluno_id', String(d.alunoId));
        params.set('regra_id', String(d.regraId));
        params.set('periodo_ref', d.periodoRef);
        if (versao > 0) params.set('versao', String(versao));
        fetch(baseUrl + '/admin/boletim-configuracao/gerados/preview?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'text/html' },
        }).then(function (resp) {
            return resp.text().then(function (text) { return { ok: resp.ok, text: text }; });
        }).then(function (res) {
            if (!res.ok) {
                content.innerHTML = '<div class="p-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">Falha ao carregar preview.</div>';
                return;
            }
            content.innerHTML = res.text || '<div class="text-sm text-gray-500">Sem conteúdo para exibir.</div>';
            d.previewRow.dataset.loaded = '1';
        }).catch(function () {
            content.innerHTML = '<div class="p-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">Erro de rede ao carregar preview.</div>';
        });
    }

    tabela.addEventListener('click', function (e) {
        var btnVersao = e.target.closest('.btn-ver-versao');
        if (btnVersao) {
            e.preventDefault();
            var versao = parseInt(btnVersao.getAttribute('data-versao') || '0', 10);
            carregarPreview(getRowData(btnVersao), versao);
            return;
        }
        var btnPreview = e.target.closest('.btn-preview-boletim');
        if (!btnPreview) return;
        e.preventDefault();
        document.querySelectorAll('[data-dropdown-menu]').forEach(function (m) { m.classList.add('hidden'); });
        var d = getRowData(btnPreview);
        if (!d || !d.previewRow) return;
        if (!d.previewRow.classList.contains('hidden') && d.previewRow.dataset.loaded === '1' && (d.previewRow.dataset.versao || '') === '') {
            d.previewRow.classList.add('hidden');
            return;
        }
        if (d.previewRow.dataset.loaded === '1' && (d.previewRow.dataset.versao || '') === '') {
            d.previewRow.classList.remove('hidden');
            return;
        }
        carregarPreview(d, 0);
    });
})();
</script>
