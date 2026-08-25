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

    public function boletimCoordenacao()
    {
        if (!$this->enforceAdminPermissionKey('relatorios_gerais', 'visualizar', false)) {
            return;
        }

        $user = $this->auth->getUser();
        [$regraId, $periodoRef] = $this->parseEventoBoletimCoordenacao((string) ($_GET['evento'] ?? ''));
        $turmaId = max(0, (int) ($_GET['turma_id'] ?? 0));
        $notaAbaixoDe = $this->parseNotaAbaixoDeBoletim($_GET['nota_abaixo_de'] ?? null);
        $materiasExibicao = $this->parseMateriasExibicaoBoletim($_GET['materias_exibicao'] ?? 'todas');
        $incluirAssinatura = !empty($_GET['assinatura']);
        $executar = !empty($_GET['executar']) && $regraId > 0 && $periodoRef !== '';
        $relatorio = $executar
            ? $this->montarRelatorioBoletimCoordenacao($regraId, $periodoRef, $turmaId, $notaAbaixoDe, $materiasExibicao)
            : null;

        $this->viewWithLayout('admin', 'admin/reports/boletim_coordenacao', [
            'title' => 'Notas da Coordenação - EducaTudo',
            'user' => $user,
            'current_page' => 'reports_boletim_coordenacao',
            'eventos' => $this->listarEventosBoletimCoordenacao(),
            'turmas' => $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC") ?: [],
            'evento_selecionado' => (string) ($_GET['evento'] ?? ''),
            'turma_id' => $turmaId,
            'nota_abaixo_de' => $notaAbaixoDe,
            'materias_exibicao' => $materiasExibicao,
            'incluir_assinatura' => $incluirAssinatura,
            'executar' => $executar,
            'relatorio' => $relatorio,
            'pode_editar_observacao' => $this->podeEditarObservacaoBoletimCoordenacao($user),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function exportarBoletimCoordenacao()
    {
        if (!$this->enforceAdminPermissionKey('relatorios_gerais', 'visualizar', false)) {
            return;
        }
        [$regraId, $periodoRef] = $this->parseEventoBoletimCoordenacao((string) ($_GET['evento'] ?? ''));
        $turmaId = max(0, (int) ($_GET['turma_id'] ?? 0));
        $notaAbaixoDe = $this->parseNotaAbaixoDeBoletim($_GET['nota_abaixo_de'] ?? null);
        $materiasExibicao = $this->parseMateriasExibicaoBoletim($_GET['materias_exibicao'] ?? 'todas');
        $incluirAssinatura = !empty($_GET['assinatura']);
        $formato = strtolower(trim((string) ($_GET['formato'] ?? 'pdf')));
        if ($regraId <= 0 || $periodoRef === '' || !in_array($formato, ['pdf', 'excel'], true)) {
            $this->redirect('/admin/reports/boletim-coordenacao');
            return;
        }

        $relatorio = $this->montarRelatorioBoletimCoordenacao($regraId, $periodoRef, $turmaId, $notaAbaixoDe, $materiasExibicao);
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($relatorio['evento_nome'] ?? 'boletim'));
        $slug = trim((string) $slug, '_-') ?: 'boletim';
        $filenameBase = 'notas_coordenacao_' . $slug . '_' . date('Ymd_His');

        if ($formato === 'excel') {
            $this->exportarBoletimCoordenacaoExcel($relatorio, $incluirAssinatura, $filenameBase);
            return;
        }

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

    private function listarEventosBoletimCoordenacao(): array
    {
        $eventos = $this->db->fetchAll(
            "SELECT g.regra_id, g.periodo_ref, r.nome, r.ano_letivo, r.series_ids,
                    COUNT(DISTINCT g.aluno_id) AS total_alunos,
                    GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome ASC SEPARATOR ', ') AS turmas_nomes,
                    MAX(g.updated_at) AS updated_at
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             INNER JOIN alunos a ON a.id = g.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE g.preview = 0 AND r.ativo = 1 AND r.exibir_em = 'boletim' AND a.ativo = 1
             GROUP BY g.regra_id, g.periodo_ref, r.nome, r.ano_letivo, r.series_ids
             ORDER BY COALESCE(r.ano_letivo, 0) DESC, updated_at DESC, r.nome ASC"
        ) ?: [];

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
        foreach ($eventos as &$evento) {
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
            $evento['nome_exibicao'] = trim((string) ($evento['nome'] ?? 'Boletim') . ($seriesLabel !== '' ? ' ' . $seriesLabel : ''));
            $evento['_serie_ordem'] = $ordemMax;
        }
        unset($evento);
        usort($eventos, static function (array $a, array $b): int {
            $cmp = ((int) ($b['_serie_ordem'] ?? 0)) <=> ((int) ($a['_serie_ordem'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });
        return $eventos;
    }

    private function parseEventoBoletimCoordenacao(string $evento): array
    {
        $parts = explode(':', trim($evento), 2);
        $regraId = isset($parts[0]) ? (int) $parts[0] : 0;
        $periodoRef = isset($parts[1]) ? base64_decode($parts[1], true) : '';
        $periodoRef = is_string($periodoRef) ? trim($periodoRef) : '';
        return [$regraId, $periodoRef];
    }

    private function montarRelatorioBoletimCoordenacao(
        int $regraId,
        string $periodoRef,
        int $turmaId,
        ?float $notaAbaixoDe = null,
        string $materiasExibicao = 'todas'
    ): array
    {
        $whereTurma = $turmaId > 0 ? ' AND a.turma_id = :turma_id' : '';
        $params = ['regra_id' => $regraId, 'periodo_ref' => $periodoRef];
        if ($turmaId > 0) {
            $params['turma_id'] = $turmaId;
        }
        $rows = $this->db->fetchAll(
            "SELECT g.aluno_id, g.materia_nome, g.ordem_linha, g.colunas_json, g.notas_json,
                    a.nome AS aluno_nome, a.ra, t.nome AS turma_nome,
                    r.nome AS evento_nome, r.series_ids, r.decimal_places,
                    o.conteudo AS observacao_conteudo, o.updated_at AS observacao_updated_at
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             INNER JOIN alunos a ON a.id = g.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             LEFT JOIN boletim_observacoes o ON o.aluno_id = a.id
             WHERE g.preview = 0 AND g.regra_id = :regra_id AND g.periodo_ref = :periodo_ref
               AND a.ativo = 1{$whereTurma}
             ORDER BY t.nome ASC, a.nome ASC, g.ordem_linha ASC, g.id ASC",
            $params
        ) ?: [];

        $columnsRaw = [];
        if ($rows !== []) {
            $columnsRaw = json_decode((string) ($rows[0]['colunas_json'] ?? ''), true);
            $columnsRaw = is_array($columnsRaw) ? $columnsRaw : [];
        }
        $columns = $this->selecionarColunasNotasBoletim($columnsRaw);
        $decimalPlaces = max(0, min(2, (int) ($rows[0]['decimal_places'] ?? 1)));
        $alunos = [];
        foreach ($rows as $row) {
            $alunoId = (int) ($row['aluno_id'] ?? 0);
            if (!isset($alunos[$alunoId])) {
                $alunos[$alunoId] = [
                    'id' => $alunoId,
                    'nome' => (string) ($row['aluno_nome'] ?? ''),
                    'ra' => (string) ($row['ra'] ?? ''),
                    'turma' => (string) ($row['turma_nome'] ?? ''),
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
        $codigoMediaFinal = '';
        foreach ($columns as $column) {
            if (($column['group'] ?? '') === 'final') {
                $codigoMediaFinal = (string) ($column['codigo'] ?? '');
                break;
            }
        }
        if ($codigoMediaFinal === '' && $columns !== []) {
            foreach (array_reverse($columns) as $column) {
                if (stripos((string) ($column['label'] ?? ''), 'média') !== false
                    || stripos((string) ($column['label'] ?? ''), 'media') !== false) {
                    $codigoMediaFinal = (string) ($column['codigo'] ?? '');
                    break;
                }
            }
        }
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
        return [
            'evento_nome' => $this->nomeEventoBoletimCoordenacao(
                (string) ($rows[0]['evento_nome'] ?? 'Boletim'),
                $rows[0]['series_ids'] ?? null
            ),
            'periodo_ref' => $periodoRef,
            'decimal_places' => $decimalPlaces,
            'columns' => $columns,
            'alunos' => $alunos,
            'total_alunos' => count($alunos),
            'total_linhas' => $totalLinhasFiltradas,
            'nota_abaixo_de' => $notaAbaixoDe,
            'materias_exibicao' => $materiasExibicao,
            'codigo_media_final' => $codigoMediaFinal,
        ];
    }

    private function selecionarColunasNotasBoletim(array $columnsRaw): array
    {
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
            if (in_array($type, ['faltas', 'resultado', 'rec'], true)
                || strpos($haystack, 'falta') !== false || strpos($haystack, 'result') !== false) {
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

    private function resolveSchoolLogoForCoordinationReportPdf(): string
    {
        try {
            $url = (string) LayoutHelper::getNavbarLogoUrl();
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
        } catch (Throwable $e) {
            error_log('ReportAdminController resolveSchoolLogoForCoordinationReportPdf: ' . $e->getMessage());
            return '';
        }
    }

    private function exportarBoletimCoordenacaoExcel(array $relatorio, bool $incluirAssinatura, string $filenameBase): void
    {
        $headers = ['Aluno'];
        if ($incluirAssinatura) {
            $headers[] = 'Assinatura';
        }
        $headers[] = 'RA';
        $headers[] = 'Turma';
        $headers[] = 'Matéria';
        foreach ((array) ($relatorio['columns'] ?? []) as $column) {
            $headers[] = (string) ($column['label'] ?? 'Nota');
        }
        $headers[] = 'Observação da coordenação';

        $rows = [];
        foreach ((array) ($relatorio['alunos'] ?? []) as $aluno) {
            $primeiraMateria = true;
            foreach ((array) ($aluno['materias'] ?? []) as $materia) {
                $row = [(string) ($aluno['nome'] ?? '')];
                if ($incluirAssinatura) {
                    $row[] = '';
                }
                // RA deve permanecer texto para não perder zeros à esquerda.
                $row[] = (string) ($aluno['ra'] ?? '');
                $row[] = (string) ($aluno['turma'] ?? '');
                $row[] = (string) ($materia['nome'] ?? '');
                foreach ((array) ($relatorio['columns'] ?? []) as $column) {
                    $value = $materia['notas'][$column['codigo']] ?? null;
                    $row[] = is_numeric($value) ? (float) $value : (string) ($value ?? '');
                }
                $row[] = $primeiraMateria ? (string) ($aluno['observacao'] ?? '') : '';
                $rows[] = $row;
                $primeiraMateria = false;
            }
        }

        $xlsx = $this->criarXlsxBoletimCoordenacao(
            $headers,
            $rows,
            max(0, min(2, (int) ($relatorio['decimal_places'] ?? 1)))
        );
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
        header('Pragma: no-cache');
        header('Content-Length: ' . strlen($xlsx));
        echo $xlsx;
        exit;
    }

    /**
     * Gera um XLSX real sem depender da extensão ZipArchive, ausente na imagem PHP
     * atual. O pacote usa arquivos OpenXML armazenados (sem compressão) em ZIP.
     */
    private function criarXlsxBoletimCoordenacao(array $headers, array $rows, int $decimalPlaces): string
    {
        $xml = static function ($value): string {
            return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };
        $columnName = static function (int $index): string {
            $name = '';
            do {
                $name = chr(65 + ($index % 26)) . $name;
                $index = intdiv($index, 26) - 1;
            } while ($index >= 0);
            return $name;
        };
        $cellString = static function (string $ref, string $value, int $style = 0) use ($xml): string {
            $preserve = trim($value) !== $value || strpos($value, "\n") !== false;
            return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t'
                . ($preserve ? ' xml:space="preserve"' : '') . '>' . $xml($value) . '</t></is></c>';
        };

        $sheetRows = [];
        $raColumnIndex = array_search('RA', $headers, true);
        $headerCells = [];
        foreach (array_values($headers) as $col => $header) {
            $headerCells[] = $cellString($columnName($col) . '1', (string) $header, 1);
        }
        $sheetRows[] = '<row r="1" ht="24" customHeight="1">' . implode('', $headerCells) . '</row>';
        foreach (array_values($rows) as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $cells = [];
            foreach (array_values($row) as $col => $value) {
                $ref = $columnName($col) . $excelRow;
                if (is_int($value) || is_float($value)) {
                    $cells[] = '<c r="' . $ref . '" s="3"><v>' . (float) $value . '</v></c>';
                } else {
                    $cells[] = $cellString($ref, (string) $value, $col === $raColumnIndex ? 4 : 2);
                }
            }
            $sheetRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
        }

        $lastColumn = $columnName(max(0, count($headers) - 1));
        $lastRow = max(1, count($rows) + 1);
        $signatureOffset = in_array('Assinatura', $headers, true) ? 1 : 0;
        $columnsXml = '<cols>'
            . '<col min="1" max="1" width="30" customWidth="1"/>'
            . ($signatureOffset ? '<col min="2" max="2" width="28" customWidth="1"/>' : '')
            . '<col min="' . (2 + $signatureOffset) . '" max="' . (2 + $signatureOffset) . '" width="14" customWidth="1"/>'
            . '<col min="' . (3 + $signatureOffset) . '" max="' . (3 + $signatureOffset) . '" width="18" customWidth="1"/>'
            . '<col min="' . (4 + $signatureOffset) . '" max="' . (4 + $signatureOffset) . '" width="25" customWidth="1"/>'
            . '<col min="' . (5 + $signatureOffset) . '" max="' . max(5 + $signatureOffset, count($headers) - 1) . '" width="15" customWidth="1"/>'
            . '<col min="' . count($headers) . '" max="' . count($headers) . '" width="45" customWidth="1"/>'
            . '</cols>';

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>' . $columnsXml
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';

        $numberFormat = $decimalPlaces <= 0 ? '0' : '0.' . str_repeat('0', $decimalPlaces);
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="' . $numberFormat . '"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF5B21B6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Notas" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
            'xl/styles.xml' => $styles,
        ];
        return $this->criarZipArmazenado($files);
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
