<?php
/**
 * @var array  $prefill
 * @var array  $irmaos
 * @var array  $anos_letivos
 * @var array  $turmas
 * @var array  $discount_rules
 * @var array  $plans
 * @var array  $config
 * @var int    $aluno_id
 * @var int    $enrollment_id
 * @var int    $ano_letivo_id
 * @var string $csrf_token
 */
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$aluno   = $prefill['aluno']       ?? [];
$resp    = $prefill['responsavel'] ?? [];
$matric  = $prefill['matricula']   ?? [];
$anoAtual= $prefill['ano_letivo']  ?? [];
$diaVencPadrao = (int)($config['dia_vencimento_padrao'] ?? 10);
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance/contracts"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Novo Contrato Financeiro</h2>
            <p class="text-sm text-gray-600">
                Defina o plano, responsável e condições de pagamento.
                <?php if ($enrollment_id): ?>
                <span class="ml-2 text-blue-600"><i class="fa-solid fa-link mr-1"></i> Vinculado à Matrícula #<?= (int)$enrollment_id ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<?php if (!empty($irmaos)): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm">
    <p class="font-semibold text-amber-800 mb-1"><i class="fa-solid fa-people-arrows mr-1"></i> Irmãos identificados</p>
    <p class="text-amber-700">
        <?= count($irmaos) ?> irmão(s) com contrato ativo: <?= $esc(implode(', ', array_column($irmaos, 'aluno_nome'))) ?>.
        Desconto de irmãos será sugerido automaticamente abaixo.
    </p>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden w-full">
    <form method="POST" action="<?= URL ?>/admin/finance/contracts" id="contract-form" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
        <input type="hidden" name="enrollment_id" value="<?= (int)$enrollment_id ?>">

        <!-- Aluno -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Aluno</h3>
            </div>

            <?php if (!$aluno_id): ?>
            <input type="hidden" name="aluno_id" id="aluno-id-hidden">
            <div class="relative">
                <label for="aluno-search" class="block text-sm font-medium text-gray-700 mb-2">
                    Buscar Aluno <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="text" id="aluno-search" placeholder="Buscar aluno por nome ou RA..."
                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <i class="fa-solid fa-search absolute right-3 top-2.5 text-gray-400"></i>
                </div>
                <div id="aluno-results" class="mt-1 bg-white border border-gray-200 rounded-lg shadow-lg hidden max-h-48 overflow-y-auto"></div>
                <div id="aluno-selected" class="mt-2 text-sm text-green-700 hidden">
                    <i class="fa-solid fa-check-circle mr-1"></i> <span id="aluno-selected-nome"></span>
                </div>
            </div>
            <?php else: ?>
            <input type="hidden" name="aluno_id" value="<?= (int)$aluno_id ?>">
            <div class="flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="w-9 h-9 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                    <?= mb_substr($aluno['nome'] ?? '?', 0, 1) ?>
                </div>
                <div>
                    <p class="font-medium text-gray-800"><?= $esc($aluno['nome'] ?? '') ?></p>
                    <p class="text-xs text-gray-500">RA: <?= $esc($aluno['ra'] ?? '—') ?> · <?= $esc($matric['turma_nome'] ?? '') ?></p>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Responsável financeiro -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Responsável Financeiro</h3>
                <p class="mt-1 text-sm text-gray-500">Dados do responsável e ano letivo do contrato.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nome <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="responsavel_nome" required
                           value="<?= $esc($resp['nome'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">CPF</label>
                    <input type="text" name="responsavel_cpf" value="<?= $esc($resp['cpf'] ?? '') ?>"
                           placeholder="000.000.000-00"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">E-mail</label>
                    <input type="email" name="responsavel_email" value="<?= $esc($resp['email'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp</label>
                    <input type="text" name="responsavel_telefone" value="<?= $esc($resp['telefone'] ?? '') ?>"
                           placeholder="(00) 00000-0000"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Ano Letivo <span class="text-red-500">*</span>
                    </label>
                    <select name="ano_letivo_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Selecione...</option>
                        <?php foreach ($anos_letivos as $al): ?>
                        <option value="<?= (int)$al['id'] ?>"
                            <?= ($ano_letivo_id && $ano_letivo_id == $al['id']) || (!$ano_letivo_id && ($anoAtual['id'] ?? 0) == $al['id']) ? 'selected' : '' ?>>
                            <?= $esc($al['ano']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <!-- Plano financeiro -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Plano Financeiro</h3>
                <p class="mt-1 text-sm text-gray-500">Selecione um plano pré-configurado ou defina manualmente abaixo.</p>
            </div>

            <?php if (!empty($plans)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <label class="plan-card border-2 border-gray-200 rounded-xl p-4 cursor-pointer hover:border-green-400 transition"
                       data-total="0" data-mes-inicio="1" data-mes-fim="12" data-num-parcelas="12">
                    <input type="radio" name="plan_id" value="0" class="sr-only" checked>
                    <div class="font-medium text-gray-700 text-sm mb-1">Manual</div>
                    <div class="text-xs text-gray-400">Definir valores manualmente</div>
                </label>
                <?php foreach ($plans as $plan): ?>
                <?php
                  $items = $plan['items'] ?? [];
                  $mensais = array_values(array_filter($items, fn($i) => $i['categoria'] === 'mensalidade'));
                  $total   = (float)($plan['total'] ?? 0);
                  $mesI    = $items ? min(array_column($items, 'mes_inicio')) : 1;
                  $mesF    = $items ? max(array_map(fn($i) => $i['mes_fim'] ?? $i['mes_inicio'], $items)) : 12;
                  $numP    = $items ? array_sum(array_column($items, 'num_parcelas')) : 12;
                ?>
                <label class="plan-card border-2 border-gray-200 rounded-xl p-4 cursor-pointer hover:border-green-400 transition"
                       data-total="<?= $total ?>" data-mes-inicio="<?= $mesI ?>" data-mes-fim="<?= $mesF ?>"
                       data-num-parcelas="<?= $numP ?>" data-plan-items='<?= htmlspecialchars(json_encode($items), ENT_QUOTES) ?>'>
                    <input type="radio" name="plan_id" value="<?= (int)$plan['id'] ?>" class="sr-only">
                    <div class="font-semibold text-gray-800 text-sm mb-1"><?= $esc($plan['nome']) ?></div>
                    <?php if (!empty($mensais)): ?>
                    <div class="text-xl font-bold text-gray-900">R$ <?= number_format($mensais[0]['valor_base'], 2, ',', '.') ?></div>
                    <div class="text-xs text-gray-400">por mês</div>
                    <?php endif; ?>
                    <div class="mt-1 text-xs text-gray-500">Total: R$ <?= number_format($total, 2, ',', '.') ?></div>
                    <div class="text-xs text-gray-400"><?= (int)$numP ?>x parcelas</div>
                </label>
                <?php endforeach; ?>
            </div>

            <div id="plan-detail" class="hidden bg-gray-50 rounded-xl p-4 text-sm border border-gray-200">
                <h4 class="font-semibold text-gray-700 mb-2">Itens incluídos no plano</h4>
                <div id="plan-detail-items" class="space-y-1"></div>
                <div class="flex justify-between font-semibold text-gray-800 border-t border-gray-200 mt-2 pt-2">
                    <span>Total</span><span id="plan-detail-total">—</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="manual-fields">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Valor Bruto Total (R$)</label>
                    <input type="text" name="valor_bruto" id="valor-bruto" placeholder="0,00"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mês Início</label>
                    <input type="number" name="mes_inicio" id="mes-inicio" value="1" min="1" max="12"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mês Fim</label>
                    <input type="number" name="mes_fim" id="mes-fim" value="12" min="1" max="12"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">N de Parcelas</label>
                    <input type="number" name="num_parcelas" id="num-parcelas" value="12" min="1" max="24"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dia Vencimento</label>
                    <input type="number" name="dia_vencimento" value="<?= $diaVencPadrao ?>" min="1" max="31"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
        </section>

        <!-- Descontos -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Descontos</h3>
                <p class="mt-1 text-sm text-gray-500">Selecione as regras de desconto aplicáveis a este contrato.</p>
            </div>

            <?php if (!empty($irmaos)): ?>
            <?php
              $posicao = count($irmaos) + 1;
              $pct     = $posicao === 2 ? 10 : 15;
            ?>
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="desconto_irmaos_ativo" value="1"
                           class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50" checked>
                    <div>
                        <p class="text-sm font-medium text-amber-800">Desconto de irmãos (<?= (int)$posicao ?>filho)</p>
                        <p class="text-xs text-amber-600"><?= (int)$pct ?>% de desconto aplicado automaticamente</p>
                    </div>
                </label>
                <input type="hidden" name="irmao_posicao" value="<?= (int)$posicao ?>">
                <input type="hidden" name="irmao_aluno_id" value="<?= (int)($irmaos[0]['aluno_id'] ?? 0) ?>">
            </div>
            <?php endif; ?>

            <div class="space-y-2">
                <?php foreach ($discount_rules as $rule): ?>
                <?php if ($rule['tipo'] === 'irmaos') continue; ?>
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="discount_rule_ids[]" value="<?= (int)$rule['id'] ?>"
                           class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                    <div class="flex-1">
                        <span class="text-sm font-medium text-gray-700"><?= $esc($rule['nome']) ?></span>
                        <span class="ml-2 text-xs text-gray-400">
                            <?= $rule['calculo'] === 'percentual' ? $rule['valor'] . '%' : 'R$ ' . number_format($rule['valor'], 2, ',', '.') ?>
                        </span>
                        <?php if ($rule['tipo'] === 'bolsa'): ?>
                            <span class="ml-1 inline-flex px-1.5 py-0.5 text-xs rounded bg-green-100 text-green-700">bolsa</span>
                        <?php endif; ?>
                        <?php if ($rule['requer_aprovacao']): ?>
                            <span class="ml-1 inline-flex px-1.5 py-0.5 text-xs rounded bg-red-100 text-red-700">requer aprovação</span>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
                <?php if (empty(array_filter($discount_rules, fn($r) => $r['tipo'] !== 'irmaos'))): ?>
                <p class="text-xs text-gray-400 italic">Nenhuma regra de desconto cadastrada.
                    <a href="<?= URL ?>/admin/finance/discount-rules" class="text-blue-600 hover:underline">Criar regras</a>
                </p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Observações -->
        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Observações</h3>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Observações internas</label>
                <textarea name="observacoes" rows="3"
                          placeholder="Observações internas sobre o contrato..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
            </div>
        </section>

        <!-- Resumo -->
        <div class="p-6 bg-gray-50 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Total do Contrato</p>
                    <p class="text-2xl font-bold text-gray-900" id="resumo-total">—</p>
                    <p class="text-xs text-gray-500 mt-0.5" id="resumo-parcelas"></p>
                </div>
                <div class="flex flex-col-reverse sm:flex-row gap-3">
                    <a href="<?= URL ?>/admin/finance/contracts"
                       class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                        <i class="fa-solid fa-file-invoice-dollar mr-2"></i>
                        Gerar Contrato e Parcelas
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function atualizarResumo() {
    const total = parseFloat(document.getElementById('valor-bruto').value.replace(',', '.')) || 0;
    const parcelas = parseInt(document.getElementById('num-parcelas').value) || 1;
    const r = document.getElementById('resumo-total');
    const p = document.getElementById('resumo-parcelas');
    if (total > 0) {
        r.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2});
        const vp = (total / parcelas).toLocaleString('pt-BR', {minimumFractionDigits:2});
        p.textContent = parcelas + 'x de R$ ' + vp;
    } else { r.textContent = '—'; p.textContent = ''; }
}
document.getElementById('valor-bruto')?.addEventListener('input', atualizarResumo);
document.getElementById('num-parcelas')?.addEventListener('input', atualizarResumo);

