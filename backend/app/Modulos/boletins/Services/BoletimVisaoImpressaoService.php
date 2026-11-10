<?php

namespace App\Modulos\Boletins\Services;

use App\Modulos\Boletins\Models\BoletimConfig;
use App\Modulos\EventosAvaliativos\Models\BoletimLayout;

/**
 * Visão de impressão do Boletim: regra boletim_regras (exibir_em=boletim)
 * ligada a um boletim_layouts, editável com o builder completo de colunas.
 */
class BoletimVisaoImpressaoService
{
    private BoletimConfig $config;
    private BoletimLayout $layouts;

    public function __construct(?BoletimConfig $config = null, ?BoletimLayout $layouts = null)
    {
        $this->config = $config ?? new BoletimConfig();
        $this->config->ensureSchema();
        $this->layouts = $layouts ?? new BoletimLayout();
    }

    /**
     * Garante uma regra de impressão para o layout e devolve o id.
     *
     * @return array{ok:bool,regra_id:int,criada:bool,erro?:string}
     */
    public function garantirRegraDoLayout(int $layoutId): array
    {
        $layout = $this->layouts->find($layoutId);
        if (!is_array($layout) || (int) ($layout['id'] ?? 0) <= 0) {
            return ['ok' => false, 'regra_id' => 0, 'criada' => false, 'erro' => 'Boletim não encontrado.'];
        }

        $existente = $this->encontrarRegraDoLayout($layoutId);
        if ($existente > 0) {
            return ['ok' => true, 'regra_id' => $existente, 'criada' => false];
        }

        $nome = trim((string) ($layout['nome'] ?? ''));
        if ($nome === '') {
            $nome = 'Boletim impressão';
        }
        $nome = $nome . ' — impressão';
        $ano = (int) ($layout['ano_letivo'] ?? 0);
        $finalidade = ((string) ($layout['finalidade'] ?? '')) === 'complementar' ? 'complementar' : 'oficial';
        $materiasJson = $this->jsonIds($this->materiasDoLayoutParaImpressao($layout));
        $seriesJson = $this->jsonIds($layout['series_ids'] ?? null);
        $turmasJson = $this->jsonIds($layout['turmas_ids'] ?? null);
        $extras = json_encode([
            'layout_boletim_id' => $layoutId,
            'quadro_livre' => 0,
            'origem' => 'composto',
        ], JSON_UNESCAPED_UNICODE);

        try {
            $regraId = $this->config->saveRule(
                $nome,
                '',
                [],
                null,
                'Visão de impressão do boletim (colunas e cálculos).',
                $materiasJson,
                null,
                null,
                $seriesJson,
                $turmasJson,
                'boletim',
                $ano > 0 ? $ano : null,
                null,
                (int) ($layout['vis_aluno'] ?? 1) === 1 ? 1 : 0,
                (int) ($layout['vis_pais'] ?? 1) === 1 ? 1 : 0,
                1,
                'none',
                2,
                null,
                null,
                null,
                1,
                is_string($extras) ? $extras : null,
                $finalidade
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'regra_id' => 0, 'criada' => false, 'erro' => $e->getMessage()];
        }

        if ($regraId <= 0) {
            return ['ok' => false, 'regra_id' => 0, 'criada' => false, 'erro' => 'Não foi possível criar a visão de impressão.'];
        }

        $this->config->mesclarExtrasJson($regraId, [
            'layout_boletim_id' => $layoutId,
            'quadro_livre' => 0,
            'origem' => 'composto',
        ]);

        return ['ok' => true, 'regra_id' => $regraId, 'criada' => true];
    }

    /**
     * Abre o builder editável do boletim (Demonstrativos + eventos + fórmula)
     * sem renomear a regra existente.
     *
     * @return array{ok:bool,regra_id:int,erro?:string}
     */
    public function prepararMontarQuadro(int $layoutId): array
    {
        $garantida = $this->garantirRegraDoLayout($layoutId);
        if (empty($garantida['ok']) || (int) ($garantida['regra_id'] ?? 0) <= 0) {
            return [
                'ok' => false,
                'regra_id' => 0,
                'erro' => (string) ($garantida['erro'] ?? 'Falha ao preparar visão.'),
            ];
        }
        $regraId = (int) $garantida['regra_id'];
        $db = \App\Core\Database::getInstance();
        $db->update(
            "UPDATE boletim_regras
                SET exibir_em = 'boletim', bimestre = NULL
              WHERE id = :id AND ativo = 1",
            ['id' => $regraId]
        );
        $this->config->mesclarExtrasJson($regraId, [
            'layout_boletim_id' => $layoutId,
            'quadro_livre' => 1,
            'origem' => 'quadro-impressao',
            'modelo_santa_maria' => 0,
        ]);
        // Matérias do quadro = regra de aprovação do modelo (com desdobramentos).
        $this->sincronizarMateriasDaRegraAcademica($layoutId, $regraId);

        return ['ok' => true, 'regra_id' => $regraId];
    }

