<?php

namespace App\Modulos\ModelosDocumentos\Services;

require_once __DIR__ . '/ModeloDocumentoService.php';

/**
 * Históricos escolares prontos para a escola escolher e editar no construtor.
 * A grade é HTML (células, mesclas e texto na vertical): o PDF usa o mesmo HTML.
 */
class LayoutsHistoricoEscolar
{
    /**
     * @return array<string,array{nome:string,codigo:string,descricao:string,resumo:string,paginas:int}>
     */
    public static function catalogo(): array
    {
        $item = static fn (string $nome, string $codigo, string $descricao, string $resumo, int $paginas): array => [
            'nome' => $nome,
            'codigo' => $codigo,
            'descricao' => $descricao,
            'resumo' => $resumo,
            'paginas' => $paginas,
        ];

        return [
            'historico_parana' => $item(
                'Histórico escolar — Paraná',
                'resultado_historico_parana',
                'Modelo editável no formato do histórico do Paraná (uma página).',
                'Uma página, com faixas na vertical, base nacional, parte diversificada e trajetória.',
                1
            ),
            'historico_espirito_santo' => $item(
                'Histórico escolar — Espírito Santo',
                'resultado_historico_espirito_santo',
                'Modelo editável no formato do histórico do Espírito Santo, com guia de transferência.',
                'Frente com o histórico e verso com a guia de transferência.',
                2
            ),
            'historico_em_branco' => $item(
                'Histórico escolar em branco',
                'resultado_historico_livre',
                'Grade vazia para montar um histórico do zero.',
                'Folha em branco. Mescle células, gire o texto e pinte a faixa até ficar igual ao modelo da escola.',
                1
            ),
        ];
    }

