<?php
/**
 * Assistente da tela Evento de Notas.
 * Explica o cálculo com o manual desta tela e só cita nota, lançamento ou jornada
 * depois de consultar o banco. Não inventa número.
 */

require_once __DIR__ . '/OpenAIService.php';
require_once __DIR__ . '/BoletimAssistenteService.php';
require_once __DIR__ . '/BoletimAssistenteFerramentas.php';
require_once __DIR__ . '/BoletimAssistenteWizard.php';
require_once __DIR__ . '/ProvasAlunoConsultaService.php';
require_once __DIR__ . '/JornadasAlunoConsultaService.php';
require_once __DIR__ . '/CreditosService.php';
require_once __DIR__ . '/../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../Controllers/Admin/BoletimConfigController.php';

class BoletimConsultaAssistenteService
{
    public const DELIMITADOR_INICIO = '<<<CONSULTA>>>';
    public const DELIMITADOR_FIM = '<<<FIM>>>';
    private const MAX_RODADAS = 4;

    private BoletimAssistenteFerramentas $ferramentas;
    private BoletimAssistenteWizard $wizard;
    private ProvasAlunoConsultaService $provas;
    private JornadasAlunoConsultaService $jornadas;
    /** @var \App\Services\OpenAIService */
    private $openai;

    public function __construct(
        ?BoletimAssistenteFerramentas $ferramentas = null,
        ?BoletimAssistenteWizard $wizard = null,
        ?ProvasAlunoConsultaService $provas = null,
        ?JornadasAlunoConsultaService $jornadas = null,
        $openai = null
    ) {
        $this->ferramentas = $ferramentas ?? new BoletimAssistenteFerramentas();
        $this->wizard = $wizard ?? new BoletimAssistenteWizard($this->ferramentas);
        $this->provas = $provas ?? new ProvasAlunoConsultaService();
        $this->jornadas = $jornadas ?? new JornadasAlunoConsultaService($this->provas);
        $this->openai = $openai instanceof \App\Services\OpenAIService
            ? $openai
            : new \App\Services\OpenAIService();
    }

