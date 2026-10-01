<?php

namespace App\Modulos\ModelosDocumentos\Services;

/**
 * Grade da ficha/relatório SEED com um token por célula.
 * O mapa diz de qual componente e de quais bimestres cada token puxa o valor.
 */
class GradeSoltaService
{
    /**
     * @return list<array{slug:string,nome:string,grupo:string,vaga:bool,aliases:list<string>}>
     */
    public static function componentes(): array
    {
        $item = static function (string $slug, string $nome, string $grupo, bool $vaga = false, array $aliases = []): array {
            return [
                'slug' => $slug,
                'nome' => $nome,
                'grupo' => $grupo,
                'vaga' => $vaga,
                'aliases' => $aliases,
            ];
        };
        return [
            $item('arte', 'Arte', 'LINGUAGENS E SUAS TECNOLOGIAS', false, ['arte']),
            $item('educacao_fisica', 'Educação Física', 'LINGUAGENS E SUAS TECNOLOGIAS', false, ['educacao fisica']),
            $item('lingua_inglesa', 'Língua Inglesa', 'LINGUAGENS E SUAS TECNOLOGIAS', false, ['lingua inglesa', 'ingles']),
            $item('lingua_portuguesa', 'Língua Portuguesa', 'LINGUAGENS E SUAS TECNOLOGIAS', false, ['lingua portuguesa', 'portugues']),
            $item('filosofia', 'Filosofia', 'CIÊNCIAS HUMANAS E SOCIAIS APLICADAS', false, ['filosofia']),
            $item('geografia', 'Geografia', 'CIÊNCIAS HUMANAS E SOCIAIS APLICADAS', false, ['geografia']),
            $item('historia', 'História', 'CIÊNCIAS HUMANAS E SOCIAIS APLICADAS', false, ['historia']),
            $item('sociologia', 'Sociologia', 'CIÊNCIAS HUMANAS E SOCIAIS APLICADAS', false, ['sociologia']),
            $item('matematica', 'Matemática', 'MATEMÁTICA E SUAS TECNOLOGIAS', false, ['matematica']),
            $item('fisica', 'Física', 'CIÊNCIAS DA NATUREZA E SUAS TECNOLOGIAS', false, ['fisica']),
            $item('quimica', 'Química', 'CIÊNCIAS DA NATUREZA E SUAS TECNOLOGIAS', false, ['quimica']),
            $item('biologia', 'Biologia', 'CIÊNCIAS DA NATUREZA E SUAS TECNOLOGIAS', false, ['biologia']),
            $item('ifa_1', 'Projeto Integrador', 'ITINERÁRIO FORMATIVO DE APROFUNDAMENTO', true, ['projeto integrador', 'projeto de vida']),
            $item('ifa_2', 'Projeto Integrador', 'ITINERÁRIO FORMATIVO DE APROFUNDAMENTO', true, []),
            $item('ifa_3', 'Projeto Integrador', 'ITINERÁRIO FORMATIVO DE APROFUNDAMENTO', true, []),
            $item('diversificada_1', 'Parte diversificada', 'PARTE DIVERSIFICADA', true, ['parte diversificada']),
        ];
    }

    /**
     * @return list<array{n:int,rotulo:string,bimestres:list<int>}>
     */
    public static function periodos(string $chave): array
    {
        return match ($chave) {
            '1127a' => [
                ['n' => 1, 'rotulo' => '1º Bimestre', 'bimestres' => [1]],
                ['n' => 2, 'rotulo' => '2º Bimestre', 'bimestres' => [2]],
                ['n' => 3, 'rotulo' => '3º Bimestre', 'bimestres' => [3]],
                ['n' => 4, 'rotulo' => '4º Bimestre', 'bimestres' => [4]],
            ],
            '1127b' => [
                ['n' => 1, 'rotulo' => '1º Semestre', 'bimestres' => [1, 2]],
                ['n' => 2, 'rotulo' => '2º Semestre', 'bimestres' => [3, 4]],
            ],
            '1128' => [
                ['n' => 1, 'rotulo' => 'Final', 'bimestres' => [1, 2, 3, 4]],
            ],
            default => [
                ['n' => 1, 'rotulo' => '1º Trimestre', 'bimestres' => [1, 2]],
                ['n' => 2, 'rotulo' => '2º Trimestre', 'bimestres' => [3]],
                ['n' => 3, 'rotulo' => '3º Trimestre', 'bimestres' => [4]],
            ],
        };
    }

