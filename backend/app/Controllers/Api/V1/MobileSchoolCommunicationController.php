<?php

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../../../Middleware/MobileApiAuthMiddleware.php';
require_once __DIR__ . '/../../../Policies/ParentStudentAccessPolicy.php';

class MobileSchoolCommunicationController extends BaseController
{
    private $db;
    private ParentStudentAccessPolicy $policy;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->policy = new ParentStudentAccessPolicy();
    }

    public function index($studentId): void
    {
        $student=$this->student($studentId);
        $rows=$this->db->fetchAll(
            "SELECT c.*, rd.lido_em,
                (SELECT COUNT(*) FROM school_communication_replies rp WHERE rp.communication_id=c.id AND rp.responsavel_id=:reply_parent AND rp.aluno_id=:reply_student) reply_count
             FROM school_communications c
             LEFT JOIN school_communication_reads rd ON rd.communication_id=c.id AND rd.responsavel_id=:read_parent AND rd.aluno_id=:read_student
             WHERE c.status='publicado' AND c.published_at<=NOW() AND (c.expires_at IS NULL OR c.expires_at>=NOW())
               AND (c.publico='todos'
                    OR (c.publico='turmas' AND EXISTS(SELECT 1 FROM school_communication_classes cc WHERE cc.communication_id=c.id AND cc.turma_id=:class_id))
                    OR (c.publico='alunos' AND EXISTS(SELECT 1 FROM school_communication_students cs WHERE cs.communication_id=c.id AND cs.aluno_id=:student_id)))
             ORDER BY FIELD(c.prioridade,'urgente','importante','normal'), c.published_at DESC",
            ['reply_parent'=>$this->parentId(),'reply_student'=>$student['id'],'read_parent'=>$this->parentId(),'read_student'=>$student['id'],'class_id'=>$student['turma_id'],'student_id'=>$student['id']]
        );
        $data=array_map(fn($row)=>$this->communication($row,false),$rows);
        $this->json(['data'=>$data,'meta'=>['count'=>count($data),'unread_count'=>count(array_filter($data,fn($i)=>!$i['is_read']))]]);
    }

    public function show($studentId,$id): void
    {
        $student=$this->student($studentId);
        $item=$this->communicationFor($student,$id);
        $attachments=$this->db->fetchAll('SELECT id,arquivo,arquivo_nome,tipo_arquivo,tamanho FROM school_communication_attachments WHERE communication_id=:id ORDER BY id',['id'=>$item['id']]);
        $replies=$this->db->fetchAll(
            'SELECT id,sender_type,mensagem,lido_em,created_at FROM school_communication_replies WHERE communication_id=:communication AND responsavel_id=:parent AND aluno_id=:student ORDER BY id',
            ['communication'=>$item['id'],'parent'=>$this->parentId(),'student'=>$student['id']]
        );
        $data=$this->communication($item,true);
        $data['attachments']=array_map(fn($a)=>['id'=>(int)$a['id'],'url'=>$this->absoluteUrl($a['arquivo']),'name'=>$a['arquivo_nome'],'mime_type'=>$a['tipo_arquivo'],'size'=>$a['tamanho']!==null?(int)$a['tamanho']:null],$attachments);
        $data['replies']=array_map(fn($r)=>['id'=>(int)$r['id'],'sender_type'=>$r['sender_type'],'message'=>$r['mensagem'],'read_at'=>$this->iso($r['lido_em']),'created_at'=>$this->iso($r['created_at'])],$replies);
        $this->json(['data'=>$data]);
    }

    public function read($studentId,$id): void
    {
        $student=$this->student($studentId);
        $item=$this->communicationFor($student,$id);
        $this->db->query('INSERT INTO school_communication_reads (communication_id,responsavel_id,aluno_id,lido_em) VALUES (:communication,:parent,:student,NOW()) ON DUPLICATE KEY UPDATE lido_em=COALESCE(lido_em,VALUES(lido_em))',['communication'=>$item['id'],'parent'=>$this->parentId(),'student'=>$student['id']]);
        $this->db->update("UPDATE school_communication_replies SET lido_em=COALESCE(lido_em,NOW()) WHERE communication_id=:communication AND responsavel_id=:parent AND aluno_id=:student AND sender_type<>'responsavel'",['communication'=>$item['id'],'parent'=>$this->parentId(),'student'=>$student['id']]);
        $this->json(['data'=>['is_read'=>true,'read_at'=>date(DATE_ATOM)]]);
    }

    public function reply($studentId,$id): void
    {
        $student=$this->student($studentId);
        $item=$this->communicationFor($student,$id);
        if(empty($item['permite_resposta']))$this->fail('replies_disabled','Esta comunicação não permite resposta.',422);
        $input=$this->input(); $message=trim((string)($input['message']??''));
        if($message===''||mb_strlen($message)>4000)$this->fail('invalid_message','Informe uma mensagem com até 4.000 caracteres.',422);
        $replyId=(int)$this->db->insert("INSERT INTO school_communication_replies (communication_id,responsavel_id,aluno_id,sender_type,sender_id,mensagem) VALUES (:communication,:parent,:student,'responsavel',:sender,:message)",['communication'=>$item['id'],'parent'=>$this->parentId(),'student'=>$student['id'],'sender'=>$this->parentId(),'message'=>$message]);
        $this->json(['data'=>['id'=>$replyId,'sender_type'=>'responsavel','message'=>$message,'created_at'=>date(DATE_ATOM)]],201);
    }

    public function calendar($studentId): void
    {
        $student=$this->student($studentId);
        $from=$this->date($_GET['from']??'',date('Y-m-01',strtotime('-1 month'))); $to=$this->date($_GET['to']??'',date('Y-m-t',strtotime('+6 months')));
        $rows=$this->db->fetchAll(
            "SELECT e.*,r.lido_em FROM school_calendar_events e LEFT JOIN school_calendar_event_reads r ON r.event_id=e.id AND r.responsavel_id=:parent AND r.aluno_id=:read_student
             WHERE e.status IN('publicado','cancelado') AND DATE(e.inicio_em) BETWEEN :date_from AND :date_to
               AND (e.publico='todos' OR (e.publico='turmas' AND EXISTS(SELECT 1 FROM school_calendar_event_classes ec WHERE ec.event_id=e.id AND ec.turma_id=:class_id)) OR (e.publico='alunos' AND EXISTS(SELECT 1 FROM school_calendar_event_students es WHERE es.event_id=e.id AND es.aluno_id=:student_id))) ORDER BY e.inicio_em",
            ['parent'=>$this->parentId(),'read_student'=>$student['id'],'date_from'=>$from,'date_to'=>$to,'class_id'=>$student['turma_id'],'student_id'=>$student['id']]
        );
        $data=array_map(fn($e)=>['id'=>(int)$e['id'],'title'=>$e['titulo'],'description'=>$e['descricao'],'category'=>$e['categoria'],'priority'=>$e['prioridade'],'location'=>$e['local'],'starts_at'=>$this->iso($e['inicio_em']),'ends_at'=>$this->iso($e['fim_em']),'all_day'=>!empty($e['dia_inteiro']),'status'=>$e['status'],'is_read'=>$e['lido_em']!==null,'read_at'=>$this->iso($e['lido_em'])],$rows);
        $this->json(['data'=>$data,'meta'=>['count'=>count($data),'from'=>$from,'to'=>$to]]);
    }

    public function calendarRead($studentId,$id): void
    {
        $student=$this->student($studentId); $event=$this->eventFor($student,$id);
        $this->db->query('INSERT INTO school_calendar_event_reads(event_id,responsavel_id,aluno_id,lido_em) VALUES(:event,:parent,:student,NOW()) ON DUPLICATE KEY UPDATE lido_em=COALESCE(lido_em,VALUES(lido_em))',['event'=>$event['id'],'parent'=>$this->parentId(),'student'=>$student['id']]);
        $this->json(['data'=>['is_read'=>true,'read_at'=>date(DATE_ATOM)]]);
    }

    private function student($raw): array { $id=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if($id===false||!$this->policy->canAccess($this->parentId(),(int)$id))$this->fail('student_not_found','Aluno não encontrado.',404); $row=$this->db->fetch('SELECT id,nome,turma_id FROM alunos WHERE id=:id',['id'=>(int)$id]); if(!$row)$this->fail('student_not_found','Aluno não encontrado.',404); return $row; }
    private function communicationFor(array $student,$raw): array { $row=$this->db->fetch("SELECT c.*,rd.lido_em FROM school_communications c LEFT JOIN school_communication_reads rd ON rd.communication_id=c.id AND rd.responsavel_id=:parent AND rd.aluno_id=:read_student WHERE c.id=:id AND c.status='publicado' AND (c.publico='todos' OR (c.publico='turmas' AND EXISTS(SELECT 1 FROM school_communication_classes cc WHERE cc.communication_id=c.id AND cc.turma_id=:class_id)) OR (c.publico='alunos' AND EXISTS(SELECT 1 FROM school_communication_students cs WHERE cs.communication_id=c.id AND cs.aluno_id=:student_id)))",['parent'=>$this->parentId(),'read_student'=>$student['id'],'id'=>(int)$raw,'class_id'=>$student['turma_id'],'student_id'=>$student['id']]); if(!$row)$this->fail('communication_not_found','Comunicação não encontrada.',404); return $row; }
    private function eventFor(array $student,$raw): array { $row=$this->db->fetch("SELECT e.id FROM school_calendar_events e WHERE e.id=:id AND e.status IN('publicado','cancelado') AND (e.publico='todos' OR (e.publico='turmas' AND EXISTS(SELECT 1 FROM school_calendar_event_classes ec WHERE ec.event_id=e.id AND ec.turma_id=:class_id)) OR (e.publico='alunos' AND EXISTS(SELECT 1 FROM school_calendar_event_students es WHERE es.event_id=e.id AND es.aluno_id=:student_id)))",['id'=>(int)$raw,'class_id'=>$student['turma_id'],'student_id'=>$student['id']]); if(!$row)$this->fail('event_not_found','Evento não encontrado.',404); return $row; }
    private function communication(array $r,bool $full): array { return ['id'=>(int)$r['id'],'title'=>$r['titulo'],'content'=>$full?$r['conteudo']:mb_substr(strip_tags($r['conteudo']),0,220),'priority'=>$r['prioridade'],'allow_replies'=>!empty($r['permite_resposta']),'audience'=>$r['publico'],'is_read'=>$r['lido_em']!==null,'read_at'=>$this->iso($r['lido_em']),'published_at'=>$this->iso($r['published_at']),'expires_at'=>$this->iso($r['expires_at']),'reply_count'=>(int)($r['reply_count']??0)]; }
    private function parentId(): int { return (int)MobileApiAuthMiddleware::$parentId; }
    private function input(): array { $data=json_decode((string)file_get_contents('php://input'),true); return is_array($data)?$data:$_POST; }
    private function date($value,string $fallback): string { return preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$value)?(string)$value:$fallback; }
    private function iso($value): ?string { if(!$value)return null;$time=strtotime((string)$value);return $time===false?null:date(DATE_ATOM,$time); }
    private function absoluteUrl(string $path): string { return preg_match('#^https?://#',$path)?$path:rtrim((string)URL,'/').'/'.ltrim($path,'/'); }
    private function fail(string $code,string $message,int $status): void { $this->json(['error'=>['code'=>$code,'message'=>$message]],$status); }
}
