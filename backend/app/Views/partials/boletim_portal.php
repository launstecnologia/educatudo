<?php
/**
 * Boletim oficial da vida escolar e, quando existir, o boletim já emitido. Só consulta.
 *
 * @var list<array<string,mixed>>|null $boletins_gerados_boletim
 * @var list<array<string,mixed>>|null $boletim_quadros_portal
 */
$boletins_gerados = is_array($boletins_gerados_boletim ?? null) ? $boletins_gerados_boletim : [];
$boletim_quadros_portal = is_array($boletim_quadros_portal ?? null) ? $boletim_quadros_portal : [];
$boletim_pode_excluir = false;
if ($boletim_quadros_portal === [] && is_array($boletins_gerados_notas ?? null)) {
    $porAno = [];
    foreach ($boletins_gerados_notas as $evento) {
        if (!is_array($evento)) {
            continue;
        }
        $codigoNota = '';
        $codigoFalta = '';
        $labelNota = '';
        foreach ((array) ($evento['colunas'] ?? []) as $colunaEvento) {
            if (!is_array($colunaEvento)) {
                continue;
            }
            $codigo = trim((string) ($colunaEvento['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $nome = mb_strtolower(trim((string) ($colunaEvento['nome'] ?? '')), 'UTF-8');
            $tipo = mb_strtolower(trim((string) ($colunaEvento['layout_type'] ?? '')), 'UTF-8');
            $blob = $codigo . ' ' . $nome . ' ' . $tipo;
            if (str_contains($blob, 'falt')) {
                $codigoFalta = $codigo;
                continue;
            }
            $pareceMedia = str_contains($blob, 'media') || str_contains($blob, 'média') || str_contains($blob, 'bim');
            if ($codigoNota === '' || $pareceMedia) {
                $codigoNota = $codigo;
                $labelNota = trim((string) ($colunaEvento['nome'] ?? ''));
                if (!$pareceMedia && $labelNota === '') {
                    $codigoNota = $codigo;
                }
            }
        }
        if ($codigoNota === '') {
            continue;
        }
        $ano = (int) ($evento['ano_letivo'] ?? 0);
        $bimestre = (int) ($evento['bimestre'] ?? 0);
        if ($labelNota === '') {
            $labelNota = $bimestre > 0 ? ($bimestre . 'º Bimestre') : 'Nota';
        }
        $chave = 'n' . (int) ($evento['regra_id'] ?? 0) . 'b' . $bimestre;
        if (!isset($porAno[$ano])) {
            $porAno[$ano] = ['colunas' => [], 'linhas' => []];
        }
        $porAno[$ano]['colunas'][] = ['codigo' => $chave, 'label' => $labelNota, 'bimestre' => $bimestre];
        if ($codigoFalta !== '') {
            $porAno[$ano]['colunas'][] = [
                'codigo' => $chave . 'f',
                'label' => 'Faltas',
                'bimestre' => $bimestre,
            ];
        }
        foreach ((array) ($evento['linhas'] ?? []) as $linhaEvento) {
            if (!is_array($linhaEvento)) {
                continue;
            }
            $materia = trim((string) ($linhaEvento['materia_nome'] ?? ''));
            if ($materia === '') {
                continue;
            }
            $notasEvento = is_array($linhaEvento['notas'] ?? null) ? $linhaEvento['notas'] : [];
            $porAno[$ano]['linhas'][$materia][$chave] = $notasEvento[$codigoNota] ?? null;
            if ($codigoFalta !== '') {
                $porAno[$ano]['linhas'][$materia][$chave . 'f'] = $notasEvento[$codigoFalta] ?? null;
            }
        }
    }
    krsort($porAno);
    foreach ($porAno as $ano => $bloco) {
        $colunasBloco = $bloco['colunas'];
        usort($colunasBloco, static function (array $a, array $b): int {
            $bimestreA = (int) ($a['bimestre'] ?? 0);
            $bimestreB = (int) ($b['bimestre'] ?? 0);
            if ($bimestreA !== $bimestreB) {
                return $bimestreA <=> $bimestreB;
            }
            $faltaA = str_ends_with((string) ($a['codigo'] ?? ''), 'f') ? 1 : 0;
            $faltaB = str_ends_with((string) ($b['codigo'] ?? ''), 'f') ? 1 : 0;

            return $faltaA <=> $faltaB;
        });
        $linhasBloco = [];
        foreach ($bloco['linhas'] as $materia => $notasMateria) {
            $linhasBloco[] = ['materia' => (string) $materia, 'notas' => $notasMateria];
        }
        if ($colunasBloco === [] || $linhasBloco === []) {
            continue;
        }
        $boletim_quadros_portal[] = [
            'titulo' => 'Boletim' . ((int) $ano > 0 ? ' ' . (int) $ano : ''),
            'colunas' => $colunasBloco,
            'linhas' => $linhasBloco,
        ];
    }
}
$formatarNotaPortal = static function ($valor, bool $faltas): string {
    if ($valor === null || $valor === '') {
        return '—';
    }
    if ($faltas && is_numeric($valor)) {
        return number_format((float) $valor, 0, ',', '.');
    }
    if (is_numeric($valor)) {
        return number_format((float) $valor, 1, ',', '.');
    }

    return (string) $valor;
};
?>
<?php if ($boletim_quadros_portal === [] && $boletins_gerados === []): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhum boletim disponível.</p>
    </div>
<?php else: ?>
    <?php foreach ($boletim_quadros_portal as $quadro): ?>
        <?php
        if (!is_array($quadro)) {
            continue;
        }
        $colunas = is_array($quadro['colunas'] ?? null) ? $quadro['colunas'] : [];
        $linhas = is_array($quadro['linhas'] ?? null) ? $quadro['linhas'] : [];
        if ($colunas === [] || $linhas === []) {
            continue;
        }
        ?>
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                <strong class="text-gray-900"><?= htmlspecialchars((string) ($quadro['titulo'] ?? 'Boletim'), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Matéria</th>
                            <?php foreach ($colunas as $coluna): ?>
                                <th class="px-4 py-2 text-center whitespace-nowrap"><?= htmlspecialchars((string) ($coluna['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($linhas as $linha): ?>
                            <?php if (!is_array($linha)) { continue; } ?>
                            <tr>
                                <td class="px-4 py-2"><?= htmlspecialchars((string) ($linha['materia'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php foreach ($colunas as $coluna): ?>
                                    <?php
                                    $codigo = (string) ($coluna['codigo'] ?? '');
                                    $faltas = str_starts_with($codigo, 'f');
                                    $notasLinha = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
                                    ?>
                                    <td class="px-4 py-2 text-center font-medium"><?= htmlspecialchars($formatarNotaPortal($notasLinha[$codigo] ?? null, $faltas), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
    <?php if ($boletins_gerados !== []): ?>
        <?php require __DIR__ . '/boletins_gerados.php'; ?>
    <?php endif; ?>
<?php endif; ?>
