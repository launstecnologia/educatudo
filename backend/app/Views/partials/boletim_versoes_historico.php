<?php
$boletimVersoes = is_array($boletim_versoes ?? null) ? $boletim_versoes : [];
$boletimVersoesFallback = trim((string) ($boletim_versoes_fallback ?? ''));
$boletimVersoesCompact = !empty($boletim_versoes_compact);

usort($boletimVersoes, static function ($a, $b): int {
    return ((int) ($a['versao'] ?? 0)) <=> ((int) ($b['versao'] ?? 0));
});

$fmtVersaoQuando = static function ($value): string {
    $v = trim((string) $value);
    if ($v === '' || strpos($v, '0000-00-00') === 0) {
        return '—';
    }
    $ts = strtotime($v);
    return $ts ? date('d/m/Y H:i:s', $ts) : $v;
};

$linhasVersao = [];
$menorVersao = null;
foreach ($boletimVersoes as $versaoItem) {
    if (!is_array($versaoItem)) {
        continue;
    }
    $num = (int) ($versaoItem['versao'] ?? 0);
    if ($num <= 0) {
        continue;
    }
    if ($menorVersao === null || $num < $menorVersao) {
        $menorVersao = $num;
    }
    $quando = $fmtVersaoQuando($versaoItem['created_at'] ?? '');
    $nome = trim((string) ($versaoItem['usuario_nome'] ?? ''));
    $verbo = ($num === 1 || $num === $menorVersao) ? 'Criado' : 'Atualizado';
    if ($nome === '') {
        $texto = $verbo . ' em ' . $quando . ' · usuário não registrado';
    } else {
        $texto = $verbo . ' por ' . $nome . ' em ' . $quando;
    }
    if ($num > 1) {
        $texto = 'v' . $num . ' · ' . $texto;
    }
    $linhasVersao[] = $texto;
}

if ($linhasVersao === []) {
    $quandoFallback = $fmtVersaoQuando($boletimVersoesFallback);
    echo '<div class="text-xs leading-5 text-gray-600">';
    echo '<div>Última gravação em ' . htmlspecialchars($quandoFallback, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<div class="text-gray-400">Usuário não registrado</div>';
    echo '</div>';
    return;
}

$linhasHtml = array_map(static function (string $linha): string {
    return htmlspecialchars($linha, ENT_QUOTES, 'UTF-8');
}, $linhasVersao);

if ($boletimVersoesCompact && count($linhasHtml) > 3) {
    $totalLinhas = count($linhasHtml);
    echo '<div class="text-xs leading-5 text-gray-700 min-w-[16rem]">';
    echo '<div>' . $linhasHtml[0] . '</div>';
    echo '<div>' . $linhasHtml[$totalLinhas - 1] . '</div>';
    echo '<details class="mt-0.5">';
    echo '<summary class="cursor-pointer text-indigo-700">ver as ' . $totalLinhas . ' versões</summary>';
    echo '<ul class="mt-1 space-y-0.5">';
    foreach ($linhasHtml as $linhaHtml) {
        echo '<li>' . $linhaHtml . '</li>';
    }
    echo '</ul></details></div>';
    return;
}

$classeLista = $boletimVersoesCompact
    ? 'text-xs leading-5 text-gray-700 space-y-0.5 min-w-[16rem]'
    : 'mb-3 space-y-1 text-xs text-gray-700';
echo '<ul class="' . $classeLista . '">';
foreach ($linhasHtml as $linhaHtml) {
    echo '<li>' . $linhaHtml . '</li>';
}
echo '</ul>';
