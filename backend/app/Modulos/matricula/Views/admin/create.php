<?php
$esc     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$prefill = $prefill ?? [];
$val     = fn($k) => $esc($prefill[$k] ?? $_POST[$k] ?? '');
$tipoAtual = $tipo ?? 'nova';
$isRem   = $tipoAtual === 'rematricula';
$isEdit  = !empty($modo_edicao);
$enrollmentId = (int) ($enrollment_id ?? 0);
$faltandoWizard = $faltando_enturmar ?? [];
$passoInicial = (int) ($passo_inicial ?? 1);
if ($passoInicial < 1 || $passoInicial > 5) {
    $passoInicial = 1;
}
$descontos = $descontos ?? [];
$responsaveisPrefill = $responsaveis ?? [];
$planos_financeiros = $planos_financeiros ?? [];
$anos_letivos = $anos_letivos ?? [];
$turmas = $turmas ?? [];
$anoLetivoSel = (int) ($prefill['ano_letivo_id'] ?? $_POST['ano_letivo_id'] ?? 0);
$cpfDigitsPrefill = preg_replace('/\D+/', '', (string) ($prefill['aluno_cpf'] ?? '')) ?? '';
$nascPrefill = trim((string) ($prefill['aluno_data_nasc'] ?? ''));
$erroCampos = [
    'ano_letivo_id' => $isEdit && $anoLetivoSel <= 0,
    'turma_id' => $isEdit && (int) ($prefill['turma_id'] ?? 0) <= 0,
    'aluno_nome' => $isEdit && trim((string) ($prefill['aluno_nome'] ?? '')) === '',
    'aluno_cpf' => $isEdit && strlen($cpfDigitsPrefill) !== 11,
    'aluno_data_nasc' => $isEdit && ($nascPrefill === '' || $nascPrefill === '0000-00-00'),
];
$formAction = $isEdit && $enrollmentId > 0
    ? URL . '/admin/enrollment/' . $enrollmentId . '/edit'
    : URL . '/admin/enrollment';
$voltarUrl = $isEdit && $enrollmentId > 0
    ? URL . '/admin/enrollment/' . $enrollmentId
    : URL . '/admin/enrollment';

$mensalidadePlanId = 0;
$cobrancasPost = $_POST['cobrancas'] ?? null;
if (is_array($cobrancasPost)) {
    foreach ($cobrancasPost as $row) {
        if (!is_array($row)) continue;
        if (($row['tipo'] ?? '') === 'mensalidade' && (int)($row['plan_id'] ?? 0) > 0) {
            $mensalidadePlanId = (int)$row['plan_id'];
            break;
        }
    }
}
if ($mensalidadePlanId <= 0) {
    $fp = (int)($prefill['finance_plan_id'] ?? $_POST['finance_plan_id'] ?? 0);
    if ($fp > 0) $mensalidadePlanId = $fp;
}
if ($mensalidadePlanId <= 0) {
    $rawCobrancas = $prefill['finance_cobrancas'] ?? null;
    if (is_string($rawCobrancas)) {
        $rawCobrancas = json_decode($rawCobrancas, true);
    }
    if (is_array($rawCobrancas)) {
        foreach ($rawCobrancas as $row) {
            if (!is_array($row)) continue;
            if (($row['tipo'] ?? '') === 'mensalidade' && (int) ($row['plan_id'] ?? 0) > 0) {
                $mensalidadePlanId = (int) $row['plan_id'];
                break;
            }
        }
    }
}

$origemAtual = $_POST['origem'] ?? ($prefill['origem'] ?? ($isRem ? 'interno' : ''));
$inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none wizard-focus';
$clsCampo = static function (string $campo) use ($inputClass, $erroCampos): string {
    return $inputClass . (!empty($erroCampos[$campo]) ? ' campo-obrigatorio-vazio' : '');
};

require_once dirname(__DIR__, 4) . '/Helpers/StudentFormHelper.php';
$ufsBrasil = \StudentFormHelper::ufsBrasil();
$ufOptionsHtml = '<option value="">UF</option>';
foreach ($ufsBrasil as $ufOpt) {
    $ufOptionsHtml .= '<option value="' . htmlspecialchars($ufOpt, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($ufOpt, ENT_QUOTES, 'UTF-8') . '</option>';
}

$steps = [
    ['n' => 1, 'label' => 'Vínculo', 'sub' => 'Tipo e turma'],
    ['n' => 2, 'label' => 'Aluno', 'sub' => 'Dados pessoais'],
    ['n' => 3, 'label' => 'Responsáveis', 'sub' => 'Família e rateio'],
    ['n' => 4, 'label' => 'Plano', 'sub' => 'Mensalidade'],
    ['n' => 5, 'label' => 'Revisar', 'sub' => 'Confirmar'],
];
?>

<style>
/* Estados ativos do wizard usam a cor do sistema (whitelabel) */
#matriculaWizard .wizard-active {
    border-color: var(--primary-color) !important;
    background-color: color-mix(in srgb, var(--primary-color) 12%, white) !important;
    color: #111827 !important;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
}
#matriculaWizard .wizard-active-num {
    background-color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: var(--primary-text-color, #fff) !important;
}
#matriculaWizard .wizard-accent { color: var(--primary-color) !important; }
#matriculaWizard .wizard-hover:hover {
    border-color: color-mix(in srgb, var(--primary-color) 35%, #e5e7eb) !important;
    background-color: color-mix(in srgb, var(--primary-color) 6%, white) !important;
}
#matriculaWizard .wizard-focus:focus {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary-color) 30%, transparent);
}
#matriculaWizard .wizard-check {
    accent-color: var(--primary-color);
    color: var(--primary-color);
}
#matriculaWizard .wizard-check:focus {
    --tw-ring-color: var(--primary-color);
}
#matriculaWizard .campo-obrigatorio-vazio {
    border-color: #f87171 !important;
    background-color: #fef2f2;
}
#matriculaWizard .campo-obrigatorio-vazio:focus {
    box-shadow: 0 0 0 2px rgba(248, 113, 113, .35);
}
</style>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= $esc($voltarUrl) ?>"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= $isEdit ? 'Editar Matrícula #' . $enrollmentId : 'Nova Matrícula' ?></h2>
            <p class="text-sm text-gray-600"><?= $isEdit ? 'Complete os campos em vermelho e salve para atualizar o processo.' : 'Wizard em 5 etapas — ao salvar, você seguirá para o contrato.' ?></p>
        </div>
    </div>
</div>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<?php if ($isEdit && $faltandoWizard !== []): ?>
<div class="mb-6 p-4 rounded-xl text-sm bg-red-50 text-red-800 border border-red-200">
    <p class="font-medium mb-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Campos faltando para enturmar</p>
    <ul class="list-disc ml-5 space-y-0.5">
        <?php foreach ($faltandoWizard as $campoFalta): ?>
        <li><?= $esc($campoFalta) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php elseif ($isRem && !empty($prefill['aluno_nome'])): ?>