    /**
     * @return array{nome:string,codigo:string,descricao:string,estrutura:array<string,mixed>}|null
     */
    public static function paraChave(string $chave): ?array
    {
        $meta = self::catalogo()[$chave] ?? null;
        if ($meta === null) {
            return null;
        }
        $html = match ($chave) {
            'historico_parana' => self::htmlParana(),
            'historico_espirito_santo' => self::htmlEspiritoSantoFrente(),
            'historico_em_branco' => self::htmlEmBranco(),
            default => '',
        };
        if ($html === '') {
            return null;
        }

        $est = ModeloDocumentoService::estruturaVazia('a4', 'retrato', 8);
        $est['header']['sections'] = [];
        $est['footer']['sections'] = [];
        $frente = self::secao($html);
        if ($chave === 'historico_espirito_santo') {
            $verso = self::secao(self::htmlEspiritoSantoVerso());
            $verso['pageBreakBefore'] = true;
            $est['body']['sections'] = [$frente, $verso];
        } else {
            $est['body']['sections'] = [$frente];
        }

        return [
            'nome' => $meta['nome'],
            'codigo' => $meta['codigo'],
            'descricao' => $meta['descricao'],
            'estrutura' => $est,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function secao(string $html): array
    {
        $sec = ModeloDocumentoService::secaoPadrao([100], 'body');
        $sec['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('html', ['html' => $html]);

        return $sec;
    }

    private static function htmlParana(): string
    {
        $c = self::cols([4.5, 4.5, 13, 9, 24, 15, 15, 15]);
        $n = static fn (string $t): string => self::td($t, 1, 1, 'edoc-centro');
        $vazio = self::tdHtml('&nbsp;', 1, 1, 'edoc-centro');
        $comp = static fn (string $t, int $rs = 1): string => self::td($t, 2, $rs);
        $linhas = [];

        $linhas[] = self::tr(
            self::tdHtml('{{logo_html}}', 3, 6, 'edoc-logo')
            . self::td('GOVERNO DO ESTADO DO PARANÁ', 5, 1, 'edoc-centro edoc-negrito'),
            '5.2mm'
        );
        foreach ([
            'SECRETARIA DE ESTADO DA EDUCAÇÃO',
            'DIRETORIA DE ENSINO',
        ] as $linha) {
            $linhas[] = self::tr(self::td($linha, 5, 1, 'edoc-centro edoc-negrito'), '4.8mm');
        }
        $linhas[] = self::tr(self::td('{{escola_nome}}', 5, 1, 'edoc-centro edoc-negrito'), '5.2mm');
        $linhas[] = self::tr(self::td('Código INEP: {{escola_docs}}', 5, 1, 'edoc-centro'), '4.8mm');
        $linhas[] = self::tr(self::td('{{escola_endereco}}', 5, 1, 'edoc-centro'), '5.2mm');
        $linhas[] = self::tr(self::td('HISTÓRICO ESCOLAR — {{etapa}}', 8, 1, 'edoc-titulo'), '7mm');
        $linhas[] = self::tr(
            self::td('Nome do Aluno: {{aluno_nome}}', 5)
            . self::td('CPF: {{aluno_cpf}}', 3),
            '6mm'
        );
        $linhas[] = self::tr(
            self::td('Nascimento:', 2)
            . self::td('Município: {{aluno_naturalidade}}', 3)
            . self::td('Estado:', 2)
            . self::td('País: {{aluno_nacionalidade}}', 1),
            '6mm'
        );
        $linhas[] = self::tr(self::td('Data: {{aluno_data_nasc}}', 8), '6mm');

        $linhas[] = self::tr(
            self::td('Fundamento Legal: Lei Federal 9394/96', 1, 27, 'edoc-vert')
            . self::td('BASE NACIONAL COMUM', 1, 13, 'edoc-vert')
            . self::td('ÁREAS DE CONHECIMENTO', 1, 2, 'edoc-centro edoc-negrito')
            . self::td('COMPONENTES CURRICULARES', 2, 2, 'edoc-centro edoc-negrito')
            . self::td('Ano', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('Ano', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('Ano', 1, 1, 'edoc-centro edoc-negrito'),
            '5.4mm'
        );
        $linhas[] = self::tr(
            self::td('1ª Série', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('2ª Série', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('3ª Série', 1, 1, 'edoc-centro edoc-negrito'),
            '5.4mm'
        );

        $grupos = [
            ['Linguagens', 3, ['Língua Portuguesa e Literatura', 'Arte', 'Educação Física']],
            ['Matemática', 1, ['Matemática']],
            ['Ciência da Natureza', 3, ['Biologia', 'Física', 'Química']],
            ['Ciências Humanas', 4, ['História', 'Geografia', 'Filosofia', 'Sociologia']],
        ];
        $notas = [
            'Língua Portuguesa e Literatura' => ['8,9', '9,5', '9,7'],
            'Arte' => ['9,0', '8,9', '9,1'],
            'Educação Física' => ['9,0', '9,2', '9,3'],
            'Matemática' => ['8,8', '9,1', '8,7'],
            'Biologia' => ['9,4', '9,2', '8,9'],
            'Física' => ['9,3', '9,6', '9,6'],
            'Química' => ['8,7', '8,8', '8,6'],
            'História' => ['9,0', '9,5', '9,5'],
            'Geografia' => ['9,2', '9,2', '9,6'],
            'Filosofia' => ['9,0', '9,2', '8,9'],
            'Sociologia' => ['9,0', '9,1', '9,0'],
        ];
        foreach ($grupos as [$area, $rs, $disciplinas]) {
            $primeira = true;
            foreach ($disciplinas as $disc) {
                $miolo = '';
                if ($primeira) {
                    $miolo .= self::td($area, 1, $rs, 'edoc-centro edoc-negrito');
                    $primeira = false;
                }
                $vals = $notas[$disc];
                $linhas[] = self::tr($miolo . $comp($disc) . $n($vals[0]) . $n($vals[1]) . $n($vals[2]), '5.6mm');
            }
        }

        $linhas[] = self::tr(self::td('Total de Aulas da Base Nacional Comum', 4, 1, 'edoc-negrito') . $vazio . $vazio . $vazio, '5.6mm');
        $linhas[] = self::tr(
            self::td('PARTE DIVERSIFICADA', 1, 5, 'edoc-vert')
            . self::td('Língua Estrangeira Moderna', 1, 2, 'edoc-centro')
            . $comp('Inglês') . $vazio . $vazio . $vazio,
            '5.6mm'
        );
        $linhas[] = self::tr($comp('Espanhol') . $vazio . $vazio . $vazio, '5.6mm');
        $linhas[] = self::tr(self::tdHtml('&nbsp;', 3) . $vazio . $vazio . $vazio, '5.6mm');
        $linhas[] = self::tr(self::tdHtml('&nbsp;', 3) . $vazio . $vazio . $vazio, '5.6mm');
        $linhas[] = self::tr(self::tdHtml('&nbsp;', 3) . $vazio . $vazio . $vazio, '5.6mm');
        $linhas[] = self::tr(self::td('Total de Aulas da Parte Diversificada', 4, 1, 'edoc-negrito') . $vazio . $vazio . $vazio, '5.6mm');
        $linhas[] = self::tr(self::td('Total de Aulas Anual do Curso (Aulas)', 4, 1, 'edoc-negrito') . $n('430') . $n('430') . $n('430'), '5.6mm');
        $linhas[] = self::tr(self::td('Total de Carga Horária Anual do Curso (Horas)', 4, 1, 'edoc-negrito') . $n('360') . $n('360') . $n('360'), '5.6mm');

        $linhas[] = self::tr(
            self::tdHtml('&nbsp;')
            . self::td('Série', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('Ano', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('Estabelecimento de Ensino', 2, 1, 'edoc-centro edoc-negrito')
            . self::td('Município', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('UF', 1, 1, 'edoc-centro edoc-negrito'),
            '5.6mm'
        );
        $linhas[] = self::tr(
            self::td('Ensino Fundamental', 1, 1, 'edoc-vert')
            . self::td('8ª Série / 9º Ano', 1, 1, 'edoc-centro')
            . self::td('2019', 1, 1, 'edoc-centro')
            . self::td('{{escola_nome}}', 2)
            . self::td('{{aluno_cidade}}', 1, 1, 'edoc-centro')
            . self::td('PR', 1, 1, 'edoc-centro'),
            '8mm'
        );
        $series = ['1ª Série' => '2020', '2ª Série' => '2021', '3ª Série' => '2022'];
        $i = 0;
        foreach ($series as $serie => $ano) {
            $miolo = '';
            if ($i === 0) {
                $miolo .= self::td('Ensino Médio', 1, 3, 'edoc-vert');
            }
            $linhas[] = self::tr(
                $miolo
                . self::td($serie, 1, 1, 'edoc-centro')
                . self::td($ano, 1, 1, 'edoc-centro')
                . self::td('{{escola_nome}}', 2)
                . self::td('{{aluno_cidade}}', 1, 1, 'edoc-centro')
                . self::td('PR', 1, 1, 'edoc-centro'),
                '7.2mm'
            );
            $i++;
        }
        $linhas[] = self::tr(
            self::td(
                'Escala de Avaliação: “A partir de 2007 - Escala numérica de notas de 0 (zero) a 10 (dez) com patamar indicativo de desempenho escolar satisfatório, a nota igual ou superior a 05 (cinco) nos termos da Resolução SE - 61, de 24/9/2007.”',
                8
            ),
            '9mm'
        );

        return self::tabela($c, implode('', $linhas));
    }

    private static function htmlEspiritoSantoFrente(): string
    {
        $cab = self::tabela(
            self::cols([100]),
            self::tr(self::tdHtml('{{logo_html}}', 1, 1, 'edoc-logo'), '16mm')
            . self::tr(self::td('GOVERNO DO ESTADO DO ESPÍRITO SANTO', 1, 1, 'edoc-centro edoc-negrito'))
            . self::tr(self::td('SECRETARIA DE ESTADO DA EDUCAÇÃO', 1, 1, 'edoc-centro edoc-negrito')),
            'edoc-sem-borda edoc-solto'
        );

        $escola = self::tabela(
            self::cols([70, 30]),
            self::tr(self::td('Unidade de Ensino: {{escola_nome}}', 2))
            . self::tr(self::td('Endereço: {{escola_endereco}}', 2))
            . self::tr(self::td('Ato de Criação:', 1) . self::td('Publicação:', 1))
            . self::tr(self::td('Ato de Aprovação:', 1) . self::td('Publicação:', 1)),
            'edoc-solto'
        );

        $aluno = self::tabela(
            self::cols([70, 30]),
            self::tr(self::td('Nome do Aluno (a): {{aluno_nome}}', 2, 1, 'edoc-negrito'))
            . self::tr(self::td('Local de Nascimento: {{aluno_naturalidade}}', 1) . self::td('Data: {{aluno_data_nasc}}', 1))
            . self::tr(self::td('Filiação: Pai: {{resp_nome}}', 2))
            . self::tr(self::td('Mãe: {{resp2_nome}}', 2))
            . self::tr(self::td('Concluiu no ano de {{ano_letivo}} a {{serie}} do Ensino Médio, nos termos da Lei nº 9394/1996.', 2)),
            'edoc-solto'
        );

        $c = self::cols([4.2, 5, 16, 29.8, 15, 15, 15]);
        $vazio = self::tdHtml('&nbsp;', 1, 1, 'edoc-centro');
        $nota = static fn (string $t): string => self::td($t, 1, 1, 'edoc-centro');
        $linhas = [];
        $linhas[] = self::tr(self::td('HISTÓRICO ESCOLAR', 7, 1, 'edoc-titulo'), '6mm');
        $linhas[] = self::tr(self::td('ENSINO MÉDIO', 7, 1, 'edoc-sub'), '5mm');
        $linhas[] = self::tr(
            self::td('Amparo Legal: Lei nº 9394/1996 de 20/12/96', 1, 18, 'edoc-vert')
            . self::td('Base Nacional Comum', 1, 14, 'edoc-vert')
            . self::td('Áreas do Conhecimento', 1, 3, 'edoc-centro edoc-negrito')
            . self::td('Componentes Curriculares', 1, 3, 'edoc-centro edoc-negrito')
            . self::td('Séries', 3, 1, 'edoc-centro edoc-negrito'),
            '5mm'
        );
        $linhas[] = self::tr(
            self::td('1ª', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('2ª', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('3ª', 1, 1, 'edoc-centro edoc-negrito'),
            '4.6mm'
        );
        $linhas[] = self::tr(
            self::td('Pontos', 1, 1, 'edoc-centro')
            . self::td('Pontos', 1, 1, 'edoc-centro')
            . self::td('Pontos', 1, 1, 'edoc-centro'),
            '4.6mm'
        );

        $blocos = [
            ['Linguagens, Códigos e suas Tecnologias', ['Língua Portuguesa', 'Educação Física', 'Arte'], ['C', 'U', 'R']],
            ['Ciências da Natureza, Matemática e suas Tecnologias', ['Física', 'Química', 'Biologia', 'Matemática'], ['S', 'A', 'N', 'D']],
            ['Ciências Humanas e suas Tecnologias', ['História', 'Geografia', 'Sociologia', 'Filosofia'], ['O', '—', '—', '—']],
        ];
        foreach ($blocos as [$area, $discs, $letras]) {
            $primeira = true;
            foreach ($discs as $i => $disc) {
                $miolo = '';
                if ($primeira) {
                    $miolo .= self::td($area, 1, count($discs), 'edoc-centro');
                    $primeira = false;
                }
                $linhas[] = self::tr(
                    $miolo . self::td($disc) . $nota($letras[$i]) . $nota('—') . $nota('—'),
                    '5.2mm'
                );
            }
        }
        $parte = ['Inglês', 'Espanhol', 'Juventude, Educação e Trabalho', ''];
        foreach ($parte as $i => $disc) {
            $miolo = $i === 0 ? self::td('Parte Diversificada', 1, 4, 'edoc-vert') : '';
            $nome = $disc === '' ? self::tdHtml('&nbsp;', 2) : self::td($disc, 2);
            $linhas[] = self::tr($miolo . $nome . $vazio . $vazio . $vazio, '6.4mm');
        }
        foreach ([
            'Total da Carga Horária Anual',
            'Total de Dias Letivos',
            '% de Faltas Anual',
            'Resultado Final',
        ] as $rotulo) {
            $linhas[] = self::tr(self::td($rotulo, 4, 1, 'edoc-negrito') . $vazio . $vazio . $vazio, '5.2mm');
        }
        $grade = self::tabela($c, implode('', $linhas), 'edoc-solto');

        $traj = self::tabela(
            self::cols([12, 18, 14, 34, 22]),
            self::tr(
                self::td('Ano', 1, 1, 'edoc-centro edoc-negrito')
                . self::td('Série/Turma', 1, 1, 'edoc-centro edoc-negrito')
                . self::td('Turno', 1, 1, 'edoc-centro edoc-negrito')
                . self::td('Unidade de Ensino', 1, 1, 'edoc-centro edoc-negrito')
                . self::td('Município/Estado', 1, 1, 'edoc-centro edoc-negrito')
            )
            . self::tr(
                self::td('{{ano_letivo}}', 1, 1, 'edoc-centro')
                . self::td('{{turma_nome}}', 1, 1, 'edoc-centro')
                . self::td('{{turno}}', 1, 1, 'edoc-centro')
                . self::td('{{escola_nome}}')
                . self::td('{{aluno_cidade}}', 1, 1, 'edoc-centro')
            )
            . self::tr(self::tdHtml('&nbsp;', 1, 1, 'edoc-centro') . $vazio . $vazio . self::tdHtml('&nbsp;') . $vazio, '6mm')
            . self::tr(self::tdHtml('&nbsp;', 1, 1, 'edoc-centro') . $vazio . $vazio . self::tdHtml('&nbsp;') . $vazio, '6mm')
        );

        return $cab . $escola . $aluno . $grade . $traj;
    }

    private static function htmlEspiritoSantoVerso(): string
    {
        $titulo = self::tabela(
            self::cols([100]),
            self::tr(self::td('Guia de Transferência', 1, 1, 'edoc-titulo'), '7mm')
            . self::tr(self::td('O aluno está cursando a {{serie}} do Ensino Médio, e até a presente data foram apurados os seguintes resultados parciais.', 1)),
            'edoc-solto'
        );

        $larguras = [22];
        for ($i = 0; $i < 3; $i++) {
            $larguras[] = 8.7;
            $larguras[] = 8.7;
            $larguras[] = 8.6;
        }
        $disc = [
            'Língua Portuguesa', 'Educação Física', 'Arte', 'Física', 'Química', 'Biologia',
            'Matemática', 'História', 'Geografia', 'Sociologia', 'Filosofia', 'Inglês',
            'Espanhol', 'Juventude, Educação e Trabalho',
        ];
        $cab2 = self::td('Pontos', 1, 1, 'edoc-centro')
            . self::td('Carga Horária', 1, 1, 'edoc-centro')
            . self::td('Nº de Faltas', 1, 1, 'edoc-centro');
        $linhas = self::tr(
            self::td('Componentes Curriculares', 1, 2, 'edoc-centro edoc-negrito')
            . self::td('1º Trimestre', 3, 1, 'edoc-centro edoc-negrito')
            . self::td('2º Trimestre', 3, 1, 'edoc-centro edoc-negrito')
            . self::td('3º Trimestre', 3, 1, 'edoc-centro edoc-negrito'),
            '5.4mm'
        );
        $linhas .= self::tr($cab2 . $cab2 . $cab2, '6.5mm');
        $traco = self::td('—', 1, 1, 'edoc-centro');
        foreach ($disc as $nome) {
            $linhas .= self::tr(self::td($nome) . str_repeat($traco, 9), '5.3mm');
        }
        $grade = self::tabela(self::cols($larguras), $linhas, 'edoc-solto');

        $criterio = 'Na avaliação da aprendizagem o ano letivo é dividido em três trimestres, a saber:'
            . '<br>1º Trimestre — 30 pontos'
            . '<br>2º Trimestre — 30 pontos'
            . '<br>3º Trimestre — 40 pontos'
            . '<br><br>Numa escala de 0 a 100 pontos é promovido à série seguinte o aluno que alcançar, no mínimo, 60 pontos em cada componente curricular e, pelo menos, 75% de frequência do total de horas do período letivo.';
        $caixa = self::tabela(
            self::cols([50, 50]),
            self::tr(
                self::td('Critérios de Avaliação', 1, 1, 'edoc-centro edoc-negrito')
                . self::td('Observações', 1, 1, 'edoc-centro edoc-negrito')
            )
            . self::tr(
                self::tdHtml($criterio)
                . self::td('{{observacoes}}'),
                '42mm'
            ),
            'edoc-solto'
        );

        $data = self::tabela(
            self::cols([100]),
            self::tr(self::td('{{cidade_data}}', 1, 1, 'edoc-centro'), '10mm'),
            'edoc-sem-borda edoc-solto'
        );
        $assin = self::tabela(
            self::cols([50, 50]),
            self::tr(
                self::tdHtml('_______________________________<br>{{secretario_nome}}<br>Assinatura e Carimbo da Secretária Escolar', 1, 1, 'edoc-centro')
                . self::tdHtml('_______________________________<br>{{diretor_nome}}<br>Assinatura e Carimbo do Diretor Escolar', 1, 1, 'edoc-centro'),
                '16mm'
            ),
            'edoc-sem-borda'
        );

        return $titulo . $grade . $caixa . $data . $assin;
    }

    private static function htmlEmBranco(): string
    {
        $c = self::cols([8, 18, 18, 14, 14, 14, 14]);
        $linhas = self::tr(self::td('HISTÓRICO ESCOLAR', 7, 1, 'edoc-titulo'), '8mm');
        $linhas .= self::tr(
            self::td('Texto na vertical', 1, 8, 'edoc-vert')
            . self::td('Área', 1, 1, 'edoc-faixa')
            . self::td('Componente', 1, 1, 'edoc-faixa')
            . self::td('1ª', 1, 1, 'edoc-faixa')
            . self::td('2ª', 1, 1, 'edoc-faixa')
            . self::td('3ª', 1, 1, 'edoc-faixa')
            . self::td('CH', 1, 1, 'edoc-faixa'),
            '6mm'
        );
        for ($i = 0; $i < 7; $i++) {
            $linhas .= self::tr(str_repeat(self::tdHtml('&nbsp;'), 6), '6.5mm');
        }
        $linhas .= self::tr(self::td('Observações', 7), '8mm');

        return self::tabela($c, $linhas);
    }

    /**
     * @param list<float> $larguras
     */
    private static function cols(array $larguras): string
    {
        $html = '';
        foreach ($larguras as $w) {
            $html .= '<col style="width:' . rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.') . '%">';
        }

        return $html;
    }

    private static function tabela(string $cols, string $corpo, string $extra = ''): string
    {
        $classe = trim('edoc-grade-livre ' . $extra);

        return '<table class="' . $classe . '"><colgroup>' . $cols . '</colgroup><tbody>' . $corpo . '</tbody></table>';
    }

    private static function tr(string $miolo, string $altura = ''): string
    {
        $style = $altura !== '' ? ' style="height:' . $altura . '"' : '';

        return '<tr' . $style . '>' . $miolo . '</tr>';
    }

    private static function td(string $texto, int $cs = 1, int $rs = 1, string $classe = ''): string
    {
        return self::tdHtml(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'), $cs, $rs, $classe);
    }

    private static function tdHtml(string $html, int $cs = 1, int $rs = 1, string $classe = ''): string
    {
        $attrs = '';
        if ($classe !== '') {
            $attrs .= ' class="' . $classe . '"';
        }
        if ($cs > 1) {
            $attrs .= ' colspan="' . $cs . '"';
        }
        if ($rs > 1) {
            $attrs .= ' rowspan="' . $rs . '"';
        }

        return '<td' . $attrs . '>' . $html . '</td>';
    }
}
