<?php
/**
 * Smoke local (sem banco): soma vs média ao juntar professores da mesma matéria.
 * Uso: php backend/scripts/smoke_materia_unica_modo.php
 */

declare(strict_types=1);

function deduplicarNotasPorMateriaSmoke(array $notas, string $modo = 'soma'): array
{
    $modo = strtolower(trim($modo)) === 'media' ? 'media' : 'soma';
    $porMateriaEProva = [];
    foreach ($notas as $item) {
        $valor = (float) ($item['valor'] ?? 0);
        $materiaId = (int) ($item['materia_id'] ?? 0);
        $provaUid = (string) ($item['prova_uid'] ?? '');
        $k = $materiaId . '|' . $provaUid;
        if (!isset($porMateriaEProva[$k])) {
            $porMateriaEProva[$k] = ['soma' => 0.0, 'qtd' => 0, 'materia_id' => $materiaId];
        }
        $porMateriaEProva[$k]['soma'] += $valor;
        $porMateriaEProva[$k]['qtd']++;
    }
    $saida = [];
    foreach ($porMateriaEProva as $item) {
        $soma = (float) $item['soma'];
        $qtd = max(1, (int) $item['qtd']);
        $saida[] = [
            'valor' => $modo === 'media' ? round($soma / $qtd, 2) : $soma,
            'materia_id' => (int) $item['materia_id'],
        ];
    }
    return $saida;
}

$profs = [
    ['valor' => 10.0, 'materia_id' => 1, 'prova_uid' => 'p1'],
    ['valor' => 10.0, 'materia_id' => 1, 'prova_uid' => 'p1'],
    ['valor' => 10.0, 'materia_id' => 1, 'prova_uid' => 'p1'],
];

$soma = deduplicarNotasPorMateriaSmoke($profs, 'soma');
$media = deduplicarNotasPorMateriaSmoke($profs, 'media');

$ok = true;
if (abs(($soma[0]['valor'] ?? 0) - 30.0) > 0.001) {
    fwrite(STDERR, "FALHA soma: esperado 30, veio " . ($soma[0]['valor'] ?? 'null') . PHP_EOL);
    $ok = false;
}
if (abs(($media[0]['valor'] ?? 0) - 10.0) > 0.001) {
    fwrite(STDERR, "FALHA media: esperado 10, veio " . ($media[0]['valor'] ?? 'null') . PHP_EOL);
    $ok = false;
}

$parcial = [
    ['valor' => 3.0, 'materia_id' => 1, 'prova_uid' => 'p1'],
    ['valor' => 4.0, 'materia_id' => 1, 'prova_uid' => 'p1'],
    ['valor' => 3.0, 'materia_id' => 1, 'prova_uid' => 'p1'],
];
$somaParc = deduplicarNotasPorMateriaSmoke($parcial, 'soma');
if (abs(($somaParc[0]['valor'] ?? 0) - 10.0) > 0.001) {
    fwrite(STDERR, "FALHA soma parcial: esperado 10, veio " . ($somaParc[0]['valor'] ?? 'null') . PHP_EOL);
    $ok = false;
}

// Área: lista filtrada (simula exclusão de filha neste evento)
$filhasArea = [11, 12, 13]; // Leitura, Literatura, Português
$excluirNesteEvento = [12]; // remove Literatura só neste bimestre/evento
$filhasNesteEvento = array_values(array_filter($filhasArea, static fn ($id) => !in_array($id, $excluirNesteEvento, true)));
if ($filhasNesteEvento !== [11, 13]) {
    fwrite(STDERR, 'FALHA filhas do evento: ' . json_encode($filhasNesteEvento) . PHP_EOL);
    $ok = false;
}

if (!$ok) {
    exit(1);
}

echo "OK smoke materia_unica_modo: soma=30, media=10, soma_parcial=10, area_filhas=[11,13]\n";
exit(0);
