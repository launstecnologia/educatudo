<?php

namespace App\Modulos\ModelosDocumentos\Services;

/** Campos individuais lidos do quadro oficial, sem refazer as fórmulas da escola. */
class CamposAcademicosDocumentoService
{
    public static function doQuadro(string $html): array
    {
        $out = ['vars' => [], 'componentes' => [], 'grupos' => []];
        if (!str_contains($html, 'data-boletim-coluna')) {
            return $out;
        }
        $doc = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<meta charset="utf-8">' . $html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $xp = new \DOMXPath($doc);
        $tabela = $xp->query('//table[.//td[@data-boletim-coluna]]')->item(0);
        $mapaCab = $tabela instanceof \DOMElement ? self::mapaCabecalhos($xp, $tabela) : [];
        $gruposOrdem = [];
        $usados = [];
        foreach ($xp->query('//tr[td[@data-boletim-coluna]]') as $linha) {
            $primeira = $xp->query('./td | ./th', $linha)->item(0);
            $nome = trim(preg_replace('/^[\s↳↪]+/u', '', $primeira->textContent) ?? '');
            if ($nome === '' || $nome === '—') {
                continue;
            }
            $slug = self::slug($nome);
            $base = $slug;
            $n = 2;
            while (isset($usados[$slug])) {
                $slug = $base . '_' . $n++;
            }
            $usados[$slug] = true;
            $comp = [
                'nome' => $nome,
                'slug' => $slug,
                'notas' => [],
                'faltas' => [],
                'media' => 'media_final_' . $slug,
                'eventos' => [],
            ];
            $out['vars']['media_final_' . $slug] = '—';
            $out['vars']['media_anual_' . $slug] = '—';
            $idx = 0;
            $codigosUsados = [];
            foreach ($xp->query('./td[@data-boletim-coluna]', $linha) as $celula) {
                $codigoBruto = trim((string) $celula->getAttribute('data-boletim-coluna'));
                $codigo = self::chave($codigoBruto !== '' ? $codigoBruto : 'coluna_' . ($idx + 1));
                $valor = trim(preg_replace('/\s+/u', ' ', $celula->textContent) ?? '');
                $valor = htmlspecialchars($valor !== '' ? $valor : '—', ENT_QUOTES, 'UTF-8');
                $meta = $mapaCab[$idx] ?? self::metaDoCodigo($codigo, $codigoBruto);
                $grupo = (string) ($meta['grupo'] ?? '');
                $rotulo = (string) ($meta['rotulo'] ?? '');
                if ($rotulo === '') {
                    $rotulo = self::rotuloCurto($codigoBruto !== '' ? $codigoBruto : $codigo);
                }
                if ($grupo === '') {
                    $grupo = 'Campos';
                }
                if (!isset($gruposOrdem[$grupo])) {
                    $gruposOrdem[$grupo] = true;
                }
                $codigoEvento = self::codigoEventoUnico($codigo, $grupo, $idx, $codigosUsados);
                $codigosUsados[$codigoEvento] = true;
                $evento = 'evento_' . $slug . '_' . $codigoEvento;
                $out['vars'][$evento] = $valor;
                $comp['eventos'][] = [
                    'chave' => $evento,
                    'rotulo' => $rotulo,
                    'grupo' => $grupo,
                    'codigo' => $codigoEvento,
                ];
                if (preg_match('/^b(\d+)_(mu|fal)$/', $codigo, $m) || preg_match('/^b(\d+)_(mu|fal)$/', $codigoEvento, $m)) {
                    $tipo = $m[2] === 'fal' ? 'falta' : 'nota';
                    $chave = $tipo . '_' . $slug . '_' . $m[1];
                    $out['vars'][$chave] = $valor;
                    $comp[$tipo === 'falta' ? 'faltas' : 'notas'][(int) $m[1] - 1] = $chave;
                }
                if ($codigo === 'mf' || str_ends_with($codigo, '_mf') || strcasecmp($rotulo, 'MF') === 0) {
                    $out['vars']['media_final_' . $slug] = $valor;
                }
                if ($codigo === 'ma' || str_ends_with($codigo, '_ma') || strcasecmp($rotulo, 'MA') === 0) {
                    $out['vars']['media_anual_' . $slug] = $valor;
                }
                if ($codigo === 'mu' || str_ends_with($codigo, '_mu') || strcasecmp($rotulo, 'MU') === 0) {
                    if (preg_match('/^b(\d+)/', $codigo, $mb) || preg_match('/(\d+)/', $grupo, $mb)) {
                        $chaveNota = 'nota_' . $slug . '_' . (int) ($mb[1] ?? 0);
                        if ((int) ($mb[1] ?? 0) > 0) {
                            $out['vars'][$chaveNota] = $valor;
                            $comp['notas'][(int) $mb[1] - 1] = $chaveNota;
                        }
                    }
                }
                $idx++;
            }
            $out['componentes'][] = $comp;
        }
        $out['grupos'] = array_keys($gruposOrdem);

        return $out;
    }

