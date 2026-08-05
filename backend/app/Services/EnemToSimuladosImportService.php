<?php
/**
 * EducaTudo - Importa dados do dump enem.sql (enem_provas, enem_questoes, enem_alternativas, enem_questoes_arquivos)
 * para as tabelas simulados_* no banco master. Banca ENEM deve existir (id=1).
 */

require_once __DIR__ . '/../Core/EnemQuestaoTextHelper.php';

class EnemToSimuladosImportService
{
    private $db;
    private $bancaEnemId = 1;
    /** exam_id (enem_provas.id) => simulados_provas.id */
    private $provaMap = [];
    /** question_id (enem_questoes.id) => simulados_questoes.id */
    private $questaoMap = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Importa a partir do conteúdo do arquivo enem.sql.
     * @param string $sqlContent Conteúdo do arquivo enem.sql
     * @return array ['provas' => N, 'questoes' => N, 'alternativas' => N, 'arquivos' => N, 'errors' => []]
     */
    public function importFromSqlContent(string $sqlContent): array
    {
        $result = ['provas' => 0, 'questoes' => 0, 'alternativas' => 0, 'arquivos' => 0, 'errors' => []];
        $this->provaMap = [];
        $this->questaoMap = [];

        $this->ensureBancaEnem();

        // 1) Provas: extrair INSERT enem_provas e inserir em simulados_provas
        $provasRows = $this->parseInsertValues($sqlContent, 'enem_provas', ['id', 'title', 'year', 'created_at']);
        foreach ($provasRows as $row) {
            $id = (int) $row[0];
            $title = $this->unquote($row[1] ?? '');
            $year = (int) ($row[2] ?? date('Y'));
            try {
                $newId = (int) $this->db->insert(
                    "INSERT INTO simulados_provas (banca_id, titulo, ano, fase, tipo, descricao, ativo) VALUES (:banca_id, :titulo, :ano, NULL, NULL, NULL, 1)",
                    ['banca_id' => $this->bancaEnemId, 'titulo' => $title, 'ano' => $year]
                );
                $this->provaMap[$id] = $newId;
                $result['provas']++;
            } catch (Exception $e) {
                $result['errors'][] = "Prova {$id}: " . $e->getMessage();
            }
        }

        // 2) Questões: extrair INSERT enem_questoes e inserir em simulados_questoes
        $questoesRows = $this->parseInsertValues($sqlContent, 'enem_questoes', [
            'id', 'exam_id', 'discipline', 'language', 'question_index', 'title', 'context', 'correct_alternative', 'alternatives_introduction', 'year', 'created_at'
        ]);
        foreach ($questoesRows as $row) {
            $id = (int) $row[0];
            $examId = (int) $row[1];
            $provaId = $this->provaMap[$examId] ?? null;
            if (!$provaId) continue;
            $language = $this->unquote($row[3] ?? 'pt');
            $questionIndex = (int) ($row[4] ?? 0);
            $title = $this->unquote($row[5] ?? '');
            $context = $this->unquote($row[6] ?? '');
            $correctAlternative = $this->unquote($row[7] ?? '');
            $alternativesIntroduction = $this->unquote($row[8] ?? '');
            try {
                $enunciado = EnemQuestaoTextHelper::buildStem($context, $alternativesIntroduction);
                $newId = (int) $this->db->insert(
                    "INSERT INTO simulados_questoes (prova_id, indice, titulo, contexto, enunciado, tipo_questao, idioma, gabarito, comentario_resolucao, dificuldade, status) VALUES (:prova_id, :indice, :titulo, :contexto, :enunciado, 'objetiva', :idioma, :gabarito, NULL, NULL, 'published')",
                    [
                        'prova_id' => $provaId,
                        'indice' => $questionIndex,
                        'titulo' => $title,
                        'contexto' => $context,
                        'enunciado' => $enunciado,
                        'idioma' => $language ?: 'pt',
                        'gabarito' => $correctAlternative,
                    ]
                );
                $this->questaoMap[$id] = $newId;
                $result['questoes']++;
            } catch (Exception $e) {
                $result['errors'][] = "Questão {$id}: " . $e->getMessage();
            }
        }

        // 3) Alternativas: enem_alternativas -> simulados_alternativas
        $altRows = $this->parseInsertValues($sqlContent, 'enem_alternativas', ['id', 'question_id', 'letter', 'text', 'file', 'is_correct']);
        $ordem = 0;
        $lastQid = null;
        foreach ($altRows as $row) {
            $questionId = (int) $row[1];
            $questaoId = $this->questaoMap[$questionId] ?? null;
            if (!$questaoId) continue;
            if ($questaoId !== $lastQid) { $ordem = 0; $lastQid = $questaoId; }
            $letra = $this->unquote($row[2] ?? 'A');
            $texto = $this->unquote($row[3] ?? '');
            $file = isset($row[4]) && strtoupper($row[4]) !== 'NULL' ? $this->unquote($row[4]) : null;
            $isCorrect = (int) ($row[5] ?? 0);
            try {
                $this->db->insert(
                    "INSERT INTO simulados_alternativas (questao_id, letra, texto, arquivo, is_correta, ordem) VALUES (:questao_id, :letra, :texto, :arquivo, :is_correta, :ordem)",
                    [
                        'questao_id' => $questaoId,
                        'letra' => $letra ?: 'A',
                        'texto' => $texto,
                        'arquivo' => $file,
                        'is_correta' => $isCorrect,
                        'ordem' => $ordem++,
                    ]
                );
                $result['alternativas']++;
            } catch (Exception $e) {
                $result['errors'][] = "Alternativa question_id={$questionId}: " . $e->getMessage();
            }
        }

        // 4) Arquivos: enem_questoes_arquivos -> simulados_questoes_arquivos
        $arqRows = $this->parseInsertValues($sqlContent, 'enem_questoes_arquivos', ['id', 'question_id', 'file_url']);
        $ordemArq = 0;
        $lastQidArq = null;
        foreach ($arqRows as $row) {
            $questionId = (int) $row[1];
            $questaoId = $this->questaoMap[$questionId] ?? null;
            if (!$questaoId) continue;
            if ($questaoId !== $lastQidArq) { $ordemArq = 0; $lastQidArq = $questaoId; }
            $fileUrl = $this->unquote($row[2] ?? '');
            if ($fileUrl === '') continue;
            try {
                $this->db->insert(
                    "INSERT INTO simulados_questoes_arquivos (questao_id, arquivo_url, tipo_arquivo, ordem, legenda) VALUES (:questao_id, :arquivo_url, 'imagem', :ordem, NULL)",
                    ['questao_id' => $questaoId, 'arquivo_url' => $fileUrl, 'ordem' => $ordemArq++]
                );
                $result['arquivos']++;
            } catch (Exception $e) {
                $result['errors'][] = "Arquivo question_id={$questionId}: " . $e->getMessage();
            }
        }

        return $result;
    }

