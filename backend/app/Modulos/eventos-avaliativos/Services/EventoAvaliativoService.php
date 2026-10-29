<?php

namespace App\Modulos\EventosAvaliativos\Services;

use App\Core\Database;
use App\Core\PeriodoLetivo;
use App\Dominio\Escola\Models\ComponenteCurricular;
use App\Modulos\EventosAvaliativos\Models\EventoAvaliativo;
use App\Modulos\GruposRegrasNotas\Models\GrupoRegrasNotas;
use App\Modulos\GruposRegrasNotas\Services\GrupoRegrasNotasService;
use App\Modulos\Provas\Models\ExamBlock;
use App\Modulos\Provas\Models\ExamEvaluationType;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Services\TipoNotaRegraService;
use Throwable;

class EventoAvaliativoService
{
    private EventoAvaliativo $model;
    private $db;
    private ?bool $agendaPronta = null;

    public function __construct()
    {
        $this->model = new EventoAvaliativo();
        $this->db = Database::getInstance();
    }

    public function model(): EventoAvaliativo
    {
        return $this->model;
    }

    public function moduloAtivo(): bool
    {
        return \App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos');
    }

    /**
     * @return array{professores:list<array<string,mixed>>,materias:list<array<string,mixed>>,turmas:list<array<string,mixed>>,periodo:array<string,mixed>}
     */
    public function catalogoFormulario(int $ano): array
    {
        $professores = $this->db->fetchAll(
            'SELECT id, nome, materias FROM professores WHERE ativo = 1 ORDER BY nome ASC'
        ) ?: [];
        $materias = (new ComponenteCurricular())->getAvaliaveis(true);
        $turmas = $this->db->fetchAll(
            'SELECT t.id, t.nome, t.tipo_ensino, t.serie_id, COALESCE(NULLIF(s.nome, \'\'), NULLIF(t.serie, \'\')) AS serie
             FROM turmas t
             LEFT JOIN serie s ON s.id = t.serie_id
             WHERE t.ativo = 1
             ORDER BY t.nome ASC'
        ) ?: [];

        return [
            'professores' => $professores,
            'materias' => $materias,
            'turmas' => $turmas,
            'periodo' => PeriodoLetivo::doAno($ano > 0 ? $ano : (int) date('Y')),
        ];
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool,id?:int,error?:string}
     */
    public function criar(array $post, int $criadoPor): array
    {
        if (!$this->model->tabelasProntas()) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_26_eventos_avaliativos.sql no painel Master.'];
        }

        $nome = trim((string) ($post['nome'] ?? ''));
        if ($nome === '') {
            return ['success' => false, 'error' => 'Informe o nome do evento.'];
        }
        if (mb_strlen($nome) > 120) {
            return ['success' => false, 'error' => 'O nome do evento pode ter no máximo 120 caracteres.'];
        }

        $ano = (int) ($post['ano_letivo'] ?? 0);
        $bimestre = (int) ($post['bimestre'] ?? 0);
        if ($ano < 2000) {
            return ['success' => false, 'error' => 'Informe o ano letivo.'];
        }
        if (!$this->periodoAceito($ano, $bimestre)) {
            return ['success' => false, 'error' => PeriodoLetivo::mensagemNumeroInvalido($ano)];
        }

        $config = $this->normalizarConfig($post);
        if (isset($config['error'])) {
            return ['success' => false, 'error' => $config['error']];
        }
        if ($config['formato'] === 'online_questoes') {
            $post['canal_origem'] = 'online';
            $post['origem'] = 'prova_online';
        } else {
            $post['canal_origem'] = 'offline';
            if (($post['modo_offline'] ?? '') === '') {
                $post['modo_offline'] = 'lancamento_direto';
            }
        }

        $turmaIds = $this->idsPositivos($post['turmas'] ?? []);
        if ($turmaIds === []) {
            return ['success' => false, 'error' => 'Selecione pelo menos uma turma.'];
        }

        $catalogo = $this->catalogoFormulario($ano);
        $professoresValidos = [];
        foreach ($catalogo['professores'] as $professor) {
            $professoresValidos[(int) ($professor['id'] ?? 0)] = true;
        }
        $materiasValidas = [];
        foreach ($catalogo['materias'] as $materia) {
            $materiasValidas[(int) ($materia['id'] ?? 0)] = true;
        }
        $subeventos = $this->normalizarSubeventos(
            $post['subeventos'] ?? [],
            $post['pares_padrao'] ?? [],
            $turmaIds,
            $config['professor_opcional'],
            $config['exige_questoes'],
            $professoresValidos,
            $materiasValidas,
            $config['formato'] === 'online_questoes'
        );
        if (isset($subeventos['error'])) {
            return ['success' => false, 'error' => $subeventos['error']];
        }

        $tipos = new ExamEvaluationType();

        $regras = new TipoNotaRegraService();
        $tipoData = $regras->normalizar($post);
        $qtd = count($subeventos['itens']);
        $tipoData['nome'] = $nome;
        $tipoData['ativo'] = 1;
        if (($tipoData['origem'] ?? '') === 'eventos' && empty($tipoData['quantidade_eventos_esperada'])) {
            $tipoData['quantidade_eventos_esperada'] = $qtd;
        }

        $liberarNota = $this->liberarNotaDoPost($post);
        if (isset($liberarNota['error'])) {
            return ['success' => false, 'error' => $liberarNota['error']];
        }
        $sigla = $this->siglaDoPost($post);
        if (isset($sigla['error'])) {
            return ['success' => false, 'error' => $sigla['error']];
        }
        $calendario = $this->prepararCalendario(
            $this->pedidoCalendario($post),
            $ano,
            $nome,
            $turmaIds,
            $subeventos['itens']
        );
        if (isset($calendario['error'])) {
            return ['success' => false, 'error' => $calendario['error']];
        }

        $tipoId = 0;
        $grupoId = 0;
        $blocosCriados = [];
        $eventoId = 0;

        try {
            $tipoId = $tipos->create($tipoData);
            if ($tipoId <= 0) {
                return ['success' => false, 'error' => 'Não foi possível criar o tipo de nota.'];
            }

            $quadro = $this->criarQuadro($nome, (string) ($tipoData['descricao'] ?? ''), $subeventos['itens'], $tipoId, !empty($post['incluir_media']));
            if (empty($quadro['success'])) {
                $this->desfazerTipo($tipos, $tipoId);
                return ['success' => false, 'error' => (string) ($quadro['error'] ?? 'Não foi possível criar o quadro.')];
            }
            $grupoId = (int) $quadro['id'];
            $marcas = $quadro['marcas'];

            $blocos = new ExamBlock();
            foreach ($subeventos['itens'] as $i => $sub) {
                $marca = $marcas[$i] ?? null;
                if ($marca === null) {
                    throw new \RuntimeException('Coluna do quadro não encontrada para ' . $sub['titulo'] . '.');
                }
                $blocoId = $blocos->create([
                    'titulo' => $sub['titulo'],
                    'descricao' => $tipoData['descricao'],
                    'ano_letivo' => $ano,
                    'bimestre' => $bimestre,
                    'tipo_avaliacao_id' => $tipoId,
                    'semana' => (int) $marca['numero'],
                    'data_prova' => $sub['data'],
                    'hora_inicio' => $sub['hora_inicio'] ?? $config['hora_inicio'],
                    'hora_fim' => $sub['hora_fim'] ?? $config['hora_fim'],
                    'criado_por' => $criadoPor,
                    'professores' => $sub['pares'],
                    'turmas' => $turmaIds,
                    'tipo_prova' => $config['tipo_prova'],
                    'formato_evento' => $config['formato'],
                    'configuracao_nota' => $config['configuracao'],
                    'prazo_entrega_professor' => $config['prazo'],
                    'liberar_gabarito' => 'imediatamente',
                    'ativo' => 1,
                    'liberado' => 0,
                    'visivel_no_portal_aluno' => $config['visivel_portal'],
                    'nota_unica_todas_materias' => $config['nota_unica'],
                    'grupo_regras_notas_id' => $grupoId,
                    'grupo_regras_marca_id' => (int) $marca['id'],
                    'grupos_regras_vinculos' => [[
                        'grupo_id' => $grupoId,
                        'tipo_id' => null,
                        'marca_id' => (int) $marca['id'],
                    ]],
                ]);
                $blocosCriados[] = (int) $blocoId;
                $this->substituirAgendas((int) $blocoId, $sub['agendas'] ?? []);
            }

            $eventoId = $this->model->criar([
                'nome' => $nome,
                'descricao' => $tipoData['descricao'],
                'ano_letivo' => $ano,
                'bimestre' => $bimestre,
                'tipo_avaliacao_id' => $tipoId,
                'grupo_regras_notas_id' => $grupoId,
                'criado_por' => $criadoPor,
                'liberar_nota_aluno' => $liberarNota['valor'],
                'sigla' => $sigla['valor'],
                'rascunho' => !empty($post['rascunho']) ? 1 : 0,
            ]);
            foreach ($blocosCriados as $blocoId) {
                $this->model->vincularBloco($eventoId, $blocoId);
            }
            $gravado = $this->gravarCalendario($eventoId, $calendario);
            if (isset($gravado['error'])) {
                throw new \RuntimeException((string) $gravado['error']);
            }
            $alunosRec = $this->gravarAlunosRecuperacao($eventoId, $bimestre, $turmaIds, $post);
            if (isset($alunosRec['error'])) {
                throw new \RuntimeException((string) $alunosRec['error']);
            }
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::criar: ' . $e->getMessage());
            $mensagemCalendario = $e->getMessage();
            $this->desfazerBlocos($blocosCriados, $criadoPor);
            if ($eventoId > 0) {
                $this->model->marcarExcluido($eventoId);
                $this->retirarDoCalendario($eventoId);
            }
            if ($grupoId > 0) {
                $this->desfazerQuadro($grupoId);
            }
            if ($tipoId > 0) {
                $this->desfazerTipo($tipos, $tipoId);
            }
            $erroCriar = str_contains($mensagemCalendario, 'calendário') || str_contains($mensagemCalendario, 'recuperação') || str_contains($mensagemCalendario, 'migration')
                ? $mensagemCalendario
                : 'Não foi possível criar o evento. Confira professores, matérias e datas.';
            return ['success' => false, 'error' => $erroCriar];
        }

        return ['success' => true, 'id' => $eventoId];
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function excluir(int $id, int $usuarioId): array
    {
        $evento = $this->model->findById($id);
        if ($evento === null) {
            return ['success' => false, 'error' => 'Evento não encontrado.'];
        }
        if ($this->model->temNotaLancada($id)) {
            return ['success' => false, 'error' => 'Este evento já tem nota lançada. Não é possível excluir.'];
        }

        $subeventos = $this->model->listarSubeventos($id);
        try {
            $blocos = new ExamBlock();
            foreach ($subeventos as $sub) {
                $blocos->delete((int) $sub['id'], $usuarioId);
            }
            $this->model->marcarExcluido($id);
            $tipoId = (int) ($evento['tipo_avaliacao_id'] ?? 0);
            if ($tipoId > 0) {
                (new ExamEvaluationType())->softDelete($tipoId);
            }
            $grupoId = (int) ($evento['grupo_regras_notas_id'] ?? 0);
            if ($grupoId > 0) {
                $this->desfazerQuadro($grupoId);
            }
            $this->retirarDoCalendario($id);
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::excluir: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível excluir o evento.'];
        }

        return ['success' => true];
    }

    /**
     * Copia os eventos do período anterior para o período atual, como rascunho.
     *
     * @return array{success:bool,criados?:int,error?:string}
     */
    public function duplicarDoPeriodoAnterior(int $ano, int $periodo, int $usuarioId): array
    {
        if (!$this->model->tabelasProntas()) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_26_eventos_avaliativos.sql no painel Master.'];
        }
        if (!$this->model->temColunaRascunho()) {
            return ['success' => false, 'error' => 'Rode a migration 2026_10_28_evento_rascunho.sql no painel Master.'];
        }
        if ($periodo < 2 || !PeriodoLetivo::numeroValido($ano, $periodo) || !PeriodoLetivo::numeroValido($ano, $periodo - 1)) {
            return ['success' => false, 'error' => 'A duplicação começa no segundo período.'];
        }

