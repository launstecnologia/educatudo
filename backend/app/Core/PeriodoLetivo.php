<?php

namespace App\Core;
/**
 * EducaTudo — divisão do ano letivo (bimestre / trimestre / semestre / etapa).
 * Fonte: coluna ano_letivo.periodo_tipo. Sem cadastro, assume bimestral.
 */


class PeriodoLetivo
{
    public const TIPOS = [
        'bimestre' => 'Bimestral (4 períodos)',
        'trimestre' => 'Trimestral (3 períodos)',
        'semestre' => 'Semestral (2 períodos)',
        'etapa_unica' => 'Etapa única',
    ];

    private const ALIASES = [
        'bimestral' => 'bimestre',
        'trimestral' => 'trimestre',
        'semestral' => 'semestre',
        'etapa' => 'etapa_unica',
        'unica' => 'etapa_unica',
        'único' => 'etapa_unica',
        'unico' => 'etapa_unica',
    ];

    /** @var array<int, array<string,mixed>> */
    private static array $cachePorAno = [];

    /** @var array<int, array<int, array{inicio:string,fim:string}>> */
    private static array $intervalosPorAno = [];

    private static ?bool $colunaExiste = null;

    private static ?bool $tabelaPeriodosExiste = null;

    public static function tipoPadrao(): string
    {
        return 'bimestre';
    }

    public static function tipoValido(?string $tipo): bool
    {
        return self::resolverTipo(strtolower(trim((string) $tipo))) !== null;
    }

    public static function normalizarTipo(?string $tipo): string
    {
        $resolvido = self::resolverTipo(strtolower(trim((string) $tipo)));
        return $resolvido ?? self::tipoPadrao();
    }

    public static function temColunaPeriodoTipo(): bool
    {
        return self::temColuna();
    }

    public static function invalidarCache(?int $ano = null): void
    {
        if ($ano === null) {
            self::$cachePorAno = [];
            self::$intervalosPorAno = [];
            self::$colunaExiste = null;
            self::$tabelaPeriodosExiste = null;
            return;
        }
        unset(self::$cachePorAno[$ano], self::$intervalosPorAno[$ano]);
    }

    public static function quantidade(string $tipo): int
    {
        return match (self::normalizarTipo($tipo)) {
            'trimestre' => 3,
            'semestre' => 2,
            'etapa_unica' => 1,
            default => 4,
        };
    }

    public static function rotuloCampo(string $tipo): string
    {
        return match (self::normalizarTipo($tipo)) {
            'trimestre' => 'Trimestre',
            'semestre' => 'Semestre',
            'etapa_unica' => 'Período',
            default => 'Bimestre',
        };
    }

