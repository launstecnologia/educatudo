<?php
/**
 * Coordenação: alterar acertos de uma prova online e recalcular a nota.
 */
$provaId = (int) ($prova['id'] ?? 0);
$blocoId = (int) ($bloco['id'] ?? 0);
$questoes = is_array($questoes ?? null) ? $questoes : [];
$alunos = is_array($alunos ?? null) ? $alunos : [];
$historico = is_array($historico ?? null) ? $historico : [];
$temEditavel = false;
$questoesEditaveis = 0;
$concluidos = 0;
foreach ($alunos as $alunoLinha) {
    if (!empty($alunoLinha['editavel'])) {
        $temEditavel = true;
        $concluidos++;
    }
}
foreach ($questoes as $questaoLinha) {
    if (empty($questaoLinha['invalidada'])) {
        $questoesEditaveis++;
    }
}
$podeAlterar = $temEditavel && $questoesEditaveis > 0;
$statusClasses = [
    'finalizado' => 'bg-green-100 text-green-800',
    'iniciado' => 'bg-amber-100 text-amber-800',
    'cancelada' => 'bg-red-100 text-red-800',
    'nao_realizou' => 'bg-gray-100 text-gray-600',
];
$formatarNota = static function ($valor): string {
    if ($valor === null || $valor === '') {
        return '—';
    }
    return number_format((float) $valor, 2, ',', '.');
};
?>

<div class="mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Modificar notas</h2>
            <p class="text-gray-600">
                <?= htmlspecialchars((string) ($prova['titulo'] ?? 'Prova')) ?>
                · <?= htmlspecialchars((string) ($prova['professor_nome'] ?? 'Professor')) ?>
                · <?= htmlspecialchars((string) ($prova['materia_nome'] ?? 'Matéria')) ?>
            </p>
            <p class="text-sm text-gray-500 mt-1">
                <?= htmlspecialchars((string) ($bloco['titulo'] ?? '')) ?>
                · <?= $concluidos ?> aluno(s) com prova concluída
                · <?= count($questoes) ?> questão(ões)
            </p>
        </div>
        <a href="<?= URL ?>/admin/provas/blocos/<?= $blocoId ?>/gerenciar"
           class="inline-flex items-center gap-2 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
            ← Voltar
        </a>
    </div>
</div>

<?php if (!empty($flash['message'])): ?>
<?php $flashOk = (($flash['type'] ?? '') === 'success'); ?>
<div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= $flashOk ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?>" role="status">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
    Alterar um acerto recalcula a nota oficial desta prova online. A nota entra no boletim.
    Para salvar, confirme sua senha, escreva o motivo e declare que está ciente da alteração.
    Quem não concluiu a prova permanece bloqueado.
</div>

<?php if ($questoes === []): ?>
<div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Esta prova ainda não tem questões.</div>
<?php elseif ($alunos === []): ?>
<div class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500">Nenhum aluno encontrado para esta prova.</div>
<?php else: ?>

<div class="flex flex-wrap items-center gap-2 mb-4">
    <button type="button" id="mn-acertar-todos"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
            <?= $podeAlterar ? '' : 'disabled' ?>>
        <i class="fa-solid fa-check-double" aria-hidden="true"></i>
        Marcar todos os alunos como acerto total
    </button>
    <button type="button" id="mn-zerar-todos"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-800 text-sm font-medium hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            <?= $podeAlterar ? '' : 'disabled' ?>>
        <i class="fa-solid fa-ban" aria-hidden="true"></i>
        Zerar nota de todos
    </button>
    <button type="button" id="mn-desfazer"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
        Desfazer
    </button>
</div>