    /**
     * @return array{tipo:string,periodos:list<array<string,mixed>>,componentes:list<array<string,mixed>>,alunos:int}
     */
    public static function mapa(string $chave): array
    {
        return [
            'tipo' => $chave === '1128' ? 'relatorio' : 'ficha',
            'periodos' => self::periodos($chave),
            'componentes' => self::componentes(),
            'alunos' => $chave === '1128' ? 20 : 1,
        ];
    }

    /**
     * @param list<array{n:int,rotulo:string,bimestres:list<int>}> $periodos
     */
    public static function htmlFicha(array $periodos): string
    {
        $head = '<tr><th rowspan="2">Componente</th>';
        $sub = '<tr>';
        foreach ($periodos as $periodo) {
            $head .= '<th colspan="2">' . htmlspecialchars((string) $periodo['rotulo'], ENT_QUOTES, 'UTF-8') . '</th>';
            $sub .= '<th>Notas ou Menções</th><th>Faltas</th>';
        }
        $head .= '<th rowspan="2">Média anual</th><th rowspan="2">Média final</th></tr>';
        $sub .= '</tr>';
        $body = '';
        $grupoAtual = '';
        foreach (self::componentes() as $comp) {
            if ($comp['grupo'] !== $grupoAtual) {
                $grupoAtual = $comp['grupo'];
                $span = 3 + (count($periodos) * 2);
                $body .= '<tr><td colspan="' . $span . '" style="font-weight:bold;background:#f3f4f6;">'
                    . htmlspecialchars($grupoAtual, ENT_QUOTES, 'UTF-8') . '</td></tr>';
            }
            $slug = $comp['slug'];
            $rotulo = $comp['vaga']
                ? '{{comp_' . $slug . '}}'
                : htmlspecialchars($comp['nome'], ENT_QUOTES, 'UTF-8');
            $body .= '<tr><td>' . $rotulo . '</td>';
            foreach ($periodos as $periodo) {
                $n = (int) $periodo['n'];
                $body .= '<td>{{nota_' . $slug . '_' . $n . '}}</td><td>{{falta_' . $slug . '_' . $n . '}}</td>';
            }
            $body .= '<td>{{media_anual_' . $slug . '}}</td><td>{{media_final_' . $slug . '}}</td></tr>';
        }
        return '<table class="dados seed-compacta" style="width:100%;border-collapse:collapse;font-size:8px;page-break-inside:avoid;">'
            . $head . $sub . $body . '</table>';
    }

