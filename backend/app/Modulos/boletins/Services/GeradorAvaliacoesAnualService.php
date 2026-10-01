<?php

namespace App\Modulos\Boletins\Services;

require_once __DIR__ . '/../Models/Boletim.php';
require_once __DIR__ . '/../../../Models/System/BoletimConfig.php';
require_once __DIR__ . '/../../../Services/SchoolCalendarService.php';
require_once __DIR__ . '/../../../Core/PeriodoLetivo.php';

use App\Modulos\Boletins\Models\Boletim;
use BoletimConfig;
use Database;
use PeriodoLetivo;
use SchoolCalendarService;
use Throwable;

/**
 * Gera os 4 eventos de avaliação do ano a partir de um modelo, respeitando o calendário letivo.
 */
class GeradorAvaliacoesAnualService
{
    private Boletim $boletim;
    private BoletimConfig $boletimConfig;
    private SchoolCalendarService $calendario;
    private Database $db;

    public function __construct()
    {
        $this->boletim = new Boletim();
        $this->boletimConfig = new BoletimConfig();
        $this->calendario = new SchoolCalendarService();
        $this->db = Database::getInstance();
    }

    /**
     * @param list<array<string,mixed>> $eventosAvaliacao
     * @return list<array{bimestre:int,nome:string,inicio:string,fim:string,origem:string}>
     */
    public static function periodosPrevistos(
        int $ano,
        array $eventosAvaliacao,
        ?string $anoInicio,
        ?string $anoFim
    ): array {
        $padrao = self::periodosPadrao($ano);
        $doCalendario = [];
        foreach ($eventosAvaliacao as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $inicio = self::soData((string) ($ev['data_inicio'] ?? ''));
            $fim = self::soData((string) ($ev['data_fim'] ?? $inicio));
            if ($inicio === '') {
                continue;
            }
            if ($fim === '') {
                $fim = $inicio;
            }
            $bim = self::bimestreDaDescricao((string) ($ev['descricao'] ?? ''), count($doCalendario) + 1);
            $doCalendario[] = [
                'bimestre' => $bim,
                'nome' => self::nomeBimestre($bim, $ano),
                'inicio' => $inicio,
                'fim' => $fim < $inicio ? $inicio : $fim,
                'origem' => 'calendario',
            ];
            if (count($doCalendario) >= PeriodoLetivo::doAno($ano)['quantidade']) {
                break;
            }
        }

        $qtd = (int) PeriodoLetivo::doAno($ano)['quantidade'];
        $usados = [];
        $out = [];
        foreach ($doCalendario as $p) {
            $bim = (int) $p['bimestre'];
            if ($bim < 1 || $bim > $qtd || isset($usados[$bim])) {
                $bim = 1;
                while ($bim <= $qtd && isset($usados[$bim])) {
                    $bim++;
                }
                if ($bim > $qtd) {
                    continue;
                }
                $p['bimestre'] = $bim;
                $p['nome'] = self::nomeBimestre($bim, $ano);
            }
            $usados[$bim] = true;
            $out[] = self::clipPeriodo($p, $anoInicio, $anoFim);
        }

        foreach ($padrao as $p) {
            $bim = (int) $p['bimestre'];
            if (isset($usados[$bim])) {
                continue;
            }
            $usados[$bim] = true;
            $out[] = self::clipPeriodo($p, $anoInicio, $anoFim);
        }

        usort($out, static fn ($a, $b) => ((int) $a['bimestre']) <=> ((int) $b['bimestre']));
        return $out;
    }

