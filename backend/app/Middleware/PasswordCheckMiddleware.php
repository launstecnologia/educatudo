<?php
/**
 * EducaTudo - Middleware de Verificação de Senha Padrão
 * Verifica se aluno ainda tem senha padrão e redireciona para alteração
 */

class PasswordCheckMiddleware
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Executa o middleware
     */
    public function handle($request, $next)
    {
        // Verificar se usuário está logado
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
            return $next($request);
        }
        
        // Aplicar apenas para alunos
        if ($_SESSION['user_type'] !== 'aluno') {
            return $next($request);
        }
        
        // Verificar se está tentando acessar a página de alteração de senha
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($currentPath, '/aluno/alterar-senha-obrigatoria') !== false) {
            return $next($request);
        }
        
        // Verificar se aluno tem senha padrão
        $aluno = $this->db->fetch(
            "SELECT senha_hash FROM alunos WHERE id = :user_id",
            ['user_id' => $_SESSION['user_id']]
        );
        
        if ($aluno && password_verify('123456', $aluno['senha_hash'])) {
            // Redirecionar para alteração obrigatória de senha
            header('Location: ' . URL . '/aluno/alterar-senha-obrigatoria');
            exit;
        }
        
        return $next($request);
    }
}