        $origem = $this->model->listar($ano, $periodo - 1);
        if ($origem === []) {
            $rotulo = PeriodoLetivo::rotulo($ano, $periodo - 1);
            return ['success' => false, 'error' => 'Não há eventos em ' . ($rotulo !== '' ? $rotulo : 'o período anterior') . ' para duplicar.'];
        }

        $jaExistem = [];
        foreach ($this->model->listar($ano, $periodo) as $atual) {
            $nomeAtual = mb_strtolower(trim((string) ($atual['nome'] ?? '')));
            if ($nomeAtual !== '') {
                $jaExistem[$nomeAtual] = true;
            }
        }

        $criados = 0;
        $erro = '';
        foreach ($origem as $evento) {
            $nome = trim((string) ($evento['nome'] ?? ''));
            if ($nome === '' || isset($jaExistem[mb_strtolower($nome)])) {
                continue;
            }
            $post = $this->postParaDuplicar((int) ($evento['id'] ?? 0), $ano, $periodo);
            if ($post === null) {
                $erro = 'Não foi possível ler "' . $nome . '".';
                continue;
            }
            $resultado = $this->criar($post, $usuarioId);
            if (empty($resultado['success'])) {
                $erro = (string) ($resultado['error'] ?? 'Não foi possível duplicar "' . $nome . '".');
                continue;
            }
            $jaExistem[mb_strtolower($nome)] = true;
            $criados++;
        }

        if ($criados === 0) {
            return ['success' => false, 'error' => $erro !== '' ? $erro : 'Os eventos do período anterior já estão neste período.'];
        }

        return ['success' => true, 'criados' => $criados];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function postParaDuplicar(int $eventoId, int $ano, int $periodo): ?array
    {
        $dados = $this->dadosParaEdicao($eventoId);
        if ($dados === null) {
            return null;
        }
        $subeventos = [];
        foreach (is_array($dados['subeventos'] ?? null) ? $dados['subeventos'] : [] as $sub) {
            if (!is_array($sub)) {
                continue;
            }
            $item = [
                'titulo' => (string) ($sub['titulo'] ?? ''),
                'data' => (string) ($sub['data'] ?? ''),
                'hora_inicio' => (string) ($sub['hora_inicio'] ?? ''),
                'hora_fim' => (string) ($sub['hora_fim'] ?? ''),
            ];
            if (!empty($sub['ajuste'])) {
                $item['ajuste_proprio'] = 1;
                $item['pares'] = $this->paresParaPost(is_array($sub['pares'] ?? null) ? $sub['pares'] : []);
            }
            $subeventos[] = $item;
        }

        return [
            'nome' => (string) ($dados['nome'] ?? ''),
            'ano_letivo' => $ano,
            'bimestre' => $periodo,
            'sigla' => (string) ($dados['sigla'] ?? ''),
            'liberar_nota_aluno' => 0,
            'canal_origem' => (string) ($dados['canal'] ?? 'offline'),
            'configuracao_nota' => (string) ($dados['configuracao_nota'] ?? 'professor_por_questao'),
            'visivel_no_portal_aluno' => 0,
            'nota_unica_todas_materias' => !empty($dados['nota_unica']) ? 1 : 0,
            'prazo_entrega_professor' => (string) ($dados['prazo'] ?? ''),
            'modo_offline' => (string) ($dados['modo_offline'] ?? 'lancamento_direto'),
            'registro_evento' => (string) ($dados['registro_evento'] ?? 'nota'),
            'criterio_fechamento' => (string) ($dados['criterio_fechamento'] ?? 'ultima'),
            'escala_max' => (string) ($dados['escala_max'] ?? '10.00'),
            'criterio_professores' => (string) ($dados['criterio_professores'] ?? 'nenhum'),
            'hora_inicio' => (string) ($dados['hora_inicio'] ?? ''),
            'hora_fim' => (string) ($dados['hora_fim'] ?? ''),
            'turmas' => is_array($dados['turmas'] ?? null) ? $dados['turmas'] : [],
            'pares_padrao' => $this->paresParaPost(is_array($dados['pares'] ?? null) ? $dados['pares'] : []),
            'subeventos' => $subeventos,
            'rascunho' => 1,
        ];
    }

