<?php

namespace App\Modulos\Boletins\Services;

require_once __DIR__ . '/../Models/Boletim.php';
require_once __DIR__ . '/../../../Models/System/BoletimConfig.php';
require_once __DIR__ . '/../../../Services/BoletimAssistenteWizard.php';
require_once __DIR__ . '/../../regras-academicas/Models/RegraAcademica.php';

use App\Modulos\Boletins\Models\Boletim;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;
use BoletimAssistenteWizard;
use BoletimConfig;
use Database;
use Throwable;

/**
 * Cadastro de boletim + documento 1º–4º (boletim_regras exibir_em=boletim).
 */
class BoletimCadastroService
{
    private Boletim $model;
    private BoletimConfig $boletimConfig;

    public function __construct()
    {
        $this->model = new Boletim();
        $this->boletimConfig = new BoletimConfig();
        $this->boletimConfig->ensureSchema();
    }

    public function model(): Boletim
    {
        return $this->model;
    }

    /**
     * @return array{success:bool,id?:int,error?:string}
     */
    public function salvar(array $input, ?int $id = null): array
    {
        if (!$this->model->tabelasProntas()) {
            return ['success' => false, 'error' => 'Rode a migration 2026_09_02_boletins.sql no painel Master.'];
        }

        $data = $this->normalizar($input);
        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($id !== null && $id > 0) {
            $atual = $this->model->findById($id);
            if ($atual === null) {
                return ['success' => false, 'error' => 'Boletim não encontrado.'];
            }
            $data['regra_id'] = $atual['regra_id'] ?? null;
            $this->model->atualizar($id, $data);
            $this->sincronizarDocumento($id);
            return ['success' => true, 'id' => $id];
        }

        $novoId = $this->model->criar($data);
        $this->sincronizarDocumento($novoId);
        return ['success' => true, 'id' => $novoId];
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function excluir(int $id): array
    {
        $item = $this->model->findById($id);
        if ($item === null) {
            return ['success' => false, 'error' => 'Boletim não encontrado.'];
        }
        $this->boletimConfig->desvincularEventosDoBoletim($id);
        $this->model->excluir($id);
        return ['success' => true];
    }

    /**
     * Recria o documento 1º–4º a partir dos eventos de notas vinculados.
     *
     * @return array{success:bool,regra_id?:int,error?:string}
     */
    public function sincronizarDocumento(int $boletimId): array
    {
        $item = $this->model->findById($boletimId);
        if ($item === null) {
            return ['success' => false, 'error' => 'Boletim não encontrado.'];
        }

        $fontes = $this->boletimConfig->fontesBimestresDoBoletim($boletimId);
        $temFonte = false;
        foreach ($fontes as $idFonte) {
            if ((int) $idFonte > 0) {
                $temFonte = true;
                break;
            }
        }

        $regraId = (int) ($item['regra_id'] ?? 0);
        $componentes = [];
        $formulaFinal = 'media_final';

        if ($temFonte) {
            $wizard = new BoletimAssistenteWizard();
            $estado = $wizard->estadoPadrao();
            $estado['exibir_em'] = 'boletim';
            $estado['finalidade'] = $item['finalidade'];
            $estado['nome'] = $item['nome'];
            $estado['ano_letivo'] = $item['ano_letivo'] ?: (int) date('Y');
            $estado['materias_ids'] = $item['materias_ids'];
            $estado['series_ids'] = $item['series_ids'];
            $estado['turmas_ids'] = $item['turmas_ids'];
            $criterios = $this->criteriosDoBoletim($item);
            $estado['nota_minima_aprovacao'] = $criterios['nota_minima_aprovacao'];
            $estado['round_mode'] = $criterios['round_mode'];
            $estado['fontes_bimestres'] = $fontes;
            $estado['regra_id'] = $regraId > 0 ? $regraId : null;
            $estado['modo'] = $regraId > 0 ? 'editar' : 'criar';
            $montado = $wizard->montar($estado);
            if (empty($montado['ok']) || !is_array($montado['rascunho'] ?? null)) {
                $erros = is_array($montado['erros'] ?? null) ? $montado['erros'] : ['Não foi possível montar o documento do boletim.'];
                return ['success' => false, 'error' => implode(' ', $erros)];
            }
            $rascunho = $montado['rascunho'];
            $componentes = $this->normalizarComponentesParaSave($rascunho['componentes'] ?? []);
            $formulaFinal = trim((string) ($rascunho['formula_final'] ?? 'media_final'));
        } else {
            $componentes = $this->normalizarComponentesParaSave($this->esqueletoDocumento());
        }

        $criterios = $this->criteriosDoBoletim($item);
        try {
            $salvoId = $this->boletimConfig->saveRule(
                (string) $item['nome'],
                $formulaFinal,
                $componentes,
                $regraId > 0 ? $regraId : null,
                $item['finalidade'] === 'complementar'
                    ? 'Boletim extra — não entra no histórico oficial.'
                    : 'Boletim oficial da vida escolar.',
                $this->model->encodeIds($item['materias_ids']),
                null,
                null,
                $this->model->encodeIds($item['series_ids']),
                $this->model->encodeIds($item['turmas_ids']),
                'boletim',
                $item['ano_letivo'] ? (int) $item['ano_letivo'] : null,
                null,
                (int) $item['vis_aluno'],
                (int) $item['vis_pais'],
                (int) $item['vis_coordenacao'],
                $criterios['round_mode'],
                $criterios['decimal_places'],
                null,
                null,
                $criterios['nota_minima_aprovacao'],
                1,
                null,
                (string) $item['finalidade']
            );
        } catch (Throwable $e) {
            error_log('BoletimCadastroService sincronizarDocumento: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível gravar o documento do boletim.'];
        }

        if ($salvoId > 0) {
            $this->model->definirRegraId($boletimId, $salvoId);
            $this->boletimConfig->setBoletimId($salvoId, $boletimId);
        }

        return ['success' => true, 'regra_id' => $salvoId];
    }

    /**
     * @return list<array{id:int,nome:string,finalidade:string,ano_letivo:?int}>
     */
    public function listarParaEventoNotas(): array
    {
        $out = [];
        $componentes = null;
        try {
            require_once dirname(__DIR__, 3) . '/Models/Education/ComponenteCurricular.php';
            $componentes = new \ComponenteCurricular();
        } catch (Throwable $e) {
            $componentes = null;
        }
        foreach ($this->model->listar(true) as $row) {
            $ids = is_array($row['materias_ids'] ?? null) ? $row['materias_ids'] : [];
            if ($componentes !== null) {
                $ids = $componentes->expandirIdsComFilhos($ids);
            }
            $criterios = $this->criteriosDoBoletim($row);
            $out[] = [
                'id' => (int) $row['id'],
                'nome' => (string) $row['nome'],
                'finalidade' => (string) $row['finalidade'],
                'ano_letivo' => $row['ano_letivo'] ? (int) $row['ano_letivo'] : null,
                'materias_ids' => $ids,
                'series_ids' => $row['series_ids'],
                'turmas_ids' => $row['turmas_ids'],
                'regra_academica_id' => $row['regra_academica_id'] ?? null,
                'nota_minima_aprovacao' => $criterios['nota_minima_aprovacao'],
                'round_mode' => $criterios['round_mode'],
                'decimal_places' => $criterios['decimal_places'],
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function normalizar(array $input): array
    {
        $regraAcademicaId = (int) ($input['regra_academica_id'] ?? 0);
        $data = [
            'nome' => trim((string) ($input['nome'] ?? '')),
            'finalidade' => (($input['finalidade'] ?? 'oficial') === 'complementar') ? 'complementar' : 'oficial',
            'ano_letivo' => (int) ($input['ano_letivo'] ?? date('Y')),
            'materias_ids' => $this->model->decodeIds($input['materias_ids'] ?? []),
            'series_ids' => $this->model->decodeIds($input['series_ids'] ?? []),
            'turmas_ids' => $this->model->decodeIds($input['turmas_ids'] ?? []),
            'regra_academica_id' => $regraAcademicaId > 0 ? $regraAcademicaId : null,
            'vis_aluno' => !empty($input['vis_aluno']) ? 1 : 0,
            'vis_pais' => !empty($input['vis_pais']) ? 1 : 0,
            'vis_coordenacao' => !empty($input['vis_coordenacao']) ? 1 : 0,
            'ativo' => !isset($input['ativo']) || !empty($input['ativo']) ? 1 : 0,
        ];
        $criterios = $this->criteriosDoBoletim($data);
        $data['nota_minima_aprovacao'] = $criterios['nota_minima_aprovacao'];
        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function validar(array $data): ?string
    {
        if ($data['nome'] === '') {
            return 'Informe o nome do boletim.';
        }
        $ano = (int) ($data['ano_letivo'] ?? 0);
        if ($ano < 2000 || $ano > 2100) {
            return 'Selecione um ano letivo válido.';
        }
        if ($data['materias_ids'] === []) {
            return 'Marque pelo menos uma matéria deste boletim.';
        }
        $regraAcadId = (int) ($data['regra_academica_id'] ?? 0);
        if ($regraAcadId > 0 && $this->carregarRegraAcademica($regraAcadId) === null) {
            return 'Regra de aprovação inválida. Cadastre-a em Acadêmico → Regras de Aprovação.';
        }
        return null;
    }

    /**
     * Mínima e arredondamento vêm da regra acadêmica vinculada (fallback 6 se não houver).
     *
     * @param array<string,mixed> $item
     * @return array{nota_minima_aprovacao:float,round_mode:string,decimal_places:int,regra:?array}
     */
    public function criteriosDoBoletim(array $item): array
    {
        $regra = $this->carregarRegraAcademica((int) ($item['regra_academica_id'] ?? 0));
        if ($regra !== null) {
            $round = strtolower(trim((string) ($regra['round_mode'] ?? 'none')));
            if (!in_array($round, ['none', 'half'], true)) {
                $round = 'none';
            }
            $minima = isset($regra['media_minima']) && is_numeric($regra['media_minima'])
                ? (float) $regra['media_minima']
                : 6.0;
            return [
                'nota_minima_aprovacao' => $minima,
                'round_mode' => $round,
                'decimal_places' => ((int) ($regra['decimal_places'] ?? 2) === 1) ? 1 : 2,
                'regra' => $regra,
            ];
        }
        return [
            'nota_minima_aprovacao' => 6.0,
            'round_mode' => 'none',
            'decimal_places' => 2,
            'regra' => null,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarRegrasAcademicas(?int $anoLetivo = null, ?int $incluirId = null): array
    {
        try {
            $model = new RegraAcademica();
            if (!$model->schemaPronto()) {
                return [];
            }
            $lista = $model->getAll([
                'ano_letivo' => $anoLetivo && $anoLetivo > 0 ? $anoLetivo : null,
                'ativo' => 1,
            ]);
            $incluirId = (int) $incluirId;
            if ($incluirId <= 0) {
                return $lista;
            }
            foreach ($lista as $r) {
                if ((int) ($r['id'] ?? 0) === $incluirId) {
                    return $lista;
                }
            }
            $extra = $this->carregarRegraAcademica($incluirId);
            if ($extra !== null) {
                $lista[] = $extra;
            }
            return $lista;
        } catch (Throwable $e) {
            error_log('BoletimCadastroService listarRegrasAcademicas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function carregarRegraAcademica(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $model = new RegraAcademica();
            $row = $model->findById($id);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function esqueletoDocumento(): array
    {
        $comps = [];
        foreach ([1, 2, 3, 4] as $bim) {
            $comps[] = [
                'codigo' => 'b' . $bim . '_media',
                'nome' => 'Média',
                'source_type' => 'nenhuma',
                'config' => ['layout_group' => 'b' . $bim, 'layout_type' => 'media'],
            ];
            $comps[] = [
                'codigo' => 'b' . $bim . '_faltas',
                'nome' => 'Faltas',
                'source_type' => 'nenhuma',
                'config' => ['layout_group' => 'b' . $bim, 'layout_type' => 'faltas'],
                'escala_max' => 999,
            ];
        }
        $comps[] = [
            'codigo' => 'media_final',
            'nome' => 'Média',
            'source_type' => 'nenhuma',
            'config' => ['layout_group' => 'final', 'layout_type' => 'media'],
        ];
        $comps[] = [
            'codigo' => 'rec_final',
            'nome' => 'Rec.',
            'source_type' => 'nenhuma',
            'config' => ['layout_group' => 'final', 'layout_type' => 'rec'],
        ];
        $comps[] = [
            'codigo' => 'faltas_final',
            'nome' => 'Faltas',
            'source_type' => 'nenhuma',
            'config' => ['layout_group' => 'final', 'layout_type' => 'faltas'],
            'escala_max' => 999,
        ];
        $comps[] = [
            'codigo' => 'resultado',
            'nome' => 'Resultado',
            'source_type' => 'nenhuma',
            'config' => ['layout_group' => 'final', 'layout_type' => 'resultado'],
        ];
        return $comps;
    }

    /**
     * @param list<array<string,mixed>> $componentes
     * @return list<array<string,mixed>>
     */
    private function normalizarComponentesParaSave(array $componentes): array
    {
        $out = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $nome = trim((string) ($c['nome'] ?? ''));
            $codigo = trim((string) ($c['codigo'] ?? ''));
            if ($nome === '' || $codigo === '') {
                continue;
            }
            $config = is_array($c['config'] ?? null) ? $c['config'] : [];
            $out[] = [
                'codigo' => $codigo,
                'nome' => $nome,
                'source_type' => (string) ($c['source_type'] ?? 'nenhuma'),
                'calc_type' => (string) ($c['calc_type'] ?? 'media'),
                'peso' => (float) ($c['peso'] ?? 1),
                'filtro_titulo' => '',
                'bloco_id' => null,
                'blocos_ids' => null,
                'config_json' => $config === [] ? null : json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'materia_id' => null,
                'materias_ids' => null,
                'materia_unica' => 0,
                'usar_percentual' => 0,
                'escala_max' => isset($c['escala_max']) ? (float) $c['escala_max'] : 10,
                'obrigatorio' => 0,
            ];
        }
        return $out;
    }

    /**
     * Modelo oficial que vale para a turma (série/turma do cadastro).
     *
     * @return array<string,mixed>|null
     */
    public function encontrarOficialParaTurma(int $turmaId, int $serieId, int $anoLetivo, ?int $preferidoId = null): ?array
    {
        if (!$this->model->tabelasProntas()) {
            return null;
        }
        $preferidoId = (int) $preferidoId;
        $melhor = null;
        $melhorPts = -1;
        foreach ($this->model->listar(true) as $row) {
            if (($row['finalidade'] ?? '') !== 'oficial') {
                continue;
            }
            if (!$this->modeloAtendeTurma($row, $turmaId, $serieId)) {
                continue;
            }
            $pts = 0;
            $id = (int) ($row['id'] ?? 0);
            $anoModelo = (int) ($row['ano_letivo'] ?? 0);
            if ($preferidoId > 0 && $id === $preferidoId) {
                $pts += 1000;
            }
            if ($anoLetivo > 0 && $anoModelo === $anoLetivo) {
                $pts += 100;
            }
            $turmas = array_map('intval', (array) ($row['turmas_ids'] ?? []));
            if ($turmaId > 0 && in_array($turmaId, $turmas, true)) {
                $pts += 20;
            }
            $series = array_map('intval', (array) ($row['series_ids'] ?? []));
            if ($serieId > 0 && in_array($serieId, $series, true)) {
                $pts += 10;
            }
            if ($pts > $melhorPts) {
                $melhorPts = $pts;
                $melhor = $row;
            }
        }

        return $melhor;
    }

    /**
     * Linhas do modelo na ordem do cadastro (Acadêmico → Modelo de Boletim).
     *
     * @param array<string,mixed> $boletim
     * @return list<array{materia_id:int,componente_nome:string,ordem:int}>
     */
    public function componentesParaFicha(array $boletim): array
    {
        $ids = [];
        foreach ((array) ($boletim['materias_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = 'm' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll(
                'SELECT id, nome FROM materias WHERE id IN (' . implode(',', $ph) . ')',
                $params
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $byId = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = (string) ($r['nome'] ?? 'Componente');
            }
        }
        $out = [];
        $ordem = 0;
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $ordem++;
            $out[] = [
                'materia_id' => $id,
                'componente_nome' => $byId[$id],
                'ordem' => $ordem,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function modeloAtendeTurma(array $row, int $turmaId, int $serieId): bool
    {
        $turmas = array_values(array_filter(array_map('intval', (array) ($row['turmas_ids'] ?? [])), static fn ($id) => $id > 0));
        $series = array_values(array_filter(array_map('intval', (array) ($row['series_ids'] ?? [])), static fn ($id) => $id > 0));
        if ($turmas === [] && $series === []) {
            return true;
        }
        if ($turmaId > 0 && in_array($turmaId, $turmas, true)) {
            return true;
        }
        if ($serieId > 0 && in_array($serieId, $series, true)) {
            return true;
        }

        return false;
    }
}
