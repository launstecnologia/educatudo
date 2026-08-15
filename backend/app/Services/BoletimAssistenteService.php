<?php
/**
 * Assistente de Boletim — traduz linguagem natural em rascunho de regra
 * (componentes + fórmula + turmas/matérias/agrupamento) sem salvar automaticamente.
 *
 * Chat: streaming SSE da mensagem ao usuário; rascunho JSON após <<<RASCUNHO>>>.
 * Exceção de produto: turno conversacional na request (como Tudinha/Apostila).
 */

require_once __DIR__ . '/OpenAIService.php';
require_once __DIR__ . '/BoletimAssistenteFerramentas.php';
require_once __DIR__ . '/CreditosService.php';
require_once __DIR__ . '/../Core/CreditosModuleRegistry.php';

class BoletimAssistenteService
{
    public const MODULO_CREDITOS = 'boletim_assistente_mensagem';
    public const DELIMITADOR_RASCUNHO = '<<<RASCUNHO>>>';

    private BoletimAssistenteFerramentas $ferramentas;
    /** @var \App\Services\OpenAIService */
    private $openai;

    public function __construct(
        ?BoletimAssistenteFerramentas $ferramentas = null,
        $openai = null
    ) {
        $this->ferramentas = $ferramentas ?? new BoletimAssistenteFerramentas();
        $this->openai = $openai instanceof \App\Services\OpenAIService
            ? $openai
            : new \App\Services\OpenAIService();
    }

    public function ferramentas(): BoletimAssistenteFerramentas
    {
        return $this->ferramentas;
    }

    /** Atalho público (controller): export/import de receita sem OpenAI. */
    public function tentarAtalhoReceitaPublico(string $mensagemUsuario, ?array $estadoFormulario): ?array
    {
        return $this->tentarAtalhoReceita($mensagemUsuario, $estadoFormulario);
    }

    public function mensagemPareceReceita(string $mensagem): bool
    {
        return $this->pareceReceitaColada($mensagem)
            || $this->parecePedidoExportarReceita($mensagem);
    }

