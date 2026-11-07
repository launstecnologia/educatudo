<?php
/**
 * Dry-run do perfil acadêmico do seed ET25 (Ensino Médio 2025).
 *
 * Não grava no banco. Reproduz a mesma conta da carga
 * 2026_10_06_importar_dados_escola_teste_em_2025.sql:
 * perfil estável por nickname, três simulados + prova, recuperação do
 * trimestre só com média abaixo de 6,00 e recuperação final só na média anual.
 *
 * Uso: php scripts/simular_notas_escola_teste_2025.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

const MATERIAS = [
    'Língua Portuguesa',
    'Matemática',
    'História',
    'Geografia',
    'Física',
    'Química',
    'Biologia',
    'Sociologia',
];

function u10000(string $chave): int
{
    return crc32($chave) % 10000;
}

/** Arredonda como o ROUND do MySQL, metade para longe de zero. $passo é a unidade de corte. */
function arredondarInteiro(int $valor, int $passo): int
{
    $sinal = $valor < 0 ? -1 : 1;
    $valor = abs($valor);
    $quo = intdiv($valor, $passo);
    $resto = $valor % $passo;
    if ($resto * 2 >= $passo) {
        $quo++;
    }
    return $sinal * $quo;
}

function perfilDo(string $nick): string
{
    if (substr($nick, -2) === '43') {
        return 'conselho';
    }
    $p = crc32($nick . '|perfil') % 100;
    if ($p < 12) {
        return 'excelente';
    }
    if ($p < 35) {
        return 'bom';
    }
    if ($p < 55) {
        return 'regular';
    }
    if ($p < 73) {
        return 'dificuldade_especifica';
    }
    if ($p < 85) {
        return 'dificuldade_generalizada';
    }
    if ($p < 94) {
        return 'em_evolucao';
    }
    return 'em_queda';
}

/** @return array<string, true> */
function materiasFracas(string $nick, string $perfil): array
{
    if ($perfil !== 'dificuldade_especifica') {
        return [];
    }
    $n = 1 + (crc32($nick . '|nweak') % 3);
    $ordem = MATERIAS;
    usort($ordem, static function (string $a, string $b) use ($nick): int {
        $ca = crc32($nick . '|fraca|' . $a);
        $cb = crc32($nick . '|fraca|' . $b);
        return $ca === $cb ? ($a <=> $b) : ($ca <=> $cb);
    });
    $fracas = [];
    foreach (array_slice($ordem, 0, $n) as $nome) {
        $fracas[$nome] = true;
    }
    return $fracas;
}

/**
 * Alvo gravado como DECIMAL(8,4). Unidade: 0,0001.
 * A conta inteira evita o erro de float do ROUND do MySQL.
 */
function alvoUnidades(string $nick, string $materia, int $tri, string $perfil, bool $fraca): int
{
    $base = u10000($nick . '|' . $materia . '|' . $tri . '|base');
    $ruido = u10000($nick . '|' . $materia . '|' . $tri . '|ruido') - 5000;
    $micro = match ($perfil) {
        'excelente' => 8_600_000 + $base * 70 + $ruido * 35,
        'bom' => 7_200_000 + $base * 80 + $ruido * 55,
        'regular' => 5_850_000 + $base * 115 + $ruido * 70,
        'dificuldade_especifica' => $fraca
            ? 4_400_000 + $base * 100 + ($tri - 1) * 120_000 + $ruido * 40
            : 7_000_000 + $base * 60 + $ruido * 45,
        'dificuldade_generalizada' => 4_550_000 + $base * 115 + $ruido * 55,
        'em_evolucao' => 5_150_000 + ($tri - 1) * 1_100_000 + ($base - 5000) * 30,
        'em_queda' => 7_900_000 - ($tri - 1) * 1_050_000 + ($base - 5000) * 30,
        default => 5_350_000 + $base * 35 + $ruido * 15,
    };
    $vies = [
        'Língua Portuguesa' => 200_000,
        'Matemática' => -350_000,
        'História' => 50_000,
        'Geografia' => 0,
        'Física' => -250_000,
        'Química' => -150_000,
        'Biologia' => -50_000,
        'Sociologia' => 100_000,
    ];
    return arredondarInteiro($micro + ($vies[$materia] ?? 0), 100);
}

/** @return array{sim1:int,sim2:int,sim3:int,prova:int} décimos */
function avaliacoes(string $nick, string $materia, int $tri, int $alvo): array
{
    $tipos = ['sim1', 'sim2', 'sim3', 'prova'];
    $deltas = [];
    foreach ($tipos as $tipo) {
        $deltas[$tipo] = (crc32($nick . '|' . $materia . '|' . $tri . '|' . $tipo) % 15 - 7) * 1000;
    }
    $mediaDelta = intdiv(array_sum($deltas), 4);
    $notas = [];
    foreach ($deltas as $tipo => $delta) {
        $bruto = $alvo + $delta - $mediaDelta;
        if ($bruto < 0) {
            $bruto = 0;
        } elseif ($bruto > 100_000) {
            $bruto = 100_000;
        }
        $notas[$tipo] = arredondarInteiro($bruto, 1000);
    }
    return $notas;
}

