<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$alunoId = (int) ($student['id'] ?? $aluno_id ?? 0);
$base = URL . '/admin/students/' . $alunoId . '/vida-escolar';
$token = (string) ($csrf_token ?? '');
$prontuario = is_array($vida_escolar_prontuario ?? null) ? $vida_escolar_prontuario : [];
$capa = is_array($prontuario['capa'] ?? null) ? $prontuario['capa'] : [];
$links = is_array($prontuario['links'] ?? null) ? $prontuario['links'] : [];
$fichaId = (int) ($prontuario['ficha_id'] ?? ($_GET['ficha_id'] ?? 0));
$aluno = is_array($prontuario['aluno'] ?? null) ? $prontuario['aluno'] : $student;
$matricula = is_array($prontuario['matricula'] ?? null) ? $prontuario['matricula'] : ($matriculaAtual ?? []);
$unidade = is_array($prontuario['unidade'] ?? null) ? $prontuario['unidade'] : [];
$sed = is_array($prontuario['sed'] ?? null) ? $prontuario['sed'] : ['itens' => []];
$inep = is_array($prontuario['inep'] ?? null) ? $prontuario['inep'] : ['edicoes' => []];
$docs_checklist = is_array($prontuario['docs_checklist'] ?? null) ? $prontuario['docs_checklist'] : ['itens' => []];
$docs_recebidos = is_array($prontuario['docs_recebidos'] ?? null) ? $prontuario['docs_recebidos'] : [];
$documentos = $docs_recebidos;
$historicos = is_array($prontuario['historicos'] ?? null) ? $prontuario['historicos'] : [];
$resultados = is_array($prontuario['resultados'] ?? null) ? $prontuario['resultados'] : [];
$emissoes = is_array($prontuario['emissoes'] ?? null) ? $prontuario['emissoes'] : [];
$fichas = is_array($prontuario['fichas'] ?? null) ? $prontuario['fichas'] : [];
$quadro = $prontuario['quadro'] ?? null;
$trajetoria = is_array($prontuario['trajetoria'] ?? null) ? $prontuario['trajetoria'] : ['anos' => []];
$importacoes = is_array($prontuario['importacoes'] ?? null) ? $prontuario['importacoes'] : [];
$materias = is_array($prontuario['materias'] ?? null) ? $prontuario['materias'] : [];
$schema_pronto = !empty($prontuario['schema_pronto'] ?? $vida_escolar_schema ?? false);
$periodos = is_array($prontuario['periodos'] ?? null) ? $prontuario['periodos'] : [1 => '1º', 2 => '2º', 3 => '3º', 4 => '4º', 0 => 'FINAL'];
$aluno_id = $alunoId;
$ficha_id = $fichaId;
$csrf_token = $token;
$ai_job_id = (int) ($_GET['ai_job'] ?? $ai_job_id ?? 0);
$pode_ler_ia = !empty($vida_escolar_pode_ler_ia);
$podeVidaEscolar = !empty($admin_permissions['vida_escolar']['visualizar']);
$podeNotasTab = !empty($admin_permissions['tab_notas']['visualizar']);
$podeBoletimTab = !empty($admin_permissions['tab_boletim']['visualizar']);

$veAbasValidas = ['boletim', 'notas', 'trajetoria', 'documentos', 'conferencia', 'dossie'];
$veAbaPedido = strtolower(trim((string) ($_GET['ve_aba'] ?? $_GET['aba'] ?? '')));
$veAba = $veAbaPedido !== '' ? $veAbaPedido : 'boletim';
if ($veAba === 'identidade') {
    $veAba = 'boletim';
}
if (!in_array($veAba, $veAbasValidas, true)) {
    $veAba = 'boletim';
}
if ($veAbaPedido === '' && !$podeVidaEscolar && $podeNotasTab) {
    $veAba = 'notas';
}

$fichaUrl = URL . '/admin/students/' . $alunoId;
$hrefAba = static function (string $nome, array $extra = []) use ($fichaUrl, $fichaId): string {
    $q = array_merge(['tab' => 'vida-escolar', 've_aba' => $nome], $extra);
    if ($fichaId > 0 && !isset($q['ficha_id'])) {
        $q['ficha_id'] = $fichaId;
    }
    return $fichaUrl . '?' . http_build_query($q);
};

