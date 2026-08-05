<?php
$defaultAgentId = 'educaprof-prof-' . (int) ($user['id'] ?? 0);
$profIdStr = (string) ($user['id'] ?? '');
$hasExistingAgent = false;
foreach ($agents ?? [] as $_a) {
    if (($profIdStr !== '') && (string) ($_a['professor_id'] ?? '') === $profIdStr) {
        $hasExistingAgent = true;
        break;
    }
}
?>
<div class="space-y-6 educaprof-wizard" id="educaprofWizard" data-existing-agent="<?= $hasExistingAgent ? '1' : '0' ?>">
    <div class="rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">EducaProf</p>
                <h2 class="text-2xl font-bold text-gray-900 mt-1">Agente IA do professor</h2>
                <p class="text-sm text-gray-600 mt-2 max-w-xl">Siga os passos: crie o agente, envie material para o RAG e teste no chat. IDs são gerados automaticamente.</p>
            </div>
            <div class="text-sm rounded-xl bg-white/80 border border-indigo-100 px-4 py-3 shadow-sm">
                <div class="text-gray-500 text-xs uppercase font-medium">Escola</div>
                <div class="font-mono text-gray-900"><?= htmlspecialchars($school_slug ?? '') ?></div>
                <?php if (empty($configured)): ?>
                    <span class="inline-block mt-2 text-red-600 font-medium text-xs">EducaProf não configurado</span>
                <?php else: ?>
                    <span class="inline-block mt-2 text-emerald-600 font-medium text-xs">Pronto para uso</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($error)): ?>
            <div class="mt-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-center gap-2 sm:gap-0" id="educaprofStepsNav" role="tablist">
        <?php
        $steps = [
            ['n' => 1, 'label' => 'Agente', 'sub' => 'Criar ou editar'],
            ['n' => 2, 'label' => 'Material', 'sub' => 'RAG'],
            ['n' => 3, 'label' => 'Chat', 'sub' => 'Testar'],
        ];
        foreach ($steps as $i => $s):
            $disabled = ((int) $s['n'] > 1 && !$hasExistingAgent) ? ' disabled' : '';
        ?>
            <button type="button" data-step-target="<?= (int) $s['n'] ?>"
                    class="step-nav-btn group flex items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition sm:flex-1 sm:max-w-[200px]
                           border-gray-200 bg-white text-gray-600 hover:border-indigo-200 hover:bg-indigo-50/50
                           data-[active=true]:border-indigo-600 data-[active=true]:bg-indigo-600 data-[active=true]:text-white data-[active=true]:shadow-md
                           disabled:opacity-40 disabled:pointer-events-none disabled:cursor-not-allowed"<?= $disabled ?>>
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

    <?php
    $promptChipGroups = [
        'objetivo' => [
            'label' => 'Objetivo (essencial)',
            'icon' => '🎯',
            'options' => [
                'Explicar conteúdo', 'Criar atividade', 'Criar prova', 'Criar simulado',
                'Fazer resumo', 'Revisão rápida', 'Tirar dúvida', 'Gerar exemplos',
            ],
        ],
        'serie' => [
            'label' => 'Série / público',
            'icon' => '🎓',
            'options' => ['6º ano', '7º ano', '8º ano', '9º ano', '1º EM', '2º EM', '3º EM', 'Vestibular', 'ENEM'],
        ],
        'dificuldade' => [
            'label' => 'Nível de dificuldade',
            'icon' => '📊',
            'options' => ['Muito fácil', 'Fácil', 'Médio', 'Difícil'],
        ],
        'tipo_saida' => [
            'label' => 'Tipo de saída',
            'icon' => '🧩',
            'options' => [
                'Texto explicativo', 'Lista de exercícios', 'Prova com gabarito',
                'Questões estilo ENEM', 'Plano de aula', 'Passo a passo',
            ],
        ],
        'estilo' => [
            'label' => 'Estilo de explicação',
            'icon' => '🎨',
            'options' => [
                'Didático', 'Simples', 'Resumido', 'Detalhado', 'Divertido', 'Formal',
                'Passo a passo', 'Com exemplos do dia a dia',
            ],
        ],
        'contexto_aluno' => [
            'label' => 'Contexto do aluno',
            'icon' => '⚡',
            'options' => [
                'Aluno com dificuldade', 'Primeira vez vendo o conteúdo', 'Revisão para prova',
                'Preparação para ENEM', 'Aula rápida (10 min)',
            ],
        ],
        'rag' => [
            'label' => 'Uso de material (RAG)',
            'icon' => '📎',
            'options' => [
                'Usar material enviado via ingestão', 'Basear na apostila fornecida',
                'Gerar com base no PDF/texto enviado', 'Resumir documento enviado',
            ],
        ],
    ];
    $quickActions = [
        ['id' => 'prova_completa', 'label' => '🔥 Criar prova completa'],
        ['id' => 'explicar_crianca', 'label' => '🧠 Explicar como criança'],
        ['id' => 'lista_exercicios', 'label' => '📄 Lista de exercícios'],
        ['id' => 'revisao_rapida', 'label' => '⚡ Revisão rápida'],
        ['id' => 'simulado_enem', 'label' => '🎯 Simulado ENEM'],
        ['id' => 'plano_aula', 'label' => '📚 Plano de aula'],
    ];
    ?>

    <div class="step-panel rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" data-step-panel="1">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6 pb-4 border-b border-gray-100">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Passo 1 — Agente EducaProf</h3>
                <p class="text-sm text-gray-600 mt-1">Monte o prompt com chips ou escreva livremente. O ID do agente é automático.</p>
            </div>
            <div class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-mono text-slate-700 border border-slate-200">
                <span class="text-slate-500">ID automático</span><br>
                <span id="displayAgentId"><?= htmlspecialchars($defaultAgentId) ?></span>
            </div>
        </div>

        <form id="agentForm" class="space-y-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="agent_id" id="agent_id_hidden" value="<?= htmlspecialchars($defaultAgentId) ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do agente</label>
                <input type="text" name="name" class="w-full max-w-xl border border-gray-300 rounded-xl px-4 py-2.5" required placeholder="Ex.: Matemática 1º EM">
            </div>

            <div class="rounded-2xl border border-indigo-100 bg-indigo-50/30 p-5 space-y-5" id="promptBuilder">
                <?php foreach ($promptChipGroups as $groupKey => $group): ?>
                    <div class="prompt-chip-group" data-group-key="<?= htmlspecialchars($groupKey) ?>">
                        <span class="flex items-center gap-2 text-sm font-semibold text-gray-800 mb-2">
                            <span><?= htmlspecialchars($group['icon']) ?></span>
                            <?= htmlspecialchars($group['label']) ?>
                        </span>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($group['options'] as $opt): ?>
                                <button type="button"
                                        class="prompt-chip px-3 py-1.5 text-sm rounded-full border border-gray-300 bg-white text-gray-700 hover:border-indigo-400 hover:bg-indigo-50 transition"
                                        data-value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-800 mb-2"><span>🚀</span> Ações rápidas</span>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($quickActions as $qa): ?>
                            <button type="button" class="quick-action px-3 py-1.5 text-sm rounded-lg bg-amber-100 border border-amber-200 text-amber-900 hover:bg-amber-200 transition"
                                    data-quick="<?= htmlspecialchars($qa['id']) ?>"><?= htmlspecialchars($qa['label']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2 border-t border-indigo-100">
                    <button type="button" id="btnRebuildPrompt" class="px-4 py-2 text-sm rounded-xl bg-indigo-600 text-white hover:bg-indigo-700">Atualizar prompt dos chips</button>
                    <button type="button" id="btnClearChips" class="px-4 py-2 text-sm rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50">Limpar chips</button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">System prompt</label>
                <textarea name="system_prompt" id="system_prompt_field" rows="9" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-mono text-sm" placeholder="Use os chips acima ou escreva aqui..."></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-3xl">
                <div>
                    <label class="text-xs text-gray-500">Modelo</label>
                    <input type="text" name="model" value="gpt-4o" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Temperature</label>
                    <input type="number" step="0.1" min="0" max="1" name="temperature" value="0.3" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Max tokens</label>
                    <input type="number" min="128" max="8192" name="max_tokens" value="2048" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" id="btnSaveAgent" class="rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 font-semibold shadow-sm">Salvar agente</button>
                <button type="button" id="btnUpdateAgent" class="rounded-xl bg-amber-500 hover:bg-amber-600 text-white px-6 py-3 font-semibold shadow-sm">Atualizar agente</button>
            </div>
        </form>
        <pre id="agentResult" class="mt-4 bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs overflow-auto max-h-40 font-mono"></pre>

        <div class="mt-6 flex justify-end">
            <button type="button" id="btnGoStep2" <?= $hasExistingAgent ? '' : 'disabled' ?> class="rounded-xl px-6 py-3 font-semibold educaprof-btn-next <?= $hasExistingAgent ? 'bg-emerald-600 text-white hover:bg-emerald-700 cursor-pointer' : 'bg-gray-200 text-gray-500 cursor-not-allowed' ?>">
                Continuar para material (RAG) →
            </button>
        </div>
    </div>

    <div class="step-panel rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hidden" data-step-panel="2">
        <div class="mb-6 pb-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">Passo 2 — Material para o RAG</h3>
            <p class="text-sm text-gray-600 mt-1">Indexação no <strong>EducaProf</strong> para o mesmo agente do passo 1. Texto, links (cole no campo) ou .txt / .md.</p>
            <p id="ragAgentHint" class="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 hidden"></p>
        </div>

        <form id="ingestForm" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="agent_id" id="ingest_agent_id" value="<?= htmlspecialchars($defaultAgentId) ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Arquivo .txt ou .md (opcional)</label>
                <input type="file" id="ingest_file" accept=".txt,.text,.md,.markdown,text/plain,text/markdown"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-emerald-100 file:text-emerald-800">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Conteúdo para indexar</label>
                <textarea name="content" id="ingest_content" rows="10" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm" required placeholder="Cole texto, links (um por linha), transcrições..."></textarea>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="button" id="btnIngest" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 font-semibold">Ingerir no EducaProf</button>
                <button type="button" id="btnSkipRag" class="rounded-xl border border-gray-300 text-gray-700 px-6 py-3 font-medium hover:bg-gray-50">Pular e ir ao chat</button>
            </div>
        </form>
        <pre id="ingestResult" class="mt-4 bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs overflow-auto max-h-36 font-mono"></pre>

        <div class="mt-6 flex justify-between">
            <button type="button" class="step-back rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" data-go-step="1">← Voltar</button>
            <button type="button" id="btnGoStep3" class="rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 font-semibold">Ir para chat →</button>
        </div>
    </div>

    <div class="step-panel rounded-2xl border border-gray-200 bg-white p-6 shadow-sm hidden" data-step-panel="3">
        <div class="mb-6 pb-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">Passo 3 — Chat de teste EducaProf</h3>
            <p class="text-sm text-gray-600 mt-1">Use o agente do passo 1. Se houver material ingerido, o RAG entra quando a API aplicar.</p>
            <div class="mt-3 text-xs">
                <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-1 font-mono text-slate-700">Sessão: <span id="displayChatId" class="ml-1"></span></span>
            </div>
        </div>

        <form id="chatForm" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="agent_id" id="chat_agent_id" value="<?= htmlspecialchars($defaultAgentId) ?>">
            <input type="hidden" name="chat_id" id="chat_id_hidden" value="">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sua pergunta</label>
                <textarea name="question" rows="5" class="w-full border border-gray-300 rounded-xl px-4 py-3" placeholder="Pergunte algo ao agente..." required></textarea>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="button" id="btnChatSend" class="rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 font-semibold">Enviar</button>
                <button type="button" id="btnNewChatSession" class="rounded-xl border border-gray-300 text-gray-700 px-5 py-3 text-sm font-medium hover:bg-gray-50">Nova sessão de teste</button>
            </div>
        </form>
        <pre id="chatResult" class="mt-4 bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs overflow-auto max-h-64 font-mono"></pre>

        <div class="mt-6">
            <button type="button" class="step-back rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50" data-go-step="2">← Voltar ao material</button>
        </div>
    </div>

    <details class="rounded-2xl border border-gray-200 bg-white shadow-sm group">
        <summary class="cursor-pointer px-6 py-4 font-semibold text-gray-800 list-none flex items-center justify-between">
            <span>Mais no EducaProf</span>
            <span class="text-gray-400 transition group-open:rotate-180">▼</span>
        </summary>
        <div class="px-6 pb-6 pt-0 border-t border-gray-100">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-4">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Uso e custos</h4>
                    <div class="flex gap-2 mb-2">
                        <input id="usageLimit" type="number" min="1" max="1000" value="50" class="border border-gray-300 rounded-lg px-3 py-2 w-28">
                        <button type="button" onclick="loadUsage()" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm">Atualizar</button>
                    </div>
                    <pre id="usageResult" class="bg-slate-50 border rounded-lg p-3 text-xs overflow-auto max-h-48"></pre>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Agentes da escola</h4>
                    <?php if (empty($agents)): ?>
                        <p class="text-sm text-gray-500">Nenhum agente listado.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto max-h-48 overflow-y-auto border rounded-lg">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr class="text-left text-gray-600">
                                        <th class="py-2 px-2">ID</th>
                                        <th class="py-2 px-2">Nome</th>
                                        <th class="py-2 px-2">Modelo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agents as $a): ?>
                                    <tr class="border-t">
                                        <td class="py-2 px-2 font-mono text-xs"><?= htmlspecialchars((string)($a['agent_id'] ?? '')) ?></td>
                                        <td class="py-2 px-2"><?= htmlspecialchars((string)($a['name'] ?? '')) ?></td>
                                        <td class="py-2 px-2"><?= htmlspecialchars((string)($a['model'] ?? '')) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </details>
