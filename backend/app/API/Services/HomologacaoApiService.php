<?php

namespace App\API\Services;

/**
 * Respostas fictícias da API do responsável para homologação.
 * Não consulta banco e não autentica usuário real.
 */
class HomologacaoApiService
{
    public const EMAIL = 'responsavel.teste@educatudo.local';
    public const SENHA = 'teste123';
    public const TOKEN = 'homologacao.teste';
    public const REFRESH = 'homologacao.refresh.teste';

    public function disponivel(): bool
    {
        return defined('ENVIRONMENT') && ENVIRONMENT === 'development';
    }

    public function tokenValido(string $token): bool
    {
        return $token !== '' && hash_equals(self::TOKEN, $token);
    }

    /** @return array{status:int,body:array} */
    public function login(string $email, string $senha): array
    {
        $emailOk = hash_equals(self::EMAIL, strtolower(trim($email)));
        $senhaOk = hash_equals(self::SENHA, $senha);
        if (!$emailOk || !$senhaOk) {
            return $this->erro(401, 'invalid_credentials', 'E-mail ou senha inválidos.');
        }

        return $this->ok([
            'access_token' => self::TOKEN,
            'refresh_token' => self::REFRESH,
            'token_type' => 'Bearer',
            'parent' => $this->responsavel(),
        ]);
    }

    /** @return array{status:int,body:array} */
    public function renovar(string $refresh): array
    {
        if (!hash_equals(self::REFRESH, $refresh)) {
            return $this->erro(401, 'invalid_token', 'Refresh token inválido.');
        }

        return $this->ok([
            'access_token' => self::TOKEN,
            'refresh_token' => self::REFRESH,
            'token_type' => 'Bearer',
        ]);
    }

    /** @return array{status:int,body:array} */
    public function sair(): array
    {
        return $this->ok([
            'logged_out' => true,
            'note' => 'No ambiente de teste o token fictício continua válido.',
        ]);
    }

    /** @return array{status:int,body:array} */
    public function eu(): array
    {
        return $this->ok($this->responsavel());
    }

    /** @return array{status:int,body:array} */
    public function alunos(): array
    {
        return $this->colecao(array_values($this->catalogoAlunos()));
    }

    /**
     * @param array<string,mixed> $extra
     * @return array{status:int,body:array}
     */
    public function recursoAluno(string $recurso, $alunoId, array $extra = []): array
    {
        $id = filter_var($alunoId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || !isset($this->catalogoAlunos()[(int) $id])) {
            return $this->erro(404, 'student_not_found', 'Aluno não encontrado.');
        }

        $aluno = $this->catalogoAlunos()[(int) $id];

        return match ($recurso) {
            'dashboard' => $this->ok($this->painel($aluno)),
            'exams' => $this->colecao($this->provas($aluno)),
            'exercises' => $this->ok($this->exercicios($aluno)),
            'journeys' => $this->colecao($this->jornadas($aluno)),
            'lesson_plans' => $this->colecao($this->planos($aluno)),
            'lesson_plan' => $this->plano($aluno, $extra['plan_id'] ?? 0),
            'writing_journeys' => $this->colecao($this->jornadasRedacao($aluno)),
            'essays' => $this->colecao($this->redacoes($aluno)),
            'notices' => $this->colecao($this->avisos($aluno)),
            'report_card' => $this->ok($this->boletim($aluno)),
            'access_events' => $this->ok($this->acessos($aluno)),
            'finance' => $this->ok($this->financeiro($aluno)),
            'payment' => $this->pagamento($aluno, (string) ($extra['source'] ?? ''), $extra['invoice_id'] ?? 0),
            'communications' => $this->comunicados($aluno),
            'communication' => $this->comunicado($aluno, $extra['communication_id'] ?? 0, true),
            'communication_read' => $this->marcarComunicado($aluno, $extra['communication_id'] ?? 0),
            'communication_reply' => $this->responderComunicado($aluno, $extra['communication_id'] ?? 0, (string) ($extra['message'] ?? '')),
            'calendar' => $this->colecao($this->calendario($aluno)),
            'calendar_read' => $this->marcarEvento($aluno, $extra['event_id'] ?? 0),
            default => $this->erro(404, 'not_found', 'Recurso não encontrado.'),
        };
    }

