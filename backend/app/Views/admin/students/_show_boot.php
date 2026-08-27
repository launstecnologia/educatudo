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
$ficha_complementar = is_array($ficha_complementar ?? null) ? $ficha_complementar : [];
$documentos_aluno = is_array($documentos_aluno ?? null) ? $documentos_aluno : [];
$audit_logs = is_array($audit_logs ?? null) ? $audit_logs : [];
$vida_escolar_prontuario = is_array($vida_escolar_prontuario ?? null) ? $vida_escolar_prontuario : [];
$vida_escolar_kpis = is_array($vida_escolar_kpis ?? null) ? $vida_escolar_kpis : ['media' => null, 'frequencia' => null];
$vida_escolar_schema = (bool) ($vida_escolar_schema ?? false);
$vida_escolar_pode_ler_ia = (bool) ($vida_escolar_pode_ler_ia ?? false);
$csrf_token = $csrf_token ?? '';
$flash_message = (string)($flash_message ?? '');
$flash_type = (string)($flash_type ?? '');
$logoHorizontalPrintUrl = '';
if (class_exists('LayoutHelper')) {
    $logoHorizontalPrintUrl = (string) (LayoutHelper::getDocumentLogoUrl() ?: '');
}

// Garantir que $student é um array
if (!is_array($student)) {
    $student = [];
}

// Função helper para converter valores para string antes de htmlspecialchars
if (!function_exists('safe_htmlspecialchars')) {
function safe_htmlspecialchars($value, $default = '') {
    if (is_array($value)) {
        return htmlspecialchars($default);
    }
    if ($value === null) {
        return htmlspecialchars($default);
    }
    return htmlspecialchars((string)$value);
}
}

if (!function_exists('format_data_br')) {
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
}

if (!function_exists('student_campo_endereco')) {
function student_campo_endereco(array $student, array $keys): string {
    foreach ($keys as $key) {
        $val = trim((string) ($student[$key] ?? ''));
        if ($val !== '') {
            return $val;
        }
    }
    return '';
}
}

if (!function_exists('format_cep_exibicao')) {
function format_cep_exibicao(?string $cep): string {
    $digits = preg_replace('/\D+/', '', (string) $cep);
    if (strlen($digits) === 8) {
        return substr($digits, 0, 5) . '-' . substr($digits, 5);
    }
    return trim((string) $cep);
}
}

if (!function_exists('can_admin_perm')) {
function can_admin_perm(array $permissions, string $key, string $action = 'visualizar'): bool {
    return !empty($permissions[$key][$action]);
}
}

if (!function_exists('responsavel_iniciais')) {
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
}
?>
