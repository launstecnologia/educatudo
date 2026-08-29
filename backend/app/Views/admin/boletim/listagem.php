<?php
$eventos = $eventos ?? [];
$csrfToken = (string) ($csrf_token ?? '');
$filtroNome = (string) ($filtro_nome ?? '');
$filtroAno = (string) ($filtro_ano ?? '');
$filtroBimestre = (string) ($filtro_bimestre ?? '');
$flashMessage = (string) ($flash_message ?? '');
$flashType = (string) ($flash_type ?? 'success');
$temGeracaoEmAndamento = !empty($tem_geracao_em_andamento);
$geracaoJobIds = array_values(array_filter(array_map('intval', $geracao_job_ids ?? [])));
$geracaoConcluidaMsg = trim((string) ($geracao_concluida_msg ?? ''));
$filtrosAtivosCount = 0;
foreach ([$filtroNome, $filtroAno, $filtroBimestre] as $fv) {
    if ($fv !== '') {
        $filtrosAtivosCount++;
    }
}

$bimestreLabel = static function ($bimestre) {
    $bimestre = (int) $bimestre;
    return $bimestre > 0 ? $bimestre . 'º Bimestre' : 'N/A';
};
$dataHoraGeracao = static function (?string $valor): ?string {
    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }
    $ts = strtotime($valor);
    return $ts !== false ? date('d/m/Y H:i', $ts) : null;
};
$duracaoGeracao = static function (?string $inicio, ?string $fim): ?string {
    $a = strtotime(trim((string) $inicio));
    $b = strtotime(trim((string) $fim));
    if ($a === false || $b === false || $b < $a) {
        return null;
    }
    $seg = $b - $a;
    if ($seg < 60) {
        return $seg . 's';
    }
    $min = intdiv($seg, 60);
    $resto = $seg % 60;
    if ($min < 60) {
        return $resto > 0 ? $min . ' min ' . $resto . 's' : $min . ' min';
    }
    $horas = intdiv($min, 60);
    $min = $min % 60;

    return $min > 0 ? $horas . 'h ' . $min . ' min' : $horas . 'h';
};
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Eventos de Notas</h2>
            <p class="text-gray-600">Configure os eventos do bimestre. Ao gerar, as médias entram no boletim da Vida Escolar.</p>
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
            <a href="<?= URL ?>/admin/boletim-guia"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-book mr-2 text-gray-500"></i>
                Guia do Boletim
            </a>
            <a href="<?= URL ?>/admin/boletim-configuracao/gerados"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-file-lines mr-2 text-gray-500"></i>
                Boletins Gerados
            </a>
            <a href="<?= URL ?>/admin/boletim-configuracao?novo=1"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Evento
            </a>
        </div>
    </div>
</div>

<?php if ($flashMessage !== ''): ?>
    <?php
    $flashClasses = 'bg-green-50 border-green-200 text-green-800';
    if ($flashType === 'error') {
        $flashClasses = 'bg-red-50 border-red-200 text-red-800';
    } elseif ($flashType === 'info') {
        $flashClasses = 'bg-amber-50 border-amber-200 text-amber-900';
    }
    ?>
    <div class="mb-6 p-4 rounded-lg border <?= $flashClasses ?>"><?= htmlspecialchars($flashMessage) ?></div>
<?php endif; ?>

<?php if ($flashMessage === '' && $geracaoConcluidaMsg !== ''): ?>
    <div class="mb-6 p-4 rounded-lg border bg-green-50 border-green-200 text-green-800"><?= htmlspecialchars($geracaoConcluidaMsg) ?></div>
<?php endif; ?>

<?php if ($temGeracaoEmAndamento): ?>
    <div class="mb-6 p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 flex items-start justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-spinner fa-spin mt-0.5"></i>
            <div>
                <p class="font-semibold">Gerando boletins em segundo plano</p>
                <p class="text-sm mt-0.5">Você pode continuar na listagem. Esta página atualiza sozinha quando a geração terminar.</p>
            </div>
        </div>
        <form method="POST" action="<?= URL ?>/admin/boletim/cancelar-geracao" class="shrink-0"
              onsubmit="return confirm('Parar a geração em andamento? Os boletins já gravados deste lote ficam como estão.');">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-red-600 hover:bg-red-700">
                <i class="fa-solid fa-stop mr-2"></i>
                Parar geração
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar eventos</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/boletim" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_nome" class="block text-sm font-medium text-gray-700 mb-1.5">Nome ou código</label>
                <input type="text" id="filtro_nome" name="nome" value="<?= htmlspecialchars($filtroNome) ?>"
                       placeholder="Buscar por nome ou código..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_ano" class="block text-sm font-medium text-gray-700 mb-1.5">Ano letivo</label>
                <input type="number" id="filtro_ano" name="ano_letivo" value="<?= htmlspecialchars($filtroAno) ?>"
                       placeholder="Ex.: 2026"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_bimestre" class="block text-sm font-medium text-gray-700 mb-1.5">Bimestre</label>
                <select id="filtro_bimestre" name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php for ($b = 1; $b <= 4; $b++): ?>
                        <option value="<?= $b ?>" <?= $filtroBimestre === (string) $b ? 'selected' : '' ?>><?= $b ?>º Bimestre</option>
                    <?php endfor; ?>
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