    public static function htmlRelatorio(): string
    {
        $comps = self::componentes();
        $head = '<tr><th>Nº</th><th>Aluno(a)</th><th>Gên.</th>';
        foreach ($comps as $comp) {
            $head .= '<th>' . htmlspecialchars($comp['vaga'] ? $comp['grupo'] : $comp['nome'], ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $head .= '<th>Resultado</th></tr>';
        $body = '';
        for ($i = 1; $i <= 20; $i++) {
            $body .= '<tr><td>' . $i . '</td><td>{{aluno_' . $i . '_nome}}</td><td>{{aluno_' . $i . '_sexo}}</td>';
            foreach ($comps as $comp) {
                $body .= '<td>{{aluno_' . $i . '_' . $comp['slug'] . '}}</td>';
            }
            $body .= '<td>{{aluno_' . $i . '_resultado}}</td></tr>';
        }
        return '<table class="dados seed-compacta" style="width:100%;border-collapse:collapse;font-size:7px;page-break-inside:avoid;">' . $head . $body . '</table>';
    }

    /**
     * Preenche os tokens da grade. Sem fonte real, usa amostra para o preview.
     *
     * @param array<string,mixed> $grade
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    public static function completar(array $grade, array $vars): array
    {
        $fonte = is_array($vars['_grade_fonte'] ?? null) ? $vars['_grade_fonte'] : null;
        $amostra = $fonte === null;
        $periodos = is_array($grade['periodos'] ?? null) ? $grade['periodos'] : [];
        $componentes = is_array($grade['componentes'] ?? null) ? $grade['componentes'] : self::componentes();
        $tipo = (string) ($grade['tipo'] ?? 'ficha');
        if ($tipo === 'relatorio') {
            return self::completarRelatorio($componentes, $vars, $fonte, $amostra);
        }
        $porSlug = self::indexarFonte($componentes, is_array($fonte['componentes'] ?? null) ? $fonte['componentes'] : []);
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        foreach ($componentes as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $slug = (string) ($comp['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $info = $porSlug[$slug] ?? null;
            if (!empty($comp['vaga'])) {
                $nomeVaga = is_array($info) ? trim((string) ($info['nome'] ?? '')) : '';
                $vars['comp_' . $slug] = $esc($nomeVaga !== '' ? $nomeVaga : (string) ($comp['nome'] ?? ''));
            }
            $notasPeriodo = [];
            foreach ($periodos as $periodo) {
                if (!is_array($periodo)) {
                    continue;
                }
                $n = (int) ($periodo['n'] ?? 0);
                $bims = is_array($periodo['bimestres'] ?? null) ? $periodo['bimestres'] : [];
                $nota = self::mediaBimestres($info, $bims);
                $falta = self::faltaPeriodo($info, $bims, $n, count($periodos));
                if ($amostra) {
                    $nota = $nota ?? (7 + (($n + strlen($slug)) % 3));
                    $falta = $falta ?? (($n + strlen($slug)) % 5);
                }
                if ($nota !== null) {
                    $notasPeriodo[] = $nota;
                }
                $vars['nota_' . $slug . '_' . $n] = $nota === null ? '' : $esc(self::fmt($nota));
                $vars['falta_' . $slug . '_' . $n] = $falta === null ? '' : $esc((string) (int) $falta);
            }
            $media = $notasPeriodo !== [] ? array_sum($notasPeriodo) / count($notasPeriodo) : null;
            $final = is_array($info) && is_numeric($info['media_final'] ?? null) ? (float) $info['media_final'] : $media;
            if ($amostra && $media === null) {
                $media = 7.5;
                $final = 7.5;
            }
            $vars['media_anual_' . $slug] = $media === null ? '' : $esc(self::fmt($media));
            $vars['media_final_' . $slug] = $final === null ? '' : $esc(self::fmt($final));
        }
        return $vars;
    }

    /**
     * PDF de teste: preenche tokens da grade que ainda estiverem vazios.
     *
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    public static function amostraDosTokens(string $html, array $vars): array
    {
        if (empty($vars['_pdf_teste']) || isset($vars['_grade_fonte']) || !preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $html, $achados)) {
            return $vars;
        }
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $vagas = [
            'ifa_1' => 'Projeto de Vida',
            'ifa_2' => 'Mídias e Cultura Digital',
            'ifa_3' => 'Ciências da Natureza',
            'diversificada_1' => 'Educação Financeira',
        ];
        $alunos = ['Maria Eduarda Silva', 'João Pedro Almeida', 'Ana Luiza Costa', 'Pedro Henrique Dias', 'Laura Beatriz Mendes'];
        foreach (array_unique($achados[1]) as $bruto) {
            $key = strtolower((string) $bruto);
            if (trim(strip_tags((string) ($vars[$key] ?? ''))) !== '') {
                continue;
            }
            if (preg_match('/^(nota|falta|media_anual|media_final)_(.+?)(?:_(\d+))?$/', $key, $m)) {
                $tipo = $m[1];
                $slug = $m[2];
                $n = (int) ($m[3] ?? 1);
                $base = 6 + ((strlen($slug) + $n) % 5);
                $vars[$key] = $tipo === 'falta'
                    ? $esc((string) (($n + strlen($slug)) % 4))
                    : $esc(number_format((float) $base, 1, ',', '.'));
                continue;
            }
            if (preg_match('/^comp_(.+)$/', $key, $m)) {
                $vars[$key] = $esc($vagas[$m[1]] ?? 'Componente');
                continue;
            }
            if (preg_match('/^aluno_(\d+)_(nome|sexo|resultado)$/', $key, $m)) {
                $i = (int) $m[1];
                $vars[$key] = match ($m[2]) {
                    'nome' => $i <= count($alunos) ? $esc($alunos[$i - 1]) : '',
                    'sexo' => $esc($i % 2 === 0 ? 'M' : 'F'),
                    default => $esc('Aprovado'),
                };
                continue;
            }
            if (preg_match('/^aluno_(\d+)_(.+)$/', $key, $m) && (int) $m[1] <= 5) {
                $vars[$key] = $esc(number_format(6 + (((int) $m[1] + strlen($m[2])) % 5), 1, ',', '.'));
            }
        }
        return $vars;
    }

    /**
     * @param list<array<string,mixed>> $componentes
     * @param array<string,mixed> $vars
     * @param array<string,mixed>|null $fonte
     * @return array<string,mixed>
     */
    private static function completarRelatorio(array $componentes, array $vars, ?array $fonte, bool $amostra): array
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $alunos = is_array($fonte['alunos'] ?? null) ? array_values($fonte['alunos']) : [];
        $nomes = ['Maria Eduarda Silva', 'João Pedro Almeida', 'Ana Luiza Costa', 'Pedro Henrique Dias'];
        for ($i = 1; $i <= 20; $i++) {
            $aluno = is_array($alunos[$i - 1] ?? null) ? $alunos[$i - 1] : null;
            $nome = is_array($aluno) ? trim((string) ($aluno['nome'] ?? '')) : '';
            if ($nome === '' && $amostra && $i <= count($nomes)) {
                $nome = $nomes[$i - 1];
            }
            $vars['aluno_' . $i . '_nome'] = $nome === '' ? '' : $esc($nome);
            $sexo = is_array($aluno) ? trim((string) ($aluno['sexo'] ?? '')) : ($amostra && $nome !== '' ? ($i % 2 === 0 ? 'M' : 'F') : '');
            $vars['aluno_' . $i . '_sexo'] = $sexo === '' ? '' : $esc($sexo);
            $resultado = is_array($aluno) ? trim((string) ($aluno['resultado'] ?? '')) : ($amostra && $nome !== '' ? 'Aprovado' : '');
            $vars['aluno_' . $i . '_resultado'] = $resultado === '' ? '' : $esc($resultado);
            foreach ($componentes as $comp) {
                if (!is_array($comp)) {
                    continue;
                }
                $slug = (string) ($comp['slug'] ?? '');
                $valor = '';
                if (is_array($aluno) && isset($aluno['notas'][$slug]) && $aluno['notas'][$slug] !== '') {
                    $valor = self::fmt($aluno['notas'][$slug]);
                } elseif ($amostra && $nome !== '') {
                    $valor = self::fmt(6 + (($i + strlen($slug)) % 4));
                }
                $vars['aluno_' . $i . '_' . $slug] = $valor === '' ? '' : $esc($valor);
            }
        }
        return $vars;
    }

    /**
     * @param list<array<string,mixed>> $componentes
     * @param list<array<string,mixed>> $linhas
     * @return array<string,array<string,mixed>>
     */
    private static function indexarFonte(array $componentes, array $linhas): array
    {
        $porAlias = [];
        foreach ($componentes as $comp) {
            if (!is_array($comp) || !empty($comp['vaga'])) {
                continue;
            }
            $slug = (string) ($comp['slug'] ?? '');
            foreach ($comp['aliases'] ?? [] as $alias) {
                $porAlias[self::normalizar((string) $alias)] = $slug;
            }
            $porAlias[self::normalizar((string) ($comp['nome'] ?? ''))] = $slug;
        }
        $vagas = [];
        foreach ($componentes as $comp) {
            if (is_array($comp) && !empty($comp['vaga'])) {
                $vagas[] = (string) ($comp['slug'] ?? '');
            }
        }
        $out = [];
        $usadas = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $nome = self::normalizar((string) ($linha['nome'] ?? $linha['materia_nome'] ?? ''));
            $slug = $porAlias[$nome] ?? '';
            if ($slug === '' || isset($usadas[$slug])) {
                foreach ($vagas as $vaga) {
                    if (!isset($usadas[$vaga])) {
                        $slug = $vaga;
                        break;
                    }
                }
            }
            if ($slug === '') {
                continue;
            }
            $usadas[$slug] = true;
            $out[$slug] = $linha;
        }
        return $out;
    }

