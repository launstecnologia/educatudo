<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('ReportAdminController')) {
class ReportAdminController extends AdminBaseController
{
    public function apiConversasAluno()
    {
        $aluno_id = $_GET['aluno_id'] ?? '';
        
        if (empty($aluno_id)) {
            $this->json(['error' => 'Aluno ID é obrigatório'], 400);
        }
        
        $conversas = $this->db->fetchAll(
            "SELECT c.*, COUNT(m.id) as total_mensagens
             FROM tudinha_conversas c
             LEFT JOIN tudinha_mensagens m ON c.id = m.conversa_id
             WHERE c.aluno_id = :aluno_id AND c.excluida = 0
             GROUP BY c.id
             ORDER BY c.ultima_atividade DESC
             LIMIT 10",
            ['aluno_id' => $aluno_id]
        );
        
        $this->json(['conversas' => $conversas]);
    }

    public function apiMensagensConversa()
    {
        $conversa_id = $_GET['conversa_id'] ?? '';
        
        if (empty($conversa_id)) {
            $this->json(['error' => 'Conversa ID é obrigatório'], 400);
        }
        
        $mensagens = $this->db->fetchAll(
            "SELECT m.*, c.aluno_id, a.nome as aluno_nome
             FROM tudinha_mensagens m
             INNER JOIN tudinha_conversas c ON m.conversa_id = c.id
             INNER JOIN alunos a ON c.aluno_id = a.id
             WHERE m.conversa_id = :conversa_id
             ORDER BY m.created_at ASC",
            ['conversa_id' => $conversa_id]
        );
        
        $this->json(['mensagens' => $mensagens]);
    }

    public function apiRedacoesAluno()
    {
        $aluno_id = $_GET['aluno_id'] ?? '';
        
        if (empty($aluno_id)) {
            $this->json(['error' => 'Aluno ID é obrigatório'], 400);
        }
        
        $redacoes = $this->db->fetchAll(
            "SELECT r.*, t.titulo as tema_titulo
             FROM redacoes r
             LEFT JOIN redacoes_temas t ON r.tema_id = t.id
             WHERE r.aluno_id = :aluno_id
             ORDER BY r.created_at DESC
             LIMIT 20",
            ['aluno_id' => $aluno_id]
        );
        
        // Parse feedback_ia se existir para estruturar os dados de correção
        foreach ($redacoes as &$redacao) {
            if (!empty($redacao['feedback_ia'])) {
                $redacao['feedback_detalhado'] = json_decode($redacao['feedback_ia'], true);
            }
        }
        
        $this->json(['redacoes' => $redacoes]);
    }

    public function apiExerciciosAluno()
    {
        $aluno_id = $_GET['aluno_id'] ?? '';
        
        if (empty($aluno_id)) {
            $this->json(['error' => 'Aluno ID é obrigatório'], 400);
        }
        
        $exercicios = $this->db->fetchAll(
            "SELECT h.*, le.titulo, le.materia, s.finished_at as data_fim
             FROM exercicios_historico h
             INNER JOIN listas_exercicios le ON h.lista_id = le.id
             LEFT JOIN exercicios_sessoes s ON h.sessao_id = s.id
             WHERE h.aluno_id = :aluno_id
             ORDER BY h.created_at DESC
             LIMIT 20",
            ['aluno_id' => $aluno_id]
        );
        
        $this->json(['exercicios' => $exercicios]);
    }

    public function relatorios()
    {
        $user = $this->auth->getUser();
        
        // Parâmetros de filtro
        $filtros = [
            'tipo' => $_GET['tipo'] ?? 'geral', // geral, turma, usuario
            'turma_id' => $_GET['turma_id'] ?? '',
            'aluno_id' => $_GET['aluno_id'] ?? '',
            'aluno_nome' => trim((string) ($_GET['aluno_nome'] ?? '')),
            'data_inicio' => $_GET['data_inicio'] ?? '',
            'data_fim' => $_GET['data_fim'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 25,
            'jr_ano_letivo' => $_GET['jr_ano_letivo'] ?? '',
            'jr_bimestre' => $_GET['jr_bimestre'] ?? '',
            'jr_professor_id' => $_GET['jr_professor_id'] ?? '',
            'jr_materia_id' => $_GET['jr_materia_id'] ?? '',
            'jr_jornada_id' => $_GET['jr_jornada_id'] ?? '',
            'jr_turma_ano_letivo' => $_GET['jr_turma_ano_letivo'] ?? '',
            'jr_avaliativo' => $_GET['jr_avaliativo'] ?? '',
            'jr_somente_atencao' => !empty($_GET['jr_somente_atencao']) ? 1 : 0,
            'jr_tempo_ordem' => $_GET['jr_tempo_ordem'] ?? '',
            'jr_modo_materia' => ($_GET['jr_modo_materia'] ?? 'total') === 'por_materia' ? 'por_materia' : 'total',
            'executar' => !empty($_GET['executar']) ? 1 : 0,
        ];
        $filtros['page'] = max(1, (int) $filtros['page']);
        $filtros['limit'] = max(10, min(500, (int) $filtros['limit']));
        if ($filtros['tipo'] === 'usuario' && empty($filtros['aluno_id']) && $filtros['aluno_nome'] !== '') {
            $alunoMatch = $this->db->fetch(
                "SELECT id, nome FROM alunos
                 WHERE ativo = 1 AND nome LIKE :nome
                 ORDER BY (nome = :nome_exato) DESC, nome ASC
                 LIMIT 1",
                [
                    'nome' => '%' . $filtros['aluno_nome'] . '%',
                    'nome_exato' => $filtros['aluno_nome'],
                ]
            );
            if (!empty($alunoMatch['id'])) {
                $filtros['aluno_id'] = (int) $alunoMatch['id'];
                $filtros['aluno_nome'] = (string) ($alunoMatch['nome'] ?? $filtros['aluno_nome']);
            }
        }
        
        // Buscar turmas
        $turmas = $this->db->fetchAll("SELECT * FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
        
        // Buscar alunos
        $alunos = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, t.nome as turma_nome 
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.ativo = 1 
             ORDER BY a.nome ASC"
        );
        
        $essays_stats = [];
        $redacoes_com_correcao = [];
        $jornadas_relatorio = [];
        if (!empty($filtros['executar'])) {
            require_once __DIR__ . '/../../Services/JornadasRelatorioService.php';
            $jornadasRelatorioService = new JornadasRelatorioService($this->db);
            $jornadas_relatorio = $jornadasRelatorioService->relatorio($filtros);
        }

        $professores_jornadas_rel = $this->db->fetchAll(
            "SELECT id, nome FROM professores ORDER BY nome ASC"
        );
        $materias_jornadas_rel = $this->db->fetchAll(
            "(SELECT id, nome FROM jornadas_materias WHERE nome IS NOT NULL AND nome <> '')
             UNION
             (SELECT id, nome FROM materias WHERE nome IS NOT NULL AND nome <> '')
             ORDER BY nome ASC"
        );
        $anos_turmas_rel = $this->db->fetchAll(
            "SELECT DISTINCT ano_letivo FROM turmas WHERE ativo = 1 ORDER BY ano_letivo DESC"
        );
        $jornadas_select_rel = $this->db->fetchAll(
            "SELECT j.id, j.titulo, t.nome AS turma_nome
             FROM jornadas j
             INNER JOIN turmas t ON j.turma_id = t.id
             WHERE (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC
             LIMIT 500"
        );
        
        $data = [
            'title' => 'Relatórios Administrativos - EducaTudo',
            'user' => $user,
            'current_page' => 'reports',
            'filtros' => $filtros,
            'turmas' => $turmas,
            'alunos' => $alunos,
            'essays_stats' => $essays_stats,
            'redacoes_com_correcao' => $redacoes_com_correcao,
            'jornadas_relatorio' => $jornadas_relatorio,
            'professores_jornadas_rel' => $professores_jornadas_rel,
            'materias_jornadas_rel' => $materias_jornadas_rel,
            'anos_turmas_rel' => $anos_turmas_rel,
            'jornadas_select_rel' => $jornadas_select_rel,
        ];
        
        $this->viewWithLayout('admin', 'admin/reports/index', $data);
    }

    /**
     * Colunas existentes em uma tabela (cache simples por request) para
     * montar SELECTs defensivos quando uma migration ainda não rodou.
     *
     * @return array<string, bool>
     */
    private function colunasExistentes(string $tabela): array
    {
        static $cache = [];
        if (isset($cache[$tabela])) {
            return $cache[$tabela];
        }
        $cols = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t",
                ['t' => $tabela]
            );
            foreach ($rows as $r) {
                $cols[(string) $r['COLUMN_NAME']] = true;
            }
        } catch (\Throwable $e) {
            $cols = [];
        }
        $cache[$tabela] = $cols;
        return $cols;
    }

    /**
     * Tela dedicada de Censo/INEP: lista alunos com situação de
     * preenchimento dos campos exigidos pelo Educacenso e oferece a
     * exportação CSV (filtrável por unidade).
     */
    public function censo()
    {
        $this->redirect('/admin/censo');
    }

    public function buscarAlunosBoletimCoordenacao(): void
    {
        if (!$this->enforceAdminPermissionKey('relatorios_gerais', 'visualizar', true)) {
            return;
        }
        $termo = $this->parseAlunoBuscaBoletimCoordenacao();
        $turmaId = max(0, (int) ($_GET['turma_id'] ?? 0));
        $cursoId = max(0, (int) ($_GET['curso_id'] ?? 0));
        if (strlen($termo) < 2) {
            $this->json(['success' => true, 'alunos' => []]);
            return;
        }
        [$whereAluno, $paramsAluno] = $this->whereAlunoBuscaBoletimCoordenacao($termo, 'a');
        $params = $paramsAluno;
        [$whereTurma, $paramsTurma] = $this->whereTurmasBoletimCoordenacao($turmaId, $cursoId, 'a');
        $params += $paramsTurma;
        $codigoSelect = $this->colunaAlunosCodigoExisteBoletimCoordenacao()
            ? 'a.codigo_aluno'
            : 'NULL AS codigo_aluno';
        $condVisivelBusca = $this->sqlAlunoVisivelBoletimCoordenacao('a', 't');
        $transferidoBusca = $this->sqlTransferidoSelectBoletimCoordenacao('a', 't');
        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, a.ativo, {$codigoSelect},
                    COALESCE(t.nome, '') AS turma_nome,
                    {$transferidoBusca} AS transferido
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE {$condVisivelBusca}{$whereAluno}{$whereTurma}
             ORDER BY a.nome ASC
             LIMIT 15",
            $params
        ) ?: [];
        if (!class_exists('AlunoLancamentoNotaHelper', false)) {
            require_once __DIR__ . '/../../Helpers/AlunoLancamentoNotaHelper.php';
        }
        $alunos = [];
        foreach ($rows as $row) {
            if (!AlunoLancamentoNotaHelper::deveExibirAluno($row, 'nome')) {
                continue;
            }
            $transferido = !empty($row['transferido']);
            $nome = AlunoLancamentoNotaHelper::rotuloNomeComTransferencia(
                trim((string) ($row['nome'] ?? '')),
                $transferido
            );
            $turma = trim((string) ($row['turma_nome'] ?? ''));
            $ra = trim((string) ($row['ra'] ?? ''));
            $rotulo = $turma !== '' ? ($turma . ' · ' . $nome) : $nome;
            if ($ra !== '') {
                $rotulo .= ' · RA ' . $ra;
            }
            $alunos[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => $nome,
                'ra' => $ra,
                'codigo_aluno' => trim((string) ($row['codigo_aluno'] ?? '')),
                'turma_nome' => $turma,
                'transferido' => $transferido ? 1 : 0,
                'rotulo' => $rotulo,
            ];
        }
        $this->json(['success' => true, 'alunos' => $alunos]);
    }

    public function boletimCoordenacao()
    {
        if (!$this->enforceAdminPermissionKey('relatorios_gerais', 'visualizar', false)) {
            return;
        }

        $user = $this->auth->getUser();
        $fonte = $this->parseFonteBoletimCoordenacao();
        $turmaId = max(0, (int) ($_GET['turma_id'] ?? 0));
        $cursoId = max(0, (int) ($_GET['curso_id'] ?? 0));
        $anoLetivo = max(0, (int) ($_GET['ano_letivo'] ?? 0));
        $periodo = $this->parsePeriodoBoletimCoordenacao($anoLetivo);
        $alunoQ = $this->parseAlunoBuscaBoletimCoordenacao();
        $notaAbaixoDe = $this->parseNotaAbaixoDeBoletim($_GET['nota_abaixo_de'] ?? null);
        $materiasExibicao = $this->parseMateriasExibicaoBoletim($_GET['materias_exibicao'] ?? 'todas');
        $incluirAssinatura = !empty($_GET['assinatura']);
        $incluirAntigas = false;
        $selecionados = $fonte === 'demonstrativo'
            ? $this->eventosVigentesBoletimCoordenacao($anoLetivo, $periodo)
            : [];
        $executar = !empty($_GET['executar']);
        if ($fonte === 'vida_escolar') {
            $executar = $executar && $anoLetivo > 0;
        } else {
            $executar = $executar && $selecionados !== [];
        }
        $relatorio = null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        if ($executar) {
            if ($fonte === 'vida_escolar') {
                $relatorio = $this->paginarAlunosRelatorioBoletimCoordenacao(
                    $this->montarRelatorioVidaEscolarCoordenacao($anoLetivo, $turmaId, $notaAbaixoDe, $materiasExibicao, $alunoQ, $periodo, $cursoId),
                    $pagina,
                    20
                );
                $relatorio['grupos'] = [[
                    'alunos' => $relatorio['alunos'],
                    'columns' => $relatorio['columns'],
                    'decimal_places' => $relatorio['decimal_places'],
                    'regra_id' => 0,
                    'evento_rotulo' => (string) ($relatorio['evento_nome'] ?? ''),
                    'evento_detalhe' => '',
                ]];
                $relatorio['indice'] = [];
                $relatorio['eventos_total'] = 1;
            } else {
                $relatorio = $this->montarPaginaEventosBoletimCoordenacao(
                    $selecionados,
                    $turmaId,
                    $notaAbaixoDe,
                    $materiasExibicao,
                    $pagina,
                    $alunoQ,
                    $cursoId
                );
                if ($fonte === 'demonstrativo') {
                    $relatorio['fonte'] = 'demonstrativo';
                    $relatorio['grupos'] = $this->anexarHtmlDemonstrativoGrupos((array) ($relatorio['grupos'] ?? []));
                }
            }
            if (is_array($relatorio)) {
                $relatorio = $this->anexarHistoricoObservacaoRelatorio($relatorio);
            }
        }

        $flash = $this->getFlashMessage();
        $zipJob = $this->resolverZipJobBoletinsVidaEscolar();
        $this->viewWithLayout('admin', 'admin/reports/boletim_coordenacao', [
            'title' => 'Notas da Coordenação - EducaTudo',
            'user' => $user,
            'current_page' => 'reports_boletim_coordenacao',
            'fonte' => $fonte,
            'incluir_antigas' => $incluirAntigas,
            'anos_letivos' => $anosLista = $this->listarAnosBoletimCoordenacao(),
            'periodos_por_ano' => $this->mapaPeriodosBoletimCoordenacao($anosLista),
            'periodo' => $periodo,
            'turmas' => $this->listarTurmasBoletimCoordenacao(),
            'cursos' => $this->listarCursosBoletimCoordenacao(),
            'curso_id' => $cursoId,
            'eventos_selecionados' => [],
            'selecionar_todos' => false,
            'evento_selecionado' => '',
            'ano_letivo' => $anoLetivo,
            'turma_id' => $turmaId,
            'aluno_q' => $alunoQ,
            'nota_abaixo_de' => $notaAbaixoDe,
            'materias_exibicao' => $materiasExibicao,
            'incluir_assinatura' => $incluirAssinatura,
            'executar' => $executar,
            'aviso_sem_vigente' => $fonte === 'demonstrativo' && !empty($_GET['executar']) && $selecionados === [],
            'relatorio' => $relatorio,
            'pode_editar_observacao' => $this->podeEditarObservacaoBoletimCoordenacao($user),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => in_array((string) ($flash['type'] ?? ''), ['success', 'info'], true)
                ? (($flash['message'] ?? '') !== '' ? 'success' : '')
                : (($flash['message'] ?? '') !== '' ? 'error' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
            'zip_job' => $zipJob,
        ]);
    }

    public function limparObservacoesBoletimCoordenacao()
    {
        if (!$this->enforceAdminPermissionKey('relatorios_gerais', 'visualizar', false)) {
            return;
        }
        $this->aplicarFiltrosPostBoletimCoordenacao();
        $user = $this->auth->getUser();
        if (!$this->podeEditarObservacaoBoletimCoordenacao($user)) {
            $this->setFlashMessage('Acesso não autorizado para apagar observações.', 'error');
            $this->redirect($this->urlVoltarBoletimCoordenacao());
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Atualize a página e tente de novo.', 'error');
            $this->redirect($this->urlVoltarBoletimCoordenacao());
            return;
        }

        $ids = $this->idsAlunosRelatorioCoordenacaoAtual();
        $apagadas = 0;
        if ($ids !== []) {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $cfg = new BoletimConfig();
            $cfg->ensureSchema();
            $usuarioId = (int) ($user['id'] ?? 0);
            $usuarioNome = (string) ($user['nome'] ?? '');
            foreach ($ids as $alunoId) {
                $row = $cfg->getObservacaoCoordenacao($alunoId);
                if (trim((string) ($row['conteudo'] ?? '')) === '') {
                    continue;
                }
                $cfg->limparObservacaoCoordenacao($alunoId, $usuarioId, $usuarioNome);
                $apagadas++;
            }
        }

        if ($apagadas === 0) {
            $this->setFlashMessage('Nenhum aluno deste relatório tinha observação atual.', 'info');
        } else {
            $this->setFlashMessage(
                'Observação atual apagada de ' . $apagadas . ' aluno' . ($apagadas === 1 ? '' : 's') . '. O texto continua salvo nas versões.',
                'success'
            );
        }
        $pagina = max(1, (int) ($_POST['pagina'] ?? 1));
        $this->redirect($this->urlVoltarBoletimCoordenacao($pagina > 1 ? ['pagina' => $pagina] : []));
    }

    private function aplicarFiltrosPostBoletimCoordenacao(): void
    {
        foreach (['fonte', 'ano_letivo', 'periodo', 'curso_id', 'turma_id', 'aluno_q', 'nota_abaixo_de', 'materias_exibicao', 'assinatura', 'incluir_antigas', 'evento', 'eventos', 'pagina', 'executar'] as $chave) {
            if (array_key_exists($chave, $_POST)) {
                $_GET[$chave] = $_POST[$chave];
            }
        }
    }

    /**
     * Alunos de todas as páginas do filtro atual, não só os 20 da tela.
     *
     * @return list<int>
     */
    private function idsAlunosRelatorioCoordenacaoAtual(): array
    {
        $fonte = $this->parseFonteBoletimCoordenacao();
        $turmaId = max(0, (int) ($_GET['turma_id'] ?? 0));
        $cursoId = max(0, (int) ($_GET['curso_id'] ?? 0));
        $anoLetivo = max(0, (int) ($_GET['ano_letivo'] ?? 0));
        $periodo = $this->parsePeriodoBoletimCoordenacao($anoLetivo);
        $alunoQ = $this->parseAlunoBuscaBoletimCoordenacao();
        $notaAbaixoDe = $this->parseNotaAbaixoDeBoletim($_GET['nota_abaixo_de'] ?? null);
        $materiasExibicao = $this->parseMateriasExibicaoBoletim($_GET['materias_exibicao'] ?? 'todas');
        $ids = [];
        $coletar = static function ($alunos) use (&$ids): void {
            foreach ((array) $alunos as $aluno) {
                if (!is_array($aluno)) {
                    continue;
                }
                $id = (int) ($aluno['id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        };
        if ($fonte === 'vida_escolar') {
            if ($anoLetivo <= 0) {
                return [];
            }
            $relatorio = $this->montarRelatorioVidaEscolarCoordenacao($anoLetivo, $turmaId, $notaAbaixoDe, $materiasExibicao, $alunoQ, $periodo, $cursoId);
            $coletar($relatorio['alunos'] ?? []);

            return array_values($ids);
        }
        foreach ($this->eventosVigentesBoletimCoordenacao($anoLetivo, $periodo) as $atual) {
            $bloco = $this->montarRelatorioBoletimCoordenacao(
                (int) $atual['regra_id'],
                (string) $atual['periodo_ref'],
                $turmaId,
                $notaAbaixoDe,
                $materiasExibicao,
                $alunoQ,
                (int) ($atual['geracao_id'] ?? 0),
                !empty($atual['usar_vigente']),
                $cursoId
            );
            $coletar($bloco['alunos'] ?? []);
        }

        return array_values($ids);
    }

    /**
     * @param array<string,mixed> $relatorio
     * @return array<string,mixed>
     */
    private function anexarHistoricoObservacaoRelatorio(array $relatorio): array
    {
        $ids = [];
        $coletar = static function ($alunos) use (&$ids): void {
            foreach ((array) $alunos as $aluno) {
                if (!is_array($aluno)) {
                    continue;
                }
                $id = (int) ($aluno['id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        };
        $coletar($relatorio['alunos'] ?? []);
        foreach ((array) ($relatorio['grupos'] ?? []) as $grupo) {
            if (is_array($grupo)) {
                $coletar($grupo['alunos'] ?? []);
            }
        }
        if ($ids === []) {
            return $relatorio;
        }
        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $cfg = new \BoletimConfig();
            $cfg->ensureSchema();
            $versoes = $cfg->listarVersoesObservacaoPorAlunos(array_values($ids));
            $log = $cfg->listarLogObservacaoPorAlunos(array_values($ids));
        } catch (\Throwable $e) {
            error_log('Histórico da observação da coordenação: ' . $e->getMessage());

            return $relatorio;
        }
        $aplicar = static function (array $aluno) use ($versoes, $log): array {
            $id = (int) ($aluno['id'] ?? 0);
            $aluno['observacao_versoes'] = $versoes[$id] ?? [];
            $aluno['observacao_log'] = $log[$id] ?? [];

            return $aluno;
        };
        if (isset($relatorio['alunos']) && is_array($relatorio['alunos'])) {
            foreach ($relatorio['alunos'] as $i => $aluno) {
                if (is_array($aluno)) {
                    $relatorio['alunos'][$i] = $aplicar($aluno);
                }
            }
        }
        if (isset($relatorio['grupos']) && is_array($relatorio['grupos'])) {
            foreach ($relatorio['grupos'] as $g => $grupo) {
                if (!is_array($grupo) || !is_array($grupo['alunos'] ?? null)) {
                    continue;
                }
                foreach ($grupo['alunos'] as $i => $aluno) {
                    if (is_array($aluno)) {
                        $relatorio['grupos'][$g]['alunos'][$i] = $aplicar($aluno);
                    }
                }
            }
        }

        return $relatorio;
    }

    public function exportarBoletimCoordenacao()
    {
        if (!$this->enforceAdminPermissionKey('relatorios_gerais', 'visualizar', false)) {
            return;
        }
        $fonte = $this->parseFonteBoletimCoordenacao();
        $turmaId = max(0, (int) ($_GET['turma_id'] ?? 0));
        $cursoId = max(0, (int) ($_GET['curso_id'] ?? 0));
        $anoLetivo = max(0, (int) ($_GET['ano_letivo'] ?? 0));
        $periodo = $this->parsePeriodoBoletimCoordenacao($anoLetivo);
        $alunoQ = $this->parseAlunoBuscaBoletimCoordenacao();
        $notaAbaixoDe = $this->parseNotaAbaixoDeBoletim($_GET['nota_abaixo_de'] ?? null);
        $materiasExibicao = $this->parseMateriasExibicaoBoletim($_GET['materias_exibicao'] ?? 'todas');
        $incluirAssinatura = !empty($_GET['assinatura']);
        $formato = strtolower(trim((string) ($_GET['formato'] ?? 'pdf')));
        if (!in_array($formato, ['pdf', 'excel', 'json', 'txt'], true)) {
            $this->redirect('/admin/reports/boletim-coordenacao');
            return;
        }
        if ($fonte === 'vida_escolar') {
            if ($anoLetivo <= 0) {
                $this->redirect('/admin/reports/boletim-coordenacao');
                return;
            }
            $relatorios = [$this->montarRelatorioVidaEscolarCoordenacao($anoLetivo, $turmaId, $notaAbaixoDe, $materiasExibicao, $alunoQ, $periodo, $cursoId)];
        } else {
            $selecionados = $this->eventosVigentesBoletimCoordenacao($anoLetivo, $periodo);
            if ($selecionados === []) {
                $this->redirect('/admin/reports/boletim-coordenacao');
                return;
            }
            $relatorios = [];
            foreach ($selecionados as $evento) {
                $relatorioEvento = $this->montarRelatorioBoletimCoordenacao(
                    (int) $evento['regra_id'],
                    (string) $evento['periodo_ref'],
                    $turmaId,
                    $notaAbaixoDe,
                    $materiasExibicao,
                    $alunoQ,
                    (int) ($evento['geracao_id'] ?? 0),
                    !empty($evento['usar_vigente']),
                    $cursoId
                );
                if (trim((string) ($evento['nome_exibicao'] ?? '')) !== '') {
                    $relatorioEvento['evento_nome'] = (string) $evento['nome_exibicao'];
                }
                $relatorios[] = $relatorioEvento;
            }
        }
        $relatorio = $relatorios[0];
        $slug = count($relatorios) > 1
            ? 'eventos'
            : (preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($relatorio['evento_nome'] ?? 'boletim')) ?: 'boletim');
        $slug = trim((string) $slug, '_-') ?: 'boletim';
        $filenameBase = 'notas_coordenacao_' . $slug . '_' . date('Ymd_His');

        if ($formato === 'json') {
            $this->exportarBoletimCoordenacaoJson($relatorios, $filenameBase);
            return;
        }
        if ($formato === 'txt') {
            $this->exportarBoletimCoordenacaoTxt($relatorios, $incluirAssinatura, $filenameBase);
            return;
        }
        if ($formato === 'excel') {
            $this->exportarPlanilhaMediasBoletim($relatorios, $filenameBase);
            return;
        }

        if ($fonte === 'vida_escolar') {
            $this->exportarBoletinsVidaEscolarLote($relatorio, $filenameBase);
            return;
        }

        if (count($relatorios) > 1) {
            $this->setFlashMessage('Com vários boletins, use Excel ou JSON. O PDF continua valendo para um boletim por vez.', 'error');
            $this->redirect($this->urlVoltarBoletimCoordenacao());
            return;
        }

        if (count((array) ($relatorio['alunos'] ?? [])) > 80) {
            $this->setFlashMessage(
                'O lote tem mais de 80 alunos. Filtre por turma para exportar o PDF.',
                'error'
            );
            $this->redirect($this->urlVoltarBoletimCoordenacao());
            return;
        }

        $this->exportarBoletimCoordenacaoTabelaPdf($relatorio, $incluirAssinatura, $filenameBase);
    }

    /**
     * Enfileira ZIP em segundo plano: um PDF por aluno.
     *
     * @param array<string,mixed> $relatorio
     */
    private function exportarBoletinsVidaEscolarLote(array $relatorio, string $filenameBase): void
    {
        $voltar = $this->urlVoltarBoletimCoordenacao();
        if (!class_exists('LayoutHelper', false)) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }
        if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
            $this->setFlashMessage('O módulo Vida Escolar está desativado nesta escola.', 'error');
            $this->redirect($voltar);
            return;
        }

        require_once __DIR__ . '/../../Modulos/vida-escolar/Services/VidaEscolarService.php';
        require_once __DIR__ . '/../../Modulos/vida-escolar/Services/VidaEscolarBoletinsLoteService.php';
        require_once __DIR__ . '/../../Services/AIJobService.php';

        $vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();
        if (!$vida->model()->schemaPronto()) {
            $this->setFlashMessage('Execute a migration da Vida Escolar (painel Master) antes de emitir os boletins.', 'error');
            $this->redirect($voltar);
            return;
        }

        $anoLetivo = (int) ($relatorio['ano_letivo'] ?? 0);
        $alunos = is_array($relatorio['alunos'] ?? null) ? $relatorio['alunos'] : [];
        $alunoIds = [];
        foreach ($alunos as $aluno) {
            $id = (int) ($aluno['id'] ?? 0);
            if ($id > 0) {
                $alunoIds[] = $id;
            }
        }
        if ($anoLetivo <= 0 || $alunoIds === []) {
            $this->setFlashMessage('Não há alunos neste filtro para emitir o boletim.', 'error');
            $this->redirect($voltar);
            return;
        }

        $fichas = $vida->model()->listarFichasAlunosAno($alunoIds, $anoLetivo);
        $idsComFicha = [];
        foreach ($fichas as $ficha) {
            $id = (int) ($ficha['aluno_id'] ?? 0);
            if ($id > 0) {
                $idsComFicha[$id] = $id;
            }
        }
        $alunoIdsFiltrados = [];
        foreach ($alunoIds as $id) {
            if (isset($idsComFicha[$id])) {
                $alunoIdsFiltrados[] = $id;
            }
        }
        if ($alunoIdsFiltrados === []) {
            $this->setFlashMessage(
                'Nenhum aluno deste filtro tem ficha na Vida Escolar para o ano ' . $anoLetivo . '.',
                'error'
            );
            $this->redirect($voltar);
            return;
        }
        if (count($alunoIdsFiltrados) > \App\Modulos\VidaEscolar\Services\VidaEscolarBoletinsLoteService::MAX_ALUNOS) {
            $this->setFlashMessage(
                'O lote tem ' . count($alunoIdsFiltrados) . ' fichas. Filtre por turma (máximo '
                . \App\Modulos\VidaEscolar\Services\VidaEscolarBoletinsLoteService::MAX_ALUNOS . ' boletins por vez).',
                'error'
            );
            $this->redirect($voltar);
            return;
        }

        if (!$this->db->tableExists('ai_jobs')) {
            $this->setFlashMessage(
                'A fila de segundo plano (ai_jobs) não está configurada nesta escola. Execute a migration no painel Master.',
                'error'
            );
            $this->redirect($voltar);
            return;
        }

        $user = $this->auth->getUser();
        $userId = (int) ($user['id'] ?? 0);
        $tenantSlug = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : '';
        if (!is_string($tenantSlug) || $tenantSlug === '') {
            $this->setFlashMessage('Não foi possível identificar a escola para gravar o ZIP. Recarregue a página e tente de novo.', 'error');
            $this->redirect($voltar);
            return;
        }
        $pendente = $this->db->fetch(
            "SELECT id, status, user_id
             FROM ai_jobs
             WHERE job_type = :tipo AND status IN ('pending', 'processing')
             ORDER BY id DESC
             LIMIT 1",
            ['tipo' => \App\Modulos\VidaEscolar\Services\VidaEscolarBoletinsLoteService::TIPO_JOB]
        );
        if (is_array($pendente) && (int) ($pendente['id'] ?? 0) > 0) {
            $jobIdPendente = (int) $pendente['id'];
            if ($userId > 0 && (int) ($pendente['user_id'] ?? 0) === $userId) {
                $_SESSION['vida_escolar_boletins_zip_job'] = $jobIdPendente;
                $this->setFlashMessage(
                    'Os boletins ainda estão sendo gerados em segundo plano. O download começa quando o ZIP ficar pronto.',
                    'info'
                );
                $this->redirect($this->urlVoltarBoletimCoordenacao(['zip_job' => $jobIdPendente]));
                return;
            }
            $this->setFlashMessage(
                'Já existe um ZIP de boletins em geração nesta escola. Aguarde terminar para pedir outro.',
                'error'
            );
            $this->redirect($voltar);
            return;
        }

        try {
            $jobId = \App\Services\AIJobService::enqueue(
                \App\Modulos\VidaEscolar\Services\VidaEscolarBoletinsLoteService::TIPO_JOB,
                [
                    'aluno_ids' => $alunoIdsFiltrados,
                    'ano_letivo' => $anoLetivo,
                    'user_id' => $userId,
                    'tenant_slug' => $tenantSlug,
                    'nome_download' => 'boletins_vida_escolar_' . $filenameBase . '.zip',
                ],
                $userId,
                'admin',
                false
            );
            \App\Services\AIJobService::tentarDispararWorker();
        } catch (\Throwable $e) {
            error_log('ReportAdminController ZIP Vida Escolar: ' . $e->getMessage());
            $this->setFlashMessage(
                'Não foi possível enfileirar o ZIP dos boletins. Tente de novo em instantes.',
                'error'
            );
            $this->redirect($voltar);
            return;
        }

        $_SESSION['vida_escolar_boletins_zip_job'] = $jobId;
        $this->setFlashMessage(
            'Geração iniciada em segundo plano: um PDF por aluno, entregues num ZIP. Com '
            . count($alunoIdsFiltrados)
            . ' boletim(ns) pode levar vários minutos — deixe esta página aberta.',
            'info'
        );
        $this->redirect($this->urlVoltarBoletimCoordenacao(['zip_job' => $jobId]));
    }

    public function baixarZipBoletinsVidaEscolar($id): void
    {
        if (!$this->enforceAdminPermissionKey('relatorios_gerais', 'visualizar', false)) {
            return;
        }

        $jobId = (int) $id;
        $voltar = $this->urlVoltarBoletimCoordenacao($jobId > 0 ? ['zip_job' => $jobId] : []);
        require_once __DIR__ . '/../../Services/AIJobService.php';
        require_once __DIR__ . '/../../Modulos/vida-escolar/Services/VidaEscolarBoletinsLoteService.php';

        $job = \App\Services\AIJobService::getJob($jobId);
        if (!$job || (string) ($job['job_type'] ?? '') !== \App\Modulos\VidaEscolar\Services\VidaEscolarBoletinsLoteService::TIPO_JOB) {
            $this->setFlashMessage('ZIP de boletins não encontrado.', 'error');
            $this->redirect($this->urlVoltarBoletimCoordenacao());
            return;
        }
        if ((string) ($job['status'] ?? '') !== 'done') {
            $this->setFlashMessage('O ZIP ainda não está pronto. Aguarde a geração em segundo plano.', 'info');
            $this->redirect($voltar);
            return;
        }

        $resultado = json_decode((string) ($job['result'] ?? ''), true);
        $nomeDownload = is_array($resultado) ? (string) ($resultado['nome_download'] ?? '') : '';
        $nomeDownload = basename(preg_replace('/[\r\n\t"\\\\]/', '', $nomeDownload) ?: '');
        if ($nomeDownload === '' || !str_ends_with(strtolower($nomeDownload), '.zip')) {
            $nomeDownload = 'boletins_vida_escolar_' . $jobId . '.zip';
        }
        $path = \App\Modulos\VidaEscolar\Services\VidaEscolarBoletinsLoteService::caminhoZip($jobId);
        if ($path === null || !is_file($path)) {
            $this->setFlashMessage('O arquivo ZIP não está mais no disco. Gere os boletins de novo.', 'error');
            $this->redirect($this->urlVoltarBoletimCoordenacao());
            return;
        }

        $tamanho = filesize($path);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
        header('X-Content-Type-Options: nosniff');
        if (is_int($tamanho) && $tamanho > 0) {
            header('Content-Length: ' . $tamanho);
        }
        readfile($path);
        exit;
    }

    /**
     * @return array{id:int,status:string,erro:string,emitidos:int,falhas:int,total:int,iniciado_em:string,finalizado_em:string,pedido_em:string,duracao:string}|null
     */
    private function resolverZipJobBoletinsVidaEscolar(): ?array
    {
        $jobId = max(0, (int) ($_GET['zip_job'] ?? $_SESSION['vida_escolar_boletins_zip_job'] ?? 0));
        if ($jobId <= 0) {
            return null;
        }
        if (!$this->db->tableExists('ai_jobs')) {
            return null;
        }
        require_once __DIR__ . '/../../Services/AIJobService.php';
        require_once __DIR__ . '/../../Modulos/vida-escolar/Services/VidaEscolarBoletinsLoteService.php';
        $job = \App\Services\AIJobService::getJob($jobId);
        if (!$job || (string) ($job['job_type'] ?? '') !== \App\Modulos\VidaEscolar\Services\VidaEscolarBoletinsLoteService::TIPO_JOB) {
            return null;
        }
        $status = (string) ($job['status'] ?? '');
        $resultado = json_decode((string) ($job['result'] ?? ''), true);
        $resultado = is_array($resultado) ? $resultado : [];
        if ($status === 'done') {
            $path = \App\Modulos\VidaEscolar\Services\VidaEscolarBoletinsLoteService::caminhoZip($jobId);
            if ($path === null) {
                $status = 'failed';
            }
        }
        return [
            'id' => $jobId,
            'status' => $status,
            'erro' => (string) ($job['error_message'] ?? ''),
            'emitidos' => (int) ($resultado['emitidos'] ?? 0),
            'falhas' => (int) ($resultado['falhas'] ?? 0),
            'total' => (int) ($resultado['total'] ?? 0),
            'iniciado_em' => $this->formatarHorarioBoletimZip(
                (string) ($resultado['iniciado_em'] ?? '')
            ),
            'finalizado_em' => $this->formatarHorarioBoletimZip(
                (string) ($resultado['finalizado_em'] ?? ($status === 'done' || $status === 'failed' ? ($job['completed_at'] ?? '') : ''))
            ),
            'pedido_em' => $this->formatarHorarioBoletimZip((string) ($job['created_at'] ?? '')),
            'duracao' => $this->formatarDuracaoBoletimZip(
                (string) ($resultado['iniciado_em'] ?? ''),
                (string) ($resultado['finalizado_em'] ?? ($job['completed_at'] ?? ''))
            ),
        ];
    }

    private function formatarHorarioBoletimZip(string $dt): string
    {
        $dt = trim($dt);
        if ($dt === '') {
            return '';
        }
        $ts = strtotime($dt);
        return $ts === false ? '' : date('d/m/Y H:i:s', $ts);
    }

    private function formatarDuracaoBoletimZip(string $inicio, string $fim): string
    {
        $a = strtotime(trim($inicio));
        $b = strtotime(trim($fim));
        if ($a === false || $b === false || $b < $a) {
            return '';
        }
        $seg = $b - $a;
        if ($seg < 60) {
            return $seg . 's';
        }
        $min = intdiv($seg, 60);
        $resto = $seg % 60;
        if ($min < 60) {
            return $resto > 0 ? $min . ' min ' . $resto . 's' : $min . ' min';
        }
        $horas = intdiv($min, 60);
        $min = $min % 60;
        return $min > 0 ? $horas . 'h ' . $min . ' min' : $horas . 'h';
    }

    /**
     * @param array<string,mixed> $extra
     */
    private function urlVoltarBoletimCoordenacao(array $extra = []): string
    {
        $params = [
            'fonte' => $this->parseFonteBoletimCoordenacao(),
            'evento' => (string) ($_GET['evento'] ?? ''),
            'incluir_antigas' => !empty($_GET['incluir_antigas']) ? 1 : 0,
            'ano_letivo' => max(0, (int) ($_GET['ano_letivo'] ?? 0)),
            'periodo' => max(0, (int) ($_GET['periodo'] ?? 0)),
            'curso_id' => max(0, (int) ($_GET['curso_id'] ?? 0)),
            'turma_id' => max(0, (int) ($_GET['turma_id'] ?? 0)),
            'aluno_q' => $this->parseAlunoBuscaBoletimCoordenacao(),
            'nota_abaixo_de' => (string) ($_GET['nota_abaixo_de'] ?? ''),
            'materias_exibicao' => $this->parseMateriasExibicaoBoletim($_GET['materias_exibicao'] ?? 'todas'),
            'assinatura' => !empty($_GET['assinatura']) ? 1 : 0,
            'executar' => 1,
        ];
        if ((string) ($params['evento'] ?? '') !== 'todos') {
            $eventosPedidos = $_GET['eventos'] ?? null;
            if (is_array($eventosPedidos) && $eventosPedidos !== []) {
                $params['eventos'] = $eventosPedidos;
            }
        }
        foreach ($extra as $chave => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }
            $params[$chave] = $valor;
        }
        return '/admin/reports/boletim-coordenacao?' . http_build_query($params);
    }

    private function parseFonteBoletimCoordenacao(): string
    {
        $raw = strtolower(trim((string) ($_GET['fonte'] ?? '')));
        if ($raw === 'evento' || $raw === 'demonstrativo') {
            return 'demonstrativo';
        }
        if ($raw === 'vida_escolar') {
            return 'vida_escolar';
        }
        return 'vida_escolar';
    }

    /**
     * @return list<int>
     */
    private function listarAnosBoletimCoordenacao(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT ano_letivo FROM turmas WHERE ativo = 1 AND ano_letivo IS NOT NULL ORDER BY ano_letivo DESC'
        ) ?: [];
        $anos = [];
        foreach ($rows as $row) {
            $ano = (int) ($row['ano_letivo'] ?? 0);
            if ($ano > 0) {
                $anos[] = $ano;
            }
        }
        if ($anos === []) {
            $anos[] = (int) date('Y');
        }
        return $anos;
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    private function listarCursosBoletimCoordenacao(): array
    {
        foreach (['curso', 'cursos'] as $tabela) {
            try {
                $existe = $this->db->fetch("SHOW TABLES LIKE '{$tabela}'");
                if (!is_array($existe) || $existe === []) {
                    continue;
                }
                try {
                    $rows = $this->db->fetchAll(
                        "SELECT id, nome FROM {$tabela} WHERE ativo = 1 ORDER BY nome ASC"
                    );
                } catch (\Throwable $eAtivo) {
                    $rows = $this->db->fetchAll("SELECT id, nome FROM {$tabela} ORDER BY nome ASC");
                }
            } catch (\Throwable $e) {
                continue;
            }
            $cursos = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                $nome = trim((string) ($row['nome'] ?? ''));
                if ($id <= 0 || $nome === '') {
                    continue;
                }
                $cursos[] = ['id' => $id, 'nome' => $nome];
            }
            if ($cursos !== []) {
                return $cursos;
            }
        }

        return [];
    }

    /**
     * @return list<array{id:int,nome:string,curso_id:int}>
     */
    private function listarTurmasBoletimCoordenacao(): array
    {
        $expr = $this->expressaoCursoTurmaBoletimCoordenacao();
        try {
            $rows = $this->db->fetchAll(
                "SELECT id, nome, {$expr} AS curso_id FROM turmas WHERE ativo = 1 ORDER BY nome ASC"
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $turmas = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $turmas[] = [
                'id' => $id,
                'nome' => (string) ($row['nome'] ?? ''),
                'curso_id' => (int) ($row['curso_id'] ?? 0),
            ];
        }

        return $turmas;
    }

    /**
     * @return list<int>
     */
    private function idsTurmasDoCursoBoletimCoordenacao(int $cursoId): array
    {
        if ($cursoId <= 0) {
            return [];
        }
        $ids = [];
        foreach ($this->listarTurmasBoletimCoordenacao() as $turma) {
            if ((int) $turma['curso_id'] === $cursoId) {
                $ids[] = (int) $turma['id'];
            }
        }

        return $ids;
    }

    /**
     * @return array{0:string,1:array<string,int>}
     */
    private function whereTurmasBoletimCoordenacao(int $turmaId, int $cursoId, string $alias = 'a'): array
    {
        if ($alias !== 'a' && $alias !== 'f') {
            $alias = 'a';
        }
        if ($turmaId > 0) {
            return [" AND {$alias}.turma_id = :turma_filtro", ['turma_filtro' => $turmaId]];
        }
        if ($cursoId <= 0) {
            return ['', []];
        }
        $ids = $this->idsTurmasDoCursoBoletimCoordenacao($cursoId);
        if ($ids === []) {
            return [' AND 1 = 0', []];
        }
        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $chave = 'turma_curso_' . $i;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $id;
        }

        return [' AND ' . $alias . '.turma_id IN (' . implode(',', $placeholders) . ')', $params];
    }

    private function expressaoCursoTurmaBoletimCoordenacao(): string
    {
        $partes = [];
        foreach (['curso_novo_id', 'curso_id'] as $coluna) {
            try {
                $existe = $this->db->fetch("SHOW COLUMNS FROM turmas LIKE '{$coluna}'");
            } catch (\Throwable $e) {
                $existe = false;
            }
            if (is_array($existe) && $existe !== []) {
                $partes[] = 'NULLIF(' . $coluna . ', 0)';
            }
        }
        if ($partes === []) {
            return '0';
        }

        return 'COALESCE(' . implode(', ', $partes) . ', 0)';
    }

    private function exportarBoletimCoordenacaoTabelaPdf(array $relatorio, bool $incluirAssinatura, string $filenameBase): void
    {
        $logoData = $this->resolveSchoolLogoForCoordinationReportPdf();
        if ($logoData === '') {
            $logoPath = __DIR__ . '/../../../logo-educatudo.png';
            if (is_file($logoPath) && is_readable($logoPath)) {
                $logoBin = @file_get_contents($logoPath);
                if (is_string($logoBin) && $logoBin !== '') {
                    $logoData = 'data:image/png;base64,' . base64_encode($logoBin);
                }
            }
        }

        ob_start();
        extract([
            'relatorio' => $relatorio,
            'incluir_assinatura' => $incluirAssinatura,
            'logo_data' => $logoData,
            'gerado_em' => date('d/m/Y H:i'),
        ], EXTR_SKIP);
        require __DIR__ . '/../../Views/admin/reports/boletim_coordenacao_pdf.php';
        $html = (string) ob_get_clean();

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    private function resolveSchoolLogoForCoordinationReportPdf(): string
    {
        try {
            $url = (string) LayoutHelper::getDocumentLogoUrl();
            if ($url === '') {
                return '';
            }
            $parts = parse_url($url) ?: [];
            $query = [];
            if (!empty($parts['query'])) {
                parse_str((string) $parts['query'], $query);
            }
            $filePath = '';
            $key = isset($query['key']) ? (string) $query['key'] : '';
            $type = isset($query['type']) ? (string) $query['type'] : 'layout';
            if ($key !== '') {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $media = new MediaStorageService($this->config);
                $localPath = $media->getLocalPath($type, $key);
                if ($localPath !== null && is_file($localPath) && is_readable($localPath)) {
                    $filePath = $localPath;
                }
            }
            if ($filePath === '' && !empty($parts['path'])) {
                $relative = ltrim((string) $parts['path'], '/');
                foreach ([__DIR__ . '/../../../public/' . $relative, __DIR__ . '/../../../' . $relative] as $candidate) {
                    if (is_file($candidate) && is_readable($candidate)) {
                        $filePath = $candidate;
                        break;
                    }
                }
            }
            if ($filePath === '') {
                return '';
            }
            $bin = @file_get_contents($filePath);
            if (!is_string($bin) || $bin === '') {
                return '';
            }
            $mimeMap = [
                'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            ];
            $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
            return 'data:' . ($mimeMap[$ext] ?? 'image/png') . ';base64,' . base64_encode($bin);
        } catch (\Throwable $e) {
            error_log('ReportAdminController resolveSchoolLogoForCoordinationReportPdf: ' . $e->getMessage());
            return '';
        }
    }

    private function listarEventosBoletimCoordenacao(bool $incluirAntigas = false): array
    {
        $selExtras = '';
        $groupExtras = '';
        try {
            $colExtras = $this->db->fetch("SHOW COLUMNS FROM boletim_regras LIKE 'extras_json'");
            if (is_array($colExtras) && $colExtras !== []) {
                $selExtras = 'r.extras_json,';
                $groupExtras = ', r.extras_json';
            }
            $colVis = $this->db->fetch("SHOW COLUMNS FROM boletim_regras LIKE 'vis_coordenacao'");
            if (is_array($colVis) && $colVis !== []) {
                $selExtras .= 'r.vis_coordenacao,';
                $groupExtras .= ', r.vis_coordenacao';
            }
        } catch (\Throwable $eExtras) {
            $selExtras = '';
            $groupExtras = '';
        }
        $selCriado = '';
        try {
            $colCriado = $this->db->fetch("SHOW COLUMNS FROM boletim_resultados_gerados LIKE 'created_at'");
            if (is_array($colCriado) && $colCriado !== []) {
                $selCriado = 'MIN(g.created_at) AS criado_em,';
            }
        } catch (\Throwable $eCriado) {
            $selCriado = '';
        }
        $sqlBase = "SELECT g.regra_id, g.periodo_ref, r.nome, r.ano_letivo, r.bimestre, r.series_ids, r.exibir_em,
                    {$selExtras}
                    {$selCriado}
                    COUNT(DISTINCT g.aluno_id) AS total_alunos,
                    GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome ASC SEPARATOR ', ') AS turmas_nomes,
                    MAX(g.updated_at) AS updated_at,
                    %s
                    MAX(g.vigente) AS vigente
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             INNER JOIN alunos a ON a.id = g.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE g.preview = 0 AND r.ativo = 1
               AND r.exibir_em IN ('boletim', 'notas') AND a.ativo = 1
             GROUP BY g.regra_id, g.periodo_ref, r.nome, r.ano_letivo, r.bimestre, r.series_ids, r.exibir_em{$groupExtras}%s
             ORDER BY COALESCE(r.ano_letivo, 0) DESC, COALESCE(r.bimestre, 0) ASC, updated_at DESC, r.nome ASC";
        $eventos = [];
        try {
            $eventos = $this->db->fetchAll(sprintf($sqlBase, 'g.geracao_id AS geracao_id, MAX(g.versao) AS versao,', ', g.geracao_id')) ?: [];
        } catch (\Throwable $eVersao) {
            $eventos = $this->db->fetchAll(sprintf($sqlBase, 'NULL AS geracao_id, 0 AS versao,', '')) ?: [];
        }

        // Uma vigente por regra: a mais recente marcada vigente; demais viram "anterior" na UI.
        $principalPorRegra = [];
        foreach ($eventos as $ev) {
            $rid = (int) ($ev['regra_id'] ?? 0);
            if ($rid <= 0 || (int) ($ev['vigente'] ?? 0) !== 1) {
                continue;
            }
            $atual = $principalPorRegra[$rid] ?? null;
            $uNovo = strtotime((string) ($ev['updated_at'] ?? '')) ?: 0;
            $uAtual = is_array($atual) ? (strtotime((string) ($atual['updated_at'] ?? '')) ?: 0) : -1;
            if ($atual === null || $uNovo > $uAtual) {
                $principalPorRegra[$rid] = $ev;
            }
        }
        // Sem vigente marcada: usa a geração mais recente da regra.
        foreach ($eventos as $ev) {
            $rid = (int) ($ev['regra_id'] ?? 0);
            if ($rid <= 0 || isset($principalPorRegra[$rid])) {
                continue;
            }
            $melhor = null;
            $melhorTs = -1;
            foreach ($eventos as $ev2) {
                if ((int) ($ev2['regra_id'] ?? 0) !== $rid) {
                    continue;
                }
                $ts = strtotime((string) ($ev2['updated_at'] ?? '')) ?: 0;
                if ($melhor === null || $ts > $melhorTs) {
                    $melhor = $ev2;
                    $melhorTs = $ts;
                }
            }
            if (is_array($melhor)) {
                $principalPorRegra[$rid] = $melhor;
            }
        }

        // Dentro do período vigente, a geração mais nova é a vigente. As anteriores ficam no histórico.
        $ultimaGeracaoPorPeriodo = [];
        foreach ($eventos as $ev) {
            $rid = (int) ($ev['regra_id'] ?? 0);
            $periodo = trim((string) ($ev['periodo_ref'] ?? ''));
            $principal = $principalPorRegra[$rid] ?? null;
            if ($rid <= 0 || $periodo === '' || !is_array($principal)) {
                continue;
            }
            if (trim((string) ($principal['periodo_ref'] ?? '')) !== $periodo) {
                continue;
            }
            $chave = $rid . '|' . $periodo;
            $ts = strtotime((string) ($ev['criado_em'] ?? $ev['updated_at'] ?? '')) ?: 0;
            $gid = (int) ($ev['geracao_id'] ?? 0);
            $atual = $ultimaGeracaoPorPeriodo[$chave] ?? null;
            if ($atual === null || $ts > (int) $atual['ts'] || ($ts === (int) $atual['ts'] && $gid > (int) $atual['gid'])) {
                $ultimaGeracaoPorPeriodo[$chave] = ['ts' => $ts, 'gid' => $gid];
            }
        }

        $seriesRows = $this->db->fetchAll(
            "SELECT id, nome, ordem FROM serie WHERE ativo = 1 ORDER BY ordem ASC, nome ASC"
        ) ?: [];
        $seriesById = [];
        foreach ($seriesRows as $serie) {
            $seriesById[(int) ($serie['id'] ?? 0)] = [
                'nome' => trim((string) ($serie['nome'] ?? '')),
                'ordem' => (int) ($serie['ordem'] ?? 0),
            ];
            $serieIdAtual = (int) ($serie['id'] ?? 0);
            if ($serieIdAtual > 0 && $seriesById[$serieIdAtual]['ordem'] <= 0
                && preg_match('/\d+/', $seriesById[$serieIdAtual]['nome'], $matchSerie)) {
                $seriesById[$serieIdAtual]['ordem'] = (int) $matchSerie[0];
            }
        }
        $pathPeriodo = __DIR__ . '/../../Core/PeriodoLetivo.php';
        if (is_file($pathPeriodo)) {
            require_once $pathPeriodo;
        }

        $saida = [];
        foreach ($eventos as $evento) {
            if ($this->eventoOcultoNaListaAvaliacoes($evento) || $this->eventoBloqueadoNaExibicao($evento)) {
                continue;
            }
            $rid = (int) ($evento['regra_id'] ?? 0);
            $periodoRef = trim((string) ($evento['periodo_ref'] ?? ''));
            $principal = $principalPorRegra[$rid] ?? null;
            $ehPrincipal = is_array($principal)
                && trim((string) ($principal['periodo_ref'] ?? '')) === $periodoRef;
            if (!$incluirAntigas && !$ehPrincipal) {
                continue;
            }

            $ids = $this->parseIdsJsonBoletimCoordenacao($evento['series_ids'] ?? null);
            $nomes = [];
            $ordemMax = 0;
            foreach ($ids as $id) {
                if (!isset($seriesById[$id])) {
                    continue;
                }
                $nomes[] = $seriesById[$id]['nome'];
                $ordemMax = max($ordemMax, (int) $seriesById[$id]['ordem']);
            }
            $seriesLabel = $this->joinLabelsBoletimCoordenacao($nomes);
            $evento['series_nomes'] = $seriesLabel;
            $tipoLabel = (($evento['exibir_em'] ?? '') === 'notas') ? 'Notas' : 'Boletim';
            $nomeBase = trim((string) ($evento['nome'] ?? 'Evento'));
            $titulo = $nomeBase;
            if ($seriesLabel !== '' && mb_stripos($titulo, $seriesLabel) === false) {
                $titulo .= ' · ' . $seriesLabel;
            }
            $ano = (int) ($evento['ano_letivo'] ?? 0);
            $bimestre = (int) ($evento['bimestre'] ?? 0);
            $rotuloPeriodo = '';
            if ($bimestre > 0 && class_exists('PeriodoLetivo')) {
                $rotuloPeriodo = PeriodoLetivo::rotulo($ano > 0 ? $ano : (int) date('Y'), $bimestre);
            }
            $detalhes = [$tipoLabel];
            if ($rotuloPeriodo !== '' && mb_stripos($titulo, $rotuloPeriodo) === false) {
                $detalhes[] = $rotuloPeriodo;
            } elseif ($bimestre <= 0 && $periodoRef !== '') {
                $periodoCurto = str_replace(' – ', ' a ', $this->formatarPeriodoRefBoletimCoordenacao($periodoRef));
                if ($periodoCurto !== '') {
                    $detalhes[] = $periodoCurto;
                }
            }
            if ($ano > 0 && mb_stripos($titulo, (string) $ano) === false) {
                $detalhes[] = (string) $ano;
            }
            $geracaoLinha = (int) ($evento['geracao_id'] ?? 0);
            $chaveGeracao = $rid . '|' . $periodoRef;
            $ultima = $ultimaGeracaoPorPeriodo[$chaveGeracao] ?? null;
            $ehVigenteLinha = $ehPrincipal
                && is_array($ultima)
                && $geracaoLinha === (int) ($ultima['gid'] ?? -1);
            if ($ehVigenteLinha) {
                $detalhes[] = 'Vigente';
            } else {
                $detalhes[] = 'Anterior';
            }
            $evento['eh_vigente'] = $ehVigenteLinha;
            $evento['rotulo_bimestre'] = $rotuloPeriodo;
            $evento['nome_exibicao'] = $titulo;
            $evento['nome_detalhe'] = implode(' · ', $detalhes);
            $evento['_serie_ordem'] = $ordemMax;
            $evento['_ano_ordem'] = $ano;
            $evento['_bim_ordem'] = $bimestre;
            $evento['_vigente_ordem'] = $ehPrincipal ? 0 : 1;
            $saida[] = $evento;
        }

        usort($saida, static function (array $a, array $b): int {
            $cmp = ((int) ($b['_ano_ordem'] ?? 0)) <=> ((int) ($a['_ano_ordem'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = ((int) ($b['_serie_ordem'] ?? 0)) <=> ((int) ($a['_serie_ordem'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = ((int) ($a['_bim_ordem'] ?? 0)) <=> ((int) ($b['_bim_ordem'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = ((int) ($a['_vigente_ordem'] ?? 0)) <=> ((int) ($b['_vigente_ordem'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });
        return $saida;
    }

    /**
     * Evento desabilitado na lista de Avaliações também sai de Notas da Coordenação.
     *
     * @param array<string,mixed> $evento
     */
    private function eventoOcultoNaListaAvaliacoes(array $evento): bool
    {
        $raw = $evento['extras_json'] ?? '';
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) && !empty($decoded['oculto_lista_avaliacoes']);
    }

    /**
     * Bloquear exibição (vis_coordenacao = 0) tira o evento de Notas da Coordenação.
     *
     * @param array<string,mixed> $evento
     */
    private function eventoBloqueadoNaExibicao(array $evento): bool
    {
        if (!array_key_exists('vis_coordenacao', $evento)) {
            return false;
        }

        return (int) $evento['vis_coordenacao'] === 0;
    }

    /**
     * Converte periodo_ref técnico (ex.: RANGE:2026-07-01:2026-09-30) em rótulo legível.
     */
    private function formatarPeriodoRefBoletimCoordenacao(string $periodoRef): string
    {
        $periodoRef = trim($periodoRef);
        if ($periodoRef === '') {
            return '';
        }
        if (preg_match('/^RANGE:(\d{4}-\d{2}-\d{2}):(\d{4}-\d{2}-\d{2})$/', $periodoRef, $m)) {
            $iniTs = strtotime($m[1]);
            $fimTs = strtotime($m[2]);
            if ($iniTs !== false && $fimTs !== false) {
                return date('d/m/Y', $iniTs) . ' – ' . date('d/m/Y', $fimTs);
            }
        }
        return $periodoRef;
    }

    private function formatarDataGeracaoBoletimCoordenacao(string $dt): string
    {
        $dt = trim($dt);
        if ($dt === '') {
            return '';
        }
        $ts = strtotime($dt);
        return $ts === false ? '' : date('d/m/Y H:i', $ts);
    }

    private function parseEventoBoletimCoordenacao(string $evento): array
    {
        $parts = explode(':', trim($evento), 2);
        $regraId = isset($parts[0]) ? (int) $parts[0] : 0;
        $periodoRef = isset($parts[1]) ? base64_decode($parts[1], true) : '';
        $periodoRef = is_string($periodoRef) ? trim($periodoRef) : '';
        return [$regraId, $periodoRef];
    }

    /**
     * @return list<string>
     */
    private function valoresEventosPedidosBoletimCoordenacao(): array
    {
        $valores = [];
        $lista = $_GET['eventos'] ?? null;
        if (is_array($lista)) {
            foreach ($lista as $item) {
                $item = trim((string) $item);
                if ($item !== '' && $item !== 'todos') {
                    $valores[] = $item;
                }
            }
        }
        $unico = trim((string) ($_GET['evento'] ?? ''));
        if ($unico !== '' && $unico !== 'todos') {
            $valores[] = $unico;
        }
        return array_values(array_unique($valores));
    }

    /**
     * @param list<array<string,mixed>> $catalogo
     * @return list<array{regra_id:int,periodo_ref:string,nome_exibicao:string,valor:string}>
     */
    private function resolverEventosSelecionadosBoletimCoordenacao(array $catalogo, bool $forcarTodos = false): array
    {
        $porValor = [];
        foreach ($catalogo as $evento) {
            $regraId = (int) ($evento['regra_id'] ?? 0);
            $periodoRef = trim((string) ($evento['periodo_ref'] ?? ''));
            if ($regraId <= 0 || $periodoRef === '') {
                continue;
            }
            $geracaoId = (int) ($evento['geracao_id'] ?? 0);
            $valor = $regraId . ':' . base64_encode($periodoRef) . ':' . $geracaoId;
            $porValor[$valor] = [
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
                'geracao_id' => $geracaoId,
                'usar_vigente' => !empty($evento['eh_vigente']),
                'nome_exibicao' => (string) ($evento['nome_exibicao'] ?? $evento['nome'] ?? 'Evento'),
                'nome_detalhe' => (string) ($evento['nome_detalhe'] ?? ''),
                'ano_letivo' => (int) ($evento['ano_letivo'] ?? 0),
                'bimestre' => (int) ($evento['bimestre'] ?? 0),
                'valor' => $valor,
                'eh_vigente' => !empty($evento['eh_vigente']),
                'versao' => (int) ($evento['versao'] ?? 0),
            ];
        }
        if ($forcarTodos || (string) ($_GET['evento'] ?? '') === 'todos') {
            $vigentes = [];
            foreach ($porValor as $valor => $item) {
                if (!empty($item['eh_vigente'])) {
                    $vigentes[$valor] = $item;
                }
            }

            return array_values($vigentes !== [] ? $vigentes : $porValor);
        }
        $saida = [];
        foreach ($this->valoresEventosPedidosBoletimCoordenacao() as $valor) {
            if (isset($porValor[$valor])) {
                $saida[$valor] = $porValor[$valor];
            }
        }
        return array_values($saida);
    }

    private function versaoConfigReferenciaBoletimCoordenacao(int $regraId): int
    {
        if ($regraId <= 0) {
            return 0;
        }
        try {
            $row = $this->db->fetch(
                'SELECT versao FROM boletim_config_versoes WHERE regra_id = :regra_id ORDER BY versao DESC, id DESC LIMIT 1',
                ['regra_id' => $regraId]
            );
        } catch (\Throwable $e) {
            return 0;
        }

        return (int) (is_array($row) ? ($row['versao'] ?? 0) : 0);
    }

    private function parseAlunoBuscaBoletimCoordenacao(): string
    {
        $q = trim((string) ($_GET['aluno_q'] ?? ''));
        if (function_exists('mb_substr')) {
            return mb_substr($q, 0, 120, 'UTF-8');
        }
        return substr($q, 0, 120);
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function whereAlunoBuscaBoletimCoordenacao(string $alunoQ, string $alias = 'a'): array
    {
        $alunoQ = trim($alunoQ);
        if ($alunoQ === '') {
            return ['', []];
        }
        $like = '%' . $alunoQ . '%';
        $params = [
            'aluno_q_nome' => $like,
            'aluno_q_ra' => $like,
        ];
        if ($this->colunaAlunosCodigoExisteBoletimCoordenacao()) {
            return [
                " AND ({$alias}.nome LIKE :aluno_q_nome OR {$alias}.ra LIKE :aluno_q_ra OR {$alias}.codigo_aluno LIKE :aluno_q_codigo)",
                $params + ['aluno_q_codigo' => $like],
            ];
        }
        return [
            " AND ({$alias}.nome LIKE :aluno_q_nome OR {$alias}.ra LIKE :aluno_q_ra)",
            $params,
        ];
    }

    private function colunaAlunosCodigoExisteBoletimCoordenacao(): bool
    {
        static $existe = null;
        if ($existe !== null) {
            return $existe;
        }
        try {
            $row = $this->db->fetch("SHOW COLUMNS FROM alunos LIKE 'codigo_aluno'");
            $existe = is_array($row) && $row !== [];
        } catch (\Throwable $e) {
            $existe = false;
        }
        return $existe;
    }

    /**
     * @param list<array{regra_id:int,periodo_ref:string,nome_exibicao:string,nome_detalhe?:string,valor:string}> $selecionados
     * @return array<string,mixed>
     */
    private function montarPaginaEventosBoletimCoordenacao(
        array $selecionados,
        int $turmaId,
        ?float $notaAbaixoDe,
        string $materiasExibicao,
        int $pagina,
        string $alunoQ = '',
        int $cursoId = 0
    ): array {
        $blocos = [];
        $totalAlunos = 0;
        $totalLinhas = 0;
        foreach ($selecionados as $atual) {
            $bloco = $this->montarRelatorioBoletimCoordenacao(
                (int) $atual['regra_id'],
                (string) $atual['periodo_ref'],
                $turmaId,
                $notaAbaixoDe,
                $materiasExibicao,
                $alunoQ,
                (int) ($atual['geracao_id'] ?? 0),
                !empty($atual['usar_vigente']),
                $cursoId
            );
            $bloco['evento_rotulo'] = (string) ($atual['nome_exibicao'] ?? $bloco['evento_nome'] ?? 'Boletim');
            $bloco['evento_detalhe'] = (string) ($atual['nome_detalhe'] ?? '');
            $versaoNotas = (int) ($atual['versao'] ?? 0);
            $versaoConfig = $this->versaoConfigReferenciaBoletimCoordenacao((int) $atual['regra_id']);
            $bloco['versao_numero'] = $versaoConfig > 0 ? $versaoConfig : $versaoNotas;
            $bloco['versao_vigente'] = !empty($atual['usar_vigente']) || !empty($atual['eh_vigente']);
            $totalAlunos += count((array) ($bloco['alunos'] ?? []));
            $totalLinhas += (int) ($bloco['total_linhas'] ?? 0);
            $blocos[] = $bloco;
        }

        $porPagina = 20;
        $totalPaginas = max(1, (int) ceil($totalAlunos / $porPagina));
        $pagina = min(max(1, $pagina), $totalPaginas);
        $inicio = ($pagina - 1) * $porPagina;
        $fim = $inicio + $porPagina;
        $cursor = 0;
        $grupos = [];
        $indice = [];
        foreach ($blocos as $bloco) {
            $alunos = array_values((array) ($bloco['alunos'] ?? []));
            $qtd = count($alunos);
            $indice[] = [
                'rotulo' => (string) ($bloco['evento_rotulo'] ?? ''),
                'detalhe' => (string) ($bloco['evento_detalhe'] ?? ''),
                'alunos' => $qtd,
                'pagina' => $qtd === 0 ? $pagina : ((int) floor($cursor / $porPagina) + 1),
            ];
            $slice = [];
            foreach ($alunos as $offset => $aluno) {
                $pos = $cursor + $offset;
                if ($pos >= $inicio && $pos < $fim) {
                    $slice[] = $aluno;
                }
            }
            $cursor += $qtd;
            if ($slice !== []) {
                $bloco['alunos'] = $slice;
                $grupos[] = $bloco;
            }
        }

        $primeiro = $blocos[0] ?? [];
        return [
            'fonte' => 'evento',
            'grupos' => $grupos,
            'indice' => $indice,
            'alunos' => [],
            'columns' => (array) ($primeiro['columns'] ?? []),
            'decimal_places' => (int) ($primeiro['decimal_places'] ?? 1),
            'regra_id' => (int) ($primeiro['regra_id'] ?? 0),
            'evento_nome' => (string) ($primeiro['evento_rotulo'] ?? $primeiro['evento_nome'] ?? ''),
            'total_alunos' => $totalAlunos,
            'total_linhas' => $totalLinhas,
            'nota_abaixo_de' => $notaAbaixoDe,
            'materias_exibicao' => $materiasExibicao,
            'pagina' => $pagina,
            'total_paginas' => $totalPaginas,
            'por_pagina' => $porPagina,
            'eventos_total' => count($blocos),
            'alunos_com_ficha' => 0,
            'alunos_sem_ficha' => 0,
            'ano_letivo' => (int) ($primeiro['ano_letivo'] ?? 0),
        ];
    }

    /**
     * Mesmo quadro do Demonstrativo de Notas no detalhe do aluno, só para a página atual.
     *
     * @param list<array<string,mixed>> $grupos
     * @return list<array<string,mixed>>
     */
    private function anexarHtmlDemonstrativoGrupos(array $grupos): array
    {
        foreach ($grupos as &$grupo) {
            if (!is_array($grupo)) {
                continue;
            }
            $regraId = (int) ($grupo['regra_id'] ?? 0);
            $periodoRef = (string) ($grupo['periodo_ref'] ?? '');
            $alunos = [];
            foreach ((array) ($grupo['alunos'] ?? []) as $aluno) {
                if (!is_array($aluno)) {
                    continue;
                }
                $aluno['demonstrativo_html'] = $this->htmlDemonstrativoNotasAluno(
                    (int) ($aluno['id'] ?? 0),
                    $regraId,
                    $periodoRef
                );
                $alunos[] = $aluno;
            }
            $grupo['alunos'] = $alunos;
        }
        unset($grupo);

        return $grupos;
    }

    public function htmlDemonstrativoNotasDoAluno(int $alunoId): string
    {
        if ($alunoId <= 0) {
            return '';
        }
        try {
            $row = $this->db->fetch(
                'SELECT regra_id, periodo_ref
                 FROM boletim_resultados_gerados
                 WHERE aluno_id = :aluno AND preview = 0
                 ORDER BY vigente DESC, id DESC
                 LIMIT 1',
                ['aluno' => $alunoId]
            );
        } catch (\Throwable $e) {
            return '';
        }
        if (!is_array($row)) {
            return '';
        }

        return $this->htmlDemonstrativoNotasAluno(
            $alunoId,
            (int) ($row['regra_id'] ?? 0),
            (string) ($row['periodo_ref'] ?? '')
        );
    }

    private function htmlDemonstrativoNotasAluno(int $alunoId, int $regraId, string $periodoRef): string
    {
        if ($alunoId <= 0 || $regraId <= 0) {
            return '';
        }
        $nivelBuffer = ob_get_level();
        try {
            $motor = $this->motorDemonstrativoCoordenacao();
            $regra = $this->regraDemonstrativoCoordenacao($regraId);
            if ($motor === null || !is_array($regra) || empty($regra['componentes'])) {
                return '';
            }
            [$ini, $fim] = $this->datasPeriodoDemonstrativoCoordenacao($periodoRef, $regra);
            if ($ini === null || $fim === null) {
                return '';
            }
            if ($ini > $fim) {
                [$ini, $fim] = [$fim, $ini];
            }
            $periodoSim = 'RANGE:' . $ini . ':' . $fim;
            $simulacao = $motor->simularRegraAluno(
                $regra,
                $alunoId,
                $periodoSim,
                $ini,
                $fim,
                [],
                false,
                true
            );
            if (!is_array($simulacao)) {
                return '';
            }
            $simulacao = $motor->montarMatrizDemonstrativoComGrupoHierarquico($simulacao, $regra);
            $matriz = is_array($simulacao['matriz_materias'] ?? null) ? $simulacao['matriz_materias'] : [];
            $cols = is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
            $linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
            if ($cols === [] || $linhas === []) {
                return '';
            }
            $decimalPlaces = ((int) ($regra['decimal_places'] ?? 2) === 1) ? 1 : 2;
            if (!class_exists('BoletimQuadroLayoutHelper', false)) {
                require_once __DIR__ . '/../../Helpers/BoletimQuadroLayoutHelper.php';
            }
            $edicaoSimulacao = null;
            $ev = [
                'grupo_regras_notas_id' => (int) ($regra['grupo_regras_notas_id'] ?? 0),
                'exibir_em' => 'notas',
            ];
            ob_start();
            include __DIR__ . '/../../Views/partials/boletim_quadro_tabela.php';
            $html = (string) ob_get_clean();
            if (stripos($html, '<table') !== false) {
                return $html;
            }
            $tituloTabelaSimples = 'Matéria';
            $ocultar_grupo_hierarquia = false;
            ob_start();
            include __DIR__ . '/../../Views/partials/boletim_quadro_tabela_simples.php';
            $html = (string) ob_get_clean();

            return stripos($html, '<table') === false ? '' : $html;
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivelBuffer) {
                ob_end_clean();
            }
            error_log('Demonstrativo coordenação aluno #' . $alunoId . ' regra #' . $regraId . ': ' . $e->getMessage());

            return '';
        }
    }

    private function motorDemonstrativoCoordenacao(): ?BoletimConfigController
    {
        static $motor = false;
        if ($motor !== false) {
            return $motor instanceof BoletimConfigController ? $motor : null;
        }
        try {
            if (!class_exists('BoletimConfigController', false)) {
                require_once __DIR__ . '/BoletimConfigController.php';
            }
            $motor = class_exists('BoletimConfigController', false) ? new BoletimConfigController(true) : null;
        } catch (\Throwable $e) {
            error_log('Demonstrativo coordenação: ' . $e->getMessage());
            $motor = null;
        }

        return $motor instanceof BoletimConfigController ? $motor : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function regraDemonstrativoCoordenacao(int $regraId): ?array
    {
        static $cfg = null;
        static $regras = [];
        if ($regraId <= 0) {
            return null;
        }
        if (array_key_exists($regraId, $regras)) {
            return is_array($regras[$regraId]) ? $regras[$regraId] : null;
        }
        if ($cfg === null) {
            if (!class_exists('BoletimConfig', false)) {
                require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            }
            $cfg = new BoletimConfig();
        }
        $regra = $cfg->getRuleById($regraId);
        $regras[$regraId] = is_array($regra) ? $regra : null;

        return $regras[$regraId];
    }

    /**
     * @param array<string,mixed> $regra
     * @return array{0:?string,1:?string}
     */
    private function datasPeriodoDemonstrativoCoordenacao(string $periodoRef, array $regra): array
    {
        $periodoRef = trim($periodoRef);
        if (preg_match('/^RANGE:(\d{4}-\d{2}-\d{2}):(\d{4}-\d{2}-\d{2})$/', $periodoRef, $m)) {
            return [$m[1], $m[2]];
        }
        $normalizar = static function (string $raw): ?string {
            $raw = trim($raw);
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $data)) {
                return $data[1];
            }

            return null;
        };

        return [
            $normalizar((string) ($regra['default_data_inicio'] ?? '')),
            $normalizar((string) ($regra['default_data_fim'] ?? '')),
        ];
    }

    private function paginarAlunosRelatorioBoletimCoordenacao(array $relatorio, int $pagina, int $porPagina): array
    {
        $porPagina = max(1, $porPagina);
        $alunos = array_values((array) ($relatorio['alunos'] ?? []));
        $total = count($alunos);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $totalPaginas);
        $relatorio['total_alunos'] = $total;
        $relatorio['pagina'] = $pagina;
        $relatorio['total_paginas'] = $totalPaginas;
        $relatorio['por_pagina'] = $porPagina;
        $relatorio['alunos'] = array_slice($alunos, ($pagina - 1) * $porPagina, $porPagina);
        return $relatorio;
    }

    /**
     * @param list<array<string,mixed>> $relatorios
     */
    private function exportarBoletimCoordenacaoJson(array $relatorios, string $filenameBase): void
    {
        $eventos = [];
        $totalAlunos = 0;
        foreach ($relatorios as $relatorio) {
            $colunas = [];
            foreach ((array) ($relatorio['columns'] ?? []) as $coluna) {
                if (!is_array($coluna)) {
                    continue;
                }
                $colunas[] = [
                    'codigo' => (string) ($coluna['codigo'] ?? ''),
                    'rotulo' => (string) ($coluna['label'] ?? ''),
                ];
            }
            $alunos = [];
            foreach ((array) ($relatorio['alunos'] ?? []) as $aluno) {
                if (!is_array($aluno)) {
                    continue;
                }
                $materias = [];
                foreach ((array) ($aluno['materias'] ?? []) as $materia) {
                    if (!is_array($materia)) {
                        continue;
                    }
                    $notas = [];
                    foreach ($colunas as $coluna) {
                        $codigo = $coluna['codigo'];
                        $rotulo = $coluna['rotulo'] !== '' ? $coluna['rotulo'] : $codigo;
                        $notas[$rotulo] = $materia['notas'][$codigo] ?? null;
                    }
                    $materias[] = [
                        'materia' => (string) ($materia['nome'] ?? ''),
                        'notas' => $notas,
                    ];
                }
                $alunos[] = [
                    'id' => (int) ($aluno['id'] ?? 0),
                    'nome' => (string) ($aluno['nome'] ?? ''),
                    'ra' => (string) ($aluno['ra'] ?? ''),
                    'turma' => (string) ($aluno['turma'] ?? ''),
                    'observacao' => (string) ($aluno['observacao'] ?? ''),
                    'materias' => $materias,
                ];
            }
            $totalAlunos += count($alunos);
            $eventos[] = [
                'regra_id' => (int) ($relatorio['regra_id'] ?? 0),
                'nome' => (string) ($relatorio['evento_nome'] ?? ''),
                'periodo' => (string) ($relatorio['periodo_ref'] ?? ''),
                'ano_letivo' => (int) ($relatorio['ano_letivo'] ?? 0),
                'bimestre' => (string) ($relatorio['bimestre_rotulo'] ?? ''),
                'criado_em' => (string) ($relatorio['evento_criado_em'] ?? ''),
                'colunas' => $colunas,
                'total_alunos' => count($alunos),
                'alunos' => $alunos,
            ];
        }
        $primeiro = $relatorios[0] ?? [];
        $payload = [
            'gerado_em' => date('c'),
            'fonte' => (string) ($primeiro['fonte'] ?? ''),
            'total_eventos' => count($eventos),
            'total_alunos' => $totalAlunos,
            'nota_abaixo_de' => $primeiro['nota_abaixo_de'] ?? null,
            'materias_exibicao' => (string) ($primeiro['materias_exibicao'] ?? 'todas'),
            'eventos' => $eventos,
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.json"');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    private function montarRelatorioBoletimCoordenacao(
        int $regraId,
        string $periodoRef,
        int $turmaId,
        ?float $notaAbaixoDe = null,
        string $materiasExibicao = 'todas',
        string $alunoQ = '',
        int $geracaoId = 0,
        bool $usarVigente = true,
        int $cursoId = 0
    ): array
    {
        [$whereTurma, $paramsTurma] = $this->whereTurmasBoletimCoordenacao($turmaId, $cursoId, 'a');
        [$whereAluno, $paramsAluno] = $this->whereAlunoBuscaBoletimCoordenacao($alunoQ, 'a');
        $params = ['regra_id' => $regraId, 'periodo_ref' => $periodoRef] + $paramsAluno + $paramsTurma;
        $filtroVersao = ' AND g.vigente = 1';
        if (!$usarVigente && $geracaoId > 0) {
            $filtroVersao = ' AND g.geracao_id = :geracao_id';
            $params['geracao_id'] = $geracaoId;
        } elseif (!$usarVigente) {
            $filtroVersao = ' AND g.geracao_id IS NULL';
        }
        $rows = [];
        try {
            $condAlunoVisivel = $this->sqlAlunoVisivelBoletimCoordenacao('a', 't');
            $transferidoSelect = $this->sqlTransferidoSelectBoletimCoordenacao('a', 't');
            $rows = $this->db->fetchAll(
                "SELECT g.aluno_id, g.materia_nome, g.ordem_linha, g.colunas_json, g.notas_json,
                        a.nome AS aluno_nome, a.ra, a.ativo, t.nome AS turma_nome,
                        {$transferidoSelect} AS transferido,
                        r.nome AS evento_nome, r.series_ids, r.decimal_places, r.ano_letivo, r.bimestre,
                        r.created_at AS regra_created_at,
                        o.conteudo AS observacao_conteudo, o.updated_at AS observacao_updated_at
                 FROM boletim_resultados_gerados g
                 INNER JOIN boletim_regras r ON r.id = g.regra_id
                 INNER JOIN alunos a ON a.id = g.aluno_id
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 LEFT JOIN boletim_observacoes o ON o.aluno_id = a.id
                 WHERE g.preview = 0 AND g.regra_id = :regra_id AND g.periodo_ref = :periodo_ref
                   AND {$condAlunoVisivel}{$whereTurma}{$whereAluno}
                   {$filtroVersao}
                 ORDER BY t.nome ASC, a.nome ASC, g.ordem_linha ASC, g.id ASC",
                $params
            ) ?: [];
        } catch (\Throwable $eVersao) {
            $rows = [];
        }
        if ($rows === [] && $usarVigente) {
            // Fallback: vigente do período, ou qualquer linha do período (ambientes sem versão).
            $condAlunoVisivel = $this->sqlAlunoVisivelBoletimCoordenacao('a', 't');
            $transferidoSelect = $this->sqlTransferidoSelectBoletimCoordenacao('a', 't');
            $rows = $this->db->fetchAll(
                "SELECT g.aluno_id, g.materia_nome, g.ordem_linha, g.colunas_json, g.notas_json,
                        a.nome AS aluno_nome, a.ra, a.ativo, t.nome AS turma_nome,
                        {$transferidoSelect} AS transferido,
                        r.nome AS evento_nome, r.series_ids, r.decimal_places, r.ano_letivo, r.bimestre,
                        r.created_at AS regra_created_at,
                        o.conteudo AS observacao_conteudo, o.updated_at AS observacao_updated_at
                 FROM boletim_resultados_gerados g
                 INNER JOIN boletim_regras r ON r.id = g.regra_id
                 INNER JOIN alunos a ON a.id = g.aluno_id
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 LEFT JOIN boletim_observacoes o ON o.aluno_id = a.id
                 WHERE g.preview = 0 AND g.regra_id = :regra_id AND g.periodo_ref = :periodo_ref
                   AND {$condAlunoVisivel}{$whereTurma}{$whereAluno}
                   AND (g.vigente = 1 OR NOT EXISTS (
                        SELECT 1 FROM boletim_resultados_gerados g3
                        WHERE g3.aluno_id = g.aluno_id AND g3.regra_id = g.regra_id
                          AND g3.periodo_ref = g.periodo_ref AND g3.preview = 0 AND g3.vigente = 1
                   ))
                 ORDER BY t.nome ASC, a.nome ASC, g.ordem_linha ASC, g.id ASC",
                $params
            ) ?: [];
        }

        if ($rows === []) {
            $regraMeta = $this->db->fetch(
                'SELECT nome AS evento_nome, series_ids, decimal_places, ano_letivo, bimestre, created_at AS regra_created_at
                 FROM boletim_regras WHERE id = :id LIMIT 1',
                ['id' => $regraId]
            ) ?: [];
            $anoLetivo = (int) ($regraMeta['ano_letivo'] ?? 0);
            $bimestre = (int) ($regraMeta['bimestre'] ?? 0);
            $bimestreRotulo = $this->rotuloBimestreBoletimCoordenacao($anoLetivo, $bimestre);
            return [
                'fonte' => 'evento',
                'regra_id' => $regraId,
                'evento_nome' => $this->nomeEventoBoletimCoordenacao(
                    (string) ($regraMeta['evento_nome'] ?? 'Boletim'),
                    $regraMeta['series_ids'] ?? null
                ),
                'periodo_ref' => $periodoRef,
                'ano_letivo' => $anoLetivo,
                'bimestre' => $bimestre > 0 ? $bimestre : null,
                'bimestre_rotulo' => $bimestreRotulo,
                'evento_criado_em' => $this->formatarDataGeracaoBoletimCoordenacao((string) ($regraMeta['regra_created_at'] ?? '')),
                'decimal_places' => max(0, min(2, (int) ($regraMeta['decimal_places'] ?? 1))),
                'columns' => [],
                'alunos' => [],
                'total_alunos' => 0,
                'total_linhas' => 0,
                'nota_abaixo_de' => $notaAbaixoDe,
                'materias_exibicao' => $materiasExibicao,
                'codigo_media_final' => '',
                'alunos_com_ficha' => 0,
                'alunos_sem_ficha' => 0,
            ];
        }

        $columnsRaw = [];
        if ($rows !== []) {
            $columnsRaw = json_decode((string) ($rows[0]['colunas_json'] ?? ''), true);
            $columnsRaw = is_array($columnsRaw) ? $columnsRaw : [];
        }
        $columns = $this->selecionarColunasNotasBoletim($columnsRaw, true);
        $decimalPlaces = max(0, min(2, (int) ($rows[0]['decimal_places'] ?? 1)));
        if (!class_exists('AlunoLancamentoNotaHelper', false)) {
            require_once __DIR__ . '/../../Helpers/AlunoLancamentoNotaHelper.php';
        }
        $alunos = [];
        foreach ($rows as $row) {
            $alunoId = (int) ($row['aluno_id'] ?? 0);
            if ($alunoId <= 0 || !AlunoLancamentoNotaHelper::deveExibirAluno([
                'nome' => (string) ($row['aluno_nome'] ?? ''),
                'ativo' => $row['ativo'] ?? 1,
                'transferido' => $row['transferido'] ?? 0,
            ])) {
                continue;
            }
            if (!isset($alunos[$alunoId])) {
                $transferido = !empty($row['transferido']);
                $alunos[$alunoId] = [
                    'id' => $alunoId,
                    'nome' => AlunoLancamentoNotaHelper::rotuloNomeComTransferencia(
                        (string) ($row['aluno_nome'] ?? ''),
                        $transferido
                    ),
                    'ra' => (string) ($row['ra'] ?? ''),
                    'turma' => (string) ($row['turma_nome'] ?? ''),
                    'transferido' => $transferido ? 1 : 0,
                    'observacao' => (string) ($row['observacao_conteudo'] ?? ''),
                    'observacao_updated_at' => $row['observacao_updated_at'] ?? null,
                    'materias' => [],
                ];
            }
            $notas = json_decode((string) ($row['notas_json'] ?? ''), true);
            $notas = is_array($notas) ? $notas : [];
            $cells = [];
            foreach ($columns as $column) {
                $value = $notas[$column['codigo']] ?? null;
                $cells[$column['codigo']] = is_numeric($value) ? (float) $value : $value;
            }
            $alunos[$alunoId]['materias'][] = [
                'nome' => (string) ($row['materia_nome'] ?? 'Sem matéria'),
                'notas' => $cells,
            ];
        }

        $alunos = array_values($alunos);
        $codigoMediaFinal = $this->codigoMediaFinalColunasBoletim($columns);
        if ($notaAbaixoDe !== null && $codigoMediaFinal !== '') {
            $alunos = array_values(array_filter($alunos, static function (array $aluno) use ($codigoMediaFinal, $notaAbaixoDe): bool {
                foreach ((array) ($aluno['materias'] ?? []) as $materia) {
                    $nota = $materia['notas'][$codigoMediaFinal] ?? null;
                    if (is_numeric($nota) && (float) $nota < $notaAbaixoDe) {
                        return true;
                    }
                }
                return false;
            }));
            if ($materiasExibicao === 'abaixo') {
                foreach ($alunos as &$alunoFiltrado) {
                    $alunoFiltrado['materias'] = array_values(array_filter(
                        (array) ($alunoFiltrado['materias'] ?? []),
                        static function (array $materia) use ($codigoMediaFinal, $notaAbaixoDe): bool {
                            $nota = $materia['notas'][$codigoMediaFinal] ?? null;
                            return is_numeric($nota) && (float) $nota < $notaAbaixoDe;
                        }
                    ));
                }
                unset($alunoFiltrado);
            }
        }

        $totalLinhasFiltradas = 0;
        foreach ($alunos as $aluno) {
            $totalLinhasFiltradas += count((array) ($aluno['materias'] ?? []));
        }
        $anoLetivo = (int) ($rows[0]['ano_letivo'] ?? 0);
        $bimestre = (int) ($rows[0]['bimestre'] ?? 0);
        $bimestreRotulo = $this->rotuloBimestreBoletimCoordenacao($anoLetivo, $bimestre);
        $eventoCriadoEm = $this->formatarDataGeracaoBoletimCoordenacao((string) ($rows[0]['regra_created_at'] ?? ''));
        $fichasInfo = $this->contarFichasVidaEscolarBoletimCoordenacao($alunos, $anoLetivo);
        return [
            'fonte' => 'evento',
            'regra_id' => $regraId,
            'evento_nome' => $this->nomeEventoBoletimCoordenacao(
                (string) ($rows[0]['evento_nome'] ?? 'Boletim'),
                $rows[0]['series_ids'] ?? null
            ),
            'periodo_ref' => $periodoRef,
            'ano_letivo' => $anoLetivo,
            'bimestre' => $bimestre > 0 ? $bimestre : null,
            'bimestre_rotulo' => $bimestreRotulo,
            'evento_criado_em' => $eventoCriadoEm,
            'decimal_places' => $decimalPlaces,
            'columns' => $columns,
            'alunos' => $alunos,
            'total_alunos' => count($alunos),
            'total_linhas' => $totalLinhasFiltradas,
            'nota_abaixo_de' => $notaAbaixoDe,
            'materias_exibicao' => $materiasExibicao,
            'codigo_media_final' => $codigoMediaFinal,
            'alunos_com_ficha' => $fichasInfo['com'],
            'alunos_sem_ficha' => $fichasInfo['sem'],
        ];
    }

    private function rotuloBimestreBoletimCoordenacao(int $anoLetivo, int $bimestre): string
    {
        if ($bimestre <= 0) {
            return '';
        }
        $pathPeriodo = __DIR__ . '/../../Core/PeriodoLetivo.php';
        if (is_file($pathPeriodo)) {
            require_once $pathPeriodo;
        }
        if (class_exists('PeriodoLetivo', false)) {
            $rotulo = PeriodoLetivo::rotulo($anoLetivo > 0 ? $anoLetivo : (int) date('Y'), $bimestre);
            if ($rotulo !== '') {
                return $rotulo;
            }
        }
        return $bimestre . 'º Bimestre';
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @return array{com:int,sem:int}
     */
    private function contarFichasVidaEscolarBoletimCoordenacao(array $alunos, int $anoLetivo): array
    {
        $total = count($alunos);
        if ($total === 0 || $anoLetivo <= 0) {
            return ['com' => 0, 'sem' => $total];
        }
        if (!class_exists('LayoutHelper', false)) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }
        if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
            return ['com' => 0, 'sem' => $total];
        }
        require_once __DIR__ . '/../../Modulos/vida-escolar/Services/VidaEscolarService.php';
        $vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();
        if (!$vida->model()->schemaPronto()) {
            return ['com' => 0, 'sem' => $total];
        }
        $ids = [];
        foreach ($alunos as $aluno) {
            $id = (int) ($aluno['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $com = count($vida->model()->listarFichasAlunosAno($ids, $anoLetivo));
        return ['com' => $com, 'sem' => max(0, $total - $com)];
    }

    /**
     * @return array<string,mixed>
     */
    private function montarRelatorioVidaEscolarCoordenacao(
        int $anoLetivo,
        int $turmaId,
        ?float $notaAbaixoDe = null,
        string $materiasExibicao = 'todas',
        string $alunoQ = '',
        int $periodo = 0,
        int $cursoId = 0
    ): array {
        $columns = $this->colunasFichaVidaEscolar($anoLetivo, $periodo);
        $codigoCorte = $periodo > 0 ? ('n' . $periodo) : 'n0';
        $base = [
            'fonte' => 'vida_escolar',
            'evento_nome' => 'Boletim ' . $anoLetivo,
            'periodo_ref' => '',
            'ano_letivo' => $anoLetivo,
            'decimal_places' => 1,
            'columns' => $columns,
            'alunos' => [],
            'total_alunos' => 0,
            'total_linhas' => 0,
            'nota_abaixo_de' => $notaAbaixoDe,
            'materias_exibicao' => $materiasExibicao,
            'codigo_media_final' => $codigoCorte,
            'alunos_com_ficha' => 0,
            'alunos_sem_ficha' => 0,
        ];
        if (!class_exists('LayoutHelper', false)) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
        }
        if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
            return $base;
        }
        require_once __DIR__ . '/../../Modulos/vida-escolar/Services/VidaEscolarService.php';
        $vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();
        if (!$vida->model()->schemaPronto()) {
            return $base;
        }

        $fichas = $vida->model()->listarFichasAnoLetivo($anoLetivo, $turmaId);
        if ($turmaId <= 0 && $cursoId > 0) {
            $turmasCurso = array_flip($this->idsTurmasDoCursoBoletimCoordenacao($cursoId));
            $fichas = array_values(array_filter($fichas, static function (array $ficha) use ($turmasCurso): bool {
                return isset($turmasCurso[(int) ($ficha['turma_id'] ?? 0)]);
            }));
        }
        if (trim($alunoQ) !== '') {
            [$whereAluno, $paramsAluno] = $this->whereAlunoBuscaBoletimCoordenacao($alunoQ, 'a');
            $idsPermitidos = [];
            if ($whereAluno !== '') {
                $condBuscaVisivel = $this->sqlAlunoVisivelBoletimCoordenacao('a', null);
                $achados = $this->db->fetchAll(
                    "SELECT a.id FROM alunos a WHERE {$condBuscaVisivel}{$whereAluno} LIMIT 500",
                    $paramsAluno
                ) ?: [];
                foreach ($achados as $rowId) {
                    $id = (int) ($rowId['id'] ?? 0);
                    if ($id > 0) {
                        $idsPermitidos[$id] = true;
                    }
                }
            }
            if ($idsPermitidos === []) {
                return $base;
            }
            $fichas = array_values(array_filter($fichas, static function (array $ficha) use ($idsPermitidos): bool {
                return isset($idsPermitidos[(int) ($ficha['aluno_id'] ?? 0)]);
            }));
        }
        $obs = $this->observacoesAlunosBoletimCoordenacao(array_map(static function (array $f): int {
            return (int) ($f['aluno_id'] ?? 0);
        }, $fichas));

        $alunos = [];
        foreach ($fichas as $ficha) {
            $fichaId = (int) ($ficha['id'] ?? 0);
            $alunoId = (int) ($ficha['aluno_id'] ?? 0);
            if ($fichaId <= 0 || $alunoId <= 0) {
                continue;
            }
            $quadro = $vida->quadro($fichaId);
            $grid = (is_array($quadro) && is_array($quadro['grid'] ?? null)) ? $quadro['grid'] : [];
            $materias = [];
            foreach ($grid as $row) {
                $celulas = is_array($row['celulas'] ?? null) ? $row['celulas'] : [];
                $notas = [];
                foreach ([1, 2, 3, 4, 0] as $periodo) {
                    $cel = is_array($celulas[$periodo] ?? null) ? $celulas[$periodo] : [];
                    $nota = $cel['nota'] ?? null;
                    if ($nota === null || $nota === '') {
                        $nota = $cel['conceito'] ?? null;
                    }
                    $notas['n' . $periodo] = is_numeric($nota) ? (float) $nota : $nota;
                    $faltas = $cel['faltas'] ?? null;
                    $notas['f' . $periodo] = is_numeric($faltas) ? (int) $faltas : $faltas;
                }
                $materias[] = [
                    'nome' => (string) ($row['linha']['componente_nome'] ?? 'Sem matéria'),
                    'notas' => $notas,
                ];
            }
            $obsAluno = $obs[$alunoId] ?? [];
            if (!class_exists('AlunoLancamentoNotaHelper', false)) {
                require_once __DIR__ . '/../../Helpers/AlunoLancamentoNotaHelper.php';
            }
            $transferidoVe = !empty($ficha['transferido']);
            $alunos[] = [
                'id' => $alunoId,
                'ficha_id' => $fichaId,
                'nome' => AlunoLancamentoNotaHelper::rotuloNomeComTransferencia(
                    (string) ($ficha['aluno_nome'] ?? ''),
                    $transferidoVe
                ),
                'ra' => (string) ($ficha['ra'] ?? ''),
                'turma' => (string) ($ficha['turma_nome'] ?? ''),
                'transferido' => $transferidoVe ? 1 : 0,
                'observacao' => (string) ($obsAluno['conteudo'] ?? ''),
                'observacao_updated_at' => $obsAluno['updated_at'] ?? null,
                'materias' => $materias,
            ];
        }

        if ($notaAbaixoDe !== null) {
            $alunos = array_values(array_filter($alunos, static function (array $aluno) use ($notaAbaixoDe, $codigoCorte): bool {
                foreach ((array) ($aluno['materias'] ?? []) as $materia) {
                    $nota = $materia['notas'][$codigoCorte] ?? null;
                    if (is_numeric($nota) && (float) $nota < $notaAbaixoDe) {
                        return true;
                    }
                }
                return false;
            }));
            if ($materiasExibicao === 'abaixo') {
                foreach ($alunos as &$alunoFiltrado) {
                    $alunoFiltrado['materias'] = array_values(array_filter(
                        (array) ($alunoFiltrado['materias'] ?? []),
                        static function (array $materia) use ($notaAbaixoDe, $codigoCorte): bool {
                            $nota = $materia['notas'][$codigoCorte] ?? null;
                            return is_numeric($nota) && (float) $nota < $notaAbaixoDe;
                        }
                    ));
                }
                unset($alunoFiltrado);
            }
        }

        $totalLinhas = 0;
        foreach ($alunos as $aluno) {
            $totalLinhas += count((array) ($aluno['materias'] ?? []));
        }
        $base['alunos'] = $alunos;
        $base['total_alunos'] = count($alunos);
        $base['total_linhas'] = $totalLinhas;
        $base['alunos_com_ficha'] = count($alunos);
        $base['alunos_sem_ficha'] = 0;
        return $base;
    }

    /**
     * @return list<array{codigo:string,label:string,group:string}>
     */
    private function colunasFichaVidaEscolar(int $anoLetivo, int $periodoFiltro = 0): array
    {
        $pathPeriodo = __DIR__ . '/../../Core/PeriodoLetivo.php';
        if (is_file($pathPeriodo) && !class_exists('PeriodoLetivo', false)) {
            require_once $pathPeriodo;
        }
        $info = class_exists('PeriodoLetivo', false)
            ? PeriodoLetivo::doAno($anoLetivo > 0 ? $anoLetivo : (int) date('Y'))
            : ['quantidade' => 4, 'rotulos' => [1 => '1º Bimestre', 2 => '2º Bimestre', 3 => '3º Bimestre', 4 => '4º Bimestre']];
        $numeros = [];
        $quantidade = max(1, (int) ($info['quantidade'] ?? 4));
        for ($i = 1; $i <= $quantidade; $i++) {
            $numeros[] = $i;
        }
        if ($periodoFiltro > 0 && in_array($periodoFiltro, $numeros, true)) {
            $numeros = [$periodoFiltro];
        }
        $rotulos = is_array($info['rotulos'] ?? null) ? $info['rotulos'] : [];
        $unico = count($numeros) === 1;
        $cols = [];
        foreach ($numeros as $periodo) {
            $cols[] = [
                'codigo' => 'n' . $periodo,
                'label' => (string) ($rotulos[$periodo] ?? ($periodo . 'º')),
                'group' => 'b' . $periodo,
            ];
            $cols[] = [
                'codigo' => 'f' . $periodo,
                'label' => $unico ? 'Faltas' : ('Faltas ' . $periodo . 'º'),
                'group' => 'b' . $periodo,
            ];
        }
        return $cols;
    }

    private function parsePeriodoBoletimCoordenacao(int $anoLetivo): int
    {
        $numero = max(0, (int) ($_GET['periodo'] ?? 0));
        if ($numero <= 0) {
            return 0;
        }
        $pathPeriodo = __DIR__ . '/../../Core/PeriodoLetivo.php';
        if (is_file($pathPeriodo) && !class_exists('PeriodoLetivo', false)) {
            require_once $pathPeriodo;
        }
        if (!class_exists('PeriodoLetivo', false)) {
            return $numero >= 1 && $numero <= 4 ? $numero : 0;
        }
        $ano = $anoLetivo > 0 ? $anoLetivo : (int) date('Y');

        return PeriodoLetivo::numeroValido($ano, $numero) ? $numero : 0;
    }

    /**
     * @param list<int> $anos
     * @return array<string, array{rotulo:string, opcoes:list<array{valor:int, rotulo:string}>}>
     */
    private function mapaPeriodosBoletimCoordenacao(array $anos): array
    {
        $pathPeriodo = __DIR__ . '/../../Core/PeriodoLetivo.php';
        if (is_file($pathPeriodo) && !class_exists('PeriodoLetivo', false)) {
            require_once $pathPeriodo;
        }
        $mapa = [];
        foreach ($anos as $ano) {
            $ano = (int) $ano;
            if ($ano <= 0 || !class_exists('PeriodoLetivo', false)) {
                continue;
            }
            $info = PeriodoLetivo::doAno($ano);
            $opcoes = [];
            foreach ((array) ($info['rotulos'] ?? []) as $numero => $rotulo) {
                $opcoes[] = [
                    'valor' => (int) $numero,
                    'rotulo' => (string) $rotulo,
                ];
            }
            $mapa[(string) $ano] = [
                'rotulo' => (string) ($info['rotulo_campo'] ?? 'Bimestre'),
                'opcoes' => $opcoes,
            ];
        }

        return $mapa;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function eventosVigentesBoletimCoordenacao(int $anoLetivo, int $periodo): array
    {
        return $this->filtrarSelecionadosPorAnoPeriodo(
            $this->resolverEventosSelecionadosBoletimCoordenacao($this->listarEventosBoletimCoordenacao(true), true),
            $anoLetivo,
            $periodo
        );
    }

    private function filtrarSelecionadosPorAnoPeriodo(array $selecionados, int $anoLetivo, int $periodo): array
    {
        if ($anoLetivo <= 0 && $periodo <= 0) {
            return $selecionados;
        }
        $saida = [];
        foreach ($selecionados as $evento) {
            if (!is_array($evento)) {
                continue;
            }
            $anoEvento = (int) ($evento['ano_letivo'] ?? 0);
            if ($anoLetivo > 0 && $anoEvento > 0 && $anoEvento !== $anoLetivo) {
                continue;
            }
            $bimestreEvento = (int) ($evento['bimestre'] ?? 0);
            if ($periodo > 0 && $bimestreEvento !== $periodo) {
                continue;
            }
            $saida[] = $evento;
        }

        return $saida;
    }

    /**
     * @param list<int> $alunoIds
     * @return array<int,array{conteudo:string,updated_at:?string}>
     */
    private function observacoesAlunosBoletimCoordenacao(array $alunoIds): array
    {
        $ids = [];
        foreach ($alunoIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $chave = 'o' . $i;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $id;
        }
        $rows = $this->db->fetchAll(
            'SELECT aluno_id, conteudo, updated_at FROM boletim_observacoes WHERE aluno_id IN ('
            . implode(',', $placeholders) . ')',
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[(int) ($row['aluno_id'] ?? 0)] = [
                'conteudo' => (string) ($row['conteudo'] ?? ''),
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }
        return $out;
    }

    private function selecionarColunasNotasBoletim(array $columnsRaw, bool $detalhado = false): array
    {
        if ($detalhado) {
            if (!class_exists('BoletimQuadroLayoutHelper', false)) {
                require_once dirname(__DIR__, 2) . '/Helpers/BoletimQuadroLayoutHelper.php';
            }

            return BoletimQuadroLayoutHelper::colunasDetalheCoordenacao($columnsRaw);
        }
        $selected = [];
        foreach ($columnsRaw as $column) {
            if (!is_array($column)) {
                continue;
            }
            $codigo = trim((string) ($column['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $type = strtolower(trim((string) ($column['layout_type'] ?? '')));
            $group = strtolower(trim((string) ($column['layout_group'] ?? '')));
            $haystack = strtolower((string) (($column['nome'] ?? '') . ' ' . $codigo));
            if ($type === 'resultado' || strpos($haystack, 'result') !== false) {
                continue;
            }
            if (in_array($type, ['faltas', 'rec'], true)
                || strpos($haystack, 'falta') !== false) {
                continue;
            }
            if ($group !== '' && !in_array($group, ['b1', 'b2', 'b3', 'b4', 'final'], true)) {
                continue;
            }
            if ($type !== '' && $type !== 'media' && $type !== 'other') {
                continue;
            }
            $labels = ['b1' => '1º Bimestre', 'b2' => '2º Bimestre', 'b3' => '3º Bimestre', 'b4' => '4º Bimestre', 'final' => 'Média'];
            $selected[] = [
                'codigo' => $codigo,
                'label' => $labels[$group] ?? (string) ($column['nome'] ?? $codigo),
                'group' => $group,
            ];
        }
        return $selected;
    }

    /**
     * @param list<array<string,mixed>> $columns
     */
    private function codigoMediaFinalColunasBoletim(array $columns): string
    {
        $candidatos = [];
        foreach ($columns as $column) {
            $codigo = trim((string) ($column['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $codigoLower = strtolower($codigo);
            $label = strtolower((string) ($column['label'] ?? ''));
            $group = strtolower((string) ($column['group'] ?? ''));
            if (str_contains($codigoLower, 'falt') || str_contains($codigoLower, 'rec')
                || str_contains($label, 'falta') || str_contains($label, 'rec.')) {
                continue;
            }
            if ($codigoLower === 'media_final' || $codigoLower === 'n0') {
                return $codigo;
            }
            $pareceMedia = str_contains($codigoLower, 'media') || str_contains($label, 'média')
                || str_contains($label, 'media');
            if ($group === 'final' && $pareceMedia) {
                $candidatos[] = $codigo;
            }
        }
        if ($candidatos !== []) {
            return $candidatos[0];
        }
        foreach ($columns as $column) {
            $codigo = trim((string) ($column['codigo'] ?? ''));
            $codigoLower = strtolower($codigo);
            $label = strtolower((string) ($column['label'] ?? ''));
            if (str_contains($codigoLower, 'falt') || str_contains($codigoLower, 'rec')) {
                continue;
            }
            if (str_contains($label, 'média') || str_contains($label, 'media') || str_contains($codigoLower, 'media')) {
                return $codigo;
            }
        }
        return '';
    }

    private function parseNotaAbaixoDeBoletim($raw): ?float
    {
        if ($raw === null || is_array($raw)) {
            return null;
        }
        $value = str_replace(',', '.', trim((string) $raw));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        $value = (float) $value;
        return ($value >= 0.0 && $value <= 10.0) ? $value : null;
    }

    private function parseMateriasExibicaoBoletim($raw): string
    {
        return strtolower(trim((string) $raw)) === 'abaixo' ? 'abaixo' : 'todas';
    }

    private function podeEditarObservacaoBoletimCoordenacao(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['tipo'] ?? '') === 'admin') {
            return true;
        }
        return ($user['tipo'] ?? '') === 'admin_escola'
            && in_array((string) ($user['perfil_admin'] ?? ''), ['dev', 'diretor', 'coordenador'], true);
    }

    private function parseIdsJsonBoletimCoordenacao($raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $text = trim((string) $raw);
            $decoded = $text !== '' ? json_decode($text, true) : [];
            $values = is_array($decoded) ? $decoded : preg_split('/[,;\s]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        }
        return array_values(array_unique(array_filter(array_map('intval', (array) $values), static function (int $id): bool {
            return $id > 0;
        })));
    }

    private function joinLabelsBoletimCoordenacao(array $labels): string
    {
        $labels = array_values(array_filter(array_map('trim', $labels), static function (string $label): bool {
            return $label !== '';
        }));
        if (count($labels) <= 1) {
            return $labels[0] ?? '';
        }
        $last = array_pop($labels);
        return implode(', ', $labels) . ' e ' . $last;
    }

    private function nomeEventoBoletimCoordenacao(string $nome, $seriesIdsRaw): string
    {
        $ids = $this->parseIdsJsonBoletimCoordenacao($seriesIdsRaw);
        if ($ids === []) {
            return $nome;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT nome FROM serie WHERE id IN ({$placeholders}) ORDER BY ordem ASC, nome ASC",
            $ids
        ) ?: [];
        $labels = array_map(static function (array $row): string {
            return trim((string) ($row['nome'] ?? ''));
        }, $rows);
        $seriesLabel = $this->joinLabelsBoletimCoordenacao($labels);
        return trim($nome . ($seriesLabel !== '' ? ' ' . $seriesLabel : ''));
    }

    /**
     * Planilha no formato da secretaria: uma aba por turma, alunos nas linhas e matérias nas colunas.
     *
     * @param list<array<string,mixed>> $relatorios
     */
    private function exportarPlanilhaMediasBoletim(array $relatorios, string $filenameBase): void
    {
        $turmas = [];
        foreach ($relatorios as $relatorio) {
            if (!is_array($relatorio)) {
                continue;
            }
            foreach ((array) ($relatorio['alunos'] ?? []) as $aluno) {
                if (!is_array($aluno)) {
                    continue;
                }
                $turma = trim((string) ($aluno['turma'] ?? ''));
                if ($turma !== '') {
                    $turmas[$turma] = true;
                }
            }
        }
        $nomesTurmas = array_keys($turmas);
        $tiposEnsino = $this->tiposEnsinoPorTurmaPlanilha($nomesTurmas);
        $transferidos = $this->transferidosPorTurmaPlanilha($nomesTurmas);
        $variosEventos = count($relatorios) > 1;
        $abas = [];
        $nomesAbas = [];
        foreach ($relatorios as $relatorio) {
            if (!is_array($relatorio)) {
                continue;
            }
            $codigoNota = $this->codigoNotaPlanilhaBoletim($relatorio);
            $casas = max(0, min(2, (int) ($relatorio['decimal_places'] ?? 1)));
            $periodo = $this->rotuloPeriodoPlanilhaBoletim($relatorio);
            $grupos = [];
            foreach ((array) ($relatorio['alunos'] ?? []) as $aluno) {
                if (!is_array($aluno)) {
                    continue;
                }
                $turma = trim((string) ($aluno['turma'] ?? ''));
                if ($turma === '') {
                    $turma = 'Sem turma';
                }
                if (!isset($grupos[$turma])) {
                    $grupos[$turma] = ['materias' => [], 'alunos' => [], 'ids' => []];
                }
                $notas = [];
                foreach ((array) ($aluno['materias'] ?? []) as $materia) {
                    if (!is_array($materia)) {
                        continue;
                    }
                    $nomeMateria = trim((string) ($materia['nome'] ?? ''));
                    if ($nomeMateria === '') {
                        continue;
                    }
                    if (!in_array($nomeMateria, $grupos[$turma]['materias'], true)) {
                        $grupos[$turma]['materias'][] = $nomeMateria;
                    }
                    $valor = $codigoNota !== '' ? ($materia['notas'][$codigoNota] ?? null) : null;
                    $notas[$nomeMateria] = $valor;
                }
                $alunoId = (int) ($aluno['id'] ?? 0);
                $grupos[$turma]['alunos'][] = [
                    'id' => $alunoId,
                    'nome' => (string) ($aluno['nome'] ?? ''),
                    'notas' => $notas,
                ];
                if ($alunoId > 0) {
                    $grupos[$turma]['ids'][$alunoId] = true;
                }
            }
            foreach ($transferidos as $turma => $lista) {
                if (!isset($grupos[$turma])) {
                    continue;
                }
                foreach ($lista as $alunoId => $nomeAluno) {
                    if (isset($grupos[$turma]['ids'][$alunoId])) {
                        continue;
                    }
                    $grupos[$turma]['alunos'][] = [
                        'id' => $alunoId,
                        'nome' => $nomeAluno,
                        'notas' => [],
                        'transferido' => true,
                    ];
                    $grupos[$turma]['ids'][$alunoId] = true;
                }
            }
            if ($grupos === []) {
                $grupos['Sem turma'] = ['materias' => [], 'alunos' => [], 'ids' => []];
            }
            foreach ($grupos as $turma => $grupo) {
                $idsTransferidos = $transferidos[$turma] ?? [];
                $linhas = [];
                foreach ($grupo['alunos'] as $aluno) {
                    $alunoId = (int) ($aluno['id'] ?? 0);
                    $transferido = !empty($aluno['transferido']) || isset($idsTransferidos[$alunoId]);
                    $valores = [];
                    foreach ($grupo['materias'] as $nomeMateria) {
                        if ($transferido) {
                            $valores[] = 'TR';
                            continue;
                        }
                        $valor = $aluno['notas'][$nomeMateria] ?? null;
                        if (is_string($valor) && strtoupper(trim($valor)) === 'TR') {
                            $valores[] = 'TR';
                            continue;
                        }
                        $valores[] = is_numeric($valor) ? (float) $valor : null;
                    }
                    $linhas[] = [
                        'nome' => mb_strtoupper(trim((string) ($aluno['nome'] ?? '')), 'UTF-8'),
                        'valores' => $valores,
                        'ordem' => $this->chaveOrdenacaoNomePlanilha((string) ($aluno['nome'] ?? '')),
                    ];
                }
                usort($linhas, static function (array $a, array $b): int {
                    return $a['ordem'] <=> $b['ordem'];
                });
                $siglas = $this->siglasMateriasPlanilha($grupo['materias']);
                $tituloTurma = $this->rotuloTurmaPlanilha($turma, (string) ($tiposEnsino[$turma] ?? ''));
                $nomeAba = $variosEventos ? trim($periodo . ' ' . $turma) : $turma;
                $abas[] = [
                    'nome' => $this->nomeAbaPlanilha($nomeAba, $nomesAbas),
                    'titulo_turma' => $tituloTurma,
                    'periodo' => $periodo,
                    'siglas' => $siglas,
                    'linhas' => $linhas,
                    'casas' => $casas,
                ];
            }
        }
        if ($abas === []) {
            $abas[] = [
                'nome' => 'Notas',
                'titulo_turma' => 'Turma',
                'periodo' => '',
                'siglas' => [],
                'linhas' => [],
                'casas' => 1,
            ];
        }

        $xlsx = $this->criarXlsxPlanilhaMedias($abas);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
        header('Pragma: no-cache');
        header('Content-Length: ' . strlen($xlsx));
        echo $xlsx;
        exit;
    }

    /**
     * @param list<string> $nomes
     * @return array<string,string>
     */
    private function tiposEnsinoPorTurmaPlanilha(array $nomes): array
    {
        $nomes = array_values(array_unique(array_filter(array_map(static function ($nome): string {
            return trim((string) $nome);
        }, $nomes), static function (string $nome): bool {
            return $nome !== '';
        })));
        if ($nomes === []) {
            return [];
        }
        $params = [];
        $holders = [];
        foreach ($nomes as $i => $nome) {
            $chave = 'turma_nome_' . $i;
            $holders[] = ':' . $chave;
            $params[$chave] = $nome;
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT nome, tipo_ensino FROM turmas WHERE nome IN (' . implode(',', $holders) . ')',
                $params
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome === '' || isset($out[$nome])) {
                continue;
            }
            $out[$nome] = trim((string) ($row['tipo_ensino'] ?? ''));
        }

        return $out;
    }

    /**
     * @param list<string> $nomes
     * @return array<string, array<int, string>>
     */
    private function transferidosPorTurmaPlanilha(array $nomes): array
    {
        $nomes = array_values(array_unique(array_filter(array_map(static function ($nome): string {
            return trim((string) $nome);
        }, $nomes), static function (string $nome): bool {
            return $nome !== '';
        })));
        if ($nomes === []) {
            return [];
        }
        $params = [];
        $holders = [];
        foreach ($nomes as $i => $nome) {
            $chave = 'turma_tr_' . $i;
            $holders[] = ':' . $chave;
            $params[$chave] = $nome;
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT t.nome AS turma_nome, a.id AS aluno_id, a.nome AS aluno_nome
                 FROM matricula m
                 INNER JOIN alunos a ON a.id = m.aluno_id
                 INNER JOIN turmas t ON t.id = m.turma_id
                 WHERE m.status = \'transferido\'
                   AND t.nome IN (' . implode(',', $holders) . ')
                   AND NOT EXISTS (
                        SELECT 1 FROM matricula ma
                        WHERE ma.aluno_id = a.id
                          AND ma.turma_id = m.turma_id
                          AND ma.status = \'ativa\'
                          AND ma.data_saida IS NULL
                   )',
                $params
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        if (!class_exists('AlunoLancamentoNotaHelper', false)) {
            require_once __DIR__ . '/../../Helpers/AlunoLancamentoNotaHelper.php';
        }
        $out = [];
        foreach ($rows as $row) {
            $turma = trim((string) ($row['turma_nome'] ?? ''));
            $alunoId = (int) ($row['aluno_id'] ?? 0);
            if ($turma === '' || $alunoId <= 0) {
                continue;
            }
            $out[$turma][$alunoId] = AlunoLancamentoNotaHelper::rotuloNomeComTransferencia(
                (string) ($row['aluno_nome'] ?? ''),
                true
            );
        }

        return $out;
    }

    private function sqlAlunoVisivelBoletimCoordenacao(string $aliasAluno = 'a', ?string $aliasTurma = 't'): string
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $aliasAluno) ?: 'a';
        $temMatricula = false;
        try {
            $temMatricula = (bool) $this->db->fetch("SHOW TABLES LIKE 'matricula'");
        } catch (\Throwable $e) {
            $temMatricula = false;
        }
        if (!$temMatricula) {
            return "({$a}.ativo = 1 OR {$a}.ativo IS NULL)";
        }
        if ($aliasTurma === null || $aliasTurma === '') {
            return "(
                {$a}.ativo = 1 OR {$a}.ativo IS NULL
                OR EXISTS (
                    SELECT 1 FROM matricula mx
                    WHERE mx.aluno_id = {$a}.id AND mx.status = 'transferido'
                )
            )";
        }
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $aliasTurma) ?: 't';

        return "(
            {$a}.ativo = 1 OR {$a}.ativo IS NULL
            OR EXISTS (
                SELECT 1 FROM matricula mx
                WHERE mx.aluno_id = {$a}.id
                  AND (mx.turma_id = {$t}.id OR {$t}.id IS NULL)
                  AND mx.status = 'transferido'
            )
        )";
    }

    private function sqlTransferidoSelectBoletimCoordenacao(string $aliasAluno = 'a', string $aliasTurma = 't'): string
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $aliasAluno) ?: 'a';
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $aliasTurma) ?: 't';
        $temMatricula = false;
        try {
            $temMatricula = (bool) $this->db->fetch("SHOW TABLES LIKE 'matricula'");
        } catch (\Throwable $e) {
            $temMatricula = false;
        }
        if (!$temMatricula) {
            return '0';
        }

        return "CASE
            WHEN EXISTS (
                SELECT 1 FROM matricula ma
                WHERE ma.aluno_id = {$a}.id
                  AND (ma.turma_id = {$t}.id OR {$t}.id IS NULL)
                  AND ma.status = 'ativa' AND ma.data_saida IS NULL
            ) THEN 0
            WHEN EXISTS (
                SELECT 1 FROM matricula mt
                WHERE mt.aluno_id = {$a}.id
                  AND (mt.turma_id = {$t}.id OR {$t}.id IS NULL)
                  AND mt.status = 'transferido'
            ) THEN 1
            ELSE 0
        END";
    }

    /**
     * @param array<string,mixed> $relatorio
     */
    private function codigoNotaPlanilhaBoletim(array $relatorio): string
    {
        $columns = is_array($relatorio['columns'] ?? null) ? $relatorio['columns'] : [];
        if (!class_exists('BoletimQuadroLayoutHelper', false)) {
            require_once dirname(__DIR__, 2) . '/Helpers/BoletimQuadroLayoutHelper.php';
        }
        $candidatos = [];
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $codigo = trim((string) ($column['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $blob = mb_strtolower($codigo . ' ' . (string) ($column['label'] ?? '') . ' ' . (string) ($column['nome'] ?? ''), 'UTF-8');
            if (str_contains($blob, 'falt')) {
                continue;
            }
            $colunaOficial = $column;
            if (trim((string) ($colunaOficial['nome'] ?? '')) === '') {
                $colunaOficial['nome'] = (string) ($column['label'] ?? '');
            }
            if (BoletimQuadroLayoutHelper::colunaEhResultadoFinalOficial($colunaOficial)) {
                return $codigo;
            }
            $candidatos[] = [$codigo, $blob];
        }
        $bimestre = (int) ($relatorio['bimestre'] ?? 0);
        if ($bimestre > 0) {
            $marca = '/(^|[^0-9])' . $bimestre . '[\sºoa]*bim/u';
            foreach ($candidatos as $candidato) {
                if (preg_match($marca, $candidato[1])) {
                    return $candidato[0];
                }
            }
        }
        foreach ($candidatos as $candidato) {
            if (str_contains($candidato[1], 'media_bim')
                || str_contains($candidato[1], 'média bimestral')
                || str_contains($candidato[1], 'media bimestral')) {
                return $candidato[0];
            }
        }
        foreach ($candidatos as $candidato) {
            if (str_contains($candidato[1], 'bimestre') || preg_match('/\bbim\b/u', $candidato[1])) {
                return $candidato[0];
            }
        }
        $final = trim((string) ($relatorio['codigo_media_final'] ?? ''));
        if ($final !== '') {
            foreach ($candidatos as $candidato) {
                if ($candidato[0] === $final) {
                    return $final;
                }
            }
        }
        $calculado = $this->codigoMediaFinalColunasBoletim($columns);
        if ($calculado !== '') {
            return $calculado;
        }

        return $candidatos[0][0] ?? '';
    }

    /**
     * @param array<string,mixed> $relatorio
     */
    private function rotuloPeriodoPlanilhaBoletim(array $relatorio): string
    {
        $bimestre = (int) ($relatorio['bimestre'] ?? 0);
        $rotulo = trim((string) ($relatorio['bimestre_rotulo'] ?? ''));
        $baixo = mb_strtolower($rotulo, 'UTF-8');
        $tipo = 'BIM';
        if (str_contains($baixo, 'trim')) {
            $tipo = 'TRIM';
        } elseif (str_contains($baixo, 'semestre') || str_contains($baixo, 'sem ')) {
            $tipo = 'SEM';
        }
        if ($bimestre > 0) {
            return $bimestre . 'º ' . $tipo;
        }

        return $rotulo !== '' ? mb_strtoupper($rotulo, 'UTF-8') : 'PERÍODO';
    }

    private function rotuloTurmaPlanilha(string $turma, string $tipoEnsino): string
    {
        $turma = trim($turma);
        if ($turma === '') {
            $turma = 'Turma';
        }
        $tipo = mb_strtolower(trim($tipoEnsino), 'UTF-8');
        $curto = '';
        if (str_contains($tipo, 'fundamental')) {
            $curto = 'E. Fundamental';
        } elseif (str_contains($tipo, 'médio') || str_contains($tipo, 'medio')) {
            $curto = 'E. Médio';
        } elseif (str_contains($tipo, 'infantil')) {
            $curto = 'E. Infantil';
        }
        if ($curto === '') {
            return $turma;
        }
        $turmaBaixa = mb_strtolower($turma, 'UTF-8');
        if (str_contains($turmaBaixa, mb_strtolower($curto, 'UTF-8')) || str_contains($turmaBaixa, 'fundamental') || str_contains($turmaBaixa, 'médio') || str_contains($turmaBaixa, 'medio')) {
            return $turma;
        }

        return $turma . ' - ' . $curto;
    }

    /**
     * @param list<string> $materias
     * @return list<string>
     */
    private function siglasMateriasPlanilha(array $materias): array
    {
        $mapa = [
            'arte' => 'ART',
            'artes' => 'ART',
            'biologia' => 'BIO',
            'ciencias' => 'CIE',
            'educacao fisica' => 'EDU',
            'ensino religioso' => 'REL',
            'espanhol' => 'ESP',
            'filosofia' => 'FIL',
            'fisica' => 'FIS',
            'geografia' => 'GEO',
            'geometria' => 'GEM',
            'gramatica' => 'GRA',
            'historia' => 'HIS',
            'ingles' => 'ING',
            'lingua espanhola' => 'ESP',
            'lingua inglesa' => 'ING',
            'lingua portuguesa' => 'POR',
            'leitura' => 'LEI',
            'leitura e interpretacao' => 'LEI',
            'literatura' => 'LIT',
            'matematica' => 'MAT',
            'musica' => 'MUS',
            'portugues' => 'POR',
            'quimica' => 'QUI',
            'redacao' => 'RED',
            'sociologia' => 'SOC',
        ];
        $usadas = [];
        $siglas = [];
        foreach ($materias as $materia) {
            $chave = $this->chaveOrdenacaoNomePlanilha((string) $materia);
            $base = $mapa[$chave] ?? '';
            if ($base === '') {
                $partes = preg_split('/\s+/', $chave) ?: [];
                $ignorar = ['de', 'da', 'do', 'das', 'dos', 'e', 'a', 'o'];
                foreach ($partes as $parte) {
                    if ($parte === '' || in_array($parte, $ignorar, true)) {
                        continue;
                    }
                    $base .= strtoupper(substr($parte, 0, 1));
                    if (strlen($base) >= 3) {
                        break;
                    }
                }
                if (strlen($base) < 3) {
                    $limpo = preg_replace('/[^a-z]/', '', $chave) ?: '';
                    $base = strtoupper(substr($limpo, 0, 3));
                }
            }
            if ($base === '') {
                $base = 'MAT';
            }
            $sigla = $base;
            $n = 2;
            while (isset($usadas[$sigla])) {
                $sigla = $base . $n;
                $n++;
            }
            $usadas[$sigla] = true;
            $siglas[] = $sigla;
        }

        return $siglas;
    }

    private function chaveOrdenacaoNomePlanilha(string $nome): string
    {
        $nome = mb_strtolower(trim($nome), 'UTF-8');
        return strtr($nome, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);
    }

    /**
     * @param array<string,bool> $usados
     */
    private function nomeAbaPlanilha(string $base, array &$usados): string
    {
        $nome = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[\\\\\\/\\?\\*\\:\\[\\]]/', ' ', $base)));
        if ($nome === '') {
            $nome = 'Turma';
        }
        $nome = mb_substr($nome, 0, 31);
        $candidato = $nome;
        $n = 2;
        while (isset($usados[mb_strtolower($candidato, 'UTF-8')])) {
            $sufixo = ' ' . $n;
            $candidato = mb_substr($nome, 0, 31 - mb_strlen($sufixo)) . $sufixo;
            $n++;
        }
        $usados[mb_strtolower($candidato, 'UTF-8')] = true;

        return $candidato;
    }

    /**
     * @param list<array<string,mixed>> $abas
     */
    private function criarXlsxPlanilhaMedias(array $abas): string
    {
        $xml = static function ($value): string {
            return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };
        $coluna = static function (int $index): string {
            $name = '';
            do {
                $name = chr(65 + ($index % 26)) . $name;
                $index = intdiv($index, 26) - 1;
            } while ($index >= 0);
            return $name;
        };
        $celulaTexto = static function (string $ref, string $value, int $style) use ($xml): string {
            $preserve = trim($value) !== $value || strpos($value, "\n") !== false;
            return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t'
                . ($preserve ? ' xml:space="preserve"' : '') . '>' . $xml($value) . '</t></is></c>';
        };

        $sheetsXml = '';
        $rels = '';
        $overrides = '';
        $files = [];
        foreach (array_values($abas) as $i => $aba) {
            $sheetId = $i + 1;
            $siglas = is_array($aba['siglas'] ?? null) ? array_values($aba['siglas']) : [];
            $linhas = is_array($aba['linhas'] ?? null) ? $aba['linhas'] : [];
            $casas = max(0, min(2, (int) ($aba['casas'] ?? 1)));
            $totalColunas = max(1, count($siglas) + 1);
            $ultimaColuna = $coluna($totalColunas - 1);
            $ultimaLinha = max(3, count($linhas) + 3);
            $sheetRows = [];
            $cabecalho = [$celulaTexto('A1', (string) ($aba['titulo_turma'] ?? 'Turma'), 1)];
            if ($siglas !== []) {
                $cabecalho[] = $celulaTexto('B1', 'MEDIA BIMESTRAL - PARA DIGITAR NO BOLETIM', 1);
                for ($c = 2; $c < $totalColunas; $c++) {
                    $cabecalho[] = $celulaTexto($coluna($c) . '1', '', 1);
                }
            }
            $sheetRows[] = '<row r="1" ht="24" customHeight="1">' . implode('', $cabecalho) . '</row>';
            $periodoCells = [$celulaTexto('A2', (string) ($aba['periodo'] ?? ''), 2)];
            foreach ($siglas as $c => $_sigla) {
                $periodoCells[] = $celulaTexto($coluna($c + 1) . '2', '', 3);
            }
            $sheetRows[] = '<row r="2" ht="20" customHeight="1">' . implode('', $periodoCells) . '</row>';
            $titulos = [$celulaTexto('A3', 'NOME DO ALUNO', 4)];
            foreach ($siglas as $c => $sigla) {
                $titulos[] = $celulaTexto($coluna($c + 1) . '3', (string) $sigla, 4);
            }
            $sheetRows[] = '<row r="3" ht="20" customHeight="1">' . implode('', $titulos) . '</row>';
            $estiloNota = 6 + ($casas * 2);
            $estiloNotaVermelha = $estiloNota + 1;
            foreach (array_values($linhas) as $indice => $linha) {
                $excelRow = $indice + 4;
                $cells = [$celulaTexto('A' . $excelRow, (string) ($linha['nome'] ?? ''), 5)];
                $valores = is_array($linha['valores'] ?? null) ? $linha['valores'] : [];
                foreach ($siglas as $c => $_sigla) {
                    $ref = $coluna($c + 1) . $excelRow;
                    $valor = $valores[$c] ?? null;
                    if (is_int($valor) || is_float($valor)) {
                        $vermelho = round((float) $valor, $casas) <= 6.0;
                        $cells[] = '<c r="' . $ref . '" s="' . ($vermelho ? $estiloNotaVermelha : $estiloNota) . '"><v>' . (float) $valor . '</v></c>';
                        continue;
                    }
                    $texto = trim((string) ($valor ?? ''));
                    $cells[] = $celulaTexto($ref, $texto, 12);
                }
                $sheetRows[] = '<row r="' . $excelRow . '" ht="18" customHeight="1">' . implode('', $cells) . '</row>';
            }
            $merge = '';
            if (count($siglas) > 1) {
                $merge = '<mergeCells count="1"><mergeCell ref="B1:' . $ultimaColuna . '1"/></mergeCells>';
            }
            $larguraMaterias = $totalColunas > 1
                ? '<col min="2" max="' . $totalColunas . '" width="8" customWidth="1"/>'
                : '';
            $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
                . '<dimension ref="A1:' . $ultimaColuna . $ultimaLinha . '"/>'
                . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                . '<sheetFormatPr defaultRowHeight="18"/>'
                . '<cols><col min="1" max="1" width="46" customWidth="1"/>' . $larguraMaterias . '</cols>'
                . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
                . $merge
                . '<pageMargins left="0.4" right="0.4" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
                . '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="1"/>'
                . '</worksheet>';
            $files['xl/worksheets/sheet' . $sheetId . '.xml'] = $sheet;
            $sheetsXml .= '<sheet name="' . $xml((string) ($aba['nome'] ?? ('Turma ' . $sheetId))) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
            $rels .= '<Relationship Id="rId' . $sheetId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetId . '.xml"/>';
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $sheetId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $rels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="3">'
            . '<numFmt numFmtId="164" formatCode="0"/>'
            . '<numFmt numFmtId="165" formatCode="0.0"/>'
            . '<numFmt numFmtId="166" formatCode="0.00"/>'
            . '</numFmts>'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FFFF0000"/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2"><border/>'
            . '<border><left style="medium"><color rgb="FF0000FF"/></left><right style="medium"><color rgb="FF0000FF"/></right><top style="medium"><color rgb="FF0000FF"/></top><bottom style="medium"><color rgb="FF0000FF"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="13">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="166" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' . $overrides . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheetsXml . '</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>',
            'xl/styles.xml' => $styles,
        ] + $files;

        return $this->criarZipArmazenado($files);
    }

    /**
     * @param list<array<string,mixed>> $relatorios
     */
    private function exportarBoletimCoordenacaoTxt(array $relatorios, bool $incluirAssinatura, string $filenameBase): void
    {
        $linhas = [];
        $varios = count($relatorios) > 1;
        foreach ($relatorios as $relatorio) {
            if (!is_array($relatorio)) {
                continue;
            }
            $nome = trim((string) ($relatorio['evento_nome'] ?? ''));
            if ($varios && $nome !== '') {
                if ($linhas !== []) {
                    $linhas[] = '';
                }
                $linhas[] = $nome;
            }
            $cabecalho = ['Aluno'];
            if ($incluirAssinatura) {
                $cabecalho[] = 'Assinatura';
            }
            $refEvento = (int) ($relatorio['regra_id'] ?? 0);
            if ($refEvento > 0) {
                $cabecalho[] = 'Ref';
            }
            $cabecalho[] = 'Bimestre';
            $cabecalho[] = 'Ano';
            $cabecalho[] = 'RA';
            $cabecalho[] = 'Turma';
            $cabecalho[] = 'Matéria';
            $colunas = [];
            foreach ((array) ($relatorio['columns'] ?? []) as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $colunas[] = $column;
                $cabecalho[] = (string) ($column['label'] ?? 'Nota');
            }
            $cabecalho[] = 'Observação da coordenação';
            $linhas[] = implode("\t", $cabecalho);

            $bimestre = (string) ($relatorio['bimestre_rotulo'] ?? '');
            $ano = (int) ($relatorio['ano_letivo'] ?? 0);
            $casas = max(0, min(2, (int) ($relatorio['decimal_places'] ?? 1)));
            foreach ((array) ($relatorio['alunos'] ?? []) as $aluno) {
                if (!is_array($aluno)) {
                    continue;
                }
                $primeiraMateria = true;
                foreach ((array) ($aluno['materias'] ?? []) as $materia) {
                    if (!is_array($materia)) {
                        continue;
                    }
                    $campos = [(string) ($aluno['nome'] ?? '')];
                    if ($incluirAssinatura) {
                        $campos[] = '';
                    }
                    if ($refEvento > 0) {
                        $campos[] = (string) $refEvento;
                    }
                    $campos[] = $bimestre;
                    $campos[] = $ano > 0 ? (string) $ano : '';
                    $campos[] = (string) ($aluno['ra'] ?? '');
                    $campos[] = (string) ($aluno['turma'] ?? '');
                    $campos[] = (string) ($materia['nome'] ?? '');
                    foreach ($colunas as $column) {
                        $valor = $materia['notas'][$column['codigo'] ?? ''] ?? null;
                        if (is_numeric($valor)) {
                            $campos[] = number_format((float) $valor, $casas, ',', '');
                        } else {
                            $campos[] = trim((string) ($valor ?? ''));
                        }
                    }
                    $campos[] = $primeiraMateria ? (string) ($aluno['observacao'] ?? '') : '';
                    $linhas[] = implode("\t", array_map(static function ($campo): string {
                        return str_replace(["\t", "\r", "\n"], ' ', (string) $campo);
                    }, $campos));
                    $primeiraMateria = false;
                }
            }
        }

        $conteudo = "\xEF\xBB\xBF" . implode("\r\n", $linhas) . "\r\n";
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.txt"');
        header('Pragma: no-cache');
        header('Content-Length: ' . strlen($conteudo));
        echo $conteudo;
        exit;
    }

    /** @param array<string,string> $files */
    private function criarZipArmazenado(array $files): string
    {
        $body = '';
        $central = '';
        $offset = 0;
        $count = 0;
        foreach ($files as $name => $data) {
            $name = str_replace('\\', '/', (string) $name);
            $crc = crc32($data);
            $size = strlen($data);
            $nameLength = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0)
                . $name . $data;
            $body .= $local;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset)
                . $name;
            $offset += strlen($local);
            $count++;
        }
        return $body . $central
            . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
    }

    private function getAlunosComChat($filtros)
    {
        $where_clauses = [];
        $params = [];
        
        // Construir filtros
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "a.id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        // Filtros de data
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(c.created_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(c.created_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        if (empty($where_clauses)) {
            $where_clauses[] = "a.ativo = 1";
        } else {
            array_unshift($where_clauses, "a.ativo = 1");
        }
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        
        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, t.nome as turma_nome,
                    COUNT(DISTINCT c.id) as total_conversas,
                    COUNT(m.id) as total_mensagens,
                    MAX(c.ultima_atividade) as ultima_atividade
             FROM alunos a
             INNER JOIN tudinha_conversas c ON a.id = c.aluno_id
             LEFT JOIN turmas t ON a.turma_id = t.id
             LEFT JOIN tudinha_mensagens m ON c.id = m.conversa_id
             {$where_sql}
             GROUP BY a.id, a.nome, a.ra, t.nome
             ORDER BY ultima_atividade DESC
             LIMIT 20",
            $params
        );
    }

    private function getAlunosComExercicios($filtros)
    {
        // Construir filtros para primeira parte (exercicios_historico)
        $where_clauses_h = [];
        $params_h = [];
        
        // Construir filtros para segunda parte (listas_personalizadas_sessoes)
        $where_clauses_sep = ['sep.status = \'finalizado\''];
        $params_sep = [];
        
        // Filtros comuns
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses_h[] = "a.turma_id = :turma_id";
            $where_clauses_sep[] = "a.turma_id = :turma_id_sep";
            $params_h['turma_id'] = $filtros['turma_id'];
            $params_sep['turma_id_sep'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses_h[] = "a.id = :aluno_id";
            $where_clauses_sep[] = "a.id = :aluno_id_sep";
            $params_h['aluno_id'] = $filtros['aluno_id'];
            $params_sep['aluno_id_sep'] = $filtros['aluno_id'];
        }
        
        // Filtros de data - diferentes para cada parte
        if (!empty($filtros['data_inicio'])) {
            $where_clauses_h[] = "DATE(h.created_at) >= :data_inicio_h";
            $where_clauses_sep[] = "DATE(sep.started_at) >= :data_inicio_sep";
            $params_h['data_inicio_h'] = $filtros['data_inicio'];
            $params_sep['data_inicio_sep'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses_h[] = "DATE(h.created_at) <= :data_fim_h";
            $where_clauses_sep[] = "DATE(sep.started_at) <= :data_fim_sep";
            $params_h['data_fim_h'] = $filtros['data_fim'];
            $params_sep['data_fim_sep'] = $filtros['data_fim'];
        }
        
        // Adicionar ativo = 1
        if (empty($where_clauses_h)) {
            $where_clauses_h[] = "a.ativo = 1";
        } else {
            array_unshift($where_clauses_h, "a.ativo = 1");
        }
        
        $where_clauses_sep[] = "a.ativo = 1";
        
        $where_sql_h = "WHERE " . implode(" AND ", $where_clauses_h);
        $where_sql_sep = "WHERE " . implode(" AND ", $where_clauses_sep);
        
        // Combinar parâmetros
        $params = array_merge($params_h, $params_sep);
        
        return $this->db->fetchAll(
            "(SELECT a.id, a.nome, a.ra, t.nome as turma_nome,
                    COUNT(h.id) as total_exercicios,
                    AVG(h.percentual_acerto) as media_acerto,
                    SUM(h.questoes_corretas) as total_acertos,
                    SUM(h.questoes_total) as total_questoes,
                    MAX(h.created_at) as ultimo_exercicio
             FROM alunos a
             INNER JOIN exercicios_historico h ON a.id = h.aluno_id
             LEFT JOIN turmas t ON a.turma_id = t.id
             {$where_sql_h}
             GROUP BY a.id, a.nome, a.ra, t.nome)
             UNION ALL
             (SELECT a.id, a.nome, a.ra, t.nome as turma_nome,
                     COUNT(sep.id) as total_exercicios,
                     0 as media_acerto,
                     (SELECT COALESCE(SUM(CASE WHEN rep.is_correct = 1 THEN 1 ELSE 0 END), 0) 
                      FROM listas_personalizadas_respostas rep 
                      WHERE rep.sessao_id = sep.id) as total_acertos,
                     (SELECT COALESCE(COUNT(*), 0) 
                      FROM listas_personalizadas_respostas rep3 
                      WHERE rep3.sessao_id = sep.id) as total_questoes,
                     MAX(sep.started_at) as ultimo_exercicio
              FROM alunos a
              INNER JOIN listas_personalizadas_sessoes sep ON a.id = sep.aluno_id
              LEFT JOIN turmas t ON a.turma_id = t.id
              {$where_sql_sep}
              GROUP BY a.id, a.nome, a.ra, t.nome, sep.id)
             ORDER BY total_exercicios DESC
             LIMIT 20",
            $params
        );
    }

    private function getAlunosComRedacoes($filtros)
    {
        $where_clauses = [];
        $params = [];
        
        // Construir filtros
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "a.id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        // Filtros de data
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(r.created_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(r.created_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        if (empty($where_clauses)) {
            $where_clauses[] = "a.ativo = 1";
        } else {
            array_unshift($where_clauses, "a.ativo = 1");
        }
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        
        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, t.nome as turma_nome,
                    COUNT(r.id) as total_redacoes,
                    COUNT(CASE WHEN r.nota IS NOT NULL THEN 1 END) as redacoes_corrigidas,
                    AVG(r.nota) as media_notas,
                    MAX(r.created_at) as ultima_redacao
             FROM alunos a
             INNER JOIN redacoes r ON a.id = r.aluno_id
             LEFT JOIN turmas t ON a.turma_id = t.id
             {$where_sql}
             GROUP BY a.id, a.nome, a.ra, t.nome
             ORDER BY total_redacoes DESC
             LIMIT 20",
            $params
        );
    }

    private function getChatStats($filtros)
    {
        $where_clauses = [];
        $params = [];
        
        // Construir filtros
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "a.id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        // Filtros de data
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(c.created_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(c.created_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        // Total de conversas
        $total_conversas = $this->db->fetch(
            "SELECT COUNT(DISTINCT c.id) as total
             FROM tudinha_conversas c
             INNER JOIN alunos a ON c.aluno_id = a.id
             {$where_sql}",
            $params
        )['total'];
        
        // Total de mensagens
        $total_mensagens = $this->db->fetch(
            "SELECT COUNT(m.id) as total
             FROM tudinha_mensagens m
             INNER JOIN tudinha_conversas c ON m.conversa_id = c.id
             INNER JOIN alunos a ON c.aluno_id = a.id
             {$where_sql}",
            $params
        )['total'];
        
        // Total de interações (mensagens não-IA)
        $where_interacoes = ['m.is_ia = 0'];
        if (!empty($where_clauses)) {
            $where_interacoes = array_merge($where_interacoes, $where_clauses);
        }
        $where_sql_interacoes = "WHERE " . implode(" AND ", $where_interacoes);
        
        $total_interacoes = $this->db->fetch(
            "SELECT COUNT(m.id) as total
             FROM tudinha_mensagens m
             INNER JOIN tudinha_conversas c ON m.conversa_id = c.id
             INNER JOIN alunos a ON c.aluno_id = a.id
             {$where_sql_interacoes}",
            $params
        )['total'];
        
        // Interações por turma
        $interacoes_por_turma = [];
        if ($filtros['tipo'] === 'geral') {
            $interacoes_por_turma = $this->db->fetchAll(
                "SELECT t.nome as turma_nome, COUNT(DISTINCT c.id) as total_conversas, 
                        COUNT(m.id) as total_mensagens,
                        COUNT(CASE WHEN m.is_ia = 0 THEN 1 END) as interacoes
                 FROM tudinha_conversas c
                 INNER JOIN alunos a ON c.aluno_id = a.id
                 LEFT JOIN turmas t ON a.turma_id = t.id
                 LEFT JOIN tudinha_mensagens m ON c.id = m.conversa_id
                 GROUP BY t.id, t.nome
                 ORDER BY total_conversas DESC"
            );
        }
        
        return [
            'total_conversas' => $total_conversas,
            'total_mensagens' => $total_mensagens,
            'total_interacoes' => $total_interacoes,
            'interacoes_por_turma' => $interacoes_por_turma
        ];
    }

    private function getExerciseStats($filtros)
    {
        $where_clauses = [];
        $params = [];
        
        // Construir filtros
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "h.aluno_id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        // Filtros de data
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(h.created_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(h.created_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        // Total de exercícios completados - usar exercicios_historico
        $total_execucoes_normal = $this->db->fetch(
            "SELECT COUNT(DISTINCT h.id) as total
             FROM exercicios_historico h
             INNER JOIN alunos a ON h.aluno_id = a.id
             {$where_sql}",
            $params
        )['total'];
        
        // Total de exercícios personalizados completados
        $where_clauses_personalizados = ["sep.status = 'finalizado'"];
        $params_personalizados = [];
        
        // Adicionar filtros de turma/aluno
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses_personalizados[] = "a.turma_id = :turma_id_p";
            $params_personalizados['turma_id_p'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses_personalizados[] = "sep.aluno_id = :aluno_id_p";
            $params_personalizados['aluno_id_p'] = $filtros['aluno_id'];
        }
        
        // Adicionar filtros de data (usar started_at para exercícios personalizados)
        if (!empty($filtros['data_inicio'])) {
            $where_clauses_personalizados[] = "DATE(sep.started_at) >= :data_inicio_p";
            $params_personalizados['data_inicio_p'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses_personalizados[] = "DATE(sep.started_at) <= :data_fim_p";
            $params_personalizados['data_fim_p'] = $filtros['data_fim'];
        }
        
        $where_sql_personalizados = "WHERE " . implode(" AND ", $where_clauses_personalizados);
        
        $total_execucoes_personalizados = $this->db->fetch(
            "SELECT COUNT(DISTINCT sep.id) as total
             FROM listas_personalizadas_sessoes sep
             INNER JOIN alunos a ON sep.aluno_id = a.id
             {$where_sql_personalizados}",
            $params_personalizados
        )['total'];
        
        $total_execucoes = $total_execucoes_normal + $total_execucoes_personalizados;
        
        // Média de acertos (exercícios normais)
        $media_acertos = $this->db->fetch(
            "SELECT AVG(h.percentual_acerto) as media
             FROM exercicios_historico h
             INNER JOIN alunos a ON h.aluno_id = a.id
             {$where_sql}",
            $params
        )['media'];
        
        // Estatísticas por turma
        $stats_por_turma = [];
        if ($filtros['tipo'] === 'geral') {
            $stats_por_turma = $this->db->fetchAll(
                "SELECT t.nome as turma_nome,
                        COUNT(DISTINCT h.id) as total_exercicios,
                        AVG(h.percentual_acerto) as media_acerto,
                        SUM(h.questoes_corretas) as total_acertos,
                        SUM(h.questoes_total) as total_questoes
                 FROM exercicios_historico h
                 INNER JOIN alunos a ON h.aluno_id = a.id
                 LEFT JOIN turmas t ON a.turma_id = t.id
                 GROUP BY t.id, t.nome
                 ORDER BY total_exercicios DESC"
            );
        }
        
        return [
            'total_execucoes' => $total_execucoes,
            'total_execucoes_bd' => $total_execucoes_normal,
            'total_execucoes_ia' => $total_execucoes_personalizados,
            'media_acertos' => $media_acertos,
            'stats_por_turma' => $stats_por_turma
        ];
    }

    private function getEssayStats($filtros)
    {
        $where_clauses = [];
        $params = [];
        
        // Construir filtros
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "r.aluno_id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        // Filtros de data
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(r.created_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(r.created_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        // Total de redações
        $total_redacoes = $this->db->fetch(
            "SELECT COUNT(*) as total
             FROM redacoes r
             INNER JOIN alunos a ON r.aluno_id = a.id
             {$where_sql}",
            $params
        )['total'];
        
        // Redações corrigidas
        $where_clauses_corrigidas = ['r.nota IS NOT NULL'];
        if (!empty($where_clauses)) {
            $where_clauses_corrigidas = array_merge($where_clauses_corrigidas, $where_clauses);
        }
        $where_sql_corrigidas = "WHERE " . implode(" AND ", $where_clauses_corrigidas);
        
        $redacoes_corrigidas = $this->db->fetch(
            "SELECT COUNT(*) as total
             FROM redacoes r
             INNER JOIN alunos a ON r.aluno_id = a.id
             {$where_sql_corrigidas}",
            $params
        )['total'];
        
        // Média de notas
        $where_clauses_media = ['r.nota IS NOT NULL'];
        if (!empty($where_clauses)) {
            $where_clauses_media = array_merge($where_clauses_media, $where_clauses);
        }
        $where_sql_media = "WHERE " . implode(" AND ", $where_clauses_media);
        
        $media_notas = $this->db->fetch(
            "SELECT AVG(r.nota) as media
             FROM redacoes r
             INNER JOIN alunos a ON r.aluno_id = a.id
             {$where_sql_media}",
            $params
        )['media'];
        
        // Estatísticas por turma
        $stats_por_turma = [];
        if ($filtros['tipo'] === 'geral') {
            $stats_por_turma = $this->db->fetchAll(
                "SELECT t.nome as turma_nome,
                        COUNT(r.id) as total_redacoes,
                        COUNT(CASE WHEN r.nota IS NOT NULL THEN 1 END) as corrigidas,
                        AVG(r.nota) as media_nota
                 FROM redacoes r
                 INNER JOIN alunos a ON r.aluno_id = a.id
                 LEFT JOIN turmas t ON a.turma_id = t.id
                 GROUP BY t.id, t.nome
                 ORDER BY total_redacoes DESC"
            );
        }
        
        return [
            'total_redacoes' => $total_redacoes,
            'redacoes_corrigidas' => $redacoes_corrigidas,
            'media_notas' => $media_notas,
            'stats_por_turma' => $stats_por_turma
        ];
    }

    private function getChartData($filtros)
    {
        // Determinar período - se não houver filtros de data, usar últimos 30 dias
        $data_fim = !empty($filtros['data_fim']) ? $filtros['data_fim'] : date('Y-m-d');
        $data_inicio = !empty($filtros['data_inicio']) ? $filtros['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
        
        // Construir filtros base
        $where_clauses = [];
        $params = [];
        
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        $where_sql = !empty($where_clauses) ? "AND " . implode(" AND ", $where_clauses) : "";
        
        // Dados temporais de chat (por dia)
        $chat_temporal = [];
        if ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $chat_temporal = $this->db->fetchAll(
                "SELECT DATE(c.created_at) as data, 
                        COUNT(DISTINCT c.id) as conversas,
                        COUNT(m.id) as mensagens,
                        COUNT(CASE WHEN m.is_ia = 0 THEN 1 END) as interacoes
                 FROM tudinha_conversas c
                 INNER JOIN alunos a ON c.aluno_id = a.id
                 LEFT JOIN tudinha_mensagens m ON c.id = m.conversa_id
                 WHERE DATE(c.created_at) >= :data_inicio AND DATE(c.created_at) <= :data_fim
                 AND c.aluno_id = :aluno_id
                 GROUP BY DATE(c.created_at)
                 ORDER BY data ASC",
                array_merge($params, ['data_inicio' => $data_inicio, 'data_fim' => $data_fim])
            );
        } else {
            $chat_temporal = $this->db->fetchAll(
                "SELECT DATE(c.created_at) as data, 
                        COUNT(DISTINCT c.id) as conversas,
                        COUNT(m.id) as mensagens,
                        COUNT(CASE WHEN m.is_ia = 0 THEN 1 END) as interacoes
                 FROM tudinha_conversas c
                 INNER JOIN alunos a ON c.aluno_id = a.id
                 LEFT JOIN tudinha_mensagens m ON c.id = m.conversa_id
                 WHERE DATE(c.created_at) >= :data_inicio AND DATE(c.created_at) <= :data_fim
                 {$where_sql}
                 GROUP BY DATE(c.created_at)
                 ORDER BY data ASC",
                array_merge($params, ['data_inicio' => $data_inicio, 'data_fim' => $data_fim])
            );
        }
        
        // Dados temporais de exercícios (por dia)
        $exercises_temporal = [];
        $params_exercises = [];
        
        if ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $params_exercises = [
                'aluno_id' => $filtros['aluno_id'],
                'data_inicio_h' => $data_inicio,
                'data_fim_h' => $data_fim,
                'data_inicio_sep' => $data_inicio,
                'data_fim_sep' => $data_fim
            ];
            
            $exercises_temporal = $this->db->fetchAll(
                "(SELECT DATE(h.created_at) as data, COUNT(DISTINCT h.id) as total
                 FROM exercicios_historico h
                 INNER JOIN alunos a ON h.aluno_id = a.id
                 WHERE DATE(h.created_at) >= :data_inicio_h AND DATE(h.created_at) <= :data_fim_h
                 AND h.aluno_id = :aluno_id
                 GROUP BY DATE(h.created_at))
                 UNION ALL
                 (SELECT DATE(sep.started_at) as data, COUNT(DISTINCT sep.id) as total
                 FROM listas_personalizadas_sessoes sep
                 INNER JOIN alunos a ON sep.aluno_id = a.id
                 WHERE DATE(sep.started_at) >= :data_inicio_sep AND DATE(sep.started_at) <= :data_fim_sep
                 AND sep.status = 'finalizado' AND sep.aluno_id = :aluno_id
                 GROUP BY DATE(sep.started_at))
                 ORDER BY data ASC",
                $params_exercises
            );
        } else {
            $params_exercises = array_merge($params, [
                'data_inicio_h' => $data_inicio,
                'data_fim_h' => $data_fim,
                'data_inicio_sep' => $data_inicio,
                'data_fim_sep' => $data_fim
            ]);
            
            $where_sql_h = !empty($where_clauses) ? "AND " . implode(" AND ", $where_clauses) : "";
            $where_sql_sep = !empty($where_clauses) ? "AND " . implode(" AND ", $where_clauses) : "";
            
            $exercises_temporal = $this->db->fetchAll(
                "(SELECT DATE(h.created_at) as data, COUNT(DISTINCT h.id) as total
                 FROM exercicios_historico h
                 INNER JOIN alunos a ON h.aluno_id = a.id
                 WHERE DATE(h.created_at) >= :data_inicio_h AND DATE(h.created_at) <= :data_fim_h
                 {$where_sql_h}
                 GROUP BY DATE(h.created_at))
                 UNION ALL
                 (SELECT DATE(sep.started_at) as data, COUNT(DISTINCT sep.id) as total
                 FROM listas_personalizadas_sessoes sep
                 INNER JOIN alunos a ON sep.aluno_id = a.id
                 WHERE DATE(sep.started_at) >= :data_inicio_sep AND DATE(sep.started_at) <= :data_fim_sep
                 AND sep.status = 'finalizado'
                 {$where_sql_sep}
                 GROUP BY DATE(sep.started_at))
                 ORDER BY data ASC",
                $params_exercises
            );
        }
        
        // Dados temporais de redações (por dia)
        $essays_temporal = [];
        if ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $essays_temporal = $this->db->fetchAll(
                "SELECT DATE(r.created_at) as data, 
                        COUNT(r.id) as total,
                        COUNT(CASE WHEN r.nota IS NOT NULL THEN 1 END) as corrigidas
                 FROM redacoes r
                 INNER JOIN alunos a ON r.aluno_id = a.id
                 WHERE DATE(r.created_at) >= :data_inicio AND DATE(r.created_at) <= :data_fim
                 AND r.aluno_id = :aluno_id
                 GROUP BY DATE(r.created_at)
                 ORDER BY data ASC",
                array_merge($params, ['data_inicio' => $data_inicio, 'data_fim' => $data_fim])
            );
        } else {
            $essays_temporal = $this->db->fetchAll(
                "SELECT DATE(r.created_at) as data, 
                        COUNT(r.id) as total,
                        COUNT(CASE WHEN r.nota IS NOT NULL THEN 1 END) as corrigidas
                 FROM redacoes r
                 INNER JOIN alunos a ON r.aluno_id = a.id
                 WHERE DATE(r.created_at) >= :data_inicio AND DATE(r.created_at) <= :data_fim
                 {$where_sql}
                 GROUP BY DATE(r.created_at)
                 ORDER BY data ASC",
                array_merge($params, ['data_inicio' => $data_inicio, 'data_fim' => $data_fim])
            );
        }
        
        // Agrupar exercícios por data (já que o UNION pode ter duplicatas)
        $exercises_grouped = [];
        foreach ($exercises_temporal as $row) {
            $data = $row['data'];
            if (!isset($exercises_grouped[$data])) {
                $exercises_grouped[$data] = 0;
            }
            $exercises_grouped[$data] += $row['total'];
        }
        $exercises_temporal = [];
        foreach ($exercises_grouped as $data => $total) {
            $exercises_temporal[] = ['data' => $data, 'total' => $total];
        }
        usort($exercises_temporal, function($a, $b) {
            return strcmp($a['data'], $b['data']);
        });
        
        return [
            'chat_temporal' => $chat_temporal,
            'exercises_temporal' => $exercises_temporal,
            'essays_temporal' => $essays_temporal,
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim
        ];
    }

    private function getExerciciosBD($filtros)
    {
        $where_clauses = [];
        $params = [];
        
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "h.aluno_id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(h.created_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(h.created_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        return $this->db->fetchAll(
            "SELECT h.*, a.nome as aluno_nome, a.ra, t.nome as turma_nome,
                    le.titulo, le.materia, le.serie,
                    s.finished_at as data_fim
             FROM exercicios_historico h
             INNER JOIN alunos a ON h.aluno_id = a.id
             LEFT JOIN turmas t ON a.turma_id = t.id
             INNER JOIN listas_exercicios le ON h.lista_id = le.id
             LEFT JOIN exercicios_sessoes s ON h.sessao_id = s.id
             {$where_sql}
             ORDER BY h.created_at DESC
             LIMIT 100",
            $params
        );
    }

    private function getExerciciosIA($filtros)
    {
        $where_clauses = ['sep.status = \'finalizado\''];
        $params = [];
        
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "sep.aluno_id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(sep.started_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(sep.started_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        
        return $this->db->fetchAll(
            "SELECT sep.*, a.nome as aluno_nome, a.ra, t.nome as turma_nome,
                    lep.titulo as lista_titulo, lep.materia, lep.quantidade_exercicios,
                    (SELECT COUNT(*) FROM listas_personalizadas_respostas rep WHERE rep.sessao_id = sep.id) as total_respostas,
                    (SELECT SUM(CASE WHEN rep.is_correct = 1 THEN 1 ELSE 0 END) FROM listas_personalizadas_respostas rep WHERE rep.sessao_id = sep.id) as acertos
             FROM listas_personalizadas_sessoes sep
             INNER JOIN alunos a ON sep.aluno_id = a.id
             LEFT JOIN turmas t ON a.turma_id = t.id
             LEFT JOIN listas_personalizadas_exercicios lep ON sep.lista_id = lep.id
             {$where_sql}
             ORDER BY sep.started_at DESC
             LIMIT 100",
            $params
        );
    }

    private function getRedacoesComCorrecao($filtros)
    {
        $where_clauses = [];
        $params = [];
        
        if ($filtros['tipo'] === 'turma' && !empty($filtros['turma_id'])) {
            $where_clauses[] = "a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        } elseif ($filtros['tipo'] === 'usuario' && !empty($filtros['aluno_id'])) {
            $where_clauses[] = "r.aluno_id = :aluno_id";
            $params['aluno_id'] = $filtros['aluno_id'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $where_clauses[] = "DATE(r.created_at) >= :data_inicio";
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where_clauses[] = "DATE(r.created_at) <= :data_fim";
            $params['data_fim'] = $filtros['data_fim'];
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        return $this->db->fetchAll(
            "SELECT r.*, a.nome as aluno_nome, a.ra, t.nome as turma_nome,
                    CASE 
                        WHEN r.corrigida_em IS NOT NULL OR r.correcao IS NOT NULL OR r.feedback_ia IS NOT NULL OR r.nota IS NOT NULL OR r.nota_final IS NOT NULL THEN 'Corrigida'
                        ELSE 'Pendente'
                    END as status_descricao
             FROM redacoes r
             INNER JOIN alunos a ON r.aluno_id = a.id
             LEFT JOIN turmas t ON a.turma_id = t.id
             {$where_sql}
             ORDER BY r.created_at DESC
             LIMIT 100",
            $params
        );
    }
}
}
