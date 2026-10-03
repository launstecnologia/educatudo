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

$versaoAberta = (int) ($boletim_versao_aberta ?? 0);
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
    $idVersao = (int) ($versaoItem['geracao_id'] ?? 0);
    if ($idVersao <= 0) {
        $idVersao = (int) ($versaoItem['id'] ?? 0);
    }
    $linhasVersao[] = [
        'num' => $num,
        'id' => $idVersao,
        'texto' => ($idVersao > 0 ? 'ID ' . $idVersao . ' · ' : '') . 'v' . $num . ' · ' . $texto,
        'vigente' => (int) ($versaoItem['vigente'] ?? 0) === 1,
    ];
}

if ($linhasVersao === []) {
    $quandoFallback = $fmtVersaoQuando($boletimVersoesFallback);
    echo '<div class="text-xs leading-5 text-gray-600">';
    echo '<div>Última gravação em ' . htmlspecialchars($quandoFallback, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<div class="text-gray-400">Usuário não registrado</div>';
    echo '</div>';
    return;
}

$botaoVersao = static function (array $linha) use ($versaoAberta): string {
    $num = (int) ($linha['num'] ?? 0);
    $aberta = $versaoAberta > 0 && $num === $versaoAberta;
    $classe = 'btn-ver-versao block w-full text-left hover:text-indigo-700';
    if ($aberta) {
        $classe .= ' font-semibold text-indigo-800';
    }
    $html = '<button type="button" class="' . $classe . '" data-versao="' . $num . '" title="Ver as notas desta versão">';
    $html .= htmlspecialchars((string) ($linha['texto'] ?? ''), ENT_QUOTES, 'UTF-8');
    if (!empty($linha['vigente'])) {
        $html .= ' <span class="inline-block ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-emerald-100 text-emerald-800">vigente</span>';
    }
    if ($aberta) {
        $html .= ' <span class="inline-block ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-indigo-100 text-indigo-800">em tela</span>';
    }
    $html .= '</button>';

    return $html;
};

if ($boletimVersoesCompact && count($linhasVersao) > 3) {
    $totalLinhas = count($linhasVersao);
    echo '<div class="text-xs leading-5 text-gray-700 min-w-[16rem]">';
    echo '<div>' . $botaoVersao($linhasVersao[0]) . '</div>';
    echo '<div>' . $botaoVersao($linhasVersao[$totalLinhas - 1]) . '</div>';
    echo '<details class="mt-0.5">';
    echo '<summary class="cursor-pointer text-indigo-700">ver as ' . $totalLinhas . ' versões</summary>';
    echo '<ul class="mt-1 space-y-0.5">';
    foreach ($linhasVersao as $linha) {
        echo '<li>' . $botaoVersao($linha) . '</li>';
    }
    echo '</ul></details></div>';
    return;
}

$classeLista = $boletimVersoesCompact
    ? 'text-xs leading-5 text-gray-700 space-y-0.5 min-w-[16rem]'
    : 'mb-3 space-y-1 text-xs text-gray-700';
echo '<ul class="' . $classeLista . '">';
foreach ($linhasVersao as $linha) {
    echo '<li>' . $botaoVersao($linha) . '</li>';
}
echo '</ul>';
if (!$boletimVersoesCompact && count($linhasVersao) > 1) {
    echo '<p class="mb-3 text-xs text-gray-500">Clique numa versão para ver as notas daquela gravação, da primeira até a vigente.</p>';
}
