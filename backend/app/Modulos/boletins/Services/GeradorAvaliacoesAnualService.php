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
        $eventos = $this->eventosAvaliacaoDoAno($ano);
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
     * @return array{success:bool,error?:string,criados?:list<int>,ignorados?:list<int>}
     */
    public function gerar(int $boletimId, int $modeloRegraId, int $anoLetivo = 0): array
    {
        $modelo = $this->boletimConfig->getRuleById($modeloRegraId);
        if (!is_array($modelo) || (int) ($modelo['id'] ?? 0) <= 0) {
            return ['success' => false, 'error' => 'Escolha um evento modelo para duplicar (crie a primeira avaliação do modelo).'];
        }
        $modeloBoletimId = (int) ($modelo['boletim_id'] ?? 0);
        if ($modeloBoletimId !== $boletimId) {
            return ['success' => false, 'error' => 'O evento modelo precisa pertencer a este modelo de boletim.'];
        }
        if ($anoLetivo <= 0) {
            $anoLetivo = (int) ($modelo['ano_letivo'] ?? 0);
        }
        $prev = $this->previsualizar($boletimId, $anoLetivo);
        if (empty($prev['ok'])) {
            return ['success' => false, 'error' => $prev['error'] ?? 'Não foi possível montar os períodos.'];
        }

        $ano = (int) $prev['ano'];
        $criados = [];
        $ignorados = [];
        foreach ($prev['periodos'] as $periodo) {
            $bim = (int) ($periodo['bimestre'] ?? 0);
            if (!PeriodoLetivo::numeroValido($ano, $bim)) {
                continue;
            }
            if (!empty($periodo['ja_existe'])) {
                $ignorados[] = $bim;
                continue;
            }
            $novoId = $this->boletimConfig->duplicateRule($modeloRegraId);
            if ($novoId === null || $novoId <= 0) {
                return ['success' => false, 'error' => 'Não foi possível duplicar o evento modelo.'];
            }
            $this->boletimConfig->setBoletimId($novoId, $boletimId);
            $nome = trim((string) ($prev['boletim']['nome'] ?? 'Avaliação')) . ' — ' . self::nomeBimestre($bim, $ano);
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
            $criados[] = $novoId;
        }

        if ($criados === [] && $ignorados === []) {
            return ['success' => false, 'error' => 'Nenhum período para gerar.'];
        }

        return [
            'success' => true,
            'criados' => $criados,
            'ignorados' => $ignorados,
        ];
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
    public function eventosAvaliacaoDoAno(int $ano): array
    {
        $cal = $this->calendario->getAno($ano);
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