    /**
     * Prepara visão livre: builder de colunas/cabeçalhos do zero.
     *
     * @return array{ok:bool,regra_id:int,erro?:string}
     */
    public function prepararQuadroLivre(int $layoutId): array
    {
        $base = $this->prepararMontarQuadro($layoutId);
        if (empty($base['ok']) || (int) ($base['regra_id'] ?? 0) <= 0) {
            return $base;
        }
        $regraId = (int) $base['regra_id'];
        $layout = $this->layouts->find($layoutId) ?: [];
        $nome = trim((string) ($layout['nome'] ?? 'Boletim'));
        if ($nome === '') {
            $nome = 'Boletim';
        }

        $db = \App\Core\Database::getInstance();
        $db->update(
            "UPDATE boletim_regras
                SET nome = :nome
              WHERE id = :id AND ativo = 1",
            ['id' => $regraId, 'nome' => $nome . ' — impressão (do zero)']
        );

        return ['ok' => true, 'regra_id' => $regraId];
    }

    /**
     * Prepara visão composta: selecionar Demonstrativos (notas) por bimestre.
     *
     * @return array{ok:bool,regra_id:int,fontes:array<int,int>,erro?:string}
     */
    public function prepararQuadroComposto(int $layoutId): array
    {
        $garantida = $this->garantirRegraDoLayout($layoutId);
        if (empty($garantida['ok']) || (int) ($garantida['regra_id'] ?? 0) <= 0) {
            return [
                'ok' => false,
                'regra_id' => 0,
                'fontes' => [],
                'erro' => (string) ($garantida['erro'] ?? 'Falha ao preparar visão.'),
            ];
        }
        $regraId = (int) $garantida['regra_id'];
        $layout = $this->layouts->find($layoutId) ?: [];
        $ano = (int) ($layout['ano_letivo'] ?? 0);
        $fontes = $this->detectarFontesBimestresSantaMaria($ano);

        $db = \App\Core\Database::getInstance();
        $db->update(
            "UPDATE boletim_regras SET exibir_em = 'boletim' WHERE id = :id AND ativo = 1",
            ['id' => $regraId]
        );
        $this->config->mesclarExtrasJson($regraId, [
            'layout_boletim_id' => $layoutId,
            'quadro_livre' => 0,
            'origem' => 'composto',
            'fontes_bimestres' => $fontes,
        ]);

        return ['ok' => true, 'regra_id' => $regraId, 'fontes' => $fontes];
    }

