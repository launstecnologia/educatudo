<?php

namespace App\Modulos\Redacoes\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Dominio\Pessoas\Services\AlunoLimiteDiarioService;

/**
 * Fluxos legados de redação com IA/créditos (tabela redacoes).
 * Preserva prompts, modelos, chaves TudiCoins e limites diários existentes.
 * OpenAI/créditos injetáveis para testes (zero rede / zero cobrança real).
 */
class RedacaoAlunoIaService
{
    public const CHAVE_CREDITO_TEMA = 'redacao_gerar_tema_aluno';
    public const ACAO_GERAR_TEMA = 'gerar_tema_redacao';
    public const ACAO_CORRIGIR = 'corrigir_redacao';

    private $db;
    /** @var object|null generateText/analyzeImage/transcreverComGoogleVision */
    private $openai;
    /** @var object|null consumir/estornarPorReferencia */
    private $creditos;
    private AlunoLimiteDiarioService $limites;

    /**
     * @param AlunoLimiteDiarioService|callable|null $limitesOuObterLimite Service ou callable(tipo):int legado dos testes
     */
    public function __construct($db = null, $openai = null, $creditos = null, $limitesOuObterLimite = null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->openai = $openai;
        $this->creditos = $creditos;
        if ($limitesOuObterLimite instanceof AlunoLimiteDiarioService) {
            $this->limites = $limitesOuObterLimite;
        } elseif (is_callable($limitesOuObterLimite)) {
            $fn = $limitesOuObterLimite;
            $this->limites = new AlunoLimiteDiarioService(
                $this->db,
                null,
                static function (string $key, string $default) use ($fn): string {
                    $tipo = str_starts_with($key, 'limit_') ? substr($key, 6) : $key;
                    return (string) $fn($tipo);
                }
            );
        } else {
            $this->limites = new AlunoLimiteDiarioService($this->db);
        }
    }

    /**
     * @return array{pode:bool,mensagem?:string,restantes?:int}
     */
    public function podeExecutarAcao(int $alunoId, string $tipo): array
    {
        return $this->limites->podeExecutarAcao($alunoId, $tipo);
    }

    public function contarUsoDiario(int $alunoId, string $tipo): int
    {
        return $this->limites->contarUsoDiario($alunoId, $tipo);
    }

    public function registrarAcaoDiaria(int $alunoId, string $acao): void
    {
        $this->limites->registrarAcao($alunoId, $acao);
    }

    /**
     * createEssay — sem TudiCoins; correção automática best-effort.
     *
     * @param array{tema_id?:mixed,titulo:string,conteudo:string,tipo?:string,tema_gerado?:?string} $dados
     * @return array{success:bool,redacao_id:int}
     */
    public function criarRedacao(int $alunoId, array $dados): array
    {
        if ($alunoId <= 0) {
            throw new \InvalidArgumentException('Aluno inválido');
        }
        $titulo = (string) ($dados['titulo'] ?? '');
        $conteudo = (string) ($dados['conteudo'] ?? '');
        $tipo = (string) ($dados['tipo'] ?? 'padrao');
        $temaId = $dados['tema_id'] ?? null;
        $temaGerado = $dados['tema_gerado'] ?? null;

        if ($titulo === '' || $conteudo === '') {
            throw new \InvalidArgumentException('Título e conteúdo são obrigatórios');
        }

        $insertData = [
            'aluno_id' => $alunoId,
            'titulo' => $titulo,
            'conteudo' => $conteudo,
            'tipo' => $tipo,
        ];
        $sql = "INSERT INTO redacoes (aluno_id, tema_id, titulo, conteudo, tipo, created_at) VALUES (:aluno_id, :tema_id, :titulo, :conteudo, :tipo, NOW())";

        if ($temaId) {
            $insertData['tema_id'] = $temaId;
        } else {
            $sql = str_replace(':tema_id', 'NULL', $sql);
            unset($insertData['tema_id']);
        }

        if ($tipo === 'ia_gerado' && $temaGerado) {
            $temaData = json_decode((string) $temaGerado, true);
            if ($temaData) {
                $insertData['tema_gerado'] = $temaGerado;
                $sql = "INSERT INTO redacoes (aluno_id, tema_id, titulo, conteudo, tipo, tema_gerado, created_at) VALUES (:aluno_id, :tema_id, :titulo, :conteudo, :tipo, :tema_gerado, NOW())";
                if (!$temaId) {
                    $sql = str_replace(':tema_id', 'NULL', $sql);
                    unset($insertData['tema_id']);
                }
            }
        }

        $redacaoId = (int) $this->db->insert($sql, $insertData);
        $this->processarCorrecaoAutomatica($redacaoId, $alunoId);
        return ['success' => true, 'redacao_id' => $redacaoId];
    }

