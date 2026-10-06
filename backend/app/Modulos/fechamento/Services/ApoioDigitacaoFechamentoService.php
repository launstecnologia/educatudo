<?php
require_once __DIR__ . '/PlanilhaTextoDocumento.php';
require_once __DIR__ . '/../../../Services/ResultadoHomologacaoService.php';
require_once __DIR__ . '/../../../Services/FrequencyService.php';
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';

/**
 * Apoio à digitação do fechamento para SED (SP) e SERE (PR).
 * Gera planilha e TXT legíveis. Não produz arquivo de importação estadual.
 */
class ApoioDigitacaoFechamentoService
{
    public const STATUS = [
        'exportado' => 'Exportado',
        'digitado' => 'Digitado',
        'enviado' => 'Enviado',
        'validado' => 'Validado',
    ];

    private const UFS = ['SP', 'PR'];

    private ResultadoHomologacaoService $homologacao;
    private $db;
    /** @var array<string, bool> */
    private array $colunas = [];
    /** @var array<string, bool> */
    private array $tabelas = [];

    public function __construct(?ResultadoHomologacaoService $homologacao = null)
    {
        $this->homologacao = $homologacao ?? new ResultadoHomologacaoService();
        $this->db = Database::getInstance();
    }

    public function schemaPronto(): bool
    {
        return $this->tabelaExiste('fechamento_apoio_digitacao');
    }

    /**
     * @return array{nome:string,uf:string,inep:string,codigo_estadual:string,disponivel:bool,canal:string}
     */
    public function escola(): array
    {
        $vazia = [
            'nome' => '',
            'uf' => '',
            'inep' => '',
            'codigo_estadual' => '',
            'disponivel' => false,
            'canal' => '',
        ];
        if (!$this->tabelaExiste('unidades')) {
            return $vazia;
        }
        $codigo = $this->colunaExiste('unidades', 'codigo_estadual')
            ? 'codigo_estadual'
            : 'NULL AS codigo_estadual';
        $row = $this->db->fetch(
            "SELECT nome, uf, inep, {$codigo}
             FROM unidades
             WHERE ativo = 1
             ORDER BY (tipo = 'matriz') DESC, id ASC
             LIMIT 1"
        );
        if (!$row) {
            return $vazia;
        }
        $uf = strtoupper(trim((string) ($row['uf'] ?? '')));
        $canal = $uf === 'SP' ? 'SED — São Paulo' : ($uf === 'PR' ? 'SERE — Paraná' : '');

        return [
            'nome' => trim((string) ($row['nome'] ?? '')),
            'uf' => $uf,
            'inep' => trim((string) ($row['inep'] ?? '')),
            'codigo_estadual' => trim((string) ($row['codigo_estadual'] ?? '')),
            'disponivel' => in_array($uf, self::UFS, true),
            'canal' => $canal,
        ];
    }