    /**
     * @param list<array{role:string,content:string}> $historico
     * @param array<string,mixed>|null $estadoFormulario
     * @return array{success:bool,mensagem?:string,rascunho?:?array,acao?:string,erros?:list<string>,tokens_usados?:int,error?:string}
     */
    public function processarMensagem(
        string $mensagemUsuario,
        array $historico = [],
        ?array $estadoFormulario = null,
        ?int $regraIdAtual = null,
        ?array $wizardEstado = null
    ): array {
        $atalho = $this->tentarAtalhoReceita($mensagemUsuario, $estadoFormulario);
        if ($atalho !== null) {
            return $atalho;
        }

        $prep = $this->prepararContexto($mensagemUsuario, $historico, $estadoFormulario, $regraIdAtual, $wizardEstado);
        if (!$prep['ok']) {
            return ['success' => false, 'error' => $prep['error']];
        }

        try {
            $raw = $this->chamarModelo($prep['mensagens'], $prep['system_json']);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteService: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Falha ao consultar a IA. Tente novamente.'];
        }

        try {
            $this->debitarCreditos();
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return $this->montarResultadoDeRespostaModelo((string) ($raw['resposta'] ?? ''), (int) ($raw['tokens_usados'] ?? 0));
    }

    /**
     * Streaming: emite pedaços de texto via $onTextoChunk; no fim processa o rascunho.
     *
     * @param callable(string):void $onTextoChunk
     * @param list<array{role:string,content:string}> $historico
     * @param array<string,mixed>|null $estadoFormulario
     * @param callable(string):void|null $onFase Ex.: texto_pronto (mensagem visível; JSON ainda gerando)
     * @return array{success:bool,mensagem?:string,rascunho?:?array,acao?:string,erros?:list<string>,error?:string}
     */
    public function processarMensagemStream(
        string $mensagemUsuario,
        callable $onTextoChunk,
        array $historico = [],
        ?array $estadoFormulario = null,
        ?int $regraIdAtual = null,
        ?array $wizardEstado = null,
        ?callable $onFase = null
    ): array {
        $atalho = $this->tentarAtalhoReceita($mensagemUsuario, $estadoFormulario);
        if ($atalho !== null) {
            $msg = (string) ($atalho['mensagem'] ?? '');
            if ($msg !== '') {
                $onTextoChunk($msg);
            }
            return $atalho;
        }

        $prep = $this->prepararContexto($mensagemUsuario, $historico, $estadoFormulario, $regraIdAtual, $wizardEstado);
        if (!$prep['ok']) {
            return ['success' => false, 'error' => $prep['error']];
        }

        $full = '';
        $textoEnviadoLen = 0;
        $textoPronto = false;
        $sep = self::DELIMITADOR_RASCUNHO;
        $sepLen = strlen($sep);

        try {
            $this->chamarModeloStream($prep['mensagens'], $prep['system_stream'], function ($chunk) use (
                &$full,
                &$textoEnviadoLen,
                &$textoPronto,
                $sep,
                $sepLen,
                $onTextoChunk,
                $onFase
            ) {
                $full .= $chunk;
                $pos = strpos($full, $sep);
                if ($pos === false) {
                    $safeEnd = max(0, strlen($full) - ($sepLen - 1));
                    $textoEnviadoLen = $this->emitirTextoUtf8Seguro($full, $textoEnviadoLen, $safeEnd, $onTextoChunk);
                    return;
                }
                if ($pos > $textoEnviadoLen) {
                    $textoEnviadoLen = $this->emitirTextoUtf8Seguro($full, $textoEnviadoLen, $pos, $onTextoChunk);
                }
                if (!$textoPronto) {
                    $textoPronto = true;
                    if ($onFase !== null) {
                        $onFase('texto_pronto');
                    }
                }
            });
        } catch (Throwable $e) {
            error_log('BoletimAssistenteService stream: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Falha ao consultar a IA. Tente novamente.'];
        }

        $pos = strpos($full, $sep);
        if ($pos === false) {
            $this->emitirTextoUtf8Seguro($full, $textoEnviadoLen, strlen($full), $onTextoChunk);
            try {
                $this->debitarCreditos();
            } catch (Throwable $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
            $mensagem = trim($full);
            return [
                'success' => true,
                'acao' => 'esclarecimento',
                'mensagem' => $mensagem !== '' ? $mensagem : 'Pode detalhar a regra de média?',
                'rascunho' => null,
                'erros' => [],
            ];
        }

        $this->emitirTextoUtf8Seguro($full, $textoEnviadoLen, $pos, $onTextoChunk);

        $mensagem = trim(substr($full, 0, $pos));
        $jsonRaw = trim(substr($full, $pos + $sepLen));

        try {
            $this->debitarCreditos();
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $parsed = $this->parseRespostaJson($jsonRaw);
        if ($parsed === null) {
            return [
                'success' => true,
                'acao' => 'esclarecimento',
                'mensagem' => $mensagem !== '' ? $mensagem : 'Pode reformular o pedido?',
                'rascunho' => null,
                'erros' => [],
            ];
        }

        if ($mensagem === '' && !empty($parsed['mensagem'])) {
            $mensagem = trim((string) $parsed['mensagem']);
        } else {
            $parsed['mensagem'] = $mensagem;
        }

        return $this->finalizarParsed($parsed, $mensagem);
    }

    /**
     * @param list<array{role:string,content:string}> $historico
     * @return array{ok:bool,error?:string,mensagens?:list<array>,system_json?:string,system_stream?:string}
     */
    private function prepararContexto(
        string $mensagemUsuario,
        array $historico,
        ?array $estadoFormulario,
        ?int $regraIdAtual,
        ?array $wizardEstado = null
    ): array {
        $mensagemUsuario = trim($mensagemUsuario);
        if ($mensagemUsuario === '') {
            return ['ok' => false, 'error' => 'Digite o que deseja configurar no boletim.'];
        }
        $limite = $this->pareceReceitaColada($mensagemUsuario) ? 12000 : 8000;
        if (mb_strlen($mensagemUsuario) > $limite) {
            return ['ok' => false, 'error' => 'Mensagem muito longa (máx. ' . $limite . ' caracteres).'];
        }

        try {
            $this->assertCreditosDisponiveis();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $catalogo = [];
        try {
            $catalogo = $this->ferramentas->montarContextoCatalogo();
        } catch (Throwable $e) {
            error_log('BoletimAssistente catalogo: ' . $e->getMessage());
            $catalogo = [
                'tipos_avaliacao' => [],
                'turmas' => [],
                'materias' => [],
                'regras_existentes' => [],
                'eventos_prova_recentes' => [],
                'quadro_semanal' => [
                    'semanas_com_evento' => [],
                    'tipos_por_chave' => [],
                    'grupos_materias' => ['A' => [], 'B' => []],
                    'dica' => '',
                ],
            ];
        }
        $regraAtual = null;
        if ($regraIdAtual !== null && $regraIdAtual > 0) {
            try {
                $regraAtual = $this->ferramentas->obterRegra($regraIdAtual);
            } catch (Throwable $e) {
                $regraAtual = null;
            }
        }

        $mensagens = [];
        foreach (array_slice($historico, -12) as $msg) {
            $role = (string) ($msg['role'] ?? '');
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }
            $mensagens[] = [
                'role' => $role,
                'content' => mb_substr($content, 0, 2000),
            ];
        }
        $mensagens[] = ['role' => 'user', 'content' => $mensagemUsuario];

        return [
            'ok' => true,
            'mensagens' => $mensagens,
            'system_json' => $this->montarSystemPrompt($catalogo, $regraAtual, $estadoFormulario, false, $wizardEstado),
            'system_stream' => $this->montarSystemPrompt($catalogo, $regraAtual, $estadoFormulario, true, $wizardEstado),
        ];
    }

    /**
     * @return array{success:bool,mensagem:string,rascunho:?array,acao:string,erros:list<string>,tokens_usados?:int}
     */
    private function montarResultadoDeRespostaModelo(string $rawResposta, int $tokensUsados): array
    {
        $pos = strpos($rawResposta, self::DELIMITADOR_RASCUNHO);
        if ($pos !== false) {
            $mensagem = trim(substr($rawResposta, 0, $pos));
            $parsed = $this->parseRespostaJson(trim(substr($rawResposta, $pos + strlen(self::DELIMITADOR_RASCUNHO))));
            if ($parsed === null) {
                return [
                    'success' => true,
                    'acao' => 'esclarecimento',
                    'mensagem' => $mensagem !== '' ? $mensagem : 'Pode reformular?',
                    'rascunho' => null,
                    'erros' => [],
                    'tokens_usados' => $tokensUsados,
                ];
            }
            if ($mensagem !== '') {
                $parsed['mensagem'] = $mensagem;
            }
            $out = $this->finalizarParsed($parsed, $mensagem);
            $out['tokens_usados'] = $tokensUsados;
            return $out;
        }

        $parsed = $this->parseRespostaJson($rawResposta);
        if ($parsed === null) {
            return [
                'success' => true,
                'acao' => 'esclarecimento',
                'mensagem' => trim($rawResposta)
                    ?: 'Não consegui montar a configuração. Pode reformular (ex.: média de semanal + bimestral)?',
                'rascunho' => null,
                'erros' => [],
                'tokens_usados' => $tokensUsados,
            ];
        }
        $out = $this->finalizarParsed($parsed, trim((string) ($parsed['mensagem'] ?? '')));
        $out['tokens_usados'] = $tokensUsados;
        return $out;
    }

    /**
     * @param array<string,mixed> $parsed
     * @return array{success:bool,mensagem:string,rascunho:?array,acao:string,erros:list<string>}
     */
    private function finalizarParsed(array $parsed, string $mensagemPreferida): array
    {
        $acao = (string) ($parsed['acao'] ?? 'esclarecimento');
        $mensagem = trim($mensagemPreferida !== '' ? $mensagemPreferida : (string) ($parsed['mensagem'] ?? ''));
        $rascunho = null;
        $erros = [];

        if (!empty($parsed['rascunho']) && is_array($parsed['rascunho'])) {
            $validado = $this->ferramentas->validarEEnriquecerRascunho($parsed['rascunho']);
            $rascunho = $validado['rascunho'];
            $erros = $validado['erros'];
            if ($validado['ok']) {
                $acao = 'rascunho';
                if ($mensagem === '') {
                    $mensagem = 'Rascunho pronto. Revise no formulário e clique em Salvar regra quando estiver ok.';
                }
            } else {
                $acao = 'esclarecimento';
                if ($mensagem === '') {
                    $mensagem = 'Preciso de mais detalhes: ' . implode(' ', $erros);
                }
            }
        }

        if ($mensagem === '') {
            $mensagem = $acao === 'rascunho'
                ? 'Rascunho aplicado ao formulário (ainda não salvo).'
                : 'Pode detalhar turmas, matérias, datas ou tipos de prova (semanal, bimestral, ENAC…)?';
        }

        return [
            'success' => true,
            'acao' => $acao,
            'mensagem' => $mensagem,
            'rascunho' => $rascunho,
            'erros' => $erros,
        ];
    }

    /**
     * Exportar/importar receita sem OpenAI (determinístico, sem debitar TudiCoins).
     *
     * @return array{success:bool,mensagem?:string,rascunho?:?array,acao?:string,erros?:list<string>,receita?:string}|null
     */
    private function tentarAtalhoReceita(string $mensagemUsuario, ?array $estadoFormulario): ?array
    {
        $mensagemUsuario = trim($mensagemUsuario);
        if ($mensagemUsuario === '') {
            return null;
        }

        if ($this->parecePedidoExportarReceita($mensagemUsuario)) {
            $estado = is_array($estadoFormulario) ? $estadoFormulario : [];
            $comps = is_array($estado['componentes'] ?? null) ? $estado['componentes'] : [];
            if (trim((string) ($estado['nome'] ?? '')) === '' && $comps === []) {
                return [
                    'success' => true,
                    'acao' => 'esclarecimento',
                    'mensagem' => 'Não há configuração no formulário para exportar. Monte a regra ou carregue um evento e tente de novo (ou use o botão Copiar receita).',
                    'rascunho' => null,
                    'erros' => [],
                ];
            }
            $receita = $this->ferramentas->formatarReceitaTexto($estado);
            return [
                'success' => true,
                'acao' => 'receita',
                'mensagem' => "Receita pronta. Copie o bloco abaixo e cole em outro Assistente de Boletim — ele monta igual. Edite o que quiser antes de colar.\n\n" . $receita,
                'rascunho' => null,
                'erros' => [],
                'receita' => $receita,
            ];
        }

        $parsed = $this->ferramentas->tentarParseReceita($mensagemUsuario);
        if ($parsed === null) {
            return null;
        }

        if (empty($parsed['ok']) || empty($parsed['rascunho']) || !is_array($parsed['rascunho'])) {
            return [
                'success' => true,
                'acao' => 'esclarecimento',
                'mensagem' => trim((string) ($parsed['aviso'] ?? ''))
                    ?: 'Não consegui ler essa receita. No chat de origem use o botão Copiar receita e cole o texto completo aqui.',
                'rascunho' => null,
                'erros' => [],
            ];
        }

        $aviso = trim((string) ($parsed['aviso'] ?? ''));
        $mensagem = 'Receita importada e aplicada no formulário (ainda não salva). Revise e clique em Salvar regra quando estiver ok.';
        if ($aviso !== '') {
            $mensagem .= "\n\n" . $aviso;
        }

        return [
            'success' => true,
            'acao' => 'rascunho',
            'mensagem' => $mensagem,
            'rascunho' => $parsed['rascunho'],
            'erros' => [],
        ];
    }

    private function parecePedidoExportarReceita(string $mensagem): bool
    {
        $m = mb_strtolower(trim($mensagem));
        if (in_array($m, [
            'copiar receita',
            'exportar receita',
            'me dá a receita',
            'me da a receita',
            'gerar receita',
            'mostrar receita',
            'exportar config',
            'copiar config',
            'config completa',
        ], true)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(copiar?|exportar?|gerar|mostrar|me\s+d[aá])\b[\s\S]{0,40}\b(receita|configura[cç][aã]o|config)\b/u',
            $m
        ) || (bool) preg_match(
            '/\b(receita|configura[cç][aã]o)\b[\s\S]{0,40}\b(copiar?|exportar?|colar|outro\s+chat)\b/u',
            $m
        );
    }

    private function pareceReceitaColada(string $mensagem): bool
    {
        return (bool) preg_match('/#\s*RECEITA_BOLETIM\b/i', $mensagem)
            || (bool) preg_match('/\[\[componente\]\]/i', $mensagem)
            || (
                (bool) preg_match('/^\s*-\s*Nome\s*:/im', $mensagem)
                && (bool) preg_match('/Componentes\s*:/i', $mensagem)
            );
    }

    private function assertCreditosDisponiveis(): void
    {
        if (!class_exists('CreditosModuleRegistry', false)) {
            require_once __DIR__ . '/../Core/CreditosModuleRegistry.php';
        }
        if (!class_exists('App\\Services\\CreditosService', false)) {
            require_once __DIR__ . '/CreditosService.php';
        }
        if (!CreditosModuleRegistry::isValid(self::MODULO_CREDITOS)) {
            return;
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        if (!$creditos->podeConsumir(
            'escola',
            \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID,
            self::MODULO_CREDITOS
        )) {
            throw new \Exception('TudiCoins insuficientes na carteira da escola.');
        }
    }

    private function debitarCreditos(): void
    {
        if (!class_exists('CreditosModuleRegistry', false)) {
            require_once __DIR__ . '/../Core/CreditosModuleRegistry.php';
        }
        if (!class_exists('App\\Services\\CreditosService', false)) {
            require_once __DIR__ . '/CreditosService.php';
        }
        if (!CreditosModuleRegistry::isValid(self::MODULO_CREDITOS)) {
            return;
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        $creditos->consumirEscola(self::MODULO_CREDITOS, 'boletim_assistente');
    }

    private function montarSystemPrompt(
        array $catalogo,
        ?array $regraAtual,
        ?array $estadoFormulario,
        bool $modoStream,
        ?array $wizardEstado = null
    ): string {
        $schemaRascunho = <<<'JSON'
{
  "acao": "rascunho|esclarecimento|nenhuma",
  "rascunho": {
    "modo": "criar|editar",
    "regra_id": null,
    "nome": "string",
    "codigo": "slug-opcional",
    "descricao_curta": "string",
    "formula_final": "(semanal + bimestral) / 2",
    "exibir_em": "notas|boletim",
    "ano_letivo": 2026,
    "bimestre": 1,
    "default_data_inicio": "YYYY-MM-DD",
    "default_data_fim": "YYYY-MM-DD",
    "turmas_ids": [],
    "materias_ids": [],
    "series_ids": [],
    "round_mode": "none|half",
    "nota_minima_aprovacao": 6.0,
    "componentes": [
      {
        "codigo": "semanal",
        "nome": "Prova semanal",
        "source_type": "provas_sistema|jornadas|calculado|evento_boletim|faltas_evento|nenhuma",
        "calc_type": "media",
        "tipo_avaliacao_id": 1,
        "tipo_avaliacao_nome": "Prova Semanal",
        "blocos_ids": [],
        "filtro_titulo": "",
        "materias_ids": [],
        "materia_unica": 0,
        "usar_percentual": 1,
        "config": {
          "expressao": "(c1 + c2) / 2",
          "formula_mode": "single",
          "formula_materias": {},
          "semana": 1,
          "agregar_nq": ["s1", "s3", "s5", "s7"],
          "regra_codigo": "slug-do-evento-de-notas",
          "componente_codigo": "media_final",
          "faltas_evento_id": 0,
          "layout_group": "b1|b2|b3|b4|final|quadro_a|quadro_b|quadro_comum",
          "layout_type": "media|faltas|rec|resultado|semana_nq|media_sem",
          "group_line": {
            "enabled": false,
            "key": "",
            "label": "",
            "mode": "media",
            "divisor": 1,
            "materias_ids": []
          }
        }
      }
    ]
  }
}
JSON;

        $catalogoJson = json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $regraJson = $regraAtual
            ? json_encode($this->enxugarRascunhoParaContexto($regraAtual), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : 'null';
        $formJson = $estadoFormulario
            ? json_encode($this->enxugarEstadoParaContexto($estadoFormulario), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : 'null';
        $wizardJson = $wizardEstado
            ? json_encode($this->enxugarEstadoParaContexto($wizardEstado), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : 'null';

        $regrasProduto = <<<'TXT'
Regras do produto:
- Notas vêm de Eventos de Prova (provas_blocos) com tipo em provas_tipos_avaliacao (Semanal, Bimestral, ENAC, Trabalho…).
- Prefira tipo_avaliacao_id / tipo_avaliacao_nome do catálogo; o sistema resolve os blocos. Não invente IDs.
- calc_type: media|soma|maior|ultima. source_type padrão: provas_sistema.
- materia_unica=1: junta/soma notas da MESMA matéria quando há mais de um professor (matérias únicas). Use quando o coordenador pedir "juntar matérias", "somar professores da mesma matéria", "matéria única".
- usar_percentual=1 em provas_sistema: nota por acertos/questões.
- Para agrupar matérias use config.group_line (enabled, key, label, mode media|soma, materias_ids).
- turmas_ids / materias_ids / series_ids: só IDs existentes no catálogo.
- Fórmula final usa os códigos dos componentes com + - * / ( ) max min.
- modo=editar só se houver regra_id atual; senão criar.
- Se faltar dado crítico (tipo, turma/matéria quando o usuário restringir o escopo, fórmula), use acao=esclarecimento e pergunte (rascunho null).
- Datas são opcionais e legadas; no wizard, provas e jornadas devem ser resolvidas pelo bimestre da peça/regra.
- Não invente matérias/turmas/tipos que não estejam no catálogo.
- MEMÓRIA: use o histórico da conversa e o estado do formulário. NÃO peça de novo datas, turmas, matérias, tipos ou fórmula que o usuário já informou. Acumule e complete o rascunho a cada turno.
- Sempre devolva a lista COMPLETA de componentes (não só o bloco novo). Ao acrescentar um bloco, mantenha os anteriores.
- Se o usuário pedir um "terceiro bloco" / "bloco de média" / "coluna com a média", CRIE um componente source_type=calculado (ex.: codigo media_final, nome "Média") com config.expressao usando os códigos dos outros blocos, ex.: (semanal + bimestral) / 2. NÃO deixe a média só em formula_final — precisa aparecer em "Blocos da regra".
- Coloque o bloco calculado DEPOIS dos blocos que ele referencia. formula_final pode ser o codigo do calculado (ex.: media_final).
- Se o usuário disser "todas as matérias", deixe materias_ids vazio (significa todas).
- Se disser uma série (ex.: 3º ano EM), resolva as turmas dessa série pelo catálogo (turmas_ids) quando possível.
- "somar questões acertadas e fazer média conforme quantidade" = provas_sistema com usar_percentual=1 e calc_type=media.
- POR PEÇA: o coordenador pode pedir nota do evento (calc_type=ultima, padrão em bimestral/ENAC/trabalho), média ou somatória. Acertos/questões (usar_percentual=1) só em semanal (padrão) ou se pedir em bimestral/ENAC.
- PAPEL NO BOLETIM: independente do tipo. pecas_opcoes[chave].papel = media|depois|so_melhora|substitui|exibe.
  media = 1ª média (parcial); depois = entra na média final com o resultado da parcial, ex.: ((bimestral + semanal) / 2 + enac) / 2; so_melhora = max(media, (media+peça)/2); substitui = max(media, peça); exibe = coluna fora da fórmula.
  Grave config.papel_wizard. Preferência: passo Exibir. estado.formulas_blocos[codigo] = tokens gerais; estado.formulas_materias_blocos[codigo][materia_id] = tokens da exceção (matéria sem semanal → só bimestral+ENAC). nomes_blocos[codigo] = nome visível. Tokens viram config.expressao; exceções viram config.formula_materias + formula_mode=per_materia. formula_tokens = bloco aberto (bloco_calc).
- JUNÇÃO DE MATÉRIAS: "juntar Português dos dois professores", "matérias únicas" → materia_unica=1 em TODOS os componentes de prova/jornada (no wizard isso é um único controle no passo Matérias, estado.materia_unica). "juntar Português + Literatura + Gramática numa linha Linguagem Português" → config.group_line.
- WIZARD: se houver "Estado do wizard" no contexto, respeite peças/pecas_opcoes/formulas_blocos/formulas_materias_blocos/nomes_blocos/formula_tokens/bloco_calc/materia_calc/colunas_ordem/fontes_bimestres/fontes_faltas/formula_preset/matérias/jornadas/grupo_linha/materia_unica/series_ids/turmas_ids. Passos Notas: Começar → Identidade → Peças → Exibir → Matérias → Revisar. Se exibir_em=boletim, NÃO monte peças/quadro: layout oficial 1º–4º bimestre (Média + Faltas) + FINAL (Média, Rec., Faltas, Resultado). Preencha estado.fontes_bimestres[1..4] com IDs dos eventos de Notas e estado.fontes_faltas[1..4] com IDs dos eventos de faltas. Componentes: evento_boletim (layout_type media) + faltas_evento (layout_type faltas) por bimestre, media_final calculada, rec_final nenhuma, faltas_final soma, resultado nenhuma (layout_group final).
- MATÉRIAS DO EVENTO: se pedir "só Matemática e Português" / "sem Educação Física", preencha materias_ids do rascunho (IDs do catálogo). Vazio = todas.
- PROVAS: use tipo_avaliacao_id (catálogo) e config.prova_bimestres para o sistema resolver blocos_ids. Não invente IDs de bloco.
- JORNADAS: use config.jornada_bimestres e preencha config.jornada_ids com as jornadas vinculadas ao bimestre da peça/regra. Pontuação é por CONCLUSÃO (não acerto de questões). Nota linear: usar_percentual=1 (100% concluídas = 10). Tabela: usar_percentual=0 e config.faixas_percentuais [{percentual_min, nota}] (ex.: 90→10, 80→9, 70→8). Se o usuário pedir “jornadas do 2º bimestre”, filtre o catálogo por bimestre e preencha jornada_ids.
- TURMAS/SÉRIES: series_ids e turmas_ids do catálogo; se citar série, preferir series_ids (ou turmas da série). No wizard isso fica no passo Identidade (no começo), não no passo Matérias.
- RECEITA PORTÁVEL: se o usuário colar um bloco começando com "# RECEITA_BOLETIM" ou com "[[componente]]", reconstrua o rascunho EXATAMENTE (incluindo expressao dos calculados). Se pedir "copiar receita" / "exportar config", responda com a receita completa no formato RECEITA_BOLETIM v1 (campos chave:valor e [[componente]] por bloco, com expressao nos calculados).
- Em componentes calculado, SEMPRE preencha config.expressao com os códigos (ex.: (c1 + c2) / 2), EXCETO media_sem do quadro: aí use config.agregar_nq (lista de códigos s1..s8) e expressao vazia — a nota é (soma N / soma Q) × 10, não a média das notas 0–10.
- Se o usuário colar markdown incompleto (sem expressao nos calculados), monte o que der e pergunte apenas as fórmulas faltantes; não peça de novo IDs já listados.
- QUADRO SEMANAL (pedido do tipo "monte o quadro", "notas semanais", "S1 S3 S5", "N e Q", "média sem + prova bim + ENAC + rec"):
  1) Olhe catalogo.quadro_semanal (tipos_por_chave, semanas_com_evento, grupos_materias A/B).
  2) Crie um componente por semana existente (codigo s1..s8, SEM hífen). source_type=provas_sistema, tipo_avaliacao do catálogo com chave_quadro=semanal, config.semana=1..8, usar_percentual=1, materia_unica=1, layout_type=semana_nq. layout_group=quadro_a para semanas ímpares (1,3,5,7) e quadro_b para pares (2,4,6,8), salvo se o usuário/config disser o contrário.
  3) Só crie a semana se houver evento com essa semana OU o usuário pedir o quadro completo (aí crie S1–S8 mesmo vazio).
  4) media_sem: source_type=calculado, config.agregar_nq com TODOS os códigos de semana, layout_group=quadro_comum, layout_type=media_sem. Isso soma acertos (N) e questões (Q) das semanas da linha — matérias do bloco A só têm notas nas ímpares, então a média fecha certo.
  5) prova_bim: tipo chave_quadro=prova_bim (ou nome bimestral), calc_type=ultima (nota lançada no evento), usar_percentual=0, materia_unica=1, layout_group=quadro_comum.
  6) ENAC / Part / Trab: só se o tipo existir no catálogo (chave enac, participacao, trabalho). calc_type=ultima, usar_percentual=0 (nota lançada). Nunca ligue acertos/questões em Part/Trab/Rec. Em ENAC só se o usuário pedir.
  7) rec: tipo recuperacao, usar_percentual=0. SEMPRE crie media_bim (calculado, layout_group=quadro_comum) e, se houver rec, media_final = max(media_bim, rec). Sem rec, formula_final=media_bim. Sem media_bim o quadro não fecha.
  8) media_bim: calculado com média (ou pesos que o usuário pedir) de media_sem + prova_bim + peças opcionais presentes. NÃO invente ENAC se não houver tipo.
  9) GRUPO DE MATÉRIAS: catalogo.quadro_semanal.grupos_materias lista A/B. Não misture as semanas dos dois blocos na mesma coluna. Se pedir "juntar matérias" / "linha única de humanas", use config.group_line (enabled, key, label, mode media|soma, materias_ids).
  10) PROFESSORES DIFERENTES da mesma matéria: materia_unica=1 em TODOS os blocos de prova do quadro (soma/média na mesma linha).
  11) Códigos permitidos: s1, media_sem, prova_bim, enac, part, trab, rec, media_bim, media_final. Nunca use hífen no código.
  12) Se o usuário for acrescentando ("agora add ENAC", "coloca recuperação"), mantenha os blocos já montados e só inclua o novo + ajuste media_bim/media_final.
  13) Se faltar só o peso da média bimestral, pergunte com exemplo: (media_sem + prova_bim) / 2 ou (media_sem * 2 + prova_bim) / 3.
