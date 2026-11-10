<?php

namespace App\Modulos\Boletins\Services;


use App\Modulos\Boletins\Models\Boletim;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;
use App\Services\BoletimAssistenteWizard;
use App\Models\System\BoletimConfig;
use App\Core\Database;
use App\Core\PeriodoLetivo;
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
            $materiasParaEventos = array_map('intval', (array) ($data['materias_ids'] ?? []));
            try {
                $materiasParaEventos = (new \App\Models\Education\ComponenteCurricular())->expandirIdsComFilhos($materiasParaEventos);
            } catch (Throwable $e) {
                // mantém ids do modelo
            }
            $this->boletimConfig->sincronizarEscopoEventosNotasDoBoletim(
                $id,
                $materiasParaEventos,
                (array) ($data['series_ids'] ?? []),
                (array) ($data['turmas_ids'] ?? [])
            );
            $this->sincronizarDocumento($id);
            return ['success' => true, 'id' => $id];
        }

        $novoId = $this->model->criar($data);
        $this->sincronizarDocumento($novoId);
        return ['success' => true, 'id' => $novoId];
    }

    /**
     * Some da listagem e do detalhe do aluno. Desativa layouts/regras ligados ao modelo.
     *
     * @return array{success:bool,error?:string}
     */
    public function excluir(int $id): array
    {
        $item = $this->model->findById($id);
        if ($item === null) {
            return ['success' => false, 'error' => 'Boletim não encontrado.'];
        }
        if (empty($item['excluido_em'])) {
            if (!$this->model->excluir($id)) {
                return ['success' => false, 'error' => 'Não foi possível ocultar o modelo.'];
            }
        }
        // Idempotente: limpa layout/regra órfãos mesmo se o modelo já estava excluído.
        $this->desativarDocumentosDoModelo($id, $item);

        return ['success' => true];
    }

    /**
     * Layout de impressão e regras do cadastro deixam de valer no detalhe do aluno.
     *
     * @param array<string,mixed> $item
     */
    private function desativarDocumentosDoModelo(int $boletimId, array $item): void
    {
        try {
            $this->boletimConfig->desativarRegrasDoBoletim($boletimId);
        } catch (Throwable $e) {
            error_log('BoletimCadastroService desativar regras #' . $boletimId . ': ' . $e->getMessage());
        }

        $regraId = (int) ($item['regra_id'] ?? 0);
        if ($regraId > 0) {
            try {
                $this->boletimConfig->deactivateRule($regraId);
            } catch (Throwable $e) {
                error_log('BoletimCadastroService desativar regra #' . $regraId . ': ' . $e->getMessage());
            }
        }

        $nome = trim((string) ($item['nome'] ?? ''));
        $ano = (int) ($item['ano_letivo'] ?? 0);
        $finalidade = (($item['finalidade'] ?? 'oficial') === 'complementar') ? 'complementar' : 'oficial';
        if ($nome === '' || $ano <= 0) {
            return;
        }
        try {
            if (class_exists(\App\Modulos\EventosAvaliativos\Models\BoletimLayout::class)) {
                (new \App\Modulos\EventosAvaliativos\Models\BoletimLayout())
                    ->desativarPorCadastro($nome, $ano, $finalidade);
            }
        } catch (Throwable $e) {
            error_log('BoletimCadastroService desativar layout do modelo #' . $boletimId . ': ' . $e->getMessage());
        }
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
            $componentes = new \App\Models\Education\ComponenteCurricular();
        } catch (Throwable $e) {
            $componentes = null;
        }
        $regraModel = null;
        try {
            $regraModel = new RegraAcademica();
        } catch (Throwable $e) {
            $regraModel = null;
        }
        foreach ($this->model->listar(true) as $row) {
            $ids = [];
            $regraId = (int) ($row['regra_academica_id'] ?? 0);
            if ($regraModel !== null && $regraId > 0) {
                try {
                    $ids = $regraModel->listarMateriasIds($regraId);
                } catch (Throwable $e) {
                    $ids = [];
                }
            }
            if ($ids === []) {
                $ids = is_array($row['materias_ids'] ?? null) ? $row['materias_ids'] : [];
            }
            if ($componentes !== null) {
                $ids = $componentes->expandirIdsComFilhos($ids);
            }
            $criterios = $this->criteriosDoBoletim($row);
            $regra = is_array($criterios['regra'] ?? null) ? $criterios['regra'] : null;
            $out[] = [
                'id' => (int) $row['id'],
                'nome' => (string) $row['nome'],
                'finalidade' => (string) $row['finalidade'],
                'ano_letivo' => $row['ano_letivo'] ? (int) $row['ano_letivo'] : null,
                'materias_ids' => $ids,
                'series_ids' => $row['series_ids'],
                'turmas_ids' => $row['turmas_ids'],
                'regra_academica_id' => $row['regra_academica_id'] ?? null,
                'regra_nome' => $regra !== null ? trim((string) ($regra['nome'] ?? '')) : '',
                'minima_origem' => $regra !== null ? 'regra' : 'padrao',
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
        $regraModel = new RegraAcademica();
        if ($regraModel->boletimPronto() && $regraAcademicaId > 0) {
            $pacote = $regraModel->pacoteBoletim($regraAcademicaId);
            if ($pacote !== null) {
                $input = array_merge($input, $pacote);
                $input['regra_academica_id'] = $regraAcademicaId;
                $input['vis_coordenacao'] = 1;
            }
        }
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
        $regraAcadId = (int) ($data['regra_academica_id'] ?? 0);
        $soRegra = (new RegraAcademica())->boletimPronto();
        if ($soRegra && $regraAcadId <= 0) {
            return 'Escolha a regra de aprovação.';
        }
        if ($data['nome'] === '') {
            return $soRegra ? 'A regra de aprovação precisa de um nome.' : 'Informe o nome do boletim.';
        }
        $ano = (int) ($data['ano_letivo'] ?? 0);
        if ($ano < 2000 || $ano > 2100) {
            return $soRegra ? 'A regra de aprovação precisa de um ano letivo.' : 'Selecione um ano letivo válido.';
        }
        if ($data['materias_ids'] === []) {
            return $soRegra ? 'Marque as disciplinas na regra de aprovação.' : 'Marque pelo menos uma matéria deste boletim.';
        }
        if ($regraAcadId > 0 && $this->carregarRegraAcademica($regraAcadId) === null) {
            return 'Regra de aprovação inválida. Cadastre-a em Acadêmico → Regras de Aprovação.';
        }
        return null;
    }

    /**
     * Períodos do menu "Períodos do boletim": bimestres/trimestres + recuperações
     * da regra (R1/R2/RF etc.), na mesma ordem da tela de Eventos Avaliativos.
     *
     * @param array<string,mixed> $item
     * @param list<array<string,mixed>> $eventosNotas
     * @return list<array{bimestre:int,regra_id:int,ano_letivo:int,rotulo:string}>
     */
    public function periodosDoMenuNotas(array $item, array $eventosNotas): array
    {
        $ano = (int) ($item['ano_letivo'] ?? 0);
        if ($ano <= 0) {
            $ano = (int) date('Y');
        }
        $porCodigo = [];
        foreach ($eventosNotas as $evento) {
            if (!is_array($evento)) {
                continue;
            }
            $codigo = (int) ($evento['bimestre'] ?? 0);
            $regraId = (int) ($evento['id'] ?? 0);
            if ($codigo <= 0 || $regraId <= 0) {
                continue;
            }
            // A consulta ordena por id: o último do mesmo código fica no menu.
            $porCodigo[$codigo] = $regraId;
        }
        // Sem evento de notas ainda: mantém o fluxo "Configurar primeiro período".
        if ($porCodigo === []) {
            return [];
        }

        $rotulosRegime = PeriodoLetivo::doAno($ano)['rotulos'] ?? [];
        if (!is_array($rotulosRegime) || $rotulosRegime === []) {
            $rotulosRegime = [1 => '1º período', 2 => '2º período', 3 => '3º período', 4 => '4º período'];
        }

        $pares = [];
        $ciclos = [];
        $final = null;
        $regraAcademicaId = (int) ($item['regra_academica_id'] ?? 0);
        if ($regraAcademicaId > 0) {
            try {
                $rec = new RecuperacaoConfig();
                if ($rec->tabelaPronta()) {
                    foreach ($rec->slotsParaTela($ano, [], $regraAcademicaId) as $slot) {
                        if (!is_array($slot)) {
                            continue;
                        }
                        $codigoSlot = (int) ($slot['codigo'] ?? 0);
                        $rotuloSlot = trim((string) ($slot['rotulo'] ?? 'Recuperação'));
                        if ($codigoSlot === RecuperacaoConfig::CODIGO_FINAL) {
                            $final = ['codigo' => $codigoSlot, 'rotulo' => $rotuloSlot !== '' ? $rotuloSlot : 'Prova Final'];
                            continue;
                        }
                        if ($codigoSlot > RecuperacaoConfig::CODIGO_PAR_BASE
                            && $codigoSlot < RecuperacaoConfig::CODIGO_FINAL) {
                            $pares[$codigoSlot - RecuperacaoConfig::CODIGO_PAR_BASE] = [
                                'codigo' => $codigoSlot,
                                'rotulo' => $rotuloSlot !== '' ? $rotuloSlot : 'Recuperação Semestral',
                            ];
                            continue;
                        }
                        if ($codigoSlot > RecuperacaoConfig::CODIGO_CICLO_BASE
                            && $codigoSlot < RecuperacaoConfig::CODIGO_PAR_BASE) {
                            $ciclos[$codigoSlot - RecuperacaoConfig::CODIGO_CICLO_BASE] = [
                                'codigo' => $codigoSlot,
                                'rotulo' => $rotuloSlot !== '' ? $rotuloSlot : 'Recuperação',
                            ];
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('BoletimCadastroService periodosDoMenuNotas: ' . $e->getMessage());
            }
        }

        $montar = static function (int $codigo, string $rotulo) use ($porCodigo, $ano): array {
            return [
                'bimestre' => $codigo,
                'regra_id' => (int) ($porCodigo[$codigo] ?? 0),
                'ano_letivo' => $ano,
                'rotulo' => $rotulo,
            ];
        };

        $out = [];
        $vistos = [];
        $numeros = [];
        foreach ($rotulosRegime as $numero => $rotulo) {
            $numeros[] = (int) $numero;
        }
        sort($numeros);
        $total = count($numeros);
        // Mesma agrupagem da tela Eventos Avaliativos: pares de períodos + slot do meio.
        for ($indiceGrupo = 0; $indiceGrupo < $total; $indiceGrupo += 2) {
            $fatia = array_slice($numeros, $indiceGrupo, 2);
            foreach ($fatia as $numero) {
                $rotuloPeriodo = trim((string) ($rotulosRegime[$numero] ?? ''));
                if ($rotuloPeriodo === '') {
                    $rotuloPeriodo = PeriodoLetivo::rotulo($ano, $numero) ?: ($numero . 'º período');
                }
                $out[] = $montar($numero, $rotuloPeriodo);
                $vistos[$numero] = true;
                if (isset($ciclos[$numero])) {
                    $out[] = $montar((int) $ciclos[$numero]['codigo'], (string) $ciclos[$numero]['rotulo']);
                    $vistos[(int) $ciclos[$numero]['codigo']] = true;
                }
            }
            $indicePar = (int) floor($indiceGrupo / 2) + 1;
            if (isset($pares[$indicePar])) {
                $out[] = $montar((int) $pares[$indicePar]['codigo'], (string) $pares[$indicePar]['rotulo']);
                $vistos[(int) $pares[$indicePar]['codigo']] = true;
            }
        }
        if ($final !== null) {
            $out[] = $montar((int) $final['codigo'], (string) $final['rotulo']);
            $vistos[(int) $final['codigo']] = true;
        }

        // Configurações já gravadas fora da regra (órfãs) continuam no menu.
        ksort($porCodigo);
        foreach ($porCodigo as $codigo => $regraId) {
            $codigo = (int) $codigo;
            if ($codigo <= 0 || isset($vistos[$codigo])) {
                continue;
            }
            $rotuloExtra = PeriodoLetivo::rotulo($ano, $codigo);
            if ($rotuloExtra === '' || $codigo >= 60) {
                $rotuloExtra = $codigo === RecuperacaoConfig::CODIGO_FINAL
                    ? 'Prova Final'
                    : ($codigo > RecuperacaoConfig::CODIGO_PAR_BASE
                        ? 'Recuperação Semestral'
                        : ($codigo > RecuperacaoConfig::CODIGO_CICLO_BASE ? 'Recuperação' : ('Período ' . $codigo)));
            }
            $out[] = $montar($codigo, $rotuloExtra);
        }

        return $out;
    }

    /**
     * Mínima e arredondamento vêm da regra acadêmica vinculada (fallback 6 se não houver).
     *
     * @param array<string,mixed> $item
     * @return array{nota_minima_aprovacao:float,round_mode:string,decimal_places:int,escopo_arredondamento:string,regra:?array,boletim_id?:int,minima_origem?:string,regra_nome?:string}
     */
    public function criteriosDoBoletim(array $item): array
    {
        $regra = $this->carregarRegraAcademica((int) ($item['regra_academica_id'] ?? 0));
        if ($regra !== null) {
            $round = strtolower(trim((string) ($regra['round_mode'] ?? 'none')));
            if (!in_array($round, ['none', 'half', 'places'], true)) {
                $round = 'none';
            }
            $escopo = strtolower(trim((string) ($regra['escopo_arredondamento'] ?? 'medias')));
            if (!isset(RegraAcademica::ESCOPOS_ARREDONDAMENTO[$escopo])) {
                $escopo = 'medias';
            }
            $minima = isset($regra['media_minima']) && is_numeric($regra['media_minima'])
                ? (float) $regra['media_minima']
                : 6.0;
            return [
                'nota_minima_aprovacao' => $minima,
                'round_mode' => $round,
                'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais(
                    $regra['decimal_places'] ?? 2
                ),
                'escopo_arredondamento' => $escopo,
                'regra' => $regra,
                'boletim_id' => (int) ($item['id'] ?? 0),
                'minima_origem' => 'regra',
                'regra_nome' => trim((string) ($regra['nome'] ?? '')),
            ];
        }
        return [
            'nota_minima_aprovacao' => 6.0,
            'round_mode' => 'none',
            'decimal_places' => 2,
            'escopo_arredondamento' => 'medias',
            'regra' => null,
            'boletim_id' => (int) ($item['id'] ?? 0),
            'minima_origem' => 'padrao',
            'regra_nome' => '',
        ];
    }

    /**
     * Critérios da regra de aprovação para o documento/prévia do boletim.
     * Quadro de impressão (sem modelo) também precisa dessas casas/arredondamento/mínima.
     *
     * @param list<int> $seriesIds
     * @return array{nota_minima_aprovacao:float,round_mode:string,decimal_places:int,escopo_arredondamento:string,regra:?array,boletim_id:int,minima_origem:string,regra_nome:string}
     */
    public function criteriosEfetivosParaDocumento(
        ?int $boletimId = null,
        ?int $anoLetivo = null,
        array $seriesIds = [],
        string $finalidade = 'oficial'
    ): array {
        $padrao = [
            'nota_minima_aprovacao' => 6.0,
            'round_mode' => 'none',
            'decimal_places' => 2,
            'escopo_arredondamento' => 'medias',
            'regra' => null,
            'boletim_id' => 0,
            'minima_origem' => 'padrao',
            'regra_nome' => '',
        ];
        $boletimId = (int) ($boletimId ?? 0);
        $item = null;
        if ($boletimId > 0) {
            $item = $this->model->findById($boletimId);
            if (!is_array($item) || !empty($item['excluido_em'])) {
                $item = null;
            }
        }
        if ($item === null) {
            $item = $this->encontrarBoletimParaCriterios($anoLetivo, $seriesIds, $finalidade);
        }
        if ($item === null) {
            return $padrao;
        }
        $criterios = $this->criteriosDoBoletim($item);
        $criterios['boletim_id'] = (int) ($item['id'] ?? 0);
        $criterios['minima_origem'] = ($criterios['regra'] ?? null) !== null ? 'regra' : 'padrao';
        $criterios['regra_nome'] = is_array($criterios['regra'] ?? null)
            ? trim((string) ($criterios['regra']['nome'] ?? ''))
            : '';

        return $criterios;
    }

    /**
     * Cadastro oficial do ano (com regra de aprovação) para critérios do quadro de impressão.
     *
     * @param list<int> $seriesIds
     * @return array<string,mixed>|null
     */
    public function encontrarBoletimParaCriterios(
        ?int $anoLetivo = null,
        array $seriesIds = [],
        string $finalidade = 'oficial'
    ): ?array {
        $finalidade = $finalidade === 'complementar' ? 'complementar' : 'oficial';
        $anoLetivo = $anoLetivo && $anoLetivo >= 2000 ? (int) $anoLetivo : null;
        $seriesIds = array_values(array_unique(array_filter(array_map('intval', $seriesIds), static function ($id) {
            return $id > 0;
        })));
        $candidatos = [];
        foreach ($this->model->listar(true) as $row) {
            if (!is_array($row) || !empty($row['excluido_em'])) {
                continue;
            }
            if (((string) ($row['finalidade'] ?? 'oficial')) !== $finalidade) {
                continue;
            }
            // Só cadastro amarrado à regra de aprovação — evita chute sem critério.
            if ((int) ($row['regra_academica_id'] ?? 0) <= 0) {
                continue;
            }
            $anoRow = !empty($row['ano_letivo']) ? (int) $row['ano_letivo'] : 0;
            if ($anoLetivo !== null && $anoRow > 0 && $anoRow !== $anoLetivo) {
                continue;
            }
            $score = 0;
            if ($anoLetivo !== null && $anoRow === $anoLetivo) {
                $score += 100;
            } elseif ($anoLetivo !== null && $anoRow <= 0) {
                $score += 10; // cadastro “qualquer ano”
            }
            $seriesRow = is_array($row['series_ids'] ?? null) ? $row['series_ids'] : $this->model->decodeIds($row['series_ids'] ?? []);
            $seriesRow = array_map('intval', (array) $seriesRow);
            if ($seriesIds !== [] && $seriesRow !== []) {
                if (array_intersect($seriesIds, $seriesRow) !== []) {
                    $score += 20;
                } else {
                    continue;
                }
            }
            $candidatos[] = [
                'score' => $score,
                'id' => (int) ($row['id'] ?? 0),
                'row' => $row,
            ];
        }
        if ($candidatos === []) {
            return null;
        }
        usort($candidatos, static function (array $a, array $b): int {
            $cmp = $b['score'] <=> $a['score'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['id'] <=> $b['id'];
        });

        return $candidatos[0]['row'];
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
        return $this->encontrarModeloParaTurma($turmaId, $serieId, $anoLetivo, 'oficial', $preferidoId);
    }

    /**
     * Modelo ativo (oficial ou complementar/extra) que melhor atende a turma.
     *
     * @param 'oficial'|'complementar'|null $finalidade null = qualquer
     * @return array<string,mixed>|null
     */
    public function encontrarModeloParaTurma(
        int $turmaId,
        int $serieId,
        int $anoLetivo,
        ?string $finalidade = null,
        ?int $preferidoId = null
    ): ?array {
        if (!$this->model->tabelasProntas()) {
            return null;
        }
        $finalidade = $finalidade !== null
            ? (strtolower(trim($finalidade)) === 'complementar' ? 'complementar' : 'oficial')
            : null;
        $preferidoId = (int) $preferidoId;
        $melhor = null;
        $melhorPts = -1;
        foreach ($this->model->listar(true) as $row) {
            $finRow = (($row['finalidade'] ?? 'oficial') === 'complementar') ? 'complementar' : 'oficial';
            if ($finalidade !== null && $finRow !== $finalidade) {
                continue;
            }
            $anoModelo = (int) ($row['ano_letivo'] ?? 0);
            // Modelo de outro ano não pode aparecer no detalhe do aluno daquele ano.
            if ($anoLetivo > 0 && $anoModelo > 0 && $anoModelo !== $anoLetivo) {
                continue;
            }
            if (!$this->modeloAtendeTurma($row, $turmaId, $serieId)) {
                continue;
            }
            $pts = 0;
            $id = (int) ($row['id'] ?? 0);
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
            if ($finRow === 'oficial') {
                $pts += 1;
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
        // Matérias vêm da regra de aprovação; o JSON do modelo é espelho/legado.
        $ids = [];
        $regraId = (int) ($boletim['regra_academica_id'] ?? 0);
        if ($regraId > 0) {
            try {
                $ids = (new \App\Modulos\RegrasAcademicas\Models\RegraAcademica())->listarMateriasIds($regraId);
            } catch (Throwable $e) {
                $ids = [];
            }
        }
        if ($ids === []) {
            foreach ((array) ($boletim['materias_ids'] ?? []) as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids !== []) {
            try {
                $ids = (new \App\Models\Education\ComponenteCurricular())->expandirIdsComFilhos($ids);
            } catch (Throwable $e) {
                // mantém ids crus
            }
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
        // Ficha mostra linhas lançáveis — tira rótulo-pai se os filhos já entraram.
        try {
            $paisComFilho = (new \App\Models\Education\ComponenteCurricular())->idsComFilhos();
            $ids = array_values(array_filter($ids, static fn ($id) => empty($paisComFilho[$id])));
        } catch (Throwable $e) {
            // mantém lista expandida
        }
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

if (!class_exists('BoletimCadastroService', false) && class_exists(__NAMESPACE__ . '\\BoletimCadastroService', false)) {
    class_alias(__NAMESPACE__ . '\\BoletimCadastroService', 'BoletimCadastroService');
}
