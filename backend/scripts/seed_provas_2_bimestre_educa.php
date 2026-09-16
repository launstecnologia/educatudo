<?php
$extra = array_slice($argv ?? [], 1);
$argv = array_merge([$argv[0] ?? __FILE__, '--bimestre=2'], $extra);
require __DIR__ . '/seed_avaliacoes_em_2025_educa.php';