<!-- Excluir evento (form compartilhado) -->
<form id="form-excluir-evento-boletim" method="POST" action="<?= URL ?>/admin/boletim-configuracao/excluir-regra" class="hidden" aria-hidden="true">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="regra_id" id="excluir-evento-regra-id" value="">
</form>

<!-- Duplicar evento (form compartilhado) -->
<form id="form-duplicar-evento-boletim" method="POST" action="<?= URL ?>/admin/boletim-configuracao/duplicar-regra" class="hidden" aria-hidden="true">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="regra_id" id="duplicar-evento-regra-id" value="">
</form>

<!-- Visibilidade para aluno/pais (form compartilhado) -->
<form id="form-visibilidade-evento-boletim" method="POST" action="<?= URL ?>/admin/boletim-configuracao/visibilidade-regra" class="hidden" aria-hidden="true">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="regra_id" id="visibilidade-evento-regra-id" value="">
    <input type="hidden" name="visivel" id="visibilidade-evento-valor" value="">
</form>

<!-- Eventos Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exibir em</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Séries</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano Letivo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bimestre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Geração</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($eventos)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-file-lines text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum evento de boletim cadastrado</p>
                        <a href="<?= URL ?>/admin/boletim-configuracao?novo=1"
                           class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Criar Primeiro Evento
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($eventos as $evento): ?>
                <?php $eventoId = (int) ($evento['id'] ?? 0); ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($evento['nome'] ?? '')) ?></div>
                        <?php if (!empty($evento['codigo'])): ?>
                        <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) $evento['codigo']) ?></div>
                        <?php endif; ?>
                        <?php
                        $stGeracao = (string) ($evento['geracao_status'] ?? '');
                        $erroGeracao = trim((string) ($evento['geracao_erro'] ?? ''));
                        ?>
                        <?php if (in_array($stGeracao, ['pending', 'processing'], true)): ?>
                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                            <i class="fa-solid fa-spinner fa-spin"></i> Gerando…
                        </span>
                        <?php elseif ($stGeracao === 'failed'): ?>
                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800"
                              title="<?= htmlspecialchars($erroGeracao !== '' ? $erroGeracao : 'A geração em segundo plano falhou.') ?>">
                            <i class="fa-solid fa-circle-exclamation"></i> Falhou
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col items-start gap-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= ($evento['exibir_em'] ?? 'boletim') === 'notas' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
                                <?= ($evento['exibir_em'] ?? 'boletim') === 'notas' ? 'Notas' : 'Boletim' ?>
                            </span>
                            <?php $liberadoAlunoPais = ((int) ($evento['vis_aluno'] ?? 1) === 1) && ((int) ($evento['vis_pais'] ?? 1) === 1); ?>
                            <span class="inline-flex px-2 py-0.5 text-[11px] font-medium rounded-full <?= $liberadoAlunoPais ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                                <?= $liberadoAlunoPais ? 'Aluno/pais liberado' : 'Aluno/pais oculto' ?>
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <?php $seriesNomes = $evento['series_nomes'] ?? []; ?>
                        <?php if (empty($seriesNomes)): ?>
                            <span class="text-sm text-gray-500">Todas as séries</span>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-1 max-w-xs">
                                <?php foreach ($seriesNomes as $serieNome): ?>
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700"><?= htmlspecialchars((string) $serieNome) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?= !empty($evento['ano_letivo']) ? (int) $evento['ano_letivo'] : 'N/A' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?= $bimestreLabel($evento['bimestre'] ?? null) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php
                        $geracaoEmAndamento = in_array($stGeracao, ['pending', 'processing'], true);
                        $inicioBruto = trim((string) ($evento['geracao_iniciada_em'] ?? ''));
                        $fimBruto = trim((string) ($evento['geracao_completed_at'] ?? ''));
                        if ($fimBruto === '' && !$geracaoEmAndamento) {
                            $fimBruto = trim((string) ($evento['ultima_geracao'] ?? ''));
                        }
                        $inicioGeracao = $dataHoraGeracao($inicioBruto !== '' ? $inicioBruto : null);
                        $fimGeracao = $dataHoraGeracao($fimBruto !== '' ? $fimBruto : null);
                        $duracaoTxt = $geracaoEmAndamento
                            ? $duracaoGeracao($inicioBruto !== '' ? $inicioBruto : null, date('Y-m-d H:i:s'))
                            : $duracaoGeracao($inicioBruto !== '' ? $inicioBruto : null, $fimBruto !== '' ? $fimBruto : null);
                        ?>
                        <div>Início <?= $inicioGeracao ?? '—' ?></div>
                        <div class="mt-0.5">Término <?= $geracaoEmAndamento ? 'em andamento' : ($fimGeracao ?? '—') ?></div>
                        <?php if ($duracaoTxt !== null): ?>
                        <div class="mt-0.5 text-xs text-gray-400">Duração <?= htmlspecialchars($duracaoTxt) ?><?= $geracaoEmAndamento ? ' até agora' : '' ?></div>
                        <?php endif; ?>
                        <?php if (!empty($evento['boletim_desatualizado'])): ?>
                            <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300"
                                  title="A configuração foi alterada depois da última geração em massa. Os boletins já visíveis para alunos/pais podem estar com a regra antiga.">
                                <i class="fa-solid fa-triangle-exclamation"></i> Desatualizado
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/boletim-configuracao?regra_id=<?= $eventoId ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </a>
                        <button type="button" onclick="duplicarEventoBoletim(<?= $eventoId ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-copy text-gray-400 w-4 text-center"></i> Duplicar
                        </button>
                        <?php $liberadoAlunoPaisMenu = ((int) ($evento['vis_aluno'] ?? 1) === 1) && ((int) ($evento['vis_pais'] ?? 1) === 1); ?>
                        <button type="button" onclick="alterarVisibilidadeEventoBoletim(<?= $eventoId ?>, <?= $liberadoAlunoPaisMenu ? 0 : 1 ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid <?= $liberadoAlunoPaisMenu ? 'fa-eye-slash' : 'fa-eye' ?> text-gray-400 w-4 text-center"></i>
                            <?= $liberadoAlunoPaisMenu ? 'Ocultar de alunos/pais' : 'Liberar para alunos/pais' ?>
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <?php if ($geracaoEmAndamento): ?>
                        <form method="POST" action="<?= URL ?>/admin/boletim/cancelar-geracao" class="block"
                              onsubmit="return confirm('Parar a geração deste evento?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="regra_id" value="<?= $eventoId ?>">
                            <button type="submit"
                                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-stop text-red-400 w-4 text-center"></i> Parar geração
                            </button>
                        </form>
                        <?php endif; ?>
                        <button type="button" onclick="excluirEventoBoletim(<?= $eventoId ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-evento-boletim-' . $eventoId;
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
    $pagTotal = (int) ($pag['total'] ?? 0);
    $pagPerPage = (int) ($pag['per_page'] ?? 10);
    $pagPage = (int) ($pag['page'] ?? 1);
    $pagTotalPages = (int) ($pag['total_pages'] ?? 1);
    $pagQueryParams = $_GET ?? [];
    unset($pagQueryParams['page']);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> evento(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/boletim<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/boletim<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/boletim<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
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
    window.location.href = <?= json_encode(URL . '/admin/boletim') ?>;
}

