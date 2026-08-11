<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/../../Models/User/Teacher.php';
require_once __DIR__ . '/../../Models/Education/Subject.php';
require_once __DIR__ . '/AdminBaseController.php';

if (!class_exists('TeacherAdminController')) {
class TeacherAdminController extends AdminBaseController
{
    public function professores()
    {
        $search = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? '';
        $pagante = $_GET['pagante'] ?? '';
        $perPage = (int)($_GET['per_page'] ?? 10);
        $page = (int)($_GET['page'] ?? 1);

        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        if ($page < 1) {
            $page = 1;
        }

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(p.nome LIKE :q_nome OR p.email LIKE :q_email OR p.codigo_prof LIKE :q_codigo)";
            $params['q_nome'] = '%' . $search . '%';
            $params['q_email'] = '%' . $search . '%';
            $params['q_codigo'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $where[] = "p.ativo = :ativo";
            $params['ativo'] = ($status === 'ativo') ? 1 : 0;
        }

        if ($pagante !== '') {
            $where[] = "p.pagante = :pagante";
            $params['pagante'] = ($pagante === 'sim') ? 1 : 0;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = $this->db->fetch(
            "SELECT COUNT(*) as total FROM professores p {$whereSql}",
            $params
        )['total'] ?? 0;

        $offset = ($page - 1) * $perPage;
        $limit = (int) $perPage;
        $offset = (int) $offset;
        $professores = $this->db->fetchAll(
            "SELECT p.*
             FROM professores p
             {$whereSql}
             ORDER BY p.nome ASC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas ORDER BY nome ASC");
        $turmasMap = [];
        foreach ($turmas as $turma) {
            $turmasMap[(string) $turma['id']] = $turma['nome'];
        }

        $teacherModel = new Teacher();

        $data = [
            'title' => 'Gestão de Professores - EducaTudo',
            'teachers' => $professores,
            'turmas_map' => $turmasMap,
            'materias_disponiveis' => $teacherModel->getSubjectsForForm(),
            'turmas_disponiveis' => $this->db->fetchAll("SELECT * FROM turmas WHERE ativo = 1 ORDER BY nome ASC"),
            'user' => $this->auth->getUser(),
            'current_page' => 'teachers',
            'csrf_token' => $this->generateCsrfToken(),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'pagante' => $pagante,
                'per_page' => $perPage,
                'page' => $page,
                'total' => $total
            ]
        ];
        
        $this->viewWithLayout('admin', 'admin/teachers/index', $data);
    }

    public function exportarProfessoresCSV()
    {
        $professores = $this->db->fetchAll(
            "SELECT p.*
             FROM professores p
             ORDER BY p.nome ASC"
        );
        
        // Buscar todas as matérias cadastradas no banco (com ID)
        $todasMaterias = $this->db->fetchAll(
            "SELECT id, nome FROM materias ORDER BY id ASC"
        );
        
        // Criar cabeçalho CSV com IDs das matérias
        $header = ['nome'];
        foreach ($todasMaterias as $materia) {
            $header[] = $materia['id']; // Usar ID ao invés do nome
        }
        $header[] = 'email';
        
        // Configurar headers para download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="professores_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Abrir output stream
        $output = fopen('php://output', 'w');
        
        // Adicionar BOM para UTF-8 (Excel)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Escrever cabeçalho
        fputcsv($output, $header, ';');
        
        // Escrever dados
        foreach ($professores as $prof) {
            $row = [$prof['nome']];
            
            $materias = json_decode($prof['materias'] ?? '[]', true);
            foreach ($todasMaterias as $materia) {
                // Verificar se o professor leciona esta matéria (comparar por nome)
                $row[] = in_array($materia['nome'], $materias ?: []) ? 'X' : '';
            }
            
            $row[] = $prof['email'] ?? '';
            
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }

    public function importarProfessoresCSV()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro ao fazer upload do arquivo CSV');
            }
            
            $file = $_FILES['csv_file']['tmp_name'];
            $teacherModel = new Teacher();
            require_once __DIR__ . '/../../Models/Education/Subject.php';
            $subjectModel = new Subject();
            
            // Ler arquivo CSV
            $handle = fopen($file, 'r');
            if (!$handle) {
                throw new Exception('Não foi possível ler o arquivo CSV');
            }
            
            // Detectar delimitador (priorizar ; ponto e vírgula, depois vírgula)
            $primeiraLinha = fgets($handle);
            rewind($handle);
            
            $delimitador = ';'; // Padrão: ponto e vírgula
            // Se não tiver ponto e vírgula mas tiver vírgula, usa vírgula
            if (strpos($primeiraLinha, ';') === false && strpos($primeiraLinha, ',') !== false) {
                $delimitador = ',';
            }
            
            // Ler primeira linha (cabeçalho)
            $header = fgetcsv($handle, 1000, $delimitador);
            if (!$header) {
                throw new Exception('Arquivo CSV vazio ou inválido');
            }
            
            // Remover BOM se existir
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            
            // Limpar espaços dos cabeçalhos
            $header = array_map('trim', $header);
            
            // Encontrar índices das colunas
            $nomeIndex = array_search('nome', array_map('strtolower', $header));
            $emailIndex = array_search('email', array_map('strtolower', $header));
            
            if ($nomeIndex === false) {
                throw new Exception('Coluna "nome" não encontrada no CSV');
            }
            
            // Identificar colunas de matérias (todas exceto nome e email) - são IDs das matérias
            $materiasColunas = [];
            foreach ($header as $index => $col) {
                $colLower = strtolower(trim($col));
                if ($colLower !== 'nome' && $colLower !== 'email') {
                    // A coluna é o ID da matéria
                    $materiaId = (int)trim($col);
                    if ($materiaId > 0) {
                        $materiasColunas[$index] = $materiaId;
                    }
                }
            }
            
            // Buscar todas as matérias do banco para validação
            $todasMateriasBanco = $subjectModel->getAll();
            $materiasPorId = [];
            foreach ($todasMateriasBanco as $materia) {
                $materiasPorId[$materia['id']] = $materia['nome'];
            }
            
            $sucessos = 0;
            $erros = 0;
            $linha = 1;
            $mensagensErro = [];
            
            // Processar cada linha
            while (($data = fgetcsv($handle, 1000, $delimitador)) !== false) {
                $linha++;
                
                if (count($data) < 2) {
                    continue; // Linha vazia ou inválida
                }
                
                try {
                    $nome = trim($data[$nomeIndex] ?? '');
                    $email = trim($data[$emailIndex] ?? '');
                    
                    if (empty($nome)) {
                        throw new Exception('Nome é obrigatório');
                    }
                    
                    // Coletar matérias por ID (colunas com valor 'X' - professor pode ter uma ou mais matérias)
                    $materias = [];
                    foreach ($materiasColunas as $index => $materiaId) {
                        if (isset($data[$index])) {
                            $valor = trim($data[$index]);
                            // Aceita 'X' ou 'x' (case insensitive) - professor pode ter múltiplas matérias
                            if (!empty($valor) && strtoupper($valor) === 'X') {
                                // Validar se o ID da matéria existe no banco
                                if (isset($materiasPorId[$materiaId])) {
                                    // Adicionar o nome da matéria (como está armazenado no banco)
                                    $materias[] = $materiasPorId[$materiaId];
                                } else {
                                    throw new Exception("Matéria com ID {$materiaId} não encontrada no banco de dados");
                                }
                            }
                        }
                    }
                    
                    // Professor pode ter uma ou mais matérias (ou nenhuma, se necessário)
                    // Não há validação obrigatória de matérias
                    
                    // Verificar se email já existe
                    if (!empty($email) && $teacherModel->emailExists($email)) {
                        throw new Exception("Email já cadastrado: {$email}");
                    }
                    
                    // Gerar código do professor
                    $codigo_prof = $teacherModel->generateCode();
                    
                    // Criar professor com senha padrão 123456
                    $teacherModel->create([
                        'nome' => $nome,
                        'email' => $email ?: null,
                        'senha' => '123456',
                        'codigo_prof' => $codigo_prof,
                        'materias' => $materias,
                        'turmas' => [],
                        'ativo' => 1
                    ]);
                    
                    $sucessos++;
                    
                } catch (Exception $e) {
                    $erros++;
                    $mensagensErro[] = "Linha {$linha}: " . $e->getMessage();
                }
            }
            
            fclose($handle);
            
            $this->json([
                'success' => true,
                'message' => "Importação concluída! {$sucessos} professor(es) importado(s) com sucesso" . ($erros > 0 ? ", {$erros} erro(s)" : ""),
                'sucessos' => $sucessos,
                'erros' => $erros,
                'mensagens_erro' => $mensagensErro
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function salvarProfessor()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $materias = $_POST['materias'] ?? [];
            $turmas = $_POST['turmas'] ?? [];
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            // Validações
            if (empty($nome) || empty($email)) {
                throw new Exception('Nome e email são obrigatórios');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            $teacherModel = new Teacher();
            
            // Verifica se email já existe
            if ($teacherModel->emailExists($email)) {
                throw new Exception('Email já cadastrado');
            }
            
            // Gera código do professor automaticamente
            $codigo_prof = $teacherModel->generateCode();
            
            // Senha padrão para professores cadastrados pelo admin
            $senha_padrao = '123456';
            
            // Cria professor com senha padrão
            $teacherModel->create([
                'nome' => $nome,
                'email' => $email,
                'senha' => $senha_padrao,
                'codigo_prof' => $codigo_prof,
                'materias' => $materias,
                'turmas' => $turmas,
                'ativo' => $ativo
            ]);
            
            $this->json(['success' => true, 'message' => 'Professor cadastrado com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function dadosProfessor($id)
    {
        $teacherModel = new Teacher();
        $professor = $teacherModel->findById($id);

        if (!$professor) {
            $this->json(['error' => 'Professor não encontrado'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'teacher' => [
                'id' => (int) $professor['id'],
                'nome' => $professor['nome'],
                'email' => $professor['email'],
                'ativo' => (bool) $professor['ativo'],
                'pagante' => (bool) ($professor['pagante'] ?? 1),
                'materias_array' => json_decode($professor['materias'] ?? '[]', true) ?: [],
                'turmas_array' => array_map('intval', json_decode($professor['turmas'] ?? '[]', true) ?: []),
                'created_at' => $professor['created_at'],
                'updated_at' => $professor['updated_at'],
            ],
        ]);
    }

    public function atualizarProfessor($id)
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $novaSenha = trim($_POST['nova_senha'] ?? '');
            $confirmarSenha = trim($_POST['confirmar_senha'] ?? '');
            $materias = $_POST['materias'] ?? [];
            $turmas = $_POST['turmas'] ?? [];
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $pagante = isset($_POST['pagante']) ? 1 : 0;
            
            // Validações
            if (empty($nome) || empty($email)) {
                throw new Exception('Nome e email são obrigatórios');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            // Senha: só altera se o admin preencher os dois campos; se deixar em branco, mantém a senha atual
            $senha_para_atualizar = '';
            if ($novaSenha !== '' || $confirmarSenha !== '') {
                if (strlen($novaSenha) < 6) {
                    throw new Exception('A nova senha deve ter no mínimo 6 caracteres');
                }
                if ($novaSenha !== $confirmarSenha) {
                    throw new Exception('Nova senha e Confirmar senha não coincidem');
                }
                $senha_para_atualizar = $novaSenha;
            }
            
            $teacherModel = new Teacher();
            
            // Verifica se professor existe
            if (!$teacherModel->exists($id)) {
                throw new Exception('Professor não encontrado');
            }
            
            // Busca professor atual para manter o código existente
            $professorAtual = $teacherModel->findById($id);
            $codigo_prof = $professorAtual['codigo_prof'] ?? $teacherModel->generateCode();
            
            // Verifica se email já existe (exceto para o próprio professor)
            if ($teacherModel->emailExists($email, $id)) {
                throw new Exception('Email já cadastrado');
            }
            
            // Atualiza professor (senha só é alterada se admin preencheu nova_senha e confirmar_senha)
            $teacherModel->update($id, [
                'nome' => $nome,
                'email' => $email,
                'senha' => $senha_para_atualizar,
                'codigo_prof' => $codigo_prof,
                'materias' => $materias,
                'turmas' => $turmas,
                'ativo' => $ativo,
                'pagante' => $pagante
            ]);
            
            $this->json(['success' => true, 'message' => 'Professor atualizado com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function excluirProfessor($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $teacherModel = new Teacher();
            
            // Verifica se professor existe
            if (!$teacherModel->exists($id)) {
                throw new Exception('Professor não encontrado');
            }
            
            // Verifica se há jornadas vinculadas ao professor
            $jornadasVinculadas = $this->db->fetchAll(
                "SELECT COUNT(*) as total FROM jornadas WHERE professor_id = :id",
                ['id' => $id]
            );
            
            if ($jornadasVinculadas[0]['total'] > 0) {
                throw new Exception('Não é possível excluir professor com jornadas vinculadas');
            }
            
            // Verifica se há matérias vinculadas ao professor
            // Como não há mais professor_id na tabela materias, 
            // esta verificação não é mais necessária
            $materiasVinculadas = [['total' => 0]];
            
            if ($materiasVinculadas[0]['total'] > 0) {
                throw new Exception('Não é possível excluir professor com matérias vinculadas');
            }
            
            // Exclui professor
            $teacherModel->delete($id);
            
            $this->json(['success' => true, 'message' => 'Professor excluído com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function toggleStatusProfessor($id)
    {
        try {
            $teacherModel = new Teacher();
            
            // Verifica se professor existe
            if (!$teacherModel->exists($id)) {
                throw new Exception('Professor não encontrado');
            }
            
            // Alterna status
            $teacherModel->toggleStatus($id);
            
            $this->json(['success' => true, 'message' => 'Status alterado com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function togglePaganteProfessor($id)
    {
        try {
            $teacherModel = new Teacher();
            
            // Verifica se professor existe
            if (!$teacherModel->exists($id)) {
                throw new Exception('Professor não encontrado');
            }
            
            // Buscar professor atual
            $professor = $teacherModel->findById($id);
            $novoStatus = ($professor['pagante'] ?? 1) ? 0 : 1;
            
            // Atualizar status de pagante
            $this->db->update(
                "UPDATE professores SET pagante = :pagante WHERE id = :id",
                ['pagante' => $novoStatus, 'id' => $id]
            );
            
            $this->json(['success' => true, 'message' => 'Status de pagante alterado com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function jornadas()
    {
        // Redireciona para a rota nova
        $this->redirect('/admin/jornadas');
    }
}
}
