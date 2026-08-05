<?php
/**
 * EducaTudo - Serviço Tudinha
 * Gerencia conversas educacionais com OpenAI
 */

namespace App\Services;

use Exception;

require_once __DIR__ . '/../AI/Suporte/CarregadorPromptTudinha.php';

class TudinhaService
{
    private $openAIService;
    private $db;
    private $systemPrompt;
    
    public function __construct()
    {
        $this->openAIService = new OpenAIService();
        
        // Garantir que Database está carregado
        if (!class_exists('Database', false)) {
            require_once __DIR__ . '/../Core/Database.php';
        }
        $this->db = \Database::getInstance();
        $this->systemPrompt = \App\AI\Suporte\CarregadorPromptTudinha::carregarChat();
    }
    
    /**
     * Retorna o system prompt
     */
    public function getSystemPrompt()
    {
        return $this->systemPrompt;
    }

    /**
     * Retorna o system prompt com o contexto acadêmico do aluno (jornadas,
     * prazos, materiais) anexado.
     *
     * @param int $alunoId
     * @return string
     */
    public function getSystemPromptComContexto($alunoId)
    {
        $contexto = $this->construirContextoAlunoNoPrompt((int) $alunoId);
        return $contexto !== '' ? $this->systemPrompt . $contexto : $this->systemPrompt;
    }

    /**
     * Bloco de contexto do aluno (identidade + acadêmico + memória) para anexar ao system prompt.
     */
    public function construirContextoAlunoNoPrompt(int $alunoId): string
    {
        if ($alunoId <= 0) {
            return '';
        }
        return $this->montarIdentidadeAluno($alunoId)
            . $this->montarContextoAcademico($alunoId)
            . $this->montarMemoriaAluno($alunoId);
    }

