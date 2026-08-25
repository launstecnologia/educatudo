<?php

namespace App\Modulos\RegrasAcademicas\Models;

use Database;

/**
 * EducaTudo - Modelo de Regras Acadêmicas
 * Critérios versionados de aprovação, recuperação e frequência.
 */
class RegraAcademica
{
    private $db;

    public const PERIODO_TIPOS = [
        'bimestre' => 'Bimestre',
        'trimestre' => 'Trimestre',
        'semestre' => 'Semestre',
        'etapa_unica' => 'Etapa única',
    ];

    public const RECUPERACAO_TIPOS = [
        'nenhuma' => 'Sem recuperação',
        'continua' => 'Contínua / paralela',
        'periodo' => 'Por período (bimestre/trimestre)',
        'final' => 'Recuperação final',
    ];

    public const RECUPERACAO_COMPOSICOES = [
        'maior_nota' => 'Maior nota (substitui se for maior)',
        'substitui' => 'Substitui a média',
        'composicao' => 'Composição (média + recuperação) / 2',
        'formula' => 'Fórmula própria (formula_final)',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function schemaPronto(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'regras_academicas'
                 LIMIT 1"
            );
            $ok = !empty($row['ok']);
        } catch (Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * @param array{ano_letivo?:int,curso_id?:int,serie_id?:int,ativo?:int} $filtros
     * @return list<array<string,mixed>>
     */
    public function getAll(array $filtros = []): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }

