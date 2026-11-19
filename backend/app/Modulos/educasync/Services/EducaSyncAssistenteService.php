<?php

namespace App\Modulos\EducaSync\Services;

use App\Core\CreditosModuleRegistry;
use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Models\ComandoIa;

/**
 * EducaSync — assistente em linguagem natural com tools tipadas (MVP).
 *
 * Mesmo padrão do ProvasAlunoAssistenteService (o LLM pede uma tool entre <<<CONSULTA>>> e <<<FIM>>>,
 * o PHP executa só o que está na whitelist e devolve o resultado), mas pertence ao módulo EducaSync:
 * as tools aqui NÃO fazem parte do assistente geral de provas.
 *
 * Limites de segurança:
 * - sem SQL do LLM: cada tool chama um Service/Model do módulo, sempre dentro do cenário informado pela rota;
 * - nenhuma tool altera o cenário: `propose_constraint_changes` só grava rascunho (confirmado = 0);
 *   a aplicação exige o endpoint de confirmação, acionado pelo usuário;
 * - nenhuma tool gera ou publica grade (o solver só roda pelo botão "Gerar grade").
 */
class EducaSyncAssistenteService
{
    public const MODULO_CREDITOS = 'educasync_assistente_mensagem';
    /**
     * Liberação temporária para testes locais: assistente funciona sem TudiCoins.
     * Voltar para false antes de produção.
     */
    public const LIBERAR_SEM_TUDICOINS = true;
    /** GPT-6 Astra — topo de linha; fallback Sol → Terra em ModeloIA. */
    public const MODELO_IA = \App\Agentes\ModeloIA::ASTRA;
    public const DELIMITADOR_INICIO = '<<<CONSULTA>>>';
    public const DELIMITADOR_FIM = '<<<FIM>>>';
    private const MAX_RODADAS = 8;
    private const MAX_MENSAGEM = 8000;

    private Cenario $cenarios;
    private ComandoIa $comandos;
    private EducaSyncAssistenteConsultaService $consulta;
    private EducaSyncPropostaService $propostas;
    /** @var \App\Services\OpenAIService */
    private $openai;

    public function __construct(
        ?Cenario $cenarios = null,
        ?ComandoIa $comandos = null,
        ?EducaSyncAssistenteConsultaService $consulta = null,
        ?EducaSyncPropostaService $propostas = null,
        $openai = null
    ) {
        $this->cenarios = $cenarios ?? new Cenario();
        $this->comandos = $comandos ?? new ComandoIa();
        $this->propostas = $propostas ?? new EducaSyncPropostaService();
        $this->consulta = $consulta ?? new EducaSyncAssistenteConsultaService(null, null, null, null, null, null, null, null, $this->propostas);
        $this->openai = $openai instanceof \App\Services\OpenAIService
            ? $openai
            : new \App\Services\OpenAIService();
    }

    public static function disponivel(): bool
    {
        return CreditosModuleRegistry::acaoIaDisponivel(self::MODULO_CREDITOS) || self::LIBERAR_SEM_TUDICOINS;
    }

