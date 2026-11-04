<?php

namespace App\Painel\Admin\Services;

use App\Core\LayoutHelper;
use App\Core\Logger;

/**
 * Monta o relatório completo da escola a partir dos bancos que o usuário marcou.
 * Cada banco vira uma aba. Senha e sessão nunca entram no arquivo.
 */
class RelatorioCompletoService
{
    private const LIMITE_ALUNOS = 1500;
    private const LIMITE_LINHAS = 8000;

    /** @var array<string, array{grupo:string,titulo:string,texto:string}> */
    private const BANCOS = [
        'dados' => ['grupo' => 'Cadastro', 'titulo' => 'Dados cadastrais', 'texto' => 'Nome, RA, documentos, filiação, contato, série e situação.'],
        'ficha' => ['grupo' => 'Cadastro', 'titulo' => 'Ficha complementar', 'texto' => 'Saúde, alimentação, transporte e contato de emergência.'],
        'responsaveis' => ['grupo' => 'Cadastro', 'titulo' => 'Responsáveis', 'texto' => 'Quem responde pelo aluno, contato e vínculo.'],
        'matricula' => ['grupo' => 'Cadastro', 'titulo' => 'Matrícula e turmas', 'texto' => 'Turma atual e o histórico de vínculo por ano.'],
        'documentos' => ['grupo' => 'Cadastro', 'titulo' => 'Documentos', 'texto' => 'O que foi pedido e o que já foi entregue. Sem o arquivo em si.'],
        'chamada' => ['grupo' => 'Cadastro', 'titulo' => 'Lista de chamada', 'texto' => 'Número de chamada, entrada tardia e transferência.'],
        'notas' => ['grupo' => 'Acadêmico', 'titulo' => 'Notas', 'texto' => 'Notas vigentes do boletim, por período e componente.'],
        'resultado' => ['grupo' => 'Acadêmico', 'titulo' => 'Resultado final', 'texto' => 'Situação, média, frequência e faltas do fechamento.'],
        'faltas_resumo' => ['grupo' => 'Rotina', 'titulo' => 'Frequência', 'texto' => 'Totais de presença, falta, justificativa, atraso e saída.'],
        'faltas' => ['grupo' => 'Rotina', 'titulo' => 'Faltas', 'texto' => 'Cada falta, atraso, saída antecipada ou falta justificada.'],
        'ocorrencias' => ['grupo' => 'Rotina', 'titulo' => 'Ocorrências', 'texto' => 'Registro pedagógico: data, categoria, gravidade e encaminhamento.'],
        'redacoes' => ['grupo' => 'Aprendizagem', 'titulo' => 'Redações', 'texto' => 'Tema, data e notas. O texto da redação não entra.'],
        'provas' => ['grupo' => 'Aprendizagem', 'titulo' => 'Provas online', 'texto' => 'Prova, nota, situação e quando foi feita.'],
        'exercicios' => ['grupo' => 'Aprendizagem', 'titulo' => 'Exercícios', 'texto' => 'Lista, acertos e percentual de cada sessão.'],
    ];

    /** @var array<string, array<string, bool>> */
    private array $cacheColunas = [];

    public function __construct(private $db)
    {
    }

    /**
     * @return array<string, array{grupo:string,titulo:string,texto:string}>
     */
    public function catalogo(): array
    {
        return self::BANCOS;
    }

    /**
     * @return array{anos:list<int>,turmas:list<array{id:int,nome:string,ano:int}>}
     */
    public function opcoes(): array
    {
        $anos = [];
        if ($this->tabelaExiste('ano_letivo')) {
            foreach ($this->consultar('SELECT ano FROM ano_letivo ORDER BY ano DESC') as $row) {
                $ano = (int) ($row['ano'] ?? 0);
                if ($ano > 0) {
                    $anos[$ano] = $ano;
                }
            }
        }
        $turmas = [];
        if ($this->tabelaExiste('turmas')) {
            foreach ($this->consultar('SELECT id, nome, ano_letivo FROM turmas ORDER BY ano_letivo DESC, nome ASC') as $row) {
                $ano = (int) ($row['ano_letivo'] ?? 0);
                if ($ano > 0) {
                    $anos[$ano] = $ano;
                }
                $turmas[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'nome' => (string) ($row['nome'] ?? ''),
                    'ano' => $ano,
                ];
            }
        }
        rsort($anos);

        return ['anos' => array_values($anos), 'turmas' => $turmas];
    }

