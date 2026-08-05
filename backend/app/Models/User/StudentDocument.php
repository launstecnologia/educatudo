<?php
/**
 * EducaTudo - Documentos / checklist de entrega do aluno.
 */

class StudentDocument
{
    private $db;

    public const STATUS = ['pendente', 'entregue', 'dispensado'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Checklist padrão de documentos (tipo => rótulo). */
    public static function checklist(): array
    {
        return [
            'rg' => 'RG (cópia)',
            'cpf' => 'CPF (cópia)',
            'certidao_nascimento' => 'Certidão de nascimento',
            'comprovante_residencia' => 'Comprovante de residência',
            'foto_3x4' => 'Foto 3x4',
            'historico_escolar' => 'Histórico escolar',
            'declaracao_transferencia' => 'Declaração de transferência',
            'carteira_vacinacao' => 'Carteira de vacinação',
            'laudo_medico' => 'Laudo médico / AEE',
            'documento_responsavel' => 'Documento do responsável',
            'outros' => 'Outros documentos',
        ];
    }

    public static function tipoLabel(string $tipo, ?string $titulo = null): string
    {
        $checklist = self::checklist();
        if ($tipo === 'outros' && $titulo !== null && trim($titulo) !== '') {
            return trim($titulo);
        }
        return $checklist[$tipo] ?? ($titulo ?: $tipo);
    }

    public function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'alunos_documentos'");
            $cache = $row !== false && !empty($row);
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    /** @return array<int,array<string,mixed>> */
    public function getByAluno(int $alunoId): array
    {
        if ($alunoId <= 0 || !$this->tableExists()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM alunos_documentos WHERE aluno_id = :id ORDER BY updated_at DESC, id DESC',
            ['id' => $alunoId]
        );

        return is_array($rows) ? $rows : [];
    }

    public function find(int $alunoId, int $docId): ?array
    {
        if ($alunoId <= 0 || $docId <= 0 || !$this->tableExists()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM alunos_documentos WHERE id = :id AND aluno_id = :aluno_id',
            ['id' => $docId, 'aluno_id' => $alunoId]
        );

        return is_array($row) ? $row : null;
    }

    private function findByTipo(int $alunoId, string $tipo): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM alunos_documentos WHERE aluno_id = :aluno_id AND tipo = :tipo ORDER BY id ASC LIMIT 1',
            ['aluno_id' => $alunoId, 'tipo' => $tipo]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Cria/atualiza um documento. Retorna o id afetado.
     * Para tipos predefinidos (exceto "outros") faz upsert por (aluno, tipo).
     * @param array<string,mixed> $data
     */
    public function save(int $alunoId, string $tipo, array $data, ?int $docId = null): int
    {
        if ($alunoId <= 0 || !$this->tableExists()) {
            return 0;
        }

        $existing = null;
        if ($docId !== null && $docId > 0) {
            $existing = $this->find($alunoId, $docId);
        } elseif ($tipo !== 'outros') {
            $existing = $this->findByTipo($alunoId, $tipo);
        }

        $status = in_array(($data['status'] ?? ''), self::STATUS, true) ? $data['status'] : 'pendente';
        $entregueEm = $status === 'entregue' ? ($data['entregue_em'] ?? date('Y-m-d H:i:s')) : null;

        $fields = [
            'titulo' => $data['titulo'] ?? null,
            'status' => $status,
            'observacao' => $data['observacao'] ?? null,
            'entregue_em' => $entregueEm,
        ];
        // Campos de arquivo só são sobrescritos quando enviados.
        $temArquivo = array_key_exists('arquivo_key', $data);

        if ($existing) {
            $set = 'titulo = :titulo, status = :status, observacao = :observacao, entregue_em = :entregue_em, updated_at = NOW()';
            $params = array_merge($fields, ['id' => (int) $existing['id']]);
            if ($temArquivo) {
                $set .= ', arquivo_key = :arquivo_key, arquivo_nome = :arquivo_nome, arquivo_mime = :arquivo_mime, arquivo_tamanho = :arquivo_tamanho';
                $params['arquivo_key'] = $data['arquivo_key'];
                $params['arquivo_nome'] = $data['arquivo_nome'] ?? null;
                $params['arquivo_mime'] = $data['arquivo_mime'] ?? null;
                $params['arquivo_tamanho'] = $data['arquivo_tamanho'] ?? null;
            }
            $this->db->update("UPDATE alunos_documentos SET {$set} WHERE id = :id", $params);

            return (int) $existing['id'];
        }

        $params = [
            'aluno_id' => $alunoId,
            'tipo' => $tipo,
            'titulo' => $fields['titulo'],
            'status' => $fields['status'],
            'observacao' => $fields['observacao'],
            'entregue_em' => $fields['entregue_em'],
            'arquivo_key' => $temArquivo ? $data['arquivo_key'] : null,
            'arquivo_nome' => $temArquivo ? ($data['arquivo_nome'] ?? null) : null,
            'arquivo_mime' => $temArquivo ? ($data['arquivo_mime'] ?? null) : null,
            'arquivo_tamanho' => $temArquivo ? ($data['arquivo_tamanho'] ?? null) : null,
            'created_by' => $data['created_by'] ?? null,
        ];

        return (int) $this->db->insert(
            'INSERT INTO alunos_documentos
                (aluno_id, tipo, titulo, status, observacao, entregue_em, arquivo_key, arquivo_nome, arquivo_mime, arquivo_tamanho, created_by, created_at, updated_at)
             VALUES
                (:aluno_id, :tipo, :titulo, :status, :observacao, :entregue_em, :arquivo_key, :arquivo_nome, :arquivo_mime, :arquivo_tamanho, :created_by, NOW(), NOW())',
            $params
        );
    }

    /** Limpa apenas a referência ao arquivo (mantém o registro de status). */
    public function clearArquivo(int $alunoId, int $docId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $this->db->update(
            'UPDATE alunos_documentos
             SET arquivo_key = NULL, arquivo_nome = NULL, arquivo_mime = NULL, arquivo_tamanho = NULL, updated_at = NOW()
             WHERE id = :id AND aluno_id = :aluno_id',
            ['id' => $docId, 'aluno_id' => $alunoId]
        );
    }

    public function delete(int $alunoId, int $docId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $this->db->query(
            'DELETE FROM alunos_documentos WHERE id = :id AND aluno_id = :aluno_id',
            ['id' => $docId, 'aluno_id' => $alunoId]
        );
    }
}
