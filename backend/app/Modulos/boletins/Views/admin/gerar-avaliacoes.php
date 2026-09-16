<?php
$boletim = is_array($boletim ?? null) ? $boletim : [];
$periodos = is_array($periodos ?? null) ? $periodos : [];
$eventosModelo = is_array($eventos_modelo ?? null) ? $eventos_modelo : [];
$modeloRegraId = (int) ($modelo_regra_id ?? 0);
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$usouCalendario = !empty($usou_calendario);
$csrf_token = $csrf_token ?? '';
$boletimId = (int) ($boletim['id'] ?? 0);

$page_header_title = 'Gerar avaliações do ano';
$page_header_subtitle = 'Cria os bimestres a partir do calendário letivo (tipo Avaliação) ou de 4 períodos padrão, clipados no ano letivo.';
$page_header_back_url = URL . '/admin/boletins';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <div class="mb-6">
        <p class="text-sm text-gray-700">
            Modelo: <strong><?= htmlspecialchars((string) ($boletim['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            · Ano <?= $anoLetivo ?>
            · <?= $usouCalendario ? 'Datas do calendário letivo' : 'Períodos padrão (4 bimestres)' ?>
        </p>
        <p class="text-xs text-gray-500 mt-1">Bimestres que já têm avaliação neste modelo são ignorados.</p>
    </div>

    <?php if ($eventosModelo === []): ?>
        <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm mb-6">
            Cadastre a primeira avaliação deste modelo em
            <a href="<?= URL ?>/admin/boletim-configuracao?novo=1" class="underline">Avaliações</a>
            para usá-la como modelo da duplicação.
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto mb-6">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Início</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fim</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origem</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($periodos === []): ?>
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">Nenhum período calculado.</td>
                </tr>
                <?php else: foreach ($periodos as $p): ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['fim'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= (($p['origem'] ?? '') === 'calendario') ? 'Calendário' : 'Padrão' ?></td>
                    <td class="px-4 py-3">
                        <?php if (!empty($p['ja_existe'])): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Já existe</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Será criado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <form method="POST" action="<?= URL ?>/admin/boletins/<?= $boletimId ?>/gerar-avaliacoes" class="space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Evento modelo (duplicar estrutura)</label>
            <select name="modelo_regra_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" <?= $eventosModelo === [] ? 'disabled' : '' ?>>
                <?php foreach ($eventosModelo as $ev): ?>
                    <option value="<?= (int) ($ev['id'] ?? 0) ?>" <?= $modeloRegraId === (int) ($ev['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($ev['nome'] ?? ('#' . (int) ($ev['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($ev['bimestre'])): ?> — <?= (int) $ev['bimestre'] ?>º bim.<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90" <?= $eventosModelo === [] ? 'disabled' : '' ?>>
                Gerar eventos faltantes
            </button>
            <a href="<?= URL ?>/admin/boletins" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
</div>
