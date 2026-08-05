<?php
$esc     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$val     = fn($k) => $esc($prefill[$k] ?? $_POST[$k] ?? '');
$isTipo  = fn($t) => ($tipo ?? 'nova') === $t ? 'selected' : '';
$isRem   = ($tipo ?? 'nova') === 'rematricula';
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/enrollment"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Nova Matrícula</h2>
            <p class="text-sm text-gray-600">Vincule um aluno existente a uma turma e ano letivo.</p>
        </div>
    </div>
</div>

<?php if ($isRem && !empty($prefill['aluno_nome'])): ?>
<div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-700 flex items-center gap-2">
    <i class="fa-solid fa-circle-info"></i>
    Dados pré-carregados do aluno. Revise e confirme para gerar o contrato.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden w-full">
    <form method="POST" action="<?= URL ?>/admin/enrollment" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

        <!-- Tipo e origem -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Tipo de Vínculo</h3>
                <p class="mt-1 text-sm text-gray-500">Selecione o tipo de matrícula e a turma de destino.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo <span class="text-red-500">*</span>
                    </label>
                    <select id="tipo" name="tipo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="nova" <?= $isTipo('nova') ?>>Matrícula Nova</option>
                        <option value="rematricula" <?= $isTipo('rematricula') ?>>Rematrícula</option>
                        <option value="transferencia" <?= $isTipo('transferencia') ?>>Transferência</option>
                    </select>
                </div>
                <div>
                    <label for="origem" class="block text-sm font-medium text-gray-700 mb-2">Origem</label>
                    <select id="origem" name="origem"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="interno">Interno (secretaria)</option>
                        <option value="site">Site</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="indicacao">Indicação</option>
                        <option value="evento">Evento</option>
                    </select>
                </div>
                <div>
                    <label for="ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-2">Ano Letivo</label>
                    <select id="ano_letivo_id" name="ano_letivo_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Selecionar...</option>
                        <?php foreach ($anos_letivos as $al): ?>
                        <option value="<?= (int)$al['id'] ?>" <?= $al['ativo'] ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
                    <select id="turma_id" name="turma_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Selecionar...</option>
                        <?php foreach ($turmas as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)($prefill['turma_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= $esc($t['nome']) ?> — <?= $esc($t['serie']) ?> (<?= $esc($t['turno'] ?? '') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <!-- Dados do aluno -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Dados do Aluno</h3>
                <p class="mt-1 text-sm text-gray-500">Busque um aluno existente ou preencha os dados para cadastro.</p>
            </div>

            <?php if (!empty($prefill['aluno_id'])): ?>
            <!-- Aluno pré-selecionado: exibe card e hidden input -->
            <input type="hidden" name="aluno_id" id="aluno_id_input" value="<?= (int)$prefill['aluno_id'] ?>">
            <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 flex items-center gap-3">
                <i class="fa-solid fa-user-graduate text-blue-500 text-lg"></i>
                <div>
                    <p class="font-semibold text-blue-800 text-sm"><?= $esc($prefill['aluno_nome'] ?? '') ?></p>
                    <?php if (!empty($prefill['aluno_ra'])): ?>
                    <p class="text-xs text-blue-600">RA: <?= $esc($prefill['aluno_ra']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Busca live de aluno -->
            <input type="hidden" name="aluno_id" id="aluno_id_input" value="">
            <div class="relative" id="aluno-search-wrapper">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Buscar Aluno <span class="text-red-500">*</span>
                </label>
                <input type="text" id="aluno_search_input" placeholder="Digite nome, RA ou e-mail..."
                       autocomplete="off"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <div id="aluno_search_dropdown"
                     class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto">
                </div>
                <div id="aluno_selected_card" class="hidden mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                    <div>
                        <p id="aluno_selected_nome" class="font-semibold text-blue-800 text-sm"></p>
                        <p id="aluno_selected_ra" class="text-xs text-blue-600"></p>
                    </div>
                    <button type="button" id="aluno_clear_btn" class="text-xs text-red-500 hover:text-red-700">Trocar</button>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nome completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="aluno_nome" value="<?= $val('aluno_nome') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">CPF</label>
                    <input type="text" name="aluno_cpf" value="<?= $val('aluno_cpf') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data de nascimento</label>
                    <input type="date" name="aluno_data_nasc" value="<?= $val('aluno_data_nasc') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">E-mail</label>
                    <input type="email" name="aluno_email" value="<?= $val('aluno_email') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Telefone</label>
                    <input type="text" name="aluno_telefone" value="<?= $val('aluno_telefone') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
        </section>

        <!-- Dados do responsável -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Responsável Legal</h3>
                <p class="mt-1 text-sm text-gray-500">Selecione um responsável cadastrado ou preencha manualmente.</p>
            </div>

            <?php if (!empty($responsaveis)): ?>
            <!-- Responsáveis cadastrados do aluno -->
            <div id="resp-radio-section" class="space-y-2">
                <div class="space-y-2">
                    <?php foreach ($responsaveis as $r): ?>
                    <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 has-[:checked]:border-green-400 has-[:checked]:bg-green-50">
                        <input type="radio" name="_resp_select" value="<?= (int)$r['id'] ?>"
                               data-nome="<?= $esc($r['nome']) ?>"
                               data-cpf="<?= $esc($r['cpf'] ?? '') ?>"
                               data-email="<?= $esc($r['email'] ?? '') ?>"
                               data-telefone="<?= $esc($r['telefone'] ?? '') ?>"
                               data-parentesco="<?= $esc($r['tipo_vinculo'] ?? '') ?>"
                               class="resp-radio rounded border-gray-300 text-green-600 focus:ring-green-500"
                               <?= $r['is_financeiro'] ? 'checked' : '' ?>>
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?= $esc($r['nome']) ?></p>
                            <p class="text-xs text-gray-500">
                                <?= $esc($r['tipo_vinculo'] ?? '') ?>
                                <?php if ($r['is_financeiro']): ?> · <span class="text-blue-600">Responsável financeiro</span><?php endif; ?>
                            </p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 has-[:checked]:border-green-400 has-[:checked]:bg-green-50">
                        <input type="radio" name="_resp_select" value="" class="resp-radio rounded border-gray-300 text-green-600 focus:ring-green-500">
                        <span class="text-sm text-gray-600">Outro / Preencher manualmente</span>
                    </label>
                </div>
            </div>
            <?php else: ?>
            <div id="resp-fetch-section" class="hidden">
                <div id="resp-radio-list" class="space-y-2"></div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="resp-fields">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nome completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="resp_nome" id="resp_nome" value="<?= $val('resp_nome') ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">CPF</label>
                    <input type="text" name="resp_cpf" id="resp_cpf" value="<?= $val('resp_cpf') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Parentesco</label>
                    <input type="text" name="resp_parentesco" id="resp_parentesco" value="<?= $val('resp_parentesco') ?>"
                           placeholder="Ex.: Mãe, Pai, Avó..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">E-mail</label>
                    <input type="email" name="resp_email" id="resp_email" value="<?= $val('resp_email') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp / Telefone</label>
                    <input type="text" name="resp_telefone" id="resp_telefone" value="<?= $val('resp_telefone') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Endereço</label>
                    <input type="text" name="resp_endereco" id="resp_endereco" value="<?= $val('resp_endereco') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
        </section>

        <!-- Informações adicionais -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Informações Adicionais</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prazo para conclusão</label>
                    <input type="date" name="expira_em"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observações / Cláusulas especiais</label>
                    <textarea name="observacoes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none"></textarea>
                </div>
            </div>
        </section>

        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200">
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="<?= URL ?>/admin/enrollment"
                   class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    <i class="fa-solid fa-check mr-2"></i>
                    Salvar Matrícula
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    // ── Responsável: preencher campos ao selecionar radio ────────────────────
    function fillResp(radio) {
        if (!radio) return;
        var d = radio.dataset;
        if (radio.value === '') {
            // limpa campos para preenchimento manual
            ['nome','cpf','email','telefone','parentesco'].forEach(function(f) {
                var el = document.getElementById('resp_' + f);
                if (el) el.value = '';
            });
            return;
        }
        var map = {nome: d.nome, cpf: d.cpf, email: d.email, telefone: d.telefone, parentesco: d.parentesco};
        Object.keys(map).forEach(function(f) {
            var el = document.getElementById('resp_' + f);
            if (el) el.value = map[f] || '';
        });
    }

    document.querySelectorAll('.resp-radio').forEach(function(radio) {
        radio.addEventListener('change', function() { fillResp(this); });
    });

    // Auto-fill on load from checked radio
    var checked = document.querySelector('.resp-radio:checked');
    if (checked) fillResp(checked);

    // ── Busca live de aluno (apenas quando não há prefill) ──────────────────
    var searchInput = document.getElementById('aluno_search_input');
    if (!searchInput) return;

    var dropdown   = document.getElementById('aluno_search_dropdown');
    var selectedCard = document.getElementById('aluno_selected_card');
    var selectedNome = document.getElementById('aluno_selected_nome');
    var selectedRa   = document.getElementById('aluno_selected_ra');
    var clearBtn   = document.getElementById('aluno_clear_btn');
    var alunoIdInput = document.getElementById('aluno_id_input');
    var respFetchSection = document.getElementById('resp-fetch-section');
    var respRadioList    = document.getElementById('resp-radio-list');

    var debounceTimer;

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        var q = this.value.trim();
        if (q.length < 2) { dropdown.classList.add('hidden'); return; }
        debounceTimer = setTimeout(function() {
            fetch('/admin/alunos/search?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    dropdown.innerHTML = '';
                    if (!data.length) {
                        dropdown.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">Nenhum aluno encontrado.</div>';
                        dropdown.classList.remove('hidden');
                        return;
                    }
                    data.forEach(function(aluno) {
                        var item = document.createElement('div');
                        item.className = 'px-4 py-3 text-sm cursor-pointer hover:bg-green-50 border-b border-gray-100 last:border-0';
                        item.innerHTML = '<span class="font-medium text-gray-800">' + aluno.nome + '</span>'
                            + '<span class="ml-2 text-xs text-gray-500">RA: ' + (aluno.ra || '—') + '</span>'
                            + (aluno.turma ? '<span class="ml-2 text-xs text-gray-400">' + aluno.turma + '</span>' : '');
                        item.addEventListener('click', function() {
                            selectAluno(aluno);
                        });
                        dropdown.appendChild(item);
                    });
                    dropdown.classList.remove('hidden');
                })
                .catch(function() { dropdown.classList.add('hidden'); });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#aluno-search-wrapper')) dropdown.classList.add('hidden');
    });

    function selectAluno(aluno) {
        alunoIdInput.value = aluno.id;
        searchInput.classList.add('hidden');
        dropdown.classList.add('hidden');
        selectedCard.classList.remove('hidden');
        selectedNome.textContent = aluno.nome;
        selectedRa.textContent = aluno.ra ? 'RA: ' + aluno.ra : '';

        // Carregar responsáveis via API
        if (respFetchSection && respRadioList) {
            fetch('/admin/alunos/' + aluno.id + '/responsaveis')
                .then(function(r) { return r.json(); })
                .then(function(resps) {
                    respRadioList.innerHTML = '';
                    if (!resps.length) { respFetchSection.classList.add('hidden'); return; }
                    resps.forEach(function(r) {
                        var label = document.createElement('label');
                        label.className = 'flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50';
                        label.innerHTML = '<input type="radio" name="_resp_select" value="' + r.id + '"'
                            + ' data-nome="' + escAttr(r.nome) + '"'
                            + ' data-cpf="' + escAttr(r.cpf || '') + '"'
                            + ' data-email="' + escAttr(r.email || '') + '"'
                            + ' data-telefone="' + escAttr(r.telefone || '') + '"'
                            + ' data-parentesco="' + escAttr(r.tipo_vinculo || '') + '"'
                            + ' class="resp-radio rounded border-gray-300 text-green-600 focus:ring-green-500"'
                            + (r.is_financeiro ? ' checked' : '') + '>'
                            + '<div><p class="text-sm font-medium text-gray-800">' + escHtml(r.nome) + '</p>'
                            + '<p class="text-xs text-gray-500">' + escHtml(r.tipo_vinculo || '') + (r.is_financeiro ? ' · <span class="text-blue-600">Financeiro</span>' : '') + '</p></div>';
                        label.querySelector('input').addEventListener('change', function() { fillResp(this); });
                        respRadioList.appendChild(label);
                    });
                    var manual = document.createElement('label');
                    manual.className = 'flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50';
                    manual.innerHTML = '<input type="radio" name="_resp_select" value="" class="resp-radio rounded border-gray-300 text-green-600 focus:ring-green-500"> <span class="text-sm text-gray-600">Outro / Preencher manualmente</span>';
                    manual.querySelector('input').addEventListener('change', function() { fillResp(this); });
                    respRadioList.appendChild(manual);
                    respFetchSection.classList.remove('hidden');

                    // Auto-fill do responsável financeiro
                    var fc = respRadioList.querySelector('.resp-radio:checked');
                    if (fc) fillResp(fc);
                })
                .catch(function() {});
        }
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            alunoIdInput.value = '';
            searchInput.value = '';
            searchInput.classList.remove('hidden');
            selectedCard.classList.add('hidden');
            if (respFetchSection) respFetchSection.classList.add('hidden');
        });
    }

    function escAttr(s) { return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
    function escHtml(s) { return String(s).replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
})();
</script>