    /**
     * @return list<array{bimestre:int,nome:string,inicio:string,fim:string,origem:string}>
     */
    public static function periodosPadrao(int $ano): array
    {
        $ano = $ano > 0 ? $ano : (int) date('Y');
        $info = PeriodoLetivo::doAno($ano);
        $tipo = (string) $info['tipo'];
        $qtd = (int) $info['quantidade'];
        $faixas = match ($tipo) {
            'trimestre' => [
                ['inicio' => $ano . '-02-01', 'fim' => $ano . '-05-15'],
                ['inicio' => $ano . '-05-16', 'fim' => $ano . '-08-31'],
                ['inicio' => $ano . '-09-01', 'fim' => $ano . '-12-15'],
            ],
            'semestre' => [
                ['inicio' => $ano . '-02-01', 'fim' => $ano . '-06-30'],
                ['inicio' => $ano . '-08-01', 'fim' => $ano . '-12-15'],
            ],
            'etapa_unica' => [
                ['inicio' => $ano . '-02-01', 'fim' => $ano . '-12-15'],
            ],
            default => [
                ['inicio' => $ano . '-02-01', 'fim' => $ano . '-04-15'],
                ['inicio' => $ano . '-04-16', 'fim' => $ano . '-06-30'],
                ['inicio' => $ano . '-08-01', 'fim' => $ano . '-10-15'],
                ['inicio' => $ano . '-10-16', 'fim' => $ano . '-12-15'],
            ],
        };
        $out = [];
        for ($i = 1; $i <= $qtd; $i++) {
            $fx = $faixas[$i - 1] ?? $faixas[0];
            $out[] = [
                'bimestre' => $i,
                'nome' => self::nomeBimestre($i, $ano),
                'inicio' => $fx['inicio'],
                'fim' => $fx['fim'],
                'origem' => 'padrao',
            ];
        }
        return $out;
    }

    public static function nomeBimestre(int $bimestre, int $ano = 0): string
    {
        return PeriodoLetivo::rotulo($ano > 0 ? $ano : (int) date('Y'), $bimestre) ?: ('Período ' . $bimestre);
    }

    public static function bimestreDaDescricao(string $descricao, int $fallback): int
    {
        $fallback = ($fallback >= 1 && $fallback <= 4) ? $fallback : 1;
        if (preg_match('/\b([1-4])\s*[ºo]?\s*(bim|bimestre)\b/i', $descricao, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bb([1-4])\b/i', $descricao, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b([1-4])\s*[ºo]\b/u', $descricao, $m)) {
            return (int) $m[1];
        }
        return $fallback;
    }

    /**
     * @return array{ok:bool,error?:string,boletim?:array<string,mixed>,ano?:int,periodos?:list<array<string,mixed>>,eventos_existentes?:array<int,int>}
     */
    public function previsualizar(int $boletimId, int $anoLetivo = 0): array
    {
        $boletim = $this->boletim->findById($boletimId);
        if ($boletim === null) {
            return ['ok' => false, 'error' => 'Modelo de boletim não encontrado.'];
        }
        $ano = $anoLetivo > 0 ? $anoLetivo : (int) ($boletim['ano_letivo'] ?? date('Y'));
        if ($ano <= 0) {
            $ano = (int) date('Y');
        }
        $datasAno = $this->datasDoAnoLetivo($ano);
        $seriesBoletim = array_values(array_filter(
            array_map('intval', (array) ($boletim['series_ids'] ?? [])),
            static fn ($id) => $id > 0
        ));
        $eventos = $this->eventosAvaliacaoDoAno($ano, $seriesBoletim);
        $periodos = self::periodosPrevistos($ano, $eventos, $datasAno['inicio'], $datasAno['fim']);
        $existentes = $this->boletimConfig->fontesBimestresDoBoletim($boletimId);
        foreach ($periodos as &$p) {
            $bim = (int) $p['bimestre'];
            $p['regra_id'] = (int) ($existentes[$bim] ?? 0);
            $p['ja_existe'] = $p['regra_id'] > 0;
        }
        unset($p);

        return [
            'ok' => true,
            'boletim' => $boletim,
            'ano' => $ano,
            'periodos' => $periodos,
            'eventos_existentes' => $existentes,
            'datas_ano' => $datasAno,
            'usou_calendario' => $eventos !== [],
        ];
    }