    /**
     * @param list<array<string,mixed>> $turmas
     * @return list<array<string,mixed>>
     */
    public function completarTurmas(array $turmas): array
    {
        $oficiais = $this->oficiaisDasTurmas($turmas);
        foreach ($turmas as $i => $turma) {
            $id = (int) ($turma['id'] ?? 0);
            $extra = $oficiais[$id] ?? ['numero_classe_oficial' => '', 'codigo_curso_oficial' => ''];
            $turmas[$i]['numero_classe_oficial'] = $extra['numero_classe_oficial'];
            $turmas[$i]['codigo_curso_oficial'] = $extra['codigo_curso_oficial'];
        }
        return $turmas;
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function salvarCodigoEscola(string $codigo): array
    {
        if (!$this->colunaExiste('unidades', 'codigo_estadual')) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_06_fechamento_apoio_digitacao.sql no painel Master.'];
        }
        $codigo = $this->textoCurto($codigo, 30);
        $escola = $this->db->fetch(
            "SELECT id FROM unidades WHERE ativo = 1 ORDER BY (tipo = 'matriz') DESC, id ASC LIMIT 1"
        );
        if (!$escola) {
            return ['success' => false, 'error' => 'Cadastre a unidade matriz antes de informar o código estadual.'];
        }
        $this->db->query(
            'UPDATE unidades SET codigo_estadual = :codigo WHERE id = :id',
            ['codigo' => $codigo !== '' ? $codigo : null, 'id' => (int) $escola['id']]
        );
        return ['success' => true];
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function salvarTurmaOficial(int $turmaId, string $numeroClasse, string $codigoCurso): array
    {
        if ($turmaId <= 0) {
            return ['success' => false, 'error' => 'Turma inválida.'];
        }
        $numeroClasse = $this->textoCurto($numeroClasse, 40);
        $codigoCurso = $this->textoCurto($codigoCurso, 40);
        $sets = [];
        $params = ['id' => $turmaId];
        if ($this->colunaExiste('turmas', 'numero_classe_oficial')) {
            $sets[] = 'numero_classe_oficial = :numero_classe';
            $params['numero_classe'] = $numeroClasse !== '' ? $numeroClasse : null;
        }
        if ($this->colunaExiste('turmas', 'codigo_curso_oficial')) {
            $sets[] = 'codigo_curso_oficial = :codigo_curso';
            $params['codigo_curso'] = $codigoCurso !== '' ? $codigoCurso : null;
        }
        if ($sets === []) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_06_fechamento_apoio_digitacao.sql no painel Master.'];
        }
        $this->db->query(
            'UPDATE turmas SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
        return ['success' => true];
    }

    /**
     * @return array{success:bool,error?:string,id?:int}
     */
    public function emitir(
        int $turmaId,
        int $anoLetivo,
        string $periodoTipo,
        int $periodoNumero,
        int $usuarioId,
        string $usuarioNome
    ): array {
        if (!$this->schemaPronto()) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_06_fechamento_apoio_digitacao.sql no painel Master.'];
        }
        $escola = $this->escola();
        if (!$escola['disponivel']) {
            return ['success' => false, 'error' => 'O apoio à digitação vale para escola de São Paulo ou do Paraná. Confira a UF da unidade matriz.'];
        }
        if (!$this->turmaDoAno($turmaId, $anoLetivo)) {
            return ['success' => false, 'error' => 'Turma não encontrada neste ano letivo.'];
        }
        if (!isset(ResultadoAcademico::PERIODO_TIPOS[$periodoTipo])) {
            $periodoTipo = 'ano';
            $periodoNumero = 0;
        }

        $preview = $this->homologacao->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $documento = $escola['uf'] === 'SP'
            ? $this->documentoSp($escola, $preview)
            : $this->documentoPr($escola, $preview);

        $versao = $this->proximaVersao($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $id = (int) $this->db->insert(
            'INSERT INTO fechamento_apoio_digitacao
                (turma_id, ano_letivo, periodo_tipo, periodo_numero, uf_destino, versao, status, homologada, pendencias_json, usuario_id, usuario_nome)
             VALUES
                (:turma_id, :ano_letivo, :periodo_tipo, :periodo_numero, :uf_destino, :versao, :status, :homologada, :pendencias_json, :usuario_id, :usuario_nome)',
            [
                'turma_id' => $turmaId,
                'ano_letivo' => $anoLetivo,
                'periodo_tipo' => $periodoTipo,
                'periodo_numero' => $periodoNumero,
                'uf_destino' => $escola['uf'],
                'versao' => $versao,
                'status' => 'exportado',
                'homologada' => !empty($documento['homologada']) ? 1 : 0,
                'pendencias_json' => json_encode($documento['pendencias'], JSON_UNESCAPED_UNICODE),
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                'usuario_nome' => $this->textoCurto($usuarioNome, 180) ?: null,
            ]
        );
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Não foi possível registrar a versão.'];
        }

        try {
            $pasta = $this->diretorioBase() . '/' . $anoLetivo;
            $base = $pasta . '/emissao_' . $id . '_turma_' . $turmaId . '_v' . $versao;
            $planilha = PlanilhaTextoDocumento::gravarPlanilha(
                $base . '.xlsx',
                $documento['colunas'],
                $documento['linhas'],
                $documento['orientacao']
            );
            PlanilhaTextoDocumento::gravarTexto($base . '.txt', $documento['txt']);
            $this->db->query(
                'UPDATE fechamento_apoio_digitacao
                 SET arquivo_planilha = :planilha, arquivo_txt = :txt
                 WHERE id = :id',
                [
                    'planilha' => $this->caminhoRelativo($planilha),
                    'txt' => $this->caminhoRelativo($base . '.txt'),
                    'id' => $id,
                ]
            );
        } catch (Throwable $e) {
            $this->db->query('DELETE FROM fechamento_apoio_digitacao WHERE id = :id', ['id' => $id]);
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $anoLetivo, int $turmaId = 0): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        $params = ['ano' => $anoLetivo];
        $filtroTurma = '';
        if ($turmaId > 0) {
            $filtroTurma = ' AND e.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }
        return $this->db->fetchAll(
            "SELECT e.*, t.nome AS turma_nome
             FROM fechamento_apoio_digitacao e
             LEFT JOIN turmas t ON t.id = e.turma_id
             WHERE e.ano_letivo = :ano {$filtroTurma}
             ORDER BY e.id DESC",
            $params
        ) ?: [];
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function atualizarStatus(int $id, string $status, string $protocolo): array
    {
        if (!isset(self::STATUS[$status])) {
            return ['success' => false, 'error' => 'Situação inválida.'];
        }
        $row = $this->buscar($id);
        if (!$row) {
            return ['success' => false, 'error' => 'Versão não encontrada.'];
        }
        if ($status === 'validado') {
            $bloqueio = $this->bloqueioValidacao($row);
            if ($bloqueio !== '') {
                return ['success' => false, 'error' => $bloqueio];
            }
        }
        $protocolo = $this->textoCurto($protocolo, 80);
        $this->db->query(
            'UPDATE fechamento_apoio_digitacao
             SET status = :status, protocolo = :protocolo
             WHERE id = :id',
            [
                'status' => $status,
                'protocolo' => $protocolo !== '' ? $protocolo : null,
                'id' => $id,
            ]
        );
        return ['success' => true];
    }

    /**
     * @return array{caminho:string,nome:string,mime:string}|null
     */
    public function arquivo(int $id, string $formato): ?array
    {
        $row = $this->buscar($id);
        if (!$row) {
            return null;
        }
        $relativo = $formato === 'txt'
            ? (string) ($row['arquivo_txt'] ?? '')
            : (string) ($row['arquivo_planilha'] ?? '');
        $absoluto = $this->caminhoAbsoluto($relativo);
        if ($absoluto === null) {
            return null;
        }
        $ext = strtolower(pathinfo($absoluto, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'text/plain; charset=UTF-8',
        };

        return [
            'caminho' => $absoluto,
            'nome' => basename($absoluto),
            'mime' => $mime,
        ];
    }

    /**
     * @param list<array<string,mixed>>|string $json
     * @return list<array{texto:string,critica:bool}>
     */
    public static function lerPendencias($json): array
    {
        if (is_array($json)) {
            $lista = $json;
        } else {
            $lista = json_decode((string) $json, true);
        }
        if (!is_array($lista)) {
            return [];
        }
        $out = [];
        foreach ($lista as $item) {
            if (!is_array($item)) {
                continue;
            }
            $texto = trim((string) ($item['texto'] ?? ''));
            if ($texto === '') {
                continue;
            }
            $out[] = ['texto' => $texto, 'critica' => !empty($item['critica'])];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $escola
     * @param array<string,mixed> $preview
     * @return array{colunas:list<string>,linhas:list<list<string>>,orientacao:list<string>,txt:list<string>,pendencias:list<array{texto:string,critica:bool}>,homologada:bool}
     */
    private function documentoSp(array $escola, array $preview): array
    {
        $turma = is_array($preview['turma'] ?? null) ? $preview['turma'] : [];
        $periodo = is_array($preview['periodo'] ?? null) ? $preview['periodo'] : [];
        $linhasPreview = is_array($preview['linhas'] ?? null) ? $preview['linhas'] : [];
        $ids = $this->idsAlunos($linhasPreview);
        $extras = $this->identificadoresAlunos($ids);
        $resumo = is_array($preview['resumo'] ?? null) ? $preview['resumo'] : [];
        $homologada = (int) ($resumo['total'] ?? 0) > 0
            && (int) ($resumo['homologados'] ?? 0) === (int) ($resumo['total'] ?? 0);

        $pendencias = [];
        if ($escola['codigo_estadual'] === '') {
            $pendencias[] = $this->pendencia('CIE da escola não informado.', true);
        }
        $numeroClasse = trim((string) ($turma['numero_classe_oficial'] ?? ''));
        if ($numeroClasse === '') {
            $pendencias[] = $this->pendencia('Número oficial da classe na SED não informado.', true);
        }
        if (!$homologada) {
            $pendencias[] = $this->pendencia('Turma ainda não homologada por completo. Este arquivo é prévia.', true);
        }

        $colunas = [
            'Nº sugerido',
            'Nome',
            'RA',
            'Dígito do RA',
            'UF do RA',
            'Movimentação',
            'Rendimento sugerido',
            'Código sugerido na SED',
            'Concluinte (conferir)',
            'Digitado em',
            'Conferido por',
            'Pendência',
        ];
        $linhas = [];
        $blocos = [];
        $ordem = 0;
        foreach ($linhasPreview as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $aluno = is_array($linha['aluno'] ?? null) ? $linha['aluno'] : [];
            $alunoId = (int) ($aluno['id'] ?? 0);
            $extra = $extras[$alunoId] ?? [];
            $ra = $this->separarRa(
                (string) ($aluno['ra'] ?? ''),
                (string) ($extra['ra_digito'] ?? ''),
                (string) ($extra['ra_uf'] ?? '')
            );
            $situacao = (string) ($linha['situacao'] ?? '');
            $transferido = !empty($aluno['transferido']) || $situacao === 'transferido';
            $rendimento = $this->rendimentoSp($situacao, $transferido);
            $avisosAluno = [];
            if (trim((string) ($aluno['ra'] ?? '')) === '') {
                $avisosAluno[] = 'RA vazio';
                $pendencias[] = $this->pendencia($this->nomeAluno($aluno) . ': RA vazio.', true);
            } elseif ($ra['digito'] === '' || $ra['uf'] === '') {
                $avisosAluno[] = 'Separar dígito e UF do RA';
                $pendencias[] = $this->pendencia($this->nomeAluno($aluno) . ': dígito ou UF do RA em branco.', false);
            }
            if (!$transferido && $this->semResultado($situacao)) {
                $avisosAluno[] = 'Sem resultado — não lançar como aprovado';
                $pendencias[] = $this->pendencia($this->nomeAluno($aluno) . ': sem resultado final.', true);
            }
            $ordem++;
            $nome = $this->nomeAluno($aluno);
            $pendenciaLinha = implode('; ', $avisosAluno);
            $linhas[] = [
                (string) $ordem,
                $nome,
                $ra['numero'],
                $ra['digito'],
                $ra['uf'],
                $rendimento['movimentacao'],
                $rendimento['rotulo'],
                $rendimento['codigo'],
                '',
                '',
                '',
                $pendenciaLinha,
            ];
            $blocos[] = [
                'Nº NA LISTAGEM (SUGERIDO): ' . $ordem,
                'NOME DO ALUNO — CONFERÊNCIA: ' . $nome,
                'RA: ' . $ra['numero'],
                'DÍGITO RA: ' . $ra['digito'],
                'UF RA: ' . $ra['uf'],
                'MOVIMENTAÇÃO: ' . $rendimento['movimentacao'],
                'RENDIMENTO SUGERIDO: ' . $rendimento['rotulo'],
                'CÓDIGO SUGERIDO NA SED: ' . $rendimento['codigo'],
                'CONCLUINTE — CONFERÊNCIA: ',
                'DIGITADO EM: ',
                'CONFERIDO POR: ',
                $pendenciaLinha !== '' ? 'PENDÊNCIA: ' . $pendenciaLinha : '',
            ];
        }

        $cabecalho = [
            ['ESCOLA', $escola['nome']],
            ['CIE', $escola['codigo_estadual']],
            ['INEP (referência, não é o CIE)', $escola['inep']],
            ['ANO LETIVO', (string) ($periodo['ano_letivo'] ?? '')],
            ['OFERTA', $this->oferta($periodo)],
            ['NÚMERO OFICIAL DA CLASSE SED', $numeroClasse],
            ['SÉRIE/ANO', trim((string) ($turma['serie'] ?? ''))],
            ['TURMA INTERNA', trim((string) ($turma['nome'] ?? ''))],
            ['TURNO', $this->turno((string) ($turma['turno'] ?? ''))],
            ['CURSO', $this->nomeCurso($turma)],
        ];
        $orientacao = $this->orientacaoSp($cabecalho, $pendencias);
        $txt = $this->textoSp($cabecalho, $blocos, $pendencias);

        return [
            'colunas' => $colunas,
            'linhas' => $linhas,
            'orientacao' => $orientacao,
            'txt' => $txt,
            'pendencias' => $pendencias,
            'homologada' => $homologada,
        ];
    }

    /**
     * @param array<string,mixed> $escola
     * @param array<string,mixed> $preview
     * @return array{colunas:list<string>,linhas:list<list<string>>,orientacao:list<string>,txt:list<string>,pendencias:list<array{texto:string,critica:bool}>,homologada:bool}
     */
    private function documentoPr(array $escola, array $preview): array
    {
        $turma = is_array($preview['turma'] ?? null) ? $preview['turma'] : [];
        $periodo = is_array($preview['periodo'] ?? null) ? $preview['periodo'] : [];
        $linhasPreview = is_array($preview['linhas'] ?? null) ? $preview['linhas'] : [];
        $ids = $this->idsAlunos($linhasPreview);
        $extras = $this->identificadoresAlunos($ids);
        $resumo = is_array($preview['resumo'] ?? null) ? $preview['resumo'] : [];
        $homologada = (int) ($resumo['total'] ?? 0) > 0
            && (int) ($resumo['homologados'] ?? 0) === (int) ($resumo['total'] ?? 0);
        $turmaId = (int) ($turma['id'] ?? 0);
        $inicio = (string) ($periodo['inicio'] ?? '');
        $fim = (string) ($periodo['fim'] ?? '');

        $pendencias = [];
        if ($escola['codigo_estadual'] === '') {
            $pendencias[] = $this->pendencia('Código da escola no SERE não informado.', true);
        }
        $codigoCurso = trim((string) ($turma['codigo_curso_oficial'] ?? ''));
        if ($codigoCurso === '') {
            $pendencias[] = $this->pendencia('Código oficial do curso não informado.', true);
        }
        if (!$homologada) {
            $pendencias[] = $this->pendencia('Turma ainda não homologada por completo. Este arquivo é prévia.', true);
        }
        $pendencias[] = $this->pendencia('Datas oficiais de início e término da turma não ficam neste cadastro. Conferir no SERE.', false);

        $colunas = [
            'Nº',
            'CGM',
            'Nome',
            'Matrícula e movimentação',
            'Componente',
            'Período',
            'Nota ou conceito',
            'Faltas',
            'Carga horária',
            'Recuperação',
            'Resultado consolidado',
            'Deliberação do conselho',
            'Pendência',
        ];
        $linhas = [];
        $blocosTxt = [];
        $ordem = 0;
        $periodoLabel = (string) ($periodo['label'] ?? '');
        $frequencia = new FrequencyService();

        foreach ($linhasPreview as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $aluno = is_array($linha['aluno'] ?? null) ? $linha['aluno'] : [];
            $alunoId = (int) ($aluno['id'] ?? 0);
            $extra = $extras[$alunoId] ?? [];
            $cgm = trim((string) ($extra['cgm'] ?? ''));
            $nome = $this->nomeAluno($aluno);
            $situacao = (string) ($linha['situacao'] ?? '');
            $transferido = !empty($aluno['transferido']) || $situacao === 'transferido';
            $movimentacao = $transferido
                ? 'Transferido — registrar a movimentação antes do resultado'
                : 'Matrícula ativa';
            $resultado = $transferido ? '' : ($this->semResultado($situacao) ? 'PENDENTE' : (string) ($linha['rotulo'] ?? ''));
            $deliberacao = $this->deliberacao($linha);
            $avisosAluno = [];
            if ($cgm === '') {
                $avisosAluno[] = 'CGM vazio';
                $pendencias[] = $this->pendencia($nome . ': CGM vazio.', true);
            }
            if (!$transferido && $this->semResultado($situacao)) {
                $avisosAluno[] = 'Sem resultado — não lançar como aprovado';
                $pendencias[] = $this->pendencia($nome . ': sem resultado final.', true);
            }
            $faltasComp = $this->faltasPorComponente($frequencia, $alunoId, $turmaId, $inicio, $fim);
            $componentes = is_array($linha['componentes'] ?? null) ? $linha['componentes'] : [];
            if ($componentes === []) {
                $componentes = [[
                    'materia_nome' => '',
                    'materia_id' => null,
                    'media_final' => null,
                    'media' => null,
                    'recuperacao' => null,
                    'carga_horaria' => null,
                ]];
                $avisosAluno[] = 'Sem componente na matriz lançada';
            }
            $ordem++;
            $linhasTxtComp = [];
            foreach ($componentes as $comp) {
                if (!is_array($comp)) {
                    continue;
                }
                $mid = (int) ($comp['materia_id'] ?? 0);
                $faltas = '';
                if ($mid > 0 && isset($faltasComp[$mid])) {
                    $faltas = (string) (int) $faltasComp[$mid];
                }
                $nota = $comp['media_final'] ?? $comp['media'] ?? null;
                $ch = $comp['carga_horaria'] ?? null;
                $chTxt = is_numeric($ch) && (int) $ch > 0 ? (string) (int) $ch . ' h' : '';
                $rec = $this->nota($comp['recuperacao'] ?? null);
                $nomeComp = trim((string) ($comp['materia_nome'] ?? ''));
                $pendenciaLinha = implode('; ', $avisosAluno);
                $linhas[] = [
                    (string) $ordem,
                    $cgm,
                    $nome,
                    $movimentacao,
                    $nomeComp,
                    $periodoLabel,
                    $this->nota($nota),
                    $faltas,
                    $chTxt,
                    $rec,
                    $resultado,
                    $deliberacao,
                    $pendenciaLinha,
                ];
                $linhasTxtComp[] = '  COMPONENTE: ' . $nomeComp
                    . ' | NOTA: ' . $this->nota($nota)
                    . ' | FALTAS: ' . $faltas
                    . ' | CH: ' . $chTxt
                    . ' | RECUPERAÇÃO: ' . $rec;
            }
            $blocosTxt[] = array_merge(
                [
                    'Nº: ' . $ordem,
                    'CGM: ' . $cgm,
                    'NOME: ' . $nome,
                    'MATRÍCULA E MOVIMENTAÇÃO: ' . $movimentacao,
                    'PERÍODO: ' . $periodoLabel,
                    'RESULTADO CONSOLIDADO: ' . $resultado,
                    'DELIBERAÇÃO DO CONSELHO: ' . $deliberacao,
                ],
                $linhasTxtComp,
                [$avisosAluno !== [] ? 'PENDÊNCIA: ' . implode('; ', $avisosAluno) : '']
            );
        }

        $regra = '';
        foreach ($linhasPreview as $linha) {
            if (is_array($linha) && trim((string) ($linha['regra']['nome'] ?? '')) !== '') {
                $regra = trim((string) $linha['regra']['nome']);
                break;
            }
        }
        $cabecalho = [
            ['ESCOLA', $escola['nome']],
            ['CÓDIGO ESCOLA SERE', $escola['codigo_estadual']],
            ['INEP (referência)', $escola['inep']],
            ['ANO/PERÍODO LETIVO', trim(((string) ($periodo['ano_letivo'] ?? '')) . ' ' . $periodoLabel)],
            ['CURSO', trim($this->nomeCurso($turma) . ($codigoCurso !== '' ? ' (' . $codigoCurso . ')' : ''))],
            ['ANO/SÉRIE', trim((string) ($turma['serie'] ?? ''))],
            ['TURMA/TURNO', trim((string) ($turma['nome'] ?? '')) . ' / ' . $this->turno((string) ($turma['turno'] ?? ''))],
            ['INÍCIO/TÉRMINO DO PERÍODO NO EDUCATUDO', trim($inicio . ' a ' . $fim)],
            ['MATRIZ VIGENTE', $this->nomeMatriz($turma)],
            ['SISTEMA DE AVALIAÇÃO', $regra],
        ];

        return [
            'colunas' => $colunas,
            'linhas' => $linhas,
            'orientacao' => $this->orientacaoPr($cabecalho, $pendencias),
            'txt' => $this->textoPr($cabecalho, $blocosTxt, $pendencias),
            'pendencias' => $pendencias,
            'homologada' => $homologada,
        ];
    }

    /**
     * @param list<array{0:string,1:string}> $cabecalho
     * @param list<array{texto:string,critica:bool}> $pendencias
     * @return list<string>
     */
    private function orientacaoSp(array $cabecalho, array $pendencias): array
    {
        $linhas = [
            'APOIO À DIGITAÇÃO — SED/SP — NÃO IMPORTÁVEL',
            'Este arquivo não entra na SED. Sirva-se dele para digitar a classe, Salvar e Enviar.',
            'Concluinte fica em branco: aprovado não é concluinte.',
            'Aluno sem resultado aparece como PENDENTE. Não converta isso em aprovado.',
            'Movimentação e rendimento são registros distintos.',
            '',
        ];
        foreach ($cabecalho as $par) {
            $linhas[] = $par[0] . ': ' . $par[1];
        }
        $linhas[] = '';
        $linhas[] = 'Legenda de referência da API NCA049 (não colar sem conferir a opção liberada na SED):';
        $linhas[] = '1 Aprovado; 2 Aprovado parcialmente; 3 Retido por frequência; 4 Retido por rendimento; 5 Retido parcialmente; 7 Terminalidade específica; 8 Curso em andamento.';
        $linhas[] = '';
        $linhas[] = 'Controle por classe:';
        $linhas[] = '[ ] Conferidos todos os alunos.';
        $linhas[] = '[ ] SALVAR na SED.';
        $linhas[] = '[ ] ENVIAR na SED.';
        $linhas[] = '[ ] Verificar situação da classe e rendimento da escola.';
        return array_merge($linhas, $this->linhasPendencia($pendencias));
    }

    /**
     * @param list<array{0:string,1:string}> $cabecalho
     * @param list<list<string>> $blocos
     * @param list<array{texto:string,critica:bool}> $pendencias
     * @return list<string>
     */
    private function textoSp(array $cabecalho, array $blocos, array $pendencias): array
    {
        $linhas = ['APOIO À DIGITAÇÃO — SED/SP — NÃO IMPORTÁVEL', ''];
        foreach ($cabecalho as $par) {
            $linhas[] = $par[0] . ': ' . $par[1];
        }
        $linhas[] = '';
        $linhas[] = 'Repetir por aluno, na ordem da listagem da SED. O número abaixo é só uma sugestão (ordem do nome).';
        foreach ($blocos as $bloco) {
            $linhas[] = '';
            foreach ($bloco as $linha) {
                if ($linha !== '') {
                    $linhas[] = $linha;
                }
            }
        }
        $linhas[] = '';
        $linhas[] = 'CONTROLE POR CLASSE';
        $linhas[] = '[ ] Conferidos todos os alunos; não aceitar aprovação padrão sem conferência.';
        $linhas[] = '[ ] SALVAR na SED.';
        $linhas[] = '[ ] ENVIAR na SED.';
        $linhas[] = '[ ] Verificar situação da classe e rendimento da escola.';
        return array_merge($linhas, $this->linhasPendencia($pendencias));
    }

    /**
     * @param list<array{0:string,1:string}> $cabecalho
     * @param list<array{texto:string,critica:bool}> $pendencias
     * @return list<string>
     */
    private function orientacaoPr(array $cabecalho, array $pendencias): array
    {
        $linhas = [
            'APOIO À CONFERÊNCIA — SERE/PR — NÃO IMPORTÁVEL',
            'Este arquivo não entra no SERE. Use-o para conferir turma, componente, nota e falta antes de lançar.',
            'Faltas são as do componente no diário. Se a célula vier vazia, a falta daquele componente não estava lançada.',
            'O nome do componente é o do EducaTudo. No SERE, use o código da matriz vigente.',
            'Não transforme o resultado interno em AP, REP ou PP. Esse enquadramento é do formulário MARFIN, que este arquivo não substitui.',
            '',
        ];
        foreach ($cabecalho as $par) {
            $linhas[] = $par[0] . ': ' . $par[1];
        }
        $linhas[] = '';
        $linhas[] = 'Ordem de trabalho:';
        $linhas[] = '1 Conferir sistema de avaliação, matriz, vínculos e movimentações.';
        $linhas[] = '2 Conferir notas e faltas.';
        $linhas[] = '3 Calcular o resultado no SERE por turma.';
        $linhas[] = '4 Registrar a deliberação do conselho quando houver.';
        return array_merge($linhas, $this->linhasPendencia($pendencias));
    }

    /**
     * @param list<array{0:string,1:string}> $cabecalho
     * @param list<list<string>> $blocos
     * @param list<array{texto:string,critica:bool}> $pendencias
     * @return list<string>
     */
    private function textoPr(array $cabecalho, array $blocos, array $pendencias): array
    {
        $linhas = ['APOIO À CONFERÊNCIA E DIGITAÇÃO — SERE/PR — NÃO IMPORTÁVEL', ''];
        foreach ($cabecalho as $par) {
            $linhas[] = $par[0] . ': ' . $par[1];
        }
        $linhas[] = '';
        foreach ($blocos as $bloco) {
            $linhas[] = '';
            foreach ($bloco as $linha) {
                if ($linha !== '') {
                    $linhas[] = $linha;
                }
            }
        }
        return array_merge($linhas, $this->linhasPendencia($pendencias));
    }

    /**
     * @param list<array{texto:string,critica:bool}> $pendencias
     * @return list<string>
     */
    private function linhasPendencia(array $pendencias): array
    {
        if ($pendencias === []) {
            return ['', 'Pendências: nenhuma.'];
        }
        $linhas = ['', 'Pendências desta versão:'];
        foreach ($pendencias as $item) {
            $linhas[] = ($item['critica'] ? '[crítica] ' : '[conferir] ') . $item['texto'];
        }
        return $linhas;
    }

    /**
     * @return array{codigo:string,rotulo:string,movimentacao:string}
     */
    private function rendimentoSp(string $situacao, bool $transferido): array
    {
        if ($transferido) {
            return [
                'codigo' => '',
                'rotulo' => '',
                'movimentacao' => 'Transferido — registrar a movimentação antes do rendimento',
            ];
        }
        $map = [
            'aprovado' => ['1', 'Aprovado'],
            'aprovado_recuperacao' => ['1', 'Aprovado'],
            'aproveitamento' => ['1', 'Aprovado'],
            'reprovado_frequencia' => ['3', 'Retido por frequência'],
            'reprovado_rendimento' => ['4', 'Retido por rendimento'],
            'aprovado_conselho' => ['', 'Aprovado por conselho — escolher a opção liberada na SED'],
        ];
        if (isset($map[$situacao])) {
            return [
                'codigo' => $map[$situacao][0],
                'rotulo' => $map[$situacao][1],
                'movimentacao' => 'Matrícula ativa',
            ];
        }
        return [
            'codigo' => '',
            'rotulo' => 'PENDENTE',
            'movimentacao' => 'Matrícula ativa',
        ];
    }

    private function semResultado(string $situacao): bool
    {
        return in_array($situacao, ['', 'em_andamento', 'recuperacao', 'exame_final'], true);
    }

    /**
     * @param array<string,mixed> $linha
     */
    private function deliberacao(array $linha): string
    {
        $resultado = (string) ($linha['conselho']['resultado'] ?? '');
        $map = [
            'aprovado_conselho' => 'Conselho: aprovado',
            'aprovado' => 'Conselho: aprovado',
            'retido' => 'Conselho: retido',
            'transferido' => 'Conselho: transferência',
            'recuperacao' => 'Conselho: recuperação',
            'manter' => 'Conselho: manter o resultado',
        ];
        if (isset($map[$resultado])) {
            return $map[$resultado];
        }
        if (!empty($linha['conselho']['finalizado'])) {
            return 'Conselho finalizado';
        }
        return '';
    }

    /**
     * @return array{numero:string,digito:string,uf:string}
     */
    private function separarRa(string $ra, string $digito, string $uf): array
    {
        $ra = trim($ra);
        $digito = strtoupper(trim($digito));
        $uf = strtoupper(trim($uf));
        if ($digito !== '' && preg_match('/^(\d+)\s*[-\/]\s*' . preg_quote($digito, '/') . '(?:\s*[-\/]\s*[A-Za-z]{2})?$/', $ra, $jaSeparado)) {
            $ra = $jaSeparado[1];
        }
        if ($digito !== '' || $uf !== '') {
            return ['numero' => $ra, 'digito' => $digito, 'uf' => $uf];
        }
        if (preg_match('/^(\d+)\s*[-\/]\s*([0-9Xx])(?:\s*[-\/]\s*([A-Za-z]{2}))?$/', $ra, $m)) {
            return [
                'numero' => $m[1],
                'digito' => strtoupper($m[2]),
                'uf' => isset($m[3]) ? strtoupper($m[3]) : '',
            ];
        }
        return ['numero' => $ra, 'digito' => '', 'uf' => ''];
    }

    /**
     * @param mixed $valor
     */
    private function nota($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (!is_numeric($valor)) {
            return trim((string) $valor);
        }
        return number_format((float) $valor, 2, ',', '');
    }

    /**
     * @param array<string,mixed> $aluno
     */
    private function nomeAluno(array $aluno): string
    {
        $nome = trim((string) ($aluno['nome'] ?? ''));
        return $nome !== '' ? $nome : 'Aluno sem nome';
    }

    /**
     * @param array<string,mixed> $periodo
     */
    private function oferta(array $periodo): string
    {
        $tipo = (string) ($periodo['tipo'] ?? 'ano');
        $numero = (int) ($periodo['numero'] ?? 0);
        if ($tipo === 'ano') {
            return 'ANUAL';
        }
        if ($tipo === 'semestre' && $numero === 1) {
            return '1º SEMESTRE';
        }
        if ($tipo === 'semestre' && $numero === 2) {
            return '2º SEMESTRE';
        }
        return (string) ($periodo['label'] ?? '');
    }

    private function turno(string $turno): string
    {
        $map = [
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
        ];
        $chave = strtolower(trim($turno));
        return $map[$chave] ?? $turno;
    }

    /**
     * @param array<string,mixed> $turma
     */
    private function nomeCurso(array $turma): string
    {
        $id = (int) ($turma['curso_novo_id'] ?? 0);
        if ($id <= 0) {
            $id = (int) ($turma['curso_id'] ?? 0);
        }
        if ($id <= 0 || !$this->tabelaExiste('curso')) {
            return '';
        }
        $row = $this->db->fetch('SELECT nome FROM curso WHERE id = :id', ['id' => $id]);
        return trim((string) ($row['nome'] ?? ''));
    }

    /**
     * @param array<string,mixed> $turma
     */
    private function nomeMatriz(array $turma): string
    {
        $id = (int) ($turma['matriz_curricular_id'] ?? 0);
        if ($id <= 0 || !$this->tabelaExiste('matrizes_curriculares')) {
            return '';
        }
        $row = $this->db->fetch(
            'SELECT nome, codigo FROM matrizes_curriculares WHERE id = :id',
            ['id' => $id]
        );
        if (!$row) {
            return '';
        }
        $nome = trim((string) ($row['nome'] ?? ''));
        $codigo = trim((string) ($row['codigo'] ?? ''));
        if ($codigo !== '' && $nome !== '') {
            return $nome . ' (' . $codigo . ')';
        }
        return $nome !== '' ? $nome : $codigo;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<int>
     */
    private function idsAlunos(array $linhas): array
    {
        $ids = [];
        foreach ($linhas as $linha) {
            $id = (int) ($linha['aluno']['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{ra_digito:string,ra_uf:string,cgm:string}>
     */
    private function identificadoresAlunos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $temDigito = $this->colunaExiste('alunos', 'ra_digito');
        $temUf = $this->colunaExiste('alunos', 'ra_uf');
        $temCgm = $this->colunaExiste('alunos', 'cgm');
        if (!$temDigito && !$temUf && !$temCgm) {
            return [];
        }
        $marcas = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $chave = 'id' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = (int) $id;
        }
        $select = 'id';
        if ($temDigito) {
            $select .= ', ra_digito';
        }
        if ($temUf) {
            $select .= ', ra_uf';
        }
        if ($temCgm) {
            $select .= ', cgm';
        }
        $rows = $this->db->fetchAll(
            'SELECT ' . $select . ' FROM alunos WHERE id IN (' . implode(', ', $marcas) . ')',
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = [
                'ra_digito' => trim((string) ($row['ra_digito'] ?? '')),
                'ra_uf' => trim((string) ($row['ra_uf'] ?? '')),
                'cgm' => trim((string) ($row['cgm'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, int>
     */
    private function faltasPorComponente(FrequencyService $frequencia, int $alunoId, int $turmaId, string $inicio, string $fim): array
    {
        if ($alunoId <= 0 || $turmaId <= 0 || $inicio === '' || $fim === '') {
            return [];
        }
        try {
            $mapa = $frequencia->alunoPorComponente($alunoId, $turmaId, $inicio, $fim);
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($mapa as $mid => $item) {
            $out[(int) $mid] = (int) ($item['faltas'] ?? 0);
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $turmas
     * @return array<int, array{numero_classe_oficial:string,codigo_curso_oficial:string}>
     */
    private function oficiaisDasTurmas(array $turmas): array
    {
        $ids = [];
        foreach ($turmas as $turma) {
            $id = (int) ($turma['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $temNumero = $this->colunaExiste('turmas', 'numero_classe_oficial');
        $temCurso = $this->colunaExiste('turmas', 'codigo_curso_oficial');
        if (!$temNumero && !$temCurso) {
            return [];
        }
        $marcas = [];
        $params = [];
        $i = 0;
        foreach ($ids as $id) {
            $chave = 't' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $id;
            $i++;
        }
        $select = 'id';
        if ($temNumero) {
            $select .= ', numero_classe_oficial';
        }
        if ($temCurso) {
            $select .= ', codigo_curso_oficial';
        }
        $rows = $this->db->fetchAll(
            'SELECT ' . $select . ' FROM turmas WHERE id IN (' . implode(', ', $marcas) . ')',
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $out[$id] = [
                'numero_classe_oficial' => trim((string) ($row['numero_classe_oficial'] ?? '')),
                'codigo_curso_oficial' => trim((string) ($row['codigo_curso_oficial'] ?? '')),
            ];
        }
        return $out;
    }

    private function turmaDoAno(int $turmaId, int $anoLetivo): bool
    {
        foreach ($this->homologacao->model()->turmasAtivas($anoLetivo) as $turma) {
            if ((int) ($turma['id'] ?? 0) === $turmaId) {
                return true;
            }
        }
        return false;
    }

    private function proximaVersao(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): int
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(MAX(versao), 0) AS versao
             FROM fechamento_apoio_digitacao
             WHERE turma_id = :turma_id AND ano_letivo = :ano
               AND periodo_tipo = :periodo_tipo AND periodo_numero = :periodo_numero',
            [
                'turma_id' => $turmaId,
                'ano' => $anoLetivo,
                'periodo_tipo' => $periodoTipo,
                'periodo_numero' => $periodoNumero,
            ]
        );
        return (int) ($row['versao'] ?? 0) + 1;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buscar(int $id): ?array
    {
        if ($id <= 0 || !$this->schemaPronto()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM fechamento_apoio_digitacao WHERE id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function bloqueioValidacao(array $row): string
    {
        if ((int) ($row['homologada'] ?? 0) !== 1) {
            return 'Esta versão saiu antes da homologação completa da turma. Homologue e gere de novo.';
        }
        foreach (self::lerPendencias($row['pendencias_json'] ?? '') as $item) {
            if ($item['critica']) {
                return 'Ainda há pendência crítica. Corrija o cadastro e gere uma nova versão.';
            }
        }
        return '';
    }

    /**
     * @return array{texto:string,critica:bool}
     */
    private function pendencia(string $texto, bool $critica): array
    {
        return ['texto' => $texto, 'critica' => $critica];
    }

    private function textoCurto(string $valor, int $limite): string
    {
        $valor = trim($valor);
        if (mb_strlen($valor) > $limite) {
            return mb_substr($valor, 0, $limite);
        }
        return $valor;
    }

    private function diretorioBase(): string
    {
        return dirname(__DIR__, 4) . '/storage/fechamento-apoio/' . $this->slugTenant();
    }

    private function slugTenant(): string
    {
        $slug = defined('TENANT_SLUG') ? (string) TENANT_SLUG : 'default';
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?? '';
        return $slug !== '' ? $slug : 'default';
    }

    private function caminhoRelativo(string $absoluto): string
    {
        $base = rtrim(str_replace('\\', '/', $this->diretorioBase()), '/');
        $absoluto = str_replace('\\', '/', $absoluto);
        if (str_starts_with($absoluto, $base . '/')) {
            return substr($absoluto, strlen($base) + 1);
        }
        return basename($absoluto);
    }

    private function caminhoAbsoluto(string $relativo): ?string
    {
        $relativo = str_replace('\\', '/', trim($relativo));
        if ($relativo === '' || str_contains($relativo, '..')) {
            return null;
        }
        $base = $this->diretorioBase();
        $candidato = $base . '/' . ltrim($relativo, '/');
        if (!is_file($candidato)) {
            return null;
        }
        $realBase = realpath($base);
        $real = realpath($candidato);
        if ($realBase === false || $real === false || !str_starts_with($real, $realBase . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $real;
    }

    private function tabelaExiste(string $tabela): bool
    {
        if (isset($this->tabelas[$tabela])) {
            return $this->tabelas[$tabela];
        }
        if (!preg_match('/^[a-z0-9_]+$/', $tabela)) {
            return false;
        }
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela',
            ['tabela' => $tabela]
        );
        $this->tabelas[$tabela] = (int) ($row['n'] ?? 0) > 0;
        return $this->tabelas[$tabela];
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        $chave = $tabela . '.' . $coluna;
        if (isset($this->colunas[$chave])) {
            return $this->colunas[$chave];
        }
        if (!preg_match('/^[a-z0-9_]+$/', $tabela) || !preg_match('/^[a-z0-9_]+$/', $coluna)) {
            return false;
        }
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND COLUMN_NAME = :coluna',
            ['tabela' => $tabela, 'coluna' => $coluna]
        );
        $this->colunas[$chave] = (int) ($row['n'] ?? 0) > 0;
        return $this->colunas[$chave];
    }
}
