<?php
/**
 * Seed local: provas online já respondidas + notas lançadas para o aluno de teste.
 *
 * Uso (dentro do container PHP):
 *   php scripts/seed_local_provas_respondidas.php
 *
 * Destino: painel do aluno (Minhas Provas, resultado, desempenho e Notas/Boletins).
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

const TENANT_DB = 'educatudo_colag';
const ALUNO_NICKNAME = 'aluno.teste';
const PREFIXO_DEMO = 'Painel Demo — ';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function connectPdo(string $host, int $port, string $db, string $user, string $pass): PDO
{
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $pdo;
}

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute(['t' => $tabela, 'c' => $coluna]);
    return (int) $stmt->fetchColumn() > 0;
}

function buscarIdPorTitulo(PDO $pdo, string $sql, string $titulo): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$titulo]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function bancoQuestoes(): array
{
    return [
        44 => [ // Matemática
            ['Quanto é 15 × 8?', ['110', '120', '125', '130'], 1],
            ['Qual é a raiz quadrada de 81?', ['7', '8', '9', '10'], 2],
            ['Simplifique a fração 18/24.', ['2/3', '3/4', '4/5', '5/6'], 1],
            ['Quanto vale 2³ + 4²?', ['20', '24', '18', '16'], 1],
            ['Qual é 25% de 80?', ['15', '20', '25', '30'], 1],
        ],
        45 => [ // Português
            ['Qual é o sujeito em “Os alunos estudaram bastante”?', ['estudaram', 'Os alunos', 'bastante', 'os'], 1],
            ['Assinale o verbo no pretérito perfeito.', ['estuda', 'estudará', 'estudou', 'estudando'], 2],
            ['Qual palavra está acentuada corretamente?', ['ideia', 'pólo', 'útil', 'heroi'], 2],
            ['“Casa” é um substantivo:', ['abstrato', 'próprio', 'comum', 'coletivo'], 2],
            ['Qual é o antônimo de “escasso”?', ['raro', 'abundante', 'pouco', 'mínimo'], 1],
        ],
        46 => [ // História
            ['Em que século ocorreu a Independência do Brasil?', ['XVIII', 'XIX', 'XX', 'XVII'], 1],
            ['Quem proclamou a República no Brasil?', ['Dom Pedro II', 'Deodoro da Fonseca', 'Getúlio Vargas', 'Tiradentes'], 1],
            ['A Lei Áurea foi assinada em:', ['1822', '1888', '1889', '1500'], 1],
            ['Qual civilização construiu Machu Picchu?', ['Asteca', 'Maia', 'Inca', 'Olmeca'], 2],
            ['A Revolução Francesa começou em:', ['1776', '1789', '1815', '1848'], 1],
        ],
        47 => [ // Geografia
            ['Qual é o maior bioma brasileiro em área?', ['Cerrado', 'Amazônia', 'Mata Atlântica', 'Caatinga'], 1],
            ['O Equador atravessa qual região do Brasil?', ['Sul', 'Sudeste', 'Norte', 'Centro-Oeste'], 2],
            ['Qual é a capital do Amazonas?', ['Belém', 'Manaus', 'Macapá', 'Boa Vista'], 1],
            ['Planalto e planície são formas de:', ['clima', 'relevo', 'vegetação', 'hidrografia'], 1],
            ['O clima semiárido predomina na:', ['Amazônia', 'Caatinga', 'Pampa', 'Pantanal'], 1],
        ],
        48 => [ // Ciências
            ['Qual organela produz energia na célula?', ['núcleo', 'mitocôndria', 'vacúolo', 'ribossomo'], 1],
            ['A fotossíntese ocorre principalmente no:', ['caule', 'cloroplasto', 'raiz', 'fruto'], 1],
            ['A água é composta por:', ['H2O', 'CO2', 'O2', 'NaCl'], 0],
            ['Os mamíferos se caracterizam por:', ['ovos com casca', 'glândulas mamárias', 'branquias', 'exoesqueleto'], 1],
            ['Qual planeta é o terceiro a partir do Sol?', ['Vênus', 'Terra', 'Marte', 'Mercúrio'], 1],
        ],
    ];
}

function tipoIdPorChave(PDO $pdo, string $chave): int
{
    if (colunaExiste($pdo, 'provas_tipos_avaliacao', 'chave_quadro')) {
        $stmt = $pdo->prepare(
            'SELECT id FROM provas_tipos_avaliacao
             WHERE deleted_at IS NULL AND chave_quadro = ?
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([$chave]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
    }
    $like = [
        'semanal' => '%semanal%',
        'prova_bim' => '%bimestral%',
    ];
    $padrao = $like[$chave] ?? $chave;
    $stmt = $pdo->prepare(
        'SELECT id FROM provas_tipos_avaliacao
         WHERE deleted_at IS NULL AND LOWER(nome) LIKE LOWER(?)
         ORDER BY id ASC LIMIT 1'
    );
    $stmt->execute([$padrao]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function aplicarMetaQuadro(PDO $pdo, int $blocoId, array $quadro): void
{
    if ($quadro === [] || $blocoId <= 0) {
        return;
    }
    $sets = [];
    $params = [];
    if (colunaExiste($pdo, 'provas_blocos', 'semana')) {
        $s = (int) ($quadro['semana'] ?? 0);
        $sets[] = 'semana = ?';
        $params[] = ($s >= 1 && $s <= 8) ? $s : null;
    }
    if (colunaExiste($pdo, 'provas_blocos', 'bimestre')) {
        $b = (int) ($quadro['bimestre'] ?? 0);
        $sets[] = 'bimestre = ?';
        $params[] = ($b >= 1 && $b <= 4) ? $b : null;
    }
    if (colunaExiste($pdo, 'provas_blocos', 'tipo_avaliacao_id')) {
        $t = (int) ($quadro['tipo_avaliacao_id'] ?? 0);
        $sets[] = 'tipo_avaliacao_id = ?';
        $params[] = $t > 0 ? $t : null;
    }
    if (colunaExiste($pdo, 'provas_blocos', 'ano_letivo')) {
        $sets[] = 'ano_letivo = ?';
        $params[] = (int) ($quadro['ano_letivo'] ?? 2026);
    }
    if ($sets === []) {
        return;
    }
    $params[] = $blocoId;
    $pdo->prepare('UPDATE provas_blocos SET ' . implode(', ', $sets) . ' WHERE id = ?')
        ->execute($params);
}

function acertosQuadro(int $materiaId, int $semana, int $bimestre): int
{
    return 1 + (($materiaId + ($semana * 2) + ($bimestre * 3)) % 5);
}

function acertosBimestral(int $materiaId, int $bimestre): int
{
    return 2 + (($materiaId + $bimestre) % 4);
}

function garantirBlocoOnline(
    PDO $pdo,
    array $ctx,
    string $titulo,
    string $descricao,
    array $materias,
    array $acertosPorMateria,
    string $dataProva,
    int $diasJanela,
    array $quadro = []
): int {
    $existente = buscarIdPorTitulo(
        $pdo,
        'SELECT id FROM provas_blocos WHERE titulo = ? AND deleted_at IS NULL LIMIT 1',
        $titulo
    );
    if ($existente > 0) {
        $pdo->prepare('UPDATE provas_blocos SET gabarito_liberado = 1, liberado = 1, ativo = 1, status = ? WHERE id = ?')
            ->execute(['liberado', $existente]);
        aplicarMetaQuadro($pdo, $existente, $quadro);
        println("→ Bloco já existia, gabarito liberado: {$titulo} (id={$existente})");
        return $existente;
    }

    $inicio = $dataProva . ' 07:00:00';
    $fim = date('Y-m-d 23:59:59', strtotime($dataProva . " +{$diasJanela} days"));

    $colunas = [
        'titulo', 'descricao', 'data_prova', 'hora_inicio', 'hora_fim',
        'criado_por', 'tipo_prova', 'formato_evento', 'configuracao_nota',
        'liberar_gabarito', 'turma_id', 'ativo', 'liberado', 'status',
        'gabarito_liberado', 'professor_id',
    ];
    $valores = [
        $titulo,
        $descricao,
        $dataProva,
        '07:00:00',
        '23:59:59',
        $ctx['admin_id'],
        'original',
        'online_questoes',
        'professor_por_questao',
        'imediatamente',
        $ctx['turma_id'],
        1,
        1,
        'liberado',
        1,
        $ctx['professor_id'],
    ];

    if (colunaExiste($pdo, 'provas_blocos', 'ano_letivo')) {
        $colunas[] = 'ano_letivo';
        $valores[] = (int) ($quadro['ano_letivo'] ?? 2026);
    }
    if (colunaExiste($pdo, 'provas_blocos', 'visivel_no_portal_aluno')) {
        $colunas[] = 'visivel_no_portal_aluno';
        $valores[] = 1;
    }
    if (colunaExiste($pdo, 'provas_blocos', 'semana')) {
        $s = (int) ($quadro['semana'] ?? 0);
        $colunas[] = 'semana';
        $valores[] = ($s >= 1 && $s <= 8) ? $s : null;
    }
    if (colunaExiste($pdo, 'provas_blocos', 'bimestre')) {
        $b = (int) ($quadro['bimestre'] ?? 0);
        $colunas[] = 'bimestre';
        $valores[] = ($b >= 1 && $b <= 4) ? $b : null;
    }
    if (colunaExiste($pdo, 'provas_blocos', 'tipo_avaliacao_id')) {
        $t = (int) ($quadro['tipo_avaliacao_id'] ?? 0);
        $colunas[] = 'tipo_avaliacao_id';
        $valores[] = $t > 0 ? $t : null;
    }

    $ph = implode(', ', array_fill(0, count($colunas), '?'));
    $pdo->prepare('INSERT INTO provas_blocos (' . implode(', ', $colunas) . ') VALUES (' . $ph . ')')
        ->execute($valores);
    $blocoId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO provas_blocos_turmas (bloco_id, turma_id) VALUES (?, ?)')
        ->execute([$blocoId, $ctx['turma_id']]);

    $banco = bancoQuestoes();
    $ordem = 1;
    foreach ($materias as $materiaId) {
        $materiaNome = $ctx['materias'][$materiaId];
        $pdo->prepare(
            'INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
             VALUES (?, ?, ?, 5)'
        )->execute([$blocoId, $ctx['professor_id'], $materiaId]);

        $tituloProva = "Prova de {$materiaNome} — {$titulo}";
        $pdo->prepare(
            'INSERT INTO provas (
                professor_id, materia_id, turma_id, titulo, descricao,
                data_inicio, data_fim, data_prova, tempo_limite, valor_total,
                mostrar_resultado, permite_correcao, liberar_resultado,
                ativo, liberada, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 60, 10.00, 1, 0, ?, 1, 1, ?)'
        )->execute([
            $ctx['professor_id'],
            $materiaId,
            $ctx['turma_id'],
            $tituloProva,
            "Avaliação de {$materiaNome} já realizada para visualização no painel do aluno.",
            $inicio,
            $fim,
            $dataProva,
            'imediatamente',
            'aprovada',
        ]);
        $provaId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO provas_turmas (prova_id, turma_id) VALUES (?, ?)')
            ->execute([$provaId, $ctx['turma_id']]);
        $pdo->prepare('INSERT INTO provas_blocos_vinculo (bloco_id, prova_id, ordem) VALUES (?, ?, ?)')
            ->execute([$blocoId, $provaId, $ordem]);

        $questoes = $banco[$materiaId] ?? [];
        $acertosAlvo = (int) ($acertosPorMateria[$materiaId] ?? 3);
        $nota = 0.0;
        $questaoIds = [];

        foreach ($questoes as $i => $q) {
            $pdo->prepare(
                'INSERT INTO provas_questoes (prova_id, enunciado, tipo, valor, ordem, explicacao)
                 VALUES (?, ?, ?, 2.00, ?, ?)'
            )->execute([
                $provaId,
                $q[0],
                'multipla_escolha',
                $i + 1,
                'A alternativa correta está destacada no gabarito.',
            ]);
            $questaoId = (int) $pdo->lastInsertId();
            $corretaIdx = (int) $q[2];
            $altIds = [];
            foreach ($q[1] as $j => $texto) {
                $pdo->prepare(
                    'INSERT INTO provas_alternativas (questao_id, texto, correta, ordem) VALUES (?, ?, ?, ?)'
                )->execute([$questaoId, $texto, $j === $corretaIdx ? 1 : 0, $j + 1]);
                $altIds[$j] = (int) $pdo->lastInsertId();
            }
            $questaoIds[] = [
                'id' => $questaoId,
                'alt_correta' => $altIds[$corretaIdx],
                'alt_errada' => $altIds[$corretaIdx === 0 ? 1 : 0],
                'acertou' => ($i + 1) <= $acertosAlvo,
            ];
            if (($i + 1) <= $acertosAlvo) {
                $nota += 2.0;
            }
        }

        $iniciado = date('Y-m-d H:i:s', strtotime($dataProva . ' 08:' . str_pad((string) (10 + $ordem), 2, '0', STR_PAD_LEFT) . ':00'));
        $finalizado = date('Y-m-d H:i:s', strtotime($iniciado . ' +25 minutes'));
        $pdo->prepare(
            'INSERT INTO provas_realizacoes (prova_id, aluno_id, iniciado_em, finalizado_em, tempo_gasto, nota, status)
             VALUES (?, ?, ?, ?, 25, ?, ?)'
        )->execute([$provaId, $ctx['aluno_id'], $iniciado, $finalizado, $nota, 'finalizado']);

        foreach ($questaoIds as $item) {
            $alt = $item['acertou'] ? $item['alt_correta'] : $item['alt_errada'];
            $pdo->prepare(
                'INSERT INTO provas_respostas (prova_id, aluno_id, questao_id, alternativa_id, correta, pontuacao)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $provaId,
                $ctx['aluno_id'],
                $item['id'],
                $alt,
                $item['acertou'] ? 1 : 0,
                $item['acertou'] ? 2.00 : 0.00,
            ]);
        }

        println("  · {$materiaNome}: nota {$nota}/10 ({$acertosAlvo}/5 acertos) — prova id={$provaId}");
        $ordem++;
    }

    println("→ Bloco criado: {$titulo} (id={$blocoId})");
    return $blocoId;
}

function garantirBlocoLancamento(PDO $pdo, array $ctx, string $titulo, array $notasPorMateria, string $dataProva): void
{
    $existente = buscarIdPorTitulo(
        $pdo,
        'SELECT id FROM provas_blocos WHERE titulo = ? AND deleted_at IS NULL LIMIT 1',
        $titulo
    );
    if ($existente > 0) {
        println("→ Evento de lançamento já existia: {$titulo} (id={$existente})");
        return;
    }

    $colunas = [
        'titulo', 'descricao', 'data_prova', 'hora_inicio', 'hora_fim',
        'criado_por', 'tipo_prova', 'formato_evento', 'configuracao_nota',
        'liberar_gabarito', 'turma_id', 'ativo', 'liberado', 'status',
        'gabarito_liberado', 'professor_id',
    ];
    $valores = [
        $titulo,
        'Notas lançadas pela escola (sem questões no sistema) para conferir a aba Notas do aluno.',
        $dataProva,
        '07:00:00',
        '23:59:59',
        $ctx['admin_id'],
        'original',
        'lancamento_nota',
        'coordenacao_calcula',
        'imediatamente',
        $ctx['turma_id'],
        1,
        0,
        'aguardando',
        1,
        $ctx['professor_id'],
    ];
    if (colunaExiste($pdo, 'provas_blocos', 'ano_letivo')) {
        $colunas[] = 'ano_letivo';
        $valores[] = 2026;
    }
    if (colunaExiste($pdo, 'provas_blocos', 'visivel_no_portal_aluno')) {
        $colunas[] = 'visivel_no_portal_aluno';
        $valores[] = 1;
    }

    $ph = implode(', ', array_fill(0, count($colunas), '?'));
    $pdo->prepare('INSERT INTO provas_blocos (' . implode(', ', $colunas) . ') VALUES (' . $ph . ')')
        ->execute($valores);
    $blocoId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO provas_blocos_turmas (bloco_id, turma_id) VALUES (?, ?)')
        ->execute([$blocoId, $ctx['turma_id']]);

    foreach ($notasPorMateria as $materiaId => $nota) {
        $pdo->prepare(
            'INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
             VALUES (?, ?, ?, 0)'
        )->execute([$blocoId, $ctx['professor_id'], $materiaId]);
        $pdo->prepare(
            'INSERT INTO provas_blocos_notas_lancadas
                (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota, observacao)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $blocoId,
            $ctx['professor_id'],
            $materiaId,
            $ctx['turma_id'],
            $ctx['aluno_id'],
            $nota,
            'Nota de demonstração para o painel do aluno.',
        ]);
        println('  · ' . $ctx['materias'][$materiaId] . ': nota lançada ' . number_format((float) $nota, 1, ',', '.') );
    }

    println("→ Evento de lançamento criado: {$titulo} (id={$blocoId})");
}

function garantirGruposMateriasQuadro(PDO $pdo): void
{
    $ok = $pdo->query("SHOW TABLES LIKE 'notas_semanais_materias'");
    if (!$ok || $ok->fetch() === false) {
        println('→ Tabela notas_semanais_materias ausente; pulei grupos A/B.');
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO notas_semanais_materias (materia_id, grupo) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE grupo = VALUES(grupo)'
    );
    $mapa = [
        44 => 'A', // Matemática
        47 => 'A', // Geografia
        48 => 'A', // Ciências
        45 => 'B', // Português
        46 => 'B', // História
    ];
    foreach ($mapa as $materiaId => $grupo) {
        $stmt->execute([$materiaId, $grupo]);
    }
    println('→ Grupos do quadro: A = Matemática/Geografia/Ciências · B = Português/História');
}

function limparSemanaBlocosAntigos(PDO $pdo): void
{
    if (!colunaExiste($pdo, 'provas_blocos', 'semana')) {
        return;
    }
    $tipoSimulado = tipoIdPorChave($pdo, 'simulado');
    $stmt = $pdo->query(
        "SELECT id, titulo FROM provas_blocos
         WHERE deleted_at IS NULL
           AND titulo NOT LIKE 'Quadro — %'
           AND semana IS NOT NULL"
    );
    $rows = $stmt ? $stmt->fetchAll() : [];
    if ($rows === []) {
        return;
    }
    $sql = "UPDATE provas_blocos
            SET semana = NULL";
    $params = [];
    if (colunaExiste($pdo, 'provas_blocos', 'tipo_avaliacao_id') && $tipoSimulado > 0) {
        $sql .= ', tipo_avaliacao_id = ?';
        $params[] = $tipoSimulado;
    }
    $sql .= " WHERE deleted_at IS NULL
                AND titulo NOT LIKE 'Quadro — %'
                AND semana IS NOT NULL";
    $pdo->prepare($sql)->execute($params);
    foreach ($rows as $r) {
        println("→ Semana removida do evento antigo: {$r['titulo']} (id={$r['id']})");
    }
}

function semearQuadroBimestres(PDO $pdo, array $ctx, int $tipoSemanal, int $tipoBim): void
{
    $grupoA = [44, 47, 48];
    $grupoB = [45, 46];
    $todas = [44, 45, 46, 47, 48];
    $datas = [
        1 => [
            1 => '2026-02-09',
            2 => '2026-02-16',
            3 => '2026-02-23',
            4 => '2026-03-02',
            5 => '2026-03-09',
            6 => '2026-03-16',
            7 => '2026-03-23',
            8 => '2026-03-30',
            'bim' => '2026-04-06',
        ],
        2 => [
            1 => '2026-05-04',
            2 => '2026-05-11',
            3 => '2026-05-18',
            4 => '2026-05-25',
            5 => '2026-06-01',
            6 => '2026-06-08',
            7 => '2026-06-15',
            8 => '2026-06-22',
            'bim' => '2026-06-29',
        ],
    ];

    foreach ([1, 2] as $bimestre) {
        $rotulo = $bimestre . 'º Bimestre';
        println('');
        println("-- {$rotulo} · provas semanais Grupo A (S1, S3, S5, S7) --");
        foreach ([1, 3, 5, 7] as $semana) {
            $acertos = [];
            foreach ($grupoA as $mid) {
                $acertos[$mid] = acertosQuadro($mid, $semana, $bimestre);
            }
            garantirBlocoOnline(
                $pdo,
                $ctx,
                "Quadro — {$rotulo} · Grupo A · Semana S{$semana}",
                "Prova semanal do Grupo A (Matemática, Geografia e Ciências) — {$rotulo}, S{$semana}.",
                $grupoA,
                $acertos,
                $datas[$bimestre][$semana],
                6,
                [
                    'semana' => $semana,
                    'bimestre' => $bimestre,
                    'tipo_avaliacao_id' => $tipoSemanal,
                    'ano_letivo' => 2026,
                ]
            );
        }

        println('');
        println("-- {$rotulo} · provas semanais Grupo B (S2, S4, S6, S8) --");
        foreach ([2, 4, 6, 8] as $semana) {
            $acertos = [];
            foreach ($grupoB as $mid) {
                $acertos[$mid] = acertosQuadro($mid, $semana, $bimestre);
            }
            garantirBlocoOnline(
                $pdo,
                $ctx,
                "Quadro — {$rotulo} · Grupo B · Semana S{$semana}",
                "Prova semanal do Grupo B (Português e História) — {$rotulo}, S{$semana}.",
                $grupoB,
                $acertos,
                $datas[$bimestre][$semana],
                6,
                [
                    'semana' => $semana,
                    'bimestre' => $bimestre,
                    'tipo_avaliacao_id' => $tipoSemanal,
                    'ano_letivo' => 2026,
                ]
            );
        }

        println('');
        println("-- {$rotulo} · prova bimestral --");
        $acertosBim = [];
        foreach ($todas as $mid) {
            $acertosBim[$mid] = acertosBimestral($mid, $bimestre);
        }
        garantirBlocoOnline(
            $pdo,
            $ctx,
            "Quadro — {$rotulo} · Prova Bimestral",
            "Prova bimestral com todas as matérias dos grupos A e B — {$rotulo}.",
            $todas,
            $acertosBim,
            $datas[$bimestre]['bim'],
            10,
            [
                'semana' => null,
                'bimestre' => $bimestre,
                'tipo_avaliacao_id' => $tipoBim,
                'ano_letivo' => 2026,
            ]
        );
    }
}

function liberarGabaritoBlocosExistentes(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT id, titulo FROM provas_blocos
         WHERE deleted_at IS NULL
           AND formato_evento = 'online_questoes'
           AND gabarito_liberado = 0"
    );
    $blocos = $stmt->fetchAll();
    if ($blocos === []) {
        println('→ Nenhum bloco antigo pendente de gabarito.');
        return;
    }
    $pdo->exec(
        "UPDATE provas_blocos
         SET gabarito_liberado = 1, liberado = 1, ativo = 1, status = 'liberado'
         WHERE deleted_at IS NULL
           AND formato_evento = 'online_questoes'
           AND gabarito_liberado = 0"
    );
    foreach ($blocos as $b) {
        println("→ Gabarito liberado no bloco existente: {$b['titulo']} (id={$b['id']})");
    }
}

println('== EducaTudo — seed provas respondidas (local) ==');

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');

$hostsLocaisPermitidos = ['mysql', 'localhost', '127.0.0.1', '::1'];
if (!in_array($host, $hostsLocaisPermitidos, true)) {
    fwrite(STDERR, "Abortado: DB_HOST={$host} não parece ambiente local.\n");
    exit(1);
}

$pdo = connectPdo($host, $port, TENANT_DB, $dbUser, $dbPass);

$aluno = $pdo->prepare('SELECT id, nome, turma_id FROM alunos WHERE nickname = ? LIMIT 1');
$aluno->execute([ALUNO_NICKNAME]);
$alunoRow = $aluno->fetch();
if (!$alunoRow || (int) $alunoRow['turma_id'] <= 0) {
    fwrite(STDERR, "Aluno '" . ALUNO_NICKNAME . "' não encontrado ou sem turma. Rode seed_local_usuarios_teste.php primeiro.\n");
    exit(1);
}

$adminId = (int) $pdo->query("SELECT id FROM usuarios WHERE email = 'admin@colag.local' LIMIT 1")->fetchColumn();
$professorId = (int) $pdo->query("SELECT id FROM professores WHERE nome = 'Prof. Teste Local' LIMIT 1")->fetchColumn();
if ($adminId <= 0 || $professorId <= 0) {
    fwrite(STDERR, "Admin ou professor de teste não encontrado.\n");
    exit(1);
}

$materias = [];
foreach ($pdo->query('SELECT id, nome FROM materias WHERE id IN (44,45,46,47,48)') as $row) {
    $materias[(int) $row['id']] = (string) $row['nome'];
}
if (count($materias) < 5) {
    fwrite(STDERR, "Matérias 44–48 não encontradas no tenant.\n");
    exit(1);
}

$ctx = [
    'aluno_id' => (int) $alunoRow['id'],
    'turma_id' => (int) $alunoRow['turma_id'],
    'admin_id' => $adminId,
    'professor_id' => $professorId,
    'materias' => $materias,
];

println('Aluno: ' . $alunoRow['nome'] . ' (id=' . $ctx['aluno_id'] . ', turma=' . $ctx['turma_id'] . ')');
println('');

liberarGabaritoBlocosExistentes($pdo);

$pdo->beginTransaction();
try {
    garantirBlocoOnline(
        $pdo,
        $ctx,
        PREFIXO_DEMO . 'Avaliação 1º Bimestre',
        'Bloco de provas online já finalizado, com gabarito liberado, para ver notas no painel do aluno.',
        [44, 45, 46, 47, 48],
        [44 => 5, 45 => 4, 46 => 3, 47 => 2, 48 => 4],
        date('Y-m-d', strtotime('-2 days')),
        40
    );

    garantirBlocoOnline(
        $pdo,
        $ctx,
        PREFIXO_DEMO . 'Simulado Interdisciplinar',
        'Segundo bloco com notas variadas (alto, médio e baixo) para conferir cores e percentuais.',
        [44, 45, 46],
        [44 => 3, 45 => 5, 46 => 1],
        date('Y-m-d', strtotime('-1 day')),
        40
    );

    garantirBlocoLancamento(
        $pdo,
        $ctx,
        PREFIXO_DEMO . 'Trabalhos (nota lançada)',
        [44 => 8.5, 45 => 9.0, 46 => 7.0, 47 => 6.5, 48 => 8.0],
        date('Y-m-d', strtotime('-5 days'))
    );

    $tipoSemanal = tipoIdPorChave($pdo, 'semanal');
    $tipoBim = tipoIdPorChave($pdo, 'prova_bim');
    if ($tipoSemanal <= 0 || $tipoBim <= 0) {
        throw new RuntimeException('Cadastre os tipos Prova Semanal e Prova Bimestral antes do seed do quadro.');
    }

    limparSemanaBlocosAntigos($pdo);
    garantirGruposMateriasQuadro($pdo);
    semearQuadroBimestres($pdo, $ctx, $tipoSemanal, $tipoBim);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Erro ao criar provas: ' . $e->getMessage() . "\n");
    exit(1);
}

println('');
println('Pronto. Entre como aluno.teste / Teste@123 em http://colag.localhost/ e abra:');
println('  • Minhas Provas  →  /aluno/provas  (botão verde “Visualizar resposta”)');
println('  • Desempenho     →  /desempenho/provas');
println('  • Notas/Boletins →  /notas-boletins?secao=notas  (quadro S1–S8, 1º e 2º bimestre)');
println('  • Notas/Boletins →  /notas-boletins?secao=provas');
println('');
