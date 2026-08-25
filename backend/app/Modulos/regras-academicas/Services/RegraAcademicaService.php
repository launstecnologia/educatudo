<?php

namespace App\Modulos\RegrasAcademicas\Services;

require_once __DIR__ . '/../Models/RegraAcademica.php';

use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

/**
 * EducaTudo - CRUD de Regras Acadêmicas (validação + versionamento).
 */
class RegraAcademicaService
{
    private RegraAcademica $model;

    public function __construct()
    {
        $this->model = new RegraAcademica();
    }

    public function model(): RegraAcademica
    {
        return $this->model;
    }

    /**
     * @return array{success: bool, id?: int, error?: string}
     */
    public function criar(array $input, ?int $usuarioId = null, ?string $usuarioNome = null): array
    {
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Rode a migration 2026_08_22_regras_academicas.sql no painel Master.'];
        }

        $data = $this->normalizar($input);
        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }
        if ($data['codigo'] !== null && $this->model->codigoExists($data['codigo'])) {
            return ['success' => false, 'error' => 'Código já cadastrado em outra regra.'];
        }

        $data['versao'] = 1;
        $id = $this->model->create($data);
        $this->model->gravarHistorico($id, 1, $data, $usuarioId, $usuarioNome);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function atualizar(int $id, array $input, ?int $usuarioId = null, ?string $usuarioNome = null): array
    {
        $atual = $this->model->findById($id);
        if (!$atual) {
            return ['success' => false, 'error' => 'Regra acadêmica não encontrada.'];
        }

        $data = $this->normalizar($input);
        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }
        if ($data['codigo'] !== null && $this->model->codigoExists($data['codigo'], $id)) {
            return ['success' => false, 'error' => 'Código já cadastrado em outra regra.'];
        }

        $data['versao'] = ((int) ($atual['versao'] ?? 1)) + 1;
        $this->model->update($id, $data);
        $this->model->gravarHistorico($id, $data['versao'], $data, $usuarioId, $usuarioNome);

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function excluir(int $id): array
    {
        if (!$this->model->exists($id)) {
            return ['success' => false, 'error' => 'Regra acadêmica não encontrada.'];
        }
        $this->model->delete($id);
        return ['success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizar(array $input): array
    {
        $codigo = strtolower(trim((string) ($input['codigo'] ?? '')));
        if ($codigo !== '') {
            $codigo = preg_replace('/[^a-z0-9_\-]+/', '-', $codigo);
        }
        $codigo = ($codigo === '' || $codigo === null) ? null : $codigo;

        $intOrNull = static function ($v): ?int {
            if ($v === null || $v === '') {
                return null;
            }
            $n = (int) $v;
            return $n > 0 ? $n : null;
        };

        $periodoNumero = $intOrNull($input['periodo_numero'] ?? null);
        $periodoTipo = (string) ($input['periodo_tipo'] ?? 'bimestre');
        if (!isset(RegraAcademica::PERIODO_TIPOS[$periodoTipo])) {
            $periodoTipo = 'bimestre';
        }

        $roundMode = strtolower(trim((string) ($input['round_mode'] ?? 'none')));
        if (!in_array($roundMode, ['none', 'half'], true)) {
            $roundMode = 'none';
        }

        $recTipo = (string) ($input['recuperacao_tipo'] ?? 'periodo');
        if (!isset(RegraAcademica::RECUPERACAO_TIPOS[$recTipo])) {
            $recTipo = 'periodo';
        }
        $recComp = (string) ($input['recuperacao_composicao'] ?? 'maior_nota');
        if (!isset(RegraAcademica::RECUPERACAO_COMPOSICOES[$recComp])) {
            $recComp = 'maior_nota';
        }

        $dec = (int) ($input['decimal_places'] ?? 2);
        if ($dec !== 1) {
            $dec = 2;
        }

        $formulaMedia = trim((string) ($input['formula_media'] ?? ''));
        $formulaFinal = trim((string) ($input['formula_final'] ?? ''));

        return [
            'nome' => trim((string) ($input['nome'] ?? '')),
            'codigo' => $codigo,
            'ano_letivo' => $intOrNull($input['ano_letivo'] ?? null),
            'curso_id' => $intOrNull($input['curso_id'] ?? null),
            'serie_id' => $intOrNull($input['serie_id'] ?? null),
            'matriz_curricular_id' => $intOrNull($input['matriz_curricular_id'] ?? null),
            'materia_id' => $intOrNull($input['materia_id'] ?? null),
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'media_minima' => is_numeric($input['media_minima'] ?? null)
                ? (float) str_replace(',', '.', (string) $input['media_minima'])
                : 6.0,
            'frequencia_minima' => is_numeric($input['frequencia_minima'] ?? null)
                ? (float) str_replace(',', '.', (string) $input['frequencia_minima'])
                : 75.0,
            'usar_frequencia' => !empty($input['usar_frequencia']) ? 1 : 0,
            'round_mode' => $roundMode,
            'decimal_places' => $dec,
            'formula_media' => $formulaMedia !== '' ? $formulaMedia : null,
            'formula_final' => $formulaFinal !== '' ? $formulaFinal : null,
            'recuperacao_tipo' => $recTipo,
            'recuperacao_composicao' => $recComp,
            'min_avaliacoes' => $intOrNull($input['min_avaliacoes'] ?? null),
            'max_avaliacoes' => $intOrNull($input['max_avaliacoes'] ?? null),
            'componentes_sem_nota' => !empty($input['componentes_sem_nota']) ? 1 : 0,
            'aprovacao_so_frequencia' => !empty($input['aprovacao_so_frequencia']) ? 1 : 0,
            'situacoes_json' => null,
            'observacoes' => trim((string) ($input['observacoes'] ?? '')) ?: null,
            'ativo' => array_key_exists('ativo', $input) ? (!empty($input['ativo']) ? 1 : 0) : 1,
        ];
    }

    private function validar(array $data): ?string
    {
        if ($data['nome'] === '') {
            return 'Nome é obrigatório.';
        }
        if ($data['media_minima'] < 0 || $data['media_minima'] > 10) {
            return 'Média mínima deve estar entre 0 e 10.';
        }
        if ($data['frequencia_minima'] < 0 || $data['frequencia_minima'] > 100) {
            return 'Frequência mínima deve estar entre 0 e 100.';
        }
        if ($data['periodo_numero'] !== null && ($data['periodo_numero'] < 1 || $data['periodo_numero'] > 4)) {
            return 'Número do período deve ser de 1 a 4.';
        }
        if ($data['min_avaliacoes'] !== null && $data['max_avaliacoes'] !== null
            && $data['min_avaliacoes'] > $data['max_avaliacoes']) {
            return 'Quantidade mínima de avaliações não pode ser maior que a máxima.';
        }
        return null;
    }
}
