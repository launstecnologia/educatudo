<?php

namespace App\Modulos\ModelosDocumentos\Services;

require_once __DIR__ . '/../../../Core/Database.php';

use Database;

/**
 * Modelos editáveis de documentos (HTML + placeholders {{campo}}).
 */
class ModeloDocumentoService
{
    public const PLACEHOLDERS = [
        'escola_nome' => 'Nome da escola/unidade',
        'escola_cnpj' => 'CNPJ (com rótulo)',
        'escola_cnpj_numero' => 'CNPJ (somente número/formatado)',
        'escola_endereco' => 'Endereço',
        'escola_docs' => 'CNPJ / INEP / telefone',
        'escola_origem' => 'Escola de origem do aluno',
        'logo_html' => 'Logo (HTML img)',
        'aluno_nome' => 'Nome do aluno',
        'aluno_cpf' => 'CPF do aluno',
        'aluno_rg' => 'RG do aluno',
        'aluno_cpf_frase' => 'Frase “, CPF nº …”',
        'aluno_data_nasc' => 'Data de nascimento',
        'aluno_nasc_frase' => 'Frase “, nascido em …”',
        'aluno_email' => 'E-mail do aluno',
        'aluno_telefone' => 'Telefone do aluno',
        'aluno_endereco' => 'Endereço do aluno',
        'aluno_cidade' => 'Cidade do aluno',
        'aluno_codigo' => 'RA / código',
        'matricula_numero' => 'Número de matrícula',
        'curso_nome' => 'Curso / segmento',
        'resp_nome' => 'Nome do responsável',
        'resp_cpf' => 'CPF do responsável',
        'resp_rg' => 'RG do responsável',
        'resp_email' => 'E-mail do responsável',
        'resp_telefone' => 'Telefone/WhatsApp',
        'resp_celular' => 'Celular do responsável',
        'resp_parentesco' => 'Parentesco',
        'resp_endereco' => 'Endereço do responsável',
        'resp_bairro' => 'Bairro do responsável',
        'resp_cep' => 'CEP do responsável',
        'resp_cidade' => 'Cidade do responsável',
        'resp_estado_civil' => 'Estado civil do responsável',
        'resp2_nome' => '2º responsável — nome',
        'resp2_cpf' => '2º responsável — CPF',
        'resp2_rg' => '2º responsável — RG',
        'resp2_email' => '2º responsável — e-mail',
        'resp2_telefone' => '2º responsável — telefone',
        'resp2_celular' => '2º responsável — celular',
        'resp2_parentesco' => '2º responsável — parentesco',
        'resp2_endereco' => '2º responsável — endereço',
        'resp2_bairro' => '2º responsável — bairro',
        'resp2_cep' => '2º responsável — CEP',
        'resp2_cidade' => '2º responsável — cidade',
        'resp2_estado_civil' => '2º responsável — estado civil',
        'se_resp2' => 'Bloco condicional: {{#se_resp2}}…{{/se_resp2}} (só se houver 2º responsável)',
        'se_resp_fin' => 'Bloco condicional: {{#se_resp_fin}}…{{/se_resp_fin}}',
        'resp_fin_nome' => 'Responsável financeiro — nome',
        'resp_fin_cpf' => 'Responsável financeiro — CPF',
        'resp_fin_rg' => 'Responsável financeiro — RG',
        'resp_fin_email' => 'Responsável financeiro — e-mail',
        'resp_fin_telefone' => 'Responsável financeiro — telefone',
        'resp_fin_celular' => 'Responsável financeiro — celular',
        'resp_fin_endereco' => 'Responsável financeiro — endereço',
        'resp_fin_bairro' => 'Responsável financeiro — bairro',
        'resp_fin_cep' => 'Responsável financeiro — CEP',
        'resp_fin_cidade' => 'Responsável financeiro — cidade',
        'pagante1_nome' => 'Pagante 1 — nome',
        'pagante1_cpf' => 'Pagante 1 — CPF',
        'pagante1_percentual' => 'Pagante 1 — percentual',
        'pagante2_nome' => 'Pagante 2 — nome',
        'pagante2_cpf' => 'Pagante 2 — CPF',
        'pagante2_percentual' => 'Pagante 2 — percentual',
        'pagante3_nome' => 'Pagante 3 — nome',
        'pagante3_cpf' => 'Pagante 3 — CPF',
        'pagante3_percentual' => 'Pagante 3 — percentual',
        'pagante_modo' => 'Modo de pagante (rótulo)',
        'documento_assinatura' => 'Documento de assinatura (rótulo)',
        'valor_anuidade' => 'Valor total da anuidade / material',
        'valor_parcela' => 'Valor bruto da parcela',
        'valor_liquido_parcela' => 'Valor líquido da parcela',
        'valor_primeira_parcela' => 'Valor da 1ª parcela',
        'desconto_primeira' => 'Desconto na 1ª parcela',
        'desconto_primeira_obs' => 'Obs. desconto 1ª parcela',
        'valor_liquido_primeira' => 'Valor líquido da 1ª parcela',
        'qtd_parcelas_primeira' => 'Qtd. parcelas da 1ª',
        'valor_mensalidades_liq' => 'Total mensalidades com desconto',
        'desconto_parcela' => 'Desconto por parcela',
        'num_parcelas' => 'Número de parcelas',
        'data_rematricula' => 'Data da rematrícula/solicitação',
        'desc1_nome' => 'Desconto 1 — nome',
        'desc1_valor' => 'Desconto 1 — valor',
        'desc2_nome' => 'Desconto 2 — nome',
        'desc2_valor' => 'Desconto 2 — valor',
        'desc3_nome' => 'Desconto 3 — nome',
        'desc3_valor' => 'Desconto 3 — valor',
        'desc4_nome' => 'Desconto 4 — nome',
        'desc4_valor' => 'Desconto 4 — valor',
        'turma_nome' => 'Turma',
        'turma_frase' => 'Frase “na turma …”',
        'serie' => 'Série',
        'ano_letivo' => 'Ano letivo',
        'situacao_matricula' => 'Situação da matrícula',
        'data_entrada' => 'Data de entrada',
        'data_saida' => 'Data de saída',
        'tipo_matricula' => 'Tipo (Matrícula / Rematrícula / …)',
        'data_hoje' => 'Data de emissão',
        'observacoes' => 'Observações',
        'info_pertinente' => 'Informação pertinente (texto livre)',
        'info_pertinente_html' => 'Informação pertinente (HTML com bullets)',
        'titulo' => 'Título do documento',
        'doc_rotulo' => 'Declaração / Documento',
        'numero' => 'Número da emissão',
        'ano' => 'Ano do número',
        'cidade_data' => 'Cidade, data por extenso',
        'diretor_nome' => 'Nome do diretor',
        'secretario_nome' => 'Nome do secretário',
        'periodo_inicio' => 'Início do período',
        'periodo_fim' => 'Fim do período',
        'frequencia_html' => 'Tabela de frequência (HTML)',
        'data_comparecimento' => 'Data do comparecimento',
        'periodo_texto' => 'Período / horário',
        'periodo_texto_frase' => 'Frase do período',
        'data_evento' => 'Data do evento (autorização)',
        'aut_horario' => 'Horário autorizado',
        'aut_motivo' => 'Motivo',
        'aut_nome_autorizado' => 'Nome autorizado',
        'aut_documento' => 'Documento do autorizado',
        'aut_parentesco' => 'Parentesco do autorizado',
        'aut_local' => 'Local',
        'aut_hora_saida' => 'Hora saída',
        'aut_hora_retorno' => 'Hora retorno',
        'aut_finalidade' => 'Finalidade',
        'responsaveis_html' => 'Lista de responsáveis (HTML)',
        'historico_html' => 'Histórico escolar (HTML)',
        'razao_social' => 'Razão social (layout padrão)',
        'cnpj_layout' => 'CNPJ do layout padrão',
        'rodape_unidades' => 'Linha de unidades/contato do rodapé',
        'assinante_nome' => 'Nome de quem assina (layout padrão)',
        'assinante_cargo' => 'Cargo de quem assina (Direção, Coordenação…)',
    ];

    /** Cargos disponíveis na assinatura do layout padrão. */
    public const CARGOS_ASSINANTE = [
        'direcao' => 'Direção',
        'coordenacao' => 'Coordenação',
        'secretaria' => 'Secretaria',
    ];

