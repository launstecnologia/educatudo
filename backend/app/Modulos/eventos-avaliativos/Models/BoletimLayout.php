<?php

namespace App\Modulos\EventosAvaliativos\Models;

use App\Core\Database;
use App\Core\PeriodoLetivo;
use App\Modulos\EventosAvaliativos\Services\FormulaMediaFinal;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

class BoletimLayout
{
    public const PECAS = [
        'media' => 'Média',
        'faltas' => 'Faltas',
        'rec' => 'Recuperação',
        'situacao' => 'Situação',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelaPronta(): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1',
                ['t' => 'boletim_layouts']
            );
            return !empty($row['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function identidadePronta(): bool
    {
        if (!$this->tabelaPronta()) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1',
                ['t' => 'boletim_layouts', 'c' => 'nome']
            );
            return !empty($row['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $ano): array
    {
        if ($ano <= 0 || !$this->tabelaPronta()) {
            return [];
        }
        if (!$this->identidadePronta()) {
            $legado = $this->db->fetchAll(
                'SELECT id, ano_letivo FROM boletim_layouts WHERE ano_letivo = :ano ORDER BY id ASC',
                ['ano' => $ano]
            );
            $saida = [];
            foreach ($legado ?: [] as $row) {
            $saida[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => 'Boletim oficial',
                'finalidade' => 'oficial',
                'ano_letivo' => (int) ($row['ano_letivo'] ?? 0),
                'vis_aluno' => 1,
                'vis_pais' => 1,
                'ativo' => 1,
                'regra_academica_nome' => '',
                'media_minima' => null,
                'materias_qtd' => 0,
                'periodos_qtd' => 0,
            ];
            }
            return $saida;
        }
        $rows = $this->db->fetchAll(
            'SELECT bl.id, bl.nome, bl.finalidade, bl.ano_letivo, bl.vis_aluno, bl.vis_pais, bl.ativo,
                    bl.materias_ids, bl.layout_json,
                    ra.nome AS regra_academica_nome, ra.media_minima
             FROM boletim_layouts bl
             LEFT JOIN regras_academicas ra ON ra.id = bl.regra_academica_id
             WHERE bl.ano_letivo = :ano
             ORDER BY FIELD(bl.finalidade, \'oficial\', \'complementar\'), bl.nome ASC, bl.id ASC',
            ['ano' => $ano]
        );
        $saida = [];
        foreach ($rows ?: [] as $row) {
            $anoLinha = (int) ($row['ano_letivo'] ?? 0);
            $saida[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => (string) ($row['nome'] ?? ''),
                'finalidade' => ((string) ($row['finalidade'] ?? '')) === 'complementar' ? 'complementar' : 'oficial',
                'ano_letivo' => $anoLinha,
                'vis_aluno' => (int) ($row['vis_aluno'] ?? 1) === 1 ? 1 : 0,
                'vis_pais' => (int) ($row['vis_pais'] ?? 1) === 1 ? 1 : 0,
                'ativo' => (int) ($row['ativo'] ?? 1) === 1 ? 1 : 0,
                'regra_academica_nome' => trim((string) ($row['regra_academica_nome'] ?? '')),
                'media_minima' => $row['media_minima'] === null ? null : (float) $row['media_minima'],
                'materias_qtd' => count($this->idsJson((string) ($row['materias_ids'] ?? ''))),
                'periodos_qtd' => $this->periodosDaLista($anoLinha, (string) ($row['layout_json'] ?? '')),
            ];
        }
        return $saida;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        if ($id <= 0 || !$this->tabelaPronta()) {
            return null;
        }
        if (!$this->identidadePronta()) {
            return $this->findLegado($id);
        }
        $row = $this->db->fetch(
            'SELECT id, ano_letivo, nome, finalidade, vis_aluno, vis_pais, ativo,
                    regra_academica_id, materias_ids, series_ids, turmas_ids, layout_json
             FROM boletim_layouts WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$row) {
            return null;
        }
        $ano = (int) ($row['ano_letivo'] ?? 0);
        $decoded = json_decode((string) ($row['layout_json'] ?? ''), true);
        $linhas = is_array($decoded['linhas'] ?? null) ? $decoded['linhas'] : [];
        $formulas = is_array($decoded['formulas'] ?? null) ? $decoded['formulas'] : [];
        $row['id'] = (int) $row['id'];
        $row['ano_letivo'] = $ano;
        $row['nome'] = (string) ($row['nome'] ?? '');
        $row['finalidade'] = ((string) ($row['finalidade'] ?? '')) === 'complementar' ? 'complementar' : 'oficial';
        $row['vis_aluno'] = (int) ($row['vis_aluno'] ?? 1) === 1 ? 1 : 0;
        $row['vis_pais'] = (int) ($row['vis_pais'] ?? 1) === 1 ? 1 : 0;
        $row['ativo'] = (int) ($row['ativo'] ?? 1) === 1 ? 1 : 0;
        $row['regra_academica_id'] = (int) ($row['regra_academica_id'] ?? 0);
        $row['materias_ids'] = $this->idsJson((string) ($row['materias_ids'] ?? ''));
        $row['series_ids'] = $this->idsJson((string) ($row['series_ids'] ?? ''));
        $row['turmas_ids'] = $this->idsJson((string) ($row['turmas_ids'] ?? ''));
        $row['linhas'] = $linhas === [] ? $this->padrao($ano) : $this->filtrar($ano, $linhas);
        $row['formula_final_media'] = trim((string) ($formulas['final_media'] ?? ''));
        return $row;
    }

    /**
     * @param array<string,mixed> $dados
     * @return array{ok:bool,erro:string,id:int}
     */
    public function criar(array $dados): array
    {
        $pronto = $this->cadastroPronto($dados);
        if (!$pronto['ok'] || !$this->identidadePronta()) {
            return ['ok' => false, 'erro' => $pronto['erro'] !== '' ? $pronto['erro'] : 'Rode a migration 2026_10_28_boletim_layouts_varios.sql no painel Master.', 'id' => 0];
        }
        $cadastro = $pronto['dados'];
        $json = json_encode(['linhas' => $this->padrao((int) $cadastro['ano_letivo'])], JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['ok' => false, 'erro' => 'Não foi possível montar o layout.', 'id' => 0];
        }
        $id = (int) $this->db->insert(
            'INSERT INTO boletim_layouts
                (ano_letivo, nome, finalidade, vis_aluno, vis_pais, ativo, regra_academica_id, materias_ids, series_ids, turmas_ids, layout_json)
             VALUES
                (:ano, :nome, :finalidade, :vis_aluno, :vis_pais, :ativo, :regra, :materias, :series, :turmas, :layout)',
            $this->paramsCadastro($cadastro, $json)
        );
        return ['ok' => $id > 0, 'erro' => $id > 0 ? '' : 'Não foi possível criar o boletim.', 'id' => $id];
    }

    /**
     * @param array<string,mixed> $dados
     * @return array{ok:bool,erro:string}
     */
    public function atualizarCadastro(int $id, array $dados): array
    {
        if (!$this->identidadePronta()) {
            return ['ok' => false, 'erro' => 'Rode a migration 2026_10_28_boletim_layouts_varios.sql no painel Master.'];
        }
        if ($this->find($id) === null) {
            return ['ok' => false, 'erro' => 'Boletim não encontrado.'];
        }
        $pronto = $this->cadastroPronto($dados);
        if (!$pronto['ok']) {
            return ['ok' => false, 'erro' => $pronto['erro']];
        }
        $cadastro = $pronto['dados'];
        $this->db->query(
            'UPDATE boletim_layouts
             SET ano_letivo = :ano, nome = :nome, finalidade = :finalidade,
                 vis_aluno = :vis_aluno, vis_pais = :vis_pais, ativo = :ativo,
                 regra_academica_id = :regra, materias_ids = :materias,
                 series_ids = :series, turmas_ids = :turmas
             WHERE id = :id',
            $this->paramsCadastro($cadastro, null) + ['id' => $id]
        );
        return ['ok' => true, 'erro' => ''];
    }

    public function excluir(int $id): void
    {
        if ($id <= 0 || !$this->identidadePronta()) {
            return;
        }
        $this->db->query('DELETE FROM boletim_layouts WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    public function salvarDoBoletim(int $id, array $linhas, ?string $formulaMedia = null): bool
    {
        $item = $this->find($id);
        if ($item === null) {
            return false;
        }
        $ano = (int) $item['ano_letivo'];
        $pacote = ['linhas' => $this->filtrar($ano, $linhas)];
        if ($formulaMedia !== null && $formulaMedia !== '') {
            $pacote['formulas'] = ['final_media' => $formulaMedia];
        }
        $json = json_encode($pacote, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $this->db->query(
            'UPDATE boletim_layouts SET layout_json = :layout WHERE id = :id',
            ['layout' => $json, 'id' => $id]
        );
        return true;
    }

    /**
     * @return list<array{codigo:string,nome:string,grupo:string}>
     */
    public function catalogo(int $ano): array
    {
        $info = PeriodoLetivo::doAno($ano);
        $rotulos = is_array($info['rotulos'] ?? null) ? $info['rotulos'] : [];
        $recuperacao = $this->mapaRecuperacao($ano);
        $colunas = [];
        foreach ($rotulos as $numero => $rotulo) {
            $numero = (int) $numero;
            foreach ($this->pecasDoPeriodo($recuperacao['no_periodo']) as $peca => $nome) {
                $colunas[] = [
                    'codigo' => $numero . '_' . $peca,
                    'nome' => $nome,
                    'grupo' => (string) $rotulo,
                ];
            }
        }
        foreach ($recuperacao['pares'] as $slot) {
            $colunas[] = [
                'codigo' => (int) $slot['codigo'] . '_rec',
                'nome' => 'Nota',
                'grupo' => (string) $slot['rotulo'],
            ];
        }
        foreach ($this->pecasFinais($recuperacao['final']) as $peca => $nome) {
            $colunas[] = [
                'codigo' => 'final_' . $peca,
                'nome' => $nome,
                'grupo' => 'Final',
            ];
        }
        return $colunas;
    }

    /**
     * @return list<array{nome:string,colunas:list<string>}>
     */
    public function padrao(int $ano): array
    {
        $info = PeriodoLetivo::doAno($ano);
        $rotulos = is_array($info['rotulos'] ?? null) ? $info['rotulos'] : [];
        $recuperacao = $this->mapaRecuperacao($ano);
        $linhas = [];
        foreach ($rotulos as $numero => $rotulo) {
            $numero = (int) $numero;
            $colunas = [$numero . '_media', $numero . '_faltas'];
            if ($recuperacao['no_periodo']) {
                $colunas[] = $numero . '_rec';
            }
            $colunas[] = $numero . '_situacao';
            $linhas[] = [
                'nome' => (string) $rotulo,
                'colunas' => $colunas,
            ];
        }
        foreach ($recuperacao['pares'] as $slot) {
            $linhas[] = [
                'nome' => mb_substr((string) $slot['rotulo'], 0, 40),
                'colunas' => [(int) $slot['codigo'] . '_rec'],
            ];
        }
        $finais = ['final_media'];
        if ($recuperacao['final']) {
            $finais[] = 'final_rec';
        }
        $finais[] = 'final_situacao';
        $linhas[] = [
            'nome' => 'Final',
            'colunas' => $finais,
        ];
        return $linhas;
    }

    /**
     * @param bool $comRecuperacao
     * @return array<string,string>
     */
    private function pecasDoPeriodo(bool $comRecuperacao): array
    {
        $pecas = [
            'media' => 'Média',
            'faltas' => 'Faltas',
        ];
        if ($comRecuperacao) {
            $pecas['rec'] = 'Recuperação';
        }
        $pecas['situacao'] = 'Situação';
        return $pecas;
    }

    /**
     * @return array<string,string>
     */
    private function pecasFinais(bool $comRecuperacao): array
    {
        $pecas = [
            'media' => 'Média',
            'faltas' => 'Faltas',
        ];
        if ($comRecuperacao) {
            $pecas['rec'] = 'Recuperação';
        }
        $pecas['situacao'] = 'Situação';
        return $pecas;
    }

    /**
     * @return array{no_periodo:bool,final:bool,pares:list<array{codigo:int,rotulo:string,grupo:string}>}
     */
    private function mapaRecuperacao(int $ano): array
    {
        $vazio = ['no_periodo' => false, 'final' => false, 'pares' => []];
        if ($ano <= 0) {
            return $vazio;
        }
        $config = new RecuperacaoConfig();
        if (!$config->tabelaPronta()) {
            return $vazio;
        }
        $noPeriodo = false;
        $final = false;
        $temPar = false;
        foreach ($config->listar($ano) as $linha) {
            $quando = (string) ($linha['quando'] ?? '');
            if ($quando === 'bimestre') {
                $noPeriodo = true;
                continue;
            }
            if ($quando === 'final') {
                $final = true;
                continue;
            }
            if ($quando !== 'par') {
                continue;
            }
            if ((string) ($linha['aplica_em'] ?? '') === 'cada') {
                $noPeriodo = true;
                continue;
            }
            $temPar = true;
        }
        $pares = [];
        if ($temPar) {
            foreach ($config->slotsParaTela($ano) as $slot) {
                $codigo = (int) ($slot['codigo'] ?? 0);
                if ($codigo < RecuperacaoConfig::CODIGO_PAR_BASE || $codigo === RecuperacaoConfig::CODIGO_FINAL) {
                    continue;
                }
                $pares[] = [
                    'codigo' => $codigo,
                    'rotulo' => (string) ($slot['rotulo'] ?? 'Recuperação'),
                    'grupo' => (string) ($slot['grupo'] ?? 'Etapas'),
                ];
            }
        }
        return ['no_periodo' => $noPeriodo, 'final' => $final, 'pares' => $pares];
    }

    /**
     * @return list<array{nome:string,colunas:list<string>}>
     */
    public function doAno(int $ano): array
    {
        if ($ano <= 0 || !$this->tabelaPronta()) {
            return $this->padrao($ano);
        }
        $row = $this->db->fetch(
            'SELECT layout_json FROM boletim_layouts WHERE ano_letivo = :ano LIMIT 1',
            ['ano' => $ano]
        );
        if (!$row) {
            return $this->padrao($ano);
        }
        $decoded = json_decode((string) ($row['layout_json'] ?? ''), true);
        $linhas = is_array($decoded['linhas'] ?? null) ? $decoded['linhas'] : [];
        return $linhas === [] ? $this->padrao($ano) : $this->filtrar($ano, $linhas);
    }

    public function formulaMediaDoAno(int $ano): string
    {
        if ($ano <= 0 || !$this->tabelaPronta()) {
            return '';
        }
        $row = $this->db->fetch(
            'SELECT layout_json FROM boletim_layouts WHERE ano_letivo = :ano LIMIT 1',
            ['ano' => $ano]
        );
        if (!$row) {
            return '';
        }
        $decoded = json_decode((string) ($row['layout_json'] ?? ''), true);
        $formulas = is_array($decoded['formulas'] ?? null) ? $decoded['formulas'] : [];
        return trim((string) ($formulas['final_media'] ?? ''));
    }

    /**
     * Médias dos períodos que a fórmula da média final pode usar.
     *
     * @return list<array{token:string,rotulo:string,exemplo:float}>
     */
    public function variaveisMedia(int $ano): array
    {
        $info = PeriodoLetivo::doAno($ano);
        $rotulos = is_array($info['rotulos'] ?? null) ? $info['rotulos'] : [];
        $exemplos = [7.5, 8.0, 6.5, 9.0, 7.0, 8.5];
        $saida = [];
        $indice = 0;
        foreach ($rotulos as $numero => $rotulo) {
            $saida[] = [
                'token' => 'm' . (int) $numero,
                'rotulo' => (string) $rotulo,
                'exemplo' => $exemplos[$indice % count($exemplos)],
            ];
            $indice++;
        }
        return $saida;
    }

    /**
     * Layout oficial ativo do ano, quando a turma e a série cabem nele.
     * Listas vazias de turma ou série valem para todas.
     *
     * @return array{id:int,nome:string,formula_final_media:string,media_minima:float,escala:string,faixas:list<array{nome:string,colunas:list<array{codigo:string,nome:string,peca:string,periodo:int}>}>}|null
     */
    public function exibicaoOficial(int $ano, int $turmaId, int $serieId): ?array
    {
        if ($ano <= 0 || !$this->identidadePronta()) {
            return null;
        }
        $colunaEscala = '';
        try {
            if ((new RegraAcademica())->escalaPronta()) {
                $colunaEscala = ', ra.escala';
            }
        } catch (\Throwable $e) {
            $colunaEscala = '';
        }
        $rows = $this->db->fetchAll(
            'SELECT bl.id, bl.turmas_ids, bl.series_ids, ra.media_minima' . $colunaEscala . '
             FROM boletim_layouts bl
             LEFT JOIN regras_academicas ra ON ra.id = bl.regra_academica_id
             WHERE bl.ano_letivo = :ano AND bl.ativo = 1 AND bl.finalidade = \'oficial\'
             ORDER BY bl.id ASC',
            ['ano' => $ano]
        ) ?: [];
        $escolhido = null;
        foreach ($rows as $row) {
            $turmas = $this->idsJson((string) ($row['turmas_ids'] ?? ''));
            $series = $this->idsJson((string) ($row['series_ids'] ?? ''));
            if ($turmas !== [] && !in_array($turmaId, $turmas, true)) {
                continue;
            }
            if ($series !== [] && !in_array($serieId, $series, true)) {
                continue;
            }
            $escolhido = $row;
            break;
        }
        if ($escolhido === null) {
            return null;
        }
        $item = $this->find((int) $escolhido['id']);
        if ($item === null) {
            return null;
        }
        $nomes = [];
        foreach ($this->catalogo($ano) as $coluna) {
            $nomes[(string) $coluna['codigo']] = (string) $coluna['nome'];
        }
        $faixas = [];
        foreach ($item['linhas'] as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $colunas = [];
            foreach ((array) ($linha['colunas'] ?? []) as $codigo) {
                $codigo = (string) $codigo;
                $parte = $this->parteDoCodigo($codigo);
                if ($parte['peca'] === '' || !isset($nomes[$codigo])) {
                    continue;
                }
                $colunas[] = [
                    'codigo' => $codigo,
                    'nome' => $nomes[$codigo],
                    'peca' => $parte['peca'],
                    'periodo' => $parte['periodo'],
                ];
            }
            if ($colunas === []) {
                continue;
            }
            $faixas[] = [
                'nome' => (string) ($linha['nome'] ?? ''),
                'colunas' => $colunas,
            ];
        }
        if ($faixas === []) {
            return null;
        }
        $minima = $escolhido['media_minima'];
        $escala = (string) ($escolhido['escala'] ?? '10');
        if (!isset(RegraAcademica::ESCALAS[$escala])) {
            $escala = '10';
        }

        return [
            'id' => (int) $item['id'],
            'nome' => (string) $item['nome'],
            'formula_final_media' => (string) ($item['formula_final_media'] ?? ''),
            'media_minima' => $minima === null || $minima === '' ? 6.0 : (float) $minima,
            'escala' => $escala,
            'faixas' => $faixas,
        ];
    }

    /**
     * Turmas do ano cobertas por um boletim oficial ativo.
     * Lista vazia de turma ou série no boletim vale para todas.
     *
     * @param list<int> $somenteTurmas
     * @return list<int>
     */
    public function turmasCobertas(int $ano, array $somenteTurmas = []): array
    {
        if ($ano <= 0 || !$this->identidadePronta()) {
            return [];
        }
        $layouts = $this->db->fetchAll(
            'SELECT turmas_ids, series_ids
             FROM boletim_layouts
             WHERE ano_letivo = :ano AND ativo = 1 AND finalidade = \'oficial\'
             ORDER BY id ASC',
            ['ano' => $ano]
        ) ?: [];
        if ($layouts === []) {
            return [];
        }
        try {
            $turmas = $this->db->fetchAll(
                'SELECT id, serie_id FROM turmas WHERE ano_letivo = :ano AND (ativo = 1 OR ativo IS NULL)',
                ['ano' => $ano]
            ) ?: [];
        } catch (\Throwable $e) {
            $turmas = $this->db->fetchAll(
                'SELECT id, serie_id FROM turmas WHERE ano_letivo = :ano',
                ['ano' => $ano]
            ) ?: [];
        }
        $filtro = [];
        foreach ($somenteTurmas as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $filtro[$id] = true;
            }
        }
        $ids = [];
        foreach ($turmas as $turma) {
            $turmaId = (int) ($turma['id'] ?? 0);
            $serieId = (int) ($turma['serie_id'] ?? 0);
            if ($turmaId <= 0 || ($filtro !== [] && !isset($filtro[$turmaId]))) {
                continue;
            }
            if ($this->layoutCobreTurma($layouts, $turmaId, $serieId)) {
                $ids[$turmaId] = $turmaId;
            }
        }

        return array_values($ids);
    }

    /**
     * Matérias do boletim oficial que cobre a turma. Vazio se nenhum boletim couber.
     *
     * @return list<array{materia_id:int,componente_nome:string,ordem:int}>
     */
    public function componentesOficial(int $ano, int $turmaId, int $serieId): array
    {
        $layout = $this->layoutOficialDaTurma($ano, $turmaId, $serieId);
        if ($layout === null) {
            return [];
        }
        $ids = $this->idsJson((string) ($layout['materias_ids'] ?? ''));
        if ($ids === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $chave = 'm' . $i;
            $ph[] = ':' . $chave;
            $params[$chave] = $id;
        }
        try {
            $nomes = $this->db->fetchAll(
                'SELECT id, nome FROM materias WHERE id IN (' . implode(',', $ph) . ')',
                $params
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $porId = [];
        foreach ($nomes as $nome) {
            $id = (int) ($nome['id'] ?? 0);
            if ($id > 0) {
                $porId[$id] = (string) ($nome['nome'] ?? 'Componente');
            }
        }
        $saida = [];
        $ordem = 0;
        foreach ($ids as $id) {
            if (!isset($porId[$id])) {
                continue;
            }
            $ordem++;
            $saida[] = [
                'materia_id' => $id,
                'componente_nome' => $porId[$id],
                'ordem' => $ordem,
            ];
        }

        return $saida;
    }

    /**
     * @param list<array<string,mixed>> $layouts
     */
    private function layoutCobreTurma(array $layouts, int $turmaId, int $serieId): bool
    {
        return $this->escolherLayoutOficial($layouts, $turmaId, $serieId) !== null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function layoutOficialDaTurma(int $ano, int $turmaId, int $serieId): ?array
    {
        if ($ano <= 0 || !$this->identidadePronta()) {
            return null;
        }
        $rows = $this->db->fetchAll(
            'SELECT id, turmas_ids, series_ids, materias_ids
             FROM boletim_layouts
             WHERE ano_letivo = :ano AND ativo = 1 AND finalidade = \'oficial\'
             ORDER BY id ASC',
            ['ano' => $ano]
        ) ?: [];

        return $this->escolherLayoutOficial($rows, $turmaId, $serieId);
    }

    /**
     * @param list<array<string,mixed>> $layouts
     * @return array<string,mixed>|null
     */
    private function escolherLayoutOficial(array $layouts, int $turmaId, int $serieId): ?array
    {
        foreach ($layouts as $layout) {
            if (!is_array($layout)) {
                continue;
            }
            $turmas = $this->idsJson((string) ($layout['turmas_ids'] ?? ''));
            $series = $this->idsJson((string) ($layout['series_ids'] ?? ''));
            if ($turmas !== [] && !in_array($turmaId, $turmas, true)) {
                continue;
            }
            if ($series !== [] && !in_array($serieId, $series, true)) {
                continue;
            }

            return $layout;
        }

        return null;
    }

    /**
     * @param array<int,float|null> $medias
     */
    public function calcularFormula(?string $formula, array $medias): ?float
    {
        return FormulaMediaFinal::calcular($formula, $medias);
    }

    /**
     * @return array{periodo:int,peca:string}
     */
    private function parteDoCodigo(string $codigo): array
    {
        if (preg_match('/^(\d+)_(media|faltas|situacao)$/', $codigo, $m)) {
            return ['periodo' => (int) $m[1], 'peca' => $m[2]];
        }
        if (preg_match('/^final_(media|faltas|rec|situacao)$/', $codigo, $m)) {
            return ['periodo' => 0, 'peca' => $m[1]];
        }
        if (preg_match('/^(?:\d+_)?rec$/', $codigo)) {
            return ['periodo' => 0, 'peca' => 'rec'];
        }

        return ['periodo' => 0, 'peca' => ''];
    }


    /**
     * @return array{ok:bool,formula:?string,erro:string}
     */
    public function normalizarFormulaMedia(int $ano, string $bruta): array
    {
        $formula = trim($bruta);
        if ($formula === '') {
            return ['ok' => true, 'formula' => null, 'erro' => ''];
        }
        if (mb_strlen($formula) > 255) {
            return ['ok' => false, 'formula' => null, 'erro' => 'A fórmula da média final pode ter no máximo 255 caracteres.'];
        }
        if (!preg_match('/^[0-9m+*\/().,\s-]+$/u', $formula)) {
            return ['ok' => false, 'formula' => null, 'erro' => 'Use as médias dos períodos (m1, m2…), números e os sinais + - * / ( ).'];
        }
        $validos = [];
        foreach ($this->variaveisMedia($ano) as $variavel) {
            $validos[$variavel['token']] = true;
        }
        if (!preg_match_all('/m\d+/', $formula, $achados) || $achados[0] === []) {
            return ['ok' => false, 'formula' => null, 'erro' => 'Inclua ao menos uma média de período, como m1.'];
        }
        foreach ($achados[0] as $token) {
            if (!isset($validos[$token])) {
                return ['ok' => false, 'formula' => null, 'erro' => 'A fórmula usa ' . $token . ', que não existe neste ano.'];
            }
        }
        if (!$this->formulaFechada($formula)) {
            return ['ok' => false, 'formula' => null, 'erro' => 'Feche a conta. Ex.: (m1 + m2 + m3 + m4) / 4.'];
        }
        return ['ok' => true, 'formula' => $formula, 'erro' => ''];
    }

    private function formulaFechada(string $formula): bool
    {
        $conta = str_replace([' ', ','], '', $formula);
        if ($conta === '' || preg_match('/^[)*\/]/', $conta) || preg_match('/[+*\/(]$/', $conta)) {
            return false;
        }
        if (str_contains($conta, '()') || preg_match('/[+*\/]{2,}/', $conta) || preg_match('/\d\(/', $conta) || preg_match('/\)\d/', $conta)) {
            return false;
        }
        $saldo = 0;
        $tamanho = strlen($conta);
        for ($i = 0; $i < $tamanho; $i++) {
            if ($conta[$i] === '(') {
                $saldo++;
            } elseif ($conta[$i] === ')') {
                $saldo--;
                if ($saldo < 0) {
                    return false;
                }
            }
        }
        return $saldo === 0;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    public function salvar(int $ano, array $linhas, ?string $formulaMedia = null): bool
    {
        if ($this->identidadePronta()) {
            return false;
        }
        $pacote = ['linhas' => $this->filtrar($ano, $linhas)];
        if ($formulaMedia !== null && $formulaMedia !== '') {
            $pacote['formulas'] = ['final_media' => $formulaMedia];
        }
        $json = json_encode($pacote, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $existe = $this->db->fetch(
            'SELECT id FROM boletim_layouts WHERE ano_letivo = :ano LIMIT 1',
            ['ano' => $ano]
        );
        if ($existe) {
            $this->db->query(
                'UPDATE boletim_layouts SET layout_json = :layout WHERE ano_letivo = :ano',
                ['layout' => $json, 'ano' => $ano]
            );
            return true;
        }
        $this->db->insert(
            'INSERT INTO boletim_layouts (ano_letivo, layout_json) VALUES (:ano, :layout)',
            ['ano' => $ano, 'layout' => $json]
        );
        return true;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array{nome:string,colunas:list<string>}>
     */
    public function filtrar(int $ano, array $linhas): array
    {
        $validos = [];
        foreach ($this->catalogo($ano) as $coluna) {
            $validos[$coluna['codigo']] = true;
        }
        $usadas = [];
        $saida = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $nome = trim((string) ($linha['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $nome = mb_substr($nome, 0, 40);
            $colunas = [];
            foreach ((array) ($linha['colunas'] ?? []) as $codigo) {
                $codigo = (string) $codigo;
                if (!isset($validos[$codigo]) || isset($usadas[$codigo])) {
                    continue;
                }
                $usadas[$codigo] = true;
                $colunas[] = $codigo;
            }
            if ($colunas === []) {
                continue;
            }
            $saida[] = ['nome' => $nome, 'colunas' => $colunas];
        }
        return $saida;
    }

    /**
     * @param array<string,mixed> $dados
     * @return array{ok:bool,erro:string,dados:array<string,mixed>}
     */
    private function cadastroPronto(array $dados): array
    {
        $vazio = ['ok' => false, 'erro' => '', 'dados' => []];
        $regraId = (int) ($dados['regra_academica_id'] ?? 0);
        $regraModel = new \App\Modulos\RegrasAcademicas\Models\RegraAcademica();
        $soRegra = $regraModel->boletimPronto();
        if ($soRegra) {
            if ($regraId <= 0) {
                $vazio['erro'] = 'Escolha a regra de aprovação.';
                return $vazio;
            }
            $pacote = $regraModel->pacoteBoletim($regraId);
            if ($pacote === null) {
                $vazio['erro'] = 'Regra de aprovação não encontrada.';
                return $vazio;
            }
            $dados = array_merge($dados, $pacote);
            $dados['regra_academica_id'] = $regraId;
            if ($pacote['materias_ids'] === []) {
                $vazio['erro'] = 'Marque as disciplinas na regra de aprovação.';
                return $vazio;
            }
            if ((int) $pacote['ano_letivo'] < 2000) {
                $vazio['erro'] = 'A regra de aprovação precisa de um ano letivo.';
                return $vazio;
            }
        }
        $nome = trim((string) ($dados['nome'] ?? ''));
        if ($nome === '') {
            $vazio['erro'] = 'Informe o nome do boletim.';
            return $vazio;
        }
        $nome = mb_substr($nome, 0, 180);
        $ano = (int) ($dados['ano_letivo'] ?? 0);
        if ($ano < 2000 || $ano > 2100) {
            $vazio['erro'] = 'Escolha o ano letivo.';
            return $vazio;
        }
        $finalidade = ((string) ($dados['finalidade'] ?? '')) === 'complementar' ? 'complementar' : 'oficial';
        $materias = $this->idsLista($dados['materias_ids'] ?? []);
        if ($materias === []) {
            $vazio['erro'] = 'Marque ao menos uma disciplina.';
            return $vazio;
        }
        $regra = (int) ($dados['regra_academica_id'] ?? 0);
        if ($regra > 0 && !$this->regraExiste($regra)) {
            $regra = 0;
        }
        return [
            'ok' => true,
            'erro' => '',
            'dados' => [
                'nome' => $nome,
                'ano_letivo' => $ano,
                'finalidade' => $finalidade,
                'vis_aluno' => !empty($dados['vis_aluno']) ? 1 : 0,
                'vis_pais' => !empty($dados['vis_pais']) ? 1 : 0,
                'ativo' => !empty($dados['ativo']) ? 1 : 0,
                'regra_academica_id' => $regra > 0 ? $regra : null,
                'materias_ids' => $materias,
                'series_ids' => $this->idsLista($dados['series_ids'] ?? []),
                'turmas_ids' => $this->idsLista($dados['turmas_ids'] ?? []),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $cadastro
     * @return array<string,mixed>
     */
    private function paramsCadastro(array $cadastro, ?string $layout): array
    {
        $materias = json_encode($cadastro['materias_ids'], JSON_UNESCAPED_UNICODE);
        $series = json_encode($cadastro['series_ids'], JSON_UNESCAPED_UNICODE);
        $turmas = json_encode($cadastro['turmas_ids'], JSON_UNESCAPED_UNICODE);
        $params = [
            'ano' => (int) $cadastro['ano_letivo'],
            'nome' => (string) $cadastro['nome'],
            'finalidade' => (string) $cadastro['finalidade'],
            'vis_aluno' => (int) $cadastro['vis_aluno'],
            'vis_pais' => (int) $cadastro['vis_pais'],
            'ativo' => (int) $cadastro['ativo'],
            'regra' => $cadastro['regra_academica_id'],
            'materias' => $materias === false ? '[]' : $materias,
            'series' => $series === false ? '[]' : $series,
            'turmas' => $turmas === false ? '[]' : $turmas,
        ];
        if ($layout !== null) {
            $params['layout'] = $layout;
        }
        return $params;
    }

    /**
     * @param mixed $bruto
     * @return list<int>
     */
    private function idsLista($bruto): array
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

    private function periodosDaLista(int $ano, string $layoutJson): int
    {
        $decoded = json_decode($layoutJson, true);
        $linhas = is_array($decoded['linhas'] ?? null) ? $decoded['linhas'] : [];
        $numeros = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            foreach ((array) ($linha['colunas'] ?? []) as $codigo) {
                if (!preg_match('/^(\d+)_/', (string) $codigo, $encontrado)) {
                    continue;
                }
                $numero = (int) $encontrado[1];
                if ($numero > 0 && $numero < 80) {
                    $numeros[$numero] = true;
                }
            }
        }
        if ($numeros !== []) {
            return count($numeros);
        }
        $rotulos = PeriodoLetivo::doAno($ano)['rotulos'] ?? [];
        return is_array($rotulos) ? count($rotulos) : 0;
    }

    /**
     * @return list<int>
     */
    private function idsJson(string $json): array
    {
        $decoded = json_decode($json, true);
        return $this->idsLista(is_array($decoded) ? $decoded : []);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findLegado(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT id, ano_letivo, layout_json FROM boletim_layouts WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$row) {
            return null;
        }
        $ano = (int) ($row['ano_letivo'] ?? 0);
        $decoded = json_decode((string) ($row['layout_json'] ?? ''), true);
        $linhas = is_array($decoded['linhas'] ?? null) ? $decoded['linhas'] : [];
        $formulas = is_array($decoded['formulas'] ?? null) ? $decoded['formulas'] : [];
        return [
            'id' => (int) $row['id'],
            'ano_letivo' => $ano,
            'nome' => 'Boletim oficial',
            'finalidade' => 'oficial',
            'vis_aluno' => 1,
            'vis_pais' => 1,
            'ativo' => 1,
            'regra_academica_id' => 0,
            'materias_ids' => [],
            'series_ids' => [],
            'turmas_ids' => [],
            'linhas' => $linhas === [] ? $this->padrao($ano) : $this->filtrar($ano, $linhas),
            'formula_final_media' => trim((string) ($formulas['final_media'] ?? '')),
        ];
    }

    private function regraExiste(int $id): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT id FROM regras_academicas WHERE id = :id LIMIT 1',
                ['id' => $id]
            );
            return !empty($row['id']);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