    /**
     * gerarTemaIA — debita TudiCoins antes da chamada; estorna só em exception do fluxo.
     * JSON inválido da IA → fallback estruturado (sem estorno) — comportamento legado.
     *
     * @return array{ok:bool,tema?:array,erro?:string,codigo?:string}
     */
    public function gerarTema(int $alunoId, string $themeRequest, ?string $refCredito = null): array
    {
        if ($alunoId <= 0) {
            return ['ok' => false, 'erro' => 'Aluno inválido', 'codigo' => 'aluno'];
        }
        $themeRequest = trim($themeRequest);
        if ($themeRequest === '') {
            return ['ok' => false, 'erro' => 'Descrição do tema é obrigatória', 'codigo' => 'validacao'];
        }

        $verificacao = $this->podeExecutarAcao($alunoId, self::ACAO_GERAR_TEMA);
        if (empty($verificacao['pode'])) {
            return ['ok' => false, 'erro' => (string) ($verificacao['mensagem'] ?? 'Limite diário atingido'), 'codigo' => 'limite'];
        }

        $creditos = $this->creditosService();
        $ref = $refCredito ?: ('tema_redacao_' . $alunoId . '_' . str_replace('.', '', uniqid('', true)));
        $debited = false;
        try {
            $creditos->consumir('aluno', $alunoId, self::CHAVE_CREDITO_TEMA, $ref);
            $debited = true;
        } catch (\Throwable $e) {
            if ($this->mensagemCreditoInsuficiente($e->getMessage())) {
                return ['ok' => false, 'erro' => $e->getMessage(), 'codigo' => 'creditos'];
            }
            throw $e;
        }

        try {
            $promptBase = $this->obterPromptGerarTema($themeRequest);
            $response = $this->openaiService()->generateText($promptBase);
            $temaData = json_decode((string) $response, true);
            if (!$temaData || !is_array($temaData)) {
                $temaData = [
                    'titulo' => "Redação sobre {$themeRequest}",
                    'descricao' => (string) $response,
                    'proposta_intervencao' => 'Desenvolva uma proposta de intervenção social que respeite os direitos humanos.',
                    'contexto' => 'Considere a realidade brasileira atual ao desenvolver sua argumentação.',
                ];
            }
            $this->registrarAcaoDiaria($alunoId, self::ACAO_GERAR_TEMA);
            return ['ok' => true, 'tema' => $temaData, 'ref_credito' => $ref];
        } catch (\Throwable $e) {
            if ($debited) {
                try {
                    $creditos->estornarPorReferencia(self::CHAVE_CREDITO_TEMA, $ref);
                } catch (\Throwable $e2) {
                    error_log('Estorno crédito gerar tema redação: ' . $e2->getMessage());
                }
            }
            return ['ok' => false, 'erro' => 'Erro ao gerar tema: ' . $e->getMessage(), 'codigo' => 'ia'];
        }
    }

    /**
     * corrigirRedacao — limite diário; sem TudiCoins no fluxo legado atual.
     * Ownership obrigatório em fetch e UPDATE final.
     *
     * @param array{redacao_id?:int|string|null,conteudo:string,titulo?:string,tema?:string} $dados
     * @return array{ok:bool,redacao_id?:int,erro?:string,codigo?:string}
     */
    public function corrigir(int $alunoId, array $dados): array
    {
        if ($alunoId <= 0) {
            return ['ok' => false, 'erro' => 'Aluno inválido', 'codigo' => 'aluno'];
        }
        $verificacao = $this->podeExecutarAcao($alunoId, self::ACAO_CORRIGIR);
        if (empty($verificacao['pode'])) {
            return ['ok' => false, 'erro' => (string) ($verificacao['mensagem'] ?? 'Limite diário atingido'), 'codigo' => 'limite'];
        }

        $conteudo = (string) ($dados['conteudo'] ?? '');
        $titulo = (string) ($dados['titulo'] ?? 'Redação');
        $tema = (string) ($dados['tema'] ?? 'Redação');
        $redacaoId = isset($dados['redacao_id']) && $dados['redacao_id'] !== '' && $dados['redacao_id'] !== null
            ? (int) $dados['redacao_id']
            : 0;

        if ($conteudo === '') {
            return ['ok' => false, 'erro' => 'Conteúdo da redação é obrigatório', 'codigo' => 'validacao'];
        }

        try {
            $redacaoId = $this->persistirAntesDaCorrecao($alunoId, $redacaoId, $titulo, $conteudo, $tema);
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage(), 'codigo' => 'persistencia'];
        }