function duplicarEventoBoletim(id) {
    if (!confirm('Duplicar este evento? Uma cópia será criada com nome e código próprios.')) {
        return;
    }
    document.getElementById('duplicar-evento-regra-id').value = id;
    document.getElementById('form-duplicar-evento-boletim').submit();
}

function excluirEventoBoletim(id) {
    if (!confirm('Excluir este evento? Ele deixa de aparecer no catálogo, no boletim e em Notas.')) {
        return;
    }
    document.getElementById('excluir-evento-regra-id').value = id;
    document.getElementById('form-excluir-evento-boletim').submit();
}

function alterarVisibilidadeEventoBoletim(id, visivel) {
    const acao = Number(visivel) === 1 ? 'liberar para alunos e pais visualizarem' : 'ocultar de alunos e pais';
    if (!confirm('Deseja ' + acao + ' este evento?')) {
        return;
    }
    document.getElementById('visibilidade-evento-regra-id').value = id;
    document.getElementById('visibilidade-evento-valor').value = Number(visivel) === 1 ? '1' : '0';
    document.getElementById('form-visibilidade-evento-boletim').submit();
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
    }
});

(function pollGeracaoBoletim() {
    const jobIds = <?= json_encode($geracaoJobIds, JSON_UNESCAPED_UNICODE) ?>;
    if (!Array.isArray(jobIds) || jobIds.length === 0) {
        return;
    }
    const ids = jobIds.map(function (id) { return Number(id); }).filter(function (id) { return id > 0; });
    const url = <?= json_encode(URL . '/admin/boletim/geracao-status', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        + '?ids=' + encodeURIComponent(ids.join(','));

    function aindaRodando(jobs) {
        if (!jobs || typeof jobs !== 'object') {
            return true;
        }
        return ids.some(function (id) {
            const st = jobs[String(id)] || jobs[id];
            if (!st) {
                return true;
            }
            return st.status === 'pending' || st.status === 'processing';
        });
    }

    function tick() {
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    setTimeout(tick, 5000);
                    return;
                }
                if (!aindaRodando(data.jobs)) {
                    window.location.reload();
                    return;
                }
                setTimeout(tick, 3000);
            })
            .catch(function () {
                setTimeout(tick, 5000);
            });
    }
    setTimeout(tick, 2500);
})();
</script>
