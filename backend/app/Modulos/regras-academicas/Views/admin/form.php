<?php
require_once __DIR__ . '/../../Models/RegraAcademica.php';

use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

$item = is_array($item ?? null) ? $item : null;
$isEdit = $item !== null;
$historico = is_array($historico ?? null) ? $historico : [];
$cursos = is_array($cursos ?? null) ? $cursos : [];
$series = is_array($series ?? null) ? $series : [];
$anosLetivos = is_array($anos_letivos ?? null) ? $anos_letivos : [];
$matrizes = is_array($matrizes ?? null) ? $matrizes : [];
$componentes = is_array($componentes ?? null) ? $componentes : [];
$csrf_token = $csrf_token ?? '';
$action = $isEdit
    ? URL . '/admin/regras-academicas/' . (int) $item['id'] . '/update'
    : URL . '/admin/regras-academicas';
$val = static function (?array $item, string $key, $default = '') {
    if (!$item) {
        return $default;
    }
    return $item[$key] ?? $default;
};
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= $isEdit ? 'Editar regra acadêmica' : 'Nova regra acadêmica' ?></h2>
            <p class="text-gray-600">Define como a escola aprova, recupera e exige frequência. Salvar gera uma nova versão — anos anteriores não mudam.</p>
        </div>
        <a href="<?= URL ?>/admin/regras-academicas" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="<?= htmlspecialchars($action) ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-6">Identificação e vigência</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
                <input type="text" name="nome" required maxlength="150" value="<?= htmlspecialchars((string) $val($item, 'nome')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex.: Ensino Médio 2026">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Código</label>
                <input type="text" name="codigo" maxlength="120" value="<?= htmlspecialchars((string) $val($item, 'codigo')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="em-2026">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Ano letivo</label>
                <select name="ano_letivo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Qualquer ano</option>
                    <?php foreach ($anosLetivos as $ano): ?>
                        <?php $ano = (int) $ano; ?>
                        <option value="<?= $ano ?>" <?= (int) $val($item, 'ano_letivo', 0) === $ano ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Deixe em branco para valer em todos os anos (não recomendado).</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Curso</label>
                <select name="curso_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Todos os cursos</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $val($item, 'curso_id', 0) === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Série</label>
                <select name="serie_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Todas as séries</option>
                    <?php foreach ($series as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $val($item, 'serie_id', 0) === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $s['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Matriz curricular</label>
                <select name="matriz_curricular_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Qualquer matriz</option>
                    <?php foreach ($matrizes as $mz): ?>
                        <option value="<?= (int) $mz['id'] ?>" <?= (int) $val($item, 'matriz_curricular_id', 0) === (int) $mz['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $mz['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Componente (exceção)</label>
                <select name="materia_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Todos os componentes</option>
                    <?php foreach ($componentes as $comp): ?>
                        <option value="<?= (int) $comp['id'] ?>" <?= (int) $val($item, 'materia_id', 0) === (int) $comp['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $comp['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Preencha só se esta regra for exceção de um componente.</p>
            </div>
            <div class="flex items-end pb-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="ativo" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !$isEdit || !empty($item['ativo']) ? 'checked' : '' ?>>
                    Regra ativa
                </label>
            </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-6">Período e fórmulas</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de período</label>
                <select name="periodo_tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach (RegraAcademica::PERIODO_TIPOS as $k => $lab): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= (string) $val($item, 'periodo_tipo', 'bimestre') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Número do período</label>
                <select name="periodo_numero" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Ano todo</option>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?= $i ?>" <?= (int) $val($item, 'periodo_numero', 0) === $i ? 'selected' : '' ?>><?= $i ?>º</option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Fórmula da média do período</label>
                <input type="text" name="formula_media" value="<?= htmlspecialchars((string) $val($item, 'formula_media')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex.: (B1 + B2 + B3 + B4) / 4">
                <p class="text-xs text-gray-500 mt-1">Ilustrativa / documentação. A composição das notas do evento continua no boletim.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Fórmula final (com recuperação)</label>
                <input type="text" name="formula_final" value="<?= htmlspecialchars((string) $val($item, 'formula_final')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex.: max(media, rec) ou (media + rec) / 2">
                <p class="text-xs text-gray-500 mt-1">Usada quando a composição da recuperação for “fórmula própria”.</p>
            </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-6">Critérios</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Média mínima</label>
                <input type="number" name="media_minima" min="0" max="10" step="0.01"
                       value="<?= htmlspecialchars(number_format((float) $val($item, 'media_minima', 6), 2, '.', '')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Frequência mínima (%)</label>
                <div class="flex items-center gap-3">
                    <input type="number" name="frequencia_minima" min="0" max="100" step="0.1"
                           value="<?= htmlspecialchars(number_format((float) $val($item, 'frequencia_minima', 75), 1, '.', '')) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-2">
                    <input type="checkbox" name="usar_frequencia" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !empty($item['usar_frequencia']) ? 'checked' : '' ?>>
                    Exigir frequência mínima na aprovação
                </label>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Arredondamento</label>
                <select name="round_mode" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="none" <?= (string) $val($item, 'round_mode', 'none') === 'none' ? 'selected' : '' ?>>Sem arredondamento especial (2 casas)</option>
                    <option value="half" <?= (string) $val($item, 'round_mode', 'none') === 'half' ? 'selected' : '' ?>>Faixa .00 / .50 / próximo inteiro</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Casas decimais</label>
                <select name="decimal_places" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="2" <?= (int) $val($item, 'decimal_places', 2) === 2 ? 'selected' : '' ?>>2 casas</option>
                    <option value="1" <?= (int) $val($item, 'decimal_places', 2) === 1 ? 'selected' : '' ?>>1 casa</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de recuperação</label>
                <select name="recuperacao_tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach (RegraAcademica::RECUPERACAO_TIPOS as $k => $lab): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= (string) $val($item, 'recuperacao_tipo', 'periodo') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Como a recuperação entra na média</label>
                <select name="recuperacao_composicao" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach (RegraAcademica::RECUPERACAO_COMPOSICOES as $k => $lab): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= (string) $val($item, 'recuperacao_composicao', 'maior_nota') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mín. de avaliações</label>
                <input type="number" name="min_avaliacoes" min="1" value="<?= htmlspecialchars((string) $val($item, 'min_avaliacoes')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Máx. de avaliações</label>
                <input type="number" name="max_avaliacoes" min="1" value="<?= htmlspecialchars((string) $val($item, 'max_avaliacoes')) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="componentes_sem_nota" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !empty($item['componentes_sem_nota']) ? 'checked' : '' ?>>
                Permitir componente sem nota (não avaliado)
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="aprovacao_so_frequencia" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !empty($item['aprovacao_so_frequencia']) ? 'checked' : '' ?>>
                Aprovar somente por frequência (sem nota)
            </label>
        </div>
        <div class="mb-8">
            <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
            <textarea name="observacoes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= htmlspecialchars((string) $val($item, 'observacoes')) ?></textarea>
        </div>

        <?php if ($isEdit && $historico !== []): ?>
        <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Histórico de versões</h3>
        <div class="overflow-x-auto mb-8 border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Versão</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Quando</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Responsável</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($historico as $h): ?>
                    <tr>
                        <td class="px-4 py-2">v<?= (int) $h['versao'] ?></td>
                        <td class="px-4 py-2 text-gray-600"><?= !empty($h['created_at']) ? date('d/m/Y H:i', strtotime((string) $h['created_at'])) : '—' ?></td>
                        <td class="px-4 py-2 text-gray-600"><?= htmlspecialchars((string) ($h['usuario_nome'] ?? '—')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/regras-academicas" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 shadow-sm">
                <?= $isEdit ? 'Salvar nova versão' : 'Cadastrar regra' ?>
            </button>
        </div>
    </form>
</div>