TXT;

        if ($modoStream) {
            $delim = self::DELIMITADOR_RASCUNHO;
            return <<<PROMPT
Você é o assistente de configuração de boletim do EducaTudo (coordenação pedagógica).

Formato da resposta (obrigatório para streaming):
1) Escreva primeiro a mensagem ao usuário em português (texto puro, sem JSON e sem markdown).
2) Em uma linha sozinha, escreva exatamente: {$delim}
3) Em seguida um único JSON válido (sem markdown) neste formato:
{$schemaRascunho}

{$regrasProduto}

Catálogo da escola (JSON):
{$catalogoJson}

Regra atual carregada (se houver):
{$regraJson}

Estado do formulário na tela (rascunho do usuário):
{$formJson}

Estado do wizard guiado (escolhas pedagógicas; null se não estiver no modo guiado):
{$wizardJson}
PROMPT;
        }

        return <<<PROMPT
Você é o assistente de configuração de boletim do EducaTudo (coordenação pedagógica).
Responda SEMPRE com um único JSON válido (sem markdown) no formato:
{
  "mensagem": "texto curto em português para o usuário",
  "acao": "rascunho|esclarecimento|nenhuma",
  "rascunho": ... mesmo schema do rascunho abaixo ou null
}
Schema do rascunho:
{$schemaRascunho}

