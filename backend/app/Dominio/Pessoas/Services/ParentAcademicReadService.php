<?php

namespace App\Dominio\Pessoas\Services;

use App\Core\Database;

/** Consultas acadêmicas compartilháveis pelos adapters da API. */
class ParentAcademicReadService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function dashboard(int $studentId): array
    {
        $counts = $this->db->fetch(
            "SELECT
                (SELECT COUNT(*) FROM provas_realizacoes WHERE aluno_id = :exam_student AND status = 'finalizado') AS exams,
                (SELECT COUNT(*) FROM jornadas_progresso_alunos WHERE aluno_id = :exercise_student AND atividade_tipo IN ('exercicio','exercicio_modulo')) AS exercises,
                (SELECT COUNT(*) FROM redacoes WHERE aluno_id = :essay_student) AS essays",
            ['exam_student' => $studentId, 'exercise_student' => $studentId, 'essay_student' => $studentId]
        );
        // Provas e redações usam escalas diferentes em algumas escolas; nunca
        // combinar as duas notas em uma única média.
        $averages = $this->db->fetch(
            "SELECT
                (SELECT AVG(nota) FROM provas_realizacoes
                  WHERE aluno_id = :exam_student AND status = 'finalizado' AND nota IS NOT NULL) AS exam_average,
                (SELECT AVG(nota_final) FROM redacoes
                  WHERE aluno_id = :essay_student AND nota_final IS NOT NULL) AS essay_average",
            ['exam_student' => $studentId, 'essay_student' => $studentId]
        );
        $last = $this->db->fetch(
            "SELECT MAX(activity_at) AS last_activity_at FROM (
                SELECT finalizado_em AS activity_at FROM provas_realizacoes WHERE aluno_id = :exam_student AND finalizado_em IS NOT NULL
                UNION ALL
                SELECT data_conclusao FROM jornadas_progresso_alunos WHERE aluno_id = :journey_student AND data_conclusao IS NOT NULL
                UNION ALL
                SELECT created_at FROM redacoes WHERE aluno_id = :essay_student
            ) activities",
            ['exam_student' => $studentId, 'journey_student' => $studentId, 'essay_student' => $studentId]
        );

        $student = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id', ['id' => $studentId]);
        $classId = (int)($student['turma_id'] ?? 0);
        $nextExam = null;
        if ($classId > 0 && $this->db->tableExists('provas')) {
            try {
                $nextExam = $this->db->fetch(
                    "SELECT p.id, p.titulo, p.data_inicio, p.data_prova, m.nome subject_name
                     FROM provas p LEFT JOIN materias m ON m.id=p.materia_id
                     WHERE p.deleted_at IS NULL AND p.ativo=1 AND COALESCE(p.data_prova, DATE(p.data_inicio)) >= CURDATE()
                       AND (p.turma_id=:class_id OR EXISTS(SELECT 1 FROM provas_turmas pt WHERE pt.prova_id=p.id AND pt.turma_id=:linked_class))
                     ORDER BY COALESCE(p.data_prova, DATE(p.data_inicio)), p.data_inicio LIMIT 1",
                    ['class_id'=>$classId, 'linked_class'=>$classId]
                );
            } catch (\Throwable $e) {
                $nextExam = $this->db->fetch(
                    "SELECT p.id, p.titulo, p.data_inicio, p.data_prova, m.nome subject_name
                     FROM provas p LEFT JOIN materias m ON m.id=p.materia_id
                     WHERE p.deleted_at IS NULL AND p.ativo=1 AND p.turma_id=:class_id
                       AND COALESCE(p.data_prova, DATE(p.data_inicio)) >= CURDATE()
                     ORDER BY COALESCE(p.data_prova, DATE(p.data_inicio)), p.data_inicio LIMIT 1",
                    ['class_id'=>$classId]
                );
            }
        }
        $accessStatus = null;
        if ($this->db->tableExists('student_access_events')) {
            $event = $this->db->fetch('SELECT id, kind, event_at FROM student_access_events WHERE student_id=:id ORDER BY event_at DESC,id DESC LIMIT 1',['id'=>$studentId]);
            if ($event) $accessStatus = ['kind'=>$event['kind'],'at'=>$this->isoDate($event['event_at']),'is_at_school'=>$event['kind']==='entrada'];
        }

        return [
            // `average_grade` permanece por compatibilidade e agora representa apenas provas.
            'average_grade' => isset($averages['exam_average']) ? round((float) $averages['exam_average'], 1) : null,
            'essay_average' => isset($averages['essay_average']) ? round((float) $averages['essay_average'], 1) : null,
            'total_exams' => (int) ($counts['exams'] ?? 0),
            'total_exercises' => (int) ($counts['exercises'] ?? 0),
            'total_essays' => (int) ($counts['essays'] ?? 0),
            'last_activity_at' => $this->isoDate($last['last_activity_at'] ?? null),
            'next_exam' => $nextExam ? [
                'id'=>(int)$nextExam['id'], 'title'=>$nextExam['titulo'], 'subject_name'=>$nextExam['subject_name'],
                'date'=>$this->isoDate($nextExam['data_prova'] ?: $nextExam['data_inicio']),
            ] : null,
            'access_status' => $accessStatus,
            'recent_activity' => $this->recentActivity($studentId),
        ];
    }

    public function accessEvents(int $studentId, ?string $from = null, ?string $to = null): array
    {
        if (!$this->db->tableExists('student_access_events')) return ['status'=>null,'events'=>[]];
        $where=['student_id=:student']; $params=['student'=>$studentId];
        if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) { $where[]='event_at >= :date_from'; $params['date_from']=$from.' 00:00:00'; }
        if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) { $where[]='event_at <= :date_to'; $params['date_to']=$to.' 23:59:59'; }
        $rows=$this->db->fetchAll('SELECT id,kind,event_at,confidence,notified_at FROM student_access_events WHERE '.implode(' AND ',$where).' ORDER BY event_at DESC,id DESC LIMIT 300',$params);
        $latest=$this->db->fetch('SELECT kind,event_at FROM student_access_events WHERE student_id=:student ORDER BY event_at DESC,id DESC LIMIT 1',['student'=>$studentId]);
        return [
            'status'=>$latest?['kind'=>$latest['kind'],'at'=>$this->isoDate($latest['event_at']),'is_at_school'=>$latest['kind']==='entrada']:null,
            'events'=>array_map(fn($row)=>['id'=>(int)$row['id'],'kind'=>$row['kind'],'event_at'=>$this->isoDate($row['event_at']),'confidence'=>$row['confidence']!==null?(float)$row['confidence']:null,'notified'=>!empty($row['notified_at'])],$rows),
        ];
    }

    /**
     * Carteirinha vigente do aluno, no mesmo formato exibido ao próprio aluno.
     *
     * @return array<string,mixed>|null
     */
    public function studentCard(int $studentId): ?array
    {
        if ($studentId <= 0) {
            return null;
        }
        try {
            if (!\App\Core\LayoutHelper::isModuleEnabled('carteirinha')) {
                return null;
            }
            $service = new \App\Modulos\Carteirinha\Services\CarteirinhaService();
            if (!$service->tabelasProntas() || !$service->habilitada()) {
                return null;
            }
            $card = $service->cartaoDoAluno($studentId);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($card)) {
            return null;
        }
        $bar = trim((string) ($card['cor_barra'] ?? ''));
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $bar)) {
            $bar = '#1649D8';
        }

        return [
            'id' => (int) ($card['id'] ?? 0),
            'name' => (string) ($card['nome'] ?? ''),
            'registration' => (string) ($card['ra'] ?? ''),
            'class_name' => (string) ($card['turma'] ?? ''),
            'photo_url' => (string) ($card['foto'] ?? ''),
            'school_name' => (string) ($card['escola'] ?? ''),
            'logo_url' => (string) ($card['logo'] ?? ''),
            'bar_color' => $bar,
            'code' => (string) ($card['codigo'] ?? ''),
            'valid_until' => (string) ($card['validade'] ?? ''),
            'issued_at' => (string) ($card['emitida_em'] ?? ''),
            'status' => (string) ($card['situacao'] ?? ''),
            'validation_url' => (string) ($card['url'] ?? ''),
        ];
    }

    /**
     * Ocorrências liberadas para a família (enviar_pais = 1).
     *
     * @return list<array<string,mixed>>
     */
    public function occurrences(int $studentId): array
    {
        if ($studentId <= 0) {
            return [];
        }
        try {
            $select = 'o.id, o.data_ocorrencia, o.titulo, o.detalhe, o.nivel_gravidade, o.retorno_em';
            $join = '';
            if ($this->hasColumn('alunos_ocorrencias', 'status')) {
                $select .= ', o.status';
            }
            if ($this->hasColumn('alunos_ocorrencias', 'categoria_id') && $this->db->tableExists('ocorrencias_categorias')) {
                $select .= ', cat.nome AS categoria_nome';
                $join .= ' LEFT JOIN ocorrencias_categorias cat ON cat.id = o.categoria_id';
            }
            if ($this->hasColumn('alunos_ocorrencias', 'materia_id') && $this->db->tableExists('materias')) {
                $select .= ', m.nome AS materia_nome';
                $join .= ' LEFT JOIN materias m ON m.id = o.materia_id';
            }
            $rows = $this->db->fetchAll(
                "SELECT {$select}
                 FROM alunos_ocorrencias o
                 {$join}
                 WHERE o.enviar_pais = 1
                   AND (
                       o.aluno_id = :aluno
                       OR EXISTS (
                           SELECT 1 FROM alunos_ocorrencias_itens oi
                           WHERE oi.ocorrencia_id = o.id AND oi.aluno_id = :aluno_item
                       )
                   )
                 ORDER BY o.data_ocorrencia DESC",
                ['aluno' => $studentId, 'aluno_item' => $studentId]
            );
        } catch (\Throwable $e) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $category = trim((string) ($row['categoria_nome'] ?? ''));
            $subject = trim((string) ($row['materia_nome'] ?? ''));
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['titulo'] ?? ''),
                'detail' => (string) ($row['detalhe'] ?? ''),
                'severity' => (string) ($row['nivel_gravidade'] ?? ''),
                'status' => isset($row['status']) ? (string) $row['status'] : null,
                'category' => $category !== '' ? $category : null,
                'subject' => $subject !== '' ? $subject : null,
                'occurred_at' => $this->isoDate($row['data_ocorrencia'] ?? null),
                'follow_up_at' => $this->isoDate($row['retorno_em'] ?? null),
            ];
        }

        return $items;
    }

    public function reportCard(int $studentId): array
    {
        // Boletim oficial fica fora do acesso dos pais (portal e app).
        return [];
    }

    /**
     * Eventos de prova da turma e notas já liberadas para a família.
     *
     * @return array{events:list<array<string,mixed>>,periods:list<array<string,mixed>>}
     */
    public function examEvents(int $studentId): array
    {
        $student = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id', ['id' => $studentId]);
        $classId = (int) ($student['turma_id'] ?? 0);

        return [
            'events' => $this->scheduledExamEvents($classId),
            'periods' => $this->liveGradePeriods($studentId, $classId),
        ];
    }

    private function recentActivity(int $studentId): array
    {
        $items=[];
        if($this->db->tableExists('student_access_events')) foreach($this->db->fetchAll('SELECT id,kind,event_at FROM student_access_events WHERE student_id=:id ORDER BY event_at DESC LIMIT 5',['id'=>$studentId]) as $row){$items[]=['type'=>'attendance','title'=>$row['kind']==='entrada'?'Entrada registrada':'Saída registrada','occurred_at'=>$this->isoDate($row['event_at']),'route'=>'/attendance','metadata'=>['kind'=>$row['kind']]];}
        if($this->db->tableExists('provas_realizacoes')) foreach($this->db->fetchAll("SELECT pr.prova_id,p.titulo,pr.finalizado_em FROM provas_realizacoes pr INNER JOIN provas p ON p.id=pr.prova_id WHERE pr.aluno_id=:id AND pr.finalizado_em IS NOT NULL ORDER BY pr.finalizado_em DESC LIMIT 3",['id'=>$studentId]) as $row){$items[]=['type'=>'exam','title'=>'Prova finalizada: '.$row['titulo'],'occurred_at'=>$this->isoDate($row['finalizado_em']),'route'=>'/exams','metadata'=>['exam_id'=>(int)$row['prova_id']]];}
        if($this->db->tableExists('school_communications')) {
            $extraPublico = '';
            if ($this->db->tableExists('school_communication_series')) {
                $extraPublico .= " OR (c.publico='series' AND EXISTS(SELECT 1 FROM school_communication_series ss WHERE ss.communication_id=c.id AND ss.serie_id=t.serie_id))";
            }
            if ($this->db->tableExists('school_communication_cursos')) {
                $extraPublico .= " OR (c.publico='cursos' AND EXISTS(SELECT 1 FROM school_communication_cursos cu WHERE cu.communication_id=c.id AND cu.curso_id=COALESCE(t.curso_novo_id, t.curso_id)))";
            }
            foreach($this->db->fetchAll("SELECT c.id,c.titulo,c.published_at FROM school_communications c INNER JOIN alunos a ON a.id=:student LEFT JOIN turmas t ON t.id=a.turma_id WHERE c.status='publicado' AND (c.publico='todos' OR EXISTS(SELECT 1 FROM school_communication_classes cc WHERE cc.communication_id=c.id AND cc.turma_id=a.turma_id) OR EXISTS(SELECT 1 FROM school_communication_students cs WHERE cs.communication_id=c.id AND cs.aluno_id=a.id){$extraPublico}) ORDER BY c.published_at DESC LIMIT 3",['student'=>$studentId]) as $row){$items[]=['type'=>'communication','title'=>'Novo comunicado: '.$row['titulo'],'occurred_at'=>$this->isoDate($row['published_at']),'route'=>'/school-communications/'.(int)$row['id'],'metadata'=>['communication_id'=>(int)$row['id']]];}
        }
        usort($items,fn($a,$b)=>strcmp((string)$b['occurred_at'],(string)$a['occurred_at']));
        return array_slice($items,0,8);
    }

    public function exams(int $studentId): array
    {
        // Não use tableExists() aqui: em alguns tenants o usuário do banco não
        // enxerga information_schema, embora consiga consultar as tabelas. O
        // portal /pais usa SHOW COLUMNS e essa deve ser a mesma fonte de verdade.
        $hasExamSubject = $this->hasColumn('provas', 'materia_id');
        $subjectParts = [];
        if ($hasExamSubject) $subjectParts[] = "NULLIF(TRIM(COALESCE(m.nome, '')), '')";
        foreach (['materia', 'disciplina', 'area_conhecimento'] as $column) {
            if ($this->hasColumn('provas_realizacoes', $column)) {
                $subjectParts[] = "NULLIF(TRIM(COALESCE(pr.{$column}, '')), '')";
            }
        }
        $subjectSelect = $subjectParts ? 'COALESCE(' . implode(', ', $subjectParts) . ')' : 'NULL';

        $questionParts = [];
        foreach (['questoes_total', 'total_questoes', 'qtd_questoes'] as $column) {
            if ($this->hasColumn('provas_realizacoes', $column)) $questionParts[] = "pr.{$column}";
        }
        $questionJoin = '';
        if ($this->hasColumn('provas_questoes', 'prova_id')) {
            $questionParts[] = 'pqc.question_count';
            $questionJoin = 'LEFT JOIN (SELECT prova_id, COUNT(*) question_count FROM provas_questoes GROUP BY prova_id) pqc ON pqc.prova_id = pr.prova_id';
        }
        $questionSelect = $questionParts ? 'COALESCE(' . implode(', ', $questionParts) . ', 0)' : '0';

        $correctParts = [];
        foreach (['questoes_corretas', 'acertos', 'qtd_acertos'] as $column) {
            if ($this->hasColumn('provas_realizacoes', $column)) $correctParts[] = "pr.{$column}";
        }
        $answerJoin = '';
        if ($this->hasColumn('provas_respostas', 'correta')) {
            $correctParts[] = 'pra.correct_count';
            $answerJoin = 'LEFT JOIN (
                SELECT prova_id, aluno_id, SUM(CASE WHEN correta = 1 THEN 1 ELSE 0 END) correct_count
                FROM provas_respostas
                WHERE aluno_id = :answer_student_id
                GROUP BY prova_id, aluno_id
            ) pra ON pra.prova_id = pr.prova_id AND pra.aluno_id = pr.aluno_id';
        }
        $correctSelect = $correctParts ? 'COALESCE(' . implode(', ', $correctParts) . ', 0)' : '0';
        $subjectJoin = $hasExamSubject ? 'LEFT JOIN materias m ON m.id = p.materia_id' : '';
        $blockSelect = 'NULL block_id, NULL block_title, NULL block_model_id, NULL block_model_name, NULL block_date, NULL term, NULL school_year';
        $blockJoin = '';
        if ($this->db->tableExists('provas_blocos_vinculo') && $this->db->tableExists('provas_blocos')) {
            $blockSelect = 'pb.id block_id, pb.titulo block_title, pb.bloco_modelo_id block_model_id, pbm.nome block_model_name, pb.data_prova block_date, pb.bimestre term, pb.ano_letivo school_year';
            $modelJoin = $this->db->tableExists('provas_blocos_modelos')
                ? 'LEFT JOIN provas_blocos_modelos pbm ON pbm.id = pb.bloco_modelo_id' : '';
            if (!$modelJoin) $blockSelect = str_replace('pbm.nome block_model_name', 'NULL block_model_name', $blockSelect);
            $blockJoin = "LEFT JOIN (SELECT prova_id, MAX(bloco_id) bloco_id FROM provas_blocos_vinculo GROUP BY prova_id) pbv ON pbv.prova_id = p.id
                          LEFT JOIN provas_blocos pb ON pb.id = pbv.bloco_id
                          {$modelJoin}";
        }
        $rows = $this->db->fetchAll(
            "SELECT p.id, pr.id realization_id, p.titulo, {$subjectSelect} subject_name,
                    pr.nota, pr.finalizado_em, pr.iniciado_em, pr.status,
                    {$questionSelect} question_count, {$correctSelect} correct_count,
                    {$blockSelect}
             FROM provas_realizacoes pr
             INNER JOIN provas p ON p.id = pr.prova_id AND p.deleted_at IS NULL
             {$subjectJoin}
             {$questionJoin}
             {$answerJoin}
             {$blockJoin}
             WHERE pr.aluno_id = :student_id
             ORDER BY COALESCE(pr.finalizado_em, pr.iniciado_em) DESC
             LIMIT 100",
            array_filter([
                'student_id' => $studentId,
                'answer_student_id' => $answerJoin !== '' ? $studentId : null,
            ], static fn ($value) => $value !== null)
        );
        return array_map(function (array $row): array {
            $questions = (int) ($row['question_count'] ?? 0);
            $correct = (int) ($row['correct_count'] ?? 0);
            $incorrect = max(0, $questions - $correct);
            $corrected = $correct + $incorrect;
            return [
                'id' => (int) $row['id'], 'title' => (string) $row['titulo'],
                'realization_id' => (int) $row['realization_id'],
                'subject_name' => $row['subject_name'],
                'grade' => $row['nota'] !== null ? (float) $row['nota'] : null,
                'status' => (string) ($row['status'] ?? ''),
                'question_count' => $questions,
                'correct_count' => $correct,
                'incorrect_count' => $incorrect,
                'pending_count' => max(0, $questions - $corrected),
                'accuracy_percent' => $corrected > 0 ? round(min(100, $correct * 100 / $corrected), 1) : 0.0,
                'completed_at' => $this->isoDate($row['finalizado_em'] ?? null),
                'block_id' => isset($row['block_id']) ? (int) $row['block_id'] : null,
                'block_title' => $row['block_title'] ?? null,
                'block_model_id' => isset($row['block_model_id']) ? (int) $row['block_model_id'] : null,
                'block_model_name' => $row['block_model_name'] ?? null,
                'block_date' => $this->isoDate($row['block_date'] ?? null),
                'term' => $row['term'] ?? null,
                'school_year' => isset($row['school_year']) ? (int) $row['school_year'] : null,
            ];
        }, $rows);
    }

    public function journeys(int $studentId): array
    {
        $rows = (new ParentJornadasService($this->db))->listarDetalhadas($studentId, 0);
        return array_map(function (array $row): array {
            $total = (int) ($row['total_modulos'] ?? 0);
            $done = (int) ($row['modulos_feitos'] ?? 0);
            return [
                'id' => (int) $row['id'],
                'title' => (string) ($row['titulo'] ?? ''),
                'status' => (string) (($row['status'] ?? '') ?: 'active'),
                'teacher_name' => $row['professor_nome'] ?? null,
                'subject_name' => $row['materia_nome'] ?? null,
                'total_modules' => $total,
                'completed_modules' => $done,
                'started' => !empty($row['fez']),
                'completed' => !empty($row['concluiu']),
                'progress_percent' => $total > 0 ? round(min(100, $done * 100 / $total), 1) : 0.0,
                'created_at' => $this->isoDate($row['created_at'] ?? null),
            ];
        }, $rows);
    }

    public function lessonPlans(int $studentId): array
    {
        $student = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id', ['id' => $studentId]);
        $classId = (int) ($student['turma_id'] ?? 0);
        if ($classId <= 0) return [];
        $rows = $this->db->fetchAll(
            "SELECT pa.id, pa.titulo, pa.objetivos, pa.created_at, p.nome professor_name, m.nome subject_name
             FROM planos_aula pa LEFT JOIN professores p ON p.id = pa.professor_id
             LEFT JOIN materias m ON m.id = pa.materia_id
             WHERE pa.turma_id = :class_id AND pa.deleted_at IS NULL
             ORDER BY pa.created_at DESC LIMIT 100",
            ['class_id' => $classId]
        );
        return array_map(function (array $row): array { return [
            'id' => (int) $row['id'], 'title' => (string) $row['titulo'],
            'objectives' => $row['objetivos'], 'teacher_name' => $row['professor_name'],
            'subject_name' => $row['subject_name'], 'created_at' => $this->isoDate($row['created_at'] ?? null),
        ]; }, $rows);
    }

    public function lessonPlan(int $studentId, int $planId): ?array
    {
        $row = $this->db->fetch(
            "SELECT pa.*, p.nome professor_name, m.nome subject_name,
                    t.nome class_name, t.serie class_grade
             FROM alunos a
             INNER JOIN planos_aula pa ON pa.turma_id = a.turma_id
             LEFT JOIN professores p ON p.id = pa.professor_id
             LEFT JOIN materias m ON m.id = pa.materia_id
             LEFT JOIN turmas t ON t.id = pa.turma_id
             WHERE a.id = :student_id AND pa.id = :plan_id AND pa.deleted_at IS NULL
             LIMIT 1",
            ['student_id' => $studentId, 'plan_id' => $planId]
        );
        if (!$row) return null;

        $resourcesList = null;
        if (!empty($row['recursos_lista'])) {
            $decoded = json_decode((string) $row['recursos_lista'], true);
            $resourcesList = is_array($decoded) ? $decoded : null;
        }
        $classDates = null;
        if (!empty($row['data_aula'])) {
            $decoded = json_decode((string) $row['data_aula'], true);
            $classDates = is_array($decoded) ? array_values($decoded) : [(string) $row['data_aula']];
        }

        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['titulo'],
            'teacher_name' => $row['professor_name'],
            'subject_name' => $row['subject_name'],
            'class' => ['id' => (int) $row['turma_id'], 'name' => $row['class_name'], 'grade' => $row['class_grade']],
            'class_dates' => $classDates,
            'school_days' => $row['dias_aula'],
            'discipline_grade' => $row['ano_disciplina'],
            'module' => $row['modulo'],
            'lesson_number' => $row['aula_num'],
            'pages' => $row['paginas'],
            'content' => $row['conteudo'],
            'content_list' => $row['conteudo_lista'],
            'objectives' => $row['objetivos'],
            'objectives_list' => $row['objetivos_lista'],
            'methodology' => $row['metodologia'],
            'resources' => $row['recursos'],
            'resources_list' => $resourcesList,
            'assessment' => $row['avaliacao'],
            'notes' => $row['observacoes'],
            'status' => $row['status'],
            'created_at' => $this->isoDate($row['created_at'] ?? null),
            'updated_at' => $this->isoDate($row['updated_at'] ?? null),
        ];
    }

    public function notices(int $studentId): array
    {
        if (!$this->db->tableExists('mural_recados') || !$this->db->tableExists('mural_recados_turmas')) {
            return [];
        }
        $student = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id', ['id' => $studentId]);
        $classId = (int) ($student['turma_id'] ?? 0);
        $params = [];
        $classCondition = '';
        if ($classId > 0) {
            $classCondition = " OR EXISTS (
                SELECT 1 FROM mural_recados_turmas rt
                 WHERE rt.mural_recado_id = r.id AND rt.turma_id = :class_id
            )";
            $params['class_id'] = $classId;
        }
        $temAluno = $this->hasColumn('mural_recados', 'aluno_id');
        $filtroPublico = $this->hasColumn('mural_recados', 'publico')
            ? "COALESCE(r.publico, 'alunos') = 'alunos' AND"
            : '';
        if ($temAluno) {
            $destinoSql = "(r.aluno_id = :student_id OR (r.aluno_id IS NULL AND (r.enviar_para_todos = 1{$classCondition})))";
            $params['student_id'] = $studentId;
        } else {
            $destinoSql = "(r.enviar_para_todos = 1{$classCondition})";
        }
        $hasSubjects = $this->db->tableExists('materias') && $this->hasColumn('mural_recados', 'materia_id');
        $subjectIdSelect = $hasSubjects ? 'r.materia_id' : 'NULL';
        $subjectSelect = $hasSubjects ? 'm.nome' : 'NULL';
        $subjectJoin = $hasSubjects ? 'LEFT JOIN materias m ON m.id = r.materia_id' : '';
        $rows = $this->db->fetchAll(
            "SELECT r.id, r.titulo, r.conteudo, r.data_publicacao, r.data_sai_mural,
                    r.autor_tipo, {$subjectIdSelect} materia_id,
                    CASE WHEN r.autor_tipo = 'professor' THEN p.nome ELSE 'Admin' END author_name,
                    {$subjectSelect} subject_name
             FROM mural_recados r
             LEFT JOIN professores p ON p.id = r.autor_id AND r.autor_tipo = 'professor'
             {$subjectJoin}
             WHERE {$filtroPublico} {$destinoSql}
               AND r.data_publicacao <= NOW()
               AND CURDATE() <= r.data_sai_mural
             ORDER BY r.data_publicacao DESC",
            $params
        );
        if ($rows === []) return [];

        $attachmentRows = [];
        if ($this->db->tableExists('mural_recados_anexos')) {
            $noticeIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($noticeIds), '?'));
            $attachmentRows = $this->db->fetchAll(
                "SELECT mural_recado_id, arquivo, arquivo_nome, tipo_arquivo, tamanho
                 FROM mural_recados_anexos WHERE mural_recado_id IN ({$placeholders}) ORDER BY id",
                $noticeIds
            );
        }
        $attachments = [];
        foreach ($attachmentRows as $attachment) {
            $attachments[(int) $attachment['mural_recado_id']][] = [
                'path' => (string) $attachment['arquivo'],
                'name' => (string) $attachment['arquivo_nome'],
                'mime_type' => $attachment['tipo_arquivo'],
                'size' => $attachment['tamanho'] !== null ? (int) $attachment['tamanho'] : null,
            ];
        }

        return array_map(function (array $row) use ($attachments): array {
            $id = (int) $row['id'];
            return [
                'id' => $id,
                'title' => (string) $row['titulo'],
                'content' => $row['conteudo'],
                'author_type' => (string) $row['autor_tipo'],
                'author_name' => (string) $row['author_name'],
                'subject_id' => $row['materia_id'] !== null ? (int) $row['materia_id'] : null,
                'subject_name' => $row['subject_name'],
                'published_at' => $this->isoDate($row['data_publicacao'] ?? null),
                'expires_on' => $row['data_sai_mural'],
                'attachments' => $attachments[$id] ?? [],
            ];
        }, $rows);
    }

    public function writingJourneys(int $studentId): array
    {
        if (!$this->db->tableExists('jornadas_redacoes') || !$this->db->tableExists('jornadas_redacoes_alunos')) return [];
        $rows = $this->db->fetchAll(
            "SELECT jr.id, jr.jornada_id, j.titulo journey_title, jr.tema_sugerido, jr.descricao_tema,
                    jr.data_limite, jra.status submission_status, jra.nota_final_utilizada, jra.updated_at
             FROM jornadas_redacoes jr INNER JOIN jornadas j ON j.id = jr.jornada_id
             LEFT JOIN jornadas_redacoes_alunos jra ON jra.jornada_redacao_id = jr.id AND jra.aluno_id = :student_id
             INNER JOIN alunos a ON a.id = :student_class_id AND a.turma_id = j.turma_id
             ORDER BY COALESCE(jr.data_limite, jr.created_at) DESC",
            ['student_id' => $studentId, 'student_class_id' => $studentId]
        );
        return array_map(function (array $row): array { return [
            'id' => (int) $row['id'], 'journey_id' => (int) $row['jornada_id'],
            'journey_title' => (string) $row['journey_title'], 'theme' => (string) $row['tema_sugerido'],
            'description' => $row['descricao_tema'], 'due_at' => $this->isoDate($row['data_limite'] ?? null),
            'submission_status' => $row['submission_status'],
            'grade' => $row['nota_final_utilizada'] !== null ? (float) $row['nota_final_utilizada'] : null,
        ]; }, $rows);
    }

    public function essays(int $studentId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, jornada_id, COALESCE(NULLIF(tema, ''), titulo) theme, nota_final,
                    COALESCE(correcao_professor, feedback_ia, correcao) feedback, eh_rascunho, created_at, corrigida_em
             FROM redacoes WHERE aluno_id = :student_id AND (oculto = 0 OR oculto IS NULL)
             ORDER BY created_at DESC LIMIT 100",
            ['student_id' => $studentId]
        );
        return array_map(function (array $row): array { return [
            'id' => (int) $row['id'], 'journey_id' => $row['jornada_id'] !== null ? (int) $row['jornada_id'] : null,
            'theme' => (string) $row['theme'], 'grade' => $row['nota_final'] !== null ? (float) $row['nota_final'] : null,
            'feedback' => $row['feedback'], 'is_draft' => !empty($row['eh_rascunho']),
            'created_at' => $this->isoDate($row['created_at'] ?? null),
            'corrected_at' => $this->isoDate($row['corrigida_em'] ?? null),
        ]; }, $rows);
    }

    public function exercises(int $studentId): array
    {
        $totais = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN pontuacao > 0 THEN 1 ELSE 0 END) AS acertos
             FROM jornadas_progresso_alunos
             WHERE aluno_id = :aluno_id AND atividade_tipo IN ('exercicio', 'exercicio_modulo')",
            ['aluno_id' => $studentId]
        );
        $total = (int) ($totais['total'] ?? 0);
        $acertos = (int) ($totais['acertos'] ?? 0);

        $ultimos = $this->db->fetchAll(
            "SELECT jpa.id, jpa.jornada_id, jpa.pontuacao, jpa.data_conclusao, j.titulo AS jornada_titulo
             FROM jornadas_progresso_alunos jpa
             INNER JOIN jornadas j ON j.id = jpa.jornada_id
             WHERE jpa.aluno_id = :aluno_id AND jpa.atividade_tipo IN ('exercicio', 'exercicio_modulo')
             ORDER BY jpa.data_conclusao DESC, jpa.created_at DESC
             LIMIT 10",
            ['aluno_id' => $studentId]
        );

        $lista = [];
        foreach ($ultimos as $item) {
            $lista[] = [
                'id' => (int) $item['id'],
                'journey_id' => (int) $item['jornada_id'],
                'journey_title' => (string) $item['jornada_titulo'],
                'score' => $item['pontuacao'] !== null ? (float) $item['pontuacao'] : null,
                'completed_at' => $this->isoDate($item['data_conclusao'] ?? null),
            ];
        }

        return [
            'total_completed' => $total,
            'correct_count' => $acertos,
            'accuracy_percent' => $total > 0 ? round(($acertos / $total) * 100, 1) : 0.0,
            'latest' => $lista,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function scheduledExamEvents(int $classId): array
    {
        if ($classId <= 0 || !$this->hasColumn('provas_blocos', 'titulo') || !$this->hasColumn('provas_blocos_turmas', 'turma_id')) {
            return [];
        }

        $hasEvent = $this->hasColumn('provas_blocos', 'evento_avaliativo_id')
            && $this->hasColumn('eventos_avaliativos', 'nome');
        $releasedColumn = $hasEvent && $this->hasColumn('eventos_avaliativos', 'liberar_nota_aluno')
            ? 'e.liberar_nota_aluno'
            : '0';
        $eventSelect = $hasEvent
            ? "e.nome AS event_name, {$releasedColumn} AS liberar_nota_aluno"
            : 'NULL AS event_name, 0 AS liberar_nota_aluno';
        $eventJoin = '';
        $ocultarNaoPublicado = '';
        if ($hasEvent) {
            $eventJoin = 'LEFT JOIN eventos_avaliativos e ON e.id = pb.evento_avaliativo_id';
            if ($this->hasColumn('eventos_avaliativos', 'deleted_at')) {
                $eventJoin .= ' AND e.deleted_at IS NULL';
            }
            if ($this->hasColumn('eventos_avaliativos', 'rascunho')) {
                $eventJoin .= ' AND e.rascunho = 0';
            }
            $ocultarNaoPublicado = ' AND (pb.evento_avaliativo_id IS NULL OR e.id IS NOT NULL)';
        }
        $deleted = $this->hasColumn('provas_blocos', 'deleted_at') ? ' AND pb.deleted_at IS NULL' : '';
        $term = $this->hasColumn('provas_blocos', 'bimestre') ? 'pb.bimestre' : 'NULL';
        $year = $this->hasColumn('provas_blocos', 'ano_letivo') ? 'pb.ano_letivo' : 'NULL';

        try {
            $rows = $this->db->fetchAll(
                "SELECT pb.id, pb.titulo, pb.data_prova, {$term} AS term, {$year} AS school_year, {$eventSelect}
                 FROM provas_blocos pb
                 INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb.id AND pbt.turma_id = :turma
                 {$eventJoin}
                 WHERE 1 = 1 {$deleted}{$ocultarNaoPublicado}
                 ORDER BY pb.data_prova DESC, pb.id DESC
                 LIMIT 80",
                ['turma' => $classId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'title' => trim((string) ($row['titulo'] ?? '')) !== '' ? (string) $row['titulo'] : 'Prova',
                'event_name' => isset($row['event_name']) && $row['event_name'] !== null ? (string) $row['event_name'] : null,
                'date' => $this->isoDate($row['data_prova'] ?? null),
                'term' => isset($row['term']) && $row['term'] !== null ? (int) $row['term'] : null,
                'school_year' => isset($row['school_year']) && $row['school_year'] !== null ? (int) $row['school_year'] : null,
                'grades_released' => !empty($row['liberar_nota_aluno']),
            ];
        }, $rows);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function liveGradePeriods(int $studentId, int $classId): array
    {
        if ($studentId <= 0 || $classId <= 0) {
            return [];
        }
        try {
            $periods = (new \App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService())
                ->notasAoVivoDoAluno($studentId, $classId);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($periods as $period) {
            if (!is_array($period)) {
                continue;
            }
            $columns = [];
            foreach ($period['colunas'] ?? [] as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $columns[] = [
                    'id' => (int) ($column['id'] ?? 0),
                    'title' => (string) ($column['titulo'] ?? 'Nota'),
                ];
            }
            $rows = [];
            foreach ($period['linhas'] ?? [] as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $grades = [];
                foreach ($line['notas'] ?? [] as $blockId => $value) {
                    $grades[(string) $blockId] = (string) $value;
                }
                $rows[] = [
                    'subject' => (string) ($line['materia'] ?? 'Matéria'),
                    'grades' => $grades,
                ];
            }
            $out[] = [
                'title' => (string) ($period['titulo'] ?? 'Notas'),
                'school_year' => (int) ($period['ano'] ?? 0),
                'term' => (int) ($period['bimestre'] ?? 0),
                'columns' => $columns,
                'rows' => $rows,
            ];
        }

        return $out;
    }

    private function isoDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        try {
            // MySQL não aceita placeholder preparado no LIKE do SHOW COLUMNS.
            // Os identificadores já foram limitados a [a-zA-Z0-9_], portanto a
            // interpolação abaixo é segura e mantém compatibilidade com tenants.
            return !empty($this->db->fetchAll("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'"));
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!class_exists('ParentAcademicReadService', false) && class_exists(__NAMESPACE__ . '\\ParentAcademicReadService', false)) {
    class_alias(__NAMESPACE__ . '\\ParentAcademicReadService', 'ParentAcademicReadService');
}
