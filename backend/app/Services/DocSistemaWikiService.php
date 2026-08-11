<?php
/**
 * Lê Markdowns de doc_sistema/ para a wiki (admin e master).
 */

class DocSistemaWikiService
{
    private string $dirBase;

    /** Ordem preferencial no índice (slug => peso). */
    private const ORDEM = [
        'index' => 0,
        'estrutura' => 1,
        'multi-tenant' => 2,
        'perfis' => 3,
        'assistente' => 4,
        'tool' => 5,
    ];

    public function __construct(?string $dirBase = null)
    {
        if ($dirBase !== null) {
            $this->dirBase = realpath($dirBase) ?: '';
            return;
        }
        $this->dirBase = realpath(__DIR__ . '/../../doc_sistema') ?: '';
    }

    public function dirBase(): string
    {
        return $this->dirBase;
    }

    /**
     * @return list<array{slug:string,titulo:string,atualizado_em:?string}>
     */
    public function listarPaginas(): array
    {
        if ($this->dirBase === '' || !is_dir($this->dirBase)) {
            return [];
        }
        $files = glob($this->dirBase . '/*.md') ?: [];
        $out = [];
        foreach ($files as $path) {
            $slug = pathinfo($path, PATHINFO_FILENAME);
            if ($slug === '' || strcasecmp($slug, 'README') === 0) {
                continue;
            }
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $slug)) {
                continue;
            }
            $mtime = @filemtime($path);
            $out[] = [
                'slug' => strtolower($slug),
                'titulo' => $this->tituloDoArquivo($path, $slug),
                'atualizado_em' => $mtime ? date('d/m/Y H:i', $mtime) : null,
            ];
        }
        usort($out, static function ($a, $b) {
            $oa = self::ORDEM[$a['slug']] ?? 100;
            $ob = self::ORDEM[$b['slug']] ?? 100;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            return strcasecmp($a['titulo'], $b['titulo']);
        });
        return $out;
    }

    /**
     * @return array{slug:string,titulo:string,conteudo_md:string,atualizado_em:?string}|null
     */
    public function carregarPagina(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) || $this->dirBase === '') {
            return null;
        }
        $path = $this->dirBase . '/' . $slug . '.md';
        $real = realpath($path);
        if ($real === false || strpos($real, $this->dirBase) !== 0 || !is_file($real)) {
            return null;
        }
        $mtime = @filemtime($real);
        return [
            'slug' => $slug,
            'titulo' => $this->tituloDoArquivo($real, $slug),
            'conteudo_md' => (string) file_get_contents($real),
            'atualizado_em' => $mtime ? date('d/m/Y H:i', $mtime) : null,
        ];
    }

    /**
     * @param list<array{slug:string,titulo:string,atualizado_em:?string}> $paginas
     * @return array{slug:string,titulo:string,conteudo_md:string,atualizado_em:?string}
     */
    public function resolverPagina(string $slug, array $paginas): array
    {
        if ($slug === '' && $paginas !== []) {
            $slug = $paginas[0]['slug'];
        }
        $doc = $slug !== '' ? $this->carregarPagina($slug) : null;
        if ($slug !== '' && $doc === null) {
            return [
                'slug' => $slug,
                'titulo' => 'Página não encontrada',
                'conteudo_md' => "# Página não encontrada\n\nO arquivo `{$slug}.md` não existe em `doc_sistema/`.",
                'atualizado_em' => null,
            ];
        }
        if ($doc === null) {
            return [
                'slug' => '',
                'titulo' => 'Documentação',
                'conteudo_md' => "# Documentação\n\nNenhum arquivo `.md` em `doc_sistema/`.",
                'atualizado_em' => null,
            ];
        }
        return $doc;
    }

    private function tituloDoArquivo(string $path, string $slugFallback): string
    {
        $fh = @fopen($path, 'r');
        if ($fh) {
            for ($i = 0; $i < 12; $i++) {
                $line = fgets($fh);
                if ($line === false) {
                    break;
                }
                if (preg_match('/^#\s+(.+)$/u', trim($line), $m)) {
                    fclose($fh);
                    return trim($m[1]);
                }
            }
            fclose($fh);
        }
        return ucfirst(str_replace(['-', '_'], ' ', $slugFallback));
    }
}