    /**
     * Monta a visão de impressão no formato do print Santa Maria
     * (PP/PU/PE/MU/FAL por bimestre), ligando aos demonstrativos de notas do ano.
     *
     * @return array{ok:bool,regra_id:int,fontes:array<int,int>,erro?:string,aviso?:string}
     */
    public function aplicarPrintSantaMaria(int $layoutId): array
    {
        $layout = $this->layouts->find($layoutId);
        if (!is_array($layout) || (int) ($layout['id'] ?? 0) <= 0) {
            return ['ok' => false, 'regra_id' => 0, 'fontes' => [], 'erro' => 'Boletim não encontrado.'];
        }
        $ano = (int) ($layout['ano_letivo'] ?? 0);
        if ($ano < 2000) {
            return ['ok' => false, 'regra_id' => 0, 'fontes' => [], 'erro' => 'Ano letivo do boletim inválido.'];
        }

        $fontes = $this->detectarFontesBimestresSantaMaria($ano);
        $comFonte = 0;
        foreach ($fontes as $idFonte) {
            if ($idFonte > 0) {
                $comFonte++;
            }
        }
        if ($comFonte === 0) {
            return [
                'ok' => false,
                'regra_id' => 0,
                'fontes' => $fontes,
                'erro' => 'Não achei Demonstrativo de notas com PP/PU (ou eventos por bimestre) em '
                    . $ano . '. Configure o Demonstrativo no formato Santa Maria antes, ou use Importar modelo (IA) com o print.',
            ];
        }

        $garantida = $this->garantirRegraDoLayout($layoutId);
        if (empty($garantida['ok']) || (int) ($garantida['regra_id'] ?? 0) <= 0) {
            return [
                'ok' => false,
                'regra_id' => 0,
                'fontes' => $fontes,
                'erro' => (string) ($garantida['erro'] ?? 'Falha ao preparar visão de impressão.'),
            ];
        }
        $regraId = (int) $garantida['regra_id'];

        $db = \App\Core\Database::getInstance();
        $db->update(
            "UPDATE boletim_regras SET exibir_em = 'boletim', formula_final = :f WHERE id = :id AND ativo = 1",
            ['id' => $regraId, 'f' => 'media_final']
        );

        $nome = trim((string) ($layout['nome'] ?? 'Boletim'));
        if ($nome === '') {
            $nome = 'Boletim';
        }
        $this->config->atualizarEventoGerado($regraId, [
            'nome' => $nome . ' — print Santa Maria',
        ]);

        $this->config->mesclarExtrasJson($regraId, [
            'layout_boletim_id' => $layoutId,
            'modelo_santa_maria' => 1,
            'quadro_livre' => 0,
            'origem' => 'santa-maria',
            'fontes_bimestres' => $fontes,
            'linhas_layout' => $this->linhasLayoutSantaMaria(),
            'rotulos_colunas' => [
                'PP' => 'PP',
                'PU' => 'PU',
                'PE' => 'PE',
                'media_final' => 'MU',
                'faltas' => 'FAL',
                'CH' => 'CH',
            ],
        ]);

        $aviso = $comFonte < 4
            ? ('Encontrei ' . $comFonte . ' de 4 bimestres no Demonstrativo. Complete os períodos faltantes no assistente.')
            : '';

        return [
            'ok' => true,
            'regra_id' => $regraId,
            'fontes' => $fontes,
            'aviso' => $aviso,
        ];
    }

    /**
     * Cabeçalhos do print (I–IV bimestre, semestres, resultado final).
     *
     * @return list<array{id:string,nome:string,colunas:list<string>}>
     */
    public function linhasLayoutSantaMaria(): array
    {
        return [
            ['id' => 'sm_b1', 'nome' => 'I BIMESTRE', 'colunas' => ['b1_pp', 'b1_pu', 'b1_ch', 'b1_pe', 'b1_mu', 'b1_fal']],
            ['id' => 'sm_b2', 'nome' => 'II BIMESTRE', 'colunas' => ['b2_pp', 'b2_pu', 'b2_ch', 'b2_pe', 'b2_mu', 'b2_fal']],
            ['id' => 'sm_s1', 'nome' => '1º SEMESTRE', 'colunas' => ['s1_rec', 's1_ms']],
            ['id' => 'sm_b3', 'nome' => 'III BIMESTRE', 'colunas' => ['b3_pp', 'b3_pu', 'b3_ch', 'b3_pe', 'b3_mu', 'b3_fal']],
            ['id' => 'sm_b4', 'nome' => 'IV BIMESTRE', 'colunas' => ['b4_pp', 'b4_pu', 'b4_ch', 'b4_pe', 'b4_mu', 'b4_fal']],
            ['id' => 'sm_s2', 'nome' => '2º SEMESTRE', 'colunas' => ['s2_rec', 's2_ms']],
            ['id' => 'sm_fin', 'nome' => 'RESULTADO FINAL', 'colunas' => ['ma', 'pf', 'mf', 'situacao']],
        ];
    }

