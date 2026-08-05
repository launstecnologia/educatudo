<?php
/**
 * Consulta de desempenho das provas sob a ótica do professor (somente leitura).
 * Usado pelo Assistente admin e pelo MCP.
 */

require_once __DIR__ . '/SaudeAprendizagemService.php';

use App\Services\SaudeAprendizagemService;

class ProvasProfessorConsultaService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return list<array{id:int,nome:string,email:?string,codigo_prof:?string}>
     */
    public function buscarProfessores(string $termo, int $limit = 20): array
    {
        $termo = trim($termo);
        if ($termo === '' || mb_strlen($termo) < 2) {
            return [];
        }
        $limit = max(1, min($limit, 50));
        $like = '%' . $termo . '%';
        $rows = $this->db->fetchAll(
            "SELECT p.id, p.nome, p.email, p.codigo_prof
             FROM professores p
             WHERE (p.ativo = 1 OR p.ativo IS NULL)
               AND (p.nome LIKE :q OR p.email LIKE :q2 OR p.codigo_prof LIKE :q3)
             ORDER BY p.nome ASC
             LIMIT {$limit}",
            ['q' => $like, 'q2' => $like, 'q3' => $like]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($r['nome'] ?? '')),
                'email' => isset($r['email']) && $r['email'] !== '' ? trim((string) $r['email']) : null,
                'codigo_prof' => isset($r['codigo_prof']) && $r['codigo_prof'] !== ''
                    ? trim((string) $r['codigo_prof']) : null,
            ];
        }
        return $out;
    }

    /** @return array{id:int,nome:string,email:?string,codigo_prof:?string}|null */
    public function obterProfessor(int $professorId): ?array
    {
        if ($professorId <= 0) {
            return null;
        }
        $r = $this->db->fetch(
            'SELECT id, nome, email, codigo_prof FROM professores
             WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1',
            ['id' => $professorId]
        );
        if (!$r) {
            return null;
        }
        return [
            'id' => (int) $r['id'],
            'nome' => trim((string) ($r['nome'] ?? '')),
            'email' => isset($r['email']) && $r['email'] !== '' ? trim((string) $r['email']) : null,
            'codigo_prof' => isset($r['codigo_prof']) && $r['codigo_prof'] !== ''
                ? trim((string) $r['codigo_prof']) : null,
        ];
    }

    /**
     * Resolve professor por id ou nome (ambíguo → candidatos).
     *
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,professor?:array,candidatos?:list,aviso?:string,error?:string}
     */
    public function resolverProfessor(array $filtros): array
    {
        $id = (int) ($filtros['professor_id'] ?? 0);
        if ($id > 0) {
            $p = $this->obterProfessor($id);
            if ($p === null) {
                return ['ok' => false, 'error' => 'Professor não encontrado.'];
            }
            return ['ok' => true, 'professor' => $p];
        }
        $nome = trim((string) ($filtros['professor_nome'] ?? $filtros['nome'] ?? ''));
        if ($nome === '') {
            return ['ok' => false, 'error' => 'Informe professor_id ou professor_nome.'];
        }
        $lista = $this->buscarProfessores($nome, 15);
        if ($lista === []) {
            return ['ok' => false, 'error' => 'Nenhum professor encontrado com esse nome.'];
        }
        if (count($lista) > 1) {
            return [
                'ok' => false,
                'aviso' => 'Há mais de um professor. Escolha um:',
                'candidatos' => $lista,
            ];
        }
        return ['ok' => true, 'professor' => $lista[0]];
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function listarTurmasProfessor(int $professorId): array
    {
        if ($professorId <= 0) {
            return [];
        }
        $ids = [];

        try {
            if ($this->temTabela('grade_horaria')) {
                $rows = $this->db->fetchAll(
                    'SELECT DISTINCT turma_id FROM grade_horaria
                     WHERE professor_id = :pid AND turma_id IS NOT NULL',
                    ['pid' => $professorId]
                ) ?: [];
                foreach ($rows as $r) {
                    $tid = (int) ($r['turma_id'] ?? 0);
                    if ($tid > 0) {
                        $ids[$tid] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            // ignora
        }

        $rows = $this->db->fetchAll(
            'SELECT DISTINCT COALESCE(pt.turma_id, p.turma_id) AS turma_id
             FROM provas p
             LEFT JOIN provas_turmas pt ON pt.prova_id = p.id
             WHERE p.professor_id = :pid
               AND p.deleted_at IS NULL
               AND (p.ativo = 1 OR p.ativo IS NULL)',
            ['pid' => $professorId]
        ) ?: [];
        foreach ($rows as $r) {
            $tid = (int) ($r['turma_id'] ?? 0);
            if ($tid > 0) {
                $ids[$tid] = true;
            }
        }

        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $turmas = $this->db->fetchAll(
            "SELECT id, nome FROM turmas WHERE id IN ({$placeholders}) ORDER BY nome ASC",
            array_keys($ids)
        ) ?: [];
        $out = [];
        foreach ($turmas as $t) {
            $out[] = [
                'id' => (int) $t['id'],
                'nome' => trim((string) ($t['nome'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,professor?:array,provas?:list,total?:int,candidatos?:list,error?:string}
     */
    public function listarProvasProfessor(array $filtros): array
    {
        $resolvido = $this->resolverProfessor($filtros);
        if (!empty($resolvido['candidatos'])) {
            return $resolvido;
        }
        if (empty($resolvido['ok'])) {
            return $resolvido;
        }
        $professor = $resolvido['professor'];
        $professorId = (int) $professor['id'];
        $limite = max(1, min(100, (int) ($filtros['limite'] ?? 40)));
        $turmaId = (int) ($filtros['turma_id'] ?? 0);
        $turmaNome = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));
        $materiaNome = trim((string) ($filtros['materia_nome'] ?? $filtros['materia'] ?? ''));
        $dataInicio = $this->normalizarData($filtros['data_inicio'] ?? null);
        $dataFim = $this->normalizarData($filtros['data_fim'] ?? null);

        if ($turmaId <= 0 && $turmaNome !== '') {
            $turmaId = $this->resolverTurmaId($turmaNome);
        }

        $sql = "SELECT p.id, p.titulo, p.materia_id, p.turma_id, p.data_inicio, p.data_fim, p.created_at,
                       m.nome AS materia_nome, t.nome AS turma_nome
                FROM provas p
                LEFT JOIN materias m ON m.id = p.materia_id
                LEFT JOIN turmas t ON t.id = p.turma_id
                WHERE p.professor_id = :pid
                  AND p.deleted_at IS NULL
                  AND (p.ativo = 1 OR p.ativo IS NULL)";
        $params = ['pid' => $professorId];

        if ($turmaId > 0) {
            $sql .= ' AND (p.turma_id = :tid OR EXISTS (
                        SELECT 1 FROM provas_turmas pt WHERE pt.prova_id = p.id AND pt.turma_id = :tid2
                      ))';
            $params['tid'] = $turmaId;
            $params['tid2'] = $turmaId;
        }
        if ($materiaNome !== '') {
            $sql .= ' AND m.nome LIKE :mat';
            $params['mat'] = '%' . $materiaNome . '%';
        }
        if ($dataInicio !== null) {
            $sql .= ' AND COALESCE(DATE(p.data_inicio), DATE(p.created_at)) >= :di';
            $params['di'] = $dataInicio;
        }
        if ($dataFim !== null) {
            $sql .= ' AND COALESCE(DATE(p.data_inicio), DATE(p.created_at)) <= :df';
            $params['df'] = $dataFim;
        }
        $sql .= " ORDER BY COALESCE(p.data_inicio, p.created_at) DESC LIMIT {$limite}";

        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $provas = [];
        foreach ($rows as $r) {
            $provaId = (int) ($r['id'] ?? 0);
            if ($provaId <= 0) {
                continue;
            }
            $agg = $this->agregarProva($provaId);
            $provas[] = [
                'prova_id' => $provaId,
                'titulo' => trim((string) ($r['titulo'] ?? '')),
                'materia' => [
                    'id' => isset($r['materia_id']) ? (int) $r['materia_id'] : null,
                    'nome' => isset($r['materia_nome']) ? trim((string) $r['materia_nome']) : null,
                ],
                'turma' => [
                    'id' => isset($r['turma_id']) ? (int) $r['turma_id'] : null,
                    'nome' => isset($r['turma_nome']) ? trim((string) $r['turma_nome']) : null,
                ],
                'data' => isset($r['data_inicio']) ? (string) $r['data_inicio'] : null,
                'alunos_finalizaram' => $agg['alunos_finalizaram'],
                'acertos' => $agg['acertos'],
                'erros' => $agg['erros'],
                'percentual_acerto' => $agg['percentual_acerto'],
                'media_nota' => $agg['media_nota'],
            ];
        }

        return [
            'ok' => true,
            'professor' => $professor,
            'provas' => $provas,
            'total' => count($provas),
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{ok:bool,professor?:array,total_provas?:int,total_acertos?:int,total_erros?:int,por_materia?:list,por_turma?:list,candidatos?:list,error?:string}
     */
    public function resumoProvasProfessor(array $filtros): array
    {
        $filtros['limite'] = min(100, (int) ($filtros['limite'] ?? 100));
        $lista = $this->listarProvasProfessor($filtros);
        if (empty($lista['ok'])) {
            return $lista;
        }
        if (!empty($lista['candidatos'])) {
            return $lista;
        }

        $porMateria = [];
        $porTurma = [];
        $totalAcertos = 0;
        $totalErros = 0;
        $visto = [];

        foreach ($lista['provas'] as $p) {
            $pid = (int) ($p['prova_id'] ?? 0);
            if ($pid > 0 && isset($visto[$pid])) {
                continue;
            }
            if ($pid > 0) {
                $visto[$pid] = true;
            }
            $acertos = (int) ($p['acertos'] ?? 0);
            $erros = (int) ($p['erros'] ?? 0);
            $totalAcertos += $acertos;
            $totalErros += $erros;

            $mat = (string) ($p['materia']['nome'] ?? 'Sem matéria');
            $tur = (string) ($p['turma']['nome'] ?? 'Sem turma');
            if (!isset($porMateria[$mat])) {
                $porMateria[$mat] = ['materia' => $mat, 'provas' => 0, 'acertos' => 0, 'erros' => 0];
            }
            if (!isset($porTurma[$tur])) {
                $porTurma[$tur] = ['turma' => $tur, 'provas' => 0, 'acertos' => 0, 'erros' => 0];
            }
            $porMateria[$mat]['provas']++;
            $porMateria[$mat]['acertos'] += $acertos;
            $porMateria[$mat]['erros'] += $erros;
            $porTurma[$tur]['provas']++;
            $porTurma[$tur]['acertos'] += $acertos;
            $porTurma[$tur]['erros'] += $erros;
        }

        $totalQ = $totalAcertos + $totalErros;
        return [
            'ok' => true,
            'professor' => $lista['professor'],
            'total_provas' => count($visto) ?: (int) ($lista['total'] ?? 0),
            'total_acertos' => $totalAcertos,
            'total_erros' => $totalErros,
            'total_questoes' => $totalQ,
            'percentual_acerto' => $totalQ > 0 ? round(($totalAcertos / $totalQ) * 100, 1) : null,
            'por_materia' => array_values($porMateria),
            'por_turma' => array_values($porTurma),
            'turmas' => $this->listarTurmasProfessor((int) $lista['professor']['id']),
        ];
    }

    /**
     * Alunos + acertos/erros de uma prova do professor.
     *
     * @return array{ok:bool,professor?:array,detalhe?:array,error?:string}
     */
    public function detalharProvaProfessor(int $professorId, int $provaId): array
    {
        if ($provaId <= 0) {
            return ['ok' => false, 'error' => 'prova_id inválido.'];
        }
        $prova = $this->db->fetch(
            "SELECT p.id, p.titulo, p.professor_id, p.materia_id, p.turma_id, p.data_inicio,
                    m.nome AS materia_nome, t.nome AS turma_nome
             FROM provas p
             LEFT JOIN materias m ON m.id = p.materia_id
             LEFT JOIN turmas t ON t.id = p.turma_id
             WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1",
            ['id' => $provaId]
        );
        if (!$prova) {
            return ['ok' => false, 'error' => 'Prova não encontrada.'];
        }
        if ($professorId <= 0) {
            $professorId = (int) ($prova['professor_id'] ?? 0);
        }
        $professor = $this->obterProfessor($professorId);
        if ($professor === null) {
            return ['ok' => false, 'error' => 'Professor não encontrado.'];
        }
        if ((int) ($prova['professor_id'] ?? 0) !== $professorId) {
            return ['ok' => false, 'error' => 'Prova não encontrada para este professor.'];
        }

        $rows = $this->db->fetchAll(
            "SELECT pr.aluno_id, pr.status, pr.nota, pr.finalizado_em,
                    a.nome AS aluno_nome, a.ra, t.nome AS turma_nome,
                    COALESCE((
                        SELECT COUNT(*) FROM provas_respostas r1
                        WHERE r1.prova_id = pr.prova_id AND r1.aluno_id = pr.aluno_id AND r1.correta = 1
                    ), 0) AS acertos,
                    COALESCE((
                        SELECT COUNT(*) FROM provas_respostas r2
                        WHERE r2.prova_id = pr.prova_id AND r2.aluno_id = pr.aluno_id AND r2.correta = 0
                    ), 0) AS erros
             FROM provas_realizacoes pr
             LEFT JOIN alunos a ON a.id = pr.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE pr.prova_id = :pid
             ORDER BY a.nome ASC
             LIMIT 300",
            ['pid' => $provaId]
        ) ?: [];

        $alunos = [];
        $acertos = 0;
        $erros = 0;
        foreach ($rows as $r) {
            $a = (int) ($r['acertos'] ?? 0);
            $e = (int) ($r['erros'] ?? 0);
            $acertos += $a;
            $erros += $e;
            $tot = $a + $e;
            $alunos[] = [
                'aluno_id' => (int) ($r['aluno_id'] ?? 0),
                'nome' => trim((string) ($r['aluno_nome'] ?? '')),
                'ra' => isset($r['ra']) ? trim((string) $r['ra']) : null,
                'turma_nome' => isset($r['turma_nome']) ? trim((string) $r['turma_nome']) : null,
                'status' => (string) ($r['status'] ?? ''),
                'nota' => isset($r['nota']) && $r['nota'] !== '' ? (float) $r['nota'] : null,
                'acertos' => $a,
                'erros' => $e,
                'percentual_acerto' => $tot > 0 ? round(($a / $tot) * 100, 1) : null,
                'finalizado_em' => isset($r['finalizado_em']) ? (string) $r['finalizado_em'] : null,
            ];
        }

        $totQ = $acertos + $erros;
        return [
            'ok' => true,
            'professor' => $professor,
            'detalhe' => [
                'prova_id' => $provaId,
                'titulo' => trim((string) ($prova['titulo'] ?? '')),
                'materia' => trim((string) ($prova['materia_nome'] ?? '')),
                'turma' => trim((string) ($prova['turma_nome'] ?? '')),
                'data' => isset($prova['data_inicio']) ? (string) $prova['data_inicio'] : null,
                'total_acertos' => $acertos,
                'total_erros' => $erros,
                'percentual_acerto' => $totQ > 0 ? round(($acertos / $totQ) * 100, 1) : null,
                'alunos' => $alunos,
                'total_alunos' => count($alunos),
            ],
        ];
    }

    /**
     * Questões mais erradas da prova (visão professor).
     *
     * @return array{ok:bool,professor?:array,prova?:array,questoes?:list,error?:string}
     */
    public function rankingErrosProva(int $professorId, int $provaId, int $limite = 15): array
    {
        if ($provaId <= 0) {
            return ['ok' => false, 'error' => 'prova_id inválido.'];
        }
        $prova = $this->db->fetch(
            'SELECT id, titulo, professor_id FROM provas
             WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $provaId]
        );
        if (!$prova) {
            return ['ok' => false, 'error' => 'Prova não encontrada.'];
        }
        if ($professorId <= 0) {
            $professorId = (int) ($prova['professor_id'] ?? 0);
        }
        $professor = $this->obterProfessor($professorId);
        if ($professor === null) {
            return ['ok' => false, 'error' => 'Professor não encontrado.'];
        }
        if ((int) ($prova['professor_id'] ?? 0) !== $professorId) {
            return ['ok' => false, 'error' => 'Prova não encontrada para este professor.'];
        }
        $limite = max(1, min(50, $limite));
        $rows = $this->db->fetchAll(
            "SELECT q.id AS questao_id, q.ordem, q.enunciado,
                    SUM(CASE WHEN r.correta = 0 THEN 1 ELSE 0 END) AS erros,
                    SUM(CASE WHEN r.correta = 1 THEN 1 ELSE 0 END) AS acertos,
                    COUNT(r.id) AS total_respostas
             FROM provas_questoes q
             LEFT JOIN provas_respostas r ON r.questao_id = q.id AND r.prova_id = q.prova_id
             WHERE q.prova_id = :pid
             GROUP BY q.id, q.ordem, q.enunciado
             HAVING erros > 0
             ORDER BY erros DESC, q.ordem ASC
             LIMIT {$limite}",
            ['pid' => $provaId]
        ) ?: [];

        $questoes = [];
        foreach ($rows as $i => $r) {
            $enunciado = html_entity_decode(strip_tags((string) ($r['enunciado'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $enunciado = preg_replace('/\s+/u', ' ', trim($enunciado)) ?? trim($enunciado);
            if (mb_strlen($enunciado) > 180) {
                $enunciado = mb_substr($enunciado, 0, 179) . '…';
            }
            $erros = (int) ($r['erros'] ?? 0);
            $acertos = (int) ($r['acertos'] ?? 0);
            $tot = $erros + $acertos;
            $questoes[] = [
                'posicao' => $i + 1,
                'questao_id' => (int) ($r['questao_id'] ?? 0),
                'ordem' => isset($r['ordem']) ? (int) $r['ordem'] : null,
                'enunciado' => $enunciado,
                'erros' => $erros,
                'acertos' => $acertos,
                'percentual_erro' => $tot > 0 ? round(($erros / $tot) * 100, 1) : null,
            ];
        }

        return [
            'ok' => true,
            'professor' => $professor,
            'prova' => [
                'prova_id' => $provaId,
                'titulo' => trim((string) ($prova['titulo'] ?? '')),
            ],
            'questoes' => $questoes,
        ];
    }

    /**
     * Saúde educacional das turmas do professor (wrapper SaudeAprendizagemService).
     *
     * @return array{ok:bool,professor?:array,ano_letivo?:array,kpis?:array,alunos_atencao?:list,turmas?:list,error?:string}
     */
    public function saudeTurmasProfessor(array $filtros): array
    {
        $resolvido = $this->resolverProfessor($filtros);
        if (!empty($resolvido['candidatos'])) {
            return $resolvido;
        }
        if (empty($resolvido['ok'])) {
            return $resolvido;
        }
        $professor = $resolvido['professor'];
        $professorId = (int) $professor['id'];

        $turmaId = (int) ($filtros['turma_id'] ?? 0);
        $turmaNome = trim((string) ($filtros['turma_nome'] ?? $filtros['turma'] ?? ''));
        if ($turmaId <= 0 && $turmaNome !== '') {
            $turmaId = $this->resolverTurmaId($turmaNome);
        }

        $turmasProf = $this->listarTurmasProfessor($professorId);
        $turmaIdsOk = [];
        foreach ($turmasProf as $t) {
            $turmaIdsOk[(int) $t['id']] = $t['nome'];
        }
        if ($turmaId > 0 && !isset($turmaIdsOk[$turmaId])) {
            // ainda permite se o professor tem prova na turma
            $temProva = $this->db->fetch(
                'SELECT 1 AS ok FROM provas p
                 LEFT JOIN provas_turmas pt ON pt.prova_id = p.id
                 WHERE p.professor_id = :pid AND p.deleted_at IS NULL
                   AND (p.turma_id = :tid OR pt.turma_id = :tid2)
                 LIMIT 1',
                ['pid' => $professorId, 'tid' => $turmaId, 'tid2' => $turmaId]
            );
            if (!$temProva) {
                return ['ok' => false, 'error' => 'Turma não vinculada a este professor.'];
            }
            $turmaIdsOk[$turmaId] = $turmaNome !== '' ? $turmaNome : ('Turma #' . $turmaId);
        }

        if ($turmaId <= 0 && $turmaIdsOk === []) {
            return [
                'ok' => false,
                'error' => 'Não encontrei turmas vinculadas a este professor (grade ou provas). Informe turma_nome.',
            ];
        }

        $ano = $this->anoLetivoAtual((int) ($filtros['ano_letivo_id'] ?? 0));
        if ($ano === null) {
            return ['ok' => false, 'error' => 'Ano letivo não encontrado.'];
        }

        $saude = new SaudeAprendizagemService();
        $nivelFiltro = trim((string) ($filtros['nivel'] ?? ''));
        if ($nivelFiltro !== '' && !in_array($nivelFiltro, SaudeAprendizagemService::NIVEIS, true)) {
            $nivelFiltro = '';
        }

        $resultado = $saude->analisar(
            (int) $ano['id'],
            $turmaId > 0 ? $turmaId : 0,
            $nivelFiltro !== '' ? $nivelFiltro : null
        );

        // Restringe às turmas do professor (nunca escola inteira).
        $linhas = $resultado['linhas'] ?? [];
        if ($turmaId > 0) {
            $linhas = array_values(array_filter($linhas, static function ($l) use ($turmaId) {
                return (int) ($l['turma_id'] ?? 0) === $turmaId;
            }));
        } else {
            $linhas = array_values(array_filter($linhas, static function ($l) use ($turmaIdsOk) {
                return isset($turmaIdsOk[(int) ($l['turma_id'] ?? 0)]);
            }));
        }

        $kpis = array_fill_keys(array_merge(['total'], SaudeAprendizagemService::NIVEIS), 0);
        $atencao = [];
        foreach ($linhas as $l) {
            $nivel = (string) ($l['nivel'] ?? SaudeAprendizagemService::NIVEL_SEM_DADOS);
            $kpis['total']++;
            if (isset($kpis[$nivel])) {
                $kpis[$nivel]++;
            }
            if (in_array($nivel, [SaudeAprendizagemService::NIVEL_CRITICO, SaudeAprendizagemService::NIVEL_ATENCAO], true)) {
                $atencao[] = [
                    'aluno_id' => (int) ($l['aluno_id'] ?? 0),
                    'nome' => trim((string) ($l['nome'] ?? $l['aluno_nome'] ?? '')),
                    'turma_nome' => trim((string) ($l['turma_nome'] ?? '')),
                    'nivel' => $nivel,
                    'nivel_rotulo' => $saude->rotuloNivel($nivel),
                    'provas_media' => $l['provas_media'] ?? $l['notas_media'] ?? null,
                    'provas_indice_pct' => $l['provas_indice_pct'] ?? $l['notas_indice_pct'] ?? null,
                ];
            }
        }
        usort($atencao, static function ($a, $b) {
            $ordem = ['critico' => 0, 'atencao' => 1];
            return ($ordem[$a['nivel']] ?? 9) <=> ($ordem[$b['nivel']] ?? 9);
        });

        return [
            'ok' => true,
            'professor' => $professor,
            'ano_letivo' => $ano,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'turmas' => $turmasProf,
            'kpis' => $kpis,
            'alunos_atencao' => array_slice($atencao, 0, 40),
            'total_alunos' => count($linhas),
        ];
    }

    /** @return array{alunos_finalizaram:int,acertos:int,erros:int,percentual_acerto:?float,media_nota:?float} */
    private function agregarProva(int $provaId): array
    {
        $row = $this->db->fetch(
            "SELECT
                COUNT(DISTINCT CASE WHEN pr.status = 'finalizado' THEN pr.aluno_id END) AS alunos_finalizaram,
                COALESCE(SUM(CASE WHEN r.correta = 1 THEN 1 ELSE 0 END), 0) AS acertos,
                COALESCE(SUM(CASE WHEN r.correta = 0 THEN 1 ELSE 0 END), 0) AS erros,
                AVG(CASE WHEN pr.status = 'finalizado' AND pr.nota IS NOT NULL THEN pr.nota END) AS media_nota
             FROM provas_realizacoes pr
             LEFT JOIN provas_respostas r
               ON r.prova_id = pr.prova_id AND r.aluno_id = pr.aluno_id
             WHERE pr.prova_id = :pid",
            ['pid' => $provaId]
        ) ?: [];

        $acertos = (int) ($row['acertos'] ?? 0);
        $erros = (int) ($row['erros'] ?? 0);
        $tot = $acertos + $erros;
        return [
            'alunos_finalizaram' => (int) ($row['alunos_finalizaram'] ?? 0),
            'acertos' => $acertos,
            'erros' => $erros,
            'percentual_acerto' => $tot > 0 ? round(($acertos / $tot) * 100, 1) : null,
            'media_nota' => isset($row['media_nota']) && $row['media_nota'] !== null
                ? round((float) $row['media_nota'], 2) : null,
        ];
    }

    private function resolverTurmaId(string $nome): int
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 0;
        }
        if (!class_exists('ProvasAlunoConsultaService', false)) {
            require_once __DIR__ . '/ProvasAlunoConsultaService.php';
        }
        $cands = (new ProvasAlunoConsultaService())->buscarTurmasPorNome($nome, 5);
        if (count($cands) === 1) {
            return (int) $cands[0]['id'];
        }
        return 0;
    }

    /** @return array{id:int,ano:int}|null */
    private function anoLetivoAtual(int $preferido = 0): ?array
    {
        if ($preferido > 0) {
            $r = $this->db->fetch(
                'SELECT id, ano FROM ano_letivo WHERE id = :id LIMIT 1',
                ['id' => $preferido]
            );
            if ($r) {
                return ['id' => (int) $r['id'], 'ano' => (int) $r['ano']];
            }
        }
        try {
            $r = $this->db->fetch(
                'SELECT id, ano FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1'
            );
            if ($r) {
                return ['id' => (int) $r['id'], 'ano' => (int) $r['ano']];
            }
        } catch (Throwable $e) {
            // coluna ativo pode não existir
        }
        $r = $this->db->fetch('SELECT id, ano FROM ano_letivo ORDER BY ano DESC LIMIT 1');
        if (!$r) {
            return null;
        }
        return ['id' => (int) $r['id'], 'ano' => (int) $r['ano']];
    }

    private function normalizarData($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim((string) $raw);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }
        return null;
    }

    private function normalizarTexto(string $texto): string
    {
        $s = mb_strtolower(trim($texto));
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') {
                $s = $t;
            }
        }
        return preg_replace('/[^a-z0-9]+/', '', $s) ?? $s;
    }

    private function temTabela(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            if (method_exists($this->db, 'tableExists')) {
                $cache[$table] = (bool) $this->db->tableExists($table);
            } else {
                $row = $this->db->fetch(
                    'SELECT 1 AS ok FROM information_schema.tables
                     WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                    ['t' => $table]
                );
                $cache[$table] = !empty($row);
            }
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}
