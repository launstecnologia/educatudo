<?php

namespace App\Painel\Professor\Controllers;


use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;

if (!class_exists(__NAMESPACE__ . '\\TeacherQuestionController', false)) {
class TeacherQuestionController extends BaseController
{
    private $authManager;
    private $db;
    private $apiBase = 'http://69.62.86.185:8080';

    /** Catálogo externo pausado. Voltar para true para reexibir importação e filtros da API. */
    private function integracaoApiExternaAtiva(): bool
    {
        return false;
    }

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
        (new \App\Modulos\Provas\Services\BancoQuestaoProfessorService($this->db))->sincronizarProvasDoProfessor($professorId);

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

        $integracaoApi = $this->integracaoApiExternaAtiva();
        $where = [$integracaoApi
            ? '(professor_id IS NULL OR professor_id = :professor_id)'
            : 'professor_id = :professor_id'];
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
        if ($integracaoApi) {
            try {
                $facetsPayload = $this->apiGet('/api/facets', $facetFilters);
                $facets = is_array($facetsPayload['facets'] ?? null) ? $facetsPayload['facets'] : [];
                $totalFiltradoApi = isset($facetsPayload['total_filtrado']) ? (int) $facetsPayload['total_filtrado'] : null;
            } catch (\Throwable $e) {
                // Sem bloquear a tela se API estiver indisponível.
            }
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
            $materiasDb = $this->db->fetchAll(
                "SELECT DISTINCT materia FROM professor_questoes_api
                  WHERE materia IS NOT NULL AND materia <> '' AND professor_id = :professor_id
                  ORDER BY materia ASC",
                ['professor_id' => $professorId]
            );
            $materias = $materiasDb;
        }

        $tiposDb = $this->db->fetchAll(
            "SELECT DISTINCT tipo FROM professor_questoes_api
              WHERE tipo IS NOT NULL AND tipo <> '' AND professor_id = :professor_id
              ORDER BY tipo ASC",
            ['professor_id' => $professorId]
        );
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

        if (!$integracaoApi) {
            $dificuldadesDb = $this->db->fetchAll(
                "SELECT nivel_dificuldade AS valor, COUNT(*) AS total
                   FROM professor_questoes_api
                  WHERE professor_id = :professor_id
                    AND nivel_dificuldade IS NOT NULL
                    AND nivel_dificuldade <> ''
                  GROUP BY nivel_dificuldade
                  ORDER BY nivel_dificuldade ASC",
                ['professor_id' => $professorId]
            );
            $rotulosNivel = ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'];
            $dificuldades = [];
            foreach ($dificuldadesDb as $row) {
                $valor = (string) ($row['valor'] ?? '');
                $dificuldades[] = [
                    'valor' => $rotulosNivel[$valor] ?? $valor,
                    'total' => (int) ($row['total'] ?? 0),
                ];
            }
        }

        $anos = is_array($facets['anos'] ?? null) ? $facets['anos'] : [];
        $origensTitulo = is_array($facets['origens_titulo'] ?? null) ? $facets['origens_titulo'] : [];
        if ($integracaoApi) {
            $dificuldades = is_array($facets['dificuldades'] ?? null) ? $facets['dificuldades'] : [];
        }
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

        $materiasProfessor = [];
        try {
            $materiasProfessor = $this->db->fetchAll(
                "SELECT DISTINCT m.nome
                   FROM grade_horaria g
                   INNER JOIN materias m ON m.id = g.materia_id
                  WHERE g.professor_id = :professor_id
                    AND m.nome IS NOT NULL
                    AND m.nome <> ''
                  ORDER BY m.nome ASC",
                ['professor_id' => $professorId]
            ) ?: [];
        } catch (\Throwable $e) {
            $materiasProfessor = [];
        }

        $data = [
            'title' => 'Questões - Portal do Professor',
            'additional_css' => '<link rel="stylesheet" href="' . URL . '/public/static/css/math-editor.css">',
            'materias_professor' => $materiasProfessor,
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
            'integracao_api_externa' => $integracaoApi,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'success' => trim((string) ($_GET['success'] ?? '')),
            'error' => trim((string) ($_GET['error'] ?? '')),
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('professor', 'teacher/questoes/index', $data);
    }

    public function criar(): void
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);
        $alternativas = [];
        $brutas = json_decode((string) ($_POST['alternativas_json'] ?? ''), true);
        if (is_array($brutas)) {
            foreach ($brutas as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $alternativas[] = [
                    'texto' => (string) ($item['texto'] ?? ''),
                    'correta' => !empty($item['correta']) ? 1 : 0,
                ];
            }
        }
        $resultado = (new \App\Modulos\Provas\Services\BancoQuestaoProfessorService($this->db))->criarManual($professorId, [
            'enunciado' => (string) ($_POST['enunciado'] ?? ''),
            'tipo' => (string) ($_POST['tipo'] ?? 'multipla_escolha'),
            'nivel' => (string) ($_POST['nivel'] ?? 'facil'),
            'valor' => (float) ($_POST['valor'] ?? 1),
            'materia' => (string) ($_POST['materia'] ?? ''),
            'alternativas' => $alternativas,
        ]);
        if (empty($resultado['ok'])) {
            $this->redirect('/professor/questoes?error=' . urlencode((string) ($resultado['erro'] ?? 'Não foi possível salvar a questão.')));
        }
        $this->redirect('/professor/questoes?success=' . urlencode('Questão adicionada ao banco.'));
    }

    public function gerarIa(): void
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);
        $body = $this->corpoJson();
        if (!$this->verifyCsrfToken((string) ($body['_token'] ?? ''))) {
            $this->json(['error' => 'Token inválido. Recarregue a página.'], 403);
            return;
        }
        $materia = trim((string) ($body['materia'] ?? ''));
        $contexto = trim((string) ($body['contexto'] ?? ''));
        $serie = trim((string) ($body['serie'] ?? ''));
        $tipo = (string) ($body['tipo'] ?? 'alternativas');
        if (!in_array($tipo, ['alternativas', 'verdadeiro_falso', 'dissertativa'], true)) {
            $tipo = 'alternativas';
        }
        $pedidos = is_array($body['quantidades_por_nivel'] ?? null) ? $body['quantidades_por_nivel'] : [];
        $total = 0;
        $partes = [];
        foreach (['Fácil', 'Médio', 'Difícil', 'Desafio'] as $nivel) {
            $qtd = max(0, (int) ($pedidos[$nivel] ?? 0));
            if ($qtd > 20) {
                $qtd = 20;
            }
            if ($qtd > 0) {
                $total += $qtd;
                $partes[] = $qtd . ' ' . $nivel;
            }
        }
        if ($total <= 0) {
            $this->json(['error' => 'Informe a quantidade em pelo menos um nível.'], 400);
            return;
        }
        if ($materia === '' && $contexto === '') {
            $this->json(['error' => 'Informe a matéria ou o contexto da questão.'], 400);
            return;
        }
        $contextoPedido = $contexto;
        if ($partes !== []) {
            $contextoPedido = trim($contextoPedido . "\nQuantidades por nível: " . implode(', ', $partes) . '.');
        }
        $referencia = null;
        try {
            $referencia = $this->reservarCreditoIa($user, 'gerar_ia_banco');
            $jobId = \App\Services\AIJobService::enqueue('gerar_prova', [
                'destino' => 'banco_professor',
                'professor_id' => $professorId,
                'tema' => $materia !== '' ? $materia : 'Questões',
                'materia' => $materia !== '' ? $materia : 'Geral',
                'quantidade' => $total,
                'nivel' => implode(', ', array_map(static function (string $parte): string {
                    return preg_replace('/^\d+\s+/', '', $parte) ?: $parte;
                }, $partes)),
                'contexto_adicional' => $contextoPedido,
                'tipo' => $tipo,
                'serie' => $serie,
                'com_imagens' => !empty($body['com_imagens']),
                'credits_ref' => $referencia,
                'credits_modulo' => $referencia !== null ? 'gerar_exercicio_ia_professor' : null,
            ], $professorId, 'professor');
        } catch (\Throwable $e) {
            if ($referencia !== null) {
                try {
                    (new \App\Services\CreditosService())->estornarPorReferencia('gerar_exercicio_ia_professor', $referencia);
                } catch (\Throwable $estorno) {
                    error_log('Estorno da geração do banco: ' . $estorno->getMessage());
                }
            }
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }
        $this->json(['success' => true, 'job_id' => $jobId]);
    }

    public function importarIa(): void
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);
        $body = $this->corpoJson();
        if (!$this->verifyCsrfToken((string) ($body['_token'] ?? ''))) {
            $this->json(['error' => 'Token inválido. Recarregue a página.'], 403);
            return;
        }
        $job = $this->jobDoProfessor((int) ($body['job_id'] ?? 0), $professorId, 'gerar_prova');
        if ($job === null) {
            $this->json(['error' => 'A geração não foi encontrada.'], 404);
            return;
        }
        if ((string) ($job['status'] ?? '') !== 'done') {
            $this->json(['error' => 'A geração ainda não terminou.'], 409);
            return;
        }
        $referencia = 'job:' . (int) $job['id'];
        if ($this->jaImportou($professorId, $referencia)) {
            $this->json(['success' => true, 'message' => 'As questões já estavam no banco.']);
            return;
        }
        $resultado = json_decode((string) ($job['result'] ?? ''), true);
        $exercicios = is_array($resultado) ? ($resultado['exercicios'] ?? $resultado['questoes'] ?? []) : [];
        $payload = json_decode((string) ($job['payload'] ?? ''), true);
        $materia = is_array($payload) ? (string) ($payload['materia'] ?? '') : '';
        $tipoPedido = is_array($payload) ? (string) ($payload['tipo'] ?? 'alternativas') : 'alternativas';
        $salvas = $this->gravarQuestoesGeradas($professorId, is_array($exercicios) ? $exercicios : [], $materia, $tipoPedido, $referencia, 'banco_ia');
        if ($salvas <= 0) {
            $this->json(['error' => 'A Tudinha não devolveu questões que pudessem entrar no banco.'], 422);
            return;
        }
        $this->json(['success' => true, 'message' => $salvas . ' questão(ões) adicionada(s) ao banco.']);
    }

    public function transcrever(): void
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);
        if (!$this->verifyCsrfToken((string) ($_POST['_token'] ?? ''))) {
            $this->json(['error' => 'Token inválido. Recarregue a página.'], 403);
            return;
        }
        $arquivo = null;
        $referencia = null;
        try {
            if (empty($_FILES['imagem']) || (int) ($_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Envie um PDF ou uma imagem da questão.');
            }
            $imagem = $_FILES['imagem'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string) finfo_file($finfo, (string) $imagem['tmp_name']) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            $permitidos = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
            ];
            if (!isset($permitidos[$mime])) {
                throw new \RuntimeException('Use PDF, PNG, JPG ou WEBP.');
            }
            if ((int) $imagem['size'] > 15 * 1024 * 1024) {
                throw new \RuntimeException('Arquivo muito grande. Tamanho máximo: 15MB.');
            }
            $slug = defined('TENANT_SLUG') ? strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG)) : '';
            if ($slug === '') {
                throw new \RuntimeException('Não foi possível preparar o arquivo desta escola.');
            }
            $dir = dirname(__DIR__, 4) . '/storage/tmp/questao_transcricao/' . $slug;
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new \RuntimeException('Não foi possível preparar o arquivo.');
            }
            $path = $dir . '/questao-' . $professorId . '-' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
            if (!move_uploaded_file((string) $imagem['tmp_name'], $path)) {
                throw new \RuntimeException('Não foi possível guardar o arquivo.');
            }
            @chmod($path, 0600);
            $arquivo = $path;
            $referencia = $this->reservarCreditoIa($user, 'transcrever_banco');
            $jobId = \App\Services\AIJobService::enqueue('transcrever_questao_arquivo', [
                'destino' => 'banco_professor',
                'professor_id' => $professorId,
                'arquivo' => [
                    'path' => $path,
                    'slug' => $slug,
                    'mime' => $mime,
                    'paginas_json' => '',
                ],
                'usuario_id' => $professorId,
                'credits_ref' => $referencia,
                'credits_modulo' => $referencia !== null ? 'gerar_exercicio_ia_professor' : null,
            ], $professorId, 'professor');
            $this->json([
                'success' => true,
                'job_id' => $jobId,
                'message' => 'Arquivo enviado. A transcrição está em andamento.',
            ]);
        } catch (\Throwable $e) {
            if ($arquivo !== null && is_file($arquivo)) {
                @unlink($arquivo);
            }
            if ($referencia !== null) {
                try {
                    (new \App\Services\CreditosService())->estornarPorReferencia('gerar_exercicio_ia_professor', $referencia);
                } catch (\Throwable $estorno) {
                    error_log('Estorno da transcrição do banco: ' . $estorno->getMessage());
                }
            }
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function importarTranscricao(): void
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);
        $body = $this->corpoJson();
        if (!$this->verifyCsrfToken((string) ($body['_token'] ?? ''))) {
            $this->json(['error' => 'Token inválido. Recarregue a página.'], 403);
            return;
        }
        $job = $this->jobDoProfessor((int) ($body['job_id'] ?? 0), $professorId, 'transcrever_questao_arquivo');
        if ($job === null) {
            $this->json(['error' => 'A transcrição não foi encontrada.'], 404);
            return;
        }
        if ((string) ($job['status'] ?? '') !== 'done') {
            $this->json(['error' => 'A transcrição ainda não terminou.'], 409);
            return;
        }
        $referencia = 'job:' . (int) $job['id'];
        if ($this->jaImportou($professorId, $referencia)) {
            $this->json(['success' => true, 'message' => 'As questões já estavam no banco.']);
            return;
        }
        $resultado = json_decode((string) ($job['result'] ?? ''), true);
        $questoes = is_array($resultado) ? ($resultado['questoes'] ?? []) : [];
        $salvas = 0;
        $servico = new \App\Modulos\Provas\Services\BancoQuestaoProfessorService($this->db);
        foreach (is_array($questoes) ? $questoes : [] as $questao) {
            if (!is_array($questao)) {
                continue;
            }
            $gravada = $servico->criarManual($professorId, [
                'enunciado' => (string) ($questao['enunciado'] ?? ''),
                'tipo' => (string) ($questao['tipo'] ?? 'multipla_escolha'),
                'nivel' => 'medio',
                'valor' => (float) ($questao['valor'] ?? 1),
                'materia' => (string) ($body['materia'] ?? ''),
                'alternativas' => is_array($questao['alternativas'] ?? null) ? $questao['alternativas'] : [],
                'origem' => 'banco_transcricao',
                'prefixo' => 'banco_transcricao',
                'origem_referencia' => $referencia,
                'resolucao' => (string) ($questao['explicacao'] ?? ''),
            ]);
            if (!empty($gravada['ok'])) {
                $salvas++;
            }
        }
        if ($salvas <= 0) {
            $this->json(['error' => 'A transcrição não devolveu questões para o banco.'], 422);
            return;
        }
        $this->json(['success' => true, 'message' => $salvas . ' questão(ões) transcrita(s) no banco.']);
    }

    public function importarApi()
    {
        if (!$this->integracaoApiExternaAtiva()) {
            $this->redirect('/professor/questoes?error=' . urlencode('A importação da API está pausada.'));
        }

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
    body { font-family: \DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111827; }
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
            throw new \Exception('cURL não está disponível no servidor.');
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
            throw new \Exception('Falha ao consultar API de questões: ' . $error);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('API de questões retornou HTTP ' . $httpCode);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \Exception('Resposta inválida da API de questões.');
        }

        return $decoded;
    }

    /** @return array<string,mixed> */
    private function corpoJson(): array
    {
        $bruto = json_decode((string) file_get_contents('php://input'), true);

        return is_array($bruto) ? $bruto : $_POST;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function jobDoProfessor(int $jobId, int $professorId, string $tipo): ?array
    {
        if ($jobId <= 0) {
            return null;
        }
        $job = \App\Services\AIJobService::getJob($jobId);
        if (!is_array($job) || (int) ($job['user_id'] ?? 0) !== $professorId || (string) ($job['job_type'] ?? '') !== $tipo) {
            return null;
        }
        $payload = json_decode((string) ($job['payload'] ?? ''), true);
        if (!is_array($payload) || (string) ($payload['destino'] ?? '') !== 'banco_professor') {
            return null;
        }

        return $job;
    }

    private function jaImportou(int $professorId, string $referencia): bool
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total
               FROM professor_questoes_api
              WHERE professor_id = :professor_id
                AND origem_referencia = :referencia",
            ['professor_id' => $professorId, 'referencia' => $referencia]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * @param list<array<string,mixed>> $exercicios
     */
    private function gravarQuestoesGeradas(int $professorId, array $exercicios, string $materia, string $tipoPedido, string $referencia, string $origem): int
    {
        $mapaTipo = [
            'alternativas' => 'multipla_escolha',
            'multipla_escolha' => 'multipla_escolha',
            'verdadeiro_falso' => 'verdadeiro_falso',
            'dissertativa' => 'dissertativa',
        ];
        $tipoPadrao = $mapaTipo[$tipoPedido] ?? 'multipla_escolha';
        $servico = new \App\Modulos\Provas\Services\BancoQuestaoProfessorService($this->db);
        $salvas = 0;
        foreach ($exercicios as $exercicio) {
            if (!is_array($exercicio)) {
                continue;
            }
            $tipo = $mapaTipo[(string) ($exercicio['tipo_questao'] ?? $exercicio['tipo'] ?? '')] ?? $tipoPadrao;
            $gravada = $servico->criarManual($professorId, [
                'enunciado' => (string) ($exercicio['enunciado'] ?? $exercicio['pergunta'] ?? ''),
                'tipo' => $tipo,
                'nivel' => (string) ($exercicio['nivel'] ?? $exercicio['nivel_dificuldade'] ?? 'medio'),
                'valor' => 1,
                'materia' => $materia,
                'alternativas' => $this->alternativasDaIa($exercicio['alternativas'] ?? [], (string) ($exercicio['correta'] ?? $exercicio['resposta_correta'] ?? '')),
                'origem' => $origem,
                'prefixo' => $origem,
                'origem_referencia' => $referencia,
                'resolucao' => (string) ($exercicio['explicacao'] ?? ''),
            ]);
            if (!empty($gravada['ok'])) {
                $salvas++;
            }
        }

        return $salvas;
    }

    /**
     * @param mixed $alternativas
     * @return list<array{texto:string,correta:int}>
     */
    private function alternativasDaIa($alternativas, string $correta): array
    {
        if (!is_array($alternativas)) {
            return [];
        }
        $correta = strtoupper(trim($correta));
        $letras = ['A', 'B', 'C', 'D', 'E'];
        $lista = [];
        $i = 0;
        foreach ($alternativas as $chave => $texto) {
            if ($i >= 5) {
                break;
            }
            if (is_array($texto)) {
                $letra = strtoupper((string) ($texto['letra'] ?? $letras[$i] ?? ''));
                $texto = (string) ($texto['texto'] ?? '');
            } else {
                $letra = is_string($chave) && !is_int($chave) ? strtoupper($chave) : ($letras[$i] ?? 'A');
                $texto = (string) $texto;
            }
            $lista[] = [
                'texto' => $texto,
                'correta' => ($correta !== '' && $letra === $correta) ? 1 : 0,
            ];
            $i++;
        }

        return $lista;
    }

    /**
     * @param array<string,mixed> $user
     */
    private function reservarCreditoIa(array $user, string $acao): ?string
    {
        $creditos = new \App\Services\CreditosService();
        $slug = defined('TENANT_SLUG') ? strtolower(trim((string) TENANT_SLUG)) : '';
        $liberada = $slug === 'colegio-educa' && !$creditos->isCreditosHabilitado();
        $modulo = 'gerar_exercicio_ia_professor';
        if ($liberada || !$creditos->moduloCobraCredito($modulo) || $creditos->getCustoModulo($modulo) <= 0) {
            return null;
        }
        $papel = \App\Services\CreditosService::tipoCarteiraDoUsuario((string) ($user['tipo'] ?? 'professor'));
        $usuarioId = (int) ($user['id'] ?? 0);
        if (!$creditos->podeConsumir($papel, $usuarioId, $modulo)) {
            throw new \RuntimeException('TudiCoins insuficientes para esta ação.');
        }
        $referencia = $acao . '_' . $usuarioId . '_' . bin2hex(random_bytes(4));
        $creditos->consumir($papel, $usuarioId, $modulo, $referencia);

        return $referencia;
    }
}
}

if (!class_exists('TeacherQuestionController', false) && class_exists(__NAMESPACE__ . '\\TeacherQuestionController', false)) {
    class_alias(__NAMESPACE__ . '\\TeacherQuestionController', 'TeacherQuestionController');
}
