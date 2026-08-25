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
        $sql = "SELECT * FROM materias";
        if ($apenasAtivos) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY ordem ASC, nome ASC";

        return $this->db->fetchAll($sql);
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
        $sql = "INSERT INTO materias
                    (nome, codigo, sigla, area_conhecimento, tipo,
                     etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio,
                     descricao, cor, ordem,
                     permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo)
                VALUES
                    (:nome, :codigo, :sigla, :area_conhecimento, :tipo,
                     :etapa_infantil, :etapa_fund_i, :etapa_fund_ii, :etapa_medio,
                     :descricao, :cor, :ordem,
                     :permite_avaliacao, :permite_frequencia, :permite_plano_aula, :permite_diario, :ativo)";

        return $this->db->insert($sql, $this->paramsFromData($data));
    }

    /**
     * Atualiza componente curricular
     */
    public function update($id, array $data)
    {
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
                    ativo = :ativo
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
        ];
    }
}