    /**
     * @param array<string,mixed>|null $info
     * @param list<int|string> $bimestres
     */
    private static function mediaBimestres(?array $info, array $bimestres): ?float
    {
        if ($info === null) {
            return null;
        }
        $vals = [];
        foreach ($bimestres as $b) {
            $k = 'b' . (int) $b;
            if (is_numeric($info[$k] ?? null)) {
                $vals[] = (float) $info[$k];
            }
        }
        if ($vals === []) {
            return null;
        }
        return array_sum($vals) / count($vals);
    }

    /**
     * Falta por período não vem separada no boletim. O total anual entra no último período.
     *
     * @param array<string,mixed>|null $info
     * @param list<int|string> $bimestres
     */
    private static function faltaPeriodo(?array $info, array $bimestres, int $n, int $totalPeriodos): ?int
    {
        if ($info === null || $n !== $totalPeriodos) {
            return null;
        }
        if (!is_numeric($info['faltas'] ?? null)) {
            return null;
        }
        return (int) $info['faltas'];
    }

    private static function fmt(mixed $valor): string
    {
        if (!is_numeric($valor)) {
            return trim((string) $valor);
        }
        return number_format((float) $valor, 1, ',', '.');
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c']);
        $texto = preg_replace('/[^a-z0-9 ]+/', ' ', $texto) ?? $texto;
        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }
}
