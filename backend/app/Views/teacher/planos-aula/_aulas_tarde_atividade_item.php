<?php
/** @var int|string $index */
/** @var array<string, mixed> $atividade */
/** @var string $tipo */
$isFirst = ((string) $index === '0' || $index === 0);
$pagina = (string) ($atividade['pagina'] ?? '');
$apostila = (string) ($atividade['apostila'] ?? '');
$exercicios = (string) ($atividade['exercicios'] ?? '');
$jornadaNome = (string) ($atividade['jornada_nome'] ?? '');
$descricao = (string) ($atividade['descricao'] ?? '');
$showExercicios = $tipo === LessonPlanAfternoonHelper::TIPO_EXERCICIOS;
$showJornadas = $tipo === LessonPlanAfternoonHelper::TIPO_JORNADAS;
$showOutros = $tipo === LessonPlanAfternoonHelper::TIPO_OUTROS;
?>
<div class="aulas-tarde-atividade-item border border-gray-200 rounded-xl p-4 bg-gray-50/40 space-y-4">
    <div class="flex items-center justify-between gap-2">
        <p class="aulas-tarde-atividade-titulo text-sm font-semibold text-gray-800">Atividade <?= is_numeric($index) ? ((int) $index + 1) : '' ?></p>
        <button type="button"
                onclick="removerAulasTardeAtividade(this)"
                class="aulas-tarde-remover-btn text-sm text-red-600 hover:text-red-700 font-medium <?= $isFirst ? 'hidden' : '' ?>">
            Remover
        </button>
    </div>

    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">
            Tipo de atividade <span class="text-red-500">*</span>
        </label>
        <select class="aulas-tarde-tipo w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                onchange="toggleAulasTardeCamposItem(this)">
            <option value="">Selecione...</option>
            <?php foreach ($aulasTardeTipos as $tipoValor => $tipoLabel): ?>
                <option value="<?= htmlspecialchars($tipoValor) ?>" <?= $tipo === $tipoValor ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tipoLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="aulas-tarde-campos-exercicios space-y-4 <?= $showExercicios ? '' : 'hidden' ?>">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Página <span class="text-red-500">*</span></label>
            <input type="text" class="aulas-tarde-pagina w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                   value="<?= htmlspecialchars($pagina) ?>" placeholder="Ex: 45 a 50" data-aulas-tarde-required>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Apostila <span class="text-red-500">*</span></label>
            <input type="text" class="aulas-tarde-apostila w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                   value="<?= htmlspecialchars($apostila) ?>" placeholder="Ex: Volume 2" data-aulas-tarde-required>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Quais exercícios <span class="text-red-500">*</span></label>
            <textarea class="aulas-tarde-exercicios w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white" rows="3"
                      placeholder="Ex: Exercícios 1 ao 8" data-aulas-tarde-required><?= htmlspecialchars($exercicios) ?></textarea>
        </div>
    </div>

    <div class="aulas-tarde-campos-jornadas <?= $showJornadas ? '' : 'hidden' ?>">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Nome da jornada <span class="text-red-500">*</span></label>
        <input type="text" class="aulas-tarde-jornada-nome w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
               value="<?= htmlspecialchars($jornadaNome) ?>" placeholder="Ex: Jornada de revisão de matemática" data-aulas-tarde-required>
    </div>

    <div class="aulas-tarde-campos-outros <?= $showOutros ? '' : 'hidden' ?>">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição <span class="text-red-500">*</span></label>
        <textarea class="aulas-tarde-descricao w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white" rows="4"
                  placeholder="Descreva a atividade" data-aulas-tarde-required><?= htmlspecialchars($descricao) ?></textarea>
    </div>
</div>
