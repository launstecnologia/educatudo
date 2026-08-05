<?php
/**
 * EducaTudo - CRUD de usuários master (painel admin master).
 * Listar, criar, editar e desativar usuários; trocar senha na edição.
 */

if (!class_exists('MasterUsuariosController')) {

class MasterUsuariosController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';
    private const SESSION_MASTER_USER_NOME = 'master_user_nome';
    private const SESSION_MASTER_USER_AVATAR = 'master_user_avatar';

    public function __construct()
    {
        parent::__construct();
    }

    private function avatarService(): MasterAvatarService
    {
        if (!class_exists('MasterAvatarService')) {
            require_once __DIR__ . '/../../Services/MasterAvatarService.php';
        }
        return new MasterAvatarService($this->config);
    }

    private function syncSessaoUsuarioAtual(int $id, string $nome, ?string $avatarUrl = null): void
    {
        $currentId = (int) ($_SESSION[self::SESSION_MASTER_USER_ID] ?? 0);
        if ($currentId !== $id) {
            return;
        }
        $_SESSION[self::SESSION_MASTER_USER_NOME] = $nome;
        if ($avatarUrl !== null) {
            $_SESSION[self::SESSION_MASTER_USER_AVATAR] = $avatarUrl;
        }
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    /**
     * Lista usuários master
     */
    public function index()
    {
        $this->requireMaster();
        $db = Database::getInstance();
        $usuarios = $db->query(
            "SELECT id, email, nome, avatar_url, ativo FROM usuarios_master ORDER BY nome, email"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->viewWithLayout('master', 'master/usuarios/index', [
            'title' => 'Usuários Master - Painel Master',
            'page_title' => 'Usuários Master',
            'current_page' => 'usuarios',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'usuarios' => $usuarios,
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create()
    {
        $this->requireMaster();
        $this->viewWithLayout('master', 'master/usuarios/form', [
            'title' => 'Novo usuário master - Painel Master',
            'page_title' => 'Novo usuário master',
            'current_page' => 'usuarios',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'usuario' => null,
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * POST: salvar novo usuário
     */
    public function store()
    {
        $this->requireMaster();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/usuarios/criar');
            exit;
        }
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $nome = trim($_POST['nome'] ?? '');

        if ($email === '' || strlen($senha) < 6) {
            $this->setFlashMessage('Preencha e-mail e senha (mínimo 6 caracteres).', 'error');
            header('Location: ' . URL . '/master/usuarios/criar');
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setFlashMessage('E-mail inválido.', 'error');
            header('Location: ' . URL . '/master/usuarios/criar');
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->query("SELECT id FROM usuarios_master WHERE email = ?", [$email]);
        if ($stmt->fetch()) {
            $this->setFlashMessage('Este e-mail já está cadastrado.', 'error');
            header('Location: ' . URL . '/master/usuarios/criar');
            exit;
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $nomeFinal = $nome ?: 'Master';
        $id = (int) $db->insert(
            "INSERT INTO usuarios_master (email, senha_hash, nome, ativo) VALUES (?, ?, ?, 1)",
            [$email, $senhaHash, $nomeFinal]
        );

        try {
            $avatarUrl = $this->avatarService()->upload($id);
            if ($avatarUrl !== null) {
                $db->query("UPDATE usuarios_master SET avatar_url = ? WHERE id = ?", [$avatarUrl, $id]);
            }
        } catch (InvalidArgumentException $e) {
            $db->query("DELETE FROM usuarios_master WHERE id = ?", [$id]);
            $this->setFlashMessage($e->getMessage(), 'error');
            header('Location: ' . URL . '/master/usuarios/criar');
            exit;
        } catch (Throwable $e) {
            error_log('MasterUsuariosController store avatar: ' . $e->getMessage());
            $this->setFlashMessage('Usuário criado, mas não foi possível salvar a foto. Tente editar o usuário.', 'error');
            header('Location: ' . URL . '/master/usuarios');
            exit;
        }

        $this->setFlashMessage('Usuário criado com sucesso.', 'success');
        header('Location: ' . URL . '/master/usuarios');
        exit;
    }

    /**
     * Formulário de edição (com opção de trocar senha)
     */
    public function edit()
    {
        $this->requireMaster();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('ID inválido.', 'error');
            header('Location: ' . URL . '/master/usuarios');
            exit;
        }
        $db = Database::getInstance();
        $usuario = $db->query(
            "SELECT id, email, nome, avatar_url, ativo FROM usuarios_master WHERE id = ?",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            $this->setFlashMessage('Usuário não encontrado.', 'error');
            header('Location: ' . URL . '/master/usuarios');
            exit;
        }

        $this->viewWithLayout('master', 'master/usuarios/form', [
            'title' => 'Editar usuário master - Painel Master',
            'page_title' => 'Editar usuário master',
            'current_page' => 'usuarios',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'usuario' => $usuario,
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * POST: atualizar usuário (nome, ativo, opcionalmente senha)
     */
    public function update()
    {
        $this->requireMaster();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('ID inválido.', 'error');
            header('Location: ' . URL . '/master/usuarios');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/usuarios/editar?id=' . $id);
            exit;
        }
        $db = Database::getInstance();
        $existente = $db->query("SELECT id, email FROM usuarios_master WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$existente) {
            $this->setFlashMessage('Usuário não encontrado.', 'error');
            header('Location: ' . URL . '/master/usuarios');
            exit;
        }

        $nome = trim($_POST['nome'] ?? '');
        $nomeFinal = $nome ?: $existente['email'];
        $ativo = isset($_POST['ativo']) && $_POST['ativo'] === '1' ? 1 : 0;
        $novaSenha = $_POST['nova_senha'] ?? '';
        $avatarUrl = null;

        if ($novaSenha !== '' && strlen($novaSenha) < 6) {
            $this->setFlashMessage('Nova senha deve ter no mínimo 6 caracteres.', 'error');
            header('Location: ' . URL . '/master/usuarios/editar?id=' . $id);
            exit;
        }

        try {
            $avatarUrl = $this->avatarService()->upload($id);
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            header('Location: ' . URL . '/master/usuarios/editar?id=' . $id);
            exit;
        }

        if ($novaSenha !== '') {
            $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            if ($avatarUrl !== null) {
                $db->query(
                    "UPDATE usuarios_master SET nome = ?, ativo = ?, senha_hash = ?, avatar_url = ? WHERE id = ?",
                    [$nomeFinal, $ativo, $senhaHash, $avatarUrl, $id]
                );
            } else {
                $db->query(
                    "UPDATE usuarios_master SET nome = ?, ativo = ?, senha_hash = ? WHERE id = ?",
                    [$nomeFinal, $ativo, $senhaHash, $id]
                );
            }
            $this->setFlashMessage('Usuário e senha atualizados com sucesso.', 'success');
        } else {
            if ($avatarUrl !== null) {
                $db->query(
                    "UPDATE usuarios_master SET nome = ?, ativo = ?, avatar_url = ? WHERE id = ?",
                    [$nomeFinal, $ativo, $avatarUrl, $id]
                );
            } else {
                $db->query(
                    "UPDATE usuarios_master SET nome = ?, ativo = ? WHERE id = ?",
                    [$nomeFinal, $ativo, $id]
                );
            }
            $this->setFlashMessage('Usuário atualizado com sucesso.', 'success');
        }

        $avatarSessao = $avatarUrl;
        if ($avatarSessao === null) {
            $row = $db->query("SELECT avatar_url FROM usuarios_master WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
            $avatarSessao = $row['avatar_url'] ?? '';
        }
        $this->syncSessaoUsuarioAtual($id, $nomeFinal, $avatarSessao ?: '');
        header('Location: ' . URL . '/master/usuarios');
        exit;
    }

    /**
     * POST: desativar usuário (ativo = 0). Não permite desativar a si mesmo.
     */
    public function desativar()
    {
        $this->requireMaster();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/usuarios');
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $currentId = (int) ($_SESSION[self::SESSION_MASTER_USER_ID] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('ID inválido.', 'error');
            header('Location: ' . URL . '/master/usuarios');
            exit;
        }
        if ($id === $currentId) {
            $this->setFlashMessage('Você não pode desativar sua própria conta.', 'error');
            header('Location: ' . URL . '/master/usuarios');
            exit;
        }
        $db = Database::getInstance();
        $db->query("UPDATE usuarios_master SET ativo = 0 WHERE id = ?", [$id]);
        $this->setFlashMessage('Usuário desativado.', 'success');
        header('Location: ' . URL . '/master/usuarios');
        exit;
    }
}

}
