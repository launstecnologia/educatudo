<?php
$plano = $plano ?? null;
$id = (int) ($plano['id'] ?? 0);
$habIds = $habilidade_ids ?? [];
$habilidades = $habilidades ?? [];
$habPlano = $habilidades_plano ?? [];
$val = static fn(string $k, $d = '') => htmlspecialchars((string) ($plano[$k] ?? $d));
?>
<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/plano-curso"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors" aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= $id > 0 ? 'Editar' : 'Novo' ?> Plano de Curso</h2>
            <p class="text-sm text-gray-600">Defina conteúdo previsto, carga horária e habilidades da BNCC para a série/matéria.</p>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden w-full">
    <form method="POST" action="<?= URL ?>/admin/plano-curso/salvar" class="divide-y divide-gray-200">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Identificação</h3>
                <p class="mt-1 text-sm text-gray-500">Série, matéria e parâmetros do plano.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-2">Matéria <span class="text-red-500">*</span></label>
                    <select id="materia_id" name="materia_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Selecione...</option>
                        <?php foreach (($materias ?? []) as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (int) ($plano['materia_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="serie_id" class="block text-sm font-medium text-gray-700 mb-2">Série</label>
                    <select id="serie_id" name="serie_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Todas / não especificada</option>
                        <?php foreach (($series ?? []) as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) ($plano['serie_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $s['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-2">Ano letivo</label>
                    <select id="ano_letivo_id" name="ano_letivo_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Não especificado</option>
                        <?php
                        $anoSelecionado = (int) ($plano['ano_letivo_id'] ?? $ano_letivo_ativo_id ?? 0);
                        foreach (($anos_letivos ?? []) as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= $anoSelecionado === (int) $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['ano']) ?><?= !empty($a['ativo']) ? ' (ativo)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="rascunho" <?= ($plano['status'] ?? '') !== 'aprovado' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="aprovado" <?= ($plano['status'] ?? '') === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                    </select>
                </div>
                <div>
                    <label for="carga_horaria_prevista" class="block text-sm font-medium text-gray-700 mb-2">Carga horária prevista (horas)</label>
                    <input type="number" min="0" id="carga_horaria_prevista" name="carga_horaria_prevista" value="<?= $val('carga_horaria_prevista', '0') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="avaliacoes_previstas" class="block text-sm font-medium text-gray-700 mb-2">Avaliações previstas</label>
                    <input type="number" min="0" id="avaliacoes_previstas" name="avaliacoes_previstas" value="<?= $val('avaliacoes_previstas', '0') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
        </section>

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Conteúdo e objetivos</h3>
            </div>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="conteudo_previsto" class="block text-sm font-medium text-gray-700 mb-2">Conteúdo previsto</label>
                    <textarea id="conteudo_previsto" name="conteudo_previsto" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"><?= $val('conteudo_previsto') ?></textarea>
                </div>
                <div>
                    <label for="objetivos" class="block text-sm font-medium text-gray-700 mb-2">Objetivos</label>
                    <textarea id="objetivos" name="objetivos" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"><?= $val('objetivos') ?></textarea>
                </div>
                <div>
                    <label for="metodologia" class="block text-sm font-medium text-gray-700 mb-2">Metodologia</label>
                    <textarea id="metodologia" name="metodologia" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"><?= $val('metodologia') ?></textarea>
                </div>
            </div>
        </section>

        <section class="p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Habilidades da BNCC</h3>
                <p class="mt-1 text-sm text-gray-500">Selecione as habilidades previstas para este plano.</p>
            </div>
            <input type="text" id="filtroHab" placeholder="Filtrar por código ou descrição..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
            <div class="max-h-72 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                <?php if (empty($habilidades)): ?>
                    <p class="p-4 text-sm text-gray-500">Nenhuma habilidade no catálogo. Importe a BNCC em <a href="<?= URL ?>/admin/bncc" class="text-green-700 underline">BNCC</a>.</p>
                <?php else: foreach ($habilidades as $h):
                    $checked = in_array((int) $h['id'], array_map('intval', $habIds), true); ?>
                    <label class="hab-item flex items-start gap-3 px-4 py-2 hover:bg-gray-50 cursor-pointer" data-busca="<?= htmlspecialchars(strtolower((string) $h['codigo'] . ' ' . (string) $h['descricao'])) ?>">
                        <input type="checkbox" name="habilidades[]" value="<?= (int) $h['id'] ?>" <?= $checked ? 'checked' : '' ?> class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-200">
                        <span>
                            <span class="block text-sm font-semibold text-gray-800"><?= htmlspecialchars((string) $h['codigo']) ?> <span class="text-xs font-normal text-gray-400"><?= htmlspecialchars((string) ($h['componente'] ?? '')) ?></span></span>
                            <span class="block text-xs text-gray-600"><?= htmlspecialchars((string) $h['descricao']) ?></span>
                        </span>
                    </label>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200">
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="<?= URL ?>/admin/plano-curso" class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">Cancelar</a>
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm">
                    <i class="fa-solid fa-check mr-2"></i> Salvar Plano
                </button>
            </div>
        </div>
    </form>
</div>

<?php if ($id > 0 && !empty($habPlano)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Acompanhamento de cobertura</h3>
        <p class="mt-1 text-sm text-gray-500">Marque as habilidades que já foram trabalhadas em sala.</p>
    </div>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($habPlano as $h): ?>
            <li class="px-6 py-3 flex items-center justify-between gap-4">
                <div>
                    <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars((string) $h['codigo']) ?></span>
                    <span class="block text-xs text-gray-600"><?= htmlspecialchars((string) $h['descricao']) ?></span>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer flex-shrink-0">
                    <input type="checkbox" class="toggle-trabalhada rounded border-gray-300 text-green-600 focus:ring-green-200"
                           data-plano="<?= $id ?>" data-hab="<?= (int) $h['id'] ?>" <?= (int) $h['trabalhada'] === 1 ? 'checked' : '' ?>>
                    <span class="text-sm text-gray-600">Trabalhada</span>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<script>
(function () {
    var filtro = document.getElementById('filtroHab');
    if (filtro) {
        filtro.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            document.querySelectorAll('.hab-item').forEach(function (el) {
                el.style.display = el.getAttribute('data-busca').indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
    var csrf = <?= json_encode((string) ($csrf_token ?? '')) ?>;
    document.querySelectorAll('.toggle-trabalhada').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('plano_id', this.getAttribute('data-plano'));
            fd.append('habilidade_id', this.getAttribute('data-hab'));
            fd.append('trabalhada', this.checked ? '1' : '0');
            fetch('<?= URL ?>/admin/plano-curso/marcar-trabalhada', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .catch(function () { cb.checked = !cb.checked; });
        });
    });
})();
</script>
