<?php

namespace App\Modulos\ModelosDocumentos\Services;

/**
 * Ata de resultados finais em tabela única por turma.
 * Lê notas, frequência e situação já homologadas. Não recalcula média.
 */
class AtaCompletaTurmaService
{
    public const DISCIPLINAS_POR_FOLHA = 8;

    public const ALUNOS_POR_FOLHA = 14;

    public const ALUNOS_FOLHA_RESUMO = 10;

    public const ALUNOS_FOLHA_ASSINATURA = 8;

    /** Uma folha só, com resumo e assinaturas, cabe neste tanto de alunos. */
    public const ALUNOS_FOLHA_UNICA = 6;

    /**
     * @param array<string,mixed> $contexto
     */
    public function html(array $contexto): string
    {
        $linhas = $this->linhasUnicas(is_array($contexto['linhas'] ?? null) ? $contexto['linhas'] : []);
        $componentes = self::componentesDasLinhas($linhas);
        $siglas = $this->siglasDosComponentes(
            $componentes,
            is_array($contexto['siglas_cadastro'] ?? null) ? $contexto['siglas_cadastro'] : [],
            is_array($contexto['glossario'] ?? null) ? $contexto['glossario'] : []
        );
        $grupos = $this->gruposDeColunas($componentes, $siglas);
        if (!empty($contexto['embutido'])) {
            return $this->htmlNoLayout($linhas, $grupos);
        }
        $janelas = self::janelas(count($linhas));
        $totalFolhas = max(1, count($janelas) * max(1, count($grupos)));
        $folha = 0;
        $partes = [$this->css()];

        if ($linhas === []) {
            $partes[] = $this->folhaVazia($contexto, 1, 1);

            return implode('', $partes);
        }

        $ultimoGrupo = count($grupos) - 1;
        $ultimaJanela = count($janelas) - 1;
        foreach ($janelas as $ji => $janela) {
            $alunos = array_slice($linhas, $janela[0], $janela[1]);
            foreach ($grupos as $gi => $grupo) {
                $folha++;
                $primeira = $ji === 0 && $gi === 0;
                $ultima = $ji === $ultimaJanela && $gi === $ultimoGrupo;
                $partes[] = $this->folha(
                    $contexto,
                    $alunos,
                    $grupo,
                    $janela[0],
                    $folha,
                    $totalFolhas,
                    $primeira,
                    $ultima,
                    $gi === $ultimoGrupo
                );
            }
        }

        return implode('', $partes);
    }

