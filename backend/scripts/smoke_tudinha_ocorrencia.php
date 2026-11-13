<?php
/**
 * Smoke: fluxo de ocorrência da Tudinha no tenant educa (sem HTTP/IA).
 * Uso: docker exec php_app_educatudo php /var/www/html/../tmp/smoke_tudinha_ocorrencia.php
 * (ajuste path se o volume montar a raiz do repo)
 */
declare(strict_types=1);

$root = dirname(__DIR__); // backend parent when run from host; in container use /var/www/html
require $root . '/backend/vendor/autoload.php';

use App\Core\Database;
use App\Painel\Admin\Services\AssistenteAcaoPropostaService;
use App\Modulos\Provas\Services\ProvasAlunoAssistenteService;

$pdo = new PDO(
    'mysql:host=mysql;port=3306;dbname=educatudo_educa;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
Database::setCurrentInstance(Database::createFromPdo($pdo, ['name' => 'educatudo_educa']));

$acoes = new AssistenteAcaoPropostaService();
$svc = new ProvasAlunoAssistenteService(null, null, null, null, null, $acoes);
$svc->definirContextoAcao(1, 0, ['id' => 1, 'tipo' => 'admin']);

function ok(string $label, bool $cond, string $extra = ''): void
{
    echo ($cond ? '[OK] ' : '[FAIL] ') . $label . ($extra !== '' ? " — $extra" : '') . PHP_EOL;
    if (!$cond) {
        exit(1);
    }
}

echo "=== Categorias ===\n";
$cats = $acoes->listarCategoriasOcorrencia();
ok('tem categorias', $cats !== [], 'count=' . count($cats));
foreach ($cats as $c) {
    echo "  - {$c['id']}: {$c['nome']}\n";
}

echo "\n=== Resolver Conversa → categoria real ===\n";
$ref = new ReflectionClass($acoes);
$m = $ref->getMethod('resolverCategoriaId');
$m->setAccessible(true);
$idConv = (int) $m->invoke($acoes, 'Conversa');
$idConvSala = (int) $m->invoke($acoes, 'Conversa na Sala de Aula');
ok('Conversa resolve id>0', $idConv > 0, "id=$idConv");
ok('Conversa na Sala resolve id>0', $idConvSala > 0, "id=$idConvSala");

echo "\n=== Proposta por RA 24254 + categoria Conversa + detalhe ===\n";
$r1 = $acoes->proporCriarOcorrencia([
    'ra' => '24254',
    'categoria' => 'Conversa',
    'titulo' => 'Conversa na sala',
    'detalhe' => 'A aluna conversou várias vezes e não parou quando o professor pediu.',
    'nivel_gravidade' => 'leve',
], 1, 0);
ok('proposta ok', !empty($r1['ok']), json_encode($r1['error'] ?? $r1['aviso'] ?? '', JSON_UNESCAPED_UNICODE));
ok('tem proposta_id', !empty($r1['proposta_id']));
ok('categoria_id preenchido', !empty($r1['payload']['categoria_id']), 'cat=' . ($r1['payload']['categoria_id'] ?? 'null'));
ok('categoria_nome preenchido', !empty($r1['payload']['categoria_nome']), 'nome=' . ($r1['payload']['categoria_nome'] ?? ''));

echo "\n=== Sem categoria → chips (não cria rascunho) ===\n";
$r2 = $acoes->proporCriarOcorrencia([
    'ra' => '24254',
    'titulo' => 'Ocorrência via assistente',
    'detalhe' => 'Aguardando',
    'nivel_gravidade' => 'leve',
], 1, 0);
ok('não cria sem categoria', empty($r2['ok']) && empty($r2['proposta_id']));
ok('devolve chips', !empty($r2['candidatos_categorias']), 'n=' . count($r2['candidatos_categorias'] ?? []));

echo "\n=== Por nome Ana Luiza Oliveira ===\n";
$r3 = $acoes->proporCriarOcorrencia([
    'aluno_nome' => 'Ana Luiza Oliveira De Souza',
    'categoria' => 'Comportamento / convivência',
    'titulo' => 'Conversa na sala',
    'detalhe' => 'Conversou durante a explicação.',
    'nivel_gravidade' => 'leve',
], 1, 0);
ok('proposta por nome ok', !empty($r3['ok']), json_encode($r3['error'] ?? $r3['aviso'] ?? $r3['candidatos'][0]['nome'] ?? '', JSON_UNESCAPED_UNICODE));

echo "\n=== Garantia server-side: 'quero fazer ocorrência da aluna Ana Luiza' ===\n";
$refSvc = new ReflectionClass($svc);
$mGarantir = $refSvc->getMethod('garantirPropostaEscrita');
$mGarantir->setAccessible(true);
$g = $mGarantir->invoke(
    $svc,
    'quero fazer uma ocorrência da aluna Ana Luiza Oliveira De Souza',
    [],
    null,
    null,
    '',
    null
);
ok('garantia retornou algo', is_array($g) && $g !== []);
$temChipsAluno = !empty($g['resultado']['candidatos']) || !empty($g['resultado']['alunos']);
$temChipsCat = !empty($g['resultado']['candidatos_categorias']);
$temProposta = !empty($g['resultado']['proposta_id']);
ok(
    'não inventa proposta vazia (só chips aluno/cat ou pedido de campo)',
    !$temProposta || !empty($g['resultado']['payload']['categoria_id']),
    'tool=' . ($g['tool'] ?? '-') . ' texto=' . mb_substr((string) ($g['texto'] ?? ''), 0, 80)
);
echo '  texto: ' . ($g['texto'] ?? '') . PHP_EOL;
echo '  chips_aluno=' . ($temChipsAluno ? '1' : '0') . ' chips_cat=' . ($temChipsCat ? '1' : '0') . ' proposta=' . ($temProposta ? '1' : '0') . PHP_EOL;

echo "\n=== Fluxo multi-turno simulado ===\n";
$hist = [
    ['role' => 'user', 'content' => 'quero criar uma ocorrência da aluna Ana Luiza Oliveira De Souza'],
    ['role' => 'assistant', 'content' => "Aluna confirmada. Escolha a **categoria** da ocorrência:\n\n[contexto_estruturado]\naluno_id=365 aluno_nome=\"Ana Luiza Oliveira De Souza\" ra=\"24254\" turma_nome=\"1A Santa Maria\"\naguardando_categoria=1 proposta_pendente=1 proposta_tipo=\"criar_ocorrencia\""],
];
$g2 = $mGarantir->invoke(
    $svc,
    'Conversa na Sala de Aula',
    $hist,
    'propor_criar_ocorrencia',
    ['candidatos_categorias' => $cats, 'ok' => false],
    '',
    null
);
echo '  após categoria: texto=' . mb_substr((string) ($g2['texto'] ?? ''), 0, 120) . PHP_EOL;
$hist2 = array_merge($hist, [
    ['role' => 'user', 'content' => 'Conversa na Sala de Aula'],
    ['role' => 'assistant', 'content' => 'Categoria definida. Agora descreva o que aconteceu.'],
]);
$g3 = $mGarantir->invoke(
    $svc,
    'A aluna conversou várias vezes; o professor pediu para parar e ela continuou.',
    $hist2,
    null,
    null,
    '',
    null
);
ok('após detalhe gera proposta', !empty($g3['resultado']['ok']) && !empty($g3['resultado']['proposta_id']), json_encode([
    'error' => $g3['resultado']['error'] ?? null,
    'aviso' => $g3['resultado']['aviso'] ?? null,
    'texto' => mb_substr((string) ($g3['texto'] ?? ''), 0, 100),
    'cat' => $g3['resultado']['payload']['categoria_id'] ?? null,
], JSON_UNESCAPED_UNICODE));

echo "\nTodos os checks passaram.\n";
