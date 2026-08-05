<style>
.student-page { display: flex; flex-direction: column; gap: 1.5rem; }
.student-top-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1.5rem;
}
@media (min-width: 1024px) {
    .student-top-grid {
        grid-template-columns: minmax(0, 3fr) minmax(240px, 1fr);
        align-items: stretch;
    }
}
.student-top-grid > .student-card,
.student-top-aside > .student-card {
    height: 100%;
    display: flex;
    flex-direction: column;
}
.student-top-grid > .student-card > .student-card-body,
.student-top-aside > .student-card > .student-card-body {
    flex: 1 1 auto;
}
.student-top-aside {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.student-duo-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
@media (min-width: 1024px) {
    .student-duo-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
.student-duo-grid .student-card-body {
    padding: 1.25rem 1.5rem 1.5rem;
}
.student-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
.student-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}
.student-card-body {
    padding: 1.5rem;
}
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

.student-info-columns {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
@media (min-width: 768px) {
    .student-info-columns {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0;
    }
    .student-info-col {
        padding: 0 1.5rem;
        border-right: 1px solid #e2e8f0;
    }
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
.student-info-address {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
}
.student-info-address-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.875rem;
}
@media (min-width: 640px) {
    .student-info-address-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1024px) {
    .student-info-address-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.student-info-address-grid .student-address-full {
    grid-column: 1 / -1;
}
@media (min-width: 640px) {
    .student-info-address-grid .student-address-half {
        grid-column: span 1;
    }
}
.student-quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}
.student-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1rem 1rem 1.125rem;
    border-left-width: 4px;
    min-width: 0;
}
.student-metrics-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .student-metrics-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
.student-quick-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 0.4375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #fff;
    transition: filter 0.15s;
    line-height: 1.25;
}
.student-quick-btn i {
    font-size: 0.75rem;
}
.student-quick-btn:hover { filter: brightness(1.08); }
.student-tabs-nav {
    display: flex;
    gap: 0;
    flex-wrap: wrap;
    width: 100%;
}
.student-tabs-nav .tab-button {
    flex-shrink: 0;
}
.student-tabs-nav-scroll {
    overflow: visible;
}
.student-hero-meta span + span::before {
    content: '|';
    margin: 0 0.75rem;
    opacity: 0.45;
    font-weight: 400;
}
</style>

<?php
// Verificar se as variáveis necessárias existem e garantir que são arrays
$student = $student ?? [];
$stats = $stats ?? [];
$conversas = is_array($conversas ?? null) ? $conversas : [];
$conversas_detalhadas = is_array($conversas_detalhadas ?? null) ? $conversas_detalhadas : [];
$exercicios_bd = is_array($exercicios_bd ?? null) ? $exercicios_bd : [];
$exercicios_ia = is_array($exercicios_ia ?? null) ? $exercicios_ia : [];
$redacoes = is_array($redacoes ?? null) ? $redacoes : [];
$ocorrencias = is_array($ocorrencias ?? null) ? $ocorrencias : [];
$historico_turmas = is_array($historico_turmas ?? null) ? $historico_turmas : [];
$jornadas_feitas = is_array($jornadas_feitas ?? null) ? $jornadas_feitas : [];
$provas_realizadas = is_array($provas_realizadas ?? null) ? $provas_realizadas : [];
$provas_matriz_blocos = is_array($provas_matriz_blocos ?? null) ? $provas_matriz_blocos : [];
$historico_acesso = is_array($historico_acesso ?? null) ? $historico_acesso : [];
$boletim_eventos_notas = is_array($boletim_eventos_notas ?? null) ? $boletim_eventos_notas : [];
$boletim_eventos_boletim = is_array($boletim_eventos_boletim ?? null) ? $boletim_eventos_boletim : [];
$boletins_gerados = is_array($boletins_gerados ?? null) ? $boletins_gerados : [];
$boletins_gerados_notas_por_regra = is_array($boletins_gerados_notas_por_regra ?? null) ? $boletins_gerados_notas_por_regra : [];
$matriculas = is_array($matriculas ?? null) ? $matriculas : [];
$matriculas_schema_ready = (bool)($matriculas_schema_ready ?? false);
$matricula_divergente_cadastro = (bool)($matricula_divergente_cadastro ?? false);
$matriculas_paralelas = is_array($matriculas_paralelas ?? null) ? $matriculas_paralelas : [];
$turmas_para_matricula = is_array($turmas_para_matricula ?? null) ? $turmas_para_matricula : [];
$anos_letivo_para_matricula = is_array($anos_letivo_para_matricula ?? null) ? $anos_letivo_para_matricula : [];
$responsaveis_aluno = is_array($responsaveis_aluno ?? null) ? $responsaveis_aluno : [];
$admin_permissions = is_array($admin_permissions ?? null) ? $admin_permissions : [];
$csrf_token = $csrf_token ?? '';
$flash_message = (string)($flash_message ?? '');
$flash_type = (string)($flash_type ?? '');
$logoHorizontalPrintUrl = '';
if (class_exists('LayoutHelper')) {
    $logoHorizontalPrintUrl = (string) (LayoutHelper::getLogoHorizontalUrl() ?: LayoutHelper::getLogoUrl() ?: '');
}

// Garantir que $student é um array
if (!is_array($student)) {
    $student = [];
}

// Função helper para converter valores para string antes de htmlspecialchars
function safe_htmlspecialchars($value, $default = '') {
    if (is_array($value)) {
        return htmlspecialchars($default);
    }
    if ($value === null) {
        return htmlspecialchars($default);
    }
    return htmlspecialchars((string)$value);
}

function format_data_br($value, $default = '—') {
    if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return htmlspecialchars($default);
    }
    if (is_array($value)) {
        return htmlspecialchars($default);
    }
    $raw = trim((string) $value);
    if ($raw === '') {
        return htmlspecialchars($default);
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return htmlspecialchars($raw);
    }
    return htmlspecialchars(date('d/m/Y', $ts));
}

function student_campo_endereco(array $student, array $keys): string {
    foreach ($keys as $key) {
        $val = trim((string) ($student[$key] ?? ''));
        if ($val !== '') {
            return $val;
        }
    }
    return '';
}

function format_cep_exibicao(?string $cep): string {
    $digits = preg_replace('/\D+/', '', (string) $cep);
    if (strlen($digits) === 8) {
        return substr($digits, 0, 5) . '-' . substr($digits, 5);
    }
    return trim((string) $cep);
}

function can_admin_perm(array $permissions, string $key, string $action = 'visualizar'): bool {
    return !empty($permissions[$key][$action]);
}

function responsavel_iniciais(string $nome): string {
    $nome = trim($nome);
    if ($nome === '') {
        return '?';
    }
    $parts = preg_split('/\s+/u', $nome, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1), 'UTF-8');
    }

    return mb_strtoupper(mb_substr($nome, 0, 2), 'UTF-8');
}
?>

<input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