    /** @return array{status:int,body:array} */
    public function notificacoes(): array
    {
        $itens = [$this->notificacao(false)];

        return [
            'status' => 200,
            'body' => [
                'data' => $itens,
                'meta' => ['count' => count($itens), 'unread_count' => 1],
            ],
        ];
    }

    /** @return array{status:int,body:array} */
    public function notificacaoLida($id): array
    {
        if ((int) $id !== 601) {
            return $this->erro(404, 'notification_not_found', 'Notificação não encontrada.');
        }

        $item = $this->notificacao(true);

        return $this->ok([
            'id' => $item['id'],
            'is_read' => true,
            'read_at' => '2026-10-13T10:35:00-03:00',
        ]);
    }

    /** @return array<string,mixed> */
    public function exemplo(string $chave): array
    {
        $aluno = 101;

        return match ($chave) {
            'login' => $this->login(self::EMAIL, self::SENHA)['body'],
            'refresh' => $this->renovar(self::REFRESH)['body'],
            'logout' => $this->sair()['body'],
            'me' => $this->eu()['body'],
            'students' => $this->alunos()['body'],
            'notifications' => $this->notificacoes()['body'],
            'notification_read' => $this->notificacaoLida(601)['body'],
            default => $this->recursoAluno($chave, $aluno, [
                'plan_id' => 501,
                'communication_id' => 801,
                'event_id' => 701,
                'source' => 'charge',
                'invoice_id' => 901,
                'message' => 'Confirmado, Ana estará presente.',
            ])['body'],
        };
    }

