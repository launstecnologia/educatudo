<?php
/**
 * EducaTudo - Modelo de Componentes Curriculares
 * Gerencia operações de banco de dados para componentes curriculares.
 *
 * Nota: a tabela física continua se chamando `materias` — é usada como FK em
 * várias tabelas core (provas, jornadas, grade_horaria, planos_aula, diário,
 * ava_disciplinas, mural_recados etc.). O rename para "Componentes
 * Curriculares" ficou só na camada de aplicação (ver .claude/docs/nomenclatura.md).
 */

class ComponenteCurricular
{
    private $db;
    /** @var array<int, list<array{id:int,nome:string,codigo:string}>>|null */
    private ?array $mapaFilhosPorPaiCache = null;

    /** Catálogo fixo de áreas do conhecimento (valor salvo => rótulo exibido) */
    public const AREAS_CONHECIMENTO = [
        'linguagens' => 'Linguagens e suas Tecnologias',
        'matematica' => 'Matemática e suas Tecnologias',
        'ciencias_natureza' => 'Ciências da Natureza e suas Tecnologias',
        'ciencias_humanas' => 'Ciências Humanas e Sociais Aplicadas',
        'ensino_religioso' => 'Ensino Religioso',
        'interdisciplinar' => 'Interdisciplinar',
        'tecnologia' => 'Tecnologia',
        'computacao' => 'Computação',
        'arte' => 'Linguagens/Arte',
        'outra' => 'Outra',
    ];

    /** Catálogo fixo de tipos/categorias (valor salvo => rótulo exibido) */
    public const TIPOS = [
        'formacao_geral' => 'Formação Geral',
        'lingua_adicional' => 'Língua Adicional',
        'eletiva' => 'Eletiva',
        'itinerario_formativo' => 'Itinerário Formativo',
        'extracurricular' => 'Extracurricular',
        'complementar' => 'Complementar',
        'outra' => 'Outra',
    ];

