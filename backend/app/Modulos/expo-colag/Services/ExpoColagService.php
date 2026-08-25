<?php
/**
 * Lógica de negócio da Expo Colag (edição, wizard de projeto, autorização de imagem).
 */

require_once __DIR__ . '/../Models/ExpoColagEdicao.php';
require_once __DIR__ . '/../Models/ExpoColagProjeto.php';
require_once __DIR__ . '/../Models/ExpoColagInscricao.php';
require_once __DIR__ . '/../Models/ExpoColagProjetoRelacoes.php';
require_once __DIR__ . '/../Models/ExpoColagAutorizacaoImagem.php';

class ExpoColagService
{
    private ExpoColagEdicao $edicaoModel;
    private ExpoColagProjeto $projetoModel;
    private ExpoColagInscricao $inscricaoModel;
    private ExpoColagProjetoRelacoes $relacoesModel;
    private ExpoColagAutorizacaoImagem $autorizacaoModel;
    private $db;

    public function __construct()
    {
        $this->edicaoModel = new ExpoColagEdicao();
        $this->projetoModel = new ExpoColagProjeto();
        $this->inscricaoModel = new ExpoColagInscricao();
        $this->relacoesModel = new ExpoColagProjetoRelacoes();
        $this->autorizacaoModel = new ExpoColagAutorizacaoImagem();
        $this->db = Database::getInstance();
    }

    /**
     * Defaults pedagógicos (SPEC §2) — editáveis sem deploy via config da edição.
     *
     * @return array<string, mixed>
     */
    public static function configPadrao(): array
    {
        return [
            'grupo_min' => 3,
            'grupo_max' => 5,
            'permite_individual' => true,
            'max_projetos_aluno' => 1,
            'cross_turma' => true,
            'max_projetos_professor' => 3,
            'inscricoes_inicio' => '2026-08-25',
            'inscricoes_fim' => '2026-09-05',
            'limite_solicitacao_recursos' => '2026-09-11',
            'fluxo_aprovacao_recurso' => 'professor_coordenacao',
            'vale_nota' => false,
            'avaliadores_por_stand' => 2,
            'voto_publico_ativo' => true,
            'checkin_ativo' => true,
            'upload_foto_responsavel' => false,
            'politica_atraso' => 'aceitar_com_marcacao',
            'criterios_banca' => [
                ['criterio' => 'Rigor e fundamentação', 'peso' => 25],
                ['criterio' => 'Criatividade e originalidade', 'peso' => 20],
                ['criterio' => 'Aplicação prática / relevância social', 'peso' => 20],
                ['criterio' => 'Qualidade da apresentação', 'peso' => 20],
                ['criterio' => 'Trabalho em equipe', 'peso' => 15],
            ],
            'categorias_premiacao' => [
                'Destaque em Rigor Científico',
                'Criatividade e Inovação',
                'Impacto Social',
                'Melhor Apresentação',
                'Escolha do Público',
                'Destaque por Setor',
            ],
            'materiais_proibidos' => [],
            'catalogo_recursos' => [],
        ];
    }

    /**
     * Garante edição ativa; cria 2026 se não existir.
     *
     * @return array{success: bool, edicao?: array, error?: string}
     */
    public function obterOuCriarEdicaoAtiva(): array
    {
        $edicao = $this->edicaoModel->findAtiva();
        if ($edicao) {
            $edicao['config_decoded'] = $this->decodificarConfig($edicao['config'] ?? null);
            return ['success' => true, 'edicao' => $edicao];
        }

        $config = json_encode(self::configPadrao(), JSON_UNESCAPED_UNICODE);
        $id = $this->edicaoModel->create([
            'nome' => 'Expo Colag',
            'edicao' => '2026',
            'tema' => null,
            'data_evento' => '2026-10-03',
            'config' => $config,
            'status' => 'Planejamento',
        ]);

        $edicao = $this->edicaoModel->findById($id);
        if (!$edicao) {
            return ['success' => false, 'error' => 'Não foi possível criar a edição da Expo Colag.'];
        }
        $edicao['config_decoded'] = self::configPadrao();
        return ['success' => true, 'edicao' => $edicao];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, edicao?: array, error?: string}
     */
    public function salvarConfiguracaoEdicao(int $edicaoId, array $input): array
    {
        $edicao = $this->edicaoModel->findById($edicaoId);
        if (!$edicao) {
            return ['success' => false, 'error' => 'Edição não encontrada.'];
        }

        $configAtual = $this->decodificarConfig($edicao['config'] ?? null);
        $configNova = array_merge($configAtual, $this->filtrarConfigInput($input));

        $dados = [
            'nome' => trim((string) ($input['nome'] ?? $edicao['nome'])) ?: 'Expo Colag',
            'edicao' => trim((string) ($input['edicao'] ?? $edicao['edicao'])) ?: '2026',
            'tema' => trim((string) ($input['tema'] ?? '')) ?: null,
            'data_evento' => $this->normalizarData($input['data_evento'] ?? $edicao['data_evento']),
            'hora_inicio' => trim((string) ($input['hora_inicio'] ?? '')) ?: null,
            'hora_fim' => trim((string) ($input['hora_fim'] ?? '')) ?: null,
            'local' => trim((string) ($input['local'] ?? '')) ?: null,
            'voto_publico_ativo' => !empty($input['voto_publico_ativo']) ? 1 : 0,
            'checkin_ativo' => !empty($input['checkin_ativo']) ? 1 : 0,
            'config' => json_encode($configNova, JSON_UNESCAPED_UNICODE),
        ];

        if (!empty($input['status']) && is_string($input['status'])) {
            $statusOk = ['Planejamento', 'Publicada', 'Em_andamento', 'Encerrada', 'Arquivada'];
            if (in_array($input['status'], $statusOk, true)) {
                $dados['status'] = $input['status'];
            }
        }

        $this->edicaoModel->update($edicaoId, $dados);
        $atualizada = $this->edicaoModel->findById($edicaoId);
        if ($atualizada) {
            $atualizada['config_decoded'] = $this->decodificarConfig($atualizada['config'] ?? null);
        }

        return ['success' => true, 'edicao' => $atualizada];
    }

