<?php
/**
 * EducaTudo - Modelo de Alunos
 * Gerencia operações de banco de dados para alunos
 */

class Student
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Colunas realmente existentes na tabela alunos (cache por tenant).
     * Usado para filtrar campos quando alguma migration ainda
     * não rodou no tenant, evitando "Unknown column".
     *
     * @return array<string, bool>
     */
    private function colunasAlunos(): array
    {
        static $cache = [];
        $key = class_exists('TenantResolver', false)
            ? \TenantResolver::workerCacheKey()
            : (defined('TENANT_ID') ? ('t' . TENANT_ID) : 'no_tenant');
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $cache[$key] = $this->lerColunasAlunosDoSchema();
        return $cache[$key];
    }

    /** @return array<string, bool> */
    private function lerColunasAlunosDoSchema(): array
    {
        $mapa = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos'"
            );
            $mapa = $this->mapaNomesColunas($rows, ['COLUMN_NAME', 'column_name']);
        } catch (\Throwable $e) {
            $mapa = [];
        }
        if ($this->mapaColunasTemNomes($mapa)) {
            return $mapa;
        }
        try {
            $rows = $this->db->fetchAll('SHOW COLUMNS FROM alunos');
            return $this->mapaNomesColunas($rows, ['Field', 'field', 'COLUMN_NAME', 'column_name']);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $chaves
     * @return array<string, bool>
     */
    private function mapaNomesColunas(array $rows, array $chaves): array
    {
        $mapa = [];
        foreach ($rows as $r) {
            $nome = '';
            foreach ($chaves as $chave) {
                if (isset($r[$chave]) && (string) $r[$chave] !== '') {
                    $nome = (string) $r[$chave];
                    break;
                }
            }
            if ($nome !== '') {
                $mapa[$nome] = true;
            }
        }
        return $mapa;
    }

    /** @param array<string, bool> $mapa */
    private function mapaColunasTemNomes(array $mapa): bool
    {
        foreach ($mapa as $nome => $ok) {
            if ($ok && (string) $nome !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private function filtrarColunasExistentes(array $columns): array
    {
        $existentes = $this->colunasAlunos();
        if (!$this->mapaColunasTemNomes($existentes)) {
            $evitar = ['cpf' => true, 'foto_url' => true];
            return array_values(array_filter(
                $columns,
                static fn ($col) => !isset($evitar[$col])
            ));
        }
        return array_values(array_filter(
            $columns,
            static fn ($col) => isset($existentes[$col])
        ));
    }
    
    /**
     * Busca todos os alunos
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT a.*, t.nome as turma_nome, t.serie as turma_serie,
                    COALESCE(GROUP_CONCAT(DISTINCT rp.nome ORDER BY rp.nome SEPARATOR ', '), p.nome) as responsavel_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             LEFT JOIN alunos_responsaveis ar ON ar.aluno_id = a.id AND ar.ativo = 1
             LEFT JOIN responsaveis rp ON rp.id = ar.responsavel_id
             LEFT JOIN responsaveis p ON a.responsavel_id = p.id
             GROUP BY a.id
             ORDER BY a.nome ASC"
        );
    }
    
    /**
     * Busca alunos ativos
     */
    public function getActive()
    {
        return $this->db->fetchAll(
            "SELECT a.*, t.nome as turma_nome, t.serie as turma_serie,
                    COALESCE(GROUP_CONCAT(DISTINCT rp.nome ORDER BY rp.nome SEPARATOR ', '), p.nome) as responsavel_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             LEFT JOIN alunos_responsaveis ar ON ar.aluno_id = a.id AND ar.ativo = 1
             LEFT JOIN responsaveis rp ON rp.id = ar.responsavel_id
             LEFT JOIN responsaveis p ON a.responsavel_id = p.id
             WHERE a.ativo = 1
             GROUP BY a.id
             ORDER BY a.nome ASC"
        );
    }
    
    /**
     * Busca aluno por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome, t.serie as turma_serie,
                    COALESCE(GROUP_CONCAT(DISTINCT rp.nome ORDER BY rp.nome SEPARATOR ', '), p.nome) as responsavel_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             LEFT JOIN alunos_responsaveis ar ON ar.aluno_id = a.id AND ar.ativo = 1
             LEFT JOIN responsaveis rp ON rp.id = ar.responsavel_id
             LEFT JOIN responsaveis p ON a.responsavel_id = p.id
             WHERE a.id = :id
             GROUP BY a.id",
            ['id' => $id]
        );
    }
    
    /**
     * Busca todos os alunos que tenham o nome exatamente igual (após trim).
     * Retorna array (vazio, 1 ou vários). Usado na importação por nome.
     */
    public function findAllByNome($nome)
    {
        $nome = trim($nome);
        if ($nome === '') {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM alunos WHERE TRIM(nome) = :nome ORDER BY id ASC",
            ['nome' => $nome]
        );
    }

    /**
     * Busca aluno por RA
     */
    public function findByRA($ra)
    {
        // Dois placeholders distintos: o mesmo :ra duas vezes no SQL causa HY093 no PDO MySQL
        return $this->db->fetch(
            "SELECT * FROM alunos WHERE (ra = :ra_val OR codigo_aluno = :codigo_val)",
            ['ra_val' => $ra, 'codigo_val' => $ra]
        );
    }
    
    /**
     * Cria novo aluno
     */
    public function create($data)
    {
        $columns = ['nome', 'nickname', 'email', 'senha_hash', 'ra', 'codigo_aluno', 'cpf', 'foto_url', 'turma_id', 'serie', 'data_nasc', 'responsavel_id', 'ativo', 'pagante'];
        $optionalColumns = ['unidade_id', 'telefone', 'celular', 'rg', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
            'nome_social', 'nacionalidade', 'naturalidade', 'uf_nascimento', 'cor_raca', 'orgao_emissor', 'uf_rg',
            'certidao_nascimento', 'certidao_livro', 'certidao_folha', 'certidao_termo', 'nis', 'passaporte', 'rne',
            'zona', 'pais', 'whatsapp', 'email_secundario',
            'nome_mae', 'nome_pai', 'codigo_inep'];
        $colsExistentes = $this->colunasAlunos();
        if (!$this->mapaColunasTemNomes($colsExistentes)) {
            $optionalColumns = [];
        } else {
            $optionalColumns = array_values(array_filter(
                $optionalColumns,
                static fn ($col) => isset($colsExistentes[$col])
            ));
        }
        foreach ($optionalColumns as $column) {
            if (array_key_exists($column, $data)) {
                $columns[] = $column;
            }
        }

        $senha = $data['senha'] ?? null;
        $senhaHash = $senha !== null && $senha !== '' ? password_hash($senha, PASSWORD_DEFAULT) : null;
        require_once __DIR__ . '/../../Helpers/StudentFormHelper.php';
        $dataNasc = StudentFormHelper::normalizarDataNasc($data['data_nasc'] ?? null);

        $params = [
            'nome' => $data['nome'],
            'nickname' => $data['nickname'] ?? null,
            'email' => $data['email'] ?? null,
            'senha_hash' => $senhaHash,
            'ra' => $data['ra'] ?? null,
            'codigo_aluno' => $data['codigo_aluno'] ?? null,
            'cpf' => $data['cpf'] ?? null,
            'foto_url' => $data['foto_url'] ?? null,
            'turma_id' => $data['turma_id'] ?? null,
            'serie' => $data['serie'] ?? null,
            'responsavel_id' => $data['responsavel_id'] ?? null,
            'ativo' => $data['ativo'] ?? 1,
            'pagante' => $data['pagante'] ?? 1,
        ];
        if ($dataNasc !== null) {
            $params['data_nasc'] = $dataNasc;
        } else {
            $columns = array_values(array_filter(
                $columns,
                static fn ($column) => $column !== 'data_nasc'
            ));
        }
        foreach ($optionalColumns as $column) {
            if (array_key_exists($column, $data)) {
                $params[$column] = $data[$column];
            }
        }

        $columns = $this->filtrarColunasExistentes($columns);
        if (isset($colsExistentes['password']) && !in_array('password', $columns, true)) {
            $columns[] = 'password';
            $params['password'] = (string) ($data['password'] ?? '');
        }
        $params = array_intersect_key($params, array_flip($columns));

        $placeholders = array_map(static fn ($col) => ':' . $col, $columns);
        $sql = 'INSERT INTO alunos (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        return $this->db->insert($sql, $params);
    }
    
    /**
     * Atualiza aluno
     * Usa placeholders posicionais (?): com PDO MySQL e prepares nativos, parâmetros nomeados
     * em UPDATE longos podem gerar SQLSTATE[HY093] em alguns ambientes.
     */
    public function update($id, $data)
    {
        require_once __DIR__ . '/../../Helpers/StudentFormHelper.php';
        $dataNasc = StudentFormHelper::normalizarDataNasc($data['data_nasc'] ?? null);

        $base = [
            'nome' => $data['nome'],
            'nickname' => $data['nickname'] ?? null,
            'email' => $data['email'] ?? null,
            'ra' => $data['ra'] ?? null,
            'codigo_aluno' => $data['codigo_aluno'] ?? null,
            'cpf' => $data['cpf'] ?? null,
            'foto_url' => $data['foto_url'] ?? null,
            'turma_id' => $data['turma_id'] ?? null,
            'serie' => $data['serie'] ?? null,
        ];
        $setParts = [];
        $params = [];
        foreach ($this->filtrarColunasExistentes(array_keys($base)) as $col) {
            $setParts[] = $col . ' = ?';
            $params[] = $base[$col];
        }

        if ($dataNasc === null) {
            $setParts[] = 'data_nasc = NULL';
        } else {
            $setParts[] = 'data_nasc = ?';
            $params[] = $dataNasc;
        }

        $setParts[] = 'responsavel_id = ?';
        $setParts[] = 'ativo = ?';
        $setParts[] = 'pagante = ?';
        $params[] = $data['responsavel_id'] ?? null;
        $params[] = $data['ativo'] ?? 1;
        $params[] = $data['pagante'] ?? 1;

        if (array_key_exists('primeiro_acesso', $data)) {
            $setParts[] = 'primeiro_acesso = ?';
            $params[] = (int)$data['primeiro_acesso'];
        }

        if (array_key_exists('sexo', $data)) {
            $setParts[] = 'sexo = ?';
            $params[] = $data['sexo'];
        }

        $camposOpcionais = ['unidade_id', 'telefone', 'celular', 'rg', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
            'nome_social', 'nacionalidade', 'naturalidade', 'uf_nascimento', 'cor_raca', 'orgao_emissor', 'uf_rg',
            'certidao_nascimento', 'certidao_livro', 'certidao_folha', 'certidao_termo', 'nis', 'passaporte', 'rne',
            'zona', 'pais', 'whatsapp', 'email_secundario',
            'nome_mae', 'nome_pai', 'codigo_inep'];
        $colsExistentes = $this->colunasAlunos();
        if (!$this->mapaColunasTemNomes($colsExistentes)) {
            $camposOpcionais = [];
        } else {
            $camposOpcionais = array_values(array_filter(
                $camposOpcionais,
                static fn ($col) => isset($colsExistentes[$col])
            ));
        }
        foreach ($camposOpcionais as $campoEndereco) {
            if (array_key_exists($campoEndereco, $data)) {
                $setParts[] = $campoEndereco . ' = ?';
                $params[] = $data[$campoEndereco];
            }
        }

        if (!empty($data['senha'])) {
            $setParts[] = 'senha_hash = ?';
            $params[] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }

        $params[] = $id;

        $sql = 'UPDATE alunos SET ' . implode(', ', $setParts) . ' WHERE id = ?';

        // Garante array 0..n-1 para placeholders ? (evita HY093 com chaves esparsas)
        return $this->db->update($sql, array_values($params));
    }
    
    /**
     * Hard delete desabilitado — exclusão de aluno é sempre soft (ativo=0 / status INACTIVE).
     * Use StudentStatusService::inactivate via StudentAdminController::excluirAluno.
     */
    public function delete($id)
    {
        throw new \RuntimeException(
            'Exclusão física de aluno não é permitida. Use o fluxo de exclusão lógica (ocultar da visualização).'
        );
    }
    
    /**
     * Verifica se aluno existe
     */
    public function exists($id)
    {
        $result = $this->db->fetch("SELECT id FROM alunos WHERE id = :id", ['id' => $id]);
        return $result !== false;
    }
    
    /**
     * Verifica se RA já existe
     */
    public function raExists($ra, $excludeId = null)
    {
        // Dois placeholders distintos: o mesmo nome :ra duas vezes gera HY093 no PDO MySQL
        $sql = "SELECT id FROM alunos WHERE (ra = :ra_val OR codigo_aluno = :codigo_val)";
        $params = ['ra_val' => $ra, 'codigo_val' => $ra];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }
    
    /**
     * Verifica se email já existe
     */
    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM alunos WHERE email = :email";
        $params = ['email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }

    /**
     * Verifica se nickname já existe (para outro aluno)
     */
    public function nicknameExists($nickname, $excludeId = null)
    {
        if ($nickname === null || $nickname === '') {
            return false;
        }
        $sql = "SELECT id FROM alunos WHERE nickname = :nickname";
        $params = ['nickname' => $nickname];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }
    
    /**
     * Alterna status do aluno
     */
    public function toggleStatus($id)
    {
        $aluno = $this->findById($id);
        if (!$aluno) {
            throw new Exception('Aluno não encontrado');
        }
        
        $novoStatus = $aluno['ativo'] ? 0 : 1;
        
        return $this->db->update(
            "UPDATE alunos SET ativo = :ativo WHERE id = :id",
            ['ativo' => $novoStatus, 'id' => $id]
        );
    }
    
    /**
     * Conta total de alunos
     */
    public function count()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM alunos");
        return $result['total'];
    }
    
    /**
     * Conta alunos ativos
     */
    public function countActive()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM alunos WHERE ativo = 1");
        return $result['total'];
    }
    
    /**
     * Busca alunos por turma
     */
    public function getByTurma($turmaId)
    {
        return $this->db->fetchAll(
            "SELECT a.*, t.nome as turma_nome, t.serie as turma_serie
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.turma_id = :turma_id AND a.ativo = 1
             ORDER BY a.nome ASC",
            ['turma_id' => $turmaId]
        );
    }
    
    /**
     * Busca alunos por responsável
     */
    public function getByResponsavel($responsavelId)
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT a.*, t.nome as turma_nome, t.serie as turma_serie
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             LEFT JOIN alunos_responsaveis ar ON ar.aluno_id = a.id AND ar.ativo = 1
             WHERE (a.responsavel_id = :resp_a OR ar.responsavel_id = :resp_ar) AND a.ativo = 1
             ORDER BY a.nome ASC",
            ['resp_a' => $responsavelId, 'resp_ar' => $responsavelId]
        );
    }
    
    /**
     * Autentica aluno
     */
    public function authenticate($ra, $senha)
    {
        $aluno = $this->findByRA($ra);
        
        if ($aluno && password_verify($senha, $aluno['senha_hash'])) {
            return $aluno;
        }
        
        return false;
    }
}
