<?php
/**
 * Agrupa demonstrativos de notas em Oficial (curso regular) vs Extra/Itinerário.
 * Usado no admin (Vida do Aluno) e no portal (aluno/pais).
 */

namespace App\Modulos\boletins\Services;

class NotasDemonstrativoGrupoService
{
    /** @var \App\Core\Database */
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: \App\Core\Database::getInstance();
    }

    /**
     * @param list<array<string,mixed>> $eventos
     * @return list<array<string,mixed>>
     */
    public function enriquecerParaAluno(array $eventos, int $alunoId): array
    {
        if ($eventos === [] || $alunoId <= 0) {
            return $eventos;
        }

        $matriculas = [];
        try {
            $matriculas = $this->db->fetchAll(
                "SELECT m.status, m.turma_id, t.nome AS turma_nome,
                        COALESCE(c.tipo, 'regular') AS curso_tipo
                 FROM matricula m
                 LEFT JOIN turmas t ON t.id = m.turma_id
                 LEFT JOIN curso c ON c.id = t.curso_novo_id
                 WHERE m.aluno_id = :aluno_id
                 ORDER BY m.data_entrada DESC, m.id DESC",
                ['aluno_id' => $alunoId]
            ) ?: [];
        } catch (\Throwable $e) {
            $matriculas = [];
        }

        // Fallback: turma principal do cadastro se não houver matrícula ativa.
        $temAtiva = false;
        foreach ($matriculas as $m) {
            if (is_array($m) && ($m['status'] ?? '') === 'ativa' && (int) ($m['turma_id'] ?? 0) > 0) {
                $temAtiva = true;
                break;
            }
        }
        if (!$temAtiva) {
            try {
                $row = $this->db->fetch(
                    "SELECT a.turma_id, t.nome AS turma_nome, COALESCE(c.tipo, 'regular') AS curso_tipo
                     FROM alunos a
                     LEFT JOIN turmas t ON t.id = a.turma_id
                     LEFT JOIN curso c ON c.id = t.curso_novo_id
                     WHERE a.id = :id",
                    ['id' => $alunoId]
                );
                if (is_array($row) && (int) ($row['turma_id'] ?? 0) > 0) {
                    $matriculas[] = [
                        'status' => 'ativa',
                        'turma_id' => (int) $row['turma_id'],
                        'turma_nome' => (string) ($row['turma_nome'] ?? ''),
                        'curso_tipo' => (string) ($row['curso_tipo'] ?? 'regular'),
                    ];
                }
            } catch (\Throwable $e) {
                // segue sem matrícula
            }
        }

        return $this->enriquecer($eventos, $matriculas);
    }

    /**
     * @param list<array<string,mixed>> $eventos
     * @param list<array<string,mixed>> $matriculas
     * @return list<array<string,mixed>>
     */
    public function enriquecer(array $eventos, array $matriculas): array
    {
        $turmasAtivas = [];
        foreach ($matriculas as $mat) {
            if (!is_array($mat) || ($mat['status'] ?? '') !== 'ativa') {
                continue;
            }
            $tid = (int) ($mat['turma_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $turmasAtivas[$tid] = [
                'curso_tipo' => strtolower(trim((string) ($mat['curso_tipo'] ?? 'regular'))) === 'extra'
                    ? 'extra'
                    : 'regular',
                'turma_nome' => trim((string) ($mat['turma_nome'] ?? ('Turma #' . $tid))),
            ];
        }

        $metaPorRegra = [];
        $idsRegra = [];
        foreach ($eventos as $evPre) {
            if (!is_array($evPre)) {
                continue;
            }
            $ridPre = (int) ($evPre['id'] ?? 0);
            if ($ridPre <= 0) {
                continue;
            }
            $turmasPre = $this->idsLista($evPre['turmas_ids'] ?? null);
            $precisaMeta = $turmasPre === []
                || (int) ($evPre['boletim_id'] ?? 0) <= 0
                || !isset($evPre['finalidade']);
            if ($precisaMeta) {
                $idsRegra[$ridPre] = $ridPre;
            }
        }
        if ($idsRegra !== []) {
            try {
                $colsMeta = 'id, turmas_ids, series_ids';
                if ($this->temColuna('boletim_regras', 'boletim_id')) {
                    $colsMeta .= ', boletim_id';
                }
                if ($this->temColuna('boletim_regras', 'finalidade')) {
                    $colsMeta .= ', finalidade';
                }
                $listaIds = array_values($idsRegra);
                $ph = [];
                $params = [];
                foreach ($listaIds as $i => $ridMeta) {
                    $k = 'r' . $i;
                    $ph[] = ':' . $k;
                    $params[$k] = $ridMeta;
                }
                $rowsMeta = $this->db->fetchAll(
                    'SELECT ' . $colsMeta . ' FROM boletim_regras WHERE id IN (' . implode(',', $ph) . ')',
                    $params
                ) ?: [];
                foreach ($rowsMeta as $rm) {
                    $ridMeta = (int) ($rm['id'] ?? 0);
                    if ($ridMeta <= 0) {
                        continue;
                    }
                    $metaPorRegra[$ridMeta] = [
                        'boletim_id' => (int) ($rm['boletim_id'] ?? 0),
                        'finalidade' => strtolower(trim((string) ($rm['finalidade'] ?? 'oficial'))) === 'complementar'
                            ? 'complementar'
                            : 'oficial',
                        'turmas_ids' => $this->idsLista($rm['turmas_ids'] ?? null),
                        'series_ids' => $this->idsLista($rm['series_ids'] ?? null),
                    ];
                }
            } catch (\Throwable $e) {
                $metaPorRegra = [];
            }
        }

        $boletimIdsUsados = [];
        foreach ($eventos as $evBol) {
            if (!is_array($evBol)) {
                continue;
            }
            $bid = (int) ($evBol['boletim_id'] ?? 0);
            if ($bid <= 0) {
                $ridBol = (int) ($evBol['id'] ?? 0);
                $bid = (int) (($metaPorRegra[$ridBol]['boletim_id'] ?? 0));
            }
            if ($bid > 0) {
                $boletimIdsUsados[$bid] = $bid;
            }
        }

        $nomesBoletim = [];
        if ($boletimIdsUsados !== [] && $this->temTabela('boletins')) {
            try {
                $listaBol = array_values($boletimIdsUsados);
                $phBol = [];
                $paramsBol = [];
                foreach ($listaBol as $i => $bid) {
                    $k = 'b' . $i;
                    $phBol[] = ':' . $k;
                    $paramsBol[$k] = $bid;
                }
                $colsBol = 'id, nome';
                if ($this->temColuna('boletins', 'finalidade')) {
                    $colsBol .= ', finalidade';
                }
                $rowsBol = $this->db->fetchAll(
                    'SELECT ' . $colsBol . ' FROM boletins WHERE id IN (' . implode(',', $phBol) . ')',
                    $paramsBol
                ) ?: [];
                foreach ($rowsBol as $bol) {
                    $bid = (int) ($bol['id'] ?? 0);
                    if ($bid > 0) {
                        $nomesBoletim[$bid] = [
                            'nome' => trim((string) ($bol['nome'] ?? '')),
                            'finalidade' => strtolower(trim((string) ($bol['finalidade'] ?? 'oficial'))) === 'complementar'
                                ? 'complementar'
                                : 'oficial',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $nomesBoletim = [];
            }
        }

        $out = [];
        foreach ($eventos as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $rid = (int) ($ev['id'] ?? 0);
            $meta = $metaPorRegra[$rid] ?? null;
            $turmasRegra = $this->idsLista($ev['turmas_ids'] ?? null);
            if ($turmasRegra === [] && is_array($meta)) {
                $turmasRegra = array_values(array_filter(array_map('intval', (array) ($meta['turmas_ids'] ?? []))));
            }
            $finalidade = strtolower(trim((string) ($ev['finalidade'] ?? ''))) === 'complementar'
                ? 'complementar'
                : 'oficial';
            if ($finalidade === 'oficial' && is_array($meta) && ($meta['finalidade'] ?? '') === 'complementar') {
                $finalidade = 'complementar';
            }
            $boletimId = (int) ($ev['boletim_id'] ?? 0);
            if ($boletimId <= 0 && is_array($meta)) {
                $boletimId = (int) ($meta['boletim_id'] ?? 0);
            }
            if ($boletimId > 0 && isset($nomesBoletim[$boletimId]) && $finalidade === 'oficial') {
                $finalidade = $nomesBoletim[$boletimId]['finalidade'];
            }

            $matchExtra = null;
            $matchRegular = null;
            foreach ($turmasRegra as $tid) {
                if (!isset($turmasAtivas[$tid])) {
                    continue;
                }
                if ($turmasAtivas[$tid]['curso_tipo'] === 'extra') {
                    $matchExtra = $tid;
                    break;
                }
                if ($matchRegular === null) {
                    $matchRegular = $tid;
                }
            }

            // Sem turmas na regra: se o aluno só tem vínculo extra, trata como extra.
            if ($matchExtra === null && $matchRegular === null && $turmasRegra === [] && $turmasAtivas !== []) {
                $soExtra = true;
                $primeiroExtra = null;
                $primeiroRegular = null;
                foreach ($turmasAtivas as $tidAt => $infoAt) {
                    if (($infoAt['curso_tipo'] ?? '') === 'extra') {
                        if ($primeiroExtra === null) {
                            $primeiroExtra = $tidAt;
                        }
                    } else {
                        $soExtra = false;
                        if ($primeiroRegular === null) {
                            $primeiroRegular = $tidAt;
                        }
                    }
                }
                if ($soExtra && $primeiroExtra !== null) {
                    $matchExtra = $primeiroExtra;
                } elseif ($primeiroRegular !== null) {
                    $matchRegular = $primeiroRegular;
                }
            }

            $nomeBol = $nomesBoletim[$boletimId]['nome'] ?? '';
            if ($matchExtra !== null) {
                $chave = 'extra-' . $matchExtra;
                $rotulo = $nomeBol !== '' ? $nomeBol : $turmasAtivas[$matchExtra]['turma_nome'];
                $cursoTipo = 'extra';
            } elseif ($finalidade === 'complementar') {
                $chave = 'extra-' . ($boletimId > 0 ? ('bol-' . $boletimId) : 'geral');
                $rotulo = $nomeBol !== '' ? $nomeBol : 'Curso extra';
                $cursoTipo = 'extra';
            } elseif ($matchRegular !== null) {
                $chave = 'regular-' . $matchRegular;
                $rotulo = $nomeBol !== '' ? $nomeBol : $turmasAtivas[$matchRegular]['turma_nome'];
                $cursoTipo = 'regular';
            } else {
                $chave = 'regular';
                $rotulo = $nomeBol !== '' ? $nomeBol : 'Demonstrativo';
                $cursoTipo = 'regular';
            }
            if ($rotulo === '') {
                $rotulo = $cursoTipo === 'extra' ? 'Curso extra' : 'Demonstrativo';
            }

            // Rótulos amigáveis quando o nome do boletim não deixa claro.
            $rotuloLower = mb_strtolower($rotulo, 'UTF-8');
            if ($cursoTipo === 'extra'
                && strpos($rotuloLower, 'itiner') === false
                && strpos($rotuloLower, 'extra') === false
                && strpos($rotuloLower, 'complement') === false
            ) {
                $rotulo = 'Itinerário / Extra — ' . $rotulo;
            } elseif ($cursoTipo === 'regular'
                && strpos($rotuloLower, 'oficial') === false
                && ($rotulo === 'Demonstrativo' || $nomeBol === '')
            ) {
                $rotulo = 'Oficial';
            }

            $ev['boletim_id'] = $boletimId;
            $ev['turmas_ids'] = $turmasRegra;
            $ev['grupo_chave'] = $chave;
            $ev['grupo_rotulo'] = $rotulo;
            $ev['curso_tipo'] = $cursoTipo;
            $ev['finalidade'] = $finalidade;
            $out[] = $ev;
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function idsLista($raw): array
    {
        $decoded = [];
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parsed = json_decode($raw, true);
            $decoded = is_array($parsed) ? $parsed : (preg_split('/[,\s;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        }
        $ids = [];
        foreach ($decoded as $v) {
            if (is_array($v)) {
                $cand = (int) ($v['id'] ?? $v['turma_id'] ?? $v['serie_id'] ?? $v['value'] ?? 0);
            } else {
                $cand = (int) $v;
            }
            if ($cand > 0) {
                $ids[$cand] = $cand;
            }
        }

        return array_values($ids);
    }

    private function temTabela(string $tabela): bool
    {
        static $cache = [];
        $chave = (defined('TENANT_ID') ? ('t' . TENANT_ID) : 'x') . ':' . $tabela;
        if (array_key_exists($chave, $cache)) {
            return $cache[$chave];
        }
        try {
            $row = $this->db->fetch(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tabela]
            );
            $cache[$chave] = $row !== false && !empty($row);
        } catch (\Throwable $e) {
            $cache[$chave] = false;
        }

        return $cache[$chave];
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        static $cache = [];
        $chave = (defined('TENANT_ID') ? ('t' . TENANT_ID) : 'x') . ':' . $tabela;
        if (!isset($cache[$chave])) {
            $cols = [];
            try {
                $rows = $this->db->fetchAll(
                    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
                    ['t' => $tabela]
                ) ?: [];
                foreach ($rows as $r) {
                    $cols[(string) ($r['COLUMN_NAME'] ?? '')] = true;
                }
            } catch (\Throwable $e) {
                $cols = [];
            }
            $cache[$chave] = $cols;
        }

        return isset($cache[$chave][$coluna]);
    }
}
