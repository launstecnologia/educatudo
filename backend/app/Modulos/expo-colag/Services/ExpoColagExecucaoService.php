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
require_once dirname(__DIR__, 3) . '/Utils/HtmlSanitizer.php';

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
    private const MAX_CONTEUDO_BYTES = 20 * 1024 * 1024;

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
        if (!$projeto || empty($projeto['ativo']) || (int) $projeto['professor_id'] !== $professorId) {
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
            'conteudos' => $this->listarConteudos($projetoId),
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
        if (!$projeto || empty($projeto['ativo'])) {
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
            'conteudos' => $this->listarConteudos($projetoId),
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
            'conteudos' => $this->listarConteudos($projetoId),
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

    /** @return list<array> */
    public function listarConteudos(int $projetoId): array
    {
        $rows = $this->listarMateriais($projetoId);
        foreach ($rows as &$row) {
            $meta = json_decode((string) ($row['visibilidade'] ?? ''), true);
            $row['meta'] = is_array($meta) ? $meta : [];
        }
        unset($row);
        return $rows;
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

    public function salvarItensMateriais(int $projetoId, int $professorId, array $input): array
    {
        if (!$this->assertProjetoProfessor($projetoId, $professorId)) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        $nomes = is_array($input['item_nome'] ?? null) ? $input['item_nome'] : [];
        $quantidades = is_array($input['item_quantidade'] ?? null) ? $input['item_quantidade'] : [];
        $observacoes = is_array($input['item_observacao'] ?? null) ? $input['item_observacao'] : [];
        $itens = [];
        foreach ($nomes as $idx => $nomeRaw) {
            $nome = trim((string) $nomeRaw);
            if ($nome === '') {
                continue;
            }
            if (count($itens) >= 50) {
                break;
            }
            $itens[] = [
                'nome' => mb_substr($nome, 0, 180),
                'quantidade' => mb_substr(trim((string) ($quantidades[$idx] ?? '')), 0, 80),
                'observacao' => mb_substr(trim((string) ($observacoes[$idx] ?? '')), 0, 255),
            ];
        }

        $this->projetoModel->update($projetoId, [
            'materiais_necessarios' => $itens !== [] ? json_encode($itens, JSON_UNESCAPED_UNICODE) : null,
        ]);
        return ['success' => true];
    }

    public function adicionarConteudo(int $projetoId, int $professorId, array $input, ?array $arquivo = null): array
    {
        if (!$this->assertProjetoProfessor($projetoId, $professorId)) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }

        $titulo = trim((string) ($input['titulo'] ?? ''));
        if ($titulo === '') {
            return ['success' => false, 'error' => 'Título é obrigatório.'];
        }

        $descricao = \App\Utils\HtmlSanitizer::clean((string) ($input['descricao_html'] ?? ''));
        $linkRaw = trim((string) ($input['link_externo'] ?? ''));
        $youtubeRaw = trim((string) ($input['youtube_url'] ?? ''));
        $link = $linkRaw !== '' ? $this->urlHttpSegura($linkRaw) : null;
        $youtube = $youtubeRaw !== '' ? $this->urlYoutubeSeguro($youtubeRaw) : null;
        if (($linkRaw !== '' && $link === null) || ($youtubeRaw !== '' && $youtube === null)) {
            return ['success' => false, 'error' => 'Informe links http(s) válidos.'];
        }

        $upload = $this->salvarArquivoConteudo($projetoId, $arquivo);
        if (!empty($upload['error'])) {
            return ['success' => false, 'error' => $upload['error']];
        }

        $arquivoUrl = $upload['url'] ?? null;
        if ($descricao === '' && $link === null && $youtube === null && $arquivoUrl === null) {
            return ['success' => false, 'error' => 'Informe um texto, link, YouTube ou anexo.'];
        }

        $meta = [
            'descricao_html' => $descricao,
            'youtube_url' => $youtube,
            'arquivo_nome' => $upload['nome_original'] ?? null,
            'arquivo_tamanho' => $upload['tamanho'] ?? null,
            'arquivo_mime' => $upload['mime'] ?? null,
        ];

        $id = (int) $this->db->insert(
            'INSERT INTO expo_colag_projeto_materiais
                (projeto_id, etapa_id, titulo, tipo, arquivo_url, link_externo, visibilidade, enviado_por, versao, origem)
             VALUES
                (:pid, NULL, :titulo, :tipo, :arquivo_url, :link_externo, :visibilidade, :enviado_por, 1, \'Execucao\')',
            [
                'pid' => $projetoId,
                'titulo' => mb_substr($titulo, 0, 255),
                'tipo' => 'conteudo',
                'arquivo_url' => $arquivoUrl,
                'link_externo' => $link,
                'visibilidade' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'enviado_por' => $professorId,
            ]
        );
        return ['success' => true, 'id' => $id];
    }

    public function removerConteudo(int $conteudoId, int $professorId): array
    {
        return $this->removerMaterial($conteudoId, $professorId);
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
                'id' => (int) ($stand['id'] ?? 0),
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
            'avaliacoes' => $this->standModel->resumoAvaliacoes((int) ($stand['id'] ?? 0)),
        ];
    }

    public function registrarAvaliacaoStand(string $token, array $input): array
    {
        if (!$this->featureAtiva()) {
            return ['success' => false, 'error' => 'Indisponível.'];
        }
        $stand = $this->standModel->findByToken($token);
        if (!$stand || empty($stand['ativo']) || empty($stand['projeto_ativo']) || ($stand['projeto_status'] ?? '') === 'Cancelado') {
            return ['success' => false, 'error' => 'Stand não encontrado.'];
        }
        $nota = (int) ($input['nota'] ?? 0);
        if ($nota < 5 || $nota > 10) {
            return ['success' => false, 'error' => 'Escolha uma nota de 5 a 10.'];
        }
        $mensagem = mb_substr(trim((string) ($input['mensagem'] ?? '')), 0, 500);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $id = $this->standModel->registrarAvaliacao(
            (int) $stand['id'],
            (int) $stand['projeto_id'],
            $nota,
            $mensagem !== '' ? $mensagem : null,
            $ip !== '' ? hash('sha256', $ip) : null,
            $ua !== '' ? $ua : null
        );
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Não foi possível salvar a avaliação agora.'];
        }
        return ['success' => true];
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

    private function urlYoutubeSeguro(?string $url): ?string
    {
        $url = $this->urlHttpSegura($url);
        if ($url === null) {
            return null;
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        if (!in_array($host, ['youtube.com', 'youtu.be', 'm.youtube.com'], true)) {
            return null;
        }
        return $url;
    }

    private function salvarArquivoConteudo(int $projetoId, ?array $file): array
    {
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        $erro = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($erro !== UPLOAD_ERR_OK) {
            return ['error' => 'Não foi possível enviar o anexo.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return ['error' => 'Anexo inválido.'];
        }
        $tamanho = (int) ($file['size'] ?? filesize($tmp));
        if ($tamanho <= 0 || $tamanho > self::MAX_CONTEUDO_BYTES) {
            return ['error' => 'Anexo deve ter no máximo 20 MB.'];
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : (string) ($file['type'] ?? '');
        if ($finfo) {
            finfo_close($finfo);
        }
        $permitidos = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ];
        if (!isset($permitidos[$mime])) {
            return ['error' => 'Use PDF, Word, imagem ou vídeo em formato comum.'];
        }

        $slug = $this->slugTenant();
        $filename = 'conteudo_' . $projetoId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
        $relDir = 'expo-colag/' . $slug . '/conteudos';
        $backend = $this->raizBackend();
        $publicDir = $backend . '/public/uploads/' . $relDir;
        if (!$this->garantirPasta($publicDir)) {
            return ['error' => 'Não foi possível preparar a pasta de uploads.'];
        }

        $destPublic = $publicDir . '/' . $filename;
        $moveu = @move_uploaded_file($tmp, $destPublic);
        if (!$moveu) {
            $moveu = @copy($tmp, $destPublic);
        }
        if (!$moveu || !is_file($destPublic) || filesize($destPublic) <= 0) {
            return ['error' => 'Não foi possível gravar o anexo.'];
        }
        @chmod($destPublic, 0644);

        $storageDir = $backend . '/storage/uploads/' . $relDir;
        if ($this->garantirPasta($storageDir)) {
            @copy($destPublic, $storageDir . '/' . $filename);
        }

        return [
            'url' => '/uploads/' . $relDir . '/' . $filename,
            'nome_original' => mb_substr((string) ($file['name'] ?? $filename), 0, 255),
            'tamanho' => $tamanho,
            'mime' => $mime,
        ];
    }

    private function slugTenant(): string
    {
        $slug = defined('TENANT_SLUG') ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) TENANT_SLUG) : 'tenant';
        return $slug !== '' ? $slug : 'tenant';
    }

    private function raizBackend(): string
    {
        if (defined('BASE_PATH') && (string) BASE_PATH !== '') {
            return rtrim((string) BASE_PATH, '/');
        }
        return dirname(__DIR__, 4);
    }

    private function garantirPasta(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }
        $old = umask(0002);
        @mkdir($dir, 0775, true);
        umask($old);
        if (!is_dir($dir)) {
            return false;
        }
        @chmod($dir, 0775);
        return true;
    }
}
