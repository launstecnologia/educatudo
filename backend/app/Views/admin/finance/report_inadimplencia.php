<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

$page_header_title    = 'Inadimplência';
$page_header_subtitle = count($inadimplentes) . ' aluno(s) com parcelas vencidas';
ob_start(); ?>
<a href="<?= URL ?>/admin/finance"
   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-arrow-left mr-2 text-gray-500"></i> Financeiro
</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<!-- Filtro de ano letivo -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-6">
    <form method="GET" class="flex items-center gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</label>
            <select name="ano_letivo_id"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    onchange="this.form.submit()">
                <option value="">Todos os anos</option>
                <?php foreach ($anos_letivos as $al): ?>
                <option value="<?= (int)$al['id'] ?>" <?= $ano_letivo_id == $al['id'] ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if (empty($inadimplentes)): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-12 text-center">
    <i class="fa-solid fa-circle-check text-4xl text-gray-300 mb-4 block"></i>
    <p class="font-semibold text-gray-700 text-lg">Nenhuma inadimplência no período selecionado.</p>
    <p class="text-sm text-gray-500 mt-1">Todos os alunos estão em dia com os pagamentos.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsável</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Parcelas vencidas</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total devido</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último vencimento</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Contato</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($inadimplentes as $row): ?>
            <tr class="hover:bg-red-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <a href="<?= URL ?>/admin/alunos/<?= (int)$row['aluno_id'] ?>"
                       class="font-medium text-gray-900 hover:text-blue-700 transition-colors">
                        <?= $esc($row['aluno_nome']) ?>
                    </a>
                    <p class="text-xs text-gray-400 mt-0.5">RA: <?= $esc($row['ra'] ?? '—') ?></p>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= $esc($row['responsavel_nome'] ?? '—') ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                    <button type="button"
                            onclick="verParcelas(<?= (int)$row['aluno_id'] ?>, '<?= addslashes($esc($row['aluno_nome'])) ?>')"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-red-100 text-red-700 text-xs font-bold hover:bg-red-200 transition-colors cursor-pointer"
                            title="Ver parcelas vencidas">
                        <?= (int)$row['qtd_vencidas'] ?>
                    </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-red-700">
                    <?= $brl($row['total_devido']) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= $row['ultimo_vencimento'] ? date('d/m/Y', strtotime($row['ultimo_vencimento'])) : '—' ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                    <div class="flex items-center justify-center gap-3">
                        <?php if ($row['responsavel_email']): ?>
                        <a href="mailto:<?= $esc($row['responsavel_email']) ?>"
                           title="Enviar e-mail"
                           class="text-gray-400 hover:text-blue-600 transition-colors">
                            <i class="fa-solid fa-envelope"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($row['responsavel_telefone']): ?>
                        <a href="https://wa.me/<?= preg_replace('/\D/', '', $row['responsavel_telefone']) ?>"
                           target="_blank"
                           title="WhatsApp"
                           class="text-gray-400 hover:text-green-600 transition-colors">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="3" class="px-6 py-3 text-sm font-semibold text-gray-700">Total</td>
                    <td class="px-6 py-3 text-right font-bold text-red-700">
                        <?= $brl(array_sum(array_column($inadimplentes, 'total_devido'))) ?>
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal parcelas vencidas -->
<dialog id="modalParcelas" class="rounded-2xl shadow-2xl border border-gray-200 p-0 w-full max-w-lg backdrop:bg-black/50">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div>
            <h3 class="text-base font-bold text-gray-900">Parcelas Vencidas</h3>
            <p class="text-sm text-gray-500" id="modalParcelasAluno"></p>
        </div>
        <button onclick="document.getElementById('modalParcelas').close()"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div id="modalParcelasBody" class="px-6 py-4 max-h-[60vh] overflow-y-auto">
        <div class="flex items-center justify-center py-10 text-gray-400">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-green-500"></i>
        </div>
    </div>
    <div class="px-6 py-3 border-t border-gray-100 flex gap-3">
        <a id="modalParcelasExtrato" href="#"
           class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-receipt mr-2"></i> Extrato Completo
        </a>
        <button onclick="document.getElementById('modalParcelas').close()"
                class="flex-1 px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            Fechar
        </button>
    </div>
</dialog>

<script>
function verParcelas(alunoId, alunoNome) {
    const modal = document.getElementById('modalParcelas');
    const body  = document.getElementById('modalParcelasBody');
    document.getElementById('modalParcelasAluno').textContent = alunoNome;
    document.getElementById('modalParcelasExtrato').href = '<?= URL ?>/admin/finance/aluno/' + alunoId + '/extrato';

    body.innerHTML = '<div class="flex items-center justify-center py-10 text-gray-400"><i class="fa-solid fa-spinner fa-spin text-2xl text-green-500"></i></div>';
    modal.showModal();

    fetch('<?= URL ?>/admin/finance/aluno/' + alunoId + '/parcelas-vencidas')
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                body.innerHTML = '<p class="text-center text-gray-400 py-8">Nenhuma parcela vencida encontrada.</p>';
                return;
            }
            const brl = v => 'R$ ' + Number(v||0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            const dt  = s => s ? s.split('-').reverse().join('/') : '—';
            const diasVencido = s => {
                const diff = Math.floor((Date.now() - new Date(s + 'T00:00:00')) / 86400000);
                return diff > 0 ? diff + 'd em atraso' : 'Hoje';
            };

            let total = 0;
            let rows = data.map(p => {
                total += Number(p.valor_total || 0);
                const dias = diasVencido(p.data_vencimento);
                return `<div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900">${p.descricao || 'Parcela ' + p.num_parcela}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Venceu em ${dt(p.data_vencimento)} <span class="text-red-500 font-medium ml-1">${dias}</span></p>
                    </div>
                    <p class="text-sm font-bold text-red-600 ml-4">${brl(p.valor_total)}</p>
                </div>`;
            }).join('');

            rows += `<div class="flex items-center justify-between pt-3 mt-1 bg-red-50 -mx-6 px-6">
                <p class="text-sm font-semibold text-gray-700">Total em atraso</p>
                <p class="text-base font-bold text-red-700">${brl(total)}</p>
            </div>`;

            body.innerHTML = rows;
        })
        .catch(() => {
            body.innerHTML = '<p class="text-center text-gray-400 py-8">Erro ao carregar parcelas.</p>';
        });
}
</script>