    /**
     * @param list<array{role?:string,content?:string}> $historico
     * @return array{success:bool, mensagem?:string, proposta?:?array<string,mixed>, consultas?:list<array<string,mixed>>, comando_ia_id?:int, error?:string}
     */
    public function processarMensagem(int $cenarioId, int $usuarioId, string $mensagemUsuario, array $historico = []): array
    {
        $mensagemUsuario = trim($mensagemUsuario);
        if ($mensagemUsuario === '') {
            return ['success' => false, 'error' => 'Digite sua pergunta sobre o cenário.'];
        }
        if (mb_strlen($mensagemUsuario) > self::MAX_MENSAGEM) {
            return ['success' => false, 'error' => 'Mensagem muito longa (máx. ' . self::MAX_MENSAGEM . ' caracteres).'];
        }
        if (!$this->cenarios->findById($cenarioId)) {
            return ['success' => false, 'error' => 'Cenário não encontrado.'];
        }

        try {
            $this->assertCreditosDisponiveis();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Auditoria: uma linha por mensagem; a tool de proposta atualiza esta mesma linha.
        $comandoId = $this->comandos->criar($cenarioId, $usuarioId, $mensagemUsuario);

        $mensagens = [];
        foreach (array_slice($historico, -10) as $msg) {
            $role = (string) ($msg['role'] ?? '');
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content === '' || ($role !== 'user' && $role !== 'assistant')) {
                continue;
            }
            $mensagens[] = ['role' => $role, 'content' => mb_substr($content, 0, 3000)];
        }
        $mensagens[] = ['role' => 'user', 'content' => $mensagemUsuario];

        $system = $this->montarSystemPrompt();
        $consultasFeitas = [];
        $propostaPendente = null;
        $textoFinal = null;

        try {
            for ($rodada = 0; $rodada < self::MAX_RODADAS; $rodada++) {
                // Astra: orçamento de raciocínio ampliado em ModeloIA::orcamentoTokens.
                $raw = $this->openai->chatCompletion($mensagens, $system, self::MODELO_IA, 0.2, 8000);
                $resposta = trim((string) ($raw['resposta'] ?? ''));
                $pedido = $this->extrairConsulta($resposta);

                if ($pedido === null) {
                    $textoFinal = $this->limparRespostaFinal($resposta);
                    break;
                }

                $tool = (string) $pedido['tool'];
                $args = $pedido['args'];
                $resultado = $this->executarTool($cenarioId, $comandoId, $tool, $args);
                $consultasFeitas[] = ['tool' => $tool, 'args' => $args];

                if ($tool === 'propose_constraint_changes' && !empty($resultado['ok'])) {
                    $propostaPendente = $this->propostaParaUi($comandoId, $resultado);
                }

                $mensagens[] = ['role' => 'assistant', 'content' => $resposta];
                $mensagens[] = [
                    'role' => 'user',
                    'content' => "Resultado da tool ({$tool}):\n"
                        . json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        . "\n\nUse só esses dados (não invente). Se precisar de outra tool, emita <<<CONSULTA>>> de novo. "
                        . 'Quando tiver o suficiente, responda em texto curto, separando FATOS de SUGESTÕES.',
                ];
            }
        } catch (\Throwable $e) {
            error_log('EducaSyncAssistenteService: ' . $e->getMessage());
            $detalhe = $e->getMessage();
            if (stripos($detalhe, 'Could not resolve host') !== false
                || stripos($detalhe, 'Failed to connect') !== false
                || stripos($detalhe, 'timed out') !== false
                || stripos($detalhe, 'Operation timed out') !== false
            ) {
                return [
                    'success' => false,
                    'error' => 'Não foi possível conectar à API da IA (rede/DNS). Verifique a internet do servidor e tente de novo em instantes.',
                ];
            }
            return ['success' => false, 'error' => 'Falha ao consultar a IA. Tente novamente.'];
        }

        if ($textoFinal === null || $textoFinal === '') {
            $textoFinal = $propostaPendente !== null
                ? 'Preparei uma proposta. Revise abaixo e confirme se estiver de acordo.'
                : 'Consultei os dados do cenário. Pode detalhar o que quer analisar?';
        }

        try {
            $this->debitarCreditos($comandoId);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return [
            'success' => true,
            'mensagem' => $textoFinal,
            'proposta' => $propostaPendente,
            'consultas' => $consultasFeitas,
            'comando_ia_id' => $comandoId,
        ];
    }

    /**
     * Whitelist das tools. Qualquer nome fora daqui é recusado.
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function executarTool(int $cenarioId, int $comandoId, string $tool, array $args): array
    {
        switch ($tool) {
            case 'get_school_schedule_context':
                return $this->consulta->contextoCenario($cenarioId);

            case 'get_teacher_availability':
                return $this->consulta->disponibilidadeProfessor($cenarioId, $args);

            case 'analyze_schedule_conflicts':
                return $this->consulta->analisarConflitos($cenarioId, $args);

            case 'propose_constraint_changes':
                // Só grava rascunho em educasync_comandos_ia (confirmado = 0). Não aplica nada.
                return $this->propostas->propor(
                    $cenarioId,
                    $comandoId,
                    $args['alteracoes'] ?? null,
                    (string) ($args['resumo'] ?? '')
                );

            case 'validate_constraint_changes':
                return $this->validarAlteracoes($cenarioId, $comandoId, $args);

            case 'compare_schedule_versions':
                return $this->consulta->compararVersoes($cenarioId, $args);

            case 'explain_schedule_result':
                return $this->consulta->explicarResultado($cenarioId, $args);

            case 'simulate_schedule':
                // Não roda o solver na request: só orienta a usar o botão Gerar grade.
                return $this->consulta->orientarGeracao();

            default:
                return ['ok' => false, 'error' => 'Tool desconhecida: ' . $tool];
        }
    }

    /**
     * @param array<string,mixed> $args alteracoes (opcional; sem ela valida o rascunho desta mensagem)
     * @return array<string,mixed>
     */
    private function validarAlteracoes(int $cenarioId, int $comandoId, array $args): array
    {
        $alteracoes = $args['alteracoes'] ?? null;
        if ($alteracoes === null) {
            $comando = $this->comandos->findDoCenario($comandoId, $cenarioId);
            $rascunho = json_decode((string) ($comando['proposta_json'] ?? ''), true);
            $alteracoes = is_array($rascunho) ? ($rascunho['alteracoes'] ?? null) : null;
        }
        $validacao = $this->propostas->validar($cenarioId, $alteracoes);
        return [
            'ok' => true,
            'valida' => $validacao['valida'],
            'erros' => $validacao['erros'],
            'avisos' => $validacao['avisos'],
            'alteracoes_normalizadas' => $validacao['alteracoes'],
        ];
    }

    /**
     * @param array<string,mixed> $resultado retorno de EducaSyncPropostaService::propor
     * @return array<string,mixed>
     */
    private function propostaParaUi(int $comandoId, array $resultado): array
    {
        $itens = [];
        foreach ((array) ($resultado['proposta']['alteracoes'] ?? []) as $item) {
            $itens[] = [
                'tipo' => (string) $item['tipo'],
                'descricao' => $this->propostas->descrever($item),
                'aplicavel' => (bool) (EducaSyncPropostaService::TIPOS[$item['tipo']]['aplicavel'] ?? false),
            ];
        }
        return [
            'comando_ia_id' => $comandoId,
            'resumo' => (string) ($resultado['proposta']['resumo'] ?? ''),
            'itens' => $itens,
            'avisos' => (array) ($resultado['avisos'] ?? []),
        ];
    }

    private function montarSystemPrompt(): string
    {
        return <<<PROMPT
Você é o Assistente do EducaSync (grade horária) da EducaTudo, para coordenação/direção.
Você trabalha SEMPRE sobre o cenário aberto na tela. Responda em português, curto (3–6 linhas).

Regras:
1) Nunca invente dados. Use só o que as tools devolverem. Sem dado, diga que não sabe e consulte a tool adequada.
2) Separe claramente FATOS (vêm das tools/solver) de SUGESTÕES (opinião genérica, não simulada). Escreva "Fatos:" e "Sugestões:" quando houver os dois.
3) Você NÃO gera, NÃO simula e NÃO publica grades. Se pedirem para gerar/simular/publicar, oriente a usar o botão "Gerar grade" do cenário e a publicar manualmente pela tela da versão.
4) Você NÃO altera o cenário. Para mudar restrições, use propose_constraint_changes: isso só cria uma PROPOSTA. Diga ao usuário que ele precisa clicar em "Confirmar" para aplicar. Nunca diga que já aplicou.
5) Tipos de proposta suportados: bloquear_professor_dia, bloquear_professor_slots, evitar_disciplina_slot, minimizar_janelas, concentrar_disciplina. Todos são aplicados ao confirmar. Exceção: bloqueios de professor (dia ou slots) NÃO são aplicados se o cenário já tem versões geradas (avise que é preciso um novo cenário). Os softs (evitar/minimizar/concentrar) valem só para as PRÓXIMAS gerações e são preferências, não garantias; diga isso ao propor.
6) Antes de propor, confira professor/matéria/dia com get_school_schedule_context (UMA vez basta). Use o rotulo/id do contexto (Matéria — Nome). Só peça id se o nome for ambíguo no retorno da tool. NÃO chame get_teacher_availability para cada professor de uma lista.
7) Para bloqueio parcial (ex.: “2ª e 3ª de terça”), use bloquear_professor_slots — NÃO diga que o sistema não suporta. Agrupe vários horários do MESMO professor em um único item (campo slots ou dia+ordens). Se o professor tem bloqueios em dias diferentes, pode ser 1 item com todos os slots OU 2 itens — prefira 1 item com todos os slots.
8) LISTA de indisponibilidades (vários professores de uma vez): chame get_school_schedule_context UMA vez e em seguida propose_constraint_changes com TODAS as alterações numa ÚNICA proposta. NÃO faça perguntas pedantes de interpretação (ex.: se “após as 10h” inclui o slot das 10:00). Resolva com horarios.grade_diaria / como_interpretar do contexto e proponha. Só pergunte se faltar professor no contexto ou se o texto for realmente impossível de mapear.
9) Interpretação padrão (use horarios.max_ordem e grade_diaria):
   - “todo o período” / “durante todo o período” / dia inteiro → bloquear_professor_dia
   - “N primeiras aulas” / “N primeiros tempos” → ordens 1..N
   - “N últimas aulas” / “N últimos tempos” → as N maiores ordens (ex.: max=6 e 2 últimas → 5 e 6)
   - “última aula” / “último tempo” → maior ordem
   - “após as Xh” / “depois das Xh” → no dia indicado, todos os slots com inicio >= HH:00 (INCLUI o que começa em X)
