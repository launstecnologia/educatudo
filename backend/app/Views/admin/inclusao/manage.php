<?php
$acc = $accommodation ?? null;
$accId = (int) ($acc['id'] ?? 0);
$status = (string) ($acc['status'] ?? 'rascunho');
$rulesMap = $rules_map ?? [];
$catalog = $catalog ?? [];
$initialStep = (int) ($initial_step ?? 1);
if ($initialStep < 1 || $initialStep > 5) {
    $initialStep = 1;
}
$activeAiJobId = (int) ($active_ai_job_id ?? 0);

$ruleVal = static function (string $key, $default = '') use ($rulesMap) {
    return $rulesMap[$key] ?? $default;
};
$isOn = static function (string $key) use ($rulesMap): bool {
    $v = strtolower((string) ($rulesMap[$key] ?? ''));
    return in_array($v, ['1', 'true', 'on', 'sim', 'yes'], true);
};

// Agrupa o catálogo por "group". Configurações visuais são preferência do próprio aluno no portal.
$grupos = [];
$studentSelfVisualKeys = ['font_size', 'font_family', 'high_contrast', 'enable_tts'];
foreach ($catalog as $key => $def) {
    if (strpos((string) $key, 'visual_') === 0 || in_array((string) $key, $studentSelfVisualKeys, true)) {
        continue;
    }
    $grupos[$def['group'] ?? 'Geral'][$key] = $def;
}
$gruposAcessoNomes = ['Tempo', 'Apresentação', 'Acesso', 'Correção'];

$extraTimeVal = (float) $ruleVal('extra_time_pct', 0);
$hideTimerOn = $isOn('hide_timer');
$showPauseTimer = $extraTimeVal > 0 && !$hideTimerOn;

$isSig = !empty($is_significativa);
$reqAprov = (int) ($reinforced_required ?? 2);
$countAprov = (int) ($reinforced_count ?? 0);
$podeAtivar = !$isSig || $countAprov >= $reqAprov;

