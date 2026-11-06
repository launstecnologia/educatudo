<?php

namespace App\API\Controllers\V1;

require_once __DIR__ . '/../../../Policies/ParentStudentAccessPolicy.php';

use App\Core\BaseController;
use App\Core\Database;
use App\Middleware\MobileApiAuthMiddleware;

class MobileSchoolCommunicationController extends BaseController
{
    private $db;
    private \ParentStudentAccessPolicy $policy;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->policy = new \ParentStudentAccessPolicy();
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
                    OR (c.publico='alunos' AND EXISTS(SELECT 1 FROM school_communication_students cs WHERE cs.communication_id=c.id AND cs.aluno_id=:student_id))
                    OR (c.publico='series' AND EXISTS(SELECT 1 FROM school_communication_series ss WHERE ss.communication_id=c.id AND ss.serie_id=:serie_id))
                    OR (c.publico='cursos' AND EXISTS(SELECT 1 FROM school_communication_cursos cu WHERE cu.communication_id=c.id AND cu.curso_id=:curso_id)))
             ORDER BY FIELD(c.prioridade,'urgente','importante','normal'), c.published_at DESC",
            ['reply_parent'=>$this->parentId(),'reply_student'=>$student['id'],'read_parent'=>$this->parentId(),'read_student'=>$student['id'],'class_id'=>$student['turma_id'],'student_id'=>$student['id'],'serie_id'=>(int)($student['serie_id']??0),'curso_id'=>(int)($student['curso_id']??0)]
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
            'SELECT id,sender_type,mensagem,imagem,lido_em,created_at FROM school_communication_replies WHERE communication_id=:communication AND responsavel_id=:parent AND aluno_id=:student ORDER BY id',
            ['communication'=>$item['id'],'parent'=>$this->parentId(),'student'=>$student['id']]
        );
        $data=$this->communication($item,true);
        $data['attachments']=array_map(fn($a)=>['id'=>(int)$a['id'],'url'=>$this->absoluteUrl($a['arquivo']),'name'=>$a['arquivo_nome'],'mime_type'=>$a['tipo_arquivo'],'size'=>$a['tamanho']!==null?(int)$a['tamanho']:null],$attachments);
        $data['replies']=array_map(function ($r) {
            $imagem = trim((string) ($r['imagem'] ?? ''));
            return ['id'=>(int)$r['id'],'sender_type'=>$r['sender_type'],'message'=>$r['mensagem'],'image_url'=>$imagem !== '' ? $this->absoluteUrl($imagem) : null,'read_at'=>$this->iso($r['lido_em']),'created_at'=>$this->iso($r['created_at'])];
        }, $replies);
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
        $input=$this->input(); $message=trim((string)($input['message'] ?? $input['mensagem'] ?? ''));
        $service=new \App\Modulos\Comunicacao\Services\SchoolCommunicationService($this->db);
        $imagem=$service->guardarImagem($_FILES['imagem'] ?? [], 'fique-sabendo');
        if(($message==='' && $imagem===null) || mb_strlen($message)>4000)$this->fail('invalid_message','Escreva uma mensagem de até 4.000 caracteres ou envie uma imagem.',422);
        $replyId=(int)$this->db->insert("INSERT INTO school_communication_replies (communication_id,responsavel_id,aluno_id,sender_type,sender_id,mensagem,imagem,imagem_nome) VALUES (:communication,:parent,:student,'responsavel',:sender,:message,:imagem,:nome)",['communication'=>$item['id'],'parent'=>$this->parentId(),'student'=>$student['id'],'sender'=>$this->parentId(),'message'=>$message,'imagem'=>$imagem['path'] ?? null,'nome'=>$imagem['name'] ?? null]);
        $nome=trim((string)($student['nome'] ?? 'Aluno'));
        $resumo=$message!=='' ? mb_substr($message, 0, 180) : 'Enviou uma imagem.';
        $service->avisarAdmins('Fique sabendo', $nome . ' respondeu: ' . $resumo);
        $this->json(['data'=>['id'=>$replyId,'sender_type'=>'responsavel','message'=>$message,'image_url'=>$imagem ? $this->absoluteUrl($imagem['path']) : null,'created_at'=>date(DATE_ATOM)]],201);
    }

    public function messages($studentId): void
    {
        $student=$this->student($studentId);
        $service=new \App\Modulos\Comunicacao\Services\SchoolCommunicationService($this->db);
        if(!$service->canalFamiliaPronto()){
            $this->json(['data'=>[],'meta'=>['count'=>0,'available'=>false]]);
            return;
        }
        $conversa=$this->conversaDaFamilia((int)$student['id'], false);
        if(!$conversa){
            $this->json(['data'=>[],'meta'=>['count'=>0,'available'=>true]]);
            return;
        }
        $this->db->query("UPDATE familia_mensagens SET lido_familia_em=NOW() WHERE conversa_id=:id AND autor_tipo='escola' AND lido_familia_em IS NULL", ['id'=>(int)$conversa['id']]);
        $rows=$this->db->fetchAll('SELECT id, autor_tipo, mensagem, imagem, created_at FROM familia_mensagens WHERE conversa_id=:id ORDER BY id', ['id'=>(int)$conversa['id']]);
        $data=array_map(fn($row)=>$this->mensagemFamilia(is_array($row)?$row:[]), is_array($rows)?$rows:[]);
        $this->json(['data'=>$data,'meta'=>['count'=>count($data),'available'=>true]]);
    }

    public function sendMessage($studentId): void
    {
        $student=$this->student($studentId);
        $service=new \App\Modulos\Comunicacao\Services\SchoolCommunicationService($this->db);
        if(!$service->canalFamiliaPronto())$this->fail('unavailable','O canal Fale com a Escola ainda não está disponível.',503);
        $input=$this->input();
        $message=trim((string)($input['message'] ?? $input['mensagem'] ?? ''));
        $imagem=$service->guardarImagem($_FILES['imagem'] ?? [], 'fale-com-a-escola');
        if(($message==='' && $imagem===null) || mb_strlen($message)>4000)$this->fail('invalid_message','Escreva uma mensagem de até 4.000 caracteres ou envie uma imagem.',422);
        $conversa=$this->conversaDaFamilia((int)$student['id'], true);
        $id=(int)$this->db->insert(
            "INSERT INTO familia_mensagens (conversa_id, autor_tipo, autor_id, mensagem, imagem, imagem_nome) VALUES (:conversa, 'responsavel', :autor, :mensagem, :imagem, :nome)",
            ['conversa'=>(int)$conversa['id'],'autor'=>$this->parentId(),'mensagem'=>$message!==''?$message:null,'imagem'=>$imagem['path'] ?? null,'nome'=>$imagem['name'] ?? null]
        );
        $this->db->query('UPDATE familia_conversas SET updated_at=NOW() WHERE id=:id', ['id'=>(int)$conversa['id']]);
        $nome=trim((string)($student['nome'] ?? 'Aluno'));
        $resumo=$message!=='' ? mb_substr($message, 0, 180) : 'Enviou uma imagem.';
        $service->avisarAdmins('Fale com a Escola', $nome . ': ' . $resumo);
        $this->json(['data'=>$this->mensagemFamilia(['id'=>$id,'autor_tipo'=>'responsavel','mensagem'=>$message,'imagem'=>$imagem['path'] ?? '','created_at'=>date('Y-m-d H:i:s')])],201);
    }

    public function calendar($studentId): void
    {
        $student=$this->student($studentId);
        $ano = filter_var($_GET['ano'] ?? $_GET['year'] ?? null, FILTER_VALIDATE_INT);
        if ($ano !== false && $ano !== null && $ano >= 2000 && $ano <= 2100) {
            $this->json($this->letivoParaPais($student, (int) $ano));
            return;
        }
        $from=$this->date($_GET['de'] ?? $_GET['from'] ?? '',date('Y-m-01',strtotime('-1 month'))); $to=$this->date($_GET['ate'] ?? $_GET['to'] ?? '',date('Y-m-t',strtotime('+6 months')));
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

    /**
     * Calendário letivo do ano, só com eventos marcados para pais.
     *
     * @param array<string,mixed> $student
     * @return array<string,mixed>
     */
    private function letivoParaPais(array $student, int $ano): array
    {
        $service = new \App\Dominio\Escola\Services\SchoolCalendarService($this->db);
        $turmaId = (int) ($student['turma_id'] ?? 0);
        $anos = $this->anosDoLetivo();
        $data = $this->eventosLetivosDaFamilia($service, $turmaId, $ano);
        $comConteudo = [];
        foreach ($anos as $candidato) {
            if ($candidato === $ano) {
                if ($data !== []) {
                    $comConteudo[] = $candidato;
                }
                continue;
            }
            if ($this->eventosLetivosDaFamilia($service, $turmaId, $candidato) !== []) {
                $comConteudo[] = $candidato;
            }
        }
        rsort($comConteudo);

        return [
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'year' => $ano,
                'years' => $comConteudo,
                'source' => 'letivo',
            ],
        ];
    }

    /**
     * @return list<int>
     */
    private function anosDoLetivo(): array
    {
        try {
            $rows = $this->db->fetchAll('SELECT DISTINCT ano FROM calendario_letivo ORDER BY ano DESC') ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $anos = [];
        foreach ($rows as $row) {
            $ano = (int) ($row['ano'] ?? 0);
            if ($ano >= 2000 && $ano <= 2100) {
                $anos[] = $ano;
            }
        }
        return $anos;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function eventosLetivosDaFamilia(\App\Dominio\Escola\Services\SchoolCalendarService $service, int $turmaId, int $ano): array
    {
        try {
            $escopo = $service->escopoDaTurma($turmaId);
            $cfg = $service->resolver($ano, (int) $escopo['serie_id'], (int) $escopo['curso_id']);
            if (!$cfg) {
                return [];
            }
            $labels = $service->visuaisTipos()['labels'] ?? [];
            $rows = $service->eventos((int) $cfg['id']);
        } catch (\Throwable $e) {
            return [];
        }

        $data = [];
        foreach ($rows as $evento) {
            if (!is_array($evento) || (int) ($evento['visivel_pais'] ?? 0) !== 1) {
                continue;
            }
            $tipo = (string) ($evento['tipo'] ?? '');
            $inicio = (string) ($evento['data_inicio'] ?? '');
            $fim = (string) ($evento['data_fim'] ?? '');
            $local = trim((string) ($evento['local_evento'] ?? ''));
            $titulo = trim((string) ($evento['descricao'] ?? ''));
            $data[] = [
                'id' => (int) ($evento['id'] ?? 0),
                'title' => $titulo !== '' ? $titulo : 'Evento',
                'description' => null,
                'category' => (string) ($labels[$tipo] ?? ($tipo !== '' ? $tipo : 'Evento')),
                'priority' => 'normal',
                'location' => $local !== '' ? $local : null,
                'starts_at' => $this->iso($inicio !== '' ? $inicio . ' 00:00:00' : null),
                'ends_at' => ($fim !== '' && $fim !== $inicio) ? $this->iso($fim . ' 00:00:00') : null,
                'all_day' => true,
                'status' => 'publicado',
                'is_read' => true,
                'source' => 'letivo',
            ];
        }
        return $data;
    }

    private function conversaDaFamilia(int $alunoId, bool $criar): ?array
    {
        $row=$this->db->fetch('SELECT id FROM familia_conversas WHERE aluno_id=:aluno AND responsavel_id=:responsavel', ['aluno'=>$alunoId,'responsavel'=>$this->parentId()]);
        if($row)return $row;
        if(!$criar)return null;
        try {
            $id=(int)$this->db->insert('INSERT INTO familia_conversas (aluno_id, responsavel_id) VALUES (:aluno, :responsavel)', ['aluno'=>$alunoId,'responsavel'=>$this->parentId()]);
            return ['id'=>$id];
        } catch (\Throwable $e) {
            $existente=$this->db->fetch('SELECT id FROM familia_conversas WHERE aluno_id=:aluno AND responsavel_id=:responsavel', ['aluno'=>$alunoId,'responsavel'=>$this->parentId()]);
            if($existente)return $existente;
            throw $e;
        }
    }

    private function mensagemFamilia(array $row): array
    {
        $imagem=trim((string)($row['imagem'] ?? ''));
        return [
            'id'=>(int)($row['id'] ?? 0),
            'sender_type'=>($row['autor_tipo'] ?? '')==='escola' ? 'escola' : 'responsavel',
            'message'=>(string)($row['mensagem'] ?? ''),
            'image_url'=>$imagem!=='' ? $this->absoluteUrl($imagem) : null,
            'created_at'=>$this->iso($row['created_at'] ?? null),
        ];
    }

    private function student($raw): array { $id=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if($id===false||!$this->policy->canAccess($this->parentId(),(int)$id))$this->fail('student_not_found','Aluno não encontrado.',404); $row=$this->db->fetch('SELECT a.id,a.nome,a.turma_id,t.serie_id,COALESCE(t.curso_novo_id,t.curso_id) curso_id FROM alunos a LEFT JOIN turmas t ON t.id=a.turma_id WHERE a.id=:id',['id'=>(int)$id]); if(!$row)$this->fail('student_not_found','Aluno não encontrado.',404); return $row; }
    private function communicationFor(array $student,$raw): array { $row=$this->db->fetch("SELECT c.*,rd.lido_em FROM school_communications c LEFT JOIN school_communication_reads rd ON rd.communication_id=c.id AND rd.responsavel_id=:parent AND rd.aluno_id=:read_student WHERE c.id=:id AND c.status='publicado' AND (c.publico='todos' OR (c.publico='turmas' AND EXISTS(SELECT 1 FROM school_communication_classes cc WHERE cc.communication_id=c.id AND cc.turma_id=:class_id)) OR (c.publico='alunos' AND EXISTS(SELECT 1 FROM school_communication_students cs WHERE cs.communication_id=c.id AND cs.aluno_id=:student_id)) OR (c.publico='series' AND EXISTS(SELECT 1 FROM school_communication_series ss WHERE ss.communication_id=c.id AND ss.serie_id=:serie_id)) OR (c.publico='cursos' AND EXISTS(SELECT 1 FROM school_communication_cursos cu WHERE cu.communication_id=c.id AND cu.curso_id=:curso_id)))",['parent'=>$this->parentId(),'read_student'=>$student['id'],'id'=>(int)$raw,'class_id'=>$student['turma_id'],'student_id'=>$student['id'],'serie_id'=>(int)($student['serie_id']??0),'curso_id'=>(int)($student['curso_id']??0)]); if(!$row)$this->fail('communication_not_found','Comunicação não encontrada.',404); return $row; }
    private function eventFor(array $student,$raw): array { $row=$this->db->fetch("SELECT e.id FROM school_calendar_events e WHERE e.id=:id AND e.status IN('publicado','cancelado') AND (e.publico='todos' OR (e.publico='turmas' AND EXISTS(SELECT 1 FROM school_calendar_event_classes ec WHERE ec.event_id=e.id AND ec.turma_id=:class_id)) OR (e.publico='alunos' AND EXISTS(SELECT 1 FROM school_calendar_event_students es WHERE es.event_id=e.id AND es.aluno_id=:student_id)))",['id'=>(int)$raw,'class_id'=>$student['turma_id'],'student_id'=>$student['id']]); if(!$row)$this->fail('event_not_found','Evento não encontrado.',404); return $row; }
    private function communication(array $r,bool $full): array { return ['id'=>(int)$r['id'],'title'=>$r['titulo'],'content'=>$full?$r['conteudo']:mb_substr(strip_tags($r['conteudo']),0,220),'video_url'=>$r['video_url']??null,'priority'=>$r['prioridade'],'allow_replies'=>!empty($r['permite_resposta']),'audience'=>$r['publico'],'is_read'=>$r['lido_em']!==null,'read_at'=>$this->iso($r['lido_em']),'published_at'=>$this->iso($r['published_at']),'expires_at'=>$this->iso($r['expires_at']),'reply_count'=>(int)($r['reply_count']??0)]; }
    private function parentId(): int { return (int)MobileApiAuthMiddleware::$parentId; }
    private function input(): array { $data=json_decode((string)file_get_contents('php://input'),true); return is_array($data)?$data:$_POST; }
    private function date($value,string $fallback): string { return preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$value)?(string)$value:$fallback; }
    private function iso($value): ?string { if(!$value)return null;$time=strtotime((string)$value);return $time===false?null:date(DATE_ATOM,$time); }
    private function absoluteUrl(string $path): string { return preg_match('#^https?://#',$path)?$path:rtrim((string)URL,'/').'/'.ltrim($path,'/'); }
    private function fail(string $code,string $message,int $status): void { $this->json(['error'=>['code'=>$code,'message'=>$message]],$status); }
}

if (!class_exists('MobileSchoolCommunicationController', false) && class_exists(__NAMESPACE__ . '\\MobileSchoolCommunicationController', false)) {
    class_alias(__NAMESPACE__ . '\\MobileSchoolCommunicationController', 'MobileSchoolCommunicationController');
}
