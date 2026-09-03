<?php
/**
 * EducaTudo - Avatar Controller
 * Gerencia seleção de avatares pré-definidos dos alunos
 */

if (!class_exists('AvatarController')) {
class AvatarController extends BaseController
{
    protected $db;
    protected $authManager;
    private $avatarDir;
    private $media;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->authManager = new AuthManager();
        // Avatares pré-definidos versionados no git (fora de public/uploads, que é gitignored)
        $this->avatarDir = __DIR__ . '/../../../public/assets/avatars/';
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $this->media = new MediaStorageService($this->config);

        if (!is_dir($this->avatarDir)) {
            mkdir($this->avatarDir, 0755, true);
        }
    }

    /**
     * Exibe página de seleção/edição de avatar
     */
    public function index()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id
                 WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            // Buscar avatar existente
            $avatar = $this->db->fetch(
                "SELECT * FROM avatares_alunos WHERE aluno_id = :aluno_id",
                ['aluno_id' => $aluno['id']]
            );
            if (is_array($avatar) && !empty($avatar['avatar_url']) && is_string($avatar['avatar_url'])) {
                if (!class_exists('ContextoAluno')) {
                    require_once __DIR__ . '/../../Core/ContextoAluno.php';
                }
                $avatar['avatar_url'] = ContextoAluno::normalizarUrlAvatar($avatar['avatar_url']);
            }

            // Buscar dados de onboarding
            $onboarding = $this->db->fetch(
                "SELECT * FROM alunos_onboarding WHERE aluno_id = :aluno_id",
                ['aluno_id' => $aluno['id']]
            );

            $data = [
                'title' => 'Meu Perfil - EducaTudo',
                'user' => $user,
                'aluno' => $aluno,
                'avatar' => $avatar,
                'onboarding' => $onboarding ?: [],
                'avatars_predefinidos' => $this->listarAvataresPredefinidos(),
                'avatares_disponiveis' => $this->listarAvataresDisponiveis(),
                'current_page' => 'avatar',
                'csrf_token' => $this->generateCsrfToken()
            ];

            $this->viewWithLayout('student', 'student/avatar', $data);

        } catch (Exception $e) {
            $this->redirect('/dashboard?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Salva avatar selecionado (pré-definido)
     */
    public function salvarSelecionado()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                throw new Exception('Token inválido');
            }

            $aluno = $this->db->fetch(
                "SELECT * FROM alunos WHERE id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $avatarSelecionado = trim($_POST['avatar_selecionado'] ?? '');
            if (empty($avatarSelecionado)) {
                throw new Exception('Avatar não selecionado');
            }

            $avataresDisponiveis = $this->listarAvataresDisponiveis();
            $avatarEscolhido = null;
            foreach ($avataresDisponiveis as $opcao) {
                if (($opcao['valor'] ?? '') === $avatarSelecionado) {
                    $avatarEscolhido = $opcao;
                    break;
                }
            }

            if ($avatarEscolhido === null) {
                throw new Exception('Avatar inválido');
            }

            $avatarUrl = (string) $avatarEscolhido['url'];

            // Verificar se já existe avatar
            $avatarExistenteId = $this->db->fetch(
                "SELECT id, descricao_objetivos FROM avatares_alunos WHERE aluno_id = :aluno_id",
                ['aluno_id' => $aluno['id']]
            );

            // Descrição de objetivos (manter existente ou usar nova)
            $descricaoObjetivos = trim($_POST['descricao_objetivos'] ?? '');
            if (empty($descricaoObjetivos) && $avatarExistenteId && !empty($avatarExistenteId['descricao_objetivos'])) {
                $descricaoObjetivos = $avatarExistenteId['descricao_objetivos'];
            }

            if ($avatarExistenteId) {
                // Remover avatar anterior se for upload personalizado (em S3 ou local)
                $avatarAntigo = $this->db->fetch(
                    "SELECT avatar_url FROM avatares_alunos WHERE id = :id",
                    ['id' => $avatarExistenteId['id']]
                );

                if ($avatarAntigo && !empty($avatarAntigo['avatar_url'])) {
                    require_once __DIR__ . '/../../Services/MediaStorageService.php';
                    $media = new MediaStorageService($this->config);
                    $this->removerAvatarAntigo($avatarAntigo['avatar_url'], $media);
                }

                // Atualizar (sem aluno_id na query UPDATE)
                $this->db->update(
                    "UPDATE avatares_alunos SET 
                     avatar_url = :avatar_url,
                     avatar_updated_at = :avatar_updated_at,
                     descricao_objetivos = :descricao_objetivos,
                     atualizado_em = :atualizado_em
                     WHERE id = :id",
                    [
                        'avatar_url' => $avatarUrl,
                        'avatar_updated_at' => date('Y-m-d H:i:s'),
                        'descricao_objetivos' => $descricaoObjetivos,
                        'atualizado_em' => date('Y-m-d H:i:s'),
                        'id' => $avatarExistenteId['id']
                    ]
                );
            } else {
                // Criar novo (nome_social obrigatório no banco: usar nome do aluno ou vazio)
                $nomeSocial = trim($aluno['nome_social'] ?? $aluno['nome'] ?? '');
                $this->db->insert(
                    "INSERT INTO avatares_alunos 
                     (aluno_id, avatar_url, avatar_updated_at, descricao_objetivos, nome_social) 
                     VALUES (:aluno_id, :avatar_url, :avatar_updated_at, :descricao_objetivos, :nome_social)",
                    [
                        'aluno_id' => $aluno['id'],
                        'avatar_url' => $avatarUrl,
                        'avatar_updated_at' => date('Y-m-d H:i:s'),
                        'descricao_objetivos' => $descricaoObjetivos,
                        'nome_social' => $nomeSocial
                    ]
                );
            }

            if (!class_exists('ContextoAluno')) {
                require_once __DIR__ . '/../../Core/ContextoAluno.php';
            }
            ContextoAluno::gravarSessaoNoLogin($this->db, (int) $aluno['id']);

            $this->redirect('/avatar?success=Avatar selecionado com sucesso!');

        } catch (Exception $e) {
            $this->redirect('/avatar?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Lista imagens pré-definidas em public/assets/avatars/ (versionadas no git).
     * Para adicionar avatar: coloque o arquivo nessa pasta e faça commit.
     *
     * @return list<string>
     */
    private function listarAvataresPredefinidos(): array
    {
        if (!is_dir($this->avatarDir)) {
            return [];
        }

        $extensoes = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
        $arquivos = [];
        $ocultos = $this->avataresBaseOcultos();

        foreach (scandir($this->avatarDir) ?: [] as $arquivo) {
            if ($arquivo === '.' || $arquivo === '..') {
                continue;
            }
            if (in_array($arquivo, $ocultos, true)) {
                continue;
            }
            if (strpos($arquivo, 'avatar_') === 0) {
                continue;
            }
            if ($arquivo !== basename($arquivo)) {
                continue;
            }
            $ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensoes, true)) {
                continue;
            }
            if (!is_file($this->avatarDir . $arquivo)) {
                continue;
            }
            $arquivos[] = $arquivo;
        }

        natcasesort($arquivos);
        return array_values($arquivos);
    }

    /**
     * Lista avatares base do sistema e avatares adicionados pela escola.
     *
     * @return list<array{valor:string, arquivo:string, url:string}>
     */
    private function listarAvataresDisponiveis(): array
    {
        $opcoes = [];

        foreach ($this->listarAvataresPredefinidos() as $arquivo) {
            $opcoes[] = [
                'valor' => $arquivo,
                'arquivo' => $arquivo,
                'url' => '/assets/avatars/' . $arquivo,
            ];
        }

        $dirTenant = $this->media->getLocalPath('avatars', 'predefinidos');
        if (is_string($dirTenant) && is_dir($dirTenant)) {
            foreach (scandir($dirTenant) ?: [] as $arquivo) {
                if ($arquivo === '.' || $arquivo === '..') {
                    continue;
                }
                if ($arquivo !== basename($arquivo)) {
                    continue;
                }
                $ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                    continue;
                }
                $key = 'predefinidos/' . $arquivo;
                if (!$this->media->exists('avatars', $key)) {
                    continue;
                }
                $opcoes[] = [
                    'valor' => $key,
                    'arquivo' => $arquivo,
                    'url' => $this->media->getDisplayUrl('avatars', $key),
                ];
            }
        }

        return $opcoes;
    }

    /**
     * @return list<string>
     */
    private function avataresBaseOcultos(): array
    {
        try {
            $row = $this->db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = :config_key LIMIT 1",
                ['config_key' => 'student_avatar_base_hidden']
            );
            $decoded = json_decode((string) ($row['config_value'] ?? '[]'), true);
            if (!is_array($decoded)) {
                return [];
            }

            return array_values(array_filter(array_map('strval', $decoded), static function (string $arquivo): bool {
                return $arquivo !== '' && $arquivo === basename($arquivo);
            }));
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Remove avatar antigo (apenas se for upload personalizado)
     */
    private function removerAvatarAntigo($avatarUrl, ?MediaStorageService $media = null)
    {
        $ref = trim((string) $avatarUrl);
        if ($ref === '') {
            return;
        }

        if (strpos($ref, '/media/serve') !== false) {
            $query = parse_url($ref, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                $type = isset($params['type']) ? trim((string) $params['type']) : '';
                $key = isset($params['key']) ? trim((string) $params['key']) : '';
                if ($type !== '' && $key !== '') {
                    if (strpos($key, 'predefinidos/') === 0) {
                        return;
                    }
                    $tenantSlug = isset($params['tenant']) ? trim((string) $params['tenant']) : '';
                    if ($tenantSlug !== '') {
                        $config = $this->config;
                        $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => $tenantSlug]);
                        $config['school'] = array_merge($config['school'] ?? [], ['code' => $tenantSlug]);
                        $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);
                        $mediaToUse = new MediaStorageService($config);
                    } else {
                        $mediaToUse = $media ?? new MediaStorageService($this->config);
                    }
                    $mediaToUse->delete($type, $key);
                    return;
                }
            }
        }

        // Só remove se for um upload personalizado (começa com avatar_)
        $fileName = basename(parse_url($avatarUrl, PHP_URL_PATH) ?: $avatarUrl);
        if (strpos($fileName, 'avatar_') === 0) {
            $uploadsDir = __DIR__ . '/../../../public/uploads/avatars/';
            $filePath = $uploadsDir . $fileName;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }
}
}
