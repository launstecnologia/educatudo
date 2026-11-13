<?php

namespace App\Modulos\Boletins\Services;
/**
 * Wizard pedagógico do Assistente de Boletim.
 * Traduz escolhas em linguagem de coordenação → rascunho técnico (sem OpenAI).
 */


use App\Models\Education\ComponenteCurricular;
use App\Modulos\Fechamento\Services\FechamentoGates;
use App\Modulos\GruposRegrasNotas\Services\GrupoRegrasNotasService;
use App\Core\PeriodoLetivo;

class BoletimAssistenteWizard
{
    public const PASSOS = [
        'inicio',
        'identidade',
        'pecas',
        'formula',
        'publico',
        'revisar',
    ];

    public const PAPEIS = ['media', 'depois', 'so_melhora', 'substitui', 'exibe'];

    private BoletimAssistenteFerramentas $ferramentas;

    public function __construct(?BoletimAssistenteFerramentas $ferramentas = null)
    {
        $this->ferramentas = $ferramentas ?? new BoletimAssistenteFerramentas();
    }

    public function ferramentas(): BoletimAssistenteFerramentas
    {
        return $this->ferramentas;
    }

    /**
     * @return array{passos:list<string>,modelos:list<array>,formulas:list<array>,regras:list<array>,series:list<array>,tipos_avaliacao:list<array>}
     */
    public function catalogo(): array
    {
        $regras = [];
        try {
            foreach ($this->ferramentas->listarRegras(200) as $r) {
                $regras[] = [
                    'id' => (int) ($r['id'] ?? 0),
                    'nome' => (string) ($r['nome'] ?? ''),
                    'codigo' => isset($r['codigo']) ? (string) $r['codigo'] : null,
                    'bimestre' => isset($r['bimestre']) ? (int) $r['bimestre'] : null,
                    'ano_letivo' => isset($r['ano_letivo']) ? (int) $r['ano_letivo'] : null,
                    'exibir_em' => isset($r['exibir_em']) ? (string) $r['exibir_em'] : null,
                    'finalidade' => strtolower(trim((string) ($r['finalidade'] ?? 'oficial'))) === 'complementar'
                        ? 'complementar'
                        : 'oficial',
                    'formula_final' => isset($r['formula_final']) ? (string) $r['formula_final'] : null,
                ];
            }
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo regras: ' . $e->getMessage());
        }

        $series = [];
        $turmas = [];
        try {
            $turmasRaw = $this->ferramentas->listarTurmas(300);
            $porSerie = [];
            foreach ($turmasRaw as $t) {
                $sid = (int) ($t['serie_id'] ?? 0);
                if ($sid > 0 && !isset($porSerie[$sid])) {
                    $porSerie[$sid] = [
                        'id' => $sid,
                        'nome' => trim((string) ($t['serie_nome'] ?? ('Série #' . $sid))),
                        'curso_nome' => trim((string) ($t['curso_nome'] ?? '')),
                    ];
                }
                $turmas[] = [
                    'id' => (int) ($t['id'] ?? 0),
                    'nome' => trim((string) ($t['nome'] ?? '')),
                    'serie_id' => isset($t['serie_id']) ? (int) $t['serie_id'] : null,
                    'serie_nome' => isset($t['serie_nome']) ? trim((string) $t['serie_nome']) : null,
                    'ano_letivo' => isset($t['ano_letivo']) ? (int) $t['ano_letivo'] : null,
                ];
            }
            $series = array_values($porSerie);
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo turmas: ' . $e->getMessage());
        }

        $materias = [];
        try {
            foreach ($this->ferramentas->listarMaterias(200) as $m) {
                $materias[] = [
                    'id' => (int) ($m['id'] ?? 0),
                    'nome' => (string) ($m['nome'] ?? ''),
                    'ordem' => (int) ($m['ordem'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo materias: ' . $e->getMessage());
        }

        $alunos = [];
        try {
            $alunos = $this->ferramentas->listarAlunos(10000);
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo alunos: ' . $e->getMessage());
        }

        $jornadas = [];
        try {
            $jornadas = $this->ferramentas->listarJornadasDoBoletim();
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo jornadas: ' . $e->getMessage());
        }

        $tipos = [];
        try {
            $tipos = $this->ferramentas->listarTiposAvaliacao();
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo tipos: ' . $e->getMessage());
        }

        $eventosProva = [];
        try {
            $eventosProva = $this->ferramentas->listarEventosProva(null, 4000, $this->ferramentas->anoLetivoPadrao());
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo eventos prova: ' . $e->getMessage());
        }

        $faltasEventos = [];
        try {
            $faltasEventos = $this->ferramentas->listarFaltasEventos(300);
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo faltas: ' . $e->getMessage());
        }

        $anoLetivoAtivo = $this->ferramentas->anoLetivoAtivo();
        $escalaMaxPadrao = $this->escalaMaxPadraoDoAno($anoLetivoAtivo);

        return [
            'passos' => self::PASSOS,
            'modelos' => $this->modelosParaCatalogo(),
            'formulas' => $this->formulasPresetMeta(),
            'regras' => $regras,
            'series' => $series,
            'turmas' => $turmas,
            'materias' => $materias,
            'alunos' => $alunos,
            'jornadas' => $jornadas,
            'tipos_avaliacao' => $tipos,
            'eventos_prova' => $eventosProva,
            'faltas_eventos' => $faltasEventos,
            'agrupamentos' => $this->catalogoAgrupamentos(),
            'familias_componentes' => $this->catalogoFamiliasComponentes(),
            'boletins' => $this->catalogoBoletins(),
            'quadros_notas' => $this->catalogoQuadrosNotas(),
            'modulo_jornadas' => \App\Core\LayoutHelper::isModuleEnabled('jornadas'),
            'modulo_eventos_avaliativos' => \App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos'),
            'eventos_avaliativos' => \App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos')
                ? $this->ferramentas->listarEventosAvaliativosParaBoletim()
                : [],
            'pecas' => $this->pecasMeta($tipos),
            'papeis' => self::papeisMeta(),
            'ano_letivo_ativo' => $anoLetivoAtivo,
            'escala_max_padrao' => $escalaMaxPadrao,
            'periodos_por_ano' => $this->catalogoPeriodos(),
        ];
    }

    /**
     * @return array<string, array{tipo:string,quantidade:int,rotulo_campo:string,rotulo_campo_plural:string,rotulos:array<string,string>}>
     */
    private function catalogoPeriodos(): array
    {
        try {
            $mapa = PeriodoLetivo::mapaPorAno();
        } catch (\Throwable $e) {
            $mapa = [(int) date('Y') => PeriodoLetivo::doAno((int) date('Y'))];
        }
        $out = [];
        foreach ($mapa as $ano => $info) {
            $rotulos = [];
            foreach ((array) ($info['rotulos'] ?? []) as $num => $lab) {
                $rotulos[(string) $num] = (string) $lab;
            }
            $out[(string) $ano] = [
                'tipo' => (string) ($info['tipo'] ?? PeriodoLetivo::tipoPadrao()),
                'quantidade' => (int) ($info['quantidade'] ?? 4),
                'rotulo_campo' => (string) ($info['rotulo_campo'] ?? 'Bimestre'),
                'rotulo_campo_plural' => (string) ($info['rotulo_campo_plural'] ?? 'Bimestres'),
                'rotulos' => $rotulos,
            ];
        }

        return $out;
    }

    private function nomePadraoEvento(int $ano, int $bimestre): string
    {
        $info = PeriodoLetivo::doAno($ano > 0 ? $ano : (int) date('Y'));
        if ($bimestre <= 0) {
            return sprintf('Notas — %d', (int) $info['ano']);
        }
        $adjetivo = match ((string) $info['tipo']) {
            'trimestre' => 'Trimestrais',
            'semestre' => 'Semestrais',
            'etapa_unica' => '',
            default => 'Bimestrais',
        };
        $campo = strtolower((string) $info['rotulo_campo']);
        if ($adjetivo === '') {
            return sprintf('Notas — %d', (int) $info['ano']);
        }

        return sprintf('Notas %s — %dº %s %d', $adjetivo, $bimestre, $campo, (int) $info['ano']);
    }

    /**
     * O nome sugerido acompanha ano, período e regime.
     * Nome digitado ou já personalizado num evento salvo permanece.
     *
     * @param array<string,mixed> $estado
     */
    private function aplicarNomeAutomatico(array &$estado): void
    {
        $flag = $estado['nome_automatico'] ?? null;
        $automatico = $flag === true || $flag === 1 || $flag === '1';
        if (!$automatico) {
            if ($flag === false || $flag === 0 || $flag === '0') {
                $estado['nome_automatico'] = false;
            } else {
                unset($estado['nome_automatico']);
            }

            return;
        }
        $estado['nome_automatico'] = true;
        if ((string) ($estado['origem'] ?? '') === 'clonar') {
            return;
        }
        $estado['nome'] = $this->nomePadraoEvento((int) ($estado['ano_letivo'] ?? 0), (int) ($estado['bimestre'] ?? 0));
    }

    /**
     * @return list<array{id:int,nome:string,finalidade:string,ano_letivo:?int,escala_max:float}>
     */
    private function catalogoBoletins(): array
    {
        $path = __DIR__ . '/BoletimCadastroService.php';
        if (!is_file($path)) {
            return [];
        }
        require_once $path;
        try {
            $boletins = (new \App\Modulos\Boletins\Services\BoletimCadastroService())->listarParaEventoNotas();
            foreach ($boletins as &$boletim) {
                if (!is_array($boletim)) {
                    continue;
                }
                $boletim['escala_max'] = $this->normalizarEscalaMaxima($boletim['escala_max'] ?? null, 10.0);
            }
            unset($boletim);

            return $boletins;
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo boletins: ' . $e->getMessage());
            return [];
        }
    }

    private function escalaMaxPadraoDoAno(int $ano): float
    {
        $ano = $ano > 0 ? $ano : $this->ferramentas->anoLetivoPadrao();
        try {
            $service = new \App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService();
            $escala = $service->escalaMaximaPadrao($ano, 0);

            return $this->normalizarEscalaMaxima($escala, 10.0);
        } catch (\Throwable $e) {
            return 10.0;
        }
    }

    /**
     * @param mixed $valor
     */
    private function normalizarEscalaMaxima($valor, float $fallback = 10.0): float
    {
        $escala = is_numeric($valor) ? round((float) $valor, 2) : $fallback;
        if ($escala < 0.01) {
            $escala = $fallback;
        }
        if ($escala > 999.0) {
            return 999.0;
        }

        return $escala;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buscarBoletimCatalogo(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        foreach ($this->catalogoBoletins() as $boletim) {
            if ((int) ($boletim['id'] ?? 0) === $id) {
                return $boletim;
            }
        }

        return null;
    }

    /**
     * Sem boletim e sem regra aplicável, a mínima do rascunho novo é 6,0.
     * Modelo vinculado usa a média da regra de aprovação daquele boletim.
     * Evento já salvo e clonagem mantêm o número que o cálculo já usa.
     *
     * @param array<string,mixed> $estado
     */
    private function aplicarMinimaResolvida(array &$estado): void
    {
        $boletimId = (int) ($estado['boletim_id'] ?? 0);
        $editando = ($estado['modo'] ?? '') === 'editar' && (int) ($estado['regra_id'] ?? 0) > 0;
        $clonando = ($estado['origem'] ?? '') === 'clonar';

        if ($boletimId > 0) {
            $boletim = $this->buscarBoletimCatalogo($boletimId);
            if (is_array($boletim)) {
                $origem = (string) ($boletim['minima_origem'] ?? 'padrao') === 'regra' ? 'regra' : 'padrao';
                $estado['nota_minima_aprovacao'] = (float) ($boletim['nota_minima_aprovacao'] ?? 6);
                $estado['nota_minima_origem'] = $origem;
                $estado['nota_minima_regra_nome'] = $origem === 'regra' ? (string) ($boletim['regra_nome'] ?? '') : '';
                return;
            }
        }

        if ($editando || $clonando) {
            $this->rotularMinimaExistente($estado, $boletimId);
            return;
        }

        $origem = (string) ($estado['nota_minima_origem'] ?? '');
        $valor = $estado['nota_minima_aprovacao'] ?? null;
        $vazio = $valor === null || $valor === '';
        $legadoSete = $origem === '' && !$vazio && abs((float) $valor - 7.0) < 0.001;
        $explicito = !$vazio
            && abs((float) $valor - 6.0) >= 0.001
            && abs((float) $valor - 7.0) >= 0.001;
        if ($explicito && $origem !== 'regra') {
            $estado['nota_minima_origem'] = 'evento';
            $estado['nota_minima_regra_nome'] = '';
            return;
        }
        if ($origem === 'padrao' || $origem === '' || $vazio || $legadoSete) {
            $estado['nota_minima_aprovacao'] = 6.0;
            $estado['nota_minima_origem'] = 'padrao';
            $estado['nota_minima_regra_nome'] = '';
        }
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function rotularMinimaExistente(array &$estado, int $boletimId): void
    {
        if (!isset($estado['nota_minima_aprovacao']) || $estado['nota_minima_aprovacao'] === '' || $estado['nota_minima_aprovacao'] === null) {
            $estado['nota_minima_aprovacao'] = 6.0;
            $estado['nota_minima_origem'] = 'padrao';
            $estado['nota_minima_regra_nome'] = '';
            return;
        }

        $origem = (string) ($estado['nota_minima_origem'] ?? '');
        if ($boletimId > 0 && $origem !== 'evento') {
            $boletim = $this->buscarBoletimCatalogo($boletimId);
            if (is_array($boletim) && (string) ($boletim['minima_origem'] ?? '') === 'regra') {
                $daRegra = (float) ($boletim['nota_minima_aprovacao'] ?? 0);
                if (abs((float) $estado['nota_minima_aprovacao'] - $daRegra) < 0.001) {
                    $estado['nota_minima_origem'] = 'regra';
                    $estado['nota_minima_regra_nome'] = (string) ($boletim['regra_nome'] ?? '');
                    return;
                }
            }
        }
        if ($origem === 'padrao' || ($boletimId <= 0 && abs((float) $estado['nota_minima_aprovacao'] - 6.0) < 0.001 && $origem !== 'evento')) {
            $estado['nota_minima_origem'] = 'padrao';
            $estado['nota_minima_regra_nome'] = '';
            return;
        }
        if (!in_array($origem, ['regra', 'evento'], true)) {
            $estado['nota_minima_origem'] = 'evento';
            $estado['nota_minima_regra_nome'] = '';
        }
    }

    private function formatarMinima(float $valor): string
    {
        $casas = abs($valor - round($valor, 1)) < 0.001 ? 1 : 2;

        return number_format($valor, $casas, ',', '');
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    private function sufixoOrigemMinima(array $rascunho): string
    {
        $origem = (string) ($rascunho['nota_minima_origem'] ?? '');
        $nome = trim((string) ($rascunho['nota_minima_regra_nome'] ?? ''));
        if ($origem === 'regra') {
            return $nome !== '' ? ' (regra vinculada: ' . $nome . ')' : ' (regra vinculada)';
        }
        if ($origem === 'evento') {
            return ' (valor deste evento)';
        }
        if ($origem === 'padrao') {
            return ' (padrão)';
        }

        return '';
    }

    /**
     * Clonagem usa a mínima do evento de origem, salvo quando o rascunho já
     * apontou uma regra vinculada.
     *
     * @param array<string,mixed> $estado
     * @param mixed $minimaFonte
     */
    private function minimaDaClonagem(array $estado, $minimaFonte): float
    {
        if (($estado['nota_minima_origem'] ?? '') === 'regra'
            && isset($estado['nota_minima_aprovacao'])
            && $estado['nota_minima_aprovacao'] !== ''
        ) {
            return (float) $estado['nota_minima_aprovacao'];
        }
        if ($minimaFonte !== null && $minimaFonte !== '' && is_numeric($minimaFonte)) {
            return (float) $minimaFonte;
        }
        if (isset($estado['nota_minima_aprovacao']) && $estado['nota_minima_aprovacao'] !== '' && is_numeric($estado['nota_minima_aprovacao'])) {
            return (float) $estado['nota_minima_aprovacao'];
        }

        return 6.0;
    }

    /**
     * @param array<string,mixed> $estado
     * @param mixed $minimaFonte
     */
    private function origemDaClonagem(array $estado, $minimaFonte): string
    {
        if (($estado['nota_minima_origem'] ?? '') === 'regra') {
            return 'regra';
        }
        if ($minimaFonte !== null && $minimaFonte !== '' && is_numeric($minimaFonte)) {
            return 'evento';
        }
        if (isset($estado['nota_minima_aprovacao']) && $estado['nota_minima_aprovacao'] !== '' && is_numeric($estado['nota_minima_aprovacao'])) {
            return (string) (($estado['nota_minima_origem'] ?? '') !== '' ? $estado['nota_minima_origem'] : 'padrao');
        }

        return 'padrao';
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    private function catalogoQuadrosNotas(): array
    {
        $path = dirname(__DIR__, 2) . '/grupos-regras-notas/Services/GrupoRegrasNotasService.php';
        if (!is_file($path)) {
            return [];
        }
        require_once $path;
        try {
            $svc = new GrupoRegrasNotasService();
            if (!$svc->moduloAtivo() || !$svc->model()->tabelasProntas()) {
                return [];
            }
            $out = [];
            foreach ($svc->model()->listar(true) as $g) {
                $id = (int) ($g['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $materiasQuadro = [];
                $vistosQuadro = [];
                $mapaQuadro = $svc->materiasQuadroPadrao($id);
                foreach (['A', 'B'] as $letraQuadro) {
                    foreach ($mapaQuadro[$letraQuadro] ?? [] as $matQuadro) {
                        if (!is_array($matQuadro)) {
                            continue;
                        }
                        $midQuadro = (int) ($matQuadro['id'] ?? 0);
                        if ($midQuadro <= 0 || isset($vistosQuadro[$midQuadro])) {
                            continue;
                        }
                        $vistosQuadro[$midQuadro] = true;
                        $materiasQuadro[] = [
                            'id' => $midQuadro,
                            'nome' => trim((string) ($matQuadro['nome'] ?? '')),
                        ];
                    }
                }
                $out[] = [
                    'id' => $id,
                    'nome' => trim((string) ($g['nome'] ?? ('Quadro #' . $id))),
                    'materias' => $materiasQuadro,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo quadros: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Critérios da regra de aprovação no documento de boletim (modelo ou impressão do zero).
     *
     * @param array<string,mixed> $estado
     */
    private function aplicarEscopoDoBoletimCadastro(array &$estado): void
    {
        $id = (int) ($estado['boletim_id'] ?? 0);
        $layoutId = (int) ($estado['layout_boletim_id'] ?? 0);
        $ehDocumentoBoletim = (($estado['exibir_em'] ?? '') === 'boletim')
            || $layoutId > 0
            || !empty($estado['quadro_livre'])
            || in_array((string) ($estado['origem'] ?? ''), ['quadro-impressao', 'santa-maria', 'composto'], true);

        // Boletim → Montar quadro: matérias sempre da regra de aprovação do layout.
        if ($layoutId > 0) {
            $idsRegra = $this->materiasIdsDoLayoutImpressao($layoutId);
            if ($idsRegra !== []) {
                $estado['materias_ids'] = $idsRegra;
            }
        }

        // Modelo vinculado: preenche matérias/séries (também no demonstrativo).
        $bol = $id > 0 ? $this->buscarBoletimCatalogo($id) : null;
        if (is_array($bol)) {
            $estado['escala_max_padrao'] = $this->normalizarEscalaMaxima(
                $bol['escala_max'] ?? null,
                $this->escalaMaxPadraoDoAno((int) ($estado['ano_letivo'] ?? 0))
            );
            if (($estado['finalidade'] ?? '') !== 'complementar') {
                $estado['finalidade'] = (($bol['finalidade'] ?? '') === 'complementar') ? 'complementar' : 'oficial';
            }
            foreach (['materias_ids', 'series_ids', 'turmas_ids'] as $campo) {
                if (!empty($estado[$campo]) && is_array($estado[$campo])) {
                    continue;
                }
                $estado[$campo] = array_values(array_filter(
                    array_map('intval', (array) ($bol[$campo] ?? [])),
                    static fn ($n) => $n > 0
                ));
            }
        }

        // Round/casas/mínima da regra de aprovação: só no documento de boletim
        // (impressão/modelo). Demonstrativo mantém o que o evento já tinha.
        if (!$ehDocumentoBoletim) {
            return;
        }

        $criterios = null;
        if (is_array($bol)) {
            $criterios = [
                'nota_minima_aprovacao' => $bol['nota_minima_aprovacao'] ?? 6,
                'escala_max' => $bol['escala_max'] ?? null,
                'round_mode' => $bol['round_mode'] ?? 'none',
                'decimal_places' => $bol['decimal_places'] ?? 2,
                'escopo_arredondamento' => (($bol['escopo_arredondamento'] ?? '') === 'todas') ? 'todas' : 'medias',
                'minima_origem' => (($bol['minima_origem'] ?? '') === 'regra') ? 'regra' : 'padrao',
                'regra_nome' => (string) ($bol['regra_nome'] ?? ''),
                'regra' => (($bol['minima_origem'] ?? '') === 'regra') ? ['nome' => (string) ($bol['regra_nome'] ?? '')] : null,
            ];
        } else {
            try {
                require_once __DIR__ . '/BoletimCadastroService.php';
                $criterios = (new \App\Modulos\Boletins\Services\BoletimCadastroService())->criteriosEfetivosParaDocumento(
                    null,
                    (int) ($estado['ano_letivo'] ?? 0) ?: null,
                    is_array($estado['series_ids'] ?? null) ? $estado['series_ids'] : [],
                    (string) ($estado['finalidade'] ?? 'oficial')
                );
            } catch (\Throwable $e) {
                error_log('aplicarEscopoDoBoletimCadastro criterios: ' . $e->getMessage());
                $criterios = null;
            }
        }
        if (is_array($criterios)) {
            $estado['escala_max_padrao'] = $this->normalizarEscalaMaxima(
                $criterios['escala_max'] ?? null,
                $this->escalaMaxPadraoDoAno((int) ($estado['ano_letivo'] ?? 0))
            );
        }
        if (!is_array($criterios) || ($criterios['regra'] ?? null) === null) {
            return;
        }
        $roundBol = strtolower(trim((string) ($criterios['round_mode'] ?? 'none')));
        $estado['round_mode'] = in_array($roundBol, ['none', 'half', 'places'], true) ? $roundBol : 'none';
        $estado['decimal_places'] = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais(
            $criterios['decimal_places'] ?? 2
        );
        $escopoBol = strtolower(trim((string) ($criterios['escopo_arredondamento'] ?? 'medias')));
        $estado['escopo_arredondamento'] = $escopoBol === 'todas' ? 'todas' : 'medias';
        $estado['nota_minima_aprovacao'] = (float) ($criterios['nota_minima_aprovacao'] ?? 6);
        $estado['nota_minima_origem'] = 'regra';
        $estado['nota_minima_regra_nome'] = (string) ($criterios['regra_nome'] ?? '');
    }

    /**
     * @return list<string>
     */
    private function inferirPecasDoQuadro(int $grupoId): array
    {
        if ($grupoId <= 0) {
            return [];
        }
        $path = dirname(__DIR__, 2) . '/grupos-regras-notas/Services/GrupoRegrasNotasService.php';
        if (!class_exists('GrupoRegrasNotasService', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('GrupoRegrasNotasService', false)) {
            return [];
        }
        try {
            $payload = (new \App\Modulos\GruposRegrasNotas\Services\GrupoRegrasNotasService())->payloadPublico($grupoId);
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($payload)) {
            return [];
        }
        $pecas = [];
        foreach ((array) ($payload['marcas'] ?? []) as $m) {
            if (!is_array($m) || (string) ($m['papel'] ?? '') === 'calculada') {
                continue;
            }
            $cod = strtolower(trim((string) ($m['codigo'] ?? $m['chave'] ?? '')));
            $tipoId = (int) ($m['tipo_nota_id'] ?? 0);
            $key = $tipoId > 0 ? $this->chavePecaPorTipoId($tipoId) : '';
            if ($key === '') {
                $key = (string) ($this->chavePecaDeComponente([
                    'codigo' => $cod,
                    'nome' => (string) ($m['nome'] ?? ''),
                    'source_type' => 'provas_sistema',
                    'config' => ['tipo_avaliacao_id' => $tipoId],
                ]) ?? '');
            }
            if ($key !== '') {
                $pecas[] = $key;
            }
        }

        return array_values(array_unique($pecas));
    }

    /**
     * @return list<array{id:int,nome:string,modo:string,aplicar_em:string,divisor:?float,materias_ids:list<int>}>
     */
    private function catalogoAgrupamentos(): array
    {
        $path = dirname(__DIR__, 2) . '/agrupamentos-componentes/Services/AgrupamentoComponenteService.php';
        if (!is_file($path)) {
            return [];
        }
        require_once $path;
        try {
            return (new \App\Modulos\AgrupamentosComponentes\Services\AgrupamentoComponenteService())->listarParaBoletim();
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo agrupamentos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Pai (rótulo) + desdobramentos já cadastrados em Componentes Curriculares.
     *
     * @return list<array{pai_id:int,nome:string,filhos:list<array{id:int,nome:string}>,materias_ids:list<int>}>
     */
    private function catalogoFamiliasComponentes(): array
    {
        $path = dirname(__DIR__) . '/Models/Education/ComponenteCurricular.php';
        if (!class_exists('ComponenteCurricular', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('ComponenteCurricular', false)) {
            return [];
        }
        try {
            $model = new ComponenteCurricular();
            $mapa = $model->mapaFilhosPorPai();
            if ($mapa === []) {
                return [];
            }
            $byId = [];
            foreach ($model->getAll(true) ?: [] as $c) {
                $id = (int) ($c['id'] ?? 0);
                if ($id > 0) {
                    $byId[$id] = $c;
                }
            }
            $out = [];
            foreach ($mapa as $paiId => $filhos) {
                $paiId = (int) $paiId;
                if ($paiId <= 0 || count($filhos) < 2) {
                    continue;
                }
                $nome = trim((string) (($byId[$paiId] ?? [])['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $filhosOut = [];
                $ids = [];
                foreach ($filhos as $f) {
                    $fid = (int) ($f['id'] ?? 0);
                    if ($fid <= 0) {
                        continue;
                    }
                    $filhosOut[] = [
                        'id' => $fid,
                        'nome' => trim((string) ($f['nome'] ?? '')),
                    ];
                    $ids[] = $fid;
                }
                if (count($ids) < 2) {
                    continue;
                }
                $out[] = [
                    'pai_id' => $paiId,
                    'nome' => $nome,
                    'filhos' => $filhosOut,
                    'materias_ids' => $ids,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo familias: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Estado inicial do wizard (opcionalmente a partir do formulário aberto).
     *
     * @param array<string,mixed>|null $estadoFormulario
     * @return array<string,mixed>
     */
    public function estadoPadrao(?array $estadoFormulario = null, ?int $regraIdAtual = null): array
    {
        $regraIdAtual = (int) $regraIdAtual;
        $boletimSeed = is_array($estadoFormulario) ? (int) ($estadoFormulario['boletim_id'] ?? 0) : 0;
        $anoPreferido = is_array($estadoFormulario) ? (int) ($estadoFormulario['ano_letivo'] ?? 0) : 0;
        $anoInformado = $anoPreferido >= 2000 && $anoPreferido <= 2100;
        $ano = $anoInformado
            ? $anoPreferido
            : $this->ferramentas->anoLetivoPadrao(null);
        $bimSeed = is_array($estadoFormulario) ? (int) ($estadoFormulario['bimestre'] ?? 0) : 0;
        if ($regraIdAtual <= 0 && $boletimSeed > 0) {
            $regraIdAtual = $this->ferramentas->sugerirRegraIdDoBoletim($boletimSeed, $ano, $bimSeed);
        }
        $estado = [
            'passo' => 'inicio',
            'origem' => 'zero',
            'modelo_key' => '',
            'clonar_regra_id' => null,
            'modo' => ($regraIdAtual !== null && $regraIdAtual > 0) ? 'editar' : 'criar',
            'regra_id' => ($regraIdAtual !== null && $regraIdAtual > 0) ? $regraIdAtual : null,
            'nome' => '',
            'codigo' => '',
            'ano_letivo' => $ano,
            'bimestre' => 1,
            'exibir_em' => 'notas',
            'finalidade' => 'oficial',
            'boletim_id' => 0,
            'grupo_regras_notas_id' => 0,
            'nota_minima_aprovacao' => 6.0,
            'escala_max_padrao' => $this->escalaMaxPadraoDoAno($ano),
            'nota_minima_origem' => 'padrao',
            'nota_minima_regra_nome' => '',
            'round_mode' => 'none',
            'decimal_places' => 2,
            'escopo_arredondamento' => 'medias',
            'pecas' => [],
            'pecas_opcoes' => $this->pecasOpcoesPadrao([]),
            'eventos_ids' => [],
            'eventos_ids_manual' => 0,
            'subeventos_ids' => [],
            'subeventos_ids_manual' => 0,
            'materia_unica' => 0,
            'materia_unica_modo' => 'soma',
            'materia_unica_tocada' => false,
            'formula_preset' => 'media_simples',
            'formula_custom' => '',
            'formula_tokens' => [],
            'formulas_blocos' => [],
            'formulas_materias_blocos' => [],
            'nomes_blocos' => [],
            'escalas_blocos' => [],
            'media_ciclo_blocos' => [],
            'media_ciclo_destinos' => [],
            'bloco_calc' => '',
            'materia_calc' => 0,
            'blocos_calc' => [],
            'colunas_ordem' => [],
            'colunas_ocultas' => [],
            'linhas_layout' => [],
            'fontes_bimestres' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
            'fontes_faltas' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
            'demonstrativos_ids' => [],
            'eventos_avaliativos_ids' => [],
            'eventos_avaliativos_subs' => [],
            'cabecalhos_manuais' => 0,
            'quadro_chips_manuais' => 0,
            'titulos_periodo_ok' => [],
            'modelo_santa_maria' => 0,
            'layout_boletim_id' => 0,
            'quadro_livre' => 0,
            'data_inicio' => '',
            'data_fim' => '',
            'jornada_modo' => 'bimestre',
            'jornada_ids' => [],
            'jornada_bimestres' => [],
            'jornada_nota_modo' => 'linear',
            'jornada_distribuicao_notas' => 'por_materia',
            'jornada_materias_ids' => [],
            'jornada_materias_tocada' => false,
            'jornada_faixas' => self::faixasJornadaPadrao(),
            'series_ids' => [],
            'turmas_ids' => [],
            'materias_ids' => [],
            'aluno_preview_id' => 0,
            'grupo_linha' => $this->grupoLinhaPadrao(),
            'rascunho_preservado' => null,
        ];

        if (
            (!is_array($estadoFormulario) || $estadoFormulario === [] || $this->estadoFormularioEstaVazio($estadoFormulario))
            && $regraIdAtual !== null
            && $regraIdAtual > 0
        ) {
            $regraExistente = $this->ferramentas->obterRegra($regraIdAtual);
            if (is_array($regraExistente)) {
                $estadoFormulario = $regraExistente;
            }
        }

        if (is_array($estadoFormulario)) {
            if (trim((string) ($estadoFormulario['nome'] ?? '')) !== '') {
                $estado['nome'] = trim((string) $estadoFormulario['nome']);
            }
            if (trim((string) ($estadoFormulario['codigo'] ?? '')) !== '') {
                $estado['codigo'] = trim((string) $estadoFormulario['codigo']);
            }
            if (!empty($estadoFormulario['ano_letivo'])) {
                $anoForm = (int) $estadoFormulario['ano_letivo'];
                $editando = ($regraIdAtual !== null && $regraIdAtual > 0)
                    || (int) ($estadoFormulario['id'] ?? 0) > 0;
                if ($editando || ($anoInformado && $anoForm === $anoPreferido)) {
                    $estado['ano_letivo'] = $anoForm;
                } else {
                    $estado['ano_letivo'] = $this->ferramentas->anoLetivoPadrao($anoForm);
                }
            }
            if (isset($estadoFormulario['bimestre']) && $estadoFormulario['bimestre'] !== '') {
                $estado['bimestre'] = (int) $estadoFormulario['bimestre'];
            }
            if (!empty($estadoFormulario['exibir_em'])) {
                $estado['exibir_em'] = (string) $estadoFormulario['exibir_em'];
            }
            if (!empty($estadoFormulario['finalidade'])) {
                $estado['finalidade'] = $this->normalizarFinalidade($estadoFormulario['finalidade']);
            }
            if (!empty($estadoFormulario['boletim_id'])) {
                $estado['boletim_id'] = (int) $estadoFormulario['boletim_id'];
            }
            if (!empty($estadoFormulario['layout_boletim_id'])) {
                $estado['layout_boletim_id'] = (int) $estadoFormulario['layout_boletim_id'];
            }
            if (!empty($estadoFormulario['grupo_regras_notas_id'])) {
                $estado['grupo_regras_notas_id'] = (int) $estadoFormulario['grupo_regras_notas_id'];
            }
            if (isset($estadoFormulario['nota_minima_aprovacao']) && $estadoFormulario['nota_minima_aprovacao'] !== '') {
                $estado['nota_minima_aprovacao'] = (float) $estadoFormulario['nota_minima_aprovacao'];
                if ($regraIdAtual > 0) {
                    $estado['nota_minima_origem'] = '';
                }
            }
            if (isset($estadoFormulario['escala_max_padrao']) && $estadoFormulario['escala_max_padrao'] !== '') {
                $estado['escala_max_padrao'] = $this->normalizarEscalaMaxima(
                    $estadoFormulario['escala_max_padrao'],
                    (float) $estado['escala_max_padrao']
                );
            }
            if (!empty($estadoFormulario['round_mode'])) {
                $estado['round_mode'] = (string) $estadoFormulario['round_mode'];
            }
            if (isset($estadoFormulario['decimal_places']) && $estadoFormulario['decimal_places'] !== '') {
                $estado['decimal_places'] = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estadoFormulario['decimal_places']);
            }
            $estado['series_ids'] = array_values(array_map('intval', (array) ($estadoFormulario['series_ids'] ?? [])));
            $estado['turmas_ids'] = array_values(array_map('intval', (array) ($estadoFormulario['turmas_ids'] ?? [])));
            $estado['materias_ids'] = array_values(array_map('intval', (array) ($estadoFormulario['materias_ids'] ?? [])));
            if (!empty($estadoFormulario['default_data_inicio'])) {
                $estado['data_inicio'] = substr((string) $estadoFormulario['default_data_inicio'], 0, 10);
            }
            if (!empty($estadoFormulario['default_data_fim'])) {
                $estado['data_fim'] = substr((string) $estadoFormulario['default_data_fim'], 0, 10);
            }

            $comps = is_array($estadoFormulario['componentes'] ?? null) ? $estadoFormulario['componentes'] : [];
            if ($comps !== []) {
                $estado['origem'] = 'formulario';
                // Reabrir configuração: se ainda não há ordem, usa as colunas já salvas.
                if ($this->normalizarColunasOrdem($estado['colunas_ordem'] ?? []) === []) {
                    $ordemSalva = [];
                    foreach ($comps as $cSalvo) {
                        if (!is_array($cSalvo)) {
                            continue;
                        }
                        $codSalvo = strtolower(trim((string) ($cSalvo['codigo'] ?? '')));
                        if ($codSalvo === '' || $codSalvo === '_semanal' || (bool) preg_match('/^s[1-8]$/', $codSalvo)) {
                            continue;
                        }
                        if (!in_array($codSalvo, $ordemSalva, true)) {
                            $ordemSalva[] = $codSalvo;
                        }
                    }
                    $estado['colunas_ordem'] = $ordemSalva;
                }
                $estado['pecas'] = $this->inferirPecasDeComponentes($comps);
                $estado['pecas_opcoes'] = $this->inferirPecasOpcoesDeComponentes(
                    $comps,
                    $estado['pecas'],
                    (string) ($estadoFormulario['formula_final'] ?? '')
                );
                $estado['materia_unica'] = $this->inferirMateriaUnicaDeComponentes($comps);
                $modoInf = $this->inferirMateriaUnicaModoDeComponentes($comps);
                if ($modoInf !== '') {
                    $estado['materia_unica_modo'] = $modoInf;
                }
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
                $this->aplicarJornadaDoFormulario($estado, $comps);
                $this->aplicarGrupoLinhaDoFormulario($estado, $comps);
                $this->marcarBimestreSalvoNasPecas($estado);
                $this->marcarEventosSalvosNoEstado($estado, $comps);
                $estado['passo'] = 'revisar';
                $estado['rascunho_preservado'] = [
                    'modo' => ($regraIdAtual !== null && $regraIdAtual > 0) ? 'editar' : 'criar',
                    'regra_id' => ($regraIdAtual !== null && $regraIdAtual > 0) ? $regraIdAtual : null,
                    'nome' => $estado['nome'],
                    'codigo' => $estado['codigo'],
                    'formula_final' => (string) ($estadoFormulario['formula_final'] ?? ''),
                    'exibir_em' => $estado['exibir_em'],
                    'finalidade' => $estado['finalidade'] ?? 'oficial',
                    'boletim_id' => (int) ($estado['boletim_id'] ?? 0),
                    'grupo_regras_notas_id' => (int) ($estado['grupo_regras_notas_id'] ?? 0),
                    'ano_letivo' => $estado['ano_letivo'],
                    'bimestre' => $estado['bimestre'] > 0 ? $estado['bimestre'] : null,
                    'turmas_ids' => $estado['turmas_ids'],
                    'materias_ids' => $estado['materias_ids'],
                    'series_ids' => $estado['series_ids'],
                    'round_mode' => $estado['round_mode'],
                    'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? 2),
                    'nota_minima_aprovacao' => $estado['nota_minima_aprovacao'],
                    'componentes' => $comps,
                ];
                $estado['fontes_bimestres'] = $this->inferirFontesBimestresDeComponentes($comps);
                $estado['fontes_faltas'] = $this->inferirFontesFaltasDeComponentes($comps);
            }
            $extrasSalvos = json_decode((string) ($estadoFormulario['extras_json'] ?? ''), true);
            if (!is_array($extrasSalvos)) {
                $extrasSalvos = [];
            }
            $linhasSalvas = $this->linhasLayoutDasExtras($extrasSalvos);
            if ($linhasSalvas === [] && $comps !== []) {
                // Fallback: títulos gravados em config_json dos componentes.
                $linhasSalvas = $this->linhasLayoutDeComponentes($comps);
            }
            if ($linhasSalvas !== []) {
                $estado['linhas_layout'] = $linhasSalvas;
                $estado['cabecalhos_manuais'] = 1;
                $estado['quadro_chips_manuais'] = 1;
            }
            if (!empty($extrasSalvos['cabecalhos_manuais'])) {
                $estado['cabecalhos_manuais'] = 1;
            }
            if (!empty($extrasSalvos['quadro_chips_manuais'])) {
                $estado['quadro_chips_manuais'] = 1;
            }
            if (is_array($extrasSalvos['colunas_ordem'] ?? null) && $extrasSalvos['colunas_ordem'] !== []) {
                $estado['colunas_ordem'] = array_values(array_filter(array_map(
                    static function ($c) {
                        return strtolower(trim((string) $c));
                    },
                    $extrasSalvos['colunas_ordem']
                )));
            }
            if (is_array($extrasSalvos['titulos_periodo_ok'] ?? null)) {
                $estado['titulos_periodo_ok'] = $extrasSalvos['titulos_periodo_ok'];
            }
            if (is_array($extrasSalvos['colunas_ocultas'] ?? null)) {
                $estado['colunas_ocultas'] = $this->normalizarColunasOcultas($extrasSalvos['colunas_ocultas']);
            }
            if (is_array($extrasSalvos['eventos_avaliativos_ids'] ?? null)) {
                $estado['eventos_avaliativos_ids'] = array_values(array_filter(array_map(
                    'intval',
                    $extrasSalvos['eventos_avaliativos_ids']
                )));
            }
            if (!empty($extrasSalvos['modelo_santa_maria']) || ((string) ($extrasSalvos['origem'] ?? '')) === 'santa-maria') {
                $estado['modelo_santa_maria'] = 1;
                $estado['origem'] = 'santa-maria';
                $estado['quadro_livre'] = 0;
                $estado['exibir_em'] = 'boletim';
            } elseif (((string) ($extrasSalvos['origem'] ?? '')) === 'composto') {
                $estado['origem'] = 'composto';
                $estado['quadro_livre'] = 0;
                $estado['exibir_em'] = 'boletim';
            }
            if ((int) ($extrasSalvos['layout_boletim_id'] ?? 0) > 0) {
                $estado['layout_boletim_id'] = (int) $extrasSalvos['layout_boletim_id'];
            }
            if (is_array($extrasSalvos['fontes_bimestres'] ?? null)) {
                $estado['fontes_bimestres'] = $this->normalizarFontesBimestres($extrasSalvos['fontes_bimestres']);
            }
            if (is_array($extrasSalvos['fontes_faltas'] ?? null)) {
                $estado['fontes_faltas'] = $this->normalizarFontesBimestres($extrasSalvos['fontes_faltas']);
            }
        }

        if ($estado['nome'] === '') {
            $estado['nome'] = $this->nomePadraoEvento((int) $estado['ano_letivo'], (int) $estado['bimestre']);
        }

        $visaoImpressaoSemModelo = ($estado['exibir_em'] ?? '') === 'boletim'
            || ($estado['origem'] ?? '') === 'quadro-impressao'
            || ($estado['origem'] ?? '') === 'santa-maria'
            || !empty($estado['quadro_livre'])
            || !empty($estado['modelo_santa_maria'])
            || (int) ($estado['layout_boletim_id'] ?? 0) > 0;
        if ((int) ($estado['boletim_id'] ?? 0) <= 0 && !$visaoImpressaoSemModelo) {
            $boletins = $this->catalogoBoletins();
            if (count($boletins) === 1) {
                $estado['boletim_id'] = (int) ($boletins[0]['id'] ?? 0);
            }
        }
        $this->aplicarEscopoDoBoletimCadastro($estado);
        $this->aplicarMinimaResolvida($estado);
        if (is_array($estado['rascunho_preservado'] ?? null)) {
            $estado['rascunho_preservado']['nota_minima_aprovacao'] = $estado['nota_minima_aprovacao'];
            $estado['rascunho_preservado']['nota_minima_origem'] = $estado['nota_minima_origem'];
            $estado['rascunho_preservado']['nota_minima_regra_nome'] = $estado['nota_minima_regra_nome'];
        }
        if (($estado['pecas'] ?? []) === []) {
            $doQuadro = $this->inferirPecasDoQuadro((int) ($estado['grupo_regras_notas_id'] ?? 0));
            if ($doQuadro !== []) {
                $estado['pecas'] = $doQuadro;
                $estado['pecas_opcoes'] = $this->mesclarPecasOpcoes(
                    $this->pecasOpcoesPadrao($doQuadro, (int) ($estado['bimestre'] ?? 0)),
                    is_array($estado['pecas_opcoes'] ?? null) ? $estado['pecas_opcoes'] : []
                );
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
            }
        }

        return $estado;
    }

    /**
     * @param array<string,mixed> $estadoFormulario
     */
    private function estadoFormularioEstaVazio(array $estadoFormulario): bool
    {
        $camposTexto = ['nome', 'codigo', 'formula_final', 'exibir_em', 'finalidade', 'ano_letivo', 'bimestre'];
        foreach ($camposTexto as $campo) {
            if (trim((string) ($estadoFormulario[$campo] ?? '')) !== '') {
                return false;
            }
        }

        foreach (['componentes', 'series_ids', 'turmas_ids', 'materias_ids'] as $campo) {
            if (!empty($estadoFormulario[$campo])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $estado
     * @return array{ok:bool,estado:array,rascunho:?array,resumo:string,erros:list<string>,formulas_disponiveis:list<array>}
     */
    /**
     * Notas bimestrais com o módulo de eventos avaliativos não usam quadro nem
     * exigem peça/componente neste assistente.
     *
     * @param array<string,mixed> $estado
     */
    private function notasBimestraisComEventoAvaliativo(array $estado): bool
    {
        if (($estado['exibir_em'] ?? 'notas') !== 'notas' || $this->ehBoletimComposto($estado)) {
            return false;
        }
        return \App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos');
    }

    /**
     * Demonstrativo de Notas puro: só monta colunas — sem fórmula nem blocos de cálculo.
     *
     * @param array<string,mixed> $estado
     */
    private function ehDemonstrativoNotas(array $estado): bool
    {
        if ((string) ($estado['exibir_em'] ?? '') !== 'notas') {
            return false;
        }
        if ($this->ehBoletimComposto($estado)) {
            return false;
        }
        if ($this->ehModeloSantaMaria($estado)) {
            return false;
        }
        if ((string) ($estado['origem'] ?? '') === 'quadro-impressao') {
            return false;
        }
        if (!empty($estado['quadro_livre'])) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<string> $erros
     * @return list<string>
     */
    private function errosDaMontagem(array $estado, array $erros): array
    {
        $quadroLivre = !empty($estado['quadro_livre'])
            || (($estado['origem'] ?? '') === 'quadro-impressao')
            || $this->ehModeloSantaMaria($estado);
        $demonstrativoNotas = $this->ehDemonstrativoNotas($estado);
        if (!$this->notasBimestraisComEventoAvaliativo($estado) && !$quadroLivre && !$demonstrativoNotas) {
            return array_values(array_unique($erros));
        }
        $ocultar = [
            'Escolha ao menos uma peça da média (ex.: \Bimestral, Jornada, ENAC).',
            'Marque ao menos uma peça para entrar na média, ou clique numa coluna amarela em Exibir e monte o cálculo.',
            'Informe ao menos um componente (ex.: prova semanal, bimestral).',
        ];
        $visiveis = [];
        foreach ($erros as $erro) {
            $erro = (string) $erro;
            if ($demonstrativoNotas && $this->deveOcultarErroFormulaDemonstrativo($erro)) {
                continue;
            }
            if (!in_array($erro, $ocultar, true)) {
                $visiveis[] = $erro;
            }
        }
        $this->preencherEventosAvaliativosAPartirDoQuadro($estado);
        $temQuadroMontado = $this->codigosExplicitosDoQuadro($estado) !== []
            || $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) !== [];
        if ($quadroLivre
            && $this->idsDemonstrativos($estado) === []
            && $this->idsEventosAvaliativos($estado) === []
            && !$temQuadroMontado) {
            $visiveis[] = 'Escolha ao menos um Evento avaliativo (por bimestre/trimestre) para montar o boletim.';
        }
        return array_values(array_unique($visiveis));
    }

    public function montar(array $estado): array
    {
        $estado = $this->normalizarEstado($estado);
        $this->aplicarNomeAutomatico($estado);
        $this->aplicarEscopoDoBoletimCadastro($estado);
        $this->aplicarMinimaResolvida($estado);
        $erros = $this->errosGrupoLinha($estado);

        if ($estado['origem'] === 'clonar' && !empty($estado['clonar_regra_id'])) {
            $base = $this->ferramentas->obterRegra((int) $estado['clonar_regra_id']);
            if ($base === null) {
                return [
                    'ok' => false,
                    'estado' => $estado,
                    'rascunho' => null,
                    'resumo' => 'Não encontrei o evento para clonar.',
                    'erros' => ['Evento de origem não encontrado.'],
                    'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
                ];
            }
            $rascunho = $this->rascunhoAPartirDeRegra($base, $estado);
            $estado['nota_minima_aprovacao'] = $rascunho['nota_minima_aprovacao'];
            $estado['nota_minima_origem'] = (string) ($rascunho['nota_minima_origem'] ?? 'evento');
            $estado['nota_minima_regra_nome'] = (string) ($rascunho['nota_minima_regra_nome'] ?? '');
            if ($this->grupoLinhaEstaVazio($estado)) {
                $this->aplicarGrupoLinhaDoFormulario(
                    $estado,
                    is_array($base['componentes'] ?? null) ? $base['componentes'] : []
                );
            }
            if (empty($estado['materia_unica_tocada'])) {
                $estado['materia_unica'] = $this->inferirMateriaUnicaDeComponentes(
                    is_array($base['componentes'] ?? null) ? $base['componentes'] : []
                );
                $modoInf = $this->inferirMateriaUnicaModoDeComponentes(
                    is_array($base['componentes'] ?? null) ? $base['componentes'] : []
                );
                if ($modoInf !== '') {
                    $estado['materia_unica_modo'] = $modoInf;
                }
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
            }
            if ($estado['series_ids'] === [] && is_array($rascunho['series_ids'] ?? null)) {
                $estado['series_ids'] = array_values(array_filter(array_map('intval', (array) $rascunho['series_ids'])));
            }
            if ($estado['turmas_ids'] === [] && is_array($rascunho['turmas_ids'] ?? null)) {
                $estado['turmas_ids'] = array_values(array_filter(array_map('intval', (array) $rascunho['turmas_ids'])));
            }
            $rascunho = $this->aplicarMateriaUnicaNoRascunho(
                $this->aplicarGrupoLinhaNoRascunho($rascunho, $estado),
                $estado
            );
            if (!$this->deveRemontarComoQuadro($estado, $rascunho)) {
                $rascunho = $this->aplicarConfigJornadaDoEstadoNoRascunho($rascunho, $estado);
                $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
                $rascunhoOk = $this->aplicarLayoutBimestreNoRascunhoNotas($validado['rascunho'], $estado);
                $rascunhoOk = $this->sanitizarDemonstrativoSemFormula($rascunhoOk, $estado);
                $estado['rascunho_preservado'] = $rascunhoOk;
                $resumo = $this->montarResumoHumano($rascunhoOk);
                $errosOut = array_values(array_unique(array_merge($erros, $validado['erros'])));
                return [
                    'ok' => $rascunhoOk['componentes'] !== [],
                    'estado' => $estado,
                    'rascunho' => $rascunhoOk,
                    'resumo' => $resumo,
                    'erros' => $this->errosDaMontagem($estado, $errosOut),
                    'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
                ];
            }
        }

        // Edição do formulário já aberto / ajuste via chat: preserva componentes técnicos,
        // salvo quando a prova semanal precisa virar o quadro S1–S8 (layout Colag).
        if (in_array($estado['origem'], ['formulario', 'chat'], true)
            && !empty($estado['rascunho_preservado'])
            && is_array($estado['rascunho_preservado'])
            && !$this->ehBoletimComposto($estado)
            && !$this->deveRemontarComoQuadro($estado, $estado['rascunho_preservado'])
        ) {
            $rascunho = $estado['rascunho_preservado'];
            $rascunho['nome'] = trim((string) ($estado['nome'] ?? $rascunho['nome'] ?? ''));
            $rascunho['ano_letivo'] = (int) ($estado['ano_letivo'] ?? $rascunho['ano_letivo'] ?? date('Y'));
            $rascunho['bimestre'] = (int) ($estado['bimestre'] ?? $rascunho['bimestre'] ?? 0) ?: null;
            $rascunho['exibir_em'] = (string) ($estado['exibir_em'] ?? $rascunho['exibir_em'] ?? 'notas');
            $rascunho['finalidade'] = $this->normalizarFinalidade(
                $estado['finalidade'] ?? $rascunho['finalidade'] ?? 'oficial'
            );
            $rascunho['boletim_id'] = (int) ($estado['boletim_id'] ?? $rascunho['boletim_id'] ?? 0);
            $rascunho['grupo_regras_notas_id'] = (int) ($estado['grupo_regras_notas_id'] ?? $rascunho['grupo_regras_notas_id'] ?? 0);
            $rascunho['round_mode'] = (string) ($estado['round_mode'] ?? $rascunho['round_mode'] ?? 'none');
            $rascunho['decimal_places'] = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? $rascunho['decimal_places'] ?? 2);
            $rascunho['nota_minima_aprovacao'] = $estado['nota_minima_aprovacao'] ?? $rascunho['nota_minima_aprovacao'] ?? 6;
            $rascunho['nota_minima_origem'] = (string) ($estado['nota_minima_origem'] ?? $rascunho['nota_minima_origem'] ?? 'padrao');
            $rascunho['nota_minima_regra_nome'] = (string) ($estado['nota_minima_regra_nome'] ?? $rascunho['nota_minima_regra_nome'] ?? '');
            $rascunho['series_ids'] = $estado['series_ids'] !== []
                ? $estado['series_ids']
                : ($rascunho['series_ids'] ?? []);
            $rascunho['turmas_ids'] = $estado['turmas_ids'] !== []
                ? $estado['turmas_ids']
                : ($rascunho['turmas_ids'] ?? []);
            $rascunho['materias_ids'] = $estado['materias_ids'] !== []
                ? $estado['materias_ids']
                : ($rascunho['materias_ids'] ?? []);
            if ($estado['series_ids'] === [] && is_array($rascunho['series_ids'] ?? null)) {
                $estado['series_ids'] = array_values(array_filter(array_map('intval', $rascunho['series_ids'])));
            }
            if ($estado['turmas_ids'] === [] && is_array($rascunho['turmas_ids'] ?? null)) {
                $estado['turmas_ids'] = array_values(array_filter(array_map('intval', $rascunho['turmas_ids'])));
            }
            if (!empty($estado['data_inicio'])) {
                $rascunho['default_data_inicio'] = (string) $estado['data_inicio'];
            }
            if (!empty($estado['data_fim'])) {
                $rascunho['default_data_fim'] = (string) $estado['data_fim'];
            }
            if ($this->grupoLinhaEstaVazio($estado)) {
                $this->aplicarGrupoLinhaDoFormulario(
                    $estado,
                    is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : []
                );
            }
            if (empty($estado['materia_unica_tocada'])) {
                $estado['materia_unica'] = $this->inferirMateriaUnicaDeComponentes(
                    is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : []
                );
                $modoInf = $this->inferirMateriaUnicaModoDeComponentes(
                    is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : []
                );
                if ($modoInf !== '') {
                    $estado['materia_unica_modo'] = $modoInf;
                }
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
            }
            $rascunho = $this->aplicarMateriaUnicaNoRascunho(
                $this->aplicarGrupoLinhaNoRascunho($rascunho, $estado),
                $estado
            );
            $rascunho = $this->aplicarConfigJornadaDoEstadoNoRascunho($rascunho, $estado);
            $rascunho = $this->aplicarFormulasBlocos($rascunho, $estado, []);
            $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
            $errosOut = array_values(array_unique(array_merge($erros, $validado['erros'])));
            $rascunhoOk = $this->aplicarLayoutBimestreNoRascunhoNotas($validado['rascunho'], $estado);
            $rascunhoOk = $this->sanitizarDemonstrativoSemFormula($rascunhoOk, $estado);
            $estado['rascunho_preservado'] = $rascunhoOk;
            return [
                'ok' => $rascunhoOk['componentes'] !== [],
                'estado' => $estado,
                'rascunho' => $rascunhoOk,
                'resumo' => $this->montarResumoHumano($rascunhoOk),
                'erros' => $this->errosDaMontagem($estado, $errosOut),
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        $ehQuadroImpressao = !empty($estado['quadro_livre']) || ($estado['origem'] ?? '') === 'quadro-impressao';
        if ($ehQuadroImpressao) {
            // Chips já no quadro (títulos/ordem) → recupera IDs perdidos no remount.
            $this->preencherEventosAvaliativosAPartirDoQuadro($estado);
            $temQuadroMontado = $this->codigosExplicitosDoQuadro($estado) !== []
                || $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) !== [];
            if ($this->idsDemonstrativos($estado) === [] && $this->idsEventosAvaliativos($estado) === [] && !$temQuadroMontado) {
                return [
                    'ok' => false,
                    'estado' => $estado,
                    'rascunho' => null,
                    'resumo' => 'Marque os Eventos avaliativos por período.',
                    'erros' => $this->errosDaMontagem($estado, $erros),
                    'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
                ];
            }
            $errosDemo = [];
            $rascunho = $this->aplicarGrupoLinhaNoRascunho(
                $this->rascunhoMesclandoDemonstrativos($estado, $errosDemo),
                $estado
            );
            $rascunho = $this->aplicarFormulasBlocos($rascunho, $estado, []);
            $rascunho = $this->garantirBlocosCalcNosComponentes($rascunho, $estado);
            $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
            $errosOut = array_values(array_unique(array_merge($erros, $errosDemo, $validado['erros'])));
            $rascunhoOut = $this->sanitizarDemonstrativoSemFormula($validado['rascunho'], $estado);
            return [
                'ok' => $rascunhoOut['componentes'] !== [],
                'estado' => $estado,
                'rascunho' => $rascunhoOut,
                'resumo' => $this->montarResumoHumano($rascunhoOut),
                'erros' => $this->errosDaMontagem($estado, $errosOut),
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        if ($this->ehBoletimComposto($estado)) {
            $errosFontes = [];
            $rascunho = $this->aplicarGrupoLinhaNoRascunho(
                $this->rascunhoAPartirDeFontesBoletim($estado, $errosFontes),
                $estado
            );
            if (empty($estado['cabecalhos_manuais'])) {
                if ($this->ehModeloSantaMaria($estado) && $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) === []) {
                    $estado['linhas_layout'] = $this->normalizarLinhasLayout($rascunho['linhas_layout'] ?? []);
                } elseif (!empty($rascunho['linhas_layout']) && $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) === []) {
                    $estado['linhas_layout'] = $this->normalizarLinhasLayout($rascunho['linhas_layout']);
                }
            }
            $rascunho = $this->aplicarFormulasBlocos($rascunho, $estado, []);
            $rascunho = $this->garantirBlocosCalcNosComponentes($rascunho, $estado);
            $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
            $errosOut = array_values(array_unique(array_merge($erros, $errosFontes, $validado['erros'])));
            $rascunhoOut = $this->sanitizarDemonstrativoSemFormula($validado['rascunho'], $estado);
            return [
                'ok' => $rascunhoOut['componentes'] !== [],
                'estado' => $estado,
                'rascunho' => $rascunhoOut,
                'resumo' => $this->montarResumoHumano($rascunhoOut),
                'erros' => $this->errosDaMontagem($estado, $errosOut),
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        if ($estado['origem'] === 'modelo' && $estado['modelo_key'] !== '') {
            $modelo = $this->encontrarModelo($estado['modelo_key']);
            if ($modelo !== null) {
                $estado['pecas'] = $modelo['pecas'];
                $padrao = $this->pecasOpcoesPadrao($modelo['pecas'], (int) ($estado['bimestre'] ?? 0));
                $tmpModelo = ['pecas_opcoes' => $padrao];
                $this->aplicarPapeisDoModelo($tmpModelo, $modelo);
                $estado['pecas_opcoes'] = $this->mesclarPecasOpcoes(
                    $tmpModelo['pecas_opcoes'],
                    is_array($estado['pecas_opcoes'] ?? null) ? $estado['pecas_opcoes'] : []
                );
                if ($estado['formula_preset'] === '' || $estado['formula_preset'] === 'media_simples') {
                    $estado['formula_preset'] = $modelo['formula_preset'];
                }
            }
        }

        if (in_array('jornada', $estado['pecas'] ?? [], true)
            && $this->resolverIdsJornadaPorBimestre($estado) === []) {
            $erros[] = 'Nenhuma jornada com esse bimestre cadastrado. Cadastre o bimestre na jornada ou ajuste o bimestre da regra.';
        }
        if (($estado['jornada_modo'] ?? '') === 'selecionadas' && in_array('jornada', $estado['pecas'] ?? [], true)
            && array_values(array_filter(array_map('intval', (array) ($estado['jornada_ids'] ?? [])), static fn ($id) => $id > 0)) === []) {
            $erros[] = 'Marque ao menos uma jornada ou use as jornadas vinculadas ao bimestre da regra.';
        }

        if (!$this->ehDemonstrativoNotas($estado)) {
            if ($estado['pecas'] === []) {
                $erros[] = 'Escolha ao menos uma peça da média (ex.: \Bimestral, Jornada, ENAC).';
            } elseif (!$this->temFormulaMontada($estado) && !$this->temPecaComPapelMedia($estado)) {
                $erros[] = 'Marque ao menos uma peça para entrar na média, ou clique numa coluna amarela em Exibir e monte o cálculo.';
            }
        }
        $dispensaModeloBoletim = ($estado['exibir_em'] ?? '') === 'boletim'
            || ($estado['origem'] ?? '') === 'quadro-impressao'
            || ($estado['origem'] ?? '') === 'santa-maria'
            || !empty($estado['quadro_livre'])
            || !empty($estado['modelo_santa_maria'])
            || (int) ($estado['layout_boletim_id'] ?? 0) > 0;
        if ((int) ($estado['boletim_id'] ?? 0) <= 0 && ($estado['exibir_em'] ?? 'notas') !== 'notas' && !$dispensaModeloBoletim) {
            if (!class_exists('FechamentoGates', false)) {
            }
            $erros[] = FechamentoGates::mensagemAvaliacaoSemModelo();
        }

        if ($this->querLayoutQuadro($estado)) {
            $rascunhoQuadro = $this->montarQuadroDoEstado($estado);
            $rascunhoQuadro = $this->aplicarGrupoLinhaNoRascunho($rascunhoQuadro, $estado);
            if (empty($estado['materia_unica_tocada'])) {
                $estado['materia_unica'] = $this->inferirMateriaUnicaDeComponentes(
                    is_array($rascunhoQuadro['componentes'] ?? null) ? $rascunhoQuadro['componentes'] : []
                );
                $modoInf = $this->inferirMateriaUnicaModoDeComponentes(
                    is_array($rascunhoQuadro['componentes'] ?? null) ? $rascunhoQuadro['componentes'] : []
                );
                if ($modoInf !== '') {
                    $estado['materia_unica_modo'] = $modoInf;
                }
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
            }
            $rascunhoQuadro = $this->aplicarMateriaUnicaNoRascunho($rascunhoQuadro, $estado);
            $validadoQuadro = $this->ferramentas->validarEEnriquecerRascunho($rascunhoQuadro);
            $errosQuadro = array_values(array_unique(array_merge($erros, $validadoQuadro['erros'])));
            $rascunhoQuadroOut = $this->sanitizarDemonstrativoSemFormula($validadoQuadro['rascunho'], $estado);
            return [
                'ok' => $rascunhoQuadroOut['componentes'] !== [],
                'estado' => $estado,
                'rascunho' => $rascunhoQuadroOut,
                'resumo' => $this->montarResumoHumano($rascunhoQuadroOut),
                'erros' => $this->errosDaMontagem($estado, $errosQuadro),
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        $rascunho = $this->aplicarMateriaUnicaNoRascunho(
            $this->aplicarGrupoLinhaNoRascunho($this->rascunhoAPartirDePecas($estado), $estado),
            $estado
        );
        $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
        $erros = array_merge($erros, $validado['erros']);
        $rascunhoOut = $this->aplicarLayoutBimestreNoRascunhoNotas($validado['rascunho'], $estado);
        $rascunhoOut = $this->sanitizarDemonstrativoSemFormula($rascunhoOut, $estado);

        return [
            'ok' => $rascunhoOut['componentes'] !== [],
            'estado' => $estado,
            'rascunho' => $rascunhoOut,
            'resumo' => $this->montarResumoHumano($rascunhoOut),
            'erros' => $this->errosDaMontagem($estado, $erros),
            'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
        ];
    }

    /**
     * Evento de Notas: a média calculada e as faltas entram no bimestre da Vida Escolar (B1–B4).
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarLayoutBimestreNoRascunhoNotas(array $rascunho, array $estado): array
    {
        if ($this->ehBoletimComposto($estado)) {
            return $rascunho;
        }
        $exibir = strtolower(trim((string) ($rascunho['exibir_em'] ?? $estado['exibir_em'] ?? '')));
        if ($exibir !== 'notas') {
            return $rascunho;
        }
        $bim = (int) ($rascunho['bimestre'] ?? $estado['bimestre'] ?? 0);
        if ($bim < 1 || $bim > 4) {
            return $rascunho;
        }
        $grupo = 'b' . $bim;
        $formulaFinal = strtolower(trim((string) ($rascunho['formula_final'] ?? '')));
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $src = (string) ($c['source_type'] ?? '');
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $ehMediaFinal = ($src === 'calculado' && in_array($cod, ['media_bim', 'media_final', 'media', $grupo . '_media'], true))
                || ($formulaFinal !== '' && $cod === $formulaFinal);
            $ehFaltas = $src === 'faltas_evento'
                || str_contains($cod, 'falt')
                || (bool) preg_match('/(^|_)fal$/', $cod);
            if (!$ehMediaFinal && !$ehFaltas) {
                continue;
            }
            if (trim((string) ($cfg['layout_group'] ?? '')) === '') {
                $cfg['layout_group'] = $grupo;
            }
            if (trim((string) ($cfg['layout_type'] ?? '')) === '') {
                $cfg['layout_type'] = $ehFaltas ? 'faltas' : 'media';
            }
            $c['config'] = $cfg;
            $comps[$i] = $c;
        }
        $rascunho['componentes'] = $comps;
        $this->anexarFaltasEventoNotasSeFaltar($rascunho, $estado);

        return $rascunho;
    }

    /**
     * Evento de Notas: inclui o lançamento de faltas do mesmo bimestre, se ainda não houver bloco.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     */
    private function anexarFaltasEventoNotasSeFaltar(array &$rascunho, array $estado): void
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $src = (string) ($c['source_type'] ?? '');
            $lt = strtolower(trim((string) (($c['config']['layout_type'] ?? '') ?: ($c['layout_type'] ?? ''))));
            if (
                $src === 'faltas_evento'
                || $lt === 'faltas'
                || str_contains($cod, 'falt')
                || (bool) preg_match('/(^|_)fal$/', $cod)
            ) {
                return;
            }
        }
        $bim = (int) ($rascunho['bimestre'] ?? $estado['bimestre'] ?? 0);
        $ano = (int) ($rascunho['ano_letivo'] ?? $estado['ano_letivo'] ?? 0);
        if ($bim < 1 || $bim > 4) {
            return;
        }
        $idFaltas = 0;
        foreach ($this->ferramentas->listarFaltasEventos(300) as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if ($ano > 0 && (int) ($ev['ano_letivo'] ?? 0) !== $ano) {
                continue;
            }
            if ((int) ($ev['bimestre'] ?? 0) === $bim) {
                $idFaltas = (int) ($ev['id'] ?? 0);
                break;
            }
        }
        if ($idFaltas <= 0) {
            return;
        }
        $grupo = 'b' . $bim;
        $comps[] = $this->componenteBoletim('faltas', 'Faltas', 'faltas_evento', [
            'faltas_evento_id' => $idFaltas,
            'layout_group' => $grupo,
            'layout_type' => 'faltas',
        ]);
        $rascunho['componentes'] = $comps;
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    public function montarResumoHumano(array $rascunho): string
    {
        $nome = trim((string) ($rascunho['nome'] ?? 'Evento'));
        $ano = $rascunho['ano_letivo'] ?? '—';
        $bim = $rascunho['bimestre'] ?? null;
        $bimTxt = ($bim !== null && (int) $bim > 0) ? ((int) $bim) . 'º bimestre' : 'sem bimestre';
        $notaMin = $rascunho['nota_minima_aprovacao'] ?? null;
        $exibir = (string) ($rascunho['exibir_em'] ?? 'boletim');

        $linhas = [];
        $linhas[] = $nome;
        $exibirTxt = $exibir;
        $fin = strtolower(trim((string) ($rascunho['finalidade'] ?? 'oficial')));
        if ($fin === 'complementar') {
            $exibirTxt .= $exibir === 'notas' ? ' (extra — curso complementar)' : ' (complementar — cursos extras)';
        } else {
            $exibirTxt .= ' (oficial)';
        }
        $linhas[] = sprintf('Período: %s · %s · Exibir em: %s', $ano, $bimTxt, $exibirTxt);
        $di = trim((string) ($rascunho['default_data_inicio'] ?? ''));
        $df = trim((string) ($rascunho['default_data_fim'] ?? ''));
        if ($di !== '' || $df !== '') {
            $linhas[] = 'Datas de referência: ' . ($di !== '' ? $di : '—') . ' a ' . ($df !== '' ? $df : '—');
        }
        if ($notaMin !== null && $notaMin !== '') {
            $linhas[] = 'Nota mínima para aprovação: ' . $this->formatarMinima((float) $notaMin) . $this->sufixoOrigemMinima($rascunho);
        }

        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        if ($comps !== []) {
            $linhas[] = '';
            $linhas[] = 'Colunas da média:';
            foreach ($comps as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $titulo = trim((string) ($c['nome'] ?? $c['codigo'] ?? 'Bloco'));
                $src = (string) ($c['source_type'] ?? '');
                $calc = (string) ($c['calc_type'] ?? 'media');
                $calcTxt = match ($calc) {
                    'soma' => 'somatória',
                    'maior' => 'maior nota',
                    'ultima' => 'nota do evento',
                    default => 'média',
                };
                $extras = [];
                $papelTxt = $this->rotuloPapel((string) (($c['config']['papel_wizard'] ?? '') ?: ''));
                if ($papelTxt !== '' && $src !== 'calculado') {
                    $extras[] = $papelTxt;
                }
                if ($src === 'provas_sistema' && !empty($c['usar_percentual'])) {
                    $extras[] = 'por acertos/questões';
                }
                $extraTxt = $extras !== [] ? ' · ' . implode(' · ', $extras) : '';
                $como = match ($src) {
                    'provas_sistema' => 'provas (' . $calcTxt . ')'
                        . (!empty($c['tipo_avaliacao_nome']) ? ' · tipo “' . $c['tipo_avaliacao_nome'] . '”' : '')
                        . (!empty($c['filtro_titulo']) ? ' · filtro “' . $c['filtro_titulo'] . '”' : '')
                        . (!empty($c['blocos_ids']) ? ' · ' . count((array) $c['blocos_ids']) . ' bloco(s)' : '')
                        . $extraTxt,
                    'jornadas' => 'jornadas concluídas (' . $calcTxt . ')'
                        . $this->resumoEscopoJornada($c)
                        . (!empty($c['config']['faixas_percentuais']) ? ' · tabela por % concluídas' : (!empty($c['usar_percentual']) ? ' · nota linear (% × 10)' : ''))
                        . $extraTxt,
                    'calculado' => 'calculado: ' . trim((string) ($c['config']['expressao'] ?? '')),
                    'evento_boletim' => 'média final do evento “'
                        . trim((string) ($c['config']['regra_codigo'] ?? '')) . '”'
                        . (trim((string) ($c['config']['componente_codigo'] ?? '')) !== ''
                            ? ' · coluna ' . trim((string) $c['config']['componente_codigo'])
                            : ''),
                    'faltas_evento' => 'faltas do evento #'
                        . (int) ($c['config']['faltas_evento_id'] ?? 0),
                    'nenhuma' => 'coluna vazia (preenchida depois)',
                    default => ($src !== '' ? $src : 'origem indefinida') . ' (' . $calcTxt . ')' . $extraTxt,
                };
                $linhas[] = '• ' . $titulo . ' — ' . $como;
            }
        }

        $series = is_array($rascunho['series_ids'] ?? null) ? $rascunho['series_ids'] : [];
        $turmas = is_array($rascunho['turmas_ids'] ?? null) ? $rascunho['turmas_ids'] : [];
        $materias = is_array($rascunho['materias_ids'] ?? null) ? $rascunho['materias_ids'] : [];
        $linhas[] = '';
        if ($materias === []) {
            $linhas[] = 'Matérias: todas.';
        } else {
            $linhas[] = 'Matérias: ' . count($materias) . ' selecionada(s).';
        }
        if ($this->rascunhoTemMateriaUnica($rascunho)) {
            $linhas[] = 'Juntar matérias iguais: sim (mesma matéria, professores diferentes).';
        }
        if ($series === [] && $turmas === []) {
            $linhas[] = 'Público: todas as séries/turmas (sem filtro).';
        } else {
            $parts = [];
            if ($series !== []) {
                $parts[] = count($series) . ' série(s)';
            }
            if ($turmas !== []) {
                $parts[] = count($turmas) . ' turma(s)';
            }
            $linhas[] = 'Público: ' . implode(', ', $parts) . ' selecionada(s).';
        }

        $grupoResumo = $this->resumoGrupoLinhaDoRascunho($rascunho);
        if ($grupoResumo !== '') {
            $linhas[] = $grupoResumo;
        }

        $linhas[] = '';
        $linhas[] = 'Ainda não está salvo — revise e clique em Aplicar no formulário, depois Salvar evento.';

        return implode("\n", $linhas);
    }

    /**
     * @return list<array{key:string,titulo:string,descricao:string,pecas:list<string>,formula_preset:string}>
     */
    private function modelosParaCatalogo(): array
    {
        $modelos = $this->modelosProntos();
        if (\App\Core\LayoutHelper::isModuleEnabled('jornadas')) {
            return $modelos;
        }

        return array_values(array_filter(
            $modelos,
            static fn (array $modelo): bool => !in_array('jornada', $modelo['pecas'] ?? [], true)
        ));
    }

    /** @return list<array{key:string,titulo:string,descricao:string,pecas:list<string>,formula_preset:string}> */
    public function modelosProntos(): array
    {
        return [
            [
                'key' => 'bimestral_jornada',
                'titulo' => 'Bimestral + Jornada',
                'descricao' => 'Média simples das duas. Ideal para começar.',
                'pecas' => ['bimestral', 'jornada'],
                'formula_preset' => 'media_simples',
            ],
            [
                'key' => 'bimestral_jornada_enac',
                'titulo' => 'Bimestral + Jornada + ENAC',
                'descricao' => 'Média parcial (bimestral + jornada), depois média final com ENAC.',
                'pecas' => ['bimestral', 'jornada', 'enac'],
                'formula_preset' => 'parcial_depois_final',
                'papeis' => ['enac' => 'depois'],
            ],
            [
                'key' => 'semanal_bimestral_jornada',
                'titulo' => 'Semanal + Bimestral + Jornada',
                'descricao' => 'Média dos três blocos.',
                'pecas' => ['semanal', 'bimestral', 'jornada'],
                'formula_preset' => 'media_simples',
            ],
            [
                'key' => 'media3_enac_melhora',
                'titulo' => 'Média 3 + ENAC (só melhora)',
                'descricao' => 'ENAC só entra se melhorar a média.',
                'pecas' => ['semanal', 'bimestral', 'jornada', 'enac'],
                'formula_preset' => 'enac_so_melhora',
                'papeis' => ['enac' => 'so_melhora'],
            ],
            [
                'key' => 'quadro_semanal',
                'titulo' => 'Quadro semanal (S1–S8)',
                'descricao' => 'N/Q por semana, média semanal, prova bimestral, ENAC/trabalho se houver e recuperação na média final. Junta a mesma matéria de professores diferentes.',
                'pecas' => ['semanal', 'bimestral', 'enac'],
                'formula_preset' => 'quadro_semanal',
            ],
        ];
    }

    /**
     * @param list<array{id:int,nome:string,chave_quadro:?string,descricao:?string,criterio_fechamento?:?string,registro_evento?:?string}> $tipos
     * @return list<array{key:string,label:string,hint:string,tipo_avaliacao_id:int,chave_quadro:?string,calc_type:string,usar_percentual:int,fechamento_rotulo:string}>
     */
    public function pecasMeta(array $tipos = []): array
    {
        if ($tipos === []) {
            try {
                $tipos = $this->ferramentas->listarTiposAvaliacao();
            } catch (\Throwable $e) {
                $tipos = [];
            }
        }
        $out = [];
        $usadas = [];
        foreach ($tipos as $t) {
            if (!is_array($t)) {
                continue;
            }
            $id = (int) ($t['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $key = $this->chavePecaDoTipo($t, $usadas);
            $usadas[$key] = true;
            $herdado = $this->fechamentoHerdadoDoTipo($t);
            $out[] = [
                'key' => $key,
                'label' => trim((string) ($t['nome'] ?? ('Tipo #' . $id))),
                'hint' => $this->hintPecaDoTipo($key, $t),
                'tipo_avaliacao_id' => $id,
                'chave_quadro' => isset($t['chave_quadro']) ? (string) $t['chave_quadro'] : null,
                'criterio_fechamento' => isset($t['criterio_fechamento']) ? (string) $t['criterio_fechamento'] : null,
                'registro_evento' => isset($t['registro_evento']) ? (string) $t['registro_evento'] : null,
                'calc_type' => $herdado['calc_type'],
                'usar_percentual' => $herdado['usar_percentual'],
                'fechamento_rotulo' => $herdado['rotulo'],
            ];
        }
        if (\App\Core\LayoutHelper::isModuleEnabled('jornadas')) {
            $out[] = [
                'key' => 'jornada',
                'label' => 'Jornada do aluno',
                'hint' => 'Nota pela quantidade de jornadas concluídas (quantitativo) ou tabela por faixa. Pode entrar na média ou só melhorar.',
                'tipo_avaliacao_id' => 0,
                'chave_quadro' => null,
                'criterio_fechamento' => null,
                'registro_evento' => null,
                'calc_type' => 'media',
                'usar_percentual' => 1,
                'fechamento_rotulo' => '',
            ];
        }
        return $out;
    }

    /** @return list<array{key:string,label:string,hint:string}> */
    public static function papeisMeta(): array
    {
        return [
            ['key' => 'media', 'label' => 'Entra na média', 'hint' => 'Compõe a 1ª média (média parcial) com as outras neste papel.'],
            ['key' => 'depois', 'label' => 'Entra depois (média final)', 'hint' => 'Entra numa segunda média, depois da média parcial. Ex.: \ENAC.'],
            ['key' => 'so_melhora', 'label' => 'Só melhora', 'hint' => 'Se for menor que a média, a média permanece.'],
            ['key' => 'substitui', 'label' => 'Substitui se for maior', 'hint' => 'Usa esta nota quando ela for maior que a média (recuperação).'],
            ['key' => 'exibe', 'label' => 'Só mostra', 'hint' => 'Aparece no boletim, mas não entra no cálculo da média.'],
        ];
    }

    /**
     * @param array{id?:int,chave_quadro?:?string} $tipo
     * @param array<string,bool> $usadas
     */
    private function chavePecaDoTipo(array $tipo, array $usadas = []): string
    {
        $canon = $this->chaveCanonicaQuadro($tipo['chave_quadro'] ?? null);
        if ($canon === '') {
            $canon = $this->chaveCanonicaPorTexto((string) ($tipo['nome'] ?? ''));
        }
        if ($canon !== '' && empty($usadas[$canon])) {
            return $canon;
        }
        return 'tipo_' . (int) ($tipo['id'] ?? 0);
    }

    private function chaveCanonicaQuadro($chave): string
    {
        $ch = strtolower(trim((string) $chave));
        return match ($ch) {
            'semanal' => 'semanal',
            'prova_bim', 'bimestral' => 'bimestral',
            'enac' => 'enac',
            'trabalho', 'trab' => 'trabalho',
            'participacao', 'participa', 'part' => 'participacao',
            'recuperacao', 'recupera', 'rec' => 'recuperacao',
            default => '',
        };
    }

    private function chaveCanonicaPorTexto(string $texto): string
    {
        $txt = mb_strtolower(trim($texto));
        if ($txt === '') {
            return '';
        }
        if (str_contains($txt, 'enac')) {
            return 'enac';
        }
        if (str_contains($txt, 'semanal')) {
            return 'semanal';
        }
        if (str_contains($txt, 'trabalho')) {
            return 'trabalho';
        }
        if (str_contains($txt, 'bimestral')) {
            return 'bimestral';
        }
        if (str_contains($txt, 'participa')) {
            return 'participacao';
        }
        if (str_contains($txt, 'recupera')) {
            return 'recuperacao';
        }
        return '';
    }

    /** @param array{nome?:string,descricao?:?string} $tipo */
    private function hintPecaDoTipo(string $key, array $tipo): string
    {
        return match ($key) {
            'semanal' => 'Monta o quadro S1–S8 com N/Q (Bloco A ímpares, Bloco B pares).',
            'bimestral' => 'Notas lançadas da prova bimestral (nota do evento).',
            'enac' => 'Avaliação ENAC. Escolha se entra na média, só melhora ou só aparece.',
            'trabalho' => 'Trabalho / atividade. Qualquer papel: média, só melhora, substitui ou só mostra.',
            'participacao' => 'Participação. Mesmas opções de papel das outras peças.',
            'recuperacao' => 'Recuperação. Padrão: substitui a média se for maior.',
            default => trim((string) ($tipo['descricao'] ?? 'Tipo de avaliação da escola. Escolha como a nota entra no boletim.')),
        };
    }

    private function papelPadrao(string $peca): string
    {
        return $peca === 'recuperacao' ? 'substitui' : 'media';
    }

    /** Peças de evento único (prova lançada) começam em "nota do evento", não em média. */
    private function calcTypePadrao(string $peca): string
    {
        $herdado = $this->fechamentoParaPeca($peca);
        if ($herdado !== null) {
            return $herdado['calc_type'];
        }
        return match ($peca) {
            'bimestral', 'enac', 'trabalho', 'participacao', 'recuperacao' => 'ultima',
            default => 'media',
        };
    }

    private function usarPercentualPadrao(string $peca): int
    {
        $herdado = $this->fechamentoParaPeca($peca);
        if ($herdado !== null) {
            return $herdado['usar_percentual'];
        }
        return $peca === 'semanal' ? 1 : 0;
    }

    /**
     * Média / somatória / acertos vêm do Tipo de Nota, não da peça do boletim.
     *
     * @param array<string,mixed> $tipo
     * @return array{calc_type:string,usar_percentual:int,rotulo:string}
     */
    private function fechamentoHerdadoDoTipo(array $tipo): array
    {
        $registro = strtolower(trim((string) ($tipo['registro_evento'] ?? 'nota')));
        $criterio = strtolower(trim((string) ($tipo['criterio_fechamento'] ?? 'ultima')));
        $usarPerc = ($registro === 'acertos_questoes' || $criterio === 'aproveitamento_nq') ? 1 : 0;
        $calc = match ($criterio) {
            'media', 'soma', 'maior', 'ultima' => $criterio,
            'aproveitamento_nq' => 'media',
            default => 'ultima',
        };
        $rotuloCalc = match ($calc) {
            'media' => 'média',
            'soma' => 'somatória',
            'maior' => 'maior nota',
            default => 'nota do evento',
        };
        $rotulo = $usarPerc
            ? $rotuloCalc . ' · acertos/questões'
            : $rotuloCalc . ' · nota lançada';
        return [
            'calc_type' => $calc,
            'usar_percentual' => $usarPerc,
            'rotulo' => $rotulo,
        ];
    }

    /**
     * @return array{calc_type:string,usar_percentual:int,rotulo:string}|null
     */
    private function fechamentoParaPeca(string $peca, int $tipoId = 0): ?array
    {
        if ($peca === 'jornada') {
            return null;
        }
        if ($tipoId <= 0) {
            foreach ($this->pecasMeta($this->tiposAvaliacaoCatalogo()) as $meta) {
                if ((string) ($meta['key'] ?? '') !== $peca) {
                    continue;
                }
                $tipoId = (int) ($meta['tipo_avaliacao_id'] ?? 0);
                break;
            }
        }
        if ($tipoId <= 0) {
            return null;
        }
        foreach ($this->tiposAvaliacaoCatalogo() as $tipo) {
            if ((int) ($tipo['id'] ?? 0) === $tipoId) {
                return $this->fechamentoHerdadoDoTipo($tipo);
            }
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    private function tiposAvaliacaoCatalogo(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $cache = $this->ferramentas->listarTiposAvaliacao();
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }

    private function pecaPermiteAcertosQuestoes(string $peca): bool
    {
        return in_array($peca, ['semanal', 'bimestral', 'enac'], true)
            || str_starts_with($peca, 'tipo_');
    }

    private function normalizarPapel($raw, string $peca = ''): string
    {
        $p = strtolower(trim((string) $raw));
        if (in_array($p, self::PAPEIS, true)) {
            return $p;
        }
        return $this->papelPadrao($peca);
    }

    private function rotuloPapel(string $papel): string
    {
        return match (strtolower(trim($papel))) {
            'media' => 'entra na média',
            'depois' => 'entra depois (média final)',
            'so_melhora' => 'só melhora',
            'substitui' => 'substitui se for maior',
            'exibe' => 'só mostra',
            default => '',
        };
    }

    /**
     * Códigos de coluna que a fórmula pode citar além das peças (média, ENAC…).
     *
     * @return list<string>
     */
    private function codigosLivresFormula(): array
    {
        return [
            'media_sem', 'media_bim', 'media_final', 'prova_bim',
        ];
    }

    /** @param array<string,mixed> $estado */
    private function temFormulaMontada(array $estado): bool
    {
        if (($estado['formula_tokens'] ?? []) !== []) {
            return true;
        }
        foreach ((array) ($estado['formulas_blocos'] ?? []) as $toks) {
            if (is_array($toks) && $toks !== []) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $estado */
    private function temPecaComPapelMedia(array $estado): bool
    {
        foreach ((array) ($estado['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '') {
                continue;
            }
            $opts = is_array($estado['pecas_opcoes'][$p] ?? null) ? $estado['pecas_opcoes'][$p] : [];
            if ($this->normalizarPapel($opts['papel'] ?? '', $p) === 'media') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $estado
     * @param array{papeis?:array<string,string>,formula_preset?:string} $modelo
     */
    private function aplicarPapeisDoModelo(array &$estado, array $modelo): void
    {
        $papeis = is_array($modelo['papeis'] ?? null) ? $modelo['papeis'] : [];
        if ($papeis === [] && ($modelo['formula_preset'] ?? '') === 'enac_so_melhora') {
            $papeis = ['enac' => 'so_melhora'];
        } elseif ($papeis === [] && ($modelo['formula_preset'] ?? '') === 'parcial_depois_final') {
            $papeis = ['enac' => 'depois'];
        }
        foreach ($papeis as $k => $papel) {
            $k = strtolower(trim((string) $k));
            if ($k === '' || !isset($estado['pecas_opcoes'][$k]) || !is_array($estado['pecas_opcoes'][$k])) {
                continue;
            }
            $estado['pecas_opcoes'][$k]['papel'] = $this->normalizarPapel($papel, $k);
        }
    }

    /** @return list<array{key:string,titulo:string,descricao:string}> */
    public function formulasPresetMeta(): array
    {
        return [
            [
                'key' => 'media_simples',
                'titulo' => 'Uma média só',
                'descricao' => 'Todas as peças da 1ª etapa pesam igual. Ex.: (bimestral + semanal + ENAC) / 3.',
            ],
            [
                'key' => 'parcial_depois_final',
                'titulo' => 'Média parcial, depois média final',
                'descricao' => 'Primeiro a média das peças da 1ª etapa. Depois junta com as que entram depois. Ex.: ((bimestral + semanal) / 2 + ENAC) / 2.',
            ],
            [
                'key' => 'enac_so_melhora',
                'titulo' => 'A segunda etapa só melhora',
                'descricao' => 'A média parcial vale; ENAC (ou outra peça de depois) só entra se melhorar.',
            ],
        ];
    }

    /**
     * @param list<string> $pecas
     * @return list<array{key:string,titulo:string,descricao:string}>
     */
    public function formulasParaPecas(array $pecas): array
    {
        return $this->formulasPresetMeta();
    }

    /** @param array<string,mixed> $estado */
    private function normalizarEstado(array $estado): array
    {
        $base = $this->estadoPadrao();
        $merged = array_merge($base, $estado);
        $merged['passo'] = in_array((string) ($merged['passo'] ?? ''), self::PASSOS, true)
            ? (string) $merged['passo']
            : 'inicio';
        $merged['origem'] = in_array((string) ($merged['origem'] ?? ''), ['zero', 'modelo', 'clonar', 'formulario', 'chat', 'quadro-impressao', 'santa-maria', 'composto'], true)
            ? (string) $merged['origem']
            : 'zero';
        $merged['quadro_livre'] = (!empty($merged['quadro_livre']) || $merged['origem'] === 'quadro-impressao')
            && !in_array($merged['origem'], ['santa-maria', 'composto'], true)
            ? 1
            : 0;
        $merged['modelo_santa_maria'] = (!empty($merged['modelo_santa_maria']) || $merged['origem'] === 'santa-maria') ? 1 : 0;
        if (!empty($merged['modelo_santa_maria'])) {
            $merged['origem'] = 'santa-maria';
            $merged['quadro_livre'] = 0;
            $merged['exibir_em'] = 'boletim';
        }
        if (!empty($merged['layout_boletim_id'])) {
            $merged['layout_boletim_id'] = (int) $merged['layout_boletim_id'];
        }
        if (!empty($merged['rascunho_preservado']) && is_array($merged['rascunho_preservado'])) {
            // mantém
        } else {
            $merged['rascunho_preservado'] = null;
        }
        $merged['modelo_key'] = trim((string) ($merged['modelo_key'] ?? ''));
        $merged['clonar_regra_id'] = !empty($merged['clonar_regra_id']) ? (int) $merged['clonar_regra_id'] : null;
        $merged['nome'] = trim((string) ($merged['nome'] ?? ''));
        $merged['codigo'] = trim((string) ($merged['codigo'] ?? ''));
        $merged['ano_letivo'] = (int) ($merged['ano_letivo'] ?? date('Y'));
        $merged['bimestre'] = (int) ($merged['bimestre'] ?? 0);
        $merged['exibir_em'] = in_array((string) ($merged['exibir_em'] ?? ''), ['notas', 'boletim'], true)
            ? (string) $merged['exibir_em']
            : 'notas';
        $merged['finalidade'] = $this->normalizarFinalidade($merged['finalidade'] ?? 'oficial');
        $merged['boletim_id'] = (int) ($merged['boletim_id'] ?? 0);
        $merged['grupo_regras_notas_id'] = (int) ($merged['grupo_regras_notas_id'] ?? 0);
        $merged['nota_minima_origem'] = in_array((string) ($merged['nota_minima_origem'] ?? ''), ['padrao', 'regra', 'evento'], true)
            ? (string) $merged['nota_minima_origem']
            : '';
        $merged['nota_minima_regra_nome'] = trim((string) ($merged['nota_minima_regra_nome'] ?? ''));
        $merged['nota_minima_aprovacao'] = isset($merged['nota_minima_aprovacao']) && $merged['nota_minima_aprovacao'] !== ''
            ? (float) $merged['nota_minima_aprovacao']
            : 6.0;
        $merged['escala_max_padrao'] = $this->normalizarEscalaMaxima(
            $merged['escala_max_padrao'] ?? null,
            $this->escalaMaxPadraoDoAno((int) ($merged['ano_letivo'] ?? 0))
        );
        $merged['round_mode'] = in_array((string) ($merged['round_mode'] ?? ''), ['none', 'half', 'places'], true)
            ? (string) $merged['round_mode']
            : 'none';
        $merged['decimal_places'] = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais(
            $merged['decimal_places'] ?? 2
        );
        $pecas = [];
        foreach ((array) ($merged['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p !== '' && (bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $p)) {
                $pecas[] = $p;
            }
        }
        $merged['pecas'] = array_values(array_unique($pecas));
        $eventosIds = [];
        foreach ((array) ($merged['eventos_ids'] ?? []) as $eventoId) {
            $eventoId = (int) $eventoId;
            if ($eventoId > 0 && !in_array($eventoId, $eventosIds, true)) {
                $eventosIds[] = $eventoId;
            }
        }
        $merged['eventos_ids'] = $eventosIds;
        $merged['eventos_ids_manual'] = (int) ($merged['eventos_ids_manual'] ?? 0) === 1 ? 1 : 0;
        $subeventosIds = [];
        foreach ((array) ($merged['subeventos_ids'] ?? []) as $subId) {
            $subId = (int) $subId;
            if ($subId > 0 && !in_array($subId, $subeventosIds, true)) {
                $subeventosIds[] = $subId;
            }
        }
        $merged['subeventos_ids'] = $subeventosIds;
        $merged['subeventos_ids_manual'] = (int) ($merged['subeventos_ids_manual'] ?? 0) === 1 ? 1 : 0;
        $merged['pecas_opcoes'] = $this->mesclarPecasOpcoes(
            $this->pecasOpcoesPadrao($merged['pecas'], (int) ($merged['bimestre'] ?? 0)),
            is_array($merged['pecas_opcoes'] ?? null) ? $merged['pecas_opcoes'] : []
        );
        $merged['formula_preset'] = trim((string) ($merged['formula_preset'] ?? 'media_simples')) ?: 'media_simples';
        $merged['formula_tokens'] = $this->normalizarFormulaTokens($merged['formula_tokens'] ?? []);
        $merged['formulas_blocos'] = $this->normalizarFormulasBlocos($merged['formulas_blocos'] ?? []);
        $merged['formulas_materias_blocos'] = $this->normalizarFormulasMateriasBlocos($merged['formulas_materias_blocos'] ?? []);
        $merged['nomes_blocos'] = $this->normalizarNomesBlocos($merged['nomes_blocos'] ?? []);
        $merged['escalas_blocos'] = $this->normalizarEscalasBlocos($merged['escalas_blocos'] ?? []);
        $merged['media_ciclo_blocos'] = $this->normalizarMediaCicloBlocos($merged['media_ciclo_blocos'] ?? []);
        $merged['media_ciclo_destinos'] = $this->normalizarMediaCicloDestinos($merged['media_ciclo_destinos'] ?? []);
        $merged['nomes_limpos'] = $this->normalizarNomesLimpos($merged['nomes_limpos'] ?? []);
        $merged['blocos_calc'] = $this->normalizarColunasOrdem($merged['blocos_calc'] ?? []);
        $merged['bloco_calc'] = strtolower(trim((string) ($merged['bloco_calc'] ?? '')));
        if ($merged['bloco_calc'] !== '' && !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $merged['bloco_calc'])) {
            $merged['bloco_calc'] = '';
        }
        $merged['materia_calc'] = max(0, (int) ($merged['materia_calc'] ?? 0));
        if (
            $merged['formula_tokens'] !== []
            && empty($merged['formulas_blocos']['media_final'])
            && $merged['materia_calc'] <= 0
            && ($merged['bloco_calc'] === '' || $merged['bloco_calc'] === 'media_final')
        ) {
            $merged['formulas_blocos']['media_final'] = $merged['formula_tokens'];
        }
        if ($merged['bloco_calc'] !== '') {
            $midCalc = $merged['materia_calc'];
            $porMatCalc = $merged['formulas_materias_blocos'][$merged['bloco_calc']] ?? null;
            if ($porMatCalc instanceof \stdClass) {
                $porMatCalc = (array) $porMatCalc;
            }
            if ($midCalc > 0 && is_array($porMatCalc) && isset($porMatCalc[$midCalc])) {
                $merged['formula_tokens'] = $porMatCalc[$midCalc];
            } elseif ($midCalc <= 0 && isset($merged['formulas_blocos'][$merged['bloco_calc']])) {
                $merged['formula_tokens'] = $merged['formulas_blocos'][$merged['bloco_calc']];
            }
        }
        $codigosLivres = array_flip($this->codigosLivresFormula());
        foreach (array_merge(
            $merged['blocos_calc'],
            array_keys($merged['formulas_blocos']),
            array_keys($merged['formulas_materias_blocos']),
            array_keys($merged['nomes_blocos'])
        ) as $extra) {
            $extra = strtolower(trim((string) $extra));
            if ($extra !== '') {
                $codigosLivres[$extra] = true;
            }
        }
        $pecasOk = array_flip($merged['pecas']);
        $filtrarTok = static function ($t) use ($pecasOk, $codigosLivres): bool {
            if (!is_array($t)) {
                return false;
            }
            if (($t['type'] ?? '') !== 'peca') {
                return true;
            }
            $v = (string) ($t['value'] ?? '');
            return isset($pecasOk[$v]) || isset($codigosLivres[$v]);
        };
        $merged['formula_tokens'] = $this->envolverSomaAntesDeDivisao($merged['formula_tokens']);
        foreach ($merged['formulas_blocos'] as $codFb => $toksFb) {
            $merged['formulas_blocos'][$codFb] = $this->envolverSomaAntesDeDivisao(
                is_array($toksFb) ? $toksFb : []
            );
        }
        foreach ($merged['formulas_materias_blocos'] as $codFb => $porMat) {
            if ($porMat instanceof \stdClass) {
                $porMat = (array) $porMat;
                $merged['formulas_materias_blocos'][$codFb] = $porMat;
            }
            if (!is_array($porMat)) {
                continue;
            }
            foreach ($porMat as $midFb => $toksFb) {
                $merged['formulas_materias_blocos'][$codFb][$midFb] = $this->envolverSomaAntesDeDivisao(
                    array_values(array_filter(
                        is_array($toksFb) ? $toksFb : [],
                        $filtrarTok
                    ))
                );
            }
            if ($merged['formulas_materias_blocos'][$codFb] === []) {
                $merged['formulas_materias_blocos'][$codFb] = new \stdClass();
            }
        }
        $temPecaTok = false;
        foreach ($merged['formula_tokens'] as $t) {
            if (is_array($t) && ($t['type'] ?? '') === 'peca') {
                $temPecaTok = true;
                break;
            }
        }
        if (!$temPecaTok) {
            $merged['formula_tokens'] = [];
        } else {
            $merged['formula_tokens'] = $this->envolverSomaAntesDeDivisao($merged['formula_tokens']);
        }
        $merged['formula_custom'] = trim((string) ($merged['formula_custom'] ?? ''));
        if ($merged['formula_tokens'] === []) {
            $merged['formula_custom'] = '';
        }
        $merged['data_inicio'] = $this->normalizarDataCampo($merged['data_inicio'] ?? '');
        $merged['data_fim'] = $this->normalizarDataCampo($merged['data_fim'] ?? '');
        $merged['jornada_modo'] = in_array((string) ($merged['jornada_modo'] ?? ''), ['todas', 'bimestre', 'selecionadas'], true)
            ? (string) $merged['jornada_modo']
            : 'bimestre';
        if ($merged['jornada_modo'] === 'todas' && in_array('jornada', $merged['pecas'], true)) {
            $merged['jornada_modo'] = 'bimestre';
        }
        $merged['jornada_ids'] = array_values(array_unique(array_filter(
            array_map('intval', (array) ($merged['jornada_ids'] ?? [])),
            static fn ($id) => $id > 0
        )));
        $bims = [];
        foreach ((array) ($merged['jornada_bimestres'] ?? []) as $b) {
            $b = (int) $b;
            if ($b >= 1 && $b <= 4) {
                $bims[] = $b;
            }
        }
        $merged['jornada_bimestres'] = array_values(array_unique($bims));
        $merged['jornada_nota_modo'] = in_array((string) ($merged['jornada_nota_modo'] ?? ''), ['linear', 'faixas'], true)
            ? (string) $merged['jornada_nota_modo']
            : 'linear';
        $merged['jornada_distribuicao_notas'] = ((string) ($merged['jornada_distribuicao_notas'] ?? '')) === 'nota_unica_todas_linhas'
            ? 'nota_unica_todas_linhas'
            : 'por_materia';
        $merged['jornada_materias_tocada'] = !empty($merged['jornada_materias_tocada']);
        $materiasJornada = [];
        foreach ((array) ($merged['jornada_materias_ids'] ?? []) as $midJornada) {
            $midJornada = (int) $midJornada;
            if ($midJornada > 0) {
                $materiasJornada[] = $midJornada;
            }
        }
        $merged['jornada_materias_ids'] = array_values(array_unique($materiasJornada));
        $merged['jornada_faixas'] = $this->normalizarFaixasJornada($merged['jornada_faixas'] ?? null);
        $merged['series_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['series_ids'] ?? [])))));
        $merged['turmas_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['turmas_ids'] ?? [])))));
        $merged['materias_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['materias_ids'] ?? [])), static fn ($id) => $id > 0)));
        $merged['aluno_preview_id'] = max(0, (int) ($merged['aluno_preview_id'] ?? 0));
        $merged['grupo_linha'] = $this->normalizarGrupoLinha($merged['grupo_linha'] ?? null);
        $flagExplicita = array_key_exists('materia_unica', $merged);
        $merged['materia_unica_tocada'] = !empty($merged['materia_unica_tocada']);
        if ($merged['materia_unica_tocada'] || $flagExplicita) {
            $merged['materia_unica'] = !empty($merged['materia_unica']) ? 1 : 0;
        } else {
            $flag = 0;
            foreach ($merged['pecas_opcoes'] as $opts) {
                if (!empty($opts['materia_unica'])) {
                    $flag = 1;
                    break;
                }
            }
            $merged['materia_unica'] = $flag;
        }
        $merged['materia_unica_modo'] = $this->normalizarMateriaUnicaModo($merged['materia_unica_modo'] ?? null);
        if ($merged['materia_unica_modo'] === 'soma' && empty($merged['materia_unica_tocada'])) {
            $modoInferido = $this->inferirMateriaUnicaModoDeComponentes(
                is_array($merged['rascunho_preservado']['componentes'] ?? null)
                    ? $merged['rascunho_preservado']['componentes']
                    : []
            );
            if ($modoInferido !== '') {
                $merged['materia_unica_modo'] = $modoInferido;
            } else {
                foreach ($merged['pecas_opcoes'] as $opts) {
                    if (!is_array($opts)) {
                        continue;
                    }
                    if (!empty($opts['materia_unica_modo'])) {
                        $merged['materia_unica_modo'] = $this->normalizarMateriaUnicaModo($opts['materia_unica_modo']);
                        break;
                    }
                }
            }
        }
        $this->aplicarMateriaUnicaNasPecasOpcoes($merged);
        $merged['colunas_ordem'] = $this->normalizarColunasOrdem($merged['colunas_ordem'] ?? []);
        $merged['colunas_ocultas'] = $this->normalizarColunasOcultas($merged['colunas_ocultas'] ?? []);
        $merged['linhas_layout'] = $this->normalizarLinhasLayout($merged['linhas_layout'] ?? []);
        $merged['fontes_bimestres'] = $this->normalizarFontesBimestres($merged['fontes_bimestres'] ?? []);
        $merged['fontes_faltas'] = $this->normalizarFontesBimestres($merged['fontes_faltas'] ?? []);
        $merged['demonstrativos_ids'] = $this->idsDemonstrativos($merged);
        $merged['eventos_avaliativos_ids'] = $this->idsEventosAvaliativos($merged);
        $merged['eventos_avaliativos_subs'] = $this->normalizarEventosAvaliativosSubs($merged['eventos_avaliativos_subs'] ?? []);
        $merged['cabecalhos_manuais'] = !empty($merged['cabecalhos_manuais']) ? 1 : 0;
        $merged['quadro_chips_manuais'] = !empty($merged['quadro_chips_manuais']) ? 1 : 0;
        if ($merged['linhas_layout'] !== []) {
            $merged['cabecalhos_manuais'] = 1;
            $merged['quadro_chips_manuais'] = 1;
        }
        if (!is_array($merged['titulos_periodo_ok'] ?? null)) {
            $merged['titulos_periodo_ok'] = [];
        }
        $this->reidratarNomesDosComponentes($merged);
        $this->reidratarEscalasDosComponentes($merged);
        $this->reidratarMediaCicloDosComponentes($merged);
        $this->reidratarFormulasDosComponentes($merged);
        return $merged;
    }

    /**
     * O título da coluna fica no nome do componente. Ao reabrir, devolve esse nome
     * para nomes_blocos sem apagar um título que o usuário acabou de digitar.
     *
     * @param array<string,mixed> $estado
     */
    private function reidratarNomesDosComponentes(array &$estado): void
    {
        if (!is_array($estado['nomes_blocos'] ?? null)) {
            $estado['nomes_blocos'] = [];
        }
        $rascunho = is_array($estado['rascunho_preservado'] ?? null) ? $estado['rascunho_preservado'] : [];
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $c) {
            if (!is_array($c) || count($estado['nomes_blocos']) >= 40) {
                break;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === '_semanal' || (bool) preg_match('/^s[1-8]$/', $cod)) {
                continue;
            }
            if (!(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $cod)) {
                continue;
            }
            if (trim((string) ($estado['nomes_blocos'][$cod] ?? '')) !== '') {
                continue;
            }
            if ($this->colunaTituloFoiLimpo($estado, $cod)) {
                continue;
            }
            if ($this->componenteVemDeEventoAvaliativo($c)) {
                continue;
            }
            $nome = trim(mb_substr((string) ($c['nome'] ?? ''), 0, 60));
            if ($nome === '') {
                continue;
            }
            $estado['nomes_blocos'][$cod] = $nome;
        }
    }

    /**
     * Ao reabrir, devolve a escala_max do componente para o editor da fórmula.
     *
     * @param array<string,mixed> $estado
     */
    private function reidratarEscalasDosComponentes(array &$estado): void
    {
        if (!is_array($estado['escalas_blocos'] ?? null)) {
            $estado['escalas_blocos'] = [];
        }
        $rascunho = is_array($estado['rascunho_preservado'] ?? null) ? $estado['rascunho_preservado'] : [];
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $c) {
            if (!is_array($c) || count($estado['escalas_blocos']) >= 40) {
                break;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === '_semanal' || (bool) preg_match('/^s[1-8]$/', $cod)) {
                continue;
            }
            if (!(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $cod)) {
                continue;
            }
            if (isset($estado['escalas_blocos'][$cod])) {
                continue;
            }
            $escala = round((float) ($c['escala_max'] ?? 0), 2);
            if ($escala < 0.01) {
                continue;
            }
            $estado['escalas_blocos'][$cod] = min(999.0, $escala);
        }
    }

    /**
     * Flag “Média do Ciclo” gravada no componente volta para o editor.
     *
     * @param array<string,mixed> $estado
     */
    private function reidratarMediaCicloDosComponentes(array &$estado): void
    {
        if (!is_array($estado['media_ciclo_blocos'] ?? null)) {
            $estado['media_ciclo_blocos'] = [];
        }
        if (!is_array($estado['media_ciclo_destinos'] ?? null)) {
            $estado['media_ciclo_destinos'] = [];
        }
        $rascunho = is_array($estado['rascunho_preservado'] ?? null) ? $estado['rascunho_preservado'] : [];
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $src = (string) ($c['source_type'] ?? '');
            if ($src !== 'calculado' && $src !== 'evento_boletim') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($cfg === [] && is_string($c['config_json'] ?? null) && trim((string) $c['config_json']) !== '') {
                $decoded = json_decode((string) $c['config_json'], true);
                if (is_array($decoded)) {
                    $cfg = $decoded;
                }
            }
            if (empty($cfg['media_ciclo'])) {
                continue;
            }
            $estado['media_ciclo_blocos'][$cod] = 1;
            if (empty($estado['media_ciclo_destinos'][$cod])) {
                $dest = strtolower(trim((string) (
                    $cfg['media_ciclo_destino']
                    ?? $cfg['layout_group']
                    ?? ''
                )));
                if (in_array($dest, ['b1', 'b2', 'b3', 'b4', 's1', 's2', 'final'], true)) {
                    $estado['media_ciclo_destinos'][$cod] = $dest;
                }
            }
        }
    }

    /**
     * Ao reabrir o evento, a fórmula gravada na coluna volta para os blocos da tela.
     *
     * @param array<string,mixed> $estado
     */
    private function reidratarFormulasDosComponentes(array &$estado): void
    {
        $rascunho = is_array($estado['rascunho_preservado'] ?? null) ? $estado['rascunho_preservado'] : [];
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        if ($comps === []) {
            return;
        }
        if (!is_array($estado['formulas_blocos'] ?? null)) {
            $estado['formulas_blocos'] = [];
        }
        if (!is_array($estado['formulas_materias_blocos'] ?? null)) {
            $estado['formulas_materias_blocos'] = [];
        }
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === 'media_sem') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $agregar = is_array($cfg['agregar_nq'] ?? null) ? $cfg['agregar_nq'] : [];
            if ($agregar !== []) {
                continue;
            }
            if (!isset($estado['formulas_blocos'][$cod]) || $estado['formulas_blocos'][$cod] === []) {
                $toks = $this->tokensDaExpressao((string) ($cfg['expressao'] ?? ''));
                if ($toks !== []) {
                    $estado['formulas_blocos'][$cod] = $toks;
                }
            }
            $porMateria = is_array($cfg['formula_materias'] ?? null) ? $cfg['formula_materias'] : [];
            if ($porMateria === []) {
                continue;
            }
            if (!isset($estado['formulas_materias_blocos'][$cod]) || !is_array($estado['formulas_materias_blocos'][$cod])) {
                $estado['formulas_materias_blocos'][$cod] = [];
            }
            foreach ($porMateria as $midRaw => $exprRaw) {
                $mid = (int) $midRaw;
                if ($mid <= 0 || isset($estado['formulas_materias_blocos'][$cod][$mid])) {
                    continue;
                }
                $toksEx = $this->tokensDaExpressao((string) $exprRaw);
                if ($toksEx !== []) {
                    $estado['formulas_materias_blocos'][$cod][$mid] = $toksEx;
                }
            }
        }
        if (
            (int) ($estado['materia_calc'] ?? 0) <= 0
            && $estado['bloco_calc'] !== ''
            && ($estado['formula_tokens'] ?? []) === []
            && isset($estado['formulas_blocos'][$estado['bloco_calc']])
        ) {
            $estado['formula_tokens'] = $estado['formulas_blocos'][$estado['bloco_calc']];
        }
    }

    /**
     * @return list<array{type:string,value:string,label:string}>
     */
    private function tokensDaExpressao(string $expr): array
    {
        $expr = trim($expr);
        $expr = str_replace(['×', '÷', '−', '–', '—'], ['*', '/', '-', '-', '-'], $expr);
        $expr = preg_replace('/\bmedia_ponderada_substituta\b/i', 'media_ponderada', $expr) ?? $expr;
        $expr = preg_replace('/\bm[eé]dia\s+ponderada(?:\s+substituta)?\b/iu', 'media_ponderada', $expr) ?? $expr;
        $regexTokens = '/(?:>=|<=|!=|<>|==)'
            . '|\b(?:verdadeiro|falso|true|false|sim|nao|não)\b'
            . '|\b(?:maior|menor|max|min|chamada|substituta|composicao|media_ponderada|media_lancado|se|if)\b'
            . '|\d+(?:\.\d+)?'
            . '|[A-Za-z_][A-Za-z0-9_]*'
            . '|[+\-*\/(),|]'
            . '|(?<![=!<>])=(?!=)'
            . '|[<>]/u';
        if ($expr === '' || !preg_match_all($regexTokens, $expr, $achados)) {
            return [];
        }
        $partes = $achados[0];
        $rotulos = [
            'media_sem' => 'Prova Semanal',
            'semanal' => 'Prova Semanal',
            'prova_bim' => 'Avaliação Bimestral',
            'bimestral' => 'Avaliação Bimestral',
            'jornada' => 'Jornada do aluno',
            'enac' => 'ENAC',
            'media_bim' => 'Média Bim',
            'media_final' => 'Resultado Final',
            'trab' => 'Trabalho',
            'trabalho' => 'Trabalho',
            'part' => 'Participação',
            'participacao' => 'Participação',
            'rec' => 'Recuperação',
            'recuperacao' => 'Recuperação',
            'faltas' => 'Faltas',
        ];
        $tokens = [];
        $n = count($partes);
        for ($i = 0; $i < $n; $i++) {
            $p = (string) $partes[$i];
            $baixo = mb_strtolower($p, 'UTF-8');
            if (in_array($baixo, ['maior', 'menor', 'max', 'min', 'chamada', 'substituta', 'composicao', 'media_ponderada', 'media_lancado', 'se', 'if'], true) && (($partes[$i + 1] ?? '') === '(')) {
                $fnVal = ($baixo === 'min' || $baixo === 'menor')
                    ? 'min'
                    : (($baixo === 'chamada' || $baixo === 'substituta')
                        ? 'chamada'
                        : ($baixo === 'composicao'
                            ? 'composicao'
                            : ($baixo === 'media_ponderada'
                                ? 'media_ponderada'
                                : ($baixo === 'media_lancado'
                                    ? 'media_lancado'
                                    : (($baixo === 'se' || $baixo === 'if') ? 'se' : 'max')))));
                $fnLabel = $fnVal === 'min'
                    ? 'menor ('
                    : ($fnVal === 'chamada'
                        ? 'nota substituta ('
                        : ($fnVal === 'composicao'
                            ? 'média c/ rec ('
                            : ($fnVal === 'media_ponderada'
                                ? 'média ponderada ('
                                : ($fnVal === 'media_lancado'
                                    ? 'média ('
                                    : ($fnVal === 'se' ? 'se (' : 'maior (')))));
                $tokens[] = [
                    'type' => 'fn',
                    'value' => $fnVal,
                    'label' => $fnLabel,
                ];
                $i++;
                continue;
            }
            // 0/1 são números na fórmula; booleanos só por palavra (verdadeiro/falso/…).
            $litsCanon = [
                'true' => 'verdadeiro',
                'verdadeiro' => 'verdadeiro',
                'sim' => 'verdadeiro',
                'false' => 'falso',
                'falso' => 'falso',
                'nao' => 'falso',
                'não' => 'falso',
            ];
            if (isset($litsCanon[$baixo])) {
                $canon = $litsCanon[$baixo];
                $tokens[] = ['type' => 'lit', 'value' => $canon, 'label' => $canon];
                continue;
            }
            if (preg_match('/^\d/', $p)) {
                $tokens[] = ['type' => 'num', 'value' => $p, 'label' => $p];
                continue;
            }
            if (in_array($p, ['>', '<', '>=', '<=', '=', '==', '!=', '<>', '+', '-', '*', '/', '(', ')', ',', '|'], true)) {
                $opVal = $p === '<>' ? '!=' : $p;
                $simbolo = match ($opVal) {
                    '*' => '×',
                    '/' => '÷',
                    '-' => '−',
                    '>=' => '≥',
                    '<=' => '≤',
                    '!=' => '≠',
                    default => $opVal,
                };
                $tokens[] = ['type' => 'op', 'value' => $opVal, 'label' => $simbolo];
                continue;
            }
            if (preg_match('/^[A-Za-z_]/', $p)) {
                $labelPeca = $rotulos[$baixo] ?? null;
                if ($labelPeca === null && preg_match('/^b[1-4]_(pp|pu|pe|ch|mu|fal)$/', $baixo, $mm)) {
                    $labelPeca = strtoupper($mm[1] === 'fal' ? 'FAL' : $mm[1]);
                }
                $tokens[] = [
                    'type' => 'peca',
                    'value' => $baixo,
                    'label' => $labelPeca ?? $p,
                ];
            }
            if (count($tokens) >= 500) {
                break;
            }
        }

        return $tokens;
    }

    private function normalizarDataCampo($raw): string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }
        return '';
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizarBimestresLista($raw): array
    {
        $out = [];
        foreach ((array) $raw as $b) {
            $n = (int) $b;
            if ($n >= 1 && $n <= 4 && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizarIdsLista($raw): array
    {
        $out = [];
        foreach ((array) $raw as $id) {
            $n = (int) $id;
            if ($n > 0 && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $pecas
     * @return array<string,array{calc_type:string,materia_unica:int,usar_percentual:int,tipo_avaliacao_id:int,papel:string,bimestres:list<int>}>
     */
    public function pecasOpcoesPadrao(array $pecas, int $bimestreIdentidade = 0): array
    {
        $bims = ($bimestreIdentidade >= 1 && $bimestreIdentidade <= 4) ? [$bimestreIdentidade] : [];
        $out = [];
        foreach ($pecas as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '') {
                continue;
            }
            $out[$p] = [
                'calc_type' => $this->calcTypePadrao($p),
                'materia_unica' => 0,
                'usar_percentual' => $this->usarPercentualPadrao($p),
                'tipo_avaliacao_id' => 0,
                'papel' => $this->papelPadrao($p),
                'bimestres' => $bims,
                'blocos_ids' => [],
                'blocos_ids_manual' => 0,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,array> $base
     * @param array<string,mixed> $override
     * @return array<string,array{calc_type:string,materia_unica:int,usar_percentual:int,tipo_avaliacao_id:int}>
     */
    private function mesclarPecasOpcoes(array $base, array $override): array
    {
        foreach ($override as $peca => $opts) {
            $peca = strtolower(trim((string) $peca));
            if ($peca === '' || !is_array($opts)) {
                continue;
            }
            if (!isset($base[$peca])) {
                $base[$peca] = [
                    'calc_type' => $this->calcTypePadrao($peca),
                    'materia_unica' => 0,
                    'usar_percentual' => $this->usarPercentualPadrao($peca),
                    'tipo_avaliacao_id' => 0,
                    'papel' => $this->papelPadrao($peca),
                    'bimestres' => [],
                    'blocos_ids' => [],
                    'blocos_ids_manual' => 0,
                ];
            }
            $calc = strtolower(trim((string) ($opts['calc_type'] ?? $base[$peca]['calc_type'])));
            if (!in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
                $calc = $this->calcTypePadrao($peca);
            }
            $base[$peca]['calc_type'] = $calc;
            $base[$peca]['materia_unica'] = !empty($opts['materia_unica']) ? 1 : 0;
            if (!empty($opts['materia_unica_modo'])) {
                $base[$peca]['materia_unica_modo'] = $this->normalizarMateriaUnicaModo($opts['materia_unica_modo']);
            }
            if (array_key_exists('usar_percentual', $opts)) {
                $base[$peca]['usar_percentual'] = !empty($opts['usar_percentual']) ? 1 : 0;
            }
            if (!$this->pecaPermiteAcertosQuestoes($peca)) {
                $base[$peca]['usar_percentual'] = 0;
            }
            if (array_key_exists('tipo_avaliacao_id', $opts)) {
                $base[$peca]['tipo_avaliacao_id'] = max(0, (int) $opts['tipo_avaliacao_id']);
            }
            if (array_key_exists('bimestres', $opts)) {
                $base[$peca]['bimestres'] = $this->normalizarBimestresLista($opts['bimestres']);
            }
            if (array_key_exists('blocos_ids', $opts)) {
                $base[$peca]['blocos_ids'] = $this->normalizarIdsLista($opts['blocos_ids']);
            }
            if (array_key_exists('blocos_ids_manual', $opts)) {
                $base[$peca]['blocos_ids_manual'] = !empty($opts['blocos_ids_manual']) ? 1 : 0;
            }
            $base[$peca]['papel'] = $this->normalizarPapel($opts['papel'] ?? ($base[$peca]['papel'] ?? ''), $peca);
        }
        return $base;
    }

    /**
     * @param list<array> $comps
     * @param list<string> $pecas
     * @return array<string,array{calc_type:string,materia_unica:int,usar_percentual:int,tipo_avaliacao_id:int,papel:string}>
     */
    private function inferirPecasOpcoesDeComponentes(array $comps, array $pecas, string $formulaFinal = ''): array
    {
        $out = $this->pecasOpcoesPadrao($pecas);
        $formulaBlob = strtolower($formulaFinal);
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $formulaBlob .= ' ' . strtolower((string) (($c['config']['expressao'] ?? '') ?: ''));
        }
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') === 'calculado') {
                continue;
            }
            if ($this->componenteEhColunaSubevento($c) || $this->componenteEhRecuperacaoExibe($c)) {
                continue;
            }
            $key = $this->chavePecaDeComponente($c);
            if ($key === null || !isset($out[$key])) {
                continue;
            }
            $calc = strtolower(trim((string) ($c['calc_type'] ?? 'media')));
            if (in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
                $out[$key]['calc_type'] = $calc;
            }
            $out[$key]['materia_unica'] = !empty($c['materia_unica']) ? 1 : 0;
            $out[$key]['materia_unica_modo'] = $this->normalizarMateriaUnicaModo($c['materia_unica_modo'] ?? 'soma');
            $out[$key]['usar_percentual'] = !empty($c['usar_percentual']) ? 1 : 0;
            if (!$this->pecaPermiteAcertosQuestoes($key)) {
                $out[$key]['usar_percentual'] = 0;
            }
            $tipoId = (int) (($c['config']['tipo_avaliacao_id'] ?? null) ?: ($c['tipo_avaliacao_id'] ?? 0));
            if ($tipoId > 0) {
                $out[$key]['tipo_avaliacao_id'] = $tipoId;
            }
            $bimsComp = $this->normalizarBimestresLista($c['config']['prova_bimestres'] ?? []);
            if ($bimsComp !== []) {
                $out[$key]['bimestres'] = $bimsComp;
            }
            $blocosComp = $this->normalizarIdsLista($c['blocos_ids'] ?? []);
            if ($blocosComp !== [] || !empty($c['config']['blocos_ids_manual'])) {
                $out[$key]['blocos_ids'] = $blocosComp;
                $out[$key]['blocos_ids_manual'] = 1;
            }
            $out[$key]['papel'] = $this->inferirPapelDeComponente($c, $formulaBlob);
        }
        return $out;
    }

    /** @param list<array> $comps @return list<string> */
    private function inferirPecasDeComponentes(array $comps): array
    {
        $pecas = [];
        foreach ($comps as $c) {
            if (!is_array($c) || $this->componenteEhRecuperacaoExibe($c)) {
                continue;
            }
            $key = $this->chavePecaDeComponente($c);
            if ($key !== null) {
                $pecas[] = $key;
            }
        }
        return array_values(array_unique($pecas));
    }

    /** @param array<string,mixed> $c */
    private function chavePecaDeComponente(array $c): ?string
    {
        $src = (string) ($c['source_type'] ?? '');
        if ($src === 'calculado') {
            return null;
        }
        $cod = mb_strtolower(trim((string) ($c['codigo'] ?? '')));
        $nome = mb_strtolower(trim((string) ($c['nome'] ?? '')));
        $filtro = mb_strtolower(trim((string) ($c['filtro_titulo'] ?? '')));
        $blob = $cod . ' ' . $nome . ' ' . $filtro;
        $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
        $layout = is_array($cfg['layout'] ?? null) ? $cfg['layout'] : [];
        $layoutG = strtolower(trim((string) ($cfg['layout_group'] ?? $layout['group'] ?? $c['layout_group'] ?? '')));
        $chaveTipo = $this->chaveCanonicaQuadro($cfg['chave_quadro'] ?? ($c['chave_quadro'] ?? ''));
        if ($src === 'jornadas' || str_contains($blob, 'jornada')) {
            return 'jornada';
        }
        if ((bool) preg_match('/^s[1-8]$/', $cod)) {
            return 'semanal';
        }
        $tipoIdComp = (int) (($cfg['tipo_avaliacao_id'] ?? null) ?: ($c['tipo_avaliacao_id'] ?? 0));
        if ($tipoIdComp > 0) {
            $porTipo = $this->chavePecaPorTipoId($tipoIdComp);
            if ($porTipo !== '') {
                return $porTipo;
            }
        }
        if ($chaveTipo !== '') {
            return $chaveTipo;
        }
        if (str_contains($blob, 'enac')) {
            return 'enac';
        }
        if (
            str_contains($blob, 'semanal')
            || (bool) preg_match('/^s[1-8]$/', $cod)
            || in_array($layoutG, ['quadro_a', 'quadro_b'], true)
        ) {
            return 'semanal';
        }
        if (str_contains($blob, 'trabalho') || $cod === 'trab') {
            return 'trabalho';
        }
        if (str_contains($blob, 'bimestral') || $cod === 'prova_bim') {
            return 'bimestral';
        }
        if (str_contains($blob, 'participa') || $cod === 'part') {
            return 'participacao';
        }
        if (str_contains($blob, 'recupera') || $cod === 'rec') {
            return 'recuperacao';
        }
        $tipoId = (int) (($cfg['tipo_avaliacao_id'] ?? null) ?: ($c['tipo_avaliacao_id'] ?? 0));
        if ($tipoId > 0) {
            return $this->chavePecaPorTipoId($tipoId);
        }
        return null;
    }

    private function chavePecaPorTipoId(int $tipoId): string
    {
        if ($tipoId <= 0) {
            return '';
        }
        foreach ($this->pecasMeta() as $meta) {
            if ((int) ($meta['tipo_avaliacao_id'] ?? 0) === $tipoId) {
                return (string) ($meta['key'] ?? ('tipo_' . $tipoId));
            }
        }
        return 'tipo_' . $tipoId;
    }

    private function inferirPapelDeComponente(array $c, string $formula): string
    {
        $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
        $salvo = strtolower(trim((string) ($cfg['papel_wizard'] ?? '')));
        if (in_array($salvo, self::PAPEIS, true)) {
            return $salvo;
        }
        $key = $this->chavePecaDeComponente($c) ?? '';
        $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
        $f = strtolower($formula);
        $layoutType = strtolower(trim((string) ($cfg['layout_type'] ?? '')));
        if ($layoutType === 'semana_nq' || (bool) preg_match('/^s[1-8]$/', $cod)) {
            return 'media';
        }
        if ($cod !== '' && $f !== '') {
            $q = preg_quote($cod, '/');
            if (preg_match('/max\s*\([^,]*,\s*\([^)]*' . $q . '/', $f)) {
                return 'so_melhora';
            }
            if (preg_match('/max\s*\([^,]*,\s*' . $q . '\s*\)/', $f)) {
                return 'substitui';
            }
            if (preg_match('/\(\s*(media_parcial|media_base|media_bim)\s*\+\s*[^)]*' . $q . '/', $f)
                || preg_match('/\(\s*' . $q . '\s*\+\s*(media_parcial|media_base|media_bim)/', $f)) {
                return 'depois';
            }
            if (!preg_match('/\b' . $q . '\b/', $f)) {
                return 'exibe';
            }
            return 'media';
        }
        return $this->papelPadrao($key);
    }

    /** @return array<string,mixed>|null */
    private function encontrarModelo(string $key): ?array
    {
        foreach ($this->modelosProntos() as $m) {
            if ($m['key'] === $key) {
                return $m;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $regra
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function rascunhoAPartirDeRegra(array $regra, array $estado): array
    {
        $bim = (int) ($estado['bimestre'] ?? 0);
        $ano = (int) ($estado['ano_letivo'] ?? date('Y'));
        $nomeBase = trim((string) ($regra['nome'] ?? 'Evento'));
        $nome = trim((string) ($estado['nome'] ?? ''));
        if ($nome === '' || $nome === $nomeBase) {
            $nome = $bim > 0
                ? preg_replace('/\d+\s*º?\s*bimestre/iu', $bim . 'º bimestre', $nomeBase) ?? ($nomeBase . ' (cópia)')
                : ($nomeBase . ' (cópia)');
            if ($nome === $nomeBase) {
                $nome = $nomeBase . ' — ' . $bim . 'º bim';
            }
        }

        return [
            'modo' => 'criar',
            'regra_id' => null,
            'nome' => $nome,
            'codigo' => '',
            'descricao_curta' => (string) ($regra['descricao_curta'] ?? ''),
            'formula_final' => (string) ($regra['formula_final'] ?? ''),
            'exibir_em' => (string) ($estado['exibir_em'] ?? $regra['exibir_em'] ?? 'notas'),
            'finalidade' => $this->normalizarFinalidade($estado['finalidade'] ?? $regra['finalidade'] ?? 'oficial'),
            'boletim_id' => (int) ($estado['boletim_id'] ?? $regra['boletim_id'] ?? 0),
            'grupo_regras_notas_id' => (int) ($estado['grupo_regras_notas_id'] ?? $regra['grupo_regras_notas_id'] ?? 0),
            'ano_letivo' => $ano,
            'bimestre' => $bim > 0 ? $bim : ($regra['bimestre'] ?? null),
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'turmas_ids' => $estado['turmas_ids'] !== [] ? $estado['turmas_ids'] : ($regra['turmas_ids'] ?? []),
            'materias_ids' => $estado['materias_ids'] !== [] ? $estado['materias_ids'] : ($regra['materias_ids'] ?? []),
            'series_ids' => $estado['series_ids'] !== [] ? $estado['series_ids'] : ($regra['series_ids'] ?? []),
            'round_mode' => (string) ($estado['round_mode'] ?? $regra['round_mode'] ?? 'none'),
            'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? $regra['decimal_places'] ?? 2),
            'nota_minima_aprovacao' => $this->minimaDaClonagem($estado, $regra['nota_minima_aprovacao'] ?? null),
            'nota_minima_origem' => $this->origemDaClonagem($estado, $regra['nota_minima_aprovacao'] ?? null),
            'nota_minima_regra_nome' => $this->origemDaClonagem($estado, $regra['nota_minima_aprovacao'] ?? null) === 'regra'
                ? (string) ($estado['nota_minima_regra_nome'] ?? '')
                : '',
            'componentes' => $this->ajustarComponentesClonados(
                is_array($regra['componentes'] ?? null) ? $regra['componentes'] : [],
                $estado
            ),
        ];
    }

    /**
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function rascunhoAPartirDePecas(array $estado): array
    {
        $pecas = $estado['pecas'];
        $componentes = [];
        $mapaCodigo = [];

        foreach ($pecas as $peca) {
            $opts = is_array($estado['pecas_opcoes'][$peca] ?? null) ? $estado['pecas_opcoes'][$peca] : [];
            $def = $this->definirComponentePeca($peca, $opts, $estado);
            if ($def === null) {
                continue;
            }
            $papel = $this->normalizarPapel($opts['papel'] ?? '', $peca);
            $def['config'] = is_array($def['config'] ?? null) ? $def['config'] : [];
            $def['config']['papel_wizard'] = $papel;
            $componentes[] = $def;
            $mapaCodigo[$peca] = ['codigo' => $def['codigo'], 'papel' => $papel, 'nome' => (string) $def['nome']];
        }

        $formulaFinal = $this->montarFormulaPorPapeis($componentes, $mapaCodigo, $pecas);
        $rascunhoTmp = [
            'componentes' => $componentes,
            'formula_final' => $formulaFinal,
        ];
        $rascunhoTmp = $this->aplicarFormulasBlocos($rascunhoTmp, $estado, $mapaCodigo);
        $componentes = is_array($rascunhoTmp['componentes'] ?? null) ? $rascunhoTmp['componentes'] : $componentes;
        $formulaFinal = trim((string) ($rascunhoTmp['formula_final'] ?? $formulaFinal));

        $bim = (int) ($estado['bimestre'] ?? 0);
        $ano = (int) ($estado['ano_letivo'] ?? date('Y'));
        $nome = trim((string) ($estado['nome'] ?? ''));
        if ($nome === '') {
            $nome = $bim > 0
                ? sprintf('Notas Bimestrais — %dº bimestre %d', $bim, $ano)
                : sprintf('Notas — %d', $ano);
        }

        return [
            'modo' => ($estado['modo'] ?? 'criar') === 'editar' && !empty($estado['regra_id']) ? 'editar' : 'criar',
            'regra_id' => !empty($estado['regra_id']) ? (int) $estado['regra_id'] : null,
            'nome' => $nome,
            'codigo' => trim((string) ($estado['codigo'] ?? '')),
            'descricao_curta' => '',
            'formula_final' => $formulaFinal,
            'exibir_em' => (string) ($estado['exibir_em'] ?? 'notas'),
            'finalidade' => $this->normalizarFinalidade($estado['finalidade'] ?? 'oficial'),
            'boletim_id' => (int) ($estado['boletim_id'] ?? 0),
            'grupo_regras_notas_id' => (int) ($estado['grupo_regras_notas_id'] ?? 0),
            'ano_letivo' => $ano,
            'bimestre' => $bim > 0 ? $bim : null,
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'turmas_ids' => $estado['turmas_ids'],
            'materias_ids' => $estado['materias_ids'],
            'series_ids' => $estado['series_ids'],
            'round_mode' => (string) ($estado['round_mode'] ?? 'none'),
            'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? 2),
            'nota_minima_aprovacao' => (float) ($estado['nota_minima_aprovacao'] ?? 6),
            'nota_minima_origem' => (string) ($estado['nota_minima_origem'] ?? 'padrao'),
            'nota_minima_regra_nome' => (string) ($estado['nota_minima_regra_nome'] ?? ''),
            'componentes' => $componentes,
        ];
    }

    /**
     * Boletim oficial “composto”: puxa média final dos eventos de Notas.
     * Visão de impressão livre (origem quadro-impressao / quadro_livre) usa o builder completo.
     */
    private function ehBoletimComposto(array $estado): bool
    {
        $origem = (string) ($estado['origem'] ?? '');
        if ($origem === 'quadro-impressao' || $origem === 'clonar' || !empty($estado['quadro_livre'])) {
            return false;
        }
        return (string) ($estado['exibir_em'] ?? '') === 'boletim';
    }

    /**
     * Print Santa Maria (PP/PU/CH/PE/MU/FAL + semestres + final).
     */
    private function ehModeloSantaMaria(array $estado): bool
    {
        if (!empty($estado['modelo_santa_maria'])) {
            return true;
        }
        return (string) ($estado['origem'] ?? '') === 'santa-maria';
    }

    /**
     * Erros de exigência de fórmula/média não se aplicam a Demonstrativo de Notas.
     */
    private function deveOcultarErroFormulaDemonstrativo(string $erro): bool
    {
        static $ocultar = [
            'Escolha ao menos uma peça da média (ex.: \Bimestral, Jornada, ENAC).',
            'Marque ao menos uma peça para entrar na média, ou clique numa coluna amarela em Exibir e monte o cálculo.',
            'Informe ao menos um componente (ex.: prova semanal, bimestral).',
        ];
        if (in_array($erro, $ocultar, true)) {
            return true;
        }
        $e = function_exists('mb_strtolower') ? mb_strtolower($erro) : strtolower($erro);
        if (str_contains($e, 'expressao') || str_contains($erro, 'expressão')) {
            return true;
        }
        if (str_contains($e, 'bloco calculado') || str_contains($e, 'calculado(s) sem')) {
            return true;
        }
        if (str_contains($erro, 'cálculo') || str_contains($erro, 'coluna amarela')) {
            return true;
        }

        return false;
    }

    /**
     * Demonstrativo de Notas: remove fórmulas, blocos calculados e referências no layout.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function sanitizarDemonstrativoSemFormula(array $rascunho, array &$estado): array
    {
        if (!$this->ehDemonstrativoNotas($estado)) {
            return $rascunho;
        }

        /** @var array<string,true> $codigosCalc */
        $codigosCalc = [];
        foreach (array_keys((array) ($estado['formulas_blocos'] ?? [])) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $codigosCalc[$cod] = true;
            }
        }
        foreach ((array) ($estado['blocos_calc'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $codigosCalc[$cod] = true;
            }
        }
        $bc = strtolower(trim((string) ($estado['bloco_calc'] ?? '')));
        if ($bc !== '') {
            $codigosCalc[$bc] = true;
        }

        $estado['formulas_blocos'] = [];
        $estado['formulas_materias_blocos'] = [];
        $estado['blocos_calc'] = [];
        $estado['bloco_calc'] = '';

        $comps = [];
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ((string) ($c['source_type'] ?? '') === 'calculado') {
                $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
                if ($cod !== '') {
                    $codigosCalc[$cod] = true;
                }
                continue;
            }
            $comps[] = $c;
        }
        $rascunho['componentes'] = $comps;
        $rascunho['formula_final'] = '';

        $this->removerCodigosCalcDoLayout($estado, $codigosCalc);
        $this->removerCodigosCalcDoLayout($rascunho, $codigosCalc);

        return $rascunho;
    }

    /**
     * @param array<string,mixed> $alvo
     * @param array<string,true> $codigosCalc
     */
    private function removerCodigosCalcDoLayout(array &$alvo, array $codigosCalc): void
    {
        if ($codigosCalc === []) {
            return;
        }
        if (isset($alvo['colunas_ordem']) && is_array($alvo['colunas_ordem'])) {
            $alvo['colunas_ordem'] = array_values(array_filter(
                $alvo['colunas_ordem'],
                static function ($cod) use ($codigosCalc): bool {
                    $cod = strtolower(trim((string) $cod));

                    return $cod !== '' && !isset($codigosCalc[$cod]);
                }
            ));
        }
        if (isset($alvo['rotulos_colunas']) && is_array($alvo['rotulos_colunas'])) {
            foreach (array_keys($codigosCalc) as $cod) {
                unset($alvo['rotulos_colunas'][$cod]);
            }
        }
        if (!isset($alvo['linhas_layout']) || !is_array($alvo['linhas_layout'])) {
            return;
        }
        $linhas = [];
        foreach ($this->normalizarLinhasLayout($alvo['linhas_layout']) as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $cols = [];
            foreach ((array) ($lin['colunas'] ?? []) as $c) {
                $c = strtolower(trim((string) $c));
                if ($c !== '' && !isset($codigosCalc[$c])) {
                    $cols[] = $c;
                }
            }
            if ($cols !== []) {
                $lin['colunas'] = $cols;
                $linhas[] = $lin;
            }
        }
        $alvo['linhas_layout'] = $linhas;
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<int>
     */
    private function idsDemonstrativos(array $estado): array
    {
        $out = [];
        foreach ((array) ($estado['demonstrativos_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<int>
     */
    private function idsEventosAvaliativos(array $estado): array
    {
        $out = [];
        foreach ((array) ($estado['eventos_avaliativos_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Após apagar/reimportar seed, os IDs salvos no quadro ficam órfãos.
     * Recasa por sigla+bimestre (ou tipo+bimestre) e atualiza o estado/componentes.
     *
     * @param array<string,mixed> $estado
     * @param list<array<string,mixed>> $catalogo
     * @param array<int,array<string,mixed>> $porId
     * @return array<int,int> mapa id_antigo => id_novo
     */
    private function repararEventosAvaliativosOrfaos(array &$estado, array $catalogo, array $porId): array
    {
        $ids = $this->idsEventosAvaliativos($estado);
        if ($ids === [] || $catalogo === []) {
            return [];
        }

        $comps = [];
        foreach ([
            is_array($estado['rascunho_preservado']['componentes'] ?? null) ? $estado['rascunho_preservado']['componentes'] : [],
            is_array($estado['componentes'] ?? null) ? $estado['componentes'] : [],
        ] as $lista) {
            foreach ($lista as $c) {
                if (is_array($c)) {
                    $comps[] = $c;
                }
            }
        }

        $ano = (int) ($estado['ano_letivo'] ?? 0);
        $mapa = [];
        $novosIds = [];
        $orfaoes = $ids;
        foreach ($comps as $c) {
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $evComp = (int) ($cfg['evento_avaliativo_id'] ?? $c['evento_avaliativo_id'] ?? 0);
            if ($evComp > 0 && !isset($porId[$evComp])) {
                $orfaoes[] = $evComp;
            }
        }
        $orfaoes = array_values(array_unique(array_filter(array_map('intval', $orfaoes))));

        foreach ($orfaoes as $idAntigo) {
            if (isset($porId[$idAntigo])) {
                $novosIds[] = $idAntigo;
                continue;
            }
            $dicas = $this->dicasEventoOrfaoDosComponentes($comps, $idAntigo);
            $sub = $this->encontrarEventoAvaliativoNoCatalogo($catalogo, $dicas, $ano);
            if ($sub === null) {
                continue;
            }
            $idNovo = (int) ($sub['id'] ?? 0);
            if ($idNovo <= 0) {
                continue;
            }
            $mapa[$idAntigo] = $idNovo;
            $novosIds[] = $idNovo;
        }

        // Mantém IDs que ainda existem e não eram órfãos.
        foreach ($ids as $idOk) {
            if (isset($porId[$idOk])) {
                $novosIds[] = $idOk;
            }
        }

        if ($mapa !== []) {
            $this->aplicarMapaEventosNosComponentesDoEstado($estado, $mapa);
        }
        $estado['eventos_avaliativos_ids'] = array_values(array_unique(array_filter(
            array_map('intval', $novosIds),
            static fn (int $id): bool => $id > 0
        )));

        return $mapa;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return array{tipo_avaliacao_id:int,bimestre:int,sigla:string,nome:string}
     */
    private function dicasEventoOrfaoDosComponentes(array $comps, int $eventoId): array
    {
        $dicas = [
            'tipo_avaliacao_id' => 0,
            'bimestre' => 0,
            'sigla' => '',
            'nome' => '',
        ];
        foreach ($comps as $c) {
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($cfg === [] && is_string($c['config_json'] ?? null) && trim((string) $c['config_json']) !== '') {
                $tmp = json_decode((string) $c['config_json'], true);
                $cfg = is_array($tmp) ? $tmp : [];
            }
            $evCfg = (int) ($cfg['evento_avaliativo_id'] ?? $c['evento_avaliativo_id'] ?? 0);
            if ($eventoId > 0 && $evCfg !== $eventoId) {
                continue;
            }
            $tipo = (int) ($cfg['tipo_avaliacao_id'] ?? $c['tipo_avaliacao_id'] ?? 0);
            if ($tipo > 0 && $dicas['tipo_avaliacao_id'] <= 0) {
                $dicas['tipo_avaliacao_id'] = $tipo;
            }
            $bims = [];
            foreach ((array) ($cfg['prova_bimestres'] ?? []) as $b) {
                $n = (int) $b;
                if ($n >= 1 && $n <= 4) {
                    $bims[] = $n;
                }
            }
            $grupo = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            if ($bims === [] && preg_match('/^b([1-4])$/', $grupo, $mGrupo) === 1) {
                $bims[] = (int) $mGrupo[1];
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($bims === [] && preg_match('/^b([1-4])_/', $cod, $mCod) === 1) {
                $bims[] = (int) $mCod[1];
            }
            if ($bims !== [] && $dicas['bimestre'] <= 0) {
                $dicas['bimestre'] = (int) $bims[0];
            }
            $sigla = '';
            if (preg_match('/^b[1-4]_([a-z0-9]+)$/', $cod, $mSig) === 1) {
                $sigla = strtoupper($mSig[1]);
            } elseif (preg_match('/^[a-z]{1,6}$/', $cod) === 1) {
                $sigla = strtoupper($cod);
            }
            $nome = trim((string) ($c['nome'] ?? ''));
            if ($sigla === '' && preg_match('/\b(PP|PU|CH|PE|REC|PF)\b/i', $nome, $mNome) === 1) {
                $sigla = strtoupper($mNome[1]);
            }
            if ($sigla !== '' && $dicas['sigla'] === '') {
                $dicas['sigla'] = $sigla;
            }
            if ($nome !== '' && $dicas['nome'] === '') {
                $dicas['nome'] = $nome;
            }
            if ($dicas['tipo_avaliacao_id'] > 0 || $dicas['sigla'] !== '') {
                break;
            }
        }

        return $dicas;
    }

    /**
     * @param list<array<string,mixed>> $catalogo
     * @param array{tipo_avaliacao_id:int,bimestre:int,sigla:string,nome:string} $dicas
     * @return array<string,mixed>|null
     */
    private function encontrarEventoAvaliativoNoCatalogo(array $catalogo, array $dicas, int $anoLetivo = 0): ?array
    {
        $tipo = (int) ($dicas['tipo_avaliacao_id'] ?? 0);
        $bim = (int) ($dicas['bimestre'] ?? 0);
        $sigla = strtoupper(trim((string) ($dicas['sigla'] ?? '')));
        $nomeKey = strtolower(trim((string) ($dicas['nome'] ?? '')));
        if ($tipo <= 0 && $sigla === '' && $nomeKey === '') {
            return null;
        }

        $candidatos = [];
        foreach ($catalogo as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $id = (int) ($ev['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($anoLetivo > 0 && (int) ($ev['ano_letivo'] ?? 0) > 0 && (int) $ev['ano_letivo'] !== $anoLetivo) {
                continue;
            }
            $bimEv = (int) ($ev['bimestre'] ?? 0);
            if ($bim > 0 && $bimEv > 0 && $bimEv !== $bim) {
                continue;
            }
            $score = 0;
            if ($tipo > 0 && (int) ($ev['tipo_avaliacao_id'] ?? 0) === $tipo) {
                $score += 4;
            }
            $siglaEv = strtoupper(trim((string) ($ev['sigla'] ?? '')));
            if ($sigla !== '' && $siglaEv === $sigla) {
                $score += 3;
            }
            $nomeEv = strtolower(trim((string) ($ev['nome'] ?? '')));
            if ($nomeKey !== '' && $nomeEv !== '' && ($nomeEv === $nomeKey || str_contains($nomeEv, $nomeKey) || str_contains($nomeKey, $nomeEv))) {
                $score += 1;
            }
            if ($bim > 0 && $bimEv === $bim) {
                $score += 1;
            }
            if ($score < 3) {
                continue;
            }
            $candidatos[] = ['score' => $score, 'ev' => $ev];
        }
        if ($candidatos === []) {
            return null;
        }
        usort($candidatos, static function (array $a, array $b): int {
            return ($b['score'] <=> $a['score']) ?: ((int) ($a['ev']['id'] ?? 0) <=> (int) ($b['ev']['id'] ?? 0));
        });

        return $candidatos[0]['ev'];
    }

    /**
     * @param array<string,mixed> $estado
     * @param array<int,int> $mapa
     */
    private function aplicarMapaEventosNosComponentesDoEstado(array &$estado, array $mapa): void
    {
        if ($mapa === []) {
            return;
        }
        foreach (['rascunho_preservado', null] as $chave) {
            if ($chave === null) {
                if (!isset($estado['componentes']) || !is_array($estado['componentes'])) {
                    continue;
                }
                $lista =& $estado['componentes'];
            } else {
                if (!isset($estado[$chave]['componentes']) || !is_array($estado[$chave]['componentes'])) {
                    continue;
                }
                $lista =& $estado[$chave]['componentes'];
            }
            foreach ($lista as $i => $c) {
                if (!is_array($c)) {
                    continue;
                }
                $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
                $evId = (int) ($cfg['evento_avaliativo_id'] ?? $c['evento_avaliativo_id'] ?? 0);
                if ($evId <= 0 || !isset($mapa[$evId])) {
                    continue;
                }
                $cfg['evento_avaliativo_id'] = $mapa[$evId];
                $lista[$i]['config'] = $cfg;
                $lista[$i]['evento_avaliativo_id'] = $mapa[$evId];
            }
            unset($lista);
        }

        $subs = $this->normalizarEventosAvaliativosSubs($estado['eventos_avaliativos_subs'] ?? []);
        if ($subs !== []) {
            $novosSubs = [];
            foreach ($subs as $evId => $idsSub) {
                $evId = (int) $evId;
                $destino = $mapa[$evId] ?? $evId;
                $novosSubs[$destino] = array_values(array_unique(array_merge(
                    $novosSubs[$destino] ?? [],
                    $idsSub
                )));
            }
            $estado['eventos_avaliativos_subs'] = $novosSubs;
        }
    }

    /**
     * Se o admin já colocou chips no quadro mas a lista de IDs se perdeu
     * (remount / Aplicar), reconstrói pelos códigos/siglas do board.
     *
     * @param array<string,mixed> $estado
     */
    private function preencherEventosAvaliativosAPartirDoQuadro(array &$estado): void
    {
        try {
            $catalogo = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            $catalogo = [];
        }
        $porId = [];
        foreach ($catalogo as $evCat) {
            if (!is_array($evCat)) {
                continue;
            }
            $idCat = (int) ($evCat['id'] ?? 0);
            if ($idCat > 0) {
                $porId[$idCat] = $evCat;
            }
        }
        $this->repararEventosAvaliativosOrfaos($estado, $catalogo, $porId);

        $ids = $this->idsEventosAvaliativos($estado);

        // 1) IDs gravados no config do componente (REC/PF/PP já mesclados).
        // Mescla mesmo com lista parcial — senão REC some quando só PP/PU estavam salvos.
        $rascunhoComps = is_array($estado['rascunho_preservado']['componentes'] ?? null)
            ? $estado['rascunho_preservado']['componentes']
            : [];
        $setCodPreenchidos = [];
        foreach ($rascunhoComps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($cfg === [] && is_string($c['config_json'] ?? null) && trim((string) $c['config_json']) !== '') {
                $tmp = json_decode((string) $c['config_json'], true);
                $cfg = is_array($tmp) ? $tmp : [];
            }
            $evId = (int) ($cfg['evento_avaliativo_id'] ?? 0);
            if ($evId > 0) {
                $ids[] = $evId;
            }
            $src = strtolower(trim((string) ($c['source_type'] ?? '')));
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            // Só códigos com fonte real (não placeholder nenhuma do print SM).
            if ($cod !== '' && $src !== '' && $src !== 'nenhuma') {
                $setCodPreenchidos[$cod] = true;
                if (preg_match('/^b[1-4]_(.+)$/', $cod, $m)) {
                    $setCodPreenchidos[$m[1]] = true;
                }
                if (preg_match('/^s[12]_(.+)$/', $cod, $m)) {
                    $setCodPreenchidos[$m[1]] = true;
                }
            }
        }

        // 2) Catálogo × códigos já preenchidos (não slots vazios s1_rec/pf).
        if ($setCodPreenchidos !== []) {
            try {
                $catalogo = $this->ferramentas->listarEventosAvaliativosParaBoletim();
            } catch (\Throwable $e) {
                $catalogo = [];
            }
            $ano = (int) ($estado['ano_letivo'] ?? 0);
            $vistos = [];
            foreach ($catalogo as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $id = (int) ($ev['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if ($ano > 0 && (int) ($ev['ano_letivo'] ?? 0) > 0 && (int) $ev['ano_letivo'] !== $ano) {
                    continue;
                }
                $codPref = strtolower($this->codigoColunaEventoAvaliativo($ev, $vistos));
                $sigla = strtolower(preg_replace('/[^a-z0-9_]+/i', '', (string) ($ev['sigla'] ?? '')) ?? '');
                $bim = (int) ($ev['bimestre'] ?? 0);
                $slotRec = '';
                if ($bim === 81) {
                    $slotRec = 's1_rec';
                } elseif ($bim === 82) {
                    $slotRec = 's2_rec';
                } elseif ($bim === 90 || $sigla === 'pf') {
                    $slotRec = 'pf';
                }
                $bate = ($codPref !== '' && isset($setCodPreenchidos[$codPref]))
                    || ($sigla !== '' && isset($setCodPreenchidos[$sigla]))
                    || ($slotRec !== '' && isset($setCodPreenchidos[$slotRec]));
                if (!$bate) {
                    continue;
                }
                $ids[] = $id;
                if ($codPref !== '') {
                    $vistos[$codPref] = true;
                }
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }
        $estado['eventos_avaliativos_ids'] = $ids;
    }

    /**
     * @param mixed $raw
     * @return array<int, list<int>>
     */
    private function normalizarEventosAvaliativosSubs($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $eventoId => $subs) {
            $eventoId = (int) $eventoId;
            if ($eventoId <= 0 || !is_array($subs)) {
                continue;
            }
            $ids = [];
            foreach ($subs as $sid) {
                $sid = (int) $sid;
                if ($sid > 0 && !in_array($sid, $ids, true)) {
                    $ids[] = $sid;
                }
            }
            if ($ids !== []) {
                $out[$eventoId] = $ids;
            }
        }

        return $out;
    }

    /**
     * Subeventos marcados no assistente; se nada foi marcado, usa todos do evento.
     *
     * @param array<string,mixed> $estado
     * @param list<array<string,mixed>> $subs
     * @return list<array<string,mixed>>
     */
    private function subeventosMarcadosDoEvento(array $estado, int $eventoId, array $subs): array
    {
        if ($eventoId <= 0 || $subs === []) {
            return $subs;
        }
        $mapa = $this->normalizarEventosAvaliativosSubs($estado['eventos_avaliativos_subs'] ?? []);
        $raw = $mapa[$eventoId] ?? [];
        if ($raw === []) {
            return $subs;
        }
        $queridos = [];
        foreach ($raw as $sid) {
            $queridos[(int) $sid] = true;
        }
        $out = [];
        foreach ($subs as $sub) {
            if (!is_array($sub)) {
                continue;
            }
            $sid = (int) ($sub['id'] ?? 0);
            if ($sid > 0 && isset($queridos[$sid])) {
                $out[] = $sub;
            }
        }

        return $out !== [] ? $out : $subs;
    }

    /**
     * @param array<string,mixed> $estado
     * @param array<string,bool> $vistos
     * @param list<string> $erros
     * @return list<array<string,mixed>>
     */
    private function componentesDeEventosAvaliativosMarcados(array $estado, array &$vistos, array &$erros): array
    {
        $placeholdersPorCodigo = $this->codigosPlaceholderSemEvento($estado);
        $escalaMaxPadrao = $this->normalizarEscalaMaxima($estado['escala_max_padrao'] ?? null, 10.0);
        $ids = $this->idsEventosAvaliativos($estado);
        if ($ids === []) {
            return [];
        }
        try {
            $catalogo = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            $erros[] = 'Não consegui listar os Eventos avaliativos.';
            return [];
        }
        $porId = [];
        foreach ($catalogo as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $id = (int) ($ev['id'] ?? 0);
            if ($id > 0) {
                $porId[$id] = $ev;
            }
        }
        $this->repararEventosAvaliativosOrfaos($estado, $catalogo, $porId);
        $ids = $this->idsEventosAvaliativos($estado);
        $out = [];
        foreach ($ids as $id) {
            if (!isset($porId[$id])) {
                // Seed recriado: tenta casar de novo antes de reclamar.
                $dicas = $this->dicasEventoOrfaoDosComponentes(
                    is_array($estado['rascunho_preservado']['componentes'] ?? null)
                        ? $estado['rascunho_preservado']['componentes']
                        : [],
                    $id
                );
                $sub = $this->encontrarEventoAvaliativoNoCatalogo($catalogo, $dicas, (int) ($estado['ano_letivo'] ?? 0));
                if ($sub !== null && (int) ($sub['id'] ?? 0) > 0) {
                    $id = (int) $sub['id'];
                    $porId[$id] = $sub;
                } else {
                    continue;
                }
            }
            $ev = $porId[$id];
            $bim = (int) ($ev['bimestre'] ?? 0);
            $ehRec = $bim >= 60;
            $nomeEvento = trim((string) ($ev['nome'] ?? ''));
            $sigla = strtoupper(trim((string) ($ev['sigla'] ?? '')));
            $tipo = (int) ($ev['tipo_avaliacao_id'] ?? 0);
            $subs = [];
            foreach ((array) ($ev['subeventos'] ?? []) as $sub) {
                if (is_array($sub) && (int) ($sub['id'] ?? 0) > 0) {
                    $subs[] = $sub;
                }
            }

            $codigoBase = $this->codigoColunaEventoAvaliativo($ev, $vistos);
            if ($codigoBase === '') {
                continue;
            }

            // REC/PF do print Santa Maria: encaixa no slot mesmo se o placeholder
            // (source_type=nenhuma) já estiver em $vistos — a mesclagem substitui.
            if ($ehRec && $bim === 81) {
                $codigoBase = 's1_rec';
            } elseif ($ehRec && $bim === 82) {
                $codigoBase = 's2_rec';
            } elseif ($ehRec && ($bim === 90 || $sigla === 'pf')) {
                $codigoBase = 'pf';
            }

            // Título da coluna = SIGLA do evento (pedido do print).
            $tituloBase = $sigla !== '' ? $sigla : ($nomeEvento !== '' ? $nomeEvento : ('Evento #' . $id));
            if ($ehRec && $sigla === '') {
                $tituloBase = 'REC';
            }
            $tituloBase = $this->siglaTituloColuna($codigoBase, $tituloBase);
            // s1_rec/s2_rec iguais a "REC" — marca o semestre no rótulo.
            if ($codigoBase === 's1_rec' && in_array(strtoupper(trim($tituloBase)), ['REC', ''], true)) {
                $tituloBase = 'REC 1º';
            } elseif ($codigoBase === 's2_rec' && in_array(strtoupper(trim($tituloBase)), ['REC', ''], true)) {
                $tituloBase = 'REC 2º';
            }

            // Montar quadro: 1 coluna por evento (não explode subeventos em 10× REC).
            $umPorEvento = !empty($estado['quadro_livre'])
                || ($estado['origem'] ?? '') === 'quadro-impressao'
                || !empty($estado['modelo_santa_maria'])
                || ($estado['origem'] ?? '') === 'santa-maria'
                || count($subs) <= 1;

            if ($umPorEvento) {
                $subsMarcados = $this->subeventosMarcadosDoEvento($estado, $id, $subs);
                $blocoId = (int) (($subsMarcados[0]['id'] ?? $subs[0]['id'] ?? 0));
                $blocosIds = [];
                foreach ($subsMarcados as $subM) {
                    $sid = (int) ($subM['id'] ?? 0);
                    if ($sid > 0) {
                        $blocosIds[] = $sid;
                    }
                }
                if ($blocosIds === [] && $blocoId > 0) {
                    $blocosIds = [$blocoId];
                }
                // Slots bN_* e s1_rec/s2_rec/pf já existem vazios no print SM —
                // ainda assim emite para a mesclagem substituir o placeholder `nenhuma`.
                $ehSlotBimestre = (bool) preg_match('/^b[1-4]_(pp|pu|ch|pe|fal)$/', $codigoBase);
                $ehSlotRecPf = in_array($codigoBase, ['s1_rec', 's2_rec', 'pf', 'rec'], true);
                // Slot já emitido nesta passada (2º evento no mesmo s1_rec/pf): usa eva{id}.
                if (isset($vistos[$codigoBase]) && ($ehSlotBimestre || $ehSlotRecPf)
                    && !empty($vistos['__emitido_' . $codigoBase])) {
                    $codigoBase = 'eva' . $id;
                    $ehSlotBimestre = false;
                    $ehSlotRecPf = false;
                }
                if (
                    isset($vistos[$codigoBase])
                    && !$ehSlotBimestre
                    && !$ehSlotRecPf
                    && empty($placeholdersPorCodigo[$codigoBase])
                ) {
                    continue;
                }
                $vistos[$codigoBase] = true;
                if ($ehSlotBimestre || $ehSlotRecPf) {
                    $vistos['__emitido_' . $codigoBase] = true;
                }
                $cfg = [
                    'tipo_avaliacao_id' => $tipo > 0
                        ? $tipo
                        : (int) (($subsMarcados[0]['tipo_avaliacao_id'] ?? $subs[0]['tipo_avaliacao_id'] ?? 0)),
                    'evento_avaliativo_id' => $id,
                    'escala_max' => $escalaMaxPadrao,
                    'papel_wizard' => 'exibe',
                    'layout_type' => $ehRec ? 'rec' : 'media',
                ];
                if ($ehRec) {
                    $cfg['recuperacao_exibe'] = 1;
                    if ($codigoBase === 's1_rec') {
                        $cfg['layout_group'] = 's1';
                    } elseif ($codigoBase === 's2_rec') {
                        $cfg['layout_group'] = 's2';
                    } elseif ($codigoBase === 'pf') {
                        $cfg['layout_group'] = 'final';
                    }
                }
                if (preg_match('/^(b[1-4])_(pp|pu|ch|pe|fal)$/', $codigoBase, $mSlot)) {
                    $cfg['layout_group'] = $mSlot[1];
                    $cfg['layout_type'] = $mSlot[2] === 'fal' ? 'faltas' : 'media';
                }
                $comp = $this->componenteBoletim($codigoBase, $tituloBase, 'provas_sistema', $cfg);
                if ($blocosIds !== []) {
                    $comp['blocos_ids'] = $blocosIds;
                    $comp['bloco_id'] = $blocosIds[0];
                }
                $out[] = $comp;
                continue;
            }

            foreach ($subs as $idx => $sub) {
                $blocoId = (int) ($sub['id'] ?? 0);
                if ($blocoId <= 0) {
                    continue;
                }
                $cod = 'sev' . $blocoId;
                if (isset($vistos[$cod])) {
                    continue;
                }
                $vistos[$cod] = true;
                $titulo = $sigla !== ''
                    ? ($sigla . ((int) $idx + 1))
                    : (trim((string) ($sub['titulo'] ?? '')) !== ''
                        ? trim((string) $sub['titulo'])
                        : ($tituloBase . ((int) $idx + 1)));
                $titulo = $this->siglaTituloColuna($cod, $titulo);
                $cfg = [
                    'tipo_avaliacao_id' => (int) ($sub['tipo_avaliacao_id'] ?? $tipo),
                    'evento_avaliativo_id' => $id,
                    'subevento_avaliativo' => 1,
                    'escala_max' => $escalaMaxPadrao,
                    'papel_wizard' => 'exibe',
                    'layout_type' => $ehRec ? 'rec' : 'media',
                ];
                if ($ehRec) {
                    $cfg['recuperacao_exibe'] = 1;
                }
                $comp = $this->componenteBoletim($cod, $titulo, 'provas_sistema', $cfg);
                $comp['blocos_ids'] = [$blocoId];
                $comp['bloco_id'] = $blocoId;
                $out[] = $comp;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $ev
     * @param array<string,bool> $vistos
     */
    private function codigoColunaEventoAvaliativo(array $ev, array $vistos): string
    {
        $sigla = strtolower(preg_replace('/[^a-z0-9_]+/i', '', (string) ($ev['sigla'] ?? '')) ?? '');
        $bim = (int) ($ev['bimestre'] ?? 0);
        // Print Santa Maria / boletim: encaixa no slot do bimestre (b1_pp, b2_pu…).
        if ($bim >= 1 && $bim <= 4 && in_array($sigla, ['pp', 'pu', 'ch', 'pe', 'fal', 'faltas'], true)) {
            $leaf = $sigla === 'faltas' ? 'fal' : $sigla;
            return 'b' . $bim . '_' . $leaf;
        }
        if ($sigla !== '' && !isset($vistos[$sigla])) {
            return $sigla;
        }
        if ($bim >= 60) {
            foreach (['rec', 'recuperacao'] as $cand) {
                if (!isset($vistos[$cand])) {
                    return $cand;
                }
            }
        }
        $id = (int) ($ev['id'] ?? 0);
        $base = 'eva' . $id;
        if (!isset($vistos[$base])) {
            return $base;
        }

        return 'eva_' . $id;
    }

    /**
     * Título curto da coluna (PP, PU, PE, REC…) a partir do código/nome.
     */
    private function siglaTituloColuna(string $codigo, string $nome = ''): string
    {
        $raw = strtolower(trim($codigo));
        // REC/MS de semestre: distingue 1º/2º, mas respeita sigla custom do evento.
        $nomeTrim = trim($nome);
        $nomeUp = strtoupper($nomeTrim);
        if ($raw === 's1_rec') {
            if ($nomeTrim === '' || $nomeUp === 'REC' || $nomeUp === 'REC 1º') {
                return 'REC 1º';
            }
            if (preg_match('/[12]\s*º|semestr|[_\s-]s1\b/i', $nomeTrim)) {
                return $nomeTrim;
            }

            return $nomeTrim . ' 1º';
        }
        if ($raw === 's2_rec') {
            if ($nomeTrim === '' || $nomeUp === 'REC' || $nomeUp === 'REC 2º') {
                return 'REC 2º';
            }
            if (preg_match('/[12]\s*º|semestr|[_\s-]s2\b/i', $nomeTrim)) {
                return $nomeTrim;
            }

            return $nomeTrim . ' 2º';
        }
        if ($raw === 's1_ms') {
            if ($nomeTrim === '' || $nomeUp === 'MS' || $nomeUp === 'MS 1º') {
                return 'MS 1º';
            }

            return preg_match('/[12]\s*º|semestr/i', $nomeTrim) ? $nomeTrim : ($nomeTrim . ' 1º');
        }
        if ($raw === 's2_ms') {
            if ($nomeTrim === '' || $nomeUp === 'MS' || $nomeUp === 'MS 2º') {
                return 'MS 2º';
            }

            return preg_match('/[12]\s*º|semestr/i', $nomeTrim) ? $nomeTrim : ($nomeTrim . ' 2º');
        }
        $leaf = $raw;
        if (preg_match('/^b[1-4]_(.+)$/', $leaf, $m)) {
            $leaf = $m[1];
        }
        if (preg_match('/^s[12]_(.+)$/', $leaf, $m)) {
            $leaf = $m[1];
        }
        $conhecidos = [
            'pp' => 'PP',
            'pu' => 'PU',
            'pe' => 'PE',
            'ch' => 'CH',
            'mu' => 'MU',
            'fal' => 'FAL',
            'faltas' => 'FAL',
            'rec' => 'REC',
            'recuperacao' => 'REC',
            'pf' => 'PF',
            'mf' => 'MF',
            'ms' => 'MS',
            'ma' => 'MA',
        ];
        if (isset($conhecidos[$leaf])) {
            return $conhecidos[$leaf];
        }
        if ($leaf !== '' && strlen($leaf) <= 5 && preg_match('/^[a-z0-9]+$/', $leaf)) {
            return strtoupper($leaf);
        }

        $nomeNorm = strtolower(trim($nome));
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nomeNorm);
            if (is_string($conv) && $conv !== '') {
                $nomeNorm = $conv;
            }
        }
        $nomeNorm = str_replace(['ª', 'º', '°'], ['a', 'o', 'o'], $nomeNorm);
        $nomeNorm = preg_replace('/[^a-z0-9]+/', ' ', $nomeNorm) ?? $nomeNorm;
        $nomeNorm = trim(preg_replace('/\s+/', ' ', $nomeNorm) ?? $nomeNorm);
        $porNome = [
            'prova parcial' => 'PP',
            'prova de unidade' => 'PU',
            'ponto extra' => 'PE',
            '2a chamada' => 'CH',
            '2o chamada' => 'CH',
            'segunda chamada' => 'CH',
            'media da unidade' => 'MU',
            'faltas' => 'FAL',
            'falta' => 'FAL',
            'recuperacao' => 'REC',
            'prova final' => 'PF',
            'media semestral' => 'MS',
            'media anual' => 'MA',
            'media final' => 'MF',
        ];
        if (isset($porNome[$nomeNorm])) {
            return $porNome[$nomeNorm];
        }
        // Eventos com prefixo (ex.: "1sm24 prova parcial").
        foreach ($porNome as $chave => $sigla) {
            if ($nomeNorm === $chave || str_ends_with($nomeNorm, ' ' . $chave)) {
                return $sigla;
            }
        }

        $nome = trim($nome);
        if ($nome !== '' && (function_exists('mb_strlen') ? mb_strlen($nome) : strlen($nome)) <= 6) {
            return function_exists('mb_strtoupper') ? mb_strtoupper($nome) : strtoupper($nome);
        }

        return $leaf !== '' ? strtoupper($leaf) : ($nome !== '' ? $nome : '');
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<string> $erros
     * @param list<array<string,mixed>> $componentes
     * @return list<array<string,mixed>>
     */
    private function mesclarEventosAvaliativosNosComponentes(array $componentes, array &$estado, array &$erros): array
    {
        $vistos = [];
        $siglasJa = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $vistos[$cod] = true;
            if (preg_match('/^b[1-4]_(.+)$/', $cod, $m)) {
                $vistos[$m[1]] = true;
            }
            if (preg_match('/^s[12]_(.+)$/', $cod, $m)) {
                $vistos[$m[1]] = true;
            }
            $sig = $this->siglaTituloColuna($cod, (string) ($c['nome'] ?? ''));
            if ($sig !== '') {
                $siglasJa[strtoupper($sig)] = true;
            }
        }
        $extras = $this->componentesDeEventosAvaliativosMarcados($estado, $vistos, $erros);
        if ($extras === []) {
            return $componentes;
        }

        if (!is_array($estado['nomes_blocos'] ?? null)) {
            $estado['nomes_blocos'] = [];
        }

        $quadroLivre = !empty($estado['quadro_livre']) || ($estado['origem'] ?? '') === 'quadro-impressao';

        // Substitui slots vazios s1_rec/s2_rec/pf quando o evento bate o código.
        foreach ($extras as $extra) {
            $codExtra = strtolower(trim((string) ($extra['codigo'] ?? '')));
            $tituloExtra = $this->siglaTituloColuna($codExtra, trim((string) ($extra['nome'] ?? '')));
            if ($tituloExtra === '') {
                $tituloExtra = trim((string) ($extra['nome'] ?? ''));
            }
            $extra['nome'] = $tituloExtra;

            // No Montar quadro: uma coluna por sigla (evita 10× REC / 5× PF),
            // exceto slots por bimestre (b1_pp ≠ b2_pp), REC de semestre e PF.
            if ($quadroLivre && $tituloExtra !== '' && isset($siglasJa[strtoupper($tituloExtra)])) {
                $slotsDistintos = in_array($codExtra, ['s1_rec', 's2_rec', 'pf', 'rec', 'recuperacao'], true)
                    || (bool) preg_match('/^b[1-4]_/', $codExtra);
                if (
                    !$slotsDistintos
                    && !$this->existePlaceholderCompativelParaExtra($componentes, $codExtra, $tituloExtra)
                ) {
                    continue;
                }
            }

            $substituido = false;
            $ehSlotBimestre = (bool) preg_match('/^b[1-4]_(pp|pu|ch|pe|fal)$/', $codExtra);
            $apenasSlots = in_array($codExtra, ['s1_rec', 's2_rec', 'pf', 'rec', 'recuperacao'], true) || $ehSlotBimestre;
            foreach ($componentes as $i => $c) {
                if (!is_array($c)) {
                    continue;
                }
                $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
                $grpExtra = strtolower(trim((string) (($extra['config']['layout_group'] ?? '') ?: '')));
                // rec genérico → só com layout_group s1/s2 (81/82). Ciclo 61–63 não cai em s1_rec.
                $recParaSlot = false;
                if (in_array($codExtra, ['rec', 'recuperacao'], true) && in_array($cod, ['s1_rec', 's2_rec'], true)) {
                    if ($grpExtra === 's1' || $grpExtra === 's2') {
                        $recParaSlot = ($cod === ($grpExtra === 's2' ? 's2_rec' : 's1_rec'));
                    }
                }
                $pfParaSlot = $codExtra === 'pf' && $cod === 'pf';
                $codigoCompativel = $cod === $codExtra || ($apenasSlots && ($recParaSlot || $pfParaSlot));
                if (!$codigoCompativel) {
                    continue;
                }
                if (!$this->componentePodeReceberEvento($c)) {
                    continue;
                }
                $componentes[$i] = $extra;
                if (in_array($codExtra, ['rec', 'recuperacao'], true)) {
                    $componentes[$i]['codigo'] = $cod;
                    $cfg = is_array($extra['config'] ?? null) ? $extra['config'] : [];
                    $cfg['layout_group'] = $cod === 's2_rec' ? 's2' : 's1';
                    $componentes[$i]['config'] = $cfg;
                    $codExtra = $cod;
                } elseif ($ehSlotBimestre) {
                    $componentes[$i]['codigo'] = $codExtra;
                    $cfg = is_array($extra['config'] ?? null) ? $extra['config'] : [];
                    if (preg_match('/^(b[1-4])_/', $codExtra, $mGrp)) {
                        $cfg['layout_group'] = $mGrp[1];
                    }
                    if (str_ends_with($codExtra, '_fal')) {
                        $cfg['layout_type'] = 'faltas';
                    } else {
                        $cfg['layout_type'] = $cfg['layout_type'] ?? 'media';
                    }
                    $componentes[$i]['config'] = $cfg;
                }
                $substituido = true;
                break;
            }
            if (!$substituido) {
                // Slot já preenchido (ex.: evento_boletim do Demonstrativo): NÃO duplica.
                // O 2º b2_pu em provas_sistema herdava half e transformava 8,8 → 9.
                $jaTemMesmoCodigo = false;
                $ehSlotUnico = $ehSlotBimestre
                    || in_array($codExtra, ['s1_rec', 's2_rec', 'pf', 'rec', 'recuperacao'], true);
                if ($ehSlotUnico && $codExtra !== '') {
                    foreach ($componentes as $cExist) {
                        if (!is_array($cExist)) {
                            continue;
                        }
                        if (strtolower(trim((string) ($cExist['codigo'] ?? ''))) === $codExtra) {
                            $jaTemMesmoCodigo = true;
                            break;
                        }
                    }
                }
                if (!$jaTemMesmoCodigo) {
                    $componentes[] = $extra;
                }
            }
            if ($codExtra !== '' && $tituloExtra !== '') {
                $estado['nomes_blocos'][$codExtra] = function_exists('mb_substr')
                    ? mb_substr($tituloExtra, 0, 60)
                    : substr($tituloExtra, 0, 60);
                $siglasJa[strtoupper($tituloExtra)] = true;
                $vistos[$codExtra] = true;
            }
        }

        return $componentes;
    }

    /**
     * @param array<string,mixed> $estado
     * @return array<string,true>
     */
    private function codigosPlaceholderSemEvento(array $estado): array
    {
        $out = [];
        $componentes = is_array($estado['rascunho_preservado']['componentes'] ?? null)
            ? $estado['rascunho_preservado']['componentes']
            : [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            if ($this->componentePodeReceberEvento($c)) {
                $out[$cod] = true;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $componente
     */
    private function componentePodeReceberEvento(array $componente): bool
    {
        $srcAtual = strtolower(trim((string) ($componente['source_type'] ?? '')));
        $cod = strtolower(trim((string) ($componente['codigo'] ?? '')));
        $cfgAtual = is_array($componente['config'] ?? null) ? $componente['config'] : [];
        if ($cfgAtual === [] && is_string($componente['config_json'] ?? null) && trim((string) $componente['config_json']) !== '') {
            $tmpCfg = json_decode((string) $componente['config_json'], true);
            $cfgAtual = is_array($tmpCfg) ? $tmpCfg : [];
        }
        $evAtual = (int) ($cfgAtual['evento_avaliativo_id'] ?? $componente['evento_avaliativo_id'] ?? 0);

        // Placeholder do Montar quadro (PO/FAZ/TESTEAZ…): pode receber o evento real.
        if ($srcAtual === '' || $srcAtual === 'nenhuma') {
            return true;
        }
        // Não sobrescreve Demonstrativo/evento_boletim já ligado.
        // Exceção: slot REC/PF legado com source preenchido mas sem evento_avaliativo_id.
        $ehSlotRecPf = in_array($cod, ['s1_rec', 's2_rec', 'pf', 'rec', 'recuperacao'], true);

        return $ehSlotRecPf && $evAtual <= 0;
    }

    /**
     * @param list<array<string,mixed>> $componentes
     */
    private function existePlaceholderCompativelParaExtra(array $componentes, string $codExtra, string $tituloExtra): bool
    {
        $siglaExtra = strtoupper(trim($tituloExtra));
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $sigla = strtoupper(trim($this->siglaTituloColuna($cod, (string) ($c['nome'] ?? ''))));
            $mesmoCodigo = $codExtra !== '' && $cod === $codExtra;
            $mesmaSigla = $siglaExtra !== '' && $sigla !== '' && $sigla === $siglaExtra;
            if (($mesmoCodigo || $mesmaSigla) && $this->componentePodeReceberEvento($c)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Códigos que o admin já colocou no quadro (ordem, cabeçalhos, blocos de cálculo).
     *
     * @param array<string,mixed> $estado
     * @return array<string,true>
     */
    private function codigosExplicitosDoQuadro(array $estado): array
    {
        $out = [];
        foreach ((array) ($estado['colunas_ordem'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $out[$cod] = true;
            }
        }
        foreach ($this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) as $lin) {
            foreach ((array) ($lin['colunas'] ?? []) as $cod) {
                $cod = strtolower(trim((string) $cod));
                if ($cod !== '') {
                    $out[$cod] = true;
                }
            }
        }
        foreach ((array) ($estado['blocos_calc'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $out[$cod] = true;
            }
        }
        foreach (array_keys((array) ($estado['formulas_blocos'] ?? [])) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $out[$cod] = true;
            }
        }

        return $out;
    }

    /**
     * Siglas citadas nas fórmulas (PO, FAZ…) — entram no cálculo mesmo sem chip no quadro.
     *
     * @param array<string,mixed> $estado
     * @return array<string,true>
     */
    private function codigosCitadosNasFormulas(array $estado): array
    {
        $out = [];
        $listas = [];
        foreach ((array) ($estado['formulas_blocos'] ?? []) as $toks) {
            if (is_array($toks)) {
                $listas[] = $toks;
            }
        }
        if (is_array($estado['formula_tokens'] ?? null)) {
            $listas[] = $estado['formula_tokens'];
        }
        foreach ((array) ($estado['formulas_materias_blocos'] ?? []) as $porMateria) {
            if (!is_array($porMateria)) {
                continue;
            }
            foreach ($porMateria as $toks) {
                if (is_array($toks)) {
                    $listas[] = $toks;
                }
            }
        }
        foreach ($listas as $toks) {
            foreach ($toks as $t) {
                if (!is_array($t) || (string) ($t['type'] ?? '') !== 'peca') {
                    continue;
                }
                $cod = strtolower(trim((string) ($t['value'] ?? '')));
                if ($cod === '' || !(bool) preg_match('/^[a-z_][a-z0-9_]{0,40}$/', $cod)) {
                    continue;
                }
                if ($cod === '_semanal' || $cod === 'media_sem') {
                    continue;
                }
                $out[$cod] = true;
            }
        }

        return $out;
    }

    /**
     * Colunas do quadro + peças da fórmula + ocultas (fonte do cálculo sem coluna visível).
     *
     * @param array<string,mixed> $estado
     * @return array<string,true>
     */
    private function codigosPermitidosNoRascunhoManual(array $estado): array
    {
        $out = $this->codigosExplicitosDoQuadro($estado);
        foreach ($this->codigosCitadosNasFormulas($estado) as $cod => $_) {
            $out[$cod] = true;
        }
        foreach ($this->normalizarColunasOcultas($estado['colunas_ocultas'] ?? []) as $cod) {
            $out[$cod] = true;
        }

        return $out;
    }

    /**
     * Peça só na fórmula: oculta no quadro. Se o admin colocou o chip de volta, tira da lista.
     *
     * @param array<string,mixed> $estado
     */
    private function sincronizarOcultasDasFormulas(array &$estado): void
    {
        $visiveis = [];
        foreach ((array) ($estado['colunas_ordem'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $visiveis[$cod] = true;
            }
        }
        foreach ($this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) as $lin) {
            foreach ((array) ($lin['colunas'] ?? []) as $cod) {
                $cod = strtolower(trim((string) $cod));
                if ($cod !== '') {
                    $visiveis[$cod] = true;
                }
            }
        }
        // Chip no quadro visual ganha da ocultação (ex.: REC recolocada no título).
        $ocultas = [];
        foreach ($this->normalizarColunasOcultas($estado['colunas_ocultas'] ?? []) as $cod) {
            if (isset($visiveis[$cod])) {
                continue;
            }
            $ocultas[] = $cod;
        }
        $set = array_fill_keys($ocultas, true);
        foreach (array_keys($this->codigosCitadosNasFormulas($estado)) as $cod) {
            if (isset($visiveis[$cod]) || isset($set[$cod])) {
                continue;
            }
            $ocultas[] = $cod;
            $set[$cod] = true;
        }
        $estado['colunas_ocultas'] = $ocultas;
    }

    /**
     * Garante IDs dos eventos cujas siglas aparecem na fórmula (mesmo sem chip no board).
     *
     * @param array<string,mixed> $estado
     */
    private function garantirEventosDasFormulasNoEstado(array &$estado): void
    {
        $citados = $this->codigosCitadosNasFormulas($estado);
        if ($citados === []) {
            return;
        }
        $ids = $this->idsEventosAvaliativos($estado);
        $ja = array_fill_keys($ids, true);
        try {
            $catalogo = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            return;
        }
        $ano = (int) ($estado['ano_letivo'] ?? 0);
        $vistos = [];
        foreach ($catalogo as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $id = (int) ($ev['id'] ?? 0);
            if ($id <= 0 || isset($ja[$id])) {
                continue;
            }
            if ($ano > 0 && (int) ($ev['ano_letivo'] ?? 0) > 0 && (int) $ev['ano_letivo'] !== $ano) {
                continue;
            }
            $codPref = strtolower($this->codigoColunaEventoAvaliativo($ev, $vistos));
            $sigla = strtolower(preg_replace('/[^a-z0-9_]+/i', '', (string) ($ev['sigla'] ?? '')) ?? '');
            $bate = ($codPref !== '' && isset($citados[$codPref]))
                || ($sigla !== '' && isset($citados[$sigla]));
            if (!$bate) {
                continue;
            }
            $ids[] = $id;
            $ja[$id] = true;
            if ($codPref !== '') {
                $vistos[$codPref] = true;
            }
        }
        $estado['eventos_avaliativos_ids'] = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * Montar do zero: mantém colunas do quadro + peças citadas na fórmula (mesmo ocultas).
     *
     * @param list<array<string,mixed>> $componentes
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function filtrarComponentesParaQuadroManual(array $componentes, array $estado): array
    {
        $permitidos = $this->codigosPermitidosNoRascunhoManual($estado);
        if ($permitidos === []) {
            return [];
        }
        $out = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === '_semanal') {
                continue;
            }
            if (isset($permitidos[$cod])) {
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * Quadro livre: puxa colunas dos Demonstrativos de notas marcados.
     *
     * @param array<string,mixed> $estado
     * @param list<string> $erros
     * @return array<string,mixed>
     */
    private function rascunhoMesclandoDemonstrativos(array &$estado, array &$erros): array
    {
        $ano = (int) ($estado['ano_letivo'] ?? date('Y'));
        $nome = trim((string) ($estado['nome'] ?? ''));
        if ($nome === '') {
            $nome = sprintf('Boletim %d', $ano);
        }
        $selfId = (int) ($estado['regra_id'] ?? 0);
        $componentes = [];
        $vistos = [];
        $formulaFinal = 'media_final';

        foreach ($this->idsDemonstrativos($estado) as $idFonte) {
            if ($selfId > 0 && $idFonte === $selfId) {
                $erros[] = 'Não use este mesmo evento como Demonstrativo.';
                continue;
            }
            $ref = $this->ferramentas->obterRegra($idFonte);
            if ($ref === null) {
                $erros[] = 'Demonstrativo #' . $idFonte . ' não encontrado.';
                continue;
            }
            if (strtolower(trim((string) ($ref['exibir_em'] ?? ''))) !== 'notas') {
                $erros[] = '“' . trim((string) ($ref['nome'] ?? '')) . '” não é Demonstrativo de notas.';
                continue;
            }
            $slug = trim((string) ($ref['codigo'] ?? ''));
            if ($slug === '') {
                $erros[] = 'O Demonstrativo “' . trim((string) ($ref['nome'] ?? '')) . '” precisa de código (slug).';
                continue;
            }
            $bim = (int) ($ref['bimestre'] ?? 0);
            $prefixo = ($bim >= 1 && $bim <= 4) ? ('b' . $bim . '_') : '';
            $mapaCodigos = [];
            foreach ((array) ($ref['componentes'] ?? []) as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $codOrig = strtolower(trim((string) ($c['codigo'] ?? '')));
                if ($codOrig === '' || $codOrig === '_semanal') {
                    continue;
                }
                $codNovo = $prefixo !== '' && !str_starts_with($codOrig, 'b' . $bim . '_')
                    ? $prefixo . $codOrig
                    : $codOrig;
                if (isset($vistos[$codNovo])) {
                    continue;
                }
                $vistos[$codNovo] = true;
                $vistos[$codOrig] = true;
                $mapaCodigos[$codOrig] = $codNovo;
                $nomeOrig = trim((string) ($c['nome'] ?? $codOrig));
                // No boletim a coluna mostra a sigla (PP/PU/PE), não "Prova Parcial".
                $nomeCol = $this->siglaTituloColuna($codNovo, $nomeOrig);
                $src = (string) ($c['source_type'] ?? 'nenhuma');
                $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
                // Cabeçalhos do Demonstrativo (ex.: "Avaliações") não vêm pro boletim —
                // aqui o admin monta do zero; só o bimestre separa as colunas.
                unset($cfg['linha_cabecalho']);
                if ($bim >= 1 && $bim <= 4) {
                    $cfg['layout_group'] = 'b' . $bim;
                }
                if ($src === 'calculado') {
                    $exp = trim((string) ($cfg['expressao'] ?? ''));
                    foreach ($mapaCodigos as $de => $para) {
                        if ($de !== $para && $exp !== '') {
                            $exp = preg_replace('/\b' . preg_quote($de, '/') . '\b/', $para, $exp) ?? $exp;
                        }
                    }
                    $cfg['expressao'] = $exp;
                    $componentes[] = $this->componenteBoletim($codNovo, $nomeCol, 'calculado', $cfg);
                } else {
                    // Só aponta pro Demonstrativo — não copia blocos/provas (senão explode em EVA/SEV).
                    $cfgLimpo = [
                        'regra_codigo' => $slug,
                        'componente_codigo' => (string) ($c['codigo'] ?? $codOrig),
                        'papel_wizard' => 'exibe',
                    ];
                    if ($bim >= 1 && $bim <= 4) {
                        $cfgLimpo['layout_group'] = 'b' . $bim;
                    }
                    if (!empty($cfg['layout_type'])) {
                        $cfgLimpo['layout_type'] = $cfg['layout_type'];
                    }
                    $componentes[] = $this->componenteBoletim($codNovo, $nomeCol, 'evento_boletim', $cfgLimpo);
                }
                if (!is_array($estado['nomes_blocos'] ?? null)) {
                    $estado['nomes_blocos'] = [];
                }
                $estado['nomes_blocos'][$codNovo] = $nomeCol;
            }
            $ff = strtolower(trim((string) ($ref['formula_final'] ?? '')));
            if ($ff !== '' && isset($mapaCodigos[$ff])) {
                $formulaFinal = $mapaCodigos[$ff];
            }
        }

        $quadroLivre = !empty($estado['quadro_livre']) || ($estado['origem'] ?? '') === 'quadro-impressao';
        // Quadro "do zero": só entra no board o que o admin clicou (colunas_ordem / linhas).
        if ($quadroLivre) {
            $estado['cabecalhos_manuais'] = 1;
        }

        // Quadro livre: fórmula com PO/FAZ… sem chip ancora o evento e mantém a peça no cálculo.
        if ($quadroLivre) {
            $this->garantirEventosDasFormulasNoEstado($estado);
            $this->sincronizarOcultasDasFormulas($estado);
        }

        $componentes = $this->mesclarEventosAvaliativosNosComponentes($componentes, $estado, $erros);
        if ($quadroLivre) {
            $componentes = $this->filtrarComponentesParaQuadroManual($componentes, $estado);
            // CH/FAL só se o admin já colocou no quadro — não injeta automaticamente.
            $explicitosChFal = $this->codigosExplicitosDoQuadro($estado);
            if ($explicitosChFal !== []) {
                $antes = count($componentes);
                $componentes = $this->garantirChPorBimestreNoQuadro($componentes, $estado);
                $componentes = $this->garantirFalPorBimestreNoQuadro($componentes, $estado);
                if (count($componentes) !== $antes) {
                    $componentes = $this->filtrarComponentesParaQuadroManual($componentes, $estado);
                }
            }
        } else {
            $componentes = $this->garantirChPorBimestreNoQuadro($componentes, $estado);
            $componentes = $this->garantirFalPorBimestreNoQuadro($componentes, $estado);
        }
        // Print Santa Maria: MU faz parte do modelo oficial. Demais quadros: admin cria o bloco.
        if (!$this->ehModeloSantaMaria($estado)) {
            $componentes = $this->removerMuAutomaticaDosComponentes($componentes, $estado);
        } else {
            $estado['linhas_layout'] = $this->garantirMuNasLinhasSantaMaria(
                is_array($estado['linhas_layout'] ?? null) ? $estado['linhas_layout'] : []
            );
        }
        // Com cabeçalhos manuais, a ordem dos chips manda — não força PP/PU/CH/PE.
        if (empty($estado['cabecalhos_manuais'])) {
            $componentes = $this->ordenarColunasPadraoPorBimestre($componentes);
        }
        $componentes = $this->filtrarComponentesQuadroImpressao($componentes, $estado);

        if ($componentes === [] && !$quadroLivre) {
            $erros[] = 'Selecione ao menos um Demonstrativo de notas ou um Evento avaliativo.';
        }

        // Cabeçalhos por bimestre (I/II/III/IV) a partir das colunas vivas.
        // Sem cabecalhos_manuais, reconstrói ao marcar/desmarcar períodos (senão fica só o 1º).
        // Com cabeçalhos manuais / quadro livre: o admin monta do zero (Novo cabeçalho).
        $linhasLayout = $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []);
        $autoBim = $this->linhasLayoutPorBimestreDosComponentes($componentes);
        if (empty($estado['cabecalhos_manuais']) && !$quadroLivre) {
            // Auto por bimestre; cabeçalhos manuais preservam o que o admin montou.
            $linhasLayout = $linhasLayout === []
                ? $autoBim
                : $this->mesclarLinhasLayoutBimestresNovos($linhasLayout, $autoBim);
            $estado['linhas_layout'] = $linhasLayout;
            $componentes = $this->aplicarLinhasLayoutNosComponentes($componentes, $linhasLayout);
        } else {
            $estado['linhas_layout'] = $linhasLayout;
            $componentes = $this->aplicarLinhasLayoutNosComponentes($componentes, $linhasLayout);
        }

        // Ordem local só com colunas vivas — evita EVA/SEV fantasma de seleção antiga.
        // Blocos de cálculo (MU etc.) vivem em blocos_calc/formulas — não descartar do layout.
        $vivos = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '') {
                $vivos[$cod] = true;
            }
        }
        foreach ((array) ($estado['blocos_calc'] ?? []) as $codBloco) {
            $codBloco = strtolower(trim((string) $codBloco));
            if ($codBloco !== '') {
                $vivos[$codBloco] = true;
            }
        }
        foreach (array_keys((array) ($estado['formulas_blocos'] ?? [])) as $codFormula) {
            $codFormula = strtolower(trim((string) $codFormula));
            if ($codFormula !== '') {
                $vivos[$codFormula] = true;
            }
        }
        $ordem = [];
        foreach ((array) ($estado['colunas_ordem'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '' && isset($vivos[$cod]) && !isset($ordem[$cod])) {
                $ordem[$cod] = true;
            }
        }
        // Quadro livre: não espalha todas as colunas vivas no board — só o que o admin adicionou.
        if (!$quadroLivre) {
            foreach (array_keys($vivos) as $cod) {
                if (!isset($ordem[$cod])) {
                    $ordem[$cod] = true;
                }
            }
        } else {
            foreach (array_keys($vivos) as $cod) {
                $ehCalc = in_array($cod, array_map('strtolower', (array) ($estado['blocos_calc'] ?? [])), true)
                    || isset(($estado['formulas_blocos'] ?? [])[$cod]);
                if ($ehCalc && !isset($ordem[$cod])) {
                    $ordem[$cod] = true;
                }
            }
        }
        $estado['colunas_ordem'] = array_keys($ordem);
        $linhasLimpas = [];
        foreach ($linhasLayout as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $cols = [];
            foreach ((array) ($lin['colunas'] ?? []) as $codLin) {
                $codLin = strtolower(trim((string) $codLin));
                if ($codLin !== '' && isset($vivos[$codLin]) && !in_array($codLin, $cols, true)) {
                    $cols[] = $codLin;
                }
            }
            if (count($cols) < 1) {
                continue;
            }
            $lin['colunas'] = $cols;
            $linhasLimpas[] = $lin;
        }
        $linhasLayout = $this->normalizarLinhasLayout($linhasLimpas);
        $estado['linhas_layout'] = $linhasLayout;
        // Ordem do demonstrativo = ordem dos chips nos cabeçalhos.
        if (!empty($estado['cabecalhos_manuais']) && $linhasLayout !== []) {
            $ordemLayout = [];
            foreach ($linhasLayout as $linOrd) {
                foreach ((array) ($linOrd['colunas'] ?? []) as $codLin) {
                    $codLin = strtolower(trim((string) $codLin));
                    if ($codLin !== '' && isset($vivos[$codLin]) && !isset($ordemLayout[$codLin])) {
                        $ordemLayout[$codLin] = true;
                    }
                }
            }
            foreach ($estado['colunas_ordem'] as $codOrd) {
                $codOrd = strtolower(trim((string) $codOrd));
                if ($codOrd !== '' && isset($vivos[$codOrd]) && !isset($ordemLayout[$codOrd])) {
                    $ordemLayout[$codOrd] = true;
                }
            }
            $estado['colunas_ordem'] = array_keys($ordemLayout);
        }

        return [
            'modo' => ($estado['modo'] ?? 'criar') === 'editar' && !empty($estado['regra_id']) ? 'editar' : 'criar',
            'regra_id' => !empty($estado['regra_id']) ? (int) $estado['regra_id'] : null,
            'nome' => $nome,
            'codigo' => trim((string) ($estado['codigo'] ?? '')),
            'descricao_curta' => '',
            'formula_final' => $formulaFinal,
            'exibir_em' => 'boletim',
            'finalidade' => $this->normalizarFinalidade($estado['finalidade'] ?? 'oficial'),
            'ano_letivo' => $ano,
            'bimestre' => null,
            'usar_resultado_aprovacao' => 1,
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'turmas_ids' => $estado['turmas_ids'] ?? [],
            'materias_ids' => $estado['materias_ids'] ?? [],
            'series_ids' => $estado['series_ids'] ?? [],
            'round_mode' => (string) ($estado['round_mode'] ?? 'none'),
            'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? 2),
            'nota_minima_aprovacao' => (float) ($estado['nota_minima_aprovacao'] ?? 6),
            'nota_minima_origem' => (string) ($estado['nota_minima_origem'] ?? 'padrao'),
            'nota_minima_regra_nome' => (string) ($estado['nota_minima_regra_nome'] ?? ''),
            'componentes' => $componentes,
            'linhas_layout' => $linhasLayout,
        ];
    }

    /**
     * Garante CH (2ª Chamada) em cada bimestre que já tem colunas do Demonstrativo.
     * Prefere coluna do Demonstrativo (evento_boletim); senão liga ao Evento avaliativo CH.
     *
     * @param list<array<string,mixed>> $componentes
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function garantirChPorBimestreNoQuadro(array $componentes, array &$estado): array
    {
        $bims = [];
        $jaTem = [];
        $chJaNoBim = [];
        $regraPorBim = [];
        // Normaliza CH já existente com código ≠ bN_ch (ex.: b1_segunda_chamada → b1_ch).
        $remapCh = [];
        foreach ($componentes as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || !preg_match('/^b([1-4])_/', $cod, $m)) {
                continue;
            }
            $bim = (int) $m[1];
            $codCanon = 'b' . $bim . '_ch';
            if ($cod === $codCanon) {
                continue;
            }
            $sig = strtoupper($this->siglaTituloColuna($cod, (string) ($c['nome'] ?? '')));
            if ($sig !== 'CH') {
                continue;
            }
            $componentes[$i]['codigo'] = $codCanon;
            $componentes[$i]['nome'] = 'CH';
            $cfgNorm = $this->configDoComponente($componentes[$i]);
            $cfgNorm['layout_group'] = 'b' . $bim;
            $componentes[$i]['config'] = $cfgNorm;
            if (isset($componentes[$i]['config_json'])) {
                $componentes[$i]['config_json'] = json_encode($cfgNorm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (!is_array($estado['nomes_blocos'] ?? null)) {
                $estado['nomes_blocos'] = [];
            }
            unset($estado['nomes_blocos'][$cod]);
            $estado['nomes_blocos'][$codCanon] = 'CH';
            $remapCh[$cod] = $codCanon;
        }
        if ($remapCh !== []) {
            $ordemRemap = [];
            foreach ((array) ($estado['colunas_ordem'] ?? []) as $codOrd) {
                $codOrd = strtolower(trim((string) $codOrd));
                if ($codOrd === '') {
                    continue;
                }
                $codOrd = $remapCh[$codOrd] ?? $codOrd;
                if (!in_array($codOrd, $ordemRemap, true)) {
                    $ordemRemap[] = $codOrd;
                }
            }
            $estado['colunas_ordem'] = $ordemRemap;
            $linhasRemap = [];
            foreach ($this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) as $lin) {
                if (!is_array($lin)) {
                    continue;
                }
                $cols = [];
                foreach ((array) ($lin['colunas'] ?? []) as $cLin) {
                    $cLin = strtolower(trim((string) $cLin));
                    if ($cLin === '') {
                        continue;
                    }
                    $cLin = $remapCh[$cLin] ?? $cLin;
                    if (!in_array($cLin, $cols, true)) {
                        $cols[] = $cLin;
                    }
                }
                $lin['colunas'] = $cols;
                $linhasRemap[] = $lin;
            }
            $estado['linhas_layout'] = $linhasRemap;
        }

        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $jaTem[$cod] = true;
            if (!preg_match('/^b([1-4])_/', $cod, $m)) {
                continue;
            }
            $bim = (int) $m[1];
            $bims[$bim] = true;
            if (preg_match('/^b[1-4]_ch$/', $cod)
                || strtoupper($this->siglaTituloColuna($cod, (string) ($c['nome'] ?? ''))) === 'CH') {
                $chJaNoBim[$bim] = true;
            }
            $cfg = $this->configDoComponente($c);
            $slug = trim((string) ($cfg['regra_codigo'] ?? ''));
            if ($slug !== '' && (string) ($c['source_type'] ?? '') === 'evento_boletim' && empty($regraPorBim[$bim])) {
                $regraPorBim[$bim] = $slug;
            }
        }
        if ($bims === []) {
            return $componentes;
        }

        $chPorBim = [];
        try {
            foreach ($this->ferramentas->listarEventosAvaliativosParaBoletim() as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $bim = (int) ($ev['bimestre'] ?? 0);
                if ($bim < 1 || $bim > 4 || isset($chPorBim[$bim])) {
                    continue;
                }
                $sigla = strtoupper(trim((string) ($ev['sigla'] ?? '')));
                $nomeNorm = strtolower(trim((string) ($ev['nome'] ?? '')));
                if (function_exists('iconv')) {
                    $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nomeNorm);
                    if (is_string($conv) && $conv !== '') {
                        $nomeNorm = $conv;
                    }
                }
                $nomeNorm = preg_replace('/[^a-z0-9]+/', ' ', $nomeNorm) ?? $nomeNorm;
                $ehCh = $sigla === 'CH'
                    || str_contains($nomeNorm, '2a chamada')
                    || str_contains($nomeNorm, 'segunda chamada');
                if (!$ehCh) {
                    continue;
                }
                $chPorBim[$bim] = $ev;
            }
        } catch (\Throwable $e) {
            // Sem catálogo: ainda cria a coluna vazia.
        }

        if (!is_array($estado['nomes_blocos'] ?? null)) {
            $estado['nomes_blocos'] = [];
        }

        foreach ([1, 2, 3, 4] as $bim) {
            if (empty($bims[$bim]) || !empty($chJaNoBim[$bim]) || !empty($jaTem['b' . $bim . '_ch'])) {
                continue;
            }
            $cod = 'b' . $bim . '_ch';
            $cfg = [
                'layout_group' => 'b' . $bim,
                'layout_type' => 'media',
                'papel_wizard' => 'exibe',
            ];

            // 1) Coluna CH dentro do Demonstrativo do bimestre.
            $slugDemo = (string) ($regraPorBim[$bim] ?? '');
            if ($slugDemo !== '') {
                try {
                    $ref = $this->ferramentas->obterRegraPorCodigo($slugDemo);
                } catch (\Throwable $e) {
                    $ref = null;
                }
                if (is_array($ref)) {
                    $colFonte = $this->codigoColunaPorAliases($ref, ['CH', 'ch', '2a_chamada', 'segunda_chamada']);
                    if ($colFonte === '') {
                        foreach ((array) ($ref['componentes'] ?? []) as $cRef) {
                            if (!is_array($cRef)) {
                                continue;
                            }
                            $sigRef = strtoupper($this->siglaTituloColuna(
                                (string) ($cRef['codigo'] ?? ''),
                                (string) ($cRef['nome'] ?? '')
                            ));
                            if ($sigRef === 'CH') {
                                $colFonte = (string) ($cRef['codigo'] ?? '');
                                break;
                            }
                        }
                    }
                    if ($colFonte !== '') {
                        $componentes[] = $this->componenteBoletim($cod, 'CH', 'evento_boletim', [
                            'regra_codigo' => $slugDemo,
                            'componente_codigo' => $colFonte,
                            'layout_group' => 'b' . $bim,
                            'layout_type' => 'media',
                            'papel_wizard' => 'exibe',
                        ]);
                        $estado['nomes_blocos'][$cod] = 'CH';
                        $jaTem[$cod] = true;
                        $chJaNoBim[$bim] = true;
                        continue;
                    }
                }
            }

            // 2) Evento avaliativo CH do bimestre (2ª Chamada).
            $ev = $chPorBim[$bim] ?? null;
            if (is_array($ev)) {
                $evId = (int) ($ev['id'] ?? 0);
                $tipo = (int) ($ev['tipo_avaliacao_id'] ?? 0);
                $blocoId = 0;
                foreach ((array) ($ev['subeventos'] ?? []) as $sub) {
                    if (is_array($sub) && (int) ($sub['id'] ?? 0) > 0) {
                        $blocoId = (int) $sub['id'];
                        if ($tipo <= 0) {
                            $tipo = (int) ($sub['tipo_avaliacao_id'] ?? 0);
                        }
                        break;
                    }
                }
                $cfg['evento_avaliativo_id'] = $evId > 0 ? $evId : null;
                $cfg['tipo_avaliacao_id'] = $tipo > 0 ? $tipo : null;
                $comp = $this->componenteBoletim($cod, 'CH', 'provas_sistema', $cfg);
                if ($blocoId > 0) {
                    $comp['blocos_ids'] = [$blocoId];
                    $comp['bloco_id'] = $blocoId;
                }
                $componentes[] = $comp;
            } else {
                // 3) Slot vazio — o admin liga depois.
                $componentes[] = $this->componenteBoletim($cod, 'CH', 'nenhuma', $cfg);
            }
            $estado['nomes_blocos'][$cod] = 'CH';
            $jaTem[$cod] = true;
            $chJaNoBim[$bim] = true;
        }

        $chVivos = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (preg_match('/^b([1-4])_ch$/', $cod, $m)) {
                $chVivos[(int) $m[1]] = $cod;
            }
        }
        $estado['linhas_layout'] = $this->garantirChNasLinhasLayout(
            $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []),
            $chVivos
        );

        return $componentes;
    }

    /**
     * Garante coluna FAL (bN_fal) em cada bimestre que já tem outras colunas no Montar quadro.
     * Fonte: fontes_faltas[N] → faltas_evento; senão placeholder vazia (como no Santa Maria).
     *
     * @param list<array<string,mixed>> $componentes
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function garantirFalPorBimestreNoQuadro(array $componentes, array &$estado): array
    {
        $bims = [];
        $jaTem = [];
        $falJaNoBim = [];
        foreach ($componentes as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $jaTem[$cod] = true;
            $cfg = $this->configDoComponente($c);
            $src = (string) ($c['source_type'] ?? '');
            $lt = strtolower((string) ($cfg['layout_type'] ?? ''));
            $ehFal = $src === 'faltas_evento'
                || $lt === 'faltas'
                || (bool) preg_match('/^b[1-4]_fal(tas)?$/', $cod)
                || strtoupper($this->siglaTituloColuna($cod, (string) ($c['nome'] ?? ''))) === 'FAL';

            if (preg_match('/^b([1-4])_/', $cod, $m)) {
                $bim = (int) $m[1];
                $bims[$bim] = true;
                if ($ehFal) {
                    $falJaNoBim[$bim] = true;
                    $codCanon = 'b' . $bim . '_fal';
                    if ($cod !== $codCanon) {
                        $componentes[$i]['codigo'] = $codCanon;
                        $componentes[$i]['nome'] = 'FAL';
                        $cfg['layout_group'] = 'b' . $bim;
                        $cfg['layout_type'] = 'faltas';
                        $componentes[$i]['config'] = $cfg;
                        if (isset($componentes[$i]['config_json'])) {
                            $componentes[$i]['config_json'] = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        if (!is_array($estado['nomes_blocos'] ?? null)) {
                            $estado['nomes_blocos'] = [];
                        }
                        unset($estado['nomes_blocos'][$cod]);
                        $estado['nomes_blocos'][$codCanon] = 'FAL';
                        unset($jaTem[$cod]);
                        $jaTem[$codCanon] = true;
                    }
                }
            } elseif ($ehFal) {
                // Faltas sem slot de bimestre — mantém, mas não conta como bN_fal.
            }
        }
        if ($bims === []) {
            return $componentes;
        }

        $fontesFaltas = $this->normalizarFontesBimestres($estado['fontes_faltas'] ?? []);
        // Se o passo Peças não preencheu, tenta o catálogo do ano (mesmo critério do seed SM).
        $ano = (int) ($estado['ano_letivo'] ?? 0);
        foreach ([1, 2, 3, 4] as $bim) {
            if ((int) ($fontesFaltas[$bim] ?? 0) > 0) {
                continue;
            }
            try {
                foreach ($this->ferramentas->listarFaltasEventos(300) as $fe) {
                    if (!is_array($fe)) {
                        continue;
                    }
                    if ($ano > 0 && (int) ($fe['ano_letivo'] ?? 0) !== $ano) {
                        continue;
                    }
                    if ((int) ($fe['bimestre'] ?? 0) !== $bim) {
                        continue;
                    }
                    $fid = (int) ($fe['id'] ?? 0);
                    if ($fid > 0) {
                        $fontesFaltas[$bim] = $fid;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Sem catálogo: cria slot vazio.
            }
        }
        $estado['fontes_faltas'] = $fontesFaltas;

        if (!is_array($estado['nomes_blocos'] ?? null)) {
            $estado['nomes_blocos'] = [];
        }

        foreach ([1, 2, 3, 4] as $bim) {
            if (empty($bims[$bim]) || !empty($falJaNoBim[$bim]) || !empty($jaTem['b' . $bim . '_fal'])) {
                continue;
            }
            $cod = 'b' . $bim . '_fal';
            $idFaltas = (int) ($fontesFaltas[$bim] ?? 0);
            $cfg = [
                'layout_group' => 'b' . $bim,
                'layout_type' => 'faltas',
                'papel_wizard' => 'exibe',
            ];
            if ($idFaltas > 0) {
                $evF = null;
                try {
                    $evF = $this->ferramentas->obterFaltasEvento($idFaltas);
                } catch (\Throwable $e) {
                    $evF = null;
                }
                if (is_array($evF)) {
                    $cfg['faltas_evento_id'] = $idFaltas;
                    $componentes[] = $this->componenteBoletim($cod, 'FAL', 'faltas_evento', $cfg);
                } else {
                    $componentes[] = $this->componenteBoletim($cod, 'FAL', 'nenhuma', $cfg);
                }
            } else {
                $componentes[] = $this->componenteBoletim($cod, 'FAL', 'nenhuma', $cfg);
            }
            $estado['nomes_blocos'][$cod] = 'FAL';
            $jaTem[$cod] = true;
            $falJaNoBim[$bim] = true;
        }

        $falVivos = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (preg_match('/^b([1-4])_fal$/', $cod, $m)) {
                $falVivos[(int) $m[1]] = $cod;
            }
        }
        $estado['linhas_layout'] = $this->garantirFalNasLinhasLayout(
            $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []),
            $falVivos
        );

        return $componentes;
    }

    /**
     * Insere bN_fal após PE (ou no fim do grupo do bimestre) em cada linha de layout.
     *
     * @param list<array{id:string,nome:string,colunas:list<string>}> $linhas
     * @param array<int,string> $falVivos bimestre => codigo bN_fal
     * @return list<array{id:string,nome:string,colunas:list<string>}>
     */
    private function garantirFalNasLinhasLayout(array $linhas, array $falVivos = []): array
    {
        if ($linhas === [] || $falVivos === []) {
            return $linhas;
        }
        $out = [];
        foreach ($linhas as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $cols = [];
            foreach ((array) ($lin['colunas'] ?? []) as $c) {
                $c = strtolower(trim((string) $c));
                if ($c !== '' && !in_array($c, $cols, true)) {
                    $cols[] = $c;
                }
            }
            $bimsNaLinha = [];
            foreach ($cols as $c) {
                if (preg_match('/^b([1-4])_/', $c, $m)) {
                    $bimsNaLinha[(int) $m[1]] = true;
                }
            }
            foreach (array_keys($bimsNaLinha) as $bim) {
                $codFal = (string) ($falVivos[$bim] ?? '');
                if ($codFal === '' || in_array($codFal, $cols, true)) {
                    continue;
                }
                $codPe = 'b' . $bim . '_pe';
                $idxPe = array_search($codPe, $cols, true);
                if ($idxPe !== false) {
                    array_splice($cols, (int) $idxPe + 1, 0, [$codFal]);
                    continue;
                }
                $idxLast = null;
                foreach ($cols as $i => $c) {
                    if (str_starts_with($c, 'b' . $bim . '_')) {
                        $idxLast = $i;
                    }
                }
                if ($idxLast !== null) {
                    array_splice($cols, (int) $idxLast + 1, 0, [$codFal]);
                } else {
                    $cols[] = $codFal;
                }
            }
            $lin['colunas'] = $cols;
            $out[] = $lin;
        }

        return $out;
    }

    /**
     * Insere bN_ch após PU em cada linha de bimestre que já tem o componente CH.
     *
     * @param list<array{id:string,nome:string,colunas:list<string>}> $linhas
     * @param array<int,string> $chVivos bimestre => codigo bN_ch
     * @return list<array{id:string,nome:string,colunas:list<string>}>
     */
    private function garantirChNasLinhasLayout(array $linhas, array $chVivos = []): array
    {
        if ($linhas === [] || $chVivos === []) {
            return $linhas;
        }
        $out = [];
        foreach ($linhas as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $cols = [];
            foreach ((array) ($lin['colunas'] ?? []) as $c) {
                $c = strtolower(trim((string) $c));
                if ($c !== '' && !in_array($c, $cols, true)) {
                    $cols[] = $c;
                }
            }
            $bimsNaLinha = [];
            foreach ($cols as $c) {
                if (preg_match('/^b([1-4])_/', $c, $m)) {
                    $bimsNaLinha[(int) $m[1]] = true;
                }
            }
            foreach (array_keys($bimsNaLinha) as $bim) {
                $codCh = (string) ($chVivos[$bim] ?? '');
                if ($codCh === '' || in_array($codCh, $cols, true)) {
                    continue;
                }
                $codPu = 'b' . $bim . '_pu';
                $idxPu = array_search($codPu, $cols, true);
                if ($idxPu !== false) {
                    array_splice($cols, (int) $idxPu + 1, 0, [$codCh]);
                    continue;
                }
                $idxFirst = null;
                foreach ($cols as $i => $c) {
                    if (str_starts_with($c, 'b' . $bim . '_')) {
                        $idxFirst = $i;
                        break;
                    }
                }
                if ($idxFirst !== null) {
                    array_splice($cols, (int) $idxFirst + 1, 0, [$codCh]);
                } else {
                    $cols[] = $codCh;
                }
            }
            $lin['colunas'] = $cols;
            $out[] = $lin;
        }

        return $out;
    }

    /**
     * Remove slots automáticos bN_mu (média da unidade).
     * O admin usa “Adicionar bloco de cálculo” e define o nome/fórmula.
     *
     * @param list<array<string,mixed>> $componentes
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function removerMuAutomaticaDosComponentes(array $componentes, array &$estado): array
    {
        $out = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (preg_match('/^b[1-4]_mu$/', $cod)) {
                continue;
            }
            $out[] = $c;
        }

        if (is_array($estado['blocos_calc'] ?? null)) {
            $estado['blocos_calc'] = array_values(array_filter(
                $estado['blocos_calc'],
                static fn ($cod) => !preg_match('/^b[1-4]_mu$/i', (string) $cod)
            ));
        }
        foreach (['formulas_blocos', 'nomes_blocos', 'escalas_blocos', 'media_ciclo_blocos', 'media_ciclo_destinos', 'formulas_materias_blocos'] as $chave) {
            if (!is_array($estado[$chave] ?? null)) {
                continue;
            }
            foreach (array_keys($estado[$chave]) as $cod) {
                if (preg_match('/^b[1-4]_mu$/i', (string) $cod)) {
                    unset($estado[$chave][$cod]);
                }
            }
        }
        if (is_array($estado['colunas_ordem'] ?? null)) {
            $estado['colunas_ordem'] = array_values(array_filter(
                $estado['colunas_ordem'],
                static fn ($cod) => !preg_match('/^b[1-4]_mu$/i', (string) $cod)
            ));
        }
        if (is_array($estado['linhas_layout'] ?? null)) {
            $estado['linhas_layout'] = $this->normalizarLinhasLayout(array_map(
                static function ($lin) {
                    if (!is_array($lin)) {
                        return $lin;
                    }
                    $lin['colunas'] = array_values(array_filter(
                        (array) ($lin['colunas'] ?? []),
                        static fn ($cod) => !preg_match('/^b[1-4]_mu$/i', (string) $cod)
                    ));

                    return $lin;
                },
                $estado['linhas_layout']
            ));
        }

        return $out;
    }

    /**
     * Ordena colunas de cada bimestre no padrão do print: PP, PU, CH, PE, MU, FAL…
     *
     * @param list<array<string,mixed>> $componentes
     * @return list<array<string,mixed>>
     */
    private function ordenarColunasPadraoPorBimestre(array $componentes): array
    {
        $pref = ['pp' => 1, 'pu' => 2, 'ch' => 3, 'pe' => 4, 'mu' => 5, 'fal' => 6, 'faltas' => 6];
        $porBim = [1 => [], 2 => [], 3 => [], 4 => []];
        $outros = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (preg_match('/^b([1-4])_(.+)$/', $cod, $m)) {
                $porBim[(int) $m[1]][] = $c;
            } else {
                $outros[] = $c;
            }
        }
        $out = [];
        foreach ([1, 2, 3, 4] as $bim) {
            $lista = $porBim[$bim];
            usort($lista, static function (array $a, array $b) use ($pref): int {
                $ca = strtolower(trim((string) ($a['codigo'] ?? '')));
                $cb = strtolower(trim((string) ($b['codigo'] ?? '')));
                $la = preg_match('/^b[1-4]_(.+)$/', $ca, $ma) ? $ma[1] : $ca;
                $lb = preg_match('/^b[1-4]_(.+)$/', $cb, $mb) ? $mb[1] : $cb;
                $pa = $pref[$la] ?? 50;
                $pb = $pref[$lb] ?? 50;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return $ca <=> $cb;
            });
            foreach ($lista as $c) {
                $out[] = $c;
            }
        }
        foreach ($outros as $c) {
            $out[] = $c;
        }

        return $out;
    }

    /**
     * No Montar quadro só ficam colunas dos Demonstrativos + eventos avulsos marcados.
     *
     * @param list<array<string,mixed>> $componentes
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function filtrarComponentesQuadroImpressao(array $componentes, array $estado): array
    {
        $marcados = [];
        foreach ($this->idsEventosAvaliativos($estado) as $id) {
            $marcados[$id] = true;
        }
        $temChBimestre = false;
        foreach ($componentes as $c0) {
            if (!is_array($c0)) {
                continue;
            }
            $cod0 = strtolower(trim((string) ($c0['codigo'] ?? '')));
            if (preg_match('/^b[1-4]_ch$/', $cod0)) {
                $temChBimestre = true;
                break;
            }
        }

        $out = [];
        $siglasAvulsas = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $cfg = $this->configDoComponente($c);
            $evId = (int) ($cfg['evento_avaliativo_id'] ?? 0);
            $src = (string) ($c['source_type'] ?? '');

            // Colunas vindas do Demonstrativo (evento_boletim / calculado com regra_codigo).
            if ($src === 'evento_boletim' || ($src === 'calculado' && !empty($cfg['regra_codigo']))) {
                $out[] = $c;
                continue;
            }
            // CH (2ª Chamada) e slots vazios por bimestre do print.
            if ($src === 'nenhuma' && preg_match('/^b[1-4]_ch$/', $cod)) {
                $out[] = $c;
                continue;
            }
            // FAL (faltas) por bimestre — fonte SchoolAbsence ou slot vazio.
            if (
                ($src === 'faltas_evento' || $src === 'nenhuma')
                && (
                    preg_match('/^b[1-4]_fal(tas)?$/', $cod)
                    || strtolower((string) ($cfg['layout_type'] ?? '')) === 'faltas'
                )
            ) {
                $out[] = $c;
                continue;
            }
            if ($src === 'calculado' && empty($cfg['evento_avaliativo_id']) && empty($cfg['subevento_avaliativo'])) {
                $out[] = $c;
                continue;
            }
            // CH ligado a evento avaliativo do bimestre (provas_sistema com código bN_ch).
            if ($src === 'provas_sistema' && preg_match('/^b[1-4]_ch$/', $cod)) {
                $out[] = $c;
                continue;
            }

            // Avulso: só se estiver marcado.
            if ($evId > 0) {
                if (!isset($marcados[$evId])) {
                    continue;
                }
                $sig = strtoupper($this->siglaTituloColuna($cod, (string) ($c['nome'] ?? '')));
                // CH já existe como bN_ch — não cria coluna avulsa duplicada.
                if ($temChBimestre && $sig === 'CH' && !preg_match('/^b[1-4]_ch$/', $cod)) {
                    continue;
                }
                // b1_pp e b2_pp compartilham a sigla PP — não deduplicar entre bimestres.
                $ehSlotBimestre = (bool) preg_match('/^b[1-4]_/', $cod);
                if (
                    $sig !== ''
                    && isset($siglasAvulsas[$sig])
                    && !$ehSlotBimestre
                    && !in_array($cod, ['s1_rec', 's2_rec'], true)
                ) {
                    continue;
                }
                if ($ehSlotBimestre) {
                    // Evita o mesmo código duas vezes; permite PP/PU/CH/PE em cada bimestre.
                    if (isset($siglasAvulsas[$cod])) {
                        continue;
                    }
                    $siglasAvulsas[$cod] = true;
                } elseif ($sig !== '') {
                    $siglasAvulsas[$sig] = true;
                }
                $out[] = $c;
                continue;
            }

            // Fantasma eva*/sev*/rcv* sem vínculo marcado.
            if (preg_match('/^(eva|sev|rcv)_?\d+$/', $cod)) {
                continue;
            }

            $out[] = $c;
        }

        return $out;
    }

    /**
     * Agrupa colunas por bimestre (b1_, b2_…) sob I BIMESTRE, II BIMESTRE… (sem "Avaliações").
     *
     * @param list<array<string,mixed>> $componentes
     * @return list<array{id:string,nome:string,colunas:list<string>}>
     */
    private function linhasLayoutPorBimestreDosComponentes(array $componentes): array
    {
        $porBim = [1 => [], 2 => [], 3 => [], 4 => []];
        $rotulos = [1 => 'I BIMESTRE', 2 => 'II BIMESTRE', 3 => 'III BIMESTRE', 4 => 'IV BIMESTRE'];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || !preg_match('/^b([1-4])_/', $cod, $m)) {
                continue;
            }
            $bim = (int) $m[1];
            if (!in_array($cod, $porBim[$bim], true)) {
                $porBim[$bim][] = $cod;
            }
        }
        $pref = ['pp' => 1, 'pu' => 2, 'ch' => 3, 'pe' => 4, 'mu' => 5, 'fal' => 6, 'faltas' => 6];
        $linhas = [];
        foreach ([1, 2, 3, 4] as $bim) {
            if (count($porBim[$bim]) < 1) {
                continue;
            }
            $cols = $porBim[$bim];
            usort($cols, static function (string $a, string $b) use ($pref): int {
                $la = preg_match('/^b[1-4]_(.+)$/', $a, $ma) ? $ma[1] : $a;
                $lb = preg_match('/^b[1-4]_(.+)$/', $b, $mb) ? $mb[1] : $b;
                $pa = $pref[$la] ?? 50;
                $pb = $pref[$lb] ?? 50;
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }

                return $a <=> $b;
            });
            $linhas[] = [
                'id' => 'bim_' . $bim,
                'nome' => $rotulos[$bim],
                'colunas' => $cols,
            ];
        }

        return $linhas;
    }

    /**
     * Acrescenta cabeçalhos de bimestre que ainda não existem no layout manual.
     * Também inclui colunas novas em linhas bim_* já existentes.
     *
     * @param list<array<string,mixed>> $atuais
     * @param list<array<string,mixed>> $auto
     * @return list<array<string,mixed>>
     */
    private function mesclarLinhasLayoutBimestresNovos(array $atuais, array $auto): array
    {
        $porId = [];
        // Bimestres já cobertos por cabeçalho manual (ex.: "I BIMESTRE" com id lin…).
        $bimsCobertos = [];
        foreach ($atuais as $idx => $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $id = trim((string) ($linha['id'] ?? ''));
            if ($id !== '') {
                $porId[$id] = $idx;
            }
            if (preg_match('/^bim_([1-4])$/', $id, $mId)) {
                $bimsCobertos[(int) $mId[1]] = true;
            }
            foreach ((array) ($linha['colunas'] ?? []) as $codLin) {
                $codLin = strtolower(trim((string) $codLin));
                if (preg_match('/^b([1-4])_/', $codLin, $mCol)) {
                    $bimsCobertos[(int) $mCol[1]] = true;
                }
            }
            $nomeLin = mb_strtolower(trim((string) ($linha['nome'] ?? '')));
            $mapaNome = [
                1 => ['i bimestre', '1º bimestre', '1o bimestre', '1° bimestre'],
                2 => ['ii bimestre', '2º bimestre', '2o bimestre', '2° bimestre'],
                3 => ['iii bimestre', '3º bimestre', '3o bimestre', '3° bimestre'],
                4 => ['iv bimestre', '4º bimestre', '4o bimestre', '4° bimestre'],
            ];
            foreach ($mapaNome as $bimN => $aliases) {
                if (in_array($nomeLin, $aliases, true)) {
                    $bimsCobertos[$bimN] = true;
                }
            }
        }
        foreach ($auto as $linhaAuto) {
            if (!is_array($linhaAuto)) {
                continue;
            }
            $id = trim((string) ($linhaAuto['id'] ?? ''));
            if ($id === '' || !preg_match('/^bim_([1-4])$/', $id, $mAuto)) {
                continue;
            }
            $bimAuto = (int) $mAuto[1];
            $colsAuto = [];
            foreach ((array) ($linhaAuto['colunas'] ?? []) as $cod) {
                $cod = strtolower(trim((string) $cod));
                if ($cod !== '' && !in_array($cod, $colsAuto, true)) {
                    $colsAuto[] = $cod;
                }
            }
            if ($colsAuto === []) {
                continue;
            }
            if (!isset($porId[$id])) {
                // Já existe cabeçalho manual cobrindo este bimestre — não duplica "I BIMESTRE".
                if (!empty($bimsCobertos[$bimAuto])) {
                    continue;
                }
                $atuais[] = [
                    'id' => $id,
                    'nome' => trim((string) ($linhaAuto['nome'] ?? $id)),
                    'colunas' => $colsAuto,
                ];
                $porId[$id] = count($atuais) - 1;
                $bimsCobertos[$bimAuto] = true;
                continue;
            }
            $idx = $porId[$id];
            $cols = [];
            foreach ((array) ($atuais[$idx]['colunas'] ?? []) as $cod) {
                $cod = strtolower(trim((string) $cod));
                if ($cod !== '' && !in_array($cod, $cols, true)) {
                    $cols[] = $cod;
                }
            }
            foreach ($colsAuto as $cod) {
                if (!in_array($cod, $cols, true)) {
                    $cols[] = $cod;
                }
            }
            $atuais[$idx]['colunas'] = $cols;
        }

        return $this->normalizarLinhasLayout($atuais);
    }

    /**
     * Grava linha_cabecalho só a partir do layout atual (ou limpa).
     *
     * @param list<array<string,mixed>> $componentes
     * @param list<array<string,mixed>> $linhasLayout
     * @return list<array<string,mixed>>
     */
    private function aplicarLinhasLayoutNosComponentes(array $componentes, array $linhasLayout): array
    {
        $porCodigo = [];
        foreach ($this->normalizarLinhasLayout($linhasLayout) as $linha) {
            $nome = trim((string) ($linha['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            foreach ((array) ($linha['colunas'] ?? []) as $cod) {
                $cod = strtolower(trim((string) $cod));
                if ($cod !== '') {
                    $porCodigo[$cod] = function_exists('mb_substr') ? mb_substr($nome, 0, 40) : substr($nome, 0, 40);
                }
            }
        }
        foreach ($componentes as &$c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $cfg = $this->configDoComponente($c);
            if (isset($porCodigo[$cod])) {
                $cfg['linha_cabecalho'] = $porCodigo[$cod];
            } else {
                unset($cfg['linha_cabecalho']);
            }
            $c['config'] = $cfg;
        }
        unset($c);

        return $componentes;
    }

    /**
     * @param mixed $raw
     * @return array<int,int>
     */
    private function normalizarFontesBimestres($raw): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ([1, 2, 3, 4] as $bim) {
            $id = (int) ($raw[$bim] ?? $raw[(string) $bim] ?? 0);
            $out[$bim] = $id > 0 ? $id : 0;
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return array<int,int>
     */
    private function inferirFontesBimestresDeComponentes(array $comps): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $grupoBim = ['b1' => 1, 'b2' => 2, 'b3' => 3, 'b4' => 4];
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'evento_boletim') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            $bim = $grupoBim[$g] ?? 0;
            if ($bim <= 0 && preg_match('/^b([1-4])/', strtolower(trim((string) ($c['codigo'] ?? ''))), $m)) {
                $bim = (int) $m[1];
            }
            if ($bim < 1 || $bim > 4) {
                continue;
            }
            $slug = trim((string) ($cfg['regra_codigo'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $ref = $this->ferramentas->obterRegraPorCodigo($slug);
            if ($ref === null) {
                continue;
            }
            $out[$bim] = (int) ($ref['id'] ?? 0);
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return array<int,int>
     */
    private function inferirFontesFaltasDeComponentes(array $comps): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $grupoBim = ['b1' => 1, 'b2' => 2, 'b3' => 3, 'b4' => 4];
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'faltas_evento') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            if ($g === 'final' || $lt === 'resultado' || $lt === 'rec') {
                continue;
            }
            $bim = $grupoBim[$g] ?? 0;
            if ($bim <= 0 && preg_match('/^b([1-4])/', strtolower(trim((string) ($c['codigo'] ?? ''))), $m)) {
                $bim = (int) $m[1];
            }
            if ($bim < 1 || $bim > 4) {
                continue;
            }
            $out[$bim] = (int) ($cfg['faltas_evento_id'] ?? $c['faltas_evento_id'] ?? 0);
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function componenteBoletim(string $codigo, string $nome, string $source, array $config): array
    {
        $ehFaltas = $this->componenteEhFaltas([
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => $source,
            'layout_type' => (string) ($config['layout_type'] ?? ''),
            'config' => $config,
        ]);
        $escalaMax = $ehFaltas
            ? 999.0
            : $this->normalizarEscalaMaxima($config['escala_max'] ?? ($config['escala_max_padrao'] ?? null), 10.0);

        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => $source,
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'usar_percentual' => 0,
            'escala_max' => $escalaMax,
            'obrigatorio' => 0,
            'config' => $config,
        ];
    }

    /**
     * @param array<string,mixed> $componente
     */
    private function componenteEhFaltas(array $componente): bool
    {
        $source = strtolower(trim((string) ($componente['source_type'] ?? '')));
        if ($source === 'faltas_evento') {
            return true;
        }
        $config = is_array($componente['config'] ?? null) ? $componente['config'] : [];
        $layoutType = strtolower(trim((string) ($config['layout_type'] ?? ($componente['layout_type'] ?? ''))));
        if ($layoutType === 'faltas') {
            return true;
        }
        $codigo = strtolower(trim((string) ($componente['codigo'] ?? '')));
        if ((bool) preg_match('/(^|_)fal(tas)?$/', $codigo)) {
            return true;
        }

        return strtoupper($this->siglaTituloColuna($codigo, (string) ($componente['nome'] ?? ''))) === 'FAL';
    }

    /**
     * Coluna da média final no evento de Notas (código de componente, não expressão).
     *
     * @param array<string,mixed> $ref
     */
    private function codigoColunaMediaDaRegra(array $ref): string
    {
        $codigos = [];
        $primeiroCalculado = '';
        foreach ((array) ($ref['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $codigos[$cod] = (string) ($c['codigo'] ?? $cod);
            if ($primeiroCalculado === '' && (string) ($c['source_type'] ?? '') === 'calculado') {
                $primeiroCalculado = (string) ($c['codigo'] ?? $cod);
            }
        }
        $formula = strtolower(trim((string) ($ref['formula_final'] ?? '')));
        if ($formula !== '' && isset($codigos[$formula])) {
            return $codigos[$formula];
        }
        foreach (['media_final', 'media_bim', 'media'] as $pref) {
            if (isset($codigos[$pref])) {
                return $codigos[$pref];
            }
        }

        return $primeiroCalculado;
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<string> $erros
     * @return array<string,mixed>
     */
    private function rascunhoAPartirDeFontesBoletim(array $estado, array &$erros): array
    {
        if ($this->ehModeloSantaMaria($estado)) {
            return $this->rascunhoSantaMariaAPartirDeFontes($estado, $erros);
        }

        $ano = (int) ($estado['ano_letivo'] ?? date('Y'));
        $nome = trim((string) ($estado['nome'] ?? ''));
        if ($nome === '') {
            $nome = sprintf('Boletim %d', $ano);
        }
        $fontes = $this->normalizarFontesBimestres($estado['fontes_bimestres'] ?? []);
        $fontesFaltas = $this->normalizarFontesBimestres($estado['fontes_faltas'] ?? []);
        $selfId = (int) ($estado['regra_id'] ?? 0);
        $componentes = [];
        $codigosMedia = [];
        $codigosFaltas = [];
        $rotulos = [1 => '1º bim', 2 => '2º bim', 3 => '3º bim', 4 => '4º bim'];

        foreach ([1, 2, 3, 4] as $bim) {
            $idFonte = (int) ($fontes[$bim] ?? 0);
            $idFaltas = (int) ($fontesFaltas[$bim] ?? 0);
            if ($idFonte <= 0 && $idFaltas <= 0) {
                continue;
            }
            $grupo = 'b' . $bim;

            if ($idFonte > 0) {
                if ($selfId > 0 && $idFonte === $selfId) {
                    $erros[] = 'O ' . $rotulos[$bim] . ' não pode apontar para este mesmo evento.';
                } else {
                    $ref = $this->ferramentas->obterRegra($idFonte);
                    if ($ref === null) {
                        $erros[] = 'Não encontrei o evento de Notas do ' . $rotulos[$bim] . '.';
                    } elseif (strtolower(trim((string) ($ref['exibir_em'] ?? ''))) !== 'notas') {
                        $erros[] = 'O evento “' . trim((string) ($ref['nome'] ?? '')) . '” não é de Notas. No boletim só entram médias finais de eventos com Exibir em: \Notas.';
                    } elseif ($this->normalizarFinalidade($ref['finalidade'] ?? 'oficial')
                        !== $this->normalizarFinalidade($estado['finalidade'] ?? 'oficial')
                    ) {
                        $tipoEsperado = $this->normalizarFinalidade($estado['finalidade'] ?? 'oficial') === 'complementar'
                            ? 'Notas extra (curso complementar)'
                            : 'Notas da série';
                        $erros[] = 'O evento “' . trim((string) ($ref['nome'] ?? '')) . '” não é ' . $tipoEsperado . '. O boletim extra só puxa Notas extra, e o oficial só puxa Notas da série.';
                    } else {
                        $slug = trim((string) ($ref['codigo'] ?? ''));
                        if ($slug === '') {
                            $erros[] = 'O evento “' . trim((string) ($ref['nome'] ?? '')) . '” não tem código (slug). Abra o evento de Notas e preencha o código.';
                        } else {
                            $codComp = 'b' . $bim . '_media';
                            $codigosMedia[] = $codComp;
                            $componentes[] = $this->componenteBoletim($codComp, 'Média', 'evento_boletim', [
                                'regra_codigo' => $slug,
                                'componente_codigo' => $this->codigoColunaMediaDaRegra($ref),
                                'layout_group' => $grupo,
                                'layout_type' => 'media',
                            ]);
                        }
                    }
                }
            }

            $codFaltas = 'b' . $bim . '_faltas';
            if ($idFaltas > 0) {
                $evF = $this->ferramentas->obterFaltasEvento($idFaltas);
                if ($evF === null) {
                    $erros[] = 'Não encontrei o evento de faltas do ' . $rotulos[$bim] . '.';
                } else {
                    $codigosFaltas[] = $codFaltas;
                    $componentes[] = $this->componenteBoletim($codFaltas, 'Faltas', 'faltas_evento', [
                        'faltas_evento_id' => $idFaltas,
                        'layout_group' => $grupo,
                        'layout_type' => 'faltas',
                    ]);
                }
            } elseif ($idFonte > 0) {
                $componentes[] = $this->componenteBoletim($codFaltas, 'Faltas', 'nenhuma', [
                    'layout_group' => $grupo,
                    'layout_type' => 'faltas',
                ]);
            }
        }

        if ($codigosMedia === []) {
            $erros[] = 'Selecione ao menos um evento de Notas (a média final de um bimestre).';
        } else {
            $n = count($codigosMedia);
            $exp = $n === 1
                ? $codigosMedia[0]
                : '(' . implode(' + ', $codigosMedia) . ') / ' . $n;
            $componentes[] = $this->componenteBoletim('media_final', 'Média', 'calculado', [
                'expressao' => $exp,
                'formula_mode' => 'single',
                'layout_group' => 'final',
                'layout_type' => 'media',
            ]);
            $componentes[] = $this->componenteBoletim('rec_final', 'Rec.', 'nenhuma', [
                'layout_group' => 'final',
                'layout_type' => 'rec',
            ]);
            if ($codigosFaltas !== []) {
                $expF = count($codigosFaltas) === 1
                    ? $codigosFaltas[0]
                    : implode(' + ', $codigosFaltas);
                $componentes[] = $this->componenteBoletim('faltas_final', 'Faltas', 'calculado', [
                    'expressao' => $expF,
                    'formula_mode' => 'single',
                    'layout_group' => 'final',
                    'layout_type' => 'faltas',
                ]);
            } else {
                $componentes[] = $this->componenteBoletim('faltas_final', 'Faltas', 'nenhuma', [
                    'layout_group' => 'final',
                    'layout_type' => 'faltas',
                ]);
            }
            $componentes[] = $this->componenteBoletim('resultado', 'Resultado', 'nenhuma', [
                'layout_group' => 'final',
                'layout_type' => 'resultado',
            ]);
        }

        $componentes = $this->mesclarEventosAvaliativosNosComponentes($componentes, $estado, $erros);

        return [
            'modo' => ($estado['modo'] ?? 'criar') === 'editar' && !empty($estado['regra_id']) ? 'editar' : 'criar',
            'regra_id' => !empty($estado['regra_id']) ? (int) $estado['regra_id'] : null,
            'nome' => $nome,
            'codigo' => trim((string) ($estado['codigo'] ?? '')),
            'descricao_curta' => '',
            'formula_final' => $codigosMedia === [] ? '' : 'media_final',
            'exibir_em' => 'boletim',
            'finalidade' => $this->normalizarFinalidade($estado['finalidade'] ?? 'oficial'),
            'ano_letivo' => $ano,
            'bimestre' => null,
            'usar_resultado_aprovacao' => 1,
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'turmas_ids' => $estado['turmas_ids'] ?? [],
            'materias_ids' => $estado['materias_ids'] ?? [],
            'series_ids' => $estado['series_ids'] ?? [],
            'round_mode' => (string) ($estado['round_mode'] ?? 'none'),
            'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? 2),
            'nota_minima_aprovacao' => (float) ($estado['nota_minima_aprovacao'] ?? 6),
            'nota_minima_origem' => (string) ($estado['nota_minima_origem'] ?? 'padrao'),
            'nota_minima_regra_nome' => (string) ($estado['nota_minima_regra_nome'] ?? ''),
            'componentes' => $componentes,
        ];
    }

    /**
     * Quadro do print Santa Maria: PP/PU/CH/PE/MU/FAL por bimestre + REC/MS + MA/PF/MF.
     *
     * @param array<string,mixed> $estado
     * @param list<string> $erros
     * @return array<string,mixed>
     */
    private function rascunhoSantaMariaAPartirDeFontes(array $estado, array &$erros): array
    {
        $ano = (int) ($estado['ano_letivo'] ?? date('Y'));
        $nome = trim((string) ($estado['nome'] ?? ''));
        if ($nome === '') {
            $nome = sprintf('Boletim %d — print Santa Maria', $ano);
        }
        $fontes = $this->normalizarFontesBimestres($estado['fontes_bimestres'] ?? []);
        $fontesFaltas = $this->normalizarFontesBimestres($estado['fontes_faltas'] ?? []);
        // Checkboxes de Demonstrativos → fontes por bimestre.
        foreach ($this->idsDemonstrativos($estado) as $idDemo) {
            $refDemo = $this->ferramentas->obterRegra($idDemo);
            if ($refDemo === null) {
                continue;
            }
            $bDemo = (int) ($refDemo['bimestre'] ?? 0);
            if ($bDemo >= 1 && $bDemo <= 4 && (int) ($fontes[$bDemo] ?? 0) <= 0) {
                $fontes[$bDemo] = $idDemo;
            }
        }
        $estado['fontes_bimestres'] = $fontes;
        $selfId = (int) ($estado['regra_id'] ?? 0);
        $componentes = [];
        $rotulos = [1 => '1º bim', 2 => '2º bim', 3 => '3º bim', 4 => '4º bim'];
        $temFonte = false;
        // Manual SM: MU = ((PP + PU) / 2) + PE — sem half na célula MU; half só em MS/MA/MF.
        $exprUnidade = static function (int $bim): string {
            $pp = 'b' . $bim . '_pp';
            $pu = 'b' . $bim . '_pu';
            $pe = 'b' . $bim . '_pe';

            return '((' . $pp . ' + ' . $pu . ') / 2) + ' . $pe;
        };

        foreach ([1, 2, 3, 4] as $bim) {
            $idFonte = (int) ($fontes[$bim] ?? 0);
            $idFaltas = (int) ($fontesFaltas[$bim] ?? 0);
            $grupo = 'b' . $bim;
            $codPp = 'b' . $bim . '_pp';
            $codPu = 'b' . $bim . '_pu';
            $codCh = 'b' . $bim . '_ch';
            $codPe = 'b' . $bim . '_pe';
            $codMu = 'b' . $bim . '_mu';
            $codFal = 'b' . $bim . '_fal';

            $colsBimAdicionadas = false;
            if ($idFonte > 0) {
                $temFonte = true;
                if ($selfId > 0 && $idFonte === $selfId) {
                    $erros[] = 'O ' . $rotulos[$bim] . ' não pode apontar para este mesmo evento.';
                } else {
                    $ref = $this->ferramentas->obterRegra($idFonte);
                    if ($ref === null) {
                        $erros[] = 'Não encontrei o Demonstrativo do ' . $rotulos[$bim] . '.';
                    } elseif (strtolower(trim((string) ($ref['exibir_em'] ?? ''))) !== 'notas') {
                        $erros[] = 'O evento “' . trim((string) ($ref['nome'] ?? '')) . '” não é Demonstrativo de Notas.';
                    } else {
                        $slug = trim((string) ($ref['codigo'] ?? ''));
                        if ($slug === '') {
                            $erros[] = 'O Demonstrativo “' . trim((string) ($ref['nome'] ?? '')) . '” não tem código (slug).';
                        } else {
                            foreach ([
                                [$codPp, 'PP', 'PP'],
                                [$codPu, 'PU', 'PU'],
                                [$codCh, 'CH', 'CH'],
                                [$codPe, 'PE', 'PE'],
                            ] as [$codComp, $nomeCol, $busca]) {
                                $colFonte = $this->codigoColunaPorAliases($ref, [$busca]);
                                $componentes[] = $colFonte !== ''
                                    ? $this->componenteBoletim($codComp, $nomeCol, 'evento_boletim', [
                                        'regra_codigo' => $slug,
                                        'componente_codigo' => $colFonte,
                                        'layout_group' => $grupo,
                                        'layout_type' => 'media',
                                    ])
                                    : $this->componenteBoletim($codComp, $nomeCol, 'nenhuma', [
                                        'layout_group' => $grupo,
                                        'layout_type' => 'media',
                                    ]);
                            }
                            $colsBimAdicionadas = true;
                        }
                    }
                }
            }
            if (!$colsBimAdicionadas) {
                foreach ([[$codPp, 'PP'], [$codPu, 'PU'], [$codCh, 'CH'], [$codPe, 'PE']] as [$codComp, $nomeCol]) {
                    $componentes[] = $this->componenteBoletim($codComp, $nomeCol, 'nenhuma', [
                        'layout_group' => $grupo,
                        'layout_type' => 'media',
                    ]);
                }
            }

            // MU no print oficial (PP/PU/CH/PE/MU/FAL) — half off para não virar 7,9→8,0.
            $componentes[] = $this->componenteBoletim($codMu, 'MU', 'calculado', [
                'expressao' => $exprUnidade($bim),
                'formula_mode' => 'single',
                'layout_group' => $grupo,
                'layout_type' => 'media',
                'round_mode_override' => 'none',
            ]);

            if ($idFaltas > 0) {
                $evF = $this->ferramentas->obterFaltasEvento($idFaltas);
                if ($evF === null) {
                    $erros[] = 'Não encontrei o evento de faltas do ' . $rotulos[$bim] . '.';
                    $componentes[] = $this->componenteBoletim($codFal, 'FAL', 'nenhuma', [
                        'layout_group' => $grupo,
                        'layout_type' => 'faltas',
                    ]);
                } else {
                    $componentes[] = $this->componenteBoletim($codFal, 'FAL', 'faltas_evento', [
                        'faltas_evento_id' => $idFaltas,
                        'layout_group' => $grupo,
                        'layout_type' => 'faltas',
                    ]);
                }
            } else {
                $componentes[] = $this->componenteBoletim($codFal, 'FAL', 'nenhuma', [
                    'layout_group' => $grupo,
                    'layout_type' => 'faltas',
                ]);
            }
        }

        // 1º semestre (após bim 1–2) e 2º semestre (após bim 3–4)
        $componentes[] = $this->componenteBoletim('s1_rec', 'REC 1º', 'nenhuma', [
            'layout_group' => 's1',
            'layout_type' => 'rec',
        ]);
        $componentes[] = $this->componenteBoletim('s1_ms', 'MS 1º', 'calculado', [
            'expressao' => '(b1_mu + b2_mu) / 2',
            'formula_mode' => 'single',
            'layout_group' => 's1',
            'layout_type' => 'media',
        ]);
        $componentes[] = $this->componenteBoletim('s2_rec', 'REC 2º', 'nenhuma', [
            'layout_group' => 's2',
            'layout_type' => 'rec',
        ]);
        $componentes[] = $this->componenteBoletim('s2_ms', 'MS 2º', 'calculado', [
            'expressao' => '(b3_mu + b4_mu) / 2',
            'formula_mode' => 'single',
            'layout_group' => 's2',
            'layout_type' => 'media',
        ]);

        $componentes[] = $this->componenteBoletim('ma', 'MA', 'calculado', [
            'expressao' => '(s1_ms + s2_ms) / 2',
            'formula_mode' => 'single',
            'layout_group' => 'final',
            'layout_type' => 'media',
        ]);
        $componentes[] = $this->componenteBoletim('pf', 'PF', 'nenhuma', [
            'layout_group' => 'final',
            'layout_type' => 'media',
        ]);
        // Sem PF lançada, MF = MA; com PF o admin ajusta para (ma + pf) / 2.
        $componentes[] = $this->componenteBoletim('mf', 'MF', 'calculado', [
            'expressao' => 'ma',
            'formula_mode' => 'single',
            'layout_group' => 'final',
            'layout_type' => 'media',
        ]);
        $componentes[] = $this->componenteBoletim('situacao', 'Situação', 'nenhuma', [
            'layout_group' => 'final',
            'layout_type' => 'resultado',
        ]);

        // Critério do print: half nas médias consolidadas (MS/MA/MF); células lançadas intactas.
        $estado['round_mode'] = 'half';
        $estado['decimal_places'] = 1;

        $this->preencherEventosAvaliativosAPartirDoQuadro($estado);
        $componentes = $this->mesclarEventosAvaliativosNosComponentes($componentes, $estado, $erros);
        $estado['linhas_layout'] = $this->garantirMuNasLinhasSantaMaria(
            is_array($estado['linhas_layout'] ?? null) ? $estado['linhas_layout'] : []
        );

        if ($this->idsEventosAvaliativos($estado) !== []) {
            $temFonte = true;
        }
        if (!$temFonte && $this->codigosExplicitosDoQuadro($estado) !== []) {
            $temFonte = true;
        }

        if (!$temFonte) {
            $erros[] = 'Escolha ao menos um Evento avaliativo (por bimestre/trimestre) para montar o boletim.';
        }

        if ($this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) === [] && empty($estado['cabecalhos_manuais'])) {
            $estado['linhas_layout'] = [
                ['id' => 'sm_b1', 'nome' => 'I BIMESTRE', 'colunas' => ['b1_pp', 'b1_pu', 'b1_ch', 'b1_pe', 'b1_mu', 'b1_fal']],
                ['id' => 'sm_b2', 'nome' => 'II BIMESTRE', 'colunas' => ['b2_pp', 'b2_pu', 'b2_ch', 'b2_pe', 'b2_mu', 'b2_fal']],
                ['id' => 'sm_s1', 'nome' => '1º SEMESTRE', 'colunas' => ['s1_rec', 's1_ms']],
                ['id' => 'sm_b3', 'nome' => 'III BIMESTRE', 'colunas' => ['b3_pp', 'b3_pu', 'b3_ch', 'b3_pe', 'b3_mu', 'b3_fal']],
                ['id' => 'sm_b4', 'nome' => 'IV BIMESTRE', 'colunas' => ['b4_pp', 'b4_pu', 'b4_ch', 'b4_pe', 'b4_mu', 'b4_fal']],
                ['id' => 'sm_s2', 'nome' => '2º SEMESTRE', 'colunas' => ['s2_rec', 's2_ms']],
                ['id' => 'sm_fin', 'nome' => 'RESULTADO FINAL', 'colunas' => ['ma', 'pf', 'mf', 'situacao']],
            ];
        }

        return [
            'modo' => ($estado['modo'] ?? 'criar') === 'editar' && !empty($estado['regra_id']) ? 'editar' : 'criar',
            'regra_id' => !empty($estado['regra_id']) ? (int) $estado['regra_id'] : null,
            'nome' => $nome,
            'codigo' => trim((string) ($estado['codigo'] ?? '')),
            'descricao_curta' => '',
            'formula_final' => 'mf',
            'exibir_em' => 'boletim',
            'finalidade' => $this->normalizarFinalidade($estado['finalidade'] ?? 'oficial'),
            'ano_letivo' => $ano,
            'bimestre' => null,
            'usar_resultado_aprovacao' => 1,
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'turmas_ids' => $estado['turmas_ids'] ?? [],
            'materias_ids' => $estado['materias_ids'] ?? [],
            'series_ids' => $estado['series_ids'] ?? [],
            'round_mode' => (string) ($estado['round_mode'] ?? 'none'),
            'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? 2),
            'nota_minima_aprovacao' => (float) ($estado['nota_minima_aprovacao'] ?? 6),
            'nota_minima_origem' => (string) ($estado['nota_minima_origem'] ?? 'padrao'),
            'nota_minima_regra_nome' => (string) ($estado['nota_minima_regra_nome'] ?? ''),
            'componentes' => $componentes,
            'linhas_layout' => $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []),
            'eventos_avaliativos_ids' => $this->idsEventosAvaliativos($estado),
            'eventos_avaliativos_subs' => $this->normalizarEventosAvaliativosSubs($estado['eventos_avaliativos_subs'] ?? []),
        ];
    }

    /**
     * @param array<string,mixed> $ref
     * @param list<string> $aliases
     */
    private function codigoColunaPorAliases(array $ref, array $aliases): string
    {
        $wanted = [];
        foreach ($aliases as $a) {
            $wanted[strtoupper(trim((string) $a))] = true;
        }
        foreach ((array) ($ref['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = trim((string) ($c['codigo'] ?? ''));
            if ($cod === '') {
                continue;
            }
            if (isset($wanted[strtoupper($cod)])) {
                return $cod;
            }
        }
        return '';
    }

    /**
     * @param mixed $raw
     * @return list<array{type:string,value:string,label:string}>
     */
    private function normalizarFormulaTokens($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $t) {
            if (!is_array($t)) {
                continue;
            }
            $type = strtolower(trim((string) ($t['type'] ?? '')));
            $value = trim((string) ($t['value'] ?? ''));
            if ($type === 'peca') {
                $value = strtolower($value);
                if (!(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $value)) {
                    continue;
                }
                $out[] = [
                    'type' => 'peca',
                    'value' => $value,
                    'label' => trim((string) ($t['label'] ?? $value)) ?: $value,
                ];
            } elseif ($type === 'op') {
                if ($value === '<>') {
                    $value = '!=';
                }
                if (!in_array($value, ['+', '-', '*', '/', '(', ')', ',', '|', '>', '<', '>=', '<=', '=', '==', '!='], true)) {
                    continue;
                }
                $simbolo = match ($value) {
                    '*' => '×',
                    '/' => '÷',
                    '-' => '−',
                    '>=' => '≥',
                    '<=' => '≤',
                    '!=' => '≠',
                    default => $value,
                };
                $out[] = ['type' => 'op', 'value' => $value, 'label' => $simbolo];
            } elseif ($type === 'lit') {
                $value = strtolower(str_replace(['ã', 'á'], ['a', 'a'], $value));
                $mapLit = [
                    'true' => 'verdadeiro',
                    'verdadeiro' => 'verdadeiro',
                    'sim' => 'verdadeiro',
                    'false' => 'falso',
                    'falso' => 'falso',
                    'nao' => 'falso',
                ];
                if (!isset($mapLit[$value])) {
                    continue;
                }
                $canon = $mapLit[$value];
                $out[] = ['type' => 'lit', 'value' => $canon, 'label' => $canon];
            } elseif ($type === 'num') {
                if (!(bool) preg_match('/^\d+(?:\.\d+)?$/', $value)) {
                    continue;
                }
                $out[] = ['type' => 'num', 'value' => $value, 'label' => $value];
            } elseif ($type === 'fn') {
                $value = strtolower($value);
                if ($value === 'substituta') {
                    $value = 'chamada';
                }
                if ($value === 'if') {
                    $value = 'se';
                }
                if ($value === 'media_ponderada_substituta') {
                    $value = 'media_ponderada';
                }
                if (!in_array($value, ['max', 'min', 'chamada', 'composicao', 'media_ponderada', 'media_lancado', 'se'], true)) {
                    continue;
                }
                $out[] = [
                    'type' => 'fn',
                    'value' => $value,
                    'label' => $value === 'min'
                        ? 'menor ('
                        : ($value === 'chamada'
                            ? 'nota substituta ('
                            : ($value === 'composicao'
                                ? 'média c/ rec ('
                                : ($value === 'media_ponderada'
                                    ? 'média ponderada ('
                                    : ($value === 'media_lancado'
                                        ? 'média ('
                                        : ($value === 'se' ? 'se (' : 'maior ('))))),
                ];
            }
            if (count($out) >= 500) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<string,list<array{type:string,value:string,label:string}>>
     */
    private function normalizarFormulasBlocos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $tokens) {
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if ($codigo === 'media_sem') {
                continue;
            }
            $norm = $this->normalizarFormulaTokens($tokens);
            if ($norm === []) {
                continue;
            }
            $out[$codigo] = $norm;
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    /**
     * Exceções de fórmula por matéria em cada bloco calculado.
     *
     * @param mixed $raw
     * @return array<string,array<int,list<array{type:string,value:string,label:string}>>|\stdClass>
     */
    private function normalizarFormulasMateriasBlocos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $porMateria) {
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || $codigo === 'media_sem' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if ($porMateria instanceof \stdClass) {
                $porMateria = (array) $porMateria;
            }
            if (!is_array($porMateria)) {
                continue;
            }
            $mapa = [];
            foreach ($porMateria as $midRaw => $tokens) {
                $mid = (int) $midRaw;
                if ($mid <= 0) {
                    continue;
                }
                $norm = $this->normalizarFormulaTokens($tokens);
                if ($norm === []) {
                    continue;
                }
                $mapa[$mid] = $norm;
                if (count($mapa) >= 40) {
                    break;
                }
            }
            if ($mapa === []) {
                $out[$codigo] = new \stdClass();
            } else {
                $out[$codigo] = $mapa;
            }
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<string,string>
     */
    private function normalizarNomesBlocos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $nome) {
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if ($codigo === '_semanal' || (bool) preg_match('/^s[1-8]$/', $codigo)) {
                continue;
            }
            $nome = trim(mb_substr((string) $nome, 0, 60));
            if ($nome === '') {
                continue;
            }
            $out[$codigo] = $nome;
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /**
     * Valor máximo (escala) por coluna calculada. Ex.: \Peso = Resultado Final × 2 → escala 20.
     *
     * @param mixed $raw
     * @return array<string,float>
     */
    private function normalizarEscalasBlocos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $escala) {
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if ($codigo === '_semanal' || (bool) preg_match('/^s[1-8]$/', $codigo)) {
                continue;
            }
            $valor = round((float) $escala, 2);
            if ($valor < 0.01) {
                continue;
            }
            if ($valor > 999) {
                $valor = 999.0;
            }
            $out[$codigo] = $valor;
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<string,int>
     */
    private function normalizarMediaCicloBlocos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $flag) {
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if (!empty($flag)) {
                $out[$codigo] = 1;
            }
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<string,string>
     */
    private function normalizarMediaCicloDestinos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        $ok = ['b1', 'b2', 'b3', 'b4', 's1', 's2', 'final'];
        foreach ($raw as $codigo => $destino) {
            $codigo = strtolower(trim((string) $codigo));
            $destino = strtolower(trim((string) $destino));
            if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if (!in_array($destino, $ok, true)) {
                continue;
            }
            $out[$codigo] = $destino;
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function destinoMediaCicloDoBloco(array $estado, string $codigo): string
    {
        $codigo = strtolower(trim($codigo));
        if ($codigo === '') {
            return '';
        }
        $mapa = is_array($estado['media_ciclo_destinos'] ?? null) ? $estado['media_ciclo_destinos'] : [];
        if (!empty($mapa[$codigo]) && is_string($mapa[$codigo])) {
            return strtolower(trim($mapa[$codigo]));
        }
        foreach ($mapa as $cod => $dest) {
            if (strtolower(trim((string) $cod)) === $codigo) {
                return strtolower(trim((string) $dest));
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function blocoEhMediaCiclo(array $estado, string $codigo): bool
    {
        $codigo = strtolower(trim($codigo));
        if ($codigo === '') {
            return false;
        }
        $mapa = is_array($estado['media_ciclo_blocos'] ?? null) ? $estado['media_ciclo_blocos'] : [];
        if (!empty($mapa[$codigo])) {
            return true;
        }
        foreach ($mapa as $cod => $flag) {
            if (!empty($flag) && strtolower(trim((string) $cod)) === $codigo) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function escalaDoBloco(array $estado, string $codigo): float
    {
        $codigo = strtolower(trim($codigo));
        $mapa = is_array($estado['escalas_blocos'] ?? null) ? $estado['escalas_blocos'] : [];
        if ($codigo !== '' && isset($mapa[$codigo])) {
            $v = round((float) $mapa[$codigo], 2);
            if ($v >= 0.01) {
                return min(999.0, $v);
            }
        }
        if (isset($estado['escala_max_padrao']) && $estado['escala_max_padrao'] !== '') {
            $padraoEstado = round((float) $estado['escala_max_padrao'], 2);
            if ($padraoEstado >= 0.01) {
                return min(999.0, $padraoEstado);
            }
        }

        return 10.0;
    }

    /**
     * Códigos cujo título o usuário apagou nesta edição.
     * Não podem ser reidratados a partir do nome antigo do componente.
     *
     * @param mixed $raw
     * @return array<string,true>
     */
    private function normalizarNomesLimpos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $marcado) {
            if (!$marcado) {
                continue;
            }
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || $codigo === '_semanal' || (bool) preg_match('/^s[1-8]$/', $codigo)) {
                continue;
            }
            if (!(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            $out[$codigo] = true;
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    private function colunaTituloFoiLimpo(array $estado, string $cod): bool
    {
        $limpos = is_array($estado['nomes_limpos'] ?? null) ? $estado['nomes_limpos'] : [];
        $cod = strtolower(trim($cod));
        if ($cod !== '' && !empty($limpos[$cod])) {
            return true;
        }
        $aliases = [
            'media_sem' => 'semanal',
            'semanal' => 'media_sem',
            'prova_bim' => 'bimestral',
            'bimestral' => 'prova_bim',
            'rec' => 'recuperacao',
            'recuperacao' => 'rec',
            'trab' => 'trabalho',
            'trabalho' => 'trab',
            'part' => 'participacao',
            'participacao' => 'part',
        ];
        $outro = $aliases[$cod] ?? '';

        return $outro !== '' && !empty($limpos[$outro]);
    }

    private function nomePadraoColuna(string $cod): string
    {
        return match ($cod) {
            'media_bim' => 'Média Bim',
            'media_final' => 'Resultado Final',
            'media_sem' => 'Média Sem',
            default => $cod,
        };
    }

    /**
     * @param list<array{type:string,value:string,label?:string}> $tokens
     * @param array<string,array{codigo:string,papel?:string,nome?:string}> $mapaCodigo
     * @param list<string> $pecasPermitidas
     */
    private function compilarTokensParaExpressao(array $tokens, array $mapaCodigo, array $pecasPermitidas = []): string
    {
        $parts = [];
        $temColuna = false;
        $temNumero = false;
        $parens = 0;
        foreach ($tokens as $t) {
            if (!is_array($t)) {
                continue;
            }
            $type = (string) ($t['type'] ?? '');
            $v = (string) ($t['value'] ?? '');
            if ($type === 'peca') {
                $codigo = $this->codigoColunaDoTokenFormula($v, $mapaCodigo);
                if ($codigo === '') {
                    return '';
                }
                $parts[] = $codigo;
                $temColuna = true;
            } elseif ($type === 'fn') {
                $v = strtolower($v);
                if ($v === 'substituta') {
                    $v = 'chamada';
                }
                if ($v === 'if') {
                    $v = 'se';
                }
                if ($v === 'media_ponderada_substituta') {
                    $v = 'media_ponderada';
                }
                if (!in_array($v, ['max', 'min', 'chamada', 'composicao', 'media_ponderada', 'media_lancado', 'se'], true)) {
                    return '';
                }
                $parts[] = $v . '(';
                $parens++;
            } elseif ($type === 'op') {
                if ($v === '<>') {
                    $v = '!=';
                }
                if (!in_array($v, ['+', '-', '*', '/', '(', ')', ',', '|', '>', '<', '>=', '<=', '=', '==', '!=', '<>'], true)) {
                    return '';
                }
                if ($v === '(') {
                    $parens++;
                } elseif ($v === ')') {
                    $parens--;
                    if ($parens < 0) {
                        return '';
                    }
                }
                $parts[] = $v;
            } elseif ($type === 'lit') {
                $baixoLit = strtolower(str_replace(['ã', 'á'], ['a', 'a'], $v));
                $litsOk = [
                    'true' => 'verdadeiro',
                    'verdadeiro' => 'verdadeiro',
                    'sim' => 'verdadeiro',
                    'false' => 'falso',
                    'falso' => 'falso',
                    'nao' => 'falso',
                ];
                if (!isset($litsOk[$baixoLit])) {
                    return '';
                }
                $parts[] = $litsOk[$baixoLit];
            } elseif ($type === 'num') {
                if (!(bool) preg_match('/^\d+(?:\.\d+)?$/', $v)) {
                    return '';
                }
                $parts[] = $v;
                $temNumero = true;
            }
        }
        if ((!$temColuna && !$temNumero) || $parts === [] || $parens !== 0) {
            return '';
        }
        $primeiro = (string) $parts[0];
        $ultimo = (string) $parts[count($parts) - 1];
        $opsBinarios = ['+', '*', '/', ',', '|', '>', '<', '>=', '<=', '=', '==', '!=', '<>'];
        if (in_array($primeiro, array_merge($opsBinarios, [')']), true)) {
            return '';
        }
        if (in_array($ultimo, array_merge($opsBinarios, ['+', '-', '*', '/', ',', '|', '(']), true) || str_ends_with($ultimo, '(')) {
            return '';
        }
        $exp = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
        $exp = str_replace(['( ', ' )', ' ,', ' |'], ['(', ')', ',', '|'], $exp);
        $exp = preg_replace('/\b(max|min|chamada|substituta|composicao|media_ponderada|media_lancado|se|if)\(\s+/', '$1(', $exp) ?? $exp;
        $exp = preg_replace('/\bsubstituta\s*\(/i', 'chamada(', $exp) ?? $exp;
        $exp = preg_replace('/\bif\s*\(/i', 'se(', $exp) ?? $exp;
        $exp = trim($exp);
        // Comparações e literais (verdadeiro/falso) entram na condição do se().
        if ($exp === '' || !(bool) preg_match('/^[a-z0-9_+\-*\/(),|.<>=!\s]+$/i', $exp)) {
            return '';
        }
        if ((str_contains($exp, 'max(') || str_contains($exp, 'min(') || str_contains($exp, 'chamada(') || str_contains($exp, 'composicao(') || str_contains($exp, 'media_ponderada(') || str_contains($exp, 'se(')) && !str_contains($exp, ',')) {
            return '';
        }
        return $this->envolverSomaAntesDeDivisaoNaExpressao($exp);
    }

    /**
     * Coluna citada na fórmula: código do quadro, apelido (recuperacao → rec) ou o próprio identificador.
     *
     * @param array<string,array{codigo?:string,papel?:string,nome?:string}> $mapa
     */
    private function codigoColunaDoTokenFormula(string $token, array $mapa): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        $baixo = strtolower($token);
        foreach ($mapa as $chave => $info) {
            if (!is_array($info)) {
                continue;
            }
            if (strtolower(trim((string) $chave)) !== $baixo) {
                continue;
            }
            $codigo = strtolower(trim((string) ($info['codigo'] ?? '')));
            if ($codigo !== '') {
                return $codigo;
            }
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token)) {
            return $baixo;
        }

        return '';
    }

    /**
     * a + b + c / 3 vira (a + b + c) / 3 — senão a divisão pega só o último termo.
     *
     * @param list<array{type:string,value:string,label?:string}> $tokens
     * @return list<array{type:string,value:string,label?:string}>
     */
    private function envolverSomaAntesDeDivisao(array $tokens): array
    {
        $tokens = array_values($tokens);
        $n = count($tokens);
        if ($n < 5) {
            return $tokens;
        }
        $ultimo = $tokens[$n - 1];
        $div = $tokens[$n - 2];
        if (($ultimo['type'] ?? '') !== 'num' || ($div['type'] ?? '') !== 'op') {
            return $tokens;
        }
        $op = (string) ($div['value'] ?? '');
        if ($op === '+') {
            $tokens[$n - 2] = ['type' => 'op', 'value' => '/', 'label' => '÷'];
            $div = $tokens[$n - 2];
            $op = '/';
        }
        if ($op !== '/') {
            return $tokens;
        }
        $left = array_slice($tokens, 0, $n - 2);
        if ($this->tokensJaEnvolvidosPorParenteses($left) || !$this->tokensSaoSomaSimples($left)) {
            return $tokens;
        }

        return array_merge(
            [['type' => 'op', 'value' => '(', 'label' => '(']],
            $left,
            [['type' => 'op', 'value' => ')', 'label' => ')']],
            [$div, $ultimo]
        );
    }

    /**
     * @param list<array{type:string,value:string,label?:string}> $left
     */
    private function tokensJaEnvolvidosPorParenteses(array $left): bool
    {
        $len = count($left);
        if ($len < 3) {
            return false;
        }
        if (($left[0]['type'] ?? '') !== 'op' || (string) ($left[0]['value'] ?? '') !== '(') {
            return false;
        }
        if (($left[$len - 1]['type'] ?? '') !== 'op' || (string) ($left[$len - 1]['value'] ?? '') !== ')') {
            return false;
        }
        $depth = 0;
        foreach ($left as $i => $t) {
            if (($t['type'] ?? '') !== 'op') {
                continue;
            }
            $v = (string) ($t['value'] ?? '');
            if ($v === '(') {
                $depth++;
            } elseif ($v === ')') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
                if ($depth === 0 && $i < $len - 1) {
                    return false;
                }
            }
        }
        return $depth === 0;
    }

    /**
     * @param list<array{type:string,value:string,label?:string}> $left
     */
    private function tokensSaoSomaSimples(array $left): bool
    {
        $depth = 0;
        $somas = 0;
        $esperandoTermo = true;
        foreach ($left as $t) {
            $type = (string) ($t['type'] ?? '');
            $v = (string) ($t['value'] ?? '');
            if ($type === 'fn') {
                if ($depth === 0 && !$esperandoTermo) {
                    return false;
                }
                $depth++;
                $esperandoTermo = false;
                continue;
            }
            if ($type === 'op' && $v === '(') {
                if ($depth === 0 && !$esperandoTermo) {
                    return false;
                }
                $depth++;
                $esperandoTermo = true;
                continue;
            }
            if ($type === 'op' && $v === ')') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
                $esperandoTermo = false;
                continue;
            }
            if ($depth > 0) {
                continue;
            }
            if ($type === 'op' && $v === '+') {
                if ($esperandoTermo) {
                    return false;
                }
                $somas++;
                $esperandoTermo = true;
                continue;
            }
            if ($type === 'op') {
                return false;
            }
            if ($type === 'peca' || $type === 'num') {
                if (!$esperandoTermo) {
                    return false;
                }
                $esperandoTermo = false;
                continue;
            }
            return false;
        }

        return !$esperandoTermo && $somas >= 1 && $depth === 0;
    }

    private function envolverSomaAntesDeDivisaoNaExpressao(string $exp): string
    {
        $exp = trim($exp);
        if ($exp !== '' && preg_match('/^([a-z][a-z0-9_]*(?:\s*\+\s*[a-z][a-z0-9_]*)+)\s*\+\s*(\d+(?:\.\d+)?)$/i', $exp, $maisN)) {
            $exp = trim((string) $maisN[1]) . ' / ' . $maisN[2];
        }
        if ($exp === '' || !preg_match('/^(.*)\/\s*(\d+(?:\.\d+)?)$/', $exp, $m)) {
            return $exp;
        }
        $left = trim((string) $m[1]);
        $div = (string) $m[2];
        if ($left === '' || $this->expressaoJaEnvolvidaPorParenteses($left)) {
            return $exp;
        }
        if (!(bool) preg_match('/^[a-z][a-z0-9_]*(?:\s*\+\s*[a-z][a-z0-9_]*)+$/i', $left)) {
            return $exp;
        }

        return '(' . $left . ') / ' . $div;
    }

    private function expressaoJaEnvolvidaPorParenteses(string $left): bool
    {
        $left = trim($left);
        $len = strlen($left);
        if ($len < 3 || $left[0] !== '(' || $left[$len - 1] !== ')') {
            return false;
        }
        $depth = 0;
        for ($i = 0; $i < $len; $i++) {
            $ch = $left[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
                if ($depth === 0 && $i < $len - 1) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    /**
     * Gera a fórmula a partir dos papéis: média das peças "media", depois
     * cada "so_melhora" (max com média da peça) e cada "substitui" (max com a peça).
     *
     * @param list<array<string,mixed>> $componentes
     * @param array<string,array{codigo:string,papel:string,nome:string}> $mapaCodigo
     * @param list<string> $pecas
     */
    private function montarFormulaPorPapeis(
        array &$componentes,
        array $mapaCodigo,
        array $pecas,
        string $codigoBase = 'media_base',
        string $codigoFinal = 'media_final',
        bool $forcarBlocoBase = false
    ): string {
        $media = [];
        $depois = [];
        $soMelhora = [];
        $substitui = [];
        foreach ($pecas as $p) {
            if (!isset($mapaCodigo[$p])) {
                continue;
            }
            $cod = (string) $mapaCodigo[$p]['codigo'];
            $papel = (string) $mapaCodigo[$p]['papel'];
            if ($papel === 'media') {
                $media[] = $cod;
            } elseif ($papel === 'depois') {
                $depois[] = $cod;
            } elseif ($papel === 'so_melhora') {
                $soMelhora[] = $cod;
            } elseif ($papel === 'substitui') {
                $substitui[] = $cod;
            }
        }

        if ($media === []) {
            return '';
        }

        $expBase = $this->expressaoMedia($media);
        $temDepois = $depois !== [];
        $temExtras = $temDepois || $soMelhora !== [] || $substitui !== [];
        if ($temDepois && !$forcarBlocoBase) {
            $codigoBase = 'media_parcial';
        }
        if (!$temExtras && !$forcarBlocoBase) {
            if (count($media) === 1) {
                return $media[0];
            }
            $componentes[] = $this->blocoCalculado($codigoFinal, 'Média final', $expBase);
            return $codigoFinal;
        }

        $prev = $codigoBase;
        $nomeBase = $forcarBlocoBase ? 'Média Bim' : ($temDepois ? 'Média parcial' : 'Média base');
        $componentes[] = $this->blocoCalculado($prev, $nomeBase, $expBase);
        if (!$temExtras) {
            return $prev;
        }

        if ($temDepois) {
            $expDepois = '(' . $prev . ' + ' . implode(' + ', $depois) . ') / ' . (1 + count($depois));
            $restantesApos = count($soMelhora) + count($substitui);
            $next = $restantesApos === 0 ? $codigoFinal : 'media_etapa';
            $nome = $next === $codigoFinal ? 'Média final' : 'Média com etapa 2';
            $componentes[] = $this->blocoCalculado($next, $nome, $expDepois);
            $prev = $next;
            if ($restantesApos === 0) {
                return $prev;
            }
        }

        $restantes = count($soMelhora) + count($substitui);
        $i = 0;
        foreach ($soMelhora as $cod) {
            $i++;
            $restantes--;
            $next = $restantes === 0 ? $codigoFinal : ('media_m' . $i);
            $nome = $next === $codigoFinal ? 'Média final' : ('Média com ' . $cod);
            $componentes[] = $this->blocoCalculado($next, $nome, 'max(' . $prev . ', (' . $prev . ' + ' . $cod . ') / 2)');
            $prev = $next;
        }
        $j = 0;
        foreach ($substitui as $cod) {
            $j++;
            $restantes--;
            $next = $restantes === 0 ? $codigoFinal : ('media_s' . $j);
            $nome = $next === $codigoFinal ? 'Média final' : ('Média com ' . $cod);
            $componentes[] = $this->blocoCalculado($next, $nome, 'max(' . $prev . ', ' . $cod . ')');
            $prev = $next;
        }

        return $prev;
    }

    /**
     * @param array{tipo_avaliacao_id?:int} $opts
     * @return array{codigo:string,nome:string,source_type:string,filtro_titulo:string,tipo_sugerido:string,tipo_avaliacao_id:int}|null
     */
    private function resolverDefinicaoPeca(string $peca, array $opts = []): ?array
    {
        if ($peca === 'jornada') {
            return [
                'codigo' => 'jornada',
                'nome' => 'Jornada do aluno',
                'source_type' => 'jornadas',
                'filtro_titulo' => '',
                'tipo_sugerido' => '',
                'tipo_avaliacao_id' => 0,
            ];
        }

        $canonicos = [
            'bimestral' => ['codigo' => 'bimestral', 'nome' => 'Prova bimestral', 'filtro_titulo' => 'bimestral', 'tipo_sugerido' => 'Bimestral'],
            'enac' => ['codigo' => 'enac', 'nome' => 'ENAC', 'filtro_titulo' => 'enac', 'tipo_sugerido' => 'ENAC'],
            'semanal' => ['codigo' => 'semanal', 'nome' => 'Prova semanal', 'filtro_titulo' => 'semanal', 'tipo_sugerido' => 'Semanal'],
            'trabalho' => ['codigo' => 'trab', 'nome' => 'Trabalho', 'filtro_titulo' => 'trabalho', 'tipo_sugerido' => 'Trabalho'],
            'participacao' => ['codigo' => 'part', 'nome' => 'Participação', 'filtro_titulo' => 'participacao', 'tipo_sugerido' => 'Participação'],
            'recuperacao' => ['codigo' => 'rec', 'nome' => 'Recuperação', 'filtro_titulo' => 'recuperacao', 'tipo_sugerido' => 'Recuperação'],
        ];

        $tipoId = max(0, (int) ($opts['tipo_avaliacao_id'] ?? 0));
        $meta = null;
        foreach ($this->pecasMeta() as $m) {
            if (!is_array($m)) {
                continue;
            }
            if ((string) ($m['key'] ?? '') === $peca) {
                $meta = $m;
                break;
            }
        }

        if (isset($canonicos[$peca])) {
            $def = $canonicos[$peca];
            $def['source_type'] = 'provas_sistema';
            $def['tipo_avaliacao_id'] = $tipoId;
            if ($meta !== null) {
                $label = trim((string) ($meta['label'] ?? ''));
                if ($label !== '') {
                    $def['nome'] = $label;
                }
                if ((int) ($meta['tipo_avaliacao_id'] ?? 0) > 0) {
                    $def['tipo_avaliacao_id'] = (int) $meta['tipo_avaliacao_id'];
                }
            }
            return $def;
        }

        if ($meta === null && $tipoId <= 0) {
            return null;
        }

        $id = (int) ($meta['tipo_avaliacao_id'] ?? 0);
        if ($id <= 0) {
            $id = $tipoId;
        }
        $nome = $meta !== null ? trim((string) ($meta['label'] ?? '')) : '';
        if ($nome === '') {
            $nome = 'Tipo #' . $id;
        }
        $chaveQuadro = $meta !== null ? trim((string) ($meta['chave_quadro'] ?? '')) : '';
        $codigo = $peca;
        if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
            $codigo = 'tipo_' . $id;
        }

        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => 'provas_sistema',
            'filtro_titulo' => $chaveQuadro !== '' ? strtolower($chaveQuadro) : strtolower($nome),
            'tipo_sugerido' => $nome,
            'tipo_avaliacao_id' => $id,
        ];
    }

    /**
     * @param array{calc_type?:string,materia_unica?:int|bool,usar_percentual?:int|bool,tipo_avaliacao_id?:int,papel?:string} $opts
     * @param array<string,mixed> $estado
     * @return array<string,mixed>|null
     */
    private function definirComponentePeca(string $peca, array $opts = [], array $estado = []): ?array
    {
        $calc = strtolower(trim((string) ($opts['calc_type'] ?? $this->calcTypePadrao($peca))));
        if (!in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
            $calc = $this->calcTypePadrao($peca);
        }
        $materiaUnica = array_key_exists('materia_unica', $estado)
            ? (!empty($estado['materia_unica']) ? 1 : 0)
            : (!empty($opts['materia_unica']) ? 1 : 0);
        $usarPerc = array_key_exists('usar_percentual', $opts)
            ? (!empty($opts['usar_percentual']) ? 1 : 0)
            : $this->usarPercentualPadrao($peca);
        if (!$this->pecaPermiteAcertosQuestoes($peca)) {
            $usarPerc = 0;
        }

        $base = $this->resolverDefinicaoPeca($peca, $opts);
        if ($base === null) {
            return null;
        }

        $dataIni = (string) ($estado['data_inicio'] ?? '');
        $dataFim = (string) ($estado['data_fim'] ?? '');
        $config = ['formula_mode' => 'single', 'expressao' => '', 'papel_wizard' => $this->normalizarPapel($opts['papel'] ?? '', $peca)];
        $blocosIds = [];
        $tipoId = max(0, (int) ($base['tipo_avaliacao_id'] ?? 0));
        if ($tipoId <= 0) {
            $tipoId = max(0, (int) ($opts['tipo_avaliacao_id'] ?? 0));
        }
        $tipoNome = '';
        $filtroTitulo = $base['filtro_titulo'];

        if ($base['source_type'] === 'jornadas') {
            $modo = (string) ($estado['jornada_modo'] ?? 'todas');
            if ($modo === 'selecionadas') {
                $ids = array_values(array_unique(array_filter(
                    array_map('intval', (array) ($estado['jornada_ids'] ?? [])),
                    static fn ($id) => $id > 0
                )));
                if ($ids === []) {
                    return null;
                }
            } else {
                $ids = $this->resolverIdsJornadaPorBimestre($estado);
                if ($ids === []) {
                    return null;
                }
                $config['jornada_bimestres'] = $this->bimestresJornadaDoEstado($estado);
            }
            $config['jornada_ids'] = $ids;
            if ($dataIni !== '') {
                $config['data_ini'] = $dataIni;
            }
            if ($dataFim !== '') {
                $config['data_fim'] = $dataFim;
            }
            $notaModo = (string) ($estado['jornada_nota_modo'] ?? 'linear');
            if ($notaModo === 'faixas') {
                $faixas = $this->normalizarFaixasJornada($estado['jornada_faixas'] ?? null);
                $config['faixas_percentuais'] = $faixas;
                $usarPerc = 0;
            } else {
                $config['faixas_percentuais'] = [];
                $usarPerc = 1;
            }
            $dist = (string) ($estado['jornada_distribuicao_notas'] ?? 'por_materia');
            $config['distribuicao_notas'] = ($dist === 'nota_unica_todas_linhas')
                ? 'nota_unica_todas_linhas'
                : 'por_materia';
            $config = $this->aplicarMateriasJornadaNoConfig($config, $estado);
        } elseif ($base['source_type'] === 'provas_sistema') {
            $tipoRef = $tipoId > 0 ? $tipoId : ($base['tipo_sugerido'] !== '' ? $base['tipo_sugerido'] : $filtroTitulo);
            $bimsPeca = $this->normalizarBimestresLista($opts['bimestres'] ?? []);
            if ($bimsPeca === []) {
                $bimEstado = (int) ($estado['bimestre'] ?? 0);
                if ($bimEstado >= 1 && $bimEstado <= 4) {
                    $bimsPeca = [$bimEstado];
                }
            }
            $manualBlocos = !empty($opts['blocos_ids_manual']);
            $blocosManualIds = $this->normalizarIdsLista($opts['blocos_ids'] ?? []);
            if ($tipoId > 0) {
                $config['tipo_avaliacao_id'] = $tipoId;
            }
            if ($manualBlocos && $tipoId > 0 && $blocosManualIds !== []) {
                $doTipo = $this->ferramentas->resolverBlocosPorTipo(
                    $tipoId,
                    null,
                    null,
                    4000,
                    null,
                    $bimsPeca
                );
                $permitidos = array_fill_keys(array_map('intval', (array) ($doTipo['blocos_ids'] ?? [])), true);
                $blocosManualIds = array_values(array_filter(
                    $blocosManualIds,
                    static fn ($id) => isset($permitidos[(int) $id])
                ));
            }
            $resolvido = $manualBlocos
                ? ['tipo' => null, 'blocos_ids' => $blocosManualIds]
                : $this->ferramentas->resolverBlocosPorTipo(
                    $tipoRef,
                    $dataIni !== '' ? $dataIni : null,
                    $dataFim !== '' ? $dataFim : null,
                    100,
                    null,
                    $bimsPeca
                );
            if ($resolvido['tipo'] !== null) {
                $tipoId = (int) $resolvido['tipo']['id'];
                $tipoNome = (string) $resolvido['tipo']['nome'];
                $config['tipo_avaliacao_id'] = $tipoId;
                $config['tipo_avaliacao_nome'] = $tipoNome;
            }
            if ($bimsPeca !== []) {
                $config['prova_bimestres'] = $bimsPeca;
            }
            if ($manualBlocos) {
                $config['blocos_ids_manual'] = 1;
            }
            $blocosIds = $resolvido['blocos_ids'] ?? [];
            // Com blocos resolvidos, o motor ignora filtro_titulo — ok.
            if ($blocosIds !== []) {
                $filtroTitulo = '';
            }
            $evPeca = (int) ($opts['evento_avaliativo_id'] ?? 0);
            if ($evPeca > 0) {
                $config['evento_avaliativo_id'] = $evPeca;
            }
            $herdado = $this->fechamentoParaPeca($peca, $tipoId);
            if ($herdado !== null) {
                $calc = $herdado['calc_type'];
                $usarPerc = $herdado['usar_percentual'];
            }
        }

        $escalaMax = $this->escalaDoBloco($estado, (string) ($base['codigo'] ?? $peca));
        if ($this->componenteEhFaltas([
            'codigo' => (string) ($base['codigo'] ?? $peca),
            'nome' => (string) ($base['nome'] ?? ''),
            'source_type' => (string) ($base['source_type'] ?? ''),
            'config' => $config,
        ])) {
            $escalaMax = 999.0;
        }

        $evRetorno = (int) ($config['evento_avaliativo_id'] ?? $opts['evento_avaliativo_id'] ?? 0);

        return [
            'codigo' => $base['codigo'],
            'nome' => $base['nome'],
            'source_type' => $base['source_type'],
            'calc_type' => $calc,
            'peso' => 1,
            'filtro_titulo' => $filtroTitulo,
            'blocos_ids' => $blocosIds,
            'materias_ids' => [],
            'materia_unica' => $materiaUnica,
            'materia_unica_modo' => $this->normalizarMateriaUnicaModo($estado['materia_unica_modo'] ?? ($opts['materia_unica_modo'] ?? 'soma')),
            'usar_percentual' => $usarPerc,
            'escala_max' => $escalaMax,
            'obrigatorio' => 0,
            'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
            'tipo_avaliacao_nome' => $tipoNome !== '' ? $tipoNome : null,
            'evento_avaliativo_id' => $evRetorno > 0 ? $evRetorno : null,
            'config' => $config,
        ];
    }

    /** @param list<string> $codigos */
    private function expressaoMedia(array $codigos): string
    {
        $codigos = array_values(array_filter(array_map('strval', $codigos)));
        $n = count($codigos);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $codigos[0];
        }
        return '(' . implode(' + ', $codigos) . ') / ' . $n;
    }

    /** @return array<string,mixed> */
    private function blocoCalculado(string $codigo, string $nome, string $expressao, float $escalaMax = 10.0): array
    {
        $escalaMax = round($escalaMax, 2);
        if ($escalaMax < 0.01) {
            $escalaMax = 10.0;
        }
        if ($escalaMax > 999) {
            $escalaMax = 999.0;
        }

        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => 'calculado',
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'usar_percentual' => 0,
            'escala_max' => $escalaMax,
            'obrigatorio' => 0,
            'config' => [
                'expressao' => $expressao,
                'formula_mode' => 'single',
            ],
        ];
    }

    /**
     * Tabela padrão: 100% concluídas → 10, 90% → 9,5, 80% → 9, …
     *
     * @return list<array{percentual_min:int,nota:float}>
     */
    public static function faixasJornadaPadrao(): array
    {
        return [
            ['percentual_min' => 100, 'nota' => 10.0],
            ['percentual_min' => 90, 'nota' => 9.5],
            ['percentual_min' => 80, 'nota' => 9.0],
            ['percentual_min' => 70, 'nota' => 8.5],
            ['percentual_min' => 60, 'nota' => 8.0],
            ['percentual_min' => 50, 'nota' => 7.5],
            ['percentual_min' => 40, 'nota' => 7.0],
            ['percentual_min' => 30, 'nota' => 6.5],
            ['percentual_min' => 20, 'nota' => 6.0],
            ['percentual_min' => 10, 'nota' => 5.5],
        ];
    }

    /**
     * @param mixed $raw
     * @return list<array{percentual_min:int,nota:float}>
     */
    private function normalizarFaixasJornada($raw): array
    {
        $base = self::faixasJornadaPadrao();
        if (!is_array($raw) || $raw === []) {
            return $base;
        }
        $map = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $p = (int) ($item['percentual_min'] ?? 0);
            if ($p < 0 || $p > 100 || !is_numeric($item['nota'] ?? null)) {
                continue;
            }
            $map[$p] = max(0.0, (float) $item['nota']);
        }
        if ($map === []) {
            return $base;
        }
        foreach ($base as $i => $faixa) {
            $p = (int) $faixa['percentual_min'];
            if (isset($map[$p])) {
                $base[$i]['nota'] = $map[$p];
            }
        }
        foreach ($map as $p => $nota) {
            $existe = false;
            foreach ($base as $faixa) {
                if ((int) $faixa['percentual_min'] === $p) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $base[] = ['percentual_min' => $p, 'nota' => $nota];
            }
        }
        usort($base, static function (array $a, array $b): int {
            return (int) $b['percentual_min'] <=> (int) $a['percentual_min'];
        });
        return $base;
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<array<string,mixed>> $comps
     */
    /**
     * O bimestre do evento salvo volta marcado nas peças. Sem isso, o 3º bimestre
     * abre com a caixa desmarcada mesmo sendo a configuração gravada.
     *
     * @param array<string,mixed> $estado
     */
    private function marcarBimestreSalvoNasPecas(array &$estado): void
    {
        $bim = (int) ($estado['bimestre'] ?? 0);
        if ($bim < 1 || $bim > 4) {
            return;
        }
        if (!is_array($estado['pecas_opcoes'] ?? null)) {
            $estado['pecas_opcoes'] = [];
        }
        foreach ($estado['pecas_opcoes'] as $key => $opts) {
            if (!is_array($opts)) {
                continue;
            }
            $bims = $this->normalizarBimestresLista($opts['bimestres'] ?? []);
            if ($bims === []) {
                $estado['pecas_opcoes'][$key]['bimestres'] = [$bim];
            }
        }
        $jornadaBims = $this->normalizarBimestresLista($estado['jornada_bimestres'] ?? []);
        if ($jornadaBims === [] && in_array('jornada', (array) ($estado['pecas'] ?? []), true)) {
            $estado['jornada_bimestres'] = [$bim];
            if (($estado['jornada_modo'] ?? '') !== 'selecionadas') {
                $estado['jornada_modo'] = 'bimestre';
            }
        }
    }

    private function aplicarJornadaDoFormulario(array &$estado, array $comps): void
    {
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'jornadas') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $ids = array_values(array_filter(array_map('intval', (array) ($cfg['jornada_ids'] ?? []))));
            $bims = [];
            foreach ((array) ($cfg['jornada_bimestres'] ?? []) as $b) {
                $b = (int) $b;
                if ($b >= 1 && $b <= 4) {
                    $bims[] = $b;
                }
            }
            $bims = array_values(array_unique($bims));
            if ($bims !== []) {
                $estado['jornada_modo'] = 'bimestre';
                $estado['jornada_bimestres'] = $bims;
                $estado['jornada_ids'] = $ids;
            } elseif ($ids !== []) {
                $estado['jornada_modo'] = 'selecionadas';
                $estado['jornada_ids'] = $ids;
            }
            $faixas = is_array($cfg['faixas_percentuais'] ?? null) ? $cfg['faixas_percentuais'] : [];
            if ($faixas !== []) {
                $estado['jornada_nota_modo'] = 'faixas';
                $estado['jornada_faixas'] = $this->normalizarFaixasJornada($faixas);
            } elseif (!empty($c['usar_percentual'])) {
                $estado['jornada_nota_modo'] = 'linear';
            }
            $dist = strtolower(trim((string) ($cfg['distribuicao_notas'] ?? 'por_materia')));
            $estado['jornada_distribuicao_notas'] = ($dist === 'nota_unica_todas_linhas')
                ? 'nota_unica_todas_linhas'
                : 'por_materia';
            if (array_key_exists('nota_unica_incluir_materias', $cfg) && is_array($cfg['nota_unica_incluir_materias'])) {
                $incluir = [];
                foreach ($cfg['nota_unica_incluir_materias'] as $midInc) {
                    $midInc = (int) $midInc;
                    if ($midInc > 0) {
                        $incluir[] = $midInc;
                    }
                }
                $estado['jornada_materias_ids'] = array_values(array_unique($incluir));
                $estado['jornada_materias_tocada'] = true;
            } else {
                $omitir = [];
                foreach ((array) ($cfg['nota_unica_omitir_materias'] ?? []) as $midOm) {
                    $midOm = (int) $midOm;
                    if ($midOm > 0) {
                        $omitir[] = $midOm;
                    }
                }
                $estado['jornada_materias_omitir'] = array_values(array_unique($omitir));
            }
            return;
        }
    }

    /**
     * Espelha no componente jornadas as opções da peça (média única, faixas, IDs).
     * Necessário quando o rascunho preservado da edição ainda tem a config antiga.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarConfigJornadaDoEstadoNoRascunho(array $rascunho, array $estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        if ($comps === []) {
            return $rascunho;
        }
        $dist = ((string) ($estado['jornada_distribuicao_notas'] ?? '')) === 'nota_unica_todas_linhas'
            ? 'nota_unica_todas_linhas'
            : 'por_materia';
        $notaModo = (string) ($estado['jornada_nota_modo'] ?? 'linear');
        $faixas = $notaModo === 'faixas'
            ? $this->normalizarFaixasJornada($estado['jornada_faixas'] ?? null)
            : [];
        $modo = (string) ($estado['jornada_modo'] ?? 'bimestre');
        $idsEstado = [];
        if ($modo === 'selecionadas') {
            $idsEstado = array_values(array_unique(array_filter(
                array_map('intval', (array) ($estado['jornada_ids'] ?? [])),
                static fn ($id) => $id > 0
            )));
        } else {
            $idsEstado = $this->resolverIdsJornadaPorBimestre($estado);
        }
        $bimsEstado = $this->bimestresJornadaDoEstado($estado);
        $alterado = false;
        foreach ($comps as $i => $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'jornadas') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if (is_string($c['config_json'] ?? null) && $c['config_json'] !== '') {
                $decoded = json_decode((string) $c['config_json'], true);
                if (is_array($decoded)) {
                    $cfg = array_merge($decoded, $cfg);
                }
            }
            $cfg['distribuicao_notas'] = $dist;
            if ($notaModo === 'faixas') {
                $cfg['faixas_percentuais'] = $faixas;
                $c['usar_percentual'] = 0;
            } else {
                $cfg['faixas_percentuais'] = [];
                $c['usar_percentual'] = 1;
            }
            $cfg['jornada_ids'] = $idsEstado;
            if ($modo === 'selecionadas') {
                unset($cfg['jornada_bimestres']);
            } elseif ($bimsEstado !== []) {
                $cfg['jornada_bimestres'] = $bimsEstado;
            }
            $cfg = $this->aplicarMateriasJornadaNoConfig($cfg, $estado);
            $c['config'] = $cfg;
            $c['config_json'] = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $comps[$i] = $c;
            $alterado = true;
        }
        if ($alterado) {
            $rascunho['componentes'] = $comps;
        }
        return $rascunho;
    }

    /**
     * Matérias marcadas na média única. Null = o usuário ainda não escolheu.
     *
     * @param array<string,mixed> $estado
     * @return list<int>|null
     */
    private function idsMateriasJornadaDoEstado(array $estado): ?array
    {
        if (empty($estado['jornada_materias_tocada'])) {
            return null;
        }
        if (((string) ($estado['jornada_distribuicao_notas'] ?? '')) !== 'nota_unica_todas_linhas') {
            return null;
        }
        $ids = [];
        foreach ((array) ($estado['jornada_materias_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarMateriasJornadaNoConfig(array $cfg, array $estado): array
    {
        $ids = $this->idsMateriasJornadaDoEstado($estado);
        if ($ids === null) {
            return $cfg;
        }
        $cfg['nota_unica_incluir_materias'] = $ids;
        unset($cfg['nota_unica_omitir_materias']);

        return $cfg;
    }

    /**
     * @param array<string,mixed> $comp
     */
    private function resumoEscopoJornada(array $comp): string
    {
        $cfg = is_array($comp['config'] ?? null) ? $comp['config'] : [];
        $bims = [];
        foreach ((array) ($cfg['jornada_bimestres'] ?? []) as $b) {
            $b = (int) $b;
            if ($b >= 1 && $b <= 4) {
                $bims[] = $b;
            }
        }
        $bims = array_values(array_unique($bims));
        $nIds = count((array) ($cfg['jornada_ids'] ?? []));
        if ($bims !== []) {
            $txt = implode(', ', array_map(static fn (int $b) => $b . 'º', $bims));
            return ' · ' . $txt . ' bimestre' . (count($bims) > 1 ? 's' : '') . ($nIds > 0 ? ' (' . $nIds . ')' : '');
        }
        if ($nIds > 0) {
            return ' · ' . $nIds . ' selecionada(s)';
        }
        return ' · bimestre da regra';
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<int>
     */
    private function bimestresJornadaDoEstado(array $estado): array
    {
        $bims = array_values(array_unique(array_filter(array_map('intval', (array) ($estado['jornada_bimestres'] ?? [])))));
        $bims = array_values(array_filter($bims, static fn ($b) => $b >= 1 && $b <= 4));
        if ($bims === []) {
            $b = (int) ($estado['bimestre'] ?? 0);
            if ($b >= 1 && $b <= 4) {
                $bims = [$b];
            }
        }
        return $bims;
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<int>
     */
    private function resolverIdsJornadaPorBimestre(array $estado): array
    {
        return $this->ferramentas->resolverIdsJornadaPorBimestre(
            $this->bimestresJornadaDoEstado($estado),
            (int) ($estado['ano_letivo'] ?? 0)
        );
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function querLayoutQuadro(array $estado): bool
    {
        if ($this->ehBoletimComposto($estado)) {
            return false;
        }
        if ((int) ($estado['grupo_regras_notas_id'] ?? 0) <= 0) {
            return false;
        }
        if (($estado['modelo_key'] ?? '') === 'quadro_semanal'
            && in_array('semanal', (array) ($estado['pecas'] ?? []), true)) {
            return true;
        }
        return in_array('semanal', (array) ($estado['pecas'] ?? []), true);
    }

    /**
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $rascunho
     */
    private function deveRemontarComoQuadro(array $estado, array $rascunho): bool
    {
        if (($estado['origem'] ?? '') === 'clonar') {
            return false;
        }
        if (!$this->querLayoutQuadro($estado)) {
            return false;
        }
        return !$this->rascunhoJaEhQuadro($rascunho);
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    private function rascunhoJaEhQuadro(array $rascunho): bool
    {
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (preg_match('/^s[1-9]\d?$/', $cod)) {
                return true;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            if (in_array($g, ['quadro_a', 'quadro_b'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function montarQuadroDoEstado(array $estado): array
    {
        $grupoQuadro = (int) ($estado['grupo_regras_notas_id'] ?? 0);
        $semanas = $this->semanasQuadroConfig($grupoQuadro > 0 ? $grupoQuadro : 0);
        $rascunho = $this->ferramentas->montarRascunhoQuadroSemanal([
            'ano_letivo' => (int) ($estado['ano_letivo'] ?? date('Y')),
            'bimestre' => (int) ($estado['bimestre'] ?? 1),
            'nome' => (string) ($estado['nome'] ?? ''),
            'codigo' => (string) ($estado['codigo'] ?? ''),
            'modo' => (string) ($estado['modo'] ?? 'criar'),
            'regra_id' => $estado['regra_id'] ?? null,
            'exibir_em' => (string) ($estado['exibir_em'] ?? 'boletim'),
            'turmas_ids' => $estado['turmas_ids'] ?? [],
            'materias_ids' => $estado['materias_ids'] ?? [],
            'series_ids' => $estado['series_ids'] ?? [],
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'round_mode' => (string) ($estado['round_mode'] ?? 'none'),
            'decimal_places' => \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? 2),
            'nota_minima_aprovacao' => $estado['nota_minima_aprovacao'] ?? 6,
            'nota_minima_origem' => (string) ($estado['nota_minima_origem'] ?? 'padrao'),
            'nota_minima_regra_nome' => (string) ($estado['nota_minima_regra_nome'] ?? ''),
            'semanas_a' => $semanas['a'],
            'semanas_b' => $semanas['b'],
            'so_semanas_com_evento' => false,
            'pecas' => $estado['pecas'] ?? [],
        ]);
        $rascunho = $this->mesclarPecasFaltantesNoQuadro($rascunho, $estado);
        $rascunho['boletim_id'] = (int) ($estado['boletim_id'] ?? 0);
        $rascunho['grupo_regras_notas_id'] = (int) ($estado['grupo_regras_notas_id'] ?? 0);
        $rascunho['finalidade'] = $this->normalizarFinalidade($estado['finalidade'] ?? 'oficial');
        $rascunho['exibir_em'] = (string) ($estado['exibir_em'] ?? 'notas');
        return $this->aplicarPapeisNoQuadro($rascunho, $estado);
    }

    /**
     * @return array{a:list<int>,b:list<int>}
     */
    private function semanasQuadroConfig(?int $grupoId = null): array
    {
        $vazio = ['a' => [], 'b' => []];
        if ($grupoId === null || $grupoId <= 0) {
            return $vazio;
        }
        $path = dirname(__DIR__, 2) . '/grupos-regras-notas/Services/GrupoRegrasNotasService.php';
        if (!class_exists('GrupoRegrasNotasService', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('GrupoRegrasNotasService', false)) {
            return $vazio;
        }
        try {
            $sem = (new GrupoRegrasNotasService())->semanasQuadroPadrao($grupoId);
            $sa = is_array($sem['a'] ?? null) ? array_values(array_map('intval', $sem['a'])) : [];
            $sb = is_array($sem['b'] ?? null) ? array_values(array_map('intval', $sem['b'])) : [];

            return ['a' => $sa, 'b' => $sb];
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard semanas quadro: ' . $e->getMessage());
            return $vazio;
        }
    }

    /**
     * Inclui no quadro S1–S8 as peças marcadas que o molde semanal não gerou
     * (Trabalho, tipo avulso, jornada…).
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function mesclarPecasFaltantesNoQuadro(array $rascunho, array $estado): array
    {
        $mapaCodigos = [
            'bimestral' => 'prova_bim',
            'enac' => 'enac',
            'trabalho' => 'trab',
            'participacao' => 'part',
            'recuperacao' => 'rec',
            'jornada' => 'jornada',
        ];
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $presentes = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '') {
                $presentes[$cod] = true;
            }
        }

        $inserir = [];
        foreach ((array) ($estado['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '' || $p === 'semanal') {
                continue;
            }
            $cod = $mapaCodigos[$p] ?? $p;
            if ($cod === '' || !empty($presentes[$cod])) {
                continue;
            }
            $opts = is_array($estado['pecas_opcoes'][$p] ?? null) ? $estado['pecas_opcoes'][$p] : [];
            $def = $this->definirComponentePeca($p, $opts, $estado);
            if ($def === null) {
                continue;
            }
            $codDef = strtolower(trim((string) ($def['codigo'] ?? $cod)));
            if ($codDef === '' || !empty($presentes[$codDef])) {
                continue;
            }
            $def['config'] = is_array($def['config'] ?? null) ? $def['config'] : [];
            $def['config']['layout_group'] = 'quadro_comum';
            if ($p === 'recuperacao' || $codDef === 'rec') {
                $def['config']['layout_type'] = 'rec';
            } else {
                $def['config']['layout_type'] = $def['config']['layout_type'] ?? 'media';
            }
            $inserir[] = $def;
            $presentes[$codDef] = true;
        }

        if ($inserir === []) {
            return $rascunho;
        }

        $out = [];
        $colocados = false;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $codC = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (!$colocados && in_array($codC, ['media_bim', 'media_final'], true)) {
                foreach ($inserir as $def) {
                    $out[] = $def;
                }
                $colocados = true;
            }
            $out[] = $c;
        }
        if (!$colocados) {
            $rascunho['componentes'] = array_merge($comps, $inserir);
            return $rascunho;
        }
        $rascunho['componentes'] = $out;
        return $rascunho;
    }

    /**
     * Recalcula media_bim / media_final do quadro a partir dos papéis do wizard.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarPapeisNoQuadro(array $rascunho, array $estado): array
    {
        $mapaCodigos = [
            'semanal' => 'media_sem',
            'bimestral' => 'prova_bim',
            'enac' => 'enac',
            'trabalho' => 'trab',
            'participacao' => 'part',
            'recuperacao' => 'rec',
            'jornada' => 'jornada',
        ];
        $dataIni = (string) ($estado['data_inicio'] ?? '');
        $dataFim = (string) ($estado['data_fim'] ?? '');
        $optsSemanal = is_array($estado['pecas_opcoes']['semanal'] ?? null) ? $estado['pecas_opcoes']['semanal'] : [];
        $bimsSemanal = $this->normalizarBimestresLista($optsSemanal['bimestres'] ?? []);
        $manualSemanal = !empty($optsSemanal['blocos_ids_manual']);
        $blocosSemanal = $this->normalizarIdsLista($optsSemanal['blocos_ids'] ?? []);
        $comps = [];
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (in_array($cod, ['media_bim', 'media_final'], true)) {
                continue;
            }
            $c['config'] = is_array($c['config'] ?? null) ? $c['config'] : [];
            if (preg_match('/^s([1-8])$/', $cod, $mSem)) {
                if ($bimsSemanal !== []) {
                    $c['config']['prova_bimestres'] = $bimsSemanal;
                }
                if ($manualSemanal) {
                    $c['blocos_ids'] = $blocosSemanal;
                    $c['config']['blocos_ids_manual'] = 1;
                }
                $tipoSem = (int) ($c['config']['tipo_avaliacao_id'] ?? $c['tipo_avaliacao_id'] ?? 0);
                if ($tipoSem > 0 && !$manualSemanal) {
                    $resolvidoSem = $this->ferramentas->resolverBlocosPorTipo(
                        $tipoSem,
                        $dataIni !== '' ? $dataIni : null,
                        $dataFim !== '' ? $dataFim : null,
                        100,
                        (int) $mSem[1],
                        $bimsSemanal
                    );
                    if (($resolvidoSem['blocos_ids'] ?? []) !== []) {
                        $c['blocos_ids'] = $resolvidoSem['blocos_ids'];
                    }
                }
            }
            $pecaKey = array_search($cod, $mapaCodigos, true);
            if ($pecaKey !== false) {
                $opts = is_array($estado['pecas_opcoes'][$pecaKey] ?? null) ? $estado['pecas_opcoes'][$pecaKey] : [];
                $c['config']['papel_wizard'] = $this->normalizarPapel($opts['papel'] ?? '', (string) $pecaKey);
                if ($pecaKey !== 'jornada') {
                    $tipoPeca = (int) ($opts['tipo_avaliacao_id'] ?? $c['config']['tipo_avaliacao_id'] ?? $c['tipo_avaliacao_id'] ?? 0);
                    $herdado = $this->fechamentoParaPeca((string) $pecaKey, $tipoPeca);
                    if ($herdado !== null) {
                        $c['calc_type'] = $herdado['calc_type'];
                        $c['usar_percentual'] = $herdado['usar_percentual'];
                    }
                    $bimsPeca = $this->normalizarBimestresLista($opts['bimestres'] ?? []);
                    if ($bimsPeca !== []) {
                        $c['config']['prova_bimestres'] = $bimsPeca;
                    }
                    $manualBlocosPeca = !empty($opts['blocos_ids_manual']);
                    if ($manualBlocosPeca) {
                        $c['blocos_ids'] = $this->normalizarIdsLista($opts['blocos_ids'] ?? []);
                        $c['config']['blocos_ids_manual'] = 1;
                    }
                    $tipoPeca = (int) ($opts['tipo_avaliacao_id'] ?? $c['config']['tipo_avaliacao_id'] ?? $c['tipo_avaliacao_id'] ?? 0);
                    if ($tipoPeca > 0 && !$manualBlocosPeca) {
                        $resolvidoPeca = $this->ferramentas->resolverBlocosPorTipo(
                            $tipoPeca,
                            $dataIni !== '' ? $dataIni : null,
                            $dataFim !== '' ? $dataFim : null,
                            100,
                            null,
                            $bimsPeca
                        );
                        if (($resolvidoPeca['blocos_ids'] ?? []) !== []) {
                            $c['blocos_ids'] = $resolvidoPeca['blocos_ids'];
                        }
                    }
                }
            }
            $comps[] = $c;
        }

        $presentes = [];
        foreach ($comps as $c) {
            $presentes[strtolower(trim((string) ($c['codigo'] ?? '')))] = true;
        }

        $mapa = [];
        $pecasFormula = [];
        foreach ((array) ($estado['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '') {
                continue;
            }
            $cod = $mapaCodigos[$p] ?? $p;
            if (empty($presentes[$cod])) {
                continue;
            }
            $opts = is_array($estado['pecas_opcoes'][$p] ?? null) ? $estado['pecas_opcoes'][$p] : [];
            $mapa[$p] = [
                'codigo' => $cod,
                'papel' => $this->normalizarPapel($opts['papel'] ?? '', $p),
                'nome' => $p,
            ];
            $pecasFormula[] = $p;
        }

        $rascunho['componentes'] = $comps;
        if ($this->ehDemonstrativoNotas($estado)) {
            $rascunho['formula_final'] = '';

            return $this->aplicarFormulasBlocos($rascunho, $estado, $mapa);
        }

        $formula = $this->montarFormulaPorPapeis($comps, $mapa, $pecasFormula, 'media_bim', 'media_final', true);
        $rascunho['formula_final'] = $formula !== '' ? $formula : (string) ($rascunho['formula_final'] ?? 'media_bim');
        $rascunho = $this->aplicarFormulasBlocos($rascunho, $estado, $mapa);
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : $comps;
        foreach ($comps as &$c) {
            if ((string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $cod = (string) ($c['codigo'] ?? '');
            if ($cod === 'media_sem') {
                continue;
            }
            $c['config'] = is_array($c['config'] ?? null) ? $c['config'] : [];
            $c['config']['layout_group'] = $c['config']['layout_group'] ?? 'quadro_comum';
            $nomeCustom = trim((string) ((is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [])[$cod] ?? ''));
            if ($nomeCustom !== '') {
                $c['nome'] = $nomeCustom;
            }
            if ($this->colunaTituloFoiLimpo($estado, $cod)) {
                $c['nome'] = $this->nomePadraoColuna($cod);
            }
            if ($cod === 'media_final') {
                $c['config']['layout_type'] = 'media';
                if ($nomeCustom === '' && trim((string) ($c['nome'] ?? '')) === '') {
                    $c['nome'] = 'Resultado Final';
                }
            } elseif ($cod === 'media_bim') {
                $c['config']['layout_type'] = 'media';
                if ($nomeCustom === '' && trim((string) ($c['nome'] ?? '')) === '') {
                    $c['nome'] = 'Média Bim';
                }
            } else {
                $c['config']['layout_type'] = $c['config']['layout_type'] ?? 'media';
            }
        }
        unset($c);

        $rascunho['componentes'] = $comps;
        $ff = trim((string) ($rascunho['formula_final'] ?? ''));
        $rascunho['formula_final'] = $ff !== '' ? $ff : 'media_bim';
        return $rascunho;
    }

    /**
     * Aplica estado.formulas_blocos (clique na coluna amarela) em cada calculado.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @param array<string,array{codigo:string,papel?:string,nome?:string}> $mapaPecas
     * @return array<string,mixed>
     */
    private function aplicarFormulasBlocos(array $rascunho, array $estado, array $mapaPecas): array
    {
        if ($this->ehDemonstrativoNotas($estado)) {
            $rascunho = $this->sanitizarDemonstrativoSemFormula($rascunho, $estado);

            return $this->aplicarEscalasBlocos($this->aplicarNomesBlocos($rascunho, $estado), $estado);
        }

        $formulas = is_array($estado['formulas_blocos'] ?? null) ? $estado['formulas_blocos'] : [];
        $excecoes = is_array($estado['formulas_materias_blocos'] ?? null) ? $estado['formulas_materias_blocos'] : [];
        if ($formulas === [] && $excecoes === []) {
            // Ainda aplica Média do Ciclo / nomes / escalas (flag pode mudar sem reeditar a fórmula).
            return $this->aplicarFlagsMediaCicloNosComponentes(
                $this->aplicarEscalasBlocos($this->aplicarNomesBlocos($rascunho, $estado), $estado),
                $estado
            );
        }

        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $ehQuadro = $this->rascunhoJaEhQuadro($rascunho);
        $mapa = $this->expandirMapaFormula($mapaPecas, $comps);
        foreach (array_keys($formulas) as $codFormula) {
            $codFormula = strtolower(trim((string) $codFormula));
            if ($codFormula === '' || isset($mapa[$codFormula]['codigo'])) {
                continue;
            }
            $mapa[$codFormula] = [
                'codigo' => $codFormula,
                'papel' => 'exibe',
                'nome' => $codFormula,
            ];
        }
        $nomes = [
            'media_bim' => 'Média Bim',
            'media_final' => 'Resultado Final',
            'media_sem' => 'Média Sem',
        ];

        $ordem = [];
        foreach (['media_bim', 'media_final'] as $codPref) {
            if (isset($formulas[$codPref])) {
                $ordem[] = $codPref;
            }
        }
        foreach ($formulas as $cod => $_toks) {
            $cod = strtolower(trim((string) $cod));
            if ($cod === '' || $cod === 'media_sem' || in_array($cod, $ordem, true)) {
                continue;
            }
            $ordem[] = $cod;
        }

        $aplicouFinal = false;
        $aplicouBim = false;
        foreach ($ordem as $codigoAlvo) {
            $tokens = is_array($formulas[$codigoAlvo] ?? null) ? $formulas[$codigoAlvo] : [];
            if ($tokens === []) {
                continue;
            }
            $mapaCompila = $mapa;
            unset($mapaCompila[$codigoAlvo]);
            $exp = $this->compilarTokensParaExpressao($tokens, $mapaCompila, []);
            if ($exp === '') {
                continue;
            }
            $nomeCustom = trim((string) ((is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [])[$codigoAlvo] ?? ''));
            $idxExistente = null;
            $sourceExistente = '';
            $nomeExistente = '';
            foreach ($comps as $i => $c) {
                if (!is_array($c)) {
                    continue;
                }
                if (strtolower(trim((string) ($c['codigo'] ?? ''))) !== $codigoAlvo) {
                    continue;
                }
                $idxExistente = $i;
                $sourceExistente = (string) ($c['source_type'] ?? '');
                $nomeExistente = trim((string) ($c['nome'] ?? ''));
                break;
            }
            if ($this->colunaTituloFoiLimpo($estado, $codigoAlvo)) {
                $nome = $this->nomePadraoColuna($codigoAlvo);
            } else {
                $nome = $nomeCustom !== ''
                    ? $nomeCustom
                    : ($nomeExistente !== '' ? $nomeExistente : ($nomes[$codigoAlvo] ?? $codigoAlvo));
            }
            $escalaAlvo = $this->escalaDoBloco($estado, $codigoAlvo);
            $grupoLayout = $this->layoutGroupDaColunaNoEstado($codigoAlvo, $estado);
            if ($idxExistente !== null) {
                $c = $comps[$idxExistente];
                $c['config'] = is_array($c['config'] ?? null) ? $c['config'] : [];
                // Fórmula em cima de evento_boletim/outra origem: vira coluna calculada.
                if ($sourceExistente !== 'calculado') {
                    $c['source_type'] = 'calculado';
                    $c['calc_type'] = 'media';
                }
                $c['config']['expressao'] = $exp;
                if ($ehQuadro) {
                    $grupoAtual = strtolower(trim((string) ($c['config']['layout_group'] ?? '')));
                    if ($grupoAtual === '' || $grupoAtual === 'quadro_comum') {
                        $c['config']['layout_group'] = $grupoLayout;
                    }
                    $c['config']['layout_type'] = $c['config']['layout_type'] ?? 'media';
                }
                $c['nome'] = $nome;
                $c['escala_max'] = $escalaAlvo;
                $comps[$idxExistente] = $c;
            } else {
                $bloco = $this->blocoCalculado($codigoAlvo, $nome, $exp, $escalaAlvo);
                if ($ehQuadro) {
                    $bloco['config']['layout_group'] = $grupoLayout;
                    $bloco['config']['layout_type'] = 'media';
                }
                $comps[] = $bloco;
            }
            $mapa[$codigoAlvo] = ['codigo' => $codigoAlvo, 'papel' => 'exibe', 'nome' => $nome];
            if ($codigoAlvo === 'media_final') {
                $aplicouFinal = true;
            } elseif ($codigoAlvo === 'media_bim') {
                $aplicouBim = true;
            }
        }

        $rascunho['componentes'] = $comps;
        if ($aplicouFinal) {
            $rascunho['formula_final'] = 'media_final';
        } elseif ($aplicouBim) {
            $temFinal = false;
            foreach ($comps as $c) {
                if (is_array($c) && strtolower((string) ($c['codigo'] ?? '')) === 'media_final') {
                    $temFinal = true;
                    break;
                }
            }
            if (!$temFinal) {
                $rascunho['formula_final'] = 'media_bim';
            }
        }
        $rascunho = $this->aplicarExcecoesMateriaNosCalculados($rascunho, $estado, $mapa);
        $rascunho = $this->aplicarEscalasBlocos($this->aplicarNomesBlocos($rascunho, $estado), $estado);
        $linhas = $this->normalizarLinhasLayout($estado['linhas_layout'] ?? $rascunho['linhas_layout'] ?? []);
        if ($linhas !== [] && is_array($rascunho['componentes'] ?? null)) {
            $rascunho['componentes'] = $this->aplicarLinhasLayoutNosComponentes($rascunho['componentes'], $linhas);
        }

        return $this->aplicarFlagsMediaCicloNosComponentes($rascunho, $estado);
    }

    /**
     * Grava config.media_ciclo (+ layout_group do título) a partir de estado.media_ciclo_blocos.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarFlagsMediaCicloNosComponentes(array $rascunho, array $estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        if ($comps === []) {
            return $rascunho;
        }
        foreach ($comps as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $src = (string) ($c['source_type'] ?? '');
            if ($src !== 'calculado' && $src !== 'evento_boletim') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($this->blocoEhMediaCiclo($estado, $cod)) {
                $cfg['media_ciclo'] = 1;
                $destino = $this->destinoMediaCicloDoBloco($estado, $cod);
                if ($destino === '') {
                    $destino = $this->layoutGroupDaColunaNoEstado($cod, $estado);
                }
                if (!in_array($destino, ['b1', 'b2', 'b3', 'b4', 's1', 's2', 'final'], true)) {
                    $destino = '';
                }
                if ($destino !== '') {
                    $cfg['media_ciclo_destino'] = $destino;
                    $cfg['layout_group'] = $destino;
                    $cfg['layout_type'] = $cfg['layout_type'] ?? 'media';
                }
            } else {
                unset($cfg['media_ciclo'], $cfg['media_ciclo_destino']);
            }
            $comps[$i]['config'] = $cfg;
        }
        $rascunho['componentes'] = $comps;

        return $rascunho;
    }

    /**
     * Infere layout_group (b1…b4 / s1 / s2) a partir do cabeçalho onde a coluna está.
     *
     * @param array<string,mixed> $estado
     */
    private function layoutGroupDaColunaNoEstado(string $codigo, array $estado): string
    {
        $codigo = strtolower(trim($codigo));
        if ($codigo === '') {
            return 'quadro_comum';
        }
        if (preg_match('/^b([1-4])_/', $codigo, $m)) {
            return 'b' . $m[1];
        }
        if (str_starts_with($codigo, 's1_')) {
            return 's1';
        }
        if (str_starts_with($codigo, 's2_')) {
            return 's2';
        }
        foreach ($this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $cols = [];
            foreach ((array) ($lin['colunas'] ?? []) as $c) {
                $c = strtolower(trim((string) $c));
                if ($c !== '') {
                    $cols[] = $c;
                }
            }
            if ($cols === [] || !in_array($codigo, $cols, true)) {
                continue;
            }
            foreach ($cols as $c) {
                if (preg_match('/^b([1-4])_/', $c, $m)) {
                    return 'b' . $m[1];
                }
                if (str_starts_with($c, 's1_')) {
                    return 's1';
                }
                if (str_starts_with($c, 's2_')) {
                    return 's2';
                }
            }
            $nome = mb_strtoupper(trim((string) ($lin['nome'] ?? '')), 'UTF-8');
            // Semestre / “a cada dois ciclos” (antes do bimestre: “1º” sozinho viraria b1).
            if (preg_match('/SEMESTRE|SEMESTRAL|\bPAR\b|ETAPA/', $nome)) {
                if (preg_match('/\b2[ºO]?\b|SEGUNDO|\bII\b/', $nome) && !preg_match('/\bIII\b/', $nome)) {
                    return 's2';
                }
                if (preg_match('/\b1[ºO]?\b|PRIMEIRO|\bI\b/', $nome)) {
                    return 's1';
                }
            }
            // Rec / média final do ano.
            if (preg_match('/\bFINAL\b|RESULTADO\s+FINAL|M[ÉE]DIA\s+FINAL|\bMF\b|\bMA\b/', $nome)) {
                return 'final';
            }
            if (preg_match('/\bIV\b|4[ºO]|QUARTO/', $nome)) {
                return 'b4';
            }
            if (preg_match('/\bIII\b|3[ºO]|TERCEIRO/', $nome)) {
                return 'b3';
            }
            if (preg_match('/\bII\b|2[ºO]|SEGUNDO/', $nome) && !preg_match('/\bIII\b/', $nome)) {
                return 'b2';
            }
            if (preg_match('/\bI\b|1[ºO]|PRIMEIRO/', $nome)) {
                return 'b1';
            }
        }

        return 'quadro_comum';
    }

    /**
     * Garante que todo bloco de cálculo do estado vira componente (mesmo sem tokens ainda).
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function garantirBlocosCalcNosComponentes(array $rascunho, array $estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $vistos = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '') {
                $vistos[$cod] = true;
            }
        }
        $codigos = [];
        foreach ((array) ($estado['blocos_calc'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $codigos[$cod] = true;
            }
        }
        foreach (array_keys((array) ($estado['formulas_blocos'] ?? [])) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '' && $cod !== 'media_sem') {
                $codigos[$cod] = true;
            }
        }
        $ehQuadro = $this->rascunhoJaEhQuadro($rascunho)
            || !empty($estado['quadro_livre'])
            || ($estado['origem'] ?? '') === 'quadro-impressao'
            || $this->ehBoletimComposto($estado);
        $nomes = is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [];
        foreach (array_keys($codigos) as $cod) {
            if (isset($vistos[$cod])) {
                // Só reforça layout_group se estiver genérico.
                foreach ($comps as $i => $c) {
                    if (!is_array($c) || strtolower(trim((string) ($c['codigo'] ?? ''))) !== $cod) {
                        continue;
                    }
                    if (!$ehQuadro) {
                        break;
                    }
                    $cfg = $this->configDoComponente($c);
                    $g = strtolower(trim((string) ($cfg['layout_group'] ?? '')));
                    if ($g === '' || $g === 'quadro_comum') {
                        $cfg['layout_group'] = $this->layoutGroupDaColunaNoEstado($cod, $estado);
                        $cfg['layout_type'] = $cfg['layout_type'] ?? 'media';
                        $comps[$i]['config'] = $cfg;
                    }
                    break;
                }
                continue;
            }
            $nome = trim((string) ($nomes[$cod] ?? ''));
            if ($nome === '') {
                $nome = $cod === 'media_bim' ? 'Média Bim'
                    : ($cod === 'media_final' ? 'Resultado Final' : 'Nova média');
            }
            $bloco = $this->blocoCalculado($cod, $nome, '0', $this->escalaDoBloco($estado, $cod));
            if ($ehQuadro) {
                $bloco['config']['layout_group'] = $this->layoutGroupDaColunaNoEstado($cod, $estado);
                $bloco['config']['layout_type'] = 'media';
            }
            $comps[] = $bloco;
            $vistos[$cod] = true;
        }
        $rascunho['componentes'] = $comps;
        $linhas = $this->normalizarLinhasLayout($estado['linhas_layout'] ?? $rascunho['linhas_layout'] ?? []);
        if ($linhas !== []) {
            $rascunho['componentes'] = $this->aplicarLinhasLayoutNosComponentes($rascunho['componentes'], $linhas);
            $rascunho['linhas_layout'] = $linhas;
        }

        return $this->aplicarFlagsMediaCicloNosComponentes(
            $this->aplicarEscalasBlocos($this->aplicarNomesBlocos($rascunho, $estado), $estado),
            $estado
        );
    }

    /**
     * Fórmulas alternativas só em algumas matérias (ex.: sem semanal → bimestral + ENAC).
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @param array<string,array{codigo:string,papel?:string,nome?:string}> $mapa
     * @return array<string,mixed>
     */
    private function aplicarExcecoesMateriaNosCalculados(array $rascunho, array $estado, array $mapa): array
    {
        $excecoes = is_array($estado['formulas_materias_blocos'] ?? null) ? $estado['formulas_materias_blocos'] : [];
        if ($excecoes === []) {
            return $rascunho;
        }
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $mapa = $this->expandirMapaFormula($mapa, $comps);
        foreach ($comps as $i => $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === 'media_sem' || !isset($excecoes[$cod])) {
                continue;
            }
            $porMat = $excecoes[$cod];
            if ($porMat instanceof \stdClass) {
                $porMat = (array) $porMat;
            }
            $porMat = is_array($porMat) ? $porMat : [];
            $mapaCompila = $mapa;
            unset($mapaCompila[$cod]);
            $fm = [];
            foreach ($porMat as $midRaw => $tokens) {
                $mid = (int) $midRaw;
                if ($mid <= 0 || !is_array($tokens) || $tokens === []) {
                    continue;
                }
                $exp = $this->compilarTokensParaExpressao($tokens, $mapaCompila, []);
                if ($exp !== '') {
                    $fm[$mid] = $exp;
                }
            }
            $c['config'] = is_array($c['config'] ?? null) ? $c['config'] : [];
            $c['config']['formula_materias'] = $fm;
            $c['config']['formula_mode'] = $fm !== [] ? 'per_materia' : 'single';
            $comps[$i] = $c;
        }
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * Aplica valor máximo (escala) definido no editor da fórmula.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarEscalasBlocos(array $rascunho, array $estado): array
    {
        $escalas = is_array($estado['escalas_blocos'] ?? null) ? $estado['escalas_blocos'] : [];
        $escalaMaxPadrao = $this->normalizarEscalaMaxima($estado['escala_max_padrao'] ?? null, 10.0);
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as &$c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || (bool) preg_match('/^s[1-8]$/', $cod)) {
                continue;
            }
            if (isset($escalas[$cod])) {
                $c['escala_max'] = $this->escalaDoBloco($estado, $cod);
                continue;
            }
            $source = strtolower(trim((string) ($c['source_type'] ?? '')));
            $ehFaltas = $this->componenteEhFaltas($c);
            if ($source === 'provas_sistema' || ($ehFaltas && $source !== 'faltas_evento')) {
                if ($escalaMaxPadrao > 0.0) {
                    $c['escala_max'] = $escalaMaxPadrao;
                }
            }
        }
        unset($c);
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarNomesBlocos(array $rascunho, array $estado): array
    {
        $nomes = is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [];
        if ($nomes === []) {
            return $rascunho;
        }
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as &$c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || (bool) preg_match('/^s[1-8]$/', $cod)) {
                continue;
            }
            if ($this->componenteVemDeEventoAvaliativo($c)) {
                continue;
            }
            if ($this->colunaTituloFoiLimpo($estado, $cod)) {
                $c['nome'] = $this->nomePadraoColuna($cod);
                continue;
            }
            $nome = trim((string) ($nomes[$cod] ?? ''));
            if ($nome === '') {
                $aliases = [
                    'media_sem' => 'semanal',
                    'semanal' => 'media_sem',
                    'prova_bim' => 'bimestral',
                    'bimestral' => 'prova_bim',
                    'rec' => 'recuperacao',
                    'recuperacao' => 'rec',
                    'trab' => 'trabalho',
                    'trabalho' => 'trab',
                    'part' => 'participacao',
                    'participacao' => 'part',
                ];
                $outro = $aliases[$cod] ?? '';
                if ($outro !== '') {
                    $nome = trim((string) ($nomes[$outro] ?? ''));
                }
            }
            if ($nome === '') {
                continue;
            }
            if ($nome !== '') {
                $c['nome'] = $nome;
            }
        }
        unset($c);
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * @param array<string,array{codigo:string,papel?:string,nome?:string}> $mapaPecas
     * @param list<array<string,mixed>> $comps
     * @return array<string,array{codigo:string,papel?:string,nome?:string}>
     */
    private function expandirMapaFormula(array $mapaPecas, array $comps): array
    {
        $mapa = $mapaPecas;
        $aliases = [
            'semanal' => 'media_sem',
            'bimestral' => 'prova_bim',
            'trabalho' => 'trab',
            'participacao' => 'part',
            'recuperacao' => 'rec',
        ];
        foreach ($aliases as $peca => $codQuadro) {
            if (!isset($mapa[$peca]['codigo'])) {
                continue;
            }
            if (!isset($mapa[$codQuadro])) {
                $mapa[$codQuadro] = $mapa[$peca];
            }
        }
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $mapa[$cod] = [
                'codigo' => $cod,
                'papel' => 'exibe',
                'nome' => (string) ($c['nome'] ?? $cod),
            ];
        }
        foreach ($aliases as $peca => $codQuadro) {
            if (isset($mapa[$peca]['codigo']) || !isset($mapa[$codQuadro]['codigo'])) {
                continue;
            }
            $mapa[$peca] = $mapa[$codQuadro];
        }
        return $mapa;
    }

    /**
     * Zera IDs de prova/jornada da origem para o clone re-resolver no bimestre/datas novos.
     *
     * @param list<array> $componentes
     * @param array<string,mixed> $estado
     * @return list<array>
     */
    private function ajustarComponentesClonados(array $componentes, array $estado): array
    {
        $dataIni = (string) ($estado['data_inicio'] ?? '');
        $dataFim = (string) ($estado['data_fim'] ?? '');
        $bims = [];
        foreach ((array) ($estado['jornada_bimestres'] ?? []) as $b) {
            $b = (int) $b;
            if ($b >= 1 && $b <= 4) {
                $bims[] = $b;
            }
        }
        $bims = array_values(array_unique($bims));
        if ($bims === []) {
            $bim = (int) ($estado['bimestre'] ?? 0);
            if ($bim >= 1 && $bim <= 4) {
                $bims = [$bim];
            }
        }
        $modoJornada = (string) ($estado['jornada_modo'] ?? 'todas');
        $out = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $c['blocos_ids'] = [];
            $c['bloco_id'] = null;
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ((string) ($c['source_type'] ?? '') === 'jornadas') {
                $cfg['jornada_ids'] = [];
                if ($modoJornada === 'selecionadas') {
                    $cfg['jornada_ids'] = array_values(array_filter(
                        array_map('intval', (array) ($estado['jornada_ids'] ?? [])),
                        static fn ($id) => $id > 0
                    ));
                    unset($cfg['jornada_bimestres']);
                } elseif ($modoJornada === 'todas' && ($dataIni !== '' || $dataFim !== '')) {
                    unset($cfg['jornada_bimestres']);
                } elseif ($bims !== []) {
                    $cfg['jornada_bimestres'] = $bims;
                } else {
                    unset($cfg['jornada_bimestres']);
                }
                if ($dataIni !== '') {
                    $cfg['data_ini'] = $dataIni;
                }
                if ($dataFim !== '') {
                    $cfg['data_fim'] = $dataFim;
                }
            }
            $c['config'] = $cfg;
            $out[] = $c;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<string>
     */
    private function errosGrupoLinha(array $estado): array
    {
        $g = $this->completarGrupoLinhaComFamilia(
            $this->normalizarGrupoLinha($estado['grupo_linha'] ?? null),
            $estado
        );
        if (!$g['ativo']) {
            return [];
        }
        $erros = [];
        if ($g['nome'] === '') {
            $erros[] = 'Dê um nome à linha agrupada (ex.: Língua Portuguesa).';
        }
        if (count($g['materias_ids']) < 2) {
            $erros[] = 'Escolha a área já cadastrada em Componentes Curriculares (pai com pelo menos dois desdobramentos).';
        }
        return $erros;
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function grupoLinhaEstaVazio(array $estado): bool
    {
        $g = $this->normalizarGrupoLinha($estado['grupo_linha'] ?? null);
        return !$g['ativo'] && $g['nome'] === '' && $g['materias_ids'] === [];
    }

    /**
     * @return array{ativo:bool,nome:string,modo:string,materias_ids:list<int>,aplicar_em:string,agrupamento_id:int,arredondamento:string}
     */
    public static function grupoLinhaPadrao(): array
    {
        return [
            'ativo' => false,
            'nome' => '',
            'modo' => 'media',
            'modos' => [],
            'materias_ids' => [],
            'aplicar_em' => 'boletim',
            'agrupamento_id' => 0,
            'arredondamento' => 'todos',
            'exemplo_sem_filhas' => false,
        ];
    }

    /**
     * Se a linha única está ligada e ainda não tem matérias, usa a única
     * família pai/filhos do escopo (Componentes Curriculares).
     *
     * @param array{ativo:bool,nome:string,modo:string,materias_ids:list<int>,aplicar_em:string,agrupamento_id:int,arredondamento:string} $g
     * @param array<string,mixed> $estado
     * @return array{ativo:bool,nome:string,modo:string,materias_ids:list<int>,aplicar_em:string,agrupamento_id:int,arredondamento:string}
     */
    private function completarGrupoLinhaComFamilia(array $g, array $estado): array
    {
        if (!$g['ativo'] || count($g['materias_ids']) >= 2 || $g['agrupamento_id'] > 0) {
            return $g;
        }
        $familias = $this->familiasNoEscopo($estado);
        if (count($familias) !== 1) {
            return $g;
        }
        $f = $familias[0];
        if ($g['nome'] === '') {
            $g['nome'] = (string) ($f['nome'] ?? '');
        }
        $g['materias_ids'] = array_values(array_map('intval', (array) ($f['materias_ids'] ?? [])));

        return $g;
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<array{pai_id:int,nome:string,filhos:list<array{id:int,nome:string}>,materias_ids:list<int>}>
     */
    private function familiasNoEscopo(array $estado): array
    {
        $idsEscopo = array_values(array_filter(
            array_map('intval', (array) ($estado['materias_ids'] ?? [])),
            static fn ($id) => $id > 0
        ));
        $bolId = (int) ($estado['boletim_id'] ?? 0);
        if ($idsEscopo === [] && $bolId > 0) {
            foreach ($this->catalogoBoletins() as $b) {
                if ((int) ($b['id'] ?? 0) !== $bolId) {
                    continue;
                }
                $idsEscopo = array_values(array_filter(
                    array_map('intval', (array) ($b['materias_ids'] ?? [])),
                    static fn ($id) => $id > 0
                ));
                break;
            }
        }
        $set = $idsEscopo === [] ? null : array_fill_keys($idsEscopo, true);
        $out = [];
        foreach ($this->catalogoFamiliasComponentes() as $f) {
            $paiId = (int) ($f['pai_id'] ?? 0);
            $filhos = [];
            foreach ((array) ($f['filhos'] ?? []) as $ch) {
                $cid = (int) ($ch['id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                if ($set !== null && empty($set[$cid])) {
                    continue;
                }
                $filhos[] = [
                    'id' => $cid,
                    'nome' => trim((string) ($ch['nome'] ?? '')),
                ];
            }
            if (count($filhos) < 2 && $set !== null && $paiId > 0 && !empty($set[$paiId])) {
                $filhos = [];
                foreach ((array) ($f['filhos'] ?? []) as $ch) {
                    $cid = (int) ($ch['id'] ?? 0);
                    if ($cid <= 0) {
                        continue;
                    }
                    $filhos[] = [
                        'id' => $cid,
                        'nome' => trim((string) ($ch['nome'] ?? '')),
                    ];
                }
            }
            if (count($filhos) < 2) {
                continue;
            }
            $out[] = [
                'pai_id' => $paiId,
                'nome' => (string) ($f['nome'] ?? ''),
                'filhos' => $filhos,
                'materias_ids' => array_map(static fn ($ch) => (int) $ch['id'], $filhos),
            ];
        }
        return $out;
    }

    /**
     * @param mixed $raw
     */
    private function normalizarAplicarEmGrupoLinha($raw, string $padraoAusente = 'boletim'): string
    {
        $v = strtolower(trim((string) $raw));
        if ($v === 'boletim' || $v === 'ambos') {
            return $v;
        }
        return $padraoAusente === 'ambos' ? 'ambos' : 'boletim';
    }

    /**
     * @param mixed $raw
     */
    private function normalizarFinalidade($raw): string
    {
        return strtolower(trim((string) $raw)) === 'complementar' ? 'complementar' : 'oficial';
    }

    /**
     * @param mixed $raw
     * @return array{ativo:bool,nome:string,modo:string,modos:array<string,string>,materias_ids:list<int>,aplicar_em:string,agrupamento_id:int,arredondamento:string}
     */
    private function normalizarGrupoLinha($raw): array
    {
        $base = self::grupoLinhaPadrao();
        if (!is_array($raw)) {
            return $base;
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($raw['materias_ids'] ?? [])),
            static fn ($id) => $id > 0
        )));
        $modo = strtolower(trim((string) ($raw['modo'] ?? 'media')));
        $aplicarEm = array_key_exists('aplicar_em', $raw)
            ? $this->normalizarAplicarEmGrupoLinha($raw['aplicar_em'], 'ambos')
            : 'boletim';
        $arred = strtolower(trim((string) ($raw['arredondamento'] ?? 'todos')));
        if ($arred === 'mãe') {
            $arred = 'mae';
        }
        if (!in_array($arred, ['todos', 'filhas', 'mae'], true)) {
            $arred = 'todos';
        }
        return [
            'ativo' => !empty($raw['ativo']),
            'nome' => trim((string) ($raw['nome'] ?? '')),
            'modo' => $modo === 'soma' ? 'soma' : 'media',
            'modos' => $this->normalizarModosGrupoLinha($raw['modos'] ?? null),
            'materias_ids' => $ids,
            'aplicar_em' => $aplicarEm,
            'agrupamento_id' => max(0, (int) ($raw['agrupamento_id'] ?? 0)),
            'arredondamento' => $arred,
            'exemplo_sem_filhas' => !empty($raw['exemplo_sem_filhas']),
        ];
    }

    /**
     * Média ou soma por peça (semanal, bimestral, jornada). Vazio = usa o modo geral.
     *
     * @param mixed $raw
     * @return array<string,string>
     */
    private function normalizarModosGrupoLinha($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $peca => $modo) {
            $peca = strtolower(trim((string) $peca));
            if ($peca === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $peca)) {
                continue;
            }
            $out[$peca] = strtolower(trim((string) $modo)) === 'soma' ? 'soma' : 'media';
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    /**
     * Peça da linha única a partir do código da coluna (semanas e média semanal = semanal).
     *
     * @param array<string,mixed> $componente
     */
    private function pecaGrupoDoComponente(array $componente): string
    {
        $cod = strtolower(trim((string) ($componente['codigo'] ?? '')));
        if ($cod === 'media_sem' || $cod === 'semanal' || (bool) preg_match('/^s[1-8]$/', $cod)) {
            return 'semanal';
        }
        $canon = match ($cod) {
            'bimestral', 'prova_bim' => 'bimestral',
            'jornada' => 'jornada',
            'enac' => 'enac',
            'trab', 'trabalho' => 'trabalho',
            'part', 'participacao' => 'participacao',
            'rec', 'recuperacao' => 'recuperacao',
            default => '',
        };
        if ($canon !== '') {
            return $canon;
        }
        if (in_array($cod, ['media_bim', 'media_final', 'media_parcial', 'media_etapa', 'faltas', 'resultado'], true)) {
            return '';
        }
        if ((bool) preg_match('/^media_[ms]\d+$/', $cod)) {
            return '';
        }
        if ((bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $cod)) {
            return $cod;
        }
        return '';
    }

    /**
     * @param array<string,mixed> $estado
     * @return array{enabled:bool,key:string,label:string,mode:string,divisor:float,materias_ids:list<int>,aplicar_em:string,arredondamento:string,agrupamento_id?:int}|null
     */
    private function configGrupoLinha(array $estado): ?array
    {
        $g = $this->completarGrupoLinhaComFamilia(
            $this->normalizarGrupoLinha($estado['grupo_linha'] ?? null),
            $estado
        );
        if (!$g['ativo']) {
            return null;
        }
        $ids = $g['materias_ids'];
        $label = $g['nome'];
        $modo = $g['modo'];
        $aplicarEm = $g['aplicar_em'];
        $idsDoEstado = array_values(array_unique(array_filter(
            array_map('intval', (array) $ids),
            static fn ($id) => $id > 0
        )));
        if ($g['agrupamento_id'] > 0) {
            $encontrado = null;
            foreach ($this->catalogoAgrupamentos() as $ag) {
                if ((int) ($ag['id'] ?? 0) === $g['agrupamento_id']) {
                    $encontrado = $ag;
                    break;
                }
            }
            if ($encontrado === null) {
                return null;
            }
            if ($label === '') {
                $label = (string) ($encontrado['nome'] ?? '');
            }
            // Catálogo é o padrão; se o evento já tem lista editada (incluir/excluir filha), respeita.
            if (count($idsDoEstado) >= 2) {
                $ids = $idsDoEstado;
            } else {
                $ids = array_values(array_map('intval', (array) ($encontrado['materias_ids'] ?? [])));
            }
        }
        if ($label === '' || count($ids) < 2) {
            return null;
        }
        $out = [
            'enabled' => true,
            'key' => $this->slugGrupo($label),
            'label' => $label,
            'mode' => $modo,
            'divisor' => 1.0,
            'materias_ids' => $ids,
            'aplicar_em' => $aplicarEm,
            'arredondamento' => $g['arredondamento'] ?? 'todos',
            'ocultar_filhas' => !empty($g['exemplo_sem_filhas']),
        ];
        if ($g['agrupamento_id'] > 0) {
            $out['agrupamento_id'] = $g['agrupamento_id'];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarMateriaUnicaNoRascunho(array $rascunho, array $estado): array
    {
        $flag = !empty($estado['materia_unica']) ? 1 : 0;
        $modo = $this->normalizarMateriaUnicaModo($estado['materia_unica_modo'] ?? 'soma');
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            if ((string) ($c['source_type'] ?? '') === 'calculado') {
                continue;
            }
            $c['materia_unica'] = $flag;
            $c['materia_unica_modo'] = $modo;
            $comps[$i] = $c;
        }
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function aplicarMateriaUnicaNasPecasOpcoes(array &$estado): void
    {
        $flag = !empty($estado['materia_unica']) ? 1 : 0;
        $modo = $this->normalizarMateriaUnicaModo($estado['materia_unica_modo'] ?? 'soma');
        $opcoes = is_array($estado['pecas_opcoes'] ?? null) ? $estado['pecas_opcoes'] : [];
        foreach ($opcoes as $k => $opts) {
            if (!is_array($opts)) {
                $opts = [];
            }
            $opts['materia_unica'] = $flag;
            $opts['materia_unica_modo'] = $modo;
            $opcoes[$k] = $opts;
        }
        $estado['pecas_opcoes'] = $opcoes;
    }

    /**
     * @param list<array<string,mixed>> $comps
     */
    private function inferirMateriaUnicaDeComponentes(array $comps): int
    {
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ((string) ($c['source_type'] ?? '') === 'calculado') {
                continue;
            }
            if (!empty($c['materia_unica'])) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * @param list<array<string,mixed>> $comps
     */
    private function inferirMateriaUnicaModoDeComponentes(array $comps): string
    {
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ((string) ($c['source_type'] ?? '') === 'calculado') {
                continue;
            }
            if (!empty($c['materia_unica'])) {
                return $this->normalizarMateriaUnicaModo($c['materia_unica_modo'] ?? 'soma');
            }
        }
        return '';
    }

    /**
     * @param mixed $modo
     */
    private function normalizarMateriaUnicaModo($modo): string
    {
        return strtolower(trim((string) $modo)) === 'media' ? 'media' : 'soma';
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    private function rascunhoTemMateriaUnica(array $rascunho): bool
    {
        return $this->inferirMateriaUnicaDeComponentes(
            is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : []
        ) === 1;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarGrupoLinhaNoRascunho(array $rascunho, array $estado): array
    {
        $gl = $this->configGrupoLinha($estado);
        $modos = $this->normalizarModosGrupoLinha($estado['grupo_linha']['modos'] ?? null);
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($gl !== null) {
                $cfgGl = $gl;
                $peca = $this->pecaGrupoDoComponente($c);
                $cfgGl['mode'] = ($peca !== '' && isset($modos[$peca]))
                    ? $modos[$peca]
                    : (string) ($gl['mode'] ?? 'media');
                $cfgGl['modo_padrao'] = (string) ($gl['mode'] ?? 'media');
                if ($modos !== []) {
                    $cfgGl['modos'] = $modos;
                }
                $cfg['group_line'] = $cfgGl;
            } elseif (!$this->grupoLinhaEstaVazio($estado)) {
                unset($cfg['group_line']);
            }
            $c['config'] = $cfg;
            $comps[$i] = $c;
        }
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<array<string,mixed>> $comps
     */
    private function aplicarGrupoLinhaDoFormulario(array &$estado, array $comps): void
    {
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $gl = is_array($cfg['group_line'] ?? null) ? $cfg['group_line'] : [];
            if (empty($gl['enabled'])) {
                continue;
            }
            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array) ($gl['materias_ids'] ?? [])),
                static fn ($id) => $id > 0
            )));
            $label = trim((string) ($gl['label'] ?? $gl['key'] ?? ''));
            if ($label === '' || count($ids) < 2) {
                continue;
            }
            $modosGravados = $this->normalizarModosGrupoLinha($gl['modos'] ?? null);
            $modoPadraoGravado = strtolower(trim((string) ($gl['modo_padrao'] ?? '')));
            if ($modosGravados !== [] || $modoPadraoGravado === 'soma' || $modoPadraoGravado === 'media') {
                $estado['grupo_linha'] = [
                    'ativo' => true,
                    'nome' => $label,
                    'modo' => ($modoPadraoGravado === 'soma' || $modoPadraoGravado === 'media')
                        ? $modoPadraoGravado
                        : ((strtolower((string) ($gl['mode'] ?? 'media')) === 'soma') ? 'soma' : 'media'),
                    'modos' => $modosGravados,
                    'materias_ids' => $ids,
                    'aplicar_em' => $this->normalizarAplicarEmGrupoLinha($gl['aplicar_em'] ?? 'ambos', 'ambos'),
                    'agrupamento_id' => max(0, (int) ($gl['agrupamento_id'] ?? 0)),
                    'arredondamento' => in_array(strtolower((string) ($gl['arredondamento'] ?? 'todos')), ['filhas', 'mae'], true)
                        ? strtolower((string) $gl['arredondamento'])
                        : 'todos',
                    'exemplo_sem_filhas' => !empty($gl['ocultar_filhas']) || !empty($gl['exemplo_sem_filhas']),
                ];
                return;
            }
            $modoPorPeca = [];
            foreach ($comps as $compModo) {
                if (!is_array($compModo)) {
                    continue;
                }
                $cfgModo = is_array($compModo['config'] ?? null) ? $compModo['config'] : [];
                $glModo = is_array($cfgModo['group_line'] ?? null) ? $cfgModo['group_line'] : [];
                if (empty($glModo['enabled'])) {
                    continue;
                }
                $peca = $this->pecaGrupoDoComponente($compModo);
                if ($peca === '') {
                    continue;
                }
                $modoPorPeca[$peca] = (strtolower((string) ($glModo['mode'] ?? 'media')) === 'soma') ? 'soma' : 'media';
            }
            $votosSoma = 0;
            $votosMedia = 0;
            foreach ($modoPorPeca as $modoPeca) {
                if ($modoPeca === 'soma') {
                    $votosSoma++;
                } else {
                    $votosMedia++;
                }
            }
            $modoPadrao = $votosSoma > $votosMedia ? 'soma' : 'media';
            $modos = [];
            foreach ($modoPorPeca as $peca => $modoPeca) {
                if ($modoPeca !== $modoPadrao) {
                    $modos[$peca] = $modoPeca;
                }
            }
            $estado['grupo_linha'] = [
                'ativo' => true,
                'nome' => $label,
                'modo' => $modoPadrao,
                'modos' => $modos,
                'materias_ids' => $ids,
                'aplicar_em' => $this->normalizarAplicarEmGrupoLinha($gl['aplicar_em'] ?? 'ambos', 'ambos'),
                'agrupamento_id' => max(0, (int) ($gl['agrupamento_id'] ?? 0)),
                'arredondamento' => in_array(strtolower((string) ($gl['arredondamento'] ?? 'todos')), ['filhas', 'mae'], true)
                    ? strtolower((string) $gl['arredondamento'])
                    : 'todos',
                'exemplo_sem_filhas' => !empty($gl['ocultar_filhas']) || !empty($gl['exemplo_sem_filhas']),
            ];
            return;
        }
    }

    private function slugGrupo(string $nome): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
        ];
        $s = strtr(mb_strtolower(trim($nome)), $map);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
        $s = trim($s, '_');
        if ($s === '') {
            return 'grupo';
        }
        return substr($s, 0, 40);
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    private function resumoGrupoLinhaDoRascunho(array $rascunho): string
    {
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $gl = is_array($c['config']['group_line'] ?? null) ? $c['config']['group_line'] : [];
            if (empty($gl['enabled'])) {
                continue;
            }
            $label = trim((string) ($gl['label'] ?? ''));
            $n = count((array) ($gl['materias_ids'] ?? []));
            $modo = ((string) ($gl['mode'] ?? 'media')) === 'soma' ? 'soma' : 'média';
            if ($label === '' || $n < 2) {
                continue;
            }
            $onde = $this->normalizarAplicarEmGrupoLinha($gl['aplicar_em'] ?? 'ambos', 'ambos') === 'boletim'
                ? 'só no boletim'
                : 'no boletim e nas notas';
            return 'Linha única: “' . $label . '” (' . $n . ' matérias, ' . $modo . ', ' . $onde . ').';
        }
        return '';
    }

    /**
     * Período da nota mais as recuperações ligadas a ele: 61 no 1º, 81 no par e 90 na final.
     *
     * @return list<int>
     */
    private function codigosPeriodoDaNota(int $bimestre): array
    {
        if ($bimestre < 1) {
            return [];
        }
        $codigos = [$bimestre];
        if ($bimestre <= 12) {
            $codigos[] = 60 + $bimestre;
        }
        if ($bimestre <= 4) {
            $codigos[] = 80 + (int) ceil($bimestre / 2);
            $codigos[] = 90;
        }
        return array_values(array_unique($codigos));
    }

    /**
     * @param array<string,mixed> $ev
     * @param array<string,mixed> $estado
     */
    private function eventoCabeNoPeriodoDaNota(array $ev, array $estado): bool
    {
        $ano = (int) ($estado['ano_letivo'] ?? 0);
        if ($ano > 0 && (int) ($ev['ano_letivo'] ?? 0) !== $ano) {
            return false;
        }
        $bimestre = (int) ($estado['bimestre'] ?? 0);
        if ($bimestre <= 0) {
            return true;
        }
        return in_array((int) ($ev['bimestre'] ?? 0), $this->codigosPeriodoDaNota($bimestre), true);
    }

    /**
     * Reabre o demonstrativo com os eventos e subeventos que já estavam na configuração salva.
     *
     * @param array<string,mixed> $estado
     * @param list<array<string,mixed>> $comps
     */
    private function marcarEventosSalvosNoEstado(array &$estado, array $comps): void
    {
        if (!$this->notasBimestraisComEventoAvaliativo($estado)) {
            return;
        }
        $blocos = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            foreach ($this->idsBlocosDoComponente($c) as $id) {
                $blocos[$id] = true;
            }
        }
        if ($blocos === []) {
            return;
        }
        try {
            $eventos = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            return;
        }
        $eventosIds = [];
        $subIds = [];
        foreach ($eventos as $ev) {
            if (!is_array($ev) || !$this->eventoCabeNoPeriodoDaNota($ev, $estado)) {
                continue;
            }
            $eventoId = (int) ($ev['id'] ?? 0);
            $achou = false;
            foreach ((array) ($ev['subeventos'] ?? []) as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $subId = (int) ($sub['id'] ?? 0);
                if ($subId <= 0 || !isset($blocos[$subId])) {
                    continue;
                }
                $subIds[] = $subId;
                $achou = true;
            }
            if ($achou && $eventoId > 0) {
                $eventosIds[] = $eventoId;
            }
        }
        if ($eventosIds === []) {
            return;
        }
        $estado['eventos_ids'] = $eventosIds;
        $estado['eventos_ids_manual'] = 1;
        $estado['subeventos_ids'] = $subIds;
        $estado['subeventos_ids_manual'] = 1;
    }

    /**
     * Sem escolha manual, entra o que já está na nota e toda recuperação do período.
     * Evento sem tipo (vários simulados no mesmo card) só entra se estiver marcado.
     *
     * @param list<array<string,mixed>> $eventos
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function eventosSelecionadosDaNota(array $eventos, array $estado): array
    {
        $manual = (int) ($estado['eventos_ids_manual'] ?? 0) === 1;
        $marcados = [];
        if ($manual) {
            foreach ((array) ($estado['eventos_ids'] ?? []) as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $marcados[$id] = true;
                }
            }
        }
        $tipos = [];
        if (!$manual) {
            foreach ((array) ($estado['pecas'] ?? []) as $key) {
                $opts = (array) (($estado['pecas_opcoes'] ?? [])[$key] ?? []);
                $tipo = (int) ($opts['tipo_avaliacao_id'] ?? 0);
                if ($tipo > 0) {
                    $tipos[$tipo] = true;
                }
            }
        }
        $out = [];
        foreach ($eventos as $ev) {
            if (!is_array($ev) || !$this->eventoCabeNoPeriodoDaNota($ev, $estado)) {
                continue;
            }
            $id = (int) ($ev['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($manual) {
                if (isset($marcados[$id])) {
                    $out[] = $ev;
                }
                continue;
            }
            $bimestre = (int) ($ev['bimestre'] ?? 0);
            $tipo = (int) ($ev['tipo_avaliacao_id'] ?? 0);
            if ($bimestre >= 60 || ($tipo > 0 && isset($tipos[$tipo]))) {
                $out[] = $ev;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $c
     */
    private function componenteVemDeEventoAvaliativo(array $c): bool
    {
        $src = (string) ($c['source_type'] ?? '');
        if ($src === 'calculado' || $src === 'faltas_evento' || $src === 'jornadas') {
            return false;
        }
        if ($this->componenteEhColunaSubevento($c) || $this->componenteEhRecuperacaoExibe($c)) {
            return true;
        }
        return $src === 'provas_sistema';
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function gravarNomeColunaNoEstado(array &$estado, string $codigo, string $nome): void
    {
        $codigo = strtolower(trim($codigo));
        $nome = trim($nome);
        if ($codigo === '' || $nome === '') {
            return;
        }
        if (!is_array($estado['nomes_blocos'] ?? null)) {
            $estado['nomes_blocos'] = [];
        }
        $estado['nomes_blocos'][$codigo] = mb_substr($nome, 0, 60);
    }

    /**
     * Tira do demonstrativo a nota de evento que não foi marcado.
     * A recuperação do período permanece, e o nome da coluna passa a ser o do evento.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function restringirDemonstrativoAosEventosMarcados(array $rascunho, array &$estado): array
    {
        if (!$this->notasBimestraisComEventoAvaliativo($estado)) {
            return $rascunho;
        }
        try {
            $catalogo = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            return $rascunho;
        }
        $selecionados = $this->eventosSelecionadosDaNota($catalogo, $estado);
        $estado['eventos_ids'] = array_map(
            static fn (array $ev): int => (int) ($ev['id'] ?? 0),
            $selecionados
        );
        $escolhaManual = (int) ($estado['subeventos_ids_manual'] ?? 0) === 1;
        $subsEscolhidos = [];
        if ($escolhaManual) {
            foreach ((array) ($estado['subeventos_ids'] ?? []) as $subId) {
                $subId = (int) $subId;
                if ($subId > 0) {
                    $subsEscolhidos[$subId] = true;
                }
            }
        }
        $blocos = [];
        $tipos = [];
        $nomePorBloco = [];
        $tipoPorBloco = [];
        foreach ($selecionados as $ev) {
            $tipo = (int) ($ev['tipo_avaliacao_id'] ?? 0);
            if ($tipo > 0) {
                $tipos[$tipo] = true;
            }
            $subs = [];
            foreach ((array) ($ev['subeventos'] ?? []) as $sub) {
                if (is_array($sub)) {
                    $subs[] = $sub;
                }
            }
            $nomeEvento = trim((string) ($ev['nome'] ?? ''));
            $recuperacao = (int) ($ev['bimestre'] ?? 0) >= 60;
            foreach ($subs as $sub) {
                $id = (int) ($sub['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if ($escolhaManual && $subsEscolhidos !== [] && !isset($subsEscolhidos[$id])) {
                    continue;
                }
                $blocos[$id] = true;
                $tipoSub = (int) ($sub['tipo_avaliacao_id'] ?? 0);
                if ($tipoSub > 0) {
                    $tipos[$tipoSub] = true;
                    $tipoPorBloco[$id] = $tipoSub;
                }
                $titulo = trim((string) ($sub['titulo'] ?? ''));
                if ($recuperacao && count($subs) <= 1 && $nomeEvento !== '') {
                    $nomePorBloco[$id] = $nomeEvento;
                } elseif ($titulo !== '') {
                    $nomePorBloco[$id] = $titulo;
                } elseif ($nomeEvento !== '') {
                    $nomePorBloco[$id] = $nomeEvento;
                }
            }
        }
        $pecas = [];
        foreach ((array) ($estado['pecas'] ?? []) as $key) {
            $key = (string) $key;
            if ($key === 'jornada') {
                $pecas[] = $key;
                continue;
            }
            $opts = (array) (($estado['pecas_opcoes'] ?? [])[$key] ?? []);
            $tipo = (int) ($opts['tipo_avaliacao_id'] ?? 0);
            if ($tipo <= 0 || isset($tipos[$tipo])) {
                $pecas[] = $key;
            }
        }
        $estado['pecas'] = $pecas;

        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $linhaPorBloco = $this->linhaCabecalhoPorBloco($comps);
        $out = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (!$this->componenteVemDeEventoAvaliativo($c)) {
                $out[] = $c;
                continue;
            }
            $ids = $this->idsBlocosDoComponente($c);
            if ($ids === []) {
                $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
                $tipo = (int) (($cfg['tipo_avaliacao_id'] ?? null) ?: ($c['tipo_avaliacao_id'] ?? 0));
                if ($tipo > 0 && !isset($tipos[$tipo])) {
                    continue;
                }
                $blocoUnico = $this->blocoUnicoDoTipo($tipo, $tipoPorBloco);
                $nomeTipo = $this->nomeUnicoDoTipo($tipo, $nomePorBloco, $tipoPorBloco);
                if ($blocoUnico > 0) {
                    $c['blocos_ids'] = [$blocoUnico];
                    $c['bloco_id'] = $blocoUnico;
                }
                if ($nomeTipo !== '') {
                    $c['nome'] = $nomeTipo;
                    $this->gravarNomeColunaNoEstado($estado, (string) ($c['codigo'] ?? ''), $nomeTipo);
                }
                $out[] = $this->aplicarLinhaCabecalhoNoComponente($c, $linhaPorBloco);
                continue;
            }
            $vivos = array_values(array_filter(
                $ids,
                static fn (int $id): bool => isset($blocos[$id])
            ));
            if ($vivos === []) {
                continue;
            }
            $c['blocos_ids'] = $vivos;
            if (count($vivos) === 1 && isset($nomePorBloco[$vivos[0]]) && $nomePorBloco[$vivos[0]] !== '') {
                $c['nome'] = $nomePorBloco[$vivos[0]];
                $this->gravarNomeColunaNoEstado($estado, (string) ($c['codigo'] ?? ''), (string) $c['nome']);
            }
            $out[] = $this->aplicarLinhaCabecalhoNoComponente($c, $linhaPorBloco);
        }
        $jaTem = [];
        $codigos = [];
        foreach ($out as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '') {
                $codigos[$cod] = true;
            }
            foreach ($this->idsBlocosDoComponente($c) as $id) {
                $jaTem[$id] = true;
            }
        }
        $novos = [];
        foreach ($nomePorBloco as $blocoId => $titulo) {
            $blocoId = (int) $blocoId;
            if ($blocoId <= 0 || isset($jaTem[$blocoId])) {
                continue;
            }
            $codigo = 'av' . $blocoId;
            if (isset($codigos[$codigo])) {
                $codigo = 'av_' . $blocoId;
            }
            $codigos[$codigo] = true;
            $jaTem[$blocoId] = true;
            $tipoSub = (int) ($tipoPorBloco[$blocoId] ?? 0);
            $nomeNovo = $titulo !== '' ? $titulo : ('Sub-evento ' . $blocoId);
            $this->gravarNomeColunaNoEstado($estado, $codigo, $nomeNovo);
            $cfgNovo = [
                'papel_wizard' => 'exibe',
                'blocos_ids_manual' => 1,
                'tipo_avaliacao_id' => $tipoSub > 0 ? $tipoSub : null,
            ];
            $nomeLinha = trim((string) ($linhaPorBloco[$blocoId] ?? ''));
            if ($nomeLinha !== '') {
                $cfgNovo['linha_cabecalho'] = function_exists('mb_substr') ? mb_substr($nomeLinha, 0, 40) : substr($nomeLinha, 0, 40);
            }
            $novos[] = [
                'codigo' => $codigo,
                'nome' => $nomeNovo,
                'source_type' => 'provas_sistema',
                'calc_type' => 'ultima',
                'usar_percentual' => 0,
                'blocos_ids' => [$blocoId],
                'bloco_id' => $blocoId,
                'peso' => 1,
                'obrigatorio' => 0,
                'tipo_avaliacao_id' => $tipoSub > 0 ? $tipoSub : null,
                'config' => $cfgNovo,
            ];
        }
        if ($novos !== []) {
            $pos = count($out);
            foreach ($out as $i => $c) {
                if (is_array($c) && $this->componenteVemDeEventoAvaliativo($c)) {
                    $pos = $i + 1;
                }
            }
            array_splice($out, $pos, 0, $novos);
        }
        $rascunho['componentes'] = $out;
        return $rascunho;
    }

    /**
     * @param array<int,int> $tipoPorBloco
     */
    private function blocoUnicoDoTipo(int $tipo, array $tipoPorBloco): int
    {
        if ($tipo <= 0) {
            return 0;
        }
        $bloco = 0;
        $qtd = 0;
        foreach ($tipoPorBloco as $blocoId => $tipoBloco) {
            if ((int) $tipoBloco !== $tipo) {
                continue;
            }
            $qtd++;
            $bloco = (int) $blocoId;
        }
        return $qtd === 1 ? $bloco : 0;
    }

    /**
     * @param array<int,string> $nomePorBloco
     * @param array<int,int> $tipoPorBloco
     */
    private function nomeUnicoDoTipo(int $tipo, array $nomePorBloco, array $tipoPorBloco): string
    {
        if ($tipo <= 0) {
            return '';
        }
        $nome = '';
        $qtd = 0;
        foreach ($tipoPorBloco as $blocoId => $tipoBloco) {
            if ((int) $tipoBloco !== $tipo) {
                continue;
            }
            $qtd++;
            $nome = (string) ($nomePorBloco[(int) $blocoId] ?? '');
        }
        return $qtd === 1 ? $nome : '';
    }

    /**
     * Com eventos avaliativos, cada subevento marcado vira coluna.
     * A peça (ex.: Prova Semanal) continua sendo a média desses subeventos.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function expandirSubeventosAvaliativos(array $rascunho, array &$estado): array
    {
        if (!$this->notasBimestraisComEventoAvaliativo($estado) || $this->rascunhoJaEhQuadro($rascunho)) {
            return $rascunho;
        }
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        if ($comps === []) {
            return $rascunho;
        }

        $rotulos = $this->rotulosSubeventosDoPeriodo($estado);
        $pais = [];
        foreach ($comps as $i => $c) {
            if (!is_array($c) || !$this->componenteEhPaiDeSubeventos($c)) {
                continue;
            }
            $ids = $this->idsBlocosDoComponente($c);
            if (count($ids) < 2) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $vivos = array_values(array_filter(
                $ids,
                static fn (int $id): bool => isset($rotulos[$id])
            ));
            if ($vivos === []) {
                $vivos = $this->blocosVivosDoEvento($estado, (string) ($c['nome'] ?? ''));
                if (count($vivos) >= 2) {
                    $c['blocos_ids'] = $vivos;
                    $comps[$i] = $c;
                }
            }
            if (count($vivos) < 2) {
                continue;
            }
            $pais[$cod] = $vivos;
        }
        if ($pais === []) {
            $rascunho['componentes'] = $this->semColunasSubevento($comps);
            return $rascunho;
        }

        $idsValidos = [];
        foreach ($pais as $ids) {
            foreach ($ids as $id) {
                $idsValidos[$id] = true;
            }
        }

        $porBloco = [];
        $demais = [];
        $codigos = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($this->componenteEhColunaSubevento($c)) {
                $bid = (int) ($this->idsBlocosDoComponente($c)[0] ?? 0);
                if ($bid <= 0 || !isset($idsValidos[$bid]) || isset($porBloco[$bid])) {
                    continue;
                }
                $porBloco[$bid] = $c;
                if ($cod !== '') {
                    $codigos[$cod] = true;
                }
                continue;
            }
            $demais[] = $c;
            if ($cod !== '') {
                $codigos[$cod] = true;
            }
        }

        $out = [];
        foreach ($demais as $c) {
            $codPai = strtolower(trim((string) ($c['codigo'] ?? '')));
            $ids = $pais[$codPai] ?? null;
            if (is_array($ids)) {
                $naFrente = [];
                foreach ($this->ordenarBlocosPeloCatalogo($ids, $rotulos) as $bid) {
                    $titulo = trim((string) ($rotulos[$bid]['titulo'] ?? ''));
                    if (isset($porBloco[$bid])) {
                        $coluna = $porBloco[$bid];
                        unset($porBloco[$bid]);
                    } else {
                        $codigo = $this->codigoSubeventoLivre($bid, $codigos);
                        $codigos[$codigo] = true;
                        $coluna = $this->componenteColunaSubevento($c, $bid, $codigo, $titulo);
                    }
                    $coluna = $this->marcarColunaSubevento($coluna, $codPai, $titulo);
                    $out[] = $coluna;
                    $naFrente[] = strtolower(trim((string) ($coluna['codigo'] ?? '')));
                }
                $this->encaixarSubeventosNaOrdem($estado, $codPai, $naFrente);
            }
            $out[] = $c;
        }

        $rascunho['componentes'] = $out;
        return $rascunho;
    }

    /**
     * @param list<mixed> $comps
     * @return list<array<string,mixed>>
     */
    private function semColunasSubevento(array $comps): array
    {
        $out = [];
        foreach ($comps as $c) {
            if (!is_array($c) || $this->componenteEhColunaSubevento($c)) {
                continue;
            }
            $out[] = $c;
        }
        return $out;
    }

    /** @param array<string,mixed> $c */
    private function componenteEhRecuperacaoExibe(array $c): bool
    {
        $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
        return !empty($cfg['recuperacao_exibe']);
    }

    /** @param array<string,mixed> $c */
    private function componenteEhColunaSubevento(array $c): bool
    {
        $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
        if (!empty($cfg['subevento_avaliativo'])) {
            return true;
        }
        $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
        return (bool) preg_match('/^sev\d+$/', $cod);
    }

    /** @param array<string,mixed> $c */
    private function componenteEhPaiDeSubeventos(array $c): bool
    {
        if ($this->componenteEhColunaSubevento($c)) {
            return false;
        }
        return (string) ($c['source_type'] ?? '') === 'provas_sistema';
    }

    /**
     * @param array<string,mixed> $c
     * @return list<int>
     */
    private function idsBlocosDoComponente(array $c): array
    {
        $raw = $c['blocos_ids'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/\s*,\s*/', trim($raw)) ?: [];
        }
        return $this->normalizarIdsLista($raw);
    }

    /**
     * @param array<string,mixed> $estado
     * @return array<int,array{titulo:string,ordem:int}>
     */
    private function rotulosSubeventosDoPeriodo(array $estado): array
    {
        $ano = (int) ($estado['ano_letivo'] ?? 0);
        $bim = (int) ($estado['bimestre'] ?? 0);
        $map = [];
        try {
            $eventos = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            return [];
        }
        foreach ($eventos as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if ($ano > 0 && (int) ($ev['ano_letivo'] ?? 0) !== $ano) {
                continue;
            }
            if ($bim > 0 && (int) ($ev['bimestre'] ?? 0) !== $bim) {
                continue;
            }
            $ordem = 0;
            foreach ((array) ($ev['subeventos'] ?? []) as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $id = (int) ($sub['id'] ?? 0);
                if ($id <= 0 || isset($map[$id])) {
                    continue;
                }
                $ordem++;
                $titulo = trim((string) ($sub['titulo'] ?? ''));
                if ($titulo === '') {
                    $titulo = 'Sub-evento ' . $ordem;
                }
                $map[$id] = ['titulo' => $titulo, 'ordem' => $ordem];
            }
        }
        return $map;
    }

    /**
     * @param list<int> $ids
     * @param array<int,array{titulo:string,ordem:int}> $rotulos
     * @return list<int>
     */
    private function ordenarBlocosPeloCatalogo(array $ids, array $rotulos): array
    {
        $ids = array_values($ids);
        usort($ids, static function (int $a, int $b) use ($rotulos): int {
            $oa = (int) ($rotulos[$a]['ordem'] ?? 1000);
            $ob = (int) ($rotulos[$b]['ordem'] ?? 1000);
            if ($oa === $ob) {
                return $a <=> $b;
            }
            return $oa <=> $ob;
        });
        return $ids;
    }

    /** @param array<string,bool> $codigos */
    private function codigoSubeventoLivre(int $blocoId, array $codigos): string
    {
        $base = 'sev' . $blocoId;
        if (!isset($codigos[$base])) {
            return $base;
        }
        $alt = 'sev_' . $blocoId;
        if (!isset($codigos[$alt])) {
            return $alt;
        }
        return 'se' . $blocoId . 'b';
    }

    /**
     * @param array<string,mixed> $pai
     * @return array<string,mixed>
     */
    private function componenteColunaSubevento(array $pai, int $blocoId, string $codigo, string $titulo): array
    {
        $novo = $pai;
        $novo['codigo'] = $codigo;
        $novo['nome'] = $titulo !== '' ? $titulo : ('Sub-evento ' . $blocoId);
        $novo['blocos_ids'] = [$blocoId];
        $novo['bloco_id'] = $blocoId;
        $novo['calc_type'] = 'ultima';
        $novo['usar_percentual'] = 0;
        $novo['filtro_titulo'] = '';
        $novo['peso'] = 1;
        $novo['obrigatorio'] = 0;
        $cfg = is_array($pai['config'] ?? null) ? $pai['config'] : [];
        $cfg['papel_wizard'] = 'exibe';
        $cfg['subevento_avaliativo'] = 1;
        $cfg['blocos_ids_manual'] = 1;
        $cfg['evento_pai_codigo'] = strtolower(trim((string) ($pai['codigo'] ?? '')));
        unset($cfg['expressao'], $cfg['agregar_nq']);
        $novo['config'] = $cfg;
        return $novo;
    }

    /**
     * @param array<string,mixed> $coluna
     * @return array<string,mixed>
     */
    private function marcarColunaSubevento(array $coluna, string $codPai, string $titulo): array
    {
        if ($titulo !== '') {
            $coluna['nome'] = $titulo;
        }
        $cfg = is_array($coluna['config'] ?? null) ? $coluna['config'] : [];
        $cfg['papel_wizard'] = 'exibe';
        $cfg['subevento_avaliativo'] = 1;
        $cfg['blocos_ids_manual'] = 1;
        $cfg['evento_pai_codigo'] = strtolower(trim($codPai));
        $coluna['config'] = $cfg;
        return $coluna;
    }

    /**
     * Sub-eventos atuais de um evento do período, quando o modelo ainda aponta para blocos apagados.
     *
     * @param array<string,mixed> $estado
     * @return list<int>
     */
    private function blocosVivosDoEvento(array $estado, string $nomeEvento): array
    {
        $nomeEvento = mb_strtolower(trim($nomeEvento));
        if ($nomeEvento === '') {
            return [];
        }
        $ano = (int) ($estado['ano_letivo'] ?? 0);
        $bim = (int) ($estado['bimestre'] ?? 0);
        try {
            $eventos = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            return [];
        }
        foreach ($eventos as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if ($ano > 0 && (int) ($ev['ano_letivo'] ?? 0) !== $ano) {
                continue;
            }
            if ($bim > 0 && (int) ($ev['bimestre'] ?? 0) !== $bim) {
                continue;
            }
            if (mb_strtolower(trim((string) ($ev['nome'] ?? ''))) !== $nomeEvento) {
                continue;
            }
            $ids = [];
            foreach ((array) ($ev['subeventos'] ?? []) as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $id = (int) ($sub['id'] ?? 0);
                if ($id > 0 && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
            return $ids;
        }
        return [];
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<string> $codigosNovos
     */
    private function encaixarSubeventosNaOrdem(array &$estado, string $paiCodigo, array $codigosNovos): void
    {
        $ordem = [];
        foreach ((array) ($estado['colunas_ordem'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '' && !in_array($cod, $ordem, true)) {
                $ordem[] = $cod;
            }
        }
        if ($ordem === []) {
            return;
        }
        $inserir = [];
        foreach ($codigosNovos as $cod) {
            $cod = strtolower(trim($cod));
            if ($cod !== '' && !in_array($cod, $ordem, true)) {
                $inserir[] = $cod;
            }
        }
        if ($inserir === []) {
            return;
        }
        $idx = array_search($paiCodigo, $ordem, true);
        if ($idx === false) {
            $estado['colunas_ordem'] = array_merge($inserir, $ordem);
            return;
        }
        array_splice($ordem, (int) $idx, 0, $inserir);
        $estado['colunas_ordem'] = $ordem;
    }

    /**
     * Colunas da recuperação do semestre e da final. Só aparecem; não entram na média do bimestre.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function anexarRecuperacoesNoDemonstrativo(array $rascunho, array &$estado): array
    {
        if (!$this->notasBimestraisComEventoAvaliativo($estado) || $this->rascunhoJaEhQuadro($rascunho)) {
            return $rascunho;
        }
        $ano = (int) ($estado['ano_letivo'] ?? 0);
        $bimestre = (int) ($estado['bimestre'] ?? 0);
        if ($ano <= 0 || $bimestre < 1 || $bimestre > 12) {
            return $rascunho;
        }
        $periodos = array_values(array_filter(
            $this->codigosPeriodoDaNota($bimestre),
            static fn (int $codigo): bool => $codigo >= 60
        ));
        try {
            $eventos = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            return $rascunho;
        }

        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $blocosJa = [];
        $codigos = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '') {
                $codigos[$cod] = true;
            }
            foreach ($this->idsBlocosDoComponente($c) as $id) {
                $blocosJa[$id] = true;
            }
        }

        $novos = [];
        $selecionados = [];
        foreach ($this->eventosSelecionadosDaNota($eventos, $estado) as $escolhido) {
            $selecionados[(int) ($escolhido['id'] ?? 0)] = true;
        }
        foreach ($eventos as $ev) {
            if (!is_array($ev) || (int) ($ev['ano_letivo'] ?? 0) !== $ano) {
                continue;
            }
            if (!isset($selecionados[(int) ($ev['id'] ?? 0)])) {
                continue;
            }
            if (!in_array((int) ($ev['bimestre'] ?? 0), $periodos, true)) {
                continue;
            }
            foreach ((array) ($ev['subeventos'] ?? []) as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $blocoId = (int) ($sub['id'] ?? 0);
                if ($blocoId <= 0 || isset($blocosJa[$blocoId])) {
                    continue;
                }
                $blocosJa[$blocoId] = true;
                $codigo = 'rcv' . $blocoId;
                if (isset($codigos[$codigo])) {
                    $codigo = 'rcv_' . $blocoId;
                }
                $codigos[$codigo] = true;
                $titulo = trim((string) ($sub['titulo'] ?? ''));
                if ($titulo === '') {
                    $titulo = trim((string) ($ev['nome'] ?? 'Recuperação'));
                }
                $novos[] = [
                    'codigo' => $codigo,
                    'nome' => $titulo,
                    'source_type' => 'provas_sistema',
                    'calc_type' => 'ultima',
                    'usar_percentual' => 0,
                    'blocos_ids' => [$blocoId],
                    'bloco_id' => $blocoId,
                    'peso' => 0,
                    'obrigatorio' => 0,
                    'config' => [
                        'papel_wizard' => 'exibe',
                        'recuperacao_exibe' => 1,
                        'layout_type' => 'rec',
                        'layout_group' => 'quadro_comum',
                        'blocos_ids_manual' => 1,
                    ],
                ];
            }
        }
        if ($novos === []) {
            return $rascunho;
        }

        $out = [];
        $inseriu = false;
        foreach ($comps as $c) {
            $cod = is_array($c) ? strtolower(trim((string) ($c['codigo'] ?? ''))) : '';
            if (!$inseriu && $cod === 'media_final') {
                foreach ($novos as $novo) {
                    $out[] = $novo;
                }
                $inseriu = true;
            }
            $out[] = $c;
        }
        if (!$inseriu) {
            foreach ($novos as $novo) {
                $out[] = $novo;
            }
        }
        $rascunho['componentes'] = $out;

        $ordem = [];
        foreach ((array) ($estado['colunas_ordem'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '' && !in_array($cod, $ordem, true)) {
                $ordem[] = $cod;
            }
        }
        $inserir = [];
        foreach ($novos as $novo) {
            $cod = strtolower((string) $novo['codigo']);
            if (!in_array($cod, $ordem, true)) {
                $inserir[] = $cod;
            }
        }
        if ($inserir !== []) {
            $idx = array_search('media_final', $ordem, true);
            if ($idx === false) {
                $ordem = array_merge($ordem, $inserir);
            } else {
                array_splice($ordem, (int) $idx, 0, $inserir);
            }
            $estado['colunas_ordem'] = $ordem;
        }

        return $rascunho;
    }

    /**
     * Aplica ordem das colunas e gera preview fictício (exemplo visual).
     *
     * @param array<string,mixed> $resultado
     * @return array<string,mixed>
     */
    /**
     * @param list<array<string,mixed>> $comps
     * @return array<int,string>
     */
    private function linhaCabecalhoPorBloco(array $comps): array
    {
        $porBloco = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $nome = trim((string) ($this->configDoComponente($c)['linha_cabecalho'] ?? ''));
            if ($nome === '') {
                continue;
            }
            if (function_exists('mb_substr')) {
                $nome = mb_substr($nome, 0, 40);
            } else {
                $nome = substr($nome, 0, 40);
            }
            foreach ($this->idsBlocosDoComponente($c) as $id) {
                if ($id > 0) {
                    $porBloco[$id] = $nome;
                }
            }
        }

        return $porBloco;
    }

    /**
     * @param array<string,mixed> $c
     * @param array<int,string> $linhaPorBloco
     * @return array<string,mixed>
     */
    private function aplicarLinhaCabecalhoNoComponente(array $c, array $linhaPorBloco): array
    {
        $cfg = $this->configDoComponente($c);
        $nome = trim((string) ($cfg['linha_cabecalho'] ?? ''));
        if ($nome === '') {
            foreach ($this->idsBlocosDoComponente($c) as $id) {
                $herdado = trim((string) ($linhaPorBloco[$id] ?? ''));
                if ($herdado !== '') {
                    $nome = $herdado;
                    break;
                }
            }
        }
        if ($nome === '') {
            return $c;
        }
        $cfg['linha_cabecalho'] = function_exists('mb_substr') ? mb_substr($nome, 0, 40) : substr($nome, 0, 40);
        $c['config'] = $cfg;

        return $c;
    }

    /**
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    private function configDoComponente(array $c): array
    {
        if (is_array($c['config'] ?? null)) {
            return $c['config'];
        }
        if (is_string($c['config_json'] ?? null) && trim($c['config_json']) !== '') {
            $decoded = json_decode($c['config_json'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * A faixa de cima segue o componente, não só o código que estava na tela na hora de criar.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function sincronizarLinhasLayout(array $rascunho, array &$estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        // Admin limpou/editou cabeçalhos: layout do estado manda; não recria "Avaliações".
        if (!empty($estado['cabecalhos_manuais'])) {
            $linhasManuais = $this->normalizarLinhasLayout($estado['linhas_layout'] ?? []);
            if ($linhasManuais === []) {
                // Não apaga o que já está nos componentes quando o estado veio sem linhas.
                $linhasManuais = $this->linhasLayoutDeComponentes($comps);
            }
            $estado['linhas_layout'] = $linhasManuais;
            if ($linhasManuais !== []) {
                $rascunho['componentes'] = $this->aplicarLinhasLayoutNosComponentes($comps, $linhasManuais);
            }
            $rascunho['linhas_layout'] = $linhasManuais;

            return $rascunho;
        }

        $porCodigo = [];
        foreach ($this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) as $linha) {
            $nome = trim((string) ($linha['nome'] ?? ''));
            foreach ((array) ($linha['colunas'] ?? []) as $cod) {
                $cod = strtolower(trim((string) $cod));
                if ($cod !== '' && $nome !== '') {
                    $porCodigo[$cod] = $nome;
                }
            }
        }
        foreach ($comps as &$c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || !isset($porCodigo[$cod])) {
                continue;
            }
            $cfg = $this->configDoComponente($c);
            if (trim((string) ($cfg['linha_cabecalho'] ?? '')) === '') {
                $cfg['linha_cabecalho'] = $porCodigo[$cod];
                $c['config'] = $cfg;
            }
        }
        unset($c);

        $grupos = [];
        $ordem = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $nome = trim((string) ($this->configDoComponente($c)['linha_cabecalho'] ?? ''));
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($nome === '' || $cod === '') {
                continue;
            }
            if (!isset($grupos[$nome])) {
                $grupos[$nome] = [];
                $ordem[] = $nome;
            }
            if (!in_array($cod, $grupos[$nome], true)) {
                $grupos[$nome][] = $cod;
            }
        }
        $linhas = [];
        foreach ($ordem as $nome) {
            $cols = $grupos[$nome];
            if (count($cols) < 1) {
                continue;
            }
            $linhas[] = [
                'id' => 'lin' . substr(sha1($nome), 0, 8),
                'nome' => $nome,
                'colunas' => $cols,
            ];
        }
        $codigosReligados = [];
        foreach ($linhas as $linha) {
            foreach ((array) ($linha['colunas'] ?? []) as $cod) {
                $codigosReligados[strtolower(trim((string) $cod))] = true;
            }
        }
        foreach ($this->normalizarLinhasLayout($estado['linhas_layout'] ?? []) as $antiga) {
            $colunas = (array) ($antiga['colunas'] ?? []);
            $religou = false;
            foreach ($colunas as $cod) {
                if (isset($codigosReligados[strtolower(trim((string) $cod))])) {
                    $religou = true;
                    break;
                }
            }
            if ($religou) {
                continue;
            }
            $linhas[] = $antiga;
        }
        $linhas = $this->normalizarLinhasLayout($linhas);
        if ($linhas !== []) {
            $estado['linhas_layout'] = $linhas;
        }
        $rascunho['componentes'] = $comps;

        return $rascunho;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @param list<string> $codigos
     * @param array<string,mixed> $estado
     */
    private function linhaCabecalhoDosCodigos(array $comps, array $codigos, array $estado = []): string
    {
        $aceitos = array_fill_keys($codigos, true);
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || !isset($aceitos[$cod])) {
                continue;
            }
            $nome = trim((string) ($this->configDoComponente($c)['linha_cabecalho'] ?? ''));
            if ($nome !== '') {
                return function_exists('mb_substr') ? mb_substr($nome, 0, 40) : substr($nome, 0, 40);
            }
        }
        foreach ((array) ($estado['linhas_layout'] ?? []) as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $nome = trim((string) ($linha['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            foreach ((array) ($linha['colunas'] ?? []) as $cod) {
                if (isset($aceitos[strtolower(trim((string) $cod))])) {
                    return function_exists('mb_substr') ? mb_substr($nome, 0, 40) : substr($nome, 0, 40);
                }
            }
        }

        return '';
    }

    /**
     * Média nova dos subeventos nasce logo depois deles.
     * Se a coluna já estava na ordem enviada, o arraste do usuário permanece.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @param array<string,bool> $jaPosicionadas
     * @return array<string,mixed>
     */
    private function encostarMediaNosSubeventos(array $rascunho, array &$estado, array $jaPosicionadas = []): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $ordem = [];
        foreach ((array) ($estado['colunas_ordem'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $ordem[] = $cod;
            }
        }
        $mudou = false;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = $this->configDoComponente($c);
            if (empty($cfg['media_subeventos'])) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $fontes = [];
            foreach ((array) ($cfg['codigos_subeventos'] ?? []) as $fonte) {
                $fonte = strtolower(trim((string) $fonte));
                if ($fonte !== '') {
                    $fontes[] = $fonte;
                }
            }
            if ($cod === '' || $fontes === []) {
                continue;
            }
            $linha = $this->linhaCabecalhoDosCodigos($comps, $fontes, $estado);
            if ($linha !== '' && trim((string) ($cfg['linha_cabecalho'] ?? '')) === '') {
                $cfg['linha_cabecalho'] = $linha;
                foreach ($comps as &$cLinha) {
                    if (!is_array($cLinha)) {
                        continue;
                    }
                    if (strtolower(trim((string) ($cLinha['codigo'] ?? ''))) !== $cod) {
                        continue;
                    }
                    $cLinha['config'] = $cfg;
                }
                unset($cLinha);
            }
            $ultimoIdx = -1;
            foreach ($fontes as $fonte) {
                $idx = array_search($fonte, $ordem, true);
                if ($idx !== false) {
                    $ultimoIdx = max($ultimoIdx, (int) $idx);
                }
            }
            if ($ultimoIdx < 0 || isset($jaPosicionadas[$cod])) {
                continue;
            }
            $atual = array_search($cod, $ordem, true);
            if ($atual !== false) {
                array_splice($ordem, (int) $atual, 1);
                if ((int) $atual < $ultimoIdx) {
                    $ultimoIdx--;
                }
            }
            array_splice($ordem, $ultimoIdx + 1, 0, [$cod]);
            $mudou = true;
        }
        $rascunho['componentes'] = $comps;
        if (!$mudou) {
            return $rascunho;
        }
        $estado['colunas_ordem'] = $ordem;

        return $this->aplicarOrdemColunas($rascunho, $estado);
    }

    /**
     * Coluna com a média (ou o critério do evento) dos subeventos marcados, logo depois deles.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function anexarMediaDosSubeventos(array $rascunho, array &$estado): array
    {
        if (!$this->notasBimestraisComEventoAvaliativo($estado) || $this->rascunhoJaEhQuadro($rascunho)) {
            return $rascunho;
        }
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $grupos = $this->gruposSubeventosNoRascunho($comps);
        if ($grupos === []) {
            return $rascunho;
        }
        $codigos = [];
        $nomesUsados = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '') {
                $codigos[$cod] = true;
            }
            $nomesUsados[mb_strtolower(trim((string) ($c['nome'] ?? '')))] = true;
        }
        $ordem = [];
        foreach ((array) ($estado['colunas_ordem'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod !== '') {
                $ordem[] = $cod;
            }
        }
        foreach ($grupos as $grupo) {
            $cols = $grupo['codigos'];
            if (count($cols) < 2) {
                continue;
            }
            $codigo = 'msub' . (int) $grupo['evento_id'];
            $exp = $this->expressaoDoCriterioDosSubeventos($cols, (string) $grupo['criterio']);
            if ($exp === '') {
                continue;
            }
            $nome = 'Média dos subeventos';
            if (count($grupos) > 1) {
                $nomeEvento = trim((string) $grupo['nome']);
                $nome = $nomeEvento !== '' ? ('Média ' . $nomeEvento) : $nome;
                if (function_exists('mb_substr')) {
                    $nome = mb_substr($nome, 0, 60);
                }
            }
            $linha = $this->linhaCabecalhoDosCodigos($comps, $cols, $estado);
            $cfgMedia = [
                'expressao' => $exp,
                'formula_mode' => 'single',
                'papel_wizard' => 'exibe',
                'layout_type' => 'media',
                'layout_group' => 'quadro_comum',
                'media_subeventos' => 1,
                'evento_avaliativo_id' => (int) $grupo['evento_id'],
                'codigos_subeventos' => $cols,
            ];
            if ($linha !== '') {
                $cfgMedia['linha_cabecalho'] = $linha;
            }
            if (isset($codigos[$codigo])) {
                foreach ($comps as &$cExistente) {
                    if (!is_array($cExistente)) {
                        continue;
                    }
                    if (strtolower(trim((string) ($cExistente['codigo'] ?? ''))) !== $codigo) {
                        continue;
                    }
                    $cfgAtual = $this->configDoComponente($cExistente);
                    $cfgAtual['media_subeventos'] = 1;
                    $cfgAtual['codigos_subeventos'] = $cols;
                    if (trim((string) ($cfgAtual['linha_cabecalho'] ?? '')) === '' && $linha !== '') {
                        $cfgAtual['linha_cabecalho'] = $linha;
                    }
                    $cExistente['config'] = $cfgAtual;
                }
                unset($cExistente);
                continue;
            }
            if ($this->expressaoJaExiste($comps, $exp)) {
                continue;
            }
            $novo = [
                'codigo' => $codigo,
                'nome' => $nome,
                'source_type' => 'calculado',
                'calc_type' => 'media',
                'usar_percentual' => 0,
                'blocos_ids' => [],
                'peso' => 0,
                'obrigatorio' => 0,
                'config' => $cfgMedia,
            ];
            $pos = 0;
            $ultimo = $cols[count($cols) - 1];
            foreach ($comps as $i => $c) {
                if (!is_array($c)) {
                    continue;
                }
                if (strtolower(trim((string) ($c['codigo'] ?? ''))) === $ultimo) {
                    $pos = $i + 1;
                }
            }
            array_splice($comps, $pos, 0, [$novo]);
            $codigos[$codigo] = true;
            $idx = array_search($ultimo, $ordem, true);
            if ($idx === false) {
                $ordem[] = $codigo;
            } else {
                array_splice($ordem, (int) $idx + 1, 0, [$codigo]);
            }
            $this->gravarNomeColunaNoEstado($estado, $codigo, $nome);
        }
        $estado['colunas_ordem'] = $ordem;
        $rascunho['componentes'] = $comps;

        return $rascunho;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return list<array{evento_id:int,nome:string,criterio:string,codigos:list<string>}>
     */
    private function gruposSubeventosNoRascunho(array $comps): array
    {
        try {
            $eventos = $this->ferramentas->listarEventosAvaliativosParaBoletim();
        } catch (\Throwable $e) {
            return [];
        }
        $porBloco = [];
        $porTipo = [];
        $porNome = [];
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'provas_sistema') {
                continue;
            }
            $cfg = $this->configDoComponente($c);
            if (!empty($cfg['recuperacao_exibe']) || !empty($cfg['media_subeventos']) || strtolower((string) ($cfg['layout_type'] ?? '')) === 'faltas') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            foreach ($this->idsBlocosDoComponente($c) as $blocoId) {
                if ($blocoId > 0 && !isset($porBloco[$blocoId])) {
                    $porBloco[$blocoId] = $cod;
                }
            }
            $tipo = (int) (($cfg['tipo_avaliacao_id'] ?? null) ?: ($c['tipo_avaliacao_id'] ?? 0));
            if ($tipo > 0 && !isset($porTipo[$tipo])) {
                $porTipo[$tipo] = $cod;
            }
            $nome = mb_strtolower(trim((string) ($c['nome'] ?? '')));
            if ($nome !== '' && !isset($porNome[$nome])) {
                $porNome[$nome] = $cod;
            }
        }
        $out = [];
        foreach ($eventos as $ev) {
            if (!is_array($ev) || (int) ($ev['bimestre'] ?? 0) >= 60) {
                continue;
            }
            $codigos = [];
            foreach ((array) ($ev['subeventos'] ?? []) as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $blocoId = (int) ($sub['id'] ?? 0);
                $tipoSub = (int) ($sub['tipo_avaliacao_id'] ?? 0);
                $titulo = mb_strtolower(trim((string) ($sub['titulo'] ?? '')));
                $cod = $porBloco[$blocoId] ?? '';
                if ($cod === '' && $tipoSub > 0) {
                    $cod = $porTipo[$tipoSub] ?? '';
                }
                if ($cod === '' && $titulo !== '') {
                    $cod = $porNome[$titulo] ?? '';
                }
                if ($cod !== '' && !in_array($cod, $codigos, true)) {
                    $codigos[] = $cod;
                }
            }
            if (count($codigos) < 2) {
                continue;
            }
            $out[] = [
                'evento_id' => (int) ($ev['id'] ?? 0),
                'nome' => trim((string) ($ev['nome'] ?? '')),
                'criterio' => strtolower(trim((string) ($ev['criterio_fechamento'] ?? ''))),
                'codigos' => $codigos,
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $codigos
     */
    private function expressaoDoCriterioDosSubeventos(array $codigos, string $criterio): string
    {
        $codigos = array_values(array_filter($codigos, static fn (string $cod): bool => $cod !== ''));
        if (count($codigos) < 2) {
            return '';
        }
        if ($criterio === 'soma') {
            return implode(' + ', $codigos);
        }
        if ($criterio === 'maior') {
            $exp = $codigos[0];
            for ($i = 1, $n = count($codigos); $i < $n; $i++) {
                $exp = 'max(' . $exp . ', ' . $codigos[$i] . ')';
            }
            return $exp;
        }

        return '(' . implode(' + ', $codigos) . ') / ' . count($codigos);
    }

    /**
     * @param list<array<string,mixed>> $comps
     */
    private function expressaoJaExiste(array $comps, string $exp): bool
    {
        $exp = strtolower(preg_replace('/\s+/', '', $exp) ?? $exp);
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $atual = strtolower(trim((string) ($this->configDoComponente($c)['expressao'] ?? '')));
            $atual = strtolower(preg_replace('/\s+/', '', $atual) ?? $atual);
            if ($atual !== '' && $atual === $exp) {
                return true;
            }
        }

        return false;
    }

    /**
     * sim1, sim2, sim3 seguem a ordem dos subeventos do mesmo evento. prova_pos vira a prova quando não há recuperação.
     *
     * @param list<array<string,mixed>> $comps
     * @param array<string,bool> $codigos
     * @param array<string,string> $nomes
     * @param list<string> $livres
     */
    private function ligarSimuladosEProvaPos(
        string $expr,
        array $comps,
        array $codigos,
        array $nomes,
        array $livres,
        bool $temRecuperacao
    ): string {
        if (!preg_match_all('/\b[a-z_][a-z0-9_]*\b/', $expr, $achados)) {
            return $expr;
        }
        $sims = [];
        foreach ($achados[0] as $tok) {
            if (preg_match('/^sim(\d+)$/', $tok, $m) && !$this->tokenApontaParaColunaViva($tok, $codigos)) {
                $sims[(int) $m[1]] = $tok;
            }
        }
        if ($sims !== []) {
            ksort($sims);
            $grupo = null;
            foreach ($this->gruposSubeventosNoRascunho($comps) as $candidato) {
                if (count($candidato['codigos']) === count($sims)) {
                    $grupo = $candidato;
                    break;
                }
            }
            if ($grupo !== null) {
                $cols = array_values($grupo['codigos']);
                $i = 0;
                foreach ($sims as $tok) {
                    $destino = $cols[$i] ?? '';
                    $i++;
                    if ($destino === '' || $destino === $tok) {
                        continue;
                    }
                    $expr = (string) preg_replace('/\b' . preg_quote($tok, '/') . '\b/', $destino, $expr);
                }
            }
        }
        if (!$temRecuperacao && preg_match('/\bprova_pos\b/', $expr) && !isset($codigos['prova_pos'])) {
            $destino = isset($codigos['prova']) ? 'prova' : $this->colunaLivreParaToken('prova', $livres, $nomes);
            if ($destino !== null && $destino !== '') {
                $expr = (string) preg_replace('/\bprova_pos\b/', $destino, $expr);
            }
        }

        return $expr;
    }

    /**
     * A média cita um código que não é mais coluna (a prova ficou de fora e entrou como zero).
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function religarExpressoesNasColunasVivas(array $rascunho, array &$estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $codigos = [];
        $nomes = [];
        $livres = [];
        $recuperacoes = [];
        $temRecuperacao = false;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $codigos[$cod] = true;
            $nomes[$cod] = mb_strtolower(trim((string) ($c['nome'] ?? $cod)));
            $src = (string) ($c['source_type'] ?? '');
            $cfg = $this->configDoComponente($c);
            $tipoLayout = strtolower(trim((string) ($cfg['layout_type'] ?? '')));
            if ($src !== 'provas_sistema' || $tipoLayout === 'faltas') {
                continue;
            }
            if (!empty($cfg['recuperacao_exibe']) || $tipoLayout === 'rec') {
                $temRecuperacao = true;
                $recuperacoes[] = $cod;
                continue;
            }
            $livres[] = $cod;
        }
        $citados = [];
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $exp = strtolower(trim((string) ($this->configDoComponente($c)['expressao'] ?? '')));
            if ($exp === '' || !preg_match_all('/\b[a-z_][a-z0-9_]*\b/', $exp, $achados)) {
                continue;
            }
            foreach ($achados[0] as $tok) {
                if (isset($codigos[$tok])) {
                    $citados[$tok] = true;
                }
            }
        }
        $livres = array_values(array_filter(
            $livres,
            static fn (string $cod): bool => !isset($citados[$cod])
        ));
        $recuperacoes = array_values(array_filter(
            $recuperacoes,
            static fn (string $cod): bool => !isset($citados[$cod])
        ));

        if (!is_array($estado['formulas_blocos'] ?? null)) {
            $estado['formulas_blocos'] = [];
        }
        foreach ($comps as &$c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $cfg = $this->configDoComponente($c);
            $exp = strtolower(trim((string) ($cfg['expressao'] ?? '')));
            if ($exp === '' || !preg_match_all('/\b[a-z_][a-z0-9_]*\b/', $exp, $achados)) {
                continue;
            }
            $faltam = [];
            foreach ($achados[0] as $tok) {
                if (in_array($tok, ['max', 'min', 'chamada', 'substituta', 'composicao', 'media_ponderada', 'media_lancado', 'se', 'if'], true) || isset($faltam[$tok])) {
                    continue;
                }
                if ($this->tokenApontaParaColunaViva($tok, $codigos)) {
                    continue;
                }
                $faltam[$tok] = true;
            }
            if ($faltam === []) {
                continue;
            }
            $nova = $exp;
            foreach (array_keys($faltam) as $tok) {
                $ehRec = $tok === 'rec' || $tok === 'recuperacao';
                $pool = $ehRec ? $recuperacoes : $livres;
                $escolha = $this->colunaLivreParaToken($tok, $pool, $nomes);
                if ($escolha !== null) {
                    $nova = (string) preg_replace('/\b' . preg_quote($tok, '/') . '\b/', $escolha, $nova);
                    $livres = array_values(array_filter(
                        $livres,
                        static fn (string $cod): bool => $cod !== $escolha
                    ));
                    $recuperacoes = array_values(array_filter(
                        $recuperacoes,
                        static fn (string $cod): bool => $cod !== $escolha
                    ));
                    continue;
                }
                if ($ehRec && !$temRecuperacao) {
                    $nova = (string) preg_replace('/\b' . preg_quote($tok, '/') . '\b/', '0', $nova);
                }
            }
            $nova = $this->ligarSimuladosEProvaPos($nova, $comps, $codigos, $nomes, $livres, $temRecuperacao);
            if ($nova === $exp) {
                continue;
            }
            $cfg['expressao'] = $nova;
            $c['config'] = $cfg;
            $codAlvo = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($codAlvo !== '') {
                $toks = $this->tokensDaExpressao($nova);
                if ($toks !== []) {
                    $estado['formulas_blocos'][$codAlvo] = $toks;
                }
            }
        }
        unset($c);
        $rascunho['componentes'] = $comps;

        return $rascunho;
    }

    /**
     * @param list<string> $livres
     * @param array<string,string> $nomes
     */
    private function colunaLivreParaToken(string $token, array $livres, array $nomes): ?string
    {
        $token = strtolower(trim($token));
        if ($token === '') {
            return null;
        }
        $melhorNota = 0;
        $candidatos = [];
        foreach ($livres as $cod) {
            $nome = mb_strtolower(trim((string) ($nomes[$cod] ?? '')));
            $nota = 0;
            if ($nome !== '' && ($nome === $token || str_contains($nome, $token))) {
                $nota = 3;
            }
            if ($nota <= 0) {
                continue;
            }
            if ($nota > $melhorNota) {
                $melhorNota = $nota;
                $candidatos = [$cod];
                continue;
            }
            if ($nota === $melhorNota) {
                $candidatos[] = $cod;
            }
        }
        if (count($candidatos) !== 1) {
            return null;
        }

        return $candidatos[0];
    }

    /**
     * @param array<string,bool> $codigos
     */
    private function tokenApontaParaColunaViva(string $token, array $codigos): bool
    {
        if (isset($codigos[$token])) {
            return true;
        }
        $aliases = [
            'prova_bim' => 'bimestral',
            'bimestral' => 'prova_bim',
            'rec' => 'recuperacao',
            'recuperacao' => 'rec',
            'media_sem' => 'semanal',
            'semanal' => 'media_sem',
            'trab' => 'trabalho',
            'trabalho' => 'trab',
            'part' => 'participacao',
            'participacao' => 'part',
        ];
        $outro = $aliases[$token] ?? '';

        return $outro !== '' && isset($codigos[$outro]);
    }

    public function enriquecerSaida(array $resultado): array
    {
        $estado = is_array($resultado['estado'] ?? null) ? $resultado['estado'] : [];
        $rascunho = is_array($resultado['rascunho'] ?? null) ? $resultado['rascunho'] : null;
        if ($rascunho !== null) {
            try {
                $jaPosicionadas = [];
                foreach ((array) ($estado['colunas_ordem'] ?? []) as $codOrdem) {
                    $codOrdem = strtolower(trim((string) $codOrdem));
                    if ($codOrdem !== '') {
                        $jaPosicionadas[$codOrdem] = true;
                    }
                }
                $ehQuadroImpressao = !empty($estado['quadro_livre'])
                    || ($estado['origem'] ?? '') === 'quadro-impressao'
                    || (int) ($estado['layout_boletim_id'] ?? 0) > 0;
                $rascunho = $this->restringirDemonstrativoAosEventosMarcados($rascunho, $estado);
                // Montar quadro NÃO explode subeventos nem anexa recuperações do Demonstrativo de Notas.
                if (!$ehQuadroImpressao) {
                    $rascunho = $this->expandirSubeventosAvaliativos($rascunho, $estado);
                    $rascunho = $this->anexarRecuperacoesNoDemonstrativo($rascunho, $estado);
                    $rascunho = $this->anexarMediaDosSubeventos($rascunho, $estado);
                } else {
                    $compsFiltrados = $this->filtrarComponentesQuadroImpressao(
                        is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [],
                        $estado
                    );
                    $rascunho['componentes'] = $compsFiltrados;
                }
                $rascunho = $this->aplicarColunasOcultas($rascunho, $estado);
                if (!$this->ehBoletimComposto($estado)) {
                    $rascunho = $this->aplicarOrdemColunas($rascunho, $estado);
                    $extraidos = $this->extrairCodigosColunasMoveis($rascunho);
                    if ($ehQuadroImpressao && !empty($estado['cabecalhos_manuais'])) {
                        // Só interseção com o que o admin montou (+ blocos de cálculo).
                        $vivosExt = [];
                        foreach ($extraidos as $codExt) {
                            $vivosExt[$codExt] = true;
                        }
                        $calcOk = [];
                        foreach ((array) ($estado['blocos_calc'] ?? []) as $codCalc) {
                            $codCalc = strtolower(trim((string) $codCalc));
                            if ($codCalc !== '') {
                                $calcOk[$codCalc] = true;
                            }
                        }
                        foreach (array_keys((array) ($estado['formulas_blocos'] ?? [])) as $codCalc) {
                            $codCalc = strtolower(trim((string) $codCalc));
                            if ($codCalc !== '') {
                                $calcOk[$codCalc] = true;
                            }
                        }
                        $ordemManual = [];
                        foreach ((array) ($estado['colunas_ordem'] ?? []) as $codOrd) {
                            $codOrd = strtolower(trim((string) $codOrd));
                            if ($codOrd === '' || isset($ordemManual[$codOrd])) {
                                continue;
                            }
                            if (isset($vivosExt[$codOrd]) || isset($calcOk[$codOrd])) {
                                $ordemManual[$codOrd] = true;
                            }
                        }
                        foreach (array_keys($calcOk) as $codCalc) {
                            if (isset($vivosExt[$codCalc]) && !isset($ordemManual[$codCalc])) {
                                $ordemManual[$codCalc] = true;
                            }
                        }
                        $estado['colunas_ordem'] = array_keys($ordemManual);
                    } else {
                        $estado['colunas_ordem'] = $extraidos;
                    }
                    $rascunho = $this->encostarMediaNosSubeventos($rascunho, $estado, $jaPosicionadas);
                }
                $rascunho = $this->sincronizarLinhasLayout($rascunho, $estado);
                $rascunho = $this->religarExpressoesNasColunasVivas($rascunho, $estado);
                $resultado['rascunho'] = $rascunho;
                $resultado['estado'] = $estado;
            } catch (\Throwable $e) {
                error_log('BoletimAssistenteWizard ordem colunas: ' . $e->getMessage());
            }
        }
        try {
            $resultado['preview'] = $this->montarPreviewFicticio($rascunho, $estado);
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard preview: ' . $e->getMessage());
            $resultado['preview'] = [
                'modo' => 'vazio',
                'aviso' => 'Exemplo indisponível agora.',
                'tabelas' => [],
                'colunas' => [],
                'pecas_disponiveis' => [],
            ];
        }
        return $resultado;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizarColunasOcultas($raw): array
    {
        $out = [];
        foreach ((array) $raw as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod === '' || !(bool) preg_match('/^[a-z_][a-z0-9_]{0,40}$/', $cod)) {
                continue;
            }
            if (!in_array($cod, $out, true)) {
                $out[] = $cod;
            }
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * Coluna tirada do quadro: some da visualização, mas permanece no cálculo/fórmula.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarColunasOcultas(array $rascunho, array $estado): array
    {
        $ocultas = array_flip($this->normalizarColunasOcultas($estado['colunas_ocultas'] ?? []));
        if ($ocultas === []) {
            return $rascunho;
        }
        $semanal = isset($ocultas['_semanal']) || isset($ocultas['media_sem']);
        $comps = [];
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            // Semanal oculto continua fora (não entra em fórmula típica do quadro).
            if ($semanal && ($cod === 'media_sem' || $this->componenteEhSemanaQuadro($c))) {
                continue;
            }
            if (isset($ocultas[$cod])) {
                $cfg = $this->configDoComponente($c);
                $cfg['ocultar_no_quadro'] = 1;
                $c['config'] = $cfg;
            }
            $comps[] = $c;
        }
        $rascunho['componentes'] = $comps;

        return $rascunho;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizarColunasOrdem($raw): array
    {
        $out = [];
        foreach ((array) $raw as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod === '' || $cod === '_semanal' || $cod === 'media_sem') {
                continue;
            }
            if (!(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $cod)) {
                continue;
            }
            if (preg_match('/^s[1-8]$/', $cod)) {
                continue;
            }
            if (!in_array($cod, $out, true)) {
                $out[] = $cod;
            }
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /**
     * Linha opcional de cabeçalho salva em extras_json. Não entra na fórmula.
     *
     * @param mixed $raw
     * @return list<array{id:string,nome:string,colunas:list<string>}>
     */
    private function linhasLayoutDasExtras($raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? ($decoded['linhas_layout'] ?? []) : [];
        } elseif (is_array($raw) && array_key_exists('linhas_layout', $raw)) {
            $raw = $raw['linhas_layout'];
        }
        return $this->normalizarLinhasLayout($raw);
    }

    /**
     * Reconstrói títulos do período a partir de linha_cabecalho nos componentes.
     *
     * @param list<array<string,mixed>> $comps
     * @return list<array{id:string,nome:string,colunas:list<string>}>
     */
    private function linhasLayoutDeComponentes(array $comps): array
    {
        $grupos = [];
        $ordem = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === '_semanal' || (bool) preg_match('/^s[1-8]$/', $cod)) {
                continue;
            }
            $nome = trim((string) ($this->configDoComponente($c)['linha_cabecalho'] ?? ''));
            if ($nome === '') {
                continue;
            }
            if (function_exists('mb_substr')) {
                $nome = mb_substr($nome, 0, 40);
            } else {
                $nome = substr($nome, 0, 40);
            }
            if (!isset($grupos[$nome])) {
                $grupos[$nome] = [];
                $ordem[] = $nome;
            }
            if (!in_array($cod, $grupos[$nome], true)) {
                $grupos[$nome][] = $cod;
            }
        }
        $linhas = [];
        foreach ($ordem as $nome) {
            $cols = $grupos[$nome] ?? [];
            if ($cols === []) {
                continue;
            }
            $linhas[] = [
                'id' => 'lin' . substr(sha1($nome), 0, 8),
                'nome' => $nome,
                'colunas' => $cols,
            ];
        }

        return $this->normalizarLinhasLayout($linhas);
    }

    private function normalizarLinhasLayout($raw): array
    {
        $out = [];
        $usadas = [];
        foreach ((array) $raw as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $nome = trim((string) ($linha['nome'] ?? ''));
            if (function_exists('mb_substr')) {
                $nome = mb_substr($nome, 0, 40);
            } else {
                $nome = substr($nome, 0, 40);
            }
            if ($nome === '') {
                continue;
            }
            $cols = [];
            foreach ((array) ($linha['colunas'] ?? []) as $cod) {
                $cod = strtolower(trim((string) $cod));
                if ($cod === '' || isset($usadas[$cod]) || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $cod)) {
                    continue;
                }
                $cols[] = $cod;
                $usadas[$cod] = true;
                if (count($cols) >= 12) {
                    break;
                }
            }
            // Título de período pode nascer vazio — chips entram depois por arraste.
            $id = strtolower(trim((string) ($linha['id'] ?? '')));
            if (!(bool) preg_match('/^lin[a-z0-9_]{0,20}$/', $id)) {
                $id = 'lin' . substr(sha1($nome . implode(',', $cols)), 0, 8);
            }
            $out[] = ['id' => $id, 'nome' => $nome, 'colunas' => $cols];
            if (count($out) >= 8) {
                break;
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $c */
    private function componenteEhSemanaQuadro(array $c): bool
    {
        $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
        if (preg_match('/^s[1-8]$/', $cod)) {
            return true;
        }
        $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
        return strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? ''))) === 'semana_nq';
    }

    /**
     * @param array<string,mixed> $rascunho
     * @return list<string>
     */
    private function extrairCodigosColunasMoveis(array $rascunho): array
    {
        $out = [];
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c) || $this->componenteEhSemanaQuadro($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === 'media_sem' || in_array($cod, $out, true)) {
                continue;
            }
            $out[] = $cod;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarOrdemColunas(array $rascunho, array $estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $semanas = [];
        $mediaSem = [];
        $outros = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            if ($this->componenteEhSemanaQuadro($c)) {
                $semanas[] = $c;
            } elseif ($cod === 'media_sem') {
                $mediaSem[] = $c;
            } else {
                $outros[$cod] = $c;
            }
        }
        $ordem = $this->normalizarColunasOrdem($estado['colunas_ordem'] ?? []);
        $ordenados = [];
        $vistos = [];
        foreach ($ordem as $cod) {
            if (!isset($outros[$cod])) {
                continue;
            }
            $ordenados[] = $outros[$cod];
            $vistos[$cod] = true;
        }
        foreach ($outros as $cod => $c) {
            if (!isset($vistos[$cod])) {
                $ordenados[] = $c;
            }
        }
        $rascunho['componentes'] = array_merge($semanas, $mediaSem, $ordenados);
        return $rascunho;
    }

    /**
     * @param array<string,mixed>|null $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function montarPreviewFicticio(?array $rascunho, array $estado): array
    {
        if ($this->rascunhoEhLayoutBoletim($rascunho, $estado)) {
            return $this->montarPreviewFicticioBoletim($rascunho, $estado);
        }

        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $pecasSel = [];
        foreach ((array) ($estado['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p !== '') {
                $pecasSel[] = $p;
            }
        }
        $disponiveis = [];
        try {
            foreach ($this->pecasMeta() as $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $key = strtolower(trim((string) ($meta['key'] ?? '')));
                if ($key === '' || in_array($key, $pecasSel, true)) {
                    continue;
                }
                $disponiveis[] = [
                    'key' => $key,
                    'label' => (string) ($meta['label'] ?? $key),
                ];
            }
        } catch (\Throwable $e) {
            $disponiveis = [];
        }

        if ($comps === []) {
            $comps = $this->componentesSinteticosPreview($pecasSel);
        }

        $colunas = [];
        $temSemana = false;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ($this->componenteEhSemanaQuadro($c)) {
                $temSemana = true;
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === 'media_sem') {
                continue;
            }
            $colPreview = [
                'codigo' => $cod,
                'nome' => (string) ($c['nome'] ?? $cod),
                'tipo' => ((string) ($c['source_type'] ?? '')) === 'calculado' ? 'calculado' : 'peca',
                'travada' => false,
            ];
            $linhaCab = trim((string) ($this->configDoComponente($c)['linha_cabecalho'] ?? ''));
            if ($linhaCab !== '') {
                $colPreview['linha_cabecalho'] = function_exists('mb_substr') ? mb_substr($linhaCab, 0, 40) : substr($linhaCab, 0, 40);
            }
            $colunas[] = $colPreview;
        }
        if ($temSemana) {
            array_unshift($colunas, [
                'codigo' => '_semanal',
                'nome' => 'Prova semanal (S1–S8 · N e Q)',
                'tipo' => 'semana_grupo',
                'travada' => false,
            ]);
        }

        $tabelas = $this->partirPreviewTabelas($comps, $estado);
        $outTabelas = [];
        foreach ($tabelas as $tab) {
            $semanas = is_array($tab['semanas'] ?? null) ? $tab['semanas'] : [];
            $outras = is_array($tab['outras'] ?? null) ? $tab['outras'] : [];
            $codigosSemana = [];
            foreach ($semanas as $sc) {
                $codigosSemana[] = strtolower((string) ($sc['codigo'] ?? ''));
            }
            $linhas = [];
            foreach ($tab['materias'] as $matNome) {
                $linhas[] = [
                    'materia_nome' => $matNome,
                    'notas' => $this->notasFicticiasLinha($matNome, $comps, $codigosSemana, $estado),
                ];
            }
            $outTabelas[] = [
                'key' => (string) ($tab['key'] ?? 'u'),
                'titulo' => (string) ($tab['titulo'] ?? 'Matérias'),
                'subtitulo' => (string) ($tab['subtitulo'] ?? ''),
                'semanas' => $semanas,
                'outras' => $outras,
                'linhas' => $linhas,
            ];
        }

        return [
            'modo' => $temSemana ? 'quadro' : 'simples',
            'aviso' => 'Exemplo com dados fictícios — não são notas reais.',
            'tabelas' => $outTabelas,
            'colunas' => $colunas,
            'pecas_disponiveis' => $disponiveis,
        ];
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return list<array<string,mixed>>
     */
    private function partirPreviewTabelas(array $comps, array $estado): array
    {
        $blocoA = [];
        $blocoB = [];
        $mediaSem = [];
        $comum = [];
        $nomePorCodigo = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $codNome = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($codNome !== '') {
                $nomePorCodigo[$codNome] = (string) ($c['nome'] ?? $codNome);
            }
        }
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($cfg === [] && is_string($c['config_json'] ?? null) && $c['config_json'] !== '') {
                $decoded = json_decode((string) $c['config_json'], true);
                if (is_array($decoded)) {
                    $cfg = $decoded;
                }
            }
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            $col = [
                'codigo' => strtolower(trim((string) ($c['codigo'] ?? ''))),
                'nome' => (string) ($c['nome'] ?? ''),
                'layout_type' => strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? ''))),
                'source_type' => (string) ($c['source_type'] ?? ''),
            ];
            $linhaCab = trim((string) ($cfg['linha_cabecalho'] ?? ''));
            if ($linhaCab !== '') {
                $col['linha_cabecalho'] = function_exists('mb_substr') ? mb_substr($linhaCab, 0, 40) : substr($linhaCab, 0, 40);
            }
            $pai = strtolower(trim((string) ($cfg['evento_pai_codigo'] ?? '')));
            if ($pai !== '' && isset($nomePorCodigo[$pai]) && $nomePorCodigo[$pai] !== '') {
                $col['grupo'] = $nomePorCodigo[$pai];
                $col['grupo_codigo'] = $pai;
            }
            if ($col['codigo'] === '') {
                continue;
            }
            if ($g === 'quadro_a' || ($this->componenteEhSemanaQuadro($c) && preg_match('/^s[1357]$/', $col['codigo']))) {
                $blocoA[] = $col;
            } elseif ($g === 'quadro_b' || ($this->componenteEhSemanaQuadro($c) && preg_match('/^s[2468]$/', $col['codigo']))) {
                $blocoB[] = $col;
            } elseif ($col['codigo'] === 'media_sem' || $col['layout_type'] === 'media_sem') {
                $mediaSem[] = $col;
            } else {
                $comum[] = $col;
            }
        }
        $comum = array_merge($mediaSem, $comum);

        $materias = $this->materiasPreview($estado, ['Física', 'Matemática', 'Geografia', 'Biologia', 'Educação Física']);
        $metade = (int) ceil(count($materias) / 2);
        $materiasA = array_slice($materias, 0, $metade);
        $materiasB = array_slice($materias, $metade);
        if ($materiasB === []) {
            $materiasB = $materiasA;
        }

        if ($blocoA === [] && $blocoB === []) {
            return [[
                'key' => 'u',
                'titulo' => 'Matérias',
                'subtitulo' => 'Exemplo',
                'semanas' => [],
                'outras' => $comum,
                'materias' => $materias,
            ]];
        }

        $out = [];
        if ($blocoA !== []) {
            $out[] = [
                'key' => 'a',
                'titulo' => 'Matérias Bloco A',
                'subtitulo' => '',
                'semanas' => $blocoA,
                'outras' => $comum,
                'materias' => $materiasA,
            ];
        }
        if ($blocoB !== []) {
            $out[] = [
                'key' => 'b',
                'titulo' => 'Matérias Bloco B',
                'subtitulo' => '',
                'semanas' => $blocoB,
                'outras' => $comum,
                'materias' => $materiasB,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed>|null $rascunho
     * @param array<string,mixed> $estado
     */
    private function rascunhoEhLayoutBoletim(?array $rascunho, array $estado): bool
    {
        return $this->ehBoletimComposto($estado);
    }

    /**
     * @param array<string,mixed>|null $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function montarPreviewFicticioBoletim(?array $rascunho, array $estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        if ($this->ehModeloSantaMaria($estado)) {
            // Sempre o quadro completo do print: bimestres + 1º/2º semestre (REC/MS) + final.
            $comps = $this->componentesSinteticosPreviewSantaMaria();
        } elseif ($comps === []) {
            $comps = $this->componentesSinteticosPreviewBoletim($estado);
        } elseif ($this->previewTemColunasSantaMaria($comps) && !$this->previewTemSemestresSantaMaria($comps)) {
            $comps = $this->mesclarSemestresSantaMariaNosComponentes($comps);
        }
        $grupos = $this->gruposPreviewBoletim($comps, $estado);
        $notaMin = (float) ($rascunho['nota_minima_aprovacao'] ?? $estado['nota_minima_aprovacao'] ?? 6);
        $materias = $this->materiasPreview($estado, ['Língua Portuguesa', 'Matemática', 'História', 'Geografia', 'Ciências']);
        $linhas = [];
        foreach ($materias as $matNome) {
            $linhas[] = [
                'materia_nome' => $matNome,
                'notas' => $this->notasFicticiasLinhaBoletim($matNome, $comps, $notaMin, $estado),
            ];
        }
        $colunas = [];
        foreach ($grupos as $grp) {
            foreach ((array) ($grp['cols'] ?? []) as $col) {
                $colunas[] = [
                    'codigo' => (string) ($col['codigo'] ?? ''),
                    'nome' => (string) ($col['nome'] ?? $col['codigo'] ?? ''),
                    'tipo' => ((string) ($col['source_type'] ?? '')) === 'calculado' ? 'calculado' : 'peca',
                    'travada' => true,
                ];
            }
        }

        $sm = $this->ehModeloSantaMaria($estado);
        return [
            'modo' => 'boletim',
            'modelo_santa_maria' => $sm ? 1 : 0,
            'aviso' => $sm
                ? 'Preview no formato do print Santa Maria (PP/PU/CH/PE/MU/FAL). Dados fictícios.'
                : 'Exemplo com dados fictícios — não são notas reais.',
            'grupos' => $grupos,
            'tabelas' => [[
                'key' => 'u',
                'titulo' => $sm ? 'COMPONENTES CURRICULARES' : 'Matérias',
                'subtitulo' => '',
                'semanas' => [],
                'outras' => [],
                'grupos' => $grupos,
                'linhas' => $linhas,
            ]],
            'colunas' => $colunas,
            'pecas_disponiveis' => [],
        ];
    }

    /**
     * @param list<array<string,mixed>> $comps
     */
    private function previewTemColunasSantaMaria(array $comps): bool
    {
        $achou = 0;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $nome = strtoupper(trim((string) ($c['nome'] ?? '')));
            $cod = strtoupper(trim((string) ($c['codigo'] ?? '')));
            if (in_array($nome, ['PP', 'PU', 'PE', 'MU', 'FAL', 'CH'], true)
                || preg_match('/_(pp|pu|pe|mu|fal|ch)$/', strtolower($cod))
            ) {
                $achou++;
            }
        }
        return $achou >= 3;
    }

    /**
     * @param list<array<string,mixed>> $comps
     */
    private function previewTemSemestresSantaMaria(array $comps): bool
    {
        $temS1 = false;
        $temS2 = false;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            if ($cod === 's1_ms' || $cod === 's1_rec' || $g === 's1') {
                $temS1 = true;
            }
            if ($cod === 's2_ms' || $cod === 's2_rec' || $g === 's2') {
                $temS2 = true;
            }
        }
        return $temS1 && $temS2;
    }

    /**
     * Completa rascunho antigo (só bimestres) com REC/MS dos semestres e final.
     *
     * @param list<array<string,mixed>> $comps
     * @return list<array<string,mixed>>
     */
    private function mesclarSemestresSantaMariaNosComponentes(array $comps): array
    {
        $full = $this->componentesSinteticosPreviewSantaMaria();
        $porCod = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '') {
                $porCod[$cod] = $c;
            }
        }
        $out = [];
        foreach ($full as $c) {
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $out[] = $porCod[$cod] ?? $c;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<string> $fallback
     * @return list<string>
     */
    /**
     * @return list<int>
     */
    private function materiasIdsDoLayoutImpressao(int $layoutId): array
    {
        if ($layoutId <= 0) {
            return [];
        }
        try {
            $layout = (new \App\Modulos\EventosAvaliativos\Models\BoletimLayout())->find($layoutId);
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($layout)) {
            return [];
        }
        $ids = [];
        $regraAcadId = (int) ($layout['regra_academica_id'] ?? 0);
        if ($regraAcadId > 0) {
            try {
                $ids = (new \App\Modulos\RegrasAcademicas\Models\RegraAcademica())->listarMateriasIds($regraAcadId);
            } catch (\Throwable $e) {
                $ids = [];
            }
        }
        if ($ids === []) {
            $ids = array_map('intval', (array) ($layout['materias_ids'] ?? []));
        }
        $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
        if ($ids === []) {
            return [];
        }
        try {
            $comp = new \App\Dominio\Escola\Models\ComponenteCurricular();
            $ids = $comp->expandirIdsComFilhos($ids);
            $pais = $comp->idsComFilhos();
            $ids = array_values(array_filter($ids, static fn ($id) => empty($pais[$id])));
        } catch (\Throwable $e) {
            // mantém ids crus
        }

        return $ids;
    }

    private function materiasPreview(array $estado, array $fallback): array
    {
        $selecionadas = array_values(array_unique(array_filter(
            array_map('intval', (array) ($estado['materias_ids'] ?? [])),
            static fn ($id) => $id > 0
        )));

        // Quadro de impressão (Boletim → Montar quadro): matérias da regra de aprovação do layout.
        $layoutId = (int) ($estado['layout_boletim_id'] ?? 0);
        if ($layoutId > 0) {
            $idsLayout = $this->materiasIdsDoLayoutImpressao($layoutId);
            if ($idsLayout !== []) {
                $selecionadas = $idsLayout;
            }
        }

        // Modelo de cadastro (boletins): matérias da regra de aprovação vinculada.
        $boletimId = (int) ($estado['boletim_id'] ?? 0);
        if ($selecionadas === [] && $boletimId > 0) {
            $bol = $this->buscarBoletimCatalogo($boletimId);
            if (is_array($bol) && !empty($bol['materias_ids'])) {
                $selecionadas = array_values(array_unique(array_filter(
                    array_map('intval', (array) $bol['materias_ids']),
                    static fn ($id) => $id > 0
                )));
            }
        }

        if ($selecionadas !== []) {
            try {
                $comp = new \App\Dominio\Escola\Models\ComponenteCurricular();
                $selecionadas = $comp->expandirIdsComFilhos($selecionadas);
                $pais = $comp->idsComFilhos();
                $selecionadas = array_values(array_filter(
                    $selecionadas,
                    static fn ($id) => empty($pais[$id])
                ));
            } catch (\Throwable $e) {
                // mantém ids crus
            }
        }

        $selecionadasSet = array_fill_keys($selecionadas, true);
        $itens = [];

        try {
            foreach ($this->ferramentas->listarMaterias(500) as $m) {
                $id = (int) ($m['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if ($selecionadas !== [] && !isset($selecionadasSet[$id])) {
                    continue;
                }
                $nome = trim((string) ($m['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $itens[] = [
                    'nome' => $nome,
                    'ordem' => (int) ($m['ordem'] ?? (100000 + $id)),
                    'id' => $id,
                ];
            }
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard materias preview: ' . $e->getMessage());
        }

        usort($itens, static function (array $a, array $b): int {
            if ((int) $a['ordem'] !== (int) $b['ordem']) {
                return (int) $a['ordem'] <=> (int) $b['ordem'];
            }

            return ((int) $a['id']) <=> ((int) $b['id']);
        });
        $nomes = [];
        foreach ($itens as $it) {
            $nomes[] = $it['nome'];
        }
        $nomes = array_values(array_unique($nomes));
        return $nomes !== [] ? $nomes : $fallback;
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function hashAlunoPreview(array $estado): int
    {
        $alunoId = (int) ($estado['aluno_preview_id'] ?? 0);
        if ($alunoId <= 0) {
            return 0;
        }
        try {
            foreach ($this->ferramentas->listarAlunos(500) as $aluno) {
                if ((int) ($aluno['id'] ?? 0) !== $alunoId) {
                    continue;
                }
                return abs(crc32(mb_strtolower((string) ($aluno['nome'] ?? '')) . '#' . $alunoId));
            }
        } catch (\Throwable $e) {
            error_log('BoletimAssistenteWizard aluno preview: ' . $e->getMessage());
        }
        return abs(crc32((string) $alunoId));
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function componentesSinteticosPreviewBoletim(array $estado): array
    {
        if ($this->ehModeloSantaMaria($estado)) {
            return $this->componentesSinteticosPreviewSantaMaria();
        }
        $fontes = $this->normalizarFontesBimestres($estado['fontes_bimestres'] ?? []);
        $faltas = $this->normalizarFontesBimestres($estado['fontes_faltas'] ?? []);
        $bims = [];
        foreach ([1, 2, 3, 4] as $b) {
            if ((int) ($fontes[$b] ?? 0) > 0 || (int) ($faltas[$b] ?? 0) > 0) {
                $bims[] = $b;
            }
        }
        if ($bims === []) {
            $bims = [1, 2, 3, 4];
        }
        $comps = [];
        $codigosMedia = [];
        $codigosFaltas = [];
        foreach ($bims as $bim) {
            $codM = 'b' . $bim . '_media';
            $codF = 'b' . $bim . '_faltas';
            $codigosMedia[] = $codM;
            $codigosFaltas[] = $codF;
            $comps[] = $this->componenteBoletim($codM, 'Média', 'evento_boletim', [
                'layout_group' => 'b' . $bim,
                'layout_type' => 'media',
            ]);
            $comps[] = $this->componenteBoletim($codF, 'Faltas', 'faltas_evento', [
                'layout_group' => 'b' . $bim,
                'layout_type' => 'faltas',
            ]);
        }
        $n = count($codigosMedia);
        $comps[] = $this->componenteBoletim('media_final', 'Média', 'calculado', [
            'expressao' => $n === 1 ? $codigosMedia[0] : '(' . implode(' + ', $codigosMedia) . ') / ' . $n,
            'layout_group' => 'final',
            'layout_type' => 'media',
        ]);
        $comps[] = $this->componenteBoletim('rec_final', 'Rec.', 'nenhuma', [
            'layout_group' => 'final',
            'layout_type' => 'rec',
        ]);
        $comps[] = $this->componenteBoletim('faltas_final', 'Faltas', 'calculado', [
            'expressao' => implode(' + ', $codigosFaltas),
            'layout_group' => 'final',
            'layout_type' => 'faltas',
        ]);
        $comps[] = $this->componenteBoletim('resultado', 'Resultado', 'nenhuma', [
            'layout_group' => 'final',
            'layout_type' => 'resultado',
        ]);

        return $comps;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function componentesSinteticosPreviewSantaMaria(): array
    {
        $comps = [];
        $exprUnidade = static function (int $bim): string {
            return '((b' . $bim . '_pp + b' . $bim . '_pu) / 2) + b' . $bim . '_pe';
        };
        foreach ([1, 2, 3, 4] as $bim) {
            $g = 'b' . $bim;
            $pp = 'b' . $bim . '_pp';
            $pu = 'b' . $bim . '_pu';
            $ch = 'b' . $bim . '_ch';
            $pe = 'b' . $bim . '_pe';
            $mu = 'b' . $bim . '_mu';
            $fal = 'b' . $bim . '_fal';
            foreach ([[$pp, 'PP'], [$pu, 'PU'], [$ch, 'CH'], [$pe, 'PE']] as [$cod, $nome]) {
                $comps[] = $this->componenteBoletim($cod, $nome, 'nenhuma', [
                    'layout_group' => $g,
                    'layout_type' => 'media',
                ]);
            }
            $comps[] = $this->componenteBoletim($mu, 'MU', 'calculado', [
                'expressao' => $exprUnidade($bim),
                'layout_group' => $g,
                'layout_type' => 'media',
                'round_mode_override' => 'none',
            ]);
            $comps[] = $this->componenteBoletim($fal, 'FAL', 'nenhuma', [
                'layout_group' => $g,
                'layout_type' => 'faltas',
            ]);
        }
        $comps[] = $this->componenteBoletim('s1_rec', 'REC 1º', 'nenhuma', [
            'layout_group' => 's1',
            'layout_type' => 'rec',
        ]);
        $comps[] = $this->componenteBoletim('s1_ms', 'MS 1º', 'calculado', [
            'expressao' => '(b1_mu + b2_mu) / 2',
            'layout_group' => 's1',
            'layout_type' => 'media',
        ]);
        $comps[] = $this->componenteBoletim('s2_rec', 'REC 2º', 'nenhuma', [
            'layout_group' => 's2',
            'layout_type' => 'rec',
        ]);
        $comps[] = $this->componenteBoletim('s2_ms', 'MS 2º', 'calculado', [
            'expressao' => '(b3_mu + b4_mu) / 2',
            'layout_group' => 's2',
            'layout_type' => 'media',
        ]);
        $comps[] = $this->componenteBoletim('ma', 'MA', 'calculado', [
            'expressao' => '(s1_ms + s2_ms) / 2',
            'layout_group' => 'final',
            'layout_type' => 'media',
        ]);
        $comps[] = $this->componenteBoletim('pf', 'PF', 'nenhuma', [
            'layout_group' => 'final',
            'layout_type' => 'media',
        ]);
        $comps[] = $this->componenteBoletim('mf', 'MF', 'calculado', [
            'expressao' => 'ma',
            'layout_group' => 'final',
            'layout_type' => 'media',
        ]);
        $comps[] = $this->componenteBoletim('situacao', 'Situação', 'nenhuma', [
            'layout_group' => 'final',
            'layout_type' => 'resultado',
        ]);
        return $comps;
    }

    /**
     * Garante MU entre PE e FAL nas linhas do print Santa Maria (layout legado sem MU).
     *
     * @param list<mixed> $linhas
     * @return list<array<string,mixed>>
     */
    private function garantirMuNasLinhasSantaMaria(array $linhas): array
    {
        $out = [];
        foreach ($linhas as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $cols = [];
            foreach ((array) ($lin['colunas'] ?? []) as $cod) {
                $c = strtolower(trim((string) $cod));
                if ($c === '') {
                    continue;
                }
                if (preg_match('/^b([1-4])_fal$/', $c, $m)) {
                    $mu = 'b' . $m[1] . '_mu';
                    if (!in_array($mu, $cols, true)) {
                        $cols[] = $mu;
                    }
                }
                if (!in_array($c, $cols, true)) {
                    $cols[] = $c;
                }
            }
            // PE presente sem MU nem FAL → acrescenta MU depois da PE.
            foreach ([1, 2, 3, 4] as $bim) {
                $pe = 'b' . $bim . '_pe';
                $mu = 'b' . $bim . '_mu';
                if (in_array($pe, $cols, true) && !in_array($mu, $cols, true)) {
                    $idx = array_search($pe, $cols, true);
                    if ($idx === false) {
                        $cols[] = $mu;
                    } else {
                        array_splice($cols, (int) $idx + 1, 0, [$mu]);
                    }
                }
            }
            $lin['colunas'] = $cols;
            $out[] = $lin;
        }

        return $this->normalizarLinhasLayout($out);
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @param array<string,mixed> $estado
     * @return list<array{key:string,label:string,cols:list<array<string,mixed>>}>
     */
    private function gruposPreviewBoletim(array $comps, array $estado = []): array
    {
        $sm = $this->ehModeloSantaMaria($estado) || $this->previewTemColunasSantaMaria($comps);
        $ordem = $sm
            ? ['b1', 'b2', 's1', 'b3', 'b4', 's2', 'final']
            : ['b1', 'b2', 'b3', 'b4', 'final'];
        $labels = $sm
            ? [
                'b1' => 'I BIMESTRE',
                'b2' => 'II BIMESTRE',
                's1' => '1º SEMESTRE',
                'b3' => 'III BIMESTRE',
                'b4' => 'IV BIMESTRE',
                's2' => '2º SEMESTRE',
                'final' => 'RESULTADO FINAL',
            ]
            : [
                'b1' => '1º BIMESTRE',
                'b2' => '2º BIMESTRE',
                'b3' => '3º BIMESTRE',
                'b4' => '4º BIMESTRE',
                'final' => 'FINAL',
            ];
        $subs = [
            'media' => 'Média',
            'faltas' => 'Faltas',
            'rec' => 'Rec.',
            'resultado' => 'Resultado',
        ];
        $ocultas = array_flip($this->normalizarColunasOcultas($estado['colunas_ocultas'] ?? []));
        $porGrupo = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            if (!isset($labels[$g])) {
                continue;
            }
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            // Oculta no quadro: entra na fórmula, não na grade visual.
            if (isset($ocultas[$cod]) || !empty($cfg['ocultar_no_quadro'])) {
                continue;
            }
            $nomeCol = trim((string) ($c['nome'] ?? ''));
            if ($nomeCol === '') {
                $nomeCol = $subs[$lt] ?? $cod;
            }
            $porGrupo[$g][] = [
                'codigo' => $cod,
                'nome' => $nomeCol,
                'layout_type' => $lt !== '' ? $lt : 'media',
                'source_type' => (string) ($c['source_type'] ?? ''),
            ];
        }
        $out = [];
        foreach ($ordem as $gk) {
            if (empty($porGrupo[$gk])) {
                continue;
            }
            $out[] = [
                'key' => $gk,
                'label' => $labels[$gk],
                'cols' => $porGrupo[$gk],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return array<string,mixed>
     */
    private function notasFicticiasLinhaBoletim(string $materia, array $comps, float $notaMin, array $estado): array
    {
        $hash = abs(crc32(mb_strtolower($materia))) + $this->hashAlunoPreview($estado);
        $roundRaw = (string) ($estado['round_mode'] ?? 'none');
        $roundMode = in_array($roundRaw, ['none', 'half', 'places'], true) ? $roundRaw : 'none';
        $casas = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? 2);
        $notas = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $src = (string) ($c['source_type'] ?? '');
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            if ($src === 'calculado') {
                continue;
            }
            if ($lt === 'resultado') {
                continue;
            }
            if ($lt === 'rec') {
                $notas[$cod] = ($hash % 3) === 0 ? $this->roundPreviewFicticio(5 + (($hash + 11) % 40) / 10, $roundMode, $casas) : '—';
                continue;
            }
            if ($lt === 'faltas' || $src === 'faltas_evento') {
                $notas[$cod] = (int) (($hash + strlen($cod) * 5) % 9);
                continue;
            }
            $notas[$cod] = $this->roundPreviewFicticio(5 + (($hash + strlen($cod) * 7) % 51) / 10, $roundMode, $casas);
        }
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $exp = trim((string) ($cfg['expressao'] ?? ''));
            $val = $this->avaliarPreviewExpr($exp, $notas);
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            if ($val === null) {
                $notas[$cod] = '—';
            } elseif ($lt === 'faltas') {
                $notas[$cod] = (int) round($val);
            } else {
                $notas[$cod] = $this->roundPreviewFicticio($val, $roundMode, $casas);
            }
        }
        $mediaFinal = is_numeric($notas['media_final'] ?? null)
            ? (float) $notas['media_final']
            : (is_numeric($notas['mf'] ?? null) ? (float) $notas['mf'] : null);
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            if ($lt !== 'resultado') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            if (trim((string) ($cfg['expressao'] ?? '')) !== '') {
                continue;
            }
            if ($cod === 'situacao' || $this->ehModeloSantaMaria($estado)) {
                $notas[$cod] = 'Cursando';
                continue;
            }
            $notas[$cod] = ($mediaFinal !== null && $mediaFinal >= $notaMin) ? 'Aprovado' : 'Reprovado';
        }

        return $notas;
    }

    /**
     * Quadro de exemplo (S1–S8) quando o rascunho ainda não tem componentes.
     *
     * @param list<string> $pecas
     * @return list<array<string,mixed>>
     */
    private function componentesSinteticosPreview(array $pecas): array
    {
        $comps = [];
        foreach ([1, 3, 5, 7] as $s) {
            $comps[] = [
                'codigo' => 's' . $s,
                'nome' => 'S' . $s,
                'source_type' => 'provas_sistema',
                'config' => ['layout_group' => 'quadro_a', 'layout_type' => 'semana_nq'],
            ];
        }
        foreach ([2, 4, 6, 8] as $s) {
            $comps[] = [
                'codigo' => 's' . $s,
                'nome' => 'S' . $s,
                'source_type' => 'provas_sistema',
                'config' => ['layout_group' => 'quadro_b', 'layout_type' => 'semana_nq'],
            ];
        }
        $comps[] = [
            'codigo' => 'media_sem',
            'nome' => 'Média Sem',
            'source_type' => 'calculado',
            'config' => [
                'layout_group' => 'quadro_comum',
                'layout_type' => 'media_sem',
                'agregar_nq' => ['s1', 's3', 's5', 's7', 's2', 's4', 's6', 's8'],
            ],
        ];
        $mapa = [
            'bimestral' => ['prova_bim', 'Prova Bim'],
            'enac' => ['enac', 'ENAC'],
            'participacao' => ['part', 'Part'],
            'trabalho' => ['trab', 'Trab'],
            'recuperacao' => ['rec', 'Rec'],
            'jornada' => ['jornada', 'Jornada'],
        ];
        if ($pecas === []) {
            $pecas = ['bimestral'];
        }
        $partes = ['media_sem'];
        foreach ($pecas as $p) {
            if ($p === 'semanal' || !isset($mapa[$p])) {
                continue;
            }
            [$cod, $nome] = $mapa[$p];
            $tipo = $p === 'recuperacao' ? 'rec' : 'media';
            $comps[] = [
                'codigo' => $cod,
                'nome' => $nome,
                'source_type' => 'provas_sistema',
                'config' => ['layout_group' => 'quadro_comum', 'layout_type' => $tipo],
            ];
            if ($p !== 'recuperacao') {
                $partes[] = $cod;
            }
        }
        $n = count($partes);
        $exp = $n === 1 ? $partes[0] : '(' . implode(' + ', $partes) . ') / ' . $n;
        $comps[] = [
            'codigo' => 'media_bim',
            'nome' => 'Média Bim',
            'source_type' => 'calculado',
            'config' => [
                'expressao' => $exp,
                'layout_group' => 'quadro_comum',
                'layout_type' => 'media',
            ],
        ];
        if (in_array('recuperacao', $pecas, true)) {
            $comps[] = [
                'codigo' => 'media_final',
                'nome' => 'Resultado Final',
                'source_type' => 'calculado',
                'config' => [
                    'expressao' => 'max(media_bim, rec)',
                    'layout_group' => 'quadro_comum',
                    'layout_type' => 'media',
                ],
            ];
        }
        return $comps;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @param list<string> $codigosSemanaBloco
     * @return array<string,mixed>
     */
    private function notasFicticiasLinha(string $materia, array $comps, array $codigosSemanaBloco, array $estado): array
    {
        $hash = abs(crc32(mb_strtolower($materia))) + $this->hashAlunoPreview($estado);
        $hashJornadaUnica = 1700 + $this->hashAlunoPreview($estado);
        $jornadaNotaUnicaEstado = ((string) ($estado['jornada_distribuicao_notas'] ?? '')) === 'nota_unica_todas_linhas';
        $roundRawLinha = (string) ($estado['round_mode'] ?? 'none');
        $roundMode = in_array($roundRawLinha, ['none', 'half', 'places'], true) ? $roundRawLinha : 'none';
        $casas = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($estado['decimal_places'] ?? 2);
        $notas = [];
        $sumN = 0;
        $sumQ = 0;
        foreach ($comps as $c) {
            if (!is_array($c) || !$this->componenteEhSemanaQuadro($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $s = (int) substr($cod, 1);
            $naBloco = in_array($cod, $codigosSemanaBloco, true);
            $q = $naBloco ? 10 : 0;
            $n = $naBloco ? (5 + (($hash + $s * 3) % 6)) : 0;
            $notas[$cod . '__n'] = $n;
            $notas[$cod . '__q'] = $q;
            if ($naBloco) {
                $sumN += $n;
                $sumQ += $q;
            }
        }

        $calculados = [];
        foreach ($comps as $c) {
            if (!is_array($c) || $this->componenteEhSemanaQuadro($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $src = (string) ($c['source_type'] ?? '');
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($src === 'calculado') {
                $agregar = is_array($cfg['agregar_nq'] ?? null) ? $cfg['agregar_nq'] : [];
                if ($agregar !== [] || $cod === 'media_sem') {
                    $notas[$cod] = $sumQ > 0 ? $this->roundPreviewFicticio(10 * $sumN / $sumQ, $roundMode, $casas) : 0.0;
                    continue;
                }
                $calculados[] = $c;
                continue;
            }
            if ($cod === 'rec' && ($hash % 3) === 0) {
                $notas[$cod] = '—';
                continue;
            }
            if (!empty($cfg['recuperacao_exibe'])) {
                $notas[$cod] = ($hash % 5) === 0
                    ? $this->roundPreviewFicticio(5 + (($hash + strlen($cod) * 3) % 40) / 10, $roundMode, $casas)
                    : '—';
                continue;
            }
            $cfgDist = strtolower(trim((string) ($cfg['distribuicao_notas'] ?? '')));
            $jornadaUnica = $src === 'jornadas'
                && ($jornadaNotaUnicaEstado || $cfgDist === 'nota_unica_todas_linhas');
            $extraSub = $this->componenteEhColunaSubevento($c)
                ? (int) ($this->idsBlocosDoComponente($c)[0] ?? 0)
                : 0;
            $seed = $jornadaUnica
                ? ($hashJornadaUnica + strlen($cod) * 7)
                : ($hash + strlen($cod) * 7 + $extraSub * 3);
            $notas[$cod] = $this->roundPreviewFicticio(5 + ($seed % 51) / 10, $roundMode, $casas);
        }

        foreach ($comps as $c) {
            if (!is_array($c) || $this->componenteEhColunaSubevento($c)) {
                continue;
            }
            $idsPai = $this->idsBlocosDoComponente($c);
            if (count($idsPai) < 2) {
                continue;
            }
            $codPai = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($codPai === '') {
                continue;
            }
            $acc = 0.0;
            $nPartes = 0;
            $setPai = array_fill_keys($idsPai, true);
            foreach ($comps as $filho) {
                if (!is_array($filho) || !$this->componenteEhColunaSubevento($filho)) {
                    continue;
                }
                $idsFilho = $this->idsBlocosDoComponente($filho);
                $bid = (int) ($idsFilho[0] ?? 0);
                if ($bid <= 0 || !isset($setPai[$bid])) {
                    continue;
                }
                $codFilho = strtolower(trim((string) ($filho['codigo'] ?? '')));
                if ($codFilho === '' || !isset($notas[$codFilho]) || !is_numeric($notas[$codFilho])) {
                    continue;
                }
                $acc += (float) $notas[$codFilho];
                $nPartes++;
            }
            if ($nPartes > 0) {
                $notas[$codPai] = $this->roundPreviewFicticio($acc / $nPartes, $roundMode, $casas);
            }
        }

        $pendentes = $calculados;
        $limite = count($calculados) + 1;
        while ($pendentes !== [] && $limite-- > 0) {
            $restou = [];
            foreach ($pendentes as $c) {
                $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
                $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
                $exp = trim((string) ($cfg['expressao'] ?? ''));
                $val = $this->avaliarPreviewExpr($exp, $notas);
                if ($val === null && $this->expressaoAindaDependeDeColuna($exp, $notas)) {
                    $restou[] = $c;
                    continue;
                }
                $notas[$cod] = $val !== null ? $this->roundPreviewFicticio($val, $roundMode, $casas) : '—';
            }
            if (count($restou) === count($pendentes)) {
                foreach ($restou as $c) {
                    $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
                    $notas[$cod] = '—';
                }
                break;
            }
            $pendentes = $restou;
        }

        return $notas;
    }

    private function roundPreviewFicticio(float $valor, string $roundMode, int $casas = 2): float
    {
        $casas = \App\Modulos\RegrasAcademicas\Services\EscalaNota::casasDecimais($casas);
        if ($roundMode !== 'half') {
            return round($valor, $casas);
        }

        $base = floor($valor);
        $dec = $valor - $base;
        if ($dec < 0.25) {
            $valor = (float) $base;
        } elseif ($dec < 0.75) {
            $valor = $base + 0.5;
        } else {
            $valor = $base + 1.0;
        }
        // Regra “Nenhuma” casa: .50 vira inteiro após a faixa.
        if ($casas === 0) {
            return (float) round($valor);
        }

        return round($valor, min(1, $casas));
    }

    /**
     * A fórmula cita uma coluna que ainda não entrou no cálculo (ex.: \ENAC depois da Média Bim).
     *
     * @param array<string,mixed> $notas
     */
    private function expressaoAindaDependeDeColuna(string $expr, array $notas): bool
    {
        $expr = strtolower(trim($expr));
        if ($expr === '' || !preg_match_all('/\b[a-z_][a-z0-9_]*\b/', $expr, $achados)) {
            return false;
        }
        foreach ($achados[0] as $cod) {
            if (in_array($cod, ['max', 'min', 'chamada', 'substituta', 'composicao', 'media_ponderada', 'media_lancado', 'se', 'if'], true)) {
                continue;
            }
            if (!isset($notas[$cod]) || !is_numeric($notas[$cod])) {
                return true;
            }
        }

        return false;
    }

    private function avaliarPreviewExpr(string $expr, array $vals): ?float
    {
        $expr = strtolower(trim($expr));
        if ($expr === '') {
            return null;
        }
        $expr = $this->envolverSomaAntesDeDivisaoNaExpressao($expr);
        $nums = [];
        foreach ($vals as $cod => $n) {
            if (!is_numeric($n) || str_contains((string) $cod, '__')) {
                continue;
            }
            $nums[strtolower((string) $cod)] = (float) $n;
        }
        uksort($nums, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        $temNotaPrev = static function (string $cod) use ($nums): bool {
            $cod = strtolower(trim($cod));

            return $cod !== '' && array_key_exists($cod, $nums);
        };
        $valorNotaPrev = static function (string $cod) use ($nums): ?float {
            $cod = strtolower(trim($cod));
            if ($cod === '' || !array_key_exists($cod, $nums)) {
                return null;
            }

            return (float) $nums[$cod];
        };
        // Expande localmente (sem ResultadoAcademicoService) para não zerar colunas
        // calculadas ainda pendentes — o caminho abaixo deixa o identificador e adia.
        // se() primeiro (descarta o ramo não usado).
        if (preg_match('/\b(?:se|if)\s*\(/i', $expr)) {
            try {
                $expr = (new \App\Modulos\Fechamento\Services\ResultadoAcademicoService())
                    ->expandirSeParaPreview($expr, $temNotaPrev, $valorNotaPrev);
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (preg_match('/\bmedia_ponderada(?:_substituta)?\s*\(/i', $expr) || preg_match('/\bm[eé]dia\s+ponderada/iu', $expr)) {
            try {
                $expr = (new \App\Modulos\Fechamento\Services\ResultadoAcademicoService())
                    ->expandirMediaPonderadaParaPreview($expr, $temNotaPrev);
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (preg_match('/\bmedia_lancado\s*\(/i', $expr)) {
            try {
                $expr = (new \App\Modulos\Fechamento\Services\ResultadoAcademicoService())
                    ->expandirMediaLancadoParaPreview($expr, $temNotaPrev);
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (preg_match('/\b(chamada|substituta)\s*\(/i', $expr)) {
            try {
                $expr = $this->expandirFuncoesChamadaPreview($expr, $nums);
            } catch (\Throwable $e) {
                return null;
            }
        }
        if (preg_match('/\bcomposicao\s*\(/i', $expr)) {
            try {
                $expr = $this->expandirFuncoesComposicaoPreview($expr, $nums);
            } catch (\Throwable $e) {
                return null;
            }
        }
        // Sem nota de recuperação, min(rec, 6) não pode anular a prova: entra como zero e o max fica com a prova.
        $expr = preg_replace_callback(
            '/\bmin\s*\(\s*([a-z_][a-z0-9_]*)/',
            static function (array $m) use ($nums): string {
                return isset($nums[$m[1]]) ? $m[0] : 'min(0';
            },
            $expr
        ) ?? $expr;
        foreach ($nums as $cod => $n) {
            $expr = preg_replace('/\b' . preg_quote((string) $cod, '/') . '\b/', (string) $n, $expr) ?? $expr;
        }
        $guard = 0;
        while ($guard++ < 20 && preg_match('/\b(max|min)\s*\(/i', $expr)) {
            // Sem parêntese na classe: o mais interno casa primeiro e o de fora não come o ")" dele.
            if (!preg_match('/\b(max|min)\s*\(\s*([0-9.+\-*\/\s]+)\s*,\s*([0-9.+\-*\/\s]+)\s*\)/i', $expr, $m)) {
                break;
            }
            $l = $this->avaliarAritmeticaPreview($m[2]);
            $r = $this->avaliarAritmeticaPreview($m[3]);
            if ($l === null || $r === null) {
                return null;
            }
            $v = strtolower($m[1]) === 'max' ? max($l, $r) : min($l, $r);
            $expr = str_replace($m[0], (string) $v, $expr);
        }
        return $this->avaliarAritmeticaPreview($expr);
    }

    /**
     * Mesma semântica do motor de fechamento: média das bases, 1ª ausente ← substituta.
     * Não troca identificador pendente por 0 — isso fica a cargo do avaliador abaixo.
     *
     * @param array<string,float> $nums
     */
    private function expandirFuncoesChamadaPreview(string $expr, array $nums): string
    {
        $temNota = static function (string $cod) use ($nums): bool {
            $cod = strtolower(trim($cod));

            return $cod !== '' && array_key_exists($cod, $nums);
        };
        $guard = 0;
        while (preg_match('/\b(chamada|substituta)\s*\(/i', $expr)) {
            if (++$guard > 80) {
                throw new \RuntimeException('Limite de aninhamento em chamada/substituta.');
            }
            if (!preg_match('/\b(chamada|substituta)\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }
            $startFn = (int) $m[0][1];
            $openParen = $startFn + strlen($m[0][0]) - 1;
            [$args, $closePos] = $this->extrairArgumentosFuncaoPreview($expr, $openParen);
            if (count($args) < 2) {
                throw new \RuntimeException('chamada/substituta precisa de ao menos 2 argumentos.');
            }
            $substituta = array_pop($args);
            $preencheu = false;
            $termos = [];
            foreach ($args as $base) {
                $baseTrim = trim((string) $base);
                $ehIdent = (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $baseTrim);
                if ($ehIdent && !$temNota($baseTrim) && !$preencheu) {
                    $termos[] = '(' . $substituta . ')';
                    $preencheu = true;
                } else {
                    $termos[] = '(' . $baseTrim . ')';
                }
            }
            $n = count($termos);
            $media = $n === 1
                ? $termos[0]
                : '((' . implode('+', $termos) . ')/' . $n . ')';
            $expr = substr($expr, 0, $startFn) . $media . substr($expr, $closePos + 1);
            // substituta já é média — remove ÷ N redundante (senão a nota cai na metade).
            if ($n > 1) {
                $cauda = substr($expr, $startFn + strlen($media));
                if (preg_match('/^\s*\/\s*(\d+(?:[.,]\d+)?)/', $cauda, $divM)) {
                    $divisor = (float) str_replace(',', '.', (string) ($divM[1] ?? ''));
                    if (abs($divisor - $n) < 0.0001) {
                        $expr = substr($expr, 0, $startFn + strlen($media)) . substr($cauda, strlen($divM[0]));
                    }
                }
            }
        }

        return $expr;
    }

    /**
     * composicao(média, REC) → sem REC: média; com REC: (média+REC)/2.
     *
     * @param array<string,float> $nums
     */
    private function expandirFuncoesComposicaoPreview(string $expr, array $nums): string
    {
        $temNota = static function (string $cod) use ($nums): bool {
            $cod = strtolower(trim($cod));

            return $cod !== '' && array_key_exists($cod, $nums);
        };
        $guard = 0;
        while (preg_match('/\bcomposicao\s*\(/i', $expr)) {
            if (++$guard > 80) {
                throw new \RuntimeException('Limite de aninhamento em composicao.');
            }
            if (!preg_match('/\bcomposicao\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
                break;
            }
            $startFn = (int) $m[0][1];
            $openParen = $startFn + strlen($m[0][0]) - 1;
            [$args, $closePos] = $this->extrairArgumentosFuncaoPreview($expr, $openParen);
            if (count($args) !== 2) {
                throw new \RuntimeException('composicao precisa de 2 argumentos: composicao(média, REC).');
            }
            $media = trim((string) $args[0]);
            $rec = trim((string) $args[1]);
            $ehIdent = (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $rec);
            if ($ehIdent && !$temNota($rec)) {
                $substituicao = '(' . $media . ')';
            } else {
                $substituicao = '((' . $media . ')+(' . $rec . '))/2';
            }
            $expr = substr($expr, 0, $startFn) . $substituicao . substr($expr, $closePos + 1);
        }

        return $expr;
    }

    /**
     * @return array{0: list<string>, 1: int}
     */
    private function extrairArgumentosFuncaoPreview(string $expr, int $openParenIdx): array
    {
        $n = strlen($expr);
        $i = $openParenIdx + 1;
        $depth = 1;
        $args = [];
        $inicio = $i;
        for (; $i < $n; $i++) {
            $c = $expr[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    $args[] = substr($expr, $inicio, $i - $inicio);

                    return [$args, $i];
                }
            } elseif ($c === ',' && $depth === 1) {
                $args[] = substr($expr, $inicio, $i - $inicio);
                $inicio = $i + 1;
            }
        }

        throw new \RuntimeException('Função com parênteses incompletos.');
    }

    private function avaliarAritmeticaPreview(string $expr): ?float
    {
        $expr = preg_replace('/\s+/', '', $expr) ?? '';
        if ($expr === '' || !preg_match('/^[0-9.+\-*\/()]+$/', $expr)) {
            return null;
        }
        if (preg_match('/[+\-*\/]{2,}/', $expr) || str_contains($expr, '()')) {
            return null;
        }
        try {
            $resultado = @eval('return (float) (' . $expr . ');');
            if (!is_numeric($resultado) || !is_finite((float) $resultado)) {
                return null;
            }
            return (float) $resultado;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!class_exists('BoletimAssistenteWizard', false) && class_exists(__NAMESPACE__ . '\\BoletimAssistenteWizard', false)) {
    class_alias(__NAMESPACE__ . '\\BoletimAssistenteWizard', 'BoletimAssistenteWizard');
}
