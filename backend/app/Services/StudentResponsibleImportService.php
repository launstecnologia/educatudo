<?php

class StudentResponsibleImportService
{
    private $db;
    private $reportsDir;
    private $alunosCatalogoCache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->reportsDir = dirname(__DIR__, 2) . '/storage/imports/responsaveis';
    }

    public function importFromCsv(string $csvPath): array
    {
        if (!is_file($csvPath)) {
            throw new Exception('Arquivo CSV não encontrado para importação.');
        }
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            throw new Exception('Não foi possível abrir o arquivo CSV.');
        }

        $header = fgetcsv($handle, 0, ',');
        if (!is_array($header) || empty($header)) {
            fclose($handle);
            throw new Exception('Cabeçalho CSV inválido.');
        }

        $headerMap = $this->buildHeaderMap($header);
        if (!$this->hasAnyAlunoMatchField($headerMap)) {
            fclose($handle);
            throw new Exception('CSV sem colunas suficientes para localizar aluno (use aluno_id, codigo_aluno/ra ou nome+turma+curso).');
        }

        $successRows = [];
        $pendingRows = [];
        $totals = [
            'linhas_processadas' => 0,
            'vinculos_criados' => 0,
            'responsaveis_criados' => 0,
            'responsaveis_atualizados' => 0,
            'pendencias' => 0,
        ];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $row = $this->normalizeRowColumns($row, $headerMap, count($header));
            if (empty(array_filter($row, static function ($v) { return trim((string)$v) !== ''; }))) {
                continue;
            }
            $totals['linhas_processadas']++;
            $alunoData = [];

            try {
                $alunoData = $this->extractAlunoData($row, $headerMap);
                $aluno = $this->resolveAlunoByNomeTurmaCurso($alunoData);
                if ($aluno === null) {
                    $pendingRows[] = array_merge($alunoData, ['motivo' => 'aluno_nao_encontrado']);
                    $totals['pendencias']++;
                    continue;
                }
                if (isset($aluno['ambiguous']) && $aluno['ambiguous'] === true) {
                    $pendingRows[] = array_merge($alunoData, ['motivo' => 'aluno_ambiguo']);
                    $totals['pendencias']++;
                    continue;
                }

                $responsaveis = $this->extractResponsaveis($row, $headerMap);
                if (empty($responsaveis)) {
                    $pendingRows[] = array_merge($alunoData, ['motivo' => 'sem_responsaveis_validos']);
                    $totals['pendencias']++;
                    continue;
                }

                foreach ($responsaveis as $resp) {
                    $upsert = $this->upsertResponsavel($resp);
                    $responsavelId = (int)$upsert['id'];
                    if ($upsert['created']) {
                        $totals['responsaveis_criados']++;
                    } elseif ($upsert['updated']) {
                        $totals['responsaveis_atualizados']++;
                    }

                    $this->linkAlunoResponsavel((int)$aluno['id'], $responsavelId, (int)$resp['is_financeiro']);
                    $totals['vinculos_criados']++;

                    $successRows[] = [
                        'aluno_id' => (int)$aluno['id'],
                        'aluno_nome' => $aluno['nome'],
                        'aluno_codigo' => $alunoData['codigo_aluno'],
                        'turma' => $alunoData['nome_turma'],
                        'curso' => $alunoData['nome_curso'],
                        'responsavel_id' => $responsavelId,
                        'responsavel_nome' => $resp['nome'],
                        'responsavel_cpf' => $resp['cpf'] ?? '',
                        'responsavel_email' => $resp['email'] ?? '',
                        'is_financeiro' => (int)$resp['is_financeiro'],
                        'status' => 'ok',
                    ];
                }

                $this->updateAlunoCoreData((int)$aluno['id'], $alunoData);
            } catch (Throwable $e) {
                $pendingRows[] = [
                    'nome_aluno' => $alunoData['nome_aluno'] ?? '',
                    'nome_curso' => $alunoData['nome_curso'] ?? '',
                    'nome_turma' => $alunoData['nome_turma'] ?? '',
                    'codigo_aluno' => $alunoData['codigo_aluno'] ?? '',
                    'motivo' => 'erro_linha: ' . $e->getMessage(),
                ];
                $totals['pendencias']++;
            }
        }
        fclose($handle);

        return $this->writeReports($successRows, $pendingRows, $totals);
    }

    public function importFromJson(string $jsonPath): array
    {
        if (!is_file($jsonPath)) {
            throw new Exception('Arquivo JSON não encontrado para importação.');
        }

        $successRows = [];
        $pendingRows = [];
        $totals = [
            'linhas_processadas' => 0,
            'vinculos_criados' => 0,
            'responsaveis_criados' => 0,
            'responsaveis_atualizados' => 0,
            'pendencias' => 0,
        ];

        foreach ($this->iterateTopLevelJsonArrayObjects($jsonPath) as $obj) {
            if (!is_array($obj) || empty($obj)) {
                continue;
            }
            $totals['linhas_processadas']++;
            try {
                $map = $this->buildObjectMap($obj);
                $alunoData = $this->extractAlunoDataFromMap($map);
                $responsaveis = $this->extractResponsaveisFromMap($map);
                if (empty($responsaveis)) {
                    $this->processAlunoOnlySync($alunoData, $successRows, $pendingRows, $totals);
                } else {
                    $this->processAlunoResponsavelSync($alunoData, $responsaveis, $successRows, $pendingRows, $totals);
                }
            } catch (Throwable $e) {
                $pendingRows[] = [
                    'nome_aluno' => isset($obj['NomeAluno']) ? (string)$obj['NomeAluno'] : '',
                    'nome_curso' => isset($obj['NomeCurso']) ? (string)$obj['NomeCurso'] : '',
                    'nome_turma' => isset($obj['NomeTurma']) ? (string)$obj['NomeTurma'] : '',
                    'codigo_aluno' => isset($obj['CodigoAluno']) ? (string)$obj['CodigoAluno'] : '',
                    'motivo' => 'erro_linha: ' . $e->getMessage(),
                ];
                $totals['pendencias']++;
            }
        }

        return $this->writeReports($successRows, $pendingRows, $totals);
    }

    private function buildHeaderMap(array $header): array
    {
        $map = [];
        foreach ($header as $idx => $col) {
            $map[$this->normalizeKey((string)$col)] = $idx;
        }
        return $map;
    }

    private function buildObjectMap(array $obj): array
    {
        $map = [];
        foreach ($obj as $k => $v) {
            $map[$this->normalizeKey((string)$k)] = $v;
        }
        return $map;
    }

    private function normalizeKey(string $value): string
    {
        $value = trim($value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value);
        return $value ?? '';
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $base = $ascii !== false ? $ascii : $value;
        return strtolower(trim($base));
    }

    private function extractAlunoData(array $row, array $headerMap): array
    {
        $get = function (array $keys) use ($row, $headerMap): string {
            return $this->getFirstCsvVal($row, $headerMap, $keys);
        };

        return [
            'aluno_id' => (int)($get(['alunoid']) ?: 0),
            'nome_aluno' => $get(['nomealuno', 'nomedb', 'nomecompleto']),
            'nome_curso' => $get(['nomecurso', 'cursonomedb', 'cursonomedb']),
            'nome_turma' => $get(['nometurma', 'turmanomedb']),
            'codigo_aluno' => $get(['codigoaluno', 'codigoalunocsv', 'codigoalunodbatual', 'ra', 'radbatual']),
            'ra' => $get(['ra', 'radbatual', 'codigoaluno', 'codigoalunocsv']),
            'email_aluno' => $get(['emailaluno', 'emaildb']),
            'cpf_aluno' => preg_replace('/\D+/', '', $get(['cpfaluno', 'cpfdb'])),
            'foto_aluno' => $get(['fotoaluno']),
        ];
    }

    private function extractAlunoDataFromMap(array $map): array
    {
        $get = function (array $keys) use ($map): string {
            return $this->getFirstMapVal($map, $keys);
        };

        return [
            'aluno_id' => (int)($get(['alunoid']) ?: 0),
            'nome_aluno' => $get(['nomealuno', 'nomedb', 'nomecompleto']),
            'nome_curso' => $get(['nomecurso', 'cursonomedb']),
            'nome_turma' => $get(['nometurma', 'turmanomedb']),
            'codigo_aluno' => $get(['codigoaluno', 'codigoalunocsv', 'codigoalunodbatual', 'ra', 'radbatual']),
            'ra' => $get(['ra', 'radbatual', 'codigoaluno', 'codigoalunocsv']),
            'email_aluno' => $get(['emailaluno', 'emaildb']),
            'cpf_aluno' => preg_replace('/\D+/', '', $get(['cpfaluno', 'cpfdb'])),
            'foto_aluno' => $get(['fotoaluno']),
        ];
    }

    private function resolveAlunoByNomeTurmaCurso(array $alunoData): ?array
    {
        $alunos = $this->getAlunosCatalogo();

        if (!empty($alunoData['aluno_id'])) {
            $id = (int)$alunoData['aluno_id'];
            foreach ($alunos as $aluno) {
                if ((int)($aluno['id'] ?? 0) === $id) {
                    return $aluno;
                }
            }
            return null;
        }

        $codigoTarget = $this->normalizeText((string)($alunoData['codigo_aluno'] ?? ''));
        $raTarget = $this->normalizeText((string)($alunoData['ra'] ?? ''));
        if ($codigoTarget !== '' || $raTarget !== '') {
            $matchesCodigo = [];
            foreach ($alunos as $aluno) {
                $raDb = $this->normalizeText((string)($aluno['ra'] ?? ''));
                $codigoDb = $this->normalizeText((string)($aluno['codigo_aluno'] ?? ''));
                if (($codigoTarget !== '' && ($codigoDb === $codigoTarget || $raDb === $codigoTarget)) ||
                    ($raTarget !== '' && ($raDb === $raTarget || $codigoDb === $raTarget))) {
                    $matchesCodigo[] = $aluno;
                }
            }
            if (count($matchesCodigo) === 1) {
                return $matchesCodigo[0];
            }
            if (count($matchesCodigo) > 1) {
                return ['ambiguous' => true];
            }
        }

        $targetNome = $this->normalizeText($alunoData['nome_aluno']);
        $targetTurma = $this->normalizeText($alunoData['nome_turma']);
        $targetCurso = $this->normalizeText($alunoData['nome_curso']);

        $matches = [];
        foreach ($alunos as $aluno) {
            $nomeOk = $this->normalizeText((string)($aluno['nome'] ?? '')) === $targetNome;
            $turmaOk = $this->normalizeText((string)($aluno['turma_nome'] ?? '')) === $targetTurma;
            $cursoDb = $this->normalizeText((string)($aluno['curso_nome'] ?? ''));
            $cursoOk = ($targetCurso === '') || ($cursoDb === $targetCurso);
            if ($nomeOk && $turmaOk && $cursoOk) {
                $matches[] = $aluno;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }
        if (count($matches) > 1) {
            return ['ambiguous' => true];
        }
        return null;
    }

    /**
     * Carrega catálogo de alunos ativos uma única vez por importação.
     * Evita N+1 queries (antes fazia SELECT de todos os alunos para cada linha do CSV).
     */
    private function getAlunosCatalogo(): array
    {
        if (is_array($this->alunosCatalogoCache)) {
            return $this->alunosCatalogoCache;
        }

        try {
            $alunos = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.turma_id, a.ra, a.codigo_aluno, t.nome as turma_nome, c.nome as curso_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 LEFT JOIN curso c ON c.id = t.curso_novo_id
                 WHERE a.ativo = 1"
            );
        } catch (Throwable $e) {
            $alunos = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.turma_id, a.ra, a.codigo_aluno, t.nome as turma_nome, '' as curso_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.ativo = 1"
            );
        }

        $this->alunosCatalogoCache = is_array($alunos) ? $alunos : [];
        return $this->alunosCatalogoCache;
    }

    private function extractResponsaveis(array $row, array $headerMap): array
    {
        $responsaveis = [];
        for ($i = 1; $i <= 3; $i++) {
            $nome = $this->getCsvVal($row, $headerMap, 'nomeresp' . $i);
            $email = $this->getCsvVal($row, $headerMap, 'emailresp' . $i);
            $celular = $this->getCsvVal($row, $headerMap, 'celularresp' . $i);
            $cpf = preg_replace('/\D+/', '', $this->getCsvVal($row, $headerMap, 'cpfresp' . $i));
            if ($cpf === '' && $i === 2) {
                $cpf = preg_replace('/\D+/', '', $this->getCsvVal($row, $headerMap, 'cpfresp2'));
            }
            $financeiro = strtoupper($this->getCsvVal($row, $headerMap, 'financeiroresp' . $i));
            $isFinanceiro = in_array($financeiro, ['S', 'SIM', '1', 'TRUE'], true) ? 1 : 0;

            if ($nome === '' && $email === '' && $cpf === '') {
                continue;
            }
            $responsaveis[] = [
                'nome' => $nome !== '' ? $nome : ('Responsável ' . $i),
                'email' => $email !== '' ? $email : null,
                'telefone' => $celular !== '' ? $celular : null,
                'cpf' => $cpf !== '' ? $cpf : null,
                'is_financeiro' => $isFinanceiro,
            ];
        }
        return $responsaveis;
    }

    private function extractResponsaveisFromMap(array $map): array
    {
        $responsaveis = [];
        for ($i = 1; $i <= 3; $i++) {
            $nome = $this->getFirstMapVal($map, ['nomeresp' . $i]);
            $email = $this->getFirstMapVal($map, ['emailresp' . $i]);
            $celular = $this->getFirstMapVal($map, ['celularresp' . $i]);
            $cpf = preg_replace('/\D+/', '', $this->getFirstMapVal($map, ['cpfresp' . $i]));
            if ($cpf === '' && $i === 2) {
                $cpf = preg_replace('/\D+/', '', $this->getFirstMapVal($map, ['cpfresp2']));
            }
            $financeiro = strtoupper($this->getFirstMapVal($map, ['financeiroresp' . $i]));
            $isFinanceiro = in_array($financeiro, ['S', 'SIM', '1', 'TRUE', '2'], true) ? 1 : 0;
            if ($nome === '' && $email === '' && $cpf === '') {
                continue;
            }
            $responsaveis[] = [
                'nome' => $nome !== '' ? $nome : ('Responsável ' . $i),
                'email' => $email !== '' ? $email : null,
                'telefone' => $celular !== '' ? $celular : null,
                'cpf' => $cpf !== '' ? $cpf : null,
                'is_financeiro' => $isFinanceiro,
            ];
        }
        return $responsaveis;
    }

    private function getCsvVal(array $row, array $headerMap, string $key): string
    {
        $idx = $headerMap[$this->normalizeKey($key)] ?? null;
        return ($idx !== null && isset($row[$idx])) ? trim((string)$row[$idx]) : '';
    }

    private function getFirstCsvVal(array $row, array $headerMap, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->getCsvVal($row, $headerMap, $key);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function getFirstMapVal(array $map, array $keys): string
    {
        foreach ($keys as $key) {
            $nk = $this->normalizeKey($key);
            if (array_key_exists($nk, $map)) {
                $v = $map[$nk];
                if ($v === null) {
                    continue;
                }
                if (is_scalar($v)) {
                    $s = trim((string)$v);
                    if ($s !== '') {
                        return $s;
                    }
                }
            }
        }
        return '';
    }

    private function hasAnyAlunoMatchField(array $headerMap): bool
    {
        $hasAlunoId = isset($headerMap['alunoid']);
        $hasCodigo = isset($headerMap['codigoaluno']) || isset($headerMap['codigoalunocsv']) || isset($headerMap['ra']) || isset($headerMap['radbatual']);
        $hasNomeTurma = (isset($headerMap['nomealuno']) || isset($headerMap['nomedb']) || isset($headerMap['nomecompleto']))
            && (isset($headerMap['nometurma']) || isset($headerMap['turmanomedb']));
        return $hasAlunoId || $hasCodigo || $hasNomeTurma;
    }

    private function processAlunoResponsavelSync(array $alunoData, array $responsaveis, array &$successRows, array &$pendingRows, array &$totals): void
    {
        $aluno = $this->resolveAlunoByNomeTurmaCurso($alunoData);
        if ($aluno === null) {
            $pendingRows[] = array_merge($alunoData, ['motivo' => 'aluno_nao_encontrado']);
            $totals['pendencias']++;
            return;
        }
        if (isset($aluno['ambiguous']) && $aluno['ambiguous'] === true) {
            $pendingRows[] = array_merge($alunoData, ['motivo' => 'aluno_ambiguo']);
            $totals['pendencias']++;
            return;
        }
        if (empty($responsaveis)) {
            $pendingRows[] = array_merge($alunoData, ['motivo' => 'sem_responsaveis_validos']);
            $totals['pendencias']++;
            return;
        }

        foreach ($responsaveis as $resp) {
            $upsert = $this->upsertResponsavel($resp);
            $responsavelId = (int)$upsert['id'];
            if ($upsert['created']) {
                $totals['responsaveis_criados']++;
            } elseif ($upsert['updated']) {
                $totals['responsaveis_atualizados']++;
            }
            $this->linkAlunoResponsavel((int)$aluno['id'], $responsavelId, (int)$resp['is_financeiro']);
            $totals['vinculos_criados']++;
            $successRows[] = [
                'aluno_id' => (int)$aluno['id'],
                'aluno_nome' => $aluno['nome'],
                'aluno_codigo' => $alunoData['codigo_aluno'] ?? '',
                'turma' => $alunoData['nome_turma'] ?? '',
                'curso' => $alunoData['nome_curso'] ?? '',
                'responsavel_id' => $responsavelId,
                'responsavel_nome' => $resp['nome'],
                'responsavel_cpf' => $resp['cpf'] ?? '',
                'responsavel_email' => $resp['email'] ?? '',
                'is_financeiro' => (int)$resp['is_financeiro'],
                'status' => 'ok',
            ];
        }
        $this->updateAlunoCoreData((int)$aluno['id'], $alunoData);
    }

    /**
     * Fluxo para JSON com atualização direta do aluno (ex.: aluno_id + ra), sem responsáveis.
     */
    private function processAlunoOnlySync(array $alunoData, array &$successRows, array &$pendingRows, array &$totals): void
    {
        $aluno = $this->resolveAlunoByNomeTurmaCurso($alunoData);
        if ($aluno === null) {
            $pendingRows[] = array_merge($alunoData, ['motivo' => 'aluno_nao_encontrado']);
            $totals['pendencias']++;
            return;
        }
        if (isset($aluno['ambiguous']) && $aluno['ambiguous'] === true) {
            $pendingRows[] = array_merge($alunoData, ['motivo' => 'aluno_ambiguo']);
            $totals['pendencias']++;
            return;
        }

        $this->updateAlunoCoreData((int)$aluno['id'], $alunoData);
        $successRows[] = [
            'aluno_id' => (int)$aluno['id'],
            'aluno_nome' => $aluno['nome'],
            'aluno_codigo' => $alunoData['codigo_aluno'] ?? '',
            'turma' => $alunoData['nome_turma'] ?? '',
            'curso' => $alunoData['nome_curso'] ?? '',
            'status' => 'aluno_atualizado',
        ];
    }

    /**
     * Itera objetos de um array JSON de forma incremental (streaming), sem carregar tudo em memória.
     */
    private function iterateTopLevelJsonArrayObjects(string $path): iterable
    {
        $fp = fopen($path, 'rb');
        if (!$fp) {
            throw new Exception('Não foi possível abrir o arquivo JSON.');
        }

        $started = false;
        $capturing = false;
        $inString = false;
        $escaped = false;
        $depth = 0;
        $buffer = '';

        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk === false || $chunk === '') {
                continue;
            }
            $len = strlen($chunk);
            for ($i = 0; $i < $len; $i++) {
                $ch = $chunk[$i];

                if (!$started) {
                    if (ctype_space($ch)) {
                        continue;
                    }
                    if ($ch !== '[') {
                        fclose($fp);
                        throw new Exception('JSON inválido: esperado array no topo.');
                    }
                    $started = true;
                    continue;
                }

                if (!$capturing) {
                    if (ctype_space($ch) || $ch === ',') {
                        continue;
                    }
                    if ($ch === ']') {
                        fclose($fp);
                        return;
                    }
                    if ($ch === '{') {
                        $capturing = true;
                        $depth = 1;
                        $buffer = '{';
                        $inString = false;
                        $escaped = false;
                        continue;
                    }
                    continue;
                }

                $buffer .= $ch;
                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($ch === '\\') {
                        $escaped = true;
                    } elseif ($ch === '"') {
                        $inString = false;
                    }
                    continue;
                }

                if ($ch === '"') {
                    $inString = true;
                    continue;
                }
                if ($ch === '{') {
                    $depth++;
                    continue;
                }
                if ($ch === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $obj = json_decode($buffer, true);
                        if (is_array($obj)) {
                            yield $obj;
                        }
                        $capturing = false;
                        $buffer = '';
                    }
                }
            }
        }
        fclose($fp);
    }

    /**
     * Recompõe linha quando há vírgulas extras no campo FotoAluno (CSV malformado).
     * Mantém o início e o fim da linha estáveis e junta o miolo no índice de FotoAluno.
     */
    private function normalizeRowColumns(array $row, array $headerMap, int $expectedCount): array
    {
        $current = count($row);
        if ($current === $expectedCount) {
            return $row;
        }

        $fotoIdx = $headerMap['fotoaluno'] ?? null;
        if ($fotoIdx === null) {
            if ($current < $expectedCount) {
                return array_pad($row, $expectedCount, '');
            }
            return array_slice($row, 0, $expectedCount);
        }

        if ($current > $expectedCount && $fotoIdx < $expectedCount) {
            $tailCount = $expectedCount - ($fotoIdx + 1);
            $prefix = array_slice($row, 0, $fotoIdx);
            $tail = $tailCount > 0 ? array_slice($row, -$tailCount) : [];
            $fotoParts = array_slice($row, $fotoIdx, $current - count($prefix) - count($tail));
            $foto = implode(',', $fotoParts);
            $normalized = array_merge($prefix, [$foto], $tail);
            if (count($normalized) < $expectedCount) {
                $normalized = array_pad($normalized, $expectedCount, '');
            }
            return array_slice($normalized, 0, $expectedCount);
        }

        if ($current < $expectedCount) {
            return array_pad($row, $expectedCount, '');
        }
        return array_slice($row, 0, $expectedCount);
    }

    private function upsertResponsavel(array $resp): array
    {
        $existing = null;
        if (!empty($resp['cpf'])) {
            $existing = $this->db->fetch("SELECT id FROM responsaveis WHERE cpf = :cpf LIMIT 1", ['cpf' => $resp['cpf']]);
        }
        if (!$existing && !empty($resp['email'])) {
            $existing = $this->db->fetch("SELECT id FROM responsaveis WHERE email = :email LIMIT 1", ['email' => $resp['email']]);
        }

        if ($existing) {
            $senhaHash = !empty($resp['cpf']) ? password_hash((string)$resp['cpf'], PASSWORD_DEFAULT) : null;
            $this->db->update(
                "UPDATE responsaveis
                 SET nome = :nome,
                     email = COALESCE(:email, email),
                     telefone = COALESCE(:telefone, telefone),
                     cpf = COALESCE(:cpf, cpf),
                     senha_hash = COALESCE(:senha_hash, senha_hash),
                     force_password_change = CASE WHEN :senha_hash IS NULL THEN force_password_change ELSE 1 END,
                     ativo = 1,
                     updated_at = NOW()
                 WHERE id = :id",
                [
                    'id' => $existing['id'],
                    'nome' => $resp['nome'],
                    'email' => $resp['email'],
                    'telefone' => $resp['telefone'],
                    'cpf' => $resp['cpf'],
                    'senha_hash' => $senhaHash,
                ]
            );
            return ['id' => (int)$existing['id'], 'created' => false, 'updated' => true];
        }

        $senhaPadrao = $resp['cpf'] ?? null;
        $senhaHash = $senhaPadrao ? password_hash($senhaPadrao, PASSWORD_DEFAULT) : password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $id = $this->db->insert(
            "INSERT INTO responsaveis (nome, email, senha_hash, force_password_change, cpf, telefone, ativo, created_at, updated_at)
             VALUES (:nome, :email, :senha_hash, 1, :cpf, :telefone, 1, NOW(), NOW())",
            [
                'nome' => $resp['nome'],
                'email' => $resp['email'],
                'senha_hash' => $senhaHash,
                'cpf' => $resp['cpf'],
                'telefone' => $resp['telefone'],
            ]
        );
        return ['id' => (int)$id, 'created' => true, 'updated' => false];
    }

    private function linkAlunoResponsavel(int $alunoId, int $responsavelId, int $isFinanceiro): void
    {
        $this->db->insert(
            "INSERT INTO alunos_responsaveis (aluno_id, responsavel_id, tipo_vinculo, is_financeiro, ativo, created_at, updated_at)
             VALUES (:aluno_id, :responsavel_id, 'responsavel', :is_financeiro, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                is_financeiro = GREATEST(is_financeiro, VALUES(is_financeiro)),
                ativo = 1,
                updated_at = NOW()",
            [
                'aluno_id' => $alunoId,
                'responsavel_id' => $responsavelId,
                'is_financeiro' => $isFinanceiro
            ]
        );

        $this->db->update(
            "UPDATE alunos
             SET responsavel_id = COALESCE(responsavel_id, :responsavel_id)
             WHERE id = :aluno_id",
            ['aluno_id' => $alunoId, 'responsavel_id' => $responsavelId]
        );
    }

    private function updateAlunoCoreData(int $alunoId, array $alunoData): void
    {
        $fotoUrl = $this->extractFotoUrl($alunoData['foto_aluno'] ?? '');
        $this->db->update(
            "UPDATE alunos
             SET codigo_aluno = COALESCE(NULLIF(:codigo_aluno, ''), codigo_aluno),
                 ra = COALESCE(NULLIF(:codigo_aluno, ''), ra),
                 cpf = COALESCE(NULLIF(:cpf, ''), cpf),
                 email = COALESCE(NULLIF(:email, ''), email),
                 foto_url = COALESCE(NULLIF(:foto_url, ''), foto_url),
                 updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $alunoId,
                'codigo_aluno' => $alunoData['codigo_aluno'] ?? '',
                'cpf' => $alunoData['cpf_aluno'] ?? '',
                'email' => $alunoData['email_aluno'] ?? '',
                'foto_url' => $fotoUrl,
            ]
        );
    }

    private function extractFotoUrl(string $rawFoto): string
    {
        $rawFoto = trim($rawFoto);
        if ($rawFoto === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $rawFoto)) {
            return $rawFoto;
        }
        if (strlen($rawFoto) > 200) {
            return '';
        }
        return $rawFoto;
    }

    private function writeReports(array $successRows, array $pendingRows, array $totals): array
    {
        if (!is_dir($this->reportsDir)) {
            @mkdir($this->reportsDir, 0775, true);
        }

        $stamp = date('Ymd_His');
        $successFile = $this->reportsDir . "/import_responsaveis_sucesso_{$stamp}.csv";
        $pendingFile = $this->reportsDir . "/import_responsaveis_pendencias_{$stamp}.csv";
        $summaryFile = $this->reportsDir . "/import_responsaveis_resumo_{$stamp}.json";

        $this->writeCsv($successFile, $successRows);
        $this->writeCsv($pendingFile, $pendingRows);
        file_put_contents($summaryFile, json_encode($totals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'totais' => $totals,
            'relatorios' => [
                'sucesso' => basename($successFile),
                'pendencias' => basename($pendingFile),
                'resumo' => basename($summaryFile),
            ],
        ];
    }

    private function writeCsv(string $filePath, array $rows): void
    {
        $fp = fopen($filePath, 'w');
        if (!$fp) {
            return;
        }
        if (empty($rows)) {
            fputcsv($fp, ['sem_dados'], ';');
            fclose($fp);
            return;
        }
        fputcsv($fp, array_keys($rows[0]), ';');
        foreach ($rows as $row) {
            fputcsv($fp, $row, ';');
        }
        fclose($fp);
    }
}
