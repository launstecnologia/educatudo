<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$contexto = is_array($contexto ?? null) ? $contexto : [];
$unidades = $contexto['unidades'] ?? [];
$anos = $contexto['anos'] ?? [(int) date('Y')];
$etapas = $contexto['etapas'] ?? [];
$statusLabels = $contexto['status_labels'] ?? [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtData = static function ($valor, string $formato = 'd/m/Y'): string {
    $valor = trim((string) $valor);
    if ($valor === '' || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($valor);
    return $ts ? date($formato, $ts) : $valor;
};
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <form method="POST" action="<?= URL ?>/admin/censo/edicao" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Unidade</label>
            <select name="unidade_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="0">Toda a escola</option>
                <?php foreach ($unidades as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) ($edicao['unidade_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                        <?= $esc($u['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Edição (ano)</label>
            <select name="ano" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <?php foreach ($anos as $ano): ?>
                    <option value="<?= (int) $ano ?>" <?= (int) ($edicao['ano'] ?? date('Y')) === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Etapa da coleta</label>
            <select name="etapa_coleta" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <?php foreach ($etapas as $k => $label): ?>
                    <option value="<?= $esc($k) ?>" <?= ($edicao['etapa_coleta'] ?? '') === $k ? 'selected' : '' ?>><?= $esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary-custom w-full px-4 py-2 rounded-lg text-sm font-semibold">Abrir edição</button>
        </div>
    </form>
    <?php if ($edicao): ?>
    <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm text-gray-600">
        <div>Referência: <strong><?= $esc($fmtData($edicao['data_referencia'] ?? '')) ?></strong></div>
        <div>Leiaute: <strong><?= $esc($edicao['versao_layout'] ?? 'pendente') ?></strong></div>
        <div>Situação: <strong><?= $esc($statusLabels[$edicao['status'] ?? ''] ?? ($edicao['status'] ?? '—')) ?></strong></div>
        <div>Última validação: <strong><?= $esc($fmtData($edicao['ultima_validacao_em'] ?? '', 'd/m/Y H:i')) ?></strong></div>
    </div>
    <?php endif; ?>
</div>