    /** @return array<string,mixed> */
    private function responsavel(): array
    {
        return [
            'id' => 1,
            'name' => 'Mariana Souza',
            'email' => self::EMAIL,
            'cpf' => '12345678909',
            'phone' => '11999990000',
            'must_change_password' => false,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function catalogoAlunos(): array
    {
        return [
            101 => [
                'id' => 101,
                'name' => 'Ana Souza',
                'photo_url' => null,
                'class' => ['id' => 7, 'name' => '7º A', 'grade' => '7º ano'],
            ],
            102 => [
                'id' => 102,
                'name' => 'Pedro Souza',
                'photo_url' => null,
                'class' => ['id' => 5, 'name' => '5º B', 'grade' => '5º ano'],
            ],
        ];
    }

    /** @param array<string,mixed> $aluno */
    private function painel(array $aluno): array
    {
        $ana = (int) $aluno['id'] === 101;

        return [
            'average_grade' => $ana ? 8.4 : 7.1,
            'essay_average' => $ana ? 820.0 : null,
            'total_exams' => $ana ? 2 : 1,
            'total_exercises' => $ana ? 18 : 6,
            'total_essays' => $ana ? 1 : 0,
            'last_activity_at' => $ana ? '2026-10-10T14:30:00-03:00' : '2026-10-09T11:00:00-03:00',
            'next_exam' => [
                'id' => $ana ? 301 : 302,
                'title' => $ana ? 'Prova de Matemática — 3º bimestre' : 'Prova de História — 3º bimestre',
                'subject_name' => $ana ? 'Matemática' : 'História',
                'date' => '2026-10-20T08:00:00-03:00',
            ],
            'access_status' => [
                'kind' => 'entrada',
                'at' => '2026-10-13T07:12:00-03:00',
                'is_at_school' => true,
            ],
            'recent_activity' => [
                [
                    'type' => 'exam',
                    'title' => $ana ? 'Prova finalizada: Ciências' : 'Prova finalizada: Geografia',
                    'occurred_at' => '2026-10-08T10:00:00-03:00',
                    'route' => '/exams',
                    'metadata' => ['exam_id' => $ana ? 310 : 311],
                ],
            ],
        ];
    }

    /** @param array<string,mixed> $aluno @return list<array<string,mixed>> */
    private function provas(array $aluno): array
    {
        $ana = (int) $aluno['id'] === 101;

        return [[
            'id' => $ana ? 310 : 311,
            'title' => $ana ? 'Ciências — 3º bimestre' : 'Geografia — 3º bimestre',
            'realization_id' => $ana ? 910 : 911,
            'subject_name' => $ana ? 'Ciências' : 'Geografia',
            'grade' => $ana ? 8.5 : 7.0,
            'status' => 'finalizado',
            'question_count' => 10,
            'correct_count' => $ana ? 8 : 7,
            'incorrect_count' => $ana ? 2 : 3,
            'pending_count' => 0,
            'accuracy_percent' => $ana ? 80.0 : 70.0,
            'completed_at' => '2026-10-08T10:00:00-03:00',
            'block_id' => 21,
            'block_title' => 'Bloco 3º bimestre',
            'block_model_id' => 3,
            'block_model_name' => 'Bimestral',
            'block_date' => '2026-10-08T00:00:00-03:00',
            'term' => '3',
            'school_year' => 2026,
        ]];
    }

    /** @param array<string,mixed> $aluno */
    private function exercicios(array $aluno): array
    {
        $ana = (int) $aluno['id'] === 101;

        return [
            'total_completed' => $ana ? 18 : 6,
            'accuracy_percent' => $ana ? 77.8 : 66.7,
            'latest' => [[
                'id' => $ana ? 1 : 2,
                'journey_id' => 401,
                'journey_title' => $ana ? 'Frações' : 'Mapas do Brasil',
                'score' => $ana ? 8.0 : 6.5,
                'completed_at' => '2026-10-07T16:20:00-03:00',
            ]],
        ];
    }

    /** @param array<string,mixed> $aluno @return list<array<string,mixed>> */
    private function jornadas(array $aluno): array
    {
        $ana = (int) $aluno['id'] === 101;

        return [[
            'id' => 401,
            'title' => $ana ? 'Frações' : 'Mapas do Brasil',
            'status' => 'active',
            'teacher_name' => $ana ? 'Helena Lima' : 'Carlos Dias',
            'subject_name' => $ana ? 'Matemática' : 'Geografia',
            'total_modules' => 4,
            'completed_modules' => $ana ? 3 : 1,
            'started' => true,
            'completed' => false,
            'progress_percent' => $ana ? 75.0 : 25.0,
            'created_at' => '2026-08-04T09:00:00-03:00',
        ]];
    }

    /** @param array<string,mixed> $aluno @return list<array<string,mixed>> */
    private function planos(array $aluno): array
    {
        $ana = (int) $aluno['id'] === 101;

        return [[
            'id' => $ana ? 501 : 502,
            'title' => $ana ? 'Equações do 1º grau' : 'Relevo brasileiro',
            'objectives' => $ana ? 'Resolver equações simples.' : 'Identificar formas de relevo.',
            'teacher_name' => $ana ? 'Helena Lima' : 'Carlos Dias',
            'subject_name' => $ana ? 'Matemática' : 'Geografia',
            'created_at' => '2026-10-01T08:00:00-03:00',
        ]];
    }

    /** @param array<string,mixed> $aluno @return array{status:int,body:array} */
    private function plano(array $aluno, $planId): array
    {
        $esperado = (int) $aluno['id'] === 101 ? 501 : 502;
        if ((int) $planId !== $esperado) {
            return $this->erro(404, 'lesson_plan_not_found', 'Plano de aula não encontrado.');
        }

        $resumo = $this->planos($aluno)[0];

        return $this->ok($resumo + [
            'class' => $aluno['class'],
            'class_dates' => ['2026-10-15'],
            'school_days' => 'quarta',
            'content' => 'Aula ilustrativa do ambiente de homologação. Nenhum dado real de aluno.',
        ]);
    }

    /** @param array<string,mixed> $aluno @return list<array<string,mixed>> */
    private function jornadasRedacao(array $aluno): array
    {
        if ((int) $aluno['id'] !== 101) {
            return [];
        }

        return [[
            'id' => 451,
            'journey_id' => 401,
            'journey_title' => 'Redação do 3º bimestre',
            'theme' => 'O papel da escola na vida digital',
            'description' => 'Texto dissertativo de até 30 linhas.',
            'due_at' => '2026-10-25T23:59:00-03:00',
            'submission_status' => 'entregue',
            'grade' => 820.0,
        ]];
    }

    /** @param array<string,mixed> $aluno @return list<array<string,mixed>> */
    private function redacoes(array $aluno): array
    {
        if ((int) $aluno['id'] !== 101) {
            return [];
        }

        return [[
            'id' => 551,
            'journey_id' => 401,
            'theme' => 'O papel da escola na vida digital',
            'grade' => 820.0,
            'feedback' => 'Boa argumentação. Falta desenvolver o segundo parágrafo.',
            'is_draft' => false,
            'created_at' => '2026-10-06T19:10:00-03:00',
            'corrected_at' => '2026-10-09T15:00:00-03:00',
        ]];
    }

    /** @param array<string,mixed> $aluno @return list<array<string,mixed>> */
    private function avisos(array $aluno): array
    {
        return [[
            'id' => (int) $aluno['id'] === 101 ? 651 : 652,
            'title' => 'Reunião de pais',
            'content' => 'Encontro ilustrativo no dia 22/10, às 19h.',
            'published_at' => '2026-10-05T12:00:00-03:00',
            'author_name' => 'Coordenação',
            'subject_name' => null,
        ]];
    }

    /** @param array<string,mixed> $aluno */
    private function boletim(array $aluno): array
    {
        $ana = (int) $aluno['id'] === 101;

        return [
            'illustrative' => true,
            'school_year' => 2026,
            'term' => '3º bimestre',
            'subjects' => [
                ['name' => $ana ? 'Matemática' : 'História', 'grade' => $ana ? 8.5 : 7.2, 'absences' => 1],
                ['name' => $ana ? 'Português' : 'Geografia', 'grade' => $ana ? 9.0 : 7.0, 'absences' => 0],
            ],
        ];
    }

    /** @param array<string,mixed> $aluno */
    private function acessos(array $aluno): array
    {
        return [
            'status' => [
                'kind' => 'entrada',
                'at' => '2026-10-13T07:12:00-03:00',
                'is_at_school' => true,
            ],
            'events' => [[
                'id' => (int) $aluno['id'] === 101 ? 1 : 2,
                'kind' => 'entrada',
                'event_at' => '2026-10-13T07:12:00-03:00',
                'confidence' => 0.98,
                'notified' => true,
            ]],
        ];
    }

    /** @param array<string,mixed> $aluno */
    private function financeiro(array $aluno): array
    {
        $faturas = (int) $aluno['id'] === 101 ? [$this->fatura()] : [];

        return [
            'summary' => [
                'pending_count' => count($faturas),
                'pending_amount' => $faturas === [] ? 0.0 : 890.0,
                'overdue_amount' => 0.0,
                'contracts_count' => (int) $aluno['id'] === 101 ? 1 : 0,
            ],
            'invoices' => $faturas,
            'contracts' => (int) $aluno['id'] === 101 ? [[
                'id' => 77,
                'status' => 'assinado',
                'payment_plan' => 'mensal',
                'school_year' => '2026',
                'net_amount' => 10680.0,
                'installments_count' => 12,
            ]] : [],
            'enrollments' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function fatura(): array
    {
        return [
            'id' => 901,
            'source' => 'charge',
            'contract_id' => 77,
            'installment_number' => 10,
            'category' => 'mensalidade',
            'description' => 'Mensalidade de outubro',
            'amount' => 890.0,
            'paid_amount' => null,
            'due_date' => '2026-10-15',
            'paid_at' => null,
            'status' => 'pendente',
            'barcode' => null,
            'notes' => 'Cobrança fictícia de homologação.',
        ];
    }

    /** @param array<string,mixed> $aluno @return array{status:int,body:array} */
    private function pagamento(array $aluno, string $source, $invoiceId): array
    {
        if ((int) $aluno['id'] !== 101 || $source !== 'charge' || (int) $invoiceId !== 901) {
            return $this->erro(404, 'invoice_not_found', 'Fatura não encontrada.');
        }

        $fatura = $this->fatura();

        return $this->ok([
            'invoice' => $fatura,
            'payment' => [
                'simulation' => true,
                'amount' => 890.0,
                'due_date' => '2026-10-15',
                'digitable_line' => '34191.79001 01043.510047 91020.150008 8 12340000089000',
                'pix_copy_paste' => '00020126580014BR.GOV.BCB.PIX0136homologacao@educatudo.local520400005303986540589.005802BR5925EDUCATUDO HOMOLOGACAO6009SAO PAULO62070503***6304ABCD',
            ],
        ]);
    }

    /** @param array<string,mixed> $aluno @return list<array<string,mixed>> */
    private function listaComunicados(array $aluno): array
    {
        $ana = (int) $aluno['id'] === 101;

        return [[
            'id' => $ana ? 801 : 802,
            'title' => $ana ? 'Passeio ao museu' : 'Feira de ciências',
            'content' => $ana
                ? 'Saída ilustrativa no dia 18/10. Autorização fictícia.'
                : 'Apresentação ilustrativa no dia 24/10.',
            'priority' => 'normal',
            'allow_replies' => true,
            'audience' => 'turmas',
            'is_read' => false,
            'read_at' => null,
            'published_at' => '2026-10-04T09:30:00-03:00',
            'expires_at' => null,
            'reply_count' => 0,
        ]];
    }

    /** @param array<string,mixed> $aluno @return array{status:int,body:array} */
    private function comunicados(array $aluno): array
    {
        $itens = $this->listaComunicados($aluno);

        return [
            'status' => 200,
            'body' => [
                'data' => $itens,
                'meta' => ['count' => count($itens), 'unread_count' => 1],
            ],
        ];
    }

    /** @param array<string,mixed> $aluno @return array{status:int,body:array} */
    private function comunicado(array $aluno, $communicationId, bool $completo): array
    {
        $item = $this->listaComunicados($aluno)[0];
        if ((int) $communicationId !== (int) $item['id']) {
            return $this->erro(404, 'communication_not_found', 'Comunicação não encontrada.');
        }
        if (!$completo) {
            return $this->ok($item);
        }

        return $this->ok($item);
    }

    /** @param array<string,mixed> $aluno @return array{status:int,body:array} */
    private function marcarComunicado(array $aluno, $communicationId): array
    {
        $checagem = $this->comunicado($aluno, $communicationId, false);
        if ($checagem['status'] !== 200) {
            return $checagem;
        }

        return $this->ok([
            'is_read' => true,
            'read_at' => '2026-10-13T10:35:00-03:00',
        ]);
    }

    /** @param array<string,mixed> $aluno @return array{status:int,body:array} */
    private function responderComunicado(array $aluno, $communicationId, string $mensagem): array
    {
        $checagem = $this->comunicado($aluno, $communicationId, false);
        if ($checagem['status'] !== 200) {
            return $checagem;
        }
        $mensagem = trim($mensagem);
        if ($mensagem === '') {
            return $this->erro(400, 'invalid_message', 'Informe a mensagem da resposta.');
        }

        return $this->ok([
            'id' => 8801,
            'message' => $mensagem,
            'created_at' => '2026-10-13T10:36:00-03:00',
        ]);
    }

    /** @param array<string,mixed> $aluno @return list<array<string,mixed>> */
    private function calendario(array $aluno): array
    {
        return [[
            'id' => 701,
            'title' => 'Conselho de classe',
            'description' => 'Evento fictício da homologação.',
            'starts_at' => '2026-10-22T19:00:00-03:00',
            'ends_at' => '2026-10-22T21:00:00-03:00',
            'status' => 'publicado',
            'is_read' => false,
            'student_id' => (int) $aluno['id'],
        ]];
    }

    /** @param array<string,mixed> $aluno @return array{status:int,body:array} */
    private function marcarEvento(array $aluno, $eventId): array
    {
        if ((int) $eventId !== 701) {
            return $this->erro(404, 'event_not_found', 'Evento não encontrado.');
        }

        return $this->ok([
            'is_read' => true,
            'read_at' => '2026-10-13T10:35:00-03:00',
            'student_id' => (int) $aluno['id'],
        ]);
    }

    /** @return array<string,mixed> */
    private function notificacao(bool $lida): array
    {
        return [
            'id' => 601,
            'title' => 'Novo comunicado',
            'message' => 'Há um comunicado ilustrativo sobre o passeio ao museu.',
            'url' => null,
            'is_delivered' => true,
            'delivered_at' => '2026-10-04T09:31:00-03:00',
            'is_read' => $lida,
            'read_at' => $lida ? '2026-10-13T10:35:00-03:00' : null,
        ];
    }

    /** @param list<array<string,mixed>> $itens @return array{status:int,body:array} */
    private function colecao(array $itens): array
    {
        return [
            'status' => 200,
            'body' => [
                'data' => $itens,
                'meta' => ['count' => count($itens)],
            ],
        ];
    }

    /** @param array<string,mixed> $data @return array{status:int,body:array} */
    private function ok(array $data): array
    {
        return ['status' => 200, 'body' => ['data' => $data]];
    }

    /** @return array{status:int,body:array} */
    private function erro(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => ['error' => ['code' => $code, 'message' => $message]],
        ];
    }
}