    /** Catálogo fixo de aplicabilidade por etapa de ensino (valor salvo => rótulo exibido) */
    public const ETAPAS = [
        'nao_aplica' => 'Não se aplica',
        'obrigatoria' => 'Obrigatória',
        'oferta' => 'Oferta (eletiva)',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Busca todos os componentes curriculares
     */
    public function getAll(bool $apenasAtivos = false)
    {
        $this->ensurePaiColumn();
        $sql = "SELECT * FROM materias";
        if ($apenasAtivos) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY ordem ASC, nome ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Lista em árvore de 1 nível: pai seguido dos filhos.
     *
     * @return list<array<string,mixed>>
     */
    public function getAllArvore(bool $apenasAtivos = false): array
    {
        $rows = $this->getAll($apenasAtivos) ?: [];
        $byPai = [];
        $roots = [];
        $ids = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ids[$id] = true;
            $pai = (int) ($r['pai_id'] ?? 0);
            if ($pai > 0) {
                $byPai[$pai][] = $r;
            } else {
                $roots[] = $r;
            }
        }
        $out = [];
        $visto = [];
        foreach ($roots as $root) {
            $rid = (int) $root['id'];
            $out[] = $root;
            $visto[$rid] = true;
            foreach ($byPai[$rid] ?? [] as $child) {
                $cid = (int) ($child['id'] ?? 0);
                $out[] = $child;
                $visto[$cid] = true;
            }
        }
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0 && empty($visto[$id])) {
                $out[] = $r;
            }
        }
        return $out;
    }

    /**
     * Componentes oficiais da matriz: raiz sem pai (Matemática) ou rótulo de área
     * (Língua Portuguesa). Desdobramentos (filhos) não entram neste catálogo —
     * a carga deles é lançada debaixo do pai.
     *
     * @return list<array<string,mixed>>
     */
    public function getOficiaisParaMatriz(bool $apenasAtivos = true): array
    {
        $idsComFilhos = $this->idsComFilhos();
        $out = [];
        foreach ($this->getAll($apenasAtivos) ?: [] as $c) {
            $id = (int) ($c['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ((int) ($c['pai_id'] ?? 0) > 0) {
                continue;
            }
            $ehRotulo = isset($idsComFilhos[$id]);
            $c['eh_rotulo'] = $ehRotulo;
            $permiteAvaliacao = !isset($c['permite_avaliacao']) || (int) $c['permite_avaliacao'] === 1;
            if ($ehRotulo || $permiteAvaliacao) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /**
     * Filhos ativos agrupados pelo id do pai.
     *
     * @return array<int, list<array{id:int,nome:string,codigo:string}>>
     */
    public function mapaFilhosPorPai(): array
    {
        if ($this->mapaFilhosPorPaiCache !== null) {
            return $this->mapaFilhosPorPaiCache;
        }
        if (!$this->temPaiColumn()) {
            return $this->mapaFilhosPorPaiCache = [];
        }
        $rows = $this->db->fetchAll(
            "SELECT id, nome, codigo, pai_id FROM materias
             WHERE pai_id IS NOT NULL AND pai_id > 0 AND ativo = 1
             ORDER BY ordem ASC, nome ASC"
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $pai = (int) ($r['pai_id'] ?? 0);
            $id = (int) ($r['id'] ?? 0);
            if ($pai <= 0 || $id <= 0) {
                continue;
            }
            $out[$pai][] = [
                'id' => $id,
                'nome' => (string) ($r['nome'] ?? ''),
                'codigo' => (string) ($r['codigo'] ?? ''),
            ];
        }
        return $this->mapaFilhosPorPaiCache = $out;
    }

    /**
     * Filho → pai (só filhos ativos).
     *
     * @return array<int, int>
     */
    public function mapaPaiPorFilho(): array
    {
        $out = [];
        foreach ($this->mapaFilhosPorPai() as $paiId => $filhos) {
            foreach ($filhos as $f) {
                $fid = (int) ($f['id'] ?? 0);
                if ($fid > 0) {
                    $out[$fid] = (int) $paiId;
                }
            }
        }
        return $out;
    }

    /**
     * Componentes que recebem nota (exclui rótulos-pai e permite_avaliacao=0).
     *
     * @return list<array<string,mixed>>
     */
    public function getAvaliaveis(bool $apenasAtivos = true): array
    {
        $this->ensurePaiColumn();
        $sql = "SELECT * FROM materias WHERE 1=1";
        if ($apenasAtivos) {
            $sql .= " AND ativo = 1";
        }
        if ($this->temColuna('permite_avaliacao')) {
            $sql .= " AND permite_avaliacao = 1";
        }
        if ($this->temPaiColumn()) {
            $sql .= " AND id NOT IN (SELECT DISTINCT pai_id FROM materias WHERE pai_id IS NOT NULL AND pai_id > 0 AND ativo = 1)";
        }
        $sql .= " ORDER BY ordem ASC, nome ASC";
        return $this->db->fetchAll($sql) ?: [];
    }

    /**
     * @return array<int, true>
     */
    public function idsComFilhos(): array
    {
        if (!$this->temPaiColumn()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT pai_id FROM materias
             WHERE pai_id IS NOT NULL AND pai_id > 0 AND ativo = 1"
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['pai_id'] ?? 0);
            if ($id > 0) {
                $out[$id] = true;
            }
        }
        return $out;
    }

    /**
     * IDs oficiais + desdobramentos (busca de nota/prova).
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public function expandirIdsComFilhos(array $ids): array
    {
        $out = [];
        $filhosPorPai = $this->mapaFilhosPorPai();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $out[$id] = $id;
            foreach ($filhosPorPai[$id] ?? [] as $f) {
                $fid = (int) ($f['id'] ?? 0);
                if ($fid > 0) {
                    $out[$fid] = $fid;
                }
            }
        }
        return array_values($out);
    }

    /**
     * @return list<int>
     */
    public function listarFilhosIds(int $paiId): array
    {
        if (!$this->temPaiColumn() || $paiId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT id FROM materias WHERE pai_id = :pai_id AND ativo = 1 ORDER BY ordem ASC, nome ASC",
            ['pai_id' => $paiId]
        ) ?: [];
        $ids = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Busca componente curricular por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT * FROM materias WHERE id = :id",
            ['id' => (int) $id]
        );
    }

    /**
     * Cria novo componente curricular
     */
    public function create(array $data)
    {
        $this->ensurePaiColumn();
        $sql = "INSERT INTO materias
                    (nome, codigo, sigla, area_conhecimento, tipo,
                     etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio,
                     descricao, cor, ordem,
                     permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo, pai_id)
                VALUES
                    (:nome, :codigo, :sigla, :area_conhecimento, :tipo,
                     :etapa_infantil, :etapa_fund_i, :etapa_fund_ii, :etapa_medio,
                     :descricao, :cor, :ordem,
                     :permite_avaliacao, :permite_frequencia, :permite_plano_aula, :permite_diario, :ativo, :pai_id)";

        return $this->db->insert($sql, $this->paramsFromData($data));
    }

    /**
     * Atualiza componente curricular
     */
    public function update($id, array $data)
    {
        $this->ensurePaiColumn();
        $sql = "UPDATE materias SET
                    nome = :nome,
                    codigo = :codigo,
                    sigla = :sigla,
                    area_conhecimento = :area_conhecimento,
                    tipo = :tipo,
                    etapa_infantil = :etapa_infantil,
                    etapa_fund_i = :etapa_fund_i,
                    etapa_fund_ii = :etapa_fund_ii,
                    etapa_medio = :etapa_medio,
                    descricao = :descricao,
                    cor = :cor,
                    ordem = :ordem,
                    permite_avaliacao = :permite_avaliacao,
                    permite_frequencia = :permite_frequencia,
                    permite_plano_aula = :permite_plano_aula,
                    permite_diario = :permite_diario,
                    ativo = :ativo,
                    pai_id = :pai_id
                WHERE id = :id";

        $params = $this->paramsFromData($data);
        $params['id'] = (int) $id;

        return $this->db->update($sql, $params);
    }

    /**
     * Exclui componente curricular
     */
    public function delete($id)
    {
        return $this->db->delete("DELETE FROM materias WHERE id = :id", ['id' => (int) $id]);
    }

    /**
     * Verifica se componente curricular existe
     */
    public function exists($id)
    {
        $result = $this->db->fetch("SELECT id FROM materias WHERE id = :id", ['id' => (int) $id]);
        return $result !== false;
    }

    /**
     * Verifica se nome do componente curricular já existe
     */
    public function nameExists($nome, $excludeId = null)
    {
        $sql = "SELECT id FROM materias WHERE nome = :nome";
        $params = ['nome' => $nome];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = (int) $excludeId;
        }

        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }

    /**
     * Verifica se código do componente curricular já existe
     */
    public function codigoExists($codigo, $excludeId = null)
    {
        $sql = "SELECT id FROM materias WHERE codigo = :codigo";
        $params = ['codigo' => $codigo];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = (int) $excludeId;
        }

        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }

    /**
     * Conta total de componentes curriculares
     */
    public function count()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM materias");
        return $result['total'];
    }

    /**
     * Monta os parâmetros nomeados a partir do array de dados já validado pelo Service.
     */
    private function paramsFromData(array $data): array
    {
        return [
            'nome' => $data['nome'],
            'codigo' => $data['codigo'],
            'sigla' => $data['sigla'] !== '' ? $data['sigla'] : null,
            'area_conhecimento' => $data['area_conhecimento'],
            'tipo' => $data['tipo'],
            'etapa_infantil' => $data['etapa_infantil'],
            'etapa_fund_i' => $data['etapa_fund_i'],
            'etapa_fund_ii' => $data['etapa_fund_ii'],
            'etapa_medio' => $data['etapa_medio'],
            'descricao' => $data['descricao'] !== '' ? $data['descricao'] : null,
            'cor' => $data['cor'] !== '' ? $data['cor'] : null,
            'ordem' => (int) $data['ordem'],
            'permite_avaliacao' => (int) $data['permite_avaliacao'],
            'permite_frequencia' => (int) $data['permite_frequencia'],
            'permite_plano_aula' => (int) $data['permite_plano_aula'],
            'permite_diario' => (int) $data['permite_diario'],
            'ativo' => (int) $data['ativo'],
            'pai_id' => !empty($data['pai_id']) ? (int) $data['pai_id'] : null,
        ];
    }

    public function marcarPaiComoRotulo(int $paiId): void
    {
        if ($paiId <= 0 || !$this->temColuna('permite_avaliacao')) {
            return;
        }
        $this->db->update(
            "UPDATE materias SET permite_avaliacao = 0 WHERE id = :id",
            ['id' => $paiId]
        );
    }

    private function ensurePaiColumn(): void
    {
        if ($this->temPaiColumn()) {
            return;
        }
        try {
            $this->db->query(
                "ALTER TABLE materias ADD COLUMN pai_id INT NULL DEFAULT NULL COMMENT 'Área-rótulo (sem nota própria)' AFTER ativo"
            );
            $this->colunaExisteCache['materias.pai_id'] = true;
        } catch (Throwable $e) {
            $this->colunaExisteCache['materias.pai_id'] = false;
        }
    }

    private function temPaiColumn(): bool
    {
        return $this->temColuna('pai_id');
    }

    private function temColuna(string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        $key = 'materias.' . $column;
        if (array_key_exists($key, $this->colunaExisteCache)) {
            return $this->colunaExisteCache[$key];
        }
        $existe = false;
        try {
            $row = $this->db->fetch("SHOW COLUMNS FROM `materias` LIKE '{$column}'");
            $existe = !empty($row);
        } catch (Throwable $e) {
            $existe = false;
        }
        $this->colunaExisteCache[$key] = $existe;
        return $existe;
    }

    /** @var array<string, bool> */
    private array $colunaExisteCache = [];
}
