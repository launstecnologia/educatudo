<?php
$edicao = $edicao ?? null;
$pode_gerenciar = $pode_gerenciar ?? false;
$config = $edicao['config_decoded'] ?? [];
?>
<div class="max-w-5xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Expo Colag</h2>
            <p class="text-sm text-gray-600 mt-1">Painel da feira de projetos — coordenação.</p>
        </div>
        <?php if ($pode_gerenciar): ?>
        <div class="flex flex-wrap gap-2">
            <a href="<?= URL ?>/admin/expo-colag/programacao"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-calendar-days"></i>
                Programação / stands
            </a>
            <a href="<?= URL ?>/admin/expo-colag/autorizacoes"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-camera"></i>
                Autorização de imagem
            </a>
            <a href="<?= URL ?>/admin/expo-colag/configuracao"
               class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium">
                <i class="fa-solid fa-sliders"></i>
                Configuração da edição
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $authC = $autorizacao_contagens ?? [];
    if ($authC):
    ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Autorização de imagem:
        <?= (int) ($authC['Autorizado_total'] ?? 0) ?> total ·
        <?= (int) ($authC['Autorizado_interno'] ?? 0) ?> interno ·
        <?= (int) ($authC['Nao_autorizado'] ?? 0) ?> pendente.
        <a href="<?= URL ?>/admin/expo-colag/autorizacoes" class="font-semibold underline ml-1">Gerenciar</a>
    </div>
    <?php endif; ?>

    <?php if (!$edicao): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
            Não foi possível carregar a edição. Verifique se a migration foi aplicada neste tenant.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Edição</p>
                <p class="text-lg font-semibold text-gray-900 mt-1"><?= htmlspecialchars($edicao['nome'] ?? 'Expo Colag') ?></p>
                <p class="text-sm text-gray-600"><?= htmlspecialchars($edicao['edicao'] ?? '') ?></p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Status</p>
                <p class="mt-2">
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                        <?= htmlspecialchars(str_replace('_', ' ', $edicao['status'] ?? '')) ?>
                    </span>
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Data do evento</p>
                <p class="text-lg font-semibold text-gray-900 mt-1">
                    <?= !empty($edicao['data_evento']) ? htmlspecialchars(date('d/m/Y', strtotime($edicao['data_evento']))) : '—' ?>
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Parâmetros principais</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500">Inscrições</dt>
                    <dd class="text-gray-900">
                        <?= htmlspecialchars(($config['inscricoes_inicio'] ?? '—') . ' → ' . ($config['inscricoes_fim'] ?? '—')) ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Grupo (min / max)</dt>
                    <dd class="text-gray-900"><?= (int) ($config['grupo_min'] ?? 3) ?> / <?= (int) ($config['grupo_max'] ?? 5) ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500">Projetos por aluno</dt>
                    <dd class="text-gray-900"><?= (int) ($config['max_projetos_aluno'] ?? 1) ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500">Limite pedido de material</dt>
                    <dd class="text-gray-900"><?= htmlspecialchars($config['limite_solicitacao_recursos'] ?? '—') ?></dd>
                </div>
            </dl>
        </div>
    <?php endif; ?>
</div>