    /**
     * @param list<array{role?:string,content?:string}> $historico
     * @param array<string,mixed>|null $wizardEstado
     * @return array{success:bool,mensagem?:string,error?:string}
     */
    public function processarMensagem(string $mensagem, array $historico = [], ?array $wizardEstado = null): array
    {
        $mensagem = trim($mensagem);
        if ($mensagem === '') {
            return ['success' => false, 'error' => 'Escreva a pergunta.'];
        }

        if (!$this->parecePerguntaDeDado($mensagem)) {
            $guia = $this->responderGuia($mensagem);
            if ($guia !== null) {
                return ['success' => true, 'mensagem' => $guia];
            }
        }

        try {
            $this->assertCreditosDisponiveis();
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $mensagens = [];
        foreach (array_slice($historico, -8) as $msg) {
            $role = (string) ($msg['role'] ?? '');
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content === '' || ($role !== 'user' && $role !== 'assistant')) {
                continue;
            }
            $mensagens[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
        }
        $mensagens[] = ['role' => 'user', 'content' => $mensagem];

        $textoFinal = null;
        try {
            for ($rodada = 0; $rodada < self::MAX_RODADAS; $rodada++) {
                $raw = $this->openai->chatCompletion(
                    $mensagens,
                    $this->montarSystemPrompt($wizardEstado),
                    'gpt-4o-mini',
                    0.1,
                    1800,
                    false
                );
                $resposta = trim((string) ($raw['resposta'] ?? ''));
                $pedido = $this->extrairConsulta($resposta);
                if ($pedido === null) {
                    $textoFinal = $this->limparRespostaFinal($resposta);
                    break;
                }

                $tool = (string) ($pedido['tool'] ?? '');
                $args = is_array($pedido['args'] ?? null) ? $pedido['args'] : [];
                $resultado = $this->executarTool($tool, $args, $wizardEstado);
                $mensagens[] = ['role' => 'assistant', 'content' => $resposta];
                $mensagens[] = [
                    'role' => 'user',
                    'content' => "Resultado de {$tool} (fonte do sistema, não invente além disto):\n"
                        . json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        . "\n\nResponda em português, curto, só com o que veio neste JSON. "
                        . 'Se houver candidatos, peça para escolher. Se faltar o número, diga que não encontrou. '
                        . 'Não emita outra consulta se já deu para responder.',
                ];
            }
        } catch (Throwable $e) {
            error_log('BoletimConsultaAssistenteService: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não deu para consultar agora. Tente de novo.'];
        }

        try {
            $this->debitarCreditos();
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($textoFinal === null || $textoFinal === '') {
            $textoFinal = 'Consultei o sistema, mas não fechou uma resposta. Diga o nome do aluno e a matéria.';
        }

        return ['success' => true, 'mensagem' => $textoFinal];
    }

    private function parecePerguntaDeDado(string $mensagem): bool
    {
        return (bool) preg_match(
            '/\b(nota|notas|jornada|jornadas|quantas|quanto|quais|lançamento|lancamento|aluno|aluna|fez|tirou|acertou)\b/ui',
            $mensagem
        );
    }

    private function responderGuia(string $mensagem): ?string
    {
        $t = mb_strtolower($mensagem);
        if (preg_match('/enac|substitu|maior\s*\(/u', $t)) {
            return "Para a Média Bim Final ficar com a média e só trocar quando o ENAC for maior:\n\n"
                . "1. Abra a coluna Média Bim Final.\n"
                . "2. Limpe a fórmula.\n"
                . "3. Monte: maior (  Média Bim  ,  ENAC  )\n"
                . "4. Salvar bloco.\n\n"
                . "Exemplo: média 7 e ENAC 9 vira 9. Média 8 e ENAC 6 continua 8. Não use o botão Maior entre as duas primeiras — ele pega os dois primeiros tipos da lista, não a Média Bim com o ENAC.";
        }
        if (preg_match('/semanal|s1|semana/u', $t)) {
            return "A Prova Semanal lista os eventos do tipo de nota Prova Semanal no bimestre marcado na peça. O título do evento (Avaliação Semanal) não define o tipo.\n\n"
                . "No passo das peças, marque Prova Semanal e o bimestre. Cada evento entra com a data, o bloco e a semana (S1, S2…). Bloco A fica nas semanas ímpares e Bloco B nas pares.\n\n"
                . "Se faltar evento, confira em Lançamento de Notas se o tipo é Prova Semanal e se o bimestre é o mesmo da peça.";
        }
        if (preg_match('/m[eé]dia|calcular|f[oó]rmula|formula|dividir/u', $t)) {
            return "A média desta tela é a soma das peças entre parênteses, dividida pela quantidade.\n\n"
                . "Exemplo: ( Prova Semanal + Avaliação Bimestral + Jornada do aluno ) ÷ 3\n\n"
                . "Monte na coluna amarela (Média Bim): clique nas peças, no +, nos parênteses e no ÷. Depois Salvar bloco. A ordem das colunas se arrasta pelo ⋮⋮. A coluna calculada precisa estar depois das peças que ela usa.";
        }
        if (preg_match('/jornada/u', $t)) {
            return "A Jornada do aluno entra como peça. Marque o bimestre dela. O sistema usa as jornadas ativas daquele bimestre, não só as mais novas.\n\n"
                . "Se a peça mostrar zero, a jornada está sem o campo Bimestre preenchido no cadastro.";
        }
        if (preg_match('/tipo de nota|peça|peca|bimestral/u', $t)) {
            return "Cada peça é um tipo de nota ativo (Prova Semanal, Avaliação Bimestral, ENAC, Jornada…). O nome do evento não vira tipo.\n\n"
                . "Marque a peça, escolha o bimestre e, se quiser, os eventos que entram. O fechamento (média, acertos/questões) vem do tipo de nota, em Avaliações → Tipos de nota.";
        }
        if (preg_match('/pr[eé]via|simular|aluno na tela|fict/u', $t)) {
            return "Sem aluno, a tabela de baixo é um exemplo. Com um aluno escolhido, a tabela passa a usar as notas lançadas dele neste evento.\n\n"
                . "A faixa verde confirma que são notas reais. Se não houver lançamento no período, a tela avisa em vez de inventar número.";
        }
        if (preg_match('/como|criar|montar|boletim|evento de nota/u', $t)) {
            return "Nesta tela o evento de notas se monta em etapas:\n\n"
                . "1. Identidade: nome, ano e para quem vale.\n"
                . "2. Peças: marque os tipos de nota e o bimestre de cada uma.\n"
                . "3. Exibir: arraste a ordem das colunas e monte a fórmula de cada bloco amarelo. Salvar bloco, depois Salvar e continuar.\n\n"
                . "A média é (peças) ÷ quantidade. Para o ENAC só substituir quando for maior, use maior ( Média Bim , ENAC ).";
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $wizardEstado
     */
    private function montarSystemPrompt(?array $wizardEstado): string
    {
        $resumo = $this->resumoEvento($wizardEstado);

        return <<<PROMPT
Você é o assistente da tela Evento de Notas do EducaTudo. Responda em português, curto e direto.

Manual desta tela (não invente botão ou regra fora disto):
- Peça = tipo de nota (Prova Semanal, Avaliação Bimestral, ENAC, Jornada do aluno…). O título do evento não define o tipo.
- Prova Semanal mostra os eventos daquele tipo no bimestre marcado. Semanas S1–S8: Bloco A ímpares, Bloco B pares.
- Média = soma entre parênteses ÷ quantidade. Ex.: (Prova Semanal + Avaliação Bimestral + Jornada) ÷ 3.
- Para ficar com a média e só trocar se o ENAC for maior: maior ( Média Bim , ENAC ). Não use "Maior entre as duas primeiras".
- Jornada entra pelo bimestre cadastrado na jornada.
- Sem aluno na prévia, os números são exemplo. Com aluno, são as notas lançadas.

Número de nota, quantidade de jornada ou lançamento SÓ pode vir de uma consulta. Se não consultou, não chute.

Para consultar, responda APENAS este bloco (sem texto antes):
<<<CONSULTA>>>
{"tool":"nome","args":{}}
<<<FIM>>>

Tools:
- buscar_aluno: args aluno_nome, turma (opcional). Use se houver mais de um candidato.
- jornadas_aluno: args aluno_nome ou aluno_id, bimestre (1-4, opcional), materia_nome (opcional). Quantas jornadas fez.
- lancamentos_aluno: args aluno_nome ou aluno_id, materia_nome, tipo (ex.: Avaliação Bimestral, Prova Semanal), bimestre. Nota lançada na prova.
- nota_no_evento: args aluno_nome ou aluno_id, materia_nome. Nota já calculada nas colunas DESTE evento aberto na tela.

Evento aberto agora:
{$resumo}
PROMPT;
    }

    /**
     * @param array<string,mixed>|null $estado
     */
    private function resumoEvento(?array $estado): string
    {
        if (!is_array($estado)) {
            return 'Nenhum evento carregado.';
        }
        $nome = trim((string) ($estado['nome'] ?? ''));
        $pecas = [];
        foreach ((array) ($estado['pecas'] ?? []) as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $pecas[] = $p;
            }
        }
        $formulas = [];
        $nomes = is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [];
        foreach ((array) ($estado['formulas_blocos'] ?? []) as $cod => $tokens) {
            $rotulo = trim((string) ($nomes[$cod] ?? $cod));
            $texto = $this->formulaTexto($tokens);
            if ($rotulo !== '' && $texto !== '') {
                $formulas[] = $rotulo . ' = ' . $texto;
            }
        }
        $alunoPreview = (int) ($estado['aluno_preview_id'] ?? 0);
        $linhas = [];
        $linhas[] = 'Nome: ' . ($nome !== '' ? $nome : '(sem nome)');
        $linhas[] = 'Ano: ' . (int) ($estado['ano_letivo'] ?? 0) . ' · bimestre do evento: ' . (int) ($estado['bimestre'] ?? 0);
        $linhas[] = 'Peças: ' . ($pecas !== [] ? implode(', ', $pecas) : '(nenhuma)');
        $linhas[] = 'Fórmulas: ' . ($formulas !== [] ? implode(' | ', $formulas) : '(nenhuma salva no rascunho)');
        if ($alunoPreview > 0) {
            $linhas[] = 'Aluno selecionado na prévia: id ' . $alunoPreview . ' (pode usar aluno_id direto).';
        }

        return implode("\n", $linhas);
    }

    /**
     * @param mixed $tokens
     */
    private function formulaTexto($tokens): string
    {
        if (!is_array($tokens)) {
            return trim((string) $tokens);
        }
        $partes = [];
        foreach ($tokens as $tok) {
            if (!is_array($tok)) {
                continue;
            }
            $label = trim((string) ($tok['label'] ?? $tok['value'] ?? ''));
            if ($label !== '') {
                $partes[] = $label;
            }
        }

        return implode(' ', $partes);
    }

    /**
     * @return array{tool:string,args:array<string,mixed>}|null
     */
    private function extrairConsulta(string $resposta): ?array
    {
        $ini = strpos($resposta, self::DELIMITADOR_INICIO);
        if ($ini === false) {
            return null;
        }
        $depois = substr($resposta, $ini + strlen(self::DELIMITADOR_INICIO));
        $fim = strpos($depois, self::DELIMITADOR_FIM);
        $jsonRaw = trim($fim === false ? $depois : substr($depois, 0, $fim));
        $decoded = json_decode($jsonRaw, true);
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
        $pos = strpos($resposta, self::DELIMITADOR_INICIO);
        if ($pos !== false) {
            $resposta = substr($resposta, 0, $pos);
        }

        return trim($resposta);
    }

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed>|null $wizardEstado
     * @return array<string,mixed>
     */
    private function executarTool(string $tool, array $args, ?array $wizardEstado): array
    {
        try {
            return match ($tool) {
                'buscar_aluno' => $this->buscarAluno($args),
                'jornadas_aluno' => $this->jornadas->resumoJornadasAluno($this->filtrosAluno($args)),
                'lancamentos_aluno' => $this->lancamentosAluno($args),
                'nota_no_evento' => $this->notaNoEvento($args, $wizardEstado),
                default => ['ok' => false, 'error' => 'Consulta desconhecida.'],
            };
        } catch (Throwable $e) {
            error_log('BoletimConsultaAssistente tool ' . $tool . ': ' . $e->getMessage());

            return ['ok' => false, 'error' => 'A consulta falhou.'];
        }
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private function filtrosAluno(array $args): array
    {
        $bimestre = (int) ($args['bimestre'] ?? 0);

        return [
            'aluno_id' => (int) ($args['aluno_id'] ?? 0),
            'aluno_nome' => trim((string) ($args['aluno_nome'] ?? $args['nome'] ?? '')),
            'turma' => trim((string) ($args['turma'] ?? '')),
            'turma_nome' => trim((string) ($args['turma'] ?? '')),
            'materia_nome' => trim((string) ($args['materia_nome'] ?? $args['materia'] ?? '')),
            'bimestre' => ($bimestre >= 1 && $bimestre <= 4) ? $bimestre : 0,
            'limite' => 40,
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private function buscarAluno(array $args): array
    {
        $nome = trim((string) ($args['aluno_nome'] ?? $args['nome'] ?? ''));
        $turma = trim((string) ($args['turma'] ?? ''));
        $lista = $this->provas->buscarAlunos($nome, 8, $turma !== '' ? $turma : null);
        if ($lista === []) {
            return ['ok' => false, 'error' => 'Nenhum aluno encontrado.'];
        }

        return ['ok' => true, 'alunos' => $lista];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private function lancamentosAluno(array $args): array
    {
        $filtros = $this->filtrosAluno($args);
        $tipo = trim((string) ($args['tipo'] ?? $args['tipo_avaliacao_nome'] ?? ''));
        if ($tipo !== '') {
            $filtros['tipo_avaliacao_nome'] = $tipo;
        }
        $lista = $this->provas->listarProvasAluno($filtros);
        if (empty($lista['ok'])) {
            return $lista;
        }
        if (!empty($lista['candidatos'])) {
            return $lista;
        }

        $itens = [];
        foreach (array_slice((array) ($lista['provas'] ?? []), 0, 20) as $prova) {
            if (!is_array($prova)) {
                continue;
            }
            $real = is_array($prova['realizacao'] ?? null) ? $prova['realizacao'] : [];
            $evento = is_array($prova['evento'] ?? null) ? $prova['evento'] : [];
            $materia = is_array($prova['materia'] ?? null) ? $prova['materia'] : [];
            $tipoAv = is_array($prova['tipo_avaliacao'] ?? null) ? $prova['tipo_avaliacao'] : [];
            $itens[] = [
                'titulo' => (string) ($prova['titulo'] ?? ''),
                'evento' => (string) ($evento['titulo'] ?? ''),
                'data' => (string) ($evento['data_prova'] ?? ''),
                'bimestre' => $evento['bimestre'] ?? null,
                'materia' => (string) ($materia['nome'] ?? ''),
                'tipo' => (string) ($tipoAv['nome'] ?? ''),
                'nota' => $real['nota'] ?? null,
                'acertos' => $real['acertos'] ?? null,
                'questoes' => $real['total_questoes'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'aluno' => $lista['aluno'] ?? null,
            'total' => (int) ($lista['total'] ?? count($itens)),
            'lancamentos' => $itens,
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed>|null $wizardEstado
     * @return array<string,mixed>
     */
    private function notaNoEvento(array $args, ?array $wizardEstado): array
    {
        $aluno = $this->resolverAlunoUnico($args);
        if (empty($aluno['ok'])) {
            return $aluno;
        }
        if (!empty($aluno['candidatos'])) {
            return $aluno;
        }
        $alunoId = (int) ($aluno['aluno']['id'] ?? 0);
        if (!is_array($wizardEstado)) {
            return ['ok' => false, 'error' => 'Nenhum evento aberto nesta tela.'];
        }

        $montado = $this->wizard->montar($wizardEstado);
        $rascunho = is_array($montado['rascunho'] ?? null) ? $montado['rascunho'] : null;
        if ($rascunho === null || empty($rascunho['componentes'])) {
            return ['ok' => false, 'error' => 'O evento ainda não tem peças para calcular.'];
        }
        $rascunho = $this->completarRascunhoSalvo($rascunho, $wizardEstado);
        [$periodoRef, $inicio, $fim] = $this->periodoDoRascunho($rascunho, $wizardEstado);

        $simulacao = (new BoletimConfigController(true))->simularRegraAluno(
            $rascunho,
            $alunoId,
            $periodoRef,
            $inicio,
            $fim
        );
        $matriz = is_array($simulacao['matriz_materias'] ?? null) ? $simulacao['matriz_materias'] : [];
        $linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
        $colunas = [];
        foreach ((array) ($matriz['colunas'] ?? []) as $col) {
            if (!is_array($col)) {
                continue;
            }
            $cod = strtolower(trim((string) ($col['codigo'] ?? '')));
            if ($cod !== '') {
                $colunas[$cod] = (string) ($col['nome'] ?? $cod);
            }
        }

        $alvo = $this->normalizarTexto((string) ($args['materia_nome'] ?? $args['materia'] ?? ''));
        if ($alvo === '') {
            $nomesSemFiltro = [];
            foreach ($linhas as $linha) {
                if (!is_array($linha)) {
                    continue;
                }
                $nomeMat = trim((string) ($linha['materia_nome'] ?? ''));
                if ($nomeMat !== '') {
                    $nomesSemFiltro[] = $nomeMat;
                }
            }

            return [
                'ok' => false,
                'error' => 'Informe a matéria.',
                'aluno' => $aluno['aluno'],
                'materias' => array_values(array_unique($nomesSemFiltro)),
            ];
        }
        $achadas = [];
        $nomes = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $nomeMat = trim((string) ($linha['materia_nome'] ?? ''));
            if ($nomeMat === '') {
                continue;
            }
            $nomes[] = $nomeMat;
            if ($alvo !== '' && !str_contains($this->normalizarTexto($nomeMat), $alvo)) {
                continue;
            }
            $notas = [];
            foreach ((array) ($linha['notas'] ?? []) as $cod => $valor) {
                $cod = strtolower(trim((string) $cod));
                if ($cod === '' || str_contains($cod, '__')) {
                    continue;
                }
                $notas[] = [
                    'coluna' => $colunas[$cod] ?? $cod,
                    'valor' => $valor,
                ];
            }
            $achadas[] = ['materia' => $nomeMat, 'notas' => $notas];
        }

        if ($achadas === []) {
            return [
                'ok' => false,
                'error' => 'Matéria não encontrada neste evento.',
                'aluno' => $aluno['aluno'],
                'materias' => array_values(array_unique($nomes)),
            ];
        }

        return [
            'ok' => true,
            'aluno' => $aluno['aluno'],
            'evento' => trim((string) ($rascunho['nome'] ?? $wizardEstado['nome'] ?? '')),
            'linhas' => $achadas,
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private function resolverAlunoUnico(array $args): array
    {
        $alunoId = (int) ($args['aluno_id'] ?? 0);
        if ($alunoId > 0) {
            return ['ok' => true, 'aluno' => ['id' => $alunoId, 'nome' => trim((string) ($args['aluno_nome'] ?? ''))]];
        }
        $nome = trim((string) ($args['aluno_nome'] ?? $args['nome'] ?? ''));
        $turma = trim((string) ($args['turma'] ?? ''));
        $lista = $this->provas->buscarAlunos($nome, 8, $turma !== '' ? $turma : null);
        if ($lista === []) {
            return ['ok' => false, 'error' => 'Nenhum aluno encontrado.'];
        }
        if (count($lista) > 1) {
            return [
                'ok' => true,
                'aviso' => 'Há mais de um aluno. Informe aluno_id.',
                'candidatos' => $lista,
            ];
        }

        return ['ok' => true, 'aluno' => $lista[0]];
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function completarRascunhoSalvo(array $rascunho, array $estado): array
    {
        $regraId = (int) ($rascunho['id'] ?? 0);
        if ($regraId <= 0) {
            $regraId = (int) ($rascunho['regra_id'] ?? $estado['regra_id'] ?? 0);
        }
        if ($regraId <= 0) {
            return $rascunho;
        }
        $rascunho['id'] = $regraId;
        $salva = $this->ferramentas->obterRegra($regraId);
        if (!is_array($salva) || empty($salva['componentes']) || !is_array($salva['componentes'])) {
            return $rascunho;
        }
        $salvas = [];
        foreach ($salva['componentes'] as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $cod = strtolower(trim((string) ($comp['codigo'] ?? '')));
            if ($cod !== '') {
                $salvas[$cod] = $comp;
            }
        }
        $componentes = [];
        foreach ((array) ($rascunho['componentes'] ?? []) as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $cod = strtolower(trim((string) ($comp['codigo'] ?? '')));
            $origem = strtolower(trim((string) ($comp['source_type'] ?? '')));
            if ($origem !== 'calculado' && isset($salvas[$cod])) {
                $nome = trim((string) ($comp['nome'] ?? ''));
                $comp = $salvas[$cod];
                if ($nome !== '') {
                    $comp['nome'] = $nome;
                }
            }
            $componentes[] = $comp;
        }
        $rascunho['componentes'] = $componentes;

        return $rascunho;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array{0:string,1:?string,2:?string}
     */
    private function periodoDoRascunho(array $rascunho, array $estado): array
    {
        $inicio = $this->dataYmd((string) ($rascunho['default_data_inicio'] ?? $estado['data_inicio'] ?? ''));
        $fim = $this->dataYmd((string) ($rascunho['default_data_fim'] ?? $estado['data_fim'] ?? ''));
        if ($inicio !== null && $fim !== null) {
            if ($inicio > $fim) {
                [$inicio, $fim] = [$fim, $inicio];
            }

            return ['RANGE:' . $inicio . ':' . $fim, $inicio, $fim];
        }
        $ano = (int) ($rascunho['ano_letivo'] ?? $estado['ano_letivo'] ?? date('Y'));
        $bimestre = (int) ($rascunho['bimestre'] ?? $estado['bimestre'] ?? 1);
        if ($ano <= 0) {
            $ano = (int) date('Y');
        }
        if ($bimestre < 1 || $bimestre > 4) {
            $bimestre = 1;
        }

        return [$ano . '-B' . $bimestre, null, null];
    }

    private function dataYmd(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        $ts = strtotime($raw);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = str_replace(
            ['á', 'à', 'â', 'ã', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'],
            ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'],
            $texto
        );

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }

    private function assertCreditosDisponiveis(): void
    {
        if (!CreditosModuleRegistry::isValid(BoletimAssistenteService::MODULO_CREDITOS)) {
            return;
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            throw new Exception('TudiCoins desabilitado para esta escola. A consulta de notas com IA não está disponível.');
        }
        if (!$creditos->podeConsumir(
            'escola',
            \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID,
            BoletimAssistenteService::MODULO_CREDITOS
        )) {
            throw new Exception('TudiCoins insuficientes na carteira da escola.');
        }
    }

    private function debitarCreditos(): void
    {
        if (!CreditosModuleRegistry::isValid(BoletimAssistenteService::MODULO_CREDITOS)) {
            return;
        }
        $creditos = new \App\Services\CreditosService();
        $creditos->consumirEscola(
            BoletimAssistenteService::MODULO_CREDITOS,
            'boletim_consulta_assistente'
        );
    }
}
