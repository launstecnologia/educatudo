<?php
/**
 * Execução do projeto: tarefas, materiais, stand/QR e programação (S4).
 */

require_once __DIR__ . '/../Models/ExpoColagProjeto.php';
require_once __DIR__ . '/../Models/ExpoColagInscricao.php';
require_once __DIR__ . '/../Models/ExpoColagTarefa.php';
require_once __DIR__ . '/../Models/ExpoColagStand.php';
require_once __DIR__ . '/../Models/ExpoColagProgramacao.php';
require_once __DIR__ . '/../Models/ExpoColagEdicao.php';
require_once __DIR__ . '/../Models/ExpoColagPedidoMaterial.php';
require_once __DIR__ . '/../Models/ExpoColagMensagem.php';
require_once __DIR__ . '/ExpoColagService.php';

class ExpoColagExecucaoService
{
    private ExpoColagProjeto $projetoModel;
    private ExpoColagInscricao $inscricaoModel;
    private ExpoColagTarefa $tarefaModel;
    private ExpoColagStand $standModel;
    private ExpoColagProgramacao $programacaoModel;
    private ExpoColagPedidoMaterial $pedidoModel;
    private ExpoColagMensagem $mensagemModel;
    private ExpoColagEdicao $edicaoModel;
    private ExpoColagService $expoService;
    private $db;

    private const TIPOS_ENTREGAVEL = ['Nenhum', 'Arquivo', 'Texto', 'Link'];
    private const STATUS_ATR_ALUNO = ['Pendente', 'Em_andamento', 'Entregue', 'Atrasada', 'Devolvida'];
    private const STATUS_ATR_PROF = ['Pendente', 'Em_andamento', 'Entregue', 'Concluida', 'Atrasada', 'Devolvida'];

    public function __construct()
    {
        $this->projetoModel = new ExpoColagProjeto();
        $this->inscricaoModel = new ExpoColagInscricao();
        $this->tarefaModel = new ExpoColagTarefa();
        $this->standModel = new ExpoColagStand();
        $this->programacaoModel = new ExpoColagProgramacao();
        $this->edicaoModel = new ExpoColagEdicao();
        $this->pedidoModel = new ExpoColagPedidoMaterial();
        $this->mensagemModel = new ExpoColagMensagem();
        $this->expoService = new ExpoColagService();
        $this->db = Database::getInstance();
    }