<div class="bg-white rounded-xl shadow-lg overflow-x-auto mb-8">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="sticky left-0 z-20 bg-gray-50 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase min-w-[220px]">Aluno</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                    <?php foreach ($questoes as $questao): ?>
                    <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 min-w-[92px]"
                        data-mn-questao="<?= (int) $questao['id'] ?>"
                        data-valor="<?= htmlspecialchars(number_format((float) $questao['valor'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-invalidada="<?= !empty($questao['invalidada']) ? '1' : '0' ?>"
                        title="<?= htmlspecialchars((string) ($questao['enunciado'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="text-gray-900">Q<?= (int) $questao['numero'] ?></div>
                        <?php if (!empty($questao['gabarito'])): ?>
                        <div class="font-normal normal-case text-gray-400">Gabarito <?= htmlspecialchars((string) $questao['gabarito']) ?></div>
                        <?php endif; ?>
                        <div class="font-normal normal-case"><?= htmlspecialchars(number_format((float) $questao['valor'], 1, ',', '.')) ?> pt</div>
                        <?php if (!empty($questao['invalidada'])): ?>
                        <div class="mt-1 text-[11px] font-semibold text-violet-700 normal-case">Invalidada</div>
                        <?php elseif ($podeAlterar): ?>
                        <button type="button"
                                class="mt-1 text-[11px] font-semibold text-indigo-700 hover:text-indigo-900 normal-case"
                                data-acertar-questao="<?= (int) $questao['id'] ?>">
                            Todos
                        </button>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($alunos as $aluno): ?>
                <?php
                $status = (string) ($aluno['status'] ?? '');
                $badge = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';
                $notaOriginal = $aluno['nota'] === null ? '' : number_format((float) $aluno['nota'], 2, '.', '');
                ?>
                <tr class="group" data-mn-aluno="<?= (int) $aluno['id'] ?>" data-editavel="<?= !empty($aluno['editavel']) ? '1' : '0' ?>">
                    <td class="sticky left-0 z-10 bg-white group-hover:bg-gray-50 px-4 py-3">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $aluno['nome']) ?></div>
                        <div class="text-xs text-gray-500">
                            <?= htmlspecialchars((string) ($aluno['turma_nome'] ?? '')) ?>
                            <?php if (($aluno['ra'] ?? '') !== ''): ?>
                                · RA <?= htmlspecialchars((string) $aluno['ra']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($aluno['editavel']) && $questoesEditaveis > 0): ?>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                            <button type="button"
                                    class="text-xs font-semibold text-indigo-700 hover:text-indigo-900"
                                    data-acertar-aluno="<?= (int) $aluno['id'] ?>">
                                Acertar todas
                            </button>
                            <button type="button"
                                    class="text-xs font-semibold text-gray-600 hover:text-gray-900"
                                    data-zerar-aluno="<?= (int) $aluno['id'] ?>">
                                Zerar nota
                            </button>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-3 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $badge ?>">
                            <?= htmlspecialchars((string) ($aluno['status_label'] ?? '')) ?>
                        </span>
                    </td>
                    <td class="px-3 py-3 whitespace-nowrap text-sm">
                        <span class="mn-nota font-semibold text-gray-900"
                              data-nota-original="<?= htmlspecialchars($notaOriginal, ENT_QUOTES, 'UTF-8') ?>">
                            <?= $formatarNota($aluno['nota']) ?>
                        </span>
                        <div class="mn-acertos text-xs text-gray-500"></div>
                    </td>
                    <?php foreach ($questoes as $questao): ?>
                    <?php
                    $resp = $aluno['respostas'][(int) $questao['id']] ?? ['correta' => null, 'pontuacao' => 0, 'letra' => null];
                    $correta = $resp['correta'];
                    $original = $correta === null ? '' : (string) (int) $correta;
                    $label = $correta === 1 ? 'Acertou' : ($correta === 0 ? 'Errou' : 'Sem resposta');
                    $cls = $correta === 1 ? 'bg-green-100 text-green-800' : ($correta === 0 ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600');
                    ?>
                    <td class="px-2 py-3 text-center">
                        <?php if (!empty($questao['invalidada'])): ?>
                        <span class="inline-flex flex-col items-center px-2 py-1 rounded-lg bg-violet-50 text-violet-800 text-xs font-semibold" title="Questão invalidada: vale o ponto para todos">
                            <?php if (!empty($resp['letra'])): ?><span><?= htmlspecialchars((string) $resp['letra']) ?></span><?php endif; ?>
                            Invalidada
                        </span>
                        <?php elseif (!empty($aluno['editavel'])): ?>
                        <button type="button"
                                class="mn-toggle inline-flex flex-col items-center min-w-[76px] px-2 py-1 rounded-lg text-xs font-semibold <?= $cls ?>"
                                data-aluno="<?= (int) $aluno['id'] ?>"
                                data-questao="<?= (int) $questao['id'] ?>"
                                data-original="<?= htmlspecialchars($original, ENT_QUOTES, 'UTF-8') ?>"
                                data-atual="<?= htmlspecialchars($original, ENT_QUOTES, 'UTF-8') ?>"
                                data-pontuacao="<?= htmlspecialchars(number_format((float) ($resp['pontuacao'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                title="Clique para alternar entre acertou e errou">
                            <?php if (!empty($resp['letra'])): ?>
                            <span class="font-medium opacity-80"><?= htmlspecialchars((string) $resp['letra']) ?></span>
                            <?php endif; ?>
                            <span class="mn-label"><?= $label ?></span>
                        </button>
                        <?php else: ?>
                        <span class="inline-flex flex-col items-center px-2 py-1 rounded-lg text-xs font-semibold <?= $cls ?>" title="Só é possível alterar quem já concluiu">
                            <?php if (!empty($resp['letra'])): ?><span><?= htmlspecialchars((string) $resp['letra']) ?></span><?php endif; ?>
                            <?= $label ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg overflow-hidden mb-24">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Histórico de alterações</h3>
        <p class="text-sm text-gray-600 mt-0.5">Quem mudou a nota, quando, o motivo e o que foi alterado em cada aluno.</p>
    </div>
    <?php if ($historico === []): ?>
    <div class="px-6 py-8 text-sm text-gray-500">Nenhuma alteração registrada nesta prova.</div>
    <?php else: ?>
    <div class="divide-y divide-gray-100">
        <?php foreach ($historico as $grupo): ?>
        <article class="px-6 py-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm font-semibold text-gray-900">
                    <?= htmlspecialchars((string) ($grupo['usuario_nome'] ?? '')) ?>
                    <span class="font-normal text-gray-500">· <?= htmlspecialchars((string) ($grupo['escopo_label'] ?? '')) ?></span>
                </p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars((string) ($grupo['quando'] ?? '')) ?></p>
            </div>
            <p class="mt-1 text-sm text-gray-700"><?= htmlspecialchars((string) ($grupo['observacao'] ?? '')) ?></p>
            <ul class="mt-3 space-y-2">
                <?php foreach ($grupo['alunos'] as $itemAluno): ?>
                <?php
                $partes = [];
                foreach ($itemAluno['detalhes'] as $detalhe) {
                    $partes[] = 'Q' . (int) $detalhe['numero'] . ': ' . $detalhe['antes'] . ' → ' . $detalhe['depois'];
                }
                $resumo = implode(' · ', $partes);
                ?>
                <li class="text-sm text-gray-800">
                    <span class="font-medium"><?= htmlspecialchars((string) $itemAluno['nome']) ?></span>
                    <span class="text-gray-500">
                        <?= htmlspecialchars((string) $itemAluno['nota_anterior']) ?>
                        →
                        <?= htmlspecialchars((string) $itemAluno['nota_nova']) ?>
                    </span>
                    <?php if ($resumo !== ''): ?>
                    <span class="block text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($resumo) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div id="mn-barra" class="hidden fixed bottom-0 inset-x-0 z-40 border-t border-amber-200 bg-amber-50 shadow-lg">
    <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-3">
        <p id="mn-resumo" class="text-sm text-amber-950 font-medium"></p>
        <div class="flex gap-2">
            <button type="button" id="mn-desfazer-barra" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm text-gray-700 hover:bg-gray-50">Desfazer</button>
            <button type="button" id="mn-abrir-confirma" class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700">Salvar alterações</button>
        </div>
    </div>
</div>

<div id="mn-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" data-fechar-modal></div>
    <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl p-6" role="dialog" aria-modal="true" aria-labelledby="mn-modal-titulo">
        <h3 id="mn-modal-titulo" class="text-lg font-semibold text-gray-900">Confirmar alteração de nota</h3>
        <p id="mn-modal-resumo" class="mt-1 text-sm text-gray-600"></p>
        <div class="mt-4 space-y-4">
            <div>
                <label for="mn-observacao" class="block text-sm font-medium text-gray-700 mb-1">Por que esta alteração está sendo feita?</label>
                <textarea id="mn-observacao" rows="3" maxlength="2000"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                          placeholder="Descreva o motivo. Este texto fica no histórico."></textarea>
            </div>
            <label class="flex items-start gap-2 text-sm text-gray-800">
                <input id="mn-ciente" type="checkbox" class="mt-1 rounded border-gray-300">
                <span>Estou ciente de que estou alterando a nota oficial dos alunos.</span>
            </label>
            <div>
                <label for="mn-senha" class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
                <input id="mn-senha" type="password" autocomplete="current-password"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <p id="mn-erro" class="hidden text-sm text-red-700"></p>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200" data-fechar-modal>Cancelar</button>
            <button type="button" id="mn-confirmar" class="px-4 py-2 rounded-lg bg-green-600 text-white font-semibold hover:bg-green-700">Confirmar alteração</button>
        </div>
    </div>
</div>

<script type="application/json" id="mn-config"><?= json_encode([
    'salvarUrl' => URL . '/admin/provas/blocos/' . $blocoId . '/provas/' . $provaId . '/modificar-notas',
    'csrf' => (string) ($csrf_token ?? ''),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script>
(function () {
    var cfgEl = document.getElementById('mn-config');
    if (!cfgEl) return;
    var cfg = JSON.parse(cfgEl.textContent || '{}');
    var escopo = 'manual';

    function formatarNota(valor) {
        return Number(valor || 0).toFixed(2).replace('.', ',');
    }

    function pintar(btn) {
        var atual = btn.dataset.atual;
        var original = btn.dataset.original;
        btn.classList.remove('bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800', 'bg-gray-100', 'text-gray-600', 'bg-gray-200', 'text-gray-700', 'ring-2', 'ring-amber-400');
        var label = 'Sem resposta';
        if (atual === '1') {
            btn.classList.add('bg-green-100', 'text-green-800');
            label = 'Acertou';
        } else if (atual === '0' && btn.dataset.zerada === '1') {
            btn.classList.add('bg-gray-200', 'text-gray-700');
            label = 'Errou';
        } else if (atual === '0') {
            btn.classList.add('bg-red-100', 'text-red-800');
            label = 'Errou';
        } else {
            btn.classList.add('bg-gray-100', 'text-gray-600');
        }
        if (atual !== original) {
            btn.classList.add('ring-2', 'ring-amber-400');
        }
        var alvo = btn.querySelector('.mn-label');
        if (alvo) alvo.textContent = label;
    }

    function coletar() {
        var alteracoes = [];
        var alunos = {};
        document.querySelectorAll('.mn-toggle').forEach(function (btn) {
            if (btn.dataset.atual === btn.dataset.original) return;
            if (btn.dataset.atual !== '0' && btn.dataset.atual !== '1') return;
            alteracoes.push({
                aluno_id: parseInt(btn.dataset.aluno, 10),
                questao_id: parseInt(btn.dataset.questao, 10),
                correta: parseInt(btn.dataset.atual, 10)
            });
            alunos[btn.dataset.aluno] = true;
        });
        return { alteracoes: alteracoes, alunos: Object.keys(alunos).length };
    }

    function atualizarNotas() {
        var questoes = Array.prototype.slice.call(document.querySelectorAll('[data-mn-questao]'));
        document.querySelectorAll('[data-mn-aluno]').forEach(function (row) {
            if (row.dataset.editavel !== '1') return;
            var nota = 0;
            var acertos = 0;
            questoes.forEach(function (th) {
                var valor = parseFloat(th.dataset.valor || '0');
                if (th.dataset.invalidada === '1') {
                    nota += valor;
                    acertos += 1;
                    return;
                }
                var btn = row.querySelector('.mn-toggle[data-questao="' + th.dataset.mnQuestao + '"]');
                if (!btn) return;
                var atual = btn.dataset.atual;
                var original = btn.dataset.original;
                var pontuacao = parseFloat(btn.dataset.pontuacao || '0');
                if (atual === '1' && original !== '1') {
                    nota += valor;
                    acertos += 1;
                } else if (atual === '0' && original !== '0') {
                    acertos += 0;
                } else {
                    nota += pontuacao;
                    if (atual === '1') acertos += 1;
                }
            });
            var notaEl = row.querySelector('.mn-nota');
            var acertosEl = row.querySelector('.mn-acertos');
            if (!notaEl) return;
            var originalNota = notaEl.dataset.notaOriginal;
            var textoNovo = formatarNota(nota);
            var pendente = Array.prototype.some.call(row.querySelectorAll('.mn-toggle'), function (btn) {
                return btn.dataset.atual !== btn.dataset.original;
            });
            if (pendente && originalNota !== '' && Number(originalNota).toFixed(2) !== nota.toFixed(2)) {
                notaEl.textContent = formatarNota(originalNota) + ' → ' + textoNovo;
                notaEl.classList.add('text-amber-800');
            } else if (pendente) {
                notaEl.textContent = textoNovo;
                notaEl.classList.add('text-amber-800');
            } else {
                notaEl.textContent = originalNota === '' ? '—' : formatarNota(originalNota);
                notaEl.classList.remove('text-amber-800');
            }
            if (acertosEl) {
                acertosEl.textContent = acertos + '/' + questoes.length + ' acertos';
            }
        });
    }

    function atualizarBarra() {
        var info = coletar();
        var barra = document.getElementById('mn-barra');
        var resumo = document.getElementById('mn-resumo');
        if (!barra || !resumo) return;
        if (info.alteracoes.length === 0) {
            barra.classList.add('hidden');
            return;
        }
        barra.classList.remove('hidden');
        resumo.textContent = info.alteracoes.length + ' questão(ões) em ' + info.alunos + ' aluno(s). A nota ainda não foi salva.';
        atualizarNotas();
    }

    function aplicarEm(lista, valor, novoEscopo, zerada) {
        lista.forEach(function (btn) {
            btn.dataset.atual = valor;
            btn.dataset.zerada = zerada ? '1' : '0';
            pintar(btn);
        });
        escopo = novoEscopo;
        atualizarBarra();
    }

    document.querySelectorAll('.mn-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.dataset.zerada = '0';
            btn.dataset.atual = btn.dataset.atual === '1' ? '0' : '1';
            escopo = 'manual';
            pintar(btn);
            atualizarBarra();
        });
    });

    var btnTodos = document.getElementById('mn-acertar-todos');
    if (btnTodos) {
        btnTodos.addEventListener('click', function () {
            aplicarEm(Array.prototype.slice.call(document.querySelectorAll('.mn-toggle')), '1', 'todos', false);
        });
    }
    var btnZerarTodos = document.getElementById('mn-zerar-todos');
    if (btnZerarTodos) {
        btnZerarTodos.addEventListener('click', function () {
            aplicarEm(Array.prototype.slice.call(document.querySelectorAll('.mn-toggle')), '0', 'zerar', true);
        });
    }
    document.querySelectorAll('[data-acertar-aluno]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-acertar-aluno');
            aplicarEm(Array.prototype.slice.call(document.querySelectorAll('.mn-toggle[data-aluno="' + id + '"]')), '1', 'aluno', false);
        });
    });
    document.querySelectorAll('[data-zerar-aluno]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-zerar-aluno');
            aplicarEm(Array.prototype.slice.call(document.querySelectorAll('.mn-toggle[data-aluno="' + id + '"]')), '0', 'zerar_aluno', true);
        });
    });
    document.querySelectorAll('[data-acertar-questao]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-acertar-questao');
            aplicarEm(Array.prototype.slice.call(document.querySelectorAll('.mn-toggle[data-questao="' + id + '"]')), '1', 'questao', false);
        });
    });

    function desfazer() {
        document.querySelectorAll('.mn-toggle').forEach(function (btn) {
            btn.dataset.atual = btn.dataset.original;
            btn.dataset.zerada = '0';
            pintar(btn);
        });
        escopo = 'manual';
        atualizarBarra();
        atualizarNotas();
    }
    var btnDesfazer = document.getElementById('mn-desfazer');
    var btnDesfazerBarra = document.getElementById('mn-desfazer-barra');
    if (btnDesfazer) btnDesfazer.addEventListener('click', desfazer);
    if (btnDesfazerBarra) btnDesfazerBarra.addEventListener('click', desfazer);

    var modal = document.getElementById('mn-modal');
    var erroEl = document.getElementById('mn-erro');
    function mostrarErro(texto) {
        if (!erroEl) return;
        erroEl.textContent = texto;
        erroEl.classList.remove('hidden');
    }
    function abrirModal() {
        var info = coletar();
        if (info.alteracoes.length === 0) return;
        var resumo = document.getElementById('mn-modal-resumo');
        if (resumo) {
            resumo.textContent = 'Você vai alterar ' + info.alteracoes.length + ' questão(ões) de ' + info.alunos + ' aluno(s).';
        }
        if (erroEl) erroEl.classList.add('hidden');
        var senha = document.getElementById('mn-senha');
        if (senha) senha.value = '';
        if (modal) modal.classList.remove('hidden');
        var obs = document.getElementById('mn-observacao');
        if (obs) obs.focus();
    }
    function fecharModal() {
        if (modal) modal.classList.add('hidden');
    }
    var btnAbrir = document.getElementById('mn-abrir-confirma');
    if (btnAbrir) btnAbrir.addEventListener('click', abrirModal);
    document.querySelectorAll('[data-fechar-modal]').forEach(function (el) {
        el.addEventListener('click', fecharModal);
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') fecharModal();
    });

    var btnConfirmar = document.getElementById('mn-confirmar');
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function () {
            var info = coletar();
            var observacao = (document.getElementById('mn-observacao').value || '').trim();
            var ciente = document.getElementById('mn-ciente').checked;
            var senha = document.getElementById('mn-senha').value || '';
            if (info.alteracoes.length === 0) {
                mostrarErro('Não há alteração para salvar.');
                return;
            }
            if (observacao.length < 10) {
                mostrarErro('Descreva o motivo da alteração (mínimo de 10 caracteres).');
                return;
            }
            if (!ciente) {
                mostrarErro('Confirme que está ciente de que está alterando uma nota.');
                return;
            }
            if (senha.trim() === '') {
                mostrarErro('Digite sua senha para confirmar.');
                return;
            }
            btnConfirmar.disabled = true;
            fetch(cfg.salvarUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': cfg.csrf
                },
                body: JSON.stringify({
                    _token: cfg.csrf,
                    senha: senha,
                    observacao: observacao,
                    ciente: true,
                    escopo: escopo,
                    alteracoes: info.alteracoes
                })
            }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                if (res.data && res.data.success) {
                    window.location.reload();
                    return;
                }
                mostrarErro((res.data && res.data.error) || 'Não foi possível salvar.');
                document.getElementById('mn-senha').value = '';
                btnConfirmar.disabled = false;
            }).catch(function () {
                mostrarErro('Erro de conexão. Tente de novo.');
                document.getElementById('mn-senha').value = '';
                btnConfirmar.disabled = false;
            });
        });
    }

    atualizarNotas();
})();
</script>