    /**
     * Aliases legados de Word (@campo) → placeholder canônico {{campo}}.
     * Usado nos contratos exportados do sistema antigo (ex.: COLAG).
     *
     * @var array<string,string>
     */
    public const PLACEHOLDER_ALIASES = [
        'aluno' => 'aluno_nome',
        'cpfaluno' => 'aluno_cpf',
        'rgaluno' => 'aluno_rg',
        'dtnasc' => 'aluno_data_nasc',
        'emailaluno' => 'aluno_email',
        'fonealuno' => 'aluno_telefone',
        'endaluno' => 'aluno_endereco',
        'cidaluno' => 'aluno_cidade',
        'anoletivo' => 'ano_letivo',
        'matricula' => 'matricula_numero',
        'curso' => 'curso_nome',
        'turma' => 'turma_nome',
        'serie' => 'serie',
        'escorigem' => 'escola_origem',
        'empcnpj' => 'escola_cnpj_numero',
        'empend' => 'escola_endereco',
        'local_data' => 'cidade_data',
        'nomresp' => 'resp_nome',
        'cpfresp' => 'resp_cpf',
        'rgresp' => 'resp_rg',
        'emailresp' => 'resp_email',
        'endresp' => 'resp_endereco',
        'bairesp' => 'resp_bairro',
        'cepresp' => 'resp_cep',
        'cidresp' => 'resp_cidade',
        'r1cel' => 'resp_celular',
        'resp1fone' => 'resp_telefone',
        'r1civil' => 'resp_estado_civil',
        'gparentesco1' => 'resp_parentesco',
        'resp2' => 'resp2_nome',
        'cpfr2' => 'resp2_cpf',
        'rgr2' => 'resp2_rg',
        'emailr2' => 'resp2_email',
        'endr2' => 'resp2_endereco',
        'bairroresp2' => 'resp2_bairro',
        'cepr2' => 'resp2_cep',
        'cidr2' => 'resp2_cidade',
        'celresp2' => 'resp2_celular',
        'foneresp2' => 'resp2_telefone',
        'ecivilr2' => 'resp2_estado_civil',
        'gparentesco2' => 'resp2_parentesco',
        'resp3' => 'resp_fin_nome',
        'cpf3' => 'resp_fin_cpf',
        'rg3' => 'resp_fin_rg',
        'emailr3' => 'resp_fin_email',
        'endr3' => 'resp_fin_endereco',
        'bairroresp3' => 'resp_fin_bairro',
        'cepr3' => 'resp_fin_cep',
        'cidresp3' => 'resp_fin_cidade',
        'celresp3' => 'resp_fin_celular',
        'foneresp3' => 'resp_fin_telefone',
        'vltplano' => 'valor_anuidade',
        'vlmensal' => 'valor_parcela',
        'vlmat' => 'valor_primeira_parcela',
        'vliq_parcela' => 'valor_liquido_parcela',
        'mensliq' => 'valor_liquido_parcela',
        'planoliq' => 'valor_mensalidades_liq',
        'descparc' => 'desconto_parcela',
        'qtpar' => 'num_parcelas',
        'qtmat' => 'qtd_parcelas_primeira',
        'vldescmat' => 'desconto_primeira',
        'descmatliq' => 'desconto_primeira_obs',
        'vlliqmat' => 'valor_liquido_primeira',
        'diacard' => 'data_rematricula',
        '1desc' => 'desc1_valor',
        '2desc' => 'desc2_valor',
        '3desc' => 'desc3_valor',
        '4desc' => 'desc4_valor',
        'ndesc1' => 'desc1_nome',
        'ndesc2' => 'desc2_nome',
        'ndesc3' => 'desc3_nome',
        'ndesc4' => 'desc4_nome',
        // Artefatos de merge do Word (texto colado ao token)
        'dtnascesc' => 'aluno_data_nasc',
        'rgalunocpf' => 'aluno_rg',
        'emailrespgrau' => 'resp_email',
    ];

    /** Códigos de sistema: código fixo; exclusão bloqueada. */
    public const CODIGOS_SISTEMA = [
        'contrato_matricula',
        'contrato_material_didatico',
        'declaracao_matricula',
        'declaracao_frequencia',
        'declaracao_comparecimento',
        'declaracao_transferencia',
        'declaracao_aut_saida',
        'declaracao_ficha_matricula',
        'declaracao_bolsista_integral',
    ];

    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function codigoParaDeclaracao(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        return 'declaracao_' . preg_replace('/[^a-z0-9_]+/', '_', $tipo);
    }

    public static function isCodigoSistema(string $codigo): bool
    {
        return in_array($codigo, self::CODIGOS_SISTEMA, true);
    }

    public static function isModeloDeclaracao(array $modelo): bool
    {
        $codigo = (string) ($modelo['codigo'] ?? '');
        return str_starts_with($codigo, 'declaracao_');
    }

