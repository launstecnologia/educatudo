<?php
$config = is_array($config ?? null) ? $config : [];
$materias = is_array($materias ?? null) ? $materias : [];
$schemaPronto = !empty($schema_pronto);
$semanasA = is_array($config['semanas_grupo_a'] ?? null) ? $config['semanas_grupo_a'] : [1, 3, 5, 7];
$semanasB = is_array($config['semanas_grupo_b'] ?? null) ? $config['semanas_grupo_b'] : [2, 4, 6, 8];
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Quadro de Notas Semanais</h2>
            <p class="text-gray-600">Defina as semanas de cada bloco (S1–S8) e o grupo de cada matéria. A média bimestral oficial fica na Configuração do Boletim.</p>
        </div>
        <a href="<?= URL ?>/admin/boletim" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php if (!empty($flash_message)): ?>
    <div class="mb-6 p-4 rounded-lg border <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
        <?= htmlspecialchars((string) $flash_message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!$schemaPronto): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
        Rode a migration <code class="text-sm">2026_08_14_notas_semanais_quadro.sql</code> no painel Master antes de usar este quadro.
    </div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="<?= URL ?>/admin/notas-semanais">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <h3 class="text-lg font-semibold text-gray-900 mb-4">Semanas por bloco</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Bloco A (ímpar, padrão)</p>
                <div class="flex flex-wrap gap-3">
                    <?php for ($s = 1; $s <= 8; $s++): ?>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="semanas_grupo_a[]" value="<?= $s ?>"
                                   <?= in_array($s, $semanasA, true) ? 'checked' : '' ?>
                                   class="rounded border-gray-300">
                            S<?= $s ?>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Bloco B (par, padrão)</p>
                <div class="flex flex-wrap gap-3">
                    <?php for ($s = 1; $s <= 8; $s++): ?>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="semanas_grupo_b[]" value="<?= $s ?>"
                                   <?= in_array($s, $semanasB, true) ? 'checked' : '' ?>
                                   class="rounded border-gray-300">
                            S<?= $s ?>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <p class="text-sm text-gray-600 mb-8 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3">
            Pesos e fórmula da média bimestral ficam na
            <a href="<?= URL ?>/admin/boletim-configuracao" class="text-purple-700 underline font-medium">Configuração do Boletim</a>.
        </p>

        <h3 class="text-lg font-semibold text-gray-900 mb-2">Matérias em cada bloco</h3>
        <p class="text-sm text-gray-500 mb-4">Se ficar em branco, o sistema infere pelo número da semana (ímpar = A, par = B).</p>
        <div class="overflow-x-auto border border-gray-200 rounded-lg mb-8">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold">Matéria</th>
                        <th class="px-4 py-2 text-center font-semibold">Bloco A</th>
                        <th class="px-4 py-2 text-center font-semibold">Bloco B</th>
                        <th class="px-4 py-2 text-center font-semibold">Auto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($materias as $mat):
                        $gid = (int) $mat['id'];
                        $g = $mat['grupo'] ?? null;
                    ?>
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900"><?= htmlspecialchars((string) $mat['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-2 text-center">
                                <input type="radio" name="materia_grupo[<?= $gid ?>]" value="A" <?= $g === 'A' ? 'checked' : '' ?>>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <input type="radio" name="materia_grupo[<?= $gid ?>]" value="B" <?= $g === 'B' ? 'checked' : '' ?>>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <input type="radio" name="materia_grupo[<?= $gid ?>]" value="" <?= $g === null ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary-custom inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-semibold">
                Salvar quadro
            </button>
        </div>
    </form>
</div>
<?php endif; ?>
