<?php
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$old = is_array($old ?? null) ? $old : [];
$erros = is_array($erros ?? null) ? $erros : [];
$val = static function (array $old, string $key, $default = '') {
    return $old[$key] ?? $default;
};
$anoPadrao = (int) ($ano_letivo_padrao ?? 0);
$turmas = $turmas ?? [];
$anos_letivos = $anos_letivos ?? [];
$inputClass = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500';

require_once dirname(__DIR__, 4) . '/Helpers/StudentFormHelper.php';
$ufsBrasil = \StudentFormHelper::ufsBrasil();
$ufOptionsHtml = '<option value="">UF</option>';
foreach ($ufsBrasil as $ufOpt) {
    $ufOptionsHtml .= '<option value="' . htmlspecialchars($ufOpt, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($ufOpt, ENT_QUOTES, 'UTF-8') . '</option>';
}

$tiposDocumento = [
    'rg' => 'RG',
    'cpf' => 'CPF',
    'certidao' => 'Certidão de nascimento',
    'comprovante_residencia' => 'Comprovante de residência',
    'historico' => 'Histórico escolar',
    'outro' => 'Outro',
];

$oldResps = [];
if (!empty($old['responsaveis']) && is_array($old['responsaveis'])) {
    foreach ($old['responsaveis'] as $r) {
        if (is_array($r) && trim((string) ($r['nome'] ?? '')) !== '') {
            $oldResps[] = $r;
        }
    }
}
$resp0 = $oldResps[0] ?? [];
$respExtras = array_slice($oldResps, 1);

$steps = [
    ['n' => 1, 'label' => 'Vínculo', 'sub' => 'Ano e turma'],
    ['n' => 2, 'label' => 'Aluno', 'sub' => 'Dados pessoais'],
    ['n' => 3, 'label' => 'Responsáveis', 'sub' => 'Família'],
    ['n' => 4, 'label' => 'Documentos', 'sub' => 'Anexos'],
];
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden" id="captacaoWizard">
    <div class="bg-blue-600 text-white px-6 py-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-hand-holding-heart text-white text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg">Interesse de matrícula</h1>
                <p class="text-blue-100 text-sm">Preencha os dados. Plano e rateio são definidos depois pela escola.</p>
            </div>
        </div>
    </div>

    <form method="POST" action="<?= URL ?>/matricula/interesse" enctype="multipart/form-data" id="captacaoForm" novalidate class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <input type="hidden" name="resp_nome" id="resp_nome" value="<?= $esc($val($old, 'resp_nome', $resp0['nome'] ?? '')) ?>">
        <input type="hidden" name="resp_cpf" id="resp_cpf" value="<?= $esc($val($old, 'resp_cpf', $resp0['cpf'] ?? '')) ?>">
        <input type="hidden" name="resp_email" id="resp_email" value="<?= $esc($val($old, 'resp_email', $resp0['email'] ?? '')) ?>">
        <input type="hidden" name="resp_telefone" id="resp_telefone" value="<?= $esc($val($old, 'resp_telefone', $resp0['telefone'] ?? '')) ?>">
        <input type="hidden" name="resp_parentesco" id="resp_parentesco" value="<?= $esc($val($old, 'resp_parentesco', $resp0['tipo_vinculo'] ?? '')) ?>">
        <input type="hidden" name="resp_endereco" id="resp_endereco" value="<?= $esc($val($old, 'resp_endereco', $resp0['endereco'] ?? '')) ?>">

        <?php if ($erros !== []): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3 space-y-1">
            <?php foreach ($erros as $erro): ?>
            <p><?= $esc($erro) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-0" role="tablist">
            <?php foreach ($steps as $i => $s): ?>
            <button type="button" data-step-target="<?= (int) $s['n'] ?>"
                    class="step-nav-btn group flex items-center gap-3 rounded-xl border-2 px-3 py-2.5 text-left transition sm:flex-1
                           <?= $s['n'] === 1 ? 'border-blue-600 bg-blue-50 text-gray-900' : 'border-gray-200 bg-white text-gray-600' ?>">
                <span class="step-nav-num flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold border-2
                             <?= $s['n'] === 1 ? 'bg-blue-600 border-blue-600 text-white' : 'border-current' ?>"><?= (int) $s['n'] ?></span>
                <span class="min-w-0">
                    <span class="block font-semibold leading-tight text-sm"><?= $esc($s['label']) ?></span>
                    <span class="block text-xs opacity-80"><?= $esc($s['sub']) ?></span>
                </span>
            </button>
            <?php if ($i < count($steps) - 1): ?>
            <div class="hidden sm:block w-4 h-0.5 bg-gray-200 shrink-0" aria-hidden="true"></div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Passo 1 -->
        <div class="step-panel space-y-4" data-step-panel="1">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Vínculo</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="ano_letivo_id">Ano letivo <span class="text-red-500">*</span></label>
                    <select id="ano_letivo_id" name="ano_letivo_id" class="<?= $inputClass ?>">
                        <option value="">Selecione</option>
                        <?php foreach ($anos_letivos as $al):
                            $idAl = (int) ($al['id'] ?? 0);
                            $sel = (int) $val($old, 'ano_letivo_id', $anoPadrao) === $idAl;
                        ?>
                        <option value="<?= $idAl ?>" <?= $sel ? 'selected' : '' ?>><?= $esc($al['ano'] ?? $idAl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="turma_id">Turma desejada</label>
                    <select id="turma_id" name="turma_id" class="<?= $inputClass ?>">
                        <option value="">Selecione (se souber)</option>
                        <?php foreach ($turmas as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $val($old, 'turma_id', 0) === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= $esc($t['nome'] ?? '') ?> — <?= $esc($t['serie'] ?? '') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="button" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white" data-next-step="2">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Passo 2 -->
        <div class="step-panel hidden space-y-4" data-step-panel="2">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Dados do aluno</h2>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_nome">Nome completo <span class="text-red-500">*</span></label>
                <input type="text" id="aluno_nome" name="aluno_nome" maxlength="255" value="<?= $esc($val($old, 'aluno_nome')) ?>" class="<?= $inputClass ?>">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_cpf">CPF <span class="text-red-500">*</span></label>
                    <input type="text" id="aluno_cpf" name="aluno_cpf" maxlength="14" inputmode="numeric" placeholder="000.000.000-00" value="<?= $esc($val($old, 'aluno_cpf')) ?>" class="<?= $inputClass ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_rg">RG</label>
                    <input type="text" id="aluno_rg" name="aluno_rg" maxlength="30" value="<?= $esc($val($old, 'aluno_rg')) ?>" class="<?= $inputClass ?>">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_data_nasc">Nascimento <span class="text-red-500">*</span></label>
                    <input type="date" id="aluno_data_nasc" name="aluno_data_nasc" value="<?= $esc($val($old, 'aluno_data_nasc')) ?>" class="<?= $inputClass ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_telefone">Celular / WhatsApp</label>
                    <input type="text" id="aluno_telefone" name="aluno_telefone" maxlength="20" value="<?= $esc($val($old, 'aluno_telefone')) ?>" class="<?= $inputClass ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_email">E-mail</label>
                    <input type="text" id="aluno_email" name="aluno_email" maxlength="255" inputmode="email" value="<?= $esc($val($old, 'aluno_email')) ?>" class="<?= $inputClass ?>">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_escola_anterior">Escola anterior</label>
                <input type="text" id="aluno_escola_anterior" name="aluno_escola_anterior" maxlength="255" value="<?= $esc($val($old, 'aluno_escola_anterior')) ?>" class="<?= $inputClass ?>">
            </div>

            <div class="pt-2 border-t border-gray-100">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Endereço</h3>
                <div class="max-w-xs mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_end_cep">CEP</label>
                    <div class="flex gap-2">
                        <input type="text" id="aluno_end_cep" name="aluno_end_cep" maxlength="9" inputmode="numeric" placeholder="00000-000" value="<?= $esc($val($old, 'aluno_end_cep')) ?>" class="<?= $inputClass ?>">
                        <button type="button" id="btn-busca-cep-aluno" class="shrink-0 inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400" id="aluno-cep-status"></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="sm:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_endereco">Logradouro</label>
                        <input type="text" id="aluno_endereco" name="aluno_endereco" value="<?= $esc($val($old, 'aluno_endereco')) ?>" class="<?= $inputClass ?>" placeholder="Rua, avenida...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_end_numero">Número</label>
                        <input type="text" id="aluno_end_numero" name="aluno_end_numero" value="<?= $esc($val($old, 'aluno_end_numero')) ?>" class="<?= $inputClass ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_end_bairro">Bairro</label>
                        <input type="text" id="aluno_end_bairro" name="aluno_end_bairro" value="<?= $esc($val($old, 'aluno_end_bairro')) ?>" class="<?= $inputClass ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_end_complemento">Complemento</label>
                        <input type="text" id="aluno_end_complemento" name="aluno_end_complemento" value="<?= $esc($val($old, 'aluno_end_complemento')) ?>" class="<?= $inputClass ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_end_cidade">Cidade</label>
                        <input type="text" id="aluno_end_cidade" name="aluno_end_cidade" value="<?= $esc($val($old, 'aluno_end_cidade')) ?>" class="<?= $inputClass ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="aluno_end_uf">UF</label>
                        <select id="aluno_end_uf" name="aluno_end_uf" class="<?= $inputClass ?>">
                            <option value="">UF</option>
                            <?php
                            $ufAluno = strtoupper(trim((string) $val($old, 'aluno_end_uf')));
                            foreach ($ufsBrasil as $ufOpt):
                            ?>
                            <option value="<?= $esc($ufOpt) ?>" <?= $ufAluno === $ufOpt ? 'selected' : '' ?>><?= $esc($ufOpt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <button type="button" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-prev-step="1">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
                </button>
                <button type="button" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white" data-next-step="3">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Passo 3 -->
        <div class="step-panel hidden space-y-4" data-step-panel="3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Responsáveis</h2>
                    <p class="text-xs text-gray-500 mt-1">O rateio financeiro é definido depois pela coordenação.</p>
                </div>
                <button type="button" id="btn-add-responsavel" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fa-solid fa-plus mr-2"></i> Adicionar
                </button>
            </div>

            <div id="responsaveis-list" class="space-y-4">
                <div class="responsavel-card border border-gray-200 rounded-xl p-4 space-y-4 bg-gray-50/40" data-resp-index="0">
                    <h3 class="text-sm font-semibold text-gray-800">Responsável <span class="resp-num">1</span></h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo <span class="text-red-500">*</span></label>
                        <input type="text" name="responsaveis[0][nome]" class="resp-nome <?= $inputClass ?>" value="<?= $esc($resp0['nome'] ?? $val($old, 'resp_nome')) ?>">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                            <input type="text" name="responsaveis[0][cpf]" class="resp-cpf <?= $inputClass ?>" inputmode="numeric" placeholder="000.000.000-00" value="<?= $esc($resp0['cpf'] ?? $val($old, 'resp_cpf')) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                            <input type="text" name="responsaveis[0][rg]" class="resp-rg <?= $inputClass ?>" value="<?= $esc($resp0['rg'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nascimento</label>
                            <input type="date" name="responsaveis[0][data_nascimento]" class="resp-data_nascimento <?= $inputClass ?>" value="<?= $esc($resp0['data_nascimento'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Celular <span class="text-red-500">*</span></label>
                            <input type="text" name="responsaveis[0][telefone]" class="resp-telefone <?= $inputClass ?>" value="<?= $esc($resp0['telefone'] ?? $val($old, 'resp_telefone')) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                            <input type="text" name="responsaveis[0][email]" class="resp-email <?= $inputClass ?>" inputmode="email" value="<?= $esc($resp0['email'] ?? $val($old, 'resp_email')) ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado civil</label>
                            <select name="responsaveis[0][estado_civil]" class="resp-estado_civil <?= $inputClass ?>">
                                <option value="">Selecionar...</option>
                                <?php foreach (['Solteiro(a)', 'Casado(a)', 'União estável', 'Divorciado(a)', 'Separado(a)', 'Viúvo(a)', 'Outro'] as $ec): ?>
                                <option value="<?= $esc($ec) ?>" <?= ($resp0['estado_civil'] ?? '') === $ec ? 'selected' : '' ?>><?= $esc($ec) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Parentesco</label>
                            <select name="responsaveis[0][tipo_vinculo]" class="resp-tipo_vinculo <?= $inputClass ?>">
                                <option value="">Selecionar...</option>
                                <?php foreach (['Pai', 'Mãe', 'Avô/Avó', 'Tio/Tia', 'Irmão/Irmã', 'Responsável legal', 'Tutor(a)', 'Outro'] as $pv): ?>
                                <option value="<?= $esc($pv) ?>" <?= ($resp0['tipo_vinculo'] ?? $val($old, 'resp_parentesco')) === $pv ? 'selected' : '' ?>><?= $esc($pv) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Profissão</label>
                            <input type="text" name="responsaveis[0][profissao]" class="resp-profissao <?= $inputClass ?>" value="<?= $esc($resp0['profissao'] ?? '') ?>">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Empresa onde trabalha</label>
                        <input type="text" name="responsaveis[0][empresa]" class="resp-empresa <?= $inputClass ?>" value="<?= $esc($resp0['empresa'] ?? '') ?>">
                    </div>
                    <div class="pt-2 border-t border-gray-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-800">Endereço</h4>
                            <button type="button" class="btn-copiar-end text-xs text-blue-600 hover:underline font-medium">
                                <i class="fa-solid fa-copy mr-1"></i> Copiar endereço do aluno
                            </button>
                        </div>
                        <div class="max-w-xs">
                            <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                            <div class="flex gap-2">
                                <input type="text" name="responsaveis[0][end_cep]" class="resp-end_cep <?= $inputClass ?>" inputmode="numeric" maxlength="9" placeholder="00000-000" value="<?= $esc($resp0['end_cep'] ?? '') ?>">
                                <button type="button" class="btn-busca-cep-resp shrink-0 inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400 resp-cep-status"></p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                <input type="text" name="responsaveis[0][endereco]" class="resp-endereco <?= $inputClass ?>" value="<?= $esc($resp0['endereco'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                <input type="text" name="responsaveis[0][end_numero]" class="resp-end_numero <?= $inputClass ?>" value="<?= $esc($resp0['end_numero'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                <input type="text" name="responsaveis[0][end_bairro]" class="resp-end_bairro <?= $inputClass ?>" value="<?= $esc($resp0['end_bairro'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                <input type="text" name="responsaveis[0][end_complemento]" class="resp-end_complemento <?= $inputClass ?>" value="<?= $esc($resp0['end_complemento'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                <input type="text" name="responsaveis[0][end_cidade]" class="resp-end_cidade <?= $inputClass ?>" value="<?= $esc($resp0['end_cidade'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                                <select name="responsaveis[0][end_uf]" class="resp-end_uf <?= $inputClass ?>">
                                    <?= $ufOptionsHtml ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template id="responsavel-card-template">
                <div class="responsavel-card border border-gray-200 rounded-xl p-4 space-y-4 bg-gray-50/40" data-resp-index="__INDEX__">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">Responsável <span class="resp-num">__NUM__</span></h3>
                        <button type="button" class="btn-remove-resp text-xs text-red-500 hover:text-red-700">
                            <i class="fa-solid fa-trash mr-1"></i> Remover
                        </button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo <span class="text-red-500">*</span></label>
                        <input type="text" name="responsaveis[__INDEX__][nome]" class="resp-nome <?= $inputClass ?>">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                            <input type="text" name="responsaveis[__INDEX__][cpf]" class="resp-cpf <?= $inputClass ?>" inputmode="numeric" placeholder="000.000.000-00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                            <input type="text" name="responsaveis[__INDEX__][rg]" class="resp-rg <?= $inputClass ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nascimento</label>
                            <input type="date" name="responsaveis[__INDEX__][data_nascimento]" class="resp-data_nascimento <?= $inputClass ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Celular</label>
                            <input type="text" name="responsaveis[__INDEX__][telefone]" class="resp-telefone <?= $inputClass ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                            <input type="text" name="responsaveis[__INDEX__][email]" class="resp-email <?= $inputClass ?>" inputmode="email">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                    <div class="pt-2 border-t border-gray-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-800">Endereço</h4>
                            <button type="button" class="btn-copiar-end text-xs text-blue-600 hover:underline font-medium">
                                <i class="fa-solid fa-copy mr-1"></i> Copiar endereço do aluno
                            </button>
                        </div>
                        <div class="max-w-xs">
                            <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                            <div class="flex gap-2">
                                <input type="text" name="responsaveis[__INDEX__][end_cep]" class="resp-end_cep <?= $inputClass ?>" inputmode="numeric" maxlength="9" placeholder="00000-000">
                                <button type="button" class="btn-busca-cep-resp shrink-0 inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400 resp-cep-status"></p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Logradouro</label>
                                <input type="text" name="responsaveis[__INDEX__][endereco]" class="resp-endereco <?= $inputClass ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                <input type="text" name="responsaveis[__INDEX__][end_numero]" class="resp-end_numero <?= $inputClass ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                <input type="text" name="responsaveis[__INDEX__][end_bairro]" class="resp-end_bairro <?= $inputClass ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                <input type="text" name="responsaveis[__INDEX__][end_complemento]" class="resp-end_complemento <?= $inputClass ?>">
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
            </template>

            <div class="flex justify-between pt-2">
                <button type="button" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-prev-step="2">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
                </button>
                <button type="button" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white" data-next-step="4">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Passo 4 -->
        <div class="step-panel hidden space-y-4" data-step-panel="4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Documentos</h2>
            <p class="text-xs text-gray-500">Arraste RG, CPF, comprovante de residência ou histórico (PDF, JPG ou PNG, máx. 10MB cada).</p>

            <div id="docs-dropzone" class="js-dropzone rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center cursor-pointer hover:bg-gray-100 transition-colors"
                 role="button" tabindex="0">
                <input type="file" id="docs-picker" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple class="sr-only">
                <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400 mb-2"></i>
                <p class="text-sm font-semibold text-gray-800">Arraste e solte os arquivos aqui</p>
                <p class="text-xs text-gray-500 mt-1">ou clique para escolher · até 8 arquivos</p>
            </div>
            <div id="docs-list" class="space-y-3"></div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="3" maxlength="2000" placeholder="Série desejada, turno, dúvidas…"
                          class="<?= $inputClass ?> resize-none"><?= $esc($val($old, 'observacoes')) ?></textarea>
            </div>

            <div class="flex justify-between pt-2">
                <button type="button" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-prev-step="3">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
                </button>
                <button type="submit" id="btn-submit-captacao"
                        class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl px-5 py-2.5 text-sm">
                    <i class="fa-solid fa-paper-plane"></i>
                    Enviar interesse
                </button>
            </div>
        </div>

        <p class="text-xs text-center text-gray-400">
            Seus dados serão usados apenas pela escola para o processo de matrícula.
        </p>
    </form>
</div>

<template id="doc-row-template">
    <div class="doc-row flex items-center gap-3 border border-gray-200 rounded-lg px-3 py-2.5 bg-white">
        <span class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 flex items-center justify-center shrink-0 text-gray-500">
            <i class="fa-solid fa-file-lines text-sm"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="js-file-label text-sm font-medium text-gray-800 truncate"></p>
            <select name="tipo_documento[]" class="mt-1 <?= $inputClass ?>">
                <?php foreach ($tiposDocumento as $k => $label): ?>
                <option value="<?= $esc($k) ?>"><?= $esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="file" name="arquivo[]" accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only js-file-input">
        <button type="button" class="btn-remove-doc text-xs font-medium text-red-600 hover:text-red-800 px-2 py-1">Remover</button>
    </div>
</template>

<script>
(function () {
    var wizard = document.getElementById('captacaoWizard');
    var form = document.getElementById('captacaoForm');
    var currentStep = 1;
    var respIndex = 1;
    var extras = <?= json_encode(array_values($respExtras), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function onlyDigits(v) { return String(v || '').replace(/\D+/g, ''); }
    function maskCep(v) {
        var d = onlyDigits(v).slice(0, 8);
        return d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    }

    function setStep(n) {
        currentStep = n;
        form.querySelectorAll('[data-step-panel]').forEach(function (el) {
            el.classList.toggle('hidden', parseInt(el.getAttribute('data-step-panel'), 10) !== n);
        });
        wizard.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            var t = parseInt(btn.getAttribute('data-step-target'), 10);
            var on = t === n;
            btn.classList.toggle('border-blue-600', on);
            btn.classList.toggle('bg-blue-50', on);
            btn.classList.toggle('text-gray-900', on);
            btn.classList.toggle('border-gray-200', !on);
            btn.classList.toggle('bg-white', !on);
            btn.classList.toggle('text-gray-600', !on);
            var num = btn.querySelector('.step-nav-num');
            if (num) {
                num.classList.toggle('bg-blue-600', on);
                num.classList.toggle('border-blue-600', on);
                num.classList.toggle('text-white', on);
                num.classList.toggle('border-current', !on);
            }
        });
        window.scrollTo({ top: wizard.offsetTop - 12, behavior: 'smooth' });
    }

    function validateStep(n) {
        if (n === 1) {
            var ano = document.getElementById('ano_letivo_id');
            if (!ano.value) { alert('Selecione o ano letivo.'); ano.focus(); return false; }
            return true;
        }
        if (n === 2) {
            var nome = document.getElementById('aluno_nome');
            var cpf = document.getElementById('aluno_cpf');
            var nasc = document.getElementById('aluno_data_nasc');
            if (!nome.value.trim()) { alert('Informe o nome do aluno.'); nome.focus(); return false; }
            if (onlyDigits(cpf.value).length !== 11) { alert('Informe o CPF do aluno com 11 dígitos.'); cpf.focus(); return false; }
            if (!nasc.value) { alert('Informe a data de nascimento.'); nasc.focus(); return false; }
            return true;
        }
        if (n === 3) {
            var card = document.querySelector('.responsavel-card');
            var nm = card && card.querySelector('.resp-nome');
            var tel = card && card.querySelector('.resp-telefone');
            var em = card && card.querySelector('.resp-email');
            if (!nm || !nm.value.trim()) { alert('Informe o nome do responsável.'); if (nm) nm.focus(); return false; }
            if ((!tel || !tel.value.trim()) && (!em || !em.value.trim())) {
                alert('Informe telefone ou e-mail do responsável.');
                if (tel) tel.focus();
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

    function buscarCep(cep, onOk, onErr) {
        var digits = onlyDigits(cep);
        if (digits.length !== 8) { if (onErr) onErr('CEP inválido'); return; }
        fetch('https://viacep.com.br/ws/' + digits + '/json/')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.erro) { if (onErr) onErr('CEP não encontrado'); return; }
                if (onOk) onOk(data);
            })
            .catch(function () { if (onErr) onErr('Falha ao consultar CEP'); });
    }

    var cepAluno = document.getElementById('aluno_end_cep');
    var statusAluno = document.getElementById('aluno-cep-status');
    function aplicarCepAluno() {
        if (statusAluno) statusAluno.textContent = 'Buscando...';
        buscarCep(cepAluno.value, function (data) {
            if (data.logradouro) document.getElementById('aluno_endereco').value = data.logradouro;
            if (data.bairro) document.getElementById('aluno_end_bairro').value = data.bairro;
            if (data.localidade) document.getElementById('aluno_end_cidade').value = data.localidade;
            if (data.uf) document.getElementById('aluno_end_uf').value = data.uf;
            if (statusAluno) statusAluno.textContent = 'Endereço preenchido.';
        }, function (msg) { if (statusAluno) statusAluno.textContent = msg; });
    }
    if (cepAluno) {
        cepAluno.addEventListener('input', function () { cepAluno.value = maskCep(cepAluno.value); });
        cepAluno.addEventListener('blur', function () { if (onlyDigits(cepAluno.value).length === 8) aplicarCepAluno(); });
    }
    var btnCepAluno = document.getElementById('btn-busca-cep-aluno');
    if (btnCepAluno) btnCepAluno.addEventListener('click', aplicarCepAluno);

    var list = document.getElementById('responsaveis-list');
    var tpl = document.getElementById('responsavel-card-template');

    function bindCard(card) {
        var cepInput = card.querySelector('.resp-end_cep');
        var btnCep = card.querySelector('.btn-busca-cep-resp');
        var statusEl = card.querySelector('.resp-cep-status');
        function aplicarResp() {
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
            }, function (msg) { if (statusEl) statusEl.textContent = msg; });
        }
        if (cepInput) {
            cepInput.addEventListener('input', function () { cepInput.value = maskCep(cepInput.value); });
            cepInput.addEventListener('blur', function () { if (onlyDigits(cepInput.value).length === 8) aplicarResp(); });
        }
        if (btnCep) btnCep.addEventListener('click', aplicarResp);
        var copy = card.querySelector('.btn-copiar-end');
        if (copy) {
            copy.addEventListener('click', function () {
                card.querySelector('.resp-endereco').value = document.getElementById('aluno_endereco').value;
                card.querySelector('.resp-end_numero').value = document.getElementById('aluno_end_numero').value;
                card.querySelector('.resp-end_complemento').value = document.getElementById('aluno_end_complemento').value;
                card.querySelector('.resp-end_bairro').value = document.getElementById('aluno_end_bairro').value;
                card.querySelector('.resp-end_cidade').value = document.getElementById('aluno_end_cidade').value;
                card.querySelector('.resp-end_uf').value = document.getElementById('aluno_end_uf').value;
                card.querySelector('.resp-end_cep').value = document.getElementById('aluno_end_cep').value;
            });
        }
        var rem = card.querySelector('.btn-remove-resp');
        if (rem) {
            rem.addEventListener('click', function () { card.remove(); });
        }
    }

    function fillCard(card, data) {
        if (!data) return;
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
            el.value = String(data[k]);
        });
    }

    function addResponsavel(data) {
        var html = tpl.innerHTML.replace(/__INDEX__/g, String(respIndex)).replace(/__NUM__/g, String(list.querySelectorAll('.responsavel-card').length + 1));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var card = wrap.firstElementChild;
        list.appendChild(card);
        fillCard(card, data || {});
        bindCard(card);
        respIndex++;
    }

    bindCard(list.querySelector('.responsavel-card'));
    (extras || []).forEach(function (r) { addResponsavel(r); });
    document.getElementById('btn-add-responsavel').addEventListener('click', function () { addResponsavel({}); });

    function syncLegacyResp() {
        var card = document.querySelector('.responsavel-card');
        if (!card) return;
        var get = function (sel) { var el = card.querySelector(sel); return el ? el.value : ''; };
        document.getElementById('resp_nome').value = get('.resp-nome');
        document.getElementById('resp_cpf').value = get('.resp-cpf');
        document.getElementById('resp_email').value = get('.resp-email');
        document.getElementById('resp_telefone').value = get('.resp-telefone');
        document.getElementById('resp_parentesco').value = get('.resp-tipo_vinculo');
        document.getElementById('resp_endereco').value = get('.resp-endereco');
    }

    function setInputFile(input, file) {
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            return true;
        } catch (err) {
            return false;
        }
    }

    var docsList = document.getElementById('docs-list');
    var docTpl = document.getElementById('doc-row-template');
    var docsDrop = document.getElementById('docs-dropzone');
    var docsPicker = document.getElementById('docs-picker');

    function addDocFile(file) {
        if (!file) return;
        if (docsList.querySelectorAll('.doc-row').length >= 8) {
            alert('Máximo de 8 documentos.');
            return;
        }
        var wrap = document.createElement('div');
        wrap.innerHTML = docTpl.innerHTML.trim();
        var row = wrap.firstElementChild;
        var input = row.querySelector('.js-file-input');
        var label = row.querySelector('.js-file-label');
        if (label) label.textContent = file.name;
        if (input) setInputFile(input, file);
        row.querySelector('.btn-remove-doc').addEventListener('click', function () { row.remove(); });
        docsList.appendChild(row);
    }

    if (docsDrop && docsPicker) {
        docsDrop.addEventListener('click', function (e) {
            if (e.target === docsPicker) return;
            docsPicker.click();
        });
        docsDrop.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                docsPicker.click();
            }
        });
        ['dragenter', 'dragover'].forEach(function (evt) {
            docsDrop.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                docsDrop.classList.add('border-blue-500', 'bg-blue-50');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            docsDrop.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                docsDrop.classList.remove('border-blue-500', 'bg-blue-50');
            });
        });
        docsDrop.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : [];
            Array.prototype.forEach.call(files, addDocFile);
        });
        docsPicker.addEventListener('change', function () {
            Array.prototype.forEach.call(this.files || [], addDocFile);
            this.value = '';
        });
    }

    var uf0 = document.querySelector('.responsavel-card .resp-end_uf');
    <?php if (!empty($resp0['end_uf'])): ?>
    if (uf0) uf0.value = <?= json_encode((string) $resp0['end_uf'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    <?php endif; ?>

    form.addEventListener('submit', function (e) {
        for (var s = 1; s <= 3; s++) {
            if (!validateStep(s)) {
                e.preventDefault();
                setStep(s);
                return;
            }
        }
        syncLegacyResp();
        var btn = document.getElementById('btn-submit-captacao');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.classList.add('opacity-70', 'pointer-events-none');
        }
    });
})();
</script>
