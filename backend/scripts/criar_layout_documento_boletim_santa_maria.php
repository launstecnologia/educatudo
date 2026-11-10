<?php
/**
 * Completa o pacote Santa Maria 2024 e cria o layout em Modelos de Documentos
 * espelhando o boletim em print (PP/PU/CH/PE/MU/FAL + semestres + final).
 *
 * Pré-requisito: seed + configurar_demonstrativo_boletim_sm24_2024.php
 *
 * Uso (container PHP):
 *   php scripts/criar_layout_documento_boletim_santa_maria.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '512M');
set_time_limit(0);

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;

const TENANT_DB = 'educatudo_educa';
const ANO = 2024;
const PREFIXO = 'SM24';
const CODIGO_MODELO = 'boletim_santa_maria_2024';
const TURMA_NOME = '1A Santa Maria';
const MEDIA_MINIMA = 7.0;

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

function fmt(?float $n): string
{
    if ($n === null) {
        return '—';
    }
    return number_format($n, 1, ',', '');
}

function mu(?float $pp, ?float $pu, ?float $pe): ?float
{
    $base = [];
    if ($pp !== null) {
        $base[] = $pp;
    }
    if ($pu !== null) {
        $base[] = $pu;
    }
    if ($base === []) {
        return $pe;
    }
    $m = array_sum($base) / count($base);
    if ($pe !== null) {
        $m += $pe;
    }
    return round($m, 1);
}

/**
 * Ordem e hierarquia do print (pai em maiúsculo na área; filhos indentados).
 *
 * @return list<array{nome:string,pai:?string,pp:array<int,?float>,pu:array<int,?float>,pe:array<int,?float>,fal:array<int,int>,rec_s1:?float,rec_s2:?float}>
 */
