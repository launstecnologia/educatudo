<?php
$evento = $evento ?? [];
$materia = $materia ?? [];
$turmas_com_alunos = $turmas_com_alunos ?? [];
$notas_map = $notas_map ?? [];
$csrf_token = $csrf_token ?? '';
$materiaId = (int) ($materia['id'] ?? $_GET['materia_id'] ?? 0);
$eventoId = (int) ($evento['id'] ?? 0);
$escalaMax = (float) ($escala_max ?? 10);
if ($escalaMax <= 0) {
    $escalaMax = 10.0;
}
if ($escalaMax > 100) {
    $escalaMax = 100.0;
}
$escalaMaxRotulo = (string) (int) $escalaMax;
$ajaxUrl = URL . '/professor/provas/evento-lancar-notas/' . $eventoId . '/ajax';
?>
<div class="mb-6">
    <a href="<?= URL ?>/professor/provas" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">← Voltar às provas</a>
</div>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Lançamento de notas</h1>
    <p class="text-gray-600 mt-2"><?= htmlspecialchars((string) ($evento['titulo'] ?? '')) ?> · <?= htmlspecialchars((string) ($materia['nome'] ?? '')) ?></p>
    <p class="text-sm text-gray-500 mt-1">Informe a nota de cada aluno (0 a <?= htmlspecialchars($escalaMaxRotulo, ENT_QUOTES, 'UTF-8') ?>). Deixe em branco para não lançar ainda. Use vírgula ou ponto para decimais. A nota é salva automaticamente ao sair do campo.</p>
    <?php if (!empty($evento['nota_unica_todas_materias'])): ?>
    <div class="mt-3 px-3 py-2 rounded-lg bg-violet-100 border border-violet-200 text-violet-900 text-sm">
        Configuração ativa: mesma nota para todas as matérias. Ao salvar, a nota deste aluno será replicada para as outras matérias do evento.
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (!empty($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<form method="post" action="<?= URL ?>/professor/provas/evento-lancar-notas/<?= $eventoId ?>" class="space-y-8" id="form-lancar-notas">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="materia_id" value="<?= $materiaId ?>">

    <div id="status-autosave-notas" class="hidden px-4 py-2 rounded-lg text-sm border" role="status" aria-live="polite"></div>

    <?php foreach ($turmas_com_alunos as $blocoTurma): ?>
        <?php
        $tid = (int) ($blocoTurma['turma_id'] ?? 0);
        $tNome = (string) ($blocoTurma['turma_nome'] ?? ('Turma #' . $tid));
        $alunos = $blocoTurma['alunos'] ?? [];
        ?>
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-slate-50">
                <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($tNome) ?></h2>
                <p class="text-sm text-gray-500"><?= count($alunos) ?> aluno(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-40">Nota (0–<?= htmlspecialchars($escalaMaxRotulo, ENT_QUOTES, 'UTF-8') ?>)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        foreach ($alunos as $al):
                            $aid = (int) ($al['id'] ?? 0);
                            $nk = $tid . '_' . $aid;
                            $nv = $notas_map[$nk] ?? null;
                            $notaStr = ($nv && isset($nv['nota']) && $nv['nota'] !== null) ? number_format((float) $nv['nota'], 2, '.', '') : '';
                            $transferido = !empty($al['transferido']);
                            $nomeAluno = \App\Helpers\AlunoLancamentoNotaHelper::rotuloNomeComTransferencia(
                                (string) ($al['nome'] ?? ''),
                                $transferido
                            );
                        ?>
                            <tr class="<?= $transferido ? 'bg-gray-100 text-gray-500' : 'hover:bg-gray-50' ?>">
                                <td class="px-4 py-3 font-medium <?= $transferido ? 'text-gray-500' : 'text-gray-900' ?>"><?= htmlspecialchars($nomeAluno) ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($transferido): ?>
                                        <input type="text" inputmode="decimal" value="<?= htmlspecialchars($notaStr) ?>"
                                               class="w-full max-w-xs border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-gray-100 text-gray-500"
                                               placeholder="—" autocomplete="off" disabled readonly>
                                    <?php else: ?>
                                        <input type="text" inputmode="decimal"
                                               name="notas[<?= $tid ?>][<?= $aid ?>]"
                                               value="<?= htmlspecialchars($notaStr) ?>"
                                               data-nota-autosave="1"
                                               data-turma-id="<?= $tid ?>"
                                               data-aluno-id="<?= $aid ?>"
                                               data-valor-salvo="<?= htmlspecialchars($notaStr) ?>"
                                               class="nota-autosave-input w-full max-w-xs border border-gray-300 rounded-lg px-2 py-1.5 text-sm"
                                               placeholder="—" autocomplete="off">
                                        <p class="nota-autosave-msg mt-1 text-xs hidden"></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($turmas_com_alunos)): ?>
        <p class="text-gray-600">Nenhum aluno encontrado nas turmas deste evento para você.</p>
    <?php else: ?>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700">
                Salvar notas
            </button>
            <a href="<?= URL ?>/professor/provas" class="inline-flex items-center px-6 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    <?php endif; ?>
</form>

<?php if (!empty($turmas_com_alunos)): ?>
<script>
(function () {
    var csrfToken = <?= json_encode($csrf_token, JSON_UNESCAPED_UNICODE) ?>;
    var materiaId = <?= (int) $materiaId ?>;
    var ajaxUrl = <?= json_encode($ajaxUrl, JSON_UNESCAPED_SLASHES) ?>;
    var statusEl = document.getElementById('status-autosave-notas');
    var statusTimer = null;
    var pendentes = 0;

    function normalizarValor(v) {
        return String(v || '').trim().replace(',', '.');
    }

    function setStatus(tipo, texto) {
        if (!statusEl) return;
        clearTimeout(statusTimer);
        statusEl.classList.remove('hidden', 'bg-slate-50', 'border-slate-200', 'text-slate-700', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-800', 'bg-red-50', 'border-red-200', 'text-red-800');
        if (tipo === 'saving') {
            statusEl.classList.add('bg-slate-50', 'border-slate-200', 'text-slate-700');
        } else if (tipo === 'ok') {
            statusEl.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-800');
            statusTimer = setTimeout(function () {
                if (pendentes === 0) statusEl.classList.add('hidden');
            }, 1800);
        } else {
            statusEl.classList.add('bg-red-50', 'border-red-200', 'text-red-800');
        }
        statusEl.textContent = texto;
    }

    function setCampoEstado(input, tipo, mensagem) {
        var msg = input.parentElement ? input.parentElement.querySelector('.nota-autosave-msg') : null;
        input.classList.remove('border-red-400', 'border-emerald-400', 'border-gray-300');
        if (tipo === 'error') {
            input.classList.add('border-red-400');
        } else if (tipo === 'ok') {
            input.classList.add('border-emerald-400');
        } else {
            input.classList.add('border-gray-300');
        }
        if (!msg) return;
        if (!mensagem) {
            msg.classList.add('hidden');
            msg.textContent = '';
            return;
        }
        msg.textContent = mensagem;
        msg.classList.remove('hidden', 'text-red-600', 'text-emerald-600', 'text-slate-500');
        msg.classList.add(tipo === 'error' ? 'text-red-600' : (tipo === 'ok' ? 'text-emerald-600' : 'text-slate-500'));
    }

    var escalaMax = <?= json_encode($escalaMax, JSON_UNESCAPED_UNICODE) ?>;
    var escalaMaxRotulo = <?= json_encode($escalaMaxRotulo, JSON_UNESCAPED_UNICODE) ?>;

    function validarLocal(valor) {
        var v = normalizarValor(valor);
        if (v === '') return { ok: true, valor: '' };
        if (!/^-?\d+(\.\d+)?$/.test(v)) return { ok: false, message: 'Nota inválida.' };
        var n = parseFloat(v);
        if (isNaN(n) || n < 0 || n > escalaMax) {
            return { ok: false, message: 'Notas devem estar entre 0 e ' + escalaMaxRotulo + '.' };
        }
        return { ok: true, valor: v };
    }

    function valoresEquivalentes(a, b) {
        var na = normalizarValor(a);
        var nb = normalizarValor(b);
        if (na === nb) return true;
        if (na === '' || nb === '') return false;
        var fa = parseFloat(na);
        var fb = parseFloat(nb);
        return !isNaN(fa) && !isNaN(fb) && Math.abs(fa - fb) < 0.0001;
    }

    function salvarCelula(input) {
        if (!input || input.disabled) return;
        var atual = normalizarValor(input.value);
        var salvo = normalizarValor(input.getAttribute('data-valor-salvo') || '');
        if (valoresEquivalentes(atual, salvo)) {
            setCampoEstado(input, 'idle', '');
            return;
        }
        var local = validarLocal(input.value);
        if (!local.ok) {
            setCampoEstado(input, 'error', local.message);
            setStatus('error', local.message);
            return;
        }

        var versao = (parseInt(input.getAttribute('data-save-version') || '0', 10) || 0) + 1;
        input.setAttribute('data-save-version', String(versao));

        if (input._notaAbort) {
            try { input._notaAbort.abort(); } catch (e) {}
        }
        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        input._notaAbort = controller;

        pendentes += 1;
        setCampoEstado(input, 'saving', 'Salvando…');
        setStatus('saving', pendentes > 1 ? ('Salvando ' + pendentes + ' notas…') : 'Salvando…');

        var fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('materia_id', String(materiaId));
        fd.append('turma_id', input.getAttribute('data-turma-id') || '');
        fd.append('aluno_id', input.getAttribute('data-aluno-id') || '');
        fd.append('nota', String(input.value || '').trim());

        var fetchOpts = { method: 'POST', credentials: 'same-origin', body: fd };
        if (controller) fetchOpts.signal = controller.signal;

        fetch(ajaxUrl, fetchOpts)
            .then(function (r) {
                return r.json().then(function (data) {
                    return { http: r.status, data: data || {} };
                }).catch(function () {
                    return { http: r.status, data: { success: false, message: 'Resposta inválida do servidor.' } };
                });
            })
            .then(function (res) {
                if (String(input.getAttribute('data-save-version') || '') !== String(versao)) {
                    return;
                }
                if (!res.data.success) {
                    var msg = res.data.message || 'Erro ao salvar.';
                    setCampoEstado(input, 'error', msg);
                    setStatus('error', msg);
                    return;
                }
                var notaSalva = res.data.nota;
                var valorPersistido = (notaSalva === null || typeof notaSalva === 'undefined')
                    ? ''
                    : String(Number(notaSalva).toFixed(2));
                input.setAttribute('data-valor-salvo', valorPersistido);
                input.value = valorPersistido;
                setCampoEstado(input, 'ok', 'Salvo');
                setStatus('ok', res.data.message || 'Nota salva.');
                setTimeout(function () {
                    if (String(input.getAttribute('data-save-version') || '') === String(versao)) {
                        setCampoEstado(input, 'idle', '');
                    }
                }, 1200);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (String(input.getAttribute('data-save-version') || '') !== String(versao)) {
                    return;
                }
                setCampoEstado(input, 'error', 'Erro de conexão ao salvar.');
                setStatus('error', 'Erro de conexão ao salvar.');
            })
            .finally(function () {
                if (input._notaAbort === controller) {
                    input._notaAbort = null;
                }
                pendentes = Math.max(0, pendentes - 1);
                if (pendentes > 0) {
                    setStatus('saving', 'Salvando ' + pendentes + ' notas…');
                }
            });
    }

    document.querySelectorAll('input[data-nota-autosave="1"]').forEach(function (input) {
        input.addEventListener('input', function () {
            clearTimeout(input._notaDebounce);
            setCampoEstado(input, 'saving', 'Na fila…');
            input._notaDebounce = setTimeout(function () {
                salvarCelula(input);
            }, 550);
        });
        input.addEventListener('blur', function () {
            clearTimeout(input._notaDebounce);
            salvarCelula(input);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(input._notaDebounce);
                input.blur();
            }
        });
    });

    var form = document.getElementById('form-lancar-notas');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (pendentes > 0) {
                e.preventDefault();
                setStatus('saving', 'Aguarde o salvamento automático terminar…');
            }
        });
    }
})();
</script>
<?php endif; ?>
