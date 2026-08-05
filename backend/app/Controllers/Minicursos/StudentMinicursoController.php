<?php
/**
 * EducaTudo - Minicursos (Aluno)
 * Lista minicursos, assiste aulas (Vídeo, Slides, PDF, Link), progresso
 */

if (!class_exists('StudentMinicursoController')) {
class StudentMinicursoController extends BaseController
{
    private $auth;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $this->auth->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    private function getAlunoId()
    {
        return (int)$this->auth->getUser()['id'];
    }

    private function checkModuleMinicursos()
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('module_aluno_minicursos', '1') !== '1') {
            $this->setFlashMessage('O módulo Minicursos está desabilitado.', 'error');
            $this->redirect('/dashboard');
            exit;
        }
    }

    public function index()
    {
        $this->checkModuleMinicursos();
        $lista = $this->db->fetchAll(
            "SELECT m.*,
             (SELECT COUNT(*) FROM minicursos_modulos mm WHERE mm.minicurso_id = m.id) as total_modulos,
             (SELECT COUNT(*) FROM minicursos_aulas ma JOIN minicursos_modulos mm ON ma.modulo_id = mm.id WHERE mm.minicurso_id = m.id) as total_aulas
             FROM minicursos m WHERE m.ativo = 1 ORDER BY m.ordem, m.id"
        );
        $aluno_id = $this->getAlunoId();
        foreach ($lista as &$row) {
            $prog = $this->db->fetch("SELECT aulas_vistas, concluido_em FROM minicursos_progresso WHERE aluno_id = :aid AND minicurso_id = :mid", ['aid' => $aluno_id, 'mid' => $row['id']]);
            $vistas = $prog ? (json_decode($prog['aulas_vistas'] ?? '[]', true) ?: []) : [];
            $row['aulas_vistas_count'] = count($vistas);
            $row['concluido'] = !empty($prog['concluido_em']);
        }
        unset($row);
        $data = [
            'title' => 'Minicursos - EducaTudo',
            'user' => $this->auth->getUser(),
            'lista' => $lista,
            'current_page' => 'minicursos'
        ];
        $this->viewWithLayout('student', 'student/minicursos/index', $data);
    }

    public function show($id)
    {
        $this->checkModuleMinicursos();
        $id = (int)$id;
        $minicurso = $this->db->fetch("SELECT * FROM minicursos WHERE id = :id AND ativo = 1", ['id' => $id]);
        if (!$minicurso) {
            $this->setFlashMessage('Minicurso não encontrado.', 'error');
            $this->redirect('/minicursos');
            return;
        }
        $arquivos = [];
        try {
            $arquivos = $this->db->fetchAll("SELECT * FROM minicursos_arquivos WHERE minicurso_id = :id ORDER BY ordem, id", ['id' => $id]);
        } catch (\Exception $e) {
        }
        $modulos = $this->db->fetchAll("SELECT * FROM minicursos_modulos WHERE minicurso_id = :id ORDER BY ordem, id", ['id' => $id]);
        $todasAulas = [];
        foreach ($modulos as &$mod) {
            $mod['aulas'] = $this->db->fetchAll("SELECT * FROM minicursos_aulas WHERE modulo_id = :mid ORDER BY ordem, id", ['mid' => $mod['id']]);
            foreach ($mod['aulas'] as $a) {
                $todasAulas[] = $a;
            }
        }
        unset($mod);
        $aluno_id = $this->getAlunoId();
        $prog = $this->db->fetch("SELECT aulas_vistas, concluido_em FROM minicursos_progresso WHERE aluno_id = :aid AND minicurso_id = :mid", ['aid' => $aluno_id, 'mid' => $id]);
        $aulas_vistas = $prog ? (json_decode($prog['aulas_vistas'] ?? '[]', true) ?: []) : [];
        $concluido = !empty($prog['concluido_em']);
        $data = [
            'title' => $minicurso['titulo'] . ' - Minicurso',
            'user' => $this->auth->getUser(),
            'minicurso' => $minicurso,
            'arquivos' => $arquivos ?? [],
            'modulos' => $modulos,
            'todas_aulas' => $todasAulas,
            'aulas_vistas' => $aulas_vistas,
            'concluido' => $concluido,
            'current_page' => 'minicursos'
        ];
        $this->viewWithLayout('student', 'student/minicursos/show', $data);
    }

    public function aula($id)
    {
        $this->checkModuleMinicursos();
        $id = (int)$id;
        $aula = $this->db->fetch(
            "SELECT ma.*, mm.minicurso_id, mm.titulo as modulo_titulo, mc.titulo as minicurso_titulo
             FROM minicursos_aulas ma
             JOIN minicursos_modulos mm ON ma.modulo_id = mm.id
             JOIN minicursos mc ON mm.minicurso_id = mc.id
             WHERE ma.id = :id AND mc.ativo = 1",
            ['id' => $id]
        );
        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada.', 'error');
            $this->redirect('/minicursos');
            return;
        }
        $aluno_id = $this->getAlunoId();
        $this->marcarAulaVista($aluno_id, $aula['minicurso_id'], $id);
        $url = $aula['url_ou_caminho'];
        if ($aula['tipo'] !== 'link' && $url && !preg_match('#^https?://#i', $url)) {
            $url = rtrim(URL, '/') . '/' . ltrim($url, '/');
        }
        $data = [
            'title' => $aula['titulo'] . ' - Minicurso',
            'user' => $this->auth->getUser(),
            'aula' => $aula,
            'url_exibir' => $url,
            'current_page' => 'minicursos'
        ];
        $this->viewWithLayout('student', 'student/minicursos/aula', $data);
    }

    private function marcarAulaVista($aluno_id, $minicurso_id, $aula_id)
    {
        $prog = $this->db->fetch("SELECT aulas_vistas FROM minicursos_progresso WHERE aluno_id = :aid AND minicurso_id = :mid", ['aid' => $aluno_id, 'mid' => $minicurso_id]);
        $vistas = $prog ? (json_decode($prog['aulas_vistas'] ?? '[]', true) ?: []) : [];
        if (!in_array($aula_id, $vistas)) {
            $vistas[] = $aula_id;
        }
        $totalAulas = $this->db->fetch(
            "SELECT COUNT(*) as c FROM minicursos_aulas ma JOIN minicursos_modulos mm ON ma.modulo_id = mm.id WHERE mm.minicurso_id = :mid",
            ['mid' => $minicurso_id]
        )['c'];
        $concluido_em = (count($vistas) >= (int)$totalAulas) ? date('Y-m-d H:i:s') : null;
        if ($prog) {
            $this->db->query(
                "UPDATE minicursos_progresso SET aulas_vistas = :vistas, concluido_em = :concluido, updated_at = NOW() WHERE aluno_id = :aid AND minicurso_id = :mid",
                ['vistas' => json_encode($vistas), 'concluido' => $concluido_em, 'aid' => $aluno_id, 'mid' => $minicurso_id]
            );
        } else {
            $this->db->insert(
                "INSERT INTO minicursos_progresso (aluno_id, minicurso_id, aulas_vistas, concluido_em) VALUES (:aid, :mid, :vistas, :concluido)",
                ['aid' => $aluno_id, 'mid' => $minicurso_id, 'vistas' => json_encode($vistas), 'concluido' => $concluido_em]
            );
        }
    }

    public function marcarVista()
    {
        $aluno_id = $this->getAlunoId();
        $aula_id = (int)($_POST['aula_id'] ?? 0);
        $aula = $this->db->fetch("SELECT ma.id, mm.minicurso_id FROM minicursos_aulas ma JOIN minicursos_modulos mm ON ma.modulo_id = mm.id JOIN minicursos mc ON mm.minicurso_id = mc.id WHERE ma.id = :id AND mc.ativo = 1", ['id' => $aula_id]);
        if (!$aula) {
            $this->json(['error' => 'Aula não encontrada'], 404);
            return;
        }
        $this->marcarAulaVista($aluno_id, $aula['minicurso_id'], $aula_id);
        $this->json(['success' => true]);
    }

}
}