    /**
     * Só a grade. Cabeçalho, título, rodapé e assinaturas ficam no layout.
     *
     * @param list<array<string,mixed>> $linhas
     * @param list<list<array{chave:string,nome:string,sigla:string}>> $grupos
     */
    private function htmlNoLayout(array $linhas, array $grupos): string
    {
        if ($linhas === [] || $grupos === []) {
            return '<p class="aviso">Nenhuma matrícula válida nesta turma.</p>';
        }
        $partes = [];
        $ultimo = count($grupos) - 1;
        foreach ($grupos as $gi => $grupo) {
            $quebra = $gi > 0 ? ' style="page-break-before:always;"' : '';
            $partes[] = '<div' . $quebra . '>'
                . $this->tabela($linhas, $grupo, 0, $gi === $ultimo)
                . $this->legenda($grupo)
                . '</div>';
        }

        return implode('', $partes);
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return array{total:int,aprovados:int,retidos:int,transferidos:int,recuperacao:int,progressao:int,cancelados:int,outros:int,texto:string}
     */
    public function totais(array $linhas): array
    {
        $contagem = [
            'aprovados' => 0,
            'retidos' => 0,
            'transferidos' => 0,
            'recuperacao' => 0,
            'progressao' => 0,
            'cancelados' => 0,
            'outros' => 0,
        ];
        foreach ($this->linhasUnicas($linhas) as $linha) {
            $contagem[$this->grupoDaLinha($linha)]++;
        }
        $total = array_sum($contagem);
        $rotulos = [
            'aprovados' => 'Aprovados',
            'retidos' => 'Retidos',
            'transferidos' => 'Transferidos',
            'recuperacao' => 'Em recuperação',
            'progressao' => 'Progressão parcial',
            'cancelados' => 'Cancelados ou desistentes',
            'outros' => 'Outras situações',
        ];
        $partes = ['Total de alunos: ' . $total];
        foreach ($rotulos as $chave => $rotulo) {
            if ($contagem[$chave] > 0 || in_array($chave, ['aprovados', 'retidos', 'transferidos'], true)) {
                $partes[] = $rotulo . ': ' . $contagem[$chave];
            }
        }

        return [
            'total' => $total,
            'aprovados' => $contagem['aprovados'],
            'retidos' => $contagem['retidos'],
            'transferidos' => $contagem['transferidos'],
            'recuperacao' => $contagem['recuperacao'],
            'progressao' => $contagem['progressao'],
            'cancelados' => $contagem['cancelados'],
            'outros' => $contagem['outros'],
            'texto' => implode(' | ', $partes),
        ];
    }

    /**
     * Fatias [início, quantidade] que cabem em cada folha, na ordem dos alunos.
     *
     * @return list<array{0:int,1:int}>
     */
    public static function janelas(int $total): array
    {
        if ($total <= 0) {
            return [];
        }
        if ($total <= self::ALUNOS_FOLHA_UNICA) {
            return [[0, $total]];
        }

        $ultima = min(self::ALUNOS_FOLHA_ASSINATURA, $total - 1);
        $prefixo = $total - $ultima;
        $primeira = min(self::ALUNOS_FOLHA_RESUMO, $prefixo);
        if ($prefixo === $primeira && $primeira < 4 && $ultima > 4) {
            $mover = min(4 - $primeira, $ultima - 1);
            $primeira += $mover;
            $ultima -= $mover;
            $prefixo = $total - $ultima;
        }

        $janelas = [[0, $primeira]];
        $inicio = $primeira;
        $limite = $total - $ultima;
        while ($inicio < $limite) {
            $qtd = min(self::ALUNOS_POR_FOLHA, $limite - $inicio);
            $janelas[] = [$inicio, $qtd];
            $inicio += $qtd;
        }
        $janelas[] = [$inicio, $total - $inicio];

        return $janelas;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array{chave:string,materia_id:int,nome:string,ordem:int}>
     */
    public static function componentesDasLinhas(array $linhas): array
    {
        $mapa = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $comps = is_array($linha['componentes'] ?? null) ? $linha['componentes'] : [];
            foreach ($comps as $comp) {
                if (!is_array($comp)) {
                    continue;
                }
                $nome = trim((string) ($comp['materia_nome'] ?? $comp['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $id = (int) ($comp['materia_id'] ?? 0);
                $chave = $id > 0 ? 'id:' . $id : 'nome:' . mb_strtolower($nome);
                $ordem = (int) ($comp['ordem'] ?? 0);
                if (!isset($mapa[$chave]) || ($ordem > 0 && ($mapa[$chave]['ordem'] === 0 || $ordem < $mapa[$chave]['ordem']))) {
                    $mapa[$chave] = [
                        'chave' => $chave,
                        'materia_id' => $id,
                        'nome' => $nome,
                        'ordem' => $ordem,
                    ];
                }
            }
        }
        $lista = array_values($mapa);
        usort($lista, static function (array $a, array $b): int {
            $oa = $a['ordem'] > 0 ? $a['ordem'] : 9999;
            $ob = $b['ordem'] > 0 ? $b['ordem'] : 9999;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strnatcasecmp($a['nome'], $b['nome']);
        });

        return $lista;
    }

    /**
     * @param array<string,mixed> $comp
     */
    public static function textoNota(array $comp): string
    {
        foreach (['conceito', 'nota_texto', 'media_texto'] as $campo) {
            $texto = trim((string) ($comp[$campo] ?? ''));
            if ($texto !== '') {
                return $texto;
            }
        }
        $media = $comp['media_final'] ?? $comp['media'] ?? null;
        if (is_numeric($media)) {
            return number_format((float) $media, 1, ',', '.');
        }
        $situacao = (string) ($comp['situacao'] ?? '');

        return match ($situacao) {
            'dispensado' => 'Disp.',
            'aproveitamento' => 'Aprov.',
            default => '—',
        };
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    private function linhasUnicas(array $linhas): array
    {
        $vistas = [];
        $unicas = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $aluno = is_array($linha['aluno'] ?? null) ? $linha['aluno'] : [];
            $id = (int) ($aluno['id'] ?? 0);
            $nome = trim((string) ($aluno['nome'] ?? ''));
            $chave = $id > 0 ? 'id:' . $id : 'nome:' . mb_strtolower($nome);
            if ($chave === 'nome:' || isset($vistas[$chave])) {
                continue;
            }
            $vistas[$chave] = true;
            $unicas[] = $linha;
        }
        if (class_exists(\Collator::class)) {
            $collator = new \Collator('pt_BR');
            usort($unicas, static function (array $a, array $b) use ($collator): int {
                $na = (string) ($a['aluno']['nome'] ?? '');
                $nb = (string) ($b['aluno']['nome'] ?? '');

                $cmp = $collator->compare($na, $nb);

                return $cmp === false ? 0 : $cmp;
            });
        } else {
            usort($unicas, static function (array $a, array $b): int {
                return strnatcasecmp((string) ($a['aluno']['nome'] ?? ''), (string) ($b['aluno']['nome'] ?? ''));
            });
        }

        return $unicas;
    }

    /**
     * @param array<string,mixed> $linha
     */
    private function grupoDaLinha(array $linha): string
    {
        $aluno = is_array($linha['aluno'] ?? null) ? $linha['aluno'] : [];
        $situacao = (string) ($linha['situacao'] ?? '');
        if (!empty($aluno['transferido']) || in_array($situacao, ['transferido', 'transferencia'], true)) {
            return 'transferidos';
        }
        $mapa = [
            'aprovados' => ['aprovado', 'aprovado_recuperacao', 'aprovado_conselho', 'aproveitamento'],
            'retidos' => ['reprovado_rendimento', 'reprovado_frequencia'],
            'recuperacao' => ['recuperacao', 'exame_final'],
            'progressao' => ['progressao_parcial', 'dependencia'],
            'cancelados' => ['desistente'],
        ];
        foreach ($mapa as $grupo => $codigos) {
            if (in_array($situacao, $codigos, true)) {
                return $grupo;
            }
        }

        return 'outros';
    }

    /**
     * @param list<array{chave:string,materia_id:int,nome:string,ordem:int}> $componentes
     * @param array<int|string,string> $cadastro
     * @param list<array<string,mixed>> $glossario
     * @return array<string,string> chave do componente => sigla
     */
    private function siglasDosComponentes(array $componentes, array $cadastro, array $glossario): array
    {
        $usadas = [];
        $out = [];
        foreach ($componentes as $comp) {
            $sigla = $this->siglaDe($comp, $cadastro, $glossario, $usadas);
            $usadas[$sigla] = true;
            $out[$comp['chave']] = $sigla;
        }

        return $out;
    }

    /**
     * @param array{chave:string,materia_id:int,nome:string,ordem:int} $comp
     * @param array<int|string,string> $cadastro
     * @param list<array<string,mixed>> $glossario
     * @param array<string,bool> $usadas
     */
    private function siglaDe(array $comp, array $cadastro, array $glossario, array $usadas): string
    {
        $id = (int) $comp['materia_id'];
        $candidata = '';
        if ($id > 0 && isset($cadastro[$id]) && trim((string) $cadastro[$id]) !== '') {
            $candidata = (string) $cadastro[$id];
        }
        $nomeChave = mb_strtolower($comp['nome']);
        if ($candidata === '' && isset($cadastro[$nomeChave]) && trim((string) $cadastro[$nomeChave]) !== '') {
            $candidata = (string) $cadastro[$nomeChave];
        }
        if ($candidata === '') {
            $slug = $this->slug($comp['nome']);
            foreach ($glossario as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $siglaItem = trim((string) ($item['sigla'] ?? ''));
                if ($siglaItem === '') {
                    continue;
                }
                $slugItem = strtolower(trim((string) ($item['slug'] ?? '')));
                $nomeItem = mb_strtolower(trim((string) ($item['nome'] ?? '')));
                if (($slugItem !== '' && $slugItem === $slug) || ($nomeItem !== '' && $nomeItem === $nomeChave)) {
                    $candidata = $siglaItem;
                    break;
                }
            }
        }
        if ($candidata === '') {
            $candidata = $this->siglaDoNome($comp['nome']);
        }
        $candidata = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $candidata));
        $candidata = substr($candidata, 0, 12);
        if ($candidata === '') {
            $candidata = 'DISC';
        }
        $base = $candidata;
        $n = 2;
        while (isset($usadas[$candidata])) {
            $candidata = substr($base, 0, 10) . $n;
            $n++;
        }

        return $candidata;
    }

    private function siglaDoNome(string $nome): string
    {
        $limpo = $this->semAcento(mb_strtolower($nome));
        $palavras = preg_split('/[^a-z0-9]+/i', $limpo) ?: [];
        $ignorar = ['de', 'da', 'do', 'das', 'dos', 'e', 'a', 'o', 'em', 'para'];
        $letras = '';
        $uteis = [];
        foreach ($palavras as $palavra) {
            if ($palavra === '' || in_array($palavra, $ignorar, true)) {
                continue;
            }
            $uteis[] = $palavra;
            $letras .= substr($palavra, 0, 1);
            if (strlen($letras) >= 4) {
                break;
            }
        }
        if (count($uteis) >= 2 && $letras !== '') {
            return strtoupper(substr($letras, 0, 4));
        }
        $uma = $uteis[0] ?? '';

        return strtoupper(substr($uma !== '' ? $uma : 'disc', 0, 4));
    }

    private function slug(string $nome): string
    {
        $limpo = $this->semAcento(mb_strtolower($nome));
        $limpo = preg_replace('/[^a-z0-9]+/', '_', $limpo) ?? '';

        return trim($limpo, '_');
    }

    private function semAcento(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);
    }

    /**
     * @param list<array{chave:string,materia_id:int,nome:string,ordem:int}> $componentes
     * @param array<string,string> $siglas
     * @return list<list<array{chave:string,materia_id:int,nome:string,ordem:int,sigla:string}>>
     */
    private function gruposDeColunas(array $componentes, array $siglas): array
    {
        $comSigla = [];
        foreach ($componentes as $comp) {
            $comp['sigla'] = $siglas[$comp['chave']] ?? 'DISC';
            $comSigla[] = $comp;
        }
        if ($comSigla === []) {
            return [[]];
        }

        return array_chunk($comSigla, self::DISCIPLINAS_POR_FOLHA);
    }

    /**
     * @param array<string,mixed> $contexto
     * @param list<array<string,mixed>> $alunos
     * @param list<array{chave:string,nome:string,sigla:string}> $grupo
     */
    private function folha(
        array $contexto,
        array $alunos,
        array $grupo,
        int $offset,
        int $numero,
        int $total,
        bool $primeira,
        bool $ultima,
        bool $comResultado
    ): string {
        $classe = $ultima ? 'folha ultima' : 'folha';
        $html = '<section class="' . $classe . '">';
        $html .= $this->cabecalho($contexto, $numero, $total);
        if ($primeira) {
            $html .= $this->resumo($contexto);
            $html .= $this->pendencias($contexto);
        }
        $html .= $this->tabela($alunos, $grupo, $offset, $comResultado);
        $html .= $this->legenda($grupo);
        if ($ultima && empty($contexto['embutido'])) {
            $html .= $this->assinaturas($contexto);
        }
        $html .= '</section>';

        return $html;
    }

    /**
     * @param array<string,mixed> $contexto
     */
    private function folhaVazia(array $contexto, int $numero, int $total): string
    {
        $assinaturas = empty($contexto['embutido']) ? $this->assinaturas($contexto) : '';

        return '<section class="folha ultima">'
            . $this->cabecalho($contexto, $numero, $total)
            . '<p class="aviso">Nenhuma matrícula válida nesta turma.</p>'
            . $assinaturas
            . '</section>';
    }

    /**
     * @param array<string,mixed> $contexto
     */
    private function cabecalho(array $contexto, int $numero, int $total): string
    {
        $ano = (string) ($contexto['ano_letivo'] ?? '');
        if (!empty($contexto['embutido'])) {
            $linha = array_filter([
                $this->par('Escola', (string) ($contexto['escola_nome'] ?? '')),
                $this->par('Turma', (string) ($contexto['turma_nome'] ?? '')),
                $this->par('Ano', $ano),
            ]);

            return '<p class="meta">' . implode(' · ', $linha) . ' · Página ' . $numero . ' de ' . $total . '</p>';
        }
        $meta = array_filter([
            $this->par('Etapa', (string) ($contexto['etapa'] ?? '')),
            $this->par('Série', (string) ($contexto['serie'] ?? '')),
            $this->par('Turma', (string) ($contexto['turma_nome'] ?? '')),
            $this->par('Turno', (string) ($contexto['turno'] ?? '')),
            $this->par('Organização', (string) ($contexto['organizacao'] ?? '')),
            $this->par('Encerramento', (string) ($contexto['encerramento'] ?? '')),
        ]);

        return '<header class="topo">'
            . '<table class="timbre"><tr>'
            . '<td class="logo"><!--LOGO--></td>'
            . '<td>'
            . '<div class="escola">' . $this->e($contexto['escola_nome'] ?? 'Escola') . '</div>'
            . $this->linhaSub((string) ($contexto['escola_endereco'] ?? ''))
            . $this->linhaSub((string) ($contexto['escola_docs'] ?? ''))
            . '</td></tr></table>'
            . '<h1>ATA DE RESULTADOS FINAIS — ANO LETIVO ' . $this->e($ano !== '' ? $ano : date('Y')) . '</h1>'
            . '<p class="meta">' . implode(' · ', $meta) . '</p>'
            . '<p class="pagina">Página ' . $numero . ' de ' . $total . '</p>'
            . '</header>';
    }

    private function par(string $rotulo, string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '—') {
            return '';
        }

        return $this->e($rotulo) . ': ' . $this->e($valor);
    }