    private function assertProjetoProfessor(int $projetoId, int $professorId): ?array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto || (int) $projeto['professor_id'] !== $professorId) {
            return null;
        }
        return $projeto;
    }

    private function assertAlunoAprovado(int $projetoId, int $alunoId): ?array
    {
        $insc = $this->inscricaoModel->findByProjetoAluno($projetoId, $alunoId);
        if (!$insc || ($insc['status'] ?? '') !== 'Aprovada') {
            return null;
        }
        return $insc;
    }

    public function painelProfessor(int $projetoId, int $professorId): array
    {
        $projeto = $this->assertProjetoProfessor($projetoId, $professorId);
        if (!$projeto) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        $this->tarefaModel->marcarAtrasadasProjeto($projetoId);
        $completo = $this->expoService->carregarProjetoCompleto($projetoId, $professorId);
        $stand = $this->standModel->findByProjeto($projetoId);

        return [
            'success' => true,
            'projeto' => $projeto,
            'relacoes' => $completo['relacoes'] ?? [],
            'inscricoes' => $this->expoService->listarInscricoesProjeto($projetoId, $professorId),
            'tarefas' => $this->tarefaModel->listarPorProjeto($projetoId),
            'atribuicoes' => $this->tarefaModel->listarAtribuicoesProjeto($projetoId),
            'materiais' => $this->listarMateriais($projetoId),
            'pedidos_materiais' => $this->listarPedidosProjetoSeguro($projetoId),
            'mensagens' => $this->listarMensagensSeguro($projetoId),
            'stand' => $stand,
            'url_qr' => $stand ? $this->urlPublicaStand((string) $stand['qr_token']) : null,
            'setores' => $this->programacaoModel->listarSetores((int) ($projeto['edicao_id'] ?? 0)),
        ];
    }

    public function painelAdmin(int $projetoId): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }

        $this->tarefaModel->marcarAtrasadasProjeto($projetoId);
        $completo = $this->expoService->carregarProjetoCompleto($projetoId, null, true);
        $stand = $this->standModel->findByProjeto($projetoId);

        return [
            'success' => true,
            'projeto' => $projeto,
            'relacoes' => $completo['relacoes'] ?? [],
            'inscricoes' => $this->expoService->listarInscricoesProjeto($projetoId, 0, true),
            'tarefas' => $this->tarefaModel->listarPorProjeto($projetoId),
            'atribuicoes' => $this->tarefaModel->listarAtribuicoesProjeto($projetoId),
            'materiais' => $this->listarMateriais($projetoId),
            'pedidos_materiais' => $this->listarPedidosProjetoSeguro($projetoId),
            'mensagens' => $this->listarMensagensSeguro($projetoId),
            'stand' => $stand,
            'url_qr' => $stand ? $this->urlPublicaStand((string) $stand['qr_token']) : null,
            'setores' => $this->programacaoModel->listarSetores((int) ($projeto['edicao_id'] ?? 0)),
        ];
    }

    public function painelAluno(int $projetoId, int $alunoId): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto || empty($projeto['ativo']) || ($projeto['status'] ?? '') === 'Cancelado') {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        $insc = $this->assertAlunoAprovado($projetoId, $alunoId);
        if (!$insc) {
            return ['success' => false, 'error' => 'Você precisa estar aprovado neste projeto.'];
        }
        $this->tarefaModel->marcarAtrasadasProjeto($projetoId);
        $completo = $this->expoService->carregarProjetoCompleto($projetoId);
        $tarefas = $this->tarefaModel->listarMinhasAtribuicoes($alunoId, $projetoId);
        $concluidas = count(array_filter($tarefas, static fn($t) => ($t['status'] ?? '') === 'Concluida'));
        $stand = $this->standModel->findByProjeto($projetoId);

        $solicitacao = $this->statusSolicitacaoMateriais($projeto, $insc);

        return [
            'success' => true,
            'projeto' => $projeto,
            'inscricao' => $insc,
            'relacoes' => $completo['relacoes'] ?? [],
            'tarefas' => $tarefas,
            'progresso' => [
                'total' => count($tarefas),
                'concluidas' => $concluidas,
            ],
            'materiais' => $this->listarMateriais($projetoId),
            'pedidos' => $this->listarPedidosAlunoSeguro($alunoId, $projetoId),
            'pode_solicitar_materiais' => !empty($solicitacao['pode']),
            'motivo_solicitacao' => (string) ($solicitacao['motivo'] ?? ''),
            'mensagens' => $this->listarMensagensSeguro($projetoId),
            'stand' => $stand,
            'url_qr' => $stand ? $this->urlPublicaStand((string) $stand['qr_token']) : null,
        ];
    }

    /** @return list<array> */
    public function listarMateriais(int $projetoId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM expo_colag_projeto_materiais
             WHERE projeto_id = :id ORDER BY origem ASC, id ASC',
            ['id' => $projetoId]
        ) ?: [];
    }

    public function criarTarefa(int $projetoId, int $professorId, array $input): array
    {
        $projeto = $this->assertProjetoProfessor($projetoId, $professorId);
        if (!$projeto) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        $titulo = trim((string) ($input['titulo'] ?? ''));
        if ($titulo === '') {
            return ['success' => false, 'error' => 'Título da tarefa é obrigatório.'];
        }
        $tipo = (string) ($input['tipo_entregavel'] ?? 'Nenhum');
        if (!in_array($tipo, self::TIPOS_ENTREGAVEL, true)) {
            $tipo = 'Nenhum';
        }
        $etapaId = !empty($input['etapa_id']) ? (int) $input['etapa_id'] : null;
        if ($etapaId) {
            $etapa = $this->db->fetch(
                'SELECT id FROM expo_colag_projeto_etapas WHERE id = :id AND projeto_id = :p',
                ['id' => $etapaId, 'p' => $projetoId]
            );
            if (!$etapa) {
                return ['success' => false, 'error' => 'Etapa inválida.'];
            }
        }

        $atribuir = (string) ($input['atribuir'] ?? 'todos');
        $idsSelecionados = [];
        if ($atribuir === 'selecionados') {
            $ids = $input['inscricao_ids'] ?? [];
            if (is_string($ids)) {
                $ids = array_filter(array_map('intval', explode(',', $ids)));
            }
            if (is_array($ids)) {
                foreach ($ids as $iid) {
                    $insc = $this->inscricaoModel->findById((int) $iid);
                    if ($insc && (int) $insc['projeto_id'] === $projetoId && ($insc['status'] ?? '') === 'Aprovada') {
                        $idsSelecionados[] = (int) $iid;
                    }
                }
            }
            if ($idsSelecionados === []) {
                return ['success' => false, 'error' => 'Selecione pelo menos um aluno do grupo.'];
            }
        }

        $id = $this->tarefaModel->create([
            'projeto_id' => $projetoId,
            'etapa_id' => $etapaId,
            'titulo' => $titulo,
            'descricao' => $input['descricao'] ?? null,
            'tipo_entregavel' => $tipo,
            'data_limite' => $this->normalizarDateTime($input['data_limite'] ?? null),
            'obrigatoria' => !isset($input['obrigatoria']) || !empty($input['obrigatoria']),
            'criada_por' => $professorId,
        ]);

        if ($atribuir === 'selecionados') {
            $this->tarefaModel->atribuirInscricoes($id, $idsSelecionados);
        } else {
            $this->tarefaModel->atribuirAprovados($id, $projetoId);
        }

        return ['success' => true, 'id' => $id];
    }

    public function excluirTarefa(int $tarefaId, int $professorId, ?int $projetoIdEsperado = null): array
    {
        $tarefa = $this->tarefaModel->findById($tarefaId);
        if (!$tarefa || !$this->assertProjetoProfessor((int) $tarefa['projeto_id'], $professorId)) {
            return ['success' => false, 'error' => 'Tarefa não encontrada.'];
        }
        if ($projetoIdEsperado !== null && (int) $tarefa['projeto_id'] !== $projetoIdEsperado) {
            return ['success' => false, 'error' => 'Tarefa não encontrada.'];
        }
        $this->tarefaModel->excluir($tarefaId);
        return ['success' => true];
    }

    public function decidirAtribuicao(
        int $atribuicaoId,
        int $professorId,
        string $acao,
        ?string $comentario = null,
        ?int $projetoIdEsperado = null
    ): array {
        $atr = $this->tarefaModel->findAtribuicao($atribuicaoId);
        if (!$atr || !$this->assertProjetoProfessor((int) $atr['projeto_id'], $professorId)) {
            return ['success' => false, 'error' => 'Atribuição não encontrada.'];
        }
        if ($projetoIdEsperado !== null && (int) $atr['projeto_id'] !== $projetoIdEsperado) {
            return ['success' => false, 'error' => 'Atribuição não encontrada.'];
        }
        if ($acao === 'concluir') {
            $this->tarefaModel->atualizarAtribuicao($atribuicaoId, [
                'status' => 'Concluida',
                'comentario_professor' => $comentario,
                'marcar_avaliado' => true,
            ]);
            $tarefa = $this->tarefaModel->findById((int) $atr['tarefa_id']);
            $this->tarefaModel->tentarFecharEtapa($tarefa ? (int) ($tarefa['etapa_id'] ?? 0) : null);
            return ['success' => true];
        }
        if ($acao === 'devolver') {
            if (trim((string) $comentario) === '') {
                return ['success' => false, 'error' => 'Informe o comentário da devolução.'];
            }
            $this->tarefaModel->atualizarAtribuicao($atribuicaoId, [
                'status' => 'Devolvida',
                'comentario_professor' => trim($comentario),
                'marcar_avaliado' => true,
            ]);
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Ação inválida.'];
    }

    public function entregarTarefaAluno(
        int $atribuicaoId,
        int $alunoId,
        array $input,
        ?int $projetoIdEsperado = null
    ): array {
        $atr = $this->tarefaModel->findAtribuicao($atribuicaoId);
        if (!$atr || (int) $atr['aluno_id'] !== $alunoId || ($atr['inscricao_status'] ?? '') !== 'Aprovada') {
            return ['success' => false, 'error' => 'Tarefa não encontrada.'];
        }
        if ($projetoIdEsperado !== null && (int) $atr['projeto_id'] !== $projetoIdEsperado) {
            return ['success' => false, 'error' => 'Tarefa não encontrada.'];
        }
        if (!in_array($atr['status'], ['Pendente', 'Em_andamento', 'Atrasada', 'Devolvida'], true)) {
            return ['success' => false, 'error' => 'Esta tarefa não aceita nova entrega.'];
        }
        $tipo = (string) ($atr['tipo_entregavel'] ?? 'Nenhum');
        $conteudo = trim((string) ($input['entrega_conteudo'] ?? ''));
        $urlRaw = trim((string) ($input['entrega_arquivo_url'] ?? ''));
        $url = $urlRaw !== '' ? $this->urlHttpSegura($urlRaw) : null;
        if ($urlRaw !== '' && $url === null) {
            return ['success' => false, 'error' => 'Informe um link http(s) válido.'];
        }
        if ($tipo === 'Texto' && $conteudo === '') {
            return ['success' => false, 'error' => 'Envie o texto da entrega.'];
        }
        if ($tipo === 'Link' && $url === null && $conteudo === '') {
            return ['success' => false, 'error' => 'Informe o link da entrega.'];
        }
        if ($tipo === 'Arquivo' && $url === null) {
            return ['success' => false, 'error' => 'Informe a URL do arquivo entregue.'];
        }

        $statusEntrega = $tipo === 'Nenhum' ? 'Concluida' : 'Entregue';
        $this->tarefaModel->atualizarAtribuicao($atribuicaoId, [
            'status' => $statusEntrega,
            'entrega_conteudo' => $conteudo !== '' ? $conteudo : ($tipo === 'Nenhum' ? 'Concluído' : null),
            'entrega_arquivo_url' => $url,
            'marcar_entregue' => true,
            'marcar_avaliado' => $statusEntrega === 'Concluida',
        ]);
        if ($statusEntrega === 'Concluida') {
            $tarefa = $this->tarefaModel->findById((int) $atr['tarefa_id']);
            $this->tarefaModel->tentarFecharEtapa($tarefa ? (int) ($tarefa['etapa_id'] ?? 0) : null);
        }
        return ['success' => true];
    }

    public function adicionarMaterial(int $projetoId, int $professorId, array $input): array
    {
        if (!$this->assertProjetoProfessor($projetoId, $professorId)) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        $titulo = trim((string) ($input['titulo'] ?? ''));
        if ($titulo === '') {
            return ['success' => false, 'error' => 'Título é obrigatório.'];
        }
        $linkRaw = trim((string) ($input['link_externo'] ?? ''));
        $arquivoRaw = trim((string) ($input['arquivo_url'] ?? ''));
        $link = $linkRaw !== '' ? $this->urlHttpSegura($linkRaw) : null;
        $arquivo = $arquivoRaw !== '' ? $this->urlHttpSegura($arquivoRaw) : null;
        if (($linkRaw !== '' && $link === null) || ($arquivoRaw !== '' && $arquivo === null)) {
            return ['success' => false, 'error' => 'Informe um link http(s) válido.'];
        }
        $id = (int) $this->db->insert(
            'INSERT INTO expo_colag_projeto_materiais
                (projeto_id, etapa_id, titulo, tipo, arquivo_url, link_externo, enviado_por, versao, origem)
             VALUES
                (:pid, :etapa_id, :titulo, :tipo, :arquivo_url, :link_externo, :enviado_por, 1, \'Execucao\')',
            [
                'pid' => $projetoId,
                'etapa_id' => !empty($input['etapa_id']) ? (int) $input['etapa_id'] : null,
                'titulo' => mb_substr($titulo, 0, 255),
                'tipo' => mb_substr(trim((string) ($input['tipo'] ?? 'link')), 0, 60) ?: 'link',
                'arquivo_url' => $arquivo,
                'link_externo' => $link,
                'enviado_por' => $professorId,
            ]
        );
        return ['success' => true, 'id' => $id];
    }

    public function removerMaterial(int $materialId, int $professorId): array
    {
        $mat = $this->db->fetch(
            'SELECT * FROM expo_colag_projeto_materiais WHERE id = :id',
            ['id' => $materialId]
        );
        if (!$mat || !$this->assertProjetoProfessor((int) $mat['projeto_id'], $professorId)) {
            return ['success' => false, 'error' => 'Material não encontrado.'];
        }
        $this->db->query('DELETE FROM expo_colag_projeto_materiais WHERE id = :id', ['id' => $materialId]);
        return ['success' => true];
    }

    /**
     * @return array{pode:bool,motivo?:string}
     */
    private function statusSolicitacaoMateriais(array $projeto, ?array $inscricao = null): array
    {
        if (!$inscricao || ($inscricao['status'] ?? '') !== 'Aprovada') {
            return ['pode' => false, 'motivo' => 'Somente participantes aprovados podem solicitar materiais.'];
        }
        if (empty($projeto['permite_solicitacao_recursos'])) {
            return ['pode' => false, 'motivo' => 'Este projeto não aceita solicitação de materiais.'];
        }
        $ed = $this->expoService->obterOuCriarEdicaoAtiva();
        $config = $ed['edicao']['config_decoded'] ?? ExpoColagService::configPadrao();
        $limite = trim((string) ($config['limite_solicitacao_recursos'] ?? ''));
        if ($limite !== '') {
            $fim = strtotime($limite . ' 23:59:59');
            if ($fim && time() > $fim) {
                return ['pode' => false, 'motivo' => 'O prazo para solicitar materiais já encerrou.'];
            }
        }
        return ['pode' => true];
    }

    public function solicitarMaterialAluno(int $projetoId, int $alunoId, array $input): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto || empty($projeto['ativo']) || ($projeto['status'] ?? '') === 'Cancelado') {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        $insc = $this->assertAlunoAprovado($projetoId, $alunoId);
        if (!$insc) {
            return ['success' => false, 'error' => 'Você precisa estar aprovado neste projeto.'];
        }
        $status = $this->statusSolicitacaoMateriais($projeto, $insc);
        if (empty($status['pode'])) {
            return ['success' => false, 'error' => $status['motivo'] ?? 'Não é possível solicitar materiais agora.'];
        }
        $titulo = trim((string) ($input['titulo'] ?? ''));
        if ($titulo === '') {
            return ['success' => false, 'error' => 'Informe o material solicitado.'];
        }
        try {
            $id = $this->pedidoModel->create([
                'projeto_id' => $projetoId,
                'aluno_id' => $alunoId,
                'inscricao_id' => (int) $insc['id'],
                'titulo' => $titulo,
                'quantidade' => $input['quantidade'] ?? null,
                'observacao' => $input['observacao'] ?? null,
            ]);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Não foi possível registrar o pedido. Peça à coordenação para atualizar o módulo.'];
        }
        return ['success' => true, 'id' => $id];
    }

    public function decidirPedidoMaterial(
        int $pedidoId,
        int $professorId,
        string $acao,
        ?string $resposta = null,
        ?int $projetoIdEsperado = null
    ): array {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido || !$this->assertProjetoProfessor((int) $pedido['projeto_id'], $professorId)) {
            return ['success' => false, 'error' => 'Pedido não encontrado.'];
        }
        if ($projetoIdEsperado !== null && (int) $pedido['projeto_id'] !== $projetoIdEsperado) {
            return ['success' => false, 'error' => 'Pedido não encontrado.'];
        }
        if (($pedido['status'] ?? '') !== 'Pendente') {
            return ['success' => false, 'error' => 'Este pedido já foi decidido.'];
        }
        $status = $acao === 'aprovar' ? 'Aprovado' : ($acao === 'recusar' ? 'Recusado' : '');
        if ($status === '') {
            return ['success' => false, 'error' => 'Ação inválida.'];
        }
        $respostaTrim = trim((string) $resposta);
        if ($status === 'Recusado' && $respostaTrim === '') {
            return ['success' => false, 'error' => 'Informe o motivo da recusa.'];
        }
        $ok = $this->pedidoModel->decidir($pedidoId, $status, $professorId, $respostaTrim !== '' ? $respostaTrim : null);
        if (!$ok) {
            return ['success' => false, 'error' => 'Este pedido já foi decidido.'];
        }
        return ['success' => true];
    }

    /** @return list<array> */
    private function listarPedidosAlunoSeguro(int $alunoId, int $projetoId): array
    {
        try {
            return $this->pedidoModel->listarPorAlunoProjeto($alunoId, $projetoId);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array> */
    private function listarPedidosProjetoSeguro(int $projetoId): array
    {
        try {
            return $this->pedidoModel->listarPorProjeto($projetoId);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array> */
    private function listarMensagensSeguro(int $projetoId): array
    {
        try {
            return $this->mensagemModel->listarPorProjeto($projetoId);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function enviarMensagemProfessor(int $projetoId, int $professorId, string $texto): array
    {
        $projeto = $this->assertProjetoProfessor($projetoId, $professorId);
        if (!$projeto) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        if (($projeto['status'] ?? '') === 'Cancelado') {
            return ['success' => false, 'error' => 'Este projeto foi cancelado.'];
        }
        return $this->gravarMensagem($projetoId, 'professor', $professorId, $texto);
    }

    public function enviarMensagemAluno(int $projetoId, int $alunoId, string $texto): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto || empty($projeto['ativo']) || ($projeto['status'] ?? '') === 'Cancelado') {
            return ['success' => false, 'error' => 'Este projeto não está disponível.'];
        }
        if (!$this->assertAlunoAprovado($projetoId, $alunoId)) {
            return ['success' => false, 'error' => 'Você precisa estar no grupo deste projeto.'];
        }
        return $this->gravarMensagem($projetoId, 'aluno', $alunoId, $texto);
    }

    private function gravarMensagem(int $projetoId, string $tipo, int $autorId, string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['success' => false, 'error' => 'Escreva uma mensagem.'];
        }
        try {
            $this->mensagemModel->create([
                'projeto_id' => $projetoId,
                'autor_tipo' => $tipo,
                'autor_id' => $autorId,
                'mensagem' => $texto,
            ]);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Não foi possível enviar. Peça à coordenação para atualizar o módulo.'];
        }
        return ['success' => true];
    }

    /** @return list<array{nome:string,quantidade:string,observacao:string}> */
    public function itensPdfAlmoxarifado(int $projetoId): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        $itens = ExpoColagService::decodificarMateriaisNecessarios($projeto['materiais_necessarios'] ?? []);
        $out = [];
        foreach ($itens as $item) {
            $out[] = [
                'nome' => (string) ($item['nome'] ?? ''),
                'quantidade' => (string) ($item['quantidade'] ?? ''),
                'observacao' => (string) ($item['observacao'] ?? ''),
            ];
        }
        foreach ($this->listarPedidosProjetoSeguro($projetoId) as $pedido) {
            if (($pedido['status'] ?? '') !== 'Aprovado') {
                continue;
            }
            $obs = trim((string) ($pedido['observacao'] ?? ''));
            $aluno = trim((string) ($pedido['aluno_nome'] ?? 'aluno'));
            $out[] = [
                'nome' => (string) ($pedido['titulo'] ?? ''),
                'quantidade' => (string) ($pedido['quantidade'] ?? ''),
                'observacao' => trim('Pedido de ' . $aluno . ($obs !== '' ? ' — ' . $obs : '')),
            ];
        }
        return $out;
    }

    public function garantirStand(int $projetoId, int $professorId, array $input = []): array
    {
        $projeto = $this->assertProjetoProfessor($projetoId, $professorId);
        if (!$projeto) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        $edicaoId = (int) ($projeto['edicao_id'] ?? 0);
        if ($edicaoId <= 0) {
            $ed = $this->expoService->obterOuCriarEdicaoAtiva();
            $edicaoId = (int) ($ed['edicao']['id'] ?? 0);
        }
        $stand = $this->standModel->criarOuObter($edicaoId, $projetoId, [
            'resumo_publico' => $input['resumo_publico'] ?? ($projeto['produto_esperado'] ?? null),
            'capa_url' => $projeto['capa_url'] ?? null,
            'horario_apresentacao' => $projeto['data_apresentacao'] ?? null,
            'numero' => $input['numero'] ?? null,
            'setor_id' => $input['setor_id'] ?? null,
        ]);
        if (!empty($input['atualizar']) && !empty($stand['id'])) {
            $this->standModel->atualizar((int) $stand['id'], [
                'setor_id' => $input['setor_id'] ?? $stand['setor_id'] ?? null,
                'numero' => $input['numero'] ?? $stand['numero'] ?? null,
                'horario_apresentacao' => $this->normalizarDateTime($input['horario_apresentacao'] ?? $stand['horario_apresentacao'] ?? null),
                'resumo_publico' => $input['resumo_publico'] ?? $stand['resumo_publico'] ?? null,
                'capa_url' => $stand['capa_url'] ?? $projeto['capa_url'] ?? null,
                'ativo' => 1,
            ]);
            $stand = $this->standModel->findByProjeto($projetoId);
        }
        return [
            'success' => true,
            'stand' => $stand,
            'url_qr' => $this->urlPublicaStand((string) ($stand['qr_token'] ?? '')),
        ];
    }

    public function dadosStandPublico(string $token): array
    {
        if (!$this->featureAtiva()) {
            return ['success' => false, 'error' => 'Indisponível.'];
        }
        $stand = $this->standModel->findByToken($token);
        if (!$stand || empty($stand['ativo'])) {
            return ['success' => false, 'error' => 'Stand não encontrado.'];
        }
        if (empty($stand['projeto_ativo']) || ($stand['projeto_status'] ?? '') === 'Cancelado') {
            return ['success' => false, 'error' => 'Este stand não está mais ativo.', 'cancelado' => true];
        }
        $nomes = $this->standModel->primeirosNomesAprovados((int) $stand['projeto_id']);
        $capaRaw = trim((string) ($stand['capa_url'] ?: ($stand['projeto_capa_url'] ?? '')));
        $capa = ExpoColagService::resolverUrlCapa($capaRaw, (int) ($stand['projeto_id'] ?? 0));
        if ($capa === '') {
            $capa = null;
        }
        $resumo = trim((string) ($stand['resumo_publico'] ?? ''));
        if ($resumo === '') {
            $resumo = trim((string) ($stand['produto_esperado'] ?? ''));
        }

        return [
            'success' => true,
            'stand' => [
                'numero' => $stand['numero'] ?? null,
                'setor' => $stand['setor_nome'] ?? null,
                'horario_apresentacao' => $stand['horario_apresentacao'] ?? null,
                'titulo' => $stand['titulo'] ?? '',
                'subtitulo' => $stand['subtitulo'] ?? null,
                'area' => $stand['area'] ?? null,
                'resumo' => $resumo,
                'produto' => $stand['produto_esperado'] ?? null,
                'capa_url' => $capa,
                'participantes' => $nomes,
                'professor_nome' => $this->primeiroNome((string) ($stand['professor_nome'] ?? '')),
            ],
        ];
    }

    public function listarProgramacaoPublica(): array
    {
        $ed = $this->expoService->obterOuCriarEdicaoAtiva();
        $edicao = $ed['edicao'] ?? null;
        if (!$edicao) {
            return ['itens' => [], 'edicao' => null];
        }
        $pubEm = $edicao['programacao_publica_em'] ?? null;
        if ($pubEm && strtotime((string) $pubEm) > time()) {
            return ['itens' => [], 'edicao' => $edicao, 'ainda_nao_publica' => true];
        }
        return [
            'itens' => $this->programacaoModel->listarPorEdicao((int) $edicao['id']),
            'edicao' => $edicao,
        ];
    }

    public function adminProgramacao(): array
    {
        $ed = $this->expoService->obterOuCriarEdicaoAtiva();
        $edicao = $ed['edicao'] ?? null;
        if (!$edicao) {
            return ['success' => false, 'error' => 'Edição não encontrada.'];
        }
        $edicaoId = (int) $edicao['id'];
        return [
            'success' => true,
            'edicao' => $edicao,
            'itens' => $this->programacaoModel->listarPorEdicao($edicaoId),
            'setores' => $this->programacaoModel->listarSetores($edicaoId),
            'stands' => $this->standModel->listarPorEdicao($edicaoId),
        ];
    }

    public function salvarItemProgramacao(array $input): array
    {
        $ed = $this->expoService->obterOuCriarEdicaoAtiva();
        $edicaoId = (int) ($ed['edicao']['id'] ?? 0);
        if ($edicaoId <= 0) {
            return ['success' => false, 'error' => 'Edição não encontrada.'];
        }
        $titulo = trim((string) ($input['titulo'] ?? ''));
        $inicio = $this->normalizarDateTime($input['hora_inicio'] ?? null);
        if ($titulo === '' || !$inicio) {
            return ['success' => false, 'error' => 'Título e horário de início são obrigatórios.'];
        }
        $id = $this->programacaoModel->create([
            'edicao_id' => $edicaoId,
            'titulo' => $titulo,
            'descricao' => $input['descricao'] ?? null,
            'tipo' => $input['tipo'] ?? 'Geral',
            'hora_inicio' => $inicio,
            'hora_fim' => $this->normalizarDateTime($input['hora_fim'] ?? null),
            'local' => $input['local'] ?? null,
            'setor_id' => $input['setor_id'] ?? null,
            'ordem' => $input['ordem'] ?? 1,
        ]);
        return ['success' => true, 'id' => $id];
    }

    public function excluirItemProgramacao(int $id): array
    {
        $item = $this->programacaoModel->findById($id);
        if (!$item) {
            return ['success' => false, 'error' => 'Item não encontrado.'];
        }
        $this->programacaoModel->excluir($id);
        return ['success' => true];
    }

    public function criarSetor(string $nome): array
    {
        $ed = $this->expoService->obterOuCriarEdicaoAtiva();
        $edicaoId = (int) ($ed['edicao']['id'] ?? 0);
        $nome = trim($nome);
        if ($edicaoId <= 0 || $nome === '') {
            return ['success' => false, 'error' => 'Nome do setor é obrigatório.'];
        }
        $id = $this->programacaoModel->criarSetor($edicaoId, $nome);
        return ['success' => true, 'id' => $id];
    }

    public function indicadoresExtrasProfessor(int $professorId): array
    {
        return [
            'tarefas_atrasadas' => $this->tarefaModel->contarAtrasadasProfessor($professorId),
            'entregas_avaliar' => $this->tarefaModel->contarPendentesAvaliacaoProfessor($professorId),
        ];
    }

    public function indicadoresExtrasAdmin(): array
    {
        return [
            'tarefas_atrasadas' => $this->tarefaModel->contarAtrasadasTodos(),
            'entregas_avaliar' => $this->tarefaModel->contarPendentesAvaliacaoTodos(),
        ];
    }

    public function urlPublicaStand(string $token): string
    {
        $base = defined('URL') ? rtrim((string) URL, '/') : '';
        return $base . '/expo-colag/s/' . $token;
    }

    private function featureAtiva(): bool
    {
        if (!class_exists('FeatureGate', false)) {
            $path = dirname(__DIR__, 3) . '/Core/FeatureGate.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (!class_exists('FeatureGate')) {
            return true;
        }
        try {
            return FeatureGate::isModuleEnabled('expo_colag');
        } catch (Throwable $e) {
            return false;
        }
    }

    private function primeiroNome(string $nome): ?string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }
        $partes = preg_split('/\s+/', $nome) ?: [];
        return $partes[0] ?? null;
    }

    private function normalizarDateTime($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $v)) {
            $v = str_replace('T', ' ', substr($v, 0, 16)) . ':00';
        }
        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    /** Aceita só http/https (bloqueia javascript:/data: etc.). */
    private function urlHttpSegura(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (strlen($url) > 500) {
            return null;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        return $url;
    }
}