    /**
     * @param list<array<string,mixed>> $pares
     * @return list<array<string,mixed>>
     */
    private function paresParaPost(array $pares): array
    {
        $saida = [];
        foreach ($pares as $par) {
            if (!is_array($par)) {
                continue;
            }
            $saida[] = [
                'professor_id' => (int) ($par['professor_id'] ?? 0),
                'materia_id' => (int) ($par['materia_id'] ?? 0),
                'turmas' => array_map('intval', is_array($par['turmas'] ?? null) ? $par['turmas'] : []),
                'quantidade_questoes' => (int) ($par['qtd'] ?? $par['quantidade_questoes'] ?? 0),
            ];
        }
        return $saida;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function dadosParaEdicao(int $id): ?array
    {
        $evento = $this->model->findById($id);
        if ($evento === null) {
            return null;
        }

        $tipo = (new ExamEvaluationType())->findById((int) ($evento['tipo_avaliacao_id'] ?? 0)) ?: [];
        $blocos = $this->db->fetchAll(
            'SELECT id, titulo, data_prova, hora_inicio, hora_fim, formato_evento,
                    configuracao_nota, prazo_entrega_professor, visivel_no_portal_aluno,
                    nota_unica_todas_materias
               FROM provas_blocos
              WHERE evento_avaliativo_id = :id AND deleted_at IS NULL
              ORDER BY semana ASC, data_prova ASC, id ASC',
            ['id' => $id]
        ) ?: [];
        $turmaRows = $this->db->fetchAll(
            'SELECT DISTINCT pbt.turma_id
               FROM provas_blocos_turmas pbt
               INNER JOIN provas_blocos pb ON pb.id = pbt.bloco_id
              WHERE pb.evento_avaliativo_id = :id AND pb.deleted_at IS NULL',
            ['id' => $id]
        ) ?: [];
        $turmaIds = [];
        foreach ($turmaRows as $row) {
            $turmaId = (int) ($row['turma_id'] ?? 0);
            if ($turmaId > 0) {
                $turmaIds[$turmaId] = $turmaId;
            }
        }
        $turmaIds = array_values($turmaIds);
        $parRows = $this->db->fetchAll(
            'SELECT pbp.id, pbp.bloco_id, pbp.professor_id, pbp.materia_id, pbp.quantidade_questoes,
                    pbpt.turma_id
               FROM provas_blocos_professores pbp
               INNER JOIN provas_blocos pb ON pb.id = pbp.bloco_id
               LEFT JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
              WHERE pb.evento_avaliativo_id = :id AND pb.deleted_at IS NULL
              ORDER BY pbp.bloco_id ASC, pbp.id ASC',
            ['id' => $id]
        ) ?: [];
        $paresPorBloco = $this->paresPorBloco($parRows, $turmaIds);

        $agendasPorBloco = $this->agendasPorBloco(array_map(static function (array $bloco): int {
            return (int) ($bloco['id'] ?? 0);
        }, $blocos));

        $primeiro = $blocos[0] ?? [];
        $formato = (string) ($primeiro['formato_evento'] ?? 'lancamento_nota');
        $padrao = [];
        $assinaturaPadrao = null;
        $subeventos = [];
        foreach ($blocos as $bloco) {
            $blocoId = (int) ($bloco['id'] ?? 0);
            $pares = $paresPorBloco[$blocoId] ?? [];
            $assinatura = $this->assinaturaPares($pares);
            if ($assinaturaPadrao === null) {
                $assinaturaPadrao = $assinatura;
                $padrao = $pares;
            }
            $ajuste = $assinatura !== $assinaturaPadrao;
            $agenda = $this->apresentarAgenda($agendasPorBloco[$blocoId] ?? []);
            $subeventos[] = [
                'id' => $blocoId,
                'titulo' => (string) ($bloco['titulo'] ?? ''),
                'data' => $this->dataIso($bloco['data_prova'] ?? ''),
                'hora_inicio' => $this->horaCurta($bloco['hora_inicio'] ?? ''),
                'hora_fim' => $this->horaCurta($bloco['hora_fim'] ?? ''),
                'ajuste' => $ajuste,
                'pares' => $ajuste ? $pares : [],
                'agenda_modo' => $agenda['modo'],
                'agendas' => $agenda['grupos'],
            ];
        }

        $escala = $tipo['escala_max'] ?? '10.00';
        if (is_numeric($escala)) {
            $escala = number_format((float) $escala, 2, '.', '');
        }

        return [
            'id' => (int) $evento['id'],
            'nome' => (string) ($evento['nome'] ?? ''),
            'ano_letivo' => (int) ($evento['ano_letivo'] ?? 0),
            'bimestre' => (int) ($evento['bimestre'] ?? 0),
            'canal' => $formato === 'online_questoes' ? 'online' : 'offline',
            'configuracao_nota' => (string) ($primeiro['configuracao_nota'] ?? 'professor_por_questao'),
            'visivel_portal' => !empty($primeiro['visivel_no_portal_aluno']) ? 1 : 0,
            'liberar_nota_aluno' => !empty($evento['liberar_nota_aluno']) ? 1 : 0,
            'sigla' => strtoupper(trim((string) ($evento['sigla'] ?? ''))),
            'nota_unica' => !empty($primeiro['nota_unica_todas_materias']) ? 1 : 0,
            'prazo' => $this->prazoCampo($primeiro['prazo_entrega_professor'] ?? ''),
            'modo_offline' => count($subeventos) > 1 ? 'eventos' : 'lancamento_direto',
            'registro_evento' => (string) ($tipo['registro_evento'] ?? 'nota'),
            'criterio_fechamento' => (string) ($tipo['criterio_fechamento'] ?? 'ultima'),
            'escala_max' => (string) $escala,
            'criterio_professores' => (string) ($tipo['criterio_professores_mesmo_componente'] ?? 'nenhum'),
            'hora_inicio' => $this->horaCurta($primeiro['hora_inicio'] ?? ''),
            'hora_fim' => $this->horaCurta($primeiro['hora_fim'] ?? ''),
            'turmas' => $turmaIds,
            'alunos_recuperacao' => $this->model->alunosDoEvento((int) $evento['id']),
            'pares' => $padrao,
            'subeventos' => $subeventos,
            'no_calendario' => $this->estaNoCalendario((int) $evento['id']) ? 1 : 0,
        ];
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool,error?:string}
     */
    public function atualizar(int $id, array $post, int $usuarioId): array
    {
        $evento = $this->model->findById($id);
        if ($evento === null) {
            return ['success' => false, 'error' => 'Evento não encontrado.'];
        }

        $nome = trim((string) ($post['nome'] ?? ''));
        if ($nome === '') {
            return ['success' => false, 'error' => 'Informe o nome do evento.'];
        }
        if (mb_strlen($nome) > 120) {
            return ['success' => false, 'error' => 'O nome do evento pode ter no máximo 120 caracteres.'];
        }

        $ano = (int) ($post['ano_letivo'] ?? 0);
        $bimestre = (int) ($post['bimestre'] ?? 0);
        if ($ano < 2000) {
            return ['success' => false, 'error' => 'Informe o ano letivo.'];
        }
        if (!$this->periodoAceito($ano, $bimestre)) {
            return ['success' => false, 'error' => PeriodoLetivo::mensagemNumeroInvalido($ano)];
        }

        $config = $this->normalizarConfig($post);
        if (isset($config['error'])) {
            return ['success' => false, 'error' => $config['error']];
        }
        if ($config['formato'] === 'online_questoes') {
            $post['canal_origem'] = 'online';
            $post['origem'] = 'prova_online';
        } else {
            $post['canal_origem'] = 'offline';
            if (($post['modo_offline'] ?? '') === '') {
                $post['modo_offline'] = 'lancamento_direto';
            }
        }

        $turmaIds = $this->idsPositivos($post['turmas'] ?? []);
        if ($turmaIds === []) {
            return ['success' => false, 'error' => 'Selecione pelo menos uma turma.'];
        }

        $catalogo = $this->catalogoFormulario($ano);
        $professoresValidos = [];
        foreach ($catalogo['professores'] as $professor) {
            $professoresValidos[(int) ($professor['id'] ?? 0)] = true;
        }
        $materiasValidas = [];
        foreach ($catalogo['materias'] as $materia) {
            $materiasValidas[(int) ($materia['id'] ?? 0)] = true;
        }
        $subeventos = $this->normalizarSubeventos(
            $post['subeventos'] ?? [],
            $post['pares_padrao'] ?? [],
            $turmaIds,
            $config['professor_opcional'],
            $config['exige_questoes'],
            $professoresValidos,
            $materiasValidas,
            $config['formato'] === 'online_questoes'
        );
        if (isset($subeventos['error'])) {
            return ['success' => false, 'error' => $subeventos['error']];
        }

        $existentes = $this->db->fetchAll(
            'SELECT id, titulo, descricao, ativo, liberado, hora_inicio, hora_fim, grupo_regras_marca_id
               FROM provas_blocos
              WHERE evento_avaliativo_id = :id AND deleted_at IS NULL',
            ['id' => $id]
        ) ?: [];
        $mapa = [];
        foreach ($existentes as $bloco) {
            $blocoId = (int) ($bloco['id'] ?? 0);
            if ($blocoId > 0) {
                $mapa[$blocoId] = $bloco;
            }
        }

        $vistos = [];
        foreach ($subeventos['itens'] as $i => $sub) {
            $blocoId = (int) ($sub['id'] ?? 0);
            if ($blocoId <= 0 || !isset($mapa[$blocoId]) || isset($vistos[$blocoId])) {
                $subeventos['itens'][$i]['id'] = 0;
                continue;
            }
            $vistos[$blocoId] = true;
        }

        foreach ($mapa as $blocoId => $bloco) {
            if (isset($vistos[$blocoId])) {
                continue;
            }
            if ($this->model->blocoTemNota($blocoId)) {
                $titulo = trim((string) ($bloco['titulo'] ?? ''));
                if ($titulo === '') {
                    $titulo = 'Sub-evento #' . $blocoId;
                }
                return ['success' => false, 'error' => 'Não é possível remover "' . $titulo . '" porque já tem nota lançada.'];
            }
        }

        $tipos = new ExamEvaluationType();
        $tipoId = (int) ($evento['tipo_avaliacao_id'] ?? 0);
        $tipoAtual = $tipoId > 0 ? ($tipos->findById($tipoId) ?: []) : [];
        $regras = new TipoNotaRegraService();
        $tipoData = $regras->normalizar($post);
        $tipoData['nome'] = $nome;
        $tipoData['ativo'] = 1;
        if (empty($tipoData['descricao']) && !empty($tipoAtual['descricao'])) {
            $tipoData['descricao'] = $tipoAtual['descricao'];
        }
        $qtd = count($subeventos['itens']);
        if (($tipoData['origem'] ?? '') === 'eventos' && empty($tipoData['quantidade_eventos_esperada'])) {
            $tipoData['quantidade_eventos_esperada'] = $qtd;
        }

        $liberarNota = $this->liberarNotaDoPost($post);
        if (isset($liberarNota['error'])) {
            return ['success' => false, 'error' => $liberarNota['error']];
        }
        $sigla = $this->siglaDoPost($post);
        if (isset($sigla['error'])) {
            return ['success' => false, 'error' => $sigla['error']];
        }
        $calendario = $this->prepararCalendario(
            $this->pedidoCalendario($post),
            $ano,
            $nome,
            $turmaIds,
            $subeventos['itens']
        );
        if (isset($calendario['error'])) {
            return ['success' => false, 'error' => $calendario['error']];
        }

        $grupoId = (int) ($evento['grupo_regras_notas_id'] ?? 0);
        $blocos = new ExamBlock();
        $criados = [];

        try {
            if ($tipoId > 0) {
                $tipos->update($tipoId, $tipoData);
            }
            $this->model->atualizar($id, [
                'nome' => $nome,
                'descricao' => $tipoData['descricao'] ?? null,
                'ano_letivo' => $ano,
                'bimestre' => $bimestre,
                'liberar_nota_aluno' => $liberarNota['valor'],
                'sigla' => $sigla['valor'],
            ]);
            $this->renomearQuadro($grupoId, $nome);

            foreach ($subeventos['itens'] as $i => $sub) {
                $blocoId = (int) ($sub['id'] ?? 0);
                $semana = $i + 1;
                if ($blocoId > 0 && isset($mapa[$blocoId])) {
                    $atual = $mapa[$blocoId];
                    $horaInicio = $sub['hora_inicio'] ?? $this->horaCurta($atual['hora_inicio'] ?? '');
                    $horaFim = $sub['hora_fim'] ?? $this->horaCurta($atual['hora_fim'] ?? '');
                    $blocos->update($blocoId, [
                        'titulo' => $sub['titulo'],
                        'descricao' => $atual['descricao'] ?? ($tipoData['descricao'] ?? null),
                        'data_prova' => $sub['data'],
                        'hora_inicio' => $horaInicio !== '' ? $horaInicio : ($config['hora_inicio'] ?? null),
                        'hora_fim' => $horaFim !== '' ? $horaFim : ($config['hora_fim'] ?? null),
                        'professores' => $sub['pares'],
                        'turmas' => $turmaIds,
                        'tipo_prova' => $config['tipo_prova'],
                        'formato_evento' => $config['formato'],
                        'configuracao_nota' => $config['configuracao'],
                        'prazo_entrega_professor' => $config['prazo'],
                        'liberar_gabarito' => 'imediatamente',
                        'ativo' => (int) ($atual['ativo'] ?? 1) ? 1 : 0,
                        'liberado' => (int) ($atual['liberado'] ?? 0) ? 1 : 0,
                        'visivel_no_portal_aluno' => $config['visivel_portal'],
                        'nota_unica_todas_materias' => $config['nota_unica'],
                        'ano_letivo' => $ano,
                        'bimestre' => $bimestre,
                        'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
                        'semana' => $semana,
                    ]);
                    $this->renomearColunaQuadro((int) ($atual['grupo_regras_marca_id'] ?? 0), $sub['titulo']);
                    $this->substituirAgendas($blocoId, $sub['agendas'] ?? []);
                    continue;
                }

                $novoId = (int) $blocos->create([
                    'titulo' => $sub['titulo'],
                    'descricao' => $tipoData['descricao'] ?? null,
                    'ano_letivo' => $ano,
                    'bimestre' => $bimestre,
                    'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
                    'semana' => $semana,
                    'data_prova' => $sub['data'],
                    'hora_inicio' => $sub['hora_inicio'] ?? $config['hora_inicio'],
                    'hora_fim' => $sub['hora_fim'] ?? $config['hora_fim'],
                    'criado_por' => $usuarioId,
                    'professores' => $sub['pares'],
                    'turmas' => $turmaIds,
                    'tipo_prova' => $config['tipo_prova'],
                    'formato_evento' => $config['formato'],
                    'configuracao_nota' => $config['configuracao'],
                    'prazo_entrega_professor' => $config['prazo'],
                    'liberar_gabarito' => 'imediatamente',
                    'ativo' => 1,
                    'liberado' => 0,
                    'visivel_no_portal_aluno' => $config['visivel_portal'],
                    'nota_unica_todas_materias' => $config['nota_unica'],
                    'grupo_regras_notas_id' => $grupoId > 0 ? $grupoId : null,
                ]);
                if ($novoId <= 0) {
                    throw new \RuntimeException('Não foi possível incluir a data ' . $sub['titulo'] . '.');
                }
                $criados[] = $novoId;
                $this->model->vincularBloco($id, $novoId);
                $this->substituirAgendas($novoId, $sub['agendas'] ?? []);
            }

            foreach (array_keys($mapa) as $blocoId) {
                if (isset($vistos[$blocoId])) {
                    continue;
                }
                $blocos->delete($blocoId, $usuarioId);
            }
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::atualizar: ' . $e->getMessage());
            $this->desfazerBlocos($criados, $usuarioId);
            return ['success' => false, 'error' => 'Não foi possível salvar o evento. Confira professores, matérias e datas.'];
        }

        $gravado = $this->gravarCalendario($id, $calendario);
        if (isset($gravado['error'])) {
            return ['success' => false, 'error' => $gravado['error']];
        }
        $alunosRec = $this->gravarAlunosRecuperacao($id, $bimestre, $turmaIds, $post);
        if (isset($alunosRec['error'])) {
            return ['success' => false, 'error' => $alunosRec['error']];
        }
        if (!empty($evento['rascunho'])) {
            $this->model->publicar($id);
        }

        return ['success' => true];
    }

    /**
     * Alunos das turmas, com a menor nota do período de origem e se está abaixo da média.
     *
     * @param list<int> $turmaIds
     * @return array{media:float,alunos:list<array{id:int,nome:string,turma_id:int,turma:string,nota:?float,abaixo:bool}>}|array{error:string}
     */
    public function candidatosRecuperacao(int $ano, int $codigoPeriodo, array $turmaIds, int $regraId = 0): array
    {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds), static function (int $id): bool {
            return $id > 0;
        })));
        if ($ano < 2000 || $codigoPeriodo < RecuperacaoConfig::CODIGO_CICLO_BASE || $turmaIds === []) {
            return ['media' => 6.0, 'alunos' => []];
        }
        $media = $this->mediaMinima($ano, $regraId);
        $periodos = $this->periodosDaRecuperacao($codigoPeriodo);
        $params = ['ano' => $ano];
        $turmasPh = [];
        foreach ($turmaIds as $i => $turmaId) {
            $chave = 't' . $i;
            $turmasPh[] = ':' . $chave;
            $params[$chave] = $turmaId;
        }
        $filtroPeriodoNotas = '';
        $filtroPeriodoMaterias = '';
        if ($periodos !== []) {
            $periodosNota = [];
            $periodosMateria = [];
            foreach ($periodos as $i => $periodo) {
                $chaveNota = 'pn' . $i;
                $chaveMateria = 'pm' . $i;
                $periodosNota[] = ':' . $chaveNota;
                $periodosMateria[] = ':' . $chaveMateria;
                $params[$chaveNota] = $periodo;
                $params[$chaveMateria] = $periodo;
            }
            $filtroPeriodoNotas = ' AND n.periodo IN (' . implode(', ', $periodosNota) . ')';
            $filtroPeriodoMaterias = ' AND n2.periodo IN (' . implode(', ', $periodosMateria) . ')';
        }
        $turmasMateria = [];
        $params['ano_mat'] = $ano;
        foreach ($turmaIds as $i => $turmaId) {
            $chave = 'mt' . $i;
            $turmasMateria[] = ':' . $chave;
            $params[$chave] = $turmaId;
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT a.id, a.nome, a.turma_id, t.nome AS turma_nome,
                        mat.id AS materia_id, mat.nome AS materia_nome, MIN(n.nota_final) AS menor_nota
                   FROM alunos a
                   INNER JOIN turmas t ON t.id = a.turma_id
                   INNER JOIN (
                        SELECT DISTINCT n2.materia_id
                          FROM notas_tipo_finais n2
                         WHERE n2.ano_letivo = :ano_mat
                           AND n2.turma_id IN (' . implode(', ', $turmasMateria) . ')
                           AND n2.materia_id > 0' . $filtroPeriodoMaterias . '
                   ) materias_periodo ON 1 = 1
                   INNER JOIN materias mat ON mat.id = materias_periodo.materia_id
                   LEFT JOIN notas_tipo_finais n
                     ON n.aluno_id = a.id
                    AND n.materia_id = mat.id
                    AND n.ano_letivo = :ano
                    AND n.turma_id = a.turma_id' . $filtroPeriodoNotas . '
                  WHERE a.turma_id IN (' . implode(', ', $turmasPh) . ')
                    AND a.ativo = 1
                    AND a.status = \'ACTIVE\'
                  GROUP BY a.id, a.nome, a.turma_id, t.nome, mat.id, mat.nome
                  ORDER BY mat.nome ASC, t.nome ASC, a.nome ASC',
                $params
            );
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::candidatosRecuperacao: ' . $e->getMessage());
            return ['error' => 'Não foi possível listar os alunos desta recuperação.'];
        }
        $alunos = [];
        foreach ($rows ?: [] as $row) {
            $nota = $row['menor_nota'];
            $notaNum = $nota === null || $nota === '' ? null : round((float) $nota, 2);
            $alunos[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => (string) ($row['nome'] ?? ''),
                'turma_id' => (int) ($row['turma_id'] ?? 0),
                'turma' => (string) ($row['turma_nome'] ?? ''),
                'materia_id' => (int) ($row['materia_id'] ?? 0),
                'materia' => (string) ($row['materia_nome'] ?? ''),
                'nota' => $notaNum,
                'abaixo' => $notaNum !== null && $notaNum < $media,
            ];
        }
        return ['media' => $media, 'alunos' => $alunos];
    }

    /**
     * @param list<int> $turmaIds
     * @param array<string,mixed> $post
     * @return array{ok:bool}|array{error:string}
     */
    private function gravarAlunosRecuperacao(int $eventoId, int $codigoPeriodo, array $turmaIds, array $post): array
    {
        if ($codigoPeriodo < RecuperacaoConfig::CODIGO_CICLO_BASE) {
            return ['ok' => true];
        }
        if (empty($post['definir_alunos_recuperacao'])) {
            if ($this->model->tabelaAlunosPronta()) {
                $this->model->limparAlunos($eventoId);
            }
            return ['ok' => true];
        }
        if (!$this->model->colunaMateriaPronta()) {
            return ['error' => 'Rode a migration 2026_10_29_eventos_avaliativos_alunos_materia.sql no painel Master.'];
        }
        $pares = $this->paresRecuperacao($post['alunos_recuperacao'] ?? []);
        if ($pares === []) {
            return ['error' => 'Marque pelo menos um aluno da recuperação.'];
        }
        $permitidas = array_fill_keys($turmaIds, true);
        $placeholders = [];
        $params = [];
        $alunosPedidos = [];
        foreach ($pares as $i => $par) {
            $chave = 'a' . $i;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $par[0];
            $alunosPedidos[$par[0]] = true;
        }
        $rows = $this->db->fetchAll(
            'SELECT id, turma_id FROM alunos WHERE id IN (' . implode(', ', $placeholders) . ')',
            $params
        );
        $turmaDoAluno = [];
        foreach ($rows ?: [] as $row) {
            $alunoId = (int) ($row['id'] ?? 0);
            $turmaId = (int) ($row['turma_id'] ?? 0);
            if ($alunoId > 0 && isset($permitidas[$turmaId])) {
                $turmaDoAluno[$alunoId] = $turmaId;
            }
        }
        $validos = [];
        foreach ($pares as $par) {
            if (isset($turmaDoAluno[$par[0]])) {
                $validos[] = $par;
            }
        }
        if (count($validos) !== count($pares) || count($turmaDoAluno) !== count($alunosPedidos)) {
            return ['error' => 'Os alunos da recuperação precisam estar nas turmas deste evento.'];
        }
        $this->model->substituirPares($eventoId, $validos);
        return ['ok' => true];
    }

    /**
     * @param mixed $raw
     * @return list<array{0:int,1:int}>
     */
    private function paresRecuperacao($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $pares = [];
        foreach ($raw as $item) {
            $partes = explode(':', trim((string) $item), 2);
            $alunoId = (int) ($partes[0] ?? 0);
            $materiaId = (int) ($partes[1] ?? 0);
            if ($alunoId <= 0 || $materiaId <= 0) {
                continue;
            }
            $pares[$alunoId . ':' . $materiaId] = [$alunoId, $materiaId];
        }
        return array_values($pares);
    }

    private function mediaMinima(int $ano, int $regraId): float
    {
        try {
            if ($regraId > 0) {
                $row = $this->db->fetch(
                    'SELECT media_minima FROM regras_academicas WHERE id = :id LIMIT 1',
                    ['id' => $regraId]
                );
            } else {
                $row = $this->db->fetch(
                    'SELECT media_minima FROM regras_academicas WHERE ano_letivo = :ano ORDER BY id ASC LIMIT 1',
                    ['ano' => $ano]
                );
            }
        } catch (Throwable $e) {
            return 6.0;
        }
        $media = $row['media_minima'] ?? null;
        if (!is_numeric($media)) {
            return 6.0;
        }
        return (float) $media;
    }

    /**
     * @return list<int>
     */
    private function periodosDaRecuperacao(int $codigo): array
    {
        if ($codigo >= RecuperacaoConfig::CODIGO_CICLO_BASE + 1 && $codigo < RecuperacaoConfig::CODIGO_PAR_BASE) {
            return [$codigo - RecuperacaoConfig::CODIGO_CICLO_BASE];
        }
        if ($codigo > RecuperacaoConfig::CODIGO_PAR_BASE && $codigo < RecuperacaoConfig::CODIGO_FINAL) {
            $par = $codigo - RecuperacaoConfig::CODIGO_PAR_BASE;
            $inicio = (($par - 1) * 2) + 1;
            return [$inicio, $inicio + 1];
        }
        return [];
    }

    /**
     * @param array<string,mixed> $post
     * @return array{formato:string,configuracao:string,tipo_prova:string,visivel_portal:int,nota_unica:int,prazo:?string,hora_inicio:?string,hora_fim:?string,professor_opcional:bool,exige_questoes:bool}|array{error:string}
     */
    private function normalizarConfig(array $post): array
    {
        $canal = (string) ($post['canal_origem'] ?? '');
        if ($canal === 'online') {
            $formato = 'online_questoes';
        } elseif ($canal === 'offline') {
            $formato = 'lancamento_nota';
        } else {
            $formato = (string) ($post['formato_evento'] ?? 'lancamento_nota');
            if (!in_array($formato, ['online_questoes', 'lancamento_nota'], true)) {
                $formato = 'lancamento_nota';
            }
        }
        $configuracao = (string) ($post['configuracao_nota'] ?? 'professor_por_questao');
        if (!in_array($configuracao, ['professor_por_questao', 'coordenacao_calcula'], true)) {
            $configuracao = 'professor_por_questao';
        }
        $tipoProva = (string) ($post['tipo_prova'] ?? 'original');
        if (!in_array($tipoProva, ['original', 'substitutiva'], true)) {
            $tipoProva = 'original';
        }

        $professorOpcional = $configuracao === 'coordenacao_calcula';
        $exigePrazo = $configuracao === 'professor_por_questao';
        $prazo = null;
        if ($exigePrazo) {
            $prazo = $this->normalizarDataHora((string) ($post['prazo_entrega_professor'] ?? ''));
            if ($prazo === null) {
                return ['error' => 'Informe o prazo para o professor.'];
            }
        }

        $horaInicio = $this->normalizarHora((string) ($post['hora_inicio'] ?? ''));
        $horaFim = $this->normalizarHora((string) ($post['hora_fim'] ?? ''));
        if ($formato === 'online_questoes' && ($horaInicio !== null || $horaFim !== null) && ($horaInicio === null || $horaFim === null || $horaInicio >= $horaFim)) {
            return ['error' => 'Informe o horário de início e de término da prova online.'];
        }

        return [
            'formato' => $formato,
            'configuracao' => $configuracao,
            'tipo_prova' => $tipoProva,
            'visivel_portal' => !empty($post['visivel_no_portal_aluno']) ? 1 : 0,
            'nota_unica' => ($formato === 'lancamento_nota' && $configuracao === 'coordenacao_calcula' && !empty($post['nota_unica_todas_materias'])) ? 1 : 0,
            'prazo' => $prazo,
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim,
            'professor_opcional' => $professorOpcional,
            'exige_questoes' => $formato === 'online_questoes' && $configuracao === 'professor_por_questao',
        ];
    }

    /**
     * @param mixed $raw
     * @param mixed $paresPadrao
     * @param list<int> $turmasEvento
     * @param array<int,bool> $professoresValidos
     * @param array<int,bool> $materiasValidas
     * @return array{itens:list<array{titulo:string,data:string,hora_inicio:?string,hora_fim:?string,pares:list<array{professor_id:?int,materia_id:int,turmas:list<int>,quantidade_questoes?:int}>}>}|array{error:string}
     */
    private function normalizarSubeventos($raw, $paresPadrao, array $turmasEvento, bool $professorOpcional, bool $exigeQuestoes, array $professoresValidos, array $materiasValidas, bool $exigeHorario = false): array
    {
        if (!is_array($raw)) {
            return ['error' => 'Inclua pelo menos um sub-evento, com nome e data.'];
        }
        $permitidas = array_fill_keys($turmasEvento, true);
        $padrao = $this->normalizarPares($paresPadrao, 'Professores e matérias', $permitidas, $professorOpcional, $exigeQuestoes, $professoresValidos, $materiasValidas);
        if (isset($padrao['error'])) {
            return $padrao;
        }
        $itens = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $titulo = trim((string) ($row['titulo'] ?? ''));
            $data = trim((string) ($row['data'] ?? ''));
            if ($titulo === '' && $data === '') {
                continue;
            }
            if ($titulo === '') {
                return ['error' => 'Todo sub-evento precisa de um nome.'];
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                return ['error' => 'Informe a data de "' . $titulo . '".'];
            }
            if (!empty($row['ajuste_proprio'])) {
                $pares = $this->normalizarPares($row['pares'] ?? [], $titulo, $permitidas, $professorOpcional, $exigeQuestoes, $professoresValidos, $materiasValidas);
                if (isset($pares['error'])) {
                    return $pares;
                }
                $paresItens = $pares['itens'];
            } else {
                $paresItens = $padrao['itens'];
            }
            $horaInicio = null;
            $horaFim = null;
            if ($exigeHorario) {
                $horaInicio = $this->normalizarHora((string) ($row['hora_inicio'] ?? ''));
                $horaFim = $this->normalizarHora((string) ($row['hora_fim'] ?? ''));
                if ($horaInicio === null || $horaFim === null || $horaInicio >= $horaFim) {
                    return ['error' => 'Informe o horário de início e de término de "' . $titulo . '".'];
                }
            }
            $agenda = $this->normalizarAgenda($row, $titulo, $permitidas, $horaInicio, $horaFim);
            if (isset($agenda['error'])) {
                return $agenda;
            }
            $blocoId = (int) ($row['id'] ?? 0);
            $itens[] = [
                'id' => $blocoId > 0 ? $blocoId : 0,
                'titulo' => mb_substr($titulo, 0, 150),
                'data' => $data,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'pares' => $paresItens,
                'agendas' => $agenda['itens'],
            ];
        }
        if ($itens === []) {
            return ['error' => 'Inclua pelo menos um sub-evento, com nome e data.'];
        }
        if (count($itens) > GrupoRegrasNotas::NUMERO_MAX) {
            return ['error' => 'Um evento pode ter no máximo ' . GrupoRegrasNotas::NUMERO_MAX . ' sub-eventos.'];
        }
        return ['itens' => $itens];
    }

    /**
     * @param mixed $raw
     * @param array<int,bool> $turmasPermitidas
     * @param array<int,bool> $professoresValidos
     * @param array<int,bool> $materiasValidas
     * @return array{itens:list<array{professor_id:?int,materia_id:int,turmas:list<int>,quantidade_questoes?:int}>}|array{error:string}
     */
    private function normalizarPares($raw, string $titulo, array $turmasPermitidas, bool $professorOpcional, bool $exigeQuestoes, array $professoresValidos, array $materiasValidas): array
    {
        if (!is_array($raw)) {
            return ['error' => 'Em "' . $titulo . '", adicione pelo menos uma matéria.'];
        }
        $itens = [];
        $vistos = [];
        foreach ($raw as $par) {
            if (!is_array($par)) {
                continue;
            }
            $materiaId = (int) ($par['materia_id'] ?? 0);
            $professorId = (int) ($par['professor_id'] ?? 0);
            if ($materiaId <= 0 && $professorId <= 0) {
                continue;
            }
            if ($materiaId <= 0 || !isset($materiasValidas[$materiaId])) {
                return ['error' => 'Em "' . $titulo . '", selecione a matéria.'];
            }
            if ($professorId <= 0 && !$professorOpcional) {
                return ['error' => 'Em "' . $titulo . '", selecione o professor.'];
            }
            if ($professorId > 0 && !isset($professoresValidos[$professorId])) {
                return ['error' => 'Em "' . $titulo . '", o professor selecionado não está ativo.'];
            }
            $turmas = [];
            foreach ($this->idsPositivos($par['turmas'] ?? []) as $turmaId) {
                if (isset($turmasPermitidas[$turmaId])) {
                    $turmas[$turmaId] = $turmaId;
                }
            }
            if ($turmas === []) {
                return ['error' => 'Em "' . $titulo . '", marque as turmas deste professor.'];
            }
            $chave = ($professorId > 0 ? $professorId : 0) . ':' . $materiaId;
            if (isset($vistos[$chave])) {
                $idx = $vistos[$chave];
                $mapaTurmas = [];
                foreach ($itens[$idx]['turmas'] as $turmaExistente) {
                    $turmaExistente = (int) $turmaExistente;
                    if ($turmaExistente > 0) {
                        $mapaTurmas[$turmaExistente] = $turmaExistente;
                    }
                }
                foreach ($turmas as $turmaId) {
                    $mapaTurmas[$turmaId] = $turmaId;
                }
                $itens[$idx]['turmas'] = array_values($mapaTurmas);
                continue;
            }
            $vistos[$chave] = count($itens);
            $item = [
                'professor_id' => $professorId > 0 ? $professorId : null,
                'materia_id' => $materiaId,
                'turmas' => array_values($turmas),
            ];
            if ($exigeQuestoes) {
                $qtd = (int) ($par['quantidade_questoes'] ?? 0);
                if ($qtd < 1) {
                    return ['error' => 'Em "' . $titulo . '", informe o número de questões.'];
                }
                $item['quantidade_questoes'] = $qtd;
            }
            $itens[] = $item;
        }
        if ($itens === []) {
            return ['error' => 'Em "' . $titulo . '", adicione pelo menos uma matéria.'];
        }
        return ['itens' => $itens];
    }

    /**
     * Notas já lançadas dos eventos marcados para o aluno ver na hora.
     * Não calcula média e não grava snapshot.
     *
     * @return list<array{titulo:string,colunas:list<array{id:int,titulo:string}>,linhas:list<array{materia:string,notas:array<int,string>}>}>
     */
    public function notasAoVivoDoAluno(int $alunoId, int $turmaId): array
    {
        if ($alunoId <= 0 || !$this->model->temColunaLiberarNota()) {
            return [];
        }
        $blocos = [];
        if ($turmaId > 0) {
            $rows = $this->db->fetchAll(
                'SELECT e.id AS evento_id, e.nome AS evento_nome, e.ano_letivo, e.bimestre,
                        pb.id AS bloco_id, pb.titulo, pb.data_prova, pb.semana
                   FROM eventos_avaliativos e
                   INNER JOIN provas_blocos pb ON pb.evento_avaliativo_id = e.id AND pb.deleted_at IS NULL
                   INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb.id AND pbt.turma_id = :turma
                  WHERE e.deleted_at IS NULL AND e.liberar_nota_aluno = 1' . $this->sqlSemRascunho('e') . '
                  ORDER BY e.ano_letivo DESC, e.bimestre ASC, e.id ASC, pb.semana ASC, pb.data_prova ASC, pb.id ASC',
                ['turma' => $turmaId]
            ) ?: [];
            foreach ($rows as $row) {
                $blocoId = (int) ($row['bloco_id'] ?? 0);
                if ($blocoId > 0) {
                    $blocos[$blocoId] = $row;
                }
            }
        }
        $notas = $this->db->fetchAll(
            'SELECT pb.id AS bloco_id, pb.titulo, pb.data_prova, pb.semana,
                    e.id AS evento_id, e.nome AS evento_nome, e.ano_letivo, e.bimestre,
                    n.id AS nota_id, n.materia_id, n.nota, m.nome AS materia_nome
               FROM provas_blocos_notas_lancadas n
               INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
               INNER JOIN eventos_avaliativos e ON e.id = pb.evento_avaliativo_id
                    AND e.deleted_at IS NULL AND e.liberar_nota_aluno = 1' . $this->sqlSemRascunho('e') . '
               LEFT JOIN materias m ON m.id = n.materia_id
              WHERE n.aluno_id = :aluno AND n.nota IS NOT NULL
              ORDER BY n.id ASC',
            ['aluno' => $alunoId]
        ) ?: [];
        $porBlocoMateria = [];
        foreach ($notas as $nota) {
            $blocoId = (int) ($nota['bloco_id'] ?? 0);
            $materiaId = (int) ($nota['materia_id'] ?? 0);
            if ($blocoId <= 0) {
                continue;
            }
            if (!isset($blocos[$blocoId])) {
                $blocos[$blocoId] = $nota;
            }
            $porBlocoMateria[$blocoId . ':' . $materiaId] = $nota;
        }
        if ($blocos === []) {
            return [];
        }

        $periodos = [];
        foreach ($blocos as $bloco) {
            $ano = (int) ($bloco['ano_letivo'] ?? 0);
            $bimestre = (int) ($bloco['bimestre'] ?? 0);
            $chave = $ano . ':' . $bimestre;
            if (!isset($periodos[$chave])) {
                $periodos[$chave] = [
                    'ano' => $ano,
                    'bimestre' => $bimestre,
                    'titulo' => $this->rotuloPeriodoAoVivo($ano, $bimestre),
                    'colunas' => [],
                    'materias' => [],
                ];
            }
            $blocoId = (int) ($bloco['bloco_id'] ?? 0);
            $titulo = trim((string) ($bloco['titulo'] ?? ''));
            if ($titulo === '') {
                $titulo = trim((string) ($bloco['evento_nome'] ?? 'Nota'));
            }
            $periodos[$chave]['colunas'][$blocoId] = [
                'id' => $blocoId,
                'titulo' => $titulo,
                'evento' => trim((string) ($bloco['evento_nome'] ?? '')),
                'ordem' => sprintf('%04d-%s-%010d', (int) ($bloco['semana'] ?? 0), (string) ($bloco['data_prova'] ?? ''), $blocoId),
            ];
        }
        foreach ($porBlocoMateria as $nota) {
            $ano = (int) ($nota['ano_letivo'] ?? 0);
            $bimestre = (int) ($nota['bimestre'] ?? 0);
            $chave = $ano . ':' . $bimestre;
            if (!isset($periodos[$chave])) {
                continue;
            }
            $materiaId = (int) ($nota['materia_id'] ?? 0);
            $materia = trim((string) ($nota['materia_nome'] ?? ''));
            if ($materia === '') {
                $materia = 'Sem matéria';
            }
            $blocoId = (int) ($nota['bloco_id'] ?? 0);
            $periodos[$chave]['materias'][$materiaId]['materia'] = $materia;
            $periodos[$chave]['materias'][$materiaId]['notas'][$blocoId] = $this->formatarNotaAoVivo($nota['nota']);
        }

        $saida = [];
        foreach ($periodos as $periodo) {
            $colunas = array_values($periodo['colunas']);
            usort($colunas, static function (array $a, array $b): int {
                return strcmp((string) $a['ordem'], (string) $b['ordem']);
            });
            $repetidos = [];
            foreach ($colunas as $coluna) {
                $repetidos[$coluna['titulo']] = ($repetidos[$coluna['titulo']] ?? 0) + 1;
            }
            $colunasProntas = [];
            foreach ($colunas as $coluna) {
                $titulo = (string) $coluna['titulo'];
                if (($repetidos[$titulo] ?? 0) > 1 && $coluna['evento'] !== '' && $coluna['evento'] !== $titulo) {
                    $titulo = $coluna['evento'] . ' · ' . $titulo;
                }
                $colunasProntas[] = ['id' => (int) $coluna['id'], 'titulo' => $titulo];
            }
            $linhas = array_values($periodo['materias']);
            usort($linhas, static function (array $a, array $b): int {
                return strcasecmp((string) ($a['materia'] ?? ''), (string) ($b['materia'] ?? ''));
            });
            $saida[] = [
                'ano' => (int) $periodo['ano'],
                'bimestre' => (int) $periodo['bimestre'],
                'titulo' => (string) $periodo['titulo'],
                'colunas' => $colunasProntas,
                'linhas' => $linhas,
            ];
        }
        usort($saida, static function (array $a, array $b): int {
            if ((int) $a['ano'] !== (int) $b['ano']) {
                return (int) $b['ano'] <=> (int) $a['ano'];
            }
            return (int) $a['bimestre'] <=> (int) $b['bimestre'];
        });
        return $saida;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{valor:int}|array{error:string}
     */
    private function liberarNotaDoPost(array $post): array
    {
        $ligado = !empty($post['liberar_nota_aluno']) ? 1 : 0;
        if ($ligado === 1 && !$this->model->temColunaLiberarNota()) {
            return ['error' => 'Rode a migration 2026_10_27_eventos_liberar_nota_aluno.sql no painel Master.'];
        }
        return ['valor' => $ligado];
    }

    /**
     * @param array<string,mixed> $post
     * @return array{valor:?string}|array{error:string}
     */
    private function siglaDoPost(array $post): array
    {
        $sigla = strtoupper(trim((string) ($post['sigla'] ?? '')));
        $sigla = preg_replace('/\s+/u', '', $sigla) ?? '';
        if ($sigla === '') {
            return ['valor' => null];
        }
        if (!preg_match('/^[A-Z0-9]{1,12}$/', $sigla)) {
            return ['error' => 'A sigla usa até 12 letras ou números. Ex.: PB.'];
        }
        if (!$this->model->temColunaSigla()) {
            return ['error' => 'Rode a migration 2026_10_27_eventos_sigla.sql no painel Master.'];
        }
        return ['valor' => $sigla];
    }

    private function sqlSemRascunho(string $alias): string
    {
        if (!$this->model->temColunaRascunho()) {
            return '';
        }
        $alias = preg_replace('/[^a-z]/', '', $alias) ?? '';
        if ($alias === '') {
            return '';
        }
        return ' AND ' . $alias . '.rascunho = 0';
    }

    private function rotuloPeriodoAoVivo(int $ano, int $bimestre): string
    {
        $especial = (new RecuperacaoConfig())->rotuloDoCodigo($ano, $bimestre);
        $periodo = $especial !== null && $especial !== '' ? $especial : PeriodoLetivo::rotulo($ano, $bimestre);
        if ($periodo === '') {
            $periodo = $bimestre > 0 ? $bimestre . 'º período' : 'Período';
        }
        return trim($ano . ' · ' . $periodo);
    }

    /**
     * @param mixed $nota
     */
    private function formatarNotaAoVivo($nota): string
    {
        if (!is_numeric($nota)) {
            return '';
        }
        $texto = number_format((float) $nota, 2, ',', '.');
        return rtrim(rtrim($texto, '0'), ',');
    }

    private function periodoAceito(int $ano, int $bimestre): bool
    {
        return (new RecuperacaoConfig())->codigoAceito($ano, $bimestre) || PeriodoLetivo::numeroValido($ano, $bimestre);
    }

    private function normalizarDataHora(string $valor): ?string
    {
        $valor = str_replace('T', ' ', trim($valor));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $valor) === 1) {
            return $valor . ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valor) === 1) {
            return $valor;
        }
        return null;
    }

    private function normalizarHora(string $valor): ?string
    {
        $valor = trim($valor);
        if (preg_match('/^\d{2}:\d{2}$/', $valor) === 1) {
            return $valor . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $valor) === 1) {
            return $valor;
        }
        return null;
    }

    /**
     * @param list<array{titulo:string,data:string}> $subeventos
     * @return array{success:bool,id?:int,marcas?:list<array<string,mixed>>,error?:string}
     */
    private function criarQuadro(string $nome, string $descricao, array $subeventos, int $tipoId, bool $incluirMedia): array
    {
        $colunas = [];
        foreach ($subeventos as $i => $sub) {
            $numero = $i + 1;
            $colunas[] = [
                'chave' => 's' . $numero,
                'codigo' => 's' . $numero,
                'nome' => $sub['titulo'],
                'numero' => $numero,
                'papel' => 'lancamento',
                'tipo_nota_id' => $tipoId,
            ];
        }
        if ($incluirMedia && count($subeventos) > 1) {
            $colunas[] = [
                'chave' => 'media',
                'codigo' => 'media',
                'nome' => 'Média',
                'numero' => 0,
                'papel' => 'calculada',
                'vai_para_boletim' => 1,
            ];
        }

        $service = new GrupoRegrasNotasService();
        $salvo = $service->salvar([
            'nome' => $nome,
            'descricao' => $descricao !== '' ? $descricao : null,
            'ativo' => 1,
            'colunas' => $colunas,
            'tipos' => [],
        ]);
        if (empty($salvo['success'])) {
            return ['success' => false, 'error' => (string) ($salvo['error'] ?? 'Não foi possível criar o quadro.')];
        }

        $grupoId = (int) $salvo['id'];
        $marcas = [];
        foreach ($service->model()->listarMarcas($grupoId) as $marca) {
            $numero = (int) ($marca['numero'] ?? 0);
            if ($numero >= 1 && $numero <= GrupoRegrasNotas::NUMERO_MAX) {
                $marcas[$numero] = $marca;
            }
        }
        ksort($marcas);
        $marcas = array_values($marcas);
        if (count($marcas) !== count($subeventos)) {
            $this->desfazerQuadro($grupoId);
            return ['success' => false, 'error' => 'O quadro não ficou com uma coluna para cada sub-evento.'];
        }

        return ['success' => true, 'id' => $grupoId, 'marcas' => $marcas];
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function idsPositivos($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    private function desfazerTipo(ExamEvaluationType $tipos, int $tipoId): void
    {
        if ($tipoId <= 0) {
            return;
        }
        try {
            $this->db->query(
                "UPDATE provas_tipos_avaliacao
                    SET nome = CONCAT(LEFT(nome, 100), ' #', id),
                        deleted_at = NOW(),
                        ativo = 0
                  WHERE id = :id AND deleted_at IS NULL",
                ['id' => $tipoId]
            );
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::desfazerTipo: ' . $e->getMessage());
            try {
                $tipos->softDelete($tipoId);
            } catch (Throwable $e2) {
                error_log('EventoAvaliativoService::desfazerTipo: ' . $e2->getMessage());
            }
        }
    }

    private function desfazerQuadro(int $grupoId): void
    {
        try {
            $res = (new GrupoRegrasNotasService())->excluirGrupo($grupoId);
            if (empty($res['success'])) {
                $tabela = (new GrupoRegrasNotas())->nomeTabela('quadro');
                $this->db->query(
                    'UPDATE `' . $tabela . '` SET ativo = 0 WHERE id = :id',
                    ['id' => $grupoId]
                );
            }
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::desfazerQuadro: ' . $e->getMessage());
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int> $turmasEvento
     * @return array<int, list<array{professor_id:string,materia_id:string,turmas:list<string>,qtd:string}>>
     */
    private function paresPorBloco(array $rows, array $turmasEvento): array
    {
        $turmasPadrao = array_map('strval', $turmasEvento);
        $agrupado = [];
        foreach ($rows as $row) {
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            $parId = (int) ($row['id'] ?? 0);
            if ($blocoId <= 0 || $parId <= 0) {
                continue;
            }
            if (!isset($agrupado[$blocoId][$parId])) {
                $qtd = (int) ($row['quantidade_questoes'] ?? 0);
                $agrupado[$blocoId][$parId] = [
                    'professor_id' => (int) ($row['professor_id'] ?? 0) > 0 ? (string) (int) $row['professor_id'] : '',
                    'materia_id' => (int) ($row['materia_id'] ?? 0) > 0 ? (string) (int) $row['materia_id'] : '',
                    'qtd' => $qtd > 0 ? (string) $qtd : '',
                    'turmas' => [],
                ];
            }
            $turmaId = (int) ($row['turma_id'] ?? 0);
            if ($turmaId > 0) {
                $agrupado[$blocoId][$parId]['turmas'][(string) $turmaId] = (string) $turmaId;
            }
        }

        $saida = [];
        foreach ($agrupado as $blocoId => $pares) {
            $lista = [];
            foreach ($pares as $par) {
                $turmas = array_values($par['turmas']);
                sort($turmas, SORT_STRING);
                if ($turmas === []) {
                    $turmas = $turmasPadrao;
                }
                $par['turmas'] = $turmas;
                $lista[] = $par;
            }
            $saida[$blocoId] = $lista;
        }
        return $saida;
    }

    /**
     * @param list<array{professor_id:string,materia_id:string,turmas:list<string>,qtd:string}> $pares
     */
    private function assinaturaPares(array $pares): string
    {
        $partes = [];
        foreach ($pares as $par) {
            $turmas = $par['turmas'];
            sort($turmas, SORT_STRING);
            $partes[] = ($par['professor_id'] !== '' ? $par['professor_id'] : '0')
                . ':' . ($par['materia_id'] !== '' ? $par['materia_id'] : '0')
                . ':' . implode(',', $turmas)
                . ':' . ($par['qtd'] ?? '');
        }
        sort($partes, SORT_STRING);
        return implode('|', $partes);
    }

    private function dataIso($valor): string
    {
        $valor = trim((string) $valor);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $valor, $m) === 1) {
            return $m[1];
        }
        return '';
    }

    private function horaCurta($valor): string
    {
        $valor = trim((string) $valor);
        if (preg_match('/^(\d{2}:\d{2})/', $valor, $m) === 1) {
            return $m[1];
        }
        return '';
    }

    private function prazoCampo($valor): string
    {
        $valor = trim((string) $valor);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', $valor, $m) === 1) {
            return $m[1] . 'T' . $m[2];
        }
        return '';
    }

    private function renomearQuadro(int $grupoId, string $nome): void
    {
        if ($grupoId <= 0 || $nome === '') {
            return;
        }
        $tabela = (new GrupoRegrasNotas())->nomeTabela('quadro');
        if (!in_array($tabela, ['quadros_notas', 'grupos_regras_notas'], true)) {
            return;
        }
        try {
            $this->db->query(
                'UPDATE `' . $tabela . '` SET nome = :nome WHERE id = :id',
                ['nome' => mb_substr($nome, 0, 120), 'id' => $grupoId]
            );
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::renomearQuadro: ' . $e->getMessage());
        }
    }

    private function renomearColunaQuadro(int $marcaId, string $nome): void
    {
        if ($marcaId <= 0 || $nome === '') {
            return;
        }
        $tabela = (new GrupoRegrasNotas())->nomeTabela('colunas');
        if (!in_array($tabela, ['quadros_notas_colunas', 'grupos_regras_notas_marcas'], true)) {
            return;
        }
        try {
            $this->db->query(
                'UPDATE `' . $tabela . '` SET nome = :nome WHERE id = :id',
                ['nome' => mb_substr($nome, 0, 150), 'id' => $marcaId]
            );
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::renomearColunaQuadro: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $post
     */
    private function pedidoCalendario(array $post): bool
    {
        return !empty($post['disponibilizar_calendario']) && empty($post['rascunho']);
    }

    private function colunaCalendarioPronta(): bool
    {
        try {
            $row = $this->db->fetch("SHOW COLUMNS FROM calendario_letivo_eventos LIKE 'evento_avaliativo_id'");
            return $row !== false && !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function estaNoCalendario(int $eventoId): bool
    {
        if ($eventoId <= 0 || !$this->colunaCalendarioPronta()) {
            return false;
        }
        $row = $this->db->fetch(
            'SELECT id FROM calendario_letivo_eventos WHERE evento_avaliativo_id = :id LIMIT 1',
            ['id' => $eventoId]
        );
        return $row !== false && !empty($row);
    }

    /**
     * @param list<int> $turmaIds
     * @param list<array<string,mixed>> $subeventos
     * @return array{publicar:bool,linhas?:list<array{calendario_id:int,data:string,descricao:string}>,error?:string}
     */
    private function prepararCalendario(bool $pedido, int $ano, string $nome, array $turmaIds, array $subeventos): array
    {
        if (!$pedido) {
            return ['publicar' => false];
        }
        if (!$this->colunaCalendarioPronta()) {
            return ['error' => 'Rode a migration 2026_10_29_evento_calendario_letivo.sql no painel Master.'];
        }

        $mapaTurmas = $this->mapaTurmasCalendario($turmaIds);
        $porCurso = [];
        foreach ($subeventos as $sub) {
            $titulo = trim((string) ($sub['titulo'] ?? ''));
            $agendas = is_array($sub['agendas'] ?? null) ? $sub['agendas'] : [];
            $ocupadas = [];
            foreach ($agendas as $agenda) {
                if (!is_array($agenda)) {
                    continue;
                }
                $turmaId = (int) ($agenda['turma_id'] ?? 0);
                $data = trim((string) ($agenda['data'] ?? ''));
                if ($turmaId <= 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
                    continue;
                }
                $ocupadas[$turmaId] = true;
                $this->marcarDataCalendario($porCurso, $mapaTurmas, $turmaId, $data, $titulo);
            }
            $dataPadrao = trim((string) ($sub['data'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPadrao) !== 1) {
                continue;
            }
            foreach ($turmaIds as $turmaId) {
                if (isset($ocupadas[$turmaId])) {
                    continue;
                }
                $this->marcarDataCalendario($porCurso, $mapaTurmas, (int) $turmaId, $dataPadrao, $titulo);
            }
        }
        if ($porCurso === []) {
            return ['error' => 'Informe a data do evento para colocá-lo no calendário letivo.'];
        }

        $cursos = $this->cursosDasTurmas($turmaIds);
        if ($cursos === []) {
            return ['error' => 'As turmas marcadas não estão em um curso, então não há calendário letivo para elas.'];
        }

        $linhas = [];
        foreach ($cursos as $curso) {
            $cursoId = (int) ($curso['id'] ?? 0);
            $calendarioId = $this->calendarioDoCurso($ano, $cursoId);
            if ($calendarioId <= 0) {
                $nomeCurso = trim((string) ($curso['nome'] ?? ''));
                return ['error' => 'O curso ' . ($nomeCurso !== '' ? $nomeCurso : 'selecionado') . ' não tem calendário letivo em ' . $ano . '.'];
            }
            $datas = $porCurso[$cursoId] ?? [];
            ksort($datas);
            $totalCurso = 0;
            foreach ($mapaTurmas as $turma) {
                if ((int) ($turma['curso_id'] ?? 0) === $cursoId) {
                    $totalCurso++;
                }
            }
            foreach ($datas as $data => $info) {
                $nomesTurma = array_values($info['turmas'] ?? []);
                sort($nomesTurma, SORT_NATURAL | SORT_FLAG_CASE);
                $texto = $this->descricaoCalendario($nome, array_values($info['titulos'] ?? []));
                if ($nomesTurma !== [] && count($nomesTurma) < $totalCurso) {
                    $texto = mb_substr($texto . ' (' . implode(', ', $nomesTurma) . ')', 0, 255);
                }
                $linhas[] = [
                    'calendario_id' => $calendarioId,
                    'data' => (string) $data,
                    'descricao' => $texto,
                ];
            }
        }

        return ['publicar' => true, 'linhas' => $linhas];
    }

    /**
     * @param array{publicar:bool,linhas?:list<array{calendario_id:int,data:string,descricao:string}>} $plano
     * @return array{error?:string}
     */
    private function gravarCalendario(int $eventoId, array $plano): array
    {
        if ($eventoId <= 0) {
            return [];
        }
        if (empty($plano['publicar'])) {
            $this->retirarDoCalendario($eventoId);
            return [];
        }
        if (!$this->colunaCalendarioPronta()) {
            return ['error' => 'Rode a migration 2026_10_29_evento_calendario_letivo.sql no painel Master.'];
        }

        $novos = [];
        try {
            foreach ($plano['linhas'] ?? [] as $linha) {
                $novos[] = (int) $this->db->insert(
                    'INSERT INTO calendario_letivo_eventos
                        (calendario_id, evento_avaliativo_id, data_inicio, data_fim, tipo, descricao, visivel_aluno, visivel_professor, visivel_pais)
                     VALUES (:c, :evento, :inicio, :fim, :tipo, :descricao, 1, 1, 1)',
                    [
                        'c' => (int) $linha['calendario_id'],
                        'evento' => $eventoId,
                        'inicio' => (string) $linha['data'],
                        'fim' => (string) $linha['data'],
                        'tipo' => 'avaliacao',
                        'descricao' => (string) $linha['descricao'],
                    ]
                );
            }
        } catch (Throwable $e) {
            error_log('EventoAvaliativoService::gravarCalendario: ' . $e->getMessage());
            $this->apagarCompromissos($novos);
            return ['error' => 'Não foi possível colocar o evento no calendário letivo.'];
        }

        $this->retirarDoCalendario($eventoId, $novos);
        return [];
    }

    /**
     * @param list<int> $manter
     */
    private function retirarDoCalendario(int $eventoId, array $manter = []): void
    {
        if ($eventoId <= 0 || !$this->colunaCalendarioPronta()) {
            return;
        }
        $params = ['id' => $eventoId];
        $sql = 'DELETE FROM calendario_letivo_eventos WHERE evento_avaliativo_id = :id';
        $marcas = [];
        foreach (array_values($manter) as $i => $compromissoId) {
            $compromissoId = (int) $compromissoId;
            if ($compromissoId <= 0) {
                continue;
            }
            $chave = 'mantem' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $compromissoId;
        }
        if ($marcas !== []) {
            $sql .= ' AND id NOT IN (' . implode(', ', $marcas) . ')';
        }
        $this->db->query($sql, $params);
    }

    /**
     * @param list<int> $ids
     */
    private function apagarCompromissos(array $ids): void
    {
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $this->db->query('DELETE FROM calendario_letivo_eventos WHERE id = :id', ['id' => $id]);
        }
    }

    /**
     * @param list<int> $turmaIds
     * @return list<array{id:int,nome:string}>
     */
    private function cursosDasTurmas(array $turmaIds): array
    {
        $params = [];
        $marcas = [];
        foreach (array_values($turmaIds) as $i => $turmaId) {
            $turmaId = (int) $turmaId;
            if ($turmaId <= 0) {
                continue;
            }
            $chave = 'turma' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $turmaId;
        }
        if ($marcas === []) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT DISTINCT c.id, c.nome
               FROM turmas t
               INNER JOIN serie s ON s.id = t.serie_id
               INNER JOIN curso c ON c.id = s.curso_id
              WHERE t.id IN (' . implode(', ', $marcas) . ')
              ORDER BY c.nome ASC',
            $params
        ) ?: [];

        $cursos = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $cursos[] = ['id' => $id, 'nome' => (string) ($row['nome'] ?? '')];
        }
        return $cursos;
    }

    private function calendarioDoCurso(int $ano, int $cursoId): int
    {
        if ($ano <= 0 || $cursoId <= 0) {
            return 0;
        }
        $row = $this->db->fetch(
            'SELECT c.id
               FROM calendario_letivo c
               INNER JOIN calendario_letivo_vinculos v ON v.calendario_id = c.id
              WHERE c.ano = :ano AND v.curso_id = :curso
              ORDER BY c.id ASC
              LIMIT 1',
            ['ano' => $ano, 'curso' => $cursoId]
        );
        return (int) ($row['id'] ?? 0);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,bool> $permitidas
     * @return array{itens:list<array{turma_id:int,data:string,hora_inicio:?string,hora_fim:?string}>}|array{error:string}
     */
    private function normalizarAgenda(array $row, string $titulo, array $permitidas, ?string $horaPadraoInicio, ?string $horaPadraoFim): array
    {
        $modo = (string) ($row['agenda_modo'] ?? 'todas');
        if (!in_array($modo, ['todas', 'serie', 'turma'], true)) {
            $modo = 'todas';
        }
        if ($modo === 'todas') {
            return ['itens' => []];
        }
        if (!$this->agendaPronta()) {
            return ['error' => 'Rode a migration 2026_10_29_provas_blocos_agendas.sql no painel Master para usar data e horário por série ou turma.'];
        }
        $bruto = $row['agendas'] ?? [];
        if (!is_array($bruto)) {
            return ['error' => 'Informe a data e o horário de "' . $titulo . '" para cada ' . ($modo === 'serie' ? 'série' : 'turma') . '.'];
        }
        $rotulo = $modo === 'serie' ? 'série' : 'turma';
        $vistos = [];
        $itens = [];
        foreach ($bruto as $grupo) {
            if (!is_array($grupo)) {
                continue;
            }
            $data = trim((string) ($grupo['data'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
                return ['error' => 'Informe a data de "' . $titulo . '" para cada ' . $rotulo . '.'];
            }
            $inicio = $this->normalizarHora((string) ($grupo['hora_inicio'] ?? ''));
            $fim = $this->normalizarHora((string) ($grupo['hora_fim'] ?? ''));
            if ($inicio === null) {
                $inicio = $horaPadraoInicio;
            }
            if ($fim === null) {
                $fim = $horaPadraoFim;
            }
            if ($inicio === null || $fim === null || $inicio >= $fim) {
                return ['error' => 'Informe o horário de início e de término de "' . $titulo . '" para cada ' . $rotulo . '.'];
            }
            $ids = [];
            foreach ($this->idsPositivos($grupo['turmas'] ?? []) as $turmaId) {
                if (!isset($permitidas[$turmaId])) {
                    continue;
                }
                if (isset($vistos[$turmaId])) {
                    return ['error' => 'A mesma série ou turma não pode ter duas datas em "' . $titulo . '".'];
                }
                $ids[] = $turmaId;
            }
            if ($ids === [] && $data === '' && $inicio === null && $fim === null) {
                continue;
            }
            if ($ids === []) {
                return ['error' => 'Escolha a ' . $rotulo . ' da data em "' . $titulo . '".'];
            }
            foreach ($ids as $turmaId) {
                $vistos[$turmaId] = true;
                $itens[] = [
                    'turma_id' => $turmaId,
                    'data' => $data,
                    'hora_inicio' => $inicio,
                    'hora_fim' => $fim,
                ];
            }
        }
        if ($itens === []) {
            return ['error' => 'Adicione ao menos uma ' . $rotulo . ' com data e horário em "' . $titulo . '".'];
        }
        if ($modo === 'turma') {
            foreach (array_keys($permitidas) as $turmaId) {
                if (!isset($vistos[(int) $turmaId])) {
                    return ['error' => 'Toda turma de "' . $titulo . '" precisa de data e horário.'];
                }
            }
        }
        return ['itens' => $itens];
    }

    /**
     * @param list<array{turma_id:int,data:string,hora_inicio:?string,hora_fim:?string}> $itens
     */
    private function substituirAgendas(int $blocoId, array $itens): void
    {
        if ($blocoId <= 0 || !$this->agendaPronta()) {
            return;
        }
        $propria = !$this->db->inTransaction();
        if ($propria) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->query('DELETE FROM provas_blocos_agendas WHERE bloco_id = :id', ['id' => $blocoId]);
            foreach ($itens as $item) {
                $turmaId = (int) ($item['turma_id'] ?? 0);
                $data = (string) ($item['data'] ?? '');
                if ($turmaId <= 0 || $data === '') {
                    continue;
                }
                $this->db->insert(
                    'INSERT INTO provas_blocos_agendas (bloco_id, turma_id, data_prova, hora_inicio, hora_fim)
                     VALUES (:bloco, :turma, :data, :inicio, :fim)',
                    [
                        'bloco' => $blocoId,
                        'turma' => $turmaId,
                        'data' => $data,
                        'inicio' => $item['hora_inicio'] ?? null,
                        'fim' => $item['hora_fim'] ?? null,
                    ]
                );
            }
            if ($propria) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($propria && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    private function agendaPronta(): bool
    {
        if ($this->agendaPronta !== null) {
            return $this->agendaPronta;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'provas_blocos_agendas'");
            $this->agendaPronta = is_array($row) && $row !== [];
        } catch (Throwable $e) {
            $this->agendaPronta = false;
        }
        return $this->agendaPronta;
    }

    /**
     * @param list<int> $blocoIds
     * @return array<int, list<array<string,mixed>>>
     */
    private function agendasPorBloco(array $blocoIds): array
    {
        $params = [];
        $marcas = [];
        foreach (array_values($blocoIds) as $i => $blocoId) {
            $blocoId = (int) $blocoId;
            if ($blocoId <= 0) {
                continue;
            }
            $chave = 'bloco' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $blocoId;
        }
        if ($marcas === [] || !$this->agendaPronta()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT a.bloco_id, a.turma_id, a.data_prova, a.hora_inicio, a.hora_fim, t.serie_id
               FROM provas_blocos_agendas a
               INNER JOIN turmas t ON t.id = a.turma_id
              WHERE a.bloco_id IN (' . implode(', ', $marcas) . ')
              ORDER BY t.nome ASC',
            $params
        ) ?: [];
        $porBloco = [];
        foreach ($rows as $row) {
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            if ($blocoId <= 0) {
                continue;
            }
            $porBloco[$blocoId][] = $row;
        }
        return $porBloco;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return array{modo:string,grupos:list<array{turmas:list<int>,data:string,hora_inicio:string,hora_fim:string}>}
     */
    private function apresentarAgenda(array $linhas): array
    {
        if ($linhas === []) {
            return ['modo' => 'todas', 'grupos' => []];
        }
        $porSerie = [];
        foreach ($linhas as $linha) {
            $assinatura = $this->dataIso($linha['data_prova'] ?? '')
                . '|' . $this->horaCurta($linha['hora_inicio'] ?? '')
                . '|' . $this->horaCurta($linha['hora_fim'] ?? '');
            $serieId = (int) ($linha['serie_id'] ?? 0);
            $porSerie[$serieId][$assinatura] = true;
        }
        $modo = 'serie';
        foreach ($porSerie as $chaves) {
            if (count($chaves) > 1) {
                $modo = 'turma';
            }
        }
        $grupos = [];
        foreach ($linhas as $linha) {
            $turmaId = (int) ($linha['turma_id'] ?? 0);
            if ($turmaId <= 0) {
                continue;
            }
            $chave = $modo === 'turma'
                ? 't' . $turmaId
                : 's' . (int) ($linha['serie_id'] ?? 0);
            if (!isset($grupos[$chave])) {
                $grupos[$chave] = [
                    'turmas' => [],
                    'data' => $this->dataIso($linha['data_prova'] ?? ''),
                    'hora_inicio' => $this->horaCurta($linha['hora_inicio'] ?? ''),
                    'hora_fim' => $this->horaCurta($linha['hora_fim'] ?? ''),
                ];
            }
            $grupos[$chave]['turmas'][] = $turmaId;
        }
        return ['modo' => $modo, 'grupos' => array_values($grupos)];
    }

    /**
     * @param list<int> $turmaIds
     * @return array<int, array{nome:string,curso_id:int}>
     */
    private function mapaTurmasCalendario(array $turmaIds): array
    {
        $params = [];
        $marcas = [];
        foreach (array_values($turmaIds) as $i => $turmaId) {
            $turmaId = (int) $turmaId;
            if ($turmaId <= 0) {
                continue;
            }
            $chave = 'turma' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $turmaId;
        }
        if ($marcas === []) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT t.id, t.nome, c.id AS curso_id
               FROM turmas t
               INNER JOIN serie s ON s.id = t.serie_id
               INNER JOIN curso c ON c.id = s.curso_id
              WHERE t.id IN (' . implode(', ', $marcas) . ')',
            $params
        ) ?: [];
        $mapa = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $mapa[$id] = [
                'nome' => (string) ($row['nome'] ?? ''),
                'curso_id' => (int) ($row['curso_id'] ?? 0),
            ];
        }
        return $mapa;
    }

    /**
     * @param array<int, array<string, array{titulos:array<string,string>,turmas:array<string,string>}>> $porCurso
     * @param array<int, array{nome:string,curso_id:int}> $mapaTurmas
     */
    private function marcarDataCalendario(array &$porCurso, array $mapaTurmas, int $turmaId, string $data, string $titulo): void
    {
        $turma = $mapaTurmas[$turmaId] ?? null;
        if ($turma === null) {
            return;
        }
        $cursoId = (int) ($turma['curso_id'] ?? 0);
        if ($cursoId <= 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
            return;
        }
        if (!isset($porCurso[$cursoId][$data])) {
            $porCurso[$cursoId][$data] = ['titulos' => [], 'turmas' => []];
        }
        if ($titulo !== '') {
            $porCurso[$cursoId][$data]['titulos'][$titulo] = $titulo;
        }
        $nomeTurma = trim((string) ($turma['nome'] ?? ''));
        if ($nomeTurma !== '') {
            $porCurso[$cursoId][$data]['turmas'][$nomeTurma] = $nomeTurma;
        }
    }

    /**
     * @param list<string> $titulos
     */
    private function descricaoCalendario(string $nome, array $titulos): string
    {
        $nome = trim($nome);
        $extra = [];
        foreach ($titulos as $titulo) {
            $titulo = trim($titulo);
            if ($titulo !== '' && mb_strtolower($titulo) !== mb_strtolower($nome)) {
                $extra[] = $titulo;
            }
        }
        $texto = $extra === [] ? $nome : $nome . ' — ' . implode(', ', $extra);
        if ($texto === '') {
            $texto = 'Avaliação';
        }
        return mb_substr($texto, 0, 255);
    }

    /**
     * @param list<int> $blocoIds
     */
    /**
     * Horário das provas de cada compromisso do calendário, separado por série e turma.
     *
     * @param list<array<string,mixed>> $eventos
     * @param list<int> $cursoIds
     * @param list<int> $serieIds
     * @return array<int, list<array{titulo:string,inicio:string,fim:string,materias:list<string>,series:list<array{nome:string,turmas:list<string>}>}>>
     */
    public function gradesDosCompromissos(array $eventos, array $cursoIds, array $serieIds): array
    {
        $cursoIds = $this->idsPositivos($cursoIds);
        $serieIds = $this->idsPositivos($serieIds);
        $compromissos = [];
        $origens = [];
        foreach ($eventos as $evento) {
            $id = (int) ($evento['id'] ?? 0);
            $origem = (int) ($evento['evento_avaliativo_id'] ?? 0);
            $data = substr((string) ($evento['data_inicio'] ?? ''), 0, 10);
            if ($id <= 0 || $origem <= 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
                continue;
            }
            $compromissos[$id] = ['origem' => $origem, 'data' => $data];
            $origens[$origem] = $origem;
        }
        if ($origens === []) {
            return [];
        }

        $linhas = $this->linhasDeProva(array_values($origens));
        if ($linhas === []) {
            return [];
        }
        $blocoIds = [];
        foreach ($linhas as $linha) {
            $blocoIds[(int) $linha['bloco_id']] = (int) $linha['bloco_id'];
        }
        $agendas = $this->agendaPorTurma(array_values($blocoIds));
        $materias = $this->materiasPorBloco(array_values($blocoIds));
        $saida = [];
        foreach ($compromissos as $compromissoId => $compromisso) {
            $grupos = [];
            foreach ($linhas as $linha) {
                if ((int) $linha['evento_avaliativo_id'] !== $compromisso['origem']) {
                    continue;
                }
                $cursoId = (int) ($linha['curso_id'] ?? 0);
                $serieId = (int) ($linha['serie_id'] ?? 0);
                if (!$this->turmaCabeNoCalendario($cursoId, $serieId, $cursoIds, $serieIds)) {
                    continue;
                }
                $blocoId = (int) $linha['bloco_id'];
                $turmaId = (int) $linha['turma_id'];
                $agenda = $agendas[$blocoId][$turmaId] ?? null;
                $data = $agenda['data'] ?? (string) $linha['data_prova'];
                if ($data !== $compromisso['data']) {
                    continue;
                }
                $inicio = $agenda['inicio'] ?? $this->horaCurta($linha['hora_inicio'] ?? '');
                $fim = $agenda['fim'] ?? $this->horaCurta($linha['hora_fim'] ?? '');
                $chave = $blocoId . '|' . $inicio . '|' . $fim;
                if (!isset($grupos[$chave])) {
                    $grupos[$chave] = [
                        'titulo' => trim((string) ($linha['titulo'] ?? '')) ?: 'Avaliação',
                        'inicio' => $inicio,
                        'fim' => $fim,
                        'ordem' => $inicio . '|' . $fim,
                        'materias' => $materias[$blocoId] ?? [],
                        'series' => [],
                    ];
                }
                $serieNome = trim((string) ($linha['serie_nome'] ?? ''));
                if ($serieNome === '') {
                    $serieNome = 'Sem série';
                }
                $serieChave = $serieId . '|' . $serieNome;
                if (!isset($grupos[$chave]['series'][$serieChave])) {
                    $grupos[$chave]['series'][$serieChave] = [
                        'nome' => $serieNome,
                        'ordem' => (int) ($linha['serie_ordem'] ?? 0),
                        'turmas' => [],
                    ];
                }
                $turmaNome = trim((string) ($linha['turma_nome'] ?? ''));
                if ($turmaNome !== '') {
                    $grupos[$chave]['series'][$serieChave]['turmas'][$turmaNome] = $turmaNome;
                }
            }
            $provas = [];
            foreach ($grupos as $grupo) {
                $series = array_values($grupo['series']);
                usort($series, static function (array $a, array $b): int {
                    $ordem = ((int) $a['ordem']) <=> ((int) $b['ordem']);
                    return $ordem !== 0 ? $ordem : strnatcasecmp((string) $a['nome'], (string) $b['nome']);
                });
                $seriesLimpas = [];
                foreach ($series as $serie) {
                    $turmas = array_values($serie['turmas']);
                    usort($turmas, 'strnatcasecmp');
                    $seriesLimpas[] = ['nome' => (string) $serie['nome'], 'turmas' => $turmas];
                }
                $provas[] = [
                    'titulo' => (string) $grupo['titulo'],
                    'inicio' => (string) $grupo['inicio'],
                    'fim' => (string) $grupo['fim'],
                    'ordem' => (string) $grupo['ordem'],
                    'materias' => $grupo['materias'],
                    'series' => $seriesLimpas,
                ];
            }
            usort($provas, static function (array $a, array $b): int {
                $ordem = strcmp((string) $a['ordem'], (string) $b['ordem']);
                return $ordem !== 0 ? $ordem : strnatcasecmp((string) $a['titulo'], (string) $b['titulo']);
            });
            foreach ($provas as &$prova) {
                unset($prova['ordem']);
            }
            unset($prova);
            if ($provas !== []) {
                $saida[$compromissoId] = $provas;
            }
        }

        return $saida;
    }

    /**
     * @param list<int> $cursoIds
     * @param list<int> $serieIds
     */
    private function turmaCabeNoCalendario(int $cursoId, int $serieId, array $cursoIds, array $serieIds): bool
    {
        if ($cursoIds === [] && $serieIds === []) {
            return true;
        }
        if ($cursoId > 0 && in_array($cursoId, $cursoIds, true)) {
            return true;
        }
        return $serieId > 0 && in_array($serieId, $serieIds, true);
    }

    /**
     * @param list<int> $origens
     * @return list<array<string,mixed>>
     */
    private function linhasDeProva(array $origens): array
    {
        [$marcas, $params] = $this->marcasNomeadas('origem', $origens);
        if ($marcas === []) {
            return [];
        }
        try {
            return $this->db->fetchAll(
                'SELECT pb.id AS bloco_id, pb.evento_avaliativo_id, pb.titulo, pb.data_prova, pb.hora_inicio, pb.hora_fim,
                        t.id AS turma_id, t.nome AS turma_nome, s.id AS serie_id, s.nome AS serie_nome, s.ordem AS serie_ordem,
                        c.id AS curso_id
                   FROM provas_blocos pb
                   INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb.id
                   INNER JOIN turmas t ON t.id = pbt.turma_id
                   LEFT JOIN serie s ON s.id = t.serie_id
                   LEFT JOIN curso c ON c.id = s.curso_id
                  WHERE pb.deleted_at IS NULL
                    AND pb.evento_avaliativo_id IN (' . implode(', ', $marcas) . ')',
                $params
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<int> $blocoIds
     * @return array<int, array<int, array{data:string,inicio:string,fim:string}>>
     */
    private function agendaPorTurma(array $blocoIds): array
    {
        if (!$this->agendaPronta()) {
            return [];
        }
        [$marcas, $params] = $this->marcasNomeadas('agenda', $blocoIds);
        if ($marcas === []) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT bloco_id, turma_id, data_prova, hora_inicio, hora_fim
                   FROM provas_blocos_agendas
                  WHERE bloco_id IN (' . implode(', ', $marcas) . ')',
                $params
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $mapa = [];
        foreach ($rows as $row) {
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            $turmaId = (int) ($row['turma_id'] ?? 0);
            $data = substr((string) ($row['data_prova'] ?? ''), 0, 10);
            if ($blocoId <= 0 || $turmaId <= 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1) {
                continue;
            }
            $mapa[$blocoId][$turmaId] = [
                'data' => $data,
                'inicio' => $this->horaCurta($row['hora_inicio'] ?? ''),
                'fim' => $this->horaCurta($row['hora_fim'] ?? ''),
            ];
        }

        return $mapa;
    }

    /**
     * @param list<int> $blocoIds
     * @return array<int, list<string>>
     */
    private function materiasPorBloco(array $blocoIds): array
    {
        [$marcas, $params] = $this->marcasNomeadas('materia', $blocoIds);
        if ($marcas === []) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT pbp.bloco_id, m.nome
                   FROM provas_blocos_professores pbp
                   INNER JOIN materias m ON m.id = pbp.materia_id
                  WHERE pbp.bloco_id IN (' . implode(', ', $marcas) . ')
                  ORDER BY m.nome ASC',
                $params
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $mapa = [];
        foreach ($rows as $row) {
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($blocoId <= 0 || $nome === '') {
                continue;
            }
            $mapa[$blocoId][$nome] = $nome;
        }
        foreach ($mapa as $blocoId => $nomes) {
            $lista = array_values($nomes);
            usort($lista, 'strnatcasecmp');
            $mapa[$blocoId] = $lista;
        }

        return $mapa;
    }

    /**
     * @param list<int> $ids
     * @return array{0:list<string>,1:array<string,int>}
     */
    private function marcasNomeadas(string $prefixo, array $ids): array
    {
        $marcas = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $chave = $prefixo . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $id;
        }

        return [$marcas, $params];
    }

    private function desfazerBlocos(array $blocoIds, int $usuarioId): void
    {
        $blocos = new ExamBlock();
        foreach ($blocoIds as $blocoId) {
            if ($blocoId <= 0) {
                continue;
            }
            try {
                $blocos->delete($blocoId, $usuarioId);
            } catch (Throwable $e) {
                error_log('EventoAvaliativoService::desfazerBlocos: ' . $e->getMessage());
            }
        }
    }
}