    /**
     * @return array<int,int> bimestre => regra_id
     */
    public function detectarFontesBimestresSantaMaria(int $ano): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        if ($ano < 2000) {
            return $out;
        }
        $candidatos = [];
        foreach ($this->config->listAllRules(400) as $row) {
            if (strtolower(trim((string) ($row['exibir_em'] ?? ''))) !== 'notas') {
                continue;
            }
            if ((int) ($row['ano_letivo'] ?? 0) !== $ano) {
                continue;
            }
            $bim = (int) ($row['bimestre'] ?? 0);
            if ($bim < 1 || $bim > 4) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $score = 0;
            $extras = json_decode((string) ($row['extras_json'] ?? ''), true);
            if (is_array($extras) && !empty($extras['modelo_santa_maria'])) {
                $score += 100;
            }
            $nome = mb_strtolower(trim((string) ($row['nome'] ?? '')));
            $codigo = mb_strtolower(trim((string) ($row['codigo'] ?? '')));
            if (str_contains($nome, 'santa maria') || str_contains($codigo, 'sm24') || str_contains($codigo, 'santa')) {
                $score += 40;
            }
            if ($this->regraTemColunasSantaMaria($id)) {
                $score += 50;
            }
            $score += 1; // qualquer notas do bimestre
            if (!isset($candidatos[$bim]) || $score > $candidatos[$bim]['score']) {
                $candidatos[$bim] = ['id' => $id, 'score' => $score];
            }
        }
        foreach ([1, 2, 3, 4] as $bim) {
            $out[$bim] = (int) ($candidatos[$bim]['id'] ?? 0);
        }
        return $out;
    }

    private function regraTemColunasSantaMaria(int $regraId): bool
    {
        if ($regraId <= 0) {
            return false;
        }
        $db = \App\Core\Database::getInstance();
        try {
            $row = $db->fetch(
                "SELECT COUNT(*) AS n FROM boletim_componentes
                  WHERE regra_id = :id AND ativo = 1
                    AND UPPER(codigo) IN ('PP','PU','PE','MU','CH','FAL','REC')",
                ['id' => $regraId]
            );
            return (int) ($row['n'] ?? 0) >= 2;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Copia colunas/componentes de um demonstrativo (exibir_em=notas) para a visão de impressão.
     *
     * @return array{ok:bool,regra_id:int,erro?:string}
     */
    public function puxarDoDemonstrativo(int $layoutId, int $regraNotasId): array
    {
        $garantida = $this->garantirRegraDoLayout($layoutId);
        if (empty($garantida['ok'])) {
            return ['ok' => false, 'regra_id' => 0, 'erro' => (string) ($garantida['erro'] ?? 'Falha ao preparar visão.')];
        }
        $destinoId = (int) $garantida['regra_id'];
        if ($regraNotasId <= 0) {
            return ['ok' => false, 'regra_id' => $destinoId, 'erro' => 'Selecione um demonstrativo de notas.'];
        }

        $fonte = $this->config->getRuleById($regraNotasId);
        if (!is_array($fonte)) {
            return ['ok' => false, 'regra_id' => $destinoId, 'erro' => 'Demonstrativo não encontrado.'];
        }
        $exibir = strtolower(trim((string) ($fonte['exibir_em'] ?? '')));
        if ($exibir !== 'notas') {
            return ['ok' => false, 'regra_id' => $destinoId, 'erro' => 'A fonte precisa ser um Demonstrativo de notas.'];
        }

        try {
            $copiaId = $this->config->duplicateRule($regraNotasId);
            if ($copiaId === null || $copiaId <= 0) {
                return ['ok' => false, 'regra_id' => $destinoId, 'erro' => 'Não foi possível copiar o demonstrativo.'];
            }

            // A cópia veio com exibir_em=notas; promovemos para a visão de impressão
            // substituindo o destino (ou atualizando a cópia e desativando o destino antigo).
            $layout = $this->layouts->find($layoutId) ?: [];
            $nome = trim((string) ($layout['nome'] ?? 'Boletim')) . ' — impressão';
            $this->config->atualizarEventoGerado($copiaId, [
                'nome' => $nome,
            ]);
            $this->promoverParaImpressao($copiaId, $layoutId, $destinoId);

            return ['ok' => true, 'regra_id' => $copiaId];
        } catch (\Throwable $e) {
            return ['ok' => false, 'regra_id' => $destinoId, 'erro' => $e->getMessage()];
        }
    }

    /**
     * @return list<array{id:int,nome:string,ano_letivo:?int,bimestre:?int}>
     */
    public function listarDemonstrativosDoAno(int $ano): array
    {
        $out = [];
        foreach ($this->config->listAllRules(400) as $row) {
            if (strtolower(trim((string) ($row['exibir_em'] ?? ''))) !== 'notas') {
                continue;
            }
            $anoLinha = (int) ($row['ano_letivo'] ?? 0);
            if ($ano > 0 && $anoLinha > 0 && $anoLinha !== $ano) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($row['nome'] ?? ('Evento #' . $id))),
                'ano_letivo' => $anoLinha > 0 ? $anoLinha : null,
                'bimestre' => isset($row['bimestre']) ? (int) $row['bimestre'] : null,
            ];
        }
        return $out;
    }

    public function encontrarRegraDoLayout(int $layoutId): int
    {
        if ($layoutId <= 0) {
            return 0;
        }
        $db = \App\Core\Database::getInstance();
        try {
            $row = $db->fetch(
                "SELECT id FROM boletim_regras
                 WHERE ativo = 1 AND exibir_em = 'boletim'
                   AND extras_json IS NOT NULL
                   AND JSON_VALID(extras_json)
                   AND CAST(JSON_UNQUOTE(JSON_EXTRACT(extras_json, '$.layout_boletim_id')) AS UNSIGNED) = :lid
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1",
                ['lid' => $layoutId]
            );
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                return (int) $row['id'];
            }
        } catch (\Throwable $e) {
            // Fallback sem JSON_EXTRACT (MySQL antigo / extras inválido)
        }
        try {
            $rows = $db->fetchAll(
                "SELECT id, extras_json FROM boletim_regras
                 WHERE ativo = 1 AND exibir_em = 'boletim'
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 300"
            ) ?: [];
        } catch (\Throwable $e) {
            return 0;
        }
        foreach ($rows as $row) {
            $extras = json_decode((string) ($row['extras_json'] ?? ''), true);
            if (!is_array($extras)) {
                continue;
            }
            if ((int) ($extras['layout_boletim_id'] ?? 0) === $layoutId) {
                return (int) ($row['id'] ?? 0);
            }
        }
        return 0;
    }

    private function promoverParaImpressao(int $copiaId, int $layoutId, int $destinoAntigoId): void
    {
        $db = \App\Core\Database::getInstance();
        $db->update(
            "UPDATE boletim_regras SET exibir_em = 'boletim' WHERE id = :id AND ativo = 1",
            ['id' => $copiaId]
        );
        $this->config->mesclarExtrasJson($copiaId, [
            'layout_boletim_id' => $layoutId,
            'quadro_livre' => 1,
            'origem' => 'quadro-impressao',
            'importado_de_notas' => 1,
        ]);
        if ($destinoAntigoId > 0 && $destinoAntigoId !== $copiaId) {
            $this->config->deactivateRule($destinoAntigoId);
        }
    }

    /**
     * Matérias lançáveis do modelo: regra de aprovação (oficiais → desdobramentos).
     *
     * @param array<string,mixed> $layout
     * @return list<int>
     */
    private function materiasDoLayoutParaImpressao(array $layout): array
    {
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
            $bruto = $layout['materias_ids'] ?? [];
            if (is_string($bruto) && $bruto !== '') {
                $decoded = json_decode($bruto, true);
                $bruto = is_array($decoded) ? $decoded : [];
            }
            $ids = array_values(array_filter(array_map('intval', (array) $bruto), static fn ($id) => $id > 0));
        }
        if ($ids === []) {
            return [];
        }
        try {
            $comp = new \App\Dominio\Escola\Models\ComponenteCurricular();
            $ids = $comp->expandirIdsComFilhos($ids);
            $pais = $comp->idsComFilhos();
            $ids = array_values(array_filter($ids, static fn ($id) => $id > 0 && empty($pais[$id])));
        } catch (\Throwable $e) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
        }

        return $ids;
    }

    /**
     * Espelha as matérias da regra de aprovação no layout e na visão de impressão.
     */
    private function sincronizarMateriasDaRegraAcademica(int $layoutId, int $boletimRegraId): void
    {
        if ($layoutId <= 0) {
            return;
        }
        $layout = $this->layouts->find($layoutId);
        if (!is_array($layout)) {
            return;
        }
        $ids = $this->materiasDoLayoutParaImpressao($layout);
        $json = $ids === [] ? null : json_encode(array_values($ids), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        $db = \App\Core\Database::getInstance();
        try {
            $db->query(
                'UPDATE boletim_layouts SET materias_ids = :mats WHERE id = :id',
                ['mats' => $json, 'id' => $layoutId]
            );
        } catch (\Throwable $e) {
            // layout sem coluna / schema antigo
        }
        if ($boletimRegraId > 0) {
            try {
                $db->query(
                    'UPDATE boletim_regras SET materias_ids = :mats WHERE id = :id AND ativo = 1',
                    ['mats' => $json, 'id' => $boletimRegraId]
                );
            } catch (\Throwable $e) {
                // regra sem coluna
            }
        }
    }

    /**
     * @param mixed $raw
     */
    private function jsonIds($raw): ?string
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $ids = array_values(array_filter(array_map('intval', $decoded), static fn ($v) => $v > 0));
                return $ids === [] ? null : json_encode($ids);
            }
            return null;
        }
        if (!is_array($raw)) {
            return null;
        }
        $ids = array_values(array_filter(array_map('intval', $raw), static fn ($v) => $v > 0));
        return $ids === [] ? null : json_encode($ids);
    }
}
