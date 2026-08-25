<?php
/**
 * EducaTudo - Modelo de Matriz Curricular
 * Define o que cada série de um curso deve cursar: Componentes Curriculares
 * vinculados, aulas por semana, obrigatoriedade e ordem no boletim/histórico.
 *
 * Referencia curso/serie reais (tabelas `curso`/`serie`, migrations 023/024) —
 * mesma estrutura que `turmas.curso_novo_id`/`serie_id` já usam (migrations
 * 028/031). Ver database/migrations/2026_08_18_matriz_curricular.sql.
 */

class MatrizCurricular
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lista matrizes, com nome de curso/série já resolvidos, opcionalmente filtradas.
     * @param array{curso_id?: int, serie_id?: int, ativo?: int} $filtros
     */
    public function getAll(array $filtros = [])
    {
        $sql = "SELECT m.*, c.nome AS curso_nome, s.nome AS serie_nome,
                       (SELECT COUNT(*) FROM matrizes_curriculares_componentes mcc WHERE mcc.matriz_id = m.id) AS total_componentes
                FROM matrizes_curriculares m
                INNER JOIN curso c ON c.id = m.curso_id
                INNER JOIN serie s ON s.id = m.serie_id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['curso_id'])) {
            $sql .= " AND m.curso_id = :curso_id";
            $params['curso_id'] = (int) $filtros['curso_id'];
        }
        if (!empty($filtros['serie_id'])) {
            $sql .= " AND m.serie_id = :serie_id";
            $params['serie_id'] = (int) $filtros['serie_id'];
        }
        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $sql .= " AND m.ativo = :ativo";
            $params['ativo'] = (int) $filtros['ativo'];
        }

        $sql .= " ORDER BY c.ordem ASC, c.nome ASC, s.ordem ASC, s.nome ASC, m.nome ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Busca uma matriz por id, com nome de curso/série já resolvidos.
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT m.*, c.nome AS curso_nome, s.nome AS serie_nome
             FROM matrizes_curriculares m
             INNER JOIN curso c ON c.id = m.curso_id
             INNER JOIN serie s ON s.id = m.serie_id
             WHERE m.id = :id",
            ['id' => (int) $id]
        );
    }

    public function create(array $data)
    {
        $sql = "INSERT INTO matrizes_curriculares
                    (nome, codigo, curso_id, serie_id, modalidade, turno,
                     carga_horaria_anual_prevista, dias_letivos_previstos,
                     duracao_padrao_aula_minutos, base_legal, observacoes, ativo)
                VALUES
                    (:nome, :codigo, :curso_id, :serie_id, :modalidade, :turno,
                     :carga_horaria_anual_prevista, :dias_letivos_previstos,
                     :duracao_padrao_aula_minutos, :base_legal, :observacoes, :ativo)";

        return $this->db->insert($sql, $this->paramsFromData($data));
    }

    public function update($id, array $data)
    {
        $sql = "UPDATE matrizes_curriculares SET
                    nome = :nome,
                    codigo = :codigo,
                    curso_id = :curso_id,
                    serie_id = :serie_id,
                    modalidade = :modalidade,
                    turno = :turno,
                    carga_horaria_anual_prevista = :carga_horaria_anual_prevista,
                    dias_letivos_previstos = :dias_letivos_previstos,
                    duracao_padrao_aula_minutos = :duracao_padrao_aula_minutos,
                    base_legal = :base_legal,
                    observacoes = :observacoes,
                    ativo = :ativo
                WHERE id = :id";

        $params = $this->paramsFromData($data);
        $params['id'] = (int) $id;

        return $this->db->update($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->delete("DELETE FROM matrizes_curriculares WHERE id = :id", ['id' => (int) $id]);
    }

    public function exists($id)
    {
        $result = $this->db->fetch("SELECT id FROM matrizes_curriculares WHERE id = :id", ['id' => (int) $id]);
        return $result !== false;
    }

    public function nameExists($nome, $excludeId = null)
    {
        $sql = "SELECT id FROM matrizes_curriculares WHERE nome = :nome";
        $params = ['nome' => $nome];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = (int) $excludeId;
        }

        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }

    public function codigoExists($codigo, $excludeId = null)
    {
        $sql = "SELECT id FROM matrizes_curriculares WHERE codigo = :codigo";
        $params = ['codigo' => $codigo];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = (int) $excludeId;
        }

        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }

    /**
     * Quantidade de turmas hoje vinculadas a esta matriz — usado pro Service
     * bloquear exclusão.
     */
    public function countTurmasVinculadas(int $matrizId): int
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM turmas WHERE matriz_curricular_id = :id",
            ['id' => $matrizId]
        );
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Componentes curriculares vinculados à matriz, já com dados do Componente
     * Curricular (nome, código, etc.) e a carga horária calculada a partir de
     * `aulas_semana` × `duracao_padrao_aula_minutos` da matriz (não persistida).
     */
    public function getComponentes(int $matrizId): array
    {
        $duracaoAula = (int) ($this->db->fetch(
            "SELECT duracao_padrao_aula_minutos FROM matrizes_curriculares WHERE id = :id",
            ['id' => $matrizId]
        )['duracao_padrao_aula_minutos'] ?? 50);

        $componentes = $this->db->fetchAll(
            "SELECT mcc.*, mat.nome AS materia_nome, mat.codigo AS materia_codigo, mat.cor AS materia_cor
             FROM matrizes_curriculares_componentes mcc
             INNER JOIN materias mat ON mat.id = mcc.materia_id
             WHERE mcc.matriz_id = :matriz_id
             ORDER BY mcc.ordem_boletim ASC, mat.nome ASC",
            ['matriz_id' => $matrizId]
        );

        foreach ($componentes as &$componente) {
            $minutosSemana = (int) $componente['aulas_semana'] * $duracaoAula;
            $componente['carga_horaria_semanal_minutos'] = $minutosSemana;
            $componente['carga_horaria_semanal_horas'] = round($minutosSemana / 60, 2);
        }
        unset($componente);

        return $componentes;
    }

    /**
     * Substitui todos os componentes de uma matriz pelos informados, numa
     * transação (delete + insert) — reflete a lista vinda do formulário.
     * @param list<array{materia_id:int, aulas_semana:int, obrigatorio:int, ordem_boletim:int, ordem_historico:int}> $componentes
     */
    public function salvarComponentes(int $matrizId, array $componentes): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->delete(
                "DELETE FROM matrizes_curriculares_componentes WHERE matriz_id = :matriz_id",
                ['matriz_id' => $matrizId]
            );

            foreach ($componentes as $componente) {
                $this->db->insert(
                    "INSERT INTO matrizes_curriculares_componentes
                        (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
                     VALUES
                        (:matriz_id, :materia_id, :aulas_semana, :obrigatorio, :ordem_boletim, :ordem_historico)",
                    [
                        'matriz_id' => $matrizId,
                        'materia_id' => (int) $componente['materia_id'],
                        'aulas_semana' => (int) $componente['aulas_semana'],
                        'obrigatorio' => (int) $componente['obrigatorio'],
                        'ordem_boletim' => (int) $componente['ordem_boletim'],
                        'ordem_historico' => (int) $componente['ordem_historico'],
                    ]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function paramsFromData(array $data): array
    {
        return [
            'nome' => $data['nome'],
            'codigo' => $data['codigo'],
            'curso_id' => (int) $data['curso_id'],
            'serie_id' => (int) $data['serie_id'],
            'modalidade' => $data['modalidade'] !== '' ? $data['modalidade'] : null,
            'turno' => $data['turno'] !== '' ? $data['turno'] : null,
            'carga_horaria_anual_prevista' => $data['carga_horaria_anual_prevista'] !== '' ? $data['carga_horaria_anual_prevista'] : null,
            'dias_letivos_previstos' => $data['dias_letivos_previstos'] !== '' ? (int) $data['dias_letivos_previstos'] : null,
            'duracao_padrao_aula_minutos' => (int) $data['duracao_padrao_aula_minutos'],
            'base_legal' => $data['base_legal'] !== '' ? $data['base_legal'] : null,
            'observacoes' => $data['observacoes'] !== '' ? $data['observacoes'] : null,
            'ativo' => (int) $data['ativo'],
        ];
    }
}