<div class="mb-6 p-4 rounded-lg border text-sm text-gray-700 flex items-center gap-2" style="background-color: color-mix(in srgb, var(--primary-color) 10%, white); border-color: var(--primary-color);">
    <i class="fa-solid fa-circle-info wizard-accent" style="color: var(--primary-color);"></i>
    Dados pré-carregados do aluno. Revise e confirme para gerar o contrato.
</div>
<?php endif; ?>

<div id="matriculaWizard" class="space-y-4">
    <!-- Stepper -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-center gap-2 sm:gap-0" id="wizardStepsNav" role="tablist">
        <?php foreach ($steps as $i => $s): ?>
            <button type="button" data-step-target="<?= (int)$s['n'] ?>"
                    class="step-nav-btn group flex items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition sm:flex-1 sm:max-w-[180px]
                           <?= $s['n'] === $passoInicial
                               ? 'wizard-active'
                               : 'border-gray-200 bg-white text-gray-600 wizard-hover' ?>"
                    data-active="<?= $s['n'] === $passoInicial ? 'true' : 'false' ?>">
                <span class="step-nav-num flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold border-2
                             <?= $s['n'] === $passoInicial ? 'wizard-active-num' : 'border-current' ?>"><?= (int)$s['n'] ?></span>
                <span class="min-w-0">
                    <span class="block font-semibold leading-tight"><?= $esc($s['label']) ?></span>
                    <span class="block text-xs <?= $s['n'] === $passoInicial ? 'text-gray-600' : 'opacity-80' ?>"><?= $esc($s['sub']) ?></span>
                </span>
            </button>
            <?php if ($i < count($steps) - 1): ?>
                <div class="hidden sm:block w-6 h-0.5 bg-gray-200 shrink-0" aria-hidden="true"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <form method="POST" action="<?= $esc($formAction) ?>" id="matriculaForm" novalidate class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <?php if (!empty($prefill['aluno_id'])): ?>
        <input type="hidden" name="aluno_id" id="aluno_id_input" value="<?= (int)$prefill['aluno_id'] ?>">
        <?php else: ?>
        <input type="hidden" name="aluno_id" id="aluno_id_input" value="">
        <?php endif; ?>

        <!-- Campos legado resp_* (sincronizados via JS) -->
        <input type="hidden" name="resp_nome" id="resp_nome" value="<?= $val('resp_nome') ?>">
        <input type="hidden" name="resp_cpf" id="resp_cpf" value="<?= $val('resp_cpf') ?>">
        <input type="hidden" name="resp_email" id="resp_email" value="<?= $val('resp_email') ?>">
        <input type="hidden" name="resp_telefone" id="resp_telefone" value="<?= $val('resp_telefone') ?>">
        <input type="hidden" name="resp_parentesco" id="resp_parentesco" value="<?= $val('resp_parentesco') ?>">
        <input type="hidden" name="resp_endereco" id="resp_endereco" value="<?= $val('resp_endereco') ?>">
        <input type="hidden" name="finance_plan_id" id="finance_plan_id" value="<?= $mensalidadePlanId > 0 ? $mensalidadePlanId : '' ?>">

        <!-- Step 1 — Vínculo -->
        <div class="step-panel <?= $passoInicial === 1 ? '' : 'hidden' ?> p-6 space-y-6" data-step-panel="1">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Tipo de Vínculo</h3>
                <p class="mt-1 text-sm text-gray-500">Selecione o tipo de matrícula e a turma de destino.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="tipo-cards">
                <?php
                $tiposCard = [
                    'nova' => ['label' => 'Matrícula (nova)', 'icon' => 'fa-user-plus', 'desc' => 'Primeira matrícula na escola'],
                    'rematricula' => ['label' => 'Rematrícula', 'icon' => 'fa-rotate', 'desc' => 'Aluno já matriculado'],
                    'transferencia' => ['label' => 'Transferência', 'icon' => 'fa-right-left', 'desc' => 'Vindo de outra instituição'],
                ];
                foreach ($tiposCard as $tk => $tc):
                ?>
                <label class="tipo-card relative flex flex-col gap-2 p-4 border-2 rounded-xl cursor-pointer transition wizard-hover
                              <?= $tipoAtual === $tk ? 'wizard-active' : 'border-gray-200' ?>">
                    <input type="radio" name="tipo" value="<?= $esc($tk) ?>" class="sr-only" <?= $tipoAtual === $tk ? 'checked' : '' ?>>
                    <span class="flex items-center gap-2 font-semibold text-gray-900">
                        <i class="fa-solid <?= $tc['icon'] ?> wizard-accent"></i>
                        <?= $esc($tc['label']) ?>
                    </span>
                    <span class="text-xs text-gray-500"><?= $esc($tc['desc']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <div id="origem-wrap" class="<?= $tipoAtual === 'nova' ? '' : 'hidden' ?>">
                <label for="origem" class="block text-sm font-medium text-gray-700 mb-2">
                    Origem <span class="text-red-500">*</span>
                </label>
                <select id="origem" name="origem" class="<?= $inputClass ?>">
                    <option value="">Selecionar...</option>
                    <option value="rede_social" <?= $origemAtual === 'rede_social' ? 'selected' : '' ?>>Rede Social</option>
                    <option value="indicacao" <?= $origemAtual === 'indicacao' ? 'selected' : '' ?>>Indicação</option>
                    <option value="site" <?= $origemAtual === 'site' ? 'selected' : '' ?>>Site</option>
                    <option value="whatsapp" <?= $origemAtual === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                    <option value="outros" <?= $origemAtual === 'outros' ? 'selected' : '' ?>>Outros</option>
                    <option value="interno" <?= $origemAtual === 'interno' ? 'selected' : '' ?>>Interno (secretaria)</option>
                    <option value="evento" <?= $origemAtual === 'evento' ? 'selected' : '' ?>>Evento</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Ano Letivo <span class="text-red-500">*</span>
                    </label>
                    <select id="ano_letivo_id" name="ano_letivo_id" class="<?= $clsCampo('ano_letivo_id') ?>">
                        <option value="">Selecionar...</option>
                        <?php foreach ($anos_letivos as $al):
                            $selAno = $anoLetivoSel > 0
                                ? $anoLetivoSel === (int) $al['id']
                                : !empty($al['ativo']);
                        ?>
                        <option value="<?= (int)$al['id'] ?>" <?= $selAno ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Turma <span class="text-red-500">*</span>
                    </label>
                    <select id="turma_id" name="turma_id" class="<?= $clsCampo('turma_id') ?>">
                        <option value="">Selecionar...</option>
                        <?php foreach ($turmas as $t):
                            $resumoT = is_array($t['vagas_resumo'] ?? null) ? $t['vagas_resumo'] : null;
                            $badgeVaga = '';
                            if ($resumoT) {
                                if (!empty($resumoT['ilimitado'])) {
                                    $badgeVaga = ' · vagas ilimitadas';
                                } else {
                                    $tot = (int) ($resumoT['vagas'] ?? 0);
                                    $ocup = (int) ($resumoT['ocupadas'] ?? 0) + (int) ($resumoT['reservadas'] ?? 0);
                                    $rest = (int) ($resumoT['restantes'] ?? 0);
                                    $badgeVaga = ' · ' . $ocup . '/' . $tot . ($rest <= 0 ? ' lotada' : '');
                                }
                            }
                        ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)($prefill['turma_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= $esc($t['nome']) ?> — <?= $esc($t['serie']) ?> (<?= $esc($t['turno'] ?? '') ?>)<?= $esc($badgeVaga) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="button" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold" data-next-step="2">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Step 2 — Aluno -->
        <div class="step-panel <?= $passoInicial === 2 ? '' : 'hidden' ?> p-6 space-y-6" data-step-panel="2">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Dados do Aluno</h3>
                <p class="mt-1 text-sm text-gray-500">Preencha os dados pessoais e o endereço.</p>
            </div>

            <?php if (!empty($prefill['aluno_id'])): ?>
            <div class="p-4 rounded-lg bg-primary/10 border border-primary/20 flex items-center gap-3">
                <i class="fa-solid fa-user-graduate wizard-accent text-lg"></i>
                <div>
                    <p class="font-semibold text-gray-800 text-sm"><?= $esc($prefill['aluno_nome'] ?? '') ?></p>
                    <?php if (!empty($prefill['aluno_ra'])): ?>
                    <p class="text-xs text-gray-500">RA: <?= $esc($prefill['aluno_ra']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome completo <span class="text-red-500">*</span></label>
                    <input type="text" name="aluno_nome" id="aluno_nome" value="<?= $val('aluno_nome') ?>" class="<?= $clsCampo('aluno_nome') ?>" data-obrigatorio-enturmar="nome">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CPF <?= $isEdit ? '<span class="text-red-500">*</span>' : '' ?></label>
                        <input type="text" name="aluno_cpf" id="aluno_cpf" value="<?= $val('aluno_cpf') ?>" class="<?= $clsCampo('aluno_cpf') ?>" inputmode="numeric" placeholder="000.000.000-00" data-obrigatorio-enturmar="cpf">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">RG</label>
                        <input type="text" name="aluno_rg" id="aluno_rg" value="<?= $val('aluno_rg') ?>" class="<?= $inputClass ?>">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data de nascimento <?= $isEdit ? '<span class="text-red-500">*</span>' : '' ?></label>
                        <input type="date" name="aluno_data_nasc" id="aluno_data_nasc" value="<?= $val('aluno_data_nasc') ?>" class="<?= $clsCampo('aluno_data_nasc') ?>" data-obrigatorio-enturmar="nasc">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Celular / WhatsApp</label>
                        <input type="text" name="aluno_telefone" id="aluno_telefone" value="<?= $val('aluno_telefone') ?>" class="<?= $inputClass ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">E-mail</label>
                        <input type="text" name="aluno_email" id="aluno_email" value="<?= $val('aluno_email') ?>" class="<?= $inputClass ?>" inputmode="email" autocomplete="email">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Escola anterior</label>
                    <input type="text" name="aluno_escola_anterior" id="aluno_escola_anterior" value="<?= $val('aluno_escola_anterior') ?>" class="<?= $inputClass ?>">
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Censo Escolar</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nome da mãe</label>
                            <input type="text" name="aluno_nome_mae" id="aluno_nome_mae" value="<?= $val('aluno_nome_mae') ?>" class="<?= $inputClass ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nome do pai</label>
                            <input type="text" name="aluno_nome_pai" id="aluno_nome_pai" value="<?= $val('aluno_nome_pai') ?>" class="<?= $inputClass ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cor / raça</label>
                            <select name="aluno_cor_raca" id="aluno_cor_raca" class="<?= $inputClass ?>">
                                <?php
                                $corSel = (string) ($prefill['aluno_cor_raca'] ?? '');
                                $coresCenso = ['' => 'Não informado', 'branca' => 'Branca', 'preta' => 'Preta', 'parda' => 'Parda', 'amarela' => 'Amarela', 'indigena' => 'Indígena', 'nao_declarada' => 'Não declarada'];
                                foreach ($coresCenso as $ck => $cl):
                                ?>
                                <option value="<?= $esc($ck) ?>" <?= $corSel === $ck ? 'selected' : '' ?>><?= $esc($cl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nacionalidade</label>
                            <input type="text" name="aluno_nacionalidade" id="aluno_nacionalidade" value="<?= $val('aluno_nacionalidade') ?>" class="<?= $inputClass ?>" placeholder="Brasileira">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Código INEP</label>
                            <input type="text" name="aluno_codigo_inep" id="aluno_codigo_inep" value="<?= $val('aluno_codigo_inep') ?>" class="<?= $inputClass ?>" maxlength="20">
                        </div>
                    </div>
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Endereço</h4>
                    <div class="space-y-4">
                        <div class="max-w-xs">
                            <label class="block text-sm font-medium text-gray-700 mb-2">CEP</label>
                            <div class="flex gap-2">
                                <input type="text" name="aluno_end_cep" id="aluno_end_cep" value="<?= $val('aluno_end_cep') ?>"
                                       class="<?= $inputClass ?> js-cep-aluno" inputmode="numeric" maxlength="9" placeholder="00000-000"
                                       data-cep-prefix="aluno">
                                <button type="button" id="btn-busca-cep-aluno"
                                        class="shrink-0 inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50"
                                        title="Buscar CEP">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400" id="aluno-cep-status"></p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Logradouro</label>
                                <input type="text" name="aluno_endereco" id="aluno_endereco" value="<?= $val('aluno_endereco') ?>" class="<?= $inputClass ?>" placeholder="Rua, avenida...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Número</label>
                                <input type="text" name="aluno_end_numero" id="aluno_end_numero" value="<?= $val('aluno_end_numero') ?>" class="<?= $inputClass ?>">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bairro</label>
                                <input type="text" name="aluno_end_bairro" id="aluno_end_bairro" value="<?= $val('aluno_end_bairro') ?>" class="<?= $inputClass ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Complemento</label>
                                <input type="text" name="aluno_end_complemento" id="aluno_end_complemento" value="<?= $val('aluno_end_complemento') ?>" class="<?= $inputClass ?>" placeholder="Apto, bloco...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cidade</label>
                                <input type="text" name="aluno_end_cidade" id="aluno_end_cidade" value="<?= $val('aluno_end_cidade') ?>" class="<?= $inputClass ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">UF</label>
                                <select name="aluno_end_uf" id="aluno_end_uf" class="<?= $inputClass ?>">
                                    <option value="">UF</option>
                                    <?php
                                    $ufAluno = strtoupper(trim((string) ($prefill['aluno_end_uf'] ?? $_POST['aluno_end_uf'] ?? '')));
                                    foreach ($ufsBrasil as $ufOpt):
                                    ?>
                                    <option value="<?= $esc($ufOpt) ?>" <?= $ufAluno === $ufOpt ? 'selected' : '' ?>><?= $esc($ufOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <button type="button" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-prev-step="1">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
                </button>
                <button type="button" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold" data-next-step="3">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Step 3 — Responsáveis -->
        <div class="step-panel <?= $passoInicial === 3 ? '' : 'hidden' ?> p-6 space-y-6" data-step-panel="3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Responsáveis</h3>
                    <p class="mt-1 text-sm text-gray-500">Adicione um ou mais responsáveis. Marque o acadêmico e o(s) financeiro(s).</p>
                </div>
                <button type="button" id="btn-add-responsavel" class="btn-secondary inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium">
                    <i class="fa-solid fa-plus mr-2"></i> Adicionar responsável
                </button>
            </div>

            <div id="responsaveis-list" class="space-y-4"></div>

            <template id="responsavel-card-template">
                <div class="responsavel-card border border-gray-200 rounded-xl p-4 space-y-4 bg-gray-50/40" data-resp-index="__INDEX__">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-800">Responsável <span class="resp-num">__NUM__</span></h4>
                        <button type="button" class="btn-remove-resp text-xs text-red-500 hover:text-red-700 <?= count($responsaveisPrefill) <= 1 ? 'hidden' : '' ?>">
                            <i class="fa-solid fa-trash mr-1"></i> Remover
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo <span class="text-red-500">*</span></label>
                            <input type="text" name="responsaveis[__INDEX__][nome]" class="resp-nome <?= $inputClass ?>">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                <input type="text" name="responsaveis[__INDEX__][cpf]" class="resp-cpf <?= $inputClass ?>" inputmode="numeric" placeholder="000.000.000-00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                                <input type="text" name="responsaveis[__INDEX__][rg]" class="resp-rg <?= $inputClass ?>">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de nascimento</label>
                                <input type="date" name="responsaveis[__INDEX__][data_nascimento]" class="resp-data_nascimento <?= $inputClass ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Celular</label>
                                <input type="text" name="responsaveis[__INDEX__][telefone]" class="resp-telefone <?= $inputClass ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                <input type="text" name="responsaveis[__INDEX__][email]" class="resp-email <?= $inputClass ?>" inputmode="email" autocomplete="email">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado civil</label>
                                <select name="responsaveis[__INDEX__][estado_civil]" class="resp-estado_civil <?= $inputClass ?>">
                                    <option value="">Selecionar...</option>
                                    <option value="Solteiro(a)">Solteiro(a)</option>
                                    <option value="Casado(a)">Casado(a)</option>
                                    <option value="União estável">União estável</option>
                                    <option value="Divorciado(a)">Divorciado(a)</option>
                                    <option value="Separado(a)">Separado(a)</option>
                                    <option value="Viúvo(a)">Viúvo(a)</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Parentesco</label>
                                <select name="responsaveis[__INDEX__][tipo_vinculo]" class="resp-tipo_vinculo <?= $inputClass ?>">
                                    <option value="">Selecionar...</option>
                                    <option value="Pai">Pai</option>
                                    <option value="Mãe">Mãe</option>
                                    <option value="Avô/Avó">Avô/Avó</option>
                                    <option value="Tio/Tia">Tio/Tia</option>
                                    <option value="Irmão/Irmã">Irmão/Irmã</option>
                                    <option value="Responsável legal">Responsável legal</option>
                                    <option value="Tutor(a)">Tutor(a)</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Profissão</label>
                                <input type="text" name="responsaveis[__INDEX__][profissao]" class="resp-profissao <?= $inputClass ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Empresa onde trabalha</label>
                            <input type="text" name="responsaveis[__INDEX__][empresa]" class="resp-empresa <?= $inputClass ?>">
                        </div>

                        <div class="pt-2 border-t border-gray-200">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-semibold text-gray-800">Endereço</h4>
                                <button type="button" class="btn-copiar-end text-xs wizard-accent hover:underline font-medium">
                                    <i class="fa-solid fa-copy mr-1"></i> Copiar endereço do aluno
                                </button>
                            </div>
                            <div class="space-y-4">
                                <div class="max-w-xs">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                    <div class="flex gap-2">
                                        <input type="text" name="responsaveis[__INDEX__][end_cep]" class="resp-end_cep <?= $inputClass ?>" inputmode="numeric" maxlength="9" placeholder="00000-000">
                                        <button type="button" class="btn-busca-cep-resp shrink-0 inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50" title="Buscar CEP">
                                            <i class="fa-solid fa-magnifying-glass"></i>
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400 resp-cep-status"></p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="md:col-span-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                        <input type="text" name="responsaveis[__INDEX__][endereco]" class="resp-endereco <?= $inputClass ?>" placeholder="Rua, avenida...">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                        <input type="text" name="responsaveis[__INDEX__][end_numero]" class="resp-end_numero <?= $inputClass ?>">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                        <input type="text" name="responsaveis[__INDEX__][end_bairro]" class="resp-end_bairro <?= $inputClass ?>">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                        <input type="text" name="responsaveis[__INDEX__][end_complemento]" class="resp-end_complemento <?= $inputClass ?>" placeholder="Apto, bloco...">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                        <input type="text" name="responsaveis[__INDEX__][end_cidade]" class="resp-end_cidade <?= $inputClass ?>">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                        <select name="responsaveis[__INDEX__][end_uf]" class="resp-end_uf <?= $inputClass ?>">
                                            <?= $ufOptionsHtml ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" name="responsaveis[__INDEX__][is_pedagogico]" value="1" class="resp-is_pedagogico wizard-check rounded border-gray-300">
                                Responsável acadêmico
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" name="responsaveis[__INDEX__][is_financeiro]" value="1" class="resp-is_financeiro wizard-check rounded border-gray-300">
                                Responsável financeiro
                            </label>
                            <div class="resp-percentual-wrap hidden items-center gap-2">
                                <label class="text-sm text-gray-700">Rateio %</label>
                                <input type="number" name="responsaveis[__INDEX__][percentual]" min="0" max="100" step="0.01"
                                       class="resp-percentual w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                       value="100">
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div class="flex justify-between pt-2">
                <button type="button" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-prev-step="2">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
                </button>
                <button type="button" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold" data-next-step="4">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Step 4 — Plano -->
        <div class="step-panel <?= $passoInicial === 4 ? '' : 'hidden' ?> p-6 space-y-6" data-step-panel="4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Plano / Mensalidade</h3>
                <p class="mt-1 text-sm text-gray-500">Selecione o plano financeiro e, se houver, regras de desconto.</p>
            </div>

            <div>
                <label for="plano_mensalidade" class="block text-sm font-medium text-gray-700 mb-2">Plano de mensalidade</label>
                <input type="hidden" name="cobrancas[0][tipo]" value="mensalidade">
                <select id="plano_mensalidade" name="cobrancas[0][plan_id]" class="<?= $inputClass ?>">
                    <option value="">Sem plano (definir depois)</option>
                    <?php foreach ($planos_financeiros as $plano): ?>
                    <option value="<?= (int)$plano['id'] ?>" <?= $mensalidadePlanId === (int)$plano['id'] ? 'selected' : '' ?>>
                        <?= $esc($plano['nome'] ?? ('Plano #' . $plano['id'])) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Composição do plano</h4>
                <div id="plano-composicao" class="rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-6 text-sm text-gray-500 text-center">
                    Selecione um plano para ver os itens.
                </div>
            </div>

            <?php if (!empty($descontos)): ?>
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Regras de desconto</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($descontos as $d):
                        $isPct = ($d['calculo'] ?? '') === 'percentual';
                        $valorLabel = $isPct
                            ? number_format((float) ($d['valor'] ?? 0), 0) . '%'
                            : 'R$ ' . number_format((float) ($d['valor'] ?? 0), 2, ',', '.');
                    ?>
                    <label class="desconto-card relative flex flex-col gap-2 p-4 border-2 border-gray-200 rounded-xl cursor-pointer transition wizard-hover">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="cobrancas[0][desconto_rule_ids][]" value="<?= (int)$d['id'] ?>"
                                   class="desconto-check mt-1 wizard-check rounded border-gray-300">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 leading-snug"><?= $esc($d['nome'] ?? '') ?></p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= $isPct ? 'Percentual' : 'Valor fixo' ?>
                                </p>
                            </div>
                            <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                                  style="background-color: color-mix(in srgb, var(--primary-color) 12%, white); color: var(--primary-color);">
                                <?= $esc($valorLabel) ?>
                            </span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="flex justify-between pt-2">
                <button type="button" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-prev-step="3">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
                </button>
                <button type="button" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold" data-next-step="5">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Step 5 — Revisar -->
        <div class="step-panel <?= $passoInicial === 5 ? '' : 'hidden' ?> p-6 space-y-6" data-step-panel="5">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Revisar</h3>
                <p class="mt-1 text-sm text-gray-500">Confira os dados antes de salvar. Em seguida você poderá gerar o contrato.</p>
            </div>

            <div id="resumo-wizard" class="rounded-lg border border-gray-200 bg-gray-50/50 p-5 space-y-4 text-sm"></div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Observações / Cláusulas especiais</label>
                <textarea name="observacoes" id="observacoes" rows="3"
                          class="<?= $inputClass ?> resize-none"><?= $esc($_POST['observacoes'] ?? $prefill['observacoes'] ?? '') ?></textarea>
            </div>

            <div class="flex justify-between pt-2 border-t border-gray-100">
                <button type="button" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-prev-step="4">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
                </button>
                <button type="submit" id="btn-submit-matricula"
                        class="btn-primary-custom inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm">
                    <?php if ($isEdit): ?>
                    <i class="fa-solid fa-floppy-disk mr-2"></i>
                    Salvar alterações
                    <?php else: ?>
                    <i class="fa-solid fa-file-signature mr-2"></i>
                    Salvar e continuar para contrato
                    <?php endif; ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    var wizard = document.getElementById('matriculaWizard');
    var form = document.getElementById('matriculaForm');
    var currentStep = <?= (int) $passoInicial ?>;
    var isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    var respIndex = 0;
    var prefillResps = <?= json_encode(array_values(array_map(static function ($r) {
        return [
            'nome' => $r['nome'] ?? '',
            'cpf' => $r['cpf'] ?? '',
            'email' => $r['email'] ?? '',
            'telefone' => $r['telefone'] ?? '',
            'tipo_vinculo' => $r['tipo_vinculo'] ?? $r['parentesco'] ?? '',
            'profissao' => $r['profissao'] ?? '',
            'empresa' => $r['empresa'] ?? '',
            'is_financeiro' => !empty($r['is_financeiro']),
            'is_pedagogico' => !empty($r['is_pedagogico']),
            'percentual' => !empty($r['is_financeiro']) ? 100 : null,
        ];
    }, $responsaveisPrefill)), JSON_UNESCAPED_UNICODE) ?>;

    function setStep(n) {
        currentStep = n;
        document.querySelectorAll('[data-step-panel]').forEach(function (el) {
            el.classList.toggle('hidden', parseInt(el.getAttribute('data-step-panel'), 10) !== n);
        });
        document.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            var t = parseInt(btn.getAttribute('data-step-target'), 10);
            var ativo = t === n;
            btn.setAttribute('data-active', ativo ? 'true' : 'false');
            btn.classList.toggle('wizard-active', ativo);
            btn.classList.toggle('wizard-hover', !ativo);
            btn.classList.toggle('border-gray-200', !ativo);
            btn.classList.toggle('bg-white', !ativo);
            btn.classList.toggle('text-gray-600', !ativo);
            var num = btn.querySelector('.step-nav-num');
            if (num) {
                num.classList.toggle('wizard-active-num', ativo);
                num.classList.toggle('border-current', !ativo);
            }
        });
        if (n === 5) buildResumo();
        window.scrollTo({ top: wizard.offsetTop - 16, behavior: 'smooth' });
    }

    function validateStep(n) {
        if (n === 1) {
            var tipo = form.querySelector('input[name="tipo"]:checked');
            var ano = document.getElementById('ano_letivo_id');
            var turma = document.getElementById('turma_id');
            var origem = document.getElementById('origem');
            if (!tipo) { alert('Selecione o tipo de vínculo.'); return false; }
            if (!ano.value) { alert('Selecione o ano letivo.'); ano.focus(); return false; }
            if (!turma.value) { alert('Selecione a turma.'); turma.focus(); return false; }
            if (tipo.value === 'nova' && !origem.value) {
                alert('Informe a origem da matrícula.');
                origem.focus();
                return false;
            }
            return true;
        }
        if (n === 2) {
            var nome = document.getElementById('aluno_nome');
            if (!nome.value.trim()) { alert('Nome do aluno é obrigatório.'); nome.focus(); return false; }
            if (isEdit) {
                var cpfEl = document.getElementById('aluno_cpf');
                var cpf = onlyDigits(cpfEl.value);
                if (cpf.length !== 11) {
                    alert('CPF do aluno é obrigatório (11 dígitos).');
                    cpfEl.focus();
                    return false;
                }
                var nasc = document.getElementById('aluno_data_nasc');
                if (!nasc.value) {
                    alert('Data de nascimento é obrigatória.');
                    nasc.focus();
                    return false;
                }
            }
            return true;
        }
        if (n === 3) {
            var cards = document.querySelectorAll('.responsavel-card');
            if (!cards.length) { alert('Adicione ao menos um responsável.'); return false; }
            var ok = false;
            var somaPct = 0;
            var temFinanceiro = false;
            cards.forEach(function (card) {
                var nm = card.querySelector('.resp-nome');
                if (nm && nm.value.trim()) ok = true;
                var fin = card.querySelector('.resp-is_financeiro');
                if (fin && fin.checked) {
                    temFinanceiro = true;
                    var pctEl = card.querySelector('.resp-percentual');
                    somaPct += parseFloat(pctEl && pctEl.value ? pctEl.value : '0') || 0;
                }
            });
            if (!ok) { alert('Informe o nome de pelo menos um responsável.'); return false; }
            if (temFinanceiro && Math.abs(somaPct - 100) > 0.5) {
                alert('O rateio dos responsáveis financeiros deve somar 100% (atual: ' + somaPct.toFixed(1) + '%).');
                return false;
            }
            if (!temFinanceiro) {
                alert('Marque ao menos um responsável financeiro.');
                return false;
            }
            syncLegacyResp();
            return true;
        }
        return true;
    }

    document.querySelectorAll('[data-next-step]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = parseInt(btn.getAttribute('data-next-step'), 10);
            if (!validateStep(currentStep)) return;
            setStep(next);
        });
    });
    document.querySelectorAll('[data-prev-step]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setStep(parseInt(btn.getAttribute('data-prev-step'), 10));
        });
    });
    document.querySelectorAll('.step-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = parseInt(btn.getAttribute('data-step-target'), 10);
            if (target > currentStep) {
                for (var s = currentStep; s < target; s++) {
                    if (!validateStep(s)) return;
                }
            }
            setStep(target);
        });
    });

    // Tipo cards
    function syncTipoUI() {
        var checked = form.querySelector('input[name="tipo"]:checked');
        var tipo = checked ? checked.value : 'nova';
        document.querySelectorAll('.tipo-card').forEach(function (card) {
            var input = card.querySelector('input');
            var on = input && input.checked;
            card.classList.toggle('wizard-active', on);
            card.classList.toggle('border-gray-200', !on);
        });
        var origemWrap = document.getElementById('origem-wrap');
        if (origemWrap) origemWrap.classList.toggle('hidden', tipo !== 'nova');
        var origem = document.getElementById('origem');
        if (origem && tipo !== 'nova' && !origem.value) origem.value = 'interno';
    }
    form.querySelectorAll('input[name="tipo"]').forEach(function (r) {
        r.addEventListener('change', syncTipoUI);
    });
    syncTipoUI();

    // Responsáveis
    var list = document.getElementById('responsaveis-list');
    var tpl = document.getElementById('responsavel-card-template');

    function onlyDigits(v) {
        return String(v || '').replace(/\D+/g, '');
    }

    function marcarCampoVazio(el, vazio) {
        if (!el) return;
        el.classList.toggle('campo-obrigatorio-vazio', !!vazio);
    }
    function atualizarCamposObrigatorios() {
        if (!isEdit) return;
        var nome = document.getElementById('aluno_nome');
        var cpf = document.getElementById('aluno_cpf');
        var nasc = document.getElementById('aluno_data_nasc');
        var ano = document.getElementById('ano_letivo_id');
        var turma = document.getElementById('turma_id');
        marcarCampoVazio(nome, !(nome && nome.value.trim()));
        marcarCampoVazio(cpf, onlyDigits(cpf && cpf.value).length !== 11);
        marcarCampoVazio(nasc, !(nasc && nasc.value));
        marcarCampoVazio(ano, !(ano && ano.value));
        marcarCampoVazio(turma, !(turma && turma.value));
    }
    ['aluno_nome', 'aluno_cpf', 'aluno_data_nasc', 'ano_letivo_id', 'turma_id'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', atualizarCamposObrigatorios);
        el.addEventListener('change', atualizarCamposObrigatorios);
    });

    function maskCep(v) {
        var d = onlyDigits(v).slice(0, 8);
        if (d.length > 5) return d.slice(0, 5) + '-' + d.slice(5);
        return d;
    }

    function montarEnderecoAluno() {
        var parts = [];
        var log = (document.getElementById('aluno_endereco').value || '').trim();
        var num = (document.getElementById('aluno_end_numero').value || '').trim();
        var comp = (document.getElementById('aluno_end_complemento').value || '').trim();
        var bairro = (document.getElementById('aluno_end_bairro').value || '').trim();
        var cidade = (document.getElementById('aluno_end_cidade').value || '').trim();
        var uf = (document.getElementById('aluno_end_uf').value || '').trim();
        var cep = (document.getElementById('aluno_end_cep').value || '').trim();
        if (log) parts.push(log + (num ? ', ' + num : '') + (comp ? ' (' + comp + ')' : ''));
        else if (num) parts.push('nº ' + num + (comp ? ' (' + comp + ')' : ''));
        if (bairro) parts.push(bairro);
        if (cidade || uf) parts.push([cidade, uf].filter(Boolean).join('/'));
        if (cep) parts.push('CEP ' + cep);
        return parts.join(' — ');
    }

    function buscarCep(cep, onOk, onErr) {
        var digits = onlyDigits(cep);
        if (digits.length !== 8) {
            if (onErr) onErr('CEP inválido');
            return;
        }
        fetch('https://viacep.com.br/ws/' + digits + '/json/')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.erro) {
                    if (onErr) onErr('CEP não encontrado');
                    return;
                }
                if (onOk) onOk(data);
            })
            .catch(function () {
                if (onErr) onErr('Falha ao consultar CEP');
            });
    }

    function bindCepAluno() {
        var cepInput = document.getElementById('aluno_end_cep');
        var statusEl = document.getElementById('aluno-cep-status');
        var btn = document.getElementById('btn-busca-cep-aluno');
        if (!cepInput) return;

        function aplicar() {
            if (statusEl) statusEl.textContent = 'Buscando...';
            buscarCep(cepInput.value, function (data) {
                if (data.logradouro) document.getElementById('aluno_endereco').value = data.logradouro;
                if (data.bairro) document.getElementById('aluno_end_bairro').value = data.bairro;
                if (data.localidade) document.getElementById('aluno_end_cidade').value = data.localidade;
                if (data.uf) document.getElementById('aluno_end_uf').value = data.uf;
                if (statusEl) statusEl.textContent = 'Endereço preenchido.';
                var num = document.getElementById('aluno_end_numero');
                if (num) num.focus();
            }, function (msg) {
                if (statusEl) statusEl.textContent = msg;
            });
        }

        cepInput.addEventListener('input', function () {
            cepInput.value = maskCep(cepInput.value);
        });
        cepInput.addEventListener('blur', function () {
            if (onlyDigits(cepInput.value).length === 8) aplicar();
        });
        if (btn) btn.addEventListener('click', aplicar);
    }
    bindCepAluno();

    function bindCard(card) {
        var fin = card.querySelector('.resp-is_financeiro');
        var wrap = card.querySelector('.resp-percentual-wrap');
        function syncPct() {
            if (!wrap) return;
            wrap.classList.toggle('hidden', !(fin && fin.checked));
            wrap.classList.toggle('flex', !!(fin && fin.checked));
        }
        if (fin) fin.addEventListener('change', syncPct);
        syncPct();

        var copyBtn = card.querySelector('.btn-copiar-end');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var map = [
                    ['aluno_endereco', '.resp-endereco'],
                    ['aluno_end_numero', '.resp-end_numero'],
                    ['aluno_end_complemento', '.resp-end_complemento'],
                    ['aluno_end_bairro', '.resp-end_bairro'],
                    ['aluno_end_cidade', '.resp-end_cidade'],
                    ['aluno_end_uf', '.resp-end_uf'],
                    ['aluno_end_cep', '.resp-end_cep'],
                ];
                map.forEach(function (pair) {
                    var src = document.getElementById(pair[0]);
                    var dest = card.querySelector(pair[1]);
                    if (src && dest) dest.value = src.value || '';
                });
            });
        }

        var cepInput = card.querySelector('.resp-end_cep');
        var statusEl = card.querySelector('.resp-cep-status');
        var btnCep = card.querySelector('.btn-busca-cep-resp');
        function aplicarResp() {
            if (!cepInput) return;
            if (statusEl) statusEl.textContent = 'Buscando...';
            buscarCep(cepInput.value, function (data) {
                var end = card.querySelector('.resp-endereco');
                var bairro = card.querySelector('.resp-end_bairro');
                var cidade = card.querySelector('.resp-end_cidade');
                var uf = card.querySelector('.resp-end_uf');
                if (end && data.logradouro) end.value = data.logradouro;
                if (bairro && data.bairro) bairro.value = data.bairro;
                if (cidade && data.localidade) cidade.value = data.localidade;
                if (uf && data.uf) uf.value = data.uf;
                if (statusEl) statusEl.textContent = 'Endereço preenchido.';
                var num = card.querySelector('.resp-end_numero');
                if (num) num.focus();
            }, function (msg) {
                if (statusEl) statusEl.textContent = msg;
            });
        }
        if (cepInput) {
            cepInput.addEventListener('input', function () {
                cepInput.value = maskCep(cepInput.value);
            });
            cepInput.addEventListener('blur', function () {
                if (onlyDigits(cepInput.value).length === 8) aplicarResp();
            });
        }
        if (btnCep) btnCep.addEventListener('click', aplicarResp);

        var rem = card.querySelector('.btn-remove-resp');
        if (rem) {
            rem.addEventListener('click', function () {
                if (list.querySelectorAll('.responsavel-card').length <= 1) return;
                card.remove();
                renumberCards();
            });
        }
    }

    function renumberCards() {
        var cards = list.querySelectorAll('.responsavel-card');
        cards.forEach(function (card, i) {
            var num = card.querySelector('.resp-num');
            if (num) num.textContent = String(i + 1);
            var rem = card.querySelector('.btn-remove-resp');
            if (rem) rem.classList.toggle('hidden', cards.length <= 1);
        });
    }

    function addResponsavel(data) {
        data = data || {};
        var html = tpl.innerHTML
            .replace(/__INDEX__/g, String(respIndex))
            .replace(/__NUM__/g, String(list.querySelectorAll('.responsavel-card').length + 1));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var card = wrap.firstElementChild;
        list.appendChild(card);

        var map = {
            nome: '.resp-nome', cpf: '.resp-cpf', rg: '.resp-rg',
            data_nascimento: '.resp-data_nascimento', estado_civil: '.resp-estado_civil',
            tipo_vinculo: '.resp-tipo_vinculo', telefone: '.resp-telefone', email: '.resp-email',
            profissao: '.resp-profissao', empresa: '.resp-empresa', endereco: '.resp-endereco',
            end_cep: '.resp-end_cep', end_numero: '.resp-end_numero', end_complemento: '.resp-end_complemento',
            end_bairro: '.resp-end_bairro', end_cidade: '.resp-end_cidade', end_uf: '.resp-end_uf'
        };
        Object.keys(map).forEach(function (k) {
            var el = card.querySelector(map[k]);
            if (!el || data[k] == null || data[k] === '') return;
            var val = String(data[k]);
            if (k === 'tipo_vinculo') {
                var parentescoMap = {
                    pai: 'Pai', mae: 'Mãe', avo: 'Avô/Avó', tio: 'Tio/Tia',
                    responsavel: 'Responsável legal', outro: 'Outro'
                };
                val = parentescoMap[val.toLowerCase()] || val;
            }
            el.value = val;
            // Se valor legado não estiver nas opções, mantém vazio (select)
            if (el.tagName === 'SELECT' && el.value !== val) {
                el.value = '';
            }
        });
        var ped = card.querySelector('.resp-is_pedagogico');
        var fin = card.querySelector('.resp-is_financeiro');
        var pct = card.querySelector('.resp-percentual');
        if (ped && data.is_pedagogico) ped.checked = true;
        if (fin && data.is_financeiro) fin.checked = true;
        if (pct && data.percentual != null) pct.value = data.percentual;
        if (list.querySelectorAll('.responsavel-card').length === 1 && !data.is_pedagogico && !data.is_financeiro) {
            if (ped) ped.checked = true;
            if (fin) fin.checked = true;
        }

        bindCard(card);
        respIndex++;
        renumberCards();
    }

    document.getElementById('btn-add-responsavel').addEventListener('click', function () {
        addResponsavel({});
    });

    if (prefillResps.length) {
        prefillResps.forEach(function (r) { addResponsavel(r); });
    } else {
        addResponsavel({});
    }

    function syncLegacyResp() {
        var cards = list.querySelectorAll('.responsavel-card');
        var primary = null;
        cards.forEach(function (card) {
            var ped = card.querySelector('.resp-is_pedagogico');
            if (!primary && ped && ped.checked) primary = card;
        });
        if (!primary && cards[0]) primary = cards[0];
        if (!primary) return;
        var get = function (sel) {
            var el = primary.querySelector(sel);
            return el ? el.value : '';
        };
        document.getElementById('resp_nome').value = get('.resp-nome');
        document.getElementById('resp_cpf').value = get('.resp-cpf');
        document.getElementById('resp_email').value = get('.resp-email');
        document.getElementById('resp_telefone').value = get('.resp-telefone');
        document.getElementById('resp_parentesco').value = get('.resp-tipo_vinculo');
        var parts = [];
        var log = get('.resp-endereco');
        var num = get('.resp-end_numero');
        var comp = get('.resp-end_complemento');
        var bairro = get('.resp-end_bairro');
        var cidade = get('.resp-end_cidade');
        var uf = get('.resp-end_uf');
        var cep = get('.resp-end_cep');
        if (log) parts.push(log + (num ? ', ' + num : '') + (comp ? ' (' + comp + ')' : ''));
        if (bairro) parts.push(bairro);
        if (cidade || uf) parts.push([cidade, uf].filter(Boolean).join('/'));
        if (cep) parts.push('CEP ' + cep);
        document.getElementById('resp_endereco').value = parts.join(' — ') || log;
    }

    // Plano composição
    var planoSelect = document.getElementById('plano_mensalidade');
    var composicao = document.getElementById('plano-composicao');
    var financePlanInput = document.getElementById('finance_plan_id');

    function loadPlanoItens() {
        var id = parseInt(planoSelect.value || '0', 10);
        if (financePlanInput) financePlanInput.value = id > 0 ? String(id) : '';
        if (id <= 0) {
            composicao.className = 'rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-6 text-sm text-gray-500 text-center';
            composicao.innerHTML = 'Selecione um plano para ver os itens.';
            return;
        }
        composicao.className = 'rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-6 text-sm text-gray-500 text-center';
        composicao.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Carregando composição...';
        fetch('<?= URL ?>/admin/enrollment/plano/' + id + '/itens')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.itens || !data.itens.length) {
                    composicao.className = 'rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-6 text-sm text-gray-500 text-center';
                    composicao.innerHTML = 'Plano sem itens cadastrados.';
                    return;
                }
                var cards = data.itens.map(function (it) {
                    var valor = Number(it.valor_base || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                    var parcela = Number(it.valor_parcela || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                    var np = parseInt(it.num_parcelas || 1, 10);
                    var cat = escHtml(it.categoria_label || it.categoria || 'Item');
                    var desc = escHtml(it.descricao || '—');
                    var parcelasTxt = np > 1
                        ? np + 'x de ' + parcela
                        : 'À vista';
                    return ''
                        + '<div class="rounded-xl border-2 border-gray-200 bg-white p-4 shadow-sm">'
                        +   '<div class="flex items-start justify-between gap-3">'
                        +     '<div class="min-w-0">'
                        +       '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold mb-2"'
                        +         ' style="background-color: color-mix(in srgb, var(--primary-color) 12%, white); color: var(--primary-color);">' + cat + '</span>'
                        +       '<p class="text-sm font-semibold text-gray-900 leading-snug">' + desc + '</p>'
                        +       '<p class="mt-1 text-xs text-gray-500">' + parcelasTxt + '</p>'
                        +     '</div>'
                        +     '<div class="text-right shrink-0">'
                        +       '<p class="text-base font-bold text-gray-900">' + valor + '</p>'
                        +       '<p class="text-[11px] text-gray-400">total</p>'
                        +     '</div>'
                        +   '</div>'
                        + '</div>';
                }).join('');
                var total = data.itens.reduce(function (acc, it) { return acc + Number(it.valor_base || 0); }, 0);
                var totalFmt = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                composicao.className = 'space-y-3';
                composicao.innerHTML =
                    '<div class="flex items-center justify-between gap-3 px-1">'
                    +   '<p class="text-sm font-medium text-gray-800">' + escHtml((data.plano && data.plano.nome) || 'Plano') + '</p>'
                    +   '<p class="text-sm text-gray-500">' + data.itens.length + ' item(ns) · <span class="font-semibold text-gray-800">' + totalFmt + '</span></p>'
                    + '</div>'
                    + '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' + cards + '</div>';
            })
            .catch(function () {
                composicao.className = 'rounded-xl border border-dashed border-red-200 bg-red-50/50 p-6 text-sm text-red-600 text-center';
                composicao.innerHTML = 'Falha ao carregar itens do plano.';
            });
    }
    if (planoSelect) {
        planoSelect.addEventListener('change', loadPlanoItens);
        if (planoSelect.value) loadPlanoItens();
    }

    document.querySelectorAll('.desconto-card').forEach(function (card) {
        var cb = card.querySelector('.desconto-check');
        function sync() {
            card.classList.toggle('wizard-active', !!(cb && cb.checked));
            card.classList.toggle('border-gray-200', !(cb && cb.checked));
        }
        if (cb) cb.addEventListener('change', sync);
        sync();
    });

    function buildResumo() {
        syncLegacyResp();
        var tipoEl = form.querySelector('input[name="tipo"]:checked');
        var tipoLabels = { nova: 'Matrícula (nova)', rematricula: 'Rematrícula', transferencia: 'Transferência' };
        var anoOpt = document.getElementById('ano_letivo_id').selectedOptions[0];
        var turmaOpt = document.getElementById('turma_id').selectedOptions[0];
        var planoOpt = planoSelect.selectedOptions[0];
        var origemOpt = document.getElementById('origem').selectedOptions[0];

        var respHtml = '';
        list.querySelectorAll('.responsavel-card').forEach(function (card, i) {
            var nome = (card.querySelector('.resp-nome').value || '').trim();
            if (!nome) return;
            var flags = [];
            if (card.querySelector('.resp-is_pedagogico').checked) flags.push('Acadêmico');
            if (card.querySelector('.resp-is_financeiro').checked) {
                var pct = card.querySelector('.resp-percentual').value;
                flags.push('Financeiro' + (pct ? ' ' + pct + '%' : ''));
            }
            respHtml += '<li>' + escHtml(nome)
                + (flags.length ? ' <span class="text-xs text-gray-500">(' + escHtml(flags.join(', ')) + ')</span>' : '')
                + '</li>';
        });

        document.getElementById('resumo-wizard').innerHTML =
            '<div><p class="text-xs text-gray-400 uppercase tracking-wide">Vínculo</p>'
            + '<p class="font-medium text-gray-800">' + escHtml(tipoLabels[tipoEl ? tipoEl.value : ''] || '—')
            + (origemOpt && origemOpt.value ? ' · Origem: ' + escHtml(origemOpt.textContent) : '')
            + '</p>'
            + '<p class="text-gray-600">' + escHtml(anoOpt ? anoOpt.textContent : '—')
            + ' · ' + escHtml(turmaOpt ? turmaOpt.textContent : '—') + '</p></div>'
            + '<div><p class="text-xs text-gray-400 uppercase tracking-wide">Aluno</p>'
            + '<p class="font-medium text-gray-800">' + escHtml(document.getElementById('aluno_nome').value) + '</p>'
            + '<p class="text-gray-600">' + escHtml(montarEnderecoAluno() || 'Endereço não informado') + '</p></div>'
            + '<div><p class="text-xs text-gray-400 uppercase tracking-wide">Responsáveis</p>'
            + '<ul class="list-disc ml-5 text-gray-700">' + (respHtml || '<li>—</li>') + '</ul></div>'
            + '<div><p class="text-xs text-gray-400 uppercase tracking-wide">Plano</p>'
            + '<p class="font-medium text-gray-800">' + escHtml(planoOpt && planoOpt.value ? planoOpt.textContent : 'Sem plano') + '</p></div>';
    }

    form.addEventListener('submit', function (e) {
        for (var s = 1; s <= 4; s++) {
            if (!validateStep(s)) {
                e.preventDefault();
                setStep(s);
                return;
            }
        }
        syncLegacyResp();
        if (financePlanInput && planoSelect) {
            financePlanInput.value = planoSelect.value || '';
        }
        var btn = document.getElementById('btn-submit-matricula');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.classList.add('opacity-70', 'pointer-events-none');
            btn.setAttribute('aria-busy', 'true');
        }
    });

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</script>