</div>

<script>
(function () {
    const DEFAULT_AGENT_ID = <?= json_encode($defaultAgentId, JSON_UNESCAPED_UNICODE) ?>;

    function newChatId() {
        try {
            if (typeof crypto !== 'undefined' && crypto.randomUUID) return 'educaprof-chat-' + crypto.randomUUID();
        } catch (e) {}
        return 'educaprof-chat-' + Date.now() + '-' + Math.random().toString(36).slice(2, 9);
    }

    const flow = {
        agentId: DEFAULT_AGENT_ID,
        agentSaved: false,
        chatId: newChatId(),
        currentStep: 1
    };

    function syncIdsToForms() {
        document.getElementById('agent_id_hidden').value = flow.agentId;
        document.getElementById('ingest_agent_id').value = flow.agentId;
        document.getElementById('chat_agent_id').value = flow.agentId;
        document.getElementById('chat_id_hidden').value = flow.chatId;
        var d = document.getElementById('displayAgentId');
        if (d) d.textContent = flow.agentId;
        var c = document.getElementById('displayChatId');
        if (c) c.textContent = flow.chatId;
    }

    function setStep(n) {
        flow.currentStep = n;
        document.querySelectorAll('[data-step-panel]').forEach(function (el) {
            var sn = parseInt(el.getAttribute('data-step-panel'), 10);
            el.classList.toggle('hidden', sn !== n);
        });
        document.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            var t = parseInt(btn.getAttribute('data-step-target'), 10);
            btn.setAttribute('data-active', t === n ? 'true' : 'false');
        });
        if (n === 2) {
            var hint = document.getElementById('ragAgentHint');
            if (hint) {
                hint.classList.remove('hidden');
                hint.innerHTML = 'Material será indexado para o agente: <strong class="font-mono">' + flow.agentId + '</strong>';
            }
        }
    }

    function unlockAfterSave() {
        flow.agentSaved = true;
        document.querySelectorAll('.step-nav-btn[data-step-target="2"], .step-nav-btn[data-step-target="3"]').forEach(function (b) {
            b.disabled = false;
        });
        var b2 = document.getElementById('btnGoStep2');
        if (b2) {
            b2.disabled = false;
            b2.classList.remove('bg-gray-200', 'text-gray-500', 'cursor-not-allowed');
            b2.classList.add('bg-emerald-600', 'text-white', 'hover:bg-emerald-700', 'cursor-pointer');
        }
    }

    document.querySelectorAll('.step-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var t = parseInt(btn.getAttribute('data-step-target'), 10);
            if (btn.disabled) return;
            if (t > 1 && !flow.agentSaved) return;
            setStep(t);
        });
    });

    document.querySelectorAll('.step-back').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setStep(parseInt(btn.getAttribute('data-go-step'), 10));
        });
    });

    document.getElementById('btnGoStep2').addEventListener('click', function () {
        if (!flow.agentSaved) return;
        setStep(2);
    });
    document.getElementById('btnGoStep3').addEventListener('click', function () { setStep(3); });
    document.getElementById('btnSkipRag').addEventListener('click', function () { setStep(3); });

    document.getElementById('btnNewChatSession').addEventListener('click', function () {
        flow.chatId = newChatId();
        syncIdsToForms();
        var cr = document.getElementById('chatResult');
        if (cr) cr.textContent = '';
    });

    document.getElementById('btnSaveAgent').addEventListener('click', function () {
        document.querySelector('#agentForm [name=action]').value = 'create';
        submitAgentFlow('agentForm', '<?= URL ?>/professor/ai-agents/agent', 'agentResult');
    });
    document.getElementById('btnUpdateAgent').addEventListener('click', function () {
        document.querySelector('#agentForm [name=action]').value = 'update';
        submitAgentFlow('agentForm', '<?= URL ?>/professor/ai-agents/agent', 'agentResult');
    });
    document.getElementById('btnIngest').addEventListener('click', function () {
        syncIdsToForms();
        submitForm('ingestForm', '<?= URL ?>/professor/ai-agents/ingest', 'ingestResult');
    });
    document.getElementById('btnChatSend').addEventListener('click', function () {
        syncIdsToForms();
        submitForm('chatForm', '<?= URL ?>/professor/ai-agents/chat', 'chatResult');
    });

    window.submitAgentFlow = async function (formId, url, outputId) {
        syncIdsToForms();
        const form = document.getElementById(formId);
        const out = document.getElementById(outputId);
        out.textContent = 'Processando...';
        try {
            const res = await fetch(url, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
            const json = await res.json();
            out.textContent = JSON.stringify(json, null, 2);
            if (json.success) {
                var aid = (json.data && json.data.agent_id) || (json.data && json.data.id);
                if (typeof aid === 'string' && aid.trim()) flow.agentId = aid.trim();
                syncIdsToForms();
                unlockAfterSave();
            }
        } catch (e) {
            out.textContent = 'Erro: ' + e.message;
        }
    };

    syncIdsToForms();
    setStep(1);

    if (document.getElementById('educaprofWizard').getAttribute('data-existing-agent') === '1') {
        flow.agentSaved = true;
        unlockAfterSave();
    }

    const labelMap = {
        objetivo: 'Objetivo principal',
        serie: 'Série / público-alvo',
        dificuldade: 'Nível de dificuldade',
        tipo_saida: 'Tipo de saída esperada',
        estilo: 'Estilo de explicação',
        contexto_aluno: 'Contexto do aluno',
        rag: 'Material e RAG'
    };
    const quickPresets = {
        prova_completa: '\n\n[Ação rápida — prova completa]\nElabore uma prova completa e coerente: enunciados claros, alternativas no estilo adequado à série, gabarito comentado e critérios de correção. Estruture por competências quando fizer sentido.',
        explicar_crianca: '\n\n[Ação rápida — explicação simples]\nExplique como se estivesse falando com uma criança curiosa: analogias do dia a dia, frases curtas, um exemplo por ideia e evite jargões sem definição.',
        lista_exercicios: '\n\n[Ação rápida — lista de exercícios]\nGere uma lista numerada de exercícios com progressão de dificuldade; ao final, gabarito separado (sem revelar no meio).',
        revisao_rapida: '\n\n[Ação rápida — revisão rápida]\nFaça uma revisão rápida em tópicos: conceitos-chave, fórmulas ou ideias centrais, armadilhas comuns e 3 questões de fixação.',
        simulado_enem: '\n\n[Ação rápida — simulado ENEM]\nMonte questões no estilo ENEM: contextualização, habilidades da BNCC quando aplicável, 5 alternativas, distratores plausíveis e gabarito comentado.',
        plano_aula: '\n\n[Ação rápida — plano de aula]\nProduza plano de aula completo: objetivos, materiais, abertura, desenvolvimento (com tempos sugeridos), atividade prática e fechamento com verificação de aprendizagem.'
    };

    function extractQuickSection(txt) {
        if (!txt) return '';
        const i = txt.indexOf('\n\n[Ação rápida');
        return i === -1 ? '' : txt.substring(i);
    }
    function getSelectedValues() {
        const out = {};
        document.querySelectorAll('.prompt-chip-group').forEach(function (wrap) {
            const key = wrap.getAttribute('data-group-key');
            const active = wrap.querySelector('.prompt-chip.is-selected');
            if (active) out[key] = active.getAttribute('data-value') || active.textContent.trim();
        });
        return out;
    }
    function setChipSelected(btn, groupWrap) {
        groupWrap.querySelectorAll('.prompt-chip').forEach(function (b) {
            b.classList.remove('is-selected', 'bg-indigo-600', 'text-white', 'border-indigo-600');
            b.classList.add('bg-white', 'text-gray-700', 'border-gray-300');
        });
        btn.classList.add('is-selected', 'bg-indigo-600', 'text-white', 'border-indigo-600');
        btn.classList.remove('bg-white', 'text-gray-700', 'border-gray-300');
    }
    function clearAllChips() {
        document.querySelectorAll('.prompt-chip').forEach(function (b) {
            b.classList.remove('is-selected', 'bg-indigo-600', 'text-white', 'border-indigo-600');
            b.classList.add('bg-white', 'text-gray-700', 'border-gray-300');
        });
    }
    function buildPromptFromChips() {
        const sel = getSelectedValues();
        const lines = [];
        lines.push('Você é um assistente pedagógico do EducaProf. Responda em português do Brasil, com clareza e correção linguística.');
        lines.push('');
        if (sel.objetivo) lines.push('**' + labelMap.objetivo + ':** ' + sel.objetivo + '.');
        if (sel.serie) lines.push('**' + labelMap.serie + ':** ' + sel.serie + '.');
        if (sel.dificuldade) lines.push('**' + labelMap.dificuldade + ':** ' + sel.dificuldade + '.');
        if (sel.tipo_saida) lines.push('**' + labelMap.tipo_saida + ':** ' + sel.tipo_saida + '.');
        if (sel.estilo) lines.push('**' + labelMap.estilo + ':** ' + sel.estilo + '.');
        if (sel.contexto_aluno) lines.push('**' + labelMap.contexto_aluno + ':** ' + sel.contexto_aluno + '.');
        if (sel.rag) lines.push('**' + labelMap.rag + ':** ' + sel.rag + '. Quando houver conteúdo indexado (RAG), priorize esse material, integre com a resposta e cite trechos quando útil.');
        lines.push('');
        lines.push('Se o pedido do professor for ambíguo, faça uma pergunta curtíssima de esclarecimento antes de responder.');
        return lines.join('\n');
    }
    function applyPromptToTextarea() {
        const ta = document.getElementById('system_prompt_field');
        const quick = extractQuickSection(ta.value);
        ta.value = buildPromptFromChips() + quick;
    }

    document.querySelectorAll('.prompt-chip-group').forEach(function (wrap) {
        wrap.querySelectorAll('.prompt-chip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.classList.contains('is-selected')) {
                    btn.classList.remove('is-selected', 'bg-indigo-600', 'text-white', 'border-indigo-600');
                    btn.classList.add('bg-white', 'text-gray-700', 'border-gray-300');
                } else setChipSelected(btn, wrap);
                applyPromptToTextarea();
            });
        });
    });
    document.querySelectorAll('.quick-action').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-quick');
            const ta = document.getElementById('system_prompt_field');
            ta.value = buildPromptFromChips() + extractQuickSection(ta.value) + (quickPresets[id] || '');
        });
    });
    document.getElementById('btnRebuildPrompt').addEventListener('click', applyPromptToTextarea);
    document.getElementById('btnClearChips').addEventListener('click', function () {
        clearAllChips();
        document.getElementById('system_prompt_field').value = '';
    });
})();

