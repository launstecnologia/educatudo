<?php
/**
 * EducaTudo - Modelo de Turmas
 * Gerencia operações de banco de dados para turmas
 */

class ClassRoom
{
    private $db;
    private $cursosSupported = null;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca todas as turmas. Quando $limit é informado, pagina (LIMIT/OFFSET).
     */
    public function getAll(?int $limit = null, int $offset = 0)
    {
        $limitSql = $limit !== null ? ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset : '';

        if ($this->supportsCursos()) {
            return $this->db->fetchAll(
                "SELECT t.*, c.nome AS curso_nome, c.tipo_curso_id, tc.nome AS tipo_curso_nome
                 FROM turmas t
                 LEFT JOIN cursos c ON c.id = t.curso_id
                 LEFT JOIN tipos_curso tc ON tc.id = c.tipo_curso_id
                 ORDER BY t.ano_letivo ASC, t.serie ASC, t.nome ASC" . $limitSql
            );
        }

        return $this->db->fetchAll(
            "SELECT * FROM turmas ORDER BY ano_letivo ASC, serie ASC, nome ASC" . $limitSql
        );
    }

    /**
     * Conta o total de turmas (para paginação).
     */
    public function countAll(): int
    {
        $row = $this->db->fetch("SELECT COUNT(*) AS total FROM turmas");
        return (int)($row['total'] ?? 0);
    }

    /**
     * Busca turmas ativas
     */
    public function getActive()
    {
        if ($this->supportsCursos()) {
            return $this->db->fetchAll(
                "SELECT t.*, c.nome AS curso_nome, c.tipo_curso_id, tc.nome AS tipo_curso_nome
                 FROM turmas t
                 LEFT JOIN cursos c ON c.id = t.curso_id
                 LEFT JOIN tipos_curso tc ON tc.id = c.tipo_curso_id
                 WHERE t.ativo = 1
                 ORDER BY t.ano_letivo ASC, t.serie ASC, t.nome ASC"
            );
        }

        return $this->db->fetchAll(
            "SELECT * FROM turmas WHERE ativo = 1 ORDER BY ano_letivo ASC, serie ASC, nome ASC"
        );
    }
    
