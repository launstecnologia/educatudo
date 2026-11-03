<?php

namespace App\Dominio\Escola\Controllers;
/**
 * EducaTudo - CRUD Série (tabela serie, depende de curso - migration 024)
 */


use App\Helpers\AdminPasswordHelper;
use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;

if (!class_exists(__NAMESPACE__ . '\\SerieController', false)) {
class SerieController extends BaseController
{
    private $auth;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirect('/admin');
        }
    }

    private function tableExists(): bool
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'serie'") !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cursoTableExists(): bool
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'curso'") !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function schemaReady(): bool
    {
        return $this->tableExists() && $this->cursoTableExists();
    }

    public function index()
    {
        $this->redirect('/admin/cursos-series', 301);
    }

    /**
     * Dados de uma série (JSON) para popular o offcanvas de edição
     */
    public function dados($id)
    {
        if (!$this->schemaReady()) {
            $this->json(['error' => 'Estrutura não disponível.'], 400);
            return;
        }
        $item = $this->db->fetch("SELECT * FROM serie WHERE id = :id", ['id' => $id]);
        if (!$item) {
            $this->json(['error' => 'Série não encontrada.'], 404);
            return;
        }
        $this->json(['success' => true, 'item' => $item]);
    }

    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }
        if (!$this->schemaReady()) {
            $this->json(['error' => 'Estrutura não disponível.'], 400);
            return;
        }

        try {
            $cursoId = (int) ($_POST['curso_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($cursoId <= 0) {
                throw new \Exception('Selecione o curso.');
            }
            if ($nome === '') {
                throw new \Exception('Nome é obrigatório.');
            }

            $curso = $this->db->fetch("SELECT id FROM curso WHERE id = :id", ['id' => $cursoId]);
            if (!$curso) {
                throw new \Exception('Curso inválido.');
            }

            $existe = $this->db->fetch(
                "SELECT id FROM serie WHERE curso_id = :curso_id AND nome = :nome",
                ['curso_id' => $cursoId, 'nome' => $nome]
            );
            if ($existe) {
                throw new \Exception('Já existe uma série com este nome neste curso.');
            }

            $this->db->insert(
                "INSERT INTO serie (curso_id, nome, ordem, ativo) VALUES (:curso_id, :nome, :ordem, :ativo)",
                [
                    'curso_id' => $cursoId,
                    'nome' => $nome,
                    'ordem' => $this->proximaOrdem($cursoId),
                    'ativo' => $ativo,
                ]
            );

            $this->json(['success' => true, 'message' => 'Série cadastrada com sucesso.']);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }
        if (!$this->schemaReady()) {
            $this->json(['error' => 'Estrutura não disponível.'], 400);
            return;
        }

        try {
            $id = (int) $id;
            $cursoId = (int) ($_POST['curso_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($cursoId <= 0) {
                throw new \Exception('Selecione o curso.');
            }
            if ($nome === '') {
                throw new \Exception('Nome é obrigatório.');
            }

            $item = $this->db->fetch(
                "SELECT id, curso_id, ordem FROM serie WHERE id = :id",
                ['id' => $id]
            );
            if (!$item) {
                throw new \Exception('Série não encontrada.');
            }

            $ordem = ((int) $item['curso_id'] === $cursoId)
                ? (int) $item['ordem']
                : $this->proximaOrdem($cursoId);

            $existe = $this->db->fetch(
                "SELECT id FROM serie WHERE curso_id = :curso_id AND nome = :nome AND id != :id",
                ['curso_id' => $cursoId, 'nome' => $nome, 'id' => $id]
            );
            if ($existe) {
                throw new \Exception('Já existe outra série com este nome neste curso.');
            }

            $this->db->update(
                "UPDATE serie SET curso_id = :curso_id, nome = :nome, ordem = :ordem, ativo = :ativo WHERE id = :id",
                ['curso_id' => $cursoId, 'nome' => $nome, 'ordem' => $ordem, 'ativo' => $ativo, 'id' => $id]
            );

            $this->json(['success' => true, 'message' => 'Série atualizada com sucesso.']);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Grava a ordem das séries de um curso depois do arraste na listagem.
     */
    public function reordenar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }
        if (!$this->schemaReady()) {
            $this->json(['error' => 'Estrutura não disponível.'], 400);
            return;
        }

        $cursoId = (int) ($_POST['curso_id'] ?? 0);
        $brutos = $_POST['ids'] ?? null;
        if ($cursoId <= 0 || !is_array($brutos) || $brutos === []) {
            $this->json(['error' => 'Informe o curso e a ordem das séries.'], 400);
            return;
        }

        $ids = [];
        foreach ($brutos as $valor) {
            $serieId = (int) $valor;
            if ($serieId <= 0 || in_array($serieId, $ids, true)) {
                $this->json(['error' => 'Ordem inválida.'], 400);
                return;
            }
            $ids[] = $serieId;
        }

        $linhas = $this->db->fetchAll(
            "SELECT id FROM serie WHERE curso_id = :curso_id",
            ['curso_id' => $cursoId]
        );
        $idsBanco = [];
        foreach ($linhas as $linha) {
            $idsBanco[] = (int) $linha['id'];
        }
        $recebidos = $ids;
        sort($idsBanco);
        sort($recebidos);
        if ($idsBanco === [] || $idsBanco !== $recebidos) {
            $this->json(['error' => 'A lista não confere com as séries deste curso. Atualize a página e tente de novo.'], 400);
            return;
        }

        $this->db->beginTransaction();
        try {
            $posicao = 1;
            foreach ($ids as $serieId) {
                $this->db->update(
                    "UPDATE serie SET ordem = :ordem WHERE id = :id AND curso_id = :curso_id",
                    ['ordem' => $posicao, 'id' => $serieId, 'curso_id' => $cursoId]
                );
                $posicao++;
            }
            $this->db->commit();
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->json(['error' => 'Não foi possível salvar a ordem.'], 400);
            return;
        }

        $this->json(['success' => true]);
    }

    public function destroy($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $user = $this->auth->getUser();
        $senha = trim((string) ($_POST['senha'] ?? ''));
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
            return;
        }
        if (!AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->json(['error' => 'Senha incorreta.'], 400);
            return;
        }

        $result = $this->deleteSerieById((int) $id);
        if ($result['deleted']) {
            $this->json(['success' => true, 'message' => 'Série excluída.']);
            return;
        }

        $this->json(['error' => $result['error']], 400);
    }

    public function bulkDestroy()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $user = $this->auth->getUser();
        $senha = trim((string) ($_POST['senha'] ?? ''));
        if ($senha === '') {
            $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
            return;
        }
        if (!AdminPasswordHelper::verifyAdminPassword($this->db, $user, $senha)) {
            $this->json(['error' => 'Senha incorreta.'], 400);
            return;
        }

        $ids = AdminPasswordHelper::parseIds($_POST['serie_ids'] ?? []);
        if ($ids === []) {
            $this->json(['error' => 'Selecione ao menos uma série.'], 400);
            return;
        }

        $deleted = 0;
        $errors = [];
        foreach ($ids as $id) {
            $result = $this->deleteSerieById($id);
            if ($result['deleted']) {
                $deleted++;
            } else {
                $errors[] = $result['error'];
            }
        }

        if ($deleted === 0) {
            $this->json(['error' => implode(' ', $errors)], 400);
            return;
        }

        $message = $deleted . ' série(s) excluída(s).';
        if ($errors !== []) {
            $message .= ' ' . count($errors) . ' não excluída(s): ' . implode(' ', array_slice($errors, 0, 3));
        }

        $this->json(['success' => true, 'message' => $message, 'deleted' => $deleted, 'errors' => $errors]);
    }

    private function proximaOrdem(int $cursoId): int
    {
        $linha = $this->db->fetch(
            "SELECT COALESCE(MAX(ordem), 0) AS maior FROM serie WHERE curso_id = :curso_id",
            ['curso_id' => $cursoId]
        );
        if (!is_array($linha)) {
            return 1;
        }

        return ((int) $linha['maior']) + 1;
    }

    /**
     * @return array{deleted: bool, error: string}
     */
    private function deleteSerieById(int $id): array
    {
        if (!$this->schemaReady()) {
            return ['deleted' => false, 'error' => 'Estrutura não disponível.'];
        }

        $item = $this->db->fetch("SELECT id, nome FROM serie WHERE id = :id", ['id' => $id]);
        if (!$item) {
            return ['deleted' => false, 'error' => 'Série #' . $id . ' não encontrada.'];
        }

        $temTurma = false;
        try {
            $hasCol = $this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'serie_id'");
            if ($hasCol !== false) {
                $temTurma = $this->db->fetch("SELECT id FROM turmas WHERE serie_id = :id LIMIT 1", ['id' => $id]) !== false;
            }
        } catch (\Exception $e) {
            $temTurma = false;
        }
        if ($temTurma) {
            return ['deleted' => false, 'error' => '"' . $item['nome'] . '": possui turmas vinculadas.'];
        }

        $this->db->delete("DELETE FROM serie WHERE id = :id", ['id' => $id]);

        return ['deleted' => true, 'error' => ''];
    }
}
}

if (!class_exists('SerieController', false) && class_exists(__NAMESPACE__ . '\\SerieController', false)) {
    class_alias(__NAMESPACE__ . '\\SerieController', 'SerieController');
}
