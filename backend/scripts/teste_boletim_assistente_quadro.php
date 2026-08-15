<?php
/**
 * Testa o rascunho do quadro semanal (fábrica + IA conversando).
 *
 * Uso (container PHP, tenant Colag):
 *   php scripts/teste_boletim_assistente_quadro.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Services/BoletimAssistenteFerramentas.php';
require_once $basePath . '/app/Services/BoletimAssistenteService.php';
require_once $basePath . '/app/Services/MetricsService.php';

const TENANT_DB = 'educatudo_colag';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
$hostsOk = ['mysql', 'localhost', '127.0.0.1', '::1'];
if (!in_array($host, $hostsOk, true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

$dsn = "mysql:host={$host};port={$port};dbname=" . TENANT_DB . ';charset=utf8mb4';
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

$ferramentas = new BoletimAssistenteFerramentas();

println('== 1) Fábrica do quadro ==');
$rascunho = $ferramentas->montarRascunhoQuadroSemanal([
    'ano_letivo' => 2026,
    'bimestre' => 1,
    'so_semanas_com_evento' => true,
]);
$validado = $ferramentas->validarEEnriquecerRascunho($rascunho);
$comps = $validado['rascunho']['componentes'] ?? [];
$codigos = [];
foreach ($comps as $c) {
    $codigos[] = (string) ($c['codigo'] ?? '');
}
println('Componentes: ' . implode(', ', $codigos));
if (!empty($validado['erros'])) {
    println('Avisos/erros da fábrica: ' . implode(' | ', $validado['erros']));
}

$obrigatorios = ['media_sem', 'prova_bim', 'media_bim'];
foreach ($obrigatorios as $cod) {
    if (!in_array($cod, $codigos, true)) {
        fail("Fábrica não gerou o bloco obrigatório: {$cod}");
    }
}
$temSemana = false;
foreach ($comps as $c) {
    $sem = (int) (($c['config']['semana'] ?? 0));
    if ($sem >= 1 && $sem <= 8 && !empty($c['materia_unica']) && !empty($c['usar_percentual'])) {
        $temSemana = true;
        break;
    }
}
if (!$temSemana) {
    fail('Fábrica não gerou coluna de semana com materia_unica + usar_percentual.');
}
$mediaSem = null;
foreach ($comps as $c) {
    if (($c['codigo'] ?? '') === 'media_sem') {
        $mediaSem = $c;
        break;
    }
}
$ag = $mediaSem['config']['agregar_nq'] ?? [];
if (!is_array($ag) || $ag === []) {
    fail('media_sem precisa de config.agregar_nq.');
}
println('OK fábrica (' . count($comps) . ' blocos, agregar_nq=' . implode(',', $ag) . ')');

println('');
println('== 2) IA conversando (montar o quadro) ==');
$assistente = new BoletimAssistenteService($ferramentas);
$mensagem = <<<'TXT'
Monte o quadro inteiro de notas semanais deste bimestre.

Quero as colunas S1 a S8 com N e Q (acertos e questões), média semanal somando os acertos das semanas da matéria, prova bimestral como nota inteira, ENAC e trabalho se existirem no catálogo, participação se existir, e recuperação puxando o evento de prova para fechar a média final com max(média bim, rec).

Tem grupo de matérias (bloco A semanas ímpares, bloco B pares). A mesma matéria com professor diferente deve juntar na mesma linha (matéria única).

Não invente tipo que não esteja no catálogo. Use os IDs reais.
TXT;

$ref = new ReflectionClass($assistente);
$prepM = $ref->getMethod('prepararContexto');
$prepM->setAccessible(true);
$prep = $prepM->invoke($assistente, $mensagem, [], null, null, null);
if (empty($prep['ok'])) {
    fail('Preparar contexto: ' . (string) ($prep['error'] ?? 'erro'));
}
$chamarM = $ref->getMethod('chamarModelo');
$chamarM->setAccessible(true);
try {
    $raw = $chamarM->invoke($assistente, $prep['mensagens'], $prep['system_json']);
} catch (Throwable $e) {
    fail('OpenAI: ' . $e->getMessage());
}

$montarM = $ref->getMethod('montarResultadoDeRespostaModelo');
$montarM->setAccessible(true);
$resp = $montarM->invoke($assistente, (string) ($raw['resposta'] ?? ''), (int) ($raw['tokens_usados'] ?? 0));

$msgIa = trim((string) ($resp['mensagem'] ?? ''));
println('Mensagem da IA: ' . ($msgIa !== '' ? $msgIa : '(vazia)'));
$rascunhoIa = $resp['rascunho'] ?? null;
if (!is_array($rascunhoIa) || empty($rascunhoIa['componentes'])) {
    fail('IA não devolveu rascunho com componentes. acao=' . (string) ($resp['acao'] ?? ''));
}

$codIa = [];
$semanasIa = [];
$materiaUnica = 0;
$provaBimPct = null;
$temAgregar = false;
$temRecMax = false;
foreach ($rascunhoIa['componentes'] as $c) {
    if (!is_array($c)) {
        continue;
    }
    $cod = (string) ($c['codigo'] ?? '');
    $codIa[] = $cod;
    $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
    $sem = (int) ($cfg['semana'] ?? $c['semana'] ?? 0);
    if ($sem >= 1 && $sem <= 8) {
        $semanasIa[] = $sem;
        if (!empty($c['materia_unica'])) {
            $materiaUnica++;
        }
        if (str_contains($cod, '-')) {
            fail("Código com hífen não permitido: {$cod}");
        }
    }
    if ($cod === 'prova_bim' || $cod === 'bimestral') {
        $provaBimPct = (int) ($c['usar_percentual'] ?? 1);
    }
    if (!empty($cfg['agregar_nq']) && is_array($cfg['agregar_nq'])) {
        $temAgregar = true;
    }
    $exp = (string) ($cfg['expressao'] ?? '');
    if (str_contains($exp, 'max(') && (str_contains($exp, 'rec') || str_contains($exp, 'media_bim'))) {
        $temRecMax = true;
    }
}

println('IA componentes: ' . implode(', ', $codIa));
println('Semanas: ' . implode(',', $semanasIa) . " · materia_unica em {$materiaUnica} coluna(s) de semana");

$falhas = [];
if (count($semanasIa) < 4) {
    $falhas[] = 'esperava pelo menos 4 semanas (S1–S8 do catálogo)';
}
if ($materiaUnica < 1) {
    $falhas[] = 'materia_unica=1 nas colunas de semana';
}
if ($provaBimPct !== 0 && $provaBimPct !== null) {
    $falhas[] = 'prova bimestral deveria ter usar_percentual=0 (nota inteira)';
}
if (!$temAgregar) {
    $falhas[] = 'media_sem deveria usar config.agregar_nq';
}

if ($falhas !== []) {
    println('ATENÇÃO — a IA montou o rascunho, mas falhou nestes pontos:');
    foreach ($falhas as $f) {
        println('  - ' . $f);
    }
    println(json_encode($rascunhoIa, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit(2);
}

if ($temRecMax) {
    println('OK recuperação na média final (max).');
}
println('OK IA montou o quadro.');
exit(0);