10) No texto ao usuário e no resumo da proposta, use SEMPRE “aula/aulas” — nunca “tempo/tempos”. Ex.: “sextas após as 10h = aulas 4–6”.
11) Conflitos de publicação / grade inviável: use analyze_schedule_conflicts (com versao_id se souber). Em Sugestões, seja concreto — contratar professor extra, redistribuir demanda entre docentes da mesma matéria, limpar grade de outras turmas do mesmo ano, aliviar bloqueios de disponibilidade ou reotimizar. Use os campos publicacao.sugestoes e conflitos_obvios quando existirem.
12) Não exponha dados pessoais além do nome do professor/turma/matéria necessário.

Para usar uma tool, emita EXATAMENTE:
<<<CONSULTA>>>
{"tool":"NOME","args":{...}}
<<<FIM>>>

Tools:
1) get_school_schedule_context — {} — resumo do cenário (grade_diaria com ordem/início/fim, demandas, versões, pesos, professores com rotulo Matéria — Nome).
2) get_teacher_availability — {"professor_id":12} ou {"professor_nome":"Ana"} — horários livres e bloqueados do professor.
3) analyze_schedule_conflicts — {"versao_id":5} (opcional) — com versão: confere alocações + bloqueio de publicação (publicacao.sugestoes); sem versão: valida o cenário e acha sobrecarga de professor.
4) propose_constraint_changes — {"resumo":"...","alteracoes":[ITEM,...]} — grava rascunho, NÃO aplica. ITEM:
   {"tipo":"bloquear_professor_dia","professor_id":12,"dia_semana":3}  (dia 1=Segunda … 7=Domingo, ou o nome do dia)
   {"tipo":"bloquear_professor_slots","professor_id":12,"slots":[{"dia_semana":2,"ordem":1},{"dia_semana":2,"ordem":2}]}
   {"tipo":"bloquear_professor_slots","professor_nome":"Helena Duarte","dia_semana":"terça","ordens":[2,3]}
   {"tipo":"evitar_disciplina_slot","materia_id":4,"dia_semana":5,"ordem":5,"peso":50}
   {"tipo":"minimizar_janelas","peso":200}  (0 a 1000)
   {"tipo":"concentrar_disciplina","materia_id":4,"turma_id":7,"peso":50}  (turma_id opcional)