    private function ensureBancaEnem(): void
    {
        $row = $this->db->fetch("SELECT id FROM simulados_bancas WHERE slug = 'enem' LIMIT 1", []);
        if ($row) {
            $this->bancaEnemId = (int) $row['id'];
            return;
        }
        $this->bancaEnemId = (int) $this->db->insert(
            "INSERT INTO simulados_bancas (nome, slug, descricao, ativo) VALUES ('ENEM', 'enem', 'Exame Nacional do Ensino Médio', 1)",
            []
        );
    }

    /**
     * Extrai linhas (arrays de valores) de todos os INSERT INTO `tableName` (...) VALUES (row1), (row2), ...
     * Suporta múltiplos blocos INSERT no mesmo arquivo. Respeita strings com vírgulas e parênteses.
     */
    private function parseInsertValues(string $sql, string $tableName, array $columnNames): array
    {
        $pattern = '/INSERT\s+INTO\s+[`\']?' . preg_quote($tableName, '/') . '[`\']?\s*\([^)]+\)\s*VALUES\s*/is';
        $offset = 0;
        $allRows = [];
        while (preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1] + strlen($m[0][0]);
            $valuesPart = $this->extractValuesSection($sql, $start);
            if ($valuesPart !== '') {
                $allRows = array_merge($allRows, $this->parseValueRows($valuesPart));
            }
            $offset = $start + strlen($valuesPart) + 1;
            if ($offset >= strlen($sql)) break;
        }
        return $allRows;
    }

    /** Encontra o trecho VALUES (...) até o ); final (fora de strings). */
    private function extractValuesSection(string $sql, int $offset): string
    {
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $escape = false;
        $depth = 0;
        $start = -1;
        for ($i = $offset; $i < $len; $i++) {
            $c = $sql[$i];
            if ($escape) { $escape = false; continue; }
            if ($c === '\\' && ($inSingle || $inDouble)) { $escape = true; continue; }
            if ($c === "'" && !$inDouble) { $inSingle = !$inSingle; continue; }
            if ($c === '"' && !$inSingle) { $inDouble = !$inDouble; continue; }
            if ($inSingle || $inDouble) continue;
            if ($c === '(') {
                // Captura apenas o primeiro parêntese de abertura do bloco VALUES.
                // Antes, este ponteiro era sobrescrito a cada linha e acabava
                // retornando somente o último registro do INSERT.
                if ($depth === 0 && $start === -1) {
                    $start = $i;
                }
                $depth++;
                continue;
            }
            if ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    $i++;
                    while ($i < $len && ($sql[$i] === ' ' || $sql[$i] === "\t" || $sql[$i] === "\n" || $sql[$i] === "\r")) $i++;
                    if ($i < $len && $sql[$i] === ';') {
                        return substr($sql, $start, $i - $start);
                    }
                }
                continue;
            }
        }
        return '';
    }

    /** Divide (row1),(row2) em array de rows; cada row é array de valores. */
    private function parseValueRows(string $valuesPart): array
    {
        $rows = [];
        $len = strlen($valuesPart);
        $inSingle = false;
        $inDouble = false;
        $escape = false;
        $depth = 0;
        $rowStart = -1;
        for ($i = 0; $i < $len; $i++) {
            $c = $valuesPart[$i];
            if ($escape) { $escape = false; continue; }
            if ($c === '\\' && ($inSingle || $inDouble)) { $escape = true; continue; }
            if ($c === "'" && !$inDouble) { $inSingle = !$inSingle; continue; }
            if ($c === '"' && !$inSingle) { $inDouble = !$inDouble; continue; }
            if ($inSingle || $inDouble) continue;
            if ($c === '(') {
                if ($depth === 0) $rowStart = $i + 1;
                $depth++;
                continue;
            }
            if ($c === ')') {
                $depth--;
                if ($depth === 0 && $rowStart >= 0) {
                    $rowStr = substr($valuesPart, $rowStart, $i - $rowStart);
                    $rows[] = $this->parseRowValues($rowStr);
                }
                continue;
            }
        }
        return $rows;
    }

    /** Divide uma linha "(v1, v2, 'x,y', NULL)" em ['v1','v2','x,y', null]. */
    private function parseRowValues(string $rowStr): array
    {
        $values = [];
        $len = strlen($rowStr);
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $escape = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $rowStr[$i];
            if ($escape) { $current .= $c; $escape = false; continue; }
            if ($c === '\\' && ($inSingle || $inDouble)) { $escape = true; continue; }
            if ($c === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                if (!$inSingle) {
                    $values[] = $current;
                    $current = '';
                }
                continue;
            }
            if ($c === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                if (!$inDouble) {
                    $values[] = $current;
                    $current = '';
                }
                continue;
            }
            if ($inSingle || $inDouble) {
                $current .= $c;
                continue;
            }
            if ($c === ',') {
                if ($current !== '') {
                    $values[] = strtoupper(trim($current)) === 'NULL' ? null : trim($current);
                }
                $current = '';
                continue;
            }
            $current .= $c;
        }
        if ($current !== '') {
            $values[] = strtoupper(trim($current)) === 'NULL' ? null : trim($current);
        }
        return $values;
    }

    private function unquote(?string $v): string
    {
        if ($v === null || $v === '') return '';
        $v = trim($v);
        if ((strpos($v, "'") === 0 && substr($v, -1) === "'") || (strpos($v, '"') === 0 && substr($v, -1) === '"')) {
            $v = substr($v, 1, -1);
        }
        return str_replace(["\\'", '\\"', '\\\\'], ["'", '"', '\\'], $v);
    }
}
