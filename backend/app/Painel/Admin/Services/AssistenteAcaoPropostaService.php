<?php

namespace App\Painel\Admin\Services;

use App\Core\AdminPermissionMatrix;
use App\Core\Database;
use App\Core\FeatureGate;
use App\Infra\Services\FirebaseMessagingService;
use App\Infra\Services\MobileDeviceService;
use App\Modulos\Diario\Models\ClassDiary;
use App\Modulos\Diario\Services\ClassDiaryService;
use App\Modulos\Notificacoes\Models\PushNotification;
use App\Modulos\Ocorrencias\Models\Ocorrencia;
use App\Modulos\Ocorrencias\Services\OcorrenciaService;
use App\Modulos\Presenca\Services\PresencaEventoService;
use App\Modulos\Provas\Services\ProvasAlunoConsultaService;
use App\Painel\Admin\Models\AssistenteProposta;

/**
 * Propostas tipadas de escrita do Assistente Admin.
 * Nada grava de verdade sem confirmar (confirmar=1) do próprio autor.
 */
class AssistenteAcaoPropostaService
{
    private const SITUACOES_CHAMADA = [
        'presente',
        'falta',
        'falta_justificada',
        'atraso',
        'saida_antecipada',
    ];

    private AssistenteProposta $propostas;
    private ProvasAlunoConsultaService $consulta;
    private OcorrenciaService $ocorrencias;
    private Ocorrencia $ocorrenciaModel;
    private PresencaEventoService $presenca;
    private ClassDiaryService $diario;
    private ClassDiary $diarioModel;
    private $db;

