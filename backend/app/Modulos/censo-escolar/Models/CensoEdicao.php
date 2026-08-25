<?php

namespace App\Modulos\CensoEscolar\Models;

use Database;
use Throwable;

/**
 * Persistência do Censo Escolar. Isolamento por conexão PDO (sem escola_id).
 */
class CensoEdicao
{
    public const STATUS = [
        'rascunho' => 'Rascunho',
        'em_preenchimento' => 'Em preenchimento',
        'em_validacao' => 'Em validação',
        'pronto_para_exportar' => 'Pronto para exportar',
        'arquivo_gerado' => 'Arquivo gerado',
        'enviado_ao_educacenso' => 'Enviado ao Educacenso',
        'com_pendencias_de_retorno' => 'Pendências de retorno',
        'validado_no_educacenso' => 'Validado no Educacenso',
        'fechado' => 'Fechado',
    ];

    public const ETAPAS = [
        'matricula_inicial' => 'Matrícula Inicial',
        'situacao_aluno' => 'Situação do Aluno',
    ];

    public const STATUS_BLOQUEADOS = ['fechado', 'validado_no_educacenso'];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function schemaPronto(): bool
    {
        return $this->tabelaExiste('censo_edicoes');
    }

    public function tabelaExiste(string $tabela): bool
    {
        static $cache = [];
        $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela) ?? '';
        if ($tabela === '') {
            return false;
        }
        $tenantKey = defined('TENANT_ID') ? ('t' . (int) TENANT_ID) : 'no_tenant';
        if (isset($cache[$tenantKey][$tabela])) {
            return $cache[$tenantKey][$tabela];
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :tabela LIMIT 1",
                ['tabela' => $tabela]
            );
            $cache[$tenantKey][$tabela] = !empty($row['ok']);
        } catch (Throwable $e) {
            $cache[$tenantKey][$tabela] = false;
        }
        return $cache[$tenantKey][$tabela];
    }

    public function colunaExiste(string $tabela, string $coluna): bool
    {
        static $cache = [];
        $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela) ?? '';
        $coluna = preg_replace('/[^a-z0-9_]/i', '', $coluna) ?? '';
        if ($tabela === '' || $coluna === '') {
            return false;
        }
        $tenantKey = defined('TENANT_ID') ? ('t' . (int) TENANT_ID) : 'no_tenant';
        $key = $tabela . '.' . $coluna;
        if (isset($cache[$tenantKey][$key])) {
            return $cache[$tenantKey][$key];
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :tabela AND column_name = :coluna LIMIT 1",
                ['tabela' => $tabela, 'coluna' => $coluna]
            );
            $cache[$tenantKey][$key] = !empty($row['ok']);
        } catch (Throwable $e) {
            $cache[$tenantKey][$key] = false;
        }
        return $cache[$tenantKey][$key];
    }

    public function findById(int $id): ?array
    {
        if (!$this->schemaPronto() || $id <= 0) {
            return null;
        }
        if ($this->tabelaExiste('unidades')) {
            $row = $this->db->fetch(
                "SELECT e.*, u.nome AS unidade_nome, u.inep AS unidade_inep
                 FROM censo_edicoes e
                 LEFT JOIN unidades u ON u.id = e.unidade_id
                 WHERE e.id = :id LIMIT 1",
                ['id' => $id]
            );
        } else {
            $row = $this->db->fetch(
                "SELECT e.*, NULL AS unidade_nome, NULL AS unidade_inep
                 FROM censo_edicoes e WHERE e.id = :id LIMIT 1",
                ['id' => $id]
            );
        }
        return is_array($row) ? $row : null;
    }

    public function findByContexto(int $unidadeId, int $ano, string $etapa): ?array
    {
        if (!$this->schemaPronto()) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM censo_edicoes
             WHERE unidade_id = :unidade_id AND ano = :ano AND etapa_coleta = :etapa
             LIMIT 1",
            ['unidade_id' => $unidadeId, 'ano' => $ano, 'etapa' => $etapa]
        );
        return is_array($row) ? $row : null;
    }

    public function listar(int $limite = 30): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        if ($this->tabelaExiste('unidades')) {
            return $this->db->fetchAll(
                "SELECT e.*, u.nome AS unidade_nome
                 FROM censo_edicoes e
                 LEFT JOIN unidades u ON u.id = e.unidade_id
                 ORDER BY e.ano DESC, e.atualizado_em DESC
                 LIMIT " . max(1, min(100, $limite))
            ) ?: [];
        }
        return $this->db->fetchAll(
            "SELECT e.*, NULL AS unidade_nome FROM censo_edicoes e
             ORDER BY e.ano DESC, e.atualizado_em DESC
             LIMIT " . max(1, min(100, $limite))
        ) ?: [];
    }

    public function criar(array $dados): int
    {
        return (int) $this->db->insert(
            "INSERT INTO censo_edicoes
                (unidade_id, ano, etapa_coleta, data_referencia, versao_layout, layout_id, status, responsavel_id)
             VALUES
                (:unidade_id, :ano, :etapa_coleta, :data_referencia, :versao_layout, :layout_id, :status, :responsavel_id)",
            [
                'unidade_id' => (int) ($dados['unidade_id'] ?? 0),
                'ano' => (int) ($dados['ano'] ?? date('Y')),
                'etapa_coleta' => (string) ($dados['etapa_coleta'] ?? 'matricula_inicial'),
                'data_referencia' => $dados['data_referencia'] ?? null,
                'versao_layout' => $dados['versao_layout'] ?? null,
                'layout_id' => isset($dados['layout_id']) && (int) $dados['layout_id'] > 0 ? (int) $dados['layout_id'] : null,
                'status' => $dados['status'] ?? 'rascunho',
                'responsavel_id' => isset($dados['responsavel_id']) ? (int) $dados['responsavel_id'] : null,
            ]
        );
    }

    public function atualizar(int $id, array $dados): void
    {
        $campos = [];
        $params = ['id' => $id];
        $permitidos = [
            'unidade_id', 'ano', 'etapa_coleta', 'data_referencia', 'versao_layout',
            'layout_id', 'status', 'responsavel_id', 'ultima_validacao_em', 'ultima_validacao_por',
            'fechado_em', 'fechado_por', 'reaberto_em', 'reaberto_por', 'motivo_reabertura',
        ];
        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $dados)) {
                continue;
            }
            $campos[] = "{$campo} = :{$campo}";
            $params[$campo] = $dados[$campo];
        }
        if ($campos === []) {
            return;
        }
        $this->db->query(
            'UPDATE censo_edicoes SET ' . implode(', ', $campos) . ' WHERE id = :id',
            $params
        );
    }

    public function unidadesAtivas(): array
    {
        if (!$this->tabelaExiste('unidades')) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT id, nome, inep, dependencia_administrativa, cnpj, endereco, numero, complemento,
                    bairro, cidade, uf, cep, telefone, email, diretor_nome, secretario_nome, tipo
             FROM unidades WHERE ativo = 1 ORDER BY tipo = 'matriz' DESC, nome ASC"
        ) ?: [];
    }

    public function unidadePorId(int $id): ?array
    {
        if (!$this->tabelaExiste('unidades') || $id <= 0) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM unidades WHERE id = :id LIMIT 1", ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    public function anosLetivosColeta(): array
    {
        $anos = [];
        $atual = (int) date('Y');
        for ($ano = $atual - 4; $ano <= $atual + 1; $ano++) {
            $anos[$ano] = $ano;
        }
        foreach ($this->anosDistintosTabela('turmas', 'ano_letivo') as $ano) {
            $anos[$ano] = $ano;
        }
        foreach ($this->anosDistintosTabela('ano_letivo', 'ano') as $ano) {
            $anos[$ano] = $ano;
        }
        foreach ($this->anosDistintosTabela('censo_edicoes', 'ano') as $ano) {
            $anos[$ano] = $ano;
        }
        $lista = array_values($anos);
        rsort($lista, SORT_NUMERIC);
        return $lista;
    }

    public function turmasDoAno(int $ano, int $unidadeId = 0): array
    {
        if (!$this->tabelaExiste('turmas')) {
            return [];
        }
        $params = ['ano' => $ano];
        $whereUnidade = '';
        if ($unidadeId > 0 && $this->colunaExiste('alunos', 'unidade_id')) {
            $whereUnidade = " AND EXISTS (
                SELECT 1 FROM alunos a
                WHERE a.turma_id = t.id AND a.unidade_id = :unidade_id
            )";
            $params['unidade_id'] = $unidadeId;
        }
        $filtroAtivo = $this->somenteAtivosDoAno($ano) ? 't.ativo = 1 AND ' : '';
        return $this->db->fetchAll(
            "SELECT t.* FROM turmas t
             WHERE {$filtroAtivo}t.ano_letivo = :ano {$whereUnidade}
             ORDER BY t.serie ASC, t.nome ASC",
            $params
        ) ?: [];
    }

    public function alunosDaEdicao(int $ano, int $unidadeId = 0): array
    {
        if (!$this->tabelaExiste('alunos')) {
            return [];
        }
        $cols = $this->colunasSelectAluno();
        $params = ['ano' => $ano];
        $where = 't.ano_letivo = :ano';
        if ($this->somenteAtivosDoAno($ano)) {
            $where = 'a.ativo = 1 AND t.ano_letivo = :ano';
        }
        if ($unidadeId > 0 && $this->colunaExiste('alunos', 'unidade_id')) {
            $where .= ' AND a.unidade_id = :unidade_id';
            $params['unidade_id'] = $unidadeId;
        }
        return $this->db->fetchAll(
            "SELECT {$cols}, t.nome AS turma_nome, t.serie AS turma_serie
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE {$where}
             ORDER BY a.nome ASC",
            $params
        ) ?: [];
    }

    public function profissionaisDaEdicao(int $ano, int $unidadeId = 0): array
    {
        if (!$this->tabelaExiste('professores') || !$this->tabelaExiste('grade_horaria')) {
            return [];
        }
        $params = ['ano' => $ano];
        $filtroTurma = 't.ano_letivo = :ano';
        if ($this->somenteAtivosDoAno($ano)) {
            $filtroTurma .= ' AND t.ativo = 1';
        }
        if ($unidadeId > 0 && $this->colunaExiste('alunos', 'unidade_id')) {
            $filtroTurma .= " AND EXISTS (
                SELECT 1 FROM alunos a WHERE a.turma_id = t.id AND a.unidade_id = :unidade_id
            )";
            $params['unidade_id'] = $unidadeId;
        }
        $cols = $this->colunasSelectProfessor();
        return $this->db->fetchAll(
            "SELECT {$cols}
             FROM professores p
             WHERE p.ativo = 1 AND p.id IN (
                SELECT g.professor_id
                FROM grade_horaria g
                INNER JOIN turmas t ON t.id = g.turma_id
                WHERE {$filtroTurma}
             )
             ORDER BY p.nome ASC",
            $params
        ) ?: [];
    }

    public function vinculosGrade(int $ano, int $unidadeId = 0): array
    {
        if (!$this->tabelaExiste('grade_horaria')) {
            return [];
        }
        $params = ['ano' => $ano];
        $filtro = 't.ano_letivo = :ano';
        if ($this->somenteAtivosDoAno($ano)) {
            $filtro .= ' AND t.ativo = 1';
        }
        if ($unidadeId > 0 && $this->colunaExiste('alunos', 'unidade_id')) {
            $filtro .= " AND EXISTS (
                SELECT 1 FROM alunos a WHERE a.turma_id = t.id AND a.unidade_id = :unidade_id
            )";
            $params['unidade_id'] = $unidadeId;
        }
        $selMateria = $this->tabelaExiste('materias') ? 'm.nome AS materia_nome, m.codigo AS materia_codigo' : "'' AS materia_nome, '' AS materia_codigo";
        $joinMateria = $this->tabelaExiste('materias') ? 'LEFT JOIN materias m ON m.id = g.materia_id' : '';
        return $this->db->fetchAll(
            "SELECT DISTINCT g.professor_id, g.turma_id, g.materia_id, p.nome AS professor_nome,
                    t.nome AS turma_nome, {$selMateria}
             FROM grade_horaria g
             INNER JOIN professores p ON p.id = g.professor_id
             INNER JOIN turmas t ON t.id = g.turma_id
             {$joinMateria}
             WHERE {$filtro}
             ORDER BY p.nome, t.nome",
            $params
        ) ?: [];
    }

    public function buscarPorChaves(string $tabela, array $unico): ?array
    {
        $permitidas = [
            'censo_complementos_escola', 'censo_complementos_gestor', 'censo_complementos_turma',
            'censo_complementos_aluno', 'censo_complementos_profissional', 'censo_matriculas',
            'censo_vinculos_profissionais', 'censo_situacoes_aluno',
        ];
        if (!in_array($tabela, $permitidas, true)) {
            return null;
        }
        $where = [];
        $params = [];
        foreach ($unico as $col => $val) {
            $col = preg_replace('/[^a-z0-9_]/i', '', (string) $col) ?? '';
            if ($col === '') {
                continue;
            }
            $where[] = "{$col} = :u_{$col}";
            $params['u_' . $col] = $val;
        }
        if ($where === []) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM {$tabela} WHERE " . implode(' AND ', $where) . ' LIMIT 1',
            $params
        );
        return is_array($row) ? $row : null;
    }

    public function upsertComplemento(string $tabela, array $unico, array $dados): int
    {
        $permitidas = [
            'censo_complementos_escola', 'censo_complementos_gestor', 'censo_complementos_turma',
            'censo_complementos_aluno', 'censo_complementos_profissional', 'censo_matriculas',
            'censo_vinculos_profissionais', 'censo_situacoes_aluno',
        ];
        if (!in_array($tabela, $permitidas, true)) {
            return 0;
        }
        $where = [];
        $params = [];
        foreach ($unico as $col => $val) {
            $col = preg_replace('/[^a-z0-9_]/i', '', (string) $col) ?? '';
            if ($col === '') {
                continue;
            }
            $where[] = "{$col} = :u_{$col}";
            $params['u_' . $col] = $val;
        }
        if ($where === []) {
            return 0;
        }
        $existente = $this->db->fetch(
            "SELECT id FROM {$tabela} WHERE " . implode(' AND ', $where) . ' LIMIT 1',
            $params
        );
        if ($existente) {
            $sets = [];
            $upd = ['id' => (int) $existente['id']];
            foreach ($dados as $col => $val) {
                $col = preg_replace('/[^a-z0-9_]/i', '', (string) $col) ?? '';
                if ($col === '' || $col === 'id') {
                    continue;
                }
                $sets[] = "{$col} = :{$col}";
                $upd[$col] = $val;
            }
            if ($sets !== []) {
                $this->db->query(
                    "UPDATE {$tabela} SET " . implode(', ', $sets) . ' WHERE id = :id',
                    $upd
                );
            }
            return (int) $existente['id'];
        }
        $cols = [];
        $ph = [];
        $ins = [];
        foreach (array_merge($unico, $dados) as $col => $val) {
            $col = preg_replace('/[^a-z0-9_]/i', '', (string) $col) ?? '';
            if ($col === '') {
                continue;
            }
            $cols[] = $col;
            $ph[] = ':' . $col;
            $ins[$col] = $val;
        }
        return (int) $this->db->insert(
            "INSERT INTO {$tabela} (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')',
            $ins
        );
    }

    public function listarEntidade(string $tabela, int $edicaoId, array $filtros = []): array
    {
        $map = [
            'escola' => 'censo_complementos_escola',
            'gestores' => 'censo_complementos_gestor',
            'turmas' => 'censo_complementos_turma',
            'alunos' => 'censo_complementos_aluno',
            'profissionais' => 'censo_complementos_profissional',
            'matriculas' => 'censo_matriculas',
            'vinculos' => 'censo_vinculos_profissionais',
        ];
        $tabelaReal = $map[$tabela] ?? '';
        if ($tabelaReal === '' || !$this->tabelaExiste($tabelaReal)) {
            return [];
        }
        $sql = $this->sqlListagem($tabela, $tabelaReal);
        $params = ['edicao_id' => $edicaoId];
        $where = ['c.edicao_id = :edicao_id'];
        $busca = trim((string) ($filtros['q'] ?? ''));
        if ($busca !== '') {
            $params['q'] = '%' . $busca . '%';
            if ($tabela === 'alunos') {
                $buscaNome = '(a.nome LIKE :q OR t.nome LIKE :q)';
                if ($this->colunaExiste('alunos', 'cpf')) {
                    $buscaNome = '(a.nome LIKE :q OR a.cpf LIKE :q OR t.nome LIKE :q)';
                }
                if ($this->colunaExiste('alunos', 'codigo_inep')) {
                    $buscaNome = str_replace('t.nome LIKE :q)', 'a.codigo_inep LIKE :q OR t.nome LIKE :q)', $buscaNome);
                }
                $where[] = $buscaNome;
            } elseif ($tabela === 'profissionais' || $tabela === 'gestores') {
                $aliasNome = $tabela === 'gestores' ? 'c.nome' : 'p.nome';
                $where[] = "({$aliasNome} LIKE :q OR c.codigo_inep LIKE :q OR c.cpf LIKE :q)";
            } elseif ($tabela === 'turmas') {
                $where[] = '(t.nome LIKE :q OR c.codigo_inep LIKE :q OR c.etapa_codigo LIKE :q)';
            } elseif ($tabela === 'matriculas') {
                $where[] = '(a.nome LIKE :q OR t.nome LIKE :q)';
            }
        }
        $status = trim((string) ($filtros['status'] ?? ''));
        if ($status === 'pendentes') {
            $where[] = "c.status_validacao IN ('pendente','incompleto','com_erro','com_alerta')";
        } elseif ($status !== '' && $status !== 'todos') {
            $where[] = 'c.status_validacao = :status_filtro';
            $params['status_filtro'] = $status;
        }
        if (isset($filtros['incluir_exportacao']) && $filtros['incluir_exportacao'] !== '') {
            $where[] = 'c.incluir_exportacao = :incluir';
            $params['incluir'] = (int) $filtros['incluir_exportacao'];
        }
        $sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY 2 ASC';
        $limite = array_key_exists('limite', $filtros) ? (int) $filtros['limite'] : 2000;
        if ($limite > 0) {
            $sql .= ' LIMIT ' . $limite;
        }
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function findComplemento(string $entidade, int $edicaoId, int $id): ?array
    {
        $map = [
            'escola' => 'censo_complementos_escola',
            'gestor' => 'censo_complementos_gestor',
            'turma' => 'censo_complementos_turma',
            'aluno' => 'censo_complementos_aluno',
            'profissional' => 'censo_complementos_profissional',
            'matricula' => 'censo_matriculas',
            'vinculo' => 'censo_vinculos_profissionais',
            'situacao' => 'censo_situacoes_aluno',
        ];
        $tabela = $map[$entidade] ?? '';
        if ($tabela === '') {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM {$tabela} WHERE id = :id AND edicao_id = :edicao_id LIMIT 1",
            ['id' => $id, 'edicao_id' => $edicaoId]
        );
        return is_array($row) ? $row : null;
    }

    public function substituirValidacoes(int $edicaoId, array $itens): void
    {
        $resolvidas = $this->db->fetchAll(
            "SELECT entidade_tipo, entidade_id, regra_codigo
             FROM censo_validacoes
             WHERE edicao_id = :id AND status IN ('conferida','justificada')",
            ['id' => $edicaoId]
        ) ?: [];
        $chavesResolvidas = [];
        foreach ($resolvidas as $row) {
            $chavesResolvidas[
                (string) ($row['entidade_tipo'] ?? '') . '|' . (int) ($row['entidade_id'] ?? 0) . '|' . (string) ($row['regra_codigo'] ?? '')
            ] = true;
        }
        $this->db->query(
            "DELETE FROM censo_validacoes WHERE edicao_id = :id AND status = 'aberta'",
            ['id' => $edicaoId]
        );
        foreach ($itens as $item) {
            $chave = (string) ($item['entidade_tipo'] ?? '') . '|' . (int) ($item['entidade_id'] ?? 0) . '|' . (string) ($item['regra_codigo'] ?? '');
            if (isset($chavesResolvidas[$chave])) {
                continue;
            }
            $this->db->insert(
                "INSERT INTO censo_validacoes
                    (edicao_id, entidade_tipo, entidade_id, regra_codigo, severidade, mensagem, orientacao, campo, status)
                 VALUES
                    (:edicao_id, :entidade_tipo, :entidade_id, :regra_codigo, :severidade, :mensagem, :orientacao, :campo, 'aberta')",
                [
                    'edicao_id' => $edicaoId,
                    'entidade_tipo' => (string) ($item['entidade_tipo'] ?? ''),
                    'entidade_id' => (int) ($item['entidade_id'] ?? 0),
                    'regra_codigo' => (string) ($item['regra_codigo'] ?? ''),
                    'severidade' => (string) ($item['severidade'] ?? 'alerta'),
                    'mensagem' => (string) ($item['mensagem'] ?? ''),
                    'orientacao' => $item['orientacao'] ?? null,
                    'campo' => $item['campo'] ?? null,
                ]
            );
        }
    }

    public function pendencias(int $edicaoId, array $filtros = []): array
    {
        $params = ['edicao_id' => $edicaoId];
        $where = ['v.edicao_id = :edicao_id'];
        if (!empty($filtros['severidade'])) {
            $where[] = 'v.severidade = :severidade';
            $params['severidade'] = $filtros['severidade'];
        }
        if (!empty($filtros['status'])) {
            $where[] = 'v.status = :status';
            $params['status'] = $filtros['status'];
        } else {
            $where[] = "v.status IN ('aberta','justificada','conferida')";
        }
        return $this->db->fetchAll(
            "SELECT v.* FROM censo_validacoes v
             WHERE " . implode(' AND ', $where) . "
             ORDER BY FIELD(v.severidade,'erro','divergencia','alerta'), v.id ASC
             LIMIT 3000",
            $params
        ) ?: [];
    }

    public function resumoValidacao(int $edicaoId): array
    {
        $row = $this->db->fetch(
            "SELECT
                SUM(CASE WHEN severidade = 'erro' AND status = 'aberta' THEN 1 ELSE 0 END) AS erros,
                SUM(CASE WHEN severidade = 'alerta' AND status = 'aberta' THEN 1 ELSE 0 END) AS alertas,
                SUM(CASE WHEN severidade = 'divergencia' AND status = 'aberta' THEN 1 ELSE 0 END) AS divergencias,
                SUM(CASE WHEN status = 'conferida' THEN 1 ELSE 0 END) AS conferidas,
                SUM(CASE WHEN status = 'justificada' THEN 1 ELSE 0 END) AS justificadas,
                COUNT(*) AS total
             FROM censo_validacoes WHERE edicao_id = :id",
            ['id' => $edicaoId]
        );
        return [
            'erros' => (int) ($row['erros'] ?? 0),
            'alertas' => (int) ($row['alertas'] ?? 0),
            'divergencias' => (int) ($row['divergencias'] ?? 0),
            'conferidas' => (int) ($row['conferidas'] ?? 0),
            'justificadas' => (int) ($row['justificadas'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function findValidacao(int $id, int $edicaoId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM censo_validacoes WHERE id = :id AND edicao_id = :edicao_id LIMIT 1',
            ['id' => $id, 'edicao_id' => $edicaoId]
        );
        return is_array($row) ? $row : null;
    }

    public function unidadeIdDaTurma(int $turmaId): int
    {
        if ($turmaId <= 0 || !$this->colunaExiste('alunos', 'unidade_id')) {
            return 0;
        }
        $row = $this->db->fetch(
            'SELECT unidade_id FROM alunos
             WHERE turma_id = :tid AND unidade_id IS NOT NULL AND unidade_id > 0
             ORDER BY id ASC LIMIT 1',
            ['tid' => $turmaId]
        );
        return (int) ($row['unidade_id'] ?? 0);
    }

    public function atualizarValidacao(int $id, int $edicaoId, array $dados): void
    {
        $this->db->query(
            "UPDATE censo_validacoes
             SET status = :status, justificativa = :justificativa,
                 resolvido_por = :resolvido_por, resolvido_em = :resolvido_em
             WHERE id = :id AND edicao_id = :edicao_id",
            [
                'status' => $dados['status'] ?? 'aberta',
                'justificativa' => $dados['justificativa'] ?? null,
                'resolvido_por' => $dados['resolvido_por'] ?? null,
                'resolvido_em' => $dados['resolvido_em'] ?? null,
                'id' => $id,
                'edicao_id' => $edicaoId,
            ]
        );
    }

    public function contarCategoria(string $tabela, int $edicaoId): array
    {
        $permitidas = [
            'censo_complementos_escola', 'censo_complementos_gestor', 'censo_complementos_turma',
            'censo_complementos_aluno', 'censo_complementos_profissional', 'censo_matriculas',
            'censo_vinculos_profissionais', 'censo_situacoes_aluno',
        ];
        if (!in_array($tabela, $permitidas, true) || !$this->tabelaExiste($tabela)) {
            return ['total' => 0, 'prontos' => 0, 'erros' => 0, 'incompletos' => 0];
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status_validacao = 'pronto' THEN 1 ELSE 0 END) AS prontos,
                    SUM(CASE WHEN status_validacao = 'com_erro' THEN 1 ELSE 0 END) AS erros,
                    SUM(CASE WHEN status_validacao IN ('pendente','incompleto','com_alerta') THEN 1 ELSE 0 END) AS incompletos
             FROM {$tabela} WHERE edicao_id = :id",
            ['id' => $edicaoId]
        );
        return [
            'total' => (int) ($row['total'] ?? 0),
            'prontos' => (int) ($row['prontos'] ?? 0),
            'erros' => (int) ($row['erros'] ?? 0),
            'incompletos' => (int) ($row['incompletos'] ?? 0),
        ];
    }

    public function proximaVersaoExportacao(int $edicaoId): int
    {
        $row = $this->db->fetch(
            "SELECT MAX(versao) AS v FROM censo_exportacoes WHERE edicao_id = :id",
            ['id' => $edicaoId]
        );
        return ((int) ($row['v'] ?? 0)) + 1;
    }

    public function proximaVersaoSnapshot(int $edicaoId): int
    {
        $row = $this->db->fetch(
            "SELECT MAX(versao) AS v FROM censo_snapshots WHERE edicao_id = :id",
            ['id' => $edicaoId]
        );
        return ((int) ($row['v'] ?? 0)) + 1;
    }

    public function salvarSnapshot(array $dados): int
    {
        return (int) $this->db->insert(
            "INSERT INTO censo_snapshots (edicao_id, versao, dados_json, hash, criado_por)
             VALUES (:edicao_id, :versao, :dados_json, :hash, :criado_por)",
            $dados
        );
    }

    public function salvarExportacao(array $dados): int
    {
        return (int) $this->db->insert(
            "INSERT INTO censo_exportacoes
                (edicao_id, snapshot_id, layout_id, versao, tipo, arquivo, nome_original, hash_sha256,
                 tamanho_bytes, total_linhas, resumo_json, status, gerado_por)
             VALUES
                (:edicao_id, :snapshot_id, :layout_id, :versao, :tipo, :arquivo, :nome_original, :hash_sha256,
                 :tamanho_bytes, :total_linhas, :resumo_json, :status, :gerado_por)",
            $dados
        );
    }

    public function exportacoes(int $edicaoId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM censo_exportacoes WHERE edicao_id = :id ORDER BY versao DESC",
            ['id' => $edicaoId]
        ) ?: [];
    }

    public function findExportacao(int $id, int $edicaoId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM censo_exportacoes WHERE id = :id AND edicao_id = :edicao_id LIMIT 1",
            ['id' => $id, 'edicao_id' => $edicaoId]
        );
        return is_array($row) ? $row : null;
    }

    public function salvarRetorno(array $dados): int
    {
        return (int) $this->db->insert(
            "INSERT INTO censo_retornos
                (edicao_id, exportacao_id, arquivo, nome_original, tipo, hash_sha256, resumo_json, importado_por)
             VALUES
                (:edicao_id, :exportacao_id, :arquivo, :nome_original, :tipo, :hash_sha256, :resumo_json, :importado_por)",
            $dados
        );
    }

    public function retornos(int $edicaoId): array
    {
        return $this->db->fetchAll(
            "SELECT r.*, e.versao AS exportacao_versao, e.nome_original AS exportacao_nome
             FROM censo_retornos r
             LEFT JOIN censo_exportacoes e ON e.id = r.exportacao_id
             WHERE r.edicao_id = :id
             ORDER BY r.importado_em DESC",
            ['id' => $edicaoId]
        ) ?: [];
    }

    public function findRetorno(int $id, int $edicaoId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM censo_retornos WHERE id = :id AND edicao_id = :edicao_id LIMIT 1",
            ['id' => $id, 'edicao_id' => $edicaoId]
        );
        return is_array($row) ? $row : null;
    }

    public function marcarRetornoAplicado(int $id): void
    {
        $this->db->query(
            "UPDATE censo_retornos SET aplicado = 1 WHERE id = :id",
            ['id' => $id]
        );
    }

    public function registrarAuditoria(array $dados): void
    {
        $this->db->insert(
            "INSERT INTO censo_auditoria
                (edicao_id, usuario_id, acao, entidade_tipo, entidade_id, dados_anteriores_json, dados_novos_json, ip)
             VALUES
                (:edicao_id, :usuario_id, :acao, :entidade_tipo, :entidade_id, :dados_anteriores_json, :dados_novos_json, :ip)",
            [
                'edicao_id' => (int) $dados['edicao_id'],
                'usuario_id' => $dados['usuario_id'] ?? null,
                'acao' => (string) $dados['acao'],
                'entidade_tipo' => $dados['entidade_tipo'] ?? null,
                'entidade_id' => $dados['entidade_id'] ?? null,
                'dados_anteriores_json' => $dados['dados_anteriores_json'] ?? null,
                'dados_novos_json' => $dados['dados_novos_json'] ?? null,
                'ip' => $dados['ip'] ?? null,
            ]
        );
    }

    public function situacoes(int $edicaoId): array
    {
        $joinResultado = '';
        $colsResultado = "NULL AS resultado_situacao, NULL AS resultado_academico, NULL AS resultado_status";
        if ($this->tabelaExiste('resultado_academico')) {
            $colsResultado = "r.situacao AS resultado_situacao, r.rotulo AS resultado_academico, r.status AS resultado_status";
            $joinResultado = "LEFT JOIN censo_edicoes e ON e.id = s.edicao_id
             LEFT JOIN resultado_academico r ON r.aluno_id = s.aluno_id
                AND r.turma_id = m.turma_id
                AND r.ano_letivo = e.ano
                AND r.periodo_tipo = 'ano'";
        }
        return $this->db->fetchAll(
            "SELECT s.*, a.nome AS aluno_nome, t.nome AS turma_nome, m.status_validacao AS matricula_status,
                    {$colsResultado}
             FROM censo_situacoes_aluno s
             INNER JOIN censo_matriculas m ON m.id = s.censo_matricula_id
             INNER JOIN alunos a ON a.id = s.aluno_id
             LEFT JOIN turmas t ON t.id = m.turma_id
             {$joinResultado}
             WHERE s.edicao_id = :id
             ORDER BY a.nome ASC, t.nome ASC",
            ['id' => $edicaoId]
        ) ?: [];
    }

    public function situacaoPorMatricula(int $edicaoId, int $matriculaId): ?array
    {
        $row = $this->db->fetch(
            "SELECT id FROM censo_situacoes_aluno
             WHERE edicao_id = :edicao_id AND censo_matricula_id = :matricula_id LIMIT 1",
            ['edicao_id' => $edicaoId, 'matricula_id' => $matriculaId]
        );
        return is_array($row) ? $row : null;
    }

    public function vinculosDoProfessor(int $edicaoId, int $professorId): array
    {
        if (!$this->tabelaExiste('censo_vinculos_profissionais') || $professorId <= 0) {
            return [];
        }
        $selMateria = $this->tabelaExiste('materias') ? 'm.nome AS materia_nome' : "'' AS materia_nome";
        $joinMateria = $this->tabelaExiste('materias') ? 'LEFT JOIN materias m ON m.id = v.materia_id' : '';
        return $this->db->fetchAll(
            "SELECT v.*, t.nome AS turma_nome, {$selMateria}
             FROM censo_vinculos_profissionais v
             LEFT JOIN turmas t ON t.id = v.turma_id
             {$joinMateria}
             WHERE v.edicao_id = :edicao_id AND v.professor_id = :professor_id
             ORDER BY t.nome ASC, v.id ASC",
            ['edicao_id' => $edicaoId, 'professor_id' => $professorId]
        ) ?: [];
    }

    public function alunoPorId(int $id): ?array
    {
        $row = $this->db->fetch("SELECT * FROM alunos WHERE id = :id LIMIT 1", ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    public function professorPorId(int $id): ?array
    {
        $row = $this->db->fetch("SELECT * FROM professores WHERE id = :id LIMIT 1", ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    public function turmaPorId(int $id): ?array
    {
        $row = $this->db->fetch("SELECT * FROM turmas WHERE id = :id LIMIT 1", ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    public function atualizarNomeProfessor(int $id, string $nome): void
    {
        $id = (int) $id;
        $nome = trim($nome);
        if ($id <= 0 || $nome === '') {
            return;
        }
        $this->db->query(
            'UPDATE professores SET nome = :nome WHERE id = :id',
            ['nome' => $nome, 'id' => $id]
        );
    }

    public function atualizarAluno(int $id, array $dados): void
    {
        $permitidos = [
            'codigo_inep', 'nome_mae', 'nome_pai', 'cpf', 'data_nasc', 'sexo', 'cor_raca',
            'nacionalidade', 'naturalidade', 'uf_nascimento', 'nome_social',
        ];
        $sets = [];
        $params = ['id' => $id];
        foreach ($permitidos as $col) {
            if (!array_key_exists($col, $dados) || !$this->colunaExiste('alunos', $col)) {
                continue;
            }
            $sets[] = "{$col} = :{$col}";
            $params[$col] = $dados[$col];
        }
        if ($sets === []) {
            return;
        }
        $this->db->query('UPDATE alunos SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    public function atualizarUnidade(int $id, array $dados): void
    {
        $permitidos = ['inep', 'dependencia_administrativa', 'cnpj', 'endereco', 'numero', 'bairro', 'cidade', 'uf', 'cep', 'telefone', 'email', 'diretor_nome', 'secretario_nome'];
        $sets = [];
        $params = ['id' => $id];
        foreach ($permitidos as $col) {
            if (!array_key_exists($col, $dados) || !$this->colunaExiste('unidades', $col)) {
                continue;
            }
            $sets[] = "{$col} = :{$col}";
            $params[$col] = $dados[$col];
        }
        if ($sets === []) {
            return;
        }
        $this->db->query('UPDATE unidades SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    public function begin(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollback();
        }
    }

    private function somenteAtivosDoAno(int $ano): bool
    {
        return $ano >= (int) date('Y');
    }

    /**
     * @return list<int>
     */
    private function anosDistintosTabela(string $tabela, string $coluna): array
    {
        if (!$this->tabelaExiste($tabela) || !$this->colunaExiste($tabela, $coluna)) {
            return [];
        }
        $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela) ?? '';
        $coluna = preg_replace('/[^a-z0-9_]/i', '', $coluna) ?? '';
        if ($tabela === '' || $coluna === '') {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT {$coluna} AS ano FROM {$tabela} WHERE {$coluna} IS NOT NULL"
        ) ?: [];
        $anos = [];
        foreach ($rows as $row) {
            $ano = (int) ($row['ano'] ?? 0);
            if ($ano >= 2000 && $ano <= 2100) {
                $anos[] = $ano;
            }
        }
        return $anos;
    }

    private function colunasSelectProfessor(): string
    {
        $base = ['id', 'nome', 'email', 'codigo_prof'];
        $opcionais = ['cpf', 'codigo_inep', 'escolaridade', 'formacao'];
        $cols = [];
        foreach ($base as $c) {
            $cols[] = 'p.' . $c;
        }
        foreach ($opcionais as $c) {
            $cols[] = $this->colunaExiste('professores', $c) ? 'p.' . $c : "NULL AS {$c}";
        }
        return implode(', ', $cols);
    }

    private function colunasSelectAluno(): string
    {
        $base = ['id', 'nome', 'turma_id', 'ativo'];
        $opcionais = [
            'cpf', 'data_nasc', 'sexo', 'nome_mae', 'nome_pai', 'codigo_inep', 'cor_raca',
            'nacionalidade', 'naturalidade', 'uf_nascimento', 'nome_social', 'unidade_id',
            'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'cep', 'zona',
        ];
        $cols = [];
        foreach ($base as $c) {
            $cols[] = 'a.' . $c;
        }
        foreach ($opcionais as $c) {
            $cols[] = $this->colunaExiste('alunos', $c) ? 'a.' . $c : "NULL AS {$c}";
        }
        return implode(', ', $cols);
    }

    private function sqlListagem(string $entidade, string $tabela): string
    {
        switch ($entidade) {
            case 'escola':
                return "SELECT c.*, u.nome AS nome, u.inep AS codigo_inep
                        FROM {$tabela} c
                        LEFT JOIN unidades u ON u.id = c.unidade_id";
            case 'gestores':
                return "SELECT c.*, c.nome AS nome, c.codigo_inep AS codigo_inep
                        FROM {$tabela} c";
            case 'turmas':
                return "SELECT c.*, t.nome AS nome, t.serie, c.codigo_inep
                        FROM {$tabela} c
                        LEFT JOIN turmas t ON t.id = c.turma_id";
            case 'alunos':
                $inep = $this->colunaExiste('alunos', 'codigo_inep') ? 'a.codigo_inep' : 'NULL AS codigo_inep';
                $cpf = $this->colunaExiste('alunos', 'cpf') ? 'a.cpf' : 'NULL AS cpf';
                return "SELECT c.*, a.nome AS nome, {$inep}, {$cpf}, a.turma_id, t.nome AS turma_nome
                        FROM {$tabela} c
                        INNER JOIN alunos a ON a.id = c.aluno_id
                        LEFT JOIN turmas t ON t.id = a.turma_id";
            case 'profissionais':
                return "SELECT c.*, p.nome AS nome, c.codigo_inep,
                        (
                            SELECT GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR ', ')
                            FROM censo_vinculos_profissionais v
                            LEFT JOIN turmas t ON t.id = v.turma_id
                            WHERE v.edicao_id = c.edicao_id AND v.professor_id = c.professor_id
                        ) AS turma_nome
                        FROM {$tabela} c
                        INNER JOIN professores p ON p.id = c.professor_id";
            case 'matriculas':
                $inepMat = $this->colunaExiste('alunos', 'codigo_inep') ? 'a.codigo_inep' : 'NULL AS codigo_inep';
                return "SELECT c.*, a.nome AS nome, t.nome AS turma_nome, {$inepMat}
                        FROM {$tabela} c
                        INNER JOIN alunos a ON a.id = c.aluno_id
                        LEFT JOIN turmas t ON t.id = c.turma_id";
            case 'vinculos':
                $selMateria = "'' AS materia_nome, '' AS materia_codigo";
                $joinMateria = '';
                if ($this->tabelaExiste('materias')) {
                    $codMat = $this->colunaExiste('materias', 'codigo') ? 'mat.codigo' : "''";
                    $selMateria = "mat.nome AS materia_nome, {$codMat} AS materia_codigo";
                    $joinMateria = 'LEFT JOIN materias mat ON mat.id = c.materia_id';
                }
                return "SELECT c.*, p.nome AS nome, t.nome AS turma_nome, {$selMateria}
                        FROM {$tabela} c
                        INNER JOIN professores p ON p.id = c.professor_id
                        LEFT JOIN turmas t ON t.id = c.turma_id
                        {$joinMateria}";
            default:
                return "SELECT c.* FROM {$tabela} c";
        }
    }
}