    /**
     * Eventos que podem servir de modelo na duplicação anual.
     * Inclui os já ligados ao boletim e também notas vigentes da mesma série/ano
     * (como em Notas da Coordenação), para copiar exatamente aquele evento.
     *
     * @return list<array<string,mixed>>
     */
    public function listarEventosModelo(int $boletimId, int $anoLetivo = 0): array
    {
        $boletim = $this->boletim->findById($boletimId);
        if ($boletim === null) {
            return [];
        }
        $ano = $anoLetivo > 0 ? $anoLetivo : (int) ($boletim['ano_letivo'] ?? date('Y'));
        $seriesBoletim = array_values(array_filter(
            array_map('intval', (array) ($boletim['series_ids'] ?? [])),
            static fn ($id) => $id > 0
        ));

        $porId = [];
        foreach ($this->boletimConfig->listarEventosNotasDoBoletim($boletimId) as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $rid = (int) ($ev['id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            $porId[$rid] = $ev;
            $porId[$rid]['_origem'] = 'boletim';
        }

        // Eventos com geração vigente (mesmo critério da coordenação).
        try {
            $temBoletimCol = (bool) $this->db->fetch("SHOW COLUMNS FROM boletim_regras LIKE 'boletim_id'");
            $selBoletim = $temBoletimCol ? 'r.boletim_id' : 'NULL AS boletim_id';
            $groupBoletim = $temBoletimCol ? ', r.boletim_id' : '';
            $temVigente = (bool) $this->db->fetch(
                "SHOW COLUMNS FROM boletim_resultados_gerados LIKE 'vigente'"
            );
            $filtroVigente = $temVigente ? ' AND g.vigente = 1' : '';
            $rowsGerados = $this->db->fetchAll(
                "SELECT r.id, r.nome, r.codigo, r.bimestre, r.ano_letivo, r.series_ids, {$selBoletim},
                        r.default_data_inicio, r.default_data_fim,
                        MAX(g.updated_at) AS gerado_em
                 FROM boletim_resultados_gerados g
                 INNER JOIN boletim_regras r ON r.id = g.regra_id
                 WHERE g.preview = 0 AND r.ativo = 1 AND r.exibir_em = 'notas'{$filtroVigente}
                 GROUP BY r.id, r.nome, r.codigo, r.bimestre, r.ano_letivo, r.series_ids{$groupBoletim},
                          r.default_data_inicio, r.default_data_fim
                 ORDER BY gerado_em DESC"
            ) ?: [];
        } catch (Throwable $e) {
            error_log('[GeradorAvaliacoesAnual] listarEventosModelo vigentes: ' . $e->getMessage());
            $rowsGerados = [];
        }

        foreach ($rowsGerados as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $rid = (int) ($ev['id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            $anoEv = (int) ($ev['ano_letivo'] ?? 0);
            if ($ano > 0 && $anoEv > 0 && $anoEv !== $ano) {
                continue;
            }
            $seriesEv = $this->parseIdsJson($ev['series_ids'] ?? '');
            if ($seriesBoletim !== [] && $seriesEv !== [] && count(array_intersect($seriesBoletim, $seriesEv)) === 0) {
                continue;
            }
            if (!isset($porId[$rid])) {
                $porId[$rid] = $ev;
                $porId[$rid]['_origem'] = 'vigente';
            } else {
                $porId[$rid]['gerado_em'] = $ev['gerado_em'] ?? ($porId[$rid]['gerado_em'] ?? null);
                if (($porId[$rid]['_origem'] ?? '') === 'boletim') {
                    $porId[$rid]['_origem'] = 'boletim_vigente';
                }
            }
        }

        $seriesById = $this->mapSeriesPorId();

        $saida = [];
        foreach ($porId as $ev) {
            $nome = trim((string) ($ev['nome'] ?? 'Evento'));
            $anoEv = (int) ($ev['ano_letivo'] ?? 0);
            $bim = (int) ($ev['bimestre'] ?? 0);
            $seriesEv = $this->parseIdsJson($ev['series_ids'] ?? '');
            $nomesSerie = [];
            foreach ($seriesEv as $sid) {
                $nSerie = trim((string) ($seriesById[$sid] ?? ''));
                if ($nSerie !== '') {
                    $nomesSerie[] = $nSerie;
                }
            }
            $seriesLabel = implode(' e ', $nomesSerie);
            $titulo = 'Notas — ' . $nome;
            if ($seriesLabel !== '' && stripos($nome, $seriesLabel) === false) {
                $titulo .= ' ' . $seriesLabel;
            }
            $partes = [$titulo];
            if ($anoEv > 0) {
                $partes[] = (string) $anoEv;
            }
            if ($bim > 0) {
                $partes[] = self::nomeBimestre($bim, $anoEv > 0 ? $anoEv : $ano);
            }
            $geradoEm = trim((string) ($ev['gerado_em'] ?? ''));
            if ($geradoEm !== '') {
                $ts = strtotime($geradoEm);
                if ($ts !== false) {
                    $partes[] = 'Gerado em ' . date('d/m/Y H:i', $ts);
                }
            }
            $origem = (string) ($ev['_origem'] ?? '');
            if ($origem === 'vigente' || $origem === 'boletim_vigente') {
                $partes[] = 'Vigente';
                $ev['eh_vigente'] = true;
            } else {
                $ev['eh_vigente'] = false;
            }
            $ev['nome_exibicao'] = implode(' · ', $partes);
            $ev['_ordem_vigente'] = !empty($ev['eh_vigente']) ? 0 : 1;
            $saida[] = $ev;
        }

        usort($saida, static function (array $a, array $b): int {
            $cmp = ((int) ($a['_ordem_vigente'] ?? 1)) <=> ((int) ($b['_ordem_vigente'] ?? 1));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = ((int) ($a['bimestre'] ?? 0)) <=> ((int) ($b['bimestre'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) ($b['gerado_em'] ?? ''), (string) ($a['gerado_em'] ?? ''));
        });

        return $saida;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function parseIdsJson($raw): array
    {
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $v) {
                $id = (int) $v;
                if ($id > 0) {
                    $out[$id] = $id;
                }
            }
            return array_values($out);
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        return array_values($out);
    }

    /**
     * @return array<int,string>
     */
    private function mapSeriesPorId(): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT id, nome FROM serie WHERE ativo = 1 ORDER BY ordem ASC, nome ASC'
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            $sid = (int) ($row['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $map[$sid] = trim((string) ($row['nome'] ?? ''));
        }
        return $map;
    }

    /**
     * Escolhe o evento modelo padrão para um bimestre (mesmo bimestre, senão vigente).
     *
     * @param list<array<string,mixed>> $eventos
     */
    public function modeloPadraoParaBimestre(array $eventos, int $bimestre): int
    {
        foreach ($eventos as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if ($bimestre > 0 && (int) ($ev['bimestre'] ?? 0) === $bimestre) {
                $id = (int) ($ev['id'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
            }
        }
        foreach ($eventos as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if (!empty($ev['eh_vigente'])) {
                $id = (int) ($ev['id'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
            }
        }
        return (int) ($eventos[0]['id'] ?? 0);
    }

    /**
     * @param array<int,int> $modelosPorBimestre bimestre => regra_id modelo
     * @param list<int>|null $bimestresSelecionados null = todos faltantes; lista = só esses (cria ou substitui)
     * @return array{success:bool,error?:string,criados?:list<int>,substituidos?:list<int>,ignorados?:list<int>,pulados?:list<int>}
     */
    public function gerar(int $boletimId, array $modelosPorBimestre, int $anoLetivo = 0, ?array $bimestresSelecionados = null): array
    {
        if ($anoLetivo <= 0) {
            $boletim = $this->boletim->findById($boletimId);
            $anoLetivo = (int) ($boletim['ano_letivo'] ?? date('Y'));
        }
        $permitidos = $this->listarEventosModelo($boletimId, $anoLetivo);
        $idsPermitidos = [];
        foreach ($permitidos as $evPermitido) {
            $rid = (int) ($evPermitido['id'] ?? 0);
            if ($rid > 0) {
                $idsPermitidos[$rid] = true;
            }
        }
        if ($idsPermitidos === []) {
            return ['success' => false, 'error' => 'Escolha um evento modelo para duplicar (crie a primeira avaliação do modelo).'];
        }

        $filtroBim = null;
        if ($bimestresSelecionados !== null) {
            $filtroBim = [];
            foreach ($bimestresSelecionados as $bSel) {
                $b = (int) $bSel;
                if ($b > 0) {
                    $filtroBim[$b] = true;
                }
            }
            if ($filtroBim === []) {
                return ['success' => false, 'error' => 'Marque ao menos um bimestre para gerar.'];
            }
        }

        $prev = $this->previsualizar($boletimId, $anoLetivo);
        if (empty($prev['ok'])) {
            return ['success' => false, 'error' => $prev['error'] ?? 'Não foi possível montar os períodos.'];
        }

        $ano = (int) $prev['ano'];
        $nomePadraoBoletim = trim((string) ($prev['boletim']['nome'] ?? 'Avaliação'));
        $criados = [];
        $substituidos = [];
        $ignorados = [];
        $pulados = [];
        foreach ($prev['periodos'] as $periodo) {
            $bim = (int) ($periodo['bimestre'] ?? 0);
            if (!PeriodoLetivo::numeroValido($ano, $bim)) {
                continue;
            }
            $jaExiste = !empty($periodo['ja_existe']);
            $regraAntigaId = (int) ($periodo['regra_id'] ?? 0);

            if ($filtroBim !== null && !isset($filtroBim[$bim])) {
                $pulados[] = $bim;
                continue;
            }
            // Sem seleção explícita: mantém o comportamento antigo (não recria existentes).
            if ($filtroBim === null && $jaExiste) {
                $ignorados[] = $bim;
                continue;
            }

            $modeloRegraId = (int) ($modelosPorBimestre[$bim] ?? 0);
            if ($modeloRegraId <= 0) {
                $modeloRegraId = $this->modeloPadraoParaBimestre($permitidos, $bim);
            }
            if ($modeloRegraId <= 0 || !isset($idsPermitidos[$modeloRegraId])) {
                return [
                    'success' => false,
                    'error' => 'Escolha um evento modelo válido para o ' . self::nomeBimestre($bim, $ano) . '.',
                ];
            }
            $modelo = $this->boletimConfig->getRuleById($modeloRegraId);
            if (!is_array($modelo) || (int) ($modelo['id'] ?? 0) <= 0) {
                return ['success' => false, 'error' => 'Evento modelo não encontrado.'];
            }
            if (strtolower(trim((string) ($modelo['exibir_em'] ?? ''))) !== 'notas') {
                return ['success' => false, 'error' => 'O evento modelo precisa ser do tipo Notas.'];
            }

            $nomeBaseModelo = trim((string) ($modelo['nome'] ?? ''));
            $nomeBaseModelo = preg_replace('/\s*[—\-]\s*\d+[ºoª]?\s*(bim|bimestre).*$/iu', '', $nomeBaseModelo) ?? $nomeBaseModelo;
            $nomeBaseModelo = trim($nomeBaseModelo);
            if ($nomeBaseModelo === '') {
                $nomeBaseModelo = $nomePadraoBoletim;
            }

            $novoId = $this->boletimConfig->duplicateRule($modeloRegraId);
            if ($novoId === null || $novoId <= 0) {
                return ['success' => false, 'error' => 'Não foi possível duplicar o evento modelo.'];
            }
            $this->boletimConfig->setBoletimId($novoId, $boletimId);
            $nome = $nomeBaseModelo . ' — ' . self::nomeBimestre($bim, $ano);
            $codigo = $this->boletimConfig->codigoUnicoPara('avaliacao-' . $ano . '-b' . $bim);
            $this->boletimConfig->atualizarEventoGerado($novoId, [
                'nome' => $nome,
                'codigo' => $codigo,
                'bimestre' => $bim,
                'ano_letivo' => $ano,
                'default_data_inicio' => $periodo['inicio'] ?? null,
                'default_data_fim' => $periodo['fim'] ?? null,
            ]);
            $this->boletimConfig->alinharComponentesAoBimestre($novoId, $bim);
            $this->boletimConfig->garantirQuadroNotasNaRegra($novoId);
            // Se o modelo tinha quadro, forçar cópia explícita (mesmo quando já veio no extras).
            $quadroModelo = $this->boletimConfig->resolverGrupoRegrasNotasId($modelo, $modeloRegraId);
            if ($quadroModelo > 0) {
                $this->boletimConfig->mesclarExtrasJson($novoId, [
                    'grupo_regras_notas_id' => $quadroModelo,
                    'quadro_notas_id' => $quadroModelo,
                ]);
            }

            if ($jaExiste && $regraAntigaId > 0) {
                $this->desativarEventoSubstituido($regraAntigaId, $boletimId, $novoId);
                $substituidos[] = $novoId;
            } else {
                $criados[] = $novoId;
            }
        }

        if ($criados === [] && $substituidos === [] && $ignorados === [] && $pulados === []) {
            return ['success' => false, 'error' => 'Nenhum período para gerar.'];
        }
        if ($criados === [] && $substituidos === [] && $pulados !== [] && $ignorados === []) {
            return ['success' => false, 'error' => 'Marque ao menos um bimestre para gerar.'];
        }

        return [
            'success' => true,
            'criados' => $criados,
            'substituidos' => $substituidos,
            'ignorados' => $ignorados,
            'pulados' => $pulados,
        ];
    }

    /**
     * Soft-delete da regra antiga + demove resultados vigentes (histórico preservado).
     */
    private function desativarEventoSubstituido(int $regraAntigaId, int $boletimId, int $novoId): void
    {
        if ($regraAntigaId <= 0 || $regraAntigaId === $novoId) {
            return;
        }
        $antiga = $this->boletimConfig->getRuleById($regraAntigaId);
        if (!is_array($antiga)) {
            return;
        }
        $boletimDaRegra = (int) ($antiga['boletim_id'] ?? 0);
        if ($boletimDaRegra > 0 && $boletimDaRegra !== $boletimId) {
            return;
        }
        $this->boletimConfig->deactivateRule($regraAntigaId);
        try {
            if (!$this->db->fetch("SHOW COLUMNS FROM boletim_resultados_gerados LIKE 'vigente'")) {
                return;
            }
            $this->db->update(
                'UPDATE boletim_resultados_gerados
                 SET vigente = 0
                 WHERE regra_id = :rid AND preview = 0 AND vigente = 1',
                ['rid' => $regraAntigaId]
            );
        } catch (Throwable $e) {
            error_log('[GeradorAvaliacoesAnual] demover vigentes regra ' . $regraAntigaId . ': ' . $e->getMessage());
        }
    }

    /**
     * @return array{inicio:?string,fim:?string}
     */
    public function datasDoAnoLetivo(int $ano): array
    {
        $vazio = ['inicio' => null, 'fim' => null];
        if ($ano <= 0) {
            return $vazio;
        }
        try {
            if (!$this->db->fetch("SHOW TABLES LIKE 'ano_letivo'")) {
                return $vazio;
            }
            $row = $this->db->fetch(
                'SELECT data_inicio, data_fim FROM ano_letivo WHERE ano = :ano LIMIT 1',
                ['ano' => $ano]
            );
            if (!is_array($row)) {
                return $vazio;
            }
            $ini = self::soData((string) ($row['data_inicio'] ?? ''));
            $fim = self::soData((string) ($row['data_fim'] ?? ''));
            return [
                'inicio' => $ini !== '' ? $ini : null,
                'fim' => $fim !== '' ? $fim : null,
            ];
        } catch (Throwable $e) {
            return $vazio;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function eventosAvaliacaoDoAno(int $ano, array $serieIds = []): array
    {
        $cal = $serieIds === []
            ? $this->calendario->getAno($ano)
            : $this->calendario->resolverParaSeries($ano, $serieIds);
        if (!is_array($cal)) {
            return [];
        }
        $id = (int) ($cal['id'] ?? 0);
        $out = [];
        foreach ($this->calendario->eventos($id) as $ev) {
            if (strtolower(trim((string) ($ev['tipo'] ?? ''))) !== 'avaliacao') {
                continue;
            }
            $out[] = $ev;
        }
        return $out;
    }

    /**
     * @param array{bimestre:int,nome:string,inicio:string,fim:string,origem:string} $periodo
     * @return array{bimestre:int,nome:string,inicio:string,fim:string,origem:string}
     */
    private static function clipPeriodo(array $periodo, ?string $anoInicio, ?string $anoFim): array
    {
        $inicio = $periodo['inicio'];
        $fim = $periodo['fim'];
        if ($anoInicio !== null && $anoInicio !== '' && $inicio < $anoInicio) {
            $inicio = $anoInicio;
        }
        if ($anoFim !== null && $anoFim !== '' && $fim > $anoFim) {
            $fim = $anoFim;
        }
        if ($fim < $inicio) {
            $fim = $inicio;
        }
        $periodo['inicio'] = $inicio;
        $periodo['fim'] = $fim;
        return $periodo;
    }

    private static function soData(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $valor, $m)) {
            return $m[1];
        }
        return '';
    }
}