    public function schemaReady(): bool
    {
        try {
            return (bool) $this->db->fetch("SHOW TABLES LIKE 'secretaria_modelos_documentos'");
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function listar(bool $somenteAtivos = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT id, codigo, nome, descricao, ativo, updated_at';
        if ($this->temColuna('orientacao')) {
            $sql .= ', orientacao';
        }
        if ($this->temColuna('usar_layout_padrao')) {
            $sql .= ', usar_layout_padrao';
        }
        $sql .= ' FROM secretaria_modelos_documentos';
        if ($somenteAtivos) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY nome ASC';
        return $this->db->fetchAll($sql) ?: [];
    }

    /** Modelos de declaração (código declaracao_*). */
    public function listarDeclaracoes(bool $somenteAtivos = true): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = "SELECT id, codigo, nome, descricao, ativo, updated_at";
        if ($this->temColuna('usar_layout_padrao')) {
            $sql .= ', usar_layout_padrao';
        }
        $sql .= " FROM secretaria_modelos_documentos WHERE codigo LIKE 'declaracao\\_%' ESCAPE '\\\\'";
        if ($somenteAtivos) {
            $sql .= ' AND ativo = 1';
        }
        $sql .= ' ORDER BY nome ASC';
        return $this->db->fetchAll($sql) ?: [];
    }

    /**
     * Modelos que NÃO são declaração/autorização (ex.: contrato_matricula).
     * Evita duplicar a lista de Gestão Escolar → Declarações.
     */
    public function listarExcetoDeclaracoes(bool $somenteAtivos = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = "SELECT id, codigo, nome, descricao, ativo, updated_at";
        if ($this->temColuna('orientacao')) {
            $sql .= ', orientacao';
        }
        $sql .= " FROM secretaria_modelos_documentos WHERE codigo NOT LIKE 'declaracao\\_%' ESCAPE '\\\\'";
        if ($somenteAtivos) {
            $sql .= ' AND ativo = 1';
        }
        $sql .= ' ORDER BY nome ASC';
        return $this->db->fetchAll($sql) ?: [];
    }

    /**
     * Agrupa modelos de declaração: declaracao | autorizacao | documento.
     *
     * @param list<array<string,mixed>> $modelos
     * @return array{declaracao:list,autorizacao:list,documento:list}
     */
    public static function agruparDeclaracoes(array $modelos): array
    {
        $out = ['declaracao' => [], 'autorizacao' => [], 'documento' => []];
        foreach ($modelos as $m) {
            $codigo = (string) ($m['codigo'] ?? '');
            if (str_contains($codigo, '_aut_') || str_starts_with($codigo, 'declaracao_aut_')) {
                $out['autorizacao'][] = $m;
            } elseif (in_array($codigo, ['declaracao_ficha_matricula', 'declaracao_historico'], true)
                || str_contains($codigo, '_ficha_')
                || str_contains($codigo, '_historico')
            ) {
                $out['documento'][] = $m;
            } else {
                $out['declaracao'][] = $m;
            }
        }
        return $out;
    }

    public function layoutPadraoReady(): bool
    {
        try {
            return (bool) $this->db->fetch("SHOW TABLES LIKE 'secretaria_declaracoes_layouts'");
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<string,mixed> */
    public function getLayoutPadrao(): array
    {
        $vazio = [
            'id' => 1,
            'cabecalho_html' => '',
            'rodape_html' => '',
            'imagem_cabecalho' => null,
            'imagem_rodape' => null,
            'razao_social' => null,
            'cnpj' => null,
            'unidade_assinatura_id' => null,
            'cargo_assinante' => 'direcao',
            'assinante_nome' => null,
        ];
        if (!$this->layoutPadraoReady()) {
            return $vazio;
        }
        $row = $this->db->fetch('SELECT * FROM secretaria_declaracoes_layouts WHERE id = 1 LIMIT 1');
        return $row ?: $vazio;
    }

    /**
     * Resolve nome/cargo da assinatura a partir do layout + unidade.
     *
     * @param array<string,mixed> $layout
     * @param array<string,mixed>|null $unidade
     * @return array{nome:string,cargo:string,cargo_codigo:string}
     */
    public function resolverAssinaturaLayout(array $layout, ?array $unidade = null): array
    {
        $cargoCodigo = strtolower(trim((string) ($layout['cargo_assinante'] ?? 'direcao')));
        if (!isset(self::CARGOS_ASSINANTE[$cargoCodigo])) {
            $cargoCodigo = 'direcao';
        }
        $cargoLabel = self::CARGOS_ASSINANTE[$cargoCodigo];

        $nome = trim((string) ($layout['assinante_nome'] ?? ''));
        if ($nome === '' && is_array($unidade)) {
            if ($cargoCodigo === 'secretaria') {
                $nome = trim((string) ($unidade['secretario_nome'] ?? ''));
            } elseif ($cargoCodigo === 'coordenacao') {
                $nome = trim((string) ($unidade['coordenador_nome'] ?? $unidade['coordenacao_nome'] ?? ''));
            } else {
                $nome = trim((string) ($unidade['diretor_nome'] ?? ''));
            }
        }

        return [
            'nome' => $nome,
            'cargo' => $cargoLabel,
            'cargo_codigo' => $cargoCodigo,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function salvarLayoutPadrao(array $data, ?array $user = null): void
    {
        if (!$this->layoutPadraoReady()) {
            throw new \RuntimeException('Tabela declaracoes_layout_padrao indisponível. Execute a migration.');
        }
        $cargo = strtolower(trim((string) ($data['cargo_assinante'] ?? 'direcao')));
        if (!isset(self::CARGOS_ASSINANTE[$cargo])) {
            $cargo = 'direcao';
        }
        $unidadeId = (int) ($data['unidade_assinatura_id'] ?? 0);
        if ($unidadeId > 0) {
            try {
                require_once BASE_PATH . '/app/Models/Education/SchoolUnit.php';
                $unidadeOk = (new \SchoolUnit())->findById($unidadeId);
                if (!$unidadeOk) {
                    $unidadeId = 0;
                }
            } catch (\Throwable $e) {
                $unidadeId = 0;
            }
        }
        $payload = [
            'cabecalho_html' => (string) ($data['cabecalho_html'] ?? ''),
            'rodape_html' => (string) ($data['rodape_html'] ?? ''),
            'razao_social' => trim((string) ($data['razao_social'] ?? '')) ?: null,
            'cnpj' => trim((string) ($data['cnpj'] ?? '')) ?: null,
            'unidade_assinatura_id' => $unidadeId > 0 ? $unidadeId : null,
            'cargo_assinante' => $cargo,
            'assinante_nome' => trim((string) ($data['assinante_nome'] ?? '')) ?: null,
            'atualizado_por' => $user['id'] ?? null,
        ];
        if (array_key_exists('imagem_cabecalho', $data)) {
            $payload['imagem_cabecalho'] = ($data['imagem_cabecalho'] !== null && $data['imagem_cabecalho'] !== '')
                ? (string) $data['imagem_cabecalho'] : null;
        }
        if (array_key_exists('imagem_rodape', $data)) {
            $payload['imagem_rodape'] = ($data['imagem_rodape'] !== null && $data['imagem_rodape'] !== '')
                ? (string) $data['imagem_rodape'] : null;
        }

        $temAssinatura = $this->temColunaLayout('unidade_assinatura_id');

        $exist = $this->db->fetch('SELECT id FROM secretaria_declaracoes_layouts WHERE id = 1 LIMIT 1');
        if ($exist) {
            $sets = 'cabecalho_html = ?, rodape_html = ?, razao_social = ?, cnpj = ?, atualizado_por = ?';
            $params = [
                $payload['cabecalho_html'],
                $payload['rodape_html'],
                $payload['razao_social'],
                $payload['cnpj'],
                $payload['atualizado_por'],
            ];
            if ($temAssinatura) {
                $sets .= ', unidade_assinatura_id = ?, cargo_assinante = ?, assinante_nome = ?';
                $params[] = $payload['unidade_assinatura_id'];
                $params[] = $payload['cargo_assinante'];
                $params[] = $payload['assinante_nome'];
            }
            if (array_key_exists('imagem_cabecalho', $payload)) {
                $sets .= ', imagem_cabecalho = ?';
                $params[] = $payload['imagem_cabecalho'];
            }
            if (array_key_exists('imagem_rodape', $payload)) {
                $sets .= ', imagem_rodape = ?';
                $params[] = $payload['imagem_rodape'];
            }
            $this->db->update("UPDATE secretaria_declaracoes_layouts SET {$sets} WHERE id = 1", $params);
            return;
        }
        if ($temAssinatura) {
            $this->db->insert(
                'INSERT INTO secretaria_declaracoes_layouts
                    (id, cabecalho_html, rodape_html, imagem_cabecalho, imagem_rodape, razao_social, cnpj,
                     unidade_assinatura_id, cargo_assinante, assinante_nome, atualizado_por)
                 VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $payload['cabecalho_html'],
                    $payload['rodape_html'],
                    $payload['imagem_cabecalho'] ?? null,
                    $payload['imagem_rodape'] ?? null,
                    $payload['razao_social'],
                    $payload['cnpj'],
                    $payload['unidade_assinatura_id'],
                    $payload['cargo_assinante'],
                    $payload['assinante_nome'],
                    $payload['atualizado_por'],
                ]
            );
            return;
        }
        $this->db->insert(
            'INSERT INTO secretaria_declaracoes_layouts
                (id, cabecalho_html, rodape_html, imagem_cabecalho, imagem_rodape, razao_social, cnpj, atualizado_por)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?)',
            [
                $payload['cabecalho_html'],
                $payload['rodape_html'],
                $payload['imagem_cabecalho'] ?? null,
                $payload['imagem_rodape'] ?? null,
                $payload['razao_social'],
                $payload['cnpj'],
                $payload['atualizado_por'],
            ]
        );
    }

    /**
     * Aplica cabeçalho/rodapé/imagens do layout padrão quando o modelo herda.
     *
     * @param array<string,mixed> $modelo
     * @return array<string,mixed>
     */
    public function aplicarLayoutPadraoNoModelo(array $modelo): array
    {
        if (!self::isModeloDeclaracao($modelo)) {
            return $modelo;
        }
        $usar = true;
        if ($this->temColuna('usar_layout_padrao')) {
            $usar = (int) ($modelo['usar_layout_padrao'] ?? 1) === 1;
        }
        if (!$usar || !$this->layoutPadraoReady()) {
            return $modelo;
        }
        $layout = $this->getLayoutPadrao();
        $modelo['cabecalho_html'] = (string) ($layout['cabecalho_html'] ?? '');
        $modelo['rodape_html'] = (string) ($layout['rodape_html'] ?? '');
        if (!empty($layout['imagem_cabecalho'])) {
            $modelo['imagem_cabecalho'] = $layout['imagem_cabecalho'];
        }
        if (!empty($layout['imagem_rodape'])) {
            $modelo['imagem_rodape'] = $layout['imagem_rodape'];
        }
        $modelo['_layout_razao_social'] = (string) ($layout['razao_social'] ?? '');
        $modelo['_layout_cnpj'] = (string) ($layout['cnpj'] ?? '');
        $modelo['_layout_unidade_assinatura_id'] = (int) ($layout['unidade_assinatura_id'] ?? 0);
        $modelo['_layout_cargo_assinante'] = (string) ($layout['cargo_assinante'] ?? 'direcao');
        $modelo['_layout_assinante_nome'] = (string) ($layout['assinante_nome'] ?? '');
        return $modelo;
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->schemaReady()) {
            return null;
        }
        $row = $this->db->fetch('SELECT * FROM secretaria_modelos_documentos WHERE id = ? LIMIT 1', [$id]);
        return $row ?: null;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '' || !$this->schemaReady()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM secretaria_modelos_documentos WHERE codigo = ? AND ativo = 1 LIMIT 1',
            [$codigo]
        );
        return $row ?: null;
    }

    public function salvar(array $data, ?int $id = null, ?array $user = null): int
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Tabela modelos_documentos indisponível. Execute a migration.');
        }

