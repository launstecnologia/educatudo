<?php

namespace App\Modulos\Boletins\Services;
/**
 * Assistente da tela Evento de Notas.
 * Explica o cálculo com o manual desta tela e só cita nota, lançamento ou jornada
 * depois de consultar o banco. Não inventa número.
 */


use App\Controllers\Admin\BoletimConfigController;
use App\Core\CreditosModuleRegistry;
use App\Modulos\Jornadas\Services\JornadasAlunoConsultaService;
use App\Modulos\Provas\Services\ProvasAlunoConsultaService;

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
    public function processarMensagem(string $mensagem, array $historico = [], ?array $wizardEstado = null, ?string $imagem = null): array
    {
        $mensagem = trim($mensagem);
        $imagem = $this->normalizarImagemPrint($imagem);
        if ($imagem === false) {
            return ['success' => false, 'error' => 'O print precisa ser uma imagem (PNG ou JPG).'];
        }
        if ($mensagem === '' && $imagem === null) {
            return ['success' => false, 'error' => 'Escreva a pergunta ou cole um print.'];
        }
        if ($this->pedidoEhMediaComEnac($mensagem)) {
            return ['success' => true, 'mensagem' => $this->textoMediaComEnac()];
        }
        if ($this->pedidoEhMediaPonderada($mensagem)) {
            return ['success' => true, 'mensagem' => $this->textoMediaPonderadaComChEPj($mensagem, $wizardEstado)];
        }
        if ($this->pedidoEhMediaComChEPj($mensagem)) {
            return ['success' => true, 'mensagem' => $this->textoMediaComChEPj($mensagem, $wizardEstado)];
        }
        // Follow-up curto ("Se tiver PJ…") sozinho não tem PP/PU/CH — junta só o pedido anterior.
        $contextoFormula = $this->contextoPedidoFormula($mensagem, $historico);
        if ($contextoFormula !== $mensagem) {
            if ($this->pedidoEhMediaPonderada($contextoFormula)) {
                return ['success' => true, 'mensagem' => $this->textoMediaPonderadaComChEPj($contextoFormula, $wizardEstado)];
            }
            if ($this->pedidoEhMediaComChEPj($contextoFormula)) {
                return ['success' => true, 'mensagem' => $this->textoMediaComChEPj($contextoFormula, $wizardEstado)];
            }
        }
        $metrica = $this->textoExplicacaoMetricaFormula($mensagem);
        if ($metrica !== null) {
            return ['success' => true, 'mensagem' => $metrica];
        }
        if ($this->parecePerguntaJornadaDeMateria($mensagem)) {
            $textoJornada = $this->textoJornadaNasMaterias($mensagem, $wizardEstado);
            if ($textoJornada !== null) {
                return ['success' => true, 'mensagem' => $textoJornada];
            }
        }
        if ($imagem !== null) {
            return $this->analisarPrint($mensagem, $imagem, $wizardEstado);
        }

        if ($this->pareceConferencia($mensagem)) {
            return ['success' => true, 'mensagem' => $this->textoConferencia($wizardEstado)];
        }
        if ($this->pareceDiagnostico($mensagem)) {
            return ['success' => true, 'mensagem' => $this->textoDiagnostico($mensagem, $wizardEstado)];
        }

        // Pedido de fórmula/conversa de montagem vai para a IA (entende e pergunta se faltar algo).
        if (!$this->parecePerguntaDeDado($mensagem) && !$this->parecePedidoDeFormula($mensagem)) {
            $guia = $this->responderGuia($mensagem, $wizardEstado);
            if ($guia !== null) {
                return ['success' => true, 'mensagem' => $guia];
            }
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
                if (
                    $tool === 'lancamentos_aluno'
                    && !empty($resultado['ok'])
                    && empty($resultado['candidatos'])
                    && !empty($resultado['resumo_para_responder'])
                ) {
                    $textoFinal = trim((string) $resultado['resumo_para_responder']);
                    break;
                }
                $mensagens[] = ['role' => 'assistant', 'content' => $resposta];
                $instrucao = "Resultado de {$tool} (fonte do sistema, não invente além disto):\n"
                    . json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    . "\n\nResponda em português, curto, só com o que veio neste JSON. "
                    . 'Se houver candidatos, peça para escolher. Se faltar o número, diga que não encontrou. '
                    . 'Não emita outra consulta se já deu para responder.';
                if ($tool === 'lancamentos_aluno' && !empty($resultado['resumo_para_responder'])) {
                    $instrucao .= "\n\nUse o campo resumo_para_responder quase como está. "
                        . 'Quando houver mais de um professor, diga claramente: \Professor Fulano: nota X,00 · Professor Cicrano: nota Y,00. '
                        . 'Não junte as notas num único número sem dizer de quem é cada uma.';
                }
                $mensagens[] = [
                    'role' => 'user',
                    'content' => $instrucao,
                ];
            }
        } catch (\Throwable $e) {
            error_log('BoletimConsultaAssistenteService: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não deu para consultar agora. Tente de novo.'];
        }

        if ($textoFinal === null || $textoFinal === '') {
            if ($this->parecePerguntaDeDado($mensagem) && !$this->parecePedidoDeFormula($mensagem)) {
                $textoFinal = 'Consultei o sistema, mas não fechou uma resposta. Diga o nome do aluno e a matéria.';
            } elseif ($this->parecePedidoDeFormula($mensagem)) {
                $textoFinal = 'Não fechei a fórmula. Me diga quais colunas entram, se tem substituta (CH), se tem Projeto/opcional e se PE soma fora.';
            } else {
                $textoFinal = 'Não entendi bem. Pode repetir com mais detalhe?';
            }
        }

        return ['success' => true, 'mensagem' => $this->corrigirFormulaPipeErrada($textoFinal)];
    }

    private function parecePerguntaDeDado(string $mensagem): bool
    {
        return (bool) preg_match(
            '/\b(nota|notas|jornada|jornadas|quantas|quanto|quais|lançamento|lancamento|aluno|aluna|fez|tirou|acertou|bimestral|semanal|enac|avalia[cç][aã]o|mat[eé]ria|professor|no caso|na verdade|quero saber)\b/ui',
            $mensagem
        );
    }

    /** Conversa livre para montar/entender fórmula — não usar texto fixo do guia. */
    private function parecePedidoDeFormula(string $mensagem): bool
    {
        return (bool) preg_match(
            '/\b(f[oó]rmula|formula|m[eé]dia|calcular|calcula|monta|montar|monte|divid|substitut|opcional|projeto|\bpp\b|\bpu\b|\bch\b|\bpj\b|\bpe\b|se\s+lan[cç]|se\s*\(|media_lancado|conta|express[aã]o|resultado final|m[eé]dia bim)\b/ui',
            $mensagem
        );
    }

    private function pareceDiagnostico(string $mensagem): bool
    {
        return (bool) preg_match(
            '/por\s*qu[eê]|tra[cç]o|vazi|n[aã]o aparece|nao aparece|sumiu|em branco|zerad/ui',
            $mensagem
        );
    }

    private function pareceConferencia(string $mensagem): bool
    {
        return (bool) preg_match(
            '/o que falta|antes de salvar|conferir (o )?evento|checklist|t[aá] pronto|est[aá] pronto/ui',
            $mensagem
        );
    }

    /**
     * @param array<string,mixed>|null $estado
     */
    private function textoConferencia(?array $estado): string
    {
        if (!is_array($estado)) {
            return 'Abra o evento em Configurar Notas para eu conferir as peças e a fórmula.';
        }
        $avisos = [];
        $pecas = [];
        foreach ((array) ($estado['pecas'] ?? []) as $peca) {
            $peca = trim((string) $peca);
            if ($peca !== '') {
                $pecas[] = $peca;
            }
        }
        if ($pecas === []) {
            $avisos[] = 'Nenhuma peça marcada. Em Peças, marque os tipos de nota deste bimestre.';
        }
        $opcoes = is_array($estado['pecas_opcoes'] ?? null) ? $estado['pecas_opcoes'] : [];
        foreach ($pecas as $peca) {
            if ($peca === 'jornada') {
                continue;
            }
            $opt = is_array($opcoes[$peca] ?? null) ? $opcoes[$peca] : [];
            $bims = array_filter(array_map('intval', (array) ($opt['bimestres'] ?? [])));
            if ($bims === []) {
                $avisos[] = 'A peça ' . $peca . ' está sem bimestre. Em Peças, marque o bimestre dela.';
            }
        }
        if (in_array('jornada', $pecas, true)) {
            $bimsJ = array_filter(array_map('intval', (array) ($estado['jornada_bimestres'] ?? [])));
            $idsJ = is_array($estado['jornada_ids'] ?? null) ? $estado['jornada_ids'] : [];
            if ($bimsJ === [] && $idsJ === []) {
                $avisos[] = 'A Jornada do aluno está sem bimestre. Em Peças, marque o bimestre da jornada.';
            }
            $formulaTexto = '';
            foreach ((array) ($estado['formulas_blocos'] ?? []) as $tokens) {
                $formulaTexto .= ' ' . $this->formulaTexto($tokens);
            }
            $formulaTexto .= ' ' . $this->formulaTexto($estado['formula_tokens'] ?? []);
            if (!str_contains($this->normalizarTexto($formulaTexto), 'jornada')) {
                $avisos[] = 'A jornada está marcada, mas não entra na fórmula. Em Exibir, coloque Jornada do aluno na média.';
            }
        }
        if ((int) ($estado['aluno_preview_id'] ?? 0) <= 0) {
            $avisos[] = 'Nenhum aluno na prévia. Em Revisar, escolha um aluno para ver as notas reais. Sem aluno, a tabela é exemplo.';
        }
        if ($avisos === []) {
            return 'Este evento está com peças, bimestre e fórmula. Em Revisar, escolha um aluno e confira se alguma coluna ficou com traço.';
        }

        return "Antes de salvar, ajuste isto:\n\n- " . implode("\n- ", $avisos);
    }

    private function textoDiagnostico(string $mensagem, ?array $estado): string
    {
        $nomePedido = $this->extrairNomeAluno($mensagem);
        $diag = $this->diagnosticarCelula([
            'aluno_nome' => $nomePedido,
            'materia_nome' => $this->extrairMateria($mensagem),
            'coluna' => $this->extrairColuna($mensagem),
            'aluno_id' => ($nomePedido === '' && is_array($estado)) ? (int) ($estado['aluno_preview_id'] ?? 0) : 0,
        ], $estado);
        if (!empty($diag['candidatos']) && is_array($diag['candidatos'])) {
            $nomes = [];
            foreach ($diag['candidatos'] as $cand) {
                if (is_array($cand)) {
                    $nomes[] = trim((string) ($cand['nome'] ?? ''));
                }
            }
            $nomes = array_values(array_filter($nomes));

            return 'Há mais de um aluno com esse nome: ' . implode(', ', $nomes) . '. Diga o nome completo.';
        }
        $texto = trim((string) ($diag['texto'] ?? $diag['error'] ?? ''));

        return $texto !== '' ? $texto : 'Não consegui ver essa célula. Diga o aluno, a matéria e a coluna.';
    }

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed>|null $wizardEstado
     * @return array<string,mixed>
     */
    private function diagnosticarCelula(array $args, ?array $wizardEstado): array
    {
        if (!is_array($wizardEstado)) {
            return [
                'ok' => false,
                'texto' => 'Abra o evento em Configurar Notas para eu comparar a célula com as peças.',
            ];
        }
        if (trim((string) ($args['aluno_nome'] ?? '')) === '' && (int) ($args['aluno_id'] ?? 0) <= 0) {
            return [
                'ok' => false,
                'texto' => 'A prévia está sem aluno, então a tabela é exemplo e o traço não é a nota real. Em Revisar, escolha o aluno e pergunte de novo.',
            ];
        }
        $aluno = $this->resolverAlunoUnico($args);
        if (empty($aluno['ok'])) {
            return ['ok' => false, 'texto' => (string) ($aluno['error'] ?? 'Aluno não encontrado.')];
        }
        if (!empty($aluno['candidatos'])) {
            return $aluno;
        }
        $alunoId = (int) ($aluno['aluno']['id'] ?? 0);
        $nomeAluno = trim((string) ($aluno['aluno']['nome'] ?? ''));
        if ($nomeAluno === '' && $alunoId > 0) {
            $completo = $this->provas->obterAluno($alunoId);
            $nomeAluno = is_array($completo) ? trim((string) ($completo['nome'] ?? '')) : '';
        }
        if ($nomeAluno === '') {
            $nomeAluno = 'Aluno';
        }
        try {
            $simulacao = $this->simularEventoAberto($wizardEstado, $alunoId);
        } catch (\Throwable $e) {
            error_log('BoletimConsultaAssistente diagnostico: ' . $e->getMessage());

            return ['ok' => false, 'texto' => 'Não deu para calcular este aluno agora.'];
        }
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
        $colunaAlvo = $this->normalizarTexto((string) ($args['coluna'] ?? ''));
        $materiaAlvo = $this->normalizarTexto((string) ($args['materia_nome'] ?? ''));
        $codigosColuna = $this->codigosDaColuna($colunas, $colunaAlvo);
        if ($codigosColuna === []) {
            $codigosColuna = array_keys($colunas);
        }
        $vazias = [];
        $preenchidas = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $nomeMat = trim((string) ($linha['materia_nome'] ?? ''));
            if ($nomeMat === '') {
                continue;
            }
            if ($materiaAlvo !== '' && !str_contains($this->normalizarTexto($nomeMat), $materiaAlvo)) {
                continue;
            }
            $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
            foreach ($codigosColuna as $cod) {
                $valor = $notas[$cod] ?? null;
                $rotulo = ($colunas[$cod] ?? $cod) . ' em ' . $nomeMat;
                if ($this->celulaVazia($valor)) {
                    $vazias[] = $rotulo;
                } else {
                    $preenchidas[] = $rotulo . ' = ' . $this->formatarValorCelula($valor);
                }
            }
        }
        if ($vazias === [] && $preenchidas === []) {
            return [
                'ok' => false,
                'texto' => 'Não achei essa matéria na simulação de ' . $nomeAluno . '. Diga o nome como está na tabela.',
            ];
        }
        $causa = $this->causaCelulaVazia($simulacao, $colunaAlvo, $wizardEstado);
        if ($vazias === []) {
            $amostra = implode('; ', array_slice($preenchidas, 0, 6));

            return [
                'ok' => true,
                'texto' => 'Para ' . $nomeAluno . ' essa célula tem nota: ' . $amostra . '.',
            ];
        }
        $lista = implode(', ', array_slice($vazias, 0, 8));
        $extra = count($vazias) > 8 ? ' e mais ' . (count($vazias) - 8) : '';
        $texto = 'Para ' . $nomeAluno . ', está vazio em: ' . $lista . $extra . '.';
        if ($preenchidas !== []) {
            $texto .= ' Com nota: ' . implode('; ', array_slice($preenchidas, 0, 4)) . '.';
        }
        $texto .= "\n\n" . $causa;

        return ['ok' => true, 'texto' => $texto, 'vazias' => $vazias, 'preenchidas' => $preenchidas];
    }

    /**
     * @param array<string,mixed>|null $wizardEstado
     */
    private function responderGuia(string $mensagem, ?array $wizardEstado = null): ?string
    {
        if ($this->pedidoEhMediaComEnac($mensagem)) {
            return $this->textoMediaComEnac();
        }
        $t = mb_strtolower($mensagem);
        if ($this->pedidoEhMediaComChEPj($mensagem)) {
            return $this->textoMediaComChEPj($mensagem, $wizardEstado);
        }
        if (preg_match('/semanal|s1|semana/u', $t) && !preg_match('/\b(aluno|aluna|nota|tirou|fez|lançamento|lancamento|f[oó]rmula|formula|m[eé]dia)\b/u', $t)) {
            return "A Prova Semanal lista os eventos do tipo de nota Prova Semanal no bimestre marcado na peça. O título do evento (Avaliação Semanal) não define o tipo.\n\n"
                . "No passo das peças, marque Prova Semanal e o bimestre. Cada evento entra com a data, o bloco e a semana (S1, S2…). Bloco A fica nas semanas ímpares e Bloco B nas pares.\n\n"
                . "Se faltar evento, confira em Lançamento de Notas se o tipo é Prova Semanal e se o bimestre é o mesmo da peça.";
        }
        if (preg_match('/\bjornada\b/u', $t) && !preg_match('/f[oó]rmula|formula|m[eé]dia|monta/u', $t)) {
            return "A Jornada do aluno entra como peça. Marque o bimestre dela. O sistema usa as jornadas ativas daquele bimestre, não só as mais novas.\n\n"
                . "Se a peça mostrar zero, a jornada está sem o campo Bimestre preenchido no cadastro.";
        }
        if (preg_match('/tipo de nota|pe[cç]a\b|como (marcar|montar) (a )?pe[cç]a/u', $t)) {
            return "Cada peça é um tipo de nota ativo (Prova Semanal, Avaliação Bimestral, ENAC, Jornada…). O nome do evento não vira tipo.\n\n"
                . "Marque a peça, escolha o bimestre e, se quiser, os eventos que entram. O fechamento (média, acertos/questões) vem do tipo de nota, em Avaliações → Tipos de nota.";
        }
        if (preg_match('/pr[eé]via|simular|aluno na tela|fict/u', $t)) {
            return "Sem aluno, a tabela de baixo é um exemplo. Com um aluno escolhido, a tabela passa a usar as notas lançadas dele neste evento.\n\n"
                . "A faixa verde confirma que são notas reais. Se não houver lançamento no período, a tela avisa em vez de inventar número.";
        }
        if (preg_match('/\b(como criar|como funciona|o que (e|é) (este|esse) evento|passos do boletim)\b/u', $t)) {
            return "Nesta tela o evento de notas se monta em etapas:\n\n"
                . "1. Identidade: nome, ano e para quem vale.\n"
                . "2. Peças: marque os tipos de nota e o bimestre de cada uma.\n"
                . "3. Exibir: arraste a ordem das colunas e monte a fórmula de cada bloco amarelo. Salvar bloco, depois Salvar e continuar.\n\n"
                . "Para montar uma fórmula, descreva a regra em português aqui no assistente.";
        }

        return null;
    }

    /**
     * Só para follow-up curto de fórmula: junta o último pedido completo do user.
     * Não usa histórico em pergunta qualquer (evita reaplicar o template).
     *
     * @param list<array{role?:string,content?:string}> $historico
     */
    private function contextoPedidoFormula(string $mensagem, array $historico = []): string
    {
        $t = $this->normalizarTexto($mensagem);
        $pareceRefino = mb_strlen(trim($mensagem)) <= 180
            && preg_match(
                '/se tiver|caso tenha|divid\w*\s+por\s*[23]|\/\s*[23]|add\b|adicionar|somar|\+\s*pe|opcional|substitut|corrija|ainda|faltou|inclua|monte/u',
                $t
            ) === 1;
        if (!$pareceRefino) {
            return $mensagem;
        }

        $anterior = '';
        foreach (array_reverse($historico) as $msg) {
            if (($msg['role'] ?? '') !== 'user') {
                continue;
            }
            $c = trim((string) ($msg['content'] ?? ''));
            if ($c === '' || $c === $mensagem || mb_strlen($c) < 20) {
                continue;
            }
            if (preg_match('/\b(pp|pu|pj|ch|pe|peso|m[eé]dia|divid|substitut|f[oó]rmula|formula)\b/ui', $c) !== 1) {
                continue;
            }
            $anterior = mb_substr($c, 0, 1500);
            break;
        }
        if ($anterior === '') {
            return $mensagem;
        }

        return trim($anterior . "\n" . $mensagem);
    }

    /**
     * (Média Bim + ENAC) ÷ 2, e só usa esse resultado se for maior que a Média Bim.
     */
    private function pedidoEhMediaComEnac(string $mensagem): bool
    {
        $t = $this->normalizarTexto($mensagem);
        if (!str_contains($t, 'enac')) {
            return false;
        }
        $divide = preg_match('/divid|\/\s*2|por 2|÷/u', $t) === 1;
        $escolheOMaior = preg_match('/maior|mantem|manter|se nao|senao/u', $t) === 1;

        return $divide && $escolheOMaior;
    }

    private function textoMediaComEnac(): string
    {
        return "O Resultado Final fica com o maior destes dois valores:\n\n"
            . "• a Média Bim\n"
            . "• a média da Média Bim com o ENAC: (Média Bim + ENAC) ÷ 2\n\n"
            . "Se essa conta der maior que a Média Bim, ela entra. Se der menor, a coluna continua com a Média Bim. O ENAC não baixa a nota.\n\n"
            . "Exemplo: Média Bim 6 e ENAC 10. (6 + 10) ÷ 2 = 8, e 8 é maior que 6, então fica 8.\n"
            . "Exemplo: Média Bim 8 e ENAC 6. (8 + 6) ÷ 2 = 7, e 7 é menor que 8, então continua 8.\n\n"
            . "Na coluna Resultado Final:\n"
            . "Resultado Final = maior((Média Bim + ENAC) ÷ 2, Média Bim)\n"
            . "1. Limpe a fórmula.\n"
            . "2. Monte: maior ( ( Média Bim + ENAC ) ÷ 2 , Média Bim )\n"
            . "3. Salvar bloco.\n\n"
            . "Não use o botão Maior entre as duas primeiras.";
    }

    /**
     * Explica métricas do motor (|, papéis, se, substituta…) sem inventar.
     */
    private function textoExplicacaoMetricaFormula(string $mensagem): ?string
    {
        $t = $this->normalizarTexto($mensagem);
        $pergunta = preg_match('/\b(pq|porque|por que|o que|como|explica|significa|serve|ordem|depois|antes|papel)\b/u', $t) === 1
            || str_contains($t, '?');

        if (
            $pergunta
            && (
                preg_match('/\bpj\b.*\bch\b|\bch\b.*\bpj\b|depois do ch|antes do ch|pj \| ch|ch \| pj/u', $t) === 1
                || (str_contains($t, '|') && preg_match('/\b(media|média|grupo|papel|pipe)\b/u', $t) === 1)
                || preg_match('/\b(grupos?|papeis|papéis)\b.*\b(media|média|\|)\b|\b(media|média)\b.*\b(grupos?|\|)\b/u', $t) === 1
            )
        ) {
            return "O | dentro de média ( ) separa PAPÉIS pela posição — não pelo nome da coluna:\n\n"
                . "média ( provas | substituta | opcional )\n"
                . "1º PP, PU = provas (a CH pode cobrir a 1ª que faltar)\n"
                . "2º CH = substituta (1 coluna)\n"
                . "3º PJ = opcional (só se tiver lançamento; a CH nunca cobre o PJ)\n\n"
                . "Por isso o PJ fica depois do CH: opcional é o 3º grupo. "
                . "Quem decide se tem PJ é o se ( PJ , … , … ) de fora — não a ordem dentro da média. "
                . "PE soma fora: + PE.";
        }

        if ($pergunta && preg_match('/\bse\s*\(|\bse\b.*(verdadeiro|falso|condi|lan[cç]|existe)/u', $t) === 1) {
            return "se ( condição , conta_se_sim , conta_se_não ) funciona como o SE do Excel:\n\n"
                . "• PJ ou PJ = verdadeiro → tem nota em PJ\n"
                . "• PJ = falso → não tem\n"
                . "• PP > 7 (ou >= < <= = !=) → compara a nota\n"
                . "• PP > PU → compara duas colunas (se faltar nota em alguma, a condição é falsa)\n\n"
                . "Ex.: se ( PJ , média ( PP , PU | CH | PJ ) , média ( PP , PU | CH ) ) + PE";
        }

        if ($pergunta && preg_match('/nota substituta|chamada\s*\(|2a.?chamada|segunda.?chamada|substitut/u', $t) === 1
            && !preg_match('/\bpj\b|projeto|opcional/u', $t)) {
            return "nota substituta ( PP , PU , CH ) = média das bases; a CH cobre só a 1ª base que faltar. "
                . "Se PP e PU existem, CH é ignorada. Já divide sozinha — não coloque ÷ 2 de novo. "
                . "PE, se entrar, some fora: + PE.\n\n"
                . "Parecido: média ( PP , PU | CH ) — aí o | marca CH como substituta.";
        }

        if ($pergunta && preg_match('/media c\/ rec|média c\/ rec|composicao|recupera/u', $t) === 1
            && !preg_match('/\benac\b/u', $t)) {
            return "média c/ rec ( média , REC ): sem recuperação fica só a média; com REC vira (média + REC) ÷ 2.";
        }

        if ($pergunta && preg_match('/\b(maior|menor)\s*\(|maior entre|menor entre/u', $t) === 1) {
            return "maior ( A , B ) fica com o maior valor; menor ( A , B ) com o menor. "
                . "Para ENAC só subir a média: Resultado Final = maior ( Média Bim , ENAC ). "
                . "O atalho “Maior entre as duas primeiras” pega só as duas primeiras peças da lista — não use para Média Bim × ENAC.";
        }

        return null;
    }

    /**
     * PP/PU com CH substituta, Projeto opcional e PE fora — linguagem natural da coordenação.
     */
    private function pedidoEhMediaComChEPj(string $mensagem): bool
    {
        $t = $this->normalizarTexto($mensagem);
        if (str_contains($t, 'enac')) {
            return false;
        }
        // Peso / teto condicional → NÃO cair no template "provas|substituta|opcional".
        if ($this->pedidoEhMediaPonderada($mensagem) || $this->pedidoTemTetoCondicional($mensagem)) {
            return false;
        }
        // Modelo 3 / bônus ponderado — mas "÷2 / ÷3 + PJ opcional" pode ter setas de validação.
        if (preg_match('/b[oô]nus|ponderad|teto|efetiva|n[aã]o\s*(passou|corrigiu|implement)/u', $t) === 1) {
            return false;
        }
        $temPpPu = preg_match('/\bpp\b|\bpu\b/u', $t) === 1;
        $temSubst = preg_match('/\bch\b|2a.?chamada|2ª.?chamada|segunda.?chamada|substitut/u', $t) === 1;
        $pedeMontar = preg_match('/m[eé]dia|divid|\/\s*2|\/\s*3|por 2|por 3|÷|monta|formula|f[oó]rmula|calcular|se tiver|se lan[cç]/u', $t) === 1;

        return $pedeMontar && $temPpPu && $temSubst;
    }

    /** Média com pesos diferentes e/ou redistribuição (siglas do evento, não só PP/PU). */
    private function pedidoEhMediaPonderada(string $mensagem): bool
    {
        $t = $this->normalizarTexto($mensagem);
        if (str_contains($t, 'enac')) {
            return false;
        }
        if ($this->pedidoTemTetoCondicional($mensagem)) {
            return true;
        }
        // "Não use pesos/pontos" ≠ pedido de média ponderada.
        if (preg_match('/\b(?:n[aã]o\s+use|n[aã]o\s+utilizar|sem|nem)\b[^.\n]{0,48}\b(?:pesos?|pontos?\s+de\s+peso)\b/u', $t) === 1) {
            return false;
        }
        // ÷2 / ÷3 sem indicação de peso → média simples (PJ opcional).
        if (preg_match('/divid\w*\s+por\s*[23]|\/\s*[23]|÷\s*[23]|\(\s*[a-z]{1,8}\s*\+\s*[a-z]{1,8}\s*\)\s*÷\s*2/u', $t) === 1
            && !$this->mensagemTemIndicacaoPeso($mensagem)
        ) {
            return false;
        }

        return $this->mensagemTemIndicacaoPeso($mensagem)
            || preg_match('/\d\s*\+\s*\d\s*\+\s*\d/u', $t) === 1;
    }

    /**
     * Usuário pode dizer "peso 3", "vale 3", "vale 3 pontos", "PP 3 pontos".
     * Não confundir com bônus: "PE até 0,5 ponto".
     */
    private function mensagemTemIndicacaoPeso(string $mensagem): bool
    {
        $t = $this->normalizarTexto($mensagem);
        // Tira "até/máximo X ponto(s)" (teto de bônus, não peso da prova).
        $t = preg_replace('/\b(?:at[eé]|m[aá]ximo|maximo|m[aá]x\.?)\s*\d+(?:[.,]\d+)?\s*pontos?\b/u', ' ', $t) ?? $t;

        return preg_match(
            '/ponderad|redistribu|proporcion|\bpesos?\b|peso\s*\d'
            . '|vale\s+\d+(?:[.,]\d+)?(?:\s*pontos?)?'
            . '|\b\d+(?:[.,]\d+)?\s*pontos?\b/u',
            $t
        ) === 1;
    }

    /** Modelo 3: teto 6 se a prova (PU efetiva / CH) < 5. */
    private function pedidoTemTetoCondicional(string $mensagem): bool
    {
        return preg_match(
            '/teto\s*(condicional|de\s*6)|ultrapassar\s*6|n[aã]o poder[aá] ultrapassar\s*6'
            . '|menor que\s*5(?:[.,]0)?\b|inferior a\s*5(?:[.,]0)?\b|abaixo de\s*5(?:[.,]0)?\b|<\s*5(?:[.,]0)?\b'
            . '|nota efetiva\s*(?:da\s*|de\s*)?(?:pu|prova)|pu efetiva|quando.{0,40}\bpu\b.{0,40}\b6\b'
            . '|limit\w* (?:a )?6.{0,40}\bpu\b|\bpu\b.{0,40}limit\w* (?:a )?6/ui',
            $mensagem
        ) === 1;
    }

    /**
     * Teto 6 se a prova-alvo (ou CH no lugar dela) < limiar; senão teto 10.
     * se(PROVA, se(PROVA<L,…), se(CH<L,…)) — CH só entra na comparação quando a prova está ausente
     * (aí a substituta cobre essa prova se a anterior já tinha nota).
     */
    private function envolverTetoCondicionalProva(
        string $nucleo,
        string $prova,
        string $subst,
        string $limiar,
        string $tetoBaixo,
        string $tetoAlto
    ): string {
        $ramoProva = "se ( {$prova} < {$limiar} , menor ( {$tetoBaixo} , {$nucleo} ) , menor ( {$tetoAlto} , {$nucleo} ) )";
        $ramoSubst = "se ( {$subst} < {$limiar} , menor ( {$tetoBaixo} , {$nucleo} ) , menor ( {$tetoAlto} , {$nucleo} ) )";

        return "se ( {$prova} , {$ramoProva} , {$ramoSubst} )";
    }

    /**
     * Siglas/títulos curtos do evento para usar na fórmula (não códigos internos).
     *
     * @param array<string,mixed>|null $estado
     * @return list<string>
     */
    private function siglasDoEventoParaFormula(?array $estado): array
    {
        $out = [];
        $nomes = is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [];
        foreach ($nomes as $cod => $rotulo) {
            $cod = strtolower(trim((string) $cod));
            $rotulo = trim((string) $rotulo);
            if ($rotulo === '' || $cod === '') {
                continue;
            }
            if (preg_match('/^(media_|resultado|_semanal|s[1-8]$)/', $cod) === 1) {
                continue;
            }
            if (!$this->rotuloPareceSiglaFormula($rotulo)) {
                continue;
            }
            $out[$rotulo] = true;
        }
        foreach ((array) ($estado['colunas_ordem'] ?? []) as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod === '' || preg_match('/^(media_|resultado|_semanal)/', $cod) === 1) {
                continue;
            }
            $rotulo = trim((string) ($nomes[$cod] ?? ''));
            if ($rotulo !== '' && $this->rotuloPareceSiglaFormula($rotulo)) {
                $out[$rotulo] = true;
            }
        }

        return array_keys($out);
    }

    /** Sigla de peça/evento (PP, AV1…) — não título de período nem palavra comum. */
    private function rotuloPareceSiglaFormula(string $rotulo): bool
    {
        $r = trim($rotulo);
        if ($r === '' || mb_strlen($r) > 12 || preg_match('/\s/u', $r) === 1) {
            return false;
        }
        if (preg_match('/^(e|ou|de|da|do|das|dos|a|o|as|os|um|uma|com|sem|ate|até|max|máx|para|por)$/iu', $r) === 1) {
            return false;
        }
        if (preg_match('/bimestre|semestre|unidade|m[eé]dia|resultado|faltas|jornada|aluno|prova\s/iu', $r) === 1) {
            return false;
        }

        return preg_match('/^[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ0-9_]{0,11}$/u', $r) === 1;
    }

    /**
     * Pares [sigla, peso] — só siglas do evento (nunca "e", "bimestre", etc.).
     *
     * @param list<string> $siglas
     * @param list<string> $excluir
     * @return list<array{0:string,1:float}>
     */
    private function extrairBasesComPeso(string $mensagem, array $siglas, array $excluir = []): array
    {
        $excl = [];
        foreach ($excluir as $e) {
            $excl[mb_strtolower(trim((string) $e))] = true;
        }
        // Com evento aberto: SOMENTE as siglas do quadro. Sem evento: só tokens que parecem sigla.
        $candidatas = $siglas;
        if ($candidatas === []) {
            if (preg_match_all(
                '/\b([A-Za-z][A-Za-z0-9_]{0,11})\b[^0-9]{0,24}(?:peso\s*|vale\s*|(?:tem|com|de)\s+)?(\d+(?:[.,]\d+)?)(?:\s*pontos?)?/ui',
                $mensagem,
                $mm,
                PREG_SET_ORDER
            )) {
                foreach ($mm as $m) {
                    $cand = (string) $m[1];
                    if ($this->rotuloPareceSiglaFormula($cand)) {
                        $candidatas[] = $cand;
                    }
                }
            }
        }
        $candidatas = array_values(array_unique(array_filter(array_map('trim', $candidatas))));
        usort($candidatas, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $bases = [];
        $usadas = [];
        foreach ($candidatas as $sigla) {
            $baixo = mb_strtolower($sigla);
            if ($sigla === '' || isset($usadas[$baixo]) || isset($excl[$baixo])) {
                continue;
            }
            if (!$this->rotuloPareceSiglaFormula($sigla)) {
                continue;
            }
            $peso = $this->extrairPesoDaSiglaNaMensagem($mensagem, $sigla);
            if ($peso === null || $peso <= 0) {
                continue;
            }
            $bases[] = [$sigla, $peso];
            $usadas[$baixo] = true;
        }

        return $bases;
    }

    /** Aceita "peso 3", "vale 3", "vale 3 pontos", "PP 3 pontos". */
    private function extrairPesoDaSiglaNaMensagem(string $mensagem, string $sigla): ?float
    {
        $q = preg_quote($sigla, '/');
        $padroes = [
            '/(?<![A-Za-z0-9_])' . $q . '(?![A-Za-z0-9_])[^0-9]{0,20}peso\s*(\d+(?:[.,]\d+)?)/ui',
            '/\bpeso\s*(\d+(?:[.,]\d+)?)\s*(?:para\s+|do\s+|de\s+|da\s+)?' . $q . '(?![A-Za-z0-9_])/ui',
            '/(?<![A-Za-z0-9_])' . $q . '(?![A-Za-z0-9_])[^0-9]{0,28}vale\s*(\d+(?:[.,]\d+)?)(?:\s*pontos?)?/ui',
            '/(?<![A-Za-z0-9_])' . $q . '(?![A-Za-z0-9_])[^0-9]{0,28}(?:tem|com|de)\s*(\d+(?:[.,]\d+)?)\s*pontos?\b/ui',
            '/(?<![A-Za-z0-9_])' . $q . '(?![A-Za-z0-9_])\s*[,:\-–]?\s*(\d+(?:[.,]\d+)?)\s*pontos?\b/ui',
        ];
        foreach ($padroes as $re) {
            if (preg_match($re, $mensagem, $m) === 1) {
                return (float) str_replace(',', '.', $m[1]);
            }
        }

        return null;
    }

    /**
     * Escolhe uma sigla do evento (ou citada) para um papel: substituta, bônus, ponto extra.
     *
     * @param list<string> $siglas
     * @param list<string> $excluir já usadas (case-insensitive)
     */
    private function escolherSiglaPorPapel(
        string $mensagem,
        array $siglas,
        string $papel,
        array $excluir = []
    ): ?string {
        $excl = [];
        foreach ($excluir as $e) {
            $excl[mb_strtolower(trim((string) $e))] = true;
        }

        $papelRe = match ($papel) {
            'substituta' => 'substitut(?:a|o)?|2a\.?\s*chamada|2ª\.?\s*chamada|segunda\s*chamada',
            'bonus' => 'b[oô]nus|bonus',
            'ponto_extra' => 'ponto\s*extra|\bpe\b.{0,24}(?:at[eé]|m[aá]ximo|maximo|no m[aá]ximo)',
            default => '',
        };

        // Palavra-chave do papel → sigla mais próxima (antes ou depois), máx. 28 chars.
        if ($papelRe !== '' && preg_match_all('/(?:' . $papelRe . ')/uis', $mensagem, $mm, PREG_OFFSET_CAPTURE)) {
            foreach ($mm[0] as $hit) {
                $pos = (int) $hit[1];
                $lenHit = strlen((string) $hit[0]);
                $antes = substr($mensagem, max(0, $pos - 28), min(28, $pos));
                $depois = substr($mensagem, $pos + $lenHit, 28);
                $vizinha = $this->siglaMaisProximaNoTrecho($antes, $siglas, $excl, true)
                    ?? $this->siglaMaisProximaNoTrecho($depois, $siglas, $excl, false);
                if ($vizinha !== null) {
                    return $vizinha;
                }
            }
        }

        // Fallback: sigla típica do papel no evento / citada.
        $fallback = match ($papel) {
            'substituta' => ['CH', '2CH', 'CH2', 'SUB', '2A'],
            'bonus' => ['PJ', 'PROJ', 'PROJETO'],
            'ponto_extra' => ['PE', 'PEX', 'EXTRA'],
            default => [],
        };
        foreach ($fallback as $cand) {
            $baixo = mb_strtolower($cand);
            if (isset($excl[$baixo])) {
                continue;
            }
            foreach ($siglas as $sigla) {
                if (mb_strtolower($sigla) === $baixo) {
                    return $sigla;
                }
            }
            if (preg_match('/\b' . preg_quote($cand, '/') . '\b/ui', $mensagem) === 1) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * @param list<string> $siglas
     * @param array<string,bool> $excl
     */
    private function siglaMaisProximaNoTrecho(string $trecho, array $siglas, array $excl, bool $pegarUltima): ?string
    {
        $achados = [];
        foreach ($siglas as $sigla) {
            $baixo = mb_strtolower($sigla);
            if ($sigla === '' || isset($excl[$baixo])) {
                continue;
            }
            $q = preg_quote($sigla, '/');
            if (preg_match_all('/(?<![A-Za-z0-9_])' . $q . '(?![A-Za-z0-9_])/ui', $trecho, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[0] as $m) {
                    $achados[] = ['sigla' => $sigla, 'pos' => (int) $m[1]];
                }
            }
        }
        if ($achados === []) {
            return null;
        }
        usort($achados, static function (array $a, array $b) use ($pegarUltima): int {
            return $pegarUltima ? ($a['pos'] <=> $b['pos']) : ($b['pos'] <=> $a['pos']);
        });

        return $pegarUltima
            ? (string) $achados[count($achados) - 1]['sigla']
            : (string) $achados[0]['sigla'];
    }

    /**
     * Título da coluna amarela aberta (ex.: MU). Não confunde com peças (PP/PU/PJ/CH/PE).
     *
     * @param array<string,mixed>|null $estado
     */
    private function nomeColunaAlvoFormula(string $mensagem = '', ?array $estado = null): string
    {
        $nomes = is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [];
        $pecasCurta = ['pp', 'pu', 'pj', 'ch', 'pe', 'fal', 'mu', 'ms', 'enac', 'rec'];

        // 1) Prioridade: cálculo amarelo aberto (é nele que a fórmula sobe).
        $cod = strtolower(trim((string) ($estado['bloco_calc'] ?? '')));
        if ($cod !== '') {
            $rotuloAberto = trim((string) ($nomes[$cod] ?? ''));
            if ($rotuloAberto !== '') {
                return $rotuloAberto;
            }
            $padrao = [
                'media_bim' => 'Média Bim',
                'media_final' => 'Resultado Final',
                's1_mu' => 'MU',
                's2_ms' => 'MS 2º',
                's1_ms' => 'MS 1º',
            ];
            if (isset($padrao[$cod])) {
                return $padrao[$cod];
            }
            if (preg_match('/_mu$/', $cod) === 1) {
                return 'MU';
            }
            if (preg_match('/_ms$/', $cod) === 1) {
                return 'MS';
            }
        }

        // 2) Só se a mensagem citar um título de BLOCO (não peça PP/PJ…).
        $msg = trim($mensagem);
        if ($msg !== '' && $nomes !== []) {
            foreach ($nomes as $rotulo) {
                $rotulo = trim((string) $rotulo);
                if ($rotulo === '') {
                    continue;
                }
                $baixo = mb_strtolower($rotulo);
                if (in_array($baixo, $pecasCurta, true)) {
                    continue;
                }
                if (preg_match('/\b' . preg_quote($rotulo, '/') . '\b/ui', $msg) === 1) {
                    return $rotulo;
                }
            }
        }

        return 'esta coluna';
    }

    /**
     * @param array<string,mixed>|null $estado
     */
    private function textoMediaComChEPj(string $mensagem = '', ?array $estado = null): string
    {
        $col = $this->nomeColunaAlvoFormula($mensagem, $estado);
        $siglas = $this->siglasDoEventoParaFormula($estado);
        $t = $this->normalizarTexto($mensagem);

        // Papéis ANTES das provas — senão PJ/CH/PE entram no array_slice(0,2) e a PU some.
        $subst = $this->escolherSiglaPorPapel($mensagem, $siglas, 'substituta');
        $opcional = $this->escolherSiglaPorPapel(
            $mensagem,
            $siglas,
            'bonus',
            $subst ? [$subst] : []
        );
        $extra = $this->escolherSiglaPorPapel(
            $mensagem,
            $siglas,
            'ponto_extra',
            array_merge($subst ? [$subst] : [], $opcional ? [$opcional] : [])
        );

        // "se tiver PJ / divide por 3 / entra na média" sem a palavra "bônus".
        if ($opcional === null) {
            foreach (['PJ', 'PROJ', 'PROJETO'] as $cand) {
                $q = preg_quote($cand, '/');
                if (preg_match('/\b' . $q . '\b/ui', $mensagem) !== 1) {
                    continue;
                }
                if (preg_match(
                    '/(?:se tiver|caso tenha|se lan[cç]|add|adicionar|somar|entra).{0,40}\b' . $q . '\b'
                    . '|\b' . $q . '\b.{0,40}(?:divid|\/\s*3|por 3|somar|entra|opcional)/ui',
                    $mensagem
                ) === 1) {
                    $opcional = $this->siglaDoEventoOuCitada($cand, $siglas);
                    break;
                }
            }
        }

        $excluirProvas = array_values(array_filter([$subst, $opcional, $extra]));
        $provas = $this->extrairProvasMediaSimples($mensagem, $siglas, $excluirProvas);

        $querOpcional = $opcional !== null || preg_match(
            '/\bopcional|projeto|se tiver\s+(nota\s+)?(no\s+|em\s+)?pj|se lan[cç].{0,20}pj|divid\w*\s+por\s*3|\/\s*3|add\s+o?\s*pj/u',
            $t
        ) === 1;
        $querExtra = $extra !== null || preg_match('/ponto\s*extra|\bpe\b|soma fora|soma o pe/u', $t) === 1;

        if ($querOpcional && $opcional === null) {
            $opcional = $this->escolherSiglaPorPapel($mensagem, $siglas, 'bonus', $excluirProvas)
                ?? $this->siglaDoEventoOuCitada('PJ', $siglas);
        }
        if ($querExtra && $extra === null) {
            $extra = $this->escolherSiglaPorPapel($mensagem, $siglas, 'ponto_extra', array_merge($excluirProvas, $opcional ? [$opcional] : []))
                ?? $this->siglaDoEventoOuCitada('PE', $siglas);
        }

        if (count($provas) < 2 || $subst === null) {
            $lista = $siglas !== [] ? implode(', ', $siglas) : '(nenhuma sigla no evento ainda)';

            return "As siglas mudam por evento — não assumo PP/PU/CH.\n\n"
                . "Colunas deste evento: {$lista}.\n"
                . "Me diga: quais 2 provas entram na média, qual é a substituta"
                . ($querOpcional ? ', qual é o opcional' : '')
                . ($querExtra ? ' e qual é o ponto extra' : '')
                . '.';
        }

        $a = $provas[0];
        $b = $provas[1];
        if ($querOpcional && $opcional !== null) {
            $formula = "{$col} = se ( {$opcional} , média ( {$a} , {$b} | {$subst} | {$opcional} ) , média ( {$a} , {$b} | {$subst} ) )";
            if ($querExtra && $extra !== null) {
                $formula .= " + {$extra}";
            }
        } elseif ($querExtra && $extra !== null) {
            $formula = "{$col} = média ( {$a} , {$b} | {$subst} ) + {$extra}";
        } else {
            $formula = "{$col} = média ( {$a} , {$b} | {$subst} )";
        }

        return "Entendi (siglas deste evento): {$a} e {$b} na média; {$subst} substituta"
            . ($opcional !== null && $querOpcional ? "; {$opcional} opcional (÷3 se lançar)" : '')
            . ($extra !== null && $querExtra ? "; {$extra} fora" : '')
            . ".\n\n{$formula}\n\n"
            . "Montei para a coluna {$col}. O | só vale dentro de média ( … ).";
    }

    /**
     * Duas provas da média simples (ex.: PP e PU), sem misturar substituta/opcional/PE.
     *
     * @param list<string> $siglas
     * @param list<string> $excluir
     * @return list<string>
     */
    private function extrairProvasMediaSimples(string $mensagem, array $siglas, array $excluir = []): array
    {
        $excl = [];
        foreach ($excluir as $e) {
            $excl[mb_strtolower(trim((string) $e))] = true;
        }

        $provas = [];
        foreach ($this->extrairBasesComPeso($mensagem, $siglas, $excluir) as [$sig]) {
            $provas[] = $sig;
        }

        // "PP + PU" / "PP e PU" na frase — ordem do pedido.
        if (count($provas) < 2
            && preg_match_all('/\b([A-Za-z][A-Za-z0-9_]{0,11})\b/u', $mensagem, $mm)
        ) {
            foreach ($mm[1] as $tok) {
                $baixo = mb_strtolower($tok);
                if (isset($excl[$baixo]) || !$this->rotuloPareceSiglaFormula($tok)) {
                    continue;
                }
                if ($siglas !== []) {
                    $hit = false;
                    foreach ($siglas as $s) {
                        if (mb_strtolower($s) === $baixo) {
                            $tok = $s;
                            $hit = true;
                            break;
                        }
                    }
                    if (!$hit) {
                        continue;
                    }
                }
                if (!in_array($tok, $provas, true)) {
                    $provas[] = $tok;
                }
                if (count($provas) >= 2) {
                    break;
                }
            }
        }

        if (count($provas) < 2) {
            foreach (['PP', 'PU', 'P1', 'P2', 'AV1', 'AV2'] as $cand) {
                if (preg_match('/\b' . preg_quote($cand, '/') . '\b/ui', $mensagem) !== 1) {
                    continue;
                }
                $sig = $this->siglaDoEventoOuCitada($cand, $siglas);
                if ($sig === null) {
                    continue;
                }
                $baixo = mb_strtolower($sig);
                if (isset($excl[$baixo]) || in_array($sig, $provas, true)) {
                    continue;
                }
                $provas[] = $sig;
                if (count($provas) >= 2) {
                    break;
                }
            }
        }

        return array_slice($provas, 0, 2);
    }

    /**
     * @param list<string> $siglas
     */
    private function siglaDoEventoOuCitada(string $cand, array $siglas): ?string
    {
        foreach ($siglas as $sigla) {
            if (mb_strtolower($sigla) === mb_strtolower($cand)) {
                return $sigla;
            }
        }

        return $siglas === [] ? $cand : null;
    }

    /**
     * Média ponderada com siglas do evento (não fixa PP/PU/PJ/CH/PE).
     *
     * @param array<string,mixed>|null $estado
     */
    private function textoMediaPonderadaComChEPj(string $mensagem = '', ?array $estado = null): string
    {
        $col = $this->nomeColunaAlvoFormula($mensagem, $estado);
        $t = $this->normalizarTexto($mensagem);
        $fmt = static function (float $n): string {
            return abs($n - round($n)) < 0.0001 ? (string) (int) round($n) : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        };

        $siglas = $this->siglasDoEventoParaFormula($estado);
        // Papéis primeiro — para não virar "peso" por número solto na frase.
        $subst = $this->escolherSiglaPorPapel($mensagem, $siglas, 'substituta');
        $bonus = $this->escolherSiglaPorPapel($mensagem, $siglas, 'bonus', $subst ? [$subst] : []);
        $extra = $this->escolherSiglaPorPapel($mensagem, $siglas, 'ponto_extra', array_merge($subst ? [$subst] : [], $bonus ? [$bonus] : []));
        $excluirBases = array_values(array_filter([$subst, $bonus, $extra]));
        $bases = $this->extrairBasesComPeso($mensagem, $siglas, $excluirBases);

        if (count($bases) < 2) {
            $lista = $siglas !== [] ? implode(', ', $siglas) : '(cadastre as colunas no evento — USAR NO CÁLCULO)';

            return "Não fechei a média ponderada: preciso de pelo menos 2 colunas com peso, usando as siglas DESTE evento.\n\n"
                . "Siglas disponíveis: {$lista}.\n"
                . "Ex.: \"AV1 peso 4, AV2 peso 6, 2CH substituta, PROJ bônus ÷ 10, EXTRA até 0,5\".";
        }

        $bases = array_slice($bases, 0, 4);

        if ($subst === null) {
            $lista = $siglas !== [] ? implode(', ', $siglas) : '(nenhuma)';

            return "Faltou a coluna substituta (2ª chamada). Neste evento: {$lista}. Qual sigla cobre a 1ª prova ausente?";
        }

        $paresTxt = [];
        $somaSem = 0.0;
        foreach ($bases as [$sig, $peso]) {
            $paresTxt[] = $sig . ' , ' . $fmt($peso);
            $somaSem += $peso;
        }
        $wSomaSem = $fmt($somaSem);
        $base = 'média ponderada ( ' . implode(' , ', $paresTxt) . ' | ' . $subst . ' )';

        $peMax = 1.0;
        $alvoPe = $extra !== null ? $extra : 'pe';
        $qPe = preg_quote($alvoPe, '/');
        if (preg_match(
            '/\b(?:' . $qPe . '|pe|ponto\s*extra)\b[^0-9]{0,40}(?:at[eé]|m[aá]ximo|maximo|limitad[oa]\s+a?|menor\s*\(?\s*)\s*(\d+(?:[.,]\d+)?)/ui',
            $mensagem,
            $mPe
        ) === 1) {
            $peMax = (float) str_replace(',', '.', $mPe[1]);
        } elseif (preg_match(
            '/\b(?:at[eé]|m[aá]ximo|maximo|limitad[oa]\s+a)\s*(\d+(?:[.,]\d+)?)\s*(?:ponto)?[^.\n]{0,24}\b(?:' . $qPe . '|pe|ponto\s*extra)\b/ui',
            $mensagem,
            $mPe2
        ) === 1) {
            $peMax = (float) str_replace(',', '.', $mPe2[1]);
        }
        if ($peMax <= 0) {
            $peMax = 1.0;
        }
        $wPe = $fmt($peMax);
        $termoPe = $extra !== null
            ? "se ( {$extra} , menor ( {$wPe} , {$extra} ) , 0 )"
            : null;

        $bonusEhBonus = $bonus !== null && (
            preg_match('/\b(b[oô]nus|bonus)\b/u', $t) === 1
            || preg_match('/\b' . preg_quote($bonus, '/') . '\s*[÷\/]\s*\d+/ui', $mensagem) === 1
            || preg_match('/\b' . preg_quote($bonus, '/') . '\b[^.\n]{0,40}\b(fora da m[eé]dia|n[aã]o entra)/ui', $mensagem) === 1
        );
        $bonusTemPeso = false;
        $wBonus = 2.0;
        if ($bonus !== null && !$bonusEhBonus) {
            $q = preg_quote($bonus, '/');
            if (preg_match('/\b' . $q . '\b[^0-9]{0,16}peso\s*(\d+(?:[.,]\d+)?)|\bpeso\s*(\d+(?:[.,]\d+)?)\s*(?:para\s+|do\s+|de\s+)?' . $q . '\b/ui', $mensagem, $mB) === 1) {
                $bonusTemPeso = true;
                $bruto = trim((string) (($mB[1] ?? '') !== '' ? $mB[1] : ($mB[2] ?? '')));
                if ($bruto !== '') {
                    $wBonus = (float) str_replace(',', '.', $bruto);
                }
            } elseif (preg_match('/\bredistribu/ui', $mensagem) === 1) {
                $bonusTemPeso = true;
            }
        }

        $descBases = implode(', ', array_map(static fn ($p) => $p[0] . ' peso ' . $fmt($p[1]), $bases));
        $querTetoCond = $this->pedidoTemTetoCondicional($mensagem);

        // Prova cujo valor efetivo (ou CH no lugar) dispara teto baixo — em geral a 2ª base (PU).
        $provaTeto = null;
        foreach ($bases as [$sig]) {
            if (preg_match('/nota\s*efetiva\s*(?:da\s*|de\s*)?' . preg_quote($sig, '/') . '/ui', $mensagem) === 1
                || preg_match('/\b' . preg_quote($sig, '/') . '\b.{0,48}(?:menor que\s*5|inferior a\s*5|abaixo de\s*5|<\s*5|teto\s*de\s*6|ultrapassar\s*6)/uis', $mensagem) === 1
            ) {
                $provaTeto = $sig;
                break;
            }
        }
        if ($provaTeto === null) {
            foreach ($bases as [$sig]) {
                if (preg_match('/^pu$/i', $sig) === 1) {
                    $provaTeto = $sig;
                    break;
                }
            }
        }
        if ($provaTeto === null && count($bases) >= 2) {
            $provaTeto = $bases[count($bases) - 1][0];
        }

        // Defaults Modelo 3. Não usar o 0,5 do PE como limiar/teto.
        $limiar = 5.0;
        $tetoBaixo = 6.0;
        $tetoAlto = 10.0;
        if (preg_match(
            '/(?:nota efetiva|efetiva(?:\s+da)?\s*' . ($provaTeto !== null ? preg_quote($provaTeto, '/') : 'pu')
            . '|\b' . ($provaTeto !== null ? preg_quote($provaTeto, '/') : 'pu') . '\b).{0,72}'
            . '(?:menor que|inferior a|abaixo de|<\s*)(\d+(?:[.,]\d+)?)/uis',
            $mensagem,
            $mLim
        ) === 1
            || preg_match('/(?:menor que|inferior a|abaixo de)\s*(5(?:[.,]0)?)\b/ui', $mensagem, $mLim) === 1
        ) {
            $limiar = (float) str_replace(',', '.', $mLim[1]);
        }
        if (preg_match(
            '/(?:ultrapassar|teto\s*(?:condicional\s*)?(?:de\s*)?|n[aã]o poder[aá] ultrapassar)\s*(6(?:[.,]0)?)\b/ui',
            $mensagem,
            $mTb
        ) === 1
            || preg_match('/\b(6(?:[.,]0)?)\b.{0,28}(?:mesmo após|após os b[oô]nus|depois dos b[oô]nus)/uis', $mensagem, $mTb) === 1
        ) {
            $v = (float) str_replace(',', '.', $mTb[1]);
            if ($v >= 5.0 && $v < 10.0) {
                $tetoBaixo = $v;
            }
        }
        $wLim = $fmt($limiar);
        $wTetoB = $fmt($tetoBaixo);
        $wTetoA = $fmt($tetoAlto);

        $fechar = function (string $nucleo, string $extraDesc) use (
            $col, $descBases, $subst, $extra, $wPe, $querTetoCond, $provaTeto, $wLim, $wTetoB, $wTetoA
        ): string {
            if ($querTetoCond && $provaTeto !== null) {
                $formula = "{$col} = " . $this->envolverTetoCondicionalProva(
                    $nucleo,
                    $provaTeto,
                    $subst,
                    $wLim,
                    $wTetoB,
                    $wTetoA
                );
                $como = "se {$provaTeto} (ou {$subst} no lugar dela) < {$wLim} → teto {$wTetoB}; senão teto {$wTetoA}";

                return "Entendi com as siglas deste evento: {$descBases}; substituta {$subst}; {$extraDesc}; {$como}; "
                    . "prova obrigatória sem substituta deixa pendente.\n\n"
                    . "{$formula}\n\n"
                    . "Montei para a coluna {$col}.";
            }
            $formula = "{$col} = menor ( {$wTetoA} , {$nucleo} )";

            return "Entendi com as siglas deste evento: {$descBases}; substituta {$subst}; {$extraDesc}; teto {$wTetoA}; "
                . "prova sem substituta deixa pendente.\n\n"
                . "{$formula}\n\n"
                . "Montei para a coluna {$col}.";
        };

        if ($bonus !== null && $bonusEhBonus) {
            $divBonus = 10.0;
            if (preg_match('/\b' . preg_quote($bonus, '/') . '\s*[÷\/]\s*(\d+(?:[.,]\d+)?)/ui', $mensagem, $mDiv) === 1) {
                $divBonus = (float) str_replace(',', '.', $mDiv[1]);
            }
            if ($divBonus <= 0) {
                $divBonus = 10.0;
            }
            $wDiv = $fmt($divBonus);
            $termoBonus = "se ( {$bonus} , {$bonus} ÷ {$wDiv} , 0 )";
            $partes = [$base, $termoBonus];
            if ($termoPe !== null) {
                $partes[] = $termoPe;
            }
            $nucleo = implode(' + ', $partes);
            $extraDesc = "{$bonus} é bônus ({$bonus} ÷ {$wDiv}, fora da média)"
                . ($extra !== null ? "; {$extra} no máximo {$wPe}" : '');

            return $fechar($nucleo, $extraDesc);
        }

        if ($bonus !== null && $bonusTemPeso) {
            if ($wBonus <= 0) {
                $wBonus = 2.0;
            }
            $wB = $fmt($wBonus);
            $somaCom = $fmt($somaSem + $wBonus);
            $comBonus = "( ( {$base} × {$wSomaSem} ) + ( {$bonus} × {$wB} ) ) ÷ {$somaCom}";
            $nucleoMedia = "se ( {$bonus} , {$comBonus} , {$base} )";
            $nucleo = $termoPe !== null ? "{$nucleoMedia} + {$termoPe}" : $nucleoMedia;
            $extraDesc = "{$bonus} peso {$wB} na média; sem {$bonus} divide por {$wSomaSem}"
                . ($extra !== null ? "; {$extra} no máximo {$wPe}" : '');

            return $fechar($nucleo, $extraDesc);
        }

        $nucleo = $termoPe !== null ? "{$base} + {$termoPe}" : $base;
        $extraDesc = $extra !== null ? "{$extra} no máximo {$wPe}" : 'sem bônus extra';

        return $fechar($nucleo, $extraDesc);
    }

    /**
     * Se a IA misturou | com +÷ fora de média(), remove a linha inválida e pede esclarecimento
     * (não inventa template PP/PU/CH).
     */
    private function corrigirFormulaPipeErrada(string $texto): string
    {
        if (!str_contains($texto, '|')) {
            return $texto;
        }
        $linhas = preg_split("/\r\n|\n|\r/", $texto) ?: [$texto];
        $mudou = false;
        $restantes = [];
        foreach ($linhas as $linha) {
            if (!preg_match('/=\s*.*\|/u', $linha)) {
                $restantes[] = $linha;
                continue;
            }
            if (preg_match('/m[eé]dia\s*\(/ui', $linha) && !preg_match('/\([^)]*[+\-×*][^)]*\|[^)]*\)\s*[÷\/]/u', $linha)) {
                $restantes[] = $linha;
                continue;
            }
            $pipeForaMedia = preg_match('/\([^)]*[+\-×*][^)]*\|[^)]*\)\s*[÷\/]/u', $linha) === 1
                || (preg_match('/[+\-×*]\s*[A-Za-zÁ-ú0-9_.ºª]+\s*\|/u', $linha) === 1
                    && !preg_match('/m[eé]dia\s*\(/ui', $linha));
            if (!$pipeForaMedia) {
                $restantes[] = $linha;
                continue;
            }
            $mudou = true;
        }
        if (!$mudou) {
            return $texto;
        }
        $limpo = trim(implode("\n", $restantes));
        $aviso = "O | só vale dentro de média ( … ), não misturado com +÷. "
            . "Me diga de novo: quais colunas são provas, qual é a substituta e se tem opcional (Projeto) ou PE fora.";

        return $limpo !== '' ? $limpo . "\n\n" . $aviso : $aviso;
    }

    /**
     * @return string|false|null data URL válida, false se inválida, null se vazia
     */
    private function normalizarImagemPrint(?string $imagem)
    {
        if ($imagem === null) {
            return null;
        }
        $imagem = trim($imagem);
        if ($imagem === '') {
            return null;
        }
        if (strlen($imagem) > 6000000) {
            return false;
        }
        if (!preg_match('#^data:image/(jpeg|jpg|png|webp|gif);base64,[a-z0-9+/=\r\n]+$#i', $imagem)) {
            return false;
        }

        return $imagem;
    }

    /**
     * @param array<string,mixed>|null $wizardEstado
     * @return array{success:bool,mensagem?:string,error?:string}
     */
    private function analisarPrint(string $mensagem, string $imagem, ?array $wizardEstado): array
    {
        if ($mensagem === '') {
            $mensagem = 'Olhe este print e diga o que está errado ou não marcado, comparado com a configuração deste evento.';
        }
        $config = $this->configuracaoParaComparar($wizardEstado);
        $prompt = <<<PROMPT
Você confere um print da tela de Evento de Notas do EducaTudo com a configuração que o sistema tem agora. Responda em português, curto, em lista. Sem LaTeX, sem markdown, sem crase e sem bloco de código. Use os nomes da tela: Média Bim, ENAC, Resultado Final.

O que fazer:
- Leia o print: o que está marcado, desmarcado, vazio ou com traço.
- Compare com a configuração do sistema abaixo. Não invente peça, botão ou nota que não esteja no print nem na configuração.
- Cite o nome da coluna e o nome da matéria. Ex.: "Jornada do aluno em Português está com traço."
- Aponte onde ajustar: \Identidade, Peças, Exibir ou Revisar.
- Se uma coluna está com traço, diga qual peça deveria preenchê-la e se essa peça não está marcada, está sem bimestre ou a prévia está sem aluno.
- Se o print é a própria configuração (caixas de jornada, bimestre, fórmula), diga o que ficou de fora.
- Não chute número de nota. Se o print mostra traço, diga que está vazio.

Configuração atual do sistema:
{$config}
PROMPT;

        try {
            $raw = $this->openai->chatCompletion(
                [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $mensagem],
                        ['type' => 'image_url', 'image_url' => ['url' => $imagem, 'detail' => 'high']],
                    ],
                ]],
                $prompt,
                'gpt-4o-mini',
                0.1,
                1200,
                false
            );
        } catch (\Throwable $e) {
            error_log('BoletimConsultaAssistenteService print: ' . $e->getMessage());

            return ['success' => false, 'error' => 'Não deu para ler o print agora. Tente de novo.'];
        }

        $texto = $this->limparRespostaFinal(trim((string) ($raw['resposta'] ?? '')));
        if ($texto === '') {
            $texto = 'Li o print, mas não fechei o que está errado. Cole de novo a parte da tela com as caixas ou a tabela.';
        }

        return ['success' => true, 'mensagem' => $texto];
    }

    /**
     * @param array<string,mixed>|null $estado
     */
    private function configuracaoParaComparar(?array $estado): string
    {
        if (!is_array($estado)) {
            return 'Nenhuma configuração deste evento veio com o print.';
        }
        $opcoes = is_array($estado['pecas_opcoes'] ?? null) ? $estado['pecas_opcoes'] : [];
        $pecas = [];
        foreach ((array) ($estado['pecas'] ?? []) as $peca) {
            $peca = trim((string) $peca);
            if ($peca === '') {
                continue;
            }
            $opt = is_array($opcoes[$peca] ?? null) ? $opcoes[$peca] : [];
            $ids = is_array($opt['blocos_ids'] ?? null) ? $opt['blocos_ids'] : [];
            $pecas[] = [
                'peca' => $peca,
                'bimestres' => array_values(array_map('intval', (array) ($opt['bimestres'] ?? []))),
                'papel' => (string) ($opt['papel'] ?? ''),
                'eventos_marcados' => count($ids),
                'escolheu_eventos_na_mao' => !empty($opt['blocos_ids_manual']),
            ];
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
        $jornadaIds = is_array($estado['jornada_ids'] ?? null) ? $estado['jornada_ids'] : [];
        $resumo = [
            'passo' => (string) ($estado['passo'] ?? ''),
            'nome' => (string) ($estado['nome'] ?? ''),
            'ano_letivo' => (int) ($estado['ano_letivo'] ?? 0),
            'bimestre_evento' => (int) ($estado['bimestre'] ?? 0),
            'pecas_marcadas' => $pecas,
            'jornada' => [
                'entra_no_evento' => in_array('jornada', array_map('strval', (array) ($estado['pecas'] ?? [])), true),
                'modo' => (string) ($estado['jornada_modo'] ?? ''),
                'bimestres' => array_values(array_map('intval', (array) ($estado['jornada_bimestres'] ?? []))),
                'quantidade_marcada' => count($jornadaIds),
                'nota' => (string) ($estado['jornada_nota_modo'] ?? ''),
            ],
            'formulas' => $formulas,
            'aluno_na_previa' => (int) ($estado['aluno_preview_id'] ?? 0) > 0,
            'colunas' => array_values(array_map('strval', (array) ($estado['colunas_ordem'] ?? []))),
        ];
        $json = json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '{}';
    }

    /**
     * @param array<string,mixed>|null $wizardEstado
     */
    private function montarSystemPrompt(?array $wizardEstado): string
    {
        $resumo = $this->resumoEvento($wizardEstado);

        return <<<PROMPT
Você é o assistente da tela Evento de Notas do EducaTudo. Conversa em português, curto e direto. Sem LaTeX, sem markdown, sem crase e sem bloco de código. Use os nomes da tela: Média Bim, ENAC, Resultado Final e os títulos das colunas do evento aberto.

Como conversar (obrigatório):
- Entenda o pedido em linguagem natural, mesmo informal, incompleto ou em várias mensagens. Use o histórico.
- Se a intenção estiver clara, responda e, se for fórmula, entregue a linha pronta.
- Se NÃO entender ou faltar informação, NÃO invente. Pergunte 1 a 3 perguntas objetivas para entender melhor (ex.: quais colunas entram na média? tem nota substituta/CH? tem Projeto/opcional? PE soma fora? em qual coluna amarela montar?).
- Se o usuário responder a sua pergunta, continue a conversa e feche a fórmula.
- Prefira os nomes das colunas que já existem no evento aberto (veja o JSON abaixo). Se o usuário citar um código que não existe no evento, pergunte qual coluna usar.

Manual canônico das fórmulas (OBRIGATÓRIO — não invente outra lógica):
- Peça = tipo de nota (Prova Semanal, Avaliação Bimestral, ENAC, Jornada…). O título do evento não define o tipo.
- Prova Semanal: eventos daquele tipo no bimestre marcado. S1–S8: Bloco A ímpares, Bloco B pares.
- Conta aritmética: + − × ÷ e ( ). Média simples = (soma) ÷ quantidade. Ausente vira 0 só na conta pura +−×÷.
- Funções: maior ( ), menor ( ), nota substituta ( ), média c/ rec ( ), média ( ), se ( ).
- Ao sugerir fórmula: uma linha \Título = expressão. O título é a coluna amarela ABERTA (veja o evento abaixo). Nunca invente MS 2º se a aberta for MU.
- Três caminhos iguais: chips, conversa aqui, ou digitar a condição — a nota final é a mesma.

PAPÉIS DO | DENTRO DE média ( ) — responda SEMPRE assim quando perguntarem a ordem:
  média ( provas | substituta | opcional )
  1º grupo = provas (ex.: PP, PU) — a substituta pode cobrir a 1ª que faltar
  2º grupo = substituta (ex.: CH) — 1 coluna
  3º grupo = opcional (ex.: PJ/Projeto) — só se houver lançamento; a substituta NUNCA cobre o opcional
  O nome da coluna não muda o papel: muda a POSIÇÃO após o |. Por isso PJ fica DEPOIS do CH: opcional é o 3º grupo, CH é o 2º.
  ERRADO dizer que “PJ vem depois do CH porque avalia a situação do aluno antes da média”. Isso é falso.
  Quem decide “tem PJ?” é o se ( ) de FORA, não a ordem dentro da média.
  PE sempre FORA: + PE. O | só existe DENTRO de média ( ). Nunca (PP+PU+CH|CH)÷3.

se ( condição , conta_se_sim , conta_se_não ):
  · PJ ou PJ = verdadeiro → tem nota em PJ
  · PJ = falso → não tem
  · PP > 7 / >= / < / <= / = / != → compara valor; se faltar nota, condição falsa
  · PP > PU → compara duas colunas
  Ex. Projeto: se ( PJ , média ( PP , PU | CH | PJ ) , média ( PP , PU | CH ) ) + PE
  (igual a se ( PJ = verdadeiro , … ))

nota substituta ( bases… , CH ): média das bases; CH cobre só a 1ª base ausente; já divide sozinha.
média c/ rec ( média , REC ): sem REC fica a média; com REC → (média+REC)÷2.
maior ( A , B ): maior valor. menor ( A , B ): menor.
ENAC só sobe a média: Resultado Final = maior ( Média Bim , ENAC ).
(Média Bim + ENAC)÷2 só se melhorar: maior ( ( Média Bim + ENAC ) ÷ 2 , Média Bim ).
Recuperação teto 7 sem cortar a média: maior ( Média Bim , menor ( 7 , Recuperação ) ).

Exemplos de intenção (troque MU pelo título aberto):
  · "PP+PU/2, CH substitui, se PJ ÷3, +PE" → MU = se ( PJ , média ( PP , PU | CH | PJ ) , média ( PP , PU | CH ) ) + PE
  · Siglas (PP, PU, PJ, CH, PE, AV1…) mudam por evento. SEMPRE use as siglas de "Siglas usáveis na fórmula" / USAR NO CÁLCULO. Nunca invente PP se o evento tem outro nome.
  · Papéis: prova+peso, substituta, bônus (soma fora), ponto extra — não o nome da sigla.
  · "peso 3", "vale 3", "vale 3 pontos" ou "PP 3 pontos" = mesmo peso. "PE até 0,5 ponto" é teto de bônus, NÃO peso.
  · "AV1 peso 3, AV2 peso 5, PROJ peso 2; redistribui; 2CH; EXTRA até 1; máx 10" →
    MU = menor ( 10 , se ( PROJ , ( ( média ponderada ( AV1 , 3 , AV2 , 5 | 2CH ) × 8 ) + ( PROJ × 2 ) ) ÷ 10 , média ponderada ( AV1 , 3 , AV2 , 5 | 2CH ) ) + se ( EXTRA , menor ( 1 , EXTRA ) , 0 ) )
  · "AV1 peso 4, AV2 peso 6; PROJ bônus ÷10; EXTRA até 0,5; 2CH; máx 10" →
    MU = menor ( 10 , média ponderada ( AV1 , 4 , AV2 , 6 | 2CH ) + se ( PROJ , PROJ ÷ 10 , 0 ) + se ( EXTRA , menor ( 0.5 , EXTRA ) , 0 ) )
  · Teto condicional (ex.: se PU efetiva < 5 → MU máx 6; senão máx 10; CH no lugar da PU):
    NÚCLEO = média ponderada ( PP , 3 , PU , 7 | CH ) + se ( PJ , PJ ÷ 10 , 0 ) + se ( PE , menor ( 0.5 , PE ) , 0 )
    MU = se ( PU , se ( PU < 5 , menor ( 6 , NÚCLEO ) , menor ( 10 , NÚCLEO ) ) , se ( CH < 5 , menor ( 6 , NÚCLEO ) , menor ( 10 , NÚCLEO ) ) )
    (repita NÚCLEO por extenso; não invente função PU_EFETIVA)
  · "se tiver PF soma com MA, senão fica MA" → MU = se ( PF , MA + PF , MA )
  · sem detalhe → pergunte quais siglas do evento entram e o papel de cada uma
  NUNCA responda média simples ÷3 quando pediram pesos. NUNCA transforme bônus em peso. NUNCA assuma PP/PU/PJ/CH/PE se não forem as siglas deste evento.
  NUNCA troque média ponderada + bônus pelo template "média ( provas | substituta | opcional ) + PE" se o pedido tinha pesos ou teto condicional.
  Se faltar recurso do motor para uma regra, diga a limitação e NÃO monte fórmula incompleta nem sobrescreva a anterior.

- Jornada: pelo bimestre cadastrado na jornada.
- "1º A"/"1a" = turma, não bimestre.
- Sem aluno na prévia = exemplo; com aluno = notas reais.

Número de nota, quantidade de jornada ou lançamento SÓ pode vir de uma consulta. Se não consultou, não chute.

Para consultar, responda APENAS este bloco (sem texto antes):
<<<CONSULTA>>>
{"tool":"nome","args":{}}
<<<FIM>>>

Tools:
- buscar_aluno: args aluno_nome, turma (opcional). Use se houver mais de um candidato.
- jornadas_aluno: args aluno_nome ou aluno_id, bimestre (1-4, opcional), materia_nome (opcional). Quantas jornadas fez.
- lancamentos_aluno: args aluno_nome ou aluno_id, materia_nome (opcional), tipo (opcional: Avaliação Bimestral, Prova Semanal, ENAC…), bimestre (opcional), professor_nome (opcional). Busca notas de TODOS os formatos: prova online e lançamento da coordenação (Lançamento de Notas). Sempre use esta tool quando o usuário pedir nota/lançamento de qualquer tipo.
- nota_no_evento: args aluno_nome ou aluno_id, materia_nome. Nota já calculada nas colunas DESTE evento aberto na tela.
- diagnostico_celula: args aluno_nome ou aluno_id, materia_nome (opcional), coluna (opcional, ex.: \Jornada do aluno). Use quando a célula está vazia, com traço, ou o usuário pergunta por quê.

Se o usuário corrigir o tipo (ex.: "no caso é Avaliação Bimestral"), consulte de novo com lancamentos_aluno e o tipo novo. Não explique peça/tipo no lugar de buscar a nota.

Ao responder lançamentos, use nota com vírgula (ex.: 6,00) e cite o professor de cada nota. Exemplo:
JULIA GARCIA CABRAL · 1ªA:
• Professor Eduardo Luis Sinastre · Biologia · Avaliação Bimestral · 1º bimestre: nota 6,00
• Professor Maria Silva · Biologia · Avaliação Bimestral · 1º bimestre: nota 4,00

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
        $titulos = [];
        foreach ($nomes as $rotulo) {
            $rotulo = trim((string) $rotulo);
            if ($rotulo !== '' && !in_array($rotulo, $titulos, true)) {
                $titulos[] = $rotulo;
            }
        }
        $linhas[] = 'Peças: ' . ($pecas !== [] ? implode(', ', $pecas) : '(nenhuma)');
        $linhas[] = 'Títulos das colunas: ' . ($titulos !== [] ? implode(', ', $titulos) : '(Média Bim, Resultado Final e os nomes padrão)');
        $siglasEvt = $this->siglasDoEventoParaFormula($estado);
        $linhas[] = 'Siglas usáveis na fórmula (evento atual): '
            . ($siglasEvt !== [] ? implode(', ', $siglasEvt) : '(ainda sem siglas — use as de USAR NO CÁLCULO)');
        $linhas[] = 'IMPORTANTE: PP/PU/PJ/CH/PE são só exemplos; neste evento use as siglas listadas acima.';
        $linhas[] = 'Fórmulas: ' . ($formulas !== [] ? implode(' | ', $formulas) : '(nenhuma salva no rascunho)');
        $colAberta = $this->nomeColunaAlvoFormula('', $estado);
        $codAberto = strtolower(trim((string) ($estado['bloco_calc'] ?? '')));
        if ($codAberto !== '') {
            $linhas[] = 'Cálculo amarelo aberto agora: ' . $colAberta . ' (código ' . $codAberto . '). '
                . 'Quando sugerir fórmula, use EXATAMENTE este título na linha Coluna = expressão — nunca invente MS 2º ou outro nome se a aberta for outra.';
        } else {
            $linhas[] = 'Nenhum cálculo amarelo aberto. Se sugerir fórmula, pergunte em qual coluna montar, ou use o título que o usuário citar.';
        }
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
        $resposta = $this->tirarMarkupTecnico($resposta);
        if ($this->respostaPareceFormulaTecnicaEnac($resposta)) {
            return $this->textoMediaComEnac();
        }

        return trim($resposta);
    }

    private function tirarMarkupTecnico(string $texto): string
    {
        $texto = preg_replace('/```[a-z]*\s*/i', '', $texto) ?? $texto;
        $texto = str_replace(['```', '**'], '', $texto);
        $texto = preg_replace('/\\\\frac\s*\{([^{}]*)\}\s*\{([^{}]*)\}/', '($1) ÷ ($2)', $texto) ?? $texto;
        $texto = preg_replace('/\\\\text\s*\{([^{}]*)\}/', '$1', $texto) ?? $texto;
        $texto = str_replace(['\\[', '\\]', '\\(', '\\)', '$$'], '', $texto);
        $texto = preg_replace('/\\\\[a-zA-Z]+/', '', $texto) ?? $texto;

        return trim($texto);
    }

    private function respostaPareceFormulaTecnicaEnac(string $texto): bool
    {
        $t = $this->normalizarTexto($texto);
        $temMax = str_contains($t, 'max(') || str_contains($t, 'maior(');
        $temDivisao = preg_match('/\/\s*2|÷\s*2|divid/u', $t) === 1;

        return str_contains($t, 'enac') && $temMax && $temDivisao;
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
                'diagnostico_celula' => $this->diagnosticarCelula($args, $wizardEstado),
                default => ['ok' => false, 'error' => 'Consulta desconhecida.'],
            };
        } catch (\Throwable $e) {
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
            'professor_nome' => trim((string) ($args['professor_nome'] ?? $args['professor'] ?? '')),
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
        foreach (array_slice((array) ($lista['provas'] ?? []), 0, 30) as $prova) {
            if (!is_array($prova)) {
                continue;
            }
            $real = is_array($prova['realizacao'] ?? null) ? $prova['realizacao'] : [];
            $evento = is_array($prova['evento'] ?? null) ? $prova['evento'] : [];
            $materia = is_array($prova['materia'] ?? null) ? $prova['materia'] : [];
            $tipoAv = is_array($prova['tipo_avaliacao'] ?? null) ? $prova['tipo_avaliacao'] : [];
            $prof = is_array($prova['professor'] ?? null) ? $prova['professor'] : [];
            $notaRaw = $real['nota'] ?? null;
            $notaFmt = is_numeric($notaRaw) ? $this->formatarNotaConsulta((float) $notaRaw) : '—';
            $itens[] = [
                'origem' => (string) ($prova['origem'] ?? 'prova_online'),
                'titulo' => (string) ($prova['titulo'] ?? ''),
                'evento' => (string) ($evento['titulo'] ?? ''),
                'data' => (string) ($evento['data_prova'] ?? ''),
                'bimestre' => $evento['bimestre'] ?? null,
                'materia' => (string) ($materia['nome'] ?? ''),
                'tipo' => (string) ($tipoAv['nome'] ?? ''),
                'professor' => (string) ($prof['nome'] ?? ''),
                'nota' => $notaRaw,
                'nota_formatada' => $notaFmt,
                'acertos' => $real['acertos'] ?? null,
                'questoes' => $real['total_questoes'] ?? null,
            ];
        }

        $alunoInfo = is_array($lista['aluno'] ?? null) ? $lista['aluno'] : [];
        $resumo = $this->montarResumoLancamentos($alunoInfo, $itens, $filtros);

        return [
            'ok' => true,
            'aluno' => $lista['aluno'] ?? null,
            'total' => (int) ($lista['total'] ?? count($itens)),
            'lancamentos' => $itens,
            'resumo_para_responder' => $resumo,
        ];
    }

    /**
     * @param array<string,mixed> $aluno
     * @param list<array<string,mixed>> $itens
     * @param array<string,mixed> $filtros
     */
    private function montarResumoLancamentos(array $aluno, array $itens, array $filtros): string
    {
        $nomeAluno = trim((string) ($aluno['nome'] ?? 'Aluno'));
        $turma = trim((string) ($aluno['turma_nome'] ?? ''));
        $cabeca = $nomeAluno . ($turma !== '' ? ' · ' . $turma : '');
        if ($itens === []) {
            $mat = trim((string) ($filtros['materia_nome'] ?? ''));
            $tipo = trim((string) ($filtros['tipo_avaliacao_nome'] ?? ''));
            $bim = (int) ($filtros['bimestre'] ?? 0);
            $ctx = [];
            if ($mat !== '') {
                $ctx[] = $mat;
            }
            if ($tipo !== '') {
                $ctx[] = $tipo;
            }
            if ($bim > 0) {
                $ctx[] = $bim . 'º bimestre';
            }

            return $cabeca . ': não encontrei lançamento'
                . ($ctx !== [] ? ' de ' . implode(', ', $ctx) : '') . '.';
        }

        $linhas = [$cabeca . ':'];
        foreach ($itens as $item) {
            $nota = (string) ($item['nota_formatada'] ?? '—');
            $prof = trim((string) ($item['professor'] ?? ''));
            $tipo = trim((string) ($item['tipo'] ?? ''));
            $mat = trim((string) ($item['materia'] ?? ''));
            $evento = trim((string) ($item['evento'] ?? $item['titulo'] ?? ''));
            $bim = (int) ($item['bimestre'] ?? 0);
            $data = trim((string) ($item['data'] ?? ''));
            $acertos = $item['acertos'] ?? null;
            $questoes = $item['questoes'] ?? null;

            $partes = [];
            if ($prof !== '') {
                $partes[] = 'Professor ' . $prof;
            }
            if ($mat !== '') {
                $partes[] = $mat;
            }
            if ($tipo !== '') {
                $partes[] = $tipo;
            }
            if ($bim > 0) {
                $partes[] = $bim . 'º bimestre';
            }
            if ($evento !== '' && $prof === '') {
                $partes[] = $evento;
            }
            if ($data !== '') {
                $partes[] = $data;
            }
            $nq = '';
            if (is_numeric($acertos) && is_numeric($questoes) && (int) $questoes > 0) {
                $nq = ' (N ' . (int) $acertos . ' / Q ' . (int) $questoes . ')';
            }
            $prefixo = $partes !== [] ? implode(' · ', $partes) : 'Lançamento';
            $linhas[] = '• ' . $prefixo . ': nota ' . $nota . $nq;
        }

        return implode("\n", $linhas);
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

    private function extrairNomeAluno(string $mensagem): string
    {
        if (preg_match('/\b(?:da|do|de|aluno|aluna)\s+([\p{L}][\p{L}\s]{2,60})/ui', $mensagem, $m)) {
            $nome = trim((string) $m[1]);
            $nome = preg_replace('/\b(est[aá]|ficou|tem|na|no|em|com|jornada|nota|coluna|mat[eé]ria|portugu[eê]s|matem[aá]tica|hist[oó]ria|geografia|ingl[eê]s|f[ií]sica|qu[ií]mica|biologia|sociologia|filosofia|literatura|reda[cç][aã]o).*$/ui', '', $nome) ?? $nome;

            return trim($nome);
        }

        return '';
    }

    private function parecePerguntaJornadaDeMateria(string $mensagem): bool
    {
        $t = $this->normalizarTexto($mensagem);
        if (!str_contains($t, 'jornada')) {
            return false;
        }

        return $this->materiasCitadas($mensagem) !== [];
    }

    /**
     * @return list<string>
     */
    private function materiasCitadas(string $mensagem): array
    {
        $t = $this->normalizarTexto($mensagem);
        $map = [
            'educacao fisica' => 'Educação Física',
            'ed fisica' => 'Educação Física',
            'lingua portuguesa' => 'Língua Portuguesa',
            'leitura e interpretacao' => 'Leitura e Interpretação',
            'artes' => 'Arte',
            'arte' => 'Arte',
            'portugues' => 'Português',
            'matematica' => 'Matemática',
            'historia' => 'História',
            'geografia' => 'Geografia',
            'ingles' => 'Inglês',
            'fisica' => 'Física',
            'quimica' => 'Química',
            'biologia' => 'Biologia',
            'sociologia' => 'Sociologia',
            'filosofia' => 'Filosofia',
            'literatura' => 'Literatura',
            'redacao' => 'Redação',
        ];
        $achadas = [];
        foreach ($map as $chave => $rotulo) {
            if (!str_contains($t, $chave)) {
                continue;
            }
            $achadas[$rotulo] = $rotulo;
            $t = str_replace($chave, ' ', $t);
        }

        return array_values($achadas);
    }

    /**
     * @param array<string,mixed>|null $wizardEstado
     */
    private function textoJornadaNasMaterias(string $mensagem, ?array $wizardEstado): ?string
    {
        $nome = $this->extrairNomeAntesDaTurma($mensagem);
        if ($nome === '') {
            return null;
        }
        $turma = $this->extrairTurmaCurta($mensagem);
        $t = $this->normalizarTexto($mensagem);
        $bimestre = 0;
        if (preg_match('/(\d)\s*[ºo]?\s*bimestre/u', $t, $mBim)) {
            $bimestre = (int) $mBim[1];
        }
        $filtros = [
            'aluno_nome' => $nome,
            'turma' => $turma,
            'bimestre' => $bimestre,
        ];
        $resumo = $this->jornadas->resumoJornadasAluno($filtros);
        if ($turma !== '' && (empty($resumo['ok']) || !empty($resumo['candidatos']))) {
            $filtros['turma'] = '';
            $resumo = $this->jornadas->resumoJornadasAluno($filtros);
        }
        if (!empty($resumo['candidatos'])) {
            $nomes = [];
            foreach ((array) $resumo['candidatos'] as $cand) {
                if (!is_array($cand)) {
                    continue;
                }
                $rotulo = trim((string) ($cand['nome'] ?? ''));
                $turmaCand = trim((string) ($cand['turma_nome'] ?? ''));
                if ($rotulo !== '') {
                    $nomes[] = $turmaCand !== '' ? $rotulo . ' (' . $turmaCand . ')' : $rotulo;
                }
            }

            return $nomes === []
                ? 'Encontrei mais de um aluno com esse nome. Diga a turma.'
                : "Encontrei mais de um aluno:\n\n• " . implode("\n• ", $nomes);
        }
        if (empty($resumo['ok'])) {
            return null;
        }

        $aluno = is_array($resumo['aluno'] ?? null) ? $resumo['aluno'] : [];
        $nomeAluno = trim((string) ($aluno['nome'] ?? $nome));
        $turmaAluno = trim((string) ($aluno['turma_nome'] ?? ''));
        $materias = $this->materiasCitadas($mensagem);
        $porMateria = is_array($resumo['por_materia'] ?? null) ? $resumo['por_materia'] : [];
        $linhas = [];
        foreach ($materias as $rotulo) {
            $cadastro = $this->resumoJornadaDaMateria($porMateria, $rotulo);
            $notaBoletim = $this->notaJornadaNoBoletim($rotulo, $nomeAluno, $wizardEstado);
            if ($notaBoletim !== null) {
                $frase = 'no boletim a Jornada do aluno está ' . $this->formatarNotaConsulta($notaBoletim);
            } else {
                $frase = 'no boletim a Jornada do aluno está vazia';
            }
            if ($cadastro !== null) {
                $frase .= '; ' . $cadastro['quantidade'] . ' jornada(s) cadastrada(s) nessa matéria, '
                    . $cadastro['concluidas'] . ' concluída(s)';
            } elseif ($notaBoletim !== null) {
                $frase .= '; não há jornada com o nome ' . $rotulo
                    . '. Esse número é o das jornadas que o evento coloca nessa linha';
            } else {
                $frase .= '; não há jornada cadastrada com o nome ' . $rotulo;
            }
            $linhas[] = $rotulo . ': ' . $frase . '.';
        }

        $outras = [];
        foreach ($porMateria as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nomeMat = trim((string) ($item['materia'] ?? ''));
            if ($nomeMat === '' || $nomeMat === 'Sem matéria') {
                continue;
            }
            $jaCitada = false;
            foreach ($materias as $rotulo) {
                if ($this->nomesMateriaConsultaBatem($nomeMat, $rotulo)) {
                    $jaCitada = true;
                    break;
                }
            }
            if ($jaCitada) {
                continue;
            }
            $outras[] = $nomeMat . ' (' . (int) ($item['concluidas'] ?? 0) . ' de ' . (int) ($item['quantidade'] ?? 0) . ' concluídas)';
        }

        $texto = $nomeAluno . ($turmaAluno !== '' ? ' · ' . $turmaAluno : '') . ".\n\n" . implode("\n", $linhas);
        if ($outras !== []) {
            $texto .= "\n\nJornadas em outras matérias: " . implode('; ', array_slice($outras, 0, 12)) . '.';
        }

        return $texto;
    }

    /**
     * @param list<array<string,mixed>> $porMateria
     * @return array{quantidade:int,concluidas:int}|null
     */
    private function resumoJornadaDaMateria(array $porMateria, string $rotulo): ?array
    {
        $quantidade = 0;
        $concluidas = 0;
        $achou = false;
        foreach ($porMateria as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nomeMat = trim((string) ($item['materia'] ?? ''));
            if (!$this->nomesMateriaConsultaBatem($nomeMat, $rotulo)) {
                continue;
            }
            $achou = true;
            $quantidade += (int) ($item['quantidade'] ?? 0);
            $concluidas += (int) ($item['concluidas'] ?? 0);
        }

        return $achou ? ['quantidade' => $quantidade, 'concluidas' => $concluidas] : null;
    }

    /**
     * @param array<string,mixed>|null $wizardEstado
     */
    private function notaJornadaNoBoletim(string $materia, string $alunoNome, ?array $wizardEstado): ?float
    {
        if (!is_array($wizardEstado)) {
            return null;
        }
        $nota = $this->notaNoEvento([
            'aluno_nome' => $alunoNome,
            'materia_nome' => $materia,
        ], $wizardEstado);
        if (empty($nota['ok']) || empty($nota['linhas']) || !is_array($nota['linhas'])) {
            return null;
        }
        foreach ($nota['linhas'] as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            foreach ((array) ($linha['notas'] ?? []) as $celula) {
                if (!is_array($celula) || !is_numeric($celula['valor'] ?? null)) {
                    continue;
                }
                $coluna = $this->normalizarTexto((string) ($celula['coluna'] ?? ''));
                if (str_contains($coluna, 'jornada')) {
                    return (float) $celula['valor'];
                }
            }
        }

        return null;
    }

    private function extrairNomeAntesDaTurma(string $mensagem): string
    {
        if (preg_match('/([\p{Lu}][\p{L}\']+(?:\s+[\p{Lu}][\p{L}\']+){1,5})\s+d[oa]\s+\d/u', $mensagem, $m)) {
            return trim((string) $m[1]);
        }

        return $this->extrairNomeAluno($mensagem);
    }

    private function extrairTurmaCurta(string $mensagem): string
    {
        if (!preg_match('/\bd[oa]\s+(\d+)\s*[ºªo]?\s*([a-zA-Z])\b/u', $mensagem, $m)) {
            return '';
        }
        $trecho = $m[0];
        if (preg_match('/bimestre/ui', substr($mensagem, (int) strpos($mensagem, $trecho), strlen($trecho) + 12))) {
            return '';
        }

        return $m[1] . 'º' . mb_strtoupper($m[2]);
    }

    private function nomesMateriaConsultaBatem(string $nome, string $pedido): bool
    {
        $nome = str_replace('artes', 'arte', $this->normalizarTexto($nome));
        $pedido = str_replace('artes', 'arte', $this->normalizarTexto($pedido));
        if ($nome === '' || $pedido === '') {
            return false;
        }

        return $nome === $pedido || str_contains($nome, $pedido) || str_contains($pedido, $nome);
    }

    private function formatarNotaConsulta(float $valor): string
    {
        $texto = number_format($valor, 2, ',', '');
        $texto = rtrim(rtrim($texto, '0'), ',');

        return $texto === '' ? '0' : $texto;
    }

    private function extrairMateria(string $mensagem): string
    {
        $t = $this->normalizarTexto($mensagem);
        $materias = [
            'lingua portuguesa', 'portugues', 'matematica', 'historia', 'geografia',
            'educacao fisica', 'ingles', 'fisica', 'quimica', 'biologia', 'sociologia',
            'filosofia', 'literatura', 'redacao', 'leitura e interpretacao',
        ];
        foreach ($materias as $materia) {
            if (str_contains($t, $materia)) {
                return $materia;
            }
        }

        return '';
    }

    private function extrairColuna(string $mensagem): string
    {
        $t = $this->normalizarTexto($mensagem);
        $colunas = [
            'jornada do aluno' => 'jornada',
            'jornada' => 'jornada',
            'media sem' => 'media sem',
            'media bim' => 'media bim',
            'prova bim' => 'prova bim',
            'enac' => 'enac',
            'faltas' => 'faltas',
            'falta' => 'faltas',
        ];
        foreach ($colunas as $chave => $rotulo) {
            if (str_contains($t, $chave)) {
                return $rotulo;
            }
        }
        if (preg_match('/\bs[1-8]\b/', $t, $m)) {
            return $m[0];
        }

        return '';
    }

    /**
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function simularEventoAberto(array $estado, int $alunoId): array
    {
        $montado = $this->wizard->montar($estado);
        $rascunho = is_array($montado['rascunho'] ?? null) ? $montado['rascunho'] : null;
        if ($rascunho === null || empty($rascunho['componentes'])) {
            throw new \Exception('O evento ainda não tem peças para calcular.');
        }
        $rascunho = $this->completarRascunhoSalvo($rascunho, $estado);
        [$periodoRef, $inicio, $fim] = $this->periodoDoRascunho($rascunho, $estado);

        return (new BoletimConfigController(true))->simularRegraAluno(
            $rascunho,
            $alunoId,
            $periodoRef,
            $inicio,
            $fim
        );
    }

    /**
     * @param array<string,string> $colunas
     * @return list<string>
     */
    private function codigosDaColuna(array $colunas, string $colunaAlvo): array
    {
        if ($colunaAlvo === '') {
            return [];
        }
        $codigos = [];
        foreach ($colunas as $cod => $nome) {
            $blob = $this->normalizarTexto($cod . ' ' . $nome);
            if (str_contains($blob, $colunaAlvo) || str_contains($colunaAlvo, $this->normalizarTexto($nome))) {
                $codigos[] = $cod;
            }
        }

        return $codigos;
    }

    private function celulaVazia($valor): bool
    {
        if ($valor === null || $valor === '') {
            return true;
        }
        if (is_string($valor)) {
            $t = trim($valor);

            return $t === '' || $t === '-' || $t === '—';
        }

        return false;
    }

    private function formatarValorCelula($valor): string
    {
        if (is_numeric($valor)) {
            return rtrim(rtrim(number_format((float) $valor, 2, ',', ''), '0'), ',');
        }

        return trim((string) $valor);
    }

    /**
     * @param array<string,mixed> $simulacao
     * @param array<string,mixed> $estado
     */
    private function causaCelulaVazia(array $simulacao, string $colunaAlvo, array $estado): string
    {
        $jornada = $colunaAlvo === '' || str_contains($colunaAlvo, 'jornada');
        foreach ((array) ($simulacao['componentes'] ?? []) as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $nome = $this->normalizarTexto((string) ($comp['nome'] ?? '') . ' ' . (string) ($comp['codigo'] ?? ''));
            $origem = strtolower(trim((string) ($comp['source_type'] ?? '')));
            $ehJornada = $origem === 'jornadas' || str_contains($nome, 'jornada');
            if ($jornada && !$ehJornada) {
                continue;
            }
            if (!$jornada && $colunaAlvo !== '' && !str_contains($nome, $colunaAlvo)) {
                continue;
            }
            $det = is_array($comp['detalhes'] ?? null) ? $comp['detalhes'] : [];
            $erro = trim((string) ($det['erro'] ?? ''));
            if ($erro !== '') {
                return $erro . ' Ajuste em Peças e simule de novo.';
            }
            $aviso = trim((string) ($det['aviso_jornadas'] ?? ''));
            $total = (int) ($det['total_jornadas_escopo'] ?? 0);
            if ($ehJornada && $total <= 0) {
                $bims = array_filter(array_map('intval', (array) ($estado['jornada_bimestres'] ?? [])));
                $onde = $bims === []
                    ? 'Em Peças, marque o bimestre da Jornada do aluno.'
                    : 'As jornadas desse bimestre não estão ligadas à turma deste aluno. Confira a turma da jornada e do aluno.';

                return ($aviso !== '' ? $aviso . ' ' : '') . $onde;
            }
            if ($ehJornada && $total > 0) {
                $concl = (int) ($det['concluidas'] ?? 0);

                return 'O aluno tem ' . $concl . ' de ' . $total . ' jornadas do bimestre. A nota entra na matéria com o mesmo nome da jornada. Onde o nome não bate, a célula fica com traço. Em Peças, confira o bimestre e a tabela por faixas.';
            }
            if ($this->celulaVazia($comp['valor'] ?? null)) {
                return 'Essa coluna não recebeu lançamento deste aluno no bimestre da peça. Confira em Lançamento de Notas se o tipo e o bimestre são os mesmos da peça.';
            }
        }
        if ((int) ($estado['aluno_preview_id'] ?? 0) <= 0) {
            return 'Escolha o aluno em Revisar. Sem aluno, a tabela de baixo é só um exemplo.';
        }

        return 'A peça dessa coluna está sem nota neste bimestre. Em Peças, confira se ela está marcada e se o bimestre é o mesmo do lançamento.';
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = str_replace(
            ['á', 'à', 'â', 'ã', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç', 'ª', 'º'],
            ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c', 'a', 'o'],
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
            throw new \Exception('TudiCoins desabilitado para esta escola. A consulta de notas com IA não está disponível.');
        }
        if (!$creditos->podeConsumir(
            'escola',
            \App\Core\CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID,
            BoletimAssistenteService::MODULO_CREDITOS
        )) {
            throw new \Exception('TudiCoins insuficientes na carteira da escola.');
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

if (!class_exists('BoletimConsultaAssistenteService', false) && class_exists(__NAMESPACE__ . '\\BoletimConsultaAssistenteService', false)) {
    class_alias(__NAMESPACE__ . '\\BoletimConsultaAssistenteService', 'BoletimConsultaAssistenteService');
}
