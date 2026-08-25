<?php

namespace App\Modulos\CensoEscolar\Services;

/**
 * De-para da série acadêmica para códigos oficiais de etapa (Anexo 6 do leiaute 2026).
 */
class CensoEtapaDePara
{
    /**
     * @return array{codigo:string, modalidade:string, descricao:string, agregada:string}
     */
    public static function sugerir(array $turma): array
    {
        $serie = self::normalizar((string) ($turma['serie'] ?? ''));
        $nome = self::normalizar((string) ($turma['nome'] ?? ''));
        $texto = trim($serie . ' ' . $nome);

        if (self::contem($texto, ['eja', 'educacao de jovens', 'educação de jovens'])) {
            return self::saida('69', 'eja', 'EJA — ensino fundamental', '306');
        }
        if (self::contem($texto, ['bercario', 'berçario', 'maternal', 'creche'])) {
            return self::saida('1', 'regular', 'Educação infantil - creche', '301');
        }
        if (self::contem($texto, ['pre escola', 'pré escola', 'pre-escola', 'educacao infantil', 'educação infantil'])) {
            return self::saida('2', 'regular', 'Educação infantil - pré-escola', '301');
        }

        $medio = (bool) preg_match('/ensino\s+m[eé]dio|\bunidade\s+ensino\s+m|\bem\s*[1-3]\b/u', $texto);
        $ano = self::extrairNumero($texto);

        if ($medio) {
            $mapa = [1 => '25', 2 => '26', 3 => '27', 4 => '28'];
            $codigo = $mapa[$ano] ?? '29';
            $desc = [1 => '1ª Série', 2 => '2ª Série', 3 => '3ª Série', 4 => '4ª Série'];
            return self::saida($codigo, 'regular', 'Ensino médio - ' . ($desc[$ano] ?? 'não seriada'), '304');
        }

        $ef = [
            1 => '14', 2 => '15', 3 => '16', 4 => '17', 5 => '18',
            6 => '19', 7 => '20', 8 => '21', 9 => '41',
        ];
        if (preg_match('/\b(1|2|3|4|5|6|7|8|9)(o|º|a|ª)?\s*(ano|serie|série)\b/u', $texto, $m)) {
            $n = (int) $m[1];
            return self::saida($ef[$n], 'regular', 'Ensino fundamental de 9 anos - ' . $n . 'º Ano', '302');
        }
        if (isset($ef[$ano])) {
            return self::saida($ef[$ano], 'regular', 'Ensino fundamental de 9 anos - ' . $ano . 'º Ano', '302');
        }

        return ['codigo' => '', 'modalidade' => '', 'descricao' => '', 'agregada' => ''];
    }

    public static function ehInterno(string $codigo): bool
    {
        return (bool) preg_match('/^(EI|EF[1-9]|EM[1-3]?|EJA)$/i', trim($codigo));
    }

    /**
     * @param list<string> $termos
     */
    private static function contem(string $texto, array $termos): bool
    {
        foreach ($termos as $termo) {
            if ($termo !== '' && mb_strpos($texto, $termo) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function extrairNumero(string $texto): int
    {
        if (preg_match('/\b(\d{1,2})\s*(o|º|a|ª)?\s*(ano|serie|série)?\b/u', $texto, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    private static function normalizar(string $valor): string
    {
        $valor = trim(mb_strtolower($valor, 'UTF-8'));
        $valor = str_replace(['º', 'ª', '.'], ['o', 'a', ''], $valor);
        return preg_replace('/\s+/', ' ', $valor) ?? $valor;
    }

    /**
     * @return array{codigo:string, modalidade:string, descricao:string, agregada:string}
     */
    private static function saida(string $codigo, string $modalidade, string $descricao, string $agregada): array
    {
        return [
            'codigo' => $codigo,
            'modalidade' => $modalidade,
            'descricao' => $descricao,
            'agregada' => $agregada,
        ];
    }
}
