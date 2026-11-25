<?php
$painel = is_array($painel ?? null) ? $painel : [];
$relatorios = is_array($relatorios ?? null) ? $relatorios : [];
$notaMinima = is_numeric($painel['nota_minima'] ?? null) ? (float) $painel['nota_minima'] : null;
$notaMinimaTexto = $notaMinima === null ? '' : number_format($notaMinima, 1, ',', '');
$notaCampo = (string) ($painel['nota_campo'] ?? 'resultado') === 'media_bim' ? 'media_bim' : 'resultado';
$notaCampoNome = $notaCampo === 'media_bim' ? 'média bimestral' : 'resultado final';
$bimestreFiltro = (int) ($painel['bimestre'] ?? 0);
$bimestresPainel = [];
foreach ((array) ($painel['bimestres'] ?? []) as $bimestrePainel) {
    $bimestrePainel = (int) $bimestrePainel;
    if ($bimestrePainel >= 1 && $bimestrePainel <= 4) {
        $bimestresPainel[$bimestrePainel] = $bimestrePainel;
    }
}
$bimestresPainel = array_values($bimestresPainel);
sort($bimestresPainel);
$bimestresAbas = count($bimestresPainel) > 1 ? $bimestresPainel : [];
$fmt = static function ($valor): string {
    if ($valor === null || $valor === '' || !is_numeric($valor)) {
        return '—';
    }

    return number_format((float) $valor, 1, ',', '');
};
$fmtInt = static function ($valor): string {
    if ($valor === null || $valor === '') {
        return '—';
    }

    return (string) (int) $valor;
};
$celNota = static function ($valor, string $campo = '') use ($fmt, $notaMinima, $notaCampo): string {
    $texto = $fmt($valor);
    if ($texto === '—') {
        return '<td class="px-2 py-2">—</td>';
    }
    $abaixo = $notaMinima !== null && $campo === $notaCampo && is_numeric($valor) && (float) $valor < $notaMinima;

    return '<td class="px-2 py-2 ' . ($abaixo ? 'abaixo' : 'nota') . '">' . $texto . '</td>';
};
$linhaTemNotaAbaixo = static function (array $linha) use ($notaMinima, $notaCampo): bool {
    if ($notaMinima === null) {
        return true;
    }

    return is_numeric($linha[$notaCampo] ?? null) && (float) $linha[$notaCampo] < $notaMinima;
};
$rotuloSerie = static function (int $serie): string {
    if ($serie === 1) {
        return '1ª Série EM';
    }
    if ($serie === 2) {
        return '2ª Série EM';
    }
    if ($serie === 3) {
        return '3ª Série EM';
    }

    return 'Ensino Médio';
};
foreach ($relatorios as $relatorio) {
    include __DIR__ . '/_aluno.php';
}
