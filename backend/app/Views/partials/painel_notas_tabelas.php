<?php
/**
 * Tabelas do painel de notas (aluno, pais, ficha admin).
 *
 * @var list<array<string,mixed>> $paineis_notas
 */
$paineisNotas = is_array($paineis_notas ?? null) ? $paineis_notas : [];
if ($paineisNotas === []) {
    return;
}
$fmtNota = static function (?float $n): string {
    if ($n === null) {
        return '—';
    }
    return number_format($n, 1, ',', '.');
};
?>
<div class="space-y-8 mb-8">
    <?php foreach ($paineisNotas as $painel): ?>
        <?php
        $quadro = is_array($painel['quadro'] ?? null) ? $painel['quadro'] : [];
        $tabelas = is_array($painel['tabelas'] ?? null) ? $painel['tabelas'] : [];
        if ($tabelas === []) {
            continue;
        }
        ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars((string) ($quadro['nome'] ?? 'Painel de notas'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-sm text-gray-500 mb-4">Cada coluna de lançamento entra sozinha quando a prova ou o trabalho é registrado. Colunas em destaque são calculadas.</p>
            <div class="space-y-6">
                <?php foreach ($tabelas as $tabela): ?>
                    <?php
                    $colunas = is_array($tabela['colunas'] ?? null) ? $tabela['colunas'] : [];
                    $linhas = is_array($tabela['linhas'] ?? null) ? $tabela['linhas'] : [];
                    $tituloTabela = trim((string) ($tabela['titulo'] ?? ''));
                    ?>
                    <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white">
                        <?php if ($tituloTabela !== ''): ?>
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <h3 class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($tituloTabela, ENT_QUOTES, 'UTF-8') ?></h3>
                            </div>
                        <?php endif; ?>
                        <table class="min-w-full text-sm text-left">
                            <thead class="bg-gray-100 text-gray-700">
                                <tr>
                                    <th class="px-4 py-2 font-semibold sticky left-0 bg-gray-100 z-10">Matéria</th>
                                    <?php foreach ($colunas as $col): ?>
                                        <?php $calc = (($col['papel'] ?? '') === 'calculada'); ?>
                                        <th class="px-3 py-2 font-semibold text-center whitespace-nowrap <?= $calc ? 'bg-violet-50 text-violet-900' : '' ?>">
                                            <?= htmlspecialchars((string) ($col['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($col['vai_para_boletim'])): ?>
                                                <span class="block text-[10px] font-normal text-violet-600 uppercase tracking-wide">Boletim</span>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if ($linhas === []): ?>
                                    <tr>
                                        <td colspan="<?= 1 + count($colunas) ?>" class="px-4 py-6 text-center text-gray-500">Nenhuma matéria neste bloco.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($linhas as $linha): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 sticky left-0 bg-white z-10">
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($linha['materia_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php if (!empty($linha['pai_nome'])): ?>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars((string) $linha['pai_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <?php
                                            $celulas = is_array($linha['celulas'] ?? null) ? $linha['celulas'] : [];
                                            foreach ($colunas as $col):
                                                $cod = (string) ($col['codigo'] ?? '');
                                                $val = $celulas[$cod] ?? null;
                                                $calc = (($col['papel'] ?? '') === 'calculada');
                                                $num = is_numeric($val) ? (float) $val : null;
                                            ?>
                                                <td class="px-3 py-2 text-center <?= $calc ? 'bg-violet-50 font-semibold text-gray-900' : 'text-gray-800' ?>">
                                                    <?= htmlspecialchars($fmtNota($num), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