    private function linhaSub(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        return '<div class="sub">' . $this->e($texto) . '</div>';
    }

    /**
     * @param array<string,mixed> $contexto
     */
    private function resumo(array $contexto): string
    {
        $linhas = is_array($contexto['linhas'] ?? null) ? $contexto['linhas'] : [];
        $totais = $this->totais($linhas);
        $itens = [
            'Total de alunos' => $totais['total'],
            'Aprovados' => $totais['aprovados'],
            'Retidos' => $totais['retidos'],
            'Transferidos' => $totais['transferidos'],
        ];
        if ($totais['recuperacao'] > 0) {
            $itens['Em recuperação'] = $totais['recuperacao'];
        }
        if ($totais['progressao'] > 0) {
            $itens['Progressão parcial'] = $totais['progressao'];
        }
        if ($totais['cancelados'] > 0) {
            $itens['Cancelados ou desistentes'] = $totais['cancelados'];
        }
        if ($totais['outros'] > 0) {
            $itens['Outras situações'] = $totais['outros'];
        }
        $html = '<table class="resumo"><tr><th>Indicador</th><th>Quantidade</th></tr>';
        foreach ($itens as $rotulo => $qtd) {
            $html .= '<tr><td>' . $this->e($rotulo) . '</td><td class="qtd">' . (int) $qtd . '</td></tr>';
        }

        return $html . '</table>';
    }

