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
     * @return array{success:bool,mensagem?:string,rascunho?:?array,acao?:string,erros?:list<string>,error?:string}
     */
    public function processarMensagemStream(
        string $mensagemUsuario,
        callable $onTextoChunk,
        array $historico = [],
        ?array $estadoFormulario = null,
        ?int $regraIdAtual = null,
        ?array $wizardEstado = null
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
        $sep = self::DELIMITADOR_RASCUNHO;
        $sepLen = strlen($sep);

        try {
            $this->chamarModeloStream($prep['mensagens'], $prep['system_stream'], function ($chunk) use (
                &$full,
                &$textoEnviadoLen,
                $sep,
                $sepLen,
                $onTextoChunk
            ) {
                $full .= $chunk;
                $pos = strpos($full, $sep);
                if ($pos === false) {
                    $safeEnd = max(0, strlen($full) - ($sepLen - 1));
                    if ($safeEnd > $textoEnviadoLen) {
                        $onTextoChunk(substr($full, $textoEnviadoLen, $safeEnd - $textoEnviadoLen));
                        $textoEnviadoLen = $safeEnd;
                    }
                    return;
                }
                if ($pos > $textoEnviadoLen) {
                    $onTextoChunk(substr($full, $textoEnviadoLen, $pos - $textoEnviadoLen));
                    $textoEnviadoLen = $pos;
                }
            });
        } catch (Throwable $e) {
            error_log('BoletimAssistenteService stream: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Falha ao consultar a IA. Tente novamente.'];
        }

        $pos = strpos($full, $sep);
        if ($pos === false) {
            if (strlen($full) > $textoEnviadoLen) {
                $onTextoChunk(substr($full, $textoEnviadoLen));
            }
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

        if ($pos > $textoEnviadoLen) {
            $onTextoChunk(substr($full, $textoEnviadoLen, $pos - $textoEnviadoLen));
        }

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
        $limite = $this->pareceReceitaColada($mensagemUsuario) ? 12000 : 4000;
        if (mb_strlen($mensagemUsuario) > $limite) {
            return ['ok' => false, 'error' => 'Mensagem muito longa (máx. ' . $limite . ' caracteres).'];
        }

        try {
            $this->assertCreditosDisponiveis();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $catalogo = $this->ferramentas->montarContextoCatalogo();
        $regraAtual = null;
        if ($regraIdAtual !== null && $regraIdAtual > 0) {
            $regraAtual = $this->ferramentas->obterRegra($regraIdAtual);
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
        "source_type": "provas_sistema",
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
            ? json_encode($regraAtual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : 'null';
        $formJson = $estadoFormulario
            ? json_encode($estadoFormulario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : 'null';
        $wizardJson = $wizardEstado
            ? json_encode($wizardEstado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
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
- Se faltar dado crítico (datas, tipo, turma), use acao=esclarecimento e pergunte (rascunho null).
- Datas podem vir dd/mm/aaaa; normalize para YYYY-MM-DD no JSON.
- Não invente matérias/turmas/tipos que não estejam no catálogo.
- MEMÓRIA: use o histórico da conversa e o estado do formulário. NÃO peça de novo datas, turmas, matérias, tipos ou fórmula que o usuário já informou. Acumule e complete o rascunho a cada turno.
- Sempre devolva a lista COMPLETA de componentes (não só o bloco novo). Ao acrescentar um bloco, mantenha os anteriores.
- Se o usuário pedir um "terceiro bloco" / "bloco de média" / "coluna com a média", CRIE um componente source_type=calculado (ex.: codigo media_final, nome "Média") com config.expressao usando os códigos dos outros blocos, ex.: (semanal + bimestral) / 2. NÃO deixe a média só em formula_final — precisa aparecer em "Blocos da regra".
- Coloque o bloco calculado DEPOIS dos blocos que ele referencia. formula_final pode ser o codigo do calculado (ex.: media_final).
- Se o usuário disser "todas as matérias", deixe materias_ids vazio (significa todas).
- Se disser uma série (ex.: 3º ano EM), resolva as turmas dessa série pelo catálogo (turmas_ids) quando possível.
- "somar questões acertadas e fazer média conforme quantidade" = provas_sistema com usar_percentual=1 e calc_type=media.
- POR PEÇA: o coordenador pode pedir média OU somatória em cada tipo (ex.: "bimestral é soma, semanal é média") — ajuste calc_type só naquele componente.
- JUNÇÃO DE MATÉRIAS: "juntar Português dos dois professores", "matérias únicas", "somar a mesma matéria" → materia_unica=1 no(s) componente(s) de prova. Explique em português simples na mensagem.
- MATÉRIAS DO EVENTO: se pedir "só Matemática e Português" / "sem Educação Física", preencha materias_ids do rascunho (IDs do catálogo). Vazio = todas.
- PERÍODO: datas default_data_inicio/fim; para provas use tipo_avaliacao_id (catálogo) para o sistema resolver blocos_ids. Não invente IDs de bloco.
- JORNADAS: config.jornada_ids vazio = todas no período; com IDs = só essas. Datas em config.data_ini/data_fim.
- TURMAS/SÉRIES: series_ids e turmas_ids do catálogo; se citar série, preferir series_ids (ou turmas da série).
- WIZARD: se houver "Estado do wizard" no contexto, respeite peças/fórmula/pecas_opcoes/datas/matérias/jornadas. Ajuste só o que o usuário pedir. Sempre devolva rascunho completo.
- RECEITA PORTÁVEL: se o usuário colar um bloco começando com "# RECEITA_BOLETIM" ou com "[[componente]]", reconstrua o rascunho EXATAMENTE (incluindo expressao dos calculados). Se pedir "copiar receita" / "exportar config", responda com a receita completa no formato RECEITA_BOLETIM v1 (campos chave:valor e [[componente]] por bloco, com expressao nos calculados).
- Em componentes calculado, SEMPRE preencha config.expressao com os códigos (ex.: (c1 + c2) / 2). Sem expressao o bloco de média não funciona — se faltar, pergunte SÓ a fórmula, com exemplo.
- Se o usuário colar markdown incompleto (sem expressao nos calculados), monte o que der e pergunte apenas as fórmulas faltantes; não peça de novo IDs já listados.
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
            2500,
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
                $requestData['max_completion_tokens'] = 2500;
            } else {
                $requestData['max_tokens'] = 2500;
            }
            if (\App\AI\ModeloIA::aceitaTemperaturaCustomizada($modelo)) {
                $requestData['temperature'] = 0.2;
            }
        } else {
            $requestData['max_tokens'] = 2500;
            $requestData['temperature'] = 0.2;
        }

        $this->openai->chatCompletionStream($requestData, $onChunk);
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