    /**
     * Busca turma por ID
     */
    public function findById($id)
    {
        if ($this->supportsCursos()) {
            return $this->db->fetch(
                "SELECT t.*, c.nome AS curso_nome, c.tipo_curso_id, tc.nome AS tipo_curso_nome
                 FROM turmas t
                 LEFT JOIN cursos c ON c.id = t.curso_id
                 LEFT JOIN tipos_curso tc ON tc.id = c.tipo_curso_id
                 WHERE t.id = :id",
                ['id' => $id]
            );
        }

        return $this->db->fetch(
            "SELECT * FROM turmas WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Cria nova turma (suporta estrutura nova: curso_novo_id, serie_id, ano_letivo_id).
     */
    public function create($data)
    {
        $useNovo = $this->supportsCursoNovo() && isset($data['curso_novo_id']) && (int)$data['curso_novo_id'] > 0;

        if ($useNovo) {
            $ano = (int)($data['ano_letivo'] ?? date('Y'));
            $anoLetivoId = $data['ano_letivo_id'] ?? $this->getAnoLetivoIdByAno($ano);
            $serieNome = $data['serie'] ?? '';
            if (!empty($data['serie_id'])) {
                $s = $this->db->fetch("SELECT nome FROM serie WHERE id = :id", ['id' => (int)$data['serie_id']]);
                if ($s) {
                    $serieNome = $s['nome'];
                }
            }
            if ($serieNome === '' && !empty($data['curso_novo_id'])) {
                $c = $this->db->fetch("SELECT nome FROM curso WHERE id = :id", ['id' => (int)$data['curso_novo_id']]);
                if ($c) {
                    $serieNome = $c['nome'];
                }
            }
            $sql = "INSERT INTO turmas (nome, ano_letivo, serie, ativo, curso_novo_id, serie_id, ano_letivo_id) VALUES (:nome, :ano_letivo, :serie, :ativo, :curso_novo_id, :serie_id, :ano_letivo_id)";
            $id = $this->db->insert($sql, [
                'nome' => $data['nome'],
                'ano_letivo' => $ano,
                'serie' => $serieNome,
                'ativo' => $data['ativo'] ?? 1,
                'curso_novo_id' => (int)$data['curso_novo_id'],
                'serie_id' => isset($data['serie_id']) && $data['serie_id'] !== '' ? (int)$data['serie_id'] : null,
                'ano_letivo_id' => $anoLetivoId ? (int)$anoLetivoId : null
            ]);
            $this->aplicarCamposExtras((int) $id, $data);
            return $id;
        }

        if ($this->supportsCursos()) {
            $sql = "INSERT INTO turmas (nome, ano_letivo, serie, curso_id, ativo) VALUES (:nome, :ano_letivo, :serie, :curso_id, :ativo)";
            $id = $this->db->insert($sql, [
                'nome' => $data['nome'],
                'ano_letivo' => $data['ano_letivo'],
                'serie' => $data['serie'],
                'curso_id' => $data['curso_id'] ?? null,
                'ativo' => $data['ativo'] ?? 1
            ]);
            $this->aplicarCamposExtras((int) $id, $data);
            return $id;
        }

        $sql = "INSERT INTO turmas (nome, ano_letivo, serie, ativo) VALUES (:nome, :ano_letivo, :serie, :ativo)";
        $id = $this->db->insert($sql, [
            'nome' => $data['nome'],
            'ano_letivo' => $data['ano_letivo'],
            'serie' => $data['serie'],
            'ativo' => $data['ativo'] ?? 1
        ]);
        $this->aplicarCamposExtras((int) $id, $data);
        return $id;
    }
    
    /**
     * Atualiza turma (suporta estrutura nova: curso_novo_id, serie_id, ano_letivo_id).
     */
    public function update($id, $data)
    {
        $useNovo = $this->supportsCursoNovo() && isset($data['curso_novo_id']) && (int)$data['curso_novo_id'] > 0;

        if ($useNovo) {
            $ano = (int)($data['ano_letivo'] ?? date('Y'));
            $anoLetivoId = $data['ano_letivo_id'] ?? $this->getAnoLetivoIdByAno($ano);
            $serieNome = $data['serie'] ?? '';
            if (!empty($data['serie_id'])) {
                $s = $this->db->fetch("SELECT nome FROM serie WHERE id = :id", ['id' => (int)$data['serie_id']]);
                if ($s) {
                    $serieNome = $s['nome'];
                }
            }
            if ($serieNome === '' && !empty($data['curso_novo_id'])) {
                $c = $this->db->fetch("SELECT nome FROM curso WHERE id = :id", ['id' => (int)$data['curso_novo_id']]);
                if ($c) {
                    $serieNome = $c['nome'];
                }
            }
            $sql = "UPDATE turmas SET nome = :nome, ano_letivo = :ano_letivo, serie = :serie, ativo = :ativo, curso_novo_id = :curso_novo_id, serie_id = :serie_id, ano_letivo_id = :ano_letivo_id, curso_id = NULL WHERE id = :id";
            $ok = $this->db->update($sql, [
                'nome' => $data['nome'],
                'ano_letivo' => $ano,
                'serie' => $serieNome,
                'ativo' => $data['ativo'] ?? 1,
                'curso_novo_id' => (int)$data['curso_novo_id'],
                'serie_id' => isset($data['serie_id']) && $data['serie_id'] !== '' ? (int)$data['serie_id'] : null,
                'ano_letivo_id' => $anoLetivoId ? (int)$anoLetivoId : null,
                'id' => $id
            ]);
            $this->aplicarCamposExtras((int) $id, $data);
            return $ok;
        }

        if ($this->supportsCursos()) {
            $sql = "UPDATE turmas SET nome = :nome, ano_letivo = :ano_letivo, serie = :serie, curso_id = :curso_id, ativo = :ativo WHERE id = :id";
            $ok = $this->db->update($sql, [
                'nome' => $data['nome'],
                'ano_letivo' => $data['ano_letivo'],
                'serie' => $data['serie'],
                'curso_id' => $data['curso_id'] ?? null,
                'ativo' => $data['ativo'] ?? 1,
                'id' => $id
            ]);
            $this->aplicarCamposExtras((int) $id, $data);
            return $ok;
        }

        $sql = "UPDATE turmas SET nome = :nome, ano_letivo = :ano_letivo, serie = :serie, ativo = :ativo WHERE id = :id";
        $ok = $this->db->update($sql, [
            'nome' => $data['nome'],
            'ano_letivo' => $data['ano_letivo'],
            'serie' => $data['serie'],
            'ativo' => $data['ativo'] ?? 1,
            'id' => $id
        ]);
        $this->aplicarCamposExtras((int) $id, $data);
        return $ok;
    }

    private function aplicarCamposExtras(int $id, array $data): void
    {
        if ($id <= 0) {
            return;
        }
        $sets = [];
        $params = [];
        if (array_key_exists('vagas', $data) && $this->temColunaTurma('vagas')) {
            $raw = $data['vagas'];
            $sets[] = 'vagas = :vagas';
            $params['vagas'] = ($raw === '' || $raw === null) ? null : ((int) $raw > 0 ? (int) $raw : null);
        }
        if (array_key_exists('turma_origem_id', $data) && $this->temColunaTurma('turma_origem_id')) {
            $sets[] = 'turma_origem_id = :turma_origem_id';
            $params['turma_origem_id'] = (int) ($data['turma_origem_id'] ?? 0) ?: null;
        }
        if (array_key_exists('matriz_curricular_id', $data) && $this->temColunaTurma('matriz_curricular_id')) {
            $sets[] = 'matriz_curricular_id = :matriz_curricular_id';
            $params['matriz_curricular_id'] = (int) ($data['matriz_curricular_id'] ?? 0) ?: null;
        }
        if (array_key_exists('turno', $data) && $this->temColunaTurma('turno')) {
            $raw = trim((string) ($data['turno'] ?? ''));
            $sets[] = 'turno = :turno';
            $params['turno'] = $raw !== '' ? $raw : null;
        }
        if (array_key_exists('sala_padrao_id', $data) && $this->temColunaTurma('sala_padrao_id')) {
            $sets[] = 'sala_padrao_id = :sala_padrao_id';
            $params['sala_padrao_id'] = (int) ($data['sala_padrao_id'] ?? 0) ?: null;
        }
        if (array_key_exists('observacoes', $data) && $this->temColunaTurma('observacoes')) {
            $raw = trim((string) ($data['observacoes'] ?? ''));
            $sets[] = 'observacoes = :observacoes';
            $params['observacoes'] = $raw !== '' ? $raw : null;
        }
        if ($sets === []) {
            return;
        }
        $params['id'] = $id;
        $this->db->update(
            'UPDATE turmas SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    private function temColunaTurma(string $coluna): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return false;
        }
        try {
            return $this->db->fetch('SHOW COLUMNS FROM turmas LIKE :c', ['c' => $coluna]) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Exclui turma
     */
    public function delete($id)
    {
        return $this->db->delete("DELETE FROM turmas WHERE id = :id", ['id' => $id]);
    }
    
    /**
     * Verifica se turma existe
     */
    public function exists($id)
    {
        $result = $this->db->fetch("SELECT id FROM turmas WHERE id = :id", ['id' => $id]);
        return $result !== false;
    }
    
    /**
     * Verifica se nome da turma já existe
     */
    public function nameExists($nome, $excludeId = null)
    {
        $sql = "SELECT id FROM turmas WHERE nome = :nome";
        $params = ['nome' => $nome];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }
    
    /**
     * Busca turmas por ano letivo
     */
    public function getByAnoLetivo($anoLetivo)
    {
        return $this->db->fetchAll(
            "SELECT * FROM turmas WHERE ano_letivo = :ano_letivo AND ativo = 1 ORDER BY serie ASC, nome ASC",
            ['ano_letivo' => $anoLetivo]
        );
    }
    
    /**
     * Busca turmas por série
     */
    public function getBySerie($serie)
    {
        return $this->db->fetchAll(
            "SELECT * FROM turmas WHERE serie = :serie AND ativo = 1 ORDER BY ano_letivo ASC, nome ASC",
            ['serie' => $serie]
        );
    }
    
    /**
     * Conta total de turmas
     */
    public function count()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM turmas");
        return $result['total'];
    }
    
    /**
     * Conta turmas ativas
     */
    public function countActive()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM turmas WHERE ativo = 1");
        return $result['total'];
    }
    
    /**
     * Busca anos letivos únicos
     */
    public function getAnosLetivos()
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT ano_letivo FROM turmas ORDER BY ano_letivo DESC"
        );
    }
    
