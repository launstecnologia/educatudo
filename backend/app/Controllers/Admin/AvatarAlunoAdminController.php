<?php
/**
 * Gestao dos avatares predefinidos exibidos no portal do aluno.
 */

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Services/MediaStorageService.php';

if (!class_exists('AvatarAlunoAdminController')) {
class AvatarAlunoAdminController extends AdminBaseController
{
    private MediaStorageService $media;

    public function __construct()
    {
        parent::__construct();
        $this->media = new MediaStorageService($this->config);
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('avatares_alunos', 'visualizar', false)) {
            return;
        }

        $flash = $this->getFlashMessage();

        $this->viewWithLayout('admin', 'admin/avatares-alunos/index', [
            'title' => 'Avatares dos Alunos - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'avatares_alunos',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
            'avatares' => $this->listarAvatares(),
        ]);
    }

    public function upload(): void
    {
        if (!$this->enforceAdminPermissionKey('avatares_alunos', 'cadastrar', false)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token invalido. Atualize a pagina e tente novamente.', 'error');
            $this->redirect('/admin/avatares-alunos');
            return;
        }

        $files = $_FILES['avatares'] ?? null;
        if (!$files || empty($files['name'])) {
            $this->setFlashMessage('Selecione pelo menos uma imagem 500x500px.', 'error');
            $this->redirect('/admin/avatares-alunos');
            return;
        }

        $total = is_array($files['name']) ? count($files['name']) : 1;
        $salvos = 0;
        $erros = [];

        for ($i = 0; $i < $total; $i++) {
            $file = $this->normalizarArquivoUpload($files, $i);
            try {
                $this->salvarArquivo($file);
                $salvos++;
            } catch (Exception $e) {
                $nome = trim((string) ($file['name'] ?? 'imagem'));
                $erros[] = ($nome !== '' ? $nome : 'imagem') . ': ' . $e->getMessage();
            }
        }

        if ($salvos > 0 && empty($erros)) {
            $this->setFlashMessage($salvos . ' avatar(es) adicionado(s) com sucesso.', 'success');
        } elseif ($salvos > 0) {
            $this->setFlashMessage($salvos . ' avatar(es) adicionado(s), mas alguns arquivos falharam. ' . implode(' ', $erros), 'error');
        } else {
            $this->setFlashMessage(implode(' ', $erros) ?: 'Nenhum avatar foi adicionado.', 'error');
        }

        $this->redirect('/admin/avatares-alunos');
    }

    public function excluir(): void
    {
        if (!$this->enforceAdminPermissionKey('avatares_alunos', 'excluir', false)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token invalido. Atualize a pagina e tente novamente.', 'error');
            $this->redirect('/admin/avatares-alunos');
            return;
        }

        $arquivo = trim((string) ($_POST['arquivo'] ?? ''));
        if ($arquivo === '' || !$this->arquivoPermitido($arquivo)) {
            $this->setFlashMessage('Avatar invalido.', 'error');
            $this->redirect('/admin/avatares-alunos');
            return;
        }

        if ($this->isCatalogoTenantKey($arquivo)) {
            if (!$this->media->exists('avatars', $arquivo)) {
                $this->setFlashMessage('Avatar nao encontrado.', 'error');
                $this->redirect('/admin/avatares-alunos');
                return;
            }

            if (!$this->media->delete('avatars', $arquivo)) {
                $this->setFlashMessage('Nao foi possivel excluir o arquivo do avatar.', 'error');
                $this->redirect('/admin/avatares-alunos');
                return;
            }
        } else {
            $this->ocultarAvatarBase($arquivo);
        }

        $limpos = $this->limparSelecoesDoAvatar($arquivo);
        $mensagem = 'Avatar excluido com sucesso.';
        if ($limpos > 0) {
            $mensagem .= ' ' . $limpos . ' aluno(s) ficaram sem avatar selecionado.';
        }

        $this->setFlashMessage($mensagem, 'success');
        $this->redirect('/admin/avatares-alunos');
    }

    public function excluirTodos(): void
    {
        if (!$this->enforceAdminPermissionKey('avatares_alunos', 'excluir', false)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token invalido. Atualize a pagina e tente novamente.', 'error');
            $this->redirect('/admin/avatares-alunos');
            return;
        }

        $avatares = $this->listarAvatares();
        $excluidos = 0;
        $limpos = 0;

        foreach ($avatares as $avatar) {
            $arquivo = (string) ($avatar['arquivo'] ?? '');
            if ($arquivo === '') {
                continue;
            }

            if ($this->isCatalogoTenantKey($arquivo) && $this->media->delete('avatars', $arquivo)) {
                $excluidos++;
                $limpos += $this->limparSelecoesDoAvatar($arquivo);
                continue;
            }

            if (!$this->isCatalogoTenantKey($arquivo)) {
                $this->ocultarAvatarBase($arquivo);
                $excluidos++;
                $limpos += $this->limparSelecoesDoAvatar($arquivo);
            }
        }

        if ($excluidos === 0) {
            $this->setFlashMessage('Nenhum avatar foi excluido.', 'error');
        } else {
            $this->setFlashMessage($excluidos . ' avatar(es) excluido(s). ' . $limpos . ' selecao(oes) de aluno foram limpas.', 'success');
        }

        $this->redirect('/admin/avatares-alunos');
    }

    private function listarAvatares(): array
    {
        $avatares = [];
        foreach ($this->listarAvataresBaseVisiveis() as $arquivoBase) {
            $pathBase = __DIR__ . '/../../../public/assets/avatars/' . $arquivoBase;
            $dimensoesBase = @getimagesize($pathBase);
            $avatares[] = [
                'arquivo' => $arquivoBase,
                'nome' => $arquivoBase,
                'origem' => 'Base',
                'url' => rtrim((string) (defined('URL') ? URL : ''), '/') . '/assets/avatars/' . rawurlencode($arquivoBase),
                'tamanho' => is_array($dimensoesBase) ? ((int) $dimensoesBase[0] . 'x' . (int) $dimensoesBase[1] . 'px') : '-',
                'usos' => $this->contarUsos($arquivoBase),
                'modificado' => is_file($pathBase) ? date('d/m/Y H:i', (int) filemtime($pathBase)) : '-',
            ];
        }

        $dir = $this->media->getLocalPath('avatars', 'predefinidos');
        if ($dir === null || !is_dir($dir)) {
            return $avatares;
        }

        foreach (scandir($dir) ?: [] as $nomeArquivo) {
            $arquivo = 'predefinidos/' . $nomeArquivo;
            if (!$this->arquivoPermitido($arquivo)) {
                continue;
            }

            $path = $this->media->getLocalPath('avatars', $arquivo);
            if (!is_file($path)) {
                continue;
            }

            $dimensoes = @getimagesize($path);
            $avatares[] = [
                'arquivo' => $arquivo,
                'nome' => $nomeArquivo,
                'origem' => 'Escola',
                'url' => $this->media->getDisplayUrl('avatars', $arquivo),
                'tamanho' => is_array($dimensoes) ? ((int) $dimensoes[0] . 'x' . (int) $dimensoes[1] . 'px') : '-',
                'usos' => $this->contarUsos($arquivo),
                'modificado' => date('d/m/Y H:i', (int) filemtime($path)),
            ];
        }

        usort($avatares, static function (array $a, array $b): int {
            return strnatcasecmp((string) $a['nome'], (string) $b['nome']);
        });

        return $avatares;
    }

    private function normalizarArquivoUpload(array $files, int $index): array
    {
        if (!is_array($files['name'])) {
            return $files;
        }

        return [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    private function salvarArquivo(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('falha no upload.');
        }

        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new Exception('arquivo maior que 5 MB.');
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new Exception('use JPG, PNG ou WebP.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new Exception('arquivo temporario indisponivel.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo ? finfo_file($finfo, $tmp) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!is_string($mimeReal) || !in_array($mimeReal, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new Exception('tipo de imagem nao permitido.');
        }

        $dimensoes = @getimagesize($tmp);
        if (!is_array($dimensoes) || (int) $dimensoes[0] !== 500 || (int) $dimensoes[1] !== 500) {
            throw new Exception('a imagem precisa ter exatamente 500x500px.');
        }

        $nome = 'predefinidos/catalogo_avatar_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!$this->media->put('avatars', $nome, $tmp, $mimeReal)) {
            throw new Exception('nao foi possivel salvar o arquivo.');
        }
    }

    private function arquivoPermitido(string $arquivo): bool
    {
        $arquivo = trim($arquivo, '/');
        if ($arquivo === '' || strpos($arquivo, '..') !== false) {
            return false;
        }

        if (!$this->isCatalogoTenantKey($arquivo) && $arquivo !== basename($arquivo)) {
            return false;
        }

        $nomeArquivo = $this->isCatalogoTenantKey($arquivo) ? basename($arquivo) : $arquivo;
        if ($nomeArquivo === '.' || $nomeArquivo === '..' || strpos($nomeArquivo, 'avatar_') === 0) {
            return false;
        }
        $ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
        return in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true);
    }

    private function contarUsos(string $arquivo): int
    {
        try {
            $url = $this->urlDoAvatar($arquivo);
            $suffix = $this->escapeLike($this->sufixoBuscaAvatar($arquivo));
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS total
                 FROM avatares_alunos
                 WHERE avatar_url = :url
                    OR avatar_url LIKE :suffix ESCAPE '\\\\'",
                [
                    'url' => $url,
                    'suffix' => '%' . $suffix . '%',
                ]
            );
            return (int) ($row['total'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function limparSelecoesDoAvatar(string $arquivo): int
    {
        try {
            $url = $this->urlDoAvatar($arquivo);
            $suffix = $this->escapeLike($this->sufixoBuscaAvatar($arquivo));
            return (int) $this->db->update(
                "UPDATE avatares_alunos
                 SET avatar_url = NULL,
                     avatar_updated_at = :avatar_updated_at,
                     atualizado_em = :atualizado_em
                 WHERE avatar_url = :url
                    OR avatar_url LIKE :suffix ESCAPE '\\\\'",
                [
                    'avatar_updated_at' => date('Y-m-d H:i:s'),
                    'atualizado_em' => date('Y-m-d H:i:s'),
                    'url' => $url,
                    'suffix' => '%' . $suffix . '%',
                ]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function escapeLike(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '%' => '\\%',
            '_' => '\\_',
        ]);
    }

    private function isCatalogoTenantKey(string $arquivo): bool
    {
        $arquivo = trim($arquivo, '/');
        return preg_match('#^predefinidos/[A-Za-z0-9_.-]+$#', $arquivo) === 1;
    }

    /**
     * @return list<string>
     */
    private function listarAvataresBaseVisiveis(): array
    {
        $dir = __DIR__ . '/../../../public/assets/avatars/';
        $ocultos = $this->avataresBaseOcultos();
        $arquivos = [];

        foreach (scandir($dir) ?: [] as $arquivo) {
            if ($arquivo === '.' || $arquivo === '..' || in_array($arquivo, $ocultos, true)) {
                continue;
            }
            if (!$this->arquivoPermitido($arquivo) || !is_file($dir . $arquivo)) {
                continue;
            }
            $arquivos[] = $arquivo;
        }

        natcasesort($arquivos);
        return array_values($arquivos);
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

    private function ocultarAvatarBase(string $arquivo): void
    {
        $arquivo = basename($arquivo);
        $ocultos = $this->avataresBaseOcultos();
        if (!in_array($arquivo, $ocultos, true)) {
            $ocultos[] = $arquivo;
            natcasesort($ocultos);
        }

        $this->db->query(
            "INSERT INTO config_layout (config_key, config_value) VALUES (:config_key, :config_value)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
            [
                'config_key' => 'student_avatar_base_hidden',
                'config_value' => json_encode(array_values($ocultos), JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function urlDoAvatar(string $arquivo): string
    {
        if ($this->isCatalogoTenantKey($arquivo)) {
            return $this->media->getDisplayUrl('avatars', $arquivo);
        }

        return '/assets/avatars/' . basename($arquivo);
    }

    private function sufixoBuscaAvatar(string $arquivo): string
    {
        if ($this->isCatalogoTenantKey($arquivo)) {
            return 'key=' . rawurlencode($arquivo);
        }

        return '/assets/avatars/' . basename($arquivo);
    }
}
}
