<?php

namespace App\Modulos\RegrasAcademicas\Services;

use App\Core\PeriodoLetivo;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

/**
 * EducaTudo - CRUD de Regras de Aprovação (validação + versionamento).
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
        $data['versao'] = 1;
        $id = $this->model->create($data);
        if ($this->model->boletimPronto()) {
            $this->model->substituirMaterias($id, (array) ($data['materias_ids'] ?? []));
        }
        if ($this->model->seriesProntas()) {
            $this->model->substituirSeries($id, (array) ($data['series_ids'] ?? []));
        }
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
            return ['success' => false, 'error' => 'Regra de aprovação não encontrada.'];
        }

        $data = $this->normalizar($input);
        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        $data['versao'] = ((int) ($atual['versao'] ?? 1)) + 1;
        $data['codigo'] = trim((string) ($atual['codigo'] ?? '')) !== ''
            ? (string) $atual['codigo']
            : null;
        $this->model->update($id, $data);
        if ($this->model->boletimPronto()) {
            $this->model->substituirMaterias($id, (array) ($data['materias_ids'] ?? []));
        }
        if ($this->model->seriesProntas()) {
            $this->model->substituirSeries($id, (array) ($data['series_ids'] ?? []));
        }
        $this->model->gravarHistorico($id, $data['versao'], $data, $usuarioId, $usuarioNome);
        $ano = (int) ($data['ano_letivo'] ?? 0);
        if ($ano >= 2000 && $ano <= 2100) {
            (new RecuperacaoConfig())->alinharAno($id, $ano);
        }

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function excluir(int $id): array
    {
        if (!$this->model->exists($id)) {
            return ['success' => false, 'error' => 'Regra de aprovação não encontrada.'];
        }
        $this->model->delete($id);
        return ['success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizar(array $input): array
    {
        $intOrNull = static function ($v): ?int {
            if ($v === null || $v === '') {
                return null;
            }
            $n = (int) $v;
            return $n > 0 ? $n : null;
        };

        $anoLetivo = $intOrNull($input['ano_letivo'] ?? null);
        $periodoTipo = $anoLetivo
            ? (string) (PeriodoLetivo::doAno($anoLetivo)['tipo'] ?? 'bimestre')
            : (string) ($input['periodo_tipo'] ?? 'bimestre');
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
        if (!in_array($dec, [0, 1, 2], true)) {
            $dec = 2;
        }

        $seriesIds = $this->idsPositivos($input['series_ids'] ?? []);
        if ($seriesIds === []) {
            $serieUnica = $intOrNull($input['serie_id'] ?? null);
            if ($serieUnica !== null) {
                $seriesIds = [$serieUnica];
            }
        }
        $serieId = count($seriesIds) === 1 ? $seriesIds[0] : null;

        $formulaMedia = trim((string) ($input['formula_media'] ?? ''));
        $formulaFinal = trim((string) ($input['formula_final'] ?? ''));

        return [
            'nome' => trim((string) ($input['nome'] ?? '')),
            'codigo' => null,
            'ano_letivo' => $anoLetivo,
            'curso_id' => $intOrNull($input['curso_id'] ?? null),
            'serie_id' => $serieId,
            'series_ids' => $seriesIds,
            'series_modo' => (string) ($input['series_modo'] ?? ''),
            'matriz_curricular_id' => null,
            'materia_id' => null,
            'agrupamento_id' => null,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => null,
            'media_minima' => is_numeric($input['media_minima'] ?? null)
                ? (float) str_replace(',', '.', (string) $input['media_minima'])
                : 6.0,
            'escala' => $escala = isset(RegraAcademica::ESCALAS[(string) ($input['escala'] ?? '')])
                ? (string) $input['escala']
                : '10',
            'conceito_minimo' => ($conceitosEscala = RegraAcademica::conceitosDa($escala)) !== null
                && isset($conceitosEscala[(string) ($input['conceito_minimo'] ?? '')])
                ? (string) $input['conceito_minimo']
                : null,
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
            'min_avaliacoes' => null,
            'max_avaliacoes' => null,
            'componentes_sem_nota' => 0,
            'aprovacao_so_frequencia' => !empty($input['aprovacao_so_frequencia']) ? 1 : 0,
            'situacoes_json' => null,
            'observacoes' => null,
            'ativo' => array_key_exists('ativo', $input) ? (!empty($input['ativo']) ? 1 : 0) : 1,
            'finalidade' => ((string) ($input['finalidade'] ?? '')) === 'complementar' ? 'complementar' : 'oficial',
            'vis_aluno' => !empty($input['vis_aluno']) ? 1 : 0,
            'vis_pais' => !empty($input['vis_pais']) ? 1 : 0,
            'materias_ids' => $this->idsPositivos($input['materias_ids'] ?? []),
        ];
    }

    private function validar(array $data): ?string
    {
        if ($data['nome'] === '') {
            return 'Nome é obrigatório.';
        }
        $escala = (string) ($data['escala'] ?? '10');
        $conceitosEscala = RegraAcademica::conceitosDa($escala);
        if ($conceitosEscala !== null) {
            if (!isset($conceitosEscala[(string) ($data['conceito_minimo'] ?? '')])) {
                return 'Escolha o conceito mínimo para aprovar.';
            }
            if (!$this->model->conceitoMinimoCabe((string) $data['conceito_minimo'])) {
                return 'Rode a migration 2026_10_29_conceito_minimo_tamanho.sql no painel Master antes de usar este conceito.';
            }
        } elseif (RegraAcademica::numerica($escala)) {
            $teto = $escala === '100' ? 100.0 : 10.0;
            if ($data['media_minima'] < 0 || $data['media_minima'] > $teto) {
                return 'Média mínima deve estar entre 0 e ' . (int) $teto . '.';
            }
        }
        if (!$this->model->escalaPronta() && ($escala !== '10' || ($data['conceito_minimo'] ?? null) !== null)) {
            return 'Rode a migration 2026_10_28_regra_escala.sql no painel Master antes de mudar a escala.';
        }
        if ($data['frequencia_minima'] < 0 || $data['frequencia_minima'] > 100) {
            return 'Frequência mínima deve estar entre 0 e 100.';
        }
        if (($data['series_modo'] ?? '') === 'nenhuma') {
            return 'Marque ao menos uma série ou escolha Todas as Séries / Anos.';
        }
        if (count($data['series_ids'] ?? []) > 1 && !$this->model->seriesProntas()) {
            return 'Rode a migration 2026_10_29_regra_series.sql no painel Master para escolher mais de uma série.';
        }
        if ($this->model->boletimPronto() && ($data['materias_ids'] ?? []) === []) {
            return 'Marque ao menos uma disciplina nesta regra.';
        }
        return null;
    }

    /**
     * @param mixed $bruto
     * @return list<int>
     */
    private function idsPositivos($bruto): array
    {
        $ids = [];
        foreach ((array) $bruto as $valor) {
            $id = (int) $valor;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!class_exists('RegraAcademicaService', false) && class_exists(__NAMESPACE__ . '\\RegraAcademicaService', false)) {
    class_alias(__NAMESPACE__ . '\\RegraAcademicaService', 'RegraAcademicaService');
}
