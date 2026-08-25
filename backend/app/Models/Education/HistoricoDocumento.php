<?php
/**
 * EducaTudo - Histórico Escolar oficial (documento versionado).
 */

class HistoricoDocumento
{
    private $db;

    public const STATUS = ['Rascunho', 'Conferido', 'Emitido', 'Assinado', 'Entregue', 'Cancelado'];
    public const FINALIDADES = ['Transferencia', 'Conclusao', 'Solicitacao'];
    public const RESULTADOS = ['Aprovado', 'Aprovado_Conselho', 'Retido', 'Transferido', 'Evadido', 'Cursando'];
    public const CARGOS = ['Diretor', 'Secretario_Escolar', 'Outro'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'historico_documentos' LIMIT 1"
            );
            $cache = !empty($row['ok']);
        } catch (\Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    public function colunaExiste(string $coluna): bool
    {
        static $cache = [];
        $coluna = preg_replace('/[^a-z0-9_]/i', '', $coluna) ?? '';
        if ($coluna === '') {
            return false;
        }
        if (array_key_exists($coluna, $cache)) {
            return $cache[$coluna];
        }
        if (!$this->tableExists()) {
            return $cache[$coluna] = false;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'historico_documentos'
                   AND column_name = :col
                 LIMIT 1",
                ['col' => $coluna]
            );
            $cache[$coluna] = !empty($row['ok']);
        } catch (\Throwable $e) {
            $cache[$coluna] = false;
        }
        return $cache[$coluna];
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists()) {
            return null;
        }
        $codigoExpr = $this->alunoTemCodigoAluno() ? 'a.codigo_aluno' : 'a.ra';
        $row = $this->db->fetch(
            "SELECT h.*, a.nome AS aluno_nome, {$codigoExpr} AS aluno_codigo
             FROM historico_documentos h
             INNER JOIN alunos a ON a.id = h.aluno_id
             WHERE h.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $row ?: null;
    }

    private function alunoTemCodigoAluno(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'alunos'
                   AND column_name = 'codigo_aluno'
                 LIMIT 1"
            );
            $ok = !empty($row['ok']);
        } catch (\Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    public function findByHash(string $hash): ?array
    {
        $hash = trim($hash);
        if ($hash === '' || !$this->tableExists()) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT h.*, a.nome AS aluno_nome
             FROM historico_documentos h
             INNER JOIN alunos a ON a.id = h.aluno_id
             WHERE h.hash_validacao = :hash LIMIT 1",
            ['hash' => $hash]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorAluno(int $alunoId): array
    {
        if ($alunoId <= 0 || !$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT h.*
             FROM historico_documentos h
             WHERE h.aluno_id = :aluno_id
             ORDER BY h.versao DESC, h.id DESC",
            ['aluno_id' => $alunoId]
        ) ?: [];
    }

    public function proximaVersao(int $alunoId): int
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(MAX(versao), 0) + 1 AS prox
             FROM historico_documentos WHERE aluno_id = :aluno_id",
            ['aluno_id' => (int) $alunoId]
        );
        return max(1, (int) ($row['prox'] ?? 1));
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO historico_documentos
                (aluno_id, unidade_id, versao, status, finalidade, observacoes_gerais, substitui_id, created_at)
             VALUES
                (:aluno_id, :unidade_id, :versao, :status, :finalidade, :observacoes_gerais, :substitui_id, NOW())",
            [
                'aluno_id' => (int) $data['aluno_id'],
                'unidade_id' => isset($data['unidade_id']) ? (int) $data['unidade_id'] : null,
                'versao' => (int) ($data['versao'] ?? 1),
                'status' => $data['status'] ?? 'Rascunho',
                'finalidade' => $data['finalidade'] ?? 'Solicitacao',
                'observacoes_gerais' => $data['observacoes_gerais'] ?? null,
                'substitui_id' => isset($data['substitui_id']) ? (int) $data['substitui_id'] : null,
            ]
        );
    }

    public function updateCampos(int $id, array $fields): void
    {
        if ($id <= 0 || $fields === []) {
            return;
        }
        $allowed = [
            'status', 'finalidade', 'observacoes_gerais', 'numero_registro_sed', 'hash_validacao', 'snapshot_json',
            'pdf_path', 'emitido_em', 'emitido_por', 'conferido_em', 'conferido_por', 'unidade_id',
        ];
        $sets = [];
        $params = ['id' => $id];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = $col . ' = :' . $col;
            $params[$col] = $fields[$col];
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE historico_documentos SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarItens(int $historicoId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM historico_itens
             WHERE historico_id = :id
             ORDER BY ano_letivo ASC, ordem ASC, id ASC",
            ['id' => (int) $historicoId]
        ) ?: [];
    }

    public function limparItensInternos(int $historicoId): void
    {
        $this->db->delete(
            "DELETE FROM historico_itens WHERE historico_id = :id AND origem = 'Interno'",
            ['id' => (int) $historicoId]
        );
    }

    public function limparResultados(int $historicoId): void
    {
        $this->db->delete(
            "DELETE FROM historico_resultados_anuais WHERE historico_id = :id",
            ['id' => (int) $historicoId]
        );
    }

    public function inserirItem(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO historico_itens
                (historico_id, ano_letivo, serie_ano, componente, materia_id, resultado_valor,
                 parecer_descritivo, carga_horaria, frequencia_percentual, origem, escola_origem, ordem)
             VALUES
                (:historico_id, :ano_letivo, :serie_ano, :componente, :materia_id, :resultado_valor,
                 :parecer_descritivo, :carga_horaria, :frequencia_percentual, :origem, :escola_origem, :ordem)",
            [
                'historico_id' => (int) $data['historico_id'],
                'ano_letivo' => (string) $data['ano_letivo'],
                'serie_ano' => (string) $data['serie_ano'],
                'componente' => (string) $data['componente'],
                'materia_id' => isset($data['materia_id']) ? (int) $data['materia_id'] : null,
                'resultado_valor' => $data['resultado_valor'] ?? null,
                'parecer_descritivo' => $data['parecer_descritivo'] ?? null,
                'carga_horaria' => isset($data['carga_horaria']) ? (int) $data['carga_horaria'] : null,
                'frequencia_percentual' => $data['frequencia_percentual'] ?? null,
                'origem' => ($data['origem'] ?? 'Interno') === 'Externo' ? 'Externo' : 'Interno',
                'escola_origem' => $data['escola_origem'] ?? null,
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
    }

    public function atualizarItem(int $itemId, array $fields): void
    {
        $allowed = [
            'ano_letivo', 'serie_ano', 'componente', 'resultado_valor', 'parecer_descritivo',
            'carga_horaria', 'frequencia_percentual', 'escola_origem',
        ];
        $sets = [];
        $params = ['id' => (int) $itemId];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = $col . ' = :' . $col;
            $params[$col] = $fields[$col];
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE historico_itens SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function excluirItem(int $itemId, int $historicoId): bool
    {
        return (bool) $this->db->delete(
            "DELETE FROM historico_itens WHERE id = :id AND historico_id = :historico_id AND origem = 'Externo'",
            ['id' => (int) $itemId, 'historico_id' => (int) $historicoId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarResultados(int $historicoId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM historico_resultados_anuais
             WHERE historico_id = :id
             ORDER BY ano_letivo ASC, id ASC",
            ['id' => (int) $historicoId]
        ) ?: [];
    }

    public function upsertResultado(array $data): void
    {
        $resultado = (string) ($data['resultado'] ?? 'Cursando');
        if (!in_array($resultado, self::RESULTADOS, true)) {
            $resultado = 'Cursando';
        }
        $existing = $this->db->fetch(
            "SELECT id FROM historico_resultados_anuais
             WHERE historico_id = :h AND ano_letivo = :a AND serie_ano = :s LIMIT 1",
            [
                'h' => (int) $data['historico_id'],
                'a' => (string) $data['ano_letivo'],
                's' => (string) $data['serie_ano'],
            ]
        );
        if ($existing) {
            $this->db->update(
                "UPDATE historico_resultados_anuais
                 SET resultado = :resultado, observacao = :observacao
                 WHERE id = :id",
                [
                    'resultado' => $resultado,
                    'observacao' => $data['observacao'] ?? null,
                    'id' => (int) $existing['id'],
                ]
            );
            return;
        }
        $this->db->insert(
            "INSERT INTO historico_resultados_anuais
                (historico_id, ano_letivo, serie_ano, resultado, observacao)
             VALUES (:historico_id, :ano_letivo, :serie_ano, :resultado, :observacao)",
            [
                'historico_id' => (int) $data['historico_id'],
                'ano_letivo' => (string) $data['ano_letivo'],
                'serie_ano' => (string) $data['serie_ano'],
                'resultado' => $resultado,
                'observacao' => $data['observacao'] ?? null,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarAssinaturas(int $historicoId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM historico_assinaturas
             WHERE historico_id = :id
             ORDER BY assinado_em ASC",
            ['id' => (int) $historicoId]
        ) ?: [];
    }

    public function registrarAssinatura(array $data): int
    {
        $cargo = (string) ($data['cargo'] ?? 'Outro');
        if (!in_array($cargo, self::CARGOS, true)) {
            $cargo = 'Outro';
        }
        $params = [
            'historico_id' => (int) $data['historico_id'],
            'usuario_id' => (int) $data['usuario_id'],
            'usuario_nome' => $data['usuario_nome'] ?? null,
            'cargo' => $cargo,
            'numero_registro' => $data['numero_registro'] ?? null,
            'tipo' => $data['tipo'] ?? 'Eletronica_Simples',
            'ip_origem' => $data['ip_origem'] ?? null,
        ];
        $existing = $this->db->fetch(
            "SELECT id FROM historico_assinaturas
             WHERE historico_id = :historico_id AND cargo = :cargo LIMIT 1",
            [
                'historico_id' => $params['historico_id'],
                'cargo' => $cargo,
            ]
        );
        if ($existing) {
            $this->db->update(
                "UPDATE historico_assinaturas
                 SET usuario_id = :usuario_id,
                     usuario_nome = :usuario_nome,
                     numero_registro = :numero_registro,
                     tipo = :tipo,
                     ip_origem = :ip_origem,
                     assinado_em = NOW()
                 WHERE id = :id",
                [
                    'usuario_id' => $params['usuario_id'],
                    'usuario_nome' => $params['usuario_nome'],
                    'numero_registro' => $params['numero_registro'],
                    'tipo' => $params['tipo'],
                    'ip_origem' => $params['ip_origem'],
                    'id' => (int) $existing['id'],
                ]
            );
            return (int) $existing['id'];
        }
        return (int) $this->db->insert(
            "INSERT INTO historico_assinaturas
                (historico_id, usuario_id, usuario_nome, cargo, numero_registro, tipo, ip_origem, assinado_em)
             VALUES
                (:historico_id, :usuario_id, :usuario_nome, :cargo, :numero_registro, :tipo, :ip_origem, NOW())",
            $params
        );
    }
}