        $redacao = $this->db->fetch(
            "SELECT r.*, a.nome as aluno_nome
             FROM redacoes r
             LEFT JOIN alunos a ON r.aluno_id = a.id
             WHERE r.id = :redacao_id AND r.aluno_id = :aluno_id",
            ['redacao_id' => $redacaoId, 'aluno_id' => $alunoId]
        );
        if (!$redacao) {
            return ['ok' => false, 'erro' => 'Redação não encontrada', 'codigo' => 'ownership'];
        }

        try {
            $promptBase = $this->obterPromptCorrigir(
                (string) ($redacao['titulo'] ?? ''),
                (string) ($redacao['conteudo'] ?? '')
            );
            $response = $this->openaiService()->generateText($promptBase, [
                'max_tokens' => 4000,
                'temperature' => 0.4,
            ]);
            $correcaoData = json_decode((string) $response, true);
            if (!$correcaoData || !is_array($correcaoData)) {
                throw new \RuntimeException('Erro ao processar correção da IA');
            }
        } catch (\Throwable $e) {
            if (class_exists(Logger::class)) {
                Logger::openaiError('Erro ao corrigir redação: ' . $e->getMessage(), [
                    'exception' => $e,
                    'redacao_id' => $redacaoId,
                    'aluno_id' => $alunoId,
                ]);
            }
            return [
                'ok' => false,
                'erro' => 'Não foi possível processar a correção no momento. Por favor, tente novamente mais tarde. Se o problema persistir, entre em contato com o suporte.',
                'codigo' => 'ia',
                'redacao_id' => $redacaoId,
            ];
        }

        $this->db->update(
            "UPDATE redacoes SET
                competencia_1 = :comp1_nota,
                competencia_2 = :comp2_nota,
                competencia_3 = :comp3_nota,
                competencia_4 = :comp4_nota,
                competencia_5 = :comp5_nota,
                nota_final = :nota_final,
                feedback_ia = :feedback,
                corrigida_em = NOW()
             WHERE id = :redacao_id AND aluno_id = :aluno_id",
            [
                'comp1_nota' => $correcaoData['competencia_1']['nota'] ?? 0,
                'comp2_nota' => $correcaoData['competencia_2']['nota'] ?? 0,
                'comp3_nota' => $correcaoData['competencia_3']['nota'] ?? 0,
                'comp4_nota' => $correcaoData['competencia_4']['nota'] ?? 0,
                'comp5_nota' => $correcaoData['competencia_5']['nota'] ?? 0,
                'nota_final' => $correcaoData['nota_final'] ?? 0,
                'feedback' => json_encode($correcaoData),
                'redacao_id' => $redacaoId,
                'aluno_id' => $alunoId,
            ]
        );

