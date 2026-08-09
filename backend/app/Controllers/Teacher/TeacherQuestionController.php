<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';

if (!class_exists('TeacherQuestionController')) {
class TeacherQuestionController extends BaseController
{
    private $authManager;
    private $db;
    private $apiBase = 'http://69.62.86.185:8080';

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'professor') {
            $this->redirect('/professor/dashboard');
        }
    }

    public function index()
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);

        $materia = trim((string) ($_GET['materia'] ?? ''));
        $tipo = trim((string) ($_GET['tipo'] ?? ''));
        $ano = trim((string) ($_GET['ano'] ?? ''));
        $origemTitulo = trim((string) ($_GET['origem_titulo'] ?? ''));
        $dificuldade = trim((string) ($_GET['dificuldade'] ?? ''));
        $topico = trim((string) ($_GET['topico'] ?? ''));
        $tag = trim((string) ($_GET['tag'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $where = ['(professor_id IS NULL OR professor_id = :professor_id)'];
        $params = ['professor_id' => $professorId];

        if ($materia !== '') {
            $where[] = 'materia = :materia';
            $params['materia'] = $materia;
        }
        if ($tipo !== '') {
            $where[] = 'tipo = :tipo';
            $params['tipo'] = $tipo;
        }
        if ($dificuldade !== '') {
            $nivelNormalizado = $this->normalizarNivelDificuldadeBancoProfessor($dificuldade);
            $where[] = '(nivel_dificuldade = :dificuldade OR JSON_UNQUOTE(JSON_EXTRACT(source_payload, "$.dificuldade")) = :dificuldade_original)';
            $params['dificuldade'] = $nivelNormalizado ?: $dificuldade;
            $params['dificuldade_original'] = $dificuldade;
        }
        if ($q !== '') {
            $where[] = '(titulo LIKE :q OR assunto LIKE :q OR enunciado_html LIKE :q OR external_id LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);
        $totalRow = $this->db->fetch("SELECT COUNT(*) as total FROM professor_questoes_api WHERE {$whereSql}", $params);
        $total = (int) ($totalRow['total'] ?? 0);

        $sql = "SELECT * FROM professor_questoes_api
                WHERE {$whereSql}
                ORDER BY updated_at DESC, id DESC
                LIMIT {$limit} OFFSET {$offset}";
        $questoes = $this->db->fetchAll($sql, $params);

        // Facetas dinâmicas da API (filtros vinculados), mesmo sem importar no banco local.
        $facetFilters = array_filter([
            'tipo' => $tipo,
            'materia' => $materia,
            'ano' => $ano,
            'origem_titulo' => $origemTitulo,
            'dificuldade' => $dificuldade,
            'topico' => $topico,
            'tag' => $tag,
            'q' => $q,
        ], function ($v) {
            return trim((string) $v) !== '';
        });

        $facets = [];
        $totalFiltradoApi = null;
        try {
            $facetsPayload = $this->apiGet('/api/facets', $facetFilters);
            $facets = is_array($facetsPayload['facets'] ?? null) ? $facetsPayload['facets'] : [];
            $totalFiltradoApi = isset($facetsPayload['total_filtrado']) ? (int) $facetsPayload['total_filtrado'] : null;
        } catch (\Throwable $e) {
            // Sem bloquear a tela se API estiver indisponível.
        }

        $materias = [];
        if (!empty($facets['materias']) && is_array($facets['materias'])) {
            foreach ($facets['materias'] as $item) {
                $valor = trim((string) ($item['valor'] ?? ''));
                if ($valor !== '') {
                    $materias[] = ['materia' => $valor, 'total' => (int) ($item['total'] ?? 0)];
                }
            }
        } else {
            $materiasDb = $this->db->fetchAll("SELECT DISTINCT materia FROM professor_questoes_api WHERE materia IS NOT NULL AND materia <> '' ORDER BY materia ASC");
            $materias = $materiasDb;
        }

        $tiposDb = $this->db->fetchAll("SELECT DISTINCT tipo FROM professor_questoes_api WHERE tipo IS NOT NULL AND tipo <> '' ORDER BY tipo ASC");
        $tipos = !empty($tiposDb) ? $tiposDb : [
            ['tipo' => 'alternativas'],
            ['tipo' => 'aberta'],
            ['tipo' => 'erro'],
        ];
        if (!empty($facets['tipos']) && is_array($facets['tipos'])) {
            $tipos = [];
            foreach ($facets['tipos'] as $item) {
                $valor = trim((string) ($item['valor'] ?? ''));
                if ($valor !== '') {
                    $tipos[] = ['tipo' => $valor, 'total' => (int) ($item['total'] ?? 0)];
                }
            }
        }

        $anos = is_array($facets['anos'] ?? null) ? $facets['anos'] : [];
        $origensTitulo = is_array($facets['origens_titulo'] ?? null) ? $facets['origens_titulo'] : [];
        $dificuldades = is_array($facets['dificuldades'] ?? null) ? $facets['dificuldades'] : [];
        $topicos = is_array($facets['topicos'] ?? null) ? $facets['topicos'] : [];
        $tags = is_array($facets['tags'] ?? null) ? $facets['tags'] : [];

        $montagens = $this->db->fetchAll(
            "SELECT m.id, m.titulo, m.created_at, COUNT(i.id) as total_itens
               FROM professor_questoes_montagens m
               LEFT JOIN professor_questoes_montagem_itens i ON i.montagem_id = m.id
              WHERE m.professor_id = :professor_id
              GROUP BY m.id
              ORDER BY m.id DESC
              LIMIT 20",
            ['professor_id' => $professorId]
        );

        $data = [
            'title' => 'Questões - Portal do Professor',
            'page_title' => 'Questões',
            'current_page' => 'questoes',
            'user' => $user,
            'questoes' => $questoes,
            'materias' => $materias,
            'tipos' => $tipos,
            'montagens' => $montagens,
            'filtro_materia' => $materia,
            'filtro_tipo' => $tipo,
            'filtro_ano' => $ano,
            'filtro_origem_titulo' => $origemTitulo,
            'filtro_dificuldade' => $dificuldade,
            'filtro_topico' => $topico,
            'filtro_tag' => $tag,
            'filtro_q' => $q,
            'facets' => $facets,
            'facets_total_filtrado' => $totalFiltradoApi,
            'anos' => $anos,
            'origens_titulo' => $origensTitulo,
            'dificuldades' => $dificuldades,
            'topicos' => $topicos,
            'tags' => $tags,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'success' => trim((string) ($_GET['success'] ?? '')),
            'error' => trim((string) ($_GET['error'] ?? '')),
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('professor', 'teacher/questoes/index', $data);
    }

    public function importarApi()
    {
        $materia = trim((string) ($_POST['materia'] ?? ''));
        $tipo = trim((string) ($_POST['tipo'] ?? ''));
        $ano = trim((string) ($_POST['ano'] ?? ''));
        $origemTitulo = trim((string) ($_POST['origem_titulo'] ?? ''));
        $dificuldade = trim((string) ($_POST['dificuldade'] ?? ''));
        $topico = trim((string) ($_POST['topico'] ?? ''));
        $tag = trim((string) ($_POST['tag'] ?? ''));
        $q = trim((string) ($_POST['q'] ?? ''));
        $limit = 200;
        $offset = 0;
        $importadas = 0;

        while (true) {
            $query = [
                'limit' => $limit,
                'offset' => $offset,
            ];
            if ($materia !== '') {
                $query['materia'] = $materia;
            }
            if ($tipo !== '') {
                $query['tipo'] = $tipo;
            }
            if ($ano !== '') {
                $query['ano'] = $ano;
            }
            if ($origemTitulo !== '') {
                $query['origem_titulo'] = $origemTitulo;
            }
            if ($dificuldade !== '') {
                $query['dificuldade'] = $dificuldade;
            }
            if ($topico !== '') {
                $query['topico'] = $topico;
            }
            if ($tag !== '') {
                $query['tag'] = $tag;
            }
            if ($q !== '') {
                $query['q'] = $q;
            }

            $payload = $this->apiGet('/api/questoes', $query);
            $questoes = is_array($payload['questoes'] ?? null) ? $payload['questoes'] : [];
            $count = (int) ($payload['count'] ?? count($questoes));
            $total = (int) ($payload['total'] ?? 0);

            if ($count <= 0 || empty($questoes)) {
                break;
            }

            foreach ($questoes as $item) {
                $externalId = trim((string) ($item['id'] ?? ''));
                if ($externalId === '') {
                    continue;
                }

                $row = [
                    'external_id' => $externalId,
                    'materia' => mb_substr(trim((string) ($item['materia'] ?? '')), 0, 120),
                    'tipo' => mb_substr(trim((string) ($item['tipo'] ?? '')), 0, 120),
                    'enunciado_html' => (string) ($item['enunciado_html'] ?? ''),
                    'alternativas_json' => !empty($item['alternativas']) ? json_encode($item['alternativas'], JSON_UNESCAPED_UNICODE) : null,
                    'gabarito' => mb_substr(trim((string) ($item['gabarito'] ?? '')), 0, 20),
                    'resolucao_html' => (string) ($item['resolucao_html'] ?? ''),
                    'bncc' => is_array($item['bncc'] ?? null) ? json_encode($item['bncc'], JSON_UNESCAPED_UNICODE) : (string) ($item['bncc'] ?? ''),
                    'tags' => is_array($item['tags'] ?? null) ? json_encode($item['tags'], JSON_UNESCAPED_UNICODE) : (string) ($item['tags'] ?? ''),
                    'topicos' => is_array($item['topicos'] ?? null) ? json_encode($item['topicos'], JSON_UNESCAPED_UNICODE) : (string) ($item['topicos'] ?? ''),
                    'source_payload' => json_encode($item, JSON_UNESCAPED_UNICODE),
                ];

                $this->db->update(
                    "INSERT INTO professor_questoes_api
                        (external_id, materia, tipo, enunciado_html, alternativas_json, gabarito, resolucao_html, bncc, tags, topicos, source_payload, created_at, updated_at)
                     VALUES
                        (:external_id, :materia, :tipo, :enunciado_html, :alternativas_json, :gabarito, :resolucao_html, :bncc, :tags, :topicos, :source_payload, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE
                        materia = VALUES(materia),
                        tipo = VALUES(tipo),
                        enunciado_html = VALUES(enunciado_html),
                        alternativas_json = VALUES(alternativas_json),
                        gabarito = VALUES(gabarito),
                        resolucao_html = VALUES(resolucao_html),
                        bncc = VALUES(bncc),
                        tags = VALUES(tags),
                        topicos = VALUES(topicos),
                        source_payload = VALUES(source_payload),
                        updated_at = NOW()",
                    $row
                );
                $importadas++;
            }

            $offset += $limit;
            if ($offset >= $total) {
                break;
            }
        }

        $msg = $importadas > 0 ? ("Importação concluída: {$importadas} questão(ões) processadas.") : 'Nenhuma questão importada.';
        $this->redirect('/professor/questoes?success=' . urlencode($msg));
    }

    public function salvarMontagem()
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $questaoIds = $_POST['questao_ids'] ?? [];

        if ($titulo === '') {
            $this->redirect('/professor/questoes?error=' . urlencode('Informe o título da lista.'));
        }
        if (!is_array($questaoIds) || empty($questaoIds)) {
            $this->redirect('/professor/questoes?error=' . urlencode('Selecione pelo menos uma questão.'));
        }

        $montagemId = (int) $this->db->insert(
            "INSERT INTO professor_questoes_montagens (professor_id, titulo, created_at, updated_at)
             VALUES (:professor_id, :titulo, NOW(), NOW())",
            ['professor_id' => $professorId, 'titulo' => mb_substr($titulo, 0, 180)]
        );

        $ordem = 1;
        foreach ($questaoIds as $qid) {
            $questaoId = (int) $qid;
            if ($questaoId <= 0) {
                continue;
            }
            $this->db->insert(
                "INSERT IGNORE INTO professor_questoes_montagem_itens (montagem_id, questao_id, ordem, created_at)
                 VALUES (:montagem_id, :questao_id, :ordem, NOW())",
                [
                    'montagem_id' => $montagemId,
                    'questao_id' => $questaoId,
                    'ordem' => $ordem++,
                ]
            );
        }

        $this->redirect('/professor/questoes?success=' . urlencode('Lista montada com sucesso.'));
    }

    public function baixarPdfSelecionadas()
    {
        $questaoIds = $_POST['questao_ids'] ?? [];
        if (!is_array($questaoIds) || empty($questaoIds)) {
            exit('Selecione ao menos uma questão.');
        }

        $ids = array_values(array_filter(array_map('intval', $questaoIds), function ($id) {
            return $id > 0;
        }));
        if (empty($ids)) {
            exit('Selecione ao menos uma questão válida.');
        }

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $questoes = $this->db->fetchAll(
            "SELECT * FROM professor_questoes_api WHERE id IN ($ph) ORDER BY id ASC",
            $ids
        );

        $this->streamPdfQuestoes($questoes, 'questoes-selecionadas-' . date('Ymd_His') . '.pdf');
    }

    public function baixarPdfMontagem($montagemId)
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);
        $montagemId = (int) $montagemId;

        $montagem = $this->db->fetch(
            "SELECT * FROM professor_questoes_montagens WHERE id = :id AND professor_id = :professor_id",
            ['id' => $montagemId, 'professor_id' => $professorId]
        );
        if (!$montagem) {
            exit('Lista não encontrada.');
        }

        $questoes = $this->db->fetchAll(
            "SELECT q.*
               FROM professor_questoes_montagem_itens i
               JOIN professor_questoes_api q ON q.id = i.questao_id
              WHERE i.montagem_id = :montagem_id
              ORDER BY i.ordem ASC, i.id ASC",
            ['montagem_id' => $montagemId]
        );

        $slugTitulo = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $montagem['titulo']));
        $slugTitulo = trim((string) $slugTitulo, '-');
        $filename = ($slugTitulo !== '' ? $slugTitulo : 'lista') . '-' . date('Ymd_His') . '.pdf';
        $this->streamPdfQuestoes($questoes, $filename);
    }

    private function normalizarNivelDificuldadeBancoProfessor(string $nivel): ?string
    {
        $value = strtolower(trim($nivel));
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        return ['facil' => 'facil', 'medio' => 'medio', 'dificil' => 'dificil', 'desafio' => 'dificil'][$value] ?? null;
    }

    private function streamPdfQuestoes(array $questoes, string $filename): void
    {
        if (empty($questoes)) {
            exit('Nenhuma questão encontrada para gerar PDF.');
        }

        if (!class_exists('\Dompdf\Dompdf')) {
            require_once __DIR__ . '/../../../vendor/autoload.php';
        }

        $rowsHtml = '';
        $numero = 1;
        foreach ($questoes as $q) {
            $enunciado = (string) ($q['enunciado_html'] ?? '');
            $materia = htmlspecialchars((string) ($q['materia'] ?? ''));
            $tipo = htmlspecialchars((string) ($q['tipo'] ?? ''));
            $gabarito = htmlspecialchars((string) ($q['gabarito'] ?? ''));
            $alternativas = json_decode((string) ($q['alternativas_json'] ?? ''), true);

            $altHtml = '';
            if (is_array($alternativas) && !empty($alternativas)) {
                foreach ($alternativas as $k => $v) {
                    $altHtml .= '<li><strong>' . htmlspecialchars((string) $k) . ')</strong> ' . htmlspecialchars((string) $v) . '</li>';
                }
            }

            $rowsHtml .= '
                <div class="q">
                    <div class="meta">Questão ' . $numero . ' | Matéria: ' . $materia . ' | Tipo: ' . $tipo . '</div>
                    <div class="enunciado">' . $enunciado . '</div>
                    ' . ($altHtml !== '' ? ('<ul class="alternativas">' . $altHtml . '</ul>') : '') . '
                    ' . ($gabarito !== '' ? ('<div class="gabarito"><strong>Gabarito:</strong> ' . $gabarito . '</div>') : '') . '
                </div>';
            $numero++;
        }

        $html = '<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111827; }
    h1 { font-size: 18px; margin-bottom: 8px; }
    .q { border: 1px solid #d1d5db; border-radius: 8px; padding: 10px; margin: 0 0 12px; page-break-inside: avoid; }
    .meta { font-size: 11px; color: #6b7280; margin-bottom: 8px; }
    .enunciado { margin-bottom: 8px; }
    .alternativas { margin: 0 0 8px 16px; padding: 0; }
    .alternativas li { margin-bottom: 4px; }
    .gabarito { font-size: 12px; color: #065f46; }
  </style>
</head>
<body>
  <h1>Lista de Questões</h1>
  ' . $rowsHtml . '
</body>
</html>';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    private function apiGet(string $path, array $query = []): array
    {
        $url = rtrim($this->apiBase, '/') . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        if (!function_exists('curl_init')) {
            throw new Exception('cURL não está disponível no servidor.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new Exception('Falha ao consultar API de questões: ' . $error);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception('API de questões retornou HTTP ' . $httpCode);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('Resposta inválida da API de questões.');
        }

        return $decoded;
    }
}
}