/** Média em centésimos, a partir de quatro notas em décimos. */
function mediaCents(int $sim1, int $sim2, int $sim3, int $prova): int
{
    $numerador = ($sim1 + $sim2 + $sim3 + $prova) * 5;
    $quo = intdiv($numerador, 2);
    if ($numerador % 2 !== 0) {
        $quo++;
    }
    return $quo;
}

function mediaTrimestre(int $sim1, int $sim2, int $sim3, int $prova, ?int $rec): int
{
    $provaPos = $rec === null ? $prova : max($prova, min($rec, 60));
    return mediaCents($sim1, $sim2, $sim3, $provaPos);
}

function notaRecuperacao(string $nick, string $materia, int $tri, int $sim1, int $sim2, int $sim3): int
{
    $pode = ($sim1 + $sim2 + $sim3) >= 180;
    $sorte = crc32($nick . '|' . $materia . '|' . $tri . '|recdec') % 100;
    if ($pode && $sorte < 68) {
        return 60 + (crc32($nick . '|' . $materia . '|' . $tri . '|recok') % 31);
    }
    return 35 + (crc32($nick . '|' . $materia . '|' . $tri . '|recno') % 22);
}

function notaRecFinal(string $nick, string $materia, string $perfil): int
{
    $chance = match ($perfil) {
        'excelente' => 70,
        'bom' => 65,
        'regular' => 52,
        'dificuldade_especifica' => 72,
        'dificuldade_generalizada' => 38,
        'em_evolucao' => 64,
        'em_queda' => 42,
        default => 0,
    };
    $sorte = crc32($nick . '|' . $materia . '|rf') % 100;
    if ($sorte < $chance) {
        return 62 + (crc32($nick . '|' . $materia . '|rfok') % 19);
    }
    return 36 + (crc32($nick . '|' . $materia . '|rfno') % 21);
}

function mediaAnualCents(int $t1, int $t2, int $t3): int
{
    $soma = $t1 + $t2 + $t3;
    $quo = intdiv($soma, 3);
    if ($soma % 3 >= 2) {
        $quo++;
    }
    return $quo;
}

function fmtNota(int $decimos): string
{
    $inteiro = intdiv($decimos, 10);
    $frac = $decimos % 10;
    return $frac === 0 ? (string) $inteiro : $inteiro . '.' . $frac;
}