$steps = [
    ['n' => 1, 'label' => 'Dados', 'sub' => 'Máscara'],
    ['n' => 2, 'label' => 'Documento', 'sub' => 'Análise IA'],
    ['n' => 3, 'label' => 'Acessibilidade', 'sub' => 'Recursos'],
    ['n' => 4, 'label' => 'Conteúdo', 'sub' => 'Adaptação'],
    ['n' => 5, 'label' => 'Revisão', 'sub' => 'Ativação'],
];
?>
<div class="space-y-6 w-full" id="accWizard" data-acc-id="<?= $accId ?>">
    <div class="mb-2">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <a href="<?= URL ?>/admin/inclusao"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors flex-shrink-0"
                   aria-label="Voltar">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars((string) $aluno['nome']) ?></h2>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($aluno['turma_nome'] ?? '')) ?></p>
                </div>
            </div>
        <?php
        $badgeMap = [
            'rascunho'  => ['Rascunho', 'bg-slate-100 text-slate-700'],
            'ativa'     => ['Ativa', 'bg-green-100 text-green-800'],
            'suspensa'  => ['Suspensa', 'bg-amber-100 text-amber-800'],
            'encerrada' => ['Encerrada', 'bg-red-100 text-red-800'],
        ];
        [$bLabel, $bCls] = $badgeMap[$status] ?? ['Sem máscara', 'bg-slate-100 text-slate-700'];
        ?>
            <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold <?= $bCls ?>"><?= $accId ? $bLabel : 'Sem máscara' ?></span>
        </div>
    </div>

    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars((string) $flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- ===== Navegação do wizard ===== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-center gap-2 sm:gap-0" id="wizardStepsNav" role="tablist">
        <?php foreach ($steps as $i => $s): ?>
            <button type="button" data-step-target="<?= (int) $s['n'] ?>"
                    class="step-nav-btn group flex items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition sm:flex-1 sm:max-w-[200px]
                           border-gray-200 bg-white text-gray-600 hover:border-green-200 hover:bg-green-50/50
                           data-[active=true]:border-green-600 data-[active=true]:bg-green-600 data-[active=true]:text-white data-[active=true]:shadow-md">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold border-2 border-current"><?= (int) $s['n'] ?></span>
                <span class="min-w-0">
                    <span class="block font-semibold leading-tight"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="block text-xs opacity-80"><?= htmlspecialchars($s['sub']) ?></span>
                </span>
            </button>
            <?php if ($i < count($steps) - 1): ?>
                <div class="hidden sm:block w-8 h-0.5 bg-gray-200 shrink-0" aria-hidden="true"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- ===== Form principal: dados da máscara + regras (Etapas 1, 3, 4 e resumo da 5) ===== -->
    <form method="post" action="<?= URL ?>/admin/inclusao/salvar" id="accForm" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
        <input type="hidden" name="aluno_id" value="<?= (int) $aluno['id'] ?>">
        <input type="hidden" name="mascara_id" value="<?= $accId ?>">
        <input type="hidden" name="_next_step" id="nextStepField" value="">
        <input type="hidden" name="and_activate" id="andActivateField" value="">
        <input type="hidden" name="tipo_adaptacao" value="significativa">

        <!-- Passo 1 — Dados da máscara -->
        <div class="step-panel p-6 space-y-6" data-step-panel="1">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Dados da máscara</h3>
                <p class="mt-1 text-sm text-gray-500">Adaptações <strong>significativas</strong> podem alterar a versão da prova e exigem revisão/aprovação da coordenação/AEE.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de adaptação</label>
                    <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-800 font-medium">
                        Significativa
                    </div>
                </div>
                <div></div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Início da vigência</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars((string) ($acc['data_inicio'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fim da vigência (opcional)</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars((string) ($acc['data_fim'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Base legal <span class="text-red-500">*</span></label>
                    <input type="text" name="base_legal" maxlength="120" placeholder="LBI / Laudo / PEI" value="<?= htmlspecialchars((string) ($acc['base_legal'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Referência de consentimento <span class="text-red-500">*</span></label>
                    <input type="text" name="ref_consentimento" maxlength="160" placeholder="Protocolo / termo assinado" value="<?= htmlspecialchars((string) ($acc['ref_consentimento'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observações (não inserir CID/diagnóstico)</label>
                    <textarea name="observacoes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"><?= htmlspecialchars((string) ($acc['observacoes'] ?? '')) ?></textarea>
                </div>
                <p class="md:col-span-2 text-xs text-gray-500">Base legal e consentimento são exigidos só na hora de <strong>ativar</strong> a máscara — você pode salvar um rascunho sem preenchê-los agora.</p>
            </div>
            <div class="flex justify-end pt-2">
                <button type="button" id="btnStep1Next" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Passo 3 — Recursos de acessibilidade -->
        <div class="step-panel hidden p-6 space-y-6" data-step-panel="3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Recursos de acessibilidade</h3>
                <p class="mt-1 text-sm text-gray-500">Defina como a prova será apresentada para este aluno.</p>
            </div>
            <?php foreach ($grupos as $grupoNome => $regras): ?>
                <?php if (!in_array($grupoNome, $gruposAcessoNomes, true)) continue; ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3"><?= htmlspecialchars((string) $grupoNome) ?></p>
                    <div class="grid grid-cols-1 md:grid-cols-2 <?= $grupoNome === 'Tempo' ? 'lg:grid-cols-3' : '' ?> gap-4">
                        <?php foreach ($regras as $key => $def): ?>
                            <?php
                                $input = $def['input'] ?? 'toggle';
                                $cardId = '';
                                $cardExtraClass = '';
                                if ($key === 'allow_pause_timer') {
                                    $cardId = ' id="cardAllowPauseTimer"';
                                    $cardExtraClass = $showPauseTimer ? '' : ' hidden';
                                }
                            ?>
                            <div class="border border-gray-200 bg-gray-50/50 rounded-lg p-4<?= $cardExtraClass ?>"<?= $cardId ?>>
                                <?php if ($input === 'toggle'): ?>
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="checkbox" name="rules[<?= $key ?>]" value="1" <?= $isOn($key) ? 'checked' : '' ?> class="mt-1 h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                                        <span>
                                            <span class="block text-sm font-medium text-gray-800"><?= htmlspecialchars((string) $def['label']) ?></span>
                                            <span class="block text-xs text-gray-500"><?= htmlspecialchars((string) ($def['help'] ?? '')) ?></span>
                                        </span>
                                    </label>
                                <?php elseif ($input === 'number'): ?>
                                    <label class="block text-sm font-medium text-gray-800 mb-2"><?= htmlspecialchars((string) $def['label']) ?></label>
                                    <input type="number" min="0" max="300" name="rules[<?= $key ?>]" value="<?= htmlspecialchars((string) $ruleVal($key)) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                                    <span class="block text-xs text-gray-500 mt-1"><?= htmlspecialchars((string) ($def['help'] ?? '')) ?></span>
                                <?php elseif ($input === 'text'): ?>
                                    <label class="block text-sm font-medium text-gray-800 mb-2"><?= htmlspecialchars((string) $def['label']) ?></label>
                                    <input type="text" maxlength="255" name="rules[<?= $key ?>]" value="<?= htmlspecialchars((string) $ruleVal($key)) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                                    <span class="block text-xs text-gray-500 mt-1"><?= htmlspecialchars((string) ($def['help'] ?? '')) ?></span>
                                <?php elseif ($input === 'select'): ?>
                                    <label class="block text-sm font-medium text-gray-800 mb-2"><?= htmlspecialchars((string) $def['label']) ?></label>
                                    <select name="rules[<?= $key ?>]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                                        <?php foreach (($def['options'] ?? []) as $optVal => $optLabel): ?>
                                            <option value="<?= htmlspecialchars((string) $optVal) ?>" <?= (string) $ruleVal($key) === (string) $optVal ? 'selected' : '' ?>><?= htmlspecialchars((string) $optLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="block text-xs text-gray-500 mt-1"><?= htmlspecialchars((string) ($def['help'] ?? '')) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($grupoNome === 'Acesso'): ?>
                            <div id="audioAnswerFuturePanel" class="md:col-span-2 <?= $isOn('allow_audio_answer') ? '' : 'hidden' ?> rounded-lg border border-dashed border-purple-300 bg-purple-50/60 p-4">
                                <p class="text-xs font-semibold text-purple-800 uppercase tracking-wide mb-3">Resposta por áudio — configurações (em breve)</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Tempo máximo de gravação (s)</label>
                                        <input type="number" disabled placeholder="Em breve" class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Idioma</label>
                                        <select disabled class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed text-sm">
                                            <option>Português</option>
                                        </select>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <input type="checkbox" disabled checked class="mt-1 h-4 w-4 text-gray-300 border-gray-300 rounded cursor-not-allowed">
                                        <span>
                                            <span class="block text-sm text-gray-500">Transcrição automática</span>
                                            <span class="block text-xs text-gray-400">Já ativada automaticamente hoje quando a resposta por áudio está habilitada.</span>
                                        </span>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <input type="checkbox" disabled class="mt-1 h-4 w-4 text-gray-300 border-gray-300 rounded cursor-not-allowed">
                                        <span class="block text-sm text-gray-500">Permitir regravar</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="flex items-center justify-between pt-2">
                <button type="button" class="step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="2">
                    <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
                </button>
                <button type="button" id="btnStep3Next" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Passo 4 — Adaptação de conteúdo -->
        <div class="step-panel hidden p-6 space-y-6" data-step-panel="4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Adaptação de conteúdo</h3>
                <p class="mt-1 text-sm text-gray-500">Adaptações <strong>significativas</strong> alteram o conteúdo da prova, geram uma versão clonada e exigem aprovação reforçada.</p>
            </div>
            <?php foreach ($grupos as $grupoNome => $regras): ?>
                <?php if (in_array($grupoNome, $gruposAcessoNomes, true)) continue; ?>
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3"><?= htmlspecialchars((string) $grupoNome) ?></p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($regras as $key => $def): ?>
                            <?php $input = $def['input'] ?? 'toggle'; ?>
                            <div class="border border-gray-200 bg-gray-50/50 rounded-lg p-4">
                                <?php if ($input === 'toggle'): ?>
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="checkbox" name="rules[<?= $key ?>]" value="1" <?= $isOn($key) ? 'checked' : '' ?> class="mt-1 h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                                        <span>
                                            <span class="block text-sm font-medium text-gray-800"><?= htmlspecialchars((string) $def['label']) ?></span>
                                            <span class="block text-xs text-gray-500"><?= htmlspecialchars((string) ($def['help'] ?? '')) ?></span>
                                        </span>
                                    </label>
                                <?php elseif ($input === 'number'): ?>
                                    <label class="block text-sm font-medium text-gray-800 mb-2"><?= htmlspecialchars((string) $def['label']) ?></label>
                                    <input type="number" min="0" max="300" name="rules[<?= $key ?>]" value="<?= htmlspecialchars((string) $ruleVal($key)) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                                    <span class="block text-xs text-gray-500 mt-1"><?= htmlspecialchars((string) ($def['help'] ?? '')) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div id="glossaryFuturePanel" class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 <?= $isOn('glossary_enabled') ? '' : 'hidden' ?>">
                <h4 class="text-sm font-semibold text-indigo-900 mb-1"><i class="fa-solid fa-book mr-1"></i> Glossário — geração automática por IA (em breve)</h4>
                <p class="text-xs text-indigo-800">Fluxo futuro: a IA identifica palavras difíceis na prova → gera definições compatíveis com a série do aluno → o aluno clica na palavra para ver o significado. Hoje o recurso "Glossário (clique na palavra)", habilitado na Etapa 3, já libera essa interação para o aluno — a geração automática das definições ainda não está implementada.</p>
            </div>

            <div class="flex items-center justify-between pt-2">
                <button type="button" class="step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="3">
                    <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
                </button>
                <button type="button" id="btnStep4Next" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>

        <!-- Passo 5 (parte 1) — Resumo, dentro do form principal -->
        <div class="step-panel hidden p-6 space-y-6" data-step-panel="5">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Revisão</h3>
                <p class="mt-1 text-sm text-gray-500">Confira os dados antes de salvar. A máscara só passa a valer nas provas após ser <strong>ativada</strong>.</p>
            </div>
            <div id="resumoContent" class="rounded-lg border border-gray-200 bg-gray-50/50 p-5"></div>
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                <button type="button" class="step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="4">
                    <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
                </button>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Salvar
                    </button>
                    <button type="submit" id="btnSaveActivate" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                        <i class="fa-solid fa-check mr-2"></i>
                        Salvar e ativar
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Passo 2 — Documento e Análise IA (fora do form principal: forms próprios de upload/análise) -->
    <div class="step-panel hidden" data-step-panel="2">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Documento e Análise IA</h3>
                <p class="mt-1 text-sm text-gray-500">Envie o laudo/PEI e use a análise por IA para sugerir uma máscara inicial. A decisão final é sempre da coordenação/AEE.</p>
            </div>

            <?php if (!$accId): ?>
                <div class="rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                    <span>Salve os dados da máscara na Etapa 1 antes de enviar o laudo.</span>
                    <button type="button" class="step-back px-3 py-1.5 rounded-lg border border-blue-300 text-blue-700 hover:bg-blue-100 text-xs font-medium" data-go-step="1">Ir para a Etapa 1</button>
                </div>
            <?php else: ?>
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 mb-1">Laudo (documento sensível)</h4>
                    <p class="text-xs text-gray-500 mb-4">Arquivos são criptografados em repouso (AES-256-GCM). Acesso restrito e auditado.</p>
                    <?php if (empty($doc_encryption_on)): ?>
                        <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
                            Upload de laudo desabilitado. Configure <code class="bg-amber-100 px-1 rounded">INCLUSAO_DOC_ENCRYPTION_KEY</code> no <code>.env</code> do servidor.
                        </div>
                    <?php else: ?>
                        <form method="post" action="<?= URL ?>/admin/inclusao/laudo/upload" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                            <input type="hidden" name="mascara_id" value="<?= $accId ?>">
                            <input type="file" name="laudo" accept=".pdf,.png,.jpg,.jpeg" required class="text-sm">
                            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 text-sm font-medium">Enviar laudo</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!empty($documentos)): ?>
                        <ul class="mt-4 divide-y divide-gray-100">
                            <?php foreach ($documentos as $doc): ?>
                                <li class="flex items-center justify-between py-2 text-sm">
                                    <span class="text-gray-700"><?= htmlspecialchars((string) ($doc['nome_original'] ?? ('Documento #' . (int) $doc['id']))) ?>
                                        <span class="text-xs text-gray-400 ml-2"><?= !empty($doc['created_at']) ? date('d/m/Y H:i', strtotime((string) $doc['created_at'])) : '' ?></span>
                                    </span>
                                    <a href="<?= URL ?>/admin/inclusao/laudo/<?= (int) $doc['id'] ?>" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800 font-medium">Abrir</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <div id="analiseIaLoading" class="<?= $activeAiJobId > 0 ? '' : 'hidden' ?> flex items-center gap-3 text-sm text-purple-700 mb-3">
                                <i class="fa-solid fa-spinner fa-spin text-lg"></i>
                                <span>Analisando laudo com IA... isso pode levar até 1 minuto.</span>
                            </div>
                            <div id="analiseIaErro" class="hidden rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 mb-3"></div>
                            <div id="analiseIaForm" class="<?= $activeAiJobId > 0 ? 'hidden' : '' ?> flex flex-wrap items-center gap-3">
                                <form method="post" action="<?= URL ?>/admin/inclusao/analisar-laudo">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                                    <input type="hidden" name="mascara_id" value="<?= $accId ?>">
                                    <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium">
                                        <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Analisar laudo com IA
                                    </button>
                                </form>
                                <span class="text-xs text-gray-500">OCR do laudo + sugestão de máscara (PDF com texto, ou imagens PNG/JPG; PDF escaneado depende de OCR no servidor). A sugestão é revisada por você.</span>
                            </div>
                        </div>

                        <?php if ($activeAiJobId > 0): ?>
                            <?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>
                            <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                new AIJobPoller(<?= $activeAiJobId ?>, {
                                    onDone: function () {
                                        window.location.href = '<?= URL ?>/admin/inclusao/aluno/<?= (int) ($aluno['id'] ?? 0) ?>?step=2';
                                    },
                                    onFailed: function (msg) {
                                        var loading = document.getElementById('analiseIaLoading');
                                        var erro = document.getElementById('analiseIaErro');
                                        var form = document.getElementById('analiseIaForm');
                                        if (loading) loading.classList.add('hidden');
                                        if (erro) {
                                            erro.textContent = 'Não foi possível concluir a análise: ' + msg;
                                            erro.classList.remove('hidden');
                                        }
                                        if (form) form.classList.remove('hidden');
                                    }
                                });
                            });
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($ai_suggestion) && !empty($ai_suggestion['suggested_rules'])): ?>
                    <div id="ai-suggestion" class="bg-purple-50 border border-purple-200 rounded-xl p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold text-purple-900">Sugestão da IA (a partir do laudo)</h4>
                                <p class="text-xs text-purple-700">
                                    Confiança geral: <strong><?= htmlspecialchars((string) ($ai_suggestion['confidence'] ?? '—')) ?></strong>
                                    <?php if (!empty($ai_suggestion['_created_at'])): ?>
                                        · gerada em <?= date('d/m/Y H:i', strtotime((string) $ai_suggestion['_created_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <button type="button" onclick="aplicarSugestaoIA()" class="shrink-0 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium">Aplicar no formulário</button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                            <?php foreach ($ai_suggestion['suggested_rules'] as $k => $v): ?>
                                <div class="border border-purple-200 bg-white rounded-lg p-3">
                                    <p class="text-sm font-semibold text-purple-900"><?= htmlspecialchars((string) ($catalog[$k]['label'] ?? $k)) ?></p>
                                    <p class="text-xs text-purple-700 mt-1">Valor sugerido: <strong><?= htmlspecialchars((string) $v) ?></strong></p>
                                    <?php if (!empty($ai_suggestion['justification'])): ?>
                                        <p class="text-xs text-gray-500 mt-2">Justificativa: <?= htmlspecialchars((string) $ai_suggestion['justification']) ?> <span class="text-gray-400">(agregada do laudo completo)</span></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-1" data-future-field="trecho-laudo-por-regra">Trecho do laudo: <em>disponível em versão futura</em></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="mt-3 text-[11px] text-purple-600">⚠️ Sugestão automática — a decisão, ajuste e ativação são da coordenação/AEE (humano no loop).</p>
                    </div>
                    <script>
                    function aplicarSugestaoIA() {
                        var s = <?= json_encode($ai_suggestion['suggested_rules'], JSON_UNESCAPED_UNICODE) ?>;
                        var truthy = ['1', 'true', 'on', 'sim', 'yes'];
                        Object.keys(s).forEach(function (k) {
                            var el = document.querySelector('[name="rules[' + k + ']"]');
                            if (!el) return;
                            var val = String(s[k]).toLowerCase();
                            if (el.type === 'checkbox') {
                                el.checked = truthy.indexOf(val) > -1;
                                el.dispatchEvent(new Event('change'));
                            } else {
                                el.value = s[k];
                                el.dispatchEvent(new Event('input'));
                                el.dispatchEvent(new Event('change'));
                            }
                        });
                        var tipo = document.querySelector('[name="tipo_adaptacao"]');
                        if (tipo) tipo.value = 'significativa';
                        if (typeof showNotification === 'function') {
                            showNotification('Sugestão aplicada. Revise os campos nas Etapas 3 e 4.', 'success');
                        } else {
                            alert('Sugestão aplicada ao formulário. Revise os campos e clique em "Salvar".');
                        }
                        setStep(3);
                    }
                    </script>
                <?php endif; ?>
            <?php endif; ?>

            <div class="flex items-center justify-between pt-2">
                <button type="button" class="step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="1">
                    <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
                </button>
                <button type="button" id="btnStep2Next" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Próximo <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Passo 5 (parte 2) — Situação e Auditoria, fora do form principal -->
    <div class="step-panel hidden space-y-6" data-step-panel="5">
        <?php if ($accId): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Situação</h2>

                <?php if ($isSig): ?>
                    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
                        <i class="fa-solid fa-circle-info mr-1"></i> Adaptações <strong>significativas</strong> (reduzir questões, reescrita por IA) geram uma <strong>versão clonada</strong> que precisa ser aprovada em <em>Versões adaptadas</em> antes de ser entregue. Em <strong>provas avulsas</strong>, a versão é gerada quando a coordenação aprova a prova; em <strong>eventos/blocos</strong>, na <strong>aprovação final do bloco</strong>. Dentro do bloco, a versão adaptada é feita com <strong>tempo próprio</strong> (fora do cronômetro compartilhado).
                    </div>
                    <div class="mb-4 rounded-lg border <?= $countAprov >= $reqAprov ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50' ?> p-4">
                        <p class="text-sm font-medium <?= $countAprov >= $reqAprov ? 'text-green-800' : 'text-amber-800' ?>">
                            Adaptação significativa — exige <?= $reqAprov > 1 ? 'dupla aprovação' : 'aprovação' ?>: <strong><?= $countAprov ?>/<?= $reqAprov ?></strong>
                        </p>
                        <?php if (!empty($reinforced_approvers)): ?>
                            <ul class="mt-2 text-xs text-gray-600 space-y-0.5">
                                <?php foreach ($reinforced_approvers as $ap): ?>
                                    <li>✔ <?= htmlspecialchars((string) ($ap['nome'] ?? ('Usuário #' . (int) $ap['user_id']))) ?>
                                        <span class="text-gray-400"><?= !empty($ap['aprovado_em']) ? date('d/m/Y H:i', strtotime((string) $ap['aprovado_em'])) : '' ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (empty($user_already_approved) && $countAprov < $reqAprov): ?>
                            <form method="post" action="<?= URL ?>/admin/inclusao/aprovar-reforcada" class="mt-3">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                                <input type="hidden" name="mascara_id" value="<?= $accId ?>">
                                <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium">Registrar minha aprovação</button>
                            </form>
                        <?php elseif (!empty($user_already_approved)): ?>
                            <p class="mt-2 text-xs text-gray-500">Você já registrou sua aprovação.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="flex flex-wrap gap-3">
                    <?php if ($status !== 'ativa'): ?>
                        <?php if ($podeAtivar): ?>
                            <form method="post" action="<?= URL ?>/admin/inclusao/ativar" onsubmit="return confirm('Ativar a máscara? As regras passarão a valer nas provas do aluno.');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                                <input type="hidden" name="mascara_id" value="<?= $accId ?>">
                                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 text-sm font-medium">Ativar máscara</button>
                            </form>
                        <?php else: ?>
                            <button type="button" disabled class="px-4 py-2 bg-gray-200 text-gray-400 rounded-lg text-sm font-medium cursor-not-allowed" title="<?= $reqAprov > 1 ? 'Requer dupla aprovação' : 'Requer aprovação reforçada' ?>">Ativar máscara</button>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($status === 'ativa'): ?>
                        <form method="post" action="<?= URL ?>/admin/inclusao/status">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                            <input type="hidden" name="mascara_id" value="<?= $accId ?>">
                            <input type="hidden" name="status" value="suspensa">
                            <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium">Suspender</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($status !== 'encerrada'): ?>
                        <form method="post" action="<?= URL ?>/admin/inclusao/status" onsubmit="return confirm('Encerrar a máscara?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                            <input type="hidden" name="mascara_id" value="<?= $accId ?>">
                            <input type="hidden" name="status" value="encerrada">
                            <button type="submit" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-medium">Encerrar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($logs)): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Auditoria recente</h2>
                    <ul class="space-y-2 text-sm">
                        <?php foreach ($logs as $log): ?>
                            <li class="flex items-center justify-between text-gray-600">
                                <span class="font-medium text-gray-800"><?= htmlspecialchars((string) $log['action']) ?></span>
                                <span class="text-xs text-gray-400"><?= !empty($log['created_at']) ? date('d/m/Y H:i', strtotime((string) $log['created_at'])) : '' ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-3">
                Salve a máscara para habilitar a ativação, o envio de laudo e a auditoria.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var wizard = document.getElementById('accWizard');
    var flow = {
        currentStep: <?= (int) $initialStep ?>,
        accId: <?= $accId ?>,
    };

    window.setStep = function (n) {
        flow.currentStep = n;
        document.querySelectorAll('[data-step-panel]').forEach(function (el) {
            var sn = parseInt(el.getAttribute('data-step-panel'), 10);
            el.classList.toggle('hidden', sn !== n);
        });
        document.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            var t = parseInt(btn.getAttribute('data-step-target'), 10);
            btn.setAttribute('data-active', t === n ? 'true' : 'false');
        });
        if (n === 5) buildResumo();
        window.scrollTo({ top: wizard.offsetTop - 16, behavior: 'smooth' });
    };

    document.querySelectorAll('.step-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setStep(parseInt(btn.getAttribute('data-step-target'), 10));
        });
    });
    document.querySelectorAll('.step-back').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setStep(parseInt(btn.getAttribute('data-go-step'), 10));
        });
    });

    document.getElementById('btnStep1Next').addEventListener('click', function () {
        if (flow.accId === 0) {
            document.getElementById('nextStepField').value = '2';
            document.getElementById('accForm').requestSubmit();
            return;
        }
        setStep(2);
    });
    document.getElementById('btnStep2Next').addEventListener('click', function () { setStep(3); });
    document.getElementById('btnStep3Next').addEventListener('click', function () { setStep(4); });
    document.getElementById('btnStep4Next').addEventListener('click', function () { setStep(5); });

    document.getElementById('btnSaveActivate').addEventListener('click', function () {
        document.getElementById('andActivateField').value = '1';
        document.getElementById('nextStepField').value = '5';
    });

    // ---- Dependências entre campos ----
    function updatePauseTimerVisibility() {
        var extra = document.querySelector('[name="rules[extra_time_pct]"]');
        var hide = document.querySelector('[name="rules[hide_timer]"]');
        var card = document.getElementById('cardAllowPauseTimer');
        if (!extra || !hide || !card) return;
        var hasExtra = parseFloat((extra.value || '0').replace(',', '.')) > 0;
        card.classList.toggle('hidden', !(hasExtra && !hide.checked));
    }
    var extraTimeEl = document.querySelector('[name="rules[extra_time_pct]"]');
    var hideTimerEl = document.querySelector('[name="rules[hide_timer]"]');
    if (extraTimeEl) extraTimeEl.addEventListener('input', updatePauseTimerVisibility);
    if (hideTimerEl) hideTimerEl.addEventListener('change', updatePauseTimerVisibility);

    function updateAudioAnswerPanel() {
        var toggle = document.querySelector('[name="rules[allow_audio_answer]"]');
        var panel = document.getElementById('audioAnswerFuturePanel');
        if (!toggle || !panel) return;
        panel.classList.toggle('hidden', !toggle.checked);
    }
    var audioToggle = document.querySelector('[name="rules[allow_audio_answer]"]');
    if (audioToggle) audioToggle.addEventListener('change', updateAudioAnswerPanel);

    function updateGlossaryPanel() {
        var toggle = document.querySelector('[name="rules[glossary_enabled]"]');
        var panel = document.getElementById('glossaryFuturePanel');
        if (!toggle || !panel) return;
        panel.classList.toggle('hidden', !toggle.checked);
    }
    var glossaryToggle = document.querySelector('[name="rules[glossary_enabled]"]');
    if (glossaryToggle) glossaryToggle.addEventListener('change', updateGlossaryPanel);

    // ---- Resumo da Etapa 5 (client-side, sem chamada ao servidor) ----
    var CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?>;

    function addRow(dl, label, value) {
        var dt = document.createElement('dt');
        dt.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide';
        dt.textContent = label;
        var dd = document.createElement('dd');
        dd.className = 'text-sm text-gray-800 mb-3';
        dd.textContent = value;
        dl.appendChild(dt);
        dl.appendChild(dd);
    }

    function formatDateTimeBR(value, fallbackTime) {
        if (!value) return '—';
        var parts = String(value).split('-');
        if (parts.length !== 3) return value;
        return parts[2] + '/' + parts[1] + '/' + parts[0] + ' ' + fallbackTime;
    }

    window.buildResumo = function () {
        var out = document.getElementById('resumoContent');
        if (!out) return;
        out.innerHTML = '';

        var tipo = 'Significativa';
        var start = formatDateTimeBR(document.querySelector('[name="data_inicio"]').value, '00:00');
        var end = formatDateTimeBR(document.querySelector('[name="data_fim"]').value, '23:59');
        var legal = (document.querySelector('[name="base_legal"]').value || '(não informado)');
        var consent = (document.querySelector('[name="ref_consentimento"]').value || '(não informado)');
        var observacoes = (document.querySelector('[name="observacoes"]').value || '—');

        var dl = document.createElement('dl');
        dl.className = 'grid grid-cols-1 md:grid-cols-2 gap-x-6';
        addRow(dl, 'Tipo de adaptação', tipo);
        addRow(dl, 'Início da vigência', start);
        addRow(dl, 'Fim da vigência', end);
        addRow(dl, 'Base legal', legal);
        addRow(dl, 'Referência de consentimento', consent);
        out.appendChild(dl);

        var notesP = document.createElement('p');
        notesP.className = 'text-sm text-gray-600 border-t border-gray-200 pt-3 mt-1';
        notesP.textContent = 'Observações: ' + observacoes;
        out.appendChild(notesP);

        var regrasAtivas = [];
        document.querySelectorAll('[name^="rules["]').forEach(function (el) {
            var m = el.name.match(/rules\[(.+)\]/);
            if (!m) return;
            var def = CATALOG[m[1]];
            if (!def) return;
            if (el.type === 'checkbox') {
                if (el.checked) regrasAtivas.push(def.label);
                return;
            }
            var v = String(el.value || '').trim();
            if (v === '' || v === '0') return;
            if (el.tagName === 'SELECT') {
                var opt = el.selectedOptions[0];
                regrasAtivas.push(def.label + ': ' + (opt ? opt.textContent : v));
            } else {
                regrasAtivas.push(def.label + ': ' + v);
            }
        });

        var title = document.createElement('p');
        title.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide mt-4 mb-2';
        title.textContent = 'Regras de acessibilidade selecionadas';
        out.appendChild(title);

        var ul = document.createElement('ul');
        ul.className = 'list-disc pl-5 text-sm text-gray-700 space-y-1';
        if (regrasAtivas.length === 0) {
            var li = document.createElement('li');
            li.className = 'text-gray-400 list-none';
            li.textContent = 'Nenhuma regra de acessibilidade selecionada.';
            ul.appendChild(li);
        } else {
            regrasAtivas.forEach(function (r) {
                var li = document.createElement('li');
                li.textContent = r;
                ul.appendChild(li);
            });
        }
        out.appendChild(ul);
    };

    document.addEventListener('DOMContentLoaded', function () {
        updatePauseTimerVisibility();
        updateAudioAnswerPanel();
        updateGlossaryPanel();
        setStep(flow.currentStep);
    });
})();
</script>
