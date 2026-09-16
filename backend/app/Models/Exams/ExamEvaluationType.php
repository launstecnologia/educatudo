<?php
/**
 * EducaTudo - Tipos de Avaliação de Provas Online
 */

class ExamEvaluationType
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE deleted_at IS NULL
             ORDER BY ativo DESC, ordem ASC, nome ASC"
        ) ?: [];
    }

    public function getAllActive(): array
    {
        return $this->db->fetchAll(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE deleted_at IS NULL AND ativo = 1
             ORDER BY ordem ASC, nome ASC"
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT *
             FROM provas_tipos_avaliacao
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function existsByName(string $nome, ?int $ignoreId = null): bool
    {
        $sql = "SELECT id FROM provas_tipos_avaliacao WHERE deleted_at IS NULL AND LOWER(nome) = LOWER(:nome)";
        $params = ['nome' => trim($nome)];
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= " AND id != :id";
            $params['id'] = $ignoreId;
        }
        $row = $this->db->fetch($sql . " LIMIT 1", $params);
        return !empty($row);
    }

    public static function chavesQuadro(): array
    {
        return [
            'semanal' => 'Prova semanal (S1–S8)',
            'prova_bim' => 'Prova bimestral',
            'enac' => 'ENAC',
            'participacao' => 'Participação',
            'trabalho' => 'Trabalho',
            'recuperacao' => 'Recuperação',
        ];
    }

    public function temColunaChaveQuadro(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_tipos_avaliacao'
                   AND COLUMN_NAME = 'chave_quadro'
                 LIMIT 1"
            );
            $cache = !empty($row);
        } catch (Exception $e) {
            $cache = false;
        }
        return $cache;
    }

    public function sanitizarChaveQuadro($valor): ?string
    {
        $c = strtolower(trim((string) $valor));
        return array_key_exists($c, self::chavesQuadro()) ? $c : null;
    }

    public function temColunasRegras(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_tipos_avaliacao'
                   AND COLUMN_NAME = 'origem'
                 LIMIT 1"
            );
            $cache = !empty($row);
        } catch (Exception $e) {
            $cache = false;
        }
        return $cache;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function paramsRegras(array $data): array
    {
        $qtd = isset($data['quantidade_eventos_esperada']) ? (int) $data['quantidade_eventos_esperada'] : 0;
        $params = [
            'origem' => $this->sanitizarOrigem($data['origem'] ?? 'lancamento_direto'),
            'registro_evento' => $this->sanitizarRegistro($data['registro_evento'] ?? 'nota'),
            'criterio_fechamento' => $this->sanitizarCriterio($data['criterio_fechamento'] ?? 'ultima'),
            'escala_max' => $this->sanitizarEscala($data['escala_max'] ?? 10),
            'quantidade_eventos_esperada' => $qtd > 0 ? $qtd : null,
            'media_professores_mesmo_componente' => !empty($data['media_professores_mesmo_componente']) ? 1 : 0,
        ];
        if ($this->temColunaCriterioProfessores()) {
            $params['criterio_professores_mesmo_componente'] = $this->sanitizarCriterioProfessores(
                $data['criterio_professores_mesmo_componente'] ?? $params['media_professores_mesmo_componente']
            );
            $params['media_professores_mesmo_componente'] = in_array($params['criterio_professores_mesmo_componente'], ['media', 'soma'], true) ? 1 : 0;
        }
        return $params;
    }

    private function sanitizarOrigem($valor): string
    {
        $v = strtolower(trim((string) $valor));
        return in_array($v, ['lancamento_direto', 'eventos', 'prova_online'], true) ? $v : 'lancamento_direto';
    }

    private function sanitizarRegistro($valor): string
    {
        $v = strtolower(trim((string) $valor));
        return in_array($v, ['nota', 'acertos_questoes'], true) ? $v : 'nota';
    }

    private function sanitizarCriterio($valor): string
    {
        $v = strtolower(trim((string) $valor));
        return in_array($v, ['ultima', 'maior', 'media', 'soma', 'aproveitamento_nq'], true) ? $v : 'ultima';
    }

    public function temColunaCriterioProfessores(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_tipos_avaliacao'
                   AND COLUMN_NAME = 'criterio_professores_mesmo_componente'
                 LIMIT 1"
            );
            $cache = !empty($row);
        } catch (Exception $e) {
            $cache = false;
        }
        return $cache;
    }

    private function sanitizarCriterioProfessores($valor): string
    {
        if ($valor === 1 || $valor === '1' || $valor === true) {
            return 'media';
        }
        $v = strtolower(trim((string) $valor));
        return in_array($v, ['nenhum', 'media', 'soma'], true) ? $v : 'nenhum';
    }

    private function sanitizarEscala($valor): float
    {
        $n = is_numeric($valor) ? (float) $valor : 10.0;
        if ($n <= 0) {
            $n = 10.0;
        }
        if ($n > 100) {
            $n = 100.0;
        }
        return round($n, 2);
    }

    public function create(array $data): int
    {
        $params = [
            'nome' => trim((string) ($data['nome'] ?? '')),
            'descricao' => !empty($data['descricao']) ? trim((string) $data['descricao']) : null,
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 0,
        ];
        $cols = 'nome, descricao, ativo, ordem';
        $vals = ':nome, :descricao, :ativo, :ordem';
        if ($this->temColunaChaveQuadro()) {
            $params['chave_quadro'] = $this->sanitizarChaveQuadro($data['chave_quadro'] ?? null);
            $cols .= ', chave_quadro';
            $vals .= ', :chave_quadro';
        }
        if ($this->temColunasRegras()) {
            $params = array_merge($params, $this->paramsRegras($data));
            $cols .= ', origem, registro_evento, criterio_fechamento, escala_max, quantidade_eventos_esperada, media_professores_mesmo_componente';
            $vals .= ', :origem, :registro_evento, :criterio_fechamento, :escala_max, :quantidade_eventos_esperada, :media_professores_mesmo_componente';
            if ($this->temColunaCriterioProfessores()) {
                $cols .= ', criterio_professores_mesmo_componente';
                $vals .= ', :criterio_professores_mesmo_componente';
            }
        }
        return (int) $this->db->insert(
            "INSERT INTO provas_tipos_avaliacao ({$cols}) VALUES ({$vals})",
            $params
        );
    }

    public function update(int $id, array $data): bool
    {
        $params = [
            'id' => $id,
            'nome' => trim((string) ($data['nome'] ?? '')),
            'descricao' => !empty($data['descricao']) ? trim((string) $data['descricao']) : null,
            'ativo' => !empty($data['ativo']) ? 1 : 0,
        ];
        $set = 'nome = :nome, descricao = :descricao, ativo = :ativo';
        if (array_key_exists('ordem', $data)) {
            $set .= ', ordem = :ordem';
            $params['ordem'] = (int) $data['ordem'];
        }
        if ($this->temColunaChaveQuadro() && array_key_exists('chave_quadro', $data)) {
            $set .= ', chave_quadro = :chave_quadro';
            $params['chave_quadro'] = $this->sanitizarChaveQuadro($data['chave_quadro'] ?? null);
        }
        if ($this->temColunasRegras()) {
            $params = array_merge($params, $this->paramsRegras($data));
            $set .= ', origem = :origem, registro_evento = :registro_evento, criterio_fechamento = :criterio_fechamento,
                      escala_max = :escala_max, quantidade_eventos_esperada = :quantidade_eventos_esperada,
                      media_professores_mesmo_componente = :media_professores_mesmo_componente';
            if ($this->temColunaCriterioProfessores()) {
                $set .= ', criterio_professores_mesmo_componente = :criterio_professores_mesmo_componente';
            }
        }
        return (bool) $this->db->query(
            "UPDATE provas_tipos_avaliacao
             SET {$set}
             WHERE id = :id AND deleted_at IS NULL",
            $params
        );
    }

    public function softDelete(int $id): bool
    {
        return (bool) $this->db->query(
            "UPDATE provas_tipos_avaliacao
             SET deleted_at = NOW()
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }
}