// ── Seleção de plano ─────────────────────────────────────────────────────────
document.querySelectorAll('.plan-card').forEach(card => {
    card.addEventListener('click', function() {
        // Highlight
        document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('border-green-500','bg-green-50'));
        this.classList.add('border-green-500', 'bg-green-50');
        this.querySelector('input[type=radio]').checked = true;

        const isManual   = this.querySelector('input').value === '0';
        const total      = parseFloat(this.dataset.total || '0');
        const mesInicio  = this.dataset.mesInicio || '1';
        const mesFim     = this.dataset.mesFim    || '12';
        const numParcelas= this.dataset.numParcelas || '12';

        document.getElementById('mes-inicio').value   = mesInicio;
        document.getElementById('mes-fim').value      = mesFim;
        document.getElementById('num-parcelas').value = numParcelas;

        if (!isManual && total > 0) {
            document.getElementById('valor-bruto').value = total.toFixed(2).replace('.', ',');
        }

        // Detalhe itens
        const detail = document.getElementById('plan-detail');
        const items  = document.getElementById('plan-detail-items');
        if (detail && !isManual && this.dataset.planItems) {
            const planItems = JSON.parse(this.dataset.planItems);
            items.innerHTML = planItems.map(i =>
                `<div class="flex justify-between text-gray-600">
                    <span>${i.descricao} (${i.num_parcelas}x / mês ${i.mes_inicio}${i.mes_fim && i.mes_fim !== i.mes_inicio ? '–' + i.mes_fim : ''})</span>
                    <span class="font-medium">R$ ${parseFloat(i.valor_base).toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>
                </div>`
            ).join('');
            document.getElementById('plan-detail-total').textContent =
                'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2});
            detail.classList.remove('hidden');
        } else if (detail) {
            detail.classList.add('hidden');
        }
        atualizarResumo();
    });
});
// Ativa o primeiro card (Manual) visualmente
document.querySelector('.plan-card')?.classList.add('border-green-500','bg-green-50');

// ── Busca de aluno ───────────────────────────────────────────────────────────
const searchInput = document.getElementById('aluno-search');
if (searchInput) {
    let timer;
    searchInput.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const q = this.value.trim();
            if (q.length < 2) { document.getElementById('aluno-results').classList.add('hidden'); return; }
            fetch('/admin/alunos/search?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    const box = document.getElementById('aluno-results');
                    if (!data.length) { box.classList.add('hidden'); return; }
                    box.innerHTML = data.map(a =>
                        `<div class="px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-sm border-b last:border-0"
                              onclick="selectAluno(${a.id},'${a.nome.replace(/'/g,"\\'")}'  )">
                            <span class="font-medium">${a.nome}</span>
                            <span class="text-gray-400 text-xs ml-2">RA: ${a.ra||'—'}</span>
                        </div>`
                    ).join('');
                    box.classList.remove('hidden');
                });
        }, 300);
    });
}
function selectAluno(id, nome) {
    document.getElementById('aluno-id-hidden').value = id;
    document.getElementById('aluno-search').value = nome;
    document.getElementById('aluno-results').classList.add('hidden');
    document.getElementById('aluno-selected').classList.remove('hidden');
    document.getElementById('aluno-selected-nome').textContent = nome;
}
</script>