async function submitForm(formId, url, outputId) {
    const form = document.getElementById(formId);
    const out = document.getElementById(outputId);
    out.textContent = 'Processando...';
    try {
        const res = await fetch(url, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
        const json = await res.json();
        out.textContent = JSON.stringify(json, null, 2);
    } catch (e) {
        out.textContent = 'Erro: ' + e.message;
    }
}

async function loadUsage() {
    const out = document.getElementById('usageResult');
    const limit = document.getElementById('usageLimit').value || '50';
    out.textContent = 'Carregando...';
    try {
        const res = await fetch('<?= URL ?>/professor/ai-agents/usage?limit=' + encodeURIComponent(limit), { credentials: 'same-origin' });
        const json = await res.json();
        out.textContent = JSON.stringify(json, null, 2);
    } catch (e) {
        out.textContent = 'Erro de conexao';
    }
}

document.getElementById('ingest_file').addEventListener('change', function (ev) {
    var f = ev.target.files && ev.target.files[0];
    if (!f) return;
    var name = (f.name || '').toLowerCase();
    if (!name.endsWith('.txt') && !name.endsWith('.md') && !name.endsWith('.markdown')) {
        alert('Use .txt ou .md. Para PDF/áudio/vídeo, extraia o texto e cole no EducaProf.');
        ev.target.value = '';
        return;
    }
    var r = new FileReader();
    r.onload = function () {
        var ta = document.getElementById('ingest_content');
        ta.value = (ta.value ? ta.value.trim() + '\n\n---\n\n' : '') + (r.result || '').toString();
    };
    r.readAsText(f);
    ev.target.value = '';
});
</script>
