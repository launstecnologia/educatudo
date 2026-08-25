<?php

namespace App\Modulos\CensoEscolar\Services;

require_once __DIR__ . '/../Models/CensoEdicao.php';
require_once __DIR__ . '/CensoLayoutService.php';
require_once __DIR__ . '/CensoColetorService.php';
require_once __DIR__ . '/CensoValidacaoService.php';
require_once __DIR__ . '/CensoExportacaoService.php';
require_once __DIR__ . '/CensoNormalizador.php';

use App\Modulos\CensoEscolar\Models\CensoEdicao;

/**
 * Orquestra edição, completude, complementos e ciclo de vida do Censo.
 */
class CensoEdicaoService
{
    private CensoEdicao $model;
    private CensoLayoutService $layouts;
    private CensoColetorService $coletor;
    private CensoValidacaoService $validacao;
    private CensoExportacaoService $exportacao;

    public function __construct(
        ?CensoEdicao $model = null,
        ?CensoLayoutService $layouts = null,
        ?CensoColetorService $coletor = null,
        ?CensoValidacaoService $validacao = null,
        ?CensoExportacaoService $exportacao = null
    ) {
        $this->model = $model ?? new CensoEdicao();
        $this->layouts = $layouts ?? new CensoLayoutService();
        $this->coletor = $coletor ?? new CensoColetorService($this->model);
        $this->validacao = $validacao ?? new CensoValidacaoService($this->model, $this->layouts);
        $this->exportacao = $exportacao ?? new CensoExportacaoService($this->model, $this->layouts, $this->validacao);
    }

    public function model(): CensoEdicao
    {
        return $this->model;
    }

    public function layouts(): CensoLayoutService
    {
        return $this->layouts;
    }

    /**
     * Anos do seletor: cadastros acadêmicos, edições já abertas e leiautes versionados.
     *
     * @return list<int>
     */
    public function anosDoSeletor(): array
    {
        $anos = [];
        foreach ($this->model->anosLetivosColeta() as $ano) {
            $anos[(int) $ano] = (int) $ano;
        }
        foreach ($this->layouts->anosDisponiveis() as $ano) {
            $anos[(int) $ano] = (int) $ano;
        }
        if ($anos === []) {
            $anos[(int) date('Y')] = (int) date('Y');
        }
        $lista = array_values($anos);
        rsort($lista, SORT_NUMERIC);
        return $lista;
    }

    public function exportacao(): CensoExportacaoService
    {
        return $this->exportacao;
    }