    public static function rotuloCampoPlural(string $tipo): string
    {
        return match (self::normalizarTipo($tipo)) {
            'trimestre' => 'Trimestres',
            'semestre' => 'Semestres',
            'etapa_unica' => 'Períodos',
            default => 'Bimestres',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function rotulosDoTipo(string $tipo): array
    {
        $tipo = self::normalizarTipo($tipo);
        $campo = self::rotuloCampo($tipo);
        $q = self::quantidade($tipo);
        $out = [];
        for ($i = 1; $i <= $q; $i++) {
            $out[$i] = $q === 1 ? 'Etapa única' : ($i . 'º ' . $campo);
        }
        return $out;
    }

    public static function token(string $tipo, int $numero): string
    {
        $tipo = self::normalizarTipo($tipo);
        $prefixo = match ($tipo) {
            'trimestre' => 'T',
            'semestre' => 'S',
            'etapa_unica' => 'E',
            default => 'B',
        };
        $q = self::quantidade($tipo);
        $n = max(1, min($q, $numero));
        return $prefixo . $n;
    }

    /**
     * @return array{
     *   ano:int,
     *   tipo:string,
     *   quantidade:int,
     *   rotulo_campo:string,
     *   rotulo_campo_plural:string,
     *   rotulos:array<int,string>
     * }
     */
    public static function doAno(int $ano): array
    {
        $ano = $ano > 0 ? $ano : (int) date('Y');
        if (isset(self::$cachePorAno[$ano])) {
            return self::$cachePorAno[$ano];
        }
        $tipo = self::tipoDoBanco($ano);
        $info = self::montar($ano, $tipo);
        self::$cachePorAno[$ano] = $info;
        return $info;
    }

    /**
     * Anos que a escola cadastrou em Ano Letivo, do mais recente ao mais antigo.
     *
     * @return list<int>
     */
    public static function anosCadastrados(): array
    {
        try {
            $rows = Database::getInstance()->fetchAll('SELECT ano FROM ano_letivo ORDER BY ano DESC');
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($rows)) {
            return [];
        }
        $anos = [];
        foreach ($rows as $row) {
            $ano = (int) ($row['ano'] ?? 0);
            if ($ano > 0) {
                $anos[$ano] = $ano;
            }
        }
        $lista = array_values($anos);
        rsort($lista);
        return $lista;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public static function mapaPorAno(): array
    {
        $mapa = [];
        $anoAtual = (int) date('Y');
        $mapa[$anoAtual] = self::doAno($anoAtual);
        if (!self::temColuna()) {
            return $mapa;
        }
        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll('SELECT ano, periodo_tipo FROM ano_letivo ORDER BY ano DESC');
            if (!is_array($rows)) {
                return $mapa;
            }
            foreach ($rows as $row) {
                $ano = (int) ($row['ano'] ?? 0);
                if ($ano <= 0) {
                    continue;
                }
                $mapa[$ano] = self::doAno($ano);
            }
        } catch (\Throwable $e) {
            // Sem tabela/coluna: só o ano corrente.
        }
        return $mapa;
    }

    public static function numeroValido(int $ano, int $numero): bool
    {
        $info = self::doAno($ano);
        return $numero >= 1 && $numero <= (int) $info['quantidade'];
    }

    /**
     * Intervalo civil do período. Sem tipo, usa a divisão gravada no ano letivo.
     * Trimestre: jan–abr, mai–ago, set–dez. Bimestre: blocos de três meses.
     *
     * @return array{inicio:string,fim:string}
     */
    public static function intervalo(int $ano, int $numero, ?string $tipo = null): array
    {
        $ano = $ano > 0 ? $ano : (int) date('Y');
        $tipoInformado = strtolower(trim((string) $tipo));
        $tipo = ($tipoInformado === '' || $tipoInformado === 'ano')
            ? (string) self::doAno($ano)['tipo']
            : self::normalizarTipo($tipoInformado);
        $quantidade = self::quantidade($tipo);
        $numero = max(1, min($quantidade, $numero));
        $tipoDoAno = (string) (self::doAno($ano)['tipo'] ?? self::tipoPadrao());
        if ($tipo === $tipoDoAno) {
            $salvos = self::periodosCompletos($ano);
            if (isset($salvos[$numero])) {
                return $salvos[$numero];
            }
        }
        if ($tipo === 'etapa_unica') {
            return [
                'inicio' => sprintf('%04d-01-01', $ano),
                'fim' => sprintf('%04d-12-31', $ano),
            ];
        }
        if ($tipo === 'semestre') {
            $mesInicio = $numero === 1 ? 1 : 7;
            $alcance = 5;
        } elseif ($tipo === 'trimestre') {
            $mesInicio = ($numero - 1) * 4 + 1;
            $alcance = 3;
        } else {
            $mesInicio = ($numero - 1) * 3 + 1;
            $alcance = 2;
        }
        return [
            'inicio' => sprintf('%04d-%02d-01', $ano, $mesInicio),
            'fim' => date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $ano, $mesInicio + $alcance))),
        ];
    }

    public static function numeroDaData(int $ano, string $data): int
    {
        $ano = $ano > 0 ? $ano : (int) date('Y', strtotime($data) ?: time());
        $salvos = self::periodosCompletos($ano);
        if ($salvos !== []) {
            $dia = date('Y-m-d', strtotime($data) ?: time());
            foreach ($salvos as $numero => $intervalo) {
                if ($dia >= $intervalo['inicio'] && $dia <= $intervalo['fim']) {
                    return (int) $numero;
                }
            }
        }
        $info = self::doAno($ano);
        $quantidade = max(1, (int) $info['quantidade']);
        $mes = (int) date('n', strtotime($data) ?: time());
        if ($mes < 1) {
            $mes = 1;
        }
        $numero = match ((string) $info['tipo']) {
            'trimestre' => (int) ceil($mes / 4),
            'semestre' => $mes <= 6 ? 1 : 2,
            'etapa_unica' => 1,
            default => (int) ceil($mes / 3),
        };
        return max(1, min($quantidade, $numero));
    }

    /**
     * Período ao abrir a tela: o que contém hoje, ou o último se o ano já passou.
     */
    public static function numeroPadrao(int $ano): int
    {
        $quantidade = max(1, (int) self::doAno($ano)['quantidade']);
        $anoHoje = (int) date('Y');
        if ($ano > 0 && $ano < $anoHoje) {
            return $quantidade;
        }
        if ($ano > $anoHoje) {
            return 1;
        }
        return self::numeroDaData($ano > 0 ? $ano : $anoHoje, date('Y-m-d'));
    }

    public static function rotulo(int $ano, int $numero): string
    {
        $info = self::doAno($ano);
        if ($numero <= 0) {
            return '';
        }
        return (string) ($info['rotulos'][$numero] ?? ($numero . 'º ' . $info['rotulo_campo']));
    }

    /**
     * Rótulo visível do boletim (ex.: "2º Bimestre"). Sem número na regra, tenta o nome.
     */
    public static function rotuloBoletim(int $ano, int $numero, string $nomeRegra = ''): string
    {
        if ($ano <= 0) {
            $ano = (int) date('Y');
        }
        if ($numero > 0) {
            $rotulo = self::rotulo($ano, $numero);
            if ($rotulo !== '') {
                return $rotulo;
            }
        }
        if (preg_match('/(\d+)\s*[ºo°]\s*(bimestre|trimestre|semestre)/iu', $nomeRegra, $m)) {
            $tipo = function_exists('mb_strtolower') ? mb_strtolower($m[2], 'UTF-8') : strtolower($m[2]);
            $tipo = function_exists('mb_convert_case')
                ? mb_convert_case($tipo, MB_CASE_TITLE, 'UTF-8')
                : ucfirst($tipo);
            return $m[1] . 'º ' . $tipo;
        }
        return '—';
    }

    public static function mensagemNumeroInvalido(int $ano): string
    {
        $campo = strtolower((string) self::doAno($ano)['rotulo_campo']);
        return 'Selecione um ' . $campo . ' válido.';
    }

    /**
     * @param array{vazio?:bool,vazio_label?:string,todos?:bool,todos_label?:string,valor_rotulo?:bool,selecionado?:int|string} $opts
     */
    public static function optionsHtml(int $ano, $selecionado = 0, array $opts = []): string
    {
        $info = self::doAno($ano);
        $html = '';
        if (!empty($opts['vazio'])) {
            $html .= '<option value="">' . htmlspecialchars((string) ($opts['vazio_label'] ?? 'Selecione')) . '</option>';
        }
        if (!empty($opts['todos'])) {
            $html .= '<option value="">' . htmlspecialchars((string) ($opts['todos_label'] ?? 'Todos')) . '</option>';
        }
        $valorRotulo = !empty($opts['valor_rotulo']);
        $selStr = trim((string) $selecionado);
        $selInt = (int) $selecionado;
        foreach ($info['rotulos'] as $num => $lab) {
            $val = $valorRotulo ? $lab : (string) $num;
            $sel = $valorRotulo
                ? ($selStr !== '' && $selStr === $lab)
                : ($selInt === (int) $num);
            $html .= '<option value="' . htmlspecialchars($val) . '"' . ($sel ? ' selected' : '') . '>'
                . htmlspecialchars($lab) . '</option>';
        }
        if ($valorRotulo && $selStr !== '' && !in_array($selStr, $info['rotulos'], true)) {
            $html .= '<option value="' . htmlspecialchars($selStr) . '" selected>' . htmlspecialchars($selStr) . '</option>';
        }
        return $html;
    }

    /**
     * @return array{ano:int,tipo:string,quantidade:int,rotulo_campo:string,rotulo_campo_plural:string,rotulos:array<int,string>}
     */
    private static function montar(int $ano, string $tipo): array
    {
        $tipo = self::normalizarTipo($tipo);
        return [
            'ano' => $ano,
            'tipo' => $tipo,
            'quantidade' => self::quantidade($tipo),
            'rotulo_campo' => self::rotuloCampo($tipo),
            'rotulo_campo_plural' => self::rotuloCampoPlural($tipo),
            'rotulos' => self::rotulosDoTipo($tipo),
        ];
    }

    /**
     * Aceita código canônico, rótulo da tela e variações (trimestral, Trimestral (3 períodos)…).
     */
    private static function resolverTipo(string $tipo): ?string
    {
        if ($tipo === '') {
            return null;
        }
        if (isset(self::TIPOS[$tipo])) {
            return $tipo;
        }
        if (isset(self::ALIASES[$tipo])) {
            return self::ALIASES[$tipo];
        }
        foreach (self::TIPOS as $cod => $lab) {
            if ($tipo === strtolower($lab)) {
                return $cod;
            }
        }
        if (str_contains($tipo, 'trimestr')) {
            return 'trimestre';
        }
        if (str_contains($tipo, 'semestr')) {
            return 'semestre';
        }
        if (str_contains($tipo, 'etapa')) {
            return 'etapa_unica';
        }
        if (str_contains($tipo, 'bimestr')) {
            return 'bimestre';
        }
        return null;
    }

    private static function tipoDoBanco(int $ano): string
    {
        if ($ano <= 0 || !self::temColuna()) {
            return self::tipoPadrao();
        }
        try {
            $db = Database::getInstance();
            $row = $db->fetch(
                'SELECT periodo_tipo FROM ano_letivo WHERE ano = :ano LIMIT 1',
                ['ano' => $ano]
            );
            if (is_array($row) && isset($row['periodo_tipo'])) {
                return self::normalizarTipo((string) $row['periodo_tipo']);
            }
        } catch (\Throwable $e) {
            return self::tipoPadrao();
        }
        return self::tipoPadrao();
    }

    /**
     * Datas gravadas no cadastro do ano. Só vale quando todos os períodos da divisão estão preenchidos.
     *
     * @return array<int, array{inicio:string,fim:string}>
     */
    private static function periodosCompletos(int $ano): array
    {
        $salvos = self::intervalosSalvos($ano);
        if ($salvos === []) {
            return [];
        }
        $qtd = self::quantidade((string) (self::doAno($ano)['tipo'] ?? self::tipoPadrao()));
        for ($i = 1; $i <= $qtd; $i++) {
            if (!isset($salvos[$i])) {
                return [];
            }
        }
        return $salvos;
    }

    /**
     * @return array<int, array{inicio:string,fim:string}>
     */
    private static function intervalosSalvos(int $ano): array
    {
        if ($ano <= 0) {
            return [];
        }
        if (isset(self::$intervalosPorAno[$ano])) {
            return self::$intervalosPorAno[$ano];
        }
        self::$intervalosPorAno[$ano] = [];
        if (!self::temTabelaPeriodos()) {
            return [];
        }
        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll(
                'SELECT p.numero, p.data_inicio, p.data_fim
                 FROM ano_letivo_periodos p
                 INNER JOIN ano_letivo a ON a.id = p.ano_letivo_id
                 WHERE a.ano = :ano
                 ORDER BY p.numero',
                ['ano' => $ano]
            );
            if (!is_array($rows)) {
                return [];
            }
            $mapa = [];
            foreach ($rows as $row) {
                $numero = (int) ($row['numero'] ?? 0);
                $inicio = substr((string) ($row['data_inicio'] ?? ''), 0, 10);
                $fim = substr((string) ($row['data_fim'] ?? ''), 0, 10);
                if ($numero < 1 || $inicio === '' || $fim === '') {
                    continue;
                }
                $mapa[$numero] = ['inicio' => $inicio, 'fim' => $fim];
            }
            self::$intervalosPorAno[$ano] = $mapa;
        } catch (\Throwable $e) {
            self::$intervalosPorAno[$ano] = [];
        }
        return self::$intervalosPorAno[$ano];
    }

    private static function temTabelaPeriodos(): bool
    {
        if (self::$tabelaPeriodosExiste !== null) {
            return self::$tabelaPeriodosExiste;
        }
        self::$tabelaPeriodosExiste = false;
        try {
            $db = Database::getInstance();
            $row = $db->fetch("SHOW TABLES LIKE 'ano_letivo_periodos'");
            self::$tabelaPeriodosExiste = is_array($row) && $row !== [];
        } catch (\Throwable $e) {
            self::$tabelaPeriodosExiste = false;
        }
        return self::$tabelaPeriodosExiste;
    }

    private static function temColuna(): bool
    {
        if (self::$colunaExiste !== null) {
            return self::$colunaExiste;
        }
        self::$colunaExiste = false;
        try {
            $db = Database::getInstance();
            $row = $db->fetch("SHOW COLUMNS FROM `ano_letivo` LIKE 'periodo_tipo'");
            if (is_array($row) && !empty($row)) {
                self::$colunaExiste = true;
                return true;
            }
        } catch (\Throwable $e) {
            // tenta INFORMATION_SCHEMA abaixo
        }
        try {
            $db = Database::getInstance();
            $row = $db->fetch(
                "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ano_letivo' AND COLUMN_NAME = 'periodo_tipo'"
            );
            self::$colunaExiste = is_array($row) && (int) ($row['n'] ?? 0) > 0;
        } catch (\Throwable $e) {
            self::$colunaExiste = false;
        }
        return self::$colunaExiste;
    }
}

if (!class_exists('PeriodoLetivo', false) && class_exists(__NAMESPACE__ . '\\PeriodoLetivo', false)) {
    class_alias(__NAMESPACE__ . '\\PeriodoLetivo', 'PeriodoLetivo');
}
