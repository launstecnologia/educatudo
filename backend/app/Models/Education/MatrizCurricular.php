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
        $countSql = "(SELECT COUNT(*) FROM matrizes_curriculares_componentes mcc WHERE mcc.matriz_id = m.id)";
        try {
            if ($this->db->fetch("SHOW COLUMNS FROM materias LIKE 'pai_id'")) {
                $countSql = "(SELECT COUNT(DISTINCT COALESCE(NULLIF(mat.pai_id, 0), mat.id))
                        FROM matrizes_curriculares_componentes mcc
                        INNER JOIN materias mat ON mat.id = mcc.materia_id
                        WHERE mcc.matriz_id = m.id)";
            }
        } catch (\Throwable $e) {
            // schema antigo sem pai_id — conta as linhas brutas
        }

        $sql = "SELECT m.*, c.nome AS curso_nome, s.nome AS serie_nome,
                       {$countSql} AS total_componentes
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

        $sql = "SELECT mcc.*, mat.nome AS materia_nome, mat.codigo AS materia_codigo, mat.cor AS materia_cor
             FROM matrizes_curriculares_componentes mcc
             INNER JOIN materias mat ON mat.id = mcc.materia_id
             WHERE mcc.matriz_id = :matriz_id
             ORDER BY mcc.ordem_boletim ASC, mat.nome ASC";
        try {
            if ($this->db->fetch("SHOW COLUMNS FROM materias LIKE 'pai_id'")) {
                $sql = "SELECT mcc.*, mat.nome AS materia_nome, mat.codigo AS materia_codigo, mat.cor AS materia_cor,
                    mat.pai_id AS pai_id, pai.nome AS pai_nome
                 FROM matrizes_curriculares_componentes mcc
                 INNER JOIN materias mat ON mat.id = mcc.materia_id
                 LEFT JOIN materias pai ON pai.id = mat.pai_id
                 WHERE mcc.matriz_id = :matriz_id
                 ORDER BY mcc.ordem_boletim ASC, mat.nome ASC";
            }
        } catch (\Throwable $e) {
            // schema antigo
        }

        $componentes = $this->db->fetchAll($sql, ['matriz_id' => $matrizId]) ?: [];

        foreach ($componentes as &$componente) {
            $minutosSemana = (int) $componente['aulas_semana'] * $duracaoAula;
            $componente['carga_horaria_semanal_minutos'] = $minutosSemana;
            $componente['carga_horaria_semanal_horas'] = round($minutosSemana / 60, 2);
        }
        unset($componente);

        return $componentes;
    }

    /**
     * Linhas oficiais da matriz: desdobramentos somam no pai (Língua Portuguesa),
     * componentes sem pai permanecem sozinhos.
     *
     * @return list<array<string,mixed>>
     */
    public function getComponentesOficiais(int $matrizId): array
    {
        return $this->agruparOficiais($this->getComponentes($matrizId));
    }

    /**
     * @param list<array<string,mixed>> $componentes
     * @return list<array<string,mixed>>
     */
    public function agruparOficiais(array $componentes): array
    {
        $filhosPorPai = [];
        $soltos = [];
        foreach ($componentes as $c) {
            $id = (int) ($c['materia_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $pai = (int) ($c['pai_id'] ?? 0);
            if ($pai > 0) {
                $filhosPorPai[$pai][] = $c;
                continue;
            }
            $soltos[] = $c;
        }

        $out = [];
        $paisEmitidos = [];
        foreach ($soltos as $c) {
            $id = (int) ($c['materia_id'] ?? 0);
            if (isset($filhosPorPai[$id])) {
                $out[] = $this->linhaOficialDoPai($id, (string) ($c['materia_nome'] ?? ''), $filhosPorPai[$id], $c);
                $paisEmitidos[$id] = true;
                continue;
            }
            $c['filhos'] = [];
            $c['eh_oficial_agrupado'] = false;
            $out[] = $c;
        }

        foreach ($filhosPorPai as $paiId => $filhos) {
            if (isset($paisEmitidos[$paiId])) {
                continue;
            }
            $nomePai = (string) ($filhos[0]['pai_nome'] ?? '');
            if ($nomePai === '') {
                $nomePai = 'Área';
            }
            $out[] = $this->linhaOficialDoPai($paiId, $nomePai, $filhos, null);
        }

        usort($out, static function ($a, $b) {
            $oa = (int) ($a['ordem_boletim'] ?? 0);
            $ob = (int) ($b['ordem_boletim'] ?? 0);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            return strcmp((string) ($a['materia_nome'] ?? ''), (string) ($b['materia_nome'] ?? ''));
        });

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $filhos
     * @param array<string,mixed>|null $linhaPai
     * @return array<string,mixed>
     */
    private function linhaOficialDoPai(int $paiId, string $nomePai, array $filhos, ?array $linhaPai): array
    {
        $aulas = 0;
        $minutos = 0;
        $obrigatorio = 1;
        $ordem = $linhaPai !== null ? (int) ($linhaPai['ordem_boletim'] ?? 0) : PHP_INT_MAX;
        foreach ($filhos as $f) {
            $aulas += (int) ($f['aulas_semana'] ?? 0);
            $minutos += (int) ($f['carga_horaria_semanal_minutos'] ?? 0);
            if (empty($f['obrigatorio'])) {
                $obrigatorio = 0;
            }
            $ordem = min($ordem, (int) ($f['ordem_boletim'] ?? 0));
        }
        if ($linhaPai !== null && empty($filhos)) {
            $aulas = (int) ($linhaPai['aulas_semana'] ?? 0);
            $minutos = (int) ($linhaPai['carga_horaria_semanal_minutos'] ?? 0);
            $obrigatorio = !empty($linhaPai['obrigatorio']) ? 1 : 0;
            $ordem = (int) ($linhaPai['ordem_boletim'] ?? 0);
        }

        $codigo = (string) ($linhaPai['materia_codigo'] ?? '');
        $cor = $linhaPai['materia_cor'] ?? null;
        $nome = $nomePai;
        if ($codigo === '' || $cor === null || $nome === '' || $nome === 'Área') {
            $paiRow = $this->db->fetch(
                "SELECT nome, codigo, cor FROM materias WHERE id = :id",
                ['id' => $paiId]
            );
            if (is_array($paiRow)) {
                if ($nome === '' || $nome === 'Área') {
                    $nome = (string) ($paiRow['nome'] ?? $nome);
                }
                if ($codigo === '') {
                    $codigo = (string) ($paiRow['codigo'] ?? '');
                }
                if ($cor === null) {
                    $cor = $paiRow['cor'] ?? null;
                }
            }
        }

        return [
            'materia_id' => $paiId,
            'materia_nome' => $nome,
            'materia_codigo' => $codigo,
            'materia_cor' => $cor,
            'aulas_semana' => $aulas,
            'obrigatorio' => $obrigatorio,
            'ordem_boletim' => $ordem === PHP_INT_MAX ? 0 : $ordem,
            'ordem_historico' => $ordem === PHP_INT_MAX ? 0 : $ordem,
            'carga_horaria_semanal_minutos' => $minutos,
            'carga_horaria_semanal_horas' => round($minutos / 60, 2),
            'filhos' => $filhos,
            'eh_oficial_agrupado' => $filhos !== [],
        ];
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
