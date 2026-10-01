<?php

/**
 * Exporta os boletins gerados (todas as matérias e notas) em JSON, Excel ou PDF.
 */
class BoletimGeradosExportacao
{
    /**
     * @param list<array<string,mixed>> $boletins
     */
    public static function enviar(string $formato, array $boletins, bool $truncado = false): void
    {
        $formato = strtolower(trim($formato));
        $nome = 'boletins-notas-' . date('Ymd-His');
        if ($formato === 'json') {
            self::enviarJson($boletins, $nome, $truncado);
            return;
        }
        if ($formato === 'excel') {
            self::enviarExcel($boletins, $nome, $truncado);
            return;
        }
        self::enviarPdf($boletins, $nome, $truncado);
    }

    private static function avisoTruncado(): string
    {
        return 'A exportação parou em 80.000 linhas de matéria. O restante do filtro não entrou no arquivo.';
    }

    /**
     * @param list<array<string,mixed>> $boletins
     * @return array<string, array{titulo:string,aba:string,lista:list<array<string,mixed>>}>
     */
    private static function agruparPorRegra(array $boletins): array
    {
        $grupos = [];
        foreach ($boletins as $boletim) {
            if (!is_array($boletim)) {
                continue;
            }
            $id = (int) ($boletim['regra_id'] ?? 0);
            $codigo = trim((string) ($boletim['regra_codigo'] ?? ''));
            $nome = trim((string) ($boletim['regra'] ?? ''));
            $chave = $id > 0 ? (string) $id : ($codigo !== '' ? $codigo : $nome);
            if ($chave === '') {
                $chave = 'regra';
            }
            if (!isset($grupos[$chave])) {
                $titulo = $nome !== '' ? $nome : 'Regra';
                if ($codigo !== '') {
                    $titulo .= ' (' . $codigo . ')';
                } elseif ($id > 0) {
                    $titulo .= ' #' . $id;
                }
                $aba = $codigo !== '' ? $codigo : ($id > 0 ? ('regra-' . $id) : $titulo);
                $grupos[$chave] = ['titulo' => $titulo, 'aba' => $aba, 'lista' => []];
            }
            $grupos[$chave]['lista'][] = $boletim;
        }
        return $grupos;
    }

