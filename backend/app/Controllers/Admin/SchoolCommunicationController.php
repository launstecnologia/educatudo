<?php

require_once __DIR__ . '/../../Services/SchoolCommunicationService.php';

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
        $items = $this->db->fetchAll(
            "SELECT c.*,
                (SELECT COUNT(*) FROM school_communication_reads r WHERE r.communication_id=c.id) read_count,
                (SELECT COUNT(*) FROM school_communication_replies rp WHERE rp.communication_id=c.id) reply_count,
                (SELECT COUNT(*) FROM school_communication_attachments a WHERE a.communication_id=c.id) attachment_count
             FROM school_communications c ORDER BY c.created_at DESC LIMIT 200"
        );
        $this->viewWithLayout('admin', 'admin/school-communication/index', [
            'title' => 'Comunicação Escolar', 'page_title' => 'Comunicação Escolar', 'user' => $this->auth->getUser(),
            'items' => $items, 'current_page' => 'school-communication', 'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function create(): void
    {
        $this->viewWithLayout('admin', 'admin/school-communication/form', [
            'title' => 'Nova comunicação', 'page_title' => 'Nova comunicação', 'user' => $this->auth->getUser(),
            'classes' => $this->classes(), 'students' => $this->students(), 'current_page' => 'school-communication',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
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
        if ($title === '' || $content === '') $this->back('Título e conteúdo são obrigatórios.');
        if (!in_array($audience, ['todos','turmas','alunos'], true)) $audience = 'todos';
        if ($audience === 'turmas' && $classIds === []) $this->back('Selecione ao menos uma turma.');
        if ($audience === 'alunos' && $studentIds === []) $this->back('Selecione ao menos um aluno.');
        if (!in_array($priority, ['normal','importante','urgente'], true)) $priority = 'normal';
        $user = $this->auth->getUser();
        $id = (int)$this->db->insert(
            "INSERT INTO school_communications (titulo, conteudo, prioridade, permite_resposta, publico, autor_tipo, autor_id, status, published_at, expires_at)
             VALUES (:title,:content,:priority,:replies,:audience,'admin',:author,'publicado',NOW(),:expires)",
            ['title' => $title, 'content' => $content, 'priority' => $priority, 'replies' => isset($_POST['permite_resposta']) ? 1 : 0,
             'audience' => $audience, 'author' => (int)$user['id'], 'expires' => $this->nullableDateTime($_POST['expires_at'] ?? null)]
        );
        foreach ($classIds as $classId) $this->db->insert('INSERT INTO school_communication_classes (communication_id,turma_id) VALUES (:id,:target)', ['id'=>$id,'target'=>$classId]);
        foreach ($studentIds as $studentId) $this->db->insert('INSERT INTO school_communication_students (communication_id,aluno_id) VALUES (:id,:target)', ['id'=>$id,'target'=>$studentId]);
        $this->service->saveAttachments($id, $_FILES['attachments'] ?? []);
        $parents = $this->service->parentIds($audience, $classIds, $studentIds);
        $this->service->push($parents, $title, $this->plainSummary($content), '/school-communications/' . $id, [
            'type'=>'school_communication', 'communication_id'=>(string)$id,
        ], (int)$user['id']);
        $this->setFlashMessage('Comunicação publicada e enviada aos responsáveis.', 'success');
        $this->redirect(URL . '/admin/comunicacao-escolar/' . $id);
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
        $events = $this->db->fetchAll('SELECT * FROM school_calendar_events ORDER BY inicio_em DESC LIMIT 300');
        $this->viewWithLayout('admin', 'admin/school-calendar/index', [
            'title'=>'Calendário escolar','page_title'=>'Calendário escolar','user'=>$this->auth->getUser(),'events'=>$events,
            'current_page'=>'school-calendar','csrf_token'=>$this->generateCsrfToken(),
        ]);
    }

    public function calendarEdit($id): void
    {
        $event=$this->db->fetch('SELECT * FROM school_calendar_events WHERE id=:id',['id'=>(int)$id]);
        if(!$event)$this->redirect(URL.'/admin/calendario-escolar');
        $selectedClasses=array_map('intval',array_column($this->db->fetchAll('SELECT turma_id FROM school_calendar_event_classes WHERE event_id=:id',['id'=>(int)$id]),'turma_id'));
        $selectedStudents=array_map('intval',array_column($this->db->fetchAll('SELECT aluno_id FROM school_calendar_event_students WHERE event_id=:id',['id'=>(int)$id]),'aluno_id'));
        $this->viewWithLayout('admin','admin/school-calendar/form',[
            'title'=>'Editar evento','page_title'=>'Editar evento','user'=>$this->auth->getUser(),'classes'=>$this->classes(),'students'=>$this->students(),
            'event'=>$event,'selectedClasses'=>$selectedClasses,'selectedStudents'=>$selectedStudents,'current_page'=>'school-calendar','csrf_token'=>$this->generateCsrfToken(),
        ]);
    }

    public function calendarCreate(): void
    {
        $this->viewWithLayout('admin', 'admin/school-calendar/form', [
            'title'=>'Novo evento','page_title'=>'Novo evento','user'=>$this->auth->getUser(),'classes'=>$this->classes(),'students'=>$this->students(),
            'event'=>null, 'selectedClasses'=>[], 'selectedStudents'=>[], 'current_page'=>'school-calendar','csrf_token'=>$this->generateCsrfToken(),
        ]);
    }

    public function calendarStore(): void
    {
        $this->csrf();
        $title = trim((string)($_POST['titulo'] ?? ''));
        $starts = $this->nullableDateTime($_POST['inicio_em'] ?? null);
        $audience = (string)($_POST['publico'] ?? 'todos');
        $classIds = $this->ids($_POST['turmas'] ?? []);
        $studentIds = $this->ids($_POST['alunos'] ?? []);
        if ($title === '' || $starts === null) $this->back('Título e início são obrigatórios.');
        if (!in_array($audience, ['todos','turmas','alunos'], true)) $audience='todos';
        if ($audience==='turmas' && $classIds===[]) $this->back('Selecione ao menos uma turma.');
        if ($audience==='alunos' && $studentIds===[]) $this->back('Selecione ao menos um aluno.');
        $user=$this->auth->getUser();
        $id=(int)$this->db->insert(
            "INSERT INTO school_calendar_events (titulo,descricao,categoria,prioridade,local,inicio_em,fim_em,dia_inteiro,publico,status,criado_por,published_at)
             VALUES (:title,:description,:category,:priority,:location,:starts,:ends,:all_day,:audience,'publicado',:author,NOW())",
            ['title'=>$title,'description'=>trim((string)($_POST['descricao']??'')),'category'=>trim((string)($_POST['categoria']??'evento')) ?: 'evento',
             'priority'=>in_array($_POST['prioridade']??'', ['normal','importante','urgente'],true)?$_POST['prioridade']:'normal','location'=>trim((string)($_POST['local']??'')) ?: null,
             'starts'=>$starts,'ends'=>$this->nullableDateTime($_POST['fim_em']??null),'all_day'=>isset($_POST['dia_inteiro'])?1:0,'audience'=>$audience,'author'=>(int)$user['id']]
        );
        foreach($classIds as $target)$this->db->insert('INSERT INTO school_calendar_event_classes (event_id,turma_id) VALUES (:id,:target)',['id'=>$id,'target'=>$target]);
        foreach($studentIds as $target)$this->db->insert('INSERT INTO school_calendar_event_students (event_id,aluno_id) VALUES (:id,:target)',['id'=>$id,'target'=>$target]);
        $parents=$this->service->parentIds($audience,$classIds,$studentIds);
        $this->service->push($parents,'Novo evento: '.$title,date('d/m/Y H:i',strtotime($starts)), '/calendar-events/'.$id, ['type'=>'calendar_event','event_id'=>(string)$id], (int)$user['id']);
        $this->setFlashMessage('Evento publicado e responsáveis notificados.','success');
        $this->redirect(URL.'/admin/calendario-escolar');
    }

    public function calendarCancel($id): void
    {
        $this->csrf();
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
        $this->csrf();
        $event=$this->db->fetch('SELECT * FROM school_calendar_events WHERE id=:id',['id'=>(int)$id]);
        if(!$event)$this->redirect(URL.'/admin/calendario-escolar');
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

    private function classes(): array { return $this->db->fetchAll('SELECT id,nome FROM turmas ORDER BY nome'); }
    private function students(): array { return $this->db->fetchAll('SELECT a.id,a.nome,t.nome turma_nome FROM alunos a LEFT JOIN turmas t ON t.id=a.turma_id WHERE a.ativo=1 ORDER BY a.nome'); }
    private function ids($values): array { return array_values(array_unique(array_filter(array_map('intval', is_array($values)?$values:[])))); }
    private function nullableDateTime($value): ?string { $value=trim((string)$value); if($value==='')return null; $time=strtotime($value); return $time===false?null:date('Y-m-d H:i:s',$time); }
    private function plainSummary(string $html): string { $text=trim(preg_replace('/\s+/u',' ',strip_tags($html))); return mb_strlen($text)>160?mb_substr($text,0,157).'...':$text; }
    private function csrf(): void { if(!$this->verifyCsrfToken($_POST['_token']??'')){ $this->setFlashMessage('Sessão expirada. Tente novamente.','error'); $this->redirect(URL.'/admin/comunicacao-escolar'); } }
    private function back(string $message): void { $this->setFlashMessage($message,'error'); $this->redirect($_SERVER['HTTP_REFERER']??(URL.'/admin/comunicacao-escolar')); }
}