{$regrasProduto}

Catálogo da escola (JSON):
{$catalogoJson}

Regra atual carregada (se houver):
{$regraJson}

Estado do formulário na tela (rascunho do usuário):
{$formJson}

Estado do wizard guiado (escolhas pedagógicas; null se não estiver no modo guiado):
{$wizardJson}

IMPORTANTE: responda apenas com JSON válido (objeto), sem markdown.
PROMPT;
    }

    /**
     * @param list<array{role:string,content:string}> $mensagens
     * @return array{resposta:string,tokens_usados:int}
     */
    private function chamarModelo(array $mensagens, string $system): array
    {
        return $this->openai->chatCompletion(
            $mensagens,
            $system,
            'gpt-4o-mini',
            0.2,
            4500,
            false
        );
    }

    /**
     * @param list<array{role:string,content:string}> $mensagens
     */
    private function chamarModeloStream(array $mensagens, string $system, callable $onChunk): void
    {
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($mensagens as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $modelo = 'gpt-4o-mini';
        $requestData = [
            'model' => $modelo,
            'messages' => $messages,
        ];

        if (!class_exists('App\\AI\\ModeloIA', false)) {
            $p = __DIR__ . '/../AI/ModeloIA.php';
            if (is_file($p)) {
                require_once $p;
            }
        }
        if (class_exists('App\\AI\\ModeloIA', false)) {
            if (\App\AI\ModeloIA::exigeMaxCompletionTokens($modelo)) {
                $requestData['max_completion_tokens'] = 4500;
            } else {
                $requestData['max_tokens'] = 4500;
            }
            if (\App\AI\ModeloIA::aceitaTemperaturaCustomizada($modelo)) {
                $requestData['temperature'] = 0.2;
            }
        } else {
            $requestData['max_tokens'] = 4500;
            $requestData['temperature'] = 0.2;
        }

        $this->openai->chatCompletionStream($requestData, $onChunk, 180);
    }

    /**
     * Tira listas grandes de blocos_ids do contexto da IA (o catálogo já resolve por tipo/semana).
     *
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function enxugarEstadoParaContexto(array $estado): array
    {
        $out = $estado;
        if (isset($out['rascunho_preservado']) && is_array($out['rascunho_preservado'])) {
            $out['rascunho_preservado'] = $this->enxugarRascunhoParaContexto($out['rascunho_preservado']);
        }
        return $this->enxugarRascunhoParaContexto($out);
    }

    /**
     * @param array<string,mixed> $rascunho
     * @return array<string,mixed>
     */
    private function enxugarRascunhoParaContexto(array $rascunho): array
    {
        if (!isset($rascunho['componentes']) || !is_array($rascunho['componentes'])) {
            return $rascunho;
        }
        $rascunho['componentes'] = array_map(static function ($comp) {
            if (!is_array($comp)) {
                return $comp;
            }
            $comp['blocos_ids'] = [];
            return $comp;
        }, $rascunho['componentes']);
        return $rascunho;
    }

    /**
     * Emite só codepoints UTF-8 completos (evita � no stream).
     *
     * @param callable(string):void $onTextoChunk
     */
    private function emitirTextoUtf8Seguro(string $full, int $from, int $to, callable $onTextoChunk): int
    {
        if ($to <= $from) {
            return $from;
        }
        $slice = mb_strcut($full, $from, $to - $from, 'UTF-8');
        if ($slice === '' || $slice === false) {
            return $from;
        }
        $onTextoChunk($slice);
        return $from + strlen($slice);
    }

    private function parseRespostaJson(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)```$/m', $raw, $m)) {
            $raw = trim($m[1]);
        }
        $dec = json_decode($raw, true);
        return is_array($dec) ? $dec : null;
    }
}
