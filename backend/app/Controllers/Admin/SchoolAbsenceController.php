<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Models/Education/SchoolAbsence.php';

class SchoolAbsenceController extends BaseController
{
    private $auth;
    private $absence;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->absence = new SchoolAbsence();
        $this->absence->ensureSchema();

        $user = $this->auth->getUser();
        if (!$this->canAccess($user)) {
            $this->redirect(URL . '/admin');
            exit;
        }
    }

    private function canAccess(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['tipo'] ?? '') === 'admin') {
            return true;
        }
        if (!class_exists('AdminSecretariaAccess')) {
            require_once __DIR__ . '/../../Core/AdminSecretariaAccess.php';
        }
        if (($user['tipo'] ?? '') === 'admin_escola'
            && in_array((string) ($user['perfil_admin'] ?? ''), AdminSecretariaAccess::perfisAdminEscolaGestaoPedagogica(), true)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<int|string> $turmasDoEvento
     * @return int[]
     */
    private function parseTurmaFiltroIdsFromRequest(array $turmasDoEvento): array
    {
        $allowed = [];
        foreach ($turmasDoEvento as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $allowed[$id] = true;
            }
        }
        if ($allowed === []) {
            return [];
        }
        $raw = (array) ($_GET['turma_ids'] ?? []);
        $out = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($allowed[$id])) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<int|string> $materiasPermitidas ids de matérias válidos para o filtro
     * @return int[]
     */
    private function parseMateriaFiltroIdsFromRequest(array $materiasPermitidas): array
    {
        $allowed = [];
        foreach ($materiasPermitidas as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $allowed[$id] = true;
            }
        }
        if ($allowed === []) {
            return [];
        }
        $raw = (array) ($_GET['materia_ids'] ?? []);
        $out = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($allowed[$id])) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Dados da tela de lançamento (turmas, matriz ou linhas, mapas) para um evento já carregado.
     *
     * @return array<string, mixed>
     */
    private function dadosParaViewLancamentoFaltas(array $eventoAtual): array
    {
        $turmaFiltroIds = $this->parseTurmaFiltroIdsFromRequest((array) ($eventoAtual['turmas_ids'] ?? []));
        $materiaFiltroIds = [];
        $matrizMateriasTodas = [];
        $materiasFiltroOpcoes = [];
        $turmas = $this->absence->listTurmasAtivas();
        $turmasById = [];
        foreach ($turmas as $t) {
            $tid = (int) ($t['id'] ?? 0);
            if ($tid > 0) {
                $turmasById[$tid] = $t;
            }
        }

        $alunos = [];
        $linhasFaltas = [];
        $lancamentosMap = [];
        $faltasMigracaoLegado = false;
        $faltasMatriz = false;
        $matrizColunasMaterias = [];
        $matrizLinhasAlunos = [];

        $anoEvento = (int) ($eventoAtual['ano_letivo'] ?? 0);
        $alunos = $this->absence->listAlunosByTurmas((array) ($eventoAtual['turmas_ids'] ?? []), $anoEvento);
        if ($turmaFiltroIds !== []) {
            $setTurma = array_fill_keys($turmaFiltroIds, true);
            $alunos = array_values(array_filter($alunos, static function (array $a) use ($setTurma): bool {
                return isset($setTurma[(int) ($a['turma_id'] ?? 0)]);
            }));
        }
        $lancamentosMap = $this->absence->getLancamentosMapByEvento((int) $eventoAtual['id']);
        $materiasFixasEvento = array_values(array_filter(
            (array) ($eventoAtual['materias_ids'] ?? []),
            static fn ($id): bool => (int) $id > 0
        ));

        if ($materiasFixasEvento !== []) {
            $faltasMatriz = true;
            $matrizColunasMaterias = $this->absence->listMateriasByIds($materiasFixasEvento);
            $matrizMateriasTodas = $matrizColunasMaterias;
            $materiaFiltroIds = $this->parseMateriaFiltroIdsFromRequest($materiasFixasEvento);
            if ($materiaFiltroIds !== []) {
                $setM = array_fill_keys($materiaFiltroIds, true);
                $matrizColunasMaterias = array_values(array_filter(
                    $matrizColunasMaterias,
                    static function (array $c) use ($setM): bool {
                        return isset($setM[(int) ($c['id'] ?? 0)]);
                    }
                ));
            }
            foreach ($alunos as $a) {
                $aid = (int) ($a['id'] ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                $matrizLinhasAlunos[] = [
                    'aluno_id' => $aid,
                    'nome' => (string) ($a['nome'] ?? ''),
                    'turma_nome' => (string) ($a['turma_nome'] ?? ''),
                    'numero_chamada' => isset($a['numero_chamada']) ? (int) $a['numero_chamada'] : null,
                ];
                if (!$faltasMigracaoLegado) {
                    $legKey = SchoolAbsence::lancamentoMapKey($aid, 0);
                    $row = $lancamentosMap[$legKey] ?? null;
                    if ($row !== null) {
                        $lf = (float) ($row['faltas'] ?? 0);
                        $lo = trim((string) ($row['observacao'] ?? ''));
                        if ($lf > 0 || $lo !== '') {
                            $faltasMigracaoLegado = true;
                        }
                    }
                }
            }
        } else {
            foreach ($alunos as $a) {
                $aid = (int) ($a['id'] ?? 0);
                $tid = (int) ($a['turma_id'] ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                $mats = $this->absence->listMateriasPorTurma($tid);
                if ($mats !== [] && !$faltasMigracaoLegado) {
                    $legKey = SchoolAbsence::lancamentoMapKey($aid, 0);
                    $row = $lancamentosMap[$legKey] ?? null;
                    if ($row !== null) {
                        $lf = (float) ($row['faltas'] ?? 0);
                        $lo = trim((string) ($row['observacao'] ?? ''));
                        if ($lf > 0 || $lo !== '') {
                            $faltasMigracaoLegado = true;
                        }
                    }
                }
                if ($mats === []) {
                    $linhasFaltas[] = [
                        'aluno_id' => $aid,
                        'nome' => (string) ($a['nome'] ?? ''),
                        'turma_nome' => (string) ($a['turma_nome'] ?? ''),
                        'numero_chamada' => isset($a['numero_chamada']) ? (int) $a['numero_chamada'] : null,
                        'materia_id' => 0,
                        'materia_nome' => '— (sem matérias na grade horária)',
                    ];
                } else {
                    foreach ($mats as $m) {
                        $linhasFaltas[] = [
                            'aluno_id' => $aid,
                            'nome' => (string) ($a['nome'] ?? ''),
                            'turma_nome' => (string) ($a['turma_nome'] ?? ''),
                            'numero_chamada' => isset($a['numero_chamada']) ? (int) $a['numero_chamada'] : null,
                            'materia_id' => (int) ($m['id'] ?? 0),
                            'materia_nome' => (string) ($m['nome'] ?? ''),
                        ];
                    }
                }
            }
        }
        if (!$faltasMatriz && $linhasFaltas !== []) {
            $seenMid = [];
            foreach ($linhasFaltas as $ln) {
                $midL = (int) ($ln['materia_id'] ?? 0);
                if ($midL <= 0 || isset($seenMid[$midL])) {
                    continue;
                }
                $seenMid[$midL] = true;
                $materiasFiltroOpcoes[] = [
                    'id' => $midL,
                    'nome' => (string) ($ln['materia_nome'] ?? ('#' . $midL)),
                ];
            }
            $allowedMidList = array_column($materiasFiltroOpcoes, 'id');
            $materiaFiltroIds = $this->parseMateriaFiltroIdsFromRequest($allowedMidList);
            if ($materiaFiltroIds !== []) {
                $setM = array_fill_keys($materiaFiltroIds, true);
                $linhasFaltas = array_values(array_filter(
                    $linhasFaltas,
                    static function (array $ln) use ($setM): bool {
                        $midL = (int) ($ln['materia_id'] ?? 0);

                        return $midL > 0 && isset($setM[$midL]);
                    }
                ));
            }
        }

        return [
            'evento_atual' => $eventoAtual,
            'turma_filtro_ids' => $turmaFiltroIds,
            'materia_filtro_ids' => $materiaFiltroIds,
            'matriz_materias_todas' => $matrizMateriasTodas,
            'materias_filtro_opcoes' => $materiasFiltroOpcoes,
            'turmas' => $turmas,
            'turmas_by_id' => $turmasById,
            'alunos' => $alunos,
            'linhas_faltas' => $linhasFaltas,
            'faltas_matriz' => $faltasMatriz,
            'matriz_colunas_materias' => $matrizColunasMaterias,
            'matriz_linhas_alunos' => $matrizLinhasAlunos,
            'lancamentos_map' => $lancamentosMap,
            'faltas_migracao_legado' => $faltasMigracaoLegado,
        ];
    }

    public function index(): void
    {
        if (!empty($_GET['evento_id']) && (int) $_GET['evento_id'] > 0) {
            $q = $_GET;
            $this->redirect(URL . '/admin/faltas/lancar?' . http_build_query($q));
            return;
        }

        $user = $this->auth->getUser();
        $eventos = $this->absence->listEventos(300);
        $materiasCatalogo = $this->absence->listMateriasCadastradas(800);
        $turmas = $this->absence->listTurmasAtivas();
        $turmasById = [];
        foreach ($turmas as $t) {
            $tid = (int) ($t['id'] ?? 0);
            if ($tid > 0) {
                $turmasById[$tid] = $t;
            }
        }

        $this->viewWithLayout('admin', 'admin/faltas/index', [
            'title' => 'Só Faltas - EducaTudo',
            'page_title' => 'Só Faltas',
            'current_page' => 'faltas',
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'eventos' => $eventos,
            'turmas' => $turmas,
            'turmas_by_id' => $turmasById,
            'materias_catalogo' => $materiasCatalogo,
            'flash_message' => $_SESSION['faltas_flash'] ?? '',
            'flash_type' => $_SESSION['faltas_flash_type'] ?? 'success',
        ]);

        unset($_SESSION['faltas_flash'], $_SESSION['faltas_flash_type']);
    }

    public function lancar(): void
    {
        $eventoId = (int) ($_GET['evento_id'] ?? 0);
        if ($eventoId <= 0) {
            $_SESSION['faltas_flash'] = 'Abra um evento pela lista para lançar faltas.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect(URL . '/admin/faltas');
            return;
        }

        $eventoAtual = $this->absence->getEventoById($eventoId);
        if (!$eventoAtual) {
            $_SESSION['faltas_flash'] = 'Evento não encontrado.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect(URL . '/admin/faltas');
            return;
        }

        $user = $this->auth->getUser();
        $dados = $this->dadosParaViewLancamentoFaltas($eventoAtual);

        $this->viewWithLayout('admin', 'admin/faltas/lancar', array_merge($dados, [
            'title' => 'Lançar faltas - EducaTudo',
            'page_title' => 'Lançar faltas',
            'current_page' => 'faltas',
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $_SESSION['faltas_flash'] ?? '',
            'flash_type' => $_SESSION['faltas_flash_type'] ?? 'success',
        ]));

        unset($_SESSION['faltas_flash'], $_SESSION['faltas_flash_type']);
    }

    public function exportarExcel(): void
    {
        $eventos = $this->absence->listEventos(0);
        $turmas = $this->absence->listTurmasAtivas();
        $turmasById = [];
        foreach ($turmas as $t) {
            $tid = (int) ($t['id'] ?? 0);
            if ($tid > 0) {
                $turmasById[$tid] = $t;
            }
        }

        $materiasById = [];
        foreach ($this->absence->listMateriasCadastradas(2000) as $materia) {
            $mid = (int) ($materia['id'] ?? 0);
            if ($mid > 0) {
                $materiasById[$mid] = $materia;
            }
        }

        $headers = ['Evento', 'Bimestre', 'Ano letivo', 'Turmas', 'Matérias', 'Criado em', 'Atualizado em'];
        $rows = [];
        foreach ($eventos as $ev) {
            $turmasLbl = [];
            foreach ((array) ($ev['turmas_ids'] ?? []) as $tidEv) {
                $tidEv = (int) $tidEv;
                if ($tidEv > 0 && isset($turmasById[$tidEv])) {
                    $tx = $turmasById[$tidEv];
                    $turmasLbl[] = trim((string) (($tx['serie_nome'] ?? '') . ' - ' . ($tx['nome'] ?? ('#' . $tidEv))));
                }
            }

            $materiasLbl = [];
            $materiasIds = (array) ($ev['materias_ids'] ?? []);
            if ($materiasIds === []) {
                $materiasLbl[] = 'Grade horária';
            } else {
                foreach ($materiasIds as $midEv) {
                    $midEv = (int) $midEv;
                    if ($midEv > 0 && isset($materiasById[$midEv])) {
                        $materiasLbl[] = (string) ($materiasById[$midEv]['nome'] ?? ('#' . $midEv));
                    } elseif ($midEv > 0) {
                        $materiasLbl[] = '#' . $midEv;
                    }
                }
            }

            $rows[] = [
                (string) ($ev['nome'] ?? ''),
                (string) ($ev['bimestre'] ?? ''),
                (string) ((int) ($ev['ano_letivo'] ?? 0)),
                implode(', ', $turmasLbl),
                implode(', ', $materiasLbl),
                (string) ($ev['created_at'] ?? ''),
                (string) ($ev['updated_at'] ?? ''),
            ];
        }

        $xlsx = $this->criarXlsxSimples('Faltas', $headers, $rows);
        $filename = 'faltas_eventos_' . date('Y-m-d_His') . '.xlsx';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Content-Length: ' . strlen($xlsx));
        echo $xlsx;
        exit;
    }

    /**
     * @param string[] $headers
     * @param array<int, array<int, string>> $rows
     */
    private function criarXlsxSimples(string $sheetName, array $headers, array $rows): string
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
        $headerCells = [];
        foreach (array_values($headers) as $col => $header) {
            $headerCells[] = $cellString($columnName($col) . '1', (string) $header, 1);
        }
        $sheetRows[] = '<row r="1" ht="24" customHeight="1">' . implode('', $headerCells) . '</row>';
        foreach (array_values($rows) as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $cells = [];
            foreach (array_values($row) as $col => $value) {
                $cells[] = $cellString($columnName($col) . $excelRow, (string) $value, 2);
            }
            $sheetRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
        }

        $lastColumn = $columnName(max(0, count($headers) - 1));
        $lastRow = max(1, count($rows) + 1);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF5B21B6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border/><border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
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

    public function criarEvento(): void
    {
        if (!$this->validateCsrf($_POST['_token'] ?? '')) {
            $_SESSION['faltas_flash'] = 'Token inválido. Recarregue a página e tente novamente.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
            return;
        }

        $nome = trim((string) ($_POST['nome'] ?? ''));
        $bimestre = trim((string) ($_POST['bimestre'] ?? ''));
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? 0);
        $turmasIds = array_map('intval', (array) ($_POST['turmas_ids'] ?? []));
        $materiasIds = array_map('intval', (array) ($_POST['materias_ids'] ?? []));

        if ($nome === '' || $bimestre === '' || $anoLetivo <= 0 || $turmasIds === []) {
            $_SESSION['faltas_flash'] = 'Preencha nome, bimestre, ano letivo e ao menos uma turma.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
            return;
        }

        try {
            $user = $this->auth->getUser();
            $createdBy = isset($user['id']) ? (int) $user['id'] : null;
            $eventoId = $this->absence->createEvento($nome, $bimestre, $anoLetivo, $turmasIds, $createdBy, $materiasIds);
            $_SESSION['faltas_flash'] = 'Evento de faltas criado com sucesso.';
            $_SESSION['faltas_flash_type'] = 'success';
            $this->redirect('/admin/faltas/lancar?evento_id=' . $eventoId);
        } catch (Throwable $e) {
            $_SESSION['faltas_flash'] = 'Erro ao criar evento: ' . $e->getMessage();
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
        }
    }

    public function atualizarEvento(): void
    {
        if (!$this->validateCsrf($_POST['_token'] ?? '')) {
            $_SESSION['faltas_flash'] = 'Token inválido. Recarregue a página e tente novamente.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
            return;
        }

        $eventoId = (int) ($_POST['evento_id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $bimestre = trim((string) ($_POST['bimestre'] ?? ''));
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? 0);
        $turmasIds = array_map('intval', (array) ($_POST['turmas_ids'] ?? []));
        $materiasIds = array_map('intval', (array) ($_POST['materias_ids'] ?? []));

        if ($eventoId <= 0) {
            $_SESSION['faltas_flash'] = 'Evento inválido.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
            return;
        }
        if ($nome === '' || $bimestre === '' || $anoLetivo <= 0 || $turmasIds === []) {
            $_SESSION['faltas_flash'] = 'Preencha nome, bimestre, ano letivo e ao menos uma turma.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas/lancar?evento_id=' . $eventoId);
            return;
        }

        try {
            $this->absence->updateEvento($eventoId, $nome, $bimestre, $anoLetivo, $turmasIds, $materiasIds);
            $_SESSION['faltas_flash'] = 'Evento atualizado com sucesso.';
            $_SESSION['faltas_flash_type'] = 'success';
            $this->redirect('/admin/faltas/lancar?evento_id=' . $eventoId);
        } catch (Throwable $e) {
            $_SESSION['faltas_flash'] = 'Erro ao atualizar evento: ' . $e->getMessage();
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas/lancar?evento_id=' . $eventoId);
        }
    }

    public function salvarLancamentos(): void
    {
        if (!$this->validateCsrf($_POST['_token'] ?? '')) {
            $_SESSION['faltas_flash'] = 'Token inválido. Recarregue a página e tente novamente.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
            return;
        }

        $eventoId = (int) ($_POST['evento_id'] ?? 0);
        if ($eventoId <= 0) {
            $_SESSION['faltas_flash'] = 'Evento inválido.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
            return;
        }

        $faltas = (array) ($_POST['faltas'] ?? []);
        $observacoes = (array) ($_POST['observacao'] ?? []);
        foreach ($observacoes as $aidRaw => $obsNested) {
            if (!is_array($obsNested)) {
                continue;
            }
            $aid = (int) $aidRaw;
            if ($aid <= 0) {
                continue;
            }
            if (!isset($faltas[$aid]) || !is_array($faltas[$aid])) {
                $faltas[$aid] = [];
            }
            foreach ($obsNested as $midRaw => $_obsVal) {
                $mid = (int) $midRaw;
                if ($mid < 0) {
                    continue;
                }
                if (!array_key_exists($mid, $faltas[$aid])) {
                    $faltas[$aid][$mid] = '';
                }
            }
        }
        $user = $this->auth->getUser();
        $createdBy = isset($user['id']) ? (int) $user['id'] : null;

        $this->absence->upsertLancamentos($eventoId, $faltas, $observacoes, $createdBy);
        $_SESSION['faltas_flash'] = 'Faltas lançadas com sucesso.';
        $_SESSION['faltas_flash_type'] = 'success';
        $eventoRow = $this->absence->getEventoById($eventoId) ?: [];
        $allowedTurmas = [];
        foreach ((array) ($eventoRow['turmas_ids'] ?? []) as $tid) {
            $tid = (int) $tid;
            if ($tid > 0) {
                $allowedTurmas[$tid] = true;
            }
        }
        $turmaIdsFiltro = [];
        foreach (array_map('intval', (array) ($_POST['turma_ids'] ?? [])) as $tid) {
            if ($tid > 0 && isset($allowedTurmas[$tid])) {
                $turmaIdsFiltro[] = $tid;
            }
        }
        $turmaIdsFiltro = array_values(array_unique($turmaIdsFiltro));
        $allowedMaterias = [];
        foreach ((array) ($eventoRow['materias_ids'] ?? []) as $mid) {
            $mid = (int) $mid;
            if ($mid > 0) {
                $allowedMaterias[$mid] = true;
            }
        }
        $rawMateriaFiltro = array_map('intval', (array) ($_POST['materia_ids'] ?? []));
        $materiaIdsFiltro = [];
        if ($allowedMaterias !== []) {
            foreach ($rawMateriaFiltro as $mid) {
                if ($mid > 0 && isset($allowedMaterias[$mid])) {
                    $materiaIdsFiltro[] = $mid;
                }
            }
        } else {
            foreach ($rawMateriaFiltro as $mid) {
                if ($mid > 0) {
                    $materiaIdsFiltro[] = $mid;
                }
            }
            $materiaIdsFiltro = array_values(array_unique($materiaIdsFiltro));
            if (count($materiaIdsFiltro) > 80) {
                $materiaIdsFiltro = array_slice($materiaIdsFiltro, 0, 80);
            }
        }
        $materiaIdsFiltro = array_values(array_unique($materiaIdsFiltro));
        $url = '/admin/faltas/lancar?evento_id=' . $eventoId;
        foreach ($turmaIdsFiltro as $tid) {
            if ($tid > 0) {
                $url .= '&turma_ids[]=' . $tid;
            }
        }
        foreach ($materiaIdsFiltro as $mid) {
            if ($mid > 0) {
                $url .= '&materia_ids[]=' . $mid;
            }
        }
        $this->redirect($url);
    }

    public function excluirEvento(): void
    {
        if (!$this->validateCsrf($_POST['_token'] ?? '')) {
            $_SESSION['faltas_flash'] = 'Token inválido. Recarregue a página e tente novamente.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
            return;
        }
        $eventoId = (int) ($_POST['evento_id'] ?? 0);
        if ($eventoId <= 0) {
            $_SESSION['faltas_flash'] = 'Evento inválido para exclusão.';
            $_SESSION['faltas_flash_type'] = 'error';
            $this->redirect('/admin/faltas');
            return;
        }
        $this->absence->deleteEvento($eventoId);
        $_SESSION['faltas_flash'] = 'Evento excluído com sucesso.';
        $_SESSION['faltas_flash_type'] = 'success';
        $this->redirect('/admin/faltas');
    }
}
