<?php require __DIR__ . '/_styles.php'; ?>

<header class="mb-8 pb-6 border-b border-gray-200">
    <a href="<?= URL ?>/admin/dev" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Configurações Avançadas</a>
    <div class="flex items-center gap-3 mt-2">
        <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-green-100 text-green-600 text-2xl">🧩</span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Módulos e Limites</h1>
            <p class="text-sm text-gray-500 mt-0.5">Habilitar/desabilitar funcionalidades e configurar limites de uso</p>
        </div>
    </div>
</header>

<?php
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? 'info';
if ($flash_message !== ''):
    $bg = $flash_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800');
?>
<div class="mb-6 p-4 rounded-lg border <?= $bg ?>">
    <?= htmlspecialchars($flash_message) ?>
</div>
<?php endif; ?>

<div class="space-y-6 max-w-5xl">

    <div class="dev-card">
        <div class="dev-card-header">Módulos do Sistema</div>
        <p class="text-gray-500 text-sm mt-1 px-4 pt-2 pb-0">Geral (aluno, professor e admin) · Professor · Aluno. Configure por instância.</p>
        <div class="dev-card-body">
            <div class="mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Legenda dos estados</p>
                <ul class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <li class="flex items-center gap-2"><span class="inline-flex w-3 h-3 rounded-full bg-green-500"></span> <strong>Habilitado</strong> — acesso total</li>
                    <li class="flex items-center gap-2"><span class="inline-flex w-3 h-3 rounded-full bg-amber-500"></span> <strong>Desabilitado</strong> — oculto no menu do aluno; rota bloqueada</li>
                    <li class="flex items-center gap-2"><span class="inline-flex w-3 h-3 rounded-full bg-slate-400"></span> <strong>Inativo</strong> — oculto e inacessível (escola não contratou)</li>
                </ul>
            </div>
            <form id="modules-form" method="post" action="<?= URL ?>/admin/dev/modules" class="space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php
                    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
                    $geralKeys = [
                        'geral_planos_aula'   => LayoutHelper::getModuleStatus('aluno_planos_aula'),
                        'geral_arquivos'      => LayoutHelper::getModuleStatus('aluno_arquivos'),
                        'geral_jornada'       => LayoutHelper::getModuleStatus('jornadas'),
                        'geral_provas'        => LayoutHelper::getModuleStatus('aluno_provas'),
                        'geral_chat_professor'=> LayoutHelper::getModuleStatus('chat_professor'),
                        'geral_redacao_orientada' => LayoutHelper::getModuleStatus('redacao_configuravel'),
                        'geral_minicursos'    => LayoutHelper::getModuleStatus('aluno_minicursos'),
                        'geral_aulas_online'  => LayoutHelper::getModuleStatus('aulas_online'),
                    ];
                    $geralLabels = [
                        'geral_planos_aula'   => 'Plano de Aula',
                        'geral_arquivos'     => 'Arquivos',
                        'geral_jornada'      => 'Jornada do Aluno',
                        'geral_provas'       => 'Prova Online',
                        'geral_chat_professor'=> 'Chat com Professor',
                        'geral_redacao_orientada' => 'Redação Orientada (e o que o professor faz para o aluno)',
                        'geral_minicursos'   => 'Mini Cursos',
                        'geral_aulas_online' => 'Aulas Online',
                    ];
                    $profKeys = [
                        'professor_ai_agents'   => LayoutHelper::getModuleStatus('professor_ai_agents'),
                        'professor_gerar_slides'=> LayoutHelper::getModuleStatus('professor_gerar_slides'),
                    ];
                    $profLabels = [
                        'professor_ai_agents'   => 'Agentes de IA (Tudinha Prof)',
                        'professor_gerar_slides'=> 'Gerar Slider',
                    ];
                    $alunoKeys = [
                        'educa_livros'      => LayoutHelper::getModuleStatus('educa_livros'),
                        'educalabs'         => LayoutHelper::getModuleStatus('educalabs'),
                        'aluno_flashcards'  => LayoutHelper::getModuleStatus('aluno_flashcards'),
                        'exercicios'        => LayoutHelper::getModuleStatus('exercicios'),
                        'exercicios_ia'     => LayoutHelper::getModuleStatus('exercicios_ia'),
                        'ingles'            => LayoutHelper::getModuleStatus('ingles'),
                        'redacoes'          => LayoutHelper::getModuleStatus('redacoes'),
                        'simulados'         => LayoutHelper::getModuleStatus('simulados'),
                        'chat'              => LayoutHelper::getModuleStatus('chat'),
                        'aluno_caderno_novo'=> LayoutHelper::getModuleStatus('aluno_caderno_novo'),
                        'forum'             => LayoutHelper::getModuleStatus('forum'),
                        'drive'             => LayoutHelper::getModuleStatus('drive'),
                        'jogos'             => LayoutHelper::getModuleStatus('jogos'),
                        'educa_hits'        => LayoutHelper::getModuleStatus('educa_hits'),
                    ];
                    $alunoLabels = [
                        'educa_livros'      => 'Educa Livros',
                        'educalabs'         => 'Educa Labs',
                        'aluno_flashcards'  => 'Flash Card',
                        'exercicios'        => 'Exercícios Banco de Dados',
                        'exercicios_ia'     => 'Exercícios Gerado por IA',
                        'ingles'            => 'Inglês',
                        'redacoes'          => 'Redação',
                        'simulados'         => 'Simulados',
                        'chat'              => 'Tudinha',
                        'aluno_caderno_novo'=> 'Meu Caderno (novo)',
                        'forum'             => 'Fórum',
                        'drive'             => 'Drive',
                        'jogos'             => 'Games',
                        'educa_hits'        => 'EducaHits',
                    ];
                ?>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600">Ação em lote (todos os módulos da página):</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="js-toggle-modules-select px-4 py-2 rounded-lg text-sm font-medium bg-green-100 text-green-800 hover:bg-green-200 transition-colors" data-target="modules-form" data-value="1">Habilitar tudo</button>
                        <button type="button" class="js-toggle-modules-select px-4 py-2 rounded-lg text-sm font-medium bg-amber-100 text-amber-800 hover:bg-amber-200 transition-colors" data-target="modules-form" data-value="0">Desabilitar tudo</button>
                        <button type="button" class="js-toggle-modules-select px-4 py-2 rounded-lg text-sm font-medium bg-slate-200 text-slate-700 hover:bg-slate-300 transition-colors" data-target="modules-form" data-value="2">Inativar tudo</button>
                    </div>
                </div>
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-2.5 bg-indigo-50 border-b border-indigo-100">
                        <span class="text-sm font-semibold text-indigo-800">Geral</span>
                        <span class="text-xs text-indigo-600 ml-2">(aluno, professor e admin)</span>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($geralKeys as $key => $val): ?>
                        <div class="flex items-center justify-between gap-3 p-3 rounded-lg bg-white border border-gray-100 hover:border-indigo-200 transition-colors">
                            <span class="text-gray-800 font-medium text-sm"><?= htmlspecialchars($geralLabels[$key]) ?></span>
                            <select name="modules[<?= $key ?>]" class="module-status-select block w-[180px] rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="1" <?= $val === '1' ? 'selected' : '' ?>>Habilitado</option>
                                <option value="0" <?= $val === '0' ? 'selected' : '' ?>>Desabilitado</option>
                                <option value="2" <?= $val === '2' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-2.5 bg-violet-50 border-b border-violet-100">
                        <span class="text-sm font-semibold text-violet-800">Professor</span>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($profKeys as $key => $val): ?>
                        <div class="flex items-center justify-between gap-3 p-3 rounded-lg bg-white border border-gray-100 hover:border-indigo-200 transition-colors">
                            <span class="text-gray-800 font-medium text-sm"><?= htmlspecialchars($profLabels[$key]) ?></span>
                            <select name="modules[<?= $key ?>]" class="module-status-select block w-[180px] rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="1" <?= $val === '1' ? 'selected' : '' ?>>Habilitado</option>
                                <option value="0" <?= $val === '0' ? 'selected' : '' ?>>Desabilitado</option>
                                <option value="2" <?= $val === '2' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-2.5 bg-emerald-50 border-b border-emerald-100">
                        <span class="text-sm font-semibold text-emerald-800">Aluno</span>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($alunoKeys as $key => $val): ?>
                        <div class="flex items-center justify-between gap-3 p-3 rounded-lg bg-white border border-gray-100 hover:border-indigo-200 transition-colors">
                            <span class="text-gray-800 font-medium text-sm"><?= htmlspecialchars($alunoLabels[$key]) ?></span>
                            <select name="modules[<?= $key ?>]" class="module-status-select block w-[180px] rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="1" <?= $val === '1' ? 'selected' : '' ?>>Habilitado</option>
                                <option value="0" <?= $val === '0' ? 'selected' : '' ?>>Desabilitado</option>
                                <option value="2" <?= $val === '2' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="pt-2 flex justify-end">
                    <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg transition-colors font-medium hover:opacity-90">
                        Salvar Módulos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="dev-card">
        <div class="dev-card-header">💰 Valor por Usuário</div>
        <p class="text-gray-500 text-sm mt-1 px-4 pt-2 pb-0">Configure o valor cobrado por cada usuário (professor ou aluno) pagante.</p>
        <div class="dev-card-body">
            <form id="valor-usuario-form" method="post" action="<?= URL ?>/admin/dev/valor-usuario/save" class="space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php
                require_once __DIR__ . '/../../../Core/LayoutHelper.php';
                $valor_por_usuario = LayoutHelper::get('valor_por_usuario', '0.00');
                ?>
                <div>
                    <label for="valor_por_usuario" class="block text-sm font-medium text-gray-700 mb-2">
                        Valor por Usuário (R$)
                    </label>
                    <input
                        type="number"
                        id="valor_por_usuario"
                        name="valor_por_usuario"
                        value="<?= htmlspecialchars($valor_por_usuario) ?>"
                        step="0.01"
                        min="0"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="0.00"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        Este valor será multiplicado pelo total de professores e alunos pagantes para calcular o valor total a pagar no mês.
                    </p>
                </div>
                <div class="flex justify-end pt-4">
                    <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Valor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="dev-card">
        <div class="dev-card-header">📅 Configurações Financeiras</div>
        <p class="text-gray-500 text-sm mt-1 px-4 pt-2 pb-0">Defina dia de fechamento, pagamento e vencimento.</p>
        <div class="dev-card-body">
            <form id="financeiro-config-form" method="post" action="<?= URL ?>/admin/dev/financeiro/save" class="space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php
                require_once __DIR__ . '/../../../Core/LayoutHelper.php';
                $financeiro_dia_fechamento = LayoutHelper::get('financeiro_dia_fechamento', '1');
                $financeiro_dia_pagamento = LayoutHelper::get('financeiro_dia_pagamento', '5');
                $financeiro_dia_vencimento = LayoutHelper::get('financeiro_dia_vencimento', '5');
                ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="financeiro_dia_fechamento" class="block text-sm font-medium text-gray-700 mb-2">
                            Dia de Fechamento
                        </label>
                        <input type="number" min="1" max="31"
                               id="financeiro_dia_fechamento" name="financeiro_dia_fechamento"
                               value="<?= htmlspecialchars($financeiro_dia_fechamento) ?>"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="financeiro_dia_pagamento" class="block text-sm font-medium text-gray-700 mb-2">
                            Dia de Pagamento
                        </label>
                        <input type="number" min="1" max="31"
                               id="financeiro_dia_pagamento" name="financeiro_dia_pagamento"
                               value="<?= htmlspecialchars($financeiro_dia_pagamento) ?>"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="financeiro_dia_vencimento" class="block text-sm font-medium text-gray-700 mb-2">
                            Dia de Vencimento
                        </label>
                        <input type="number" min="1" max="31"
                               id="financeiro_dia_vencimento" name="financeiro_dia_vencimento"
                               value="<?= htmlspecialchars($financeiro_dia_vencimento) ?>"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <div class="flex justify-end pt-4">
                    <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Configurações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="dev-card">
        <div class="dev-card-header">⚠️ Limites Diários por Aluno</div>
        <p class="text-gray-500 text-sm mt-1 px-4 pt-2 pb-0">Configure limites diários de uso para cada funcionalidade por aluno.</p>
        <div class="dev-card-body">
            <form id="limites-diarios-form" method="post" action="<?= URL ?>/admin/dev/limites-diarios/save" class="space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <?php
                require_once __DIR__ . '/../../../Core/LayoutHelper.php';
                $limites = [
                    'chat_interacoes' => LayoutHelper::get('limit_chat_interacoes', '0'),
                    'gerar_tema_redacao' => LayoutHelper::get('limit_gerar_tema_redacao', '0'),
                    'corrigir_redacao' => LayoutHelper::get('limit_corrigir_redacao', '0'),
                    'exercicios' => LayoutHelper::get('limit_exercicios', '0'),
                    'simulados' => LayoutHelper::get('limit_simulados', '0'),
                ];
                $descricoes = [
                    'chat_interacoes' => 'Interações de Chat (1 mensagem enviada + 1 resposta = 1 interação)',
                    'gerar_tema_redacao' => 'Geração de Tema de Redação',
                    'corrigir_redacao' => 'Correção de Redação',
                    'exercicios' => 'Exercícios Realizados',
                    'simulados' => 'Simulados Realizados',
                ];
                $observacoes = [
                    'chat_interacoes' => 'Nota: Mesmo que o aluno delete o chat visualmente, a interação continua contabilizada.',
                    'gerar_tema_redacao' => 'Limite diário de gerações de tema de redação pela IA.',
                    'corrigir_redacao' => 'Limite diário de correções de redação pela IA.',
                    'exercicios' => 'Limite diário de exercícios realizados.',
                    'simulados' => 'Limite diário de simulados iniciados.',
                ];
                ?>

                <?php foreach ($limites as $key => $valor): ?>
                    <div>
                        <label for="<?= $key ?>" class="block text-sm font-medium text-gray-700 mb-2">
                            <?= htmlspecialchars($descricoes[$key]) ?>
                        </label>
                        <input
                            type="number"
                            id="<?= $key ?>"
                            name="<?= $key ?>"
                            value="<?= htmlspecialchars($valor) ?>"
                            min="0"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="0 = sem limite"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            <?= htmlspecialchars($observacoes[$key]) ?>
                        </p>
                    </div>
                <?php endforeach; ?>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">
                        <strong>ℹ️ Importante:</strong> Digite 0 para desabilitar limite. Os limites são contabilizados por dia e resetam automaticamente à meia-noite.
                    </p>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Limites
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
(function() {
    document.querySelectorAll('.js-toggle-modules-select').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const value = btn.getAttribute('data-value');
            if (!targetId || value == null) return;
            const form = document.getElementById(targetId);
            if (!form) return;
            form.querySelectorAll('select.module-status-select').forEach((sel) => {
                sel.value = value;
            });
        });
    });
})();

