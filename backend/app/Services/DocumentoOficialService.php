<?php
require_once __DIR__ . '/ResultadoHomologacaoService.php';
require_once __DIR__ . '/FrequencyService.php';
require_once __DIR__ . '/../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/DeclarationService.php';

use App\Services\DeclarationService;

/**
 * EducaTudo - Emissão de documentos oficiais a partir do resultado homologado.
 * Layout: modelo HTML escolhido pela escola (secretaria_modelos_documentos),
 * com fallback para views PHP.
 */
class DocumentoOficialService
{
    private ResultadoHomologacaoService $homologacao;
    private ResultadoAcademico $model;
    private DeclarationService $declarations;
    private $db;

    public function __construct(?ResultadoHomologacaoService $homologacao = null)
    {
        $this->homologacao = $homologacao ?? new ResultadoHomologacaoService();
        $this->model = $this->homologacao->model();
        $this->declarations = new DeclarationService();
        $this->db = Database::getInstance();
    }

    public function homologacao(): ResultadoHomologacaoService
    {
        return $this->homologacao;
    }

    /**
     * @return array{html:string,orientacao:string,papel:string,numero:int,modelo_codigo:string,payload:array}
     */
    public function emitirFicha(int $alunoId, int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero, ?int $usuarioId, ?array $configApp = null): array
    {
        $payload = $this->montarFicha($alunoId, $turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        if ($payload === null) {
            throw new RuntimeException('Não foi possível montar a ficha deste aluno.');
        }
        return $this->emitirDocumento('ficha_individual', $payload, $usuarioId, $configApp, $alunoId, $turmaId);
    }

    /**
     * Ficha individual completa do ano (escola, matrícula, 4 bimestres, CH, frequência, resultado).
     *
     * @return array<string,mixed>|null
     */
    public function montarFicha(int $alunoId, int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): ?array
    {
        $payload = $this->homologacao->payloadAluno($alunoId, $turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        if ($payload === null) {
            return null;
        }
        $payload = $this->enriquecerAluno($payload, $alunoId);
        $payload = $this->enriquecerCabecalhoAcademico($payload, $alunoId, $turmaId, $anoLetivo);
        $payload['componentes_ficha'] = $this->montarComponentesFicha($alunoId, $turmaId, $anoLetivo, $payload);
        $cargas = $this->cargaDaMatriz((int) ($payload['turma']['matriz_curricular_id'] ?? 0));
        $payload['dias_letivos'] = (string) ((int) ($cargas['_dias'] ?? 200));
        $payload['quadro_notas_html'] = $this->quadroFichaHtml($payload['componentes_ficha']);
        $payload['observacoes'] = $this->observacoesFicha($alunoId, $turmaId, $anoLetivo, $payload);
        $payload['proxima_serie'] = $this->proximaSerieRotulo($payload);
        $payload['conselho_label'] = !empty($payload['_homologado'])
            ? ('Homologado em ' . date('d/m/Y'))
            : 'Em andamento';
        return $payload;
    }

    /**
     * @return array{html:string,orientacao:string,papel:string,numero:int,modelo_codigo:string,payload:array}
     */
    public function emitirAta(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero, ?int $usuarioId, ?array $configApp = null): array
    {
        $preview = $this->homologacao->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $linhas = $preview['linhas'] ?? [];
        $totais = $this->totaisAta($linhas);
        $turma = is_array($preview['turma'] ?? null) ? $preview['turma'] : [];
        $detalhe = $this->db->fetch(
            'SELECT t.*, c.nome AS curso_nome, s.nome AS serie_nome
             FROM turmas t
             LEFT JOIN curso c ON c.id = t.curso_novo_id
             LEFT JOIN serie s ON s.id = t.serie_id
             WHERE t.id = :id LIMIT 1',
            ['id' => $turmaId]
        ) ?: [];
        $turma = array_merge($turma, $detalhe);
        $turma['turno_label'] = $this->rotuloTurno((string) ($turma['turno'] ?? ''));
        $payload = [
            'turma' => $turma,
            'periodo' => $preview['periodo'],
            'linhas' => $linhas,
            'resumo' => $preview['resumo'],
            'tabela_html' => $this->tabelaAtaHtml($linhas),
            'ata_totais' => $totais['texto'],
            'total_aprovados' => $totais['aprovados'],
            'total_retidos' => $totais['retidos'],
            'total_transferidos' => $totais['transferidos'],
            'unidade' => $this->unidadeDaTurma($turmaId),
        ];
        $payload['conselho_label'] = $this->ehEmissaoOficial('ata_resultados', $payload) ? 'Homologado' : 'Prévia';
        return $this->emitirDocumento('ata_resultados', $payload, $usuarioId, $configApp, null, $turmaId);
    }

    /**
     * @return array{html:string,orientacao:string,papel:string,numero:int,modelo_codigo:string,payload:array}
     */
    public function emitirBoletim(int $alunoId, int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero, ?int $usuarioId, ?array $configApp = null): array
    {
        $payload = $this->montarFicha($alunoId, $turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        if ($payload === null) {
            throw new RuntimeException('Não foi possível montar o boletim deste aluno.');
        }
        $payload['quadro_notas_html'] = $this->quadroBoletimFinalHtml($payload['componentes_ficha'] ?? []);
        return $this->emitirDocumento('boletim', $payload, $usuarioId, $configApp, $alunoId, $turmaId);
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{html:string,orientacao:string,papel:string,numero:int,modelo_codigo:string,payload:array,linhas:list}
     */
    public function emitirRelatorio(string $tipoRelatorio, int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero, ?int $usuarioId, ?array $configApp = null, array $filtros = []): array
    {
        if (!isset(ResultadoAcademico::DOCUMENTO_TIPOS[$tipoRelatorio])) {
            $tipoRelatorio = 'relatorio_fechamento';
        }
        $preview = $this->homologacao->previewTurma($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        $linhas = $this->filtrarRelatorio($preview['linhas'], $tipoRelatorio, $filtros);
        if ($tipoRelatorio === 'relatorio_classificacao') {
            usort($linhas, static function ($a, $b) {
                $ma = $a['avaliado']['media_final'] ?? $a['avaliado']['media'] ?? null;
                $mb = $b['avaliado']['media_final'] ?? $b['avaliado']['media'] ?? null;
                $fa = is_numeric($ma) ? (float) $ma : -1;
                $fb = is_numeric($mb) ? (float) $mb : -1;
                return $fb <=> $fa;
            });
        }
        $payload = [
            'turma' => $preview['turma'],
            'periodo' => $preview['periodo'],
            'resumo' => $preview['resumo'],
            'titulo_relatorio' => ResultadoAcademico::DOCUMENTO_TIPOS[$tipoRelatorio],
            'linhas' => $linhas,
            'tabela_html' => $this->tabelaRelatorioHtml($linhas, $tipoRelatorio),
        ];
        $out = $this->emitirDocumento($tipoRelatorio, $payload, $usuarioId, $configApp, null, $turmaId);
        $out['linhas'] = $linhas;
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    public function filtrarRelatorio(array $linhas, string $tipo, array $filtros = []): array
    {
        $out = [];
        foreach ($linhas as $linha) {
            $sit = (string) ($linha['situacao'] ?? '');
            $ok = match ($tipo) {
                'relatorio_aprovados' => in_array($sit, ['aprovado', 'aprovado_recuperacao', 'aprovado_conselho', 'aproveitamento'], true),
                'relatorio_reprovados' => in_array($sit, ['reprovado_rendimento', 'reprovado_frequencia'], true),
                'relatorio_recuperacao' => in_array($sit, ['recuperacao', 'exame_final', 'progressao_parcial', 'dependencia'], true),
                'relatorio_frequencia' => true,
                'relatorio_desempenho' => true,
                'relatorio_classificacao' => true,
                'relatorio_pendencias' => !empty($linha['pendencias']),
                'relatorio_fechamento' => true,
                default => true,
            };
            if (!$ok) {
                continue;
            }
            $out[] = $linha;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{html:string,orientacao:string,papel:string,numero:int,modelo_codigo:string,payload:array}
     */
    private function emitirDocumento(string $tipo, array $payload, ?int $usuarioId, ?array $configApp, ?int $alunoId, ?int $turmaId): array
    {
        $periodo = $payload['periodo'] ?? [];
        $anoLetivo = (int) ($periodo['ano_letivo'] ?? date('Y'));
        $modeloCodigo = $this->model->getLayoutCodigo($tipo);
        $oficial = $this->ehEmissaoOficial($tipo, $payload);
        $numero = $oficial ? $this->model->proximoNumeroEmissao($tipo, $anoLetivo) : 0;
        $vars = $this->varsDoPayload($payload, $tipo, $numero, $anoLetivo);
        $render = $this->renderComModelo($modeloCodigo, $vars, $tipo, $payload, $configApp);

        $canonical = json_encode([
            'tipo' => $tipo,
            'payload' => $this->payloadResumo($payload),
            'modelo' => $modeloCodigo,
            'numero' => $numero,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = is_string($canonical) ? hash('sha256', $canonical) : null;

        if ($oficial) {
            $this->model->registrarEmissao([
                'tipo' => $tipo,
                'modelo_codigo' => $modeloCodigo,
                'aluno_id' => $alunoId,
                'turma_id' => $turmaId,
                'resultado_id' => $payload['_resultado_id'] ?? $payload['resultado_id'] ?? null,
                'ano_letivo' => $anoLetivo,
                'periodo_tipo' => $periodo['tipo'] ?? null,
                'periodo_numero' => $periodo['numero'] ?? null,
                'numero' => $numero,
                'hash_validacao' => $hash,
                'snapshot_json' => $canonical,
                'emitido_por' => $usuarioId,
            ]);
        }

        $html = (string) ($render['html'] ?? '');
        if (!$oficial) {
            $html = '<div style="border:2px dashed #b45309;background:#fffbeb;color:#92400e;padding:8px 12px;margin-bottom:16px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;text-align:center">Rascunho — sem valor oficial</div>'
                . $html;
        }

        return [
            'html' => $html,
            'orientacao' => $render['orientacao'],
            'papel' => $render['papel'] ?? 'A4',
            'numero' => $numero,
            'modelo_codigo' => $modeloCodigo,
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{html:string,orientacao:string,papel:string}
     */
    private function renderComModelo(string $codigo, array $vars, string $tipo, array $payload, ?array $configApp): array
    {
        $modelo = $this->buscarModelo($codigo);
        if ($modelo) {
            require_once __DIR__ . '/../Modulos/modelos-documentos/Services/ModeloDocumentoService.php';
            $svc = new \App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService($this->db);
            $unidade = is_array($payload['unidade'] ?? null) ? $payload['unidade'] : null;
            if (trim((string) ($vars['logo_html'] ?? '')) === '') {
                $vars['logo_html'] = $svc->logoHtmlInstitucional($unidade, $configApp);
            }
            $html = $svc->renderHtml($modelo, $vars, \App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService::estiloDoModelo($modelo), $configApp);
            return [
                'html' => $html,
                'orientacao' => $svc->orientacaoDompdf($modelo),
                'papel' => $svc->papelDompdf($modelo),
            ];
        }
        return [
            'html' => $this->renderFallbackPhp($tipo, $payload, $vars),
            'orientacao' => in_array($tipo, ['ata_resultados', 'boletim', 'historico'], true)
                || str_starts_with($tipo, 'relatorio_')
                ? 'landscape'
                : 'portrait',
            'papel' => 'A4',
        ];
    }

    private function buscarModelo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        try {
            require_once __DIR__ . '/../Modulos/modelos-documentos/Services/ModeloDocumentoService.php';
            $svc = new \App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService($this->db);
            return $svc->findByCodigo($codigo);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $vars
     */
    private function renderFallbackPhp(string $tipo, array $payload, array $vars): string
    {
        $file = match (true) {
            $tipo === 'ficha_individual' => 'ficha.php',
            $tipo === 'ata_resultados' => 'ata.php',
            $tipo === 'boletim' => 'boletim.php',
            default => 'relatorio.php',
        };
        $path = __DIR__ . '/../Views/admin/resultados-finais/pdf/' . $file;
        if (!is_file($path)) {
            return '<p>Template indisponível.</p>';
        }
        ob_start();
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        require $path;
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private function varsDoPayload(array $payload, string $tipo, int $numero, int $anoLetivo): array
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $aluno = is_array($payload['aluno'] ?? null) ? $payload['aluno'] : [];
        $turma = is_array($payload['turma'] ?? null) ? $payload['turma'] : [];
        $periodo = is_array($payload['periodo'] ?? null) ? $payload['periodo'] : [];
        $avaliado = is_array($payload['avaliado'] ?? null) ? $payload['avaliado'] : [];
        $freq = is_array($payload['frequencia'] ?? null) ? $payload['frequencia'] : [];
        $unidade = is_array($payload['unidade'] ?? null) ? $payload['unidade'] : [];
        $resumo = is_array($payload['resumo'] ?? null) ? $payload['resumo'] : [];

        $freqTxt = isset($freq['percentual']) && is_numeric($freq['percentual'])
            ? number_format((float) $freq['percentual'], 1, ',', '.') . '%'
            : '—';

        $quadro = $payload['quadro_notas_html'] ?? $this->quadroNotasHtml($payload['componentes'] ?? []);
        $tabela = $payload['tabela_html'] ?? '';
        $historicoHtml = $payload['historico_html'] ?? $payload['trajetoria_html'] ?? '';
        $alunoId = (int) ($aluno['id'] ?? 0);
        if ($historicoHtml === '' && $alunoId > 0) {
            $historicoHtml = $this->trajetoriaHtml($alunoId);
        }

        $mae = trim((string) ($aluno['nome_mae'] ?? ''));
        $pai = trim((string) ($aluno['nome_pai'] ?? ''));
        $filiacao = trim(implode(' / ', array_filter([$mae, $pai], static fn ($v) => $v !== '')));
        $etapa = (string) ($turma['curso_nome'] ?? $turma['serie_nome'] ?? $turma['serie'] ?? '');
        $cargaTotal = $this->somarCargaHoraria($payload);
        $slugEscola = strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '-', (string) ($unidade['nome_fantasia'] ?? $unidade['nome'] ?? 'EDUCA')));
        if ($slugEscola === '') {
            $slugEscola = 'EDUCA';
        }
        $codigoValidacao = $slugEscola . '-' . $anoLetivo . '-' . ($aluno['ra'] ?? $aluno['codigo_aluno'] ?? $numero);

        $cidade = trim((string) ($unidade['cidade'] ?? $unidade['cidade_nome'] ?? ''));
        $cidadeData = trim((string) ($payload['cidade_data'] ?? ''));
        if ($cidadeData === '') {
            $cidadeData = ($cidade !== '' ? $cidade . ', ' : '') . $this->dataPorExtenso(date('Y-m-d'));
        }

        return [
            'escola_nome' => $esc($unidade['razao_social'] ?? $unidade['nome'] ?? (class_exists('LayoutHelper') ? LayoutHelper::getSystemTitle() : 'Escola')),
            'escola_cnpj' => !empty($unidade['cnpj']) ? $esc('CNPJ ' . $unidade['cnpj']) : '',
            'escola_inep' => !empty($unidade['inep']) ? $esc('Código INEP ' . $unidade['inep']) : '',
            'escola_unidade' => $esc($unidade['nome'] ?? $unidade['nome_fantasia'] ?? ''),
            'escola_endereco' => $esc($unidade['endereco_completo'] ?? $unidade['endereco'] ?? ''),
            'escola_docs' => $esc(trim(implode(' | ', array_filter([
                !empty($unidade['cnpj']) ? 'CNPJ ' . $unidade['cnpj'] : '',
                !empty($unidade['inep']) ? 'Código INEP ' . $unidade['inep'] : '',
            ])))),
            'aluno_nome' => $esc($aluno['nome'] ?? ''),
            'aluno_codigo' => $esc($aluno['codigo_aluno'] ?? $aluno['ra'] ?? '—'),
            'aluno_cpf' => $esc($aluno['cpf'] ?? '—'),
            'aluno_data_nasc' => $this->fmtData($aluno['data_nasc'] ?? null),
            'aluno_filiacao' => $esc($filiacao !== '' ? $filiacao : '—'),
            'aluno_naturalidade' => $esc($aluno['naturalidade'] ?? '—'),
            'aluno_nacionalidade' => $esc($aluno['nacionalidade'] ?? 'Brasileira'),
            'aluno_nasc_frase' => !empty($aluno['data_nasc']) ? $esc(', nascido(a) em ' . $this->fmtData($aluno['data_nasc'])) : '',
            'turma_nome' => $esc($turma['nome'] ?? ''),
            'serie' => $esc($turma['serie_nome'] ?? $turma['serie'] ?? $turma['turma_serie'] ?? ''),
            'curso_nome' => $esc($turma['curso_nome'] ?? ''),
            'etapa' => $esc($etapa !== '' ? $etapa : ($turma['serie_nome'] ?? $turma['serie'] ?? '—')),
            'turno' => $esc($turma['turno_label'] ?? $this->rotuloTurno((string) ($turma['turno'] ?? ''))),
            'situacao_matricula' => $esc($payload['situacao_matricula_label'] ?? '—'),
            'ano_letivo' => (string) $anoLetivo,
            'periodo_label' => $esc($periodo['label'] ?? 'Ano letivo'),
            'situacao_final' => $esc($avaliado['rotulo'] ?? $payload['rotulo'] ?? '—'),
            'frequencia_percentual' => $esc($freqTxt),
            'quadro_notas_html' => is_string($quadro) ? $quadro : '',
            'tabela_html' => is_string($tabela) ? $tabela : '',
            'historico_html' => is_string($historicoHtml) ? $historicoHtml : '',
            'trajetoria_html' => is_string($historicoHtml) ? $historicoHtml : '',
            'titulo_relatorio' => $esc($payload['titulo_relatorio'] ?? ResultadoAcademico::DOCUMENTO_TIPOS[$tipo] ?? 'Relatório'),
            'observacoes' => $esc($payload['observacoes'] ?? ''),
            'data_hoje' => date('d/m/Y'),
            'data_extenso' => $esc($this->dataPorExtenso(date('Y-m-d'))),
            'numero' => $numero > 0 ? (string) $numero : 'prévia',
            'ano' => (string) $anoLetivo,
            'cidade_data' => $esc($cidadeData),
            'diretor_nome' => $esc($unidade['diretor_nome'] ?? 'Diretor(a) Escolar'),
            'secretario_nome' => $esc($unidade['secretario_nome'] ?? 'Secretário(a) Escolar'),
            'assinante_nome' => $esc($unidade['diretor_nome'] ?? ''),
            'assinante_cargo' => 'Direção',
            'total_alunos' => (string) (int) ($resumo['total'] ?? 0),
            'total_homologados' => (string) (int) ($resumo['homologados'] ?? 0),
            'total_pendencias' => (string) (int) ($resumo['pendencias'] ?? 0),
            'ata_totais' => $esc((string) ($payload['ata_totais'] ?? '')),
            'carga_horaria_total' => $esc($cargaTotal),
            'proxima_serie' => $esc((string) ($payload['proxima_serie'] ?? '—')),
            'dias_letivos' => $esc((string) ($payload['dias_letivos'] ?? '—')),
            'conselho_label' => $esc((string) ($payload['conselho_label'] ?? (!empty($payload['_homologado']) ? 'Homologado' : '—'))),
            'codigo_validacao' => $esc($codigoValidacao),
            'logo_html' => '',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function enriquecerAluno(array $payload, int $alunoId): array
    {
        $aluno = $this->declarations->getAluno($alunoId);
        if ($aluno) {
            $payload['aluno'] = array_merge($payload['aluno'] ?? [], $aluno);
            $payload['unidade'] = $this->declarations->getUnidadeForAluno($aluno) ?: [];
        }
        if (empty($payload['quadro_notas_html'])) {
            $payload['quadro_notas_html'] = $this->quadroNotasHtml($payload['componentes'] ?? []);
        }
        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function enriquecerCabecalhoAcademico(array $payload, int $alunoId, int $turmaId, int $anoLetivo): array
    {
        $turma = $this->db->fetch(
            'SELECT t.*, c.nome AS curso_nome, s.nome AS serie_nome
             FROM turmas t
             LEFT JOIN curso c ON c.id = t.curso_novo_id
             LEFT JOIN serie s ON s.id = t.serie_id
             WHERE t.id = :id LIMIT 1',
            ['id' => $turmaId]
        ) ?: (is_array($payload['turma'] ?? null) ? $payload['turma'] : []);
        $payload['turma'] = array_merge($payload['turma'] ?? [], $turma);
        $payload['turma']['turno_label'] = $this->rotuloTurno((string) ($turma['turno'] ?? ''));
        $payload['turma']['curso_nome'] = (string) ($turma['curso_nome'] ?? $payload['turma']['curso_nome'] ?? '');
        $payload['turma']['serie_nome'] = (string) ($turma['serie_nome'] ?? $turma['serie'] ?? $payload['turma']['serie'] ?? '');

        $matricula = $this->matriculaDoAno($alunoId, $turmaId, $anoLetivo);
        $payload['matricula'] = $matricula;
        $status = (string) ($matricula['status'] ?? '');
        if ($status === '' && !empty($payload['aluno']['transferido'])) {
            $status = 'transferido';
        }
        if ($status === '') {
            $status = 'ativa';
        }
        $payload['situacao_matricula'] = $status;
        $payload['situacao_matricula_label'] = $this->rotuloMatricula($status);

        $unidade = is_array($payload['unidade'] ?? null) ? $payload['unidade'] : [];
        $end = trim(implode(', ', array_filter([
            trim((string) ($unidade['endereco'] ?? '')),
            trim((string) ($unidade['numero'] ?? '')),
            trim((string) ($unidade['bairro'] ?? '')),
            trim((string) ($unidade['cidade'] ?? '')),
            trim((string) ($unidade['uf'] ?? '')),
        ], static fn ($v) => $v !== '')));
        $payload['unidade']['endereco_completo'] = $end;

        return $payload;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function matriculaDoAno(int $alunoId, int $turmaId, int $anoLetivo): ?array
    {
        try {
            $row = $this->db->fetch(
                "SELECT m.id, m.status, m.data_entrada, m.data_saida, al.ano AS ano_letivo
                 FROM matricula m
                 INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                 WHERE m.aluno_id = :aluno AND m.turma_id = :turma AND al.ano = :ano
                 ORDER BY m.id DESC LIMIT 1",
                ['aluno' => $alunoId, 'turma' => $turmaId, 'ano' => $anoLetivo]
            );
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<array<string,mixed>>
     */
    private function montarComponentesFicha(int $alunoId, int $turmaId, int $anoLetivo, array $payload): array
    {
        $periodo = is_array($payload['periodo'] ?? null) ? $payload['periodo'] : [];
        $inicio = (string) ($periodo['inicio'] ?? sprintf('%04d-01-01', $anoLetivo));
        $fim = (string) ($periodo['fim'] ?? sprintf('%04d-12-31', $anoLetivo));

        $notas = $this->notasBimestraisAluno($alunoId, $turmaId, $anoLetivo);
        $freqs = (new FrequencyService())->alunoPorComponente($alunoId, $turmaId, $inicio, $fim);
        $cargas = $this->cargaDaMatriz((int) ($payload['turma']['matriz_curricular_id'] ?? 0));
        $diasLetivos = (int) ($cargas['_dias'] ?? 200);
        unset($cargas['_duracao'], $cargas['_dias']);
        $mapaPai = $this->mapaPaiPorFilho();
        $notas = $this->agruparNotasNoOficial($notas, $cargas, $mapaPai);
        $freqs = $this->agruparFrequenciaNoOficial($freqs, $cargas, $mapaPai);

        $nomes = [];
        foreach ($payload['componentes'] ?? [] as $c) {
            $mid = $this->idOficialNaCarga((int) ($c['materia_id'] ?? 0), $cargas, $mapaPai);
            if ($mid > 0) {
                $nomes[$mid] = (string) ($cargas[$mid]['nome'] ?? $c['materia_nome'] ?? 'Componente');
            }
        }
        foreach ($notas as $mid => $info) {
            if (!isset($nomes[$mid])) {
                $nomes[$mid] = (string) ($info['nome'] ?? 'Componente');
            }
        }
        foreach ($cargas as $mid => $info) {
            if (!is_int($mid) && !ctype_digit((string) $mid)) {
                continue;
            }
            $mid = (int) $mid;
            if (!isset($nomes[$mid])) {
                $nomes[$mid] = (string) ($info['nome'] ?? 'Componente');
            }
        }

        $linhas = [];
        foreach ($nomes as $mid => $nome) {
            $bims = $notas[$mid]['bimestres'] ?? [];
            $mediasBim = [];
            $rec = null;
            for ($b = 1; $b <= 4; $b++) {
                if (isset($bims[$b]) && is_numeric($bims[$b]['media'])) {
                    $mediasBim[] = (float) $bims[$b]['media'];
                }
                if (isset($bims[$b]['recuperacao']) && is_numeric($bims[$b]['recuperacao'])) {
                    $rec = (float) $bims[$b]['recuperacao'];
                }
            }
            $mediaAnual = $mediasBim !== [] ? round(array_sum($mediasBim) / count($mediasBim), 2) : null;
            $recAno = ($mediaAnual !== null && $mediaAnual < 6.0) ? $rec : null;
            $freq = $freqs[$mid] ?? null;
            $aulasSemana = (int) ($cargas[$mid]['aulas_semana'] ?? 0);
            $semanas = $diasLetivos > 0 ? (int) max(1, round($diasLetivos / 5)) : 40;
            $chPrevista = $aulasSemana > 0 ? $aulasSemana * $semanas : null;
            $chCumprida = is_array($freq) ? (int) $freq['total_aulas'] : null;
            $snap = null;
            foreach ($payload['componentes'] ?? [] as $c) {
                $cid = $this->idOficialNaCarga((int) ($c['materia_id'] ?? 0), $cargas, $mapaPai);
                if ($cid === (int) $mid) {
                    $snap = $c;
                    break;
                }
            }
            $freqGeral = is_array($payload['frequencia'] ?? null) && is_numeric($payload['frequencia']['percentual'] ?? null)
                ? (float) $payload['frequencia']['percentual']
                : (is_array($freq) && is_numeric($freq['percentual'] ?? null) ? (float) $freq['percentual'] : null);
            $avaliadoComp = $this->avaliarComponenteFicha($payload, $alunoId, $turmaId, $anoLetivo, $mediaAnual, $recAno, $freqGeral);
            $linhas[] = [
                'materia_id' => $mid,
                'materia_nome' => $nome,
                'aulas_semana' => $aulasSemana ?: null,
                'carga_prevista' => $chPrevista,
                'carga_cumprida' => $chCumprida,
                'carga_horaria' => $chPrevista ?? ($snap['carga_horaria'] ?? null),
                'b1' => $bims[1]['media'] ?? null,
                'b2' => $bims[2]['media'] ?? null,
                'b3' => $bims[3]['media'] ?? null,
                'b4' => $bims[4]['media'] ?? null,
                'recuperacao' => $recAno,
                'media' => $mediaAnual,
                'media_final' => $avaliadoComp['media_final'] ?? $mediaAnual,
                'faltas' => is_array($freq) ? (int) $freq['faltas'] : ($snap['faltas'] ?? null),
                'frequencia_percentual' => is_array($freq) ? $freq['percentual'] : ($snap['frequencia_percentual'] ?? null),
                'situacao' => $avaliadoComp['situacao'] ?? ($snap['situacao'] ?? null),
                'rotulo' => $avaliadoComp['rotulo'] ?? ($snap['rotulo'] ?? '—'),
            ];
        }
        usort($linhas, static fn ($a, $b) => strcasecmp((string) $a['materia_nome'], (string) $b['materia_nome']));
        return $linhas;
    }

    /**
     * @return array<int, array{nome:string,bimestres:array<int,array{media:?float,recuperacao:?float}>}>
     */
    private function notasBimestraisAluno(int $alunoId, int $turmaId, int $anoLetivo): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT g.materia_id, g.materia_nome, g.media_final, g.notas_json, r.bimestre
                 FROM boletim_resultados_gerados g
                 INNER JOIN boletim_regras r ON r.id = g.regra_id
                 WHERE g.preview = 0 AND g.vigente = 1 AND g.aluno_id = :aluno AND r.ano_letivo = :ano_regra
                   AND r.bimestre BETWEEN 1 AND 4
                   AND EXISTS (
                        SELECT 1
                        FROM matricula m
                        INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                        WHERE m.aluno_id = :aluno_mat AND m.turma_id = :turma AND al.ano = :ano_matricula
                          AND m.status IN ('ativa', 'concluido', 'transferido')
                   )
                 ORDER BY r.bimestre ASC, g.ordem_linha ASC, g.id ASC",
                [
                    'aluno' => $alunoId,
                    'ano_regra' => $anoLetivo,
                    'aluno_mat' => $alunoId,
                    'turma' => $turmaId,
                    'ano_matricula' => $anoLetivo,
                ]
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['materia_id'] ?? 0);
            $bim = (int) ($row['bimestre'] ?? 0);
            if ($bim < 1 || $bim > 4) {
                continue;
            }
            if ($mid === 0) {
                continue;
            }
            if (!isset($out[$mid])) {
                $out[$mid] = ['nome' => (string) ($row['materia_nome'] ?? 'Componente'), 'bimestres' => []];
            }
            if (isset($out[$mid]['bimestres'][$bim])) {
                continue;
            }
            $notas = json_decode((string) ($row['notas_json'] ?? ''), true);
            $notas = is_array($notas) ? $notas : [];
            $media = $this->mediaDeLinhaBoletim($row['media_final'] ?? null, $notas);
            $rec = $this->recDeNotasJson($notas);
            $out[$mid]['bimestres'][$bim] = ['media' => $media, 'recuperacao' => $rec];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $notas
     */
    private function recDeNotasJson(array $notas): ?float
    {
        foreach ($notas as $codigo => $val) {
            if (!is_numeric($val)) {
                continue;
            }
            $c = strtolower((string) $codigo);
            if ($c === 'rec' || $c === 'rec_final' || str_contains($c, 'recup')) {
                return (float) $val;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{situacao:?string,rotulo:?string,media_final:?float}
     */
    private function avaliarComponenteFicha(
        array $payload,
        int $alunoId,
        int $turmaId,
        int $anoLetivo,
        ?float $mediaAnual,
        ?float $rec,
        ?float $freqGeral
    ): array {
        $turma = is_array($payload['turma'] ?? null) ? $payload['turma'] : [];
        $regra = $this->homologacao->motor()->resolverRegra([
            'ano_letivo' => $anoLetivo,
            'curso_id' => (int) ($turma['curso_novo_id'] ?? $turma['curso_id'] ?? 0) ?: null,
            'serie_id' => (int) ($turma['serie_id'] ?? 0) ?: null,
            'matriz_curricular_id' => (int) ($turma['matriz_curricular_id'] ?? 0) ?: null,
            'periodo_tipo' => 'bimestre',
            'aluno_id' => $alunoId,
            'turma_id' => $turmaId,
        ]);
        if ($regra === null) {
            $regra = $this->homologacao->motor()->regraFallbackDoBoletim(['nota_minima_aprovacao' => 6]);
        }
        $avaliado = $this->homologacao->motor()->avaliar([
            'media' => $mediaAnual,
            'media_antes_rec' => $rec !== null ? $mediaAnual : null,
            'recuperacao' => $rec,
            'tem_nota' => $mediaAnual !== null,
            'frequencia_percentual' => $freqGeral,
            'aluno_id' => $alunoId,
            'turma_id' => $turmaId,
        ], $regra);
        return [
            'situacao' => $avaliado['situacao'] ?? null,
            'rotulo' => $avaliado['rotulo'] ?? null,
            'media_final' => $avaliado['media_final'] ?? $mediaAnual,
        ];
    }

    /**
     * @param array<string,mixed> $notas
     */
    private function mediaDeLinhaBoletim($mediaFinal, array $notas): ?float
    {
        if (is_numeric($mediaFinal)) {
            return (float) $mediaFinal;
        }
        foreach (['media_final', 'media_bim', 'media'] as $k) {
            if (isset($notas[$k]) && is_numeric($notas[$k])) {
                return (float) $notas[$k];
            }
        }
        return null;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function cargaDaMatriz(int $matrizId): array
    {
        $out = ['_duracao' => 50, '_dias' => 200];
        if ($matrizId <= 0) {
            return $out;
        }
        try {
            $matriz = $this->db->fetch(
                'SELECT duracao_padrao_aula_minutos, dias_letivos_previstos FROM matrizes_curriculares WHERE id = :id LIMIT 1',
                ['id' => $matrizId]
            );
            if ($matriz) {
                $out['_duracao'] = (int) ($matriz['duracao_padrao_aula_minutos'] ?? 50) ?: 50;
                $out['_dias'] = (int) ($matriz['dias_letivos_previstos'] ?? 200) ?: 200;
            }
            $rows = [];
            try {
                require_once __DIR__ . '/../Models/Education/MatrizCurricular.php';
                foreach ((new \MatrizCurricular())->getComponentesOficiais($matrizId) as $linha) {
                    $mid = (int) ($linha['materia_id'] ?? 0);
                    if ($mid <= 0) {
                        continue;
                    }
                    $rows[] = [
                        'materia_id' => $mid,
                        'aulas_semana' => (int) ($linha['aulas_semana'] ?? 0),
                        'nome' => (string) ($linha['materia_nome'] ?? ''),
                    ];
                }
            } catch (Throwable $e) {
                $rows = $this->db->fetchAll(
                    'SELECT c.materia_id, c.aulas_semana, m.nome
                     FROM matrizes_curriculares_componentes c
                     INNER JOIN materias m ON m.id = c.materia_id
                     WHERE c.matriz_id = :id
                     ORDER BY c.ordem_boletim ASC, m.nome ASC',
                    ['id' => $matrizId]
                ) ?: [];
            }
            foreach ($rows as $row) {
                $mid = (int) $row['materia_id'];
                $out[$mid] = [
                    'aulas_semana' => (int) ($row['aulas_semana'] ?? 0),
                    'nome' => (string) ($row['nome'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            return $out;
        }
        return $out;
    }

    /**
     * @return array<int, int>
     */
    private function mapaPaiPorFilho(): array
    {
        try {
            require_once __DIR__ . '/../Models/Education/ComponenteCurricular.php';
            return (new \ComponenteCurricular())->mapaPaiPorFilho();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int|string, mixed> $cargas
     * @param array<int, int> $mapaPai
     */
    private function idOficialNaCarga(int $mid, array $cargas, array $mapaPai): int
    {
        if ($mid <= 0) {
            return 0;
        }
        if (isset($cargas[$mid])) {
            return $mid;
        }
        $pai = (int) ($mapaPai[$mid] ?? 0);
        if ($pai > 0 && isset($cargas[$pai])) {
            return $pai;
        }
        return $mid;
    }

    /**
     * @param array<int, array<string,mixed>> $notas
     * @param array<int|string, mixed> $cargas
     * @param array<int, int> $mapaPai
     * @return array<int, array<string,mixed>>
     */
    private function agruparNotasNoOficial(array $notas, array $cargas, array $mapaPai): array
    {
        $out = [];
        $filhosPorPai = [];
        foreach ($notas as $mid => $info) {
            $mid = (int) $mid;
            if ($mid < 0) {
                $nome = mb_strtolower(trim((string) ($info['nome'] ?? '')));
                foreach ($cargas as $cid => $carga) {
                    if (!is_int($cid) && !ctype_digit((string) $cid)) {
                        continue;
                    }
                    $cid = (int) $cid;
                    if ($nome !== '' && $nome === mb_strtolower(trim((string) ($carga['nome'] ?? '')))) {
                        if (!isset($out[$cid])) {
                            $out[$cid] = $info;
                        }
                        break;
                    }
                }
                continue;
            }
            $oficial = $this->idOficialNaCarga($mid, $cargas, $mapaPai);
            if ($oficial > 0 && $oficial !== $mid && isset($cargas[$oficial])) {
                if (isset($notas[$oficial])) {
                    continue;
                }
                $filhosPorPai[$oficial][] = is_array($info) ? $info : [];
                continue;
            }
            $out[$mid] = $info;
        }
        foreach ($filhosPorPai as $pai => $infos) {
            if (!isset($out[$pai])) {
                $out[$pai] = $this->mediaNotasComponentes($infos, (string) ($cargas[$pai]['nome'] ?? 'Componente'));
            }
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $infos
     * @return array{nome:string,bimestres:array<int,array{media:?float,recuperacao:?float}>}
     */
    private function mediaNotasComponentes(array $infos, string $nome): array
    {
        $bims = [];
        for ($b = 1; $b <= 4; $b++) {
            $vals = [];
            $recs = [];
            foreach ($infos as $info) {
                $cel = $info['bimestres'][$b] ?? null;
                if (!is_array($cel)) {
                    continue;
                }
                if (isset($cel['media']) && is_numeric($cel['media'])) {
                    $vals[] = (float) $cel['media'];
                }
                if (isset($cel['recuperacao']) && is_numeric($cel['recuperacao'])) {
                    $recs[] = (float) $cel['recuperacao'];
                }
            }
            $bims[$b] = [
                'media' => $vals !== [] ? round(array_sum($vals) / count($vals), 2) : null,
                'recuperacao' => $recs !== [] ? round(array_sum($recs) / count($recs), 2) : null,
            ];
        }
        return ['nome' => $nome, 'bimestres' => $bims];
    }

    /**
     * @param array<int, array<string,mixed>> $freqs
     * @param array<int|string, mixed> $cargas
     * @param array<int, int> $mapaPai
     * @return array<int, array<string,mixed>>
     */
    private function agruparFrequenciaNoOficial(array $freqs, array $cargas, array $mapaPai): array
    {
        $out = [];
        foreach ($freqs as $mid => $info) {
            $mid = (int) $mid;
            $oficial = $this->idOficialNaCarga($mid, $cargas, $mapaPai);
            $dest = $oficial > 0 ? $oficial : $mid;
            if (!isset($out[$dest])) {
                $out[$dest] = ['faltas' => 0, 'total_aulas' => 0, 'percentual' => null];
            }
            $out[$dest]['faltas'] += (int) ($info['faltas'] ?? 0);
            $out[$dest]['total_aulas'] += (int) ($info['total_aulas'] ?? 0);
        }
        foreach ($out as $mid => $info) {
            $aulas = (int) ($info['total_aulas'] ?? 0);
            $faltas = (int) ($info['faltas'] ?? 0);
            $out[$mid]['percentual'] = $aulas > 0
                ? round((($aulas - $faltas) / $aulas) * 100, 1)
                : null;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function observacoesFicha(int $alunoId, int $turmaId, int $anoLetivo, array $payload): string
    {
        $partes = [];
        $obs = trim((string) ($payload['observacoes'] ?? ''));
        if ($obs !== '') {
            $partes[] = $obs;
        }
        try {
            $del = $this->db->fetch(
                "SELECT d.resultado_decisao, d.justificativa
                 FROM conselho_deliberacoes d
                 INNER JOIN conselho_sessoes s ON s.id = d.sessao_id
                 WHERE s.turma_id = :turma AND s.ano_letivo = :ano AND d.aluno_id = :aluno
                 ORDER BY s.id DESC, d.id DESC LIMIT 1",
                ['turma' => $turmaId, 'ano' => $anoLetivo, 'aluno' => $alunoId]
            );
            if ($del && trim((string) ($del['justificativa'] ?? '')) !== '') {
                $partes[] = 'Conselho de Classe: ' . trim((string) $del['justificativa']);
            }
        } catch (Throwable $e) {
            // módulo de conselho pode não estar migrado
        }
        return implode("\n", array_unique($partes));
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    private function quadroFichaHtml(array $linhas): string
    {
        if ($linhas === []) {
            return '<p>Nenhum componente lançado neste período.</p>';
        }
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $fmt = static function ($v): string {
            return is_numeric($v) ? number_format((float) $v, 1, ',', '.') : '—';
        };
        $html = '<table class="dados"><tr>'
            . '<td class="label">Componente Curricular</td>'
            . '<td class="label">CH</td>'
            . '<td class="label">Nota Final</td>'
            . '<td class="label">Faltas</td>'
            . '<td class="label">Situação</td></tr>';
        foreach ($linhas as $c) {
            $html .= '<tr><td>' . $esc($c['materia_nome'] ?? '') . '</td>'
                . '<td>' . $esc(($c['carga_prevista'] ?? $c['carga_horaria'] ?? '—')) . '</td>'
                . '<td>' . $esc($fmt($c['media_final'] ?? $c['media'] ?? null)) . '</td>'
                . '<td>' . $esc($c['faltas'] ?? '—') . '</td>'
                . '<td>' . $esc($c['rotulo'] ?? '—') . '</td></tr>';
        }
        return $html . '</table>';
    }

    private function rotuloTurno(string $turno): string
    {
        $map = [
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
        ];
        $k = strtolower(trim($turno));
        return $map[$k] ?? ($turno !== '' ? $turno : '—');
    }

    private function rotuloMatricula(string $status): string
    {
        $map = [
            'ativa' => 'Matriculado',
            'transferido' => 'Transferido',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
        ];
        $k = strtolower(trim($status));
        return $map[$k] ?? ($status !== '' ? $status : '—');
    }

    /**
     * @param list<array<string,mixed>> $componentes
     */
    public function quadroNotasHtml(array $componentes): string
    {
        if ($componentes === []) {
            return '<p>Nenhum componente lançado neste período.</p>';
        }
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<table class="dados"><tr>'
            . '<td class="label">Componente</td><td class="label">CH</td>'
            . '<td class="label">Média</td><td class="label">Faltas</td>'
            . '<td class="label">Situação</td></tr>';
        foreach ($componentes as $c) {
            $media = isset($c['media_final']) && is_numeric($c['media_final'])
                ? number_format((float) $c['media_final'], 1, ',', '.')
                : (isset($c['media']) && is_numeric($c['media']) ? number_format((float) $c['media'], 1, ',', '.') : '—');
            $html .= '<tr><td>' . $esc($c['materia_nome'] ?? '') . '</td>'
                . '<td>' . $esc($c['carga_horaria'] ?? '—') . '</td>'
                . '<td>' . $esc($media) . '</td>'
                . '<td>' . $esc($c['faltas'] ?? '—') . '</td>'
                . '<td>' . $esc($c['rotulo'] ?? '—') . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param list<array<string,mixed>> $componentes
     */
    public function quadroBoletimFinalHtml(array $componentes): string
    {
        if ($componentes === []) {
            return '<p>Nenhum componente lançado neste período.</p>';
        }
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $fmt = static function ($v): string {
            return is_numeric($v) ? number_format((float) $v, 1, ',', '.') : '—';
        };
        $html = '<table class="dados"><tr>'
            . '<td class="label">Componente</td>'
            . '<td class="label">B1</td><td class="label">B2</td>'
            . '<td class="label">B3</td><td class="label">B4</td>'
            . '<td class="label">Média Final</td>'
            . '<td class="label">Faltas</td>'
            . '<td class="label">Resultado</td></tr>';
        foreach ($componentes as $c) {
            $html .= '<tr><td>' . $esc($c['materia_nome'] ?? '') . '</td>'
                . '<td>' . $esc($fmt($c['b1'] ?? null)) . '</td>'
                . '<td>' . $esc($fmt($c['b2'] ?? null)) . '</td>'
                . '<td>' . $esc($fmt($c['b3'] ?? null)) . '</td>'
                . '<td>' . $esc($fmt($c['b4'] ?? null)) . '</td>'
                . '<td>' . $esc($fmt($c['media_final'] ?? $c['media'] ?? null)) . '</td>'
                . '<td>' . $esc($c['faltas'] ?? '—') . '</td>'
                . '<td>' . $esc($c['rotulo'] ?? '—') . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    public function tabelaAtaHtml(array $linhas): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<table class="dados"><tr>'
            . '<td class="label">Nº</td><td class="label">Aluno</td>'
            . '<td class="label">Frequência</td><td class="label">Resultado Final</td>'
            . '<td class="label">Destino/Observação</td></tr>';
        $i = 0;
        foreach ($linhas as $linha) {
            $i++;
            $freq = $linha['frequencia']['percentual'] ?? null;
            $freqTxt = is_numeric($freq) ? number_format((float) $freq, 1, ',', '.') . '%' : '—';
            $html .= '<tr><td>' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . '</td>'
                . '<td>' . $esc($linha['aluno']['nome'] ?? '') . '</td>'
                . '<td>' . $esc($freqTxt) . '</td>'
                . '<td>' . $esc($linha['rotulo'] ?? '—') . '</td>'
                . '<td>' . $esc($this->destinoAta($linha)) . '</td></tr>';
        }
        if ($i === 0) {
            $html .= '<tr><td colspan="5">Nenhum aluno neste recorte.</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param list<array<string,mixed>> $linhas
     */
    public function tabelaRelatorioHtml(array $linhas, string $tipo): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<table class="dados"><tr>'
            . '<td class="label">#</td><td class="label">Aluno</td>'
            . '<td class="label">Média</td><td class="label">Frequência</td>'
            . '<td class="label">Situação</td><td class="label">Status</td></tr>';
        $i = 0;
        foreach ($linhas as $linha) {
            $i++;
            $media = $linha['avaliado']['media_final'] ?? null;
            $mediaTxt = is_numeric($media) ? number_format((float) $media, 1, ',', '.') : '—';
            $freq = $linha['frequencia']['percentual'] ?? null;
            $freqTxt = is_numeric($freq) ? number_format((float) $freq, 1, ',', '.') . '%' : '—';
            $html .= '<tr><td>' . $i . '</td>'
                . '<td>' . $esc($linha['aluno']['nome'] ?? '') . '</td>'
                . '<td>' . $esc($mediaTxt) . '</td>'
                . '<td>' . $esc($freqTxt) . '</td>'
                . '<td>' . $esc($linha['rotulo'] ?? '—') . '</td>'
                . '<td>' . $esc(($linha['status'] ?? '') === 'homologado' ? 'Homologado' : 'Prévia') . '</td></tr>';
        }
        if ($i === 0) {
            $html .= '<tr><td colspan="6">Nenhum aluno neste recorte.</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * Número oficial só é gravado quando o documento sai do snapshot homologado.
     *
     * @param array<string,mixed> $payload
     */
    private function ehEmissaoOficial(string $tipo, array $payload): bool
    {
        if (in_array($tipo, ['ficha_individual', 'boletim', 'historico'], true)) {
            return !empty($payload['_homologado']);
        }
        $linhas = is_array($payload['linhas'] ?? null) ? $payload['linhas'] : [];
        if ($linhas === []) {
            return false;
        }
        foreach ($linhas as $linha) {
            if (empty($linha['_homologado'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function payloadResumo(array $payload): array
    {
        return [
            'aluno_id' => $payload['aluno']['id'] ?? null,
            'turma_id' => $payload['turma']['id'] ?? $payload['turma_id'] ?? null,
            'situacao' => $payload['situacao'] ?? $payload['avaliado']['situacao'] ?? null,
            'periodo' => $payload['periodo'] ?? null,
            'homologado' => !empty($payload['_homologado']),
        ];
    }

    private function fmtData($d): string
    {
        $d = trim((string) $d);
        if ($d === '' || $d === '0000-00-00') {
            return '—';
        }
        $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
        return $dt ? $dt->format('d/m/Y') : '—';
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return array{texto:string,aprovados:int,retidos:int,transferidos:int}
     */
    private function totaisAta(array $linhas): array
    {
        $aprovados = 0;
        $retidos = 0;
        $transferidos = 0;
        foreach ($linhas as $linha) {
            $sit = (string) ($linha['situacao'] ?? '');
            if (!empty($linha['aluno']['transferido']) || $sit === 'transferencia') {
                $transferidos++;
            } elseif (in_array($sit, ['reprovado_rendimento', 'reprovado_frequencia'], true)) {
                $retidos++;
            } else {
                $aprovados++;
            }
        }
        $total = count($linhas);
        return [
            'aprovados' => $aprovados,
            'retidos' => $retidos,
            'transferidos' => $transferidos,
            'texto' => 'Total de alunos: ' . $total
                . ' | Aprovados: ' . $aprovados
                . ' | Retidos: ' . $retidos
                . ' | Transferidos: ' . $transferidos,
        ];
    }

    /**
     * @param array<string,mixed> $linha
     */
    private function destinoAta(array $linha): string
    {
        if (!empty($linha['aluno']['transferido']) || ($linha['situacao'] ?? '') === 'transferencia') {
            return 'Transferido';
        }
        $sit = (string) ($linha['situacao'] ?? '');
        if (in_array($sit, ['reprovado_rendimento', 'reprovado_frequencia'], true)) {
            return 'Retido';
        }
        return (string) ($linha['proxima_serie'] ?? $linha['rotulo'] ?? '—');
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function somarCargaHoraria(array $payload): string
    {
        $linhas = $payload['componentes_ficha'] ?? $payload['componentes'] ?? [];
        $soma = 0.0;
        $tem = false;
        foreach (is_array($linhas) ? $linhas : [] as $c) {
            $ch = $c['carga_prevista'] ?? $c['carga_horaria'] ?? null;
            if (is_numeric($ch)) {
                $soma += (float) $ch;
                $tem = true;
            }
        }
        if (!$tem) {
            return '—';
        }
        $txt = (fmod($soma, 1.0) < 0.05) ? (string) (int) round($soma) : number_format($soma, 1, ',', '.');
        return $txt . ' h';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function proximaSerieRotulo(array $payload): string
    {
        $sit = (string) ($payload['situacao'] ?? $payload['avaliado']['situacao'] ?? '');
        if (in_array($sit, ['reprovado_rendimento', 'reprovado_frequencia'], true)) {
            return (string) ($payload['turma']['serie_nome'] ?? $payload['turma']['serie'] ?? 'Retido');
        }
        return (string) ($payload['proxima_serie'] ?? '—');
    }

    private function trajetoriaHtml(int $alunoId): string
    {
        if ($alunoId <= 0) {
            return '';
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT r.ano_letivo, t.nome AS turma_nome, t.serie, r.situacao, r.status
                   FROM resultado_academico r
                   LEFT JOIN turmas t ON t.id = r.turma_id
                  WHERE r.aluno_id = :a AND r.periodo_tipo = 'ano' AND r.status = 'homologado'
                  ORDER BY r.ano_letivo ASC",
                ['a' => $alunoId]
            ) ?: [];
        } catch (Throwable $e) {
            return '';
        }
        if ($rows === []) {
            return '';
        }
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<table class="dados"><tr>'
            . '<td class="label">Ano</td><td class="label">Ano/Série</td>'
            . '<td class="label">Estabelecimento</td><td class="label">Resultado</td></tr>';
        foreach ($rows as $r) {
            $html .= '<tr><td>' . $esc($r['ano_letivo'] ?? '') . '</td>'
                . '<td>' . $esc($r['serie'] ?? $r['turma_nome'] ?? '') . '</td>'
                . '<td>' . $esc($r['turma_nome'] ?? '') . '</td>'
                . '<td>' . $esc($r['situacao'] ?? '') . '</td></tr>';
        }
        return $html . '</table>';
    }

    private function dataPorExtenso(string $iso): string
    {
        $dt = DateTime::createFromFormat('Y-m-d', substr($iso, 0, 10));
        if (!$dt) {
            return date('d/m/Y');
        }
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];
        $dia = (int) $dt->format('j');
        $mes = $meses[(int) $dt->format('n')] ?? $dt->format('m');
        return $dia . ' de ' . $mes . ' de ' . $dt->format('Y');
    }

    /**
     * @return array<string,mixed>
     */
    private function unidadeDaTurma(int $turmaId): array
    {
        try {
            $aluno = $this->db->fetch(
                'SELECT a.* FROM alunos a
                  INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = :t
                 ORDER BY m.id DESC LIMIT 1',
                ['t' => $turmaId]
            );
            if ($aluno) {
                $u = $this->declarations->getUnidadeForAluno($aluno);
                if (is_array($u)) {
                    return $u;
                }
            }
        } catch (Throwable $e) {
            // segue fallback
        }
        try {
            $u = $this->declarations->getUnidadeForAluno([]);
            return is_array($u) ? $u : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array{codigo:string,nome:string}>
     */
    public function modelosDisponiveis(): array
    {
        $out = [];
        try {
            require_once __DIR__ . '/../Modulos/modelos-documentos/Services/ModeloDocumentoService.php';
            $svc = new \App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService($this->db);
            foreach ($svc->listar(true) as $m) {
                $codigo = (string) ($m['codigo'] ?? '');
                if ($codigo === '' || (!str_starts_with($codigo, 'resultado_') && !str_starts_with($codigo, 'declaracao_'))) {
                    continue;
                }
                $out[] = ['codigo' => $codigo, 'nome' => (string) ($m['nome'] ?? $codigo)];
            }
        } catch (Throwable $e) {
            // schema ainda não aplicado
        }
        if ($out === []) {
            foreach (ResultadoAcademico::LAYOUT_PADRAO as $codigo) {
                $out[] = ['codigo' => $codigo, 'nome' => $codigo];
            }
        }
        return $out;
    }
}