<?php if (empty($student) || !is_array($student)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-red-900 mb-2">Aluno não encontrado</h3>
        <p class="text-red-700 mb-4">O aluno solicitado não foi encontrado no sistema.</p>
        <a href="<?= URL ?>/admin/students" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar para Lista de Alunos
        </a>
    </div>
<?php else: ?>
<?php
$responsaveisCount = count($responsaveis_aluno);
$statusAlunoAtivo = (int)($student['ativo'] ?? 0) === 1;
$primeiroAcessoRealizado = (int)($student['primeiro_acesso'] ?? 1) === 0;
$sexoLabels = ['M' => 'Masculino', 'F' => 'Feminino', 'N' => 'Neutro / outro'];
$sexoLabel = $sexoLabels[$student['sexo'] ?? ''] ?? 'Não informado';
$alunoPagante = (int)($student['pagante'] ?? 1) === 1;
$turmaDisplay = $student['turma_display'] ?? $student['turma_nome'] ?? 'Sem turma';
$ehMatriculaPostRel = '';
$ehMatriculaSyncRel = '';
if ($matriculas_schema_ready) {
    $ehMatriculaPathBase = '';
    if (defined('URL') && URL !== '') {
        $ehPu = parse_url((string) URL);
        $ehMatriculaPathBase = isset($ehPu['path']) ? rtrim((string) $ehPu['path'], '/') : '';
    }
    $ehMatriculaPostRel = $ehMatriculaPathBase . '/admin/students/' . (int) ($student['id'] ?? 0) . '/matricula';
    $ehMatriculaSyncRel = $ehMatriculaPathBase . '/admin/students/' . (int) ($student['id'] ?? 0) . '/matricula-sincronizar-cadastro';
}
$enderecoLogradouro = student_campo_endereco($student, ['logradouro', 'endereco', 'endereco_logradouro']);
$enderecoNumero = student_campo_endereco($student, ['numero', 'endereco_numero', 'num_endereco', 'numero_endereco']);
$enderecoComplemento = student_campo_endereco($student, ['complemento', 'endereco_complemento']);
$enderecoBairro = student_campo_endereco($student, ['bairro', 'endereco_bairro']);
$enderecoCidade = student_campo_endereco($student, ['cidade', 'endereco_cidade']);
$enderecoUf = student_campo_endereco($student, ['uf', 'estado', 'endereco_uf']);
$enderecoCepRaw = student_campo_endereco($student, ['cep', 'endereco_cep']);
$enderecoCep = $enderecoCepRaw !== '' ? format_cep_exibicao($enderecoCepRaw) : '';
require_once __DIR__ . '/../../../Helpers/StudentFormHelper.php';
$cpfDisplay = StudentFormHelper::formatCpfDisplay($student['cpf'] ?? '');
$rgDisplay = StudentFormHelper::formatRgDisplay($student['rg'] ?? '');
$telefoneDisplay = StudentFormHelper::formatTelefoneDisplay($student['telefone'] ?? '');
$celularDisplay = StudentFormHelper::formatTelefoneDisplay($student['celular'] ?? '');
$dataNascInput = StudentFormHelper::formatDataNascInput($student['data_nasc'] ?? null);
$dataNascDisplay = '';
if ($dataNascInput !== '') {
    $dataNascDisplay = implode('/', array_reverse(explode('-', $dataNascInput)));
}
$matriculaAtiva = false;
$teveMatriculaHistorico = false;
if ($matriculas_schema_ready) {
    foreach ($matriculas as $matriculaRow) {
        $teveMatriculaHistorico = true;
        if (($matriculaRow['status'] ?? '') === 'ativa') {
            $matriculaAtiva = true;
        }
    }
} elseif (!empty($student['turma_id'])) {
    $matriculaAtiva = true;
}
if ($matriculaAtiva) {
    $matriculaEstado = 'vinculada';
    $statusMatriculaLabel = 'Vinculada';
} elseif ($teveMatriculaHistorico) {
    $matriculaEstado = 'encerrada';
    $statusMatriculaLabel = 'Encerrada';
} else {
    $matriculaEstado = 'pendente';
    $statusMatriculaLabel = 'Pendente';
}
$matriculaPendente = ($matriculaEstado === 'pendente');
$matriculaEncerrada = ($matriculaEstado === 'encerrada');
if ($matriculaEncerrada) {
    $statusLoginLabel = 'Encerrado';
    $statusLoginClass = 'bg-slate-100 text-slate-700';
    $statusAlunoAtivo = false;
} elseif ($statusAlunoAtivo) {
    $statusLoginLabel = 'Ativo';
    $statusLoginClass = 'bg-green-100 text-green-800';
} else {
    $statusLoginLabel = 'Inativo';
    $statusLoginClass = 'bg-red-100 text-red-800';
}
$matriculaBadgeClass = match ($matriculaEstado) {
    'vinculada' => 'bg-green-100 text-green-800',
    'encerrada' => 'bg-slate-100 text-slate-700',
    default => 'bg-amber-100 text-amber-800',
};
?>
<div class="mb-4">
    <button type="button"
            onclick="voltarDetalheAluno()"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 hover:text-gray-900 transition-colors">
        <i class="fa-solid fa-arrow-left"></i>
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
        } catch (error) {
            // Usa o fallback seguro abaixo.
        }
    }
    window.location.href = fallback;
}
</script>
<div class="rounded-2xl bg-gradient-to-br from-[#0f172a] via-[#1e3a8a] to-[#2563eb] p-6 md:p-8 text-white shadow-xl mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 flex-1 min-w-0">
            <?php
            $mode = 'hero';
            $size = 'xl';
            include __DIR__ . '/_student_photo.php';
            ?>
            <div class="min-w-0 flex-1">
            <h3 class="text-xl sm:text-2xl lg:text-[1.75rem] font-bold tracking-tight leading-tight mb-3"><?= safe_htmlspecialchars($student['nome'] ?? '', 'Aluno') ?></h3>
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $matriculaEncerrada ? 'bg-slate-500/25 text-slate-100' : ($statusAlunoAtivo ? 'bg-emerald-500/25 text-emerald-100' : 'bg-red-500/25 text-red-100') ?>">
                    <?= safe_htmlspecialchars($matriculaEncerrada ? 'Encerrado' : ($statusAlunoAtivo ? 'Ativo' : 'Inativo')) ?>
                </span>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-500/30 text-blue-100">
                    <?= $primeiroAcessoRealizado ? '1º acesso realizado' : '1º acesso pendente' ?>
                </span>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $alunoPagante ? 'bg-violet-500/30 text-violet-100' : 'bg-white/10 text-slate-200' ?>">
                    <?= $alunoPagante ? 'Pagante' : 'Não pagante' ?>
                </span>
                <?php if ($matriculaPendente): ?>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/30 text-amber-100">
                    Matrícula pendente
                </span>
                <?php endif; ?>
            </div>
            <div class="student-hero-meta flex flex-wrap items-center text-sm text-white/90">
                <span>RA: <strong class="font-semibold"><?= safe_htmlspecialchars($student['ra'] ?? '-', '-') ?></strong></span>
                <span>Turma: <strong class="font-semibold"><?= $matriculaEncerrada ? 'Encerrada' : ($matriculaPendente ? 'Pendente' : safe_htmlspecialchars($turmaDisplay)) ?></strong></span>
                <?php if (!empty($student['numero_chamada'])): ?>
                <span>Nº chamada: <strong class="font-semibold"><?= (int) $student['numero_chamada'] ?></strong></span>
                <?php endif; ?>
                <span>Responsáveis: <strong class="font-semibold"><?= (int)$responsaveisCount ?></strong></span>
            </div>
            </div>
        </div>
        <div class="flex flex-row lg:flex-col gap-2 flex-shrink-0">
            <a href="<?= URL ?>/admin/students/<?= $student['id'] ?? '' ?>/edit" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-white text-slate-900 text-sm font-semibold hover:bg-slate-50 transition-colors shadow-sm">
                <i class="fa-solid fa-pen-to-square mr-2 text-blue-600"></i>
                Editar aluno
            </a>
            <?php if ($student['ativo'] ?? 0): ?>
            <button onclick="abrirModalInativarAluno()" data-perm-key="acao_rapida_ativar_desativar" data-perm-action="alterar" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-white/10 border border-white/25 text-white text-sm font-semibold hover:bg-white/15 transition-colors">
                <i class="fa-solid fa-power-off mr-2"></i>
                Inativar / TR
            </button>
            <?php else: ?>
            <button onclick="abrirModalAtivarAluno()" data-perm-key="acao_rapida_ativar_desativar" data-perm-action="alterar" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl border border-white/40 text-white text-sm font-semibold hover:bg-white/10 transition-colors">
                <i class="fa-solid fa-user-check mr-2"></i>
                Ativar
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Student Details -->
<?php $jaFezPrimeiroAcesso = (int)($student['primeiro_acesso'] ?? 1) === 0; ?>
<div class="student-page">
    <div class="student-top-grid">
        <div class="student-card min-w-0">
            <div class="student-card-header flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-user text-blue-600"></i>
                </div>
                <h3 class="text-base font-semibold text-slate-900">Informações Pessoais</h3>
            </div>
            <div class="student-card-body">
                <div class="student-info-columns">
                    <div class="student-info-col">
                        <div class="student-info-col-title">Identificação</div>
                        <div class="student-info-fields">
                            <div>
                                <span class="student-field-label">Nome Completo</span>
                                <p class="student-field-value"><?= safe_htmlspecialchars($student['nome'] ?? '', '') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">RA / Código</span>
                                <p class="student-field-value"><?= safe_htmlspecialchars($student['ra'] ?? '', '') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Nickname</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nickname'] ?? null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">CPF</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($cpfDisplay ?: null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">RG</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($rgDisplay ?: null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Sexo</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($sexoLabel) ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Data de nascimento</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($dataNascDisplay ?: null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Telefone</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($telefoneDisplay ?: null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Celular</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($celularDisplay ?: null, 'Não informado') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="student-info-col">
                        <div class="student-info-col-title">Acesso ao Sistema</div>
                        <div class="student-info-fields">
                            <div>
                                <span class="student-field-label">Login (nickname)</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nickname'] ?? null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Email</span>
                                <p class="student-field-value font-normal break-all"><?= safe_htmlspecialchars($student['email'] ?? null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Primeiro acesso</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $jaFezPrimeiroAcesso ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                                    <?= $jaFezPrimeiroAcesso ? 'Já realizado' : 'Pendente' ?>
                                </span>
                            </div>
                            <div>
                                <span class="student-field-label">Status</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $statusLoginClass ?>">
                                    <?= safe_htmlspecialchars($statusLoginLabel) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="student-info-col">
                        <div class="student-info-col-title">Vínculo Escolar</div>
                        <div class="student-info-fields">
                            <div>
                                <span class="student-field-label">Turma</span>
                                <p class="student-field-value font-normal"><?= $matriculaEncerrada ? 'Encerrada' : ($matriculaPendente ? 'Pendente' : safe_htmlspecialchars($turmaDisplay)) ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Matrícula</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $matriculaBadgeClass ?>">
                                    <?= safe_htmlspecialchars($statusMatriculaLabel) ?>
                                </span>
                            </div>
                            <?php if (!empty($student['serie'])): ?>
                            <div>
                                <span class="student-field-label">Série</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['serie']) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($student['numero_chamada'])): ?>
                            <div>
                                <span class="student-field-label">Nº na lista de chamada</span>
                                <p class="student-field-value font-normal"><?= (int) $student['numero_chamada'] ?></p>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span class="student-field-label">Responsável principal</span>
                                <p class="student-field-value font-normal leading-snug"><?= safe_htmlspecialchars($student['responsavel_nome'] ?? null, 'Sem responsável') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="student-info-address">
                    <div class="student-info-col-title">Endereço</div>
                    <div class="student-info-address-grid">
                        <div class="student-address-full">
                            <span class="student-field-label">Logradouro</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoLogradouro ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Número</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoNumero ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Complemento</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoComplemento ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Bairro</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoBairro ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Cidade</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoCidade ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">UF</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoUf ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">CEP</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoCep ?: null, 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
                <?php
                $certidaoPartes = array_filter([
                    !empty($student['certidao_livro'] ?? '') ? 'Livro ' . $student['certidao_livro'] : '',
                    !empty($student['certidao_folha'] ?? '') ? 'Folha ' . $student['certidao_folha'] : '',
                    !empty($student['certidao_termo'] ?? '') ? 'Termo ' . $student['certidao_termo'] : '',
                ]);
                $certidaoResumo = trim((string) ($student['certidao_nascimento'] ?? ''));
                if (!empty($certidaoPartes)) {
                    $certidaoResumo = trim($certidaoResumo . ' (' . implode(', ', $certidaoPartes) . ')');
                }
                ?>
                <div class="student-info-address">
                    <div class="student-info-col-title">Documentação civil</div>
                    <div class="student-info-address-grid">
                        <div>
                            <span class="student-field-label">Nome da mãe</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nome_mae'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Nome do pai</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nome_pai'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Código INEP (Censo)</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['codigo_inep'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Nome social</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nome_social'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Nacionalidade</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nacionalidade'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Naturalidade</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['naturalidade'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">UF nascimento</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['uf_nascimento'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Cor / Raça</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['cor_raca'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Órgão emissor RG</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars(trim(($student['orgao_emissor'] ?? '') . ' ' . ($student['uf_rg'] ?? '')) ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">NIS</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nis'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Zona</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars(ucfirst((string) ($student['zona'] ?? '')) ?: null, 'Não informada') ?></p>
                        </div>
                        <div class="student-address-full">
                            <span class="student-field-label">Certidão de nascimento</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($certidaoResumo ?: null, 'Não informada') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">WhatsApp</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars(\StudentFormHelper::formatTelefoneDisplay($student['whatsapp'] ?? '') ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">E-mail secundário</span>
                            <p class="student-field-value font-normal break-all"><?= safe_htmlspecialchars($student['email_secundario'] ?? null, 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <aside class="student-top-aside space-y-6 lg:sticky lg:top-24 min-w-0">
            <div class="student-card">
                <div class="student-card-header flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-bolt text-amber-500"></i>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900">Ações Rápidas</h3>
                </div>
                <div class="student-card-body student-quick-actions">
                    <a href="<?= URL ?>/admin/students/<?= $student['id'] ?? '' ?>/edit" data-perm-key="acao_rapida_editar_aluno" data-perm-action="visualizar"
                       class="student-quick-btn bg-blue-600">
                        <i class="fa-solid fa-pen-to-square mr-2"></i> Editar Aluno
                    </a>
                    <a href="<?= URL ?>/admin/students/<?= $student['id'] ?? 0 ?>/acessar-como" data-perm-key="acao_rapida_acessar_aluno" data-perm-action="visualizar"
                       class="student-quick-btn bg-emerald-600">
                        <i class="fa-solid fa-right-to-bracket mr-2"></i> Acessar como Aluno
                    </a>
                    <button type="button" onclick="abrirModalAcessarComoPai()" data-perm-key="acao_rapida_acessar_pai" data-perm-action="visualizar"
                        <?= empty($responsaveis_aluno) ? 'disabled' : '' ?>
                        class="student-quick-btn <?= empty($responsaveis_aluno) ? 'bg-slate-300 text-slate-500 cursor-not-allowed' : 'bg-cyan-600' ?>">
                        <i class="fa-solid fa-people-roof mr-2"></i> Acessar como Pai
                    </button>
                    <?php if ($student['ativo'] ?? 0): ?>
                    <button onclick="abrirModalInativarAluno()" data-perm-key="acao_rapida_ativar_desativar" data-perm-action="alterar"
                            class="student-quick-btn bg-orange-500">
                        <i class="fa-solid fa-user-slash mr-2"></i> Inativar / TR
                    </button>
                    <?php else: ?>
                    <button onclick="abrirModalAtivarAluno()" data-perm-key="acao_rapida_ativar_desativar" data-perm-action="alterar"
                            class="student-quick-btn bg-green-600">
                        <i class="fa-solid fa-user-check mr-2"></i> Ativar aluno
                    </button>
                    <?php endif; ?>
                    <button onclick="alterarSenhaPadrao(<?= $student['id'] ?? 0 ?>, '<?= safe_htmlspecialchars(addslashes($student['nome'] ?? ''), '') ?>')" data-perm-key="acao_rapida_resetar_senha" data-perm-action="alterar"
                            class="student-quick-btn bg-purple-600">
                        <i class="fa-solid fa-key mr-2"></i> Resetar Senha
                    </button>
                    <?php if ($matriculas_schema_ready): ?>
                    <button type="button" onclick="abrirModalMatricula()" data-perm-key="matriculas_aluno" data-perm-action="cadastrar"
                            class="student-quick-btn bg-teal-600">
                        <i class="fa-solid fa-graduation-cap mr-2"></i> Matrícula
                    </button>
                    <?php endif; ?>
                    <button onclick="abrirModalCadastrarPai(<?= $student['id'] ?? 0 ?>)" data-perm-key="acao_rapida_cadastrar_responsavel" data-perm-action="cadastrar"
                            class="student-quick-btn bg-indigo-600">
                        <i class="fa-solid fa-user-plus mr-2"></i> Cadastrar Responsável
                    </button>
                    <a href="<?= URL ?>/admin/reunioes/aluno?aluno_id=<?= $student['id'] ?? 0 ?>"
                       class="student-quick-btn bg-teal-600">
                        <i class="fa-solid fa-file-lines mr-2"></i> ATA / Reunião c/ Pais
                    </a>
                    <button onclick="abrirModalAnalise(<?= $student['id'] ?? 0 ?>)" data-perm-key="acao_rapida_analise_tudinha" data-perm-action="alterar"
                            class="student-quick-btn bg-pink-600">
                        <i class="fa-solid fa-wand-magic-sparkles mr-2"></i> Análise da Tudinha
                    </button>
                    <button type="button" onclick="abrirModalDoc('Declaracoes')" data-perm-key="declaracoes_aluno" data-perm-action="visualizar"
                            class="student-quick-btn bg-amber-600">
                        <i class="fa-solid fa-file-lines mr-2"></i> Declarações
                    </button>
                    <button type="button" onclick="abrirModalDoc('Documentacao')" data-perm-key="declaracoes_aluno" data-perm-action="visualizar"
                            class="student-quick-btn bg-rose-600">
                        <i class="fa-solid fa-folder-open mr-2"></i> Documentação
                    </button>
                    <button type="button" onclick="abrirModalDoc('Autorizacoes')" data-perm-key="declaracoes_aluno" data-perm-action="visualizar"
                            class="student-quick-btn bg-cyan-700">
                        <i class="fa-solid fa-file-signature mr-2"></i> Autorizações
                    </button>
                    <a href="<?= URL ?>/admin/enrollment/create?aluno_id=<?= (int)($student['id'] ?? 0) ?>&tipo=rematricula"
                       class="student-quick-btn bg-indigo-600">
                        <i class="fa-solid fa-file-signature mr-2"></i> Rematrícula
                    </a>
                    <button type="button" onclick="abrirFinanceiro(<?= (int)($student['id'] ?? 0) ?>)"
                            class="student-quick-btn bg-emerald-600">
                        <i class="fa-solid fa-dollar-sign mr-2"></i> Financeiro
                    </button>
                    <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleVisible('inclusao')): ?>
                    <button type="button" onclick="abrirInclusao(<?= (int) ($student['id'] ?? 0) ?>)" data-perm-key="inclusao" data-perm-action="visualizar"
                            class="student-quick-btn bg-violet-600">
                        <i class="fa-solid fa-universal-access mr-2"></i> EducaInclui / Laudo
                    </button>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/reconhecimento-facial/alunos/<?= (int)($student['id'] ?? 0) ?>/eventos" data-perm-key="reconhecimento_facial" data-perm-action="visualizar"
                       class="student-quick-btn bg-sky-700">
                        <i class="fa-solid fa-door-open mr-2"></i> Entrada / Saída Facial
                    </a>
                    <button type="button" onclick="abrirModalExcluirAluno()" data-perm-key="acao_rapida_excluir_aluno" data-perm-action="excluir"
                            class="student-quick-btn bg-red-600">
                        <i class="fa-solid fa-trash-can mr-2"></i> Excluir Aluno
                    </button>
                </div>
            </div>
            <!-- Drawer: Financeiro do Aluno -->
            <div id="drawerFinanceiro" class="fixed inset-0 z-[9998] hidden" aria-modal="true">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="fecharFinanceiro()"></div>
                <!-- Painel -->
                <div class="absolute right-0 top-0 h-full w-full max-w-2xl bg-white shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300" id="drawerFinanceiroPanel">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Financeiro</h3>
                            <p class="text-sm text-gray-500" id="drawerFinanceiroAluno"></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a id="drawerFinanceiroExtratoLink" href="#" target="_blank"
                               class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                                <i class="fa-solid fa-receipt mr-1.5"></i> Extrato completo
                            </a>
                            <button onclick="fecharFinanceiro()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Conteúdo -->
                    <div class="flex-1 overflow-y-auto px-6 py-4" id="drawerFinanceiroBody">
                        <div class="flex items-center justify-center h-40">
                            <div class="text-center text-gray-400">
                                <i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block text-green-500"></i>
                                <p class="text-sm">Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Drawer: EducaInclui / Laudo do Aluno -->
            <div id="drawerInclusao" class="fixed inset-0 z-[9998] hidden" aria-modal="true">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="fecharInclusao()"></div>
                <!-- Painel -->
                <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300" id="drawerInclusaoPanel">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">EducaInclui / Laudo</h3>
                            <p class="text-sm text-gray-500" id="drawerInclusaoAluno"></p>
                        </div>
                        <button onclick="fecharInclusao()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                    <!-- Conteúdo -->
                    <div class="flex-1 overflow-y-auto px-6 py-4" id="drawerInclusaoBody">
                        <div class="flex items-center justify-center h-40">
                            <div class="text-center text-gray-400">
                                <i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block text-green-500"></i>
                                <p class="text-sm">Carregando...</p>
                            </div>
                        </div>
                    </div>
                    <!-- Rodapé -->
                    <div class="px-6 py-4 border-t border-gray-200 flex-shrink-0">
                        <a id="drawerInclusaoManageLink" href="#"
                           class="btn-primary-custom w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors">
                            <i class="fa-solid fa-pen-to-square mr-2"></i> Abrir máscara completa
                        </a>
                    </div>
                </div>
            </div>
            <script>
            function abrirInclusao(alunoId) {
                const drawer    = document.getElementById('drawerInclusao');
                const panel     = document.getElementById('drawerInclusaoPanel');
                const body      = document.getElementById('drawerInclusaoBody');
                const alunoSpan = document.getElementById('drawerInclusaoAluno');
                const manageLink = document.getElementById('drawerInclusaoManageLink');

                manageLink.href = '<?= URL ?>/admin/inclusao/aluno/' + alunoId;

                drawer.classList.remove('hidden');
                requestAnimationFrame(() => panel.classList.remove('translate-x-full'));
                document.body.style.overflow = 'hidden';

                body.innerHTML = '<div class="flex items-center justify-center h-40"><div class="text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block text-green-500"></i><p class="text-sm">Carregando...</p></div></div>';

                fetch('<?= URL ?>/admin/inclusao/aluno/' + alunoId + '/resumo')
                    .then(r => r.json())
                    .then(data => renderInclusaoResumo(data, alunoSpan))
                    .catch(() => {
                        body.innerHTML = '';
                        const err = document.createElement('div');
                        err.className = 'text-center py-10 text-gray-400';
                        err.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-3xl mb-3 block text-amber-400"></i>';
                        const p = document.createElement('p');
                        p.textContent = 'Erro ao carregar dados do EducaInclui.';
                        err.appendChild(p);
                        body.appendChild(err);
                    });
            }

            function fecharInclusao() {
                const drawer = document.getElementById('drawerInclusao');
                const panel  = document.getElementById('drawerInclusaoPanel');
                panel.classList.add('translate-x-full');
                setTimeout(() => { drawer.classList.add('hidden'); document.body.style.overflow = ''; }, 300);
            }

            function renderInclusaoResumo(data, alunoSpan) {
                const body = document.getElementById('drawerInclusaoBody');
                body.innerHTML = '';
                if (data.error) {
                    const p = document.createElement('p');
                    p.className = 'text-center text-gray-400 py-10';
                    p.textContent = 'Não foi possível carregar os dados.';
                    body.appendChild(p);
                    return;
                }
                if (data.aluno_nome) alunoSpan.textContent = data.aluno_nome;

                if (!data.has_accommodation) {
                    const empty = document.createElement('div');
                    empty.className = 'text-center py-10 text-gray-400';
                    empty.innerHTML = '<i class="fa-solid fa-universal-access text-4xl mb-4 block text-gray-200"></i>';
                    const p = document.createElement('p');
                    p.textContent = 'Este aluno ainda não tem máscara de acessibilidade cadastrada.';
                    empty.appendChild(p);
                    body.appendChild(empty);
                    return;
                }

                const statusLabel = { rascunho: 'Rascunho', ativa: 'Ativa', suspensa: 'Suspensa', encerrada: 'Encerrada' };
                const statusCls = {
                    rascunho: 'bg-slate-100 text-slate-700',
                    ativa: 'bg-green-100 text-green-800',
                    suspensa: 'bg-amber-100 text-amber-800',
                    encerrada: 'bg-red-100 text-red-800',
                };
                const tipoLabel = { acesso: 'Acesso', significativa: 'Significativa' };

                const statusRow = document.createElement('div');
                statusRow.className = 'flex items-center gap-2 mb-4';
                const badge = document.createElement('span');
                badge.className = 'inline-flex px-3 py-1 rounded-full text-xs font-semibold ' + (statusCls[data.status] || statusCls.rascunho);
                badge.textContent = statusLabel[data.status] || data.status || '—';
                statusRow.appendChild(badge);
                const tipo = document.createElement('span');
                tipo.className = 'text-sm text-gray-600';
                tipo.textContent = tipoLabel[data.tipo_adaptacao] || data.tipo_adaptacao || '';
                statusRow.appendChild(tipo);
                body.appendChild(statusRow);

                const laudoP = document.createElement('p');
                laudoP.className = 'text-sm text-gray-600 mb-4 flex items-center gap-2';
                laudoP.innerHTML = '<i class="fa-solid fa-file-shield text-gray-400"></i>';
                const laudoSpan = document.createElement('span');
                laudoSpan.textContent = data.laudo_count > 0
                    ? data.laudo_count + ' laudo(s) anexado(s)'
                    : 'Nenhum laudo anexado ainda';
                laudoP.appendChild(laudoSpan);
                body.appendChild(laudoP);

                const title = document.createElement('p');
                title.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2';
                title.textContent = 'Regras de acessibilidade ativas';
                body.appendChild(title);

                const ul = document.createElement('ul');
                ul.className = 'list-disc pl-5 text-sm text-gray-700 space-y-1';
                if (!data.regras_ativas || data.regras_ativas.length === 0) {
                    const li = document.createElement('li');
                    li.className = 'text-gray-400 list-none';
                    li.textContent = 'Nenhuma regra ativa.';
                    ul.appendChild(li);
                } else {
                    data.regras_ativas.forEach(r => {
                        const li = document.createElement('li');
                        li.textContent = r;
                        ul.appendChild(li);
                    });
                }
                body.appendChild(ul);
            }

            document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharInclusao(); });

            function abrirFinanceiro(alunoId) {
                const drawer    = document.getElementById('drawerFinanceiro');
                const panel     = document.getElementById('drawerFinanceiroPanel');
                const body      = document.getElementById('drawerFinanceiroBody');
                const alunoSpan = document.getElementById('drawerFinanceiroAluno');
                const extLink   = document.getElementById('drawerFinanceiroExtratoLink');

                alunoSpan.textContent = document.querySelector('.student-name-display')?.textContent?.trim() || '';
                extLink.href = '<?= URL ?>/admin/finance/aluno/' + alunoId + '/extrato';

                drawer.classList.remove('hidden');
                requestAnimationFrame(() => panel.classList.remove('translate-x-full'));
                document.body.style.overflow = 'hidden';

                body.innerHTML = '<div class="flex items-center justify-center h-40"><div class="text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin text-3xl mb-3 block text-green-500"></i><p class="text-sm">Carregando...</p></div></div>';

                fetch('<?= URL ?>/admin/finance/aluno/' + alunoId + '/resumo')
                    .then(r => r.json())
                    .then(data => renderFinanceiro(data, alunoId, alunoSpan))
                    .catch(() => {
                        body.innerHTML = '<div class="text-center py-10 text-gray-400"><i class="fa-solid fa-triangle-exclamation text-3xl mb-3 block text-amber-400"></i><p>Erro ao carregar dados financeiros.</p></div>';
                    });
            }

            function fecharFinanceiro() {
                const drawer = document.getElementById('drawerFinanceiro');
                const panel  = document.getElementById('drawerFinanceiroPanel');
                panel.classList.add('translate-x-full');
                setTimeout(() => { drawer.classList.add('hidden'); document.body.style.overflow = ''; }, 300);
            }

            function renderFinanceiro(data, alunoId, alunoSpan) {
                const body = document.getElementById('drawerFinanceiroBody');
                if (data.nome) alunoSpan.textContent = data.nome;

                const brl = v => 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                const dt  = s => s ? s.split('-').reverse().join('/') : '—';
                const stCls = {
                    pendente: 'bg-amber-100 text-amber-700',
                    vencido:  'bg-red-100 text-red-700',
                    pago:     'bg-green-100 text-green-700',
                };
                const stLabel = { pendente: 'A pagar', vencido: 'Vencido', pago: 'Pago' };

                const totAberto = (data.faturas || []).filter(f => ['pendente','vencido'].includes(f.status)).reduce((s,f) => s + Number(f.valor_total||0), 0);
                const totPago   = (data.faturas || []).filter(f => f.status === 'pago').reduce((s,f) => s + Number(f.valor_total||0), 0);

                let html = `
                    <div class="grid grid-cols-3 gap-3 mb-5">
                        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center shadow-sm">
                            <p class="text-xs text-gray-500 mb-0.5">Saldo</p>
                            <p class="text-base font-bold ${data.saldo >= 0 ? 'text-green-700' : 'text-red-600'}">${brl(Math.abs(data.saldo || 0))}</p>
                            <p class="text-xs text-gray-400">${data.saldo >= 0 ? 'credor' : 'devedor'}</p>
                        </div>
                        <div class="bg-amber-50 rounded-xl border border-amber-200 p-3 text-center shadow-sm">
                            <p class="text-xs text-amber-600 mb-0.5">Em aberto</p>
                            <p class="text-base font-bold text-amber-700">${brl(totAberto)}</p>
                        </div>
                        <div class="bg-green-50 rounded-xl border border-green-200 p-3 text-center shadow-sm">
                            <p class="text-xs text-green-600 mb-0.5">Pago</p>
                            <p class="text-base font-bold text-green-700">${brl(totPago)}</p>
                        </div>
                    </div>
                    <div class="flex gap-3 mb-5">
                        <a href="<?= URL ?>/admin/finance/aluno/${alunoId}/charge"
                           class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg bg-primary text-primary text-sm font-semibold hover:opacity-90 transition-opacity">
                            <i class="fa-solid fa-plus mr-2"></i> Cobrança Avulsa
                        </a>
                        <a href="<?= URL ?>/admin/finance/aluno/${alunoId}/extrato"
                           class="flex-1 inline-flex items-center justify-center px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fa-solid fa-receipt mr-2"></i> Extrato Completo
                        </a>
                    </div>`;

                const faturas = data.faturas || [];
                const abertas = faturas.filter(f => ['pendente','vencido'].includes(f.status));
                const pagas   = faturas.filter(f => f.status === 'pago');

                if (abertas.length) {
                    html += `<h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5"><i class="fa-solid fa-circle-exclamation text-amber-500"></i> Em aberto (${abertas.length})</h4>
                        <div class="space-y-2 mb-5">`;
                    abertas.forEach(f => {
                        const cls = stCls[f.status] || stCls.pendente;
                        const tipo = f.tipo === 'cobrança' ? `<span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700 mr-1">${f.categoria_label||f.categoria}</span>` : '';
                        html += `<div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center justify-between gap-3 shadow-sm">
                            <div class="flex-1 min-w-0">
                                ${tipo}<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${stLabel[f.status]||f.status}</span>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5 truncate">${f.descricao}</p>
                                <p class="text-xs text-gray-400">Vence ${dt(f.data_vencimento)}</p>
                            </div>
                            <p class="text-base font-bold text-gray-900 flex-shrink-0">${brl(f.valor_total)}</p>
                        </div>`;
                    });
                    html += `</div>`;
                }

                if (pagas.length) {
                    html += `<h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5"><i class="fa-solid fa-circle-check text-green-500"></i> Pagos (${pagas.length})</h4>
                        <div class="space-y-2">`;
                    pagas.forEach(f => {
                        const tipo = f.tipo === 'cobrança' ? `<span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700 mr-1">${f.categoria_label||f.categoria}</span>` : '';
                        html += `<div class="bg-gray-50 rounded-xl border border-gray-100 px-4 py-3 flex items-center justify-between gap-3 opacity-80">
                            <div class="flex-1 min-w-0">
                                ${tipo}
                                <p class="text-sm font-medium text-gray-700 truncate">${f.descricao}</p>
                                <p class="text-xs text-gray-400">Pago em ${dt(f.data_pagamento)}</p>
                            </div>
                            <p class="text-sm font-semibold text-green-700 flex-shrink-0">${brl(f.valor_total)}</p>
                        </div>`;
                    });
                    html += `</div>`;
                }

                if (!faturas.length) {
                    html += `<div class="text-center py-10 text-gray-400"><i class="fa-solid fa-file-invoice text-4xl mb-4 block text-gray-200"></i><p>Nenhuma fatura encontrada.</p></div>`;
                }

                body.innerHTML = html;
            }

            // Fechar com Escape
            document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharFinanceiro(); });
            </script>

            <!-- Modal: Declarações do Aluno -->
            <div id="modalDeclaracoes" class="doc-modal hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4" style="display:none;">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                        <h3 class="text-lg font-semibold text-slate-900">
                            <i class="fa-solid fa-file-lines text-amber-500 mr-2"></i> Declarações
                        </h3>
                        <button type="button" onclick="fecharModalDoc('Declaracoes')" class="text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-3">
                        <p class="text-xs text-slate-500">Os dados institucionais do cabeçalho vêm da unidade vinculada ao aluno.</p>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/matricula/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-graduation-cap"></i></span>
                            <span><span class="block font-medium text-slate-800">Declaração de Matrícula</span><span class="block text-xs text-slate-500">Comprovante de matrícula ativa</span></span>
                        </a>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/transferencia/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-right-from-bracket"></i></span>
                            <span><span class="block font-medium text-slate-800">Declaração de Transferência</span><span class="block text-xs text-slate-500">Conclusão / saída do aluno</span></span>
                        </a>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fa-solid fa-calendar-check"></i></span>
                                <span><span class="block font-medium text-slate-800">Declaração de Frequência</span><span class="block text-xs text-slate-500">Calculada a partir do diário de classe</span></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Início</label>
                                    <input type="date" id="decl_freq_inicio" value="<?= date('Y') ?>-01-01" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Fim</label>
                                    <input type="date" id="decl_freq_fim" value="<?= date('Y-m-d') ?>" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                            <button type="button" onclick="gerarDeclaracaoFrequencia()" class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-user-check"></i></span>
                                <span><span class="block font-medium text-slate-800">Declaração de Comparecimento</span><span class="block text-xs text-slate-500">Presença em data específica</span></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Data</label>
                                    <input type="date" id="decl_comp_data" value="<?= date('Y-m-d') ?>" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Período (opcional)</label>
                                    <input type="text" id="decl_comp_periodo" placeholder="Ex: 08h às 12h" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                            <button type="button" onclick="gerarDeclaracaoComparecimento()" class="w-full px-3 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal: Documentação do Aluno -->
            <div id="modalDocumentacao" class="doc-modal hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4" style="display:none;">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                        <h3 class="text-lg font-semibold text-slate-900">
                            <i class="fa-solid fa-folder-open text-rose-500 mr-2"></i> Documentação
                        </h3>
                        <button type="button" onclick="fecharModalDoc('Documentacao')" class="text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-3">
                        <p class="text-xs text-slate-500">Os dados institucionais do cabeçalho vêm da unidade vinculada ao aluno.</p>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/historico-escolar"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left"></i></span>
                            <span><span class="block font-medium text-slate-800">Histórico Escolar</span><span class="block text-xs text-slate-500">Documento oficial (rascunho → emissão → assinatura → QR)</span></span>
                        </a>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/historico/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-dashed border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-slate-50 text-slate-500 flex items-center justify-center"><i class="fa-solid fa-file-pdf"></i></span>
                            <span><span class="block font-medium text-slate-700">PDF rápido (legado)</span><span class="block text-xs text-slate-500">Extrato de boletins sem workflow jurídico</span></span>
                        </a>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/ficha_matricula/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center"><i class="fa-solid fa-id-card"></i></span>
                            <span><span class="block font-medium text-slate-800">Ficha de Matrícula</span><span class="block text-xs text-slate-500">Dados cadastrais e responsáveis</span></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Modal: Autorizações do Aluno -->
            <div id="modalAutorizacoes" class="doc-modal hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4" style="display:none;">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                        <h3 class="text-lg font-semibold text-slate-900">
                            <i class="fa-solid fa-file-signature text-cyan-600 mr-2"></i> Autorizações
                        </h3>
                        <button type="button" onclick="fecharModalDoc('Autorizacoes')" class="text-slate-400 hover:text-slate-600">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-3">
                        <p class="text-xs text-slate-500">Os dados institucionais do cabeçalho vêm da unidade vinculada ao aluno.</p>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center"><i class="fa-solid fa-door-open"></i></span>
                                <span><span class="block font-medium text-slate-800">Autorização de Saída</span><span class="block text-xs text-slate-500">Saída antecipada do aluno</span></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Data</label>
                                    <input type="date" id="aut_saida_data" value="<?= date('Y-m-d') ?>" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Horário</label>
                                    <input type="text" id="aut_saida_horario" placeholder="Ex: 11h30" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                            <input type="text" id="aut_saida_motivo" placeholder="Motivo (opcional)" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm mb-3">
                            <button type="button" onclick="gerarAutSaida()" class="w-full px-3 py-2 rounded-lg bg-orange-600 text-white text-sm font-medium hover:bg-orange-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center"><i class="fa-solid fa-people-arrows"></i></span>
                                <span><span class="block font-medium text-slate-800">Autorização de Retirada</span><span class="block text-xs text-slate-500">Retirada do aluno por terceiros</span></span>
                            </div>
                            <input type="text" id="aut_ret_nome" placeholder="Nome da pessoa autorizada" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm mb-2">
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <input type="text" id="aut_ret_doc" placeholder="Documento (RG/CPF)" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                <input type="text" id="aut_ret_parentesco" placeholder="Parentesco/vínculo" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                            </div>
                            <button type="button" onclick="gerarAutRetirada()" class="w-full px-3 py-2 rounded-lg bg-cyan-600 text-white text-sm font-medium hover:bg-cyan-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>

                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes/aut_imagem/pdf" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-fuchsia-50 text-fuchsia-600 flex items-center justify-center"><i class="fa-solid fa-camera"></i></span>
                            <span><span class="block font-medium text-slate-800">Autorização de Uso de Imagem</span><span class="block text-xs text-slate-500">Consentimento de uso de imagem/voz</span></span>
                        </a>

                        <div class="p-3 rounded-xl border border-slate-200">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-9 h-9 rounded-lg bg-lime-50 text-lime-600 flex items-center justify-center"><i class="fa-solid fa-bus"></i></span>
                                <span><span class="block font-medium text-slate-800">Autorização de Passeio/Excursão</span><span class="block text-xs text-slate-500">Participação em atividade externa</span></span>
                            </div>
                            <input type="text" id="aut_pas_local" placeholder="Destino / local" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm mb-2">
                            <div class="grid grid-cols-3 gap-2 mb-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Data</label>
                                    <input type="date" id="aut_pas_data" value="<?= date('Y-m-d') ?>" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Saída</label>
                                    <input type="text" id="aut_pas_saida" placeholder="08h" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Retorno</label>
                                    <input type="text" id="aut_pas_retorno" placeholder="17h" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm">
                                </div>
                            </div>
                            <button type="button" onclick="gerarAutPasseio()" class="w-full px-3 py-2 rounded-lg bg-lime-600 text-white text-sm font-medium hover:bg-lime-700 transition-colors">
                                <i class="fa-solid fa-download mr-1"></i> Gerar PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                (function () {
                    var baseDecl = '<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/declaracoes';
                    var moved = {};
                    function val(id) {
                        var el = document.getElementById(id);
                        return el ? el.value : '';
                    }
                    function abrir(tipo, params) {
                        var qs = '';
                        if (params) {
                            var parts = [];
                            Object.keys(params).forEach(function (k) {
                                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
                            });
                            qs = parts.length ? ('?' + parts.join('&')) : '';
                        }
                        window.open(baseDecl + '/' + tipo + '/pdf' + qs, '_blank', 'noopener');
                    }
                    window.abrirModalDoc = function (nome) {
                        var m = document.getElementById('modal' + nome);
                        if (!m) { return; }
                        // Move para o body para que "position: fixed" se ancore na viewport
                        // (evita que ancestrais com transform/backdrop-blur prendam o modal).
                        if (!moved[nome]) { document.body.appendChild(m); moved[nome] = true; }
                        m.style.display = 'flex';
                    };
                    window.fecharModalDoc = function (nome) {
                        var m = document.getElementById('modal' + nome);
                        if (m) { m.style.display = 'none'; }
                    };
                    window.gerarDeclaracaoFrequencia = function () {
                        abrir('frequencia', { inicio: val('decl_freq_inicio'), fim: val('decl_freq_fim') });
                    };
                    window.gerarDeclaracaoComparecimento = function () {
                        abrir('comparecimento', { data: val('decl_comp_data'), periodo: val('decl_comp_periodo') });
                    };
                    window.gerarAutSaida = function () {
                        abrir('aut_saida', { data: val('aut_saida_data'), horario: val('aut_saida_horario'), motivo: val('aut_saida_motivo') });
                    };
                    window.gerarAutRetirada = function () {
                        abrir('aut_retirada', { nome_autorizado: val('aut_ret_nome'), documento: val('aut_ret_doc'), parentesco: val('aut_ret_parentesco') });
                    };
                    window.gerarAutPasseio = function () {
                        abrir('aut_passeio', { local: val('aut_pas_local'), data: val('aut_pas_data'), hora_saida: val('aut_pas_saida'), hora_retorno: val('aut_pas_retorno') });
                    };
                    document.addEventListener('click', function (e) {
                        if (e.target && e.target.classList && e.target.classList.contains('doc-modal')) {
                            e.target.style.display = 'none';
                        }
                    });
                })();
            </script>

            <div id="successCard" class="hidden bg-green-50 border border-green-200 rounded-2xl p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-lg font-medium text-green-800">Senha Alterada com Sucesso!</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>A senha do aluno <strong id="alunoNomeConfirmacao"></strong> foi alterada para a senha padrão:</p>
                            <div class="mt-2 bg-green-100 border border-green-300 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-lg font-bold text-green-800">123456</span>
                                    <button onclick="copiarSenha()" class="text-green-600 hover:text-green-800 text-sm font-medium">📋 Copiar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button onclick="fecharCardSucesso()" class="text-green-400 hover:text-green-600 ml-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <?php
    $fc = is_array($ficha_complementar ?? null) ? $ficha_complementar : [];
    $fcVal = static function ($key) use ($fc) {
        $v = trim((string) ($fc[$key] ?? ''));
        return $v !== '' ? $v : null;
    };
    $transporteTipos = ['escolar' => 'Van/Ônibus escolar', 'publico' => 'Transporte público', 'proprio' => 'Próprio / familiar', 'a_pe' => 'A pé / bicicleta'];
    $usaTransporteFc = !empty($fc['usa_transporte_escolar']);
    $transporteTipoFc = (string) ($fc['transporte_tipo'] ?? '');
    ?>
    <div class="student-card min-w-0 mb-6">
        <div class="student-card-header flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-notes-medical text-rose-500"></i>
            </div>
            <div>
                <h3 class="text-base font-semibold text-slate-900">Ficha complementar</h3>
                <p class="text-sm text-slate-500 mt-0.5">Saúde, alimentação e transporte</p>
            </div>
        </div>
        <div class="student-card-body">
            <div class="student-info-columns">
                <div class="student-info-col">
                    <div class="student-info-col-title">Saúde</div>
                    <div class="student-info-fields">
                        <div>
                            <span class="student-field-label">Tipo sanguíneo</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('tipo_sanguineo'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Plano de saúde</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars(trim(($fcVal('plano_saude') ?? '') . ' ' . ($fcVal('plano_saude_numero') ? '— ' . $fcVal('plano_saude_numero') : '')) ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Hospital de referência</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('hospital_referencia'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Alergias</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('alergias'), 'Nenhuma informada') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Medicamentos de uso contínuo</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('medicamentos_uso'), 'Nenhum informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Condições crônicas</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('condicoes_cronicas'), 'Nenhuma informada') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Acessibilidade / deficiência</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('deficiencias_obs'), 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
                <div class="student-info-col">
                    <div class="student-info-col-title">Contato de emergência</div>
                    <div class="student-info-fields">
                        <div>
                            <span class="student-field-label">Nome</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('contato_emergencia_nome'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Telefone</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('contato_emergencia_telefone'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Parentesco</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('contato_emergencia_parentesco'), 'Não informado') ?></p>
                        </div>
                    </div>
                    <div class="student-info-col-title mt-4">Alimentação</div>
                    <div class="student-info-fields">
                        <div>
                            <span class="student-field-label">Restrições alimentares</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('restricoes_alimentares'), 'Nenhuma informada') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Observações</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('alimentacao_obs'), 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
                <div class="student-info-col">
                    <div class="student-info-col-title">Transporte escolar</div>
                    <div class="student-info-fields">
                        <div>
                            <span class="student-field-label">Utiliza transporte escolar</span>
                            <p class="student-field-value font-normal"><?= $usaTransporteFc ? 'Sim' : 'Não' ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Tipo</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($transporteTipos[$transporteTipoFc] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Rota / linha</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('transporte_rota'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Ponto / referência</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('transporte_ponto'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Responsável / motorista</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('transporte_responsavel'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Telefone do transporte</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('transporte_telefone'), 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($fcVal('observacoes_gerais')): ?>
            <div class="student-info-address">
                <div class="student-info-col-title">Observações gerais</div>
                <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('observacoes_gerais')) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/../../../Models/User/StudentDocument.php';
    $docChecklist = \StudentDocument::checklist();
    $docsAluno = is_array($documentos_aluno ?? null) ? $documentos_aluno : [];
    $docsPorTipo = [];
    $docsOutros = [];
    foreach ($docsAluno as $docRow) {
        if (($docRow['tipo'] ?? '') === 'outros') {
            $docsOutros[] = $docRow;
        } else {
            $docsPorTipo[$docRow['tipo']] = $docRow;
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
    foreach ($docChecklist as $ck => $lbl) {
        if ($ck === 'outros') { continue; }
        if (($docsPorTipo[$ck]['status'] ?? '') === 'entregue') { $totalEntregues++; }
    }
    $totalChecklist = count($docChecklist) - 1;
    ?>
    <div id="section-documentos-aluno" class="student-card min-w-0 mb-6" data-perm-key="documentos_aluno" data-perm-action="visualizar">
        <div class="student-card-header flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-folder-open text-indigo-500"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Documentos</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Checklist de entrega · <?= (int) $totalEntregues ?>/<?= (int) $totalChecklist ?> entregues</p>
                </div>
            </div>
            <?php if ($docCanEdit): ?>
            <button type="button" onclick="abrirModalDocumento()" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
                <i class="fa-solid fa-plus mr-2"></i> Documento
            </button>
            <?php endif; ?>
        </div>
        <div class="student-card-body">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                            <th class="py-2 pr-4 font-semibold">Documento</th>
                            <th class="py-2 pr-4 font-semibold">Status</th>
                            <th class="py-2 pr-4 font-semibold">Arquivo</th>
                            <th class="py-2 font-semibold text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $linhasDoc = [];
                        foreach ($docChecklist as $ckTipo => $ckLabel) {
                            if ($ckTipo === 'outros') { continue; }
                            $linhasDoc[] = ['tipo' => $ckTipo, 'label' => $ckLabel, 'row' => $docsPorTipo[$ckTipo] ?? null];
                        }
                        foreach ($docsOutros as $outroRow) {
                            $linhasDoc[] = ['tipo' => 'outros', 'label' => \StudentDocument::tipoLabel('outros', $outroRow['titulo'] ?? null), 'row' => $outroRow];
                        }
                        foreach ($linhasDoc as $linha):
                            $row = $linha['row'];
                            [$badgeLabel, $badgeClass] = $docStatusBadge($row['status'] ?? 'pendente');
                            $temArquivo = !empty($row['arquivo_key']);
                            $docId = (int) ($row['id'] ?? 0);
                            $dataAttr = htmlspecialchars(json_encode([
                                'doc_id' => $docId,
                                'tipo' => $linha['tipo'],
                                'titulo' => (string) ($row['titulo'] ?? ''),
                                'status' => (string) ($row['status'] ?? 'pendente'),
                                'observacao' => (string) ($row['observacao'] ?? ''),
                                'label' => $linha['label'],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td class="py-3 pr-4">
                                <span class="font-medium text-slate-800"><?= safe_htmlspecialchars($linha['label']) ?></span>
                                <?php if (!empty($row['observacao'])): ?>
                                    <p class="text-xs text-slate-500 mt-0.5"><?= safe_htmlspecialchars($row['observacao']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                            </td>
                            <td class="py-3 pr-4">
                                <?php if ($temArquivo): ?>
                                    <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/documentos/<?= $docId ?>/baixar" target="_blank" rel="noopener"
                                       class="inline-flex items-center text-indigo-600 hover:text-indigo-800 text-sm">
                                        <i class="fa-solid fa-paperclip mr-1"></i> <?= safe_htmlspecialchars($row['arquivo_nome'] ?? 'Ver arquivo') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Sem arquivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-right whitespace-nowrap">
                                <?php if ($docCanEdit): ?>
                                <button type="button" class="px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-800"
                                        data-documento="<?= $dataAttr ?>" onclick="abrirModalDocumento(JSON.parse(this.dataset.documento))">
                                    Gerenciar
                                </button>
                                <?php endif; ?>
                                <?php if ($docCanDelete && $docId > 0 && ($temArquivo || $linha['tipo'] === 'outros')): ?>
                                <button type="button" class="px-2 py-1 text-xs font-medium text-red-600 hover:text-red-800"
                                        onclick="removerDocumento(<?= $docId ?>)">
                                    Remover
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Perf: histórico de auditoria saiu da carga principal e virou página própria,
         pra não rodar SHOW TABLES + SELECT em toda visita ao perfil do aluno. -->
    <div id="section-auditoria-aluno" class="student-card min-w-0 mb-6">
        <div class="student-card-body flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-clock-rotate-left text-slate-500"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Histórico de auditoria</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Últimas ações sensíveis sobre este aluno</p>
                </div>
            </div>
            <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/auditoria"
               class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shrink-0">
                Ver histórico
            </a>
        </div>
    </div>

    <div class="student-duo-grid">
        <div id="section-responsaveis-vinculados" class="student-card min-w-0">
            <div class="student-card-header">
                <h3 class="text-base font-semibold text-slate-900">Responsáveis Vinculados</h3>
            </div>
            <div class="student-card-body pt-4">
                <?php if (empty($responsaveis_aluno)): ?>
                    <p class="text-sm text-gray-500">Nenhum responsável vinculado.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($responsaveis_aluno as $resp): ?>
                            <?php
                            $respNome = (string)($resp['nome'] ?? '');
                            $respEmail = (string)($resp['email'] ?? '');
                            $respTelefone = (string)($resp['telefone'] ?? '');
                            $respCpf = (string)($resp['cpf'] ?? '');
                            $respAtivo = (int)($resp['ativo'] ?? 1) === 1;
                            $respFinanceiro = (int)($resp['is_financeiro'] ?? 0) === 1;
                            $respIniciais = responsavel_iniciais($respNome);
                            ?>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 py-3 border-b border-slate-100 last:border-0">
                                <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="w-10 h-10 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                    <?= safe_htmlspecialchars($respIniciais) ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900 text-sm leading-snug"><?= safe_htmlspecialchars($respNome, '-') ?></p>
                                    <p class="text-xs text-slate-500 mt-1 break-words"><?= safe_htmlspecialchars($respCpf, 'CPF não informado') ?></p>
                                    <p class="text-xs text-slate-500 break-all"><?= safe_htmlspecialchars($respEmail, 'Sem email') ?><?php if ($respTelefone !== ''): ?> · <?= safe_htmlspecialchars($respTelefone) ?><?php endif; ?></p>
                                </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 sm:flex-shrink-0 sm:pl-0 pl-[52px]">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $respAtivo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $respAtivo ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                    <?php if ($respFinanceiro): ?>
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-violet-100 text-violet-800">Financeiro</span>
                                    <?php endif; ?>
                                    <?php if (!empty($resp['parentesco'])): ?>
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-slate-100 text-slate-700"><?= safe_htmlspecialchars($resp['parentesco']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($resp['pode_retirar'])): ?>
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">Pode retirar</span>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="px-2.5 py-1 text-xs font-medium text-blue-600 hover:text-blue-800"
                                        data-responsavel="<?= htmlspecialchars(json_encode([
                                            'aluno_id' => (int)($student['id'] ?? 0),
                                            'responsavel_id' => (int)($resp['id'] ?? 0),
                                            'nome' => $respNome,
                                            'email' => $respEmail,
                                            'telefone' => $respTelefone,
                                            'cpf' => $respCpf,
                                            'rg' => (string)($resp['rg'] ?? ''),
                                            'celular' => (string)($resp['celular'] ?? ''),
                                            'data_nascimento' => (string)($resp['data_nascimento'] ?? ''),
                                            'endereco' => (string)($resp['endereco'] ?? ''),
                                            'numero' => (string)($resp['numero'] ?? ''),
                                            'complemento' => (string)($resp['complemento'] ?? ''),
                                            'bairro' => (string)($resp['bairro'] ?? ''),
                                            'cidade' => (string)($resp['cidade'] ?? ''),
                                            'uf' => (string)($resp['uf'] ?? ''),
                                            'cep' => (string)($resp['cep'] ?? ''),
                                            'observacoes' => (string)($resp['observacoes'] ?? ''),
                                            'is_financeiro' => $respFinanceiro ? 1 : 0,
                                            'ativo' => $respAtivo ? 1 : 0,
                                            'parentesco' => (string)($resp['parentesco'] ?? ''),
                                            'profissao' => (string)($resp['profissao'] ?? ''),
                                            'empresa' => (string)($resp['empresa'] ?? ''),
                                            'pode_retirar' => (int)($resp['pode_retirar'] ?? 0),
                                            'recebe_boletos' => (int)($resp['recebe_boletos'] ?? 0),
                                            'recebe_boletim' => (int)($resp['recebe_boletim'] ?? 0),
                                            'recebe_notificacoes' => (int)($resp['recebe_notificacoes'] ?? 0),
                                            'responsavel_pedagogico' => (int)($resp['responsavel_pedagogico'] ?? 0),
                                            'guarda_judicial' => (int)($resp['guarda_judicial'] ?? 0),
                                            'assina_documentos' => (int)($resp['assina_documentos'] ?? 0)
                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="abrirModalEditarResponsavel(JSON.parse(this.dataset.responsavel))">
                                        Editar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($responsaveisCount > 0): ?>
                <div class="mt-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('section-responsaveis-vinculados').scrollIntoView({behavior:'smooth'})" class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                        Ver todos os responsáveis (<?= (int)$responsaveisCount ?>)
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($matriculas_schema_ready): ?>
        <div id="section-matriculas-aluno" class="student-card min-w-0">
            <div class="student-card-header">
                <h3 class="text-base font-semibold text-slate-900">Matrículas</h3>
                <p class="text-sm text-slate-500 mt-0.5">Turmas e anos de estudo do aluno</p>
            </div>
            <div class="student-card-body">
                <?php if ($flash_message !== ''): ?>
                <div class="mb-4 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
                    <?= safe_htmlspecialchars($flash_message) ?>
                </div>
                <?php endif; ?>
                <?php if ($matricula_divergente_cadastro): ?>
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-medium">Matrícula ativa diferente da turma do cadastro</p>
                    <p class="mt-1 text-amber-900/90">A turma principal no topo da página é <strong><?= safe_htmlspecialchars($turmaDisplay) ?></strong>, mas existe matrícula <strong>ativa</strong> em outra turma. Isso afeta provas e listagens. Confirme se o topo está correto; em seguida use o botão para encerrar a(s) matrícula(s) errada(s) e abrir na turma do cadastro.</p>
                    <button type="button" id="btnSyncMatriculaCadastro" class="mt-3 inline-flex items-center px-4 py-2 rounded-lg bg-amber-700 text-white text-sm font-semibold hover:bg-amber-800">
                        Alinhar matrícula com turma do cadastro
                    </button>
                    <p id="syncMatriculaMsg" class="mt-2 text-sm hidden"></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($matriculas_paralelas)): ?>
                <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50/80 px-4 py-3">
                    <p class="text-sm font-semibold text-indigo-950">Também matriculado em</p>
                    <p class="text-xs text-indigo-900/80 mt-1">Faltas, provas e jornadas usam a turma principal<?= !empty($turmaDisplay) ? ': <strong>' . safe_htmlspecialchars($turmaDisplay) . '</strong>' : '' ?>.</p>
                    <ul class="mt-3 space-y-2">
                        <?php foreach ($matriculas_paralelas as $mp): ?>
                        <li class="flex flex-wrap items-center gap-2 text-sm text-indigo-950">
                            <span class="font-medium"><?= safe_htmlspecialchars($mp['turma_nome'] ?? '') ?></span>
                            <?php if (($mp['curso_tipo'] ?? '') === 'extra'): ?>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-200 text-indigo-900">Curso extra</span>
                            <?php else: ?>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Paralela</span>
                            <?php endif; ?>
                            <span class="text-xs text-indigo-800/70">Ano <?= (int)($mp['ano_letivo_ano'] ?? 0) ?> · entrada <?= format_data_br($mp['data_entrada'] ?? null) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if (empty($matriculas)): ?>
                    <p class="text-gray-500 text-sm">Nenhuma matrícula cadastrada. Use <strong>Matrícula</strong> em Ações Rápidas para adicionar.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4">Turma</th>
                                    <th class="py-2 pr-4">Vínculo</th>
                                    <th class="py-2 pr-4">Ano letivo</th>
                                    <th class="py-2 pr-4">Entrada</th>
                                    <th class="py-2 pr-4">Saída</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($matriculas as $mat): ?>
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-900"><?= safe_htmlspecialchars($mat['turma_nome'] ?? '') ?></td>
                                        <td class="py-2 pr-4">
                                            <?php
                                            $vr = $mat['vinculo_rotulo'] ?? '';
                                            $vrClass = $vr === 'Principal' ? 'bg-blue-100 text-blue-800' : ($vr === 'Extra' ? 'bg-indigo-100 text-indigo-800' : 'bg-purple-100 text-purple-800');
                                            if ($vr !== '' && ($mat['status'] ?? '') === 'ativa'):
                                            ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $vrClass ?>"><?= safe_htmlspecialchars($vr) ?></span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-700"><?= (int)($mat['ano_letivo_ano'] ?? 0) ?></td>
                                        <td class="py-2 pr-4 text-gray-700"><?= format_data_br($mat['data_entrada'] ?? null) ?></td>
                                        <td class="py-2 pr-4 text-gray-700"><?= format_data_br($mat['data_saida'] ?? null) ?></td>
                                        <td class="py-2 pr-4">
                                            <?php
                                            $stMat = (string)($mat['status'] ?? '');
                                            if ($stMat === 'ativa') {
                                                $stLabel = 'Ativa';
                                                $stClass = 'bg-green-100 text-green-800';
                                            } elseif ($stMat === 'concluido') {
                                                $stLabel = 'Encerrada';
                                                $stClass = 'bg-slate-100 text-slate-700';
                                            } elseif ($stMat === 'transferido') {
                                                $stLabel = 'Transferida';
                                                $stClass = 'bg-gray-100 text-gray-600';
                                            } else {
                                                $stLabel = $stMat !== '' ? $stMat : '—';
                                                $stClass = 'bg-gray-100 text-gray-600';
                                            }
                                            ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $stClass ?>"><?= safe_htmlspecialchars($stLabel) ?></span>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <?php if (($mat['status'] ?? '') === 'ativa'): ?>
                                            <form action="<?= URL ?>/admin/students/<?= (int)$student['id'] ?>/matricula/<?= (int)$mat['id'] ?>/encerrar" method="POST" class="inline" onsubmit="return confirm('Encerrar esta matrícula?');">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="data_saida" value="<?= date('Y-m-d') ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-semibold">Encerrar</button>
                                            </form>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <button type="button" onclick="toggleHistoricoTurmas()" class="mt-4 text-sm font-semibold text-blue-600 hover:text-blue-800 inline-flex items-center">
                    Ver histórico de turmas
                    <i id="icon-historico-chevron" class="fa-solid fa-chevron-down ml-1.5 text-xs transition-transform"></i>
                </button>
                <div id="bloco-historico-turmas" class="hidden mt-4 pt-4 border-t border-slate-200">
                    <?php if (empty($historico_turmas)): ?>
                        <p class="text-slate-500 text-sm">Nenhum histórico de turma registrado.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                                        <th class="py-2 pr-4">Turma</th>
                                        <th class="py-2 pr-4">Ano letivo</th>
                                        <th class="py-2 pr-4">Tipo</th>
                                        <th class="py-2 pr-4">Início</th>
                                        <th class="py-2 pr-4">Fim</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($historico_turmas as $hist): ?>
                                        <tr>
                                            <td class="py-2 pr-4 text-slate-900 font-medium"><?= safe_htmlspecialchars($hist['turma_nome'] ?? '', '') ?></td>
                                            <td class="py-2 pr-4 text-slate-600"><?= safe_htmlspecialchars($hist['ano_letivo'] ?? '', '') ?></td>
                                            <td class="py-2 pr-4 text-slate-600"><?= safe_htmlspecialchars($hist['tipo_ensino'] ?? '', '') ?></td>
                                            <td class="py-2 pr-4 text-slate-600"><?= format_data_br($hist['data_inicio'] ?? null, '') ?></td>
                                            <td class="py-2 pr-4 text-slate-600"><?= format_data_br($hist['data_fim'] ?? null, 'Atual') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modal adicionar matrícula -->
        <div id="modalAddMatricula" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Adicionar matrícula</h3>
                    <button type="button" onclick="fecharModalMatricula()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="formAddMatricula" class="space-y-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div>
                        <label for="mat_turma_id" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                        <select id="mat_turma_id" name="turma_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="">Selecione</option>
                            <?php foreach ($turmas_para_matricula as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" data-curso-tipo="<?= safe_htmlspecialchars($t['curso_tipo'] ?? 'regular') ?>"><?= safe_htmlspecialchars($t['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="mat_ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-1">Ano letivo</label>
                            <select id="mat_ano_letivo_id" name="ano_letivo_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Selecione</option>
                                <?php foreach ($anos_letivo_para_matricula as $al): ?>
                                <option value="<?= (int)$al['id'] ?>"><?= (int)$al['ano'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="mat_data_entrada" class="block text-sm font-medium text-gray-700 mb-1">Data entrada</label>
                            <input type="date" id="mat_data_entrada" name="data_entrada" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div id="wrap_definir_turma_principal" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" id="mat_definir_turma_principal" name="definir_turma_principal" value="1"
                                   class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                   <?= empty($student['turma_id']) ? 'checked' : '' ?>>
                            <span>
                                <span class="block text-sm font-medium text-gray-800">Definir como turma principal</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Desmarque ao vincular curso extra em paralelo (ex.: Música, Robótica).</span>
                            </span>
                        </label>
                    </div>
                    <p id="matriculaMsg" class="text-sm hidden"></p>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="fecharModalMatricula()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</button>
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg font-semibold hover:opacity-90">
                            <i class="fa-solid fa-plus mr-1"></i> Adicionar matrícula
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function toggleHistoricoTurmas() {
            var bloco = document.getElementById('bloco-historico-turmas');
            var icon = document.getElementById('icon-historico-chevron');
            if (!bloco) return;
            bloco.classList.toggle('hidden');
            if (icon) icon.classList.toggle('rotate-180');
        }
        async function ehFetchJsonMatricula(url, body) {
            var r = await fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            var text = await r.text();
            try {
                return { ok: r.ok, status: r.status, json: JSON.parse(text) };
            } catch (parseErr) {
                return { ok: false, status: r.status, json: null, raw: text.slice(0, 400) };
            }
        }
        function atualizarCheckboxTurmaPrincipalMatricula() {
            var sel = document.getElementById('mat_turma_id');
            var cb = document.getElementById('mat_definir_turma_principal');
            if (!sel || !cb) return;
            var opt = sel.options[sel.selectedIndex];
            var cursoExtra = opt && opt.getAttribute('data-curso-tipo') === 'extra';
            var alunoTemTurma = <?= !empty($student['turma_id']) ? 'true' : 'false' ?>;
            if (!alunoTemTurma) {
                cb.checked = true;
                cb.disabled = true;
                return;
            }
            cb.disabled = false;
            if (cursoExtra) {
                cb.checked = false;
            } else if (!sel.value) {
                cb.checked = true;
            }
        }
        document.getElementById('mat_turma_id')?.addEventListener('change', atualizarCheckboxTurmaPrincipalMatricula);
        document.getElementById('formAddMatricula')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            var msg = document.getElementById('matriculaMsg');
            msg.classList.add('hidden');
            var formData = new FormData(this);
            var cbPrincipal = document.getElementById('mat_definir_turma_principal');
            if (cbPrincipal && !cbPrincipal.checked) {
                formData.delete('definir_turma_principal');
            }
            try {
                var res = await ehFetchJsonMatricula('<?= htmlspecialchars($ehMatriculaPostRel, ENT_QUOTES, 'UTF-8') ?>', formData);
                if (res.json && res.json.success) {
                    msg.textContent = res.json.message;
                    msg.className = 'mt-2 text-sm text-green-700';
                    msg.classList.remove('hidden');
                    this.reset();
                    document.getElementById('mat_data_entrada').value = '<?= date('Y-m-d') ?>';
                    atualizarCheckboxTurmaPrincipalMatricula();
                    fecharModalMatricula();
                    setTimeout(function(){ location.reload(); }, 800);
                    return;
                }
                if (res.json && res.json.error) {
                    msg.textContent = res.json.error;
                    msg.className = 'mt-2 text-sm text-red-700';
                    msg.classList.remove('hidden');
                    return;
                }
                msg.textContent = res.raw ? ('Erro HTTP ' + res.status + ' (resposta não é JSON).') : ('Erro HTTP ' + res.status + '.');
                msg.className = 'mt-2 text-sm text-red-700';
                msg.classList.remove('hidden');
            } catch (err) {
                msg.textContent = 'Falha de rede ou bloqueio do navegador. Se o site é HTTPS, confira se em configurações a URL base também usa HTTPS.';
                msg.className = 'mt-2 text-sm text-red-700';
                msg.classList.remove('hidden');
            }
        });
        document.getElementById('btnSyncMatriculaCadastro')?.addEventListener('click', async function() {
            if (!confirm('Encerrar matrículas ativas que não forem a turma do cadastro e registrar a matrícula na turma correta?')) return;
            var el = document.getElementById('syncMatriculaMsg');
            if (el) { el.classList.add('hidden'); }
            var fd = new FormData();
            fd.append('_token', document.getElementById('csrf_token').value);
            try {
                var res = await ehFetchJsonMatricula('<?= htmlspecialchars($ehMatriculaSyncRel, ENT_QUOTES, 'UTF-8') ?>', fd);
                if (res.json && res.json.success) {
                    if (el) { el.textContent = res.json.message; el.className = 'mt-2 text-sm text-green-800'; el.classList.remove('hidden'); }
                    setTimeout(function(){ location.reload(); }, 900);
                    return;
                }
                if (el) {
                    el.textContent = (res.json && res.json.error) ? res.json.error : ('Erro HTTP ' + res.status + '.');
                    el.className = 'mt-2 text-sm text-red-700';
                    el.classList.remove('hidden');
                }
            } catch (err) {
                if (el) {
                    el.textContent = 'Falha de rede. Verifique URL HTTPS nas configurações.';
                    el.className = 'mt-2 text-sm text-red-700';
                    el.classList.remove('hidden');
                }
            }
        });
        </script>
        <?php else: ?>
        <div class="student-card min-w-0">
            <div class="student-card-header flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center">
                    <i class="fa-solid fa-clock-rotate-left text-slate-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Histórico de Turmas</h3>
                    <p class="text-sm text-slate-500">Registros por ano letivo</p>
                </div>
            </div>
            <div class="student-card-body">
                <?php if (empty($historico_turmas)): ?>
                    <p class="text-slate-500 text-sm">Nenhum histórico registrado.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                                    <th class="py-2 pr-4">Turma</th>
                                    <th class="py-2 pr-4">Ano</th>
                                    <th class="py-2 pr-4">Início</th>
                                    <th class="py-2 pr-4">Fim</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($historico_turmas as $hist): ?>
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-slate-900"><?= safe_htmlspecialchars($hist['turma_nome'] ?? '') ?></td>
                                    <td class="py-2 pr-4 text-slate-600"><?= safe_htmlspecialchars($hist['ano_letivo'] ?? '') ?></td>
                                    <td class="py-2 pr-4 text-slate-600"><?= format_data_br($hist['data_inicio'] ?? null, '') ?></td>
                                    <td class="py-2 pr-4 text-slate-600"><?= format_data_br($hist['data_fim'] ?? null, 'Atual') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div><!-- fim student-duo-grid -->
</div><!-- fim student-page -->

<!-- Relatório Detalhado -->
<div id="section-relatorio-detalhado" class="mt-8">
    <div class="student-card overflow-hidden">
        <div class="student-card-header">
            <h3 class="text-lg font-semibold text-slate-900">Relatório Detalhado</h3>
        </div>
        <div class="border-b border-slate-200 px-4 student-tabs-nav-scroll">
            <nav class="student-tabs-nav -mb-px py-1" aria-label="Tabs">
                <button onclick="showTab('relatorio')" id="tab-relatorio" data-tab-perm-key="tab_relatorio" class="tab-button active flex items-center px-4 py-3 text-sm font-medium border-b-2 border-blue-500 text-blue-600 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Relatório
                </button>
                <button onclick="showTab('redacoes')" id="tab-redacoes" data-tab-perm-key="tab_redacao" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Jornada de Redação
                </button>
                <button onclick="showTab('ocorrencias')" id="tab-ocorrencias" data-tab-perm-key="tab_ocorrencias" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Ocorrências
                </button>
                <button onclick="showTab('jornadas')" id="tab-jornadas" data-tab-perm-key="tab_jornadas" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    Jornadas
                </button>
                <button onclick="showTab('provas')" id="tab-provas" data-tab-perm-key="tab_provas" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Provas
                </button>
                <button onclick="showTab('notas-eventos')" id="tab-notas-eventos" data-tab-perm-key="tab_notas" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4V7m-9 8h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    Notas
                </button>
                <button onclick="showTab('boletim-eventos')" id="tab-boletim-eventos" data-tab-perm-key="tab_boletim" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Boletim
                </button>
                <button onclick="showTab('acessos')" id="tab-acessos" data-tab-perm-key="tab_acessos" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Acessos
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Tab: Relatório -->
            <div id="content-relatorio" class="tab-content">
                <div class="student-metrics-row">
                    <div class="student-metric-card border-l-red-500">
                        <div class="text-xs font-medium text-slate-500 mb-1 leading-tight">Jornada de Redação</div>
                        <div class="text-2xl font-bold text-slate-900"><?= $stats['redacoes_total'] ?? 0 ?></div>
                        <div class="text-[11px] text-slate-500 mt-1"><?= $stats['redacoes_corrigidas'] ?? 0 ?> corr. | <?= number_format($stats['media_redacoes'] ?? 0, 1) ?></div>
                    </div>
                    <div class="student-metric-card border-l-green-500">
                        <div class="text-xs font-medium text-slate-500 mb-1 leading-tight">Jornadas</div>
                        <div class="text-2xl font-bold text-slate-900"><?= $stats['jornadas_concluidas'] ?? 0 ?></div>
                        <div class="text-[11px] text-slate-500 mt-1">Trilhas completas</div>
                    </div>
                    <div class="student-metric-card border-l-slate-400">
                        <div class="text-xs font-medium text-slate-500 mb-1 leading-tight">Mural de recados</div>
                        <div class="text-2xl font-bold text-slate-900"><?= $stats['mural_recados_vistos'] ?? 0 ?> <span class="text-lg font-semibold text-slate-400">/</span> <?= $stats['mural_recados_total'] ?? 0 ?></div>
                        <div class="text-[11px] text-slate-500 mt-1"><?= (int)($stats['mural_recados_total'] ?? 0) > 0 && (int)($stats['mural_recados_vistos'] ?? 0) > 0 ? 'Está lendo' : 'Não está lendo' ?></div>
                    </div>
                </div>

            </div>

            <!-- Tab: Exercícios Banco de Dados -->
            <div id="content-exercicios-bd" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Exercícios do Banco de Dados</h3>
                    <span class="text-sm text-gray-500"><?= count($exercicios_bd ?? []) ?> exercícios encontrados</span>
                </div>
                
                <?php if (empty($exercicios_bd)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-500">Nenhum exercício encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($exercicios_bd as $exercicio): ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="text-lg font-semibold text-gray-900">
                                                <?= safe_htmlspecialchars($exercicio['titulo'] ?? null, 'Exercício') ?>
                                            </h4>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                Finalizado
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">Matéria:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= safe_htmlspecialchars($exercicio['materia'] ?? null, 'N/A') ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Questões:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= $exercicio['questoes_corretas'] ?? 0 ?>/<?= $exercicio['questoes_total'] ?? 0 ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Acertos:</span>
                                                <span class="font-medium text-green-600 ml-1">
                                                    <?= $exercicio['questoes_corretas'] ?? 0 ?>
                                                    <?php
                                                        $totalQuestoes = $exercicio['questoes_total'] ?? 0;
                                                        $acertos = $exercicio['questoes_corretas'] ?? 0;
                                                        $percentualAcerto = ($totalQuestoes > 0 && $acertos > 0) ? round(($acertos / $totalQuestoes) * 100) : ($exercicio['percentual_acerto'] ?? 0);
                                                    ?>
                                                    <?php if ($totalQuestoes > 0): ?>
                                                        <span class="text-blue-600 font-semibold ml-1">(<?= number_format($percentualAcerto, 1) ?>%)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Realizado em:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= !empty($exercicio['created_at']) ? date('d/m/Y H:i', strtotime($exercicio['created_at'])) : '' ?></span>
                                            </div>
                                        </div>
                                        <?php if (!empty($exercicio['data_fim'])): ?>
                                            <div class="mt-2 text-sm text-gray-600">
                                                <strong>Finalizado em:</strong> <?= !empty($exercicio['data_fim']) ? date('d/m/Y H:i', strtotime($exercicio['data_fim'])) : 'N/A' ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Exercícios IA -->
            <div id="content-exercicios-ia" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Exercícios Gerados por IA</h3>
                    <span class="text-sm text-gray-500"><?= count($exercicios_ia ?? []) ?> sessões encontradas</span>
                </div>

                <!-- Listas do aluno (permite excluir listas em erro/gerando) -->
                <?php $listas_pers = $listas_personalizadas_aluno ?? []; ?>
                <?php if (!empty($listas_pers)): ?>
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">Listas de exercícios personalizados do aluno</h4>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lista</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sessões</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Criada em</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($listas_pers as $lp): ?>
                                <tr class="lista-personalizada-row" data-lista-id="<?= (int)$lp['id'] ?>">
                                    <td class="px-4 py-2 text-sm text-gray-900"><?= safe_htmlspecialchars($lp['titulo'] ?? '', '—') ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= safe_htmlspecialchars($lp['materia'] ?? '', '—') ?></td>
                                    <td class="px-4 py-2">
                                        <?php
                                        $st = $lp['status'] ?? '';
                                        $badge = $st === 'concluido' ? 'bg-green-100 text-green-800' : ($st === 'gerando' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        $label = $st === 'concluido' ? 'Pronta' : ($st === 'gerando' ? 'Gerando' : 'Erro');
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $badge ?>"><?= $label ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= (int)($lp['total_sessoes'] ?? 0) ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= !empty($lp['created_at']) ? date('d/m/Y H:i', strtotime($lp['created_at'])) : '—' ?></td>
                                    <td class="px-4 py-2 text-right">
                                        <form method="post" action="<?= URL ?>/admin/students/excluir-lista-exercicio-ia" class="inline" onsubmit="return confirm('Excluir esta lista? Esta ação não pode ser desfeita.');">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                            <input type="hidden" name="lista_id" value="<?= (int)$lp['id'] ?>">
                                            <input type="hidden" name="aluno_id" value="<?= (int)($student['id'] ?? 0) ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <h4 class="text-lg font-semibold text-gray-800 mb-3">Exercícios realizados (sessões)</h4>
                
                <?php if (empty($exercicios_ia)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        <p class="text-gray-500">Nenhum exercício IA encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($exercicios_ia as $exercicio): ?>
                            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="text-lg font-semibold text-gray-900">
                                                <?= safe_htmlspecialchars($exercicio['lista_titulo'] ?? null, 'Exercício IA') ?>
                                            </h4>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $exercicio['status'] === 'finalizado' ? 'bg-green-100 text-green-800' : ($exercicio['status'] === 'em_andamento' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                                <?= ucfirst($exercicio['status'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">Matéria:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= safe_htmlspecialchars($exercicio['materia'] ?? null, 'N/A') ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Questões:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= $exercicio['total_respostas'] ?? 0 ?>/<?= $exercicio['quantidade_exercicios'] ?? 0 ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Acertos:</span>
                                                <span class="font-medium text-green-600 ml-1">
                                                    <?= $exercicio['acertos'] ?? 0 ?>
                                                    <?php
                                                        $totalQuestoes = $exercicio['quantidade_exercicios'] ?? 0;
                                                        $acertos = $exercicio['acertos'] ?? 0;
                                                        $percentualAcerto = ($totalQuestoes > 0 && $acertos > 0) ? round(($acertos / $totalQuestoes) * 100) : 0;
                                                    ?>
                                                    <?php if ($totalQuestoes > 0 && $acertos > 0): ?>
                                                        <span class="text-blue-600 font-semibold ml-1">(<?= $percentualAcerto ?>%)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Iniciado:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= !empty($exercicio['started_at']) ? date('d/m/Y H:i', strtotime($exercicio['started_at'])) : '' ?></span>
                                            </div>
                                        </div>
                                        <?php if ($exercicio['status'] === 'finalizado' && $exercicio['finished_at']): ?>
                                            <div class="mt-2 text-sm text-gray-600">
                                                <strong>Finalizado em:</strong> <?= !empty($exercicio['finished_at']) ? date('d/m/Y H:i', strtotime($exercicio['finished_at'])) : 'N/A' ?>
                                                <?php if ($exercicio['tempo_gasto']): ?>
                                                    | <strong>Tempo:</strong> <?= gmdate('H:i:s', $exercicio['tempo_gasto']) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <a href="<?= URL ?>/admin/students/exercicio-ia/<?= $exercicio['id'] ?>" 
                                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            Ver Detalhes
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Jornada de Redação -->
            <div id="content-redacoes" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Jornada de Redação</h3>
                    <span class="text-sm text-gray-500"><?= count($redacoes ?? []) ?> redações encontradas</span>
                </div>
                
                <?php if (empty($redacoes)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                        <p class="text-gray-500">Nenhuma redação encontrada</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($redacoes as $index => $redacao): ?>
                            <div class="bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                                <!-- Header Clicável -->
                                <button onclick="toggleRedacaoDetails(<?= $redacao['id'] ?>)" class="w-full text-left p-6 flex items-center justify-between hover:bg-gray-50 transition-colors">
                                    <div class="flex-1">
                                        <div class="flex items-center flex-wrap gap-3 mb-2">
                                            <h4 class="text-lg font-bold text-gray-900">
                                                <?= safe_htmlspecialchars($redacao['tema'] ?? null, 'Sem tema') ?>
                                            </h4>
                                            <?php 
                                                $estaCorrigida = !empty($redacao['corrigida_em']) || 
                                                                !empty($redacao['correcao']) || 
                                                                !empty($redacao['feedback_ia']) || 
                                                                !empty($redacao['nota']) || 
                                                                !empty($redacao['nota_final']);
                                            ?>
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $estaCorrigida ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                                <?= safe_htmlspecialchars($redacao['status_descricao'] ?? null, 'Pendente') ?>
                                            </span>
                                            <?php 
                                                $notaExibir = $redacao['nota'] ?? $redacao['nota_final'] ?? null;
                                                if ($notaExibir): 
                                            ?>
                                                <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    Nota: <?= number_format($notaExibir, 1) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                                            <div>
                                                <span class="font-medium text-gray-700">Criada em:</span>
                                                <span class="ml-1"><?= !empty($redacao['created_at']) ? date('d/m/Y H:i', strtotime($redacao['created_at'])) : '' ?></span>
                                            </div>
                                            <?php if (!empty($redacao['texto'])): ?>
                                                <?php 
                                                    $texto_limpo = strip_tags($redacao['texto']);
                                                    $palavras = str_word_count($texto_limpo);
                                                ?>
                                                <div>
                                                    <span class="font-medium text-gray-700">Palavras:</span>
                                                    <span class="ml-1"><?= $palavras ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($redacao['updated_at'] && $redacao['updated_at'] != $redacao['created_at']): ?>
                                                <div>
                                                    <span class="font-medium text-gray-700">Atualizada:</span>
                                                    <span class="ml-1"><?= !empty($redacao['updated_at']) ? date('d/m/Y H:i', strtotime($redacao['updated_at'])) : '' ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <svg id="arrow-<?= $redacao['id'] ?>" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <!-- Conteúdo Expansível -->
                                <div id="redacao-detalhes-<?= $redacao['id'] ?>" class="hidden border-t">
                                    <div class="p-6">
                                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                                            <!-- Coluna 8: Redação -->
                                            <div class="lg:col-span-8">
                                                <?php if (!empty($redacao['texto'])): ?>
                                                    <div class="mb-6">
                                                        <h5 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Redação:</h5>
                                                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 text-base text-gray-800 leading-relaxed whitespace-pre-wrap">
                                                            <?= nl2br(safe_htmlspecialchars($redacao['texto'] ?? null, '')) ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Coluna 4: Resultado -->
                                            <div class="lg:col-span-4 space-y-4">
                                                <!-- Correção -->
                                                <?php if (!empty($redacao['correcao'])): ?>
                                                    <div>
                                                        <h5 class="text-sm font-semibold text-blue-700 mb-2 uppercase tracking-wide">Correção:</h5>
                                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900 leading-relaxed whitespace-pre-wrap max-h-96 overflow-y-auto">
                                                            <?= nl2br(safe_htmlspecialchars($redacao['correcao'] ?? null, '')) ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Feedback IA -->
                                                <?php if (!empty($redacao['feedback_ia'])): ?>
                                                    <div>
                                                        <h5 class="text-sm font-semibold text-purple-700 mb-2 uppercase tracking-wide">Feedback da IA:</h5>
                                                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-xs text-purple-900 leading-relaxed whitespace-pre-wrap max-h-96 overflow-y-auto">
                                                            <?php 
                                                                // Tentar decodificar JSON se for JSON
                                                                $feedback = $redacao['feedback_ia'];
                                                                $decoded = json_decode($feedback, true);
                                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                                    echo '<div class="space-y-2">';
                                                                    if (isset($decoded['comentarios_gerais'])) {
                                                                        echo '<div class="mb-3"><strong>Comentários Gerais:</strong><br>' . nl2br(safe_htmlspecialchars($decoded['comentarios_gerais'] ?? null, '')) . '</div>';
                                                                    }
                                                                    if (isset($decoded['sugestoes_melhoria'])) {
                                                                        echo '<div class="mb-3"><strong>Sugestões de Melhoria:</strong><br>' . nl2br(safe_htmlspecialchars($decoded['sugestoes_melhoria'] ?? null, '')) . '</div>';
                                                                    }
                                                                    echo '</div>';
                                                                } else {
                                                                    echo nl2br(safe_htmlspecialchars(is_array($feedback) ? '' : (string)($feedback ?? ''), ''));
                                                                }
                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Competências -->
                                                <?php 
                                                    // Buscar feedback da IA se existir
                                                    $feedback = null;
                                                    if (!empty($redacao['feedback_ia'])) {
                                                        $feedbackDecoded = json_decode($redacao['feedback_ia'], true);
                                                        if (json_last_error() === JSON_ERROR_NONE) {
                                                            $feedback = $feedbackDecoded;
                                                        } else {
                                                            $feedback = $redacao['feedback_ia'];
                                                        }
                                                    }
                                                    
                                                    $competencias = [
                                                        1 => ['nome' => 'Domínio da norma padrão da Língua Portuguesa', 'nota' => $redacao['competencia_1'] ?? null],
                                                        2 => ['nome' => 'Compreensão da proposta e desenvolvimento do tema', 'nota' => $redacao['competencia_2'] ?? null],
                                                        3 => ['nome' => 'Seleção e organização de argumentos', 'nota' => $redacao['competencia_3'] ?? null],
                                                        4 => ['nome' => 'Coesão e coerência', 'nota' => $redacao['competencia_4'] ?? null],
                                                        5 => ['nome' => 'Proposta de intervenção', 'nota' => $redacao['competencia_5'] ?? null]
                                                    ];
                                                    
                                                    $tem_competencia = false;
                                                    foreach ($competencias as $comp) {
                                                        if ($comp['nota'] !== null && $comp['nota'] !== '') {
                                                            $tem_competencia = true;
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                
                                                <?php if ($tem_competencia): ?>
                                                    <div>
                                                        <h5 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Competências:</h5>
                                                        <div class="space-y-3">
                                                            <?php foreach ($competencias as $num => $comp): ?>
                                                                <?php if ($comp['nota'] !== null && $comp['nota'] !== ''): ?>
                                                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                                                                        <div class="flex justify-between items-center mb-2">
                                                                            <span class="text-sm font-medium text-gray-900">
                                                                                Competência <?= $num ?>
                                                                            </span>
                                                                            <span class="text-sm font-bold text-blue-600">
                                                                                <?= $comp['nota'] ?>/200
                                                                            </span>
                                                                        </div>
                                                                        <div class="text-xs text-gray-600 mb-2">
                                                                            <?= safe_htmlspecialchars($comp['nome'] ?? null, '') ?>
                                                                        </div>
                                                                        <?php if ($feedback && isset($feedback["competencia_$num"]['explicacao'])): ?>
                                                                            <div class="text-xs text-gray-700 bg-white border border-gray-200 p-2 rounded mt-2">
                                                                                <?= nl2br(safe_htmlspecialchars($feedback["competencia_$num"]['explicacao'] ?? null, '')) ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($redacao['corrigida_em'])): ?>
                                                    <div class="text-xs text-gray-500 text-center pt-2 border-t">
                                                        Corrigida em: <?= !empty($redacao['corrigida_em']) ? date('d/m/Y H:i', strtotime($redacao['corrigida_em'])) : 'N/A' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Ocorrências -->
            <div id="content-ocorrencias" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Ocorrências do aluno</h3>
                    <span class="text-sm text-gray-500"><?= count($ocorrencias ?? []) ?> registros</span>
                </div>

                <?php if (empty($ocorrencias)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <p class="text-gray-500">Nenhuma ocorrência registrada.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($ocorrencias as $oc): ?>
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-base font-semibold text-gray-900"><?= safe_htmlspecialchars($oc['titulo'] ?? '', '') ?></h4>
                                        <p class="text-xs text-gray-500 mt-1"><?= date('d/m/Y H:i', strtotime($oc['data_ocorrencia'])) ?></p>
                                    </div>
                                    <span class="text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                        <?= ucfirst($oc['nivel_gravidade'] ?? '') ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700 mt-3"><?= safe_htmlspecialchars($oc['detalhe'] ?? '', '') ?></p>
                                <div class="text-xs text-gray-500 mt-3 flex flex-wrap gap-4">
                                    <div>Atitude: <?= $oc['atitude_coordenacao'] ? ucfirst($oc['atitude_coordenacao']) : '-' ?></div>
                                    <div>Retorno: <?= !empty($oc['retorno_em']) ? date('d/m/Y', strtotime($oc['retorno_em'])) : '-' ?></div>
                                    <div>Pais: <?= !empty($oc['enviar_pais']) ? 'Sim' : 'Não' ?></div>
                                    <div>Registrado por: <?= safe_htmlspecialchars($oc['criado_por_nome'] ?? 'Admin', '') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Jornadas -->
            <div id="content-jornadas" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Jornadas feitas</h3>
                    <span class="text-sm text-gray-500"><?= count($jornadas_feitas ?? []) ?> jornadas concluídas</span>
                </div>
                <?php if (empty($jornadas_feitas)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <p class="text-gray-500">Nenhuma jornada concluída.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jornada</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data conclusão</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Detalhes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($jornadas_feitas as $jf): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900"><?= safe_htmlspecialchars($jf['titulo'] ?? '', '—') ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?= !empty($jf['data_conclusao']) ? date('d/m/Y H:i', strtotime($jf['data_conclusao'])) : '—' ?></td>
                                        <td class="px-4 py-2 text-sm">
                                            <?php if (!empty($jf['id']) && !empty($student['id'])): ?>
                                                <a href="<?= URL ?>/admin/jornadas/<?= (int)$jf['id'] ?>/aluno/<?= (int)$student['id'] ?>/exercicios"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-xs font-medium transition-colors">
                                                    Ver respostas (acertos/erros)
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Provas -->
            <div id="content-provas" class="tab-content hidden" data-lazy-tab="provas" data-lazy-loaded="0">
                <div class="text-center py-12 text-gray-400">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando provas...
                </div>
            </div>

            <!-- Tab: Notas -->
            <div id="content-notas-eventos" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Eventos de Notas</h3>
                    <span class="text-sm text-gray-500"><?= count($boletim_eventos_notas) ?> evento(s)</span>
                </div>

                <?php if (empty($boletim_eventos_notas)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-gray-500">Nenhum evento de notas visível para coordenação.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($boletim_eventos_notas as $ev): ?>
                            <?php
                                $ridNota = (int)($ev['id'] ?? 0);
                                $geradoNota = $ridNota > 0 ? ($boletins_gerados_notas_por_regra[$ridNota] ?? null) : null;
                                $updatedFmt = !empty($ev['updated_at']) ? date('d/m/Y H:i', strtotime((string)$ev['updated_at'])) : '-';
                            ?>
                            <div class="border border-gray-200 rounded-lg p-4 bg-white">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-base font-semibold text-gray-900"><?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento') ?></div>
                                        <div class="text-sm text-gray-600">
                                            <?php $bimestreNota = $ev['bimestre'] ?? null; ?>
                                            <?php $anoNota = $ev['ano_letivo'] ?? null; ?>
                                            Bimestre: <?= $bimestreNota ? ((int) $bimestreNota . 'º') : 'N/A' ?>
                                            | Ano: <?= $anoNota ? (int) $anoNota : 'N/A' ?>
                                            | Atualizado: <?= safe_htmlspecialchars($updatedFmt, '-') ?>
                                        </div>
                                    </div>
                                    <?php if (is_array($geradoNota) && !empty($geradoNota['linhas'])): ?>
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                                                data-notas-title="<?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento de Notas') ?>"
                                                onclick="abrirModalNotasEvento('modal-notas-evento-<?= $ridNota ?>', this)">
                                                Abrir notas
                                            </button>
                                            <button
                                                type="button"
                                                class="px-3 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700"
                                                data-notas-title="<?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento de Notas') ?>"
                                                onclick="imprimirNotasEvento('modal-notas-evento-<?= $ridNota ?>', this)">
                                                Imprimir
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-amber-700">Sem tabela gerada no banco para este evento.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (is_array($geradoNota) && !empty($geradoNota['linhas'])): ?>
                                <div id="modal-notas-evento-<?= $ridNota ?>" class="hidden">
                                    <?php
                                    $boletinsGeradosBackup = $boletins_gerados;
                                    $boletimPodeExcluirBackup = $boletim_pode_excluir ?? false;
                                    $boletimAlunoIdBackup = $boletim_aluno_id ?? 0;
                                    $boletins_gerados = [$geradoNota];
                                    $boletim_pode_excluir = false;
                                    $boletim_aluno_id = 0;
                                    require __DIR__ . '/../../partials/boletins_gerados.php';
                                    $boletins_gerados = $boletinsGeradosBackup;
                                    $boletim_pode_excluir = $boletimPodeExcluirBackup;
                                    $boletim_aluno_id = $boletimAlunoIdBackup;
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Modal genérico de abrir/imprimir conteúdo (usado pelas abas Provas e Notas) -->
            <div id="modal-notas-evento" class="hidden fixed inset-0 z-50 p-4 sm:p-6">
                <div class="absolute inset-0 bg-black/50" onclick="fecharModalNotasEvento()"></div>
                <div class="relative bg-white rounded-xl border border-gray-200 shadow-xl max-w-6xl mx-auto h-[85vh] flex flex-col overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h4 id="modal-notas-evento-title" class="text-base font-semibold text-gray-900">Notas do evento</h4>
                        <div class="flex items-center gap-2">
                            <button type="button" class="px-3 py-1.5 text-sm rounded-md bg-emerald-600 hover:bg-emerald-700 text-white" onclick="imprimirNotasModalAtual()">Imprimir</button>
                            <button type="button" class="px-3 py-1.5 text-sm rounded-md bg-gray-200 hover:bg-gray-300 text-gray-700" onclick="fecharModalNotasEvento()">Fechar</button>
                        </div>
                    </div>
                    <div id="modal-notas-evento-body" class="w-full flex-1 overflow-y-auto p-4 bg-gray-50"></div>
                </div>
            </div>

            <!-- Tab: Boletim -->
            <div id="content-boletim-eventos" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-3xl font-bold text-gray-900">Boletim</h2>
                    <?php if (!empty($boletins_gerados)): ?>
                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/boletim/pdf"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-sm shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                            </svg>
                            Baixar PDF
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($boletins_gerados)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-gray-500">Nenhum boletim gerado para este aluno ainda.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $boletim_pode_excluir = (bool) ($boletim_pode_excluir ?? false);
                    $boletim_aluno_id = (int) ($student['id'] ?? 0);
                    $boletim_csrf_token = (string) ($csrf_token ?? '');
                    require __DIR__ . '/../../partials/boletins_gerados.php';
                    ?>

                    <?php
                    $obsConteudo = (string) (($boletim_observacao['conteudo'] ?? '') ?: '');
                    $obsTokenInit = htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div id="boletim-observacao-block"
                         class="mt-6 rounded-xl border border-gray-200 bg-white p-5"
                         data-aluno-id="<?= (int) ($student['id'] ?? 0) ?>"
                         data-csrf-token="<?= $obsTokenInit ?>"
                         data-endpoint="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/boletim/observacao">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-base font-semibold text-gray-900">Observação</h3>
                            <button type="button"
                                    id="btn-editar-observacao"
                                    class="<?= $obsConteudo === '' ? 'hidden' : '' ?> text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                                Editar
                            </button>
                        </div>

                        <div id="observacao-view" class="<?= $obsConteudo === '' ? 'hidden' : '' ?>">
                            <p id="observacao-texto" class="text-sm text-gray-800 whitespace-pre-wrap break-words"><?= htmlspecialchars($obsConteudo, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <div id="observacao-edit" class="<?= $obsConteudo === '' ? '' : 'hidden' ?> space-y-3">
                            <textarea id="observacao-textarea"
                                      rows="5"
                                      maxlength="5000"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                      placeholder="Escreva uma observação que ficará no boletim e no PDF…"><?= htmlspecialchars($obsConteudo, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        id="btn-salvar-observacao"
                                        class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">
                                    Salvar
                                </button>
                                <button type="button"
                                        id="btn-cancelar-observacao"
                                        class="<?= $obsConteudo === '' ? 'hidden' : '' ?> px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-medium">
                                    Cancelar
                                </button>
                                <span id="observacao-status" class="text-xs text-gray-500"></span>
                            </div>
                        </div>
                    </div>

                    <script>
                    (function () {
                        var block = document.getElementById('boletim-observacao-block');
                        if (!block) return;
                        var viewEl = document.getElementById('observacao-view');
                        var editEl = document.getElementById('observacao-edit');
                        var textoEl = document.getElementById('observacao-texto');
                        var taEl = document.getElementById('observacao-textarea');
                        var btnEditar = document.getElementById('btn-editar-observacao');
                        var btnSalvar = document.getElementById('btn-salvar-observacao');
                        var btnCancelar = document.getElementById('btn-cancelar-observacao');
                        var statusEl = document.getElementById('observacao-status');
                        var endpoint = block.getAttribute('data-endpoint') || '';
                        var csrf = block.getAttribute('data-csrf-token') || '';
                        var ultimoSalvo = (textoEl && textoEl.textContent) ? textoEl.textContent : '';

                        function entrarEdicao() {
                            if (viewEl) viewEl.classList.add('hidden');
                            if (editEl) editEl.classList.remove('hidden');
                            if (btnEditar) btnEditar.classList.add('hidden');
                            if (btnCancelar) btnCancelar.classList.toggle('hidden', ultimoSalvo.trim() === '');
                            if (taEl) {
                                taEl.value = ultimoSalvo;
                                taEl.focus();
                            }
                        }

                        function sairEdicao() {
                            if (textoEl) textoEl.textContent = ultimoSalvo;
                            var temConteudo = ultimoSalvo.trim() !== '';
                            if (viewEl) viewEl.classList.toggle('hidden', !temConteudo);
                            if (editEl) editEl.classList.toggle('hidden', temConteudo);
                            if (btnEditar) btnEditar.classList.toggle('hidden', !temConteudo);
                            if (btnCancelar) btnCancelar.classList.toggle('hidden', !temConteudo);
                        }

                        function salvar() {
                            if (!taEl) return;
                            var conteudo = taEl.value || '';
                            statusEl.textContent = 'Salvando…';
                            statusEl.classList.remove('text-red-600');
                            statusEl.classList.add('text-gray-500');
                            var form = new FormData();
                            form.append('_token', csrf);
                            form.append('conteudo', conteudo);
                            fetch(endpoint, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                                body: form,
                            }).then(function (resp) {
                                return resp.json().then(function (data) { return { ok: resp.ok, data: data }; });
                            }).then(function (res) {
                                if (!res.ok || !res.data || res.data.success !== true) {
                                    var msg = (res.data && res.data.error) ? res.data.error : 'Falha ao salvar.';
                                    statusEl.textContent = msg;
                                    statusEl.classList.remove('text-gray-500');
                                    statusEl.classList.add('text-red-600');
                                    return;
                                }
                                ultimoSalvo = (res.data.conteudo !== undefined ? String(res.data.conteudo) : conteudo);
                                statusEl.textContent = 'Salvo.';
                                sairEdicao();
                                setTimeout(function () { statusEl.textContent = ''; }, 1800);
                            }).catch(function (err) {
                                statusEl.textContent = 'Falha de rede.';
                                statusEl.classList.remove('text-gray-500');
                                statusEl.classList.add('text-red-600');
                                console.error(err);
                            });
                        }

                        if (btnEditar) btnEditar.addEventListener('click', entrarEdicao);
                        if (btnSalvar) btnSalvar.addEventListener('click', salvar);
                        if (btnCancelar) btnCancelar.addEventListener('click', sairEdicao);
                    })();
                    </script>
                <?php endif; ?>
            </div>

            <!-- Tab: Acessos -->
            <div id="content-acessos" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Histórico de acesso</h3>
                    <span class="text-sm text-gray-500"><?= count($historico_acesso ?? []) ?> acessos</span>
                </div>
                <?php if (empty($historico_acesso)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <p class="text-gray-500">Nenhum registro de acesso (logins com sucesso) encontrado para o RA deste aluno.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data e hora</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($historico_acesso as $ha): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900"><?= !empty($ha['created_at']) ? date('d/m/Y H:i:s', strtotime($ha['created_at'])) : '—' ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?= safe_htmlspecialchars($ha['ip_address'] ?? '', '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal inativação -->
<div id="modalInativarAluno" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Inativar aluno</h3>
        <p class="text-sm text-gray-600 mb-4">Registra motivo, encerra matrículas e preserva histórico. Use <strong>TRANSFERENCIA</strong> para marcar TR na lista de chamada.</p>
        <form id="formInativarAluno" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                <select name="reason" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="TRANSFERENCIA">Transferência (TR)</option>
                    <option value="EVASAO">Evasão</option>
                    <option value="CONCLUSAO">Conclusão</option>
                    <option value="ADMINISTRATIVO">Administrativo</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                <textarea name="observation" rows="2" required class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
                <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg px-3 py-2" autocomplete="current-password">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Digite CONFIRMAR</label>
                <input type="text" name="confirm_text" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="CONFIRMAR">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="fecharModalInativarAluno()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700">Confirmar inativação</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal exclusão (soft-delete: só oculta da visualização) -->
<div id="modalExcluirAluno" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Excluir aluno</h3>
        <p class="text-sm text-gray-600 mb-4">O aluno será <strong>ocultado da visualização</strong>. Os dados <strong>não são apagados</strong> do banco e podem ser recuperados depois.</p>
        <form id="formExcluirAluno" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação (opcional)</label>
                <textarea name="observation" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Motivo da exclusão"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
                <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg px-3 py-2" autocomplete="current-password">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Digite CONFIRMAR</label>
                <input type="text" name="confirm_text" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="CONFIRMAR">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="fecharModalExcluirAluno()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Confirmar exclusão</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ativação -->
<div id="modalAtivarAluno" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reativar aluno</h3>
        <form id="formAtivarAluno" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observação (opcional)</label>
                <textarea name="observation" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">Reativação administrativa</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Digite CONFIRMAR</label>
                <input type="text" name="confirm_text" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="CONFIRMAR">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="fecharModalAtivarAluno()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</button>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
const alunoStatusId = <?= (int) ($student['id'] ?? 0) ?>;

function abrirModalInativarAluno() {
    document.getElementById('modalInativarAluno')?.classList.remove('hidden');
}
function fecharModalInativarAluno() {
    document.getElementById('modalInativarAluno')?.classList.add('hidden');
}
function abrirModalExcluirAluno() {
    document.getElementById('modalExcluirAluno')?.classList.remove('hidden');
}
function fecharModalExcluirAluno() {
    document.getElementById('modalExcluirAluno')?.classList.add('hidden');
}
function abrirModalAtivarAluno() {
    document.getElementById('modalAtivarAluno')?.classList.remove('hidden');
}
function fecharModalAtivarAluno() {
    document.getElementById('modalAtivarAluno')?.classList.add('hidden');
}
function abrirModalMatricula() {
    var form = document.getElementById('formAddMatricula');
    var msg = document.getElementById('matriculaMsg');
    if (form) form.reset();
    var dataEntrada = document.getElementById('mat_data_entrada');
    if (dataEntrada) dataEntrada.value = '<?= date('Y-m-d') ?>';
    if (msg) msg.classList.add('hidden');
    if (typeof atualizarCheckboxTurmaPrincipalMatricula === 'function') {
        atualizarCheckboxTurmaPrincipalMatricula();
    }
    document.getElementById('modalAddMatricula')?.classList.remove('hidden');
}
function fecharModalMatricula() {
    document.getElementById('modalAddMatricula')?.classList.add('hidden');
}

function extrairMensagemErroServidor(raw, httpStatus) {
    const texto = (raw || '').toString().trim();
    if (texto === '') {
        return 'Resposta vazia do servidor (HTTP ' + httpStatus + ')';
    }
    if (texto.startsWith('{') || texto.startsWith('[')) {
        return texto.slice(0, 300);
    }
    const titleMatch = texto.match(/<title>([^<]+)<\/title>/i);
    if (titleMatch && titleMatch[1]) {
        return 'Erro no servidor: ' + titleMatch[1].trim() + ' (HTTP ' + httpStatus + ')';
    }
    const bodyMatch = texto.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
    if (bodyMatch && bodyMatch[1]) {
        const limpo = bodyMatch[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        if (limpo !== '') {
            return limpo.slice(0, 220);
        }
    }
    return 'Erro inesperado do servidor (HTTP ' + httpStatus + ')';
}

async function parseRespostaJsonFetch(response) {
    const raw = await response.text();
    let data = {};
    try {
        data = raw ? JSON.parse(raw) : {};
    } catch (err) {
        throw new Error(extrairMensagemErroServidor(raw, response.status));
    }
    if (!response.ok && !data.error) {
        throw new Error(data.message || ('Falha na requisição (HTTP ' + response.status + ')'));
    }
    return data;
}

document.getElementById('formInativarAluno')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_token', document.getElementById('csrf_token').value);
    fd.append('confirm', '1');
    fetch(`<?= URL ?>/admin/students/${alunoStatusId}/inactivate`, { method: 'POST', body: fd })
        .then(parseRespostaJsonFetch)
        .then(data => {
            if (data.success) location.reload();
            else alert('Erro: ' + (data.error || 'Falha ao inativar'));
        })
        .catch((err) => alert(err.message || 'Erro de conexão'));
});

document.getElementById('formAtivarAluno')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_token', document.getElementById('csrf_token').value);
    fd.append('confirm', '1');
    fetch(`<?= URL ?>/admin/students/${alunoStatusId}/activate`, { method: 'POST', body: fd })
        .then(parseRespostaJsonFetch)
        .then(data => {
            if (data.success) location.reload();
            else alert('Erro: ' + (data.error || 'Falha ao ativar'));
        })
        .catch((err) => alert(err.message || 'Erro de conexão'));
});

document.getElementById('formExcluirAluno')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_token', document.getElementById('csrf_token').value);
    fetch(`<?= URL ?>/admin/students/${alunoStatusId}/excluir`, { method: 'POST', body: fd })
        .then(parseRespostaJsonFetch)
        .then(data => {
            if (data.success) {
                window.location.href = '<?= URL ?>/admin/students';
            } else {
                alert('Erro ao excluir: ' + (data.error || 'Falha ao excluir'));
            }
        })
        .catch((err) => alert(err.message || 'Erro de conexão'));
});

// Função para alterar senha para padrão
function alterarSenhaPadrao(alunoId, alunoNome) {
    console.log('Função alterarSenhaPadrao chamada:', alunoId, alunoNome);
    
    if (!confirm(`Tem certeza que deseja alterar a senha do aluno "${alunoNome}" para a senha padrão (123456)?`)) {
        console.log('Usuário cancelou a operação');
        return;
    }
    
    console.log('Usuário confirmou a operação');
    
    // Criar form data
    const formData = new FormData();
    const tokenElement = document.getElementById('csrf_token');
    if (tokenElement) {
        formData.append('_token', tokenElement.value);
        console.log('Token CSRF:', tokenElement.value);
    } else {
        console.error('Token CSRF não encontrado!');
        alert('Erro: Token de segurança não encontrado');
        return;
    }
    
    // Mostrar loading no botão
    const botao = event.target;
    const textoOriginal = botao.innerHTML;
    botao.innerHTML = '⏳ Alterando...';
    botao.disabled = true;
    
    const url = `<?= URL ?>/admin/students/${alunoId}/password`;
    console.log('Fazendo requisição para:', url);
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Resposta recebida:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Dados recebidos:', data);
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
        
        if (data.success) {
            // Mostrar card de sucesso
            document.getElementById('alunoNomeConfirmacao').textContent = alunoNome;
            document.getElementById('successCard').classList.remove('hidden');
            
            // Scroll para o card de sucesso
            document.getElementById('successCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            alert(`Senha alterada com sucesso! Nova senha: 123456`);
        } else {
            alert('Erro ao alterar senha: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
        alert('Erro de conexão ao alterar senha. Tente novamente.');
    });
}

// Função para copiar senha
function copiarSenha() {
    const senha = '123456';
    navigator.clipboard.writeText(senha).then(() => {
        alert('Senha copiada para a área de transferência!');
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar senha. Tente selecionar e copiar manualmente: 123456');
    });
}

// Função para fechar card de sucesso
function fecharCardSucesso() {
    document.getElementById('successCard').classList.add('hidden');
}

// Função para controlar tabs
const STUDENT_ID_FOR_TABS = <?= (int) ($student['id'] ?? 0) ?>;

function showTab(tabName) {
    // Esconder todos os conteúdos
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(content => {
        content.classList.add('hidden');
    });

    // Remover classe active de todos os botões
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        button.classList.remove('active', 'border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    // Mostrar conteúdo da tab selecionada
    const content = document.getElementById('content-' + tabName);
    if (content) {
        content.classList.remove('hidden');
        carregarAbaSobDemanda(content);
    }

    // Ativar botão da tab selecionada
    const button = document.getElementById('tab-' + tabName);
    if (button) {
        button.classList.add('active', 'border-blue-500', 'text-blue-600');
        button.classList.remove('border-transparent', 'text-gray-500');
    }
}

// Abas marcadas com data-lazy-tab carregam o conteúdo via AJAX só no primeiro
// clique (em vez de virem prontas na carga inicial da página, que era o que
// tornava o Detalhe do Aluno lento — muita informação calculada de uma vez só).
function carregarAbaSobDemanda(content) {
    const lazyTab = content.getAttribute('data-lazy-tab');
    if (!lazyTab || content.getAttribute('data-lazy-loaded') === '1') {
        return;
    }
    content.setAttribute('data-lazy-loaded', '1');
    fetch(<?= json_encode(URL . '/admin/students', JSON_UNESCAPED_SLASHES) ?> + '/' + STUDENT_ID_FOR_TABS + '/tab/' + lazyTab, { credentials: 'same-origin' })
        .then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.text();
        })
        .then(function (html) {
            content.innerHTML = html;
        })
        .catch(function () {
            content.setAttribute('data-lazy-loaded', '0');
            content.innerHTML = '<div class="text-center py-12 text-red-600">Erro ao carregar esta aba. <button type="button" class="underline" onclick="carregarAbaSobDemanda(document.getElementById(\'' + content.id + '\'))">Tentar novamente</button></div>';
        });
}

const notasPrintLogoUrl = <?= json_encode($logoHorizontalPrintUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

function abrirModalNotasEvento(contentId, buttonEl) {
    if (!contentId) return;
    const content = document.getElementById(contentId);
    if (!content) return;
    const title = (buttonEl && buttonEl.getAttribute('data-notas-title')) || 'Notas do evento';
    const modal = document.getElementById('modal-notas-evento');
    const body = document.getElementById('modal-notas-evento-body');
    const titleEl = document.getElementById('modal-notas-evento-title');
    if (!modal || !body || !titleEl) return;
    titleEl.textContent = title;
    body.innerHTML = content.innerHTML;
    modal.classList.remove('hidden');
}

function fecharModalNotasEvento() {
    const modal = document.getElementById('modal-notas-evento');
    const body = document.getElementById('modal-notas-evento-body');
    if (modal) modal.classList.add('hidden');
    if (body) body.innerHTML = '';
}

function imprimirNotasEvento(contentId, buttonEl) {
    if (!contentId) return;
    const content = document.getElementById(contentId);
    if (!content) return;
    const title = (buttonEl && buttonEl.getAttribute('data-notas-title')) || 'Notas do evento';
    imprimirConteudoNotas(title, content.innerHTML);
}

function imprimirNotasModalAtual() {
    const titleEl = document.getElementById('modal-notas-evento-title');
    const body = document.getElementById('modal-notas-evento-body');
    if (!body) return;
    const title = titleEl ? titleEl.textContent : 'Notas do evento';
    imprimirConteudoNotas(title, body.innerHTML);
}

function imprimirConteudoNotas(title, bodyHtml) {
    const win = window.open('', '_blank', 'width=1024,height=768');
    if (!win) {
        alert('Não foi possível abrir a janela de impressão. Verifique o bloqueio de pop-up.');
        return;
    }
    const safeTitle = (title || 'Notas do evento').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const headerLogo = notasPrintLogoUrl
        ? '<img src="' + notasPrintLogoUrl.replace(/"/g, '&quot;') + '" alt="Logo" style="max-height:52px; max-width:260px; object-fit:contain;">'
        : '';
    win.document.write(
        '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
        '<title>' + safeTitle + '</title>' +
        '<style>' +
        '@page{size:A4 landscape;margin:12mm;}' +
        'body{font-family:Arial,sans-serif;margin:0;color:#111827;}' +
        '.sheet{padding:2mm 0;}' +
        '.header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:8px 0 14px;border-bottom:2px solid #0f766e;margin-bottom:14px;}' +
        '.header-meta{text-align:right;}' +
        'h1{font-size:18px;margin:0 0 4px;line-height:1.2;}' +
        '.sub{font-size:12px;color:#6b7280;margin:0;}' +
        '.print-table-wrap{border:1px solid #d1d5db;border-radius:8px;overflow:hidden;}' +
        'table{width:100%;border-collapse:collapse;font-size:13px;}' +
        'th,td{border:1px solid #d1d5db;padding:7px 8px;text-align:center;}' +
        'th:first-child,td:first-child{text-align:left;}' +
        'thead th{background:#eef2ff;color:#1f2937;font-weight:700;}' +
        'tbody tr:nth-child(even){background:#f9fafb;}' +
        '@media print{.no-print{display:none !important;}}' +
        '</style></head><body>' +
        '<div class="sheet">' +
        '<div class="header">' +
        '<div>' + headerLogo + '</div>' +
        '<div class="header-meta">' +
        '<h1>' + safeTitle + '</h1>' +
        '<p class="sub">Formato boletim · Impresso em ' + new Date().toLocaleString('pt-BR') + '</p>' +
        '</div>' +
        '</div>' +
        '<div class="print-table-wrap">' + bodyHtml + '</div>' +
        '</div>' +
        '<script>window.onload=function(){window.print();};<\/script>' +
        '</body></html>'
    );
    win.document.close();
}

// Função para mostrar/ocultar detalhes de conversas
function toggleConversaDetalhes(conversaId) {
    const detalhes = document.getElementById('conversa-detalhes-' + conversaId);
    const toggleText = document.getElementById('toggle-text-' + conversaId);
    
    if (detalhes && toggleText) {
        if (detalhes.classList.contains('hidden')) {
            detalhes.classList.remove('hidden');
            toggleText.textContent = 'Ocultar detalhes';
        } else {
            detalhes.classList.add('hidden');
            toggleText.textContent = 'Ver detalhes';
        }
    }
}

function irParaAbaRelatorio(tabName) {
    showTab(tabName);
    const sec = document.getElementById('section-relatorio-detalhado');
    if (sec) {
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Função para mostrar/ocultar detalhes de redações
function toggleRedacaoDetails(redacaoId) {
    const detalhes = document.getElementById('redacao-detalhes-' + redacaoId);
    const arrow = document.getElementById('arrow-' + redacaoId);
    
    if (detalhes && arrow) {
        if (detalhes.classList.contains('hidden')) {
            detalhes.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        } else {
            detalhes.classList.add('hidden');
            arrow.classList.remove('rotate-180');
        }
    }
}

// Função para abrir modal de análise
function abrirModalAnalise(alunoId) {
    document.getElementById('modalAnalise').classList.remove('hidden');
    document.getElementById('alunoIdAnalise').value = alunoId;
    // Data padrão: hoje
    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('dataAte').value = hoje;
}

// Função para fechar modal
function fecharModalAnalise() {
    document.getElementById('modalAnalise').classList.add('hidden');
    document.getElementById('resultadoAnalise').classList.add('hidden');
    document.getElementById('loadingAnalise').classList.add('hidden');
}

// Função para gerar análise
function gerarAnalise() {
    const alunoId = document.getElementById('alunoIdAnalise').value;
    const dataAte = document.getElementById('dataAte').value;
    
    if (!dataAte) {
        alert('Por favor, selecione uma data limite');
        return;
    }
    
    // Mostrar loading
    document.getElementById('loadingAnalise').classList.remove('hidden');
    document.getElementById('resultadoAnalise').classList.add('hidden');
    document.getElementById('btnGerarAnalise').disabled = true;
    
    const formData = new FormData();
    formData.append('_token', document.getElementById('csrf_token').value);
    formData.append('data_ate', dataAte);
    
    fetch(`<?= URL ?>/admin/students/${alunoId}/analise-tudinha`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.job_id) {
            document.getElementById('loadingAnalise').classList.add('hidden');
            document.getElementById('btnGerarAnalise').disabled = false;
            alert('Erro ao gerar análise: ' + (data.error || 'Erro desconhecido'));
            return;
        }

        new AIJobPoller(data.job_id, {
            onDone: function(result) {
                document.getElementById('loadingAnalise').classList.add('hidden');
                document.getElementById('btnGerarAnalise').disabled = false;
                document.getElementById('resultadoAnalise').classList.remove('hidden');
                document.getElementById('conteudoAnalise').innerHTML = formatarAnalise(result.analise);
                document.getElementById('dataAnalise').textContent = 'Análise de até ' + new Date(dataAte).toLocaleDateString('pt-BR');
            },
            onFailed: function(err) {
                document.getElementById('loadingAnalise').classList.add('hidden');
                document.getElementById('btnGerarAnalise').disabled = false;
                alert('Erro ao gerar análise: ' + err);
            }
        });
    })
    .catch(error => {
        console.error('Erro:', error);
        document.getElementById('loadingAnalise').classList.add('hidden');
        document.getElementById('btnGerarAnalise').disabled = false;
        alert('Erro de conexão ao gerar análise');
    });
}

// Ocorrências - formulário e IA

// Função para formatar análise
function formatarAnalise(analise) {
    // Se for string, retornar diretamente
    if (typeof analise === 'string') {
        // Tentar fazer parse se for JSON string
        try {
            analise = JSON.parse(analise);
        } catch(e) {
            return '<div class="whitespace-pre-wrap text-gray-700">' + analise.replace(/\n/g, '<br>') + '</div>';
        }
    }
    
    // Se ainda for objeto, extrair campos
    if (typeof analise === 'object' && analise !== null) {
        let html = '';
        
        // Função auxiliar para escapar HTML
        const escapeHtml = (text) => {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        // Função auxiliar para escapar HTML e quebrar linhas
        const formatarTexto = (texto) => {
            if (!texto) return '';
            
            // Se for objeto, formatar como lista
            if (typeof texto === 'object' && texto !== null) {
                let textoFormatado = '';
                for (const [key, value] of Object.entries(texto)) {
                    if (value) {
                        const valorFormatado = typeof value === 'object' ? JSON.stringify(value) : String(value);
                        textoFormatado += '<div class="mb-3"><strong class="text-gray-800">' + escapeHtml(String(key)) + ':</strong> <span class="text-gray-700">' + escapeHtml(valorFormatado) + '</span></div>';
                    }
                }
                return textoFormatado || '<pre>' + JSON.stringify(texto, null, 2) + '</pre>';
            }
            
            // Converter para string
            texto = String(texto);
            
            // Tentar fazer parse se for JSON string
            try {
                const parsed = JSON.parse(texto);
                if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
                    // Se for objeto, formatar como lista
                    let textoFormatado = '';
                    for (const [key, value] of Object.entries(parsed)) {
                        if (value) {
                            const valorFormatado = typeof value === 'object' ? JSON.stringify(value) : String(value);
                            textoFormatado += '<div class="mb-3"><strong class="text-gray-800">' + escapeHtml(String(key)) + ':</strong> <span class="text-gray-700">' + escapeHtml(valorFormatado) + '</span></div>';
                        }
                    }
                    return textoFormatado || escapeHtml(texto);
                }
            } catch(e) {
                // Não é JSON, continuar normalmente
            }
            
            // Escapar HTML e converter quebras de linha
            return escapeHtml(texto).replace(/\n/g, '<br>');
        };
        
        // Dificuldades
        const dificuldades = analise.dificuldades || analise.Dificuldades || analise.dificuldades_identificadas;
        if (dificuldades) {
            html += '<div class="mb-6"><h4 class="font-bold text-red-700 mb-2 text-lg">🔴 Dificuldades Identificadas:</h4><div class="text-gray-700 leading-relaxed">' + formatarTexto(dificuldades) + '</div></div>';
        }
        
        // Facilidades
        const facilidades = analise.facilidades || analise.Facilidades || analise.facilidades_identificadas;
        if (facilidades) {
            html += '<div class="mb-6"><h4 class="font-bold text-green-700 mb-2 text-lg">🟢 Facilidades Identificadas:</h4><div class="text-gray-700 leading-relaxed">' + formatarTexto(facilidades) + '</div></div>';
        }
        
        // Observações
        const observacoes = analise.observacoes || analise.Observacoes || analise.observacoes_gerais;
        if (observacoes) {
            html += '<div class="mb-6"><h4 class="font-bold text-blue-700 mb-2 text-lg">📊 Observações Gerais:</h4><div class="text-gray-700 leading-relaxed">' + formatarTexto(observacoes) + '</div></div>';
        }
        
        // Recomendações
        const recomendacoes = analise.recomendacoes || analise.Recomendacoes;
        if (recomendacoes) {
            html += '<div class="mb-6"><h4 class="font-bold text-purple-700 mb-2 text-lg">💡 Recomendações:</h4><div class="text-gray-700 leading-relaxed">' + formatarTexto(recomendacoes) + '</div></div>';
        }
        
        // Se tiver análise_completa mas não os campos específicos
        if (!html && analise.analise_completa) {
            html = '<div class="whitespace-pre-wrap text-gray-700">' + formatarTexto(analise.analise_completa) + '</div>';
        }
        
        return html || '<div class="text-gray-700"><pre>' + JSON.stringify(analise, null, 2) + '</pre></div>';
    }
    
    return '<div class="text-gray-700">Dados inválidos recebidos</div>';
}

        // Função para abrir modal de cadastrar pai
        function abrirModalCadastrarPai(alunoId) {
            document.getElementById('modalCadastrarPai').classList.remove('hidden');
            document.getElementById('aluno_id_pai').value = alunoId;
            document.getElementById('formCadastrarPai').reset();
            document.getElementById('errorMessagePai').classList.add('hidden');
            document.getElementById('successMessagePai').classList.add('hidden');
        }

        // Função para fechar modal de cadastrar pai
        function fecharModalCadastrarPai() {
            document.getElementById('modalCadastrarPai').classList.add('hidden');
            document.getElementById('formCadastrarPai').reset();
            document.getElementById('errorMessagePai').classList.add('hidden');
            document.getElementById('successMessagePai').classList.add('hidden');
        }

        function abrirModalEditarResponsavel(data) {
            document.getElementById('modalEditarResponsavel').classList.remove('hidden');
            document.getElementById('resp_edit_aluno_id').value = data.aluno_id || '';
            document.getElementById('resp_edit_responsavel_id').value = data.responsavel_id || '';
            document.getElementById('resp_edit_nome').value = data.nome || '';
            document.getElementById('resp_edit_email').value = data.email || '';
            document.getElementById('resp_edit_telefone').value = data.telefone || '';
            document.getElementById('resp_edit_cpf').value = data.cpf || '';
            document.getElementById('resp_edit_rg').value = data.rg || '';
            document.getElementById('resp_edit_celular').value = data.celular || '';
            document.getElementById('resp_edit_data_nascimento').value = data.data_nascimento || '';
            document.getElementById('resp_edit_endereco').value = data.endereco || '';
            document.getElementById('resp_edit_numero').value = data.numero || '';
            document.getElementById('resp_edit_complemento').value = data.complemento || '';
            document.getElementById('resp_edit_bairro').value = data.bairro || '';
            document.getElementById('resp_edit_cidade').value = data.cidade || '';
            document.getElementById('resp_edit_uf').value = data.uf || '';
            document.getElementById('resp_edit_cep').value = data.cep || '';
            document.getElementById('resp_edit_observacoes').value = data.observacoes || '';
            document.getElementById('resp_edit_senha').value = '';
            document.getElementById('resp_edit_financeiro').checked = Number(data.is_financeiro || 0) === 1;
            document.getElementById('resp_edit_ativo').checked = Number(data.ativo || 0) === 1;
            var setVal = function (id, val) { var el = document.getElementById(id); if (el) { el.value = val || ''; } };
            var setChk = function (id, val) { var el = document.getElementById(id); if (el) { el.checked = Number(val || 0) === 1; } };
            setVal('resp_edit_parentesco', data.parentesco);
            setVal('resp_edit_profissao', data.profissao);
            setVal('resp_edit_empresa', data.empresa);
            setChk('resp_edit_pode_retirar', data.pode_retirar);
            setChk('resp_edit_recebe_boletos', data.recebe_boletos);
            setChk('resp_edit_recebe_boletim', data.recebe_boletim);
            setChk('resp_edit_recebe_notificacoes', data.recebe_notificacoes);
            setChk('resp_edit_responsavel_pedagogico', data.responsavel_pedagogico);
            setChk('resp_edit_guarda_judicial', data.guarda_judicial);
            setChk('resp_edit_assina_documentos', data.assina_documentos);
            document.getElementById('respEditError').classList.add('hidden');
            document.getElementById('respEditSuccess').classList.add('hidden');
        }

        function fecharModalEditarResponsavel() {
            document.getElementById('modalEditarResponsavel').classList.add('hidden');
            document.getElementById('formEditarResponsavel').reset();
            document.getElementById('respEditError').classList.add('hidden');
            document.getElementById('respEditSuccess').classList.add('hidden');
        }

        var STUDENT_ID_DOC = <?= (int) ($student['id'] ?? 0) ?>;

        function docToggleTitulo() {
            var tipo = document.getElementById('doc_tipo').value;
            document.getElementById('doc_titulo_wrap').classList.toggle('hidden', tipo !== 'outros');
        }

        function abrirModalDocumento(data) {
            var modal = document.getElementById('modalDocumentoAluno');
            document.getElementById('formDocumentoAluno').reset();
            document.getElementById('docError').classList.add('hidden');
            document.getElementById('docSuccess').classList.add('hidden');
            data = data || {};
            document.getElementById('doc_doc_id').value = data.doc_id || '';
            document.getElementById('doc_tipo').value = data.tipo || 'rg';
            document.getElementById('doc_titulo').value = data.titulo || '';
            document.getElementById('doc_status').value = data.status || 'pendente';
            document.getElementById('doc_observacao').value = data.observacao || '';
            docToggleTitulo();
            modal.classList.remove('hidden');
        }

        function fecharModalDocumento() {
            document.getElementById('modalDocumentoAluno').classList.add('hidden');
        }

        async function salvarDocumento(event) {
            event.preventDefault();
            var form = event.target;
            var formData = new FormData(form);
            var errorDiv = document.getElementById('docError');
            var successDiv = document.getElementById('docSuccess');
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            try {
                var response = await fetch('<?= URL ?>/admin/students/' + STUDENT_ID_DOC + '/documentos/salvar', {
                    method: 'POST',
                    body: formData
                });
                var result = await response.json();
                if (response.ok && result.success) {
                    successDiv.textContent = result.message || 'Documento salvo';
                    successDiv.classList.remove('hidden');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    errorDiv.textContent = result.error || 'Erro ao salvar documento';
                    errorDiv.classList.remove('hidden');
                }
            } catch (e) {
                errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                errorDiv.classList.remove('hidden');
            }
        }

        async function removerDocumento(docId) {
            if (!confirm('Remover este documento?')) { return; }
            var formData = new FormData(document.getElementById('formRemoverDocumento'));
            try {
                var response = await fetch('<?= URL ?>/admin/students/' + STUDENT_ID_DOC + '/documentos/' + docId + '/remover', {
                    method: 'POST',
                    body: formData
                });
                var result = await response.json();
                if (response.ok && result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Erro ao remover documento');
                }
            } catch (e) {
                alert('Erro de conexão. Tente novamente.');
            }
        }

        function abrirModalAcessarComoPai() {
            document.getElementById('modalAcessarComoPai').classList.remove('hidden');
        }

        function fecharModalAcessarComoPai() {
            document.getElementById('modalAcessarComoPai').classList.add('hidden');
        }

        async function salvarEdicaoResponsavel(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const errorDiv = document.getElementById('respEditError');
            const successDiv = document.getElementById('respEditSuccess');
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');

            try {
                const response = await fetch('<?= URL ?>/admin/students/responsavel/atualizar', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    successDiv.textContent = result.message || 'Responsável atualizado.';
                    successDiv.classList.remove('hidden');
                    setTimeout(() => location.reload(), 900);
                } else {
                    errorDiv.textContent = result.error || 'Erro ao atualizar responsável.';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                errorDiv.classList.remove('hidden');
            }
        }

        // Função para cadastrar pai
        async function cadastrarPai(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const errorDiv = document.getElementById('errorMessagePai');
            const successDiv = document.getElementById('successMessagePai');
            
            // Hide previous messages
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            
            try {
                const response = await fetch('<?= URL ?>/admin/students/cadastrar-pai', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    successDiv.textContent = result.message || 'Responsável cadastrado e vinculado com sucesso!';
                    successDiv.classList.remove('hidden');
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    errorDiv.textContent = result.error || 'Erro ao cadastrar responsável';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                errorDiv.classList.remove('hidden');
            }
        }

        const ADMIN_PERMISSIONS = <?= json_encode($admin_permissions, JSON_UNESCAPED_UNICODE) ?>;

        function hasAdminPermission(key, action = 'visualizar') {
            return !!(ADMIN_PERMISSIONS[key] && ADMIN_PERMISSIONS[key][action]);
        }

        function applyStudentPermissionVisibility() {
            const responsaveisSection = document.getElementById('section-responsaveis-vinculados');
            if (responsaveisSection && !hasAdminPermission('responsaveis_vinculados', 'visualizar')) {
                responsaveisSection.classList.add('hidden');
            }

            const matriculasSection = document.getElementById('section-matriculas-aluno');
            if (matriculasSection && !hasAdminPermission('matriculas_aluno', 'visualizar')) {
                matriculasSection.classList.add('hidden');
            }

            document.querySelectorAll('[data-perm-key]').forEach((el) => {
                const key = el.getAttribute('data-perm-key');
                const action = el.getAttribute('data-perm-action') || 'visualizar';
                if (!key || hasAdminPermission(key, action)) return;
                el.classList.add('hidden');
                if ('disabled' in el) {
                    el.disabled = true;
                }
            });

            const tabMap = {
                'tab-relatorio': 'content-relatorio',
                'tab-redacoes': 'content-redacoes',
                'tab-ocorrencias': 'content-ocorrencias',
                'tab-jornadas': 'content-jornadas',
                'tab-provas': 'content-provas',
                'tab-notas-eventos': 'content-notas-eventos',
                'tab-boletim-eventos': 'content-boletim-eventos',
                'tab-acessos': 'content-acessos'
            };

            let firstAllowedTab = null;
            document.querySelectorAll('[data-tab-perm-key]').forEach((btn) => {
                const key = btn.getAttribute('data-tab-perm-key');
                const allowed = !!key && hasAdminPermission(key, 'visualizar');
                const contentId = tabMap[btn.id] || '';
                const content = contentId ? document.getElementById(contentId) : null;
                if (!allowed) {
                    btn.classList.add('hidden');
                    if (content) content.classList.add('hidden');
                    return;
                }
                if (!firstAllowedTab) {
                    firstAllowedTab = btn.id.replace('tab-', '');
                }
            });

            showTab(firstAllowedTab || 'relatorio');
        }

        // Inicializar tab padrão ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            applyStudentPermissionVisibility();
            
            // Fechar modal ao clicar fora
            document.getElementById('modalAnalise').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalAnalise();
                }
            });
            
            // Fechar modal de cadastrar pai ao clicar fora
            document.getElementById('modalCadastrarPai').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalCadastrarPai();
                }
            });

            document.getElementById('modalEditarResponsavel')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalEditarResponsavel();
                }
            });

            document.getElementById('modalAcessarComoPai')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalAcessarComoPai();
                }
            });

            document.getElementById('modalAddMatricula')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalMatricula();
                }
            });
        });
</script>

<?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>

<div id="modalAcessarComoPai" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <div>
                <h3 class="text-2xl font-bold text-gray-900">Acessar como Pai</h3>
                <p class="text-sm text-gray-500 mt-1">Selecione qual responsável deseja acessar no portal.</p>
            </div>
            <button onclick="fecharModalAcessarComoPai()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <?php if (empty($responsaveis_aluno)): ?>
                <p class="text-sm text-gray-500">Nenhum responsável ativo vinculado a este aluno.</p>
            <?php else: ?>
                <form method="GET" action="<?= URL ?>/admin/students/<?= (int)($student['id'] ?? 0) ?>/acessar-como-pai" class="space-y-4">
                    <div>
                        <label for="responsavel_id_acesso" class="block text-sm font-medium text-gray-700 mb-2">Responsável</label>
                        <select id="responsavel_id_acesso" name="responsavel_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500">
                            <option value="">Selecione um responsável</option>
                            <?php foreach ($responsaveis_aluno as $resp): ?>
                                <option value="<?= (int)($resp['id'] ?? 0) ?>">
                                    <?= safe_htmlspecialchars($resp['nome'] ?? '', 'Responsável') ?><?= !empty($resp['email']) ? ' - ' . safe_htmlspecialchars($resp['email']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="fecharModalAcessarComoPai()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                            Entrar como pai
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Editar Responsável -->
<div id="modalEditarResponsavel" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[92vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <h3 class="text-2xl font-bold text-gray-900">Editar Responsável</h3>
            <button onclick="fecharModalEditarResponsavel()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <form id="formEditarResponsavel" onsubmit="salvarEdicaoResponsavel(event)">
                <input type="hidden" id="resp_edit_aluno_id" name="aluno_id" value="">
                <input type="hidden" id="resp_edit_responsavel_id" name="responsavel_id" value="">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="space-y-5">
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Identificação</h4>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label for="resp_edit_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                                <input type="text" id="resp_edit_nome" name="nome" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Nome completo do responsável">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                            <div>
                                <label for="resp_edit_cpf" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                <input type="text" id="resp_edit_cpf" name="cpf" maxlength="14"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="000.000.000-00">
                            </div>
                            <div>
                                <label for="resp_edit_rg" class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                                <input type="text" id="resp_edit_rg" name="rg" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="00.000.000-0">
                            </div>
                            <div>
                                <label for="resp_edit_data_nascimento" class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                                <input type="date" id="resp_edit_data_nascimento" name="data_nascimento"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="resp_edit_email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                <input type="email" id="resp_edit_email" name="email"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="email@exemplo.com">
                            </div>
                            <div>
                                <label for="resp_edit_telefone" class="block text-sm font-medium text-gray-700 mb-1">Telefone fixo</label>
                                <input type="text" id="resp_edit_telefone" name="telefone" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="(00) 0000-0000">
                            </div>
                            <div>
                                <label for="resp_edit_celular" class="block text-sm font-medium text-gray-700 mb-1">Celular / WhatsApp</label>
                                <input type="text" id="resp_edit_celular" name="celular" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Endereço</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-2">
                                <label for="resp_edit_endereco" class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                <input type="text" id="resp_edit_endereco" name="endereco" maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Rua, Avenida...">
                            </div>
                            <div>
                                <label for="resp_edit_numero" class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                <input type="text" id="resp_edit_numero" name="numero" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="123">
                            </div>
                            <div>
                                <label for="resp_edit_complemento" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                <input type="text" id="resp_edit_complemento" name="complemento" maxlength="100"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Apto, Casa...">
                            </div>
                            <div>
                                <label for="resp_edit_bairro" class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                <input type="text" id="resp_edit_bairro" name="bairro" maxlength="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Bairro">
                            </div>
                            <div>
                                <label for="resp_edit_cep" class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                <input type="text" id="resp_edit_cep" name="cep" maxlength="9"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="00000-000">
                            </div>
                            <div class="md:col-span-2">
                                <label for="resp_edit_cidade" class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                <input type="text" id="resp_edit_cidade" name="cidade" maxlength="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Cidade">
                            </div>
                            <div>
                                <label for="resp_edit_uf" class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                <select id="resp_edit_uf" name="uf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <option value="">--</option>
                                    <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                    <option value="<?= $uf ?>"><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Acesso ao portal</h4>
                        <div>
                            <label for="resp_edit_senha" class="block text-sm font-medium text-gray-700 mb-1">
                                Nova senha <span class="text-gray-400 font-normal">(deixe em branco para manter a atual)</span>
                            </label>
                            <input type="password" id="resp_edit_senha" name="senha"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Mínimo 6 caracteres">
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-4">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" id="resp_edit_financeiro" name="is_financeiro" value="1" class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700">Responsável financeiro</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" id="resp_edit_ativo" name="ativo" value="1" class="rounded border-gray-300 text-indigo-600" checked>
                                <span class="text-sm text-gray-700">Ativo</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Dados do vínculo</h4>
                        <?php $prefix = 'resp_edit_'; include __DIR__ . '/_responsavel_vinculo_fields.php'; ?>
                    </div>

                    <div>
                        <label for="resp_edit_observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea id="resp_edit_observacoes" name="observacoes" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 resize-none"
                                  placeholder="Informações adicionais..."></textarea>
                    </div>
                </div>
                <div id="respEditError" class="hidden mt-4 bg-red-100 border border-red-300 text-red-700 px-3 py-2 rounded-lg text-sm"></div>
                <div id="respEditSuccess" class="hidden mt-4 bg-green-100 border border-green-300 text-green-700 px-3 py-2 rounded-lg text-sm"></div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="fecharModalEditarResponsavel()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Salvar alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Análise da Tudinha -->
<div id="modalAnalise" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <h3 class="text-2xl font-bold text-gray-900">Análise da Tudinha</h3>
            <button onclick="fecharModalAnalise()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="p-6">
            <input type="hidden" id="alunoIdAnalise" value="">
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Data limite para análise:
                </label>
                <input type="date" id="dataAte" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                <p class="text-xs text-gray-500 mt-1">A análise considerará todas as atividades até esta data</p>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button onclick="fecharModalAnalise()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancelar
                </button>
                <button id="btnGerarAnalise" onclick="gerarAnalise()" class="px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700 transition-colors">
                    Gerar Análise
                </button>
            </div>
            
            <!-- Loading -->
            <div id="loadingAnalise" class="hidden mt-6 text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-pink-600 mb-4"></div>
                <p class="text-gray-600">Gerando análise completa do aluno...</p>
                <p class="text-sm text-gray-500 mt-2">Isso pode levar alguns minutos</p>
            </div>
            
            <!-- Resultado -->
            <div id="resultadoAnalise" class="hidden mt-6">
                <div class="bg-gradient-to-r from-pink-50 to-purple-50 border border-pink-200 rounded-lg p-6">
                    <div class="mb-4">
                        <span class="text-sm text-gray-500" id="dataAnalise"></span>
                    </div>
                    <div id="conteudoAnalise" class="prose max-w-none">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Documento do Aluno -->
<div id="modalDocumentoAluno" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
            <h3 class="text-2xl font-bold text-gray-900">Documento do aluno</h3>
            <button onclick="fecharModalDocumento()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <form id="formDocumentoAluno" onsubmit="salvarDocumento(event)" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" id="doc_doc_id" name="doc_id" value="">
                <div class="space-y-4">
                    <div>
                        <label for="doc_tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo de documento</label>
                        <select id="doc_tipo" name="tipo" onchange="docToggleTitulo()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <?php foreach ($docChecklist as $ckTipo => $ckLabel): ?>
                            <option value="<?= htmlspecialchars($ckTipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ckLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="doc_titulo_wrap" class="hidden">
                        <label for="doc_titulo" class="block text-sm font-medium text-gray-700 mb-2">Título do documento</label>
                        <input type="text" id="doc_titulo" name="titulo" maxlength="160" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Ex: Declaração de vacinação">
                    </div>
                    <div>
                        <label for="doc_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="doc_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="pendente">Pendente</option>
                            <option value="entregue">Entregue</option>
                            <option value="dispensado">Dispensado</option>
                        </select>
                    </div>
                    <div>
                        <label for="doc_arquivo" class="block text-sm font-medium text-gray-700 mb-2">Arquivo (opcional)</label>
                        <input type="file" id="doc_arquivo" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500 mt-1">PDF, imagem ou documento (até 10MB). Anexar marca como entregue.</p>
                    </div>
                    <div>
                        <label for="doc_observacao" class="block text-sm font-medium text-gray-700 mb-2">Observação</label>
                        <input type="text" id="doc_observacao" name="observacao" maxlength="255" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div id="docError" class="hidden mt-4 bg-red-100 border border-red-300 text-red-700 px-3 py-2 rounded-lg text-sm"></div>
                <div id="docSuccess" class="hidden mt-4 bg-green-100 border border-green-300 text-green-700 px-3 py-2 rounded-lg text-sm"></div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="fecharModalDocumento()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formRemoverDocumento" class="hidden">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
</form>

<!-- Modal de Cadastrar Responsável -->
<div id="modalCadastrarPai" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[92vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Cadastrar / Vincular Responsável</h3>
            <button onclick="fecharModalCadastrarPai()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="px-6 py-3 bg-blue-50 border-b border-blue-100 text-sm text-blue-700">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Se o responsável <strong>já tem cadastro</strong> (ex: outro filho na escola), informe o CPF — ele será vinculado automaticamente sem criar novo registro.
        </div>

        <div class="p-6">
            <form id="formCadastrarPai" onsubmit="cadastrarPai(event)">
                <input type="hidden" id="aluno_id_pai" name="aluno_id" value="">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="space-y-5">

                    <!-- Identificação -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Identificação</h4>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label for="pai_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                                <input type="text" id="pai_nome" name="nome" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Nome completo do responsável">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mt-3">
                            <div>
                                <label for="pai_cpf" class="block text-sm font-medium text-gray-700 mb-1">CPF *</label>
                                <input type="text" id="pai_cpf" name="cpf" required maxlength="14"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="000.000.000-00">
                            </div>
                            <div>
                                <label for="pai_rg" class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                                <input type="text" id="pai_rg" name="rg" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="00.000.000-0">
                            </div>
                            <div>
                                <label for="pai_data_nascimento" class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                                <input type="date" id="pai_data_nascimento" name="data_nascimento"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="pai_email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                <input type="email" id="pai_email" name="email"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="email@exemplo.com">
                            </div>
                            <div>
                                <label for="pai_telefone" class="block text-sm font-medium text-gray-700 mb-1">Telefone fixo</label>
                                <input type="text" id="pai_telefone" name="telefone" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="(00) 0000-0000">
                            </div>
                            <div>
                                <label for="pai_celular" class="block text-sm font-medium text-gray-700 mb-1">Celular / WhatsApp</label>
                                <input type="text" id="pai_celular" name="celular" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>

                    <!-- Endereço -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Endereço</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label for="pai_endereco" class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                <input type="text" id="pai_endereco" name="endereco" maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Rua, Avenida...">
                            </div>
                            <div>
                                <label for="pai_numero" class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                <input type="text" id="pai_numero" name="numero" maxlength="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="123">
                            </div>
                            <div>
                                <label for="pai_complemento" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                <input type="text" id="pai_complemento" name="complemento" maxlength="100"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Apto, Casa...">
                            </div>
                            <div>
                                <label for="pai_bairro" class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                <input type="text" id="pai_bairro" name="bairro" maxlength="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Bairro">
                            </div>
                            <div>
                                <label for="pai_cep" class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                <input type="text" id="pai_cep" name="cep" maxlength="9"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="00000-000">
                            </div>
                            <div class="col-span-2">
                                <label for="pai_cidade" class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                <input type="text" id="pai_cidade" name="cidade" maxlength="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Cidade">
                            </div>
                            <div>
                                <label for="pai_uf" class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                <select id="pai_uf" name="uf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">--</option>
                                    <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                    <option value="<?= $uf ?>"><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Responsável financeiro + Senha -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Acesso ao portal</h4>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label for="pai_senha" class="block text-sm font-medium text-gray-700 mb-1">
                                    Senha <span class="text-gray-400 font-normal">(deixe em branco se já tem cadastro)</span>
                                </label>
                                <input type="password" id="pai_senha" name="senha"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Mínimo 6 caracteres">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="is_financeiro" value="1" class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700 font-medium">Responsável financeiro (recebe cobranças e assina contratos)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Vínculo -->
                    <div class="pt-3 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Dados do vínculo</h4>
                        <?php $prefix = 'pai_'; include __DIR__ . '/_responsavel_vinculo_fields.php'; ?>
                    </div>

                    <!-- Observações -->
                    <div>
                        <label for="pai_observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                        <textarea id="pai_observacoes" name="observacoes" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                  placeholder="Informações adicionais..."></textarea>
                    </div>
                </div>

                <div id="errorMessagePai" class="hidden mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm"></div>
                <div id="successMessagePai" class="hidden mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg text-sm"></div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="fecharModalCadastrarPai()"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Responsável
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