    /**
     * @param array<string,mixed> $contexto
     */
    private function pendencias(array $contexto): string
    {
        $lista = [];
        $linhas = is_array($contexto['linhas'] ?? null) ? $contexto['linhas'] : [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $itens = is_array($linha['pendencias'] ?? null) ? $linha['pendencias'] : [];
            $textos = [];
            foreach ($itens as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $textos[] = $item;
                }
            }
            if ($textos === []) {
                continue;
            }
            $nome = trim((string) ($linha['aluno']['nome'] ?? 'Aluno'));
            $lista[] = ($nome !== '' ? $nome : 'Aluno') . ': ' . implode('; ', $textos);
        }
        if ($lista === []) {
            return '';
        }
        $visiveis = array_slice($lista, 0, 12);
        $html = '<div class="pendencias"><strong>Pendências antes da escrituração definitiva</strong><ul>';
        foreach ($visiveis as $item) {
            $html .= '<li>' . $this->e($item) . '</li>';
        }
        $resto = count($lista) - count($visiveis);
        if ($resto > 0) {
            $html .= '<li>e mais ' . $resto . ' aluno(s).</li>';
        }

        return $html . '</ul></div>';
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @param list<array{chave:string,nome:string,sigla:string}> $grupo
     */
    private function tabela(array $alunos, array $grupo, int $offset, bool $comResultado): string
    {
        $colspanAviso = 3 + count($grupo) + ($comResultado ? 2 : 0);
        $html = '<table class="ata">' . $this->colgroup(count($grupo), $comResultado)
            . '<thead><tr>'
            . '<th class="num">Nº</th><th class="ra">RA</th><th class="nome">Nome do aluno</th>';
        foreach ($grupo as $comp) {
            $html .= '<th class="disc">' . $this->e($comp['sigla']) . '</th>';
        }
        if ($comResultado) {
            $html .= '<th class="freq">Freq.</th><th class="resultado">Resultado</th>';
        }
        $html .= '</tr></thead><tbody>';
        if ($grupo === [] && $alunos !== []) {
            $html .= '<tr><td colspan="' . $colspanAviso . '">Nenhum componente curricular consolidado para esta turma.</td></tr>';
        }
        $i = $offset;
        foreach ($alunos as $linha) {
            $i++;
            $aluno = is_array($linha['aluno'] ?? null) ? $linha['aluno'] : [];
            $ra = trim((string) ($aluno['ra'] ?? $aluno['codigo_aluno'] ?? ''));
            $notas = $this->notasPorChave($linha);
            $html .= '<tr>'
                . '<td class="num">' . $i . '</td>'
                . '<td class="ra">' . $this->e($ra !== '' ? $ra : '—') . '</td>'
                . '<td class="nome">' . $this->e($aluno['nome'] ?? '') . '</td>';
            foreach ($grupo as $comp) {
                $celula = $notas[$comp['chave']] ?? null;
                $html .= '<td class="nota">' . $this->e(is_array($celula) ? self::textoNota($celula) : '—') . '</td>';
            }
            if ($comResultado) {
                $freq = $linha['frequencia']['percentual'] ?? null;
                $freqTxt = is_numeric($freq) ? number_format((float) $freq, 1, ',', '.') . '%' : '—';
                $html .= '<td class="freq">' . $this->e($freqTxt) . '</td>'
                    . '<td class="resultado">' . $this->e((string) ($linha['rotulo'] ?? '—')) . '</td>';
            }
            $html .= '</tr>';
        }
        if ($alunos === []) {
            $html .= '<tr><td colspan="' . max(3, $colspanAviso) . '">Nenhum aluno neste recorte.</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * Larguras iniciais. O editor substitui este colgroup quando o usuário arrasta as colunas.
     */
    private function colgroup(int $disciplinas, bool $comResultado): string
    {
        $cauda = $comResultado ? 20.0 : 0.0;
        $resto = max(1.0, 100.0 - 38.0 - $cauda);
        $cada = $disciplinas > 0 ? $resto / $disciplinas : 0.0;
        $pesos = [5.0, 11.0, 22.0];
        for ($i = 0; $i < $disciplinas; $i++) {
            $pesos[] = $cada;
        }
        if ($comResultado) {
            $pesos[] = 8.0;
            $pesos[] = 12.0;
        }
        $soma = array_sum($pesos) ?: 1.0;
        $html = '<colgroup>';
        foreach ($pesos as $peso) {
            $html .= '<col style="width:' . number_format($peso / $soma * 100, 2, '.', '') . '%">';
        }

        return $html . '</colgroup>';
    }

    /**
     * @param array<string,mixed> $linha
     * @return array<string,array<string,mixed>>
     */
    private function notasPorChave(array $linha): array
    {
        $out = [];
        $comps = is_array($linha['componentes'] ?? null) ? $linha['componentes'] : [];
        foreach ($comps as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $nome = trim((string) ($comp['materia_nome'] ?? $comp['nome'] ?? ''));
            $id = (int) ($comp['materia_id'] ?? 0);
            $chave = $id > 0 ? 'id:' . $id : 'nome:' . mb_strtolower($nome);
            if ($chave === 'nome:') {
                continue;
            }
            $out[$chave] = $comp;
        }

        return $out;
    }

    /**
     * @param list<array{nome:string,sigla:string}> $grupo
     */
    private function legenda(array $grupo): string
    {
        if ($grupo === []) {
            return '';
        }
        $itens = [];
        foreach ($grupo as $comp) {
            $itens[] = $this->e($comp['sigla']) . ' — ' . $this->e($comp['nome']);
        }

        return '<p class="legenda">' . implode(' · ', $itens) . '</p>';
    }

    /**
     * @param array<string,mixed> $contexto
     */
    private function assinaturas(array $contexto): string
    {
        $local = trim((string) ($contexto['cidade_data'] ?? ''));
        $diretor = trim((string) ($contexto['diretor_nome'] ?? ''));
        $secretario = trim((string) ($contexto['secretario_nome'] ?? ''));
        if ($diretor === '') {
            $diretor = 'Direção';
        }
        if ($secretario === '') {
            $secretario = 'Secretaria';
        }

        return '<div class="fecho">'
            . ($local !== '' ? '<p class="local">' . $this->e($local) . '</p>' : '')
            . '<table class="assinaturas"><tr>'
            . '<td><span>' . $this->e($secretario) . '<br>Secretaria escolar</span></td>'
            . '<td><span>' . $this->e($diretor) . '<br>Direção</span></td>'
            . '</tr></table></div>';
    }

    private function css(): string
    {
        return '<style>
@page { margin: 8mm; }
body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 9pt; }
.folha { page-break-after: always; }
.folha.ultima { page-break-after: auto; }
.timbre { width: 100%; border-collapse: collapse; margin: 0 0 4px; }
.timbre td { border: none; vertical-align: middle; padding: 0; }
.logo { width: 70px; }
.logo img { max-height: 42px; max-width: 64px; }
.escola { font-size: 11pt; font-weight: bold; text-align: center; }
.sub, .meta, .pagina, .legenda { font-size: 8pt; color: #333; text-align: center; margin: 0; }
h1 { font-size: 11pt; text-align: center; margin: 4px 0; }
.pagina { margin: 2px 0 6px; }
table.resumo { width: 220px; border-collapse: collapse; margin: 0 0 8px; font-size: 8pt; }
table.resumo th, table.resumo td { border: 1px solid #d1d5db; padding: 2px 6px; }
table.resumo th { background: #f3f4f6; text-align: left; }
table.resumo td.qtd { text-align: right; }
.pendencias { border: 1px solid #fcd34d; background: #fffbeb; color: #92400e; font-size: 8pt; padding: 4px 8px; margin: 0 0 8px; }
.pendencias ul { margin: 4px 0 0; padding-left: 16px; }
table.ata { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8pt; }
table.ata th, table.ata td { border: 1px solid #d1d5db; padding: 2px 3px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; }
table.ata th { background: #f3f4f6; font-size: 7.5pt; text-align: center; }
table.ata td.nota, table.ata td.freq, table.ata td.num, table.ata td.ra, table.ata td.resultado { text-align: center; }
table.ata td.nome, table.ata th.nome { text-align: left; }
table.ata th.num, table.ata td.num { width: 4%; }
table.ata th.ra, table.ata td.ra { width: 11%; }
table.ata th.freq, table.ata td.freq { width: 8%; }
table.ata th.resultado, table.ata td.resultado { width: 14%; }
.legenda { text-align: left; margin: 4px 0 0; }
.aviso { font-size: 9pt; margin: 8px 0; }
.local { text-align: center; margin: 10px 0 0; font-size: 9pt; }
table.assinaturas { width: 100%; border-collapse: collapse; margin-top: 8px; }
table.assinaturas td { border: none; text-align: center; padding-top: 22px; width: 50%; }
table.assinaturas span { display: block; border-top: 1px solid #333; margin: 0 24px; padding-top: 4px; font-size: 8pt; }
</style>';
    }

    private function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