    /**
     * @return array{success:bool, id?:int, error?:string}
     */
    public function garantirEdicao(array $input, int $usuarioId): array
    {
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Execute a migration do Censo Escolar (painel Master) antes de usar o módulo.'];
        }
        $ano = (int) ($input['ano'] ?? date('Y'));
        $unidadeId = (int) ($input['unidade_id'] ?? 0);
        $etapa = (string) ($input['etapa_coleta'] ?? 'matricula_inicial');
        if (!isset(CensoEdicao::ETAPAS[$etapa])) {
            $etapa = 'matricula_inicial';
        }
        if ($ano < 2000 || $ano > 2100) {
            return ['success' => false, 'error' => 'Ano da edição inválido.'];
        }
        $existente = $this->model->findByContexto($unidadeId, $ano, $etapa);
        if ($existente) {
            $layout = $this->layouts->carregar($ano, $etapa);
            if ($this->edicaoEditavel($existente)) {
                $this->model->atualizar((int) $existente['id'], [
                    'versao_layout' => (string) ($layout['versao'] ?? $existente['versao_layout'] ?? ''),
                ]);
                $this->coletor->sincronizar($existente);
                $atual = $this->model->findById((int) $existente['id']);
                if ($atual) {
                    $this->validacao->validar($atual);
                    $this->model->atualizar((int) $existente['id'], ['ultima_validacao_por' => $usuarioId]);
                }
            }
            return ['success' => true, 'id' => (int) $existente['id']];
        }
        $layout = $this->layouts->carregar($ano, $etapa);
        $dataRef = trim((string) ($input['data_referencia'] ?? ''));
        if ($dataRef === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) {
            $dataRef = $ano . '-05-28';
        }
        $id = $this->model->criar([
            'unidade_id' => $unidadeId,
            'ano' => $ano,
            'etapa_coleta' => $etapa,
            'data_referencia' => $dataRef,
            'versao_layout' => $layout['versao'] ?? null,
            'status' => 'rascunho',
            'responsavel_id' => $usuarioId,
        ]);
        $this->model->registrarAuditoria([
            'edicao_id' => $id,
            'usuario_id' => $usuarioId,
            'acao' => 'criar_edicao',
            'entidade_tipo' => 'edicao',
            'entidade_id' => $id,
            'dados_novos_json' => json_encode(['ano' => $ano, 'etapa' => $etapa, 'unidade_id' => $unidadeId], JSON_UNESCAPED_UNICODE),
            'ip' => $this->ip(),
        ]);
        $this->coletor->sincronizar($this->model->findById($id) ?? []);
        $this->model->atualizar($id, ['status' => 'em_preenchimento']);
        $criada = $this->model->findById($id);
        if ($criada) {
            $this->validacao->validar($criada);
            $this->model->atualizar($id, ['ultima_validacao_por' => $usuarioId]);
        }
        return ['success' => true, 'id' => $id];
    }

    public function atualizarConfig(array $edicao, array $input, int $usuarioId): array
    {
        if (!$this->edicaoEditavel($edicao)) {
            return ['success' => false, 'error' => 'A edição está fechada.'];
        }
        $dataRef = trim((string) ($input['data_referencia'] ?? ''));
        if ($dataRef !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) {
            return ['success' => false, 'error' => 'Data de referência inválida.'];
        }
        $this->model->atualizar((int) $edicao['id'], [
            'data_referencia' => $dataRef !== '' ? $dataRef : $edicao['data_referencia'],
            'responsavel_id' => $usuarioId,
        ]);
        $this->model->registrarAuditoria([
            'edicao_id' => (int) $edicao['id'],
            'usuario_id' => $usuarioId,
            'acao' => 'configurar_edicao',
            'entidade_tipo' => 'edicao',
            'entidade_id' => (int) $edicao['id'],
            'dados_novos_json' => json_encode(['data_referencia' => $dataRef], JSON_UNESCAPED_UNICODE),
            'ip' => $this->ip(),
        ]);
        return ['success' => true];
    }

    public function sincronizar(array $edicao, int $usuarioId): array
    {
        $r = $this->coletor->sincronizar($edicao);
        if (!empty($r['success'])) {
            $this->model->registrarAuditoria([
                'edicao_id' => (int) $edicao['id'],
                'usuario_id' => $usuarioId,
                'acao' => 'sincronizar',
                'entidade_tipo' => 'edicao',
                'entidade_id' => (int) $edicao['id'],
                'dados_novos_json' => json_encode($r['totais'] ?? [], JSON_UNESCAPED_UNICODE),
                'ip' => $this->ip(),
            ]);
            if ((string) ($edicao['status'] ?? '') === 'rascunho') {
                $this->model->atualizar((int) $edicao['id'], ['status' => 'em_preenchimento']);
            }
            $atual = $this->model->findById((int) $edicao['id']);
            if ($atual && $this->edicaoEditavel($atual)) {
                $this->validacao->validar($atual);
                $this->model->atualizar((int) $edicao['id'], ['ultima_validacao_por' => $usuarioId]);
            }
        }
        return $r;
    }

    public function validar(array $edicao, int $usuarioId): array
    {
        if (!$this->edicaoEditavel($edicao)) {
            return ['success' => false, 'error' => 'A edição está fechada.'];
        }
        $r = $this->validacao->validar($edicao);
        if (!empty($r['success'])) {
            $this->model->atualizar((int) $edicao['id'], ['ultima_validacao_por' => $usuarioId]);
            $this->model->registrarAuditoria([
                'edicao_id' => (int) $edicao['id'],
                'usuario_id' => $usuarioId,
                'acao' => 'validar',
                'entidade_tipo' => 'edicao',
                'entidade_id' => (int) $edicao['id'],
                'dados_novos_json' => json_encode($r['resumo'] ?? [], JSON_UNESCAPED_UNICODE),
                'ip' => $this->ip(),
            ]);
        }
        return $r;
    }

    public function painel(array $edicao): array
    {
        $id = (int) $edicao['id'];
        $cards = [
            ['chave' => 'escola', 'label' => 'Escola', 'tabela' => 'censo_complementos_escola'],
            ['chave' => 'gestores', 'label' => 'Gestores', 'tabela' => 'censo_complementos_gestor'],
            ['chave' => 'turmas', 'label' => 'Turmas', 'tabela' => 'censo_complementos_turma'],
            ['chave' => 'alunos', 'label' => 'Alunos', 'tabela' => 'censo_complementos_aluno'],
            ['chave' => 'profissionais', 'label' => 'Profissionais', 'tabela' => 'censo_complementos_profissional'],
            ['chave' => 'matriculas', 'label' => 'Matrículas', 'tabela' => 'censo_matriculas'],
        ];
        $saida = [];
        foreach ($cards as $c) {
            $n = $this->model->contarCategoria($c['tabela'], $id);
            $pct = $n['total'] > 0 ? (int) round(100 * $n['prontos'] / $n['total']) : 0;
            $saida[] = $c + $n + [
                'percentual' => $pct,
                'pendencias' => $n['erros'] + $n['incompletos'],
            ];
        }
        $layout = $this->layouts->carregar((int) $edicao['ano'], (string) $edicao['etapa_coleta']);
        return [
            'cards' => $saida,
            'validacao' => $this->model->resumoValidacao($id),
            'layout' => $layout,
            'pode_gerar' => $this->validacao->podeGerarTxt($edicao),
        ];
    }

    public function salvarComplemento(array $edicao, string $entidade, int $id, array $input, int $usuarioId): array
    {
        if (!$this->edicaoEditavel($edicao)) {
            return ['success' => false, 'error' => 'A edição está fechada.'];
        }
        $mapTabela = [
            'escola' => 'censo_complementos_escola',
            'gestor' => 'censo_complementos_gestor',
            'turma' => 'censo_complementos_turma',
            'aluno' => 'censo_complementos_aluno',
            'profissional' => 'censo_complementos_profissional',
            'matricula' => 'censo_matriculas',
        ];
        $tabela = $mapTabela[$entidade] ?? '';
        if ($tabela === '') {
            return ['success' => false, 'error' => 'Entidade inválida.'];
        }
        $atual = $this->model->findComplemento($entidade, (int) $edicao['id'], $id);
        if (!$atual) {
            return ['success' => false, 'error' => 'Registro não encontrado nesta edição.'];
        }
        $dados = $this->json($atual['dados_json'] ?? null);
        $camposJson = [
            'localizacao', 'localizacao_diferenciada', 'situacao_funcionamento', 'categoria_privada',
            'regulamentacao', 'data_inicio_ano_letivo', 'data_fim_ano_letivo', 'forma_ocupacao',
            'agua', 'energia', 'esgoto', 'lixo', 'internet', 'alimentacao', 'acessibilidade',
            'deficiencia', 'transporte', 'recursos_acessibilidade', 'atendimento_especializado',
            'indigena', 'escolaridade', 'formacao_superior', 'pos_graduacao', 'cargo',
            'criterio_acesso', 'situacao_funcional', 'mediacao', 'horario_inicio', 'duracao',
            'dias_semana', 'tipo_atendimento', 'local_funcionamento',
            'nome_mae', 'nome_pai', 'data_nasc', 'cor_raca', 'nacionalidade',
            'dependencia_administrativa', 'codigo_inep', 'cpf',
            'municipio', 'cep', 'endereco', 'numero', 'bairro', 'distrito', 'horario',
        ];
        foreach ($camposJson as $campo) {
            if (array_key_exists($campo, $input)) {
                $dados[$campo] = trim((string) $input[$campo]);
            }
        }
        if ($entidade === 'profissional') {
            if (isset($dados['escolaridade'])) {
                $escInep = CensoNormalizador::escolaridadeInep((string) $dados['escolaridade']);
                if ($escInep !== '') {
                    $dados['escolaridade'] = $escInep;
                }
            }
            if (isset($dados['situacao_funcional'])) {
                $dados['situacao_funcional'] = CensoNormalizador::situacaoFuncional((string) $dados['situacao_funcional']);
            }
        }
        $upd = [
            'dados_json' => json_encode($dados, JSON_UNESCAPED_UNICODE),
            'status_validacao' => 'pendente',
        ];
        if (isset($input['etapa_codigo']) && $this->model->colunaExiste($tabela, 'etapa_codigo')) {
            $upd['etapa_codigo'] = trim((string) $input['etapa_codigo']);
        }
        if (isset($input['modalidade_codigo']) && $this->model->colunaExiste($tabela, 'modalidade_codigo')) {
            $upd['modalidade_codigo'] = trim((string) $input['modalidade_codigo']);
        }
        if (isset($input['codigo_inep']) && $this->model->colunaExiste($tabela, 'codigo_inep')) {
            $upd['codigo_inep'] = trim((string) $input['codigo_inep']);
        }
        if (isset($input['cpf']) && $this->model->colunaExiste($tabela, 'cpf')) {
            $upd['cpf'] = preg_replace('/\D+/', '', (string) $input['cpf']);
        }
        if (isset($input['cargo_codigo']) && $this->model->colunaExiste($tabela, 'cargo_codigo')) {
            $upd['cargo_codigo'] = trim((string) $input['cargo_codigo']);
        }
        if (isset($input['incluir_exportacao']) && $this->model->colunaExiste($tabela, 'incluir_exportacao')) {
            $upd['incluir_exportacao'] = !empty($input['incluir_exportacao']) ? 1 : 0;
        }
        if (isset($input['motivo_exclusao']) && $this->model->colunaExiste($tabela, 'motivo_exclusao')) {
            $upd['motivo_exclusao'] = trim((string) $input['motivo_exclusao']);
        }
        $this->model->upsertComplemento($tabela, ['id' => $id], $upd);

        if (!empty($input['atualizar_cadastro_principal'])) {
            $this->propagarCadastro($entidade, $atual, $input);
        }
        $this->model->registrarAuditoria([
            'edicao_id' => (int) $edicao['id'],
            'usuario_id' => $usuarioId,
            'acao' => 'salvar_complemento',
            'entidade_tipo' => $entidade,
            'entidade_id' => $id,
            'dados_anteriores_json' => json_encode($atual['dados_json'] ?? null, JSON_UNESCAPED_UNICODE),
            'dados_novos_json' => $upd['dados_json'],
            'ip' => $this->ip(),
        ]);
        return ['success' => true];
    }

    public function marcarConferido(array $edicao, int $validacaoId, int $usuarioId, string $justificativa = ''): array
    {
        if (!$this->edicaoEditavel($edicao)) {
            return ['success' => false, 'error' => 'A edição está fechada.'];
        }
        $atual = $this->model->findValidacao($validacaoId, (int) $edicao['id']);
        if (!$atual) {
            return ['success' => false, 'error' => 'Pendência não encontrada nesta edição.'];
        }
        if (($atual['severidade'] ?? '') === 'erro') {
            return ['success' => false, 'error' => 'Erro impeditivo não pode ser só conferido. Corrija o cadastro e valide de novo.'];
        }
        $this->model->atualizarValidacao($validacaoId, (int) $edicao['id'], [
            'status' => $justificativa !== '' ? 'justificada' : 'conferida',
            'justificativa' => $justificativa !== '' ? $justificativa : null,
            'resolvido_por' => $usuarioId,
            'resolvido_em' => date('Y-m-d H:i:s'),
        ]);
        $this->model->registrarAuditoria([
            'edicao_id' => (int) $edicao['id'],
            'usuario_id' => $usuarioId,
            'acao' => $justificativa !== '' ? 'justificar' : 'conferir',
            'entidade_tipo' => 'validacao',
            'entidade_id' => $validacaoId,
            'ip' => $this->ip(),
        ]);
        return ['success' => true];
    }

    public function fechar(array $edicao, int $usuarioId): array
    {
        $gate = $this->validacao->podeGerarTxt($edicao);
        $resumo = $this->model->resumoValidacao((int) $edicao['id']);
        if ($resumo['erros'] > 0) {
            return ['success' => false, 'error' => 'Não é possível fechar com erros impeditivos.'];
        }
        $this->model->atualizar((int) $edicao['id'], [
            'status' => 'fechado',
            'fechado_em' => date('Y-m-d H:i:s'),
            'fechado_por' => $usuarioId,
        ]);
        $this->model->registrarAuditoria([
            'edicao_id' => (int) $edicao['id'],
            'usuario_id' => $usuarioId,
            'acao' => 'fechar',
            'entidade_tipo' => 'edicao',
            'entidade_id' => (int) $edicao['id'],
            'dados_novos_json' => json_encode(['gate' => $gate], JSON_UNESCAPED_UNICODE),
            'ip' => $this->ip(),
        ]);
        return ['success' => true];
    }

    public function reabrir(array $edicao, int $usuarioId, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            return ['success' => false, 'error' => 'Informe o motivo da reabertura.'];
        }
        $this->model->atualizar((int) $edicao['id'], [
            'status' => 'em_preenchimento',
            'reaberto_em' => date('Y-m-d H:i:s'),
            'reaberto_por' => $usuarioId,
            'motivo_reabertura' => $motivo,
        ]);
        $this->model->registrarAuditoria([
            'edicao_id' => (int) $edicao['id'],
            'usuario_id' => $usuarioId,
            'acao' => 'reabrir',
            'entidade_tipo' => 'edicao',
            'entidade_id' => (int) $edicao['id'],
            'dados_novos_json' => json_encode(['motivo' => $motivo], JSON_UNESCAPED_UNICODE),
            'ip' => $this->ip(),
        ]);
        return ['success' => true];
    }

    public function confirmarSituacao(array $edicao, int $id, string $codigo, string $justificativa, int $usuarioId): array
    {
        if (!$this->edicaoEditavel($edicao)) {
            return ['success' => false, 'error' => 'A edição está fechada.'];
        }
        $atual = $this->model->findComplemento('situacao', (int) $edicao['id'], $id);
        if (!$atual) {
            return ['success' => false, 'error' => 'Situação não encontrada nesta edição.'];
        }
        $this->model->upsertComplemento(
            'censo_situacoes_aluno',
            ['id' => $id, 'edicao_id' => (int) $edicao['id']],
            [
                'situacao_codigo' => $codigo,
                'justificativa' => $justificativa !== '' ? $justificativa : null,
                'confirmado_por' => $usuarioId,
                'confirmado_em' => date('Y-m-d H:i:s'),
                'status_validacao' => 'pronto',
                'origem' => 'manual',
            ]
        );
        return ['success' => true];
    }

    public function garantirSituacoes(array $edicao): void
    {
        if (!$this->edicaoEditavel($edicao)) {
            return;
        }
        $matriculas = $this->model->listarEntidade('matriculas', (int) $edicao['id']);
        foreach ($matriculas as $m) {
            $existente = $this->model->situacaoPorMatricula((int) $edicao['id'], (int) $m['id']);
            if ($existente) {
                continue;
            }
            $this->model->upsertComplemento(
                'censo_situacoes_aluno',
                [
                    'edicao_id' => (int) $edicao['id'],
                    'censo_matricula_id' => (int) $m['id'],
                ],
                [
                    'aluno_id' => (int) $m['aluno_id'],
                    'origem' => 'sincronizacao',
                    'status_validacao' => 'pendente',
                ]
            );
        }
    }

    public function edicaoEditavel(array $edicao): bool
    {
        return !in_array((string) ($edicao['status'] ?? ''), CensoEdicao::STATUS_BLOQUEADOS, true);
    }

    public function mascararCpf(?string $cpf): string
    {
        $d = preg_replace('/\D+/', '', (string) $cpf);
        if (strlen($d) !== 11) {
            return $d === '' ? '' : '•••';
        }
        return substr($d, 0, 3) . '.***.***-' . substr($d, -2);
    }

    private function propagarCadastro(string $entidade, array $atual, array $input): void
    {
        if ($entidade === 'aluno' && !empty($atual['aluno_id'])) {
            $payload = [];
            foreach (['codigo_inep', 'nome_mae', 'nome_pai', 'cor_raca', 'nacionalidade'] as $campo) {
                if (!array_key_exists($campo, $input)) {
                    continue;
                }
                $payload[$campo] = $input[$campo];
            }
            if (array_key_exists('cpf', $input)) {
                $payload['cpf'] = preg_replace('/\D+/', '', (string) $input['cpf']);
            }
            if ($payload !== []) {
                $this->model->atualizarAluno((int) $atual['aluno_id'], $payload);
            }
        }
        if ($entidade === 'escola' && !empty($atual['unidade_id'])) {
            $payload = [];
            if (array_key_exists('codigo_inep', $input) || array_key_exists('inep', $input)) {
                $payload['inep'] = $input['codigo_inep'] ?? $input['inep'];
            }
            if (array_key_exists('dependencia_administrativa', $input)) {
                $payload['dependencia_administrativa'] = $input['dependencia_administrativa'];
            }
            if ($payload !== []) {
                $this->model->atualizarUnidade((int) $atual['unidade_id'], $payload);
            }
        }
    }

    private function json($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }

    private function ip(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? substr($ip, 0, 45) : null;
    }
}
