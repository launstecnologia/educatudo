<?php
/**
 * EducaTudo - SchoolUnitsAdminController
 * CRUD das unidades da escola (matriz/filial) e seus dados institucionais,
 * usados no cabeçalho das declarações oficiais.
 */

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Models/Education/SchoolUnit.php';

if (!class_exists('SchoolUnitsAdminController')) {
class SchoolUnitsAdminController extends AdminBaseController
{
    private function model(): SchoolUnit
    {
        return new SchoolUnit();
    }

    private function auditarUnidade(string $action, ?int $unidadeId, array $extra = []): void
    {
        try {
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            $user = $this->auth->getUser();
            \Logger::logAudit(
                $action,
                '/admin/unidades/' . ($unidadeId !== null ? $unidadeId : ''),
                array_merge(['unidade_id' => $unidadeId], $extra),
                $user['id'] ?? null,
                $user['tipo'] ?? null
            );
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'visualizar', false)) {
            return;
        }

        $model = $this->model();
        $unidades = $model->getAll();
        foreach ($unidades as &$u) {
            $u['total_alunos'] = $model->countStudents((int) $u['id']);
        }
        unset($u);

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Unidades da Escola - EducaTudo',
            'units' => $unidades,
            'schema_ready' => $model->tableExists(),
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'unidades',
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];

        $this->viewWithLayout('admin', 'admin/units/index', $data);
    }

    public function store(): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'cadastrar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            $dados = $this->extractPost();
            $this->aplicarLogo($dados);
            $id = $this->model()->create($dados);
            $this->auditarUnidade('CREATE_UNIT', (int) $id, ['nome' => $dados['nome'] ?? null]);
            $this->json(['success' => true, 'message' => 'Unidade cadastrada com sucesso', 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function dados($id): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'visualizar', true)) {
            return;
        }
        $unit = $this->model()->findById((int) $id);
        if (!$unit) {
            $this->json(['error' => 'Unidade não encontrada'], 404);
            return;
        }
        $this->json(['success' => true, 'unit' => $unit]);
    }

    public function update($id): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            $unit = $this->model()->findById((int) $id);
            if (!$unit) {
                throw new \RuntimeException('Unidade não encontrada');
            }
            $dados = $this->extractPost();
            $this->aplicarLogo($dados);
            $this->model()->update((int) $id, $dados);
            $this->auditarUnidade('UPDATE_UNIT', (int) $id);
            $this->json(['success' => true, 'message' => 'Unidade atualizada com sucesso']);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function delete($id): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'excluir', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            $model = $this->model();
            $unit = $model->findById((int) $id);
            if (!$unit) {
                throw new \RuntimeException('Unidade não encontrada');
            }
            $vinculados = $model->countStudents((int) $id);
            if ($vinculados > 0) {
                throw new \RuntimeException("Não é possível excluir: {$vinculados} aluno(s) vinculado(s) a esta unidade.");
            }
            $model->delete((int) $id);
            $this->auditarUnidade('DELETE_UNIT', (int) $id, ['nome' => $unit['nome'] ?? null]);
            $this->json(['success' => true, 'message' => 'Unidade excluída com sucesso']);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPost(): array
    {
        $fields = [
            'nome', 'tipo', 'razao_social', 'cnpj', 'inep', 'dependencia_administrativa',
            'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'telefone',
            'email', 'diretor_nome', 'secretario_nome',
            'ato_autorizacao', 'ato_credenciamento', 'ato_reconhecimento',
            'diretor_registro', 'secretario_registro',
        ];
        $out = [];
        foreach ($fields as $f) {
            $out[$f] = trim((string) ($_POST[$f] ?? ''));
        }
        $out['ativo'] = isset($_POST['ativo']) ? 1 : 0;
        return $out;
    }

    /**
     * Aplica upload ou remoção do logo. Sem arquivo novo e sem remover, não
     * altera logo_url (update preserva o valor atual).
     *
     * @param array<string, mixed> $dados
     */
    private function aplicarLogo(array &$dados): void
    {
        $arquivo = $_FILES['logo'] ?? null;
        if (is_array($arquivo)) {
            $erroUpload = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($erroUpload !== UPLOAD_ERR_NO_FILE && $erroUpload !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Falha no upload do logo. Use PNG, JPG, WEBP ou GIF de até 5MB.');
            }
            if ($erroUpload === UPLOAD_ERR_OK && (int) ($arquivo['size'] ?? 0) > 0) {
                $dados['logo_url'] = $this->salvarLogo($arquivo);
                return;
            }
        }
        if (!empty($_POST['remover_logo'])) {
            $dados['logo_url'] = '';
        }
    }

    /**
     * @param array<string, mixed> $file
     */
    private function salvarLogo(array $file): string
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload do logo.');
        }
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Logo muito grande (máx. 5MB).');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Arquivo de logo inválido.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmp) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        $permitidos = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!is_string($mime) || !isset($permitidos[$mime])) {
            throw new \RuntimeException('Use uma imagem PNG, JPG, WEBP ou GIF.');
        }

        $nome = 'unidade_logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new \MediaStorageService($this->config);
        if (!$media->put('layout', $nome, $tmp, $mime)) {
            throw new \RuntimeException('Não foi possível salvar o logo.');
        }

        return $media->getDisplayUrl('layout', $nome);
    }
}
}