    /**
     * @param list<array<string,mixed>> $boletins
     */
    private static function enviarJson(array $boletins, string $nome, bool $truncado): void
    {
        $payload = [
            'gerado_em' => date('c'),
            'total_boletins' => count($boletins),
            'boletins' => [],
        ];
        if ($truncado) {
            $payload['aviso'] = self::avisoTruncado();
        }
        foreach ($boletins as $boletim) {
            $colunas = self::colunasDoBoletim($boletim);
            $materias = [];
            foreach ((array) ($boletim['linhas'] ?? []) as $linha) {
                if (!is_array($linha)) {
                    continue;
                }
                $notas = [];
                foreach ($colunas as $col) {
                    $notas[$col['rotulo']] = self::valorNota($linha, $col, (int) ($boletim['decimal_places'] ?? 2));
                }
                $materias[] = [
                    'materia' => (string) ($linha['materia'] ?? ''),
                    'notas' => $notas,
                ];
            }
            $payload['boletins'][] = [
                'aluno' => (string) ($boletim['aluno'] ?? ''),
                'ra' => (string) ($boletim['ra'] ?? ''),
                'turma' => (string) ($boletim['turma'] ?? ''),
                'regra_id' => (int) ($boletim['regra_id'] ?? 0),
                'regra' => (string) ($boletim['regra'] ?? ''),
                'regra_codigo' => (string) ($boletim['regra_codigo'] ?? ''),
                'bimestre' => self::rotuloBimestre($boletim),
                'versao' => (int) ($boletim['versao'] ?? 1),
                'exibir_em' => (string) ($boletim['exibir_em'] ?? ''),
                'materias' => $materias,
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nome . '.json"');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * @param list<array<string,mixed>> $boletins
     */
    private static function enviarExcel(array $boletins, string $nome, bool $truncado): void
    {
        $grupos = self::agruparPorRegra($boletins);
        if ($grupos === []) {
            $grupos['vazio'] = ['titulo' => 'Notas', 'aba' => 'Notas', 'lista' => []];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $usados = [];
        if ($truncado) {
            $xml .= '<Worksheet ss:Name="Aviso"><Table>';
            $xml .= self::linhaExcel([self::avisoTruncado()], true);
            $xml .= '</Table></Worksheet>';
        }
        $fixos = ['Aluno', 'RA', 'Turma', 'Regra', 'Código', 'Bimestre', 'Versão', 'Matéria'];
        foreach ($grupos as $grupo) {
            $lista = $grupo['lista'];
            $xml .= '<Worksheet ss:Name="' . self::xml(self::nomeAba($grupo['aba'], $usados)) . '"><Table>';
            $colunas = self::colunasUniao($lista);
            $cabecalhos = $fixos;
            foreach ($colunas as $col) {
                $cabecalhos[] = $col['rotulo'];
            }
            $xml .= self::linhaExcel($cabecalhos, true);
            foreach ($lista as $boletim) {
                $casas = (int) ($boletim['decimal_places'] ?? 2);
                foreach ((array) ($boletim['linhas'] ?? []) as $linha) {
                    if (!is_array($linha)) {
                        continue;
                    }
                    $celulas = [
                        (string) ($boletim['aluno'] ?? ''),
                        (string) ($boletim['ra'] ?? ''),
                        (string) ($boletim['turma'] ?? ''),
                        (string) ($boletim['regra'] ?? ''),
                        (string) ($boletim['regra_codigo'] ?? ''),
                        self::rotuloBimestre($boletim),
                        (string) (int) ($boletim['versao'] ?? 1),
                        (string) ($linha['materia'] ?? ''),
                    ];
                    foreach ($colunas as $col) {
                        $celulas[] = self::valorNota($linha, $col, $casas);
                    }
                    $xml .= self::linhaExcel($celulas, false);
                }
            }
            $xml .= '</Table></Worksheet>';
        }
        $xml .= '</Workbook>';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nome . '.xls"');
        echo $xml;
    }

    /**
     * @param list<array<string,mixed>> $boletins
     */
    private static function enviarPdf(array $boletins, string $nome, bool $truncado): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
            h1 { font-size: 14px; margin: 0 0 4px; }
            h2 { font-size: 11px; margin: 14px 0 4px; }
            p.meta { color: #444; margin: 0 0 8px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
            th, td { border: 1px solid #ccc; padding: 2px 3px; text-align: center; }
            th { background: #f3f4f6; }
            td.nome, th.nome { text-align: left; }
        </style></head><body>';
        $html .= '<h1>Boletins gerados</h1>';
        $html .= '<p class="meta">Exportado em ' . self::h(date('d/m/Y H:i')) . ' · ' . count($boletins) . ' boletim(ns), com todas as notas.</p>';
        if ($truncado) {
            $html .= '<p class="meta">' . self::h(self::avisoTruncado()) . '</p>';
        }

        $grupos = self::agruparPorRegra($boletins);
        if ($grupos === []) {
            $html .= '<p>Nenhum boletim no filtro atual.</p>';
        }
        $fixos = ['Aluno', 'RA', 'Turma', 'Regra', 'Código', 'Bimestre', 'Versão', 'Matéria'];
        foreach ($grupos as $grupo) {
            $lista = $grupo['lista'];
            $colunas = self::colunasUniao($lista);
            $html .= '<h2>' . self::h($grupo['titulo']) . '</h2>';
            $html .= '<table><thead><tr>';
            foreach ($fixos as $fixo) {
                $html .= '<th class="nome">' . self::h($fixo) . '</th>';
            }
            foreach ($colunas as $col) {
                $html .= '<th>' . self::h($col['rotulo']) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($lista as $boletim) {
                $casas = (int) ($boletim['decimal_places'] ?? 2);
                foreach ((array) ($boletim['linhas'] ?? []) as $linha) {
                    if (!is_array($linha)) {
                        continue;
                    }
                    $html .= '<tr>';
                    $html .= '<td class="nome">' . self::h((string) ($boletim['aluno'] ?? '')) . '</td>';
                    $html .= '<td>' . self::h((string) ($boletim['ra'] ?? '')) . '</td>';
                    $html .= '<td>' . self::h((string) ($boletim['turma'] ?? '')) . '</td>';
                    $html .= '<td class="nome">' . self::h((string) ($boletim['regra'] ?? '')) . '</td>';
                    $html .= '<td>' . self::h((string) ($boletim['regra_codigo'] ?? '')) . '</td>';
                    $html .= '<td>' . self::h(self::rotuloBimestre($boletim)) . '</td>';
                    $html .= '<td>' . (int) ($boletim['versao'] ?? 1) . '</td>';
                    $html .= '<td class="nome">' . self::h((string) ($linha['materia'] ?? '')) . '</td>';
                    foreach ($colunas as $col) {
                        $html .= '<td>' . self::h(self::valorNota($linha, $col, $casas)) . '</td>';
                    }
                    $html .= '</tr>';
                }
            }
            $html .= '</tbody></table>';
        }
        $html .= '</body></html>';

        if (!class_exists(\Dompdf\Dompdf::class)) {
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }
        }
        if (!class_exists(\Dompdf\Dompdf::class)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Não foi possível gerar o PDF.';
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A3', 'landscape');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nome . '.pdf"');
        echo $dompdf->output();
    }

    /**
     * @param array<string,mixed> $boletim
     * @return list<array{codigo:string,rotulo:string,faltas:bool}>
     */
    private static function colunasDoBoletim(array $boletim): array
    {
        $out = [];
        $vistos = [];
        foreach ((array) ($boletim['colunas'] ?? []) as $col) {
            if (!is_array($col)) {
                continue;
            }
            $codigo = trim((string) ($col['codigo'] ?? ''));
            if ($codigo === '' || isset($vistos[$codigo])) {
                continue;
            }
            $vistos[$codigo] = true;
            $rotulo = trim((string) ($col['nome'] ?? ''));
            if ($rotulo === '') {
                $rotulo = $codigo;
            }
            $tipo = strtolower(trim((string) ($col['layout_type'] ?? '')));
            if ($tipo === 'semana_nq') {
                $out[] = ['codigo' => $codigo . '__n', 'rotulo' => $rotulo . ' N', 'faltas' => false, 'inteiro' => true];
                $out[] = ['codigo' => $codigo . '__q', 'rotulo' => $rotulo . ' Q', 'faltas' => false, 'inteiro' => true];
                continue;
            }
            $faltas = ((string) ($col['source_type'] ?? '')) === 'faltas_evento'
                || $tipo === 'faltas';
            $out[] = ['codigo' => $codigo, 'rotulo' => $rotulo, 'faltas' => $faltas, 'inteiro' => false];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $boletins
     * @return list<array{codigo:string,rotulo:string,faltas:bool}>
     */
    private static function colunasUniao(array $boletins): array
    {
        $out = [];
        $vistos = [];
        $rotulos = [];
        foreach ($boletins as $boletim) {
            foreach (self::colunasDoBoletim($boletim) as $col) {
                if (isset($vistos[$col['codigo']])) {
                    continue;
                }
                $vistos[$col['codigo']] = true;
                $rotulo = $col['rotulo'];
                if (isset($rotulos[$rotulo])) {
                    $rotulo .= ' (' . $col['codigo'] . ')';
                }
                $rotulos[$rotulo] = true;
                $col['rotulo'] = $rotulo;
                $out[] = $col;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $linha
     * @param array{codigo:string,rotulo:string,faltas:bool} $col
     */
    private static function valorNota(array $linha, array $col, int $casas): string
    {
        $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
        $nv = $notas[$col['codigo']] ?? null;
        if ($nv === null || $nv === '') {
            return '';
        }
        if (is_numeric($nv)) {
            if (!empty($col['faltas']) || !empty($col['inteiro'])) {
                return number_format((float) round((float) $nv), 0, ',', '');
            }
            $casas = $casas === 1 ? 1 : 2;
            return number_format((float) $nv, $casas, ',', '');
        }
        return trim((string) $nv);
    }

    /**
     * @param list<string> $celulas
     */
    private static function linhaExcel(array $celulas, bool $cabecalho): string
    {
        $xml = '<Row>';
        foreach ($celulas as $i => $celula) {
            $texto = (string) $celula;
            $ehNota = !$cabecalho && $i >= 8 && $texto !== '' && preg_match('/^-?\d+(,\d+)?$/', $texto);
            if ($ehNota) {
                $normalizado = str_replace(',', '.', $texto);
                $xml .= '<Cell><Data ss:Type="Number">' . self::xml($normalizado) . '</Data></Cell>';
            } else {
                $xml .= '<Cell><Data ss:Type="String">' . self::xml($texto) . '</Data></Cell>';
            }
        }
        return $xml . '</Row>';
    }

    /**
     * @param array<string,bool> $usados
     */
    private static function nomeAba(string $nome, array &$usados): string
    {
        $nome = preg_replace('/[\\\\\\/\\?\\*\\:\\[\\]]/', ' ', $nome) ?? 'Notas';
        $nome = trim(preg_replace('/\s+/', ' ', $nome) ?? 'Notas');
        if ($nome === '') {
            $nome = 'Notas';
        }
        if (function_exists('mb_substr')) {
            $nome = mb_substr($nome, 0, 28, 'UTF-8');
        } else {
            $nome = substr($nome, 0, 28);
        }
        $base = $nome;
        $i = 2;
        while (isset($usados[$nome])) {
            $sufixo = ' ' . $i;
            $nome = $base;
            if (function_exists('mb_substr')) {
                $nome = mb_substr($base, 0, 31 - strlen($sufixo), 'UTF-8') . $sufixo;
            } else {
                $nome = substr($base, 0, 31 - strlen($sufixo)) . $sufixo;
            }
            $i++;
        }
        $usados[$nome] = true;
        return $nome;
    }

    /**
     * @param array<string,mixed> $boletim
     */
    private static function rotuloBimestre(array $boletim): string
    {
        if (!class_exists('PeriodoLetivo', false)) {
            require_once dirname(__DIR__) . '/Core/PeriodoLetivo.php';
        }
        return PeriodoLetivo::rotuloBoletim(
            (int) ($boletim['ano_letivo'] ?? 0),
            (int) ($boletim['bimestre'] ?? 0),
            (string) ($boletim['regra'] ?? '')
        );
    }

    private static function xml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function h(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }
}