(function() {
    const form = document.getElementById('modules-form');
    if (!form) return;
    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ Módulos atualizados com sucesso');
                window.location.reload();
            } else if (data && data.error) {
                alert('❌ Erro: ' + data.error);
            } else {
                alert('❌ Erro inesperado ao salvar módulos');
            }
        })
        .catch(() => alert('❌ Falha na conexão ao salvar módulos'));
    });
})();

(function() {
    const form = document.getElementById('valor-usuario-form');
    if (!form) return;
    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ ' + data.message);
            } else if (data && data.error) {
                alert('❌ Erro: ' + data.error);
            } else {
                alert('❌ Erro inesperado ao salvar valor');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('❌ Falha na conexão ao salvar valor');
        });
    });
})();

(function() {
    const form = document.getElementById('financeiro-config-form');
    if (!form) return;
    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ ' + data.message);
            } else if (data && data.error) {
                alert('❌ Erro: ' + data.error);
            } else {
                alert('❌ Erro inesperado ao salvar configurações financeiras');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('❌ Falha na conexão ao salvar configurações financeiras');
        });
    });
})();

(function() {
    const form = document.getElementById('limites-diarios-form');
    if (!form) return;
    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ ' + data.message);
            } else if (data && data.error) {
                alert('❌ Erro: ' + data.error);
            } else {
                alert('❌ Erro inesperado ao salvar limites');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('❌ Falha na conexão ao salvar limites');
        });
    });
})();
</script>
