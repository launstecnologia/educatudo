<?php

namespace App\Modulos\VidaEscolar\Services;

/**
 * Histórico escolar numa grade única, no formato da folha oficial:
 * cabeçalho da escola, base nacional comum, parte diversificada,
 * estudos realizados e certificado. As notas vêm do histórico do aluno.
 */
class GradeHistoricoOficial
{
    /**
     * @param array<string,mixed> $dados
     */
    public static function html(array $dados, string $logoHtml): string
    {
        $aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
        $unidade = is_array($dados['unidade'] ?? null) ? $dados['unidade'] : [];
        $doc = is_array($dados['documento'] ?? null) ? $dados['documento'] : [];
        $itens = is_array($dados['itens'] ?? null) ? $dados['itens'] : [];
        $resultados = is_array($dados['resultados'] ?? null) ? $dados['resultados'] : [];
        $estudos = is_array($dados['estudos'] ?? null) ? $dados['estudos'] : [];

        $quadro = self::quadro($itens, $resultados);
        $transf = is_array($dados['transferencia'] ?? null) ? $dados['transferencia'] : null;
        if ($transf !== null) {
            $quadro['ultimo']['resultado'] = 'Transferido';
        }
        $escola = self::escola($unidade);
        $etapa = $quadro['ensino_medio'] ? 'ENSINO MÉDIO' : 'ENSINO FUNDAMENTAL';
        $tituloFolha = $transf !== null
            ? 'HISTÓRICO ESCOLAR PARA TRANSFERÊNCIA — ' . $etapa
            : 'HISTÓRICO ESCOLAR — ' . $etapa;
        $obs = trim((string) ($dados['observacoes_gerais'] ?? $doc['observacoes_gerais'] ?? ''));
        if ($quadro['sobra'] !== []) {
            $extra = 'Outros componentes: ' . implode(', ', $quadro['sobra']) . '.';
            $obs = trim($obs . ($obs !== '' ? ' ' : '') . $extra);
        }

        $c = self::cols([3.2, 3.2, 12, 8, 25.6, 16, 16, 16]);
        $linhas = self::cabecalho($escola, $logoHtml);
        $linhas[] = self::tr(self::td($tituloFolha, 8, 1, 'edoc-titulo'), '6.2mm');
        $linhas[] = self::identidade($aluno);
        $linhas[] = self::nascimento($aluno);
        $linhas[] = self::tr(self::td('Data: ' . self::dataBr((string) ($aluno['data_nasc'] ?? '')), 8), '5mm');
        if ($transf !== null) {
            $linhas[] = self::tr(
                self::td('Transferência', 2, 1, 'edoc-negrito')
                . self::td('Data de saída: ' . trim((string) ($transf['data_saida_br'] ?? '')), 2)
                . self::td('Turma: ' . trim((string) ($transf['turma'] ?? '')), 2)
                . self::td('Ano letivo: ' . trim((string) ($transf['ano_letivo'] ?? '')), 2),
                '5mm'
            );
        }

        $divLinhas = $quadro['diversificada'];
        $rsFund = 2 + 11 + 1 + count($divLinhas) + 3;
        $rsBase = 13;
        $linhas[] = self::tr(
            self::td('Fundamento Legal: Lei Federal 9.394/96', 1, $rsFund, 'edoc-vert')
            . self::td('BASE NACIONAL COMUM', 1, $rsBase, 'edoc-vert')
            . self::td('ÁREAS DE CONHECIMENTO', 1, 2, 'edoc-centro edoc-negrito')
            . self::td('COMPONENTES CURRICULARES', 2, 2, 'edoc-centro edoc-negrito')
            . self::td('Ano', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('Ano', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('Ano', 1, 1, 'edoc-centro edoc-negrito'),
            '4.8mm'
        );
        $rotulos = $quadro['rotulos'];
        $linhas[] = self::tr(
            self::td($rotulos[0], 1, 1, 'edoc-centro edoc-negrito')
            . self::td($rotulos[1], 1, 1, 'edoc-centro edoc-negrito')
            . self::td($rotulos[2], 1, 1, 'edoc-centro edoc-negrito'),
            '4.8mm'
        );

        foreach (self::gruposBase() as $grupo) {
            $primeira = true;
            foreach ($grupo['disciplinas'] as $disc) {
                $miolo = '';
                if ($primeira) {
                    $miolo .= self::td($grupo['area'], 1, count($grupo['disciplinas']), 'edoc-centro edoc-negrito');
                    $primeira = false;
                }
                $linhas[] = self::tr(
                    $miolo . self::td($disc['nome'], 2) . self::notas($quadro['notas'][$disc['id']] ?? []),
                    '4.6mm'
                );
            }
        }

        $linhas[] = self::tr(
            self::td('Total de Aulas da Base Nacional Comum', 4, 1, 'edoc-negrito') . self::notas([]),
            '4.6mm'
        );

        $primeiraDiv = true;
        $iDiv = 0;
        foreach ($divLinhas as $disc) {
            $miolo = '';
            if ($primeiraDiv) {
                $miolo .= self::td('PARTE DIVERSIFICADA', 1, count($divLinhas), 'edoc-vert');
                $primeiraDiv = false;
            }
            if ($iDiv === 0) {
                $miolo .= self::td('Língua Estrangeira Moderna', 1, 2, 'edoc-centro');
            } elseif ($iDiv > 1) {
                $miolo .= self::tdHtml('&nbsp;', 1);
            }
            $nome = $disc['nome'] !== '' ? $disc['nome'] : ' ';
            $linhas[] = self::tr($miolo . self::td($nome, 2) . self::notas($quadro['notas'][$disc['id']] ?? []), '4.6mm');
            $iDiv++;
        }

        $linhas[] = self::tr(self::td('Total de Aulas da Parte Diversificada', 4, 1, 'edoc-negrito') . self::notas([]), '4.6mm');
        $linhas[] = self::tr(self::td('Total de Aulas Anual do Curso (Aulas)', 4, 1, 'edoc-negrito') . self::notas([]), '4.6mm');
        $linhas[] = self::tr(
            self::td('Total de Carga Horária Anual do Curso (Horas)', 4, 1, 'edoc-negrito') . self::notas($quadro['carga'], true),
            '4.6mm'
        );

        $linhas[] = self::tr(
            self::tdHtml('&nbsp;')
            . self::td('Série', 2, 1, 'edoc-centro edoc-negrito')
            . self::td('Ano', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('Estabelecimento de Ensino', 2, 1, 'edoc-centro edoc-negrito')
            . self::td('Município', 1, 1, 'edoc-centro edoc-negrito')
            . self::td('UF', 1, 1, 'edoc-centro edoc-negrito'),
            '4.8mm'
        );

        $blocos = self::blocosEstudo($estudos, $itens, $escola, $quadro);
        foreach ($blocos as $bloco) {
            $i = 0;
            $total = count($bloco['linhas']);
            foreach ($bloco['linhas'] as $linha) {
                $miolo = '';
                if ($i === 0) {
                    $miolo .= self::td($bloco['titulo'], 1, $total, 'edoc-vert');
                }
                $linhas[] = self::tr(
                    $miolo
                    . self::td((string) $linha['serie'], 2, 1, 'edoc-centro')
                    . self::td((string) $linha['ano'], 1, 1, 'edoc-centro')
                    . self::td((string) $linha['escola'], 2)
                    . self::td((string) $linha['municipio'], 1, 1, 'edoc-centro')
                    . self::td((string) $linha['uf'], 1, 1, 'edoc-centro'),
                    '6.2mm'
                );
                $i++;
            }
        }

        $escala = ($escola['uf'] === 'SP')
            ? 'Escala de Avaliação: A partir de 2007 — escala numérica de notas de 0 (zero) a 10 (dez), com desempenho escolar satisfatório na nota igual ou superior a 5 (cinco), nos termos da Resolução SE 62, de 29/10/2019.'
            : 'Escala de Avaliação: notas de 0 (zero) a 10 (dez). O aproveitamento satisfatório segue a regra de aprovação da instituição.';
        $linhas[] = self::tr(self::td($escala, 8), '8mm');
        $linhas[] = self::tr(self::td('OBSERVAÇÕES: ' . $obs, 8), '6mm');
        $linhas[] = self::tr(self::td('CERTIFICADO', 8, 1, 'edoc-titulo'), '5mm');
        $linhas[] = self::tr(self::td(self::certificado($aluno, $escola, $quadro), 8), '9mm');
        if ($transf !== null) {
            $dataSaida = trim((string) ($transf['data_saida_br'] ?? ''));
            $linhas[] = self::tr(self::td('Data da transferência: ' . ($dataSaida !== '' ? $dataSaida : '—'), 8), '5mm');
        } else {
            $registro = trim((string) ($dados['numero_registro_sed'] ?? $doc['numero_registro_sed'] ?? ''));
            $linhas[] = self::tr(self::td('Número de publicação de concluinte: ' . $registro, 8), '5mm');
        }

        $url = trim((string) ($dados['validation_url'] ?? ''));
        $rodape = $url !== ''
            ? '<p style="margin:2px 0 0;font-size:7pt;color:#444;">Validação: ' . self::e($url) . '</p>'
            : '';

        return self::tabela($c, implode('', $linhas)) . $rodape;
    }

    /**
     * @param array<string,mixed> $unidade
     * @return array{nome:string,endereco:string,bairro:string,municipio:string,uf:string,cep:string,telefone:string,email:string,cnpj:string,inep:string,ato:string,diretor:string}
     */
    private static function escola(array $unidade): array
    {
        $nome = trim((string) ($unidade['razao_social'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($unidade['nome'] ?? 'Instituição de Ensino'));
        }
        $logradouro = trim((string) ($unidade['endereco'] ?? $unidade['logradouro'] ?? ''));
        $numero = trim((string) ($unidade['numero'] ?? ''));
        if ($numero !== '' && $logradouro !== '') {
            $logradouro .= ', ' . $numero;
        }

        return [
            'nome' => $nome,
            'endereco' => $logradouro,
            'bairro' => trim((string) ($unidade['bairro'] ?? '')),
            'municipio' => trim((string) ($unidade['cidade'] ?? $unidade['municipio'] ?? '')),
            'uf' => strtoupper(trim((string) ($unidade['uf'] ?? $unidade['estado'] ?? ''))),
            'cep' => trim((string) ($unidade['cep'] ?? '')),
            'telefone' => trim((string) ($unidade['telefone'] ?? $unidade['fone'] ?? '')),
            'email' => trim((string) ($unidade['email'] ?? $unidade['email_institucional'] ?? '')),
            'cnpj' => trim((string) ($unidade['cnpj'] ?? '')),
            'inep' => trim((string) ($unidade['inep'] ?? $unidade['codigo_inep'] ?? '')),
            'ato' => trim((string) ($unidade['ato_autorizacao'] ?? $unidade['ato_criacao'] ?? '')),
            'diretor' => trim((string) ($unidade['diretor_nome'] ?? '')),
        ];
    }

    /**
     * @param array{nome:string,endereco:string,bairro:string,municipio:string,uf:string,cep:string,telefone:string,email:string,cnpj:string,inep:string,ato:string,diretor:string} $escola
     * @return list<string>
     */
    private static function cabecalho(array $escola, string $logoHtml): array
    {
        $logo = $logoHtml !== '' ? $logoHtml : '&nbsp;';
        $local = self::juntar([
            $escola['bairro'] !== '' ? 'Bairro: ' . $escola['bairro'] : '',
            $escola['municipio'] !== '' ? 'Município: ' . $escola['municipio'] : '',
            $escola['uf'] !== '' ? $escola['uf'] : '',
            $escola['cep'] !== '' ? 'CEP: ' . $escola['cep'] : '',
        ]);
        $contato = self::juntar([
            $escola['telefone'] !== '' ? 'Telefone: ' . $escola['telefone'] : '',
            $escola['email'] !== '' ? 'E-mail: ' . $escola['email'] : '',
        ]);
        $docs = self::juntar([
            $escola['cnpj'] !== '' ? 'CNPJ: ' . $escola['cnpj'] : '',
            $escola['inep'] !== '' ? 'INEP: ' . $escola['inep'] : '',
        ]);
        $ato = $escola['ato'] !== '' ? 'Ato legal de criação: ' . $escola['ato'] : 'Ato legal de criação:';

        return [
            self::tr(
                self::tdHtml($logo, 2, 6, 'edoc-logo')
                . self::td($escola['nome'], 6, 1, 'edoc-centro edoc-negrito'),
                '5mm'
            ),
            self::tr(self::td($ato, 6, 1, 'edoc-centro'), '4.4mm'),
            self::tr(self::td('Endereço: ' . $escola['endereco'], 6), '4.4mm'),
            self::tr(self::td($local, 6), '4.4mm'),
            self::tr(self::td($contato, 6), '4.4mm'),
            self::tr(self::td($docs, 6, 1, 'edoc-centro'), '4.4mm'),
        ];
    }

    /**
     * @param array<string,mixed> $aluno
     */
    private static function identidade(array $aluno): string
    {
        $rg = trim((string) ($aluno['rg'] ?? ''));
        if ($rg === '') {
            $rg = trim((string) ($aluno['cpf'] ?? ''));
        }
        $ra = trim((string) ($aluno['ra'] ?? ''));
        if ($ra === '') {
            $ra = trim((string) ($aluno['codigo_aluno'] ?? ''));
        }

        return self::tr(
            self::td('Nome do Aluno: ' . trim((string) ($aluno['nome'] ?? '')), 4)
            . self::td('RG/RNM: ' . $rg, 2)
            . self::td('RA: ' . $ra, 2),
            '5.2mm'
        );
    }

    /**
     * @param array<string,mixed> $aluno
     */
    private static function nascimento(array $aluno): string
    {
        return self::tr(
            self::td('Nascimento', 2, 1, 'edoc-negrito')
            . self::td('Município: ' . trim((string) ($aluno['naturalidade'] ?? '')), 2)
            . self::td('Estado: ' . trim((string) ($aluno['uf_nascimento'] ?? '')), 2)
            . self::td('País: ' . trim((string) ($aluno['nacionalidade'] ?? '')), 2),
            '5mm'
        );
    }

    /**
     * @param list<array<string,mixed>> $itens
     * @param list<array<string,mixed>> $resultados
     * @return array{
     *   ensino_medio:bool,
     *   rotulos:array{0:string,1:string,2:string},
     *   notas:array<string,array<int,string>>,
     *   carga:array<int,string>,
     *   diversificada:list<array{id:string,nome:string}>,
     *   sobra:list<string>,
     *   anos:array<int,string>,
     *   series:array<int,string>,
     *   resultados:array<int,string>,
     *   ultimo:array{serie:string,ano:string,resultado:string}
     * }
     */
    private static function quadro(array $itens, array $resultados): array
    {
        $chaves = [];
        foreach ($itens as $it) {
            if (!is_array($it)) {
                continue;
            }
            $serie = trim((string) ($it['serie_ano'] ?? ''));
            $ano = trim((string) ($it['ano_letivo'] ?? ''));
            $faixa = self::faixa($serie);
            $k = $ano . '|' . $serie;
            if (!isset($chaves[$k])) {
                $chaves[$k] = ['ano' => $ano, 'serie' => $serie, 'faixa' => $faixa];
            }
        }
        uasort($chaves, static fn (array $a, array $b): int => strcmp($a['ano'] . $a['serie'], $b['ano'] . $b['serie']));

        $temMedio = false;
        foreach ($chaves as $col) {
            if ($col['faixa']['etapa'] === 'em') {
                $temMedio = true;
                break;
            }
        }
        $rotulos = $temMedio ? ['1ª Série', '2ª Série', '3ª Série'] : ['', '', ''];
        $mapaColuna = [];
        $ordem = 0;
        $anos = ['', '', ''];
        $series = ['', '', ''];
        foreach ($chaves as $k => $col) {
            if ($temMedio) {
                if ($col['faixa']['etapa'] !== 'em') {
                    continue;
                }
                $idx = $col['faixa']['indice'];
            } else {
                if ($ordem > 2) {
                    break;
                }
                $idx = $ordem;
                $rotulos[$idx] = $col['serie'] !== '' ? $col['serie'] : 'Série';
                $ordem++;
            }
            if ($idx < 0 || $idx > 2) {
                continue;
            }
            $mapaColuna[$k] = $idx;
            if ($anos[$idx] === '') {
                $anos[$idx] = $col['ano'];
                $series[$idx] = $col['serie'] !== '' ? $col['serie'] : $rotulos[$idx];
            }
        }

        $notas = [];
        $cargaNum = [0, 0, 0];
        $extras = [];
        foreach ($itens as $it) {
            if (!is_array($it)) {
                continue;
            }
            $k = trim((string) ($it['ano_letivo'] ?? '')) . '|' . trim((string) ($it['serie_ano'] ?? ''));
            if (!isset($mapaColuna[$k])) {
                continue;
            }
            $idx = $mapaColuna[$k];
            $nome = trim((string) ($it['componente'] ?? ''));
            $valor = self::nota($it['resultado_valor'] ?? '');
            $id = self::canonico($nome);
            if ($id === null) {
                if ($nome !== '') {
                    $extras[$nome][$idx] = $valor;
                }
            } else {
                $notas[$id][$idx] = $valor;
            }
            $ch = $it['carga_horaria'] ?? null;
            if ($ch !== null && $ch !== '' && is_numeric($ch)) {
                $cargaNum[$idx] += (int) $ch;
            }
        }

        $div = [
            ['id' => 'ingles', 'nome' => 'Inglês'],
            ['id' => 'espanhol', 'nome' => 'Espanhol'],
        ];
        $sobra = [];
        $nExtra = 0;
        foreach ($extras as $nome => $vals) {
            if ($nExtra >= 6) {
                $sobra[] = $nome;
                continue;
            }
            $id = 'extra_' . $nExtra;
            $notas[$id] = $vals;
            $div[] = ['id' => $id, 'nome' => $nome];
            $nExtra++;
        }
        while (count($div) < 5) {
            $div[] = ['id' => 'vazio_' . count($div), 'nome' => ''];
        }

        $carga = [];
        foreach ($cargaNum as $i => $total) {
            $carga[$i] = $total > 0 ? (string) $total : '';
        }

        $resultadosIdx = [];
        foreach ($resultados as $r) {
            if (!is_array($r)) {
                continue;
            }
            $k = trim((string) ($r['ano_letivo'] ?? '')) . '|' . trim((string) ($r['serie_ano'] ?? ''));
            if (!isset($mapaColuna[$k])) {
                continue;
            }
            $resultadosIdx[$mapaColuna[$k]] = self::rotuloResultado((string) ($r['resultado'] ?? ''));
        }
        $ultimo = ['serie' => '', 'ano' => '', 'resultado' => ''];
        for ($i = 2; $i >= 0; $i--) {
            if ($anos[$i] === '' && ($notas === [] || !self::colunaTemNota($notas, $i))) {
                continue;
            }
            if ($anos[$i] === '' && $series[$i] === '') {
                continue;
            }
            $ultimo = [
                'serie' => $rotulos[$i] !== '' ? $rotulos[$i] : $series[$i],
                'ano' => $anos[$i],
                'resultado' => $resultadosIdx[$i] ?? '',
            ];
            break;
        }

        return [
            'ensino_medio' => $temMedio,
            'rotulos' => [$rotulos[0], $rotulos[1], $rotulos[2]],
            'notas' => $notas,
            'carga' => $carga,
            'diversificada' => $div,
            'sobra' => $sobra,
            'anos' => $anos,
            'series' => $series,
            'resultados' => $resultadosIdx,
            'ultimo' => $ultimo,
        ];
    }

    /**
     * @param array<string,array<int,string>> $notas
     */
    private static function colunaTemNota(array $notas, int $idx): bool
    {
        foreach ($notas as $vals) {
            if (trim((string) ($vals[$idx] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $estudos
     * @param list<array<string,mixed>> $itens
     * @param array{nome:string,municipio:string,uf:string} $escola
     * @param array{ensino_medio:bool,anos:array<int,string>,series:array<int,string>,rotulos:array{0:string,1:string,2:string}} $quadro
     * @return list<array{titulo:string,linhas:list<array{serie:string,ano:string,escola:string,municipio:string,uf:string}>}>
     */
    private static function blocosEstudo(array $estudos, array $itens, array $escola, array $quadro): array
    {
        $porChave = [];
        foreach ($estudos as $estudo) {
            if (!is_array($estudo)) {
                continue;
            }
            $serie = trim((string) ($estudo['serie_ano'] ?? ''));
            $ano = trim((string) ($estudo['ano_letivo'] ?? ''));
            if ($ano === '' && $serie === '') {
                continue;
            }
            $porChave[$ano . '|' . $serie] = [
                'serie' => $serie,
                'ano' => $ano,
                'escola' => trim((string) ($estudo['escola'] ?? '')) ?: $escola['nome'],
                'municipio' => trim((string) ($estudo['municipio'] ?? '')) ?: $escola['municipio'],
                'uf' => trim((string) ($estudo['uf'] ?? '')) ?: $escola['uf'],
                'faixa' => self::faixa($serie),
            ];
        }
        if ($porChave === []) {
            foreach ($itens as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $serie = trim((string) ($it['serie_ano'] ?? ''));
                $ano = trim((string) ($it['ano_letivo'] ?? ''));
                $k = $ano . '|' . $serie;
                if ($ano === '' || isset($porChave[$k])) {
                    continue;
                }
                $origem = trim((string) ($it['escola_origem'] ?? ''));
                $porChave[$k] = [
                    'serie' => $serie,
                    'ano' => $ano,
                    'escola' => $origem !== '' ? $origem : $escola['nome'],
                    'municipio' => $escola['municipio'],
                    'uf' => $escola['uf'],
                    'faixa' => self::faixa($serie),
                ];
            }
        }

        $fund = [];
        $medio = [
            0 => self::linhaEstudo('1ª Série', '', $escola),
            1 => self::linhaEstudo('2ª Série', '', $escola),
            2 => self::linhaEstudo('3ª Série', '', $escola),
        ];
        $medioPreenchido = false;
        foreach ($porChave as $linha) {
            $faixa = $linha['faixa'];
            unset($linha['faixa']);
            if ($faixa['etapa'] === 'em') {
                $medio[$faixa['indice']] = [
                    'serie' => $quadro['rotulos'][$faixa['indice']] ?? $linha['serie'],
                    'ano' => $linha['ano'],
                    'escola' => $linha['escola'],
                    'municipio' => $linha['municipio'],
                    'uf' => $linha['uf'],
                ];
                $medioPreenchido = true;
                continue;
            }
            if ($faixa['etapa'] === 'ef' || !$quadro['ensino_medio']) {
                $fund[] = $linha;
            }
        }
        if ($fund === []) {
            $fund[] = self::linhaEstudo('9º Ano', '', $escola);
        }
        if (!$quadro['ensino_medio'] && !$medioPreenchido) {
            $medio = [];
            foreach ($quadro['series'] as $i => $serie) {
                if ($serie === '' && ($quadro['anos'][$i] ?? '') === '') {
                    continue;
                }
                $medio[] = self::linhaEstudo(
                    $serie !== '' ? $serie : (string) $quadro['rotulos'][$i],
                    (string) ($quadro['anos'][$i] ?? ''),
                    $escola
                );
            }
        }

        $blocos = [
            ['titulo' => 'Ensino Fundamental', 'linhas' => array_values($fund)],
        ];
        if ($medio !== []) {
            $blocos[] = ['titulo' => 'Ensino Médio', 'linhas' => array_values($medio)];
        }

        return $blocos;
    }

    /**
     * @param array{nome:string,municipio:string,uf:string} $escola
     * @return array{serie:string,ano:string,escola:string,municipio:string,uf:string}
     */
    private static function linhaEstudo(string $serie, string $ano, array $escola): array
    {
        return [
            'serie' => $serie,
            'ano' => $ano,
            'escola' => $ano !== '' ? $escola['nome'] : '',
            'municipio' => $ano !== '' ? $escola['municipio'] : '',
            'uf' => $ano !== '' ? $escola['uf'] : '',
        ];
    }

    /**
     * @param array<string,mixed> $aluno
     * @param array{nome:string,diretor:string} $escola
     * @param array{ensino_medio:bool,ultimo:array{serie:string,ano:string,resultado:string}} $quadro
     */
    private static function certificado(array $aluno, array $escola, array $quadro): string
    {
        $diretor = $escola['diretor'] !== '' ? ' (' . $escola['diretor'] . ')' : '';
        $nome = trim((string) ($aluno['nome'] ?? ''));
        $ra = trim((string) ($aluno['ra'] ?? ''));
        if ($ra === '') {
            $ra = trim((string) ($aluno['codigo_aluno'] ?? ''));
        }
        $rg = trim((string) ($aluno['rg'] ?? ''));
        $ultimo = $quadro['ultimo'];
        $serie = $ultimo['serie'] !== '' ? $ultimo['serie'] : 'série informada';
        $ano = $ultimo['ano'] !== '' ? $ultimo['ano'] : '—';
        $etapa = $quadro['ensino_medio'] ? 'Ensino Médio' : 'Ensino Fundamental';
        $fato = self::fatoCertificado($ultimo['resultado']);
        $docs = [];
        if ($ra !== '') {
            $docs[] = 'RA ' . $ra;
        }
        if ($rg !== '') {
            $docs[] = 'RG ' . $rg;
        }
        $ident = $nome;
        if ($docs !== []) {
            $ident .= ', ' . implode(', ', $docs) . ',';
        }

        return 'O Diretor da ' . $escola['nome'] . $diretor . ' certifica, nos termos do inciso VII do art. 24 da Lei Federal 9.394/96, que '
            . $ident . ' ' . $fato . ' ' . $serie . ' do ' . $etapa . ', no ano de ' . $ano . '.';
    }

    private static function fatoCertificado(string $resultado): string
    {
        $r = self::norm($resultado);
        if (str_contains($r, 'retid') || str_contains($r, 'reprov')) {
            return 'foi retido na';
        }
        if (str_contains($r, 'transfer')) {
            return 'transferiu-se na';
        }
        if (str_contains($r, 'cursando') || str_contains($r, 'andamento') || $r === '') {
            return 'cursou a';
        }

        return 'concluiu a';
    }

    private static function rotuloResultado(string $resultado): string
    {
        $mapa = [
            'Aprovado' => 'Aprovado',
            'Aprovado_Conselho' => 'Aprovado pelo Conselho',
            'Retido' => 'Retido',
            'Transferido' => 'Transferido',
            'Evadido' => 'Evadido',
            'Cursando' => 'Cursando',
        ];

        return $mapa[$resultado] ?? $resultado;
    }

    /**
     * @return array{etapa:string,indice:int}
     */
    private static function faixa(string $serie): array
    {
        $n = self::norm($serie);
        $num = 0;
        if (preg_match('/\b(\d+)\b/', $n, $m) === 1) {
            $num = (int) $m[1];
        }
        $medio = str_contains($n, 'medio') || preg_match('/\bem\b/', $n) === 1;
        if (!$medio && str_contains($n, 'serie') && !str_contains($n, 'fundamental') && $num >= 1 && $num <= 3) {
            $medio = true;
        }
        if ($medio && $num >= 1 && $num <= 3) {
            return ['etapa' => 'em', 'indice' => $num - 1];
        }
        if (str_contains($n, 'fundamental') || (str_contains($n, 'ano') && !$medio)) {
            return ['etapa' => 'ef', 'indice' => $num];
        }

        return ['etapa' => 'outro', 'indice' => -1];
    }

    private static function canonico(string $nome): ?string
    {
        $n = self::norm($nome);
        if ($n === '') {
            return null;
        }
        $mapa = [
            'lingua portuguesa e literatura' => 'portugues',
            'lingua portuguesa' => 'portugues',
            'portugues' => 'portugues',
            'educacao fisica' => 'edfisica',
            'lingua estrangeira moderna ingles' => 'ingles',
            'lingua inglesa' => 'ingles',
            'ingles' => 'ingles',
            'lingua espanhola' => 'espanhol',
            'espanhol' => 'espanhol',
            'matematica' => 'matematica',
            'biologia' => 'biologia',
            'fisica' => 'fisica',
            'quimica' => 'quimica',
            'historia' => 'historia',
            'geografia' => 'geografia',
            'filosofia' => 'filosofia',
            'sociologia' => 'sociologia',
            'arte' => 'arte',
        ];
        if (isset($mapa[$n])) {
            return $mapa[$n];
        }
        $chaves = array_keys($mapa);
        usort($chaves, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($chaves as $chave) {
            if (str_contains($n, $chave)) {
                return $mapa[$chave];
            }
        }

        return null;
    }

    /**
     * @return list<array{area:string,disciplinas:list<array{id:string,nome:string}>}>
     */
    private static function gruposBase(): array
    {
        return [
            ['area' => 'Linguagens', 'disciplinas' => [
                ['id' => 'portugues', 'nome' => 'Língua Portuguesa e Literatura'],
                ['id' => 'arte', 'nome' => 'Arte'],
                ['id' => 'edfisica', 'nome' => 'Educação Física'],
            ]],
            ['area' => 'Matemática', 'disciplinas' => [
                ['id' => 'matematica', 'nome' => 'Matemática'],
            ]],
            ['area' => 'Ciência da Natureza', 'disciplinas' => [
                ['id' => 'biologia', 'nome' => 'Biologia'],
                ['id' => 'fisica', 'nome' => 'Física'],
                ['id' => 'quimica', 'nome' => 'Química'],
            ]],
            ['area' => 'Ciências Humanas', 'disciplinas' => [
                ['id' => 'historia', 'nome' => 'História'],
                ['id' => 'geografia', 'nome' => 'Geografia'],
                ['id' => 'filosofia', 'nome' => 'Filosofia'],
                ['id' => 'sociologia', 'nome' => 'Sociologia'],
            ]],
        ];
    }

    /**
     * @param array<int,string> $vals
     */
    private static function notas(array $vals, bool $zeroVazio = false): string
    {
        $html = '';
        for ($i = 0; $i < 3; $i++) {
            $txt = trim((string) ($vals[$i] ?? ''));
            if ($txt === '' && $zeroVazio) {
                $txt = ' ';
            }
            $html .= self::td($txt !== '' ? $txt : ' ', 1, 1, 'edoc-centro');
        }

        return $html;
    }

    private static function nota($valor): string
    {
        $s = trim((string) $valor);
        if ($s === '') {
            return '';
        }
        $n = str_replace(',', '.', $s);
        if (!is_numeric($n)) {
            return $s;
        }

        return number_format((float) $n, 1, ',', '');
    }

    private static function dataBr(string $data): string
    {
        $data = substr(trim($data), 0, 10);
        if ($data === '' || $data === '0000-00-00') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $data);

        return $dt instanceof \DateTime ? $dt->format('d/m/Y') : '';
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ç' => 'c', 'º' => '', 'ª' => '',
        ]);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? '';

        return trim($s);
    }

    /**
     * @param list<string> $partes
     */
    private static function juntar(array $partes): string
    {
        $partes = array_values(array_filter($partes, static fn (string $p): bool => $p !== ''));

        return implode('   ', $partes);
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

    private static function tabela(string $cols, string $corpo): string
    {
        return '<table class="edoc-grade-livre"><colgroup>' . $cols . '</colgroup><tbody>' . $corpo . '</tbody></table>';
    }

    private static function tr(string $miolo, string $altura = ''): string
    {
        $style = $altura !== '' ? ' style="height:' . $altura . '"' : '';

        return '<tr' . $style . '>' . $miolo . '</tr>';
    }

    private static function td(string $texto, int $cs = 1, int $rs = 1, string $classe = ''): string
    {
        return self::tdHtml(self::e($texto), $cs, $rs, $classe);
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

    private static function e(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }
}
