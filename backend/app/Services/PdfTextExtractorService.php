<?php
/**
 * EducaTudo - Extração de texto de PDF sem dependências externas
 *
 * Implementação mínima e sem biblioteca de terceiros (evita mexer no
 * composer.lock, que está travado numa cadeia de dependências frágil —
 * ver commit desta mudança). Cobre o caso comum de PDF "de texto" gerado
 * digitalmente (não é OCR): descompacta streams FlateDecode e extrai os
 * operadores de texto (Tj/TJ) do content stream. PDFs escaneados
 * (imagem pura, sem texto real) não têm texto extraível por este método
 * — nesse caso retorna string vazia e o chamador decide o que informar
 * ao aluno.
 */

namespace App\Services;

class PdfTextExtractorService
{
    /**
     * @param string $conteudoPdf Bytes crus do arquivo PDF
     * @param int $limiteCaracteres Corta o texto extraído neste tamanho
     * @return array{texto: string, truncado: bool}
     */
    public static function extrairTexto(string $conteudoPdf, int $limiteCaracteres = 8000): array
    {
        $partes = [];

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $conteudoPdf, $streams, PREG_SET_ORDER)) {
            foreach ($streams as $streamMatch) {
                $bruto = $streamMatch[1];
                $descompactado = @gzuncompress($bruto);
                $conteudo = ($descompactado !== false) ? $descompactado : $bruto;

                $texto = self::extrairOperadoresDeTexto($conteudo);
                if ($texto !== '') {
                    $partes[] = $texto;
                }
            }
        }

        $textoCompleto = trim(implode("\n", $partes));
        // Normaliza espaços repetidos deixados pela concatenação de fragmentos de texto
        $textoCompleto = preg_replace('/[ \t]{2,}/', ' ', $textoCompleto);

        $truncado = false;
        if (function_exists('mb_strlen') && mb_strlen($textoCompleto) > $limiteCaracteres) {
            $textoCompleto = mb_substr($textoCompleto, 0, $limiteCaracteres);
            $truncado = true;
        }

        return ['texto' => $textoCompleto, 'truncado' => $truncado];
    }

    /**
     * Extrai o texto dos operadores de exibição de texto do PDF (Tj e TJ)
     * de um content stream já descompactado.
     */
    private static function extrairOperadoresDeTexto(string $conteudo): string
    {
        $pedacos = [];

        // Operador Tj: (texto) Tj
        if (preg_match_all('/\(((?:\\\\.|[^()\\\\])*)\)\s*Tj/', $conteudo, $matches)) {
            foreach ($matches[1] as $m) {
                $pedacos[] = self::decodificarStringPdf($m);
            }
        }

        // Operador TJ: [(texto1) -120 (texto2)] TJ — array com strings intercaladas de ajuste de kerning
        if (preg_match_all('/\[((?:[^\[\]]|\\\\.)*)\]\s*TJ/', $conteudo, $arrays)) {
            foreach ($arrays[1] as $arrayConteudo) {
                if (preg_match_all('/\(((?:\\\\.|[^()\\\\])*)\)/', $arrayConteudo, $sub)) {
                    $linha = '';
                    foreach ($sub[1] as $m) {
                        $linha .= self::decodificarStringPdf($m);
                    }
                    if ($linha !== '') {
                        $pedacos[] = $linha;
                    }
                }
            }
        }

        return implode(' ', $pedacos);
    }

    /**
     * Decodifica escapes de string PDF: \( \) \\ e sequências octais \ddd.
     */
    private static function decodificarStringPdf(string $string): string
    {
        $string = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
            return chr(octdec($m[1]));
        }, $string);
        $string = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $string);

        return $string;
    }
}
