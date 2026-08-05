<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('ExerciseAdminController')) {
class ExerciseAdminController extends AdminBaseController
{
public function exercicios()
    {
        // Parâmetros de paginação
        $page = (int)($_GET['page'] ?? 1);
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // Parâmetros de filtro
        $filtros = [
            'materia' => $_GET['materia'] ?? '',
            'nivel' => $_GET['nivel'] ?? '',
            'titulo' => $_GET['titulo'] ?? ''
        ];
        
        // Construir WHERE clause
        $where_conditions = [];
        $params = [];
        
        if (!empty($filtros['materia'])) {
            $where_conditions[] = 'le.materia = :materia';
            $params['materia'] = $filtros['materia'];
        }
        
        if (!empty($filtros['nivel'])) {
            $where_conditions[] = 'le.nivel_dificuldade = :nivel';
            $params['nivel'] = $filtros['nivel'];
        }
        
        if (!empty($filtros['titulo'])) {
            $where_conditions[] = 'le.titulo LIKE :titulo';
            $params['titulo'] = '%' . $filtros['titulo'] . '%';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Buscar total de registros
        $total = $this->db->fetch(
            "SELECT COUNT(DISTINCT le.id) as total
             FROM listas_exercicios le
             LEFT JOIN usuarios u ON le.criado_por = u.id
             LEFT JOIN questoes q ON le.id = q.lista_id AND q.ativo = 1
             LEFT JOIN execucao_exercicios ee ON le.id = ee.lista_id AND ee.status = 'finalizado'
             $where_clause",
            $params
        )['total'];
        
        $total_pages = ceil($total / $per_page);
        
        // Buscar exercícios com paginação
        $exercicios = $this->db->fetchAll(
            "SELECT le.*, u.nome as criado_por_nome,
                    COUNT(DISTINCT q.id) as total_questoes,
                    COUNT(DISTINCT ee.id) as total_execucoes,
                    AVG(ee.percentual_acerto) as media_acerto
             FROM listas_exercicios le
             LEFT JOIN usuarios u ON le.criado_por = u.id
             LEFT JOIN questoes q ON le.id = q.lista_id AND q.ativo = 1
             LEFT JOIN execucao_exercicios ee ON le.id = ee.lista_id AND ee.status = 'finalizado'
             $where_clause
             GROUP BY le.id
             ORDER BY le.created_at DESC
             LIMIT $per_page OFFSET $offset",
            $params
        );
        
        // Buscar matérias disponíveis para o filtro
        $materias = $this->db->fetchAll(
            "SELECT DISTINCT materia FROM listas_exercicios WHERE materia IS NOT NULL AND materia != '' ORDER BY materia"
        );
        
        $data = [
            'title' => 'Gestão de Exercícios - EducaTudo',
            'exercises' => $exercicios,
            'user' => $this->auth->getUser(),
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken(),
            'filtros' => $filtros,
            'materias' => $materias,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => $total_pages
            ]
        ];
        
        $this->viewWithLayout('admin', 'admin/exercises/index', $data);
    }

    public function criarExercicio()
    {
        $data = [
            'title' => 'Criar Exercício - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('admin', 'admin/exercises/create', $data);
    }

    public function salvarExercicio()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/exercises?error=Token inválido');
        }

        try {
            $titulo = trim($_POST['titulo'] ?? '');
            $materia = trim($_POST['materia'] ?? '');
            $serie = trim($_POST['serie'] ?? '');
            $nivel_dificuldade = $_POST['nivel_dificuldade'] ?? '';
            $descricao = trim($_POST['descricao'] ?? '');
            
            // Validações
            if (empty($titulo) || empty($materia) || empty($serie) || empty($nivel_dificuldade)) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            if (!in_array($nivel_dificuldade, ['Fácil', 'Médio', 'Difícil'])) {
                throw new Exception('Nível de dificuldade inválido');
            }
            
            $user = $this->auth->getUser();
            
            // Criar lista de exercícios
            $listaId = $this->db->insert(
                "INSERT INTO listas_exercicios (titulo, materia, serie, nivel_dificuldade, descricao, criado_por, tipo_usuario) 
                 VALUES (:titulo, :materia, :serie, :nivel_dificuldade, :descricao, :criado_por, 'admin')",
                [
                    'titulo' => $titulo,
                    'materia' => $materia,
                    'serie' => $serie,
                    'nivel_dificuldade' => $nivel_dificuldade,
                    'descricao' => $descricao,
                    'criado_por' => $user['id']
                ]
            );
            
            $this->redirect("/admin/exercises?success=Exercício criado com sucesso! ID: {$listaId}");
            
        } catch (Exception $e) {
            $this->redirect('/admin/exercises?error=' . urlencode($e->getMessage()));
        }
    }

    public function editarExercicio($id)
    {
        $exercicio = $this->db->fetch(
            "SELECT * FROM listas_exercicios WHERE id = :id",
            ['id' => $id]
        );
        
        if (!$exercicio) {
            $this->redirect('/admin/exercises?error=Exercício não encontrado');
        }
        
        $data = [
            'title' => 'Editar Exercício - EducaTudo',
            'exercicio' => $exercicio,
            'user' => $this->auth->getUser(),
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('admin', 'admin/exercises/edit', $data);
    }

    public function atualizarExercicio($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/exercises?error=Token inválido');
        }

        try {
            $titulo = trim($_POST['titulo'] ?? '');
            $materia = trim($_POST['materia'] ?? '');
            $serie = trim($_POST['serie'] ?? '');
            $nivel_dificuldade = $_POST['nivel_dificuldade'] ?? '';
            $descricao = trim($_POST['descricao'] ?? '');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            // Validações
            if (empty($titulo) || empty($materia) || empty($serie) || empty($nivel_dificuldade)) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            if (!in_array($nivel_dificuldade, ['Fácil', 'Médio', 'Difícil'])) {
                throw new Exception('Nível de dificuldade inválido');
            }
            
            // Verificar se exercício existe
            $exercicio = $this->db->fetch(
                "SELECT * FROM listas_exercicios WHERE id = :id",
                ['id' => $id]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado');
            }
            
            // Atualizar exercício
            $this->db->update(
                "UPDATE listas_exercicios 
                 SET titulo = :titulo, materia = :materia, serie = :serie, 
                     nivel_dificuldade = :nivel_dificuldade, descricao = :descricao, 
                     ativo = :ativo, updated_at = NOW()
                 WHERE id = :id",
                [
                    'titulo' => $titulo,
                    'materia' => $materia,
                    'serie' => $serie,
                    'nivel_dificuldade' => $nivel_dificuldade,
                    'descricao' => $descricao,
                    'ativo' => $ativo,
                    'id' => $id
                ]
            );
            
            $this->redirect("/admin/exercises?success=Exercício atualizado com sucesso!");
            
        } catch (Exception $e) {
            $this->redirect('/admin/exercises?error=' . urlencode($e->getMessage()));
        }
    }

    public function excluirExercicio($id)
    {
        try {
            // Verificar se exercício existe
            $exercicio = $this->db->fetch(
                "SELECT * FROM listas_exercicios WHERE id = :id",
                ['id' => $id]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado');
            }
            
            // Verificar se há execuções em andamento
            $execucoesAtivas = $this->db->fetch(
                "SELECT COUNT(*) as count FROM execucao_exercicios WHERE lista_id = :id AND status = 'em_andamento'",
                ['id' => $id]
            );
            
            if ($execucoesAtivas['count'] > 0) {
                throw new Exception('Não é possível excluir exercício com execuções em andamento');
            }
            
            // Excluir questões primeiro (devido à foreign key)
            $this->db->update("DELETE FROM questoes WHERE lista_id = :id", ['id' => $id]);
            
            // Excluir respostas
            $this->db->update(
                "DELETE re FROM exercicios_respostas re 
                 INNER JOIN execucao_exercicios ee ON re.execucao_id = ee.id 
                 WHERE ee.lista_id = :id",
                ['id' => $id]
            );
            
            // Excluir execuções
            $this->db->update("DELETE FROM execucao_exercicios WHERE lista_id = :id", ['id' => $id]);
            
            // Excluir lista de exercícios
            $this->db->update("DELETE FROM listas_exercicios WHERE id = :id", ['id' => $id]);
            
            $this->json(['success' => true, 'message' => 'Exercício excluído com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function importarExercicios()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/exercises?error=Token inválido');
        }

        try {
            if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Arquivo JSON é obrigatório');
            }
            
            $fileContent = file_get_contents($_FILES['json_file']['tmp_name']);
            $data = json_decode($fileContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Arquivo JSON inválido: ' . json_last_error_msg());
            }
            
            if (!isset($data['exercicios']) || !is_array($data['exercicios'])) {
                throw new Exception('Formato JSON inválido. Deve conter array "exercicios"');
            }
            
            $user = $this->auth->getUser();
            $importados = 0;
            $erros = [];
            
            foreach ($data['exercicios'] as $index => $exercicioData) {
                try {
                    // Validar dados obrigatórios
                    $titulo = $exercicioData['titulo_lista'] ?? '';
                    $materia = $exercicioData['materia'] ?? '';
                    $serie = $exercicioData['serie'] ?? '';
                    $nivel = $exercicioData['nivel_dificuldade'] ?? '';
                    $questoes = $exercicioData['questoes'] ?? [];
                    
                    if (empty($titulo) || empty($materia) || empty($serie) || empty($nivel)) {
                        throw new Exception("Dados obrigatórios faltando");
                    }
                    
                    if (!in_array($nivel, ['Fácil', 'Médio', 'Difícil'])) {
                        throw new Exception("Nível inválido: {$nivel}");
                    }
                    
                    if (!is_array($questoes) || empty($questoes)) {
                        throw new Exception("Nenhuma questão encontrada");
                    }
                    
                    // Criar lista de exercícios
                    $listaId = $this->db->insert(
                        "INSERT INTO listas_exercicios (titulo, materia, serie, nivel_dificuldade, descricao, criado_por, tipo_usuario) 
                         VALUES (:titulo, :materia, :serie, :nivel_dificuldade, :descricao, :criado_por, 'admin')",
                        [
                            'titulo' => $titulo,
                            'materia' => $materia,
                            'serie' => $serie,
                            'nivel_dificuldade' => $nivel,
                            'descricao' => "Importado via JSON - " . date('d/m/Y H:i'),
                            'criado_por' => $user['id']
                        ]
                    );
                    
                    // Criar questões
                    foreach ($questoes as $qIndex => $questao) {
                        $pergunta = $questao['pergunta'] ?? '';
                        $alternativas = $questao['alternativas'] ?? [];
                        $respostaCorreta = $questao['resposta_correta'] ?? '';
                        $explicacao = $questao['explicacao'] ?? '';
                        
                        if (empty($pergunta) || empty($alternativas) || empty($respostaCorreta)) {
                            throw new Exception("Questão " . ($qIndex + 1) . " incompleta");
                        }
                        
                        $this->db->insert(
                            "INSERT INTO questoes (lista_id, pergunta, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, explicacao, tempo_estimado, ordem) 
                             VALUES (:lista_id, :pergunta, :a, :b, :c, :d, :resposta_correta, :explicacao, :tempo_estimado, :ordem)",
                            [
                                'lista_id' => $listaId,
                                'pergunta' => $pergunta,
                                'a' => $alternativas['A'] ?? '',
                                'b' => $alternativas['B'] ?? '',
                                'c' => $alternativas['C'] ?? '',
                                'd' => $alternativas['D'] ?? '',
                                'resposta_correta' => $respostaCorreta,
                                'explicacao' => $explicacao,
                                'tempo_estimado' => 120, // Padrão 2 minutos
                                'ordem' => $qIndex + 1
                            ]
                        );
                    }
                    
                    $importados++;
                    
                } catch (Exception $e) {
                    $erros[] = "Exercício " . ($index + 1) . ": " . $e->getMessage();
                }
            }
            
            $mensagem = "Importação concluída! {$importados} exercícios importados.";
            if (!empty($erros)) {
                $mensagem .= " Erros: " . implode('; ', $erros);
            }
            
            $this->redirect("/admin/exercises?success=" . urlencode($mensagem));
            
        } catch (Exception $e) {
            $this->redirect('/admin/exercises?error=' . urlencode($e->getMessage()));
        }
    }

    public function exportarExercicios($id = null)
    {
        try {
            if ($id) {
                // Exportar exercício específico
                $exercicio = $this->db->fetch(
                    "SELECT * FROM listas_exercicios WHERE id = :id",
                    ['id' => $id]
                );
                
                if (!$exercicio) {
                    throw new Exception('Exercício não encontrado');
                }
                
                $questoes = $this->db->fetchAll(
                    "SELECT * FROM questoes WHERE lista_id = :id ORDER BY ordem",
                    ['id' => $id]
                );
                
                $data = [
                    'exercicios' => [[
                        'titulo_lista' => $exercicio['titulo'],
                        'materia' => $exercicio['materia'],
                        'serie' => $exercicio['serie'],
                        'nivel_dificuldade' => $exercicio['nivel_dificuldade'],
                        'questoes' => array_map(function($q) {
                            return [
                                'id' => $q['id'],
                                'pergunta' => $q['pergunta'],
                                'alternativas' => [
                                    'A' => $q['alternativa_a'],
                                    'B' => $q['alternativa_b'],
                                    'C' => $q['alternativa_c'],
                                    'D' => $q['alternativa_d']
                                ],
                                'resposta_correta' => $q['resposta_correta'],
                                'explicacao' => $q['explicacao']
                            ];
                        }, $questoes)
                    ]]
                ];
                
                $filename = 'exercicio_' . $id . '_' . date('Y-m-d') . '.json';
                
            } else {
                // Exportar todos os exercícios
                $exercicios = $this->db->fetchAll(
                    "SELECT * FROM listas_exercicios ORDER BY created_at DESC"
                );
                
                $data = ['exercicios' => []];
                
                foreach ($exercicios as $exercicio) {
                    $questoes = $this->db->fetchAll(
                        "SELECT * FROM questoes WHERE lista_id = :id ORDER BY ordem",
                        ['id' => $exercicio['id']]
                    );
                    
                    $data['exercicios'][] = [
                        'titulo_lista' => $exercicio['titulo'],
                        'materia' => $exercicio['materia'],
                        'serie' => $exercicio['serie'],
                        'nivel_dificuldade' => $exercicio['nivel_dificuldade'],
                        'questoes' => array_map(function($q) {
                            return [
                                'id' => $q['id'],
                                'pergunta' => $q['pergunta'],
                                'alternativas' => [
                                    'A' => $q['alternativa_a'],
                                    'B' => $q['alternativa_b'],
                                    'C' => $q['alternativa_c'],
                                    'D' => $q['alternativa_d']
                                ],
                                'resposta_correta' => $q['resposta_correta'],
                                'explicacao' => $q['explicacao']
                            ];
                        }, $questoes)
                    ];
                }
                
                $filename = 'exercicios_' . date('Y-m-d') . '.json';
            }
            
            // Definir headers para download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
            
        } catch (Exception $e) {
            $this->redirect('/admin/exercises?error=' . urlencode($e->getMessage()));
        }
    }

    public function gerenciarQuestoes($id)
    {
        $exercicio = $this->db->fetch(
            "SELECT * FROM listas_exercicios WHERE id = :id",
            ['id' => $id]
        );
        
        if (!$exercicio) {
            $this->redirect('/admin/exercises?error=Exercício não encontrado');
        }
        
        $questoes = $this->db->fetchAll(
            "SELECT * FROM questoes WHERE lista_id = :id ORDER BY ordem ASC",
            ['id' => $id]
        );
        
        $data = [
            'title' => 'Gerenciar Questões - ' . $exercicio['titulo'],
            'exercicio' => $exercicio,
            'questoes' => $questoes,
            'user' => $this->auth->getUser(),
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('admin', 'admin/exercises/questions/index', $data);
    }

    public function adicionarQuestao($id)
    {
        $exercicio = $this->db->fetch(
            "SELECT * FROM listas_exercicios WHERE id = :id",
            ['id' => $id]
        );
        
        if (!$exercicio) {
            $this->redirect('/admin/exercises?error=Exercício não encontrado');
        }
        
        // Determinar próxima ordem
        $ultimaOrdem = $this->db->fetch(
            "SELECT MAX(ordem) as max_ordem FROM questoes WHERE lista_id = :id",
            ['id' => $id]
        );
        $proximaOrdem = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
        
        $data = [
            'title' => 'Adicionar Questão - ' . $exercicio['titulo'],
            'exercicio' => $exercicio,
            'proxima_ordem' => $proximaOrdem,
            'user' => $this->auth->getUser(),
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('admin', 'admin/exercises/questions/create', $data);
    }

    public function salvarQuestao($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect("/admin/exercises/{$id}/questions?error=Token inválido");
        }

        try {
            $pergunta = trim($_POST['pergunta'] ?? '');
            $alternativa_a = trim($_POST['alternativa_a'] ?? '');
            $alternativa_b = trim($_POST['alternativa_b'] ?? '');
            $alternativa_c = trim($_POST['alternativa_c'] ?? '');
            $alternativa_d = trim($_POST['alternativa_d'] ?? '');
            $resposta_correta = $_POST['resposta_correta'] ?? '';
            $explicacao = trim($_POST['explicacao'] ?? '');
            $tempo_estimado = (int)($_POST['tempo_estimado'] ?? 120);
            $ordem = (int)($_POST['ordem'] ?? 1);
            
            // Validações
            if (empty($pergunta) || empty($alternativa_a) || empty($alternativa_b) || 
                empty($alternativa_c) || empty($alternativa_d) || empty($resposta_correta)) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            if (!in_array($resposta_correta, ['A', 'B', 'C', 'D'])) {
                throw new Exception('Resposta correta deve ser A, B, C ou D');
            }
            
            // Verificar se exercício existe
            $exercicio = $this->db->fetch(
                "SELECT * FROM listas_exercicios WHERE id = :id",
                ['id' => $id]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado');
            }
            
            // Criar questão
            $questaoId = $this->db->insert(
                "INSERT INTO questoes (lista_id, pergunta, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, explicacao, tempo_estimado, ordem, ativo) 
                 VALUES (:lista_id, :pergunta, :a, :b, :c, :d, :resposta_correta, :explicacao, :tempo_estimado, :ordem, 1)",
                [
                    'lista_id' => $id,
                    'pergunta' => $pergunta,
                    'a' => $alternativa_a,
                    'b' => $alternativa_b,
                    'c' => $alternativa_c,
                    'd' => $alternativa_d,
                    'resposta_correta' => $resposta_correta,
                    'explicacao' => $explicacao,
                    'tempo_estimado' => $tempo_estimado,
                    'ordem' => $ordem
                ]
            );
            
            $this->redirect("/admin/exercises/{$id}/questions?success=Questão adicionada com sucesso! ID: {$questaoId}");
            
        } catch (Exception $e) {
            $this->redirect("/admin/exercises/{$id}/questions?error=" . urlencode($e->getMessage()));
        }
    }

    public function editarQuestao($listaId, $questaoId)
    {
        $exercicio = $this->db->fetch(
            "SELECT * FROM listas_exercicios WHERE id = :id",
            ['id' => $listaId]
        );
        
        if (!$exercicio) {
            $this->redirect('/admin/exercises?error=Exercício não encontrado');
        }
        
        $questao = $this->db->fetch(
            "SELECT * FROM questoes WHERE id = :id AND lista_id = :lista_id",
            ['id' => $questaoId, 'lista_id' => $listaId]
        );
        
        if (!$questao) {
            $this->redirect("/admin/exercises/{$listaId}/questions?error=Questão não encontrada");
        }
        
        $data = [
            'title' => 'Editar Questão - ' . $exercicio['titulo'],
            'exercicio' => $exercicio,
            'questao' => $questao,
            'user' => $this->auth->getUser(),
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('admin', 'admin/exercises/questions/edit', $data);
    }

    public function atualizarQuestao($listaId, $questaoId)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect("/admin/exercises/{$listaId}/questions?error=Token inválido");
        }

        try {
            $pergunta = trim($_POST['pergunta'] ?? '');
            $alternativa_a = trim($_POST['alternativa_a'] ?? '');
            $alternativa_b = trim($_POST['alternativa_b'] ?? '');
            $alternativa_c = trim($_POST['alternativa_c'] ?? '');
            $alternativa_d = trim($_POST['alternativa_d'] ?? '');
            $resposta_correta = $_POST['resposta_correta'] ?? '';
            $explicacao = trim($_POST['explicacao'] ?? '');
            $tempo_estimado = (int)($_POST['tempo_estimado'] ?? 120);
            $ordem = (int)($_POST['ordem'] ?? 1);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            // Validações
            if (empty($pergunta) || empty($alternativa_a) || empty($alternativa_b) || 
                empty($alternativa_c) || empty($alternativa_d) || empty($resposta_correta)) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            if (!in_array($resposta_correta, ['A', 'B', 'C', 'D'])) {
                throw new Exception('Resposta correta deve ser A, B, C ou D');
            }
            
            // Verificar se questão existe
            $questao = $this->db->fetch(
                "SELECT * FROM questoes WHERE id = :id AND lista_id = :lista_id",
                ['id' => $questaoId, 'lista_id' => $listaId]
            );
            
            if (!$questao) {
                throw new Exception('Questão não encontrada');
            }
            
            // Atualizar questão
            $this->db->update(
                "UPDATE questoes 
                 SET pergunta = :pergunta, alternativa_a = :a, alternativa_b = :b, 
                     alternativa_c = :c, alternativa_d = :d, resposta_correta = :resposta_correta, 
                     explicacao = :explicacao, tempo_estimado = :tempo_estimado, 
                     ordem = :ordem, ativo = :ativo, updated_at = NOW()
                 WHERE id = :id",
                [
                    'pergunta' => $pergunta,
                    'a' => $alternativa_a,
                    'b' => $alternativa_b,
                    'c' => $alternativa_c,
                    'd' => $alternativa_d,
                    'resposta_correta' => $resposta_correta,
                    'explicacao' => $explicacao,
                    'tempo_estimado' => $tempo_estimado,
                    'ordem' => $ordem,
                    'ativo' => $ativo,
                    'id' => $questaoId
                ]
            );
            
            $this->redirect("/admin/exercises/{$listaId}/questions?success=Questão atualizada com sucesso!");
            
        } catch (Exception $e) {
            $this->redirect("/admin/exercises/{$listaId}/questions?error=" . urlencode($e->getMessage()));
        }
    }

    public function excluirQuestao($listaId, $questaoId)
    {
        try {
            // Verificar se questão existe
            $questao = $this->db->fetch(
                "SELECT * FROM questoes WHERE id = :id AND lista_id = :lista_id",
                ['id' => $questaoId, 'lista_id' => $listaId]
            );
            
            if (!$questao) {
                throw new Exception('Questão não encontrada');
            }
            
            // Verificar se há respostas para esta questão
            $respostas = $this->db->fetch(
                "SELECT COUNT(*) as count FROM exercicios_respostas re 
                 INNER JOIN execucao_exercicios ee ON re.execucao_id = ee.id 
                 WHERE re.questao_id = :questao_id AND ee.lista_id = :lista_id",
                ['questao_id' => $questaoId, 'lista_id' => $listaId]
            );
            
            if ($respostas['count'] > 0) {
                throw new Exception('Não é possível excluir questão que já possui respostas de alunos');
            }
            
            // Excluir questão
            $this->db->update("DELETE FROM questoes WHERE id = :id", ['id' => $questaoId]);
            
            $this->json(['success' => true, 'message' => 'Questão excluída com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function reordenarQuestoes($listaId)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $ordens = $_POST['ordens'] ?? [];
            
            if (!is_array($ordens)) {
                throw new Exception('Dados de ordenação inválidos');
            }
            
            foreach ($ordens as $questaoId => $novaOrdem) {
                $this->db->update(
                    "UPDATE questoes SET ordem = :ordem WHERE id = :id AND lista_id = :lista_id",
                    [
                        'ordem' => (int)$novaOrdem,
                        'id' => (int)$questaoId,
                        'lista_id' => (int)$listaId
                    ]
                );
            }
            
            $this->json(['success' => true, 'message' => 'Questões reordenadas com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
}
