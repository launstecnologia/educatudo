<style>
.student-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
.student-card-header {
    padding: 1.15rem 1.35rem;
    border-bottom: 1px solid #e2e8f0;
}
.student-card-body { padding: 1.25rem 1.35rem; }
.student-field-label {
    display: block;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 0.35rem;
}
.student-field-value {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.4;
}
.student-info-columns { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
@media (min-width: 768px) {
    .student-info-columns { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0; }
    .student-info-col { padding: 0 1.5rem; border-right: 1px solid #e2e8f0; }
    .student-info-col:first-child { padding-left: 0; }
    .student-info-col:last-child { border-right: none; padding-right: 0; }
}
.student-info-col-title {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 1rem;
}
.student-info-fields { display: flex; flex-direction: column; gap: 1rem; }
.student-info-address { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; }
.student-info-address-grid { display: grid; grid-template-columns: 1fr; gap: 0.875rem; }
@media (min-width: 640px) {
    .student-info-address-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .student-info-address-grid .student-address-half { grid-column: span 1; }
}
@media (min-width: 1024px) {
    .student-info-address-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
.student-info-address-grid .student-address-full { grid-column: 1 / -1; }
.student-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1rem 1rem 1.125rem;
    border-left-width: 4px;
    min-width: 0;
}
.student-metrics-row { display: grid; grid-template-columns: 1fr; gap: 0.75rem; }
@media (min-width: 640px) {
    .student-metrics-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
.student-tabs-nav { display: flex; gap: 0; flex-wrap: wrap; width: 100%; }
.student-tabs-nav .tab-button { flex-shrink: 0; }
.student-tabs-nav-scroll { overflow: visible; }
.aluno-abas-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}
.aluno-abas-scroll::-webkit-scrollbar { height: 4px; }
.aluno-link {
    color: #2563eb;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.aluno-link:hover { color: #1d4ed8; }
.aluno-kpi {
    border: 1px solid #eef2f6;
    background: #f8fafc;
    border-radius: 12px;
    padding: 0.85rem 1rem;
    min-width: 0;
}
.aluno-btn-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.9rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    background: #fff;
    color: #374151;
    font-size: 0.8125rem;
    font-weight: 600;
}
.aluno-btn-outline:hover { background: #f9fafb; }
.aluno-ficha-shell {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
.aluno-ficha-sticky {
    position: sticky;
    top: 4.5rem;
    z-index: 24;
    background: #fff;
    border-top: 1px solid #e2e8f0;
}
.aluno-ficha-sticky.is-compact {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}
.aluno-card-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    background: #eff6ff;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.8125rem;
}
.aluno-aba-btn[aria-selected="true"] {
    color: #2563eb;
    border-bottom-color: #2563eb;
}
.aluno-timeline { position: relative; margin-left: 0.55rem; padding-left: 1.15rem; }
.aluno-timeline::before {
    content: '';
    position: absolute;
    left: 0.2rem;
    top: 0.35rem;
    bottom: 0.35rem;
    width: 2px;
    background: #e2e8f0;
}
.aluno-timeline-dot {
    position: absolute;
    left: -0.05rem;
    top: 0.4rem;
    width: 0.7rem;
    height: 0.7rem;
    border-radius: 999px;
    background: #2563eb;
    border: 2px solid #dbeafe;
}
@media (min-width: 768px) {
    .aluno-ficha-sticky { top: 5.25rem; }
}
</style>

<?php include __DIR__ . '/_show_boot.php'; ?>

<input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

<?php if (empty($student) || !is_array($student)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <h3 class="text-lg font-semibold text-red-900 mb-2">Aluno não encontrado</h3>
        <p class="text-red-700 mb-4">O aluno solicitado não foi encontrado no sistema.</p>
        <a href="<?= URL ?>/admin/students" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
            Voltar para Lista de Alunos
        </a>
    </div>
<?php else: ?>
<?php include __DIR__ . '/_show_vars.php'; ?>

<?php
$jaFezPrimeiroAcesso = $primeiroAcessoRealizado;
$fc = is_array($ficha_complementar ?? null) ? $ficha_complementar : [];
$fcVal = static function ($key) use ($fc) {
    $v = trim((string) ($fc[$key] ?? ''));
    return $v !== '' ? $v : null;
};

require_once __DIR__ . '/../../../Models/User/StudentDocument.php';
$docChecklist = \StudentDocument::checklist();
$docsAluno = is_array($documentos_aluno ?? null) ? $documentos_aluno : [];
$docsPorTipo = [];
$docsOutros = [];
foreach ($docsAluno as $docRow) {
    if (($docRow['tipo'] ?? '') === 'outros') {
        $docsOutros[] = $docRow;
    } else {
        $docsPorTipo[$docRow['tipo'] ?? ''] = $docRow;
    }
}
$docStatusBadge = static function ($status) {
    switch ((string) $status) {
        case 'entregue': return ['Entregue', 'bg-green-100 text-green-800'];
        case 'dispensado': return ['Dispensado', 'bg-slate-100 text-slate-600'];
        default: return ['Pendente', 'bg-amber-100 text-amber-800'];
    }
};
$docCanEdit = !empty($admin_permissions['documentos_aluno']['cadastrar']) || !empty($admin_permissions['documentos_aluno']['alterar']);
$docCanDelete = !empty($admin_permissions['documentos_aluno']['excluir']);
$totalEntregues = 0;
$docsPendentesLabels = [];
foreach ($docChecklist as $ck => $lbl) {
    if ($ck === 'outros') {
        continue;
    }
    $stDoc = (string) ($docsPorTipo[$ck]['status'] ?? 'pendente');
    if ($stDoc === 'entregue') {
        $totalEntregues++;
    } elseif ($stDoc !== 'dispensado') {
        $docsPendentesLabels[] = $lbl;
    }
}
$totalChecklist = count($docChecklist) - 1;
$linhasDocResumo = [];
foreach ($docChecklist as $ckTipo => $ckLabel) {
    if ($ckTipo === 'outros') {
        continue;
    }
    $linhasDocResumo[] = ['tipo' => $ckTipo, 'label' => $ckLabel, 'row' => $docsPorTipo[$ckTipo] ?? null];
    if (count($linhasDocResumo) >= 4) {
        break;
    }
}

$enderecoPartes = array_filter([
    $enderecoLogradouro,
    $enderecoNumero !== '' ? 'nº ' . $enderecoNumero : '',
    $enderecoBairro,
    trim($enderecoCidade . ($enderecoUf !== '' ? '/' . $enderecoUf : '')),
], static function ($v) { return trim((string) $v) !== ''; });
$enderecoResumo = implode(', ', $enderecoPartes);

$matriculaAtual = null;
if ($matriculas_schema_ready) {
    foreach ($matriculas as $matriculaRow) {
        if (($matriculaRow['status'] ?? '') === 'ativa') {
            $matriculaAtual = $matriculaRow;
            break;
        }
    }
}

$turmaNomeCurto = trim((string) ((is_array($matriculaAtual) ? ($matriculaAtual['turma_nome'] ?? '') : '') ?: ($student['turma_nome'] ?? $turmaDisplay)));
$anoLetivoFicha = (is_array($matriculaAtual) && !empty($matriculaAtual['ano_letivo_ano']))
    ? (string) (int) $matriculaAtual['ano_letivo_ano']
    : '';
$turmaAnoLabel = $anoLetivoFicha !== '' ? ($turmaNomeCurto . ' • ' . $anoLetivoFicha) : $turmaNomeCurto;
$serieDisplayFicha = trim((string) ($student['serie'] ?? $student['serie_nome'] ?? ''));
$metaPartesAluno = [];
$raAluno = trim((string) ($student['ra'] ?? ''));
$metaPartesAluno[] = 'RA ' . ($raAluno !== '' ? $raAluno : '—');
$turmaMeta = $matriculaEncerrada ? 'Encerrada' : ($matriculaPendente ? 'Pendente' : $turmaDisplay);
if ($serieDisplayFicha !== '' && stripos($turmaMeta, $serieDisplayFicha) === false) {
    $turmaMeta .= ' • ' . $serieDisplayFicha;
}
$metaPartesAluno[] = $turmaMeta;
if (!empty($student['numero_chamada'])) {
    $metaPartesAluno[] = 'Nº ' . (int) $student['numero_chamada'];
}
$metaLinhaAluno = implode(' | ', $metaPartesAluno);
$statusMatriculaVisual = $matriculaEstado === 'vinculada' ? 'Ativa' : $statusMatriculaLabel;

$pendenciasAluno = [];
foreach ($docsPendentesLabels as $docPend) {
    $pendenciasAluno[] = $docPend;
}
if ($matriculaPendente) {
    $pendenciasAluno[] = 'Matrícula pendente';
}
if (!$primeiroAcessoRealizado) {
    $pendenciasAluno[] = 'Primeiro acesso ainda não realizado';
}
$pendenciasCount = count($pendenciasAluno);
$pendenciasAluno = array_slice($pendenciasAluno, 0, 3);

$atividadeLabelsAudit = [
    'CREATE_STUDENT' => ['Cadastro', 'Cadastro do aluno'],
    'UPDATE_STUDENT' => ['Cadastro', 'Edição do cadastro'],
    'DELETE_STUDENT' => ['Cadastro', 'Exclusão do aluno'],
    'LINK_GUARDIAN' => ['Responsável', 'Responsável vinculado'],
    'UPDATE_GUARDIAN' => ['Responsável', 'Responsável atualizado'],
    'SAVE_STUDENT_DOCUMENT' => ['Documento', 'Documento salvo'],
    'DELETE_STUDENT_DOCUMENT' => ['Documento', 'Documento removido'],
    'DOWNLOAD_STUDENT_DOCUMENT' => ['Documento', 'Download de documento'],
    'GENERATE_DECLARATION' => ['Declaração', 'Declaração emitida'],
    'VIEW_ADMIN' => ['Sistema', 'Visualização do perfil'],
];
$atividadeRecente = [];
if ($matriculaAtual) {
    $quandoRaw = (string) ($matriculaAtual['updated_at'] ?? $matriculaAtual['created_at'] ?? $matriculaAtual['data_entrada'] ?? '');
    $descMat = $anoLetivoFicha !== ''
        ? ('Matrícula confirmada para o ano letivo de ' . $anoLetivoFicha)
        : 'Matrícula atualizada';
    $atividadeRecente[] = [
        'quando' => $quandoRaw !== '' ? date('d/m/Y H:i', strtotime($quandoRaw)) : '',
        'tipo' => 'Matrícula',
        'descricao' => $descMat,
        'usuario' => '',
        '_ts' => $quandoRaw !== '' ? strtotime($quandoRaw) : 1,
    ];
}
foreach (array_slice($audit_logs, 0, 5) as $logAtv) {
    $codeAtv = (string) ($logAtv['action'] ?? '');
    $metaAtv = $atividadeLabelsAudit[$codeAtv] ?? ['Sistema', $codeAtv];
    $quandoRaw = (string) ($logAtv['created_at'] ?? '');
    $papelAtv = trim((string) ($logAtv['user_role'] ?? ''));
    $atividadeRecente[] = [
        'quando' => $quandoRaw !== '' ? date('d/m/Y H:i', strtotime($quandoRaw)) : '',
        'tipo' => $metaAtv[0],
        'descricao' => $metaAtv[1],
        'usuario' => $papelAtv !== '' ? ucfirst($papelAtv) : '',
        '_ts' => $quandoRaw !== '' ? strtotime($quandoRaw) : 0,
    ];
}
foreach (array_slice($historico_acesso, 0, 3) as $ha) {
    $quandoRaw = (string) ($ha['created_at'] ?? '');
    $atividadeRecente[] = [
        'quando' => $quandoRaw !== '' ? date('d/m/Y H:i', strtotime($quandoRaw)) : '',
        'tipo' => 'Acesso',
        'descricao' => 'Login no portal do aluno',
        'usuario' => 'Aluno',
        '_ts' => $quandoRaw !== '' ? strtotime($quandoRaw) : 0,
    ];
}
foreach (array_slice($ocorrencias, 0, 3) as $oc) {
    $quandoRaw = (string) ($oc['data_ocorrencia'] ?? $oc['created_at'] ?? '');
    $atividadeRecente[] = [
        'quando' => $quandoRaw !== '' ? date('d/m/Y H:i', strtotime($quandoRaw)) : '',
        'tipo' => 'Ocorrência',
        'descricao' => (string) ($oc['titulo'] ?? 'Ocorrência registrada'),
        'usuario' => (string) ($oc['criado_por_nome'] ?? ''),
        '_ts' => $quandoRaw !== '' ? strtotime($quandoRaw) : 0,
    ];
}
usort($atividadeRecente, static function ($a, $b) {
    return ($b['_ts'] ?? 0) <=> ($a['_ts'] ?? 0);
});
$atividadeRecente = array_slice($atividadeRecente, 0, 5);

$moduloVidaEscolar = !class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('vida_escolar');
$abasAluno = [
    'visao-geral' => ['label' => 'Visão geral', 'count' => null],
    'dados-pessoais' => ['label' => 'Dados pessoais', 'count' => null],
    'responsaveis' => ['label' => 'Responsáveis', 'count' => $responsaveisCount, 'perm_key' => 'responsaveis_vinculados'],
    'matriculas' => ['label' => 'Matrículas', 'count' => null, 'perm_key' => 'matriculas_aluno'],
    'saude' => ['label' => 'Saúde', 'count' => null],
    'documentos' => ['label' => 'Documentos', 'count' => $totalChecklist, 'perm_key' => 'documentos_aluno'],
];
if ($moduloVidaEscolar) {
    $abasAluno['vida-escolar'] = ['label' => 'Vida escolar', 'count' => null];
}
$abasAluno['pedagogico'] = ['label' => 'Pedagógico', 'count' => null];
$abasAluno['historico'] = ['label' => 'Histórico', 'count' => null];
?>

<?php if ($flash_message !== ''): ?>
<div class="mb-4 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
    <?= safe_htmlspecialchars($flash_message) ?>
</div>
<?php endif; ?>

<div class="mb-5 flex items-center justify-between gap-3">
    <h2 class="text-2xl font-bold text-gray-900">Detalhes do aluno</h2>
    <button type="button"
            onclick="voltarDetalheAluno()"
            class="aluno-btn-outline shrink-0">
        <i class="fa-solid fa-arrow-left mr-2"></i>
        Voltar
    </button>
</div>
<script>
function voltarDetalheAluno() {
    var fallback = <?= json_encode(URL . '/admin/students', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var referrer = document.referrer || '';
    if (referrer && window.history.length > 1) {
        try {
            if (new URL(referrer, window.location.href).origin === window.location.origin) {
                window.history.back();
                return;
            }
        } catch (error) {}
    }
    window.location.href = fallback;
}
</script>

<div class="aluno-ficha-shell mb-5">
    <?php
    $alunoCardModo = 'full';
    include __DIR__ . '/_card_identificacao_aluno.php';
    ?>
    <div id="aluno-ficha-sticky" class="aluno-ficha-sticky">
        <?php
        $alunoCardModo = 'compact';
        include __DIR__ . '/_card_identificacao_aluno.php';
        ?>
        <div class="aluno-abas-scroll">
            <nav class="flex min-w-max px-2" role="tablist" aria-label="Seções do aluno">
                <?php foreach ($abasAluno as $abaId => $abaMeta): ?>
                <button type="button"
                        id="aba-btn-<?= safe_htmlspecialchars($abaId) ?>"
                        class="aluno-aba-btn px-4 py-3 text-sm font-medium text-slate-500 border-b-2 border-transparent whitespace-nowrap hover:text-slate-800"
                        role="tab"
                        aria-selected="<?= $abaId === 'visao-geral' ? 'true' : 'false' ?>"
                        aria-controls="aba-painel-<?= safe_htmlspecialchars($abaId) ?>"
                        data-aba="<?= safe_htmlspecialchars($abaId) ?>"
                        <?php if (!empty($abaMeta['perm_key'])): ?>data-perm-key="<?= safe_htmlspecialchars($abaMeta['perm_key']) ?>" data-perm-action="visualizar"<?php endif; ?>
                        onclick="selecionarAbaAluno('<?= safe_htmlspecialchars($abaId) ?>')">
                    <?= safe_htmlspecialchars($abaMeta['label']) ?>
                    <?php if ($abaMeta['count'] !== null): ?>
                        <span class="ml-1.5 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold"><?= (int) $abaMeta['count'] ?></span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</div>

<div id="aba-painel-visao-geral" class="aluno-aba-painel" role="tabpanel" aria-labelledby="aba-btn-visao-geral">
    <?php include __DIR__ . '/_tab_visao_geral.php'; ?>
</div>
<div id="aba-painel-dados-pessoais" class="aluno-aba-painel hidden" role="tabpanel" aria-labelledby="aba-btn-dados-pessoais" hidden>
    <?php include __DIR__ . '/_secao_dados_pessoais.php'; ?>
</div>
<div id="aba-painel-responsaveis" class="aluno-aba-painel hidden" role="tabpanel" aria-labelledby="aba-btn-responsaveis" hidden>
    <?php include __DIR__ . '/_secao_responsaveis.php'; ?>
</div>
<div id="aba-painel-matriculas" class="aluno-aba-painel hidden" role="tabpanel" aria-labelledby="aba-btn-matriculas" hidden>
    <?php include __DIR__ . '/_secao_matriculas.php'; ?>
</div>
<div id="aba-painel-saude" class="aluno-aba-painel hidden" role="tabpanel" aria-labelledby="aba-btn-saude" hidden>
    <?php include __DIR__ . '/_secao_saude.php'; ?>
</div>
<div id="aba-painel-documentos" class="aluno-aba-painel hidden" role="tabpanel" aria-labelledby="aba-btn-documentos" hidden>
    <?php include __DIR__ . '/_secao_documentos.php'; ?>
</div>
<?php if ($moduloVidaEscolar): ?>
<div id="aba-painel-vida-escolar" class="aluno-aba-painel hidden" role="tabpanel" aria-labelledby="aba-btn-vida-escolar" hidden>
    <?php include __DIR__ . '/_tab_vida_escolar.php'; ?>
</div>
<?php endif; ?>
<div id="aba-painel-pedagogico" class="aluno-aba-painel hidden" role="tabpanel" aria-labelledby="aba-btn-pedagogico" hidden>
    <?php include __DIR__ . '/_relatorio_detalhado.php'; ?>
</div>
<div id="aba-painel-historico" class="aluno-aba-painel hidden" role="tabpanel" aria-labelledby="aba-btn-historico" hidden>
    <?php include __DIR__ . '/_secao_auditoria.php'; ?>
    <div class="student-card mt-5">
        <div class="student-card-header">
            <h3 class="text-base font-semibold text-slate-900">Atividade recente</h3>
        </div>
        <div class="student-card-body">
            <?php if (empty($atividadeRecente)): ?>
                <p class="text-sm text-slate-500">Nenhuma atividade recente registrada.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($atividadeRecente as $ev): ?>
                    <li class="py-3 first:pt-0">
                        <p class="text-sm font-medium text-slate-800"><?= safe_htmlspecialchars($ev['descricao'] ?? '') ?></p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            <?= safe_htmlspecialchars($ev['quando'] ?? '') ?>
                            · <?= safe_htmlspecialchars($ev['tipo'] ?? '') ?>
                            <?php if (!empty($ev['usuario'])): ?> · <?= safe_htmlspecialchars($ev['usuario']) ?><?php endif; ?>
                        </p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_offcanvas_acoes_aluno.php'; ?>
<?php include __DIR__ . '/_drawers_aluno.php'; ?>
<?php
if (!empty($moduloVidaEscolar) && !empty($modDir)) {
    $offcanvasLancarEscola = $modDir . '/_offcanvas_lancar_escola.php';
    if (is_file($offcanvasLancarEscola)) {
        include $offcanvasLancarEscola;
    }
}
?>
<?php if ($matriculas_schema_ready): ?>
<?php include __DIR__ . '/_modal_matricula_aluno.php'; ?>
<?php endif; ?>

<script>
var ABA_ALUNO_HASH = {
    'section-matriculas-aluno': 'matriculas',
    'section-documentos-aluno': 'documentos',
    'section-responsaveis-vinculados': 'responsaveis',
    'section-auditoria-aluno': 'historico',
    'section-relatorio-detalhado': 'pedagogico'
};

function abrirAbaAlunoComSub(abaId, subTab) {
    selecionarAbaAluno(abaId);
    if (subTab && typeof showTab === 'function') {
        setTimeout(function () { showTab(subTab); }, 60);
    }
}

function selecionarAbaAluno(abaId, atualizarUrl) {
    if (typeof veFecharLancarEscola === 'function') veFecharLancarEscola();
    if (!abaId) abaId = 'visao-geral';
    var paineis = document.querySelectorAll('.aluno-aba-painel');
    var botoes = document.querySelectorAll('.aluno-aba-btn');
    var encontrada = false;
    paineis.forEach(function (painel) {
        var ativa = painel.id === 'aba-painel-' + abaId;
        painel.classList.toggle('hidden', !ativa);
        if (ativa) {
            painel.removeAttribute('hidden');
            encontrada = true;
        } else {
            painel.setAttribute('hidden', 'hidden');
        }
    });
    if (!encontrada) {
        selecionarAbaAluno('visao-geral', atualizarUrl);
        return;
    }
    botoes.forEach(function (btn) {
        var ativa = btn.getAttribute('data-aba') === abaId;
        btn.setAttribute('aria-selected', ativa ? 'true' : 'false');
        btn.classList.toggle('text-blue-600', ativa);
        btn.classList.toggle('border-blue-600', ativa);
        btn.classList.toggle('text-slate-500', !ativa);
        btn.classList.toggle('border-transparent', !ativa);
    });
    if (atualizarUrl !== false) {
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', abaId);
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        } catch (e) {}
    }
}

function initAbasAluno() {
    document.querySelectorAll('#offcanvasAcoesAluno [data-perm-key].hidden').forEach(function (el) {
        el.setAttribute('data-perm-hidden', '1');
    });
    var modalNotas = document.getElementById('modal-notas-evento');
    if (modalNotas && modalNotas.parentElement !== document.body) {
        document.body.appendChild(modalNotas);
    }
    var tab = 'visao-geral';
    try {
        var params = new URLSearchParams(window.location.search);
        if (params.get('tab')) tab = params.get('tab');
        var hash = (window.location.hash || '').replace('#', '');
        if (hash && ABA_ALUNO_HASH[hash]) tab = ABA_ALUNO_HASH[hash];
    } catch (e) {}
    var btn = document.getElementById('aba-btn-' + tab);
    if (btn && btn.classList.contains('hidden')) {
        tab = 'visao-geral';
    }
    selecionarAbaAluno(tab, false);
    try {
        var veAba = new URLSearchParams(window.location.search).get('ve_aba') || new URLSearchParams(window.location.search).get('aba');
        if (tab === 'vida-escolar' && typeof selecionarVeAba === 'function') {
            selecionarVeAba(veAba || 'boletim');
        }
    } catch (e) {}
    initCabecalhoCompactoAluno();
}

function initCabecalhoCompactoAluno() {
    var full = document.getElementById('aluno-id-card-full');
    var compact = document.getElementById('aluno-id-card-compact');
    var sticky = document.getElementById('aluno-ficha-sticky');
    if (!full || !compact || !sticky || !('IntersectionObserver' in window)) return;
    var observer = new IntersectionObserver(function (entries) {
        var visivel = entries[0] && entries[0].isIntersecting;
        compact.classList.toggle('hidden', visivel);
        sticky.classList.toggle('is-compact', !visivel);
    }, { threshold: 0, rootMargin: '-88px 0px 0px 0px' });
    observer.observe(full);
}
</script>

<?php endif; ?>

<?php include __DIR__ . '/_show_modais_scripts.php'; ?>
