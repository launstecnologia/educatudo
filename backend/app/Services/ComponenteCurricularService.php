<?php
require_once __DIR__ . '/../Models/Education/ComponenteCurricular.php';

/**
 * EducaTudo - Service de Componentes Curriculares
 * Valida e monta os dados de criação/edição de um componente curricular.
 */
class ComponenteCurricularService
{
    private ComponenteCurricular $componenteCurricular;

    public function __construct()
    {
        $this->componenteCurricular = new ComponenteCurricular();
    }

    /**
     * Valida e cria um componente curricular.
     * @return array{success: bool, id?: int, error?: string}
     */
    public function criar(array $input): array
    {
        $data = $this->normalizar($input);

        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($this->componenteCurricular->nameExists($data['nome'])) {
            return ['success' => false, 'error' => 'Nome do componente curricular já cadastrado'];
        }

        if ($this->componenteCurricular->codigoExists($data['codigo'])) {
            return ['success' => false, 'error' => 'Código do componente curricular já cadastrado'];
        }

        $id = $this->componenteCurricular->create($data);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Valida e atualiza um componente curricular existente.
     * @return array{success: bool, error?: string}
     */
    public function atualizar(int $id, array $input): array
    {
        if (!$this->componenteCurricular->exists($id)) {
            return ['success' => false, 'error' => 'Componente curricular não encontrado'];
        }

        $data = $this->normalizar($input);

        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($this->componenteCurricular->nameExists($data['nome'], $id)) {
            return ['success' => false, 'error' => 'Nome do componente curricular já cadastrado'];
        }

        if ($this->componenteCurricular->codigoExists($data['codigo'], $id)) {
            return ['success' => false, 'error' => 'Código do componente curricular já cadastrado'];
        }

        $this->componenteCurricular->update($id, $data);

        return ['success' => true];
    }

    /**
     * Normaliza o input bruto (ex.: $_POST) para o formato esperado pelo Model.
     */
    private function normalizar(array $input): array
    {
        return [
            'nome' => trim((string) ($input['nome'] ?? '')),
            'codigo' => strtoupper(trim((string) ($input['codigo'] ?? ''))),
            'sigla' => strtoupper(trim((string) ($input['sigla'] ?? ''))),
            'area_conhecimento' => trim((string) ($input['area_conhecimento'] ?? '')),
            'tipo' => trim((string) ($input['tipo'] ?? '')),
            'etapa_infantil' => $this->normalizarEtapa($input['etapa_infantil'] ?? ''),
            'etapa_fund_i' => $this->normalizarEtapa($input['etapa_fund_i'] ?? ''),
            'etapa_fund_ii' => $this->normalizarEtapa($input['etapa_fund_ii'] ?? ''),
            'etapa_medio' => $this->normalizarEtapa($input['etapa_medio'] ?? ''),
            'descricao' => trim((string) ($input['descricao'] ?? '')),
            'cor' => trim((string) ($input['cor'] ?? '')),
            'ordem' => (int) ($input['ordem'] ?? 0),
            'permite_avaliacao' => !empty($input['permite_avaliacao']) ? 1 : 0,
            'permite_frequencia' => !empty($input['permite_frequencia']) ? 1 : 0,
            'permite_plano_aula' => !empty($input['permite_plano_aula']) ? 1 : 0,
            'permite_diario' => !empty($input['permite_diario']) ? 1 : 0,
            'ativo' => !empty($input['ativo']) ? 1 : 0,
        ];
    }

    /**
     * @return string|null Mensagem de erro, ou null se válido.
     */
    private function validar(array $data): ?string
    {
        if ($data['nome'] === '') {
            return 'Nome é obrigatório';
        }
        if (mb_strlen($data['nome']) > 100) {
            return 'Nome deve ter no máximo 100 caracteres';
        }

        if ($data['codigo'] === '') {
            return 'Código é obrigatório';
        }
        if (mb_strlen($data['codigo']) > 20) {
            return 'Código deve ter no máximo 20 caracteres';
        }

        if (mb_strlen($data['sigla']) > 10) {
            return 'Sigla deve ter no máximo 10 caracteres';
        }

        if (!isset(ComponenteCurricular::AREAS_CONHECIMENTO[$data['area_conhecimento']])) {
            return 'Área do conhecimento inválida';
        }

        if (!isset(ComponenteCurricular::TIPOS[$data['tipo']])) {
            return 'Tipo/categoria inválido';
        }

        if ($data['cor'] !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['cor'])) {
            return 'Cor inválida — use o formato hexadecimal (#RRGGBB)';
        }

        return null;
    }

    /**
     * Normaliza o valor de uma etapa de ensino, caindo em "não se aplica"
     * quando vazio ou fora do catálogo — nunca deixa o form travar por causa
     * dessa parte opcional do cadastro.
     */
    private function normalizarEtapa($valor): string
    {
        $valor = trim((string) $valor);
        return isset(ComponenteCurricular::ETAPAS[$valor]) ? $valor : 'nao_aplica';
    }
}