    /**
     * Vagas do professor na edição (rascunho e cancelado não ocupam limite).
     *
     * @return array{atingiu: bool, max: int, atual: int, error?: string}
     */
    public function situacaoLimiteProfessor(int $professorId, ?int $edicaoId = null, ?int $excetoProjetoId = null): array
    {
        $edicaoResult = $this->obterOuCriarEdicaoAtiva();
        if (!$edicaoResult['success']) {
            return ['atingiu' => false, 'max' => 3, 'atual' => 0];
        }
        $edicao = $edicaoResult['edicao'];
        $config = $edicao['config_decoded'] ?? self::configPadrao();
        $max = max(1, (int) ($config['max_projetos_professor'] ?? 3));
        $eid = ($edicaoId !== null && $edicaoId > 0) ? $edicaoId : (int) $edicao['id'];
        $projetos = $this->projetoModel->listarPorProfessor($professorId, $eid);
        $atual = 0;
        foreach ($projetos as $p) {
            if ($excetoProjetoId !== null && $excetoProjetoId > 0 && (int) $p['id'] === $excetoProjetoId) {
                continue;
            }
            $st = (string) ($p['status'] ?? '');
            if ($st === 'Cancelado' || $st === 'Rascunho') {
                continue;
            }
            $atual++;
        }

        $out = ['atingiu' => $atual >= $max, 'max' => $max, 'atual' => $atual];
        if ($out['atingiu']) {
            $out['error'] = "Limite de {$max} projetos por professor atingido nesta edição.";
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, id?: int, error?: string}
     */
    public function salvarRascunhoProjeto(int $professorId, array $input): array
    {
        $titulo = trim((string) ($input['titulo'] ?? ''));
        if ($titulo === '') {
            return ['success' => false, 'error' => 'Título é obrigatório.'];
        }

        $edicaoResult = $this->obterOuCriarEdicaoAtiva();
        if (!$edicaoResult['success']) {
            return $edicaoResult;
        }
        $edicao = $edicaoResult['edicao'];
        $config = $edicao['config_decoded'] ?? self::configPadrao();

        $limite = $this->situacaoLimiteProfessor($professorId, (int) $edicao['id']);
        if ($limite['atingiu']) {
            return [
                'success' => false,
                'error' => $limite['error'] ?? "Limite de {$limite['max']} projetos por professor atingido nesta edição.",
            ];
        }

        $id = $this->projetoModel->create([
            'edicao_id' => (int) $edicao['id'],
            'titulo' => $titulo,
            'subtitulo' => trim((string) ($input['subtitulo'] ?? '')) ?: null,
            'area' => trim((string) ($input['area'] ?? '')) ?: null,
            'professor_id' => $professorId,
            'descricao' => trim((string) ($input['descricao'] ?? '')) ?: null,
            'modalidade' => $this->modalidadeValida($input['modalidade'] ?? 'Grupo'),
            'vagas_totais' => max(1, (int) ($input['vagas_totais'] ?? ($config['grupo_max'] ?? 5))),
            'vagas_minimas' => max(1, (int) ($input['vagas_minimas'] ?? ($config['grupo_min'] ?? 3))),
            'modo_ingresso' => 'Livre',
            'inscricoes_inicio' => ($config['inscricoes_inicio'] ?? null) ? ($config['inscricoes_inicio'] . ' 00:00:00') : null,
            'inscricoes_fim' => ($config['inscricoes_fim'] ?? null) ? ($config['inscricoes_fim'] . ' 23:59:59') : null,
            'vale_nota' => !empty($config['vale_nota']) ? 1 : 0,
            'status' => 'Rascunho',
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function alterarStatusProjeto(int $projetoId, int $professorId, string $novoStatus, ?string $motivo = null): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto || (int) $projeto['professor_id'] !== $professorId) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }

        $atual = (string) $projeto['status'];
        if (!$this->projetoModel->podeTransitar($atual, $novoStatus)) {
            return ['success' => false, 'error' => "Não é possível alterar de {$atual} para {$novoStatus}."];
        }

        if ($novoStatus === 'Cancelado' && trim((string) $motivo) === '') {
            return ['success' => false, 'error' => 'Justificativa obrigatória para cancelar.'];
        }

        $dados = ['status' => $novoStatus];
        if ($novoStatus === 'Cancelado') {
            $dados['motivo_cancelamento'] = trim((string) $motivo);
        }
        if ($novoStatus === 'Publicado' && empty($projeto['publicar_em'])) {
            $dados['publicar_em'] = date('Y-m-d H:i:s');
        }

        $this->projetoModel->update($projetoId, $dados);
        return ['success' => true];
    }

    /**
     * Remove o projeto e dependências. Bloqueia se houver inscrição ativa.
     *
     * @return array{success: bool, error?: string}
     */
    public function excluirProjeto(int $projetoId, int $professorId): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto || (int) $projeto['professor_id'] !== $professorId) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        if ((string) ($projeto['status'] ?? '') === 'Concluido') {
            return ['success' => false, 'error' => 'Projeto concluído não pode ser excluído.'];
        }
        try {
            $this->db->beginTransaction();
            $this->db->fetch(
                'SELECT id FROM expo_colag_projetos WHERE id = :id FOR UPDATE',
                ['id' => $projetoId]
            );
            if ($this->inscricaoModel->contarAtivasPorProjeto($projetoId) > 0) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Há alunos inscritos neste projeto. Cancele as inscrições antes de excluir.'];
            }
            $this->apagarDependenciasProjeto($projetoId);
            $this->projetoModel->excluir($projetoId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('ExpoColagService::excluirProjeto: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível excluir o projeto.'];
        }