    /**
     * Monta o array de mensagens OpenAI a partir do histórico da conversa.
     *
     * @return array<int, array{role: string, content: mixed}>
     */
    public function construirMensagensParaOpenAI(
        int $conversaId,
        string $mensagemAluno,
        $imageUrl = null,
        $imagemDataUriVisao = null
    ): array {
        $historico = $this->carregarHistoricoPrivado($conversaId, 20);
        $messages = [];

        foreach ($historico as $msg) {
            if ($msg['is_ia'] == 1) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $msg['mensagem'],
                ];
            } else {
                $content = $msg['mensagem'];
                if (!empty($msg['image_url'])) {
                    $content = 'Imagem: ' . $msg['image_url'] . "\n\n" . $content;
                }
                $messages[] = [
                    'role' => 'user',
                    'content' => $content,
                ];
            }
        }

        if (empty($messages)) {
            $conteudoMensagem = $mensagemAluno;
            if ($imageUrl) {
                $conteudoMensagem = 'Imagem: ' . $imageUrl . "\n\n" . $conteudoMensagem;
            }
            $messages[] = ['role' => 'user', 'content' => $conteudoMensagem];
        }

        if ($imagemDataUriVisao !== null && !empty($messages)) {
            $ultimoIndice = count($messages) - 1;
            if ($messages[$ultimoIndice]['role'] === 'user') {
                $textoAtual = is_string($messages[$ultimoIndice]['content'])
                    ? trim($messages[$ultimoIndice]['content'])
                    : '';
                $messages[$ultimoIndice]['content'] = [
                    ['type' => 'text', 'text' => $textoAtual !== '' ? $textoAtual : 'Descreva e explique o que você vê nesta imagem.'],
                    ['type' => 'image_url', 'image_url' => ['url' => $imagemDataUriVisao]],
                ];
            }
        }

        return $messages;
    }

    /**
     * Monta a linha com o primeiro nome do aluno (do cadastro), pra dar à
     * Tudinha um nome real pra usar na conversa em vez de vocativos genéricos.
     * A MEMÓRIA PESSOAL (montarMemoriaAluno) pode conter um apelido preferido
     * que a IA deve preferir a este nome de cadastro quando presente.
     *
     * @param int $alunoId
     * @return string
     */
    private function montarIdentidadeAluno($alunoId)
    {
        try {
            $aluno = $this->db->fetch("SELECT nome FROM alunos WHERE id = :id", ['id' => $alunoId]);
            $nomeCompleto = trim((string) ($aluno['nome'] ?? ''));
            if ($nomeCompleto === '') {
                return '';
            }
            $primeiroNome = trim(explode(' ', $nomeCompleto)[0]);
            if ($primeiroNome === '') {
                return '';
            }
            return "\n\nNome do aluno: {$primeiroNome}";
        } catch (\Throwable $e) {
            error_log("Tudinha: falha ao montar identidade do aluno: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Extrai o bloco <div class="tudinha-memoria" data-memorias='[...]'></div>
     * (emitido pelo prompt quando o aluno compartilha um fato pessoal durável)
     * do HTML da resposta. Mesmo padrão de extrairSugestoes(): tolerante à
     * ordem dos atributos e ao tipo de aspas, roda ANTES de qualquer
     * sanitização de HTML.
     *
     * Diferente de extrairSugestoes() (extraída a cada leitura, só pra UI),
     * esta extração deve rodar UMA ÚNICA VEZ, no momento em que a resposta é
     * gerada e persistida — o chamador é responsável por gravar via
     * salvarMemorias() e salvar $html (sem o marcador) no lugar do texto
     * original, para o marcador nunca ficar no banco.
     *
     * @param string $html
     * @return array{html: string, memorias: array<int, array{chave: string, valor: string}>}
     */
    public static function extrairMemorias($html)
    {
        $html = (string) $html;
        $memorias = [];

        if (preg_match_all('/<div\b([^>]*)>\s*<\/div>/i', $html, $divs, PREG_SET_ORDER)) {
            foreach ($divs as $div) {
                $atributos = $div[1];
                if (!preg_match('/class\s*=\s*(["\'])(?:(?!\1).)*tudinha-memoria(?:(?!\1).)*\1/i', $atributos)) {
                    continue;
                }
                if (!preg_match('/data-memorias\s*=\s*(["\'])((?:(?!\1).)*)\1/i', $atributos, $dataMatch)) {
                    continue;
                }
                $decodificado = json_decode(html_entity_decode($dataMatch[2], ENT_QUOTES), true);
                if (is_array($decodificado)) {
                    foreach ($decodificado as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $chave = trim((string) ($item['chave'] ?? ''));
                        $valor = trim((string) ($item['valor'] ?? ''));
                        if ($chave !== '' && $valor !== '') {
                            $memorias[] = ['chave' => $chave, 'valor' => $valor];
                        }
                    }
                }
                $html = trim(str_replace($div[0], '', $html));
                // Sem break: mesmo que a IA emita mais de um bloco por engano, todos devem
                // ser extraídos e removidos — o marcador nunca pode sobrar salvo no banco.
            }
        }

        if (empty($memorias) && strpos($html, 'tudinha-memoria') !== false) {
            error_log('Tudinha: bloco de memória presente na resposta mas não foi possível extrair (formato inesperado)');
        }

        return ['html' => $html, 'memorias' => $memorias];
    }

    /**
     * Grava (ou atualiza) fatos pessoais do aluno extraídos por extrairMemorias().
     * Upsert por (aluno_id, chave) — a IA reusa a mesma chave para atualizar um
     * fato já conhecido em vez de duplicar.
     *
     * @param int $alunoId
     * @param array<int, array{chave: string, valor: string}> $memorias
     */
    public function salvarMemorias($alunoId, array $memorias)
    {
        foreach ($memorias as $item) {
            $chave = strtolower(trim((string) ($item['chave'] ?? '')));
            $chave = preg_replace('/[^a-z0-9_]/', '_', $chave);
            $chave = trim((string) $chave, '_');
            $valor = trim((string) ($item['valor'] ?? ''));
            if ($chave === '' || $valor === '') {
                continue;
            }
            $chave = mb_substr($chave, 0, 80);
            $valor = mb_substr($valor, 0, 500);

            try {
                $this->db->query(
                    "INSERT INTO tudinha_memorias_aluno (aluno_id, chave, valor, created_at, updated_at)
                     VALUES (:aluno_id, :chave, :valor, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()",
                    ['aluno_id' => (int) $alunoId, 'chave' => $chave, 'valor' => $valor]
                );
            } catch (\Throwable $e) {
                error_log("Tudinha: falha ao salvar memória do aluno ({$chave}): " . $e->getMessage());
            }
        }
    }

    /**
     * Monta o bloco de contexto com fatos pessoais que o aluno já compartilhou
     * em conversas anteriores (apelido preferido, objetivo de prova, etc.).
     * Best-effort: qualquer falha retorna string vazia, nunca interrompe o chat.
     *
     * @param int $alunoId
     * @return string
     */
    private function montarMemoriaAluno($alunoId)
    {
        try {
            $memorias = $this->db->fetchAll(
                "SELECT chave, valor FROM tudinha_memorias_aluno WHERE aluno_id = :aluno_id ORDER BY updated_at DESC LIMIT 30",
                ['aluno_id' => $alunoId]
            );
            if (empty($memorias)) {
                return '';
            }
            $linhas = [];
            foreach ($memorias as $m) {
                $linhas[] = "- {$m['chave']}: {$m['valor']}";
            }
            return "\n\nMEMÓRIA PESSOAL DO ALUNO (fatos que ele já compartilhou com você em conversas "
                . "anteriores — use com naturalidade dentro da conversa, nunca liste isso como uma lista "
                . "pro aluno nem diga que está \"consultando a memória\"):\n"
                . implode("\n", $linhas);
        } catch (\Throwable $e) {
            error_log("Tudinha: falha ao montar memória do aluno: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Extrai o bloco <div class="tudinha-sugestoes" data-sugestoes='[...]'></div>
     * (emitido pelo prompt ao fim de respostas educacionais) do HTML da resposta.
     *
     * Extrai no backend, ANTES de qualquer sanitização de HTML, em vez de
     * confiar que a div sobreviva ao ChatFormatter::sanitizeHtml() (que não
     * inclui <div> nem atributos data-* no allowlist e removeria o bloco
     * silenciosamente). O HTML retornado já vem sem o bloco; as sugestões
     * vêm à parte, prontas para virar um campo próprio na resposta da API.
     *
     * @param string $html
     * @return array{html: string, sugestoes: array<int, string>}
     */
    public static function extrairSugestoes($html)
    {
        $html = (string) $html;
        $sugestoes = [];

        // Tolerante à ordem dos atributos e ao tipo de aspas (simples/duplas) em
        // ambos — a IA não é determinística na formatação exata do HTML entre
        // chamadas, então um regex rígido (ex.: exigindo "class" antes de
        // "data-sugestoes", ou só aspas simples) pode falhar silenciosamente e
        // deixar a div crua vazar até o ChatFormatter, que a remove sem avisar.
        if (preg_match_all('/<div\b([^>]*)>\s*<\/div>/i', $html, $divs, PREG_SET_ORDER)) {
            foreach ($divs as $div) {
                $atributos = $div[1];
                if (!preg_match('/class\s*=\s*(["\'])(?:(?!\1).)*tudinha-sugestoes(?:(?!\1).)*\1/i', $atributos)) {
                    continue;
                }
                if (!preg_match('/data-sugestoes\s*=\s*(["\'])((?:(?!\1).)*)\1/i', $atributos, $dataMatch)) {
                    continue;
                }
                $decodificado = json_decode(html_entity_decode($dataMatch[2], ENT_QUOTES), true);
                if (is_array($decodificado)) {
                    foreach ($decodificado as $pergunta) {
                        if (is_string($pergunta) && trim($pergunta) !== '') {
                            $sugestoes[] = trim($pergunta);
                        }
                    }
                }
                $html = trim(str_replace($div[0], '', $html));
                break; // só um bloco de sugestões esperado por resposta
            }
        }

        // Rede de segurança: se o marcador "tudinha-sugestoes" aparece na resposta
        // mas nada foi extraído, a IA provavelmente formatou o bloco fora do
        // esperado (ex.: div não vazia, self-closing, atributo sem aspas) — loga
        // pra não perder essa regressão silenciosamente (o ChatFormatter remove
        // a div sem avisar de qualquer forma).
        if (empty($sugestoes) && strpos($html, 'tudinha-sugestoes') !== false) {
            error_log('Tudinha: bloco de sugestões presente na resposta mas não foi possível extrair (formato inesperado)');
        }

        return ['html' => $html, 'sugestoes' => $sugestoes];
    }

    /**
     * Carrega histórico de mensagens (método público para uso externo)
     * Este método chama o método privado carregarHistoricoPrivado
     */
    public function carregarHistorico($conversa_id, $limite = 20)
    {
        return $this->carregarHistoricoPrivado($conversa_id, $limite);
    }
    
    /**
     * Gera resposta da Tudinha baseada na mensagem do aluno e histórico
     *
     * @param int $conversa_id ID da conversa
     * @param string $mensagemAluno Mensagem do aluno
     * @param string|null $imageUrl URL da imagem (opcional, só usada como referência textual no histórico)
     * @param int|null $alunoId Se informado, injeta contexto acadêmico (jornadas, prazos, materiais) no prompt desta chamada
     * @param string|null $imagemDataUriVisao Se informado (data URI completa, ex. "data:image/png;base64,...",
     *        já com o MIME real do arquivo), a IA "vê" essa imagem de verdade nesta chamada (formato multimodal
     *        da OpenAI), em vez de depender só de texto extraído por OCR. Só vale pro turno atual — turnos
     *        futuros da conversa só têm o texto salvo no histórico (ver carregarHistoricoPrivado).
     * @return string Resposta da Tudinha em HTML
     */
    public function gerarResposta($conversa_id, $mensagemAluno, $imageUrl = null, $alunoId = null, $imagemDataUriVisao = null)
    {
        try {
            require_once __DIR__ . '/../AI/TudinhaChatService.php';
            return \App\AI\TudinhaChatService::gerarResposta(
                (int) $conversa_id,
                (string) $mensagemAluno,
                $imageUrl,
                $alunoId !== null ? (int) $alunoId : null,
                $imagemDataUriVisao
            );
        } catch (Exception $e) {
            error_log("=== ERRO EM TudinhaService::gerarResposta ===");
            error_log("Tipo: " . get_class($e));
            error_log("Mensagem: " . $e->getMessage());
            error_log("Arquivo: " . $e->getFile());
            error_log("Linha: " . $e->getLine());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            error_log("=============================================");
            throw $e;
        }
    }

    /**
     * Gera texto didático em HTML sem conversa/histórico (ex.: modal de exercício na jornada).
     *
     * @param string $mensagemUsuario Conteúdo da pergunta em texto puro
     * @param string|null $systemOverride Se não vazio, substitui o system prompt padrão
     * @param string $model Modelo OpenAI (ex.: gpt-4o-mini, gpt-4o)
     * @param float $temperature Temperatura da amostragem
     * @param int $maxTokens Máximo de tokens na resposta
     * @return string HTML
     */
    public function gerarExplicacaoSemHistorico(
        string $mensagemUsuario,
        $systemOverride = null,
        string $model = 'gpt-4o-mini',
        float $temperature = 0.55,
        int $maxTokens = 1800
    ) {
        $apiKey = $this->openAIService->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new Exception('OPENAI_API_KEY não configurada. Configure via Admin > Dev Settings > Chaves de API ou no arquivo .env');
        }
        $system = ($systemOverride !== null && trim((string) $systemOverride) !== '')
            ? trim((string) $systemOverride)
            : $this->systemPrompt;
        $messages = [
            ['role' => 'user', 'content' => $mensagemUsuario],
        ];
        $resultado = $this->openAIService->chatCompletion(
            $messages,
            $system,
            $model,
            $temperature,
            $maxTokens
        );
        if (!isset($resultado['resposta']) || trim((string) $resultado['resposta']) === '') {
            throw new Exception('Resposta vazia da OpenAI');
        }
        $resposta = trim((string) $resultado['resposta']);

        return $this->validarEConverterHTML($resposta);
    }

    /**
     * Monta um bloco de texto (não HTML) com o contexto acadêmico atual do
     * aluno — jornadas em andamento com prazo/progresso e materiais
     * (apostilas) disponíveis — para dar à Tudinha dados reais ao responder
     * perguntas sobre agenda, prazos e "o que tenho pra fazer". Best-effort:
     * qualquer falha retorna string vazia, nunca interrompe o chat.
     *
     * @param int $alunoId
     * @return string
     */
    private function montarContextoAcademico($alunoId)
    {
        try {
            $aluno = $this->db->fetch("SELECT turma_id FROM alunos WHERE id = :id", ['id' => $alunoId]);
            $turmaId = (int) ($aluno['turma_id'] ?? 0);
            if ($turmaId <= 0) {
                return '';
            }

            $partes = [];

            // Jornadas em andamento (não concluídas), com prazo e progresso.
            // Filtra por turma_id diretamente no SQL (não em PHP depois do LIMIT):
            // um filtro amplo tipo "turma_id = X OR tem estrutura" antes do LIMIT
            // podia descartar as jornadas da turma do aluno se outras turmas do
            // tenant tivessem jornadas mais recentes. Aceita não pegar jornadas
            // vinculadas só via estrutura.turmas_selecionadas (contexto do chat
            // não precisa da cobertura completa do dashboard, só precisa ser
            // confiável para a turma principal do aluno).
            $jornadas = $this->db->fetchAll(
                "SELECT j.id, j.titulo, j.turma_id, j.estrutura,
                        (SELECT COUNT(*) FROM jornadas_modulos jm WHERE jm.jornada_id = j.id) as total_modulos,
                        (SELECT COUNT(*) FROM jornadas_progresso_alunos jpa WHERE jpa.jornada_id = j.id AND jpa.aluno_id = :aluno_id AND jpa.atividade_tipo = 'modulo' AND jpa.status = 'concluido') as modulos_concluidos
                 FROM jornadas j
                 WHERE j.turma_id = :turma_id
                   AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status IS NULL OR j.status = '')
                   AND (j.ativo = 1 OR j.ativo IS NULL)
                 ORDER BY j.created_at DESC
                 LIMIT 15",
                ['aluno_id' => $alunoId, 'turma_id' => $turmaId]
            );

            $linhasJornadas = [];
            $hoje = date('Y-m-d');
            foreach ($jornadas as $j) {
                $estrutura = json_decode($j['estrutura'] ?? '', true);
                $totalMod = (int) ($j['total_modulos'] ?? 0);
                $concluidos = (int) ($j['modulos_concluidos'] ?? 0);
                if ($totalMod > 0 && $concluidos >= $totalMod) {
                    continue; // já concluída
                }
                $linha = '- "' . $j['titulo'] . '"';
                if ($totalMod > 0) {
                    $linha .= " ({$concluidos}/{$totalMod} módulos concluídos)";
                }
                $dataFim = is_array($estrutura) ? ($estrutura['data_fim'] ?? null) : null;
                if (!empty($dataFim)) {
                    $linha .= $dataFim < $hoje ? " — prazo era {$dataFim}, ATRASADA" : " — prazo até {$dataFim}";
                }
                $linhasJornadas[] = $linha;
                if (count($linhasJornadas) >= 8) {
                    break;
                }
            }
            $partes[] = !empty($linhasJornadas)
                ? "Jornadas em andamento do aluno:\n" . implode("\n", $linhasJornadas)
                : "Jornadas em andamento do aluno: NENHUMA no momento (todas concluídas ou não há jornada atribuída à turma dele).";

            // Materiais (apostilas) já processados e disponíveis para o aluno
            $apostilas = $this->db->fetchAll(
                "SELECT ai.titulo, m.nome as materia_nome
                 FROM apostilas_ia ai
                 LEFT JOIN materias m ON m.id = ai.disciplina_id
                 WHERE (ai.turma_id = :turma_id OR ai.id IN (SELECT apostila_id FROM apostila_ia_turmas WHERE turma_id = :turma_id2))
                   AND ai.status = 'pronto'
                 ORDER BY ai.created_at DESC
                 LIMIT 10",
                ['turma_id' => $turmaId, 'turma_id2' => $turmaId]
            );
            if (!empty($apostilas)) {
                $linhasApostilas = [];
                foreach ($apostilas as $a) {
                    $materia = trim((string) ($a['materia_nome'] ?? ''));
                    $linhasApostilas[] = '- "' . $a['titulo'] . '"' . ($materia !== '' ? " ({$materia})" : '');
                }
                $partes[] = "Materiais (apostilas) disponíveis para o aluno:\n" . implode("\n", $linhasApostilas)
                    . "\nSe o aluno perguntar sobre o CONTEÚDO de um desses materiais, oriente a abrir o "
                    . 'chat da apostila específica (menu "Meu Material") — não tente responder o conteúdo aqui, só confirme que o material existe e ajude a localizá-lo.';
            } else {
                $partes[] = "Materiais (apostilas) disponíveis para o aluno: NENHUM processado ainda para a turma dele.";
            }

            // Recados do mural (professor/admin → turma do aluno), só os ainda válidos.
            // Inclui a matéria (quando o recado tem uma vinculada) pra IA conseguir
            // filtrar por matéria se o aluno pedir ("recado de biologia", por exemplo).
            $recados = $this->db->fetchAll(
                "SELECT r.titulo, r.conteudo, r.data_publicacao, r.autor_tipo, r.autor_id,
                        CASE WHEN r.autor_tipo = 'professor' THEN (SELECT p.nome FROM professores p WHERE p.id = r.autor_id LIMIT 1) ELSE 'Administração' END as autor_nome,
                        m.nome as materia_nome
                 FROM mural_recados r
                 LEFT JOIN materias m ON m.id = r.materia_id
                 WHERE (r.enviar_para_todos = 1 OR EXISTS (
                            SELECT 1 FROM mural_recados_turmas rt WHERE rt.mural_recado_id = r.id AND rt.turma_id = :turma_id
                        ))
                   AND CURDATE() <= r.data_sai_mural
                 ORDER BY r.data_publicacao DESC
                 LIMIT 10",
                ['turma_id' => $turmaId]
            );
            if (!empty($recados)) {
                $linhasRecados = [];
                foreach ($recados as $r) {
                    $autor = trim((string) ($r['autor_nome'] ?? '')) ?: 'Escola';
                    $materia = trim((string) ($r['materia_nome'] ?? ''));
                    $data = !empty($r['data_publicacao']) ? date('d/m/Y', strtotime($r['data_publicacao'])) : '';
                    $conteudoCompleto = trim(strip_tags((string) ($r['conteudo'] ?? '')));
                    $linha = "- \"{$r['titulo']}\"" . ($materia !== '' ? " [matéria: {$materia}]" : '') . " ({$autor}, {$data}): {$conteudoCompleto}";
                    $linhasRecados[] = $linha;
                }
                $partes[] = "Recados do mural para a turma do aluno, do MAIS RECENTE pro mais antigo "
                    . "(o primeiro da lista é o último recado):\n" . implode("\n", $linhasRecados)
                    . "\nSe o aluno pedir \"o último recado\", \"os dois últimos\" ou \"recado de tal matéria\", "
                    . "entregue direto o essencial do(s) recado(s) pedido(s) — não apenas confirme que existe. "
                    . "Mas resuma de forma prática e direta (quem mandou, do que se trata, e a parte que importa "
                    . "pro aluno agir — data, prazo, o que precisa levar/fazer), em vez de recitar o texto "
                    . "completo palavra por palavra; isso vale ainda mais em conversa por voz, onde um recado "
                    . "lido na íntegra fica cansativo de ouvir.";
            } else {
                $partes[] = "Recados do mural para a turma do aluno: NENHUM recado ativo no momento.";
            }

            // Provas realizadas e notas — mais recentes primeiro, exame direto por aluno_id
            $provas = $this->db->fetchAll(
                "SELECT p.titulo, pr.nota, pr.status, pr.iniciado_em,
                        (SELECT COUNT(*) FROM provas_questoes pq WHERE pq.prova_id = pr.prova_id) as total_questoes,
                        (SELECT COUNT(*) FROM provas_respostas prs WHERE prs.prova_id = pr.prova_id AND prs.aluno_id = pr.aluno_id AND prs.correta = 1) as acertos
                 FROM provas_realizacoes pr
                 INNER JOIN provas p ON p.id = pr.prova_id
                 WHERE pr.aluno_id = :aluno_id
                 ORDER BY pr.iniciado_em DESC
                 LIMIT 8",
                ['aluno_id' => $alunoId]
            );
            if (!empty($provas)) {
                $linhasProvas = [];
                foreach ($provas as $p) {
                    $data = !empty($p['iniciado_em']) ? date('d/m/Y', strtotime($p['iniciado_em'])) : '';
                    $linha = "- \"{$p['titulo']}\" ({$data})";
                    if ($p['status'] === 'finalizada' || $p['status'] === 'corrigida') {
                        $nota = $p['nota'] !== null ? number_format((float) $p['nota'], 1, ',', '') : 'ainda sem nota';
                        $linha .= ": nota {$nota}";
                        $totalQ = (int) ($p['total_questoes'] ?? 0);
                        if ($totalQ > 0) {
                            $linha .= " ({$p['acertos']}/{$totalQ} questões corretas)";
                        }
                    } else {
                        $linha .= ": ainda não finalizada (status: {$p['status']})";
                    }
                    $linhasProvas[] = $linha;
                }
                $partes[] = "Provas realizadas pelo aluno (mais recentes primeiro):\n" . implode("\n", $linhasProvas);
            } else {
                $partes[] = "Provas realizadas pelo aluno: NENHUMA prova registrada ainda.";
            }

            // Desempenho agregado em exercícios das jornadas (acertos vs. erros) — pontuacao
            // > 0 é acerto, pontuacao = 0/NULL é erro (não existe coluna booleana "correta" aqui)
            $desempenho = $this->db->fetch(
                "SELECT
                    COUNT(*) as total_respondidas,
                    SUM(CASE WHEN jpa.pontuacao > 0 THEN 1 ELSE 0 END) as total_acertos,
                    SUM(CASE WHEN jpa.pontuacao = 0 OR jpa.pontuacao IS NULL THEN 1 ELSE 0 END) as total_erros
                 FROM jornadas_progresso_alunos jpa
                 INNER JOIN jornadas j ON j.id = jpa.jornada_id
                 WHERE jpa.aluno_id = :aluno_id
                   AND j.turma_id = :turma_id
                   AND jpa.atividade_tipo IN ('exercicio', 'exercicio_modulo')
                   AND jpa.resposta IS NOT NULL",
                ['aluno_id' => $alunoId, 'turma_id' => $turmaId]
            );
            $totalRespondidas = (int) ($desempenho['total_respondidas'] ?? 0);
            if ($totalRespondidas > 0) {
                $acertos = (int) ($desempenho['total_acertos'] ?? 0);
                $erros = (int) ($desempenho['total_erros'] ?? 0);
                $percentual = round(($acertos / $totalRespondidas) * 100);
                $partes[] = "Relatório geral de desempenho em exercícios das jornadas: {$totalRespondidas} exercícios "
                    . "respondidos no total, {$acertos} acertos e {$erros} erros ({$percentual}% de aproveitamento).";
            } else {
                $partes[] = "Relatório geral de desempenho em exercícios das jornadas: NENHUM exercício respondido ainda.";
            }

            return "\n\nCONTEXTO ACADÊMICO ATUAL DO ALUNO — você TEM acesso a esses dados reais, já "
                . "consultados no sistema (não diga que não tem acesso a informações pessoais; use o que "
                . "está aqui pra responder sobre agenda, prazos, jornadas, materiais, recados, provas/notas "
                . "e desempenho). Se uma seção diz NENHUM/NENHUMA, isso é a resposta correta — diga isso ao "
                . "aluno normalmente, não invente dados que não estejam aqui:\n"
                . implode("\n\n", $partes);
        } catch (\Throwable $e) {
            error_log("Tudinha: falha ao montar contexto acadêmico: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Carrega histórico de mensagens da conversa (método privado)
     *
     * @param int $conversa_id ID da conversa
     * @param int $limite Número máximo de mensagens (padrão: 20)
     * @return array Array de mensagens ordenadas por data
     */
    private function carregarHistoricoPrivado($conversa_id, $limite = 20)
    {
        try {
            // Pega as $limite mensagens mais RECENTES (ORDER BY DESC + LIMIT), depois
            // reordena ASC pra ficar cronológico de novo antes de devolver — um
            // "ORDER BY created_at ASC LIMIT N" direto pega sempre as N mensagens
            // mais ANTIGAS da conversa, não as mais recentes; numa conversa com mais
            // de $limite mensagens, isso travava a IA vendo só o início antigo do
            // papo pra sempre, sem nunca alcançar o que foi dito por último.
            $sql = "SELECT mensagem, is_ia, image_url, created_at
                    FROM (
                        SELECT id, mensagem, is_ia, image_url, created_at
                        FROM tudinha_mensagens
                        WHERE conversa_id = :conversa_id
                        ORDER BY created_at DESC, id DESC
                        LIMIT " . (int)$limite . "
                    ) recentes
                    ORDER BY created_at ASC, id ASC";

            $mensagens = $this->db->fetchAll(
                $sql,
                [
                    'conversa_id' => $conversa_id
                ]
            );

            return $mensagens ?: [];

        } catch (Exception $e) {
            error_log("Erro ao carregar histórico: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Valida e converte resposta para HTML válido
     * Remove markdown e garante HTML válido
     * 
     * @param string $resposta Resposta da IA
     * @return string Resposta em HTML válido
     */
    public function validarEConverterHTML($resposta)
    {
        // Remover markdown code blocks se existirem
        $resposta = preg_replace('/```[a-z]*\n?/i', '', $resposta);
        $resposta = preg_replace('/```\n?/', '', $resposta);
        
        // Remover markdown bold (**texto**)
        $resposta = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $resposta);
        
        // Remover markdown headers (##, ###)
        $resposta = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $resposta);
        $resposta = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $resposta);
        $resposta = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $resposta);
        
        // Remover markdown listas (- item)
        $resposta = preg_replace('/^-\s+(.+)$/m', '<li>$1</li>', $resposta);
        
        // LaTeX de verdade (\( \), \[ \]) é esperado agora — não mexer nele.
        // Só normaliza o delimitador $...$/$$...$$ caso a IA escorregue pra essa
        // sintaxe por hábito (o MathJax da página não está configurado pra
        // reconhecer $, só \( \) e \[ \]), convertendo pro delimitador certo em
        // vez de só arrancar os $ e deixar a fórmula sem render nenhum.
        $resposta = preg_replace('/\$\$([^$]+)\$\$/', '\\[$1\\]', $resposta);
        $resposta = preg_replace('/\$([^$]+)\$/', '\\($1\\)', $resposta);
        
        // Garantir que está envolvido em parágrafos se necessário
        if (!preg_match('/^<[h|p|d|u|o|l]/i', trim($resposta))) {
            // Se não começar com tag HTML, envolver em parágrafo
            $resposta = '<p>' . nl2br($resposta) . '</p>';
        }
        
        return $resposta;
    }
}

