<?php

namespace App\Modulos\Boletins\Services;

class BoletimModeloLeituraService
{
    private const LIMIAR_SIMILARIDADE = 0.85;
    private const TAMANHO_MAXIMO_UPLOAD = 12 * 1024 * 1024; // 12MB

    private BoletimAssistenteFerramentas $ferramentas;
    private BoletimAssistenteWizard $wizard;
    /** @var \App\Services\OpenAIService */
    private $openai;

    public function __construct(
        ?BoletimAssistenteFerramentas $ferramentas = null,
        ?BoletimAssistenteWizard $wizard = null,
        $openai = null
    ) {
        $this->ferramentas = $ferramentas ?? new BoletimAssistenteFerramentas();
        $this->wizard = $wizard ?? new BoletimAssistenteWizard($this->ferramentas);
        $this->openai = $openai instanceof \App\Services\OpenAIService
            ? $openai
            : new \App\Services\OpenAIService();
    }

    /**
     * @param array<string,mixed> $file
     * @return array{path:string,mime:string,nome_original:string,slug:string}
     */
    public function salvarUploadTemporario(array $file): array
    {
        $erro = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Envie um arquivo PDF, JPG, PNG ou WEBP.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Upload inválido.');
        }

        $nomeOriginal = trim((string) ($file['name'] ?? 'boletim'));
        $tamanho = (int) ($file['size'] ?? 0);
        if ($tamanho <= 0 || $tamanho > self::TAMANHO_MAXIMO_UPLOAD) {
            throw new \RuntimeException('Arquivo inválido ou muito grande (máx. 12MB).');
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : (string) ($file['type'] ?? 'application/octet-stream');
        if ($finfo) {
            finfo_close($finfo);
        }
        $mime = strtolower(trim($mime));

        $permitidos = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($permitidos[$mime])) {
            throw new \RuntimeException('Formato inválido. Use PDF, JPG, PNG ou WEBP.');
        }

        $slug = $this->tenantSlug();
        $base = dirname(__DIR__, 4) . '/storage/tmp/boletim_modelo_ia/' . $slug;
        if (!is_dir($base) && !@mkdir($base, 0700, true) && !is_dir($base)) {
            throw new \RuntimeException('Não foi possível preparar o diretório temporário.');
        }

        $nomeSeguro = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', basename($nomeOriginal));
        if (!is_string($nomeSeguro) || $nomeSeguro === '') {
            $nomeSeguro = 'boletim.' . $permitidos[$mime];
        }
        $destino = $base . '/modelo-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '-' . $nomeSeguro;
        if (!move_uploaded_file($tmp, $destino)) {
            throw new \RuntimeException('Não foi possível salvar o arquivo para leitura.');
        }
        @chmod($destino, 0600);