function fmtMedia(int $cents): string
{
    $neg = $cents < 0;
    $cents = abs($cents);
    return ($neg ? '-' : '') . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

$series = ['1', '2', '3'];
$letras = ['a', 'b', 'c', 'd'];

$porPerfil = [];
$alunos = 0;
$turmas = 12;
$disciplinas = count(MATERIAS);
$notasBase = 0;
$recs = [1 => 0, 2 => 0, 3 => 0];
$alunosRecFinal = 0;
$linhasRecFinal = 0;
$direto = 0;
$aposRec = 0;
$reprovado = 0;
$conselhoFora = 0;
$conselhoMateriaBaixa = 0;
$exemplos = [];
$porMateriaRec = [];

foreach ($series as $serie) {
    foreach ($letras as $letra) {
        for ($slot = 1; $slot <= 50; $slot++) {
            $nick = sprintf('et25.%s%s%02d', $serie, $letra, $slot);
            $perfil = perfilDo($nick);
            $porPerfil[$perfil] = ($porPerfil[$perfil] ?? 0) + 1;
            $alunos++;
            $fracas = materiasFracas($nick, $perfil);
            $historico = [];
            $usouRec = false;
            $precisaFinal = false;
            $ficou = false;
            $especiais = substr($nick, -2);

            foreach (MATERIAS as $materia) {
                $trimestres = [];
                $medias = [];
                foreach ([1, 2, 3] as $tri) {
                    $centro = alvoUnidades($nick, $materia, $tri, $perfil, isset($fracas[$materia]));
                    $notas = avaliacoes($nick, $materia, $tri, $centro);
                    $antes = mediaCents($notas['sim1'], $notas['sim2'], $notas['sim3'], $notas['prova']);
                    $rec = null;
                    if ($antes < 600) {
                        $rec = notaRecuperacao($nick, $materia, $tri, $notas['sim1'], $notas['sim2'], $notas['sim3']);
                        $recs[$tri]++;
                        $porMateriaRec[$materia] = ($porMateriaRec[$materia] ?? 0) + 1;
                        $usouRec = true;
                    }
                    $final = mediaTrimestre($notas['sim1'], $notas['sim2'], $notas['sim3'], $notas['prova'], $rec);
                    $notasBase += 4;
                    $trimestres[$tri] = $notas + ['antes' => $antes, 'rec' => $rec, 'final' => $final];
                    $medias[] = $final;
                }
                $anual = mediaAnualCents($medias[0], $medias[1], $medias[2]);
                $recFinal = null;
                $depois = $anual;
                if ($anual < 600) {
                    $recFinal = notaRecFinal($nick, $materia, $perfil);
                    $depois = max($anual, $recFinal * 10);
                    $linhasRecFinal++;
                    $precisaFinal = true;
                }
                if ($depois < 600) {
                    $ficou = true;
                }
                $historico[$materia] = [
                    'trimestres' => $trimestres,
                    'anual' => $anual,
                    'rec_final' => $recFinal,
                    'depois' => $depois,
                    'fraca' => isset($fracas[$materia]),
                ];
            }

            if ($precisaFinal && !in_array($especiais, ['44', '47', '48'], true)) {
                $alunosRecFinal++;
            }
            if (!in_array($especiais, ['43', '44', '47', '48'], true)) {
                if ($ficou) {
                    $reprovado++;
                } elseif ($usouRec || $precisaFinal) {
                    $aposRec++;
                } else {
                    $direto++;
                }
            }
            if ($especiais === '43') {
                $soma = 0;
                foreach ($historico as $h) {
                    $soma += $h['depois'];
                    if ($h['depois'] < 500) {
                        $conselhoMateriaBaixa++;
                    }
                }
                $quo = intdiv($soma, 8);
                if ($soma % 8 >= 4) {
                    $quo++;
                }
                if ($quo < 500 || $quo >= 600) {
                    $conselhoFora++;
                }
            }
            if (!isset($exemplos[$perfil])) {
                $exemplos[$perfil] = ['nick' => $nick, 'historico' => $historico];
            }
        }
    }
}

ksort($porPerfil);
echo "DRY-RUN ET25 — nenhum registro gravado\n";
echo "Escola: Educa Teste | ano 2025 | prefixo et25.* | {$turmas} turmas | {$disciplinas} disciplinas\n";
echo "Alunos processados: {$alunos}\n";
echo "Notas de simulado e prova: {$notasBase}\n";
echo "Recuperações T1={$recs[1]} T2={$recs[2]} T3={$recs[3]} total=" . array_sum($recs) . "\n";
echo "Linhas de recuperação final: {$linhasRecFinal}\n";
echo "Alunos com recuperação final (fora transferência, desistência e frequência): {$alunosRecFinal}\n";
echo "Aprovados direto: {$direto} | após recuperação: {$aposRec} | reprovados por nota: {$reprovado}\n";
echo "Conselho fora da faixa 5–6: {$conselhoFora}\n";
echo "Disciplinas de conselho abaixo de 5: {$conselhoMateriaBaixa}\n";
echo "Perfis:\n";
foreach ($porPerfil as $nome => $qtd) {
    echo "  {$nome}: {$qtd}\n";
}
echo "Recuperações por disciplina:\n";
arsort($porMateriaRec);
foreach ($porMateriaRec as $nome => $qtd) {
    echo "  {$nome}: {$qtd}\n";
}

$mostrar = ['excelente', 'bom', 'regular', 'dificuldade_especifica', 'dificuldade_generalizada', 'em_evolucao', 'em_queda', 'conselho'];
foreach ($mostrar as $perfil) {
    if (!isset($exemplos[$perfil])) {
        continue;
    }
    $ex = $exemplos[$perfil];
    echo "\n== {$perfil} {$ex['nick']} ==\n";
    foreach ($ex['historico'] as $materia => $h) {
        $partes = [];
        foreach ($h['trimestres'] as $tri => $t) {
            $rec = $t['rec'] === null ? '-' : fmtNota($t['rec']);
            $partes[] = 'T' . $tri . ' ' . fmtNota($t['sim1']) . '/' . fmtNota($t['sim2']) . '/' . fmtNota($t['sim3']) . '/' . fmtNota($t['prova'])
                . ' rec ' . $rec . ' => ' . fmtMedia($t['final']);
        }
        $rf = $h['rec_final'] === null ? '-' : fmtNota($h['rec_final']);
        $marca = $h['fraca'] ? ' [fraca]' : '';
        echo "  {$materia}{$marca}: " . implode(' | ', $partes) . ' | ano ' . fmtMedia($h['anual']) . " rf {$rf} => " . fmtMedia($h['depois']) . "\n";
    }
}

echo "\nNada foi gravado. A carga só entra pelo painel Master, na escola teste,\n";
echo "marcando somente 2026_10_06_importar_dados_escola_teste_em_2025.sql.\n";
echo "Reversão: 2026_10_06_importar_dados_escola_teste_em_2025_apagar.sql.\n";