function linhasBoletim(): array
{
    // Espelha o seed + linhas-mãe (soma das MUs dos filhos).
    $filhos = [
        'Língua Portuguesa' => [
            'pai' => null,
            'pp' => [1 => 8.5, 2 => 7.0, 3 => 8.5, 4 => 8.5],
            'pu' => [1 => 7.5, 2 => 8.8, 3 => 7.1, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Redação' => [
            'pai' => null,
            'pp' => [1 => 9.3, 2 => 9.7, 3 => 6.5, 4 => 9.0],
            'pu' => [1 => 8.6, 2 => 7.0, 3 => 8.1, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Literatura' => [
            'pai' => null,
            'pp' => [1 => 9.0, 2 => 9.5, 3 => 8.0, 4 => 10.0],
            'pu' => [1 => 7.5, 2 => 8.8, 3 => 7.1, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Arte' => [
            'pai' => null,
            'pp' => [1 => 10.0, 2 => 6.5, 3 => 10.0, 4 => 8.0],
            'pu' => [1 => 7.5, 2 => 8.8, 3 => 7.1, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'História 1' => [
            'pai' => 'História',
            'pp' => [1 => 5.0, 2 => 5.0, 3 => 4.0, 4 => null],
            'pu' => [1 => 4.1, 2 => 4.6, 3 => 4.8, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'História 2' => [
            'pai' => 'História',
            'pp' => [1 => 5.0, 2 => 5.0, 3 => 4.0, 4 => 2.0],
            'pu' => [1 => 4.0, 2 => 4.5, 3 => 4.8, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Geografia' => [
            'pai' => null,
            'pp' => [1 => 9.0, 2 => 10.0, 3 => 10.0, 4 => 8.5],
            'pu' => [1 => 8.1, 2 => 9.1, 3 => 9.6, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 3, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Sociologia' => [
            'pai' => null,
            'pp' => [1 => 8.5, 2 => 8.0, 3 => 9.0, 4 => 6.5],
            'pu' => [1 => 8.1, 2 => 9.1, 3 => 9.6, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Filosofia' => [
            'pai' => null,
            'pp' => [1 => 8.5, 2 => 6.0, 3 => 9.5, 4 => null],
            'pu' => [1 => 8.1, 2 => 9.1, 3 => 9.6, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Matemática 1' => [
            'pai' => 'Matemática',
            'pp' => [1 => 3.0, 2 => 3.0, 3 => 4.0, 4 => null],
            'pu' => [1 => 1.7, 2 => 2.4, 3 => 3.1, 4 => null],
            'pe' => [1 => 0.5, 2 => 0.5, 3 => 0.5, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
            'rec_s1' => 3.5,
            'rec_s2' => null,
        ],
        'Matemática 2' => [
            'pai' => 'Matemática',
            'pp' => [1 => 2.1, 2 => 0.5, 3 => 4.9, 4 => null],
            'pu' => [1 => 1.7, 2 => 2.4, 3 => 3.1, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Física 1' => [
            'pai' => 'Física',
            'pp' => [1 => 2.0, 2 => 4.5, 3 => 4.5, 4 => null],
            'pu' => [1 => 2.8, 2 => 3.3, 3 => 3.7, 4 => null],
            'pe' => [1 => 0.5, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Física 2' => [
            'pai' => 'Física',
            'pp' => [1 => 2.5, 2 => 2.0, 3 => 3.5, 4 => null],
            'pu' => [1 => 2.8, 2 => 3.3, 3 => 3.7, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Química 1' => [
            'pai' => 'Química',
            'pp' => [1 => 4.0, 2 => 5.0, 3 => 5.5, 4 => null],
            'pu' => [1 => 3.5, 2 => 4.0, 3 => 4.2, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Química 2' => [
            'pai' => 'Química',
            'pp' => [1 => 3.5, 2 => 4.5, 3 => 4.0, 4 => null],
            'pu' => [1 => 3.5, 2 => 4.0, 3 => 4.2, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Biologia 1' => [
            'pai' => 'Biologia',
            'pp' => [1 => 2.0, 2 => 3.5, 3 => 4.0, 4 => null],
            'pu' => [1 => 2.8, 2 => 3.3, 3 => 3.7, 4 => null],
            'pe' => [1 => 0.5, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Biologia 2' => [
            'pai' => 'Biologia',
            'pp' => [1 => 4.0, 2 => 3.0, 3 => 5.0, 4 => null],
            'pu' => [1 => 2.8, 2 => 3.3, 3 => 3.7, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Língua Inglesa' => [
            'pai' => null,
            'pp' => [1 => 8.0, 2 => 7.5, 3 => 8.0, 4 => 7.0],
            'pu' => [1 => 7.5, 2 => 8.0, 3 => 7.5, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
        'Educação Física' => [
            'pai' => null,
            'pp' => [1 => 9.0, 2 => 9.0, 3 => 9.0, 4 => 9.0],
            'pu' => [1 => 9.0, 2 => 9.0, 3 => 9.0, 4 => null],
            'pe' => [1 => null, 2 => null, 3 => null, 4 => null],
            'fal' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
            'rec_s1' => null,
            'rec_s2' => null,
        ],
    ];

    $ordemPais = ['História', 'Matemática', 'Física', 'Química', 'Biologia'];
    $out = [];
    $inseridos = [];

    $push = static function (string $nome, array $dados, bool $indent = false) use (&$out, &$inseridos): void {
        if (isset($inseridos[$nome])) {
            return;
        }
        $out[] = [
            'nome' => $indent ? ('..' . $nome) : $nome,
            'pai' => $dados['pai'] ?? null,
            'pp' => $dados['pp'],
            'pu' => $dados['pu'],
            'pe' => $dados['pe'],
            'fal' => $dados['fal'],
            'rec_s1' => $dados['rec_s1'] ?? null,
            'rec_s2' => $dados['rec_s2'] ?? null,
            'area' => !$indent && ($dados['pai'] ?? null) === null && in_array($nome, ['História', 'Matemática', 'Física', 'Química', 'Biologia'], true),
        ];
        $inseridos[$nome] = true;
    };

    foreach (['Língua Portuguesa', 'Redação', 'Literatura', 'Arte'] as $n) {
        $push($n, $filhos[$n]);
    }
    foreach ($ordemPais as $pai) {
        $filhosDoPai = [];
        foreach ($filhos as $nome => $dados) {
            if (($dados['pai'] ?? null) === $pai) {
                $filhosDoPai[$nome] = $dados;
            }
        }
        // Linha-mãe: só MU/FAL agregados (como no print).
        $pp = [1 => null, 2 => null, 3 => null, 4 => null];
        $pu = [1 => null, 2 => null, 3 => null, 4 => null];
        $pe = [1 => null, 2 => null, 3 => null, 4 => null];
        $fal = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $muPai = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        $tem = [1 => false, 2 => false, 3 => false, 4 => false];
        foreach ($filhosDoPai as $dados) {
            foreach ([1, 2, 3, 4] as $b) {
                $m = mu($dados['pp'][$b] ?? null, $dados['pu'][$b] ?? null, $dados['pe'][$b] ?? null);
                if ($m !== null) {
                    $muPai[$b] += $m;
                    $tem[$b] = true;
                }
                $fal[$b] += (int) ($dados['fal'][$b] ?? 0);
            }
        }
        // Guarda MU agregada em pe[b] com marcador especial via 'agregado'
        $push($pai, [
            'pai' => null,
            'pp' => $pp,
            'pu' => $pu,
            'pe' => $pe,
            'fal' => $fal,
            'rec_s1' => null,
            'rec_s2' => null,
            'mu_agregado' => [
                1 => $tem[1] ? round($muPai[1], 1) : null,
                2 => $tem[2] ? round($muPai[2], 1) : null,
                3 => $tem[3] ? round($muPai[3], 1) : null,
                4 => $tem[4] ? round($muPai[4], 1) : null,
            ],
        ]);
        // Corrige último item com mu_agregado
        $out[count($out) - 1]['mu_agregado'] = [
            1 => $tem[1] ? round($muPai[1], 1) : null,
            2 => $tem[2] ? round($muPai[2], 1) : null,
            3 => $tem[3] ? round($muPai[3], 1) : null,
            4 => $tem[4] ? round($muPai[4], 1) : null,
        ];
        $out[count($out) - 1]['area'] = true;
        foreach ($filhosDoPai as $nome => $dados) {
            $push($nome, $dados, true);
        }
        if ($pai === 'História') {
            foreach (['Geografia', 'Sociologia', 'Filosofia'] as $n) {
                $push($n, $filhos[$n]);
            }
        }
    }
    foreach (['Língua Inglesa', 'Educação Física'] as $n) {
        $push($n, $filhos[$n]);
    }

    return $out;
}

function cel(?float $v, bool $bold = false): string
{
    $t = fmt($v);
    if ($bold && $v !== null) {
        return '<b>' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</b>';
    }
    return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
}

function celInt(int $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function htmlQuadroSantaMaria(array $linhas): string
{
    $thBim = static function (string $rotulo): string {
        return '<th colspan="6" style="border:1px solid #111;background:#f3f4f6;font-size:7pt;padding:2px;">'
            . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '</th>';
    };
    $thSem = static function (string $rotulo): string {
        return '<th colspan="2" style="border:1px solid #111;background:#f3f4f6;font-size:7pt;padding:2px;">'
            . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '</th>';
    };
    $thSub = static function (string $t): string {
        return '<th style="border:1px solid #111;background:#eef0f3;font-size:6.5pt;padding:1px 2px;">'
            . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</th>';
    };

    $html = '<table class="sm24-boletim" width="100%" style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:7pt;">'
        . '<thead>'
        . '<tr>'
        . '<th rowspan="2" style="border:1px solid #111;background:#f3f4f6;font-size:7pt;width:14%;">COMPONENTES CURRICULARES</th>'
        . $thBim('I BIMESTRE') . $thBim('II BIMESTRE') . $thSem('1º SEMESTRE')
        . $thBim('III BIMESTRE') . $thBim('IV BIMESTRE') . $thSem('2º SEMESTRE')
        . '<th colspan="4" style="border:1px solid #111;background:#f3f4f6;font-size:7pt;">RESULTADO FINAL</th>'
        . '</tr><tr>';
    foreach ([1, 2] as $_) {
        foreach (['PP', 'PU', 'CH', 'PE', 'MU', 'FAL'] as $c) {
            $html .= $thSub($c);
        }
    }
    $html .= $thSub('REC') . $thSub('MS');
    foreach ([3, 4] as $_) {
        foreach (['PP', 'PU', 'CH', 'PE', 'MU', 'FAL'] as $c) {
            $html .= $thSub($c);
        }
    }
    $html .= $thSub('REC') . $thSub('MS');
    foreach (['MA', 'PF', 'MF', 'Situação'] as $c) {
        $html .= $thSub($c);
    }
    $html .= '</tr></thead><tbody>';

    foreach ($linhas as $row) {
        $nome = (string) $row['nome'];
        $ehArea = !empty($row['area']);
        $styleNome = $ehArea
            ? 'font-weight:bold;border:1px solid #111;padding:1px 3px;background:#fafafa;'
            : 'border:1px solid #111;padding:1px 3px;';
        $html .= '<tr><td style="' . $styleNome . '">' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</td>';

        $mus = [];
        foreach ([1, 2, 3, 4] as $b) {
            if ($ehArea) {
                $m = $row['mu_agregado'][$b] ?? null;
                $html .= '<td style="border:1px solid #111;text-align:center;">—</td>'
                    . '<td style="border:1px solid #111;text-align:center;">—</td>'
                    . '<td style="border:1px solid #111;text-align:center;">—</td>'
                    . '<td style="border:1px solid #111;text-align:center;">—</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . cel(is_numeric($m) ? (float) $m : null, true) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . celInt((int) ($row['fal'][$b] ?? 0)) . '</td>';
            } else {
                $pp = $row['pp'][$b] ?? null;
                $pu = $row['pu'][$b] ?? null;
                $pe = $row['pe'][$b] ?? null;
                $m = mu(
                    is_numeric($pp) ? (float) $pp : null,
                    is_numeric($pu) ? (float) $pu : null,
                    is_numeric($pe) ? (float) $pe : null
                );
                $mus[$b] = $m;
                $html .= '<td style="border:1px solid #111;text-align:center;">' . cel(is_numeric($pp) ? (float) $pp : null) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . cel(is_numeric($pu) ? (float) $pu : null) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">—</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . cel(is_numeric($pe) ? (float) $pe : null) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . cel($m, true) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . celInt((int) ($row['fal'][$b] ?? 0)) . '</td>';
            }
            if ($b === 2) {
                $rec = $row['rec_s1'] ?? null;
                $msVals = [];
                if ($ehArea) {
                    if (isset($row['mu_agregado'][1]) && is_numeric($row['mu_agregado'][1])) {
                        $msVals[] = (float) $row['mu_agregado'][1];
                    }
                    if (isset($row['mu_agregado'][2]) && is_numeric($row['mu_agregado'][2])) {
                        $msVals[] = (float) $row['mu_agregado'][2];
                    }
                } else {
                    if (($mus[1] ?? null) !== null) {
                        $msVals[] = $mus[1];
                    }
                    if (($mus[2] ?? null) !== null) {
                        $msVals[] = $mus[2];
                    }
                }
                $ms = $msVals !== [] ? round(array_sum($msVals) / count($msVals), 1) : null;
                if ($rec !== null && $ms !== null) {
                    $ms = max($ms, (float) $rec);
                }
                $html .= '<td style="border:1px solid #111;text-align:center;">' . cel(is_numeric($rec) ? (float) $rec : null) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . cel($ms, true) . '</td>';
            }
            if ($b === 4) {
                $rec = $row['rec_s2'] ?? null;
                $msVals = [];
                if ($ehArea) {
                    if (isset($row['mu_agregado'][3]) && is_numeric($row['mu_agregado'][3])) {
                        $msVals[] = (float) $row['mu_agregado'][3];
                    }
                    if (isset($row['mu_agregado'][4]) && is_numeric($row['mu_agregado'][4])) {
                        $msVals[] = (float) $row['mu_agregado'][4];
                    }
                } else {
                    if (($mus[3] ?? null) !== null) {
                        $msVals[] = $mus[3];
                    }
                    if (($mus[4] ?? null) !== null) {
                        $msVals[] = $mus[4];
                    }
                }
                $ms = $msVals !== [] ? round(array_sum($msVals) / count($msVals), 1) : null;
                if ($rec !== null && $ms !== null) {
                    $ms = max($ms, (float) $rec);
                }
                $html .= '<td style="border:1px solid #111;text-align:center;">' . cel(is_numeric($rec) ? (float) $rec : null) . '</td>'
                    . '<td style="border:1px solid #111;text-align:center;">' . cel($ms, true) . '</td>';
            }
        }
        $html .= '<td style="border:1px solid #111;text-align:center;">—</td>'
            . '<td style="border:1px solid #111;text-align:center;">—</td>'
            . '<td style="border:1px solid #111;text-align:center;">—</td>'
            . '<td style="border:1px solid #111;text-align:center;font-size:6.5pt;">Cursando</td>';
        $html .= '</tr>';
    }

    return $html . '</tbody></table>';
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

try {
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    Database::setCurrentInstance(Database::createFromPdo($pdo, [
        'host' => $host,
        'port' => $port,
        'name' => TENANT_DB,
        'user' => $dbUser,
        'pass' => $dbPass,
    ]));
} catch (Throwable $e) {
    fail('Não conectou em ' . TENANT_DB . '. ' . $e->getMessage());
}

$db = Database::getInstance();
println('== Layout documento + gaps Santa Maria 2024 ==');

$regra = $db->fetch(
    "SELECT id FROM regras_academicas
      WHERE ano_letivo = :a AND nome = :n AND ativo = 1
      ORDER BY id ASC LIMIT 1",
    ['a' => ANO, 'n' => 'Aprovação Santa Maria 2024']
);
if (!$regra) {
    fail('Regra de aprovação Santa Maria 2024 não encontrada. Rode configurar_demonstrativo_boletim_sm24_2024.php');
}
$regraId = (int) $regra['id'];
println('  regra #' . $regraId);

// Hierarquia: Redação/Literatura sob Língua Portuguesa oficial (#31).
$lp = $db->fetch("SELECT id FROM materias WHERE codigo = 'LPO' OR nome = 'Língua Portuguesa' ORDER BY id ASC LIMIT 1");
$lpId = (int) ($lp['id'] ?? 0);
if ($lpId > 0) {
    $db->query(
        "UPDATE materias SET pai_id = :p
          WHERE ativo = 1 AND nome IN ('Redação','Literatura') AND (pai_id IS NULL OR pai_id <> :p2)",
        ['p' => $lpId, 'p2' => $lpId]
    );
    println('  componentes: Redação/Literatura → LP #' . $lpId);
}

// Eventos faltantes: REC 2º semestre (82) e Prova Final (90).
$turma = $db->fetch(
    'SELECT id FROM turmas WHERE nome = :n AND ano_letivo = :a LIMIT 1',
    ['n' => TURMA_NOME, 'a' => ANO]
);
$turmaId = (int) ($turma['id'] ?? 0);
$admin = $db->fetch("SELECT id FROM usuarios WHERE email = 'admin@educa.local' LIMIT 1");
$adminId = (int) ($admin['id'] ?? 0);
$eventos = new EventoAvaliativoService();

$garantirEvento = static function (
    string $nome,
    int $bimestre,
    string $sigla,
    string $data
) use ($db, $eventos, $turmaId, $adminId): void {
    $exist = $db->fetch(
        'SELECT id FROM eventos_avaliativos
          WHERE deleted_at IS NULL AND ano_letivo = :a AND bimestre = :b AND nome = :n LIMIT 1',
        ['a' => ANO, 'b' => $bimestre, 'n' => $nome]
    );
    if ($exist) {
        println('  evento ' . $sigla . ' B' . $bimestre . ' já existe (#' . (int) $exist['id'] . ')');
        return;
    }
    if ($turmaId <= 0 || $adminId <= 0) {
        println('  aviso: sem turma/admin para criar ' . $nome);
        return;
    }
    $res = $eventos->criar([
        'nome' => $nome,
        'descricao' => $nome . ' — layout Santa Maria.',
        'ano_letivo' => ANO,
        'bimestre' => $bimestre,
        'ativo' => '1',
        'canal_origem' => 'offline',
        'modo_offline' => 'lancamento_direto',
        'configuracao_nota' => 'coordenacao_calcula',
        'tipo_prova' => 'original',
        'escala_max' => '10',
        'incluir_media' => '0',
        'sigla' => $sigla,
        'liberar_nota_aluno' => '1',
        'turmas' => [$turmaId],
        'pares_padrao' => [],
        'subeventos' => [['titulo' => $nome, 'data' => $data]],
    ], $adminId);
    if (empty($res['success'])) {
        println('  aviso criar ' . $nome . ': ' . (string) ($res['error'] ?? 'falha'));
        return;
    }
    println('  evento ' . $sigla . ' B' . $bimestre . ' criado (#' . (int) ($res['id'] ?? 0) . ')');
};

$garantirEvento(PREFIXO . ' Recuperação 2º semestre', 82, 'REC', ANO . '-12-09');
$garantirEvento(PREFIXO . ' Prova Final', RecuperacaoConfig::CODIGO_FINAL, 'PF', ANO . '-12-16');

// Modelo de documento (layout idêntico ao print).
$svc = new ModeloDocumentoService();
if (!$svc->schemaReady()) {
    fail('Tabela secretaria_modelos_documentos indisponível.');
}

$linhas = linhasBoletim();
$quadro = htmlQuadroSantaMaria($linhas);

$identHtml = '<table width="100%" style="width:100%;border-collapse:collapse;font-size:8pt;margin:0 0 6px;">'
    . '<tr>'
    . '<td style="width:55%;vertical-align:top;">'
    . '<div style="font-weight:bold;font-size:11pt;">ESCOLA SANTA MARIA LTDA</div>'
    . '<div style="font-size:8pt;">CNPJ: 47.768.602/0001-57</div>'
    . '<div style="font-size:8pt;margin-top:4px;">BOLETIM DE DESEMPENHO ACADÊMICO</div>'
    . '</td>'
    . '<td style="width:45%;vertical-align:top;font-size:8pt;">'
    . '<div><b>Aluno(a):</b> {{aluno_nome}}</div>'
    . '<div><b>RA:</b> {{aluno_ra}} &nbsp; <b>Turma/Série/Turno:</b> {{turma_nome}} — {{serie}} — {{turno}}</div>'
    . '<div><b>Curso:</b> {{etapa}} &nbsp; <b>Período Letivo:</b> {{ano_letivo}}</div>'
    . '<div><b>Data de Emissão:</b> {{data_hoje}}</div>'
    . '</td>'
    . '</tr></table>';

$legenda = '<p style="font-size:6.5pt;margin:6px 0 2px;line-height:1.35;">'
    . '<b>Legenda:</b> PP — Prova Parcial | PU — Prova de Unidade | PR — Projetos | CH — 2ª Chamada | '
    . 'PE — Ponto Extra | MU — Média da Unidade | FAL — Faltas | REC — Recuperação | '
    . 'MS — Média do Semestre | MA — Média Anual | PF — Prova Final | MF — Média Final'
    . '</p>'
    . '<p style="font-size:7pt;margin:0;"><b>Observação:</b> O estudante que não atingir média semestral 7,0 (sete) '
    . 'deverá realizar a recuperação semestral. MU = ((PP + PU) / 2) + PE.</p>';

$est = ModeloDocumentoService::estruturaVazia('a4', 'paisagem', 6);
$est['page']['margin'] = ['top' => 6, 'right' => 6, 'bottom' => 6, 'left' => 6];

$cab = ModeloDocumentoService::secaoPadrao([100], 'header');
$cab['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('html', ['html' => $identHtml]);
$est['header']['sections'] = [$cab];

$body = ModeloDocumentoService::secaoPadrao([100], 'body');
$body['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
    'html',
    ['html' => $quadro],
    ['fontSize' => 7]
);
$obs = ModeloDocumentoService::secaoPadrao([100], 'body');
$obs['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('html', ['html' => $legenda]);
$est['body']['sections'] = [$body, $obs];

$ass = ModeloDocumentoService::secaoPadrao([50, 50], 'footer');
$ass['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
    'texto_rico',
    ['html' => '<p style="text-align:center;margin:18px 0 0;">________________________</p>'
        . '<p style="text-align:center;margin:4px 0 0;font-size:8pt;">Secretaria</p>'],
    ['textAlign' => 'center']
);
$ass['columns'][1]['elements'][] = ModeloDocumentoService::elementoEstrutura(
    'texto_rico',
    ['html' => '<p style="text-align:center;margin:18px 0 0;">________________________</p>'
        . '<p style="text-align:center;margin:4px 0 0;font-size:8pt;">Direção</p>'],
    ['textAlign' => 'center']
);
$est['footer']['sections'] = [$ass];

$existModelo = $db->fetch(
    'SELECT id FROM secretaria_modelos_documentos WHERE codigo = :c LIMIT 1',
    ['c' => CODIGO_MODELO]
);

$payload = [
    'codigo' => CODIGO_MODELO,
    'nome' => 'Boletim Santa Maria — layout do print',
    'descricao' => 'Layout paisagem idêntico ao boletim Santa Maria (PP/PU/CH/PE/MU/FAL, semestres e final).',
    'orientacao' => 'paisagem',
    'formato_papel' => 'a4',
    'margem_mm' => 6,
    'ativo' => 1,
    'usar_layout_padrao' => 0,
    'estrutura_json' => json_encode($est, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'corpo_html' => $identHtml . $quadro . $legenda,
    'cabecalho_html' => '',
    'rodape_html' => '',
];

if ($existModelo) {
    $modeloId = (int) $existModelo['id'];
    $svc->salvar($payload, $modeloId);
    println('  modelo documento atualizado #' . $modeloId);
} else {
    $modeloId = $svc->salvar($payload, null);
    println('  modelo documento criado #' . $modeloId);
}

// Aponta o tipo oficial "boletim" para este layout, se a tabela existir.
if ($db->tableExists('resultado_documento_layouts')) {
    $db->query(
        'INSERT INTO resultado_documento_layouts (tipo, modelo_codigo)
         VALUES (\'boletim\', :c)
         ON DUPLICATE KEY UPDATE modelo_codigo = VALUES(modelo_codigo)',
        ['c' => CODIGO_MODELO]
    );
    println('  resultado_documento_layouts.boletim → ' . CODIGO_MODELO);
}

println('');
println('Pronto.');
println('  Editor:  http://educa.localhost/admin/modelos-documentos/' . $modeloId . '/editor');
println('  Preview: http://educa.localhost/admin/modelos-documentos/' . $modeloId . '/preview');
println('  Eventos: http://educa.localhost/admin/eventos-avaliativos?ano=' . ANO . '&regra=' . $regraId);
println('  Modelos: http://educa.localhost/admin/boletins');