        return [
            'path' => $destino,
            'mime' => $mime,
            'nome_original' => mb_substr($nomeOriginal !== '' ? $nomeOriginal : basename($destino), 0, 180),
            'slug' => $slug,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function processarArquivo(string $path, string $mime, string $nomeOriginal): array
    {
        if ($path === '' || !is_file($path)) {
            throw new \RuntimeException('Arquivo temporário não encontrado para leitura.');
        }

        $imagem = $this->imagemParaLeitura($path, $mime, $nomeOriginal);
        $jsonRaw = trim((string) $this->openai->extrairLayoutBoletimModeloOpenAI($imagem['b64'], $imagem['mime']));
        $extraido = $this->normalizarExtraido($this->decodificarRespostaJson($jsonRaw));
        $catalogo = $this->catalogoLocal();
        $rascunho = $this->casarComCatalogo($extraido, $catalogo);
        $rascunho['arquivo'] = [
            'nome_original' => $nomeOriginal,
            'mime' => $mime,
        ];
        $rascunho['extraido'] = $extraido;
        $rascunho['gerado_em'] = date('c');
        $rascunho['tem_pendencias'] = $this->temPendencias($rascunho);

        return $rascunho;
    }

    /**
     * @return array{
     *   tipos:list<array<string,mixed>>,
     *   pecas:list<array<string,mixed>>,
     *   materias:list<array<string,mixed>>,
     *   eventos:list<array<string,mixed>>
     * }
     */
    public function catalogoLocal(): array
    {
        $tipos = $this->ferramentas->listarTiposAvaliacao();
        $pecas = $this->wizard->pecasMeta($tipos);
        $materias = $this->ferramentas->listarMateriasParaCasamentoModelo(600);
        $eventos = $this->ferramentas->listarEventosAvaliativosParaBoletim();

        return [
            'tipos' => $tipos,
            'pecas' => $pecas,
            'materias' => $materias,
            'eventos' => $eventos,
        ];
    }

    /**
     * @param array<string,mixed> $extraido
     * @param array<string,mixed> $catalogo
     * @return array<string,mixed>
     */
    public function casarComCatalogo(array $extraido, array $catalogo): array
    {
        $idx = $this->indexarCatalogo($catalogo);

        $legendas = [];
        foreach ((array) ($extraido['legendas'] ?? []) as $legenda) {
            if (!is_array($legenda)) {
                continue;
            }
            $sigla = trim((string) ($legenda['sigla'] ?? ''));
            $descricao = trim((string) ($legenda['descricao'] ?? ''));
            $textoBusca = trim($sigla . ' ' . $descricao);
            $matchPeca = $this->melhorCandidato($textoBusca, $idx['pecas']);
            $matchTipo = $this->melhorCandidato($textoBusca, $idx['tipos']);
            $legendas[] = [
                'sigla' => $sigla,
                'descricao' => $descricao,
                'confianca' => (float) ($legenda['confianca'] ?? 0),
                'match_peca' => $matchPeca,
                'match_tipo' => $matchTipo,
                'pendente' => $matchPeca['status'] !== 'ok' && $matchTipo['status'] !== 'ok',
            ];
        }

        $colunas = [];
        foreach ((array) ($extraido['colunas'] ?? []) as $coluna) {
            if (!is_array($coluna)) {
                continue;
            }
            $chave = trim((string) ($coluna['chave'] ?? ''));
            $rotulo = trim((string) ($coluna['rotulo'] ?? ''));
            $grupo = trim((string) ($coluna['grupo'] ?? ''));
            $tipoSugerido = strtolower(trim((string) ($coluna['tipo_sugerido'] ?? 'outro')));
            if ($tipoSugerido === '') {
                $tipoSugerido = 'outro';
            }
            $textoBusca = trim($rotulo . ' ' . $grupo);
            $precisaPeca = $tipoSugerido === 'lancamento';
            $matchPeca = $precisaPeca ? $this->melhorCandidato($textoBusca, $idx['pecas']) : $this->resultadoSemBusca();
            $matchTipo = $precisaPeca ? $this->melhorCandidato($textoBusca, $idx['tipos']) : $this->resultadoSemBusca();

            $evento = $this->resultadoSemBusca();
            if ($this->pareceTextoDeEvento($rotulo, $grupo)) {
                $evento = $this->melhorCandidato($textoBusca, $idx['eventos']);
            }

            $colunas[] = [
                'chave' => $chave,
                'rotulo' => $rotulo,
                'grupo' => $grupo,
                'tipo_sugerido' => $tipoSugerido,
                'confianca' => (float) ($coluna['confianca'] ?? 0),
                'match_peca' => $matchPeca,
                'match_tipo' => $matchTipo,
                'match_evento' => $evento,
                'pendente' => $precisaPeca && $matchPeca['status'] !== 'ok' && $matchTipo['status'] !== 'ok',
            ];
        }

        $nomesComponentes = [];
        foreach ((array) ($extraido['componentes'] ?? []) as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $nomeTmp = trim((string) ($comp['nome'] ?? ''));
            if ($nomeTmp !== '') {
                $nomesComponentes[] = $nomeTmp;
            }
        }

        $componentes = [];
        foreach ((array) ($extraido['componentes'] ?? []) as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $nome = trim((string) ($comp['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $ehTituloSecao = $this->pareceTituloAgrupamentoComponente($nome, $nomesComponentes);
            $matchMateria = $this->melhorCandidato($nome, $idx['materias']);
            if ($matchMateria['status'] !== 'ok') {
                $matchMateria = $this->melhorCandidatoExato($nome, $idx['materias']) ?? $matchMateria;
            }
            if ($ehTituloSecao && $matchMateria['status'] !== 'ok') {
                $matchMateria = [
                    'status' => 'titulo',
                    'id' => null,
                    'label' => 'Título de seção (não é linha de nota)',
                    'score' => 1.0,
                    'candidatos' => [],
                ];
            }
            $componentes[] = [
                'nome' => $nome,
                'confianca' => (float) ($comp['confianca'] ?? 0),
                'eh_titulo_secao' => $ehTituloSecao,
                'match_materia' => $matchMateria,
                'pendente' => !$ehTituloSecao && $matchMateria['status'] !== 'ok',
            ];
        }

        $rascunho = [
            'colunas' => $colunas,
            'legendas' => $legendas,
            'componentes' => $componentes,
            'observacoes_layout' => trim((string) ($extraido['observacoes_layout'] ?? '')),
            'nome_sugestao' => $this->sugerirNome($extraido, $colunas),
        ];

        return $this->propagarLegendasParaColunas($rascunho);
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $atribuicoes
     * @param array<string,mixed> $catalogo
     * @return array<string,mixed>
     */
    public function aplicarAtribuicoes(array $rascunho, array $atribuicoes, array $catalogo): array
    {
        $idx = $this->indexarCatalogo($catalogo);

        foreach ((array) ($atribuicoes['legendas'] ?? []) as $chave => $atrib) {
            if (!is_array($atrib)) {
                continue;
            }
            foreach ($this->encontrarIndices($rascunho['legendas'] ?? [], (string) $chave, 'sigla') as $pos) {
                if (!empty($atrib['peca_key'])) {
                    $pecaKey = (string) $atrib['peca_key'];
                    if (!isset($idx['pecas_por_id'][$pecaKey])) {
                        throw new \RuntimeException('Peça inválida em atribuição manual: ' . $pecaKey);
                    }
                    $rascunho['legendas'][$pos]['match_peca'] = $this->resultadoManual($idx['pecas_por_id'][$pecaKey]);
                }
                if (!empty($atrib['tipo_avaliacao_id'])) {
                    $tipoId = (int) $atrib['tipo_avaliacao_id'];
                    if (!isset($idx['tipos_por_id'][$tipoId])) {
                        throw new \RuntimeException('Tipo de avaliação inválido: #' . $tipoId);
                    }
                    $rascunho['legendas'][$pos]['match_tipo'] = $this->resultadoManual($idx['tipos_por_id'][$tipoId]);
                }
            }
        }

        foreach ((array) ($atribuicoes['colunas'] ?? []) as $chave => $atrib) {
            if (!is_array($atrib)) {
                continue;
            }
            $indices = $this->encontrarIndicesColunaPorCodigo($rascunho['colunas'] ?? [], (string) $chave);
            foreach ($indices as $pos) {
                if (!empty($atrib['peca_key'])) {
                    $pecaKey = (string) $atrib['peca_key'];
                    if (!isset($idx['pecas_por_id'][$pecaKey])) {
                        throw new \RuntimeException('Peça inválida em atribuição manual: ' . $pecaKey);
                    }
                    $rascunho['colunas'][$pos]['match_peca'] = $this->resultadoManual($idx['pecas_por_id'][$pecaKey]);
                }
                if (!empty($atrib['tipo_avaliacao_id'])) {
                    $tipoId = (int) $atrib['tipo_avaliacao_id'];
                    if (!isset($idx['tipos_por_id'][$tipoId])) {
                        throw new \RuntimeException('Tipo de avaliação inválido: #' . $tipoId);
                    }
                    $rascunho['colunas'][$pos]['match_tipo'] = $this->resultadoManual($idx['tipos_por_id'][$tipoId]);
                }
                if (!empty($atrib['evento_id'])) {
                    $eventoId = (int) $atrib['evento_id'];
                    if (!isset($idx['eventos_por_id'][$eventoId])) {
                        throw new \RuntimeException('Evento avaliativo inválido: #' . $eventoId);
                    }
                    $rascunho['colunas'][$pos]['match_evento'] = $this->resultadoManual($idx['eventos_por_id'][$eventoId]);
                }
            }
        }

        foreach ((array) ($atribuicoes['componentes'] ?? []) as $chave => $atrib) {
            if (!is_array($atrib)) {
                continue;
            }
            foreach ($this->encontrarIndices($rascunho['componentes'] ?? [], (string) $chave, 'nome') as $pos) {
                $materiaId = (int) ($atrib['materia_id'] ?? 0);
                if ($materiaId > 0) {
                    if (!isset($idx['materias_por_id'][$materiaId])) {
                        throw new \RuntimeException('Matéria inválida: #' . $materiaId);
                    }
                    $rascunho['componentes'][$pos]['match_materia'] = $this->resultadoManual($idx['materias_por_id'][$materiaId]);
                }
            }
        }

        $rascunho = $this->propagarLegendasParaColunas($rascunho);

        foreach ((array) ($rascunho['legendas'] ?? []) as $i => $item) {
            $matchPeca = (array) ($item['match_peca'] ?? []);
            $matchTipo = (array) ($item['match_tipo'] ?? []);
            $rascunho['legendas'][$i]['pendente'] = (($matchPeca['status'] ?? 'pendente') !== 'ok')
                && (($matchTipo['status'] ?? 'pendente') !== 'ok');
        }
        foreach ((array) ($rascunho['colunas'] ?? []) as $i => $item) {
            $tipo = strtolower(trim((string) ($item['tipo_sugerido'] ?? '')));
            $matchPeca = (array) ($item['match_peca'] ?? []);
            $matchTipo = (array) ($item['match_tipo'] ?? []);
            $rascunho['colunas'][$i]['pendente'] = $tipo === 'lancamento'
                && (($matchPeca['status'] ?? 'pendente') !== 'ok')
                && (($matchTipo['status'] ?? 'pendente') !== 'ok');
        }
        $nomesComponentes = [];
        foreach ((array) ($rascunho['componentes'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nomeTmp = trim((string) ($item['nome'] ?? ''));
            if ($nomeTmp !== '') {
                $nomesComponentes[] = $nomeTmp;
            }
        }
        foreach ((array) ($rascunho['componentes'] ?? []) as $i => $item) {
            $match = (array) ($item['match_materia'] ?? []);
            $nome = trim((string) ($item['nome'] ?? ''));
            $ehTitulo = !empty($item['eh_titulo_secao'])
                || (($match['status'] ?? '') === 'titulo')
                || ($nome !== '' && $this->pareceTituloAgrupamentoComponente($nome, $nomesComponentes));
            if (($match['status'] ?? '') !== 'ok') {
                $exato = $nome !== '' ? $this->melhorCandidatoExato($nome, $idx['materias']) : null;
                if ($exato !== null) {
                    $rascunho['componentes'][$i]['match_materia'] = $exato;
                    $rascunho['componentes'][$i]['pendente'] = false;
                    continue;
                }
            }
            if ($ehTitulo && ($match['status'] ?? '') !== 'ok') {
                $rascunho['componentes'][$i]['eh_titulo_secao'] = true;
                $rascunho['componentes'][$i]['match_materia'] = [
                    'status' => 'titulo',
                    'id' => null,
                    'label' => 'Título de seção (não é linha de nota)',
                    'score' => 1.0,
                    'candidatos' => is_array($match['candidatos'] ?? null) ? $match['candidatos'] : [],
                ];
                $rascunho['componentes'][$i]['pendente'] = false;
                continue;
            }
            $rascunho['componentes'][$i]['pendente'] = ($match['status'] ?? 'pendente') !== 'ok';
        }

        $rascunho['tem_pendencias'] = $this->temPendencias($rascunho);
        $rascunho['atualizado_em'] = date('c');

        return $rascunho;
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    public function temPendencias(array $rascunho): bool
    {
        foreach ((array) ($rascunho['legendas'] ?? []) as $item) {
            if (!empty($item['pendente'])) {
                return true;
            }
        }
        foreach ((array) ($rascunho['colunas'] ?? []) as $item) {
            if (!empty($item['pendente'])) {
                return true;
            }
        }
        foreach ((array) ($rascunho['componentes'] ?? []) as $item) {
            if (!empty($item['pendente'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @return array<string,mixed>
     */
    public function montarRascunhoAssistente(array $rascunho): array
    {
        $pecas = [];
        $pecasOpcoes = [];

        foreach ((array) ($rascunho['legendas'] ?? []) as $item) {
            $this->acumularPecaPorMatch($item, $pecas, $pecasOpcoes);
        }
        foreach ((array) ($rascunho['colunas'] ?? []) as $item) {
            $this->acumularPecaPorMatch($item, $pecas, $pecasOpcoes);
        }

        $nome = trim((string) ($rascunho['nome_sugestao'] ?? ''));
        if ($nome === '') {
            $nome = 'Modelo importado por IA';
        }

        return [
            'nome' => $nome,
            'pecas' => array_values(array_keys($pecas)),
            'pecas_opcoes' => $pecasOpcoes,
            'origem_modelo_ia' => 1,
        ];
    }

    /**
     * @return array{b64:string,mime:string}
     */
    private function imagemParaLeitura(string $path, string $mime, string $nomeOriginal): array
    {
        $mime = strtolower(trim($mime));
        if ($mime === 'application/pdf' || str_ends_with(strtolower($nomeOriginal), '.pdf')) {
            return $this->rasterizarPrimeiraPaginaPdf($path);
        }

        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        if (!str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        return ['b64' => base64_encode($bytes), 'mime' => $mime];
    }

    /**
     * @return array{b64:string,mime:string}
     */
    private function rasterizarPrimeiraPaginaPdf(string $path): array
    {
        if (!class_exists('Imagick')) {
            throw new \RuntimeException('PDF não suportado neste servidor. Envie uma imagem (JPG, PNG ou WEBP) do boletim-print.');
        }

        try {
            $im = new \Imagick();
            $im->setResolution(150, 150);
            $im->readImage($path . '[0]');
            $im->setImageBackgroundColor('white');
            try {
                $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            } catch (\Throwable $e) {
            }
            try {
                $flat = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                if ($flat instanceof \Imagick) {
                    $im = $flat;
                }
            } catch (\Throwable $e) {
            }
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(80);
            $blob = $im->getImageBlob();
            $im->clear();
            $im->destroy();
            if (!is_string($blob) || $blob === '') {
                throw new \RuntimeException('Falha ao converter o PDF em imagem.');
            }
            return ['b64' => base64_encode($blob), 'mime' => 'image/jpeg'];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Não foi possível ler o PDF no servidor. Envie uma imagem (JPG, PNG ou WEBP) do boletim-print.');
        }
    }

    /**
     * @param string $raw
     * @return array<string,mixed>
     */
    private function decodificarRespostaJson(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new \RuntimeException('A IA não retornou conteúdo para leitura do modelo.');
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
            $raw = trim((string) $m[1]);
        }

        $dec = json_decode($raw, true);
        if (!is_array($dec)) {
            throw new \RuntimeException('A IA retornou um formato inválido para o modelo de boletim.');
        }

        return $dec;
    }

    /**
     * @param array<string,mixed> $json
     * @return array<string,mixed>
     */
    private function normalizarExtraido(array $json): array
    {
        return [
            'colunas' => is_array($json['colunas'] ?? null) ? array_values($json['colunas']) : [],
            'legendas' => is_array($json['legendas'] ?? null) ? array_values($json['legendas']) : [],
            'componentes' => is_array($json['componentes'] ?? null) ? array_values($json['componentes']) : [],
            'observacoes_layout' => trim((string) ($json['observacoes_layout'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $catalogo
     * @return array<string,mixed>
     */
    private function indexarCatalogo(array $catalogo): array
    {
        $pecas = [];
        $tipos = [];
        $materias = [];
        $eventos = [];
        $pecasPorId = [];
        $tiposPorId = [];
        $materiasPorId = [];
        $eventosPorId = [];

        foreach ((array) ($catalogo['pecas'] ?? []) as $peca) {
            if (!is_array($peca)) {
                continue;
            }
            $id = (string) ($peca['key'] ?? '');
            if ($id === '') {
                continue;
            }
            $label = trim((string) ($peca['label'] ?? $id));
            $pecas[] = ['id' => $id, 'label' => $label, 'normalizado' => $this->normalizarTexto($label . ' ' . $id)];
            $pecasPorId[$id] = ['id' => $id, 'label' => $label];
        }

        foreach ((array) ($catalogo['tipos'] ?? []) as $tipo) {
            if (!is_array($tipo)) {
                continue;
            }
            $id = (int) ($tipo['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $nome = trim((string) ($tipo['nome'] ?? ('Tipo #' . $id)));
            $chaveQuadro = trim((string) ($tipo['chave_quadro'] ?? ''));
            $normalizado = $this->normalizarTexto($nome . ' ' . $chaveQuadro);
            $tipos[] = ['id' => $id, 'label' => $nome, 'normalizado' => $normalizado];
            $tiposPorId[$id] = ['id' => $id, 'label' => $nome];
        }

        foreach ((array) ($catalogo['materias'] ?? []) as $materia) {
            if (!is_array($materia)) {
                continue;
            }
            $id = (int) ($materia['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $nome = trim((string) ($materia['nome'] ?? ('Matéria #' . $id)));
            $materias[] = ['id' => $id, 'label' => $nome, 'normalizado' => $this->normalizarTexto($nome)];
            $materiasPorId[$id] = ['id' => $id, 'label' => $nome];
        }

        foreach ((array) ($catalogo['eventos'] ?? []) as $evento) {
            if (!is_array($evento)) {
                continue;
            }
            $id = (int) ($evento['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $nome = trim((string) ($evento['nome'] ?? ('Evento #' . $id)));
            $sigla = trim((string) ($evento['sigla'] ?? ''));
            $eventos[] = [
                'id' => $id,
                'label' => $nome,
                'normalizado' => $this->normalizarTexto($nome . ' ' . $sigla),
            ];
            $eventosPorId[$id] = ['id' => $id, 'label' => $nome];
        }

        return [
            'pecas' => $pecas,
            'tipos' => $tipos,
            'materias' => $materias,
            'eventos' => $eventos,
            'pecas_por_id' => $pecasPorId,
            'tipos_por_id' => $tiposPorId,
            'materias_por_id' => $materiasPorId,
            'eventos_por_id' => $eventosPorId,
        ];
    }

    /**
     * @param list<array<string,mixed>> $opcoes
     * @return array{status:string,id:mixed,label:string,score:float,candidatos:list<array<string,mixed>>}
     */
    private function melhorCandidato(string $texto, array $opcoes): array
    {
        $busca = $this->normalizarTexto($texto);
        if ($busca === '' || $opcoes === []) {
            return $this->resultadoSemBusca();
        }

        $candidatos = [];
        foreach ($opcoes as $opcao) {
            $normalizadoOpcao = (string) ($opcao['normalizado'] ?? '');
            if ($normalizadoOpcao === '') {
                continue;
            }
            $score = $busca === $normalizadoOpcao ? 1.0 : $this->similaridade($busca, $normalizadoOpcao);
            if ($score <= 0) {
                continue;
            }
            $candidatos[] = [
                'id' => $opcao['id'],
                'label' => (string) ($opcao['label'] ?? ''),
                'score' => round($score, 4),
            ];
        }

        usort($candidatos, static function (array $a, array $b): int {
            if ((float) $a['score'] === (float) $b['score']) {
                return strcmp((string) $a['label'], (string) $b['label']);
            }
            return (float) $a['score'] > (float) $b['score'] ? -1 : 1;
        });

        if ($candidatos === []) {
            return [
                'status' => 'pendente',
                'id' => null,
                'label' => '',
                'score' => 0.0,
                'candidatos' => [],
            ];
        }

        $melhor = $candidatos[0];
        $segundo = $candidatos[1] ?? null;
        // Nome idêntico sempre vale; senão exige limiar + distância do 2º lugar.
        $exato = (float) $melhor['score'] >= 0.999;
        $aceito = $exato || (
            (float) $melhor['score'] >= self::LIMIAR_SIMILARIDADE
            && ($segundo === null || abs((float) $melhor['score'] - (float) $segundo['score']) > 0.05)
        );

        return [
            'status' => $aceito ? 'ok' : 'pendente',
            'id' => $aceito ? $melhor['id'] : null,
            'label' => $aceito ? (string) $melhor['label'] : '',
            'score' => (float) $melhor['score'],
            'candidatos' => array_slice($candidatos, 0, 5),
        ];
    }

    /**
     * @param list<array<string,mixed>> $opcoes
     * @return array{status:string,id:mixed,label:string,score:float,candidatos:list<array<string,mixed>>}|null
     */
    private function melhorCandidatoExato(string $texto, array $opcoes): ?array
    {
        $busca = $this->normalizarTexto($texto);
        if ($busca === '') {
            return null;
        }
        foreach ($opcoes as $opcao) {
            $normalizadoOpcao = (string) ($opcao['normalizado'] ?? '');
            if ($normalizadoOpcao === '' || $normalizadoOpcao !== $busca) {
                continue;
            }
            return [
                'status' => 'ok',
                'id' => $opcao['id'],
                'label' => (string) ($opcao['label'] ?? ''),
                'score' => 1.0,
                'candidatos' => [[
                    'id' => $opcao['id'],
                    'label' => (string) ($opcao['label'] ?? ''),
                    'score' => 1.0,
                ]],
            ];
        }
        return null;
    }

    /**
     * Ex.: "História" no print costuma ser título quando já existem "História 1" e "História 2".
     *
     * @param list<string> $outrosNomes
     */
    private function pareceTituloAgrupamentoComponente(string $nome, array $outrosNomes): bool
    {
        $norm = $this->normalizarTexto($nome);
        if ($norm === '' || mb_strlen($norm) < 3) {
            return false;
        }
        $filhos = 0;
        foreach ($outrosNomes as $outro) {
            $outroNorm = $this->normalizarTexto((string) $outro);
            if ($outroNorm === '' || $outroNorm === $norm) {
                continue;
            }
            if (str_starts_with($outroNorm, $norm . ' ')) {
                $filhos++;
            }
        }
        return $filhos >= 1;
    }

    /**
     * @param array<string,mixed> $itemCatalogo
     * @return array{status:string,id:mixed,label:string,score:float,candidatos:list<array<string,mixed>>}
     */
    private function resultadoManual(array $itemCatalogo): array
    {
        return [
            'status' => 'ok',
            'id' => $itemCatalogo['id'] ?? null,
            'label' => (string) ($itemCatalogo['label'] ?? ''),
            'score' => 1.0,
            'candidatos' => [[
                'id' => $itemCatalogo['id'] ?? null,
                'label' => (string) ($itemCatalogo['label'] ?? ''),
                'score' => 1.0,
            ]],
        ];
    }

    /**
     * @return array{status:string,id:mixed,label:string,score:float,candidatos:list<array<string,mixed>>}
     */
    private function resultadoSemBusca(): array
    {
        return [
            'status' => 'pendente',
            'id' => null,
            'label' => '',
            'score' => 0.0,
            'candidatos' => [],
        ];
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        if ($texto === '') {
            return '';
        }
        $semAcento = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if (is_string($semAcento) && $semAcento !== '') {
            $texto = $semAcento;
        } else {
            $texto = strtr($texto, [
                'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
                'é' => 'e', 'ê' => 'e', 'ë' => 'e',
                'í' => 'i', 'ï' => 'i',
                'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                'ú' => 'u', 'ü' => 'u',
                'ç' => 'c',
            ]);
        }
        $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', (string) $texto);
        return trim((string) $texto);
    }

    private function similaridade(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text($a, $b, $pct);
        return max(0.0, min(1.0, ((float) $pct) / 100.0));
    }

    private function tenantSlug(): string
    {
        $slug = defined('TENANT_SLUG') ? (string) TENANT_SLUG : '';
        $slug = strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $slug));
        if ($slug === '') {
            throw new \RuntimeException('TENANT_SLUG inválido para upload temporário.');
        }
        return $slug;
    }

    private function pareceTextoDeEvento(string $rotulo, string $grupo): bool
    {
        $texto = trim($rotulo . ' ' . $grupo);
        if ($texto === '') {
            return false;
        }
        $norm = $this->normalizarTexto($texto);
        if ($norm === '' || strlen($norm) < 4) {
            return false;
        }
        if (preg_match('/^(pp|mt|mb|mf|rec|faltas|situacao)$/i', $norm)) {
            return false;
        }
        return str_contains($norm, 'bimestre')
            || str_contains($norm, 'simulado')
            || str_contains($norm, 'avali')
            || str_contains($norm, 'prova')
            || str_contains($norm, 'enac');
    }

    /**
     * @param list<array<string,mixed>> $lista
     * @return list<int>
     */
    private function encontrarIndices(array $lista, string $chave, string $campo): array
    {
        $out = [];
        $chaveNorm = $this->normalizarTexto($chave);
        if ($lista === [] || $chaveNorm === '') {
            return $out;
        }
        foreach ($lista as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $valor = $this->normalizarTexto((string) ($item[$campo] ?? ''));
            if ($valor !== '' && $valor === $chaveNorm) {
                $out[] = (int) $i;
            }
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $colunas
     * @return list<int>
     */
    private function encontrarIndicesColunaPorCodigo(array $colunas, string $codigo): array
    {
        $codigoNorm = $this->normalizarTexto($codigo);
        if ($colunas === [] || $codigoNorm === '') {
            return [];
        }
        $out = [];
        foreach ($colunas as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $chave = $this->normalizarTexto((string) ($item['chave'] ?? ''));
            $rotulo = $this->normalizarTexto((string) ($item['rotulo'] ?? ''));
            if ($chave === $codigoNorm || $rotulo === $codigoNorm) {
                $out[] = (int) $i;
            }
        }
        return $out;
    }

    /**
     * Atribuição de uma legenda (ex.: PP → Prova Parcial) vale para todas as colunas
     * com a mesma sigla/rótulo em qualquer bimestre.
     *
     * @param array<string,mixed> $rascunho
     * @return array<string,mixed>
     */
    private function propagarLegendasParaColunas(array $rascunho): array
    {
        $mapa = [];
        foreach ((array) ($rascunho['legendas'] ?? []) as $legenda) {
            if (!is_array($legenda)) {
                continue;
            }
            $sigla = $this->normalizarTexto((string) ($legenda['sigla'] ?? ''));
            if ($sigla === '') {
                continue;
            }
            $matchPeca = is_array($legenda['match_peca'] ?? null) ? $legenda['match_peca'] : [];
            $matchTipo = is_array($legenda['match_tipo'] ?? null) ? $legenda['match_tipo'] : [];
            $okPeca = ($matchPeca['status'] ?? '') === 'ok';
            $okTipo = ($matchTipo['status'] ?? '') === 'ok';
            if (!$okPeca && !$okTipo) {
                continue;
            }
            $mapa[$sigla] = [
                'match_peca' => $okPeca ? $matchPeca : null,
                'match_tipo' => $okTipo ? $matchTipo : null,
            ];
        }

        if ($mapa === []) {
            return $rascunho;
        }

        foreach ((array) ($rascunho['colunas'] ?? []) as $i => $coluna) {
            if (!is_array($coluna)) {
                continue;
            }
            $chave = $this->normalizarTexto((string) ($coluna['chave'] ?? ''));
            $rotulo = $this->normalizarTexto((string) ($coluna['rotulo'] ?? ''));
            $hit = $mapa[$chave] ?? $mapa[$rotulo] ?? null;
            if ($hit === null) {
                continue;
            }
            if ($hit['match_peca'] !== null && (($coluna['match_peca']['status'] ?? '') !== 'ok')) {
                $rascunho['colunas'][$i]['match_peca'] = $hit['match_peca'];
            }
            if ($hit['match_tipo'] !== null && (($coluna['match_tipo']['status'] ?? '') !== 'ok')) {
                $rascunho['colunas'][$i]['match_tipo'] = $hit['match_tipo'];
            }
            $tipo = strtolower(trim((string) ($rascunho['colunas'][$i]['tipo_sugerido'] ?? '')));
            $matchPeca = (array) ($rascunho['colunas'][$i]['match_peca'] ?? []);
            $matchTipo = (array) ($rascunho['colunas'][$i]['match_tipo'] ?? []);
            $rascunho['colunas'][$i]['pendente'] = $tipo === 'lancamento'
                && (($matchPeca['status'] ?? 'pendente') !== 'ok')
                && (($matchTipo['status'] ?? 'pendente') !== 'ok');
        }

        return $rascunho;
    }

    /**
     * @param array<string,mixed> $extraido
     * @param list<array<string,mixed>> $colunas
     */
    private function sugerirNome(array $extraido, array $colunas): string
    {
        $obs = trim((string) ($extraido['observacoes_layout'] ?? ''));
        if ($obs !== '') {
            return mb_substr('Modelo IA - ' . $obs, 0, 80);
        }

        $grupos = [];
        foreach ($colunas as $col) {
            $grupo = trim((string) ($col['grupo'] ?? ''));
            if ($grupo !== '') {
                $grupos[$grupo] = true;
            }
        }
        if ($grupos !== []) {
            return mb_substr('Modelo IA - ' . implode(' / ', array_keys($grupos)), 0, 80);
        }

        return 'Modelo importado por IA';
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,bool> $pecas
     * @param array<string,array<string,mixed>> $pecasOpcoes
     */
    private function acumularPecaPorMatch(array $item, array &$pecas, array &$pecasOpcoes): void
    {
        $matchPeca = (array) ($item['match_peca'] ?? []);
        $pecaKey = (string) ($matchPeca['id'] ?? '');
        if ($pecaKey !== '') {
            $pecas[$pecaKey] = true;
        }

        $matchTipo = (array) ($item['match_tipo'] ?? []);
        $tipoId = (int) ($matchTipo['id'] ?? 0);
        if ($tipoId > 0 && $pecaKey !== '') {
            if (!isset($pecasOpcoes[$pecaKey])) {
                $pecasOpcoes[$pecaKey] = [];
            }
            $pecasOpcoes[$pecaKey]['tipo_avaliacao_id'] = $tipoId;
            $pecasOpcoes[$pecaKey]['papel'] = 'media';
        }
    }
}

if (!class_exists('BoletimModeloLeituraService', false) && class_exists(__NAMESPACE__ . '\\BoletimModeloLeituraService', false)) {
    class_alias(__NAMESPACE__ . '\\BoletimModeloLeituraService', 'BoletimModeloLeituraService');
}
