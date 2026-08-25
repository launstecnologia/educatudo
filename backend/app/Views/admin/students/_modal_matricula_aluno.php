<?php
/** Modal e scripts de matrícula — fora das abas para não ficarem em display:none. */
?>
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
