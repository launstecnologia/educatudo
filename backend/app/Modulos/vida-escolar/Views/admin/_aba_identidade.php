<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$aluno = is_array($aluno ?? null) ? $aluno : [];
$matricula = is_array($matricula ?? null) ? $matricula : [];
$unidade = is_array($unidade ?? null) ? $unidade : [];
$links = is_array($links ?? null) ? $links : [];
$campo = static function (string $label, $valor) use ($esc): void {
    $txt = trim((string) $valor);
    ?>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500"><?= $esc($label) ?></dt>
        <dd class="mt-0.5 text-sm font-medium text-gray-900"><?= $esc($txt !== '' ? $txt : 'Não informado') ?></dd>
    </div>
    <?php
};
$dataBr = static function ($v): string {
    $s = trim((string) $v);
    if ($s === '' || $s === '0000-00-00') {
        return '';
    }
    $ts = strtotime($s);
    return $ts ? date('d/m/Y', $ts) : $s;
};
?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex justify-between items-start gap-3 flex-wrap mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Identidade e matrícula</h3>
            <p class="text-sm text-gray-500">Dados usados em histórico, SED e Educacenso. Edição fica no cadastro do aluno.</p>
        </div>
        <a href="<?= URL . ($links['cadastro'] ?? '/admin/students/' . (int) ($aluno['id'] ?? 0)) ?>" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Abrir cadastro</a>
    </div>
    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php $campo('Nome', $aluno['nome'] ?? ''); ?>
        <?php $campo('CPF', $aluno['cpf'] ?? ''); ?>
        <?php $campo('RG', $aluno['rg'] ?? ''); ?>
        <?php $campo('Nascimento', $dataBr($aluno['data_nasc'] ?? null)); ?>
        <?php $campo('Nome da mãe', $aluno['nome_mae'] ?? ''); ?>
        <?php $campo('Nome do pai', $aluno['nome_pai'] ?? ''); ?>
        <?php $campo('Sexo', $aluno['sexo'] ?? ''); ?>
        <?php $campo('Cor/raça', $aluno['cor_raca'] ?? ''); ?>
        <?php $campo('Nacionalidade', $aluno['nacionalidade'] ?? ''); ?>
        <?php $campo('Código INEP do aluno', $aluno['codigo_inep'] ?? ''); ?>
        <?php $campo('Turma', $aluno['turma_nome'] ?? $matricula['turma_nome'] ?? ''); ?>
        <?php $campo('Série', $aluno['turma_serie'] ?? $matricula['turma_serie'] ?? ''); ?>
        <?php $campo('Situação da matrícula', $matricula['status'] ?? ''); ?>
        <?php $campo('Data de entrada', $dataBr($matricula['data_entrada'] ?? null)); ?>
        <?php $campo('Data de saída', $dataBr($matricula['data_saida'] ?? null)); ?>
        <?php $campo('Escola / unidade', $unidade['nome'] ?? ''); ?>
        <?php $campo('INEP da escola', $unidade['inep'] ?? ''); ?>
        <?php $campo('CNPJ', $unidade['cnpj'] ?? ''); ?>
        <?php $campo('Diretor(a)', $unidade['diretor_nome'] ?? ''); ?>
        <?php $campo('Secretário(a)', $unidade['secretario_nome'] ?? ''); ?>
    </dl>
</div>
<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Emitir agora</h3>
    <p class="text-sm text-gray-500 mb-4">Declarações usam o layout da escola. Débito financeiro não bloqueia documento acadêmico.</p>
    <div class="flex flex-wrap gap-2">
        <a href="<?= URL . ($links['declaracao_matricula'] ?? '#') ?>" target="_blank" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Declaração de matrícula</a>
        <a href="<?= URL . ($links['declaracao_frequencia'] ?? '#') ?>" target="_blank" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Declaração de frequência</a>
        <a href="<?= URL . ($links['ficha_matricula'] ?? '#') ?>" target="_blank" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Ficha de matrícula</a>
        <a href="<?= URL . ($links['declaracao_transferencia'] ?? '#') ?>" target="_blank" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Declaração de transferência</a>
    </div>
</div>
