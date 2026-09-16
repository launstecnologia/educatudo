<?php
require_once __DIR__ . '/../Models/FechamentoPeriodo.php';
require_once __DIR__ . '/FechamentoMaquinaEstados.php';
require_once __DIR__ . '/FechamentoGates.php';
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/../../../Services/ResultadoHomologacaoService.php';

/**
 * Fechamento oficial: estado canônico por turma × período.
 * resultado_academico permanece o detalhe por aluno/disciplina.
 */
class FechamentoService
{
    private FechamentoPeriodo $model;
    private ResultadoHomologacaoService $homologacao;

    public function __construct(
        ?FechamentoPeriodo $model = null,
        ?ResultadoHomologacaoService $homologacao = null
    ) {
        $this->model = $model ?? new FechamentoPeriodo();
        $this->homologacao = $homologacao ?? new ResultadoHomologacaoService();
    }

    public function model(): FechamentoPeriodo
    {
        return $this->model;
    }

    public function homologacao(): ResultadoHomologacaoService
    {
        return $this->homologacao;
    }

    /**
     * @return array<string,mixed>
     */
    public function garantirVigente(
        int $turmaId,
        int $anoLetivo,
        string $periodoTipo,
        int $periodoNumero,
        ?int $regraAcademicaId = null
    ): array {
        $existente = $this->model->findVigente($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        if ($existente) {
            if ($regraAcademicaId && empty($existente['regra_academica_id'])) {
                $this->model->atualizar((int) $existente['id'], ['regra_academica_id' => $regraAcademicaId]);
                $existente['regra_academica_id'] = $regraAcademicaId;
            }
            return $existente;
        }
        $id = $this->model->criar([
            'turma_id' => $turmaId,
            'ano_letivo' => $anoLetivo,
            'periodo_tipo' => $periodoTipo,
            'periodo_numero' => $periodoNumero,
            'periodo_ref' => FechamentoMaquinaEstados::periodoRef($anoLetivo, $periodoTipo, $periodoNumero),
            'status' => FechamentoMaquinaEstados::ABERTO,
            'regra_academica_id' => $regraAcademicaId,
            'vigente' => 1,
        ]);
        return $this->model->findById($id) ?? [
            'id' => $id,
            'turma_id' => $turmaId,
            'status' => FechamentoMaquinaEstados::ABERTO,
        ];
    }

    /**
     * @return array{success:bool,error?:string,fechamento?:array<string,mixed>}
     */
    public function transitar(
        int $turmaId,
        int $anoLetivo,
        string $periodoTipo,
        int $periodoNumero,
        string $destino,
        int $usuarioId,
        string $justificativa = '',
        ?int $regraAcademicaId = null
    ): array {
        $atual = $this->garantirVigente($turmaId, $anoLetivo, $periodoTipo, $periodoNumero, $regraAcademicaId);
        $de = FechamentoMaquinaEstados::normalizar((string) ($atual['status'] ?? FechamentoMaquinaEstados::ABERTO));
        $para = FechamentoMaquinaEstados::normalizar($destino);

        if ($de === $para) {
            return ['success' => true, 'fechamento' => $atual];
        }

        if ($de === FechamentoMaquinaEstados::HOMOLOGADO && $para === FechamentoMaquinaEstados::RETIFICADO) {
            return $this->retificar($turmaId, $anoLetivo, $periodoTipo, $periodoNumero, $usuarioId, $justificativa);
        }

        if (!FechamentoMaquinaEstados::podeTransitar($de, $para)) {
            return ['success' => false, 'error' => FechamentoMaquinaEstados::mensagemTransicaoInvalida($de, $para)];
        }

        if ($de === FechamentoMaquinaEstados::ABERTO && $para === FechamentoMaquinaEstados::EM_FECHAMENTO) {
            $preview = $this->homologacao->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
            $erros = FechamentoGates::errosIniciarFechamento(is_array($preview['resumo'] ?? null) ? $preview['resumo'] : []);
            if ($erros !== []) {
                return ['success' => false, 'error' => $erros[0]];
            }
        }

        $payload = ['status' => $para];
        if ($para === FechamentoMaquinaEstados::HOMOLOGADO) {
            $payload['homologado_em'] = date('Y-m-d H:i:s');
            $payload['homologado_por'] = $usuarioId;
        }
        if ($regraAcademicaId) {
            $payload['regra_academica_id'] = $regraAcademicaId;
        }
        $this->model->atualizar((int) $atual['id'], $payload);
        $this->model->registrarHistorico((int) $atual['id'], [
            'status_anterior' => $de,
            'status_novo' => $para,
            'justificativa' => $justificativa !== '' ? $justificativa : null,
            'usuario_id' => $usuarioId,
        ]);
        $atualizado = $this->model->findById((int) $atual['id']);
        return ['success' => true, 'fechamento' => $atualizado ?: $atual];
    }

    /**
     * Homologado permanece imutável; nasce um novo vigente RETIFICADO.
     *
     * @return array{success:bool,error?:string,fechamento?:array<string,mixed>}
     */
    public function retificar(
        int $turmaId,
        int $anoLetivo,
        string $periodoTipo,
        int $periodoNumero,
        int $usuarioId,
        string $justificativa
    ): array {
        $justificativa = trim($justificativa);
        if ($justificativa === '') {
            return ['success' => false, 'error' => 'Informe a justificativa da retificação.'];
        }
        $atual = $this->model->findVigente($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        if (!$atual) {
            return ['success' => false, 'error' => 'Não há fechamento vigente para retificar.'];
        }
        $de = FechamentoMaquinaEstados::normalizar((string) ($atual['status'] ?? ''));
        if ($de !== FechamentoMaquinaEstados::HOMOLOGADO) {
            return ['success' => false, 'error' => 'Só é possível retificar um período homologado.'];
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $this->model->marcarNaoVigente((int) $atual['id']);
            $this->model->registrarHistorico((int) $atual['id'], [
                'status_anterior' => $de,
                'status_novo' => FechamentoMaquinaEstados::RETIFICADO,
                'justificativa' => $justificativa,
                'usuario_id' => $usuarioId,
                'payload_json' => json_encode(['acao' => 'supersedido_por_retificacao'], JSON_UNESCAPED_UNICODE),
            ]);

            $novoId = $this->model->criar([
                'turma_id' => $turmaId,
                'ano_letivo' => $anoLetivo,
                'periodo_tipo' => $periodoTipo,
                'periodo_numero' => $periodoNumero,
                'periodo_ref' => (string) ($atual['periodo_ref'] ?? FechamentoMaquinaEstados::periodoRef($anoLetivo, $periodoTipo, $periodoNumero)),
                'status' => FechamentoMaquinaEstados::RETIFICADO,
                'regra_academica_id' => $atual['regra_academica_id'] ?? null,
                'retificado_de_id' => (int) $atual['id'],
                'justificativa' => $justificativa,
                'vigente' => 1,
            ]);
            if ($novoId <= 0) {
                throw new RuntimeException('Falha ao criar o período vigente da retificação.');
            }
            $this->model->registrarHistorico($novoId, [
                'status_anterior' => $de,
                'status_novo' => FechamentoMaquinaEstados::RETIFICADO,
                'justificativa' => $justificativa,
                'usuario_id' => $usuarioId,
                'payload_json' => json_encode(['retificado_de_id' => (int) $atual['id']], JSON_UNESCAPED_UNICODE),
            ]);
            $this->homologacao->marcarAlunosReabertosPorRetificacao(
                $turmaId,
                $anoLetivo,
                $periodoTipo,
                $periodoNumero,
                $usuarioId,
                $justificativa
            );
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('FechamentoService::retificar: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível registrar a retificação. Tente novamente.'];
        }

        return ['success' => true, 'fechamento' => $this->model->findById($novoId)];
    }

    /**
     * Atualiza o estado do período a partir do preview de alunos.
     *
     * @param array<string,mixed> $preview
     */
    public function sincronizarAposHomologacaoAlunos(
        int $turmaId,
        int $anoLetivo,
        string $periodoTipo,
        int $periodoNumero,
        array $preview,
        int $usuarioId
    ): void {
        $resumo = is_array($preview['resumo'] ?? null) ? $preview['resumo'] : [];
        $total = (int) ($resumo['total'] ?? 0);
        $homologados = (int) ($resumo['homologados'] ?? 0);
        $recuperacao = (int) ($resumo['recuperacao'] ?? 0);
        $regraId = 0;
        foreach (($preview['linhas'] ?? []) as $linha) {
            $rid = (int) ($linha['avaliado']['regra_id'] ?? ($linha['regra']['id'] ?? 0));
            if ($rid > 0) {
                $regraId = $rid;
                break;
            }
        }

        $atual = $this->garantirVigente($turmaId, $anoLetivo, $periodoTipo, $periodoNumero, $regraId ?: null);
        $status = FechamentoMaquinaEstados::normalizar((string) ($atual['status'] ?? FechamentoMaquinaEstados::ABERTO));
        if (FechamentoMaquinaEstados::estaTravado($status)) {
            return;
        }

        $destino = $status;
        if ($total > 0 && $homologados >= $total && $recuperacao === 0) {
            $destino = FechamentoMaquinaEstados::HOMOLOGADO;
        } elseif ($recuperacao > 0 && $homologados > 0) {
            $destino = FechamentoMaquinaEstados::EM_RECUPERACAO;
        } elseif ($homologados > 0 || $status === FechamentoMaquinaEstados::ABERTO) {
            $destino = FechamentoMaquinaEstados::EM_FECHAMENTO;
        }

        if ($destino !== $status && FechamentoMaquinaEstados::podeTransitar($status, $destino)) {
            $this->transitar($turmaId, $anoLetivo, $periodoTipo, $periodoNumero, $destino, $usuarioId, 'Sincronização após homologação de alunos', $regraId ?: null);
        } elseif ($regraId > 0) {
            $this->model->atualizar((int) $atual['id'], ['regra_academica_id' => $regraId]);
        }
    }

    public function estaTravado(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): bool
    {
        return $this->model->estaTravado($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
    }

    /**
     * Bloqueio de edição oficial (notas/faltas/boletim). Preview/rascunho não usa isto.
     *
     * @return array{ok:bool,error?:string}
     */
    public function assertEditavel(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero, bool $oficial = true): array
    {
        if (!$oficial) {
            return ['ok' => true];
        }
        if ($turmaId <= 0) {
            return ['ok' => true];
        }
        if ($this->model->estaTravado($turmaId, $anoLetivo, $periodoTipo, $periodoNumero)) {
            return [
                'ok' => false,
                'error' => 'Este período está homologado. Notas, faltas e o boletim oficial só podem ser alterados após retificação formal.',
            ];
        }
        if ($periodoTipo !== 'ano' && $this->model->estaTravado($turmaId, $anoLetivo, 'ano', 0)) {
            return [
                'ok' => false,
                'error' => 'O ano letivo desta turma está homologado. Use retificação para alterar lançamentos oficiais.',
            ];
        }
        return ['ok' => true];
    }

    /**
     * @param list<int> $turmaIds
     * @return array{ok:bool,error?:string}
     */
    public function assertTurmasEditaveis(array $turmaIds, int $anoLetivo, string $periodoTipo, int $periodoNumero, bool $oficial = true): array
    {
        foreach ($turmaIds as $tid) {
            $tid = (int) $tid;
            if ($tid <= 0) {
                continue;
            }
            $res = $this->assertEditavel($tid, $anoLetivo, $periodoTipo, $periodoNumero, $oficial);
            if (empty($res['ok'])) {
                return $res;
            }
        }
        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function assertEditavelNaData(int $turmaId, int $anoLetivo, string $dataYmd, int $bimestre = 0): array
    {
        if ($turmaId <= 0) {
            return ['ok' => true];
        }
        if ($this->model->estaTravadoNaData($turmaId, $anoLetivo, $dataYmd, $bimestre)) {
            return [
                'ok' => false,
                'error' => 'Período homologado: frequência e faltas oficiais estão travadas até uma retificação.',
            ];
        }
        return ['ok' => true];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function painel(int $anoLetivo, string $periodoTipo, int $periodoNumero, int $turmaFiltro = 0): array
    {
        $turmas = $this->homologacao->model()->turmasAtivas($anoLetivo);
        $linhas = [];
        foreach ($turmas as $turma) {
            $tid = (int) ($turma['id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            if ($turmaFiltro > 0 && $tid !== $turmaFiltro) {
                continue;
            }
            $preview = $this->homologacao->previewTurma($tid, $anoLetivo, $periodoTipo, $periodoNumero);
            $fechamento = $this->model->findVigente($tid, $anoLetivo, $periodoTipo, $periodoNumero);
            $status = $fechamento
                ? FechamentoMaquinaEstados::normalizar((string) $fechamento['status'])
                : FechamentoMaquinaEstados::ABERTO;
            $linhas[] = [
                'turma' => $preview['turma'] ?: $turma,
                'periodo' => $preview['periodo'],
                'resumo' => $preview['resumo'],
                'fechamento' => $fechamento,
                'status' => $status,
                'status_rotulo' => FechamentoMaquinaEstados::rotulo($status),
                'travado' => FechamentoMaquinaEstados::estaTravado($status),
                'pendencias' => (int) ($preview['resumo']['pendencias'] ?? 0),
                'pode_homologar' => !empty($preview['pode_homologar']) && !FechamentoMaquinaEstados::estaTravado($status),
            ];
        }
        return $linhas;
    }

    /**
     * Livro de homologações para o offcanvas (por turma ou ano inteiro).
     *
     * @return array{schema_pronto:bool,registros:list<array<string,mixed>>}
     */
    public function dadosHomologacoes(int $anoLetivo, int $turmaId = 0): array
    {
        $registros = [];
        foreach ($this->model->listarLivro($anoLetivo, $turmaId) as $reg) {
            $fid = (int) ($reg['id'] ?? 0);
            $auditoria = [];
            if ($fid > 0) {
                foreach ($this->model->listarHistorico($fid) as $hist) {
                    $de = trim((string) ($hist['status_anterior'] ?? '—'));
                    $para = trim((string) ($hist['status_novo'] ?? ''));
                    $auditoria[] = $de . ' → ' . $para;
                }
            }
            $status = FechamentoMaquinaEstados::normalizar((string) ($reg['status'] ?? ''));
            $registros[] = [
                'turma_nome' => (string) ($reg['turma_nome'] ?? ''),
                'periodo_ref' => (string) ($reg['periodo_ref'] ?? ''),
                'status' => $status,
                'status_rotulo' => FechamentoMaquinaEstados::rotulo($status),
                'vigente' => !empty($reg['vigente']),
                'homologado_em' => (string) ($reg['homologado_em'] ?? ''),
                'homologado_por_nome' => (string) ($reg['homologado_por_nome'] ?? ''),
                'justificativa' => (string) ($reg['justificativa'] ?? ''),
                'retificado_de_id' => (int) ($reg['retificado_de_id'] ?? 0),
                'auditoria' => $auditoria,
            ];
        }

        return [
            'schema_pronto' => $this->model->schemaPronto(),
            'registros' => $registros,
        ];
    }

    /**
     * Emissão de documentos e histórico para o offcanvas.
     *
     * @return array<string,mixed>
     */
    public function dadosDocumentos(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): array
    {
        $turmaNome = '';
        $homologados = 0;
        $linhas = [];
        if ($turmaId > 0) {
            $preview = $this->homologacao->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
            $turmaNome = (string) ($preview['turma']['nome'] ?? '');
            $homologados = (int) ($preview['resumo']['homologados'] ?? 0);
            foreach ($preview['linhas'] ?? [] as $linha) {
                $linhas[] = [
                    'aluno_id' => (int) ($linha['aluno']['id'] ?? 0),
                    'aluno_nome' => (string) ($linha['aluno']['nome'] ?? ''),
                    'rotulo' => (string) ($linha['rotulo'] ?? ''),
                ];
            }
        }

        $emissoes = [];
        foreach ($this->homologacao->model()->listarEmissoes($anoLetivo, $turmaId, '') as $em) {
            $tipo = (string) ($em['tipo'] ?? '');
            $emissoes[] = [
                'emitido_em' => (string) ($em['emitido_em'] ?? ''),
                'tipo' => $tipo,
                'tipo_rotulo' => ResultadoAcademico::DOCUMENTO_TIPOS[$tipo] ?? $tipo,
                'turma_nome' => (string) ($em['turma_nome'] ?? ''),
                'aluno_nome' => (string) ($em['aluno_nome'] ?? ''),
                'numero' => (int) ($em['numero'] ?? 0),
            ];
        }

        return [
            'turma_id' => $turmaId,
            'turma_nome' => $turmaNome,
            'homologados' => $homologados,
            'linhas' => $linhas,
            'emissoes' => $emissoes,
            'qs' => http_build_query([
                'ano_letivo' => $anoLetivo,
                'periodo_tipo' => $periodoTipo,
                'periodo_numero' => $periodoNumero,
                'turma_id' => $turmaId,
            ]),
        ];
    }
}
