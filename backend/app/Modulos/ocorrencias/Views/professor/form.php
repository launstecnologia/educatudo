<?php
require_once __DIR__ . '/../../Models/Ocorrencia.php';

use App\Modulos\Ocorrencias\Models\Ocorrencia;

$aula = is_array($aula ?? null) ? $aula : [];
$aluno = is_array($aluno ?? null) ? $aluno : [];
$categorias = is_array($categorias ?? null) ? $categorias : [];
$schemaEstendido = !empty($schema_estendido);
$voltar_url = (string) ($voltar_url ?? '/professor/diario');
$dataPadrao = date('Y-m-d\TH:i');
if (!empty($aula['data_aula'])) {
    $hora = substr((string) ($aula['horario_de'] ?? '08:00:00'), 0, 5);
    $dataPadrao = $aula['data_aula'] . 'T' . $hora;
}
$origem = (string) ($_GET['origem'] ?? '');
?>
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <a href="<?= htmlspecialchars($voltar_url) ?>" class="text-purple-700 hover:underline text-sm">← Voltar à chamada</a>
        <h1 class="text-3xl font-bold text-gray-900 mt-2">Registrar ocorrência</h1>
        <p class="text-gray-600 mt-1">O fato fica no cadastro do aluno. Esta aula só fica vinculada como origem.</p>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash_status ?? '') === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
            <?= htmlspecialchars((string) $flash_message) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= URL ?>/professor/ocorrencias" class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
        <input type="hidden" name="diario_aula_id" value="<?= (int) ($aula['id'] ?? 0) ?>">
        <input type="hidden" name="aluno_id" value="<?= (int) ($aluno['id'] ?? 0) ?>">
        <?php if ($origem === 'diario'): ?>
            <input type="hidden" name="origem" value="diario">
        <?php endif; ?>

        <p class="text-sm text-gray-700 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3">
            <strong><?= htmlspecialchars((string) ($aluno['nome'] ?? '')) ?></strong>
            · <?= htmlspecialchars((string) ($aula['turma_nome'] ?? '')) ?>
            · <?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?>
            · <?= !empty($aula['data_aula']) ? date('d/m/Y', strtotime((string) $aula['data_aula'])) : '' ?>
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data e hora</label>
                <input type="datetime-local" name="data_ocorrencia" required value="<?= htmlspecialchars($dataPadrao) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <?php if ($schemaEstendido): ?>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Categoria</label>
                <select name="categoria_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Selecione</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars((string) $cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Título</label>
            <input type="text" name="titulo" required maxlength="120" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
            <textarea name="detalhe" rows="4" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Descreva o fato. A observação da chamada continua sendo só frequência."></textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Gravidade</label>
                <select name="nivel_gravidade" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Selecione</option>
                    <?php foreach (Ocorrencia::GRAVIDADES as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Local</label>
                <input type="text" name="local" maxlength="120" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Encaminhamento (opcional)</label>
            <textarea name="encaminhamento" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Sugestão para a coordenação. Não gera advertência automática."></textarea>
        </div>
        <p class="text-xs text-gray-500">Este registro começa interno. A coordenação decide se o responsável vê no portal.</p>

        <div class="flex justify-end gap-3">
            <a href="<?= htmlspecialchars($voltar_url) ?>" class="px-5 py-2.5 border border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700">Salvar ocorrência</button>
        </div>
    </form>
</div>