    public function __construct(
        ?AssistenteProposta $propostas = null,
        ?ProvasAlunoConsultaService $consulta = null,
        ?OcorrenciaService $ocorrencias = null,
        ?Ocorrencia $ocorrenciaModel = null,
        ?PresencaEventoService $presenca = null,
        ?ClassDiaryService $diario = null,
        ?ClassDiary $diarioModel = null
    ) {
        $this->propostas = $propostas ?? new AssistenteProposta();
        $this->consulta = $consulta ?? new ProvasAlunoConsultaService();
        $this->ocorrencias = $ocorrencias ?? new OcorrenciaService();
        $this->ocorrenciaModel = $ocorrenciaModel ?? new Ocorrencia();
        $this->presenca = $presenca ?? new PresencaEventoService();
        $this->diario = $diario ?? new ClassDiaryService();
        $this->diarioModel = $diarioModel ?? new ClassDiary();
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        return $this->propostas->tabelasProntas();
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function proporCriarOcorrencia(array $args, int $usuarioId, int $conversaId = 0): array
    {
        if (!$this->propostas->tabelasProntas()) {
            return ['ok' => false, 'error' => 'Rode a migration 2026_11_12_assistente_propostas para habilitar ações de escrita.'];
        }
        if ($usuarioId <= 0) {
            return ['ok' => false, 'error' => 'Usuário não autenticado para gravar proposta.'];
        }

        $resolucao = $this->resolverAlunosArgs($args);
        if (!empty($resolucao['candidatos'])) {
            return [
                'ok' => false,
                'aviso' => $resolucao['aviso'] ?? 'Há mais de um aluno. Escolha pelo id:',
                'candidatos' => $resolucao['candidatos'],
            ];
        }
        if ($resolucao['alunos'] === []) {
            return ['ok' => false, 'error' => $resolucao['error'] ?? 'Informe ao menos um aluno (nome+turma, RA ou aluno_id).'];
        }

        $titulo = trim((string) ($args['titulo'] ?? ''));
        $detalhe = trim((string) ($args['detalhe'] ?? $args['descricao'] ?? ''));
        $nivel = strtolower(trim((string) ($args['nivel_gravidade'] ?? $args['gravidade'] ?? '')));
        $data = trim((string) ($args['data_ocorrencia'] ?? $args['data'] ?? date('Y-m-d H:i:s')));
        if ($titulo === '' || $detalhe === '' || $nivel === '') {
            return ['ok' => false, 'error' => 'Informe titulo, detalhe e nivel_gravidade (leve|moderado|grave).'];
        }
        if (!isset(Ocorrencia::GRAVIDADES[$nivel])) {
            return ['ok' => false, 'error' => 'Gravidade inválida. Use leve, moderado ou grave.'];
        }
        if (strtotime($data) === false) {
            return ['ok' => false, 'error' => 'Data da ocorrência inválida.'];
        }

        $categoriaId = (int) ($args['categoria_id'] ?? 0);
        $categoriaNome = trim((string) ($args['categoria'] ?? $args['categoria_nome'] ?? ''));
        if ($this->ocorrenciaModel->schemaEstendido()) {
            if ($categoriaId <= 0 && $categoriaNome !== '') {
                $categoriaId = $this->resolverCategoriaId($categoriaNome);
            }
            if ($categoriaId <= 0 || !$this->ocorrenciaModel->categoriaExiste($categoriaId)) {
                $cats = $this->ocorrenciaModel->categorias(true);
                $lista = array_map(static fn($c) => [
                    'id' => (int) $c['id'],
                    'nome' => (string) ($c['nome'] ?? ''),
                ], $cats);
                // Rascunho com 1ª categoria ativa — o card Confirmar aparece; usuário revisa antes de gravar.
                if (count($lista) === 1 || (count($lista) > 1 && empty($args['exigir_categoria']))) {
                    $categoriaId = (int) ($lista[0]['id'] ?? 0);
                }
                if ($categoriaId <= 0 || !$this->ocorrenciaModel->categoriaExiste($categoriaId)) {
                    return [
                        'ok' => false,
                        'aviso' => 'Escolha a categoria da ocorrência:',
                        'candidatos_categorias' => $lista,
                        'error' => 'Informe categoria_id válida.',
                    ];
                }
            }
        } else {
            $categoriaId = 0;
        }

        // Push/portal desligados por padrão — só liga se o usuário pedir explicitamente.
        $notificar = $this->boolArg($args['enviar_pais'] ?? $args['notificar_push'] ?? $args['notificar_pais'] ?? false);
        $payload = [
            'alunos' => $resolucao['alunos'],
            'titulo' => mb_substr($titulo, 0, 120),
            'detalhe' => $detalhe,
            'nivel_gravidade' => $nivel,
            'data_ocorrencia' => date('Y-m-d H:i:s', strtotime($data)),
            'categoria_id' => $categoriaId > 0 ? $categoriaId : null,
            'local' => mb_substr(trim((string) ($args['local'] ?? '')), 0, 120),
            'encaminhamento' => trim((string) ($args['encaminhamento'] ?? '')),
            'enviar_pais' => $notificar ? 1 : 0,
            'notificar_push' => $notificar ? 1 : 0,
            'turma' => trim((string) ($args['turma'] ?? $args['turma_nome'] ?? '')),
        ];

        $id = $this->propostas->criar($usuarioId, 'criar_ocorrencia', $payload, $conversaId);
        if ($id === null) {
            return ['ok' => false, 'error' => 'Não foi possível gravar o rascunho da proposta.'];
        }

        return [
            'ok' => true,
            'proposta_id' => $id,
            'tipo' => 'criar_ocorrencia',
            'resumo' => $this->resumoOcorrencia($payload),
            'detalhes' => $this->detalhesProposta('criar_ocorrencia', $payload),
            'payload' => $payload,
            'situacao' => 'RASCUNHO — nada foi gravado. Confirme abaixo.',
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function proporEntradaSaida(array $args, int $usuarioId, int $conversaId = 0): array
    {
        if (!$this->propostas->tabelasProntas()) {
            return ['ok' => false, 'error' => 'Rode a migration 2026_11_12_assistente_propostas para habilitar ações de escrita.'];
        }
        if ($usuarioId <= 0) {
            return ['ok' => false, 'error' => 'Usuário não autenticado para gravar proposta.'];
        }

        $tipo = strtolower(trim((string) ($args['tipo'] ?? $args['evento'] ?? '')));
        if (!in_array($tipo, ['entrada', 'saida'], true)) {
            return ['ok' => false, 'error' => 'Informe tipo=entrada ou tipo=saida.'];
        }

        $resolucao = $this->resolverAlunosArgs($args);
        if (!empty($resolucao['candidatos'])) {
            return [
                'ok' => false,
                'aviso' => $resolucao['aviso'] ?? 'Há mais de um aluno. Escolha pelo id:',
                'candidatos' => $resolucao['candidatos'],
            ];
        }
        if ($resolucao['alunos'] === []) {
            return ['ok' => false, 'error' => $resolucao['error'] ?? 'Informe o aluno (nome+turma, RA ou aluno_id).'];
        }

        $quando = trim((string) ($args['ocorrido_em'] ?? $args['horario'] ?? $args['data'] ?? ''));
        try {
            $dt = $quando !== '' ? new \DateTimeImmutable($quando) : new \DateTimeImmutable('now');
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Data/horário inválidos.'];
        }

        $payload = [
            'alunos' => $resolucao['alunos'],
            'tipo' => $tipo,
            'ocorrido_em' => $dt->format('Y-m-d H:i:s'),
        ];
        $id = $this->propostas->criar($usuarioId, 'entrada_saida', $payload, $conversaId);
        if ($id === null) {
            return ['ok' => false, 'error' => 'Não foi possível gravar o rascunho da proposta.'];
        }

        return [
            'ok' => true,
            'proposta_id' => $id,
            'tipo' => 'entrada_saida',
            'resumo' => $this->resumoEntradaSaida($payload),
            'detalhes' => $this->detalhesProposta('entrada_saida', $payload),
            'payload' => $payload,
            'situacao' => 'RASCUNHO — nada foi gravado. Confirme abaixo.',
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function proporChamadaAula(array $args, int $usuarioId, int $conversaId = 0): array
    {
        if (!$this->propostas->tabelasProntas()) {
            return ['ok' => false, 'error' => 'Rode a migration 2026_11_12_assistente_propostas para habilitar ações de escrita.'];
        }
        if ($usuarioId <= 0) {
            return ['ok' => false, 'error' => 'Usuário não autenticado para gravar proposta.'];
        }

        $situacao = strtolower(trim((string) ($args['situacao'] ?? $args['presenca'] ?? 'presente')));
        if ($situacao === 'falta justificada') {
            $situacao = 'falta_justificada';
        }
        if (!in_array($situacao, self::SITUACOES_CHAMADA, true)) {
            return ['ok' => false, 'error' => 'Situação inválida. Use presente, falta, atraso, falta_justificada ou saida_antecipada.'];
        }

        $resolucao = $this->resolverAlunosArgs($args);
        if (!empty($resolucao['candidatos'])) {
            return [
                'ok' => false,
                'aviso' => $resolucao['aviso'] ?? 'Há mais de um aluno. Escolha pelo id:',
                'candidatos' => $resolucao['candidatos'],
            ];
        }
        if ($resolucao['alunos'] === []) {
            return ['ok' => false, 'error' => $resolucao['error'] ?? 'Informe o aluno (nome+turma, RA ou aluno_id).'];
        }

        $aulaRes = $this->resolverAula($args, $resolucao['alunos']);
        if (!empty($aulaRes['candidatos'])) {
            return [
                'ok' => false,
                'aviso' => $aulaRes['aviso'] ?? 'Há mais de uma aula. Informe diario_aula_id:',
                'candidatos_aulas' => $aulaRes['candidatos'],
            ];
        }
        if (empty($aulaRes['aula'])) {
            return ['ok' => false, 'error' => $aulaRes['error'] ?? 'Informe diario_aula_id ou turma+data (+ matéria).'];
        }
        $aula = $aulaRes['aula'];

        $payload = [
            'alunos' => $resolucao['alunos'],
            'diario_aula_id' => (int) $aula['id'],
            'aula' => [
                'id' => (int) $aula['id'],
                'data_aula' => (string) ($aula['data_aula'] ?? ''),
                'turma_nome' => (string) ($aula['turma_nome'] ?? ''),
                'materia_nome' => (string) ($aula['materia_nome'] ?? ''),
                'horario_de' => (string) ($aula['horario_de'] ?? ''),
                'horario_ate' => (string) ($aula['horario_ate'] ?? ''),
            ],
            'situacao' => $situacao,
            'observacao' => trim((string) ($args['observacao'] ?? '')),
        ];
        $id = $this->propostas->criar($usuarioId, 'chamada_aula', $payload, $conversaId);
        if ($id === null) {
            return ['ok' => false, 'error' => 'Não foi possível gravar o rascunho da proposta.'];
        }

        return [
            'ok' => true,
            'proposta_id' => $id,
            'tipo' => 'chamada_aula',
            'resumo' => $this->resumoChamada($payload),
            'detalhes' => $this->detalhesProposta('chamada_aula', $payload),
            'payload' => $payload,
            'situacao' => 'RASCUNHO — nada foi gravado. Confirme abaixo.',
        ];
    }

    /**
     * Campos fixos para o card de confirmação (título, descrição, data/hora, envolvidos).
     *
     * @param array<string,mixed> $payload
     * @return array{
     *   tipo_label:string,
     *   titulo:string,
     *   descricao:string,
     *   data_hora:string,
     *   data_hora_label:string,
     *   envolvidos:list<string>,
     *   extras?:list<array{label:string,valor:string}>
     * }
     */
    public function detalhesProposta(string $tipo, array $payload): array
    {
        $envolvidos = [];
        foreach ((array) ($payload['alunos'] ?? []) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $nome = trim((string) ($a['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $turma = trim((string) ($a['turma_nome'] ?? ''));
            $ra = trim((string) ($a['ra'] ?? ''));
            $rotulo = $nome;
            if ($turma !== '') {
                $rotulo .= ' (' . $turma . ')';
            }
            if ($ra !== '') {
                $rotulo .= ' · RA ' . $ra;
            }
            $envolvidos[] = $rotulo;
        }

        if ($tipo === 'criar_ocorrencia') {
            $quandoRaw = (string) ($payload['data_ocorrencia'] ?? '');
            $grav = Ocorrencia::GRAVIDADES[(string) ($payload['nivel_gravidade'] ?? '')]
                ?? (string) ($payload['nivel_gravidade'] ?? '');
            $extras = [];
            if ($grav !== '') {
                $extras[] = ['label' => 'Gravidade', 'valor' => $grav];
            }
            $local = trim((string) ($payload['local'] ?? ''));
            if ($local !== '') {
                $extras[] = ['label' => 'Local', 'valor' => $local];
            }
            $extras[] = [
                'label' => 'Notificar responsáveis',
                'valor' => !empty($payload['notificar_push']) || !empty($payload['enviar_pais']) ? 'Sim' : 'Não',
            ];
            return [
                'tipo_label' => 'Ocorrência',
                'titulo' => trim((string) ($payload['titulo'] ?? 'Ocorrência')),
                'descricao' => trim((string) ($payload['detalhe'] ?? '')),
                'data_hora' => $quandoRaw,
                'data_hora_label' => $this->formatarDataHoraPt($quandoRaw),
                'envolvidos' => $envolvidos,
                'extras' => $extras,
            ];
        }

        if ($tipo === 'entrada_saida') {
            $rotulo = (($payload['tipo'] ?? '') === 'saida') ? 'Saída' : 'Entrada';
            $quandoRaw = (string) ($payload['ocorrido_em'] ?? '');
            return [
                'tipo_label' => 'Portaria · ' . $rotulo,
                'titulo' => 'Registrar ' . mb_strtolower($rotulo),
                'descricao' => 'Registro de ' . mb_strtolower($rotulo) . ' na portaria/presença.',
                'data_hora' => $quandoRaw,
                'data_hora_label' => $this->formatarDataHoraPt($quandoRaw),
                'envolvidos' => $envolvidos,
                'extras' => [],
            ];
        }

        if ($tipo === 'chamada_aula') {
            $aula = is_array($payload['aula'] ?? null) ? $payload['aula'] : [];
            $dataAula = trim((string) ($aula['data_aula'] ?? ''));
            $horaDe = trim((string) ($aula['horario_de'] ?? ''));
            $horaAte = trim((string) ($aula['horario_ate'] ?? ''));
            $quandoRaw = $dataAula;
            $quandoLabel = $this->formatarDataHoraPt($dataAula);
            if ($horaDe !== '') {
                $faixa = substr($horaDe, 0, 5);
                if ($horaAte !== '') {
                    $faixa .= '–' . substr($horaAte, 0, 5);
                }
                $quandoLabel = ($quandoLabel !== '—' ? $quandoLabel . ' · ' : '') . $faixa;
                $quandoRaw = trim($dataAula . ' ' . $horaDe);
            }
            $materia = trim((string) ($aula['materia_nome'] ?? ''));
            $turma = trim((string) ($aula['turma_nome'] ?? ''));
            $situacao = trim((string) ($payload['situacao'] ?? 'presente'));
            $obs = trim((string) ($payload['observacao'] ?? ''));
            $extras = [];
            if ($turma !== '') {
                $extras[] = ['label' => 'Turma', 'valor' => $turma];
            }
            if ($materia !== '') {
                $extras[] = ['label' => 'Matéria', 'valor' => $materia];
            }
            $extras[] = ['label' => 'Situação', 'valor' => str_replace('_', ' ', $situacao)];
            return [
                'tipo_label' => 'Chamada da aula',
                'titulo' => 'Lançar ' . str_replace('_', ' ', $situacao) . ' na chamada',
                'descricao' => $obs !== '' ? $obs : 'Confirme o lançamento da presença/falta na aula.',
                'data_hora' => $quandoRaw,
                'data_hora_label' => $quandoLabel,
                'envolvidos' => $envolvidos,
                'extras' => $extras,
            ];
        }

        return [
            'tipo_label' => 'Proposta',
            'titulo' => 'Ação pendente',
            'descricao' => '',
            'data_hora' => '',
            'data_hora_label' => '',
            'envolvidos' => $envolvidos,
            'extras' => [],
        ];
    }

    private function formatarDataHoraPt(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '—';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        $temHora = preg_match('/\d{1,2}:\d{2}/', $raw) === 1;
        return $temHora
            ? date('d/m/Y H:i', $ts)
            : date('d/m/Y', $ts);
    }

    /**
     * Confirma a proposta pendente mais recente da conversa (ou do usuário, se a conversa não achar).
     *
     * @param array<string,mixed> $usuario
     * @return array{success:bool,error?:string,mensagem?:string,resultado?:array<string,mixed>}
     */
    public function confirmarPendenteNaConversa(int $usuarioId, int $conversaId, array $usuario): array
    {
        $pendente = $this->propostas->encontrarPendente($usuarioId, $conversaId);
        if ($pendente === null) {
            return [
                'success' => false,
                'error' => 'Não há proposta pendente para confirmar. Peça a ação de novo e use o botão Confirmar, ou diga "sim" logo após a proposta.',
            ];
        }
        return $this->confirmar((int) $pendente['id'], $usuarioId, $usuario);
    }

    public function confirmar(int $propostaId, int $usuarioId, array $usuario): array
    {
        $row = $this->propostas->findDoUsuario($propostaId, $usuarioId);
        if (!$row) {
            return ['success' => false, 'error' => 'Proposta não encontrada.'];
        }
        $estado = (int) ($row['confirmado'] ?? 0);
        if ($estado === 1) {
            return ['success' => false, 'error' => 'Esta proposta já foi confirmada.'];
        }
        if ($estado === 2) {
            return ['success' => false, 'error' => 'Esta proposta já está sendo aplicada.'];
        }
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            return ['success' => false, 'error' => 'Payload da proposta inválido.'];
        }
        $tipo = (string) ($row['tipo'] ?? '');

        $perm = $this->checarPermissao($tipo, $usuario);
        if ($perm !== null) {
            return ['success' => false, 'error' => $perm];
        }

        if (!$this->propostas->reivindicar($propostaId, $usuarioId)) {
            return ['success' => false, 'error' => 'Esta proposta já foi confirmada ou está em processamento.'];
        }

        try {
            switch ($tipo) {
                case 'criar_ocorrencia':
                    $resultado = $this->aplicarOcorrencia($payload, $usuarioId);
                    break;
                case 'entrada_saida':
                    $resultado = $this->aplicarEntradaSaida($payload, $usuarioId, $propostaId);
                    break;
                case 'chamada_aula':
                    $resultado = $this->aplicarChamada($payload);
                    break;
                default:
                    $this->propostas->liberarClaim($propostaId, $usuarioId, 'Tipo desconhecido');
                    return ['success' => false, 'error' => 'Tipo de proposta desconhecido.'];
            }
        } catch (\Throwable $e) {
            error_log('AssistenteAcaoPropostaService::confirmar falhou — ' . $e->getMessage());
            $this->propostas->liberarClaim($propostaId, $usuarioId, 'excecao');
            return ['success' => false, 'error' => 'Não foi possível aplicar a proposta. Tente novamente.'];
        }

        if (empty($resultado['success'])) {
            $erro = (string) ($resultado['error'] ?? 'Não foi possível aplicar a proposta.');
            $this->propostas->liberarClaim($propostaId, $usuarioId, $erro);
            return ['success' => false, 'error' => $erro];
        }

        if (!$this->propostas->finalizarConfirmada($propostaId, $usuarioId, $resultado)) {
            error_log('AssistenteAcaoPropostaService: falha ao finalizar proposta #' . $propostaId);
            return ['success' => false, 'error' => 'Ação pode ter sido aplicada, mas a auditoria falhou. Não confirme de novo sem verificar.'];
        }

        return [
            'success' => true,
            'mensagem' => (string) ($resultado['mensagem'] ?? 'Proposta aplicada.'),
            'resultado' => $resultado,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function aplicarOcorrencia(array $payload, int $usuarioId): array
    {
        $alunoIds = array_values(array_map(
            static fn($a) => (int) ($a['id'] ?? 0),
            (array) ($payload['alunos'] ?? [])
        ));
        $alunoIds = array_values(array_filter($alunoIds, static fn($id) => $id > 0));
        if ($alunoIds === []) {
            return ['success' => false, 'error' => 'Nenhum aluno na proposta.'];
        }

        $input = [
            'alunos' => $alunoIds,
            'titulo' => (string) ($payload['titulo'] ?? ''),
            'detalhe' => (string) ($payload['detalhe'] ?? ''),
            'nivel_gravidade' => (string) ($payload['nivel_gravidade'] ?? ''),
            'data_ocorrencia' => (string) ($payload['data_ocorrencia'] ?? date('Y-m-d H:i:s')),
            'categoria_id' => (int) ($payload['categoria_id'] ?? 0),
            'local' => (string) ($payload['local'] ?? ''),
            'encaminhamento' => (string) ($payload['encaminhamento'] ?? ''),
            'enviar_pais' => !empty($payload['enviar_pais']) ? 1 : 0,
        ];
        $criado = $this->ocorrencias->criar($input, $usuarioId, 'admin', 0, []);
        if (empty($criado['success'])) {
            return ['success' => false, 'error' => $criado['error'] ?? 'Falha ao criar ocorrência.'];
        }

        $push = ['parents' => 0, 'sent_fcm' => 0, 'failed' => 0];
        if (!empty($payload['notificar_push'])) {
            $push = $this->enviarPushOcorrencia(
                $alunoIds,
                (string) $payload['titulo'],
                (int) $criado['id'],
                $usuarioId
            );
        }

        $nomes = array_map(static fn($a) => (string) ($a['nome'] ?? ''), (array) ($payload['alunos'] ?? []));
        return [
            'success' => true,
            'ocorrencia_id' => (int) $criado['id'],
            'push' => $push,
            'mensagem' => 'Ocorrência #' . (int) $criado['id'] . ' criada para '
                . implode(', ', array_filter($nomes)) . '.'
                . ($push['parents'] > 0
                    ? ' Push FCM: ' . (int) $push['sent_fcm'] . ' envio(s).'
                    : ''),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function aplicarEntradaSaida(array $payload, int $usuarioId, int $propostaId = 0): array
    {
        $tipo = (string) ($payload['tipo'] ?? '');
        $quando = (string) ($payload['ocorrido_em'] ?? date('Y-m-d H:i:s'));
        $aplicados = [];
        foreach ((array) ($payload['alunos'] ?? []) as $aluno) {
            $alunoId = (int) ($aluno['id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            $idExterno = $propostaId > 0
                ? 'assistente:proposta:' . $propostaId . ':aluno:' . $alunoId . ':' . $tipo
                : 'assistente:manual:' . $alunoId . ':' . $tipo . ':' . md5($quando);
            $res = $this->presenca->registrar([
                'aluno_id' => $alunoId,
                'tipo' => $tipo,
                'ocorrido_em' => $quando,
                'origem' => 'manual_secretaria',
                'id_externo' => $idExterno,
                'registrado_por' => $usuarioId,
            ]);
            $aplicados[] = [
                'aluno_id' => $alunoId,
                'nome' => (string) ($aluno['nome'] ?? ''),
                'evento_id' => (int) ($res['evento_id'] ?? 0),
                'duplicado' => !empty($res['duplicado']),
            ];
        }
        if ($aplicados === []) {
            return ['success' => false, 'error' => 'Nenhum evento registrado.'];
        }
        $rotulo = $tipo === 'saida' ? 'Saída' : 'Entrada';
        return [
            'success' => true,
            'eventos' => $aplicados,
            'mensagem' => $rotulo . ' registrada para ' . count($aplicados) . ' aluno(s).',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function aplicarChamada(array $payload): array
    {
        $aulaId = (int) ($payload['diario_aula_id'] ?? 0);
        $aula = $this->diarioModel->getAula($aulaId);
        if (!$aula) {
            return ['success' => false, 'error' => 'Aula do diário não encontrada.'];
        }
        if ((string) ($aula['status'] ?? '') === 'cancelada') {
            return ['success' => false, 'error' => 'Aula cancelada não aceita chamada pelo assistente.'];
        }
        $situacao = (string) ($payload['situacao'] ?? 'presente');
        $obsNova = trim((string) ($payload['observacao'] ?? ''));
        $existentes = $this->diarioModel->frequenciasMap($aulaId);
        $frequencias = [];
        foreach ((array) ($payload['alunos'] ?? []) as $aluno) {
            $alunoId = (int) ($aluno['id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            $atual = is_array($existentes[$alunoId] ?? null) ? $existentes[$alunoId] : [];
            $freq = [
                'situacao' => $situacao,
                'observacao' => $obsNova !== '' ? $obsNova : (string) ($atual['observacao'] ?? ''),
            ];
            // Preserva nota já lançada (salvarLancamento sobrescreve nota se omitida).
            if (isset($atual['nota']) && $atual['nota'] !== null && $atual['nota'] !== '') {
                $freq['nota'] = $atual['nota'];
            }
            $frequencias[$alunoId] = $freq;
        }
        if ($frequencias === []) {
            return ['success' => false, 'error' => 'Nenhum aluno na proposta.'];
        }

        $jaFinalizada = (string) ($aula['status'] ?? '') === 'finalizada';
        $execucao = (string) ($aula['execucao'] ?? 'conforme_planejado');
        if ($execucao === '') {
            $execucao = 'conforme_planejado';
        }
        $this->diario->salvarLancamento(
            $aulaId,
            $execucao,
            (string) ($aula['conteudo_realizado'] ?? ''),
            (string) ($aula['observacoes'] ?? ''),
            $frequencias,
            $jaFinalizada,
            []
        );

        return [
            'success' => true,
            'diario_aula_id' => $aulaId,
            'alunos' => count($frequencias),
            'mensagem' => 'Chamada atualizada (' . $situacao . ') para ' . count($frequencias)
                . ' aluno(s) na aula #' . $aulaId . '.',
        ];
    }

    /**
     * Push só via FCM (tokens vinculados a responsaveis.id).
     * OneSignal fica de fora: external_id numérico sem namespace pode colidir entre perfis/escolas.
     *
     * @param list<int> $alunoIds
     * @return array{parents:int,sent_fcm:int,failed:int}
     */
    private function enviarPushOcorrencia(
        array $alunoIds,
        string $titulo,
        int $ocorrenciaId,
        int $criadoPor
    ): array {
        $parentIds = $this->parentIdsDosAlunos($alunoIds);
        if ($parentIds === []) {
            return ['parents' => 0, 'sent_fcm' => 0, 'failed' => 0];
        }

        // Texto genérico — não envia o detalhe disciplinar no push (LGPD / revisão humana).
        $tituloPush = 'Nova ocorrência escolar';
        $mensagemPush = 'Há um registro novo sobre o aluno. Abra o app para ver os detalhes.';
        if (count($alunoIds) === 1 && $titulo !== '') {
            $mensagemPush = 'Nova ocorrência: ' . mb_substr($titulo, 0, 80) . '. Abra o app para ver.';
        }
        $notifications = new PushNotification();
        $notificationId = $notifications->create(
            $tituloPush,
            $mensagemPush,
            '/notifications',
            'pais',
            null,
            $criadoPor
        );

        $sentFcm = 0;
        $failed = 0;
        $devices = new MobileDeviceService();
        $firebase = new FirebaseMessagingService();
        $deviceRows = $devices->enabledTokensForParents($parentIds);
        $tokensByParent = [];
        foreach ($deviceRows as $device) {
            $tokensByParent[(int) $device['parent_id']][] = (string) $device['fcm_token'];
        }
        foreach ($parentIds as $parentId) {
            $tracking = $notifications->addEnvio($notificationId, $parentId, 'pai');
            $tokens = $tokensByParent[$parentId] ?? [];
            if ($tokens === []) {
                continue;
            }
            foreach ($tokens as $fcmToken) {
                $result = $firebase->sendToToken($fcmToken, $tituloPush, $mensagemPush, [
                    'tracking_token' => (string) $tracking,
                    'notificacao_id' => (string) $notificationId,
                    'type' => 'ocorrencia',
                    'ocorrencia_id' => (string) $ocorrenciaId,
                    'route' => '/notifications',
                ]);
                if (!empty($result['success'])) {
                    $sentFcm++;
                    $notifications->marcarEntregueDestinatario($notificationId, $parentId, 'pai');
                } else {
                    $failed++;
                    if (!empty($result['invalid_token'])) {
                        $devices->disableToken($fcmToken);
                    }
                }
            }
        }

        return [
            'parents' => count($parentIds),
            'sent_fcm' => $sentFcm,
            'failed' => $failed,
        ];
    }

    /**
     * @param list<int> $alunoIds
     * @return list<int>
     */
    private function parentIdsDosAlunos(array $alunoIds): array
    {
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds))));
        if ($alunoIds === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach ($alunoIds as $i => $id) {
            $k = 'a' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $in = implode(',', $ph);
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT parent_id FROM (
                SELECT a.responsavel_id AS parent_id
                  FROM alunos a
                 WHERE a.id IN ({$in}) AND a.responsavel_id IS NOT NULL
                UNION
                SELECT ar.responsavel_id AS parent_id
                  FROM alunos_responsaveis ar
                 WHERE ar.aluno_id IN ({$in}) AND ar.ativo = 1
             ) links
             INNER JOIN responsaveis r ON r.id = links.parent_id AND r.ativo = 1",
            $params
        ) ?: [];
        return array_values(array_unique(array_map('intval', array_column($rows, 'parent_id'))));
    }

    /**
     * @param array<string,mixed> $args
     * @return array{alunos:list<array{id:int,nome:string,ra:?string,turma_id:?int,turma_nome:?string}>,candidatos?:list,aviso?:string,error?:string}
     */
    private function resolverAlunosArgs(array $args): array
    {
        $turma = trim((string) ($args['turma'] ?? $args['turma_nome'] ?? ''));
        $idsDiretos = [];
        if (isset($args['aluno_id'])) {
            $idsDiretos[] = (int) $args['aluno_id'];
        }
        if (isset($args['alunos_ids']) && is_array($args['alunos_ids'])) {
            foreach ($args['alunos_ids'] as $id) {
                $idsDiretos[] = (int) $id;
            }
        }
        if (isset($args['aluno_ids']) && is_array($args['aluno_ids'])) {
            foreach ($args['aluno_ids'] as $id) {
                $idsDiretos[] = (int) $id;
            }
        }
        $idsDiretos = array_values(array_unique(array_filter($idsDiretos, static fn($id) => $id > 0)));

        $alunos = [];
        foreach ($idsDiretos as $id) {
            $row = $this->db->fetch(
                "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.id = :id AND a.ativo = 1
                 LIMIT 1",
                ['id' => $id]
            );
            if ($row) {
                $alunos[] = [
                    'id' => (int) $row['id'],
                    'nome' => (string) $row['nome'],
                    'ra' => $row['ra'] !== null ? (string) $row['ra'] : null,
                    'turma_id' => isset($row['turma_id']) ? (int) $row['turma_id'] : null,
                    'turma_nome' => $row['turma_nome'] !== null ? (string) $row['turma_nome'] : null,
                ];
            }
        }

        $nomes = [];
        if (isset($args['alunos']) && is_array($args['alunos'])) {
            foreach ($args['alunos'] as $item) {
                if (is_numeric($item)) {
                    $idsDiretos[] = (int) $item;
                    continue;
                }
                if (is_array($item)) {
                    if ((int) ($item['id'] ?? 0) > 0) {
                        $idsDiretos[] = (int) $item['id'];
                    } elseif (!empty($item['nome'])) {
                        $nomes[] = trim((string) $item['nome']);
                    }
                    continue;
                }
                $s = trim((string) $item);
                if ($s !== '') {
                    $nomes[] = $s;
                }
            }
        }
        foreach (['aluno_nome', 'nome', 'termo'] as $k) {
            $v = trim((string) ($args[$k] ?? ''));
            if ($v !== '') {
                $nomes[] = $v;
            }
        }
        if (!empty($args['ra'])) {
            $nomes[] = 'ra ' . trim((string) $args['ra']);
        }

        // Reconsulta ids que vieram misturados em alunos[]
        foreach (array_values(array_unique(array_filter($idsDiretos))) as $id) {
            if ($this->jaTemAluno($alunos, $id)) {
                continue;
            }
            $row = $this->db->fetch(
                "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.id = :id AND a.ativo = 1
                 LIMIT 1",
                ['id' => $id]
            );
            if ($row) {
                $alunos[] = [
                    'id' => (int) $row['id'],
                    'nome' => (string) $row['nome'],
                    'ra' => $row['ra'] !== null ? (string) $row['ra'] : null,
                    'turma_id' => isset($row['turma_id']) ? (int) $row['turma_id'] : null,
                    'turma_nome' => $row['turma_nome'] !== null ? (string) $row['turma_nome'] : null,
                ];
            }
        }

        $candidatos = [];
        foreach (array_unique($nomes) as $nome) {
            $encontrados = $this->consulta->buscarAlunos($nome, 20, $turma !== '' ? $turma : null);
            if ($encontrados === [] && $turma !== '') {
                $encontrados = $this->consulta->buscarAlunos($nome, 15, null);
            }
            if (count($encontrados) === 1) {
                $a = $encontrados[0];
                if (!$this->jaTemAluno($alunos, (int) $a['id'])) {
                    $alunos[] = $a;
                }
                continue;
            }
            if (count($encontrados) > 1) {
                foreach ($encontrados as $c) {
                    $candidatos[(int) $c['id']] = $c;
                }
            }
        }

        if ($candidatos !== [] && $alunos === []) {
            return [
                'alunos' => [],
                'candidatos' => array_values($candidatos),
                'aviso' => 'Há mais de um aluno com esse nome. Escolha pelo id (e informe a turma se souber):',
            ];
        }
        if ($candidatos !== [] && $alunos !== []) {
            // Alguns resolvidos, outros ambíguos — pede escolha dos ambíguos.
            return [
                'alunos' => [],
                'candidatos' => array_values($candidatos),
                'aviso' => 'Alguns nomes ficaram ambíguos. Escolha os alunos certos pelo id:',
            ];
        }
        if ($alunos === []) {
            return ['alunos' => [], 'error' => 'Nenhum aluno encontrado com os dados informados.'];
        }

        return ['alunos' => $alunos];
    }

    /**
     * @param list<array{id:int}> $alunos
     */
    private function jaTemAluno(array $alunos, int $id): bool
    {
        foreach ($alunos as $a) {
            if ((int) ($a['id'] ?? 0) === $id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $args
     * @param list<array{id:int,turma_id:?int}> $alunos
     * @return array{aula?:array<string,mixed>,candidatos?:list,aviso?:string,error?:string}
     */
    private function resolverAula(array $args, array $alunos): array
    {
        $aulaId = (int) ($args['diario_aula_id'] ?? $args['aula_id'] ?? 0);
        if ($aulaId > 0) {
            $aula = $this->diarioModel->getAula($aulaId);
            if (!$aula) {
                return ['error' => 'Aula #' . $aulaId . ' não encontrada.'];
            }
            return ['aula' => $aula];
        }

        $data = trim((string) ($args['data'] ?? $args['data_aula'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $ts = strtotime($data);
            if ($ts === false) {
                return ['error' => 'Data da aula inválida.'];
            }
            $data = date('Y-m-d', $ts);
        }

        $turmaId = (int) ($args['turma_id'] ?? 0);
        if ($turmaId <= 0 && !empty($alunos[0]['turma_id'])) {
            $turmaId = (int) $alunos[0]['turma_id'];
        }
        $materiaId = (int) ($args['materia_id'] ?? 0);
        $materiaNome = trim((string) ($args['materia'] ?? $args['materia_nome'] ?? ''));

        if ($turmaId <= 0) {
            return ['error' => 'Informe diario_aula_id ou turma do aluno + data.'];
        }

        $params = ['turma_id' => $turmaId, 'data' => $data];
        $sql = "SELECT da.id, da.data_aula, da.horario_de, da.horario_ate, da.turma_id, da.materia_id,
                       t.nome AS turma_nome, m.nome AS materia_nome, p.nome AS professor_nome
                FROM diario_aulas da
                INNER JOIN turmas t ON t.id = da.turma_id
                INNER JOIN materias m ON m.id = da.materia_id
                INNER JOIN professores p ON p.id = da.professor_id
                WHERE da.turma_id = :turma_id AND da.data_aula = :data";
        if ($materiaId > 0) {
            $sql .= ' AND da.materia_id = :materia_id';
            $params['materia_id'] = $materiaId;
        } elseif ($materiaNome !== '') {
            $sql .= ' AND m.nome LIKE :materia_nome';
            $params['materia_nome'] = '%' . $materiaNome . '%';
        }
        $sql .= ' ORDER BY da.horario_de ASC LIMIT 20';
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        if ($rows === []) {
            return ['error' => 'Nenhuma aula encontrada para essa turma/data.'];
        }
        if (count($rows) === 1) {
            $aula = $this->diarioModel->getAula((int) $rows[0]['id']);
            return $aula ? ['aula' => $aula] : ['error' => 'Aula não encontrada.'];
        }
        return [
            'candidatos' => array_map(static function (array $r): array {
                return [
                    'diario_aula_id' => (int) $r['id'],
                    'data_aula' => (string) $r['data_aula'],
                    'turma_nome' => (string) ($r['turma_nome'] ?? ''),
                    'materia_nome' => (string) ($r['materia_nome'] ?? ''),
                    'professor_nome' => (string) ($r['professor_nome'] ?? ''),
                    'horario' => trim((string) ($r['horario_de'] ?? '') . '–' . (string) ($r['horario_ate'] ?? ''), '–'),
                ];
            }, $rows),
            'aviso' => 'Há mais de uma aula nesse dia. Informe diario_aula_id:',
        ];
    }

    private function resolverCategoriaId(string $nome): int
    {
        $nomeNorm = mb_strtolower(trim($nome));
        foreach ($this->ocorrenciaModel->categorias(true) as $c) {
            if (mb_strtolower(trim((string) ($c['nome'] ?? ''))) === $nomeNorm) {
                return (int) $c['id'];
            }
        }
        foreach ($this->ocorrenciaModel->categorias(true) as $c) {
            $n = mb_strtolower(trim((string) ($c['nome'] ?? '')));
            if ($n !== '' && (str_contains($n, $nomeNorm) || str_contains($nomeNorm, $n))) {
                return (int) $c['id'];
            }
        }
        return 0;
    }

    /**
     * @param array<string,mixed> $usuario
     */
    private function checarPermissao(string $tipo, array $usuario): ?string
    {
        $permissoes = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $usuario);
        if ($tipo === 'criar_ocorrencia') {
            if (!FeatureGate::isModuleEnabled('ocorrencias')) {
                return 'Módulo de ocorrências desabilitado nesta escola.';
            }
            if (!AdminPermissionMatrix::can($permissoes, 'ocorrencias', 'cadastrar')) {
                return 'Sem permissão para cadastrar ocorrências.';
            }
            return null;
        }
        if ($tipo === 'entrada_saida') {
            if (!FeatureGate::isModuleEnabled('presenca')) {
                return 'Módulo de presença desabilitado nesta escola.';
            }
            if (!AdminPermissionMatrix::can($permissoes, 'presenca', 'alterar')) {
                return 'Sem permissão para registrar entrada/saída.';
            }
            return null;
        }
        if ($tipo === 'chamada_aula') {
            if (!FeatureGate::isModuleEnabled('diario_classe')) {
                return 'Módulo de diário de classe desabilitado nesta escola.';
            }
            if (!AdminPermissionMatrix::can($permissoes, 'diario_classe', 'alterar')
                && !AdminPermissionMatrix::can($permissoes, 'diario_classe', 'cadastrar')) {
                return 'Sem permissão para lançar chamada no diário.';
            }
            return null;
        }
        return 'Tipo de proposta não suportado.';
    }

    /** @param array<string,mixed> $payload */
    private function resumoOcorrencia(array $payload): string
    {
        $nomes = array_map(static fn($a) => (string) ($a['nome'] ?? ''), (array) ($payload['alunos'] ?? []));
        $grav = Ocorrencia::GRAVIDADES[(string) ($payload['nivel_gravidade'] ?? '')] ?? (string) ($payload['nivel_gravidade'] ?? '');
        $detalhe = trim((string) ($payload['detalhe'] ?? ''));
        if (mb_strlen($detalhe) > 160) {
            $detalhe = mb_substr($detalhe, 0, 157) . '…';
        }
        return 'Criar ocorrência "' . (string) ($payload['titulo'] ?? '') . '" (' . $grav . ') para '
            . implode(', ', array_filter($nomes))
            . ($detalhe !== '' ? ' — ' . $detalhe : '')
            . (!empty($payload['notificar_push']) ? ' — com push aos responsáveis' : ' — sem push');
    }

    /** @param array<string,mixed> $payload */
    private function resumoEntradaSaida(array $payload): string
    {
        $nomes = array_map(static fn($a) => (string) ($a['nome'] ?? ''), (array) ($payload['alunos'] ?? []));
        $rotulo = (($payload['tipo'] ?? '') === 'saida') ? 'Saída' : 'Entrada';
        return $rotulo . ' em ' . (string) ($payload['ocorrido_em'] ?? '') . ' para ' . implode(', ', array_filter($nomes));
    }

    /** @param array<string,mixed> $payload */
    private function resumoChamada(array $payload): string
    {
        $nomes = array_map(static fn($a) => (string) ($a['nome'] ?? ''), (array) ($payload['alunos'] ?? []));
        $aula = (array) ($payload['aula'] ?? []);
        return 'Marcar ' . (string) ($payload['situacao'] ?? 'presente') . ' para '
            . implode(', ', array_filter($nomes))
            . ' na aula #' . (int) ($payload['diario_aula_id'] ?? 0)
            . ' (' . (string) ($aula['materia_nome'] ?? '') . ' · ' . (string) ($aula['data_aula'] ?? '') . ')';
    }

    /** @param mixed $v */
    private function boolArg($v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return (int) $v === 1;
        }
        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'true', 'sim', 'yes', 'on'], true);
    }
}