$veAbas = [
    'boletim' => ['label' => 'Boletim', 'icon' => 'fa-table', 'perm_key' => 'vida_escolar'],
    'notas' => ['label' => 'Notas', 'icon' => 'fa-list-ol', 'perm_key' => 'tab_notas'],
    'trajetoria' => ['label' => 'Trajetória', 'icon' => 'fa-timeline', 'perm_key' => 'vida_escolar'],
    'documentos' => ['label' => 'Histórico / emissões', 'icon' => 'fa-folder-open', 'perm_key' => 'vida_escolar'],
    'conferencia' => ['label' => 'SED / INEP', 'icon' => 'fa-clipboard-check', 'perm_key' => 'vida_escolar'],
    'dossie' => ['label' => 'Dossiê', 'icon' => 'fa-box-archive', 'perm_key' => 'vida_escolar'],
];
$hrefPacote = !empty($links['pacote']) ? (URL . $links['pacote']) : ($base . '/pacote-transferencia');
$hrefDossie = !empty($links['dossie']) ? (URL . $links['dossie']) : ($base . '/dossie');
$modDir = dirname(__DIR__, 3) . '/Modulos/vida-escolar/Views/admin';
$mostrarCapa = $podeVidaEscolar && $prontuario !== [];
?>
<div class="space-y-5">
    <?php if ($podeVidaEscolar && empty($schema_pronto)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-900 text-sm">
        Aplique a migration <code>2026_08_25_vida_escolar</code> no Master para gravar a ficha oficial. O prontuário já mostra cadastro, SED/INEP e documentos emitidos.
    </div>
    <?php endif; ?>

    <?php if ($mostrarCapa): ?>
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        <?php
        $resumoCards = [
            ['label' => 'Situação', 'value' => (string) ($capa['situacao'] ?? '—'), 'icon' => 'fa-user-graduate', 'valueClass' => 'text-gray-900', 'iconClass' => 'bg-slate-100 text-slate-600'],
            ['label' => 'Ficha do ano', 'value' => (string) ($capa['status_ficha_label'] ?? 'Sem ficha'), 'icon' => 'fa-table', 'valueClass' => 'text-gray-900', 'iconClass' => 'bg-blue-50 text-blue-600'],
            ['label' => 'Documentos', 'value' => (string) ($capa['docs_txt'] ?? '—'), 'icon' => 'fa-folder-open', 'valueClass' => !empty($capa['docs_ok']) ? 'text-green-700' : 'text-amber-700', 'iconClass' => 'bg-amber-50 text-amber-600'],
            ['label' => 'SED / INEP', 'value' => (string) ($capa['sed_txt'] ?? '—'), 'icon' => 'fa-clipboard-check', 'valueClass' => !empty($capa['sed_ok']) ? 'text-green-700' : 'text-amber-700', 'iconClass' => 'bg-green-50 text-green-600'],
        ];
        foreach ($resumoCards as $card):
        ?>
        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500"><?= $esc($card['label']) ?></p>
                    <p class="mt-1 text-lg font-bold leading-tight <?= $esc($card['valueClass']) ?>"><?= $esc($card['value']) ?></p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= $esc($card['iconClass']) ?>">
                    <i class="fa-solid <?= $esc($card['icon']) ?> text-sm"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-gray-600">
            <?= $esc($capa['turma'] ?? '') ?><?= !empty($capa['serie']) ? ' · ' . $esc($capa['serie']) : '' ?>
            <?= !empty($capa['ano_letivo']) ? ' · ' . (int) $capa['ano_letivo'] : '' ?>
            · <?= (int) ($capa['anos_trajetoria'] ?? 0) ?> ano(s) na trajetória
            <?php if (!empty($capa['historico_emitido'])): ?> · Histórico oficial emitido<?php endif; ?>
            · Educacenso: <?= $esc($capa['inep_txt'] ?? '') ?>
        </p>
        <div class="flex flex-wrap gap-2">
            <a href="<?= $esc($hrefPacote) ?>" download class="aluno-btn-outline text-sm">Baixar pacote de transferência</a>
            <a href="<?= $esc($hrefDossie) ?>" download class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Baixar dossiê (PDF)</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-2 text-sm">
        <?php foreach ($veAbas as $chave => $meta): ?>
            <button type="button"
                    class="ve-pill px-3 py-1.5 rounded-full <?= $veAba === $chave ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>"
                    data-ve-aba="<?= $esc($chave) ?>"
                    <?php if (!empty($meta['perm_key'])): ?>data-perm-key="<?= $esc($meta['perm_key']) ?>" data-perm-action="visualizar"<?php endif; ?>
                    onclick="selecionarVeAba('<?= $esc($chave) ?>')">
                <i class="fa-solid <?= $esc($meta['icon']) ?> mr-1 text-xs"></i><?= $esc($meta['label']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div id="ve-painel-boletim" class="ve-painel <?= $veAba === 'boletim' ? '' : 'hidden' ?>" data-ve-aba="boletim">
        <?php
        $partial = $modDir . '/_aba_boletim.php';
        if ($podeVidaEscolar && is_file($partial)) {
            include $partial;
        } elseif ($podeVidaEscolar) {
            echo '<p class="text-sm text-slate-500">Não foi possível carregar o boletim oficial.</p>';
        }
        ?>
        <div class="student-card mt-5" data-perm-key="tab_boletim" data-perm-action="visualizar">
            <div class="student-card-header">
                <h3 class="text-base font-semibold text-slate-900">Eventos de notas (origem)</h3>
                <p class="text-xs text-slate-500 mt-1">Composição de provas, jornadas e faltas. O documento oficial é o boletim acima.</p>
            </div>
            <div class="student-card-body">
                <?php include __DIR__ . '/_secao_boletim_eventos.php'; ?>
            </div>
        </div>
    </div>

    <div id="ve-painel-notas" class="ve-painel <?= $veAba === 'notas' ? '' : 'hidden' ?>" data-ve-aba="notas" data-perm-key="tab_notas" data-perm-action="visualizar">
        <div class="student-card">
            <div class="student-card-body">
                <?php include __DIR__ . '/_secao_notas_eventos.php'; ?>
            </div>
        </div>
    </div>

    <?php foreach (['trajetoria', 'documentos', 'conferencia', 'dossie'] as $veChave): ?>
    <div id="ve-painel-<?= $esc($veChave) ?>" class="ve-painel <?= $veAba === $veChave ? '' : 'hidden' ?>" data-ve-aba="<?= $esc($veChave) ?>">
        <?php
        $partial = $modDir . '/_aba_' . $veChave . '.php';
        if ($podeVidaEscolar && is_file($partial)) {
            include $partial;
        } elseif ($podeVidaEscolar) {
            echo '<div class="student-card"><div class="student-card-body"><p class="text-sm text-slate-500">Conteúdo indisponível.</p></div></div>';
        }
        ?>
    </div>
    <?php endforeach; ?>
</div>
<script>
function selecionarVeAba(nome) {
    if (typeof veFecharLancarEscola === 'function') veFecharLancarEscola();
    if (!nome) nome = 'boletim';
    document.querySelectorAll('.ve-painel').forEach(function (painel) {
        var ativa = painel.getAttribute('data-ve-aba') === nome;
        painel.classList.toggle('hidden', !ativa);
    });
    document.querySelectorAll('.ve-pill').forEach(function (btn) {
        var ativa = btn.getAttribute('data-ve-aba') === nome;
        btn.classList.toggle('bg-primary', ativa);
        btn.classList.toggle('text-white', ativa);
        btn.classList.toggle('bg-gray-100', !ativa);
        btn.classList.toggle('text-gray-700', !ativa);
    });
    try {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', 'vida-escolar');
        url.searchParams.set('ve_aba', nome);
        history.replaceState(null, '', url.pathname + url.search + url.hash);
    } catch (e) {}
}
</script>
