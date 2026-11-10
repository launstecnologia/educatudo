<?php

namespace App\Modulos\ModelosDocumentos\Services;

/** Campos individuais lidos do quadro oficial, sem refazer as fórmulas da escola. */
class CamposAcademicosDocumentoService
{
    public static function doQuadro(string $html): array
    {
        $out = ['vars' => [], 'componentes' => []];
        if (!str_contains($html, 'data-boletim-coluna')) return $out;
        $doc = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<meta charset="utf-8">' . $html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $xp = new \DOMXPath($doc);
        $usados = [];
        foreach ($xp->query('//tr[td[@data-boletim-coluna]]') as $linha) {
            $primeira = $xp->query('./td | ./th', $linha)->item(0);
            $nome = trim(preg_replace('/^[\s↳↪]+/u', '', $primeira->textContent) ?? '');
            if ($nome === '' || $nome === '—') continue;
            $slug = self::slug($nome);
            $base = $slug;
            $n = 2;
            while (isset($usados[$slug])) $slug = $base . '_' . $n++;
            $usados[$slug] = true;
            $comp = ['nome' => $nome, 'slug' => $slug, 'notas' => [], 'faltas' => [], 'media' => 'media_final_' . $slug, 'eventos' => []];
            $out['vars']['media_final_' . $slug] = '—';
            $out['vars']['media_anual_' . $slug] = '—';
            foreach ($xp->query('./td[@data-boletim-coluna]', $linha) as $celula) {
                $codigo = self::chave($celula->getAttribute('data-boletim-coluna'));
                $valor = trim(preg_replace('/\s+/u', ' ', $celula->textContent) ?? '');
                $valor = htmlspecialchars($valor !== '' ? $valor : '—', ENT_QUOTES, 'UTF-8');
                $evento = 'evento_' . $slug . '_' . $codigo;
                $out['vars'][$evento] = $valor;
                $comp['eventos'][] = ['chave' => $evento, 'rotulo' => strtoupper(str_replace('_', ' ', $codigo))];
                if (preg_match('/^b(\d+)_(mu|fal)$/', $codigo, $m)) {
                    $tipo = $m[2] === 'fal' ? 'falta' : 'nota';
                    $chave = $tipo . '_' . $slug . '_' . $m[1];
                    $out['vars'][$chave] = $valor;
                    $comp[$tipo === 'falta' ? 'faltas' : 'notas'][(int) $m[1] - 1] = $chave;
                }
                if ($codigo === 'mf' || str_ends_with($codigo, '_mf')) $out['vars']['media_final_' . $slug] = $valor;
                if ($codigo === 'ma' || str_ends_with($codigo, '_ma')) $out['vars']['media_anual_' . $slug] = $valor;
            }
            $out['componentes'][] = $comp;
        }
        return $out;
    }

    private static function slug(string $nome): string
    {
        $alvo = self::chave($nome);
        foreach (GradeSoltaService::componentes() as $comp) {
            if (!empty($comp['vaga'])) continue;
            foreach (array_merge([$comp['nome']], $comp['aliases'] ?? []) as $alias) {
                if (self::chave($alias) === $alvo) return $comp['slug'];
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