        $codigo = $this->normalizarCodigo((string) ($data['codigo'] ?? ''));
        $nome = trim((string) ($data['nome'] ?? ''));
        $corpo = trim((string) ($data['corpo_html'] ?? ''));
        if ($codigo === '' || $nome === '' || $corpo === '') {
            throw new \InvalidArgumentException('Código, nome e corpo são obrigatórios.');
        }

        $orientacao = (($data['orientacao'] ?? '') === 'paisagem') ? 'paisagem' : 'retrato';
        $payload = [
            'codigo' => $codigo,
            'nome' => $nome,
            'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
            'cabecalho_html' => (string) ($data['cabecalho_html'] ?? ''),
            'corpo_html' => $corpo,
            'rodape_html' => (string) ($data['rodape_html'] ?? ''),
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'atualizado_por' => $user['id'] ?? null,
            'orientacao' => $orientacao,
            'usar_layout_padrao' => array_key_exists('usar_layout_padrao', $data)
                ? (!empty($data['usar_layout_padrao']) ? 1 : 0)
                : 0,
        ];

        $temImagens = $this->temColuna('imagem_cabecalho');
        $imgCab = array_key_exists('imagem_cabecalho', $data) ? $data['imagem_cabecalho'] : null;
        $imgRod = array_key_exists('imagem_rodape', $data) ? $data['imagem_rodape'] : null;

        if ($id && $id > 0) {
            $exist = $this->findById($id);
            if (!$exist) {
                throw new \InvalidArgumentException('Modelo não encontrado.');
            }
            if (self::isCodigoSistema((string) ($exist['codigo'] ?? ''))) {
                $codigo = (string) $exist['codigo'];
                $payload['codigo'] = $codigo;
            }
            $dup = $this->db->fetch(
                'SELECT id FROM secretaria_modelos_documentos WHERE codigo = ? AND id <> ? LIMIT 1',
                [$codigo, $id]
            );
            if ($dup) {
                throw new \InvalidArgumentException('Já existe outro modelo com este código.');
            }

            $sets = 'codigo = ?, nome = ?, descricao = ?, cabecalho_html = ?, corpo_html = ?,
                     rodape_html = ?, ativo = ?, atualizado_por = ?';
            $params = [
                $payload['codigo'],
                $payload['nome'],
                $payload['descricao'],
                $payload['cabecalho_html'],
                $payload['corpo_html'],
                $payload['rodape_html'],
                $payload['ativo'],
                $payload['atualizado_por'],
            ];
            if ($this->temColuna('orientacao')) {
                $sets .= ', orientacao = ?';
                $params[] = $payload['orientacao'];
            }
            if ($this->temColuna('usar_layout_padrao')) {
                $sets .= ', usar_layout_padrao = ?';
                $params[] = $payload['usar_layout_padrao'];
            }
            if ($temImagens && $imgCab !== null) {
                $sets .= ', imagem_cabecalho = ?';
                $params[] = $imgCab !== '' ? $imgCab : null;
            }
            if ($temImagens && $imgRod !== null) {
                $sets .= ', imagem_rodape = ?';
                $params[] = $imgRod !== '' ? $imgRod : null;
            }
            $params[] = $id;
            $this->db->update(
                "UPDATE secretaria_modelos_documentos SET {$sets} WHERE id = ?",
                $params
            );
            return $id;
        }

        $dup = $this->db->fetch(
            'SELECT id FROM secretaria_modelos_documentos WHERE codigo = ? LIMIT 1',
            [$codigo]
        );
        if ($dup) {
            throw new \InvalidArgumentException('Já existe um modelo com este código.');
        }

        $cols = 'codigo, nome, descricao, cabecalho_html, corpo_html, rodape_html, ativo, criado_por, atualizado_por';
        $marks = '?,?,?,?,?,?,?,?,?';
        $params = [
            $payload['codigo'],
            $payload['nome'],
            $payload['descricao'],
            $payload['cabecalho_html'],
            $payload['corpo_html'],
            $payload['rodape_html'],
            $payload['ativo'],
            $user['id'] ?? null,
            $payload['atualizado_por'],
        ];
        if ($this->temColuna('orientacao')) {
            $cols .= ', orientacao';
            $marks .= ',?';
            $params[] = $payload['orientacao'];
        }
        if ($this->temColuna('usar_layout_padrao')) {
            $cols .= ', usar_layout_padrao';
            $marks .= ',?';
            $params[] = $payload['usar_layout_padrao'];
        }
        if ($temImagens) {
            $cols .= ', imagem_cabecalho, imagem_rodape';
            $marks .= ',?,?';
            $params[] = ($imgCab !== null && $imgCab !== '') ? $imgCab : null;
            $params[] = ($imgRod !== null && $imgRod !== '') ? $imgRod : null;
        }

