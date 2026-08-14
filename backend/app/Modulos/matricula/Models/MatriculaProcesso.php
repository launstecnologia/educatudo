<?php

namespace App\Modulos\Matricula\Models;

use Database;

/**
 * Processo de matrícula/rematrícula (workflow digital).
 * Tabela: matricula_processos — distinta de `matricula` (vínculo acadêmico).
 */
class MatriculaProcesso
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function schemaReady(): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matricula_processos' LIMIT 1"
            );
            return !empty($row['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function temColuna(string $coluna): bool
    {
        static $cache = [];
        $tk = class_exists('TenantResolver', false)
            ? \TenantResolver::workerCacheKey()
            : (defined('TENANT_ID') ? ('t' . TENANT_ID) : 'no_tenant');
        $key = $tk . ':' . $coluna;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return $cache[$key] = false;
        }
        try {
            $cache[$key] = (bool) $this->db->fetch(
                'SHOW COLUMNS FROM matricula_processos LIKE ?',
                [$coluna]
            );
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }

    public function temTabelaResponsaveis(): bool
    {
        return $this->temTabela('matricula_processos_responsaveis');
    }

    public function temTabelaProdutos(): bool
    {
        return $this->temTabela('matricula_processos_produtos');
    }

    public function temTabelaDocumentos(): bool
    {
        return $this->temTabela('matricula_processos_documentos');
    }

    private function temTabela(string $nome): bool
    {
        static $cache = [];
        $tk = class_exists('TenantResolver', false)
            ? \TenantResolver::workerCacheKey()
            : (defined('TENANT_ID') ? ('t' . TENANT_ID) : 'no_tenant');
        $key = $tk . ':' . $nome;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!preg_match('/^[a-z0-9_]+$/i', $nome)) {
            return $cache[$key] = false;
        }
        try {
            $cache[$key] = (bool) $this->db->fetch('SHOW TABLES LIKE ?', [$nome]);
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT e.*,
                    al.ano AS ano_letivo_nome,
                    t.nome AS turma_nome, t.serie AS turma_serie,
                    al2.nome AS aluno_nome_atual
             FROM matricula_processos e
             LEFT JOIN ano_letivo al  ON al.id  = e.ano_letivo_id
             LEFT JOIN turmas t       ON t.id   = e.turma_id
             LEFT JOIN alunos al2     ON al2.id = e.aluno_id
             WHERE e.id = ?",
            [$id]
        );
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $row = $this->db->fetch(
            "SELECT e.*, al.ano AS ano_letivo_nome, t.nome AS turma_nome, t.serie AS turma_serie
             FROM matricula_processos e
             LEFT JOIN ano_letivo al ON al.id = e.ano_letivo_id
             LEFT JOIN turmas t      ON t.id  = e.turma_id
             WHERE e.contrato_token = ?",
            [$token]
        );
        return $row ?: null;
    }

    public function findByZapSignDocToken(string $docToken): ?array
    {
        $docToken = trim($docToken);
        if ($docToken === '' || !$this->temColuna('zapsign_doc_token')) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM matricula_processos WHERE zapsign_doc_token = ? LIMIT 1',
            [$docToken]
        );
        return $row ?: null;
    }

    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = "SELECT e.*,
                       al.ano AS ano_letivo_nome,
                       t.nome AS turma_nome, t.serie AS turma_serie
                FROM matricula_processos e
                LEFT JOIN ano_letivo al ON al.id = e.ano_letivo_id
                LEFT JOIN turmas t      ON t.id  = e.turma_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.created_at DESC
                LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM matricula_processos e WHERE " . implode(' AND ', $where),
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /** @return array{0: list<string>, 1: list<mixed>} */
    private function buildFilters(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'e.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['tipo'])) {
            $where[] = 'e.tipo = ?';
            $params[] = $filters['tipo'];
        }
        if (!empty($filters['ano_letivo_id'])) {
            $where[] = 'e.ano_letivo_id = ?';
            $params[] = (int) $filters['ano_letivo_id'];
        }
        if (!empty($filters['turma_id'])) {
            $where[] = 'e.turma_id = ?';
            $params[] = (int) $filters['turma_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(e.aluno_nome LIKE ? OR e.resp_nome LIKE ? OR e.resp_telefone LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        return [$where, $params];
    }

    public function create(array $data): int
    {
        $cols = [
            'tipo', 'status', 'aluno_id', 'ano_letivo_id', 'turma_id', 'serie_id',
            'aluno_nome', 'aluno_cpf', 'aluno_data_nasc', 'aluno_genero', 'aluno_email', 'aluno_telefone',
            'resp_nome', 'resp_cpf', 'resp_email', 'resp_telefone', 'resp_parentesco', 'resp_endereco',
            'origem', 'observacoes', 'expira_em', 'criado_por',
        ];
        $optional = [
            'aluno_rg', 'aluno_endereco',
            'aluno_end_numero', 'aluno_end_complemento', 'aluno_end_bairro', 'aluno_end_cidade', 'aluno_end_uf', 'aluno_end_cep',
            'aluno_escola_anterior',
            'finance_plan_id', 'finance_cobrancas',
            'pagamento_status', 'pagante_modo', 'documento_assinatura_codigo',
            'contrato_assinado_path',
        ];
        foreach ($optional as $col) {
            if (array_key_exists($col, $data) && $this->temColuna($col)) {
                $cols[] = $col;
            }
        }

        $placeholders = [];
        $params = [];
        foreach ($cols as $col) {
            $placeholders[] = '?';
            $val = $data[$col] ?? null;
            if ($col === 'tipo') {
                $val = $val ?? 'nova';
            } elseif ($col === 'status') {
                $val = $val ?? 'rascunho';
            } elseif ($col === 'origem') {
                $val = $val ?? 'interno';
            } elseif (in_array($col, ['aluno_nome', 'resp_nome'], true)) {
                $val = $val ?? '';
            }
            $params[] = $val;
        }

        $this->db->insert(
            'INSERT INTO matricula_processos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = [
            'status', 'aluno_id', 'ano_letivo_id', 'turma_id', 'serie_id',
            'aluno_nome', 'aluno_cpf', 'aluno_rg', 'aluno_data_nasc', 'aluno_genero',
            'aluno_email', 'aluno_telefone', 'aluno_endereco',
            'aluno_end_numero', 'aluno_end_complemento', 'aluno_end_bairro', 'aluno_end_cidade', 'aluno_end_uf', 'aluno_end_cep',
            'aluno_escola_anterior',
            'resp_nome', 'resp_cpf', 'resp_email', 'resp_telefone', 'resp_parentesco', 'resp_endereco',
            'finance_plan_id', 'finance_cobrancas', 'pagamento_status', 'pagante_modo',
            'documento_assinatura_codigo', 'dados_confirmados_em',
            'contrato_pdf_path', 'contrato_token', 'contrato_hash', 'contrato_assinado_path',
            'assinado_em', 'assinante_ip', 'assinante_nome',
            'zapsign_doc_token', 'zapsign_signer_token', 'zapsign_sign_url',
            'zapsign_status', 'zapsign_enviado_em',
            'observacoes', 'expira_em', 'origem', 'tipo',
        ];
        $sets = [];
        $params = [];
        foreach ($data as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                continue;
            }
            if (!$this->temColuna($k) && !in_array($k, [
                'status', 'aluno_id', 'ano_letivo_id', 'turma_id', 'serie_id',
                'aluno_nome', 'aluno_cpf', 'aluno_data_nasc', 'aluno_genero',
                'aluno_email', 'aluno_telefone',
                'resp_nome', 'resp_cpf', 'resp_email', 'resp_telefone', 'resp_parentesco', 'resp_endereco',
                'contrato_pdf_path', 'contrato_token', 'contrato_hash',
                'assinado_em', 'assinante_ip', 'assinante_nome',
                'observacoes', 'expira_em', 'origem', 'tipo',
            ], true)) {
                continue;
            }
            $sets[] = "`$k` = ?";
            $params[] = $v;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $this->db->update(
            'UPDATE matricula_processos SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
    }

    public function transition(int $id, string $newStatus, ?array $user = null, string $acao = ''): void
    {
        $current = $this->db->fetch('SELECT status FROM matricula_processos WHERE id = ?', [$id]);
        $this->db->update('UPDATE matricula_processos SET status = ? WHERE id = ?', [$newStatus, $id]);
        $this->db->insert(
            'INSERT INTO matricula_processos_auditorias
             (enrollment_id, status_de, status_para, acao, usuario_id, usuario_nome, ip)
             VALUES (?,?,?,?,?,?,?)',
            [
                $id,
                $current['status'] ?? null,
                $newStatus,
                $acao ?: null,
                $user['id'] ?? null,
                $user['nome'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    public function countsByStatus(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS total FROM matricula_processos GROUP BY status'
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['total'];
        }
        return $out;
    }

    public function getAuditTrail(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM matricula_processos_auditorias WHERE enrollment_id = ? ORDER BY created_at ASC',
            [$id]
        ) ?: [];
    }

    public function listarResponsaveis(int $enrollmentId): array
    {
        if (!$this->temTabelaResponsaveis()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT * FROM matricula_processos_responsaveis WHERE enrollment_id = ? ORDER BY ordem ASC',
            [$enrollmentId]
        ) ?: [];
    }

    public function substituirResponsaveis(int $enrollmentId, array $responsaveis): void
    {
        if (!$this->temTabelaResponsaveis()) {
            return;
        }
        $this->db->query(
            'DELETE FROM matricula_processos_responsaveis WHERE enrollment_id = ?',
            [$enrollmentId]
        );

        $temRg = $this->temColunaResponsavel('rg');
        $temDataNasc = $this->temColunaResponsavel('data_nascimento');
        $temEstadoCivil = $this->temColunaResponsavel('estado_civil');
        $temProfissao = $this->temColunaResponsavel('profissao');
        $temEmpresa = $this->temColunaResponsavel('empresa');

        $ordem = 1;
        foreach ($responsaveis as $r) {
            if (!is_array($r) || trim((string) ($r['nome'] ?? '')) === '') {
                continue;
            }

            $cols = [
                'enrollment_id', 'ordem', 'tipo_vinculo', 'is_pedagogico', 'is_financeiro', 'nome',
                'tipo_documento', 'documento', 'email', 'telefone', 'endereco', 'percentual',
            ];
            $params = [
                $enrollmentId,
                $ordem++,
                $r['tipo_vinculo'] ?? $r['parentesco'] ?? null,
                !empty($r['is_pedagogico']) ? 1 : 0,
                !empty($r['is_financeiro']) ? 1 : 0,
                trim((string) $r['nome']),
                ($r['tipo_documento'] ?? 'cpf') === 'cnpj' ? 'cnpj' : 'cpf',
                $r['documento'] ?? $r['cpf'] ?? null,
                $r['email'] ?? null,
                $r['telefone'] ?? null,
                $r['endereco'] ?? null,
                isset($r['percentual']) && $r['percentual'] !== '' ? (float) $r['percentual'] : null,
            ];

            if ($temRg) {
                $cols[] = 'rg';
                $params[] = trim((string) ($r['rg'] ?? '')) ?: null;
            }
            if ($temDataNasc) {
                $cols[] = 'data_nascimento';
                $params[] = trim((string) ($r['data_nascimento'] ?? '')) ?: null;
            }
            if ($temEstadoCivil) {
                $cols[] = 'estado_civil';
                $params[] = trim((string) ($r['estado_civil'] ?? '')) ?: null;
            }
            if ($temProfissao) {
                $cols[] = 'profissao';
                $params[] = trim((string) ($r['profissao'] ?? '')) ?: null;
            }
            if ($temEmpresa) {
                $cols[] = 'empresa';
                $params[] = trim((string) ($r['empresa'] ?? '')) ?: null;
            }
            foreach (['end_cep', 'end_numero', 'end_complemento', 'end_bairro', 'end_cidade', 'end_uf'] as $endCol) {
                if ($this->temColunaResponsavel($endCol)) {
                    $cols[] = $endCol;
                    $val = trim((string) ($r[$endCol] ?? ''));
                    if ($endCol === 'end_uf') {
                        $val = strtoupper(substr($val, 0, 2));
                    }
                    $params[] = $val !== '' ? $val : null;
                }
            }

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $this->db->insert(
                'INSERT INTO matricula_processos_responsaveis (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')',
                $params
            );
        }
    }

    private function temColunaResponsavel(string $coluna): bool
    {
        static $cache = [];
        $tk = class_exists('TenantResolver', false)
            ? \TenantResolver::workerCacheKey()
            : (defined('TENANT_ID') ? ('t' . TENANT_ID) : 'no_tenant');
        $key = $tk . ':mpr:' . $coluna;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return $cache[$key] = false;
        }
        try {
            $cache[$key] = (bool) $this->db->fetch(
                'SHOW COLUMNS FROM matricula_processos_responsaveis LIKE ?',
                [$coluna]
            );
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }

    public function listarProdutos(int $enrollmentId): array
    {
        if (!$this->temTabelaProdutos()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT * FROM matricula_processos_produtos WHERE enrollment_id = ? ORDER BY ordem ASC',
            [$enrollmentId]
        ) ?: [];
    }

    public function substituirProdutos(int $enrollmentId, array $produtos): void
    {
        if (!$this->temTabelaProdutos()) {
            return;
        }
        $this->db->query(
            'DELETE FROM matricula_processos_produtos WHERE enrollment_id = ?',
            [$enrollmentId]
        );
        $ordem = 1;
        foreach ($produtos as $p) {
            if (!is_array($p)) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO matricula_processos_produtos
                 (enrollment_id, plan_item_id, tipo, descricao, incluir, valor_base, num_parcelas,
                  mes_inicio, status, ordem)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [
                    $enrollmentId,
                    $p['plan_item_id'] ?? null,
                    $p['tipo'] ?? 'mensalidade',
                    $p['descricao'] ?? ($p['tipo'] ?? 'Produto'),
                    isset($p['incluir']) ? (int) (bool) $p['incluir'] : 1,
                    (float) ($p['valor_base'] ?? 0),
                    (int) ($p['num_parcelas'] ?? 1),
                    $p['mes_inicio'] ?? null,
                    $p['status'] ?? 'pendente',
                    $ordem++,
                ]
            );
        }
    }

    public function listarDocumentos(int $enrollmentId): array
    {
        if (!$this->temTabelaDocumentos()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT * FROM matricula_processos_documentos WHERE enrollment_id = ? ORDER BY created_at DESC',
            [$enrollmentId]
        ) ?: [];
    }

    public function adicionarDocumento(int $enrollmentId, array $data): int
    {
        $this->db->insert(
            'INSERT INTO matricula_processos_documentos
             (enrollment_id, tipo, nome_original, path, mime, tamanho, criado_por)
             VALUES (?,?,?,?,?,?,?)',
            [
                $enrollmentId,
                $data['tipo'] ?? 'outro',
                $data['nome_original'] ?? '',
                $data['path'] ?? '',
                $data['mime'] ?? null,
                $data['tamanho'] ?? null,
                $data['criado_por'] ?? null,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function removerDocumento(int $enrollmentId, int $docId): ?array
    {
        $doc = $this->db->fetch(
            'SELECT * FROM matricula_processos_documentos WHERE id = ? AND enrollment_id = ?',
            [$docId, $enrollmentId]
        );
        if (!$doc) {
            return null;
        }
        $this->db->query(
            'DELETE FROM matricula_processos_documentos WHERE id = ? AND enrollment_id = ?',
            [$docId, $enrollmentId]
        );
        return $doc;
    }
}
