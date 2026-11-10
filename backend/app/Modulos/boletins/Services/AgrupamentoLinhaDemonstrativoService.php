<?php

namespace App\Modulos\Boletins\Services;

use App\Core\Database;
use App\Dominio\Escola\Models\ComponenteCurricular;
use App\Modulos\Boletins\Models\BoletimConfig;

/**
 * Lê/grava group_line do Demonstrativo de notas (boletim_componentes.config_json).
 * Usado pelo formulário de Evento Avaliativo (seção Disciplina → Agrupar).
 */
class AgrupamentoLinhaDemonstrativoService
{
    private BoletimConfig $boletimConfig;

    public function __construct(?BoletimConfig $boletimConfig = null)
    {
        $this->boletimConfig = $boletimConfig ?? new BoletimConfig();
    }

    /**
     * @return array{
     *   disponivel:bool,
     *   motivo:?string,
     *   demonstrativo_id:int,
     *   demonstrativo_nome:string,
     *   assistente_url:string,
     *   grupo:array<string,mixed>,
     *   familias:list<array{pai_id:int,nome:string,filhos:list<array{id:int,nome:string}>,materias_ids:list<int>}>,
     *   familias_titulo:array<int, array{ativo:bool,modo:string}>,
     *   tem_ajuste_peca:bool
     * }
     */
    public function dadosParaFormulario(int $ano, int $bimestre, array $materiasEscopoIds = []): array
    {
        $vazio = [
            'disponivel' => false,
            'motivo' => null,
            'demonstrativo_id' => 0,
            'demonstrativo_nome' => '',
            'assistente_url' => '',
            'grupo' => $this->grupoPadrao(),
            'familias' => [],
            'familias_titulo' => [],
            'tem_ajuste_peca' => false,
        ];

        $resolvido = $this->resolverDemonstrativo($ano, $bimestre);
        if (empty($resolvido['ok'])) {
            $vazio['motivo'] = (string) ($resolvido['erro'] ?? 'Demonstrativo indisponível.');
            $vazio['familias'] = $this->familiasComponentes($materiasEscopoIds);

            return $vazio;
        }

        $regraId = (int) ($resolvido['id'] ?? 0);
        $regra = $this->boletimConfig->getRuleById($regraId);
        if ($regra === null) {
            $vazio['motivo'] = 'Demonstrativo não encontrado.';

            return $vazio;
        }

        $lido = $this->lerGrupoDaRegra($regra);
        $familias = $this->familiasComponentes($materiasEscopoIds);
        if ($familias === [] && $materiasEscopoIds === []) {
            $familias = $this->familiasComponentes(
                array_map('intval', (array) (json_decode((string) ($regra['materias_ids'] ?? '[]'), true) ?: []))
            );
        }

        return [
            'disponivel' => true,
            'motivo' => null,
            'demonstrativo_id' => $regraId,
            'demonstrativo_nome' => trim((string) ($regra['nome'] ?? '')),
            'assistente_url' => '/admin/boletim-configuracao/assistente?regra_id=' . $regraId,
            'grupo' => $lido['grupo'],
            'familias' => $familias,
            'familias_titulo' => $this->mapearFamiliasTitulo($familias, $lido['grupos']),
            'tem_ajuste_peca' => $lido['tem_ajuste_peca'],
        ];
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool,error?:string,skipped?:bool}
     */
    public function salvarDoPost(int $ano, int $bimestre, array $post): array
    {
        if (empty($post['grupo_linha_enviar'])) {
            return ['success' => true, 'skipped' => true];
        }

        $resolvido = $this->resolverDemonstrativo($ano, $bimestre);
        if (empty($resolvido['ok'])) {
            return ['success' => false, 'error' => (string) ($resolvido['erro'] ?? 'Demonstrativo indisponível.')];
        }

        $regraIdPost = (int) ($post['grupo_demonstrativo_id'] ?? 0);
        $regraId = (int) ($resolvido['id'] ?? 0);
        if ($regraIdPost > 0 && $regraIdPost !== $regraId) {
            return ['success' => false, 'error' => 'O Demonstrativo mudou. Recarregue a página e tente de novo.'];
        }

        $familiasTituloPost = $this->familiasTituloDoPost($post);
        if ($familiasTituloPost !== []) {
            $ok = $this->gravarGroupLinesNaRegra($regraId, $familiasTituloPost);
            return $ok
                ? ['success' => true]
                : ['success' => false, 'error' => 'Não foi possível salvar os títulos no Demonstrativo.'];
        }

        $ativo = !empty($post['grupo_ativo']);
        // Sem títulos marcados e sem linha única → limpa. Com grupo_ativo, segue o fluxo legado.
        if (!$ativo) {
            $ok = $this->gravarGroupLinesNaRegra($regraId, []);
            return $ok
                ? ['success' => true]
                : ['success' => false, 'error' => 'Não foi possível desligar o agrupamento.'];
        }

        $ids = [];
        foreach ((array) ($post['grupo_materias'] ?? []) as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        $nome = trim((string) ($post['grupo_nome'] ?? ''));
        $familiaId = (int) ($post['grupo_familia'] ?? 0);
        if (($nome === '' || count($ids) < 2) && $familiaId > 0) {
            foreach ($this->familiasComponentes([]) as $fam) {
                if ((int) ($fam['pai_id'] ?? 0) !== $familiaId) {
                    continue;
                }
                if ($nome === '') {
                    $nome = trim((string) ($fam['nome'] ?? ''));
                }
                if (count($ids) < 2) {
                    $ids = array_values(array_map('intval', (array) ($fam['materias_ids'] ?? [])));
                }
                break;
            }
        }
        $modo = strtolower(trim((string) ($post['grupo_modo'] ?? 'media'))) === 'soma' ? 'soma' : 'media';
        $aplicar = strtolower(trim((string) ($post['grupo_aplicar'] ?? 'boletim'))) === 'ambos' ? 'ambos' : 'boletim';
        $arred = strtolower(trim((string) ($post['grupo_arred'] ?? 'todos')));
        if ($arred === 'mãe') {
            $arred = 'mae';
        }
        if (!in_array($arred, ['todos', 'filhas', 'mae'], true)) {
            $arred = 'todos';
        }
        $ocultarFilhas = !empty($post['grupo_exemplo_sem_filhas']);

        if ($nome === '' || count($ids) < 2) {
            return ['success' => false, 'error' => 'Para agrupar, escolha a área e ao menos duas matérias.'];
        }

        $cfg = [
            'enabled' => true,
            'key' => $this->slugGrupo($nome),
            'label' => mb_substr($nome, 0, 80),
            'mode' => $modo,
            'modo_padrao' => $modo,
            'divisor' => 1.0,
            'materias_ids' => $ids,
            'aplicar_em' => $aplicar,
            'arredondamento' => $arred,
            'ocultar_filhas' => $ocultarFilhas,
        ];

        $ok = $this->gravarGroupLinesNaRegra($regraId, [$cfg]);
        return $ok
            ? ['success' => true]
            : ['success' => false, 'error' => 'Não foi possível salvar o agrupamento no Demonstrativo.'];
    }

    /**
     * @return array{ok:bool,id?:int,erro?:string}
     */
    public function resolverDemonstrativo(int $ano, int $bimestre): array
    {
        if ($ano < 2000 || $ano > 2100 || $bimestre < 1 || $bimestre > 4) {
            return ['ok' => false, 'erro' => 'Informe o ano e o bimestre do evento para agrupar matérias.'];
        }

        $candidatos = [];
        foreach ($this->boletimConfig->listarEventosNotasAtivos() as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['ano_letivo'] ?? 0) !== $ano) {
                continue;
            }
            if ((int) ($row['bimestre'] ?? 0) !== $bimestre) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $candidatos[$id] = $row;
            }
        }

        if ($candidatos === []) {
            return [
                'ok' => false,
                'erro' => 'Não há Demonstrativo de notas neste bimestre. Crie o Demonstrativo e volte para agrupar.',
            ];
        }
        if (count($candidatos) > 1) {
            return [
                'ok' => false,
                'erro' => 'Há mais de um Demonstrativo neste bimestre. Configure o agrupamento no assistente do Demonstrativo.',
            ];
        }

        $unico = reset($candidatos);

        return ['ok' => true, 'id' => (int) ($unico['id'] ?? 0)];
    }

    /**
     * @param array<string,mixed> $regra
     * @return array{grupo:array<string,mixed>,grupos:list<array<string,mixed>>,tem_ajuste_peca:bool}
     */
    private function lerGrupoDaRegra(array $regra): array
    {
        $grupo = $this->grupoPadrao();
        $grupos = [];
        $temAjuste = false;
        $modosVistos = [];
        $vistos = [];

        foreach ((array) ($regra['componentes'] ?? []) as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $cfg = json_decode((string) ($comp['config_json'] ?? ''), true);
            if (!is_array($cfg)) {
                $cfg = is_array($comp['config'] ?? null) ? $comp['config'] : [];
            }
            $gl = is_array($cfg['group_line'] ?? null) ? $cfg['group_line'] : null;
            if ($gl === null || empty($gl['enabled'])) {
                continue;
            }

            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array) ($gl['materias_ids'] ?? [])),
                static fn ($id) => $id > 0
            )));
            if (count($ids) < 2) {
                continue;
            }
            $modo = strtolower(trim((string) ($gl['mode'] ?? $gl['modo_padrao'] ?? 'media')));
            $modo = $modo === 'soma' ? 'soma' : 'media';
            $aplicar = strtolower(trim((string) ($gl['aplicar_em'] ?? 'boletim'))) === 'ambos' ? 'ambos' : 'boletim';
            $arred = strtolower(trim((string) ($gl['arredondamento'] ?? 'todos')));
            if ($arred === 'mãe') {
                $arred = 'mae';
            }
            if (!in_array($arred, ['todos', 'filhas', 'mae'], true)) {
                $arred = 'todos';
            }
            $label = trim((string) ($gl['label'] ?? ''));
            $chaveUnica = strtolower($label) . '|' . implode(',', $ids);
            if (isset($vistos[$chaveUnica])) {
                continue;
            }
            $vistos[$chaveUnica] = true;

            $item = [
                'ativo' => true,
                'nome' => $label,
                'modo' => $modo,
                'materias_ids' => $ids,
                'aplicar_em' => $aplicar,
                'arredondamento' => $arred,
                'exemplo_sem_filhas' => !empty($gl['ocultar_filhas']),
                'agrupamento_id' => max(0, (int) ($gl['agrupamento_id'] ?? 0)),
                'key' => (string) ($gl['key'] ?? $this->slugGrupo($label !== '' ? $label : 'area')),
            ];
            $grupos[] = $item;
            $grupo = $item;

            $modoPeca = strtolower(trim((string) ($gl['mode'] ?? '')));
            $modoPadrao = strtolower(trim((string) ($gl['modo_padrao'] ?? $modo)));
            if ($modoPeca !== '' && $modoPadrao !== '' && $modoPeca !== $modoPadrao) {
                $temAjuste = true;
            }
            $modosVistos[$modo] = true;
            if (count($modosVistos) > 1) {
                $temAjuste = true;
            }
        }

        return ['grupo' => $grupo, 'grupos' => $grupos, 'tem_ajuste_peca' => $temAjuste];
    }

    /**
     * @param array<string,mixed> $post
     * @return list<array<string,mixed>>
     */
    private function familiasTituloDoPost(array $post): array
    {
        $paisMarcados = [];
        foreach ((array) ($post['familia_titulo'] ?? []) as $raw) {
            $paiId = (int) $raw;
            if ($paiId > 0) {
                $paisMarcados[$paiId] = true;
            }
        }
        if ($paisMarcados === []) {
            return [];
        }

        $modosPost = is_array($post['familia_modo'] ?? null) ? $post['familia_modo'] : [];
        $aplicar = strtolower(trim((string) ($post['grupo_aplicar'] ?? 'boletim'))) === 'ambos' ? 'ambos' : 'boletim';
        $arred = strtolower(trim((string) ($post['grupo_arred'] ?? 'todos')));
        if ($arred === 'mãe') {
            $arred = 'mae';
        }
        if (!in_array($arred, ['todos', 'filhas', 'mae'], true)) {
            $arred = 'todos';
        }
        $ocultarFilhas = !empty($post['grupo_exemplo_sem_filhas']);

        $out = [];
        foreach ($this->familiasComponentes([]) as $fam) {
            $paiId = (int) ($fam['pai_id'] ?? 0);
            if ($paiId <= 0 || empty($paisMarcados[$paiId])) {
                continue;
            }
            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array) ($fam['materias_ids'] ?? [])),
                static fn ($id) => $id > 0
            )));
            if (count($ids) < 2) {
                continue;
            }
            $nome = trim((string) ($fam['nome'] ?? ''));
            if ($nome === '') {
                $nome = 'Área';
            }
            $modoRaw = $modosPost[$paiId] ?? ($modosPost[(string) $paiId] ?? 'media');
            $modo = strtolower(trim((string) $modoRaw)) === 'soma' ? 'soma' : 'media';
            $out[] = [
                'enabled' => true,
                'key' => $this->slugGrupo($nome),
                'label' => function_exists('mb_substr') ? mb_substr($nome, 0, 80) : substr($nome, 0, 80),
                'mode' => $modo,
                'modo_padrao' => $modo,
                'divisor' => 1.0,
                'materias_ids' => $ids,
                'aplicar_em' => $aplicar,
                'arredondamento' => $arred,
                'ocultar_filhas' => $ocultarFilhas,
                'pai_id' => $paiId,
            ];
        }

        return $out;
    }

    /**
     * @param list<array{pai_id:int,nome:string,materias_ids:list<int>}> $familias
     * @param list<array<string,mixed>> $grupos
     * @return array<int, array{ativo:bool,modo:string}>
     */
    private function mapearFamiliasTitulo(array $familias, array $grupos): array
    {
        $out = [];
        foreach ($familias as $fam) {
            $paiId = (int) ($fam['pai_id'] ?? 0);
            if ($paiId <= 0) {
                continue;
            }
            $idsFam = array_fill_keys(
                array_map('intval', (array) ($fam['materias_ids'] ?? [])),
                true
            );
            $achou = null;
            foreach ($grupos as $grp) {
                $idsGrp = array_map('intval', (array) ($grp['materias_ids'] ?? []));
                $inter = 0;
                foreach ($idsGrp as $mid) {
                    if ($mid > 0 && !empty($idsFam[$mid])) {
                        $inter++;
                    }
                }
                if ($inter < 2) {
                    continue;
                }
                $achou = $grp;
                break;
            }
            $out[$paiId] = [
                'ativo' => $achou !== null,
                'modo' => ($achou !== null && (($achou['modo'] ?? '') === 'soma')) ? 'soma' : 'media',
            ];
        }

        return $out;
    }

    /**
     * Grava um group_line por área (título no boletim). Lista vazia remove todos.
     *
     * @param list<array<string,mixed>> $groupLines
     */
    private function gravarGroupLinesNaRegra(int $regraId, array $groupLines): bool
    {
        if ($regraId <= 0) {
            return false;
        }
        $regra = $this->boletimConfig->getRuleById($regraId);
        if ($regra === null) {
            return false;
        }

        $db = Database::getInstance();
        $componentes = array_values(array_filter(
            (array) ($regra['componentes'] ?? []),
            static fn ($c) => is_array($c) && (int) ($c['id'] ?? 0) > 0
        ));

        // Limpa group_line de todos os componentes (inclui âncoras antigas).
        foreach ($componentes as $comp) {
            $compId = (int) ($comp['id'] ?? 0);
            $cfg = json_decode((string) ($comp['config_json'] ?? ''), true);
            if (!is_array($cfg)) {
                $cfg = [];
            }
            if (!isset($cfg['group_line'])) {
                continue;
            }
            unset($cfg['group_line']);
            $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                continue;
            }
            $db->update(
                'UPDATE boletim_componentes SET config_json = :c WHERE id = :id AND regra_id = :r AND ativo = 1',
                ['c' => $json, 'id' => $compId, 'r' => $regraId]
            );
        }

        if ($groupLines === []) {
            return true;
        }

        foreach ($groupLines as $gl) {
            if (!is_array($gl) || empty($gl['enabled'])) {
                continue;
            }
            // Sempre âncora por área (grp_*), para não sobrescrever PP/PU/CH do demonstrativo.
            $codigo = 'grp_' . $this->slugGrupo((string) ($gl['label'] ?? 'area'));
            $exist = $db->fetch(
                'SELECT id, config_json FROM boletim_componentes
                  WHERE regra_id = :r AND codigo = :c AND ativo = 1 LIMIT 1',
                ['r' => $regraId, 'c' => $codigo]
            );
            $cfgAncora = [
                'papel_wizard' => 'exibe',
                'group_line' => $gl,
                'layout_type' => 'media',
                'titulo_boletim' => 1,
            ];
            if ((int) ($gl['pai_id'] ?? 0) > 0) {
                $cfgAncora['pai_id'] = (int) $gl['pai_id'];
            }
            $jsonAncora = json_encode($cfgAncora, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonAncora === false) {
                return false;
            }
            $nomeArea = function_exists('mb_substr')
                ? mb_substr((string) ($gl['label'] ?? 'Área'), 0, 80)
                : substr((string) ($gl['label'] ?? 'Área'), 0, 80);
            if (is_array($exist) && (int) ($exist['id'] ?? 0) > 0) {
                $n = (int) $db->update(
                    'UPDATE boletim_componentes SET config_json = :c, nome = :n WHERE id = :id AND regra_id = :r AND ativo = 1',
                    [
                        'c' => $jsonAncora,
                        'n' => $nomeArea,
                        'id' => (int) $exist['id'],
                        'r' => $regraId,
                    ]
                );
                if ($n < 0) {
                    return false;
                }
                continue;
            }
            $novoId = (int) $db->insert(
                'INSERT INTO boletim_componentes
                    (regra_id, codigo, nome, source_type, calc_type, peso, config_json, materia_unica, materia_unica_modo, obrigatorio, ativo)
                 VALUES
                    (:r, :codigo, :nome, :src, :ct, 0, :cfg, 0, :mum, 0, 1)',
                [
                    'r' => $regraId,
                    'codigo' => $codigo,
                    'nome' => $nomeArea,
                    'src' => 'nenhuma',
                    'ct' => 'media',
                    'cfg' => $jsonAncora,
                    'mum' => 'soma',
                ]
            );
            if ($novoId <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $escopoIds
     * @return list<array{pai_id:int,nome:string,filhos:list<array{id:int,nome:string}>,materias_ids:list<int>}>
     */
    private function familiasComponentes(array $escopoIds = []): array
    {
        $set = [];
        foreach ($escopoIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $set[$id] = true;
            }
        }
        $filtrar = $set !== [];

        $cc = new ComponenteCurricular();
        $mapa = $cc->mapaFilhosPorPai();
        $nomesPai = [];
        foreach ($cc->getAll(false) as $m) {
            if (!is_array($m)) {
                continue;
            }
            $id = (int) ($m['id'] ?? 0);
            if ($id > 0) {
                $nomesPai[$id] = trim((string) ($m['nome'] ?? ''));
            }
        }

        $out = [];
        foreach ($mapa as $paiId => $filhos) {
            $paiId = (int) $paiId;
            if ($paiId <= 0 || !is_array($filhos)) {
                continue;
            }
            $lista = [];
            foreach ($filhos as $ch) {
                if (!is_array($ch)) {
                    continue;
                }
                $cid = (int) ($ch['id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                if ($filtrar && empty($set[$cid])) {
                    continue;
                }
                $lista[] = [
                    'id' => $cid,
                    'nome' => trim((string) ($ch['nome'] ?? '')),
                ];
            }
            // Se o escopo tem o pai e poucas filhas marcadas, mostra a família completa.
            if (count($lista) < 2 && $filtrar && !empty($set[$paiId])) {
                $lista = [];
                foreach ($filhos as $ch) {
                    if (!is_array($ch)) {
                        continue;
                    }
                    $cid = (int) ($ch['id'] ?? 0);
                    if ($cid <= 0) {
                        continue;
                    }
                    $lista[] = [
                        'id' => $cid,
                        'nome' => trim((string) ($ch['nome'] ?? '')),
                    ];
                }
            }
            if (count($lista) < 2) {
                continue;
            }
            $out[] = [
                'pai_id' => $paiId,
                'nome' => $nomesPai[$paiId] ?? ('Área #' . $paiId),
                'filhos' => $lista,
                'materias_ids' => array_map(static fn ($ch) => (int) $ch['id'], $lista),
            ];
        }

        return $out;
    }

    /**
     * @return array{ativo:bool,nome:string,modo:string,materias_ids:list<int>,aplicar_em:string,arredondamento:string,exemplo_sem_filhas:bool,agrupamento_id:int}
     */
    private function grupoPadrao(): array
    {
        return [
            'ativo' => false,
            'nome' => '',
            'modo' => 'media',
            'materias_ids' => [],
            'aplicar_em' => 'boletim',
            'arredondamento' => 'todos',
            'exemplo_sem_filhas' => false,
            'agrupamento_id' => 0,
        ];
    }

    private function slugGrupo(string $label): string
    {
        $s = strtolower(trim($label));
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($conv) && $conv !== '') {
                $s = $conv;
            }
        }
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
        $s = trim($s, '_');
        if ($s === '') {
            $s = 'area';
        }

        return mb_substr($s, 0, 40);
    }
}