    /**
     * @param array<string,mixed> $entrada
     * @return array{titulo:string,escola:string,gerado_em:string,abas:list<array{nome:string,colunas:list<string>,linhas:list<list<string>>,aviso:string}>}
     */
    public function montar(array $entrada): array
    {
        $filtros = $this->normalizar($entrada);
        if ($filtros['bancos'] === []) {
            throw new \RuntimeException('Marque pelo menos um banco de dados.');
        }
        $alunos = $this->alunos($filtros);
        $ids = [];
        $mapa = [];
        foreach ($alunos as $aluno) {
            $id = (int) ($aluno['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
            $mapa[$id] = $aluno;
        }

        $abas = [];
        foreach ($filtros['bancos'] as $chave) {
            try {
                $abas[] = $this->banco($chave, $filtros, $ids, $mapa);
            } catch (\Throwable $e) {
                Logger::error('Relatório completo: falha no banco ' . $chave, ['exception' => $e], 'general');
                $abas[] = $this->vazio($this->titulo($chave), 'Este banco não pôde ser lido nesta escola.');
            }
        }
        array_unshift($abas, $this->capa($filtros, count($ids), $abas));

        return [
            'titulo' => 'Relatório completo',
            'escola' => $this->nomeEscola(),
            'gerado_em' => date('d/m/Y H:i'),
            'total_alunos' => count($ids),
            'abas' => $abas,
        ];
    }

    /**
     * @param array<string,mixed> $entrada
     * @return array{ano:int,turma_id:int,busca:string,data_inicio:string,data_fim:string,incluir_inativos:bool,bancos:list<string>}
     */
    public function normalizar(array $entrada): array
    {
        $ano = (int) ($entrada['ano'] ?? 0);
        if ($ano < 2000 || $ano > 2100) {
            $ano = 0;
        }
        $turmaId = (int) ($entrada['turma_id'] ?? 0);
        if ($turmaId < 0) {
            $turmaId = 0;
        }
        $busca = trim((string) ($entrada['busca'] ?? ''));
        $busca = str_replace(['%', '_'], '', $busca);
        if (mb_strlen($busca) > 80) {
            $busca = mb_substr($busca, 0, 80);
        }
        $inicio = $this->dataValida((string) ($entrada['data_inicio'] ?? ''));
        $fim = $this->dataValida((string) ($entrada['data_fim'] ?? ''));
        if ($inicio !== '' && $fim !== '' && $inicio > $fim) {
            throw new \RuntimeException('A data inicial não pode ser maior que a data final.');
        }
        $bancos = [];
        $marcados = $entrada['bancos'] ?? [];
        if (!is_array($marcados)) {
            $marcados = [];
        }
        foreach ($marcados as $chave) {
            $chave = (string) $chave;
            if (isset(self::BANCOS[$chave]) && !in_array($chave, $bancos, true)) {
                $bancos[] = $chave;
            }
        }

        return [
            'ano' => $ano,
            'turma_id' => $turmaId,
            'busca' => $busca,
            'data_inicio' => $inicio,
            'data_fim' => $fim,
            'incluir_inativos' => !empty($entrada['incluir_inativos']),
            'bancos' => $bancos,
        ];
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @return array{nome:string,colunas:list<string>,linhas:list<list<string>>,aviso:string}
     */
    private function banco(string $chave, array $filtros, array $ids, array $mapa): array
    {
        return match ($chave) {
            'dados' => $this->dados($ids),
            'ficha' => $this->ficha($ids, $mapa),
            'responsaveis' => $this->responsaveis($ids, $mapa),
            'matricula' => $this->matricula($ids, $mapa, $filtros),
            'documentos' => $this->documentos($ids, $mapa),
            'chamada' => $this->chamada($ids, $mapa, $filtros),
            'notas' => $this->notas($ids, $mapa, $filtros),
            'resultado' => $this->resultado($ids, $mapa, $filtros),
            'faltas_resumo' => $this->faltasResumo($ids, $mapa, $filtros),
            'faltas' => $this->faltas($ids, $mapa, $filtros),
            'ocorrencias' => $this->ocorrencias($ids, $mapa, $filtros),
            'redacoes' => $this->redacoes($ids, $mapa, $filtros),
            'provas' => $this->provas($ids, $mapa, $filtros),
            'exercicios' => $this->exercicios($ids, $mapa, $filtros),
            default => $this->vazio($this->titulo($chave), 'Banco desconhecido.'),
        };
    }

    /**
     * @param list<int> $ids
     * @return array{nome:string,colunas:list<string>,linhas:list<list<string>>,aviso:string}
     */
    private function dados(array $ids): array
    {
        $mapa = [
            'nome' => 'Nome',
            'nome_social' => 'Nome social',
            'ra' => 'RA',
            'ra_digito' => 'Dígito do RA',
            'ra_uf' => 'UF do RA',
            'cgm' => 'CGM',
            'codigo_aluno' => 'Código do aluno',
            'codigo_inep' => 'Código INEP',
            'cpf' => 'CPF',
            'email' => 'E-mail',
            'telefone' => 'Telefone',
            'data_nasc' => 'Nascimento',
            'sexo' => 'Sexo',
            'cor_raca' => 'Cor/raça',
            'nacionalidade' => 'Nacionalidade',
            'naturalidade' => 'Naturalidade',
            'uf_nascimento' => 'UF de nascimento',
            'nome_mae' => 'Nome da mãe',
            'nome_pai' => 'Nome do pai',
            'nis' => 'NIS',
            'certidao_nascimento' => 'Certidão de nascimento',
            'serie' => 'Série',
            'status' => 'Situação',
            'ativo' => 'Ativo',
            'pagante' => 'Pagante',
        ];
        $cols = $this->colunas('alunos');
        $select = ['a.id'];
        $cabecalho = [];
        foreach ($mapa as $coluna => $rotulo) {
            if (!isset($cols[$coluna])) {
                continue;
            }
            $select[] = 'a.' . $coluna;
            $cabecalho[] = $rotulo;
        }
        $cabecalho[] = 'Turma';
        $cabecalho[] = 'Ano da turma';
        $params = [];
        $in = $this->clausulaIn('a.id', $ids, 'dado', $params);
        $rows = $this->consultar(
            'SELECT ' . implode(', ', $select) . ', t.nome AS turma_nome, t.ano_letivo
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE ' . $in . '
             ORDER BY a.nome ASC',
            $params
        );
        $linhas = [];
        foreach ($rows as $row) {
            $linha = [];
            foreach ($mapa as $coluna => $_rotulo) {
                if (!isset($cols[$coluna])) {
                    continue;
                }
                $linha[] = $this->formatarCampo($coluna, $row[$coluna] ?? '');
            }
            $linha[] = $this->texto($row['turma_nome'] ?? '');
            $linha[] = $this->texto($row['ano_letivo'] ?? '');
            $linhas[] = $linha;
        }

        return $this->aba('Dados cadastrais', $cabecalho, $linhas, '');
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     */
    private function ficha(array $ids, array $mapa): array
    {
        if (!$this->tabelaExiste('alunos_ficha_complementar')) {
            return $this->vazio('Ficha complementar', 'Esta escola ainda não tem ficha complementar.');
        }
        $campos = [
            'tipo_sanguineo' => 'Tipo sanguíneo',
            'alergias' => 'Alergias',
            'medicamentos_uso' => 'Medicamentos',
            'condicoes_cronicas' => 'Condições crônicas',
            'deficiencias_obs' => 'Deficiências',
            'plano_saude' => 'Plano de saúde',
            'plano_saude_numero' => 'Número do plano',
            'hospital_referencia' => 'Hospital de referência',
            'contato_emergencia_nome' => 'Emergência — nome',
            'contato_emergencia_telefone' => 'Emergência — telefone',
            'contato_emergencia_parentesco' => 'Emergência — parentesco',
            'restricoes_alimentares' => 'Restrições alimentares',
            'alimentacao_obs' => 'Alimentação',
            'usa_transporte_escolar' => 'Usa transporte',
            'transporte_tipo' => 'Transporte',
            'transporte_rota' => 'Rota',
            'transporte_ponto' => 'Ponto',
            'transporte_responsavel' => 'Responsável do transporte',
            'transporte_telefone' => 'Telefone do transporte',
            'observacoes_gerais' => 'Observações',
        ];
        $cols = $this->colunas('alunos_ficha_complementar');
        $select = ['f.aluno_id'];
        $cabecalho = ['Aluno', 'RA', 'Turma'];
        foreach ($campos as $coluna => $rotulo) {
            if (!isset($cols[$coluna])) {
                continue;
            }
            $select[] = 'f.' . $coluna;
            $cabecalho[] = $rotulo;
        }
        $params = [];
        $in = $this->clausulaIn('f.aluno_id', $ids, 'ficha', $params);
        $rows = $this->consultar(
            'SELECT ' . implode(', ', $select) . ' FROM alunos_ficha_complementar f WHERE ' . $in,
            $params
        );
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $linha = [$this->texto($aluno['nome'] ?? ''), $this->texto($aluno['ra'] ?? ''), $this->texto($aluno['turma'] ?? '')];
            foreach ($campos as $coluna => $_rotulo) {
                if (!isset($cols[$coluna])) {
                    continue;
                }
                $linha[] = $coluna === 'usa_transporte_escolar'
                    ? $this->simNao($row[$coluna] ?? '')
                    : $this->texto($row[$coluna] ?? '');
            }
            $linhas[] = $linha;
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Ficha complementar', $cabecalho, $linhas, '');
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     */
    private function responsaveis(array $ids, array $mapa): array
    {
        $cabecalho = ['Aluno', 'RA', 'Turma', 'Responsável', 'Vínculo', 'Financeiro', 'CPF', 'Telefone', 'Celular', 'E-mail', 'Cidade', 'UF'];
        if (!$this->tabelaExiste('alunos_responsaveis') || !$this->tabelaExiste('responsaveis')) {
            return $this->responsavelUnico($ids, $cabecalho);
        }
        $params = [];
        $in = $this->clausulaIn('ar.aluno_id', $ids, 'resp', $params);
        $ativo = isset($this->colunas('alunos_responsaveis')['ativo']) ? ' AND ar.ativo = 1' : '';
        $rows = $this->consultar(
            'SELECT ar.aluno_id, ar.tipo_vinculo, ar.is_financeiro, r.nome, r.cpf, r.telefone, r.celular, r.email, r.cidade, r.uf
             FROM alunos_responsaveis ar
             INNER JOIN responsaveis r ON r.id = ar.responsavel_id
             WHERE ' . $in . $ativo . '
             ORDER BY r.nome ASC',
            $params
        );
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                $this->texto($row['nome'] ?? ''),
                $this->texto($row['tipo_vinculo'] ?? ''),
                $this->simNao($row['is_financeiro'] ?? ''),
                $this->texto($row['cpf'] ?? ''),
                $this->texto($row['telefone'] ?? ''),
                $this->texto($row['celular'] ?? ''),
                $this->texto($row['email'] ?? ''),
                $this->texto($row['cidade'] ?? ''),
                $this->texto($row['uf'] ?? ''),
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Responsáveis', $cabecalho, $linhas, '');
    }

    /**
     * @param list<int> $ids
     * @param list<string> $cabecalho
     */
    private function responsavelUnico(array $ids, array $cabecalho): array
    {
        if (!isset($this->colunas('alunos')['responsavel_id']) || !$this->tabelaExiste('responsaveis')) {
            return $this->vazio('Responsáveis', 'Esta escola ainda não tem responsáveis vinculados.');
        }
        $params = [];
        $in = $this->clausulaIn('a.id', $ids, 'respu', $params);
        $rows = $this->consultar(
            'SELECT a.nome AS aluno, a.ra, t.nome AS turma, r.nome, r.cpf, r.telefone, r.celular, r.email, r.cidade, r.uf
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             INNER JOIN responsaveis r ON r.id = a.responsavel_id
             WHERE ' . $in . '
             ORDER BY a.nome ASC',
            $params
        );
        $linhas = [];
        foreach ($rows as $row) {
            $linhas[] = [
                $this->texto($row['aluno'] ?? ''),
                $this->texto($row['ra'] ?? ''),
                $this->texto($row['turma'] ?? ''),
                $this->texto($row['nome'] ?? ''),
                '',
                '',
                $this->texto($row['cpf'] ?? ''),
                $this->texto($row['telefone'] ?? ''),
                $this->texto($row['celular'] ?? ''),
                $this->texto($row['email'] ?? ''),
                $this->texto($row['cidade'] ?? ''),
                $this->texto($row['uf'] ?? ''),
            ];
        }

        return $this->aba('Responsáveis', $cabecalho, $linhas, '');
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{ano:int,turma_id:int} $filtros
     */
    private function matricula(array $ids, array $mapa, array $filtros): array
    {
        $cabecalho = ['Aluno', 'RA', 'Turma', 'Ano', 'Início', 'Fim', 'Situação'];
        $linhas = [];
        foreach ($mapa as $aluno) {
            if ($filtros['ano'] > 0 && (int) ($aluno['ano_letivo'] ?? 0) !== $filtros['ano'] && $filtros['turma_id'] <= 0) {
                continue;
            }
            if ($filtros['turma_id'] > 0 && (int) ($aluno['turma_id'] ?? 0) !== $filtros['turma_id']) {
                continue;
            }
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                $this->texto($aluno['ano_letivo'] ?? ''),
                '',
                '',
                'Turma atual',
            ];
        }
        if ($this->tabelaExiste('alunos_turmas_historico')) {
            $params = [];
            $in = $this->clausulaIn('h.aluno_id', $ids, 'mat', $params);
            $extra = '';
            if ($filtros['ano'] > 0 && isset($this->colunas('alunos_turmas_historico')['ano_letivo'])) {
                $extra .= ' AND h.ano_letivo = :ano_mat';
                $params['ano_mat'] = (string) $filtros['ano'];
            }
            if ($filtros['turma_id'] > 0) {
                $extra .= ' AND h.turma_id = :turma_mat';
                $params['turma_mat'] = $filtros['turma_id'];
            }
            $rows = $this->consultar(
                'SELECT h.aluno_id, h.ano_letivo, h.data_inicio, h.data_fim, t.nome AS turma
                 FROM alunos_turmas_historico h
                 LEFT JOIN turmas t ON t.id = h.turma_id
                 WHERE ' . $in . $extra . '
                 ORDER BY h.data_inicio DESC',
                $params
            );
            foreach ($rows as $row) {
                $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
                $fim = $this->dataBr($row['data_fim'] ?? '');
                $linhas[] = [
                    $this->texto($aluno['nome'] ?? ''),
                    $this->texto($aluno['ra'] ?? ''),
                    $this->texto($row['turma'] ?? ''),
                    $this->texto($row['ano_letivo'] ?? ''),
                    $this->dataBr($row['data_inicio'] ?? ''),
                    $fim,
                    $fim === '' ? 'Em aberto' : 'Encerrado',
                ];
            }
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Matrícula e turmas', $cabecalho, $linhas, '');
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     */
    private function documentos(array $ids, array $mapa): array
    {
        if (!$this->tabelaExiste('alunos_documentos')) {
            return $this->vazio('Documentos', 'Esta escola ainda não tem o checklist de documentos.');
        }
        $params = [];
        $in = $this->clausulaIn('d.aluno_id', $ids, 'doc', $params);
        $rows = $this->consultar(
            'SELECT d.aluno_id, d.tipo, d.titulo, d.status, d.arquivo_nome, d.observacao, d.entregue_em
             FROM alunos_documentos d
             WHERE ' . $in . '
             ORDER BY d.entregue_em DESC',
            $params
        );
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                $this->texto($row['tipo'] ?? ''),
                $this->texto($row['titulo'] ?? ''),
                $this->texto($row['status'] ?? ''),
                $this->texto($row['arquivo_nome'] ?? ''),
                $this->texto($row['observacao'] ?? ''),
                $this->dataHoraBr($row['entregue_em'] ?? ''),
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Documentos', ['Aluno', 'RA', 'Turma', 'Tipo', 'Título', 'Situação', 'Arquivo', 'Observação', 'Entregue em'], $linhas, '');
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{ano:int,turma_id:int} $filtros
     */
    private function chamada(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('alunos_turma_chamada')) {
            return $this->vazio('Lista de chamada', 'Esta escola ainda não tem lista de chamada.');
        }
        $params = [];
        $in = $this->clausulaIn('c.aluno_id', $ids, 'ch', $params);
        $extra = '';
        if ($filtros['turma_id'] > 0) {
            $extra .= ' AND c.turma_id = :turma_ch';
            $params['turma_ch'] = $filtros['turma_id'];
        }
        if ($filtros['ano'] > 0 && $this->tabelaExiste('ano_letivo')) {
            $extra .= ' AND al.ano = :ano_ch';
            $params['ano_ch'] = $filtros['ano'];
        }
        $rows = $this->consultar(
            'SELECT c.aluno_id, c.numero_chamada, c.entrada_tardia, c.marcado_tr, c.data_entrada_turma, t.nome AS turma, al.ano
             FROM alunos_turma_chamada c
             LEFT JOIN turmas t ON t.id = c.turma_id
             LEFT JOIN ano_letivo al ON al.id = c.ano_letivo_id
             WHERE ' . $in . $extra . '
             ORDER BY t.nome ASC, c.numero_chamada ASC',
            $params
        );
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($row['turma'] ?? ''),
                $this->texto($row['ano'] ?? ''),
                $this->texto($row['numero_chamada'] ?? ''),
                $this->simNao($row['entrada_tardia'] ?? ''),
                $this->simNao($row['marcado_tr'] ?? ''),
                $this->dataBr($row['data_entrada_turma'] ?? ''),
            ];
        }

        return $this->aba('Lista de chamada', ['Aluno', 'RA', 'Turma', 'Ano', 'Número', 'Entrada tardia', 'Transferido', 'Entrada na turma'], $linhas, '');
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{ano:int} $filtros
     */
    private function notas(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('boletim_resultados_gerados')) {
            return $this->vazio('Notas', 'Ainda não há boletim gerado nesta escola.');
        }
        $cols = $this->colunas('boletim_resultados_gerados');
        $params = [];
        $in = $this->clausulaIn('g.aluno_id', $ids, 'nota', $params);
        $where = [$in];
        if (isset($cols['vigente'])) {
            $where[] = 'g.vigente = 1';
        }
        if (isset($cols['preview'])) {
            $where[] = 'g.preview = 0';
        }
        $joinRegra = $this->tabelaExiste('boletim_regras') ? 'LEFT JOIN boletim_regras br ON br.id = g.regra_id' : '';
        $anoSql = $joinRegra !== '' ? 'br.ano_letivo' : 'NULL';
        if ($filtros['ano'] > 0 && $joinRegra !== '') {
            $where[] = 'br.ano_letivo = :ano_nota';
            $params['ano_nota'] = $filtros['ano'];
        }
        $ordem = isset($cols['ordem_linha']) ? 'g.ordem_linha ASC, ' : '';
        $rows = $this->consultar(
            'SELECT g.aluno_id, g.periodo_ref, g.materia_nome, g.media_final, g.notas_json, ' . $anoSql . ' AS ano_regra
             FROM boletim_resultados_gerados g
             ' . $joinRegra . '
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY g.periodo_ref ASC, ' . $ordem . 'g.materia_nome ASC
             LIMIT ' . (self::LIMITE_LINHAS + 1),
            $params
        );
        $aviso = $this->avisoLimite($rows);
        $chaves = [];
        $decodificadas = [];
        foreach ($rows as $i => $row) {
            $json = json_decode((string) ($row['notas_json'] ?? ''), true);
            $json = is_array($json) ? $json : [];
            $decodificadas[$i] = $json;
            foreach ($json as $codigo => $valor) {
                $codigo = (string) $codigo;
                if ($codigo === '' || is_array($valor) || is_object($valor)) {
                    continue;
                }
                $chaves[$codigo] = true;
            }
        }
        $principais = array_slice(array_keys($chaves), 0, 16);
        $temDemais = count($chaves) > count($principais);
        $cabecalho = array_merge(['Aluno', 'RA', 'Turma', 'Ano', 'Período', 'Componente', 'Média'], $principais);
        if ($temDemais) {
            $cabecalho[] = 'Demais notas';
        }
        $linhas = [];
        foreach ($rows as $i => $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $json = $decodificadas[$i] ?? [];
            $linha = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                $this->texto($row['ano_regra'] ?? ''),
                $this->texto($row['periodo_ref'] ?? ''),
                $this->texto($row['materia_nome'] ?? ''),
                $this->numero($row['media_final'] ?? ''),
            ];
            foreach ($principais as $codigo) {
                $linha[] = $this->valorNota($json[$codigo] ?? '');
            }
            if ($temDemais) {
                $resto = [];
                foreach ($json as $codigo => $valor) {
                    if (is_array($valor) || is_object($valor) || in_array((string) $codigo, $principais, true)) {
                        continue;
                    }
                    $resto[] = (string) $codigo . ': ' . $this->valorNota($valor);
                }
                $linha[] = implode(' | ', $resto);
            }
            $linhas[] = $linha;
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Notas', $cabecalho, $linhas, $aviso);
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{ano:int,turma_id:int} $filtros
     */
    private function resultado(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('resultado_academico')) {
            return $this->vazio('Resultado final', 'Ainda não há resultado final nesta escola.');
        }
        $params = [];
        $in = $this->clausulaIn('r.aluno_id', $ids, 'res', $params);
        $extra = '';
        if ($filtros['ano'] > 0) {
            $extra .= ' AND r.ano_letivo = :ano_res';
            $params['ano_res'] = $filtros['ano'];
        }
        if ($filtros['turma_id'] > 0) {
            $extra .= ' AND r.turma_id = :turma_res';
            $params['turma_res'] = $filtros['turma_id'];
        }
        $rows = $this->consultar(
            'SELECT r.aluno_id, r.ano_letivo, r.periodo_tipo, r.periodo_numero, r.status, r.situacao, r.rotulo,
                    r.media_final, r.frequencia_percentual, r.faltas, r.homologado_em, t.nome AS turma
             FROM resultado_academico r
             LEFT JOIN turmas t ON t.id = r.turma_id
             WHERE ' . $in . $extra . '
             ORDER BY r.ano_letivo DESC, r.periodo_numero ASC
             LIMIT ' . (self::LIMITE_LINHAS + 1),
            $params
        );
        $aviso = $this->avisoLimite($rows);
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $periodo = trim((string) ($row['periodo_tipo'] ?? ''));
            $numero = (int) ($row['periodo_numero'] ?? 0);
            if ($numero > 0) {
                $periodo .= ' ' . $numero;
            }
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($row['turma'] ?? ''),
                $this->texto($row['ano_letivo'] ?? ''),
                $periodo,
                $this->texto($row['situacao'] ?? ''),
                $this->texto($row['rotulo'] ?? ''),
                $this->numero($row['media_final'] ?? ''),
                $this->numero($row['frequencia_percentual'] ?? ''),
                $this->texto($row['faltas'] ?? ''),
                $this->texto($row['status'] ?? ''),
                $this->dataHoraBr($row['homologado_em'] ?? ''),
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Resultado final', ['Aluno', 'RA', 'Turma', 'Ano', 'Período', 'Situação', 'Rótulo', 'Média', 'Frequência %', 'Faltas', 'Status', 'Homologado em'], $linhas, $aviso);
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{data_inicio:string,data_fim:string,turma_id:int} $filtros
     */
    private function faltasResumo(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('diario_frequencias') || !$this->tabelaExiste('diario_aulas')) {
            return $this->vazio('Frequência', 'O diário de classe ainda não tem frequência lançada.');
        }
        $params = [];
        $in = $this->clausulaIn('df.aluno_id', $ids, 'fr', $params);
        [$extra, $params] = $this->filtroDiario($filtros, $params, 'fr');
        $rows = $this->consultar(
            'SELECT df.aluno_id,
                    SUM(CASE WHEN df.situacao = \'presente\' THEN 1 ELSE 0 END) AS presentes,
                    SUM(CASE WHEN df.situacao = \'falta\' THEN 1 ELSE 0 END) AS faltas,
                    SUM(CASE WHEN df.situacao = \'falta_justificada\' THEN 1 ELSE 0 END) AS justificadas,
                    SUM(CASE WHEN df.situacao = \'atraso\' THEN 1 ELSE 0 END) AS atrasos,
                    SUM(CASE WHEN df.situacao = \'saida_antecipada\' THEN 1 ELSE 0 END) AS saidas,
                    COUNT(*) AS aulas
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE ' . $in . $extra . '
             GROUP BY df.aluno_id',
            $params
        );
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $aulas = (int) ($row['aulas'] ?? 0);
            $presentes = (int) ($row['presentes'] ?? 0);
            $percentual = $aulas > 0 ? number_format(($presentes / $aulas) * 100, 1, '.', '') : '';
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                (string) $aulas,
                (string) $presentes,
                (string) (int) ($row['faltas'] ?? 0),
                (string) (int) ($row['justificadas'] ?? 0),
                (string) (int) ($row['atrasos'] ?? 0),
                (string) (int) ($row['saidas'] ?? 0),
                $percentual,
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Frequência', ['Aluno', 'RA', 'Turma', 'Aulas', 'Presentes', 'Faltas', 'Justificadas', 'Atrasos', 'Saídas', '% presença'], $linhas, '');
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{data_inicio:string,data_fim:string,turma_id:int} $filtros
     */
    private function faltas(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('diario_frequencias') || !$this->tabelaExiste('diario_aulas')) {
            return $this->vazio('Faltas', 'O diário de classe ainda não tem frequência lançada.');
        }
        $params = [];
        $in = $this->clausulaIn('df.aluno_id', $ids, 'fa', $params);
        [$extra, $params] = $this->filtroDiario($filtros, $params, 'fa');
        $joinMateria = $this->tabelaExiste('materias') && isset($this->colunas('materias')['nome'])
            ? 'LEFT JOIN materias m ON m.id = da.materia_id'
            : '';
        $materia = $joinMateria !== '' ? 'm.nome' : "''";
        $obs = isset($this->colunas('diario_frequencias')['observacao']) ? 'df.observacao' : "''";
        $rows = $this->consultar(
            'SELECT df.aluno_id, df.situacao, ' . $obs . ' AS observacao, da.data_aula, t.nome AS turma, ' . $materia . ' AS materia
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             LEFT JOIN turmas t ON t.id = da.turma_id
             ' . $joinMateria . '
             WHERE ' . $in . $extra . ' AND df.situacao <> \'presente\'
             ORDER BY da.data_aula DESC
             LIMIT ' . (self::LIMITE_LINHAS + 1),
            $params
        );
        $aviso = $this->avisoLimite($rows);
        $rotulos = [
            'falta' => 'Falta',
            'falta_justificada' => 'Falta justificada',
            'atraso' => 'Atraso',
            'saida_antecipada' => 'Saída antecipada',
        ];
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $situacao = (string) ($row['situacao'] ?? '');
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($row['turma'] ?? ''),
                $this->dataBr($row['data_aula'] ?? ''),
                $this->texto($row['materia'] ?? ''),
                $rotulos[$situacao] ?? $situacao,
                $this->texto($row['observacao'] ?? ''),
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Faltas', ['Aluno', 'RA', 'Turma', 'Data', 'Componente', 'Situação', 'Observação'], $linhas, $aviso);
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{ano:int,turma_id:int,data_inicio:string,data_fim:string} $filtros
     */
    private function ocorrencias(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('alunos_ocorrencias')) {
            return $this->vazio('Ocorrências', 'Esta escola ainda não tem ocorrências.');
        }
        $cols = $this->colunas('alunos_ocorrencias');
        $params = [];
        $in = $this->clausulaIn('o.aluno_id', $ids, 'oc', $params);
        $extra = '';
        if ($filtros['data_inicio'] !== '') {
            $extra .= ' AND o.data_ocorrencia >= :oc_ini';
            $params['oc_ini'] = $filtros['data_inicio'] . ' 00:00:00';
        }
        if ($filtros['data_fim'] !== '') {
            $extra .= ' AND o.data_ocorrencia <= :oc_fim';
            $params['oc_fim'] = $filtros['data_fim'] . ' 23:59:59';
        }
        if ($filtros['turma_id'] > 0 && isset($cols['turma_id'])) {
            $extra .= ' AND (o.turma_id = :oc_turma OR o.turma_id IS NULL)';
            $params['oc_turma'] = $filtros['turma_id'];
        }
        if ($filtros['ano'] > 0 && $filtros['data_inicio'] === '' && $filtros['data_fim'] === '') {
            $extra .= ' AND o.data_ocorrencia >= :oc_ano_ini AND o.data_ocorrencia <= :oc_ano_fim';
            $params['oc_ano_ini'] = $filtros['ano'] . '-01-01 00:00:00';
            $params['oc_ano_fim'] = $filtros['ano'] . '-12-31 23:59:59';
        }
        $categoria = $this->tabelaExiste('ocorrencias_categorias') && isset($cols['categoria_id'])
            ? 'c.nome'
            : "''";
        $joinCat = $categoria !== "''" ? 'LEFT JOIN ocorrencias_categorias c ON c.id = o.categoria_id' : '';
        $status = isset($cols['status']) ? 'o.status' : "''";
        $local = isset($cols['local']) ? 'o.local' : "''";
        $enc = isset($cols['encaminhamento']) ? 'o.encaminhamento' : "''";
        $rows = $this->consultar(
            'SELECT o.aluno_id, o.data_ocorrencia, o.titulo, o.detalhe, o.nivel_gravidade, o.enviar_pais,
                    ' . $status . ' AS status, ' . $local . ' AS local, ' . $enc . ' AS encaminhamento, ' . $categoria . ' AS categoria
             FROM alunos_ocorrencias o
             ' . $joinCat . '
             WHERE ' . $in . $extra . '
             ORDER BY o.data_ocorrencia DESC
             LIMIT ' . (self::LIMITE_LINHAS + 1),
            $params
        );
        $aviso = $this->avisoLimite($rows);
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                $this->dataHoraBr($row['data_ocorrencia'] ?? ''),
                $this->texto($row['categoria'] ?? ''),
                $this->texto($row['titulo'] ?? ''),
                $this->texto($row['nivel_gravidade'] ?? ''),
                $this->texto($row['status'] ?? ''),
                $this->texto($row['local'] ?? ''),
                $this->simNao($row['enviar_pais'] ?? ''),
                $this->texto($row['detalhe'] ?? ''),
                $this->texto($row['encaminhamento'] ?? ''),
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Ocorrências', ['Aluno', 'RA', 'Turma', 'Data', 'Categoria', 'Título', 'Gravidade', 'Status', 'Local', 'Avisou a família', 'Detalhe', 'Encaminhamento'], $linhas, $aviso);
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{data_inicio:string,data_fim:string,ano:int} $filtros
     */
    private function redacoes(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('redacoes')) {
            return $this->vazio('Redações', 'Esta escola ainda não tem redações.');
        }
        $cols = $this->colunas('redacoes');
        $params = [];
        $in = $this->clausulaIn('r.aluno_id', $ids, 'red', $params);
        $extra = '';
        if (isset($cols['eh_rascunho'])) {
            $extra .= ' AND (r.eh_rascunho = 0 OR r.eh_rascunho IS NULL)';
        }
        [$extraData, $params] = $this->filtroData($filtros, $params, 'r.created_at', 'red');
        $notaUsada = isset($cols['nota_final_utilizada']) ? 'r.nota_final_utilizada' : 'NULL';
        $notaProf = isset($cols['nota_final_professor']) ? 'r.nota_final_professor' : 'NULL';
        $notaIa = isset($cols['nota_final']) ? 'r.nota_final' : (isset($cols['nota']) ? 'r.nota' : 'NULL');
        $tema = isset($cols['tema']) ? 'r.tema' : (isset($cols['titulo']) ? 'r.titulo' : "''");
        $rows = $this->consultar(
            'SELECT r.aluno_id, r.created_at, ' . $tema . ' AS tema, ' . $notaUsada . ' AS nota_usada, '
            . $notaProf . ' AS nota_professor, ' . $notaIa . ' AS nota_ia
             FROM redacoes r
             WHERE ' . $in . $extra . $extraData . '
             ORDER BY r.created_at DESC
             LIMIT ' . (self::LIMITE_LINHAS + 1),
            $params
        );
        $aviso = $this->avisoLimite($rows);
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                $this->dataHoraBr($row['created_at'] ?? ''),
                $this->texto($row['tema'] ?? ''),
                $this->numero($row['nota_usada'] ?? ''),
                $this->numero($row['nota_professor'] ?? ''),
                $this->numero($row['nota_ia'] ?? ''),
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Redações', ['Aluno', 'RA', 'Turma', 'Data', 'Tema', 'Nota utilizada', 'Nota do professor', 'Nota da IA'], $linhas, $aviso);
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{data_inicio:string,data_fim:string,ano:int,turma_id:int} $filtros
     */
    private function provas(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('provas_realizacoes') || !$this->tabelaExiste('provas')) {
            return $this->vazio('Provas online', 'Esta escola ainda não tem provas online.');
        }
        $params = [];
        $in = $this->clausulaIn('pr.aluno_id', $ids, 'prv', $params);
        $extra = '';
        if ($filtros['turma_id'] > 0) {
            $extra .= ' AND (p.turma_id = :prv_turma OR p.turma_id IS NULL)';
            $params['prv_turma'] = $filtros['turma_id'];
        }
        [$extraData, $params] = $this->filtroData($filtros, $params, 'pr.iniciado_em', 'prv');
        $joinMateria = $this->tabelaExiste('materias') && isset($this->colunas('materias')['nome'])
            ? 'LEFT JOIN materias m ON m.id = p.materia_id'
            : '';
        $materia = $joinMateria !== '' ? 'm.nome' : "''";
        $rows = $this->consultar(
            'SELECT pr.aluno_id, p.titulo, pr.nota, pr.status, pr.iniciado_em, pr.finalizado_em, ' . $materia . ' AS materia
             FROM provas_realizacoes pr
             INNER JOIN provas p ON p.id = pr.prova_id
             ' . $joinMateria . '
             WHERE ' . $in . $extra . $extraData . '
             ORDER BY pr.iniciado_em DESC
             LIMIT ' . (self::LIMITE_LINHAS + 1),
            $params
        );
        $aviso = $this->avisoLimite($rows);
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                $this->texto($row['titulo'] ?? ''),
                $this->texto($row['materia'] ?? ''),
                $this->numero($row['nota'] ?? ''),
                $this->texto($row['status'] ?? ''),
                $this->dataHoraBr($row['iniciado_em'] ?? ''),
                $this->dataHoraBr($row['finalizado_em'] ?? ''),
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Provas online', ['Aluno', 'RA', 'Turma', 'Prova', 'Componente', 'Nota', 'Situação', 'Início', 'Fim'], $linhas, $aviso);
    }

    /**
     * @param list<int> $ids
     * @param array<int,array<string,mixed>> $mapa
     * @param array{data_inicio:string,data_fim:string,ano:int} $filtros
     */
    private function exercicios(array $ids, array $mapa, array $filtros): array
    {
        if (!$this->tabelaExiste('exercicios_historico')) {
            return $this->vazio('Exercícios', 'Esta escola ainda não tem histórico de exercícios.');
        }
        $params = [];
        $in = $this->clausulaIn('h.aluno_id', $ids, 'ex', $params);
        [$extraData, $params] = $this->filtroData($filtros, $params, 'h.data_execucao', 'ex');
        $join = $this->tabelaExiste('listas_exercicios') ? 'LEFT JOIN listas_exercicios le ON le.id = h.lista_id' : '';
        $titulo = $join !== '' && isset($this->colunas('listas_exercicios')['titulo']) ? 'le.titulo' : "''";
        $materia = $join !== '' && isset($this->colunas('listas_exercicios')['materia']) ? 'le.materia' : "''";
        $rows = $this->consultar(
            'SELECT h.aluno_id, h.data_execucao, h.questoes_corretas, h.questoes_total, h.percentual_acerto, h.status,
                    ' . $titulo . ' AS titulo, ' . $materia . ' AS materia
             FROM exercicios_historico h
             ' . $join . '
             WHERE ' . $in . $extraData . '
             ORDER BY h.data_execucao DESC
             LIMIT ' . (self::LIMITE_LINHAS + 1),
            $params
        );
        $aviso = $this->avisoLimite($rows);
        $linhas = [];
        foreach ($rows as $row) {
            $aluno = $mapa[(int) ($row['aluno_id'] ?? 0)] ?? [];
            $linhas[] = [
                $this->texto($aluno['nome'] ?? ''),
                $this->texto($aluno['ra'] ?? ''),
                $this->texto($aluno['turma'] ?? ''),
                $this->dataHoraBr($row['data_execucao'] ?? ''),
                $this->texto($row['titulo'] ?? ''),
                $this->texto($row['materia'] ?? ''),
                $this->texto($row['questoes_corretas'] ?? ''),
                $this->texto($row['questoes_total'] ?? ''),
                $this->numero($row['percentual_acerto'] ?? ''),
                $this->texto($row['status'] ?? ''),
            ];
        }
        $this->ordenarPorAluno($linhas);

        return $this->aba('Exercícios', ['Aluno', 'RA', 'Turma', 'Data', 'Lista', 'Componente', 'Acertos', 'Questões', '% acerto', 'Situação'], $linhas, $aviso);
    }

    /**
     * @param array{ano:int,turma_id:int,busca:string,data_inicio:string,data_fim:string,incluir_inativos:bool,bancos:list<string>} $filtros
     * @return list<array<string,mixed>>
     */
    private function alunos(array $filtros): array
    {
        if (!$this->tabelaExiste('alunos')) {
            throw new \RuntimeException('O cadastro de alunos não está disponível nesta escola.');
        }
        $cols = $this->colunas('alunos');
        $where = ['1=1'];
        $params = [];
        if (!$filtros['incluir_inativos'] && isset($cols['ativo'])) {
            $where[] = 'a.ativo = 1';
        }
        $temHistorico = $this->tabelaExiste('alunos_turmas_historico');
        if ($filtros['turma_id'] > 0) {
            if ($temHistorico) {
                $where[] = '(a.turma_id = :turma_id OR EXISTS (SELECT 1 FROM alunos_turmas_historico h WHERE h.aluno_id = a.id AND h.turma_id = :turma_hist))';
                $params['turma_id'] = $filtros['turma_id'];
                $params['turma_hist'] = $filtros['turma_id'];
            } else {
                $where[] = 'a.turma_id = :turma_id';
                $params['turma_id'] = $filtros['turma_id'];
            }
        }
        if ($filtros['ano'] > 0) {
            $partes = ['t.ano_letivo = :ano_turma'];
            $params['ano_turma'] = $filtros['ano'];
            if ($temHistorico && isset($this->colunas('alunos_turmas_historico')['ano_letivo'])) {
                $partes[] = 'EXISTS (SELECT 1 FROM alunos_turmas_historico h2 WHERE h2.aluno_id = a.id AND h2.ano_letivo = :ano_hist)';
                $params['ano_hist'] = (string) $filtros['ano'];
            }
            $where[] = '(' . implode(' OR ', $partes) . ')';
        }
        if ($filtros['busca'] !== '') {
            $buscas = ['a.nome LIKE :busca'];
            $params['busca'] = '%' . $filtros['busca'] . '%';
            if (isset($cols['ra'])) {
                $buscas[] = 'a.ra LIKE :busca_ra';
                $params['busca_ra'] = '%' . $filtros['busca'] . '%';
            }
            if (isset($cols['codigo_aluno'])) {
                $buscas[] = 'a.codigo_aluno LIKE :busca_cod';
                $params['busca_cod'] = '%' . $filtros['busca'] . '%';
            }
            $where[] = '(' . implode(' OR ', $buscas) . ')';
        }
        $sqlWhere = implode(' AND ', $where);
        $total = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id WHERE ' . $sqlWhere,
            $params
        );
        $quantidade = (int) ($total['total'] ?? 0);
        if ($quantidade === 0) {
            throw new \RuntimeException('Nenhum aluno encontrado com esses filtros.');
        }
        if ($quantidade > self::LIMITE_ALUNOS) {
            throw new \RuntimeException('Há ' . $quantidade . ' alunos nesse recorte. Escolha uma turma ou um nome para gerar o arquivo.');
        }
        $ra = isset($cols['ra']) ? 'a.ra' : "''";
        $turmaId = isset($cols['turma_id']) ? 'a.turma_id' : '0';

        return $this->consultar(
            'SELECT a.id, a.nome, ' . $ra . ' AS ra, ' . $turmaId . ' AS turma_id, t.nome AS turma, t.ano_letivo
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE ' . $sqlWhere . '
             ORDER BY a.nome ASC',
            $params
        );
    }

    /**
     * @param array{ano:int,turma_id:int,busca:string,data_inicio:string,data_fim:string,incluir_inativos:bool,bancos:list<string>} $filtros
     * @param list<array{nome:string,linhas:list<list<string>>}> $abas
     * @return array{nome:string,colunas:list<string>,linhas:list<list<string>>,aviso:string}
     */
    private function capa(array $filtros, int $alunos, array $abas): array
    {
        $turma = 'Todas';
        if ($filtros['turma_id'] > 0) {
            $row = $this->db->fetch('SELECT nome, ano_letivo FROM turmas WHERE id = :id', ['id' => $filtros['turma_id']]);
            $turma = trim((string) (($row['nome'] ?? '') . ' ' . ($row['ano_letivo'] ?? '')));
            if ($turma === '') {
                $turma = (string) $filtros['turma_id'];
            }
        }
        $periodo = 'Todo o período disponível';
        if ($filtros['data_inicio'] !== '' || $filtros['data_fim'] !== '') {
            $periodo = ($filtros['data_inicio'] !== '' ? $this->dataBr($filtros['data_inicio']) : '…')
                . ' a '
                . ($filtros['data_fim'] !== '' ? $this->dataBr($filtros['data_fim']) : '…');
        } elseif ($filtros['ano'] > 0) {
            $periodo = 'Ano ' . $filtros['ano'];
        }
        $linhas = [
            ['Escola', $this->nomeEscola()],
            ['Gerado em', date('d/m/Y H:i')],
            ['Alunos no recorte', (string) $alunos],
            ['Ano letivo', $filtros['ano'] > 0 ? (string) $filtros['ano'] : 'Todos'],
            ['Turma', $turma],
            ['Busca', $filtros['busca'] !== '' ? $filtros['busca'] : '—'],
            ['Período', $periodo],
            ['Inativos', $filtros['incluir_inativos'] ? 'Incluídos' : 'Fora do arquivo'],
            ['', ''],
            ['Banco', 'Linhas'],
        ];
        foreach ($abas as $aba) {
            $linhas[] = [(string) ($aba['nome'] ?? ''), (string) count($aba['linhas'] ?? [])];
        }

        return $this->aba('Capa', ['Item', 'Valor'], $linhas, 'Senhas e sessões não entram neste arquivo.');
    }

    /**
     * @param array{data_inicio:string,data_fim:string,ano:int,turma_id:int} $filtros
     * @param array<string,mixed> $params
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filtroDiario(array $filtros, array $params, string $prefixo): array
    {
        $extra = '';
        if ($filtros['turma_id'] > 0) {
            $extra .= ' AND da.turma_id = :' . $prefixo . '_turma';
            $params[$prefixo . '_turma'] = $filtros['turma_id'];
        }
        $inicio = $filtros['data_inicio'];
        $fim = $filtros['data_fim'];
        if ($inicio === '' && $fim === '' && $filtros['ano'] > 0) {
            $inicio = $filtros['ano'] . '-01-01';
            $fim = $filtros['ano'] . '-12-31';
        }
        if ($inicio !== '') {
            $extra .= ' AND da.data_aula >= :' . $prefixo . '_ini';
            $params[$prefixo . '_ini'] = $inicio;
        }
        if ($fim !== '') {
            $extra .= ' AND da.data_aula <= :' . $prefixo . '_fim';
            $params[$prefixo . '_fim'] = $fim;
        }

        return [$extra, $params];
    }

    /**
     * @param array{data_inicio:string,data_fim:string,ano:int} $filtros
     * @param array<string,mixed> $params
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filtroData(array $filtros, array $params, string $coluna, string $prefixo): array
    {
        $extra = '';
        $inicio = $filtros['data_inicio'];
        $fim = $filtros['data_fim'];
        if ($inicio === '' && $fim === '' && $filtros['ano'] > 0) {
            $inicio = $filtros['ano'] . '-01-01';
            $fim = $filtros['ano'] . '-12-31';
        }
        if ($inicio !== '') {
            $extra .= ' AND ' . $coluna . ' >= :' . $prefixo . '_ini';
            $params[$prefixo . '_ini'] = $inicio . ' 00:00:00';
        }
        if ($fim !== '') {
            $extra .= ' AND ' . $coluna . ' <= :' . $prefixo . '_fim';
            $params[$prefixo . '_fim'] = $fim . ' 23:59:59';
        }

        return [$extra, $params];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function avisoLimite(array &$rows): string
    {
        if (count($rows) <= self::LIMITE_LINHAS) {
            return '';
        }
        $rows = array_slice($rows, 0, self::LIMITE_LINHAS);

        return 'Este banco passou de ' . self::LIMITE_LINHAS . ' linhas. O arquivo traz as primeiras. Afine a turma ou o período para ver o restante.';
    }

    /**
     * @param list<int> $ids
     * @param array<string,mixed> $params
     */
    private function clausulaIn(string $coluna, array $ids, string $prefixo, array &$params): string
    {
        $holders = [];
        foreach (array_values($ids) as $i => $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $chave = $prefixo . '_' . $i;
            $holders[] = ':' . $chave;
            $params[$chave] = $id;
        }
        if ($holders === []) {
            return '1=0';
        }

        return $coluna . ' IN (' . implode(',', $holders) . ')';
    }

    /**
     * @param list<string> $colunas
     * @param list<list<string>> $linhas
     * @return array{nome:string,colunas:list<string>,linhas:list<list<string>>,aviso:string}
     */
    private function aba(string $nome, array $colunas, array $linhas, string $aviso): array
    {
        return [
            'nome' => $nome,
            'colunas' => $colunas,
            'linhas' => $linhas,
            'aviso' => $aviso,
        ];
    }

    /**
     * @return array{nome:string,colunas:list<string>,linhas:list<list<string>>,aviso:string}
     */
    private function vazio(string $nome, string $aviso): array
    {
        return $this->aba($nome, ['Aviso'], [], $aviso);
    }

    private function titulo(string $chave): string
    {
        return self::BANCOS[$chave]['titulo'] ?? 'Banco';
    }

    /**
     * @param list<list<string>> $linhas
     */
    private function ordenarPorAluno(array &$linhas): void
    {
        usort($linhas, static function (array $a, array $b): int {
            return strcasecmp((string) ($a[0] ?? ''), (string) ($b[0] ?? ''));
        });
    }

    private function formatarCampo(string $coluna, mixed $valor): string
    {
        if ($coluna === 'data_nasc') {
            return $this->dataBr($valor);
        }
        if ($coluna === 'sexo') {
            return match (strtoupper(trim((string) $valor))) {
                'M' => 'Masculino',
                'F' => 'Feminino',
                'N' => 'Não informado',
                default => $this->texto($valor),
            };
        }
        if ($coluna === 'status') {
            return match (strtoupper(trim((string) $valor))) {
                'ACTIVE' => 'Ativo',
                'INACTIVE' => 'Inativo',
                'GRADUATED' => 'Concluído',
                'SUSPENDED' => 'Suspenso',
                default => $this->texto($valor),
            };
        }
        if ($coluna === 'ativo' || $coluna === 'pagante') {
            return $this->simNao($valor);
        }

        return $this->texto($valor);
    }

    private function valorNota(mixed $valor): string
    {
        if (is_array($valor) || is_object($valor)) {
            return '';
        }
        if (is_numeric($valor)) {
            return $this->numero($valor);
        }

        return $this->texto($valor);
    }

    private function numero(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (!is_numeric($valor)) {
            return $this->texto($valor);
        }

        return rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');
    }

    private function simNao(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return ((int) $valor) === 1 ? 'Sim' : 'Não';
    }

    private function dataBr(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '' || str_starts_with($texto, '0000')) {
            return '';
        }
        $data = \DateTime::createFromFormat('Y-m-d', substr($texto, 0, 10));
        if ($data === false) {
            return $this->texto($texto);
        }

        return $data->format('d/m/Y');
    }

    private function dataHoraBr(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '' || str_starts_with($texto, '0000')) {
            return '';
        }
        $data = \DateTime::createFromFormat('Y-m-d H:i:s', substr($texto, 0, 19));
        if ($data === false) {
            return $this->dataBr($texto);
        }
        $hora = $data->format('H:i');

        return $data->format('d/m/Y') . ($hora !== '00:00' ? ' ' . $hora : '');
    }

    private function texto(mixed $valor): string
    {
        $texto = trim((string) $valor);
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return $texto;
    }

    private function dataValida(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        $data = \DateTime::createFromFormat('Y-m-d', $valor);

        return ($data !== false && $data->format('Y-m-d') === $valor) ? $valor : '';
    }

    private function nomeEscola(): string
    {
        if (!class_exists(LayoutHelper::class)) {
            return '';
        }
        try {
            foreach (['nome_escola', 'escola_nome', 'school_name', 'titulo', 'nome'] as $chave) {
                $valor = trim((string) LayoutHelper::get($chave, ''));
                if ($valor !== '') {
                    return $valor;
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }

    /**
     * @return array<string, bool>
     */
    private function colunas(string $tabela): array
    {
        if (isset($this->cacheColunas[$tabela])) {
            return $this->cacheColunas[$tabela];
        }
        $cols = [];
        try {
            $rows = $this->db->fetchAll(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela',
                ['tabela' => $tabela]
            );
            foreach ($rows ?: [] as $row) {
                $nome = (string) ($row['COLUMN_NAME'] ?? '');
                if ($nome !== '') {
                    $cols[$nome] = true;
                }
            }
        } catch (\Throwable $e) {
            $cols = [];
        }
        $this->cacheColunas[$tabela] = $cols;

        return $cols;
    }

    private function tabelaExiste(string $tabela): bool
    {
        return $this->colunas($tabela) !== [];
    }

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    private function consultar(string $sql, array $params = []): array
    {
        $rows = $this->db->fetchAll($sql, $params);

        return is_array($rows) ? $rows : [];
    }
}