        $this->db->insert(
            "INSERT INTO secretaria_modelos_documentos ({$cols}) VALUES ({$marks})",
            $params
        );
        return (int) $this->db->lastInsertId();
    }

    public function excluir(int $id): void
    {
        if ($id <= 0 || !$this->schemaReady()) {
            return;
        }
        $exist = $this->findById($id);
        if ($exist && self::isCodigoSistema((string) ($exist['codigo'] ?? ''))) {
            throw new \InvalidArgumentException(
                'Modelos do sistema não podem ser excluídos. Desative-os se não quiser usá-los.'
            );
        }
        $this->db->query('DELETE FROM secretaria_modelos_documentos WHERE id = ?', [$id]);
    }

    /**
     * @param array<string,string> $vars
     * @param array<string,mixed>|null $config app config (para resolver imagens no S3)
     */
    public function renderHtml(array $modelo, array $vars, string $estilo = 'auto', ?array $config = null): string
    {
        $modelo = $this->aplicarLayoutPadraoNoModelo($modelo);

        // Vars do layout padrão (razão social / CNPJ / unidades / assinatura)
        if (!isset($vars['razao_social']) || $vars['razao_social'] === '') {
            $vars['razao_social'] = htmlspecialchars(
                (string) ($modelo['_layout_razao_social'] ?? $vars['escola_nome'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            );
        }
        if (!isset($vars['cnpj_layout']) || $vars['cnpj_layout'] === '') {
            $vars['cnpj_layout'] = htmlspecialchars((string) ($modelo['_layout_cnpj'] ?? ''), ENT_QUOTES, 'UTF-8');
        }
        if (!isset($vars['rodape_unidades'])) {
            $vars['rodape_unidades'] = $vars['escola_docs'] ?? '';
        }
        if (!isset($vars['assinante_nome']) || $vars['assinante_nome'] === ''
            || !isset($vars['assinante_cargo']) || $vars['assinante_cargo'] === '') {
            $unidadeAssinatura = null;
            $unidadeId = (int) ($modelo['_layout_unidade_assinatura_id'] ?? 0);
            if ($unidadeId > 0) {
                try {
                    require_once BASE_PATH . '/app/Models/Education/SchoolUnit.php';
                    $unidadeAssinatura = (new \SchoolUnit())->findById($unidadeId) ?: null;
                } catch (\Throwable $e) {
                    $unidadeAssinatura = null;
                }
            }
            $assinatura = $this->resolverAssinaturaLayout([
                'cargo_assinante' => $modelo['_layout_cargo_assinante'] ?? 'direcao',
                'assinante_nome' => $modelo['_layout_assinante_nome'] ?? '',
            ], is_array($unidadeAssinatura) ? $unidadeAssinatura : null);
            if (!isset($vars['assinante_nome']) || $vars['assinante_nome'] === '') {
                $vars['assinante_nome'] = htmlspecialchars($assinatura['nome'], ENT_QUOTES, 'UTF-8');
            }
            if (!isset($vars['assinante_cargo']) || $vars['assinante_cargo'] === '') {
                $vars['assinante_cargo'] = htmlspecialchars($assinatura['cargo'], ENT_QUOTES, 'UTF-8');
            }
        }

        $cab = $this->aplicarPlaceholders((string) ($modelo['cabecalho_html'] ?? ''), $vars);
        $corpo = $this->aplicarPlaceholders((string) ($modelo['corpo_html'] ?? ''), $vars);
        $rodape = $this->aplicarPlaceholders((string) ($modelo['rodape_html'] ?? ''), $vars);

        $codigo = (string) ($modelo['codigo'] ?? '');
        if ($estilo === 'auto') {
            $estilo = str_starts_with($codigo, 'declaracao_') ? 'declaracao' : 'simples';
        }

        $css = $estilo === 'declaracao' ? $this->cssDeclaracao() : $this->cssSimples();
        $orientacao = (($modelo['orientacao'] ?? 'retrato') === 'paisagem') ? 'paisagem' : 'retrato';
        $css .= "\n" . $this->cssBanners($orientacao);

        $imgCabHtml = '';
        $imgRodHtml = '';
        $srcCab = $this->resolverImagemSrc((string) ($modelo['imagem_cabecalho'] ?? ''), $config);
        $srcRod = $this->resolverImagemSrc((string) ($modelo['imagem_rodape'] ?? ''), $config);
        // Dimensões em mm — Dompdf respeita melhor que max-height/object-fit
        [$cabH, $rodH] = $orientacao === 'paisagem' ? ['32mm', '22mm'] : ['42mm', '28mm'];

        // Declarações: capa + rodapé em faixa. Contratos: só logo centralizada (sem capa/rodapé).
        if ($estilo === 'declaracao') {
            if ($srcCab !== '') {
                $srcEsc = htmlspecialchars($srcCab, ENT_QUOTES, 'UTF-8');
                $imgCabHtml = '<div class="banner-cab"><img src="' . $srcEsc
                    . '" alt="Cabeçalho" width="100%" style="width:100%;max-height:' . $cabH . ';height:auto;"></div>';
            }
            if ($srcRod !== '') {
                $srcEsc = htmlspecialchars($srcRod, ENT_QUOTES, 'UTF-8');
                $imgRodHtml = '<div class="banner-rod"><img src="' . $srcEsc
                    . '" alt="Rodapé" width="100%" style="width:100%;max-height:' . $rodH . ';height:auto;"></div>';
            }
        } else {
            $logoHtml = trim((string) ($vars['logo_html'] ?? ''));
            $modeloJaTemLogo = str_contains((string) ($modelo['cabecalho_html'] ?? ''), '{{logo_html}}')
                || str_contains((string) ($modelo['corpo_html'] ?? ''), '{{logo_html}}');
            if ($modeloJaTemLogo) {
                $logoHtml = ''; // já entra via placeholder no HTML do modelo
            }
            if ($logoHtml === '' && !$modeloJaTemLogo && $srcCab !== '') {
                // Reaproveita a imagem de capa como logo (sem faixa full-width)
                $srcEsc = htmlspecialchars($srcCab, ENT_QUOTES, 'UTF-8');
                $logoHtml = '<img src="' . $srcEsc . '" alt="Logo" style="max-height:70px;max-width:220px;width:auto;height:auto;">';
            }
            if ($logoHtml !== '') {
                $imgCabHtml = '<div class="doc-logo" style="text-align:center;margin:0 0 16px;">' . $logoHtml . '</div>';
            }
            $css .= "\n  .doc-logo img { display:block; margin:0 auto; max-height:70px; max-width:220px; width:auto; height:auto; }\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
{$css}
</style>
</head>
<body>
{$imgCabHtml}
{$cab}
{$corpo}
{$rodape}
{$imgRodHtml}
</body>
</html>
HTML;
    }

    /** @return 'portrait'|'landscape' */
    public function orientacaoDompdf(array $modelo): string
    {
        return (($modelo['orientacao'] ?? 'retrato') === 'paisagem') ? 'landscape' : 'portrait';
    }

    /**
     * Resolve caminho/chave de imagem para data-URI (Dompdf) ou URL.
     *
     * @param array<string,mixed>|null $config
     */
    public function resolverImagemSrc(string $ref, ?array $config = null): string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return '';
        }

        // Já é data URI ou http(s)
        if (str_starts_with($ref, 'data:') || str_starts_with($ref, 'http://') || str_starts_with($ref, 'https://')) {
            return $ref;
        }

        // Rejeitar traversal / absolutos no valor persistido
        if (str_contains($ref, '..') || str_starts_with($ref, '/') || str_contains($ref, "\0")) {
            return '';
        }

        $bin = null;
        $mime = 'image/png';
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $allowedRoot = realpath($base . '/storage/modelos_documentos');

        $localCandidates = [];
        if (str_starts_with($ref, 'storage/modelos_documentos/')) {
            $localCandidates[] = $base . '/' . $ref;
        } elseif (!str_contains($ref, '/') || preg_match('#^[a-z0-9_-]+/modelos_documentos/#i', $ref)) {
            // Chave S3 típica: {slug}/modelos_documentos/arquivo.ext — local espelhado
            $localCandidates[] = $base . '/storage/modelos_documentos/' . $ref;
            if (preg_match('#^[a-z0-9_-]+/modelos_documentos/(.+)$#i', $ref, $m)) {
                $localCandidates[] = $base . '/storage/modelos_documentos/' . ($m[1] ?? '');
            }
        }

        foreach ($localCandidates as $path) {
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }
            $real = realpath($path);
            if ($real === false) {
                continue;
            }
            if ($allowedRoot !== false && !str_starts_with($real, $allowedRoot . DIRECTORY_SEPARATOR) && $real !== $allowedRoot) {
                continue;
            }
            $bin = @file_get_contents($real);
            $ext = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));
            $map = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];
            $mime = $map[$ext] ?? 'image/png';
            break;
        }

        // S3 key
        if (($bin === null || $bin === false || $bin === '') && is_array($config)) {
            try {
                require_once $base . '/app/Services/MediaStorageService.php';
                $media = new \MediaStorageService($config);
                $contents = $media->getContents('arquivos', $ref);
                if (is_string($contents) && $contents !== '') {
                    $bin = $contents;
                    $ext = strtolower((string) pathinfo($ref, PATHINFO_EXTENSION));
                    $map = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];
                    $mime = $map[$ext] ?? 'image/png';
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (!is_string($bin) || $bin === '') {
            return '';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    private function temColuna(string $coluna): bool
    {
        return $this->temColunaTabela('secretaria_modelos_documentos', $coluna);
    }

    private function temColunaLayout(string $coluna): bool
    {
        return $this->temColunaTabela('secretaria_declaracoes_layouts', $coluna);
    }

    private function temColunaTabela(string $tabela, string $coluna): bool
    {
        static $cache = [];
        $dbName = '';
        try {
            $dbName = (string) ($this->db->fetch('SELECT DATABASE() AS db')['db'] ?? '');
        } catch (\Throwable $e) {
            $dbName = '';
        }
        $cacheKey = $dbName . "\0" . $tabela . "\0" . $coluna;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1",
                [$tabela, $coluna]
            );
            $cache[$cacheKey] = !empty($row['ok']);
        } catch (\Throwable $e) {
            $cache[$cacheKey] = false;
        }
        return $cache[$cacheKey];
    }

    /**
     * Variáveis escapadas a partir do \$viewData de AdminDeclarationController.
     *
     * @param array<string,mixed> $viewData
     * @return array<string,string>
     */
    public function varsFromDeclaracao(array $viewData): array
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $fmt = static function ($d) use ($esc): string {
            $d = trim((string) $d);
            if ($d === '' || $d === '0000-00-00') {
                return '—';
            }
            $dt = \DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
            return $dt ? $esc($dt->format('d/m/Y')) : '—';
        };

        $dados = is_array($viewData['dados'] ?? null) ? $viewData['dados'] : [];
        $aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
        $unidade = is_array($dados['unidade'] ?? null) ? $dados['unidade'] : [];
        $mat = is_array($dados['matricula_encerrada'] ?? null)
            ? $dados['matricula_encerrada']
            : (is_array($dados['matricula'] ?? null) ? $dados['matricula'] : []);
        $freq = is_array($dados['frequencia'] ?? null) ? $dados['frequencia'] : [];
        $periodo = is_array($dados['periodo'] ?? null) ? $dados['periodo'] : [];
        $aut = is_array($dados['aut'] ?? null) ? $dados['aut'] : [];
        $responsaveis = is_array($dados['responsaveis'] ?? null) ? $dados['responsaveis'] : [];

        $nomeUnidade = trim((string) ($unidade['razao_social'] ?? ''))
            ?: trim((string) ($unidade['nome'] ?? ''))
            ?: 'Instituição de Ensino';
        $linhaEndereco = trim(implode(', ', array_filter([
            trim((string) ($unidade['endereco'] ?? ''))
                . (trim((string) ($unidade['numero'] ?? '')) !== '' ? ', ' . $unidade['numero'] : ''),
            trim((string) ($unidade['bairro'] ?? '')),
            trim(trim((string) ($unidade['cidade'] ?? ''))
                . (trim((string) ($unidade['uf'] ?? '')) !== '' ? ' / ' . $unidade['uf'] : '')),
            trim((string) ($unidade['cep'] ?? '')) !== '' ? 'CEP ' . $unidade['cep'] : '',
        ])));
        $linhaDocs = trim(implode(' • ', array_filter([
            trim((string) ($unidade['cnpj'] ?? '')) !== '' ? 'CNPJ: ' . $unidade['cnpj'] : '',
            trim((string) ($unidade['inep'] ?? '')) !== '' ? 'INEP: ' . $unidade['inep'] : '',
            trim((string) ($unidade['telefone'] ?? '')) !== '' ? 'Tel.: ' . $unidade['telefone'] : '',
        ])));

        $alunoNome = trim((string) ($aluno['nome'] ?? ''));
        $alunoCpf = trim((string) ($aluno['cpf'] ?? ''));
        $alunoRg = trim((string) ($aluno['rg'] ?? $aluno['documento_rg'] ?? ''));
        $infoPertinente = trim((string) ($viewData['info_pertinente'] ?? $dados['info_pertinente'] ?? ''));
        $infoPertinenteHtml = '';
        if ($infoPertinente !== '') {
            $linhas = preg_split('/\r\n|\r|\n/', $infoPertinente) ?: [];
            $bullets = [];
            foreach ($linhas as $linha) {
                $linha = trim($linha);
                if ($linha === '') {
                    continue;
                }
                $linha = ltrim($linha, "•\t-–—* ");
                if ($linha === '') {
                    continue;
                }
                $bullets[] = '<div>• ' . $esc($linha) . '</div>';
            }
            $infoPertinenteHtml = $bullets !== []
                ? implode("\n", $bullets)
                : '<div>' . $esc($infoPertinente) . '</div>';
        } else {
            $infoPertinenteHtml = '<div>• _______________________________</div>';
        }
        $alunoNasc = $fmt($aluno['data_nasc'] ?? '');
        $turma = trim((string) ($mat['turma_nome'] ?? $aluno['turma_nome'] ?? ''));
        $serie = trim((string) ($mat['turma_serie'] ?? $aluno['turma_serie'] ?? $aluno['serie'] ?? ''));
        $anoLetivo = trim((string) ($mat['ano_letivo'] ?? date('Y')));
        $status = (string) ($mat['status'] ?? 'ativa');
        $situacao = $status === 'ativa' ? 'Matrícula ativa'
            : ($status === 'concluido' ? 'Concluído'
            : ($status === 'transferido' ? 'Transferido' : ucfirst($status ?: '—')));

        $logoData = trim((string) ($viewData['logo_data'] ?? ''));
        $logoHtml = $logoData !== ''
            ? '<img src="' . $esc($logoData) . '" alt="Logo">'
            : '';

        $tipo = (string) ($viewData['tipo'] ?? '');
        $docRotulo = 'Documento';
        if (!class_exists('\\DeclarationService', false) && defined('BASE_PATH')) {
            $declPath = BASE_PATH . '/app/Services/DeclarationService.php';
            if (is_file($declPath)) {
                require_once $declPath;
            }
        }
        if (class_exists('\\DeclarationService', false) && in_array($tipo, \DeclarationService::TIPOS, true)) {
            $docRotulo = 'Declaração';
        }

        $resp0 = $responsaveis[0] ?? [];
        $respNome = trim((string) ($resp0['nome'] ?? ''));

        $freqHtml = '';
        if (!empty($freq['sem_registros'])) {
            $freqHtml = '<p>Não há registros de aulas finalizadas no período informado para o cálculo de frequência.</p>';
        } elseif ($freq !== []) {
            $perc = $freq['percentual'] ?? null;
            $percTxt = $perc !== null
                ? $esc(number_format((float) $perc, 1, ',', '.')) . '%'
                : '—';
            $freqHtml = '<table class="dados">'
                . '<tr><td class="label">Total de aulas registradas</td><td>' . (int) ($freq['total_aulas'] ?? 0) . '</td></tr>'
                . '<tr><td class="label">Presenças</td><td>' . (int) ($freq['presencas'] ?? 0) . '</td></tr>'
                . '<tr><td class="label">Faltas</td><td>' . (int) ($freq['faltas'] ?? 0) . '</td></tr>'
                . '<tr><td class="label">Faltas justificadas</td><td>' . (int) ($freq['faltas_justificadas'] ?? 0) . '</td></tr>'
                . '<tr><td class="label">Percentual de frequência</td><td>' . $percTxt . '</td></tr>'
                . '</table>';
        }

        $respHtml = '';
        if ($responsaveis !== []) {
            $respHtml = '<table class="dados"><tr><td class="label">Nome</td><td class="label">CPF</td><td class="label">Vínculo</td></tr>';
            foreach ($responsaveis as $r) {
                $respHtml .= '<tr><td>' . $esc($r['nome'] ?? '') . '</td><td>'
                    . $esc($r['cpf'] ?? '—') . '</td><td>'
                    . $esc($r['tipo_vinculo'] ?? '') . '</td></tr>';
            }
            $respHtml .= '</table>';
        } else {
            $respHtml = '<p>Nenhum responsável cadastrado.</p>';
        }

        $periodoTexto = trim((string) ($dados['periodo_texto'] ?? ''));
        $dataComp = $fmt($dados['data_comparecimento'] ?? '');
        $dataEvento = $fmt($dados['data_evento'] ?? '');
        if ($dataEvento === '—') {
            $dataEvento = '';
        }

        return [
            'escola_nome' => $esc($nomeUnidade),
            'escola_cnpj' => trim((string) ($unidade['cnpj'] ?? '')) !== ''
                ? $esc('CNPJ: ' . $unidade['cnpj']) : '',
            'escola_endereco' => $esc($linhaEndereco),
            'escola_docs' => $esc($linhaDocs),
            'logo_html' => $logoHtml,
            'aluno_nome' => $esc($alunoNome),
            'aluno_cpf' => $esc($alunoCpf !== '' ? $alunoCpf : '—'),
            'aluno_rg' => $esc($alunoRg !== '' ? $alunoRg : '—'),
            'aluno_cpf_frase' => $alunoCpf !== '' ? ', inscrito(a) no CPF sob o nº ' . $esc($alunoCpf) : '',
            'aluno_data_nasc' => $alunoNasc,
            'aluno_nasc_frase' => $alunoNasc !== '—' ? ', nascido(a) em ' . $alunoNasc : '',
            'aluno_email' => $esc($aluno['email'] ?? '—'),
            'aluno_telefone' => $esc($aluno['telefone'] ?? '—'),
            'aluno_codigo' => $esc(trim((string) ($aluno['codigo_aluno'] ?? $aluno['ra'] ?? '')) ?: '—'),
            'resp_nome' => $esc($respNome !== '' ? $respNome : '________________________'),
            'resp_cpf' => $esc($resp0['cpf'] ?? '—'),
            'resp_email' => $esc($resp0['email'] ?? '—'),
            'resp_telefone' => $esc($resp0['telefone'] ?? '—'),
            'resp_parentesco' => $esc($resp0['tipo_vinculo'] ?? '—'),
            'resp_endereco' => '—',
            'turma_nome' => $esc($turma !== '' ? $turma : '—'),
            'turma_frase' => $turma !== ''
                ? ' na turma <span class="destaque">' . $esc($turma) . '</span>'
                    . ($serie !== '' ? ' (' . $esc($serie) . ')' : '')
                : '',
            'serie' => $esc($serie !== '' ? $serie : '—'),
            'ano_letivo' => $esc($anoLetivo),
            'situacao_matricula' => $esc($situacao),
            'data_entrada' => $fmt($mat['data_entrada'] ?? ''),
            'data_saida' => $fmt($mat['data_saida'] ?? ''),
            'tipo_matricula' => '',
            'data_hoje' => $esc((string) ($viewData['gerado_em'] ?? date('d/m/Y'))),
            'observacoes' => $esc($infoPertinente),
            'info_pertinente' => $esc($infoPertinente),
            'info_pertinente_html' => $infoPertinenteHtml,
            'titulo' => $esc((string) ($viewData['titulo'] ?? 'Documento')),
            'doc_rotulo' => $esc($docRotulo),
            'numero' => (string) (int) ($viewData['numero'] ?? 0),
            'ano' => (string) (int) ($viewData['ano'] ?? date('Y')),
            'cidade_data' => $esc((string) ($viewData['cidade_data'] ?? '')),
            'diretor_nome' => $esc(trim((string) ($unidade['diretor_nome'] ?? '')) ?: "\u{00a0}"),
            'secretario_nome' => $esc(trim((string) ($unidade['secretario_nome'] ?? '')) ?: "\u{00a0}"),
            'periodo_inicio' => $fmt($periodo['inicio'] ?? ''),
            'periodo_fim' => $fmt($periodo['fim'] ?? ''),
            'frequencia_html' => $freqHtml,
            'data_comparecimento' => $dataComp,
            'periodo_texto' => $esc($periodoTexto !== '' ? $periodoTexto : '—'),
            'periodo_texto_frase' => $periodoTexto !== ''
                ? ', no período <span class="destaque">' . $esc($periodoTexto) . '</span>'
                : '',
            'data_evento' => $dataEvento !== '' ? $dataEvento : '____/____/______',
            'aut_horario' => $esc(trim((string) ($aut['horario'] ?? '')) ?: '____:____'),
            'aut_motivo' => $esc(trim((string) ($aut['motivo'] ?? '')) ?: '____________________________________________________'),
            'aut_nome_autorizado' => $esc($aut['nome_autorizado'] ?? ''),
            'aut_documento' => $esc($aut['documento'] ?? ''),
            'aut_parentesco' => $esc($aut['parentesco'] ?? ''),
            'aut_local' => $esc($aut['local'] ?? ''),
            'aut_hora_saida' => $esc($aut['hora_saida'] ?? ''),
            'aut_hora_retorno' => $esc($aut['hora_retorno'] ?? ''),
            'aut_finalidade' => $esc($aut['finalidade'] ?? ''),
            'responsaveis_html' => $respHtml,
            'historico_html' => '',
        ];
    }

    /**
     * Dados fictícios para pré-visualizar o PDF do modelo no admin.
     *
     * @return array<string,string>
     */
    public static function varsExemplo(): array
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $out = [];
        foreach (self::PLACEHOLDERS as $key => $_label) {
            $out[$key] = '';
        }
        $amostra = [
            'escola_nome' => 'Colégio Almeida Garrett – COLAG',
            'escola_cnpj' => ' · CNPJ: 00.000.000/0001-00',
            'escola_cnpj_numero' => '00.000.000/0001-00',
            'escola_endereco' => 'Rua Marquês de Pombal, 415 — Ribeirão Preto/SP',
            'escola_docs' => 'CNPJ 00.000.000/0001-00 · Tel. (16) 3941-5257',
            'escola_origem' => 'Escola Municipal Exemplo',
            'aluno_nome' => 'Maria Eduarda Silva',
            'aluno_cpf' => '123.456.789-00',
            'aluno_rg' => '12.345.678-9',
            'aluno_cpf_frase' => ', CPF nº 123.456.789-00',
            'aluno_data_nasc' => '15/03/2015',
            'aluno_nasc_frase' => ', nascido(a) em 15/03/2015',
            'aluno_email' => 'maria.eduarda@email.com',
            'aluno_telefone' => '(16) 99999-0000',
            'aluno_endereco' => 'Rua das Flores, 100',
            'aluno_cidade' => 'Ribeirão Preto/SP',
            'aluno_codigo' => '202600123',
            'matricula_numero' => '2026-0456',
            'curso_nome' => 'Ensino Fundamental',
            'resp_nome' => 'Ana Paula Silva',
            'resp_cpf' => '987.654.321-00',
            'resp_rg' => '98.765.432-1',
            'resp_email' => 'ana.paula@email.com',
            'resp_telefone' => '(16) 98888-1111',
            'resp_celular' => '(16) 98888-1111',
            'resp_parentesco' => 'Mãe',
            'resp_endereco' => 'Rua das Flores, 100',
            'resp_bairro' => 'Centro',
            'resp_cep' => '14000-000',
            'resp_cidade' => 'Ribeirão Preto/SP',
            'resp_estado_civil' => 'Casada',
            'resp2_nome' => 'Carlos Silva',
            'resp2_cpf' => '111.222.333-44',
            'resp2_rg' => '11.222.333-4',
            'resp2_email' => 'carlos.silva@email.com',
            'resp2_telefone' => '(16) 97777-2222',
            'resp2_celular' => '(16) 97777-2222',
            'resp2_parentesco' => 'Pai',
            'resp2_endereco' => 'Rua das Flores, 100',
            'resp2_bairro' => 'Centro',
            'resp2_cep' => '14000-000',
            'resp2_cidade' => 'Ribeirão Preto/SP',
            'resp2_estado_civil' => 'Casado',
            'resp_fin_nome' => 'Ana Paula Silva',
            'resp_fin_cpf' => '987.654.321-00',
            'resp_fin_rg' => '98.765.432-1',
            'resp_fin_email' => 'ana.paula@email.com',
            'resp_fin_telefone' => '(16) 98888-1111',
            'resp_fin_celular' => '(16) 98888-1111',
            'resp_fin_endereco' => 'Rua das Flores, 100',
            'resp_fin_bairro' => 'Centro',
            'resp_fin_cep' => '14000-000',
            'resp_fin_cidade' => 'Ribeirão Preto/SP',
            'valor_anuidade' => 'R$ 13.000,00',
            'valor_parcela' => 'R$ 1.000,00',
            'valor_liquido_parcela' => 'R$ 950,00',
            'valor_primeira_parcela' => 'R$ 1.000,00',
            'desconto_primeira' => 'R$ 50,00',
            'desconto_primeira_obs' => '(pontualidade)',
            'valor_liquido_primeira' => 'R$ 950,00',
            'qtd_parcelas_primeira' => '1',
            'valor_mensalidades_liq' => 'R$ 11.400,00',
            'desconto_parcela' => 'R$ 50,00',
            'num_parcelas' => '12',
            'data_rematricula' => date('d/m/Y'),
            'desc1_nome' => 'Pontualidade',
            'desc1_valor' => 'R$ 50,00',
            'desc2_nome' => 'Irmãos',
            'desc2_valor' => 'R$ 30,00',
            'desc3_nome' => '',
            'desc3_valor' => '',
            'desc4_nome' => '',
            'desc4_valor' => '',
            'turma_nome' => '5º Ano A',
            'turma_frase' => 'na turma 5º Ano A',
            'serie' => '5º Ano',
            'ano_letivo' => date('Y'),
            'situacao_matricula' => 'Ativa',
            'data_entrada' => '01/02/' . date('Y'),
            'data_saida' => '',
            'tipo_matricula' => 'Rematrícula',
            'data_hoje' => date('d/m/Y'),
            'observacoes' => '',
            'info_pertinente' => 'Anos/séries: 2024–2025 — 4º e 5º anos',
            'info_pertinente_html' => '<ul><li>2024 — 4º ano</li><li>2025 — 5º ano</li></ul>',
            'titulo' => 'Pré-visualização do modelo',
            'doc_rotulo' => 'Documento',
            'numero' => '001',
            'ano' => date('Y'),
            'cidade_data' => 'Ribeirão Preto, ' . date('d') . ' de julho de ' . date('Y'),
            'diretor_nome' => 'Diretor(a) Exemplo',
            'secretario_nome' => 'Secretário(a) Exemplo',
            'periodo_inicio' => '01/02/' . date('Y'),
            'periodo_fim' => '30/06/' . date('Y'),
            'frequencia_html' => '<p>Frequência exemplo: 95%</p>',
            'data_comparecimento' => date('d/m/Y'),
            'periodo_texto' => 'manhã',
            'periodo_texto_frase' => 'no período da manhã',
            'data_evento' => date('d/m/Y'),
            'aut_horario' => '11h30',
            'aut_motivo' => 'Consulta médica',
            'aut_nome_autorizado' => 'João da Silva',
            'aut_documento' => 'RG 12.345.678-9',
            'aut_parentesco' => 'Tio',
            'aut_local' => 'Unidade I',
            'aut_hora_saida' => '11h30',
            'aut_hora_retorno' => '14h00',
            'aut_finalidade' => 'Consulta',
            'responsaveis_html' => '<p>Ana Paula Silva (mãe) · Carlos Silva (pai)</p>',
            'historico_html' => '',
            'razao_social' => 'Centro Educacional e Cultural Almeida Santos LTDA.',
            'cnpj_layout' => '00.000.000/0001-00',
            'rodape_unidades' => 'Unidade I — Rua Marquês de Pombal, 415',
            'assinante_nome' => 'Diretor(a) Exemplo',
            'assinante_cargo' => 'Direção',
            'logo_html' => '',
        ];
        foreach ($amostra as $k => $v) {
            $out[$k] = $esc($v);
        }
        return $out;
    }

    /**
     * @param array<string,string> $vars
     */
    public function aplicarPlaceholders(string $html, array $vars): string
    {
        if ($html === '') {
            return '';
        }

        // 0) Blocos condicionais {{#se:campo}}...{{/se:campo}} e atalho {{#se_resp2}}...{{/se_resp2}}
        $html = $this->aplicarBlocosCondicionais($html, $vars);

        // 1) Canônicos {{campo}}
        $html = (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static function (array $m) use ($vars): string {
                $key = strtolower($m[1]);
                if (!array_key_exists($key, $vars)) {
                    return '';
                }
                return (string) $vars[$key];
            },
            $html
        );

        // 2) Aliases legados @campo (Word / sistema antigo)
        $aliases = self::PLACEHOLDER_ALIASES;
        $html = (string) preg_replace_callback(
            '/@([a-zA-Z0-9_][a-zA-Z0-9_]*)/',
            static function (array $m) use ($vars, $aliases): string {
                $raw = strtolower($m[1]);
                $canonical = $aliases[$raw] ?? $raw;
                if (!array_key_exists($canonical, $vars)) {
                    return '';
                }
                return (string) $vars[$canonical];
            },
            $html
        );

        return $html;
    }

    /**
     * Remove ou mantém trechos do HTML conforme o valor de um placeholder.
     * Sintaxe: {{#se:resp2_nome}}...{{/se:resp2_nome}}
     * Atalhos: {{#se_resp2}}...{{/se_resp2}} (= resp2_nome)
     *          {{#se_resp_fin}}...{{/se_resp_fin}} (= resp_fin_nome)
     *
     * @param array<string,string> $vars
     */
    public function aplicarBlocosCondicionais(string $html, array $vars): string
    {
        $atalhos = [
            'se_resp2' => 'resp2_nome',
            'se_resp_fin' => 'resp_fin_nome',
            'se_resp3' => 'resp_fin_nome',
        ];

        // Atalhos {{#se_resp2}}...{{/se_resp2}}
        foreach ($atalhos as $tag => $campo) {
            $html = (string) preg_replace_callback(
                '/\{\{\s*#' . preg_quote($tag, '/') . '\s*\}\}(.*?)\{\{\s*\/' . preg_quote($tag, '/') . '\s*\}\}/is',
                function (array $m) use ($vars, $campo): string {
                    return $this->valorPreenchido($vars[$campo] ?? '') ? $m[1] : '';
                },
                $html
            );
        }

        // Genérico {{#se:campo}}...{{/se:campo}}
        $html = (string) preg_replace_callback(
            '/\{\{\s*#se:([a-z0-9_]+)\s*\}\}(.*?)\{\{\s*\/se:\1\s*\}\}/is',
            function (array $m) use ($vars): string {
                $campo = strtolower($m[1]);
                return $this->valorPreenchido($vars[$campo] ?? '') ? $m[2] : '';
            },
            $html
        );

        // Remove <p> vazios deixados por blocos condicionais
        $html = (string) preg_replace('/<p[^>]*>\s*(?:<br\s*\/?\s*>|\s)*<\/p>/i', '', $html);

        return $html;
    }

    private function valorPreenchido(string $valor): bool
    {
        $v = trim(html_entity_decode(strip_tags($valor), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($v === '') {
            return false;
        }
        $vazios = ['—', '-', '–', 'n/a', 'na', 'null', 'undefined'];
        return !in_array(mb_strtolower($v), $vazios, true);
    }

    public function normalizarCodigo(string $codigo): string
    {
        $codigo = strtolower(trim($codigo));
        $codigo = preg_replace('/[^a-z0-9_]+/', '_', $codigo) ?? '';
        return trim($codigo, '_');
    }

    private function cssSimples(): string
    {
        return <<<CSS
  body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; line-height: 1.45; margin: 24px 28px; }
  h1,h2,h3 { color: #111; }
  p { margin: 0 0 8px; }
  table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  th, td { border: 1px solid #ccc; padding: 4px 6px; font-size: 9pt; }
  th { background: #f3f4f6; }
CSS;
    }

    /**
     * Faixas de cabeçalho/rodapé dimensionadas para A4 (retrato ou paisagem).
     * Dompdf: preferir mm + width 100% / height auto (object-fit é pouco confiável).
     */
    private function cssBanners(string $orientacao): string
    {
        // A4 retrato 210×297 · paisagem 297×210 — faixas ~12–15% da altura útil
        if ($orientacao === 'paisagem') {
            $cabMax = '32mm';
            $rodMax = '22mm';
        } else {
            $cabMax = '42mm';
            $rodMax = '28mm';
        }

        return <<<CSS
  .banner-cab { width: 100%; margin: 0 0 8px 0; padding: 0; text-align: center; overflow: hidden; max-height: {$cabMax}; }
  .banner-cab img { display: block; width: 100%; max-width: 100%; height: auto; max-height: {$cabMax}; margin: 0 auto; }
  .banner-rod { width: 100%; margin: 12px 0 0 0; padding: 0; text-align: center; overflow: hidden; max-height: {$rodMax}; }
  .banner-rod img { display: block; width: 100%; max-width: 100%; height: auto; max-height: {$rodMax}; margin: 0 auto; }
CSS;
    }

    private function cssDeclaracao(): string
    {
        return <<<CSS
  @page { margin: 22mm 20mm 22mm 20mm; }
  * { box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 11pt; margin: 0; line-height: 1.6; }
  .header { display: table; width: 100%; border-bottom: 2px solid #064e3b; padding-bottom: 10px; margin-bottom: 6px; }
  .header .logo-cell { display: table-cell; width: 90px; vertical-align: middle; }
  .header .title-cell { display: table-cell; vertical-align: middle; padding-left: 12px; }
  .header img { max-height: 64px; max-width: 84px; }
  .header .escola { font-size: 13pt; font-weight: bold; color: #064e3b; margin: 0 0 2px 0; }
  .header .meta { font-size: 8.5pt; color: #4b5563; margin: 1px 0; }
  .doc-num { text-align: right; font-size: 8.5pt; color: #6b7280; margin: 6px 0 18px 0; }
  h1.doc-title { text-align: center; font-size: 15pt; color: #111827; letter-spacing: 1px; text-transform: uppercase; margin: 10px 0 26px 0; }
  .corpo { text-align: justify; font-size: 11.5pt; margin: 0 4px; }
  .corpo p { margin: 0 0 14px 0; }
  .destaque { font-weight: bold; color: #111827; }
  table.dados { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 10.5pt; }
  table.dados td { border: 1px solid #d1d5db; padding: 6px 9px; }
  table.dados td.label { background: #f3f4f6; font-weight: bold; width: 38%; }
  .fecho { margin-top: 36px; text-align: right; font-size: 11pt; }
  .assinaturas { margin-top: 60px; width: 100%; display: table; }
  .assinaturas .sig { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 16px; }
  .assinaturas .line { border-top: 1px solid #374151; margin: 0 auto 4px auto; width: 80%; padding-top: 4px; }
  .assinaturas .nome { font-size: 10pt; font-weight: bold; }
  .assinaturas .cargo { font-size: 9pt; color: #4b5563; }
  .footer { margin-top: 40px; text-align: center; font-size: 7.5pt; color: #9ca3af; }
CSS;
    }
}