    /**
     * Busca séries únicas
     */
    public function getSeries()
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT serie FROM turmas ORDER BY serie ASC"
        );
    }
    
    /**
     * Alterna status ativo/inativo
     */
    public function toggleStatus($id)
    {
        $turma = $this->findById($id);
        if (!$turma) {
            return false;
        }
        
        $newStatus = $turma['ativo'] ? 0 : 1;
        return $this->update($id, ['ativo' => $newStatus]);
    }
    
    /**
     * Verifica se há alunos vinculados à turma
     */
    public function hasStudents($id)
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM alunos WHERE turma_id = :id",
            ['id' => $id]
        );
        return $result['total'] > 0;
    }
    
    /**
     * Conta alunos da turma (turma principal ou matrícula ativa).
     */
    public function countStudents($id)
    {
        require_once __DIR__ . '/../../Services/AlunoTurmaResolver.php';

        return (new \App\Services\AlunoTurmaResolver())->contarAlunosPorTurma((int) $id);
    }

    /**
     * Verifica se a tabela matricula existe.
     */
    public function supportsMatricula()
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'matricula'") !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Retorna alunos que pertencem a pelo menos uma das turmas informadas.
     * Considera alunos.turma_id (legado) e, se existir, matrículas ativas (tabela matricula).
     */
    public function getAlunosByTurmasIds(array $turmaIds)
    {
        require_once __DIR__ . '/../../Services/AlunoTurmaResolver.php';

        return (new \App\Services\AlunoTurmaResolver())->listarAlunosPorTurmasIds($turmaIds);
    }

    /**
     * Retorna lista de cursos ativos com o tipo de curso.
     */
    public function getCursos()
    {
        if (!$this->supportsCursos()) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT c.id, c.nome, c.tipo_curso_id, c.ordem, tc.nome AS tipo_nome
             FROM cursos c
             LEFT JOIN tipos_curso tc ON tc.id = c.tipo_curso_id
             WHERE c.ativo = 1
             ORDER BY tc.ordem ASC, tc.nome ASC, c.ordem ASC, c.nome ASC"
        );
    }

    /**
     * Busca um curso ativo por ID.
     */
    public function findCursoById($id)
    {
        if (!$this->supportsCursos()) {
            return null;
        }

        return $this->db->fetch(
            "SELECT c.id, c.nome, c.tipo_curso_id, tc.nome AS tipo_nome
             FROM cursos c
             LEFT JOIN tipos_curso tc ON tc.id = c.tipo_curso_id
             WHERE c.id = :id AND c.ativo = 1
             LIMIT 1",
            ['id' => $id]
        );
    }

    /**
     * Verifica se a estrutura de cursos existe no banco atual.
     */
    private function supportsCursos()
    {
        if ($this->cursosSupported !== null) {
            return $this->cursosSupported;
        }

        try {
            $hasCursosTable = $this->db->fetch("SHOW TABLES LIKE 'cursos'");
            $hasTiposCursoTable = $this->db->fetch("SHOW TABLES LIKE 'tipos_curso'");
            $hasCursoIdColumn = $this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'curso_id'");

            $this->cursosSupported = ($hasCursosTable !== false)
                && ($hasTiposCursoTable !== false)
                && ($hasCursoIdColumn !== false);
        } catch (Exception $e) {
            $this->cursosSupported = false;
        }

        return $this->cursosSupported;
    }

    /**
     * Verifica se a tabela curso (nova estrutura) e a coluna turmas.curso_novo_id existem.
     */
    public function supportsCursoNovo()
    {
        try {
            $hasCursoTable = $this->db->fetch("SHOW TABLES LIKE 'curso'");
            $hasCol = $this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'curso_novo_id'");
            return ($hasCursoTable !== false) && ($hasCol !== false);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Lista cursos da tabela curso (nova estrutura), incluindo extras.
     */
    public function getCursosNovo()
    {
        if (!$this->supportsCursoNovo()) {
            return [];
        }
        try {
            $hasPossuiSerie = $this->db->fetch("SHOW COLUMNS FROM curso LIKE 'possui_serie'");
            $hasTipo = $this->db->fetch("SHOW COLUMNS FROM curso LIKE 'tipo'");
            if ($hasTipo !== false && $hasPossuiSerie !== false) {
                return $this->db->fetchAll(
                    "SELECT id, nome, ordem, tipo, COALESCE(possui_serie, 1) AS possui_serie FROM curso WHERE ativo = 1 ORDER BY ordem ASC, nome ASC"
                );
            }
            return $this->db->fetchAll("SELECT id, nome, ordem FROM curso WHERE ativo = 1 ORDER BY ordem ASC, nome ASC");
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Anos letivos ativos (tabela `ano_letivo`), para o dropdown de "Ano
     * Letivo" do formulário de turma (fluxo "nova estrutura") — hoje o campo
     * era travado no ano corrente (`date('Y')`); com essa lista o admin pode
     * escolher outro ano (ex.: preparar turmas do ano seguinte).
     */
    public function getAnosLetivoNovo()
    {
        try {
            $hasTable = $this->db->fetch("SHOW TABLES LIKE 'ano_letivo'");
            if ($hasTable === false) {
                return [];
            }
            return $this->db->fetchAll(
                "SELECT id, ano FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC"
            );
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Séries da tabela serie (por curso_id), para uso no formulário de turma.
     */
    public function getSeriesNovo()
    {
        if (!$this->supportsCursoNovo()) {
            return [];
        }
        try {
            $hasSerie = $this->db->fetch("SHOW TABLES LIKE 'serie'");
            if ($hasSerie === false) {
                return [];
            }
            return $this->db->fetchAll(
                "SELECT id, curso_id, nome, ordem FROM serie WHERE ativo = 1 ORDER BY curso_id ASC, ordem ASC, nome ASC"
            );
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Retorna o id do ano_letivo para o ano informado (ex: ano atual).
     */
    public function getAnoLetivoIdByAno($ano)
    {
        try {
            $hasTable = $this->db->fetch("SHOW TABLES LIKE 'ano_letivo'");
            if ($hasTable === false) {
                return null;
            }
            $row = $this->db->fetch("SELECT id FROM ano_letivo WHERE ano = :ano LIMIT 1", ['ano' => (int)$ano]);
            return $row ? (int)$row['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Ano civil (ex.: 2026) correspondente a um ano_letivo_id — usado pra
     * manter a coluna legada `turmas.ano_letivo` (int) em sincronia quando o
     * admin escolhe um Ano Letivo diferente do calendário atual.
     */
    public function getAnoById($anoLetivoId)
    {
        try {
            $hasTable = $this->db->fetch("SHOW TABLES LIKE 'ano_letivo'");
            if ($hasTable === false) {
                return null;
            }
            $row = $this->db->fetch("SELECT ano FROM ano_letivo WHERE id = :id LIMIT 1", ['id' => (int) $anoLetivoId]);
            return $row ? (int) $row['ano'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
