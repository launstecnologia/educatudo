<?php
require_once __DIR__ . '/../Models/Education/Sala.php';

/**
 * EducaTudo - Service de Sala / Ambiente
 * Valida e monta os dados de criação/edição de uma sala/ambiente.
 */
class SalaService
{
    private Sala $sala;

    public function __construct()
    {
        $this->sala = new Sala();
    }

    /**
     * @return array{success: bool, id?: int, error?: string}
     */
    public function criar(array $input): array
    {
        $data = $this->normalizar($input);

        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($this->sala->codigoExists($data['codigo'])) {
            return ['success' => false, 'error' => 'Código já cadastrado para outra sala/ambiente'];
        }

        $id = $this->sala->create($data);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function atualizar(int $id, array $input): array
    {
        if (!$this->sala->exists($id)) {
            return ['success' => false, 'error' => 'Sala/Ambiente não encontrada'];
        }

        $data = $this->normalizar($input);

        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($this->sala->codigoExists($data['codigo'], $id)) {
            return ['success' => false, 'error' => 'Código já cadastrado para outra sala/ambiente'];
        }

        $this->sala->update($id, $data);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function excluir(int $id): array
    {
        if (!$this->sala->exists($id)) {
            return ['success' => false, 'error' => 'Sala/Ambiente não encontrada'];
        }

        $vinculos = $this->sala->countVinculos($id);
        if ($vinculos > 0) {
            return [
                'success' => false,
                'error' => "Não é possível excluir: há {$vinculos} registro(s) vinculado(s) (turmas ou patrimônio). " .
                    'Marque como "Inativa" em vez de excluir.',
            ];
        }

        $this->sala->delete($id);

        return ['success' => true];
    }

    private function normalizar(array $input): array
    {
        return [
            'codigo' => strtoupper(trim((string) ($input['codigo'] ?? ''))),
            'nome' => trim((string) ($input['nome'] ?? '')),
            'tipo' => trim((string) ($input['tipo'] ?? 'sala')),
            'capacidade' => trim((string) ($input['capacidade'] ?? '')),
            'bloco' => trim((string) ($input['bloco'] ?? '')),
            'andar' => trim((string) ($input['andar'] ?? '')),
            'responsavel_nome' => trim((string) ($input['responsavel_nome'] ?? '')),
            'ativo' => !empty($input['ativo']) ? 1 : 0,
        ];
    }

    private function validar(array $data): ?string
    {
        if ($data['nome'] === '') {
            return 'Nome é obrigatório';
        }

        if (!isset(Sala::TIPOS[$data['tipo']])) {
            return 'Tipo inválido';
        }

        if ($data['capacidade'] !== '' && ((int) $data['capacidade'] < 0 || !ctype_digit($data['capacidade']))) {
            return 'Capacidade deve ser um número inteiro positivo';
        }

        return null;
    }
}