5) validate_constraint_changes — {"alteracoes":[ITEM,...]} ou {} (valida o rascunho atual) — confere tipos e se as entidades existem.
6) compare_schedule_versions — {"versao_a":3,"versao_b":5} — diferença de métricas e de alocações.
7) explain_schedule_result — {"versao_id":5} — fatos do solver da versão + sugestões genéricas.
PROMPT;
    }

    /** @return array{tool:string,args:array<string,mixed>}|null */
    private function extrairConsulta(string $resposta): ?array
    {
        $ini = strpos($resposta, self::DELIMITADOR_INICIO);
        if ($ini === false) {
            return null;
        }
        $depois = substr($resposta, $ini + strlen(self::DELIMITADOR_INICIO));
        $fim = strpos($depois, self::DELIMITADOR_FIM);
        $jsonRaw = trim($fim === false ? $depois : substr($depois, 0, $fim));
        $jsonRaw = preg_replace('/^```(?:json)?\s*/i', '', $jsonRaw) ?? $jsonRaw;
        $jsonRaw = preg_replace('/\s*```$/', '', $jsonRaw) ?? $jsonRaw;
        $decoded = json_decode(trim($jsonRaw), true);
        if (!is_array($decoded) || empty($decoded['tool'])) {
            return null;
        }
        return [
            'tool' => (string) $decoded['tool'],
            'args' => is_array($decoded['args'] ?? null) ? $decoded['args'] : [],
        ];
    }

    private function limparRespostaFinal(string $resposta): string
    {
        $resposta = preg_replace(
            '/' . preg_quote(self::DELIMITADOR_INICIO, '/') . '.*?' . preg_quote(self::DELIMITADOR_FIM, '/') . '/s',
            '',
            $resposta
        ) ?? $resposta;
        $pos = strpos($resposta, self::DELIMITADOR_INICIO);
        if ($pos !== false) {
            $resposta = substr($resposta, 0, $pos);
        }
        return trim($resposta);
    }

    private function assertCreditosDisponiveis(): void
    {
        if (self::LIBERAR_SEM_TUDICOINS) {
            return;
        }
        if (!CreditosModuleRegistry::isValid(self::MODULO_CREDITOS)) {
            throw new \Exception('Item de TudiCoins do assistente do EducaSync não cadastrado.');
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        if (!$creditos->podeConsumir('escola', CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, self::MODULO_CREDITOS)) {
            throw new \Exception('TudiCoins insuficientes na carteira da escola.');
        }
    }

    private function debitarCreditos(int $comandoId): void
    {
        if (self::LIBERAR_SEM_TUDICOINS) {
            return;
        }
        if (!CreditosModuleRegistry::isValid(self::MODULO_CREDITOS)) {
            throw new \Exception('Item de TudiCoins do assistente do EducaSync não cadastrado.');
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        $creditos->consumirEscola(self::MODULO_CREDITOS, 'educasync_assistente:' . $comandoId);
    }
}
