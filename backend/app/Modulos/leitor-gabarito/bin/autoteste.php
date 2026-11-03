<?php

/**
 * Gera uma folha, inclina, salva em JPEG e confere se a leitura devolve o mesmo código e as mesmas letras.
 * Uso: php backend/app/Modulos/leitor-gabarito/bin/autoteste.php
 */

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/Services/GeometriaFolha.php';
require_once dirname(__DIR__) . '/Services/CodigoQrFolha.php';
require_once dirname(__DIR__) . '/Services/DesenhistaFolha.php';
require_once dirname(__DIR__) . '/Services/AlinhamentoFolha.php';
require_once dirname(__DIR__) . '/Services/LeitorFolha.php';

use App\Modulos\LeitorGabarito\Services\DesenhistaFolha;
use App\Modulos\LeitorGabarito\Services\GeometriaFolha;
use App\Modulos\LeitorGabarito\Services\LeitorFolha;

$folhaId = 42;
if (GeometriaFolha::idDosBits(GeometriaFolha::bitsDoCodigo($folhaId)) !== $folhaId) {
    fwrite(STDERR, "Código da folha não fechou a ida e a volta.\n");
    exit(1);
}
$bitsQuebrados = GeometriaFolha::bitsDoCodigo($folhaId);
$bitsQuebrados[3] = $bitsQuebrados[3] === 1 ? 0 : 1;
if (GeometriaFolha::idDosBits($bitsQuebrados) !== null) {
    fwrite(STDERR, "Código inválido foi aceito.\n");
    exit(1);
}

$questoes = [];
for ($q = 1; $q <= 8; $q++) {
    $alts = [];
    foreach (['A', 'B', 'C', 'D'] as $i => $letra) {
        $alts[] = ['letra' => $letra, 'id' => ($q * 10) + $i];
    }
    $questoes[] = ['questao_id' => $q, 'valor' => 1.25, 'alternativas' => $alts];
}

$desenhista = new DesenhistaFolha();
$alunoId = 15;
$folha = $desenhista->criarImagem($questoes, $folhaId, $alunoId, 'José Álvares', 'Prova de Matemática');
$bolhas = GeometriaFolha::bolhas($questoes);

$preencher = [
    1 => ['A'],
    2 => ['C'],
    4 => ['A', 'B'],
    5 => ['D'],
];
$preto = imagecolorallocate($folha, 0, 0, 0);
foreach ($bolhas as $questao) {
    $letras = $preencher[$questao['numero']] ?? [];
    foreach ($questao['bolhas'] as $bolha) {
        if (!in_array($bolha['letra'], $letras, true)) {
            continue;
        }
        imagefilledellipse($folha, (int) $bolha['cx'], (int) $bolha['cy'], 22, 22, $preto);
    }
}

$margem = 90;
$canvas = imagecreatetruecolor(imagesx($folha) + ($margem * 2), imagesy($folha) + ($margem * 2));
$branco = imagecolorallocate($canvas, 255, 255, 255);
imagefill($canvas, 0, 0, $branco);
imagecopy($canvas, $folha, $margem, $margem + 30, 0, 0, imagesx($folha), imagesy($folha));
$rotacionada = imagerotate($canvas, 3.0, $branco);
if (!$rotacionada instanceof GdImage) {
    fwrite(STDERR, "Falha ao girar a folha de teste.\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/leitor-gabarito-autoteste.jpg';
imagejpeg($rotacionada, $tmp, 80);

$inicio = microtime(true);
$leitor = new LeitorFolha();
$alinhamento = $leitor->preparar($tmp);
$marcacoes = $leitor->lerMarcacoes($alinhamento, $questoes);
$segundos = round(microtime(true) - $inicio, 2);

$esperado = [1 => 'A', 2 => 'C', 3 => null, 4 => null, 5 => 'D', 6 => null, 7 => null, 8 => null];
$situacao = [1 => 'marcada', 2 => 'marcada', 3 => 'vazia', 4 => 'dupla', 5 => 'marcada', 6 => 'vazia', 7 => 'vazia', 8 => 'vazia'];
$falhas = [];
if ($alinhamento->folhaId !== $folhaId) {
    $falhas[] = 'código leu ' . var_export($alinhamento->folhaId, true) . " em vez de {$folhaId}";
}
if ($alinhamento->alunoQr !== $alunoId) {
    $falhas[] = 'QR leu aluno ' . var_export($alinhamento->alunoQr, true) . " em vez de {$alunoId}";
}
foreach ($marcacoes as $linha) {
    $n = (int) $linha['numero'];
    if (($esperado[$n] ?? null) !== $linha['letra']) {
        $falhas[] = "questão {$n}: letra " . var_export($linha['letra'], true) . ' (esperado ' . var_export($esperado[$n], true) . ')';
    }
    if (($situacao[$n] ?? '') !== $linha['situacao']) {
        $falhas[] = "questão {$n}: situação {$linha['situacao']} (esperado {$situacao[$n]})";
    }
    if ($falhas !== [] && $n <= 5) {
        $falhas[] = 'preenchimentos q' . $n . ' ' . json_encode($linha['opcoes'], JSON_UNESCAPED_UNICODE);
    }
}

if ($falhas !== []) {
    fwrite(STDERR, "Autoteste falhou em {$segundos}s\n" . implode("\n", $falhas) . "\n");
    fwrite(STDERR, "Imagem: {$tmp}\n");
    exit(1);
}

echo "Autoteste ok em {$segundos}s (folha {$folhaId}, JPEG inclinado).\n";
@unlink($tmp);
exit(0);
