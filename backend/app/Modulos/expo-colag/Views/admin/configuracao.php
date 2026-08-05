<?php
$edicao = $edicao ?? [];
$config = $config ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="max-w-3xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="<?= URL ?>/admin/expo-colag" class="text-sm text-primary hover:underline">← Expo Colag</a>
            <h2 class="text-2xl font-bold text-gray-900 mt-1">Configuração da edição</h2>
            <p class="text-sm text-gray-600 mt-1">Parâmetros pedagógicos sem necessidade de deploy.</p>
        </div>
    </div>

    <form method="post" action="<?= URL ?>/admin/expo-colag/configuracao" class="rounded-xl border border-gray-200 bg-white p-6 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="edicao_id" value="<?= (int) ($edicao['id'] ?? 0) ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($edicao['nome'] ?? 'Expo Colag') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Edição</label>
                <input type="text" name="edicao" value="<?= htmlspecialchars($edicao['edicao'] ?? '2026') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data do evento</label>
                <input type="date" name="data_evento" value="<?= htmlspecialchars($edicao['data_evento'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Local</label>
                <input type="text" name="local" value="<?= htmlspecialchars($edicao['local'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tema</label>
            <input type="text" name="tema" value="<?= htmlspecialchars($edicao['tema'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
        </div>

        <hr class="border-gray-100">

        <h3 class="text-sm font-semibold text-gray-900">Participação e prazos</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Grupo mínimo</label>
                <input type="number" name="grupo_min" min="1" value="<?= (int) ($config['grupo_min'] ?? 3) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Grupo máximo</label>
                <input type="number" name="grupo_max" min="1" value="<?= (int) ($config['grupo_max'] ?? 5) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Máx. projetos / aluno</label>
                <input type="number" name="max_projetos_aluno" min="1" value="<?= (int) ($config['max_projetos_aluno'] ?? 1) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Máx. projetos / professor</label>
                <input type="number" name="max_projetos_professor" min="1" value="<?= (int) ($config['max_projetos_professor'] ?? 3) ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Inscrições — início</label>
                <input type="date" name="inscricoes_inicio" value="<?= htmlspecialchars($config['inscricoes_inicio'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Inscrições — fim</label>
                <input type="date" name="inscricoes_fim" value="<?= htmlspecialchars($config['inscricoes_fim'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Limite pedido de material</label>
                <input type="date" name="limite_solicitacao_recursos" value="<?= htmlspecialchars($config['limite_solicitacao_recursos'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fluxo aprovação de recurso</label>
                <select name="fluxo_aprovacao_recurso" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    <?php
                    $fluxo = $config['fluxo_aprovacao_recurso'] ?? 'professor_coordenacao';
                    $opcoesFluxo = [
                        'professor' => 'Só professor',
                        'professor_coordenacao' => 'Professor → coordenação',
                        'professor_coordenacao_financeiro' => 'Professor → coordenação → financeiro',
                    ];
                    foreach ($opcoesFluxo as $val => $label):
                    ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $fluxo === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="permite_individual" value="1" <?= !empty($config['permite_individual']) ? 'checked' : '' ?>>
                Permitir projeto individual
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="cross_turma" value="1" <?= !empty($config['cross_turma']) ? 'checked' : '' ?>>
                Aluno pode entrar em projeto de outra turma
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="vale_nota" value="1" <?= !empty($config['vale_nota']) ? 'checked' : '' ?>>
                Vale nota
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="voto_publico_ativo" value="1" <?= !empty($config['voto_publico_ativo']) || !empty($edicao['voto_publico_ativo']) ? 'checked' : '' ?>>
                Voto do público
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="checkin_ativo" value="1" <?= !empty($config['checkin_ativo']) || !empty($edicao['checkin_ativo']) ? 'checked' : '' ?>>
                Check-in de responsáveis
            </label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary-custom px-4 py-2.5 rounded-lg text-sm font-medium">Salvar</button>
            <a href="<?= URL ?>/admin/expo-colag" class="px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200">Cancelar</a>
        </div>
    </form>
</div>
