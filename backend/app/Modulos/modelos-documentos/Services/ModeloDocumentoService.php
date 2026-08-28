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
        'doc_rotulo' => 'Rótulo do documento (Declaração, Autorização…)',
        'numero' => 'Número da emissão',
        'ano' => 'Ano do número da emissão',
        'pagina' => 'Número da página (PDF)',
        'total_paginas' => 'Total de páginas (PDF)',
        'periodo_nome' => 'Nome do período (bimestre/trimestre)',
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
        'quadro_notas_html' => 'Quadro de notas/componentes (HTML)',
        'identidade_html' => 'Tabela de identidade do aluno (HTML)',
        'trajetoria_html' => 'Trajetória escolar por ano (HTML)',
        'documentos_html' => 'Checklist de documentos (HTML)',
        'sed_html' => 'Conferência SED / Educacenso (HTML)',
        'situacao_final' => 'Situação acadêmica final',
        'frequencia_percentual' => 'Frequência (%)',
        'tabela_html' => 'Tabela coletiva (ata/relatório)',
        'titulo_relatorio' => 'Título do relatório acadêmico',
        'periodo_label' => 'Rótulo do período (ano/bimestre…)',
        'total_alunos' => 'Total de alunos (ata/relatório)',
        'total_homologados' => 'Total homologados',
        'total_pendencias' => 'Total de pendências críticas',
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
        'declaracao_aut_retirada',
        'declaracao_aut_imagem',
        'declaracao_aut_passeio',
        'declaracao_ficha_matricula',
        'declaracao_historico',
        'declaracao_bolsista_integral',
        'resultado_ficha_individual',
        'resultado_ata_finais',
        'resultado_boletim_padrao',
        'resultado_relatorio_padrao',
        'resultado_historico',
        'vida_escolar_boletim',
        'vida_escolar_dossie',
        'vida_escolar_pacote',
        'vida_escolar_sed',
        'vida_escolar_historico',
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

    /** @return array<string,string> chave => rótulo */
    public const CATEGORIAS = [
        'declaracao' => 'Declarações',
        'autorizacao' => 'Autorizações',
        'contrato' => 'Contratos',
        'oficial' => 'Documentos oficiais',
        'outro' => 'Outros modelos',
    ];

    /**
     * Blocos prontos para montar o documento no editor (logo, título, tabela…).
     *
     * @return list<array{id:string,label:string,icone:string,alvo:string,html:string,ajuda:string}>
     */
    public static function blocosEditor(): array
    {
        return [
            [
                'id' => 'logo',
                'label' => 'Logo',
                'icone' => 'fa-image',
                'alvo' => 'corpo_html',
                'ajuda' => 'Insere a logo da unidade/escola na coluna ou no texto.',
                'html' => '<p style="text-align:center;margin:0;">{{logo_html}}</p>',
            ],
            [
                'id' => 'cabecalho_escola',
                'label' => 'Logo + escola',
                'icone' => 'fa-building-columns',
                'alvo' => 'corpo_html',
                'ajuda' => 'Duas colunas: logo à esquerda e nome da escola centralizado na vertical.',
                'html' => self::htmlLinhaColunas(
                    [28, 72],
                    'middle',
                    [
                        '<p style="text-align:center;margin:0;">{{logo_html}}</p>',
                        '<p class="escola" style="margin:0 0 2px 0;">{{escola_nome}}</p>'
                            . '<p class="meta" style="margin:0;">{{escola_endereco}}</p>'
                            . '<p class="meta" style="margin:0;">{{escola_docs}}</p>',
                    ]
                ),
            ],
            [
                'id' => 'titulo',
                'label' => 'Título',
                'icone' => 'fa-heading',
                'alvo' => 'corpo_html',
                'ajuda' => 'Número + título do documento.',
                'html' => '<div class="doc-num">{{doc_rotulo}} nº {{numero}}/{{ano}}</div>'
                    . '<h1 class="doc-title">{{titulo}}</h1>',
            ],
            [
                'id' => 'texto',
                'label' => 'Texto livre',
                'icone' => 'fa-align-left',
                'alvo' => 'corpo_html',
                'ajuda' => 'Parágrafo com o nome do aluno já marcado.',
                'html' => '<p>Declaramos, para os devidos fins, que '
                    . '<span class="destaque">{{aluno_nome}}</span>{{aluno_cpf_frase}}.</p>',
            ],
            [
                'id' => 'tabela_aluno',
                'label' => 'Tabela do aluno',
                'icone' => 'fa-table',
                'alvo' => 'corpo_html',
                'ajuda' => 'Nome, CPF, turma, série, ano.',
                'html' => '<table class="dados">'
                    . '<tr><td class="label">Aluno(a)</td><td>{{aluno_nome}}</td></tr>'
                    . '<tr><td class="label">CPF</td><td>{{aluno_cpf}}</td></tr>'
                    . '<tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td></tr>'
                    . '<tr><td class="label">Turma</td><td>{{turma_nome}}</td></tr>'
                    . '<tr><td class="label">Série</td><td>{{serie}}</td></tr>'
                    . '<tr><td class="label">Ano letivo</td><td>{{ano_letivo}}</td></tr>'
                    . '</table>',
            ],
            [
                'id' => 'tabela_responsavel',
                'label' => 'Tabela do responsável',
                'icone' => 'fa-user-group',
                'alvo' => 'corpo_html',
                'ajuda' => 'Dados do responsável legal.',
                'html' => '<table class="dados">'
                    . '<tr><td class="label">Responsável</td><td>{{resp_nome}}</td></tr>'
                    . '<tr><td class="label">CPF</td><td>{{resp_cpf}}</td></tr>'
                    . '<tr><td class="label">Parentesco</td><td>{{resp_parentesco}}</td></tr>'
                    . '<tr><td class="label">Telefone</td><td>{{resp_telefone}}</td></tr>'
                    . '<tr><td class="label">E-mail</td><td>{{resp_email}}</td></tr>'
                    . '</table>',
            ],
            [
                'id' => 'frequencia',
                'label' => 'Tabela de frequência',
                'icone' => 'fa-calendar-check',
                'alvo' => 'corpo_html',
                'ajuda' => 'Preenchida na emissão da declaração de frequência.',
                'html' => '{{frequencia_html}}',
            ],
            [
                'id' => 'quadro_notas',
                'label' => 'Quadro de notas',
                'icone' => 'fa-list-ol',
                'alvo' => 'corpo_html',
                'ajuda' => 'Componentes e notas (ficha/boletim).',
                'html' => '{{quadro_notas_html}}',
            ],
            [
                'id' => 'identidade',
                'label' => 'Identidade do aluno',
                'icone' => 'fa-id-card',
                'alvo' => 'corpo_html',
                'ajuda' => 'Tabela civil/matrícula da Vida Escolar.',
                'html' => '{{identidade_html}}',
            ],
            [
                'id' => 'trajetoria',
                'label' => 'Trajetória',
                'icone' => 'fa-timeline',
                'alvo' => 'corpo_html',
                'ajuda' => 'Anos de escolarização (esta escola e origem).',
                'html' => '{{trajetoria_html}}',
            ],
            [
                'id' => 'tabela_coletiva',
                'label' => 'Tabela coletiva',
                'icone' => 'fa-table-cells',
                'alvo' => 'corpo_html',
                'ajuda' => 'Ata / relatório da turma.',
                'html' => '{{tabela_html}}',
            ],
            [
                'id' => 'responsaveis',
                'label' => 'Lista de responsáveis',
                'icone' => 'fa-users',
                'alvo' => 'corpo_html',
                'ajuda' => 'Tabela gerada na ficha de matrícula.',
                'html' => '{{responsaveis_html}}',
            ],
            [
                'id' => 'data_cidade',
                'label' => 'Cidade e data',
                'icone' => 'fa-location-dot',
                'alvo' => 'rodape_html',
                'ajuda' => 'Ex.: Ribeirão Preto, 22 de agosto de 2026.',
                'html' => '<div class="fecho">{{cidade_data}}.</div>',
            ],
            [
                'id' => 'assinaturas',
                'label' => 'Assinaturas (escola)',
                'icone' => 'fa-signature',
                'alvo' => 'rodape_html',
                'ajuda' => 'Secretaria + Direção.',
                'html' => self::htmlLinhaColunas(
                    [50, 50],
                    'bottom',
                    [
                        '<p style="text-align:center;margin:0;">________________________</p>'
                            . '<p class="nome" style="text-align:center;margin:4px 0 0;font-weight:bold;">{{secretario_nome}}</p>'
                            . '<p class="cargo" style="text-align:center;margin:0;">Secretaria</p>',
                        '<p style="text-align:center;margin:0;">________________________</p>'
                            . '<p class="nome" style="text-align:center;margin:4px 0 0;font-weight:bold;">{{diretor_nome}}</p>'
                            . '<p class="cargo" style="text-align:center;margin:0;">Direção</p>',
                    ]
                ),
            ],
            [
                'id' => 'assinatura_resp',
                'label' => 'Assinatura do responsável',
                'icone' => 'fa-pen',
                'alvo' => 'rodape_html',
                'ajuda' => 'Responsável legal + Direção (autorizações).',
                'html' => self::htmlLinhaColunas(
                    [50, 50],
                    'bottom',
                    [
                        '<p style="text-align:center;margin:0;">________________________</p>'
                            . '<p class="nome" style="text-align:center;margin:4px 0 0;font-weight:bold;">{{resp_nome}}</p>'
                            . '<p class="cargo" style="text-align:center;margin:0;">Responsável legal</p>',
                        '<p style="text-align:center;margin:0;">________________________</p>'
                            . '<p class="nome" style="text-align:center;margin:4px 0 0;font-weight:bold;">{{diretor_nome}}</p>'
                            . '<p class="cargo" style="text-align:center;margin:0;">Direção</p>',
                    ]
                ),
            ],
        ];
    }

    /**
     * Linha de colunas estilo Elementor (tabela — CKEditor e Dompdf preservam).
     *
     * @param list<int> $larguras Soma deve ser 100
     * @param 'top'|'middle'|'bottom'|'topo'|'meio'|'base' $valign
     * @param list<string> $celulas HTML de cada coluna
     */
    public static function htmlLinhaColunas(array $larguras, string $valign = 'middle', array $celulas = []): string
    {
        $va = match ($valign) {
            'top', 'topo' => 'top',
            'bottom', 'base' => 'bottom',
            default => 'middle',
        };
        $tds = [];
        foreach (array_values($larguras) as $i => $w) {
            $w = max(1, min(100, (int) $w));
            $inner = trim((string) ($celulas[$i] ?? ''));
            if ($inner === '') {
                $inner = '<p>&nbsp;</p>';
            }
            $tds[] = '<td class="doc-col" style="width:' . $w . '%;vertical-align:' . $va
                . ';padding:8px 10px;border:none;border-color:transparent;">' . $inner . '</td>';
        }
        $cols = [];
        foreach (array_values($larguras) as $w) {
            $w = max(1, min(100, (int) $w));
            $cols[] = '<col style="width:' . $w . '%">';
        }
        return '<table class="doc-linha" width="100%" border="0" style="width:100%;border-collapse:collapse;table-layout:fixed;border:none;">'
            . '<colgroup>' . implode('', $cols) . '</colgroup><tbody><tr>'
            . implode('', $tds) . '</tr></tbody></table><p>&nbsp;</p>';
    }

    /**
     * Presets de estrutura (ícones de colunas, como no Elementor).
     *
     * @return list<array{id:string,label:string,cols:list<int>}>
     */
    public static function estruturasEditor(): array
    {
        return [
            ['id' => '100', 'label' => '1 coluna', 'cols' => [100]],
            ['id' => '50_50', 'label' => '50 / 50', 'cols' => [50, 50]],
            ['id' => '2col', 'label' => '2 colunas', 'cols' => [50, 50]],
            ['id' => '3col', 'label' => '3 colunas', 'cols' => [33, 34, 33]],
            ['id' => '4col', 'label' => '4 colunas', 'cols' => [25, 25, 25, 25]],
            ['id' => '25_75', 'label' => '25 / 75', 'cols' => [25, 75]],
            ['id' => '33_67', 'label' => '33 / 67', 'cols' => [33, 67]],
            ['id' => '40_60', 'label' => '40 / 60', 'cols' => [40, 60]],
            ['id' => '30_70', 'label' => '30 / 70', 'cols' => [30, 70]],
            ['id' => '60_40', 'label' => '60 / 40', 'cols' => [60, 40]],
            ['id' => '67_33', 'label' => '67 / 33', 'cols' => [67, 33]],
            ['id' => '75_25', 'label' => '75 / 25', 'cols' => [75, 25]],
        ];
    }

    /**
     * @return array{layout:list<array<string,mixed>>,conteudo:list<array<string,mixed>>,dados:list<array<string,mixed>>,tabelas:list<array<string,mixed>>,extras:list<array<string,mixed>>}
     */
    public static function catalogoElementosEditor(): array
    {
        $item = static fn (string $tipo, string $label, string $icone, string $ajuda = '') => [
            'tipo' => $tipo,
            'label' => $label,
            'icone' => $icone,
            'ajuda' => $ajuda,
        ];
        return [
            'layout' => self::estruturasEditor(),
            'conteudo' => [
                $item('titulo', 'Título', 'fa-heading'),
                $item('texto', 'Texto', 'fa-align-left'),
                $item('texto_rico', 'Texto rico', 'fa-pen-to-square'),
                $item('logo', 'Logo', 'fa-image', 'Logo da unidade ou da escola'),
                $item('imagem', 'Imagem', 'fa-photo-film'),
            ],
            'dados' => [
                $item('dados_escola', 'Dados da escola', 'fa-building-columns'),
                $item('dados_aluno', 'Dados do aluno', 'fa-user-graduate'),
                $item('dados_responsavel', 'Dados do responsável', 'fa-user'),
                $item('dados_turma', 'Dados da turma', 'fa-users'),
                $item('frequencia', 'Frequência', 'fa-chart-pie'),
                $item('observacoes', 'Observações', 'fa-comment'),
                $item('assinaturas', 'Assinaturas', 'fa-signature'),
            ],
            'tabelas' => [
                $item('tabela_aluno', 'Tabela do aluno', 'fa-table'),
                $item('tabela_notas', 'Tabela de notas', 'fa-table-list'),
                $item('tabela_frequencia', 'Tabela de frequência', 'fa-calendar-check'),
                $item('historico', 'Histórico escolar', 'fa-scroll'),
                $item('resultado_final', 'Resultado final', 'fa-flag-checkered'),
            ],
            'extras' => [
                $item('linha', 'Linha divisória', 'fa-minus'),
                $item('espacador', 'Espaçador', 'fa-arrows-up-down'),
                $item('pagina', 'Número da página', 'fa-file-lines'),
                $item('quebra_pagina', 'Quebra de página', 'fa-file-circle-plus'),
                $item('qrcode', 'QR Code', 'fa-qrcode'),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function estruturaVazia(string $formato = 'a4', string $orientacao = 'retrato', int $margem = 15): array
    {
        $pagina = [
            'size' => strtolower($formato) === 'a5' ? 'A5' : 'A4',
            'orientation' => $orientacao === 'paisagem' ? 'landscape' : 'portrait',
            'margin' => ['top' => $margem, 'right' => $margem, 'bottom' => $margem, 'left' => $margem],
        ];
        return [
            'version' => 1,
            'page' => $pagina,
            'header' => ['repeat' => true, 'sections' => []],
            'body' => ['sections' => [self::secaoPadrao([100], 'body')]],
            'footer' => ['repeat' => true, 'sections' => []],
        ];
    }

    /**
     * @param list<int> $larguras
     * @return array<string,mixed>
     */
    public static function secaoPadrao(array $larguras, string $role = 'body'): array
    {
        $cols = [];
        foreach ($larguras as $w) {
            $cols[] = [
                'id' => self::idEstrutura('c'),
                'width' => max(10, min(100, (int) $w)),
                'vAlign' => 'top',
                'elements' => [],
            ];
        }
        return [
            'id' => self::idEstrutura('s'),
            'type' => 'section',
            'role' => $role,
            'columns' => $cols,
        ];
    }

    /**
     * Layout visual pronto para o editor (boletim e modelos com “boletim” no código).
     *
     * @return array<string,mixed>|null
     */
    public static function estruturaSugeridaParaCodigo(string $codigo): ?array
    {
        $codigo = strtolower(trim($codigo));
        if ($codigo === '') {
            return null;
        }
        if ($codigo === 'resultado_boletim_padrao'
            || $codigo === 'vida_escolar_boletim'
            || str_contains($codigo, 'boletim')
        ) {
            return self::estruturaSugeridaBoletim();
        }
        return null;
    }

    /**
     * Boletim oficial: identificação, resultado, quadro de notas e duas assinaturas.
     *
     * @return array<string,mixed>
     */
    public static function estruturaSugeridaBoletim(): array
    {
        $est = self::estruturaVazia('a4', 'paisagem', 12);

        $cab = self::secaoPadrao([100], 'header');
        $cab['columns'][0]['vAlign'] = 'middle';
        $cab['columns'][0]['elements'][] = self::elementoEstrutura(
            'logo',
            ['width' => 96, 'align' => 'left', 'vAlign' => 'middle']
        );
        $est['header']['sections'] = [$cab];

        $titulo = self::secaoPadrao([100], 'body');
        $titulo['columns'][0]['elements'] = [
            self::elementoEstrutura(
                'titulo',
                ['text' => 'BOLETIM ESCOLAR', 'tag' => 'h1'],
                ['textAlign' => 'center', 'fontWeight' => 'bold']
            ),
            self::elementoEstrutura(
                'texto',
                ['text' => '{{aluno_nome}} · {{turma_nome}} · {{serie}} · {{ano_letivo}} · RA {{aluno_codigo}}'],
                ['textAlign' => 'center', 'fontSize' => 9, 'color' => '#374151']
            ),
            self::elementoEstrutura(
                'texto',
                ['text' => 'Situação: {{situacao_final}}  |  Frequência: {{frequencia_percentual}}'],
                ['textAlign' => 'center', 'fontSize' => 9]
            ),
        ];

        $notas = self::secaoPadrao([100], 'body');
        $notas['columns'][0]['elements'][] = self::elementoEstrutura(
            'tabela_notas',
            [],
            ['fontSize' => 8]
        );

        $obs = self::secaoPadrao([100], 'body');
        $obs['columns'][0]['elements'][] = self::elementoEstrutura('observacoes', [], [], true);

        $est['body']['sections'] = [$titulo, $notas, $obs];

        $data = self::secaoPadrao([100], 'footer');
        $data['columns'][0]['elements'][] = self::elementoEstrutura(
            'texto',
            ['text' => '{{cidade_data}}.'],
            ['textAlign' => 'right', 'fontSize' => 10]
        );

        $htmlSec = '<p style="text-align:center;margin:16px 0 0;">________________________</p>'
            . '<p style="text-align:center;margin:4px 0 0;font-weight:bold;">{{secretario_nome}}</p>'
            . '<p style="text-align:center;margin:0;font-size:9pt;color:#4b5563;">Secretaria</p>';
        $htmlDir = '<p style="text-align:center;margin:16px 0 0;">________________________</p>'
            . '<p style="text-align:center;margin:4px 0 0;font-weight:bold;">{{diretor_nome}}</p>'
            . '<p style="text-align:center;margin:0;font-size:9pt;color:#4b5563;">Direção</p>';

        $ass = self::secaoPadrao([50, 50], 'footer');
        $ass['columns'][0]['vAlign'] = 'bottom';
        $ass['columns'][1]['vAlign'] = 'bottom';
        $ass['columns'][0]['elements'][] = self::elementoEstrutura('texto_rico', ['html' => $htmlSec], ['textAlign' => 'center']);
        $ass['columns'][1]['elements'][] = self::elementoEstrutura('texto_rico', ['html' => $htmlDir], ['textAlign' => 'center']);
        $est['footer']['sections'] = [$data, $ass];

        return $est;
    }

    /**
     * @param array<string,mixed> $props
     * @param array<string,mixed> $style
     * @return array<string,mixed>
     */
    private static function elementoEstrutura(string $tipo, array $props = [], array $style = [], bool $ocultarVazio = false): array
    {
        $el = [
            'id' => self::idEstrutura('e'),
            'type' => $tipo,
            'props' => $props,
            'style' => $style,
        ];
        if ($ocultarVazio) {
            $el['hideIfEmpty'] = true;
        }
        return $el;
    }

    /**
     * Quadro de notas no formato da ficha (bimestres + final, nota e falta).
     * O tamanho do texto herda do bloco no editor (Propriedades → Tamanho).
     *
     * @param list<string> $periodos
     * @param list<array{componente?:string,celulas?:list<array{nota?:string,falta?:string}>}> $linhas
     */
    public static function htmlQuadroNotas(array $periodos, array $linhas): string
    {
        $periodos = array_values($periodos);
        if ($periodos === []) {
            $periodos = ['1º Bimestre', '2º Bimestre', '3º Bimestre', '4º Bimestre', 'FINAL'];
        }
        $nPer = count($periodos);
        $colspan = 1 + ($nPer * 2);

        $html = '<table class="quadro-notas dados"><thead><tr>'
            . '<th class="comp" rowspan="2">Componente</th>';
        foreach ($periodos as $rotulo) {
            $html .= '<th colspan="2">' . htmlspecialchars((string) $rotulo, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $html .= '</tr><tr>';
        for ($i = 0; $i < $nPer; $i++) {
            $html .= '<th>Nota</th><th>Falta</th>';
        }
        $html .= '</tr></thead><tbody>';

        if ($linhas === []) {
            $html .= '<tr><td colspan="' . $colspan . '">Sem notas lançadas neste boletim.</td></tr>';
        } else {
            foreach ($linhas as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $html .= '<tr><td class="comp">' . htmlspecialchars((string) ($row['componente'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                $celulas = is_array($row['celulas'] ?? null) ? $row['celulas'] : [];
                for ($i = 0; $i < $nPer; $i++) {
                    $c = is_array($celulas[$i] ?? null) ? $celulas[$i] : [];
                    $nota = trim((string) ($c['nota'] ?? '')) !== '' ? (string) $c['nota'] : '—';
                    $falta = trim((string) ($c['falta'] ?? '')) !== '' ? (string) $c['falta'] : '—';
                    $html .= '<td class="num">' . htmlspecialchars($nota, ENT_QUOTES, 'UTF-8') . '</td>'
                        . '<td class="num">' . htmlspecialchars($falta, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
        }

        return $html . '</tbody></table>';
    }

    public static function htmlQuadroNotasAmostra(): string
    {
        $nf = static fn (string $n, string $f): array => ['nota' => $n, 'falta' => $f];
        $vazio = [$nf('—', '—'), $nf('—', '—'), $nf('—', '—')];
        $linhas = [
            ['componente' => 'Língua Portuguesa', 'celulas' => array_merge([$nf('7,0', '8')], $vazio, [$nf('7,0', '8')])],
            ['componente' => 'Matemática', 'celulas' => array_merge([$nf('7,0', '2')], $vazio, [$nf('7,0', '2')])],
            ['componente' => 'Língua Inglesa', 'celulas' => array_merge([$nf('7,0', '8')], $vazio, [$nf('7,0', '8')])],
            ['componente' => 'História', 'celulas' => array_merge([$nf('7,5', '—')], $vazio, [$nf('7,5', '—')])],
            ['componente' => 'Geografia', 'celulas' => array_merge([$nf('8,0', '2')], $vazio, [$nf('8,0', '2')])],
            ['componente' => 'Física', 'celulas' => array_merge([$nf('5,5', '8')], $vazio, [$nf('5,5', '8')])],
            ['componente' => 'Química', 'celulas' => array_merge([$nf('6,0', '2')], $vazio, [$nf('6,0', '2')])],
            ['componente' => 'Biologia', 'celulas' => array_merge([$nf('9,0', '8')], $vazio, [$nf('9,0', '8')])],
            ['componente' => 'Redação', 'celulas' => array_merge([$nf('8,5', '—')], $vazio, [$nf('8,5', '—')])],
            ['componente' => 'Educação Física', 'celulas' => array_merge([$nf('10,0', '2')], $vazio, [$nf('10,0', '2')])],
            ['componente' => 'Sociologia', 'celulas' => array_merge([$nf('9,5', '8')], $vazio, [$nf('9,5', '8')])],
            ['componente' => 'Filosofia', 'celulas' => array_merge([$nf('8,0', '2')], $vazio, [$nf('8,0', '2')])],
            ['componente' => 'Arte', 'celulas' => array_merge([$nf('9,5', '8')], $vazio, [$nf('9,5', '8')])],
        ];

        return self::htmlQuadroNotas(
            ['1º Bimestre', '2º Bimestre', '3º Bimestre', '4º Bimestre', 'FINAL'],
            $linhas
        );
    }

    public static function idEstrutura(string $prefixo = 'n'): string
    {
        try {
            $rand = bin2hex(random_bytes(4));
        } catch (\Throwable $e) {
            $rand = (string) mt_rand(100000, 999999);
        }
        return $prefixo . '_' . $rand;
    }

    /**
     * Todos os placeholders, agrupados. Chaves que ainda não estiverem em nenhum
     * grupo entram em “Outros” — a lista da lateral nunca fica incompleta.
     *
     * @return array<string,array{label:string,chaves:list<string>}>
     */
    public static function gruposPlaceholders(): array
    {
        $grupos = [
            'documento' => [
                'label' => 'Documento',
                'chaves' => [
                    'titulo', 'doc_rotulo', 'numero', 'ano', 'data_hoje', 'cidade_data',
                    'diretor_nome', 'secretario_nome', 'assinante_nome', 'assinante_cargo',
                    'documento_assinatura', 'observacoes', 'info_pertinente', 'info_pertinente_html',
                    'pagina', 'total_paginas',
                ],
            ],
            'escola' => [
                'label' => 'Escola',
                'chaves' => [
                    'escola_nome', 'escola_cnpj', 'escola_cnpj_numero', 'escola_endereco',
                    'escola_docs', 'escola_origem', 'logo_html', 'razao_social', 'cnpj_layout',
                    'rodape_unidades',
                ],
            ],
            'aluno' => [
                'label' => 'Aluno',
                'chaves' => [
                    'aluno_nome', 'aluno_cpf', 'aluno_rg', 'aluno_cpf_frase', 'aluno_data_nasc',
                    'aluno_nasc_frase', 'aluno_email', 'aluno_telefone', 'aluno_endereco',
                    'aluno_cidade', 'aluno_codigo', 'matricula_numero',
                ],
            ],
            'responsavel' => [
                'label' => 'Responsáveis',
                'chaves' => [
                    'resp_nome', 'resp_cpf', 'resp_rg', 'resp_email', 'resp_telefone', 'resp_celular',
                    'resp_parentesco', 'resp_endereco', 'resp_bairro', 'resp_cep', 'resp_cidade',
                    'resp_estado_civil', 'responsaveis_html',
                    'se_resp2', 'resp2_nome', 'resp2_cpf', 'resp2_rg', 'resp2_email', 'resp2_telefone',
                    'resp2_celular', 'resp2_parentesco', 'resp2_endereco', 'resp2_bairro', 'resp2_cep',
                    'resp2_cidade', 'resp2_estado_civil',
                    'se_resp_fin', 'resp_fin_nome', 'resp_fin_cpf', 'resp_fin_rg', 'resp_fin_email',
                    'resp_fin_telefone', 'resp_fin_celular', 'resp_fin_endereco', 'resp_fin_bairro',
                    'resp_fin_cep', 'resp_fin_cidade',
                ],
            ],
            'turma' => [
                'label' => 'Turma / matrícula',
                'chaves' => [
                    'turma_nome', 'turma_frase', 'serie', 'ano_letivo', 'curso_nome',
                    'situacao_matricula', 'data_entrada', 'data_saida', 'tipo_matricula',
                    'periodo_label', 'periodo_nome', 'periodo_inicio', 'periodo_fim',
                ],
            ],
            'autorizacao' => [
                'label' => 'Autorização / comparecimento',
                'chaves' => [
                    'data_comparecimento', 'periodo_texto', 'periodo_texto_frase', 'data_evento',
                    'aut_horario', 'aut_motivo', 'aut_nome_autorizado', 'aut_documento',
                    'aut_parentesco', 'aut_local', 'aut_hora_saida', 'aut_hora_retorno', 'aut_finalidade',
                ],
            ],
            'academico' => [
                'label' => 'Notas / frequência / resultados',
                'chaves' => [
                    'quadro_notas_html', 'frequencia_html', 'frequencia_percentual', 'historico_html',
                    'tabela_html', 'situacao_final', 'titulo_relatorio', 'total_alunos',
                    'total_homologados', 'total_pendencias',
                ],
            ],
            'contrato' => [
                'label' => 'Contrato / valores',
                'chaves' => [
                    'valor_anuidade', 'valor_parcela', 'valor_liquido_parcela', 'valor_primeira_parcela',
                    'desconto_primeira', 'desconto_primeira_obs', 'valor_liquido_primeira',
                    'qtd_parcelas_primeira', 'valor_mensalidades_liq', 'desconto_parcela', 'num_parcelas',
                    'data_rematricula', 'pagante_modo',
                    'pagante1_nome', 'pagante1_cpf', 'pagante1_percentual',
                    'pagante2_nome', 'pagante2_cpf', 'pagante2_percentual',
                    'pagante3_nome', 'pagante3_cpf', 'pagante3_percentual',
                    'desc1_nome', 'desc1_valor', 'desc2_nome', 'desc2_valor',
                    'desc3_nome', 'desc3_valor', 'desc4_nome', 'desc4_valor',
                ],
            ],
        ];

        $usadas = [];
        foreach ($grupos as $grupo) {
            foreach ($grupo['chaves'] as $chave) {
                $usadas[$chave] = true;
            }
        }
        $resto = [];
        foreach (array_keys(self::PLACEHOLDERS) as $chave) {
            if (!isset($usadas[$chave])) {
                $resto[] = $chave;
            }
        }
        if ($resto !== []) {
            $grupos['outros'] = [
                'label' => 'Outros',
                'chaves' => $resto,
            ];
        }

        return $grupos;
    }

    public static function categoriaDoCodigo(string $codigo): string
    {
        $codigo = strtolower(trim($codigo));
        if (str_contains($codigo, '_aut_') || str_starts_with($codigo, 'declaracao_aut_')) {
            return 'autorizacao';
        }
        if (str_starts_with($codigo, 'declaracao_')) {
            return 'declaracao';
        }
        if (str_starts_with($codigo, 'contrato_')) {
            return 'contrato';
        }
        if (str_starts_with($codigo, 'resultado_') || str_starts_with($codigo, 'vida_escolar_')) {
            return 'oficial';
        }
        return 'outro';
    }

    /** CSS/estilo do PDF: contratos ficam simples; o restante usa o visual de declaração. */
    public static function estiloDoModelo(array $modelo): string
    {
        return self::categoriaDoCodigo((string) ($modelo['codigo'] ?? '')) === 'contrato'
            ? 'simples'
            : 'declaracao';
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
        if ($this->temColuna('formato_papel')) {
            $sql .= ', formato_papel';
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

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorCategoria(string $categoria, bool $somenteAtivos = false): array
    {
        $todos = $this->listar($somenteAtivos);
        $categoria = strtolower(trim($categoria));
        if ($categoria === '' || $categoria === 'todos') {
            return $todos;
        }
        $out = [];
        foreach ($todos as $row) {
            if (self::categoriaDoCodigo((string) ($row['codigo'] ?? '')) === $categoria) {
                $out[] = $row;
            }
        }
        return $out;
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
        $usar = false;
        if ($this->temColuna('usar_layout_padrao')) {
            $usar = (int) ($modelo['usar_layout_padrao'] ?? 0) === 1;
        } elseif (self::isModeloDeclaracao($modelo)) {
            $usar = true;
        }
        if (!$usar || !$this->layoutPadraoReady()) {
            return $modelo;
        }
        $layout = $this->getLayoutPadrao();
        $cabModelo = (string) ($modelo['cabecalho_html'] ?? '');
        $rodModelo = (string) ($modelo['rodape_html'] ?? '');
        $cabLayout = trim((string) ($layout['cabecalho_html'] ?? ''));
        $rodLayout = trim((string) ($layout['rodape_html'] ?? ''));
        // Só preenche cabeçalho/rodapé vazios. O HTML montado no modelo é o que sai no PDF.
        if ($cabLayout !== '' && $this->htmlEditorVazio($cabModelo)) {
            $modelo['cabecalho_html'] = $cabLayout;
        }
        if ($rodLayout !== '' && $this->htmlEditorVazio($rodModelo)) {
            $modelo['rodape_html'] = $rodLayout;
        }
        if (!empty($layout['imagem_cabecalho']) && trim((string) ($modelo['imagem_cabecalho'] ?? '')) === '') {
            $modelo['imagem_cabecalho'] = $layout['imagem_cabecalho'];
        }
        if (!empty($layout['imagem_rodape']) && trim((string) ($modelo['imagem_rodape'] ?? '')) === '') {
            $modelo['imagem_rodape'] = $layout['imagem_rodape'];
        }
        $modelo['_layout_razao_social'] = (string) ($layout['razao_social'] ?? '');
        $modelo['_layout_cnpj'] = (string) ($layout['cnpj'] ?? '');
        $modelo['_layout_unidade_assinatura_id'] = (int) ($layout['unidade_assinatura_id'] ?? 0);
        $modelo['_layout_cargo_assinante'] = (string) ($layout['cargo_assinante'] ?? 'direcao');
        $modelo['_layout_assinante_nome'] = (string) ($layout['assinante_nome'] ?? '');
        return $modelo;
    }

    private function htmlEditorVazio(string $html): bool
    {
        if (preg_match('/<(img|svg|table|hr|figure)\b/i', $html) === 1) {
            return false;
        }
        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = str_replace("\xC2\xA0", ' ', $texto);
        return trim($texto) === '';
    }

    /**
     * @param array<string,mixed> $modelo
     * @return array<string,mixed>
     */
    public function estruturaDoModelo(array $modelo): array
    {
        $raw = trim((string) ($modelo['estrutura_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['body'])) {
                return $this->normalizarEstrutura($decoded, $modelo);
            }
        }
        return $this->estruturaAPartirDeHtml($modelo);
    }

    public function modeloTemEstruturaVisual(array $modelo): bool
    {
        $raw = trim((string) ($modelo['estrutura_json'] ?? ''));
        if ($raw === '') {
            return false;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) && isset($decoded['body']);
    }

    /**
     * @param array<string,mixed> $estrutura
     * @param array<string,mixed> $modelo
     * @return array<string,mixed>
     */
    public function normalizarEstrutura(array $estrutura, array $modelo = []): array
    {
        $base = self::estruturaVazia(
            (string) ($modelo['formato_papel'] ?? 'a4'),
            (string) ($modelo['orientacao'] ?? 'retrato'),
            (int) ($modelo['margem_mm'] ?? 15)
        );
        if (isset($estrutura['page']) && is_array($estrutura['page'])) {
            $base['page'] = array_merge($base['page'], $estrutura['page']);
        }
        foreach (['header', 'body', 'footer'] as $area) {
            if (!isset($estrutura[$area]) || !is_array($estrutura[$area])) {
                continue;
            }
            $base[$area]['repeat'] = array_key_exists('repeat', $estrutura[$area])
                ? !empty($estrutura[$area]['repeat'])
                : ($area !== 'body');
            $secoes = $estrutura[$area]['sections'] ?? [];
            if (is_array($secoes)) {
                $base[$area]['sections'] = array_values(array_filter($secoes, 'is_array'));
            }
        }
        $base['version'] = 1;
        return $this->estruturaSemLogoDuplicado($base);
    }

    /**
     * Se já existe bloco tipo logo, tira {{logo_html}} de textos/HTML irmãos (evita duas logos no PDF).
     *
     * @param array<string,mixed> $estrutura
     * @return array<string,mixed>
     */
    private function estruturaSemLogoDuplicado(array $estrutura): array
    {
        foreach (['header', 'body', 'footer'] as $area) {
            $secoes = $estrutura[$area]['sections'] ?? null;
            if (!is_array($secoes)) {
                continue;
            }
            $temLogo = false;
            foreach ($secoes as $secao) {
                if (!is_array($secao)) {
                    continue;
                }
                foreach ($secao['columns'] ?? [] as $col) {
                    if (!is_array($col)) {
                        continue;
                    }
                    foreach ($col['elements'] ?? [] as $el) {
                        if (is_array($el) && ($el['type'] ?? '') === 'logo') {
                            $temLogo = true;
                            break 3;
                        }
                    }
                }
            }
            if (!$temLogo) {
                continue;
            }
            foreach ($secoes as $si => $secao) {
                if (!is_array($secao)) {
                    continue;
                }
                foreach ($secao['columns'] ?? [] as $ci => $col) {
                    if (!is_array($col)) {
                        continue;
                    }
                    foreach ($col['elements'] ?? [] as $ei => $el) {
                        if (!is_array($el)) {
                            continue;
                        }
                        $tipo = (string) ($el['type'] ?? '');
                        if (!in_array($tipo, ['html', 'texto', 'texto_rico'], true)) {
                            continue;
                        }
                        $props = is_array($el['props'] ?? null) ? $el['props'] : [];
                        foreach (['html', 'text'] as $campo) {
                            if (isset($props[$campo]) && is_string($props[$campo])) {
                                $props[$campo] = $this->removerPlaceholderLogo($props[$campo]);
                            }
                        }
                        $estrutura[$area]['sections'][$si]['columns'][$ci]['elements'][$ei]['props'] = $props;
                    }
                }
            }
        }
        return $estrutura;
    }

    private function removerPlaceholderLogo(string $html): string
    {
        $html = (string) preg_replace('/\{\{\s*logo_html\s*\}\}/i', '', $html);
        $html = (string) preg_replace('/<p[^>]*>\s*(?:&nbsp;|\xC2\xA0|\s)*<\/p>/i', '', $html);

        return $html;
    }

    /**
     * @param array<string,mixed> $estrutura
     */
    private function estruturaTemTipo(array $estrutura, string $tipo): bool
    {
        foreach (['header', 'body', 'footer'] as $area) {
            foreach ($estrutura[$area]['sections'] ?? [] as $secao) {
                if (!is_array($secao)) {
                    continue;
                }
                foreach ($secao['columns'] ?? [] as $col) {
                    if (!is_array($col)) {
                        continue;
                    }
                    foreach ($col['elements'] ?? [] as $el) {
                        if (is_array($el) && ($el['type'] ?? '') === $tipo) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $modelo
     * @return array<string,mixed>
     */
    public function estruturaAPartirDeHtml(array $modelo): array
    {
        $est = self::estruturaVazia(
            (string) ($modelo['formato_papel'] ?? 'a4'),
            (string) ($modelo['orientacao'] ?? 'retrato'),
            (int) ($modelo['margem_mm'] ?? 15)
        );
        $map = [
            'header' => (string) ($modelo['cabecalho_html'] ?? ''),
            'body' => (string) ($modelo['corpo_html'] ?? ''),
            'footer' => (string) ($modelo['rodape_html'] ?? ''),
        ];
        foreach ($map as $area => $html) {
            $html = trim($html);
            $cols = $area === 'header' ? [30, 70] : [100];
            $secao = self::secaoPadrao($cols, $area);
            if ($html !== '') {
                if ($area === 'header' && count($secao['columns']) === 2) {
                    $htmlLimpo = $this->removerPlaceholderLogo($html);
                    $secao['columns'][0]['elements'][] = [
                        'id' => self::idEstrutura('e'),
                        'type' => 'logo',
                        'props' => ['width' => 120, 'align' => 'center', 'vAlign' => 'middle'],
                    ];
                    $secao['columns'][1]['elements'][] = [
                        'id' => self::idEstrutura('e'),
                        'type' => 'html',
                        'props' => ['html' => $htmlLimpo],
                    ];
                } else {
                    $secao['columns'][0]['elements'][] = [
                        'id' => self::idEstrutura('e'),
                        'type' => 'html',
                        'props' => ['html' => $html],
                    ];
                }
            } elseif ($area === 'body') {
                $secao['columns'][0]['elements'][] = [
                    'id' => self::idEstrutura('e'),
                    'type' => 'titulo',
                    'props' => ['text' => '{{escola_nome}}', 'tag' => 'h1'],
                ];
            } else {
                $est[$area]['sections'] = [];
                continue;
            }
            $est[$area]['sections'] = [$secao];
        }
        return $est;
    }

    /**
     * @param array<string,mixed> $estrutura
     * @param array<string,string> $vars
     * @return array{cabecalho:string,corpo:string,rodape:string}
     */
    public function htmlDaEstrutura(array $estrutura, array $vars = []): array
    {
        require_once __DIR__ . '/DocumentoRenderer.php';
        $renderer = new DocumentoRenderer();
        return $renderer->renderizarPartes($estrutura, $vars);
    }

    /**
     * @param array<string,mixed> $estrutura
     * @param array<string,mixed> $data
     */
    public function salvarEstrutura(int $id, array $estrutura, array $data, ?array $user = null): int
    {
        if (!$this->temColuna('estrutura_json')) {
            throw new \RuntimeException(
                'Execute a migration 2026_08_22_modelos_documentos_estrutura.sql no painel Master.'
            );
        }
        $exist = $id > 0 ? $this->findById($id) : null;
        $estrutura = $this->normalizarEstrutura($estrutura, array_merge($exist ?: [], $data));
        $codigoTentativa = $this->normalizarCodigo((string) ($data['codigo'] ?? $exist['codigo'] ?? ''));
        if ($id <= 0 && self::isCodigoSistema($codigoTentativa)) {
            throw new \InvalidArgumentException(
                'Este código pertence a um modelo do sistema. Edite o modelo existente em vez de criar outro com o mesmo código.'
            );
        }
        $json = json_encode($estrutura, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            throw new \InvalidArgumentException('Estrutura do documento inválida.');
        }

        $partes = $this->htmlDaEstrutura($estrutura);
        $corpo = trim($partes['corpo']);
        if ($corpo === '') {
            $corpo = '<p>&nbsp;</p>';
        }
        $payload = [
            'codigo' => (string) ($data['codigo'] ?? $exist['codigo'] ?? ''),
            'nome' => (string) ($data['nome'] ?? $exist['nome'] ?? ''),
            'descricao' => (string) ($data['descricao'] ?? $exist['descricao'] ?? ''),
            'cabecalho_html' => $partes['cabecalho'],
            'corpo_html' => $corpo,
            'rodape_html' => $partes['rodape'],
            'ativo' => array_key_exists('ativo', $data)
                ? (!empty($data['ativo']) ? 1 : 0)
                : (int) ($exist['ativo'] ?? 1),
            'orientacao' => (string) ($data['orientacao'] ?? $exist['orientacao'] ?? 'retrato'),
            'formato_papel' => (string) ($data['formato_papel'] ?? $exist['formato_papel'] ?? 'a4'),
            'margem_mm' => $data['margem_mm'] ?? $exist['margem_mm'] ?? 15,
            'espacamento_linha' => $data['espacamento_linha'] ?? $exist['espacamento_linha'] ?? 1.5,
            'usar_layout_padrao' => array_key_exists('usar_layout_padrao', $data)
                ? (!empty($data['usar_layout_padrao']) ? 1 : 0)
                : (int) ($exist['usar_layout_padrao'] ?? 1),
            'estrutura_json' => $json,
        ];
        if (trim($payload['nome']) === '') {
            throw new \InvalidArgumentException('Informe o nome do modelo.');
        }
        if (trim($payload['codigo']) === '') {
            $payload['codigo'] = 'modelo_' . date('YmdHis');
        }
        $saved = $this->salvar($payload, $id > 0 ? $id : null, $user);
        if ($this->temColuna('estrutura_json')) {
            $this->db->update(
                'UPDATE secretaria_modelos_documentos SET estrutura_json = ? WHERE id = ?',
                [$json, $saved]
            );
        }
        return $saved;
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
            'formato_papel' => $this->normalizarFormatoPapel((string) ($data['formato_papel'] ?? 'a4')),
            'margem_mm' => $this->normalizarMargemMm($data['margem_mm'] ?? 20),
            'espacamento_linha' => $this->normalizarEspacamento($data['espacamento_linha'] ?? 1.5),
            'usar_layout_padrao' => array_key_exists('usar_layout_padrao', $data)
                ? (!empty($data['usar_layout_padrao']) ? 1 : 0)
                : 0,
        ];
        if ($this->temColuna('estrutura_json') && array_key_exists('estrutura_json', $data)) {
            $payload['estrutura_json'] = $data['estrutura_json'];
        }

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
            if ($this->temColuna('formato_papel')) {
                $sets .= ', formato_papel = ?';
                $params[] = $payload['formato_papel'];
            }
            if ($this->temColuna('margem_mm')) {
                $sets .= ', margem_mm = ?';
                $params[] = $payload['margem_mm'];
            }
            if ($this->temColuna('espacamento_linha')) {
                $sets .= ', espacamento_linha = ?';
                $params[] = $payload['espacamento_linha'];
            }
            if ($this->temColuna('usar_layout_padrao')) {
                $sets .= ', usar_layout_padrao = ?';
                $params[] = $payload['usar_layout_padrao'];
            }
            if (isset($payload['estrutura_json'])) {
                $sets .= ', estrutura_json = ?';
                $params[] = $payload['estrutura_json'];
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
        if ($this->temColuna('formato_papel')) {
            $cols .= ', formato_papel';
            $marks .= ',?';
            $params[] = $payload['formato_papel'];
        }
        if ($this->temColuna('margem_mm')) {
            $cols .= ', margem_mm';
            $marks .= ',?';
            $params[] = $payload['margem_mm'];
        }
        if ($this->temColuna('espacamento_linha')) {
            $cols .= ', espacamento_linha';
            $marks .= ',?';
            $params[] = $payload['espacamento_linha'];
        }
        if ($this->temColuna('usar_layout_padrao')) {
            $cols .= ', usar_layout_padrao';
            $marks .= ',?';
            $params[] = $payload['usar_layout_padrao'];
        }
        if (isset($payload['estrutura_json'])) {
            $cols .= ', estrutura_json';
            $marks .= ',?';
            $params[] = $payload['estrutura_json'];
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
        $unidadeAssinatura = null;
        if (!isset($vars['assinante_nome']) || $vars['assinante_nome'] === ''
            || !isset($vars['assinante_cargo']) || $vars['assinante_cargo'] === '') {
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

        if (trim((string) ($vars['logo_html'] ?? '')) === '') {
            $unidadeParaLogo = is_array($unidadeAssinatura) ? $unidadeAssinatura : null;
            if ($unidadeParaLogo === null) {
                $unidadeIdLogo = (int) ($modelo['_layout_unidade_assinatura_id'] ?? 0);
                if ($unidadeIdLogo > 0) {
                    try {
                        require_once BASE_PATH . '/app/Models/Education/SchoolUnit.php';
                        $unidadeParaLogo = (new \SchoolUnit())->findById($unidadeIdLogo) ?: null;
                    } catch (\Throwable $e) {
                        $unidadeParaLogo = null;
                    }
                }
            }
            $vars['logo_html'] = $this->logoHtmlInstitucional(is_array($unidadeParaLogo) ? $unidadeParaLogo : null, $config);
        }

        $htmlBrutoCab = '';
        $htmlBrutoCorpo = '';
        $estruturaVisual = null;
        if ($this->modeloTemEstruturaVisual($modelo)) {
            $estruturaVisual = $this->estruturaDoModelo($modelo);
            $partes = $this->htmlDaEstrutura($estruturaVisual, $vars);
            $htmlBrutoCab = $partes['cabecalho'];
            $htmlBrutoCorpo = $partes['corpo'];
            $cab = $this->aplicarPlaceholders($partes['cabecalho'], $vars);
            $corpo = $this->aplicarPlaceholders($partes['corpo'], $vars);
            $rodape = $this->aplicarPlaceholders($partes['rodape'], $vars);
        } else {
            $htmlBrutoCab = (string) ($modelo['cabecalho_html'] ?? '');
            $htmlBrutoCorpo = (string) ($modelo['corpo_html'] ?? '');
            $cab = $this->aplicarPlaceholders($htmlBrutoCab, $vars);
            $corpo = $this->aplicarPlaceholders($htmlBrutoCorpo, $vars);
            $rodape = $this->aplicarPlaceholders((string) ($modelo['rodape_html'] ?? ''), $vars);
        }

        $codigo = (string) ($modelo['codigo'] ?? '');
        if ($estilo === 'auto') {
            $estilo = str_starts_with($codigo, 'declaracao_') ? 'declaracao' : 'simples';
        }

        $css = $estilo === 'declaracao' ? $this->cssDeclaracao($modelo) : $this->cssSimples($modelo);
        if ($estruturaVisual !== null) {
            $css = $this->cssSimples($modelo);
        }
        $css .= "\n" . $this->cssEstrutura();
        $css .= "\n" . $this->cssBanners($modelo);

        $imgCabHtml = '';
        $imgRodHtml = '';
        $srcCab = $this->resolverImagemSrc((string) ($modelo['imagem_cabecalho'] ?? ''), $config);
        $srcRod = $this->resolverImagemSrc((string) ($modelo['imagem_rodape'] ?? ''), $config);
        [$cabH, $rodH] = $this->alturasFaixa($modelo);

        // Editor visual / documentos oficiais: o layout do modelo é a fonte.
        // Não prependar faixa do papel timbrado (Dompdf estica PNG e gera páginas em branco).
        $pularFaixa = $estruturaVisual !== null
            || self::categoriaDoCodigo($codigo) === 'oficial';
        if (!$pularFaixa) {
            if ($estilo === 'declaracao') {
                if ($srcCab !== '') {
                    $srcEsc = htmlspecialchars($srcCab, ENT_QUOTES, 'UTF-8');
                    $imgCabHtml = '<div class="banner-cab"><img src="' . $srcEsc
                        . '" alt="Cabeçalho" style="height:' . $cabH . ';width:auto;max-width:100%;"></div>';
                } else {
                    $logoHtml = trim((string) ($vars['logo_html'] ?? ''));
                    $modeloJaTemLogo = str_contains($htmlBrutoCab . $htmlBrutoCorpo, '{{logo_html}}');
                    if ($logoHtml !== '' && !$modeloJaTemLogo) {
                        $imgCabHtml = '<div class="doc-logo" style="text-align:center;margin:0 0 16px;">' . $logoHtml . '</div>';
                    }
                }
                if ($srcRod !== '') {
                    $srcEsc = htmlspecialchars($srcRod, ENT_QUOTES, 'UTF-8');
                    $imgRodHtml = '<div class="banner-rod"><img src="' . $srcEsc
                        . '" alt="Rodapé" style="height:' . $rodH . ';width:auto;max-width:100%;"></div>';
                }
            } else {
                $logoHtml = trim((string) ($vars['logo_html'] ?? ''));
                $modeloJaTemLogo = str_contains($htmlBrutoCab . $htmlBrutoCorpo, '{{logo_html}}');
                if ($modeloJaTemLogo) {
                    $logoHtml = '';
                }
                if ($logoHtml === '' && !$modeloJaTemLogo && $srcCab !== '') {
                    $srcEsc = htmlspecialchars($srcCab, ENT_QUOTES, 'UTF-8');
                    $logoHtml = '<img src="' . $srcEsc . '" alt="Logo" width="180" height="70" style="height:70px;width:auto;max-width:220px;">';
                }
                if ($logoHtml !== '') {
                    $imgCabHtml = '<div class="doc-logo" style="text-align:center;margin:0 0 16px;">' . $logoHtml . '</div>';
                }
            }
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

    /** @return 'A4'|'A5' */
    public function papelDompdf(array $modelo): string
    {
        return $this->normalizarFormatoPapel((string) ($modelo['formato_papel'] ?? 'a4')) === 'a5' ? 'A5' : 'A4';
    }

    public function margemMm(array $modelo): int
    {
        return $this->normalizarMargemMm($modelo['margem_mm'] ?? 20);
    }

    public function espacamentoLinha(array $modelo): float
    {
        return $this->normalizarEspacamento($modelo['espacamento_linha'] ?? 1.5);
    }

    /**
     * @param \Dompdf\Dompdf $dompdf
     */
    public function aplicarPapelDompdf($dompdf, array $modelo): void
    {
        if (!is_object($dompdf) || !method_exists($dompdf, 'setPaper')) {
            return;
        }
        if ($this->modeloTemEstruturaVisual($modelo)) {
            $page = $this->estruturaDoModelo($modelo)['page'] ?? [];
            if (($page['orientation'] ?? '') === 'landscape') {
                $modelo['orientacao'] = 'paisagem';
            } elseif (($page['orientation'] ?? '') === 'portrait') {
                $modelo['orientacao'] = 'retrato';
            }
            if (!empty($page['size'])) {
                $modelo['formato_papel'] = $this->normalizarFormatoPapel((string) $page['size']);
            }
            if (isset($page['margin']['top'])) {
                $modelo['margem_mm'] = $this->normalizarMargemMm($page['margin']['top']);
            }
        }
        $dompdf->setPaper($this->papelDompdf($modelo), $this->orientacaoDompdf($modelo));
    }

    public function normalizarFormatoPapel(string $formato): string
    {
        return strtolower(trim($formato)) === 'a5' ? 'a5' : 'a4';
    }

    public function normalizarMargemMm($valor): int
    {
        $m = (int) $valor;
        if ($m < 8) {
            return 8;
        }
        if ($m > 40) {
            return 40;
        }
        return $m;
    }

    public function normalizarEspacamento($valor): float
    {
        $v = (float) str_replace(',', '.', (string) $valor);
        $permitidos = [1.0, 1.15, 1.5, 2.0];
        $melhor = 1.5;
        $delta = 99;
        foreach ($permitidos as $p) {
            $d = abs($p - $v);
            if ($d < $delta) {
                $delta = $d;
                $melhor = $p;
            }
        }
        return $melhor;
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

        if (str_starts_with($ref, 'data:image/png')
            || str_starts_with($ref, 'data:image/jpeg')
            || str_starts_with($ref, 'data:image/gif')
            || str_starts_with($ref, 'data:image/webp')
            || str_starts_with($ref, 'data:image/jpg')) {
            return $ref;
        }
        if (str_starts_with($ref, 'data:')) {
            return '';
        }
        // PDF com remote desligado: só data-URI. Converte /media/serve do tenant; rejeita URL externa.
        if (str_starts_with($ref, 'http://') || str_starts_with($ref, 'https://')
            || str_starts_with($ref, '/media/serve')
            || str_starts_with($ref, 'media/serve')) {
            return $this->urlParaDataUri($ref, $config);
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
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo ? finfo_file($finfo, $real) : false;
            if ($finfo) {
                finfo_close($finfo);
            }
            $permitidos = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
            if (!is_string($mimeReal) || !in_array($mimeReal, $permitidos, true)) {
                continue;
            }
            $bin = @file_get_contents($real);
            $mime = $mimeReal;
            break;
        }

        // S3 key
        if (($bin === null || $bin === false || $bin === '') && is_array($config)) {
            try {
                require_once $base . '/app/Services/MediaStorageService.php';
                $media = new \MediaStorageService($config);
                $contents = $media->getContents('arquivos', $ref);
                if (is_string($contents) && $contents !== '') {
                    $finfoBuf = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeBuf = $finfoBuf ? finfo_buffer($finfoBuf, $contents) : false;
                    if ($finfoBuf) {
                        finfo_close($finfoBuf);
                    }
                    $okMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
                    if (is_string($mimeBuf) && in_array($mimeBuf, $okMime, true)) {
                        $bin = $contents;
                        $mime = $mimeBuf;
                    }
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

    /**
     * Logo da unidade (se houver), da escola (config de layout) ou da faixa
     * do papel timbrado, em data-URI para o Dompdf (remote desligado).
     *
     * @param array<string,mixed>|null $unidade
     * @param array<string,mixed>|null $config
     */
    public function logoHtmlInstitucional(?array $unidade, ?array $config = null): string
    {
        $urls = [];
        $unidadeLogo = trim((string) ($unidade['logo_url'] ?? ''));
        if ($unidadeLogo !== '') {
            $urls[] = $unidadeLogo;
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        if (!class_exists('LayoutHelper', false)) {
            require_once $base . '/app/Core/LayoutHelper.php';
        }
        try {
            $principal = trim((string) \LayoutHelper::getDocumentLogoUrl());
            if ($principal !== '') {
                $urls[] = $principal;
            }
            $login = trim((string) \LayoutHelper::getLoginLogoUrl());
            if ($login !== '' && !in_array($login, $urls, true)) {
                $urls[] = $login;
            }
        } catch (\Throwable $e) {
            // layout ainda não carregado
        }

        if ($unidadeLogo === '') {
            try {
                require_once $base . '/app/Models/Education/SchoolUnit.php';
                $ativas = (new \SchoolUnit())->getActive();
                $matriz = $ativas[0] ?? null;
                if (is_array($matriz)) {
                    $fallbackUnidade = trim((string) ($matriz['logo_url'] ?? ''));
                    if ($fallbackUnidade !== '' && !in_array($fallbackUnidade, $urls, true)) {
                        array_unshift($urls, $fallbackUnidade);
                    }
                }
            } catch (\Throwable $e) {
                // sem tabela unidades
            }
        }

        foreach ($urls as $url) {
            $data = $this->urlParaDataUri((string) $url, $config);
            if ($data === '') {
                continue;
            }
            return $this->htmlImgLogo($data);
        }

        try {
            $layout = $this->getLayoutPadrao();
            $srcFaixa = $this->resolverImagemSrc((string) ($layout['imagem_cabecalho'] ?? ''), $config);
            if ($this->ehDataUriImagem($srcFaixa)) {
                return $this->htmlImgLogo($srcFaixa);
            }
        } catch (\Throwable $e) {
            // sem papel timbrado
        }

        return '';
    }

    private function htmlImgLogo(string $src): string
    {
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
            . '" alt="Logo" width="180" height="70" style="height:70px;width:auto;max-width:220px;display:inline-block;vertical-align:middle;">';
    }

    private function ehDataUriImagem(string $src): bool
    {
        return str_starts_with($src, 'data:image/png')
            || str_starts_with($src, 'data:image/jpeg')
            || str_starts_with($src, 'data:image/gif')
            || str_starts_with($src, 'data:image/webp')
            || str_starts_with($src, 'data:image/jpg');
    }

    /**
     * Converte URL local de logo (media/serve layout) em data-URI. Sem fetch HTTP.
     *
     * @param array<string,mixed>|null $config
     */
    private function urlParaDataUri(string $url, ?array $config = null): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if ($this->ehDataUriImagem($url)) {
            return $url;
        }
        if (str_starts_with($url, 'data:')) {
            return '';
        }

        $parts = parse_url($url) ?: [];
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        $slugAtual = $this->slugTenantAtual($config);
        $slugQuery = preg_replace('/[^a-z0-9_-]/i', '', (string) ($query['tenant'] ?? '')) ?? '';
        if ($slugQuery !== '' && $slugAtual !== '' && strcasecmp($slugQuery, $slugAtual) !== 0) {
            return '';
        }

        $type = strtolower(trim((string) ($query['type'] ?? '')));
        $key = basename(str_replace('\\', '/', (string) ($query['key'] ?? '')));

        if ($key === '' && !empty($parts['path'])) {
            $pathUrl = str_replace('\\', '/', (string) $parts['path']);
            if (preg_match('#/storage/files/([a-zA-Z0-9_-]+)/layout/([^/]+)$#', $pathUrl, $m) === 1) {
                $slugPath = (string) ($m[1] ?? '');
                if ($slugAtual !== '' && strcasecmp($slugPath, $slugAtual) !== 0) {
                    return '';
                }
                $key = basename((string) ($m[2] ?? ''));
                $type = 'layout';
            }
        }

        if ($type === '') {
            $type = 'layout';
        }
        if ($type !== 'layout') {
            return '';
        }
        if ($key === '' || $key === '.' || $key === '..' || str_contains($key, "\0") || str_contains($key, '..')) {
            return '';
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $filePath = '';
        $cfg = is_array($config) ? $config : [];
        if ($slugAtual !== '') {
            $cfg['tenant'] = array_merge($cfg['tenant'] ?? [], ['slug' => $slugAtual]);
        }

        try {
            require_once $base . '/app/Services/MediaStorageService.php';
            $media = new \MediaStorageService($cfg);
            $localPath = $media->getLocalPath('layout', $key);
            if ($localPath !== null && is_file($localPath) && is_readable($localPath)) {
                $filePath = $localPath;
            }
        } catch (\Throwable $e) {
            $filePath = '';
        }

        if ($filePath === '' && $slugAtual !== '') {
            $cand = $base . '/storage/files/' . $slugAtual . '/layout/' . $key;
            if (is_file($cand) && is_readable($cand)) {
                $filePath = $cand;
            }
        }

        if ($filePath === '') {
            return '';
        }

        $real = realpath($filePath);
        if ($real === false) {
            return '';
        }
        $slugPasta = $slugAtual !== '' ? $slugAtual : 'default';
        $rootTenant = realpath($base . '/storage/files/' . $slugPasta . '/layout');
        if ($rootTenant === false
            || (!str_starts_with($real, $rootTenant . DIRECTORY_SEPARATOR) && $real !== $rootTenant)) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo ? finfo_file($finfo, $real) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        $permitidos = [
            'image/png' => true,
            'image/jpeg' => true,
            'image/gif' => true,
            'image/webp' => true,
        ];
        if (!is_string($mimeReal) || !isset($permitidos[$mimeReal])) {
            return '';
        }
        $bin = @file_get_contents($real);
        if (!is_string($bin) || $bin === '') {
            return '';
        }
        return 'data:' . $mimeReal . ';base64,' . base64_encode($bin);
    }

    /**
     * @param array<string,mixed>|null $config
     */
    private function slugTenantAtual(?array $config): string
    {
        $slug = '';
        if (defined('TENANT_SLUG')) {
            $slug = (string) TENANT_SLUG;
        }
        if ($slug === '' && is_array($config)) {
            $slug = (string) ($config['tenant']['slug'] ?? $config['school']['code'] ?? '');
        }
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?? '';
        return $slug;
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
        $pickAluno = static function (array $arr, array $keys): string {
            foreach ($keys as $k) {
                if (isset($arr[$k]) && trim((string) $arr[$k]) !== '') {
                    return trim((string) $arr[$k]);
                }
            }
            return '';
        };
        $alunoEndereco = trim(implode(', ', array_filter([
            $pickAluno($aluno, ['logradouro', 'endereco', 'endereco_logradouro'])
                . ($pickAluno($aluno, ['numero', 'endereco_numero']) !== ''
                    ? ', ' . $pickAluno($aluno, ['numero', 'endereco_numero']) : ''),
            $pickAluno($aluno, ['complemento', 'endereco_complemento']),
            $pickAluno($aluno, ['bairro', 'endereco_bairro']),
            trim($pickAluno($aluno, ['cidade', 'endereco_cidade'])
                . ($pickAluno($aluno, ['uf', 'estado', 'endereco_uf']) !== ''
                    ? ' / ' . $pickAluno($aluno, ['uf', 'estado', 'endereco_uf']) : '')),
            $pickAluno($aluno, ['cep', 'endereco_cep']) !== ''
                ? 'CEP ' . $pickAluno($aluno, ['cep', 'endereco_cep']) : '',
        ])));
        $alunoCidade = $pickAluno($aluno, ['cidade', 'endereco_cidade']);
        $alunoTelefone = $pickAluno($aluno, ['celular', 'telefone']);
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
            'aluno_telefone' => $esc($alunoTelefone !== '' ? $alunoTelefone : '—'),
            'aluno_endereco' => $esc($alunoEndereco !== '' ? $alunoEndereco : '—'),
            'aluno_cidade' => $esc($alunoCidade !== '' ? $alunoCidade : '—'),
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
            'pagante1_nome' => 'Ana Paula Silva',
            'pagante1_cpf' => '987.654.321-00',
            'pagante1_percentual' => '100%',
            'pagante2_nome' => '',
            'pagante2_cpf' => '',
            'pagante2_percentual' => '',
            'pagante3_nome' => '',
            'pagante3_cpf' => '',
            'pagante3_percentual' => '',
            'pagante_modo' => 'Único pagante',
            'documento_assinatura' => 'Contrato de Matrícula',
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
            'quadro_notas_html' => self::htmlQuadroNotasAmostra(),
            'identidade_html' => '<table class="dados"><tr><td class="label">Nome</td><td>Maria Eduarda Silva</td></tr><tr><td class="label">CPF</td><td>123.456.789-00</td></tr></table>',
            'trajetoria_html' => '<table class="dados"><tr><td>2024</td><td>8º Ano</td><td>EMEF Exemplo</td><td>externo</td><td>Aprovado</td></tr></table>',
            'documentos_html' => '<table class="dados"><tr><td>Certidão de nascimento</td><td>entregue</td></tr></table>',
            'sed_html' => '<table class="dados"><tr><td>CPF ou RG</td><td>Ok</td></tr></table>',
            'situacao_final' => 'Aprovado',
            'frequencia_percentual' => '95,0%',
            'tabela_html' => '<table class="dados"><tr><td class="label">Aluno</td><td class="label">Situação</td></tr><tr><td>Maria Eduarda Silva</td><td>Aprovado</td></tr></table>',
            'titulo_relatorio' => 'Relatório de fechamento',
            'periodo_label' => '2º bimestre',
            'periodo_nome' => '2º bimestre',
            'pagina' => '1',
            'total_paginas' => '1',
            'total_alunos' => '28',
            'total_homologados' => '26',
            'total_pendencias' => '2',
        ];
        foreach ($amostra as $k => $v) {
            $htmlKeys = [
                'logo_html', 'frequencia_html', 'info_pertinente_html', 'responsaveis_html',
                'historico_html', 'quadro_notas_html', 'tabela_html',
                'identidade_html', 'trajetoria_html', 'documentos_html', 'sed_html',
            ];
            $out[$k] = in_array($k, $htmlKeys, true) ? $v : $esc($v);
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

    private function cssSimples(array $modelo = []): string
    {
        $margem = $this->margemMm($modelo);
        $lh = number_format($this->espacamentoLinha($modelo), 2, '.', '');
        return <<<CSS
  @page { margin: {$margem}mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #222; line-height: {$lh}; margin: 0; }
  h1,h2,h3 { color: #111; }
  p { margin: 0 0 8px; }
  table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  th, td { border: 1px solid #ccc; padding: 4px 6px; }
  th { background: #f3f4f6; }
  table.quadro-notas { font-size: inherit; }
  table.quadro-notas th, table.quadro-notas td { font-size: inherit !important; padding: 3px 4px; }
  .page-break { page-break-after: always; break-after: page; }
CSS;
    }

    /**
     * Colunas tipo Elementor: sem borda no PDF, vertical-align via style inline.
     */
    private function cssEstrutura(): string
    {
        return <<<CSS
  table.doc-linha { width: 100% !important; border-collapse: collapse; table-layout: fixed; margin: 0 0 8px 0; border: none !important; page-break-inside: auto; }
  table.doc-linha table { width: 100%; margin: 0 !important; border: none !important; border-collapse: collapse; height: auto !important; }
  figure.table { width: 100%; margin: 0 0 10px 0; }
  figure.table table { width: 100%; border: none !important; }
  table.doc-linha td, table.doc-linha th,
  figure.table td, figure.table th { border: none !important; background: transparent !important; padding: 4px 8px; vertical-align: middle; }
  table.dados { margin: 8px 0; }
  table.dados td, table.dados th { border: 1px solid #ccc !important; background: transparent; }
  table.quadro-notas { width: 100%; border-collapse: collapse; margin: 4px 0 8px; font-size: inherit; }
  table.quadro-notas th, table.quadro-notas td { border: 1px solid #d1d5db !important; padding: 3px 4px; font-size: inherit !important; }
  table.quadro-notas th { background: #f3f4f6 !important; font-weight: bold; text-align: center; text-transform: uppercase; letter-spacing: .02em; color: #6b7280; }
  table.quadro-notas td.comp, table.quadro-notas th.comp { text-align: left; text-transform: none; letter-spacing: 0; color: #111827; font-weight: 600; }
  table.quadro-notas td.num { text-align: center; }
  .quadro-notas-wrap { font-size: inherit; }
  .doc-logo-el img, .doc-logo img { height: 70px; width: auto; max-width: 220px; display: inline-block; vertical-align: middle; }
  table.doc-linha img { max-width: 100%; }
  figure.image, .image { margin: 4px 0; }
  .image img { max-width: 100%; height: auto; }
  .image-style-align-left { float: left; margin: 0 10px 8px 0; }
  .image-style-align-right { float: right; margin: 0 0 8px 10px; }
  .image-style-align-center, .image-style-block-align-center { display: table; margin: 0 auto; }
  .doc-secao { page-break-inside: auto; }
CSS;
    }
    private function cssBanners(array $modelo): string
    {
        [$cabMax, $rodMax] = $this->alturasFaixa($modelo);

        return <<<CSS
  .banner-cab { width: 100%; margin: 0 0 8px 0; padding: 0; text-align: center; }
  .banner-cab img { display: inline-block; height: {$cabMax}; width: auto; max-width: 100%; }
  .banner-rod { width: 100%; margin: 12px 0 0 0; padding: 0; text-align: center; }
  .banner-rod img { display: inline-block; height: {$rodMax}; width: auto; max-width: 100%; }
CSS;
    }

    /** @return array{0:string,1:string} */
    private function alturasFaixa(array $modelo): array
    {
        $a5 = $this->normalizarFormatoPapel((string) ($modelo['formato_papel'] ?? 'a4')) === 'a5';
        $paisagem = (($modelo['orientacao'] ?? 'retrato') === 'paisagem');
        if ($a5) {
            return $paisagem ? ['22mm', '15mm'] : ['29mm', '20mm'];
        }
        return $paisagem ? ['32mm', '22mm'] : ['42mm', '28mm'];
    }

    private function cssDeclaracao(array $modelo = []): string
    {
        $margem = $this->margemMm($modelo);
        $lh = number_format($this->espacamentoLinha($modelo), 2, '.', '');
        return <<<CSS
  @page { margin: {$margem}mm; }
  * { box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 11pt; margin: 0; line-height: {$lh}; }
  .header { display: table; width: 100%; border-bottom: 2px solid #064e3b; padding-bottom: 10px; margin-bottom: 6px; }
  .header .logo-cell { display: table-cell; width: 90px; vertical-align: middle; }
  .header .title-cell { display: table-cell; vertical-align: middle; padding-left: 12px; }
  .header img { max-height: 64px; max-width: 84px; }
  .header .escola { font-size: 13pt; font-weight: bold; color: #064e3b; margin: 0 0 2px 0; }
  .header .meta { font-size: 8.5pt; color: #4b5563; margin: 1px 0; }
  .doc-num { text-align: right; font-size: 8.5pt; color: #6b7280; margin: 6px 0 18px 0; }
  h1.doc-title { text-align: center; font-size: 15pt; color: #111827; letter-spacing: 1px; text-transform: uppercase; margin: 10px 0 26px 0; }
  .corpo { margin: 0 4px; }
  .corpo p { margin: 0 0 0.6em 0; }
  .destaque { font-weight: bold; color: #111827; }
  table.dados { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: inherit; }
  table.dados td { border: 1px solid #d1d5db; padding: 6px 9px; font-size: inherit; }
  table.dados td.label { background: #f3f4f6; font-weight: bold; width: 38%; }
  table.quadro-notas { margin: 4px 0 8px; font-size: inherit; }
  table.quadro-notas th, table.quadro-notas td { font-size: inherit !important; padding: 3px 4px; }
  table.quadro-notas th { background: #f3f4f6; font-weight: bold; text-align: center; text-transform: uppercase; letter-spacing: .02em; color: #6b7280; font-size: inherit !important; }
  table.quadro-notas td.comp, table.quadro-notas th.comp { text-align: left; text-transform: none; letter-spacing: 0; color: #111827; font-weight: 600; }
  table.quadro-notas td.num { text-align: center; }
  .fecho { margin-top: 36px; text-align: right; font-size: 11pt; }
  .assinaturas { margin-top: 60px; width: 100%; display: table; }
  .assinaturas .sig { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 16px; }
  .assinaturas .line { border-top: 1px solid #374151; margin: 0 auto 4px auto; width: 80%; padding-top: 4px; }
  .assinaturas .nome { font-size: 10pt; font-weight: bold; }
  .assinaturas .cargo { font-size: 9pt; color: #4b5563; }
  .footer { margin-top: 40px; text-align: center; font-size: 7.5pt; color: #9ca3af; }
  .page-break { page-break-after: always; break-after: page; }
CSS;
    }
}
