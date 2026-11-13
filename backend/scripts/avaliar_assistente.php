<?php
/**
 * Bateria de roteamento da Tudinha (motor v2): para cada frase real, confere se o
 * modelo escolhe a tool e os argumentos esperados. Não executa tools nem acessa banco.
 *
 * Uso (na raiz de backend/):
 *   php scripts/avaliar_assistente.php
 *   php scripts/avaliar_assistente.php --modelo=gpt-5.6-sol --filtro=oc- --meta=95
 *
 * Casos: tests/fixtures/assistente_frases.json
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Modulos\Provas\Services\Assistente\AssistenteEstadoConversa;
use App\Modulos\Provas\Services\Assistente\AssistenteFerramentas;
use App\Modulos\Provas\Services\Assistente\AssistenteMotorService;
use App\Modulos\Provas\Services\Assistente\AssistenteResolvedorAluno;

$opcoes = getopt('', ['modelo::', 'filtro::', 'meta::', 'verboso']);
$modelo = (string) ($opcoes['modelo'] ?? AssistenteMotorService::MODELO);
$filtro = (string) ($opcoes['filtro'] ?? '');
$meta = (float) ($opcoes['meta'] ?? 95);
$verboso = isset($opcoes['verboso']);

$fixture = json_decode((string) file_get_contents(dirname(__DIR__) . '/tests/fixtures/assistente_frases.json'), true);
if (!is_array($fixture) || empty($fixture['casos'])) {
    fwrite(STDERR, "Fixture inválida.\n");
    exit(2);
}

date_default_timezone_set('America/Sao_Paulo');
$catalogos = 'Eventos Avaliativos desta escola: [{"sigla":"PB","nome":"Prova Bimestral"},{"sigla":"PO","nome":"Prova Objetiva"}]'
    . "\nHoje (America/Sao_Paulo): " . date('Y-m-d');
$system = AssistenteFerramentas::promptSistema($catalogos);
$definicoes = AssistenteFerramentas::definicoes();
$openai = new \App\Infra\Services\OpenAIService();

$total = 0;
$acertos = 0;
$falhas = [];
$porGrupo = [];
$inicio = microtime(true);

foreach ($fixture['casos'] as $caso) {
    $id = (string) $caso['id'];
    if ($filtro !== '' && !str_starts_with($id, $filtro)) {
        continue;
    }
    $total++;
    $grupo = explode('-', $id)[0];
    $porGrupo[$grupo] ??= ['total' => 0, 'ok' => 0];
    $porGrupo[$grupo]['total']++;

    $estadoBruto = [];
    if (!empty($caso['estado'])) {
        $estadoBruto = $fixture['estados'][$caso['estado']] ?? [];
    }
    $estado = AssistenteEstadoConversa::normalizar($estadoBruto);
    $mensagens = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'system', 'content' => AssistenteEstadoConversa::paraPrompt($estado)],
    ];
    foreach ((array) ($caso['historico'] ?? []) as $h) {
        $mensagens[] = ['role' => (string) $h['role'], 'content' => (string) $h['content']];
    }
    $mensagens[] = ['role' => 'user', 'content' => (string) $caso['frase']];

    try {
        $resp = $openai->chatCompletionComFerramentas($mensagens, $definicoes, $modelo, 1500);
    } catch (\Throwable $e) {
        $falhas[] = [$id, $caso['frase'], 'ERRO: ' . $e->getMessage()];
        continue;
    }

    $chamada = $resp['tool_calls'][0] ?? null;
    $toolObtida = $chamada['nome'] ?? null;
    $argsObtidos = AssistenteMotorService::limparArgs($chamada['args'] ?? []);
    $esperadas = $caso['tool'] === null ? [null] : (array) $caso['tool'];

    $motivo = null;
    if (!in_array($toolObtida, $esperadas, true)) {
        $motivo = 'tool ' . json_encode($toolObtida) . ' (esperado ' . json_encode($caso['tool']) . ')';
    } else {
        foreach ((array) ($caso['args'] ?? []) as $chave => $esperado) {
            if (!argBate($argsObtidos[$chave] ?? null, $esperado)) {
                $motivo = "arg {$chave}=" . json_encode($argsObtidos[$chave] ?? null, JSON_UNESCAPED_UNICODE)
                    . ' (esperado ' . json_encode($esperado, JSON_UNESCAPED_UNICODE) . ')';
                break;
            }
        }
    }

    if ($motivo === null) {
        $acertos++;
        $porGrupo[$grupo]['ok']++;
        if ($verboso) {
            echo "[OK]   {$id} {$toolObtida} " . json_encode($argsObtidos, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo '.';
        }
    } else {
        $falhas[] = [$id, $caso['frase'], $motivo . ' | args=' . json_encode($argsObtidos, JSON_UNESCAPED_UNICODE)];
        echo $verboso ? "[FAIL] {$id} {$motivo}\n" : 'F';
    }
}

$taxa = $total > 0 ? round($acertos * 100 / $total, 1) : 0.0;
echo "\n\nModelo: {$modelo}\n";
echo "Acerto: {$acertos}/{$total} ({$taxa}%) em " . round(microtime(true) - $inicio, 1) . "s\n\n";
foreach ($porGrupo as $g => $p) {
    printf("  %-4s %d/%d\n", $g, $p['ok'], $p['total']);
}
if ($falhas !== []) {
    echo "\nFalhas:\n";
    foreach ($falhas as [$id, $frase, $motivo]) {
        echo "  - {$id} «{$frase}»\n      {$motivo}\n";
    }
}
exit($taxa >= $meta ? 0 : 1);

/**
 * Texto: o obtido contém o esperado (sem acento/caixa). Bool/int: igualdade.
 *
 * @param mixed $obtido
 * @param mixed $esperado
 */
function argBate($obtido, $esperado): bool
{
    if (is_bool($esperado) || is_int($esperado)) {
        return $obtido === $esperado;
    }
    if (is_array($obtido)) {
        $obtido = implode(' | ', array_map('strval', $obtido));
    }
    if (!is_scalar($obtido)) {
        return false;
    }
    return str_contains(
        AssistenteResolvedorAluno::normalizar((string) $obtido),
        AssistenteResolvedorAluno::normalizar((string) $esperado)
    );
}
