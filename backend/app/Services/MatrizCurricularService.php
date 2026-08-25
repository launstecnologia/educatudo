<?php
require_once __DIR__ . '/../Models/Education/MatrizCurricular.php';

/**
 * EducaTudo - Service de Matriz Curricular
 * Valida e monta os dados de criação/edição de uma Matriz Curricular e dos
 * Componentes Curriculares vinculados a ela.
 */
class MatrizCurricularService
{
    private MatrizCurricular $matrizCurricular;
    private $db;

    public function __construct()
    {
        $this->matrizCurricular = new MatrizCurricular();
        $this->db = Database::getInstance();
    }

    /**
     * Valida e cria uma Matriz Curricular com seus componentes.
     * @return array{success: bool, id?: int, error?: string}
     */
    public function criar(array $input): array
    {
        $data = $this->normalizar($input);
        $componentes = $this->normalizarComponentes($input['componentes'] ?? []);

        $erro = $this->validar($data, $componentes);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($this->matrizCurricular->nameExists($data['nome'])) {
            return ['success' => false, 'error' => 'Nome da matriz já cadastrado'];
        }

        if ($this->matrizCurricular->codigoExists($data['codigo'])) {
            return ['success' => false, 'error' => 'Código da matriz já cadastrado'];
        }

        $id = $this->matrizCurricular->create($data);
        $this->matrizCurricular->salvarComponentes((int) $id, $componentes);

        return ['success' => true, 'id' => (int) $id];
    }

    /**
     * Valida e atualiza uma Matriz Curricular existente e seus componentes.
     * @return array{success: bool, error?: string}
     */
    public function atualizar(int $id, array $input): array
    {
        if (!$this->matrizCurricular->exists($id)) {
            return ['success' => false, 'error' => 'Matriz Curricular não encontrada'];
        }

        $data = $this->normalizar($input);
        $componentes = $this->normalizarComponentes($input['componentes'] ?? []);

        $erro = $this->validar($data, $componentes);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($this->matrizCurricular->nameExists($data['nome'], $id)) {
            return ['success' => false, 'error' => 'Nome da matriz já cadastrado'];
        }

        if ($this->matrizCurricular->codigoExists($data['codigo'], $id)) {
            return ['success' => false, 'error' => 'Código da matriz já cadastrado'];
        }

        $this->matrizCurricular->update($id, $data);
        $this->matrizCurricular->salvarComponentes($id, $componentes);

        return ['success' => true];
    }

    /**
     * Exclui uma Matriz Curricular, desde que nenhuma turma esteja vinculada.
     * @return array{success: bool, error?: string}
     */
    public function excluir(int $id): array
    {
        if (!$this->matrizCurricular->exists($id)) {
            return ['success' => false, 'error' => 'Matriz Curricular não encontrada'];
        }

        $turmasVinculadas = $this->matrizCurricular->countTurmasVinculadas($id);
        if ($turmasVinculadas > 0) {
            return [
                'success' => false,
                'error' => "Não é possível excluir: há {$turmasVinculadas} turma(s) vinculada(s) a esta matriz. " .
                    'Desvincule as turmas ou marque a matriz como "Inativa" em vez de excluir.',
            ];
        }

        $this->matrizCurricular->delete($id);

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
            'curso_id' => (int) ($input['curso_id'] ?? 0),
            'serie_id' => (int) ($input['serie_id'] ?? 0),
            'modalidade' => trim((string) ($input['modalidade'] ?? '')),
            'turno' => trim((string) ($input['turno'] ?? '')),
            'carga_horaria_anual_prevista' => trim((string) ($input['carga_horaria_anual_prevista'] ?? '')),
            'dias_letivos_previstos' => trim((string) ($input['dias_letivos_previstos'] ?? '')),
            'duracao_padrao_aula_minutos' => (int) ($input['duracao_padrao_aula_minutos'] ?? 50),
            'base_legal' => trim((string) ($input['base_legal'] ?? '')),
            'observacoes' => trim((string) ($input['observacoes'] ?? '')),
            'ativo' => !empty($input['ativo']) ? 1 : 0,
        ];
    }

    /**
     * Normaliza a lista de componentes vinda do formulário (linhas dinâmicas).
     * @return list<array{materia_id:int, aulas_semana:int, obrigatorio:int, ordem_boletim:int, ordem_historico:int}>
     */
    private function normalizarComponentes(array $componentesInput): array
    {
        $componentes = [];

        foreach ($componentesInput as $ordem => $componente) {
            $materiaId = (int) ($componente['materia_id'] ?? 0);
            if ($materiaId <= 0) {
                continue;
            }

            $componentes[] = [
                'materia_id' => $materiaId,
                'aulas_semana' => max(1, (int) ($componente['aulas_semana'] ?? 1)),
                'obrigatorio' => !empty($componente['obrigatorio']) ? 1 : 0,
                'ordem_boletim' => (int) ($componente['ordem_boletim'] ?? $ordem),
                'ordem_historico' => (int) ($componente['ordem_historico'] ?? $ordem),
            ];
        }

        return $componentes;
    }

    /**
     * @param list<array{materia_id:int}> $componentes
     * @return string|null Mensagem de erro, ou null se válido.
     */
    private function validar(array $data, array $componentes): ?string
    {
        if ($data['nome'] === '') {
            return 'Nome da matriz é obrigatório';
        }

        if ($data['codigo'] === '') {
            return 'Código da matriz é obrigatório';
        }

        if ($data['curso_id'] <= 0) {
            return 'Curso é obrigatório';
        }

        if ($data['serie_id'] <= 0) {
            return 'Série é obrigatória';
        }

        $serie = $this->db->fetch(
            "SELECT id, curso_id FROM serie WHERE id = :id",
            ['id' => $data['serie_id']]
        );
        if (!$serie) {
            return 'Série inválida';
        }
        if ((int) $serie['curso_id'] !== $data['curso_id']) {
            return 'A série selecionada não pertence ao curso selecionado';
        }

        if ($data['duracao_padrao_aula_minutos'] <= 0) {
            return 'Duração da aula deve ser maior que zero';
        }

        if (empty($componentes)) {
            return 'Adicione pelo menos um Componente Curricular à matriz';
        }

        $materiaIds = array_column($componentes, 'materia_id');
        if (count($materiaIds) !== count(array_unique($materiaIds))) {
            return 'Há Componentes Curriculares repetidos na lista';
        }

        $placeholders = implode(',', array_fill(0, count($materiaIds), '?'));
        $existentes = $this->db->fetchAll(
            "SELECT id FROM materias WHERE id IN ({$placeholders}) AND ativo = 1",
            $materiaIds
        );
        if (count($existentes) !== count($materiaIds)) {
            return 'Um ou mais Componentes Curriculares selecionados são inválidos ou estão inativos';
        }

        return null;
    }
}