        return ['success' => true];
    }

    private function apagarDependenciasProjeto(int $projetoId): void
    {
        $params = ['id' => $projetoId];
        $this->db->query(
            'DELETE a FROM expo_colag_projeto_tarefa_atribuicoes a
             INNER JOIN expo_colag_projeto_tarefas t ON t.id = a.tarefa_id
             WHERE t.projeto_id = :id',
            $params
        );
        $this->db->query('DELETE FROM expo_colag_projeto_tarefas WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_inscricoes WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_stands WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_materias WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_professores WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_objetivos WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_tipos_trabalho WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_papeis WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_habilidades WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_visibilidade WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_etapas WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_encontros WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_rubrica WHERE projeto_id = :id', $params);
        $this->db->query('DELETE FROM expo_colag_projeto_materiais WHERE projeto_id = :id', $params);
    }

    public function indicadoresProfessor(int $professorId): array
    {
        $porStatus = $this->projetoModel->contarPorStatusProfessor($professorId);
        $ativos = 0;
        foreach ($porStatus as $status => $total) {
            if (!in_array($status, ['Cancelado', 'Concluido'], true)) {
                $ativos += $total;
            }
        }
        return [
            'ativos' => $ativos,
            'inscricoes_pendentes' => $this->inscricaoModel->contarPendentesProfessor($professorId),
            'por_status' => $porStatus,
        ];
    }

    public function listarProjetosProfessor(int $professorId): array
    {
        return $this->projetoModel->listarPorProfessor($professorId);
    }

    /**
     * @param array{area?:string,so_com_vagas?:bool,encerrando?:bool,q?:string} $filtros
     */
    public function listarMuralAluno(int $alunoId = 0, array $filtros = []): array
    {
        $projetos = $this->projetoModel->listarPublicados();
        $ctx = $alunoId > 0 ? $this->contextoVisibilidadeAluno($alunoId) : null;
        $minhasAtivas = $alunoId > 0 ? $this->inscricaoModel->projetosAtivosIds($alunoId) : [];
        $agora = time();
        $out = [];
        foreach ($projetos as $p) {
            if ($ctx && !$this->alunoPodeVerProjeto((int) $p['id'], $ctx)) {
                continue;
            }
            $aprovadas = $this->inscricaoModel->contarAprovadas((int) $p['id']);
            $vagas = max(0, (int) $p['vagas_totais']);
            $p['vagas_preenchidas'] = $aprovadas;
            $p['vagas_restantes'] = max(0, $vagas - $aprovadas);
            $p['lotado'] = $vagas > 0 && $aprovadas >= $vagas;
            $p['janela_aberta'] = $this->janelaInscricaoAberta($p);
            $p['minha_inscricao'] = in_array((int) $p['id'], $minhasAtivas, true);

            if (!empty($filtros['area']) && strcasecmp((string) ($p['area'] ?? ''), (string) $filtros['area']) !== 0) {
                continue;
            }
            if (!empty($filtros['so_com_vagas']) && !empty($p['lotado'])) {
                continue;
            }
            if (!empty($filtros['encerrando'])) {
                $fim = !empty($p['inscricoes_fim']) ? strtotime((string) $p['inscricoes_fim']) : false;
                if (!$fim || $fim < $agora || ($fim - $agora) > 7 * 86400) {
                    continue;
                }
            }
            if (!empty($filtros['q'])) {
                $q = mb_strtolower(trim((string) $filtros['q']));
                $hay = mb_strtolower(($p['titulo'] ?? '') . ' ' . ($p['subtitulo'] ?? '') . ' ' . ($p['area'] ?? '') . ' ' . ($p['professor_nome'] ?? ''));
                if ($q !== '' && strpos($hay, $q) === false) {
                    continue;
                }
            }
            $out[] = $p;
        }
        return $out;
    }

    /** @return array{destaques:list,abertas:list,meus:list,encerrando:list} */
    public function organizarMural(array $projetos): array
    {
        $agora = time();
        $destaques = [];
        $abertas = [];
        $meus = [];
        $encerrando = [];
        foreach ($projetos as $p) {
            if (!empty($p['minha_inscricao'])) {
                $meus[] = $p;
            }
            if (!empty($p['destaque'])) {
                $destaques[] = $p;
            }
            if (!empty($p['janela_aberta']) && empty($p['lotado'])) {
                $abertas[] = $p;
            }
            $fim = !empty($p['inscricoes_fim']) ? strtotime((string) $p['inscricoes_fim']) : false;
            if ($fim && $fim >= $agora && ($fim - $agora) <= 7 * 86400) {
                $encerrando[] = $p;
            }
        }
        return compact('destaques', 'abertas', 'meus', 'encerrando');
    }

    /**
     * @return array{aluno_id:int,turma_id:int,serie_id:int}
     */
    public function contextoVisibilidadeAluno(int $alunoId): array
    {
        $aluno = $this->db->fetch(
            'SELECT a.id, a.turma_id, t.serie_id
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id',
            ['id' => $alunoId]
        ) ?: [];
        return [
            'aluno_id' => $alunoId,
            'turma_id' => (int) ($aluno['turma_id'] ?? 0),
            'serie_id' => (int) ($aluno['serie_id'] ?? 0),
        ];
    }

    /** @param array{aluno_id:int,turma_id:int,serie_id:int} $ctx */
    public function alunoPodeVerProjeto(int $projetoId, array $ctx): bool
    {
        $rows = $this->db->fetchAll(
            'SELECT escopo, referencia_id FROM expo_colag_projeto_visibilidade WHERE projeto_id = :id',
            ['id' => $projetoId]
        ) ?: [];
        // Sem regra = legado/rascunho antigo: não exibir no mural do aluno
        if ($rows === []) {
            return false;
        }
        foreach ($rows as $r) {
            $escopo = (string) ($r['escopo'] ?? '');
            $ref = (int) ($r['referencia_id'] ?? 0);
            if ($escopo === 'Aluno' && $ref === (int) $ctx['aluno_id']) {
                return true;
            }
            if ($escopo === 'Turma' && $ref === (int) $ctx['turma_id'] && $ref > 0) {
                return true;
            }
            if ($escopo === 'Serie' && $ref === (int) $ctx['serie_id'] && $ref > 0) {
                return true;
            }
        }
        return false;
    }

    public function janelaInscricaoAberta(array $projeto): bool
    {
        $status = (string) ($projeto['status'] ?? '');
        if (in_array($status, ['Rascunho', 'Cancelado', 'Concluido', 'Avaliacao', 'Entrega'], true)) {
            return false;
        }
        $agora = date('Y-m-d H:i:s');
        $ini = $projeto['inscricoes_inicio'] ?? null;
        $fim = $projeto['inscricoes_fim'] ?? null;
        if ($ini && $agora < $ini) {
            return false;
        }
        if ($fim && $agora > $fim) {
            return false;
        }
        return in_array($status, ['Publicado', 'Inscricoes_abertas', 'Em_execucao'], true);
    }

    /**
     * Avalia conflitos de agenda do aluno com o projeto alvo.
     *
     * @return array{bloqueios:list<string>,alertas:list<string>,infos:list<string>}
     */
    public function avaliarConflitos(int $alunoId, int $projetoId): array
    {
        $bloqueios = [];
        $alertas = [];
        $infos = [];

        $encontrosNovo = $this->db->fetchAll(
            'SELECT rotulo, data_hora FROM expo_colag_projeto_encontros WHERE projeto_id = :id',
            ['id' => $projetoId]
        ) ?: [];
        $projeto = $this->projetoModel->findById($projetoId);
        $apresentacaoNovo = $projeto['data_apresentacao'] ?? null;

        $projetosAtivos = $this->inscricaoModel->projetosAtivosIds($alunoId);
        foreach ($projetosAtivos as $pid) {
            if ($pid === $projetoId) {
                continue;
            }
            $encontrosOutro = $this->db->fetchAll(
                'SELECT rotulo, data_hora FROM expo_colag_projeto_encontros WHERE projeto_id = :id',
                ['id' => $pid]
            ) ?: [];
            foreach ($encontrosNovo as $en) {
                $t1 = strtotime((string) ($en['data_hora'] ?? ''));
                if (!$t1) {
                    continue;
                }
                foreach ($encontrosOutro as $eo) {
                    $t2 = strtotime((string) ($eo['data_hora'] ?? ''));
                    if (!$t2) {
                        continue;
                    }
                    // Mesmo horário (janela de 1h)
                    if (abs($t1 - $t2) < 3600) {
                        $bloqueios[] = 'Há um encontro ao vivo no mesmo horário em outro projeto (' .
                            date('d/m H:i', $t1) . ').';
                    }
                }
            }

            $outro = $this->projetoModel->findById($pid);
            if ($apresentacaoNovo && !empty($outro['data_apresentacao'])) {
                $d1 = date('Y-m-d', strtotime((string) $apresentacaoNovo));
                $d2 = date('Y-m-d', strtotime((string) $outro['data_apresentacao']));
                if ($d1 === $d2) {
                    $alertas[] = 'A apresentação final coincide com a de outro projeto no dia ' .
                        date('d/m/Y', strtotime($d1)) . '. Confirme se deseja continuar.';
                }
            }

            $etapasNovo = $this->db->fetchAll(
                'SELECT data_limite FROM expo_colag_projeto_etapas WHERE projeto_id = :id AND data_limite IS NOT NULL',
                ['id' => $projetoId]
            ) ?: [];
            $etapasOutro = $this->db->fetchAll(
                'SELECT data_limite FROM expo_colag_projeto_etapas WHERE projeto_id = :id AND data_limite IS NOT NULL',
                ['id' => $pid]
            ) ?: [];
            $diasOutro = [];
            foreach ($etapasOutro as $e) {
                $diasOutro[substr((string) $e['data_limite'], 0, 10)] = true;
            }
            foreach ($etapasNovo as $e) {
                $d = substr((string) $e['data_limite'], 0, 10);
                if ($d !== '' && isset($diasOutro[$d])) {
                    $infos[] = 'Há etapas com prazo no mesmo dia (' . date('d/m/Y', strtotime($d)) . ') em outro projeto.';
                    break;
                }
            }
        }

        return [
            'bloqueios' => array_values(array_unique($bloqueios)),
            'alertas' => array_values(array_unique($alertas)),
            'infos' => array_values(array_unique($infos)),
        ];
    }

    /**
     * Situação do aluno frente ao projeto (para CTA).
     *
     * @return array<string,mixed>
     */
    public function statusInscricaoParaAluno(int $projetoId, int $alunoId): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto) {
            return ['pode_inscrever' => false, 'motivo' => 'Projeto não encontrado.'];
        }

        $edicao = $this->obterOuCriarEdicaoAtiva();
        $config = $edicao['edicao']['config_decoded'] ?? self::configPadrao();
        $max = (int) ($config['max_projetos_aluno'] ?? 1);

        $existente = $this->inscricaoModel->findByProjetoAluno($projetoId, $alunoId);
        $aprovadas = $this->inscricaoModel->contarAprovadas($projetoId);
        $vagas = max(0, (int) $projeto['vagas_totais']);
        $lotado = $vagas > 0 && $aprovadas >= $vagas;
        $janela = $this->janelaInscricaoAberta($projeto);
        $ativas = $this->inscricaoModel->contarAtivasAluno($alunoId, $projetoId);
        $conflitos = $this->avaliarConflitos($alunoId, $projetoId);

        $pode = true;
        $motivo = '';
        if ($existente && in_array($existente['status'], ExpoColagInscricao::STATUS_ATIVOS, true)) {
            $pode = false;
            $motivo = 'Você já está inscrito neste projeto (' . str_replace('_', ' ', $existente['status']) . ').';
        } elseif (($projeto['modo_ingresso'] ?? '') === 'Convite_direto') {
            $pode = false;
            $motivo = 'Este projeto só aceita inscrição por convite do professor.';
        } elseif (!$janela) {
            $pode = false;
            $motivo = 'As inscrições não estão abertas neste momento.';
        } elseif ($ativas >= $max) {
            $pode = false;
            $motivo = "Você já participa do máximo de {$max} projeto(s) simultâneo(s) nesta edição.";
        } elseif ($lotado && empty($projeto['lista_espera_ativa'])) {
            $pode = false;
            $motivo = 'Não há vagas disponíveis e a lista de espera está desativada.';
        } elseif (!empty($conflitos['bloqueios'])) {
            $pode = false;
            $motivo = $conflitos['bloqueios'][0];
        }

        return [
            'pode_inscrever' => $pode,
            'motivo' => $motivo,
            'inscricao' => $existente,
            'lotado' => $lotado,
            'janela_aberta' => $janela,
            'vagas_restantes' => max(0, $vagas - $aprovadas),
            'limite_atingido' => $ativas >= $max,
            'conflitos' => $conflitos,
            'modo_ingresso' => $projeto['modo_ingresso'] ?? 'Livre',
            'exige_justificativa' => !empty($projeto['exige_justificativa']),
        ];
    }

    /**
     * Inscrição com lock de vagas (SPEC 5.2).
     *
     * @param array<string,mixed> $input justificativa, papel_id, confirmar_apresentacao
     * @return array{success:bool,status?:string,id?:int,error?:string,alertas?:list,infos?:list}
     */
    public function inscreverAluno(int $projetoId, int $alunoId, array $input = []): array
    {
        $ctx = $this->contextoVisibilidadeAluno($alunoId);
        if (!$this->alunoPodeVerProjeto($projetoId, $ctx)) {
            return ['success' => false, 'error' => 'Projeto não disponível para você.'];
        }

        $statusInfo = $this->statusInscricaoParaAluno($projetoId, $alunoId);
        if (!$statusInfo['pode_inscrever']) {
            return ['success' => false, 'error' => $statusInfo['motivo'] ?: 'Não é possível se inscrever.'];
        }

        $conflitos = $this->avaliarConflitos($alunoId, $projetoId);
        if (!empty($conflitos['bloqueios'])) {
            return ['success' => false, 'error' => $conflitos['bloqueios'][0], 'conflitos' => $conflitos];
        }
        if (!empty($conflitos['alertas']) && empty($input['confirmar_apresentacao'])) {
            return [
                'success' => false,
                'error' => $conflitos['alertas'][0],
                'requer_confirmacao' => true,
                'conflitos' => $conflitos,
            ];
        }

        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        if (!empty($projeto['exige_justificativa']) && trim((string) ($input['justificativa'] ?? '')) === '') {
            return ['success' => false, 'error' => 'Este projeto exige uma justificativa para inscrição.'];
        }
        if (($projeto['modo_ingresso'] ?? '') === 'Convite_direto') {
            return ['success' => false, 'error' => 'Este projeto só aceita inscrição por convite do professor.'];
        }

        $edicao = $this->obterOuCriarEdicaoAtiva();
        $config = $edicao['edicao']['config_decoded'] ?? self::configPadrao();
        $max = (int) ($config['max_projetos_aluno'] ?? 1);

        $papelId = !empty($input['papel_id']) ? (int) $input['papel_id'] : null;
        if ($papelId !== null && $papelId > 0) {
            $papelOk = $this->db->fetch(
                'SELECT id FROM expo_colag_projeto_papeis
                 WHERE id = :id AND projeto_id = :projeto_id',
                ['id' => $papelId, 'projeto_id' => $projetoId]
            );
            if (!$papelOk) {
                return ['success' => false, 'error' => 'Papel inválido para este projeto.'];
            }
        } else {
            $papelId = null;
        }

        try {
            $this->db->beginTransaction();

            $projetoLock = $this->db->fetch(
                'SELECT id, vagas_totais, modo_ingresso, lista_espera_ativa, status,
                        inscricoes_inicio, inscricoes_fim, exige_justificativa
                 FROM expo_colag_projetos WHERE id = :id FOR UPDATE',
                ['id' => $projetoId]
            );
            if (!$projetoLock || !$this->janelaInscricaoAberta($projetoLock)) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'As inscrições não estão abertas neste momento.'];
            }

            // Serializa teto de projetos do aluno entre requests paralelos
            $this->inscricaoModel->travarAtivasAluno($alunoId);

            $existente = $this->inscricaoModel->findByProjetoAluno($projetoId, $alunoId);
            if ($existente && in_array($existente['status'], ExpoColagInscricao::STATUS_ATIVOS, true)) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Você já está inscrito neste projeto.'];
            }

            if ($this->inscricaoModel->contarAtivasAluno($alunoId, $projetoId) >= $max) {
                $this->db->rollback();
                return ['success' => false, 'error' => "Limite de {$max} projeto(s) simultâneo(s) atingido."];
            }

            $aprovadas = $this->inscricaoModel->contarAprovadasForUpdate($projetoId);
            $vagas = max(0, (int) $projetoLock['vagas_totais']);
            $temVaga = $vagas === 0 || $aprovadas < $vagas;
            $listaEspera = !empty($projetoLock['lista_espera_ativa']);
            $modo = (string) ($projetoLock['modo_ingresso'] ?? 'Livre');

            if (!$temVaga) {
                if (!$listaEspera) {
                    $this->db->rollback();
                    return ['success' => false, 'error' => 'Não há vagas disponíveis.'];
                }
                $statusFinal = 'Lista_espera';
            } elseif ($modo === 'Com_aprovacao') {
                $statusFinal = 'Aguardando';
            } else {
                $statusFinal = 'Aprovada'; // Livre
            }

            if ($existente) {
                $ok = $this->inscricaoModel->atualizarStatus(
                    (int) $existente['id'],
                    $statusFinal,
                    null,
                    null,
                    ['Recusada', 'Cancelada_aluno', 'Removido_professor'],
                    false
                );
                if (!$ok) {
                    $this->db->rollback();
                    return ['success' => false, 'error' => 'Você já está inscrito neste projeto.'];
                }
                // Reabrir: atualizar justificativa e limpar decisão anterior
                $this->db->query(
                    'UPDATE expo_colag_inscricoes SET justificativa = :j, papel_id = :papel, inscrito_em = NOW(),
                        motivo_recusa = NULL, decidido_em = NULL, decidido_por = NULL
                     WHERE id = :id',
                    [
                        'j' => trim((string) ($input['justificativa'] ?? '')) ?: null,
                        'papel' => $papelId,
                        'id' => (int) $existente['id'],
                    ]
                );
                $id = (int) $existente['id'];
            } else {
                $id = $this->inscricaoModel->create([
                    'projeto_id' => $projetoId,
                    'aluno_id' => $alunoId,
                    'papel_id' => $papelId,
                    'justificativa' => trim((string) ($input['justificativa'] ?? '')) ?: null,
                    'status' => $statusFinal,
                ]);
            }

            // Auto-abrir status do projeto se ainda Publicado
            if (($projetoLock['status'] ?? '') === 'Publicado' && $this->janelaInscricaoAberta($projetoLock)) {
                $this->db->query(
                    "UPDATE expo_colag_projetos SET status = 'Inscricoes_abertas' WHERE id = :id AND status = 'Publicado'",
                    ['id' => $projetoId]
                );
            }

            $this->db->commit();
            return [
                'success' => true,
                'id' => $id,
                'status' => $statusFinal,
                'alertas' => $conflitos['alertas'],
                'infos' => $conflitos['infos'],
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('ExpoColagService::inscreverAluno: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível concluir a inscrição. Tente novamente.'];
        }
    }

    /**
     * Aluno cancela a própria inscrição.
     *
     * @return array{success:bool,error?:string}
     */
    public function cancelarInscricaoAluno(int $inscricaoId, int $alunoId, ?int $projetoIdEsperado = null): array
    {
        $peek = $this->inscricaoModel->findById($inscricaoId);
        if (!$peek || (int) $peek['aluno_id'] !== $alunoId) {
            return ['success' => false, 'error' => 'Inscrição não encontrada.'];
        }
        $projetoId = (int) $peek['projeto_id'];
        if ($projetoIdEsperado !== null && $projetoId !== $projetoIdEsperado) {
            return ['success' => false, 'error' => 'Inscrição não encontrada.'];
        }

        try {
            $this->db->beginTransaction();
            // Ordem de lock: projeto → inscrição (igual a inscrever/decidir)
            $this->db->fetch(
                'SELECT id FROM expo_colag_projetos WHERE id = :id FOR UPDATE',
                ['id' => $projetoId]
            );
            $insc = $this->db->fetch(
                'SELECT * FROM expo_colag_inscricoes WHERE id = :id FOR UPDATE',
                ['id' => $inscricaoId]
            );
            if (!$insc || (int) $insc['aluno_id'] !== $alunoId || (int) $insc['projeto_id'] !== $projetoId) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Inscrição não encontrada.'];
            }
            if (!in_array($insc['status'], ExpoColagInscricao::STATUS_ATIVOS, true)) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Esta inscrição não pode ser cancelada.'];
            }

            $eraAprovada = $insc['status'] === 'Aprovada';
            $ok = $this->inscricaoModel->atualizarStatus(
                $inscricaoId,
                'Cancelada_aluno',
                $alunoId,
                null,
                ExpoColagInscricao::STATUS_ATIVOS
            );
            if (!$ok) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Esta inscrição não pode ser cancelada.'];
            }
            if ($eraAprovada) {
                $this->promoverListaEsperaInterno($projetoId);
            }
            $this->db->commit();
            return ['success' => true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('ExpoColagService::cancelarInscricaoAluno: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível cancelar.'];
        }
    }

    /**
     * Professor aprova ou recusa inscrição pendente.
     *
     * @return array{success:bool,error?:string,status?:string}
     */
    public function decidirInscricao(
        int $inscricaoId,
        int $professorId,
        string $decisao,
        ?string $motivo = null,
        ?int $projetoIdEsperado = null
    ): array {
        if ($decisao === 'recusar' && trim((string) $motivo) === '') {
            return ['success' => false, 'error' => 'Informe o motivo da recusa.'];
        }
        if ($decisao !== 'aprovar' && $decisao !== 'recusar') {
            return ['success' => false, 'error' => 'Decisão inválida.'];
        }

        $peek = $this->inscricaoModel->findById($inscricaoId);
        if (!$peek) {
            return ['success' => false, 'error' => 'Inscrição não encontrada.'];
        }
        $projetoId = (int) $peek['projeto_id'];
        if ($projetoIdEsperado !== null && $projetoId !== $projetoIdEsperado) {
            return ['success' => false, 'error' => 'Inscrição não encontrada.'];
        }

        try {
            $this->db->beginTransaction();

            $projetoLock = $this->db->fetch(
                'SELECT id, vagas_totais, lista_espera_ativa, professor_id
                 FROM expo_colag_projetos WHERE id = :id FOR UPDATE',
                ['id' => $projetoId]
            );
            if (!$projetoLock || (int) $projetoLock['professor_id'] !== $professorId) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Inscrição não encontrada.'];
            }

            $insc = $this->db->fetch(
                'SELECT * FROM expo_colag_inscricoes WHERE id = :id FOR UPDATE',
                ['id' => $inscricaoId]
            );
            if (!$insc || (int) $insc['projeto_id'] !== $projetoId) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Inscrição não encontrada.'];
            }

            $statusAtual = (string) ($insc['status'] ?? '');
            $podeDecidir = $statusAtual === 'Aguardando'
                || ($decisao === 'aprovar' && $statusAtual === 'Lista_espera');
            if (!$podeDecidir) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Só é possível decidir inscrições aguardando (ou promover da lista).'];
            }

            if ($decisao === 'recusar') {
                $ok = $this->inscricaoModel->atualizarStatus(
                    $inscricaoId,
                    'Recusada',
                    $professorId,
                    trim((string) $motivo),
                    ['Aguardando', 'Lista_espera']
                );
                if (!$ok) {
                    $this->db->rollback();
                    return ['success' => false, 'error' => 'Inscrição já foi alterada.'];
                }
                $this->db->commit();
                return ['success' => true, 'status' => 'Recusada'];
            }

            $aprovadas = $this->inscricaoModel->contarAprovadasForUpdate($projetoId);
            $vagas = max(0, (int) ($projetoLock['vagas_totais'] ?? 0));
            if ($vagas > 0 && $aprovadas >= $vagas) {
                if (!empty($projetoLock['lista_espera_ativa'])) {
                    $ok = $this->inscricaoModel->atualizarStatus(
                        $inscricaoId,
                        'Lista_espera',
                        $professorId,
                        null,
                        [$statusAtual]
                    );
                    if (!$ok) {
                        $this->db->rollback();
                        return ['success' => false, 'error' => 'Inscrição já foi alterada.'];
                    }
                    // Recoloca no fim da fila (não fura quem já estava em espera)
                    $this->db->query(
                        'UPDATE expo_colag_inscricoes SET inscrito_em = NOW() WHERE id = :id AND status = \'Lista_espera\'',
                        ['id' => $inscricaoId]
                    );
                    $this->db->commit();
                    return ['success' => true, 'status' => 'Lista_espera'];
                }
                $this->db->rollback();
                return ['success' => false, 'error' => 'Sem vagas para aprovar.'];
            }

            $ok = $this->inscricaoModel->atualizarStatus(
                $inscricaoId,
                'Aprovada',
                $professorId,
                null,
                [$statusAtual]
            );
            if (!$ok) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'Inscrição já foi alterada.'];
            }
            $this->db->commit();
            return ['success' => true, 'status' => 'Aprovada'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('ExpoColagService::decidirInscricao: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Falha ao decidir inscrição.'];
        }
    }

    public function listarInscricoesProjeto(int $projetoId, int $professorId): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto || (int) $projeto['professor_id'] !== $professorId) {
            return [];
        }
        return $this->inscricaoModel->listarPorProjeto($projetoId);
    }

    public function listarPendentesProfessor(int $professorId): array
    {
        return $this->inscricaoModel->listarPendentesPorProfessor($professorId);
    }

    public function listarInscricoesAluno(int $alunoId): array
    {
        return $this->inscricaoModel->listarPorAluno($alunoId);
    }

    /** Dentro de transação com projeto já locked. */
    private function promoverListaEsperaInterno(int $projetoId): void
    {
        $projeto = $this->db->fetch(
            'SELECT id, vagas_totais, modo_ingresso FROM expo_colag_projetos WHERE id = :id',
            ['id' => $projetoId]
        );
        if (!$projeto) {
            return;
        }
        $aprovadas = $this->inscricaoModel->contarAprovadas((int) $projetoId);
        $vagas = max(0, (int) $projeto['vagas_totais']);
        if ($vagas > 0 && $aprovadas >= $vagas) {
            return;
        }
        $prox = $this->inscricaoModel->proximoListaEspera($projetoId);
        if (!$prox) {
            return;
        }

        $modo = (string) ($projeto['modo_ingresso'] ?? 'Livre');
        $jaAprovadoPeloProfessor = !empty($prox['decidido_por']);
        // Com_aprovacao sem decisão do professor → Aguardando; demais casos → Aprovada.
        if ($modo === 'Com_aprovacao' && !$jaAprovadoPeloProfessor) {
            $this->inscricaoModel->atualizarStatus(
                (int) $prox['id'],
                'Aguardando',
                null,
                null,
                ['Lista_espera'],
                false
            );
            return;
        }

        // Mantém decidido_por se o professor já tinha aprovado (só faltava vaga)
        $this->db->query(
            "UPDATE expo_colag_inscricoes
             SET status = 'Aprovada', motivo_recusa = NULL
             WHERE id = :id AND status = 'Lista_espera'",
            ['id' => (int) $prox['id']]
        );
    }

    public function obterProjeto(int $id): ?array
    {
        return $this->projetoModel->findById($id);
    }

    /**
     * Projeto + relações para o wizard / pré-visualização.
     *
     * @return array{success:bool,projeto?:array,relacoes?:array,error?:string}
     */
    public function carregarProjetoCompleto(int $projetoId, ?int $professorId = null): array
    {
        $projeto = $this->projetoModel->findById($projetoId);
        if (!$projeto) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        if ($professorId !== null && (int) $projeto['professor_id'] !== $professorId) {
            return ['success' => false, 'error' => 'Projeto não encontrado.'];
        }
        return [
            'success' => true,
            'projeto' => $projeto,
            'relacoes' => $this->relacoesModel->carregarTudo($projetoId),
        ];
    }

    /**
     * Salva o wizard completo (rascunho persistente). Cria se id=0.
     *
     * @param array<string, mixed> $input
     * @return array{success:bool,id?:int,error?:string}
     */
    public function salvarProjetoCompleto(int $professorId, array $input, ?array $capaFile = null): array
    {
        $titulo = trim((string) ($input['titulo'] ?? ''));
        if ($titulo === '') {
            return ['success' => false, 'error' => 'Título é obrigatório.'];
        }

        $projetoId = (int) ($input['projeto_id'] ?? $input['id'] ?? 0);
        $edicaoResult = $this->obterOuCriarEdicaoAtiva();
        if (!$edicaoResult['success']) {
            return $edicaoResult;
        }
        $edicao = $edicaoResult['edicao'];
        $config = $edicao['config_decoded'] ?? self::configPadrao();

        if ($projetoId > 0) {
            $existente = $this->projetoModel->findById($projetoId);
            if (!$existente || (int) $existente['professor_id'] !== $professorId) {
                return ['success' => false, 'error' => 'Projeto não encontrado.'];
            }
            if (($existente['status'] ?? '') === 'Cancelado') {
                return ['success' => false, 'error' => 'Projeto cancelado não pode ser editado.'];
            }
        } else {
            $rascunho = $this->salvarRascunhoProjeto($professorId, $input);
            if (!$rascunho['success']) {
                return $rascunho;
            }
            $projetoId = (int) $rascunho['id'];
        }

        $capaUrl = trim((string) ($input['capa_url'] ?? ''));
        if ($capaFile && ($capaFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $upload = $this->salvarCapa($capaFile, $projetoId);
            if (!$upload['success']) {
                return $upload;
            }
            $capaUrl = $upload['url'];
        }

        $formatos = $input['formatos_aceitos'] ?? [];
        if (is_string($formatos)) {
            $formatos = array_filter(array_map('trim', explode(',', $formatos)));
        }
        if (!is_array($formatos)) {
            $formatos = [];
        }

        $dados = [
            'titulo' => $titulo,
            'subtitulo' => trim((string) ($input['subtitulo'] ?? '')) ?: null,
            'area' => trim((string) ($input['area'] ?? '')) ?: null,
            'materia_principal_id' => !empty($input['materia_principal_id']) ? (int) $input['materia_principal_id'] : null,
            'descricao' => trim((string) ($input['descricao'] ?? '')) ?: null,
            'contexto_pratico' => trim((string) ($input['contexto_pratico'] ?? '')) ?: null,
            'produto_esperado' => trim((string) ($input['produto_esperado'] ?? '')) ?: null,
            'conexoes_interdisciplinares' => trim((string) ($input['conexoes_interdisciplinares'] ?? '')) ?: null,
            'pre_requisitos' => trim((string) ($input['pre_requisitos'] ?? '')) ?: null,
            'modalidade' => $this->modalidadeValida($input['modalidade'] ?? 'Grupo'),
            'vagas_totais' => max(1, (int) ($input['vagas_totais'] ?? ($config['grupo_max'] ?? 5))),
            'vagas_minimas' => max(1, (int) ($input['vagas_minimas'] ?? ($config['grupo_min'] ?? 3))),
            'tamanho_grupo' => !empty($input['tamanho_grupo']) ? (int) $input['tamanho_grupo'] : null,
            'modo_ingresso' => $this->modoIngressoValido($input['modo_ingresso'] ?? 'Livre'),
            'exige_justificativa' => !empty($input['exige_justificativa']) ? 1 : 0,
            'lista_espera_ativa' => !empty($input['lista_espera_ativa']) ? 1 : 0,
            'inscricoes_inicio' => $this->normalizarDateTime($input['inscricoes_inicio'] ?? null, false),
            'inscricoes_fim' => $this->normalizarDateTime($input['inscricoes_fim'] ?? null, true),
            'data_inicio' => $this->normalizarData($input['data_inicio'] ?? null),
            'data_fim' => $this->normalizarData($input['data_fim'] ?? null),
            'data_apresentacao' => $this->normalizarDateTime($input['data_apresentacao'] ?? null, false),
            'briefing_entrega' => trim((string) ($input['briefing_entrega'] ?? '')) ?: null,
            'formatos_aceitos' => $formatos !== [] ? json_encode(array_values($formatos), JSON_UNESCAPED_UNICODE) : null,
            'vale_nota' => !empty($input['vale_nota']) ? 1 : 0,
            'tudinha_ativa' => !empty($input['tudinha_ativa']) ? 1 : 0,
            'tudinha_contexto' => trim((string) ($input['tudinha_contexto'] ?? '')) ?: null,
            'custo_tudicoins' => max(0, (float) ($input['custo_tudicoins'] ?? 0)),
            'permite_solicitacao_recursos' => !empty($input['permite_solicitacao_recursos']) ? 1 : 0,
            'destaque' => !empty($input['destaque']) ? 1 : 0,
            'ativo' => !empty($input['ativo']) ? 1 : 0,
        ];
        if ($capaUrl !== '') {
            $dados['capa_url'] = $capaUrl;
        }

        $this->projetoModel->update($projetoId, $dados);

        $materias = $input['materias_conectadas'] ?? $input['materia_ids'] ?? [];
        if (!is_array($materias)) {
            $materias = [];
        }

        $parceiros = $input['professores_parceiros'] ?? $input['professor_ids'] ?? [];
        if (!is_array($parceiros)) {
            $parceiros = [];
        }

        $objetivos = $input['objetivos'] ?? [];
        if (is_string($objetivos)) {
            $objetivos = preg_split('/\r\n|\r|\n/', $objetivos) ?: [];
        }
        if (!is_array($objetivos)) {
            $objetivos = [];
        }

        $tipos = $input['tipos_trabalho'] ?? [];
        if (!is_array($tipos)) {
            $tipos = [];
        }

        $papeis = $this->parseJsonOrArray($input['papeis'] ?? []);
        $habilidades = $this->parseHabilidades($input);
        $visibilidade = $this->parseVisibilidade($input);
        $etapas = $this->parseJsonOrArray($input['etapas'] ?? []);
        $encontros = $this->parseJsonOrArray($input['encontros'] ?? []);
        $rubrica = $this->parseJsonOrArray($input['rubrica'] ?? []);
        $materiais = $this->parseJsonOrArray($input['materiais'] ?? []);

        // Não apagar visibilidade de projeto já publicado se o form vier sem árvore.
        $existente = $this->projetoModel->findById($projetoId);
        $statusAtual = (string) ($existente['status'] ?? 'Rascunho');
        $pularVisibilidade = $visibilidade === []
            && !in_array($statusAtual, ['Rascunho', 'Cancelado'], true);

        try {
            $this->db->beginTransaction();
            $this->relacoesModel->sincronizarMaterias($projetoId, $materias);
            $this->relacoesModel->sincronizarProfessores($projetoId, $parceiros);
            $this->relacoesModel->sincronizarObjetivos($projetoId, $objetivos);
            $this->relacoesModel->sincronizarTiposTrabalho($projetoId, $tipos);
            $this->relacoesModel->sincronizarPapeis($projetoId, $papeis);
            $this->relacoesModel->sincronizarHabilidades($projetoId, $habilidades);
            if (!$pularVisibilidade) {
                $this->relacoesModel->sincronizarVisibilidade($projetoId, $visibilidade);
            }
            $this->relacoesModel->sincronizarEtapas($projetoId, $etapas);
            $this->relacoesModel->sincronizarEncontros($projetoId, $encontros);
            $this->relacoesModel->sincronizarRubrica($projetoId, $rubrica);
            $this->relacoesModel->sincronizarMateriais($projetoId, $materiais, $professorId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return ['success' => false, 'error' => 'Falha ao salvar relações do projeto. Tente novamente.'];
        }

        return ['success' => true, 'id' => $projetoId];
    }

    /**
     * Publica o projeto (Rascunho → Publicado). Valida campos mínimos.
     *
     * @return array{success:bool,error?:string}
     */
    public function publicarProjeto(int $projetoId, int $professorId): array
    {
        $completo = $this->carregarProjetoCompleto($projetoId, $professorId);
        if (!$completo['success']) {
            return $completo;
        }
        $projeto = $completo['projeto'];
        $rel = $completo['relacoes'];

        if (trim((string) ($projeto['titulo'] ?? '')) === '') {
            return ['success' => false, 'error' => 'Informe o título antes de publicar.'];
        }
        if (trim((string) ($projeto['descricao'] ?? '')) === '') {
            return ['success' => false, 'error' => 'Informe a descrição pedagógica antes de publicar.'];
        }
        if (empty($rel['visibilidade'])) {
            return ['success' => false, 'error' => 'Defina a visibilidade (série, turma ou aluno) antes de publicar.'];
        }

        $status = (string) ($projeto['status'] ?? 'Rascunho');
        if (in_array($status, ['Publicado', 'Inscricoes_abertas'], true)) {
            return ['success' => true];
        }
        if ($status !== 'Rascunho') {
            return ['success' => false, 'error' => 'Só é possível publicar a partir de rascunho.'];
        }

        $limite = $this->situacaoLimiteProfessor($professorId, (int) ($projeto['edicao_id'] ?? 0), $projetoId);
        if ($limite['atingiu']) {
            return [
                'success' => false,
                'error' => $limite['error'] ?? "Limite de {$limite['max']} projetos por professor atingido nesta edição.",
            ];
        }

        $r1 = $this->alterarStatusProjeto($projetoId, $professorId, 'Publicado');
        if (!$r1['success']) {
            return $r1;
        }

        $agora = date('Y-m-d H:i:s');
        $inicio = $projeto['inscricoes_inicio'] ?? null;
        $fim = $projeto['inscricoes_fim'] ?? null;
        if ($inicio && $fim && $agora >= $inicio && $agora <= $fim) {
            return $this->alterarStatusProjeto($projetoId, $professorId, 'Inscricoes_abertas');
        }

        return ['success' => true];
    }

    /** Catálogos para o wizard. */
    public function catalogosWizard(): array
    {
        $materias = $this->db->fetchAll('SELECT id, nome FROM materias ORDER BY nome ASC') ?: [];
        try {
            $professores = $this->db->fetchAll('SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome ASC') ?: [];
        } catch (Throwable $e) {
            $professores = $this->db->fetchAll('SELECT id, nome FROM professores ORDER BY nome ASC') ?: [];
        }
        try {
            $turmas = $this->db->fetchAll(
                'SELECT id, nome, serie, serie_id FROM turmas WHERE ativo = 1 ORDER BY serie ASC, nome ASC'
            ) ?: [];
        } catch (Throwable $e) {
            $turmas = $this->db->fetchAll(
                'SELECT id, nome, serie FROM turmas ORDER BY serie ASC, nome ASC'
            ) ?: [];
            foreach ($turmas as &$t) {
                $t['serie_id'] = $t['serie_id'] ?? null;
            }
            unset($t);
        }

        $seriesMap = [];
        foreach ($turmas as $t) {
            $serieId = (int) ($t['serie_id'] ?? 0);
            $serieNome = trim((string) ($t['serie'] ?? ''));
            $key = $serieId > 0 ? 'id:' . $serieId : 'nome:' . $serieNome;
            if (!isset($seriesMap[$key])) {
                $seriesMap[$key] = [
                    'id' => $serieId > 0 ? $serieId : 0,
                    'nome' => $serieNome !== '' ? $serieNome : ('Série #' . $serieId),
                    'referencia_id' => $serieId > 0 ? $serieId : (int) $t['id'], // fallback: usa turma se sem serie_id
                    'usa_serie_id' => $serieId > 0,
                    'turmas' => [],
                ];
            }
            $seriesMap[$key]['turmas'][] = [
                'id' => (int) $t['id'],
                'nome' => (string) $t['nome'],
            ];
        }

        // Preferir tabela serie quando existir
        try {
            $serieRows = $this->db->fetchAll('SELECT id, nome FROM serie ORDER BY nome ASC') ?: [];
            if ($serieRows !== []) {
                $byId = [];
                foreach ($serieRows as $s) {
                    $byId[(int) $s['id']] = [
                        'id' => (int) $s['id'],
                        'nome' => (string) $s['nome'],
                        'referencia_id' => (int) $s['id'],
                        'usa_serie_id' => true,
                        'turmas' => [],
                    ];
                }
                foreach ($turmas as $t) {
                    $sid = (int) ($t['serie_id'] ?? 0);
                    if ($sid > 0 && isset($byId[$sid])) {
                        $byId[$sid]['turmas'][] = ['id' => (int) $t['id'], 'nome' => (string) $t['nome']];
                    }
                }
                $seriesMap = $byId;
            }
        } catch (Throwable $e) {
            // tabela serie pode não existir em todos os tenants
        }

        $habilidades = [];
        if (is_file(__DIR__ . '/../../../Models/Bncc/BnccSkill.php')) {
            require_once __DIR__ . '/../../../Models/Bncc/BnccSkill.php';
            $bncc = new BnccSkill();
            $habilidades = $bncc->listar('', '', 200);
        }

        $edicaoResult = $this->obterOuCriarEdicaoAtiva();
        $config = $edicaoResult['edicao']['config_decoded'] ?? self::configPadrao();

        return [
            'materias' => $materias,
            'professores' => $professores,
            'series' => array_values($seriesMap),
            'habilidades' => $habilidades,
            'config_edicao' => $config,
            'criterios_banca_padrao' => $config['criterios_banca'] ?? [],
        ];
    }

    public function listarAlunosPorTurma(int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }
        // Preferir resolver com matrícula ativa (padrão da plataforma).
        $resolverPath = __DIR__ . '/../../../Services/AlunoTurmaResolver.php';
        if (is_file($resolverPath)) {
            require_once $resolverPath;
            $resolver = new \App\Services\AlunoTurmaResolver();
            $rows = $resolver->listarAlunosPorTurma($turmaId);
            $out = [];
            foreach ($rows as $row) {
                if (isset($row['ativo']) && (int) $row['ativo'] !== 1) {
                    continue;
                }
                $out[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'nome' => (string) ($row['nome'] ?? ''),
                ];
            }
            return $out;
        }
        return $this->db->fetchAll(
            'SELECT id, nome FROM alunos WHERE turma_id = :turma_id AND ativo = 1 ORDER BY nome ASC',
            ['turma_id' => $turmaId]
        ) ?: [];
    }

    public function buscarHabilidadesBncc(string $q): array
    {
        if (!is_file(__DIR__ . '/../../../Models/Bncc/BnccSkill.php')) {
            return [];
        }
        require_once __DIR__ . '/../../../Models/Bncc/BnccSkill.php';
        $bncc = new BnccSkill();
        return $bncc->listar($q, '', 50);
    }

    public function autorizacaoImagemResumo(): array
    {
        return [
            'contagens' => $this->autorizacaoModel->contarPorStatus(),
            'alunos' => $this->autorizacaoModel->listarResumo(),
        ];
    }

    public function registrarAutorizacaoImagem(int $alunoId, string $status, ?int $registradoPor = null, ?string $observacao = null): array
    {
        return $this->autorizacaoModel->registrar($alunoId, $status, $registradoPor, null, $observacao);
    }

    /**
     * @param array<string,mixed> $file
     * @return array{success:bool,url?:string,error?:string}
     */
    private function salvarCapa(array $file, int $projetoId): array
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['success' => false, 'error' => 'Upload de capa inválido.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return ['success' => false, 'error' => 'Capa deve ser JPG, PNG ou WebP.'];
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Capa deve ter no máximo 5 MB.'];
        }

        $slug = defined('TENANT_SLUG') ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) TENANT_SLUG) : 'tenant';
        $ext = $allowed[$mime];
        $filename = 'expo_colag_' . $projetoId . '_' . time() . '.' . $ext;
        $relDir = 'expo-colag/' . $slug;
        // Services/ → expo-colag/ → Modulos/ → app/ → backend/
        $base = dirname(__DIR__, 4) . '/storage/uploads/' . $relDir;
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }
        $dest = $base . '/' . $filename;
        if (!@move_uploaded_file($tmp, $dest)) {
            return ['success' => false, 'error' => 'Falha ao gravar a capa.'];
        }

        $url = (defined('URL') ? URL : '') . '/storage/uploads/' . $relDir . '/' . $filename;
        return ['success' => true, 'url' => $url];
    }

    private function modoIngressoValido($value): string
    {
        $ok = ['Livre', 'Com_aprovacao', 'Convite_direto'];
        $v = (string) $value;
        return in_array($v, $ok, true) ? $v : 'Livre';
    }

    private function normalizarDateTime($value, bool $fimDoDia): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        $v = str_replace('T', ' ', $v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return $v . ($fimDoDia ? ' 23:59:59' : ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
            return $v . ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v)) {
            return $v;
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    private function parseJsonOrArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** @param array<string,mixed> $input */
    private function parseHabilidades(array $input): array
    {
        $raw = $input['habilidades'] ?? $input['habilidades_bncc'] ?? [];
        $list = $this->parseJsonOrArray($raw);
        if ($list === [] && !empty($input['codigos_habilidade'])) {
            $codigos = is_array($input['codigos_habilidade'])
                ? $input['codigos_habilidade']
                : preg_split('/[\s,;]+/', (string) $input['codigos_habilidade']);
            foreach ($codigos ?: [] as $c) {
                $c = trim((string) $c);
                if ($c !== '') {
                    $list[] = ['codigo' => $c];
                }
            }
        }
        return $list;
    }

    /** @param array<string,mixed> $input */
    private function parseVisibilidade(array $input): array
    {
        $raw = $input['visibilidade'] ?? [];
        $list = $this->parseJsonOrArray($raw);
        if ($list !== []) {
            return $list;
        }
        // Campos simples do form: series[], turmas[], alunos[]
        foreach (['Serie' => 'series', 'Turma' => 'turmas', 'Aluno' => 'alunos'] as $escopo => $key) {
            $ids = $input[$key] ?? [];
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $list[] = ['escopo' => $escopo, 'referencia_id' => $id];
                }
            }
        }
        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodificarConfig($raw): array
    {
        $padrao = self::configPadrao();
        if ($raw === null || $raw === '') {
            return $padrao;
        }
        if (is_array($raw)) {
            return array_merge($padrao, $raw);
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return $padrao;
        }
        return array_merge($padrao, $decoded);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function filtrarConfigInput(array $input): array
    {
        $out = [];
        $ints = ['grupo_min', 'grupo_max', 'max_projetos_aluno', 'max_projetos_professor', 'avaliadores_por_stand'];
        foreach ($ints as $k) {
            if (isset($input[$k]) && $input[$k] !== '') {
                $out[$k] = max(0, (int) $input[$k]);
            }
        }
        // Checkboxes: ausentes no POST = desligados (sempre sobrescrever).
        $bools = ['permite_individual', 'cross_turma', 'vale_nota', 'voto_publico_ativo', 'checkin_ativo', 'upload_foto_responsavel'];
        foreach ($bools as $k) {
            $out[$k] = !empty($input[$k]);
        }
        $datas = ['inscricoes_inicio', 'inscricoes_fim', 'limite_solicitacao_recursos'];
        foreach ($datas as $k) {
            if (!empty($input[$k])) {
                $d = $this->normalizarData($input[$k]);
                if ($d) {
                    $out[$k] = $d;
                }
            }
        }
        if (isset($input['fluxo_aprovacao_recurso'])) {
            $out['fluxo_aprovacao_recurso'] = trim((string) $input['fluxo_aprovacao_recurso']);
        }
        if (isset($input['politica_atraso'])) {
            $out['politica_atraso'] = trim((string) $input['politica_atraso']);
        }
        return $out;
    }

    private function normalizarData($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return $v;
        }
        return null;
    }

    private function modalidadeValida($value): string
    {
        $v = (string) $value;
        $ok = ['Individual', 'Grupo', 'Grupo_com_papeis'];
        return in_array($v, $ok, true) ? $v : 'Grupo';
    }
}
