<?php
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$campanha = $campanha ?? [];
$mapa_planos = $mapa_planos ?? [];
$processos = $processos ?? [];
$planos_origem = $planos_origem ?? [];
$planos_destino = $planos_destino ?? [];
$series = $series ?? [];
$statusLabels = ['rascunho' => 'Rascunho', 'aberta' => 'Aberta', 'encerrada' => 'Encerrada'];
$procStatus = [
    'rascunho' => 'Rascunho',
    'aguardando_contrato' => 'Aguard. contrato',
    'aguardando_assinatura' => 'Aguard. assinatura',
    'confirmada' => 'Confirmada',
    'enturmada' => 'Enturmada',
    'lista_espera' => 'Fila',
    'cancelada' => 'Cancelada',
    'abandonada' => 'Abandonada',
];
$id = (int) ($campanha['id'] ?? 0);
$inicio = substr((string) ($campanha['inicio'] ?? ''), 0, 10);
$fim = substr((string) ($campanha['fim'] ?? ''), 0, 10);

$page_header_title = $campanha['nome'] ?? 'Campanha';
$page_header_subtitle = ($campanha['ano_origem'] ?? '—') . ' → ' . ($campanha['ano_destino'] ?? '—');
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment/campanhas" class="btn-secondary text-sm">← Campanhas</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?= ($campanha['status'] ?? '') === 'aberta' ? 'bg-green-100 text-green-800' : (($campanha['status'] ?? '') === 'encerrada' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700') ?>">
        <?= $esc($statusLabels[$campanha['status'] ?? ''] ?? ($campanha['status'] ?? '')) ?>
    </span>
    <?php if (($campanha['status'] ?? '') === 'rascunho'): ?>
    <form method="POST" action="<?= URL ?>/admin/enrollment/campanhas/<?= $id ?>/status">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <input type="hidden" name="status" value="aberta">
        <button type="submit" class="btn-primary text-sm">Abrir campanha</button>
    </form>
    <?php elseif (($campanha['status'] ?? '') === 'aberta'): ?>
    <form method="POST" action="<?= URL ?>/admin/enrollment/campanhas/<?= $id ?>/status" onsubmit="return confirm('Encerrar esta campanha?')">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <input type="hidden" name="status" value="encerrada">
        <button type="submit" class="btn-secondary text-sm">Encerrar</button>
    </form>
    <?php endif; ?>
    <?php if (($campanha['status'] ?? '') !== 'encerrada'): ?>
    <form method="POST" action="<?= URL ?>/admin/enrollment/campanhas/<?= $id ?>/gerar"
          onsubmit="return confirm('Gerar processos de rematrícula para os alunos do ano de origem?')">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <button type="submit" class="btn-secondary text-sm">
            <i class="fa-solid fa-wand-magic-sparkles mr-1.5"></i> Gerar processos
        </button>
    </form>
    <?php endif; ?>
    <a href="<?= URL ?>/admin/finance/plans" class="text-sm text-primary hover:underline">Clonar planos no financeiro</a>
</div>

<form method="POST" action="<?= URL ?>/admin/enrollment/campanhas/<?= $id ?>" class="mb-6">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
    <div class="bg-white rounded-xl shadow-lg p-6 space-y-5 w-full">
        <h3 class="font-semibold text-gray-800">Dados da campanha</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" name="nome" required maxlength="160" value="<?= $esc($campanha['nome'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Início</label>
                <input type="date" name="inicio" required value="<?= $esc($inicio) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fim</label>
                <input type="date" name="fim" required value="<?= $esc($fim) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reajuste de referência (%)</label>
                <input type="number" step="0.01" name="reajuste_pct" value="<?= $esc($campanha['reajuste_pct'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Plano padrão (destino)</label>
                <select name="plano_padrao_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">Nenhum</option>
                    <?php foreach ($planos_destino as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($campanha['plano_padrao_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= $esc($p['nome'] ?? ('Plano #' . $p['id'])) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="space-y-3">
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="fila_auto_oferecer" value="1" class="rounded border-gray-300 text-primary"
                       <?= !empty($campanha['fila_auto_oferecer']) ? 'checked' : '' ?>>
                Oferecer vaga automaticamente ao primeiro da fila
            </label>
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="exige_censo" value="1" class="rounded border-gray-300 text-primary"
                       <?= !empty($campanha['exige_censo']) ? 'checked' : '' ?>>
                Exigir dados do Censo no portal da família
            </label>
        </div>
        <button type="submit" class="btn-primary text-sm">Salvar alterações</button>
    </div>
</form>

<form method="POST" action="<?= URL ?>/admin/enrollment/campanhas/<?= $id ?>/mapa" class="mb-6">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
    <div class="bg-white rounded-xl shadow-lg p-6 space-y-4 w-full">
        <div>
            <h3 class="font-semibold text-gray-800">Mapa de preços</h3>
            <p class="text-sm text-gray-500 mt-1">Plano atual → plano do ano novo. Sem origem, vale para a série. Sem linha, usa o plano padrão.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Plano origem</th>
                        <th class="px-3 py-2 text-left">Série</th>
                        <th class="px-3 py-2 text-left">Plano destino</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $linhas = $mapa_planos;
                    for ($i = 0; $i < 3; $i++) {
                        $linhas[] = [];
                    }
                    foreach ($linhas as $i => $linha):
                    ?>
                    <tr>
                        <td class="px-3 py-2">
                            <select name="mapa[<?= (int) $i ?>][plano_origem_id]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                                <option value="">Qualquer</option>
                                <?php foreach ($planos_origem as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= (int) ($linha['plano_origem_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= $esc($p['nome'] ?? ('#' . $p['id'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <select name="mapa[<?= (int) $i ?>][serie_id]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                                <option value="">Qualquer</option>
                                <?php foreach ($series as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (int) ($linha['serie_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= $esc($s['nome'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <select name="mapa[<?= (int) $i ?>][plano_destino_id]" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                                <option value="">—</option>
                                <?php foreach ($planos_destino as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= (int) ($linha['plano_destino_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= $esc($p['nome'] ?? ('#' . $p['id'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn-primary text-sm">Salvar mapa</button>
    </div>
</form>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="font-semibold text-gray-800">Processos (<?= count($processos) ?>)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plano</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($processos === []): ?>
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Nenhum processo gerado.</td></tr>
                <?php else: ?>
                <?php foreach ($processos as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><?= $esc($p['aluno_nome'] ?? '') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= $esc($p['turma_nome'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= $esc($procStatus[$p['status'] ?? ''] ?? ($p['status'] ?? '')) ?></td>
                    <td class="px-4 py-3">
                        <form method="POST" action="<?= URL ?>/admin/enrollment/campanhas/<?= $id ?>/aplicar-plano" class="flex gap-2 items-center">
                            <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
                            <input type="hidden" name="enrollment_id" value="<?= (int) $p['id'] ?>">
                            <select name="plano_id" class="border border-gray-300 rounded-lg px-2 py-1.5 text-xs">
                                <option value="">Plano…</option>
                                <?php foreach ($planos_destino as $pl): ?>
                                <option value="<?= (int) $pl['id'] ?>" <?= (int) ($p['finance_plan_id'] ?? 0) === (int) $pl['id'] ? 'selected' : '' ?>>
                                    <?= $esc($pl['nome'] ?? ('#' . $pl['id'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="text-xs text-primary hover:underline">Aplicar</button>
                        </form>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= URL ?>/admin/enrollment/<?= (int) $p['id'] ?>" class="text-primary hover:underline">Abrir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