        $sql = "SELECT r.*,
                       c.nome AS curso_nome,
                       s.nome AS serie_nome,
                       m.nome AS materia_nome
                FROM regras_academicas r
                LEFT JOIN curso c ON c.id = r.curso_id
                LEFT JOIN serie s ON s.id = r.serie_id
                LEFT JOIN materias m ON m.id = r.materia_id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['ano_letivo'])) {
            $sql .= " AND r.ano_letivo = :ano_letivo";
            $params['ano_letivo'] = (int) $filtros['ano_letivo'];
        }
        if (!empty($filtros['curso_id'])) {
            $sql .= " AND r.curso_id = :curso_id";
            $params['curso_id'] = (int) $filtros['curso_id'];
        }
        if (!empty($filtros['serie_id'])) {
            $sql .= " AND r.serie_id = :serie_id";
            $params['serie_id'] = (int) $filtros['serie_id'];
        }
        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $sql .= " AND r.ativo = :ativo";
            $params['ativo'] = (int) $filtros['ativo'];
        }

        $sql .= " ORDER BY r.ano_letivo DESC, r.nome ASC";

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * Regras ativas para o motor resolver a mais específica.
     * @return list<array<string,mixed>>
     */
    public function listarAtivas(?int $anoLetivo = null): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        $sql = "SELECT * FROM regras_academicas WHERE ativo = 1";
        $params = [];
        if ($anoLetivo !== null && $anoLetivo > 0) {
            $sql .= " AND (ano_letivo = :ano_letivo OR ano_letivo IS NULL)";
            $params['ano_letivo'] = $anoLetivo;
        }
        $sql .= " ORDER BY updated_at DESC, id DESC";

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function findById(int $id): ?array
    {
        if (!$this->schemaPronto() || $id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT r.*,
                    c.nome AS curso_nome,
                    s.nome AS serie_nome,
                    m.nome AS materia_nome
             FROM regras_academicas r
             LEFT JOIN curso c ON c.id = r.curso_id
             LEFT JOIN serie s ON s.id = r.serie_id
             LEFT JOIN materias m ON m.id = r.materia_id
             WHERE r.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function exists(int $id): bool
    {
        return $this->findById($id) !== null;
    }

    public function codigoExists(string $codigo, ?int $ignoreId = null): bool
    {
        if (!$this->schemaPronto() || $codigo === '') {
            return false;
        }
        $sql = "SELECT id FROM regras_academicas WHERE codigo = :codigo";
        $params = ['codigo' => $codigo];
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= " AND id != :id";
            $params['id'] = $ignoreId;
        }
        $row = $this->db->fetch($sql . " LIMIT 1", $params);
        return !empty($row);
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO regras_academicas (
                nome, codigo, ano_letivo, curso_id, serie_id, matriz_curricular_id, materia_id,
                periodo_tipo, periodo_numero, media_minima, frequencia_minima, usar_frequencia,
                round_mode, decimal_places, formula_media, formula_final,
                recuperacao_tipo, recuperacao_composicao, min_avaliacoes, max_avaliacoes,
                componentes_sem_nota, aprovacao_so_frequencia, situacoes_json, observacoes,
                versao, ativo
             ) VALUES (
                :nome, :codigo, :ano_letivo, :curso_id, :serie_id, :matriz_curricular_id, :materia_id,
                :periodo_tipo, :periodo_numero, :media_minima, :frequencia_minima, :usar_frequencia,
                :round_mode, :decimal_places, :formula_media, :formula_final,
                :recuperacao_tipo, :recuperacao_composicao, :min_avaliacoes, :max_avaliacoes,
                :componentes_sem_nota, :aprovacao_so_frequencia, :situacoes_json, :observacoes,
                :versao, :ativo
             )",
            $this->paramsFromData($data)
        );
    }

    public function update(int $id, array $data): bool
    {
        $params = $this->paramsFromData($data);
        $params['id'] = $id;
        return (bool) $this->db->update(
            "UPDATE regras_academicas SET
                nome = :nome,
                codigo = :codigo,
                ano_letivo = :ano_letivo,
                curso_id = :curso_id,
                serie_id = :serie_id,
                matriz_curricular_id = :matriz_curricular_id,
                materia_id = :materia_id,
                periodo_tipo = :periodo_tipo,
                periodo_numero = :periodo_numero,
                media_minima = :media_minima,
                frequencia_minima = :frequencia_minima,
                usar_frequencia = :usar_frequencia,
                round_mode = :round_mode,
                decimal_places = :decimal_places,
                formula_media = :formula_media,
                formula_final = :formula_final,
                recuperacao_tipo = :recuperacao_tipo,
                recuperacao_composicao = :recuperacao_composicao,
                min_avaliacoes = :min_avaliacoes,
                max_avaliacoes = :max_avaliacoes,
                componentes_sem_nota = :componentes_sem_nota,
                aprovacao_so_frequencia = :aprovacao_so_frequencia,
                situacoes_json = :situacoes_json,
                observacoes = :observacoes,
                versao = :versao,
                ativo = :ativo
             WHERE id = :id",
            $params
        );
    }

    public function delete(int $id): bool
    {
        if (!$this->schemaPronto() || $id <= 0) {
            return false;
        }
        return (bool) $this->db->query(
            "DELETE FROM regras_academicas WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarHistorico(int $regraId): array
    {
        if (!$this->schemaPronto() || $regraId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT id, regra_id, versao, parametros_json, usuario_id, usuario_nome, created_at
             FROM regras_academicas_historico
             WHERE regra_id = :regra_id
             ORDER BY versao DESC",
            ['regra_id' => $regraId]
        ) ?: [];
    }

    public function gravarHistorico(int $regraId, int $versao, array $parametros, ?int $usuarioId, ?string $usuarioNome): void
    {
        $this->db->insert(
            "INSERT INTO regras_academicas_historico
                (regra_id, versao, parametros_json, usuario_id, usuario_nome)
             VALUES
                (:regra_id, :versao, :parametros_json, :usuario_id, :usuario_nome)",
            [
                'regra_id' => $regraId,
                'versao' => $versao,
                'parametros_json' => json_encode($parametros, JSON_UNESCAPED_UNICODE),
                'usuario_id' => $usuarioId !== null && $usuarioId > 0 ? $usuarioId : null,
                'usuario_nome' => $usuarioNome !== null && $usuarioNome !== '' ? $usuarioNome : null,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paramsFromData(array $data): array
    {
        return [
            'nome' => (string) ($data['nome'] ?? ''),
            'codigo' => $data['codigo'] ?? null,
            'ano_letivo' => $data['ano_letivo'] ?? null,
            'curso_id' => $data['curso_id'] ?? null,
            'serie_id' => $data['serie_id'] ?? null,
            'matriz_curricular_id' => $data['matriz_curricular_id'] ?? null,
            'materia_id' => $data['materia_id'] ?? null,
            'periodo_tipo' => (string) ($data['periodo_tipo'] ?? 'bimestre'),
            'periodo_numero' => $data['periodo_numero'] ?? null,
            'media_minima' => (float) ($data['media_minima'] ?? 6),
            'frequencia_minima' => (float) ($data['frequencia_minima'] ?? 75),
            'usar_frequencia' => (int) ($data['usar_frequencia'] ?? 0),
            'round_mode' => (string) ($data['round_mode'] ?? 'none'),
            'decimal_places' => (int) ($data['decimal_places'] ?? 2),
            'formula_media' => $data['formula_media'] ?? null,
            'formula_final' => $data['formula_final'] ?? null,
            'recuperacao_tipo' => (string) ($data['recuperacao_tipo'] ?? 'periodo'),
            'recuperacao_composicao' => (string) ($data['recuperacao_composicao'] ?? 'maior_nota'),
            'min_avaliacoes' => $data['min_avaliacoes'] ?? null,
            'max_avaliacoes' => $data['max_avaliacoes'] ?? null,
            'componentes_sem_nota' => (int) ($data['componentes_sem_nota'] ?? 0),
            'aprovacao_so_frequencia' => (int) ($data['aprovacao_so_frequencia'] ?? 0),
            'situacoes_json' => $data['situacoes_json'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'versao' => (int) ($data['versao'] ?? 1),
            'ativo' => (int) ($data['ativo'] ?? 1),
        ];
    }
}
