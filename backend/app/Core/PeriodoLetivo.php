<?php
/**
 * EducaTudo — divisão do ano letivo (bimestre / trimestre / semestre / etapa).
 * Fonte: coluna ano_letivo.periodo_tipo. Sem cadastro, assume bimestral.
 */

require_once __DIR__ . '/Database.php';

class PeriodoLetivo
{
    public const TIPOS = [
        'bimestre' => 'Bimestral (4 períodos)',
        'trimestre' => 'Trimestral (3 períodos)',
        'semestre' => 'Semestral (2 períodos)',
        'etapa_unica' => 'Etapa única',
    ];

    /** @var array<int, array<string,mixed>> */
    private static array $cachePorAno = [];

    private static ?bool $colunaExiste = null;

    public static function tipoPadrao(): string
    {
        return 'bimestre';
    }

    public static function normalizarTipo(?string $tipo): string
    {
        $tipo = strtolower(trim((string) $tipo));
        return isset(self::TIPOS[$tipo]) ? $tipo : self::tipoPadrao();
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
        } catch (Throwable $e) {
            // Sem tabela/coluna: só o ano corrente.
        }
        return $mapa;
    }

    public static function numeroValido(int $ano, int $numero): bool
    {
        $info = self::doAno($ano);
        return $numero >= 1 && $numero <= (int) $info['quantidade'];
    }

    public static function rotulo(int $ano, int $numero): string
    {
        $info = self::doAno($ano);
        if ($numero <= 0) {
            return '';
        }
        return (string) ($info['rotulos'][$numero] ?? ($numero . 'º ' . $info['rotulo_campo']));
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
        } catch (Throwable $e) {
            return self::tipoPadrao();
        }
        return self::tipoPadrao();
    }

    private static function temColuna(): bool
    {
        if (self::$colunaExiste !== null) {
            return self::$colunaExiste;
        }
        self::$colunaExiste = false;
        try {
            $db = Database::getInstance();
            $row = $db->fetch(
                "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ano_letivo' AND COLUMN_NAME = 'periodo_tipo'"
            );
            self::$colunaExiste = is_array($row) && (int) ($row['n'] ?? 0) > 0;
        } catch (Throwable $e) {
            self::$colunaExiste = false;
        }
        return self::$colunaExiste;
    }
}
