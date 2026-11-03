<?php

namespace App\Modulos\Comunicacao\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Core\LayoutHelper;
use App\Modulos\Comunicacao\Services\SchoolCommunicationService;

/**
 * Comunicação escolar + calendário escolar (admin).
 */
class SchoolCommunicationController extends BaseController
{
    private $db;
    private AuthManager $auth;
    private SchoolCommunicationService $service;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->auth = new AuthManager();
        if (!$this->auth->isLoggedIn()) $this->redirect('/');
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'] ?? '', ['admin','admin_escola'], true)) $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        $this->service = new SchoolCommunicationService($this->db);
    }

    public function index(): void
    {
        $flash = $this->getFlashMessage();
        $items = $this->db->fetchAll(
            "SELECT c.*,
                (SELECT COUNT(*) FROM school_communication_reads r WHERE r.communication_id=c.id) read_count,
                (SELECT COUNT(*) FROM school_communication_replies rp WHERE rp.communication_id=c.id) reply_count,
                (SELECT COUNT(*) FROM school_communication_attachments a WHERE a.communication_id=c.id) attachment_count
             FROM school_communications c ORDER BY c.created_at DESC LIMIT 200"
        );
        $this->viewWithLayout('admin', 'admin/school-communication/index', [
            'title' => 'Comunicação Escolar', 'page_title' => 'Comunicação Escolar', 'user' => $this->auth->getUser(),
            'items' => $items, 'classes' => $this->classes(), 'students' => $this->students(),
            'series' => $this->series(), 'cursos' => $this->cursos(),
            'professor_liberado' => LayoutHelper::get('module_professor_comunicacao_pais', '0') === '1',
            'current_page' => 'school-communication', 'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'], 'flash_type' => $flash['type'],
        ]);
    }

    public function create(): void
    {
        $this->redirect(URL . '/admin/comunicacao-escolar?novo=1');
    }

    public function store(): void
    {
        $this->csrf();
        $title = trim((string)($_POST['titulo'] ?? ''));
        $content = trim((string)($_POST['conteudo'] ?? ''));
        $audience = (string)($_POST['publico'] ?? 'todos');
        $priority = (string)($_POST['prioridade'] ?? 'normal');
        $classIds = $this->ids($_POST['turmas'] ?? []);
        $studentIds = $this->ids($_POST['alunos'] ?? []);
        $serieIds = $this->ids($_POST['series'] ?? []);
        $cursoIds = $this->ids($_POST['cursos'] ?? []);
        $video = $this->youtube($_POST['video_url'] ?? '');
        if ($title === '' || $content === '') {
            $this->setFlashMessage('Título e conteúdo são obrigatórios.', 'error');
            $this->redirect(URL . '/admin/comunicacao-escolar?novo=1');
        }
        if ($video === false) {
            $this->setFlashMessage('Informe um link do YouTube válido ou deixe o campo vazio.', 'error');
            $this->redirect(URL . '/admin/comunicacao-escolar?novo=1');
        }
        if (!in_array($audience, ['todos','turmas','alunos','series','cursos'], true)) $audience = 'todos';
        if ($audience === 'turmas' && $classIds === []) {
            $this->setFlashMessage('Selecione ao menos uma turma.', 'error');
            $this->redirect(URL . '/admin/comunicacao-escolar?novo=1');
        }
        if ($audience === 'alunos' && $studentIds === []) {
            $this->setFlashMessage('Selecione ao menos um aluno.', 'error');
            $this->redirect(URL . '/admin/comunicacao-escolar?novo=1');
        }
        if ($audience === 'series' && $serieIds === []) {
            $this->setFlashMessage('Selecione ao menos uma série.', 'error');
            $this->redirect(URL . '/admin/comunicacao-escolar?novo=1');
        }
        if ($audience === 'cursos' && $cursoIds === []) {
            $this->setFlashMessage('Selecione ao menos um curso.', 'error');
            $this->redirect(URL . '/admin/comunicacao-escolar?novo=1');
        }
        if (!in_array($priority, ['normal','importante','urgente'], true)) $priority = 'normal';
        $enviarPush = isset($_POST['enviar_push']) ? 1 : 0;
        $user = $this->auth->getUser();
        $id = (int)$this->db->insert(
            "INSERT INTO school_communications (titulo, conteudo, video_url, prioridade, permite_resposta, enviar_push, publico, autor_tipo, autor_id, status, published_at, expires_at)
             VALUES (:title,:content,:video,:priority,:replies,:push,:audience,'admin',:author,'publicado',NOW(),:expires)",
            ['title' => $title, 'content' => $content, 'video' => $video, 'priority' => $priority, 'replies' => isset($_POST['permite_resposta']) ? 1 : 0,
             'push' => $enviarPush, 'audience' => $audience, 'author' => (int)$user['id'], 'expires' => $this->nullableDateTime($_POST['expires_at'] ?? null)]
        );
        if ($audience === 'turmas') {
            foreach ($classIds as $classId) $this->db->insert('INSERT INTO school_communication_classes (communication_id,turma_id) VALUES (:id,:target)', ['id'=>$id,'target'=>$classId]);
        } elseif ($audience === 'alunos') {
            foreach ($studentIds as $studentId) $this->db->insert('INSERT INTO school_communication_students (communication_id,aluno_id) VALUES (:id,:target)', ['id'=>$id,'target'=>$studentId]);
        } elseif ($audience === 'series') {
            foreach ($serieIds as $serieId) $this->db->insert('INSERT INTO school_communication_series (communication_id,serie_id) VALUES (:id,:target)', ['id'=>$id,'target'=>$serieId]);
        } elseif ($audience === 'cursos') {
            foreach ($cursoIds as $cursoId) $this->db->insert('INSERT INTO school_communication_cursos (communication_id,curso_id) VALUES (:id,:target)', ['id'=>$id,'target'=>$cursoId]);
        }
        $this->service->saveAttachments($id, $_FILES['attachments'] ?? []);
        if ($enviarPush === 1) {
            $parents = $this->service->parentIds($audience, $classIds, $studentIds, $serieIds, $cursoIds);
            $this->service->push($parents, $title, $this->plainSummary($content), '/school-communications/' . $id, [
                'type'=>'school_communication', 'communication_id'=>(string)$id,
            ], (int)$user['id']);
        }
        $this->setFlashMessage($enviarPush === 1 ? 'Comunicação publicada e enviada por push aos responsáveis.' : 'Comunicação publicada para os responsáveis.', 'success');
        $this->redirect(URL . '/admin/comunicacao-escolar/' . $id);
    }

    public function liberarProfessor(): void
    {
        $this->csrf();
        $valor = isset($_POST['professor_comunicacao_pais']) ? '1' : '0';
        $this->db->query(
            "INSERT INTO config_layout (config_key, config_value) VALUES ('module_professor_comunicacao_pais', :valor)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)",
            ['valor' => $valor]
        );
        LayoutHelper::invalidateCache();
        $this->setFlashMessage($valor === '1' ? 'Professores podem enviar recados aos pais.' : 'Envio do professor aos pais desligado.', 'success');
        $this->redirect(URL . '/admin/comunicacao-escolar');
    }

    public function show($id): void
    {
        $item = $this->db->fetch('SELECT * FROM school_communications WHERE id=:id', ['id'=>(int)$id]);
        if (!$item) $this->redirect(URL . '/admin/comunicacao-escolar');
        $attachments = $this->db->fetchAll('SELECT * FROM school_communication_attachments WHERE communication_id=:id ORDER BY id', ['id'=>(int)$id]);
        $reads = $this->db->fetchAll(
            'SELECT r.*, p.nome responsavel_nome, a.nome aluno_nome FROM school_communication_reads r INNER JOIN responsaveis p ON p.id=r.responsavel_id INNER JOIN alunos a ON a.id=r.aluno_id WHERE r.communication_id=:id ORDER BY r.lido_em DESC', ['id'=>(int)$id]
        );
        $replies = $this->db->fetchAll(
            "SELECT rp.*, p.nome responsavel_nome, a.nome aluno_nome FROM school_communication_replies rp
             INNER JOIN responsaveis p ON p.id=rp.responsavel_id INNER JOIN alunos a ON a.id=rp.aluno_id
             WHERE rp.communication_id=:id ORDER BY rp.created_at", ['id'=>(int)$id]
        );
        $this->viewWithLayout('admin', 'admin/school-communication/show', [
            'title'=>$item['titulo'], 'page_title'=>'Detalhes da comunicação', 'user'=>$this->auth->getUser(), 'item'=>$item,
            'attachments'=>$attachments, 'reads'=>$reads, 'replies'=>$replies, 'current_page'=>'school-communication', 'csrf_token'=>$this->generateCsrfToken(),
        ]);
    }

    public function reply($id): void
    {
        $this->csrf();
        $reply = $this->db->fetch('SELECT * FROM school_communication_replies WHERE id=:reply AND communication_id=:communication', ['reply'=>(int)($_POST['reply_id']??0),'communication'=>(int)$id]);
        $message = trim((string)($_POST['mensagem'] ?? ''));
        if (!$reply || $message === '') $this->back('Resposta inválida.');
        $user = $this->auth->getUser();
        $this->db->insert(
            "INSERT INTO school_communication_replies (communication_id,responsavel_id,aluno_id,sender_type,sender_id,mensagem) VALUES (:communication,:parent,:student,'admin',:sender,:message)",
            ['communication'=>(int)$id,'parent'=>(int)$reply['responsavel_id'],'student'=>(int)$reply['aluno_id'],'sender'=>(int)$user['id'],'message'=>$message]
        );
        $this->service->push([(int)$reply['responsavel_id']], 'Nova resposta da escola', $message, '/school-communications/' . (int)$id, ['type'=>'school_communication','communication_id'=>(string)(int)$id], (int)$user['id']);
        $this->setFlashMessage('Resposta enviada.', 'success');
        $this->redirect(URL . '/admin/comunicacao-escolar/' . (int)$id);
    }

    public function calendar(): void
    {
        if ((string) ($_GET['novo'] ?? '') === '1') {
            $this->setFlashMessage('Cadastre o aviso no Calendário Letivo, na opção Avisar a família.', 'success');
            $this->redirect(URL . '/admin/calendario-letivo');
        }
        $temVinculo = $this->colunaEventoEscolar();
        if ($temVinculo) {
            $pedido = (int) ($_GET['evento'] ?? 0);
            if ($pedido > 0) {
                $ligado = $this->db->fetch(
                    'SELECT l.id, l.calendario_id, c.ano
                     FROM calendario_letivo_eventos l
                     INNER JOIN calendario_letivo c ON c.id = l.calendario_id
                     WHERE l.evento_escolar_id = :id
                     LIMIT 1',
                    ['id' => $pedido]
                );
                if ($ligado) {
                    $this->redirect(URL . '/admin/calendario-letivo?ano=' . (int) $ligado['ano'] . '&id=' . (int) $ligado['calendario_id'] . '&evento=' . (int) $ligado['id']);
                }
            }
            $events = $this->db->fetchAll(
                'SELECT e.*, l.id AS letivo_id, l.calendario_id AS letivo_calendario_id, c.ano AS letivo_ano
                 FROM school_calendar_events e
                 LEFT JOIN calendario_letivo_eventos l ON l.evento_escolar_id = e.id
                 LEFT JOIN calendario_letivo c ON c.id = l.calendario_id
                 ORDER BY e.inicio_em DESC
                 LIMIT 300'
            );
        } else {
            $events = $this->db->fetchAll('SELECT * FROM school_calendar_events ORDER BY inicio_em DESC LIMIT 300');
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/school-calendar/index', [
            'title' => 'Avisos à família', 'page_title' => 'Avisos à família', 'user' => $this->auth->getUser(), 'events' => $events,
            'classes' => $this->classes(), 'students' => $this->students(),
            'current_page' => 'school-calendar', 'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'], 'flash_type' => $flash['type'],
        ]);
    }

    public function calendarEdit($id): void
    {
        $this->redirect(URL.'/admin/calendario-escolar?evento='.(int)$id);
    }

    public function calendarCreate(): void
    {
        $this->setFlashMessage('Cadastre o aviso no Calendário Letivo, na opção Avisar a família.', 'success');
        $this->redirect(URL . '/admin/calendario-letivo');
    }

    public function calendarDados($id): void
    {
        $event = $this->db->fetch('SELECT * FROM school_calendar_events WHERE id=:id', ['id'=>(int)$id]);
        if (!$event) {
            $this->json(['success'=>false,'error'=>'Evento não encontrado.'], 404);
            return;
        }
        $turmas = array_map('intval', array_column($this->db->fetchAll('SELECT turma_id FROM school_calendar_event_classes WHERE event_id=:id', ['id'=>(int)$id]), 'turma_id'));
        $alunos = array_map('intval', array_column($this->db->fetchAll('SELECT aluno_id FROM school_calendar_event_students WHERE event_id=:id', ['id'=>(int)$id]), 'aluno_id'));
        $this->json([
            'success' => true,
            'item' => [
                'id' => (int) $event['id'],
                'titulo' => (string) ($event['titulo'] ?? ''),
                'descricao' => (string) ($event['descricao'] ?? ''),
                'categoria' => (string) ($event['categoria'] ?? 'evento'),
                'prioridade' => (string) ($event['prioridade'] ?? 'normal'),
                'local' => (string) ($event['local'] ?? ''),
                'inicio_em' => $this->datetimeLocalValue($event['inicio_em'] ?? null),
                'fim_em' => $this->datetimeLocalValue($event['fim_em'] ?? null),
                'dia_inteiro' => !empty($event['dia_inteiro']) ? 1 : 0,
                'publico' => (string) ($event['publico'] ?? 'todos'),
                'turmas' => $turmas,
                'alunos' => $alunos,
            ],
        ]);
    }

    public function calendarStore(): void
    {
        $this->csrf(URL.'/admin/calendario-escolar');
        $this->setFlashMessage('Cadastre o aviso no Calendário Letivo, na opção Avisar a família.', 'success');
        $this->redirect(URL . '/admin/calendario-letivo');
    }

    public function calendarCancel($id): void
    {
        $this->csrf(URL.'/admin/calendario-escolar');
        $event=$this->db->fetch('SELECT * FROM school_calendar_events WHERE id=:id',['id'=>(int)$id]);
        if(!$event)$this->redirect(URL.'/admin/calendario-escolar');
        $this->db->update("UPDATE school_calendar_events SET status='cancelado' WHERE id=:id",['id'=>(int)$id]);
        $classIds=array_column($this->db->fetchAll('SELECT turma_id FROM school_calendar_event_classes WHERE event_id=:id',['id'=>(int)$id]),'turma_id');
        $studentIds=array_column($this->db->fetchAll('SELECT aluno_id FROM school_calendar_event_students WHERE event_id=:id',['id'=>(int)$id]),'aluno_id');
        $parents=$this->service->parentIds($event['publico'],$classIds,$studentIds);
        $user=$this->auth->getUser();
        $this->service->push($parents,'Evento cancelado: '.$event['titulo'],'Consulte o calendário escolar.','/calendar-events/'.(int)$id,['type'=>'calendar_event','event_id'=>(string)(int)$id],(int)$user['id']);
        $this->setFlashMessage('Evento cancelado e responsáveis notificados.','success');
        $this->redirect(URL.'/admin/calendario-escolar');
    }

    public function calendarUpdate($id): void
    {
        $this->csrf(URL.'/admin/calendario-escolar');
        $event=$this->db->fetch('SELECT * FROM school_calendar_events WHERE id=:id',['id'=>(int)$id]);
        if(!$event)$this->redirect(URL.'/admin/calendario-escolar');
        if ($this->colunaEventoEscolar()) {
            $ligado = $this->db->fetch(
                'SELECT l.id, l.calendario_id, c.ano
                 FROM calendario_letivo_eventos l
                 INNER JOIN calendario_letivo c ON c.id = l.calendario_id
                 WHERE l.evento_escolar_id = :id
                 LIMIT 1',
                ['id' => (int) $id]
            );
            if ($ligado) {
                $this->setFlashMessage('Edite este aviso no Calendário Letivo.', 'success');
                $this->redirect(URL . '/admin/calendario-letivo?ano=' . (int) $ligado['ano'] . '&id=' . (int) $ligado['calendario_id'] . '&evento=' . (int) $ligado['id']);
            }
        }
        $title=trim((string)($_POST['titulo']??'')); $starts=$this->nullableDateTime($_POST['inicio_em']??null); $audience=(string)($_POST['publico']??'todos');
        $classIds=$this->ids($_POST['turmas']??[]); $studentIds=$this->ids($_POST['alunos']??[]);
        if($title===''||$starts===null)$this->back('Título e início são obrigatórios.');
        if(!in_array($audience,['todos','turmas','alunos'],true))$audience='todos';
        if($audience==='turmas'&&$classIds===[])$this->back('Selecione ao menos uma turma.');
        if($audience==='alunos'&&$studentIds===[])$this->back('Selecione ao menos um aluno.');
        $priority=in_array($_POST['prioridade']??'', ['normal','importante','urgente'],true)?$_POST['prioridade']:'normal';
        $this->db->update("UPDATE school_calendar_events SET titulo=:title,descricao=:description,categoria=:category,prioridade=:priority,local=:location,inicio_em=:starts,fim_em=:ends,dia_inteiro=:all_day,publico=:audience,status='publicado' WHERE id=:id",[
            'title'=>$title,'description'=>trim((string)($_POST['descricao']??'')),'category'=>trim((string)($_POST['categoria']??'evento'))?:'evento','priority'=>$priority,
            'location'=>trim((string)($_POST['local']??''))?:null,'starts'=>$starts,'ends'=>$this->nullableDateTime($_POST['fim_em']??null),'all_day'=>isset($_POST['dia_inteiro'])?1:0,'audience'=>$audience,'id'=>(int)$id,
        ]);
        $this->db->query('DELETE FROM school_calendar_event_classes WHERE event_id=:id',['id'=>(int)$id]);
        $this->db->query('DELETE FROM school_calendar_event_students WHERE event_id=:id',['id'=>(int)$id]);
        foreach($classIds as $target)$this->db->insert('INSERT INTO school_calendar_event_classes(event_id,turma_id) VALUES(:id,:target)',['id'=>(int)$id,'target'=>$target]);
        foreach($studentIds as $target)$this->db->insert('INSERT INTO school_calendar_event_students(event_id,aluno_id) VALUES(:id,:target)',['id'=>(int)$id,'target'=>$target]);
        $parents=$this->service->parentIds($audience,$classIds,$studentIds); $user=$this->auth->getUser();
        $this->service->push($parents,'Evento atualizado: '.$title,date('d/m/Y H:i',strtotime($starts)),'/calendar-events/'.(int)$id,['type'=>'calendar_event','event_id'=>(string)(int)$id],(int)$user['id']);
        $this->setFlashMessage('Evento atualizado e responsáveis notificados.','success');
        $this->redirect(URL.'/admin/calendario-escolar');
    }

    private function colunaEventoEscolar(): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'calendario_letivo_eventos'
                   AND COLUMN_NAME = 'evento_escolar_id'
                 LIMIT 1"
            );
            return $row !== false && !empty($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function classes(): array { return $this->db->fetchAll('SELECT id,nome FROM turmas ORDER BY nome'); }
    private function series(): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT s.id, s.nome, c.nome AS curso_nome
                 FROM serie s INNER JOIN curso c ON c.id = s.curso_id
                 WHERE s.ativo = 1 ORDER BY c.nome, s.ordem, s.nome'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
    private function cursos(): array
    {
        try {
            return $this->db->fetchAll('SELECT id, nome FROM curso WHERE ativo = 1 ORDER BY ordem, nome');
        } catch (\Throwable $e) {
            return [];
        }
    }
    /** @return string|null|false */
    private function youtube($value)
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://(www\.)?(youtube\.com|youtu\.be)/#i', $url)) {
            return false;
        }
        return mb_substr($url, 0, 500);
    }
    private function students(): array { return $this->db->fetchAll('SELECT a.id,a.nome,t.nome turma_nome FROM alunos a LEFT JOIN turmas t ON t.id=a.turma_id WHERE a.ativo=1 ORDER BY a.nome'); }
    private function ids($values): array { return array_values(array_unique(array_filter(array_map('intval', is_array($values)?$values:[])))); }
    private function nullableDateTime($value): ?string { $value=trim((string)$value); if($value==='')return null; $time=strtotime($value); return $time===false?null:date('Y-m-d H:i:s',$time); }
    private function plainSummary(string $html): string { $text=trim(preg_replace('/\s+/u',' ',strip_tags($html))); return mb_strlen($text)>160?mb_substr($text,0,157).'...':$text; }
    private function datetimeLocalValue($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $time = strtotime($value);
        return $time === false ? '' : date('Y-m-d\TH:i', $time);
    }
    private function csrf(?string $fallback = null): void { if(!$this->verifyCsrfToken($_POST['_token']??'')){ $this->setFlashMessage('Sessão expirada. Tente novamente.','error'); $this->redirect($fallback ?? (URL.'/admin/comunicacao-escolar')); } }
    private function back(string $message): void { $this->setFlashMessage($message,'error'); $this->redirect($_SERVER['HTTP_REFERER']??(URL.'/admin/comunicacao-escolar')); }
}

if (!class_exists('SchoolCommunicationController', false) && class_exists(__NAMESPACE__ . '\\SchoolCommunicationController', false)) {
    class_alias(__NAMESPACE__ . '\\SchoolCommunicationController', 'SchoolCommunicationController');
}