    /**
     * Lê o thead do quadro: grupo (bimestre/semestre/final) + nome do campo (PP, MU, MA…).
     *
     * @return list<array{grupo:string,rotulo:string}>
     */
    private static function mapaCabecalhos(\DOMXPath $xp, \DOMElement $tabela): array
    {
        $linhas = [];
        foreach ($xp->query('./thead/tr', $tabela) as $linha) {
            $linhas[] = $linha;
        }
        if ($linhas === []) {
            return [];
        }
        $grade = [];
        foreach ($linhas as $r => $linha) {
            $c = 0;
            foreach ($xp->query('./th | ./td', $linha) as $celula) {
                while (isset($grade[$r][$c])) {
                    $c++;
                }
                $nome = trim(preg_replace('/\s+/u', ' ', $celula->textContent) ?? '');
                $altura = min(20, max(1, (int) $celula->getAttribute('rowspan') ?: 1));
                $largura = min(200, max(1, (int) $celula->getAttribute('colspan') ?: 1));
                for ($rr = $r; $rr < $r + $altura; $rr++) {
                    for ($cc = $c; $cc < $c + $largura; $cc++) {
                        $grade[$rr][$cc] = $nome;
                    }
                }
                $c += $largura;
            }
        }
        if ($grade === []) {
            return [];
        }
        $maxCol = 0;
        foreach ($grade as $linha) {
            $maxCol = max($maxCol, max(array_keys($linha)));
        }
        $ultimaLinha = max(array_keys($grade));
        $out = [];
        // Coluna 0 costuma ser o nome do componente.
        for ($c = 1; $c <= $maxCol; $c++) {
            $rotulo = trim((string) ($grade[$ultimaLinha][$c] ?? ''));
            $grupo = '';
            for ($r = 0; $r < $ultimaLinha; $r++) {
                $cand = trim((string) ($grade[$r][$c] ?? ''));
                if ($cand !== '' && strcasecmp($cand, $rotulo) !== 0) {
                    $grupo = $cand;
                    break;
                }
            }
            if ($rotulo === '' && $grupo === '') {
                continue;
            }
            if ($rotulo === '') {
                $rotulo = $grupo;
                $grupo = '';
            }
            $out[] = ['grupo' => $grupo, 'rotulo' => $rotulo];
        }

        return $out;
    }

    /**
     * Evita colisão quando o mesmo código (PP, MU…) se repete em vários períodos.
     *
     * @param array<string,true> $usados
     */
    private static function codigoEventoUnico(string $codigo, string $grupo, int $idx, array $usados): string
    {
        if (!isset($usados[$codigo])) {
            return $codigo;
        }
        $prefixo = self::chave($grupo);
        $candidato = ($prefixo !== '' ? $prefixo . '_' : 'c' . ($idx + 1) . '_') . $codigo;
        if (!isset($usados[$candidato])) {
            return $candidato;
        }
        $n = 2;
        while (isset($usados[$candidato . '_' . $n])) {
            $n++;
        }

        return $candidato . '_' . $n;
    }

    /** @return array{grupo:string,rotulo:string} */
    private static function metaDoCodigo(string $codigo, string $codigoBruto = ''): array
    {
        $base = $codigoBruto !== '' ? $codigoBruto : $codigo;
        if (preg_match('/^b(\d+)[_-](.+)$/i', $codigo, $m)) {
            return [
                'grupo' => ((int) $m[1]) . 'º Bimestre',
                'rotulo' => self::rotuloCurto($m[2]),
            ];
        }
        if (preg_match('/^s(\d+)[_-](.+)$/i', $codigo, $m)) {
            return [
                'grupo' => ((int) $m[1]) . 'º Semestre',
                'rotulo' => self::rotuloCurto($m[2]),
            ];
        }
        $finais = ['ma' => 'MA', 'mf' => 'MF', 'pf' => 'PF', 'situacao' => 'Situação', 'sit' => 'Situação'];
        if (isset($finais[$codigo])) {
            return ['grupo' => 'Resultado final', 'rotulo' => $finais[$codigo]];
        }
        if (in_array($codigo, ['rec', 'ms'], true)) {
            return ['grupo' => 'Semestre', 'rotulo' => strtoupper($codigo)];
        }

        return ['grupo' => 'Campos', 'rotulo' => self::rotuloCurto($base)];
    }

    private static function rotuloCurto(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return 'Campo';
        }
        if (preg_match('/^[a-z0-9_]+$/i', $valor) === 1 && !str_contains($valor, ' ')) {
            $partes = preg_split('/[_-]+/', $valor) ?: [$valor];
            $ultima = (string) end($partes);
            // Prefere o nome do campo (PP, MU, MA) ao prefixo de período (b1, s1).
            if (count($partes) > 1 && preg_match('/^(b|s)\d+$/i', (string) $partes[0])) {
                return strtoupper($ultima);
            }
            if (strlen($valor) <= 6) {
                return strtoupper($valor);
            }
            return strtoupper(str_replace('_', ' ', $valor));
        }

        return $valor;
    }

    private static function slug(string $nome): string
    {
        $alvo = self::chave($nome);
        foreach (GradeSoltaService::componentes() as $comp) {
            if (!empty($comp['vaga'])) {
                continue;
            }
            foreach (array_merge([$comp['nome']], $comp['aliases'] ?? []) as $alias) {
                if (self::chave($alias) === $alvo) {
                    return $comp['slug'];
                }
            }
        }

        return substr($alvo, 0, 28);
    }

    private static function chave(string $nome): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);

        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($ascii !== false ? $ascii : $nome)) ?? '', '_');
    }
}
