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