        // Comportamento legado: NÃO registra alunos_acoes_diarias em corrigirRedacao.
        return ['ok' => true, 'redacao_id' => $redacaoId];
    }

    /**
     * Transcrição — sem TudiCoins no fluxo legado atual.
     *
     * @param array{tmp_name:string,name:string,type:string,size:int,error:int} $imagem
     * @return array{ok:bool,texto_transcrito?:string,warning?:string,erro?:string,tipo_erro?:string,is_quota_error?:bool}
     */
    public function transcreverImagem(int $alunoId, array $imagem, $mediaStorage = null): array
    {
        if ($alunoId <= 0) {
            return ['ok' => false, 'erro' => 'Aluno inválido', 'tipo_erro' => 'aluno'];
        }
        if (($imagem['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'erro' => 'Erro no upload da imagem', 'tipo_erro' => 'upload'];
        }
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array((string) ($imagem['type'] ?? ''), $allowedTypes, true)) {
            return ['ok' => false, 'erro' => 'Tipo de arquivo não permitido. Use JPG, JPEG, PNG ou WebP.', 'tipo_erro' => 'formato_invalido'];
        }
        if ((int) ($imagem['size'] ?? 0) > 10 * 1024 * 1024) {
            return ['ok' => false, 'erro' => 'Arquivo muito grande. Máximo 10MB.', 'tipo_erro' => 'imagem_grande'];
        }

        $tmp = (string) ($imagem['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            return ['ok' => false, 'erro' => 'Arquivo temporário inválido', 'tipo_erro' => 'upload'];
        }

        if ($mediaStorage !== null) {
            $fileName = 'redacao_' . $alunoId . '_' . time() . '_' . uniqid() . '.' . pathinfo((string) $imagem['name'], PATHINFO_EXTENSION);
            if (class_exists(\App\Infra\Services\MediaStorageService::class)) {
                $key = \App\Infra\Services\MediaStorageService::userKey('student', $alunoId, $fileName);
            } elseif (class_exists(\App\Services\MediaStorageService::class)) {
                $key = \App\Services\MediaStorageService::userKey('student', $alunoId, $fileName);
            } else {
                $key = 'student/' . $alunoId . '/' . $fileName;
            }
            if (!$mediaStorage->put('redacoes', $key, $tmp, (string) $imagem['type'])) {
                return ['ok' => false, 'erro' => 'Erro ao salvar imagem', 'tipo_erro' => 'storage'];
            }
        }

        $fileContent = file_get_contents($tmp);
        if ($fileContent === false) {
            return ['ok' => false, 'erro' => 'Falha ao ler imagem', 'tipo_erro' => 'upload'];
        }
        $imageData = base64_encode($fileContent);
        $prompt = $this->obterPromptTranscrever();

        try {
            set_time_limit(300);
            @ini_set('max_execution_time', '300');
            $texto = $this->openaiService()->analyzeImage($imageData, $prompt);
            return ['ok' => true, 'texto_transcrito' => (string) $texto];
        } catch (\Throwable $e) {
            return $this->tratarFalhaTranscricao($e, $imageData, $alunoId);
        }
    }

    /**
     * Correção automática pós-createEssay (best-effort; falha não aborta criação).
     */
    public function processarCorrecaoAutomatica(int $redacaoId, ?int $alunoId = null): void
    {
        try {
            $params = ['redacao_id' => $redacaoId];
            $sql = 'SELECT * FROM redacoes WHERE id = :redacao_id';
            if ($alunoId !== null && $alunoId > 0) {
                $sql .= ' AND aluno_id = :aluno_id';
                $params['aluno_id'] = $alunoId;
            }
            $redacao = $this->db->fetch($sql, $params);
            if (!$redacao) {
                return;
            }
            $promptBase = $this->obterPromptCorrigir(
                (string) ($redacao['titulo'] ?? ''),
                (string) ($redacao['conteudo'] ?? '')
            );
            $response = $this->openaiService()->generateText($promptBase, ['temperature' => 0.4]);
            $correcaoData = json_decode((string) $response, true);
            if (!$correcaoData || !is_array($correcaoData)) {
                $correcaoData = [
                    'competencia_1' => ['nota' => 150, 'explicacao' => 'Bom domínio da norma padrão'],
                    'competencia_2' => ['nota' => 150, 'explicacao' => 'Adequada compreensão do tema'],
                    'competencia_3' => ['nota' => 150, 'explicacao' => 'Argumentos bem selecionados'],
                    'competencia_4' => ['nota' => 150, 'explicacao' => 'Boa coesão e coerência'],
                    'competencia_5' => ['nota' => 150, 'explicacao' => 'Proposta de intervenção adequada'],
                    'nota_final' => 750,
                    'comentarios_gerais' => 'Sua redação apresenta boa estrutura e argumentação. Continue praticando!',
                    'sugestoes_melhoria' => 'Continue desenvolvendo suas ideias e praticando a escrita.',
                ];
            }
            $updateParams = [
                'comp1_nota' => $correcaoData['competencia_1']['nota'] ?? 0,
                'comp1_exp' => $correcaoData['competencia_1']['explicacao'] ?? '',
                'comp2_nota' => $correcaoData['competencia_2']['nota'] ?? 0,
                'comp2_exp' => $correcaoData['competencia_2']['explicacao'] ?? '',
                'comp3_nota' => $correcaoData['competencia_3']['nota'] ?? 0,
                'comp3_exp' => $correcaoData['competencia_3']['explicacao'] ?? '',
                'comp4_nota' => $correcaoData['competencia_4']['nota'] ?? 0,
                'comp4_exp' => $correcaoData['competencia_4']['explicacao'] ?? '',
                'comp5_nota' => $correcaoData['competencia_5']['nota'] ?? 0,
                'comp5_exp' => $correcaoData['competencia_5']['explicacao'] ?? '',
                'nota_final' => $correcaoData['nota_final'] ?? 0,
                'comentarios' => $correcaoData['comentarios_gerais'] ?? '',
                'sugestoes' => $correcaoData['sugestoes_melhoria'] ?? '',
                'redacao_id' => $redacaoId,
            ];
            $whereAluno = '';
            if ($alunoId !== null && $alunoId > 0) {
                $whereAluno = ' AND aluno_id = :aluno_id';
                $updateParams['aluno_id'] = $alunoId;
            }
            $this->db->update(
                "UPDATE redacoes SET
                    competencia_1_nota = :comp1_nota,
                    competencia_1_explicacao = :comp1_exp,
                    competencia_2_nota = :comp2_nota,
                    competencia_2_explicacao = :comp2_exp,
                    competencia_3_nota = :comp3_nota,
                    competencia_3_explicacao = :comp3_exp,
                    competencia_4_nota = :comp4_nota,
                    competencia_4_explicacao = :comp4_exp,
                    competencia_5_nota = :comp5_nota,
                    competencia_5_explicacao = :comp5_exp,
                    nota_final = :nota_final,
                    comentarios_gerais = :comentarios,
                    sugestoes_melhoria = :sugestoes,
                    corrigida_em = NOW()
                 WHERE id = :redacao_id{$whereAluno}",
                $updateParams
            );
        } catch (\Throwable $e) {
            error_log("Erro na correção automática da redação {$redacaoId}: " . $e->getMessage());
        }
    }

    public function obterPromptGerarTema(string $themeRequest): string
    {
        $promptConfig = $this->db->fetch(
            'SELECT config_value FROM config_layout WHERE config_key = ?',
            ['prompt_gerar_tema_redacao']
        );
        if (!$promptConfig || empty($promptConfig['config_value'])) {
            return "Você é um especialista em educação brasileira e criação de temas de redação para o ENEM. 
            
            O aluno solicitou um tema sobre: '{$themeRequest}'
            
            Crie um tema de redação completo seguindo o formato do ENEM com:
            1. Um título claro e específico
            2. Uma descrição detalhada do tema
            3. Uma proposta de intervenção sugerida
            4. Contexto atual relevante
            
            Responda em formato JSON com as seguintes chaves:
            - titulo: título do tema
            - descricao: descrição detalhada do tema
            - proposta_intervencao: sugestão de proposta de intervenção
            - contexto: contexto atual do tema
            
            Seja específico e atual, considerando a realidade brasileira.";
        }
        return str_replace('{themeRequest}', $themeRequest, (string) $promptConfig['config_value']);
    }

    public function obterPromptCorrigir(string $titulo, string $conteudo): string
    {
        $promptConfig = $this->db->fetch(
            'SELECT config_value FROM config_layout WHERE config_key = ?',
            ['prompt_corrigir_redacao']
        );
        if (!$promptConfig || empty($promptConfig['config_value'])) {
            return "Você é um corretor especializado em redações do ENEM. 

            Corrija a redação abaixo nos critérios e parâmetros oficiais do ENEM:

            COMPETÊNCIA I – Domínio da norma padrão da Língua Portuguesa
            COMPETÊNCIA II – Compreensão da proposta e desenvolvimento do tema  
            COMPETÊNCIA III – Seleção e organização de argumentos
            COMPETÊNCIA IV – Coesão e coerência
            COMPETÊNCIA V – Proposta de intervenção

            REDAÇÃO:
            Título: {$titulo}
            Conteúdo: {$conteudo}

            Responda em formato JSON com as seguintes chaves:
            - competencia_1: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_2: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_3: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_4: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - competencia_5: {nota: 0-200, explicacao: \"explicação detalhada\"}
            - nota_final: soma das 5 competências
            - comentarios_gerais: \"comentários gerais sobre a redação\"
            - sugestoes_melhoria: \"sugestões específicas para melhoria\"

            Seja detalhado e construtivo nas explicações.";
        }
        $promptBase = (string) $promptConfig['config_value'];
        $promptBase = str_replace('{titulo}', $titulo, $promptBase);
        return str_replace('{conteudo}', $conteudo, $promptBase);
    }

    public function obterPromptTranscrever(): string
    {
        $promptConfig = $this->db->fetch(
            'SELECT config_value FROM config_layout WHERE config_key = ?',
            ['prompt_transcrever_imagem']
        );
        if (!$promptConfig || empty($promptConfig['config_value'])) {
            return 'Transcreva todo o texto presente nesta imagem. Retorne apenas o texto transcrito, sem comentários adicionais.';
        }
        return (string) $promptConfig['config_value'];
    }

    private function persistirAntesDaCorrecao(int $alunoId, int $redacaoId, string $titulo, string $conteudo, string $tema): int
    {
        $hasEhRascunho = false;
        try {
            $columnCheck = $this->db->fetch("SHOW COLUMNS FROM redacoes LIKE 'eh_rascunho'");
            $hasEhRascunho = !empty($columnCheck);
        } catch (\Throwable $e) {
            $hasEhRascunho = false;
        }

        if ($redacaoId > 0) {
            $rascunho = null;
            if ($hasEhRascunho) {
                $rascunho = $this->db->fetch(
                    'SELECT eh_rascunho, tema_texto FROM redacoes WHERE id = :id AND aluno_id = :aluno_id',
                    ['id' => $redacaoId, 'aluno_id' => $alunoId]
                );
            } else {
                $rascunho = $this->db->fetch(
                    'SELECT id FROM redacoes WHERE id = :id AND aluno_id = :aluno_id',
                    ['id' => $redacaoId, 'aluno_id' => $alunoId]
                );
            }
            if (!$rascunho) {
                throw new \RuntimeException('Redação não encontrada');
            }

            $updateParams = [
                'id' => $redacaoId,
                'aluno_id' => $alunoId,
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'tema' => $tema,
                'texto' => $conteudo,
            ];
            if ($hasEhRascunho && isset($rascunho['eh_rascunho']) && (int) $rascunho['eh_rascunho'] === 1) {
                if (!empty($rascunho['tema_texto'])) {
                    $updateParams['tema_texto'] = $rascunho['tema_texto'];
                    $this->db->update(
                        "UPDATE redacoes
                         SET titulo = :titulo, conteudo = :conteudo, tema = :tema, texto = :texto,
                             tema_texto = :tema_texto, eh_rascunho = 0, updated_at = NOW()
                         WHERE id = :id AND aluno_id = :aluno_id",
                        $updateParams
                    );
                } else {
                    $this->db->update(
                        "UPDATE redacoes
                         SET titulo = :titulo, conteudo = :conteudo, tema = :tema, texto = :texto,
                             eh_rascunho = 0, updated_at = NOW()
                         WHERE id = :id AND aluno_id = :aluno_id",
                        $updateParams
                    );
                }
            } else {
                $this->db->update(
                    "UPDATE redacoes
                     SET titulo = :titulo, conteudo = :conteudo, tema = :tema, texto = :texto, updated_at = NOW()
                     WHERE id = :id AND aluno_id = :aluno_id",
                    $updateParams
                );
            }
            return $redacaoId;
        }

        if ($hasEhRascunho) {
            return (int) $this->db->insert(
                "INSERT INTO redacoes (aluno_id, tema_id, titulo, conteudo, tema, texto, eh_rascunho, created_at)
                 VALUES (:aluno_id, NULL, :titulo, :conteudo, :tema, :texto, 0, NOW())",
                [
                    'aluno_id' => $alunoId,
                    'titulo' => $titulo,
                    'conteudo' => $conteudo,
                    'tema' => $tema,
                    'texto' => $conteudo,
                ]
            );
        }
        return (int) $this->db->insert(
            "INSERT INTO redacoes (aluno_id, tema_id, titulo, conteudo, tema, texto, created_at)
             VALUES (:aluno_id, NULL, :titulo, :conteudo, :tema, :texto, NOW())",
            [
                'aluno_id' => $alunoId,
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'tema' => $tema,
                'texto' => $conteudo,
            ]
        );
    }

    /**
     * @return array{ok:bool,texto_transcrito?:string,warning?:string,erro?:string,tipo_erro?:string,is_quota_error?:bool}
     */
    private function tratarFalhaTranscricao(\Throwable $e, string $imageData, int $alunoId): array
    {
        $mensagemErro = $e->getMessage();
        $isQuotaError = false;
        $tipoErro = 'geral';
        $mensagemLower = strtolower($mensagemErro);

        if (preg_match('/\[tipo:\s*([^\]]+)\]/i', $mensagemErro, $matches)) {
            $tipoErro = trim($matches[1]);
            $mensagemErro = preg_replace('/\s*\[tipo:\s*[^\]]+\]/i', '', $mensagemErro) ?? $mensagemErro;
        }

        if (
            str_contains($mensagemLower, 'quota')
            || str_contains($mensagemLower, 'insufficient_quota')
            || str_contains($mensagemLower, 'rate limit')
            || str_contains($mensagemLower, 'billing')
            || str_contains($mensagemLower, 'payment')
            || str_contains($mensagemLower, 'credit')
            || str_contains($mensagemLower, 'limit exceeded')
            || str_contains($mensagemLower, 'resource exhausted')
            || $tipoErro === 'quota'
        ) {
            $isQuotaError = true;
            $tipoErro = 'quota';
            $mensagemErro = 'A cota de uso da API foi excedida ou o limite de pagamento foi atingido. Por favor, verifique sua conta ou tente novamente mais tarde.';
        } elseif (str_contains($mensagemLower, 'timeout') || str_contains($mensagemLower, 'timed out') || $tipoErro === 'timeout') {
            $tipoErro = 'timeout';
            $mensagemErro = 'O tempo de processamento expirou. Tente novamente com uma imagem menor ou mais clara.';
        } elseif (
            str_contains($mensagemLower, 'invalid')
            || str_contains($mensagemLower, 'formato')
            || $tipoErro === 'imagem_invalida'
            || $tipoErro === 'formato_invalido'
        ) {
            $tipoErro = 'imagem_invalida';
            $mensagemErro = 'A imagem enviada é inválida ou não pôde ser processada. Verifique o formato e tente novamente.';
        }

        if (class_exists(Logger::class)) {
            Logger::openaiError('Erro na transcrição de imagem: ' . $e->getMessage(), [
                'exception' => $e,
                'aluno_id' => $alunoId,
                'image_size' => strlen($imageData),
                'tipo_erro' => $tipoErro,
                'is_quota_error' => $isQuotaError,
            ]);
        }

        if (!$isQuotaError) {
            try {
                $textoBruto = $this->openaiService()->transcreverComGoogleVision($imageData);
                if (!empty($textoBruto)) {
                    return [
                        'ok' => true,
                        'texto_transcrito' => (string) $textoBruto,
                        'warning' => 'Texto transcrito sem formatação automática. Alguns serviços de IA podem estar indisponíveis no momento.',
                    ];
                }
            } catch (\Throwable $e2) {
                $m2 = strtolower($e2->getMessage());
                if (str_contains($m2, 'quota') || str_contains($m2, 'insufficient_quota') || str_contains($m2, 'billing')) {
                    $isQuotaError = true;
                    $tipoErro = 'quota';
                    $mensagemErro = 'A cota de uso da API foi excedida ou o limite de pagamento foi atingido. Por favor, verifique sua conta ou tente novamente mais tarde.';
                }
            }
        }

        return [
            'ok' => false,
            'erro' => $mensagemErro,
            'tipo_erro' => $tipoErro,
            'is_quota_error' => $isQuotaError,
        ];
    }

    private function mensagemCreditoInsuficiente(string $msg): bool
    {
        return stripos($msg, 'TudiCoins') !== false
            || stripos($msg, 'insuficientes') !== false
            || stripos($msg, 'Créditos') !== false;
    }

    private function openaiService()
    {
        if ($this->openai !== null) {
            return $this->openai;
        }
        $this->openai = new \App\Services\OpenAIService();
        return $this->openai;
    }

    private function creditosService()
    {
        if ($this->creditos !== null) {
            return $this->creditos;
        }
        $this->creditos = new \App\Services\CreditosService();
        return $this->creditos;
    }
}
