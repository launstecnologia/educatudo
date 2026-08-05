<?php

namespace App\Performance\Reports;

/**
 * Exporta uma lista de arrays associativos (mesma forma) para CSV.
 * Abre bem no Excel/Sheets — cobre o pedido de exportação sem adicionar
 * dependência nova (uma exportação .xlsx "de verdade" precisaria do pacote
 * phpoffice/phpspreadsheet, que não está no composer.json hoje).
 */
final class CsvExporter
{
    /**
     * @param list<array<string,mixed>> $rows
     */
    public static function stream(array $rows, string $filename): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');

        $out = fopen('php://output', 'w');
        // BOM UTF-8: Excel no Windows abre acentuação correta.
        fwrite($out, "\xEF\xBB\xBF");

        if ($rows === []) {
            fclose($out);
            exit;
        }

        // PHP 8.4: o parâmetro $escape passou a ser obrigatório (senão gera Deprecated
        // e, com display_errors=1, isso vaza texto no meio do arquivo CSV). '\\' mantém
        // o comportamento histórico do fputcsv.
        $headers = array_keys(self::flatten($rows[0]));
        fputcsv($out, $headers, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_values(self::flatten($row)), ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /**
     * Achata valores array/bool para string legível numa célula de CSV.
     *
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private static function flatten(array $row): array
    {
        $flat = [];
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $flat[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $flat[$key] = $value ? '1' : '0';
            } else {
                $flat[$key] = (string) ($value ?? '');
            }
        }
        return $flat;
    }
}
